<?php
/**
 * Shopping
 * 
 * Flow controller for the customer shopping experience
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, January 6, 2010
 * @package shopp
 * @subpackage shopping
 **/

/**
 * Shopping
 *
 * @author Jonathan Davis
 * @package shopp
 * @since 1.1
 **/
class Shopping extends SessionObject {
	
	var $registry = array();

	/**
	 * Shopping constructor
	 *
	 * @author Jonathan Davis
	 * @todo Change table to 'shopping' and update schema
	 * 
	 * @return void
	 **/
	function __construct () {
		$this->_table = DatabaseObject::tablename('cart');
		parent::__construct();
		add_action('init',array(&$this,'init'));
	}

	/**
	 * Starts the session
	 * 
	 * Initializes the session if not already started
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function init () {
		if(session_id() == "") @session_start();
	}	

	/**
	 * Handles data to be stored in the shopping session
	 * 
	 * Registers data objects to be stored in the session and restores the 
	 * object when the property exists (was loaded) from the session data.
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param string $property Property name to use
	 * @param object $object The object data to store
	 * @return void
	 **/
	function store ($property, &$object) {
		if (isset($this->data->{$property}))
			$object = $this->data->{$property};	
		$this->data->{$property} = &$object;
	}
		
} // end Shopping class

?>