<?php
/**
 * Test email page template.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap edc-wrap">
    <h1><?php _e( 'Send Test Email', 'email-deliverability-checker' ); ?></h1>
    <p><?php _e( 'Enter an email address to send a test message and verify delivery.', 'email-deliverability-checker' ); ?></p>
    
    <form id="edc-test-form" class="edc-form">
        <input type="email" id="edc-recipient" placeholder="<?php esc_attr_e( 'recipient@example.com', 'email-deliverability-checker' ); ?>" required>
        <button type="submit"><?php _e( 'Send Test Email', 'email-deliverability-checker' ); ?></button>
    </form>
    
    <div id="edc-message"></div>
</div>
