<?php
/*
Plugin Name: Shopp
Version: 1.0RC3
Description: Bolt-on ecommerce solution for WordPress
Plugin URI: http://shopplugin.net
Author: Ingenesis Limited
Author URI: http://ingenesis.net

	Copyright 2008 Ingenesis Limited

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

define("SHOPP_VERSION","1.0RC3");
define("SHOPP_GATEWAY_USERAGENT","WordPress Shopp Plugin/".SHOPP_VERSION);
define("SHOPP_HOME","http://shopplugin.net/");
define("SHOPP_DOCS","http://docs.shopplugin.net/");
define("SHOPP_DEBUG",true);

require("core/functions.php");
require_once("core/DB.php");
require("core/model/Settings.php");

if ($_GET['shopp_image'] || 
		preg_match('/images\/\d+/',$_SERVER['REQUEST_URI'])) 
		shopp_image();
if ($_GET['shopp_lookup'] == 'catalog.css') shopp_catalog_css();
if ($_GET['shopp_lookup'] == 'settings.js') shopp_settings_js();

require("core/Flow.php");
require("core/model/Cart.php");
require("core/model/ShipCalcs.php");
require("core/model/Catalog.php");
require("core/model/Purchase.php");

$Shopp = new Shopp();

class Shopp {
	var $Cart;
	var $Flow;
	var $Settings;
	var $ShipCalcs;
	var $Product;
	var $Category;
	var $Catalog;
	var $_debug;
	
	function Shopp () {
		if (SHOPP_DEBUG) {
			$this->_debug = new StdClass();
			if (function_exists('memory_get_peak_usage'))
				$this->_debug->memory = "Initial: ".number_format(memory_get_peak_usage(true)/1024/1024, 2, '.', ',') . " MB<br />";
			if (function_exists('memory_get_usage'))
				$this->_debug->memory = "Initial: ".number_format(memory_get_usage(true)/1024/1024, 2, '.', ',') . " MB<br />";
		}
		
		$this->path = dirname(__FILE__);
		$this->file = basename(__FILE__);
		$this->directory = basename($this->path);
		$this->secure = (!empty($_SERVER['HTTPS']));
		$this->uri = WP_PLUGIN_URL."/".$this->directory;
		$this->wpadminurl = get_bloginfo('wpurl')."/wp-admin/admin.php";
		if ($this->secure) $this->uri = str_replace('http://','https://',$this->uri);

		$this->Settings = new Settings();
		$this->Flow = new Flow($this);

		// Keep any DB operations from occuring while in maintenance mode
		if (!empty($_GET['updated']) && $this->Settings->get('maintenance') == "on") {
			$this->Flow->upgrade();
			$this->Settings->save("maintenance","off");
		} elseif ($this->Settings->get('maintenance') == "on") {
			add_action('init', array(&$this, 'ajax'));
			add_action('wp', array(&$this, 'shortcodes'));
			return true;
		}
		
		register_deactivation_hook("shopp/Shopp.php", array(&$this, 'deactivate'));
		register_activation_hook("shopp/Shopp.php", array(&$this, 'install'));

		// Initialize defaults if they have not been entered
		if (!$this->Settings->get('shopp_setup')) {
			if ($this->Settings->unavailable) return true;
			$this->Flow->setup();
		}
		
		if (!SHOPP_LOOKUP) add_action('init',array(&$this,'init'));

		add_action('init', array(&$this, 'ajax'));
		add_action('init', array(&$this, 'xorder'));
		add_action('init', array(&$this, 'tinymce'));
		add_action('parse_request', array(&$this, 'lookups') );
		add_action('parse_request', array(&$this, 'cart'));
		add_action('parse_request', array(&$this, 'checkout'));
		add_action('parse_request', array(&$this, 'catalog') );
		add_action('wp', array(&$this, 'shortcodes'));
		add_action('wp', array(&$this, 'behaviors'));

		add_action('wp_ajax_shopp_add_category', array(&$this, 'ajax') );
		add_action('wp_ajax_shopp_add_image', array(&$this, 'ajax') );
		add_action('wp_ajax_shopp_add_download', array(&$this, 'ajax') );

		add_action('admin_menu', array(&$this, 'lookups'));
		add_action('admin_menu', array(&$this, 'add_menus'));
		add_filter('favorite_actions', array(&$this, 'favorites'));

		add_action('admin_footer', array(&$this, 'footer'));
		add_action('wp_dashboard_setup', array(&$this, 'dashboard_init'));
		add_action('wp_dashboard_widgets', array(&$this, 'dashboard'));
		add_action('admin_print_styles-index.php', array(&$this, 'dashboard_css'));
		add_action('save_post', array(&$this, 'page_updates'),10,2);

		add_action('widgets_init', array(&$this->Flow, 'init_cart_widget'));
		add_action('widgets_init', array(&$this->Flow, 'init_categories_widget'));
		add_action('widgets_init', array(&$this->Flow, 'init_tagcloud_widget'));
		add_action('widgets_init', array(&$this->Flow, 'init_facetedmenu_widget'));
		add_filter('wp_list_pages',array(&$this->Flow,'secure_checkout_link'));

		add_action('rewrite_rules', array(&$this,'page_updates'));
		add_filter('rewrite_rules_array',array(&$this,'rewrites'));
		add_filter('query_vars', array(&$this,'queryvars'));
		return true;
	}
	
	function init() {
		if (SHOPP_PERMALINKS) {
			$pages = $this->Settings->get('pages');
			$this->shopuri = $this->link('catalog');
			if ($this->shopuri == trailingslashit(get_bloginfo('wpurl'))) $this->shopuri .= "{$pages['catalog']['name']}/";
			if ($this->secure) $this->shopuri = str_replace('http://','https://',$this->shopuri);
			$this->imguri = trailingslashit($this->shopuri)."images/";
		} else {
			$this->shopuri = get_bloginfo('wpurl');
			$this->imguri = add_query_arg('shopp_image','=',get_bloginfo('wpurl'));
		} 
		
		$this->Cart = new Cart();
		session_start();
		
		$this->Catalog = new Catalog();
		$this->ShipCalcs = new ShipCalcs($this->path);
		
		// Handle WordPress pre-logins
		$authentication = $this->Settings->get('account_system');
		if ($authentication == "wordpress") {
			// See if the wordpress user is already logged in
			get_currentuserinfo();
			global $user_ID;

			if (!empty($user_ID)) {
				$Account = new Customer($user_ID,'wpuser');
				if (!$Cart->data->login) $this->Flow->loggedin($Account);
				$Cart->data->Order->Customer->wpuser = $user_ID;
			}
		}
		
	}

	/**
	 * install()
	 * Installs the tables and initializes settings */
	function install () {
		global $wpdb;

		// If no settings are available,
		// no tables exist, so this is a
		// new install
		if ($this->Settings->unavailable) 
			include("core/install.php");
		
		if ($this->Settings->get('version') != SHOPP_VERSION)
			$this->Flow->upgrade();
				
		if ($this->Settings->get('shopp_setup')) {
			$this->Settings->save('maintenance','off');
			
			// Publish/re-enable Shopp pages
			$filter = "";
			$pages = $this->Settings->get('pages');
			foreach ($pages as $page) $filter .= ($filter == "")?"ID={$page['id']}":" OR ID={$page['id']}";	
			if ($filter != "") $wpdb->query("UPDATE $wpdb->posts SET post_status='publish' WHERE $filter");
			$this->page_updates(true);
		}
		
		if ($this->Settings->get('show_welcome') == "on")
			$this->Settings->save('display_welcome','on');
	}
	
	/**
	 * deactivate()
	 * Resets the data_model to prepare for potential upgrades/changes to the table schema */
	function deactivate() {
		global $wpdb;

		// Unpublish/disable Shopp pages
		$filter = "";
		$pages = $this->Settings->get('pages');
		if (!is_array($pages)) return true;
		foreach ($pages as $page) $filter .= ($filter == "")?"ID={$page['id']}":" OR ID={$page['id']}";	
		if ($filter != "") $wpdb->query("UPDATE $wpdb->posts SET post_status='draft' WHERE $filter");

		$this->Settings->save('data_model','');

		return true;
	}
	
	/**
	 * add_menus()
	 * Adds the WordPress admin menus */
	function add_menus () {

		if (function_exists('add_object_page')) $main = add_object_page('Shopp', 'Shopp', 8, $this->Flow->Admin->default, array(&$this,'orders'),$this->uri."/core/ui/icons/shopp.png");
		else $main = add_menu_page('Shopp', 'Shopp', 8, $this->Flow->Admin->default, array(&$this,'orders'),$this->uri."/core/ui/icons/shopp.png");
		$orders = add_submenu_page($this->Flow->Admin->default,__('Orders','Shopp'), __('Orders','Shopp'), 8, $this->Flow->Admin->orders, array(&$this,'orders'));
		$promotions = add_submenu_page($this->Flow->Admin->default,__('Promotions','Shopp'), __('Promotions','Shopp'), 8, $this->Flow->Admin->promotions, array(&$this,'promotions'));
		$products = add_submenu_page($this->Flow->Admin->default,__('Products','Shopp'), __('Products','Shopp'), 8, $this->Flow->Admin->products, array(&$this,'products'));
		$categories = add_submenu_page($this->Flow->Admin->default,__('Categories','Shopp'), __('Categories','Shopp'), 8, $this->Flow->Admin->categories, array(&$this,'categories'));
		$settings = add_submenu_page($this->Flow->Admin->default,__('Settings','Shopp'), __('Settings','Shopp'), 8, $this->Flow->Admin->settings, array(&$this,'settings'));

		if (function_exists('add_contextual_help')) {
			add_contextual_help($orders,'<a href="'.SHOPP_DOCS.'Managing_Orders" target="_blank">Managing Orders</a>');
			add_contextual_help($promotions,'<a href="'.SHOPP_DOCS.'Running_Sales_%26_Promotions" target="_blank">Running Sales &amp; Promotions</a>');
			add_contextual_help($products,'<a href="'.SHOPP_DOCS.'Editing_a_Product" target="_blank">Editing a Product</a>');
			add_contextual_help($categories,'<a href="'.SHOPP_DOCS.'Editing_a_Category" target="_blank">Editing a Category</a>');

			add_contextual_help($settings,'<a href="'.SHOPP_DOCS.'General_Settings" target="_blank">General Settings</a> | <a href="'.SHOPP_DOCS.'Checkout_Settings" target="_blank">Checkout Settings</a> | <a href="'.SHOPP_DOCS.'Payments_Settings" target="_blank">Payments Settings</a> | <a href="'.SHOPP_DOCS.'Shipping_Settings" target="_blank">Shipping Settings</a> | <a href="'.SHOPP_DOCS.'Taxes_Settings" target="_blank">Taxes Settings</a> | <a href="'.SHOPP_DOCS.'Presentation_Settings" target="_blank">Presetation Settings</a> | <a href="'.SHOPP_DOCS.'System_Settings" target="_blank">System Settings</a> | <a href="'.SHOPP_DOCS.'Update_Settings" target="_blank">Update Settings</a>');
			
		} else $help = add_submenu_page($this->Flow->Admin->default,__('Help','Shopp'), __('Help','Shopp'), 8, $this->Flow->Admin->help, array(&$this,'help'));
		
		// $welcome = add_submenu_page($this->Flow->Admin->default,__('Welcome','Shopp'), __('Welcome','Shopp'), 8, $this->Flow->Admin->welcome, array(&$this,'welcome'));
		
		add_action("admin_print_scripts-$main", array(&$this, 'admin_behaviors'));
		add_action("admin_print_scripts-$orders", array(&$this, 'admin_behaviors'));
		add_action("admin_print_scripts-$categories", array(&$this, 'admin_behaviors'));
		add_action("admin_print_scripts-$products", array(&$this, 'admin_behaviors'));
		add_action("admin_print_scripts-$promotions", array(&$this, 'admin_behaviors'));
		add_action("admin_print_scripts-$settings", array(&$this, 'admin_behaviors'));
		add_action("admin_print_scripts-$help", array(&$this, 'admin_behaviors'));		
		add_action("admin_print_scripts-$welcome", array(&$this, 'admin_behaviors'));		

	}

	function favorites ($actions) {
		$key = 'admin.php?page='.$this->Flow->Admin->products.'&edit=new';
	    $actions[$key] = array('New Shopp Product',8);
		return $actions;
	}
		
	/**
	 * admin_behaviors()
	 * Dynamically includes necessary JavaScript and stylesheets for the admin */
	function admin_behaviors () {
		global $wp_version;
		wp_enqueue_script('jquery');
		wp_enqueue_script('shopp',"{$this->uri}/core/ui/behaviors/shopp.js");
		
		// Load only for the product editor to keep other admin screens snappy
		if (($_GET['page'] == $this->Flow->Admin->products || 
			 $_GET['page'] == $this->Flow->Admin->categories) && 
			 isset($_GET['edit'])) {
			wp_enqueue_script('shopp.editor.lib',"{$this->uri}/core/ui/behaviors/editors.js");
			wp_enqueue_script('shopp.product.editor',"{$this->uri}/core/ui/products/editor.js");
			//wp_enqueue_script('jquery.tablednd',"{$this->uri}/core/ui/jquery/jquery.tablednd.js",array('jquery'),'');
			wp_enqueue_script('shopp.ocupload',"{$this->uri}/core/ui/behaviors/ocupload.js");
			wp_enqueue_script('jquery-ui-sortable', '/wp-includes/js/jquery/ui.sortable.js', array('jquery-ui-core'), '1.5');
			
			wp_enqueue_script('swfupload');
			if (version_compare($wp_version,"2.6.9","<")) wp_enqueue_script('swfupload-degrade');
			else wp_enqueue_script('swfupload-swfobject');
		}
		
		?>
		<link rel='stylesheet' href='<?php echo $this->uri; ?>/core/ui/styles/admin.css' type='text/css' />
		<?php
	}
	
	/**
	 * dashbaord_css()
	 * Loads only the Shopp Admin CSS on the WordPress dashboard for widget styles */
	function dashboard_css () {
		?><link rel='stylesheet' href='<?php echo $this->uri; ?>/core/ui/styles/admin.css' type='text/css' />
<?php
	}
	
	/**
	 * dashboard_init()
	 * Initializes the Shopp dashboard widgets */
	function dashboard_init () {
		
		wp_register_sidebar_widget('dashboard_shopp_stats', 'Shopp Stats', array(&$this->Flow,'dashboard_stats'),
			array('all_link' => '','feed_link' => '','width' => 'half','height' => 'single')
		);

		wp_register_sidebar_widget('dashboard_shopp_orders', 'Shopp Orders', array(&$this->Flow,'dashboard_orders'),
			array('all_link' => 'admin.php?page='.$this->Flow->Admin->orders,'feed_link' => '','width' => 'half','height' => 'single')
		);

		wp_register_sidebar_widget('dashboard_shopp_products', 'Shopp Products', array(&$this->Flow,'dashboard_products'),
			array('all_link' => 'admin.php?page='.$this->Flow->Admin->products,'feed_link' => '','width' => 'half','height' => 'single')
		);
		
	}

	/**
	 * dashboard ()
	 * Adds the Shopp dashboard widgets to the WordPress Dashboard */
	function dashboard ($widgets) {
		$dashboard = $this->Settings->get('dashboard');
		if (current_user_can('manage_options') && $dashboard == "on")
			array_unshift($widgets,'dashboard_shopp_stats','dashboard_shopp_orders','dashboard_shopp_products');
		return $widgets;
	}
	
	/**
	 * behaviors()
	 * Dynamically includes necessary JavaScript and stylesheets as needed in 
	 * public shopping pages handled by Shopp */
	function behaviors () {
		global $wp_query;
		$object = $wp_query->get_queried_object();
		
		// Determine which tag is getting used in the current post/page
		$tag = false;
		$tagregexp = join( '|', array_keys($this->shortcodes) );
		foreach ($wp_query->posts as $post) {
			if (preg_match('/\[('.$tagregexp.')\b(.*?)(?:(\/))?\](?:(.+?)\[\/\1\])?/',$post->post_content,$matches))
				$tag = $matches[1];
		}

		// Include stylesheets and javascript based on whether shopp shortcodes are used
		add_action('wp_head', array(&$this, 'header'));
		add_action('wp_footer', array(&$this, 'footer'));
		wp_enqueue_script('jquery');
		wp_enqueue_script('shopp-settings',"$this->shopuri?shopp_lookup=settings.js");
		wp_enqueue_script("shopp-thickbox","{$this->uri}/core/ui/behaviors/thickbox.js");
		wp_enqueue_script("shopp","{$this->uri}/core/ui/behaviors/shopp.js");

		if ($tag == "checkout")
			wp_enqueue_script('shopp_checkout',"{$this->uri}/core/ui/behaviors/checkout.js");		
			
	}
		
	/**
	 * shortcodes()
	 * Handles shortcodes used on Shopp-installed pages and used by
	 * site owner for including categories/products in posts and pages */
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
	
	function tinymce () {
		if (!current_user_can('edit_posts') && !current_user_can('edit_pages')) return;

		// Add TinyMCE buttons when using rich editor
		if (get_user_option('rich_editing') == 'true') {
			add_filter('tiny_mce_version', array(&$this,'mceupdate')); // Move to plugin activation
			add_filter('mce_external_plugins', array(&$this,'mceplugin'),5);
			add_filter('mce_buttons', array(&$this,'mcebutton'),5);
		}
	}

	function mceplugin ($plugins) {
		$plugins['Shopp'] = $this->uri.'/core/ui/behaviors/tinymce/editor_plugin.js';
		return $plugins;
	}

	function mcebutton ($buttons) {
		array_push($buttons, "separator", "Shopp");
		return $buttons;
	}

	function my_change_mce_settings( $init_array ) {
	    $init_array['disk_cache'] = false; // disable caching
	    $init_array['compress'] = false; // disable gzip compression
	    $init_array['old_cache_max'] = 3; // keep 3 different TinyMCE configurations cached (when switching between several configurations regularly)
	}

	function mceupdate($ver) {
	  return ++$ver;
	}
	
	
	/**
	 * page_updates()
	 * Handles changes to Shopp-installed pages that may affect 'pretty' urls */
	function page_updates ($update=false,$updates=false) {
		global $wpdb;
		$pages = $this->Settings->get('pages');
		
		if (!empty($pages)) {
			$updates = false;
			foreach($pages as $page) if ($page['id'] == $update_id) $updates = true;
		}
		
		// No pages setting, rebuild it
		if (empty($pages) || $updates || $update) {
			$pages = $this->Flow->Pages;
			
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
						$page['permalink'] = str_replace(trailingslashit(get_bloginfo('wpurl')),'',get_permalink($page['id']));
						// trailingslashit(preg_replace('|https?://[^/]+/|i','',get_permalink($page['id'])));
						if ($page['permalink'] == get_bloginfo('wpurl')) $page['permalink'] = "";
						break;
					}
				}
			}
			
			$this->Settings->save('pages',$pages);

		}
		return $update;
	}
			
	/**
	 * rewrites()
	 * Adds Shopp-specific pretty-url rewrite rules to the WordPress rewrite rules */
	function rewrites ($wp_rewrite_rules) {
		$this->page_updates(true);
		$pages = $this->Settings->get('pages');
		if (!$pages) $pages = $this->Flow->Pages;
		$shop = $pages['catalog']['permalink'];
		if (!empty($shop)) $shop = trailingslashit($shop);
		$catalog = $pages['catalog']['name'];
		$checkout = $pages['checkout']['permalink'];

		$rules = array(
			$checkout.'?$' => 'index.php?pagename='.$checkout.'&shopp_proc=checkout',
			(empty($shop)?"$catalog/":$shop).'feed/?$' => 'index.php?shopp_lookup=newproducts-rss',
			(empty($shop)?"$catalog/":$shop).'receipt/?$' => 'index.php?pagename='.$checkout.'&shopp_proc=receipt',
			(empty($shop)?"$catalog/":$shop).'confirm-order/?$' => 'index.php?pagename='.$checkout.'&shopp_proc=confirm-order',
			(empty($shop)?"$catalog/":$shop).'download/([a-z0-9]{40})/?$' => 'index.php?shopp_download=$matches[1]',
			(empty($shop)?"$catalog/":$shop).'images/(\d+)/?.*?$' => 'index.php?shopp_image=$matches[1]'
		);

		// catalog/category/category-slug
		if (empty($shop)) {
			$rules[$catalog.'/category/([a-zA-Z0-9_\-\/]+?)/feed/?$'] = 'index.php?shopp_lookup=category-rss&shopp_category=$matches[1]';
			$rules[$catalog.'/category/([a-zA-Z0-9_\-\/]+?)/page/?([0-9]{1,})/?$'] = 'index.php?pagename='.$catalog.'&shopp_category=$matches[1]&paged=$matches[2]';
			$rules[$catalog.'/category/([a-zA-Z0-9_\-\/]+?)/?$'] = 'index.php?pagename='.$catalog.'&shopp_category=$matches[1]';
		} else {
			$rules[$shop.'category/([a-zA-Z0-9_\-\/]+?)/feed/?$'] = 'index.php?shopp_lookup=category-rss&shopp_category=$matches[1]';
			$rules[$shop.'category/([a-zA-Z0-9_\-\/]+?)/page/?([0-9]{1,})/?$'] = 'index.php?pagename='.$shop.'&shopp_category=$matches[1]&paged=$matches[2]';
			$rules[$shop.'category/([a-zA-Z0-9_\-\/]+?)?$'] = 'index.php?pagename='.$shop.'&shopp_category=$matches[1]';
		}

		// tags
		if (empty($shop)) {
			$rules[$catalog.'/tag/([a-zA-Z0-9%_\+\-\/]+?)/feed/?$'] = 'index.php?shopp_lookup=category-rss&shopp_tag=$matches[1]';
			$rules[$catalog.'/tag/([a-zA-Z0-9%_\+\-\/]+?)/page/?([0-9]{1,})/?$'] = 'index.php?pagename='.$catalog.'&shopp_tag=$matches[1]&paged=$matches[2]';
			$rules[$catalog.'/tag/([a-zA-Z0-9%_\+\-\/]+?)/?$'] = 'index.php?pagename='.$catalog.'&shopp_tag=$matches[1]';
		} else {
			$rules[$shop.'tag/([a-zA-Z0-9%_\+\-\/]+?)/feed/?$'] = 'index.php?shopp_lookup=category-rss&shopp_tag=$matches[1]';
			$rules[$shop.'tag/([a-zA-Z0-9%_\+\-\/]+?)/page/?([0-9]{1,})/?$'] = 'index.php?pagename='.$shop.'&shopp_tag=$matches[1]&paged=$matches[2]';
			$rules[$shop.'tag/([a-zA-Z0-9%_\+\-\/]+?)/?$'] = 'index.php?pagename='.$shop.'&shopp_tag=$matches[1]';
		}

		// catalog/productid
		if (empty($shop)) $rules[$catalog.'/(\d+(,\d+)?)/?$'] = 'index.php?pagename='.$catalog.'&shopp_pid=$matches[1]';
		else $rules[$shop.'(\d+(,\d+)?)/?$'] = 'index.php?pagename='.$shop.'&shopp_pid=$matches[1]';

		// catalog/category/product-slug
		if (empty($shop)) $rules[$catalog.'/([a-zA-Z0-9_\-\/]+?)/([a-zA-Z0-9_\-]+?)/?$'] = 'index.php?pagename='.$catalog.'&shopp_category=$matches[1]&shopp_product=$matches[2]'; // category/product-slug
		else $rules[$shop.'([a-zA-Z0-9_\-\/]+?)/([a-zA-Z0-9_\-]+?)/?$'] = 'index.php?pagename='.$shop.'&shopp_category=$matches[1]&shopp_product=$matches[2]'; // category/product-slug			

		return $rules + $wp_rewrite_rules;
	}
	
	/**
	 * queryvars()
	 * Registers the query variables used by Shopp */
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

		return $vars;
	}
	
	/**
	 * orders()
	 * Handles order administration screens */
	function orders () {
		if ($this->Settings->get('display_welcome') == "on") {
			$this->welcome(); return;
		}
		if (isset($_GET['manage'])) $this->Flow->order_manager();
		else $this->Flow->orders_list();
	}

	/**
	 * categories()
	 * Handles category administration screens */
	function categories () {
		if ($this->Settings->get('display_welcome') == "on") {
			$this->welcome(); return;
		}
		if (isset($_GET['edit'])) $this->Flow->category_editor();
		else $this->Flow->categories_list();
	}

	/**
	 * products()
	 * Handles product administration screens */
	function products () {
		if ($this->Settings->get('display_welcome') == "on") {
			$this->welcome(); return;
		}
		if (isset($_GET['edit'])) $this->Flow->product_editor();
		elseif (isset($_GET['category'])) $this->Flow->category_editor();
		elseif (isset($_GET['categories'])) $this->Flow->categories_list();
		else $this->Flow->products_list();
	}

	/**
	 * promotions()
	 * Handles product administration screens */
	function promotions () {
		if ($this->Settings->get('display_welcome') == "on") {
			$this->welcome(); return;
		}
		if (isset($_GET['promotion'])) $this->Flow->promotion_editor();
		else $this->Flow->promotions_list();
	}

	/**
	 * settings()
	 * Handles settings administration screens */
	function settings () {
		if ($this->Settings->get('display_welcome') == "on" && empty($_POST['setup'])) {
			$this->welcome(); return;
		}

		switch($_GET['edit']) {
			case "catalog": 		$this->Flow->settings_catalog(); break;
			case "cart": 			$this->Flow->settings_cart(); break;
			case "checkout": 		$this->Flow->settings_checkout(); break;
			case "payments": 		$this->Flow->settings_payments(); break;
			case "shipping": 		$this->Flow->settings_shipping(); break;
			case "taxes": 			$this->Flow->settings_taxes(); break;
			case "presentation":	$this->Flow->settings_presentation(); break;
			case "system":			$this->Flow->settings_system(); break;
			case "update":			$this->Flow->settings_update(); break;
			default: 				$this->Flow->settings_general();
		}
		
	}

	/**
	 * titles ()
	 * Changes the Shopp catalog page titles to include the product
	 * name and category (when available) */
	function titles ($title,$sep=" &mdash; ",$placement="left") {
		if ($placement == "right") {
			if (isset($this->Product)) $title =  $this->Product->name." $sep ".$title;
			if (isset($this->Category)) $title = $this->Category->name." $sep ".$title;
			
		} else {
			if (isset($this->Product)) $title .=  " $sep ".$this->Product->name;
			if (isset($this->Category)) $title .= " $sep ".$this->Category->name;
		}
		return $title;
	}

	function feeds () {
		if (empty($this->Category)):?>
	<link rel='alternate' type="application/rss+xml" title="<?php bloginfo('name'); ?> New Products RSS Feed" href="<?php echo $this->shopuri.((SHOPP_PERMALINKS)?'/feed/':'?shopp_lookup=newproducts-rss'); ?>" />
	<?php
			else:
			$uri = 'category/'.$this->Category->uri;
			if ($this->Category->slug == "tag") $uri = $this->Category->slug.'/'.$this->Category->tag;
			$link = $this->shopuri.((SHOPP_PERMALINKS)?'/'.$uri.'/feed/':'?shopp_category='.$this->Category->id.'&shopp_lookup=category-rss')
			?>
	<link rel='alternate' type="application/rss+xml" title="<?php bloginfo('name'); ?> <?php echo $this->Category->name; ?> RSS Feed" href="<?php echo $link; ?>" />
	<?php
		endif;
	}

	function updatesearch () {
		global $wp_query;
		$wp_query->query_vars['s'] = $this->Cart->data->Search;
		// $wp->is_search = false;
		//echo "<pre>"; print_r($wp); echo "</pre>";
	}

	function metadata () {
		if (!empty($this->Product)): 
			$tags = "";
			if (empty($this->Product->tags)) $this->Product->load_data(array('tags'));
			foreach($this->Product->tags as $tag)
				$tags .= (!empty($tags))?", {$tag->name}":$tag->name;
		?>
		<meta name="keywords" content="<?php echo attribute_escape($tags); ?>" />
		<meta name="description" content="<?php echo attribute_escape($this->Product->summary); ?>" />
	<?php
		endif;
	}

	/**
	 * header()
	 * Adds stylesheets necessary for Shopp public shopping pages */
	function header () {		
		?><link rel='stylesheet' href='<?php echo $this->shopuri; ?>?shopp_lookup=catalog.css' type='text/css' />
		<link rel='stylesheet' href='<?php echo SHOPP_TEMPLATES_URI; ?>/shopp.css' type='text/css' />
		<link rel='stylesheet' href='<?php echo $this->uri; ?>/core/ui/styles/thickbox.css' type='text/css' />
		<?php
	}
	
	/**
	 * footer()
	 * Adds report information and custom debugging tools to the public and admin footers */
	function footer () {
		if (!SHOPP_DEBUG) return true;
		$db = DB::get();
		global $wpdb;
		
		if (current_user_can('manage_options')) {
			if (function_exists('memory_get_peak_usage'))
				$this->_debug->memory .= "End: ".number_format(memory_get_peak_usage(true)/1024/1024, 2, '.', ',') . " MB<br />";
			elseif (function_exists('memory_get_usage'))
				$this->_debug->memory .= "End: ".number_format(memory_get_usage(true)/1024/1024, 2, '.', ',') . " MB";

			echo '<script type="text/javascript">'."\n";
			echo '//<![CDATA['."\n";
			echo 'var memory_profile = "'.$this->_debug->memory.'";';
			echo 'var wpquerytotal = '.$wpdb->num_queries.';';
			echo 'var shoppquerytotal = '.count($db->queries).';';
			echo '//]]>'."\n";
			echo '</script>'."\n";
		}

	}
	
	function catalog ($wp) {
		$pages = $this->Settings->get('pages');
		// echo "<pre>"; print_r($wp->query_vars); echo "</pre>";
		
		
		$type = "catalog";
		if ($category = $wp->query_vars['shopp_category']) $type = "category";
		if ($productid = $wp->query_vars['shopp_pid']) $type = "product";
		if ($productname = $wp->query_vars['shopp_product']) $type = "product";

		if ($tag = $wp->query_vars['shopp_tag']) {
			$type = "category";
			$category = "tag";
		}

		$referer = wp_get_referer();
		if (!empty($wp->query_vars['s']) && // Search query is present and...
			// The referering page is includes a Shopp catalog page path
			(strpos($referer,$this->link('catalog')) !== false || 
				// Or the referer was a search that matches the last recorded Shopp search
				substr($referer,-1*(strlen($this->Cart->data->Search))) == $this->Cart->data->Search || 
				// Or the blog URL matches the Shopp catalog URL (Takes over search for store-only search)
				trailingslashit(get_bloginfo('wpurl')) == $this->link('catalog') || 
				// Or the referer is one of the Shopp cart, checkout or account pages
				$referer == $this->link('cart') || $referer == $this->link('checkout') || 
				$referer == $this->link('account'))) {
			$this->Cart->data->Search = $wp->query_vars['s'];
			$wp->query_vars['s'] = "";
			$wp->query_vars['pagename'] = $pages['catalog']['name'];
			add_action('wp_head', array(&$this, 'updatesearch'));
			$type = "category"; 
			$category = "search-results";
		}
		
		// Find product by given ID
		if (!empty($productid) && empty($this->Product->id)) {
			$this->Product = new Product($productid);
		}

		if (!empty($category) || !empty($tag)) {
			if (strpos($category,"/") !== false) {
				$categories = split("/",$category);
				$category = end($categories);
			}
			
			switch ($category) {
				case SearchResults::$slug: 
					$this->Category = new SearchResults(array('search'=>$this->Cart->data->Search)); break;
				case TagProducts::$slug: 
					$this->Category = new TagProducts(array('tag'=>$tag)); break;
				case BestsellerProducts::$slug: $this->Category = new BestsellerProducts(); break;
				case NewProducts::$slug: $this->Category = new NewProducts(); break;
				case FeaturedProducts::$slug: $this->Category = new FeaturedProducts(); break;
				case OnSaleProducts::$slug: $this->Category = new OnSaleProducts(); break;
				default:
					$key = "id";
					if (!preg_match("/\d+/",$category)) $key = "slug";
					$this->Category = new Category($category,$key);
			}

		}
		
		
		// Category Filters
		if (!empty($this->Category->slug)) {
			if (empty($this->Cart->data->Category[$this->Category->slug]))
				$this->Cart->data->Category[$this->Category->slug] = array();
			$CategoryFilters =& $this->Cart->data->Category[$this->Category->slug];
			if (is_array($_GET['shopp_catfilters']))
				$CategoryFilters = array_merge($CategoryFilters,$_GET['shopp_catfilters']);
		}
		
		// Catalog sort order setting
		if (isset($_GET['shopp_orderby'])) {
			$this->Cart->data->Category['orderby'] = $_GET['shopp_orderby'];
		}
			
		// Find product by category name and product name
		if (!empty($productname) && empty($this->Product->id)) {
			$this->Product = new Product($productname,"slug");
		}
		
		$this->Catalog = new Catalog($type);
		add_filter('wp_title', array(&$this, 'titles'),10,3);
		add_action('wp_head', array(&$this, 'metadata'));
		add_action('wp_head', array(&$this, 'feeds'));

	}
		
	/**
	 * cart()
	 * Handles shopping cart requests */
	function cart () {
		if (empty($_REQUEST['cart'])) return true;

		$this->Flow->cart_request();
		if (isset($_REQUEST['ajax'])) $this->Flow->cart_ajax();
		switch ($_REQUEST['redirect']) {
			case "checkout": header("Location: ".$this->link($_REQUEST['redirect'],true)); break;
			default: 
				if (!empty($_REQUEST['redirect']))
					header("Location: ".$this->link($_REQUEST['redirect']));
				else header("Location: ".$this->link('cart'));
		}
		exit();
	}
	
	/**
	 * checkout()
	 * Handles checkout process */
	function checkout ($wp) {
		$Order = $this->Cart->data->Order;

		$gateway = false;
		// Intercept external checkout processing
		if (!empty($wp->query_vars['shopp_xco'])) {
			$gateway = "{$this->path}/gateways/{$wp->query_vars['shopp_xco']}.php";
			if (file_exists($gateway)) {
				$gateway_meta = $this->Flow->scan_gateway_meta($gateway);
				$ProcessorClass = $gateway_meta->tags['class'];
				include($gateway);
				$Payment = new $ProcessorClass();
				if ($wp->query_vars['shopp_proc'] != "confirm-order" && 
						empty($_POST['checkout'])) $Payment->checkout();
			}
		}
		
		if (empty($_POST['checkout'])) return true;
		if ($_POST['checkout'] == "confirmed") {
			$this->Flow->order($gateway);
			return true;
		}
		if ($_POST['checkout'] != "process") return true;
		
		if ($_POST['process-login'] == "login") {
			if (isset($_POST['email-login'])) $this->Flow->login($_POST['email-login'],$_POST['password-login'],'email');
			else if (isset($_POST['loginname-login'])) $this->Flow->login($_POST['loginname-login'],$_POST['password-login'],'loginname');
			return true;
		}
		
		$_POST['billing']['cardexpires'] = sprintf("%02d%02d",$_POST['billing']['cardexpires-m'],$_POST['billing']['cardexpires-y']);
		
		if (isset($_POST['data'])) $Order->data = $_POST['data'];
		if (empty($Order->Customer))
			$Order->Customer = new Customer();
		$Order->Customer->updates($_POST);
		$Order->Customer->confirm_password = $_POST['confirm-password'];

		if (empty($Order->Billing))
			$Order->Billing = new Billing();
		$Order->Billing->updates($_POST['billing']);
		
		if (!empty($_POST['billing']['cardexpires-mm']) && !empty($_POST['billing']['cardexpires-yy'])) {
			$Order->Billing->cardexpires = mktime(0,0,0,
					$_POST['billing']['cardexpires-mm'],
					1,
					($_POST['billing']['cardexpires-yy'])+2000
				);
		}
		
		$Order->Billing->cvv = $_POST['billing']['cvv'];

		if (empty($Order->Shipping))
			$Order->Shipping = new Shipping();
			
		if ($_POST['shipping']) $Order->Shipping->updates($_POST['shipping']);
		if (!empty($_POST['shipmethod'])) $Order->Shipping->method = $_POST['shipmethod'];
		else $Order->Shipping->method = key($this->Cart->data->ShipCosts);

		// Override posted shipping updates with billing address
		if ($_POST['sameshipaddress'] == "on")
			$Order->Shipping->updates($Order->Billing,
				array("_datatypes","_table","_key","_lists","id","created","modified"));
		
		// Check for taxes, or process order
		if ($this->Settings->get('taxes') == "on") {
			$taxrates = $this->Settings->get('taxrates');
			$this->Cart->data->Totals->taxrate = 0;
			if (!empty($taxrates)) {
				foreach($taxrates as $setting) {
					if ($Order->Shipping->state == $setting['zone']) {
						$this->Cart->data->Totals->taxrate = $setting['rate'];
						break;					
					}
				}
			}

			$this->Cart->totals();

			if ($this->Cart->data->Totals->tax > 0 || 
					$this->Settings->get('order_confirmation') == "always") {
				header("Location: ".$this->link('confirm-order','',true));
				exit();
			} else $this->Flow->order();
		} elseif ($this->Settings->get('order_confirmation') == "always") {
			header("Location: ".$this->link('confirm-order','',true));
			exit();
		} else $this->Flow->order();
	}

	/**
	 * xorder ()
	 * Handle external checkout system order notifications */
	function xorder () {
		$gateway = false;
		if (!empty($_GET['shopp_xorder'])) {
			$gateway = "{$this->path}/gateways/{$_GET['shopp_xorder']}/{$_GET['shopp_xorder']}.php";
			if (file_exists($gateway)) {
				$gateway_meta = $this->Flow->scan_gateway_meta($gateway);
				$ProcessorClass = $gateway_meta->tags['class'];
				include($gateway);
				$Payment = new $ProcessorClass();
				$Payment->process();
			}
		}
	}
		
	/**
	 * link ()
	 * Builds a full URL for a specific Shopp-related resource */
	function link ($target,$secure=false) {
		$internals = array("receipt","confirm-order");
		$pages = $this->Settings->get('pages');
		if (!is_array($pages)) $pages = $this->Flow->Pages;
		
		$uri = ($secure)?str_replace('http://','https://',get_bloginfo('wpurl')):get_bloginfo('wpurl');

		if (array_key_exists($target,$pages)) $page = $pages[$target];
		else {
			if (in_array($target,$internals)) {
				$page = $pages['checkout'];
				if (SHOPP_PERMALINKS) 
					$page['permalink'] = $pages['catalog']['permalink'].trailingslashit($target);
				else $page['id'] .= "&shopp_proc=$target";
			}
			else $page = $pages['catalog'];
 		}
		
		if (SHOPP_PERMALINKS) return $uri."/".$page['permalink'];
		else return $uri.'?page_id='.$page['id'];
	}
	
	/**
	 * help()
	 * This function provides graceful degradation when the 
	 * contextual javascript behavior isn't working, this
	 * provides the default behavior of showing a help gateway
	 * page with instructions on where to find help on Shopp. */
	function help () {
		include(SHOPP_ADMINPATH."/help/help.php");
	}

	function welcome () {
		include(SHOPP_ADMINPATH."/help/welcome.php");
	}
	
	/**
	 * AJAX Responses */
	
	/**
	 * lookups ()
	 * Provides fast db lookups with as little overhead as possible */
	function lookups($wp) {
		// global $wp_rewrite;
		// $pages = $this->Settings->get('pages');
		// echo "<pre>"; print_r($wp); echo "</pre>";
		// echo "<pre>"; print_r($wp_rewrite); echo "</pre>";
		// echo "<pre>"; print_r($pages); echo "</pre>";

		// Grab query requests from permalink rewriting query vars
		$admin = false;
		$download = $wp->query_vars['shopp_download'];
		$lookup = $wp->query_vars['shopp_lookup'];
				
		// Admin Lookups
		if ($_GET['page'] == "shopp/lookup") {
			$admin = true;
			$image = $_GET['id'];
			$download = $_GET['download'];
		}
		
		if (!empty($download)) $lookup = "download";
		if (empty($lookup)) $lookup = $_GET['lookup'];
		
		switch($lookup) {
			case "zones":
				$zones = $this->Settings->get('zones');
				if (isset($_GET['country']))
					echo json_encode($zones[$_GET['country']]);
				exit();
				break;
			case "shipcost":
				$this->init();
				if (isset($_GET['method'])) {
					$this->Cart->data->Order->Shipping->shipmethod = $_GET['method'];
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
				$result->options = unserialize($result->options);
				$result->prices = unserialize($result->prices);
				foreach ($result->options as &$menu) {
					foreach ($menu['options'] as &$option) $option['id'] += $_GET['cat'];
				}
				foreach ($result->prices as &$price) {
					$optionids = split(",",$price['options']);
					foreach ($optionids as &$id) $id += $_GET['cat'];
					$price['options'] = join(",",$optionids);
					$price['optionkey'] = "";
				}
				
				echo json_encode($result);
				exit();
				break;
			case "newproducts-rss":
				$NewProducts = new NewProducts(array('show' => 5000));
				echo shopp_rss($NewProducts->rss());
				exit();
				break;
			case "category-rss":
				$this->catalog($wp);
				echo shopp_rss($this->Category->rss());
				exit();
				break;
			case "download":
				if (empty($download)) break;
				$storage = $this->Settings->get('product_storage');
				$path = rtrim($this->Settings->get('products_path'),"/");
				
				
				if ($admin) {
					$Asset = new Asset($download);
				} else {
					require_once("core/model/Purchased.php");
					$Purchased = new Purchased($download,"dkey");
					$Asset = new Asset($Purchased->download);
										
					$forbidden = false;
					// Download limit checking
					if (($this->Settings->get('download_limit') && !($Purchased->downloads < $this->Settings->get('download_limit'))) &&  // Has download credits available
						($this->Settings->get('download_timelimit') && $Purchased->created < mktime()+$this->Settings->get('download_timelimit') ))
								$forbidden = true;

					if ($this->Settings->get('download_restriction') == "ip") {
						$Purchase = new Purchase($Purchased->purchase);
						if ($Purchase->ip != $_SERVER['REMOTE_ADDR']) $forbidden = true;
					}
				}
				
				if ($forbidden) {
					header("Status: 403 Forbidden");
					header("Location: ".$this->link(''));
					exit();
				}
				
				header ("Content-type: ".$Asset->properties['mimetype']); 
				header ("Content-Disposition: inline; filename=".$Asset->name); 
				header ("Content-Description: Delivered by WordPress/Shopp ".SHOPP_VERSION);
				if ($storage == "fs") {
					$filepath = join("/",array($path,$Asset->value,$Asset->name));
					header ("Content-length: ".@filesize($filepath)); 
					readfile($filepath);
				} else {
					header ("Content-length: ".strlen($Asset->data)); 
					echo $Asset->data;
				}
				
				$Purchased->downloads++;
				$Purchased->save();
				exit();
				break;
		}
	}

	/**
	 * ajax ()
	 * Handles AJAX request processing */
	function ajax() {
		
		switch($_GET['action']) {
			
			// Add a category in the product editor
			case "wp_ajax_shopp_add_category":
				if (!empty($_GET['name'])) {
					$Catalog = new Catalog();
					$Catalog->load_categories();
					
					$Category = new Category();
					$Category->name = $_GET['name'];
					$Category->slug = sanitize_title_with_dashes($Category->name);
					$Category->parent = $_GET['parent'];

					// Work out pathing
					$paths = array();
					if (!empty($Category->slug)) $paths = array($Category->slug);
					$uri = "/".$Category->slug;

					// If we're saving a new category, lookup the parent
					for ($i = count($this->Catalog->categories); $i > 0; $i--)
						if ($Category->parent == $this->Catalog->categories[$i]->id) break;
						array_unshift($paths,$this->Catalog->categories[$i]->slug);
					$uri = "/".$this->Catalog->categories[$i]->slug.$uri;

					$parentkey = $this->Catalog->categories[$i]->parentkey;
					while ($parentkey > -1) {
						$tree_category = $this->Catalog->categories[$parentkey];
						array_unshift($paths,$tree_category->slug);
						$uri = "/".$tree_category->slug.$uri;
						$parentkey = $tree_category->parentkey;
					}

					$Category->uri = join("/",$paths);
					
					$Category->save();
					echo json_encode($Category);
				}
				exit();
				break;

			case "wp_ajax_shopp_edit_slug":
				
				switch ($_REQUEST['type']) {
					case "category":
						$Category = new Category($_REQUEST['id']);
						if (empty($_REQUEST['slug'])) $_REQUEST['slug'] = $Category->name;
						$Category->slug = sanitize_title_with_dashes($_REQUEST['slug']);
						if ($Category->save()) echo $Category->slug;
						else echo '-1';
						break;
					case "product":
						$Product = new Product($_REQUEST['id']);
						if (empty($_REQUEST['slug'])) $_REQUEST['slug'] = $Product->name;
						$Product->slug = sanitize_title_with_dashes($_REQUEST['slug']);
						if ($Product->save()) echo $Product->slug;
						else echo '-1';
						break;
				}
				exit();
				break;
				
			// Upload an image in the product editor
			case "wp_ajax_shopp_add_image":
				$this->Flow->add_images();
				exit();
				break;
				
			// Upload a product download file in the product editor
			case "wp_ajax_shopp_add_download":
				$this->Flow->product_downloads();
				exit();
				break;

			// Upload a product download file in the product editor
			case "wp_ajax_shopp_verify_file":
				$basepath = trailingslashit($this->Settings->get('products_path'));
				if (!file_exists($basepath.$_POST['filepath'])) die("NULL");
				if (is_dir($basepath.$_POST['filepath'])) die("ISDIR");
				if (!is_readable($basepath.$_POST['filepath'])) die("READ");
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

} // end Shopp

/**
 * shopp()
 * Provides the Shopp 'tag' support to allow for complete 
 * customization of customer interfaces
 *
 * @param $object - The object to get the tag property from
 * @param $property - The property of the object to get/output
 * @param $options - Custom options for the property result in query form (option1=value&option2=value&...)
 */
function shopp () {
	global $Shopp;
	$args = func_get_args();

	$object = strtolower($args[0]);
	$property = strtolower($args[1]);
	$paramsets = $args[2];
	$paramsets = split("&",$paramsets);

	$options = array();
	foreach ($paramsets as $paramset) {
		list($key,$value) = split("=",$paramset);
		$options[strtolower($key)] = $value;
	}
	
	$result = "";
	switch (strtolower($object)) {
		case "cart": $result = $Shopp->Cart->tag($property,$options); break;
		case "cartitem": $result = $Shopp->Cart->itemtag($property,$options); break;
		case "checkout": $result = $Shopp->Cart->checkouttag($property,$options); break;
		case "category": $result = $Shopp->Category->tag($property,$options); break;
		case "catalog": $result = $Shopp->Catalog->tag($property,$options); break;
		case "product": $result = $Shopp->Product->tag($property,$options); break;
		case "purchase": $result = $Shopp->Cart->data->Purchase->tag($property,$options); break;
	}

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
	if (isset($options['return']) && value_is_true($options['return']))
		return $result;

	// Output the result
	echo $result;
	return true;
}

?>