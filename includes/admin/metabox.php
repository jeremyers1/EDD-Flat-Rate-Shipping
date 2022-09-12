<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class EDD_Flat_Rate_Shipping_Metabox {

	public function __construct() {
		add_action( 'edd_meta_box_fields',           array( $this, 'metabox' ), 10 );
		add_action( 'edd_updated_edited_purchase',   array( $this, 'save_payment' ) );
		add_filter( 'edd_metabox_fields_save',       array( $this, 'meta_fields_save' ) );
	}

	/**
	 * Render the extra meta box fields
	 *
	 * @since 1.0.0
	 *
	 * @access private
	 * @return void
	 */
	public function metabox( $post_id = 0 ) {
		$enabled = get_post_meta( $post_id, '_edd_enable_shipping', true );

		?>
		<div id="edd_flat_rate_shipping">
		 <p><strong><?php _e( 'Shipping Option', 'edd-flat-rate-shipping' ); ?></strong></p>
			<p>
				<label for="edd_enable_shipping">
					<input type="checkbox" name="_edd_enable_shipping" id="edd_enable_shipping" value="1"<?php checked( 1, $enabled ); ?>/>
					<?php printf( __( 'This %s requires the flat-rate shipping fee.', 'edd-flat-rate-shipping' ), edd_get_label_singular() ); ?>
				</label>
			</p>
		</div>
	<?php
	}

	/**
	 * Save the shipping details on payment edit
	 *
	 * @since 1.0.0
	 *
	 * @access private
	 * @return void
	 */
	public function save_payment( $payment_id = 0 ) {

		$address = isset( $_POST['edd-payment-shipping-address'] ) ? $_POST['edd-payment-shipping-address'] : false;
		if ( ! $address ) {
			return;
		}

		$stored_address = edd_flat_rate_shipping_get_order_shipping_address( $payment_id );
		$address_id     = ! empty( $stored_address['id'] ) ? $stored_address['id'] : false;
		if ( $address_id ) {
			$new_address                = $address[0];
			$new_address['region']      = $new_address['state'];
			$new_address['postal_code'] = $new_address['zip'];
			edd_update_order_address(
				$address_id,
				$new_address
			);
		} else {
			$meta                       = edd_get_payment_meta( $payment_id );
			$user_info                  = $meta['user_info'];
			$user_info['shipping_info'] = $address[0];
			$meta['user_info']          = $user_info;
			edd_update_payment_meta( $payment_id, '_edd_payment_meta', $meta );
		}

		if ( isset( $_POST['edd-payment-shipped'] ) ) {
			edd_update_payment_meta( $payment_id, '_edd_payment_shipping_status', 2 );
		} elseif ( edd_get_payment_meta( $payment_id, '_edd_payment_shipping_status', true ) ) {
			edd_update_payment_meta( $payment_id, '_edd_payment_shipping_status', 1 );
		}
	}

	/**
	 * Save our extra meta box fields
	 *
	 * @since 1.0.0
	 *
	 * @access private
	 * @return array
	 */
	public function meta_fields_save( $fields ) {

		// Tell EDD to save our extra meta fields
		$fields[] = '_edd_enable_shipping';
		return $fields;
	}
}
