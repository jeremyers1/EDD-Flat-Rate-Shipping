<?php

/**
 * Functions
 *
 * @package EDD_Flat_Rate_Shipping
 * @since 1.0.0
 */

/**
 * Gets the shipping address for an order.
 *
 * @since 1.0.0
 * @param int $order_id The order ID.
 * @return array|false
 */
function edd_flat_rate_shipping_get_order_shipping_address( $order_id ) {
	$address = false;
	if ( function_exists( 'edd_get_order_addresses' ) ) {
		$addresses = edd_get_order_addresses(
			array(
				'order_id' => $order_id,
				'type'     => 'shipping',
				'number'   => 1,
			)
		);
		if ( ! empty( $addresses[0] ) ) {
			$address = $addresses[0];

			return array(
				'id'       => $address->id,
				'address'  => $address->address,
				'address2' => $address->address2,
				'city'     => $address->city,
				'state'    => $address->region,
				'zip'      => $address->postal_code,
				'country'  => $address->country,
			);
		}
	}

	$user_info = edd_get_payment_meta_user_info( $order_id );
	if ( ! empty( $user_info['shipping_info'] ) ) {
		$address = $user_info['shipping_info'];
	}

	return $address;
}