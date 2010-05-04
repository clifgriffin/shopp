jQuery(document).ready(function () {
	var $ = jqnc(),
		sameshipping = $('#same-shipping'),
		submitLogin = $('#submit-login'),
		checkoutForm = $('#checkout.shopp'),
		shippingFields = $('#shipping-address-fields'),	
		billingFields = $('#billing-address-fields'),
		localeMenu = $('#billing-locale'),
		localeFields = $('#checkout.shopp li.locale');
		
	if (localeMenu.children().size() == 0) localeFields.hide();
	
	if (sameshipping.length > 0) {
		sameshipping.change(function() {
			if (sameshipping.attr('checked')) {
				billingFields.removeClass('half');
				shippingFields.hide().find('.required').attr('disabled',true);
			} else {
				billingFields.addClass('half');
				shippingFields.show().find('input, select').not('#shipping-xaddress').attr('disabled',false);
			}
		}).change();

		// For IE compatibility
		sameshipping.click(function () { $(this).change(); }); 
	}
	
	submitLogin.click(function () {
		checkoutForm.unbind('submit');
		checkoutForm.submit(function () {
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
	
	if (!checkoutForm.hasClass('validate')) {
		checkoutForm.submit(function () {
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
	
	$('#billing-country, #billing-state').change(function () {
		var country = $('#billing-country').val(),
			state = $('#billing-state').val(),
			id = country+state,options;

		if (!localeMenu.get(0)) return;
		localeMenu.empty().attr('disabled',true);
		if (locales[id]) {
			$.each(locales[id], function (index,label) {
				options += '<option value="'+label+'">'+label+'</option>';
			});
			$(options).appendTo(localeMenu);
			localeMenu.removeAttr('disabled');
			localeFields.show();
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