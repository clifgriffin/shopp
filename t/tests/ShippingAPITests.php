<?php
/**
 * ShippingAPITests
 * 
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 20 October, 2009
 * @package 
 **/

/**
 * Initialize
 **/

class ShippingAPITests extends ShoppTestCase {

	function test_shipping_hasestimates () {
		global $Shopp;
		$Shopp->Order->Cart->clear();
	
		$Product = new Product(81); $Price = false;
		$Shopp->Order->Cart->add(1,$Product,$Price,false);
		$Shopp->Order->Cart->totals();
		$this->assertTrue(shopp('shipping','hasestimates'));
		
	}

	function test_shipping_methodname () {
		global $Shopp;
		$Shopp->Order->Cart->clear();
	
		$Product = new Product(81); $Price = false;
		$Shopp->Order->Cart->add(1,$Product,$Price,false);
		$Shopp->Order->Cart->totals();
		
		ob_start();
		if (shopp('shipping','hasestimates'))
			while(shopp('shipping','methods'))
				shopp('shipping','method-name');
		$actual = ob_get_contents();
		ob_end_clean();
		$this->assertEquals('Standard',$actual);
	}

	function test_shipping_methodcost () {
		global $Shopp;
		$Shopp->Order->Cart->clear();
	
		$Product = new Product(81); $Price = false;
		$Shopp->Order->Cart->add(1,$Product,$Price,false);
		$Shopp->Order->Cart->totals();
		
		ob_start();
		if (shopp('shipping','hasestimates'))
			while(shopp('shipping','methods'))
				shopp('shipping','method-cost');
		$actual = ob_get_contents();
		ob_end_clean();
		$this->assertEquals('$3.00',$actual);
	}

	function test_shipping_methodselector () {
		global $Shopp;
		$Shopp->Order->Cart->clear();
	
		$Product = new Product(81); $Price = false;
		$Shopp->Order->Cart->add(1,$Product,$Price,false);
		$Shopp->Order->Cart->totals();
		
		ob_start();
		if (shopp('shipping','hasestimates')) shopp('shipping','methods');
		shopp('shipping','method-selector');
		$actual = ob_get_contents();
		ob_end_clean();
		
		$expected = array(
			'tag' => 'input',
			'attributes' => array('type' => 'radio','name' => 'shipmethod','value' => 'Standard','class' => 'shipmethod')
		);
		$this->assertTag($expected,$actual,'',true);
	}
	
	function test_shipping_methoddelivery () {
		global $Shopp;
		$Shopp->Order->Cart->clear();
	
		$Product = new Product(81); $Price = false;
		$Shopp->Order->Cart->add(1,$Product,$Price,false);
		$Shopp->Order->Cart->totals();
		
		ob_start();
		if (shopp('shipping','hasestimates')) shopp('shipping','methods');
		shopp('shipping','method-delivery');
		$actual = ob_get_contents();
		ob_end_clean();
		$this->assertTrue(!empty($actual));
	}
	

} // end ShippingAPITests class

?>