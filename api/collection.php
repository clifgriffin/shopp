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
 * @since 1.2
 *
 * @param string $name Class name of the smart collection
 * @return void
 **/
function shopp_register_collection ($name) {
	global $Shopp;
	if (empty($Shopp)) return;
	$Shopp->Collections[] = $name;
	$slug = $name::$_slug;

	add_rewrite_tag("%shopp_collection%",'collection/([^/]+)');
	add_permastruct('shopp_collection', Storefront::slug()."/%shopp_collection%", true);
}


function shopp_add_product_category ($product,$category) {}
function shopp_rmv_product_category ($product,$category) {}

function shopp_categories () {}
function shopp_subcategories ($category) {}
function shopp_product_categories ($product) {}
function shopp_category_products ($category) {}

function shopp_catalog_count () {}
function shopp_category_count ($category) {}
function shopp_subcategory_count ($category) {}
function shopp_product_categories_count ($product) {}


?>