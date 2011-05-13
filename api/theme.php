<?php
/**
 * Shopp Theme API
 *
 * @author Jonathan Davis, John Dillick
 * @version 1.0
 * @copyright Ingenesis Limited, February 25, 2011
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package Shopp
 * @since 1.2
 * @subpackage Theme
 **/

/**
 * Defines the shopp() 'tag' handler for complete template customization
 *
 * Appropriately routes tag calls to the tag handler for the requested object.
 *
 * @since 1.0
 * @version 1.2
 *
 * @param mixed $context The object label or Object to get the tag property from
 * @param $property The property of the object to get/output
 * @param $options Custom options for the property result in query form
 *                   (option1=value&option2=value&...) or alternatively as an associative array
 */
function shopp () {
	$Object = false;
	$result = false;

	$parameters = array('first','second','third');	// Parameter prototype
	$num = func_num_args();							// Determine number of arguments provided
	$context = $tag = false;							// object API to use and tag name
	$options = array();								// options to pass to API call

	if ($num < 1) { // Not enough arguments to do anything, bail
		new ShoppError(__('shopp() theme tag syntax error: no object property specified.','Shopp'));
		return;
	}

	// Grab the arguments (up to 3)
	$args = array_combine(array_slice($parameters,0,$num),func_get_args());
	extract($args);

	if ( is_object($first) ) { // Handle Object instances as first argument
		$Object = $first;
		$context = isset($Object->api) ? $Object->api : strtolower(get_class($Object));
	} elseif ( false !== strpos($context,'.') ) { // Handle object.tag first argument
		list($context,$tag) = explode('.', strtolower($context));
	} elseif ('' == $context.$tag) { // Normal tag handler
		list($context,$tag) = array_map('strtolower',array($first,$second));
	}

	$options = shopp_parse_options($num < 3?$second:$third);

	// strip hypens from tag names
	$tag = str_replace ( '-', '', $tag );

	// strip get prefix from requested tag
	$get = false;
	if ( 'get' == substr($tag, 0, 3) ) {
		$tag = substr($tag,3);
		$get = true;
	}

	// switch (strtolower($context)) {
	// case "cart": 		if (isset($Shopp->Order->Cart)) $Object =& $Shopp->Order->Cart; break;
	// case "cartitem": 	if (isset($Shopp->Order->Cart)) {
	// 						$Cart =& $Shopp->Order->Cart;
	// 						$Item = false;
	// 						if (isset($Cart->_item_loop)) { $Item = current($Cart->contents); $Item->_id = key($Cart->contents); }
	// 						elseif (isset($Cart->_shipped_loop)) { $Item = current($Cart->shipped); $Item->_id = key($Cart->shipped); }
	// 						elseif (isset($Cart->_downloads_loop)) { $Item = current($Cart->downloads); $Item->_id = key($Cart->downloads); }
	// 						if ($Item === false) return false;
	// 						$Object = $Item;
	// 					}
	// 					break;
	// case "shipping": 	if (isset($Shopp->Order->Cart)) $Object =& $Shopp->Order->Cart; break;
	// case "category": 	if (isset($Shopp->Category)) $Object =& $Shopp->Category; break;
	// case "subcategory": if (isset($Shopp->Category->child)) $Object =& $Shopp->Category->child; break;
	// case "catalog": 	if (isset($Shopp->Catalog)) $Object =& $Shopp->Catalog; break;
	// case "product": 	if (isset($Shopp->Product)) $Object =& $Shopp->Product; break;
	// case "checkout": 	if (isset($Shopp->Order)) $Object =& $Shopp->Order; break;
	// case "purchase": 	if (isset($Shopp->Purchase)) $Object =& $Shopp->Purchase; break;
	// case "customer": 	if (isset($Shopp->Order->Customer)) $Object =& $Shopp->Order->Customer; break;
	// case "error": 		if (isset($Shopp->Errors)) $Object =& $Shopp->Errors; break;

	$Object = apply_filters('shopp_themeapi_object', $Object, $context);
	$Object = apply_filters('shopp_tag_domain', $Object, $context); // @deprecated
	echo "object: $context Object: "._object_r($Object)." property: ".$tag.BR;

	if ('has-context' == $tag) return ($Object);

	if (!$Object) new ShoppError( sprintf( __('The shopp(\'%s\') tag cannot be used in this context because the object responsible for handling it doesn\'t exist.', 'Shopp'), $context ),'shopp_tag_error',SHOPP_ADMIN_ERR);

	$themeapi = apply_filters('shopp_themeapi_context_name',$context);
	$result = apply_filters('shopp_themeapi_'.strtolower($themeapi.'_'.$tag),$result,$options,$Object); // tag specific tag filter
	$result = apply_filters('shopp_tag_'.strtolower($context.'_'.$tag),$result,$options,$Object); // @deprecated

	$result = apply_filters('shopp_themeapi_'.strtolower($themeapi),$result,$options,$tag,$Object); // global object tag filter
	$result = apply_filters('shopp_ml_t',$result,$options,$tag,$Object);

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

	if ( $get ||
		( isset($options['return']) && value_is_true($options['return']) ) ||
		( isset($options['echo']) && !value_is_true($options['echo']) )	)
		return $result;

	// Output the result
	if (is_scalar($result)) echo $result;
	else return $result;
	return true;

}

require_once('theme/cart.php');
require_once('theme/cartitem.php');
require_once('theme/shipping.php');
require_once('theme/collection.php');
require_once('theme/catalog.php');
require_once('theme/product.php');
require_once('theme/checkout.php');
require_once('theme/purchase.php');
require_once('theme/customer.php');
require_once('theme/error.php');

?>