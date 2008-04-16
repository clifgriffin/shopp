<?php
/**
 * Catalog class
 * 
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited,  9 April, 2008
 * @package shopp
 **/

class Catalog extends DatabaseObject {

	function Catalog ($id=false) {
		$this->init('catalog');
		if ($this->load($id)) return true;
		else return false;
	}

} // end Catalog class

?>