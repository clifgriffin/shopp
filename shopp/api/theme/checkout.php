<?php

add_filter('shoppapi_checkout_billingaddress', array('ShoppCheckoutAPI','billingaddress'), 10, 3);
add_filter('shoppapi_checkout_billingcard', array('ShoppCheckoutAPI','billingcard'), 10, 3);
add_filter('shoppapi_checkout_billingcardexpiresmm', array('ShoppCheckoutAPI','billingcardexpiresmm'), 10, 3);
add_filter('shoppapi_checkout_billingcardexpiresyy', array('ShoppCheckoutAPI','billingcardexpiresyy'), 10, 3);
add_filter('shoppapi_checkout_billingcardholder', array('ShoppCheckoutAPI','billingcardholder'), 10, 3);
add_filter('shoppapi_checkout_billingcardtype', array('ShoppCheckoutAPI','billingcardtype'), 10, 3);
add_filter('shoppapi_checkout_billingcity', array('ShoppCheckoutAPI','billingcity'), 10, 3);
add_filter('shoppapi_checkout_billingcountry', array('ShoppCheckoutAPI','billingcountry'), 10, 3);
add_filter('shoppapi_checkout_billingcvv', array('ShoppCheckoutAPI','billingcvv'), 10, 3);
add_filter('shoppapi_checkout_billinglocale', array('ShoppCheckoutAPI','billinglocale'), 10, 3);
add_filter('shoppapi_checkout_billinglocalities', array('ShoppCheckoutAPI','billinglocalities'), 10, 3);
add_filter('shoppapi_checkout_billingpostcode', array('ShoppCheckoutAPI','billingpostcode'), 10, 3);
add_filter('shoppapi_checkout_billingprovince', array('ShoppCheckoutAPI','billingstate'), 10, 3);
add_filter('shoppapi_checkout_billingstate', array('ShoppCheckoutAPI','billingstate'), 10, 3);
add_filter('shoppapi_checkout_billingrequired', array('ShoppCheckoutAPI','cardrequired'), 10, 3);
add_filter('shoppapi_checkout_cardrequired', array('ShoppCheckoutAPI','cardrequired'), 10, 3);
add_filter('shoppapi_checkout_billingxaddress', array('ShoppCheckoutAPI','billingxaddress'), 10, 3);
add_filter('shoppapi_checkout_billingxco', array('ShoppCheckoutAPI','billingxco'), 10, 3);
add_filter('shoppapi_checkout_billingxcsc', array('ShoppCheckoutAPI','billingxcsc'), 10, 3);
add_filter('shoppapi_checkout_billingxcscrequired', array('ShoppCheckoutAPI','billingxcscrequired'), 10, 3);
add_filter('shoppapi_checkout_cartsummary', array('ShoppCheckoutAPI','cartsummary'), 10, 3);
add_filter('shoppapi_checkout_completed', array('ShoppCheckoutAPI','completed'), 10, 3);
add_filter('shoppapi_checkout_confirmbutton', array('ShoppCheckoutAPI','confirmbutton'), 10, 3);
add_filter('shoppapi_checkout_confirmpassword', array('ShoppCheckoutAPI','confirmpassword'), 10, 3);
add_filter('shoppapi_checkout_customerinfo', array('ShoppCheckoutAPI','customerinfo'), 10, 3);
add_filter('shoppapi_checkout_data', array('ShoppCheckoutAPI','data'), 10, 3);
add_filter('shoppapi_checkout_email', array('ShoppCheckoutAPI','email'), 10, 3);
add_filter('shoppapi_checkout_emaillogin', array('ShoppCheckoutAPI','accountlogin'), 10, 3);
add_filter('shoppapi_checkout_loginnamelogin', array('ShoppCheckoutAPI','accountlogin'), 10, 3);
add_filter('shoppapi_checkout_accountlogin', array('ShoppCheckoutAPI','accountlogin'), 10, 3);
add_filter('shoppapi_checkout_errors', array('ShoppCheckoutAPI','error'), 10, 3);
add_filter('shoppapi_checkout_error', array('ShoppCheckoutAPI','error'), 10, 3);
add_filter('shoppapi_checkout_firstname', array('ShoppCheckoutAPI','firstname'), 10, 3);
add_filter('shoppapi_checkout_function', array('ShoppCheckoutAPI','checkoutfunction'), 10, 3);
add_filter('shoppapi_checkout_gatewayinputs', array('ShoppCheckoutAPI','gatewayinputs'), 10, 3);
add_filter('shoppapi_checkout_hasdata', array('ShoppCheckoutAPI','hasdata'), 10, 3);
add_filter('shoppapi_checkout_lastname', array('ShoppCheckoutAPI','lastname'), 10, 3);
add_filter('shoppapi_checkout_localpayment', array('ShoppCheckoutAPI','localpayment'), 10, 3);
add_filter('shoppapi_checkout_loggedin', array('ShoppCheckoutAPI','loggedin'), 10, 3);
add_filter('shoppapi_checkout_loginname', array('ShoppCheckoutAPI','loginname'), 10, 3);
add_filter('shoppapi_checkout_marketing', array('ShoppCheckoutAPI','marketing'), 10, 3);
add_filter('shoppapi_checkout_notloggedin', array('ShoppCheckoutAPI','notloggedin'), 10, 3);
add_filter('shoppapi_checkout_orderdata', array('ShoppCheckoutAPI','orderdata'), 10, 3);
add_filter('shoppapi_checkout_organization', array('ShoppCheckoutAPI','company'), 10, 3);
add_filter('shoppapi_checkout_company', array('ShoppCheckoutAPI','company'), 10, 3);
add_filter('shoppapi_checkout_password', array('ShoppCheckoutAPI','password'), 10, 3);
add_filter('shoppapi_checkout_passwordlogin', array('ShoppCheckoutAPI','passwordlogin'), 10, 3);
add_filter('shoppapi_checkout_payoption', array('ShoppCheckoutAPI','payoption'), 10, 3);
add_filter('shoppapi_checkout_paymentoption', array('ShoppCheckoutAPI','payoption'), 10, 3);
add_filter('shoppapi_checkout_payoptions', array('ShoppCheckoutAPI','payoptions'), 10, 3);
add_filter('shoppapi_checkout_paymentoptions', array('ShoppCheckoutAPI','payoptions'), 10, 3);
add_filter('shoppapi_checkout_phone', array('ShoppCheckoutAPI','phone'), 10, 3);
add_filter('shoppapi_checkout_receipt', array('ShoppCheckoutAPI','receipt'), 10, 3);
add_filter('shoppapi_checkout_residentialshippingaddress', array('ShoppCheckoutAPI','residentialshippingaddress'), 10, 3);
add_filter('shoppapi_checkout_sameshippingaddress', array('ShoppCheckoutAPI','sameshippingaddress'), 10, 3);
add_filter('shoppapi_checkout_shipping', array('ShoppCheckoutAPI','shipping'), 10, 3);
add_filter('shoppapi_checkout_shippingaddress', array('ShoppCheckoutAPI','shippingaddress'), 10, 3);
add_filter('shoppapi_checkout_shippingcity', array('ShoppCheckoutAPI','shippingcity'), 10, 3);
add_filter('shoppapi_checkout_shippingcountry', array('ShoppCheckoutAPI','shippingcountry'), 10, 3);
add_filter('shoppapi_checkout_shippingpostcode', array('ShoppCheckoutAPI','shippingpostcode'), 10, 3);
add_filter('shoppapi_checkout_shippingprovince', array('ShoppCheckoutAPI','shippingstate'), 10, 3);
add_filter('shoppapi_checkout_shippingstate', array('ShoppCheckoutAPI','shippingstate'), 10, 3);
add_filter('shoppapi_checkout_shippingxaddress', array('ShoppCheckoutAPI','shippingxaddress'), 10, 3);
add_filter('shoppapi_checkout_submit', array('ShoppCheckoutAPI','submit'), 10, 3);
add_filter('shoppapi_checkout_submitlogin', array('ShoppCheckoutAPI','submitlogin'), 10, 3);
add_filter('shoppapi_checkout_loginbutton', array('ShoppCheckoutAPI','submitlogin'), 10, 3);
add_filter('shoppapi_checkout_url', array('ShoppCheckoutAPI','url'), 10, 3);
add_filter('shoppapi_checkout_xcobuttons', array('ShoppCheckoutAPI','xcobuttons'), 10, 3);

/**
 * Provides shopp('checkout') theme API functionality
 *
 * @author Jonathan Davis, John Dillick
 * @since 1.2
 **/
class ShoppCheckoutAPI {
	function accountlogin ($result, $options, $O) {
		if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
		if (!empty($_POST['account-login']))
			$options['value'] = $_POST['account-login'];
		return '<input type="text" name="account-login" id="account-login"'.inputattrs($options).' />';
	}

	function billingaddress ($result, $options, $O) {
		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return $O->Billing->address;
		if (!empty($O->Billing->address))
			$options['value'] = $O->Billing->address;
		return '<input type="text" name="billing[address]" id="billing-address" '.inputattrs($options).' />';
	}

	function billingcard ($result, $options, $O) {
		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value")
			return str_repeat('X',strlen($O->Billing->card)-4)
				.substr($O->Billing->card,-4);
		$options['class'] = isset($options['class']) ? $options['class'].' paycard':'paycard';
		if (!empty($O->Billing->card))
			$options['value'] = $O->Billing->card;
		if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
		return '<input type="text" name="billing[card]" id="billing-card" '.inputattrs($options).' />';
	}

	function billingcardexpiresmm ($result, $options, $O) {
		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return date("m",$O->Billing->cardexpires);
		$options['class'] = isset($options['class']) ? $options['class'].' paycard':'paycard';
		if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
		if (!empty($O->Billing->cardexpires))
			$options['value'] = date("m",$O->Billing->cardexpires);
		return '<input type="text" name="billing[cardexpires-mm]" id="billing-cardexpires-mm" '.inputattrs($options).' />';
	}

	function billingcardexpiresyy ($result, $options, $O) {
		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return date("y",$O->Billing->cardexpires);
		$options['class'] = isset($options['class']) ? $options['class'].' paycard':'paycard';
		if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
		if (!empty($O->Billing->cardexpires))
			$options['value'] = date("y",$O->Billing->cardexpires);
		return '<input type="text" name="billing[cardexpires-yy]" id="billing-cardexpires-yy" '.inputattrs($options).' />';
	}

	function billingcardholder ($result, $options, $O) {
		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return $O->Billing->cardholder;
		$options['class'] = isset($options['class']) ? $options['class'].' paycard':'paycard';
		if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
		if (!empty($O->Billing->cardholder))
			$options['value'] = $O->Billing->cardholder;
		return '<input type="text" name="billing[cardholder]" id="billing-cardholder" '.inputattrs($options).' />';
	}

	function billingcardtype ($result, $options, $O) {
		$select_attrs = array('title','required','class','disabled','required','size','tabindex','accesskey');

		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return $O->Billing->cardtype;
		$options['class'] = isset($options['class']) ? $options['class'].' paycard':'paycard';
		if (!isset($options['selected'])) $options['selected'] = false;
		if (!empty($O->Billing->cardtype))
			$options['selected'] = $O->Billing->cardtype;

		$cards = array();
		foreach ($O->paycards as $paycard)
			$cards[$paycard->symbol] = $paycard->name;

		$label = (!empty($options['label']))?$options['label']:'';
		$output = '<select name="billing[cardtype]" id="billing-cardtype" '.inputattrs($options,$select_attrs).'>';
		$output .= '<option value="" selected="selected">'.$label.'</option>';
	 	$output .= menuoptions($cards,$options['selected'],true);
		$output .= '</select>';

		$js = array();
		$js[] = "var paycards = {};";
		foreach ($O->paycards as $handle => $paycard) {
			$js[] = "paycards['".$handle."'] = ".json_encode($paycard).";";
		}
		add_storefrontjs(join("",$js), true);

		return $output;
	}

	function billingcity ($result, $options, $O) {
		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return $O->Billing->city;
		if (!empty($O->Billing->city))
			$options['value'] = $O->Billing->city;
		return '<input type="text" name="billing[city]" id="billing-city" '.inputattrs($options).' />';
	}

	function billingcountry ($result, $options, $O) {
		global $Shopp;
		$base = $Shopp->Settings->get('base_operations');
		$countries = $Shopp->Settings->get('target_markets');
		$select_attrs = array('title','required','class','disabled','required','size','tabindex','accesskey');

		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return $O->Billing->country;
		if (!empty($O->Billing->country))
			$options['selected'] = $O->Billing->country;
		else if (empty($options['selected'])) $options['selected'] = $base['country'];
		$output = '<select name="billing[country]" id="billing-country" '.inputattrs($options,$select_attrs).'>';
	 	$output .= menuoptions($countries,$options['selected'],true);
		$output .= '</select>';
		return $output;
	}

	function billingcvv ($result, $options, $O) {
		if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
		if (!empty($_POST['billing']['cvv']))
			$options['value'] = $_POST['billing']['cvv'];
		$options['class'] = isset($options['class']) ? $options['class'].' paycard':'paycard';
		return '<input type="text" name="billing[cvv]" id="billing-cvv" '.inputattrs($options).' />';
	}

	function billinglocale ($result, $options, $O) {
		global $Shopp;
		$select_attrs = array('title','required','class','disabled','required','size','tabindex','accesskey');

		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return $O->Billing->locale;
		if (!isset($options['selected'])) $options['selected'] = false;
		if (!empty($O->Billing->locale)) {
			$options['selected'] = $O->Billing->locale;
			$options['value'] = $O->Billing->locale;
		}
		if (empty($options['type'])) $options['type'] = "menu";
		$output = false;


		$rates = $Shopp->Settings->get("taxrates");
		foreach ($rates as $rate) if (is_array($rate['locals']))
			$locales[$rate['country'].$rate['zone']] = array_keys($rate['locals']);

		add_storefrontjs('var locales = '.json_encode($locales).';',true);

		$Taxes = new CartTax();
		$rate = $Taxes->rate(false,true);

	    if(!isset($rate['locals']))
	        foreach ($O->Cart->contents as $Item)
	            if ( ( $rate = $Taxes->rate($Item,true) )
	                && isset($rate['locals']) )
	                break;

		if (!is_array($rate)) return;
		$localities = array_keys($rate['locals']);
		$label = (!empty($options['label']))?$options['label']:'';
		$output = '<select name="billing[locale]" id="billing-locale" '.inputattrs($options,$select_attrs).'>';
	 	$output .= menuoptions($localities,$options['selected']);
		$output .= '</select>';
		return $output;
	}

	function billinglocalities ($result, $options, $O) {
		global $Shopp;
		$rates = $Shopp->Settings->get("taxrates");
		foreach ((array)$rates as $rate) if (isset($rate['locals']) && is_array($rate['locals'])) return true;
		return false;
	}

	function billingpostcode ($result, $options, $O) {
		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return $O->Billing->postcode;
		if (!empty($O->Billing->postcode))
			$options['value'] = $O->Billing->postcode;
		return '<input type="text" name="billing[postcode]" id="billing-postcode" '.inputattrs($options).' />';
	}

	function billingstate ($result, $options, $O) {
		global $Shopp;
		$base = $Shopp->Settings->get('base_operations');
		$countries = $Shopp->Settings->get('target_markets');
		$select_attrs = array('title','required','class','disabled','required','size','tabindex','accesskey');

		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return $O->Billing->state;
		if (!isset($options['selected'])) $options['selected'] = false;
		if (!empty($O->Billing->state)) {
			$options['selected'] = $O->Billing->state;
			$options['value'] = $O->Billing->state;
		}

		$output = false;
		$country = $base['country'];
		if (!empty($O->Billing->country))
			$country = $O->Billing->country;
		if (!array_key_exists($country,$countries)) $country = key($countries);

		$regions = Lookup::country_zones();
		$states = $regions[$country];

		if (isset($options['options']) && empty($states)) $states = explode(",",$options['options']);

		if (isset($options['type']) && $options['type'] == "text")
			return '<input type="text" name="billing[state]" id="billing-state" '.inputattrs($options).'/>';

		$classname = isset($options['class'])?$options['class']:'';
		$label = (!empty($options['label']))?$options['label']:'';
		$options['disabled'] = 'disabled';
		$options['class'] = ($classname?"$classname ":"").'disabled hidden';

		$output .= '<select name="billing[state]" id="billing-state-menu" '.inputattrs($options,$select_attrs).'>';
		$output .= '<option value="">'.$label.'</option>';
		if (is_array($states) && !empty($states)) $output .= menuoptions($states,$options['selected'],true);
		$output .= '</select>';
		unset($options['disabled']);
		$options['class'] = $classname;
		$output .= '<input type="text" name="billing[state]" id="billing-state" '.inputattrs($options).'/>';

		return $output;
	}

	function billingxaddress ($result, $options, $O) {
		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return $O->Billing->xaddress;
		if (!empty($O->Billing->xaddress))
			$options['value'] = $O->Billing->xaddress;
		return '<input type="text" name="billing[xaddress]" id="billing-xaddress" '.inputattrs($options).' />';
	}

	/**
	 * @since 1.0
	 * @deprecated 1.1
	 **/
	function billingxco ($result, $options, $O) { return; }

	function billingxcsc ($result, $options, $O) {
		if (empty($options['input'])) return;
		$input = $options['input'];

		$cards = array();
		$valid = array();
		// Collect valid card inputs for all gateways
		foreach ($O->payoptions as $payoption) {
			foreach ($payoption->cards as $card) {
				$PayCard = Lookup::paycard($card);
				if (empty($PayCard->inputs)) continue;
				$cards[] = $PayCard->symbol;
				foreach ($PayCard->inputs as $field => $size)
					$valid[$field] = $size;
			}
		}

		if (!array_key_exists($input,$valid)) return;

		if (!empty($_POST['billing']['xcsc'][$input]))
			$options['value'] = $_POST['billing']['xcsc'][$input];
		$options['class'] = isset($options['class']) ? $options['class'].' paycard xcsc':'paycard xcsc';

		if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
		$string = '<input type="text" name="billing[xcsc]['.$input.']" id="billing-xcsc-'.$input.'" '.inputattrs($options).' />';
		return $string;
	}

	function billingxcscrequired ($result, $options, $O) {
		global $Shopp;
		$Gateways = $Shopp->Gateways->active;
		foreach ($Gateways as $Gateway) {
			foreach ((array)$Gateway->settings['cards'] as $card) {
				$PayCard = Lookup::paycard($card);
				if (!empty($PayCard->inputs)) return true;
			}
		}
		return false;
	}

	function cardrequired ($result, $options, $O) {
		global $Shopp;
		if ($O->Cart->Totals->total == 0) return false;
		foreach ($Shopp->Gateways->active as $gateway)
			if (!empty($gateway->cards)) return true;
		return false;
	}

	function cartsummary ($result, $options, $O) {
		ob_start();
		include(SHOPP_TEMPLATES."/summary.php");
		$content = ob_get_contents();
		ob_end_clean();

		// If inside the checkout form, strip the extra <form> tag so we don't break standards
		// This is ugly, but necessary given the different markup contexts the cart summary is used in
		$Storefront =& ShoppStorefront();
		if ($Storefront !== false && $Storefront->checkout)
			$content = preg_replace('/<\/?form.*?>/','',$content);

		return $content;
	}

	function company ($result, $options, $O) {
		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return $O->Customer->company;
		if (!empty($O->Customer->company))
			$options['value'] = $O->Customer->company;
		return '<input type="text" name="company" id="company" '.inputattrs($options).' />';
	}

	function completed ($result, $options, $O) {
		global $Shopp;
		if (empty($Shopp->Purchase->id) && $O->purchase !== false) {
			$Shopp->Purchase = new Purchase($O->purchase);
			$Shopp->Purchase->load_purchased();
			return (!empty($Shopp->Purchase->id));
		}
		return false;
	}

	function confirmbutton ($result, $options, $O) {
		$submit_attrs = array('title','class','value','disabled','tabindex','accesskey');

		if (empty($options['errorlabel'])) $options['errorlabel'] = __('Return to Checkout','Shopp');
		if (empty($options['value'])) $options['value'] = __('Confirm Order','Shopp');

		$button = '<input type="submit" name="confirmed" id="confirm-button" '.inputattrs($options,$submit_attrs).' />';
		$return = '<a href="'.shoppurl(false,'checkout',$O->security()).'"'.inputattrs($options,array('class')).'>'.
						$options['errorlabel'].'</a>';

		if (!$O->validated) $markup = $return;
		else $markup = $button;
		return apply_filters('shopp_checkout_confirm_button',$markup,$options,$submit_attrs);
	}

	function confirmpassword ($result, $options, $O) {
		if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
		if (!empty($O->Customer->_confirm_password))
			$options['value'] = $O->Customer->_confirm_password;
		return '<input type="password" name="confirm-password" id="confirm-password" '.inputattrs($options).' />';
	}

	function customerinfo ($result, $options, $O) {
		$select_attrs = array('title','required','class','disabled','required','size','tabindex','accesskey');
		$defaults = array(
			'name' => false, // REQUIRED
			'info' => false,
			'mode' => false,
			'title' => '',
			'type' => 'hidden',
			'value' => '',
			'cols' => '30',
			'rows' => '3',
			'options' => ''
		);
		$op = array_merge($defaults,$options);
		extract($op);

		// Allowed input types
		$allowed_types = array("text","hidden","password","checkbox","radio","textarea","menu");

		// Input types that can override option-specified value with the loaded data value
		$value_override = array("text","hidden","password","textarea","menu");

		/// Allowable attributes for textarea inputs
		$textarea_attrs = array('accesskey','title','tabindex','class','disabled','required');

		if (!$name) { // Iterator for order data
			if (!isset($O->_customer_info_loop)) {
				reset($O->Customer->info->named);
				$O->_customer_info_loop = true;
			} else next($O->Customer->info->named);

			if (current($O->Customer->info->named) !== false) return true;
			else {
				unset($O->_customer_info_loop);
				return false;
			}
		}

		if (isset($O->Customer->info->named[$name])) $info = $O->Customer->info->named[$name];
		if ($name && $mode == "value") return $info;

		if (!in_array($type,$allowed_types)) $type = 'hidden';
		if (empty($title)) $title = $name;
		$id = 'customer-info-'.sanitize_title_with_dashes($name);

		if (in_array($type,$value_override) && !empty($info))
			$value = $info;
		switch (strtolower($type)) {
			case "textarea":
				return '<textarea name="info['.$name.']" cols="'.$cols.'" rows="'.$rows.'" id="'.$id.'" '.inputattrs($op,$textarea_attrs).'>'.$value.'</textarea>';
				break;
			case "menu":
				if (is_string($options)) $options = explode(',',$options);
				return '<select name="info['.$name.']" id="'.$id.'" '.inputattrs($op,$select_attrs).'>'.menuoptions($options,$value).'</select>';
				break;
			default:
				return '<input type="'.$type.'" name="info['.$name.']" id="'.$id.'" '.inputattrs($op).' />';
				break;
		}
	}

	function data ($result, $options, $O) {
		if (!is_array($O->data)) return false;
		$data = current($O->data);
		$name = key($O->data);
		if (isset($options['name'])) return $name;
		return $data;
	}

	function email ($result, $options, $O) {
		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return $O->Customer->email;
		if (!empty($O->Customer->email))
			$options['value'] = $O->Customer->email;
		return '<input type="text" name="email" id="email" '.inputattrs($options).' />';
	}

	function error ($result, $options, $O) {
		$Errors = &ShoppErrors();
		if (!$Errors->exist(SHOPP_COMM_ERR)) return false;
		$errors = $Errors->get(SHOPP_COMM_ERR);
		$defaults = array(
			'before' => '<li>',
			'after' => '</li>'
		);
		$options = array_merge($defaults,$options);
		extract($options);

		$result = "";
		foreach ((array)$errors as $error)
			if (!$error->blank()) $result .= $before.$error->message(true).$after;
		return $result;
	}

	function firstname ($result, $options, $O) {
		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return $O->Customer->firstname;
		if (!empty($O->Customer->firstname))
			$options['value'] = $O->Customer->firstname;
		return '<input type="text" name="firstname" id="firstname" '.inputattrs($options).' />';
	}

	function checkoutfunction ($result, $options, $O) {
		global $Shopp;
		if (!isset($options['shipcalc'])) $options['shipcalc'] = '<img src="'.SHOPP_ADMIN_URI.'/icons/updating.gif" alt="'.__('Updating','Shopp').'" width="16" height="16" />';
		$regions = Lookup::country_zones();
		$base = $Shopp->Settings->get('base_operations');

		$js = "var regions = ".json_encode($regions).",".
							"SHIPCALC_STATUS = '".$options['shipcalc']."',".
							"d_pm = '".sanitize_title_with_dashes($O->paymethod)."',".
							"pm_cards = {};";

		foreach ($O->payoptions as $handle => $option) {
			if (empty($option->cards)) continue;
			$js .= "pm_cards['".$handle."'] = ".json_encode($option->cards).";";
		}
		add_storefrontjs($js,true);

		if (!empty($options['value'])) $value = $options['value'];
		else $value = "process";
		$output = '<div><input type="hidden" name="checkout" value="'.$value.'" /></div>';
		if ($value == "confirmed") $output = apply_filters('shopp_confirm_form',$output);
		else $output = apply_filters('shopp_checkout_form',$output);
		return $output;
	}

	function gatewayinputs ($result, $options, $O) { return apply_filters('shopp_checkout_gateway_inputs',false); }

	function hasdata ($result, $options, $O) { return (is_array($O->data) && count($O->data) > 0); }

	function lastname ($result, $options, $O) {
		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return $O->Customer->lastname;
		if (!empty($O->Customer->lastname))
			$options['value'] = $O->Customer->lastname;
		return '<input type="text" name="lastname" id="lastname" '.inputattrs($options).' />';
	}

	/**
	 * @since 1.0
	 * @deprecated 1.1
	 **/
	function localpayment ($result, $options, $O) { return true; }

	function loggedin ($result, $options, $O) { return $O->Customer->login; }

	function loginname ($result, $options, $O) {
		if (!isset($options['mode'])) $options['mode'] = "input";
		if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
		if ($options['mode'] == "value") return $O->Customer->loginname;
		if (!empty($O->Customer->loginname))
			$options['value'] = $O->Customer->loginname;
		return '<input type="text" name="loginname" id="login" '.inputattrs($options).' />';
	}

	function marketing ($result, $options, $O) {
		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return $O->Customer->marketing;
		if (!empty($O->Customer->marketing))
			$options['value'] = $O->Customer->marketing;
		$attrs = array("accesskey","alt","checked","class","disabled","format",
			"minlength","maxlength","readonly","size","src","tabindex",
			"title");
		$input = '<input type="hidden" name="marketing" value="no" />';
		$input .= '<input type="checkbox" name="marketing" id="marketing" value="yes" '.inputattrs($options,$attrs).' />';
		return $input;
	}

	function notloggedin ($result, $options, $O) { global $Shopp; return (!$O->Customer->login && $Shopp->Settings->get('account_system') != "none"); }

	function orderdata ($result, $options, $O) {
		$select_attrs = array('title','required','class','disabled','required','size','tabindex','accesskey');
		$defaults = array(
			'name' => false, // REQUIRED
			'data' => false,
			'mode' => false,
			'title' => '',
			'type' => 'hidden',
			'value' => '',
			'cols' => '30',
			'rows' => '3',
			'options' => ''
		);
		$op = array_merge($defaults,$options);
		extract($op);

		// Allowed input types
		$allowed_types = array("text","hidden","password","checkbox","radio","textarea","menu");

		// Input types that can override option-specified value with the loaded data value
		$value_override = array("text","hidden","password","textarea","menu");

		/// Allowable attributes for textarea inputs
		$textarea_attrs = array('accesskey','title','tabindex','class','disabled','required');

		if (!$name) { // Iterator for order data
			if (!isset($O->_data_loop)) {
				reset($O->data);
				$O->_data_loop = true;
			} else next($O->data);

			if (current($O->data) !== false) return true;
			else {
				unset($O->_data_loop);
				return false;
			}
		}

		if (isset($O->data[$name])) $data = $O->data[$name];
		if ($name && $mode == "value") return $data;

		if (!in_array($type,$allowed_types)) $type = 'hidden';
		if (empty($title)) $title = $name;
		$id = 'order-data-'.sanitize_title_with_dashes($name);

		if (in_array($type,$value_override) && !empty($data))
			$value = $data;
		switch (strtolower($type)) {
			case "textarea":
				return '<textarea name="data['.$name.']" cols="'.$cols.'" rows="'.$rows.'" id="'.$id.'" '.inputattrs($op,$textarea_attrs).'>'.$value.'</textarea>';
				break;
			case "menu":
				if (is_string($options)) $options = explode(',',$options);
				return '<select name="data['.$name.']" id="'.$id.'" '.inputattrs($op,$select_attrs).'>'.menuoptions($options,$value).'</select>';
				break;
			default:
				return '<input type="'.$type.'" name="data['.$name.']" id="'.$id.'" '.inputattrs($op).' />';
				break;
		}
	}

	function password ($result, $options, $O) {
		if (!isset($options['mode'])) $options['mode'] = "input";
		if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
		if ($options['mode'] == "value")
			return strlen($O->Customer->password) == 34?str_pad('&bull;',8):$O->Customer->password;
		if (!empty($O->Customer->password))
			$options['value'] = $O->Customer->password;
		return '<input type="password" name="password" id="password" '.inputattrs($options).' />';
	}

	function passwordlogin ($result, $options, $O) {
		if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
		if (!empty($_POST['password-login']))
			$options['value'] = $_POST['password-login'];
		return '<input type="password" name="password-login" id="password-login" '.inputattrs($options).' />';
	}

	function payoption ($result, $options, $O) {
		$payoption = current($O->payoptions);
		$defaults = array(
			'labelpos' => 'after',
			'labeling' => false,
			'type' => 'hidden',
		);
		$options = array_merge($defaults,$options);
		extract($options);

		if (value_is_true($return)) return $payoption;

		$types = array('radio','checkbox','hidden');
		if (!in_array($type,$types)) $type = 'hidden';

		if (empty($options['value'])) $options['value'] = key($O->payoptions);

		$_ = array();
		if (value_is_true($labeling))
			$_[] = '<label>';
		if ($labelpos == "before") $_[] = $payoption->label;
		$_[] = '<input type="'.$type.'" name="paymethod"'.inputattrs($options).' />';
		if ($labelpos == "after") $_[] = $payoption->label;
		if (value_is_true($labeling))
			$_[] = '</label>';

		return join("",$_);
	}

	function payoptions ($result, $options, $O) {
		$select_attrs = array('title','required','class','disabled','required','size','tabindex','accesskey');

		if ($O->Cart->orderisfree()) return false;
		$payment_methods = apply_filters('shopp_payment_methods',count($O->payoptions));
		if ($payment_methods <= 1) return false; // Skip if only one gateway is active
		$defaults = array(
			'default' => false,
			'exclude' => false,
			'type' => 'menu',
			'mode' => false
		);
		$options = array_merge($defaults,$options);
		extract($options);
		unset($options['type']);

		if ("loop" == $mode) {
			if (!isset($O->_pay_loop)) {
				reset($O->payoptions);
				$O->_pay_loop = true;
			} else next($O->payoptions);

			if (current($O->payoptions) !== false) return true;
			else {
				unset($O->_pay_loop);
				return false;
			}
			return true;
		}

		$excludes = array_map('sanitize_title_with_dashes',explode(",",$exclude));
		$payoptions = array_keys($O->payoptions);

		$payoptions = array_diff($payoptions,$excludes);
		$paymethod = current($payoptions);

		if ($default !== false && !isset($O->_paymethod_selected)) {
			$default = sanitize_title_with_dashes($default);
			if (in_array($default,$payoptions)) $paymethod = $default;
		}

		if ($O->paymethod != $paymethod) {
			$O->paymethod = $paymethod;
			$processor = $O->payoptions[$O->paymethod]->processor;
			if (!empty($processor)) $O->processor($processor);
		}

		$output = '';
		switch ($type) {
			case "list":
				$output .= '<span><ul>';
				foreach ($payoptions as $value) {
					if (in_array($value,$excludes)) continue;
					$payoption = $O->payoptions[$value];
					$options['value'] = $value;
					$options['checked'] = ($O->paymethod == $value)?'checked':false;
					if ($options['checked'] === false) unset($options['checked']);
					$output .= '<li><label><input type="radio" name="paymethod" '.inputattrs($options).' /> '.$payoption->label.'</label></li>';
				}
				$output .= '</ul></span>';
				break;
			case "hidden":
				if (!isset($options['value']) && $default) $options['value'] = $O->paymethod;
				$output .= '<input type="hidden" name="paymethod"'.inputattrs($options).' />';
				break;
			default:
				$output .= '<select name="paymethod" '.inputattrs($options,$select_attrs).'>';
				foreach ($payoptions as $value) {
					if (in_array($value,$excludes)) continue;
					$payoption = $O->payoptions[$value];
					$selected = ($O->paymethod == $value)?' selected="selected"':'';
					$output .= '<option value="'.$value.'"'.$selected.'>'.$payoption->label.'</option>';
				}
				$output .= '</select>';
				break;
		}

		return $output;
	}

	function phone ($result, $options, $O) {
		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return $O->Customer->phone;
		if (!empty($O->Customer->phone))
			$options['value'] = $O->Customer->phone;
		return '<input type="text" name="phone" id="phone" '.inputattrs($options).' />';
	}

	function receipt ($result, $options, $O) { global $Shopp; if (!empty($Shopp->Purchase->id)) return $Shopp->Purchase->receipt(); }

	function residentialshippingaddress ($result, $options, $O) {
		$label = __("Residential shipping address","Shopp");
		if (isset($options['label'])) $label = $options['label'];
		if (isset($options['checked']) && value_is_true($options['checked'])) $checked = ' checked="checked"';
		$output = '<label for="residential-shipping"><input type="hidden" name="shipping[residential]" value="no" /><input type="checkbox" name="shipping[residential]" value="yes" id="residential-shipping" '.$checked.' /> '.$label.'</label>';
		return $output;
	}

	function sameshippingaddress ($result, $options, $O) {
		$label = __("Same shipping address","Shopp");
		if (isset($options['label'])) $label = $options['label'];
		$checked = ' checked="checked"';
		if (isset($options['checked']) && !value_is_true($options['checked'])) $checked = '';
		$output = '<label for="same-shipping"><input type="checkbox" name="sameshipaddress" value="on" id="same-shipping" '.$checked.' /> '.$label.'</label>';
		return $output;
	}

	function shipping ($result, $options, $O) { return (!empty($O->shipped)); }

	function shippingaddress ($result, $options, $O) {
		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return $O->Shipping->address;
		if (!empty($O->Shipping->address))
			$options['value'] = $O->Shipping->address;
		return '<input type="text" name="shipping[address]" id="shipping-address" '.inputattrs($options).' />';
	}

	function shippingcity ($result, $options, $O) {
		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return $O->Shipping->city;
		if (!empty($O->Shipping->city))
			$options['value'] = $O->Shipping->city;
		return '<input type="text" name="shipping[city]" id="shipping-city" '.inputattrs($options).' />';
	}

	function shippingcountry ($result, $options, $O) {
		global $Shopp;
		$base = $Shopp->Settings->get('base_operations');
		$countries = $Shopp->Settings->get('target_markets');
		$select_attrs = array('title','required','class','disabled','required','size','tabindex','accesskey');

		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return $O->Shipping->country;
		if (!empty($O->Shipping->country))
			$options['selected'] = $O->Shipping->country;
		else if (empty($options['selected'])) $options['selected'] = $base['country'];
		$output = '<select name="shipping[country]" id="shipping-country" '.inputattrs($options,$select_attrs).'>';
	 	$output .= menuoptions($countries,$options['selected'],true);
		$output .= '</select>';
		return $output;
	}

	function shippingpostcode ($result, $options, $O) {
		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return $O->Shipping->postcode;
		if (!empty($O->Shipping->postcode))
			$options['value'] = $O->Shipping->postcode;
		return '<input type="text" name="shipping[postcode]" id="shipping-postcode" '.inputattrs($options).' />';
	}

	function shippingstate ($result, $options, $O) {
		global $Shopp;
		$base = $Shopp->Settings->get('base_operations');
		$countries = $Shopp->Settings->get('target_markets');
		$select_attrs = array('title','required','class','disabled','required','size','tabindex','accesskey');

		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return $O->Shipping->state;
		if (!isset($options['selected'])) $options['selected'] = false;
		if (!empty($O->Shipping->state)) {
			$options['selected'] = $O->Shipping->state;
			$options['value'] = $O->Shipping->state;
		}

		$output = false;
		$country = $base['country'];
		if (!empty($O->Shipping->country))
			$country = $O->Shipping->country;
		if (!array_key_exists($country,$countries)) $country = key($countries);

		$regions = Lookup::country_zones();
		$states = $regions[$country];

		if (isset($options['options']) && empty($states)) $states = explode(",",$options['options']);

		if (isset($options['type']) && $options['type'] == "text")
			return '<input type="text" name="shipping[state]" id="shipping-state" '.inputattrs($options).'/>';

		$classname = isset($options['class'])?$options['class']:'';
		$label = (!empty($options['label']))?$options['label']:'';
		$options['disabled'] = 'disabled';
		$options['class'] = ($classname?"$classname ":"").'disabled hidden';

		$output .= '<select name="shipping[state]" id="shipping-state-menu" '.inputattrs($options,$select_attrs).'>';
		$output .= '<option value="">'.$label.'</option>';
		if (is_array($states) && !empty($states)) $output .= menuoptions($states,$options['selected'],true);
		$output .= '</select>';
		unset($options['disabled']);
		$options['class'] = $classname;
		$output .= '<input type="text" name="shipping[state]" id="shipping-state" '.inputattrs($options).'/>';

		return $output;
	}

	function shippingxaddress ($result, $options, $O) {
		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return $O->Shipping->xaddress;
		if (!empty($O->Shipping->xaddress))
			$options['value'] = $O->Shipping->xaddress;
		return '<input type="text" name="shipping[xaddress]" id="shipping-xaddress" '.inputattrs($options).' />';
	}

	function submit ($result, $options, $O) {
		$submit_attrs = array('title','class','value','disabled','tabindex','accesskey');

		if (!isset($options['value'])) $options['value'] = __('Submit Order','Shopp');
		$options['class'] = isset($options['class'])?$options['class'].' checkout-button':'checkout-button';

		$wrapclass = '';
		if (isset($options['wrapclass'])) $wrapclass = ' '.$options['wrapclass'];

		$buttons = array('<input type="submit" name="process" id="checkout-button" '.inputattrs($options,$submit_attrs).' />');

		if (!$O->Cart->orderisfree())
			$buttons = apply_filters('shopp_checkout_submit_button',$buttons,$options,$submit_attrs);

		$_ = array();
		foreach ($buttons as $label => $button)
			$_[] = '<span class="payoption-button payoption-'.sanitize_title_with_dashes($label).($label === 0?$wrapclass:'').'">'.$button.'</span>';

		return join("\n",$_);
	}

	function submitlogin ($result, $options, $O) {
		$string = '<input type="hidden" name="process-login" id="process-login" value="false" />';
		$string .= '<input type="submit" name="submit-login" id="submit-login" '.inputattrs($options).' />';
		return $string;
	}

	function url ($result, $options, $O) {
		$process = get_query_var('s_pr');
		$link = shoppurl(false,'checkout',$O->security());

		// Pass any arguments along
		$args = $_GET;
		unset($args['page_id'],$args['acct']);
		$link = esc_url(add_query_arg($args,$link));
		if ($process == "confirm-order") $link = apply_filters('shopp_confirm_url',$link);
		else $link = apply_filters('shopp_checkout_url',$link);
		return $link;
	}

	/**
	 * @since 1.0
	 * @deprecated 1.1
	 **/
	function xcobuttons ($result, $options, $O) { return; }

}

?>