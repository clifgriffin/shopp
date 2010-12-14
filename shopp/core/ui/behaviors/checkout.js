jQuery(document).ready(function () {
	var $ = jqnc(),login=false,
		sameship = $('#same-shipping'),
		submitLogin = $('#submit-login-checkout'),
		accountLogin = $('#account-login-checkout'),
		passwordLogin = $('#password-login-checkout'),
		checkoutForm = $('#checkout.shopp'),
		shipFields = $('#shipping-address-fields'),	
		billFields = $('#billing-address-fields'),
		paymethods = $('#checkout.shopp [name=paymethod]'),
		localeMenu = $('#billing-locale'),
		billCard = $('#billing-card'),
		billCardtype = $('#billing-cardtype'),
		checkoutButtons = $('.payoption-button'),
		localeFields = $('#checkout.shopp li.locale');
				
	// No payment option selectors found, use default
	if (paymethods.length == 0) paymethod_select(false,d_pm);
	else paymethods.change(paymethod_select).change();
	
	// Validate paycard number before submit
	checkoutForm.bind('shopp_validate',function () {
		if (!validcard()) this.shopp_validate = ["Not a valid card number.",billCard.get(0)];
	});
	
	// Validate paycard number on entry
	billCard.change(validcard);
	
	// Enable/disable the extra card security fields when needed
	billCardtype.change(function () {
		var cardtype = billCardtype.val(),
			card = paycards[cardtype.toLowerCase()];

		$('.paycard.xcsc').attr('disabled',true).addClass('disabled');
		if (!card || !card['inputs']) return;
			
		$.each(card['inputs'],function (input,inputlen) {
			$('#billing-xcsc-'+input).attr('disabled',false).removeClass('disabled');
		});
		
	}).change();

	if (localeMenu.children().size() == 0) localeFields.hide();
	
	sameship.change(function() {
		if (sameship.attr('checked')) {
			billFields.removeClass('half');
			shipFields.hide().find('.required').attr('disabled',true);
		} else {
			billFields.addClass('half');
			shipFields.show().find('input, select').not('#shipping-xaddress, .unavailable').attr('disabled',false);
		}
	}).change()
		.click(function () { $(this).change(); }); // For IE compatibility

	
	submitLogin.click(function () { login=true; });
	checkoutForm.unbind('submit').submit(function () {
		if (login) {
			login=false;
			if (accountLogin.val() == "") {
				alert(sjss.LOGIN_NAME_REQUIRED);
				accountLogin.focus();
				return false;
			}
			if (passwordLogin.val() == "") {
				alert(sjss.LOGIN_PASSWORD_REQUIRED);
				passwordLogin.focus();
				return false;
			}
			$('#process-login').val('true');
			return true;
		}

		if (validate(this)) return true;
		else return false;
	});

	$('#billing-country,#shipping-country').change(function() {
		var prefix = $(this).attr('id').split('-')[0],
			country = $(this).val();
			state = $('#'+prefix+'-state'),
			options = ['<option value=""></option>'];
		if (state.attr('type') == "text") return true;
		if (!regions[country]) return state.empty().attr('disabled',true).addClass('unavailable disabled');;
		$.each(regions[country], function (value,label) {
			options += '<option value="'+value+'">'+label+'</option>';
		});
		state.empty().append(options).attr('disabled',false).removeClass('unavailable disabled');
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
		if ($(this).parents('#checkout').size()) {
			$('.shopp_cart_shipping, .shopp_cart_tax, .shopp_cart_total').html('?');
			$.getJSON(sjss.ajaxurl+"?action=shopp_ship_costs&method="+$(this).val(),
				function (r) {
					var prefix = 'span.shopp_cart_';
					$(prefix+'shipping').html(asMoney(new Number(r.shipping)));
					$(prefix+'tax').html(asMoney(new Number(r.tax)));
					$(prefix+'total').html(asMoney(new Number(r.total)));
			});
		} else $(this).parents('form').submit();
	});

	$(window).load(function () {
		$(document).trigger('shopp_paymethod',[paymethods.val()]);
	});

	function paymethod_select (e,paymethod) {
		if (!paymethod) paymethod = $(this).val();
		var $this = $(this),checkoutButton = $('.payoption-'+paymethod),options='',pc = false;
		if ($this.attr('type') == "radio" && $this.attr('checked') == false) return;
		$(document).trigger('shopp_paymethod',[paymethod]);

		checkoutButtons.hide();
		if (checkoutButton.length == 0) checkoutButton = $('.payoption-0');

		if (pm_cards[paymethod] && pm_cards[paymethod].length > 0) {
			checkoutForm.find('.payment,.paycard').show();
			checkoutForm.find('.paycard.disabled').attr('disabled',false).removeClass('disabled');
			$.each(pm_cards[paymethod], function (a,s) {
				if (!paycards[s]) return;
				pc = paycards[s];
				options += '<option value="'+pc.symbol+'">'+pc.name+'</option>';				
			});
			billCardtype.html(options).change();

		} else {
			checkoutForm.find('.payment,.paycard').hide();
			checkoutForm.find('.paycard').attr('disabled',true).addClass('disabled');
		}
		checkoutButton.show();
	}

	function validcard () {
		if (billCard.length == 0) return true;
		if (billCard.attr('disabled')) return true;
		var v = billCard.val().replace(/\D/g,''),
			paymethod = paymethods.filter(':checked').val()?paymethods.filter(':checked').val():paymethods.val(),
			card = false;
		if (!paymethod || !pm_cards[paymethod]) return false;
		$.each(pm_cards[paymethod], function (a,s) {
			var pc = paycards[s],pattern = new RegExp(pc.pattern.substr(1,pc.pattern.length-2));
			if (v.match(pattern)) {
				card = pc.symbol;
				return billCardtype.val(card).change();
			}
		});
		if (!luhn(v)) return false;
		return card;
	}
	
	function luhn (n) {
		n = n.toString().replace(/\D/g, '').split('').reverse();
		if (!n.length) return false;

		var total = 0;
		for (i = 0; i < n.length; i++) {
			n[i] = parseInt(n[i]);
			total += i % 2 ? 2 * n[i] - (n[i] > 4 ? 9 : 0) : n[i];
		}
		return (total % 10) == 0;
	}

	
});