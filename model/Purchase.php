<?php
/**
 * Purchase class
 * Order invoice logging
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 28 March, 2008
 * @package shopp
 **/

class Purchase extends DatabaseObject {

	function Purchase ($id=false) {
		$this->init('purchase');
		if ($this->load($id)) return true;
		else return false;
	}

} // end Purchase class

?>