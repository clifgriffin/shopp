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
		$Shopp->Product = new Product(81);
	}
	
	function test_product_id () {
		ob_start();
		shopp('product','id');
		$output = ob_get_contents();
		ob_end_clean();
		$this->assertEquals("81",$output);
	}

	function test_product_name () {
		ob_start();
		shopp('product','name');
		$output = ob_get_contents();
		ob_end_clean();
		$this->assertEquals("Ultimate Matrix Collection",$output);
	}
	
	function test_product_slug () {
		ob_start();
		shopp('product','slug');
		$output = ob_get_contents();
		ob_end_clean();
		$this->assertEquals("ultimate-matrix-collection",$output);
	}

	function test_product_url () {
		global $Shopp;
		ob_start();
		shopp('product','url');
		$output = ob_get_contents();
		ob_end_clean();
		if (SHOPP_PERMALINKS) $this->assertEquals($Shopp->shopuri.'ultimate-matrix-collection/',$output);
	}
	
	function test_product_description () {
		ob_start();
		shopp('product','description');
		$output = ob_get_contents();
		ob_end_clean();
		$this->assertEquals("a2b247e75ba9fed7afead41f74de4691",md5($output));
	}

	function test_product_summary () {
		ob_start();
		shopp('product','summary');
		$output = ob_get_contents();
		ob_end_clean();
		$this->assertEquals("3b31a462a3ec3b704934bdc5ae960af6",md5($output));
	}
	
	function test_product_found () {
		global $Shopp;
		$this->assertTrue(shopp('product','found'));
		$original = $Shopp->Product;
		$Shopp->Product = new Product(-1);
		$this->assertFalse(shopp('product','found'));
		$Shopp->Product = $original;
	}

	function test_product_isfeatured () {
		$this->assertTrue(shopp('product','isfeatured'));
		$this->assertTrue(shopp('product','is-featured'));
	}

	function test_product_price () {
		ob_start();
		shopp('product','price');
		$output = ob_get_contents();
		ob_end_clean();
		$this->assertEquals("$34.86 &mdash; $129.95",$output);
	}
	
	function test_product_onsale () {
		$this->assertTrue(shopp('product','onsale'));
	}

	function test_product_saleprice () {
		ob_start();
		shopp('product','saleprice');
		$output = ob_get_contents();
		ob_end_clean();
		$this->assertEquals("$15.06 &mdash; $63.86",$output);
	}
	
	function test_product_prices_withvat () {
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

		$Shopp->Settings->registry['base_operations']['vat'] = true;
		ob_start();
		shopp('product','price');
		$output = ob_get_contents();
		ob_end_clean();
		$this->assertEquals("$40.09 &mdash; $149.44",$output);
	
		ob_start();
		shopp('product','price','taxes=off');
		$output = ob_get_contents();
		ob_end_clean();
		$this->assertEquals("$34.86 &mdash; $129.95",$output);

		ob_start();
		shopp('product','saleprice');
		$output = ob_get_contents();
		ob_end_clean();
		$this->assertEquals("$17.32 &mdash; $73.44",$output);
	
		ob_start();
		shopp('product','saleprice','taxes=off');
		$output = ob_get_contents();
		ob_end_clean();
		$this->assertEquals("$15.06 &mdash; $63.86",$output);

		$Shopp->Settings->registry['taxrates'] = array();
		
	}
	
	function test_product_hassavings () {
		$this->assertTrue(shopp('product','has-savings'));
	}
	
	function test_product_savings () {
		ob_start();
		shopp('product','savings');
		$output = ob_get_contents();
		ob_end_clean();
		$this->assertEquals("$19.80 &mdash; $66.09",$output);

		ob_start();
		shopp('product','savings','show=percent');
		$output = ob_get_contents();
		ob_end_clean();
		$this->assertEquals("190% &mdash; 51%",$output);
	}
	
	function test_product_weight () {
		ob_start();
		shopp('product','weight');
		$output = ob_get_contents();
		ob_end_clean();
		$this->assertEquals("0.2 - 1.151 lb",$output);
	}
	
	function test_product_thumbnail () {
		ob_start();
		shopp('product','thumbnail');
		$output = ob_get_contents();
		ob_end_clean();
		$this->assertXmlStringEqualsXmlString('<img src="http://shopptest/store/images/363" alt="Ultimate Matrix Collection" width="96" height="96"  />',$output);
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
		$control = '<select name="products[81][quantity]" id="quantity-81"><option selected="selected" value="1">1</option><option value="2">2</option><option value="3">3</option><option value="5">5</option><option value="10">10</option><option value="11">11</option><option value="12">12</option><option value="13">13</option><option value="14">14</option><option value="15">15</option></select>';
		$this->assertEquals($control,$output);
		$this->assertValidMarkup($output);
	}
	
	function test_product_freeshipping () {
		$this->assertFalse(shopp('product','freeshipping'));
	}
	
	function test_product_addtocart () {
		ob_start();
		shopp('product','addtocart');
		$output = ob_get_contents();
		ob_end_clean();
		$this->assertValidMarkup($output);
	}

	function test_product_hascategories () {
		global $Shopp;
		$this->assertTrue(shopp('product','has-categories'));
		$this->assertEquals(3,count($Shopp->Product->categories));
	}
	
	function test_product_category_tags () {
		ob_start();
		if (shopp('product','has-categories')) 
			while(shopp('product','categories')) shopp('product','category');
		$output = ob_get_contents();
		ob_end_clean();
		$this->assertEquals('EntertainmentMovies & TVBlu-Ray',$output);
	}
	
	function test_product_hasimages () {
		global $Shopp;
		$this->assertTrue(shopp('product','hasimages'));
		$this->assertTrue(shopp('product','has-images'));
		$this->assertEquals(6,count($Shopp->Product->images));
	}
	
	function test_product_image_tags () {
		ob_start();
		shopp('product','thumbnail');
		$thumbnail = ob_get_contents();
		ob_end_clean();
		
		ob_start();
		if (shopp('product','has-images')) 
			while(shopp('product','images')) shopp('product','image');
		$output = ob_get_contents();
		ob_end_clean();
		
		$this->assertEquals('<img src="http://shopptest/store/images/363" alt="Ultimate Matrix Collection" width="96" height="96"  /><img src="http://shopptest/store/images/480" alt="Ultimate Matrix Collection" width="96" height="96"  />',$output);
	}
	
} // end ProductAPITests class

?>