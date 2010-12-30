<?php
/**
 * CartAPITests
 * 
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 19 October, 2009
 * @package 
 **/

/**
 * Initialize
 **/

class CartAPITests extends ShoppTestCase {
	
	function setUp () {
		parent::setUp();
		$Order =& ShoppOrder();
		$Order->Shipping->country = 'US';
	}

	function test_cart_url () {
		ob_start();
		shopp('cart','url');
		$actual = ob_get_contents();
		ob_end_clean();
		$this->assertEquals('http://shopptest/store/cart/',$actual);
	}
	
	function test_cart_hasitems () {
		$Order =& ShoppOrder();
		$Order->Cart->clear();
		$this->assertFalse(shopp('cart','hasitems'));

		$Product = new Product(81); 
		$Price = false;
		
		$Order->Cart->add(1,$Product,$Price,false);
		$this->assertTrue(shopp('cart','hasitems'));
	}
	
	function test_cart_totalitems () {
		$Order =& ShoppOrder();
		$Order->Cart->clear();
		$FirstProduct = new Product(81); $FirstPrice = false;
		$SecondProduct = new Product(82); $SecondPrice = false;
		$Order->Cart->totals();
		
		ob_start();
		shopp('cart','totalitems');
		$actual = ob_get_contents();
		ob_end_clean();
		$this->assertEquals(0,$actual);

		$Order->Cart->add(1,$FirstProduct,$FirstPrice,false);
		$Order->Cart->totals();

		ob_start();
		shopp('cart','totalitems');
		$actual = ob_get_contents();
		ob_end_clean();
		$this->assertEquals(1,$actual);

		$Order->Cart->add(1,$SecondProduct,$SecondPrice,false);
		$Order->Cart->totals();

		ob_start();
		shopp('cart','totalitems');
		$actual = ob_get_contents();
		ob_end_clean();
		$this->assertEquals(2,$actual);

		$Order->Cart->add(3,$FirstProduct,$FirstPrice,false);
		$Order->Cart->totals();

		ob_start();
		shopp('cart','totalitems');
		$actual = ob_get_contents();
		ob_end_clean();
		$this->assertEquals(5,$actual);
	}
	
	function test_cart_itemlooping () {
		global $Shopp;
		$Order =& ShoppOrder();
		$Order->Cart->clear();
		$FirstProduct = new Product(81); $FirstPrice = false;
		$Order->Cart->add(1,$FirstProduct,$FirstPrice,false);
		
		$SecondProduct = new Product(82); $SecondPrice = false;
		$Order->Cart->add(1,$SecondProduct,$SecondPrice,false);

		$Order->Cart->totals();
		
		ob_start();
		if (shopp('cart','hasitems'))
			while(shopp('cart','items'))
				shopp('cartitem','id');
		$actual = ob_get_contents();
		ob_end_clean();
		$this->assertEquals('01',$actual);
	}
	
	function test_cart_lastitem () {
		$Order =& ShoppOrder();
		$Order->Cart->clear();
		$FirstProduct = new Product(81); $FirstPrice = false;
		$Order->Cart->add(1,$FirstProduct,$FirstPrice,false);

		$Item = shopp('cart','lastitem','return=true');
		$this->assertEquals(81,$Item->product);
		
		$SecondProduct = new Product(82); $SecondPrice = false;
		$Order->Cart->add(1,$SecondProduct,$SecondPrice,false);

		$Item = shopp('cart','lastitem','return=true');
		$this->assertEquals(82,$Item->product);

		$Order->Cart->add(1,$FirstProduct,$FirstPrice,false);
		$Item = shopp('cart','lastitem','return=true');
		$this->assertEquals(81,$Item->product);		
	}
	
	function test_cart_haspromos () {
		$Order =& ShoppOrder();
		$Order->Cart->clear();
		$Product = new Product(81); $Price = false;
		$Order->Cart->add(1,$Product,$Price,false);
		$Order->Cart->totals();
		
		$this->assertFalse(shopp('cart','haspromos'));
		$_REQUEST['promocode'] = '2percent';
		$Order->Cart->request();
		$Order->Cart->totals();
		$this->assertTrue(shopp('cart','haspromos'));		
	}

	function test_cart_totalpromos () {
		$Order =& ShoppOrder();
		$Order->Cart->clear();
		$Product = new Product(81); $Price = false;
		$Order->Cart->add(1,$Product,$Price,false);
		$Order->Cart->totals();
		
		ob_start();
		shopp('cart','totalpromos');
		$actual = ob_get_contents();
		ob_end_clean();
		
		$this->assertEquals(0,$actual);
		$_REQUEST['promocode'] = '2percent';
		$Order->Cart->request();
		$Order->Cart->totals();
		ob_start();
		shopp('cart','totalpromos');
		$actual = ob_get_contents();
		ob_end_clean();
		$this->assertEquals(1,$actual);
	}

	function test_cart_promo_name () {
		$Order =& ShoppOrder();

		$Order->Cart->clear();

		$Product = new Product(81); $Price = false;
		$Order->Cart->add(1,$Product,$Price,false);
		$_REQUEST['promocode'] = '2percent';
		$Order->Cart->request();
		$Order->Cart->totals();
		$_REQUEST['promocode'] = '3DollarsOff';
		$Order->Cart->request();
		$Order->Cart->totals();
		
		ob_start();
		if (shopp('cart','haspromos'))
			while(shopp('cart','promos')) 
				shopp('cart','promo-name');
		$actual = ob_get_contents();
		ob_end_clean();
		
		$this->assertEquals('2% Off$3 Off',$actual);
	}

	function test_cart_promo_discount () {
		$Order =& ShoppOrder();

		$Order->Cart->clear();

		$Product = new Product(81); $Price = false;
		$Order->Cart->add(1,$Product,$Price,false);
		$_REQUEST['promocode'] = '2percent';
		$Order->Cart->request();
		$Order->Cart->totals();
		$_REQUEST['promocode'] = '3DollarsOff';
		$Order->Cart->request();
		$Order->Cart->totals();
		
		ob_start();
		if (shopp('cart','haspromos'))
			while(shopp('cart','promos')) 
				shopp('cart','promo-discount');
		$actual = ob_get_contents();
		ob_end_clean();
		
		$this->assertEquals('2% Off!$3.00 Off!',$actual);
	}
	
	function test_cart_function_tag () {
		new ShoppError('Error');
		ob_start();
		shopp('cart','function');
		$actual = ob_get_contents();
		ob_end_clean();
		
		$expected = array(
			'tag' => 'input',
			'attributes' => array('type' => 'hidden','name' => 'cart','value' => 'true')
		);
		$this->assertTag($expected,$actual,'',true);
		
		$expected = array(
			'tag' => 'input',
			'attributes' => array('type' => 'submit','name' => 'update','id'=>'hidden-update')
		);
		$this->assertTag($expected,$actual,'',true);

		$expected = array(
			'tag' => 'ul',
			'child' => array(
				'tag' => 'li',
				'content' => 'Error'
			)
		);
		$this->assertTag($expected,$actual,"$actual",true);
		$this->assertValidMarkup($actual);
	}
	
	function test_cart_emptybutton () {
		ob_start();
		shopp('cart','empty-button');
		$actual = ob_get_contents();
		ob_end_clean();
		
		$expected = array(
			'tag' => 'input',
			'attributes' => array('type' => 'submit','name' => 'empty','id' => 'empty-button')
		);
		$this->assertTag($expected,$actual,'',true);
		$this->assertValidMarkup($actual);
	}

	function test_cart_updatebutton () {
		ob_start();
		shopp('cart','update-button');
		$actual = ob_get_contents();
		ob_end_clean();
		
		$expected = array(
			'tag' => 'input',
			'attributes' => array('type' => 'submit','name' => 'update','class' => 'update-button')
		);
		$this->assertTag($expected,$actual,'',true);
		$this->assertValidMarkup($actual);
	}
	
	function test_cart_sidecart () {
		ob_start();
		shopp('cart','sidecart');
		$actual = ob_get_contents();
		ob_end_clean();
		
		$expected = array(
			'tag' => 'div',
			'attributes' => array('id' => 'shopp-cart-ajax')
		);
		$this->assertTag($expected,$actual,'',true);
		$this->assertTrue(!empty($actual));
	}
	
	function test_cart_hasdiscount () {
		$Order =& ShoppOrder();
		$Order->Cart->clear();
		$Product = new Product(81); $Price = false;
		$Order->Cart->add(1,$Product,$Price,false);
		$_REQUEST['promocode'] = '2percent';
		$Order->Cart->request();
		$Order->Cart->totals();

		$this->assertTrue(shopp('cart','hasdiscount'));
	}
	
	function test_cart_discount () {
		$Order =& ShoppOrder();
		$Order->Cart->clear();
		$Product = new Product(81); $Price = false;
		$Order->Cart->add(1,$Product,$Price,false);
		$_REQUEST['promocode'] = '2percent';
		$Order->Cart->request();
		$Order->Cart->totals();

		ob_start();
		shopp('cart','discount');
		$actual = ob_get_contents();
		ob_end_clean();
		$this->assertEquals('$1.28',$actual);
	}
	
	function test_cart_promosavailable () {
		global $Shopp;
		$Order =& ShoppOrder();
		$Order->Cart->clear();
		$Product = new Product(81); $Price = false;
		$Order->Cart->add(1,$Product,$Price,false);
		$Order->Cart->totals();
		
		$this->assertTrue(shopp('cart','promos-available'));
		$Shopp->Settings->registry['promo_limit'] = 1;
		$this->assertTrue(shopp('cart','promos-available'));

		$_REQUEST['promocode'] = '2percent';
		$Order->Cart->request();
		$Order->Cart->totals();
		
		$this->assertFalse(shopp('cart','promos-available'));

	}
	
	function test_cart_promocode () {
		$Order =& ShoppOrder();
		$Order->Cart->clear();

		ob_start();
		shopp('cart','promo-code');
		$actual = ob_get_contents();
		ob_end_clean();
		
		$expected = array(
			'tag' => 'input',
			'attributes' => array('type' => 'text','name' => 'promocode','id' => 'promocode')
		);
		$this->assertTag($expected,$actual,'',true);
		$expected = array(
			'tag' => 'input',
			'attributes' => array('type' => 'submit','name' => 'update','id' => 'apply-code')
		);
		$this->assertTag($expected,$actual,'',true);

		$this->assertValidMarkup($actual);
	}
	
	function test_cart_hasshippingmethods () {
		$Order =& ShoppOrder();
		$Order->Cart->clear();
	
		$Product = new Product(81); $Price = false;
		$Order->Cart->add(1,$Product,$Price,false);
		$Order->Cart->totals();

		$Order->Cart->shipping = array();
		$this->assertFalse(shopp('cart','has-shipping-methods'));
		$Order->Cart->shipping['Test'] = array (
            'name' => 'Test',
            'delivery' => 'prompt',
            'method' => 'FlatRates::order',
            'US' => array('0' => '3.00'),
            'North America' => array('0' => '5.00'),
            'Worldwide' => array('0' => '10.00'),
            'cost' => '3'
		);
		$this->assertTrue(shopp('cart','has-shipping-methods'));
	}
	
	function test_cart_needsshipped () {
		$Order =& ShoppOrder();
		$Order->Cart->clear();
	
		$Product = new Product(81); $Price = false;
		$Order->Cart->add(1,$Product,$Price,false);
		$Order->Cart->totals();
		$this->assertTrue(shopp('cart','needs-shipped'));
	}
	
	function test_cart_hasshipcosts () {
		$Order =& ShoppOrder();
		$Order->Cart->clear();
	
		$Product = new Product(81); $Price = false;
		$Order->Cart->add(1,$Product,$Price,false);
		$Order->Cart->totals();
		$this->assertTrue(shopp('cart','has-ship-costs'));
	}
	
	function test_cart_needsshippingestimates () {
		$Order =& ShoppOrder();
		$Order->Cart->clear();
	
		$Product = new Product(81); $Price = false;
		$Order->Cart->add(1,$Product,$Price,false);
		$Order->Cart->totals();
		$this->assertTrue(shopp('cart','needs-shipping-estimates'));
	}
	
	function test_cart_shippingestimates () {
		global $Shopp;
		$Order =& ShoppOrder();
		$Order->Cart->clear();
	
		$Product = new Product(81); $Price = false;
		$Order->Cart->add(1,$Product,$Price,false);
		$Order->Cart->totals();
		
		ob_start();
		shopp('cart','shipping-estimates');
		$actual = ob_get_contents();
		ob_end_clean();

		$expected = array(
			'tag' => 'select',
			'attributes' => array('name' => 'shipping[country]','id' => 'shipping-country')
		);
		$this->assertTag($expected,$actual,'',true);

		$Shopp->Settings->registry['target_markets'] = array('US' => 'USA');
		ob_start();
		shopp('cart','shipping-estimates');
		$actual = ob_get_contents();
		ob_end_clean();

		$expected = array(
			'tag' => 'input',
			'attributes' => array('type' => 'hidden','name' => 'shipping[country]','id' => 'shipping-country')
		);
		$this->assertTag($expected,$actual,'',true);

		ob_start();
		shopp('cart','shipping-estimates','postcode=on');
		$actual = ob_get_contents();
		ob_end_clean();

		$expected = array(
			'tag' => 'input',
			'attributes' => array('name' => 'shipping[postcode]','id' => 'shipping-postcode')
		);
		$this->assertTag($expected,$actual,'',true);
		
		$this->assertValidMarkup($actual);
		
	}
	
	function test_cart_subtotal () {
		$Order =& ShoppOrder();
		$Order->Cart->clear();
	
		$Product = new Product(81); $Price = false;
		$Order->Cart->add(1,$Product,$Price,false);
		$Order->Cart->totals();
		
		ob_start();
		shopp('cart','subtotal');
		$actual = ob_get_contents();
		ob_end_clean();
		
		$expected = array(
			'tag' => 'span',
			'attributes' => array('class' => 'shopp_cart_subtotal'),
			'content' => '$63.86'
		);
		$this->assertTag($expected,$actual,'',true);
		
		$this->assertValidMarkup($actual);
	}
	
	function test_cart_shipping () {
		$Order =& ShoppOrder();
		$Order->Cart->clear();
	
		$Product = new Product(81); $Price = false;
		$Order->Cart->add(1,$Product,$Price,false);
		$Order->Cart->totals();
		
		ob_start();
		shopp('cart','shipping');
		$actual = ob_get_contents();
		ob_end_clean();
		
		$expected = array(
			'tag' => 'span',
			'attributes' => array('class' => 'shopp_cart_shipping'),
			'content' => '$3.00'
		);
		$this->assertTag($expected,$actual,'',true);
		
		$this->assertValidMarkup($actual);
	}
	
	function test_cart_hastaxes () {
		$this->assertTrue(shopp('cart','has-taxes'));
	}
	
	function test_cart_tax () {
		$Order =& ShoppOrder();
		$Order->Cart->clear();
	
		$Product = new Product(81); $Price = false;
		$Order->Cart->add(1,$Product,$Price,false);
		$Order->Cart->totals();
		
		ob_start();
		shopp('cart','tax');
		$actual = ob_get_contents();
		ob_end_clean();

		$expected = array(
			'tag' => 'span',
			'attributes' => array('class' => 'shopp_cart_tax'),
			'content' => '$9.58'
		);
		$this->assertTag($expected,$actual,'',true);
		
		$this->assertValidMarkup($actual);
	}
	
	function test_cart_total () {
		$Order =& ShoppOrder();
		$Order->Cart->clear();
		// print_r($Order);
	
		$Product = new Product(81); $Price = false;
		$Order->Cart->add(1,$Product,$Price,false);
		$Order->Cart->totals();
		
		// print_r($Order->Cart->totals());
		ob_start();
		shopp('cart','total');
		$actual = ob_get_contents();
		ob_end_clean();

		$this->assertEquals('76.44',$Order->Cart->Totals->total);

		$expected = array(
			'tag' => 'span',
			'attributes' => array('class' => 'shopp_cart_total'),
			'content' => '$76.44'
		);
		$this->assertTag($expected,$actual,'',true);
		
		$this->assertValidMarkup($actual);
	}
	
} // end CartAPITests class

?>