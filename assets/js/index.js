var edd_global_vars;

jQuery(document).ready(function ($) {
	$('#edd_purchase_form #edd-shipping-country').val($('#billing_country').val());

	// Once the gateway is loaded, check whether the address fields should show.
	$('body').on('edd_gateway_loaded', function () {
		// Checks for address line 1, or an existing Stripe address.
		if ($('#card_address, .edd-stripe-update-billing-address-current').is(':visible')) {
			$('#edd_flat_rate_shipping_fields_wrap').hide();
		} else {
			$('#edd_flat_rate_shipping_diff_address').hide();
			$('#edd_flat_rate_shipping_fields_wrap').show();
		}
	});

	// Listen for Stripe to update the billing address or add a new card and show fields as needed.
	$('body').on('change', '#edd-stripe-update-billing-address, #edd-stripe-add-new', function () {
		if (($(this).is(':checked') && -1 == $('#edd-existing-shipping-addresses').val()) || $('.edd-stripe-update-billing-address-current').is(':visible')) {
			$('#edd_flat_rate_shipping_diff_address').show();
			$('#edd_flat_rate_shipping_fields_wrap').hide();
		} else {
			$('#edd_flat_rate_shipping_diff_address').hide();
			$('#edd_flat_rate_shipping_fields_wrap').show();
		}
	});

	$('body').on('change', 'select[name=shipping_country],select[name=billing_country]', function () {
		var billing = true;

		if ($('select[name=billing_country]').length && !$('#edd_flat_rate_shipping_show').is(':checked')) {
			var val = $('select[name=billing_country]').val();
		} else {
			var val = $('select[name=shipping_country]').val();
			billing = false;
		}

		if (billing && edd_global_vars.taxes_enabled == '1') {
			return; // EDD core will recalculate on billing address change if taxes are enabled
		}

		if (val == 'US') {
			$('#shipping_state_other').hide();
			$('#shipping_state_us').show();
			$('#shipping_state_ca').hide();
		} else if (val == 'CA') {
			$('#shipping_state_other').hide();
			$('#shipping_state_us').hide();
			$('#shipping_state_ca').show();
		} else {
			$('#shipping_state_other').show();
			$('#shipping_state_us').hide();
			$('#shipping_state_ca').hide();
		}

		edd_shipping_trigger_address_change(val);
	});

	$('body').on('edd_taxes_recalculated', function (event, data) {
		if ($('#edd_flat_rate_shipping_show').is(':checked')) {
			return;
		}

		edd_shipping_trigger_address_change(data.postdata.billing_country);
	});

	$('body').on('change', 'select#edd-gateway, input.edd-gateway', function (e) {
		edd_shipping_trigger_address_change(edd_global_vars.shipping_base_region);
	});

	$('body').on('change', '#edd_flat_rate_shipping_show', function () {
		$('#edd_flat_rate_shipping_fields_wrap').toggle();
	});

	$('body').on('change', '.edd-existing-shipping-addresses', function () {
		var existing_value = $(this).val();
		var target = $('#edd-shipping-new-address-wrapper');
		var country = false;

		if ('new' === existing_value) {
			target.show();
			if ($('select.shipping-country').val().length) {
				country = $('select.shipping-country').val();
			}
		} else {
			target.hide();
			country = $(this).find(':selected').data('country');
		}

		if (false !== country) {
			edd_shipping_trigger_address_change(country);
		}
	});

	/**
	 * Given a country and state, trigger an update of the shipping charges
	 * @param country
	 * @param state
	 */
	function edd_shipping_trigger_address_change(country = '') {
		var purchase_button = $('#edd-purchase-button');
		purchase_button.prop('disabled', true);

		$('#edd-shipping-country').val(country);

		var postData = {
			action: 'edd_get_shipping_rate',
			country: country,
			billing_country: country,
		};

		$.ajax({
			type: 'POST',
			data: postData,
			dataType: 'json',
			url: edd_global_vars.ajaxurl,
			success: function (response) {
				$('#edd_checkout_cart').replaceWith(response.data.html);
				$('.edd_cart_amount').each(function () {
					$(this).text(response.data.total);
				});
			},
			complete: function () {
				purchase_button.prop('disabled', false);
			},
		}).fail(function (data) {
			if (window.console && window.console.log) {
				console.log(data);
			}
		});
	}
});
