<?php
/**
 * PayPal Express
 * @class PayPalExpress
 *
 * @author Jonathan Davis
 * @version 1.1.5
 * @copyright Ingenesis Limited, 26 August, 2008
 * @package shopp
 * @since 1.1
 * @subpackage PayPalExpress
 *
 * $Id$
 **/

class PayPalExpress extends GatewayFramework implements GatewayModule {

	// Settings
	var $secure = false;
	var $refunds = true;
	var $authonly = true;

	// URLs
	var $buttonurl = 'http://www.paypal.com/%s/i/btn/btn_xpressCheckout.gif';
	var $sandboxurl = 'https://www.sandbox.paypal.com/%s/cgi-bin/webscr?cmd=_express-checkout';
	var $liveurl = 'https://www.paypal.com/%s/cgi-bin/webscr?cmd=_express-checkout';
	var $sandboxapi = 'https://api-3t.sandbox.paypal.com/nvp';
	var $liveapi = 'https://api-3t.paypal.com/nvp';

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
	var $status = array(
					'Pending' => 'authed',
					'Processed' => 'authed',
					'Canceled-Reversal' => 'captured',
					'Completed' => 'captured',
					'Refunded' => 'refund',
					'Reversed' => 'refund',
					'Denied' => 'void',
					'Expired' => 'void',
					'Failed' => 'void',
					'Voided' => 'void',
				);

	var $shiprequired = array('en_GB');

	function __construct () {
		parent::__construct();

		$this->setup('username','password','signature','testmode');

		$this->settings['currency_code'] = $this->currencies[0];
		if (in_array($this->baseop['currency']['code'],$this->currencies))
			$this->settings['currency_code'] = $this->baseop['currency']['code'];

		if (array_key_exists($this->baseop['country'],$this->locales))
			$this->settings['locale'] = $this->locales[$this->baseop['country']];
		else $this->settings['locale'] = $this->locales['US'];

		$this->buttonurl = sprintf(force_ssl($this->buttonurl), $this->settings['locale']);
		$this->sandboxurl = sprintf($this->sandboxurl, $this->settings['locale']);
		$this->liveurl = sprintf($this->liveurl, $this->settings['locale']);

		if (!isset($this->settings['label'])) $this->settings['label'] = "PayPal";

		add_action('shopp_txn_update',array($this,'updates'));
		add_filter('shopp_tag_cart_paypalexpress',array($this,'cartcheckout'),10,2);
		add_filter('shopp_checkout_submit_button',array($this,'submit'),10,3);

		// Order Event Handlers
		add_action('shopp_paypalexpress_auth', array($this, 'DoExpressCheckoutPayment'));
		add_action('shopp_paypalexpress_authed', array($this, 'capture'));
		add_action('shopp_paypalexpress_capture', array($this, 'capture'));
		add_action('shopp_paypalexpress_refund', array($this, 'RefundTransaction'));
	}

	function actions () {
		add_action('shopp_checkout_processed', array($this, 'checkout'));
		add_action('shopp_init_confirmation', array($this, 'GetExpressCheckoutDetails'));
		add_action('shopp_remote_payment', array($this, 'returned'));
	}


	function submit ($tag=false,$options=array(),$attrs=array()) {
		$tag[$this->settings['label']] = '<input type="image" name="process" src="'.$this->buttonurl.'" class="checkout-button" '.inputattrs($options,$attrs).' />';
		return $tag;
	}

	function url ($url=false) {
		if ($this->settings['testmode'] == "on") return $this->sandboxurl;
		else return $this->liveurl;
	}

	function apiurl ($url=false) {
		if ($this->settings['testmode'] == "on") return $this->sandboxapi;
		else return $this->liveapi;
	}

	function returned () {

		if (!empty($_GET['token'])) $this->Order->token = $_GET['token'];
		if (!empty($_GET['PayerID'])) $this->Order->payerid = $_GET['PayerID'];

		if ($_POST['checkout'] == "confirmed") do_action('shopp_confirm_order');
	}


	function notax ($rate) { return false; }

	function headers () {
		$_ = array();

		$_['USER'] 					= $this->settings['username'];
		$_['PWD'] 					= $this->settings['password'];
		$_['SIGNATURE']				= $this->settings['signature'];
		$_['VERSION']				= "84.0";

		return $_;
	}

	function RefundTransaction ( RefundOrderEvent $Event ) {
		$Purchase = shopp_order($Event->order);

		$_ = $this->headers();
		$_['METHOD'] = 'RefundTransaction';
		$_['TRANSACTIONID'] = $Event->txnid;
		$RefundType = 'Full';
		if ( ! $Purchase->refunded && $Event->amount < $Purchase->total ) {
			$RefundType = 'Partial';
			$_['AMT'] = number_format($Event->amount, $this->precision);;
			$_['CURRENCYCODE']			= $this->settings['currency_code'];
		}
		$_['REFUNDTYPE'] = $RefundType;
		if ( $Event->reason ) $_['NOTE'] = $Event->reason;
		$message = $this->encode($_);
		$response = $this->send($message);

		if ( 'Success' != $response->ack ) {
			if(SHOPP_DEBUG) new ShoppError('In '.__FUNCTION__.': '.$response->debuglog, 'debug'.__FUNCTION__, SHOPP_DEBUG_ERR);
		}

		if ( 'Success' == $response->ack || 'SuccessWithWarning' == $response->ack ) {
			shopp_add_order_event ( $Event->order, 'refunded', array(
				'txnid' 	=> $response->refundtransactionid, 			// Transaction ID for the REFUND event
				'amount' 	=> floatvalue($response->grossrefundamt),	// Amount refunded
				'gateway' 	=> $Event->gateway							// Gateway handler name (module name from @subpackage)
			));
			return;
		}

		shopp_add_order_event ( $Event->order, 'refund-fail', array(
			'amount' 	=> $Event->amount,				// Amount to be refunded
			'error' 	=> $response->l_errorcode0,		// Error code (if provided)
			'message' 	=> $response->l_shortmessage0,	// Error message reported by the gateway
			'gateway' 	=> $Event->gateway				// Gateway handler name (module name from @subpackage)
		));

	}

	function cancelorder ( RefundedOrderEvent $Refunded ) {
		$order = $Refunded->order;
		$Purchase = shopp_order($order);
		if ($Refunded->amount != $Purchase->total) return;

		// If not a partial refund, cancel the remaining balance
		shopp_add_order_event($order,'voided',array(
			'txnorigin' => $Refunded->txnid,
			'txnid' => $Purchase->txnid,
			'gateway' => $this->module
		));
	}


	function purchase () {
		// Localize Order objects
		$Order = $this->Order;
		$Customer = $Order->Customer;
		$Billing = $Order->Billing;
		$Shipping = $Order->Shipping;
		$Cart = $Order->Cart;
		$Totals = $Order->Cart->Totals;

		$_ = array();

		$_['EMAIL']							= $Customer->email;
		$_['SHIPTOPHONENUM']				= $Customer->phone;

		// Shipping address override
		if (!empty($Shipping->address) && !empty($Shipping->postcode)) {
			$_['ADDROVERRIDE'] 							= 1;
			$_['PAYMENTREQUEST_0_SHIPTOSTREET'] 		= $Shipping->address;
			if (!empty($Shipping->xaddress))
				$_['PAYMENTREQUEST_0_SHIPTOSTREET2']	= $Shipping->xaddress;
			$_['PAYMENTREQUEST_0_SHIPTOCITY']		= $Shipping->city;
			$_['PAYMENTREQUEST_0_SHIPTOSTATE']		= $Shipping->state;
			$_['PAYMENTREQUEST_0_SHIPTOZIP']			= $Shipping->postcode;
			$_['PAYMENTREQUEST_0_SHIPTOCOUNTRYCODE']		= $Shipping->country;
		}

		if (empty($this->Order->Cart->shipped) &&
			!in_array($this->settings['locale'],$this->shiprequired)) $_['NOSHIPPING'] = 1;

		// Transaction
		$_['PAYMENTREQUEST_0_CURRENCYCODE']			= $this->settings['currency_code'];
		$_['PAYMENTREQUEST_0_AMT']					= number_format($Totals->total,$this->precision);
		$_['PAYMENTREQUEST_0_ITEMAMT']				= number_format(round($Totals->subtotal,$this->precision) -
														round($Totals->discount,$this->precision),$this->precision);
		$_['PAYMENTREQUEST_0_SHIPPINGAMT']			= number_format($Totals->shipping,$this->precision);
		$_['PAYMENTREQUEST_0_TAXAMT']				= number_format($Totals->tax,$this->precision);

		if (isset($Order->data['paypal-custom']))
			$_['CUSTOM'] = htmlentities($Order->data['paypal-custom']);

		// Line Items
		foreach($Cart->contents as $i => $Item) {
			$_['L_PAYMENTREQUEST_0_NAME'.$i]		= $Item->name.((!empty($Item->optionlabel))?' '.$Item->optionlabel:'');
			$_['L_PAYMENTREQUEST_0_AMT'.$i]			= number_format($Item->unitprice,$this->precision);
			$_['L_PAYMENTREQUEST_0_NUMBER'.$i]		= $i;
			$_['L_PAYMENTREQUEST_0_QTY'.$i]			= $Item->quantity;
			$_['L_PAYMENTREQUEST_0_TAXAMT'.$i]		= number_format(0,$this->precision);
		}

		if ($Totals->discount != 0) {
			$discounts = array();
			foreach($Cart->discounts as $discount)
				$discounts[] = $discount->name;

			$i++;
			$_['L_PAYMENTREQUEST_0_NUMBER'.$i]		= $i;
			$_['L_PAYMENTREQUEST_0_NAME'.$i]		= htmlentities(join(", ",$discounts));
			$_['L_PAYMENTREQUEST_0_AMT'.$i]			= number_format($Totals->discount*-1,$this->precision);
			$_['L_PAYMENTREQUEST_0_QTY'.$i]			= 1;
			$_['L_PAYMENTREQUEST_0_TAXAMT'.$i]		= number_format(0,$this->precision);
		}

		return $_;
	}

	function SetExpressCheckout () {
		$_ = $this->headers();

		// Options
		$_['METHOD']							= "SetExpressCheckout";
		$_['PAYMENTREQUEST_0_PAYMENTACTION']	= "Sale";
		$_['LANDINGPAGE']						= "Billing";

		// Include page style option, if provided
		if (isset($_GET['pagestyle'])) $_['PAGESTYLE'] = $_GET['pagestyle'];

		if (isset($this->Order->data['paypal-custom']))
			$_['CUSTOM'] = htmlentities($this->Order->data['paypal-custom']);

		$_['RETURNURL']			= shoppurl(array('rmtpay'=>'process'),'confirm');

		$_['CANCELURL']			= shoppurl(false,'cart');

		$_ = array_merge($_,$this->purchase());

		$message = $this->encode($_);
		return $this->send($message);
	}

	function checkout () {
		if ( $this->Order->Cart->orderisfree() ) return;

		$response = $this->SetExpressCheckout();
		// if(SHOPP_DEBUG) new ShoppError(_object_r($response),false,SHOPP_DEBUG_ERR);
		if ( 'Success' != $response->ack ) {
			if(SHOPP_DEBUG) new ShoppError('In '.__FUNCTION__.': '.$response->debuglog, 'debug'.__FUNCTION__, SHOPP_DEBUG_ERR);
		}

		if ( 'Success' != $response->ack && 'SuccessWithWarning' != $response->ack ) {
			$message = join("; ", $response->longmessages);
			if (empty($message)) $message = __('The transaction failed for an unknown reason. PayPal did not provide any indication of why it failed.','Shopp');
			new ShoppError($message,'paypal_express_transacton_error',SHOPP_TRXN_ERR,array('codes'=>join('; ',$response->errorcode)));
			shopp_redirect(shoppurl(false,'checkout'));
		}

		if (!empty($response) && isset($response->token))
			shopp_redirect(add_query_arg('token',$response->token,$this->url()));

		return false;
	}

	function cartcheckout () {
		if ( $this->Order->Cart->orderisfree() ) return;

		$Order = $this->Order;

		$response = $this->SetExpressCheckout();

		if ( 'Success' != $response->ack ) {
			if(SHOPP_DEBUG) new ShoppError('In '.__FUNCTION__.': '.$response->debuglog, 'debug'.__FUNCTION__, SHOPP_DEBUG_ERR);
		}

		if ( 'Success' != $response->ack && 'SuccessWithWarning' != $response->ack ) {
			$message = join("; ", $response->longmessages);
			if (empty($message)) $message = __('The transaction failed for an unknown reason. PayPal did not provide any indication of why it failed.','Shopp');
			new ShoppError($message,'paypal_express_transacton_error',SHOPP_TRXN_ERR,array('codes'=>join('; ',$response->errorcode)));
			shopp_redirect(shoppurl(false,'checkout'));
		}

		$action = add_query_arg('token',$response->token,$this->url());

		$submit = $this->submit(array());
		$submit = $submit[$this->settings['label']];

		$result = '<form action="'.esc_attr($action).'" method="POST">';
		$result .= $submit;
		$result .= '</form>';
		return $result;
	}

	function GetExpressCheckoutDetails () {
		$Order = $this->Order;

		if (!isset($Order->token) || !isset($Order->payerid)) return false;

		$_ = $this->headers();

   		$_['METHOD'] 				= "GetExpressCheckoutDetails";
		$_['TOKEN'] 				= $Order->token;

		// Get transaction details
		$response = false;
		for ($attempts = 0; $attempts < 2 && !$response; $attempts++) {
			$message = $this->encode($_);
			$response = $this->send($message);
		}

		$fields = array(
			'Customer' => array(
				'firstname' => 'firstname',
				'lastname' => 'lastname',
				'email' => 'email',
				'phone' => 'phonenum',
				'company' => 'payerbusiness'
			),
			'Shipping' => array(
				'address' => 'shiptostreet',
				'xaddress' => 'shiptostreet2',
				'city' => 'shiptocity',
				'state' => 'shiptostate',
				'country' => 'shiptocountrycode',
				'postcode' => 'shiptozip'
			)
		);


		foreach ($fields as $Object => $set) {
			$changes = false;
			foreach ($set as $shopp => $paypal) {
				if (isset($response->{$paypal}) && (empty($Order->{$Object}->{$shopp}) || $changes)) {
					$Order->{$Object}->{$shopp} = $response->{$paypal};
					// If any of the fieldset is changed, change the rest to keep data sets in sync
					$changes = true;
				}
			}
		}

		if (empty($Order->Shipping->state) && empty($Order->Shipping->country))
			add_filter('shopp_cart_taxrate',array($this,'notax'));

		$targets = shopp_setting('target_markets');
		if (!in_array($Order->Shipping->country,array_keys($targets))) {
			new ShoppError(__('The location you are purchasing from is outside of our market regions. This transaction cannot be processed.','Shopp'),'paypalexpress_market',SHOPP_TRXN_ERR);
			shopp_redirect(shoppurl(false,'checkout'));
		}

	}

	function DoExpressCheckoutPayment ( $Event ) {
		$Purchase = shopp_order($Event->order);

		if (!isset($this->Order->token) ||
			!isset($this->Order->payerid)) return false;

		$_ = $this->headers();

		$_['METHOD'] 							= "DoExpressCheckoutPayment";
		$_['PAYMENTREQUEST_0_PAYMENTACTION']	= "Sale";
		$_['TOKEN'] 							= $this->Order->token;
		$_['PAYERID'] 							= $this->Order->payerid;
		$_['BUTTONSOURCE']						= 'shopplugin.net[PPE]';

		// Transaction
		$_ = array_merge($_,$this->purchase());

		$message = $this->encode($_);
		$response = $this->send($message);

		if (!$response) {
			new ShoppError(__('No response was received from PayPal. The order cannot be processed.','Shopp'),'paypalexpress_noresults',SHOPP_COMM_ERR);
			shopp_redirect(shoppurl(false,'checkout'));
		}

		if ( 'Success' != $response->ack ) {
			if(SHOPP_DEBUG) new ShoppError('In '.__FUNCTION__.': '.$response->debuglog, 'debug'.__FUNCTION__, SHOPP_DEBUG_ERR);
		}

		if ( 'Success' != $response->ack && 'SuccessWithWarning' != $response->ack ) {
			$message = join("; ", $response->longmessages);
			if (empty($message)) $message = __('The transaction failed for an unknown reason. PayPal did not provide any indication of why it failed.','Shopp');
			new ShoppError($message,'paypal_express_transacton_error',SHOPP_TRXN_ERR,array('codes'=>join('; ',$response->errorcode)));
			shopp_redirect(shoppurl(false,'checkout'));
		}

		$txnid = $response->paymentinfo_0_transactionid;
		$status = $this->status[$response->paymentinfo_0_paymentstatus];

		if ( 'captured' == $status || 'authed' == $status ) {
			$amount = floatvalue($response->paymentinfo_0_amt);
			$fees = floatvalue($response->paymentinfo_0_feeamt);
			$type = $response->paymentinfo_0_paymenttype;

			shopp_add_order_event ( $Event->order, 'authed', array(
				'txnid' 	=> $txnid,						// Transaction ID
				'amount' 	=> $amount,						// Amount captured
				'fees' 		=> $fees,						// Transaction fees taken by the gateway net revenue = amount-fees
				'gateway' 	=> $Event->gateway,				// Gateway handler name (module name from @subpackage)
				'paymethod' => $this->settings['label'],	// Payment method (payment method label from payment settings)
				'paytype' 	=> $type,						// Type of payment (check, MasterCard, etc)
				'payid' 	=> '',							// Payment ID (last 4 of card or check number)
				'capture' 	=> ( 'captured' == $status )	// Captured payment flag
			));
		}

	}

	function capture ( $Event ) {
		if ( is_a( $Event, 'AuthedOrderEvent' ) && $Event->capture || is_a( $Event, 'CaptureOrderEvent' ) && ! $Event->user ) {
			shopp_add_order_event ( $Event->order, 'captured', array(
				'txnid' => $Event->txnid,		// Transaction ID
				'amount' => $Event->amount,		// Amount captured
				'fees' => $Event->fees,			// Transaction fees taken by the gateway net revenue = amount-fees
				'gateway' => $Event->gateway,	// Gateway handler name (module name from @subpackage)
			));
		}
	}

	function updates () {
		global $Shopp;

		// Cancel processing if this is not a PayPal Website Payments Standard/Express Checkout IPN
		if (isset($_POST['txn_type']) && $_POST['txn_type'] != "cart") return false;

		$target = isset($_POST['parent_txn_id'])?$_POST['parent_txn_id']:$_POST['txn_id'];

		$Purchase = shopp_order( $target,'trans' );

		if ( $Purchase->txnid != $target || empty($Purchase->id) ) return; // No Purchase found to update
		if ( $Purchase->gateway != $this->module ) return; // Not a PPE order, don't touch it

		// Validate the order notification
		if ($this->verifyipn() != "VERIFIED") {
			new ShoppError(sprintf(__('An unverifiable order update notification was received from PayPal for transaction: %s. Possible fraudulent notification!  The order will not be updated.  IPN message: %s','Shopp'),$target,_object_r($_POST)),'paypal_txn_verification',SHOPP_TRXN_ERR);
			return false;
		}

		$txnstatus = $this->status[$_POST['payment_status']];
		if ( ! $txnstatus || $Purchase->txnstatus == $txnstatus ) return; // no change

		$fees = 0;
		$amount = 0;
		if ( isset($_POST['mc_fee']) ) $fees = abs($_POST['mc_fee']);
		$amount = isset($_POST['mc_gross']) ? abs($_POST['mc_gross']) : $Purchase->total;

		if ( 'captured' == $txnstatus ) {
			shopp_add_order_event ( $Purchase->id, 'capture', array(
				'txnid' => $Purchase->txnid,	// Transaction ID of the prior AuthedOrderEvent
				'gateway' => $Purchase->gateway,// Gateway (class name) to process capture through
				'fees' => $fees,				// Transaction fees taken by the gateway net revenue = amount-fees
				'amount' => $amount,			// Amount to capture (charge)
				'user' => 0						// User for user-initiated captures
			));
		}

		if ( 'refund' == $txnstatus ) {
			shopp_add_order_event ( $Purchase->id, 'refunded', array(
				'txnid' 	=> $_POST['txn_id'], 	// Transaction ID for the REFUND event
				'amount' 	=> $amount,				// Amount refunded
				'gateway' 	=> $Purchase->gateway	// Gateway handler name (module name from @subpackage)
			));
		}

		if ( 'void' == $txnstatus ) {
			shopp_add_order_event ( $Purchase->id, 'voided', array(
			'txnorigin' => $Purchase->txnid,		// Original transaction ID (txnid of original Purchase record)
			'txnid' 	=> $_POST['txn_id'],		// Transaction ID for the VOID event
			'gateway' 	=> $Purchase->gateway		// Gateway handler name (module name from @subpackage)
			));
		}

		if (SHOPP_DEBUG) new ShoppError('PayPal IPN update processed for transaction: '.$target,false,SHOPP_DEBUG_ERR);

		die('PayPal IPN update processed.');
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

	function send ($message) {
		if(SHOPP_DEBUG) new ShoppError('message: '._object_r($this->response($message)),false,SHOPP_DEBUG_ERR);
		$response = parent::send($message,$this->apiurl());
		return $this->response($response);
	}

	function response ($buffer) {
		$_ = new stdClass();
		$r = array();
		$pairs = explode("&",$buffer);
		foreach($pairs as $pair) {
			list($key,$value) = explode("=",$pair);
			if (preg_match("/l_(\w+?)(\d+)/i",$key,$matches)) {
				// Capture line item data into an array structure
				if (!isset($r[$matches[1]])) $r[$matches[1]] = array();
				// Skip non-line item data
				if (is_array($r[$matches[1]])) $r[$matches[1]][$matches[2]] = urldecode($value);
			} else $r[$key] = urldecode($value);
		}

		// Remap array to object
		foreach ($r as $key => $value) {
			if (empty($key)) continue;
			$key = strtolower($key);
			$_->{$key} = $value;
		}

		$i = 0;
		if ( isset($response->correlationid) ) {
			$_->debuglog = "CorrelationID: $response->correlationid ";
		}
		while ( isset($_->{"l_errorcode$i"}) ) {
			if ( ! isset($_->errorcodes) ) $_->errorcodes = array();
			if ( ! isset($_->shortmessages) ) $_->errorcodes = array();
			if ( ! isset($_->longmessages) ) $_->errorcodes = array();

			$_->errorcodes[$i] = $_->{"l_errorcode$i"};
			$_->debuglog .= "Code: ".$_->errorcodes[$i]." ";

			$_->shortmessages[$i] = $_->{"l_shortmessage$i"};
			$_->debuglog .= "Short: ".$_->shortmessages[$i]." ";

			$_->longmessages[$i] = $_->{"l_longmessage$i"};
			$_->debuglog .= "Long: ".$_->longmessages[$i]."; ";

			$i++;
		}
		if(SHOPP_DEBUG) new ShoppError(_object_r($_),false,SHOPP_DEBUG_ERR);

		return $_;
	}

	function settings () {
		$this->ui->text(0,array(
			'name' => 'username',
			'size' => 30,
			'value' => $this->settings['username'],
			'label' => __('Enter your PayPal API Username.','Shopp')
		));

		$this->ui->password(0,array(
			'name' => 'password',
			'size' => 16,
			'value' => $this->settings['password'],
			'label' => __('Enter your PayPal API Password.','Shopp')
		));

		$this->ui->text(0,array(
			'name' => 'signature',
			'size' => 48,
			'value' => $this->settings['signature'],
			'label' => __('Enter your PayPal API Signature.','Shopp')
		));

		$this->ui->checkbox(0,array(
			'name' => 'testmode',
			'checked' => $this->settings['testmode'],
			'label' => sprintf(__('Use the %s','Shopp'),'<a href="http://docs.shopplugin.net/PayPal_Sandbox" target="shoppdocs">PayPal Sandbox</a>')
		));
	}

} // END class PayPalExpress

?>