<?php
/**
 * Shiprates.php
 *
 * Provides shipping service rate options
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, April 2013
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @subpackage shiprates
 **/

defined( 'WPINC' ) || header( 'HTTP/1.1 403' ) & exit; // Prevent direct access

/**
 * Finds applicable service rates
 *
 * @author Jonathan Davis
 * @since 1.3
 * @package shiprates
 **/
class ShoppShiprates extends ListFramework {

	// pri $services = array();		// The generated shipping method options

	private $selected = false;		// The currently selected shipping method
	private $fees = 0;				// Merchant shipping fees
	private $track = array();		// modules register properties for the change checksum hash
	private $request = false;		// The generated request checksum

	private $free = false;			// Free shipping
	private $realtime = false;		// Flag for when realtime shipping systems are enabled


	/**
	 * Returns the currently selected shiprate service
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @param string $selected (optional) The slug to set as the selected shiprate service option
	 * @return ShoppShiprateService The currently selected shiprate service
	 **/
	public function selected ( string $selected = null ) {

		if ( is_null($selected) ) {
			if ( ! $this->exists( $this->selected ) )
				return false;
		}

		if ( $this->exists( $selected ) )
			$this->selected = $selected;

		return $this->get( $this->selected );

	}

	/**
	 * Adds up line item shipping fees
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @param ShoppShippableItem $Item A ShoppShippableItem compatible item
	 * @return void
	 **/
	public function itemfees ( ShoppShippableItem $Item ) {
		$this->fees += $Item->fees;
	}

	/**
	 * Provides the total custom merchant-defined shipping fees
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @return float The shipping fee amount
	 **/
	public function fees () {
		return (float)shopp_setting('order_shipfee') + $this->fees;
	}

	/**
	 * Checks or sets if shipping is free
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @param boolean $free Flag to set the free shipping value
	 * @return boolean True if free, false otherwise
	 **/
	public function free ( boolean $free = null ) {
		if ( isset($free) )
			$this->free = $free;
		return $this->free;
	}

	/**
	 * Returns the amount of the currently selected service
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @return float The cost amount of the selected shiprate service
	 **/
	public function amount () {
		$selection = $this->selected();
		if ( false === $selection ) return false;	// Check selection first, since a selection must be made
		if ( $this->free() ) return (float)0;		// regardless of free shipping
		return (float)$selection->amount;
	}

	/**
	 * Adds data tracking to check for request changes
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @param string $name The name of the value to track
	 * @param mixed $value The data to track stored as a reference
	 * @return void
	 **/
	public function track ( string $name, &$value ) {
		$this->track[ $name ] = $value;
	}

	/**
	 * Determines if any shipping services are available
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @return boolean True if there are shipping services, false if not or if shipping is disabled
	 **/
	public function exist () {

		if ( $this->disabled() ) return false;

		if ( $this->count() == 0 ) return false;

		return true;

	}

	/**
	 * Calculates the shipping rate amounts using active shipping modules
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @return float The shipping rate service amount, or false if disabled
	 **/
	public function calculate () {

		if ( $this->disabled() ) return false;		// Shipping disabled

		if ( $this->free() ) return 0;				// Free shipping for this order

		if ( $this->requested() ) 					// Return the current amount if the request hasn't changed
			return (float)$this->amount();

		// Initialize shipping modules
		do_action('shopp_calculate_shipping_init');

		// Calculate active shipping module service methods
		$this->modules();

		// Find the lowest cost option to use as a default selection
		$lowest = $this->lowrate();

		// If nothing is currently, select the lowest cost option
		if ( ! $this->selected() && false !== $lowest )
			$this->selected( $lowest->slug );

		// Return the amount
		return (float)$this->amount();

	}

	/**
	 * Runs the shipping module calculations to populate the applicable shipping service rate options
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @return void
	 **/
	private function modules () {

		$services = array();

		// Run shipping module aggregate shipping calculations
		do_action_ref_array('shopp_calculate_shipping', array(&$services, ShoppOrder() ));

		// No shipping options were generated, try fallback calculators for realtime rate failures
		if ( empty($services) && $this->realtime ) {
			do_action('shopp_calculate_fallback_shipping_init');
			do_action_ref_array('shopp_calculate_fallback_shipping', array(&$services, ShoppOrder() ));
		}

		if ( empty($services) ) return false; // Still no rates, bail

		$this->clear();
		$this->populate($services);
		$this->sort('self::sort');

	}

	/**
	 * Determines the lowest cost
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @return ShoppShiprateService
	 **/
	private function lowrate () {

		$estimate = false;
		foreach ($this as $name => $option) {

			// Skip if not to be included
			if ( ! $option->estimate ) continue;

			// If the option amount is less than current estimate
			// Update the estimate to use this option instead
			if ( ! $estimate || $option->amount < $estimate->amount )
				$estimate = $option;
		}

		return $estimate;

	}

	/**
	 * Determines if the request has changed
	 *
	 * This method uses a fast hash to checksum all of the variable
	 * data that might be used to calculate shipping service rates.
	 * The last request is kept and checked against the current
	 * request to see if anything has changed. If nothing has
	 * changed, the shipping calculations can be skipped and the
	 * current shipping service rates are kept along with the current
	 * shipping rate amount.
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @return boolean True if the current request is the same as the prior request
	 **/
	private function requested () {
		$request = hash('crc32b', serialize($this->track));
		if ( $this->request == $request ) return true;
		$this->request = $request;
		return false;
	}

	/**
	 * Determines if the shipping system is disabled
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @return void Description...
	 **/
	private function disabled () {

		// If shipping is disabled
		if ( ! shopp_setting_enabled('shipping') ) return true;

		return false;
	}

}

/**
 * ShippingOption class
 *
 * A data structure for order shipping options
 *
 * @author Jonathan Davis
 * @since 1.1
 * @version 1.2
 * @package shopp
 * @subpackage shiprates
 **/
class ShoppShiprateService {

	public $name;				// Name of the shipping option
	public $slug;				// URL-safe name of the shipping option @since 1.2
	public $amount;				// Amount (cost) of the shipping option
	public $delivery;			// Estimated delivery of the shipping option
	public $estimate;			// Include option in estimate
	public $items = array();	// Item shipping rates for this shipping option

	/**
	 * Builds a shipping option from a configured/calculated
	 * shipping rate array
	 *
	 * Example:
	 * new ShippingOption(array(
	 * 		'name' => 'Name of Shipping Rate Method',
	 * 		'slug' => 'rate-method-slug',
	 * 		'amount' => 0.99,
	 * 		'delivery' => '1d-2d',
	 * 		'items' => array(
	 * 			0 => 0.99,
	 * 			1 => 0.50
	 * 		)
	 * ));
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param array $rate The calculated shipping rate
	 * @param boolean $estimate Flag to be included/excluded from estimates
	 * @return void
	 **/
	public function __construct ( array $rate, $estimate = true ) {

		if (!isset($rate['slug'])) // Fire off an error if the slug is not provided
			return ( ! shopp_debug('A slug (string) value is required in the $rate array parameter when constructing a new ShoppShiprateService') );

		$this->name = $rate['name'];
		$this->slug = $rate['slug'];
		$this->amount = $rate['amount'];
		$this->estimate = $estimate;

		if ( ! empty($rate['delivery']) )
			$this->delivery = $rate['delivery'];
		if ( ! empty($rate['items']) )
			$this->items = $rate['items'];
	}

} // END class ShippingOption

if ( ! class_exists('ShippingOption',false) ) {
	class ShippingOption extends ShoppShiprateService {
	}
}

/**
 * Converts a line item object to on that is compatible with the ShoppShiprates system
 *
 * @author Jonathan Davis
 * @since 1.3
 * @package shiprates
 **/
class ShoppShippableItem {

	private $class;
	private $Object;

	public $fees = 0;
	public $weight = 0;
	public $length = 0;
	public $width = 0;
	public $height = 0;
	public $shipsfree = false;

	function __construct ( $Object ) {

		$this->Object = $Object;
		$this->class = get_class($Object);

		switch ( $this->class ) {
			case 'ShoppCartItem': $this->ShoppCartItem(); break;
		}

	}

	function ShoppCartItem () {
		$Item = $this->Object;
		if ( ! $Item->shipped ) return false;

		$this->fees = $Item->shipfee;
		$this->weight = $Item->weight;
		$this->length = $Item->length;
		$this->width = $Item->width;
		$this->height = $Item->height;
		$this->shipsfree = $Item->freeshipping;

	}

}