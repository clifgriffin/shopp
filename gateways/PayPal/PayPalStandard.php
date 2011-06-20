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
	var $status = array('' => 'UNKNOWN','Canceled-Reversal' => 'CHARGED','Completed' => 'CHARGED',
						'Denied' => 'VOID', 'Expired' => 'VOID','Failed' => 'VOID','Pending' => 'PENDING',
						'Refunded' => 'VOID','Reversed' => 'VOID','Processed' => 'PENDING','Voided' => 'VOID');

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

		add_action('shopp_txn_update',array(&$this,'updates'));
		add_filter('shopp_tag_cart_paypal',array(&$this,'sendcart'),10,2);
		add_filter('shopp_checkout_submit_button',array(&$this,'submit'),10,3);
	}

	function actions () {
		add_action('shopp_process_checkout', array(&$this,'checkout'),9);

		add_action('shopp_init_confirmation',array(&$this,'confirmation'));
		add_action('shopp_remote_payment',array(&$this,'payment'));
		add_action('shopp_init_checkout',array(&$this,'returned'));
		add_action('shopp_process_order',array(&$this,'process'));
	}

	function confirmation () {
		add_filter('shopp_confirm_url',array(&$this,'url'));
		add_filter('shopp_confirm_form',array(&$this,'form'));
	}

	function checkout () {
		$this->Order->Billing->cardtype = "PayPal";
		$this->Order->confirm = true;
	}

	function submit ($tag=false,$options=array(),$attrs=array()) {
		$tag[$this->settings['label']] = '<input type="image" name="process" src="'.$this->buttonurl.'" '.inputattrs($options,$attrs).' />';
		return $tag;
	}

	function url ($url=false) {
		if ($this->settings['testmode'] == "on") return $this->sandboxurl;
		else return $this->checkouturl;
	}

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
	 * form()
	 * Builds a hidden form to submit to PayPal when confirming the order for processing */
	function form ($form,$options=array()) {
		global $Shopp;
		$Order = $this->Order;

		$_ = array();
		$fc = array();

		$_['cmd'] 					= "_cart";
		$_['upload'] 				= 1;
		$_['business']				= $this->settings['account'];
		$_['invoice']				= mktime();
		$_['custom']				= $Shopp->Shopping->session;

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
			$_['item_name_'.$id]		= $Item->name.((!empty($Item->option->label))?' '.$Item->option->label:'');
			$_['amount_'.$id]			= number_format($Item->unitprice,$this->precision);
			$_['quantity_'.$id]			= $Item->quantity;
			$_['weight_'.$id]			= $Item->quantity;
		}

		// Workaround a PayPal limitation of not correctly handling no subtotals or
		// handling discounts in the amount of the item subtotals by adding the
		// shipping fee to the line items to get included in the subtotal. If no
		// shipping fee is available use 1.00 to satisfy minimum order amount requirements
		if ((int)$Order->Cart->Totals->subtotal == 0 ||
			$Order->Cart->Totals->subtotal-$Order->Cart->Totals->discount == 0) {
			$id++;
			$_['item_number_'.$id]		= $id;
			$_['item_name_'.$id]		= apply_filters('paypal_freeorder_handling_label',
														__('Shipping & Handling','Shopp'));
			$_['amount_'.$id]			= number_format(max($Order->Cart->Totals->shipping,1.00),$this->precision);
			$_['quantity_'.$id]			= 1;
			$fc['shipping'] = 'amount_'.$id;
		} else {
			$_['handling_cart']			= number_format($Order->Cart->Totals->shipping,$this->precision);
			$fc['shipping']	= 'handling_cart';
		}


		$_['discount_amount_cart'] 		= number_format($Order->Cart->Totals->discount,$this->precision);
		$_['tax_cart']					= number_format($Order->Cart->Totals->tax,$this->precision);
		$fc['tax'] = 'tax_cart';

		$_['amount']					= number_format($Order->Cart->Totals->total,$this->precision);
		$fc['total']	= 'amount';

		$_ = array_merge($_,$options);

		return $form.$this->format($_,$fc);
	}

	function payment () {
		if (isset($_REQUEST['tx'])) { // PDT
			add_filter('shopp_valid_order',array(&$this,'pdtpassthru'));
			// Run order processing
			do_action('shopp_process_order');
		}
	}

	function pdtpassthru ($valid) {
		if ($valid) return $valid;
		// If the order data validation fails, passthru to the thank you page
		shopp_redirect( shoppurl(false,'thanks') );
	}

	function returned () {
		$process = get_query_var('shopp_proc');
		if ($process != 'thanks') return;
		global $Shopp;

		// Session has already been reset after a processed transaction
		if (!empty($Shopp->Purchase->id)) return;

		// Customer returned from PayPal
		// but no transaction processed yet
		// reset the session to preserve original order
		$Shopp->resession();

	}

	function process () {
		global $Shopp;

		$txnid = false;
		$txnstatus = false;
		if (isset($_POST['txn_id'])) { // IPN order processing
			if (SHOPP_DEBUG) new ShoppError('Processing transaction from an IPN message.',false,SHOPP_DEBUG_ERR);
			$txnid = $_POST['txn_id'];
			$txnstatus = $this->status[$_POST['payment_status']];
		} elseif (isset($_REQUEST['tx'])) { // PDT order processing
			if (SHOPP_DEBUG) new ShoppError('Processing PDT packet: '._object_r($_GET),false,SHOPP_DEBUG_ERR);

			$txnid = $_GET['tx'];
			$txnstatus = $this->status[$_GET['st']];

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
				$Shopp->resession();
				$Shopp->Purchase = $Purchase;
				$Shopp->Order->purchase = $Purchase->id;
				shopp_redirect(shoppurl(false,'thanks',false));
			}

		}

		if (!$txnid) return new ShoppError('No transaction ID was found from either a PDT or IPN message. Transaction cannot be processed.',false,SHOPP_DEBUG_ERR);
		$Shopp->Order->transaction($txnid,$txnstatus);

	}

	function updates () {
		global $Shopp;

		// Cancel processing if this is not a PayPal Website Payments Standard/Express Checkout IPN
		if (isset($_POST['txn_type']) && $_POST['txn_type'] != "cart") return false;

		$target = false;
		if (isset($_POST['txn_id']) && !isset($_POST['parent_txn_id']))
			$target = $_POST['txn_id'];
		elseif (!empty($_POST['parent_txn_id'])) $target = $_POST['parent_txn_id'];

		// No transaction target: invalid IPN, silently ignore the message
		if (!$target) return;

		// Validate the order notification
		if ($this->verifyipn() != "VERIFIED") {
			new ShoppError(sprintf(__('An unverifiable order update notification was received from PayPal for transaction: %s. Possible fraudulent notification!  The order will not be updated.  IPN message: %s','Shopp'),$target,_object_r($_POST)),'paypal_txn_verification',SHOPP_TRXN_ERR);
			return false;
		}

		$Purchase = new Purchase($target,'txnid');

		// Purchase record exists, update it
		if ($Purchase->txnid == $target && !empty($Purchase->id)) {
			if ($Purchase->gateway != $this->name) return; // Not a PPS order, don't touch it
			$txnstatus = isset($this->status[$_POST['payment_status']])?
				$this->status[$_POST['payment_status']]:$_POST['payment_status'];

			$Purchase->txnstatus = $txnstatus;
			$Purchase->save();
			do_action_ref_array('shopp_order_txnstatus_update',array(&$txnstatus,&$Purchase)); // TODO: update to use Order->transaction()

			$Shopp->Purchase = &$Purchase;
			$Shopp->Order->purchase = $Purchase->id;

			do_action('shopp_order_notifications');
			die('PayPal IPN update processed.');
		}

		if (!isset($_POST['custom'])) {
			new ShoppError(sprintf(__('No reference to the pending order was available in the PayPal IPN message. Purchase creation failed for transaction %s.'),$target),'paypalstandard_process_neworder',SHOPP_TRXN_ERR);
			die('PayPal IPN failed.');
		}

		$Shopp->Order->unhook();
		$Shopp->resession($_POST['custom']);
		$Shopp->Order = ShoppingObject::__new('Order',$Shopp->Order);
		$this->actions();

		$Shopping = &$Shopp->Shopping;
		// Couldn't load the session data
		if ($Shopping->session != $_POST['custom'])
			return new ShoppError("Session could not be loaded: {$_POST['custom']}",false,SHOPP_DEBUG_ERR);
		else new ShoppError("PayPal successfully loaded session: {$_POST['custom']}",false,SHOPP_DEBUG_ERR);

		$this->ipnupdates();

		do_action('shopp_process_order'); // New order
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