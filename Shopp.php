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

require("model/Setting.php");
require("model/Cart.php");

$Shopp =& new Shopp();

class Shopp {
	
	function Shopp () {
		$this->path = dirname(__FILE__);
		$this->uri = get_bloginfo('wpurl')."/wp-content/plugins/shopp/";
		
		add_action('admin_menu', array(&$this, 'add_menus'));
		add_action('admin_head', array(&$this, 'admin_header'));
		add_action('wp_head', array(&$this, 'stylesheet'));
		add_action('init', array(&$this, 'uri'));
		add_action('init', array(&$this, 'cart'));
		add_action('init', array(&$this, 'checkout'));
		add_filter('the_content',array(&$this, 'pages'));
		
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
	
	function uri () {
		define("SHOPP_URI",str_replace($_SERVER['QUERY_STRING'],"",$_SERVER['REQUEST_URI']));
	}
	
	function orders () {
		include("ui/orders/orders.html");
	}

	function products () {
		include("flow/products.php");

		if (isset($_GET['edit'])) product_editor();
				
		products_list();
	}

	function settings () {
		include("ui/settings/settings.html");
	}
	
	function cart () {
		if (empty($_POST['cart']) && empty($_GET['cart'])) return true;
		include_once("flow/cart.php");
		
		if ($_POST['cart'] == "ajax") cart_ajax();
		else if (!empty($_GET['cart'])) cart_request();
		else cart_post();	
	}
	
	function checkout () {
		global $Cart;
		if (empty($_POST['checkout'])) return true;
		
		require("model/Purchase.php");
		require("model/Purchased.php");
		require("model/Customer.php");
		require("model/Billing.php");
		require("model/Shipping.php");
		include("gateways/AuthorizeNet/Authorize.net.php");
		
		$Customer = new Customer();
		$Customer->updates($_POST);
		
		$Shipping = new Shipping();
		$Shipping->updates($_POST['shipping']);

		$Billing = new Billing();
		$_POST['billing']['cardexpires'] = sprintf("%02d%02d",$_POST['billing']['cardexpires-m'],$_POST['billing']['cardexpires-y']);
		$Billing->updates($_POST['billing']);
		
		
		$Order = new stdClass();
		$Order->Customer =& $Customer;
		$Order->Shipping =& $Shipping;
		$Order->Billing =& $Billing;
		$Order->Cart =& $Cart;
		
		$Payment = new AuthorizeNet($Order);
		$Payment->process();
		
	}
	
	
	function pages ($content) {
		include_once("flow/cart.php");
		include_once("flow/checkout.php");
		
		preg_match_all("/\[(.*?)\]/",$content,$tags,PREG_SET_ORDER);
		
		foreach($tags as $tag) {
			if ($tag[1] == "cart") cart_default();
			if ($tag[1] == "checkout") one_step_checkout();
			if ($tag[1] == "order-summary") checkout_order_summary();
			
		}
		
	}

} // end WebShop