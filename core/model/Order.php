<?php
/**
 * Order
 *
 * Order data container and middleware object
 *
 * @author Jonathan Davis
 * @version 1.0.1
 * @copyright Ingenesis Limited, January 12, 2010
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @subpackage transact
 **/

/**
 * Order
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package transact
 **/
class Order {

	var $Customer = false;			// The current customer
	var $Shipping = false;			// The shipping address
	var $Billing = false;			// The billing address
	var $Cart = false;				// The shopping cart
	var $data = array();			// Extra/custom order data
	var $payoptions = array();		// List of payment method options
	var $paycards = array();		// List of accepted PayCards
	var $sameship = NULL;			// Flag for using the billing address for shipping

	var $processor = false;			// The payment processor module name
	var $paymethod = false;			// The selected payment method

	// Post processing properties
	var $purchase = false;			// Generated purchase ID
	var $gateway = false;			// Proper name of the gateway used to process the order
	var $txnid = false;				// The transaction ID reported by the gateway
	var $txnstatus = "PENDING";		// Status of the payment

	// Processing control properties
	var $confirm = false;			// Flag to confirm order or not
	var $confirmed = false;			// Confirmed by the shopper for processing
	var $accounts = false;			// Account system setting
	var $validated = false;			// The pre-processing order validation flag

	/**
	 * Order constructor
	 *
	 * @author Jonathan Davis
	 *
	 * @return void
	 **/
	function __construct () {
		$this->Cart = new Cart();
		$this->Customer = new Customer();
		$this->Billing = new Billing();
		$this->Shipping = new Shipping();

		$this->Shipping->destination();

		$this->created = mktime();

		$this->listeners();
	}

	/**
	 * Re-establish event listeners and discover the current gateway processor
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	function __wakeup () {
		$this->listeners();
	}

	/**
	 * Establish event listeners
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	function listeners () {
		$Settings =& ShoppSettings();
		$this->confirm = ($Settings->get('order_confirmation') == "always");
		$this->accounts = $Settings->get('account_system');
		$this->validated = false; // Reset the order validation flag

		add_action('shopp_process_shipmethod', array(&$this,'shipmethod'));
		add_action('shopp_process_checkout', array(&$this,'checkout'));
		add_action('shopp_confirm_order', array(&$this,'confirmed'));
		add_action('shopp_process_order', array(&$this,'validate'),7);

		add_action('shopp_process_free_order',array(&$this,'freebie'));
		add_action('shopp_update_destination',array(&$this->Shipping,'destination'));
		add_action('shopp_create_purchase',array(&$this,'purchase'));
		add_action('shopp_order_notifications',array(&$this,'notify'));

		// Schedule for the absolute last action to be run
		add_action('shopp_order_success',array(&$this,'success'),100);

		add_action('shopp_resession',array(&$this->Cart,'clear'));
		add_action('shopp_resession',array(&$this,'clear'));

		// Collect available payment methods from active gateways
		// Schedule for after the gateways are loaded  (priority 20)
		add_action('shopp_init',array(&$this,'payoptions'),20);

		// Select the default gateway processor
		// Schedule for after the gateways are loaded (priority 20)
		add_action('shopp_init',array(&$this,'processor'),20);

		// Set locking timeout for concurrency operation protection
		if (!defined('SHOPP_TXNLOCK_TIMEOUT')) define('SHOPP_TXNLOCK_TIMEOUT',10);

	}

	function unhook () {
		remove_action('shopp_create_purchase',array(&$this,'purchase'));
		remove_action('shopp_order_notifications',array(&$this,'notify'));
		remove_action('shopp_order_success',array(&$this,'success'));
		remove_action('shopp_process_order', array(&$this,'validate'),7);

		remove_class_actions(array(
			'shopp_create_purchase',
			'shopp_order_notifications',
			'shopp_order_success',
			'shopp_process_order'
			),'GatewayFramework');

	}

	function __destruct() {
		$this->unhook();
	}

	/**
	 * Builds a list of payment method options
	 *
	 * @author Jonathan Davis
	 * @since 1.1.5
	 *
	 * @return void
	 **/
	function payoptions () {
		global $Shopp;
		$accepted = array();

		$options = array();
		foreach ($Shopp->Gateways->active as $gateway) {
			$labels = is_array($gateway->settings['label'])?$gateway->settings['label']:array($gateway->settings['label']);
			$accepted = array_merge($accepted,$gateway->cards());
			foreach ($labels as $method) {
				$_ = new StdClass();
				$_->label = $method;
				$_->processor = $gateway->module;
				$_->cards = array_keys($gateway->cards());
				$handle = sanitize_title_with_dashes($method);
				$options[$handle] = $_;
			}
		}
		$this->paycards = $accepted;
		$this->payoptions = $options;
	}

	/**
	 * Set or get the currently selected gateway processor
	 *
	 * @author Jonathan Davis, John Dillick
	 * @since 1.1
	 *
	 * @return Object|false The currently selected gateway
	 **/
	function processor ($processor=false) {
		global $Shopp;

		// Set the gateway processor from a selected payment method
		if (isset($_POST['paymethod'])) {
			$processor = false;
			if (isset($this->payoptions[$_POST['paymethod']])) {
				$this->paymethod = $_POST['paymethod'];
				$processor = $this->payoptions[$this->paymethod]->processor;
				if (in_array($processor,$Shopp->Gateways->activated)) {
					$this->processor = $processor;
					$this->_paymethod_selected = true;
					 // Prevent unnecessary reprocessing on subsequent calls
					unset($_POST['paymethod']);
				}
			}
			if (!$processor) new ShoppError(__("The payment method you selected is no longer available. Please choose another.","Shopp"));
		}

		if (count($Shopp->Gateways->activated) == 1 // base case
			|| (!$this->processor && !$processor && count($Shopp->Gateways->activated) > 1)) {
			// Automatically select the first active gateway
			reset($Shopp->Gateways->activated);
			$module = current($Shopp->Gateways->activated);
			if ($this->processor != $module)
				$this->processor = $module;
		} elseif (!empty($processor)) { // Change the current processor
			if ($this->processor != $processor && in_array($processor,$Shopp->Gateways->activated))
				$this->processor = $processor;
		}

		if (isset($Shopp->Gateways->active[$this->processor])) {
			$Gateway = $Shopp->Gateways->active[$this->processor];
			$this->gateway = $Gateway->name;

			// Set the paymethod if not set already, or if it has changed
			$gateway_label = is_array($Gateway->settings['label'])?
				$Gateway->settings['label']:array($Gateway->settings['label']);

			if (!$this->paymethod)
				$this->paymethod = sanitize_title_with_dashes($gateway_label[0]);

		}

		return $this->processor;
	}

	/**
	 * Determine if payment card data has been submitted
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return boolean
	 **/
	function ccpayment () {
		$ccdata = array('card','cardexpires-mm','cardexpires-yy','cvv');
		foreach ($ccdata as $field)
			if (isset($_POST['billing'][$field])) return true;
		return false;
	}

	/**
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
	function checkout () {
		global $Shopp;
		if (!isset($_POST['checkout'])) return;
		if ($_POST['checkout'] != "process") return;

		$_POST = stripslashes_deep($_POST);

		$cc = $this->ccpayment();

		if ($cc) {
			$_POST['billing']['cardexpires'] = sprintf("%02d%02d",$_POST['billing']['cardexpires-mm'],$_POST['billing']['cardexpires-yy']);

			// If the card number is provided over a secure connection
			// Change the cart to operate in secure mode
			if (!empty($_POST['billing']['card']) && is_shopp_secure())
				$Shopp->Shopping->secured(true);

			// Sanitize the card number to ensure it only contains numbers
			if (!empty($_POST['billing']['card']))
				$_POST['billing']['card'] = preg_replace('/[^\d]/','',$_POST['billing']['card']);

		}

		// Remove invlalid characters from the phone number
		$_POST['phone'] = preg_replace('/[^\d\(\)\-+\. (ext|x)]/','',$_POST['phone']);

		if (isset($_POST['data'])) $this->data = $_POST['data'];
		if (isset($_POST['info'])) $this->Customer->info = $_POST['info'];

		if (empty($this->Customer))
			$this->Customer = new Customer();

		$this->Customer->updates($_POST);

		// Keep confirm-password field value when showing checkout validation errors
		if (isset($_POST['confirm-password']))
			$this->Customer->_confirm_password = $_POST['confirm-password'];

		if (empty($this->Billing))
			$this->Billing = new Billing();
		// Default the cardtype to the payment method label selected
		$this->Billing->cardtype = $this->payoptions[$this->paymethod]->label;
		$this->Billing->updates($_POST['billing']);

		// Special case for updating/tracking billing locale
		if (!empty($_POST['billing']['locale']))
			$this->Billing->locale = $_POST['billing']['locale'];

		if ($cc) {
			if (!empty($_POST['billing']['cardexpires-mm']) && !empty($_POST['billing']['cardexpires-yy'])) {
				$exmm = preg_replace('/[^\d]/','',$_POST['billing']['cardexpires-mm']);
				$exyy = preg_replace('/[^\d]/','',$_POST['billing']['cardexpires-yy']);
				$this->Billing->cardexpires = mktime(0,0,0,$exmm,1,($exyy)+2000);
			} else $this->Billing->cardexpires = 0;

			$this->Billing->cvv = preg_replace('/[^\d]/','',$_POST['billing']['cvv']);
			if (!empty($_POST['billing']['xcsc'])) {
				foreach ($_POST['billing']['xcsc'] as $field => $value)
					$this->Billing->{$field} = $value;
			}
		}

		if (!empty($this->Cart->shipped)) {
			if (empty($this->Shipping))
				$this->Shipping = new Shipping();

			if (isset($_POST['shipping'])) $this->Shipping->updates($_POST['shipping']);
			if (!empty($_POST['shipmethod'])) $this->Shipping->method = $_POST['shipmethod'];
			else $this->Shipping->method = key($this->Cart->shipping);

			// Override posted shipping updates with billing address
			if ( isset($_POST['sameshipaddress']) ) {
				if ( $this->sameship = ( "on" == $_POST['sameshipaddress'] ) ) {
					$this->Shipping->updates($this->Billing,
						array("_datatypes","_table","_key","_lists","id","created","modified"));
				}
			}
		} else $this->Shipping = new Shipping(); // Use blank shipping for non-Shipped orders

		$freebie = $this->Cart->orderisfree();
		$estimated = $this->Cart->Totals->total;

		$this->Cart->changed(true);
		$this->Cart->totals();
		if ($this->validform() !== true) return;
		else $this->Customer->updates($_POST); // Catch changes from validation

		if (apply_filters('shopp_process_free_order',$this->Cart->orderisfree())) return;

		// Catch originally free orders that get extra (shipping) costs added to them
		if ($freebie && $this->Cart->Totals->total > 0) {

			if ( ! (count($this->payoptions) == 1 // One paymethod
					&& ( isset($this->payoptions[$this->paymethod]->cards) // Remote checkout
						&& empty( $this->payoptions[$this->paymethod]->cards ) ) )
				) {
				new ShoppError(__('Payment information for this order is missing.','Shopp'),'checkout_no_paymethod',SHOPP_ERR);
				shopp_redirect( shoppurl(false,'checkout',$this->security()) );
			}
		}
		do_action('shopp_checkout_processed');

		// If the cart's total changes at all, confirm the order
		if ($estimated != $this->Cart->Totals->total || $this->confirm)
			shopp_redirect( shoppurl(false,'confirm-order',$this->security()) );
		else do_action('shopp_process_order');

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
	 * @return void Description...
	 **/
	function shipmethod () {
		if (empty($this->Cart->shipped)) return;
		if (empty($this->Shipping))
				$this->Shipping = new Shipping();

		if ($_POST['shipmethod'] == $this->Shipping->method) return;

		$this->Shipping->method = $_POST['shipmethod'];
		$this->Cart->retotal = true;
		$this->Cart->totals();
	}

	/**
	 * Confirms the order and starts order processing
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	function confirmed () {

		if ($_POST['checkout'] == "confirmed") {
			$this->confirmed = true;
			do_action('shopp_process_order');
		}

	}

	/**
	 * Handles processing free orders, overriding any configured gateways
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	function freebie ($free) {
		if (!$free) return $free;

		$this->gateway = $this->Billing->cardtype = __('Free Order','Shopp');
		$this->transaction(crc32($this->Customer->email.mktime()),'CHARGED');
		return true;
	}

	/**
	 * Generates a Purchase record from the order
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	function purchase () {
		global $Shopp;

		// Need a transaction ID to create a purchase
		if (empty($this->txnid)) return false;

		// Lock for concurrency protection
		$this->lock();

		$Purchase = new Purchase($this->txnid,'txnid');
		if (!empty($Purchase->id)) {
			$this->unlock();
			$Shopp->resession();

			$this->purchase = $Purchase->id;
			if ($this->purchase !== false)
				shopp_redirect(shoppurl(false,'thanks'));

		}

		// WordPress account integration used, customer has no wp user
		if ("wordpress" == $this->accounts && empty($this->Customer->wpuser)) {
			if ( $wpuser = get_current_user_id() ) $this->Customer->wpuser = $wpuser; // use logged in WordPress account
			else $this->Customer->create_wpuser(); // not logged in, create new account
		}

		// New customer, save hashed password
		if (!$this->Customer->exists()) {
			$this->Customer->id = false;
			if (SHOPP_DEBUG) new ShoppError('Creating new Shopp customer record','new_customer',SHOPP_DEBUG_ERR);
			if (empty($this->Customer->password)) $this->Customer->password = wp_generate_password(12,true);
			if ("shopp" == $this->accounts) $this->Customer->notification();
			$this->Customer->password = wp_hash_password($this->Customer->password);
		} else unset($this->Customer->password); // Existing customer, do not overwrite password field!

		$this->Customer->save();

		$this->Billing->customer = $this->Customer->id;
		$this->Billing->card = substr($this->Billing->card,-4);
		$paycard = Lookup::paycard($this->Billing->cardtype);
		$this->Billing->cardtype = !$paycard?$this->Billing->cardtype:$paycard->name;
		$this->Billing->cvv = false;
		$this->Billing->save();

		// Card data is truncated, switch the cart to normal mode
		$Shopp->Shopping->secured(false);

		if (!empty($this->Shipping->address)) {
			$this->Shipping->customer = $this->Customer->id;
			$this->Shipping->save();
		}

		$base = $Shopp->Settings->get('base_operations');

		$promos = array();
		foreach ($this->Cart->discounts as &$promo) {
			$promos[$promo->id] = $promo->name;
			$promo->uses++;
		}

		$Purchase = new Purchase();
		$Purchase->copydata($this);
		$Purchase->copydata($this->Customer);
		$Purchase->copydata($this->Billing);
		$Purchase->copydata($this->Shipping,'ship');
		$Purchase->copydata($this->Cart->Totals);
		$Purchase->customer = $this->Customer->id;
		$Purchase->billing = $this->Billing->id;
		$Purchase->shipping = $this->Shipping->id;
		$Purchase->taxing = ($base['vat'])?'inclusive':'exclusive';
		$Purchase->promos = $promos;
		$Purchase->freight = $this->Cart->Totals->shipping;
		$Purchase->ip = $Shopp->Shopping->ip;
		$Purchase->save();
		$this->unlock();
		Promotion::used(array_keys($promos));

		foreach($this->Cart->contents as $Item) {
			$Purchased = new Purchased();
			$Purchased->copydata($Item);
			$Purchased->price = $Item->option->id;
			$Purchased->purchase = $Purchase->id;
			if (!empty($Purchased->download)) $Purchased->keygen();
			$Purchased->save();
			if ($Item->inventory) $Item->unstock();
		}

		$this->purchase = $Purchase->id;
		$Shopp->Purchase = &$Purchase;

		if (SHOPP_DEBUG) new ShoppError('Purchase '.$Purchase->id.' was successfully saved to the database.',false,SHOPP_DEBUG_ERR);

		do_action('shopp_order_notifications');

		do_action_ref_array('shopp_order_success',array(&$Shopp->Purchase));
	}

	/**
	 * Create a lock for transaction processing
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return boolean
	 **/
	function lock () {
		if (empty($this->txnid)) return false;
		$db = DB::get();

		$r = new StdClass();
		$r->locked = 0;
		for ($attempts = 0; $attempts < 3 && $r->locked == 0; $attempts++)
			$r = $db->query("SELECT GET_LOCK('$this->txnid',".SHOPP_TXNLOCK_TIMEOUT.") AS locked");

		if ($r->locked == 1) return true;

		new ShoppError(sprintf(__('Transaction %s failed. Could not acheive a transaction lock.','Shopp'),$this->txnid),'order_txn_lock',SHOPP_TRXN_ERR);
		shopp_redirect( shoppurl(false,'checkout',$this->security()) );
	}

	/**
	 * Unlocks a transaction lock
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return boolean
	 **/
	function unlock () {
		$db = DB::get();
		if (empty($this->txnid)) return false;

		$r = $db->query("SELECT RELEASE_LOCK('$this->txnid') as unlocked");
		return ($r->unlocked == 1)?true:false;
	}

	/**
	 * Send out new order notifications
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	function notify () {
		global $Shopp;
		$Purchase = $Shopp->Purchase;

		// Send email notifications
		// notification(addressee name, email, subject, email template, receipt template)
		$Purchase->notification(
			"$Purchase->firstname $Purchase->lastname",
			$Purchase->email,
			__('Order Receipt','Shopp')
		);

		if ($Shopp->Settings->get('receipt_copy') != 1) return;
		$Purchase->notification(
			'',
			$Shopp->Settings->get('merchant_email'),
			__('New Order','Shopp')
		);
	}

	/**
	 * Sets transaction information to create the purchase record
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param string $id Transaction ID
	 * @param string $status (optional) Transaction status (PENDING, CHARGED, VOID, etc)
	 * @param float $fees (optional) Transaction fees assesed by the processor
	 *
	 * @return true
	 **/
	function transaction ($id,$status="PENDING",$fees=0) {
		$this->txnid = $id;
		$this->txnstatus = $status;
		$this->fees = $fees;

		if (empty($this->txnid)) return new ShoppError(sprintf('The payment gateway %s did not provide a transaction id. Purchase records cannot be created without a transaction id.',$this->gateway),'shopp_order_transaction',SHOPP_DEBUG_ERR);

		$Purchase = new Purchase($this->txnid,'txnid');
		if (!empty($Purchase->id)) {
			if($status != $Purchase->txnstatus) {
				do_action_ref_array('shopp_order_txnstatus_update',array(&$status,&$Purchase));
				$Purchase->txnstatus = $status;
				$Purchase->save();
			}
		} else do_action('shopp_create_purchase');

		return true;
	}

	/**
	 * Resets the session and redirects to the thank you page
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	function success () {
		global $Shopp;

		$Shopp->resession();

		if ($this->purchase !== false)
			shopp_redirect(shoppurl(false,'thanks'));
	}

	/**
	 * Validate the checkout form data before processing the order
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return boolean Status of valid checkout data
	 **/
	function validform () {

		if (apply_filters('shopp_firstname_required',empty($_POST['firstname'])))
			return new ShoppError(__('You must provide your first name.','Shopp'),'cart_validation');

		if (apply_filters('shopp_lastname_required',empty($_POST['lastname'])))
			return new ShoppError(__('You must provide your last name.','Shopp'),'cart_validation');

		$rfc822email =	'([^\\x00-\\x20\\x22\\x28\\x29\\x2c\\x2e\\x3a-\\x3c\\x3e\\x40\\x5b-\\x5d\\x7f-\\xff]+|\\x22([^\\x0d'.
						'\\x22\\x5c\\x80-\\xff]|\\x5c[\\x00-\\x7f])*\\x22)(\\x2e([^\\x00-\\x20\\x22\\x28\\x29\\x2c\\x2e'.
						'\\x3a-\\x3c\\x3e\\x40\\x5b-\\x5d\\x7f-\\xff]+|\\x22([^\\x0d\\x22\\x5c\\x80-\\xff]|\\x5c[\\x00-\\x7f])*'.
						'\\x22))*\\x40([^\\x00-\\x20\\x22\\x28\\x29\\x2c\\x2e\\x3a-\\x3c\\x3e\\x40\\x5b-\\x5d\\x7f-\\xff]+'.
						'|\\x5b([^\\x0d\\x5b-\\x5d\\x80-\\xff]|\\x5c[\\x00-\\x7f])*\\x5d)(\\x2e([^\\x00-\\x20\\x22\\x28'.
						'\\x29\\x2c\\x2e\\x3a-\\x3c\\x3e\\x40\\x5b-\\x5d\\x7f-\\xff]+|\\x5b([^\\x0d\\x5b-\\x5d\\x80-\\xff]'.
						'|\\x5c[\\x00-\\x7f])*\\x5d))*';
		if(apply_filters('shopp_email_valid',!preg_match("!^$rfc822email$!", $_POST['email'])))
			return new ShoppError(__('You must provide a valid e-mail address.','Shopp'),'cart_validation');

		if ($this->accounts == "wordpress" && !$this->Customer->login) {
			require_once(ABSPATH."/wp-includes/registration.php");

			// Validate possible wp account names for availability
			if(isset($_POST['loginname'])){
				if(apply_filters('shopp_login_exists',username_exists($_POST['loginname'])))
					return new ShoppError(__('The login name you provided is not available.  Try logging in if you have previously created an account.'), 'cart_validation');
			} else { // need to find a usuable login
				list($handle,$domain) = explode("@",$_POST['email']);
				if(!username_exists($handle)) $_POST['loginname'] = $handle;

				$handle = $_POST['firstname'].substr($_POST['lastname'],0,1);
				if(!isset($_POST['loginname']) && !username_exists($handle)) $_POST['loginname'] = $handle;

				$handle = substr($_POST['firstname'],0,1).$_POST['lastname'];
				if(!isset($_POST['loginname']) && !username_exists($handle)) $_POST['loginname'] = $handle;

				$handle .= rand(1000,9999);
				if(!isset($_POST['loginname']) && !username_exists($handle)) $_POST['loginname'] = $handle;

				if(apply_filters('shopp_login_required',!isset($_POST['loginname'])))
					return new ShoppError(__('A login is not available for creation with the information you provided.  Please try a different email address or name, or try logging in if you previously created an account.'),'cart_validation');
			}
			if(SHOPP_DEBUG) new ShoppError('Login set to '. $_POST['loginname'] . ' for WordPress account creation.',false,SHOPP_DEBUG_ERR);
			$ExistingCustomer = new Customer($_POST['email'],'email');
			if (apply_filters('shopp_email_exists',(email_exists($_POST['email']) || !empty($ExistingCustomer->id))))
				return new ShoppError(__('The email address you entered is already in use. Try logging in if you previously created an account, or enter another email address to create your new account.','Shopp'),'cart_validation');
		} elseif ($this->accounts == "shopp"  && !$this->Customer->login) {
			$ExistingCustomer = new Customer($_POST['email'],'email');
			if (apply_filters('shopp_email_exists',!empty($ExistingCustomer->id)))
				return new ShoppError(__('The email address you entered is already in use. Try logging in if you previously created an account, or enter another email address to create a new account.','Shopp'),'cart_validation');
		}

		// Validate WP account
		if (apply_filters('shopp_login_required',(isset($_POST['loginname']) && empty($_POST['loginname']))))
			return new ShoppError(__('You must enter a login name for your account.','Shopp'),'cart_validation');

		if (isset($_POST['loginname'])) {
			require_once(ABSPATH."/wp-includes/registration.php");
			if (apply_filters('shopp_login_valid',(!validate_username($_POST['loginname']))))
				return new ShoppError(__('This login name is invalid because it uses illegal characters. Please enter a valid login name.','Shopp'),'cart_validation');

			if (apply_filters('shopp_login_exists',username_exists($_POST['loginname'])))
				return new ShoppError(__('The login name is already in use. Try logging in if you previously created that account, or enter another login name for your new account.','Shopp'),'cart_validation');
		}

		if (isset($_POST['password'])) {
			if (apply_filters('shopp_passwords_required',(empty($_POST['password']) || empty($_POST['confirm-password']))))
				return new ShoppError(__('You must provide a password for your account and confirm it to ensure correct spelling.','Shopp'),'cart_validation');
			if (apply_filters('shopp_password_mismatch',($_POST['password'] != $_POST['confirm-password']))) {
				$_POST['password'] = ""; $_POST['confirm-password'] = "";
				return new ShoppError(__('The passwords you entered do not match. Please re-enter your passwords.','Shopp'),'cart_validation');
			}
		}

		if (apply_filters('shopp_billing_address_required',(empty($_POST['billing']['address']) || strlen($_POST['billing']['address']) < 4)))
			return new ShoppError(__('You must enter a valid street address for your billing information.','Shopp'),'cart_validation');

		if (apply_filters('shopp_billing_postcode_required',empty($_POST['billing']['postcode'])))
			return new ShoppError(__('You must enter a valid postal code for your billing information.','Shopp'),'cart_validation');

		if (apply_filters('shopp_billing_country_required',empty($_POST['billing']['country'])))
			return new ShoppError(__('You must select a country for your billing information.','Shopp'),'cart_validation');

		// Skip validating payment details for purchases not requiring a
		// payment (credit) card including free orders, remote checkout systems, etc
		if (!$this->ccpayment()) return apply_filters('shopp_validate_checkout',true);

		if (apply_filters('shopp_billing_card_required',empty($_POST['billing']['card'])))
			return new ShoppError(__('You did not provide a credit card number.','Shopp'),'cart_validation');

		if (apply_filters('shopp_billing_cardtype_required',empty($_POST['billing']['cardtype'])))
			return new ShoppError(__('You did not select a credit card type.','Shopp'),'cart_validation');

		$card = Lookup::paycard(strtolower($_POST['billing']['cardtype']));
		if (!$card) return apply_filters('shopp_validate_checkout',true);
		if (apply_filters('shopp_billing_valid_card',!$card->validate($_POST['billing']['card'])))
			return new ShoppError(__('The credit card number you provided is invalid.','Shopp'),'cart_validation');

		if (apply_filters('shopp_billing_cardexpires_month_required',empty($_POST['billing']['cardexpires-mm'])))
			return new ShoppError(__('You did not enter the month the credit card expires.','Shopp'),'cart_validation');

		if (apply_filters('shopp_billing_cardexpires_year_required',empty($_POST['billing']['cardexpires-yy'])))
			return new ShoppError(__('You did not enter the year the credit card expires.','Shopp'),'cart_validation');

		if (apply_filters('shopp_billing_card_expired',(!empty($_POST['billing']['cardexpires-mm']) && !empty($_POST['billing']['cardexpires-yy'])))
		 	&& $_POST['billing']['cardexpires-mm'] < date('n') && $_POST['billing']['cardexpires-yy'] <= date('y'))
			return new ShoppError(__('The credit card expiration date you provided has already expired.','Shopp'),'cart_validation');

		if (apply_filters('shopp_billing_cardholder_required',strlen($_POST['billing']['cardholder']) < 2))
			return new ShoppError(__('You did not enter the name on the credit card you provided.','Shopp'),'cart_validation');

		if (apply_filters('shopp_billing_cvv_required',strlen($_POST['billing']['cvv']) < 3))
			return new ShoppError(__('You did not enter a valid security ID for the card you provided. The security ID is a 3 or 4 digit number found on the back of the credit card.','Shopp'),'cart_validation');

		return apply_filters('shopp_validate_checkout',true);
	}


	function validate () {
		if (apply_filters('shopp_valid_order',$this->isvalid())) return true;

		shopp_redirect( shoppurl(false,'checkout',$this->security()) );
	}

	/**
	 * Validate order data before transaction processing
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return boolean Validity of the order
	 **/
	function isvalid ($report=true) {
		global $Shopp;
		$Customer = $this->Customer;
		$Shipping = $this->Shipping;
		$Cart = $this->Cart;
		$errors = 0;
		$valid = true;

		if (SHOPP_DEBUG) new ShoppError('Validating order data for processing',false,SHOPP_DEBUG_ERR);

		if (empty($Cart->contents)) {
			$valid = apply_filters('shopp_ordering_empty_cart',false);
			new ShoppError(__("There are no items in the cart."),'invalid_order'.$errors++,($report?SHOPP_TRXN_ERR:SHOPP_DEBUG_ERR));
		}

		$stock = true;
		foreach ($Cart->contents as $item) {
			if (!$item->instock()){
				$valid = apply_filters('shopp_ordering_items_outofstock',false);
				new ShoppError(sprintf(__("%s does not have sufficient stock to process order."),
				$item->name . ($item->option->label?" ({$item->option->label})":"")),
				'invalid_order'.$errors++,($report?SHOPP_TRXN_ERR:SHOPP_DEBUG_ERR));
				$stock = false;
			}
		}

		$valid_customer = true;
		if (!$Customer) $valid_customer = apply_filters('shopp_ordering_empty_customer',false); // No Customer

		// Always require name and email
		if (empty($Customer->firstname)) $valid_customer = apply_filters('shopp_ordering_empty_firstname',false);
		if (empty($Customer->lastname)) $valid_customer = apply_filters('shopp_ordering_empty_lastname',false);
		if (empty($Customer->email)) $valid_customer = apply_filters('shopp_ordering_empty_email',false);

		if (!$valid_customer) {
			$valid = false;
			new ShoppError(__('There is not enough customer information to process the order.','Shopp'),'invalid_order'.$errors++,($report?SHOPP_TRXN_ERR:SHOPP_DEBUG_ERR));
		}

		// Check for shipped items but no Shipping information
		$valid_shipping = true;
		if (!empty($this->Cart->shipped) && !$this->Cart->noshipping) {
			if (empty($Shipping->address))
				$valid_shipping = apply_filters('shopp_ordering_empty_shipping_address',false);
			if (empty($Shipping->country))
				$valid_shipping = apply_filters('shopp_ordering_empty_shipping_country',false);
			if (empty($Shipping->postcode))
				$valid_shipping = apply_filters('shopp_ordering_empty_shipping_postcode',false);

			if ($Cart->freeshipping === false && !$Cart->Totals->shipping) {
				$valid = apply_filters('shopp_ordering_no_shipping_costs',false);
				$message = __('The order cannot be processed. No shipping costs could be determined. Either the shipping rate provider was unavailable, or may have rejected the shipping address you provided. Please return to %scheckout%s and try again.', 'Shopp');
				if (!$valid) new ShoppError( sprintf( $message,'<a href="'.shoppurl(false,'checkout',$this->security()).'">','</a>' ), 'invalid_order'.$errors++, ($report?SHOPP_TRXN_ERR:SHOPP_DEBUG_ERR)
				);
			}

		}
		if (!$valid_shipping) {
			$valid = false;
			new ShoppError(__('The shipping address information is incomplete. The order cannot be processed.','Shopp'),'invalid_order'.$errors++,($report?SHOPP_TRXN_ERR:SHOPP_DEBUG_ERR));
		}

		return $valid;
	}

	/**
	 * Evaluates if checkout process needs to be secured
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return boolean Whether the checkout form should be secured
	 **/
	function security () {
		global $Shopp;
		return $Shopp->Gateways->secure;
	}

	/**
	 * Clear order-specific information to prepare for a new order
	 *
	 * @author Jonathan Davis
	 * @since 1.1.5
	 *
	 * @return void
	 **/
	function clear () {
		$this->data = array();			// Custom order data
		$this->txnid = false;			// The transaction ID reported by the gateway
		$this->gateway = false;			// Proper name of the gateway used to process the order
		$this->txnstatus = "PENDING";	// Status of the payment
		$this->confirmed = false;		// Confirmed by the shopper for processing
	}

	/**
	 * Provides shopp('checkout') template API functionality
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @return mixed
	 **/
	function tag ($property,$options=array()) {
		global $Shopp,$wp;

		$pages = $Shopp->Settings->get('pages');
		$base = $Shopp->Settings->get('base_operations');
		$countries = $Shopp->Settings->get('target_markets');
		$process = get_query_var('shopp_proc');

		$select_attrs = array('title','required','class','disabled','required','size','tabindex','accesskey');
		$submit_attrs = array('title','class','value','disabled','tabindex','accesskey');

		if (!isset($options['mode'])) $options['mode'] = "input";

		switch ($property) {
			case "url":
				$link = shoppurl(false,'checkout',$this->security());

				// Pass any arguments along
				$args = $_GET;
				unset($args['page_id'],$args['acct']);
				$link = esc_url(add_query_arg($args,$link));
				if ($process == "confirm-order") $link = apply_filters('shopp_confirm_url',$link);
				else $link = apply_filters('shopp_checkout_url',$link);
				return $link;
				break;
			case "function":
				if (!isset($options['shipcalc'])) $options['shipcalc'] = '<img src="'.SHOPP_ADMIN_URI.'/icons/updating.gif" alt="'.__('Updating','Shopp').'" width="16" height="16" />';
				$regions = Lookup::country_zones();
				$base = $Shopp->Settings->get('base_operations');

				$js = "var regions = ".json_encode($regions).",".
									"SHIPCALC_STATUS = '".$options['shipcalc']."',".
									"d_pm = '".sanitize_title_with_dashes($this->paymethod)."',".
									"pm_cards = {};";

				foreach ($this->payoptions as $handle => $option) {
					if (empty($option->cards)) continue;
					$js .= "pm_cards['".$handle."'] = ".json_encode($option->cards).";";
				}
				add_storefrontjs($js,true);

				if (!empty($options['value'])) $value = $options['value'];
				else $value = "process";
				$output = '<div><input id="shopp-checkout-function" type="hidden" name="checkout" value="'.$value.'" /></div>';
				if ($value == "confirmed") $output = apply_filters('shopp_confirm_form',$output);
				else $output = apply_filters('shopp_checkout_form',$output);
				return $output;
				break;
			case "errors":
			case "error":
				$Errors = &ShoppErrors();
				if (!$Errors->exist(SHOPP_COMM_ERR)) return false;
				$errors = $Errors->get(SHOPP_COMM_ERR);
				$defaults = array(
					'before' => '<li>',
					'after' => '</li>'
				);
				$options = array_merge($defaults,$options);
				extract($options);

				$result = "";
				foreach ((array)$errors as $error)
					if (!$error->blank()) $result .= $before.$error->message(true).$after;
				return $result;
				break;
			case "cart-summary":
				ob_start();
				include(SHOPP_TEMPLATES."/summary.php");
				$content = ob_get_contents();
				ob_end_clean();

				// If inside the checkout form, strip the extra <form> tag so we don't break standards
				// This is ugly, but necessary given the different markup contexts the cart summary is used in
				$Storefront =& ShoppStorefront();
				if ($Storefront !== false && $Storefront->checkout)
					$content = preg_replace('/<\/?form.*?>/','',$content);

				return $content;
				break;
			case "loggedin": return $this->Customer->login; break;
			case "notloggedin": return (!$this->Customer->login && $Shopp->Settings->get('account_system') != "none"); break;
			case "email-login":  // Deprecating
			case "loginname-login":  // Deprecating
			case "account-login":
				if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
				if (!empty($_POST['account-login']))
					$options['value'] = $_POST['account-login'];
				return '<input type="text" name="account-login" id="account-login"'.inputattrs($options).' />';
				break;
			case "password-login":
				if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
				if (!empty($_POST['password-login']))
					$options['value'] = $_POST['password-login'];
				return '<input type="password" name="password-login" id="password-login" '.inputattrs($options).' />';
				break;
			case "submit-login": // Deprecating
			case "login-button":
				$string = '<input type="hidden" name="process-login" id="process-login" value="false" />';
				$string .= '<input type="submit" name="submit-login" id="submit-login" '.inputattrs($options).' />';
				return $string;
				break;
			case "firstname":
				if ($options['mode'] == "value") return $this->Customer->firstname;
				if (!empty($this->Customer->firstname))
					$options['value'] = $this->Customer->firstname;
				return '<input type="text" name="firstname" id="firstname" '.inputattrs($options).' />';
				break;
			case "lastname":
				if ($options['mode'] == "value") return $this->Customer->lastname;
				if (!empty($this->Customer->lastname))
					$options['value'] = $this->Customer->lastname;
				return '<input type="text" name="lastname" id="lastname" '.inputattrs($options).' />';
				break;
			case "email":
				if ($options['mode'] == "value") return $this->Customer->email;
				if (!empty($this->Customer->email))
					$options['value'] = $this->Customer->email;
				return '<input type="text" name="email" id="email" '.inputattrs($options).' />';
				break;
			case "loginname":
				if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
				if ($options['mode'] == "value") return $this->Customer->loginname;
				if (!empty($this->Customer->loginname))
					$options['value'] = $this->Customer->loginname;
				return '<input type="text" name="loginname" id="login" '.inputattrs($options).' />';
				break;
			case "password":
				if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
				if ($options['mode'] == "value")
					return strlen($this->Customer->password) == 34?str_pad('&bull;',8):$this->Customer->password;
				if (!empty($this->Customer->password))
					$options['value'] = $this->Customer->password;
				return '<input type="password" name="password" id="password" '.inputattrs($options).' />';
				break;
			case "confirm-password":
				if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
				if (!empty($this->Customer->_confirm_password))
					$options['value'] = $this->Customer->_confirm_password;
				return '<input type="password" name="confirm-password" id="confirm-password" '.inputattrs($options).' />';
				break;
			case "phone":
				if ($options['mode'] == "value") return $this->Customer->phone;
				if (!empty($this->Customer->phone))
					$options['value'] = $this->Customer->phone;
				return '<input type="text" name="phone" id="phone" '.inputattrs($options).' />';
				break;
			case "organization":
			case "company":
				if ($options['mode'] == "value") return $this->Customer->company;
				if (!empty($this->Customer->company))
					$options['value'] = $this->Customer->company;
				return '<input type="text" name="company" id="company" '.inputattrs($options).' />';
				break;
			case "marketing":
				if ($options['mode'] == "value") return $this->Customer->marketing;
				if (!empty($this->Customer->marketing))
					$options['value'] = $this->Customer->marketing;
				$attrs = array("accesskey","alt","checked","class","disabled","format",
					"minlength","maxlength","readonly","size","src","tabindex",
					"title");
				$input = '<input type="hidden" name="marketing" value="no" />';
				$input .= '<input type="checkbox" name="marketing" id="marketing" value="yes" '.inputattrs($options,$attrs).' />';
				return $input;
				break;
			case "customer-info":
				$defaults = array(
					'name' => false, // REQUIRED
					'info' => false,
					'mode' => false,
					'title' => '',
					'type' => 'hidden',
					'value' => '',
					'cols' => '30',
					'rows' => '3',
					'options' => ''
				);
				$op = array_merge($defaults,$options);
				extract($op);

				// Allowed input types
				$allowed_types = array("text","hidden","password","checkbox","radio","textarea","menu");

				// Input types that can override option-specified value with the loaded data value
				$value_override = array("text","hidden","password","textarea","menu");

				/// Allowable attributes for textarea inputs
				$textarea_attrs = array('accesskey','title','tabindex','class','disabled','required');

				if (!$name) { // Iterator for order data
					if (!isset($this->_customer_info_loop)) {
						reset($this->Customer->info->named);
						$this->_customer_info_loop = true;
					} else next($this->Customer->info->named);

					if (current($this->Customer->info->named) !== false) return true;
					else {
						unset($this->_customer_info_loop);
						return false;
					}
				}

				if (isset($this->Customer->info->named[$name])) $info = $this->Customer->info->named[$name];
				if ($name && $mode == "value") return $info;

				if (!in_array($type,$allowed_types)) $type = 'hidden';
				if (empty($title)) $title = $name;
				$id = 'customer-info-'.sanitize_title_with_dashes($name);

				if (in_array($type,$value_override) && !empty($info))
					$value = $info;
				switch (strtolower($type)) {
					case "textarea":
						return '<textarea name="info['.$name.']" cols="'.$cols.'" rows="'.$rows.'" id="'.$id.'" '.inputattrs($op,$textarea_attrs).'>'.$value.'</textarea>';
						break;
					case "menu":
						if (is_string($options)) $options = explode(',',$options);
						return '<select name="info['.$name.']" id="'.$id.'" '.inputattrs($op,$select_attrs).'>'.menuoptions($options,$value).'</select>';
						break;
					default:
						return '<input type="'.$type.'" name="info['.$name.']" id="'.$id.'" '.inputattrs($op).' />';
						break;
				}
				break;

			// SHIPPING TAGS
			case "shipping": return (!empty($this->shipped)); break;
			case "shipping-address":
				if ($options['mode'] == "value") return $this->Shipping->address;
				if (!empty($this->Shipping->address))
					$options['value'] = $this->Shipping->address;
				return '<input type="text" name="shipping[address]" id="shipping-address" '.inputattrs($options).' />';
				break;
			case "shipping-xaddress":
				if ($options['mode'] == "value") return $this->Shipping->xaddress;
				if (!empty($this->Shipping->xaddress))
					$options['value'] = $this->Shipping->xaddress;
				return '<input type="text" name="shipping[xaddress]" id="shipping-xaddress" '.inputattrs($options).' />';
				break;
			case "shipping-city":
				if ($options['mode'] == "value") return $this->Shipping->city;
				if (!empty($this->Shipping->city))
					$options['value'] = $this->Shipping->city;
				return '<input type="text" name="shipping[city]" id="shipping-city" '.inputattrs($options).' />';
				break;
			case "shipping-province":
			case "shipping-state":
				if ($options['mode'] == "value") return $this->Shipping->state;
				if (!isset($options['selected'])) $options['selected'] = false;
				if (!empty($this->Shipping->state)) {
					$options['selected'] = $this->Shipping->state;
					$options['value'] = $this->Shipping->state;
				}

				$output = false;
				$country = $base['country'];
				if (!empty($this->Shipping->country))
					$country = $this->Shipping->country;
				if (!array_key_exists($country,$countries)) $country = key($countries);

				$classname = "shipping-state".(isset($options['class']) ? ' '.$options['class'] : '');

				$regions = Lookup::country_zones();
				$states = $regions[$country];

				if (isset($options['options']) && empty($states)) $states = explode(",",$options['options']);

				if (isset($options['type']) && $options['type'] == "text")
					return '<input type="text" name="shipping[state]" id="shipping-state" '.inputattrs($options).'/>';

				$label = (!empty($options['label']))?$options['label']:'';
				$options['disabled'] = 'disabled';
				$options['class'] = ($classname?"$classname ":"").'disabled hidden';

				$output .= '<select name="shipping[state]" id="shipping-state-menu" '.inputattrs($options,$select_attrs).'>';
				$output .= '<option value="">'.$label.'</option>';
				if (is_array($states) && !empty($states)) $output .= menuoptions($states,$options['selected'],true);
				$output .= '</select>';
				unset($options['disabled']);
				$options['class'] = $classname;
				$output .= '<input type="text" name="shipping[state]" id="shipping-state" '.inputattrs($options).'/>';

				return $output;
				break;
			case "shipping-postcode":
				if ($options['mode'] == "value") return $this->Shipping->postcode;
				if (!empty($this->Shipping->postcode))
					$options['value'] = $this->Shipping->postcode;
				return '<input type="text" name="shipping[postcode]" id="shipping-postcode" '.inputattrs($options).' />'; break;
			case "shipping-country":
				if ($options['mode'] == "value") return $this->Shipping->country;
				if (!empty($this->Shipping->country))
					$options['selected'] = $this->Shipping->country;
				else if (empty($options['selected'])) $options['selected'] = $base['country'];
				$output = '<select name="shipping[country]" id="shipping-country" '.inputattrs($options,$select_attrs).'>';
			 	$output .= menuoptions($countries,$options['selected'],true);
				$output .= '</select>';
				return $output;
				break;
			case "same-shipping-address":
				$label = __("Same shipping address","Shopp");
				if ( isset($options['label']) ) $label = $options['label'];
				$checked = ' checked="checked"';
				if (  	( isset($this->sameship) && ! $this->sameship ) ||
						( isset($options['checked']) && ! value_is_true($options['checked']) )
					) {
						$checked = '';
				}

				$output = '<label for="same-shipping"><input type="hidden" name="sameshipaddress" value="off" /><input type="checkbox" name="sameshipaddress" value="on" id="same-shipping" '.$checked.' /> '.$label.'</label>';
				return $output;
				break;
			case "residential-shipping-address":
				$label = __("Residential shipping address","Shopp");
				if (isset($options['label'])) $label = $options['label'];
				if (isset($options['checked']) && value_is_true($options['checked'])) $checked = ' checked="checked"';
				$output = '<label for="residential-shipping"><input type="hidden" name="shipping[residential]" value="no" /><input type="checkbox" name="shipping[residential]" value="yes" id="residential-shipping" '.$checked.' /> '.$label.'</label>';
				return $output;
				break;

			// BILLING TAGS
			case "billing-required": // DEPRECATED
			case "card-required":
				if ($this->Cart->Totals->total == 0) return false;
				foreach ($Shopp->Gateways->active as $gateway)
					if (!empty($gateway->cards)) return true;
				return false;
				break;
			case "billing-address":
				if ($options['mode'] == "value") return $this->Billing->address;
				if (!empty($this->Billing->address))
					$options['value'] = $this->Billing->address;
				return '<input type="text" name="billing[address]" id="billing-address" '.inputattrs($options).' />';
				break;
			case "billing-xaddress":
				if ($options['mode'] == "value") return $this->Billing->xaddress;
				if (!empty($this->Billing->xaddress))
					$options['value'] = $this->Billing->xaddress;
				return '<input type="text" name="billing[xaddress]" id="billing-xaddress" '.inputattrs($options).' />';
				break;
			case "billing-city":
				if ($options['mode'] == "value") return $this->Billing->city;
				if (!empty($this->Billing->city))
					$options['value'] = $this->Billing->city;
				return '<input type="text" name="billing[city]" id="billing-city" '.inputattrs($options).' />';
				break;
			case "billing-province":
			case "billing-state":
				if ($options['mode'] == "value") return $this->Billing->state;
				if (!isset($options['selected'])) $options['selected'] = false;
				if (!empty($this->Billing->state)) {
					$options['selected'] = $this->Billing->state;
					$options['value'] = $this->Billing->state;
				}

				$output = false;
				$country = $base['country'];
				if (!empty($this->Billing->country))
					$country = $this->Billing->country;
				if (!array_key_exists($country,$countries)) $country = key($countries);

				$regions = Lookup::country_zones();
				$states = $regions[$country];

				if (isset($options['options']) && empty($states)) $states = explode(",",$options['options']);

				if (isset($options['type']) && $options['type'] == "text")
					return '<input type="text" name="billing[state]" id="billing-state" '.inputattrs($options).'/>';

				$classname = ( isset($options['class']) ? $options['class'].' ' : '' ).'billing-state';

				$label = (!empty($options['label']))?$options['label']:'';
				$options['disabled'] = 'disabled';
				$options['class'] = ($classname?"$classname ":"").'disabled hidden';

				$output .= '<select name="billing[state]" id="billing-state-menu" '.inputattrs($options,$select_attrs).'>';
				$output .= '<option value="">'.$label.'</option>';
				if (is_array($states) && !empty($states)) $output .= menuoptions($states,$options['selected'],true);
				$output .= '</select>';
				unset($options['disabled']);
				$options['class'] = $classname;
				$output .= '<input type="text" name="billing[state]" id="billing-state" '.inputattrs($options).'/>';

				return $output;
				break;
			case "billing-postcode":
				if ($options['mode'] == "value") return $this->Billing->postcode;
				if (!empty($this->Billing->postcode))
					$options['value'] = $this->Billing->postcode;
				return '<input type="text" name="billing[postcode]" id="billing-postcode" '.inputattrs($options).' />';
				break;
			case "billing-country":
				if ($options['mode'] == "value") return $this->Billing->country;
				if (!empty($this->Billing->country))
					$options['selected'] = $this->Billing->country;
				else if (empty($options['selected'])) $options['selected'] = $base['country'];
				$output = '<select name="billing[country]" id="billing-country" '.inputattrs($options,$select_attrs).'>';
			 	$output .= menuoptions($countries,$options['selected'],true);
				$output .= '</select>';
				return $output;
				break;
			case "billing-card":
				if ($options['mode'] == "value")
					return str_repeat('X',strlen($this->Billing->card)-4)
						.substr($this->Billing->card,-4);
				$options['class'] = isset($options['class']) ? $options['class'].' paycard':'paycard';
				if (!empty($this->Billing->card))
					$options['value'] = $this->Billing->card;

				if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
				return '<input type="text" name="billing[card]" id="billing-card" '.inputattrs($options).' />';
				break;
			case "billing-cardexpires-mm":
				if ($options['mode'] == "value") return date("m",$this->Billing->cardexpires);
				$options['class'] = isset($options['class']) ? $options['class'].' paycard':'paycard';
				if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
				if (!empty($this->Billing->cardexpires))
					$options['value'] = date("m",$this->Billing->cardexpires);
				return '<input type="text" name="billing[cardexpires-mm]" id="billing-cardexpires-mm" '.inputattrs($options).' />';
				break;
			case "billing-cardexpires-yy":
				if ($options['mode'] == "value") return date("y",$this->Billing->cardexpires);
				$options['class'] = isset($options['class']) ? $options['class'].' paycard':'paycard';
				if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
				if (!empty($this->Billing->cardexpires))
					$options['value'] = date("y",$this->Billing->cardexpires);
				return '<input type="text" name="billing[cardexpires-yy]" id="billing-cardexpires-yy" '.inputattrs($options).' />';
				break;
			case "billing-cardtype":
				if ($options['mode'] == "value") return $this->Billing->cardtype;
				$options['class'] = isset($options['class']) ? $options['class'].' paycard':'paycard';
				if (!isset($options['selected'])) $options['selected'] = false;
				if (!empty($this->Billing->cardtype))
					$options['selected'] = $this->Billing->cardtype;

				$cards = array();
				foreach ($this->paycards as $paycard)
					$cards[$paycard->symbol] = $paycard->name;

				$label = (!empty($options['label']))?$options['label']:'';
				$output = '<select name="billing[cardtype]" id="billing-cardtype" '.inputattrs($options,$select_attrs).'>';
				$output .= '<option value="" selected="selected">'.$label.'</option>';
			 	$output .= menuoptions($cards,$options['selected'],true);
				$output .= '</select>';

				$js = array();
				$js[] = "var paycards = {};";
				foreach ($this->paycards as $handle => $paycard) {
					$js[] = "paycards['".$handle."'] = ".json_encode($paycard).";";
				}
				add_storefrontjs(join("",$js), true);

				return $output;
				break;
			case "billing-cardholder":
				if ($options['mode'] == "value") return $this->Billing->cardholder;
				$options['class'] = isset($options['class']) ? $options['class'].' paycard':'paycard';
				if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
				if (!empty($this->Billing->cardholder))
					$options['value'] = $this->Billing->cardholder;
				return '<input type="text" name="billing[cardholder]" id="billing-cardholder" '.inputattrs($options).' />';
				break;
			case "billing-cvv":
				if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
				if (!empty($_POST['billing']['cvv']))
					$options['value'] = $_POST['billing']['cvv'];
				$options['class'] = isset($options['class']) ? $options['class'].' paycard':'paycard';
				return '<input type="text" name="billing[cvv]" id="billing-cvv" '.inputattrs($options).' />';
				break;
			case "billing-xcsc-required":
				$Gateways = $Shopp->Gateways->active;
				foreach ($Gateways as $Gateway) {
					foreach ((array)$Gateway->settings['cards'] as $card) {
						$PayCard = Lookup::paycard($card);
						if (!empty($PayCard->inputs)) return true;
					}
				}
				return false;
				break;
			case "billing-xcsc":
				if (empty($options['input'])) return;
				$input = $options['input'];

				$cards = array();
				$valid = array();
				// Collect valid card inputs for all gateways
				foreach ($this->payoptions as $payoption) {
					foreach ($payoption->cards as $card) {
						$PayCard = Lookup::paycard($card);
						if (empty($PayCard->inputs)) continue;
						$cards[] = $PayCard->symbol;
						foreach ($PayCard->inputs as $field => $size)
							$valid[$field] = $size;
					}
				}

				if (!array_key_exists($input,$valid)) return;

				if (!empty($_POST['billing']['xcsc'][$input]))
					$options['value'] = $_POST['billing']['xcsc'][$input];
				$options['class'] = isset($options['class']) ? $options['class'].' paycard xcsc':'paycard xcsc';

				if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
				$string = '<input type="text" name="billing[xcsc]['.$input.']" id="billing-xcsc-'.$input.'" '.inputattrs($options).' />';
				return $string;
				break;
			case "billing-xco": return; break; // DEPRECATED
			case "billing-localities":
				$rates = $Shopp->Settings->get("taxrates");
				foreach ((array)$rates as $rate) if (isset($rate['locals']) && is_array($rate['locals'])) return true;
				return false;
				break;
			case "billing-locale":
				$output = false;

				if ( "value" == $options['mode'] ) { return $this->Billing->locale; }

				if ( ! isset($options['selected']) ) {
					$options['selected'] = $this->Billing->locale ? $this->Billing->locale : false;
				}

				$rates = $Shopp->Settings->get("taxrates");
				foreach ( $rates as $rate ) { // @todo - what if more than one set of local rates applies to current country/zone? ie. conditions
					if ( isset( $rate['locals'] ) ) {
						$locales[$rate['country'].$rate['zone']] = array_keys($rate['locals']);
					}
				}

				// if there are no local tax jurisdictions in settings
				if ( ! empty($locales) ) {
					// Add all the locales to the javascript environment
					add_storefrontjs('var locales = '.json_encode($locales).';',true);

					$Taxes = new CartTax();

					// Check for local rates applying to current country/zone
					$setting = true; // return the whole rate setting, not just the percentage
					$Item = false; // Item to pass to tax rate lookup
					$rate = $Taxes->rate($Item,$setting);

					// If the current country.state combination doesn't match any of the local jurisdictions,
					// check for local jurisdiction rate setting that has a product-specific condition.
					if( ! isset($rate['locals']) ) {
						foreach ( $this->Cart->contents as $Item ) {
							if ( ( $rate = $Taxes->rate($Item,$setting) ) && isset($rate['locals']) ) {
								break;
							}
						}
					}

					// names of local tax jurisdictions that apply to current country.zone
					$localities = array();
					if ( isset($rate['locals']) ) {
						$localities = array_keys($rate['locals']);
					}

					// Make this a required field
					$options['class'] .= ( isset($options['class']) ? ", " : "" . "required" );

					// disable this field automatically if no local jurisdictions apply to current country.zone
					if ( empty($localities) ) $options['disabled'] = 'disabled';

					// Start stub select menu for billing local tax jurisdiction (needed for javascript to populate)
					$output = '<select name="billing[locale]" id="billing-locale" '.inputattrs($options,$select_attrs).'>';

				 	if ( ! empty($localities) ) { $output .= "<option></option>".menuoptions($localities, $options['selected']); }

					// End stub select menu for billing local tax jurisdiction
					$output .= '</select>';
				}

				return $output;
				break;
			case "has-data":
			case "hasdata": return (is_array($this->data) && count($this->data) > 0); break;
			case "order-data":
			case "orderdata":
				$defaults = array(
					'name' => false, // REQUIRED
					'data' => false,
					'mode' => false,
					'title' => '',
					'type' => 'hidden',
					'value' => '',
					'cols' => '30',
					'rows' => '3',
					'options' => ''
				);
				$op = array_merge($defaults,$options);
				extract($op);

				// Allowed input types
				$allowed_types = array("text","hidden","password","checkbox","radio","textarea","menu");

				// Input types that can override option-specified value with the loaded data value
				$value_override = array("text","hidden","password","textarea","menu");

				/// Allowable attributes for textarea inputs
				$textarea_attrs = array('accesskey','title','tabindex','class','disabled','required');

				if (!$name) { // Iterator for order data
					if (!isset($this->_data_loop)) {
						reset($this->data);
						$this->_data_loop = true;
					} else next($this->data);

					if (current($this->data) !== false) return true;
					else {
						unset($this->_data_loop);
						return false;
					}
				}

				if (isset($this->data[$name])) $data = $this->data[$name];
				if ($name && $mode == "value") return $data;

				if (!in_array($type,$allowed_types)) $type = 'hidden';
				if (empty($title)) $title = $name;
				$id = 'order-data-'.sanitize_title_with_dashes($name);

				if (in_array($type,$value_override) && !empty($data))
					$value = $data;
				switch (strtolower($type)) {
					case "textarea":
						return '<textarea name="data['.$name.']" cols="'.$cols.'" rows="'.$rows.'" id="'.$id.'" '.inputattrs($op,$textarea_attrs).'>'.$value.'</textarea>';
						break;
					case "menu":
						if (is_string($options)) $options = explode(',',$options);
						return '<select name="data['.$name.']" id="'.$id.'" '.inputattrs($op,$select_attrs).'>'.menuoptions($options,$value).'</select>';
						break;
					default:
						return '<input type="'.$type.'" name="data['.$name.']" id="'.$id.'" '.inputattrs($op).' />';
						break;
				}
				break;
			case "data":
				if (!is_array($this->data)) return false;
				$data = current($this->data);
				$name = key($this->data);
				if (isset($options['name'])) return $name;
				return $data;
				break;
			case "submit":
				if (!isset($options['value'])) $options['value'] = __('Submit Order','Shopp');
				$options['class'] = isset($options['class'])?$options['class'].' checkout-button':'checkout-button';

				$wrapclass = '';
				if (isset($options['wrapclass'])) $wrapclass = ' '.$options['wrapclass'];

				$buttons = array('<input type="submit" name="process" id="checkout-button" '.inputattrs($options,$submit_attrs).' />');

				if (!$this->Cart->orderisfree())
					$buttons = apply_filters('shopp_checkout_submit_button',$buttons,$options,$submit_attrs);

				$_ = array();
				foreach ($buttons as $label => $button)
					$_[] = '<span class="payoption-button payoption-'.sanitize_title_with_dashes($label).($label === 0?$wrapclass:'').'">'.$button.'</span>';

				return join("\n",$_);
				break;
			case "confirm-button":
				if (empty($options['errorlabel'])) $options['errorlabel'] = __('Return to Checkout','Shopp');
				if (empty($options['value'])) $options['value'] = __('Confirm Order','Shopp');

				$button = '<input type="submit" name="confirmed" id="confirm-button" '.inputattrs($options,$submit_attrs).' />';
				$return = '<a href="'.shoppurl(false,'checkout',$this->security()).'"'.inputattrs($options,array('class')).'>'.
								$options['errorlabel'].'</a>';

				if (!$this->validated) $markup = $return;
				else $markup = $button;
				return apply_filters('shopp_checkout_confirm_button',$markup,$options,$submit_attrs);
				break;
			case "local-payment": return true; break; // DEPRECATED
			case "xco-buttons": return;	break; // DEPRECATED
			case "payoptions":
			case "payment-options":
			case "paymentoptions":
				if ($this->Cart->orderisfree()) return false;
				$payment_methods = apply_filters('shopp_payment_methods',count($this->payoptions));
				if ($payment_methods <= 1) return false; // Skip if only one gateway is active
				$defaults = array(
					'default' => false,
					'exclude' => false,
					'type' => 'menu',
					'mode' => false
				);
				$options = array_merge($defaults,$options);
				extract($options);
				unset($options['type']);

				if ("loop" == $mode) {
					if (!isset($this->_pay_loop)) {
						reset($this->payoptions);
						$this->_pay_loop = true;
					} else next($this->payoptions);

					if (current($this->payoptions) !== false) return true;
					else {
						unset($this->_pay_loop);
						return false;
					}
					return true;
				}

				$excludes = array_map('sanitize_title_with_dashes',explode(",",$exclude));
				$payoptions = array_keys($this->payoptions);

				$payoptions = array_diff($payoptions,$excludes);
				$paymethod = current($payoptions);

				if ($default !== false && !isset($this->_paymethod_selected)) {
					$default = sanitize_title_with_dashes($default);
					if (in_array($default,$payoptions)) $paymethod = $default;
				}

				if ($this->paymethod != $paymethod) {
					$this->paymethod = $paymethod;
					$processor = $this->payoptions[$this->paymethod]->processor;
					if (!empty($processor)) $this->processor($processor);
				}

				$output = '';
				switch ($type) {
					case "list":
						$output .= '<span><ul>';
						foreach ($payoptions as $value) {
							if (in_array($value,$excludes)) continue;
							$payoption = $this->payoptions[$value];
							$options['value'] = $value;
							$options['checked'] = ($this->paymethod == $value)?'checked':false;
							if ($options['checked'] === false) unset($options['checked']);
							$output .= '<li><label><input type="radio" name="paymethod" '.inputattrs($options).' /> '.$payoption->label.'</label></li>';
						}
						$output .= '</ul></span>';
						break;
					case "hidden":
						if (!isset($options['value']) && $default) $options['value'] = $this->paymethod;
						$output .= '<input type="hidden" name="paymethod"'.inputattrs($options).' />';
						break;
					default:
						$output .= '<select name="paymethod" '.inputattrs($options,$select_attrs).'>';
						foreach ($payoptions as $value) {
							if (in_array($value,$excludes)) continue;
							$payoption = $this->payoptions[$value];
							$selected = ($this->paymethod == $value)?' selected="selected"':'';
							$output .= '<option value="'.$value.'"'.$selected.'>'.$payoption->label.'</option>';
						}
						$output .= '</select>';
						break;
				}

				return $output;
				break;
			case "payoption":
			case "payment-option":
			case "paymentoption":
				$payoption = current($this->payoptions);
				$defaults = array(
					'labelpos' => 'after',
					'labeling' => false,
					'type' => 'hidden',
				);
				$options = array_merge($defaults,$options);
				extract($options);

				if (value_is_true($return)) return $payoption;

				$types = array('radio','checkbox','hidden');
				if (!in_array($type,$types)) $type = 'hidden';

				if (empty($options['value'])) $options['value'] = key($this->payoptions);

				$_ = array();
				if (value_is_true($labeling))
					$_[] = '<label>';
				if ($labelpos == "before") $_[] = $payoption->label;
				$_[] = '<input type="'.$type.'" name="paymethod"'.inputattrs($options).' />';
				if ($labelpos == "after") $_[] = $payoption->label;
				if (value_is_true($labeling))
					$_[] = '</label>';

				return join("",$_);
				break;
			case "gatewayinputs":
			case "gateway-inputs":
				return apply_filters('shopp_checkout_gateway_inputs',false);
				break;
			case "completed":
				if (empty($Shopp->Purchase->id) && $this->purchase !== false) {
					$Shopp->Purchase = new Purchase($this->purchase);
					$Shopp->Purchase->load_purchased();
					return (!empty($Shopp->Purchase->id));
				}
				return false;
				break;
			case "receipt":
				if (!empty($Shopp->Purchase->id))
					return $Shopp->Purchase->receipt();
				break;
		}
	}

} // END class Order

/**
 * Helper to access the Shopping Order
 *
 * @author Jonathan Davis
 * @since 1.0
 *
 * @return Order
 **/
function &ShoppOrder () {
	global $Shopp;
	return $Shopp->Order;
}

?>