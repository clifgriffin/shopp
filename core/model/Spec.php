<?php
/**
 * Spec class
 * Catalog product spec table
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 26 July, 2008
 * @package shopp
 **/

class Spec extends DatabaseObject {

	function Spec ($id=false) {
		$this->init('Spec');
		if ($this->load($id)) return true;
		else return false;
	}

} // end Spec class

?>