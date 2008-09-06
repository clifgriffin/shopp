<?php
/**
 * Promotion class
 * Handles special promotion deals
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 2 September, 2008
 * @package shopp
 **/

class Promotion extends DatabaseObject {
	static $table = "promo";

	function Promotion ($id=false) {
		$this->init(self::$table);
		if ($this->load($id)) return true;
		else return false;
	}
	
	function build_discounts () {
		$db = DB::get();
		
		$discount_table = DatabaseObject::tablename(Discount::$table);
		$product_table = DatabaseObject::tablename(Product::$table);
		$price_table = DatabaseObject::tablename(Price::$table);
		$catalog_table = DatabaseObject::tablename(Catalog::$table);
		$category_table = DatabaseObject::tablename(Category::$table);
		
		$where = "";
		// Go through each rule to construct an SQL query 
		// that gets all applicable product & price ids
		if (!empty($this->rules) && is_array($this->rules)) {
			foreach ($this->rules as $rule) {
			
				switch($rule['logic']) {
					case "Is equal to": $match = "='".$rule['value']."'"; break;
					case "Is not equal to": $match = "!='".$rule['value']."'"; break;
					case "Contains": $match = " LIKE '%".$rule['value']."%'"; break;
					case "Does not contain": $match = " NOT LIKE '%".$rule['value']."%'"; break;
					case "Begins with": $match = " LIKE '".$rule['value']."%'"; break;
					case "Ends with": $match = " LIKE '%".$rule['value']."'"; break;
					case "Is greater than": $match = ">".preg_replace("/[^\d\.]/","",$rule['value']); break;
					case "Is greater than or equal to": $match = ">=".preg_replace("/[^\d\.]/","",$rule['value']); break;
					case "Is less than": $match = "<".preg_replace("/[^\d\.]/","",$rule['value']); break;
					case "Is less than or equal to": $match = "<=".preg_replace("/[^\d\.]/","",$rule['value']); break;
				}
			
				$where .= "AND ";
				switch($rule['property']) {
					case "Name": $where .= "p.name$match"; break;
					case "Category": $where .= "cat.name$match"; break;
					case "Variation": $where .= "prc.label$match"; break;
					case "Price": $where .= "prc.price$match"; break;
					case "Sale price": $where .= "(prc.onsale='on' AND prc.saleprice$match)"; break;
					case "Type": $where .= "prc.type$match"; break;
					case "In stock": $where .= "(prc.inventory='on' AND prc.stock$match)"; break;
				}
			
			}
			
		}
		
		$type = ($this->type == "Item")?'catalog':'cart';
		// Delete previous discount records
		$db->query("DELETE FROM $discount_table WHERE promo=$this->id");
		$query = "INSERT INTO $discount_table (promo,product,price) 
					SELECT '$this->id' as promo,p.id AS product,prc.id AS price
					FROM $product_table as p 
					LEFT JOIN $price_table AS prc ON prc.product=p.id 
					LEFT JOIN $catalog_table AS clog ON clog.product=p.id 
					LEFT JOIN $category_table AS cat ON clog.category=cat.id 
					WHERE TRUE $where 
					GROUP BY prc.id";
		$db->query($query);
		
	}

} // end Promotion class


// Discount table provides discount index for faster, efficient discount lookups
class Discount extends DatabaseObject {
	static $table = "discount";
	
	function Promotion ($id=false) {
		$this->init(self::$table);
		if ($this->load($id)) return true;
		else return false;
	}

} // end Discount class


?>