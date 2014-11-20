<?php
/**
 * Plugin Name: Shopp Services
 * Plugin URI: http://shopplugin.com
 * Description: Provides performance improvements for Shopp asset services
 * Version: 1.0
 * Author: Ingenesis Limited
 * Author URI: http://ingenesis.net
 * Requires at least: 3.5
 * Tested up to: 4.0
 *
 *    Portions created by Ingenesis Limited are Copyright © 2014 by Ingenesis Limited
 *
 *    This file is part of Shopp.
 *
 *    Shopp is free software: you can redistribute it and/or modify
 *    it under the terms of the GNU General Public License as published by
 *    the Free Software Foundation, either version 3 of the License, or
 *    (at your option) any later version.
 *
 *    Shopp is distributed in the hope that it will be useful,
 *    but WITHOUT ANY WARRANTY; without even the implied warranty of
 *    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *    GNU General Public License for more details.
 *
 *    You should have received a copy of the GNU General Public License
 *    along with Shopp.  If not, see <http://www.gnu.org/licenses/>.
 *
 **/

// Prevent direct access
defined( 'WPINC' ) || header( 'HTTP/1.1 403' ) & exit;

// Image Server request handling
if ( isset($_GET['siid']) || 1 == preg_match('{^/.+?/images/\d+/.*$}', $_SERVER['REQUEST_URI']) )
	return shopp_service_load();

// Script Server request handling
if ( isset($_GET['load']) && 1 == preg_match('/shopp-scripts.js/', $_SERVER['REQUEST_URI']) )
	return shopp_service_load();

function shopp_service_load () {
	if ( ! defined('SHOPP_SERVICE') )
		define('SHOPP_SERVICE', true);

	add_filter( 'option_active_plugins', 'shopp_services_exclude_plugins');
	add_filter( 'site_option_active_sitewide_plugins', 'shopp_services_exclude_plugins');
}

function shopp_services_exclude_plugins ( array $plugins = array() ) {
	$load = (array) get_option('shopp_services_plugins');
	foreach ( $plugins as $i => $name ) {
		if ( false !== strpos( $name, 'Shopp.php' ) || isset($load[ $name ]) ) continue;
		unset($plugins[ $i ]);
	}
	return $plugins;
}