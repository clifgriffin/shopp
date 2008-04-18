<?php
/**
 * Asset class
 * Catalog product assets (metadata, images, downloads)
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 28 March, 2008
 * @package shopp
 **/

class Asset extends DatabaseObject {

	function Asset ($id=false) {
		$this->init('asset');
		if ($this->load($id)) return true;
		else return false;
	}	

} // end Asset class

?>