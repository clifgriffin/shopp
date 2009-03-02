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
	var $category;
	var $sku;
	var $type;
	var $name;
	var $description;
	var $optionlabel;
	var $option = false;
	var $options = array();
	var $saved = 0;
	var $savings = 0;
	var $quantity = 0;
	var $unitprice = 0;
	var $total = 0;
	var $weight = 0;
	var $shipfee = 0;
	var $tax = 0;
	var $download = false;
	var $shipping = false;
	var $inventory = false;
	var $taxable = false;

	function Item ($Product,$pricing,$category,$data=array()) {
		global $Shopp; // To access settings

		$Product->load_data(array('prices','images'));

		// If product variations are enabled, disregard the first priceline
		if ($Product->variations == "on") array_shift($Product->prices);

		// If option ids are passed, lookup by option key, otherwise by id
		if (is_array($pricing)) $Price = $Product->pricekey[$Product->optionkey($pricing)];
		else if ($pricing) $Price = $Product->priceid[$pricing];
		else $Price = $Product->prices[0];
				
		$this->product = $Product->id;
		$this->price = $Price->id;
		$this->category = $category;
		$this->option = $Price;
		$this->name = $Product->name;
		$this->slug = $Product->slug;
		$this->description = $Product->summary;
		$this->thumbnail = $Product->thumbnail;
		$this->options = $Product->prices;
		$this->sku = $Price->sku;
		$this->type = $Price->type;
		$this->sale = $Price->onsale;
		$this->saved = ($Price->price - $Price->promoprice);
		$this->savings = ($Price->price > 0)?percentage($this->saved/$Price->price)*100:0;
		$this->freeshipping = ($Price->freeshipping || $Product->freeshipping);
		$this->unitprice = (($Price->onsale)?$Price->promoprice:$Price->price);
		$this->optionlabel = (count($Product->prices) > 1)?$Price->label:'';
		$this->donation = $Price->donation;
		$this->data = $data;

		if (!empty($Price->download)) $this->download = $Price->download;
		
		if ($Price->shipping == "on" && $Price->type == "Shipped") {
			$this->shipping = true;
			$this->weight = $Price->weight;
			$this->shipfee = $Price->shipfee;
		}
		
		$this->inventory = ($Price->inventory == "on")?true:false;
		$this->taxable = ($Price->tax == "on" && $Shopp->Settings->get('taxes') == "on")?true:false;
	}
		
	function quantity ($qty) {

		if ($this->type == "Donation" && $this->donation['var'] == "on") {
			if ($this->donation['min'] == "on" && floatnum($qty) < $this->unitprice) 
				$this->unitprice = $this->unitprice;
			else $this->unitprice = floatnum($qty);
			$this->quantity = 1;
			$qty = 1;
		}

		$qty = preg_replace('/[^\d+]/','',$qty);
		if ($this->inventory) {
			if ($qty > $this->option->stock) 
				$this->quantity = $this->option->stock;
			else $this->quantity = $qty;
		} else $this->quantity = $qty;
		
		$this->total = $this->quantity * $this->unitprice;
	}
	
	function add ($qty) {
		if ($this->type == "Donation" && $this->donation['var'] == "on") {
			$qty = floatnum($qty);
			$this->quantity = $this->unitprice;
		}
		$this->quantity($this->quantity+$qty);
	}
	
	function options ($selection = "") {
		if (empty($this->options)) return "";

		$string = "";
		foreach($this->options as $option) {
			if ($option->type == "N/A") continue;
			$currently = ($option->onsale)?$option->promoprice:$option->price;
		
			$difference = $currently-$this->unitprice;

			$price = '';
			if ($difference > 0) $price = '  (+'.money($difference).')';
			if ($difference < 0) $price = '  (-'.money(abs($difference)).')';
			
			$selected = "";
			if ($selection == $option->id) $selected = ' selected="Selected"';
			$disabled = "";
			if ($option->inventory == "on" && $option->stock < $this->quantity)
				$disabled = ' disabled="disabled"';
			
			$string .= '<option value="'.$option->id.'"'.$selected.$disabled.'>'.$option->label.$price.'</option>';
		}
		return $string;
	}
	
	function unstock () {
		$db = DB::get();
		$table = DatabaseObject::tablename(Price::$table);
		$db->query("UPDATE $table SET stock=stock-{$this->quantity} WHERE id='{$this->price}'");
	}
	
	function shipping (&$Shipping) {
	}
	
	function tag ($id,$property,$options=array()) {
		global $Shopp;
		
		
		if (SHOPP_PERMALINKS) $imageuri = $Shopp->shopuri."images/";
		$imageuri =  $Shopp->shopuri."?shopp_image=";
		
		// Return strings with no options
		switch ($property) {
			case "id": return $id;
			case "name": return $this->name;
			case "link":
			case "url": 
				return (SHOPP_PERMALINKS)?
					$Shopp->shopuri.$this->category."/".$this->slug:
						add_query_arg(array('shopp_category' => $this->category,
											'shopp_pid' => $this->product),$Shopp->shopuri);
			case "sku": return $this->sku;
		}
		
		// Handle currency values
		$result = "";
		switch ($property) {
			case "unitprice": $result = $this->unitprice; break;
			case "total": $result = $this->total; break;
			case "tax": $result = $this->tax; break;			
		}
		if (!empty($result)) {
			if (isset($options['currency']) && !value_is_true($options['currency'])) return $result;
			else return money($result);
		}
		
		// Handle values with complex options
		switch ($property) {
			case "quantity": 
				$result = $this->quantity;
				if ($this->type == "Donation" && $this->donation['var'] == "on") return $result;
				if (isset($options['input']) && $options['input'] == "menu") {
					if (!isset($options['value'])) $options['value'] = $this->quantity;
					if (!isset($options['options'])) 
						$values = "1-15,20,25,30,35,40,45,50,60,70,80,90,100";
					else $values = $options['options'];
					
					if (strpos($values,",") !== false) $values = split(",",$values);
					else $values = array($values);
					$qtys = array();
					foreach ($values as $value) {
						if (strpos($value,"-") !== false) {
							$value = split("-",$value);
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
							$result = '<button type="submit" name="remove['.$id.']" value="'.$id.'"'.$class.' tabindex="">'.$label.'</button>';
					}
				} else {
					$result = '<a href="'.SHOPP_CARTURL.'?cart=update&amp;item='.$id.'&amp;quantity=0"'.$class.'>'.$label.'</a>';
				}
				break;
			case "optionlabel": $result = $this->optionlabel; break;
			case "options":
				$class = "";
				if (strtolower($options['show']) == "selected") 
					return (!empty($this->optionlabel))?$options['before'].$this->optionlabel.$options['after']:'';
					
				if (isset($options['class'])) $class = ' class="'.$options['class'].'" ';
				if (count($this->options) > 1) {
					$result .= $options['before'];
					$result .= '<input type="hidden" name="items['.$id.'][product]" value="'.$this->product.'"/>';
					$result .= ' <select name="items['.$id.'][price]" id="items-'.$id.'-price"'.$class.'>';
					$result .= $this->options($this->price);
					$result .= '</select>';
					$result .= $options['after'];
				}
				break;
			case "hasinputs": 
			case "has-inputs": return (count($this->data) > 0); break;
			case "inputs":			
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
			case "input":
				$data = current($this->data);
				$name = key($this->data);
				if (isset($options['name'])) return $name;
				return $data;
				break;
			case "thumbnail":
				if (!empty($options['class'])) $options['class'] = ' class="'.$options['class'].'"';
				if (isset($this->thumbnail)) {
					$img = $this->thumbnail;
					$width = (isset($options['width']))?$options['width']:$img->properties['height'];
					$height = (isset($options['height']))?$options['height']:$img->properties['height'];
					
					return '<img src="'.$imageuri.$img->id.'" alt="'.$this->name.' '.$img->datatype.'" width="'.$width.'" height="'.$height.'" '.$options['class'].' />'; break;
				}
			
		}
		if (!empty($result)) return $result;
		
		
		return false;
	}

} // end Item class

?>