<?php
/**
 * CartTest
 * 
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited,  6 October, 2009
 * @package shopp
 **/

/**
 * Initialize
 **/

class CartTest extends ShoppTestCase {

	public function test_cart_init () {
		global $Shopp;
		$this->assertEquals(true, isset($Shopp->Cart) );
	}

	public function test_cart_totals_init () {
		global $Shopp;
		$this->assertEquals(true, isset($Shopp->Cart->data->Total) );
	}

} // end CartTest class

?>