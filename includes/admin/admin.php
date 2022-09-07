<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class EDD_Flat_Rate_Shipping_Admin {

	public function __construct() {
		add_filter( 'edd_payments_table_columns',            array( $this, 'add_shipped_column' ) );
		add_filter( 'edd_payments_table_sortable_columns',   array( $this, 'add_sortable_column' ) );
		add_filter( 'edd_get_payments_args',                 array( $this, 'sort_payments' ) );
		add_filter( 'edd_payments_table_column',             array( $this, 'display_shipped_column_value' ), 10, 3 );
		add_filter( 'edd_payments_table_bulk_actions',       array( $this, 'register_bulk_action' ) );
		add_action( 'edd_payments_table_do_bulk_action',     array( $this, 'process_bulk_actions' ), 10, 2 );
		add_action( 'edd_reports_tab_export_content_bottom', array( $this, 'show_export_options' ) );
		add_action( 'edd_unshipped_orders_export',           array( $this, 'do_export' ) );
		add_filter( 'edd_address_type_labels', array( $this, 'add_address_label' ) );
	}

	/**
	 * Add a shipped status column to Payment History
	 *
	 * @since 1.0
	 *
	 * @access public
	 * @return array
	 */
	public function add_shipped_column( $columns ) {
		// Force the Shipped column to be placed just before Status
		unset( $columns['status'] );
		$columns['shipped'] = __( 'Shipped?', 'edd-flat-rate-shipping' );
		$columns['status']  = __( 'Status', 'edd-flat-rate-shipping' );
		return $columns;
	}

	/**
	 * Make the Shipped? column sortable
	 *
	 * @since 1.0
	 *
	 * @access public
	 * @return array
	 */
	public function add_sortable_column( $columns ) {
		$columns['shipped'] = array( 'shipped', false );
		return $columns;
	}

	/**
	 * Sort payment history by shipped status
	 *
	 * @since 1.0
	 *
	 * @access public
	 * @return array
	 */
	public function sort_payments( $args ) {

		if( isset( $_GET['orderby'] ) && $_GET['orderby'] == 'shipped' ) {

			$args['orderby'] = 'meta_value';
			$args['meta_key'] = '_edd_payment_shipping_status';

		}

		return $args;

	}

	/**
	 * Display the shipped status
	 *
	 * @since 1.0
	 *
	 * @access public
	 * @return string
	 */
	public function display_shipped_column_value( $value = '', $payment_id = 0, $column_name = '' ) {

		if ( 'shipped' !== $column_name ) {
			return $value;
		}

		$value        = __( 'N/A', 'edd-flat-rate-shipping' );
		$order_status = edd_get_payment_status( $payment_id );
		// Return early if an order has not been completed somehow.
		if ( ! in_array( $order_status, array( 'publish', 'complete', 'refunded', 'partially_refunded', 'revoked' ), true ) ) {
			return $value;
		}
		$shipping_status = (int) edd_get_payment_meta( $payment_id, '_edd_payment_shipping_status', true );
		if ( 1 === $shipping_status ) {
			$value = __( 'No', 'edd-flat-rate-shipping' );
		} elseif ( 2 === $shipping_status ) {
			$value = __( 'Yes', 'edd-flat-rate-shipping' );
		}

		return $value;
	}

	/**
	 * Register the bulk action for marking payments as Shipped
	 *
	 * @since 1.5
	 *
	 * @access public
	 * @return array
	 */
	public function register_bulk_action( $actions ) {
		$actions['set-as-shipped'] = __( 'Set as Shipped', 'edd-flat-rate-shipping' );
		return $actions;
	}

	/**
	 * Mark payments as shipped in bulk
	 *
	 * @since 1.5
	 *
	 * @access public
	 * @return array
	 */
	public function process_bulk_actions( $id, $action ) {
		if ( 'set-as-shipped' === $action ) {
			edd_update_payment_meta( $id, '_edd_payment_shipping_status', 2 );
		}
	}

	/**
	 * Add the shipped status column header
	 *
	 * @since 2.0
	 *
	 * @param object $order
	 * @return void
	 */
	public function shipped_column_header( $order ) {
		echo '<th>' . __( 'Shipped', 'edd-flat-rate-shipping' ) . '</th>';
	}

	/**
	 * Add the shipped status column header
	 *
	 * @since 2.0
	 *
	 * @param object $order
	 * @return void
	 */
	public function shipped_column_value( $order ) {

		$value           = __( 'N/A', 'edd-flat-rate-shipping' );
		$shipping_status = (int) edd_get_payment_meta( $order->ID, '_edd_payment_shipping_status', true );
		if ( 1 === $shipping_status ) {
			$value = __( 'No', 'edd-flat-rate-shipping' );
		} elseif ( 2 === $shipping_status ) {
			$value = __( 'Yes', 'edd-flat-rate-shipping' );
		}

		if ( 2 === $shipping_status ) {
			$new_status = '1';
		} else {
			$new_status = '2';
		}

		$toggle_url = esc_url( add_query_arg( array(
			'edd_action' => 'toggle_shipped_status',
			'order_id'   => $order->ID,
			'new_status' => $new_status
		) ) );

		$toggle_text = $shipped == '2' ? __( 'Mark as not shipped', 'edd-flat-rate-shipping' ) : __( 'Mark as shipped', 'edd-flat-rate-shipping' );

		echo '<td>' . esc_html( $value );
		if( $shipped ) {
			echo '<span class="edd-flat-rate-shipping-sep">&nbsp;&ndash;&nbsp;</span><a href="' . $toggle_url . '" class="edd-flat-rate-shipping-toggle-status">' . $toggle_text . '</a>';
		}
		echo '</td>';
	}

	/**
	 * Add the export unshipped orders box to the export screen
	 *
	 * @access      public
	 * @since       1.2
	 * @return      void
	 */
	public function show_export_options() {
		?>
		<div class="postbox">
			<h3><span><?php _e( 'Export Unshipped Orders to CSV', 'edd-flat-rate-shipping' ); ?></span></h3>
			<div class="inside">
				<p><?php _e( 'Download a CSV of all unshipped orders.', 'edd-flat-rate-shipping' ); ?></p>
				<p><a class="button" href="<?php echo wp_nonce_url( add_query_arg( array( 'edd-action' => 'unshipped_orders_export' ) ), 'edd_export_unshipped_orders' ); ?>"><?php _e( 'Generate CSV', 'edd-flat-rate-shipping' ) ; ?></a></p>
			</div><!-- .inside -->
		</div><!-- .postbox -->
	<?php
	}

	/**
	 * Trigger the CSV export
	 *
	 * @access      public
	 * @since       1.2
	 * @return      void
	 */
	public function do_export() {
		$flat_rate_shipping = edd_flat_rate_shipping();
		require_once $flat_rate_shipping->plugin_path . '/includes/admin/class-shipping-export.php';

		$export = new EDD_Flat_Rate_Shipping_Export();

		$export->export();
	}

	/**
	 * Adds "Shipping" as an address label in EDD 3.0.
	 *
	 * @since 2.3.9
	 * @param array $labels The array of address labels.
	 * @return array
	 */
	public function add_address_label( $labels ) {
		$labels['shipping'] = __( 'Shipping', 'edd-flat-rate-shipping' );

		return $labels;
	}
}
