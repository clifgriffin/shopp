<?php
/**
 * Order API
 *
 * Set of api calls for retrieving, storing, modifying orders, and sending order events.
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, June 23, 2011
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @since 1.0
 * @subpackage shopp
 **/

/**
 * shopp_orders - get a list of purchases
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param mixed $from (optional) mktime or SQL datetime, get purchases after this date/time.
 * @param mixed $to (optional) mktime or SQL datetime, get purchased before this date/time.
 * @param bool $items (optional default:true) load purchased items into the records, slightly slower operation
 * @param array $customers (optional) list of int customer ids to limit the purchases to.  All customers by default.
 * @param int $limit (optional default:false) maximimum number of results to get, false for no limit
 * @param string $order (optional default:DESC) DESC or ASC, for sorting in ascending or descending order.
 * @return array of orders
 **/
function shopp_orders ( $from = false, $to = false, $items = true, $customers = array(), $limit = false, $order = 'DESC' ) {
	$pt = DatabaseObject::tablename(Purchase::$table);
	$pd = DatabaseObject::tablename(Purchased::$table);

	$where = array();
	if ( $from ) {
		if ( 1 == preg_match('/^([0-9]{2,4})-([0-1][0-9])-([0-3][0-9]) (?:([0-2][0-9]):([0-5][0-9]):([0-5][0-9]))?$/', $from) )
			$where[] = "AND '$from' < created";
		else if ( is_int($from) )
			$where[] = "AND FROM_UNIXTIME($from) < created";
	}

	if ( $to ) {
		if ( 1 == preg_match('/^([0-9]{2,4})-([0-1][0-9])-([0-3][0-9]) (?:([0-2][0-9]):([0-5][0-9]):([0-5][0-9]))?$/', $to) )
			$where[] = "AND '$to' >= created";
		else if ( is_int($from) )
			$where[] = "AND FROM_UNIXTIME($to) >= created";
	}

	if ( ! empty($customers) ) {
		$set = db::escape(implode(',',$customers));
		$where[] = "AND 0 < FIND_IN_SET(customer,'".$set."')";
	}

	$where = implode(' ', $where);

	if ( $limit && is_int($limit) ) $limit = " LIMIT $limit";

	$query = "SELECT * FROM $pt WHERE 1 $where ORDER BY id ".('DESC' == $order ? "DESC" : "ASC").$limit;
	echo $query.BR;
	$orders = DB::query($query, false, '_shopp_order_purchase');
	if ( $items ) $orders = DB::query("SELECT * FROM $pd AS pd WHERE 0 < FIND_IN_SET(pd.purchase,'".implode(",", array_keys($orders))."')", false, '_shopp_order_purchased', $orders);

	return $orders;
}

/**
 * _shopp_order_purchase - helper function for shopp_orders
 *
 * @author John Dillick
 * @since 1.2
 *
 **/
function _shopp_order_purchase ( &$records, &$record ) {
	$records[$record->id] = $record;
}

/**
 * _shopp_order_purchased - helper function for shopp_orders
 *
 * @author John Dillick
 * @since 1.2
 *
 **/
function _shopp_order_purchased ( &$records, &$record, $objects ) {
	if ( ! isset($records[$record->purchase]) && isset($objects[$record->purchase]) ) {
		if ( ! isset($objects[$record->purchase]->purchased) ) $objects[$record->purchase]->purchased = array();

		if ( "yes" == $record->addons ) {
			$record->addons = new ObjectMeta($record->id, 'purchased', 'addon');
		}

		$objects[$record->purchase]->purchased[] = $record;
		$records[$record->purchase] = $objects[$record->purchase];
	}
}

/**
 * shopp_order_count - get an order count, total or during or a time period
 *
 * @author John Dillick
 * @since 1.2
 *
 * @return int number of orders found
 **/
function shopp_order_count ($from = false, $to = false) {
	return count( shopp_orders( $from, $to, false ) );
}

/**
 * shopp_customer_orders - get a list of orders for a particular customer
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int $customer (required) the customer id to load the orders for
 * @param mixed $from (optional) mktime or SQL datetime, get purchases after this date/time.
 * @param mixed $to (optional) mktime or SQL datetime, get purchased before this date/time.
 * @param bool $items (optional default:true) load purchased items into the records, slightly slower operation
 * @return array of orders
 **/
function shopp_customer_orders ( $customer = false, $from, $to, $items ) {
	if ( ! $customer || ! shopp_customer_exists($customer) ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: Invalid or missing customer id.",false,SHOPP_DEBUG_ERR);
		return false;
	}

	$defaults = array('from' => false, 'to' => false, 'items' => true);
	$settings = wp_parse_args( func_get_args(), $defaults );

	extract($settings);

	return shopp_orders( $from, $to, $items, array($customer) );
}

/**
 * shopp_recent_orders - load orders for a specified time range in the past
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int $time number of time units (period) to go back
 * @param string $period the time period, can be days, weeks, months, years.
 * @return array of orders
 **/
function shopp_recent_orders ($time = 1, $period = 'day') {
	$periods = array('day', 'days', 'week', 'weeks', 'month', 'months', 'year', 'years');

	if ( ! in_array($period, $periods) ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: Invalid period $period.  Use one of (".implode(", ", $periods).")",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}

	if ( ! $time || ! is_int($time) || $time < 0 ) $time = 1;

	$from = strtotime("$time $period ago");

	return shopp_orders($from);
}

/**
 * shopp_recent_orders - load orders for a specified time range in the past for a particular customer
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int $customer (required) the customer id to load the orders for
 * @param int $time number of time units (period) to go back
 * @param string $period the time period, can be days, weeks, months, years.
 * @return array of orders
 **/
function shopp_recent_customer_orders ($customer = false, $time = 1, $period = 'day') {
	if ( ! $customer || ! shopp_customer_exists($customer) ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: Invalid or missing customer id.",false,SHOPP_DEBUG_ERR);
		return false;
	}

	$periods = array('day', 'days', 'week', 'weeks', 'month', 'months', 'year', 'years');

	if ( ! in_array($period, $periods) ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: Invalid period $period.  Use one of (".implode(", ", $periods).")",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}

	if ( ! $time || ! is_int($time) || $time < 0 ) $time = 1;

	$from = strtotime("$time $period ago");

	return shopp_customer_orders ( $customer, $from );
}

/**
 * shopp_last_order - get the most recent order
 *
 * @author John Dillick
 * @since 1.2
 *
 * @return order or false on failure
 **/
function shopp_last_order () {
	$orders = shopp_orders ( false, false, true, array(), 1);

	if ( is_array($orders) && ! empty($orders) ) return reset($orders);
	return false;
}

/**
 * shopp_last_customer_order - load the most recent order for a particular customer
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int $customer (required) the customer id to load the order for
 * @return order or false on failure
 **/
function shopp_last_customer_order ( $customer = false ) {
	if ( ! $customer || ! shopp_customer_exists($customer) ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: Invalid or missing customer id.",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}
	$orders = shopp_orders ( false, false, true, array($customer), 1);

	if ( is_array($orders) && ! empty($orders) ) return reset($orders);
	return false;
}

/**
 * shopp_order - load a specified order by id
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int $id the order id
 * @return order or false on failure
 **/
function shopp_order ( $id = false ) {
	if ( ! $id || ! shopp_order_exists($id) ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: Invalid or missing order id.",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}

	$Purchase = new Purchase($id);
	$Purchase->load_purchased();
	return $Purchase;
}

/**
 * shopp_order_exists - determine if an order exists with the specified id, or transaction id.
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int $id the order id, or the transaction id
 * @return bool true if the order exists, else false
 **/
function shopp_order_exists ( $id = false ) {
	$Purchase = new Purchase();
	if ( is_int($id) )
		$Purchase = new Purchase($id);
	else if ( ! is_string($id) )
		$Purchase = new Purchase($id,'txnid');

	return ( ! empty($Purchase->id) );

}

/**
 * shopp_add_order - build an order
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param array $data parameters (see $order_field for allowed purchase parameters, and see shopp_add_order_line for line item parameters)
 * @return bool/Purchase false on failure, new order on success
 **/
function shopp_add_order ( $data = array() ) {
	$order_fields = array('customer', 'shipping', 'billing', 'currency', 'ip', 'firstname', 'lastname', 'email', 'phone', 'company', 'card', 'cardtype', 'cardexpires', 'cardholder', 'address', 'xaddress', 'city', 'state', 'country', 'postcode', 'shipaddress', 'shipxaddress', 'shipcity', 'shipstate', 'shipcountry', 'shippostcode', 'geocode', 'promos', 'subtotal', 'freight', 'tax', 'total', 'discount', 'fees', 'taxing', 'txnid', 'txnstatus', 'gateway', 'carrier', 'shipmethod', 'shiptrack', 'status', 'data');

	$Purchase = new Purchase();
	foreach ( $data as $key => $value ) {
		if ( ! in_array($key, $order_fields) ) continue;
		$Purchase->{$key} = $value;
	}

	$Purchase->save();

	if ( empty($Purchase->id) ) return false;

	if ( isset($data[$items]) && is_array($data[$items]) ) {
		$Purchase->purchased = array();
		foreach ( $data[$items] as $i => $item ) {
			$Purchased = shopp_add_order_line( $Purchase->id, $data[$items] );
			if ($Purchased) $Purchase->purchased[$i] = $Purchased;
		}
	}

	return $Purchase;

}

/**
 * shopp_rmv_order - remove an order
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param init $id id of order to remove
 * @return bool true on success, false on failure
 **/
function shopp_rmv_order ($id) {
	if ( shopp_order_exists($id) ) {
		$Purchase = new Purchase($id);
		$Purchase->load_purchased();
		foreach ( $Purchase->purchased as $Purchased ) {
			$Purchased->delete();
		}
		$Purchase->delete();
	} else return false;

	return true;
}

/**
 * shopp_add_order_line - add a line item to an order
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int $order (required) the order id to add the line item to
 * @param array $data data to create the item from (see $item_fields for allowed data)
 * @return bool/Purchased item - false on failure, new order line item on success.
 **/
function shopp_add_order_line ( $order = false, $data = array() ) {
	$item_fields = array(
		'product', // product id of line item
		'price', // variant id of line item
		'download', // download asset id for line item
		'dkey', // unique download key to assign to download item
		'name', // name of item
		'description', // description of item
		'optionlabel', // string label of variant combination of this item
		'sku', // sku of item
		'quantity', // quantity of items on this line
		'unitprice', // unit price
		'unittax', // unit tax
		'shipping', // line item shipping cost
		'total', // line item total cost
		'addons', // array of addons
		'variation', // array of key => value (optionmenu => option) pairs for the variant combination
		'data' // associative array of item "data" key value pairs
		);

	if ( ! shopp_order_exists($order) ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: Invalid order id.",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}

	$Purchased = new Purchased();
	foreach ( $data as $key => $value ) {
		if ( ! in_array($key, $item_fields) ) continue;
		$Purchased->{$key} = $value;
	}

	$Purchased->purchase = $order;

	$Purchased->save();
	return ( ! empty($Purchased->id) ? $Purchased : false );
}

/**
 * shopp_rmv_order_line - remove an order line by index
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int $order (required) the order id to remove the line from
 * @param int $line (optional default:0) the index of the line to remove
 * @return bool true on success, false on failure
 **/
function shopp_rmv_order_line ( $order = false, $line = 0 ) {
	$Lines = shopp_order_lines($order);
	if ( empty($Lines) || $line >= count($Lines) || ! isset($Lines[$line]) ) return false;
	$Lines[$line]->delete();
	return true;
}

/**
 * shopp_order_lines - get a list of the items associated with an order
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int $order (required) the order id
 * @return bool/array false on failure, array of purchased line items on success
 **/
function shopp_order_lines ( $order = false ) {
	$Order = shopp_order( $order );
	if ( $Order ) return $Order->purchased;
	return false;
}

/**
 * shopp_order_line_count - get the number of line items in a specified order
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int $order the order id
 * @return int the number of line items
 **/
function shopp_order_line_count ( $order = false ) {
	$lines = shopp_order_lines($order);
	if ( $lines ) return count($lines);
	return 0;
}

/**
 * shopp_add_order_line_download - attach a download asset to a order line
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int $order the order id to add the download asset to
 * @param int $line the order line item to add the download asset to
 * @param int $download the download asset id
 * @return bool true on success, false on failure
 **/
function shopp_add_order_line_download ( $order = false, $line = 0, $download = false ) {
	$Lines = shopp_order_lines($order);
	if ( empty($Lines) || $line >= count($Lines) || ! isset($Lines[$line]) )
		return false;

	$DL = new ProductDownload($download);
	if ( empty($DL->id) ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: Invalid or missing download asset id.",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}

	$Lines[$line]->download = $download;
	$Lines[$line]->save();
	return true;
}

/**
 * shopp_rmv_order_line_download - remove a download asset from a line item
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int $order the order id to remove the download asset from
 * @param int $line the order line item to remove the download asset from
 * @return bool true on success, false on failure
 **/
function shopp_rmv_order_line_download ( $order = false, $line = 0 ) {
	$Lines = shopp_order_lines($order);
	if ( empty($Lines) || $line >= count($Lines) || ! isset($Lines[$line]) )
		return false;

	$Lines[$line]->download = 0;
	$Lines[$line]->save();
	return true;
}

/**
 * shopp_order_line_data_count - return the count of the line item data array
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int $order the order id
 * @param int $line the order line item
 * @return int/bool count number of entries in the line item data array for a given line item, false if line item doesn't exist
 **/
function shopp_order_line_data_count ($order = false, $line = 0 ) {
	$Lines = shopp_order_lines($order);
	if ( empty($Lines) || $line >= count($Lines) || ! isset($Lines[$line]) )
		return false;

	if ( is_array($Lines[$line]->data) ) return count($Lines[$line]->data);
	return 0;
}

/**
 * shopp_order_line_data - return the line item data array
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int $order the order id
 * @param int $line the order line item
 * @return array/bool entries in the line item data array for a given line item, false if line item doesn't exist
 **/
function shopp_order_line_data ($order = false, $line = 0, $name = false) {
	$Lines = shopp_order_lines($order);
	if ( empty($Lines) || $line >= count($Lines) || ! isset($Lines[$line]) )
		return false;

	if ( is_array($Lines[$line]->data) && ! empty($Lines[$line]->data) ) {
		if ( $name && in_array($name, array_keys($Lines[$line]->data)) ) return $Lines[$line]->data[$name];
		return $Lines[$line]->data;
	}
	return false;
}

/**
 * shopp_add_order_line_data - add one or more key=>value pair to the line item data array.  The specified data is merged with existing data.
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int $order the order id
 * @param int $line the order line item
 * @param array $data new key=>value pairs to add to the line item
 * @return bool true on success, false on failure
 **/
function shopp_add_order_line_data ( $order = false, $line = 0, $data = array() ) {
	$Lines = shopp_order_lines($order);
	if ( empty($Lines) || $line >= count($Lines) || ! isset($Lines[$line]) )
		return false;

	if ( ! is_array($Lines[$line]->data) ) $Lines[$line]->data = array();

	$Lines[$line]->data = array_merge($Lines[$line]->data, $data);
	$Lines[$line]->save();
	return true;
}

/**
 * shopp_rmv_order_line_data - remove all or one data key=>value pair from the order line data array
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int $order (required) the order id
 * @param int $line (required) the order line item
 * @param string $name (optional default:false) the key to remove, removes all data when false
 * @return bool true on success, false on failure
 **/
function shopp_rmv_order_line_data ($order = false, $line = 0, $name = false) {
	$Lines = shopp_order_lines($order);
	if ( empty($Lines) || $line >= count($Lines) || ! isset($Lines[$line]) )
		return false;

	if ( ! is_array($Lines[$line]->data) ) $Lines[$line]->data = array();
	if ( $name && in_array($name, array_keys($Lines[$line]->data) ) ) unset($Lines[$line]->data[$name]);

	$Lines[$line]->save();
}

/**
 * shopp_add_order_event - log an order event
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int $order (required) the order id for the event
 * @param string $type (required) the order event type
 * @param string $message (optional default:'') the log message for the event
 * @return bool true on success, false on error
 **/
function shopp_add_order_event ($order = false, $type = false, $message = '') {
	if ( ! $order || ! shopp_order_exists($order) ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: Missing or invalid order id.",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}

	if ( ! $type || ! OrderEvent::handler($type)) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: Missing or invalid order event type",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}

	return OrderEvent::add($order,$type,$message);
}

?>