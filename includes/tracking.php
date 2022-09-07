<?php

/**
 * Class EDD_Flat_Rate_Shipping_Tracking
 * @since 2.3
 * Hooks, filters, and methods for the Tracking IDs features of Flat Rate Shipping
 */
class EDD_Flat_Rate_Shipping_Tracking {

	/**
	 * Load up all the hooks needed for tracking IDS
	 *
	 * @since 2.3
	 *
	 */
	public function __construct() {
		add_action( 'edd_view_order_details_billing_after', array( $this, 'payment_tracking' ) );
		add_filter( 'edd_get_order_details_sections',       array( $this, 'add_tracking_details_section' ), 10, 2 );
		add_action( 'edd_updated_edited_purchase',          array( $this, 'save_edited_payment' ), 10, 1 );
		add_action( 'edd_add_email_tags',                   array( $this, 'add_email_tag' ), 100 );
		add_action( 'edd_send-tracking',                    array( $this, 'send_tracking' ), 10, 1 );
		add_action( 'edd_purchase_history_header_after',    array( $this, 'order_details_header' ), 10, 1 );
		add_action( 'edd_purchase_history_row_end',         array( $this, 'order_details_row' ), 10, 2 );
	}

	/**
	 * Adds the tracking details as an order section in EDD 3.0.
	 *
	 * @since 2.3.9
	 * @param array  $sections The array of order sections.
	 * @param object $order    The order object.
	 * @return array
	 */
	public function add_tracking_details_section( $sections, $order ) {

		$needs_shipping = edd_flat_rate_shipping()->payment_needs_shipping( $order->id );
		if ( ! $needs_shipping ) {
			return $sections;
		}

		$sections[] = array(
			'id'       => 'tracking',
			'label'    => __( 'Tracking', 'easy-flat-rate-shipping' ),
			'icon'     => 'admin-site-alt2',
			'callback' => array( $this, 'show_tracking_order_section' ),
		);

		return $sections;
	}

	/**
	 * Shows the tracking details in EDD 3.0.
	 *
	 * @since 2.3.9
	 * @param object $order The order object.
	 * @return void
	 */
	public function show_tracking_order_section( $order ) {
		remove_action( 'edd_view_order_details_billing_after', array( $this, 'payment_tracking' ) );

		$this->do_tracking_order_details( $order->id );
	}

	/**
	 * Show the tracking ID metabox on the view order details
	 *
	 * @since 2.3
	 * @param $payment_id
	 *
	 * @return void
	 */
	public function payment_tracking( $payment_id ) {
		$needs_shipping = edd_flat_rate_shipping()->payment_needs_shipping( $payment_id );

		if ( ! $needs_shipping ) {
			return;
		}

		?>
		<div id="edd-payment-tracking" class="postbox">
			<div class="inside">
				<?php $this->do_tracking_order_details( $payment_id ); ?>
			</div><!-- /.inside -->
		</div><!-- /#edd-payment-notes -->
		<?php
	}

	/**
	 * Outputs the tracking info in the order details.
	 *
	 * @since 2.3.9
	 * @param int $payment_id The order ID.
	 * @return void
	 */
	private function do_tracking_order_details( $payment_id ) {
		$tracking_ids = $this->get_payment_tracking( $payment_id );
		$was_sent     = $this->payment_tracking_last_sent( $payment_id );
		?>
		<div id="edd-tracking-fields" class="edd-tracking-fields edd_repeatable_table">
			<div class="edd-repeatable-row-header">
				<h3 class="hndle"><?php esc_html_e( 'Tracking Info', 'edd-flat-rate-shipping' ); ?></h3>
			</div>
			<?php
			if ( ! empty( $tracking_ids ) ) :

				foreach ( $tracking_ids as $key => $args ) :
					?>
					<div class="edd_tracking_ids_wrapper edd-form-row edd_repeatable_row edd-repeatable-row-standard-fields" data-key="<?php echo esc_attr( $key ); ?>">
						<?php $this->tracking_input_field( $key, $args ); ?>
					</div>
					<?php
				endforeach;
			else :
				?>
				<div class="edd_tracking_ids_wrapper edd-form-row edd_repeatable_row edd-repeatable-row-standard-fields" data-key="1">
					<?php $this->tracking_input_field( 0 ); ?>
				</div>
			<?php endif; ?>

			<div class="edd-flat-rate-shipping-tracking-actions">
				<div class="submit">
					<button class="button button-secondary edd_add_repeatable"><?php esc_html_e( 'Add New Tracking ID', 'edd-flat-rate-shipping' ); ?></button>
				</div>
				<?php if ( ! empty( $tracking_ids ) ) : ?>
					<div class="edd-ss-send-tracking-wrapper">
						<?php
						wp_nonce_field( 'edd-ti-send-tracking', 'edd-ti-send-tracking', false, true );
						$notify_button_text = ! empty( $was_sent ) ? __( 'Resend Tracking Info', 'edd-flat-rate-shipping' ) : __( 'Send Tracking Info', 'edd-flat-rate-shipping' );
						?>
						<button class="button button-secondary" id="edd-tracking-info-notify-customer" data-payment="<?php echo esc_attr( $payment_id ); ?>"><?php echo esc_html( $notify_button_text ); ?></button>
					</div>
					<?php
				endif;
				?>
			</div>
		</div>
		<div class="clear"></div>
		<?php
	}

	/**
	 * Display the input field for a tracking ID in the view order details metabox.
	 *
	 * @since 2.3
	 *
	 * @param       $key
	 * @param array $args
	 *
	 * @return void
	 */
	private function tracking_input_field( $key, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				/* translators: the current row */
				'name'        => sprintf( __( 'Parcel %s', 'edd-flat-rate-shipping' ), $key + 1 ),
				'tracking_id' => '',
			)
		);
		?>
		<div class="edd-form-group edd-form-row__column">
			<label for="edd_tracking_ids-<?php echo esc_attr( $key ); ?>-name" class="edd-form-group__label"><?php esc_html_e( 'Parcel Name', 'edd-flat-rate-shipping' ); ?></label>
			<div class="edd-form-group__control">
			<?php
				echo EDD()->html->text(
					array(
						'name'        => 'edd_tracking_ids[' . esc_attr( $key ) . '][name]',
						'id'          => 'edd_tracking_ids-' . esc_attr( $key ) . '-name',
						'value'       => esc_attr( $args['name'] ),
						'placeholder' => esc_html__( 'Package Name', 'edd-flat-rate-shipping' ),
						'class'       => 'edd-flat-rate-shipping-name-input edd-form-group__input large-text',
					)
				);
			?>
			</div>
		</div>

		<div class="edd-form-group edd-form-row__column">
			<label for="edd_tracking_ids-<?php echo esc_attr( $key ); ?>-tracking_id" class="edd-form-group__label"><?php esc_html_e( 'Tracking ID', 'edd-flat-rate-shipping' ); ?></label>
			<div class="edd-form-group__control">
			<?php
				echo EDD()->html->text(
					array(
						'name'        => 'edd_tracking_ids[' . esc_attr( $key ) . '][tracking_id]',
						'id'          => 'edd_tracking_ids-' . esc_attr( $key ) . '-tracking_id',
						'value'       => esc_attr( $args['tracking_id'] ),
						'placeholder' => esc_html__( 'Tracking ID', 'edd-flat-rate-shipping' ),
						'class'       => 'edd-flat-rate-shipping-tracking-input edd-form-group__input large-text',
					)
				);
			?>
			</div>
		</div>

		<?php if ( ! empty( $args['tracking_id'] ) ) : ?>
			<div class="edd-form-group edd-form-row__column edd-form-row__column-center">
					<a href="<?php echo esc_url( $this->get_tracking_link( $args['tracking_id'] ) ); ?>" target="_blank"><?php esc_html_e( 'Track Parcel', 'edd-flat-rate-shipping' ); ?></a>
			</div>
		<?php endif; ?>

		<div class="edd-form-group edd-form-row__column edd-form-row__column-center">
			<button class="edd_remove_repeatable" data-type="file" style="background: url(<?php echo esc_url( admin_url( '/images/xit.gif' ) ); ?>) no-repeat;">
				<span class="screen-reader-text">
				<?php
					/* translators: the tracking ID */
					printf( esc_html__( 'Remove tracking ID %s', 'edd-flat-rate-shipping' ), esc_html( $args['tracking_id'] ) );
				?>
				</span>
				<span aria-hidden="true">&times;</span>
			</button>
		</div>
		<?php
	}

	/**
	 * Save the post meta for the order details when adding tracking IDs
	 *
	 * @since 2.3
	 * @param $payment_id
	 *
	 * @return void
	 */
	public function save_edited_payment( $payment_id ) {
		$tracking_ids = isset( $_POST['edd_tracking_ids'] ) ? $_POST['edd_tracking_ids'] : array();

		foreach ( $tracking_ids as $key => $tracking_id ) {
			if ( empty( $tracking_id['tracking_id'] ) ) {
				unset( $tracking_ids[ $key ] );
			}
		}

		if ( empty( $tracking_ids ) ) {
			$payment = new EDD_Payment( $payment_id );
			$payment->delete_meta( '_edd_payment_tracking_ids' );
		} else {
			edd_update_payment_meta( $payment_id, '_edd_payment_tracking_ids', $tracking_ids );
		}
	}

	/**
	 * Register the `tracking_ids` email tag
	 *
	 * @since 2.3
	 *
	 * @return void
	 */
	public function add_email_tag() {
		edd_add_email_tag( 'tracking_ids', __( 'Show saved tracking ids for payment.', 'edd-flat-rate-shipping' ), array( $this, 'output_tracking_ids_tag' ) );
	}

	/**
	 * Output a UL of the tracking IDs for a payment
	 *
	 * @since 2.3
	 * @param int $payment_id
	 *
	 * @return string
	 */
	public function output_tracking_ids_tag( $payment_id = 0 ) {

		// Start a buffer so we don't output any errors into the email.
		ob_start();
		$output = '';
		$tracking_ids = $this->get_payment_tracking( $payment_id );

		if ( $tracking_ids ) {
			$output = '<ul>';
			foreach ( $tracking_ids as $key => $tracking_info ) {
				$output .= '<li>' . $tracking_info['name'] . '&mdash;<a href="' . $this->get_tracking_link( $tracking_info['tracking_id'] ) . '">' . $tracking_info['tracking_id'] . '</a></li>';
			}
			$output .= '</ul>';
		}
		ob_end_clean();
		return $output;

	}

	/**
	 * Replace the tracking_ids email tag with the actual email tag list.
	 *
	 * @since 2.3
	 *
	 * @param $message
	 * @param $payment_id
	 *
	 * @return mixed
	 */
	public function filter_template_tags( $message, $payment_id ) {
		$tracking_ids = $this->output_tracking_ids_tag( $payment_id );
		$message      = str_replace( '{tracking_ids}', $tracking_ids, $message );

		return $message;
	}

	/**
	 * Use EDD_Emails to send the tracking IDs to the customer
	 *
	 * @since 2.3
	 *
	 * @param $post
	 * @return void
	 */
	public function send_tracking( $post ) {
		$nonce = ! empty( $post['nonce'] ) ? $post['nonce'] : false;
		if ( ! wp_verify_nonce( $nonce, 'edd-ti-send-tracking' ) ) { wp_die(); }

		$has_tracking = $this->payment_has_tracking( $post['payment_id'] );
		if ( false === $has_tracking ) {
			return;
		}

		$from_name    = edd_get_option( 'from_name', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
		$from_email   = edd_get_option( 'from_email', get_bloginfo( 'admin_email' ) );
		$to_email     = edd_get_payment_user_email( $post['payment_id'] );

		$subject      = edd_get_option( 'tracking_ids_subject', __( 'Your order has shipped!', 'edd-flat-rate-shipping' ) );
		$heading      = edd_get_option( 'tracking_ids_heading', __( 'Your order has shipped!', 'edd-flat-rate-shipping' ) );
		$message      = edd_get_option( 'tracking_ids_email', '' );

		if ( empty( $message ) ) {
			$message = $this->get_default_tracking_email_message();
		}

		$message = EDD()->email_tags->do_tags( $message, $post['payment_id'] );

		$headers  = "From: " . stripslashes_deep( html_entity_decode( $from_name, ENT_COMPAT, 'UTF-8' ) ) . " <$from_email>\r\n";
		$headers .= "Reply-To: ". $from_email . "\r\n";
		$headers .= "MIME-Version: 1.0\r\n";
		$headers .= "Content-Type: text/html; charset=utf-8\r\n";

		$attachments = array();

		$emails = EDD()->emails;

		$emails->__set( 'from_name', $from_name );
		$emails->__set( 'from_email', $from_email );
		$emails->__set( 'heading', $heading );
		$emails->__set( 'headers', $headers );

		$result = $emails->send( $to_email, $subject, $message, $attachments );

		$response = array( 'success' => $result );
		$response['message'] = $result ? __( 'Email sent.', 'edd-flat-rate-shipping' ) : __( 'Error sending email. Try again later.', 'edd-flat-rate-shipping' );

		if ( $result ) {
			edd_update_payment_meta( $post['payment_id'], '_edd_payment_tracking_sent', current_time( 'timestamp' ) );
			edd_insert_payment_note( $post['payment_id'], sprintf( __( 'Tracking information sent to %s.', 'edd-flat-rate-shipping' ), $to_email ) );
		}

		echo json_encode( $response );
		die();
	}

	/**
	 * Check if a payment ID has tracking information
	 *
	 * @since 2.3
	 * @param int $payment_id
	 *
	 * @return bool
	 */
	public function payment_has_tracking( $payment_id = 0 ) {
		$payment = new EDD_Payment( $payment_id );

		if ( empty( $payment->ID ) ) {
			return false;
		}

		$has_tracking = $payment->get_meta( '_edd_payment_tracking_ids' );

		return ! empty( $has_tracking ) ? true : false;
	}

	/**
	 * Get the tracking IDs for a payment.
	 *
	 * @since 2.3
	 * @param int $payment_id
	 *
	 * @return bool|mixed
	 */
	public function get_payment_tracking( $payment_id = 0 ) {
		$payment = new EDD_Payment( $payment_id );

		if ( empty( $payment->ID ) ) {
			return false;
		}

		$has_tracking = $payment->get_meta( '_edd_payment_tracking_ids' );

		return ! empty( $has_tracking ) ? $has_tracking : false;
	}

	/**
	 * Check if we've sent tracking IDs to a customer before.
	 *
	 * @since 2.3
	 * @param int $payment_id
	 *
	 * @return array|bool|mixed
	 */
	public function payment_tracking_last_sent( $payment_id = 0 ) {
		$payment = new EDD_Payment( $payment_id );
		$tracking_sent = $payment->get_meta( '_edd_payment_tracking_sent' );
		if ( empty( $tracking_sent ) ) {
			return false;
		}

		if ( is_array( $tracking_sent ) ) {
			$tracking_sent = array_shift( arsort( $tracking_sent ) );
		}

		return $tracking_sent;
	}

	/**
	 * Generate a link to AfterShip for a tracking ID
	 *
	 * @since 2.3
	 *
	 * @param $tracking_id
	 *
	 * @return mixed|void
	 */
	public function get_tracking_link( $tracking_id ) {
		return apply_filters( 'edd_flat_rate_shipping_tracking_link', 'https://track.aftership.com/' . $tracking_id, $tracking_id);
	}

	/**
	 * Show the 'Tracking' header on the order list.
	 *
	 * @since 2.3
	 *
	 * @return void
	 */
	public function order_details_header() {
		?>
		<th class="edd_purchase_tracking"><?php _e( 'Shipping', 'edd-tracking-info' ); ?></th>
		<?php
	}

	/**
	 * Show the 'Tracking' content on the order list.
	 *
	 * @since 2.3
	 *
	 * @return void
	 */
	public function order_details_row( $payment_id, $purchase_data ) {
		ob_start();
		$tracking_ids   = $this->get_payment_tracking( $payment_id );
		$needs_shipping = edd_flat_rate_shipping()->payment_needs_shipping( $payment_id );
		$payment_status = edd_get_payment_status( $payment_id );
		?>
			<td>
			<?php if ( $tracking_ids ) : ?>
				<?php foreach ( $tracking_ids as $tracking_id ) : ?>
					<span class="edd-shipping-tracking-id"><a href="<?php echo $this->get_tracking_link( $tracking_id['tracking_id'] ); ?>" target="_blank"><?php echo $tracking_id['tracking_id']; ?></a></span>
				<?php endforeach; ?>
			<?php elseif ( $needs_shipping && ( 'complete' === $payment_status || 'publish' === $payment_status ) ) : ?>
				<?php $shipped_status = edd_get_payment_meta( $payment_id, '_edd_payment_shipping_status', true ); ?>
				<?php echo 2 === (int) $shipped_status ? __( 'Shipped', 'edd-flat-rate-shipping' ) : __( 'Shipment Pending', 'edd-flat-rate-shipping' ); ?>
			<?php else : ?>
				&mdash;
			<?php endif; ?>
			</td>
		<?php
		echo ob_get_clean();
	}

	/**
	 * Get the default tracking ID email content
	 *
	 * @since 2.3
	 *
	 * @return mixed|string|void
	 */
	public function get_default_tracking_email_message() {
		return __( "Dear {name},\n\nYour recent order {payment_id} has been shipped. Your tracking information is below.\n\n{tracking_ids}\n\n{sitename}", "edd-flat-rate-shipping" );
	}

}
