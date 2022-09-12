<?php
/**
 * Privacy Functions
 *
 * @package     EDD/FlatRateShipping
 * @subpackage  Functions
 * @copyright   Copyright (c) 2021, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/** Export Functions */

/**
 * Register any of our Privacy Data Exporters
 *
 * @param $exporters
 *
 * @return array
 */
function edd_flat_rate_shipping_register_privacy_exporters( $exporters ) {

	$exporters[] = array(
		'exporter_friendly_name' => __( 'Customer Shipping Addresses', 'edd-flat-rate-shipping' ),
		'callback'               => 'edd_privacy_flat_rate_shipping_addresses_exporter',
	);

	return $exporters;

}
add_filter( 'wp_privacy_personal_data_exporters', 'edd_flat_rate_shipping_register_privacy_exporters' );

/**
 * Retrieves the shipping address information for the Privacy Exporter
 *
 * @param string $email_address
 * @param int    $page
 *
 * @return array
 */
function edd_privacy_flat_rate_shipping_addresses_exporter( $email_address = '', $page = 1 ) {

	$customer = new EDD_Customer( $email_address );

	$shipping_addresses = edd_flat_rate_shipping()->get_customer_shipping_addresses( $customer->id );
	if ( empty( $shipping_addresses ) ) {
		return array( 'data' => array(), 'done' => true );
	}

	$export_items = array();

	foreach ( $shipping_addresses as $key => $address ) {
		if ( ! empty( $address['id'] ) ) {
			$key = $address['id'];
		}
		$data_points = array(
			array(
				'name'  => __( 'Address', 'edd-flat-rate-shipping' ),
				'value' => ! empty( $address['address'] ) ? $address['address'] : '',
			),
			array(
				'name'  => __( 'Address 2', 'edd-flat-rate-shipping' ),
				'value' => ! empty( $address['address2'] ) ? $address['address2'] : '',
			),
			array(
				'name'  => __( 'City', 'edd-flat-rate-shipping' ),
				'value' => ! empty( $address['city'] ) ? $address['city'] : '',
			),
			array(
				'name'  => __( 'Country', 'edd-flat-rate-shipping' ),
				'value' => ! empty( $address['country'] ) ? $address['country'] : '',
			),
			array(
				'name'  => __( 'State', 'edd-flat-rate-shipping' ),
				'value' => ! empty( $address['state'] ) ? $address['state'] : '',
			),
			array(
				'name'  => __( 'Zip', 'edd-flat-rate-shipping' ),
				'value' => ! empty( $address['zip'] ) ? $address['zip'] : '',
			),
		);

		$export_items[] = array(
			'group_id'    => 'edd-shipping-addresses',
			'group_label' => __( 'Customer Shipping Addresses', 'edd-flat-rate-shipping' ),
			'item_id'     => "edd-shipping-addresses-{$key}",
			'data'        => $data_points,
		);

	}

	// Add the data to the list, and tell the exporter to come back for the next page of payments.
	return array(
		'data' => $export_items,
		'done' => true,
	);

}

/**
 * Adds the shipping address to the order data exporter.
 *
 * @param array   $data_points The array of data being exported.
 * @param object  $payment     In EDD 3.0, \EDD\Orders\Order
 *
 * @return array
 */
function edd_flat_rate_shipping_order_include_shipping_address_in_export( $data_points, $payment ) {
	$payment_id       = $payment->id;
	$shipping_address = edd_flat_rate_shipping_get_order_shipping_address( $payment_id );

	if ( $shipping_address ) {
		$shipping_street = array();
		if ( ! empty( $shipping_address['address'] ) ) {
			$shipping_street[] = $shipping_address['address'];
		}

		if ( ! empty( $shipping_address['address2'] ) ) {
			$shipping_street[] = $shipping_address['address2'];
		}
		$shipping_street = implode( "\n", array_values( $shipping_street ) );

		$shipping_city_state = array();
		if ( ! empty( $shipping_address['city'] ) ) {
			$shipping_city_state[] = $shipping_address['city'];
		}

		if ( ! empty( $shipping_address['state'] ) ) {
			$shipping_city_state[] = $shipping_address['state'];
		}
		$shipping_city_state = implode( ', ', array_values( $shipping_city_state ) );

		$shipping_country_postal = array();
		if ( ! empty( $shipping_address['zip'] ) ) {
			$shipping_country_postal[] = $shipping_address['zip'];
		}

		if ( ! empty( $shipping_address['country'] ) ) {
			$shipping_country_postal[] = $shipping_address['country'];
		}
		$shipping_country_postal = implode( "\n", array_values( $shipping_country_postal ) );

		$full_shipping_address = '';

		if ( ! empty( $shipping_street ) ) {
			$full_shipping_address .= $shipping_street . "\n";
		}

		if ( ! empty( $shipping_city_state ) ) {
			$full_shipping_address .= $shipping_city_state . "\n";
		}

		if ( ! empty( $shipping_country_postal ) ) {
			$full_shipping_address .= $shipping_country_postal . "\n";
		}

		$data_points[] = array(
			'name'  => __( 'Shipping Address', 'edd-flat-rate-shipping' ),
			'value' => $full_shipping_address,
		);
	}

	return $data_points;
}
add_filter( 'edd_privacy_order_details_item', 'edd_flat_rate_shipping_order_include_shipping_address_in_export', 10, 2 );

/** Anonymizer/Erasure Functions */

/**
 * When a payment status action is requested, checks if the payment still needs to be shipped, and if so, tells WordPress
 * and Easy Digital Downloads to take no action on the payment.
 *
 * Flat Rate Shipping is using this, by default, to stop published, pending, and processing payments from allowing a customer
 * to be anonymized, as there is still necessary information that may require customer interaction.
 *
 * @param $action
 * @param $payment
 *
 * @return string
 */
function edd_flat_rate_shipping_modify_payment_action( $action, $payment ) {
	$was_shipped = $payment->get_meta( '_edd_payment_shipping_status', true );

	// 1 = Not Shipped, 2 = Shipped
	if ( 1 === absint( $was_shipped ) ) {
		$action = 'none';
	}

	return $action;
}
add_filter( 'edd_privacy_payment_status_action_publish',    'edd_flat_rate_shipping_modify_payment_action', 10, 2 );
add_filter( 'edd_privacy_payment_status_action_pending',    'edd_flat_rate_shipping_modify_payment_action', 10, 2 );
add_filter( 'edd_privacy_payment_status_action_processing', 'edd_flat_rate_shipping_modify_payment_action', 10, 2 );

/**
 * During the process of anonymizing or deleting a payment detects if an item has shipping information, and if so, takes
 * no action on the payment and returns a message stating why it was not processed.
 *
 * @param array       $should_anonymize_payment
 * @param EDD_Payment $payment
 *
 * @return array
 */
function edd_flat_rate_shipping_should_anonymize_payment( $should_anonymize_payment, $payment ) {
	$was_shipped = edd_get_payment_meta( $payment->ID, '_edd_payment_shipping_status', true );

	// 1 = Not Shipped, 2 = Shipped
	if ( 1 === absint( $was_shipped ) ) {
		$should_anonymize_payment = array(
			'should_anonymize' => false,
			'message'          => array(
				/* translators: the payment ID */
				sprintf( __( 'Payment %s not processed. Still awaiting shipment.', 'edd-flat-rate-shipping' ), $payment->number )
			),
		);
	}

	return $should_anonymize_payment;
}
add_filter( 'edd_should_anonymize_payment', 'edd_flat_rate_shipping_should_anonymize_payment', 10, 2 );

/**
 * When a payment is anonymized, this function removes the shpping address lines, leaving the non-personal information in
 * tact for customer support purposes.
 *
 * @param $payment
 */
function edd_flat_rate_shipping_anonymize_payment( $payment ) {
	if ( function_exists( 'edd_get_order_addresses' ) ) {
		$addresses = edd_get_order_addresses(
			array(
				'order_id' => $payment->id,
				'type'     => 'shipping',
				'fields'   => 'id',
			)
		);
		if ( ! empty( $addresses ) ) {
			foreach ( $addresses as $address_id ) {
				edd_update_order_address(
					$address_id,
					array(
						'address'  => '',
						'address2' => '',
					)
				);
			}
		}
	} elseif ( isset( $payment->payment_meta['user_info']['shipping_info'] ) ) {
		$payment->payment_meta['user_info']['shipping_info']['address'] = '';
		$payment->payment_meta['user_info']['shipping_info']['address2'] = '';
		$payment->save();
	}
}
add_action( 'edd_anonymize_payment', 'edd_flat_rate_shipping_anonymize_payment' );

/**
 * Register eraser for EDD Flat Rate Shipping Data
 *
 * @param array $erasers
 *
 * @return array
 */
function edd_flat_rate_shipping_register_privacy_erasers( $erasers = array() ) {

	// The order of these matter, customer needs to be anonymized prior to the customer, so that the payment can adopt
	// properties of the customer like email.

	$erasers[] = array(
		'eraser_friendly_name' => __( 'Customer Shipping Addresses', 'edd-flat-rate-shipping' ),
		'callback'             => 'edd_privacy_flat_rate_shipping_eraser',
	);

	return $erasers;

}
add_filter( 'wp_privacy_personal_data_erasers', 'edd_flat_rate_shipping_register_privacy_erasers' );

/**
 * Delete any saved shipping addresses for this customer.
 *
 * Since saved shipping addresses are just a 'nice to have', we can delete them even if a customer isn't anonymized.
 * The important shipping information is stored on the payments themselves.
 *
 * @param string $email_address
 * @param int    $page
 *
 * @return array
 */
function edd_privacy_flat_rate_shipping_eraser( $email_address, $page = 1 ) {

	if ( function_exists( '_edd_privacy_get_customer_id_for_email' ) ) {
		$customer = _edd_privacy_get_customer_id_for_email( $email_address );
	} else {
		$customer = new EDD_Customer( $email_address );
	}

	if ( empty( $customer->id ) ) {
		return array(
			'items_removed'  => false,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	if ( function_exists( 'edd_get_customer_addresses' ) ) {
		$shipping_addresses = edd_get_customer_addresses(
			array(
				'customer_id' => $customer->id,
				'type'        => 'shipping',
				'fields'      => 'id',
			)
		);
	} else {
		$shipping_addresses = $customer->get_meta( 'shipping_address', false );
	}
	if ( empty( $shipping_addresses ) ) {
		return array(
			'items_removed'  => false,
			'items_retained' => false,
			'messages'       => array(
				/* translators: the customer email address. */
				sprintf( __( 'Customer for %s had no shipping addresses.', 'edd-flat-rate-shipping' ), $email_address ),
			),
			'done'           => true,
		);
	}

	if ( function_exists( 'edd_delete_customer_address' ) ) {
		foreach ( $shipping_addresses as $address_id ) {
			edd_delete_customer_address( $address_id );
		}
	} else {
		$customer->delete_meta( 'shipping_address' );
	}

	return array(
		'items_removed'  => true,
		'items_retained' => false,
		'messages'       => array(
			/* translators: the customer ID. */
			sprintf( __( 'Shipping addresses removed for customer %s.', 'edd-flat-rate-shipping' ), $email_address ),
		),
		'done'           => true,
	);

}
