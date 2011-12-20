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
 * ShoppCustomer - get and set the global Customer object
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param Customer $Object (optional) the specified Customer object
 * @return Customer the current global customer object
 **/
function &ShoppCustomer ( &$Object = false ) {
	$Order = &ShoppOrder();
	if ( $Object && is_a($Object, 'Customer') ) {
		$Order->Customer = $Object;
	}
	return $Order->Customer;
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
	if ( ! $Object && ! $Shopp->Catalog ) $Shopp->Catalog = new Catalog();

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
 * Get and set the Order object
 *
 * @author Jonathan Davis
 * @since 1.0
 *
 * @return Order
 **/
function &ShoppOrder ( &$Object = false ) {
	global $Shopp; $false = false;
	if (empty($Shopp)) return $false;
	if ($Object !== false) $Shopp->Order = $Object;
	return $Shopp->Order;
}


/**
 * Determines if the requested page is a catalog page
 *
 * Returns true for the catalog front page, Shopp taxonomy (categories, tags) pages,
 * smart collections and product pages
 *
 * @author John Dillick
 * @since 1.2
 *
 * @return bool true if the current page request is the catalog landing page, else false.
 **/
function is_catalog_page () {
	return is_shopp_page('catalog');
}

/**
 * Determines if the requested page is the catalog front page.
 *
 * @author Jonathan Davis
 * @since 1.2
 *
 * @return bool true if the current page request is the catalog landing page, else false.
 **/
function is_catalog_frontpage () {
	return is_shopp_page('catalog') && !( is_shopp_taxonomy() || is_shopp_product() || is_shopp_collection() );
}

/**
 * Determines if the requested page is the account page.
 *
 * @author John Dillick
 * @since 1.2
 *
 * @return bool true if the current page request is the account page, else false.
 **/
function is_account_page () {
	return is_shopp_page('account');
}

/**
 * Determines if the requested page is the cart page.
 *
 * @author John Dillick
 * @since 1.2
 *
 * @return bool true if the current page request is the cart page, else false.
 **/
function is_cart_page () {
	return is_shopp_page('cart');
}

/**
 * Determines if the requested page is the checkout page.
 *
 * @author John Dillick
 * @since 1.2
 *
 * @return bool true if the current page request is the checkout page, else false.
 **/
function is_checkout_page () {
	return is_shopp_page('checkout');
}

/**
 * Determines if the requested page is the confirm order page.
 *
 * @author John Dillick
 * @since 1.2
 *
 * @return bool true if the current page request is the confirm order page, else false.
 **/
function is_confirm_page () {
	return is_shopp_page('confirm');
}

/**
 * Determines if the requested page is the thanks page.
 *
 * @author John Dillick
 * @since 1.2
 *
 * @return bool true if the current page request is the thanks page, else false.
 **/
function is_thanks_page () {
	return is_shopp_page('thanks');
}


/**
 * Determines if the requested page is a Shopp page or if it matches a given Shopp page
 *
 * Also checks to see if the current loaded query is a Shopp product or product taxonomy.
 *
 * @author Jonathan Davis, John Dillick
 * @since 1.0
 *
 * @param string $page (optional) System page name ID for the correct Storefront page @see Storefront::default_pages()
 * @return boolean
 **/
function is_shopp_page ($page=false) {
	// Check for Shopp custom posttype/taxonomy requests
	if ( 'catalog' == $page ||  ! $page )
		if ( is_shopp_taxonomy() || is_shopp_product() || is_shopp_collection() ) return true;

	$pages = Storefront::pages_settings();

	// Detect if the requested page is a Storefront page
	if ( ! $page ) $page = Storefront::slugpage(get_query_var('shopp_page'));

	return isset( $pages[ $page ] ) && $pages[ $page ]['slug'] == get_query_var('shopp_page');
}

/**
 * Determines if the current request is for a registered dynamic Shopp collection
 *
 * NOTE: This function will not identify PHP loaded collections, it only
 * compares the page request, meaning using is_shopp_collection on the catalog landing
 * page, even when the landing page (catalog.php) template loads the CatalogProducts collection
 * will return false, because CatalogProducts is loaded in the template and not directly
 * from the request.
 *
 * @author Jonathan Davis
 * @since 1.2
 *
 * @return boolean
 **/
function is_shopp_collection () {
	$slug = get_query_var('shopp_collection');
	if (empty($slug)) return false;

	global $Shopp;
	foreach ($Shopp->Collections as $Collection) {
		$Collection_slug = get_class_property($Collection,'_slug');
		if ($slug == $Collection_slug) return true;
	}
	return false;
}

/**
 * Determines if the current request is for a Shopp product taxonomy
 *
 * @author John Dillick
 * @since 1.2
 *
 * @return boolean
 **/
function is_shopp_taxonomy () {
	$taxonomies = get_object_taxonomies(Product::$posttype, 'names');
	foreach ( $taxonomies as $taxonomy )
		if ( is_tax($taxonomy) ) return true;
	return false;
}

/**
 * Determines if the current request is for a Shopp product custom post type
 *
 * @author John Dillick
 * @since 1.2
 *
 * @return boolean
 **/
function is_shopp_product () {
	return is_singular(Product::$posttype);
}

?>