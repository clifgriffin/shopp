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
require("Customer.php");
require("Billing.php");
require("Shipping.php");

$Cart =& new Cart();
session_start();

class Cart {

	// properties
	var $_table;
	var $session;
	var $created;
	var $modified;
	var $ip;
	var $data;
	var $contents = array();
	var $looping = false;
	var $runaway = 0;
	
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
		
		$this->data = new stdClass();
		$this->data->Totals = new stdClass();
		$this->data->Totals->subtotal = 0;
		$this->data->Totals->shipping = 0;
		$this->data->Totals->tax = 0;
		$this->data->Totals->taxrate = 0;
		$this->data->Totals->total = 0;
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
			reset($this->contents);
			
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
		if (!$db->query("DELETE FROM $this->_table WHERE session='$this->session'")) 
			trigger_error("Could not clear session data.");
		return true;
	}
	
	/* save() 
	 * Save the session data to our session table in the database. */
	function save () {
		$db =& DB::get();
		
		$data = serialize($this->data);
		$contents = serialize($this->contents);
		if (!$db->query("UPDATE $this->_table SET ip='$this->ip',data='$data',contents='$contents',modified=now() WHERE session='$this->session'")) 
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
		global $Shopp;
		if ($Price->product != $Product->id) return false;
		if (($item = $this->hasproduct($Product->id,$Price->id)) !== false) {
			$this->contents[$item]->add($quantity);
		} else {
			$Item = new Item($quantity,$Product,$Price,$Shopp->Settings->get('taxes'));
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
		elseif (isset($this->contents[$item])) {
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
		global $Shopp;
		$this->contents[$item] = new Item($this->contents[$item]->quantity,$Product,$Price,$Shopp->Settings->get('taxes'));
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
		$Totals =& $this->data->Totals;
		$Totals->subtotal = 0;
		$Totals->shipping = 0;
		$Totals->tax = 0;
		$Totals->total = 0;

		foreach ($this->contents as $Item) {
			$Totals->subtotal +=  $Item->total;
			if ($Item->shipping = "on")
				$Totals->shipping += ($Item->quantity * $Item->domship);

			if ($Item->tax == "on" && $Totals->taxrate > 0)
				$Totals->tax += $Item->total * ($Totals->taxrate/100);
				
		}
		
		if ($Totals->shipping > 0 
				&& $Totals->tax > 0  
				&& $Totals->taxrate > 0)
			$Totals->tax += $Totals->shipping * ($Totals->taxrate/100);
		
		if ($Totals->tax > 0) $Totals->tax = round($Totals->tax,2);
		
		
		$Totals->total = $Totals->subtotal + 
			$Totals->shipping + $Totals->tax;		
	}
	
	function tag ($property,$options=array()) {
		global $Shopp;
		
		// Return strings with no options
		switch ($property) {
			case "url": return SHOPP_CARTURL; break;
			case "free-shipping-text": return $Shopp->Settings->get('free_shipping_text'); break;
			case "totalitems": return count($this->contents); break;
			case "hasitems": if (count($this->contents) > 0) return true; else return false; break;
			case "items":
				if (!$this->looping) {
					reset($this->contents);
					$this->looping = true;
				} else next($this->contents);
				
				if (current($this->contents)) return true;
				else {
					$this->looping = false;
					reset($this->contents);
					return false;
				}
		}
		
		$result = "";
		switch ($property) {
			case "subtotal": $result = $this->data->Totals->subtotal; break;
			case "shipping": $result = $this->data->Totals->shipping; break;
			case "tax": $result = $this->data->Totals->tax; break;
			case "total": $result = $this->data->Totals->total; break;
		}
		
		if (isset($options['currency']) && !value_is_true($options['currency'])) return $result;
		else return money($result);
		
		return false;
	}
	
	function itemtag ($property,$options=array()) {
		if ($this->looping) {
			$Item = current($this->contents);
			if ($Item !== false) {
				$id = key($this->contents);
				if ($property == "id") return $id;
				return $Item->tag($id,$property,$options);
			}
		} else return false;
	}
	
} // end Cart class

?>