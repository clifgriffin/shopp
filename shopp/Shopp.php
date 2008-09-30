<?php
/*
Plugin Name: Shopp
Version: 1.0b1
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

define("SHOPP_VERSION","1.0b1");
define("SHOPP_GATEWAY_USERAGENT","WordPress Shopp Plugin/".SHOPP_VERSION);
define("SHOPP_HOME","http://shopplugin.net/");
define("SHOPP_DOCS","http://docs.shopplugin.net/");
define("SHOPP_DEBUG",true);

require("core/functions.php");
require("core/DB.php");
require("core/Flow.php");

require("core/model/Settings.php");
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
			$this->_debug->memory = "Initial: ".number_format(memory_get_usage()/1024, 2, '.', ',') . " KB<br />";
		}
		
		$this->path = dirname(__FILE__);
		$this->file = basename(__FILE__);
		$this->directory = basename($this->path);
		$this->uri = get_bloginfo('wpurl')."/wp-content/plugins/".$this->directory;
		$this->wpadminurl = get_bloginfo('wpurl')."/wp-admin/admin.php";
		
		$this->Settings = new Settings();
		$this->Flow = new Flow($this);

		// Keep any DB operations from occuring while in maintenance mode
		if (!empty($_GET['updated']) && $this->Settings->get('maintenance') == "on"){
			if ($this->Flow->upgrade()) $this->Settings->save("maintenance","off");
		} elseif ($this->Settings->get('maintenance') == "on") {
			add_action('wp', array(&$this, 'shortcodes'));
			return true;
		}
		
		// register_deactivation_hook(__FILE__, array(&$this, 'deactivate'));
		register_activation_hook("shopp/Shopp.php", array(&$this, 'install'));

		// Initialize defaults if they have not been entered
		if (!$this->Settings->get('shopp_setup')) {
			$this->Flow->setup();
		}
		
		if (!SHOPP_LOOKUP) add_action('init',array(&$this,'init'));

		add_action('init', array(&$this, 'ajax'));
		add_action('init', array(&$this, 'xorder'));
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
		add_action('admin_footer', array(&$this, 'footer'));
		add_action('wp_dashboard_setup', array(&$this, 'dashboard_init'));
		add_action('wp_dashboard_widgets', array(&$this, 'dashboard'));
		add_action('admin_print_styles-index.php', array(&$this, 'dashboard_css'));
		add_action('save_post', array(&$this, 'page_updates'),10,2);

		add_action('widgets_init', array(&$this->Flow, 'init_cart_widget'));
		add_action('widgets_init', array(&$this->Flow, 'init_categories_widget'));
		add_filter('wp_list_pages',array(&$this->Flow,'secure_checkout_link'));

		add_action('rewrite_rules', array(&$this,'page_updates'));
		add_filter('rewrite_rules_array',array(&$this,'rewrites'));
		add_filter('query_vars', array(&$this,'queryvars'));
		return true;
	}
	
	function init() {
		$this->Cart = new Cart();
		session_start();
		
		$this->Catalog = new Catalog();
		$this->ShipCalcs = new ShipCalcs($this->Settings,$this->path);
	}

	/**
	 * install()
	 * Installs the tables and initializes settings */
	function install () {

		// If no settings are available,
		// no tables exist, so this is a
		// new install
		if ($this->Settings->unavailable) 
			include("core/install.php");
		
		if ($this->Settings->get('version') != SHOPP_VERSION)
			$this->Flow->upgrade();
				
		// If the plugin has been previously setup
		// dump the datatype model cache so it can be rebuilt
		// Useful when table schemas change so we can
		// force the in memory data model to get rebuilt
		if ($this->Settings->get('shopp_setup'))
			$this->Settings->save('data_model','');

	}
	
	/**
	 * deactivate()
	 * Resets the data_model to prepare for potential upgrades/changes to the table schema */
	function deactivate() {
		$this->Settings->save('data_model','');  // Clear the data model cache
		return true;
	}
	
	/**
	 * add_menus()
	 * Adds the WordPress admin menus */
	function add_menus () {
		$main = add_menu_page('Shopp', 'Shopp', 8, $this->Flow->Admin->default, array(&$this,'orders'));
		$orders = add_submenu_page($this->Flow->Admin->default,__('Orders','Shopp'), __('Orders','Shopp'), 8, $this->Flow->Admin->orders, array(&$this,'orders'));
		$products = add_submenu_page($this->Flow->Admin->default,__('Products','Shopp'), __('Products','Shopp'), 8, $this->Flow->Admin->products, array(&$this,'products'));
		$promotions = add_submenu_page($this->Flow->Admin->default,__('Promotions','Shopp'), __('Promotions','Shopp'), 8, $this->Flow->Admin->promotions, array(&$this,'promotions'));
		$settings = add_submenu_page($this->Flow->Admin->default,__('Settings','Shopp'), __('Settings','Shopp'), 8, $this->Flow->Admin->settings, array(&$this,'settings'));
		$help = add_submenu_page($this->Flow->Admin->default,__('Help','Shopp'), __('Help','Shopp'), 8, $this->Flow->Admin->help, array(&$this,'help'));
		add_action("admin_print_scripts-$main", array(&$this, 'admin_behaviors'));
		add_action("admin_print_scripts-$orders", array(&$this, 'admin_behaviors'));
		add_action("admin_print_scripts-$products", array(&$this, 'admin_behaviors'));
		add_action("admin_print_scripts-$promotions", array(&$this, 'admin_behaviors'));
		add_action("admin_print_scripts-$settings", array(&$this, 'admin_behaviors'));
		add_action("admin_print_scripts-$help", array(&$this, 'admin_behaviors'));
	}

	/**
	 * admin_behaviors()
	 * Dynamically includes necessary JavaScript and stylesheets for the admin */
	function admin_behaviors () {
		wp_enqueue_script('jquery');
		wp_enqueue_script('shopp',"{$this->uri}/core/ui/behaviors/shopp.js");
		
		// Load only for the product editor to keep other admin screens snappy
		if ($_GET['page'] == $this->Flow->Admin->products && isset($_GET['edit']))
			wp_enqueue_script('shopp.product.editor',"{$this->uri}/core/ui/products/editor.js");
			//wp_enqueue_script('jquery.tablednd',"{$this->uri}/core/ui/jquery/jquery.tablednd.js",array('jquery'),'');
			wp_enqueue_script('jquery-ui-sortable', '/wp-includes/js/jquery/ui.sortable.js', array('jquery-ui-core'), '1.5');
			wp_enqueue_script('swfupload');
			wp_enqueue_script('swfupload-degrade');
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
			array('all_link' => '','feed_link' => '','width' => 'fourth','height' => 'single')
		);

		wp_register_sidebar_widget('dashboard_shopp_orders', 'Shopp Orders', array(&$this->Flow,'dashboard_orders'),
			array('all_link' => 'admin.php?page='.$this->Flow->Admin->orders,'feed_link' => '','width' => 'half','height' => 'single')
		);

		wp_register_sidebar_widget('dashboard_shopp_products', 'Shopp Products', array(&$this->Flow,'dashboard_products'),
			array('all_link' => 'admin.php?page='.$this->Flow->Admin->products,'feed_link' => '','width' => 'fourth','height' => 'single')
		);

		// optional: if you want users to be able to edit the settings of your widget, you need to register a widget_control
		// wp_register_widget_control( $widget_id, $widget_control_title, $control_output_callback,
		// 	array(), // leave an empty array here: oddity in widget code
		// 	array(
		// 		'widget_id' => $widget_id, // Yes - again.  This is required: oddity in widget code
		// 		'arg'       => an arg to pass to the $control_output_callback,
		// 		'another'   => another arg to pass to the $control_output_callback,
		// 		...
		// 	)
		// );
		
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
		if ($tag) {
			add_action('wp_head', array(&$this, 'header'));
			add_action('wp_footer', array(&$this, 'footer'));
			wp_enqueue_script('jquery');
			wp_enqueue_script("shopp-thickbox","{$this->uri}/core/ui/behaviors/thickbox.js");
			wp_enqueue_script("shopp","{$this->uri}/core/ui/behaviors/shopp.js");
		}
		
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
					echo strpos($post->content,$page->content);
					if (strpos($post->post_content,$page['content']) !== false) {
						$page['id'] = $post->ID;
						$page['title'] = $post->post_title;
						$page['name'] = $post->post_name;
						$page['permalink'] = preg_replace('|https?://[^/]+/|i','',get_permalink($page['id']));
						if ($page['permalink'] == get_bloginfo('siteurl')) $page['permalink'] = "";
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
		$pages = $this->Settings->get('pages');
		if (!$pages) $pages = $this->Flow->Pages;
		$shop = $pages['catalog']['permalink'];
		$catalog = $pages['catalog']['name'];
		$checkout = $pages['checkout']['permalink'];
		
		$rules = array(
			$checkout.'?$' => 'index.php?pagename='.$checkout.'&shopp_proc=checkout',
			(empty($shop)?"$catalog/":$shop).'feed/?$' => 'index.php?shopp_lookup=newproducts-rss',
			$shop.'receipt/?$' => 'index.php?pagename='.$checkout.'&shopp_proc=receipt',
			$shop.'confirm-order/?$' => 'index.php?pagename='.$checkout.'&shopp_proc=confirm-order',
			$shop.'download/([a-z0-9]{40})/?$' => 'index.php?shopp_download=$matches[1]',
			$shop.'images/(\d+)/?.*?$' => 'index.php?shopp_image=$matches[1]'
		);

		// catalog/category/category-slug
		if (empty($shop)) {
			$rules[$catalog.'/category/([a-zA-Z0-9_\-\/]+?)/feed/?$'] = 'index.php?shopp_lookup=category-rss&shopp_category=$matches[1]';
			$rules[$catalog.'/category/([a-zA-Z0-9_\-\/]+?)/page/?([0-9]{1,})/?$'] = 'index.php?pagename='.$catalog.'&shopp_category=$matches[1]&paged=$matches[2]';
			$rules[$catalog.'/category/([a-zA-Z0-9_\-\/]+?)/?$'] = 'index.php?pagename='.$catalog.'&shopp_category=$matches[1]';
		} else {
			$rules[$shop.'category/([a-zA-Z0-9_\-\/]+?)/feed/?$'] = 'index.php?shopp_lookup=category-rss&shopp_category=$matches[1]';
			$rules[$shop.'category/([a-zA-Z0-9_\-\/]+?)/page/?([0-9]{1,})/?$'] = 'index.php?pagename='.$shop.'&shopp_category=$matches[1]&paged=$matches[2]';
			$rules[$shop.'category/([a-zA-Z0-9_\-\/]+?)/?$'] = 'index.php?pagename='.$shop.'&shopp_category=$matches[1]';
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
		if (isset($_GET['manage'])) $this->Flow->order_manager();
		else $this->Flow->orders_list();
	}

	/**
	 * products()
	 * Handles product administration screens */
	function products () {
		if (isset($_GET['edit'])) $this->Flow->product_editor();
		elseif (isset($_GET['categories'])) $this->Flow->categories_list();
		elseif (isset($_GET['category'])) $this->Flow->category_editor();
		else $this->Flow->products_list();
	}

	/**
	 * promotions()
	 * Handles product administration screens */
	function promotions () {
		if (isset($_GET['promotion'])) $this->Flow->promotion_editor();
		else $this->Flow->promotions_list();
	}

	/**
	 * settings()
	 * Handles settings administration screens */
	function settings () {

		switch($_GET['edit']) {
			case "catalog": 		$this->Flow->settings_catalog(); break;
			case "cart": 			$this->Flow->settings_cart(); break;
			case "checkout": 		$this->Flow->settings_checkout(); break;
			case "payments": 		$this->Flow->settings_payments(); break;
			case "shipping": 		$this->Flow->settings_shipping(); break;
			case "taxes": 			$this->Flow->settings_taxes(); break;
			case "presentation":	$this->Flow->settings_presentation(); break;
			case "update":			$this->Flow->settings_update(); break;
			case "ftp":				$this->Flow->settings_ftp(); break;
			default: 				$this->Flow->settings_general();
		}
		
	}

	/**
	 * titles ()
	 * Changes the Shopp catalog page titles to include the product
	 * name and category (when available) */
	function titles ($title) {
		if (isset($this->Product)) $title = $this->Product->name;
		if (isset($this->Category)) $title .= " &mdash; ".$this->Category->name;
		
		return $title;
	}

	function feeds () {
		if (SHOPP_PERMALINKS) {
			$pages = $this->Settings->get('pages');
			$shoppage = $this->link('catalog');
			if ($shoppage == get_bloginfo('siteurl')."/")
				$shoppage .= $pages['catalog']['name'];
		} else $shoppage = get_bloginfo('siteurl');

		if (empty($this->Category)):?>
	<link rel='alternate' type="application/rss+xml" title="<?php bloginfo('name'); ?> New Products RSS Feed" href="<?php echo $shoppage.((SHOPP_PERMALINKS)?'/feed/':'?shopp_lookup=newproducts-rss'); ?>" />
	<?php
			else:?>
	<link rel='alternate' type="application/rss+xml" title="<?php bloginfo('name'); ?> <?php echo $this->Category->name; ?> RSS Feed" href="<?php echo $shoppage.((SHOPP_PERMALINKS)?'/category/'.$this->Category->uri.'/feed/':'?shopp_category='.$this->Category->id.'&shopp_lookup=category-rss'); ?>" />
	<?php
		endif;
	}

	/**
	 * header()
	 * Adds stylesheets necessary for Shopp public shopping pages */
	function header () {
		if (SHOPP_PERMALINKS) {
			$pages = $this->Settings->get('pages');
			$shoppage = $this->link('catalog');
			if ($shoppage == get_bloginfo('siteurl')."/")
				$shoppage .= $pages['catalog']['name'];
		} else $shoppage = get_bloginfo('siteurl');
		
		?><link rel='stylesheet' href='<?php echo $shoppage; ?>?shopp_lookup=catalog.css' type='text/css' />
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
			$this->_debug->memory .= "Peak: ".number_format(memory_get_peak_usage()/1024, 2, '.', ',') . " KB<br />";
			$this->_debug->memory .= "End: ".number_format(memory_get_usage()/1024, 2, '.', ',') . " KB";


			echo '<script type="text/javascript">'."\n";
			echo '//<![CDATA['."\n";
			echo 'var memory_profile = "'.$this->_debug->memory.'";';
			echo 'var wpquerytotal = '.$wpdb->num_queries.';';
			echo 'var shoppquerytotal = '.count($db->queries).';';
			echo 'var shoppqueries = '.json_encode($db->queries).';';
			echo 'var shoppobjectdump = "";';
	 		echo 'shoppobjectdump = "'.addslashes(shopp_debug($this->_debug->backtrace)).'";';
			// if (isset($this->_debug->objects)) echo 'shoppobjectdump = "'.addslashes($this->_debug->objects).'";';
			echo '//]]>'."\n";
			echo '</script>'."\n";
		}

	}
		
	/**
	 * cart()
	 * Handles shopping cart requests */
	function cart () {
		if (empty($_POST['cart']) && empty($_GET['cart'])) return true;

		if ($_POST['cart'] == "ajax") $this->Flow->cart_ajax(); 
		else $this->Flow->cart_request();
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

		if (!empty($_POST['submit-login'])) {
			$this->Flow->login($_POST['email-login'],$_POST['password-login']);
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
		$Order->Billing->cardexpires = mktime(0,0,0,$_POST['billing']['cardexpires-mm'],1,($_POST['billing']['cardexpires-yy'])+2000);
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
			foreach($taxrates as $setting) {
				if ($Order->Shipping->state == $setting['zone']) {
					$this->Cart->data->Totals->taxrate = $setting['rate'];
					break;					
				}
			}

			$this->Cart->totals();

			if ($this->Cart->data->Totals->tax > 0) {
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
	
	function catalog ($wp) {
		$pages = $this->Settings->get('pages');
		
		$type = "catalog";
		if ($category = $wp->query_vars['shopp_category']) $type = "category";
		if ($productid = $wp->query_vars['shopp_pid']) $type = "product";
		if ($productname = $wp->query_vars['shopp_product']) $type = "product";
		if ($search = $wp->query_vars['s']) {
			$wp->query_vars['s'] = "";
			$wp->query_vars['pagename'] = $pages['catalog']['name'];
			$type = "category"; 
			$category = "search-results";
		}
		
		// Find product by given ID
		if (!empty($productid) && empty($this->Product->id)) {
			$this->Product = new Product($productid);
		}
		
		if (!empty($category)) {
			if (strpos($category,"/") !== false) {
				$categories = split("/",$category);
				$category = end($categories);
			}
			
			switch ($category) {
				case SearchResults::$slug: 
					$this->Category = new SearchResults(array('search'=>$search)); break;
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
			
		// Find product by category name and product name
		if (!empty($productname) && empty($this->Product->id)) {
			$this->Product = new Product($productname,"slug");
		}
		
		$this->Catalog = new Catalog($type);
		add_filter('single_post_title', array(&$this, 'titles'));
		add_action('wp_head', array(&$this, 'feeds'));

	}
	
	/**
	 * link ()
	 * Builds a full URL for a specific Shopp-related resource */
	function link ($target,$secure=false) {
		$internals = array("receipt","confirm-order");
		$pages = $this->Settings->get('pages');
		
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
	
	/**
	 * AJAX Responses */
	
	/**
	 * lookups ()
	 * Provides fast db lookups with as little overhead as possible */
	function lookups($wp) {
		// global $wp_rewrite;
		// echo "<pre>"; print_r($wp); echo "</pre>";
		// echo "<pre>"; print_r($wp_rewrite); echo "</pre>";

		// Grab query requests from permalink rewriting query vars
		$image = $wp->query_vars['shopp_image'];
		$download = $wp->query_vars['shopp_download'];
		$lookup = $wp->query_vars['shopp_lookup'];
		
		// Special handler to ensure thickbox will load db images
		if (empty($image)) {
			$requests = split("/",trim($_SERVER['REQUEST_URI'],'/'));
			if ($requests[0] == "shopp_image") $image = $requests[1];
		}
		
		// Admin Lookups
		if ($_GET['page'] == "shopp/lookup") $image = $_GET['id'];
		
		if (!empty($image)) $lookup = "image";
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
			case "spectemplate":
				$db = DB::get();
				$table = DatabaseObject::tablename(Category::$table);			
				$result = $db->query("SELECT specs FROM $table WHERE id='{$_GET['cat']}'");
				echo json_encode(unserialize($result->specs));
				exit();
				break;
			case "image":
				if (empty($image)) break;
				$Asset = new Asset($image);					
				header ("Content-type: ".$Asset->properties['mimetype']); 
				header ("Content-length: ".strlen($Asset->data)); 
				header ("Content-Disposition: inline; filename='".$Asset->name."'"); 
				header ("Content-Description: Delivered by WordPress/Shopp ".SHOPP_VERSION);
				echo $Asset->data;
				exit();
				break;
			case "catalog.css":
				$stylesheet = $this->Flow->catalog_css();
				header ("Content-length: ".strlen($stylesheet)); 
				header ("Content-type: text/css"); 
				header ("Content-Disposition: inline; filename='catalog.css'"); 
				header ("Content-Description: Delivered by WordPress/Shopp ".SHOPP_VERSION);
				echo $stylesheet;
				exit();
				break;
			case "newproducts-rss":
				$NewProducts = new NewProducts();
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
				require_once("core/model/Purchased.php");
				$Purchased = new Purchased($download,"dkey");
				$Asset = new Asset($Purchased->download);
				
				$forbidden = false;
				// Download limit checking
				if (!($Purchased->downloads < $this->Settings->get('download_limit') &&  // Has download credits available
						$Purchased->created < mktime()+$this->Settings->get('download_timelimit') ))
							$forbidden = true;
			
				if ($this->Settings->get('download_restriction') == "ip") {
					$Purchase = new Purchase($Purchased->purchase);
					if ($Purchase->ip != $_SERVER['REMOTE_ADDR']) $forbidden = true;
				}
				
				if ($forbidden) {
					header("Status: 403 Forbidden");
					header("Location: ".$this->link(''));
					exit();
				}
				
				header ("Content-type: ".$Asset->properties['mimetype']); 
				header ("Content-length: ".strlen($Asset->data)); 
				header ("Content-Disposition: inline; filename=".$Asset->name); 
				header ("Content-Description: Delivered by WordPress/Shopp ".SHOPP_VERSION);
				echo $Asset->data;
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
				if (!current_user_can('manage_options')) exit();
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
					$paths = array_push($this->Catalog->categories[$i]->slug,$paths);
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
				
			// Upload an image in the product editor
			case "wp_ajax_shopp_add_image":
				if (!current_user_can('manage_options')) exit();
				$this->Flow->product_images();
				exit();
				break;
				
			// Upload a product download file in the product editor
			case "wp_ajax_shopp_add_download":
				if (!current_user_can('manage_options')) exit();
				// TODO: Error handling
				// TODO: Security - anti-virus scan?
		
				// Save the uploaded file
				$File = new Asset();
				$File->parent = 0;
				$File->name = $_FILES['Filedata']['name'];
				$File->context = "price";
				$File->datatype = "download";
				$File->size = filesize($_FILES['Filedata']['tmp_name']);
				$File->properties = array("mimetype" => file_mimetype($_FILES['Filedata']['tmp_name']));
				$File->data = addslashes(file_get_contents($_FILES['Filedata']['tmp_name']));
				$File->save();
				unset($File->data); // Remove file contents from memory
		
				echo json_encode(array("id"=>$File->id,"name"=>$File->name,"type"=>$File->properties['mimetype'],"size"=>$File->size));
				exit();
				break;
				
			// Perform a version check for any updates
			case "wp_ajax_shopp_version_check":
				if (!current_user_can('manage_options')) exit();
				
				$request = array(
					"ShoppServerRequest" => "version-check",
					"v" => SHOPP_VERSION					
				);
				echo $this->Flow->callhome($request);
				exit();

			// Perform an update process
			case "wp_ajax_shopp_update":
				if (!current_user_can('manage_options')) exit();
				echo $this->Flow->update();
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
		case "shipping": $result = $Shopp->Cart->shippingtag($property,$options); break;
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