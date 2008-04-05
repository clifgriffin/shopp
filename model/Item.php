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
	var $name;
	var $brand;
	var $optionname;
	var $description;
	var $discount = 0;
	var $quantity = 0;
	var $unitprice = 0;
	var $total = 0;
	var $domship = 0;
	var $intlship = 0;
	var $tax = true;

	function Item ($qty,$Product,$Price,$TaxSetting) {
		$Product->load_prices();
		$this->product = $Product->id;
		$this->price = $Price->id;
		$this->name = $Product->name;
		$this->brand = $Product->brand;
		$this->sku = $Price->sku;
		$this->discount = 0; // Not implemented yet
		$this->quantity = $qty;
		$this->unitprice = (($Price->sale == "on")?$Price->saleprice:$Price->price);
		$this->total = $this->quantity * $this->unitprice;

		if (count($Product->prices) > 1) {
			$this->optionname = $Price->label;
			$this->options = $Product->prices;
		}

		if ($Price->shipping == "on") {
			$this->domship = $Price->domship;
			$this->intlship = $Price->intlship;
		}

		$this->tax = ($Price->tax == "on" && $TaxSetting == "on")?true:false;
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
		foreach($this->options as $option) {
			if ($selected == $option->id) echo "<option value=\"$option->id\" selected=\"\">$option->label</option>";
			else echo "<option value=\"$option->id\">$option->label</option>";
		}
	}

} // end Item class

?>