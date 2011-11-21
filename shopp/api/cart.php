<?php
/**
 * Cart API
 *
 * Plugin api calls for manipulating the cart contents.
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, June 23, 2011
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @since 1.2
 * @subpackage shopp
 **/

/**
 * shopp_add_cart_variant - add a product to the cart by variant id
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int $variant (required) variant id to add
 * @param int $quantity (optional default: 1) quantity of product to add
 * @return bool true on success, false on failure
 **/
function shopp_add_cart_variant ( $variant = false, $quantity = 1, $key = 'id') {
	$keys = array('id','optionkey','label','sku');
	if ( false === $variant ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: Variant parameter required.",__FUNCTION__,SHOPP_DEBUG_ERR);
	}
	if (!in_array($key,$keys)) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: Variant key $key invalid.",__FUNCTION__,SHOPP_DEBUG_ERR);
	}
	$Price = new Price( $variant, $key);
	if ( empty($Price->id) ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: Product variant $variant invalid.",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}

	return shopp_add_cart_product($Price->product, $quantity, $Price->id);
}

/**
 * shopp_add_cart_product - add a product to the cart
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int $product (required) product id to add
 * @param int $quantity (optional default: 1) quantity of product to add
 * @param int $variant (optional) variant id to use
 * @return bool true on success,
 * false on failure
 **/
function shopp_add_cart_product ( $product = false, $quantity = 1, $variant = false, $data = array() ) {
	$Order = ShoppOrder();
	if ( (int) $quantity < 1 ) $quantity = 1;

	if ( false === $product ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: Product parameter required.",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}

	$Product = new Product( $product );
	if ( empty($Product->id) ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: Product $product invalid",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}


	if ( false !== $variant ) {
		$Price = new Price( $variant );
		if ( empty($Price->id) || $Price->product != $product) {
			if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: Product variant $variant invalid.",__FUNCTION__,SHOPP_DEBUG_ERR);
			return false;
		}
	}

	if ( !empty($data) ) {
		if ( !is_array($data)) {
			if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: Product custom input data must be an array.",__FUNCTION__,SHOPP_DEBUG_ERR);
			return false;
		}
	}

	$added = $Order->Cart->add($quantity, $Product, $variant, false, $data);
	$Order->Cart->changed(true);
	$Order->Cart->totals();
	return $added;
}


/**
 * shopp_rmv_cart_item - remove a specific item from the cart
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int $item (required) the numeric index of the item contents array to remove ( 0 indexed )
 * @return bool true for success, false on failure
 **/
function shopp_rmv_cart_item ( $item = false ) {
	$Order = ShoppOrder();
	if ( false === $item ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: Missing item parameter.",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}

	if ( 0 == $count = count($Order->Cart->contents) ) return true;
	if ( $item < 0 || $item >= $count ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: No such item $item",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}
	$remove = $Order->Cart->remove($item);
	$Order->Cart->changed(true);
	$Order->Cart->totals();
	return $remove;
}

/**
 * shopp_cart_items - get a list of the items in the cart
 *
 * @author John Dillick
 * @since 1.2
 *
 * @return array list of items in the cart
 **/
function shopp_cart_items () {
	return ShoppOrder()->Cart->contents;
}

/**
 * shopp_cart_items_count - get count of items in the cart
 *
 * @author John Dillick
 * @since 1.2
 *
 * @return void Description...
 **/
function shopp_cart_items_count () {
	return count( ShoppOrder()->Cart->contents );
}

/**
 * shopp_cart_item - get an object representing the item in the cart.
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int|string $item the integer index of the item in the cart, string 'recent-cartitem' for last added cart item
 * @return stdClass object with quantity, product id, variant id, and list of addons of the item.
 **/
function shopp_cart_item ( $item = false ) {
	$Order = ShoppOrder();
	if ( false === $item ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: Missing item parameter.",__FUNCTION__,SHOPP_DEBUG_ERR);
	}

	if ( 'recent-cartitem' === $item && $Order->Cart->Added ) return $Order->Cart->Added;

	$items = shopp_cart_items();

	if ( $item < 0 || $item >= count($items) ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: No such item $item",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}
	return $items[$item];

}

/**
 * Empty the contents of the cart
 *
 * @author Jonathan Davis
 * @since 1.2
 *
 * @return void
 **/
function shopp_empty_cart () {
	ShoppOrder()->Cart->clear();
	ShoppOrder()->Cart->totals();
}

/**
 * Apply a promocode to the cart
 *
 * @author Jonathan Davis
 * @since 1.2
 *
 * @param string $code The promotion code to apply
 * @return void
 **/
function shopp_add_cart_promocode ($code = false) {
	if ( false === $code || empty($code) ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: Missing code parameter.",__FUNCTION__,SHOPP_DEBUG_ERR);
	}

	$Cart = ShoppOrder()->Cart;
	$Cart->promocode = esc_attr($code);
	$Cart->changed(true);
	$Cart->totals();
}

// todo: implement shopp_add_cart_item_addon in plugin api
function shopp_add_cart_item_addon ( $item = false, $addon = false ) {
	// $Order = ShoppOrder();
	// if ( false === $item || false === $addon ) {
	// 	if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: item and addon parameter required.",__FUNCTION__,SHOPP_DEBUG_ERR);
	// 	return false;
	// }
	// if ( $item < 0 || $item >= shopp_cart_items_count() ) {
	// 	if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: No such item $item",__FUNCTION__,SHOPP_DEBUG_ERR);
	// 	return false;
	// }
}

// todo: implement shopp_rmv_cart_item_addon in plugin api
function shopp_rmv_cart_item_addon ( $item = false, $addon = false ) {
	// $Order = ShoppOrder();
	// if ( false === $item || false === $addon ) {
	// 	if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: item and addon parameter required.",__FUNCTION__,SHOPP_DEBUG_ERR);
	// 	return false;
	// }
	// if ( $item < 0 || $item >= shopp_cart_items_count() ) {
	// 	if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: No such item $item",__FUNCTION__,SHOPP_DEBUG_ERR);
	// 	return false;
	// }
	// $Item = $Order->Cart->contents[$item];
	// if ( $addon < 0 || $addon >= count( $Item->addons ) ) {
	// 	if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: No such addon $addon on this item.",__FUNCTION__,SHOPP_DEBUG_ERR);
	// 	return false;
	// }
}

// todo: implement shopp_cart_item_addons in plugin api
function shopp_cart_item_addons ($item) {}

// todo: implement shopp_cart_item_addons_count in plugin api
function shopp_cart_item_addons_count ($item) {}

?>