<?php
/**
 * PayPal Express
 * @class PayPalExpress
 *
 * @author Jonathan Davis
 * @version 1.1
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

	// URLs
	var $buttonurl = 'http://www.paypal.com/%s/i/btn/btn_xpressCheckout.gif';
	var $sandboxurl = 'https://www.sandbox.paypal.com/%s/cgi-bin/webscr?cmd=_express-checkout';
	var $liveurl = 'https://www.paypal.com/%s/cgi-bin/webscr?cmd=_express-checkout';
	var $sandboxapi = 'https://api-3t.sandbox.paypal.com/nvp';
	var $liveapi = 'https://api-3t.paypal.com/nvp';

	// Internals
	var $baseop = array();
	var $currencies = array("USD", "AUD", "CAD", "CHF", "CZK", "DKK", "EUR", "GBP", 
							"HKD", "HUF", "JPY", "NOK", "NZD", "PLN", "SEK", "SGD");
	var $locales = array("AT" => "de_DE", "AU" => "en_AU", "BE" => "en_US", "CA" => "en_US",
							"CH" => "de_DE", "CN" => "zh_CN", "DE" => "de_DE", "ES" => "es_ES",
							"FR" => "fr_FR", "GB" => "en_GB", "GF" => "fr_FR", "GI" => "en_US",
							"GP" => "fr_FR", "IE" => "en_US", "IT" => "it_IT", "JP" => "ja_JP",
							"MQ" => "fr_FR", "NL" => "nl_NL", "PL" => "pl_PL", "RE" => "fr_FR",
							"US" => "en_US");
	var $status = array('' => 'UNKNOWN','Canceled-Reversal' => 'CHARGED','Completed' => 'CHARGED', 
						'Denied' => 'VOID', 'Expired' => 'VOID','Failed' => 'VOID','Pending' => 'PENDING',
						'Refunded' => 'VOID','Reversed' => 'VOID','Processed' => 'PENDING','Voided' => 'VOID');

	var $shiprequired = array('en_GB');

	function __construct () {
		parent::__construct();
		
		$this->setup('username','password','signature','testmode');
		
		global $Shopp;
		$this->baseop = $Shopp->Settings->get('base_operations');
		
		$this->settings['currency_code'] = $this->currencies[0];
		if (in_array($this->baseop['currency']['code'],$this->currencies))
			$this->settings['currency_code'] = $this->baseop['currency']['code'];

		if (array_key_exists($this->baseop['country'],$this->locales))
			$this->settings['locale'] = $this->locales[$this->baseop['country']];
		else $this->settings['locale'] = $this->locales['US'];

		$this->buttonurl = sprintf($this->buttonurl, $this->settings['locale']);
		$this->sandboxurl = sprintf($this->sandboxurl, $this->settings['locale']);
		$this->liveurl = sprintf($this->liveurl, $this->settings['locale']);

		if (!isset($this->settings['label'])) $this->settings['label'] = "PayPal";

		add_action('shopp_txn_update',array(&$this,'updates'));
	}
	
	function actions () {
		add_action('shopp_checkout_processed', array(&$this,'checkout'));
		add_action('shopp_init_checkout',array(&$this,'init'));

		add_action('shopp_init_confirmation',array(&$this,'confirmation'));
		add_action('shopp_remote_payment',array(&$this,'returned'));
		add_action('shopp_process_order',array(&$this,'process'));
	}
	
	function init () {
		add_filter('shopp_checkout_submit_button',array(&$this,'submit'),10,3);
	}
	
	function submit ($tag=false,$options=array(),$attrs=array()) {
		return '<input type="image" name="process" src="'.$this->buttonurl.'" id="checkout-button" '.inputattrs($options,$attrs).' />';
	}
	
	function url ($url=false) {
		if ($this->settings['testmode'] == "on") return $this->sandboxurl;
		else return $this->liveurl;
	}

	function api ($url=false) {
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
		$_['VERSION']				= "53.0";

		return $_;
	}
	
	function purchase () {
		global $Shopp;
		$_ = array();
		
		// Transaction
		$_['CURRENCYCODE']			= $this->settings['currency_code'];
		$_['AMT']					= number_format($this->Order->Cart->Totals->total,2);
		$_['ITEMAMT']				= number_format($this->Order->Cart->Totals->subtotal - 
													$this->Order->Cart->Totals->discount,2);
		$_['SHIPPINGAMT']			= number_format($this->Order->Cart->Totals->shipping,2);
		$_['TAXAMT']				= number_format($this->Order->Cart->Totals->tax,2);


		$_['EMAIL']					= $this->Customer->email;
		$_['PHONENUM']				= $this->Order->Customer->phone;
		
		// Shipping address override
		if (!empty($this->Order->Shipping->address) && !empty($this->Order->Shipping->postcode)) {
			$_['ADDRESSOVERRIDE'] = 1;
			$_['SHIPTOSTREET'] 		= $this->Order->Shipping->address;
			if (!empty($this->Order->Shipping->xaddress))
				$_['SHIPTOSTREET2']	= $this->Order->Shipping->xaddress;
			$_['SHIPTOCITY']		= $this->Order->Shipping->city;
			$_['SHIPTOSTATE']		= $this->Order->Shipping->state;
			$_['SHIPTOZIP']			= $this->Order->Shipping->postcode;
			$_['SHIPTOCOUNTRY']		= $this->Order->Shipping->country;
		}
		
		if (empty($this->Order->Cart->shipped) && 
			!in_array($this->settings['locale'],$this->shiprequired)) $_['NOSHIPPING'] = 1;

		// Line Items
		foreach($this->Order->Cart->contents as $i => $Item) {
			$_['L_NUMBER'.$i]		= $i;
			$_['L_NAME'.$i]			= $Item->name.((!empty($Item->optionlabel))?' '.$Item->optionlabel:'');
			$_['L_AMT'.$i]			= number_format($Item->unitprice,2);
			$_['L_QTY'.$i]			= $Item->quantity;
			$_['L_TAXAMT'.$i]		= number_format($Item->taxes,2);
		}
		
		if ($this->Order->Cart->Totals->discount != 0) {
			$discounts = array();
			foreach($this->Order->Cart->discounts as $discount)
				$discounts[] = $discount->name;
			
			$i++;
			$_['L_NUMBER'.$i]		= $i;
			$_['L_NAME'.$i]			= join(", ",$discounts);
			$_['L_AMT'.$i]			= number_format($this->Order->Cart->Totals->discount*-1,2);
			$_['L_QTY'.$i]			= 1;
			$_['L_TAXAMT'.$i]		= number_format(0,2);
		}
		
		return $_;
	}
		
	function checkout () {
		global $Shopp;
		
		$_ = $this->headers();

		// Options
		$_['METHOD']				= "SetExpressCheckout";
		$_['PAYMENTACTION']			= "Sale";
		$_['LANDINGPAGE']			= "Billing";

		// Include page style option, if provided
		if (isset($_GET['pagestyle'])) $_['PAGESTYLE'] = $_GET['pagestyle'];

		if (isset($this->Order->data['paypal-custom']))
			$_['CUSTOM'] = htmlentities($this->Order->data['paypal-custom']);

		$_['RETURNURL']			= add_query_arg('rmtpay','process',$Shopp->link('confirm-order'));

		$_['CANCELURL']			= $Shopp->link('cart');
		
		$_ = array_merge($_,$this->purchase());
		
		$message = $this->encode($_);
		$response = $this->send($message);
		
		if ($response->ack == "Failure") {
			
		}

		if (!empty($response) && isset($response->token))
			shopp_redirect(add_query_arg('token',$response->token,$this->url()));
		
		return false;	
	}
	
	function confirmation () {
		global $Shopp;
		if (!isset($this->Order->token) 
			|| !isset($this->Order->payerid)) return false;
		
		$_ = $this->headers();

   		$_['METHOD'] 				= "GetExpressCheckoutDetails";
		$_['TOKEN'] 				= $this->Order->token;

		// Get transaction details
		$response = false;
		for ($attempts = 0; $attempts < 2 && !$response; $attempts++) {
			$message = $this->encode($_);
			$response = $this->send($message);
		}
	
		$Customer = $this->Order->Customer;
		$Customer->firstname = $response->firstname;
		$Customer->lastname = $response->lastname;
		$Customer->email = $response->email;
		$Customer->phone = $response->phonenum;
		
		$Shipping = &$this->Order->Shipping;		
		$Shipping->address = $response->shiptostreet;
		$Shipping->xaddress = $response->shiptostreet2;
		$Shipping->city = $response->shiptocity;
		$Shipping->state = $response->shiptostate;
		$Shipping->country = $response->shiptocountrycode;
		$Shipping->postcode = $response->shiptozip;
		
		if (empty($Shipping->state) && empty($Shipping->country))
			add_filter('shopp_cart_taxrate',array(&$this,'notax'));
					
		$targets = $Shopp->Settings->get('target_markets');
		if (!in_array($this->Order->Billing->country,array_keys($targets))) {
			new ShoppError(__('The location you are purchasing from is outside of our market regions. This transaction cannot be processed.','Shopp'),'paypalexpress_market',SHOPP_TRXN_ERR);
			shopp_redirect($Shopp->link('checkout'));
		}
		
	} 
	
	function process () {
		global $Shopp;
		error_log('process '.$this->Order->token);
		if (!isset($this->Order->token) || 
			!isset($this->Order->payerid)) return false;
				
		$_ = $this->headers();

		$_['METHOD'] 				= "DoExpressCheckoutPayment";
		$_['PAYMENTACTION']			= "Sale";
		$_['TOKEN'] 				= $this->Order->token;
		$_['PAYERID'] 				= $this->Order->payerid;

		// Transaction
		$_ = array_merge($_,$this->purchase());

		$message = $this->encode($_);
		$response = $this->send($message);

		if (!$response) {
			new ShoppError(__('No response was received from PayPal. The order cannot be processed.','Shopp'),'paypalexpress_noresults',SHOPP_COMM_ERR);
			shopp_redirect($Shopp->link('checkout'));
		}
		
		if (strtolower($response->ack) != "success") {
			$message = join("; ",$response->longmessage);
			if (empty($message)) $message = __('The transaction failed for an unknown reason. PayPal did not provide any indication of why it failed.','Shopp');
			new ShoppError($message,'paypal_express_transacton_error',SHOPP_TRXN_ERR,array('codes'=>join('; ',$response->errorcode)));
			shopp_redirect($Shopp->link('checkout'));
		}

		$txnid = $response->transactionid;
		$txnstatus = $this->status[$response->paymentstatus];
		
		$Shopp->Order->transaction($txnid,$txnstatus);
	}
	
	function updates () {
		global $Shopp;

		// Cancel processing if this is not a PayPal Website Payments Standard/Express Checkout IPN
		if (isset($_POST['txn_type']) && $_POST['txn_type'] != "cart") return false;

		$target = isset($_POST['parent_txn_id'])?$_POST['parent_txn_id']:$_POST['txn_id'];

		$Purchase = new Purchase($target,'txnid');
		if ($Purchase->gateway != $this->module) return; // Not a PPE order, don't touch it
		
		// Validate the order notification
		if ($this->verifyipn() != "VERIFIED") {
			new ShoppError(sprintf(__('An unverifiable order update notification was received from PayPal for transaction: %s. Possible fraudulent notification!  The order will not be updated.  IPN message: %s','Shopp'),$target,_object_r($_POST)),'paypal_txn_verification',SHOPP_TRXN_ERR);
			return false;
		} 
		
		if (!$txnstatus) $txnstatus = $this->status[$_POST['payment_status']];
		
		$Purchase->transtatus = $txnstatus;
		$Purchase->save();
		
		$Shopp->Purchase = &$Purchase;
		$Shopp->Order->purchase = $Purchase->id;

		do_action('shopp_order_notifications');
		
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
		$response = parent::send($message,$this->api());
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