<?php
/**
 * ProductAPITests
 * 
 *
 * @author John Dillick
 * @version 1.0
 * @copyright Ingenesis Limited, 14 October, 2009
 * @package 
 **/

/**
 * Initialize
 **/
require_once 'PHPUnit/Framework.php';

class CartItemAPITests extends ShoppTestCase {	
	function CartItemAPITests () {
	}
	
	function test_cartitem_addbypriceid () {
		global $Shopp;
		echo "Session ID: " . session_id()."\n"; 
		$Shopp->Cart->clear();
		// print_r($Shopp->Cart->data);
		$product = new Product(55);

		$price = 108;
		$Shopp->Cart->add(1, $product, $price, false);
		$this->assertTrue(shopp('cart','hasitems'));
		$this->assertEquals(count($Shopp->Cart->contents),1);

	}
	
	function test_cartitem_addbyoptionid () {
		global $Shopp;
		$Shopp->Cart->clear();
		// print_r($Shopp->Cart->data);
		
		$product = new Product(55);
		
		$price = array(1);
		$Shopp->Cart->add(1, $product, $price, false);
		$this->assertTrue(shopp('cart','hasitems'));
		$this->assertEquals(count($Shopp->Cart->contents),1);
	}

	function test_cartitem_name () {
		global $Shopp;
		$Shopp->Cart->clear();
		// print_r($Shopp->Cart->data);
		
		$product = new Product(55);

		$price = array(1);
		$Shopp->Cart->add(1, $product, $price, false);
		$this->assertTrue(shopp('cart','hasitems'));
		ob_start();
		while(shopp('cart', 'items')) shopp('cartitem','name');
		$output = ob_get_contents();
		ob_end_clean();
		
		$this->assertEquals("Smart & Sexy - Push-Up Underwire Bra and Thong Panty Set",$output);
	}
	
	function test_cartitem_url () {
		global $Shopp;
		$Shopp->Cart->clear();
		// print_r($Shopp->Cart->data);
		
		$product = new Product(55);

		$price = array(1);
		$Shopp->Cart->add(1, $product, $price, false);
		$this->assertTrue(shopp('cart','hasitems'));
		ob_start();
		while(shopp('cart', 'items')) shopp('cartitem','url');
		$output = ob_get_contents();
		ob_end_clean();
		// echo $output."\n";
		$this->assertEquals("http://shopptest/store/smart-sexy-push-up-underwire-bra-and-thong-panty-set",$output);
	}

	function test_cartitem_sku () {
		global $Shopp;
		$Shopp->Cart->clear();
		// print_r($Shopp->Cart->data);
		
		$product = new Product(81);

		$price = array(11);
		$Shopp->Cart->add(1, $product, $price, false);
		$this->assertTrue(shopp('cart','hasitems'));
		ob_start();
		while(shopp('cart', 'items')) shopp('cartitem','sku');
		$output = ob_get_contents();
		ob_end_clean();
		// echo $output."\n";
		// $this->assertEquals("BR-81",$output);
	}

	function test_cartitem_unitprice () {
		global $Shopp;
		$Shopp->Cart->clear();
		// print_r($Shopp->Cart->data);
		
		$product = new Product(55);

		$price = array(1);
		$Shopp->Cart->add(1, $product, $price, false);
		$this->assertTrue(shopp('cart','hasitems'));

		while(shopp('cart', 'items')){ 
			ob_start();
			shopp('cartitem','unitprice');
			$output = ob_get_contents();
			ob_end_clean();
			
			// echo $output."\n";
			$this->assertEquals("$10.00",$output);
			
			ob_start();
			shopp('cartitem', 'unitprice', 'currency=off&taxes=true');
			$output = ob_get_contents();
			ob_end_clean();
			$this->assertEquals("11.50",$output);
		}		
	}

} // end CartItemAPITests class

?>