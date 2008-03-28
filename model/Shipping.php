<?php
/**
 * Shipping class
 * Shipping addresses
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 28 March, 2008
 * @package shopp
 **/

class Shipping extends DatabaseObject {

	function Shipping ($id=false) {
		$this->init('shipping');
		if ($this->load($id)) return true;
		else return false;
	}

} // end Shipping class

?>