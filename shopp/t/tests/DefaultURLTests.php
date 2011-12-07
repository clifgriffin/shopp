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

class DefaultURLTests extends ShoppTestCase {

	function setUp () {
		parent::setUp();
		// ShoppOrder()->Shipping->country = 'US';

		// Pre-set product for repeated use
		// $Product = shopp_product("Ultimate Matrix Collection", 'name');
		// ShoppProduct($Product);

		// Load category for repeated use
		// shopp('catalog','category','slug=apparel&load=true');

	}

/**
 * Switch to Default DB Structure
 **/

	function _default_urls () {
		$ps = get_option('permalink_structure');
		update_option('permalink_structure','');
		flush_rewrite_rules();
		return $ps;
	}

	function _pretty_urls ( $permalink_structure ) {
		update_option('permalink_structure', $permalink_structure);
		flush_rewrite_rules();
	}

	function test_cart_url () {
		$orig = $this->_default_urls();
		ob_start();
		shopp('cart','url');
		$actual = ob_get_contents();
		ob_end_clean();

		$this->_pretty_urls( $orig ); // restore before assertions
		$this->assertEquals('http://shopptest/?shopp_page=cart',$actual);
	}

	function test_checkout_url () {
		$orig = $this->_default_urls();
		ob_start();
		shopp('checkout','url');
		$actual = ob_get_contents();
		ob_end_clean();

		$this->_pretty_urls( $orig ); // restore before assertions
		$this->assertEquals('http://shopptest/?shopp_page=checkout',$actual);
	}

	function test_account_url () {
		$orig = $this->_default_urls();
		ob_start();
		shopp('customer','accounturl');
		$actual = ob_get_contents();
		ob_end_clean();

		$this->_pretty_urls( $orig ); // restore before assertions
		$this->assertEquals('http://shopptest/?shopp_page=account',$actual);
	}

	function test_product_url () {
		
		$Product = shopp_product("Ultimate Matrix Collection", 'name');
		ShoppProduct($Product);
		
		$orig = $this->_default_urls();
		ob_start();
		shopp('product','url');
		$actual = ob_get_contents();
		ob_end_clean();
		
		$this->_pretty_urls( $orig ); // restore before assertions
		$this->assertEquals('http://shopptest/?shopp_product=ultimate-matrix-collection',$actual);
	}

	function test_category_url () {
		$orig = $this->_default_urls();
		shopp('catalog','category','slug=apparel&load=true');
		ob_start();
		shopp('category','url');
		$actual = ob_get_contents();
		ob_end_clean();

		$this->_pretty_urls( $orig ); // restore before assertions
		$this->assertEquals('http://shopptest/?shopp_category=apparel',$actual);
	}

	function test_catalog_url () {
		$orig = $this->_default_urls();
		ob_start();
		shopp('catalog','url');
		$actual = ob_get_contents();
		ob_end_clean();

		$this->_pretty_urls( $orig ); // restore before assertions
		$this->assertEquals('http://shopptest/?shopp_page=store',$actual);
	}

	function test_catalogproducts_url () {

	    $orig = $this->_default_urls();
		shopp('catalog','catalog-products','load=true');
		ob_start();
		shopp('collection','url');
		$actual = ob_get_contents();
		ob_end_clean();

		$this->_pretty_urls( $orig ); // restore before assertions
		$this->assertEquals('http://shopptest/?shopp_collection=catalog',$actual);
	}

	function test_newproducts_url () {

	    $orig = $this->_default_urls();
		shopp('catalog','new-products','load=true');
		ob_start();
		shopp('collection','url');
		$actual = ob_get_contents();
		ob_end_clean();

		$this->_pretty_urls( $orig ); // restore before assertions
		$this->assertEquals('http://shopptest/?shopp_collection=new',$actual);
	}

	function test_featuredproducts_url () {

	    $orig = $this->_default_urls();
		shopp('catalog','featured-products','load=true');
		ob_start();
		shopp('collection','url');
		$actual = ob_get_contents();
		ob_end_clean();

		$this->_pretty_urls( $orig ); // restore before assertions
		$this->assertEquals('http://shopptest/?shopp_collection=featured',$actual);
	}

	function test_onsaleproducts_url () {

	    $orig = $this->_default_urls();
		shopp('catalog','onsale-products','load=true');
		ob_start();
		shopp('collection','url');
		$actual = ob_get_contents();
		ob_end_clean();

		$this->_pretty_urls( $orig ); // restore before assertions
		$this->assertEquals('http://shopptest/?shopp_collection=onsale',$actual);
	}

	function test_bestsellerproducts_url () {

	    $orig = $this->_default_urls();
		shopp('catalog','bestseller-products','load=true');
		ob_start();
		shopp('collection','url');
		$actual = ob_get_contents();
		ob_end_clean();

		$this->_pretty_urls( $orig ); // restore before assertions
		$this->assertEquals('http://shopptest/?shopp_collection=bestsellers',$actual);
	}

	function test_alsoboughtproducts_url () {

		$orig = $this->_default_urls();
		shopp('catalog','alsobought-products','load=true');
		ob_start();
		shopp('collection','url');
		$actual = ob_get_contents();
		ob_end_clean();

		$this->_pretty_urls( $orig ); // restore before assertions
		$this->assertEquals('http://shopptest/?shopp_collection=alsobought',$actual);
	}

	function test_randomproducts_url () {

	    $orig = $this->_default_urls();
		shopp('catalog','random-products','load=true');
		ob_start();
		shopp('collection','url');
		$actual = ob_get_contents();
		ob_end_clean();

		$this->_pretty_urls( $orig ); // restore before assertions
		$this->assertEquals('http://shopptest/?shopp_collection=random',$actual);
	}
	
	function test_relatedproducts_url () {

	    $orig = $this->_default_urls();
		shopp('catalog','related-products','load=true');
		ob_start();
		shopp('collection','url');
		$actual = ob_get_contents();
		ob_end_clean();

		$this->assertEquals('http://shopptest/?shopp_collection=related',$actual);
	}
	
	function test_tagproducts_url () {

	    $orig = $this->_default_urls();
		shopp('catalog','tag-products','tag=action&load=true');
		ob_start();
		shopp('collection','url');
		$actual = ob_get_contents();
		ob_end_clean();

		$this->assertEquals('http://shopptest/?shopp_collection=tag',$actual);
	}
	
	function test_searchproducts_url () {

	    
		$orig = $this->_default_urls();
		shopp('catalog','search-products','search=Star+Wars&load=true');
		ob_start();
		shopp('collection','url');
		$actual = ob_get_contents();
		ob_end_clean();

		$this->assertEquals('http://shopptest/?shopp_collection=search-results/?s=Star+Wars&s_cs=1',$actual);
	}
	
	function test_category_paginated_url () {

	    $orig = $this->_default_urls();
		shopp('catalog','category','slug=apparel&load=true');
		shopp('collection','has-products');
		
		ob_start();
		shopp('collection','pagination');
		$actual = ob_get_contents();
		ob_end_clean();

		$markup = array(
			'tag' => 'a',
			'attributes' => array(
				'href' => 'http://shopptest/store/category/apparel/page/2/',
			),
			'content' => '2'
		);
		$this->assertTag($markup,$output,'',true);
		$this->assertValidMarkup($output);
	}
	
	function test_category_feed_url () {

		$orig = $this->_default_urls();
		shopp('catalog','category','slug=apparel&load=true');
		ob_start();
		shopp('category','feed-url');
		$actual = ob_get_contents();
		ob_end_clean();

		$this->assertEquals('http://shopptest/?shopp_category=apparel&src=category_rss',$actual);
	}
	
	function tearDown() {
		
		parent::tearDown();
		// Set back to original (Pretty)
	}

} // end DefaultURLTests class

?>