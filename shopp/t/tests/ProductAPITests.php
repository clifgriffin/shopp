<?php
/**
 * ProductAPITests
 * 
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 14 October, 2009
 * @package 
 **/

/**
 * Initialize
 **/
require_once 'PHPUnit/Framework.php';

class ProductAPITests extends ShoppTestCase {

	function ProductAPITests () {
		global $Shopp;
		$Shopp->Product = new Product(4);
	}
	
	function test_product_id () {
		ob_start();
		shopp('product','id');
		$output = ob_get_contents();
		ob_end_clean();
		$this->assertEquals("4",$output);
	}

	function test_product_name () {
		ob_start();
		shopp('product','name');
		$output = ob_get_contents();
		ob_end_clean();
		$this->assertEquals("Fallout 3: Game of the Year",$output);
	}
	
	function test_product_slug () {
		ob_start();
		shopp('product','slug');
		$output = ob_get_contents();
		ob_end_clean();
		$this->assertEquals("fallout-3-game-of-the-year",$output);
	}

	function test_product_url () {
		global $Shopp;
		ob_start();
		shopp('product','url');
		$output = ob_get_contents();
		ob_end_clean();
		if (SHOPP_PERMALINKS) $this->assertEquals($Shopp->shopuri.'fallout-3-game-of-the-year/',$output);
	}
	
	function test_product_found () {
		global $Shopp;
		$this->assertTrue(shopp('product','found'));
		$original = $Shopp->Product;
		$Shopp->Product = new Product(-1);
		$this->assertFalse(shopp('product','found'));
		$Shopp->Product = $original;
	}
	

	function test_product_price () {
		global $Shopp;
		$Shopp->Settings->registry['base_operations'] = array(
			'name' => 'USA',
		    'currency' => array(
		            'code' => 'USD',
		            'format' => array(
		                    'cpos' => 1,
		                    'currency' => '$',
		                    'precision' => 2,
		                    'decimals' => '.',
		                    'thousands' => ',',
		                ),
		        ),
		    'units' => 'imperial',
		    'region' => 0,
		    'country' => 'US',
		    'zone' => 'OH',
		    'vat' => false,
		);
		$Shopp->Settings->registry['taxrates'] = array(
			0 => array('rate' => 15,'country'=>'*')
		);
		
		ob_start();
		shopp('product','price');
		$output = ob_get_contents();
		ob_end_clean();
		$this->assertEquals("$59.82",$output);
		
		$Shopp->Settings->registry['base_operations']['vat'] = true;
		ob_start();
		shopp('product','price');
		$output = ob_get_contents();
		ob_end_clean();
		$this->assertEquals("$68.79",$output);
		
		ob_start();
		shopp('product','price','taxes=off');
		$output = ob_get_contents();
		ob_end_clean();
		$this->assertEquals("$59.82",$output);

	}	

	function test_product_thumbnail () {
		ob_start();
		shopp('product','thumbnail');
		$output = ob_get_contents();
		ob_end_clean();
		$this->assertXmlStringEqualsXmlString('<img src="http://shopptest/store/images/39" alt="Fallout 3: Game of the Year" width="96" height="96"  />',$output);
		$this->assertValidMarkup($output);
	}
	
	function test_product_gallery () {
		ob_start();
		shopp('product','gallery');
		$output = ob_get_contents();
		ob_end_clean();
		$this->assertValidMarkup($output);		
	}
	
	function test_product_quantity () {
		ob_start();
		shopp('product','quantity','input=text');
		$output = ob_get_contents();
		ob_end_clean();
		$this->assertValidMarkup($output);

		ob_start();
		shopp('product','quantity','input=menu&options=1-3,5,10-15');
		$output = trim(ob_get_contents());
		ob_end_clean();
		$control = '<select name="products[4][quantity]" id="quantity-4"><option selected="selected" value="1">1</option><option value="2">2</option><option value="3">3</option><option value="5">5</option><option value="10">10</option><option value="11">11</option><option value="12">12</option><option value="13">13</option><option value="14">14</option><option value="15">15</option></select>';
		$this->assertEquals($control,$output);
		$this->assertValidMarkup($output);
	}
	
	function test_product_addtocart () {
		ob_start();
		shopp('product','addtocart');
		$output = ob_get_contents();
		ob_end_clean();
		$this->assertValidMarkup($output);
	}


} // end ProductAPITests class

?>