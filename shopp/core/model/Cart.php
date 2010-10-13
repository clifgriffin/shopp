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

class Cart {

	// properties
	var $contents = array();	// The contents (Items) of the cart 
	var $shipped = array();		// Reference list of shipped Items
	var $downloads = array();	// Reference list of digital Items
	var $discounts = array();	// List of promotional discounts applied
	var $promocodes = array();	// List of promotional codes applied
	var $shipping = array();	// List of shipping options
	
	// Object properties
	var $Added = false;			// Last Item added
	var $Totals = false;		// Cart Totals data structure
	
	var $freeship = false;
	var $showpostcode = false;	// Flag to show postcode field in shipping estimator
	var $noshipping = false;	// Shipping calculates disabled
	
	// Internal properties
	var $changed = false;		// Flag when Cart updates and needs retotaled
	var $added = false;			// The index of the last item added
	
	var $runaway = 0;
	var $retotal = false;
	var $handlers = false;
		
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
		
		if (isset($_REQUEST['checkout'])) shopp_redirect(shoppurl(false,'checkout',$Shopp->Order->security()));
		
		if (isset($_REQUEST['shopping'])) shopp_redirect(shoppurl());
		
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
				$products = array(); // List of products to add
				if (isset($_REQUEST['product'])) $products[] = $_REQUEST['product'];
				if (!empty($_REQUEST['products']) && is_array($_REQUEST['products'])) 
					$products = array_merge($products,$_REQUEST['products']);
				
				if (empty($products)) break;
				
				foreach ($products as $id => $product) {
					if (isset($product['quantity']) && $product['quantity'] == '0') continue;
					$quantity = (empty($product['quantity']) && 
						$product['quantity'] !== 0)?1:$product['quantity']; // Add 1 by default
					$Product = new Product($product['product']);
					$pricing = false;
					
					if (!empty($product['options'][0])) $pricing = $product['options'];
					elseif (isset($product['price'])) $pricing = $product['price'];
					
					$category = false;
					if (!empty($product['category'])) $category = $product['category'];

					$data = array();
					if (!empty($product['data'])) $data = $product['data'];

					$addons = array();
					if (isset($product['addons'])) $addons = $product['addons'];

					if (!empty($Product->id)) {
						if (isset($product['item'])) $result = $this->change($product['item'],$Product,$pricing);
						else $result = $this->add($quantity,$Product,$pricing,$category,$data,$addons);
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
							$item['price'] != $this->contents[$id]->priceline) {
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
		$AjaxCart->url = shoppurl(false,'cart');
		$AjaxCart->label = __('Edit shopping cart','Shopp');
		$AjaxCart->checkouturl = shoppurl(false,'checkout',$Shopp->Order->security());
		$AjaxCart->checkoutLabel = __('Proceed to Checkout','Shopp');
		$AjaxCart->imguri = SHOPP_PRETTYURLS?trailingslashit(shoppurl('images')):shoppurl().'&siid=';
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
	function add ($quantity,&$Product,&$Price,$addons=array(),$data=array(),$category=false) {

		$NewItem = new Item($Product,$Price,$addons,$data,$category);
		if (!$NewItem->valid()) return false;
		
		if (($item = $this->hasitem($NewItem)) !== false) {
			$this->contents[$item]->add($quantity);
			$this->added = $item;
		} else {
			$NewItem->quantity($quantity);
			$this->contents[] = $NewItem;
			$this->added = count($this->contents)-1;
		}

		do_action_ref_array('shopp_cart_add_item',array(&$NewItem));
		$this->Added = &$NewItem;

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
		if (isset($this->promocode)) unset($this->promocode);
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
	function change ($item,&$Product,$pricing,$addons=array()) {
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
		$data = $this->contents[$item]->data;
		$addons = array();
		foreach ($this->contents[$item]->addons as $addon) $addons[] = $addon->options;
		$this->contents[$item] = new Item($Product,$pricing,$category,$data,$addons);
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
		// Find matching item fingerprints
		foreach ($this->contents as $i => $Item)
			if ($Item->fingerprint() === $NewItem->fingerprint()) return $i;
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
		if (!($this->retotal || $this->changed())) return true;

		$Totals = new CartTotals();
		$this->Totals = &$Totals;
		
		// Setup discount calculator
		$Discounts = new CartDiscounts();
		
		// Free shipping until costs are assessed
		$this->freeshipping = true;	

		// Identify downloadable products
		$this->downloads();
		
		// If no items are shipped, free shipping is disabled
		if (!$this->shipped()) $this->freeshipping = false;
		
		foreach ($this->contents as $key => $Item) {
			$Item->retotal();

			$Totals->quantity += $Item->quantity;
			$Totals->subtotal +=  $Item->total;
			
			// Reinitialize item discount amounts
			$Item->discount = 0;
			
			// Item does not have free shipping, 
			// so the cart shouldn't have free shipping
			if (!$Item->freeshipping) $this->freeshipping = false;
			
		}

		// Calculate Shipping
		$Shipping = new CartShipping();
		if ($this->changed()) {
			// Only fully recalculate shipping costs 
			// if the cart contents have changed
			$Totals->shipping = $Shipping->calculate();

			// Save the generated shipping options
			$this->shipping = $Shipping->options();
			
		} else $Totals->shipping = $Shipping->selected();
		
		// Calculate discounts
		$Totals->discount = $Discounts->calculate();

		//$this->promotions();
		$Totals->discount = ($Totals->discount > $Totals->subtotal)?$Totals->subtotal:$Totals->discount;

		// Calculate taxes
		$Tax = new CartTax();
		$Totals->taxrate = $Tax->rate();
		$Totals->tax = $Tax->calculate();
	    
		// Calculate final totals
		$Totals->total = roundprice($Totals->subtotal - roundprice($Totals->discount) + 
			$Totals->shipping + $Totals->tax);

		do_action_ref_array('shopp_cart_retotal',array(&$this->Totals));
		$this->changed = false;
		$this->retotal = false;
		
		
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

		$this->shipped = array();
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
		$this->downloads = array();
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
			case "url": return shoppurl(false,'cart'); break;
			case "referrer": 
			case "referer": 
				$referrer = $Shopp->Shopping->data->referrer; 
				if (!$referrer) $referrer = shopp('catalog','url','return=1');
				return $referrer;
				break;
			case "hasitems":
			case "has-items": return (count($this->contents) > 0); break;
			case "totalitems":
			case "total-items": return $this->Totals->quantity; break;
			case "items":
				if (!isset($this->_item_loop)) {
					reset($this->contents);
					$this->_item_loop = true;
				} else next($this->contents);
				
				if (current($this->contents)) return true;
				else {
					unset($this->_item_loop);
					reset($this->contents);
					return false;
				}
				break;
			case "hasshipped":
			case "has-shipped": return $this->shipped(); break;
			case "shippeditems":
			case "shipped-items":
				if (!isset($this->_shipped_loop)) {
					reset($this->shipped);
					$this->_shipped_loop = true;
				} else next($this->shipped);
			
				if (current($this->shipped)) return true;
				else {
					unset($this->_shipped_loop);
					reset($this->shipped);
					return false;
				}
				break;
			case "hasdownloads":
			case "has-downloads": return $this->downloads(); break;
			case "downloaditems":
			case "download-items":
				if (!isset($this->_downloads_loop)) {
					reset($this->downloads);
					$this->_downloads_loop = true;
				} else next($this->downloads);
			
				if (current($this->downloads)) return true;
				else {
					unset($this->_downloads_loop);
					reset($this->downloads);
					return false;
				}
				break;
			case "lastitem":
			case "last-item": return $this->contents[$this->added]; break;
			case "totalpromos":
			case "total-promos": return count($this->discounts); break;
			case "haspromos":
			case "has-promos": return (count($this->discounts) > 0); break;
			case "discounts":
			case "promos":
				if (!isset($this->_promo_looping)) {
					reset($this->discounts);
					$this->_promo_looping = true;
				} else next($this->discounts);
				
				$discount = current($this->discounts);
				while ($discount && empty($discount->applied))
					$discount = next($this->discounts);
				
				if (current($this->discounts)) return true;
				else {
					unset($this->_promo_looping);
					reset($this->discounts);
					return false;
				}
			case "promoname":
			case "promo-name":
				$discount = current($this->discounts);
				if ($discount->applied == 0 && empty($discount->items)) return false;
				return $discount->name;
				break;
			case "promodiscount":
			case "promo-discount":
				$discount = current($this->discounts);
				if ($discount->applied == 0 && empty($discount->items)) return false;
				if (!isset($options['label'])) $options['label'] = ' '.__('Off!','Shopp');
				else $options['label'] = ' '.$options['label'];
				$string = false;
				if (!empty($options['before'])) $string = $options['before'];

				switch($discount->type) {
					case "Free Shipping": $string .= $Shopp->Settings->get('free_shipping_text'); break;
					case "Percentage Off": $string .= percentage($discount->discount,array('precision' => 0)).$options['label']; break;
					case "Amount Off": $string .= money($discount->discount).$options['label']; break;
					case "Buy X Get Y Free": return sprintf(__('Buy %s get %s free','Shopp'),$discount->buyqty,$discount->getqty); break;
				}
				if (!empty($options['after'])) $string .= $options['after'];
				
				return $string;
				break;
			case "function": 
				$result = '<div class="hidden"><input type="hidden" id="cart-action" name="cart" value="true" /></div><input type="submit" name="update" id="hidden-update" />';

				$Errors = &ShoppErrors();
				if (!$Errors->exist(SHOPP_STOCK_ERR)) return $result;

				ob_start();
				include(SHOPP_TEMPLATES."/errors.php");
				$errors = ob_get_contents();
				ob_end_clean();
				return $result.$errors;
				break;
			case "emptybutton":
			case "empty-button": 
				if (!isset($options['value'])) $options['value'] = __('Empty Cart','Shopp');
				return '<input type="submit" name="empty" id="empty-button" '.inputattrs($options,$submit_attrs).' />';
				break;
			case "updatebutton":
			case "update-button": 
				if (!isset($options['value'])) $options['value'] = __('Update Subtotal','Shopp');
				if (isset($options['class'])) $options['class'] .= " update-button";
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
			case "hasdiscount":
			case "has-discount": return ($this->Totals->discount > 0); break;
			case "discount": return money($this->Totals->discount); break;
		}
		
		$result = "";
		switch ($property) {
			case "promos-available":
				if (!$Shopp->Promotions->available()) return false;
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
				$result = '<ul><li>';
				
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
				return apply_filters(
							'shopp_shipping_hasestimates',
							(!empty($this->shipping) && !$this->noshipping),
							$this->shipping
						); break;
			case "needs-shipped": return (!empty($this->shipped)); break;
			case "hasshipcosts":
			case "has-shipcosts":
			case "hasship-costs":
			case "has-ship-costs": return ($this->Totals->shipping > 0); break;
			case "needs-shipping-estimates":
				$markets = $Shopp->Settings->get('target_markets');
				return (!empty($this->shipped) && !$this->noshipping && ($this->showpostcode || count($markets) > 1));
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
					$result .= '<input type="text" name="shipping[postcode]" id="shipping-postcode" size="6" value="'.$Shipping->postcode.'" />&nbsp;';
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
		$Item = false;
		if (isset($this->_item_loop)) $Item = current($this->contents);
		elseif (isset($this->_shipped_loop)) $Item = current($this->shipped);
		elseif (isset($this->_downloads_loop)) $Item = current($this->downloads);
		
		if ($Item !== false) {
			$id = key($this->contents);
			return $Item->tag($id,$property,$options);
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
		$result = "";
		
		switch ($property) {
			case "url": return shoppurl(false,'cart');
			case "hasestimates": return apply_filters('shopp_shipping_hasestimates',!empty($this->shipping)); break;
			case "options":
			case "methods":
				if (!isset($this->sclooping)) $this->sclooping = false;
				if (!$this->sclooping) {
					reset($this->shipping);
					$this->sclooping = true;
				} else next($this->shipping);
				
				if (current($this->shipping) !== false) return true;
				else {
					$this->sclooping = false;
					reset($this->shipping);
					return false;
				}
				break;
			case "option-menu":
			case "method-menu":
				// @todo Add options for differential pricing and estimated delivery dates
				$_ = array();
				$_[] = '<select name="shipmethod" class="shopp shipmethod">';
				foreach ($this->shipping as $method) {
					$selected = ((isset($Shopp->Order->Shipping->method) && 
						$Shopp->Order->Shipping->method == $method->name))?' selected="selected"':false;
					
					$_[] = '<option value="'.$method->name.'"'.$selected.'>'.$method->name.' &mdash '.money($method->amount).'</option>';
				}
				$_[] = '</select>';
				return join("",$_);
				break;
			case "option-name": 
			case "method-name": 
				$option = current($this->shipping);
				return $option->name;
				break;
			case "method-selected":
				$method = current($this->shipping);
				return ((isset($Shopp->Order->Shipping->method) && 
					$Shopp->Order->Shipping->method == $method->name));			
				break;
			case "option-cost": 
			case "method-cost": 
				$option = current($this->shipping);
				return money($option->amount);
				break;
			case "method-selector":
				$method = current($this->shipping);

				$checked = '';
				if ((isset($Shopp->Order->Shipping->method) && 
					$Shopp->Order->Shipping->method == $method->name))
						$checked = ' checked="checked"';
				
				$result = '<input type="radio" name="shipmethod" value="'.$method->name.'" class="shopp shipmethod" '.$checked.' />';
				return $result;
				
				break;
			case "option-delivery":
			case "method-delivery":
				$periods = array("h"=>3600,"d"=>86400,"w"=>604800,"m"=>2592000);
				$option = current($this->shipping);
				if (!$option->delivery) return "";
				$estimates = explode("-",$option->delivery);
				$format = get_option('date_format');
				if (count($estimates) > 1 
					&& $estimates[0] == $estimates[1]) $estimates = array($estimates[0]);
				$result = "";
				for ($i = 0; $i < count($estimates); $i++){
					list($interval,$p) = sscanf($estimates[$i],'%d%s');
					if (!empty($result)) $result .= "&mdash;";
					$result .= _d($format,mktime()+($interval*$periods[$p]));
				}				
				return $result;
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
	var $itemsd = 0;			// Subtotal of cart item discounts
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
		
		// Use an offset amount as a buffer to account for how 
		// MySQL's UNIX_TIMESTAMP() converts the datetime to a 
		// UTC-based timestamp from the Jan 1, 1970 00:00:00 epoch
		// We use 43200 to represent 12-hours (UTC +/- 12 hours) and
		// add 1 to account for the default amount set in the promotion editor
		$offset = 43200 + 1;
		
		$_table = DatabaseObject::tablename(Promotion::$table);
		$query = "SELECT * FROM $_table WHERE (target='Cart' OR target='Cart Item')
		            AND status='enabled' -- Promo must be enabled, in all cases
					AND (
					    -- Promo is not date based
					    ( 
					        UNIX_TIMESTAMP(starts) <= $offset
					        AND
					        UNIX_TIMESTAMP(ends) <= $offset
					    )
					    OR
					    -- Promo has start and end dates, check that we are in between
					    ( 
					        UNIX_TIMESTAMP(starts) > $offset 
					        AND
					        UNIX_TIMESTAMP(ends) > $offset
					        AND
					        (".time()." BETWEEN UNIX_TIMESTAMP(starts) AND UNIX_TIMESTAMP(ends))
					    )
					    OR
					    -- Promo has _only_ a start date, check that we are after it
					    ( 
					        UNIX_TIMESTAMP(starts) > $offset
					        AND
					        UNIX_TIMESTAMP(ends) <= $offset
					        AND
					        UNIX_TIMESTAMP(starts) < ".time()."
					    )
					    OR
					    -- Promo has _only_ an end date, check that we are before it
					    ( 
					        UNIX_TIMESTAMP(starts) <= $offset
					        AND
					        UNIX_TIMESTAMP(ends) > $offset
					        AND
					        ".time()." < UNIX_TIMESTAMP(ends)
						)
				    ) ORDER BY target DESC";
		$this->promotions = $db->query($query,AS_ARRAY);
	}
	
	/**
	 * Reset and load all the active promotions
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function reload () {
		$this->promotions = array();	// Wipe loaded promotions
		$this->load();					// Re-load active promotions
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
	var $itemprops = array('Any item name','Any item quantity','Any item amount');
	var $cartitemprops = array('Name','Category','Tag name','Variation','Input name','Input value','Quantity','Unit price','Total price','Discount amount');
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
		$baseop = $Shopp->Settings->get('base_operations');
		$this->precision = $baseop['currency']['format']['precision'];
		
		$this->Order = &$Shopp->Order;
		$this->Cart = &$Shopp->Order->Cart;
		$this->promos = &$Shopp->Promotions->promotions;
		// print_r($this->Cart->promocodes);
		// echo "<pre>";
		// print_r($this->Cart->discounts);
		// echo "</pre>";
		// 
		// print_r($this->promos);
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
		foreach ($this->Cart->discounts as $Discount) {
			if (isset($Discount->items)) {
				foreach ($Discount->items as $id => $amount) {
					if (isset($this->Cart->contents[$id])) {
						$this->Cart->contents[$id]->discount += $amount;
						$this->Cart->contents[$id]->retotal();
					}
				}
			}
			$discount += !empty($Discount->items)?array_sum($Discount->items):$Discount->applied;
		}

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
			$total = 0;
			foreach ($promo->rules as $index => $rule) {
				if ($index == "item") continue;
				$match = false;
				$total++;
				extract($rule);
				if ($property == "Promo code") {
					// See if a promo code rule matches
					$match = $this->promocode($rule);					
				} elseif (in_array($property,$this->itemprops)) {
					// See if an item rule matches
					foreach ($this->Cart->contents as $id => &$Item)
						if ($match = $Item->match($rule)) break;
				} else {
					// Match cart aggregate property rules
					switch($property) {
						case "Promo use count": $subject = $promo->uses; break;
						case "Total quantity": $subject = $this->Cart->Totals->quantity; break;
						case "Shipping amount": $subject = $this->Cart->Totals->shipping; break;
						case "Subtotal amount": $subject = $this->Cart->Totals->subtotal; break;
						case "Customer type": $subject = $this->Order->Customer->type; break;
						case "Ship-to country": $subject = $this->Order->Shipping->country; break;
					}
					if (Promotion::match_rule($subject,$logic,$value,$property))
						$match = true;
				}
				
				if ($match && $promo->search == "all") $matches++;
				if ($match && $promo->search == "any") {
					$applypromo = true; break; // Kill the rule loop since the promo applies
				}

			} // End rules loop

			if ($promo->search == "all" && $matches == $total)
				$applypromo = true;
				
			if (!$applypromo) {
				$promo->applied = 0; 		// Reset promo applied discount
				if (!empty($promo->items))	// Reset any items applied to
					$promo->items = array(); 
				continue; // Try next promotion
			}

			// Apply the promotional discount
			switch ($promo->type) {
				case "Amount Off": $discount = $promo->discount; break;
				case "Percentage Off": 
					$discount = ($this->Cart->Totals->subtotal-$this->Cart->Totals->itemsd) 
									* ($promo->discount/100);
					break;
				case "Free Shipping": 
					if ($promo->target == "Cart") {
						$discount = 0;
						$this->Cart->freeshipping = true;
						$this->Cart->Totals->shipping = 0;
					}
					break;
			}
			$this->discount($promo,$discount);

		} // End promos loop
		
		// Promocode was/is applied
		if (empty($this->Cart->promocode)) return;
		if (isset($this->Cart->promocodes[strtolower($this->Cart->promocode)]) 
			&& is_array($this->Cart->promocodes[strtolower($this->Cart->promocode)])) return;

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
	function discount ($promo,$discount) {

		$promo->applied = 0;		// Track total discount applied by the promo
		$promo->items = array();	// Track the cart items the rule applies to

		// Line item discounts
		if (isset($promo->rules['item'])) {

			// See if an item rule matches
			foreach ($this->Cart->contents as $id => &$Item) {
				$matches = 0;
				foreach ($promo->rules['item'] as $rule) {
					if (!in_array($rule['property'],$this->cartitemprops)) continue;
					if ($Item->match($rule) && !isset($promo->items[$id])) $matches++;
				} // endforeach $promo->rules['item']
				
				if ($matches == count($promo->rules['item'])) { // all conditions must match
				
					switch ($promo->type) {
						case "Percentage Off": $discount = ($Item->unitprice*$Item->quantity)*($promo->discount/100); break;
						case "Amount Off": $discount = $promo->discount; break;
						case "Free Shipping": $discount = 0; $Item->freeshipping = true; break;
						case "Buy X Get Y Free": $discount = floor(
																$Item->quantity / 
																($promo->buyqty + $promo->getqty)
															  ) * ($Item->unitprice);
							break;
					}
					$promo->applied += $discount;
					$promo->items[$id] = $discount;
				} else {
					
				}
			}
			
			$this->Cart->Totals->itemsd += $promo->applied;
		} else {
			$promo->applied = $discount;
		}
		
		// Determine which promocode matched
		$promocode_rules = array_filter($promo->rules,array(&$this,'_filter_promocode_rule'));
		foreach ($promocode_rules as $rule) {
			extract($rule);
			
			$subject = strtolower($this->Cart->promocode);
			$promocode = strtolower($value);
			
			if (Promotion::match_rule($subject,$logic,$promocode,$property)) {
				// Prevent customers from reapplying codes
				if (isset($this->Cart->promocodes[$promocode]) 
						&& is_array($this->Cart->promocodes[$promocode]) 
						&& in_array($promo->id,$this->Cart->promocodes[$promocode])) {
					new ShoppError(sprintf(__("%s has already been applied.","Shopp"),$value),'cart_promocode_used',SHOPP_ALL_ERR);
					$this->Cart->promocode = false;
					return false;
				}
				// Add the code to the registry
				if (!isset($this->Cart->promocodes[$promocode]) 
					|| !is_array($this->Cart->promocodes[$promocode])) 
					$this->Cart->promocodes[$promocode] = array();
				else $this->Cart->promocodes[$promocode][] = $promo->id;
				$this->Cart->promocode = false;
			}
		}
		
		$this->Cart->discounts[$promo->id] = $promo;
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
		$promocode = strtolower($value);
		
		// Match previously applied codes
		if (isset($this->Cart->promocodes[$promocode]) 
			&& is_array($this->Cart->promocodes[$promocode])) return true;
		
		// Match new codes
		
		// No code provided, nothing will match
		if (empty($this->Cart->promocode)) return false;

		$subject = strtolower($this->Cart->promocode);		
		return Promotion::match_rule($subject,$logic,$promocode,$property);
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
		return (isset($rule['property']) && $rule['property'] == "Promo code");
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
	var $disabled = false;
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
		$Settings =& ShoppSettings();
		
		$this->Shipping = &$Shopp->Order->Shipping;
		$this->modules = &$Shopp->Shipping->active;
		$this->Cart = &$Shopp->Order->Cart;
		
		$this->showpostcode = $Shopp->Shipping->postcodes;
		
		$this->disabled = $this->Cart->noshipping = ($Settings->get('shipping') == "off");
		$this->handling = $Settings->get('order_shipfee');
		
	}
	
	function status () {
		// If shipping is disabled, bail
		if ($this->disabled) return false;
		// If no shipped items, bail
		if (!$this->Cart->shipped()) return false;
		// If the cart is flagged for free shipping bail
		if ($this->Cart->freeshipping) return 0;
		return true;
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

		$status = $this->status();
		if ($status !== true) return $status;

		// Initialize shipping modules
		do_action('shopp_calculate_shipping_init');

		foreach ($this->Cart->shipped as $id => &$Item) {
			if ($Item->freeshipping) continue;
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
			$option->amount += apply_filters('shopp_cart_fees',$this->fees);
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
		else $this->Shipping->method = $estimate->name;


		// Return the estimated amount
		return $estimate->amount;
	}
	
	/**
	 * Returns the currently calculated shipping options
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return array List of ShippingOption objects
	 **/
	function options () {
		return $this->options;
	}
	
	/**
	 * Return the currently selected shipping method
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return float The shipping amount
	 **/
	function selected () {

		$status = $this->status();
		if ($status !== true) return $status;

		if (!empty($this->Shipping->method) && isset($this->Cart->shipping[$this->Shipping->method]))
			return $this->Cart->shipping[$this->Shipping->method]->amount;
		$method = current($this->Cart->shipping);
		return $method->amount;
	}
	
} // END class CartShipping

/**
 * CartTax class
 * 
 * Handles tax calculations
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package shopp
 * @subpackage cart
 **/
class CartTax {

	var $Order = false;
	var $enabled = false;
	var $shipping = false;
	var $rates = array();
	
	/**
	 * CartTax constructor
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function __construct () {
		global $Shopp;
		$this->Order = &ShoppOrder();
		$base = $Shopp->Settings->get('base_operations');
		$this->format = $base['currency']['format'];
		$this->vat = $base['vat'];
		$this->enabled = ($Shopp->Settings->get('taxes') == "on");
		$this->rates = $Shopp->Settings->get('taxrates');
		$this->shipping = ($Shopp->Settings->get('tax_shipping') == "on");
	}
	
	/**
	 * Determine the applicable tax rate
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return float The tax rate (or false if no rate applies)
	 **/
	function rate ($Item=false,$settings=false) {
		if (!$this->enabled) return false;		
		if (!is_array($this->rates)) return false;
		
		$Customer = $this->Order->Customer;
		$Billing = $this->Order->Billing;
		$Shipping = $this->Order->Shipping;
		$country = false;
		if (!empty($Shipping->country)) $country = $Shipping->country;
		elseif (!empty($Billing->country)) $country = $Billing->country;

		$zone = false;
		if (!empty($Shipping->state)) $zone = $Shipping->state;
		elseif (!empty($Billing->state)) $zone = $Billing->state;

		$locale = false;
		if (!empty($Shipping->locale)) $locale = $Shipping->locale;
		elseif (!empty($Billing->locale)) $locale = $Billing->locale;

		$global = false;
		foreach ($this->rates as $setting) {
			$rate = false;
			
			if (isset($setting['locals']) && is_array($setting['locals'])) {
				if ($country == $setting['country'] &&
					$zone == $setting['zone']) {
						$localrate = isset($setting['locals'][$locale])?$setting['locals'][$locale]:0;
						$rate = ($this->float($setting['rate'])+$this->float($localrate));
					}
			} elseif (isset($setting['zone'])) {
				if ($country == $setting['country'] && $zone == $setting['zone'])
					$rate = $this->float($setting['rate']);
			} elseif ($country == $setting['country']) {
				$rate = $this->float($setting['rate']);
			}


			// Match tax rules
			if (isset($setting['rules']) && is_array($setting['rules'])) {
				$applies = false;
				$matches = 0;

				foreach ($setting['rules'] as $rule) {
					$match = false;
					if ($Item !== false && strpos($rule['p'],'product') !== false) {
						$match = $Item->taxrule($rule);
					} elseif (strpos($rule['p'],'customer') !== false) {
						$match = $Customer->taxrule($rule);
					}

					$match = apply_filters('shopp_customer_taxrule_match',$match,$rule,$this);
					if ($match) $matches++;
				}
				if ($setting['logic'] == "all" && $matches == count($setting['rules'])) $applies = true;
				if ($setting['logic'] == "any" && $matches > 0) $applies = true;
				if (!$applies) continue;
			}
			
			// Grab the global setting if found
			if ($setting['country'] == "*") $global = $setting;

			if ($rate !== false) { // The first rate to fully apply wins
				if ($settings) return apply_filters('shopp_cart_taxrate_settings',$setting);
				return apply_filters('shopp_cart_taxrate',$rate/100);
			}
			
		}
		
		if ($global) {
			if ($settings) return apply_filters('shopp_cart_taxrate_settings',$global);
			return apply_filters('shopp_cart_taxrate',$this->float($global['rate'])/100);
		}
		return false;
	}
	
	function float ($rate) {
		$format = $this->format;
		$format['precision'] = 3;
		return floatvalue($rate,true,$format);
	}
	
	/**
	 * Calculates total taxes
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return float Total tax amount
	 **/
	function calculate () {
		$Totals =& $this->Order->Cart->Totals;

		$tiers = array();
		$taxes = 0;
		foreach ($this->Order->Cart->contents as $id => &$Item) {
			if (!$Item->taxable) continue;
			$Item->taxrate = $this->rate($Item);
			
			if (!isset($tiers[$Item->taxrate])) $tiers[$Item->taxrate] = $Item->total;
			else $tiers[$Item->taxrate] += $Item->total;
			
			$taxes += $Item->tax;
		}
		
		if ($this->shipping) {
			if ($this->vat) // Remove the taxes from the shipping amount for inclusive-tax calculations
				$Totals->shipping = (floatvalue($Totals->shipping)/(1+$Totals->taxrate));
			$taxes += roundprice($Totals->shipping*$Totals->taxrate);
		}

		return $taxes;
	}
	
} // END class CartTax

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