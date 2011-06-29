<?php
/**
 * Core
 *
 * Interface for getting and setting global objects.
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, February 25, 2011
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @since 1.0
 * @subpackage shopp
 **/

/**
 * ShoppProduct - get and set the global Product object
 *
 * @author Jonathan Davis
 * @since 1.2
 *
 * @param Product (optional) $Object the product object to set to the global context.
 * @return mixed if the global Product context isn't set, bool false will be returned, otherwise the global Product object will be returned
 **/
function &ShoppProduct ( &$Object = false ) {
	global $Shopp; $false = false;
	if (empty($Shopp)) return $false;
	if ($Object !== false) $Shopp->Product = $Object;
	return $Shopp->Product;
}

/**
 * ShoppCollection - get and set the global Collection object (ie. ProductCategory, SmartCollection)
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param Collection (optional) $Object the Collection object to set to the global context.
 * @return mixed if the global Collection context isn't set, bool false will be returned, otherwise the global Collection object will be returned
 **/
function &ShoppCollection ( &$Object = false ) {
	global $Shopp; $false = false;
	if (empty($Shopp)) return $false;
	if ($Object !== false) $Shopp->Category = $Object;
	return $Shopp->Category;
}

/**
 * ShoppCatalog - get and set the global Catalog object
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param Catalog (optional) $Object the Catalog object to set to the global context.
 * @return mixed if the global Catalog context isn't set, bool false will be returned, otherwise the global Catalog object will be returned
 **/
function &ShoppCatalog ( &$Object = false ) {
	global $Shopp; $false = false;
	if (empty($Shopp)) return $false;
	if ($Object !== false) $Shopp->Catalog = $Object;
	return $Shopp->Catalog;
}

/**
 * ShoppPurchase - get and set the global Purchase object
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param Purchase (optional) $Object the Catalog object to set to the global context.
 * @return mixed if the global Purchase context isn't set, bool false will be returned, otherwise the global Purchase object will be returned
 **/
function &ShoppPurchase ( &$Object = false ) {
	global $Shopp; $false = false;
	if (empty($Shopp)) return $false;
	if ($Object !== false) $Shopp->Purchase = $Object;
	return $Shopp->Purchase;
}

?>