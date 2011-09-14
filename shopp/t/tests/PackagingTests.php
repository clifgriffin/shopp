<?php
/**
 * PackagingTests
 *
 *
 * @author John Dillick
 * @version 1.0
 * @copyright Ingenesis Limited, 6 April, 2011
 * @package
 **/

/**
 * Initialize
 **/

class PackagingTests extends ShoppTestCase {
	function setUp () {
		parent::setUp();

		global $PkgProduct1, $PkgProduct2, $PkgProduct3, $PkgProduct4;

		// doesn't matter... packaged alone in all models
		if ( is_a($PkgProduct1, 'Product') ) return;
		$data = array(
			'name' => "Packager Test Product 1",
			'publish' => array( 'flag' => true ),
			'description' => "item 1",
			'packaging' => true
		);
		$data['single'] = array(
			'type' => 'Shipped',
			'price' => 41.00,
			'shipping' => array('flag'=>true, 'fee'=>1.50, 'weight'=>1, 'length'=>1, 'width'=>1, 'height'=>1)
		);
		$PkgProduct1 = shopp_add_product($data);

		// Square item 10 lbs
		$data = array(
			'name' => "Packager Test Product 2",
			'publish' => array( 'flag' => true ),
			'description' => "item 2",
			'packaging' => false
		);
		$data['single'] = array(
			'type' => 'Shipped',
			'price' => 42.00,
			// doesn't matter... packaged alone in all models
			'shipping' => array('flag'=>true, 'fee'=>1.50, 'weight'=>10, 'length'=>5, 'width'=>5, 'height'=>5)
		);
		$PkgProduct2 = shopp_add_product($data);

		// long item 15 lbs
		$data = array(
			'name' => "Packager Test Product 3",
			'publish' => array( 'flag' => true ),
			'description' => "item 3",
			'packaging' => false
		);
		$data['single'] = array(
			'type' => 'Shipped',
			'price' => 42.00,
			// doesn't matter... packaged alone in all models
			'shipping' => array('flag'=>true, 'fee'=>1.50, 'weight'=>15, 'length'=>15, 'width'=>5, 'height'=>5)
		);
		$PkgProduct3 = shopp_add_product($data);

		// tall item 50 lbs
		$data = array(
			'name' => "Packager Test Product 4",
			'publish' => array( 'flag' => true ),
			'description' => "item 4",
			'packaging' => false
		);
		$data['single'] = array(
			'type' => 'Shipped',
			'price' => 42.00,
			// doesn't matter... packaged alone in all models
			'shipping' => array('flag'=>true, 'fee'=>1.50, 'weight'=>50, 'length'=>10, 'width'=>10, 'height'=>20)
		);
		$PkgProduct4 = shopp_add_product($data);

	}

	function test_package_mass () {
		global $PkgProduct1, $PkgProduct2, $PkgProduct3, $PkgProduct4;
		$products = array($PkgProduct1, $PkgProduct2, $PkgProduct3, $PkgProduct4);
		$items = array();
		foreach ( $products as $i => $Product ) {
			$items[$i] = new Item ( $Product, false );
			$items[$i]->quantity( $i + 1 );
		}

		$packager = new ShippingPackager(array('type'=>'mass'));

		foreach ($items as $Item) {
			$packager->add_item($Item);
		}
		$pkgs = array();
		while ( $packager->packages() ) $pkgs[] = $p = $packager->package();

		// check package 1
		$this->AssertEquals(2, count($pkgs));
		$pkg = $pkgs[0];
		$this->AssertEquals(1, count($pkg->contents()));
		$this->AssertEquals(1, $pkg->weight());
		$this->AssertEquals(41, $pkg->value());
		$contents = $pkg->contents();
		$this->AssertEquals(1, reset($contents)->quantity);

		// check package 2
		$pkg = $pkgs[1];
		$this->AssertEquals(3, count($pkg->contents()));
		$this->AssertEquals(265, $pkg->weight());
		$this->AssertEquals(378, $pkg->value());
		$contents = $pkg->contents();

		$item = reset($contents);
		$this->AssertEquals(2, $item->quantity);
		$this->AssertEquals('Packager Test Product 2', $item->name);

		$item = next($contents);
		$this->AssertEquals(3, $item->quantity);
		$this->AssertEquals('Packager Test Product 3', $item->name);

		$item = next($contents);
		$this->AssertEquals(4, $item->quantity);
		$this->AssertEquals('Packager Test Product 4', $item->name);

		$products = array($PkgProduct1, $PkgProduct2, $PkgProduct3, $PkgProduct4);
		$items = array();
		foreach ( $products as $i => $Product ) {
			$items[$i] = new Item ( $Product, false );
			$items[$i]->quantity( 4 - $i );
		}
		foreach ($items as $Item) {
			$packager->add_item($Item);
		}

		$pkgs = array();
		while ( $packager->packages() ) $pkgs[] = $p = $packager->package();

		$this->AssertEquals(6, count($pkgs));

		$count = 0;
		foreach($pkgs as $i => $pkg) {
			$wt = $pkg->weight();
			$w = $pkg->width();
			$l = $pkg->length();
			$h = $pkg->height();
			$v = $pkg->value();
			$contents = $pkg->contents();
			$items = array();
			foreach( $contents as $item ) {
				$items[] = array( 'qty'=>$item->quantity, 'name'=>$item->name );
			}

			switch ($count++) {
				case 0:
				case 2:
				case 3:
				case 4:
				case 5:
					$this->AssertEquals(1, $wt);
					$this->AssertEquals(1, $w);
					$this->AssertEquals(1, $l);
					$this->AssertEquals(1, $h);
					$this->AssertEquals(41, $v);
					$this->AssertEquals(1, count($items));
					break;

				case 1:
					$this->AssertEquals(375, $wt);
					$this->AssertEquals(0, $w);
					$this->AssertEquals(0, $l);
					$this->AssertEquals(0, $h);
					$this->AssertEquals(630, $v);
					$this->AssertEquals(3, count($items));
					break;

			}
		}
	}

	function test_package_like () {}

	function test_package_all () {}

	function test_package_piece () {}
}
?>