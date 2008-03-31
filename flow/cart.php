<?php
/**
 * Cart flow controller
 * Handles get/post/ajax requests for the shopping cart
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 28 March, 2008
 * @package shopp
 **/

require("{$this->path}/model/Product.php");

function cart_post () {
	global $Cart;
	
	if (isset($_POST['product']) && isset($_POST['price'])) {
		$Product = new Product($_POST['product']);
		$Price = new Price($_POST['price']);
		$quantity = (!empty($_POST['quantity']))?$_POST['quantity']:1;
		
		if (isset($_POST['item'])) $Cart->change($_POST['item'],$Product,$Price);
		$Cart->add($quantity,$Product,$Price);
	}
	
	if (!empty($_POST['item']) && isset($_POST['quantity']))
		$Cart->update($_POST['item'],$_POST['quantity']);
		
	if (!empty($_POST['items'])) {
		foreach ($_POST['items'] as $id => $item) {
			if (isset($item['quantity'])) $Cart->update($id,$item['quantity']);
			if (isset($item['product']) && isset($item['price'])) {
				$Product = new Product($item['product']);
				$Price = new Price($item['price']);
				$Cart->change($id,$Product,$Price);
			}
		}
		
	}

}

function cart_request () {
	global $Cart;
	
	switch ($_GET['cart']) {
		case "add":		// Received an add product request, add a new item the cart
			if (!empty($_GET['product']) && strpos($_GET['product'],",") !== false) {
				list($product_id,$price_id) = split(",",$_GET['product']);
				$Product = new Product($product_id);
				$Price = new Price($price_id);
				$quantity = (!empty($_GET['quantity']))?$_GET['quantity']:1;
				$Cart->add($quantity,$Product,$Price);				
			}
			break;
		case "update":  // Received an update request
		
			// Update quantity
			if (isset($_GET['item']) && isset($_GET['quantity'])) 
				$Cart->update($_GET['item'],$_GET['quantity']);
			
			// Update product/pricing
			if (isset($_GET['item']) && 
					!empty($_GET['product']) && 
					strpos($_GET['product'],",") !== false) {

				list($product_id,$price_id) = split(",",$_GET['product']);
				$Product = new Product($product_id);
				$Price = new Price($price_id);
				$Cart->change($_GET['item'],$Product,$Price);
			}
			
			break;
	}

}

function cart_ajax () {
	// Not implemented
}

function cart_default () {
	global $Shopp,$Cart;
	include("{$Shopp->path}/ui/cart/cart.html");
}

?>