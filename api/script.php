<?php
/**
 * Script API
 *
 * Plugin API function for script management
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, March, 2013
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @since 1.3
 * @subpackage shopp
 **/

defined( 'WPINC' ) || header( 'HTTP/1.1 403' ) & exit; // Prevent direct access

/**
 * Register new JavaScript file.
 */
function shopp_register_script( $handle, $src, $deps = array(), $ver = false, $in_footer = false ) {
	global $ShoppScripts;
	if ( !is_a($ShoppScripts, 'ShoppScripts') )
		$ShoppScripts = new ShoppScripts();

	$ShoppScripts->add( $handle, $src, $deps, $ver );
	if ( $in_footer )
		$ShoppScripts->add_data( $handle, 'group', 1 );
}

function shopp_localize_script( $handle, $object_name, $l10n ) {
	global $ShoppScripts;
	if ( !is_a($ShoppScripts, 'ShoppScripts') )
		return false;

	return $ShoppScripts->localize( $handle, $object_name, $l10n );
}

function shopp_custom_script ($handle, $code) {
	global $ShoppScripts;
	if ( !is_a($ShoppScripts, 'ShoppScripts') )
		return false;

	$code = !empty($ShoppScripts->registered[$handle]->extra['code'])?$ShoppScripts->registered[$handle]->extra['code'].$code:$code;
	return $ShoppScripts->add_data( $handle, 'code', $code );
}

/**
 * Remove a registered script.
 */
function shopp_deregister_script( $handle ) {
	global $ShoppScripts;
	if ( !is_a($ShoppScripts, 'ShoppScripts') )
		$ShoppScripts = new ShoppScripts();

	$ShoppScripts->remove( $handle );
}

/**
 * Enqueues script.
 *
 * Registers the script if src provided (does NOT overwrite) and enqueues.
*/
function shopp_enqueue_script( $handle, $src = false, $deps = array(), $ver = false, $in_footer = false ) {
	global $ShoppScripts;
	if ( !is_a($ShoppScripts, 'ShoppScripts') )
		$ShoppScripts = new ShoppScripts();

	if ( $src ) {
		$_handle = explode('?', $handle);
		$ShoppScripts->add( $_handle[0], $src, $deps, $ver );
		if ( $in_footer )
			$ShoppScripts->add_data( $_handle[0], 'group', 1 );
	}
	$ShoppScripts->enqueue( $handle );
}

/**
 * Check whether script has been added to WordPress Scripts.
 *
 * The values for list defaults to 'queue', which is the same as enqueue for
 * scripts.
 *
 * @param string $handle Handle used to add script.
 * @param string $list Optional, defaults to 'queue'. Others values are 'registered', 'queue', 'done', 'to_do'
 * @return bool
 */
function shopp_script_is( $handle, $list = 'queue' ) {
	global $ShoppScripts;
	if ( !is_a($ShoppScripts, 'ShoppScripts') )
		$ShoppScripts = new ShoppScripts();

	$query = $ShoppScripts->query( $handle, $list );

	if ( is_object( $query ) )
		return true;

	return $query;
}

/**
 * Handle Shopp script dependencies in the WP script queue
 *
 * @author Jonathan Davis
 * @since 1.1
 *
 * @return void
 **/
function shopp_dependencies () {
	global $ShoppScripts,$wp_scripts;
	if ( !is_a($ShoppScripts, 'ShoppScripts') )
		$ShoppScripts = new ShoppScripts();

	foreach ($wp_scripts->queue as $handle) {
		$deps = $wp_scripts->registered[$handle]->deps;
		$shoppdeps = array_intersect($deps,array_keys($ShoppScripts->registered));
		foreach ($shoppdeps as $key => $s_handle) {
			shopp_enqueue_script($s_handle);
			array_splice($deps,$key,1);
		}
		$wp_scripts->registered[$handle]->deps = $deps;
	}
}