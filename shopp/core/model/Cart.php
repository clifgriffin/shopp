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
		$this->data->Totals->shipping = 0;
		$this->data->Totals->tax = 0;
		$this->data->Totals->taxrate = 0;
		$this->data->Totals->total = 0;
		$this->data->Shipping = false;
		$this->data->Estimates = false;

		$this->data->Order = new stdClass();
		$this->data->ShipCosts = array();
		$this->data->Purchase = false;

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
	function close () {
		return true;
	}

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
	function add ($quantity,&$Product,&$Price) {
		if (($item = $this->hasproduct($Product->id,$Price->id)) !== false) {
			$this->contents[$item]->add($quantity);
		} else {
			$Item = new Item($quantity,$Product,$Price);
			$this->contents[] = $Item;
			if ($Item->shipping) $this->data->Shipping = true;
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
	 * clear()
	 * Empties the contents of the cart */
	function clear () {
		$this->contents = array();
		return true;
	}
	
	/**
	 * change()
	 * Changes an item to a different product/price variation */
	function change ($item,$Product,$pricing) {
		// Don't change anything if everything is the same
		if ($this->contents[$item]->product == $Product->id &&
				$this->contents[$item]->price == $pricing) return true;
		$this->contents[$item] = new Item($this->contents[$item]->quantity,$Product,$pricing);
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
	
	/**
	 * shipzone()
	 * Sets the shipping address location 
	 * for calculating shipping estimates */
	function shipzone ($data) {
		$this->data->Order->Shipping = new Shipping();
		$this->data->Order->Shipping->updates($data);
		if (isset($data['region'])) 
			$this->data->Order->Shipping->region = $data['region'];
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

		if (!is_array($methods)) return 0;

		$estimate = false;
		foreach ($methods as $id => $rate) {
			$shipping = 0;

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
			
			if (!$Cart->freeshipping) {
				list($ShipCalcClass,$process) = split("::",$rate['method']);
				if ($Shopp->ShipCalcs->modules[$ShipCalcClass]) {
					$shipping += $Shopp->ShipCalcs->modules[$ShipCalcClass]->calculate($this,$rate,$column);
				}
			}

			// Calculate any product-specific shipping fee markups
			$shipflag = false;
			foreach ($this->contents as $Item) {
				if ($Item->shipping) $shipflag = true;
				if ($Item->shipfee > 0) $shipping += ($Item->quantity * $Item->shipfee);
			}
			if ($shipflag) $this->data->Shipping = true;
			else $this->data->Shipping = false;

			if (!$estimate) $estimate = $shipping;
			if ($shipping < $estimate) $estimate = $shipping;
			$rate['cost'] = $shipping;
			$ShipCosts[$rate['name']] = $rate;
		}
				
		if (!empty($this->data->Order->Shipping->shipmethod)) return $ShipCosts[$this->data->Order->Shipping->shipmethod]['cost'];
		return $estimate;
	}
	
	/**
	 * totals()
	 * Calculates subtotal, shipping, tax and 
	 * order total amounts */
	function totals () {
		$Totals = $this->data->Totals;
		$Totals->subtotal = 0;
		$Totals->shipping = 0;
		$Totals->tax = 0;
		$Totals->total = 0;

		$freeshipping = true;
		foreach ($this->contents as $key => $Item) {

			if ($Item->shipping && !$Item->freeshipping) $this->shipping[$key] = $Item;
			if (!$Item->freeshipping) $freeshipping = false;
			
			$Totals->subtotal +=  $Item->total;
			
			if ($Item->tax && $Totals->taxrate > 0)
				$Totals->tax += $Item->total * ($Totals->taxrate/100);
				
		}
		if ($Totals->tax > 0) $Totals->tax = round($Totals->tax,2);
		$this->freeshipping = $freeshipping;
		
		if ($this->data->Shipping) $Totals->shipping = $this->shipping();
		
		$Totals->total = $Totals->subtotal + 
			$Totals->shipping + $Totals->tax;		
	}
	
	function inputattrs ($options,$allowed=array()) {
		if (empty($allowed)) {
			$allowed = array("accesskey","alt","checked","class","disabled","format",
				"minlength","maxlength","readonly","required","size","src","tabindex",
				"title","value");
		}
		$string = "";
		$classes = "";
		foreach ($options as $key => $value) {
			if (!in_array($key,$allowed)) continue;
			switch($key) {
				case "class": $classes .= " $value"; break;
				case "disabled": $classes .= " disabled"; $string .= ' disabled="disabled"'; break;
				case "readonly": $classes .= " readonly"; $string .= ' readonly="readonly"'; break;
				case "required": $classes .= " required"; break;
				case "minlength": $classes .= " min$value"; break;
				case "format": $classes .= " $value"; break;
				default:
					$string .= ' '.$key.'="'.$value.'"';
			}
		}
		if (!empty($classes)) $string .= ' class="'.ltrim($classes).'"';
		return $string;
	}
	
	
	function tag ($property,$options=array()) {
		global $Shopp;
		
		// Return strings with no options
		switch ($property) {
			case "url": return $Shopp->link('cart'); break;
			case "totalitems": return count($this->contents); break;
			case "hasitems": return (count($this->contents) > 0)?true:false; break;
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
			case "function": return '<div><input type="hidden" id="cart-action" name="cart" value="true" /></div>'; break;
			case "empty-button": 
				if (empty($options['value'])) $options['value'] = "Empty Cart";
				return '<input type="submit" name="empty" id="empty-button"'.$this->inputattrs($options,$submit_attrs).' />';
				break;
			case "update-button": 
				if (empty($options['value'])) $options['value'] = "Update Subtotal";
				return '<input type="submit" name="update" id="update-button"'.$this->inputattrs($options,$submit_attrs).' />';
				break;
			case "sidecart":
				ob_start();
				include("{$Shopp->Flow->basepath}/templates/sidecart.php");		
				$content = ob_get_contents();
				ob_end_clean();
				return $content;
				break;
		}
		
		$result = "";
		switch ($property) {
			case "needs-shipped": return $this->data->Shipping; break;
			case "shipping-estimates":
				if (!$this->data->Shipping) return "";
				$base = $Shopp->Settings->get('base_operations');
				$markets = $Shopp->Settings->get('target_markets');
				foreach ($markets as $iso => $country) $countries[$iso] = $country;
				if (!empty($this->data->Order->Shipping->country)) $selected = $this->data->Order->Shipping->country;
				else $selected = $base['country'];
				$result .= '<select name="shipping[country]" id="shipping-country">';
				$result .= menuoptions($countries,$selected,true);
				$result .= '</select>';
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
				} else	$options['currency'] = "false";
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
	
	function shippingtag ($property,$options=array()) {
		global $Shopp;
		$ShipCosts =& $this->data->ShipCosts;
		$result = "";
			
		switch ($property) {
			case "hasestimates": if (count($ShipCosts) > 0) return true; else return false; break;
			case "methods":			
				if (!$this->looping) {
					reset($ShipCosts);
					$this->looping = true;
				} else next($ShipCosts);
				
				if (current($ShipCosts)) return true;
				else {
					$this->looping = false;
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
		$secureuri = str_replace("http://","https://",get_bloginfo('wpurl'));

		$select_attrs = array('title','required','class','disabled','required','size','tabindex','accesskey');
		$submit_attrs = array('title','value','disabled','tabindex','accesskey');

		switch ($property) {
			case "url": 
				// Test Mode will not require encrypted checkout
				if (strpos($gateway,"TestMode.php") !== false) $link = $Shopp->link('checkout');
				else $link = $Shopp->link('checkout',true);
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
				include("{$Shopp->path}/templates/summary.php");
				$content = ob_get_contents();
				ob_end_clean();
				return $content;
				break;
			case "firstname": 
				if (!empty($this->data->Order->Customer->firstname))
					$options['value'] = $this->data->Order->Customer->firstname; 
				return '<input type="text" name="firstname" id="firstname"'.$this->inputattrs($options).' />';
				break;
			case "lastname":
				if (!empty($this->data->Order->Customer->lastname))
					$options['value'] = $this->data->Order->Customer->lastname; 
				return '<input type="text" name="lastname" id="lastname"'.$this->inputattrs($options).' />'; 
				break;
			case "email":
				if (!empty($this->data->Order->Customer->email))
					$options['value'] = $this->data->Order->Customer->email; 
				return '<input type="text" name="email" id="email"'.$this->inputattrs($options).' />';
				break;
			case "phone": 
			if (!empty($this->data->Order->Customer->phone))
				$options['value'] = $this->data->Order->Customer->phone; 
				return '<input type="text" name="phone" id="phone"'.$this->inputattrs($options).' />'; 
				break;

			// SHIPPING TAGS
			case "shipping": return $this->data->Shipping;
			case "shipping-address": 
			if (!empty($this->data->Order->Shipping->address))
				$options['value'] = $this->data->Order->Shipping->address; 
				return '<input type="text" name="shipping[address]" id="shipping-address"'.$this->inputattrs($options).' />';
				break;
			case "shipping-xaddress":
			if (!empty($this->data->Order->Shipping->xaddress))
				$options['value'] = $this->data->Order->Shipping->xaddress; 
				return '<input type="text" name="shipping[xaddress]" id="shipping-xaddress"'.$this->inputattrs($options).' />';
				break;
			case "shipping-city":
				if (!empty($this->data->Order->Shipping->city))
					$options['value'] = $this->data->Order->Shipping->city; 
				return '<input type="text" name="shipping[city]" id="shipping-city"'.$this->inputattrs($options).' />';
				break;
			case "shipping-state":
				if (!empty($this->data->Order->Shipping->state))
					$options['selected'] = $this->data->Order->Shipping->state; 				
				$regions = $Shopp->Settings->get('zones');
				$states = $regions[$base['country']];
				$label = (!empty($options['label']))?$options['label']:'';
				$output = '<select name="shipping[state]" id="shipping-state"'.$this->inputattrs($options,$select_attrs).'>';
				$output .= '<option value="" selected="selected">'.$label.'</option>';
			 	$output .= menuoptions($states,$options['selected'],true);
				$output .= '</select>';
				return $output;
				break;
			case "shipping-postcode":
				if (!empty($this->data->Order->Shipping->postcode))
					$options['value'] = $this->data->Order->Shipping->postcode; 				
				return '<input type="text" name="shipping[postcode]" id="shipping-postcode"'.$this->inputattrs($options).' />'; break;
			case "shipping-country": 
				if (!empty($this->data->Order->Shipping->country))
					$options['selected'] = $this->data->Order->Shipping->country;
				else if (empty($options['selected'])) $options['selected'] = $base['country'];
				$output = '<select name="shipping[country]" id="shipping-country"'.$this->inputattrs($options,$select_attrs).'>';
			 	$output .= menuoptions($countries,$options['selected'],true);
				$output .= '</select>';
				return $output;
				break;
			case "same-shipping-address":
				$label = "Same shipping address";
				if (isset($options['label'])) $label = $options['label'];
				$output = '<label for="same-shipping"><input type="checkbox" name="sameshipaddress" value="on" id="same-shipping" checked="checked" /> '.$label.'</label>';
				return $output;
				break;
				
			// BILLING TAGS
			case "billing-address":
				if (!empty($this->data->Order->Billing->address))
					$options['value'] = $this->data->Order->Billing->address;			
				return '<input type="text" name="billing[address]" id="billing-address"'.$this->inputattrs($options).' />';
				break;
			case "billing-xaddress":
				if (!empty($this->data->Order->Billing->xaddress))
					$options['value'] = $this->data->Order->Billing->xaddress;			
				return '<input type="text" name="billing[xaddress]" id="billing-xaddress"'.$this->inputattrs($options).' />';
				break;
			case "billing-city":
				if (!empty($this->data->Order->Billing->city))
					$options['value'] = $this->data->Order->Billing->city;			
				return '<input type="text" name="billing[city]" id="billing-city"'.$this->inputattrs($options).' />'; 
				break;
			case "billing-state": 
				if (!empty($this->data->Order->Billing->state))
					$options['selected'] = $this->data->Order->Billing->state;			
				$regions = $Shopp->Settings->get('zones');
				$states = $regions[$base['country']];
				$label = (!empty($options['label']))?$options['label']:'';
				$output = '<select name="billing[state]" id="billing-state"'.$this->inputattrs($options,$select_attrs).'>';
				$output .= '<option value="" selected="selected">'.$label.'</option>';
			 	$output .= menuoptions($states,$options['selected'],true);
				$output .= '</select>';
				return $output;
				break;
			case "billing-postcode":
				if (!empty($this->data->Order->Billing->postcode))
					$options['value'] = $this->data->Order->Billing->postcode;			
				return '<input type="text" name="billing[postcode]" id="billing-postcode"'.$this->inputattrs($options).' />';
				break;
			case "billing-country": 
				if (!empty($this->data->Order->Billing->country))
					$options['selected'] = $this->data->Order->Billing->country;
				else if (empty($options['selected'])) $options['selected'] = $base['country'];			
				$output = '<select name="billing[country]" id="billing-country"'.$this->inputattrs($options,$select_attrs).'>';
			 	$output .= menuoptions($countries,$base['country'],true);
				$output .= '</select>';
				return $output;
				break;
			case "billing-card":
				if (!empty($this->data->Order->Billing->card))
					$options['value'] = $this->data->Order->Billing->card;			
				return '<input type="text" name="billing[card]" id="billing-card"'.$this->inputattrs($options).' />';
				break;
			case "billing-cardexpires-mm":
				if (!empty($this->data->Order->Billing->cardexpires))
					$options['value'] = date("m",$this->data->Order->Billing->cardexpires);				
				return '<input type="text" name="billing[cardexpires-mm]" id="billing-cardexpires-mm"'.$this->inputattrs($options).' />'; break;
			case "billing-cardexpires-yy": 
				if (!empty($this->data->Order->Billing->cardexpires))
					$options['value'] = date("y",$this->data->Order->Billing->cardexpires);							
				return '<input type="text" name="billing[cardexpires-yy]" id="billing-cardexpires-yy"'.$this->inputattrs($options).' />'; break;
			case "billing-cardtype":
				if (!empty($this->data->Order->Billing->cardtype))
					$options['selected'] = $this->data->Order->Billing->cardtype;	
				$cards = $Shopp->Settings->get('gateway_cardtypes');
				$label = (!empty($options['label']))?$options['label']:'';
				$output = '<select name="billing[cardtype]" id="billing-cardtype"'.$this->inputattrs($options,$select_attrs).'>';
				$output .= '<option value="" selected="selected">'.$label.'</option>';
			 	$output .= menuoptions($cards,$options['selected']);
				$output .= '</select>';
				return $output;
				break;
			case "billing-cardholder":
				if (!empty($this->data->Order->Billing->cardholder))
					$options['value'] = $this->data->Order->Billing->cardholder;			
				return '<input type="text" name="billing[cardholder]" id="billing-cardholder"'.$this->inputattrs($options).' />';
				break;
			case "billing-cvv":
				if (!empty($this->data->Order->Billing->cardholder))
					$options['value'] = $_POST['billing']['cvv'];
				return '<input type="text" name="billing[cvv]" id="billing-cvv"'.$this->inputattrs($options).' />';
				break;
			case "submit": 
				if (empty($options['value'])) $options['value'] = "Submit Order";
				return '<input type="submit" name="process" id="checkout-button"'.$this->inputattrs($options,$submit_attrs).' />'; break;
			case "confirm-button": 
				if (empty($options['value'])) $options['value'] = "Confirm Order";
				return '<input type="submit" name="confirmed" id="confirm-button"'.$this->inputattrs($options,$submit_attrs).' />'; break;
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