<?php
$GLOBALS['wp_tests_options'] = array('active_plugins' => array( 'shopp/Shopp.php' ));

define('SHOPP_UNSUPPORTED',false);

require 'lib/bootstrap.php';
require ABSPATH.'wp-content/plugins/shopp/core/flow/Install.php';
require 'testcase.php';

// Setup Shopp schema
$Installation = new ShoppInstallation();
do_action('shopp_activate');

// Test data
$productdefinitions = array(
	array(
		'name' => '1930s Chrome Art Deco Decanter',
		'slug' => '1930s-decanter',
		'publish' => array('flag' => true)
	),
	array(
		'name' => 'Multi-Prism Spectroscope',
		'slug' => 'multi-prism-spectroscope',
		'publish' => array('flag' => true)
	)
);

$lastyear = new DateTime();
$lastyear = $lastyear->modify('-1 year')->format('Y-m-d H:i:s');

$nextyear = new DateTime();
$nextyear = $nextyear->modify('+1 year')->format('Y-m-d H:i:s');

$promodefinitions = array(
	array(
		'name' => '2 PC Off',
		'status' => 'enabled',
		'type' => 'Percentage Off',
		'target' => 'Cart',
		'discount' => '2.0',
		'search' => 'any',
		'starts' => $lastyear,
		'ends' => $nextyear,
		'rules' => array(
			1 => array(
				'property' => 'Promo code',
				'logic' => 'Is equal to',
				'value' => '2Percent'
			))
	),
	array(
		'name' => '3 Dollars Off',
		'status' => 'enabled',
		'type' => 'Amount Off',
		'target' => 'Cart Item',
		'discount' => '3.0',
		'search' => 'any',
		'starts' => $lastyear,
		'ends' => $nextyear,
		'rules' => array(
			'item' => array(
				'property' => 'Quantity',
                'logic' => 'Is greater than',
                'value' => 0
			),
			1 => array(
				'property' => 'Promo code',
				'logic' => 'Is equal to',
				'value' => '3DollarsOff'
			))
	)
);

foreach ($productdefinitions as $productdata) {
	shopp_add_product($productdata);
}

foreach ($promodefinitions as $promodata) {
	$Promotion = new Promotion();
	$Promotion->updates($promodata);
	$Promotion->save();
	$Promotion->id;
}