<?php
/**
 * Template API
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, February 25, 2011
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package Shopp
 * @since 1.2
 * @subpackage Template
 **/

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
	global $Shopp;
	$args = func_get_args();

	$object = strtolower($args[0]);
	$property = strtolower($args[1]);
	$options = array();

	if (isset($args[2])) {
		if (is_array($args[2]) && !empty($args[2])) {
			// handle associative array for options
			foreach(array_keys($args[2]) as $key)
				$options[strtolower($key)] = $args[2][$key];
		} else {
			// regular url-compatible arguments
			$paramsets = explode("&",$args[2]);
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

	$Object = false; $result = false;
	switch (strtolower($object)) {
		case "cart": if (isset($Shopp->Order->Cart)) $Object =& $Shopp->Order->Cart; break;
		case "cartitem": if (isset($Shopp->Order->Cart)) $Object =& $Shopp->Order->Cart; break;
		case "shipping": if (isset($Shopp->Order->Cart)) $Object =& $Shopp->Order->Cart; break;
		case "category": if (isset($Shopp->Category)) $Object =& $Shopp->Category; break;
		case "subcategory": if (isset($Shopp->Category->child)) $Object =& $Shopp->Category->child; break;
		case "catalog": if (isset($Shopp->Catalog)) $Object =& $Shopp->Catalog; break;
		case "product": if (isset($Shopp->Product)) $Object =& $Shopp->Product; break;
		case "checkout": if (isset($Shopp->Order)) $Object =& $Shopp->Order; break;
		case "purchase": if (isset($Shopp->Purchase)) $Object =& $Shopp->Purchase; break;
		case "customer": if (isset($Shopp->Order->Customer)) $Object =& $Shopp->Order->Customer; break;
		case "error": if (isset($Shopp->Errors)) $Object =& $Shopp->Errors; break;
		default: $Object = apply_filters('shopp_tag_domain',$Object,$object);
	}

	if ('has-context' == $property) return ($Object);

	if (!$Object) new ShoppError("The shopp('$object') tag cannot be used in this context because the object responsible for handling it doesn't exist.",'shopp_tag_error',SHOPP_ADMIN_ERR);
	else {
		switch (strtolower($object)) {
			case "cartitem": $result = $Object->itemtag($property,$options); break;
			case "shipping": $result = $Object->shippingtag($property,$options); break;
			default: $result = $Object->tag($property,$options); break;
		}
	}

	// Multilanguage translation filter to support translation plugins
	$result = apply_filters('shopp_ml_t',$result,$options,$property,$Object);

	// Provide a filter hook for every template tag, includes passed options and the relevant Object as parameters
	$result = apply_filters('shopp_tag_'.strtolower($object).'_'.strtolower($property),$result,$options,$Object);

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

?>