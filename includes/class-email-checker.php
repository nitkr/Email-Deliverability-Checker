<?php
/**
 * Core class for email checks.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EDC_Email_Checker {

    public function add_site_health_tests( $tests ) {
        $tests['direct']['email_spf'] = array(
            'label' => __( 'SPF Record Check', 'email-deliverability-checker' ),
            'test'  => array( $this, 'check_spf' ),
        );

        $tests['direct']['email_dmarc'] = array(
            'label' => __( 'DMARC Record Check', 'email-deliverability-checker' ),
            'test'  => array( $this, 'check_dmarc' ),
        );

        $tests['direct']['email_mx'] = array(
            'label' => __( 'MX Record Check', 'email-deliverability-checker' ),
            'test'  => array( $this, 'check_mx' ),
        );

        $tests['direct']['email_dkim'] = array(
            'label' => __( 'DKIM Record Check', 'email-deliverability-checker' ),
            'test'  => array( $this, 'check_dkim' ),
        );

        $tests['direct']['email_blacklist'] = array(
            'label' => __( 'Email Blacklist Monitor', 'email-deliverability-checker' ),
            'test'  => array( $this, 'check_blacklist' ),
        );

        $tests['async']['email_provider_config'] = array(
            'label' => __( 'Email Provider Configuration', 'email-deliverability-checker' ),
            'test'  => rest_url( 'email-deliverability-checker/v1/check-provider-config' ),
        );

        return $tests;
    }

    public function register_rest_endpoints() {
        register_rest_route( 'email-deliverability-checker/v1', '/check-provider-config', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'perform_provider_config_check' ),
            'permission_callback' => function() {
                return current_user_can( 'view_site_health_checks' );
            },
        ) );
    }

    public function check_spf() {
        $domain  = wp_parse_url( home_url(), PHP_URL_HOST );
        $records = @dns_get_record( $domain, DNS_TXT ) ?: array();
        $spf_record = false;

        foreach ( $records as $record ) {
            if ( isset( $record['txt'] ) && strpos( $record['txt'], 'v=spf1' ) === 0 ) {
                $spf_record = true;
                break;
            }
        }

        $result = $this->get_base_result( 'email_spf' );

        if ( ! $spf_record ) {
            $result['status']      = 'critical';
            $result['label']       = __( 'No SPF record found', 'email-deliverability-checker' );
            $result['description'] = __( 'Your domain does not have an SPF record set up.', 'email-deliverability-checker' );
            $result['actions']     = $this->get_spf_action();
            $result['badge']['color'] = 'red';
        } else {
            $result['status']      = 'good';
            $result['label']       = __( 'SPF record is set', 'email-deliverability-checker' );
            $result['description'] = __( 'Your domain has an SPF record.', 'email-deliverability-checker' );
        }

        return $result;
    }

    public function check_dmarc() {
        $domain  = wp_parse_url( home_url(), PHP_URL_HOST );
        $records = @dns_get_record( '_dmarc.' . $domain, DNS_TXT ) ?: array();
        $dmarc_record = false;

        foreach ( $records as $record ) {
            if ( isset( $record['txt'] ) && strpos( $record['txt'], 'v=DMARC1' ) === 0 ) {
                $dmarc_record = true;
                break;
            }
        }

        $result = $this->get_base_result( 'email_dmarc' );

        if ( ! $dmarc_record ) {
            $result['status']      = 'recommended';
            $result['label']       = __( 'No DMARC record found', 'email-deliverability-checker' );
            $result['description'] = __( 'Your domain does not have a DMARC record.', 'email-deliverability-checker' );
            $result['actions']     = $this->get_dmarc_action();
            $result['badge']['color'] = 'orange';
        } else {
            $result['status']      = 'good';
            $result['label']       = __( 'DMARC record is set', 'email-deliverability-checker' );
            $result['description'] = __( 'Your domain has a DMARC record.', 'email-deliverability-checker' );
        }

        return $result;
    }

    public function check_mx() {
        $domain = wp_parse_url( home_url(), PHP_URL_HOST );
        $mx_records = @dns_get_record( $domain, DNS_MX ) ?: array();

        $result = $this->get_base_result( 'email_mx' );

        if ( empty( $mx_records ) ) {
            $result['status']      = 'critical';
            $result['label']       = __( 'No MX records found', 'email-deliverability-checker' );
            $result['description'] = __( 'Your domain does not have MX records set up. This may prevent receiving emails.', 'email-deliverability-checker' );
            $result['actions']     = $this->get_mx_action();
            $result['badge']['color'] = 'red';
        } else {
            $result['status']      = 'good';
            $result['label']       = __( 'MX records are set', 'email-deliverability-checker' );
            $mx_list = array_map( function( $rec ) {
                return $rec['target'] . ' (priority ' . $rec['pri'] . ')';
            }, $mx_records );
            $result['description'] = __( 'Your domain has the following MX records: ', 'email-deliverability-checker' ) . implode( ', ', $mx_list ) . '.';
        }

        return $result;
    }

    public function check_dkim() {
        $domain = wp_parse_url( home_url(), PHP_URL_HOST );
        $dkim_records = @dns_get_record( '_domainkey.' . $domain, DNS_TXT ) ?: array();
        $dkim_record = false;

        foreach ( $dkim_records as $record ) {
            if ( isset( $record['txt'] ) && strpos( $record['txt'], 'v=DKIM1' ) === 0 ) {
                $dkim_record = true;
                break;
            }
        }

        $result = $this->get_base_result( 'email_dkim' );

        if ( ! $dkim_record ) {
            $result['status']      = 'recommended';
            $result['label']       = __( 'No DKIM record found', 'email-deliverability-checker' );
            $result['description'] = __( 'Your domain does not have a DKIM record set up. DKIM helps authenticate emails and improve deliverability. Note: DKIM requires a specific selector (e.g., default._domainkey); check your email provider for the correct selector.', 'email-deliverability-checker' );
            $result['actions']     = $this->get_dkim_action();
            $result['badge']['color'] = 'orange';
        } else {
            $result['status']      = 'good';
            $result['label']       = __( 'DKIM record is set', 'email-deliverability-checker' );
            $result['description'] = __( 'Your domain has a DKIM record configured.', 'email-deliverability-checker' );
        }

        return $result;
    }

    public function check_blacklist() {
        $blacklist_results = edc_check_blacklists();
        $result = $this->get_base_result( 'email_blacklist' );

        if ( empty( $blacklist_results['listed'] ) ) {
            $result['status']      = 'good';
            $result['label']       = __( 'Domain not blacklisted', 'email-deliverability-checker' );
            $result['description'] = __( 'Your domain is not listed on any checked blacklists.', 'email-deliverability-checker' );
        } else {
            $result['status']      = 'critical';
            $result['label']       = __( 'Domain blacklisted', 'email-deliverability-checker' );
            $result['description'] = __( 'Your domain is listed on the following blacklists: ', 'email-deliverability-checker' ) . implode( ', ', $blacklist_results['listed'] ) . '. ' . sprintf( __( 'Checked %d blacklists.', 'email-deliverability-checker' ), count( $blacklist_results['checked'] ) );
            $result['actions']     = sprintf( '<p><a href="%s" target="_blank" rel="noopener">%s</a></p>', esc_url( $blacklist_results['learn_more'] ), __( 'Learn how to remove from blacklists', 'email-deliverability-checker' ) );
            $result['badge']['color'] = 'red';
        }

        return $result;
    }

    public function perform_provider_config_check( $request ) {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $wpms_active = is_plugin_active( 'wp-mail-smtp/wp_mail_smtp.php' );

        $result = $this->get_base_result( 'email_provider_config' );

        if ( ! $wpms_active ) {
            $result['status']      = 'recommended';
            $result['label']       = __( 'No SMTP plugin configured', 'email-deliverability-checker' );
            $result['description'] = __( 'Your site is using the default PHP mail function, which can lead to deliverability issues. Consider installing an SMTP plugin for reliable email sending.', 'email-deliverability-checker' );
            $result['actions']     = $this->get_smtp_install_action();
            $result['badge']['color'] = 'orange';
            return $result;
        }

        $option = get_option( 'wp_mail_smtp', array() );
        $mailer = isset( $option['mail']['mailer'] ) ? $option['mail']['mailer'] : 'mail';

        if ( $mailer === 'mail' || $mailer === 'default' ) {
            $result['status']      = 'recommended';
            $result['label']       = __( 'Using default PHP mailer', 'email-deliverability-checker' );
            $result['description'] = __( 'WP Mail SMTP is installed, but set to use the default PHP mail function. For better deliverability, configure an SMTP or API-based mailer.', 'email-deliverability-checker' );
            $result['actions']     = $this->get_smtp_config_action();
            $result['badge']['color'] = 'orange';
            return $result;
        }

        // Mailer-specific validation.
        $is_valid = false;
        $description = '';
        $status = 'critical';
        $label = __( 'Email provider configuration invalid', 'email-deliverability-checker' );
        $badge_color = 'red';

        switch ( $mailer ) {
            case 'sendgrid':
                $api_key = isset( $option['sendgrid']['api_key'] ) ? $option['sendgrid']['api_key'] : '';
                if ( empty( $api_key ) ) {
                    $description = __( 'SendGrid API key is not set.', 'email-deliverability-checker' );
                    break;
                }
                $response = wp_remote_get( 'https://api.sendgrid.com/v3/user/account', array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $api_key,
                    ),
                ) );
                if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
                    $is_valid = true;
                } else {
                    $description = __( 'Unable to validate SendGrid API key.', 'email-deliverability-checker' );
                }
                break;

            case 'mailgun':
                $api_key = isset( $option['mailgun']['api_key'] ) ? $option['mailgun']['api_key'] : '';
                $domain = isset( $option['mailgun']['domain'] ) ? $option['mailgun']['domain'] : '';
                if ( empty( $api_key ) || empty( $domain ) ) {
                    $description = __( 'Mailgun API key or domain not set.', 'email-deliverability-checker' );
                    break;
                }
                $response = wp_remote_get( 'https://api.mailgun.net/v3/domains/' . rawurlencode( $domain ), array(
                    'headers' => array(
                        'Authorization' => 'Basic ' . base64_encode( 'api:' . $api_key ),
                    ),
                ) );
                if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
                    $is_valid = true;
                } else {
                    $description = __( 'Unable to validate Mailgun credentials.', 'email-deliverability-checker' );
                }
                break;

            case 'sendinblue': // Brevo
                $api_key = isset( $option['sendinblue']['api_key'] ) ? $option['sendinblue']['api_key'] : '';
                if ( empty( $api_key ) ) {
                    $description = __( 'Brevo API key not set.', 'email-deliverability-checker' );
                    break;
                }
                $response = wp_remote_get( 'https://api.brevo.com/v3/account', array(
                    'headers' => array(
                        'api-key' => $api_key,
                        'Accept'  => 'application/json',
                    ),
                ) );
                if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
                    $is_valid = true;
                } else {
                    $description = __( 'Unable to validate Brevo API key.', 'email-deliverability-checker' );
                }
                break;

            case 'postmark':
                $api_key = isset( $option['postmark']['api_key'] ) ? $option['postmark']['api_key'] : '';
                if ( empty( $api_key ) ) {
                    $description = __( 'Postmark API key not set.', 'email-deliverability-checker' );
                    break;
                }
                $response = wp_remote_get( 'https://api.postmarkapp.com/server', array(
                    'headers' => array(
                        'X-Postmark-Server-Token' => $api_key,
                        'Accept'                  => 'application/json',
                    ),
                ) );
                if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
                    $is_valid = true;
                } else {
                    $description = __( 'Unable to validate Postmark API key.', 'email-deliverability-checker' );
                }
                break;

            case 'smtp':
                $host = isset( $option['smtp']['host'] ) ? $option['smtp']['host'] : '';
                $port = isset( $option['smtp']['port'] ) ? (int) $option['smtp']['port'] : 25;
                $encryption = isset( $option['smtp']['encryption'] ) ? $option['smtp']['encryption'] : 'none';
                if ( empty( $host ) ) {
                    $description = __( 'SMTP host not set.', 'email-deliverability-checker' );
                    break;
                }
                $prefix = ( $encryption === 'ssl' ) ? 'ssl://' : '';
                if ( $encryption === 'tls' ) {
                    $port = $port ?: 587;
                }
                $connection = @fsockopen( $prefix . $host, $port, $errno, $errstr, 5 );
                if ( is_resource( $connection ) ) {
                    fclose( $connection );
                    $is_valid = true;
                } else {
                    $description = sprintf( __( 'Unable to connect to SMTP server: %s (%d)', 'email-deliverability-checker' ), $errstr, $errno );
                }
                break;

            case 'gmail':
            case 'outlook':
                // For OAuth mailers, check if access token is set.
                $access_token = isset( $option[$mailer]['access_token'] ) ? $option[$mailer]['access_token'] : '';
                if ( ! empty( $access_token ) ) {
                    $is_valid = true;
                } else {
                    $description = __( 'OAuth access token not set or expired.', 'email-deliverability-checker' );
                }
                break;

            default:
                // For other mailers, check if key fields are set (generic).
                if ( isset( $option[$mailer] ) && ! empty( $option[$mailer] ) ) {
                    $is_valid = true;
                    $description = __( 'Configuration fields are set, but validation not implemented for this mailer.', 'email-deliverability-checker' );
                    $status = 'recommended';
                    $label = __( 'Email provider configured (unvalidated)', 'email-deliverability-checker' );
                    $badge_color = 'orange';
                } else {
                    $description = __( 'Configuration for the selected mailer is incomplete.', 'email-deliverability-checker' );
                }
                break;
        }

        if ( $is_valid ) {
            $status = 'good';
            $label = __( 'Email provider configuration is valid', 'email-deliverability-checker' );
            $description = __( 'Your email provider settings appear to be correctly configured.', 'email-deliverability-checker' );
            $badge_color = 'blue';
        } elseif ( empty( $description ) ) {
            $description = __( 'Unable to validate the configuration for the selected mailer.', 'email-deliverability-checker' );
        }

        $result['status'] = $status;
        $result['label'] = $label;
        $result['description'] = $description;
        $result['badge']['color'] = $badge_color;

        if ( $status !== 'good' ) {
            $result['actions'] = $this->get_smtp_config_action();
        }

        return $result;
    }

    private function get_base_result( $test ) {
        return array(
            'badge'     => array(
                'label' => __( 'Email', 'email-deliverability-checker' ),
                'color' => 'blue',
            ),
            'actions'   => '',
            'test'      => $test,
        );
    }

    private function get_spf_action() {
        return sprintf(
            '<p><a href="%s" target="_blank" rel="noopener">%s</a></p>',
            'https://www.cloudflare.com/learning/email-security/what-is-spf/',
            __( 'Learn how to set up SPF', 'email-deliverability-checker' )
        );
    }

    private function get_dmarc_action() {
        return sprintf(
            '<p><a href="%s" target="_blank" rel="noopener">%s</a></p>',
            'https://www.cloudflare.com/learning/email-security/dmarc-what-is-it-how-does-it-work/',
            __( 'Learn how to set up DMARC', 'email-deliverability-checker' )
        );
    }

    private function get_mx_action() {
        return sprintf(
            '<p><a href="%s" target="_blank" rel="noopener">%s</a></p>',
            'https://www.cloudflare.com/learning/dns/dns-records/dns-mx-record/',
            __( 'Learn how to set up MX records', 'email-deliverability-checker' )
        );
    }

    private function get_dkim_action() {
        return sprintf(
            '<p><a href="%s" target="_blank" rel="noopener">%s</a></p>',
            'https://www.cloudflare.com/learning/email-security/dkim/',
            __( 'Learn how to set up DKIM', 'email-deliverability-checker' )
        );
    }

    private function get_smtp_install_action() {
        return sprintf(
            '<p><a href="%s" target="_blank" rel="noopener">%s</a></p>',
            'https://wordpress.org/plugins/wp-mail-smtp/',
            __( 'Install WP Mail SMTP', 'email-deliverability-checker' )
        );
    }

    private function get_smtp_config_action() {
        return sprintf(
            '<p><a href="%s">%s</a></p>',
            admin_url( 'admin.php?page=wp-mail-smtp' ),
            __( 'Review WP Mail SMTP settings', 'email-deliverability-checker' )
        );
    }

    public function get_all_checks() {
        $provider_check = $this->perform_provider_config_check( new WP_REST_Request( 'GET', '' ) );
        return array(
            'spf' => $this->check_spf(),
            'dmarc' => $this->check_dmarc(),
            'mx' => $this->check_mx(),
            'dkim' => $this->check_dkim(),
            'blacklist' => $this->check_blacklist(),
            'provider' => $provider_check,
        );
    }
}
