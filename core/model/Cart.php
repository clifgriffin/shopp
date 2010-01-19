<?php
/**
 * Cart.php
 * 
 * The shopping cart system
 *
 * @author Jonathan Davis
 * @version 1.1
 * @copyright Ingenesis Limited, January 19, 2010
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @subpackage cart
 **/

require("Item.php");

/**
 * 
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package shopp
 **/
class Cart {

	// properties
	var $contents = array();	// The contents (Items) of the cart 
	var $shipped = array();		// Reference list of shipped Items
	var $downloads = array();	// Reference list of digital Items
	var $discounts = array();	// List of promotional discounts applied
	var $promocodes = array();	// List of promotional codes applied
	
	// Object properties
	var $Added = false;			// Last Item added
	var $Totals = false;		// Cart Totals data structure
	
	var $freeship = false;
	var $showpostcode = false;	// Flag to show postcode field in shipping estimator
	
	// Internal properties
	var $changed = false;		// Flag when Cart updates and needs retotaled
	
	var $looping = false;
	var $itemlooping = false;
	var $runaway = 0;
	var $retotal = false;
	var $handlers = false;
	
	// methods
	
	/**
	 * Cart constructor
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @return void
	 **/
	function __construct () {
		$this->Totals = new CartTotals();	// Initialize aggregate total data
		$this->listeners();					// Establish our command listeners
	}
	
	/**
	 * Restablish listeners after being loaded from the session
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function __wakeup () {
		$this->listeners();
	}
	
	/**
	 * Listen for events to trigger cart functionality
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function listeners () {
		add_action('parse_request',array(&$this,'totals'),99);
		add_action('shopp_cart_request',array(&$this,'request'));
		add_action('shopp_session_reset',array(&$this,'clear'));
	}
	
	/**
	 * Processes cart requests and updates the cart data
	 * 
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @return void
	 **/
	function request () {
		global $Shopp;

		if (isset($_REQUEST['checkout'])) shopp_redirect($Shopp->link('checkout',true));
		
		if (isset($_REQUEST['shopping'])) shopp_redirect($Shopp->link('catalog'));
		
		if (isset($_REQUEST['shipping'])) {
			if (!empty($_REQUEST['shipping']['postcode'])) // Protect input field from XSS
				$_REQUEST['shipping']['postcode'] = esc_attr($_REQUEST['shipping']['postcode']);
				
			do_action_ref_array('shopp_update_destination',array($_REQUEST['shipping']));
			if (!empty($_REQUEST['shipping']['country']) || !empty($_REQUEST['shipping']['postcode']))
				$this->changed(true);
		}

		if (!empty($_REQUEST['promocode'])) {
			$this->promocode = esc_attr($_REQUEST['promocode']);
			$this->changed(true);
		}
		
		if (!isset($_REQUEST['cart'])) $_REQUEST['cart'] = false;
		if (isset($_REQUEST['remove'])) $_REQUEST['cart'] = "remove";
		if (isset($_REQUEST['update'])) $_REQUEST['cart'] = "update";
		if (isset($_REQUEST['empty'])) $_REQUEST['cart'] = "empty";

		if (!isset($_REQUEST['quantity'])) $_REQUEST['quantity'] = 1;

		switch($_REQUEST['cart']) {
			case "add":			
				if (isset($_REQUEST['product'])) {
					
					$quantity = (empty($product['quantity']) && 
						$product['quantity'] !== 0)?1:$product['quantity']; // Add 1 by default
					$Product = new Product($_REQUEST['product']);
					$pricing = false;
					if (!empty($_REQUEST['options']) && !empty($_REQUEST['options'][0])) 
						$pricing = $_REQUEST['options'];
					else $pricing = $_REQUEST['price'];

					$category = false;
					if (!empty($_REQUEST['category'])) $category = $_REQUEST['category'];
					
					if (isset($_REQUEST['data'])) $data = $_REQUEST['data'];
					else $data = array();

					if (isset($_REQUEST['item'])) $result = $this->change($_REQUEST['item'],$Product,$pricing);
					else $result = $this->add($quantity,$Product,$pricing,$category,$data);
					
				}
				
				if (isset($_REQUEST['products']) && is_array($_REQUEST['products'])) {
					foreach ($_REQUEST['products'] as $id => $product) {
						$quantity = (empty($product['quantity']) && 
							$product['quantity'] !== 0)?1:$product['quantity']; // Add 1 by default
						$Product = new Product($product['product']);
						$pricing = false;
						if (!empty($product['options']) && !empty($product['options'][0])) 
							$pricing = $product['options'];
						elseif (isset($product['price'])) $pricing = $product['price'];
						
						$category = false;
						if (!empty($product['category'])) $category = $product['category'];

						if (!empty($product['data'])) $data = $product['data'];
						else $data = array();

						if (!empty($Product->id)) {
							if (isset($product['item'])) $result = $this->change($product['item'],$Product,$pricing);
							else $result = $this->add($quantity,$Product,$pricing,$category,$data);
						}
					}
					
				}
				break;
			case "remove":
				if (!empty($this->contents)) $this->remove(current($_REQUEST['remove']));
				break;
			case "empty":
				$this->clear();
				break;
			default:
				if (isset($_REQUEST['item']) && isset($_REQUEST['quantity'])) {
					$this->update($_REQUEST['item'],$_REQUEST['quantity']);
				} elseif (!empty($_REQUEST['items'])) {
					foreach ($_REQUEST['items'] as $id => $item) {
						if (isset($item['quantity'])) {
							$item['quantity'] = ceil(preg_replace('/[^\d\.]+/','',$item['quantity']));
							if (!empty($item['quantity'])) $this->update($id,$item['quantity']);
						    if (isset($_REQUEST['remove'][$id])) $this->remove($_REQUEST['remove'][$id]);
						}
						if (isset($item['product']) && isset($item['price']) && 
							$item['product'] == $this->contents[$id]->product &&
							$item['price'] != $this->contents[$id]->price) {
							$Product = new Product($item['product']);
							$this->change($id,$Product,$item['price']);
						}
					}
				}
		}

		do_action('shopp_cart_updated',$this);
	}

	/**
	 * Responds to AJAX-based cart requests
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @return string JSON response
	 **/
	function ajax () { 
		global $Shopp;
		
		if ($_REQUEST['response'] == "html") {
			echo $this->tag('sidecart');
			exit();
		}
		$AjaxCart = new StdClass();
		$AjaxCart->url = $Shopp->link('cart');
		$AjaxCart->Totals = clone($this->Totals);
		$AjaxCart->Contents = array();
		foreach($this->contents as $Item) {
			$CartItem = clone($Item);
			unset($CartItem->options);
			$AjaxCart->Contents[] = $CartItem;
		}
		if (isset($this->added))
			$AjaxCart->Item = clone($this->Added);
		else $AjaxCart->Item = new Item();
		unset($AjaxCart->Item->options);
		
		echo json_encode($AjaxCart);
		exit();
	}
	
	
	/**
	 * Adds a product as an item to the cart
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @param int $quantity The quantity of the item to add to the cart
	 * @param Product $Product Product object to add to the cart
	 * @param Price $Price Price object to add to the cart
	 * @param int $category The id of the category navigated to find the product
	 * @param array $data Any custom item data to carry through
	 * @return boolean
	 **/
	function add ($quantity,&$Product,&$Price,$category,$data=array()) {

		$NewItem = new Item($Product,$Price,$category,$data);
		if (!$NewItem->valid()) return false;
		
		if (($item = $this->hasitem($NewItem)) !== false) {
			$this->contents[$item]->add($quantity);
		} else {
			$NewItem->quantity($quantity);
			$this->contents[] = $NewItem;
		}

		do_action_ref_array('shopp_cart_add_item',array(&$NewItem));
		$this->added = &$NewItem;

		$this->changed(true);
		return true;
	}
	
	/**
	 * Removes an item from the cart
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @param int $item Index of the item in the Cart contents
	 * @return boolean
	 **/
	function remove ($item) {
		array_splice($this->contents,$item,1);
		$this->changed(true);
		return true;
	}
	
	/**
	 * Changes the quantity of an item in the cart
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @param int $item Index of the item in the Cart contents
	 * @param int $quantity New quantity to update the item to
	 * @return boolean
	 **/
	function update ($item,$quantity) {
		if (empty($this->contents)) return false;
		if ($quantity == 0) return $this->remove($item);
		elseif (isset($this->contents[$item])) {
			$this->contents[$item]->quantity($quantity);
			if ($this->contents[$item]->quantity == 0) $this->remove($item);
			$this->changed(true);
		}
		return true;
	}
	
	
	/**
	 * Empties the contents of the cart
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @return boolean
	 **/
	function clear () {
		$this->contents = array();
		$this->promocodes = array();
		$this->discounts = array();
		$this->changed(true);
		return true;
	}
		
	/**
	 * Changes an item to a different product/price variation
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @param int $item Index of the item to change
	 * @param Product $Product Product object to change to
	 * @param int|array|Price $pricing Price record ID or an array of pricing record IDs or a Price object
	 * @return boolean
	 **/
	function change ($item,&$Product,$pricing) {
		// Don't change anything if everything is the same
		if ($this->contents[$item]->product == $Product->id &&
				$this->contents[$item]->price == $pricing) return true;

		// If the updated product and price variation match
		// add the updated quantity of this item to the other item
		// and remove this one
		foreach ($this->contents as $id => $thisitem) {
			if ($thisitem->product == $Product->id && $thisitem->price == $pricing) {
				$this->update($id,$thisitem->quantity+$this->contents[$item]->quantity);
				$this->remove($item);
				return $this->changed(true);
			}
		}

		// No existing item, so change this one
		$qty = $this->contents[$item]->quantity;
		$category = $this->contents[$item]->category;
		$this->contents[$item] = new Item($Product,$pricing,$category);
		$this->contents[$item]->quantity($qty);
		
		return $this->changed(true);
	}
	
	/**
	 * Determines if a specified item is already in this cart
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @param Item $NewItem The new Item object to look for
	 * @return boolean|int	Item index if found, false if not found
	 **/
	function hasitem($NewItem) {
		$i = 0;
		foreach ($this->contents as $Item) {
			if ($Item->product == $NewItem->product && 
					$Item->price == $NewItem->price && 
					(empty($NewItem->data) || 
					(serialize($Item->data) == serialize($NewItem->data)))) 
				return $i;
			$i++;
		}
		return false;
	}
			
	/**
	 * Determines if the cart has changed and needs retotaled
	 *
	 * Set the cart as changed by specifying a changed value or
	 * get the current changed flag.
	 * 
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @param boolean $changed (optional) Used to set the changed flag
	 * @return boolean
	 **/
	function changed ($changed=false) {
		if ($changed) $this->changed = true;
		else return $this->changed;
	}
	
	/**
	 * taxrate()
	 * Determines the taxrate based on the currently
	 * available shipping information set by shipzone() */
	function taxrate () {
		global $Shopp;
		if ($Shopp->Settings->get('taxes') == "off") return false;
		
		$taxrates = $Shopp->Settings->get('taxrates');
		if (!is_array($taxrates)) return false;

		if (!empty($this->Order->Shipping->country)) $country = $this->Order->Shipping->country;
		elseif (!empty($this->Order->Billing->country)) $country = $this->Order->Billing->country;
		else return false;

		if (!empty($this->Order->Shipping->state)) $zone = $this->Order->Shipping->state;
		elseif (!empty($this->Order->Billing->state)) $zone = $this->Order->Billing->state;
		else return false;
		
		$global = false;
		foreach ($taxrates as $setting) {
			// Grab the global setting if found
			if ($setting['country'] == "*") {
				$global = $setting;
				continue;
			}
			
			if (isset($setting['zone'])) {
				if ($country == $setting['country'] &&
					$zone == $setting['zone'])
						return apply_filters('shopp_cart_taxrate',$setting['rate']/100);
			} elseif ($country == $setting['country']) {
				return apply_filters('shopp_cart_taxrate',$setting['rate']/100);
			}
		}
		
		if ($global) return apply_filters('shopp_cart_taxrate',$global['rate']/100);
		
	}
	
	/**
	 * Calculates aggregated total amounts
	 *
	 * Iterates over the cart items in the contents of the cart
	 * to calculate aggregated total amounts including the
	 * subtotal, shipping, tax, discounts and grand total
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @return void
	 **/
	function totals () {
		global $Shopp;
		if (!$this->retotal && !$this->changed()) return true;

		$this->Totals = new CartTotals();
		$Totals = &$this->Totals;
		
		// Free shipping until costs are assessed
		$this->freeshipping = true;	

		// If no items are shipped, free shipping is disabled
		if (!$this->shipped()) $this->freeshipping = false;
		
		foreach ($this->contents as $key => $Item) {

			$Totals->quantity += $Item->quantity;
			$Totals->subtotal +=  $Item->total;
			
			// Tabulate the taxable total to be calculated after discounts
			if ($Item->taxable) $Totals->taxed += $Item->total;
			
			// Item does not have free shipping, 
			// so the cart shouldn't have free shipping
			if (!$Item->freeshipping) $this->freeshipping = false;
			
		}
		
		// Calculate discounts
		$Discounts = new CartDiscounts();
		$Totals->discount = $Discounts->calculate();

		//$this->promotions();
		$Totals->discount = ($Totals->discount > $Totals->subtotal)?$Totals->subtotal:$Totals->discount;

		// Calculate shipping
		$Shipping = new CartShipping();
		$Totals->shipping = $Shipping->calculate();
		// if (!$this->ShippingDisabled && $this->Shipping && !$this->freeshippingping) 
		// 	$Totals->shipping = $this->shipping();

		// Calculate taxes
		$shippingTaxed = ($Shopp->Settings->get('tax_shipping') == "on");
		$Totals->taxrate = $this->taxrate();
	    
		if ($Totals->discount > $Totals->taxed) $Totals->taxed = 0;
		else $Totals->taxed -= $Totals->discount;
		if($shippingTaxed) $Totals->taxed += $Totals->shipping;
		$Totals->tax = round($Totals->taxed*$Totals->taxrate,2);

		// Calculate final totals
		$Totals->total = round($Totals->subtotal - round($Totals->discount,2) + 
			$Totals->shipping + $Totals->tax,2);

		do_action_ref_array('shopp_cart_retotal',array(&$Totals));
		$this->Totals = &$Totals;
	}
			
	/**
	 * Determines if the current order has no cost
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @return boolean True if the entire order is free
	 **/
	function orderisfree() {
		$status = (count($this->contents) > 0 && floatvalue($this->Totals->total) == 0);
		return apply_filters('shopp_free_order',$status);
	}
	
	/**
	 * Finds shipped items in the cart and builds a reference list
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return boolean True if there are shipped items in the cart
	 **/
	function shipped () {
		$shipped = array_filter($this->contents,array(&$this,'_filter_shipped'));
		
		foreach ($shipped as $key => $item)
			$this->shipped[$key] = &$this->contents[$key];
		return (!empty($this->shipped));
	}
	
	/**
	 * Helper method to identify shipped items in the cart
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return boolean
	 **/
	private function _filter_shipped ($item) {
		return ($item->shipped);
	}
	
	/**
	 * Finds downloadable items in the cart and builds a reference list
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return boolean True if there are shipped items in the cart
	 **/
	function downloads () {
		$downloads = array_filter($this->contents,array(&$this,'_filter_downloads'));
		foreach ($downloads as $key => $item)
			$this->downloads[$key] = &$this->contents[$key];
		return (!empty($this->downloads));
	}
	
	/**
	 * Helper method to identify digital items in the cart
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return boolean
	 **/
	private function _filter_downloads ($item) {
		return ($item->download >= 0);
	}

	
	/**
	 * Provides shopp('cart') template api functionality
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @return mixed
	 **/
	function tag ($property,$options=array()) {
		global $Shopp;
		$submit_attrs = array('title','value','disabled','tabindex','accesskey','class');
		
		// Return strings with no options
		switch ($property) {
			case "url": return $Shopp->link('cart'); break;
			case "hasitems": return (count($this->contents) > 0); break;
			case "totalitems": return $this->Totals->quantity; break;
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
			case "lastitem": return $this->contents[$this->added]; break;
			case "totalpromos": return count($this->PromosApplied); break;
			case "haspromos": return (count($this->PromosApplied) > 0); break;
			case "promos":
			return false;
				if (!$this->looping) {
					reset($this->discounts);
					$this->looping = true;
				} else next($this->discounts);
				
				if (current($this->discounts)) return true;
				else {
					$this->looping = false;
					reset($this->discounts);
					return false;
				}
			case "promo-name":
				$discount = current($this->discounts);
				return $discount->promo->name;
				break;
			case "promo-discount":
				$discount = current($this->discounts);
				if (!isset($options['label'])) $options['label'] = ' '.__('Off!','Shopp');
				else $options['label'] = ' '.$options['label'];
				$string = false;
				if (!empty($options['before'])) $string = $options['before'];
				
				switch($discount->promo->type) {
					case "Free Shipping": $string .= $Shopp->Settings->get('free_shipping_text'); break;
					case "Percentage Off": $string .= percentage($promo->discount)." ".$options['label']; break;
					case "Amount Off": $string .= money($promo->discount)." ".$options['label']; break;
					case "Buy X Get Y Free": return ""; break;
				}
				if (!empty($options['after'])) $string = $options['after'];
				
				return $string;
				break;
			case "function": 
				$result = '<div class="hidden"><input type="hidden" id="cart-action" name="cart" value="true" /></div><input type="submit" name="update" id="hidden-update" />';
				if (!$Shopp->Errors->exist()) return $result;
				$errors = $Shopp->Errors->get(SHOPP_COMM_ERR);
				foreach ((array)$errors as $error) 
					if (!empty($error)) $result .= '<p class="error">'.$error->message(true,false).'</p>';
				return $result;
				break;
			case "empty-button": 
				if (!isset($options['value'])) $options['value'] = __('Empty Cart','Shopp');
				return '<input type="submit" name="empty" id="empty-button" '.inputattrs($options,$submit_attrs).' />';
				break;
			case "update-button": 
				if (!isset($options['value'])) $options['value'] = __('Update Subtotal','Shopp');
				if (isset($options['class'])) $options['class'] .= "update-button";
				else $options['class'] = "update-button";
				return '<input type="submit" name="update"'.inputattrs($options,$submit_attrs).' />';
				break;
			case "sidecart":
				ob_start();
				include(SHOPP_TEMPLATES."/sidecart.php");
				$content = ob_get_contents();
				ob_end_clean();
				return $content;
				break;
			case "hasdiscount": return ($this->Totals->discount > 0); break;
			case "discount": return money($this->Totals->discount); break;
		}
		
		$result = "";
		switch ($property) {
			case "promos-available":
				if ($Shopp->Promotions->available()) return false;
				// Skip if the promo limit has been reached
				if ($Shopp->Settings->get('promo_limit') > 0 && 
					count($this->discounts) >= $Shopp->Settings->get('promo_limit')) return false;
				return true;
				break;
			case "promo-code": 
				// Skip if no promotions exist
				if (!$Shopp->Promotions->available()) return false;
				// Skip if the promo limit has been reached
				if ($Shopp->Settings->get('promo_limit') > 0 && 
					count($this->discounts) >= $Shopp->Settings->get('promo_limit')) return false;
				if (!isset($options['value'])) $options['value'] = __("Apply Promo Code","Shopp");
				$result .= '<ul><li>';
				
				$result = "";
				if ($Shopp->Errors->exist()) {
					$result .= '<p class="error">';
					$errors = $Shopp->Errors->source('CartDiscounts');
					foreach ((array)$errors as $error) if (!empty($error)) $result .= $error->message(true,false);
					$result .= '</p>';
				}
					
				$result .= '<span><input type="text" id="promocode" name="promocode" value="" size="10" /></span>';
				$result .= '<span><input type="submit" id="apply-code" name="update" '.inputattrs($options,$submit_attrs).' /></span>';
				$result .= '</li></ul>';
				return $result;
			case "has-shipping-methods": 
				return (!$this->ShippingDisabled
						&& count($this->ShipCosts) > 1
						&& $this->Shipping); break;				
			case "needs-shipped": return (!empty($this->shipped)); break;
			case "hasshipcosts":
			case "has-ship-costs": return false;//return ($this->Totals->shipping > 0); break;
			case "needs-shipping-estimates":
				$markets = $Shopp->Settings->get('target_markets');
				return (!empty($this->shipped) && ($this->showpostcode || count($markets) > 1));
				break;
			case "shipping-estimates":
				if (empty($this->shipped)) return "";
				$base = $Shopp->Settings->get('base_operations');
				$markets = $Shopp->Settings->get('target_markets');
				$Shipping = &$Shopp->Order->Shipping;
				if (empty($markets)) return "";
				foreach ($markets as $iso => $country) $countries[$iso] = $country;
				if (!empty($Shipping->country)) $selected = $Shipping->country;
				else $selected = $base['country'];
				$result .= '<ul><li>';
				if ((isset($options['postcode']) && value_is_true($options['postcode'])) || $this->showpostcode) {
					$result .= '<span>';
					$result .= '<input name="shipping[postcode]" id="shipping-postcode" size="6" value="'.$Shipping->postcode.'" />&nbsp;';
					$result .= '</span>';
				}
				if (count($countries) > 1) {
					$result .= '<span>';
					$result .= '<select name="shipping[country]" id="shipping-country">';
					$result .= menuoptions($countries,$selected,true);
					$result .= '</select>';
					$result .= '</span>';
				} else $result .= '<input type="hidden" name="shipping[country]" id="shipping-country" value="'.key($markets).'" />';
				$result .= '<br class="clear" /></li></ul>';
				return $result;
				break;
		}
		
		$result = "";
		switch ($property) {
			case "subtotal": $result = $this->Totals->subtotal; break;
			case "shipping": 
				if (empty($this->shipped)) return "";
				if (isset($options['label'])) {
					$options['currency'] = "false";
					if ($this->Totals->shipping === 0) {
						$result = $Shopp->Settings->get('free_shipping_text');
						if (empty($result)) $result = __('Free Shipping!','Shopp');
					}
						
					else $result = $options['label'];
				} else {
					if ($this->Totals->shipping === null) 
						return __("Enter Postal Code","Shopp");
					elseif ($this->Totals->shipping === false) 
						return __("Not Available","Shopp");
					else $result = $this->Totals->shipping;
				}
				break;
			case "hastaxes":
			case "has-taxes":
				return ($this->Totals->tax > 0); break;
			case "tax": 
				if ($this->Totals->tax > 0) {
					if (isset($options['label'])) {
						$options['currency'] = "false";
						$result = $options['label'];
					} else $result = $this->Totals->tax;
				} else $options['currency'] = "false";
				break;
			case "total": 
				$result = $this->Totals->total; 
				break;
		}
		
		if (isset($options['currency']) && !value_is_true($options['currency'])) return $result;
		else return '<span class="shopp_cart_'.$property.'">'.money($result).'</span>';
		
		return false;
	}
	
	/**
	 * Provides shopp('cartitem') template API functionality
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @return mixed
	 **/
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
	 * Provides shopp('shipping') template API functionality
	 * 
	 * Used primarily in the summary.php template
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @return mixed
	 **/
	function shippingtag ($property,$options=array()) {
		global $Shopp;
		$ShipCosts =& $this->ShipCosts;
		$result = "";
		
		switch ($property) {
			case "hasestimates": return (count($ShipCosts) > 0); break;
			case "methods":
				if (!isset($this->sclooping)) $this->sclooping = false;
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
				if ((isset($this->Order->Shipping->method) && 
					$this->Order->Shipping->method == $method['name']) ||
					($method['cost'] == $this->Totals->shipping))
						$checked = ' checked="checked"';
	
				$result .= '<input type="radio" name="shipmethod" value="'.$method['name'].'" class="shipmethod" '.$checked.' rel="'.$method['cost'].'" />';
				return $result;
				
				break;
			case "method-delivery":
				$periods = array("h"=>3600,"d"=>86400,"w"=>604800,"m"=>2592000);
				$method = current($ShipCosts);
				if (!$method['delivery']) return "";
				$estimates = explode("-",$method['delivery']);
				$format = get_option('date_format');
				if ($estimates[0] == $estimates[1]) $estimates = array($estimates[0]);
				$result = "";
				for ($i = 0; $i < count($estimates); $i++){
					list($interval,$p) = sscanf($estimates[$i],'%d%s');
					if (!empty($result)) $result .= "&mdash;";
					$result .= _d($format,mktime()+($interval*$periods[$p]));
				}				
				return $result;
		}
	}
	
	/**
	 * Provides shopp('checkout') template API functionality
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * 
	 * @return mixed
	 **/
	function checkouttag ($property,$options=array()) {
		global $Shopp,$wp;
		$gateway = $Shopp->Settings->get('payment_gateway');
		$xcos = $Shopp->Settings->get('xco_gateways');
		$pages = $Shopp->Settings->get('pages');
		$base = $Shopp->Settings->get('base_operations');
		$countries = $Shopp->Settings->get('target_markets');
		$process = get_query_var('shopp_proc');
		$xco = get_query_var('shopp_xco');

		$select_attrs = array('title','required','class','disabled','required','size','tabindex','accesskey');
		$submit_attrs = array('title','class','value','disabled','tabindex','accesskey');
		
		
		if (!isset($options['mode'])) $options['mode'] = "input";
		
		switch ($property) {
			case "url": 
				$ssl = true;
				// Test Mode will not require encrypted checkout
				if (strpos($gateway,"TestMode.php") !== false 
					|| isset($_GET['shopp_xco']) 
					|| $this->orderisfree() 
					|| SHOPP_NOSSL) 
					$ssl = false;
				$link = $Shopp->link('checkout',$ssl);
				
				// Pass any arguments along
				$args = $_GET;
				if (isset($args['page_id'])) unset($args['page_id']);
				$link = esc_url(add_query_arg($args,$link));
				if ($process == "confirm-order") $link = apply_filters('shopp_confirm_url',$link);
				else $link = apply_filters('shopp_checkout_url',$link);
				return $link;
				break;
			case "function":
				if (!isset($options['shipcalc'])) $options['shipcalc'] = '<img src="'.SHOPP_PLUGINURI.'/core/ui/icons/updating.gif" width="16" height="16" />';
				$regions = $Shopp->Settings->get('zones');
				$base = $Shopp->Settings->get('base_operations');
				$output = '<script type="text/javascript">'."\n";
				$output .= '<!--'."\n";
				$output .= 'var currencyFormat = '.json_encode($base['currency']['format']).';'."\n";
				$output .= 'var regions = '.json_encode($regions).';'."\n";
				$output .= 'var SHIPCALC_STATUS = \''.$options['shipcalc'].'\'';
				$output .= '//-->'."\n";
				$output .= '</script>'."\n";
				if (!empty($options['value'])) $value = $options['value'];
				else $value = "process";
				$output .= '<div><input type="hidden" name="checkout" value="'.$value.'" /></div>'; 
				if ($value == "confirmed") $output = apply_filters('shopp_confirm_form',$output);
				else $output = apply_filters('shopp_checkout_form',$output);
				return $output;
				break;
			case "error":
				$result = "";
				if (!$this->Errors->exist(SHOPP_COMM_ERR)) return false;
				$errors = $this->Errors->get(SHOPP_COMM_ERR);
				foreach ((array)$errors as $error) if (!empty($error)) $result .= $error->message();
				return $result;
				// if (isset($options['show']) && $options['show'] == "code") return $this->OrderError->code;
				// return $this->OrderError->message;
				break;
			case "cart-summary":
				ob_start();
				include(SHOPP_TEMPLATES."/summary.php");
				$content = ob_get_contents();
				ob_end_clean();
				return $content;
				break;
			case "loggedin": return $this->login; break;
			case "notloggedin": return (!$this->login && $Shopp->Settings->get('account_system') != "none"); break;
			case "email-login":  // Deprecating
			case "loginname-login":  // Deprecating
			case "account-login": 
				if (!empty($_POST['account-login']))
					$options['value'] = $_POST['account-login']; 
				return '<input type="text" name="account-login" id="account-login"'.inputattrs($options).' />';
				break;
			case "password-login": 
				if (!empty($_POST['password-login']))
					$options['value'] = $_POST['password-login']; 
				return '<input type="password" name="password-login" id="password-login" '.inputattrs($options).' />';
				break;
			case "submit-login": // Deprecating
			case "login-button":
				$string = '<input type="hidden" name="process-login" id="process-login" value="false" />';
				$string .= '<input type="submit" name="submit-login" id="submit-login" '.inputattrs($options).' />';
				return $string;
				break;

			case "firstname": 
				if ($options['mode'] == "value") return $this->Order->Customer->firstname;
				if (!empty($this->Order->Customer->firstname))
					$options['value'] = $this->Order->Customer->firstname; 
				return '<input type="text" name="firstname" id="firstname" '.inputattrs($options).' />';
				break;
			case "lastname":
				if ($options['mode'] == "value") return $this->Order->Customer->lastname;
				if (!empty($this->Order->Customer->lastname))
					$options['value'] = $this->Order->Customer->lastname; 
				return '<input type="text" name="lastname" id="lastname" '.inputattrs($options).' />'; 
				break;
			case "email":
				if ($options['mode'] == "value") return $this->Order->Customer->email;
				if (!empty($this->Order->Customer->email))
					$options['value'] = $this->Order->Customer->email; 
				return '<input type="text" name="email" id="email" '.inputattrs($options).' />';
				break;
			case "loginname":
				if ($options['mode'] == "value") return $this->Order->Customer->login;
				if (!empty($this->Order->Customer->login))
					$options['value'] = $this->Order->Customer->login; 
				return '<input type="text" name="login" id="login" '.inputattrs($options).' />';
				break;
			case "password":
				if ($options['mode'] == "value") return $this->Order->Customer->password;
				if (!empty($this->Order->Customer->password))
					$options['value'] = $this->Order->Customer->password; 
				return '<input type="password" name="password" id="password" '.inputattrs($options).' />';
				break;
			case "confirm-password":
				if (!empty($this->Order->Customer->confirm_password))
					$options['value'] = $this->Order->Customer->confirm_password; 
				return '<input type="password" name="confirm-password" id="confirm-password" '.inputattrs($options).' />';
				break;
			case "phone": 
				if ($options['mode'] == "value") return $this->Order->Customer->phone;
				if (!empty($this->Order->Customer->phone))
					$options['value'] = $this->Order->Customer->phone; 
				return '<input type="text" name="phone" id="phone" '.inputattrs($options).' />'; 
				break;
			case "organization": 
			case "company": 
				if ($options['mode'] == "value") return $this->Order->Customer->company;
				if (!empty($this->Order->Customer->company))
					$options['value'] = $this->Order->Customer->company; 
				return '<input type="text" name="company" id="company" '.inputattrs($options).' />'; 
				break;
			case "customer-info":
				$allowed_types = array("text","password","hidden","checkbox","radio");
				if (empty($options['type'])) $options['type'] = "hidden";
				if (isset($options['name']) && $options['mode'] == "value") 
					return $this->Order->Customer->info[$options['name']];
				if (isset($options['name']) && in_array($options['type'],$allowed_types)) {
					if (isset($this->Order->Customer->info[$options['name']])) 
						$options['value'] = $this->Order->Customer->info[$options['name']]; 
					return '<input type="text" name="info['.$options['name'].']" id="customer-info-'.$options['name'].'" '.inputattrs($options).' />'; 
				}
				break;

			// SHIPPING TAGS
			case "shipping": return $this->Shipping;
			case "shipping-address": 
				if ($options['mode'] == "value") return $this->Order->Shipping->address;
				if (!empty($this->Order->Shipping->address))
					$options['value'] = $this->Order->Shipping->address; 
				return '<input type="text" name="shipping[address]" id="shipping-address" '.inputattrs($options).' />';
				break;
			case "shipping-xaddress":
				if ($options['mode'] == "value") return $this->Order->Shipping->xaddress;
				if (!empty($this->Order->Shipping->xaddress))
					$options['value'] = $this->Order->Shipping->xaddress; 
				return '<input type="text" name="shipping[xaddress]" id="shipping-xaddress" '.inputattrs($options).' />';
				break;
			case "shipping-city":
				if ($options['mode'] == "value") return $this->Order->Shipping->city;
				if (!empty($this->Order->Shipping->city))
					$options['value'] = $this->Order->Shipping->city; 
				return '<input type="text" name="shipping[city]" id="shipping-city" '.inputattrs($options).' />';
				break;
			case "shipping-province":
			case "shipping-state":
				if ($options['mode'] == "value") return $this->Order->Shipping->state;
				if (!isset($options['selected'])) $options['selected'] = false;
				if (!empty($this->Order->Shipping->state)) {
					$options['selected'] = $this->Order->Shipping->state;
					$options['value'] = $this->Order->Shipping->state;
				}
				
				$output = false;
				$country = $base['country'];
				if (!empty($this->Order->Shipping->country))
					$country = $this->Order->Shipping->country;
				if (!array_key_exists($country,$countries)) $country = key($countries);

				if (empty($options['type'])) $options['type'] = "menu";
				$regions = $Shopp->Settings->get('zones');
				$states = $regions[$country];
				if (is_array($states) && $options['type'] == "menu") {
					$label = (!empty($options['label']))?$options['label']:'';
					$output = '<select name="shipping[state]" id="shipping-state" '.inputattrs($options,$select_attrs).'>';
					$output .= '<option value="" selected="selected">'.$label.'</option>';
				 	$output .= menuoptions($states,$options['selected'],true);
					$output .= '</select>';
				} else $output .= '<input type="text" name="shipping[state]" id="shipping-state" '.inputattrs($options).'/>';
				return $output;
				break;
			case "shipping-postcode":
				if ($options['mode'] == "value") return $this->Order->Shipping->postcode;
				if (!empty($this->Order->Shipping->postcode))
					$options['value'] = $this->Order->Shipping->postcode; 				
				return '<input type="text" name="shipping[postcode]" id="shipping-postcode" '.inputattrs($options).' />'; break;
			case "shipping-country": 
				if ($options['mode'] == "value") return $this->Order->Shipping->country;
				if (!empty($this->Order->Shipping->country))
					$options['selected'] = $this->Order->Shipping->country;
				else if (empty($options['selected'])) $options['selected'] = $base['country'];
				$output = '<select name="shipping[country]" id="shipping-country" '.inputattrs($options,$select_attrs).'>';
			 	$output .= menuoptions($countries,$options['selected'],true);
				$output .= '</select>';
				return $output;
				break;
			case "same-shipping-address":
				$label = __("Same shipping address","Shopp");
				if (isset($options['label'])) $label = $options['label'];
				$checked = ' checked="checked"';
				if (isset($options['checked']) && !value_is_true($options['checked'])) $checked = '';
				$output = '<label for="same-shipping"><input type="checkbox" name="sameshipaddress" value="on" id="same-shipping" '.$checked.' /> '.$label.'</label>';
				return $output;
				break;
				
			// BILLING TAGS
			case "billing-required": 
				if ($this->Totals->total == 0) return false;
				if (isset($_GET['shopp_xco'])) {
					$xco = join('/',array($Shopp->path,'gateways',$_GET['shopp_xco'].".php"));
					if (file_exists($xco)) {
						$meta = $Shopp->Flow->scan_gateway_meta($xco);
						$PaymentSettings = $Shopp->Settings->get($meta->tags['class']);
						return ($PaymentSettings['billing-required'] != "off");
					}
				}
				return ($this->Totals->total > 0); break;
			case "billing-address":
				if ($options['mode'] == "value") return $this->Order->Billing->address;
				if (!empty($this->Order->Billing->address))
					$options['value'] = $this->Order->Billing->address;			
				return '<input type="text" name="billing[address]" id="billing-address" '.inputattrs($options).' />';
				break;
			case "billing-xaddress":
				if ($options['mode'] == "value") return $this->Order->Billing->xaddress;
				if (!empty($this->Order->Billing->xaddress))
					$options['value'] = $this->Order->Billing->xaddress;			
				return '<input type="text" name="billing[xaddress]" id="billing-xaddress" '.inputattrs($options).' />';
				break;
			case "billing-city":
				if ($options['mode'] == "value") return $this->Order->Billing->city;
				if (!empty($this->Order->Billing->city))
					$options['value'] = $this->Order->Billing->city;			
				return '<input type="text" name="billing[city]" id="billing-city" '.inputattrs($options).' />'; 
				break;
			case "billing-province": 
			case "billing-state": 
				if ($options['mode'] == "value") return $this->Order->Billing->state;
				if (!isset($options['selected'])) $options['selected'] = false;
				if (!empty($this->Order->Billing->state)) {
					$options['selected'] = $this->Order->Billing->state;
					$options['value'] = $this->Order->Billing->state;
				}
				if (empty($options['type'])) $options['type'] = "menu";
				
				$output = false;
				$country = $base['country'];
				if (!empty($this->Order->Billing->country))
					$country = $this->Order->Billing->country;
				if (!array_key_exists($country,$countries)) $country = key($countries);

				$regions = $Shopp->Settings->get('zones');
				$states = $regions[$country];
				if (is_array($states) && $options['type'] == "menu") {
					$label = (!empty($options['label']))?$options['label']:'';
					$output = '<select name="billing[state]" id="billing-state" '.inputattrs($options,$select_attrs).'>';
					$output .= '<option value="" selected="selected">'.$label.'</option>';
				 	$output .= menuoptions($states,$options['selected'],true);
					$output .= '</select>';
				} else $output .= '<input type="text" name="billing[state]" id="billing-state" '.inputattrs($options).'/>';
				return $output;
				break;
			case "billing-postcode":
				if ($options['mode'] == "value") return $this->Order->Billing->postcode;
				if (!empty($this->Order->Billing->postcode))
					$options['value'] = $this->Order->Billing->postcode;			
				return '<input type="text" name="billing[postcode]" id="billing-postcode" '.inputattrs($options).' />';
				break;
			case "billing-country": 
				if ($options['mode'] == "value") return $this->Order->Billing->country;
				if (!empty($this->Order->Billing->country))
					$options['selected'] = $this->Order->Billing->country;
				else if (empty($options['selected'])) $options['selected'] = $base['country'];			
				$output = '<select name="billing[country]" id="billing-country" '.inputattrs($options,$select_attrs).'>';
			 	$output .= menuoptions($countries,$options['selected'],true);
				$output .= '</select>';
				return $output;
				break;
			case "billing-card":
				if ($options['mode'] == "value") 
					return str_repeat('X',strlen($this->Order->Billing->card)-4)
						.substr($this->Order->Billing->card,-4);
				if (!empty($this->Order->Billing->card)) {
					$options['value'] = $this->Order->Billing->card;
					$this->Order->Billing->card = "";
				}
				return '<input type="text" name="billing[card]" id="billing-card" '.inputattrs($options).' />';
				break;
			case "billing-cardexpires-mm":
				if ($options['mode'] == "value") return date("m",$this->Order->Billing->cardexpires);
				if (!empty($this->Order->Billing->cardexpires))
					$options['value'] = date("m",$this->Order->Billing->cardexpires);				
				return '<input type="text" name="billing[cardexpires-mm]" id="billing-cardexpires-mm" '.inputattrs($options).' />'; 	
				break;
			case "billing-cardexpires-yy": 
				if ($options['mode'] == "value") return date("y",$this->Order->Billing->cardexpires);
				if (!empty($this->Order->Billing->cardexpires))
					$options['value'] = date("y",$this->Order->Billing->cardexpires);							
				return '<input type="text" name="billing[cardexpires-yy]" id="billing-cardexpires-yy" '.inputattrs($options).' />'; 
				break;
			case "billing-cardtype":
				if ($options['mode'] == "value") return $this->Order->Billing->cardtype;
				if (!isset($options['selected'])) $options['selected'] = false;
				if (!empty($this->Order->Billing->cardtype))
					$options['selected'] = $this->Order->Billing->cardtype;	
				$cards = $Shopp->Settings->get('gateway_cardtypes');
				$label = (!empty($options['label']))?$options['label']:'';
				$output = '<select name="billing[cardtype]" id="billing-cardtype" '.inputattrs($options,$select_attrs).'>';
				$output .= '<option value="" selected="selected">'.$label.'</option>';
			 	$output .= menuoptions($cards,$options['selected']);
				$output .= '</select>';
				return $output;
				break;
			case "billing-cardholder":
				if ($options['mode'] == "value") return $this->Order->Billing->cardholder;
				if (!empty($this->Order->Billing->cardholder))
					$options['value'] = $this->Order->Billing->cardholder;			
				return '<input type="text" name="billing[cardholder]" id="billing-cardholder" '.inputattrs($options).' />';
				break;
			case "billing-cvv":
				if (!empty($this->Order->Billing->cardholder))
					$options['value'] = $_POST['billing']['cvv'];
				return '<input type="text" name="billing[cvv]" id="billing-cvv" '.inputattrs($options).' />';
				break;
			case "billing-xco":     
				if (isset($_GET['shopp_xco'])) {
					if ($this->Totals->total == 0) return false;
					$xco = join('/',array($Shopp->path,'gateways',$_GET['shopp_xco'].".php"));
					if (file_exists($xco)) {
						$meta = $Shopp->Flow->scan_gateway_meta($xco);
						$ProcessorClass = $meta->tags['class'];
						include_once($xco);
						$Payment = new $ProcessorClass();
						if (method_exists($Payment,'billing')) return $Payment->billing($options);
					}
				}
				break;
				
			case "has-data":
			case "hasdata": return (is_array($this->Order->data) && count($this->Order->data) > 0); break;
			case "order-data":
			case "orderdata":
				if (isset($options['name']) && $options['mode'] == "value") 
					return $this->Order->data[$options['name']];
				if (empty($options['type'])) $options['type'] = "hidden";
				$allowed_types = array("text","hidden",'password','checkbox','radio','textarea');
				if (isset($options['name']) && in_array($options['type'],$allowed_types)) {
					if (!isset($options['title'])) $options['title'] = $options['name'];
					if (in_array($options['type'],$value_override) && isset($this->Order->data[$options['name']])) 
						$options['value'] = $this->Order->data[$options['name']];
					if (!isset($options['cols'])) $options['cols'] = "30";
					if (!isset($options['rows'])) $options['rows'] = "3";
					if ($options['type'] == "textarea") 
						return '<textarea name="data['.$options['name'].']" cols="'.$options['cols'].'" rows="'.$options['rows'].'" id="order-data-'.$options['name'].'" '.inputattrs($options,array('accesskey','title','tabindex','class','disabled','required')).'>'.$options['value'].'</textarea>';
					return '<input type="'.$options['type'].'" name="data['.$options['name'].']" id="order-data-'.$options['name'].'" '.inputattrs($options).' />';
				}

				// Looping for data value output
				if (!$this->dataloop) {
					reset($this->Order->data);
					$this->dataloop = true;
				} else next($this->Order->data);

				if (current($this->Order->data) !== false) return true;
				else {
					$this->dataloop = false;
					return false;
				}
				
				break;
			case "data":
				if (!is_array($this->Order->data)) return false;
				$data = current($this->Order->data);
				$name = key($this->Order->data);
				if (isset($options['name'])) return $name;
				return $data;
				break;
			case "submit": 
				if (!isset($options['value'])) $options['value'] = __('Submit Order','Shopp');
				return '<input type="submit" name="process" id="checkout-button" '.inputattrs($options,$submit_attrs).' />'; break;
			case "confirm-button": 
				if (!isset($options['value'])) $options['value'] = __('Confirm Order','Shopp');
				return '<input type="submit" name="confirmed" id="confirm-button" '.inputattrs($options,$submit_attrs).' />'; break;
			case "local-payment": 
				return (!empty($gateway)); break;
			case "xco-buttons":     
				if (!is_array($xcos)) return false;
				$buttons = "";
				foreach ($xcos as $xco) {
					$xcopath = join('/',array($Shopp->path,'gateways',$xco));
					if (!file_exists($xcopath)) continue;
					$meta = scan_gateway_meta($xcopath);
					$ProcessorClass = $meta->tags['class'];
					if (!empty($ProcessorClass)) {
						$PaymentSettings = $Shopp->Settings->get($ProcessorClass);
						if ($PaymentSettings['enabled'] == "on") {
							include_once($xcopath);
							$Payment = new $ProcessorClass();
							$buttons .= $Payment->tag('button',$options);
						}
					}
				}
				return $buttons;
				break;
			case "receipt":
				if (!empty($this->Purchase->id)) 
					return $Shopp->Flow->order_receipt();
				break;
		}
	}
		
} // END class Cart


/**
 * Provides a data structure template for Cart totals
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package shopp
 * @subpackage cart
 **/
class CartTotals {
	
	var $taxrates = array();	// List of tax figures (rates and amounts)
	var $quantity = 0;			// Total quantity of items in the cart
	var $subtotal = 0;			// Subtotal of item totals
	var $discount = 0;			// Subtotal of cart discounts
	var $shipping = 0;			// Subtotal of shipping costs for items
	var $taxed = 0;				// Subtotal of taxable item totals
	var $tax = 0;				// Subtotal of item taxes
	var $total = 0;				// Grand total
		
} // END class CartTotals

/**
 * CartPromotions class
 * 
 * Helper class to load session promotions that can apply
 * to the cart
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package shopp
 * @subpackage cart
 **/
class CartPromotions {
	
	var $promotions = array();
	
	/**
	 * OrderPromotions constructor
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function __construct () {
		$this->load();
	}
	
	/**
	 * Loads promotions applicable to this shopping session if needed
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function load () {
		$db = &DB::get();

		// Already loaded
		if (!empty($this->promotions)) return true;
		
		$_table = DatabaseObject::tablename(Promotion::$table);
		$query = "SELECT * FROM $_table WHERE scope='Order' 
					AND ((status='enabled' 
					AND UNIX_TIMESTAMP(starts) > 0 
					AND UNIX_TIMESTAMP(starts) < UNIX_TIMESTAMP() 
					AND UNIX_TIMESTAMP(ends) > UNIX_TIMESTAMP()) 
					OR status='enabled')";
			
		$this->promotions = $db->query($query,AS_ARRAY);
	}
	
	/**
	 * Determines if there are promotions available for the order
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return boolean
	 **/
	function available () {
		return (!empty($this->promotions));
	}
	
} // END class CartPromotions


/**
 * CartDiscounts class
 * 
 * Manages the promotional discounts that apply to the cart
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package shopp
 * @subpackage cart
 **/
class CartDiscounts {

	// Registries
	var $Settings = false;
	var $Cart = false;
	var $promos = array();
	
	// Settings
	var $limit = 0;
	
	// Internals
	var $itemrules = array('Any item name','Any item quantity','Any item amount');
	var $matched = array();
	
	/**
	 * Initializes discount calculations
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function __construct () {
		global $Shopp;
		$this->limit = $Shopp->Settings->get('promo_limit');
		
		$this->Cart = &$Shopp->Order->Cart;
		$this->promos = &$Shopp->Promotions->promotions;
	}
	
	/**
	 * Calculates the discounts applied to the order
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return float The total discount amount
	 **/
	function calculate () {
		$this->applypromos();
		
		$discount = 0;
		foreach ($this->Cart->discounts as $Discount)
			$discount += $Discount->applied;
			
		return $discount;
	}

	/**
	 * Determines which promotions to apply to the order
	 * 
	 * Matches promotion rules to conditions in the cart to determine which
	 * promotions apply.
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function applypromos () {

		// Iterate over each promo to determine whether it applies
		foreach ($this->promos as &$promo) {
			$applypromo = false;
			if (!is_array($promo->rules))
				$promo->rules = unserialize($promo->rules);

			// If promotion limit has been reached, cancel the loop
			if ($this->limit > 0 && count($this->Cart->discounts)+1 > $this->limit) {
				if (!empty($this->Cart->promocode)) {
					new ShoppError(__("No additional codes can be applied.","Shopp"),'cart_promocode_limit',SHOPP_ALL_ERR);
					$this->Cart->promocode = false;
				}
				break;
			}
			
			// Match the promo rules against the cart properties
			$matches = 0;
			foreach ($promo->rules as $rule) {
				$match = false;
				extract($rule);

				if ($property == "Promo code") {
					// See if a promo code rule matches
					$match = $this->promocode($rule);
				} elseif (in_array($property,$this->itemrules)) {
					// See if an item rule matches
					foreach ($this->Cart->contents as $id => &$Item) {
						$match = $this->itemrule($Item,$id,$rule);
						if ($match) break;
					}
				} else {
					// Match cart aggregate property rules
					switch($property) {
						case "Total quantity": $subject = $this->Cart->Totals->quantity; break;
						case "Shipping amount": $subject = $this->Cart->Totals->shipping; break;
						case "Subtotal amount": $subject = $this->Cart->Totals->subtotal; break;
					}
					if (Promotion::match_rule($subject,$logic,$value,$property))
						$match = true;
				}
				
				if ($match && $promo->search == "all") $matches++;
				if ($match && $promo->search == "any") {
					$applypromo = true; break; // Kill the rule loop since the promo applies
				}

			} // End rules loop
			
			if ($promo->search == "all" && $matches == count($promo->rules))
				$applypromo = true;

			if (!$applypromo) continue; // Try next promotion

			// Apply the promotional discount
			switch ($promo->type) {
				case "Percentage Off": $discount = $this->Cart->Totals->subtotal*($promo->discount/100); break;
				case "Amount Off": $discount = $promo->discount; break;
				case "Free Shipping": $discount = 0; $this->Cart->freeshipping = true; break;
			}
			$this->apply_discount($promo,$discount);
			
		} // End promos loop
		
		// Handle promo codes that were not found
		if (empty($this->Cart->promocode)) return;
		
		$codes_applied = array_change_key_case($this->Cart->promocodes);
		if (!array_key_exists(strtolower($this->Cart->promocode),$codes_applied)) {
			new ShoppError(
				sprintf(__("%s is not a valid code.","Shopp"),$this->Cart->promocode),
				'cart_promocode_notfound',SHOPP_ALL_ERR);
			$this->Cart->promocode = false;
		}
		
	}
	
	/**
	 * Adds a discount entry for a promotion that applies
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param Object $Promotion The pseudo-Promotion object to apply
	 * @param float $discount The calculated discount amount
	 * @return void
	 **/
	function apply_discount ($promo,$discount) {

		$promo->applied = $discount;

		// Determine which promocode matched
		$promocode_rules = array_filter($promo->rules,array(&$this,'_filter_promocode_rule'));
		foreach ($promocode_rules as $rule) {
			extract($rule);
			
			$subject = strtolower($this->Cart->promocode);
			$promocode = strtolower($value);
			
			if (Promotion::match_rule($subject,$logic,$promocode,$property)) {
				if (isset($this->Cart->promocodes[$value])) {
					new ShoppError(sprintf(__("%s has already been applied.","Shopp"),$value),'cart_promocode_used',SHOPP_ALL_ERR);
					$this->Cart->promocode = false;
					return false;
				}
				$this->Cart->promocodes[$value] = $promo;
				$this->Cart->promocode = false;
			}
		}
		
		$this->Cart->discounts[$promo->id] = $promo;
	}
	
	/**
	 * Matches an Item to an item rule
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param Item $Item The Item to test against
	 * @param int $id The index of the Item in the cart
	 * @param array $rule The conditions of the rule
	 * @return boolean
	 **/
	function itemrule (&$Item,$id,$rule) {
		extract($rule);
		switch($property) {
			case "Any item name": $subject = $Item->name; break;
			case "Any item quantity": $subject = (int)$Item->quantity; break;
			case "Any item amount": $subject = $Item->total; break;
		}
		
		if (Promotion::match_rule($subject,$logic,$value,$property)) {
			$this->items[$id] = &$Item;
			return true;
		}
		return false;
	}
	
	/**
	 * Matches a Promo Code rule to a code submitted from the shopping cart
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param array $rule The promo code rule
	 * @return boolean
	 **/
	function promocode ($rule) {
		extract($rule);

		// Match previously applied codes
		if (in_array($value,$this->Cart->promocodes)) return true;
		
		// Match new codes
		
		// No code provided, nothing will match
		if (empty($this->Cart->promocode)) return false;

		$subject = strtolower($this->Cart->promocode);
		$promocode = strtolower($value);
		if (Promotion::match_rule($subject,$logic,$promocode,$property)) 
			return true;
		return false;
	}
	
	/**
	 * Helper method to identify a rule as a promo code rule
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param array $rule The rule to test
	 * @return boolean
	 **/
	function _filter_promocode_rule ($rule) {
		return ($rule['property'] == "Promo code");
	}
	
} // END class CartDiscounts

/**
 * CartShipping class
 * 
 * Mediator object for triggering ShippingModule calculations that are 
 * then used for a lowest-cost shipping estimate to show in the cart.
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package shopp
 * @subpackage cart
 **/
class CartShipping {
	
	var $options = array();
	var $modules = false;
	var $fees = 0;
	var $handling = 0;
	
	/**
	 * CartShipping constructor
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function __construct () {
		global $Shopp;
		
		$this->Shipping = &$Shopp->Order->Shipping;
		$this->modules = &$Shopp->Shipping->active;
		$this->Cart = &$Shopp->Order->Cart;
		
		$this->handling = $Shopp->Settings->get('order_shipfee');
		
	}
	
	/**
	 * Runs the shipping calculation modules
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function calculate () {
		global $Shopp;
		
		// If no shipped items, bail
		if (!$this->Cart->shipped()) return false;
		// If the cart is flagged for free shipping bail
		if ($this->Cart->freeshipping) return 0;

		// Initialize shipping modules
		do_action('shopp_calculate_init');

		foreach ($this->Cart->shipped as $id => &$Item) {
			// Calculate any product-specific shipping fee markups
			if ($Item->shipfee > 0) $this->fees += ($Item->quantity * $Item->shipfee);
			// Run shipping module item calculations
								do_action_ref_array('shopp_calculate_item_shipping',array($id,&$Item));
		}
	
		// Add order handling fee
		if ($this->handling > 0) $this->fees += $this->handling;
		
		// Run shipping module aggregate shipping calculations
		do_action_ref_array('shopp_calculate_shipping',array(&$this->options,$Shopp->Order));

		// No shipping options were generated, bail
		if (empty($this->options)) return false;
		
		// Determine the lowest cost estimate
		$estimate = false;
		foreach ($this->options as $name => $option) {
			// Add in the fees
			$option->amount += apply_filter('shopp_cart_fees',$this->fees);				
			// Skip if not to be included
			if (!$option->estimate) continue;
			// If the option amount is less than current estimate
			// Update the estimate to use this option instead
			if (!$estimate || $option->amount < $estimate->amount)
				$estimate = $option;
		}
		
		// Wipe out the selected shipping method if the option doesn't exist
		if (!isset($this->options[$this->Shipping->method]))
			$this->Shipping->method = false;

		// Always return the selected shipping option if a method has been set
		if (!empty($this->Shipping->method))
			return $this->options[$this->Shipping->method]->amount;

		// Return the estimated amount
		return $estimate->amount;
	}
	
} // END class CartShipping

/**
 * CartTaxes class
 * 
 * Handles tax calculations
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package shopp
 * @subpackage cart
 **/
class CartTaxes {
	
	/**
	 * CartTaxes constructor
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function __construct () {
	}
	
	function calculate () {
	}
	
} // END class CartTaxes

/**
 * ShippingOption class
 * 
 * A data structure for order shipping options
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package shopp
 * @subpackage cart
 **/
class ShippingOption {
	
	var $name;				// Name of the shipping option
	var $amount;			// Amount (cost) of the shipping option
	var $delivery;			// Estimated delivery of the shipping option
	var $estimate;			// Include option in estimate
	var $items = array();	// Item shipping rates for this shipping option

	/**
	 * Builds a shipping option from a configured/calculated 
	 * shipping rate array
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param array $rate The calculated shipping rate
	 * @param boolean $estimate Flag to be included/excluded from estimates
	 * @return void
	 **/
	function __construct ($rate,$estimate=true) {
		$this->name = $rate['name'];
		$this->amount = $rate['amount'];
		$this->estimate = $estimate;
		if (!empty($rate['delivery']))
			$this->delivery = $rate['delivery'];
		if (!empty($rate['items']))
			$this->delivery = $rate['items'];
	}
} // END class ShippingOption

?>