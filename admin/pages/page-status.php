<?php
/**
 * Status page template.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$checker = new EDC_Email_Checker();
$checks = $checker->get_all_checks();

$all_good = true;
foreach ( $checks as $check ) {
    if ( $check['status'] !== 'good' ) {
        $all_good = false;
        break;
    }
}
?>

<div class="wrap edc-wrap">
    <h1><?php _e( 'Email Deliverability Status', 'email-deliverability-checker' ); ?></h1>
    
    <?php if ( $all_good ) : ?>
        <div class="edc-highlight"><?php _e( 'Emails are working fine!', 'email-deliverability-checker' ); ?></div>
    <?php endif; ?>
    
    <?php foreach ( $checks as $key => $check ) : ?>
        <div class="edc-card edc-status-<?php echo esc_attr( $check['status'] ); ?>">
            <h2><?php echo esc_html( $check['label'] ); ?></h2>
            <p><?php echo esc_html( $check['description'] ); ?></p>
            <?php if ( ! empty( $check['actions'] ) ) : ?>
                <?php echo wp_kses_post( $check['actions'] ); ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
