<?php
/**
 * Order API
 *
 * Description…
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, June 23, 2011
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @since 1.0
 * @subpackage shopp
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


function _shopp_order_purchase ( &$records, &$record ) {
	$records[$record->id] = $record;
}

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

function shopp_order_count ($from = false, $to = false) {
	return count( shopp_orders( $from, $to, false ) );
}

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

function shopp_last_order () {
	$orders = shopp_orders ( false, false, true, array(), 1);

	if ( is_array($orders) && ! empty($orders) ) return reset($orders);
	return false;
}

function shopp_last_customer_order ( $customer = false ) {
	if ( ! $customer || ! shopp_customer_exists($customer) ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: Invalid or missing customer id.",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}
	$orders = shopp_orders ( false, false, true, array($customer), 1);

	if ( is_array($orders) && ! empty($orders) ) return reset($orders);
	return false;
}

function shopp_order ( $id = false ) {
	if ( ! $id || ! shopp_order_exists($id) ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: Invalid or missing order id.",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}

	$Purchase = new Purchase($id);
	$Purchase->load_purchased();
	return $Purchase;
}

function shopp_order_exists ( $id = false ) {
	if ( ! $id || ! is_int($id) ) {
		return false;
	}

	$Purchase = new Purchase($id);
	return ( ! empty($Purchase->id) );

}

function shopp_add_order ( $data = array() ) {
	$order_fields = array('customer', 'shipping', 'billing', 'currency', 'ip', 'firstname', 'lastname', 'email', 'phone', 'company', 'card', 'cardtype', 'cardexpires', 'cardholder', 'address', 'xaddress', 'city', 'state', 'country', 'postcode', 'shipaddress', 'shipxaddress', 'shipcity', 'shipstate', 'shipcountry', 'shippostcode', 'geocode', 'promos', 'subtotal', 'freight', 'tax', 'total', 'discount', 'fees', 'taxing', 'txnid', 'txnstatus', 'gateway', 'carrier', 'shipmethod', 'shiptrack', 'status', 'data');

	$Purchase = new Purchase();
	foreach ( $data as $key => $value ) {
		if ( ! in_array($key, $order_fields) ) continue;
		$Purchase->{$key} = $value;
	}

	if ( isset($data[$items]) && is_array($data[$items]) ) {
		$Purchase->purchased = array();
		foreach ( $data[$items] as $i => $item ) {
			$Purchased = shopp_add_order_line( $Purchase->id, $data[$items] );
			if ($Purchased) $Purchase->purchased[$i] = $Purchased;
		}
	}

	return $Purchase;

}

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

function shopp_add_order_line ($order = false, $data = array() ) {
	$item_fields = array('purchase', 'product', 'price', 'download', 'dkey', 'name', 'description', 'optionlabel', 'sku', 'quantity', 'downloads', 'unitprice', 'unittax', 'shipping', 'total', 'addons', 'variation', 'data');

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

function shopp_rmv_order_line ( $order = false, $line = 0 ) {
	$Lines = shopp_order_lines($order);
	if ( empty($Lines) || $line >= count($Lines) || ! isset($Lines[$line]) ) return false;
	$Lines[$line]->delete();
	return true;
}

function shopp_order_lines ( $order = false ) {
	$Order = shopp_order( $order );
	if ( $Order ) return $Order->purchased;
	return false;
}

function shopp_order_line_count ( $order = false ) {
	$lines = shopp_order_lines($order);
	if ( $lines ) return count($lines);
	return 0;
}

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

function shopp_rmv_order_line_download ( $order = false, $line = 0 ) {
	$Lines = shopp_order_lines($order);
	if ( empty($Lines) || $line >= count($Lines) || ! isset($Lines[$line]) )
		return false;

	$Lines[$line]->download = 0;
	$Lines[$line]->save();
	return true;
}

function shopp_order_line_data_count ($order = false, $line = 0 ) {
	$Lines = shopp_order_lines($order);
	if ( empty($Lines) || $line >= count($Lines) || ! isset($Lines[$line]) )
		return false;

	if ( is_array($Lines[$line]->data) ) return count($Lines[$line]->data);
	return 0;
}

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

function shopp_add_order_line_data ( $order = false, $line = 0, $data = array() ) {
	$Lines = shopp_order_lines($order);
	if ( empty($Lines) || $line >= count($Lines) || ! isset($Lines[$line]) )
		return false;

	if ( ! is_array($Lines[$line]->data) ) $Lines[$line]->data = array();

	$Lines[$line]->data = array_merge($Lines[$line]->data, $data);
	$Lines[$line]->save();
}

function shopp_rmv_order_line_data ($order = false, $line = 0, $name = false) {
	$Lines = shopp_order_lines($order);
	if ( empty($Lines) || $line >= count($Lines) || ! isset($Lines[$line]) )
		return false;

	if ( ! is_array($Lines[$line]->data) ) $Lines[$line]->data = array();
	if ( $name && in_array($name, array_keys($Lines[$line]->data) ) ) unset($Lines[$line]->data[$name]);

	$Lines[$line]->save();
}

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