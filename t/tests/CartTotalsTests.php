<?php
/**
 * CartTotalsTests
 *
 *
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited,  2 February, 2011
 * @package shopp
 * @subpackage
 **/

/**
 * CartTotalsTests
 *
 * @author
 * @since 1.1
 * @package shopp
 **/
class CartTotalsTests extends ShoppTestCase {

	function test_cart_base_case () {
		$Order =& ShoppOrder();
		$Order->Cart->clear();



		$Product = new Product(1); $Price = false;
		$Order->Cart->add(1,$Product,$Price,false);
		$Order->Cart->totals();

		$options = array('return' => '1','wrapper'=>'0');

		$expected = '$59.82';
		while(shopp('cart', 'items')){
			$actual = shopp('cartitem','unitprice',$options);
			$this->assertEquals($expected, $actual,'Cart line item unit price assertion failed');
			$actual = shopp('cartitem','total',$options);
			$this->assertEquals($expected, $actual,'Cart line item total assertion failed');
		}

		$actual = shopp('cart','subtotal',$options);
		$this->assertEquals($expected, $actual,'Cart subtotal assertion failed');

		$expected = '$0.00';
		$actual = shopp('cart','discount',$options);
		$this->assertEquals($expected, $actual,'Cart discount assertion failed');

		$expected = '$8.97';
		$actual = shopp('cart','tax',$options);
		$this->assertEquals($expected, $actual,'Cart tax assertion failed');

		$expected = '$3.00';
		$actual = shopp('cart','shipping',$options);
		$this->assertEquals($expected, $actual,'Cart shipping assertion failed');

		$expected = '$71.79';
		$actual = shopp('cart','total',$options);
		$this->assertEquals($expected, $actual,'Cart grand Total assertion failed');

	}

	function test_cart_tax_shipping () {
		$Settings =& ShoppSettings();
		$Settings->registry['tax_shipping'] = 'on';

		$Order =& ShoppOrder();
		$Order->Cart->clear();

		$Product = new Product(1); $Price = false;
		$Order->Cart->add(1,$Product,$Price,false);
		$Order->Cart->totals();

		$options = array('return' => '1','wrapper'=>'0');

		$expected = '$59.82';
		while(shopp('cart', 'items')){
			$actual = shopp('cartitem','unitprice',$options);
			$this->assertEquals($expected, $actual,'Cart line item unit price assertion failed');
			$actual = shopp('cartitem','total',$options);
			$this->assertEquals($expected, $actual,'Cart line item total assertion failed');
		}

		$actual = shopp('cart','subtotal',$options);
		$this->assertEquals($expected, $actual,'Cart subtotal assertion failed');

		$expected = '$0.00';
		$actual = shopp('cart','discount',$options);
		$this->assertEquals($expected, $actual,'Cart discount assertion failed');

		$expected = '$9.42';
		$actual = shopp('cart','tax',$options);
		$this->assertEquals($expected, $actual,'Cart tax assertion failed');

		$expected = '$3.00';
		$actual = shopp('cart','shipping',$options);
		$this->assertEquals($expected, $actual,'Cart shipping assertion failed');

		$expected = '$72.24';
		$actual = shopp('cart','total',$options);
		$this->assertEquals($expected, $actual,'Cart grand Total assertion failed');
		$Settings->registry['tax_shipping'] = 'off';

	}

	function test_cart_item_percent_discount () {
		global $Shopp;
		@$Shopp->Shopping->reset();
		$Settings =& ShoppSettings();
		$Order =& ShoppOrder();

		$Product = new Product(1); $Price = false;
		$Order->Cart->add(1,$Product,$Price,false);

		$Order->Cart->promocode = "cart-item-promo";
		$Order->Cart->changed(true);
		$Order->Cart->totals();

		$options = array('return' => '1','wrapper'=>'0');

		$expected = '$59.82';
		while(shopp('cart', 'items')){
			$actual = shopp('cartitem','unitprice',$options);
			$this->assertEquals($expected, $actual,'Cart line item unit price assertion failed');
			$actual = shopp('cartitem','total',$options);
			$this->assertEquals($expected, $actual,'Cart line item total assertion failed');
		}

		$expected = '$59.82';
		$actual = shopp('cart','subtotal',$options);
		$this->assertEquals($expected, $actual,'Cart subtotal assertion failed');

		$expected = '$19.74';
		$actual = shopp('cart','discount',$options);
		$this->assertEquals($expected, $actual,'Cart discount assertion failed');

		$expected = '$6.01';
		$actual = shopp('cart','tax',$options);
		$this->assertEquals($expected, $actual,'Cart tax assertion failed');

		$expected = '$3.00';
		$actual = shopp('cart','shipping',$options);
		$this->assertEquals($expected, $actual,'Cart shipping assertion failed');

		$expected = '$49.09';
		$actual = shopp('cart','total',$options);
		$this->assertEquals($expected, $actual,'Cart grand Total assertion failed');

	}

	public function test_cartitem_amountoff_promocode_multi_qty () {
		global $Shopp;
		$Order =& ShoppOrder();
		$Order->Cart->clear();

		$product = new Product(55);

		$price = 108;
		$Order->Cart->add(10, $product, $price, false);
		$this->assertTrue(shopp('cart','hasitems'));
		$this->assertEquals(count($Order->Cart->contents),1);

		// Create promo
		$Promotion = new Promotion();
		$Promotion->status = "enabled";
		$Promotion->type = "Amount Off";
		$Promotion->discount = 1; // $1 off
		$Promotion->target = "Cart Item"; //Applied to entire order
		$Promotion->search = "all"; // all rules must apply, alternatively "any"
		$Promotion->rules = array(
			"item" => array( // item rules
				array(
					"property" => "Name",
					"logic" => "Is equal to",
					"value" => "Smart & Sexy - Push-Up Underwire Bra and Thong Panty Set"
					)
			),
			1 => array(
				"property"=>"Promo code",
				"logic"=>"Is equal to",
				"value"=>"cartitemamount"
			)
		);

		$Shopp->Promotions->promotions = array($Promotion);
		$Order->Cart->promocode = "cartitemamount";
		$Order->Cart->totals();
		$Shopp->Promotions->reload();

		$options = array('return' => '1','wrapper'=>'0');

		while(shopp('cart', 'items')){
			$expected = '$10.00';
			$actual = shopp('cartitem','unitprice',$options);
			$this->assertEquals($expected, $actual,'Cart line item unit price assertion failed');
			$expected = '$100.00';
			$actual = shopp('cartitem','total',$options);
			$this->assertEquals($expected, $actual,'Cart line item total assertion failed');
		}

		$expected = '$100.00';
		$actual = shopp('cart','subtotal',$options);
		$this->assertEquals($expected, $actual,'Cart subtotal assertion failed');

		$expected = '$10.00';
		$actual = shopp('cart','discount',$options);
		$this->assertEquals($expected, $actual,'Cart discount assertion failed');

		$expected = '$13.50';
		$actual = shopp('cart','tax',$options);
		$this->assertEquals($expected, $actual,'Cart tax assertion failed');

		$expected = '$3.00';
		$actual = shopp('cart','shipping',$options);
		$this->assertEquals($expected, $actual,'Cart shipping assertion failed');

		$expected = '$106.50';
		$actual = shopp('cart','total',$options);
		$this->assertEquals($expected, $actual,'Cart grand Total assertion failed');
	}

	public function test_cartitem_amoutoff_multi_qty () {
		global $Shopp;
		$Order =& ShoppOrder();
		$Order->Cart->clear();

		$product = new Product(55);

		$price = 108;
		$Order->Cart->add(10, $product, $price, false);
		$this->assertTrue(shopp('cart','hasitems'));
		$this->assertEquals(count($Order->Cart->contents),1);

		// Create promo
		$Promotion = new Promotion();
		$Promotion->status = "enabled";
		$Promotion->type = "Amount Off";
		$Promotion->discount = 1; // $1 off
		$Promotion->target = "Cart Item"; //Applied to entire order
		$Promotion->search = "all"; // all rules must apply, alternatively "any"
		$Promotion->rules = array(
			"item" => array( // item rules
				array(
					"property" => "Name",
					"logic" => "Is equal to",
					"value" => "Smart & Sexy - Push-Up Underwire Bra and Thong Panty Set"
					),
				array(
					"property" => "Quantity",
					"logic" => "Is greater than",
					"value" => "9"
				)
			),
			1 => array(
				"property"=>"Total quantity",
				"logic"=>"Is greater than",
				"value"=>"1"
			)
		);

		$Shopp->Promotions->promotions = array($Promotion);
		$Order->Cart->totals();
		$Shopp->Promotions->reload();

		$options = array('return' => '1','wrapper'=>'0');

		while(shopp('cart', 'items')){
			$expected = '$10.00';
			$actual = shopp('cartitem','unitprice',$options);
			$this->assertEquals($expected, $actual,'Cart line item unit price assertion failed');
			$expected = '$100.00';
			$actual = shopp('cartitem','total',$options);
			$this->assertEquals($expected, $actual,'Cart line item total assertion failed');
		}

		$expected = '$100.00';
		$actual = shopp('cart','subtotal',$options);
		$this->assertEquals($expected, $actual,'Cart subtotal assertion failed');

		$expected = '$10.00';
		$actual = shopp('cart','discount',$options);
		$this->assertEquals($expected, $actual,'Cart discount assertion failed');

		$expected = '$13.50';
		$actual = shopp('cart','tax',$options);
		$this->assertEquals($expected, $actual,'Cart tax assertion failed');

		$expected = '$3.00';
		$actual = shopp('cart','shipping',$options);
		$this->assertEquals($expected, $actual,'Cart shipping assertion failed');

		$expected = '$106.50';
		$actual = shopp('cart','total',$options);
		$this->assertEquals($expected, $actual,'Cart grand Total assertion failed');
	}

	public function test_cartitem_buy_x_get_y () {
		global $Shopp;
		$Order =& ShoppOrder();
		$Order->Cart->clear();

		$product = new Product(55);

		$price = 108;
		$Order->Cart->add(6, $product, $price, false);
		$this->assertTrue(shopp('cart','hasitems'));
		$this->assertEquals(count($Order->Cart->contents),1);

		$options = array('return' => '1','wrapper'=>'0');

		while(shopp('cart', 'items')){
			$expected = '$10.00';
			$actual = shopp('cartitem','unitprice',$options);
			$this->assertEquals($expected, $actual,'Cart line item unit price assertion failed');
			$expected = '$60.00';
			$actual = shopp('cartitem','total',$options);
			$this->assertEquals($expected, $actual,'Cart line item total assertion failed');
		}

		// Create promo
		$Promotion = new Promotion();
		$Promotion->status = "enabled";
		$Promotion->type = "Buy X Get Y Free";
		$Promotion->buyqty = 5;
		$Promotion->getqty = 1;
		$Promotion->target = "Cart Item"; //Applied to entire order
		$Promotion->search = "all"; // all rules must apply, alternatively "any"
		$Promotion->rules = array(
			"item" => array( // item rules
				array(
					"property" => "Name",
					"logic" => "Is equal to",
					"value" => "Smart & Sexy - Push-Up Underwire Bra and Thong Panty Set"
					)
			),
			1 => array(
				"property"=>"Promo code",
				"logic"=>"Is equal to",
				"value"=>"buy5get1"
			)
		);

		$Shopp->Promotions->promotions = array($Promotion);
		$Order->Cart->promocode = "buy5get1";
		$Order->Cart->totals();
		$Shopp->Promotions->reload();

		$expected = '$60.00';
		$actual = shopp('cart','subtotal',$options);
		$this->assertEquals($expected, $actual,'Cart subtotal assertion failed');

		$expected = '$10.00';
		$actual = shopp('cart','discount',$options);
		$this->assertEquals($expected, $actual,'Cart discount assertion failed');

		$expected = '$7.50';
		$actual = shopp('cart','tax',$options);
		$this->assertEquals($expected, $actual,'Cart tax assertion failed');

		$expected = '$3.00';
		$actual = shopp('cart','shipping',$options);
		$this->assertEquals($expected, $actual,'Cart shipping assertion failed');

		$expected = '$60.50';
		$actual = shopp('cart','total',$options);
		$this->assertEquals($expected, $actual,'Cart grand Total assertion failed');
	}

	function test_cart_order_percent_discount () {
		global $Shopp;
		@$Shopp->Shopping->reset();
		$Settings =& ShoppSettings();
		$Order =& ShoppOrder();

		$Product = new Product(1); $Price = false;
		$Order->Cart->add(1,$Product,$Price,false);

		$Order->Cart->promocode = "2percent";
		$Order->Cart->changed(true);
		$Order->Cart->totals();

		$options = array('return' => '1','wrapper'=>'0');

		$expected = '$59.82';
		while(shopp('cart', 'items')){
			$actual = shopp('cartitem','unitprice',$options);
			$this->assertEquals($expected, $actual,'Cart line item unit price assertion failed');
			$actual = shopp('cartitem','total',$options);
			$this->assertEquals($expected, $actual,'Cart line item total assertion failed');
		}

		$expected = '$59.82';
		$actual = shopp('cart','subtotal',$options);
		$this->assertEquals($expected, $actual,'Cart subtotal assertion failed');

		$expected = '$1.20';
		$actual = shopp('cart','discount',$options);
		$this->assertEquals($expected, $actual,'Cart discount assertion failed');

		$expected = '$8.97';
		$actual = shopp('cart','tax',$options);
		$this->assertEquals($expected, $actual,'Cart tax assertion failed');

		$expected = '$3.00';
		$actual = shopp('cart','shipping',$options);
		$this->assertEquals($expected, $actual,'Cart shipping assertion failed');

		$expected = '$70.59';
		$actual = shopp('cart','total',$options);
		$this->assertEquals($expected, $actual,'Cart grand Total assertion failed');

	}

	function test_cart_shipping_discount () {
		global $Shopp;
		@$Shopp->Shopping->reset();
		$Settings =& ShoppSettings();
		$Order =& ShoppOrder();

		$Product = new Product(1); $Price = false;
		$Order->Cart->add(1,$Product,$Price,false);

		$Order->Cart->promocode = "FreeShip";
		$Order->Cart->changed(true);
		$Order->Cart->totals();

		$options = array('return' => '1','wrapper'=>'0');

		$expected = '$59.82';
		while(shopp('cart', 'items')){
			$actual = shopp('cartitem','unitprice',$options);
			$this->assertEquals($expected, $actual,'Cart line item unit price assertion failed');
			$actual = shopp('cartitem','total',$options);
			$this->assertEquals($expected, $actual,'Cart line item total assertion failed');
		}

		$expected = '$59.82';
		$actual = shopp('cart','subtotal',$options);
		$this->assertEquals($expected, $actual,'Cart subtotal assertion failed');

		$expected = '$0.00';
		$actual = shopp('cart','discount',$options);
		$this->assertEquals($expected, $actual,'Cart discount assertion failed');

		$expected = '$8.97';
		$actual = shopp('cart','tax',$options);
		$this->assertEquals($expected, $actual,'Cart tax assertion failed');

		$expected = '$0.00';
		$actual = shopp('cart','shipping',$options);
		$this->assertEquals($expected, $actual,'Cart shipping assertion failed');

		$expected = '$68.79';
		$actual = shopp('cart','total',$options);
		$this->assertEquals($expected, $actual,'Cart grand Total assertion failed');

	}


	function test_cart_vat_base_case () {
		global $Shopp;
		@$Shopp->Shopping->reset();

		$Settings =& ShoppSettings();
		$Settings->registry['base_operations'] = unserialize('a:7:{s:4:"name";s:14:"United Kingdom";s:8:"currency";a:2:{s:4:"code";s:3:"GBP";s:6:"format";a:6:{s:4:"cpos";b:1;s:8:"currency";s:2:"£";s:9:"precision";i:2;s:8:"decimals";s:1:".";s:9:"thousands";s:1:",";s:8:"grouping";a:1:{i:0;i:3;}}}s:5:"units";s:6:"metric";s:6:"region";i:3;s:7:"country";s:2:"GB";s:4:"zone";N;s:3:"vat";b:1;}');

		$Order =& ShoppOrder();
		$Order->Cart->clear();

		$Product = new Product(1); $Price = false;
		$Order->Cart->add(1,$Product,$Price,false);
		$Order->Cart->totals();

		$options = array('return' => '1','wrapper'=>'0');

		$expected = '£68.79';
		while(shopp('cart', 'items')){
			$actual = shopp('cartitem','unitprice',$options);
			$this->assertEquals($expected, $actual,'Cart line item unit price assertion failed');
			$actual = shopp('cartitem','total',$options);
			$this->assertEquals($expected, $actual,'Cart line item total assertion failed');
		}

		$expected = '£59.82';
		$actual = shopp('cart','subtotal',$options);
		$this->assertEquals($expected, $actual,'Cart subtotal assertion failed');

		$expected = '£0.00';
		$actual = shopp('cart','discount',$options);
		$this->assertEquals($expected, $actual,'Cart discount assertion failed');

		$expected = '£8.97';
		$actual = shopp('cart','tax',$options);
		$this->assertEquals($expected, $actual,'Cart tax assertion failed');

		$expected = '£3.00';
		$actual = shopp('cart','shipping',$options);
		$this->assertEquals($expected, $actual,'Cart shipping assertion failed');

		$expected = '£71.79';
		$actual = shopp('cart','total',$options);
		$this->assertEquals($expected, $actual,'Cart grand Total assertion failed');

	}

	function test_cart_vat_taxed_shipping () {
		global $Shopp;
		@$Shopp->Shopping->reset();

		$Settings =& ShoppSettings();
		$Settings->registry['base_operations'] = unserialize('a:7:{s:4:"name";s:14:"United Kingdom";s:8:"currency";a:2:{s:4:"code";s:3:"GBP";s:6:"format";a:6:{s:4:"cpos";b:1;s:8:"currency";s:2:"£";s:9:"precision";i:2;s:8:"decimals";s:1:".";s:9:"thousands";s:1:",";s:8:"grouping";a:1:{i:0;i:3;}}}s:5:"units";s:6:"metric";s:6:"region";i:3;s:7:"country";s:2:"GB";s:4:"zone";N;s:3:"vat";b:1;}');
		$Settings->registry['tax_shipping'] = 'on';

		$Order =& ShoppOrder();
		$Order->Cart->clear();

		$Product = new Product(1); $Price = false;
		$Order->Cart->add(1,$Product,$Price,false);
		$Order->Cart->totals();

		$options = array('return' => '1','wrapper'=>'0');

		$expected = '£68.79';
		while(shopp('cart', 'items')){
			$actual = shopp('cartitem','unitprice',$options);
			$this->assertEquals($expected, $actual,'Cart line item unit price assertion failed');
			$actual = shopp('cartitem','total',$options);
			$this->assertEquals($expected, $actual,'Cart line item total assertion failed');
		}

		$expected = '£59.82';
		$actual = shopp('cart','subtotal',$options);
		$this->assertEquals($expected, $actual,'Cart subtotal assertion failed');

		$expected = '£0.00';
		$actual = shopp('cart','discount',$options);
		$this->assertEquals($expected, $actual,'Cart discount assertion failed');

		$expected = '£9.36';
		$actual = shopp('cart','tax',$options);
		$this->assertEquals($expected, $actual,'Cart tax assertion failed');

		$expected = '£2.61';
		$actual = shopp('cart','shipping',$options);
		$this->assertEquals($expected, $actual,'Cart shipping assertion failed');

		$expected = '£71.79';
		$actual = shopp('cart','total',$options);
		$this->assertEquals($expected, $actual,'Cart grand Total assertion failed');
		$Settings->registry['tax_shipping'] = 'off';

	}

	function test_cart_vat_item_percent_discount () {
		global $Shopp;
		@$Shopp->Shopping->reset();

		$Settings =& ShoppSettings();
		$Settings->registry['base_operations'] = unserialize('a:7:{s:4:"name";s:14:"United Kingdom";s:8:"currency";a:2:{s:4:"code";s:3:"GBP";s:6:"format";a:6:{s:4:"cpos";b:1;s:8:"currency";s:2:"£";s:9:"precision";i:2;s:8:"decimals";s:1:".";s:9:"thousands";s:1:",";s:8:"grouping";a:1:{i:0;i:3;}}}s:5:"units";s:6:"metric";s:6:"region";i:3;s:7:"country";s:2:"GB";s:4:"zone";N;s:3:"vat";b:1;}');

		$Order =& ShoppOrder();
		$Order->Cart->clear();

		$Product = new Product(1); $Price = false;
		$Order->Cart->add(1,$Product,$Price,false);

		$Order->Cart->promocode = "cart-item-promo";
		$Order->Cart->changed(true);
		$Order->Cart->totals();

		$options = array('return' => '1','wrapper'=>'0');

		$expected = '£68.79';
		while(shopp('cart', 'items')){
			$actual = shopp('cartitem','unitprice',$options);
			$this->assertEquals($expected, $actual,'Cart line item unit price assertion failed');
			$actual = shopp('cartitem','total',$options);
			$this->assertEquals($expected, $actual,'Cart line item total assertion failed');
		}

		$expected = '£59.82';
		$actual = shopp('cart','subtotal',$options);
		$this->assertEquals($expected, $actual,'Cart subtotal assertion failed');

		$expected = '£19.74';
		$actual = shopp('cart','discount',$options);
		$this->assertEquals($expected, $actual,'Cart discount assertion failed');

		$expected = '£6.01';
		$actual = shopp('cart','tax',$options);
		$this->assertEquals($expected, $actual,'Cart tax assertion failed');

		$expected = '£3.00';
		$actual = shopp('cart','shipping',$options);
		$this->assertEquals($expected, $actual,'Cart shipping assertion failed');

		$expected = '£49.09';
		$actual = shopp('cart','total',$options);
		$this->assertEquals($expected, $actual,'Cart grand Total assertion failed');

	}

	function test_cart_vat_order_percent_discount () {
		global $Shopp;
		@$Shopp->Shopping->reset();

		$Settings =& ShoppSettings();
		$Settings->registry['base_operations'] = unserialize('a:7:{s:4:"name";s:14:"United Kingdom";s:8:"currency";a:2:{s:4:"code";s:3:"GBP";s:6:"format";a:6:{s:4:"cpos";b:1;s:8:"currency";s:2:"£";s:9:"precision";i:2;s:8:"decimals";s:1:".";s:9:"thousands";s:1:",";s:8:"grouping";a:1:{i:0;i:3;}}}s:5:"units";s:6:"metric";s:6:"region";i:3;s:7:"country";s:2:"GB";s:4:"zone";N;s:3:"vat";b:1;}');

		$Order =& ShoppOrder();
		$Order->Cart->clear();

		$Product = new Product(1); $Price = false;
		$Order->Cart->add(1,$Product,$Price,false);

		$Order->Cart->promocode = "2percent";
		$Order->Cart->changed(true);
		$Order->Cart->totals();

		$options = array('return' => '1','wrapper'=>'0');

		$expected = '£68.79';
		while(shopp('cart', 'items')){
			$actual = shopp('cartitem','unitprice',$options);
			$this->assertEquals($expected, $actual,'Cart line item unit price assertion failed');
			$actual = shopp('cartitem','total',$options);
			$this->assertEquals($expected, $actual,'Cart line item total assertion failed');
		}

		$expected = '£59.82';
		$actual = shopp('cart','subtotal',$options);
		$this->assertEquals($expected, $actual,'Cart subtotal assertion failed');

		$expected = '£1.20';
		$actual = shopp('cart','discount',$options);
		$this->assertEquals($expected, $actual,'Cart discount assertion failed');

		$expected = '£8.97';
		$actual = shopp('cart','tax',$options);
		$this->assertEquals($expected, $actual,'Cart tax assertion failed');

		$expected = '£3.00';
		$actual = shopp('cart','shipping',$options);
		$this->assertEquals($expected, $actual,'Cart shipping assertion failed');

		$expected = '£70.59';
		$actual = shopp('cart','total',$options);
		$this->assertEquals($expected, $actual,'Cart grand Total assertion failed');

	}

	function test_cart_vat_shipping_discount () {
		global $Shopp;
		@$Shopp->Shopping->reset();

		$Settings =& ShoppSettings();
		$Settings->registry['base_operations'] = unserialize('a:7:{s:4:"name";s:14:"United Kingdom";s:8:"currency";a:2:{s:4:"code";s:3:"GBP";s:6:"format";a:6:{s:4:"cpos";b:1;s:8:"currency";s:2:"£";s:9:"precision";i:2;s:8:"decimals";s:1:".";s:9:"thousands";s:1:",";s:8:"grouping";a:1:{i:0;i:3;}}}s:5:"units";s:6:"metric";s:6:"region";i:3;s:7:"country";s:2:"GB";s:4:"zone";N;s:3:"vat";b:1;}');

		$Order =& ShoppOrder();
		$Order->Cart->clear();

		$Product = new Product(1); $Price = false;
		$Order->Cart->add(1,$Product,$Price,false);

		$Order->Cart->promocode = "FreeShip";
		$Order->Cart->changed(true);
		$Order->Cart->totals();

		$options = array('return' => '1','wrapper'=>'0');

		$expected = '£68.79';
		while(shopp('cart', 'items')){
			$actual = shopp('cartitem','unitprice',$options);
			$this->assertEquals($expected, $actual,'Cart line item unit price assertion failed');
			$actual = shopp('cartitem','total',$options);
			$this->assertEquals($expected, $actual,'Cart line item total assertion failed');
		}

		$expected = '£59.82';
		$actual = shopp('cart','subtotal',$options);
		$this->assertEquals($expected, $actual,'Cart subtotal assertion failed');

		$expected = '£0.00';
		$actual = shopp('cart','discount',$options);
		$this->assertEquals($expected, $actual,'Cart discount assertion failed');

		$expected = '£8.97';
		$actual = shopp('cart','tax',$options);
		$this->assertEquals($expected, $actual,'Cart tax assertion failed');

		$expected = '£0.00';
		$actual = shopp('cart','shipping',$options);
		$this->assertEquals($expected, $actual,'Cart shipping assertion failed');

		$expected = '£68.79';
		$actual = shopp('cart','total',$options);
		$this->assertEquals($expected, $actual,'Cart grand Total assertion failed');

	}


} // end CartTotalsTests class

?>