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
		ShoppOrder()->Shipping->country = 'US';
	}

	function test_cart_url () {
		ob_start();
		shopp('cart','url');
		$actual = ob_get_contents();
		ob_end_clean();
		$this->assertEquals('http://shopptest/store/cart/',$actual);
	}

	function test_cart_hasitems () {
		shopp_empty_cart();
		$this->assertFalse(shopp('cart','hasitems'));

		$Product = shopp_product('knowing','slug');
		shopp_add_cart_product($Product->id,1);

		$this->assertTrue(shopp('cart','hasitems'));
	}

	function test_cart_totalitems () {
		$FirstProduct =  shopp_product('eagle-eye','slug');
		$SecondProduct = shopp_product('knowing','slug');

		shopp_empty_cart();

		ob_start();
		shopp('cart','totalitems');
		$actual = ob_get_contents();
		ob_end_clean();
		$this->assertEquals(0,$actual);

		shopp_add_cart_product($FirstProduct->id,1);

		ob_start();
		shopp('cart','totalitems');
		$actual = ob_get_contents();
		ob_end_clean();
		$this->assertEquals(1,$actual);

		shopp_add_cart_product($SecondProduct->id,1);

		ob_start();
		shopp('cart','totalitems');
		$actual = ob_get_contents();
		ob_end_clean();
		$this->assertEquals(2,$actual);

		shopp_add_cart_product($FirstProduct->id,3);

		ob_start();
		shopp('cart','totalitems');
		$actual = ob_get_contents();
		ob_end_clean();
		$this->assertEquals(2,$actual);
	}


	function test_cart_totalquantity () {
		$FirstProduct =  shopp_product('eagle-eye','slug');
		$SecondProduct = shopp_product('knowing','slug');

		shopp_empty_cart();

		ob_start();
		shopp('cart','totalquantity');
		$actual = ob_get_contents();
		ob_end_clean();
		$this->assertEquals(0,$actual);

		shopp_add_cart_product($FirstProduct->id,1);

		ob_start();
		shopp('cart','totalquantity');
		$actual = ob_get_contents();
		ob_end_clean();
		$this->assertEquals(1,$actual);

		shopp_add_cart_product($SecondProduct->id,1);

		ob_start();
		shopp('cart','totalquantity');
		$actual = ob_get_contents();
		ob_end_clean();
		$this->assertEquals(2,$actual);

		shopp_add_cart_product($FirstProduct->id,3);

		ob_start();
		shopp('cart','totalquantity');
		$actual = ob_get_contents();
		ob_end_clean();
		$this->assertEquals(5,$actual);
	}


	function test_cart_itemlooping () {
		shopp_empty_cart();

		$FirstProduct = shopp_product('eagle-eye','slug');
		shopp_add_cart_product($FirstProduct->id,1);

		$SecondProduct = shopp_product('knowing','slug');
		shopp_add_cart_product($SecondProduct->id,1);

		ob_start();
		if (shopp('cart','hasitems'))
			while(shopp('cart','items'))
				shopp('cartitem','id');
		$actual = ob_get_contents();
		ob_end_clean();
		$this->assertEquals('01',$actual);
	}

	function test_cart_lastitem () {
		shopp_empty_cart();
		$FirstProduct = shopp_product('eagle-eye','slug');

		shopp_add_cart_product($FirstProduct->id,1);
		$Item = shopp('cart','lastitem','return=true');
		$this->assertEquals($FirstProduct->name,$Item->name);

		$SecondProduct = shopp_product('knowing','slug');
		shopp_add_cart_product($SecondProduct->id,1);
		$Item = shopp('cart','lastitem','return=true');
		$this->assertEquals($SecondProduct->name,$Item->name);

		shopp_add_cart_product($FirstProduct->id,1);
		$Item = shopp('cart','lastitem','return=true');
		$this->assertEquals($FirstProduct->name,$Item->name);
	}

	function test_cart_haspromos () {
		shopp_empty_cart();

		$Product = shopp_product('code-is-poetry-t-shirt','slug');
		shopp_add_cart_product($Product->id,1);

		$this->assertFalse(shopp('cart','haspromos'));
		$_REQUEST['promocode'] = '2percent';
		ShoppOrder()->Cart->request();
		ShoppOrder()->Cart->totals();
		$this->assertTrue(shopp('cart','haspromos'));
	}

	function test_cart_totalpromos () {
		shopp_set_setting('promo_limit',0);

		shopp_empty_cart();

		$Product = shopp_product('code-is-poetry-t-shirt','slug');
		shopp_add_cart_product($Product->id,1);

		ob_start();
		shopp('cart','totalpromos');
		$actual = ob_get_contents();
		ob_end_clean();
		$this->assertEquals(0,$actual);

		shopp_add_cart_promocode('2percent');
		ob_start();
		shopp('cart','totalpromos');
		$actual = ob_get_contents();
		ob_end_clean();
		$this->assertEquals(1,$actual);
	}

	function test_cart_promo_name () {
		shopp_set_setting('promo_limit',0);
		shopp_empty_cart();

		$Product = shopp_product('eagle-eye','slug');
		shopp_add_cart_product($Product->id,1);
		shopp_add_cart_promocode('2Percent');
		shopp_add_cart_promocode('3DollarsOff');

		ob_start();
		if (shopp('cart','haspromos'))
			while(shopp('cart','promos'))
				shopp('cart','promo-name');
		$actual = ob_get_contents();
		ob_end_clean();

		$this->assertEquals('2% Off$3 Off',$actual);
	}

	function test_cart_promo_discount () {
		shopp_set_setting('promo_limit',0);
		shopp_empty_cart();

		$Product = shopp_product('eagle-eye','slug');
		shopp_add_cart_product($Product->id,1);
		shopp_add_cart_promocode('2Percent');
		shopp_add_cart_promocode('3DollarsOff');

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
		shopp_empty_cart();
		$Product = shopp_product('code-is-poetry-t-shirt','slug');
		shopp_add_cart_product($Product->id,1);

		$_REQUEST['promocode'] = '2percent';
		ShoppOrder()->Cart->request();
		ShoppOrder()->Cart->totals();

		$this->assertTrue(shopp('cart','hasdiscount'));
	}

	function test_cart_discount () {
		shopp_set_setting('promo_limit', 0);
		shopp_empty_cart();
		$Product = shopp_product('code-is-poetry-t-shirt','slug');
		shopp_add_cart_product($Product->id,1);

		shopp_add_cart_promocode('2percent');

		ob_start();
		shopp('cart','discount');
		$actual = ob_get_contents();
		ob_end_clean();

		$expected = array(
			'tag' => 'span',
			'attributes' => array('class' => 'shopp-cart cart-discount'),
			'content' => '$0.18'
		);
		$this->assertTag($expected,$actual,$actual,true);
		$this->assertValidMarkup($actual);

		shopp_add_cart_promocode('3DollarsOff');

		ob_start();
		shopp('cart','discount');
		$actual = ob_get_contents();
		ob_end_clean();

		$expected = array(
			'tag' => 'span',
			'attributes' => array('class' => 'shopp-cart cart-discount'),
			'content' => '$3.18'
		);
		$this->assertTag($expected,$actual,$actual,true);
		$this->assertValidMarkup($actual);

	}

	function test_cart_promosavailable () {
		shopp_set_setting('promo_limit', 0);

		shopp_empty_cart();
		$Product = shopp_product('eagle-eye','slug');

		$this->assertTrue(shopp('cart','promos-available'));
		shopp_set_setting('promo_limit', 1);
		$this->assertTrue(shopp('cart','promos-available'));

		shopp_add_cart_promocode('2percent');

		$this->assertFalse(shopp('cart','promos-available'));

	}

	function test_cart_promocode () {
		shopp_empty_cart();

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
		ShoppOrder()->Cart->clear();

		$Product = shopp_product('code-is-poetry-t-shirt','slug'); $Price = false;
		ShoppOrder()->Cart->add(1,$Product,$Price,false);
		ShoppOrder()->Cart->totals();

		ShoppOrder()->Cart->shipping = array();
		$this->assertFalse(shopp('cart','has-shipping-methods'));
		ShoppOrder()->Cart->shipping['Test'] = array (
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
		$Product = shopp_product('code-is-poetry-t-shirt','slug');
		shopp_empty_cart();

		shopp_add_cart_product($Product->id,1);

		$this->assertTrue(shopp('cart','needs-shipped'));
	}

	function test_cart_hasshipcosts () {
		$Product = shopp_product('code-is-poetry-t-shirt','slug');
		shopp_empty_cart();
		shopp_add_cart_product($Product->id,1);

		$this->assertTrue(shopp('cart','has-ship-costs'));
	}

	function test_cart_needsshippingestimates () {
		$Product = shopp_product('code-is-poetry-t-shirt','slug');

		shopp_empty_cart();
		shopp_add_cart_product($Product->id,1);

		$this->assertTrue(shopp('cart','needs-shipping-estimates'));
	}

	function test_cart_shippingestimates () {

		shopp_empty_cart();
		$Product = shopp_product('code-is-poetry-t-shirt','slug');
		shopp_add_cart_product($Product->id,1);
		shopp_set_setting('target_markets', array('US' => 'USA','UK' => 'United Kingdom'));

		ob_start();
		shopp('cart','shipping-estimates');
		$actual = ob_get_contents();
		ob_end_clean();

		$expected = array(
			'tag' => 'select',
			'attributes' => array('name' => 'shipping[country]','id' => 'shipping-country')
		);
		$this->assertTag($expected,$actual,$actual,true);

		shopp_set_setting('target_markets', array('US' => 'USA'));
		ob_start();
		shopp('cart','shipping-estimates');
		$actual = ob_get_contents();
		ob_end_clean();

		$expected = array(
			'tag' => 'input',
			'attributes' => array('type' => 'hidden','name' => 'shipping[country]','id' => 'shipping-country')
		);
		$this->assertTag($expected,$actual,$actual,true);

		ob_start();
		shopp('cart','shipping-estimates','postcode=on');
		$actual = ob_get_contents();
		ob_end_clean();

		$expected = array(
			'tag' => 'input',
			'attributes' => array('name' => 'shipping[postcode]','id' => 'shipping-postcode')
		);
		$this->assertTag($expected,$actual,$actual,true);

		$this->assertValidMarkup($actual);

	}

	function test_cart_subtotal () {
		shopp_empty_cart();
		$Product = shopp_product('code-is-poetry-t-shirt','slug');
		shopp_add_cart_product($Product->id,1);

		ob_start();
		shopp('cart','subtotal');
		$actual = ob_get_contents();
		ob_end_clean();

		$expected = array(
			'tag' => 'span',
			'attributes' => array('class' => 'shopp-cart cart-subtotal'),
			'content' => '$9.01'
		);
		$this->assertTag($expected,$actual,$actual,true);
		$this->assertValidMarkup($actual);
	}

	function test_cart_shipping () {
		shopp_empty_cart();
		$Product = shopp_product('code-is-poetry-t-shirt','slug');
		shopp_add_cart_product($Product->id,1);

		ob_start();
		shopp('cart','shipping');
		$actual = ob_get_contents();
		ob_end_clean();

		$expected = array(
			'tag' => 'span',
			'attributes' => array('class' => 'shopp-cart cart-shipping'),
			'content' => '$3.00'
		);
		$this->assertTag($expected,$actual,$actual,true);

		$this->assertValidMarkup($actual);
	}

	function test_cart_hastaxes () {
		$this->assertTrue(shopp('cart','has-taxes'));
	}

	function test_cart_tax () {
		$Product = shopp_product('code-is-poetry-t-shirt','slug');
		shopp_empty_cart();
		shopp_add_cart_product($Product->id,1);

		ob_start();
		shopp('cart','tax');
		$actual = ob_get_contents();
		ob_end_clean();

		$expected = array(
			'tag' => 'span',
			'attributes' => array('class' => 'shopp-cart cart-tax'),
			'content' => '$1.20'
		);

		$this->assertTag($expected,$actual,$actual,true);
		$this->assertValidMarkup($actual);
	}

	function test_cart_total () {
		shopp_empty_cart();

		$Product = shopp_product('code-is-poetry-t-shirt','slug');
		shopp_add_cart_product($Product->id,1);

		ob_start();
		shopp('cart','total','number=1');
		$actual = ob_get_contents();
		ob_end_clean();

		$this->assertEquals('13.21',$actual);

		ob_start();
		shopp('cart','total');
		$actual = ob_get_contents();
		ob_end_clean();

		$expected = array(
			'tag' => 'span',
			'attributes' => array('class' => 'shopp-cart cart-total'),
			'content' => '$13.21'
		);
		$this->assertTag($expected,$actual,$actual,true);

		$this->assertValidMarkup($actual);
	}

} // end CartAPITests class

?>