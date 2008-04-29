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

		if ($Price->shipping == "on") {
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
			if ($selected == $option->id) $string .= "<option value=\"$option->id\" selected=\"\">$option->label</option>";
			else $string .= "<option value=\"$option->id\">$option->label</option>";
		}
		return $string;
	}
	
	function shipping (&$Shipping) {
		
		
	}
	
	function tag ($id,$property,$options=array()) {
		// Return strings with no options
		switch ($property) {
			case "name": return $this->name;
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
				$size = 3;
				$class = "";
				$result = $this->quantity;
				$title = "";
				if (isset($options['size'])) $size = $options['size'];
				if (isset($options['class'])) $class = ' class="'.$options['class'].'"';
				if (isset($options['title'])) $class = ' class="'.$options['title'].'"';
				if (isset($options['input']) && valid_input($options['input']))
					$result = '<input type="'.$options['input'].'" name="items['.$id.']['.$property.']" id="items['.$id.']['.$property.']" title="'.$title.'" value="'.$this->quantity.'" size="'.$size.'"'.$class.' />';
				break;
			case "remove":
				$label = "Remove";
				if (isset($options['label'])) $label = $options['label'];
				
				
				$result = '<a href="'.SHOPP_CARTURL.'?cart=update&amp;item='.$id.'&amp;quantity=0">'.$label.'</a>';
				
				break;
			case "options":
				$class = "";
				if (isset($options['class'])) $class = ' class="'.$options['class'].'" ';
				if (!empty($this->optionname)) {
					$result .= '&nbsp;<select name="items['.$id.'][price]" id="items['.$id.'][price]"'.$class.'>';
					$result .= $this->options($this->price);
					$result .= '</select>';
				}
		}
		if (!empty($result)) return $result;
		
		
		return false;
	}

} // end Item class

?>