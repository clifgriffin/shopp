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
	}
	
	function test_product_id () {
		global $Shopp;
		$Shopp->Product = new Product(4);

		ob_start();
		shopp('product','id');
		$output = ob_get_contents();
		ob_end_clean();
		$this->assertEquals("4",$output);
	}

	function test_product_name () {
		global $Shopp;
		$Shopp->Product = new Product(4);

		ob_start();
		shopp('product','name');
		$output = ob_get_contents();
		ob_end_clean();
		$this->assertEquals("Fallout 3: Game of the Year",$output);
	}
	
	function test_product_slug () {
		global $Shopp;
		$Shopp->Product = new Product(4);

		ob_start();
		shopp('product','slug');
		$output = ob_get_contents();
		ob_end_clean();
		$this->assertEquals("fallout-3-game-of-the-year",$output);
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
		
		$Shopp->Product = new Product(4);
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



} // end ProductAPITests class

?>