<?php
/*
Plugin Name: Shopp
Version: 1.0
Description: Bolt-on ecommerce solution for WordPress
Plugin URI: http://shopplugin.net
Author: Ingenesis Limited
Author URI: http://ingenesis.net
*/

define("SHOPP_VERSION","1.0dev116");
define("SHOPP_GATEWAY_USERAGENT","WordPress Shopp Plugin/".SHOPP_VERSION);
define("SHOPP_HOME","http://shopplugin.net/");
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

		// Keep any DB operations from occuring while in 
		// maintenance mode
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
		
		if (!SHOPP_LOOKUP) {
			$this->Cart = new Cart();
			session_start();
			
			$this->Catalog = new Catalog();

			$this->ShipCalcs = new ShipCalcs($this->Settings,$this->path);
			setlocale(LC_MONETARY, 'en_US'); // Move to settings manager ??		
		}

		add_action('init', array(&$this, 'ajax'));
		add_action('parse_request', array(&$this, 'lookups') );
		add_action('parse_request', array(&$this, 'cart'));
		add_action('parse_request', array(&$this, 'checkout'));
		add_action('wp', array(&$this, 'shortcodes'));
		add_action('wp', array(&$this, 'behaviors'));

		add_action('wp_ajax_shopp_add_category', array(&$this, 'ajax') );
		add_action('wp_ajax_shopp_add_image', array(&$this, 'ajax') );
		add_action('wp_ajax_shopp_add_download', array(&$this, 'ajax') );

		add_action('admin_menu', array(&$this, 'lookups'));
		add_action('admin_menu', array(&$this, 'add_menus'));
		add_action('admin_footer', array(&$this, 'footer'));
		add_action('save_post', array(&$this, 'page_updates'),10,2);

		add_action('widgets_init', array(&$this->Flow, 'init_cart_widget'));
		add_action('widgets_init', array(&$this->Flow, 'init_categories_widget'));

		update_option('rewrite_rules', '');
		add_filter('mod_rewrite_rules', array(&$this,'page_updates'));
		add_filter('rewrite_rules_array',array(&$this,'rewrites'));
		add_filter('query_vars', array(&$this,'queryvars'));
		// add_filter('wp_list_categories',array(&$this->Flow,'catalog_categories'));
	
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
		$main = add_menu_page('Shop', 'Shop', 8, $this->Flow->Admin->default, array(&$this,'orders'));
		$orders = add_submenu_page($this->Flow->Admin->default,'Orders', 'Orders', 8, $this->Flow->Admin->orders, array(&$this,'orders'));
		$products = add_submenu_page($this->Flow->Admin->default,'Products', 'Products', 8, $this->Flow->Admin->products, array(&$this,'products'));
		$settings = add_submenu_page($this->Flow->Admin->default,'Settings', 'Settings', 8, $this->Flow->Admin->settings, array(&$this,'settings'));
		$help = add_submenu_page($this->Flow->Admin->default,'Help', 'Help', 8, $this->Flow->Admin->help, array(&$this,'help'));
		add_action("admin_print_scripts-$main", array(&$this, 'admin_behaviors'));
		add_action("admin_print_scripts-$orders", array(&$this, 'admin_behaviors'));
		add_action("admin_print_scripts-$products", array(&$this, 'admin_behaviors'));
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
	 * behaviors()
	 * Dynamically includes necessary JavaScript and stylesheets as needed in 
	 * public shopping pages handled by Shopp */
	function behaviors () {
		global $wp_query;
		$object = $wp_query->get_queried_object();
		
		// Determine which tag is getting used in the current post/page
		$tag = false;
		$tagregexp = join( '|', array_keys($this->shortcodes) );
		foreach ($wp_query->posts as &$post) {
			if (preg_match('/\[('.$tagregexp.')\b(.*?)(?:(\/))?\](?:(.+?)\[\/\1\])?/',$post->post_content,$matches))
				$tag = $matches[1];
		}

		// Include stylesheets and javascript based on whether shopp shortcodes are used
		if ($tag) {
			add_action('wp_head', array(&$this, 'page_styles'));
			add_action('wp_footer', array(&$this, 'footer'));
			wp_enqueue_script('jquery');
			wp_enqueue_script("shopp-thickbox","{$this->uri}/core/ui/behaviors/thickbox.js");
			wp_enqueue_script("shopp","{$this->uri}/core/ui/behaviors/shopp.js");
		}
		
		if ($tag == "checkout")
			wp_enqueue_script('shopp_checkout',"{$this->uri}/core/ui/behaviors/checkout.js");		
			
	}

	/**
	 * page_styles()
	 * Adds stylesheets necessary for Shopp public shopping pages */
	function page_styles () {
		
		?><link rel='stylesheet' href='<?php echo $this->uri; ?>/core/ui/styles/shopp.css' type='text/css' />
		<link rel='stylesheet' href='<?php echo $this->uri; ?>/core/ui/styles/thickbox.css' type='text/css' />
		<?php
	}
	
	/**
	 * shortcodes()
	 * Handles shortcodes used on Shopp-installed pages and used by
	 * site owner for including categories/products in posts and pages */
	function shortcodes () {
		// Move wpautop priority to run before shortcodes are processed
		if (version_compare(get_bloginfo('version'),'2.5','==')) {
			remove_filter('the_content', 'wpautop');
			add_filter('the_content', 'wpautop',8);
		}
		
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
			else add_shortcode($name,&$callback);
	}
		
	/**
	 * rewrites()
	 * Adds Shopp-specific pretty-url rewrite rules to the WordPress rewrite rules */
	function rewrites ($wp_rewrite_rules) {
		$pages = $this->Settings->get('pages');
		$shop = $pages['catalog']['name'];
		$cart = $pages['cart']['name'];
		$checkout = $pages['checkout']['name'];
		
		$rules = array(
			$shop.'/'.$checkout.'/?$' => 'index.php?pagename='.$shop.'/'.$checkout.'&shopp_proc=checkout',
			$shop.'/receipt/?$' => 'index.php?pagename='.$shop.'/'.$checkout.'&shopp_proc=receipt',
			$shop.'/confirm-order/?$' => 'index.php?pagename='.$shop.'/'.$checkout.'&shopp_proc=confirm-order',
			$shop.'/download/([a-z0-9]{40})/?$' => 'index.php?shopp_download=$matches[1]',
			$shop.'/images/(\d+)/?.*?$' => 'index.php?shopp_image=$matches[1]',
			'('.$shop.')/(\d+(,\d+)?)/?$' => 'index.php?pagename=$matches[1]&shopp_pid=$matches[2]',
			'('.$shop.')/category/([a-zA-Z0-9_\-\/]+?)/?$' => 'index.php?pagename=$matches[1]&shopp_category=$matches[2]',
			'('.$shop.')/([a-zA-Z0-9_\-\/]+?)/([a-zA-Z0-9_\-]+?)/?$' => 'index.php?pagename=$matches[1]&shopp_category=$matches[2]&shopp_product=$matches[3]'
		);

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
	 * footer()
	 * Adds report information and custom debugging tools to the public and admin footers */
	function footer () {
		if (!SHOPP_DEBUG) return true;
		$db = DB::get();
		global $wpdb;
		
		shopp_debug($this);
		
		$this->_debug->memory .= "Peak: ".number_format(memory_get_peak_usage()/1024, 2, '.', ',') . " KB<br />";
		$this->_debug->memory .= "End: ".number_format(memory_get_usage()/1024, 2, '.', ',') . " KB";
		
		echo '<script type="text/javascript">'."\n";
		echo '//<![CDATA['."\n";
		echo 'var memory_profile = "'.$this->_debug->memory.'";';
		echo 'var wpquerytotal = '.$wpdb->num_queries.';';
		echo 'var shoppquerytotal = '.count($db->queries).';';
		echo 'var shoppqueries = '.json_encode($db->queries).';';
		echo 'var shoppobjectdump = "";';
		// if (isset($this->_debug->objects)) echo 'shoppobjectdump = "'.addslashes($this->_debug->objects).'";';
		echo '//]]>'."\n";
		echo '</script>'."\n";
		
	}
	
	/**
	 * page_updates()
	 * Handles changes to Shopp-installed pages that may affect 'pretty' urls */
	function page_updates ($update_id=false,$updates=false) {
		$pages = $this->Settings->get('pages');

		if (!$updates) {
			foreach ($pages as $key => &$page) {
				$permalink = get_permalink($page['id']);
				if ($key == "checkout") $permalink = str_replace("http://","https://",$permalink);
				if (!empty($permalink)) $page['permalink'] = $permalink;
			}
		} else {
			foreach ($pages as $key => &$page) {
				if ($page['id'] == $update_id) {
					$page['title'] = $updates->post_title;
					$page['name'] = $updates->post_name;
					$page['permalink'] = get_permalink($page['id']);
					if ($key == "checkout") $page['permalink'] =  str_replace("http://","https://",$page['permalink']);
					break;
				}
			}
		}
		$this->Settings->save('pages',$pages);
	}
		
	/**
	 * cart()
	 * Handles shopping cart requests */
	function cart () {
		if (empty($_POST['cart']) && empty($_GET['cart'])) return true;

		if ($_POST['cart'] == "ajax") $this->Flow->cart_ajax(); 
		else if (!empty($_GET['cart'])) $this->Flow->cart_request();
		else $this->Flow->cart_post();
	}
	
	/**
	 * checkout()
	 * Handles checkout process */
	function checkout ($wp) {

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
				
		$_POST['billing']['cardexpires'] = sprintf("%02d%02d",$_POST['billing']['cardexpires-m'],$_POST['billing']['cardexpires-y']);
		
		$Order = new stdClass();
		
		$Order->Customer = new Customer();
		$Order->Customer->updates($_POST);

		$Order->Shipping = new Shipping();
		if ($_POST['shipping']) $Order->Shipping->updates($_POST['shipping']);

		$Order->Billing = new Billing();
		$Order->Billing->updates($_POST['billing']);
		$Order->Billing->cardexpires = mktime(0,0,0,$_POST['billing']['cardexpires-mm'],1,($_POST['billing']['cardexpires-yy'])+2000);
		$Order->Billing->cvv = $_POST['billing']['cvv'];
		
		$this->Cart->data->Order = $Order;
		
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
	 * link ()
	 * Builds a full URL for a specific Shopp-related resource */
	function link ($target,$path="",$secure=false) {
		$pages = $this->Settings->get('pages');
		
		$uri = ($secure)?str_replace('http://','https://',get_bloginfo('wpurl')):get_bloginfo('wpurl');
		if ($path == "") $path = '/'.$pages['catalog']['name'].'/';
		
		if (array_key_exists($target,$pages)) $page = $pages[$target];
		else {
			if (SHOPP_PERMALINKS) $page['name'] = $target;
			else $page['id'] = $pages['checkout']['id']."&shopp_proc=".$target;	
		}
		if (SHOPP_PERMALINKS) return $uri.$path.$page['name'];
		else return $uri.'?page_id='.$page['id'];
	}
	
	/**
	 * help()
	 * This function provides graceful degradation when the 
	 * contextual javascript behavior isn't working, this
	 * provides the default behavior of showing a help gateway
	 * page with instructions on where to find help on Shopp. */
	function help () {
		include(SHOPP_ADMINPATH."/help/help.html");
	}
	
	/**
	 * AJAX Responses */
	
	/**
	 * lookups ()
	 * Provides fast db lookups with as little overhead as possible */
	function lookups($wp) {
		
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
		
		switch($lookup) {
			case "zones":
				$zones = $this->Settings->get('zones');
				if (isset($_GET['country']))
					echo json_encode($zones[$_GET['country']]);
				exit();
				break;
			case "image":
				if (empty($image)) break;
				$Asset = new Asset($image);					
				header ("Content-type: ".$Asset->properties['mimetype']); 
				header ("Content-length: ".strlen($Asset->data)); 
				header ("Content-Disposition: inline; filename='".$Asset->name."'"); 
				header ("Content-Description: Delivered by WordPress/Shopp");
				echo $Asset->data;
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
				header ("Content-Description: Delivered by WordPress/Shopp");
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
				if (!empty($_GET['name'])) {
					$Catalog = new Catalog();
					$Catalog->load_categories();
					
					$Category = new Category();
					$Category->name = $_GET['name'];
					$Category->slug = sanitize_title_with_dashes($Category->name);
					$Category->parent = $_GET['parent'];

					// Work out pathing
					$Category->uri = $Category->slug;
					
					for ($i = count($Shopp->Catalog->categories); $i > 0; $i--)
						if ($Category->parent == $Shopp->Catalog->categories[$i]->id) break;
					$Category->uri = $Shopp->Catalog->categories[$i]->slug."/".$Category->uri;

					$parentkey = $Shopp->Catalog->categories[$i]->parentkey;
					while ($parentkey > -1) {
						$tree_category = $Shopp->Catalog->categories[$parentkey];
						$uri = $tree_category->slug."/".$uri;
						$parentkey = $tree_category->parentkey;
					}

					$_POST['uri'] = $uri;
										
					$Category->save();
					echo json_encode($Category);
				}
				exit();
				break;
				
			// Upload an image in the product editor
			case "wp_ajax_shopp_add_image":
				$this->Flow->product_images();
				exit();
				break;
				
			// Upload a product download file in the product editor
			case "wp_ajax_shopp_add_download":
		
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
				$request = array(
					"ShoppServerRequest" => "version-check",
					"v" => SHOPP_VERSION					
				);
				echo $this->Flow->callhome($request);
				exit();

			// Perform an update process
			case "wp_ajax_shopp_update":
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
		case "shipestimate": $result = $Shopp->Cart->shipestimatetag($property,$options); break;
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