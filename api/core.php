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


/**
 * Determines if the requested page is a Shopp page or if it matches a given Shopp page
 * Also checks to see if the current loaded query is a Shopp product or product taxonomy.
 *
 * @author Jonathan Davis, John Dillick
 * @since 1.0
 *
 * @param string $page (optional) Page name to look for in Shopp's page registry
 * @return boolean
 **/
function is_shopp_page ($page=false) {
	// Check for loading single shopp_product or shopp_product taxonomy on catalog checks and ordinary checks
	if ( 'catalog' == $page || ! $page ) {
		$product_tax = false;
		$taxonomies = get_object_taxonomies(Product::$posttype, 'names');
		foreach ( $taxonomies as $taxonomy ) {
			if ( is_tax($taxonomy) ) $product_tax = true;
		}
		if ( is_singular(Product::$posttype) || $product_tax ) return true;
	}

	if ( ! is_page() ) return false;

	// @todo replace with storefront_pages setting?
	$pages = shopp_setting('pages');

	// Detect if the requested page is a Shopp page
	if ( ! $page ) {
		foreach ($pages as $page)
			if ( is_page($page['id']) ) return true;
		return false;
	}

	// Determine if the visitor's requested page matches the provided page
	if (!isset($pages[strtolower($page)])) return false;
	$page = $pages[strtolower($page)];
	if ( is_page($page['id']) ) return true;
	return false;

}

/**
 * is_shopp_taxonomy - Is the current WordPress query, a query for a Shopp product taxonomy?
 *
 * @author John Dillick
 * @since 1.2
 *
 * @return bool true if the current WordPress query is for a shopp product taxonomy, else false
 **/
function is_shopp_taxonomy () {
	$taxonomies = get_object_taxonomies(Product::$posttype, 'names');
	foreach ( $taxonomies as $taxonomy ) {
		if ( is_tax($taxonomy) ) return true;
	}
	return false;
}

/**
 * is_shopp_product - Is the current WordPress query, a query for a Shopp product?
 *
 * @author John Dillick
 * @since 1.2
 *
 * @return bool true if the current WordPress query is for a shopp product, else false
 **/
function is_shopp_product () {
	return is_singular(Product::$posttype);
}

?>