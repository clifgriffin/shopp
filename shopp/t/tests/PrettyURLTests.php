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

class PrettyURLTests extends ShoppTestCase {

	function setUp () {
		parent::setUp();
		// ShoppOrder()->Shipping->country = 'US';

	}

	function test_cart_url () {

		ob_start();
		shopp('cart','url');
		$actual = ob_get_contents();
		ob_end_clean();

		$this->assertEquals('http://shopptest/store/cart/',$actual);
	}

	function test_checkout_url () {

		ob_start();
		shopp('checkout','url');
		$actual = ob_get_contents();
		ob_end_clean();

		$this->assertEquals('http://shopptest/store/checkout/',$actual);
	}

	function test_account_url () {

		ob_start();
		shopp('customer','accounturl');
		$actual = ob_get_contents();
		ob_end_clean();

		$this->assertEquals('http://shopptest/store/account/',$actual);
	}

	function test_product_url () {
		
		$Product = shopp_product("Ultimate Matrix Collection", 'name');
		ShoppProduct($Product);
		
		ob_start();
		shopp('product','url');
		$actual = ob_get_contents();
		ob_end_clean();

		$this->assertEquals('http://shopptest/store/ultimate-matrix-collection/',$actual);
	}

	function test_category_url () {

		shopp('catalog','category','slug=apparel&load=true');
		ob_start();
		shopp('category','url');
		$actual = ob_get_contents();
		ob_end_clean();

		$this->assertEquals('http://shopptest/store/category/apparel/',$actual);
	}

	function test_catalog_url () {

		ob_start();
		shopp('catalog','url');
		$actual = ob_get_contents();
		ob_end_clean();

		$this->assertEquals('http://shopptest/store/',$actual);
	}

	function test_catalogproducts_url () {

	    shopp('catalog','catalog-products','load=true');
		ob_start();
		shopp('collection','url');
		$actual = ob_get_contents();
		ob_end_clean();

		$this->assertEquals('http://shopptest/store/collection/catalog/',$actual);
	}

	function test_newproducts_url () {

	    shopp('catalog','new-products','load=true');
		ob_start();
		shopp('collection','url');
		$actual = ob_get_contents();
		ob_end_clean();

		$this->assertEquals('http://shopptest/store/collection/new/',$actual);
	}

	function test_featuredproducts_url () {

	    shopp('catalog','featured-products','load=true');
		ob_start();
		shopp('collection','url');
		$actual = ob_get_contents();
		ob_end_clean();

		$this->assertEquals('http://shopptest/store/collection/featured/',$actual);
	}

	function test_onsaleproducts_url () {

	    shopp('catalog','onsale-products','load=true');
		ob_start();
		shopp('collection','url');
		$actual = ob_get_contents();
		ob_end_clean();

		$this->assertEquals('http://shopptest/store/collection/onsale/',$actual);
	}

	function test_bestsellerproducts_url () {

	    shopp('catalog','bestseller-products','load=true');
		ob_start();
		shopp('collection','url');
		$actual = ob_get_contents();
		ob_end_clean();

		$this->assertEquals('http://shopptest/store/collection/bestsellers/',$actual);
	}

	function test_alsoboughtproducts_url () {

	    shopp('catalog','alsobought-products','load=true');
		ob_start();
		shopp('collection','url');
		$actual = ob_get_contents();
		ob_end_clean();

		$this->assertEquals('http://shopptest/store/collection/alsobought/',$actual);
	}

	function test_randomproducts_url () {

	    shopp('catalog','random-products','load=true');
		ob_start();
		shopp('collection','url');
		$actual = ob_get_contents();
		ob_end_clean();

		$this->assertEquals('http://shopptest/store/collection/random/',$actual);
	}

} // end PrettyURLTests class

?>