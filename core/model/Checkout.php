<?php
/**
 * Checkout.php
 *
 * Handles checkout form processing
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
 * Checkout manager
 *
 * @author Jonathan Davis
 * @since 1.1
 * @version 1.2
 * @package order
 **/
class ShoppCheckout {

	private $form = array();		// Holds the cleaned up POST data
	private $confirmed = false;		// Flag to indicate

	static $defaults = array(
		'guest' => false,
		'sameaddress' => 'off',
		'firstname' => '',
		'lastname' => '',
		'phone' => '',
		'company' => '',
		'shipmethod' => false,
		'billing' => array(),
		'shipping' => array(),
		'info' => array(),
		'data' => array()
	);

	function __construct () {

		ShoppingObject::store('confirmed',$this->confirmed);

		if ( empty($_POST) ) return;

		$submitted = stripslashes_deep($_POST);				// Clean it up
		$this->form = array_merge(self::$defaults, $submitted);	// Capture it

		$action = $this->form('checkout');

		add_action('shopp_confirm_order', array($this, 'confirmed'));

		if ( 'process' != $action) return;

		add_action('shopp_process_shipmethod', array($this, 'shipmethod'));

		add_action('shopp_process_checkout', array($this, 'data'));
		add_action('shopp_process_checkout', array($this, 'customer'));
		add_action('shopp_process_checkout', array($this, 'shipaddress'));
		add_action('shopp_process_checkout', array($this, 'shipmethod'));
		add_action('shopp_process_checkout', array($this, 'billaddress'));
		add_action('shopp_process_checkout', array($this, 'payment'));
		add_action('shopp_process_checkout', array($this, 'process'));

	}

	public function form ( string $key = null ) {
		if ( isset($key) ) {
			if ( isset($this->form[ $key ]) )
				return $this->form[ $key ];
			else return false;
		}

		return $this->form;
	}

	public function data () {
		if ( $this->form('data') )
			ShoppOrder()->data = $this->form('data');
	}

	public function customer () {

		$Customer = ShoppOrder()->Customer;

		// Update guest checkout flag
		$guest = false;
		if ( str_true($this->form('guest')) ) $guest = true;
		$Customer->guest = apply_filters('shopp_guest_checkout', $guest);

		$updates = array(
			'firstname' => $this->form('firstname'),
			'lastname' => $this->form('lastname'),
			'company' => $this->form('company'),
			'phone' => $this->form('phone'),
			'info' => $this->form('info')
		);

		// Remove invalid characters from the phone number
		$updates['phone'] = preg_replace('/[^\d\(\)\-+\. (ext|x)]/','', $updates['phone'] );

		if ( empty($Customer) ) $Customer = new Customer();
		else $Customer->reset();

		$Customer->updates($updates);

		// Keep confirm-password field value when showing checkout validation errors
		$confirmpass = $this->form('confirm-password');
		if ( ! empty($confirmpass) )
			$Customer->_confirm_password = $confirmpass;

	}

	public function shipaddress () {

		$Cart = ShoppOrder()->Cart;
		$ShippingAddress = ShoppOrder()->Shipping;
		$Shiprates = ShoppOrder()->Shiprates;

		if ( ! $Cart->shipped() ) // Use blank shipping for non-Shipped orders
			return $ShippingAddress = new ShippingAddress();

		if ( empty($ShippingAddress) )
			$ShippingAddress = new ShippingAddress();

		$form = $this->form('shipped');

		if ( ! empty($form) ) $ShippingAddress->updates($form);

		$copy = strtolower( $this->form('sameaddress') );
		if ( 'billing' == $copy ) {
			ShoppOrder()->sameaddress = $copy;
			ShoppOrder()->Billing->updates($form);
		}

		if ( $Cart->shipped() ) do_action('shopp_update_destination');

	}

	/**
	 * Processes changes to the shipping method
	 *
	 * Handles changes to the shipping method outside of other
	 * checkout processes
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	public function shipmethod () {
		$Shiprates = ShoppOrder()->Shiprates;
		$ShippingAddress = ShoppOrder()->Shipping;

		if ( $Shiprates->disabled() ) return;

		if ( empty($ShippingAddress) )
			$ShippingAddress = new ShippingAddress();

		$selection = $this->form('shipmethod');
		if ( $selection == $Shiprates->selected()->slug ) return;

		// Verify shipping method exists first
		if ( ! $Shiprates->exists($selection) ) return;

		$selected = $Shiprates->selected( $selection );

		$ShippingAddress->option = $selected->name;
		$ShippingAddress->method = $selected->slug;
	}


	public function billaddress () {
		$Cart = ShoppOrder()->Cart;
		$BillingAddress = ShoppOrder()->Billing;

		if ( empty($BillingAddress) )
			$BillingAddress = new BillingAddress();

		$form = $this->form('billing');

		// Prevent overwriting the card data when updating the BillingAddress
		$ignore = array();
		if ( ! empty($form['card']) && $form['card'] == substr($BillingAddress->card,-4) )
			$ignore[] = 'card';

		$BillingAddress->updates($_POST['billing'],$ignore);

		// Special case for updating/tracking billing locale
		if ( ! empty($form['locale']) )
			$BillingAddress->locale = $form['locale'];

		// Handle same address copying
		$copy = strtolower( $this->form('sameaddress') );
		if ( 'shipping' == $copy ) {
			ShoppOrder()->sameaddress = $copy;
			ShoppOrder()->Shipping->updates($form);
		}

		if ( ! $Cart->shipped() ) do_action('shopp_update_destination');

	}

	public function payment () {
		if ( ! $this->paycard() ) return;

		$Billing = ShoppOrder()->Billing;
		$Payments = ShoppOrder()->Payments;

		$form = $this->form('billing');

		// If the card number is provided over a secure connection
		// Change the cart to operate in secure mode
		if ( ! empty($form['card']) && is_ssl() )
			$Shopping->secured(true);

		// Sanitize the card number to ensure it only contains numbers
		if ( ! empty($form['card']) )
			$billing['card'] = preg_replace('/[^\d]/','',$billing['card']);

		// Change the cardtype to the selected payment service option label
		$Billing->cardtype = $Payments->selected()->label;

		$form['cardexpires'] = sprintf('%02d%02d', $form['cardexpires-mm'], $form['cardexpires-yy']);

		if ( ! empty($form['cardexpires-mm']) && ! empty($form['cardexpires-yy'])) {
			$exmm = preg_replace('/[^\d]/','',$form['cardexpires-mm']);
			$exyy = preg_replace('/[^\d]/','',$form['cardexpires-yy']);
			$Billing->cardexpires = mktime(0,0,0,$exmm,1,($exyy)+2000);
		} else $Billing->cardexpires = 0;

		$Billing->cvv = preg_replace('/[^\d]/', '', $form['cvv']);

		// Extra card security check fields
		if ( ! empty($form['xcsc']) ) {
			$Billing->xcsc = array();
			foreach ( (array)$form['xcsc'] as $field => $value ) {
				$Billing->Billing->xcsc[] = $field;	// Add to the XCSC registry of fields
				$Billing->$field = $value;			// Add the property
			}
		}

	}

	/**
	 * Determine if payment card data has been submitted
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return boolean
	 **/
	public function paycard () {
		$fields = array('card','cardexpires-mm','cardexpires-yy','cvv');
		$billing = $this->form('billing');
		foreach ( $fields as $field )
			if ( isset($billing[ $field ]) ) return true;
		return false;
	}


	/*
	 * Checkout form processing
	 *
	 * Handles taking user input from the checkout form and
	 * processing the information into useable order data
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	public function process () {
		$Shopping = ShoppShopping();
		$Payments = ShoppOrder()->Payments;
		$Cart = ShoppOrder()->Cart;

		$forcedconfirm = 'always' == shopp_setting('order_confirmation');

		$action = $this->form('checkout');

		if ( ! $action || 'process' != $action) return;

		$wasfree = $Cart->orderisfree();
		$estimated = $Cart->Totals->total();

		$Cart->totals();

		if ( $this->validate() !== true ) return;
		else ShoppOrder()->Customer->updates( $this->form() ); // Catch changes from validation

		// Catch originally free orders that get extra (shipping) costs added to them
		if ( $wasfree && ! $Cart->orderisfree() && $Payments->count() > 1 && ! empty($Payments->selected()->cards) ) {
			shopp_add_error( __('The order amount changed and requires that you select a payment method.','Shopp') );
			shopp_redirect( shoppurl(false,'checkout',$this->security()) );
		}

		// If using shopp_checkout_processed for a payment gateway redirect action
		// be sure to include a ShoppOrder()->Cart->orderisfree() check first.
		do_action('shopp_checkout_processed');

		if ($Cart->orderisfree()) do_action('shopp_process_free_order');

		// If the cart's total changes at all, confirm the order
		if ( apply_filters('shopp_order_confirm_needed', $estimated != $Cart->Totals->total() || $forcedconfirm ) )
			shopp_redirect( shoppurl(false, 'confirm', ShoppOrder()->security()) );
		else do_action('shopp_process_order');
	}

	/**
	 * Confirms the order and starts order processing
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	public function confirmed () {
		$action = $this->form('checkout');
		if ( 'confirmed' == $action ) {
			$this->confirmed = true;
			do_action('shopp_process_order');
		}
	}

	/**
	 * Validate the checkout form data before processing the order
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return boolean Status of valid checkout data
	 **/
	public function validate () {
		$Customer = ShoppOrder()->Customer;
		$Payments = ShoppOrder()->Payments;

		if ( apply_filters('shopp_firstname_required', empty($_POST['firstname'])) )
			return shopp_add_error(__('You must provide your first name.','Shopp'));

		if ( apply_filters('shopp_lastname_required', empty($_POST['lastname'])) )
			return shopp_add_error(__('You must provide your last name.','Shopp'));

		if ( apply_filters('shopp_email_valid', ! preg_match('!^' . self::RFC822_EMAIL . '$!', $_POST['email'])) )
			return shopp_add_error(__('You must provide a valid e-mail address.','Shopp'));

		if ( apply_filters(' shopp_clickwrap_required', isset($_POST['data']['clickwrap']) && 'agreed' !== $_POST['data']['clickwrap']) )
			return shopp_add_error(__('You must agree to the terms of sale.','Shopp'));

		if ( 'wordpress' == shopp_setting('account_system') && ! $Customer->logged_in() ) {
			require ABSPATH . '/wp-includes/registration.php';

			// Validate possible wp account names for availability
			if( isset($_POST['loginname']) ) {
				if( apply_filters('shopp_login_exists', username_exists($_POST['loginname'])) )
					return shopp_add_error(__('The login name you provided is not available.  Try logging in if you have previously created an account.'));
			} else { // need to find a usuable login
				list($handle, $domain) = explode('@', $_POST['email']);
				if(!username_exists($handle)) $_POST['loginname'] = $handle;

				$handle = $_POST['firstname'] . substr($_POST['lastname'], 0, 1);
				if( ! isset($_POST['loginname']) && ! username_exists($handle)) $_POST['loginname'] = $handle;

				$handle = substr($_POST['firstname'],0,1).$_POST['lastname'];
				if( ! isset($_POST['loginname']) && ! username_exists($handle)) $_POST['loginname'] = $handle;

				$handle .= rand(1000,9999);
				if( ! isset($_POST['loginname']) && ! username_exists($handle)) $_POST['loginname'] = $handle;

				if( apply_filters('shopp_login_required',!isset($_POST['loginname'])) )
					return shopp_add_error(__('A login is not available for creation with the information you provided. Please try a different email address or name, or try logging in if you previously created an account.'));
			}
			shopp_debug('Login set to '. $_POST['loginname'] . ' for WordPress account creation.');
			$ExistingCustomer = new Customer($_POST['email'], 'email');
			if ( $Customer->guest && ! empty($ExistingCustomer->id) ) $Customer->id = $ExistingCustomer->id;
			if ( apply_filters('shopp_email_exists', ! $Customer->guest && (email_exists($_POST['email']) || ! empty($ExistingCustomer->id))) )
				return shopp_add_error(__('The email address you entered is already in use. Try logging in if you previously created an account, or enter another email address to create your new account.','Shopp'));
		} elseif ('shopp' == shopp_setting('account_system') && ! $Customer->logged_in()) {
			$ExistingCustomer = new Customer($_POST['email'],'email');
			if ( apply_filters('shopp_email_exists', ! empty($ExistingCustomer->id)) )
				return shopp_add_error(__('The email address you entered is already in use. Try logging in if you previously created an account, or enter another email address to create a new account.','Shopp'));
		}

		// Validate WP account
		if ( apply_filters('shopp_login_required', (isset($_POST['loginname']) && empty($_POST['loginname']))) )
			return shopp_add_error(__('You must enter a login name for your account.','Shopp'));

		if ( isset($_POST['loginname']) ) {
			require ABSPATH . '/wp-includes/registration.php';
			if ( apply_filters('shopp_login_valid', ( ! validate_username($_POST['loginname']))) ) {
				$sanitized = sanitize_user( $_POST['loginname'], true );
				$illegal = array_diff( str_split($_POST['loginname']), str_split($sanitized) );
				return shopp_add_error( sprintf(__('The login name provided includes invalid characters: %s','Shopp'), esc_html(join(' ',$illegal))) );
			}

			if ( apply_filters('shopp_login_exists', username_exists($_POST['loginname'])) )
				return shopp_add_error(__('The login name is already in use. Try logging in if you previously created that account, or enter another login name for your new account.','Shopp'));
		}

		if ( isset($_POST['password']) ) {
			if ( apply_filters('shopp_passwords_required', (empty($_POST['password']) || empty($_POST['confirm-password']))) )
				return shopp_add_error(__('You must provide a password for your account and confirm it to ensure correct spelling.','Shopp'));
			if ( apply_filters('shopp_password_mismatch', ($_POST['password'] != $_POST['confirm-password'])) ) {
				$_POST['password'] = ''; $_POST['confirm-password'] = '';
				return shopp_add_error(__('The passwords you entered do not match. Please re-enter your passwords.','Shopp'));
			}
		}

		$BillingAddress = ShoppOrder()->Billing;
		if ( apply_filters('shopp_billing_address_required', (empty($BillingAddress->address) || strlen($BillingAddress->address) < 4)) )
			return shopp_add_error(__('You must enter a valid street address for your billing information.','Shopp'));

		if ( apply_filters('shopp_billing_postcode_required',empty($BillingAddress->postcode)) )
			return shopp_add_error(__('You must enter a valid postal code for your billing information.','Shopp'));

		if ( apply_filters('shopp_billing_country_required',empty($BillingAddress->country)))
			return shopp_add_error(__('You must select a country for your billing information.','Shopp'),'cart_validation');

		if ( apply_filters('shopp_billing_locale_required',isset($_POST['billing']['locale']) && empty($_POST['billing']['locale'])))
			return shopp_add_error(__('You must select a local jursidiction for tax purposes.','Shopp'));

		// Skip validating payment details for purchases not requiring a
		// payment (credit) card including free orders, remote checkout systems, etc
		if ( ! $this->paycard() ) return apply_filters('shopp_validate_checkout', true);

		if ( apply_filters('shopp_billing_card_required',empty($_POST['billing']['card'])) )
			return shopp_add_error(__('You did not provide a credit card number.','Shopp'));

		if ( apply_filters('shopp_billing_cardtype_required',empty($_POST['billing']['cardtype'])) )
			return shopp_add_error(__('You did not select a credit card type.','Shopp'));

		$card = Lookup::paycard(strtolower($_POST['billing']['cardtype']));
		if ( ! $card ) return apply_filters('shopp_validate_checkout', true);
		if ( apply_filters('shopp_billing_valid_card',!$card->validate($_POST['billing']['card'])))
			return shopp_add_error(__('The credit card number you provided is invalid.','Shopp'));

		if ( apply_filters('shopp_billing_cardexpires_month_required',empty($_POST['billing']['cardexpires-mm'])) )
			return shopp_add_error(__('You did not enter the month the credit card expires.','Shopp'));

		if ( apply_filters('shopp_billing_cardexpires_year_required',empty($_POST['billing']['cardexpires-yy'])) )
			return shopp_add_error(__('You did not enter the year the credit card expires.','Shopp'));

		if ( apply_filters('shopp_billing_card_expired',(!empty($_POST['billing']['cardexpires-mm']) && !empty($_POST['billing']['cardexpires-yy'])))
		 	&& $_POST['billing']['cardexpires-mm'] < date('n') && $_POST['billing']['cardexpires-yy'] <= date('y') )
			return shopp_add_error(__('The credit card expiration date you provided has already expired.','Shopp'));

		if ( apply_filters('shopp_billing_cardholder_required',strlen($_POST['billing']['cardholder']) < 2) )
			return shopp_add_error(__('You did not enter the name on the credit card you provided.','Shopp'));

		if ( apply_filters('shopp_billing_cvv_required',strlen($_POST['billing']['cvv']) < 3) )
			return shopp_add_error(__('You did not enter a valid security ID for the card you provided. The security ID is a 3 or 4 digit number found on the back of the credit card.','Shopp'));

		return apply_filters('shopp_validate_checkout', true);
	}

	const RFC822_EMAIL = '([^\\x00-\\x20\\x22\\x28\\x29\\x2c\\x2e\\x3a-\\x3c\\x3e\\x40\\x5b-\\x5d\\x7f-\\xff]+|\\x22([^\\x0d\\x22\\x5c\\x80-\\xff]|\\x5c[\\x00-\\x7f])*\\x22)(\\x2e([^\\x00-\\x20\\x22\\x28\\x29\\x2c\\x2e\\x3a-\\x3c\\x3e\\x40\\x5b-\\x5d\\x7f-\\xff]+|\\x22([^\\x0d\\x22\\x5c\\x80-\\xff]|\\x5c[\\x00-\\x7f])*\\x22))*\\x40([^\\x00-\\x20\\x22\\x28\\x29\\x2c\\x2e\\x3a-\\x3c\\x3e\\x40\\x5b-\\x5d\\x7f-\\xff]+|\\x5b([^\\x0d\\x5b-\\x5d\\x80-\\xff]|\\x5c[\\x00-\\x7f])*\\x5d)(\\x2e([^\\x00-\\x20\\x22\\x28\\x29\\x2c\\x2e\\x3a-\\x3c\\x3e\\x40\\x5b-\\x5d\\x7f-\\xff]+|\\x5b([^\\x0d\\x5b-\\x5d\\x80-\\xff]|\\x5c[\\x00-\\x7f])*\\x5d))*';

}