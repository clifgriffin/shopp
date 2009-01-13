(function($) {

	var validate = function (form) {
		if (!form) return false;
		
		var inputs = form.getElementsByTagName('input');
		var selects = form.getElementsByTagName('select');

		var passed = true;
		var passwords = new Array();
		var error = new Array();

		for (var i = selects.length-1; i >= 0; i--) {
			// Validate required fields
			if (selects[i].className.match(new RegExp('required')) && !selects[i].disabled) {
				if (selects[i].selectedIndex == 0 && selects[i].options[0].value == "")
					error = new Array("Your "+selects[i].title+" is required.",selects[i]);
			}
		}

		for (var i = inputs.length-1; i >= 0; i--) {
			// Validate required fields
			if (inputs[i].className.match(new RegExp('required'))) {
				if (inputs[i].value == null || inputs[i].value == "")
					error = new Array("Your "+inputs[i].title+" is required.",inputs[i]);
			}
		
			// Validate emails
			if (inputs[i].className.match(new RegExp('email'))) {
				if (!inputs[i].value.match(new RegExp('^[a-zA-Z0-9._-]+@([a-zA-Z0-9.-]+\.)+[a-zA-Z0-9.-]{2,4}$'))) 
					error = new Array("The e-mail address you provided does not appear to be a valid address.",inputs[i]);
			}
		
			// Validate minumum lengths
			if(chars = inputs[i].className.match(new RegExp('min(\\d+)'))) {
				if (inputs[i].value.length < chars[1])
					error = new Array("The "+inputs[i].title+" you entered is too short. It must be at least "+chars[1]+" characters long.",inputs[i]);
			}

			// Validate minumum lengths
			if (inputs[i].className.match(new RegExp('passwords'))) {
				passwords.push(inputs[i]);
				if (passwords.length == 2 && passwords[0].value != passwords[1].value)
					error = new Array("The passwords you entered do not match. They must match in order to confirm you are correctly entering the password you want to use.",passwords[1]);
					
			}
		}
	
		if (error.length > 0) {
			error[1].focus();
			alert(error[0]);
			passed = false;
		}
		return passed;
	}

	$(window).ready(function () {

		$('#same-shipping').change(function() {
			if ($('#same-shipping').attr('checked')) {
				$('#billing-address-fields').removeClass('half');
				$('#shipping-address-fields').hide();
				$('#shipping-address-fields .required').removeClass('required');
			} else {
				$('#billing-address-fields').addClass('half');
				$('#shipping-address-fields input').addClass('required');
				$('#shipping-address-fields select').addClass('required');
				$('#shipping-address-fields').show();
			}
		}).change();

		// For IE compatibility
		$('#same-shipping').click(function () { $(this).change(); }); 
		
		$('#submit-login').click(function () {
			$('#checkout.shopp').unbind('submit');
			$('#checkout.shopp').submit(function () {
				if ($('#email-login').val() == "") {
					alert("You did not enter an email address to login with.");
					$('#email-login').focus();
					return false;
				}
				if ($('#password-login').val() == "") {
					alert("You did not enter a password to login with.");
					$('#password-login').focus();
					return false;
				}
				$('#process-login').val('login');
				return true;
			}).submit();
		});
		
		$('#checkout.shopp').submit(function () {
			if (validate(this)) return true;
			else return false;
		});

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
			// console.log($('#shopp form').attr('action'));
			$.getJSON($('#shopp form').attr('action')+"?shopp_lookup=shipcost&method="+$(this).val(),
				function (result) {
					var totals = eval(result);
					$('#shipping').html(asMoney(totals.shipping));
					$('#total').html(asMoney(totals.total));
			});
		});

	});
})(jQuery)


