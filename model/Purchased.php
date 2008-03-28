<?php
/**
 * Purchased class
 * Purchased line items for orders
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 28 March, 2008
 * @package shopp
 **/

class Purchased extends DatabaseObject {

	function Purchased ($id=false) {
		$this->init('purchased');
		if ($this->load($id)) return true;
		else return false;
	}

} // end Purchased class

?>