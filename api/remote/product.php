<?php
/**
* ShoppProductRemoteAPI - Provides remote API calls to access catalog products
*
* @version 1.0
* @since 1.3
* @package shopp
* @subpackage ShoppProductRemoteAPI
*
**/

/**
 * Provides shopp('Product') theme api functionality
 *
 * @author Jonathan Davis
 * @since 1.3
 * @package remoteapi
 **/
class ShoppProductRemoteAPI {

	static function _register () {
		shopp_add_remoteapi('product', array(__CLASS__,'product'), 'read' );
	}

	static function product ($result,$resource,$options) {
		list($request,$id) = $resource;
		$Product = shopp_product($id);
		return $Product;
	}

}

?>