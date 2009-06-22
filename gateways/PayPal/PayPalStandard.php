<?php
/**
 * PayPal Standard
 * @class PayPalStandard
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 27 May, 2009
 * @package Shopp
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
	var $currencies = array("USD", "AUD", "CAD", "CHF", "CZK", "DKK", "EUR", "GBP", 
							"HKD", "HUF", "JPY", "NOK", "NZD", "PLN", "SEK", "SGD");
	var $locales = array("AT" => "de_DE", "AU" => "en_AU", "BE" => "en_US", "CA" => "en_US",
							"CH" => "de_DE", "CN" => "zh_CN", "DE" => "de_DE", "ES" => "es_ES",
							"FR" => "fr_FR", "GB" => "en_GB", "GF" => "fr_FR", "GI" => "en_US",
							"GP" => "fr_FR", "IE" => "en_US", "IT" => "it_IT", "JP" => "ja_JP",
							"MQ" => "fr_FR", "NL" => "nl_NL", "PL" => "pl_PL", "RE" => "fr_FR",
							"US" => "en_US");

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
			
		if (isset($_POST['checkout']) && 
			$_POST['checkout'] == "process" && 
			!$loginproc) $this->checkout();
		
		// Capture processed payment
		if (isset($_GET['tx'])) $_POST['checkout'] = "confirmed";
			
		// Capture processed payment
		// if (isset($_POST['order_number'])
		// 	&& isset($_POST['credit_card_processed'])
		// 	 && $_POST['credit_card_processed'] == "Y") $_POST['checkout'] = "confirmed";

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
		
		header("Location: ".add_query_arg('shopp_xco','PayPal/PayPalStandard',$Shopp->link('confirm-order',false)));
		exit();
	}
	
	/**
	 * form()
	 * Builds a hidden form to submit to PayPal when confirming the order for processing */
	function form ($form) {
		global $Shopp;
		
		$_ = array();

		$_['cmd'] 					= "_cart";
		$_['upload'] 				= 1;
		$_['business']				= $this->settings['account'];

		// Options
		$_['return']				= $Shopp->link();
		$_['cancel_return']			= $Shopp->link('cart');
		if (SHOPP_PERMALINKS)
			$_['notify_url']		= $Shopp->link('confirm-order').'?shopp_xco=PayPal/PayPalStandard';
		else
			$_['notify_url']			= add_query_arg('shopp_xco','PayPal/PayPalStandard',$Shopp->link('confirm-order'));
		$_['rm']					= 2; // Return method POST
		
		// Include page style option, if provided
		if (isset($_GET['pagestyle'])) $_['pagestyle'] = $_GET['pagestyle'];

		if (isset($Shopp->Cart->data->Order->data['paypal-custom']))
			$_['custom'] = htmlentities($Shopp->Cart->data->Order->data['paypal-custom']);
		
		// Transaction
		$_['currency_code']			= $this->settings['currency_code'];

		// Disable shipping fields if no shipped items in cart
		if (!$Shopp->Cart->data->Shipping) $_['no_shipping'] = 1;

		// Line Items
		foreach($Shopp->Cart->contents as $i => $Item) {
			$id=$i+1;
			$_['item_number_'.$id]		= $id;
			$_['item_name_'.$id]		= $Item->name.((!empty($Item->optionlabel))?' '.$Item->optionlabel:'');
			$_['amount_'.$id]			= number_format($Item->unitprice,2);
			$_['quantity_'.$id]			= $Item->quantity;
			$_['weight_'.$id]			= $Item->quantity;
			// $_['tax_'.$id]				= number_format($Item->taxes,2);
			// $_['handling_'.$id]			= number_format($Item->shipfee,2);
		}
		
		$_['discount_amount_cart'] 		= number_format($Shopp->Cart->data->Totals->discount,2);
		$_['tax_cart']					= number_format($Shopp->Cart->data->Totals->tax,2);
		$_['handling_cart']					= number_format($Shopp->Cart->data->Totals->shipping,2);
		$_['amount']					= number_format($Shopp->Cart->data->Totals->total,2);
		
				
		return $form.$this->format($_);
	}
	
	
	function details () {
		global $Shopp;
		if (!isset($Shopp->Cart->data->PayPalStandard->token) && 
			!isset($Shopp->Cart->data->PayPalStandard->payerid)) return false;

		$_ = $this->headers();

   		$_['METHOD'] 				= "GetExpressCheckoutDetails";
		$_['TOKEN'] 				= $Shopp->Cart->data->PayPalStandard->token;

		$this->transaction = $this->encode($_);
		$this->send();
		
		$Customer = $Shopp->Cart->data->Order->Customer;
		$Customer->firstname = $this->Response->firstname;
		$Customer->lastname = $this->Response->lastname;
		$Customer->email = $this->Response->email;
		$Customer->phone = $this->Response->phonenum;
		
		$Shipping = $Shopp->Cart->data->Order->Shipping;		
		$Shipping->address = $this->Response->shiptostreet;
		$Shipping->xaddress = $this->Response->shiptostreet2;
		$Shipping->city = $this->Response->shiptocity;
		$Shipping->state = $this->Response->shiptostate;
		$Shipping->country = $this->Response->shiptocountrycode;
		$Shipping->postcode = $this->Response->shiptozip;

		$Billing = $Shopp->Cart->data->Order->Billing;
		$Billing->cardtype = "PayPal";
		$Billing->address = $Shipping->address;
		$Billing->xaddress = $Shipping->xaddress;
		$Billing->city = $Shipping->city;
		$Billing->state = $Shipping->state;
		$Billing->country = $Shipping->country;
		$Billing->postcode = $Shipping->postcode;

		$Shopp->Cart->updated();
		
	} 
	
	function ipn () {
		$_ = array();
		$_['cmd'] = "_notify-synch";
		$_['tx'] = $_GET['tx'];
		$_['at'] = "";
		
		$this->transaction = $this->encode($_);
	}
	
	
	function process () {
		global $Shopp;
		
		
		
		
		
		
		
		
		exit();
				
		$_ = $this->headers();

		$_['METHOD'] 				= "DoExpressCheckoutPayment";
		$_['PAYMENTACTION']			= "Sale";
		$_['TOKEN'] 				= $Shopp->Cart->data->PayPalStandard->token;
		$_['PAYERID'] 				= $Shopp->Cart->data->PayPalStandard->payerid;

		// Transaction
		$_ = array_merge($_,$this->purchase());

		$this->transaction = $this->encode($_);
		$result = $this->send();
		if (!$result) {
			new ShoppError(__('No response was received from PayPal. The order cannot be processed.','Shopp'),'paypalstandard_noresults',SHOPP_COMM_ERR);
		}
		
		// If the transaction is a success, get the transaction details, 
		// build the purchase receipt, save it and return it
		if (strtolower($result->ack) == "success") {
			$_ = $this->headers();
			
			$_['METHOD'] 				= "GetTransactionDetails";
			$_['TRANSACTIONID']			= $this->Response->transactionid;
			
			$this->transaction = $this->encode($_);
			$result = $this->send();
			if (!$result) {
				new ShoppError(__('Details for the order were not provided by PayPal.','Shopp'),'paypalstandard_notrxn_details',SHOPP_COMM_ERR);
				return false;
			}

			$Order = $Shopp->Cart->data->Order;
			$Order->Totals = $Shopp->Cart->data->Totals;
			$Order->Items = $Shopp->Cart->contents;
			$Order->Cart = $Shopp->Cart->session;

			$Order->Customer->save();

			$Order->Billing->customer = $Order->Customer->id;
			$Order->Billing->cardtype = "PayPal";
			$Order->Billing->save();

			$Order->Shipping->customer = $Order->Customer->id;
			$Order->Shipping->save();
			
			$Purchase = new Purchase();
			$Purchase->customer = $Order->Customer->id;
			$Purchase->billing = $Order->Billing->id;
			$Purchase->shipping = $Order->Shipping->id;
			$Purchase->copydata($Order->Customer);
			$Purchase->copydata($Order->Billing);
			$Purchase->copydata($Order->Shipping,'ship');
			$Purchase->copydata($Order->Totals);
			$Purchase->freight = $Order->Totals->shipping;
			$Purchase->gateway = "PayPal Express";
			$Purchase->transactionid = $this->Response->transactionid;
			$Purchase->fees = $this->Response->feeamt;
			$Purchase->save();

			foreach($Shopp->Cart->contents as $Item) {
				$Purchased = new Purchased();
				$Purchased->copydata($Item);
				$Purchased->purchase = $Purchase->id;
				if (!empty($Purchased->download)) $Purchased->keygen();
				$Purchased->save();
				if ($Item->inventory) $Item->unstock();
			}

			return $Purchase;
		}
		
		// Fail by default
		return false;
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
		curl_setopt($connection, CURLOPT_TIMEOUT, 5); 
		curl_setopt($connection, CURLOPT_USERAGENT, SHOPP_GATEWAY_USERAGENT); 
		curl_setopt($connection, CURLOPT_REFERER, "http://".$_SERVER['SERVER_NAME']); 
		curl_setopt($connection, CURLOPT_FAILONERROR, 1);
		curl_setopt($connection, CURLOPT_RETURNTRANSFER, 1);
		$buffer = curl_exec($connection);   
		if ($error = curl_error($connection)) 
			new ShoppError($error,'paypal_express_connection',SHOPP_COMM_ERR);
		curl_close($connection);
		
		$this->Response = false;
		$this->Response = $this->response($buffer);
		return $this->Response;
	}
	
	function response ($buffer) {
		$_ = new stdClass();
		$r = array();
		$pairs = split("&",$buffer);
		foreach($pairs as $pair) {
			list($key,$value) = split("=",$pair);
			if (preg_match("/(\w*?)(\d+)/",$key,$matches)) {
				if (!isset($r[$matches[1]])) $r[$matches[1]] = array();
				$r[$matches[1]][$matches[2]] = urldecode($value);
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
	

	/**
	 * encode()
	 * Builds a get/post encoded string from the provided $data */
	function encode ($data) {
		$query = "";
		foreach($data as $key => $value) {
			if (is_array($value)) {
				foreach($value as $item) {
					if (strlen($query) > 0) $query .= "&";
					$query .= "$key=".urlencode($item);
				}
			} else {
				if (strlen($query) > 0) $query .= "&";
				$query .= "$key=".urlencode($value);
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
		return $this->sandbox_url;
	}
	
	function settings () {
		?>
			<th scope="row" valign="top"><label for="paypalstandard-enabled">PayPal Standard</label></th> 
			<td><input type="hidden" name="settings[PayPalStandard][billing-required]" value="off" /><input type="hidden" name="settings[PayPalStandard][enabled]" value="off" /><input type="checkbox" name="settings[PayPalStandard][enabled]" value="on" id="paypalstandard-enabled"<?php echo ($this->settings['enabled'] == "on")?' checked="checked"':''; ?>/><label for="paypalstandard-enabled"> <?php _e('Enable','Shopp'); ?> PayPal Standard</label>
				<div id="paypalstandard-settings">
		
				<p><input type="text" name="settings[PayPalStandard][account]" id="paypalstd-account" size="30" value="<?php echo $this->settings['account']; ?>"/><br />
				<?php __('Enter your PayPal account e-mail.','Shopp'); ?></p>
								
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