<?php
/**
 * PayPal Standard
 * @class PayPalStandard
 *
 * @author Jonathan Davis
 * @version 1.0.5
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

	// URLs
	var $buttonurl = 'http://www.paypal.com/%s/i/btn/btn_xpressCheckout.gif';
	var $sandboxurl = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
	var $checkouturl = 'https://www.paypal.com/cgi-bin/webscr';

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

	function __construct () {
		parent::__construct();
		if (is_shopp_secure()) $this->buttonurl = str_replace('http://','https://',$this->buttonurl);
		$this->setup('account','pdtverify','pdttoken','testmode');
		
		$this->settings['currency_code'] = $this->currencies[0];
		if (in_array($this->baseop['currency']['code'],$this->currencies))
			$this->settings['currency_code'] = $this->baseop['currency']['code'];

		if (array_key_exists($this->baseop['country'],$this->locales))
			$this->settings['locale'] = $this->locales[$this->baseop['country']];
		else $this->settings['locale'] = $this->locales['US'];

		$this->buttonurl = sprintf($this->buttonurl, $this->settings['locale']);

		if (!isset($this->settings['label'])) $this->settings['label'] = "PayPal";
		
		add_action('shopp_txn_update',array(&$this,'updates'));
				
	}
	
	function actions () {
		add_action('shopp_process_checkout', array(&$this,'checkout'),9);
		add_action('shopp_init_checkout',array(&$this,'init'));

		add_action('shopp_init_confirmation',array(&$this,'confirmation'));
		add_action('shopp_remote_payment',array(&$this,'returned'));
		add_action('shopp_process_order',array(&$this,'process'));
	}
	
	function confirmation () {
		add_filter('shopp_confirm_url',array(&$this,'url'));
		add_filter('shopp_confirm_form',array(&$this,'form'));
	}
	
	function init () {
		add_filter('shopp_checkout_submit_button',array(&$this,'submit'),10,3);
	}
		
	function checkout () {
		$this->Order->Billing->cardtype = "PayPal";
		$this->Order->confirm = true;
	}

	function submit ($tag=false,$options=array(),$attrs=array()) {
		return '<input type="image" name="process" src="'.$this->buttonurl.'" id="checkout-button" '.inputattrs($options,$attrs).' />';
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
		
		$_ = array();

		$_['cmd'] 					= "_cart";
		$_['upload'] 				= 1;
		$_['business']				= $this->settings['account'];
		$_['invoice']				= mktime();
		$_['custom']				= $Shopp->Shopping->session;
		
		// Options
		$_['return']				= add_query_arg('rmtpay','process',$Shopp->link('checkout',false));
		$_['cancel_return']			= $Shopp->link('cart');
		$_['notify_url']			= add_query_arg('_txnupdate','PPS',$Shopp->link('checkout'));
		$_['rm']					= 1; // Return with no transaction data
		
		// Pre-populate PayPal Checkout
		$_['first_name']			= $Order->Customer->firstname;
		$_['last_name']				= $Order->Customer->lastname;
		$_['lc']					= $this->baseop['country'];
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
		$_['night_phone_a']			= $Order->Customer->phone;
		
		// Include page style option, if provided
		if (isset($_GET['pagestyle'])) $_['pagestyle'] = $_GET['pagestyle'];

		// if (isset($Order->data['paypal-custom']))
		// 	$_['custom'] = htmlentities($Order->data['paypal-custom']);
		
		// Transaction
		$_['currency_code']			= $this->settings['currency_code'];


		// Line Items
		foreach($Order->Cart->contents as $i => $Item) {
			$id=$i+1;
			$_['item_number_'.$id]		= $id;
			$_['item_name_'.$id]		= $Item->name.((!empty($Item->optionlabel))?' '.$Item->optionlabel:'');
			$_['amount_'.$id]			= number_format($Item->unitprice,$this->precision);
			$_['quantity_'.$id]			= $Item->quantity;
			$_['weight_'.$id]			= $Item->quantity;
		}
		
		$_['discount_amount_cart'] 		= number_format($Order->Cart->Totals->discount,$this->precision);
		$_['tax_cart']					= number_format($Order->Cart->Totals->tax,$this->precision);
		$_['handling_cart']				= number_format($Order->Cart->Totals->shipping,$this->precision);
		$_['amount']					= number_format($Order->Cart->Totals->total,$this->precision);
		
		return $form.$this->format($_);
	}
	
	function returned () {
		if (isset($_REQUEST['tx'])) { // PDT
			// Run order processing
			do_action('shopp_process_order'); 
		}
	}
	
	function process () {
		global $Shopp;
		
		$txnid = false;
		$txnstatus = false;
		if (isset($_REQUEST['tx'])) { // PDT order processing
			if (SHOPP_DEBUG) new ShoppError('Processing PDT packet: '._object_r($_GET),false,SHOPP_DEBUG_ERR);

			$txnid = $_GET['tx'];
			$txnstatus = $this->status[$_GET['st']];

			$pdtstatus = $this->verifypdt();
			if (!$pdtstatus) {
				new ShoppError(__('The transaction was not verified by PayPal.','Shopp'),false,SHOPP_DEBUG_ERR);
				shopp_redirect($Shopp->link('checkout',false));
			}

			$Purchase = new Purchase($txnid,'txnid');
			if (!empty($Purchase->id)) {
				if (SHOPP_DEBUG) new ShoppError('Order located, already created from an IPN message.',false,SHOPP_DEBUG_ERR);
				$Shopp->resession();
				$Shopp->Purchase = $Purchase;
				$Shopp->Order->purchase = $Purchase->id;
				shopp_redirect($Shopp->link('thanks',false));
			}

		}
		
		if (isset($_POST['txn_id'])) { // IPN order processing
			$txnid = $_POST['txn_id'];
			$txnstatus = $this->status[$_POST['payment_status']];
		}
		
		$Shopp->Order->transaction($txnid,$txnstatus);
		
	}
	
	function updates () {
		global $Shopp;

		// Cancel processing if this is not a PayPal Website Payments Standard/Express Checkout IPN
		if (isset($_POST['txn_type']) && $_POST['txn_type'] != "cart") return false;

		// Validate the order notification
		if ($this->verifyipn() != "VERIFIED") {
			new ShoppError(sprintf(__('An unverifiable order update notification was received from PayPal for transaction: %s. Possible fraudulent notification!  The order will not be updated.  IPN message: %s','Shopp'),$target,_object_r($_POST)),'paypal_txn_verification',SHOPP_TRXN_ERR);
			return false;
		} 
		
		
		// Need an session id to locate pre-order data and a transaction id for the order
		if (isset($_POST['custom']) && isset($_POST['txn_id']) && !isset($_POST['parent_txn_id'])) {

			$Shopp->resession($_POST['custom']);
			$Shopp->Order = ShoppingObject::__new('Order',$Shopp->Order);
			
			$Shopping = &$Shopp->Shopping;
			// Couldn't load the session data
			if ($Shopping->session != $_POST['custom'])
				return new ShoppError("Session could not be loaded: {$_POST['custom']}",false,SHOPP_DEBUG_ERR);
			else new ShoppError("PayPal successfully loaded session: {$_POST['custom']}",false,SHOPP_DEBUG_ERR);
			
			return do_action('shopp_process_order'); // New order
		} elseif (!empty($_POST['parent_txn_id'])) {

			$target = $_POST['parent_txn_id'];

			$Purchase = new Purchase($target,'txnid');

			if (!$txnstatus) $txnstatus = $this->status[$_POST['payment_status']];

			$Purchase->txnstatus = $txnstatus;
			$Purchase->save();

			$Shopp->Purchase = &$Purchase;
			$Shopp->Order->purchase = $Purchase->id;

			do_action('shopp_order_notifications');

			if (SHOPP_DEBUG) new ShoppError('PayPal IPN update processed for transaction: '.$target,false,SHOPP_DEBUG_ERR);
			
		}

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
	
	function verifypdt () {
		if ($this->settings['testmode'] == "on") return "VERIFIED";
		$_ = array();
		$_['cmd'] = "_notify-synch";
		$_['at'] = $this->settings['pdttoken'];
		
		$message = $this->encode(array_merge($_GET,$_));
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
		
	function send ($message) {
		return parent::send($message,$this->url());
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

		$this->verifytoken();
	}
	
		function verifytoken () {
	?>
			PayPalStandard.behaviors = function () {
				$('#settings-paypalstandard-pdtverify').change(function () {
					if ($(this).attr('checked')) $('#settings-paypalstandard-pdttoken').parent().show();
					else $('#settings-paypalstandard-pdttoken').parent().hide();
				}).change();
			}
	<?php
		}
	
	

} // END class PayPalStandard

?>