<?php
/**
 * Logs list table class.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class EDC_Logs_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( array(
            'singular' => __( 'email log', 'email-deliverability-checker' ),
            'plural'   => __( 'email logs', 'email-deliverability-checker' ),
            'ajax'     => false,
        ) );
    }

    public function get_columns() {
        return array(
            'id'        => __( 'ID', 'email-deliverability-checker' ),
            'to_email'  => __( 'To', 'email-deliverability-checker' ),
            'subject'   => __( 'Subject', 'email-deliverability-checker' ),
            'sent_date' => __( 'Sent Date', 'email-deliverability-checker' ),
            'status'    => __( 'Status', 'email-deliverability-checker' ),
            'opens'     => __( 'Opens', 'email-deliverability-checker' ),
            'clicks'    => __( 'Clicks', 'email-deliverability-checker' ),
        );
    }

    public function prepare_items() {
        global $wpdb;

        $per_page = apply_filters( 'edc_logs_per_page', 20 );
        $current_page = $this->get_pagenum();

        // Handle search.
        $search_term = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';

        // Handle status filter.
        $status_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : 'all';

        // Handle date range.
        $from_date = isset( $_GET['from_date'] ) ? sanitize_text_field( $_GET['from_date'] ) : '';
        $to_date = isset( $_GET['to_date'] ) ? sanitize_text_field( $_GET['to_date'] ) : '';

        // Build query.
        $where = '1=1';
        if ( ! empty( $search_term ) ) {
            $search_term = '%' . $wpdb->esc_like( $search_term ) . '%';
            $where .= $wpdb->prepare( " AND (to_email LIKE %s OR subject LIKE %s OR message LIKE %s)", $search_term, $search_term, $search_term );
        }
        if ( 'all' !== $status_filter && in_array( $status_filter, array( 'sent', 'failed', 'bounced' ) ) ) {
            $where .= $wpdb->prepare( " AND status = %s", $status_filter );
        }
        if ( ! empty( $from_date ) ) {
            $where .= $wpdb->prepare( " AND sent_date >= %s", $from_date . ' 00:00:00' );
        }
        if ( ! empty( $to_date ) ) {
            $where .= $wpdb->prepare( " AND sent_date <= %s", $to_date . ' 23:59:59' );
        }

        $total_items = $wpdb->get_var( "SELECT COUNT(id) FROM {$wpdb->prefix}edc_email_logs WHERE $where" );

        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
        ) );

        $offset = ( $current_page - 1 ) * $per_page;

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array( $columns, $hidden, $sortable );

        $this->items = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}edc_email_logs WHERE $where ORDER BY id DESC LIMIT %d, %d",
            $offset,
            $per_page
        ), ARRAY_A );  // Use ARRAY_A to get associative arrays
    }

    public function get_sortable_columns() {
        return array(
            'id' => array( 'id', true ),
            'sent_date' => array( 'sent_date', true ),
            'status' => array( 'status', false ),
        );
    }

    protected function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'id':
            case 'sent_date':
            case 'status':
            case 'opens':
            case 'clicks':
                return $item[ $column_name ];
            case 'to_email':
                $to = maybe_unserialize( $item['to_email'] );
                return is_array( $to ) ? implode( ', ', $to ) : $to;
            case 'subject':
                return esc_html( $item['subject'] );
            default:
                return '';
        }
    }

    protected function column_status( $item ) {
        $status = $item['status'];
        $output = '<span class="edc-status-text">' . esc_html( ucfirst( $status ) ) . '</span>';

        if ( 'failed' === $status && ! empty( $item['error'] ) ) {
            $output .= '<br><a href="#" class="edc-toggle-error" data-log-id="' . esc_attr( $item['id'] ) . '">' . __( 'View Error', 'email-deliverability-checker' ) . '</a>';
            $output .= '<div class="edc-error-details" id="edc-error-' . esc_attr( $item['id'] ) . '" style="display:none;">';
            $output .= '<pre><code class="edc-error-content">' . esc_html( $item['error'] ) . '</code></pre>';
            $output .= '</div>';
        }

        return $output;
    }

    protected function extra_tablenav( $which ) {
        if ( 'top' === $which ) {
            $status_filter = isset( $_GET['status'] ) ? $_GET['status'] : 'all';
            $from_date = isset( $_GET['from_date'] ) ? $_GET['from_date'] : '';
            $to_date = isset( $_GET['to_date'] ) ? $_GET['to_date'] : '';
            ?>
            <div class="alignleft actions">
                <label for="status-filter" class="screen-reader-text"><?php _e( 'Filter by status', 'email-deliverability-checker' ); ?></label>
                <select name="status" id="status-filter">
                    <option value="all" <?php selected( $status_filter, 'all' ); ?>><?php _e( 'All', 'email-deliverability-checker' ); ?></option>
                    <option value="sent" <?php selected( $status_filter, 'sent' ); ?>><?php _e( 'Successful', 'email-deliverability-checker' ); ?></option>
                    <option value="failed" <?php selected( $status_filter, 'failed' ); ?>><?php _e( 'Failed', 'email-deliverability-checker' ); ?></option>
                    <option value="bounced" <?php selected( $status_filter, 'bounced' ); ?>><?php _e( 'Bounced', 'email-deliverability-checker' ); ?></option>
                </select>

                <label for="from-date" class="screen-reader-text"><?php _e( 'From date', 'email-deliverability-checker' ); ?></label>
                <input type="date" name="from_date" id="from-date" value="<?php echo esc_attr( $from_date ); ?>">

                <label for="to-date" class="screen-reader-text"><?php _e( 'To date', 'email-deliverability-checker' ); ?></label>
                <input type="date" name="to_date" id="to-date" value="<?php echo esc_attr( $to_date ); ?>">

                <input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'email-deliverability-checker' ); ?>">
            </div>
            <?php
        }
    }

    public function search_box( $text, $input_id ) {
        if ( empty( $_GET['s'] ) && ! $this->has_items() ) {
            return;
        }

        ?>
        <p class="search-box">
            <label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( $text ); ?>:</label>
            <input type="search" id="<?php echo esc_attr( $input_id ); ?>" name="s" value="<?php _admin_search_query(); ?>" />
            <?php submit_button( $text, 'button', '', false, array( 'id' => 'search-submit' ) ); ?>
        </p>
        <?php
    }
}
