<?php
/**
 * Totals.php
 * Order totals calculator
 *
 * @author Jonathan Davis
 * @version 1.0
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
class OrderTotals extends ListFramework {

	const TOTAL = 'total';
	private $register = array( self::TOTAL => null );	// Registry of "register" entries

	private $checks   = array();	// Track changes in the column registers

	public function __construct () {
		$this->add('total', new OrderTotal( array('amount' => 0.0) ));
	}

	public function register ( OrderTotalAmount $Entry, $onremove = false ) {
		$register = $Entry->register($this);
		if ( ! isset($this->register[ $register ]) ) $this->register[ $register ] = array();

		$this->register[ $register ][ $Entry->id() ] = $Entry;

		// Register auto entry removal on dispatch of a given WP action
		if ( ! empty($onremove) )
			add_action($onremove, array($Entry,'remove') );

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
	public function &entry ( string $register, string $id ) {
		if ( ! isset($this->register[ $register ]) ) return false;
		$Register = &$this->register[ $register ];

		if ( ! isset($Register[$id]) ) return false;
		return $Register[$id];
	}

	/**
	 * Take off an OrderAmount entry from the register
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @param string $register The register to find the entry in
	 * @param string $id The entry identifier
	 * @return boolean True if succesful, false otherwise
	 **/
	public function takeoff ( string $register, string $id ) {

		if ( ! isset($this->register[ $register ]) ) return false;
		$Register = &$this->register[ $register ];

		if ( ! isset($Register[ $id ])) return false;

		unset($Register[ $id ]);
		return true;
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

		if ( $this->exists($register) ) $Total = &$this->get( $register ); // &$this->_list[ $register ];
		else $Total = &$this->add( $register, false );

		$Register = &$this->register[ $register ];

		// Return the current total for the register if it hasn't changed
		if ( ! $this->changed($register) && self::TOTAL != $register )
			return (float)$Total->amount();

		// Calculate a new total amount for the register
		$Total = new OrderTotal( array('amount' => 0.0) );
		if ( empty($Register) ) return $Total->amount();

		foreach ( $Register as $Entry) {
			$amount = $Entry->amount();
			if ( OrderTotalAmount::CREDIT == $Entry->column() ) 	// Set the amount based on transaction column
				$amount = $Entry->amount() * OrderTotalAmount::CREDIT;
			$Total->amount( $Total->amount() + $amount );
		}

		// Do not include entry in grand total if it is not a balance adjusting register
		if ( null === $Entry->column() ) return $Total->amount();

		// For other registers, add or update that register's total entry for it in the totals register
		$GrandTotal = &$this->register[ self::TOTAL ];

		if ( ! isset($GrandTotal[ $register ]) ) // Add a new total entry
			$GrandTotal[ $register ] = new OrderTotal( array('id' => $register, 'amount' => $Total->amount() ) );
		else $GrandTotal[ $register ]->amount($Total->amount()); // Update the existing entry amount with the new total

		// If the total register did change, re-calculate the total register
		if ( $this->changed('total') ) $this->total();

		// Return the newly calculated amount
		return apply_filters( "shopp_ordertotals_{$register}_total", $Total->amount(), $Register );
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
		$this->checks[$register] = hash('crc32b', serialize($this->register[$register]) );
		if ( 0 == $check ) return true;
		return ( $check != $this->checks[$register] );
	}

}

abstract class OrderTotalAmount {

	// Transaction type constants
	const DEBIT = 1;
	const CREDIT = -1;

	protected $register = '';	// Register name
	protected $id = '';			// Identifier name/id
	protected $column = null;	// A flag to determine the role of the amount
	protected $amount = 0.0;	// The amount the amount type
	protected $parent = false;	// The parent OrderTotals instance

	public function __construct ( array $options = array() ) {
		$this->populate($options);
	}

	protected function populate ($options) {
		foreach ($options as $name => $value)
			if ( isset($this->$name) ) $this->$name = $value;
	}

	public function id () {
		// Generate a quick checksum if no ID was given
		if ( empty($this->id) ) $this->id = hash('crc32b',serialize($this));
		return $this->id;
	}

	public function register ( OrderTotals $OrderTotals ) {
		$this->parent = $OrderTotals;
		return $this->register;
	}

	public function &amount ( float $value = null ) {
		if ( ! is_null($value) ) $this->amount = $value;
		return $this->amount;
	}

	public function column () {
		return $this->column;
	}

	public function remove () {
		var_dump(__METHOD__);
		$OrderTotals = $this->parent;
		$OrderTotals->takeoff($this->register,$this->id);
	}

}

class OrderTotal extends OrderTotalAmount {
	protected $register = 'total';

	public function label () {
		return __('Total','Shopp');
	}

}

class OrderAmountDebit extends OrderTotalAmount {
	protected $column = OrderTotalAmount::DEBIT;
}

class OrderAmountCredit extends OrderTotalAmount {
	protected $column = OrderTotalAmount::CREDIT;
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
	protected $quantity = 0;

	public function label () {
		return __('Fee','Shopp');
	}
}

class OrderAmountItem  extends OrderAmountDebit {
	protected $register = 'order';

	public function __construct ( CartItem $Item ) {
		$this->unit = &$Item->unitprice;
		$this->amount = &$Item->total;
		$this->id = $Item->fingerprint();
	}

	public function label () {
		return __('Subtotal','Shopp');
	}

}

class OrderAmountItemQuantity extends OrderTotalAmount {
	protected $register = 'quantity';

	public function __construct ( CartItem $Item ) {
		$this->amount = &$Item->quantity;
		$this->id = $Item->fingerprint();
	}

	public function label () {
		return __('quantity','Shopp');
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