<?php
/**
 * Shipping Export Class
 *
 * This class handles exporting orders that need shipped
 *
 * @package     Easy Digital Downloads - Flat Rate Shipping
 * @subpackage  Export Class
 * @copyright   Copyright (c) 2021, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.2
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class EDD_Flat_Rate_Shipping_Export extends EDD_Export {
	/**
	 * Our export type. Used for export-type specific filters / actions
	 *
	 * @access      public
	 * @var         string
	 * @since       1.2
	 */
	public $export_type = 'unshipped_orders';

	/**
	 * Set the CSV columns
	 *
	 * @access      public
	 * @since       1.2
	 * @return      array
	 */
	public function csv_cols() {
		$cols = array(
			'id'         => __( 'Order ID',   'edd-flat-rate-shipping' ),
			'date'       => __( 'Date', 'edd' ),
			'first_name' => __( 'First Name', 'edd-flat-rate-shipping' ),
			'last_name'  => __( 'Last Name', 'edd-flat-rate-shipping' ),
			'email'      => __( 'Email', 'edd-flat-rate-shipping' ),
			'address'    => __( 'Address', 'edd-flat-rate-shipping' ),
			'address2'   => __( 'Address Line 2', 'edd-flat-rate-shipping' ),
			'city'       => __( 'City', 'edd-flat-rate-shipping' ),
			'state'      => __( 'State / Province', 'edd-flat-rate-shipping' ),
			'zip'        => __( 'Zip / Postal Code', 'edd-flat-rate-shipping' ),
			'country'    => __( 'Country', 'edd-flat-rate-shipping' ),
			'amount'     => __( 'Amount', 'edd' ) . ' (' . html_entity_decode( edd_currency_filter( '' ) ) . ')',
			'tax'        => __( 'Tax', 'edd' ) . ' (' . html_entity_decode( edd_currency_filter( '' ) ) . ')',
			'gateway'    => __( 'Payment Method', 'edd' ),
			'key'        => __( 'Purchase Key', 'edd' ),
			'products'   => __( 'Products', 'edd' ),
			'status'     => __( 'Payment Status', 'edd' )
		);
		return $cols;
	}

	/**
	 * Get the data being exported
	 *
	 * @access      public
	 * @since       1.2
	 * @return      array
	 */
	public function get_data() {

		$data = array();

		$args = array(
			'nopaging'   => true,
			'fields'     => 'ids',
			'meta_query' => array(
				array(
					'key'   => '_edd_payment_shipping_status',
					'value' => '1',
				),
			),
		);

		$payments = edd_get_payments( $args );

		if ( $payments ) {
			foreach ( $payments as $payment_id ) {
				$user_info = edd_get_payment_meta_user_info( $payment_id );
				$downloads = edd_get_payment_meta_cart_details( $payment_id );
				$products  = '';

				if ( $downloads ) {
					foreach ( $downloads as $key => $download ) {

						// Display the Downoad Name
						$products .= get_the_title( $download['id'] );

						if ( $key != ( count( $downloads ) -1 ) ) {
							$products .= ' / ';
						}
					}
				}

				$address = edd_flat_rate_shipping_get_order_shipping_address( $payment_id );
				if ( ! $address ) {
					$address = array(
						'address'  => '',
						'address2' => '',
						'city'     => '',
						'state'    => '',
						'zip'      => '',
						'country'  => '',
					);
				}
				$payment = edd_get_payment( $payment_id );
				$data[]  = array(
					'id'         => $payment->ID,
					'date'       => $payment->date,
					'first_name' => $user_info['first_name'],
					'last_name'  => $user_info['last_name'],
					'email'      => $user_info['email'],
					'address'    => $address['address'],
					'address2'   => $address['address2'],
					'city'       => $address['city'],
					'state'      => $address['state'],
					'zip'        => $address['zip'],
					'country'    => $address['country'],
					'amount'     => $payment->total,
					'tax'        => $payment->tax,
					'gateway'    => $payment->gateway,
					'key'        => $payment->key,
					'products'   => $products,
					'status'     => $payment->status,
				);
			}
		}

		$data = apply_filters( 'edd_export_get_data', $data );
		$data = apply_filters( 'edd_export_get_data_' . $this->export_type, $data );

		return $data;
	}
}
