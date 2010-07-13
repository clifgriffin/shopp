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
	var $data = array();		// Custom input data
	var $quantity = 0;			// The selected quantity for the line item
	var $unitprice = 0;			// Per unit price
	var $price = 0;				// Per unit price after discounts are applied
	var $unittax = 0;			// Per unit tax amount
	var $tax = 0;				// Sum of the per unit tax amount for the line item
	var $taxrate = 0;			// Tax rate for the item
	var $total = 0;				// Total cost of the line item (unitprice x quantity)
	var $discount = 0;			// Discount applied to each unit
	var $discounts = 0;			// Sum of per unit discounts (discount for the line)
	var $weight = 0;			// Weight of the line item (unit weight)
	var $shipfee = 0;			// Shipping fees for each unit of the line item
	var $download = false;		// Download ID of the asset from the selected price object
	var $shipping = false;		// Shipping setting of the selected price object
	var $inventory = false;		// Inventory setting of the selected price object
	var $taxable = false;		// Taxable setting of the selected price object
	var $freeshipping = false;	// Free shipping status of the selected price object

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
			
		$addonsum = 0;
		if (isset($Product->addons) && $Product->addons == "on") 
			$this->addons($addonsum,$addons,$Product->prices);

		if (isset($Price->id))
			$this->option = $this->mapprice($Price);
		
		$this->sku = $Price->sku;
		$this->type = $Price->type;
		$this->sale = $Price->onsale;
		$this->freeshipping = $Price->freeshipping;
		// $this->saved = ($Price->price - $Price->promoprice);
		// $this->savings = ($Price->price > 0)?percentage($this->saved/$Price->price)*100:0;
		$this->unitprice = (($Price->onsale)?$Price->promoprice:$Price->price)+$addonsum;
		if ($this->type == "Donation")
			$this->donation = $Price->donation;
		$this->data = stripslashes_deep(attribute_escape_deep($data));
		
		// Map out the selected menu name and option
		if ($Product->variations == "on") {
			$selected = explode(",",$this->option->options); $s = 0;
			foreach ($Product->options as $i => $menu) {
				foreach($menu['options'] as $option) {
					if ($option['id'] == $selected[$s]) {
						$this->variation[$menu['name']] = $option['name']; break;
					}
				}
				$s++;
			}
		}

		if (!empty($Price->download)) $this->download = $Price->download;
		if ($Price->type == "Shipped") {
			$this->shipped = true;
			if ($Price->shipping == "on") {
				$this->weight = $Price->weight;
				$this->shipfee = $Price->shipfee;
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
		if ($this->inventory && $this->option->stock == 0) {
			new ShoppError(__('The product could not be added to the cart because it is not in stock.','cart_item_invalid',SHOPP_ERR));
			return false;
		}
		return true;
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

		$qty = preg_replace('/[^\d+]/','',$qty);
		if ($this->inventory) {
			if ($qty > $this->option->stock) {
				new ShoppError(__('Not enough of the product is available in stock to fulfill your request.','Shopp'),'item_low_stock');
				$this->quantity = $this->option->stock;
			}
			else $this->quantity = $qty;
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
			$currently = ($option->onsale)?$option->promoprice:$option->price;
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
			if ($price->type == "N/A") continue;
			$pricing = $this->mapprice($price);
			if ($pricing) $this->variations[] = $pricing;
		}		
	}
	
	/**
	 * Populates the addons from a collection of price objects
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param array $prices A list of Price objects
	 * @return void
	 **/
	function addons (&$sum,$addons,$prices) {
		foreach ($prices as $p)	{
			if ($p->type == "N/A" || !in_array($p->options,$addons)) continue;
			$pricing = $this->mapprice($p);
			if ($pricing) {
				$pricing->unitprice = (($p->onsale)?$p->promoprice:$p->price);
				$this->addons[] = $pricing;
				$sum += $pricing->unitprice;
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
		$map = array('id','type','label','onsale','promoprice','price','inventory','stock','options');
		$_ = new stdClass();
		foreach ($map as $property) {
			if (empty($price->options) && $property == 'label') continue;
			$_->{$property} = $price->{$property};
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
	 * Unstock the item from inventory
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function unstock () {
		if (!$this->inventory) return;
		global $Shopp;
		$db = DB::get();
		
		// Update stock in the database
		$table = DatabaseObject::tablename(Price::$table);
		$db->query("UPDATE $table SET stock=stock-{$this->quantity} WHERE id='{$this->priceline}' AND stock > 0");
		
		// Update stock in the model
		$this->option->stock -= $this->quantity;

		// Handle notifications
		$product = $this->name.' ('.$this->option->label.')';
		if ($this->option->stock == 0)
			return new ShoppError(sprintf(__('%s is now out-of-stock!','Shopp'),$product),'outofstock_warning',SHOPP_STOCK_ERR);
		
		if ($this->option->stock <= $Shopp->Settings->get('lowstock_level'))
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
		$stock = apply_filters('shopp_cartitem_stock',false,&$this);
		if ($stock !== false) return $stock;

		$table = DatabaseObject::tablename(Price::$table);
		$result = $db->query("SELECT stock FROM $table WHERE id='$this->priceline'");
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
		$this->price = ($this->unitprice-$this->discount);
		$this->unittax = ($this->price*$this->taxrate);
		$this->discounts = ($this->discount*$this->quantity);
		$this->tax = ($this->unittax*$this->quantity);
		$this->total = ($this->price * $this->quantity);
		// print_r($this);
	}

	/**
	 * Provides support for the shopp('cartitem') tags
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return mixed
	 **/
	function tag ($id,$property,$options=array()) {
		global $Shopp;

		// Return strings with no options
		switch ($property) {
			case "id": return $id;
			case "product": return $this->product;
			case "name": return $this->name;
			case "type": return $this->type;
			case "link":
			case "url": 
				return (SHOPP_PERMALINKS)?
					$Shopp->shopuri.$this->slug:
					add_query_arg('shopp_pid',$this->product,$Shopp->shopuri);
			case "sku": return $this->sku;
		}
		
		$taxes = isset($options['taxes'])?value_is_true($options['taxes']):null;
		if (in_array($property,array('price','newprice','unitprice','total','tax','options')))
			$taxes = shopp_taxrate($taxes,$this->taxable,$this) > 0?true:false;

		// Handle currency values
		$result = "";
		switch ($property) {
			case "discount": $result = (float)$this->discount; break;
			case "price":
			case "newprice": $result = (float)$this->price+($taxes?$this->unittax:0); break;
			case "unitprice": $result = (float)$this->unitprice+($taxes?$this->unittax:0); break;
			case "unittax": $result = (float)$this->unittax; break;
			case "discounts": $result = (float)$this->discounts; break;
			case "tax": $result = (float)$this->tax; break;
			case "total": $result = (float)$this->total+($taxes?$this->tax:0); break;
		}
		if (is_float($result)) {
			if (isset($options['currency']) && !value_is_true($options['currency'])) return $result;
			else return money($result);
		}
		
		// Handle values with complex options
		switch ($property) {
			case "taxrate": return percentage($this->taxrate*100,array('precision' => 1)); break;
			case "quantity": 
				$result = $this->quantity;
				if ($this->type == "Donation" && $this->donation['var'] == "on") return $result;
				if (isset($options['input']) && $options['input'] == "menu") {
					if (!isset($options['value'])) $options['value'] = $this->quantity;
					if (!isset($options['options'])) 
						$values = "1-15,20,25,30,35,40,45,50,60,70,80,90,100";
					else $values = $options['options'];
					
					if (strpos($values,",") !== false) $values = explode(",",$values);
					else $values = array($values);
					$qtys = array();
					foreach ($values as $value) {
						if (strpos($value,"-") !== false) {
							$value = explode("-",$value);
							if ($value[0] >= $value[1]) $qtys[] = $value[0];
							else for ($i = $value[0]; $i < $value[1]+1; $i++) $qtys[] = $i;
						} else $qtys[] = $value;
					}
					$result = '<select name="items['.$id.']['.$property.']">';
					foreach ($qtys as $qty) 
						$result .= '<option'.(($qty == $this->quantity)?' selected="selected"':'').' value="'.$qty.'">'.$qty.'</option>';
					$result .= '</select>';
				} elseif (isset($options['input']) && valid_input($options['input'])) {
					if (!isset($options['size'])) $options['size'] = 5;
					if (!isset($options['value'])) $options['value'] = $this->quantity;
					$result = '<input type="'.$options['input'].'" name="items['.$id.']['.$property.']" id="items-'.$id.'-'.$property.'" '.inputattrs($options).'/>';
				} else $result = $this->quantity;
				break;
			case "remove":
				$label = __("Remove");
				if (isset($options['label'])) $label = $options['label'];
				if (isset($options['class'])) $class = ' class="'.$options['class'].'"';
				else $class = ' class="remove"';
				if (isset($options['input'])) {
					switch ($options['input']) {
						case "button":
							$result = '<button type="submit" name="remove['.$id.']" value="'.$id.'"'.$class.' tabindex="">'.$label.'</button>'; break;
						case "checkbox":
						    $result = '<input type="checkbox" name="remove['.$id.']" value="'.$id.'"'.$class.' tabindex="" title="'.$label.'"/>'; break;
					}
				} else {
					$result = '<a href="'.href_add_query_arg(array('cart'=>'update','item'=>$id,'quantity'=>0),$Shopp->link('cart')).'"'.$class.'>'.$label.'</a>';
				}
				break;
			case "optionlabel": $result = $this->option->label; break;
			case "options":
				$class = "";
				if (!isset($options['before'])) $options['before'] = '';
				if (!isset($options['after'])) $options['after'] = '';
				if (isset($options['show']) && 
					strtolower($options['show']) == "selected") 
					return (!empty($this->option->label))?
						$options['before'].$this->option->label.$options['after']:'';
					
				if (isset($options['class'])) $class = ' class="'.$options['class'].'" ';
				if (count($this->variations) > 1) {
					$result .= $options['before'];
					$result .= '<input type="hidden" name="items['.$id.'][product]" value="'.$this->product.'"/>';
					$result .= ' <select name="items['.$id.'][price]" id="items-'.$id.'-price"'.$class.'>';
					$result .= $this->options($this->priceline);
					$result .= '</select>';
					$result .= $options['after'];
				}
				break;
			case "addons-list":
			case "addonslist":
				if (empty($this->addons)) return false;
				$prefix = "+"; $before = ""; $after = ""; $classes = ""; $excludes = array();
				if (!empty($options['class'])) $classes = ' class="'.$options['class'].'"';
				if (!empty($options['exclude'])) $excludes = explode(",",$options['exclude']);
				if (!empty($options['before'])) $before = $options['before'];
				if (!empty($options['after'])) $after = $options['after'];
				if (!empty($options['prefix'])) $prefix = $options['prefix'];

				$result .= $before.'<ul'.$classes.'>';
				foreach ($this->addons as $id => $addon) {
					if (in_array($addon->label,$excludes)) continue;
					$result .= '<li>'.($prefix?$prefix.' ':'').''.$addon->label.'</li>';
				}
				$result .= '</ul>'.$after;
				return $result;
				break;
			case "hasinputs": 
			case "has-inputs": return (count($this->data) > 0); break;
			case "inputs":			
				if (!isset($this->_data_loop)) {
					reset($this->data);
					$this->_data_loop = true;
				} else next($this->data);

				if (current($this->data) !== false) return true;
				else {
					unset($this->_data_loop);
					reset($this->data);
					return false;
				}
				break;
			case "input":
				$data = current($this->data);
				$name = key($this->data);
				if (isset($options['name'])) return $name;
				return $data;
				break;
			case "inputs-list":
			case "inputslist":
				if (empty($this->data)) return false;
				$before = ""; $after = ""; $classes = ""; $excludes = array();
				if (!empty($options['class'])) $classes = ' class="'.$options['class'].'"';
				if (!empty($options['exclude'])) $excludes = explode(",",$options['exclude']);
				if (!empty($options['before'])) $before = $options['before'];
				if (!empty($options['after'])) $after = $options['after'];
				
				$result .= $before.'<ul'.$classes.'>';
				foreach ($this->data as $name => $data) {
					if (in_array($name,$excludes)) continue;
					$result .= '<li><strong>'.$name.'</strong>: '.$data.'</li>';
				}
				$result .= '</ul>'.$after;
				return $result;
				break;
			case "thumbnail":
				$defaults = array(
					'class' => '',
					'width' => 48,
					'height' => 48,
					'fit' => false,
					'sharpen' => false,
					'quality' => false,
					'bg' => false,
					'alt' => false,
					'title' => false
				);
				
				$options = array_merge($defaults,$options);
				extract($options);

				if (isset($this->image)) {
					$img = $this->image;

					$scale = (!$fit)?false:esc_attr($fit);
					$sharpen = (!$sharpen)?false:esc_attr(min($sharpen,$img->_sharpen));
					$quality = (!$quality)?false:esc_attr(min($quality,$img->_quality));
					$fill = (!$bg)?false:esc_attr(hexdec(ltrim($bg,'#')));
					$scaled = $img->scaled($width,$height,$scale);

					$alt = empty($alt)?$img->alt:$alt;
					$title = empty($title)?$img->title:$title;
					$title = empty($title)?'':' title="'.esc_attr($title).'"';
					$class = !empty($class)?' class="'.esc_attr($class).'"':'';

					if (!empty($options['title'])) $title = ' title="'.esc_attr($options['title']).'"';
					$alt = esc_attr(!empty($img->alt)?$img->alt:$this->name);
					return '<img src="'.add_query_string($img->resizing($width,$height,$scale,$sharpen,$quality,$fill),$Shopp->imguri.$img->id).'"'.$title.' alt="'.$alt.'" width="'.$scaled['width'].'" height="'.$scaled['height'].'"'.$class.' />'; 
				}
				break;
				
		}
		if (!empty($result)) return $result;
		
		
		return false;
	}

} // END class Item

?>