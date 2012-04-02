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
 * @param WP_Query $wp_query (optional) will use the global wp_query by default if false, or the WP_Query object to evaluation
 * @return boolean
 **/
function is_catalog_page ( $wp_query = false ) {
	return is_shopp_page('catalog', $wp_query);
}

/**
 * Determines if the requested page is the catalog front page.
 *
 * @author Jonathan Davis
 * @since 1.2
 *
 * @param WP_Query $wp_query (optional) will use the global wp_query by default if false, or the WP_Query object to evaluation
 * @return boolean
 **/
function is_catalog_frontpage ( $wp_query = false ) {
	if ( false === $wp_query ) { global $wp_the_query; $wp_query =& $wp_the_query; }
	return is_shopp_page('catalog', $wp_query) && ! ( is_shopp_product($wp_query) || is_shopp_collection($wp_query) );
}

/**
 * Determines if the requested page is the account page.
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param WP_Query $wp_query (optional) will use the global wp_query by default if false, or the WP_Query object to evaluation
 * @return boolean
 **/
function is_account_page ( $wp_query = false ) {
	return is_shopp_page('account', $wp_query);
}

/**
 * Determines if the requested page is the cart page.
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param WP_Query $wp_query (optional) will use the global wp_query by default if false, or the WP_Query object to evaluation
 * @return boolean
 **/
function is_cart_page ( $wp_query = false ) {
	return is_shopp_page('cart', $wp_query);
}

/**
 * Determines if the requested page is the checkout page.
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param WP_Query $wp_query (optional) will use the global wp_query by default if false, or the WP_Query object to evaluation
 * @return boolean
 **/
function is_checkout_page ( $wp_query = false ) {
	return is_shopp_page('checkout', $wp_query);
}

/**
 * Determines if the requested page is the confirm order page.
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param WP_Query $wp_query (optional) will use the global wp_query by default if false, or the WP_Query object to evaluation
 * @return boolean
 **/
function is_confirm_page ( $wp_query = false ) {
	return is_shopp_page('confirm', $wp_query);
}

/**
 * Determines if the requested page is the thanks page.
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param WP_Query $wp_query (optional) will use the global wp_query by default if false, or the WP_Query object to evaluation
 * @return boolean
 **/
function is_thanks_page ( $wp_query = false ) {
	return is_shopp_page('thanks', $wp_query);
}

/**
 * Determines if the requested page is the shopp search page.
 *
 * @author John Dillick
 * @since 1.2.1
 *
 * @param WP_Query $wp_query (optional) will use the global wp_query by default if false, or the WP_Query object to evaluation
 * @return boolean
 **/
function is_shopp_search ( $wp_query = false ) {
	if ( false === $wp_query ) { global $wp_the_query; $wp_query =& $wp_the_query; }
	return $wp_query->is_search() && $wp_query->get('s_cs');
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
 * @param WP_Query $wp_query (optional) will use the global wp_query by default if false, or the WP_Query object to evaluation
 * @return boolean
 **/
function is_shopp_page ( $page = false, $wp_query = false ) {
	if ( false === $wp_query ) { global $wp_the_query; $wp_query =& $wp_the_query; }
	if (empty($wp_query->query_vars)) new ShoppError('Conditional is_shopp_page functions do not work before the WordPress query is run. Before then, they always return false.','doing_it_wrong',SHOPP_DEBUG_ERR);
	$is_shopp_page = false;
	$pages = Storefront::pages_settings();

	// Check for Shopp custom posttype/taxonomy requests
	if ( 'catalog' == $page ||  ! $page ) {
		if ( is_shopp_product($wp_query) || is_shopp_collection($wp_query) ) $is_shopp_page = true;

		$slug = $wp_query->get('shopp_page');
		if ( $slug && $pages['catalog']['slug'] == $slug ) $is_shopp_page = true;
		if ( ! $is_shopp_page && ! $slug && Product::$posttype == $wp_query->get('post_type') ) $is_shopp_page = true;
	}

	// Detect if the requested page is a Storefront page
	$slugpage = $wp_query->get('shopp_page');
	if ( ! $page && $slugpage ) $page = Storefront::slugpage($slugpage);
	if ( isset( $pages[ $page ] ) && $pages[ $page ]['slug'] == $slugpage ) $is_shopp_page = true;

	return $is_shopp_page;
}

/**
 * Determines if the passed WP_Query object is a Shopp storefront page, Shopp product collection, Shopp product taxonomy, or Shopp product query.
 * Alias for is_shopp_page() with reordered arguments, as it will usually be used for testing parse_query action referenced objects for custom WP_Query loops.
 *
 * @author John Dillick
 * @since 1.2.1
 *
 * @param WP_Query $wp_query (optional) will use the global wp_query by default if false, or the WP_Query object to evaluation
 * @param string $page (optional) System page name ID for the correct Storefront page @see Storefront::default_pages()
 * @return bool
 **/
function is_shopp_query ( $wp_query = false, $page = false ) {
	return is_shopp_page( $page, $wp_query );
}

/**
 * Determines if the current request is for any shopp smart collection, product taxonomy term, or search collection
 *
 * NOTE: This function will not identify PHP loaded collections, it only
 * compares the page request, meaning using is_shopp_collection on the catalog landing
 * page, even when the landing page (catalog.php) template loads the CatalogProducts collection
 * will return false, because CatalogProducts is loaded in the template and not directly
 * from the request.
 *
 * @author John Dillick, Jonathan Davis
 * @since 1.2
 *
 * @param WP_Query $wp_query (optional) will use the global wp_query by default if false, or the WP_Query object to evaluation
 * @return boolean
 **/
function is_shopp_collection ( $wp_query = false ) {
	if ( is_shopp_smart_collection($wp_query) || is_shopp_taxonomy($wp_query) || is_shopp_search($wp_query) ) return true;
	return false;
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
 * @author John Dillick, Jonathan Davis
 * @since 1.2
 *
 * @param WP_Query $wp_query (optional) will use the global wp_query by default if false, or the WP_Query object to evaluation
 * @return boolean
 **/
function is_shopp_smart_collection ( $wp_query = false ) {
	if ( false === $wp_query ) { global $wp_the_query; $wp_query =& $wp_the_query; }

	$slug = $wp_query->get('shopp_collection');
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
 * @param WP_Query $wp_query (optional) will use the global wp_query by default if false, or the WP_Query object to evaluation
 * @return boolean
 **/
function is_shopp_taxonomy ( $wp_query = false ) {
	if ( false === $wp_query ) { global $wp_the_query; $wp_query =& $wp_the_query; }

	$taxonomies = get_object_taxonomies(Product::$posttype, 'names');
	foreach ( $taxonomies as $taxonomy ) {
		if ( $wp_query->is_tax($taxonomy) ) return true;
	}
	return false;
}

/**
 * Determines if the current request is for a Shopp product custom post type
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param WP_Query $wp_query (optional) will use the global wp_query by default if false, or the WP_Query object to evaluation
 * @return boolean
 **/
function is_shopp_product ( $wp_query = false ) {
	if ( false === $wp_query ) { global $wp_the_query; $wp_query =& $wp_the_query; }
	$product = $wp_query->get(Product::$posttype);
	return (bool) $product;
}

?>