<?php
/**
* ShoppCustomerThemeAPI - Provided theme api tags.
*
* @version 1.0
* @since 1.2
* @package shopp
* @subpackage ShoppCustomerThemeAPI
*
**/

class ShoppCustomerThemeAPI implements ShoppAPI {
	static $register = array(
		'accounts' => 'accounts',
		'accounturl' => 'account_url',
		'action' => 'action',
		'billingaddress' => 'billing_address',
		'billingcity' => 'billing_city',
		'billingcountry' => 'billing_country',
		'billingpostcode' => 'billing_postcode',
		'billingprovince' => 'billing_state',
		'billingstate' => 'billing_state',
		'billingxaddress' => 'billing_xaddress',
		'company' => 'company',
		'confirmpassword' => 'confirm_password',
		'download' => 'download',
		'downloads' => 'downloads',
		'email' => 'email',
		'emaillogin' => 'account_login',
		'loginnamelogin' => 'account_login',
		'accountlogin' => 'account_login',
		'errorsexist' => 'errors_exist',
		'firstname' => 'first_name',
		'hasaccount' => 'has_account',
		'hasdownloads' => 'has_downloads',
		'hasinfo' => 'has_info',
		'haspurchases' => 'has_purchases',
		'info' => 'info',
		'lastname' => 'last_name',
		'loggedin' => 'logged_in',
		'loginerrors' => 'errors',
		'loginlabel' => 'login_label',
		'loginname' => 'login_name',
		'management' => 'management',
		'marketing' => 'marketing',
		'menu' => 'menu',
		'notloggedin' => 'not_logged_in',
		'orderlookup' => 'order_lookup',
		'password' => 'password',
		'passwordchanged' => 'password_changed',
		'passwordlogin' => 'password_login',
		'phone' => 'phone',
		'process' => 'process',
		'profilesaved' => 'profile_saved',
		'purchases' => 'purchases',
		'receipt' => 'order',
		'order' => 'order',
		'recoverbutton' => 'recover_button',
		'recoverurl' => 'recover_url',
		'register' => 'register',
		'registrationerrors' => 'registration_errors',
		'registrationform' => 'registration_form',
		'residentialshippingaddress' => 'residential_shipping_address',
		'sameshippingaddress' => 'same_shipping_address',
		'savebutton' => 'save_button',
		'shipping' => 'shipping',
		'shippingaddress' => 'shipping_address',
		'shippingcity' => 'shipping_city',
		'shippingcountry' => 'shipping_country',
		'shippingpostcode' => 'shipping_postcode',
		'shippingprovince' => 'shipping_state',
		'shippingstate' => 'shipping_state',
		'shippingxaddress' => 'shipping_xaddress',
		'submitlogin' => 'submit_login',
		'loginbutton' => 'submit_login',
		'url' => 'url',
		'wpusercreated' => 'wpuser_created'
	);


	static function _apicontext () { return "customer"; }

	/**
	 * _setobject - returns the global context object used in the shopp('customer') call
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 **/
	static function _setobject ($Object, $object) {
		if ( strtolower($object) != 'customer' ) return $Object; // not mine, do nothing
		else {
			if (is_object($Object) && 'Customer' == get_class($Object)) return $Object;

			$Order =& ShoppOrder();
			if(isset($Order->Customer)) return $Order->Customer;
		}
		return false;
	}

	function account_login ($result, $options, $O) {
		global $Shopp;
		$checkout = false;
		if (isset($Shopp->Flow->Controller->checkout))
			$checkout = $Shopp->Flow->Controller->checkout;

		$id = "account-login".($checkout?"-checkout":'');
		if (!empty($_POST['account-login']))
			$options['value'] = $_POST['account-login'];
		if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
		return '<input type="text" name="account-login" id="'.$id.'"'.inputattrs($options).' />';
	}

	function accounts ($result, $options, $O) { return $Shopp->Settings->get('account_system'); }

	function account_url ($result, $options, $O) { return shoppurl(false,'account'); }

	function action ($result, $options, $O) {
		$action = null;
		if (isset($O->pages[$_GET['acct']])) $action = $_GET['acct'];
		return shoppurl(array('acct'=>$action),'account');
	}

	function billing_address ($result, $options, $O) {
		$Order =& ShoppOrder();
		if ($options['mode'] == "value") return $Order->Billing->address;
		if (!empty($Order->Billing->address))
			$options['value'] = $Order->Billing->address;
		return '<input type="text" name="billing[address]" id="billing-address" '.inputattrs($options).' />';
	}

	function billing_city ($result, $options, $O) {
		$Order =& ShoppOrder();
		if ($options['mode'] == "value") return $Order->Billing->city;
		if (!empty($Order->Billing->city))
			$options['value'] = $Order->Billing->city;
		return '<input type="text" name="billing[city]" id="billing-city" '.inputattrs($options).' />';
	}

	function billing_country ($result, $options, $O) {
		$Order =& ShoppOrder();
		if ($options['mode'] == "value") return $Order->Billing->country;
		$base = $Shopp->Settings->get('base_operations');

		if (!empty($Order->Billing->country))
			$options['selected'] = $Order->Billing->country;
		else if (empty($options['selected'])) $options['selected'] = $base['country'];

		$countries = $Shopp->Settings->get('target_markets');

		$output = '<select name="billing[country]" id="billing-country" '.inputattrs($options,$select_attrs).'>';
	 	$output .= menuoptions($countries,$options['selected'],true);
		$output .= '</select>';
		return $output;
	}

	function billing_postcode ($result, $options, $O) {
		$Order =& ShoppOrder();
		if ($options['mode'] == "value") return $Order->Billing->postcode;
		if (!empty($Order->Billing->postcode))
			$options['value'] = $Order->Billing->postcode;
		return '<input type="text" name="billing[postcode]" id="billing-postcode" '.inputattrs($options).' />';
	}

	function billing_state ($result, $options, $O) {
		$Order =& ShoppOrder();
		if ($options['mode'] == "value") return $Order->Billing->state;
		if (!isset($options['selected'])) $options['selected'] = false;
		if (!empty($Order->Billing->state)) {
			$options['selected'] = $Order->Billing->state;
			$options['value'] = $Order->Billing->state;
		}
		if (empty($options['type'])) $options['type'] = "menu";
		$countries = Lookup::countries();

		$output = false;
		$country = $base['country'];
		if (!empty($Order->Billing->country))
			$country = $Order->Billing->country;
		if (!array_key_exists($country,$countries)) $country = key($countries);

		$regions = Lookup::country_zones();
		$states = $regions[$country];
		if (is_array($states) && $options['type'] == "menu") {
			$label = (!empty($options['label']))?$options['label']:'';
			$output = '<select name="billing[state]" id="billing-state" '.inputattrs($options,$select_attrs).'>';
			$output .= '<option value="" selected="selected">'.$label.'</option>';
		 	$output .= menuoptions($states,$options['selected'],true);
			$output .= '</select>';
		} else if ($options['type'] == "menu") {
			$options['disabled'] = 'disabled';
			$options['class'] = ($options['class']?" ":null).'unavailable';
			$label = (!empty($options['label']))?$options['label']:'';
			$output = '<select name="billing[state]" id="billing-state" '.inputattrs($options,$select_attrs).'></select>';
		} else $output .= '<input type="text" name="billing[state]" id="billing-state" '.inputattrs($options).'/>';
		return $output;
	}

	function billing_xaddress ($result, $options, $O) {
		$Order =& ShoppOrder();
		if ($options['mode'] == "value") return $Order->Billing->xaddress;
		if (!empty($Order->Billing->xaddress))
			$options['value'] = $Order->Billing->xaddress;
		return '<input type="text" name="billing[xaddress]" id="billing-xaddress" '.inputattrs($options).' />';
	}

	function company ($result, $options, $O) {
		if (isset($options['mode']) && $options['mode'] == "value") return $O->company;
		if (!empty($O->company))
			$options['value'] = $O->company;
		return '<input type="text" name="company" id="company"'.inputattrs($options).' />';
	}

	function confirm_password ($result, $options, $O) {
		if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
		$options['value'] = "";
		return '<input type="password" name="confirm-password" id="confirm-password"'.inputattrs($options).' />';
	}

	function download ($result, $options, $O) {
		$download = current($O->downloads);
		$df = get_option('date_format');
		$properties = unserialize($download->properties);
		$string = '';
		if (array_key_exists('id',$options)) $string .= $download->download;
		if (array_key_exists('purchase',$options)) $string .= $download->purchase;
		if (array_key_exists('name',$options)) $string .= $download->name;
		if (array_key_exists('variation',$options)) $string .= $download->optionlabel;
		if (array_key_exists('downloads',$options)) $string .= $download->downloads;
		if (array_key_exists('key',$options)) $string .= $download->dkey;
		if (array_key_exists('created',$options)) $string .= $download->created;
		if (array_key_exists('total',$options)) $string .= money($download->total);
		if (array_key_exists('filetype',$options)) $string .= $properties['mimetype'];
		if (array_key_exists('size',$options)) $string .= readableFileSize($download->size);
		if (array_key_exists('date',$options)) $string .= _d($df,mktimestamp($download->created));
		if (array_key_exists('url',$options))
			$string .= SHOPP_PRETTYURLS?
				shoppurl("download/$download->dkey"):
				shoppurl(array('s_dl'=>$download->dkey),'account');

		return $string;
	}

	function downloads ($result, $options, $O) {
		if (empty($O->downloads)) return false;
		if (!isset($O->_dowload_looping)) {
			reset($O->downloads);
			$O->_dowload_looping = true;
		} else next($O->downloads);

		if (current($O->downloads) !== false) return true;
		else {
			unset($O->_dowload_looping);
			reset($O->downloads);
			return false;
		}
	}

	function email ($result, $options, $O) {
		if (isset($options['mode']) && $options['mode'] == "value") return $O->email;
		if (!empty($O->email))
			$options['value'] = $O->email;
		return '<input type="text" name="email" id="email"'.inputattrs($options).' />';
	}

	function errors ($result, $options, $O) {
		if (!apply_filters('shopp_show_account_errors',true)) return false;
		$Errors = &ShoppErrors();
		if (!$Errors->exist(SHOPP_AUTH_ERR)) return false;

		ob_start();
		include(SHOPP_TEMPLATES."/errors.php");
		$errors = ob_get_contents();
		ob_end_clean();
		return $errors;
	}

	function errors_exist ($result, $options, $O) {
		$Errors = &ShoppErrors();
		return ($Errors->exist(SHOPP_AUTH_ERR));
	}

	function first_name ($result, $options, $O) {
		if (isset($options['mode']) && $options['mode'] == "value") return $O->firstname;
		if (!empty($O->firstname))
			$options['value'] = $O->firstname;
		return '<input type="text" name="firstname" id="firstname"'.inputattrs($options).' />';
	}

	function has_account ($result, $options, $O) {
		$system = $Shopp->Settings->get('account_system');
		if ($system == "wordpress") return ($O->wpuser != 0);
		elseif ($system == "shopp") return (!empty($O->password));
		else return false;
	}

	function has_downloads ($result, $options, $O) {
		return (!empty($O->downloads));
	}

	function has_info ($result, $options, $O) {
		if (!is_object($O->info) || empty($O->info->meta)) return false;
		if (!isset($O->_info_looping)) {
			reset($O->info->meta);
			$O->_info_looping = true;
		} else next($O->info->meta);

		if (current($O->info->meta) !== false) return true;
		else {
			unset($O->_info_looping);
			reset($O->info->meta);
			return false;
		}
	}

	function has_purchases ($result, $options, $O) {
		$filters = array();
		if (isset($options['daysago']))
			$filters['where'] = "UNIX_TIMESTAMP(o.created) > UNIX_TIMESTAMP()-".($options['daysago']*86400);
		if (empty($Shopp->purchases)) $O->load_orders($filters);
		return (!empty($Shopp->purchases));
	}

	function info ($result, $options, $O) {
		$defaults = array(
			'mode' => 'input',
			'type' => 'text',
			'name' => false,
			'value' => false
		);
		$options = array_merge($defaults,$options);
		extract($options);

		if ($O->_info_looping)
			$info = current($O->info->meta);
		elseif ($name !== false && is_object($O->info->named[$name]))
			$info = $O->info->named[$name];

		switch ($mode) {
			case "name": return $info->name; break;
			case "value": return $info->value; break;
		}

		if (!$name && !empty($info->name)) $options['name'] = $info->name;
		elseif (!$name) return false;

		if (!$value && !empty($info->value)) $options['value'] = $info->value;

		$allowed_types = array("text","password","hidden","checkbox","radio");
		$type = in_array($type,$allowed_types)?$type:'hidden';
		return '<input type="'.$type.'" name="info['.$options['name'].']" id="customer-info-'.sanitize_title_with_dashes($options['name']).'"'.inputattrs($options).' />';
	}

	function last_name ($result, $options, $O) {
		if (isset($options['mode']) && $options['mode'] == "value") return $O->lastname;
		if (!empty($O->lastname))
			$options['value'] = $O->lastname;
		return '<input type="text" name="lastname" id="lastname"'.inputattrs($options).' />';
	}

	function logged_in ($result, $options, $O) { return $Shopp->Order->Customer->login; }

	function login_label ($result, $options, $O) {
		$accounts = $Shopp->Settings->get('account_system');
		$label = __('Email Address','Shopp');
		if ($accounts == "wordpress") $label = __('Login Name','Shopp');
		if (isset($options['label'])) $label = $options['label'];
		return $label;
	}

	function login_name ($result, $options, $O) {
		if (isset($options['mode']) && $options['mode'] == "value") return $O->loginname;
		if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
		if (!empty($O->loginname))
			$options['value'] = $O->loginname;
		return '<input type="text" name="loginname" id="login"'.inputattrs($options).' />';
	}

	function management ($result, $options, $O) {
		$page = current($O->menus);
		if (array_key_exists('url',$options)) return shoppurl(array('acct'=>$page->request),'account');
		if (array_key_exists('action',$options)) return $page->request;
		return $page->label;
	}

	function marketing ($result, $options, $O) {
		if ($options['mode'] == "value") return $O->marketing;
		if (!empty($O->marketing) && value_is_true($O->marketing)) $options['checked'] = true;
		$attrs = array("accesskey","alt","checked","class","disabled","format",
			"minlength","maxlength","readonly","size","src","tabindex",
			"title");
		$input = '<input type="hidden" name="marketing" value="no" />';
		$input .= '<input type="checkbox" name="marketing" id="marketing" value="yes" '.inputattrs($options,$attrs).' />';
		return $input;
	}

	function menu ($result, $options, $O) {
		if (!isset($O->_menu_looping)) {
			reset($O->menus);
			$O->_menu_looping = true;
		} else next($O->menus);

		if (current($O->menus) !== false) return true;
		else {
			unset($O->_menu_looping);
			reset($O->menus);
			return false;
		}
	}

	function not_logged_in ($result, $options, $O) { global $Shopp; return (!$Shopp->Order->Customer->login && $Shopp->Settings->get('account_system') != "none"); }

	function order ($result, $options, $O) {
		return shoppurl(array('acct'=>'order','id'=>$Shopp->Purchase->id),'account');
	}

	function order_lookup ($result, $options, $O) {
		$auth = $Shopp->Settings->get('account_system');
		if ($auth != "none") return true;

		if (!empty($_POST['vieworder']) && !empty($_POST['purchaseid'])) {
			require_once("Purchase.php");
			$Purchase = new Purchase($_POST['purchaseid']);
			if ($Purchase->email == $_POST['email']) {
				$Shopp->Purchase = $Purchase;
				$Purchase->load_purchased();
				ob_start();
				include(SHOPP_TEMPLATES."/receipt.php");
				$content = ob_get_contents();
				ob_end_clean();
				return apply_filters('shopp_order_lookup',$content);
			}
		}

		ob_start();
		include(SHOPP_ADMIN_PATH."/orders/account.php");
		$content = ob_get_contents();
		ob_end_clean();
		return apply_filters('shopp_order_lookup',$content);
	}

	function password ($result, $options, $O) {
		if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
		if (isset($options['mode']) && $options['mode'] == "value")
			return strlen($O->password) == 34?str_pad('&bull;',8):$O->password;
		$options['value'] = "";
		return '<input type="password" name="password" id="password"'.inputattrs($options).' />';
	}

	function password_changed ($result, $options, $O) {
		$change = (isset($O->_password_change) && $O->_password_change);
		unset($O->_password_change);
		return $change;
	}

	function password_login ($result, $options, $O) {
		global $Shopp;
		$checkout = false;
		if (isset($Shopp->Flow->Controller->checkout))
			$checkout = $Shopp->Flow->Controller->checkout;
		if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
		$id = "password-login".($checkout?"-checkout":'');

		if (!empty($_POST['password-login']))
			$options['value'] = $_POST['password-login'];
		return '<input type="password" name="password-login" id="'.$id.'"'.inputattrs($options).' />';
	}

	function phone ($result, $options, $O) {
		if (isset($options['mode']) && $options['mode'] == "value") return $O->phone;
		if (!empty($O->phone))
			$options['value'] = $O->phone;
		return '<input type="text" name="phone" id="phone"'.inputattrs($options).' />';
	}

	function process ($result, $options, $O) {
		if (!empty($_GET['acct']) && isset($O->pages[$_GET['acct']])) return $_GET['acct'];
		return false;
	}

	function profile_saved ($result, $options, $O) {
		$saved = (isset($O->_saved) && $O->_saved);
		unset($O->_saved);
		return $saved;
	}

	function purchases ($result, $options, $O) {
		if (!isset($O->_purchaseloop)) {
			reset($Shopp->purchases);
			$Shopp->Purchase = current($Shopp->purchases);
			$O->_purchaseloop = true;
		} else {
			$Shopp->Purchase = next($Shopp->purchases);
		}

		if (current($Shopp->purchases) !== false) return true;
		else {
			unset($O->_purchaseloop);
			return false;
		}
	}

	function recover_button ($result, $options, $O) {
		if (!isset($options['value'])) $options['value'] = __('Get New Password','Shopp');
			return '<input type="submit" name="recover-login" id="recover-button"'.inputattrs($options).' />';
	}

	function recover_url ($result, $options, $O) { return add_query_arg('acct','recover',shoppurl(false,'account')); }

	function register ($result, $options, $O) {
		return '<input type="submit" name="shopp_registration" value="Register" />';
	}

	function registration_errors ($result, $options, $O) {
		$Errors =& ShoppErrors();
		if (!$Errors->exist(SHOPP_ERR)) return false;
		ob_start();
		include(SHOPP_TEMPLATES.'/errors.php');
		$markup = ob_get_contents();
		ob_end_clean();
		return $markup;
	}

	function registration_form ($result, $options, $O) {
		$regions = Lookup::country_zones();
		add_storefrontjs("var regions = ".json_encode($regions).";",true);
		return $_SERVER['REQUEST_URI'];
	}

	function residential_shipping_address ($result, $options, $O) {
		$label = __("Residential shipping address","Shopp");
		if (isset($options['label'])) $label = $options['label'];
		if (isset($options['checked']) && value_is_true($options['checked'])) $checked = ' checked="checked"';
		$output = '<label for="residential-shipping"><input type="hidden" name="shipping[residential]" value="no" /><input type="checkbox" name="shipping[residential]" value="yes" id="residential-shipping" '.$checked.' /> '.$label.'</label>';
		return $output;
	}

	function same_shipping_address ($result, $options, $O) {
		$label = __("Same shipping address","Shopp");
		if (isset($options['label'])) $label = $options['label'];
		$checked = ' checked="checked"';
		if (isset($options['checked']) && !value_is_true($options['checked'])) $checked = '';
		$output = '<label for="same-shipping"><input type="checkbox" name="sameshipaddress" value="on" id="same-shipping" '.$checked.' /> '.$label.'</label>';
		return $output;
	}

	function save_button ($result, $options, $O) {
		if (!isset($options['label'])) $options['label'] = __('Save','Shopp');
		$result = '<input type="hidden" name="customer" value="true" />';
		$result .= '<input type="submit" name="save" id="save-button"'.inputattrs($options).' />';
		return $result;
	}

	function shipping ($result, $options, $O) { $Order =& ShoppOrder(); return $Order->Shipping; }

	function shipping_address ($result, $options, $O) {
		$Order =& ShoppOrder();
		if ($options['mode'] == "value") return $Order->Shipping->address;
		if (!empty($Order->Shipping->address))
			$options['value'] = $Order->Shipping->address;
		return '<input type="text" name="shipping[address]" id="shipping-address" '.inputattrs($options).' />';
	}

	function shipping_city ($result, $options, $O) {
		$Order =& ShoppOrder();
		if ($options['mode'] == "value") return $Order->Shipping->city;
		if (!empty($Order->Shipping->city))
			$options['value'] = $Order->Shipping->city;
		return '<input type="text" name="shipping[city]" id="shipping-city" '.inputattrs($options).' />';
	}

	function shipping_country ($result, $options, $O) {
		$Order =& ShoppOrder();
		if ($options['mode'] == "value") return $Order->Shipping->country;
		$base = $Shopp->Settings->get('base_operations');
		if (!empty($Order->Shipping->country))
			$options['selected'] = $Order->Shipping->country;
		else if (empty($options['selected'])) $options['selected'] = $base['country'];

		$countries = $Shopp->Settings->get('target_markets');

		$output = '<select name="shipping[country]" id="shipping-country" '.inputattrs($options,$select_attrs).'>';
	 	$output .= menuoptions($countries,$options['selected'],true);
		$output .= '</select>';
		return $output;
	}

	function shipping_postcode ($result, $options, $O) {
		$Order =& ShoppOrder();
		if ($options['mode'] == "value") return $Order->Shipping->postcode;
		if (!empty($Order->Shipping->postcode))
			$options['value'] = $Order->Shipping->postcode;
		return '<input type="text" name="shipping[postcode]" id="shipping-postcode" '.inputattrs($options).' />';
	}

	function shipping_state ($result, $options, $O) {
		$Order =& ShoppOrder();
		if ($options['mode'] == "value") return $Order->Shipping->state;
		if (!isset($options['selected'])) $options['selected'] = false;
		if (!empty($Order->Shipping->state)) {
			$options['selected'] = $Order->Shipping->state;
			$options['value'] = $Order->Shipping->state;
		}
		$countries = Lookup::countries();
		$output = false;
		$country = $base['country'];
		if (!empty($Order->Shipping->country))
			$country = $Order->Shipping->country;
		if (!array_key_exists($country,$countries)) $country = key($countries);

		if (empty($options['type'])) $options['type'] = "menu";
		$regions = Lookup::country_zones();
		$states = $regions[$country];
		if (is_array($states) && $options['type'] == "menu") {
			$label = (!empty($options['label']))?$options['label']:'';
			$output = '<select name="shipping[state]" id="shipping-state" '.inputattrs($options,$select_attrs).'>';
			$output .= '<option value="" selected="selected">'.$label.'</option>';
		 	$output .= menuoptions($states,$options['selected'],true);
			$output .= '</select>';
		} else if ($options['type'] == "menu") {
			$options['disabled'] = 'disabled';
			$options['class'] = ($options['class']?" ":null).'unavailable';
			$label = (!empty($options['label']))?$options['label']:'';
			$output = '<select name="shipping[state]" id="shipping-state" '.inputattrs($options,$select_attrs).'></select>';
		} else $output .= '<input type="text" name="shipping[state]" id="shipping-state" '.inputattrs($options).'/>';
		return $output;
	}

	function shipping_xaddress ($result, $options, $O) {
		$Order =& ShoppOrder();
		if ($options['mode'] == "value") return $Order->Shipping->xaddress;
		if (!empty($Order->Shipping->xaddress))
			$options['value'] = $Order->Shipping->xaddress;
		return '<input type="text" name="shipping[xaddress]" id="shipping-xaddress" '.inputattrs($options).' />';
	}

	function submit_login ($result, $options, $O) {
		global $Shopp;
		$checkout = false;
		if (isset($Shopp->Flow->Controller->checkout))
			$checkout = $Shopp->Flow->Controller->checkout;
		$Order =& ShoppOrder();

		if (!isset($options['value'])) $options['value'] = __('Login','Shopp');
		$string = "";
		$id = "submit-login";

		$request = $_GET;
		if (isset($request['acct']) && $request['acct'] == "logout") unset($request['acct']);

		if ($checkout) {
			$id .= "-checkout";
			$string .= '<input type="hidden" name="process-login" id="process-login" value="false" />';
			$string .= '<input type="hidden" name="redirect" value="checkout" />';
		} else $string .= '<input type="hidden" name="process-login" value="true" /><input type="hidden" name="redirect" value="'.shoppurl($request,'account',$Order->security()).'" />';
		$string .= '<input type="submit" name="submit-login" id="'.$id.'"'.inputattrs($options).' />';
		return $string;
	}

	function url ($result, $options, $O) {
		return shoppurl(array('acct'=>null),'account',$Shopp->Gateways->secure);
	}

	function wpuser_created ($result, $options, $O) { return $O->newuser; }

}

?>