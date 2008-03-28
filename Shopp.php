<?php
/*
Plugin Name: Shopp
Version: 1.0
Description: E-Commerce catalog, shopping cart & payment processing plugin
Author: Ingenesis Limited
Author URI: http://ingenesis.net
*/

require("core/functions.php");
require("core/DB.php");

require("model/Setting.php");
require("model/Cart.php");

$Shopp =& new Shopp();

class Shopp {
	
	function Shopp () {
		$this->path = dirname(__FILE__);
		
		add_action('admin_menu', array(&$this, 'add_menus'));
		
	}
	
	function add_menus () {
		add_menu_page('Shop', 'Shop', 8, __FILE__, array(&$this,'orders'));
		add_submenu_page(__FILE__,'Orders', 'Orders', 8, __FILE__, array(&$this,'orders'));
		add_submenu_page(__FILE__,'Products', 'Products', 8, 'catalog', array(&$this,'products'));
		add_submenu_page(__FILE__,'Settings', 'Settings', 8, 'settings', array(&$this,'settings'));
	}
	
	function orders () {
		include("ui/orders/orders.html");
	}

	function products () {
		global $Products;
		$db =& DB::get();
		
		$Products = $db->query("SELECT * FROM shopp_product",AS_ARRAY);
		
		include("ui/products/products.html");
	}

	function settings () {
		include("ui/settings/settings.html");
	}
	
} // end WebShop