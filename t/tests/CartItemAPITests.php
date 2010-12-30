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

	// example for adding product to cart by the product id and price id
	function test_cartitem_addbypriceid () {
		$Order =& ShoppOrder();
		$Order->Cart->clear();

		$product = new Product(55);

		$price = 108;
		$Order->Cart->add(1, $product, $price, false);
		$this->assertTrue(shopp('cart','hasitems'));
		$this->assertEquals(count($Order->Cart->contents),1);

	}

	// example for adding product to cart by the product id and the option number
	function test_cartitem_addbyoptionid () {
		$Order =& ShoppOrder();
		$Order->Cart->clear();

		$product = new Product(55);

		$price = array(1);
		$Order->Cart->add(1, $product, $price, false);
		$this->assertTrue(shopp('cart','hasitems'));
		$this->assertEquals(count($Order->Cart->contents),1);
	}

	function test_cartitem_name () {
		$Order =& ShoppOrder();
		$Order->Cart->clear();

		$product = new Product(55);

		$price = array(1);
		$Order->Cart->add(1, $product, $price, false);
		$this->assertTrue(shopp('cart','hasitems'));
		ob_start();
		while(shopp('cart', 'items')) shopp('cartitem','name');
		$actual = ob_get_contents();
		ob_end_clean();
		$expected = "Smart & Sexy - Push-Up Underwire Bra and Thong Panty Set";
		$this->assertEquals($expected, $actual);
	}

	function test_cartitem_url () {
		$Order =& ShoppOrder();
		$Order->Cart->clear();

		$product = new Product(55);

		$price = array(1);
		$Order->Cart->add(1, $product, $price, false);
		$this->assertTrue(shopp('cart','hasitems'));
		ob_start();
		while(shopp('cart', 'items')) shopp('cartitem','url');
		$actual = ob_get_contents();
		ob_end_clean();
		$expected = "http://shopptest/store/smart-sexy-push-up-underwire-bra-and-thong-panty-set/";
		$this->assertEquals($expected, $actual);
	}

	function test_cartitem_sku () {
		$Order =& ShoppOrder();
		$Order->Cart->clear();

		$product = new Product(81);

		$price = array(11);
		$Order->Cart->add(1, $product, $price, false);
		$this->assertTrue(shopp('cart','hasitems'));
		ob_start();
		while(shopp('cart', 'items')) shopp('cartitem','sku');
		$actual = ob_get_contents();
		ob_end_clean();
		$expected = "BR-81";
		$this->assertEquals($expected, $actual);
	}

	function test_cartitem_unitprice () {
		$Order =& ShoppOrder();
		$Order->Cart->clear();

		$product = new Product(55);

		$price = array(1);
		$Order->Cart->add(1, $product, $price, false);
		$this->assertTrue(shopp('cart','hasitems'));

		while(shopp('cart', 'items')){
			ob_start();
			shopp('cartitem','unitprice');
			$actual = ob_get_contents();
			ob_end_clean();

			$expected = "$10.00";
			$this->assertEquals($expected, $actual);

			ob_start();
			shopp('cartitem', 'unitprice', 'currency=off&taxes=true');
			$actual = ob_get_contents();
			ob_end_clean();
			$expected = "11.50";
			$this->assertEquals($expected, $actual);
		}
	}

	function test_cartitem_tax () {
		$Order =& ShoppOrder();
		$Order->Cart->clear();

		$product = new Product(55);

		$price = array(1);
		$Order->Cart->add(1, $product, $price, false);
		$this->assertTrue(shopp('cart','hasitems'));

		while(shopp('cart', 'items')){
			// ob_start();
			// shopp('cartitem','tax');
			// $actual = ob_get_contents();
			// ob_end_clean();
			//
			// $expected = "$0.00";
			// $this->assertEquals($expected, $actual);

			$actual = "";
			ob_start();
			shopp('cartitem','tax','taxes=true');
			$actual = ob_get_contents();
			ob_end_clean();

			$expected = "$1.50";
			$this->assertEquals($expected, $actual);
		}
	}

	function test_cartitem_quantity () {
		$Order =& ShoppOrder();
		$Order->Cart->clear();

		$product = new Product(55);

		$price = 108;
		$Order->Cart->add(1, $product, $price, false);

		$product = new Product(81);

		$price = array(11);
		$Order->Cart->add(6, $product, $price, false);

		$this->assertTrue(shopp('cart','hasitems'));
		$this->assertEquals(count($Order->Cart->contents),2);
		while(shopp('cart', 'items')){
			$item = current($Order->Cart->contents);
			ob_start();
			shopp('cartitem','quantity');
			$actual = ob_get_contents();
			ob_end_clean();

			if($item->product == 55) $this->assertEquals("1", $actual);
			else $this->assertEquals("6", $actual);
		}

	}

	function test_cartitem_total () {
		$Order =& ShoppOrder();
		$Order->Cart->clear();

		$product = new Product(55);

		$price = array(1);
		$Order->Cart->add(3, $product, $price, false);
		$this->assertTrue(shopp('cart','hasitems'));

		while(shopp('cart', 'items')){
			ob_start();
			shopp('cartitem','total');
			$actual = ob_get_contents();
			ob_end_clean();

			$expected = "$30.00";
			$this->assertEquals($expected, $actual);

			ob_start();
			shopp('cartitem','total', 'taxes=on&currency=off');
			$actual = ob_get_contents();
			ob_end_clean();

			$expected = "34.50";
			$this->assertEquals($expected, $actual);
		}
	}

	function test_cartitem_quantityinput () {
		$Order =& ShoppOrder();
		$Order->Cart->clear();

		$product = new Product(55);

		$price = 108;
		$Order->Cart->add(1, $product, $price, false);
		$this->assertTrue(shopp('cart','hasitems'));
		$this->assertEquals(count($Order->Cart->contents),1);

		while(shopp('cart', 'items')){
			ob_start();
			shopp('cartitem','quantity', 'input=menu');
			$actual = ob_get_contents();
			ob_end_clean();

			ob_start();
?><select name="items[0][quantity]"><option selected="selected" value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option><option value="6">6</option><option value="7">7</option><option value="8">8</option><option value="9">9</option><option value="10">10</option><option value="11">11</option><option value="12">12</option><option value="13">13</option><option value="14">14</option><option value="15">15</option><option value="20">20</option><option value="25">25</option><option value="30">30</option><option value="35">35</option><option value="40">40</option><option value="45">45</option><option value="50">50</option><option value="60">60</option><option value="70">70</option><option value="80">80</option><option value="90">90</option><option value="100">100</option></select><?php
			$expected = ob_get_contents();
			ob_end_clean();

			$this->assertEquals($expected,$actual);
			$this->assertValidMarkup($actual);

			ob_start();
			shopp('cartitem','quantity', 'input=text&class=myClass');
			$actual = ob_get_contents();
			ob_end_clean();

			ob_start();
			?><input type="text" name="items[0][quantity]" id="items-0-quantity"  size="5" value="1" class="myClass"/><?php
			$expected = ob_get_contents();
			ob_end_clean();
			$this->assertEquals($expected,$actual);
			$this->assertValidMarkup($actual);
		}
	}

		function test_cartitem_remove () {
			global $Shopp;
			$Order =& ShoppOrder();
			$Order->Cart->clear();

			$product = new Product(55);

			$price = 108;
			$Order->Cart->add(1, $product, $price, false);
			$this->assertTrue(shopp('cart','hasitems'));
			$this->assertEquals(count($Order->Cart->contents),1);

			while(shopp('cart', 'items')){
				ob_start();
				shopp('cartitem','remove', 'input=button&label=My Label&class=myclass');
				$actual = ob_get_contents();
				ob_end_clean();

				ob_start();
				?><button type="submit" name="remove[0]" value="0" class="myclass" tabindex="">My Label</button><?php
				$expected = ob_get_contents();
				ob_end_clean();

				$this->assertEquals($expected,$actual);
				$this->assertValidMarkup($actual);

				ob_start();
				shopp('cartitem','remove', 'label=My Label 2&class=myclass2');
				$actual = ob_get_contents();
				ob_end_clean();

				ob_start();
				?><a href="http://shopptest/store/cart/?cart=update&amp;item=0&amp;quantity=0" class="myclass2">My Label 2</a><?php
				$expected = ob_get_contents();
				ob_end_clean();

				$this->assertEquals($expected,$actual);
				$this->assertValidMarkup($actual);
			}
		}

		function test_cartitem_coverimage (){
			global $Shopp;
			$Order =& ShoppOrder();
			$Order->Cart->clear();

			$product = new Product(55);

			$price = 108;
			$Order->Cart->add(1, $product, $price, false);
			$this->assertTrue(shopp('cart','hasitems'));
			while(shopp('cart', 'items')){
				ob_start();
				shopp('cartitem','coverimage','class=cart-thumb&width=200&height=220');
				$actual = ob_get_contents();
				ob_end_clean();

				ob_start();
				?><img src="http://shopptest/store/images/623/?200,220,2321611135" alt="Smart &amp; Sexy - Push-Up Underwire Bra and Thong Panty Set" width="200" height="192" class="cart-thumb" /><?php
				$expected = ob_get_contents();
				ob_end_clean();

				$this->assertEquals($expected, $actual);
				$this->assertValidMarkup($actual);
			}
			$Order->Cart->clear();
			$product = new Product(9);

			$price = array(1,8);
			$Order->Cart->add(1, $product, $price, false);
			$this->assertTrue(shopp('cart','hasitems'));
			while(shopp('cart', 'items')){
				ob_start();
				shopp('cartitem','coverimage');
				$actual = ob_get_contents();
				ob_end_clean();

				ob_start();
				?><img src="http://shopptest/store/images/555/?48,48,951692384" alt="Faded Glory - Men&#039;s Original Fit Jeans" width="48" height="48" /><?php
				$expected = ob_get_contents();
				ob_end_clean();

				$this->assertEquals($expected, $actual);
				$this->assertValidMarkup($actual);
			}

		}

		function test_cartitem_options (){
			global $Shopp;
			$Order =& ShoppOrder();
			$Order->Cart->clear();

			$product = new Product(55);

			$price = 108;
			$Order->Cart->add(1, $product, $price, false);
			$this->assertTrue(shopp('cart','hasitems'));
			while(shopp('cart', 'items')){
				ob_start();
				shopp('cartitem','options','before=<div>&after=</div>');
				$actual = ob_get_contents();
				ob_end_clean();

				ob_start();
				?><div><input type="hidden" name="items[0][product]" value="55"/> <select name="items[0][price]" id="items-0-price"><option value="108" selected="selected">34A</option><option value="109">34B</option><option value="110">36B</option><option value="111">36C</option><option value="112">38C</option></select></div><?php
				$expected = ob_get_contents();
				ob_end_clean();

				$this->assertEquals($expected, $actual);
				$this->assertValidMarkup($actual);
			}
		}

		function test_cartitem_inputs (){
			global $Shopp;
			$Order =& ShoppOrder();
			$Order->Cart->clear();

			$product = new Product(55);

			$price = 108;
			$data = array('customField1' => "Custom Value1\nWith Newline", 'customField2' => 'Custom Value2');
			$Order->Cart->add(1, $product, $price, false, $data);
			$this->assertTrue(shopp('cart','hasitems'));
			while(shopp('cart', 'items')){
				$this->assertTrue(shopp('cartitem', 'hasinputs'));
				while(shopp('cartitem', 'inputs')) {
					ob_start();
					shopp('cartitem', 'input', 'name');
					$name = ob_get_contents();
					ob_end_clean();

					ob_start();
					shopp('cartitem', 'input');
					$actual = ob_get_contents();
					ob_end_clean();

					$this->assertTrue(isset($name));
					$expected = $data[$name];

					$this->assertEquals($expected, $actual);
				}
			}
		}

		function test_cartitem_inputslist (){
			global $Shopp;
			$Order =& ShoppOrder();
			$Order->Cart->clear();

			$product = new Product(55);

			$price = 108;
			$data = array('customField1' => "Custom Value1\nWith Newline", 'customField2' => 'Custom Value2', 'merryxmas'=>'Merry Christmas');
			$Order->Cart->add(1, $product, $price, false, $data);

			$this->assertTrue(shopp('cart','hasitems'));
			while(shopp('cart', 'items')){
				$this->assertTrue(shopp('cartitem', 'hasinputs'));
				while(shopp('cartitem', 'inputs')) {
					$this->assertTrue(shopp('cartitem', 'hasinputs'));
					ob_start();
					shopp('cartitem', 'inputslist');
					$actual = ob_get_contents();
					ob_end_clean();
					$this->assertTrue(!empty($actual));

					ob_start();
					?><ul><li><strong>customField1</strong>: Custom Value1
With Newline</li><li><strong>customField2</strong>: Custom Value2</li><li><strong>merryxmas</strong>: Merry Christmas</li></ul><?php
					$expected = ob_get_contents();
					ob_end_clean();

					$this->assertEquals($expected, $actual);
					$this->assertValidMarkup($actual);

					$this->assertTrue(shopp('cartitem', 'hasinputs'));
					ob_start();
					shopp('cartitem', 'inputslist','before=<div>&after=</div>&class=customdata&exclude=merryxmas');
					$actual = ob_get_contents();
					ob_end_clean();
					$this->assertTrue(!empty($actual));

					ob_start();
					?><div><ul class="customdata"><li><strong>customField1</strong>: Custom Value1
With Newline</li><li><strong>customField2</strong>: Custom Value2</li></ul></div><?php
					$expected = ob_get_contents();
					ob_end_clean();

					$this->assertEquals($expected, $actual);
					$this->assertValidMarkup($actual);
				}
			}
		}

} // end CartItemAPITests class

?>