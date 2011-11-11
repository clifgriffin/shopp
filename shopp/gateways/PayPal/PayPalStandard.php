<?php
/**
 * PayPal Standard
 * @class PayPalStandard
 *
 * @author Jonathan Davis
 * @version 1.1.5
 * @copyright Ingenesis Limited, 27 May, 2009
 * @package Shopp
 * @since 1.1
 * @subpackage PayPalStandard
 *
 * $Id$
 **/

class PayPalStandard extends GatewayFramework implements GatewayModule {

	// Settings
	var $secure = false;
	var $recurring = true;

	// URLs
	var $buttonurl = 'http://www.paypal.com/%s/i/btn/btn_xpressCheckout.gif';
	var $sandboxurl = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
	var $checkouturl = 'https://www.paypal.com/cgi-bin/webscr';

	// Internals
	var $baseop = array();
	var $currencies = array("USD", "AUD", "BRL", "CAD", "CZK", "DKK", "EUR", "HKD", "HUF",
	 						"ILS", "JPY", "MYR", "MXN", "NOK", "NZD", "PHP", "PLN", "GBP",
	 						"SGD", "SEK", "CHF", "TWD", "THB");
	var $locales = array("AT" => "de_DE", "AU" => "en_AU", "BE" => "en_US", "CA" => "en_US",
							"CH" => "de_DE", "CN" => "zh_CN", "DE" => "de_DE", "ES" => "es_ES",
							"FR" => "fr_FR", "GB" => "en_GB", "GF" => "fr_FR", "GI" => "en_US",
							"GP" => "fr_FR", "IE" => "en_US", "IT" => "it_IT", "JP" => "ja_JP",
							"MQ" => "fr_FR", "NL" => "nl_NL", "PL" => "pl_PL", "RE" => "fr_FR",
							"US" => "en_US");
	// status to event mapping
	var $events = array(
						'Expired' => 'voided',
						'Failed' => 'voided',
						'Refunded' => 'voided',
						'Reversed' => 'voided',
						'Voided' => 'voided',
						'Denied' => 'voided',
						'Canceled-Reversal' => 'captured',
						'Completed' => 'captured',
						'Pending' => 'purchase',
						'Processed' => 'purchase',
						);

	function __construct () {
		parent::__construct();

		$this->setup('account','pdtverify','pdttoken','testmode');

		$this->settings['currency_code'] = $this->currencies[0];
		if (in_array($this->baseop['currency']['code'],$this->currencies))
			$this->settings['currency_code'] = $this->baseop['currency']['code'];

		if (array_key_exists($this->baseop['country'],$this->locales))
			$this->settings['locale'] = $this->locales[$this->baseop['country']];
		else $this->settings['locale'] = $this->locales['US'];

		$this->buttonurl = sprintf(force_ssl($this->buttonurl), $this->settings['locale']);

		if (!isset($this->settings['label'])) $this->settings['label'] = "PayPal";

		add_action('shopp_txn_update',array($this,'updates')); // possible IPN updates
		add_filter('shopp_tag_cart_paypal',array($this,'sendcart'),10,2); // provides shopp('cart','paypal') checkout button
		add_filter('shopp_checkout_submit_button',array($this,'submit'),10,3); // replace submit button with paypal image

		// event system callbacks, normal established generally by Order::process()
		add_action('shopp_paypalstandard_captured', array(ShoppOrder(),'accounts')); // account creation
		add_action('shopp_paypalstandard_captured', array(ShoppOrder(),'notify')); // order email notification
	}

	/**
	 * actions
	 *
	 * these action callbacks are only established when the current Order::processor() is set to this module.  All other general actiosn belong in the constructor
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	function actions () {
		add_action('shopp_process_checkout', array($this,'checkout'),9); // intercept checkout request, force confirm
		add_action('shopp_init_confirmation',array($this,'confirmation')); // replace confirm order page with paypal form
		add_action('shopp_remote_payment',array($this,'payment')); // process PDT sync return
		add_action('shopp_init_checkout',array($this,'returned')); // wipes shopping session on thanks page load
		add_action('shopp_process_order',array($this,'process')); // process new order (IPN or PDT)
	}

	/**
	 * confirmation
	 *
	 * replaces the confirm order form to submit cart to PPS
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	function confirmation () {
		add_filter('shopp_confirm_url',array($this,'url'));
		add_filter('shopp_confirm_form',array($this,'form'));
	}

	/**
	 * checkout
	 *
	 * forces the checkout request to go to order confirmation so that the confirm order form can be replaced for PPS
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	function checkout () {
		$this->Order->Billing->cardtype = "PayPal";
		$this->Order->confirm = true;
	}

	/**
	 * submit
	 *
	 * replaces the submit button the checkout form with a PayPal checkout button image
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	function submit ($tag=false,$options=array(),$attrs=array()) {
		$tag[$this->settings['label']] = '<input type="image" name="process" src="'.$this->buttonurl.'" '.inputattrs($options,$attrs).' />';
		return $tag;
	}

	/**
	 * url
	 *
	 * url returns the live or test paypal url, depending on testmode setting
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return string checkout url
	 **/
	function url ($url=false) {
		if ($this->settings['testmode'] == "on") return $this->sandboxurl;
		else return $this->checkouturl;
	}

	/**
	 * sendcart
	 *
	 * builds a form appropriate for sending to PayPal directly from the cart.. used by shopp('cart','paypal')
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return string PayPal cart form
	 **/
	function sendcart () {
		$Order = $this->Order;

		$submit = $this->submit(array());
		$submit = $submit[$this->settings['label']];

		$result = '<form action="'.$this->url().'" method="POST">';
		$result .= $this->form('',array('address_override'=>0));
		$result .= $submit;
		$result .= '</form>';
		return $result;
	}

	/**
	 * form
	 *
	 * Builds a hidden form to submit to PayPal when confirming the order for processing
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @return string PayPal cart form contents
	 **/
	function form ($form,$options=array()) {
		global $Shopp;
		$Shopping = ShoppShopping();
		$Order = $this->Order;

		$_ = array();

		$_['cmd'] 					= "_cart";
		$_['upload'] 				= 1;
		$_['business']				= $this->settings['account'];
		$_['invoice']				= time();
		$_['custom']				= $Shopping->session;

		// Options
		if ($this->settings['pdtverify'] == "on")
			$_['return']			= shoppurl(array('rmtpay'=>'process'),'checkout',false);
		else $_['return']				= shoppurl(false,'thanks');

		$_['cancel_return']			= shoppurl(false,'cart');
		$_['notify_url']			= shoppurl(array('_txnupdate'=>'PPS'),'checkout');
		$_['rm']					= 1; // Return with no transaction data

		// Pre-populate PayPal Checkout
		$_['first_name']			= $Order->Customer->firstname;
		$_['last_name']				= $Order->Customer->lastname;
		$_['lc']					= $this->baseop['country'];
		$_['charset']				= 'utf-8';
		$_['bn']					= 'shopplugin.net[WPS]';

		$AddressType = "Shipping";
		// Disable shipping fields if no shipped items in cart
		if (empty($Order->Cart->shipped)) {
			$AddressType = "Billing";
			$_['no_shipping'] = 1;
		}

		$_['address_override'] 		= 1;
		$_['address1']				= $Order->{$AddressType}->address;
		if (!empty($Order->{$AddressType}->xaddress))
			$_['address2']			= $Order->{$AddressType}->xaddress;
		$_['city']					= $Order->{$AddressType}->city;
		$_['state']					= $Order->{$AddressType}->state;
		$_['zip']					= $Order->{$AddressType}->postcode;
		$_['country']				= $Order->{$AddressType}->country;
		$_['email']					= $Order->Customer->email;

		$phone = parse_phone($Order->Customer->phone);
		if ( in_array($Order->Billing->country,array('US','CA')) ) {
			$_['night_phone_a']			= $phone['area'];
			$_['night_phone_b']			= $phone['prefix'];
			$_['night_phone_c']			= $phone['exchange'];
		} else $_['night_phone_b']		= $phone['raw'];

		// Include page style option, if provided
		if (isset($_GET['pagestyle'])) $_['pagestyle'] = $_GET['pagestyle'];

		// if (isset($Order->data['paypal-custom']))
		// 	$_['custom'] = htmlentities($Order->data['paypal-custom']);

		// Transaction
		$_['currency_code']			= $this->settings['currency_code'];

		if ($Order->Cart->recurring()) {

			$Item = $Order->Cart->recurring[0];
			$_['cmd']	= '_xclick-subscriptions';
			$_['rm']	= 2; // Return with transaction data

			$_['item_number'] = $Item->product;
			$_['item_name'] = $Item->name.((!empty($Item->option->label))?' ('.$Item->option->label.')':'');

			// Trial pricing
			$_['a1']	= number_format($Item->recurring['trialprice'],$this->precision);
			$_['p1']	= $Item->option->recurring['trialint'];
			$_['t1']	= strtoupper($Item->option->recurring['trialperiod']);


			$_['a3']	= number_format($Item->unitprice,$this->precision);
			$_['p3']	= $Item->option->recurring['interval'];
			$_['t3']	= strtoupper($Item->option->recurring['period']);

			$_['src']	= 1;

		} else {

			// Line Items
			foreach($Order->Cart->contents as $i => $Item) {
				$id=$i+1;
				$_['item_number_'.$id]		= $id;
				$_['item_name_'.$id]		= $Item->name.((!empty($Item->option->label))?' '.$Item->option->label:'');
				$_['amount_'.$id]			= number_format($Item->unitprice,$this->precision);
				$_['quantity_'.$id]			= $Item->quantity;
				$_['weight_'.$id]			= $Item->quantity;
			}

			// Workaround a PayPal limitation of not correctly handling no subtotals or
			// handling discounts in the amount of the item subtotals by adding the
			// shipping fee to the line items to get included in the subtotal. If no
			// shipping fee is available use 0.01 to satisfy minimum order amount requirements
			if ((int)$Order->Cart->Totals->subtotal == 0 ||
				$Order->Cart->Totals->subtotal-$Order->Cart->Totals->discount == 0) {
				$id++;
				$_['item_number_'.$id]		= $id;
				$_['item_name_'.$id]		= apply_filters('paypal_freeorder_handling_label',
															__('Shipping & Handling','Shopp'));
				$_['amount_'.$id]			= number_format(max($Order->Cart->Totals->shipping,0.01),$this->precision);
				$_['quantity_'.$id]			= 1;
			} else
				$_['handling_cart']				= number_format($Order->Cart->Totals->shipping,$this->precision);

			$_['discount_amount_cart'] 		= number_format($Order->Cart->Totals->discount,$this->precision);
			$_['tax_cart']					= number_format($Order->Cart->Totals->tax,$this->precision);
			$_['amount']					= number_format($Order->Cart->Totals->total,$this->precision);

		}

		$_ = array_merge($_,$options);

		return $form.$this->format($_);
	}

	/**
	 * payment
	 *
	 * setup valid order override, to force the thanks page regardless of order processing results
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	function payment () {
		if (isset($_REQUEST['tx'])) { // PDT
			add_filter('shopp_valid_order',array($this,'pdtpassthru'));
			// Run order processing
			do_action('shopp_process_order');
		}
	}

	/**
	 * pdtpassthru
	 *
	 * If order data validation fails, causes redirect to thank you page
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return bool valid order
	 **/
	function pdtpassthru ($valid) {
		if ($valid) return $valid;
		// If the order data validation fails, passthru to the thank you page
		shopp_redirect( shoppurl(false,'thanks') );
	}

	/**
	 * returned
	 *
	 * resets shopping session in preparation for loading thanks page
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	function returned () {
		if ( ! is_thanks_page() ) return;

		global $Shopp;

		// Session has already been reset after a processed transaction
		if ( ! empty($Shopp->Purchase->id) ) return;

		// Customer returned from PayPal
		// but no transaction processed yet
		// reset the session to preserve original order
		Shopping::resession();

	}

	/**
	 * process
	 *
	 * the shopp_process_order action of PPS ( see actions() method )
	 * process new orders via PDT synchronous redirect from PayPal or asynchronously from IPN ( shopping session populated by updates() )
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 * @return void
	 **/
	function process () {
		global $Shopp;

		$txnid = false;
		$txnstatus = false;
		$event = 'purchase';

		if (isset($_POST['txn_id'])) { // IPN order processing
			if (SHOPP_DEBUG) new ShoppError('Processing transaction from an IPN message.',false,SHOPP_DEBUG_ERR);
			$txnid = $_POST['txn_id'];
			$txnstatus = $_POST['payment_status'];

		} elseif (isset($_REQUEST['tx'])) { // PDT order processing
			if (SHOPP_DEBUG) new ShoppError('Processing PDT packet: '._object_r($_GET),false,SHOPP_DEBUG_ERR);

			$txnid = $_GET['tx'];
			$txnstatus = $_GET['st'];

			if ($this->settings['pdtverify'] == "on") {
				$pdtstatus = $this->verifypdt();
				if (!$pdtstatus) {
					new ShoppError(__('The transaction was not verified by PayPal.','Shopp'),false,SHOPP_DEBUG_ERR);
					shopp_redirect(shoppurl(false,'checkout',false));
				}
			}

			$Purchase = new Purchase($txnid,'txnid');
			if (!empty($Purchase->id)) {
				if (SHOPP_DEBUG) new ShoppError('Order located, already created from an IPN message.',false,SHOPP_DEBUG_ERR);
				Shopping::resession();


				ShoppPurchase($Purchase);
				ShoppOrder()->purchase = $Purchase->id;
				shopp_redirect(shoppurl(false,'thanks',false));
			}

		}

		if ( $txnstatus && isset($this->events[$txnstatus]) )
			$event = $this->events[$txnstatus];

		if ( $event == 'voided') return; // the transaction is void of the starting gate. Don't create a purchase.

		if (!$txnid) return new ShoppError('No transaction ID was found from either a PDT or IPN message. Transaction cannot be processed.',false,SHOPP_DEBUG_ERR);

		// remove undesirable order creation action on new Order object
		remove_action('shopp_purchase_order_created',array(ShoppOrder(),'process'));

		shopp_add_order_event(false, 'purchase', array(
			'gateway' => $this->module,
			'txnid' => $txnid
		));
	}

	function updates () {
		// update is not for PPS
		if ( 'PPS' != $_REQUEST['_txnupdate'] ) return;

		// Cancel processing if this is not a PayPal IPN message (invalid)
		if (isset($_POST['txn_type']) && $_POST['txn_type'] != "cart") {
			if(SHOPP_DEBUG) new ShoppError('Invalid IPN request.  Incorrect txn_type.','paypal_ipn_invalid',SHOPP_DEBUG_ERR);
			return false;
		}

		global $Shopp;

		$target = false;
		// if no parent transaction id, this is a new transaction
		if ( isset($_POST['txn_id']) && ! isset($_POST['parent_txn_id']) ) {
			$target = $_POST['txn_id'];
		// if a parent transaction id exists, this is associated with our existing purchase
		} elseif ( ! empty($_POST['parent_txn_id']) ) {
			$target = $_POST['parent_txn_id'];
		}

		// No transaction target: invalid IPN, silently ignore the message
		if ( ! $target ) {
			if(SHOPP_DEBUG) new ShoppError("Invalid IPN request.  Missing txn_id or parent_txn_id.",'paypal_ipn_invalid',SHOPP_DEBUG_ERR);
			return;
		}

		// Validate the order notification
		if ( $this->verifyipn() != "VERIFIED" ) {
			new ShoppError(sprintf(__('An unverifiable order update notification was received from PayPal for transaction: %s. Possible fraudulent notification!  The order will not be updated.  IPN message: %s','Shopp'),$target,_object_r($_POST)),'paypal_txn_verification',SHOPP_TRXN_ERR);
			return false;
		}

		$Purchase = new Purchase($target,'txnid');

		// Purchase record exists, update it
		if ( $Purchase->txnid == $target && ! empty($Purchase->id) ) {
			$txnid = $target;
			$txnstatus = $_POST['payment_status'];
			$fee = 0;
			$amount = 0;
			if ( isset($_POST['mc_fee']) ) $fee = abs($_POST['mc_fee']);
			$amount = isset($_POST['mc_gross']) ? abs($_POST['mc_gross']) : $Purchase->total;

			switch ( $this->events($txnstatus) ) {
				case "purchase":
					if(SHOPP_DEBUG) new ShoppError('Ignoring IPN with '.$txnstatus.' status on existing order '.$Purchase->id, 'paypal_invalid_txnstatus', SHOPP_DEBUG_ERR);
					break;
				case "captured":
					shopp_add_order_event($Purchase->id, 'captured', array(
						'txnid' => $txnid,				// Transaction ID of the CAPTURE event
						'amount' => $amount,			// Amount captured
						'fees' => $fee,	// Transaction fees taken by the gateway net revenue = amount-fees
						'gateway' => $this->module		// Gateway handler name (module name from @subpackage)
					));
					break;
				case "refunded":
					shopp_add_order_event($Purchase->id, 'refunded', array(
						'txnid' => $txnid,				// Transaction ID for the REFUND event
						'amount' => $refund,		// Amount refunded
						'gateway' => $this->module	// Gateway handler name (module name from @subpackage)
					));
					break;
				case "voided":
					// void txnid
					$new_txnid = isset($_POST['txn_id']) ? $_POST['txn_id'] : $txnid;

					shopp_add_order_event($Purchase->id, 'voided', array(
						'txnid' => $new_txnid,				// Transaction ID
						'txnorigin' => $txnid,				// Original Transaction ID
						'gateway' => $this->module			// Gateway handler name (module name from @subpackage)
					));
					break;
				default: break; // do nothing
			}

			die('PayPal IPN update processed.');
		}

		// New order creation by IPN
		if (!isset($_POST['custom'])) {
			new ShoppError(sprintf(__('No reference to the pending order was available in the PayPal IPN message. Purchase creation failed for transaction %s.'),$target),'paypalstandard_process_neworder',SHOPP_TRXN_ERR);
			die('PayPal IPN failed.');
		}

		// load the desired session, which leaves the previous/defunct Order object intact
		Shopping::resession($_POST['custom']);

		// destroy the defunct Order object from defunct session and restore the Order object from the loaded session
		// also assign the restored Order object as the global Order object
		$this->Order = ShoppOrder( ShoppingObject::__new('Order', ShoppOrder()) );

		$Shopping = ShoppShopping();

		// Couldn't load the session data
		if ($Shopping->session != $_POST['custom'])
			return new ShoppError("Session could not be loaded: {$_POST['custom']}",false,SHOPP_DEBUG_ERR);
		else new ShoppError("PayPal successfully loaded session: {$_POST['custom']}",false,SHOPP_DEBUG_ERR);

		// process shipping address changes from IPN message
		$this->ipnupdates();

		do_action('shopp_process_order');
		die('PayPal IPN processed.');
	}

	function ipnupdates () {
		$Order = $this->Order;
		$data = stripslashes_deep($_POST);

		$fields = array(
			'Customer' => array(
				'firstname' => 'first_name',
				'lastname' => 'last_name',
				'email' => 'payer_email',
				'phone' => 'contact_phone',
				'company' => 'payer_business_name'
			),
			'Shipping' => array(
				'address' => 'address_street',
				'city' => 'address_city',
				'state' => 'address_state',
				'country' => 'address_country_code',
				'postcode' => 'address_zip'
			)
		);

		foreach ($fields as $Object => $set) {
			$changes = false;
			foreach ($set as $shopp => $paypal) {
				if (isset($data[$paypal]) && (empty($Order->{$Object}->{$shopp}) || $changes)) {
					$Order->{$Object}->{$shopp} = $data[$paypal];
					// If any of the fieldset is changed, change the rest to keep data sets in sync
					$changes = true;
				}
			}
		}
	}

	function verifyipn () {
		if ($this->settings['testmode'] == "on") return "VERIFIED";
		$_ = array();
		$_['cmd'] = "_notify-validate";

		$message = $this->encode(array_merge($_POST,$_));
		$response = $this->send($message);
		if (SHOPP_DEBUG) new ShoppError('PayPal IPN notification verfication response received: '.$response,'paypal_standard',SHOPP_DEBUG_ERR);
		return $response;
	}

	function verifypdt () {
		if ($this->settings['pdtverify'] != "on") return false;
		if ($this->settings['testmode'] == "on") return "VERIFIED";
		$_ = array();
		$_['cmd'] = "_notify-synch";
		$_['at'] = $this->settings['pdttoken'];
		$_['tx'] = $_GET['tx'];

		$message = $this->encode($_);
		$response = $this->send($message);
		return (strpos($response,"SUCCESS") !== false);
	}

	function error () {
		if (!empty($this->Response)) {

			$message = join("; ",$this->Response->l_longmessage);
			if (empty($message)) return false;
			return new ShoppError($message,'paypal_express_transacton_error',SHOPP_TRXN_ERR,
				array('code'=>$code));
		}
	}

	function send ($data, $url=false, $deprecated=false, $options = array()) {
		return parent::send($data,$this->url());
	}

	function settings () {

		$this->ui->text(0,array(
			'name' => 'account',
			'value' => $this->settings['account'],
			'size' => 30,
			'label' => __('Enter your PayPal account email.','Shopp')
		));

		$this->ui->checkbox(0,array(
			'name' => 'pdtverify',
			'checked' => $this->settings['pdtverify'],
			'label' => __('Enable order verification','Shopp')
		));

		$this->ui->text(0,array(
			'name' => 'pdttoken',
			'size' => 30,
			'value' => $this->settings['pdttoken'],
			'label' => __('PDT identity token for validating orders.','Shopp')
		));

		$this->ui->checkbox(0,array(
			'name' => 'testmode',
			'label' => sprintf(__('Use the %s','Shopp'),'<a href="http://docs.shopplugin.net/PayPal_Sandbox" target="shoppdocs">PayPal Sandbox</a>'),
			'checked' => $this->settings['testmode']
		));

		$this->ui->behaviors($this->tokenjs());

	}

	function tokenjs () {
		ob_start(); ?>
jQuery(document).bind('paypalstandardSettings',function() {
	var $ = jqnc(),p = '#paypalstandard-pdt',v = $(p+'verify'),t = $(p+'token');
	v.change(function () { v.attr('checked')? t.parent().fadeIn('fast') : t.parent().hide(); }).change();
});
<?php
		$script = ob_get_contents(); ob_end_clean();
		return $script;
	}

} // END class PayPalStandard

?>