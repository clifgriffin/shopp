<?php
/**
 * Variation class
 * Catalog product variations
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 28 March, 2008
 * @package shopp
 **/

class Variation extends DatabaseObject {

	function Variation ($id=false) {
		$this->init('var');
		if ($this->load($id)) return true;
		else return false;
	}

} // end Option class

?>