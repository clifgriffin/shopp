<?php
/**
 * Customer class
 * Customer contact information
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 28 March, 2008
 * @package shopp
 **/

class Customer extends DatabaseObject {
	static $table = "customer";

	function Customer ($id=false,$key=false) {
		$this->init(self::$table);
		if ($this->load($id,$key)) return true;
		else return false;
	}

} // end Customer class

?>