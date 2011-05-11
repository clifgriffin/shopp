<?php

add_filter('shoppapi_error_trxn', array('ShoppErrorAPI', 'trxn'),10, 3);
add_filter('shoppapi_error_auth', array('ShoppErrorAPI', 'auth'),10, 3);
add_filter('shoppapi_error_addon', array('ShoppErrorAPI', 'addon'),10, 3);
add_filter('shoppapi_error_comm', array('ShoppErrorAPI', 'comm'),10, 3);
add_filter('shoppapi_error_stock', array('ShoppErrorAPI', 'stock'),10, 3);
add_filter('shoppapi_error_admin', array('ShoppErrorAPI', 'admin'),10, 3);
add_filter('shoppapi_error_db', array('ShoppErrorAPI', 'db'),10, 3);
add_filter('shoppapi_error_debug', array('ShoppErrorAPI', 'debug'),10, 3);

/**
 * Provides functionality for the shopp('error') tags
 *
 * Support for triggering errors through the Theme API.
 *
 * @author Jonathan Davis, John Dillick
 * @since 1.2
 *
 **/
class ShoppErrorAPI {
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