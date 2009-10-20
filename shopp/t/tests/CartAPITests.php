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

	function test_cart_url () {
		ob_start();
		shopp('cart','url');
		$actual = ob_get_contents();
		ob_end_clean();
		$this->assertEquals('http://shopptest/store/cart/',$actual);
	}
	
	function test_cart_hasitems () {
		global $Shopp;
		$Shopp->Cart->clear();
		$this->assertFalse(shopp('cart','hasitems'));

		$Product = new Product(81); $Price = false;
		
		$Shopp->Cart->add(1,$Product,$Price,false);
		$this->assertTrue(shopp('cart','hasitems'));
	}
	
	function test_cart_totalitems () {
		global $Shopp;
		$Shopp->Cart->clear();
		$FirstProduct = new Product(81); $FirstPrice = false;
		$SecondProduct = new Product(82); $SecondPrice = false;
		$Shopp->Cart->totals();
		
		ob_start();
		shopp('cart','totalitems');
		$actual = ob_get_contents();
		ob_end_clean();
		$this->assertEquals(0,$actual);

		$Shopp->Cart->add(1,$FirstProduct,$FirstPrice,false);
		$Shopp->Cart->totals();

		ob_start();
		shopp('cart','totalitems');
		$actual = ob_get_contents();
		ob_end_clean();
		$this->assertEquals(1,$actual);

		$Shopp->Cart->add(1,$SecondProduct,$SecondPrice,false);
		$Shopp->Cart->totals();

		ob_start();
		shopp('cart','totalitems');
		$actual = ob_get_contents();
		ob_end_clean();
		$this->assertEquals(2,$actual);

		$Shopp->Cart->add(3,$FirstProduct,$FirstPrice,false);
		$Shopp->Cart->totals();

		ob_start();
		shopp('cart','totalitems');
		$actual = ob_get_contents();
		ob_end_clean();
		$this->assertEquals(5,$actual);
	}
	
	function test_cart_itemlooping () {
		global $Shopp;
		$Shopp->Cart->clear();
		$FirstProduct = new Product(81); $FirstPrice = false;
		$Shopp->Cart->add(1,$FirstProduct,$FirstPrice,false);
		
		$SecondProduct = new Product(82); $SecondPrice = false;
		$Shopp->Cart->add(1,$SecondProduct,$SecondPrice,false);

		$Shopp->Cart->totals();
		
		ob_start();
		if (shopp('cart','hasitems'))
			while(shopp('cart','items'))
				shopp('cartitem','id');
		$actual = ob_get_contents();
		ob_end_clean();
		$this->assertEquals('01',$actual);
	}
	
	function test_cart_lastitem () {
		global $Shopp;
		$Shopp->Cart->clear();
		$FirstProduct = new Product(81); $FirstPrice = false;
		$Shopp->Cart->add(1,$FirstProduct,$FirstPrice,false);

		$Item = shopp('cart','lastitem','return=true');
		$this->assertEquals(81,$Item->product);
		
		$SecondProduct = new Product(82); $SecondPrice = false;
		$Shopp->Cart->add(1,$SecondProduct,$SecondPrice,false);

		$Item = shopp('cart','lastitem','return=true');
		$this->assertEquals(82,$Item->product);

		$Shopp->Cart->add(1,$FirstProduct,$FirstPrice,false);
		$Item = shopp('cart','lastitem','return=true');
		$this->assertEquals(81,$Item->product);		
	}
	
	function test_cart_haspromos () {
		global $Shopp;
		$Shopp->Cart->clear();
		$Product = new Product(81); $Price = false;
		$Shopp->Cart->add(1,$Product,$Price,false);
		$Shopp->Cart->totals();
		
		$this->assertFalse(shopp('cart','haspromos'));
		$_REQUEST['promocode'] = '2percent';
		$Shopp->Cart->request();
		$Shopp->Cart->totals();
		$this->assertTrue(shopp('cart','haspromos'));		
	}

	function test_cart_totalpromos () {
		global $Shopp;
		$Shopp->Cart->clear();
		$Shopp->Cart->data->PromosApplied = array();
		$Shopp->Cart->data->PromoCodes = array();
		$Product = new Product(81); $Price = false;
		$Shopp->Cart->add(1,$Product,$Price,false);
		$Shopp->Cart->totals();
		
		ob_start();
		shopp('cart','totalpromos');
		$actual = ob_get_contents();
		ob_end_clean();
		
		$this->assertEquals(0,$actual);
		$_REQUEST['promocode'] = '2percent';
		$Shopp->Cart->request();
		$Shopp->Cart->totals();
		ob_start();
		shopp('cart','totalpromos');
		$actual = ob_get_contents();
		ob_end_clean();
		$this->assertEquals(1,$actual);
	}

	function test_cart_promo_name () {
		global $Shopp;
		$Shopp->Cart->clear();
		$Shopp->Cart->data->PromosApplied = array();
		$Shopp->Cart->data->PromoCodes = array();
		$Product = new Product(81); $Price = false;
		$Shopp->Cart->add(1,$Product,$Price,false);
		$_REQUEST['promocode'] = '2percent';
		$Shopp->Cart->request();
		$Shopp->Cart->totals();
		$_REQUEST['promocode'] = '3DollarsOff';
		$Shopp->Cart->request();
		$Shopp->Cart->totals();
		
		ob_start();
		if (shopp('cart','haspromos'))
			while(shopp('cart','promos')) 
				shopp('cart','promo-name');
		$actual = ob_get_contents();
		ob_end_clean();
		
		$this->assertEquals('2% Off$3 Off',$actual);
	}

	function test_cart_promo_discount () {
		global $Shopp;
		$Shopp->Cart->clear();
		$Shopp->Cart->data->PromosApplied = array();
		$Shopp->Cart->data->PromoCodes = array();
		$Product = new Product(81); $Price = false;
		$Shopp->Cart->add(1,$Product,$Price,false);
		$_REQUEST['promocode'] = '2percent';
		$Shopp->Cart->request();
		$Shopp->Cart->totals();
		$_REQUEST['promocode'] = '3DollarsOff';
		$Shopp->Cart->request();
		$Shopp->Cart->totals();
		
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
			'tag' => 'p',
			'attributes' => array('class' => 'error'),
			'content' => 'Error'
		);
		$this->assertTag($expected,$actual,'',true);
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
		global $Shopp;
		$Shopp->Cart->clear();
		$Shopp->Cart->data->PromosApplied = array();
		$Shopp->Cart->data->PromoCodes = array();
		$Product = new Product(81); $Price = false;
		$Shopp->Cart->add(1,$Product,$Price,false);
		$_REQUEST['promocode'] = '2percent';
		$Shopp->Cart->request();
		$Shopp->Cart->totals();
		$this->assertTrue(shopp('cart','hasdiscount'));
	}
	
	function test_cart_discount () {
		global $Shopp;
		$Shopp->Cart->clear();
		$Shopp->Cart->data->PromosApplied = array();
		$Shopp->Cart->data->PromoCodes = array();
		$Product = new Product(81); $Price = false;
		$Shopp->Cart->add(1,$Product,$Price,false);
		$_REQUEST['promocode'] = '2percent';
		$Shopp->Cart->request();
		$Shopp->Cart->totals();

		ob_start();
		shopp('cart','discount');
		$actual = ob_get_contents();
		ob_end_clean();
		$this->assertEquals('$1.28',$actual);
	}
	
	function test_cart_promosavailable () {
		global $Shopp;
		$Shopp->Cart->clear();
		$Shopp->Cart->data->PromosApplied = array();
		$Shopp->Cart->data->PromoCodes = array();
		$Product = new Product(81); $Price = false;
		$Shopp->Cart->add(1,$Product,$Price,false);
		$Shopp->Cart->totals();

		$this->assertTrue(shopp('cart','promos-available'));
		$Shopp->Settings->registry['promo_limit'] = 1;
		$this->assertTrue(shopp('cart','promos-available'));

		$_REQUEST['promocode'] = '2percent';
		$Shopp->Cart->request();
		$Shopp->Cart->totals();
		
		$this->assertFalse(shopp('cart','promos-available'));

	}
	
	function test_cart_promocode () {
		global $Shopp;
		$Shopp->Cart->clear();
		$Shopp->Cart->data->PromosApplied = array();
		$Shopp->Cart->data->PromoCodes = array();
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
		global $Shopp;
		$Shopp->Cart->clear();
	
		$Product = new Product(81); $Price = false;
		$Shopp->Cart->add(1,$Product,$Price,false);
		$Shopp->Cart->totals();
		
		$this->assertFalse(shopp('cart','has-shipping-methods'));
		
		$Shopp->Cart->data->ShipCosts['Test'] = array (
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
		global $Shopp;
		$Shopp->Cart->clear();
	
		$Product = new Product(81); $Price = false;
		$Shopp->Cart->add(1,$Product,$Price,false);
		$Shopp->Cart->totals();
		$this->assertTrue(shopp('cart','needs-shipped'));
	}
	
	function test_cart_hasshipcosts () {
		global $Shopp;
		$Shopp->Cart->clear();
	
		$Product = new Product(81); $Price = false;
		$Shopp->Cart->add(1,$Product,$Price,false);
		$Shopp->Cart->totals();
		$this->assertTrue(shopp('cart','has-ship-costs'));
	}
	
	function test_cart_needsshippingestimates () {
		global $Shopp;
		$Shopp->Cart->clear();
	
		$Product = new Product(81); $Price = false;
		$Shopp->Cart->add(1,$Product,$Price,false);
		$Shopp->Cart->totals();
		$this->assertTrue(shopp('cart','needs-shipping-estimates'));
	}
	
	function test_cart_shippingestimates () {
		global $Shopp;
		$Shopp->Cart->clear();
	
		$Product = new Product(81); $Price = false;
		$Shopp->Cart->add(1,$Product,$Price,false);
		$Shopp->Cart->totals();
		
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
		global $Shopp;
		$Shopp->Cart->clear();
	
		$Product = new Product(81); $Price = false;
		$Shopp->Cart->add(1,$Product,$Price,false);
		$Shopp->Cart->totals();
		
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
		global $Shopp;
		$Shopp->Cart->clear();
	
		$Product = new Product(81); $Price = false;
		$Shopp->Cart->add(1,$Product,$Price,false);
		$Shopp->Cart->totals();
		
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
		global $Shopp;
		$Shopp->Cart->clear();
	
		$Product = new Product(81); $Price = false;
		$Shopp->Cart->add(1,$Product,$Price,false);
		$Shopp->Cart->totals();
		
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
		global $Shopp;
		$Shopp->Cart->clear();
	
		$Product = new Product(81); $Price = false;
		$Shopp->Cart->add(1,$Product,$Price,false);
		$Shopp->Cart->totals();
		
		ob_start();
		shopp('cart','total');
		$actual = ob_get_contents();
		ob_end_clean();

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