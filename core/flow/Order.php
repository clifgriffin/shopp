<?php
/**
 * Order
 *
 * Order data container and middleware object
 *
 * @author Jonathan Davis
 * @version 1.2
 * @copyright Ingenesis Limited, January 12, 2010
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @subpackage order
 **/

/**
 * Order
 *
 * @author Jonathan Davis
 * @since 1.1
 * @version 1.2
 * @package order
 **/
class Order {

	var $Customer = false;			// The current customer
	var $Shipping = false;			// The shipping address
	var $Billing = false;			// The billing address
	var $Cart = false;				// The shopping cart
	var $data = array();			// Extra/custom order data
	var $payoptions = array();		// List of payment method options
	var $paycards = array();		// List of accepted PayCards
	var $sameaddress = false;		// Toggle for copying a primary address to the secondary address
	var $guest = false;				// Flag for guest checkout

	var $processor = false;			// The payment processor module name
	var $paymethod = false;			// The selected payment method

	// Post processing properties
	var $inprogress = false;		// Generated purchase ID
	var $purchase = false;			// Purchase ID of the finalized sale
	var $gateway = false;			// Proper name of the gateway used to process the order
	var $txnid = false;				// The transaction ID reported by the gateway
	var $txnstatus = 'PENDING';		// Status of the payment

	// Processing control properties
	var $confirm = false;			// Flag to confirm order or not
	var $confirmed = false;			// Confirmed by the shopper for processing
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

		$this->Billing = new BillingAddress();
		$this->Billing->locate();

		$this->Shipping = new ShippingAddress();
		$this->Shipping->locate();

		$this->created = null;

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

	function __destruct() {
		$this->unhook();
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
		$this->confirm = (shopp_setting('order_confirmation') == 'always');
		$this->validated = false; // Reset the order validation flag

		add_action('parse_request',array($this,'request'));

		add_action('shopp_process_shipmethod', array($this,'shipmethod'));
		add_action('shopp_process_checkout', array($this,'checkout'));
		add_action('shopp_confirm_order', array($this,'confirmed'));


		// Order processing
		add_action('shopp_process_order', array($this,'validate'),7);
		add_action('shopp_process_order', array($this,'submit'),100);

		add_action('shopp_process_free_order',array($this,'freebie'));
		add_action('shopp_update_destination',array($this->Shipping,'locate'));

		add_action('shopp_purchase_order_event',array($this,'purchase'));
		add_action('shopp_purchase_order_created',array($this,'invoice'));
		add_action('shopp_purchase_order_created',array($this,'process'));

		add_action('shopp_authed_order_event',array($this,'unstock'));
		add_action('shopp_authed_order_event',array($this,'captured'));

		// Status updates
		add_action('shopp_order_txnstatus_update',array($this,'salestats'),10,2);

		// Ensure payment card PAN is truncated after successful processing
		add_action('shopp_authed_order_event',array($this,'securecard'));

		add_action('shopp_resession',array($this->Cart,'clear'));
		add_action('shopp_resession',array($this,'clear'));

		// Collect available payment methods from active gateways
		// Schedule for after the gateways are loaded (priority 20)
		add_action('shopp_init',array($this,'payoptions'),20);

		// Process customer selected payment methods after gateways are loaded (priority 20)
		add_action('shopp_init',array($this,'payment'),20);

		// Select the default gateway processor
		// Schedule for after the gateways are loaded (priority 20)
		add_action('shopp_init',array($this,'processor'),20);

		// Handle remote transaction processing (priority 20)
		// Needs to happen after the processor is selected in the session,
		// but before gateway-order specific handlers are established
		add_action('shopp_init',array($this,'txnupdates'),20);

		// Set locking timeout for concurrency operation protection
		if (!defined('SHOPP_TXNLOCK_TIMEOUT')) define('SHOPP_TXNLOCK_TIMEOUT',10);

	}

	function unhook () {
		remove_action('shopp_authed_order_event',array($this,'purchase'));
		remove_action('shopp_create_purchase',array($this,'purchase'));
		remove_action('shopp_purchase_order_event',array($this,'purchase'));
		remove_action('shopp_purchase_order_created',array($this,'invoice'));
		remove_action('shopp_purchase_order_created',array($this,'process'));

		remove_action('shopp_process_order', array($this,'validate'),7);
		remove_action('shopp_process_order', array($this,'submit'),100);
	}

	/**
	 * Handles remote transaction update request flow control
	 *
	 * Moved from the Flow class in 1.2.3
	 *
	 * @author Jonathan Davis
	 * @since 1.2.3
	 *
	 * @return void
	 **/
	function txnupdates () {

		add_action('shopp_txn_update',create_function('',"status_header('200'); exit();"),101); // Default shopp_txn_update requests to HTTP status 200

		if ( ! empty($_REQUEST['_txnupdate']) )
			return do_action('shopp_txn_update');

	}

	/**
	 * Handles checkout request flow control
	 *
	 * @author Jonathan Davis
	 * @since 1.2.3
	 *
	 * @return void
	 **/
	function request () {

		if ( ! empty($_REQUEST['rmtpay']) )
			return do_action('shopp_remote_payment');

		if ( array_key_exists('checkout',$_POST) ) {

			$checkout = strtolower($_POST['checkout']);
			if ('process' == $checkout) 		do_action('shopp_process_checkout');
			elseif ('confirmed' == $checkout)	do_action('shopp_confirm_order');

		} elseif ( array_key_exists('shipmethod',$_POST) ) {

			do_action('shopp_process_shipmethod');

		}

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

		if ('FreeOrder' == $this->processor) return;

		global $Shopp;
		$Gateways = $Shopp->Gateways;
		$accepted = array();
		$options = array();
		$processor = false;

		$gateways = explode(",",shopp_setting('active_gateways'));

		foreach ($gateways as $gateway) {
			$id	= false;
			if (false !== strpos($gateway,'-')) list($module,$id) = explode('-',$gateway);
			else $module = $gateway;
			if (!isset($Gateways->active[ $module ])) continue;
			$Gateway = $Gateways->active[ $module ];
			if ($module == $this->processor) $processor = true;
			$settings = $Gateway->settings;

			if ( false !== $id && isset($Gateway->settings[$id]) )
				$settings = $Gateway->settings[$id];

			$accepted = array_merge($accepted,$Gateway->cards());

			$_ = new StdClass();
			$_->label = $settings['label'];
			$_->processor = $Gateway->module;
			$_->setting = $gateway;
			$_->cards = array_keys($Gateway->cards());
			$handle = sanitize_title_with_dashes($_->label);

			$options[$handle] = $_;
		}

		$this->paycards = $accepted;
		$this->payoptions = $options;

		$processors = array_keys($this->payoptions);
		$processors[] = 'FreeOrder'; // Include FreeOrder in list of available payment systems

		// Setup default payment method if the current is not found in the active gateways or payment options
		if ( false == $processor || !in_array($this->paymethod,array_keys($this->payoptions))) {
			$default = reset($this->payoptions);

			if ( ! empty($default) )
					$this->paymethod = key($this->payoptions);

			if ( ! empty($this->payoptions) )
				$this->processor = $this->payoptions[$this->paymethod]->processor;
		}

	}

	function payment () {
		global $Shopp;
		$Gateways = $Shopp->Gateways;

		// Set the gateway processor from a selected payment method
		if ( isset($_POST['paymethod']) ) {
			$selected = $_POST['paymethod'];
			unset($_POST['paymethod']); // Prevent unnecessary reprocessing on subsequent calls
			$processor = false;
			if ( isset($this->payoptions[$selected]) ) {
				$processor = $this->payoptions[$selected]->processor;
				if (in_array($processor,$Gateways->activated)) {
					$this->paymethod = $selected;
					$this->processor = $processor;
					$this->_paymethod_selected = true;
				}
			}
			if (!$processor) new ShoppError(__('The payment method you selected is no longer available. Please choose another.','Shopp'));
		}

	}

	/**
	 * Set or get the currently selected gateway processor
	 *
	 * @author Jonathan Davis, John Dillick
	 * @since 1.1
	 *
	 * @return Object|false The currently selected gateway
	 **/
	public function processor ($processor=false) {
		global $Shopp;

		if ('FreeOrder' == $processor || 'FreeOrder' == $this->processor) {
			if ( $Shopp->Gateways->freeorder ) {
				$this->processor = 'FreeOrder';
				$Shopp->Gateways->activated = array($this->processor);
				if ( ! isset($Shopp->Gateways->active[ $processor ]) )
					$Shopp->Gateways->active[ $processor ] = $Shopp->Gateways->freeorder;
				$this->paymethod = sanitize_title_with_dashes($Shopp->Gateways->freeorder->name);
				return $this->processor;
			}
		}

		// No processor set for this order, set default from payment options
		if ( false == $this->processor ) {
			$default = reset($this->payoptions);
			if (!empty($default)) $this->paymethod = key($this->payoptions);
		}

		// No valid payoptions for the selected payment method, bail
		if ( ! isset($this->payoptions[$this->paymethod]) ) {
			$this->paymethod = false;
			// Only show error after checkout form is submitted
			if (isset($_POST['checkout'])) new ShoppError(Lookup::errors('gateway','nogateways'));
			return false;
		}

		// Set the processor based on the payment method selected
		$processor = $this->payoptions[$this->paymethod]->processor;
		if (in_array($processor,$Shopp->Gateways->activated))
			$this->processor = $processor;

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
	function paycard () {
		$ccdata = array('card','cardexpires-mm','cardexpires-yy','cvv');
		foreach ($ccdata as $field)
			if (isset($_POST['billing'][$field])) return true;
		return false;
	}

	/**
	 * Provides the current payment method
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @return object Payment method object
	 **/
	function paymethod () {
		if (!isset($this->paymethod)) return false;
		if (!isset($this->payoptions[$this->paymethod])) return false;

		return $this->payoptions[$this->paymethod];
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
	function shipmethod () {
		if (empty($this->Cart->shipped)) return;
		if (empty($this->Shipping))
				$this->Shipping = new ShippingAddress();

		if ($_POST['shipmethod'] == $this->Shipping->method) return;

		// Verify shipping method exists first
		if ( !isset($this->Cart->shipping[ $_POST['shipmethod'] ]) ) return;

		$this->Shipping->method = $_POST['shipmethod'];
		$this->Shipping->option = $this->Cart->shipping[$_POST['shipmethod']]->name;

		$this->Cart->retotal = true;
		$this->Cart->totals();
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
		$Shopping = ShoppShopping();
		$Cart = $this->Cart;

		if (!isset($_POST['checkout'])) return;
		if ($_POST['checkout'] != 'process') return;

		$_POST = stripslashes_deep($_POST);

		$cc = $this->paycard();

		if ($cc) {
			$_POST['billing']['cardexpires'] = sprintf('%02d%02d',$_POST['billing']['cardexpires-mm'],$_POST['billing']['cardexpires-yy']);

			// If the card number is provided over a secure connection
			// Change the cart to operate in secure mode
			if (!empty($_POST['billing']['card']) && is_ssl())
				$Shopping->secured(true);

			// Sanitize the card number to ensure it only contains numbers
			if (!empty($_POST['billing']['card']))
				$_POST['billing']['card'] = preg_replace('/[^\d]/','',$_POST['billing']['card']);

		}

		// Update guest checkout flag
		if (isset($_POST['guest'])) $this->guest = str_true($_POST['guest']);
		$this->guest = apply_filters('shopp_guest_checkout', $this->guest);

		// Remove invlalid characters from the phone number
		$_POST['phone'] = preg_replace('/[^\d\(\)\-+\. (ext|x)]/','',$_POST['phone']);

		if (isset($_POST['data'])) $this->data = $_POST['data'];
		if (isset($_POST['info'])) $this->Customer->info = $_POST['info'];

		if (empty($this->Customer)) $this->Customer = new Customer();
		else $this->Customer->reset();

		$this->Customer->updates($_POST);

		// Keep confirm-password field value when showing checkout validation errors
		if (isset($_POST['confirm-password']))
			$this->Customer->_confirm_password = $_POST['confirm-password'];

		if (empty($this->Billing))
			$this->Billing = new BillingAddress();

		// Default the cardtype to the payment method label selected
		$this->Billing->cardtype = $this->payoptions[$this->paymethod]->label;

		$ignore = array();
		if (isset($_POST['billing']['card']) && $_POST['billing']['card'] == substr($this->Billing->card,-4))
			$ignore[] = 'card';
		$this->Billing->updates($_POST['billing'],$ignore);

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
				$this->Billing->xcsc = array();
				foreach ($_POST['billing']['xcsc'] as $field => $value) {
					$this->Billing->xcsc[] = $field;
					$this->Billing->{$field} = $value;
				}
			}
		}

		if (!empty($Cart->shipped)) {
			if (empty($this->Shipping))
				$this->Shipping = new ShippingAddress();

			if (isset($_POST['shipping'])) $this->Shipping->updates($_POST['shipping']);
			if (!empty($_POST['shipmethod']) && isset($Cart->shipping[$_POST['shipmethod']])) $this->Shipping->method = $_POST['shipmethod'];
			else $this->Shipping->method = key($Cart->shipping);

			if (isset($Cart->shipping[$this->Shipping->method]))
				$this->Shipping->option = $Cart->shipping[$this->Shipping->method]->name;

		} else $this->Shipping = new ShippingAddress(); // Use blank shipping for non-Shipped orders

		// Same address handling
		if ( isset($_POST['sameaddress']) ) {
			switch (strtolower($_POST['sameaddress'])) {
				case 'shipping':
					$this->sameaddress = 'shipping';
					$this->Shipping->updates($_POST['billing']);
					break;
				case 'billing':
					$this->sameaddress = 'billing';
					$this->Billing->updates($_POST['shipping']);
					break;
				default:
					$this->sameaddress = 'off';
					break;
			}
		}

		$freebie = $Cart->orderisfree();
		$estimated = $Cart->Totals->total;

		$Cart->changed(true);
		$Cart->totals();

		// Stop here if this is a shipping method update
		if (isset($_POST['update-shipping'])) return;

		if ($this->validform() !== true) return;
		else $this->Customer->updates($_POST); // Catch changes from validation

		// Catch originally free orders that get extra (shipping) costs added to them
		if ($freebie && !$Cart->orderisfree()) {

			if ( ! (count($this->payoptions) == 1 // One paymethod
					&& ( isset($this->payoptions[$this->paymethod]->cards) // Remote checkout
						&& empty( $this->payoptions[$this->paymethod]->cards ) ) )
				) {
				new ShoppError(__('The order amount changed and requires that you select a payment method.','Shopp'),'checkout_no_paymethod');
				shopp_redirect( shoppurl(false,'checkout',$this->security()) );
			}

		}

		// If using shopp_checkout_processed for a payment gateway redirect action
		// be sure to include a ShoppOrder()->Cart->orderisfree() check first.
		do_action('shopp_checkout_processed');

		if ($Cart->orderisfree()) do_action('shopp_process_free_order');

		// If the cart's total changes at all, confirm the order
		if (apply_filters('shopp_order_confirm_needed', ($estimated != $Cart->Totals->total || $this->confirm) ))
			shopp_redirect( shoppurl(false,'confirm',$this->security()) );
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
	function confirmed () {
		if ($_POST['checkout'] == 'confirmed') {
			$this->confirmed = true;
			do_action('shopp_process_order');
		}
	}

	/**
	 * Submits the order to create a Purchase record
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @return void
	 **/
	function submit () {
		shopp_add_order_event(false,'purchase',array(
			'gateway' => $this->processor()
		));
	}

	/**
	 * Creates an invoice transaction event to setup the payment balance
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @return void
	 **/
	function invoice ($Purchase) {
		shopp_add_order_event($Purchase->id,'invoiced',array(
			'gateway' => $Purchase->gateway,			// Gateway handler name (module name from @subpackage)
			'amount' => $Purchase->total				// Capture of entire order amount
		));
	}

	/**
	 * Fires an unstock order event for a purchase to deduct stock from inventory
	 *
	 * @author Jonathan Davis
	 * @since 1.2.1
	 *
	 * @return void
	 **/
	function unstock ( AuthedOrderEvent $Event ) {
		if ( ! shopp_setting_enabled('inventory') ) return false;

		$Purchase = ShoppPurchase();
		if (!isset($Purchase->id) || empty($Purchase->id) || $Event->order != $Purchase->id)
			$Purchase = new Purchase($Event->order);

		if ( ! isset($Purchase->events) || empty($Purchase->events) ) $Purchase->load_events(); // Load purchased
		if ( in_array('unstock', array_keys($Purchase->events)) ) return true; // Unstock already occurred, do nothing
		if ( empty($Purchase->purchased) ) $Purchase->load_purchased();
		if ( ! $Purchase->stocked ) return false;

		shopp_add_order_event($Purchase->id,'unstock');
	}

	/**
	 * Marks an order as captured
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @return void
	 **/
	function captured ($Event) {

		if ('authed' == $Event->name) {
			if (!isset($Event->capture)) return;
			if (!$Event->capture) return;
		}

		shopp_add_order_event($Event->order,'captured',array(
			'txnid' => $Event->txnid,				// Can be either the original transaction ID or an ID for this transaction
			'amount' => $Event->amount,				// Capture of entire order amount
			'fees' => $Event->fees,					// Transaction fees taken by the gateway net revenue = amount-fees
			'gateway' => $Event->gateway			// Gateway handler name (module name from @subpackage)
		));
	}

	/**
	 * Order processing decides the type of transaction processing request to make
	 *
	 * Decides with operation to request:
	 * Authorization - Get authorization to charge the order amount with the payment method provided
	 * Sale - Get authorization and immediate capture (charge) of the payment
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @return void
	 **/
	function process ($Purchase) {

		$processing = 'sale'; 						// By default, process as a sale event
		if ( $this->Cart->shipped ) {				// If there are shipped items
			$processing = 'auth';					// Use authorize payment processing don't charge
			if (shopp_setting_enabled('inventory'))	// If inventory tracking enabled, set items to unstock after successful authed event
				add_action('shopp_authed_order_event',array($this,'unstock'));
		}
		$default = array($this,$processing);

		// Gateway modules can use 'shopp_purchase_order_gatewaymodule_processing' filter hook to override order processing
		// Return a string of 'auth' for auth processing, or 'sale' for sale processing
		// For advanced overrides, gateways can provide custom callbacks as a standard PHP object callback array: array($this,'customhandler')
		if ( !empty($Purchase->gateway) ) {
			$gateway = sanitize_key($Purchase->gateway);
			$processing = apply_filters('shopp_purchase_order_'.$gateway.'_processing',$processing,$Purchase);
		}

		// General order processing filter override
		$processing = apply_filters('shopp_purchase_order_processing',$processing,$Purchase);

		if ( is_string($processing) ) $callback = array($this,$processing);
		elseif ( is_array($processing) ) $callback = $processing;

		if (!is_callable($callback)) $callback = $default;

		call_user_func($callback,$Purchase);

	}

	/**
	 * Sets up order events for Auth-only transaction processing
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @return void
	 **/
	function auth ($Purchase) {

		add_action('shopp_authed_order_event',array($this,'notify'));
		add_action('shopp_authed_order_event',array($this,'accounts'));
		add_action('shopp_authed_order_event',array($this,'success'));

		shopp_add_order_event($Purchase->id,'auth',array(
			'gateway' => $Purchase->gateway,
			'amount' => $Purchase->total
		));

	}

	/**
	 * Sets up order events for Auth-Capture "Sale" transaction processing
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @return void
	 **/
	function sale ($Purchase) {

		add_action('shopp_captured_order_event',array($this,'notify'));
		add_action('shopp_captured_order_event',array($this,'accounts'));
		add_action('shopp_captured_order_event',array($this,'success'));

		shopp_add_order_event($Purchase->id,'sale',array(
			'gateway' => $Purchase->gateway,
			'amount' => $Purchase->total
		));
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
		// if (!$free) return $free;

		$this->processor = 'FreeOrder';
		$this->processor($this->processor);
		$this->Billing->cardtype = __('Free Order','Shopp');

		return true;
	}

	/**
	 * Converts a shopping session order to a Purchase record
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	function purchase ( PurchaseOrderEvent $Event ) {
		$Shopping = ShoppShopping();

		// No auth message, bail
		if (empty($Event))
			return (!$error = new ShoppError('Order failure: An empty order event message was received by the order processor.','shopp_order_failure',SHOPP_DEBUG_ERR));

		// Copy details from Auth message
		$this->txnstatus = $Event->name;
		$this->gateway = $Event->gateway;

		$paycard = Lookup::paycard($this->Billing->cardtype);
		$this->Billing->cardtype = !$paycard?$this->Billing->cardtype:$paycard->name;

		$base = shopp_setting('base_operations');

		$promos = array();
		foreach ($this->Cart->discounts as &$promo) {
			$promos[$promo->id] = $promo->name;
			$promo->uses++;
		}

		if (empty($this->inprogress)) {
			$Purchase = new Purchase();	// Create a new order
		} else { // Handle updates to an existing order from checkout reprocessing
			$updates = true;
			if ( !empty(ShoppPurchase()->id) ) $Purchase = ShoppPurchase();	// Update existing order
			else $Purchase = new Purchase($this->inprogress);
			$changed = ($this->checksum != $this->Cart->checksum); // Detect changes to the cart
		}

		// Capture early event transaction IDs
		if ( isset($Event->txnid) ) $Purchase->txnid = $this->txnid = $Event->txnid;

		$Purchase->copydata($this);
		$Purchase->copydata($this->Customer);
		$Purchase->copydata($this->Billing);
		$Purchase->copydata($this->Shipping,'ship');
		$Purchase->copydata($this->Cart->Totals);
		$Purchase->customer = $this->Customer->id;
		$Purchase->taxing = shopp_setting_enabled('tax_inclusive')?'inclusive':'exclusive';
		$Purchase->promos = $promos;
		$Purchase->freight = $this->Cart->Totals->shipping;
		$Purchase->ip = $Shopping->ip;
		$Purchase->created = current_time('mysql');
		$Purchase->save();

		Promotion::used(array_keys($promos));

		// Process the order events if updating an existing order
		if ( ! empty($this->inprogress) ) {

			if ($changed) { // The order has changed since the last order attempt

				// Rebuild purchased records from cart items
				$Purchase->delete_purchased();

				// Void prior invoiced balance
				shopp_add_order_event($Purchase->id,'voided',array(
					'txnorigin' => '','txnid' => '',
					'gateway' => $Purchase->gateway
				));

				// Recreate purchased records from the cart and re-invoice for the new order total
				$this->items($Purchase->id);
				$this->invoice($Purchase);

			}

			ShoppPurchase($Purchase);
			return $this->process($Purchase);
		}

		// Catch Purchase record save errors
		if ( empty($Purchase->id) ) {
			new ShoppError(__('The order could not be created because of a technical problem on the server. Please try again, or contact the website adminstrator.','Shopp'),'shopp_purchase_save_failure');
			return;
		}

		$this->items($Purchase->id);		// Create purchased records from the cart items

		$this->purchase = false; 			// Clear last purchase in prep for new purchase
		$this->inprogress = $Purchase->id;	// Keep track of the purchase record in progress for transaction updates
		ShoppPurchase( $Purchase );

		if (SHOPP_DEBUG) new ShoppError('Purchase '.$Purchase->id.' was successfully saved to the database.',false,SHOPP_DEBUG_ERR);

		// Start the transaction processing events
		do_action('shopp_purchase_order_created',$Purchase);

	}

	/**
	 * Builds purchased records from cart items attached to the given Purchase ID
	 *
	 * @author Jonathan Davis
	 * @since 1.2.2
	 *
	 * @param int $purchaseid The Purchase id to attach the purchased records to
	 * @return void
	 **/
	function items ( $purchaseid ) {
		foreach($this->Cart->contents as $Item) {	// Build purchased records from cart items
			$Purchased = new Purchased();
			$Purchased->purchase = $purchaseid;
			$Purchased->copydata($Item);
			$Purchased->save();
		}
		$this->checksum = $this->Cart->checksum;	// Track the cart contents checksum to detect changes.
	}

	/**
	 * Creates a customer record (and WordPress user) and attaches the order to it
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @return void
	 **/
	function accounts ($Event) {

		// WordPress account integration used, customer has no wp user
		if (!$this->guest) {
			if ('wordpress' == shopp_setting('account_system') && empty($this->Customer->wpuser)) {
				if ( $wpuser = get_current_user_id() ) $this->Customer->wpuser = $wpuser; // use logged in WordPress account
				else $this->Customer->create_wpuser(); // not logged in, create new account
			}
		}

		// New customer, save hashed password
		if (!$this->Customer->exists()) {
			$this->Customer->id = false;
			if (SHOPP_DEBUG) new ShoppError('Creating new Shopp customer record','new_customer',SHOPP_DEBUG_ERR);
			if (empty($this->Customer->password)) $this->Customer->password = wp_generate_password(12,true);
			if (!$this->guest && 'shopp' == shopp_setting('account_system')) $this->Customer->notification();
			$this->Customer->password = wp_hash_password($this->Customer->password);
		} else unset($this->Customer->password); // Existing customer, do not overwrite password field!

		$this->Customer->save();

		// Update billing address
		if (!empty($this->Billing->address)) {
			$this->Billing->customer = $this->Customer->id;
			$this->Billing->save();
		}

		// Update shipping address
		if (!empty($this->Shipping->address)) {
			$this->Shipping->customer = $this->Customer->id;
			$this->Shipping->save();
		}

		// Update Purchase with link to created customer record
		if ( ! empty($this->Customer->id) ) {
			$Purchase = ShoppPurchase();

			if ($Purchase->id != $Event->order)
				$Purchase = new Purchase($Event->order);

			$Purchase->customer = $this->Customer->id;
			$Purchase->billing = $this->Billing->id;
			$Purchase->shipping = $this->Shipping->id;
			$Purchase->save();

		}

	}

	/**
	 * Recalculates sales stats for products
	 *
	 * Updates the sales stats for products affected by purchase transaction status changes.
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @param string $status New transaction status being set
	 * @param Purchase $Purchase The affected Purchase object
	 * @return void
	 **/
	function salestats ($status, &$Purchase) {
		if (empty($Purchase->id)) return;

		$products = DatabaseObject::tablename(Product::$table);
		$purchased = DatabaseObject::tablename(Purchased::$table);

		// Transaction status changed
		if ('CHARGED' == $status) // Now CHARGED, add quantity ordered to product 'sold' stat
			$query = "UPDATE $products AS p LEFT JOIN $purchased AS s ON p.id=s.product SET p.sold=p.sold+s.quantity WHERE s.purchase=$Purchase->id";
		elseif ($Purchase->txnstatus == 'CHARGED') // Changed from CHARGED, remove quantity ordered from product 'sold' stat
			$query = "UPDATE $products AS p LEFT JOIN $purchased AS s ON p.id=s.product SET p.sold=p.sold-s.quantity WHERE s.purchase=$Purchase->id";

		$db->query($query);

	}

	/**
	 * Sets transaction information to create the purchase record
	 *
	 * This method still exists for backward-compatibility but should **NOT** be used
	 * in development. Please use calls from the Developer API instead (such as shopp_add_order_event())
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * @deprecated Will be removed in Shopp 1.3
	 *
	 * @param string $id Transaction ID
	 * @param string $status (optional) Transaction status (PENDING, CHARGED, VOID, etc)
	 * @param float $fees (optional) Transaction fees assesed by the processor
	 *
	 * @return true
	 **/
	function transaction ($id,$status='PENDING',$fees=0) {
		global $Shopp;
		$this->txnid = $id;
		$this->txnstatus = $status;
		$this->fees = $fees;

		$Purchase = new Purchase($id,'txnid');
		$processor = ShoppOrder()->processor();
		$Gateway = $Shopp->Gateways->active[ $processor ];

		$type = false;
		switch ($status) {
			case 'CHARGED': if (!$type) $type = 'captured';
			case 'VOID':	if (!$type) $type = 'voided';
			case 'REFUND':	if (!$type) $type = 'refunded';

				// Force Sale (auth-capture) processing for 1.1 transaction model compatibility
				add_action('shopp_purchase_order_processing',create_function('$p','return "sale";'));

				add_action('shopp_sale_order_event',array($Gateway,'process'));

				if (empty($Purchase->id)) return $this->submit();

				shopp_add_order_event($Purchase->id,$type,array(
					'txnid' => $this->txnid,			// Transaction ID of the CAPTURE event
					'amount' => $Purchase->total,		// Amount captured
					'fees' => $this->fees,				// Transaction fees taken by the gateway net revenue = amount-fees
					'gateway' => $Purchase->gateway		// Gateway handler name (module name from @subpackage)
				));
				break;

			case 'PENDING':
			default:
				// Force Authorization-only processing for PENDING status orders
				add_action('shopp_purchase_order_processing',create_function('$p','return "auth";'));
				add_action('shopp_sale_order_event',array($Gateway,'process'));
		}

	}


	/**
	 * Send out new order notifications
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	function notify ($Event) {
		$Purchase = ShoppPurchase();
		if ( empty($Purchase) || empty($Purchase->id) )
			$Purchase = new Purchase($Event->order); // Load the order if not already loaded

		do_action('shopp_order_notifications',$Purchase);
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
		$this->purchase = $this->inprogress;
		$this->inprogress = false;
		do_action('shopp_order_success',ShoppPurchase());

		Shopping::resession();

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

		if (apply_filters(' shopp_clickwrap_required',isset($_POST['data']['clickwrap']) && 'agreed' !== $_POST['data']['clickwrap']) )
			return new ShoppError(__('You must agree to the terms of sale.','Shopp'),'checkout_validation');

		if ('wordpress' == shopp_setting('account_system') && !$this->Customer->logged_in()) {
			require(ABSPATH."/wp-includes/registration.php");

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
					return new ShoppError(__('A login is not available for creation with the information you provided. Please try a different email address or name, or try logging in if you previously created an account.'),'cart_validation');
			}
			if(SHOPP_DEBUG) new ShoppError('Login set to '. $_POST['loginname'] . ' for WordPress account creation.',false,SHOPP_DEBUG_ERR);
			$ExistingCustomer = new Customer($_POST['email'],'email');
			if ( $this->guest && ! empty($ExistingCustomer->id) ) $this->Customer->id = $ExistingCustomer->id;
			if ( apply_filters('shopp_email_exists', !$this->guest && (email_exists($_POST['email']) || !empty($ExistingCustomer->id))) )
				return new ShoppError(__('The email address you entered is already in use. Try logging in if you previously created an account, or enter another email address to create your new account.','Shopp'),'cart_validation');
		} elseif ('shopp' == shopp_setting('account_system') && !$this->Customer->logged_in()) {
			$ExistingCustomer = new Customer($_POST['email'],'email');
			if (apply_filters('shopp_email_exists',!empty($ExistingCustomer->id)))
				return new ShoppError(__('The email address you entered is already in use. Try logging in if you previously created an account, or enter another email address to create a new account.','Shopp'),'cart_validation');
		}

		// Validate WP account
		if (apply_filters('shopp_login_required',(isset($_POST['loginname']) && empty($_POST['loginname']))))
			return new ShoppError(__('You must enter a login name for your account.','Shopp'),'cart_validation');

		if (isset($_POST['loginname'])) {
			require(ABSPATH."/wp-includes/registration.php");
			if (apply_filters('shopp_login_valid',(!validate_username($_POST['loginname'])))) {
				$sanitized = sanitize_user( $_POST['loginname'], true );
				$illegal = array_diff(str_split($_POST['loginname']),str_split($sanitized));
				return new ShoppError(sprintf(__('The login name provided includes invalid characters: %s','Shopp'),esc_html(join(' ',$illegal))),'cart_validation');
			}

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

		if (apply_filters('shopp_billing_address_required',(empty($this->Billing->address) || strlen($this->Billing->address) < 4)))
			return new ShoppError(__('You must enter a valid street address for your billing information.','Shopp'),'cart_validation');

		if (apply_filters('shopp_billing_postcode_required',empty($this->Billing->postcode)))
			return new ShoppError(__('You must enter a valid postal code for your billing information.','Shopp'),'cart_validation');

		if (apply_filters('shopp_billing_country_required',empty($this->Billing->country)))
			return new ShoppError(__('You must select a country for your billing information.','Shopp'),'cart_validation');

		if (apply_filters('shopp_billing_locale_required',isset($_POST['billing']['locale']) && empty($_POST['billing']['locale'])))
			return new ShoppError(__('You must select a local jursidiction for tax purposes.','Shopp'),'cart_validation');

		// Skip validating payment details for purchases not requiring a
		// payment (credit) card including free orders, remote checkout systems, etc
		if (!$this->paycard()) return apply_filters('shopp_validate_checkout',true);

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
		shopp_redirect( shoppurl(false,'checkout',$this->security()), true );
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
		if ($this->Cart->shipped() && shopp_setting_enabled('shipping')) {
			if (empty($Shipping->address))
				$valid_shipping = apply_filters('shopp_ordering_empty_shipping_address',false);
			if (empty($Shipping->country))
				$valid_shipping = apply_filters('shopp_ordering_empty_shipping_country',false);
			if (empty($Shipping->postcode))
				$valid_shipping = apply_filters('shopp_ordering_empty_shipping_postcode',false);

			if ($Cart->freeshipping === false && $Cart->Totals->shipping === false) {
				$valid = apply_filters('shopp_ordering_no_shipping_costs',false);

				$message = __('The order cannot be processed. No shipping is available to the address you provided. Please return to %scheckout%s and try again.', 'Shopp');

				global $Shopp;
				if ($Shopp->Shipping->realtime)
					$message = __('The order cannot be processed. The shipping rate service did not provide rates because of a problem and no other shipping is available to the address you provided. Please return to %scheckout%s and try again or contact the store administrator.', 'Shopp');

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
		return $Shopp->Gateways->secure || is_ssl();
	}

	/**
	 * Secures the payment card by truncating it to the last four digits
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @return void
	 **/
	function securecard () {
		if (!empty($this->Billing->card) && strlen($this->Billing->card) > 4) {
			$this->Billing->card = substr($this->Billing->card,-4);

			// Card data is truncated, switch the cart to normal mode
			ShoppShopping()->secured(false);
		}
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

} // END class Order
