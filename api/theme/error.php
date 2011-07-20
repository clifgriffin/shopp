<?php
/**
* ShoppErrorThemeAPI - Provided theme api tags.
*
* @version 1.0
* @since 1.2
* @package shopp
* @subpackage ShoppErrorThemeAPI
*
**/

/**
 * Provides functionality for the shopp('error') tags
 *
 * Support for triggering errors through the Theme API.
 *
 * @author Jonathan Davis, John Dillick
 * @since 1.2
 *
 **/
class ShoppErrorThemeAPI implements ShoppAPI {
	static $register = array(
		'trxn' => 'trxn',
		'auth' => 'auth',
		'addon' => 'addon',
		'comm' => 'comm',
		'stock' => 'stock',
		'admin' => 'admin',
		'db' => 'db',
		'debug' => 'debug'
	);

	static function _apicontext () { return 'error'; }


	/**
	 * _setobject - returns the global context object used in the shopp('error') call
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 **/
	static function _setobject ($Object, $object) {
		if ( strtolower($object) != 'error' ) return $Object; // not mine
		return ShoppErrors();
	}


	function trxn ($result, $options, $O) { if (empty($options)) return false; new ShoppError(key($options),'template_error',SHOPP_TRXN_ERR); }

	function auth ($result, $options, $O) { if (empty($options)) return false; new ShoppError(key($options),'template_error',SHOPP_AUTH_ERR); }

	function addon ($result, $options, $O) { if (empty($options)) return false; new ShoppError(key($options),'template_error',SHOPP_ADDON_ERR); }

	function comm ($result, $options, $O) { if (empty($options)) return false; new ShoppError(key($options),'template_error',SHOPP_COMM_ERR); }

	function stock ($result, $options, $O) { if (empty($options)) return false; new ShoppError(key($options),'template_error',SHOPP_STOCK_ERR); }

	function admin ($result, $options, $O) { if (empty($options)) return false; new ShoppError(key($options),'template_error',SHOPP_ADMIN_ERR); }

	function db ($result, $options, $O) { if (empty($options)) return false; new ShoppError(key($options),'template_error',SHOPP_DB_ERR); }

	function debug ($result, $options, $O) { if (empty($options)) return false; new ShoppError(key($options),'template_error',SHOPP_DEBUG_ERR); }
}

?>