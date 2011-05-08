<?php
/**
 * Template API
 *
 * @author Jonathan Davis, John Dillick
 * @version 1.0
 * @copyright Ingenesis Limited, February 25, 2011
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package Shopp
 * @since 1.2
 * @subpackage Template
 **/

require_once('theme/cart.php');
require_once('theme/cartitem.php');
require_once('theme/shipping.php');
require_once('theme/category.php');
require_once('theme/subcategory.php');
require_once('theme/catalog.php');
require_once('theme/product.php');
require_once('theme/checkout.php');
require_once('theme/purchase.php');
require_once('theme/customer.php');
require_once('theme/error.php');

class shoppapi {
	function __call($method, $options) {
		global $Shopp;
		list($object,$property) = explode("_",$method);
		if (empty($object) || empty($property)) return;

		if (is_array($property) && count($property) == 1) $property = $property[0];

		$Object = false; $result = false;
		switch (strtolower($object)) {
			case "cart": 		if (isset($Shopp->Order->Cart)) $Object =& $Shopp->Order->Cart; break;
			case "cartitem": 	if (isset($Shopp->Order->Cart)) {
									$Cart =& $Shopp->Order->Cart;
									$Item = false;
									if (isset($Cart->_item_loop)) { $Item = current($Cart->contents); $Item->_id = key($Cart->contents); }
									elseif (isset($Cart->_shipped_loop)) { $Item = current($Cart->shipped); $Item->_id = key($Cart->shipped); }
									elseif (isset($Cart->_downloads_loop)) { $Item = current($Cart->downloads); $Item->_id = key($Cart->downloads); }
									if ($Item === false) return false;
									$Object = $Item;
								}
								break;
			case "shipping": 	if (isset($Shopp->Order->Cart)) $Object =& $Shopp->Order->Cart; break;
			case "category": 	if (isset($Shopp->Category)) $Object =& $Shopp->Category; break;
			case "subcategory": if (isset($Shopp->Category->child)) $Object =& $Shopp->Category->child; break;
			case "catalog": 	if (isset($Shopp->Catalog)) $Object =& $Shopp->Catalog; break;
			case "product": 	if (isset($Shopp->Product)) $Object =& $Shopp->Product; break;
			case "checkout": 	if (isset($Shopp->Order)) $Object =& $Shopp->Order; break;
			case "purchase": 	if (isset($Shopp->Purchase)) $Object =& $Shopp->Purchase; break;
			case "customer": 	if (isset($Shopp->Order->Customer)) $Object =& $Shopp->Order->Customer; break;
			case "error": 		if (isset($Shopp->Errors)) $Object =& $Shopp->Errors; break;
			default: $Object = apply_filters('shopp_tag_domain',$Object,$object);
		}

		if ('has-context' == $property) return ($Object);

		if (!$Object) new ShoppError( sprintf( __('The shopp(\'%s\') tag cannot be used in this context because the object responsible for handling it doesn\'t exist.', 'Shopp'), $object ),'shopp_tag_error',SHOPP_ADMIN_ERR);

		// global property getters
		if ( 'get' == substr($property, 0, 3) && property_exists($Object, substr($property, 3)) ) {
			$getter = substr($property, 4);
			$result = $Object->$getter;
		}

		$result = apply_filters('shoppapi_'.strtolower($object).'_'.strtolower($property),$result,$options,$Object); // property specific tag filter
		$result = apply_filters('shoppapi_'.strtolower($object),$result,$options,$property,$Object); // global object tag filter

		$result = apply_filters('shopp_tag_'.strtolower($object).'_'.strtolower($property),$result,$options,$Object); // deprecated

		$result = apply_filters('shopp_ml_t',$result,$options,$property,$Object);

		// Force boolean result
		if (isset($options['is'])) {
			if (value_is_true($options['is'])) {
				if ($result) return true;
			} else {
				if ($result == false) return true;
			}
			return false;
		}

		// Always return a boolean if the result is boolean
		if (is_bool($result)) return $result;

		// Return the result instead of outputting it
		if ((isset($options['return']) && value_is_true($options['return'])) ||
				isset($options['echo']) && !value_is_true($options['echo']))
			return $result;

		// Output the result
		if (is_scalar($result)) echo $result;
		else return $result;
		return true;

	}
}

/**
 * Defines the shopp() 'tag' handler for complete template customization
 *
 * Appropriately routes tag calls to the tag handler for the requested object.
 *
 * @since 1.0
 * @version 1.2
 *
 * @param $object The object to get the tag property from
 * @param $property The property of the object to get/output
 * @param $options Custom options for the property result in query form
 *                   (option1=value&option2=value&...) or alternatively as an associative array
 */
function shopp () {
	$args = func_get_args();
	list($object,$property) = explode('.', strtolower($args[0]));

	if (!empty($object) && !empty($property)) if(isset($args[1])) $optionsarg = $args[1];
	else {
		if (count($args) < 2) return; // missing property
		$object = strtolower($args[0]);
		$property = strtolower($args[1]);
		if(isset($args[2])) $optionsarg = $args[2];
	}

	$options = array();
	if (isset($optionsarg)) {
		if (is_array($optionsarg) && !empty($optionsarg)) {
			// handle associative array for options
			foreach(array_keys($optionsarg) as $key)
				$options[strtolower($key)] = $optionsarg[$key];
		} else {
			// regular url-compatible arguments
			$paramsets = explode("&",$optionsarg);
			foreach ((array)$paramsets as $paramset) {
				if (empty($paramset)) continue;
				$key = $paramset;
				$value = "";
				if (strpos($paramset,"=") !== false)
					list($key,$value) = explode("=",$paramset);
				$options[strtolower($key)] = $value;
			}
		}
	}

	// strip hypens from all properties, allows all manner of hyphenated properties without creating invalid method call
	$property = str_replace ( "-", "", $property);

	if (!empty($object) && !empty($property)) {
		$apicall = $object."_".$property;
		if (!preg_match('/[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*./', $apicall )) {
			new ShoppError(__(sprintf('Invalid Shoppapi method call shoppapi::%s', $apicall),'Shopp'),false,SHOPP_ADMIN_ERR);
			return;
		}
		return shoppapi::$apicall($options);
	}
	return;
}

?>