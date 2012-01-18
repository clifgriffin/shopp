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

require('Item.php');

class Cart {

	// properties
	var $contents = array();	// The contents (Items) of the cart
	var $shipped = array();		// Reference list of shipped Items
	var $downloads = array();	// Reference list of digital Items
	var $recurring = array();	// Reference list of recurring Items
	var $discounts = array();	// List of promotional discounts applied
	var $promocodes = array();	// List of promotional codes applied
	var $shipping = array();	// List of shipping options
	var $processing = array();	// Min-Max order processing timeframe

	// Object properties
	var $Added = false;			// Last Item added
	var $Totals = false;		// Cart Totals data structure

	var $freeship = false;
	var $showpostcode = false;	// Flag to show postcode field in shipping estimator

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
					$quantity = ( ! isset($product['quantity']) ||
									( empty($product['quantity']) && $product['quantity'] !== 0 )
								) ? 1 : $product['quantity']; // Add 1 by default
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
				if (!empty($this->contents)) $this->remove(key($_REQUEST['remove']));
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

		if ('html' == strtolower($_REQUEST['response'])) {
			echo shopp('cart','get-sidecart');
			exit();
		}
		$AjaxCart = new StdClass();
		$AjaxCart->url = shoppurl(false,'cart');
		$AjaxCart->label = __('Edit shopping cart','Shopp');
		$AjaxCart->checkouturl = shoppurl(false,'checkout',ShoppOrder()->security());
		$AjaxCart->checkoutLabel = __('Proceed to Checkout','Shopp');
		$AjaxCart->imguri = '' != get_option('permalink_structure')?trailingslashit(shoppurl('images')):shoppurl().'&siid=';
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
	function add ($quantity,&$Product,&$Price,$category=false,$data=array(),$addons=array()) {

		$NewItem = new Item($Product,$Price,$category,$data,$addons);
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
		$Totals->discount = ($Totals->discount > $Totals->subtotal)?$Totals->subtotal:$Totals->discount;

		// Calculate taxes
		$Tax = new CartTax();
		$Totals->taxrate = $Tax->rate();
		$Totals->tax = $Tax->calculate();

		// Calculate final totals
		$amounts = array($Totals->subtotal,$Totals->discount*-1,$Totals->shipping,$Totals->tax);
		$amounts = array_map('roundprice',$amounts);
		$Totals->total = array_sum($amounts);

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
		return $this->_typeitems('shipped');
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
		return $this->_typeitems('downloads');
	}

	/**
	 * Finds recurring payment items in the cart and builds a reference list
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @return boolean True if there are recurring payment items in the cart
	 **/
	function recurring () {
		return $this->_typeitems('recurring');
	}


	private function _typeitems ($type) {
		$types = array('shipped','downloads','recurring');
		if (!in_array($type,$types)) return false;

		$filter = "_filter_$type";
		$items = array_filter($this->contents,array(&$this,$filter));

		$this->$type = array();
		foreach ($items as $key => $item)
			$this->{$type}[$key] =& $this->contents[$key];

		return (!empty($this->$type));
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
	 * Helper method to identify digital items in the cart
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return boolean
	 **/
	private function _filter_downloads ($item) {
		return ($item->download);
	}

	/**
	 * Helper method to identify recurring payment items in the cart
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @return boolean
	 **/
	private function _filter_recurring ($item) {
		return ($item->recurring);
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

		// Already loaded
		if (!empty($this->promotions)) return true;

		$promos = DatabaseObject::tablename(Promotion::$table);
		$datesql = Promotion::activedates();
		$query = "SELECT * FROM $promos WHERE status='enabled' AND $datesql ORDER BY target DESC";

		$loaded = DB::query($query,'array','index','target',true);
		$cartpromos = array('Cart','Cart Item');
		$this->promotions = array();

		foreach ($cartpromos as $type)
			if (isset($loaded[$type]))
				$this->promotions = array_merge($this->promotions,$loaded[$type]);

		if (isset($loaded['Catalog'])) {
			$promos = array();
			foreach ($loaded['Catalog'] as $promo)
				$promos[ sanitize_title_with_dashes($promo->name) ] = array($promo->id,$promo->name);

			shopp_set_setting('active_catalog_promos',$promos);
		}

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
		$this->limit = shopp_setting('promo_limit');
		$baseop = shopp_setting('base_operations');
		$this->precision = $baseop['currency']['format']['precision'];

		$this->Order = &$Shopp->Order;
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

		$sum = array();
		foreach ($this->Cart->discounts as $Discount) {
			if (isset($Discount->items) && !empty($Discount->items)) {
				foreach ($Discount->items as $id => $amount) {

					if (isset($this->Cart->contents[$id])) {
						$Item = $this->Cart->contents[$id];

						if (shopp_setting_enabled('tax_inclusive') && 'Buy X Get Y Free' == $Discount->type) {
							// Specialized line item for inclusive tax model buy X get Y free discounts [bug #806]
							$Item->retotal();
							$Item->discounts += $amount; // total line item discount
						} else {
							$Item->discount += $amount; // unit discount
							$Item->retotal();
						}

						if ( $Item->discounts ) $Discount->applied += $Item->discounts; // total line item discount
						$sum[$id] = $Item->discounts;
					}

				}
			} else {
				$sum[] = $Discount->applied;
			}
		}

		$discount = array_sum($sum);
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

		usort($this->promos,array(&$this,'_active_discounts'));

		// Iterate over each promo to determine whether it applies
		$discount = 0;
		foreach ($this->promos as &$promo) {
			$applypromo = false;
			if (!is_array($promo->rules))
				$promo->rules = unserialize($promo->rules);

			// If promotion limit has been reached and the promo has
			// not already applied as a cart discount, cancel the loop
			if ($this->limit > 0 && count($this->Cart->discounts)+1 > $this->limit
				&& !isset($this->Cart->discounts[$promo->id])) {
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
				if ($index === "item") continue;
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

				$this->remove($promo->id);	// Remove it from the discount stack if it is there

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
						$promo->freeshipping = $this->Cart->Totals->shipping;
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
				if ('Donation' == $Item->type) continue;
				$matches = 0;
				foreach ($promo->rules['item'] as $rule) {
					if (!in_array($rule['property'],$this->cartitemprops)) continue;
					if ($Item->match($rule) && !isset($promo->items[$id])) $matches++;
				} // endforeach $promo->rules['item']

				if ($matches == count($promo->rules['item'])) { // all conditions must match

					// These must result in the discount applied to the *unit price*!
					switch ($promo->type) {
						case "Percentage Off": $discount = $Item->unitprice*($promo->discount/100); break;
						case "Amount Off": $discount = $promo->discount; break;
						case "Free Shipping": $discount = 0; $Item->freeshipping = true; break;
						case "Buy X Get Y Free":
							// With inclusive tax model, the discount must be applied to the line item discounts [bug #806]
							// The exclusive tax model needs a pre-tax unit price discount to avoid tax on the free item(s)
							if (shopp_setting_enabled('tax_inclusive'))
								$discount = $promo->getqty * ($Item->unitprice + $Item->unittax);
							else $discount = $Item->unitprice*( $promo->getqty / ($promo->buyqty + $promo->getqty ));
							break;
					}
					$promo->items[$id] = $discount;
				}
			}

			if ($promo->applied == 0 && empty($promo->items)) {
				if (isset($this->Cart->discounts[$promo->id]))
					unset($this->Cart->discounts[$promo->id]);
				return;
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
	 * Removes an applied discount
	 *
	 * @author Jonathan Davis
	 * @since 1.1.5
	 *
	 * @param int $id The promo id to remove
	 * @return boolean True if successfully removed
	 **/
	function remove ($id) {
		if (!isset($this->Cart->discounts[$id])) return false;

		unset($this->Cart->discounts[$id]);
		return true;
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
	 * Helper method to sort active discounts before other promos
	 *
	 * Sorts active discounts to the top of the available promo list
	 * to enable efficient promo limit enforcement
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	function _active_discounts ($a,$b) {
		$_ =& $this->Cart->discounts;
		return (isset($_[$a->id]) && !isset($_[$b->id]))?-1:1;
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

		$this->Cart = &$Shopp->Order->Cart;
		$this->modules = &$Shopp->Shipping->active;
		$this->Shipping = &$Shopp->Order->Shipping;
		$this->Shipping->locate();

		$this->showpostcode = $Shopp->Shipping->postcodes;

		$this->handling = shopp_setting('order_shipfee');
		$this->realtime = $Shopp->Shipping->realtime;

	}

	function status () {
		// If shipping is disabled, bail
		if (!shopp_setting_enabled('shipping')) return false;
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

		$this->Cart->processing = array();
		foreach ($this->Cart->shipped as $id => &$Item) {
			if ($Item->freeshipping) continue;
			// Calculate any product-specific shipping fee markups
			if ($Item->shipfee > 0) $this->fees += ($Item->quantity * $Item->shipfee);
			$this->Cart->processing['min'] = ShippingFramework::daytimes($this->Cart->processing['min'],$Item->processing['min']);
			$this->Cart->processing['max'] = ShippingFramework::daytimes($this->Cart->processing['max'],$Item->processing['max']);

			// Run shipping module item calculations
			do_action_ref_array('shopp_calculate_item_shipping',array($id,&$Item));
		}

		// Add order handling fee
		if ($this->handling > 0) $this->fees += $this->handling;

		// Run shipping module aggregate shipping calculations
		do_action_ref_array('shopp_calculate_shipping',array(&$this->options,$Shopp->Order));

		// No shipping options were generated, try fallback calculators for realtime rate failures
		if (empty($this->options)) {
			if ($this->realtime) {
				do_action('shopp_calculate_fallback_shipping_init');
				do_action_ref_array('shopp_calculate_fallback_shipping',array(&$this->options,$Shopp->Order));
			}
			if (empty($this->options)) return false; // Still no rates, bail
		}

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

		// Always return the selected shipping option if a valid/available method has been set
		if (empty($this->Shipping->method)
			|| !isset($this->options[$this->Shipping->method]))
				$this->Shipping->method = $estimate->slug;

		$amount = $this->options[$this->Shipping->method]->amount;
		$this->Cart->freeshipping = ($amount == 0);

		// Return the estimated amount
		return $amount;
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
		$base = shopp_setting('base_operations');
		$this->format = $base['currency']['format'];
		$this->inclusive = shopp_setting_enabled('tax_inclusive');
		$this->enabled = shopp_setting_enabled('taxes');
		$this->rates = shopp_setting('taxrates');
		$this->shipping = shopp_setting_enabled('tax_shipping');
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
		$country = $zone = $locale = $global = false;
		if ( ! $this->Order->Cart->shipped() ) { // Use billing address
			$country = $Billing->country;
			$zone = $Billing->state;
			if ( isset($Billing->locale) ) $locale = $Billing->locale;
		} else {
			$country = $Shipping->country;
			$zone = $Shipping->state;
			if ( isset($Billing->locale) ) $locale = $Billing->locale; // exception for locale
		}

		foreach ($this->rates as $setting) {
			$rate = false;
			if (isset($setting['locals']) && is_array($setting['locals'])) {
				$localmatch = true;
				if ( $country != $setting['country'] ) $localmatch = false;
				if ( isset($setting['zone']) && !empty($setting['zone']) && $zone != $setting['zone'] ) $localmatch = false;
				if ( $localmatch ) {
					$localrate = isset($setting['locals'][$locale])?$setting['locals'][$locale]:0;
					$rate = ($this->float($setting['rate'])+$this->float($localrate));
				}
			} elseif (isset($setting['zone']) && !empty($setting['zone'])) {
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
			if (!$Item->istaxed) continue;
			$Item->taxrate = $this->rate($Item);

			if (!isset($tiers[$Item->taxrate])) $tiers[$Item->taxrate] = $Item->total;
			else $tiers[$Item->taxrate] += $Item->total;

			$taxes += $Item->tax;
		}

		if ($this->shipping) {
			if ($this->inclusive) // Remove the taxes from the shipping amount for inclusive-tax calculations
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
 * @version 1.2
 * @package shopp
 * @subpackage cart
 **/
class ShippingOption {

	var $name;				// Name of the shipping option
	var $slug;				// URL-safe name of the shipping option @since 1.2
	var $amount;			// Amount (cost) of the shipping option
	var $delivery;			// Estimated delivery of the shipping option
	var $estimate;			// Include option in estimate
	var $items = array();	// Item shipping rates for this shipping option

	/**
	 * Builds a shipping option from a configured/calculated
	 * shipping rate array
	 *
	 * Example:
	 * new ShippingOption(array(
	 * 		'name' => 'Name of Shipping Rate Method',
	 * 		'slug' => 'rate-method-slug',
	 * 		'amount' => 0.99,
	 * 		'delivery' => '1d-2d',
	 * 		'items' => array(
	 * 			0 => 0.99,
	 * 			1 => 0.50
	 * 		)
	 * ));
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param array $rate The calculated shipping rate
	 * @param boolean $estimate Flag to be included/excluded from estimates
	 * @return void
	 **/
	function __construct ($rate,$estimate=true) {

		if (!isset($rate['slug'])) // Fire off an error if the slug is not provided
			return ( ! new ShoppError('A slug (string) property is required in the rate parameter when constructing a new ShippingOption','shopp_dev_err',SHOPP_DEBUG_ERR) );

		$this->name = $rate['name'];
		$this->slug = $rate['slug'];
		$this->amount = $rate['amount'];
		$this->estimate = $estimate;
		if (!empty($rate['delivery']))
			$this->delivery = $rate['delivery'];
		if (!empty($rate['items']))
			$this->items = $rate['items'];
	}

} // END class ShippingOption

?>