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

	function Purchase ($id=false,$key=false) {
		$this->init(self::$table);
		if (!$id) return true;
		if ($this->load($id,$key)) return true;
		else return false;
	}

	function load_purchased () {
		$db = DB::get();

		$table = DatabaseObject::tablename(Purchased::$table);
		if (empty($this->id)) return false;
		$this->purchased = $db->query("SELECT * FROM $table WHERE purchase=$this->id",AS_ARRAY);
		foreach ($this->purchased as &$purchase) $purchase->data = unserialize($purchase->data);
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
				if (empty($options['format'])) $options['format'] = get_option('date_format');
				return date($options['format'],((is_int($this->created))?$this->created:mktimestamp($this->created)));
				break;
			case "card": return (!empty($this->card))?sprintf("%'X16d",$this->card):''; break;
			case "cardtype": return $this->cardtype; break;
			case "transactionid": return $this->transactionid; break;
			case "firstname": return $this->firstname; break;
			case "lastname": return $this->lastname; break;
			case "address": return $this->address; break;
			case "xaddress": return $this->xaddress; break;
			case "city": return $this->city; break;
			case "state": 
				if (strlen($this->state > 2)) return $this->state;
				$regions = $Shopp->Settings->get('zones');
				$states = $regions[$this->country];
				return $states[$this->state];
				break;
			case "postcode": return $this->postcode; break;
			case "country": 
				$countries = $Shopp->Settings->get('target_markets');
				return $countries[$this->country]; break;
			case "shipaddress": return $this->shipaddress; break;
			case "shipxaddress": return $this->shipxaddress; break;
			case "shipcity": return $this->shipcity; break;
			case "shipstate":
				if (strlen($this->shipstate > 2)) return $this->shipstate;
				$regions = $Shopp->Settings->get('zones');
				$states = $regions[$this->country];
				return $states[$this->shipstate];
				break;
			case "shippostcode": return $this->shippostcode; break;
			case "shipcountry": 
				$countries = $Shopp->Settings->get('target_markets');
				return $countries[$this->shipcountry]; break;
			case "shipmethod": return $this->shipmethod; break;
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
			case "item-id":
				$item = current($this->purchased);
				return $item->id; break;
			case "item-product":
				$item = current($this->purchased);
				return $item->product; break;
			case "item-price":
				$item = current($this->purchased);
				return $item->price; break;
			case "item-name":
				$item = current($this->purchased);
				return $item->name; break;
			case "item-description":
				$item = current($this->purchased);
				return $item->description; break;
			case "item-options":
				$item = current($this->purchased);
				return (!empty($item->optionlabel))?$options['before'].$item->optionlabel.$options['after']:''; break;
			case "item-sku":
				$item = current($this->purchased);
				return $item->sku; break;
			case "item-download":
				$item = current($this->purchased);
				if (empty($item->download)) return "";
				if (!isset($options['label'])) $options['label'] = "Download Now";
				if (isset($options['class'])) $options['class'] = ' class="'.$options['class'].'"';
				if (SHOPP_PERMALINKS) $url = $Shopp->shopuri."download/".$item->dkey;
				else $url = add_query_arg('shopp_download',$item->dkey,$Shopp->shopuri);
				return '<a href="'.$url.'"'.$options['class'].'>'.$options['label'].'</a>'; break;
			case "item-quantity":
				$item = current($this->purchased);
				return $item->quantity; break;
			case "item-unitprice":
				$item = current($this->purchased);
				return money($item->unitprice); break;
			case "item-total":
				$item = current($this->purchased);
				return money($item->total); break;
			case "item-has-inputs":
			case "item-hasinputs": 
				$item = current($this->purchased);
				return (count($item->data) > 0); break;
			case "item-inputs":
				$item = current($this->purchased);
				if (!$this->itemdataloop) {
					reset($item->data);
					$this->itemdataloop = true;
				} else next($item->data);

				if (current($item->data)) return true;
				else {
					$this->itemdataloop = false;
					return false;
				}
				break;
			case "item-input":
				$item = current($this->purchased);
				$data = current($item->data);
				$name = key($item->data);
				if (isset($options['name'])) return $name;
				return $data;
				break;
			case "has-data":
			case "hasdata": return (count($this->data) > 0); break;
			case "orderdata":
				if (!$this->dataloop) {
					reset($this->data);
					$this->dataloop = true;
				} else next($this->data);

				if (current($this->data)) return true;
				else {
					$this->dataloop = false;
					return false;
				}
				break;
			case "data":
				$data = current($this->data);
				$name = key($this->data);
				if (isset($options['name'])) return $name;
				return $data;
				break;
			case "subtotal": return money($this->subtotal); break;
			case "hasfreight": return ($this->freight > 0);
			case "freight": return money($this->freight); break;
			case "hasdiscount": return ($this->discount > 0);
			case "discount": return money($this->discount); break;
			case "hastax": return ($this->tax > 0)?true:false;
			case "tax": return money($this->tax); break;
			case "total": return money($this->total); break;
			case "status": 
				$labels = $Shopp->Settings->get('order_status');
				if (empty($labels)) $labels = array('');
				return $labels[$this->status];
				break;
				
		}
	}

} // end Purchase class

?>