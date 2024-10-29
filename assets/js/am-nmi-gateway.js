jQuery(function ($) {
	'use strict';


	// Listen for input events on the credit card input field
	$('#am-nmi-gateway-for-woocommerce-card-number').on('input', function (e) {
		// Remove all spaces first
		let ccNumber = $(this).val().replace(/\s+/g, '');

		// Insert a space after every 4 digits
		ccNumber = ccNumber.replace(/(.{4})/g, '$1 ');

		// Trim any trailing space and update the input value
		$(this).val(ccNumber.trim());
	});


	let nmi_error = {},
		card_allowed;

	/**
	 * Object to handle NMI payment forms.
	 */
	let am_nmi_form = {

		/**
		 * Creates all NMI elements that will be used to enter cards or IBANs.
		 */
		createElements: function () {

			const customCss = !($('#cfw-payment-method').length || $('.woolentor-step--payment').length || $('.avada-checkout').length || $('.ro-checkout-process').length || $('button.wfacp_next_page_button').length) ? {} : {
				"height": "30px"
			}

			if (window.CollectJS !== undefined) {

				CollectJS.configure({
					//"paymentSelector" : "#place_order",
					"variant": "inline",
					"styleSniffer": "true",
					"customCss": customCss,
					//"googleFont": "Montserrat:400",
					"fields": {
						"ccnumber": {
							"selector": "#am-nmi-gateway-for-woocommerce-card-number-element",
							"placeholder": "•••• •••• •••• ••••"
						},
						"ccexp": {
							"selector": "#am-nmi-gateway-for-woocommerce-card-expiry-element",
							"placeholder": wc_nmi_params.placeholder_expiry
						},
						"cvv": {
							"display": "show",
							"selector": "#am-nmi-gateway-for-woocommerce-card-cvc-element",
							"placeholder": wc_nmi_params.placeholder_cvc
						}
					},
					'validationCallback': function (field, status, message) {
						if (status) {
							message = field + " is OK: " + message;
							nmi_error[field] = '';
						} else {
							nmi_error[field] = message;
						}
						console.log(message);
					},
					"timeoutDuration": 20000,
					"timeoutCallback": function () {
						$(document).trigger('nmiError', wc_nmi_params.timeout_error);
					},
					"fieldsAvailableCallback": function () {
						am_nmi_form.unblock();
						console.log("Collect.js loaded the fields onto the form");
					},
					'callback': function (response) {
						am_nmi_form.onNMIResponse(response);
					}
				});
			} else {
				$(document).trigger('nmiError', wc_nmi_params.collect_js_error);
				$('#wc-am-nmi-gateway-for-woocommerce-cc-form label, #wc-am-nmi-gateway-for-woocommerce-cc-form .wc-am-nmi-gateway-for-woocommerce-elements-field').hide();
			}

		},

		/**
		 * Initialize event handlers and UI state.
		 */
		init: function () {
			// checkout page
			if ($('form.woocommerce-checkout').length) {
				this.form = $('form.woocommerce-checkout');
			}

			$('form.woocommerce-checkout')
				.on(
					'checkout_place_order_nmi',
					this.onSubmit
				);

			// pay order page
			if ($('form#order_review').length) {
				this.form = $('form#order_review');
			}

			$('form#order_review')
				.on(
					'submit',
					this.onSubmit
				);

			// add payment method page
			if ($('form#add_payment_method').length) {
				this.form = $('form#add_payment_method');
			}

			$('form#add_payment_method')
				.on(
					'submit',
					this.onSubmit
				);

			$(document)
				.on(
					'change',
					'#wc-am-nmi-gateway-for-woocommerce-cc-form :input',
					this.onCCFormChange
				)
				.on(
					'nmiError',
					this.onError
				)
				.on(
					'checkout_error',
					this.clearToken
				);

			if (am_nmi_form.isNMIChosen()) {
				am_nmi_form.block();
				am_nmi_form.createElements();
			}

			// CheckoutWC and woolentor, La Forat theme
			$('body').on('click', 'a[href="#cfw-payment-method"], a[data-tab="#cfw-payment-method"], a[data-step="step--payment"], a.ro-tab-2, a.ro-btn-2, button.wfacp_next_page_button', function () {
				// Don't re-mount if already mounted in DOM.
				if (am_nmi_form.isNMIChosen()) {
					am_nmi_form.block();
					am_nmi_form.createElements();
				}
			});

			/**
			 * Only in checkout page we need to delay the mounting of the
			 * card as some AJAX process needs to happen before we do.
			 */
			if ('yes' === wc_nmi_params.is_checkout) {
				$(document.body).on('updated_checkout', function () {
					// Re-mount  on updated checkou
					if (am_nmi_form.isNMIChosen()) {
						am_nmi_form.block();
						am_nmi_form.createElements();
					}

				});
			}

			$(document.body).on('payment_method_selected', function () {
				// Don't re-mount if already mounted in DOM.
				if (am_nmi_form.isNMIChosen()) {
					am_nmi_form.block();
					am_nmi_form.createElements();
				}
			});

			if (this.form !== undefined) {
				this.form.on('click change', 'input[name="wc-am-nmi-gateway-for-woocommerce-payment-token"]', function () {
					if (am_nmi_form.isNMIChosen() && !$('#am-nmi-gateway-for-woocommerce-card-number-element').children().length) {
						am_nmi_form.block();
						am_nmi_form.createElements();
					}
				});
			}
		},

		isNMIChosen: function () {
			return $('#payment_method_nmi').is(':checked') && (!$('input[name="wc-am-nmi-gateway-for-woocommerce-payment-token"]:checked').length || 'new' === $('input[name="wc-am-nmi-gateway-for-woocommerce-payment-token"]:checked').val());
		},

		hasToken: function () {
			return (0 < $('input.nmi_js_token').length) && (0 < $('input.nmi_js_response').length);
		},

		block: function () {
			am_nmi_form.form.block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});
		},

		unblock: function () {
			am_nmi_form.form.unblock();
		},

		getSelectedPaymentElement: function () {
			return $('.payment_methods input[name="payment_method"]:checked');
		},

		onError: function (e, result) {
			//console.log(responseObject.response);
			let message = result;
			let selectedMethodElement = am_nmi_form.getSelectedPaymentElement().closest('li');
			let savedTokens = selectedMethodElement.find('.woocommerce-SavedPaymentMethods-tokenInput');
			let errorContainer;

			if (savedTokens.length) {
				// In case there are saved cards too, display the message next to the correct one.
				let selectedToken = savedTokens.filter(':checked');

				if (selectedToken.closest('.woocommerce-SavedPaymentMethods-new').length) {
					// Display the error next to the CC fields if a new card is being entered.
					errorContainer = $('#wc-am-nmi-gateway-for-woocommerce-cc-form .nmi-source-errors');
				} else {
					// Display the error next to the chosen saved card.
					errorContainer = selectedToken.closest('li').find('.nmi-source-errors');
				}
			} else {
				// When no saved cards are available, display the error next to CC fields.
				errorContainer = selectedMethodElement.find('.nmi-source-errors');
			}

			am_nmi_form.onCCFormChange();
			$('.woocommerce-NoticeGroup-checkout').remove();
			console.log(result); // Leave for troubleshooting.
			$(errorContainer).html('<ul class="woocommerce_error woocommerce-error wc-am-nmi-gateway-for-woocommerce-error"><li /></ul>');
			$(errorContainer).find('li').text(message); // Prevent XSS

			if ($('.wc-am-nmi-gateway-for-woocommerce-error').length) {
				$('html, body').animate({
					scrollTop: ($('.wc-am-nmi-gateway-for-woocommerce-error').offset().top - 200)
				}, 200);
			}
			am_nmi_form.unblock();
		},

		onSubmit: function (e) {
			if (am_nmi_form.isNMIChosen() && !am_nmi_form.hasToken()) {
				e.preventDefault();
				am_nmi_form.block();
				let error_message;

				console.log(nmi_error);

				let validCardNumber = document.querySelector("#am-nmi-gateway-for-woocommerce-card-number-element .CollectJSValid") !== null;
				let validCardExpiry = document.querySelector("#am-nmi-gateway-for-woocommerce-card-expiry-element .CollectJSValid") !== null;
				let validCardCvv = document.querySelector("#am-nmi-gateway-for-woocommerce-card-cvc-element .CollectJSValid") !== null;

				if (!validCardNumber) {
					error_message = wc_nmi_params.card_number_error + (nmi_error.ccnumber ? ' ' + wc_nmi_params.error_ref.replace('[ref]', nmi_error.ccnumber) : '');
					$(document.body).trigger('nmiError', error_message);
					return false;
				}

				if (!validCardExpiry) {
					error_message = wc_nmi_params.card_expiry_error + (nmi_error.ccexp ? ' ' + wc_nmi_params.error_ref.replace('[ref]', nmi_error.ccexp) : '');
					$(document.body).trigger('nmiError', error_message);
					return false;
				}

				if (!validCardCvv) {
					error_message = wc_nmi_params.card_cvc_error + (nmi_error.cvv ? ' ' + wc_nmi_params.error_ref.replace('[ref]', nmi_error.cvv) : '');
					$(document.body).trigger('nmiError', error_message);
					return false;
				}

				CollectJS.startPaymentRequest();

				// Prevent form submitting
				return false;
			}
		},

		onCCFormChange: function () {
			$('.wc-am-nmi-gateway-for-woocommerce-error, .nmi_js_token, .nmi_js_response').remove();
		},

		onNMIResponse: function (response) {
			console.log(response);

			if (response.card.type != null) {
				wc_nmi_params.allowed_card_types.forEach(function (card_type) {
					if (response.card.type == card_type.replace('diners-club', 'diners')) {
						card_allowed = true;
					}
				});

				if (!card_allowed) {
					$(document.body).trigger('nmiError', wc_nmi_params.card_disallowed_error);
					return false;
				}
			}

			am_nmi_form.form.append("<input type='hidden' class='nmi_js_token' name='nmi_js_token' value='" + response.token + "'/>");
			am_nmi_form.form.append("<input type='hidden' class='nmi_js_response' name='nmi_js_response' value='" + JSON.stringify(response) + "'/>");
			am_nmi_form.form.submit();
		},

		clearToken: function () {
			$('.nmi_js_token, .nmi_js_response').remove();
		}
	};

	am_nmi_form.init();
});


