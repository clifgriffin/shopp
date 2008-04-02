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
		
		$this->Flow = new Flow($this);
		$this->Settings = new Settings();
		
		if (!$this->Settings->get('shopp_setup')) $this->Flow->setup();
				
		add_action('admin_menu', array(&$this, 'add_menus'));
		add_action('admin_head', array(&$this, 'admin_header'));
		add_action('wp_head', array(&$this, 'stylesheet'));
		add_action('init', array(&$this, 'cart'));
		add_action('init', array(&$this, 'checkout'));
		add_action('init', array(&$this, 'pagecodes'));
	}
	
	function add_menus () {
		add_menu_page('Shop', 'Shop', 8, __FILE__, array(&$this,'orders'));
		add_submenu_page(__FILE__,'Orders', 'Orders', 8, __FILE__, array(&$this,'orders'));
		add_submenu_page(__FILE__,'Products', 'Products', 8, 'products', array(&$this,'products'));
		add_submenu_page(__FILE__,'Settings', 'Settings', 8, 'settings', array(&$this,'settings'));
	}

	function admin_header () {
		?><link rel='stylesheet' href='<?php echo $this->uri; ?>ui/admin.css' type='text/css' />
		<script type='text/javascript' src='<?php echo $this->uri; ?>ui/shopp.js'></script><?php
	}
	
	function stylesheet () {
		?><link rel='stylesheet' href='<?php echo $this->uri; ?>ui/shopp.css' type='text/css' /><?php
	}
		
	function orders () {
		include("ui/orders/orders.html");
	}

	function products () {
		require("model/Product.php");
		if (isset($_GET['edit'])) $this->Flow->product_editor();
		$this->Flow->products_list();
	}

	function settings () {
		switch($_GET['edit']) {
			case "catalog":
				$this->Flow->settings_catalog();
				break;
			case "payments":
				$this->Flow->settings_payments();
				break;
			case "shipping":
				$this->Flow->settings_shipping();
				break;
			default:
				$this->Flow->settings_general();
		}
		
	}
	
	function cart () {
		if (empty($_POST['cart']) && empty($_GET['cart'])) return true;
		require("model/Product.php");
		
		if ($_POST['cart'] == "ajax") $this->Flow->cart_ajax();
		else if (!empty($_GET['cart'])) $this->Flow->cart_request();
		else $this->Flow->cart_post();	
	}
	
	function checkout () {
		global $Cart;
		$processor_file = $this->Settings->get('payment_gateway');

		if (!$processor_file) return true;
		if (!file_exists($processor_file)) return true;
		if (empty($_POST['checkout'])) return true;
		if ($_POST['checkout'] != "process") return true;
		
		$_POST['billing']['cardexpires'] = sprintf("%02d%02d",$_POST['billing']['cardexpires-m'],$_POST['billing']['cardexpires-y']);
		
		require("model/Purchase.php");
		require("model/Customer.php");
		require("model/Billing.php");
		require("model/Shipping.php");
		
		// Dynamically the payment processing gateway
		$processor_data = $this->Flow->scan_gateway_meta($processor_file);
		$ProcessorClass = $processor_data->tags['class'];
		include($processor_file);
		
		$Order = new stdClass();

		$Order->Customer = new Customer();
		$Order->Customer->updates($_POST);
		
		$Order->Shipping = new Shipping();
		$Order->Shipping->updates($_POST['shipping']);

		$Order->Billing = new Billing();
		$Order->Billing->updates($_POST['billing']);
		
		$Order->Cart =& $Cart;
		
		$Payment = new $ProcessorClass($Order);
		if ($Payment->process()) {
			$Order->Customer->save();
			$Order->Shipping->customer = $Order->Customer->id;
			$Order->Shipping->save();
			$Order->Billing->customer = $Order->Customer->id;
			$Order->Billing->save();
			
			$Purchase = new Purchase();
			$Purchase->customer = $Order->Customer->id;
			$Purchase->billing = $Order->Billing->id;
			$Purchase->shipping = $Order->Shipping->id;
			$Purchase->copydata($Order->Customer);
			$Purchase->copydata($Order->Billing);
			$Purchase->copydata($Order->Shipping,'ship');
			$Purchase->copydata($Order->Cart->data);
			$Purchase->freight = $Order->Cart->data->shipping;
			$Purchase->gateway = $processor_data->name;
			$Purchase->transactionid = $Payment->transactionid();
			$Purchase->save();

			foreach($Cart->contents as $Item) {
				$Purchased = new Purchased();
				$Purchased->copydata($Item);
				$Purchased->purchase = $Purchase->id;
				$Purchased->save();
			}
			
			$Cart->data->Purchase = $Purchase->id;

			header("Location: /shop/receipt/");
			exit();
		} else {
			$Cart->data->OrderError = $Payment->error();
		}
		
	}
	
	function pagecodes () {
		remove_filter('the_content', 'wpautop');
		add_filter('the_content', 'wpautop',8);
		add_shortcode('cart',array(&$this->Flow,'cart_default'));
		add_shortcode('checkout',array(&$this->Flow,'checkout_onestep'));
		add_shortcode('order-summary',array(&$this->Flow,'checkout_order_summary'));
		add_shortcode('receipt',array(&$this->Flow,'order_receipt'));
	}

} // end WebShop