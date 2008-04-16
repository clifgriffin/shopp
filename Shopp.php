<?php
/*
Plugin Name: Shopp
Version: 1.0
Description: E-Commerce catalog, shopping cart & payment processing plugin
Author: Ingenesis Limited
Author URI: http://ingenesis.net
*/

setlocale(LC_MONETARY, 'en_US'); // Move to settings manager

define("SHOPP_VERSION","1.0");
define("SHOPP_GATEWAY_USERAGENT","WordPress Shopp Plugin/".SHOPP_VERSION);

require("core/functions.php");
require("core/DB.php");
require("core/Flow.php");

require("model/Settings.php");
require("model/Cart.php");

$Shopp =& new Shopp();

class Shopp {
	var $Flow;
	var $Settings;
	
	function Shopp () {
		$this->path = dirname(__FILE__);
		$this->uri = get_bloginfo('wpurl')."/wp-content/plugins/shopp/";
		
		$this->Settings = new Settings();
		$this->Flow = new Flow($this);
		
		// Move this to install()
		if (!$this->Settings->get('shopp_setup')) $this->Flow->development_setup();
				
		add_action('admin_menu', array(&$this, 'add_menus'));
		add_action('wp_head', array(&$this, 'page_headers'));
		add_action('init', array(&$this, 'lookups'));
		add_action('init', array(&$this, 'ajax'));
		add_action('init', array(&$this, 'cart'));
		add_action('init', array(&$this, 'checkout'));
		add_action('init', array(&$this, 'shortcodes'));
		register_activation_hook(__FILE__, array(&$this,'activate'));

	 
	}
	
	function add_menus () {
		$main = add_menu_page('Shop', 'Shop', 8, __FILE__, array(&$this,'orders'));
		$orders = add_submenu_page(__FILE__,'Orders', 'Orders', 8, __FILE__, array(&$this,'orders'));
		$products = add_submenu_page(__FILE__,'Products', 'Products', 8, 'products', array(&$this,'products'));
		$settings = add_submenu_page(__FILE__,'Settings', 'Settings', 8, 'settings', array(&$this,'settings'));
		add_action("admin_print_scripts-$main", array(&$this, 'admin_header'));
		add_action("admin_print_scripts-$orders", array(&$this, 'admin_header'));
		add_action("admin_print_scripts-$products", array(&$this, 'admin_header'));
		add_action("admin_print_scripts-$settings", array(&$this, 'admin_header'));
		
	}

	function admin_header () {
		wp_enqueue_script('shopp',"{$this->uri}ui/shopp.js");
		if ($_GET['page'] == "products" && isset($_GET['edit']))
			wp_enqueue_script('jquery.tablednd',"{$this->uri}ui/jquery/jquery.tablednd.js",array('jquery'),'');
			
		?>
		<link rel='stylesheet' href='<?php echo $this->uri; ?>ui/admin.css' type='text/css' />
		<?php
	}
	
	function page_headers () {
		wp_enqueue_script('jquery');
		?>
		<link rel='stylesheet' href='<?php echo $this->uri; ?>ui/shopp.css' type='text/css' />
		<?php
	}
		
	function orders () {
		if (isset($_GET['manage'])) $this->Flow->order_manager();
		else $this->Flow->orders_list();
	}

	function products () {
		require("model/Product.php");
		require("model/Category.php");
		require("model/Catalog.php");
		
		if (isset($_GET['edit'])) $this->Flow->product_editor();
		elseif (isset($_GET['categories'])) $this->Flow->categories_list();
		elseif (isset($_GET['category'])) $this->Flow->category_editor();
		else $this->Flow->products_list();
	}

	function settings () {
		
		switch($_GET['edit']) {
			case "products":
				$this->Flow->settings_product_page();
				break;
			case "catalog":
				$this->Flow->settings_catalog();
				break;
			case "cart":
				$this->Flow->settings_cart();
				break;
			case "checkout":
				$this->Flow->settings_checkout();
				break;
			case "payments":
				$this->Flow->settings_payments();
				break;
			case "shipping":
				$this->Flow->settings_shipping();
				break;
			case "taxes":
				$this->Flow->settings_taxes();
				break;
			default:
				$this->Flow->settings_general();
		}
		
	}
	
	function cart () {
		global $Cart;
		if (empty($_POST['cart']) && empty($_GET['cart'])) return true;
		require("model/Product.php");

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
		
		require_once("model/Purchase.php");
		
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

			$Cart =& new Cart();
			session_start();
			$Cart->data->Purchase = $Purchase->id;

			// Send the e-mail receipt
			$receipt = array();
			$receipt['from'] = $this->Settings->get('shopowner_email');
			$receipt['to'] = "\"{$Purchase->firstname} {$Purchase->lastname}\" <{$Purchase->email}>";
			$receipt['subject'] = "KMXUS.com Order Receipt";
			$receipt['receipt'] = $this->Flow->order_receipt();
			$receipt['url'] = $_SERVER['SERVER_NAME'];
			send_email("{$this->path}/ui/checkout/email.html",$receipt);
			
			if ($this->Settings->get('receipt_copy') == 1) {
				$receipt['to'] = $this->Settings->get('shopowner_email');
				send_email("{$this->path}/ui/checkout/email.html",$receipt);
			}

			header("Location: ".SHOPP_RECEIPTURL);
			exit();
		} else {
			$Cart->data->OrderError = $Payment->error();
		}
	}
	
	function shortcodes () {
		remove_filter('the_content', 'wpautop');
		add_filter('the_content', 'wpautop',8);
		
		add_shortcode('cart',array(&$this->Flow,'cart_default'));
		add_shortcode('checkout',array(&$this->Flow,'checkout_onestep'));
		add_shortcode('order-summary',array(&$this->Flow,'checkout_order_summary'));
		add_shortcode('confirmation-summary',array(&$this->Flow,'order_confirmation'));
		add_shortcode('receipt',array(&$this->Flow,'order_receipt'));
	}
	
	function activate () {
		$db =& DB::get();
		
		// If the plugin has been previously setup
		// dump the datatype model cache so it can be rebuilt
		// Useful when table schemas change so we can
		// force them to get rebuilt
		if ($this->Settings->get('shopp_setup'))
			$this->Settings->save('datatype_model','');

		if ($this->Settings->unavailable) {
			include("core/install.php");
		}
		
	}
	
	/**
	 * AJAX Responses
	 */
	function lookups() {
		$db =& DB::get();
		
		switch($_GET['lookup']) {
			case "regions":
				$regions = $this->Settings->get('regions');
				if (isset($_GET['country']))
					echo json_encode($regions[$_GET['country']]);
				exit();
				break;
		}
	}

	function ajax() {
		$db =& DB::get();
		
		switch($_GET['add']) {
			case "category":
				if (!empty($_GET['name'])) {
					require("model/Category.php");
					$Category = new Category();
					$Category->name = $_GET['name'];
					$Category->parent = $_GET['parent'];
					$Category->save();
					echo json_encode($Category);
				}
				exit();
				break;
		}
	}

} // end Shopp

/**
 * shopp()
 * Provides for Shopp 'tag' support to allow for complete 
 * customization of all customer interfaces
 *
 * @param $object - The object to get the tag property from
 * @param $property - The property of the object to get/output
 * @param $options - Custom options for the property result in query form (option1=value&option2=value&...)
 */
function shopp () {
	global $Cart,$Shopp;
	$args = func_get_args();
	
	$object = strtolower($args[0]);
	$property = strtolower($args[1]);
	$paramsets = strtolower($args[2]);
	$paramsets = split("&",$paramsets);

	$options = array();
	foreach ($paramsets as $paramset) {
		list($key,$value) = split("=",$paramset);
		$options[$key] = $value;
	}
	
	$result = "";
	switch (strtolower($object)) {
		case "cart": $result = $Cart->tag($property,$options); break;
		case "cartitem": $result = $Cart->itemtag($property,$options); break;
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