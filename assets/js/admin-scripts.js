jQuery(document).ready(function ($) {
	$('#edd-tracking-info-notify-customer').click(function (e) {
		e.preventDefault();

		var button = $(this);
		button.addClass('updating-message').attr('disabled', true);

		var payment_id = button.data('payment');
		var nonce = $('#edd-ti-send-tracking').val();

		var postData = {
			edd_action: 'send-tracking',
			payment_id: payment_id,
			nonce: nonce,
		};

		$.post(
			ajaxurl,
			postData,
			function (response) {
				button.removeClass('updating-message').html(response.message);
				if (response.success) {
					button.addClass('updated-message');
				}
			},
			'json'
		);
	});
});
