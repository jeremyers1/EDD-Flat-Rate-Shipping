<?php
/**
 * Triggers all upgrade functions
 *
 * @since 1.0.0
 * @return void
*/
function edd_fr_show_upgrade_notice() {

	if( ! function_exists( 'EDD' ) ) {
		return;
	}

	$current_version = get_option( 'edd_flat_rate_shipping_version' );

	if( function_exists( 'edd_has_upgrade_completed' ) && function_exists( 'edd_maybe_resume_upgrade' ) ) {
		$resume_upgrade = edd_maybe_resume_upgrade();
		if ( empty( $resume_upgrade ) ) {

			if ( ( false === $current_version || version_compare( $current_version, '2.2.3', '<' ) ) || ! edd_has_upgrade_completed( 'ss_upgrade_customer_addresses' ) ) {
				printf(
					'<div class="updated"><p>' . __( 'Easy Digital Downloads - Flat Rate Shipping needs to update customer records, click <a href="%s">here</a> to start the upgrade.', 'edd-flat-rate-shipping' ) . '</p></div>',
					esc_url( add_query_arg( array( 'edd_action' => 'ss_upgrade_customer_addresses' ), admin_url() ) )
				);
			}

		}
	}
}
add_action( 'admin_notices', 'edd_fr_show_upgrade_notice' );

/**
 * Upgrades old license keys with the new site URL store
 *
 * @since 1.0.0
 * @return void
 */
function edd_fr_customer_address_upgrade() {
	global $wpdb;
	$current_version = get_option( 'edd_flat_rate_shipping_version' );

	if ( version_compare( $current_version, '2.2.3', '>=' ) ) {
		return;
	}

	ignore_user_abort( true );

	if ( ! edd_is_func_disabled( 'set_time_limit' ) ) {
		set_time_limit( 0 );
	}

	$step   = isset( $_GET['step'] ) ? absint( $_GET['step'] ) : 1;
	$number = 25;
	$total  = isset( $_GET['total'] ) ? absint( $_GET['total'] ) : false;
	$offset = $step == 1 ? 0 : $step * $number;

	if ( $step < 2 ) {

		// Check if we have any payments before moving on
		$sql = "SELECT ID FROM $wpdb->posts WHERE post_type = 'edd_payment' LIMIT 1";
		$has_payments = $wpdb->get_col( $sql );

		if( empty( $has_payments ) ) {
			// We had no payments, just complete
			update_option( 'edd_flat_rate_shipping_version', preg_replace( '/[^0-9.].*/', '', EDD_FLAT_RATE_SHIPPING_VERSION ) );
			edd_set_upgrade_complete( 'ss_upgrade_customer_addresses' );
			delete_option( 'edd_doing_upgrade' );
			wp_redirect( admin_url() ); exit;
		}
	}

	if ( false === $total ) {
		$sql   = "SELECT COUNT( ID ) FROM $wpdb->posts WHERE post_type = 'edd_payment'";
		$total = $wpdb->get_var( $sql );
	}

	$sql      = $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type = 'edd_payment' LIMIT %d OFFSET %d", $number, $offset );
	$payments = $wpdb->get_col( $sql );

	if( $payments ) {
		foreach( $payments as $payment ) {

			$payment   = new EDD_Payment( $payment );
			$user_info = $payment->user_info;
			if ( ! isset( $user_info['shipping_info'] ) ) {
				continue;
			}

			edd_flat_rate_shipping()->add_customer_shipping_address( $payment->customer_id, $user_info['shipping_info'] );

		}

		// Keys found so upgrade them
		$step++;
		$redirect = add_query_arg( array(
			'page'        => 'edd-upgrades',
			'edd-upgrade' => 'ss_upgrade_customer_addresses',
			'step'        => $step,
			'number'      => $number,
			'total'       => $total,
		), admin_url( 'index.php' ) );
		wp_safe_redirect( $redirect ); exit;

	} else {

		// No more data to update. Downloads have been altered or dismissed
		update_option( 'edd_flat_rate_shipping_version', preg_replace( '/[^0-9.].*/', '', EDD_FLAT_RATE_SHIPPING_VERSION ) );
		edd_set_upgrade_complete( 'ss_upgrade_customer_addresses' );
		delete_option( 'edd_doing_upgrade' );

		wp_redirect( admin_url() ); exit;
	}

}
add_action( 'edd_fr_upgrade_customer_addresses', 'edd_fr_customer_address_upgrade' );