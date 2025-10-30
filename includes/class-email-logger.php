<?php
/**
 * Class for email logging and stats.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EDC_Email_Logger {

    const STATS_OPTION = 'edc_email_stats';

    public $last_log_id = 0;

    public function __construct() {
        // Initialize stats if not exist.
        if ( ! get_option( self::STATS_OPTION ) ) {
            update_option( self::STATS_OPTION, array(
                'total_sent' => 0,
                'successful' => 0,
                'failed' => 0,
            ) );
        }
    }

    public function log_attempt( $mail ) {
        global $wpdb;
        $stats = get_option( self::STATS_OPTION );
        $stats['total_sent']++;
        update_option( self::STATS_OPTION, $stats );

        // Log full email details.
        $to = is_array( $mail['to'] ) ? serialize( $mail['to'] ) : $mail['to'];
        $headers = is_array( $mail['headers'] ) ? serialize( $mail['headers'] ) : $mail['headers'];
        $attachments = is_array( $mail['attachments'] ) ? serialize( $mail['attachments'] ) : $mail['attachments'];

        $wpdb->insert( $wpdb->prefix . 'edc_email_logs', array(
            'to_email' => $to,
            'subject' => $mail['subject'],
            'message' => $mail['message'],
            'headers' => $headers,
            'attachments' => $attachments,
            'sent_date' => current_time( 'mysql' ),
            'status' => 'sent',
        ) );

        $this->last_log_id = $wpdb->insert_id;

        // Add tracking if HTML email.
        $is_html = false;
        if ( is_array( $mail['headers'] ) ) {
            foreach ( $mail['headers'] as $header ) {
                if ( stripos( $header, 'Content-Type: text/html' ) !== false ) {
                    $is_html = true;
                    break;
                }
            }
        } elseif ( stripos( $mail['headers'], 'Content-Type: text/html' ) !== false ) {
            $is_html = true;
        }

        if ( $is_html && $this->last_log_id ) {
            // Add open tracking pixel.
            $nonce = wp_create_nonce( 'edc_open_' . $this->last_log_id );
            $pixel_url = add_query_arg( array(
                'edc_track' => 'open',
                'id' => $this->last_log_id,
                'key' => $nonce,
            ), home_url( '/' ) );
            $pixel = '<img src="' . esc_url( $pixel_url ) . '" width="1" height="1" alt="" style="display:none;" />';
            $mail['message'] .= $pixel;

            // Rewrite links for click tracking.
            libxml_use_internal_errors( true ); // Suppress warnings.
            $dom = new DOMDocument();
            $dom->loadHTML( mb_convert_encoding( $mail['message'], 'HTML-ENTITIES', 'UTF-8' ), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
            $links = $dom->getElementsByTagName( 'a' );
            foreach ( $links as $link ) {
                $original_url = $link->getAttribute( 'href' );
                if ( ! empty( $original_url ) && strpos( $original_url, 'http' ) === 0 && strpos( $original_url, 'mailto:' ) === false ) {
                    $hash = hash( 'sha256', $original_url );
                    $track_url = add_query_arg( array(
                        'edc_track' => 'click',
                        'id' => $this->last_log_id,
                        'url' => base64_encode( $original_url ),
                        'hash' => $hash,
                    ), home_url( '/' ) );
                    $link->setAttribute( 'href', $track_url );
                }
            }
            $mail['message'] = $dom->saveHTML();
            libxml_clear_errors();
        }

        return $mail;
    }

    public function log_failure( $error ) {
        global $wpdb;
        $stats = get_option( self::STATS_OPTION );
        $stats['failed']++;
        update_option( self::STATS_OPTION, $stats );

        if ( $this->last_log_id > 0 ) {
            $wpdb->update( $wpdb->prefix . 'edc_email_logs', array(
                'status' => 'failed',
                'error' => $error->get_error_message(),
            ), array( 'id' => $this->last_log_id ) );
        }
    }

    public function get_email_stats() {
        $stats = get_option( self::STATS_OPTION, array(
            'total_sent' => 0,
            'successful' => 0,
            'failed' => 0,
        ) );
        $stats['successful'] = $stats['total_sent'] - $stats['failed'];
        return $stats;
    }
}
