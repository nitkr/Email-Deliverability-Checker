<?php
/**
 * Class for handling test emails.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EDC_Test_Email {

    public static function send_test_email_ajax() {
        check_ajax_referer( 'edc-nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'email-deliverability-checker' ) );
        }

        $recipient = sanitize_email( $_POST['recipient'] );
        if ( ! is_email( $recipient ) ) {
            wp_send_json_error( __( 'Invalid email address.', 'email-deliverability-checker' ) );
        }

        $subject = __( 'Test Email from Email Deliverability Checker', 'email-deliverability-checker' );
        $message = __( 'This is a test email to verify your WordPress email configuration.', 'email-deliverability-checker' );
        $headers = array( 'Content-Type: text/html; charset=UTF-8' );

        $last_error = '';
        $error_hook = function( $error ) use ( &$last_error ) {
            $last_error = $error->get_error_message();
        };
        add_action( 'wp_mail_failed', $error_hook );

        $sent = wp_mail( $recipient, $subject, $message, $headers );

        remove_action( 'wp_mail_failed', $error_hook );

        if ( $sent ) {
            wp_send_json_success( __( 'Test email sent successfully!', 'email-deliverability-checker' ) );
        } else {
            $error_message = __( 'Failed to send test email.', 'email-deliverability-checker' );
            if ( ! empty( $last_error ) ) {
                $error_message .= "\n" . __( 'Error details:', 'email-deliverability-checker' ) . ' ' . $last_error;
            } else {
                $error_message .= "\n" . __( 'Check your server logs or email configuration for more details.', 'email-deliverability-checker' );
            }
            wp_send_json_error( $error_message );
        }
    }
}
