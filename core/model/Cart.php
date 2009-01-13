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

class Cart {

	// properties
	var $_table;
	var $session;
	var $created;
	var $modified;
	var $ip;
	var $data;
	var $contents = array();
	var $shipped = array();
	var $freeshipping = false;
	var $looping = false;
	var $runaway = 0;
	
	// methods
	
	/* Cart()
	 * Constructor that creates a new shopping Cart runtime object */
	function Cart () {
		$this->_table = DatabaseObject::tablename('cart');
		
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
		$this->data->Totals->quantity = 0;
		$this->data->Totals->discount = 0;
		$this->data->Totals->shipping = 0;
		$this->data->Totals->tax = 0;
		$this->data->Totals->taxrate = 0;
		$this->data->Totals->total = 0;

		$this->data->Shipping = false;
		$this->data->Estimates = false;
		$this->data->Order = new stdClass();
		$this->data->Order->data = array();
		$this->data->Promotions = array();
		$this->data->PromosApplied = array();
		$this->data->PromoCode = false;
		$this->data->PromoCodes = array();
		$this->data->PromoCodeResult = false;
		$this->data->Purchase = false;
		$this->data->ShipCosts = array();
		$this->data->ShippingPostcode = false;
		$this->data->Purchase = false;
		$this->data->Category = array();
		$this->data->Search = false;
		return true;
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
	function close () { return true; }

	/* load()
	 * Gets data from the session data table and loads Member 
	 * objects into the User from the loaded data. */
	function load () {
		$db = DB::get();
		
		if (is_robot()) return true;
		
		if ($result = $db->query("SELECT * FROM $this->_table WHERE session='$this->session'")) {
			$this->ip = $result->ip;
			$this->data = unserialize($result->data);
			$this->contents = unserialize($result->contents);
			$this->created = mktimestamp($result->created);
			$this->modified = mktimestamp($result->modified);
			//reset($this->contents);
			
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
		$db = DB::get();		
		if (!$db->query("DELETE FROM $this->_table WHERE session='$this->session'")) 
			trigger_error("Could not clear session data.");
		unset($this->session,$this->ip,$this->data,$this->contents);
		return true;
	}
	
	/* save() 
	 * Save the session data to our session table in the database. */
	function save () {
		global $Shopp;
		$db = DB::get();

		if (!$Shopp->Settings->unavailable) {
			$data = $db->escape(serialize($this->data));
			$contents = $db->escape(serialize($this->contents));
			if (!$db->query("UPDATE $this->_table SET ip='$this->ip',data='$data',contents='$contents',modified=now() WHERE session='$this->session'")) 
				trigger_error("Could not save session updates to the database.");
			return true;
		}
	}

	/* trash()
	 * Garbage collection routine for cleaning up old and expired
	 * sessions. */
	function trash () {
		$db = DB::get();
				
		// 1800 seconds = 30 minutes, 3600 seconds = 1 hour
		if (!$db->query("DELETE LOW_PRIORITY FROM $this->_table WHERE UNIX_TIMESTAMP(NOW())-UNIX_TIMESTAMP(modified) > 7200")) 
			trigger_error("Could not delete cached session data.");
		return true;
	}
	
	/**
	 * add()
	 * Adds a product as an item to the cart */
	function add ($quantity,&$Product,&$Price,$category) {
		$NewItem = new Item($Product,$Price,$category);
		if (($item = $this->hasitem($NewItem)) !== false) {
			$this->contents[$item]->add($quantity);
			$this->added = $this->contents[$item];
		} else {
			$NewItem->quantity($quantity);
			$this->contents[] = $NewItem;
			$this->added = $this->contents[count($this->contents)-1];
			if ($NewItem->shipping) $this->data->Shipping = true;
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
			if ($this->contents[$item]->quantity == 0) $this->remove($item);
			$this->totals();
			$this->save();
		}
		return true;
	}
	
	/**
	 * clear()
	 * Empties the contents of the cart */
	function clear () {
		$this->contents = array();
		return true;
	}
	
	/**
	 * change()
	 * Changes an item to a different product/price variation */
	function change ($item,&$Product,$pricing) {
		// Don't change anything if everything is the same
		if ($this->contents[$item]->product == $Product->id &&
				$this->contents[$item]->price == $pricing) return true;

		// If the updated product and price variation match
		// add the updated quantity of this item to the other item
		// and remove this one
		foreach ($this->contents as $id => $cartitem) {
			if ($cartitem->product == $Product->id && $cartitem->price == $pricing) {
				$this->update($id,$cartitem->quantity+$this->contents[$item]->quantity);
				$this->remove($item);
				$this->totals();
				$this->save();
				return true;
			}
		}

		// No existing item, so change this one
		$qty = $this->contents[$item]->quantity;
		$category = $this->contents[$item]->category;
		$this->contents[$item] = new Item($Product,$pricing,$category);
		$this->contents[$item]->quantity($qty);
		$this->totals();
		$this->save();
		return true;
	}
	
	/**
	 * hasitem()
	 * Determines if a specified item is already in this cart */
	function hasitem($NewItem) {
		$i = 0;
		foreach ($this->contents as $Item) {
			if ($Item->product == $NewItem->product && 
					$Item->price == $NewItem->price) return $i;
			$i++;
		}
		return false;
	}
	
	/**
	 * shipzone()
	 * Sets the shipping address location 
	 * for calculating shipping estimates */
	function shipzone ($data) {
		if (!isset($this->data->Order->Shipping))
			$this->data->Order->Shipping = new Shipping();
		$this->data->Order->Shipping->updates($data);

		if (!isset($this->data->Order->Billing))
			$this->data->Order->Billing = new Billing();
		$this->data->Order->Billing->updates($data);

		if (isset($data['region'])) {
			$this->data->Order->Shipping->region = $data['region'];
			$this->data->Order->Billing->region = $data['region'];
		}
			
		$this->totals();
	}
	
	/**
	 * shipping()
	 * Calulates shipping costs based on the contents
	 * of the cart and the currently available shipping
	 * location set with shipzone() */
	function shipping () {
		if (!$this->data->Order->Shipping) return false;
		global $Shopp;

		$ShipCosts = &$this->data->ShipCosts;
		$Shipping = $this->data->Order->Shipping;
		$base = $Shopp->Settings->get('base_operations');
		$methods = $Shopp->Settings->get('shipping_rates');
		$handling = $Shopp->Settings->get('order_shipfee');

		if (!is_array($methods)) return 0;

		$estimate = false;
		foreach ($methods as $id => $rate) {
			$shipping = 0;
			if (isset($rate['postcode-required'])) $this->data->ShippingPostcode = true;
			
			if ($this->freeshipping) {
				$ShipCosts[$rate['name']] = 0;
				return 0;
			}
			
			if ($Shipping->country == $base['country']) {
				// Use country/domestic region
				if (isset($rate[$base['country']]))	$column = $base['country'];  // Use the country rate
				else $column = $Shipping->postarea(); // Try to get domestic regional rate

			} else if (isset($rate[$Shipping->region])) {
				// Global region rate
				$column = $Shipping->region;
			} else {
				// Worldwide shipping rate, last rate entry
				end($rate);
				$column = key($rate);
			}
			
			list($ShipCalcClass,$process) = split("::",$rate['method']);
			if ($Shopp->ShipCalcs->modules[$ShipCalcClass]) {
				$shipping += $Shopp->ShipCalcs->modules[$ShipCalcClass]->calculate($this,$rate,$column);
			}

			// Calculate any product-specific shipping fee markups
			$shipflag = false;
			foreach ($this->contents as $Item) {
				if ($Item->shipping) $shipflag = true;
				if ($Item->shipfee > 0) $shipping += ($Item->quantity * $Item->shipfee);
			}
			if ($shipflag) $this->data->Shipping = true;
			else $this->data->Shipping = false;
			
			// Add order handling fee
			if ($handling > 0) $shipping += $handling;
			
			if (!$estimate) $estimate = $shipping;
			if ($shipping < $estimate) $estimate = $shipping;
			$rate['cost'] = $shipping;
			$ShipCosts[$rate['name']] = $rate;
		}
				
		if (!empty($this->data->Order->Shipping->shipmethod)) 
			return $ShipCosts[$this->data->Order->Shipping->shipmethod]['cost'];
		return $estimate;
	}
	
	function promotions () {
		$db = DB::get();
		
		
		// Load promotions if they've not yet been loaded
		if (empty($this->data->Promotions) || true) {
			$promo_table = DatabaseObject::tablename(Promotion::$table);
			// Add date-based lookup too
			$this->data->Promotions = $db->query("SELECT * FROM $promo_table WHERE scope='Order' AND ((status='enabled' AND UNIX_TIMESTAMP(starts) > 0 AND UNIX_TIMESTAMP(starts) < UNIX_TIMESTAMP() AND UNIX_TIMESTAMP(ends) > UNIX_TIMESTAMP()) OR status='enabled')",AS_ARRAY);
		}

		$PromoCodeFound = false; $PromoCodeExists = false;
		$this->data->PromosApplied = array();
		foreach ($this->data->Promotions as &$promo) {
			if (!is_array($promo->rules))
				$promo->rules = unserialize($promo->rules);
			
			// Add quantity rule automatically for buy x get y promos
			if ($promo->type == "Buy X Get Y Free") {
				$promo->search = "all";
				if ($promo->rules[count($promo->rules)-1]['property'] != "Item quantity") {
					$qtyrule = array(
						'property' => 'Item quantity',
						'logic' => "Is greater than",
						'value' => $promo->buyqty);
					$promo->rules[] = $qtyrule;
				}
			}
			
			$items = array();
			
			$match = false;
			$rulematches = 0;
			foreach ($promo->rules as $rule) {
				$rulematch = false;
				switch($rule['property']) {
					case "Item name": 
						foreach ($this->contents as &$Item) {
							if (Promotion::match_rule($Item->name,$rule['logic'],$rule['value'])) {
								$items[] = &$Item;
								$rulematch = true;
							}
						}
						break;
					case "Item quantity":
						foreach ($this->contents as &$Item) {
							if (Promotion::match_rule($Item->quantity,$rule['logic'],$rule['value'])) {
								$items[] = &$Item;
								$rulematch = true;
							}
						}
						break;
					case "Total quantity":
						if (Promotion::match_rule($this->data->Totals->quantity,$rule['logic'],$rule['value'])) {
							$rulematch = true;
						}
						break;
					case "Shipping amount": 
						if (Promotion::match_rule($this->data->Totals->shipping,$rule['logic'],$rule['value'])) {
							$rulematch = true;
						}
						break;
					case "Subtotal amount": 
						if (Promotion::match_rule($this->data->Totals->subtotal,$rule['logic'],$rule['value'])) {
							$rulematch = true;
						}
						break;
					case "Promo code":
						if (is_array($this->data->PromoCodes) && in_array($rule['value'],$this->data->PromoCodes)) {							
							$rulematch = true;
							break;
						}
						if (!empty($this->data->PromoCode)) {
							if (Promotion::match_rule($this->data->PromoCode,$rule['logic'],$rule['value'])) {
 								if (is_array($this->data->PromoCodes) && 
									!in_array($this->data->PromoCode, $this->data->PromoCodes)) {
									$this->data->PromoCodes[] = $this->data->PromoCode;
									$PromoCodeFound = $this->data->PromoCode;
								} else $PromoCodeExists = true;
								$this->data->PromoCode = false;
								$rulematch = true;
							}
						}
						break;
				}
				
				if ($rulematch && $promo->search == "all") $rulematches++;
				if ($rulematch && $promo->search == "any") {
					$match = true;
					break; // One matched, no need to match any more
				}
			} // end foreach ($promo->rules)

			if ($promo->search == "all" && $rulematches == count($promo->rules))
				$match = true;
				
			// Everything matches up, apply the promotion
			if ($match) {

				if (!empty($items)) {
					$freeshipping = 0;
					// Apply promo calculation to specific cart items
					foreach ($items as $item) {
						switch ($promo->type) {
							case "Percentage Off": $this->data->Totals->discount += $item->unitprice*($promo->discount/100); break;
							case "Amount Off": $this->data->Totals->discount += $promo->discount; break;
							case "Buy X Get Y Free": $this->data->Totals->discount += floor($item->quantity / ($promo->buyqty + $promo->getqty))*($item->unitprice);
							case "Free Shipping": $freeshipping++; break;
						}
					}
					if ($freeshipping == count($this->contents) || $promo->scope == "Order") $this->freeshipping = true;
					else $this->freeshipping = false;
				} else {
					// Apply promo calculation to entire order
					switch ($promo->type) {
						case "Percentage Off": $this->data->Totals->discount += $this->data->Totals->subtotal*($promo->discount/100); break;
						case "Amount Off": $this->data->Totals->discount += $promo->discount; break;
						case "Free Shipping": $this->freeshipping = true; break;
					}
				}
				$this->data->PromosApplied[] = $promo;
			}
			
			if ($match && $promo->exclusive == "on") break;
			
		} // end foreach ($Promotions)
		
		if (!empty($this->data->PromoCode) && !$PromoCodeFound && !$PromoCodeExists) {
			$this->data->PromoCodeResult = $this->data->PromoCode.__(" is not a valid code.","Shopp");
			$this->data->PromoCode = false;
		}
		
	}
	
	/**
	 * totals()
	 * Calculates subtotal, shipping, tax and 
	 * order total amounts */
	function totals () {
		$Totals =& $this->data->Totals;
		$Totals->quantity = 0;
		$Totals->subtotal = 0;
		$Totals->discount = 0;
		$Totals->shipping = 0;
		$Totals->tax = 0;
		$Totals->total = 0;

		$freeshipping = true;	// Assume free shipping unless proven wrong
		foreach ($this->contents as $key => $Item) {

			// Add the item to the shipped list
			if ($Item->shipping && !$Item->freeshipping) $this->shipped[$key] = $Item;
			if (!$Item->freeshipping) $freeshipping = false;
			
			$Totals->quantity += $Item->quantity;
			$Totals->subtotal +=  $Item->total;

			if ($Item->tax && $Totals->taxrate > 0)
				$Totals->tax += $Item->total * ($Totals->taxrate/100);
				
		}
		if ($Totals->tax > 0) $Totals->tax = round($Totals->tax,2);
		$this->freeshipping = $freeshipping;
		

		$this->promotions();
		$discount = ($Totals->discount > $Totals->subtotal)?$Totals->subtotal:$Totals->discount;
		
		if ($this->data->Shipping) $Totals->shipping = $this->shipping();

		$Totals->total = $Totals->subtotal - $discount + 
			$Totals->shipping + $Totals->tax;
	}
	
	function tag ($property,$options=array()) {
		global $Shopp;
		
		// Return strings with no options
		switch ($property) {
			case "url": return $Shopp->link('cart'); break;
			case "hasitems": return (count($this->contents) > 0); break;
			case "totalitems": return $this->data->Totals->quantity; break;
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
			case "totalpromos": return count($this->data->PromosApplied); break;
			case "haspromos": return (count($this->data->PromosApplied) > 0); break;
			case "promos":
				if (!$this->looping) {
					reset($this->data->PromosApplied);
					$this->looping = true;
				} else next($this->data->PromosApplied);

				if (current($this->data->PromosApplied)) return true;
				else {
					$this->looping = false;
					reset($this->data->PromosApplied);
					return false;
				}
			case "promo-name":
				$promo = current($this->data->PromosApplied);
				return $promo->name;
				break;
			case "promo-discount":
				$promo = current($this->data->PromosApplied);
				if (empty($options['label'])) $options['label'] = "Off!";
				if (!empty($options['before'])) $string = $options['before'];
				switch($promo->type) {
					case "Free Shipping": $string .= $Shopp->Settings->get('free_shipping_text');
					case "Percentage Off": $string .= percentage($promo->discount)." ".$options['label'];
					case "Amount Off": $string .= money($promo->discount)." ".$options['label'];
					case "Buy X Get Y Free": return "";
				}
				if (!empty($options['after'])) $string = $options['after'];
				return $string;
				
				break;
			case "function": return '<div class="hidden"><input type="hidden" id="cart-action" name="cart" value="true" /></div><input type="submit" name="update" id="hidden-update" />'; break;
			case "empty-button": 
				if (!isset($options['value'])) $options['value'] = "Empty Cart";
				return '<input type="submit" name="empty" id="empty-button"'.inputattrs($options,$submit_attrs).' />';
				break;
			case "update-button": 
				if (!isset($options['value'])) $options['value'] = "Update Subtotal";
				return '<input type="submit" name="update" class="update-button"'.inputattrs($options,$submit_attrs).' />';
				break;
			case "sidecart":
				ob_start();
				include(SHOPP_TEMPLATES."/sidecart.php");		
				$content = ob_get_contents();
				ob_end_clean();
				return $content;
				break;
			case "hasdiscount": return ($this->data->Totals->discount > 0); break;
			case "discount": return money($this->data->Totals->discount); break;
		}
		
		$result = "";
		switch ($property) {
			case "promo-code": 
				if (!isset($options['value'])) $options['value'] = __("Apply Promo Code");
				$result .= '<ul><li>';
				
				if ($this->data->PromoCodeResult !== false)
					$result .= '<p class="error">'.$this->data->PromoCodeResult.'</p>';
				$result .= '<span><input type="text" id="promocode" name="promocode" value="" size="10" /></span>';
				$result .= '<span><input type="submit" id="apply-code" name="update"'.inputattrs($options,$submit_attrs).' /></span>';
				$result .= '</li></ul>';
				return $result;
			case "has-shipping-methods": 
				return (count($this->data->ShipCosts) > 1 &&
						$this->data->Totals->shipping > 0 &&
						$this->data->Shipping); break;				
			case "needs-shipped": return $this->data->Shipping; break;
			case "hasshipcosts": return ($this->data->Totals->shipping > 0); break;
			case "needs-shipping-estimates":
				$markets = $Shopp->Settings->get('target_markets');
				return ($this->data->Shipping && count($markets) > 1);
				break;
			case "shipping-estimates":
				if (!$this->data->Shipping) return "";
				$base = $Shopp->Settings->get('base_operations');
				$markets = $Shopp->Settings->get('target_markets');
				if (empty($markets)) return "";
				foreach ($markets as $iso => $country) $countries[$iso] = $country;
				if (!empty($this->data->Order->Shipping->country)) $selected = $this->data->Order->Shipping->country;
				else $selected = $base['country'];
				$result .= '<ul><li>';
				if (value_is_true($options['postcode']) || $this->data->ShippingPostcode) {
					$result .= '<span>';
					$result .= '<input name="shipping[postcode]" id="shipping-postcode" size="6" value="'.$this->data->Order->Shipping->postcode.'" />&nbsp;';
					$result .= '</span>';
				}
				$result .= '<span>';
				$result .= '<select name="shipping[country]" id="shipping-country">';
				$result .= menuoptions($countries,$selected,true);
				$result .= '</select>';
				$result .= '</span>';
				$result .= '</li></ul>';
				return $result;
				break;
		}
		
		$result = "";
		switch ($property) {
			case "subtotal": $result = $this->data->Totals->subtotal; break;
			case "shipping": 
				if (!$this->data->Shipping) return "";
				if (isset($options['label'])) {
					$options['currency'] = "false";
					if ($this->data->Totals->shipping > 0) $result = $options['label'];
					else $result = $Shopp->Settings->get('free_shipping_text');
				} else $result = $this->data->Totals->shipping;
				break;
			case "tax": 
				if ($this->data->Totals->tax > 0) {
					if (isset($options['label'])) {
						$options['currency'] = "false";
						$result = $options['label'];
					} else $result = $this->data->Totals->tax;
				} else $options['currency'] = "false";
				break;
			case "total": 
				$result = $this->data->Totals->total; 
				break;
		}
		
		if (isset($options['currency']) && !value_is_true($options['currency'])) return $result;
		else return '<span id="'.$property.'">'.money($result).'</span>';
		
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


	/**
	 * shippingtag()
	 * shopp('shipping','...')
	 * Used primarily in the summary.php template
	 **/
	function shippingtag ($property,$options=array()) {
		global $Shopp;
		$ShipCosts =& $this->data->ShipCosts;
		$result = "";
		
		switch ($property) {
			case "hasestimates": return (count($ShipCosts) > 0); break;
			case "methods":			
				if (!$this->sclooping) {
					reset($ShipCosts);
					$this->sclooping = true;
				} else next($ShipCosts);
				
				if (current($ShipCosts)) return true;
				else {
					$this->sclooping = false;
					reset($ShipCosts);
					return false;
				}
				break;
			case "method-name": 
				return key($ShipCosts);
				break;
			case "method-cost": 
				$method = current($ShipCosts);
				return money($method['cost']);
				break;
			case "method-selector":
				$method = current($ShipCosts);
	
				$checked = '';
				if ($this->data->Order->Shipping->shipmethod == $method['name'] ||
					($method['cost'] == $this->data->Totals->shipping))
						$checked = ' checked="checked"';
	
				$result .= '<input type="radio" name="shipmethod" value="'.$method['name'].'" '.$id.' class="shipmethod" '.$checked.' />';
				return $result;
				
				break;
			case "method-delivery":
				$periods = array("h"=>3600,"d"=>86400,"w"=>604800,"m"=>2592000);
				$method = current($ShipCosts);
				$estimates = split("-",$method['delivery']);
				$format = get_option('date_format');
				if ($estimates[0] == $estimates[1]) $estimates = array($estimates[0]);
				$result = "";
				for ($i = 0; $i < count($estimates); $i++){
					list($interval,$p) = sscanf($estimates[$i],'%d%s');
					if (!empty($result)) $result .= "&mdash;";
					$result .= date($format,mktime()+($interval*$periods[$p]));
				}				
				return $result;
		}
	}
	
	function checkouttag ($property,$options=array()) {
		global $Shopp;
		$gateway = $Shopp->Settings->get('payment_gateway');
		$pages = $Shopp->Settings->get('pages');
		$base = $Shopp->Settings->get('base_operations');
		$countries = $Shopp->Settings->get('target_markets');

		$select_attrs = array('title','required','class','disabled','required','size','tabindex','accesskey');
		$submit_attrs = array('title','value','disabled','tabindex','accesskey');

		switch ($property) {
			case "url": 
				$ssl = true;
				// Test Mode will not require encrypted checkout
				if (strpos($gateway,"TestMode.php") !== false || 
					$_GET['shopp_xco'] == "PayPal/PayPalExpress") $ssl = false;
				$link = $Shopp->link('checkout',$ssl);
				$query = $_SERVER['QUERY_STRING'];
				if (SHOPP_PERMALINKS && !empty($query)) $query = "?$query";
				return $link.$query;
				break;
			case "function":
				$regions = $Shopp->Settings->get('zones');
				$base = $Shopp->Settings->get('base_operations');
				$output = '<script type="text/javascript">'."\n";
				$output .= '//<![CDATA['."\n";
				$output .= 'var currencyFormat = '.json_encode($base['currency']['format']).';'."\n";
				$output .= 'var regions = '.json_encode($regions).';'."\n";
				$output .= '//]]>'."\n";
				$output .= '</script>'."\n";
				if (!empty($options['value'])) $value = $options['value'];
				else $value = "process";
				$output .= '<div><input type="hidden" name="checkout" value="'.$value.'" /></div>'; 
				return $output;
				break;
			case "error":
				if (isset($options['show']) && $options['show'] == "code") return $this->data->OrderError->code;
				return $this->data->OrderError->message;
				break;
			case "cart-summary":
				ob_start();
				include(SHOPP_TEMPLATES."/summary.php");
				$content = ob_get_contents();
				ob_end_clean();
				return $content;
				break;
			case "loggedin": return $this->data->login; break;
			case "notloggedin": return (!$this->data->login && $Shopp->Settings->get('account_system') != "none"); break;
			case "loginname-login": 
				if (!empty($_POST['loginname-login']))
					$options['value'] = $_POST['loginname-login']; 
				return '<input type="text" name="loginname-login" id="loginname-login"'.inputattrs($options).' />';
				break;
			case "email-login": 
				if (!empty($_POST['email-login']))
					$options['value'] = $_POST['email-login']; 
				return '<input type="text" name="email-login" id="email-login"'.inputattrs($options).' />';
				break;
			case "password-login": 
				if (!empty($_POST['password-login']))
					$options['value'] = $_POST['password-login']; 
				return '<input type="password" name="password-login" id="password-login"'.inputattrs($options).' />';
				break;
			case "submit-login": // Deprecating
			case "login-button":
				$string = '<input type="hidden" name="process-login" id="process-login" value="false" />';
				$string .= '<input type="button" name="submit-login" id="submit-login"'.inputattrs($options).' />';
				return $string;
				break;

			case "firstname": 
				if (!empty($this->data->Order->Customer->firstname))
					$options['value'] = $this->data->Order->Customer->firstname; 
				return '<input type="text" name="firstname" id="firstname"'.inputattrs($options).' />';
				break;
			case "lastname":
				if (!empty($this->data->Order->Customer->lastname))
					$options['value'] = $this->data->Order->Customer->lastname; 
				return '<input type="text" name="lastname" id="lastname"'.inputattrs($options).' />'; 
				break;
			case "email":
				if (!empty($this->data->Order->Customer->email))
					$options['value'] = $this->data->Order->Customer->email; 
				return '<input type="text" name="email" id="email"'.inputattrs($options).' />';
				break;
			case "loginname":
				if (!empty($this->data->Order->Customer->login))
					$options['value'] = $this->data->Order->Customer->login; 
				return '<input type="text" name="login" id="login"'.inputattrs($options).' />';
				break;
			case "password":
				if (!empty($this->data->Order->Customer->password))
					$options['value'] = $this->data->Order->Customer->password; 
				return '<input type="password" name="password" id="password"'.inputattrs($options).' />';
				break;
			case "confirm-password":
				if (!empty($this->data->Order->Customer->confirm_password))
					$options['value'] = $this->data->Order->Customer->confirm_password; 
				return '<input type="password" name="confirm-password" id="confirm-password"'.inputattrs($options).' />';
				break;
			case "phone": 
			if (!empty($this->data->Order->Customer->phone))
				$options['value'] = $this->data->Order->Customer->phone; 
				return '<input type="text" name="phone" id="phone"'.inputattrs($options).' />'; 
				break;
			case "customer-info":
				$allowed_types = array("text","password","hidden","checkbox","radio");
				if (empty($options['type'])) $options['type'] = "hidden";
				if (isset($options['name']) && in_array($options['type'],$allowed_types)) {
					if (isset($this->data->Order->Customer->info[$options['name']])) 
						$options['value'] = $this->data->Order->Customer->info[$options['name']]; 
					return '<input type="text" name="info['.$options['name'].']" id="customer-info-'.$options['name'].'"'.inputattrs($options).' />'; 
				}
				break;

			// SHIPPING TAGS
			case "shipping": return $this->data->Shipping;
			case "shipping-address": 
			if (!empty($this->data->Order->Shipping->address))
				$options['value'] = $this->data->Order->Shipping->address; 
				return '<input type="text" name="shipping[address]" id="shipping-address"'.inputattrs($options).' />';
				break;
			case "shipping-xaddress":
			if (!empty($this->data->Order->Shipping->xaddress))
				$options['value'] = $this->data->Order->Shipping->xaddress; 
				return '<input type="text" name="shipping[xaddress]" id="shipping-xaddress"'.inputattrs($options).' />';
				break;
			case "shipping-city":
				if (!empty($this->data->Order->Shipping->city))
					$options['value'] = $this->data->Order->Shipping->city; 
				return '<input type="text" name="shipping[city]" id="shipping-city"'.inputattrs($options).' />';
				break;
			case "shipping-province":
			case "shipping-state":
				if (!empty($this->data->Order->Shipping->state)) {
					$options['selected'] = $this->data->Order->Shipping->state;
					$options['value'] = $this->data->Order->Shipping->state;
				}
				if (!empty($this->data->Order->Shipping->country))
					$selectedCountry = $this->data->Order->Shipping->country;
				else $selectedCountry = $base['country'];
				if (empty($options['type'])) $options['type'] = "menu";
				$regions = $Shopp->Settings->get('zones');
				$states = $regions[$selectedCountry];
				if (is_array($states) && $options['type'] == "menu") {
					$label = (!empty($options['label']))?$options['label']:'';
					$output = '<select name="shipping[state]" id="shipping-state"'.inputattrs($options,$select_attrs).'>';
					$output .= '<option value="" selected="selected">'.$label.'</option>';
				 	$output .= menuoptions($states,$options['selected'],true);
					$output .= '</select>';
				} else $output .= '<input type="text" name="shipping[state]" id="shipping-state" '.inputattrs($options).'/>';
				return $output;
				break;
			case "shipping-postcode":
				if (!empty($this->data->Order->Shipping->postcode))
					$options['value'] = $this->data->Order->Shipping->postcode; 				
				return '<input type="text" name="shipping[postcode]" id="shipping-postcode"'.inputattrs($options).' />'; break;
			case "shipping-country": 
				if (!empty($this->data->Order->Shipping->country))
					$options['selected'] = $this->data->Order->Shipping->country;
				else if (empty($options['selected'])) $options['selected'] = $base['country'];
				$output = '<select name="shipping[country]" id="shipping-country"'.inputattrs($options,$select_attrs).'>';
			 	$output .= menuoptions($countries,$options['selected'],true);
				$output .= '</select>';
				return $output;
				break;
			case "same-shipping-address":
				$label = __("Same shipping address");
				if (isset($options['label'])) $label = $options['label'];
				$checked = ' checked="checked"';
				if (isset($options['checked']) && !value_is_true($options['checked'])) $checked = '';
				$output = '<label for="same-shipping"><input type="checkbox" name="sameshipaddress" value="on" id="same-shipping" '.$checked.' /> '.$label.'</label>';
				return $output;
				break;
				
			// BILLING TAGS
			case "billing-address":
				if (!empty($this->data->Order->Billing->address))
					$options['value'] = $this->data->Order->Billing->address;			
				return '<input type="text" name="billing[address]" id="billing-address"'.inputattrs($options).' />';
				break;
			case "billing-xaddress":
				if (!empty($this->data->Order->Billing->xaddress))
					$options['value'] = $this->data->Order->Billing->xaddress;			
				return '<input type="text" name="billing[xaddress]" id="billing-xaddress"'.inputattrs($options).' />';
				break;
			case "billing-city":
				if (!empty($this->data->Order->Billing->city))
					$options['value'] = $this->data->Order->Billing->city;			
				return '<input type="text" name="billing[city]" id="billing-city"'.inputattrs($options).' />'; 
				break;
			case "billing-province": 
			case "billing-state": 
				if (!empty($this->data->Order->Billing->state)) {
					$options['selected'] = $this->data->Order->Billing->state;
					$options['value'] = $this->data->Order->Billing->state;
				}
				if (empty($options['type'])) $options['type'] = "menu";
				
				$regions = $Shopp->Settings->get('zones');
				$states = $regions[$base['country']];
				if (is_array($states) && $options['type'] == "menu") {
					$label = (!empty($options['label']))?$options['label']:'';
					$output = '<select name="billing[state]" id="billing-state"'.inputattrs($options,$select_attrs).'>';
					$output .= '<option value="" selected="selected">'.$label.'</option>';
				 	$output .= menuoptions($states,$options['selected'],true);
					$output .= '</select>';
				} else $output .= '<input type="text" name="billing[state]" id="billing-state" '.inputattrs($options).'/>';
				return $output;
				break;
			case "billing-postcode":
				if (!empty($this->data->Order->Billing->postcode))
					$options['value'] = $this->data->Order->Billing->postcode;			
				return '<input type="text" name="billing[postcode]" id="billing-postcode"'.inputattrs($options).' />';
				break;
			case "billing-country": 
				if (!empty($this->data->Order->Billing->country))
					$options['selected'] = $this->data->Order->Billing->country;
				else if (empty($options['selected'])) $options['selected'] = $base['country'];			
				$output = '<select name="billing[country]" id="billing-country"'.inputattrs($options,$select_attrs).'>';
			 	$output .= menuoptions($countries,$options['selected'],true);
				$output .= '</select>';
				return $output;
				break;
			case "billing-card":
				if (!empty($this->data->Order->Billing->card)) {
					$options['value'] = $this->data->Order->Billing->card;
					$this->data->Order->Billing->card = "";
				}
				return '<input type="text" name="billing[card]" id="billing-card"'.inputattrs($options).' />';
				break;
			case "billing-cardexpires-mm":
				if (!empty($this->data->Order->Billing->cardexpires))
					$options['value'] = date("m",$this->data->Order->Billing->cardexpires);				
				return '<input type="text" name="billing[cardexpires-mm]" id="billing-cardexpires-mm"'.inputattrs($options).' />'; break;
			case "billing-cardexpires-yy": 
				if (!empty($this->data->Order->Billing->cardexpires))
					$options['value'] = date("y",$this->data->Order->Billing->cardexpires);							
				return '<input type="text" name="billing[cardexpires-yy]" id="billing-cardexpires-yy"'.inputattrs($options).' />'; break;
			case "billing-cardtype":
				if (!empty($this->data->Order->Billing->cardtype))
					$options['selected'] = $this->data->Order->Billing->cardtype;	
				$cards = $Shopp->Settings->get('gateway_cardtypes');
				$label = (!empty($options['label']))?$options['label']:'';
				$output = '<select name="billing[cardtype]" id="billing-cardtype"'.inputattrs($options,$select_attrs).'>';
				$output .= '<option value="" selected="selected">'.$label.'</option>';
			 	$output .= menuoptions($cards,$options['selected']);
				$output .= '</select>';
				return $output;
				break;
			case "billing-cardholder":
				if (!empty($this->data->Order->Billing->cardholder))
					$options['value'] = $this->data->Order->Billing->cardholder;			
				return '<input type="text" name="billing[cardholder]" id="billing-cardholder"'.inputattrs($options).' />';
				break;
			case "billing-cvv":
				if (!empty($this->data->Order->Billing->cardholder))
					$options['value'] = $_POST['billing']['cvv'];
				return '<input type="text" name="billing[cvv]" id="billing-cvv"'.inputattrs($options).' />';
				break;
				
			case "order-data":
				$allowed_types = array("text","hidden",'password','checkbox','radio');
				if (empty($options['type'])) $options['type'] = "hidden";
				if (isset($options['name']) && in_array($options['type'],$allowed_types)) {
					if (isset($this->data->Order->data[$options['name']])) 
						$options['value'] = $this->data->Order->data[$options['name']];
					return '<input type="'.$options['type'].'" name="data['.$options['name'].']" id="order-data-'.$options['name'].'"'.inputattrs($options).' />'; 
				}
				break;

			case "submit": 
				if (!isset($options['value'])) $options['value'] = "Submit Order";
				return '<input type="submit" name="process" id="checkout-button"'.inputattrs($options,$submit_attrs).' />'; break;
			case "confirm-button": 
				if (!isset($options['value'])) $options['value'] = "Confirm Order";
				return '<input type="submit" name="confirmed" id="confirm-button"'.inputattrs($options,$submit_attrs).' />'; break;
			case "local-payment": 
				$gateway = $Shopp->Settings->get('payment_gateway'); 
				return (!empty($gateway)); break;
			case "xco-buttons": 
				$gateways = array();
				$PPX = $Shopp->Settings->get('PayPalExpress');
				if ($PPX['enabled'] == "on") $gateways[] = "{$Shopp->path}/gateways/PayPal/PayPalExpress.php";
				$GC = $Shopp->Settings->get('GoogleCheckout');
				if ($GC['enabled'] == "on") $gateways[] = "{$Shopp->path}/gateways/GoogleCheckout/GoogleCheckout.php";

				if (!empty($gateways)) {
					foreach ($gateways as $gateway) {
						$gateway_meta = $Shopp->Flow->scan_gateway_meta($gateway);
						$ProcessorClass = $gateway_meta->tags['class'];
						if (!empty($ProcessorClass)) {
							include_once($gateway);					
							$Payment = new $ProcessorClass();
							$button .= $Payment->tag('button');
						}
					}
					return $button;
				}

				break;
		}
	}
		
} // end Cart class

?>