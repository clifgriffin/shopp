<?php
/**
 * PayPal Express
 * @class PayPalExpress
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 26 August, 2008
 * @package Shopp
 **/

class PayPalExpress {
	var $button = 'https://www.paypal.com/en_US/i/btn/btn_xpressCheckout.gif';
	var $sandbox_url = 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout';
	var $checkout_url = 'https://www.paypal.com/cgi-bin/webscr?cmd=_express-checkout';
	var $transaction = array();
	var $settings = array();
	var $Response = false;

	function PayPalExpress () {
		global $Shopp;
		$this->settings = $Shopp->Settings->get('PayPalExpress');
		$this->settings['merchant_email'] = $Shopp->Settings->get('merchant_email');

		// Capture PayPal Express transaction information as it becomes available
		if (!isset($Shopp->Cart->data->PayPalExpress)) $Shopp->Cart->data->PayPalExpress = new stdClass();
		if (!empty($_GET['token'])) $Shopp->Cart->data->PayPalExpress->token = $_GET['token'];
		if (!empty($_GET['PayerID'])) $Shopp->Cart->data->PayPalExpress->payerid = $_GET['PayerID'];

		return true;
	}
		
	function checkout () {
		global $Shopp;
		
		$_ = array();

		// Options
		$_['USER'] 					= $this->settings['username'];
		$_['PWD'] 					= $this->settings['password'];
		$_['SIGNATURE']				= $this->settings['signature'];

		$_['VERSION']				= "52.0";
		$_['METHOD']				= "SetExpressCheckout";
		$_['PAYMENTACTION']			= "Sale";
		
		// Transaction
		$_['CURRENCYCODE']			= "USD";
		$_['AMT']					= number_format($Shopp->Cart->data->Totals->total,2);
		$_['ITEMAMT']				= number_format($Shopp->Cart->data->Totals->subtotal,2);
		$_['SHIPPINGAMT']			= number_format($Shopp->Cart->data->Totals->shipping,2);
		$_['TAXAMT']				= number_format($Shopp->Cart->data->Totals->tax,2);

		// Disable shipping fields if no shipped items in cart
		if (!$Shopp->Cart->data->Shipping) $_['NOSHIPPING'] = 1;

		// Line Items
		foreach($Shopp->Cart->contents as $i => $Item) {
			$_['L_NAME'.$i]			= $Item->name.((!empty($Item->optionlabel))?' '.$Item->optionlabel:'');
			$_['L_AMT'.$i]			= number_format($Item->unitprice,2);
			$_['L_NUMBER'.$i]		= $i;
			$_['L_QTY'.$i]			= $Item->quantity;
			$_['L_TAXAMT'.$i]		= number_format($Item->taxes,2);
		}

		$_['RETURNURL']				= $Shopp->link('confirm-order',true).
										((SHOPP_PERMALINKS)?'?':'&').
										"shopp_xco=PayPal/PayPalExpress";
		$_['CANCELURL']				= $Shopp->link('cart',true);
		
		$this->transaction = $this->encode($_);
		$result = $this->send();
		
		if (!empty($result) && isset($result->token)){
			if ($this->settings['testmode'] == "on") header("Location: {$this->sandbox_url}&token=".$result->token);
			else header("Location: {$this->checkbox_url}&token=".$result->token);
			exit();
		}
			

		return false;	
	}
	
	function process () {
		global $Shopp;
		if (!isset($Shopp->Cart->data->PayPalExpress->token) && 
			!isset($Shopp->Cart->data->PayPalExpress->payerid)) return false;
			
		// Options
		$_['USER'] 					= $this->settings['username'];
		$_['PWD'] 					= $this->settings['password'];
		$_['SIGNATURE']				= $this->settings['signature'];

		$_['VERSION']				= "52.0";

		$_['METHOD'] 				= "DoExpressCheckoutPayment";
		$_['PAYMENTACTION']			= "Sale";
		$_['TOKEN'] 				= $Shopp->Cart->data->PayPalExpress->token;
		$_['PAYERID'] 				= $Shopp->Cart->data->PayPalExpress->payerid;

		// Transaction
		$_['CURRENCYCODE']			= "USD";
		$_['AMT']					= number_format($Shopp->Cart->data->Totals->total,2);
		$_['ITEMAMT']				= number_format($Shopp->Cart->data->Totals->subtotal,2);
		$_['SHIPPINGAMT']			= number_format($Shopp->Cart->data->Totals->shipping,2);
		$_['TAXAMT']				= number_format($Shopp->Cart->data->Totals->tax,2);

		// Disable shipping fields if no shipped items in cart
		if (!$Shopp->Cart->data->Shipping) $_['NOSHIPPING'] = 1;

		// Line Items
		foreach($Shopp->Cart->contents as $i => $Item) {
			$_['L_NAME'.$i]			= $Item->name.((!empty($Item->option))?' '.$Item->option:'');
			$_['L_AMT'.$i]			= number_format($Item->unitprice,2);
			$_['L_NUMBER'.$i]		= $i;
			$_['L_QTY'.$i]			= $Item->quantity;
			$_['L_TAXAMT'.$i]		= number_format($Item->taxes,2);
		}

		$this->transaction = $this->encode($_);
		$result = $this->send();
		
		// If the transaction is a success, get the transaction details, 
		// build the purchase receipt, save it and return it
		if (strtolower($result->ack) == "success") {
			$_ = array();
			// Options
			$_['USER'] 					= $this->settings['username'];
			$_['PWD'] 					= $this->settings['password'];
			$_['SIGNATURE']				= $this->settings['signature'];

			$_['VERSION']				= "52.0";
			
			$_['METHOD'] 				= "GetTransactionDetails";
			$_['TRANSACTIONID']			= $result->transactionid;
			
			$this->transaction = $this->encode($_);
			$result = $this->send();
			
			$Customer = new Customer();
			$Customer->firstname = $result->firstname;
			$Customer->lastname = $result->lastname;
			$Customer->email = $result->email;
			$Customer->phone = $result->phonenum;
			$Customer->save();
			
			$Shipping = new Shipping();
			$Shipping->customer = $Customer->id;
			$Shipping->address = $result->shiptostreet;
			$Shipping->xaddress = $result->shiptostreet2;
			$Shipping->city = $result->shiptocity;
			$Shipping->state = $result->shiptostate;
			$Shipping->country = $result->shiptocountrycode;
			$Shipping->postcode = $result->shiptozip;
			$Shipping->save();

			$Billing = new Billing();
			$Billing->customer = $Customer->id;
			$Billing->cardtype = "PayPal";
			$Billing->address = $Shipping->address;
			$Billing->xaddress = $Shipping->xaddress;
			$Billing->city = $Shipping->city;
			$Billing->state = $Shipping->state;
			$Billing->country = $Shipping->country;
			$Billing->postcode = $Shipping->postcode;
			$Billing->save();
			
			$Purchase = new Purchase();
			$Purchase->customer = $Customer->id;
			$Purchase->billing = $Billing->id;
			$Purchase->shipping = $Order->Shipping->id;
			$Purchase->copydata($Customer);
			$Purchase->copydata($Billing);
			$Purchase->copydata($Shipping,'ship');
			$Purchase->copydata($Shopp->Cart->data->Totals);
			$Purchase->freight = $Shopp->Cart->data->Totals->shipping;
			$Purchase->gateway = "PayPal Express";
			$Purchase->transactionid = $result->transactionid;
			$Purchase->fees = $result->feeamt;
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
			$Error = new stdClass();
			$Error->code = $this->Response->l_errorcode[0];
			$Error->message = $this->Response->l_shortmessage[0];
			return $Error;
		}
	}
		
	function send () {
		$connection = curl_init();
		if ($this->settings['testmode'] == "on")
			curl_setopt($connection,CURLOPT_URL,"https://api-3t.sandbox.paypal.com/nvp"); // Sandbox testing
		else curl_setopt($connection,CURLOPT_URL,"https://api-3t.paypal.com/nvp"); // Live		
		curl_setopt($connection, CURLOPT_SSL_VERIFYPEER, 0); 
		curl_setopt($connection, CURLOPT_SSL_VERIFYHOST, 0); 
		curl_setopt($connection, CURLOPT_NOPROGRESS, 1); 
		curl_setopt($connection, CURLOPT_VERBOSE, 1); 
		curl_setopt($connection, CURLOPT_FOLLOWLOCATION,0); 
		curl_setopt($connection, CURLOPT_POST, 1); 
		curl_setopt($connection, CURLOPT_POSTFIELDS, $this->transaction); 
		curl_setopt($connection, CURLOPT_TIMEOUT, 60); 
		curl_setopt($connection, CURLOPT_USERAGENT, SHOPP_GATEWAY_USERAGENT); 
		curl_setopt($connection, CURLOPT_REFERER, "https://".$_SERVER['SERVER_NAME']); 
		curl_setopt($connection, CURLOPT_RETURNTRANSFER, 1);
		$buffer = curl_exec($connection);
		curl_close($connection);

		$Response = $this->response($buffer);
		return $Response;
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
			$key = strtolower($key);
			$_->{$key} = $value;
		}
		
		$this->Response = $_;
		return $_;
	}
	
	
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
	
	function tag ($property,$options=array()) {
		global $Shopp;
		switch ($property) {
			case "button": 
				if (SHOPP_PERMALINKS) $url = $Shopp->link('checkout')."?shopp_xco=PayPal/PayPalExpress";
				else $url = $Shopp->link('checkout')."&shopp_xco=PayPal/PayPalExpress";
				return '<p class="submit"><a href="'.$url.'"><img src="'.$this->button.'" alt="Checkout with PayPal" /></a></p>';
		}
	}
	
	function settings () {
		global $Shopp;
		?>
		<p><input type="text" name="settings[PayPalExpress][username]" id="paypalxp-username" size="30" value="<?php echo $this->settings['username']; ?>"/><br />
		Enter your PayPal Express API Username.</p>
		<p><input type="password" name="settings[PayPalExpress][password]" id="paypalxp-password" size="16" value="<?php echo $this->settings['password']; ?>" /><br />
		Enter your PayPal Express API Password.</p>
		<p><input type="text" name="settings[PayPalExpress][signature]" id="paypalxp-signature" size="48" value="<?php echo $this->settings['signature']; ?>" /><br />
		Enter your PayPal Express API Signature.</p>
		<p><label for="paypalxp-testmode"><input type="hidden" name="settings[PayPalExpress][testmode]" value="off" /><input type="checkbox" name="settings[PayPalExpress][testmode]" id="paypalxp-testmode" size="48" value="on"<?php echo ($this->settings['testmode'])?' checked="checked"':''; ?> /> Enable test mode</label></p>
		<?php
	}

} // end PayPalExpress class

?>