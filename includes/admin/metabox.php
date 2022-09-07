<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class EDD_Flat_Rate_Shipping_Metabox {

	public function __construct() {
		add_action( 'edd_meta_box_fields',           array( $this, 'metabox' ), 10 );
		add_action( 'edd_updated_edited_purchase',   array( $this, 'save_payment' ) );
		add_action( 'edd_download_price_option_row', array( $this, 'price_row' ), 700, 3 );
		add_filter( 'edd_metabox_fields_save',       array( $this, 'meta_fields_save' ) );
	}

	/**
	 * Render the extra meta box fields
	 *
	 * @since 1.0
	 *
	 * @access private
	 * @return void
	 */
	public function metabox( $post_id = 0 ) {
		$currency_position = edd_get_option( 'currency_position', 'before' );

		$download = new EDD_Download( $post_id );

		$enabled          = get_post_meta( $post_id, '_edd_enable_shipping', true );
		$variable_pricing = $download->has_variable_prices();
		$display          = $enabled && ! $variable_pricing ? '' : 'style="display:none;"';
		$domestic         = get_post_meta( $post_id, '_edd_shipping_domestic', true );
		$international    = get_post_meta( $post_id, '_edd_shipping_international', true );
		?>
		<div id="edd_flat_rate_shipping">
			<script type="text/javascript">
				jQuery(document).ready(function($) {
					$('#edd_enable_shipping').on('click',function() {
						var variable_pricing = $('#edd_variable_pricing').is(':checked');
						var enabled          = $(this).is(':checked');
						if ( enabled ) {
							if ( variable_pricing ) {
								$('.edd_prices_shipping').show();
							} else {
								$('#edd_flat_rate_shipping_fields').show();
							}
						} else {
							$('#edd_flat_rate_shipping_fields,.edd_prices_shipping').hide();
						}
					});

					$('#edd_variable_pricing').on('click', function() {
						var enabled  = $(this).is(':checked');
						var shipping = $('#edd_enable_shipping').is(':checked');

						if ( ! shipping ) {
							return;
						}

						if ( enabled ) {
							$('.edd_prices_shipping').show();
							$('#edd_flat_rate_shipping_fields').hide();
						} else {
							$('#edd_flat_rate_shipping_fields').show();
							$('.edd_prices_shipping').hide();
						}
					});
				});</script>
			<p><strong><?php _e( 'Shipping Options', 'edd-flat-rate-shipping' ); ?></strong></p>
			<p>
				<label for="edd_enable_shipping">
					<input type="checkbox" name="_edd_enable_shipping" id="edd_enable_shipping" value="1"<?php checked( 1, $enabled ); ?>/>
					<?php printf( __( 'Enable shipping for this %s', 'edd-flat-rate-shipping' ), edd_get_label_singular() ); ?>
				</label>
			</p>
			<div id="edd_flat_rate_shipping_fields" <?php echo $display; ?>>
				<table>
					<tr>
						<td>
							<label for="edd_shipping_domestic"><?php _e( 'Domestic Rate:', 'edd-flat-rate-shipping' ); ?>&nbsp;</label>
						</td>
						<td>
							<?php if( 'before' === $currency_position ) : ?>
								<span><?php echo edd_currency_filter( '' ); ?></span><input type="number" min="0" step="0.01" class="small-text" value="<?php esc_attr_e( $domestic ); ?>" id="edd_shipping_domestic" name="_edd_shipping_domestic"/>
							<?php else : ?>
								<input type="number" min="0" step="0.01" class="small-text" value="<?php esc_attr_e( $domestic ); ?>" id="edd_shipping_domestic" name="_edd_shipping_domestic"/><?php echo edd_currency_filter( '' ); ?>
							<?php endif; ?>
						</td>
						<td>
							<label for="edd_shipping_international"><?php _e( 'International Rate:', 'edd-flat-rate-shipping' ); ?>&nbsp;</label>
						</td>
						<td>
							<?php if( $currency_position == 'before' ) : ?>
								<span><?php echo edd_currency_filter( '' ); ?></span><input type="number" min="0" step="0.01" class="small-text" value="<?php esc_attr_e( $international ); ?>" id="edd_shipping_international" name="_edd_shipping_international"/>
							<?php else : ?>
								<input type="number" min="0" step="0.01" class="small-text" value="<?php esc_attr_e( $international ); ?>" id="edd_shipping_international" name="_edd_shipping_international"/><?php echo edd_currency_filter( '' ); ?>
							<?php endif; ?>
						</td>
					</tr>
				</table>
			</div>
		</div>
	<?php
	}

	/**
	 * Save the shipping details on payment edit
	 *
	 * @since 1.5
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
	 * Renders the shipping options for variable prices.
	 *
	 * @since 1.0
	 * @param int   $post_id The current download ID.
	 * @param int   $key     The current row.
	 * @param array $args    The parameters specific to the row.
	 * @return void
	 */
	public function price_row( $post_id, $key, $args ) {
		$enabled           = get_post_meta( $post_id, '_edd_enable_shipping', true );
		$currency_position = edd_get_option( 'currency_position', 'before' );
		$display           = $enabled ? '' : 'style="display:none;"';
		$prices            = edd_get_variable_prices( $post_id );
		$shipping          = isset( $prices[ $key ]['shipping'] ) ? $prices[ $key ]['shipping'] : false;

		$domestic      = '';
		$international = '';
		if ( is_array( $shipping ) ) {
			$domestic      = ! empty( $shipping['domestic'] ) ? $shipping['domestic'] : $domestic;
			$international = ! empty( $shipping['international'] ) ? $shipping['international'] : $international;
		} elseif ( ! empty( $shipping ) ) {
			$domestic      = get_post_meta( $post_id, '_edd_shipping_domestic', true );
			$international = get_post_meta( $post_id, '_edd_shipping_international', true );
		}
		?>
		<div class="edd-custom-price-option-section edd_prices_shipping" <?php echo $display; ?>>
			<span class="edd-custom-price-option-section-title"><?php esc_html_e( 'Flat Rate Shipping Settings', 'edd-flat-rate-shipping' ); ?></span>
			<div class="edd-custom-price-option-section-content edd-form-row">
				<div class="edd-form-group edd-form-row__column">
					<label class="edd-form-group__label" for="edd_shipping_domestic_<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Domestic', 'edd-flat-rate-shipping' ); ?></label>
					<div class="edd-form-group__control">
						<?php
						if ( 'before' === $currency_position ) {
							?>
							<span class="edd-amount-control__currency is-before"><?php echo esc_html( edd_currency_filter( '' ) ); ?></span>&nbsp;
							<input type="number" min="0" step="0.01" class="edd-form-group__input small-text" value="<?php echo esc_attr( $domestic ); ?>" id="edd_shipping_domestic_<?php echo esc_attr( $key ); ?>" name="edd_variable_prices[<?php echo esc_attr( $key ); ?>][shipping][domestic]"/>
							<?php
						} else {
							?>
							<input type="number" min="0" step="0.01" class="edd-form-group__input small-text" value="<?php echo esc_attr( $domestic ); ?>" id="edd_shipping_domestic_<?php echo esc_attr( $key ); ?>" name="edd_variable_prices[<?php echo esc_attr( $key ); ?>][shipping][domestic]"/>
							&nbsp;<span class="edd-amount-control__currency is-after"><?php echo esc_html( edd_currency_filter( '' ) ); ?></span>
							<?php
						}
						?>
					</div>
				</div>
				<div class="edd-form-group edd-form-row__column">
					<label class="edd-form-group__label" for="edd_shipping_international_<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'International', 'edd-flat-rate-shipping' ); ?></label>
					<div class="edd-form-group__control">
						<?php
						if ( 'before' === $currency_position ) {
							?>
							<span class="edd-amount-control__currency is-before"><?php echo esc_html( edd_currency_filter( '' ) ); ?></span>&nbsp;
							<input type="number" min="0" step="0.01" class="edd-form-group__input small-text" value="<?php echo esc_attr( $international ); ?>" id="edd_shipping_international_<?php echo esc_attr( $key ); ?>" name="edd_variable_prices[<?php echo esc_attr( $key ); ?>][shipping][international]"/>
							<?php
						} else {
							?>
							<input type="number" min="0" step="0.01" class="edd-form-group__input small-text" value="<?php echo esc_attr( $international ); ?>" id="edd_shipping_international_<?php echo esc_attr( $key ); ?>" name="edd_variable_prices[<?php echo esc_attr( $key ); ?>][shipping][international]"/>
							&nbsp;<span class="edd-amount-control__currency is-after"><?php echo esc_html( edd_currency_filter( '' ) ); ?></span>
							<?php
						}
						?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Save our extra meta box fields
	 *
	 * @since 1.0
	 *
	 * @access private
	 * @return array
	 */
	public function meta_fields_save( $fields ) {

		// Tell EDD to save our extra meta fields
		$fields[] = '_edd_enable_shipping';
		$fields[] = '_edd_shipping_domestic';
		$fields[] = '_edd_shipping_international';
		return $fields;

	}
}
