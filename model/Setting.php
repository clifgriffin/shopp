<?php
/**
 * Setting class
 * Shopp settings
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 28 March, 2008
 * @package shopp
 **/

class Setting extends DatabaseObject {

	function Setting ($id=false) {
		$this->init('setting');
		if ($this->load($id)) return true;
		else return false;
	}

} // end Shop class

?>