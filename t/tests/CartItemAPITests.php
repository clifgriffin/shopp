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
	
	function test_cartitem_quantity () {
		global $Shopp;
		$Shopp->Cart->clear();

		$product = new Product(55);

		$price = 108;
		$Shopp->Cart->add(1, $product, $price, false);
		
		$product = new Product(81);

		$price = array(11);
		$Shopp->Cart->add(6, $product, $price, false);

		$this->assertTrue(shopp('cart','hasitems'));
		$this->assertEquals(count($Shopp->Cart->contents),2);
		while(shopp('cart', 'items')){
			$item = current($Shopp->Cart->contents);
			ob_start();			 
			shopp('cartitem','quantity');
			$output = ob_get_contents();
			ob_end_clean();
			
			if($item->product == 55) $this->assertEquals("1", $output);
			else $this->assertEquals("6", $output);
		}
		
	}
	
	function test_cartitem_quantityinput () {
		global $Shopp;
		$Shopp->Cart->clear();

		$product = new Product(55);

		$price = 108;
		$Shopp->Cart->add(1, $product, $price, false);
		$this->assertTrue(shopp('cart','hasitems'));
		$this->assertEquals(count($Shopp->Cart->contents),1);
		
		while(shopp('cart', 'items')){
			ob_start();			 
			shopp('cartitem','quantity', 'input=menu');
			$output = ob_get_contents();
			ob_end_clean();

			ob_start();
?><select name="items[0][quantity]"><option selected="selected" value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option><option value="6">6</option><option value="7">7</option><option value="8">8</option><option value="9">9</option><option value="10">10</option><option value="11">11</option><option value="12">12</option><option value="13">13</option><option value="14">14</option><option value="15">15</option><option value="20">20</option><option value="25">25</option><option value="30">30</option><option value="35">35</option><option value="40">40</option><option value="45">45</option><option value="50">50</option><option value="60">60</option><option value="70">70</option><option value="80">80</option><option value="90">90</option><option value="100">100</option></select><?php
			$testValue = ob_get_contents();
			ob_end_clean();
			
			$this->assertEquals($testValue,$output);	
			$this->assertValidMarkup($output);
			
			ob_start();			 
			shopp('cartitem','quantity', 'input=text&class=myClass');
			$output = ob_get_contents();
			ob_end_clean();

			ob_start();
			?><input type="text" name="items[0][quantity]" id="items-0-quantity"  size="5" value="1" class=" myClass"/><?php
			$testValue = ob_get_contents();
			ob_end_clean();			
			$this->assertEquals($testValue,$output);	
			$this->assertValidMarkup($output);
		}
	}

		function test_cartitem_remove () {
			global $Shopp;
			$Shopp->Cart->clear();

			$product = new Product(55);

			$price = 108;
			$Shopp->Cart->add(1, $product, $price, false);
			$this->assertTrue(shopp('cart','hasitems'));
			$this->assertEquals(count($Shopp->Cart->contents),1);

			while(shopp('cart', 'items')){
				ob_start();			 
				shopp('cartitem','remove', 'input=button&label=My Label&class=myclass');
				$output = ob_get_contents();
				ob_end_clean();
				
				ob_start();
				?><button type="submit" name="remove[0]" value="0" class="myclass" tabindex="">My Label</button><?php
				$testValue = ob_get_contents();
				ob_end_clean();

				$this->assertEquals($testValue,$output);	
				$this->assertValidMarkup($output);

				ob_start();			 
				shopp('cartitem','remove', 'label=My Label 2&class=myclass2');
				$output = ob_get_contents();
				ob_end_clean();
				
				ob_start();
				?><a href="http://shopptest/store/cart/?cart=update&amp;item=0&amp;quantity=0" class="myclass2">My Label 2</a><?php
				$testValue = ob_get_contents();
				ob_end_clean();			
				$this->assertEquals($testValue,$output);
				$this->assertValidMarkup($output);
			}
		}
		
		function test_cartitem_thumbnail (){
			global $Shopp;
			$Shopp->Cart->clear();

			$product = new Product(55);

			$price = 108;
			$Shopp->Cart->add(1, $product, $price, false);
			$this->assertTrue(shopp('cart','hasitems'));
			while(shopp('cart', 'items')){
				ob_start();			 
				shopp('cartitem','thumbnail', 'class=cart-thumb&width=200&height=220');
				$output = ob_get_contents();
				ob_end_clean();
				
				ob_start();
				?><img src="http://shopptest/store/images/276" alt="Smart &amp; Sexy - Push-Up Underwire Bra and Thong Panty Set thumbnail" width="200" height="220"  class="cart-thumb" /><?php
				$testValue = ob_get_contents();
				ob_end_clean();
				
				$this->assertEquals($testValue, $output);
				$this->assertValidMarkup($output);
			}
			$Shopp->Cart->clear();
			$product = new Product(9);

			$price = array(1,8);
			$Shopp->Cart->add(1, $product, $price, false);
			$this->assertTrue(shopp('cart','hasitems'));
			while(shopp('cart', 'items')){
				ob_start();			 
				shopp('cartitem','thumbnail');
				$output = ob_get_contents();
				ob_end_clean();
				
				ob_start();
				?><img src="http://shopptest/store/images/72" alt="Faded Glory - Men's Original Fit Jeans thumbnail" width="96" height="96"  /><?php
				$testValue = ob_get_contents();
				ob_end_clean();
				
				$this->assertEquals($testValue, $output);
				$this->assertValidMarkup($output);
			}			
				
		}
		
		function test_cartitem_options (){
			global $Shopp;
			$Shopp->Cart->clear();

			$product = new Product(55);

			$price = 108;
			$Shopp->Cart->add(1, $product, $price, false);
			$this->assertTrue(shopp('cart','hasitems'));
			while(shopp('cart', 'items')){
				ob_start();			 
				shopp('cartitem','options','before=<div>&after=</div>');
				$output = ob_get_contents();
				ob_end_clean();
				
				ob_start();
				?><div><input type="hidden" name="items[0][product]" value="55"/> <select name="items[0][price]" id="items-0-price"><option value="108" selected="selected">34A</option><option value="109">34B</option><option value="110">36B</option><option value="111">36C</option><option value="112">38C</option></select></div><?php
				$testValue = ob_get_contents();
				ob_end_clean();
				
				$this->assertEquals($testValue, $output);
				$this->assertValidMarkup($output);
			}			
		}

} // end CartItemAPITests class

?>