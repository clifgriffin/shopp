<?php
/*
Plugin Name: Shopp
Version: 1.1 dev
Description: Bolt-on ecommerce solution for WordPress
Plugin URI: http://shopplugin.net
Author: Ingenesis Limited
Author URI: http://ingenesis.net

	Portions created by Ingenesis Limited are Copyright © 2008-2010 by Ingenesis Limited

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

define('SHOPP_VERSION','1.1 dev');
define('SHOPP_REVISION','$Rev$');
define('SHOPP_GATEWAY_USERAGENT','WordPress Shopp Plugin/'.SHOPP_VERSION);
define('SHOPP_HOME','http://shopplugin.net/');
define('SHOPP_DOCS','http://docs.shopplugin.net/');

require("core/functions.php");
require("core/legacy.php");
require_once("core/DB.php");
require_once("core/model/Settings.php");

if (isset($_GET['shopp_image']) || 
	preg_match('/images\/\d+/',$_SERVER['REQUEST_URI'])) 
		require("core/image.php");
if (isset($_GET['shopp_lookup']) && $_GET['shopp_lookup'] == 'catalog.css') shopp_catalog_css();
if (isset($_GET['shopp_lookup']) && $_GET['shopp_lookup'] == 'settings.js') 
	shopp_settings_js(basename(dirname(__FILE__)));

// Load super controllers and framework systems
require("core/flow/Flow.php");
require("core/flow/Login.php");
require("core/flow/Modules.php");

// Load Shopp-managed data model objects
require("core/model/Gateway.php");
require("core/model/Shopping.php");
require("core/model/Error.php");
require("core/model/Order.php");
require("core/model/Cart.php");
require("core/model/Catalog.php");
require("core/model/Purchase.php");
require("core/model/Customer.php");

// Start up the core
$Shopp = new Shopp();
do_action('shopp_init');

/**
 * Shopp class
 *
 * @author Jonathan Davis
 * @package shopp
 * @since 1.0
 **/
class Shopp {
	var $Settings;		// Shopp settings registry
	var $Flow;			// Controller routing
	var $Catalog;		// The main catalog
	var $Category;		// Current category
	var $Product;		// Current product
	var $Cart;			// The shopping cart
	var $Login;			// The currently authenticated customer
	var $Purchase; 		// Currently requested order receipt
	var $Shipping;		// Shipping modules
	var $Gateways;		// Gateway modules
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

		$languages_path = array(PLUGINDIR,$this->directory,'lang');
		load_plugin_textdomain('Shopp',sanitize_path(join('/',$languages_path)));

		$this->uri = WP_PLUGIN_URL."/".$this->directory;
		$this->siteurl = get_bloginfo('url');
		$this->wpadminurl = admin_url();
		
		$this->secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on");
		if ($this->secure) {
			$this->uri = str_replace('http://','https://',$this->uri);
			$this->siteurl = str_replace('http://','https://',$this->siteurl);
			$this->wpadminurl = str_replace('http://','https://',$this->wpadminurl);
		}

		// Initialize settings & macros

		$this->Settings = new Settings();
		
		if (!defined('BR')) define('BR','<br />');

		// Overrideable macros
		if (!defined('SHOPP_USERLEVEL')) define('SHOPP_USERLEVEL',8);
		if (!defined('SHOPP_NOSSL')) define('SHOPP_NOSSL',false);
		if (!defined('SHOPP_PREPAYMENT_DOWNLOADS')) define('SHOPP_PREPAYMENT_DOWNLOADS',false);
		if (!defined('SHOPP_SESSION_TIMEOUT')) define('SHOPP_SESSION_TIMEOUT',7200);
		if (!defined('SHOPP_QUERY_DEBUG')) define('SHOPP_QUERY_DEBUG',false);
		if (!defined('SHOPP_GATEWAY_TIMEOUT')) define('SHOPP_GATEWAY_TIMEOUT',10);
		if (!defined('SHOPP_SHIPPING_TIMEOUT')) define('SHOPP_SHIPPING_TIMEOUT',10);

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
		define("SHOPP_DBSCHEMA",SHOPP_MODEL_PATH."/schema.sql");

		define("SHOPP_TEMPLATES",($this->Settings->get('theme_templates') != "off" 
			&& is_dir($this->Settings->get('theme_templates')))?
					  $this->Settings->get('theme_templates'):
					  SHOPP_PATH.'/'."templates");
		define("SHOPP_TEMPLATES_URI",($this->Settings->get('theme_templates') != "off"
			&& is_dir($this->Settings->get('theme_templates')))?
					  get_bloginfo('stylesheet_directory')."/shopp":
					  $this->uri."/templates");


		define("SHOPP_PERMALINKS",(get_option('permalink_structure') == "")?false:true);
		
		define("SHOPP_LOOKUP",(strpos($_SERVER['REQUEST_URI'],"images/") !== false
			||  strpos($_SERVER['REQUEST_URI'],"lookup=") !== false)?true:false);
				
		// Initialize application control processing
		
		$this->Flow = new Flow();

		register_deactivation_hook(SHOPP_PLUGINFILE, array(&$this, 'activate'));
		register_activation_hook(SHOPP_PLUGINFILE, array(&$this, 'deactivate'));
		
		// Keep any DB operations from occuring while in maintenance mode
		if (!empty($_GET['updated']) && 
				($this->Settings->get('maintenance') == "on" || $this->Settings->unavailable)) {
			$this->Flow->handler('Install');
			do_action('shopp_upgrade');
			return true;
		} elseif ($this->Settings->get('maintenance') == "on") {
			add_action('init', array(&$this, 'ajax'));
			add_action('wp', array(&$this, 'shortcodes'));
			return true;
		}
		
		// Initialize defaults if they have not been entered
		if (!$this->Settings->get('shopp_setup')) {
			if ($this->Settings->unavailable) return true;
			$this->Flow->setup();
		}


		$this->Shopping = new Shopping();
		
		
		add_action('init', array(&$this,'init'));
		add_action('init', array(&$this, 'ajax'));
		
		// Admin calls
		add_action('admin_menu', array(&$this, 'lookups'));
		add_filter('favorite_actions', array(&$this, 'favorites'));
		
		// Theme widgets
		add_action('widgets_init', array(&$this, 'widgets'));
		// add_filter('wp_list_pages',array(&$this->Flow,'secure_page_links'));
		add_action('admin_head-options-reading.php',array(&$this,'pages_index'));
		add_action('generate_rewrite_rules',array(&$this,'pages_index'));
		add_filter('rewrite_rules_array',array(&$this,'rewrites'));
		add_action('save_post', array(&$this, 'pages_index'),10,2);
		add_filter('query_vars', array(&$this,'queryvars'));
		
		// Extras & Integrations
		add_filter('aioseop_canonical_url', array(&$this,'canonurls'));
				
	}
	
	/**
	 * Initializes the Shopp runtime environment
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @return void
	 **/
	function init() {
		$pages = $this->Settings->get('pages');
		if (empty($pages)) $this->pages_index();
		if (SHOPP_PERMALINKS) {
			$this->shopuri = trailingslashit($this->link('catalog'));
			$this->canonuri = trailingslashit($this->link('catalog'),false);
			if ($this->shopuri == trailingslashit(get_bloginfo('url'))) {
				$this->shopuri .= "{$pages['catalog']['name']}/";
				$this->canonuri .= "{$pages['catalog']['name']}/";
			}
			$this->imguri = trailingslashit($this->shopuri)."images/";
		} else {
			$this->shopuri = add_query_arg('page_id',$pages['catalog']['id'],get_bloginfo('url'));
			$this->imguri = add_query_arg('shopp_image','=',get_bloginfo('url'));
			$this->canonuri = $this->link('catalog');
		}
		if ($this->secure) {
			$this->shopuri = str_replace('http://','https://',$this->shopuri);	
			$this->imguri = str_replace('http://','https://',$this->imguri);	
		}
		
		if (SHOPP_LOOKUP) return true;

		$this->Order = ShoppingObject::__new('Order');
		$this->Errors = ShoppingObject::__new('ShoppErrors');
		$this->Promotions = ShoppingObject::__new('CartPromotions');
		$this->Gateways = new GatewayModules();
		$this->Shipping = new ShippingModules();
		
		$this->ErrorLog = new ShoppErrorLogging($this->Settings->get('error_logging'));
		$this->ErrorNotify = new ShoppErrorNotification($this->Settings->get('merchant_email'),
									$this->Settings->get('error_notifications'));
									
		if (!$this->Shopping->handlers) new ShoppError(__('The Cart session handlers could not be initialized because the session was started by the active theme or an active plugin before Shopp could establish its session handlers. The cart will not function.','Shopp'),'shopp_cart_handlers',SHOPP_ADMIN_ERR);
		if (SHOPP_DEBUG && $this->Shopping->handlers) new ShoppError('Session handlers initialized successfully.','shopp_cart_handlers',SHOPP_DEBUG_ERR);
		if (SHOPP_DEBUG) new ShoppError('Session started.','shopp_session_debug',SHOPP_DEBUG_ERR);

		new Login();
	}
	
	/**
	 * Installs the tables and initializes settings
	 *
	 * Loads the install script if an installation is needed, or upgrades 
	 * the database if an upgrade is needed.
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @return void
	 **/
	function activate () {
		global $wpdb,$wp_rewrite;

		// If no settings are available,
		// no tables exist, so this is a
		// new install
		if ($this->Settings->unavailable) 
			include("core/install.php");
		
		$ver = $this->Settings->get('version');		
		if (!empty($ver) && $ver != SHOPP_VERSION) {
			$this->Flow->handler('Install');
			$this->Flow->Controller->upgrade();
		}
			
				
		if ($this->Settings->get('shopp_setup')) {
			$this->Settings->save('maintenance','off');
			$this->Settings->save('shipcalc_lastscan','');
			
			// Publish/re-enable Shopp pages
			$filter = "";
			$pages = $this->Settings->get('pages');
			foreach ($pages as $page) $filter .= ($filter == "")?"ID={$page['id']}":" OR ID={$page['id']}";	
			if ($filter != "") $wpdb->query("UPDATE $wpdb->posts SET post_status='publish' WHERE $filter");
			$this->pages_index(true);
			
			// Update rewrite rules
			$wp_rewrite->flush_rules();
			$wp_rewrite->wp_rewrite_rules();
			
		}
		
		if ($this->Settings->get('show_welcome') == "on")
			$this->Settings->save('display_welcome','on');
	}
	
	/**
	 * Reset for Shopp data model and WordPress rewrite rules
	 *
	 * Flushes the active data model in preparation for potential upgrades
	 * and wipes the rewrite rules.  Also unpublishes the Shopp pages so the
	 * store front no longers shows up on the live site.
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @return void
	 **/
	function deactivate() {
		global $wpdb,$wp_rewrite;

		// Unpublish/disable Shopp pages
		$filter = "";
		$pages = $this->Settings->get('pages');
		if (!is_array($pages)) return true;
		foreach ($pages as $page) $filter .= ($filter == "")?"ID={$page['id']}":" OR ID={$page['id']}";	
		if ($filter != "") $wpdb->query("UPDATE $wpdb->posts SET post_status='draft' WHERE $filter");

		// Update rewrite rules
		$wp_rewrite->flush_rules();
		$wp_rewrite->wp_rewrite_rules();

		$this->Settings->save('data_model','');

		return true;
	}
	
	function favorites ($actions) {
		// $key = add_query_arg(array('page'=>$this->Flow->Admin->editproduct,'id'=>'new'),$this->wpadminurl);
		// 	    $actions[$key] = array(__('New Shopp Product','Shopp'),8);
		return $actions;
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

		// include('core/ui/widgets/cart.php');
		include('core/ui/widgets/categories.php');
		include('core/ui/widgets/section.php');
		include('core/ui/widgets/tagcloud.php');
		include('core/ui/widgets/facetedmenu.php');
		include('core/ui/widgets/product.php');
		
		if (version_compare($wp_version,'2.8-dev','<')) {
			$ShoppCategories = new LegacyShoppCategoriesWidget();
			$ShoppSection = new LegacyShoppCategorySectionWidget();
			$ShoppTagCloud = new LegacyShoppTagCloudWidget();
			$ShoppFacetedMenu = new LegacyShoppFacetedMenuWidget();
			// $ShoppCart = new LegacyShoppCartWidget();
			$ShoppProduct = new LegacyShoppProductWidget();
		}
		
	}
		
	/**
	 * Handles shortcodes used on Shopp-installed pages and other post/pages
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @return void
	 **/
	function shortcodes () {

		$this->shortcodes = array();
		$this->shortcodes['catalog'] = array(&$this->Flow,'catalog');
		$this->shortcodes['cart'] = array(&$this->Flow,'cart');
		$this->shortcodes['checkout'] = array(&$this->Flow,'checkout');
		$this->shortcodes['account'] = array(&$this->Flow,'account');
		$this->shortcodes['product'] = array(&$this->Flow,'product_shortcode');
		$this->shortcodes['category'] = array(&$this->Flow,'category_shortcode');
		
		foreach ($this->shortcodes as $name => &$callback)
			if ($this->Settings->get("maintenance") == "on")
				add_shortcode($name,array(&$this->Flow,'maintenance_shortcode'));
			else add_shortcode($name,$callback);
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
		return true; // DEV
		$pages = $this->Settings->get('pages');
		
		// No pages setting, use defaults
		$pages = $this->Pages;
		
		// Find pages with Shopp-related main shortcodes
		$codes = array();
		$search = "";
		foreach ($pages as $page) $codes[] = $page['content'];
		foreach ($codes as $code) $search .= ((!empty($search))?" OR ":"")."post_content LIKE '%$code%'";
		$query = "SELECT ID,post_title,post_name,post_content FROM $wpdb->posts WHERE post_status='publish' AND ($search)";
		$results = $wpdb->get_results($query);

		// Match updates from the found results to our pages index
		foreach ($pages as $key => &$page) {
			foreach ($results as $index => $post) {
				if (strpos($post->post_content,$page['content']) !== false) {
					$page['id'] = $post->ID;
					$page['title'] = $post->post_title;
					$page['name'] = $post->post_name;
					$page['permalink'] = str_replace(trailingslashit(get_bloginfo('url')),'',get_permalink($page['id']));
					if ($page['permalink'] == get_bloginfo('url')) $page['permalink'] = "";
					break;
				}
			}
		}
		
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
		if (!$pages) $pages = $this->Flow->Pages;
		$shop = $pages['catalog']['permalink'];
		if (!empty($shop)) $shop = trailingslashit($shop);
		$catalog = $pages['catalog']['name'];
		$cart = $pages['cart']['permalink'];
		$checkout = $pages['checkout']['permalink'];
		$account = $pages['account']['permalink'];

		$rules = array(
			$cart.'?$' => 'index.php?pagename='.shopp_pagename($cart),
			$account.'?$' => 'index.php?pagename='.shopp_pagename($account),
			$checkout.'?$' => 'index.php?pagename='.shopp_pagename($checkout).'&shopp_proc=checkout',
			(empty($shop)?"$catalog/":$shop).'feed/?$' => 'index.php?shopp_lookup=newproducts-rss',
			(empty($shop)?"$catalog/":$shop).'(thanks|receipt)/?$' => 'index.php?pagename='.shopp_pagename($checkout).'&shopp_proc=thanks',
			(empty($shop)?"$catalog/":$shop).'confirm-order/?$' => 'index.php?pagename='.shopp_pagename($checkout).'&shopp_proc=confirm-order',
			(empty($shop)?"$catalog/":$shop).'download/([a-z0-9]{40})/?$' => 'index.php?pagename='.shopp_pagename($account).'&shopp_download=$matches[1]',
			(empty($shop)?"$catalog/":$shop).'images/(\d+)/?.*?$' => 'index.php?shopp_image=$matches[1]'
		);

		// catalog/category/category-slug
		if (empty($shop)) {
			$rules[$catalog.'/category/(.+?)/feed/?$'] = 'index.php?shopp_lookup=category-rss&shopp_category=$matches[1]';
			$rules[$catalog.'/category/(.+?)/page/?([A-Z0-9]{1,})/?$'] = 'index.php?pagename='.shopp_pagename($catalog).'&shopp_category=$matches[1]&paged=$matches[2]';
			$rules[$catalog.'/category/(.+)/?$'] = 'index.php?pagename='.shopp_pagename($catalog).'&shopp_category=$matches[1]';
		} else {
			$rules[$shop.'category/(.+?)/feed/?$'] = 'index.php?shopp_lookup=category-rss&shopp_category=$matches[1]';
			$rules[$shop.'category/(.+?)/page/?([A-Z0-9]{1,})/?$'] = 'index.php?pagename='.shopp_pagename($shop).'&shopp_category=$matches[1]&paged=$matches[2]';
			$rules[$shop.'category/(.+)/?$'] = 'index.php?pagename='.shopp_pagename($shop).'&shopp_category=$matches[1]';
		}

		// tags
		if (empty($shop)) {
			$rules[$catalog.'/tag/(.+?)/feed/?$'] = 'index.php?shopp_lookup=category-rss&shopp_tag=$matches[1]';
			$rules[$catalog.'/tag/(.+?)/page/?([0-9]{1,})/?$'] = 'index.php?pagename='.shopp_pagename($catalog).'&shopp_tag=$matches[1]&paged=$matches[2]';
			$rules[$catalog.'/tag/(.+)/?$'] = 'index.php?pagename='.shopp_pagename($catalog).'&shopp_tag=$matches[1]';
		} else {
			$rules[$shop.'tag/(.+?)/feed/?$'] = 'index.php?shopp_lookup=category-rss&shopp_tag=$matches[1]';
			$rules[$shop.'tag/(.+?)/page/?([0-9]{1,})/?$'] = 'index.php?pagename='.shopp_pagename($shop).'&shopp_tag=$matches[1]&paged=$matches[2]';
			$rules[$shop.'tag/(.+)/?$'] = 'index.php?pagename='.shopp_pagename($shop).'&shopp_tag=$matches[1]';
		}

		// catalog/productid
		if (empty($shop)) $rules[$catalog.'/(\d+(,\d+)?)/?$'] = 'index.php?pagename='.shopp_pagename($catalog).'&shopp_pid=$matches[1]';
		else $rules[$shop.'(\d+(,\d+)?)/?$'] = 'index.php?pagename='.shopp_pagename($shop).'&shopp_pid=$matches[1]';

		// catalog/product-slug
		if (empty($shop)) $rules[$catalog.'/(.+)/?$'] = 'index.php?pagename='.shopp_pagename($catalog).'&shopp_product=$matches[1]'; // category/product-slug
		else $rules[$shop.'(.+)/?$'] = 'index.php?pagename='.shopp_pagename($shop).'&shopp_product=$matches[1]'; // category/product-slug			

		// catalog/categories/path/product-slug
		if (empty($shop)) $rules[$catalog.'/([\w%_\\+-\/]+?)/([\w_\-]+?)/?$'] = 'index.php?pagename='.shopp_pagename($catalog).'&shopp_category=$matches[1]&shopp_product=$matches[2]'; // category/product-slug
		else $rules[$shop.'([\w%_\+\-\/]+?)/([\w_\-]+?)/?$'] = 'index.php?pagename='.shopp_pagename($shop).'&shopp_category=$matches[1]&shopp_product=$matches[2]'; // category/product-slug			
		$corepath = array(PLUGINDIR,$this->directory,'core');

		// Add mod_rewrite rule for image server for low-resource, speedy delivery
		add_rewrite_rule('.*/images/(\d+)/?.*?$',join('/',$corepath).'/image.php?shopp_image=$1');

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
		$vars[] = 'shopp_proc';
		$vars[] = 'shopp_category';
		$vars[] = 'shopp_tag';
		$vars[] = 'shopp_pid';
		$vars[] = 'shopp_product';
		$vars[] = 'shopp_lookup';
		$vars[] = 'shopp_image';
		$vars[] = 'shopp_download';
		$vars[] = 'shopp_xco';
		$vars[] = 'st';
	
		return $vars;
	}

	/**
	 * xorder ()
	 * Handle external checkout system order notifications */
	function xorder () {
		$path = false;
		if (!empty($_GET['shopp_xorder'])) {
			$gateway = $this->Settings->get($_GET['shopp_xorder']);
			if (isset($gateway['path'])) $path = $gateway['path'];
			// Use the old path support for transition if the new path setting isn't available
			if (empty($path)) $path = "{$_GET['shopp_xorder']}/{$_GET['shopp_xorder']}.php";
			if ($this->gateway($path)) $this->Gateway->process();
			exit();
		}
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
	function resession () {
		session_regenerate_id();
		$this->Cart->session = session_id();
		session_write_close();
		$this->Cart = new Cart();
		session_start();
		$this->Cart->clear();
		$this->Cart->data->Promotions = array();
		return true;
	}
	
	/**
	 * gateway ()
	 * Loads a requested gateway */
	function gateway ($gateway,$load=false) {
		if (substr($gateway,-4) != ".php") $gateway .= ".php";
		$filepath = join('/',array($this->path,'gateways',$gateway));
		if (!file_exists($filepath)) {
			new ShoppError(__('There was a problem loading the requested payment processor.','Shopp').' ('.$gateway.')','shopp_load_gateway');
			return false;
		}
		$meta = $this->Flow->scan_gateway_meta($filepath);
		$ProcessorClass = $meta->tags['class'];
		include_once($filepath);

		if (isset($this->Cart->data->Order) && !$load) $this->Gateway = new $ProcessorClass($this->Cart->data->Order);
		else $this->Gateway = new $ProcessorClass();

		return true;
	}
	
	/**
	 * link ()
	 * Builds a full URL for a specific Shopp-related resource */
	function link ($target,$secure=false) {
		$internals = array("thanks","receipt","confirm-order");
		$pages = $this->Settings->get('pages');
		
		if (!is_array($pages)) $pages = $this->Flow->Pages;
		
		$uri = get_bloginfo('url');
		if ($secure && !SHOPP_NOSSL) $uri = str_replace('http://','https://',$uri);

		if (array_key_exists($target,$pages)) $page = $pages[$target];
		else {
			if (in_array($target,$internals)) {
				$page = $pages['checkout'];
				if (SHOPP_PERMALINKS) {
					$catalog = $pages['catalog']['permalink'];
					if (empty($catalog)) $catalog = $pages['catalog']['name'];
					$page['permalink'] = trailingslashit($catalog).$target;
				} else $page['id'] .= "&shopp_proc=$target";
			} else $page = $pages['catalog'];
 		}

		if (SHOPP_PERMALINKS) return user_trailingslashit($uri."/".$page['permalink']);
		else return add_query_arg('page_id',$page['id'],trailingslashit($uri));
	}
		
	/**
	 * help()
	 * This function provides graceful degradation when the 
	 * contextual javascript behavior isn't working, this
	 * provides the default behavior of showing a help gateway
	 * page with instructions on where to find help on Shopp. */
	function help () {
		include(SHOPP_ADMIN_PATH."/help/help.php");
	}

	function welcome () {
		include(SHOPP_ADMIN_PATH."/help/welcome.php");
	}
	
	/**
	 * AJAX Responses */
	
	/**
	 * lookups ()
	 * Provides fast db lookups with as little overhead as possible */
	function lookups($wp) {
		$db =& DB::get();

		// Grab query requests from permalink rewriting query vars
		$admin = false;
		$download = (isset($wp->query_vars['shopp_download']))?$wp->query_vars['shopp_download']:'';
		$lookup = (isset($wp->query_vars['shopp_lookup']))?$wp->query_vars['shopp_lookup']:'';
				
		// Admin Lookups
		if (isset($_GET['page']) && $_GET['page'] == "shopp-lookup") {
			$admin = true;
			$image = $_GET['id'];
			$download = $_GET['download'];
		}
		
		if (!empty($download)) $lookup = "download";
		if (empty($lookup)) $lookup = (isset($_GET['lookup']))?$_GET['lookup']:'';

		switch($lookup) {
			case "purchaselog":
				if (!defined('WP_ADMIN') || !is_user_logged_in() || !current_user_can('manage_options')) die('-1');
				$db =& DB::get();

				if (!isset($_POST['settings']['purchaselog_columns'])) {
					$_POST['settings']['purchaselog_columns'] =
					 	array_keys(array_merge($Purchase,$Purchased));
					$_POST['settings']['purchaselog_headers'] = "on";
				}
				
				$this->Flow->settings_save();
				
				$format = $this->Settings->get('purchaselog_format');
				if (empty($format)) $format = 'tab';
				
				switch ($format) {
					case "csv": new PurchasesCSVExport(); break;
					case "xls": new PurchasesXLSExport(); break;
					case "iif": new PurchasesIIFExport(); break;
					default: new PurchasesTabExport();
				}
				exit();
				break;
			case "customerexport":
				if (!defined('WP_ADMIN') || !is_user_logged_in() || !current_user_can('manage_options')) die('-1');
				$db =& DB::get();

				if (!isset($_POST['settings']['customerexport_columns'])) {
					$Customer = Customer::exportcolumns();
					$Billing = Billing::exportcolumns();
					$Shipping = Shipping::exportcolumns();
					$_POST['settings']['customerexport_columns'] =
					 	array_keys(array_merge($Customer,$Billing,$Shipping));
					$_POST['settings']['customerexport_headers'] = "on";
				}

				$this->Flow->settings_save();

				$format = $this->Settings->get('customerexport_format');
				if (empty($format)) $format = 'tab';

				switch ($format) {
					case "csv": new CustomersCSVExport(); break;
					case "xls": new CustomersXLSExport(); break;
					default: new CustomersTabExport();
				}
				exit();
				break;
			case "receipt":
				if (!defined('WP_ADMIN') || !is_user_logged_in() || !current_user_can('manage_options')) die('-1');
				if (preg_match("/\d+/",$_GET['id'])) {
					$this->Cart->data->Purchase = new Purchase($_GET['id']);
					$this->Cart->data->Purchase->load_purchased();
				} else die('-1');
				echo "<html><head>";
					echo '<style type="text/css">body { padding: 20px; font-family: Arial,Helvetica,sans-serif; }</style>';
					echo "<link rel='stylesheet' href='".SHOPP_TEMPLATES_URI."/shopp.css' type='text/css' />";
				echo "</head><body>";
				echo $this->Flow->order_receipt();
				if (isset($_GET['print']) && $_GET['print'] == 'auto')
					echo '<script type="text/javascript">window.onload = function () { window.print(); window.close(); }</script>';
				echo "</body></html>";
				exit();
				break;
			case "zones":
				$zones = $this->Settings->get('zones');
				if (isset($_GET['country']))
					echo json_encode($zones[$_GET['country']]);
				exit();
				break;
			case "shipcost":
				@session_start();
				$this->ShipCalcs = new ShipCalcs($this->path);
				if (isset($_GET['method'])) {
					$this->Cart->data->Order->Shipping->method = $_GET['method'];
					$this->Cart->retotal = true;
					$this->Cart->updated();
					$this->Cart->totals();
					echo json_encode($this->Cart->data->Totals);
				}
				exit();
				break;
			case "category-menu":
				echo $this->Flow->category_menu();
				exit();
				break;
			case "category-products-menu":
				echo $this->Flow->category_products();
				exit();
				break;
			case "spectemplate":
				$db = DB::get();
				$table = DatabaseObject::tablename(Category::$table);			
				$result = $db->query("SELECT specs FROM $table WHERE id='{$_GET['cat']}' AND spectemplate='on'");
				echo json_encode(unserialize($result->specs));
				exit();
				break;
			case "optionstemplate":
				$db = DB::get();
				$table = DatabaseObject::tablename(Category::$table);			
				$result = $db->query("SELECT options,prices FROM $table WHERE id='{$_GET['cat']}' AND variations='on'");
				if (empty($result)) exit();
				$result->options = unserialize($result->options);
				$result->prices = unserialize($result->prices);
				foreach ($result->options as &$menu) {
					foreach ($menu['options'] as &$option) $option['id'] += $_GET['cat'];
				}
				foreach ($result->prices as &$price) {
					$optionids = explode(",",$price['options']);
					foreach ($optionids as &$id) $id += $_GET['cat'];
					$price['options'] = join(",",$optionids);
					$price['optionkey'] = "";
				}
				
				echo json_encode($result);
				exit();
				break;
			case "newproducts-rss":
				$NewProducts = new NewProducts(array('show' => 5000));
				header("Content-type: application/rss+xml; charset=utf-8");
				echo shopp_rss($NewProducts->rss());
				exit();
				break;
			case "category-rss":
				$this->catalog($wp);
				header("Content-type: application/rss+xml; charset=utf-8");
				echo shopp_rss($this->Category->rss());
				exit();
				break;
			case "download":
				if (empty($download)) break;
		
				if ($admin) {
					$Asset = new Asset($download);
				} else {
					$db = DB::get();
					$pricetable = DatabaseObject::tablename(Purchase::$table);			
					$pricetable = DatabaseObject::tablename(Price::$table);			
					$assettable = DatabaseObject::tablename(Asset::$table);			
					
					require_once("core/model/Purchased.php");
					$Purchased = new Purchased($download,"dkey");
					$Purchase = new Purchase($Purchased->purchase);
					$target = $db->query("SELECT target.* FROM $assettable AS target LEFT JOIN $pricetable AS pricing ON pricing.id=target.parent AND target.context='price' WHERE pricing.id=$Purchased->price AND target.datatype='download'");
					$Asset = new Asset();
					$Asset->populate($target);

					$forbidden = false;

					// Purchase Completion check
					if ($Purchase->transtatus != "CHARGED" 
						&& !SHOPP_PREPAYMENT_DOWNLOADS) {
						new ShoppError(__('This file cannot be downloaded because payment has not been received yet.','Shopp'),'shopp_download_limit');
						$forbidden = true;
					}
					
					// Account restriction checks
					if ($this->Settings->get('account_system') != "none"
						&& (!$this->Cart->data->login
						|| $this->Cart->data->Order->Customer->id != $Purchase->customer)) {
							new ShoppError(__('You must login to access this download.','Shopp'),'shopp_download_limit',SHOPP_ERR);
							header('Location: '.$this->link('account'));
							exit();
					}
					
					// Download limit checking
					if ($this->Settings->get('download_limit') // Has download credits available
						&& $Purchased->downloads+1 > $this->Settings->get('download_limit')) {
							new ShoppError(__('This file can no longer be downloaded because the download limit has been reached.','Shopp'),'shopp_download_limit');
							$forbidden = true;
						}
							
					// Download expiration checking
					if ($this->Settings->get('download_timelimit') // Within the timelimit
						&& $Purchased->created+$this->Settings->get('download_timelimit') < mktime() ) {
							new ShoppError(__('This file can no longer be downloaded because it has expired.','Shopp'),'shopp_download_limit');
							$forbidden = true;
						}
					
					// IP restriction checks
					if ($this->Settings->get('download_restriction') == "ip"
						&& !empty($Purchase->ip) 
						&& $Purchase->ip != $_SERVER['REMOTE_ADDR']) {
							new ShoppError(__('The file cannot be downloaded because this computer could not be verified as the system the file was purchased from.','Shopp'),'shopp_download_limit');
							$forbidden = true;	
						}

					do_action_ref_array('shopp_download_request',array(&$Purchased));
				}
			
				if ($forbidden) {
					header("Status: 403 Forbidden");
					return;
				}
				
				if ($Asset->download($download)) {
					$Purchased->downloads++;
					$Purchased->save();
					do_action_ref_array('shopp_download_success',array(&$Purchased));
					exit();
				}
				break;
		}
	}

	/**
	 * ajax ()
	 * Handles AJAX request processing */
	function ajax() {
		if (!isset($_REQUEST['action']) || !defined('DOING_AJAX')) return;
		
		if (isset($_POST['action'])) {			
			switch($_POST['action']) {
				// Upload an image in the product editor
				case "shopp_add_image":
					$this->Flow->add_images();
					exit();
					break;
				
				// Upload a product download file in the product editor
				case "shopp_add_download":
					$this->Flow->product_downloads();
					exit();
					break;
			}
		}
		
		if ((!is_user_logged_in() || !current_user_can('manage_options'))
			&& strpos($_GET['action'],'wp_ajax_shopp_') !== false) die('-1');
		
		if (empty($_GET['action'])) return;
		switch($_GET['action']) {
			
			// Add a category in the product editor
			case "wp_ajax_shopp_add_category":
				check_admin_referer('shopp-ajax_add_category');
			
				if (!empty($_GET['name'])) {
					$Catalog = new Catalog();
					$Catalog->load_categories();
				
					$Category = new Category();
					$Category->name = $_GET['name'];
					$Category->slug = sanitize_title_with_dashes($Category->name);
					$Category->parent = $_GET['parent'];
										
					// Work out pathing
					$paths = array();
					if (!empty($Category->slug)) $paths = array($Category->slug);  // Include self
				
					$parentkey = -1;
					// If we're saving a new category, lookup the parent
					if ($Category->parent > 0) {
						array_unshift($paths,$Catalog->categories[$Category->parent]->slug);
						$parentkey = $Catalog->categories[$Category->parent]->parent;
					}
				
					while ($category_tree = $Catalog->categories[$parentkey]) {
						array_unshift($paths,$category_tree->slug);
						$parentkey = $category_tree->parent;
					}
				
					if (count($paths) > 1) $Category->uri = join("/",$paths);
					else $Category->uri = $paths[0];
					
					$Category->save();
					echo json_encode($Category);
				}
				exit();
				break;

			case "wp_ajax_shopp_edit_slug":
				check_admin_referer('shopp-ajax_edit_slug');
				if ( !current_user_can('manage_options') ) die("-1");
								
				switch ($_REQUEST['type']) {
					case "category":
						$Category = new Category($_REQUEST['id']);
						if (empty($_REQUEST['slug'])) $_REQUEST['slug'] = $Category->name;
						$Category->slug = sanitize_title_with_dashes($_REQUEST['slug']);
						if ($Category->save()) echo apply_filters('editable_slug',$Category->slug);
						else echo '-1';
						break;
					case "product":
						$Product = new Product($_REQUEST['id']);
						if (empty($_REQUEST['slug'])) $_REQUEST['slug'] = $Product->name;
						$Product->slug = sanitize_title_with_dashes($_REQUEST['slug']);
						if ($Product->save()) echo apply_filters('editable_slug',$Product->slug);
						else echo '-1';
						break;
				}
				exit();
				break;
				
			// Upload a product download file in the product editor
			case "wp_ajax_shopp_verify_file":
				check_admin_referer('shopp-ajax_verify_file');
				if ( !current_user_can('manage_options') ) exit();
				chdir(WP_CONTENT_DIR); // relative path context for realpath
				$target = trailingslashit(sanitize_path(realpath($this->Settings->get('products_path')))).$_POST['filepath'];
				if (!file_exists($target)) die("NULL");
				if (is_dir($target)) die("ISDIR");
				if (!is_readable($target)) die("READ");
				die("OK");
				break;
				
			// Perform a version check for any updates
			case "wp_ajax_shopp_version_check":	
				check_admin_referer('shopp-wp_ajax_shopp_update');
				$request = array(
					"ShoppServerRequest" => "version-check",
					"ver" => '1.0'
				);
				$data = array(
					'core' => SHOPP_VERSION,
					'addons' => join("-",$this->Flow->validate_addons())
				);
				echo $this->Flow->callhome($request,$data);
				exit();
			case "wp_ajax_shopp_verify":
				if ($this->Settings->get('maintenance') == "on") echo "1";
				exit();

			// Perform an update process
			case "wp_ajax_shopp_update":
				check_admin_referer('shopp-wp_ajax_shopp_update');
				$this->Flow->update();
				exit();
			case "wp_ajax_shopp_setftp":
				check_admin_referer('shopp-wp_ajax_shopp_update');
				$this->Flow->settings_save();
				$updates = $this->Settings->get('ftp_credentials');
				exit();
		}
				
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
		case "checkout": if (isset($Shopp->Order)) $Object =& $Shopp->Order; break;
		case "category": if (isset($Shopp->Category)) $Object =& $Shopp->Category; break;
		case "subcategory": if (isset($Shopp->Category->child)) $Object =& $Shopp->Category->child; break;
		case "catalog": if (isset($Shopp->Catalog)) $Object =& $Shopp->Catalog; break;
		case "product": if (isset($Shopp->Product)) $Object =& $Shopp->Product; break;
		case "checkout": if (isset($Shopp->Order)) $Object =& $Shopp->Order; break;
		case "purchase": if (isset($Shopp->Purchase)) $Object =& $Shopp->Purchase; break;
		case "customer": if (isset($Shopp->Order->Customer)) $Object =& $Shopp->Order->Customer; break;
		case "error": if (isset($Shopp->Errors)) $Object =& $Shopp->Errors; break;
		default: $Object = false;
	}

	if (!$Object) new ShoppError("The shopp('$object') tag cannot be used in this context because the object responsible for handling it doesn't exist.",'shopp_tag_error',SHOPP_ADMIN_ERR);
	else {
		switch (strtolower($object)) {
			case "cartitem": $result = $Object->itemtag($property,$options); break;
			case "shipping": $result = $Object->shippingtag($property,$options); break;
			default: $result = $Object->tag($property,$options); break;
		}
	}

	// Provide a filter hook for every template tag, includes passed options and the relevant Object as parameters
	$result = apply_filters('shopp_tag_'.strtolower($object).'_'.strtolower($property),$result,$options,&$Object);

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
	if (is_string($result)) echo $result;
	else return $result;
	return true;
}

?>
