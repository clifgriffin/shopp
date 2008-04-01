<?php
/**
 * Checkout flow controller
 * Handles checkout page form rendering
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 31 March, 2008
 * @package 
 **/

function one_step_checkout () {
	global $Shopp;
	include("{$Shopp->path}/ui/checkout/checkout.html");
}

function checkout_order_summary () {
	global $Shopp,$Cart;
	include("{$Shopp->path}/ui/checkout/summary.html");
}

?>