$j=jQuery.noConflict();

var validate = function (form) {
	var passed = true;
	var inputs = form.getElementsByTagName('input');
	var selects = form.getElementsByTagName('select');
	var error = new Array();

	for (var i = selects.length-1; i >= 0; i--) {
		// Validate required fields
		if (selects[i].className.match(new RegExp('required'))) {
			if (selects[i].selectedIndex == 0)
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
$j(window).ready(function () {

	$j('#useshipping').click(function() {
		if(this.checked) {
	 		$j('#billing-address').val($j('#shipping-address').val()).attr('readonly','readonly');
	 		$j('#billing-xaddress').val($j('#shipping-xaddress').val()).attr('readonly','readonly');
	 		$j('#billing-city').val($j('#shipping-city').val()).attr('readonly','readonly');
	 		$j('#billing-state').val($j('#shipping-state').val()).attr('readonly','readonly');
	 		$j('#billing-postcode').val($j('#shipping-postcode').val()).attr('readonly','readonly');
	 		$j('#billing-country').val($j('#shipping-country').val()).attr('readonly','readonly');
		} else {
			$j('#billing-address').removeAttr('readonly');
	 		$j('#billing-xaddress').removeAttr('readonly');
	 		$j('#billing-city').removeAttr('readonly');
	 		$j('#billing-state').removeAttr('readonly');
	 		$j('#billing-postcode').removeAttr('readonly');
	 		$j('#billing-country').removeAttr('readonly');
		}
	});

	$j('#checkout-form').submit(function () {
		if (validate(this)) return true;
		else return false;
	});
	
});
