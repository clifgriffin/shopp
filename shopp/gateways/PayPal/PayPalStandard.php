<?php
/**
 * PayPal Standard
 * @class PayPalStandard
 *
 * @author Jonathan Davis
 * @version 1.0.5
 * @copyright Ingenesis Limited, 27 May, 2009
 * @package Shopp
 * @since 1.1 dev
 * @subpackage PayPalStandard
 * 
 * $Id$
 **/

class PayPalStandard extends GatewayFramework {
	// var $type = "xco"; // Define as an External CheckOut/remote checkout processor
	var $buttonurl = 'http://www.paypal.com/%s/i/btn/btn_xpressCheckout.gif';
	var $sandboxurl = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
	var $checkouturl = 'https://www.paypal.com/cgi-bin/webscr';

	var $transaction = array();
	var $settings = array();
	
	var $Response = false;
	var $checkout = true;
	var $pdt = false;
	var $secure = false;
	
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

	function PayPalStandard () {
		parent::__construct();
		
		global $Shopp;
		$this->setup('account','testmode');
		$this->settings['merchant_email'] = $Shopp->Settings->get('merchant_email');
		$this->settings['base_operations'] = $Shopp->Settings->get('base_operations');
		$this->settings['currency_code'] = $this->currencies[0];
		if (in_array($this->settings['base_operations']['currency']['code'],$this->currencies))
			$this->settings['currency_code'] = $this->settings['base_operations']['currency']['code'];

		if (array_key_exists($this->settings['base_operations']['country'],$this->locales))
			$this->settings['locale'] = $this->locales[$this->settings['base_operations']['country']];
		else $this->settings['locale'] = $this->locales["US"];

		$this->buttonurl = sprintf($this->buttonurl, $this->settings['locale']);
		$this->ipn = add_query_arg('shopp_xorder','PayPalStandard',$Shopp->link('catalog',true));
		
		// $loginproc = (isset($_POST['process-login']) 
		// 	&& $_POST['process-login'] != 'false')?$_POST['process-login']:false;
		// 	
		// if (isset($_POST['checkout']) && 
		// 	$_POST['checkout'] == "process" && 
		// 	!$loginproc) $this->checkout();
		// 
		// // Capture processed payment
		// if (isset($_REQUEST['tx'])) {
		// 	$this->pdt = true;
		// 	$this->order();
		// }

		error_log("paypal constructed");
		add_action('shopp_txn_update',array(&$this,'updates'));
		add_action('shopp_init_checkout',array(&$this,'init'));
		add_action('shopp_process_checkout', array(&$this,'checkout'),9);
		add_action('shopp_init_confirmation',array(&$this,'confirmation'));
		add_action('shopp_remote_order',array(&$this,'returned'));
		add_action('shopp_process_order',array(&$this,'process'));
	}
	
	function confirmation () {
		if (!$this->myorder()) return false;
		add_filter('shopp_confirm_url',array(&$this,'url'));
		add_filter('shopp_confirm_form',array(&$this,'form'));
		add_filter('shopp_checkout_confirm_button',array(&$this,'confirm'),10,3);
	}
	
	function init () {
		if (!$this->myorder()) return false;
		add_filter('shopp_checkout_submit_button',array(&$this,'submit'),10,3);
	}
		
	function checkout () {
		global $Shopp;
		$Shopp->Order->confirm = true;
		// global $Shopp;
		// if (empty($_POST['checkout'])) return false;
		// 
		// // Save checkout data
		// $Order = $Shopp->Cart->data->Order;
		// 
		// if (isset($_POST['data'])) $Order->data = $_POST['data'];
		// if (empty($Order->Customer))
		// 	$Order->Customer = new Customer();
		// $Order->Customer->updates($_POST);
		// $Order->Customer->confirm_password = $_POST['confirm-password'];
		// 
		// if (empty($Order->Billing))
		// 	$Order->Billing = new Billing();
		// $Order->Billing->updates($_POST['billing']);
		// 
		// if (empty($Order->Shipping))
		// 	$Order->Shipping = new Shipping();
		// 	
		// if ($_POST['shipping']) $Order->Shipping->updates($_POST['shipping']);
		// if (!empty($_POST['shipmethod'])) $Order->Shipping->method = $_POST['shipmethod'];
		// else $Order->Shipping->method = key($Shopp->Cart->data->ShipCosts);
		// 
		// // Override posted shipping updates with billing address
		// if ($_POST['sameshipaddress'] == "on")
		// 	$Order->Shipping->updates($Order->Billing,
		// 		array("_datatypes","_table","_key","_lists","id","created","modified"));
		// 
		// $estimatedTotal = $Shopp->Cart->data->Totals->total;
		// $Shopp->Cart->updated();
		// $Shopp->Cart->totals();
		// 
		// if ($Shopp->Cart->validate() !== true) {
		// 	$_POST['checkout'] = false;
		// 	return;
		// } else $Order->Customer->updates($_POST); // Catch changes from validation
		// 
		// if (number_format($Shopp->Cart->data->Totals->total, 2) == 0) {
		// 	$_POST['checkout'] = 'confirmed';
		// 	return true;
		// }
		// 
		// if(!$Shopp->Cart->validorder()) shopp_redirect($Shopp->link('cart')); 
		// shopp_redirect(add_query_arg('shopp_xco','PayPal/PayPalStandard',$Shopp->link('confirm-order',false)));
	}

	function submit ($tag=false,$options=array(),$attrs=array()) {
		return '<input type="image" name="process" src="'.$this->buttonurl.'" id="checkout-button" '.inputattrs($options,$attrs).' />';
	}

	function confirm ($tag=false,$options=array(),$attrs=array()) {
		return '<input type="image" name="confirmed" src="'.$this->buttonurl.'" id="confirm-button" '.inputattrs($options,$attrs).' />';
	}

	
	function url ($url=false) {
		if ($this->settings['testmode'] == "on") return $this->sandboxurl;
		else return $this->checkouturl;
	}
	
	/**
	 * form()
	 * Builds a hidden form to submit to PayPal when confirming the order for processing */
	function form ($form) {
		global $Shopp;
		$Order = $this->Order;
		$precision = $this->settings['base_operations']['currency']['format']['precision'];
		
		$_ = array();

		$_['cmd'] 					= "_cart";
		$_['upload'] 				= 1;
		$_['business']				= $this->settings['account'];
		$_['invoice']				= $Shopp->Shopping->session;
		
		// Options
		$_['return']				= add_query_arg('r_order','process',$Shopp->link('checkout',false));
		$_['cancel_return']			= $Shopp->link('cart');
		$_['notify_url']			= add_query_arg('_txnupdate','PPS',$Shopp->link('catalog'));
		$_['rm']					= 1; // Return with no transaction data
		
		// Pre-populate PayPal Checkout
		$_['first_name']			= $Order->Customer->firstname;
		$_['last_name']				= $Order->Customer->lastname;
		$_['lc']					= $this->settings['base_operations']['country'];
		
		$AddressType = "Shipping";
		if (!$Order->Shipping) $AddressType = "Billing";
		
		$_['address_override'] 		= 1;
		$_['address1']				= $Order->{$AddressType}->address;
		if (!empty($Order->{$AddressType}->xaddress))
			$_['address2']			= $Order->{$AddressType}->xaddress;
		$_['city']					= $Order->{$AddressType}->city;
		$_['state']					= $Order->{$AddressType}->state;
		$_['zip']					= $Order->{$AddressType}->postcode;
		$_['country']				= $Order->{$AddressType}->country;
		$_['night_phone_a']			= $Order->Customer->phone;
		
		// Include page style option, if provided
		if (isset($_GET['pagestyle'])) $_['pagestyle'] = $_GET['pagestyle'];

		if (isset($Order->data['paypal-custom']))
			$_['custom'] = htmlentities($Order->data['paypal-custom']);
		
		// Transaction
		$_['currency_code']			= $this->settings['currency_code'];

		// Disable shipping fields if no shipped items in cart
		if (!$Order->Shipping) $_['no_shipping'] = 1;

		// Line Items
		foreach($Order->Cart->contents as $i => $Item) {
			$id=$i+1;
			$_['item_number_'.$id]		= $id;
			$_['item_name_'.$id]		= $Item->name.((!empty($Item->optionlabel))?' '.$Item->optionlabel:'');
			$_['amount_'.$id]			= number_format($Item->unitprice,$precision);
			$_['quantity_'.$id]			= $Item->quantity;
			$_['weight_'.$id]			= $Item->quantity;
		}
		
		$_['discount_amount_cart'] 		= number_format($Order->Cart->Totals->discount,$precision);
		$_['tax_cart']					= number_format($Order->Cart->Totals->tax,$precision);
		$_['handling_cart']				= number_format($Order->Cart->Totals->shipping,$precision);
		$_['amount']					= number_format($Order->Cart->Totals->total,$precision);
		
		return $form.$this->format($_);
	}
	
	function returned () {
		if (isset($_REQUEST['tx']) && $this->myorder()) { // PDT
			error_log('paypal returned');
			// Run order processing
			do_action('shopp_process_order'); 
		}
	}
	
	function process () {
			global $Shopp;
			
			$txnstatus = false;
			$transactionid = false;
			if (isset($_REQUEST['tx'])) { // PDT processing
				if (SHOPP_DEBUG) new ShoppError('Processing PDT packet: '._object_r($_GET),false,SHOPP_DEBUG_ERR);

				$txnid = $_GET['tx'];
				$txnstatus = $this->status[$_GET['st']];
				error_log("$txnid - $txnstatus");
				$Purchase = new Purchase($txnid,'txnid');

				if (!empty($Purchase->id)) {
					error_log("Purchase exists! Update status...");
					if (SHOPP_DEBUG) new ShoppError('Order located, already created from an IPN message.',false,SHOPP_DEBUG_ERR);
					$Shopp->resession();
					$Shopp->Purchase = $Purchase;
					$Shopp->Order->purchase = $Purchase->id;
					shopp_redirect($Shopp->link('thanks',false));
				}

			}
			
			if (isset($_REQUEST['txn_id'])) { // IPN processing
				$txnid = $_POST['txn_id'];
				$txnstatus = $this->status[$_POST['payment_status']];

				// Validate the order notification
				$ipnstatus = $this->verifyipn();
				if ($ipnstatus != "VERIFIED") {
					$txnstatus = $ipnstatus;
					new ShoppError('An unverifiable order notification was received from PayPal. Possible fraudulent order attempt! The order will be created, but the order payment status must be manually set to "Charged" when the payment can be verified.','paypal_txn_verification',SHOPP_TRXN_ERR);
				} else if (SHOPP_DEBUG) new ShoppError('IPN notification validated.',false,SHOPP_DEBUG_ERR);
				
			}
		
		$Shopp->Order->transaction($txnid,$txnstatus);
		
	}
	
	function updates () {

		// Cancel processing if this is not a PayPal Website Payments Standard/Express Checkout IPN
		if (isset($_POST['txn_type']) && $_POST['txn_type'] != "cart") return false;

		// Need an invoice number to locate pre-order data
		if (!isset($_POST['invoice'])) return false;

		global $Shopp;
		$Shopp->resession($_POST['invoice']);
		$Shopping = &$Shopp->Shopping;
		// Couldn't load the session data
		if ($Shopping->session != $_POST['invoice'])
			return new ShoppError("Session could not be loaded: {$_POST['invoice']}",false,SHOPP_DEBUG_ERR);
		else new ShoppError("PayPal successfully loaded session: {$_POST['invoice']}",false,SHOPP_DEBUG_ERR);

		if (!isset($_POST['txn_id']) && !isset($_POST['parent_txn_id'])) return false; // Not a notification request
		$target = isset($_POST['parent_txn_id'])?$_POST['parent_txn_id']:$_POST['txn_id'];

		$Purchase = new Purchase($target,'transactionid');
		if (empty($Purchase->id)) {
			new ShoppError('No existing purchase to update for transaction: '.$target,false,SHOPP_DEBUG_ERR);
			if ($Shopp->Order->validorder()) do_action('shopp_create_purchase');
		}
		
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
		
		// global $Shopp;
		// if (!isset($_POST['txn_id']) && !isset($_POST['parent_txn_id'])) return false; // Not a notification request
		// $target = isset($_POST['parent_txn_id'])?$_POST['parent_txn_id']:$_POST['txn_id'];
		// $Purchase = new Purchase($target,'transactionid');
		// if (empty($Purchase->id)) {
		// 	new ShoppError('No existing purchase to update for transaction: '.$target,false,SHOPP_DEBUG_ERR);
		// 	return false;  // No order exists, bail out
		// }
		// 
		// // Validate the order notification
		// if ($this->verifyipn() != "VERIFIED") {
		// 	new ShoppError(sprintf(__('An unverifiable order update notification was received from PayPal for transaction: %s. Possible fraudulent notification!  The order will not be updated.  IPN message: %s','Shopp'),$target,_object_r($_POST)),'paypal_txn_verification',SHOPP_TRXN_ERR);
		// 	return false;
		// } 
		// 
		// if (!$txnstatus) $txnstatus = $this->status[$_POST['payment_status']];
		// 
		// // Order exists, handle IPN updates
		// $Purchase->transtatus = $txnstatus;
		// $Purchase->save();
		// 
		// $Shopp->Cart->data->Purchase =& $Purchase;
		// $Shopp->Cart->data->Purchase->load_purchased();
		// 
		// $Purchase->notification(
		// 	"$Purchase->firstname $Purchase->lastname",
		// 	$Purchase->email,
		// 	__('Order Payment Update','Shopp')
		// );
		// 
		// if ($Shopp->Settings->get('receipt_copy') == 1) {
		// 	$Purchase->notification(
		// 		'',
		// 		$Shopp->Settings->get('merchant_email'),
		// 		__('PayPal Order Payment Update','Shopp')
		// 	);
		// }
		// 
		// if (SHOPP_DEBUG) new ShoppError('PayPal IPN update processed for transaction: '.$target,false,SHOPP_DEBUG_ERR);
		// return true;
	}
	
	function verifyipn () {
		$_ = array();
		$_['cmd'] = "_notify-validate";
		
		$transaction = $this->encode(array_merge($_POST,$_));
		$response = parent::send($transaction,$this->url);
		if (SHOPP_DEBUG) new ShoppError('PayPal IPN notification verfication response received: '.$response,'paypal_standard',SHOPP_DEBUG_ERR);
		return $response;
	}
	

	
	function error () {
		if (!empty($this->Response)) {
			
			$message = join("; ",$this->Response->l_longmessage);
			if (empty($message)) return false;
			return new ShoppError($message,'paypal_express_transacton_error',SHOPP_TRXN_ERR,
				array('code'=>$code));
		}
	}
		
	function send () {
		return parent::send($this->transaction,$this->url());
	}
	
	function response () { /* Placeholder */ }

	/**
	 * encode()
	 * Builds a get/post encoded string from the provided $data */
	function encode ($data) {
		$query = "";
		foreach($data as $key => $value) {
			if (is_array($value)) {
				foreach($value as $item) {
					if (strlen($query) > 0) $query .= "&";
					$query .= "$key=".urlencode(stripslashes($item));
				}
			} else {
				if (strlen($query) > 0) $query .= "&";
				$query .= "$key=".urlencode(stripslashes($value));
			}
		}
		return $query;
	}
	
	/**
	 * format()
	 * Generates hidden inputs based on the supplied $data */
	function format ($data) {
		$query = "";
		foreach($data as $key => $value) {
			if (is_array($value)) {
				foreach($value as $item)
					$query .= '<input type="hidden" name="'.$key.'[]" value="'.attribute_escape($item).'" />';
			} else {
				$query .= '<input type="hidden" name="'.$key.'" value="'.attribute_escape($value).'" />';
			}
		}
		return $query;
	}
	
	// function tag ($property,$options=array()) {
	// 	global $Shopp;
	// 	switch ($property) {
	// 		case "button":
	// 			$args = array();
	// 			$args['shopp_xco'] = 'PayPal/PayPalStandard';
	// 			if (isset($options['pagestyle'])) $args['pagestyle'] = $options['pagestyle'];
	// 			$url = add_query_arg($args,$Shopp->link('checkout'));
	// 			return '<p><a href="'.$url.'"><img src="'.$this->button.'" alt="Checkout with PayPal" /></a></p>';
	// 	}
	// }

	// Required, but not used
	function billing () {}
		
	function settings () {
		$this->ui->text(0,array(
			'name' => 'account',
			'value' => $this->settings['account'],
			'size' => 30,
			'label' => __('Enter your PayPal account email.','Shopp')
		));

		$this->ui->checkbox(0,array(
			'name' => 'testmode',
			'label' => sprintf(__('Use the %s','Shopp'),'<a href="http://docs.shopplugin.net/PayPal_Sandbox" target="shoppdocs">PayPal Sandbox</a>'),
			'checked' => $this->settings['testmode']
		));
		
	}
	

} // END class PayPalStandard

?>