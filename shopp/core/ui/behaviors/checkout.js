(function($) {

	$(window).ready(function () {
		
		var sameshipping = $('#same-shipping');
		
		if (sameshipping.length > 0) {
			sameshipping.change(function() {
				if ($('#same-shipping').attr('checked')) {
					$('#billing-address-fields').removeClass('half');
					$('#shipping-address-fields').hide();
					$('#shipping-address-fields .required').attr('disabled',true);
				} else {
					$('#billing-address-fields').addClass('half');
					$('#shipping-address-fields input').not('#shipping-xaddress').attr('disabled',false);
					$('#shipping-address-fields select').attr('disabled',false);
					$('#shipping-address-fields').show();
				}
			}).change();

			// For IE compatibility
			sameshipping.click(function () { $(this).change(); }); 
		}
		
		$('#submit-login').click(function () {
			$('#checkout.shopp').unbind('submit');
			$('#checkout.shopp').submit(function () {
				if ($('#account-login').val() == "") {
					alert(CHECKOUT_LOGIN_NAME);
					$('#account-login').focus();
					return false;
				}
				if ($('#password-login').val() == "") {
					alert(CHECKOUT_LOGIN_PASSWORD);
					$('#password-login').focus();
					return false;
				}
				$('#process-login').val('true');
				return true;
			}).submit();
		});
		
		if (!$('#checkout.shopp').hasClass('validate')) {
			$('#checkout.shopp').submit(function () {
				if (validate(this)) return true;
				else return false;
			});
		}

		$('#shipping-country').change(function() {
			if ($('#shipping-state').attr('type') == "text") return true;
			$('#shipping-state').empty().attr('disabled',true);
			$('<option></option>').val('').html('').appendTo('#shipping-state');
			if (regions[this.value]) {
				$.each(regions[this.value], function (value,label) {
					option = $('<option></option>').val(value).html(label).appendTo('#shipping-state');
				});
				$('#shipping-state').attr('disabled',false);
			}
		});

		$('#billing-country').change(function() {
			if ($('#billing-state').attr('type') == "text") return true;
			$('#billing-state').empty().attr('disabled',true);
			$('<option></option>').val('').html('').appendTo('#billing-state');
			if (regions[this.value]) {
				$.each(regions[this.value], function (value,label) {
					option = $('<option></option>').val(value).html(label).appendTo('#billing-state');
				});
				$('#billing-state').attr('disabled',false);
			}
		});	
		
		$('input.shipmethod').click(function () {
			$('#shipping, #total').html(SHIPCALC_STATUS);
			
			var url = $('#shopp form').attr('action');
			url += (url.indexOf("?") == -1)?"?":"&";
			$.getJSON(ajaxurl+"?action=shopp_shipping_costs&method="+$(this).val(),
				function (result) {
					var totals = eval(result);
					$('span.shopp_cart_shipping').html(asMoney(totals.shipping));
					$('span.shopp_cart_tax').html(asMoney(totals.tax));
					$('span.shopp_cart_total').html(asMoney(totals.total));
			});
		});
		
		$('#checkout.shopp [name=paymethod]').change(function () {
			if (ccpayments[$(this).val()] != null && ccpayments[$(this).val()]) {
				$('#checkout.shopp .creditcard').show();
				$('#checkout.shopp .creditcard [disabled]').attr('disabled',false);
			} else {
				$('#checkout.shopp .creditcard').hide();
				$('#checkout.shopp .creditcard .required,#checkout.shopp .creditcard .min3').attr('disabled',true);
			}
		}).change();

	});
})(jQuery)