<?php
/**
 * Product class
 * Catalog products
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 28 March, 2008
 * @package shopp
 **/

class Product extends DatabaseObject {

	function Product ($id=false) {
		$this->init('product');
		if ($this->load($id)) return true;
		else return false;
	}

} // end Product class

?>