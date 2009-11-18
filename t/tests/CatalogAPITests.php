<?php
/**
 * CatalogAPITests
 * 
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 21 October, 2009
 * @package 
 **/

/**
 * Initialize
 **/

class CatalogAPITests extends ShoppTestCase {

	function test_catalog_url () {
		ob_start();
		shopp('catalog','url');
		$actual = ob_get_contents();
		ob_end_clean();
		$this->assertEquals('http://shopptest/',$actual);		
	}

	function test_catalog_type () {
		ob_start();
		shopp('catalog','type');
		$actual = ob_get_contents();
		ob_end_clean();
		$this->assertEquals('catalog',$actual);		
	}
	
	// function test_catalog_iscatalog () {
	// 	global $Shopp;
	// 	$Shopp->Catalog->type = 'catalog';
	// 	$this->assertTrue(shopp('catalog','is-catalog'));
	// }

} // end CatalogAPITests class

?>