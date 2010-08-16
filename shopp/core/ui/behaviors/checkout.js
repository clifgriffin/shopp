jQuery(document).ready(function () {
	var $ = jqnc(),
		sameshipping = $('#same-shipping'),
		submitLogin = $('#submit-checkout-login'),
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
				shippingFields.show().find('input, select').not('#shipping-xaddress, .unavailable').attr('disabled',false);
			}
		}).change();

		// For IE compatibility
		sameshipping.click(function () { $(this).change(); }); 
	}
	
	submitLogin.click(function () {
		checkoutForm.unbind('submit').submit(function () {
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
		$('#shipping-state').empty().attr('disabled',true).addClass('unavailable disabled');
		$('<option></option>').val('').html('').appendTo('#shipping-state');
		if (regions[this.value]) {
			$.each(regions[this.value], function (value,label) {
				option = $('<option></option>').val(value).html(label).appendTo('#shipping-state');
			});
			$('#shipping-state').attr('disabled',false).removeClass('unavailable disabled');
		}
	});

	$('#billing-country').change(function() {
		if ($('#billing-state').attr('type') == "text") return true;
		$('#billing-state').empty().attr('disabled',true).addClass('unavailable disabled');
		$('<option></option>').val('').html('').appendTo('#billing-state');
		if (regions[this.value]) {
			$.each(regions[this.value], function (value,label) {
				option = $('<option></option>').val(value).html(label).appendTo('#billing-state');
			});
			$('#billing-state').attr('disabled',false).removeClass('unavailable disabled');
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
	
	$('.shopp .shipmethod').change(function () {
		$('.shopp_cart_shipping, .shopp_cart_tax, .shopp_cart_total').html('?');
		$.getJSON(ShoppSettings.ajaxurl+"?action=shopp_shipping_costs&method="+$(this).val(),
			function (result) {
				var totals = eval(result);
				$('span.shopp_cart_shipping').html(asMoney(totals.shipping));
				$('span.shopp_cart_tax').html(asMoney(totals.tax));
				$('span.shopp_cart_total').html(asMoney(totals.total));
		});
	});
	
	$('#checkout.shopp [name=paymethod]').change(function () {
		var paymethod = $(this).val();
		if (ccpayments[paymethod] != false && ccpayments[paymethod].length > 0) {
			$('#checkout.shopp .payment').show();
			$('#checkout.shopp .creditcard').show();
			$('#checkout.shopp .creditcard.disabled').attr('disabled',false).removeClass('disabled');
			$('#checkout.shopp #billing-cardtype').empty().attr('disabled',false).removeClass('disabled');
			var options = '<option value="" selected="selected"></option>';
			$.each(ccpayments[paymethod], function (a,b) {
				$.each(ccallowed[paymethod], function (c,d){
					if (b.symbol == d) options += '<option value="'+b.symbol+'">'+b.name+'</option>';
				});
			});
			$(options).appendTo('#checkout.shopp #billing-cardtype');

		} else {
			$('#checkout.shopp .payment').hide();
			$('#checkout.shopp .creditcard').hide();
			$('#checkout.shopp .creditcard').addClass('disabled').attr('disabled',true);
			$('#checkout.shopp #billing-cardtype').addClass('disabled').attr('disabled',true);
		}
	}).change().change(function () {
		var paymethod = $(this).val();
		$.post( ShoppSettings.ajaxurl, 
		{	action : 'shopp_checkout_submit_button',
		 	paymethod : paymethod
		},
		function (data) {
			if (data != null) $('#checkout.shopp p.submit').html(data);
		});
	});

});