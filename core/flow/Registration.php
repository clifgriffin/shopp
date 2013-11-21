<?php
/**
 * Registration.php
 *
 * Handles customer registration form processing
 *
 * @author Jonathan Davis
 * @version 1.3
 * @copyright Ingenesis Limited, April 2013
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @subpackage order
 **/

defined( 'WPINC' ) || header( 'HTTP/1.1 403' ) & exit; // Prevent direct access

/**
 * Customer registration manager
 *
 * @author Jonathan Davis
 * @since 1.3
 * @version 1
 * @package order
 **/
class ShoppRegistration {

	const PROCESS = 'shopp_registration';

	private $form = array();		// Holds the cleaned up POST data

	static $defaults = array(
		'sameaddress' => 'off',
		'firstname' => '',
		'lastname' => '',
		'phone' => '',
		'company' => '',
		'billing' => array(),
		'shipping' => array(),
		'info' => array(),
	);

	public function __construct () {

		$_POST = apply_filters('shopp_customer_registration',$_POST);

		$submitted = stripslashes_deep($_POST);					// Clean it up
		$this->form = array_merge(self::$defaults, $submitted);	// Capture it

		if ( ! self::submitted() ) return;

		add_action('parse_request', array($this, 'info'));
		add_action('parse_request', array($this, 'customer'));
		add_action('parse_request', array($this, 'shipaddress'));
		add_action('parse_request', array($this, 'billaddress'));

		add_action('parse_request', array(__CLASS__, 'process'));

		add_filter('shopp_validate_registration', 'ShoppFormValidation::names');
		add_filter('shopp_validate_registration', 'ShoppFormValidation::email');
		add_filter('shopp_validate_registration', 'ShoppFormValidation::login');
		add_filter('shopp_validate_registration', 'ShoppFormValidation::passwords');
		add_filter('shopp_validate_registration', 'ShoppFormValidation::shipaddress');
		add_filter('shopp_validate_registration', 'ShoppFormValidation::billaddress');

	}

	public static function submitted () {
		return isset($_POST[ self::PROCESS ]);
	}


	public function form ( string $key = null ) {
		if ( isset($key) ) {
			if ( isset($this->form[ $key ]) )
				return $this->form[ $key ];
			else return false;
		}

		return $this->form;
	}

	public function info () {
		$Customer = ShoppOrder()->Customer;

		if ( $this->form('info') )
			$Customer->info = $this->form('info');

	}

	public function customer () {

		$Customer = ShoppOrder()->Customer;

		$updates = array(
			'firstname' => $this->form('firstname'),
			'lastname' => $this->form('lastname'),
			'company' => $this->form('company'),
			'email' => $this->form('email'),
			'phone' => $this->form('phone'),
			'info' => $this->form('info'),
            'password' => $this->form('password'),
			'loginname' => $this->form('loginname')
		);

		// Remove invalid characters from the phone number
		$updates['phone'] = preg_replace('/[^\d\(\)\-+\. (ext|x)]/','', $updates['phone'] );

		if ( empty($Customer) ) $Customer = new ShoppCustomer();
		else $Customer->reset();

		$Customer->updates($updates);

		// Keep confirm-password field value when showing checkout validation errors
		$confirmpass = $this->form('confirm-password');
		if ( ! empty($confirmpass) )
			$Customer->_confirm_password = $confirmpass;

	}

	public function shipaddress () {
		$ShippingAddress = ShoppOrder()->Shipping;
		$BillingAddress = ShoppOrder()->Billing;

		if ( empty($ShippingAddress) )
			$ShippingAddress = new ShippingAddress();

		$form = $this->form('shipping');

		if ( ! empty($form) ) $ShippingAddress->updates($form);

		// Handle same address copying
		ShoppOrder()->sameaddress = strtolower( $this->form('sameaddress') );

		if ( 'billing' == ShoppOrder()->sameaddress )
			$BillingAddress->updates($form);

	}

	public function billaddress () {

		$BillingAddress = ShoppOrder()->Billing;
		$ShippingAddress = ShoppOrder()->Shipping;

		if ( empty($BillingAddress) )
			$BillingAddress = new BillingAddress();

		$form = $this->form('billing');

		// Prevent overwriting the card data when updating the BillingAddress
		$ignore = array();
		if ( ! empty($form['card']) && preg_replace('/[^\d]/','',$form['card']) == substr($BillingAddress->card,-4) )
			$ignore[] = 'card';

		$BillingAddress->updates($form,$ignore);

		// Handle same address copying
		ShoppOrder()->sameaddress = strtolower( $this->form('sameaddress') );
		if ( 'shipping' == ShoppOrder()->sameaddress )
			$ShippingAddress->updates($form);

	}

	public static function process () {

		if ( true !== apply_filters('shopp_validate_registration', true) ) return;

		$Customer = ShoppOrder()->Customer;

		if ( $Customer->session(ShoppCustomer::GUEST) ) {
			$Customer->type = __('Guest', 'Shopp');
		} else {

			// WordPress account integration used, customer has no wp user
			if ( 'wordpress' == shopp_setting('account_system') && empty($Customer->wpuser) ) {
				if ( $wpuser = get_current_user_id() ) $Customer->wpuser = $wpuser; // use logged in WordPress account
				else $Customer->create_wpuser(); // not logged in, create new account
			}

			if ( ! $Customer->exists() ) {
				$Customer->id = false;
				shopp_debug('Creating new Shopp customer record');
				if ( empty($Customer->password) )
					$Customer->password = wp_generate_password(12, true);

				if ( 'shopp' == shopp_setting('account_system') ) $Customer->notification();
				$Customer->password = wp_hash_password($Customer->password);
				if ( isset($Customer->passhash) ) $Customer->password = $Customer->passhash;
			} else unset($Customer->password); // Existing customer, do not overwrite password field!

		}

		// New customer, save hashed password
		$Customer->save();
        $Customer->password = '';

		// Update billing and shipping addresses
		$addresses = array('Billing', 'Shipping');
		foreach ($addresses as $Address) {
			if ( empty(ShoppOrder()->$Address->address) ) continue;
			$Address = ShoppOrder()->$Address;
			$Address->customer = $Customer->id;
			$Address->save();
		}

		do_action('shopp_customer_registered', $Customer);

        if ( apply_filters('shopp_registration_redirect', false) )
			Shopp::redirect( Shopp::url(false, 'account') );
	}

}