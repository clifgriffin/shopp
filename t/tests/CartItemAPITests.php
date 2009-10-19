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
		$Shopp->Cart->clear();
		$product = new Product(55);
		// $product->load_data(array('prices'));
		// print_r($product->prices);
		// add ($quantity,&$Product,&$Price,$category,$data=array())
		$price = 108;
		$Shopp->Cart->add(1, $product, $price, false);
		//print_r($product->prices[1]);
		$this->assertTrue(shopp('cart','hasitems'));
		$this->assertEquals(count($Shopp->Cart->contents),1);
		// ob_start();
		// while(shopp('cart', 'items')) shopp('cartitem','name');
		// $output = ob_get_contents();
		// ob_end_clean();
		// $this->assertEquals("Smart & Sexy - Push-Up Underwire Bra and Thong Panty Set",$output);

	}
	
	function test_cartitem_addbyoptionid () {
		global $Shopp;
		$Shopp->Cart->clear();
		$Shopp->Cart->secure = false;
		$product = new Product(55);
		// $product->load_data(array('prices'));
		// print_r($product->prices);
		// add ($quantity,&$Product,&$Price,$category,$data=array())
		$price = array(1);
		$Shopp->Cart->add(1, $product, $price, false);
		//print_r($product->prices[1]);
		$this->assertTrue(shopp('cart','hasitems'));
		$this->assertEquals(count($Shopp->Cart->contents),1);
		// ob_start();
		// while(shopp('cart', 'items')) shopp('cartitem','name');
		// $output = ob_get_contents();
		// ob_end_clean();
		// $this->assertEquals("Smart & Sexy - Push-Up Underwire Bra and Thong Panty Set",$output);
	}

} // end CartItemAPITests class

?>