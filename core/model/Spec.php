<?php
/**
 * Spec class
 * Catalog product spec table
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 26 July, 2008
 * @package shopp
 * @subpackage product
 **/
class Spec extends MetaObject {
	
	function __construct ($id=false) {
		$this->init(self::$table);
		$this->load($id);
		$this->context = 'product';
		$this->type = 'spec';
	}
	
	function updates ($data,$ignored=array()) {
		parent::updates($data,$ignores);
		if (preg_match('/^.*?(\d+[\.\,\d]*).*$/',$this->value))
			$this->numeral = preg_replace('/^.*?(\d+[\.\,\d]*).*$/','$1',$this->value);
	}

} // END class Spec

?>