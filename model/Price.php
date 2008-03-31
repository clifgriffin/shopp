<?php
/**
 * Price class
 * Catalog product price variations
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 28 March, 2008
 * @package shopp
 **/

class Price extends DatabaseObject {

	function Price ($id=false) {
		$this->init('price');
		if ($this->load($id)) return true;
		else return false;
	}

} // end Price class

?>