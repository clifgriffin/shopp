(function($) {

	var validate = function (form) {
		console.log(form)
		var passed = true;
		var inputs = form.getElementsByTagName('input');
		var selects = form.getElementsByTagName('select');
		var error = new Array();

		for (var i = selects.length-1; i >= 0; i--) {
			// Validate required fields
			if (selects[i].className.match(new RegExp('required'))) {
				if (selects[i].options[selects[i].selectedIndex].value == "")
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
				
		}
	
		if (error.length > 0) {
			error[1].focus();
			alert(error[0]);
			passed = false;
		}
		return passed;
	}

	$(window).ready(function () {
		$('#useshipping').click(function() {
			if(this.checked) {
		 		$('#billing-address').val($('#shipping-address').val()).attr('readonly','readonly');
		 		$('#billing-xaddress').val($('#shipping-xaddress').val()).attr('readonly','readonly');
		 		$('#billing-city').val($('#shipping-city').val()).attr('readonly','readonly');
		 		$('#billing-postcode').val($('#shipping-postcode').val()).attr('readonly','readonly');
		 		$('#billing-country').val($('#shipping-country').val()).attr('readonly','readonly').change();
		 		$('#billing-state').val($('#shipping-state').val()).attr('readonly','readonly');
			} else {
				$('#billing-address').removeAttr('readonly');
		 		$('#billing-xaddress').removeAttr('readonly');
		 		$('#billing-city').removeAttr('readonly');
		 		$('#billing-state').removeAttr('readonly');
		 		$('#billing-postcode').removeAttr('readonly');
		 		$('#billing-country').removeAttr('readonly');
			}
		});

		$('#checkout.shopp').submit(function () {
			if (validate(this)) return true;
			else return false;
		});

		$('#shipping-country').change(function() {
			$('#shipping-state').empty();
			$('<option></option>').val('').html('').appendTo('#shipping-state');
			$.each(regions[this.value], function (value,label) {
					option = $('<option></option>').val(value).html(label).appendTo('#shipping-state');
			});
		});

		$('#billing-country').change(function() {
			$('#billing-state').empty();
			$('<option></option>').val('').html('').appendTo('#billing-state');
			$.each(regions[this.value], function (value,label) {
					option = $('<option></option>').val(value).html(label).appendTo('#billing-state');
			});
		});	

	});
})(jQuery)


