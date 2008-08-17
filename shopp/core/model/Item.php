<?php
/**
 * Item class
 * Cart items
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 28 March, 2008
 * @package shopp
 **/

class Item {
	var $product;
	var $price;
	var $sku;
	var $type;
	var $name;
	var $brand;
	var $optionname;
	var $description;
	var $discount = 0;
	var $quantity = 0;
	var $unitprice = 0;
	var $total = 0;
	var $weight = 0;
	var $shipfee = 0;
	var $shipping = false;
	var $tax = false;

	function Item ($qty,&$Product,&$Price) {
		global $Shopp; // To access settings

		$Product->load_prices();
		$this->product = $Product->id;
		$this->price = $Price->id;
		$this->name = $Product->name;
		$this->brand = $Product->brand;
		$this->sku = $Price->sku;
		$this->type = $Price->type;
		$this->discount = 0; // Not implemented yet
		$this->quantity = $qty;
		$this->unitprice = (($Price->sale == "on")?$Price->saleprice:$Price->price);
		$this->total = $this->quantity * $this->unitprice;

		if (count($Product->prices) > 1) {
			$this->optionname = $Price->label;
			$this->options = $Product->prices;
		}

		if ($Price->shipping == "on" && $Price->type == "Shipped") {
			$this->shipping = true;
			$this->weight = $Price->weight;
			$this->shipfee = $Price->shipfee;
		}

		$this->tax = ($Price->tax == "on" && $Shopp->Settings->get('taxes') == "on")?true:false;
	}
	
	function quantity ($qty) {
		$this->quantity = $qty;
		$this->total = $this->quantity * $this->unitprice;
	}
	
	function add ($qty) {
		$this->quantity += $qty;
		$this->total = $this->quantity * $this->unitprice;
	}
	
	function options ($selected = "") {
		$string = "";
		foreach($this->options as $option) {
			if ($option->type != "N/A") {
				$price = money(($option->sale == "on")?$option->saleprice:$option->price);
				if ($selected == $option->id) $string .= "<option value=\"$option->id\" selected=\"\">$option->label ($price)</option>";
				else $string .= "<option value=\"$option->id\">$option->label ($price)</option>";
			}
		}
		return $string;
	}
	
	function shipping (&$Shipping) {
		
		
	}
	
	function tag ($id,$property,$options=array()) {
		global $Shopp;
		
		$uri = (SHOPP_PERMALINK)?$this->product:'&amp;shopp_pid='.$this->product;
		
		// Return strings with no options
		switch ($property) {
			case "name": return $this->name;
			case "url": return $Shopp->link('').$uri;
			case "brand": return $this->brand;
			case "sku": return $this->sku;
		}
		
		// Handle currency values
		$result = "";
		switch ($property) {
			case "unitprice": $result = $this->unitprice; break;
			case "total": $result = $this->total; break;
			case "tax": $result = $this->tax; break;
			case "total": $result = $this->data->Totals->total; break;
			
		}
		if (!empty($result)) {
			if (isset($options['currency']) && !value_is_true($options['currency'])) return $result;
			else return money($result);
		}
		
		// Handle values with complex options
		switch ($property) {
			case "quantity": 
				$size = 5;
				$class = "";
				$result = $this->quantity;
				$title = "";
				if (isset($options['input']) && valid_input($options['input'])) {
					if (isset($options['size'])) $size = $options['size'];
					if (isset($options['class'])) $class = ' class="'.$options['class'].'"';
					if (isset($options['title'])) $class = ' class="'.$options['title'].'"';
					$result = '<input type="'.$options['input'].'" name="items['.$id.']['.$property.']" id="items-'.$id.'-'.$property.'" title="'.$title.'" value="'.$this->quantity.'" size="'.$size.'"'.$class.' />';
				} else $result = $this->quantity;
				break;
			case "remove":
				$label = "Remove";
				if (isset($options['label'])) $label = $options['label'];
				if (isset($options['input'])) {
					switch ($options['input']) {
						case "button":
							$result = '<button type="submit" name="remove" value="remove" class="remove">'.$label.'</button>';
					}
				} else {
					$result = '<a href="'.SHOPP_CARTURL.'?cart=update&amp;item='.$id.'&amp;quantity=0">'.$label.'</a>';
				}
				
				
				break;
			case "options":
				$class = "";
				if (isset($options['class'])) $class = ' class="'.$options['class'].'" ';
				if (!empty($this->optionname)) {
					$result .= '<input type="hidden" name="items['.$id.'][product]" value="'.$this->product.'"/>';
					$result .= ' <select name="items['.$id.'][price]" id="items-'.$id.'-price"'.$class.'>';
					$result .= $this->options($this->price);
					$result .= '</select>';
				}
		}
		if (!empty($result)) return $result;
		
		
		return false;
	}

} // end Item class

?>