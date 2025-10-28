<?php
/**
 * Email logs page template.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$list_table = new EDC_Logs_List_Table();
$list_table->prepare_items();
?>

<div class="wrap edc-wrap">
    <h1><?php esc_html_e( 'Email Logs', 'email-deliverability-checker' ); ?></h1>
    <p><?php esc_html_e( 'View all outgoing emails sent from your site, including tracking data.', 'email-deliverability-checker' ); ?></p>
    <p><?php esc_html_e( 'Note: For bounce tracking, configure your email provider\'s webhook to send bounce events to: ', 'email-deliverability-checker' ); ?><code><?php echo esc_url( rest_url( 'edc/v1/bounce' ) ); ?></code>. <?php esc_html_e( 'The plugin expects parameters like "event" = "bounce", "email", and "reason". Supports batch events.', 'email-deliverability-checker' ); ?></p>
    
    <form id="edc-logs-form" method="get">
        <input type="hidden" name="page" value="edc-logs" />
        <?php
        $list_table->search_box( __( 'Search Logs', 'email-deliverability-checker' ), 'edc-search' );
        $list_table->display();
        ?>
    </form>
</div>
