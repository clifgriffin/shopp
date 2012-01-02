<?php
/*
Plugin Name: Shopp
Version: 1.1.9.1
Description: Bolt-on ecommerce solution for WordPress
Plugin URI: http://shopplugin.net
Author: Ingenesis Limited
Author URI: http://ingenesis.net

	Portions created by Ingenesis Limited are Copyright © 2008-2011 by Ingenesis Limited

	This file is part of Shopp.

	Shopp is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	Shopp is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with Shopp.  If not, see <http://www.gnu.org/licenses/>.

*/

define('SHOPP_VERSION','1.1.9.1');
define('SHOPP_REVISION','$Rev$');
define('SHOPP_GATEWAY_USERAGENT','WordPress Shopp Plugin/'.SHOPP_VERSION);
define('SHOPP_HOME','https://shopplugin.net/');
define('SHOPP_CUSTOMERS','http://customers.shopplugin.net/');
define('SHOPP_DOCS','http://docs.shopplugin.net/');

require("core/functions.php");
require("core/legacy.php");

shopp_prereqs();
shopp_timezone();

require_once("core/DB.php");
require_once("core/model/Settings.php");

// Serve images and bypass loading all of Shopp
if (isset($_GET['siid']) || preg_match('/images\/\d+/',$_SERVER['REQUEST_URI']))
	require("core/image.php");

if (isset($_GET['sjsl']))
	require("core/scripts.php");

// Load super controllers
require("core/flow/Flow.php");
require("core/flow/Storefront.php");
require("core/flow/Login.php");
require('core/flow/Scripts.php');

// Load frameworks & Shopp-managed data model objects
require("core/model/Modules.php");
require("core/model/Gateway.php");
require("core/model/Lookup.php");
require("core/model/Shopping.php");
require("core/model/Error.php");
require("core/model/Order.php");
require("core/model/Cart.php");
require("core/model/Meta.php");
require("core/model/Asset.php");
require("core/model/Catalog.php");
require("core/model/Purchase.php");
require("core/model/Customer.php");

// Start up the core
$Shopp = new Shopp();
do_action('shopp_loaded');

/**
 * Shopp class
 *
 * @author Jonathan Davis
 * @package shopp
 * @since 1.0
 **/
class Shopp {
	var $Settings;			// Shopp settings registry
	var $Flow;				// Controller routing
	var $Catalog;			// The main catalog
	var $Category;			// Current category
	var $Product;			// Current product
	var $Purchase; 			// Currently requested order receipt
	var $Shopping; 			// The shopping session
	var $Errors;			// Error system
	var $Order;				// The current session Order
	var $Promotions;		// Active promotions registry
	var $SmartCategories;	// Smart Categories registry
	var $Gateways;			// Gateway modules
	var $Shipping;			// Shipping modules
	var $Storage;			// Storage engine modules

	var $path;		  		// File ystem path to the plugin
	var $file;		  		// Base file name for the plugin (this file)
	var $directory;	  		// The parent directory name
	var $uri;		  		// The URI fragment to the plugin
	var $siteurl;	  		// The full site URL
	var $wpadminurl;  		// The admin URL for the site

	var $_debug;

	function Shopp () {
		if (WP_DEBUG) {
			$this->_debug = new StdClass();
			if (function_exists('memory_get_peak_usage'))
				$this->_debug->memory = memory_get_peak_usage(true);
			if (function_exists('memory_get_usage'))
				$this->_debug->memory = memory_get_usage(true);
		}

		// Determine system and URI paths

		$this->path = sanitize_path(dirname(__FILE__));
		$this->file = basename(__FILE__);
		$this->directory = basename($this->path);

		$languages_path = array($this->directory,'lang');
		load_plugin_textdomain('Shopp',false,sanitize_path(join('/',$languages_path)));

		$this->uri = WP_PLUGIN_URL."/".$this->directory;
		$this->siteurl = get_bloginfo('url');
		$this->wpadminurl = admin_url();

		if ($this->secure = is_shopp_secure()) {
			$this->uri = str_replace('http://','https://',$this->uri);
			$this->siteurl = str_replace('http://','https://',$this->siteurl);
			$this->wpadminurl = str_replace('http://','https://',$this->wpadminurl);
		}

		// Initialize settings & macros

		$this->Settings = new Settings();

		if (!defined('BR')) define('BR','<br />');

		// Overrideable macros
		if (!defined('SHOPP_NOSSL')) define('SHOPP_NOSSL',false);
		if (!defined('SHOPP_PREPAYMENT_DOWNLOADS')) define('SHOPP_PREPAYMENT_DOWNLOADS',false);
		if (!defined('SHOPP_SESSION_TIMEOUT')) define('SHOPP_SESSION_TIMEOUT',7200);
		if (!defined('SHOPP_QUERY_DEBUG')) define('SHOPP_QUERY_DEBUG',false);
		if (!defined('SHOPP_GATEWAY_TIMEOUT')) define('SHOPP_GATEWAY_TIMEOUT',10);
		if (!defined('SHOPP_SHIPPING_TIMEOUT')) define('SHOPP_SHIPPING_TIMEOUT',10);
		if (!defined('SHOPP_TEMP_PATH')) define('SHOPP_TEMP_PATH',sys_get_temp_dir());

		// Settings & Paths
		define("SHOPP_DEBUG",($this->Settings->get('error_logging') == 2048));
		define("SHOPP_PATH",$this->path);
		define("SHOPP_PLUGINURI",$this->uri);
		define("SHOPP_PLUGINFILE",$this->directory."/".$this->file);

		define("SHOPP_ADMIN_DIR","/core/ui");
		define("SHOPP_ADMIN_PATH",SHOPP_PATH.SHOPP_ADMIN_DIR);
		define("SHOPP_ADMIN_URI",SHOPP_PLUGINURI.SHOPP_ADMIN_DIR);
		define("SHOPP_FLOW_PATH",SHOPP_PATH."/core/flow");
		define("SHOPP_MODEL_PATH",SHOPP_PATH."/core/model");
		define("SHOPP_GATEWAYS",SHOPP_PATH."/gateways");
		define("SHOPP_SHIPPING",SHOPP_PATH."/shipping");
		define("SHOPP_STORAGE",SHOPP_PATH."/storage");
		define("SHOPP_DBSCHEMA",SHOPP_MODEL_PATH."/schema.sql");

		define("SHOPP_TEMPLATES",($this->Settings->get('theme_templates') != "off"
			&& is_dir(sanitize_path(get_stylesheet_directory().'/shopp')))?
					  sanitize_path(get_stylesheet_directory().'/shopp'):
					  SHOPP_PATH.'/'."templates");
		define("SHOPP_TEMPLATES_URI",($this->Settings->get('theme_templates') != "off"
			&& is_dir(sanitize_path(get_stylesheet_directory().'/shopp')))?
					  sanitize_path(get_bloginfo('stylesheet_directory')."/shopp"):
					  SHOPP_PLUGINURI."/templates");

		define("SHOPP_PRETTYURLS",(get_option('permalink_structure') == "")?false:true);
		define("SHOPP_PERMALINKS",SHOPP_PRETTYURLS); // Deprecated

		// Initialize application control processing

		$this->Flow = new Flow();
		$this->Shopping = new Shopping();

		add_action('init', array(&$this,'init'));

		// Plugin management
        add_action('after_plugin_row_'.SHOPP_PLUGINFILE, array(&$this, 'status'),10,2);
        add_action('install_plugins_pre_plugin-information', array(&$this, 'changelog'));
        add_action('shopp_check_updates', array(&$this, 'updates'));
		add_action('shopp_init',array(&$this, 'loaded'));

		// Theme integration
		add_action('widgets_init', array(&$this, 'widgets'));
		add_filter('wp_list_pages',array(&$this,'secure_links'));
		add_filter('rewrite_rules_array',array(&$this,'rewrites'));
		add_action('admin_head-options-reading.php',array(&$this,'pages_index'));
		add_action('generate_rewrite_rules',array(&$this,'pages_index'));
		add_action('save_post', array(&$this, 'pages_index'),10,2);
		add_action('shopp_reindex_pages', array(&$this, 'pages_index'));

		add_filter('query_vars', array(&$this,'queryvars'));

		if (!wp_next_scheduled('shopp_check_updates'))
			wp_schedule_event(time(),'twicedaily','shopp_check_updates');

	}

	/**
	 * Initializes the Shopp runtime environment
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @return void
	 **/
	function init () {

		$this->Errors = new ShoppErrors($this->Settings->get('error_logging'));
		$this->Order = ShoppingObject::__new('Order');
		$this->Promotions = ShoppingObject::__new('CartPromotions');
		$this->Gateways = new GatewayModules();
		$this->Shipping = new ShippingModules();
		$this->Storage = new StorageEngines();
		$this->SmartCategories = array();

		$this->ErrorLog = new ShoppErrorLogging($this->Settings->get('error_logging'));
		$this->ErrorNotify = new ShoppErrorNotification($this->Settings->get('merchant_email'),
									$this->Settings->get('error_notifications'));

		if (!$this->Shopping->handlers) new ShoppError(__('The Cart session handlers could not be initialized because the session was started by the active theme or an active plugin before Shopp could establish its session handlers. The cart will not function.','Shopp'),'shopp_cart_handlers',SHOPP_ADMIN_ERR);
		if (SHOPP_DEBUG && $this->Shopping->handlers) new ShoppError('Session handlers initialized successfully.','shopp_cart_handlers',SHOPP_DEBUG_ERR);
		if (SHOPP_DEBUG) new ShoppError('Session started.','shopp_session_debug',SHOPP_DEBUG_ERR);

		global $pagenow;
		if (defined('WP_ADMIN')
			&& $pagenow == "plugins.php"
			&& $_GET['action'] != 'deactivate') $this->updates();

		new Login();
		do_action('shopp_init');
	}


	/**
	 * Initializes theme widgets
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @return void
	 **/
	function widgets () {
		global $wp_version;
		include('core/ui/widgets/account.php');
		include('core/ui/widgets/cart.php');
		include('core/ui/widgets/categories.php');
		include('core/ui/widgets/section.php');
		include('core/ui/widgets/tagcloud.php');
		include('core/ui/widgets/facetedmenu.php');
		include('core/ui/widgets/product.php');
		include('core/ui/widgets/search.php');
	}

	/**
	 * Relocates the Shopp-installed pages and indexes any changes
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @param boolean $update (optional) Used in a filter callback context
	 * @param boolean $updates (optional) Used in an action callback context
	 * @return boolean The update status
	 **/
	function pages_index ($update=false,$updates=false) {
		global $wpdb;
		$pages = $this->Settings->get('pages');
		$pages = shopp_locate_pages();
		$this->Settings->save('pages',$pages);
		if ($update) return $update;
	}

	/**
	 * Adds Shopp-specific pretty-url rewrite rules to WordPress rewrite rules
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @param array $wp_rewrite_rules An array of existing WordPress rewrite rules
	 * @return array Modified rewrite rules
	 **/
	function rewrites ($wp_rewrite_rules) {
		$this->pages_index(true);
		$pages = $this->Settings->get('pages');
		if (!$pages) return $wp_rewrite_rules;

		// Collect Shopp page URIs and IDs
		$uris = array();
		$builtins = array();
		foreach ($pages as $page => $data) {
			if ($page == "catalog")	$catalogid = $data['id'];
			$uris[$page] = $data['uri'];
			$builtins[] = $data['id'];
		}
		extract($uris);

		// Find sub-pages of the main catalog page so we can add rewrite exclusions
		$pagenames = array();
		$subpages = get_pages(array('child_of'=>$catalogid,'exclude'=>join(',',$builtins)));
		foreach ($subpages as $page) $pagenames[] = $page->post_name;

		// Build the rewrite rules for Shopp
		$rules = array(
			$cart.'?$' => 'index.php?pagename='.shopp_pagename($cart),
			$account.'?$' => 'index.php?pagename='.shopp_pagename($account),
			$checkout.'?$' => 'index.php?pagename='.shopp_pagename($checkout).'&shopp_proc=checkout',

			/* Exclude sub-pages of the main storefront page (catalog page) */
			'('.$catalog.'/('.join('|',$pagenames).'))?$' => 'index.php?pagename=$matches[1]',

			/* catalog */
			$catalog.'/feed/?$' // Catalog feed
				=> 'index.php?src=category_rss&shopp_category=new',
			$catalog.'/(thanks|receipt)/?$' // Thanks page handling
				=> 'index.php?pagename='.shopp_pagename($checkout).'&shopp_proc=thanks',
			$catalog.'/confirm-order/?$' // Confirm order page handling
				=> 'index.php?pagename='.shopp_pagename($checkout).'&shopp_proc=confirm-order',
			$catalog.'/download/([a-f0-9]{40})/?$' // Download handling
				=> 'index.php?pagename='.shopp_pagename($account).'&src=download&shopp_download=$matches[1]',
			$catalog.'/images/(\d+)/?.*?$' // Image handling
				=> 'index.php?siid=$matches[1]',

			/* catalog/category/category-slug */
			$catalog.'/category/(.+?)/feed/?$' // Category feeds
				=> 'index.php?src=category_rss&shopp_category=$matches[1]',
			$catalog.'/category/(.+?)/page/?(0\-9|[A-Z0-9]{1,})/?$' // Category pagination
				=> 'index.php?pagename='.shopp_pagename($catalog).'&shopp_category=$matches[1]&paged=$matches[2]',
			$catalog.'/category/(.+)/?$' // Category permalink
				=> 'index.php?pagename='.shopp_pagename($catalog).'&shopp_category=$matches[1]',

			/* catalog/tags */
			$catalog.'/tag/(.+?)/feed/?$' // Tag feeds
				=> 'index.php?src=category_rss&shopp_tag=$matches[1]',
			$catalog.'/tag/(.+?)/page/?([0-9]{1,})/?$' // Tag pagination
				=> 'index.php?pagename='.shopp_pagename($catalog).'&shopp_tag=$matches[1]&paged=$matches[2]',
			$catalog.'/tag/(.+)/?$' // Tag permalink
				=> 'index.php?pagename='.shopp_pagename($catalog).'&shopp_tag=$matches[1]',

			/* catalog/product-slug */
			$catalog.'/(.+)/?$' => 'index.php?pagename='.shopp_pagename($catalog).'&shopp_product=$matches[1]'

		);

		// Add mod_rewrite rule for image server for low-resource, speedy delivery
		$corepath = array(PLUGINDIR,$this->directory,'core');
		add_rewrite_rule('.*'.$catalog.'/images/(\d+)/?\??(.*)$',join('/',$corepath).'/image.php?siid=$1&$2');

		return $rules + $wp_rewrite_rules;
	}

	/**
	 * Registers the query variables used by Shopp
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @param array $vars The current list of handled WordPress query vars
	 * @return array Augmented list of query vars including Shopp vars
	 **/
	function queryvars ($vars) {
		$vars[] = 'shopp_proc';			// Shopp process parameter
		$vars[] = 'shopp_category';		// Category slug or id
		$vars[] = 'shopp_tag';			// Tag slug
		$vars[] = 'shopp_pid';			// Product ID
		$vars[] = 'shopp_product';		// Product slug
		$vars[] = 'shopp_download';		// Download key
		$vars[] = 'shopp_orderby';		// Product sort order (category view)
		$vars[] = 'src';				// Shopp resource
		$vars[] = 'siid';				// Shopp image id
		$vars[] = 'catalog';			// Catalog flag
		$vars[] = 'acct';				// Account process

		return $vars;
	}

	/**
	 * Reset the shopping session
	 *
	 * Controls the cart to allocate a new session ID and transparently
	 * move existing session data to the new session ID.
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @return boolean True on success
	 **/
	function resession ($session=false) {
		// commit current session
		session_write_close();
		$this->Shopping->handling(); // Workaround for PHP 5.2 bug #32330

		if ($session) { // loading session
			$this->Shopping->session = session_id($session); // session_id while session is closed
			$this->Shopping = new Shopping();
			session_start();
			return true;
		}

		session_start();
		session_regenerate_id(); // Generate new ID while session is started

		// Ensure we have the newest session ID
		$this->Shopping->session = session_id();

		// Commit the session and restart
		session_write_close();
		$this->Shopping->handling(); // Workaround for PHP 5.2 bug #32330
		session_start();

		do_action('shopp_reset_session'); // Deprecated
		do_action('shopp_resession');
		return true;

	}

	/**
	 * @deprecated {@see shoppurl()}
	 *
	 **/
	function link ($target,$secure=false) {
		return shoppurl(false,$target,$secure);
	}

	/**
	 * Provides the JavaScript environment with Shopp settings
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	function settingsjs () {
		$baseop = $this->Settings->get('base_operations');

		$currency = array();
		if (isset($baseop['currency'])
			&& isset($baseop['currency']['format'])
			&& isset($baseop['currency']['format']['decimals'])
			&& !empty($baseop['currency']['format']['decimals'])
		) {
			$currency = array(
				// Currency formatting
				'cp' => $baseop['currency']['format']['cpos'],
				'c' => $baseop['currency']['format']['currency'],
				'p' => $baseop['currency']['format']['precision'],
				't' => $baseop['currency']['format']['thousands'],
				'd' => $baseop['currency']['format']['decimals'],
				'g' => is_array($baseop['currency']['format']['grouping'])?join(',',$baseop['currency']['format']['grouping']):$baseop['currency']['format']['grouping'],
			);
		}

		$base = array(
			'nocache' => is_shopp_page('account'),

			// Validation alerts
			'REQUIRED_FIELD' => __('Your %s is required.','Shopp'),
			'INVALID_EMAIL' => __('The e-mail address you provided does not appear to be a valid address.','Shopp'),
			'MIN_LENGTH' => __('The %s you entered is too short. It must be at least %d characters long.','Shopp'),
			'PASSWORD_MISMATCH' => __('The passwords you entered do not match. They must match in order to confirm you are correctly entering the password you want to use.','Shopp'),
			'REQUIRED_CHECKBOX' => __('%s must be checked before you can proceed.','Shopp')
		);

		$checkout = array();
		if (shopp_script_is('checkout')) {
			$checkout = array(
				'ajaxurl' => admin_url('admin-ajax.php'),

				// Alerts
				'LOGIN_NAME_REQUIRED' => __('You did not enter a login.','Shopp'),
				'LOGIN_PASSWORD_REQUIRED' => __('You did not enter a password to login with.','Shopp'),
			);
		}

		// Admin only
		if (defined('WP_ADMIN'))
			$base['UNSAVED_CHANGES_WARNING'] = __('There are unsaved changes that will be lost if you continue.','Shopp');

		$calendar = array();
		if (shopp_script_is('calendar')) {
			$calendar = array(
				// Month names
				'month_jan' => __('January','Shopp'),
				'month_feb' => __('February','Shopp'),
				'month_mar' => __('March','Shopp'),
				'month_apr' => __('April','Shopp'),
				'month_may' => __('May','Shopp'),
				'month_jun' => __('June','Shopp'),
				'month_jul' => __('July','Shopp'),
				'month_aug' => __('August','Shopp'),
				'month_sep' => __('September','Shopp'),
				'month_oct' => __('October','Shopp'),
				'month_nov' => __('November','Shopp'),
				'month_dec' => __('December','Shopp'),

				// Weekday names
				'weekday_sun' => __('Sun','Shopp'),
				'weekday_mon' => __('Mon','Shopp'),
				'weekday_tue' => __('Tue','Shopp'),
				'weekday_wed' => __('Wed','Shopp'),
				'weekday_thu' => __('Thu','Shopp'),
				'weekday_fri' => __('Fri','Shopp'),
				'weekday_sat' => __('Sat','Shopp')
			);
		}


		$defaults = apply_filters('shopp_js_settings',array_merge($currency,$base,$checkout,$calendar));
		shopp_localize_script('shopp','sjss',$defaults);
	}

	/**
	 * Filters the WP page list transforming unsecured URLs to secure URLs
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	function secure_links ($linklist) {
		if (!$this->Gateways->secure) return $linklist;
		$hrefs = array(
			'checkout' => shoppurl(false,'checkout'),
			'account' => shoppurl(false,'account')
		);
		if (empty($this->Gateways->active)) return str_replace($hrefs['checkout'],shoppurl(false,'cart'),$linklist);

		foreach ($hrefs as $href) {
			$secure_href = str_replace("http://","https://",$href);
			$linklist = str_replace($href,$secure_href,$linklist);
		}
		return $linklist;
	}

	/**
	 * Registers a smart category
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param string $name Class name of the smart category
	 * @return void
	 **/
	function add_smartcategory ($name) {
		global $Shopp;
		if (empty($Shopp)) return;
			$Shopp->SmartCategories[] = $name;
	}

	/**
	 * Communicates with the Shopp update service server
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param array $request (optional) A list of request variables to send
	 * @param array $data (optional) A list of data variables to send
	 * @param array $options (optional)
	 * @return string The response from the server
	 **/
	function callhome ($request=array(),$data=array(),$options=array()) {
		$query = http_build_query(array_merge(array('ver'=>'1.1'),$request),'','&');
		$data = http_build_query($data,'','&');

		$connection = curl_init();
		curl_setopt($connection, CURLOPT_URL, SHOPP_HOME."?".$query);
		curl_setopt($connection, CURLOPT_USERAGENT, SHOPP_GATEWAY_USERAGENT);
		curl_setopt($connection, CURLOPT_HEADER, 0);
		curl_setopt($connection, CURLOPT_POST, 1);
		curl_setopt($connection, CURLOPT_POSTFIELDS, $data);
		curl_setopt($connection, CURLOPT_TIMEOUT, 20);
		curl_setopt($connection, CURLOPT_RETURNTRANSFER, 1);

		if (!(ini_get("safe_mode") || ini_get("open_basedir")))
			curl_setopt($connection, CURLOPT_FOLLOWLOCATION,1);

		// Added to handle SSL timeout issues
		// Maybe if a timeout occurs the connection should be
		// re-attempted with this option for better overall performance
		curl_setopt($connection, CURLOPT_FRESH_CONNECT, 1);

		$result = curl_exec($connection);
		if ($error = curl_error($connection)) {
			if(SHOPP_DEBUG) new ShoppError("cURL error [".curl_errno($connection)."]: ".$error,false,SHOPP_DEBUG_ERR);

			// Attempt HTTP connection
			curl_setopt($connection, CURLOPT_URL, str_replace('https://', 'http://', SHOPP_HOME)."?".$query);
			$result = curl_exec($connection);
			if ($error = curl_error($connection)) {
				if(SHOPP_DEBUG) new ShoppError("cURL error [".curl_errno($connection)."]: ".$error,false,SHOPP_DEBUG_ERR);
			}
		}

		curl_close ($connection);

		return $result;
	}

	/**
	 * Checks for available updates
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return array List of available updates
	 **/
	function updates () {
		$updates = new StdClass();

		$addons = array_merge(
			$this->Gateways->checksums(),
			$this->Shipping->checksums(),
			$this->Storage->checksums()
		);

		$request = array("ShoppServerRequest" => "update-check");
		$data = array(
			'core' => SHOPP_VERSION,
			'addons' => join("-",$addons),
			'wp' => get_bloginfo('version')
		);

		$response = $this->callhome($request,$data);
		if ($response == '-1') return; // Bad response, bail
		$response = unserialize($response);

		unset($updates->response);

		if (isset($response->addons)) {
			$updates->response[SHOPP_PLUGINFILE.'/addons'] = $response->addons;
			unset($response->addons);
		}

		if (isset($response->id))
			$updates->response[SHOPP_PLUGINFILE] = $response;

		if (function_exists('get_site_transient')) $plugin_updates = get_site_transient('update_plugins');
		else $plugin_updates = get_transient('update_plugins');

		if (isset($updates->response)) {
			$this->Settings->save('updates',$updates);

			// Add Shopp to the WP plugin update notification count
			$plugin_updates->response[SHOPP_PLUGINFILE] = true;

		} else unset($plugin_updates->response[SHOPP_PLUGINFILE]); // No updates, remove Shopp from the plugin update count

		if (function_exists('set_site_transient')) set_site_transient('update_plugins',$plugin_updates);
		else set_transient('update_plugins',$plugin_updates);

		return $updates;
	}

	/**
	 * Loads the change log for an available update
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	function changelog () {
		if($_REQUEST["plugin"] != "shopp") return;

		$request = array("ShoppServerRequest" => "changelog");
		$data = array(
		);
		$response = $this->callhome($request,$data);

		echo '<html><head>';
		echo '<link rel="stylesheet" href="'.admin_url().'/css/install.css" type="text/css" />';
		echo '<link rel="stylesheet" href="'.SHOPP_ADMIN_URI.'/styles/admin.css" type="text/css" />';
		echo '</head>';
		echo '<body id="error-page" class="shopp-update">';
		echo $response;
		echo "</body>";
		echo '</html>';
		exit();
	}

	/**
	 * Reports on the availability of new updates and the update key
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	function status () {
		$updates = $this->Settings->get('updates');
		$key = $this->Settings->get('updatekey');

		$activated = isset($key[0])?($key[0] == '1'):false;
		$core = isset($updates->response[SHOPP_PLUGINFILE])?$updates->response[SHOPP_PLUGINFILE]:false;
		$addons = isset($updates->response[SHOPP_PLUGINFILE.'/addons'])?$updates->response[SHOPP_PLUGINFILE.'/addons']:false;

		if (!empty($core)) { // Core update available
			$plugin_name = 'Shopp';
			$details_url = admin_url('plugin-install.php?tab=plugin-information&plugin=' . $core->slug . '&TB_iframe=true&width=600&height=800');
			$update_url = wp_nonce_url('update.php?action=shopp&plugin='.SHOPP_PLUGINFILE,'upgrade-plugin_shopp');

			if (!$activated) { // Key not active
				$update_url = SHOPP_HOME."store/";
				$message = sprintf(__('There is a new version of %1$s available, but your %1$s key has not been activated. No automatic upgrade available. <a href="%2$s" class="thickbox" title="%3$s">View version %4$s details</a> or <a href="%4$s">purchase a Shopp key</a> to get access to automatic updates and official support services.','Shopp'),$plugin_name,$details_url,esc_attr($plugin_name),$core->new_version,$update_url);
				$this->Settings->save('updates',false);
			} else $message = sprintf(__('There is a new version of %1$s available. <a href="%2$s" class="thickbox" title="%3$s">View version %4$s details</a> or <a href="%5$s">upgrade automatically</a>.'),$plugin_name,$details_url,esc_attr($plugin_name),$core->new_version,$update_url);

			echo '<tr class="plugin-update-tr"><td colspan="3" class="plugin-update"><div class="update-message">'.$message.'</div></td></tr>';

			return;
		}

		if (!$activated) { // No update availableKey not active
			$message = sprintf(__('Your Shopp key has not been activated. Feel free to <a href="%1$s">purchase a Shopp key</a> to get access to automatic updates and official support services.','Shopp'),SHOPP_HOME."store/");
			echo '<tr class="plugin-update-tr"><td colspan="3" class="plugin-update"><div class="update-message">'.$message.'</div></td></tr>';
			$this->Settings->save('updates',false);
			return;
		}

        if ($addons) {
			// Addon update messages
			foreach ($addons as $addon) {
				$message = sprintf(__('There is a new version of the %s add-on available. <a href="%s">Upgrade automatically</a> to version %s','Shopp'),$addon->name,wp_nonce_url('update.php?action=shopp&addon=' . $addon->slug.'&type='.$addon->type, 'upgrade-shopp-addon_' . $addon->slug),$addon->new_version);
				echo '<tr class="plugin-update-tr"><td colspan="3" class="plugin-update"><div class="update-message">'.$message.'</div></td></tr>';

			}
		}

	}

	/**
	 * Detect if this Shopp installation needs maintenance
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return boolean
	 **/
	function maintenance () {
		// Settings unavailable
		if (!$this->Settings->available || !$this->Settings->get('shopp_setup') != "completed")
			return false;

		$this->Settings->save('maintenance','on');
		return true;
	}

} // END class Shopp

/**
 * Defines the shopp() 'tag' handler for complete template customization
 *
 * Appropriately routes tag calls to the tag handler for the requested object.
 *
 * @param $object The object to get the tag property from
 * @param $property The property of the object to get/output
 * @param $options Custom options for the property result in query form
 *                   (option1=value&option2=value&...) or alternatively as an associative array
 */
function shopp () {
	global $Shopp;
	$args = func_get_args();

	$object = strtolower($args[0]);
	$property = strtolower($args[1]);
	$options = array();

	if (isset($args[2])) {
		if (is_array($args[2]) && !empty($args[2])) {
			// handle associative array for options
			foreach(array_keys($args[2]) as $key)
				$options[strtolower($key)] = $args[2][$key];
		} else {
			// regular url-compatible arguments
			$paramsets = explode("&",$args[2]);
			foreach ((array)$paramsets as $paramset) {
				if (empty($paramset)) continue;
				$key = $paramset;
				$value = "";
				if (strpos($paramset,"=") !== false)
					list($key,$value) = explode("=",$paramset);
				$options[strtolower($key)] = $value;
			}
		}
	}

	$Object = false; $result = false;
	switch (strtolower($object)) {
		case "cart": if (isset($Shopp->Order->Cart)) $Object =& $Shopp->Order->Cart; break;
		case "cartitem": if (isset($Shopp->Order->Cart)) $Object =& $Shopp->Order->Cart; break;
		case "shipping": if (isset($Shopp->Order->Cart)) $Object =& $Shopp->Order->Cart; break;
		case "category": if (isset($Shopp->Category)) $Object =& $Shopp->Category; break;
		case "subcategory": if (isset($Shopp->Category->child)) $Object =& $Shopp->Category->child; break;
		case "catalog": if (isset($Shopp->Catalog)) $Object =& $Shopp->Catalog; break;
		case "product": if (isset($Shopp->Product)) $Object =& $Shopp->Product; break;
		case "checkout": if (isset($Shopp->Order)) $Object =& $Shopp->Order; break;
		case "purchase": if (isset($Shopp->Purchase)) $Object =& $Shopp->Purchase; break;
		case "customer": if (isset($Shopp->Order->Customer)) $Object =& $Shopp->Order->Customer; break;
		case "error": if (isset($Shopp->Errors)) $Object =& $Shopp->Errors; break;
		default: $Object = apply_filters('shopp_tag_domain',$Object,$object);
	}

	if ('has-context' == $property) return ($Object);

	if (!$Object) new ShoppError("The shopp('$object') tag cannot be used in this context because the object responsible for handling it doesn't exist.",'shopp_tag_error',SHOPP_ADMIN_ERR);
	else {
		switch (strtolower($object)) {
			case "cartitem": $result = $Object->itemtag($property,$options); break;
			case "shipping": $result = $Object->shippingtag($property,$options); break;
			default: $result = $Object->tag($property,$options); break;
		}
	}

	// Provide a filter hook for every template tag, includes passed options and the relevant Object as parameters
	$result = apply_filters('shopp_tag_'.strtolower($object).'_'.strtolower($property),$result,$options,$Object);

	// Integration filter hook for multilingual plugins
	$result = apply_filters('shopp_ml_t',$result,$options,$property,$Object);

	// Force boolean result
	if (isset($options['is'])) {
		if (value_is_true($options['is'])) {
			if ($result) return true;
		} else {
			if ($result == false) return true;
		}
		return false;
	}

	// Always return a boolean if the result is boolean
	if (is_bool($result)) return $result;

	// Return the result instead of outputting it
	if ((isset($options['return']) && value_is_true($options['return'])) ||
			isset($options['echo']) && !value_is_true($options['echo']))
		return $result;

	// Output the result
	if (is_scalar($result)) echo $result;
	else return $result;
	return true;
}

?>