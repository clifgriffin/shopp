<?php
/**
 * Cart class
 * Shopping session handling
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 28 March, 2008
 * @package shopp
 **/

require("Item.php");

$Cart =& new Cart();
session_start();

class Cart {

	// properties
	var $_table;
	var $session;
	var $customer = 0;
	var $created;
	var $modified;
	var $ip;
	var $data;
	var $contents = array();
	
	// methods
	
	/* Cart()
	 * Constructor that creates a new shopping Cart runtime object */
	function Cart () {
		$this->_table = DBPREFIX."cart";
		
		session_set_save_handler(
			array( &$this, 'open' ),	// Open
			array( &$this, 'close' ),	// Close
			array( &$this, 'load' ),	// Read
			array( &$this, 'save' ),	// Write
			array( &$this, 'unload' ),	// Destroy
			array( &$this, 'trash' )	// Garbage Collection
		);
	}
		
	/* open()
	 * Initializing routine for the session management. */
	function open () {
		$this->trash();	// Clear out any residual session information before loading new data
		if (!isset($this->session)) $this->session = session_id();	// Grab our session id
		$this->ip = $_SERVER['REMOTE_ADDR'];						// Save the IP address making the request
		return true;
	}
	
	/* close()
	 * Placeholder function as we are working with a persistant 
	 * database as opposed to file handlers. */
	function close () {
		return true;
	}

	/* load()
	 * Gets data from the session data table and loads Member 
	 * objects into the User from the loaded data. */
	function load () {
		$db =& DB::get();

		if (is_robot()) return true;
				
		if ($result = $db->query("SELECT * FROM $this->_table WHERE session='$this->session'")) {
			$this->ip = $result->ip;
			$this->data = unserialize($result->data);
			$this->contents = unserialize($result->contents);
			$this->created = mktimestamp($result->created);
			$this->modified = mktimestamp($result->modified);
			
		} else {
			$db->query("INSERT INTO $this->_table (session, ip, created, modified) 
							VALUES ('$this->session','$this->ip',now(),now())");
		}

		return true;
	}
	
	/* unload()
	 * Deletes the session data from the database, unregisters the 
	 * session and releases all the objects. */
	function unload () {
		$db =& DB::get();		
		if (!$db->query("DELETE LOW_PRIORITY FROM $this->_table WHERE session='$this->session'")) 
			trigger_error("Could not clear session data.");
		return true;
	}
	
	/* save() 
	 * Save the session data to our session table in the database. */
	function save () {
		$db =& DB::get();
		
		$loggedin = ($this->loggedin) ? 1 : 0;
		$data = serialize($this->data);
		$contents = serialize($this->contents);
		if (!$db->query("UPDATE $this->_table SET customer='$this->customer',ip='$this->ip',data='$data',contents='$contents',modified=now() WHERE session='$this->session'")) 
			trigger_error("Could not save session updates to the database.");
		return true;
	}

	/* trash()
	 * Garbage collection routine for cleaning up lingering 
	 * sessions. */
	function trash () {
		$db =& DB::get();
		// 1800 seconds = 30 minutes, 3600 seconds = 1 hour
		if (!$db->query("DELETE LOW_PRIORITY FROM $this->_table WHERE UNIX_TIMESTAMP(NOW())-UNIX_TIMESTAMP(modified) > 7200")) 
			trigger_error("Could not delete cached session data.");
		return true;
	}
	
	/**
	 * add()
	 * Adds a product as an item to the cart */
	function add ($quantity,$Product,$Price) {
		if (($item = $this->hasproduct($Product->id,$Price->id)) !== false) {
			$this->contents[$item]->add($quantity);
		} else {
			$Item = new Item($quantity,$Product,$Price);
			$this->contents[] = $Item;
		}
		$this->totals();
		$this->save();
		return true;
	}
	
	/**
	 * remove()
	 * Removes an item from the cart */
	function remove ($item) {
		array_splice($this->contents,$item,1);
		$this->totals();
		$this->save();
		return true;
	}
	
	/**
	 * update()
	 * Changes the quantity of an item in the cart */
	function update ($item,$quantity) {
		if (empty($this->contents)) return false;
		if ($quantity == 0) return $this->remove($item);
		else {
			$this->contents[$item]->quantity($quantity);
			$this->totals();
			$this->save();
		}
		return true;
	}
	
	/**
	 * change()
	 * Changes an item to a different product/price variation */
	function change ($item,$Product,$Price) {
		$this->contents[$item] = new Item($this->contents[$item]->quantity,$Product,$Price);
		$this->totals();
		$this->save();
		return true;
	}
	
	/**
	 * hasproduct()
	 * Determines if a specified product/price variation is 
	 * currently in this cart */
	function hasproduct($product,$price) {
		$i = 0;
		foreach ($this->contents as $Item) {
			if ($Item->product == $product && 
					$Item->price == $price) return $i;
			$i++;
		}
		return false;
	}
	
	function totals () {
		global $Shopp;
		$this->data = new StdClass();
		$this->data->subtotal = 0;
		$this->data->shipping = 0;
		$this->data->tax = 0;
		$this->data->total = 0;
		
		foreach ($this->contents as $Item) {
			$this->data->subtotal +=  $Item->total;
			if ($Item->shipping="on") {
				$this->data->shipping += ($Item->quantity * $Item->domship);
			}
				
		}
		$this->data->total = $this->data->subtotal+$this->data->shipping;		
	}
	
} // end Cart class

?>