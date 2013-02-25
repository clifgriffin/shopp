<?php
/**
 * Totals.php
 * Order totals calculator
 *
 * @author Jonathan Davis
 * @version 1.9
 * @copyright Ingenesis Limited, February 2013
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @subpackage ordertotals
 **/

/**
 * OrderTotals
 *
 * Manages order total registers
 *
 * @author Jonathan Davis
 * @since 1.3
 * @version 1.0
 * @package ordertotals
 **/
class OrderTotals extends RegistryManager {

	const TOTAL = 'total';
	private $register = array( self::TOTAL => null );	// The main registry of entries
	private $_list   = array( self::TOTAL => null );	// Aggregated sum entries

	private $checks   = array();	// Track changes in the column registers

	public function __construct () {
		$this->_list['total'] = new OrderTotal( array('amount' => 0.0) );
	}

	public function register ( OrderAmount $Entry ) {
		$register = $Entry->register();
		if ( ! isset($this->register[ $register ]) ) $this->register[ $register ] = array();

		$this->register[ $register ][ $Entry->id() ] = $Entry;

		$this->total($register);
	}

	/**
	 * Get a specific register OrderAmount class entry
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @param string $register The register to find the entry in
	 * @param string $id The entry identifier
	 * @return OrderAmount The order amount entry
	 **/
	public function get ( string $register, string $id ) {
		if ( ! isset($this->register[ $register ]) ) return false;
		$Register = &$this->register[ $register ];
		if ( ! isset($Register[$id]) ) return false;
		return $Register[$id];
	}

	/**
	 * Update a specific register entry
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @param string $register The name of the register to update
	 * @param string $id The id string of the entry
	 * @param float $amount The amount to change
	 * @return boolean True for success, false otherwise
	 **/
	public function update ( string $register, string $id, float $amount ) {
		if ( ! isset($this->register[ $register ]) ) return false;

		$Register = &$this->register[ $register ];

		if ( ! isset($Register[$id]) ) return false;
		$Entry = $Register[$id];

		// Set the new amount
		$Entry->amount($amount);

		// Recalculate the total for this register and the grand totals
		$this->total($register);

		return true;
	}

	/**
	 * Get the total amount of a register
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @param string $register The register to calculate/get totals for
	 * @return mixed Returns the total amount as a float, or boolean false if the register doesn't exist
	 **/
	public function total ( $register = self::TOTAL ) {

		if ( empty($register) ) $register = self::TOTAL;
		if ( ! isset($this->register[ $register ]) ) return false;

		$Total = &$this->_list[ $register ];
		$Register = &$this->register[ $register ];

		// Return the current total for the register if it hasn't changed
		if ( ! $this->changed($register) && self::TOTAL != $register )
			return (float)$Total->amount();

		// Calculate a new total amount for the register
		$Total = new OrderTotal( array('amount' => 0.0) );
		foreach ( $Register as $Entry) {
			// Set the amount based on CREDIT or DEBIT column
			$amount = OrderAmount::CREDIT == $Entry->column() ? $Entry->amount() * -1 : $Entry->amount();
			$Total->amount( $Total->amount() + $amount );
		}

		// Return the newly calculated amount if this is the total register
		if ( self::TOTAL == $register ) return $Total->amount();

		// For other registers, add or update that register's total entry for it in the totals register
		$GrandTotal = &$this->register[ self::TOTAL ];

		if ( ! isset($GrandTotal[ $register ]) ) // Add a new total entry
			$GrandTotal[ $register ] = new OrderTotal( array('id' => $register, 'amount' => $Total->amount() ) );
		else $GrandTotal[ $register ]->amount($Total->amount()); // Update the existing entry amount with the new total

		// If the total register did change, re-calculate the total register
		if ( $this->changed('total') ) $this->total();

		// Return the newly calculated amount
		return $Total->amount();
	}

	/**
	 * Determines if the register has changed since last checked
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @param string $register The name of the register to check
	 * @return boolean True when the register has changed
	 **/
	private function changed ( string $register ) {
		$check = isset($this->checks[ $register ]) ? $this->checks[$register] : 0;
		$this->checks[$register] = crc32( serialize($this->register[$register]) );
		if ( 0 == $check ) return true;
		return ( $check != $this->checks[$register] );
	}

}

abstract class OrderAmount {

	// Define types
	const DEBIT = 1;
	const CREDIT = -1;

	protected $register = '';	// Register name
	protected $id = '';			// Identifier name/id
	protected $column = null;	// A flag to determine the role of the amount
	protected $amount = 0.0;	// The amount the amount type

	public function __construct ( array $options = array() ) {
		$this->populate($options);
	}

	protected function populate ($options) {
		foreach ($options as $name => $value)
			if ( isset($this->$name) ) $this->$name = $value;
	}

	public function id () {
		// Generate a quick checksum if no ID was given
		if ( empty($this->id) ) $this->id = crc32(serialize($this));
		return $this->id;
	}

	public function register () {
		return $this->register;
	}

	public function &amount ( float $value = null ) {
		if ( ! is_null($value) ) $this->amount = $value;
		return $this->amount;
	}

	public function column () {
		return $this->column;
	}

}

class OrderTotal extends OrderAmount {
	protected $register = 'total';

	public function label () {
		return __('Total','Shopp');
	}

}

class OrderAmountDebit extends OrderAmount {
	protected $column = OrderAmount::DEBIT;
}

class OrderAmountCredit extends OrderAmount {
	protected $column = OrderAmount::CREDIT;
}

class OrderAmountDiscount extends OrderAmountCredit {
	protected $register = 'discount';
	protected $setting = false;	// The related discount/promo setting
	protected $code = false;	// The code used

	public function label () {
		return __('Discounts','Shopp');
	}

}

class OrderAmountAccountCredit extends OrderAmountCredit {
	protected $register = 'account';

	public function label () {
		return __('Credit','Shopp');
	}
}

class OrderAmountGiftCertificate extends OrderAmountCredit {
	protected $register = 'certificate';

	public function label () {
		return __('Gift Certificate','Shopp');
	}
}

class OrderAmountGiftCard extends OrderAmountCredit {
	protected $register = 'giftcard';

	public function label () {
		return __('Gift Card','Shopp');
	}
}

class OrderAmountFee extends OrderAmountDebit {
	protected $register = 'fee';

	public function label () {
		return __('Fee','Shopp');
	}
}

class OrderAmountTax extends OrderAmountDebit {
	protected $register = 'tax';
	protected $setting = false;	// The related tax setting
	protected $rate = 0.0;	// The applied rate
	protected $items = array();

	public function label () {
		return __('Tax','Shopp');
	}
}

class OrderAmountShipping extends OrderAmountDebit {
	protected $register = 'shipping';
	protected $setting = false;
	protected $delivery = false;
	protected $items = array();

	public function label () {
		return __('Shipping','Shopp');
	}
}