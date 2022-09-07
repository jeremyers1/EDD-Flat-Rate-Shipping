<?php
/**
 * @var EDD_Flat_Rate_Shipping $this
 */

$display = $this->has_billing_fields() ? 'display:none;' : '';
?>

<div id="edd_flat_rate_shipping">
	<input type="hidden" name="edd_shipping_country" id="edd-shipping-country" value="<?php echo esc_attr( $this->get_shipping_country() ); ?>" />
	<?php if ( $this->has_billing_fields() ) : ?>
		<fieldset id="edd_flat_rate_shipping_diff_address">
			<label for="edd_flat_rate_shipping_show">
				<input type="checkbox" id="edd_flat_rate_shipping_show" name="edd_use_different_shipping" value="1"/>
				<?php esc_html_e( 'Ship to Different Address?', 'edd-flat-rate-shipping' ); ?>
			</label>
		</fieldset>
	<?php endif; ?>
	<div id="edd_flat_rate_shipping_fields_wrap" style="<?php echo esc_attr( $display ); ?>">
		<fieldset id="edd_flat_rate_shipping_fields">
			<?php do_action( 'edd_shipping_address_top' ); ?>
			<legend><?php esc_html_e( 'Shipping Details', 'edd-flat-rate-shipping' ); ?></legend>
			<?php
			$existing_addresses = array();
			if ( is_user_logged_in() ) {
				$customer = EDD()->customers->get_customer_by( 'user_id', get_current_user_id() );
				if ( ! empty( $customer->id ) ) {
					$existing_addresses = $this->get_customer_shipping_addresses( $customer->id );
				}
			}

			$options   = array();
			$countries = array();
			foreach ( $existing_addresses as $key => $values ) {
				if ( ! empty( $values['id'] ) ) {
					$key = $values['id'];
				}
				$address_label     = array(
					! empty( $values['address'] ) ? $values['address'] : '',
					! empty( $values['address2'] ) ? $values['address2'] : '',
					! empty( $values['city'] ) ? $values['city'] : '',
					! empty( $values['state'] ) ? $values['state'] : '',
					! empty( $values['zip'] ) ? $values['zip'] : '',
				);
				$countries[ $key ] = $values['country'];

				$address_label   = array_values( array_filter( $address_label ) );
				$options[ $key ] = implode( ', ', $address_label );
			}

			$show_address_fields = empty( $options ) ? '' : 'display:none;';

			if ( ! empty( $options ) ) : ?>
				<div class="edd-existing-shipping-addresses-wrapper">
					<p>
						<select name="existing_shipping_address" class="edd-select edd-existing-shipping-addresses" id="edd-existing-shipping-addresses">
							<option value="-1"><?php esc_attr_e( 'Select an Address', 'edd-flat-rate-shipping' ); ?></option>
							<?php
							foreach ( $options as $key => $option ) :
								if ( ! empty( $countries[ $key ] ) ) {
									$address_country = $countries[ $key ];
								}
								?>
								<option value="<?php echo esc_attr( $key ); ?>" data-country="<?php echo esc_attr( $address_country ); ?>"><?php echo esc_html( $option ); ?></option>
							<?php endforeach; ?>
							<option value="new"><?php esc_html_e( 'Add new address', 'edd-flat-rate-shipping' ); ?></option>
						</select>
					</p>
				</div>
			<?php endif; ?>
			<div id="edd-shipping-new-address-wrapper" style="<?php echo esc_attr( $show_address_fields ); ?>">
				<p id="edd-shipping-address-wrap">
					<label class="edd-label"><?php esc_html_e( 'Shipping Address', 'edd-flat-rate-shipping' ); ?></label>
					<span class="edd-description"><?php esc_html_e( 'The address to ship your purchase to.', 'edd-flat-rate-shipping' ); ?></span>
					<input type="text" name="shipping_address" class="shipping-address edd-input" placeholder="<?php esc_html_e( 'Address line 1', 'edd-flat-rate-shipping' ); ?>"/>
				</p>
				<p id="edd-shipping-address-2-wrap">
					<label class="edd-label"><?php esc_html_e( 'Shipping Address Line 2', 'edd-flat-rate-shipping' ); ?></label>
					<span class="edd-description"><?php esc_html_e( 'The suite, apt no, PO box, etc, associated with your shipping address.', 'edd-flat-rate-shipping' ); ?></span>
					<input type="text" name="shipping_address_2" class="shipping-address-2 edd-input" placeholder="<?php esc_html_e( 'Address line 2', 'edd-flat-rate-shipping' ); ?>"/>
				</p>
				<p id="edd-shipping-city-wrap">
					<label class="edd-label"><?php esc_html_e( 'Shipping City', 'edd-flat-rate-shipping' ); ?></label>
					<span class="edd-description"><?php esc_html_e( 'The city for your shipping address.', 'edd-flat-rate-shipping' ); ?></span>
					<input type="text" name="shipping_city" class="shipping-city edd-input" placeholder="<?php esc_html_e( 'City', 'edd-flat-rate-shipping' ); ?>"/>
				</p>
				<p id="edd-shipping-country-wrap">
					<label class="edd-label"><?php esc_html_e( 'Shipping Country', 'edd-flat-rate-shipping' ); ?></label>
					<span class="edd-description"><?php esc_html_e( 'The country for your shipping address.', 'edd-flat-rate-shipping' ); ?></span>
					<select name="shipping_country" class="shipping-country edd-select">
						<?php
						$countries = edd_get_country_list();
						foreach ( $countries as $country_code => $country ) {
							echo '<option value="' . esc_attr( $country_code ) . '">' . esc_html( $country ) . '</option>';
						}
						?>
					</select>
				</p>
				<p id="edd-shipping-state-wrap">
					<label class="edd-label"><?php esc_html_e( 'Shipping State / Province', 'edd-flat-rate-shipping' ); ?></label>
					<span class="edd-description"><?php esc_html_e( 'The state / province for your shipping address.', 'edd-flat-rate-shipping' ); ?></span>
					<input type="text" size="6" name="shipping_state_other" id="shipping_state_other" class="shipping-state edd-input" placeholder="<?php esc_html_e( 'State / Province', 'edd-flat-rate-shipping' ); ?>" style="display:none;"/>
					<select name="shipping_state_us" id="shipping_state_us" class="shipping-state edd-select">
						<?php
						$states = edd_get_states_list();
						foreach ( $states as $state_code => $state ) {
							echo '<option value="' . esc_attr( $state_code ) . '">' . esc_html( $state ) . '</option>';
						}
						?>
					</select>
					<select name="shipping_state_ca" id="shipping_state_ca" class="shipping-state edd-select" style="display: none;">
						<?php
						$provinces = edd_get_provinces_list();
						foreach ( $provinces as $province_code => $province ) {
							echo '<option value="' . esc_attr( $province_code ) . '">' . esc_html( $province ) . '</option>';
						}
						?>
					</select>
				</p>
				<p id="edd-shipping-zip-wrap">
					<label class="edd-label"><?php esc_html_e( 'Shipping Zip / Postal Code', 'edd-flat-rate-shipping' ); ?></label>
					<span class="edd-description"><?php esc_html_e( 'The zip / postal code for your shipping address.', 'edd-flat-rate-shipping' ); ?></span>
					<input type="text" size="4" name="shipping_zip" class="shipping-zip edd-input" placeholder="<?php esc_html_e( 'Zip / Postal code', 'edd-flat-rate-shipping' ); ?>"/>
				</p>
			</div>
			<?php do_action( 'edd_shipping_address_bottom' ); ?>
		</fieldset>
	</div>
</div>
