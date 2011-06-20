<?php
/**
 * ProductAPITests
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 14 October, 2009
 * @package shopp
 **/
class ProductAPITests extends ShoppTestCase {

	function setUp () {
		global $Shopp;
		parent::setUp();
		$Shopp->Flow->Controller = new Storefront();
		$Shopp->Catalog = new Catalog();
		shopp('catalog','product','id=81&load=1');
		shopp('product','found');
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
		if (SHOPP_PRETTYURLS) $this->assertEquals('http://shopptest/store/ultimate-matrix-collection/',$output);
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
		$Settings =& ShoppSettings();
		$Settings->registry['base_operations'] = unserialize('a:7:{s:4:"name";s:3:"USA";s:8:"currency";a:2:{s:4:"code";s:3:"USD";s:6:"format";a:6:{s:4:"cpos";b:1;s:8:"currency";s:1:"$";s:9:"precision";i:2;s:8:"decimals";s:1:".";s:9:"thousands";s:1:",";s:8:"grouping";a:1:{i:0;i:3;}}}s:5:"units";s:8:"imperial";s:6:"region";i:0;s:7:"country";s:2:"US";s:4:"zone";s:2:"OH";s:3:"vat";b:0;}');
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
							'grouping' => 3
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
		$this->assertEquals("51% &mdash; 57%",$output);
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
		$this->assertXmlStringEqualsXmlString('<img src="http://shopptest/store/images/652/UlitimateMatrixBRCollections.jpg?96,96,2395623139" alt="Ultimate Matrix Collection" width="96" height="96"/>',$output);
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
		$output = ob_get_contents();
		ob_end_clean();

		$expected = array(
			'tag' => 'select',
			'attributes' => array(
				'name' => 'products[81][quantity]',
				'id' => 'quantity-81'
			),
			'children' => array(
					'count' => 10,
					'only' => array('tag' => 'option')
			)
		);

		$this->assertTag($expected,$output,"++ $output",true);
		$this->assertValidMarkup($output);
	}

	function test_product_freeshipping () {
		$this->assertFalse(shopp('product','freeshipping'));
	}

	function test_product_hasimages () {
		global $Shopp;
		$this->assertTrue(shopp('product','hasimages'));
		$this->assertTrue(shopp('product','has-images'));
		$this->assertEquals(2,count($Shopp->Product->images));
	}

	function test_product_hascategories () {
		global $Shopp;
		$this->assertTrue(shopp('product','has-categories'));
		$this->assertEquals(3,count($Shopp->Product->categories));
	}

	function test_product_incategory_byname () {
		$this->assertTrue(shopp('product','in-category','name=Entertainment'));
	}

	function test_product_incategory_byslug () {
		$this->assertTrue(shopp('product','in-category','slug=movies-tv'));
	}

	function test_product_incategory_byid () {
		$this->assertTrue(shopp('product','in-category','id=24'));
	}

	function test_product_category_tags () {
		ob_start();
		if (shopp('product','has-categories'))
			while(shopp('product','categories')) shopp('product','category');
		$output = ob_get_contents();
		ob_end_clean();
		$this->assertEquals('EntertainmentMovies & TVBlu-Ray',$output);
	}

	function test_product_image_tags () {
		ob_start();
		if (shopp('product','has-images'))
			while(shopp('product','images')) shopp('product','image');
		$output = ob_get_contents();
		ob_end_clean();

		$this->assertEquals('<img src="http://shopptest/store/images/652/UlitimateMatrixBRCollections.jpg?96,96,2395623139" alt="Ultimate Matrix Collection" width="96" height="96"  /><img src="http://shopptest/store/images/690/zz6fac9e2b.jpg?96,96,1325025925" alt="original" width="96" height="67"  />',$output);
	}

	function test_product_hastags () {
		global $Shopp;
		$this->assertTrue(shopp('product','hastags'));
		$this->assertTrue(shopp('product','has-tags'));
		$this->assertEquals(6,count($Shopp->Product->tags));
	}

	function test_product_tag_tags () {
		ob_start();
		if (shopp('product','has-tags'))
			while(shopp('product','tags')) shopp('product','tag');
		$output = ob_get_contents();
		ob_end_clean();

		$this->assertEquals('matrixtrilogybluraymoviewarnerwachowski',$output);
	}

	function test_product_tagged_byname () {
		$this->assertTrue(shopp('product','tagged','name=matrix'));
	}

	function test_product_tagged_byid () {
		$this->assertTrue(shopp('product','tagged','id=28'));
	}


	function test_product_hasspecs () {
		global $Shopp;
		$this->assertTrue(shopp('product','hasspecs'));
		$this->assertTrue(shopp('product','has-specs'));
		$this->assertEquals(7,count($Shopp->Product->specs));
	}

	function test_product_spec_tags () {
		ob_start();
		if (shopp('product','has-specs'))
			while(shopp('product','specs')) shopp('product','spec');
		$output = ob_get_contents();
		ob_end_clean();

		$this->assertEquals('Rating: R RatedStudio: Warner Home VideoRun Time (in minutes): 415Format: Blu-Ray, DVDLanguage: EnglishScreen Format: WidescreenDirector: The Wachowski Brothers',$output);
	}

	function test_product_spec_tags_byname () {
		ob_start();
		if (shopp('product','has-specs'))
			while(shopp('product','specs')) shopp('product','spec','name');
		$output = ob_get_contents();
		ob_end_clean();

		$this->assertEquals('RatingStudioRun Time (in minutes)FormatLanguageScreen FormatDirector',$output);

	}

	function test_product_spec_tags_bycontent () {
		ob_start();
		if (shopp('product','has-specs'))
			while(shopp('product','specs')) shopp('product','spec','content');
		$output = ob_get_contents();
		ob_end_clean();

		$this->assertEquals('R RatedWarner Home Video415Blu-Ray, DVDEnglishWidescreenThe Wachowski Brothers',$output);
	}

	function test_product_outofstock () {
		$this->assertFalse(shopp('product','outofstock'));
	}

	function test_product_hasvariations () {
		$this->assertTrue(shopp('product','has-variations'));
	}

	function test_product_variations_menus () {
		global $Shopp;

		ob_start();
		shopp('product','variations','mode=single');
		$output = ob_get_contents();
		ob_end_clean();
		$this->assertValidMarkup($output);

		ob_start();
		shopp('product','variations','mode=multi');
		$output = ob_get_contents();
		ob_end_clean();
		$this->assertValidMarkup($output);

	}

	function test_product_variation_tags () {
		ob_start();
		if (shopp('product','has-variations')) {
			while(shopp('product','variations')) {
				shopp('product','variation','id');
				shopp('product','variation','label');
				shopp('product','variation','type');
				shopp('product','variation','sku');
				shopp('product','variation','price');
				shopp('product','variation','saleprice');
				shopp('product','variation','stock');
				shopp('product','variation','weight');
				shopp('product','variation','shipfee');
				shopp('product','variation','sale');
				shopp('product','variation','shipping');
				shopp('product','variation','tax');
				shopp('product','variation','inventory');
			}
		}
		$output = ob_get_contents();
		ob_end_clean();
		$this->assertEquals('228Blu-RayShippedBR-81$129.95$63.86251.151 lb$0.00256DVDShipped$34.86$15.060.2 lb$0.00',$output);

	}

	function test_product_input () {
		ob_start();
		shopp('product','input','type=text&name=Testing');
		$output = ob_get_contents();
		ob_end_clean();

		$markup = array(
			'tag' => 'input',
			'attributes' => array(
				'type' => 'text',
				'name' => 'products[81][data][Testing]',
				'id' => 'data-Testing-81'
			)
		);
		$this->assertTag($markup,$output,'',true);
		$this->assertValidMarkup($output);
	}

	function test_product_addtocart () {
		global $Shopp;

		$Shopp->Product->outofstock = true;
		ob_start();
		shopp('product','addtocart');
		$output = ob_get_contents();
		ob_end_clean();
		$this->assertEquals('<span class="outofstock">Out of stock</span>',$output);
		$Shopp->Product->outofstock = false;

		ob_start();
		shopp('product','addtocart');
		$output = ob_get_contents();
		ob_end_clean();

		$markup = array(
			'tag' => 'input',
			'attributes' => array('type' => 'hidden','name' => 'products[81][product]','value' => '81')
		);
		$this->assertTag($markup,$output,'',true);

		$markup = array(
			'tag' => 'input',
			'attributes' => array('type' => 'hidden','name' => 'cart','value' => 'add')
		);
		$this->assertTag($markup,$output,'',true);

		$markup = array(
			'tag' => 'input',
			'attributes' => array('type' => 'submit','name' => 'addtocart')
		);
		$this->assertTag($markup,$output,'',true);

		$this->assertValidMarkup($output);

	}


} // end ProductAPITests class

?>