<?php
/**
 * Address.php
 *
 * Provides foundational address data management framework
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, February 21, 2011
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @since 1.2
 * @subpackage shopp
 **/

/**
 * Address
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package shopp
 **/
class Address extends DatabaseObject {
	static $table = "address";

	/**
	 * Address constructor
	 *
	 * @author Jonathan Davis
	 *
	 * @return void
	 **/
	function __construct ($id=false,$key=false) {
		$this->init(self::$table);
		$this->load($id,$key);
	}

	/**
	 * Determines the domestic area name from a U.S. ZIP code or
	 * Canadian postal code.
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return string
	 **/
	function postarea () {
		global $Shopp;
		$code = $this->postcode;
		$areas = Lookup::country_areas();

		// Skip if there are no areas for this country
		if (!isset($areas[$this->country])) return false;

		// If no postcode is provided, return the first regional column
		if (empty($this->postcode)) return key($areas[$this->country]);

		// Lookup US area name
		if (preg_match("/\d{5}(\-\d{4})?/",$code)) {

			foreach ($areas['US'] as $name => $states) {
				foreach ($states as $id => $coderange) {
					for($i = 0; $i<count($coderange); $i+=2) {
						if ($code >= (int)$coderange[$i] && $code <= (int)$coderange[$i+1]) {
							$this->state = $id;
							return $name;
						}
					}
				}
			}
		}

		// Lookup Canadian area name
		if (preg_match("/\w\d\w\s*\d\w\d/",$code)) {

			foreach ($areas['CA'] as $name => $provinces) {
				foreach ($provinces as $id => $fsas) {
					if (in_array(substr($code,0,1),$fsas)) {
						$this->state = $id;
						return $name;
					}
				}
			}
			return $name;

		}

		return false;
	}
} // END class Address


/**
 * Billing class
 *
 * Billing Address
 *
 * @author Jonathan Davis
 * @version 1.2
 * @copyright Ingenesis Limited, 21 February, 2011
 * @package address
 **/

class BillingAddress extends Address {

	var $type = 'billing';

	/**
	 * Billing constructor
	 *
	 * @author Jonathan Davis
	 *
	 * @param int $id The ID of the record
	 * @param string $key The column name for the specified ID
	 * @return void
	 **/
	function __construct ($id=false,$key='customer') {
		$this->init(self::$table);
		$this->load(array($key => $id,'type' => 'billing'));
		$this->type = 'billing';
	}

	function exportcolumns () {
		$prefix = "b.";
		return array(
			$prefix.'address' => __('Billing Street Address','Shopp'),
			$prefix.'xaddress' => __('Billing Street Address 2','Shopp'),
			$prefix.'city' => __('Billing City','Shopp'),
			$prefix.'state' => __('Billing State/Province','Shopp'),
			$prefix.'country' => __('Billing Country','Shopp'),
			$prefix.'postcode' => __('Billing Postal Code','Shopp'),
			);
	}

} // end Billing class

/**
 * Shipping class
 *
 * The shipping address manager
 *
 * @author Jonathan Davis
 * @version 1.2
 * @copyright Ingenesis Limited, 21 February, 2011
 * @package address
 **/
class ShippingAddress extends Address {

	var $type = 'shipping';
	var $method = false;

	/**
	 * Shipping constructor
	 *
	 * @author Jonathan Davis
	 *
	 * @param int $id The ID of the record
	 * @param string $key The column name for the specified ID
	 * @return void
	 **/
	function __construct ($id=false,$key='customer') {
		$this->init(self::$table);
		$this->load(array($key => $id,'type' => 'shipping'));
		$this->type = 'shipping';
	}

	/**
	 * Registry of supported export fields
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return array
	 **/
	function exportcolumns () {
		$prefix = "s.";
		return array(
			$prefix.'address' => __('Shipping Street Address','Shopp'),
			$prefix.'xaddress' => __('Shipping Street Address 2','Shopp'),
			$prefix.'city' => __('Shipping City','Shopp'),
			$prefix.'state' => __('Shipping State/Province','Shopp'),
			$prefix.'country' => __('Shipping Country','Shopp'),
			$prefix.'postcode' => __('Shipping Postal Code','Shopp'),
			);
	}

	/**
	 * Sets the shipping address location for calculating
	 * shipping estimates.
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void Description...
	 **/
	function destination ($data=false) {
		global $Shopp;

		$base = $Shopp->Settings->get('base_operations');
		$countries = Lookup::countries();
		$regions = Lookup::regions();

		if ($data) $this->updates($data);

		// Update state if postcode changes for tax updates
		if (isset($this->postcode))
			$this->postarea();

		if (empty($this->country))
			$this->country = $base['country'];

		$this->region = false;
		if (isset($regions[$countries[$this->country]['region']]))
			$this->region = $regions[$countries[$this->country]['region']];

	}


} // END class Shipping

?>