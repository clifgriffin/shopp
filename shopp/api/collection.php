<?php
/**
 * Collection API
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, February 25, 2011
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package Shopp
 * @since 1.2
 * @subpackage Collection
 **/

/**
 * collection
 *
 * @author Jonathan Davis
 * @since 1.2
 * @package shopp
 **/
class Collection {

	/**
	 * collection constructor
	 *
	 * @author Jonathan Davis
	 *
	 * @return void
	 **/
	function __construct () {

	}

} // END class collection

/**
 * Registers a smart category
 *
 * @author Jonathan Davis
 * @since 1.1
 *
 * @param string $name Class name of the smart category
 * @return void
 **/
function register_collection ($class) {
	global $Shopp;
	if (empty($Shopp)) return;
		$Shopp->SmartCategories[] = $name;
}


?>