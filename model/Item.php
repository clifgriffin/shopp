<?php
/**
 * Item class
 * Cart items
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 28 March, 2008
 * @package shopp
 **/

class Item extends DatabaseObject {

	function Item ($id=false) {
		$this->init('item');
		if ($this->load($id)) return true;
		else return false;
	}

} // end Item class

?>