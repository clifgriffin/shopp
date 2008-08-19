<?php
/**
 * Purchase class
 * Order invoice logging
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 28 March, 2008
 * @package shopp
 **/

require("Purchased.php");

class Purchase extends DatabaseObject {
	static $table = "purchase";
	var $purchased = array();

	function Purchase ($id=false) {
		$this->init(self::$table);
		if (!$id) return true;
		if ($this->load($id)) return true;
		else return false;
	}

	function load_purchased () {
		$db = DB::get();

		$table = DatabaseObject::tablename(Purchased::$table);
		if (empty($this->id)) return false;
		$this->purchased = $db->query("SELECT * FROM $table WHERE purchase=$this->id",AS_ARRAY);
		return true;
	}
	
	function copydata ($Object,$prefix="") {
		$ignores = array("_datatypes","_table","_key","_lists","id","created","modified");
		foreach(get_object_vars($Object) as $property => $value) {
			$property = $prefix.$property;
			if (property_exists($this,$property) && 
				!in_array($property,$ignores)) 
				$this->{$property} = $value;
		}
	}
	
	function tag ($property,$options=array()) {
		global $Shopp;
				
		// Return strings with no options
		switch ($property) {
			case "url": return $Shopp->link('cart'); break;
			case "id": return $this->id; break;
			case "date": 
				if (empty($options['format'])) $options['format'] = "F j, Y";
				return date($options['format'],$this->created);
				break;
			case "card": return sprintf("%'X16d",$this->card); break;
			case "cardtype": return $this->cardtype; break;
			case "transactionid": return $this->transactionid; break;
			case "firstname": return $this->firstname; break;
			case "lastname": return $this->lastname; break;
			case "label":
				if (empty($this->shipaddress)) return "Bill to";
				else return "Ship to"; break;
			case "address":
				if (empty($this->shipaddress)) return $this->address;
				else return $this->shipaddress; break;
			case "xaddress":
				if (empty($this->shipaddress)) return $this->xaddress;
				else return $this->shipxaddress; break;
			case "city":
				if (empty($this->shipaddress)) return $this->city;
				else return $this->shipcity; break;
			case "state":
				if (empty($this->shipaddress)) return $this->state;
				else return $this->shipstate; break;
			case "postcode":
				if (empty($this->shipaddress)) return $this->postcode;
				else return $this->shippostcode; break;
			case "country":
				if (empty($this->shipaddress)) return $this->country;
				else return $this->shipcountry; break;
				

			case "totalitems": return count($this->purchased); break;
			case "hasitems": if (count($this->purchased) > 0) return true; else return false; break;
			case "items":
				if (!$this->looping) {
					reset($this->purchased);
					$this->looping = true;
				} else next($this->purchased);

				if (current($this->purchased)) return true;
				else {
					$this->looping = false;
					reset($this->purchased);
					return false;
				}
			case "item-name":
				$item = current($this->purchased);
				return $item->name; break;
			case "item-options":
				$item = current($this->purchased);
				return $item->optionname; break;
			case "item-quantity":
				$item = current($this->purchased);
				return $item->quantity; break;
			case "item-unitprice":
				$item = current($this->purchased);
				return $item->unitprice; break;
			case "item-total":
				$item = current($this->purchased);
				return $item->total; break;

			case "subtotal": return money($this->subtotal); break;
			case "hasfrieght": return ($this->frieght > 0)?true:false;
			case "frieght": return money($this->frieght); break;
			case "hastax": return ($this->tax > 0)?true:false;
			case "tax": return money($this->tax); break;
			case "total": return money($this->total); break;
			


		}
	}
	
	

} // end Purchase class

?>