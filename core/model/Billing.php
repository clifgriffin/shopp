<?php
/**
 * Billing class
 * Billing information
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 28 March, 2008
 * @package shopp
 **/

class Billing extends DatabaseObject {
	static $table = "billing";

	function Billing ($id=false,$key=false) {
		$this->init(self::$table);
		if ($this->load($id,$key)) return true;
		else return false;
	}

} // end Billing class

?>