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

	static $values = array(
		"Name" => "text",
		"Category" => "text",
		"Variation" => "text",
		"Price" => "price",
		"Sale price" => "price",
		"Type" => "text",
		"In stock" => "text",
		"Any item name" => "text",
		"Any item quantity" => "text",
		"Any item amount" => "price",
		"Total quantity" => "text",
		"Shipping amount" => "price",
		"Subtotal amount" => "price",
		"Promo use count" => "text",
		"Promo code" => "text",
		"Ship-to country" => "text",
		"Customer type" => "text"
	);

	function Promotion ($id=false) {
		$this->init(self::$table);
		if ($this->load($id)) return true;
		else return false;
	}

	function catalog_discounts () {
		$db = DB::get();

		$product_table = DatabaseObject::tablename(Product::$table);
		$price_table = DatabaseObject::tablename(Price::$table);
		$catalog_table = DatabaseObject::tablename(Catalog::$table);
		$category_table = DatabaseObject::tablename(Category::$table);

		$where_notdiscounted = array("0 = FIND_IN_SET($this->id,discounts)");
		$where = array();
		// Go through each rule to construct an SQL query
		// that gets all applicable product & price ids
		if (!empty($this->rules) && is_array($this->rules)) {
			foreach ($this->rules as $rule) {

				if (Promotion::$values[$rule['property']] == "price")
					$value = floatvalue($rule['value']);
				else $value = $rule['value'];

				switch($rule['logic']) {
					case "Is equal to": $match = "='$value'"; break;
					case "Is not equal to": $match = "!='$value'"; break;
					case "Contains": $match = " LIKE '%$value%'"; break;
					case "Does not contain": $match = " NOT LIKE '%$value%'"; break;
					case "Begins with": $match = " LIKE '$value%'"; break;
					case "Ends with": $match = " LIKE '%$value'"; break;
					case "Is greater than": $match = "> $value"; break;
					case "Is greater than or equal to": $match = ">= $value"; break;
					case "Is less than": $match = "< $value"; break;
					case "Is less than or equal to": $match = "<= $value"; break;
				}

				switch($rule['property']) {
					case "Name":
						$where[] = "p.name$match";
						$joins[$product_table] = "LEFT JOIN $product_table as p ON prc.product=p.id";
						break;
					case "Category":
						$where[] = "cat.name$match";
						$joins[$catalog_table] = "LEFT JOIN $catalog_table AS catalog ON catalog.product=prc.product";
						$joins[$category_table] = "LEFT JOIN $category_table AS cat ON catalog.parent=cat.id AND catalog.type='category'";
						break;
					case "Variation": $where[] = "prc.label$match"; break;
					case "Price": $where[] = "prc.price$match"; break;
					case "Sale price": $where[] = "(prc.onsale='on' AND prc.saleprice$match)"; break;
					case "Type": $where[] = "prc.type$match"; break;
					case "In stock": $where[] = "(prc.inventory='on' AND prc.stock$match)"; break;
				}

			}

		}

		if (!empty($where)) $where = "WHERE ".join(" AND ",$where);
		else $where = false;

		if (!empty($joins)) $joins = join(' ',$joins);
		else $joins = false;

		// Find all the pricetags the promotion is *currently assigned* to
		$query = "SELECT id FROM $price_table WHERE 0 < FIND_IN_SET($this->id,discounts)";
		$results = $db->query($query,AS_ARRAY);
		$current = array_map(create_function('$o', 'return $o->id;'), $results);

		// Find all the pricetags the promotion is *going to apply* to
		$query = "SELECT prc.id,prc.product,prc.discounts FROM $price_table AS prc
					$joins
					$where";

		$results = $db->query($query,AS_ARRAY);
		$updates = array_map(create_function('$o', 'return $o->id;'), $results);

		// Determine which records need promo added to and removed from
		$added = array_diff($updates,$current);
		$removed = array_diff($current,$updates);

		// Add discounts to specific rows
		$query = "UPDATE $price_table
					SET discounts=CONCAT(discounts,IF(discounts='','$this->id',',$this->id'))
					WHERE id IN (".join(',',$added).")";

		$db->query($query);

		// Remove discounts from pricetags that now don't match the conditions
		$this->uncatalog_discounts($removed);

		// Recalculate product stats for the products with pricetags that have changed
		$Collection = new PromoProducts(array('id' => $this->id));
		$Collection->pagination = false;
		$Collection->load_products( array('load'=>array('prices','restat')) );

	}

	function uncatalog_discounts ($pricetags) {
		$db =& DB::get();
		$_table = DatabaseObject::tablename(Price::$table);

		$discounted = $db->query("SELECT id,discounts,FIND_IN_SET($this->id,discounts) AS offset FROM $_table WHERE id IN ('".join(',',$pricetags)."')",AS_ARRAY);

		foreach ($discounted as $index => $pricetag) {
			$promos = explode(',',$pricetag->discounts);
			array_splice($promos,($offset-1),1);
			$db->query("UPDATE LOW_PRIORITY $_table SET discounts='".join(',',$promos)."' WHERE id=$pricetag->id");
		}
	}

	/**
	 * match_rule ()
	 * Determines if the value of a given subject matches the rule based
	 * on the specified operation */
	function match_rule ($subject,$op,$value,$property=false) {
		switch($op) {
			// String or Numeric operations
			case "Is equal to":
			 	if($property && Promotion::$values[$property] == 'price') {
					return ( floatvalue($subject) != 0
					&& floatvalue($value) != 0
					&& floatvalue($subject) == floatvalue($value));
				} else {
					if (is_array($subject)) return (in_array($value,$subject));
					return ("$subject" === "$value");
				}
				break;
			case "Is not equal to":
				if (is_array($subject)) return (!in_array($value,$subject));
				return ("$subject" !== "$value"
						|| (floatvalue($subject) != 0
						&& floatvalue($value) != 0
						&& floatvalue($subject) != floatvalue($value)));
						break;

			// String operations
			case "Contains":
				if (is_array($subject)) {
					foreach ($subject as $s)
						if (stripos($s,$value) !== false) return true;
					return false;
				}
				return (stripos($subject,$value) !== false); break;
			case "Does not contain":
				if (is_array($subject)) {
					foreach ($subject as $s)
						if (stripos($s,$value) !== false) return false;
					return true;
				}
				return (stripos($subject,$value) === false); break;
			case "Begins with":
				if (is_array($subject)) {
					foreach ($subject as $s)
						if (stripos($s,$value) === 0) return true;
					return false;
				}
				return (stripos($subject,$value) === 0); break;
			case "Ends with":
				if (is_array($subject)) {
					foreach ($subject as $s)
						if (stripos($s,$value) === strlen($s) - strlen($value)) return true;
					return false;
				}
				return  (stripos($subject,$value) === strlen($subject) - strlen($value)); break;

			// Numeric operations
			case "Is greater than":
				return (floatvalue($subject,false) > floatvalue($value,false));
				break;
			case "Is greater than or equal to":
				return (floatvalue($subject,false) >= floatvalue($value,false));
				break;
			case "Is less than":
				return (floatvalue($subject,false) < floatvalue($value,false));
				break;
			case "Is less than or equal to":
				return (floatvalue($subject,false) <= floatvalue($value,false));
				break;
		}

		return false;
	}

	/**
	 * Records when a specific promotion is used
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param array $promos A list of Promotion ids of the promotions to be updated
	 * @return void
	 **/
	function used ($promos) {
		$db =& DB::get();
		if (empty($promos) || !is_array($promos)) return;
		$table = DatabaseObject::tablename(self::$table);
		$db->query("UPDATE LOW_PRIORITY $table SET uses=uses+1 WHERE 0 < FIND_IN_SET(id,'".join(',',$promos)."')");
	}

} // END clas Promotion

?>