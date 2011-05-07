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
 * Registers a smart collection of products
 *
 * @author Jonathan Davis
 * @since 1.1
 *
 * @param string $name Class name of the smart collection
 * @return void
 **/
function register_collection ($name) {
	global $Shopp;
	if (empty($Shopp)) return;
	$Shopp->Collections[] = $name;
	$slug = $name::$_slug;

	add_rewrite_tag("%shopp_collection%",'collection/([^/]+)');
	add_permastruct('shopp_collection', SHOPP_CATALOG_SLUG."/%shopp_collection%", true);

}


?>