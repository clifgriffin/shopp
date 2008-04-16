<?php
/**
 * Category class
 * 
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited,  9 April, 2008
 * @package shopp
 **/

class Category extends DatabaseObject {

	function Category ($id=false) {
		$this->init('category');
		if ($this->load($id)) return true;
		else return false;
	}

} // end Category class

?>