<?php
/**
* tests for api/collection.php
*/
class CollectionDevAPITests extends ShoppTestCase
{

	function test_shopp_add_product_category () {
		global $test_shopp_add_product_category;

		$this->AssertFalse( ! $parent = shopp_add_product_category('Unit Test Category Parent','Product Category for Unit Testing') );
		$this->AssertFalse( ! $child = shopp_add_product_category('Unit Test Category Child', 'Product Category for Unit Testing', $parent) );
		$this->AssertFalse( ! $grandchild = shopp_add_product_category('Unit Test Category Grand-Child', 'Product Category for Unit Testing', $child) );
		$this->AssertFalse( ! $secondgrandchild = shopp_add_product_category('Unit Test Category 2nd Grand-Child', 'Product Category for Unit Testing', $child) );
		$this->AssertFalse( ! $ggrandchild = shopp_add_product_category('Unit Test Category Great Grand-Child', 'Product Category for Unit Testing', $secondgrandchild) );

		// check hierarchy
		$hierarchy = _get_term_hierarchy(ProductCategory::$taxonomy);
		$this->AssertTrue(in_array($parent, array_keys($hierarchy)));
		$this->AssertTrue(in_array($child, $hierarchy[$parent]));

		$this->AssertTrue(in_array($child, array_keys($hierarchy)));
		$this->AssertTrue(in_array($grandchild, $hierarchy[$child]));
		$this->AssertTrue(in_array($secondgrandchild, $hierarchy[$child]));

		$this->AssertTrue(in_array($secondgrandchild, array_keys($hierarchy)));
		$this->AssertTrue(in_array($ggrandchild, $hierarchy[$secondgrandchild]));

		$test_shopp_add_product_category = array($parent, $child, $grandchild, $secondgrandchild, $ggrandchild);
		foreach ( $test_shopp_add_product_category as $cat ) {
			$this->AssertTrue( is_a($Cat = shopp_product_category($cat), 'ProductCategory'));
			$this->AssertTrue( $Cat->id == $cat );
		}

	}

	function test_shopp_rmv_product_category () {
		global $test_shopp_add_product_category;
		foreach ( $test_shopp_add_product_category as $destroy ) {
			$this->AssertTrue( shopp_rmv_product_category($destroy) );
			$this->AssertFalse( shopp_product_category($destroy) );
		}
	}
}
?>