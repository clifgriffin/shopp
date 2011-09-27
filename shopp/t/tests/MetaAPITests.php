<?php
/**
*
*/
class MetaAPITests extends ShoppTestCase
{

	function test_bug1130_shopp_set_meta () {
		$Price = shopp_product_variant(258);

		$settings = array(
			'dimensions' => array(
				'weight'=> 1.1,
				'height'=> 2,
				'width' => 10,
				'length' => 10
			)
		);

		$return = shopp_set_meta ( $Price->id, 'price', 'settings', $settings );
		$this->AssertTrue($return);

		$return = shopp_set_meta ( $Price->id, 'price', 'settings', $settings );
		$this->AssertTrue($return);

		// get all meta records for this priceline
		$meta = shopp_meta ( $Price->id, 'price');
		$this->AssertEquals('a:1:{i:2205;O:8:"stdClass":4:{s:6:"parent";i:258;s:4:"type";s:4:"meta";s:4:"name";s:8:"settings";s:5:"value";a:1:{s:10:"dimensions";a:4:{s:6:"weight";d:1.1000000000000001;s:6:"height";i:2;s:5:"width";i:10;s:6:"length";i:10;}}}}',
			serialize($meta));

	}

	function test_shopp_set_meta () {
		$Price = shopp_product_variant(174);
		$return = shopp_set_meta ( $Price->id, 'price', 'mypricesetting', 'hello world' );
		$meta = shopp_meta ( $Price->id, 'price', 'mypricesetting');
		$this->AssertEquals('hello world', $meta);
	}

	function test_shopp_meta () {
		$data = array(
			'name' => "Download Product Test",
			'single' => array(),
			'publish' => array( 'flag' => true ),
			'description' =>
				"Testing Download"
		);
		$data['single'] = array(
			'type' => 'Download',
			'price' => 41.00,
		);

		$Product = shopp_add_product($data);

		file_put_contents ( 'testdownload.txt' , 'my test download file' );
		shopp_add_product_download ( $Product->id, realpath('testdownload.txt') );
		$ProductVariant = shopp_product_variant(array('product'=>$Product->id), 'product');

		$download = shopp_meta ( $ProductVariant->id, 'price', 'testdownload.txt', 'download' );

		$this->AssertTrue(is_object($download));
		$this->AssertEquals('text/plain', $download->mime);
		$this->AssertEquals('21', $download->size);
		$this->AssertEquals('testdownload.txt', $download->uri);
	}

}
?>