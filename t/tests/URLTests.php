<?php
/**
 * URLTests
 *
 *
 * @author Jonathan Davis, Dave Kress
 * @version 1.0
 * @copyright Ingenesis Limited, 28 November, 2011
 * @package
 **/

/**
 * Initialize
 **/

class URLTests extends ShoppTestCase {

	function setUp () {
		parent::setUp();
		// ShoppOrder()->Shipping->country = 'US';
		
		$Product = shopp_product("Ultimate Matrix Collection", 'name');
		ShoppProduct($Product);
	}

	function test_pretty_cart_url () {
		
		ob_start();
		shopp('cart','url');
		$actual = ob_get_contents();
		ob_end_clean();

		$this->assertEquals('http://shopptest/store/cart/',$actual);
	}
	
	function test_pretty_checkout_url () {
		
		ob_start();
		shopp('checkout','url');
		$actual = ob_get_contents();
		ob_end_clean();

		$this->assertEquals('http://shopptest/store/checkout/',$actual);
	}

	function test_pretty_account_url () {
		
		ob_start();
		shopp('account','url');
		$actual = ob_get_contents();
		ob_end_clean();

		$this->assertEquals('http://shopptest/store/account/',$actual);
	}
	
	function test_pretty_product_url () {
		
		ob_start();
		shopp('product','url');
		$actual = ob_get_contents();
		ob_end_clean();

		$this->assertEquals('http://shopptest/store/ultimate-matrix-collection/',$actual);
	}
	
	function test_pretty_category_url () {
		
		ob_start();
		shopp('category','url');
		$actual = ob_get_contents();
		ob_end_clean();
		
		$this->assertEquals('http://shopptest/store/category/apparel/',$actual);
	}
	
/**
 * Switch to Default DB Structure
 **/

	function test_default_urls () {
		update_option('permalink_structure','');
		flush_rewrite_rules();
	}

	function test_default_cart_url () {
		
		ob_start();
		shopp('cart','url');
		$actual = ob_get_contents();
		ob_end_clean();

		$this->assertEquals('http://shopptest/?shopp_page=cart',$actual);
	}
	
	function test_default_checkout_url () {
		
		ob_start();
		shopp('checkout','url');
		$actual = ob_get_contents();
		ob_end_clean();

		$this->assertEquals('http://shopptest/?shopp_page=checkout',$actual);
	}
	
	function test_default_account_url () {
		
		ob_start();
		shopp('account','url');
		$actual = ob_get_contents();
		ob_end_clean();

		$this->assertEquals('http://shopptest/?shopp_page=account',$actual);
	}
	
	function test_default_product_url () {
		
		ob_start();
		shopp('product','url');
		$actual = ob_get_contents();
		ob_end_clean();

		$this->assertEquals('http://shopptest/?shopp_product=ultimate-matrix-collection',$actual);
	}
	
	
} // end URLTests class

?>