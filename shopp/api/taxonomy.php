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

/**
 * Registers a new taxonomy into the system
 *
 * @author Jonathan Davis
 * @since 1.2
 *
 * @param string $name The name of the new taxonomy
 * @param array $options An associative array of taxonomy options
 * @return void
 **/
function register_catalog_taxonomy ($name, $options = array()) {
	$Taxonomies =& ShoppTaxonomies();

	$defaults = array(	'_builtin' => false,		// Flag for built-in system taxonomies
						'hierarchical' => false,	// Flag for taxonomies supporting hierarchies
						'rewrite' => true,			// @todo not implemented
						'query_var' => sanitize_title_with_dashes($name),	// @todo not implemented
						'public' => true,			// @todo not implemented
						'edit_ui' => null,			// @todo not implemented
						'labels' => array(),		// @todo not implemented
						'capabilities' => array()	// @todo not implemented
					);
	$options = array_merge($defaults,$options);

	$Taxonomies->add($name,$options);
}

/**
 * Determines if a taxonomy is registered
 *
 * @author Jonathan Davis
 * @since 1.2
 *
 * @param string $name The name of the taxonomy
 * @return boolean True if the taxonomy exists
 **/
function catalog_taxonomy_exists ($name) {
	$Taxonomies =& ShoppTaxonomies();
	return $Taxonomies->exists($name);
}

/**
 * Gets a registered taxonomy entry
 *
 * @author Jonathan Davis
 * @since 1.2
 *
 * @param string $name The taxonomy name
 * @return array An array of taxonomy settings
 **/
function &get_catalog_taxonomy ($name) {
	$Taxonomies =& ShoppTaxonomies();
	return $Taxonomies->get($name);
}

/**
 * Get the reserved ID for a registered taxonomy
 *
 * @author Jonathan Davis
 * @since 1.2
 *
 * @param string $name The taxonomy name
 * @return int The ID of the taxonomy (or boolean false if it fails)
 **/
function get_catalog_taxonomy_id ($name) {
	$Taxonomies =& ShoppTaxonomies();
	return $Taxonomies->get_id($name);
}

?>