<?php
/*
Plugin Name: Shopp
Version: 1.0
Description: Bolt-on ecommerce solution for WordPress
Plugin URI: http://shopplugin.net
Author: Ingenesis Limited
Author URI: http://ingenesis.net
*/

define("SHOPP_VERSION","1.0");
define("SHOPP_GATEWAY_USERAGENT","WordPress Shopp Plugin/".SHOPP_VERSION);
define("SHOPP_DEBUG",true);

require("core/functions.php");
require("core/DB.php");
require("core/Flow.php");

require("core/model/Settings.php");
require("core/model/Cart.php");
require("core/model/ShipCalcs.php");

$Shopp =& new Shopp();

class Shopp {
	var $Cart;
	var $Flow;
	var $Settings;
	var $ShipCalcs;
	var $Product;
	var $Category;
	var $Catalog;
	
	function Shopp () {
		$this->path = dirname(__FILE__);
		$this->file = basename(__FILE__);
		$this->directory = basename($this->path);
		$this->uri = get_bloginfo('wpurl')."/wp-content/plugins/".$this->directory;
		$this->wpadminurl = get_bloginfo('wpurl')."/wp-admin/admin.php";		
		
		$this->Settings = new Settings();
		$this->Flow = new Flow($this);
				
		// register_deactivation_hook(__FILE__, array(&$this, 'deactivate'));
		register_activation_hook("shopp/Shopp.php", array(&$this, 'install'));

		// Initialize defaults if they have not been entered
		if (!$this->Settings->get('shopp_setup')) {
			$this->Flow->setup();
		}
		
		if (!SHOPP_LOOKUP) {
			$this->Cart = new Cart();
			session_start();

			$this->ShipCalcs = new ShipCalcs($this->Settings,$this->path);
			setlocale(LC_MONETARY, 'en_US'); // Move to settings manager ??		
		}

		add_action('init', array(&$this, 'ajax'));
		add_action('parse_query', array(&$this, 'lookups') );
		add_action('parse_query', array(&$this, 'shortcodes'));
		add_action('parse_query', array(&$this, 'cart'));
		add_action('parse_query', array(&$this, 'checkout'));

		add_action('wp_ajax_shopp_add_category', array(&$this, 'ajax') );
		add_action('wp_ajax_shopp_add_image', array(&$this, 'ajax') );
		add_action('wp_ajax_shopp_add_download', array(&$this, 'ajax') );

		add_action('admin_menu', array(&$this, 'lookups'));
		add_action('admin_menu', array(&$this, 'add_menus'));
		add_action('wp_head', array(&$this, 'page_headers'));
		add_action('wp_footer', array(&$this, 'footer'));
		add_action('admin_footer', array(&$this, 'footer'));
		add_action('save_post', array(&$this, 'page_updates'),10,2);

		add_action('widgets_init', array(&$this->Flow, 'init_cart_widget'));

		update_option('rewrite_rules', '');
		add_filter('generate_rewrite_rules',array(&$this,'rewrites'));
		add_filter('query_vars', array(&$this,'queryvars'));

		wp_enqueue_script('jquery');
		wp_enqueue_script('shopp',"{$this->uri}/core/ui/shopp.js");

	
	}
	
	function install () {

		if ($this->Settings->unavailable) 
			include("core/install.php");
				
		// If the plugin has been previously setup
		// dump the datatype model cache so it can be rebuilt
		// Useful when table schemas change so we can
		// force the in memory data model to get rebuilt
		if ($this->Settings->get('shopp_setup'))
			$this->Settings->save('data_model','');
		
	}
	
	function deactivate() {
		return true;
	}
	
	function add_menus () {
		$main = add_menu_page('Shop', 'Shop', 8, $this->Flow->Admin->default, array(&$this,'orders'));
		$orders = add_submenu_page($this->Flow->Admin->default,'Orders', 'Orders', 8, $this->Flow->Admin->orders, array(&$this,'orders'));
		$products = add_submenu_page($this->Flow->Admin->default,'Products', 'Products', 8, $this->Flow->Admin->products, array(&$this,'products'));
		$settings = add_submenu_page($this->Flow->Admin->default,'Settings', 'Settings', 8, $this->Flow->Admin->settings, array(&$this,'settings'));
		add_action("admin_print_scripts-$main", array(&$this, 'admin_header'));
		add_action("admin_print_scripts-$orders", array(&$this, 'admin_header'));
		add_action("admin_print_scripts-$products", array(&$this, 'admin_header'));
		add_action("admin_print_scripts-$settings", array(&$this, 'admin_header'));
	}

	function admin_header () {
		if ($_GET['page'] == $this->Flow->Admin->products && isset($_GET['edit']))
			wp_enqueue_script('shopp.product.editor',"{$this->uri}/core/ui/products/editor.js");
			//wp_enqueue_script('jquery.tablednd',"{$this->uri}/core/ui/jquery/jquery.tablednd.js",array('jquery'),'');
			wp_enqueue_script('jquery-ui-sortable', '/wp-includes/js/jquery/ui.sortable.js', array('jquery-ui-core'), '1.5');
			wp_enqueue_script('swfupload');
			wp_enqueue_script('swfupload-degrade');
		?>
		<link rel='stylesheet' href='<?php echo $this->uri; ?>/core/ui/admin.css' type='text/css' />
		<?php
	}
	
	function page_headers () {
		?>
		<link rel='stylesheet' href='<?php echo $this->uri; ?>/core/ui/shopp.css' type='text/css' />
		<?php
	}
	
	function rewrites ($wp_rewrite) {
		$pages = $this->Settings->get('pages');
		$shop = $pages[0]['name'];
		$cart = $pages[1]['name'];
		$checkout = $pages[2]['name'];
		
		$rules = array(
			'('.$shop.'/'.$cart.')/?$' => 'index.php?pagename='. $wp_rewrite->preg_index(1),
			'('.$shop.'/'.$checkout.')/?$' => 'index.php?pagename='. $wp_rewrite->preg_index(1),
			$shop.'/receipt/?$' => 'index.php?pagename='.$shop.'/'.$checkout.'&shopp_proc=receipt',
			$shop.'/confirm-order/?$' => 'index.php?pagename='.$shop.'/'.$checkout.'&shopp_proc=confirm',
			$shop.'/images/(\d+)/?$' => 'index.php?shopp_image='.$wp_rewrite->preg_index(1),
			'('.$shop.')/(\d+(,\d+)?)/?$' => 'index.php?pagename='. $wp_rewrite->preg_index(1).'&shopp_pid='. $wp_rewrite->preg_index(2),
			'('.$shop.')/category/([a-zA-Z0-9_\-\/]+?)/?$' => 'index.php?pagename='. $wp_rewrite->preg_index(1).'&shopp_category='. $wp_rewrite->preg_index(2),
			'('.$shop.')/([a-zA-Z0-9_\-\/]+?)/([a-zA-Z0-9_\-]+?)/?$' => 'index.php?pagename='. $wp_rewrite->preg_index(1).'&shopp_category='. $wp_rewrite->preg_index(2).'&shopp_product='. $wp_rewrite->preg_index(3),			
		);

		$wp_rewrite->rules = $rules + $wp_rewrite->rules;
	}
	
	function queryvars ($vars) {
		$vars[] = 'shopp_proc';
		$vars[] = 'shopp_category';
		$vars[] = 'shopp_pid';
		$vars[] = 'shopp_product';
		$vars[] = 'shopp_file';
		$vars[] = 'shopp_lookup';
		$vars[] = 'shopp_image';

		return $vars;
	}
			
	function orders () {
		if (isset($_GET['manage'])) $this->Flow->order_manager();
		else $this->Flow->orders_list();
	}

	function products () {
		require("core/model/Product.php");
		require("core/model/Category.php");
		require("core/model/Catalog.php");
		
		if (isset($_GET['edit'])) $this->Flow->product_editor();
		elseif (isset($_GET['categories'])) $this->Flow->categories_list();
		elseif (isset($_GET['category'])) $this->Flow->category_editor();
		else $this->Flow->products_list();
	}

	function settings () {

		switch($_GET['edit']) {
			case "products": 	$this->Flow->settings_product_page(); break;
			case "catalog": 	$this->Flow->settings_catalog(); break;
			case "cart": 		$this->Flow->settings_cart(); break;
			case "checkout": 	$this->Flow->settings_checkout(); break;
			case "payments": 	$this->Flow->settings_payments(); break;
			case "shipping": 	$this->Flow->settings_shipping(); break;
			case "taxes": 		$this->Flow->settings_taxes(); break;
			default: 			$this->Flow->settings_general();
		}
		
	}
	
	function footer () {
		if (!SHOPP_DEBUG) return true;
		$db = DB::get();
		global $wpdb;
		
		echo '<script type="text/javascript">';
		echo 'var peakmem = '.memory_get_usage(true).';';
		echo 'var wpquerytotal = '.$wpdb->num_queries.';';
		echo 'var shoppquerytotal = '.count($db->queries).';';
		echo 'var shoppqueries = '.json_encode($db->queries).';';
		echo '</script>';
		
	}
	
	function page_updates ($update_id,$updates) {
		$pages = $this->Settings->get('pages');
		foreach ($pages as &$page) {
			if ($page['id'] == $update_id) {
				$page['title'] = $updates->post_title;
				$page['name'] = $updates->post_name;
				$page['permalink'] = get_permalink($update_id);
				break;
			}
		}
		$this->Settings->save('pages',$pages);
	}
		
	function cart () {
		global $Cart;
		if (empty($_POST['cart']) && empty($_GET['cart'])) return true;
		require("core/model/Product.php");

		if ($_POST['cart'] == "ajax") $this->Flow->cart_ajax(); 
		else if (!empty($_GET['cart'])) $this->Flow->cart_request();
		else $this->Flow->cart_post();
										
	}
	
	function checkout () {
		global $Cart;
		
		if (empty($_POST['checkout'])) return true;
		if ($_POST['checkout'] == "confirmed") {
			$this->order();
			return true;
		};
		if ($_POST['checkout'] != "process") return true;
		
		$_POST['billing']['cardexpires'] = sprintf("%02d%02d",$_POST['billing']['cardexpires-m'],$_POST['billing']['cardexpires-y']);
		
		$Order = new stdClass();
		
		$Order->Customer = new Customer();
		$Order->Customer->updates($_POST);
		
		$Order->Shipping = new Shipping();
		$Order->Shipping->updates($_POST['shipping']);

		$Order->Billing = new Billing();
		$Order->Billing->updates($_POST['billing']);
		
		$Cart->data->Order = $Order;
		
		// Check for taxes, or process order
		if ($this->Settings->get('taxes') == "on") {
			$taxrates = $this->Settings->get('taxrates');
			foreach($taxrates as $setting) {
				if ($Order->Shipping->state == $setting['region']) {
					$Cart->data->Totals->taxrate = $setting['rate'];
					break;					
				}
			}
			
			$Cart->totals();
			if ($Cart->data->Totals->tax > 0) {
				header("Location: ".SHOPP_CONFIRMURL);
				exit();
			} else $this->order();
		} elseif ($this->Settings->get('order_confirmation') == "always") {
			header("Location: ".SHOPP_CONFIRMURL);
			exit();
		} else $this->order();
	}
		
	function order() {
		global $Cart;
		$processor_file = $this->Settings->get('payment_gateway');

		if (!$processor_file) return true;
		if (!file_exists($processor_file)) return true;
		
		require_once("core/model/Purchase.php");
		
		// Dynamically the payment processing gateway
		$processor_data = $this->Flow->scan_gateway_meta($processor_file);
		$ProcessorClass = $processor_data->tags['class'];
		include($processor_file);
		
		$Order =& $Cart->data->Order;
		$Order->Totals =& $Cart->data->Totals;
		$Order->Items =& $Cart->contents;
		$Order->Cart =& $Cart->session;
		
		$Payment = new $ProcessorClass($Order);
		if ($Payment->process()) {
			$Order->Customer->save();
			$Order->Shipping->customer = $Order->Customer->id;
			$Order->Shipping->save();
			$Order->Billing->customer = $Order->Customer->id;
			$Order->Billing->card = substr($Order->Billing->card,-4);
			$Order->Billing->save();
			
			$Purchase = new Purchase();
			$Purchase->customer = $Order->Customer->id;
			$Purchase->billing = $Order->Billing->id;
			$Purchase->shipping = $Order->Shipping->id;
			$Purchase->copydata($Order->Customer);
			$Purchase->copydata($Order->Billing);
			$Purchase->copydata($Order->Shipping,'ship');
			$Purchase->copydata($Cart->data->Totals);
			$Purchase->freight = $Cart->data->Totals->shipping;
			$Purchase->gateway = $processor_data->name;
			$Purchase->transactionid = $Payment->transactionid();
			$Purchase->save();

			foreach($Cart->contents as $Item) {
				$Purchased = new Purchased();
				$Purchased->copydata($Item);
				$Purchased->purchase = $Purchase->id;
				$Purchased->save();
			}
			
			// Empty cart on successful order
			$Cart->unload();
			session_destroy();

			// Start new cart session
			$Cart =& new Cart();
			session_start();
			
			// Save the purchase ID for later lookup
			$Cart->data->Purchase = $Purchase->id;

			// Send the e-mail receipt
			$receipt = array();
			$receipt['from'] = $this->Settings->get('shopowner_email');
			$receipt['to'] = "\"{$Purchase->firstname} {$Purchase->lastname}\" <{$Purchase->email}>";
			$receipt['subject'] = "KMXUS.com Order Receipt";
			$receipt['receipt'] = $this->Flow->order_receipt();
			$receipt['url'] = $_SERVER['SERVER_NAME'];
			send_email("{$this->path}/vanilla/email.html",$receipt);
			
			if ($this->Settings->get('receipt_copy') == 1) {
				$receipt['to'] = $this->Settings->get('shopowner_email');
				send_email("{$this->path}/vanilla/email.html",$receipt);
			}

			header("Location: ".SHOPP_RECEIPTURL);
			exit();
		} else {
			$Cart->data->OrderError = $Payment->error();
		}
	}
	
	function shortcodes () {
		// remove_filter('the_content', 'wpautop');
		// add_filter('the_content', 'wpautop',8);
		
		add_shortcode('catalog',array(&$this->Flow,'catalog'));
		add_shortcode('cart',array(&$this->Flow,'cart_default'));
		add_shortcode('shipping-estimate',array(&$this->Flow,'shipping_estimate'));
		add_shortcode('checkout',array(&$this->Flow,'checkout'));
		add_shortcode('order-summary',array(&$this->Flow,'checkout_order_summary'));
		add_shortcode('confirmation-summary',array(&$this->Flow,'order_confirmation'));
		add_shortcode('receipt',array(&$this->Flow,'order_receipt'));
	}
	
	/**
	 * AJAX Responses
	 */
	function lookups() {
		$db =& DB::get();

		$image = get_query_var('shopp_image');
		$lookup = get_query_var('shopp_lookup');
		if (empty($lookup)) $lookup = $_GET['lookup'];
		if (!empty($image)) $lookup = "asset";
		
		switch($lookup) {
			case "zones":
				$zones = $this->Settings->get('zones');
				if (isset($_GET['country']))
					echo json_encode($zones[$_GET['country']]);
				exit();
				break;
			case "asset":
				$id = $image;
				if (empty($id))	$id = get_query_var('shopp_file');
				if (empty($id)) $id = $_GET['id'];
			
				if (!empty($id)) {
					require("core/model/Asset.php");
					$Asset = new Asset($id);					
					header ("Content-type: ".$Asset->properties['mimetype']); 
					header ("Content-length: ".strlen($Asset->data)); 
					header ("Content-Disposition: inline; filename='".$Asset->name."'"); 
					header ("Content-Description: Delivered by WordPress/Shopp");
					echo $Asset->data;
				}
				exit();
				break;
		}
	}

	function ajax() {
		
		switch($_GET['action']) {
			case "wp_ajax_shopp_add_category":
				if (!empty($_GET['name'])) {
					require("core/model/Category.php");
					$Category = new Category();
					$Category->name = $_GET['name'];
					$Category->slug = sanitize_title_with_dashes($Category->name);
					$Category->parent = $_GET['parent'];
					$Category->save();
					echo json_encode($Category);
				}
				exit();
				break;
			case "wp_ajax_shopp_add_image":
				$this->Flow->product_images();
				exit();
				break;
			case "wp_ajax_shopp_add_download":
				require("core/model/Asset.php");
		
				// TODO: Error handling
				// TODO: Security - anti-virus scan?
		
				// Save the uploaded file
				$File = new Asset();
				$File->parent = 0;
				$File->name = $_FILES['Filedata']['name'];
				$File->datatype = "download";
				$File->size = filesize($_FILES['Filedata']['tmp_name']);
				$File->properties = array("mimetype" => file_mimetype($_FILES['Filedata']['tmp_name']));
				$File->data = addslashes(file_get_contents($_FILES['Filedata']['tmp_name']));
				$File->save();
				unset($File->data); // Remove file contents from memory
		
				echo json_encode(array("id"=>$File->id,"name"=>$File->name,"type"=>$File->properties['mimetype'],"size"=>$File->size));
				exit();
				break;
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
		case "category": $result = $Shopp->Category->tag($property,$options); break;
		case "catalog": $result = $Shopp->Catalog->tag($property,$options); break;
		case "product": $result = $Shopp->Product->tag($property,$options); break;
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