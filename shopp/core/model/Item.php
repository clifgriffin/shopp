<?php
/**
 * Item.php
 *
 * Cart line items generated from product/price objects
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 28 March, 2008
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @since 1.0
 * @subpackage cart
 **/
class Item {
	var $product = false;		// The source product ID
	var $priceline = false;		// The source price ID
	var $category = false;		// The breadcrumb category
	var $sku = false;			// The SKU of the product/price combination
	var $type = false;			// The type of the product price object
	var $name = false;			// The name of the source product
	var $description = false;	// Short description from the product summary
	var $option = false;		// The option ID of the price object
	var $variation = array();	// The selected variation
	var $variations = array();	// The available variation options
	var $addons = array();		// The addons added to the item
	var $image = false;			// The cover image for the product
	var $data = array();		// Custom input data
	var $quantity = 0;			// The selected quantity for the line item
	var $addonsum = 0;			// The sum of selected addons
	var $unitprice = 0;			// Per unit price
	var $priced = 0;			// Per unit price after discounts are applied
	var $totald = 0;			// Total price after discounts
	var $unittax = 0;			// Per unit tax amount
	var $pricedtax = 0;			// Per unit tax amount after discounts are applied
	var $tax = 0;				// Sum of the per unit tax amount for the line item
	var $taxrate = 0;			// Tax rate for the item
	var $total = 0;				// Total cost of the line item (unitprice x quantity)
	var $discount = 0;			// Discount applied to each unit
	var $discounts = 0;			// Sum of per unit discounts (discount for the line)
	var $weight = 0;			// Unit weight of the line item (unit weight)
	var $length = 0;			// Unit length of the line item (unit length)
	var $width = 0;				// Unit width of the line item (unit width)
	var $height = 0;			// Unit height of the line item (unit height)
	var $shipfee = 0;			// Shipping fees for each unit of the line item
	var $download = false;		// Download ID of the asset from the selected price object
	var $shipping = false;		// Shipping setting of the selected price object
	var $shipped = false;		// Shipped flag when the item needs shipped
	var $inventory = false;		// Inventory setting of the selected price object
	var $taxable = false;		// Taxable setting of the selected price object
	var $freeshipping = false;	// Free shipping status of the selected price object
	var $packaging = "off";		// Should the item be packaged separately

	/**
	 * Constructs a line item from a Product object and identified price object
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param object $Product Product object
	 * @param mixed $pricing A list of price IDs; The option key of a price object; or a Price object
	 * @param int $category (optional)The breadcrumb category ID where the product was added from
	 * @param array $data (optional) Custom data associated with the line item
	 * @param array $addons (optional) A set of addon options
	 * @return void
	 **/
	function __construct ($Product,$pricing,$category=false,$data=array(),$addons=array()) {

		$Product->load_data(array('prices','images','categories','tags','specs'));

		// If product variations are enabled, disregard the first priceline
		if ($Product->variations == "on") array_shift($Product->prices);

		// If option ids are passed, lookup by option key, otherwise by id
		if (is_array($pricing)) {
			$Price = $Product->pricekey[$Product->optionkey($pricing)];
			if (empty($Price)) $Price = $Product->pricekey[$Product->optionkey($pricing,true)];
		} elseif ($pricing !== false) {
			$Price = $Product->priceid[$pricing];
		} else {
			foreach ($Product->prices as &$Price)
				if ($Price->type != "N/A" &&
					(!$Price->stocked ||
					($Price->stocked && $Price->stock > 0))) break;
		}
		if (isset($Product->id)) $this->product = $Product->id;
		if (isset($Price->id)) $this->priceline = $Price->id;

		$this->name = $Product->name;
		$this->slug = $Product->slug;

		$this->category = $category;
		$this->categories = $this->namelist($Product->categories);
		$this->tags = $this->namelist($Product->tags);
		$this->image = current($Product->images);
		$this->description = $Product->summary;

		if ($Product->variations == "on")
			$this->variations($Product->prices);

		if (isset($Product->addons) && $Product->addons == "on")
			$this->addons($this->addonsum,$addons,$Product->prices);

		if (isset($Price->id))
			$this->option = $this->mapprice($Price);

		$this->sku = $Price->sku;
		$this->type = $Price->type;
		$this->sale = $Price->onsale;
		$this->freeshipping = $Price->freeshipping;
		// $this->saved = ($Price->price - $Price->promoprice);
		// $this->savings = ($Price->price > 0)?percentage($this->saved/$Price->price)*100:0;
		$this->unitprice = (($Price->onsale)?$Price->promoprice:$Price->price)+$this->addonsum;
		if ($this->type == "Donation")
			$this->donation = $Price->donation;

		$this->data = stripslashes_deep(esc_attrs($data));
		$this->recurrences();

		// Map out the selected menu name and option
		if ($Product->variations == "on") {
			$selected = explode(",",$this->option->options); $s = 0;
			$variants = isset($Product->options['v'])?$Product->options['v']:$Product->options;
			foreach ($variants as $i => $menu) {
				foreach($menu['options'] as $option) {
					if ($option['id'] == $selected[$s]) {
						$this->variation[$menu['name']] = $option['name']; break;
					}
				}
				$s++;
			}
		}

		$packaging_meta = new MetaObject();
		$packaging_meta->load(array('context'=>'product','parent'=>$Product->id,'type'=>'meta','name'=>'packaging'));
		$this->packaging = ($packaging_meta->id && $packaging_meta->value == "on" ? "on" : "off");

		if (!empty($Price->download)) $this->download = $Price->download;
		if ($Price->type == "Shipped") {
			$this->shipped = true;
			if ($Price->shipping == "on") {
				$this->weight = $Price->weight;
				if (isset($Price->dimensions)) {
					foreach ((array)$Price->dimensions as $dimension => $value)
						$this->$dimension = $value;
				}
				$this->shipfee = $Price->shipfee;
				if (isset($Product->addons) && $Product->addons == "on")
					$this->addons($this->shipfee,$addons,$Product->prices,'shipfee');
			} else $this->freeshipping = true;
		}
		$Settings = ShoppSettings();
		$this->inventory = ($Price->inventory == "on")?true:false;
		$this->taxable = ($Price->tax == "on" && $Settings->get('taxes') == "on")?true:false;
	}

	/**
	 * Validates the line item
	 *
	 * Ensures the product and price object exist in the catalog and that
	 * inventory is available for the selected price variation.
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return boolean
	 **/
	function valid () {
		if (!$this->product || !$this->priceline) {
			new ShoppError(__('The product could not be added to the cart because it could not be found.','cart_item_invalid',SHOPP_ERR));
			return false;
		}
		if ($this->inventory && !$this->instock()) {
			new ShoppError(__('The product could not be added to the cart because it is not in stock.','cart_item_invalid',SHOPP_ERR));
			return false;
		}
		return true;
	}

	/**
	 * Provides the polynomial fingerprint of the item for detecting uniqueness
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return string
	 **/
	function fingerprint () {
		$_  = array($this->product,$this->priceline);
		if (!empty($this->addons))	$_[] = serialize($this->addons);
		if (!empty($this->data))	$_[] = serialize($this->data);
		return crc32(join('',$_));
	}

	/**
	 * Sets the quantity of the line item
	 *
	 * Sets the quantity only if stock is available or
	 * the donation amount to the donation minimum.
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param int $qty The quantity to set the line item to
	 * @return void
	 **/
	function quantity ($qty) {

		if ($this->type == "Donation" && $this->donation['var'] == "on") {
			if ($this->donation['min'] == "on" && floatvalue($qty) < $this->unitprice)
				$this->unitprice = $this->unitprice;
			else $this->unitprice = floatvalue($qty,false);
			$this->quantity = 1;
			$qty = 1;
		}

		if ($this->type == "Subscription" || $this->type == "Membership") {
			$this->quantity = 1;
			return;
		}

		$qty = preg_replace('/[^\d+]/','',$qty);
		if ($this->inventory) {
			$levels = array($this->option->stock);
			foreach ($this->addons as $addon) // Take into account stock levels of any addons
				if ($addon->inventory == "on") $levels[] = $addon->stock;
			if ($qty > min($levels)) {
				new ShoppError(__('Not enough of the product is available in stock to fulfill your request.','Shopp'),'item_low_stock');
				$this->quantity = min($levels);
			} else $this->quantity = $qty;
		} else $this->quantity = $qty;

		$this->retotal();
	}

	/**
	 * Adds a specified quantity to the line item
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	function add ($qty) {
		if ($this->type == "Donation" && $this->donation['var'] == "on") {
			$qty = floatvalue($qty);
			$this->quantity = $this->unitprice;
		}
		$this->quantity($this->quantity+$qty);
	}

	/**
	 * Generates an option menu of available price variations
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param int $selection (optional) The selected price option
	 * @param float $taxrate (optional) The tax rate to apply to pricing information
	 * @return string
	 **/
	function options ($selection = "") {
		if (empty($this->variations)) return "";

		$string = "";
		foreach($this->variations as $option) {
			if ($option->type == "N/A") continue;
			$currently = ($option->onsale?$option->promoprice:$option->price)+$this->addonsum;
			$difference = (float)($currently+$this->unittax)-($this->unitprice+$this->unittax);

			$price = '';
			if ($difference > 0) $price = '  (+'.money($difference).')';
			if ($difference < 0) $price = '  (-'.money(abs($difference)).')';

			$selected = "";
			if ($selection == $option->id) $selected = ' selected="selected"';
			$disabled = "";
			if ($option->inventory == "on" && $option->stock < $this->quantity)
				$disabled = ' disabled="disabled"';

			$string .= '<option value="'.$option->id.'"'.$selected.$disabled.'>'.$option->label.$price.'</option>';
		}
		return $string;

	}

	/**
	 * Populates the variations from a collection of price objects
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param array $prices A list of Price objects
	 * @return void
	 **/
	function variations ($prices) {
		foreach ($prices as $price)	{
			if ($price->type == "N/A" || $price->context != "variation") continue;
			$pricing = $this->mapprice($price);
			if ($pricing) $this->variations[] = $pricing;
		}
	}

	/**
	 * Sums values of the applied addons properties
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param array $prices A list of Price objects
	 * @return void
	 **/
	function addons (&$sum,$addons,$prices,$property='pricing') {
		foreach ($prices as $p)	{
			if ($p->type == "N/A" || $p->context != "addon") continue;
			$pricing = $this->mapprice($p);
			if (empty($pricing) || !in_array($pricing->options,$addons)) continue;
			if ($property == "pricing") {
				$pricing->unitprice = (($p->onsale)?$p->promoprice:$p->price);
				$this->addons[] = $pricing;
				$sum += $pricing->unitprice;

			} else {
				if (isset($pricing->$property)) $sum += $pricing->$property;
			}
		}
	}

	/**
	 * Maps price object properties
	 *
	 * Populates only the necessary properties from a price object
	 * to a variation option to cut down on line item data size
	 * for better serialization performance.
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param Object $price Price object to minimize
	 * @return object An Item variation object
	 **/
	function mapprice ($price) {
		$map = array(
			'id','type','label','onsale','promoprice','price',
			'inventory','stock','sku','options','dimensions',
			'shipfee','download','recurring'
		);
		$_ = new stdClass();
		foreach ($map as $property) {
			if (empty($price->options) && $property == 'label') continue;
			if (isset($price->{$property})) $_->{$property} = $price->{$property};
		}
		return $_;
	}

	/**
	 * Collects a list of name properties from a list of objects
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param array $items List of objects with a name property to grab
	 * @return array List of names
	 **/
	function namelist ($items) {
		$list = array();
		foreach ($items as $item) $list[$item->id] = $item->name;
		return $list;
	}

	/**
	 * Sets the current subscription payment plan status
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @return void
	 **/
	function recurrences () {
		if (empty($this->option->recurring)) return;
		extract($this->option->recurring);

		$ps = Price::periods();
		$periods = array();
		foreach ($ps as $i => $p) {
			$periods[$i] = array();
			foreach ($p as $r) $periods[$i][$r['value']] = $r['label'];
		}

		$subscription = array();
		if ($trial == 'on') {
			$singular = (int)($trialint==1);
			$periodLabel = $periods[$singular][$trialperiod];
			$price = $trialprice > 0?money($trialprice):__('Free','Shopp');
			$for = __('for','Shopp');
			$subscription[] = "$price $for $trialint $periodLabel";
		}

		$singular = (int)($interval==1);
		$periodLabel = $periods[$singular][$period];
		$price = $this->unitprice > 0?money($this->unitprice):__('Free','Shopp');
		$for = __('for','Shopp');
		$subscription[] = "$price $for $interval $periodLabel";

		$this->data['Subscription'] = $subscription;
	}

	/**
	 * Unstock the item from inventory
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	function unstock () {
		if (!$this->inventory) return;
		$db = DB::get();
		$Settings =& ShoppSettings();

		// Update stock in the database
		$pricetable = DatabaseObject::tablename(Price::$table);
		if ($db->query("UPDATE $pricetable SET stock=stock-{$this->quantity} WHERE id='{$this->priceline}' AND stock > 0")) {
			$producttable = DatabaseObject::tablename(Product::$table);
			$db->query("UPDATE $producttable SET stock=stock-{$this->quantity} WHERE id='{$this->product}' AND stock > 0");
		}

		if (!empty($this->addons)) {
			foreach ($this->addons as &$Addon) {
				$db->query("UPDATE $table SET stock=stock-{$this->quantity} WHERE id='{$Addon->id}' AND stock > 0");
				$Addon->stock -= $this->quantity;
				$product_addon = "$product ($Addon->label)";
				if ($Addon->stock == 0)
					new ShoppError(sprintf(__('%s is now out-of-stock!','Shopp'),$product_addon),'outofstock_warning',SHOPP_STOCK_ERR);
				elseif ($Addon->stock <= $Settings->get('lowstock_level'))
					return new ShoppError(sprintf(__('%s has low stock levels and should be re-ordered soon.','Shopp'),$product_addon),'lowstock_warning',SHOPP_STOCK_ERR);

			}
		}

		// Update stock in the model
		$this->option->stock = max(0, (int) $this->option->stock - $this->quantity);

		// Handle notifications
		$product = "$this->name (".$this->option->label.")";
		if ($this->option->stock == 0)
			return new ShoppError(sprintf(__('%s is now out-of-stock!','Shopp'),$product),'outofstock_warning',SHOPP_STOCK_ERR);

		if ($this->option->stock <= $Settings->get('lowstock_level'))
			return new ShoppError(sprintf(__('%s has low stock levels and should be re-ordered soon.','Shopp'),$product),'lowstock_warning',SHOPP_STOCK_ERR);

	}

	/**
	 * Verifies the item is in stock
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return boolean
	 **/
	function instock () {
		if (!$this->inventory) return true;
		$this->option->stock = $this->getstock();
		return $this->option->stock >= $this->quantity;
	}

	/**
	 * Determines the stock level of the line item
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return int The amount of stock available
	 **/
	function getstock () {
		$db = DB::get();
		$stock = apply_filters('shopp_cartitem_stock',false,$this);
		if ($stock !== false) return $stock;

		$table = DatabaseObject::tablename(Price::$table);
		$ids = array($this->priceline);
		if (!empty($this->addons)) foreach ($this->addons as $addon) $ids[] = $addon->id;
		$result = $db->query("SELECT min(stock) AS stock FROM $table WHERE 0 < FIND_IN_SET(id,'".join(',',$ids)."')");
		if (isset($result->stock)) return $result->stock;

		return $this->option->stock;
	}

	/**
	 * Match a rule to the item
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param array $rule A structured rule array
	 * @return boolean
	 **/
	function match ($rule) {
		extract($rule);

		switch($property) {
			case "Any item name": $subject = $this->name; break;
			case "Any item quantity": $subject = (int)$this->quantity; break;
			case "Any item amount": $subject = $this->total; break;

			case "Name": $subject = $this->name; break;
			case "Category": $subject = $this->categories; break;
			case "Tag name": $subject = $this->tags; break;
			case "Variation": $subject = $this->option->label; break;

			// case "Input name": $subject = $Item->option->label; break;
			// case "Input value": $subject = $Item->option->label; break;

			case "Quantity": $subject = $this->quantity; break;
			case "Unit price": $subject = $this->unitprice; break;
			case "Total price": $subject = $this->total; break;
			case "Discount amount": $subject = $this->discount; break;

		}
		return Promotion::match_rule($subject,$logic,$value,$property);
	}

	function taxrule ($rule) {
		switch ($rule['p']) {
			case "product-name": return ($rule['v'] == $this->name); break;
			case "product-tags": return (in_array($rule['v'],$this->tags)); break;
			case "product-category": return (in_array($rule['v'],$this->categories)); break;
		}
		return false;
	}

	/**
	 * Recalculates line item amounts
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	function retotal () {
		$this->taxrate = shopp_taxrate(true,$this->taxable,$this);

		$this->priced = ($this->unitprice-$this->discount);
		$this->unittax = ($this->unitprice*$this->taxrate);
		$this->pricedtax = ($this->priced*$this->taxrate);
		$this->discounts = ($this->discount*$this->quantity);
		$this->tax = (($this->priced*$this->taxrate)*$this->quantity);
		$this->total = ($this->unitprice * $this->quantity);
		$this->totald = ($this->priced * $this->quantity);

	}

	/**
	 * Provides support for the shopp('cartitem') tags
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * @deprecated 1.2
	 * @return mixed
	 **/
	function tag ($id,$property,$options=array()) {
		$options['return'] = 'on';
		return shopp('cartitem',$property,$options);
	}

} // END class Item

?>