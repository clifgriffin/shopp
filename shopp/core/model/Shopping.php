<?php
/**
 * Shopping
 * 
 * Flow controller for the customer shopping experience
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, January 6, 2010
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @since 1.1
 * @subpackage shopping
 **/

/**
 * Shopping class
 * 
 * The Shopping class is a specific implementation of a SessionObject that 
 * provides automated session storage of data of any kind.  Data must be 
 * registered to the Shopping class by statically calling the
 * ShoppingObject::store method to be stored in and loaded from the 
 * session.
 * 
 * Storing objects requires the use of the ShoppingObject helper class in 
 * order to maintain initialized instances {@see ShoppingObject}
 *
 * @author Jonathan Davis
 * @package shopp
 * @since 1.1
 **/
class Shopping extends SessionObject {
	
	/**
	 * Shopping constructor
	 *
	 * @author Jonathan Davis
	 * @todo Change table to 'shopping' and update schema
	 * 
	 * @return void
	 **/
	function __construct () {
		// Set the database table to use
		$this->_table = DatabaseObject::tablename('shopping');

		// Initialize the session handlers
		parent::__construct();

		// Queue the session to start
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
		@session_start();
	}	
	
	/**
	 * Resets the entire session
	 *
	 * Generates a new session ID and reassigns the current session 
	 * to the new ID, then wipes out the Cart contents.
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @return boolean
	 **/
	function reset () {
		session_regenerate_id();
		$this->session = session_id();
		session_write_close();
		do_action('shopp_session_reset');
		return true;
	}
		
} // END class Shopping

/**
 * ShoppingObject class
 * 
 * A helper class that uses a Factory-like approach in instantiating objects
 * ensuring that the correct instantiation of the object is always provided.
 * When planning to store an entire object in the session, the object must
 * be initialized by calling the __new method of the ShoppingObject and 
 * providing the class name as the only argument:
 * 
 * $object = &ShoppingObject::__new('ObjectClass');
 * 
 * The ShoppingObject then determines if the object has already been 
 * initialized from a previous session, or if a new instance is required 
 * returning a reference to the instance object.
 * 
 * NOTE: It is important to realize that any ShoppingObject-instantiated 
 * objects that use action hooks will need to re-establish those action 
 * hooks after the session is reloaded because the unserialized instance of 
 * the object will lose its hook callbacks.  This can be done by defining 
 * a new method for initalizing all the applicable action listeners, then 
 * calling that method both in the object constructor and using the __wakeup
 * magic method.
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package shopp
 **/
class ShoppingObject {
	
	static function &__new ($class, &$ref=false) {
		global $Shopp;
		
		if ($ref !== false) $ref->__destruct();
		
		if (isset($Shopp->Shopping->data->{$class})) // Restore the object
			$object = $Shopp->Shopping->data->{$class};
		else {
			$object = new $class();					// Create a new object
			$Shopp->Shopping->data->{$class} = &$object; // Register storage
		}

		return $object;
	}
	
	/**
	 * Handles data to be stored in the shopping session
	 * 
	 * Registers non-object data to be stored in the session and restores the 
	 * data when the property exists (was loaded) from the session data.
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param string $property Property name to use
	 * @param object $data The data to store
	 * @return void
	 **/
	static function store ($property, &$data) {
		global $Shopp;
		if (isset($Shopp->Shopping->data->{$property}))	// Restore the data
			$data = $Shopp->Shopping->data->{$property};
		$Shopp->Shopping->data->{$property} = &$data;	// Keep a reference
	}
		
}

?>