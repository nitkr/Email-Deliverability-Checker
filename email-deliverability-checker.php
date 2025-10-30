<?php
/*
Plugin Name: Email Deliverability Checker
Plugin URI: https://librevious.com
Description: A WordPress plugin to check email deliverability status, integrate with Site Health, send test emails, and more.
Version: 1.0
Author: Nithin K R
Author URI: https://librevious.com
License: GPL-2.0+
Text Domain: email-deliverability-checker
Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

define( 'EDC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EDC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include core classes.
require_once EDC_PLUGIN_DIR . 'includes/class-email-checker.php';
require_once EDC_PLUGIN_DIR . 'includes/class-test-email.php';
require_once EDC_PLUGIN_DIR . 'includes/class-email-logger.php';
require_once EDC_PLUGIN_DIR . 'includes/class-edc-logs-list-table.php';

// Load text domain for translations.
function edc_load_textdomain() {
    load_plugin_textdomain( 'email-deliverability-checker', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'edc_load_textdomain' );

// Plugin activation hook.
register_activation_hook( __FILE__, 'edc_activate' );

function edc_activate() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $table_logs = $wpdb->prefix . 'edc_email_logs';
    $sql_logs = "CREATE TABLE $table_logs (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        to_email text NOT NULL,
        subject varchar(255) NOT NULL,
        message longtext NOT NULL,
        headers text NOT NULL,
        attachments text NOT NULL,
        sent_date datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        status varchar(20) NOT NULL DEFAULT 'sent',
        error text,
        opens int(11) NOT NULL DEFAULT 0,
        clicks int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (id)
    ) $charset_collate;";
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql_logs );

    $table_events = $wpdb->prefix . 'edc_email_events';
    $sql_events = "CREATE TABLE $table_events (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        log_id bigint(20) UNSIGNED NOT NULL,
        event_type varchar(20) NOT NULL,
        event_date datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        details text,
        PRIMARY KEY (id),
        KEY log_id (log_id)
    ) $charset_collate;";
    dbDelta( $sql_events );
}

// Register admin menu.
function edc_register_admin_menu() {
    add_menu_page(
        __( 'Email Deliverability', 'email-deliverability-checker' ),
        __( 'Email Checker', 'email-deliverability-checker' ),
        'manage_options',
        'edc-dashboard',
        'edc_render_dashboard',
        'dashicons-email-alt',
        80
    );

    add_submenu_page(
        'edc-dashboard',
        __( 'Status Check', 'email-deliverability-checker' ),
        __( 'Status', 'email-deliverability-checker' ),
        'manage_options',
        'edc-status',
        'edc_render_status_page'
    );

    add_submenu_page(
        'edc-dashboard',
        __( 'Test Email', 'email-deliverability-checker' ),
        __( 'Test Email', 'email-deliverability-checker' ),
        'manage_options',
        'edc-test-email',
        'edc_render_test_email_page'
    );

    add_submenu_page(
        'edc-dashboard',
        __( 'Email Logs', 'email-deliverability-checker' ),
        __( 'Email Logs', 'email-deliverability-checker' ),
        'manage_options',
        'edc-logs',
        'edc_render_logs_page'
    );
}
add_action( 'admin_menu', 'edc_register_admin_menu' );

// Enqueue admin styles and scripts.
function edc_enqueue_admin_assets( $hook ) {
    if ( strpos( $hook, 'edc-' ) !== false ) {
        wp_enqueue_style( 'edc-admin-style', EDC_PLUGIN_URL . 'admin/css/admin-style.css', array(), '1.3.0' );
        wp_enqueue_script( 'edc-admin-script', EDC_PLUGIN_URL . 'admin/js/admin-script.js', array( 'jquery' ), '1.3.0', true );
        wp_localize_script( 'edc-admin-script', 'edc_ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'edc-nonce' ),
            'log_note' => __( 'Log: This error occurred during the test email send attempt. Review your email settings or contact support if the issue persists.', 'email-deliverability-checker' )
        ) );
    }
}
add_action( 'admin_enqueue_scripts', 'edc_enqueue_admin_assets' );

// Render dashboard with widgets.
function edc_render_dashboard() {
    $logger = new EDC_Email_Logger();
    $stats = $logger->get_email_stats();
    ?>
    <div class="wrap edc-wrap">
        <h1><?php _e( 'Email Deliverability Checker', 'email-deliverability-checker' ); ?></h1>
        <p><?php _e( 'Manage your email settings and monitor deliverability.', 'email-deliverability-checker' ); ?></p>
        
        <div class="edc-dashboard-widgets">
            <div class="edc-card">
                <h2><?php _e( 'Status Check', 'email-deliverability-checker' ); ?></h2>
                <p><?php _e( 'Verify DNS records and email provider configuration.', 'email-deliverability-checker' ); ?></p>
                <a href="<?php echo admin_url( 'admin.php?page=edc-status' ); ?>" class="button button-primary"><?php _e( 'Check Status', 'email-deliverability-checker' ); ?></a>
            </div>
            
            <div class="edc-card">
                <h2><?php _e( 'Send Test Email', 'email-deliverability-checker' ); ?></h2>
                <p><?php _e( 'Test your email sending functionality.', 'email-deliverability-checker' ); ?></p>
                <a href="<?php echo admin_url( 'admin.php?page=edc-test-email' ); ?>" class="button button-primary"><?php _e( 'Send Test', 'email-deliverability-checker' ); ?></a>
            </div>
            
            <div class="edc-card">
                <h2><?php _e( 'Email Statistics', 'email-deliverability-checker' ); ?></h2>
                <p><?php _e( 'Overview of emails sent from your site.', 'email-deliverability-checker' ); ?></p>
                <ul>
                    <li><?php printf( __( 'Total Emails Sent: %d', 'email-deliverability-checker' ), $stats['total_sent'] ); ?></li>
                    <li><?php printf( __( 'Successful Sends: %d', 'email-deliverability-checker' ), $stats['successful'] ); ?></li>
                    <li><?php printf( __( 'Failed Sends: %d', 'email-deliverability-checker' ), $stats['failed'] ); ?></li>
                </ul>
                <p><?php _e( 'Note: Statistics are based on wp_mail attempts. Actual delivery depends on your email provider.', 'email-deliverability-checker' ); ?></p>
            </div>
        </div>
        
        <p><?php _e( 'More features coming soon!', 'email-deliverability-checker' ); ?></p>
    </div>
    <?php
}

// Render status page.
function edc_render_status_page() {
    include EDC_PLUGIN_DIR . 'admin/pages/page-status.php';
}

// Render test email page.
function edc_render_test_email_page() {
    include EDC_PLUGIN_DIR . 'admin/pages/page-test-email.php';
}

// Render logs page.
function edc_render_logs_page() {
    include EDC_PLUGIN_DIR . 'admin/pages/page-logs.php';
}

// AJAX handler for test email (called from JS).
add_action( 'wp_ajax_edc_send_test_email', array( 'EDC_Test_Email', 'send_test_email_ajax' ) );

// Integrate with Site Health.
$email_checker = new EDC_Email_Checker();
add_filter( 'site_status_tests', array( $email_checker, 'add_site_health_tests' ) );
add_action( 'rest_api_init', array( $email_checker, 'register_rest_endpoints' ) );

// Register webhook for bounces.
add_action( 'rest_api_init', 'edc_register_webhook' );

function edc_register_webhook() {
    register_rest_route( 'edc/v1', '/bounce', array(
        'methods' => 'POST',
        'callback' => 'edc_handle_bounce',
        'permission_callback' => '__return_true', // Public, rely on provider auth.
    ) );
}

function edc_handle_bounce( $request ) {
    $params = $request->get_params();
    global $wpdb;

    // Handle single event or array (e.g., SendGrid batch).
    $events = is_array( $params ) && isset( $params[0] ) ? $params : array( $params );

    foreach ( $events as $event ) {
        if ( isset( $event['event'] ) && 'bounce' === $event['event'] && isset( $event['email'] ) ) {
            $email = $event['email'];
            $reason = isset( $event['reason'] ) ? $event['reason'] : '';

            // Find the latest log matching the email.
            $log_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}edc_email_logs WHERE to_email LIKE '%%%s%%' ORDER BY id DESC LIMIT 1",
                like_escape( $email )
            ) );

            if ( $log_id ) {
                $wpdb->update( $wpdb->prefix . 'edc_email_logs', array( 'status' => 'bounced' ), array( 'id' => $log_id ) );
                $wpdb->insert( $wpdb->prefix . 'edc_email_events', array(
                    'log_id' => $log_id,
                    'event_type' => 'bounce',
                    'event_date' => current_time( 'mysql' ),
                    'details' => $reason,
                ) );
            }
        }
    }

    return new WP_REST_Response( 'OK', 200 );
}

// Handle tracking requests (open, click).
add_action( 'init', 'edc_handle_tracking' );

function edc_handle_tracking() {
    if ( isset( $_GET['edc_track'] ) ) {
        global $wpdb;
        $type = sanitize_text_field( $_GET['edc_track'] );
        $id = intval( $_GET['id'] );
        if ( $id <= 0 ) {
            return;
        }

        if ( 'open' === $type ) {
            $key = sanitize_text_field( $_GET['key'] );
            if ( wp_verify_nonce( $key, 'edc_open_' . $id ) ) {
                $wpdb->insert( $wpdb->prefix . 'edc_email_events', array(
                    'log_id' => $id,
                    'event_type' => 'open',
                    'event_date' => current_time( 'mysql' ),
                ) );
                $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}edc_email_logs SET opens = opens + 1 WHERE id = %d", $id ) );
            }
            // Output transparent 1x1 GIF pixel.
            header( 'Content-Type: image/gif' );
            echo base64_decode( 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' );
            exit;
        } elseif ( 'click' === $type ) {
            $url = base64_decode( $_GET['url'] );
            $hash = sanitize_text_field( $_GET['hash'] );
            if ( hash( 'sha256', $url ) === $hash ) {
                $wpdb->insert( $wpdb->prefix . 'edc_email_events', array(
                    'log_id' => $id,
                    'event_type' => 'click',
                    'event_date' => current_time( 'mysql' ),
                    'details' => $url,
                ) );
                $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}edc_email_logs SET clicks = clicks + 1 WHERE id = %d", $id ) );
                wp_redirect( $url );
                exit;
            }
        }
    }
}

// Blacklist check function.
function edc_check_blacklists() {
    $domain = wp_parse_url( home_url(), PHP_URL_HOST );
    $blacklists = array(
        'dbl.spamhaus.org' => 'Spamhaus DBL',
        'multi.uribl.com' => 'URIBL Multi',
        'black.uribl.com' => 'URIBL Black',
    );

    $checked = array();
    $listed = array();

    foreach ( $blacklists as $bl => $name ) {
        $checked[] = $name;
        $query = $domain . '.' . $bl;
        $records = @dns_get_record( $query, DNS_A ) ?: array();
        if ( ! empty( $records ) ) {
            $listed[] = $name . ' (' . $bl . ')';
        }
    }

    $learn_more = 'https://www.spamhaus.org/domain-block-list/';

    return array(
        'checked' => $checked,
        'listed' => $listed,
        'learn_more' => $learn_more,
    );
}

// Initialize email logger.
$email_logger = new EDC_Email_Logger();
add_filter( 'wp_mail', array( $email_logger, 'log_attempt' ) );
add_action( 'wp_mail_failed', array( $email_logger, 'log_failure' ) );
