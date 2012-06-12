<?php
/**
* ShoppCheckoutThemeAPI - Provided theme api tags.
*
* @version 1.0
* @since 1.2
* @package shopp
* @subpackage ShoppCheckoutThemeAPI
*
**/

// Default text filters for checkout Theme API tags
add_filter('shopp_checkout_clickwrap_terms', 'wptexturize');
add_filter('shopp_checkout_clickwrap_terms', 'convert_chars');
add_filter('shopp_checkout_clickwrap_terms', 'wpautop');
add_filter('shopp_checkout_clickwrap_terms', 'do_shortcode', 11); // AFTER wpautop()

/**
 * Provides shopp('checkout') theme API functionality
 *
 * @author Jonathan Davis, John Dillick
 * @since 1.2
 **/
class ShoppCheckoutThemeAPI implements ShoppAPI {
	static $register = array(
		'billingaddress' => 'billing_address',
		'billingcard' => 'billing_card',
		'billingcardexpiresmm' => 'billing_card_expires_mm',
		'billingcardexpiresyy' => 'billing_card_expires_yy',
		'billingcardholder' => 'billing_card_holder',
		'billingcardtype' => 'billing_card_type',
		'billingcity' => 'billing_city',
		'billingcountry' => 'billing_country',
		'billingcvv' => 'billing_cvv',
		'billinglocale' => 'billing_locale',
		'billinglocalities' => 'billing_localities',
		'billingname' => 'billing_name',
		'billingpostcode' => 'billing_postcode',
		'billingprovince' => 'billing_state',
		'billingstate' => 'billing_state',
		'billingrequired' => 'card_required',
		'cardrequired' => 'card_required',
		'billingxaddress' => 'billing_xaddress',
		'billingxco' => 'billing_xco',
		'billingxcsc' => 'billing_xcsc',
		'billingxcscrequired' => 'billing_xcsc_required',
		'cartsummary' => 'cart_summary',
		'clickwrap' => 'clickwrap',
		'completed' => 'completed',
		'confirmbutton' => 'confirm_button',
		'confirmpassword' => 'confirm_password',
		'customerinfo' => 'customer_info',
		'data' => 'data',
		'email' => 'email',
		'emaillogin' => 'account_login',
		'loginnamelogin' => 'account_login',
		'accountlogin' => 'account_login',
		'errors' => 'error',
		'error' => 'error',
		'firstname' => 'first_name',
		'function' => 'checkout_function',
		'gatewayinputs' => 'gateway_inputs',
		'guest' => 'guest',
		'hasdata' => 'has_data',
		'lastname' => 'last_name',
		'localpayment' => 'local_payment',
		'loggedin' => 'logged_in',
		'loginname' => 'login_name',
		'marketing' => 'marketing',
		'notloggedin' => 'not_logged_in',
		'orderdata' => 'order_data',
		'organization' => 'company',
		'company' => 'company',
		'password' => 'password',
		'passwordlogin' => 'password_login',
		'payoption' => 'payoption',
		'paymentoption' => 'payoption',
		'payoptions' => 'payoptions',
		'paymentoptions' => 'payoptions',
		'phone' => 'phone',
		'receipt' => 'receipt',
		'residentialshippingaddress' => 'residential_shipping_address',
		'samebillingaddress' => 'same_billing_address',
		'sameshippingaddress' => 'same_shipping_address',
		'shipping' => 'shipping',
		'shippingaddress' => 'shipping_address',
		'shippingcity' => 'shipping_city',
		'shippingcountry' => 'shipping_country',
		'shippingname' => 'shipping_name',
		'shippingpostcode' => 'shipping_postcode',
		'shippingprovince' => 'shipping_state',
		'shippingstate' => 'shipping_state',
		'shippingxaddress' => 'shipping_xaddress',
		'submit' => 'submit',
		'submitlogin' => 'submit_login',
		'loginbutton' => 'submit_login',
		'url' => 'url',
		'xcobuttons' => 'xco_buttons'
	);

	static function _apicontext () { return 'checkout'; }

	/**
	 * _setobject - returns the global context object used in the shopp('checkout) call
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 **/
	static function _setobject ($Object, $object) {
		if ( is_object($Object) && is_a($Object, 'Order') && 'checkout' == strtolower($object) ) return $Object;
		else if ( strtolower($object) != 'checkout' ) return $Object; // not mine, do nothing

		return ShoppOrder();
	}

	static function account_login ($result, $options, $O) {
		if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
		if (!empty($_POST['account-login']))
			$options['value'] = $_POST['account-login'];
		return '<input type="text" name="account-login" id="account-login"'.inputattrs($options).' />';
	}

	static function billing_name ($result, $options, $O) {
		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return $O->Billing->name;
		if (!empty($O->Billing->name))
			$options['value'] = $O->Billing->name;
		return '<input type="text" name="billing[name]" id="billing-name" '.inputattrs($options).' />';
	}

	static function billing_address ($result, $options, $O) {
		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return $O->Billing->address;
		if (!empty($O->Billing->address))
			$options['value'] = $O->Billing->address;
		return '<input type="text" name="billing[address]" id="billing-address" '.inputattrs($options).' />';
	}

	static function billing_card ($result, $options, $O) {
		if (!isset($options['mode'])) $options['mode'] = "input";
		if (!empty($O->Billing->card) && strlen($O->Billing->card) > 4)
			$options['value'] = str_repeat('X',strlen($O->Billing->card)-4).substr($O->Billing->card,-4);
		if ($options['mode'] == "value") return $options['value'];
		$options['class'] = isset($options['class']) ? $options['class'].' paycard':'paycard';
		if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
		return '<input type="text" name="billing[card]" id="billing-card" '.inputattrs($options).' />';
	}

	static function billing_card_expires_mm ($result, $options, $O) {
		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return date("m",$O->Billing->cardexpires);
		$options['class'] = isset($options['class']) ? $options['class'].' paycard':'paycard';
		if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
		if (!empty($O->Billing->cardexpires))
			$options['value'] = date("m",$O->Billing->cardexpires);
		return '<input type="text" name="billing[cardexpires-mm]" id="billing-cardexpires-mm" '.inputattrs($options).' />';
	}

	static function billing_card_expires_yy ($result, $options, $O) {
		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return date("y",$O->Billing->cardexpires);
		$options['class'] = isset($options['class']) ? $options['class'].' paycard':'paycard';
		if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
		if (!empty($O->Billing->cardexpires))
			$options['value'] = date("y",$O->Billing->cardexpires);
		return '<input type="text" name="billing[cardexpires-yy]" id="billing-cardexpires-yy" '.inputattrs($options).' />';
	}

	static function billing_card_holder ($result, $options, $O) {
		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return $O->Billing->cardholder;
		$options['class'] = isset($options['class']) ? $options['class'].' paycard':'paycard';
		if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
		if (!empty($O->Billing->cardholder))
			$options['value'] = $O->Billing->cardholder;
		return '<input type="text" name="billing[cardholder]" id="billing-cardholder" '.inputattrs($options).' />';
	}

	static function billing_card_type ($result, $options, $O) {
		$select_attrs = array('title','required','class','disabled','required','size','tabindex','accesskey');

		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return $O->Billing->cardtype;
		$options['class'] = isset($options['class']) ? $options['class'].' paycard':'paycard';
		if (!isset($options['selected'])) $options['selected'] = false;
		if (!empty($O->Billing->cardtype))
			$options['selected'] = $O->Billing->cardtype;

		$cards = array();
		foreach ($O->paycards as $paycard) {
			// Convert full card type names to card type symbols
			if ($options['selected'] == $paycard->name) $options['selected'] = $paycard->symbol;
			$cards[$paycard->symbol] = $paycard->name;
		}

		$label = (!empty($options['label']))?$options['label']:'';
		$output = '<select name="billing[cardtype]" id="billing-cardtype" '.inputattrs($options,$select_attrs).'>';
		$output .= '<option value="">'.$label.'</option>';
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

	static function billing_city ($result, $options, $O) {
		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return $O->Billing->city;
		if (!empty($O->Billing->city))
			$options['value'] = $O->Billing->city;
		return '<input type="text" name="billing[city]" id="billing-city" '.inputattrs($options).' />';
	}

	static function billing_country ($result, $options, $O) {
		global $Shopp;

		$base = shopp_setting('base_operations');
		$countries = shopp_setting('target_markets');
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

	static function billing_cvv ($result, $options, $O) {
		if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
		if (!empty($_POST['billing']['cvv']))
			$options['value'] = $_POST['billing']['cvv'];
		$options['class'] = isset($options['class']) ? $options['class'].' paycard':'paycard';
		return '<input type="text" name="billing[cvv]" id="billing-cvv" '.inputattrs($options).' />';
	}

	static function billing_locale ($result, $options, $O) {
		global $Shopp;

		$select_attrs = array('title','required','class','disabled','required','size','tabindex','accesskey');
		$output = false;

		if ( "value" == $options['mode'] ) { return $O->Billing->locale; }

		if ( ! isset($options['selected']) ) {
			$options['selected'] = $O->Billing->locale ? $O->Billing->locale : false;
		}

		$rates = shopp_setting('taxrates');
		foreach ( $rates as $rate ) { // @todo - what if more than one set of local rates applies to current country/zone? ie. conditions
			if ( isset( $rate['locals'] ) ) {
				$locales[$rate['country'].$rate['zone']] = array_keys($rate['locals']);
			}
		}

		// if there are no local tax jurisdictions in settings
		if ( ! empty($locales) ) {
			// Add all the locales to the javascript environment
			add_storefrontjs('var locales = '.json_encode($locales).';',true);

			$Taxes = new CartTax();

			// Check for local rates applying to current country/zone
			$setting = true; // return the whole rate setting, not just the percentage
			$Item = false; // Item to pass to tax rate lookup
			$rate = $Taxes->rate($Item,$setting);

			// If the current country.state combination doesn't match any of the local jurisdictions,
			// check for local jurisdiction rate setting that has a product-specific condition.
			if( ! isset($rate['locals']) ) {
				foreach ( $O->Cart->contents as $Item ) {
					if ( ( $rate = $Taxes->rate($Item,$setting) ) && isset($rate['locals']) ) {
						break;
					}
				}
			}

			// names of local tax jurisdictions that apply to current country.zone
			$localities = array();
			if ( isset($rate['locals']) ) {
				$localities = array_keys($rate['locals']);
			}

			// Make this a required field
			$options['class'] .= ( isset($options['class']) ? ", " : "" . "required" );

			// disable this field automatically if no local jurisdictions apply to current country.zone
			if ( empty($localities) ) $options['disabled'] = 'disabled';

			// Start stub select menu for billing local tax jurisdiction (needed for javascript to populate)
			$output = '<select name="billing[locale]" id="billing-locale" '.inputattrs($options,$select_attrs).'>';

		 	if ( ! empty($localities) ) { $output .= "<option></option>".menuoptions($localities, $options['selected']); }

			// End stub select menu for billing local tax jurisdiction
			$output .= '</select>';
		}

		return $output;

	} // end function billing_locale

	static function billing_localities ($result, $options, $O) {
		$rates = shopp_setting("taxrates");
		foreach ((array)$rates as $rate) if (isset($rate['locals']) && is_array($rate['locals'])) return true;
		return false;
	}

	static function billing_postcode ($result, $options, $O) {
		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return $O->Billing->postcode;
		if (!empty($O->Billing->postcode))
			$options['value'] = $O->Billing->postcode;
		return '<input type="text" name="billing[postcode]" id="billing-postcode" '.inputattrs($options).' />';
	}

	static function billing_state ($result, $options, $O) {
		global $Shopp;
		$base = shopp_setting('base_operations');
		$countries = shopp_setting('target_markets');
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

		$classname = ( isset($options['class']) ? $options['class'].' ' : '' ).'billing-state';

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

	static function billing_xaddress ($result, $options, $O) {
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
	static function billing_xco ($result, $options, $O) { return; }

	static function billing_xcsc ($result, $options, $O) {
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

	static function billing_xcsc_required ($result, $options, $O) {
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

	static function card_required ($result, $options, $O) {
		global $Shopp;
		if ($O->Cart->Totals->total == 0) return false;
		foreach ($Shopp->Gateways->active as $gateway)
			if (!empty($gateway->cards)) return true;
		return false;
	}

	static function cart_summary ($result, $options, $O) {
		ob_start();
		locate_shopp_template(array('summary.php'),true);
		$content = ob_get_contents();
		ob_end_clean();

		// If inside the checkout form, strip the extra <form> tag so we don't break standards
		// This is ugly, but necessary given the different markup contexts the cart summary is used in
		$Storefront =& ShoppStorefront();
		if ($Storefront !== false && $Storefront->checkout)
			$content = preg_replace('/<\/?form.*?>/','',$content);

		return $content;
	}

	static function company ($result, $options, $O) {
		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return $O->Customer->company;
		if (!empty($O->Customer->company))
			$options['value'] = $O->Customer->company;
		return '<input type="text" name="company" id="company" '.inputattrs($options).' />';
	}

	static function completed ($result, $options, $O) {
		if ( $O->purchase === false ) return false;
		if ( ! ShoppPurchase() || empty(ShoppPurchase()->id) ) {
			ShoppPurchase(new Purchase($O->purchase));
			ShoppPurchase()->load_purchased();
		}
		return (!empty(ShoppPurchase()->id));
	}

	static function confirm_button ($result, $options, $O) {
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

	static function confirm_password ($result, $options, $O) {
		if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
		if (!empty($O->Customer->_confirm_password))
			$options['value'] = $O->Customer->_confirm_password;
		return '<input type="password" name="confirm-password" id="confirm-password" '.inputattrs($options).' />';
	}

	static function customer_info ($result, $options, $O) {
		$select_attrs = array('title','required','class','disabled','required','size','tabindex','accesskey');
		$defaults = array(
			'name' => false, // REQUIRED
			'info' => false,
			'mode' => false,
			'title' => '',
			'type' => 'hidden',
			'value' => '',
			'options' => ''
		);
		if ('textarea' == $defaults['type']) {
			$defaults['cols'] = '30';
			$defaults['rows'] = '3';
		}
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

		if (is_array($O->Customer->info) && isset($O->Customer->info[$name])) $info = $O->Customer->info[$name];
		if ($name && $mode == "value") return $info;
		if (empty($value) && !empty($info)) $op['value'] = $info;

		if (!in_array($type,$allowed_types)) $type = 'hidden';
		if (empty($title)) $op['title'] = $name;
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

	static function data ($result, $options, $O) {
		if (!is_array($O->data)) return false;
		$data = current($O->data);
		$name = key($O->data);
		if (isset($options['name'])) return $name;
		return $data;
	}

	static function email ($result, $options, $O) {
		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return $O->Customer->email;
		if (!empty($O->Customer->email))
			$options['value'] = $O->Customer->email;
		return '<input type="text" name="email" id="email" '.inputattrs($options).' />';
	}

	static function error ($result, $options, $O) {
		return ShoppCatalogThemeAPI::errors($result,$options,$O);
	}

	static function first_name ($result, $options, $O) {
		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return $O->Customer->firstname;
		if (!empty($O->Customer->firstname))
			$options['value'] = $O->Customer->firstname;
		return '<input type="text" name="firstname" id="firstname" '.inputattrs($options).' />';
	}

	static function checkout_function ($result, $options, $O) {
		$defaults = array(
			'updating' => '<img src="'.SHOPP_ADMIN_URI.'/icons/updating.gif" alt="'.__('Updating','Shopp').'" width="16" height="16" />'
		);
		$options = array_merge($defaults,$options);
		extract($options);
		$regions = Lookup::country_zones();
		$base = shopp_setting('base_operations');

		$js = "var regions=".json_encode($regions).",".
				  "c_upd='".$updating."',".
				  "d_pm='".sanitize_title_with_dashes($O->paymethod)."',".
				  "pm_cards={};";

		foreach ($O->payoptions as $handle => $option) {
			if (empty($option->cards)) continue;
			$js .= "pm_cards['".$handle."'] = ".json_encode($option->cards).";";
		}
		add_storefrontjs($js,true);

		if (!empty($options['value'])) $value = $options['value'];
		else $value = 'process';
		$output = '
<script type="text/javascript">
//<![CDATA[
	document.body.className += \' js-on\';
//]]>
</script>
<div><input id="shopp-checkout-function" type="hidden" name="checkout" value="'.$value.'" /></div>
		';

		if ($value == "confirmed") $output = apply_filters('shopp_confirm_form',$output);
		else $output = apply_filters('shopp_checkout_form',$output);
		return $output;
	}

	static function gateway_inputs ($result, $options, $O) { return apply_filters('shopp_checkout_gateway_inputs',false); }

	static function guest ($result, $options, $O) {
		$allowed = array('class','checked','title');
		$defaults = array(
			'label' => __('Checkout as a guest','Shopp'),
			'checked' => 'off'
		);
		$options = array_merge($defaults,$options);
		extract($options);

		if ( str_true($O->guest) ||str_true($checked) )
			$options['checked'] = 'on';

		$_ = array();
		if (!empty($label))
			$_[] = '<label for="guest-checkout">';
		$_[] = '<input type="hidden" name="guest" value="no" />';
		$_[] = '<input type="checkbox" name="guest" value="yes" id="guest-checkout"'.inputattrs($options,$allowed).' />';
		if (!empty($label))
			$_[] = "&nbsp;$label</label>";

		return join('',$_);
	}

	static function has_data ($result, $options, $O) { return (is_array($O->data) && count($O->data) > 0); }

	static function last_name ($result, $options, $O) {
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
	static function local_payment ($result, $options, $O) { return true; }

	static function logged_in ($result, $options, $O) { return $O->Customer->logged_in(); }

	static function login_name ($result, $options, $O) {
		if (!isset($options['mode'])) $options['mode'] = "input";
		if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
		if ($options['mode'] == "value") return $O->Customer->loginname;
		if (!empty($O->Customer->loginname))
			$options['value'] = $O->Customer->loginname;
		return '<input type="text" name="loginname" id="login" '.inputattrs($options).' />';
	}

	static function marketing ($result, $options, $O) {
		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return $O->Customer->marketing;
		if (!empty($O->Customer->marketing))
			$options['value'] = $O->Customer->marketing;
		$attrs = array("accesskey","alt","checked","class","disabled","format",
			"minlength","maxlength","readonly","size","src","tabindex",
			"title");
		if (str_true($options['value'])) $options['checked'] = 'checked';
		$input = '<input type="hidden" name="marketing" value="no" />';
		$input .= '<input type="checkbox" name="marketing" id="marketing" value="yes" '.inputattrs($options,$attrs).' />';
		return $input;
	}

	static function clickwrap ($result, $options, $O) {
		$modes = array('input','value');
		$name = 'clickwrap';
		$value = isset($O->data[$name]) ? $O->data[$name] : false;
		$defaults = array(
			'mode' => 'input',
			'terms' => false,
			'termsclass' => false,
			'class' => 'required',
			'value' => $value
		);
		$options = array_merge($defaults,$options);
		extract($options);
		$frame = false;

		if (!in_array($mode,$modes)) $mode = $modes[0];

		if ('value' == $mode) return $value;

		$attrs = array('accesskey','alt','checked','class','disabled','format',
			'minlength','maxlength','readonly','size','src','tabindex',
			'title');

		if ('agreed' == $value) $options['checked'] = 'checked';

		if (false !== $agreement) {
			$page = get_page_by_path($agreement);
			$frame = '<div class="scrollable clickwrap clickwrap-terms'.( $termsclass ? " $termsclass" : "" ).'">'.apply_filters('shopp_checkout_clickwrap_terms',$page->post_content).'</div>';
		}
		$input = '<input type="hidden" name="data[clickwrap]" value="no" /><input type="checkbox" name="data[clickwrap]" id="clickwrap" value="agreed" '.inputattrs($options,$attrs).' />';
		return $frame.$input;
	}


	static function not_logged_in ($result, $options, $O) { return (!$O->Customer->logged_in() && shopp_setting('account_system') != "none"); }

	static function order_data ($result, $options, $O) {
		$select_attrs = array('title','required','class','disabled','required','size','tabindex','accesskey');
		$defaults = array(
			'name' => false, // REQUIRED
			'data' => false,
			'mode' => false,
			'title' => '',
			'type' => 'hidden',
			'value' => '',
			'options' => ''
		);
		if ('textarea' == $defaults['type']) {
			$defaults['cols'] = '30';
			$defaults['rows'] = '3';
		}
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
			$op['value'] = $value = $data;

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

	static function password ($result, $options, $O) {
		if (!isset($options['mode'])) $options['mode'] = "input";
		if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
		if ($options['mode'] == "value")
			return strlen($O->Customer->password) == 34?str_pad('&bull;',8):$O->Customer->password;
		if (!empty($O->Customer->password))
			$options['value'] = $O->Customer->password;
		return '<input type="password" name="password" id="password" '.inputattrs($options).' />';
	}

	static function password_login ($result, $options, $O) {
		if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
		if (!empty($_POST['password-login']))
			$options['value'] = $_POST['password-login'];
		return '<input type="password" name="password-login" id="password-login" '.inputattrs($options).' />';
	}

	static function payoption ($result, $options, $O) {
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
		if (str_true($labeling)) {
			$_[] = '<label class="'.esc_attr($options['value']).'">';
			if ($labelpos == "before") $_[] = $payoption->label;
		}
		$_[] = '<input type="'.$type.'" name="paymethod" id="paymethod-'.esc_attr($options['value']).'"'.inputattrs($options).' />';
		if (str_true($labeling)) {
			if ($labelpos == "after") $_[] = $payoption->label;
			$_[] = '</label>';
		}

		return join("",$_);
	}

	static function payoptions ($result, $options, $O) {
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

		if ('loop' == $mode) {
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

		if ( ( ! isset($O->_paymethod_selected) || ! $O->_paymethod_selected ) && $O->paymethod != $paymethod ) {
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
					$options['checked'] = ($O->paymethod == $value);
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

	static function phone ($result, $options, $O) {
		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return $O->Customer->phone;
		if (!empty($O->Customer->phone))
			$options['value'] = $O->Customer->phone;
		return '<input type="text" name="phone" id="phone" '.inputattrs($options).' />';
	}

	static function receipt ($result, $options, $O) { global $Shopp; if (!empty($Shopp->Purchase->id)) return $Shopp->Purchase->receipt(); }

	static function residential_shipping_address ($result, $options, $O) {
		$allowed = array('class','checked','title');
		$defaults = array(
			'label' => __('Residential shipping address','Shopp'),
			'checked' => 'on'
		);
		$options = array_merge($defaults,$options);
		extract($options);

		if ( ( isset($O->Shipping->residential) && ! str_true($O->Shipping->residential) ) || ! str_true($checked) )
			$options['checked'] = 'off';

		$_ = array();
		$_[] = '<label for="residential-shipping">';
		$_[] = '<input type="hidden" name="shipping[residential]" value="no" />';
		$_[] = '<input type="checkbox" name="shipping[residential]" value="yes" id="residential-shipping"'.inputattrs($options,$allowed).' />';
		$_[] = "&nbsp;$label</label>";

		return join('',$_);
	}

	static function same_billing_address ($result, $options, $O) {
		$allowed = array('class','checked');
		$defaults = array(
			'label' => __('Same billing address','Shopp'),
			'checked' => 'on',
			'type' => 'billing',
			'class' => ''
		);
		$options = array_merge($defaults,$options);
		$options['type'] = 'billing';
		return self::same_shipping_address($result,$options,$O);
	}

	static function same_shipping_address ($result, $options, $O) {
		$allowed = array('class','checked');
		$defaults = array(
			'label' => __('Same shipping address','Shopp'),
			'checked' => 'on',
			'type' => 'shipping',
			'class' => ''
		);
		$options = array_merge($defaults,$options);
		extract($options);

		// Doing it wrong
		if ( 'shipping' == $type && 'billing' == $O->sameaddress ) return '';
		if ( 'billing' == $type && 'shipping' == $O->sameaddress ) return '';

		// Order->sameaddress defaults to false
		if ( $O->sameaddress ) {
			if ( 'off' == $O->sameaddress ) $options['checked'] = 'off';
			if ( $O->sameaddress == $type ) $options['checked'] = 'on';
		}

		$options['class'] = trim($options['class'].' sameaddress '.$type);
		$id = "same-address-$type";

		$_ = array();
		$_[] = '<label for="'.$id.'">';
		$_[] = '<input type="hidden" name="sameaddress" value="off" />';
		$_[] = '<input type="checkbox" name="sameaddress" value="'.$type.'" id="'.$id.'" '.inputattrs($options,$allowed).' />';
		$_[] = "&nbsp;$label</label>";

		return join('',$_);
	}

	static function shipping ($result, $options, $O) { return (!empty($O->shipped)); }

	static function shipping_name ($result, $options, $O) {
		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return $O->Shipping->name;
		if (!empty($O->Shipping->name))
			$options['value'] = $O->Shipping->name;
		return '<input type="text" name="shipping[name]" id="shipping-name" '.inputattrs($options).' />';
	}

	static function shipping_address ($result, $options, $O) {
		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return $O->Shipping->address;
		if (!empty($O->Shipping->address))
			$options['value'] = $O->Shipping->address;
		return '<input type="text" name="shipping[address]" id="shipping-address" '.inputattrs($options).' />';
	}

	static function shipping_city ($result, $options, $O) {
		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return $O->Shipping->city;
		if (!empty($O->Shipping->city))
			$options['value'] = $O->Shipping->city;
		return '<input type="text" name="shipping[city]" id="shipping-city" '.inputattrs($options).' />';
	}

	static function shipping_country ($result, $options, $O) {
		$base = shopp_setting('base_operations');
		$countries = shopp_setting('target_markets');
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

	static function shipping_postcode ($result, $options, $O) {
		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return $O->Shipping->postcode;
		if (!empty($O->Shipping->postcode))
			$options['value'] = $O->Shipping->postcode;
		return '<input type="text" name="shipping[postcode]" id="shipping-postcode" '.inputattrs($options).' />';
	}

	static function shipping_state ($result, $options, $O) {
		global $Shopp;

		$base = shopp_setting('base_operations');
		$countries = shopp_setting('target_markets');
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

		$classname = "shipping-state".(isset($options['class']) ? ' '.$options['class'] : '');

		$regions = Lookup::country_zones();
		$states = $regions[$country];

		if (isset($options['options']) && empty($states)) $states = explode(",",$options['options']);

		if (isset($options['type']) && $options['type'] == "text")
			return '<input type="text" name="shipping[state]" id="shipping-state" '.inputattrs($options).'/>';

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

	static function shipping_xaddress ($result, $options, $O) {
		if (!isset($options['mode'])) $options['mode'] = "input";
		if ($options['mode'] == "value") return $O->Shipping->xaddress;
		if (!empty($O->Shipping->xaddress))
			$options['value'] = $O->Shipping->xaddress;
		return '<input type="text" name="shipping[xaddress]" id="shipping-xaddress" '.inputattrs($options).' />';
	}

	static function submit ($result, $options, $O) {
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

	static function submit_login ($result, $options, $O) {
		$string = '<input type="submit" name="submit-login" id="submit-login" '.inputattrs($options).' />';
		return $string;
	}

	static function url ($result, $options, $O) {
		$link = shoppurl(false,'checkout',$O->security());
		$Storefront = ShoppStorefront();

		// Pass any arguments along
		$args = $_GET;
		unset($args['shopp_page'],$args['acct']);
		$link = esc_url(add_query_arg($args,$link));

		if (isset($Storefront->_confirm_page_content)) $link = apply_filters('shopp_confirm_url',$link);
		else $link = apply_filters('shopp_checkout_url',$link);
		return $link;
	}

}

?>