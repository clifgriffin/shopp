<?php
/**
 * PayPal Standard
 * @class PayPalStandard
 *
 * @author Jonathan Davis
 * @version 1.0.5
 * @copyright Ingenesis Limited, 27 May, 2009
 * @package Shopp
 * 
 * $Id$
 **/

class PayPalStandard {          
	var $type = "xco"; // Define as an External CheckOut/remote checkout processor
	var $button = 'http://www.paypal.com/%s/i/btn/btn_xpressCheckout.gif';
	var $sandbox_url = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
	var $checkout_url = 'https://www.paypal.com/cgi-bin/webscr';
	var $transaction = array();
	var $settings = array();
	var $Response = false;
	var $checkout = true;
	var $pdt = false;
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
		global $Shopp;
		$this->settings = $Shopp->Settings->get('PayPalStandard');
		$this->settings['merchant_email'] = $Shopp->Settings->get('merchant_email');
		$this->settings['base_operations'] = $Shopp->Settings->get('base_operations');
		$this->settings['currency_code'] = $this->currencies[0];
		if (in_array($this->settings['base_operations']['currency']['code'],$this->currencies))
			$this->settings['currency_code'] = $this->settings['base_operations']['currency']['code'];

		if (array_key_exists($this->settings['base_operations']['country'],$this->locales))
			$this->settings['locale'] = $this->locales[$this->settings['base_operations']['country']];
		else $this->settings['locale'] = $this->locales["US"];

		$this->button = sprintf($this->button, $this->settings['locale']);
		$this->ipn = add_query_arg('shopp_xorder','PayPalStandard',$Shopp->link('catalog',true));
			
		$loginproc = (isset($_POST['process-login']) 
			&& $_POST['process-login'] != 'false')?$_POST['process-login']:false;
			
		if (isset($_POST['checkout']) && 
			$_POST['checkout'] == "process" && 
			!$loginproc) $this->checkout();
		
		// Capture processed payment
		if (isset($_REQUEST['tx'])) {
			$this->pdt = true;
			$this->order();
		}

	}
	
	function actions () {
		add_filter('shopp_confirm_url',array(&$this,'url'));
		add_filter('shopp_confirm_form',array(&$this,'form'));
	}
	
	function checkout () {
		global $Shopp;
		if (empty($_POST['checkout'])) return false;

		// Save checkout data
		$Order = $Shopp->Cart->data->Order;

		if (isset($_POST['data'])) $Order->data = $_POST['data'];
		if (empty($Order->Customer))
			$Order->Customer = new Customer();
		$Order->Customer->updates($_POST);
		$Order->Customer->confirm_password = $_POST['confirm-password'];

		if (empty($Order->Billing))
			$Order->Billing = new Billing();
		$Order->Billing->updates($_POST['billing']);

		if (empty($Order->Shipping))
			$Order->Shipping = new Shipping();
			
		if ($_POST['shipping']) $Order->Shipping->updates($_POST['shipping']);
		if (!empty($_POST['shipmethod'])) $Order->Shipping->method = $_POST['shipmethod'];
		else $Order->Shipping->method = key($Shopp->Cart->data->ShipCosts);

		// Override posted shipping updates with billing address
		if ($_POST['sameshipaddress'] == "on")
			$Order->Shipping->updates($Order->Billing,
				array("_datatypes","_table","_key","_lists","id","created","modified"));

		$estimatedTotal = $Shopp->Cart->data->Totals->total;
		$Shopp->Cart->updated();
		$Shopp->Cart->totals();

		if ($Shopp->Cart->validate() !== true) {
			$_POST['checkout'] = false;
			return;
		} else $Order->Customer->updates($_POST); // Catch changes from validation

		if (number_format($Shopp->Cart->data->Totals->total, 2) == 0) {
			$_POST['checkout'] = 'confirmed';
			return true;
		}
		
		if(!$Shopp->Cart->validorder()) shopp_redirect($Shopp->link('cart')); 
		shopp_redirect(add_query_arg('shopp_xco','PayPal/PayPalStandard',$Shopp->link('confirm-order',false)));
	}
	
	/**
	 * form()
	 * Builds a hidden form to submit to PayPal when confirming the order for processing */
	function form ($form) {
		global $Shopp;
		$Order = $Shopp->Cart->data->Order;
		$Order->Totals = $Shopp->Cart->data->Totals;
		$Order->Items = $Shopp->Cart->contents;
		$Order->Cart = $Shopp->Cart->session;
		$precision = $this->settings['base_operations']['currency']['format']['precision'];
		
		$_ = array();

		$_['cmd'] 					= "_cart";
		$_['upload'] 				= 1;
		$_['business']				= $this->settings['account'];
		$_['invoice']				= $Order->Cart;
		
		// Options
		$_['return']				= add_query_arg('shopp_xco','PayPal/PayPalStandard',
													$Shopp->link('confirm-order',false));
		$_['cancel_return']			= $Shopp->link('cart');
		$_['notify_url']			= add_query_arg('shopp_xorder','PayPalStandard',$Shopp->link('catalog'));
		$_['rm']					= 1; // Return with no transaction data
		
		// Pre-populate PayPal Checkout
		$_['first_name']			= $Order->Customer->firstname;
		$_['last_name']				= $Order->Customer->lastname;
		$_['lc']					= $this->settings['base_operations']['country'];
		
		$AddressType = "Shipping";
		if (!$Shopp->Cart->data->Shipping) $AddressType = "Billing";
		
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
		if (!$Shopp->Cart->Shipping) $_['no_shipping'] = 1;

		// Line Items
		foreach($Order->Items as $i => $Item) {
			$id=$i+1;
			$_['item_number_'.$id]		= $id;
			$_['item_name_'.$id]		= $Item->name.((!empty($Item->optionlabel))?' '.$Item->optionlabel:'');
			$_['amount_'.$id]			= number_format($Item->unitprice,$precision);
			$_['quantity_'.$id]			= $Item->quantity;
			$_['weight_'.$id]			= $Item->quantity;
		}
		
		$_['discount_amount_cart'] 		= number_format($Order->Totals->discount,$precision);
		$_['tax_cart']					= number_format($Order->Totals->tax,$precision);
		$_['handling_cart']				= number_format($Order->Totals->shipping,$precision);
		$_['amount']					= number_format($Order->Totals->total,$precision);
		
		return $form.$this->format($_);
	}
		
	function process () {
		global $Shopp;
		if (SHOPP_DEBUG) new ShoppError('PayPal IPN notification received: '._object_r($_POST),false,SHOPP_DEBUG_ERR);
		
		// Cancel processing if this is not a Website Payments Standard/Express Checkout IPN
		if (isset($_POST['txn_type']) && $_POST['txn_type'] != "cart") return false;
		
		// Handle IPN updates to existing purchases
		if ($this->updates()) die("Updated."); 
		
		// If no invoice number is available, we 
		if (empty($_POST['invoice'])) {
			if (SHOPP_DEBUG) new ShoppError('No invoice number was provided by PayPal: '._object_r($_POST),'paypalstd_debug',SHOPP_DEBUG_ERR);
			return new ShoppError(__('An unverifiable order with no invoice number was received from PayPal. Possible fraudulent order attempt!','Shopp'),'paypal_txn_verification',SHOPP_TRXN_ERR);
		}

		$Shopp->Cart = new Cart();
		$Shopp->Cart->reset();

		// Load the cart for the correct order
		$Shopp->Cart->session = $_POST['invoice'];
		if (!$Shopp->Cart->load($Shopp->Cart->session)) 
			new ShoppError('Session could not be loaded: '.$Shopp->Cart->session,false,SHOPP_DEBUG_ERR);
		else new ShoppError('PayPal successfully loaded session: '.$Shopp->Cart->session,false,SHOPP_DEBUG_ERR);

		if (isset($Shopp->Cart->data)) {
			$Order = $Shopp->Cart->data->Order;
			$Order->Totals = $Shopp->Cart->data->Totals;
			$Order->Items = $Shopp->Cart->contents;
			$Order->Cart = $Shopp->Cart->session;
		}

		if (SHOPP_DEBUG) new ShoppError('PayPal IPN new transaction: '._object_r($_POST),false,SHOPP_DEBUG_ERR);
		
		// Validate the order data
		$validation = true;

		if(!$Shopp->Cart->validorder()){
			new ShoppError(sprintf(__('The order can not be processed. Order data: %s -- IPN message: %s','Shopp'),_object_r($Order),_object_r($_POST)),'invalid_order_pps',SHOPP_TRXN_ERR);
			$validation = false;	
		}
		
		if(floatvalue($_POST['mc_gross']) != floatvalue($Order->Totals->total)){
			$validation = false;
			if(SHOPP_DEBUG) new ShoppError(sprintf(__('Order validation failed. The order total from the IPN message (%s) does not match the Shopp order total (%s)','Shopp'),floatvalue($_POST['mc_gross']),floatvalue($Order->Totals->total)),'paypalstd_total_mismatch',SHOPP_TRXN_ERR);
		}  
		 
		if ($validation) $this->order();		
		exit();
	}
	
	function updates () {
		global $Shopp;
		if (!isset($_POST['txn_id']) && !isset($_POST['parent_txn_id'])) return false; // Not a notification request
		$target = isset($_POST['parent_txn_id'])?$_POST['parent_txn_id']:$_POST['txn_id'];
		$Purchase = new Purchase($target,'transactionid');
		if (empty($Purchase->id)) {
			new ShoppError('No existing purchase to update for transaction: '.$target,false,SHOPP_DEBUG_ERR);
			return false;  // No order exists, bail out
		}
		
		// Validate the order notification
		if ($this->verifyipn() != "VERIFIED") {
			new ShoppError(sprintf(__('An unverifiable order update notification was received from PayPal for transaction: %s. Possible fraudulent notification!  The order will not be updated.  IPN message: %s','Shopp'),$target,_object_r($_POST)),'paypal_txn_verification',SHOPP_TRXN_ERR);
			return false;
		} 
		
		if (!$txnstatus) $txnstatus = $this->status[$_POST['payment_status']];
		
		// Order exists, handle IPN updates
		$Purchase->transtatus = $txnstatus;
		$Purchase->save();
		
		$Shopp->Cart->data->Purchase =& $Purchase;
		$Shopp->Cart->data->Purchase->load_purchased();
		
		$Purchase->notification(
			"$Purchase->firstname $Purchase->lastname",
			$Purchase->email,
			__('Order Payment Update','Shopp')
		);
		
		if ($Shopp->Settings->get('receipt_copy') == 1) {
			$Purchase->notification(
				'',
				$Shopp->Settings->get('merchant_email'),
				__('PayPal Order Payment Update','Shopp')
			);
		}
		
		if (SHOPP_DEBUG) new ShoppError('PayPal IPN update processed for transaction: '.$target,false,SHOPP_DEBUG_ERR);
		return true;
	}
	
	function verifyipn () {
		$_ = array();
		$_['cmd'] = "_notify-validate";
		
		$this->transaction = $this->encode(array_merge($_POST,$_));
		$Response = $this->send();
		if (SHOPP_DEBUG) new ShoppError('PayPal IPN notification verfication response received: '.$Response,'paypal_standard',SHOPP_DEBUG_ERR);
		return $Response;
	}
	
	function order () {
		global $Shopp;
		
		$txnstatus = false;
		$transactionid = false;
		if ($this->pdt) {
			if (SHOPP_DEBUG) new ShoppError('Processing PDT packet: '._object_r($_GET),false,SHOPP_DEBUG_ERR);
			
			$transactionid = $_GET['tx'];
			$txnstatus = $this->status[$_GET['st']];
			$Purchase = new Purchase($transactionid,'transactionid');
			if (!empty($Purchase->id)) {
				if (SHOPP_DEBUG) new ShoppError('Order located, already created from an IPN message.',false,SHOPP_DEBUG_ERR);
				$Shopp->resession();
				$Shopp->Cart->data->Purchase = $Purchase;
				$Shopp->Cart->data->Purchase->load_purchased();
				shopp_redirect($Shopp->link('thanks',false));
			}
		} else {
			$ipnstatus = $this->verifyipn();

			// Validate the order notification
			if ($ipnstatus != "VERIFIED") {
				$txnstatus = $ipnstatus;
				new ShoppError('An unverifiable order notification was received from PayPal. Possible fraudulent order attempt! The order will be created, but the order payment status must be manually set to "Charged" when the payment can be verified.','paypal_txn_verification',SHOPP_TRXN_ERR);
			} else if (SHOPP_DEBUG) new ShoppError('IPN notification validated.',false,SHOPP_DEBUG_ERR);
			
			$transactionid = $_POST['txn_id'];
			$txnstatus = $this->status[$_POST['payment_status']];
		}
		
		$Order = $Shopp->Cart->data->Order;
		$Order->Totals = $Shopp->Cart->data->Totals;
		$Order->Items = $Shopp->Cart->contents;
		$Order->Cart = $Shopp->Cart->session;

		// Transaction successful, save the order
		$authentication = $Shopp->Settings->get('account_system');
		
		if ($authentication == "wordpress") {
			// Check if they've logged in
			// If the shopper is already logged-in, save their updated customer info
			if ($Shopp->Cart->data->login) {
				$user = get_userdata($Order->Customer->wpuser);
				$Order->Customer->wpuser = $user->ID;
				if (SHOPP_DEBUG) new ShoppError('Customer logged in, linking Shopp customer account to existing WordPress account.',false,SHOPP_DEBUG_ERR);
			}
			
			// Create WordPress account (if necessary)
			if (!$Order->Customer->wpuser) {
				if (SHOPP_DEBUG) new ShoppError('Creating a new WordPress account for this customer.',false,SHOPP_DEBUG_ERR);
				if(!$Order->Customer->new_wpuser()) new ShoppError(__('Account creation failed on order for customer id:' . $Order->Customer->id, "Shopp"), false,SHOPP_TRXN_ERR);
			}
		}

		// Create a WP-compatible password hash to go in the db
		if (empty($Order->Customer->id) && isset($Order->Customer->password))
			$Order->Customer->password = wp_hash_password($Order->Customer->password);
		$Order->Customer->save();

		$Order->Billing->customer = $Order->Customer->id;
		$Order->Billing->cardtype = "PayPal";
		$Order->Billing->save();

		if (!empty($Order->Shipping->address)) {
			$Order->Shipping->customer = $Order->Customer->id;
			$Order->Shipping->save();
		}
		
		$Promos = array();
		foreach ($Shopp->Cart->data->PromosApplied as $promo)
			$Promos[$promo->id] = $promo->name;

		$Purchase = new Purchase();
		$Purchase->customer = $Order->Customer->id;
		$Purchase->billing = $Order->Billing->id;
		$Purchase->shipping = $Order->Shipping->id;
		$Purchase->data = $Order->data;
		$Purchase->promos = $Promos;
		$Purchase->copydata($Order->Customer);
		$Purchase->copydata($Order->Billing);
		$Purchase->copydata($Order->Shipping,'ship');
		$Purchase->copydata($Shopp->Cart->data->Totals);
		$Purchase->freight = $Shopp->Cart->data->Totals->shipping;
		$Purchase->gateway = "PayPal".(isset($_POST['test_ipn']) && $_POST['test_ipn'] == "1"?" Sandbox":"");
		$Purchase->transactionid = $transactionid;
		$Purchase->transtatus = $txnstatus;
		if (isset($_POST['mc_fee'])) $Purchase->fees = $_POST['mc_fee'];
		$Purchase->ip = $Shopp->Cart->ip;
		$Purchase->save();
		// echo "<pre>"; print_r($Purchase); echo "</pre>";

		foreach($Shopp->Cart->contents as $Item) {
			$Purchased = new Purchased();
			$Purchased->copydata($Item);
			$Purchased->purchase = $Purchase->id;
			if (!empty($Purchased->download)) $Purchased->keygen();
			$Purchased->save();
			if ($Item->inventory) $Item->unstock();
		}

		// Empty cart on successful order
		$Shopp->Cart->unload();
		session_destroy();

		// Start new cart session
		$Shopp->Cart = new Cart();
		session_start();
		
		// Keep the user loggedin
		if ($Shopp->Cart->data->login)
			$Shopp->Cart->loggedin($Order->Customer);
		
		// Save the purchase ID for later lookup
		$Shopp->Cart->data->Purchase = new Purchase($Purchase->id);
		$Shopp->Cart->data->Purchase->load_purchased();
		// $Shopp->Cart->save();

		// Allow other WordPress plugins access to Purchase data to extend
		// what Shopp does after a successful transaction
		do_action_ref_array('shopp_order_success',array(&$Shopp->Cart->data->Purchase));
		
		// Send email notifications
		// notification(addressee name, email, subject, email template, receipt template)
		$Purchase->notification(
			"$Purchase->firstname $Purchase->lastname",
			$Purchase->email,
			__('Order Receipt','Shopp')
		);

		if ($Shopp->Settings->get('receipt_copy') == 1) {
			$Purchase->notification(
				'',
				$Shopp->Settings->get('merchant_email'),
				__('New Order','Shopp')
			);
		}
		
		if ($this->pdt) shopp_redirect($Shopp->link('thanks',false));

		exit();
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
		$connection = curl_init();
		if ($this->settings['testmode'] == "on")
			curl_setopt($connection,CURLOPT_URL,$this->sandbox_url); // Sandbox testing
		else curl_setopt($connection,CURLOPT_URL,$this->checkout_url); // Live		
		curl_setopt($connection, CURLOPT_SSL_VERIFYPEER, 0); 
		curl_setopt($connection, CURLOPT_SSL_VERIFYHOST, 0); 
		curl_setopt($connection, CURLOPT_NOPROGRESS, 1); 
		curl_setopt($connection, CURLOPT_VERBOSE, 1); 
		curl_setopt($connection, CURLOPT_FOLLOWLOCATION,1); 
		curl_setopt($connection, CURLOPT_POST, 1); 
		curl_setopt($connection, CURLOPT_POSTFIELDS, $this->transaction); 
		curl_setopt($connection, CURLOPT_TIMEOUT, SHOPP_GATEWAY_TIMEOUT); 
		curl_setopt($connection, CURLOPT_USERAGENT, SHOPP_GATEWAY_USERAGENT); 
		curl_setopt($connection, CURLOPT_REFERER, "http://".$_SERVER['SERVER_NAME']); 
		curl_setopt($connection, CURLOPT_FAILONERROR, 1);
		curl_setopt($connection, CURLOPT_RETURNTRANSFER, 1);
		$buffer = curl_exec($connection);   
		if ($error = curl_error($connection)) 
			new ShoppError($error,'paypal_standard_connection',SHOPP_COMM_ERR);
		curl_close($connection);
		
		$this->Response = $buffer;
		return $this->Response;
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
	
	function tag ($property,$options=array()) {
		global $Shopp;
		switch ($property) {
			case "button":
				$args = array();
				$args['shopp_xco'] = 'PayPal/PayPalStandard';
				if (isset($options['pagestyle'])) $args['pagestyle'] = $options['pagestyle'];
				$url = add_query_arg($args,$Shopp->link('checkout'));
				return '<p><a href="'.$url.'"><img src="'.$this->button.'" alt="Checkout with PayPal" /></a></p>';
		}
	}

	// Required, but not used
	function billing () {}
	
	function url ($url) {
		global $Shopp;
		if ($this->settings['testmode'] == "on") return $this->sandbox_url;
		else return $this->checkout_url;
	}
	
	function settings () {
		?>
			<th scope="row" valign="top"><label for="paypalstandard-enabled">PayPal Standard</label></th> 
			<td><input type="hidden" name="settings[PayPalStandard][billing-required]" value="off" /><input type="hidden" name="settings[PayPalStandard][enabled]" value="off" /><input type="checkbox" name="settings[PayPalStandard][enabled]" value="on" id="paypalstandard-enabled"<?php echo ($this->settings['enabled'] == "on")?' checked="checked"':''; ?>/><label for="paypalstandard-enabled"> <?php _e('Enable','Shopp'); ?> PayPal Standard</label>
				<div id="paypalstandard-settings">
		
				<p><input type="text" name="settings[PayPalStandard][account]" id="paypalstd-account" size="30" value="<?php echo $this->settings['account']; ?>"/><br />
				<?php _e('Enter your PayPal account e-mail.','Shopp'); ?></p>
								
				<p><label for="paypalstd-testmode"><input type="hidden" name="settings[PayPalStandard][testmode]" value="off" /><input type="checkbox" name="settings[PayPalStandard][testmode]" id="paypalstd-testmode" value="on"<?php echo ($this->settings['testmode'] == "on")?' checked="checked"':''; ?> /> Use the <a href="http://docs.shopplugin.net/PayPal_Sandbox" target="shoppdocs">PayPal Sandbox</a></label></p>
				
				<input type="hidden" name="settings[PayPalStandard][path]" value="<?php echo gateway_path(__FILE__); ?>"  />
				<input type="hidden" name="settings[xco_gateways][]" value="<?php echo gateway_path(__FILE__); ?>"  />
				
				</div>
			</td>
		<?php
	}
	
	function registerSettings () {
		?>
		xcosettings('#paypalstandard-enabled','#paypalstandard-settings');
		<?php
	}

} // end PayPalStandard class

?>