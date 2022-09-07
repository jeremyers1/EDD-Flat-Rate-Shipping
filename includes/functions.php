<?php

/**
 * Functions
 *
 * @package EDD_Flat_Rate_Shipping
 * @since 2.3.9
 */

/**
 * Gets the shipping address for an order.
 *
 * @since 2.3.9
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

		// If EDD 3.0 is getting shipping info from here, it needs to be migrated to the proper tables and the order meta updated or removed.
		if ( function_exists( 'edd_get_order_meta' ) ) {
			edd_flat_rate_shipping()->add_shipping_address_to_order( $order_id, array( 'user_info' => $user_info ) );
			$old_meta = edd_get_order_meta( $order_id, 'payment_meta', true );
			unset( $old_meta['user_info']['shipping_info'] );
			if ( empty( $old_meta['user_info'] ) ) {
				unset( $old_meta['user_info'] );
			}
			if ( empty( $old_meta ) ) {
				edd_delete_order_meta( $order_id, 'payment_meta' );
			} else {
				edd_update_order_meta( $order_id, 'payment_meta', $old_meta );
			}
		}
	}

	return $address;
}

add_action( 'edd_30_migrate_order', 'edd_flat_rate_shipping_30_migration', 10, 3 );
/**
 * During the EDD 3.0 migration, copies the shipping address from the old post metadata
 * to the new order and customer address tables.
 *
 * @since 2.3.9
 * @param int   $order_id      The new order ID.
 * @param array $payment_meta  The original payment meta.
 * @param array $name          The original post meta.
 * @return void
 */
function edd_flat_rate_shipping_30_migration( $order_id, $payment_meta, $meta ) {
	$has_shipping = ! empty( $meta['_edd_payment_shipping_status'] ) && ! empty( $meta['_edd_payment_shipping_status'][0] );
	if ( ! $has_shipping ) {
		return;
	}
	$shipping_info = ! empty( $payment_meta['user_info']['shipping_info'] ) ? $payment_meta['user_info']['shipping_info'] : false;
	if ( ! $shipping_info ) {
		return;
	}

	$shipping_info['type']        = 'shipping';
	$shipping_info['order_id']    = $order_id;
	$shipping_info['name']        = $payment_meta['user_info']['first_name'] . ' ' . $payment_meta['user_info']['last_name'];
	$shipping_info['region']      = $shipping_info['state'];
	$shipping_info['postal_code'] = $shipping_info['zip'];
	$order_address                = edd_add_order_address( $shipping_info );
	$customer_id                  = edd_get_payment_customer_id( $order_id );
	edd_maybe_add_customer_address(
		$customer_id,
		$shipping_info
	);
}

add_filter( 'edd_30_core_user_info', 'edd_flat_rate_shipping_core_user_info_shipping' );
/**
 * Adds the order shipping info to the array of data which does not need to be
 * preserved in the payment_meta since it's being migrated.
 *
 * @since 2.3.9
 * @param array  $user_info The array of core user_info fields.
 * @return array
 */
function edd_flat_rate_shipping_core_user_info_shipping( $user_info ) {
	$user_info[] = 'shipping_info';

	return $user_info;
}
