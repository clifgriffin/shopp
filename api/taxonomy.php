<?php
/**
 * Taxonomy API
 *
 * @author Jonathan Davis
 * @copyright Ingenesis Limited, February 25, 2011
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package Shopp
 * @since 1.2
 * @subpackage Taxonomy
 **/

function init_shopp_taxonomies () {
	register_catalog_taxonomy('category',array(
		'_builtin' => true,
		'hierarchical' => true,
		'public' => true,
		'editor_ui' => true
	));

	register_catalog_taxonomy('tag',array(
		'_builtin' => true,
		'editor_ui' => true
	));

	register_catalog_taxonomy('promo',array(
		'_builtin' => true,
		'editor_ui' => false
	));

}
add_action( 'shopp_init', 'init_shopp_taxonomies', 0 ); // highest priority


function register_catalog_taxonomy ($name, $options = array()) {
	$Taxonomies =& ShoppTaxonomies();

	$defaults = array(	'_builtin' => false,
						'hierarchical' => false,
						'rewrite' => true,
						'query_var' => sanitize_title_with_dashes($name),
						'public' => true,
						'edit_ui' => null,
						'labels' => array(),
						'capabilities' => array(),
					);
	$options = array_merge($defaults,$options);

	$Taxonomies->add($name,$options);
}

function catalog_taxonomy_exists () {
	$Taxonomies =& ShoppTaxonomies();
	return $Taxonomies->exists($name);
}

function &get_catalog_taxonomy ($name) {
	$Taxonomies =& ShoppTaxonomies();
	return $Taxonomies->get($name);
}

function get_catalog_taxonomy_id ($name) {
	$Taxonomies =& ShoppTaxonomies();
	return $Taxonomies->get_id($name);
}

?>