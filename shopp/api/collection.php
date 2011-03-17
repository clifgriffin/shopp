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
 * Registers a smart category
 *
 * @author Jonathan Davis
 * @since 1.1
 *
 * @param string $name Class name of the smart category
 * @return void
 **/
function register_collection ($class) {
	Shopp::add_collection($class);
}


?>