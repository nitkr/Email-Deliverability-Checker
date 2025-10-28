<?php
/**
 * Uninstall script for Email Deliverability Checker.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Drop custom tables.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}edc_email_logs" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}edc_email_events" );

// Delete options.
delete_option( 'edc_email_stats' );
