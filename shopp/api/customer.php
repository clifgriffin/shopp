<?php
/**
 * Customer API
 *
 * Plugin api function for customers
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, June 23, 2011
 * @license GNU GPL version 3 ( or later = false ) {@see license.txt}
 * @package shopp
 * @since 1.2
 * @subpackage shopp
 **/

/**
 * shopp_customer - get customer information
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int $customer (required) customer id to load
 * @return mixed, stdClass representation of the customer, bool false on failure
 **/
function shopp_customer ( $customer = false ) {
	if ( ! $customer ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: customer parameter required.",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}
	$Customer = new Customer( $customer );
	if ( empty($Customer->id) ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: Customer $customer could not be found.",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}

	$Customer = $Customer->simplify();

	$addresses = shopp_customer_addresses($customer);
	foreach ( $addresses as $type => $address ) {
		$Address = new stdClass;
		$Address->type = $type;
		foreach ($address as $property => $value) {
			$Address->{$property} = $value;
		}
		$Customer->{$type} = $Address;
	}
	return $Customer;
}

/**
 * shopp_customer_marketing - whether or not a customer accepts your marketing
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int $customer customer id to check
 * @return bool true if marketing accepted, false on failure and if marketing is not accepted.
 **/
function shopp_customer_marketing (  $customer = false ) {
	$customer = shopp_customer($customer);
	if ( $customer && isset($customer->marketing) && "yes" == $customer->marketing ) return true;
	return false;
}

/**
 * shopp_customer_marketing_list - get a list of customer names, type, and email addresses for marketing
 *
 * @author John Dillick
 * @since 1.2
 *
 * @return array list of customers for marketing
 **/
function shopp_customer_marketing_list () {
	$table = DatabaseObject::tablename(Customer::$table);
	return db::query( "select firstname, lastname, email, type from $table where marketing='yes'", AS_ARRAY );
}

function shopp_add_customer (  $data = array() ) {
	if ( empty($data) ) {
		if(SHOPP_DEBUG) new ShoppError("shopp_add_customer - no customer data supplied.",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}

	$map = array('wpuser', 'firstname', 'lastname', 'email', 'phone', 'company', 'marketing', 'type');
	$address_map = array( 'saddress' => 'address', 'baddress' => 'address', 'sxaddress' => 'xaddress', 'bxaddress' => 'xaddress', 'scity' => 'city', 'bcity' => 'city', 'sstate' => 'state', 'bstate' => 'state', 'scountry' => 'country', 'bcountry' => 'country', 'spostcode' => 'postcode', 'bpostcode' => 'postcode', 'sgeocode' => 'geocode', 'bgeocode' => 'geocode', 'residential'=>'residential' );

	// handle duplicate or missing wpuser
	if ( isset($data['wpuser']) ) {
		$c = new Customer($data['wpuser'], 'wpuser');
		if ( ! empty($c->id) ) {
			if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: Customer with WordPress user id {$data['wpuser']} already exists.",__FUNCTION__,SHOPP_DEBUG_ERR);
			return false;
		}
	} else if (shopp_setting('account_system') == "wordpress") {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: Wordpress account id must by specified in data array with key wpuser.",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}

	// handle duplicate or missing email address
	if ( isset($data['email']) ) {
		$c = new Customer($data['email'], 'email');
		if ( ! empty($c->id) ) {
			if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: Customer with email {$data['email']} already exists.",__FUNCTION__,SHOPP_DEBUG_ERR);
			return false;
		}
	} else {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: Email address must by specified in data array with key email.",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}

	// handle missing first or last name
	if ( ! isset($data['firstname']) || ! isset($data['lastname']) ) {
		if(SHOPP_DEBUG) new ShoppError("shopp_add_customer failure: Data array missing firstname or lastname.",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}

	$shipping = array();
	$billing = array();
	$Customer = new Customer();

	foreach ( $data as $key => $value ) {
		if ( in_array($key, $map) ) $Customer->{$key} = $value;
		else if( SHOPP_DEBUG && ! in_array( $key, array_keys($address_map) ) )
			new ShoppError("shopp_add_customer notice: Invalid customer data $key",__FUNCTION__,SHOPP_DEBUG_ERR);
		if ( in_array( $key, array_keys($address_map) ) ) {
			$type = ( 's' == substr($key, 0, 1) ? 'shipping' : 'billing' );
			$$type[$address_map[$key]] = $value;
		}
	}

	$Customer->save();
	if ( empty($Customer->id) ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: Could not create customer.",__FUNCTION__,SHOPP_DEBUG_ERR);
	}

	if ( ! empty($shipping) ) shopp_add_customer_address( $Customer->id, $shipping, 'shipping' );
	if ( ! empty($billing) ) shopp_add_customer_address( $Customer->id, $billing, 'billing' );

	return $Customer->id;
} // end shopp_add_customer

/**
 * shopp_add_customer_address - add or update an address for a customer
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int $customer (required) the customer id the address is added to
 * @param array $data (required) key value pairs for address, values can be keyed 'address', 'xaddress', 'city', 'state', 'postcode', 'country', 'geocode',  and 'residential' (residential added to shipping address)
 * @param string $type (optional default: billing) billing, shipping, or both
 * @return mixed int id for one address creation/update, array of ids if created/updated both shipping and billing, bool false on error
 **/
function shopp_add_customer_address (  $customer = false, $data = false, $type = 'billing' ) {
	if ( ! $customer ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: Customer id required.",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}

	if ( empty($data) ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: data array is empty",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}

	$map = array( 'address', 'xaddress', 'city', 'state', 'postcode', 'country', 'geocode', 'residential' );
	$address = array();

	foreach ( $map as $property ) {
		if ( isset($data[$property]) ) $address[$property] = $data[$property];
		if ( 'residential' == $property ) $address[$propery] = value_is_true($data[$property]) ? "on" : "off";
	}

	$Billing = new BillingAddress( $customer, 'customer' );
	$Shipping = new ShippingAddress ( $customer, 'customer');
	if ( $type == 'billing' ) {
		$Billing->updates($address);
		if ( apply_filters('shopp_validate_address', true, $Billing) ) {
			$Billing->save();
			return $Billing->id;
		}
	} else if ($type = 'shipping') {
		$Shipping->updates($address);
		if ( apply_filters('shopp_validate_address', true, $Shipping) ) {
			$Shipping->save();
			return $Shipping->id;
		}
	} else { // both
		$Billing->updates($address);
		$Shipping->updates($address);
		if ( apply_filters('shopp_validate_address', true, $Billing) && apply_filters('shopp_validate_address', true, $Shipping) ) {
			$Billing->save();
			$Shipping->save();
			return array('billing' => $Billing->id, 'shipping' => $Shipping->id);
		}
	}

	if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: one or more addresses did not validate.",__FUNCTION__,SHOPP_DEBUG_ERR);
	return false;
}

/**
 * shopp_rmv_customer - remove a customer, and data associated with the customer
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int $customer (required) id of the customer to remove
 * @return bool true on success, false on failure
 **/
function shopp_rmv_customer (  $customer = false ) {
	if ( ! $customer ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: Customer id required.",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}

	$Customer = new Customer($customer);
	if ( empty($Customer->id) ) {
		if(SHOPP_DEBUG) new ShoppError("shopp_rmv_customer notice: No such customer with id $customer",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}

	// Remove Addresses
	$Billing = new BillingAddress($customer, 'customer');
	$Shipping = new ShippingAddress($customer, 'customer');
	if ( ! empty($Billing->id) ) $Billing->delete();
	if ( ! empty($Shipping->id) ) $Shipping->delete();

	// Remove Meta records
	$metas = shopp_meta ( $customer, 'customer' );
	foreach( $metas as $meta ) shopp_rmv_meta ( $meta->id );

	// Remove Customer record
	$Customer->delete();

	return true;
}

/**
 * shopp_address - return an address record by id
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int $address (required) the address id to retrieve
 * @return array of address fields
 **/
function shopp_address (  $address = false ) {
	if ( ! $address ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: Missing address id parameter.",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}
	$Address = new Address($address);

	if ( empty($Address->id) ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: No such address with id $address.",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}

	$_ = array();
	$map = array('type', 'address' , 'xaddress', 'city', 'state', 'country', 'postcode', 'geocode', 'residential');
	foreach ( $map as $property ) {
		if ( isset($Address->{$property}) ) $_[$property] = $Address->{$property};
	}
	return $_;
}

/**
 * shopp_customer_address_count - get count of addresses stored on customer record
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int $customer (required) the customer id
 * @return int number of address records that exist for the customer
 **/
function shopp_customer_address_count (  $customer = false ) {
	if ( ! $customer ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: customer id required.",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}
	$table = DatabaseObject::tablename(Address::$table);
	$customer = db::escape($customer);
	return db::query("select count(*) from $table where customer=$customer");
}

/**
 * shopp_customer_addresses - get list of addresses for a customer
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int $customer (required) the customer id
 * @return array list of addresses
 **/
function shopp_customer_addresses (  $customer = false ) {
	if ( ! $customer ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: customer id required.",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}

	$customer = $db::escape($customer);
	$table = DatabaseObject::tablename(Address::$table);
	$addresses = db::query("select * from $table where customer=$customer", AS_ARRAY);

	$_ = array();
	$map = array('id', 'address' , 'xaddress', 'city', 'state', 'country', 'postcode', 'geocode', 'residential');
	foreach ( $addresses as $address ) {
		$_[$address[$type]] = array();
		foreach ( $map as $property ) {
			if ( isset($Address->{$property}) ) $_[$address[$type]][$property] = $address[$property];
		}
	}
	return $_;
}

/**
 * shopp_rmv_customer_address - remove an address
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int $address the address id to remove
 * @return bool true on success, false on failure
 **/
function shopp_rmv_customer_address (  $address = false ) {
	if ( ! $address ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: Missing address id parameter.",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}
	$Address = new Address($address);

	if ( empty($Address->id) ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: No such address with id $address.",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}

	return $Address->delete();
}

?>