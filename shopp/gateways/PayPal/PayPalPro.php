<?php
/**
 * PayPal Pro
 * @class PayPalPro
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 19 August, 2008
 * @package Shopp
 **/

class PayPalPro {
	var $transaction = array();
	var $settings = array();
	var $Response = false;

	function PayPalPro (&$Order="") {
		global $Shopp;
		$this->settings = $Shopp->Settings->get('PayPalPro');
		$this->settings['merchant_email'] = $Shopp->Settings->get('merchant_email');
		
		if (!empty($Order)) $this->build($Order);
		return true;
	}
	
	function process () {
		$this->Response = $this->send();
		if ($this->Response->ack == "Success" || 
			$this->Response->ack == "SuccessWithWarning") return true;
		else return false;
	}
	
	function transactionid () {
		if (!empty($this->Response)) return $this->Response->transactionid;
	}
	
	function error () {
		if (!empty($this->Response)) {
			$Error = new stdClass();
			$Error->code = $this->Response->errorcodes[0];
			$Error->message = $this->Response->longerror[0];
			return $Error;
		}
	}
	
	function build ($Order) {
		$_ = array();

		// Options
		$_['USER'] 					= $this->settings['username'];
		$_['PWD'] 					= $this->settings['password'];
		$_['SIGNATURE']				= $this->settings['signature'];

		$_['VERSION']				= "52.0";
		$_['METHOD']				= "DoDirectPayment";
		$_['PAYMENTACTION']			= "Sale";
		$_['IPADDRESS']				= $_SERVER["REMOTE_ADDR"];
		$_['RETURNFMFDETAILS']		= "1"; // optional - return fraud management filter data
		
		// Customer Contact
		$_['FIRSTNAME']				= $Order->Customer->firstname;
		$_['LASTNAME']				= $Order->Customer->lastname;
		$_['EMAIL']					= $Order->Customer->email;
		$_['PHONENUM']				= $Order->Customer->phone;
		
		// Billing
		$_['CREDITCARDTYPE']		= $Order->Billing->cardtype;
		$_['ACCT']					= $Order->Billing->card;
		$_['EXPDATE']				= date("mY",$Order->Billing->cardexpires);
		$_['CVV2']					= $Order->Billing->cvv;
		$_['STREET']				= $Order->Billing->address;
		$_['STREET2']				= $Order->Billing->xaddress;
		$_['CITY']					= $Order->Billing->city;
		$_['STATE']					= $Order->Billing->state;
		$_['ZIP']					= $Order->Billing->postcode;
		$_['COUNTRYCODE']			= $Order->Billing->country;
		
		// Shipping
		$_['SHOPTONAME'] 			= $Order->Customer->firstname.' '.$Order->Customer->lastname;
		$_['SHIPTOSTREET']			= $Order->Shipping->address;
		$_['SHIPTOSTREET2']			= $Order->Shipping->xaddress;
		$_['SHIPTOCITY']			= $Order->Shipping->city;
		$_['SHIPTOSTATE']			= $Order->Shipping->state;
		$_['SHIPTOZIP']				= $Order->Shipping->postcode;
		$_['SHIPTOCOUNTRYCODE']		= $Order->Shipping->country;
		$_['SHIPTOPHONENUM']		= $Order->Customer->phone;
		
		// Transaction
		$_['AMT']					= $Order->Totals->total;
		$_['ITEMAMT']				= $Order->Totals->subtotal;
		$_['SHIPPINGAMT']			= $Order->Totals->shipping;
		$_['TAXAMT']				= $Order->Totals->tax;
		
		// Line Items
		foreach($Order->Items as $i => $Item) {
			$_['L_NAME'.$i]			= $Item->name.((!empty($Item->option))?' '.$Item->option:'');
			$_['L_AMT'.$i]			= $Item->unitprice;
			$_['L_NUMBER'.$i]		= $i;
			$_['L_QTY'.$i]			= $Item->quantity;
			$_['L_TAXAMT'.$i]		= $Item->taxes;
		}
		
		$this->transaction = "";
		foreach($_ as $key => $value) {
			if (is_array($value)) {
				foreach($value as $item) {
					if (strlen($this->transaction) > 0) $this->transaction .= "&";
					$this->transaction .= "$key=".urlencode($item);
				}
			} else {
				if (strlen($this->transaction) > 0) $this->transaction .= "&";
				$this->transaction .= "$key=".urlencode($value);
			}
		}
	}
	
	function send () {
		$connection = curl_init();
		if ($this->settings['testmode'] == "on")
			curl_setopt($connection,CURLOPT_URL,"https://api-3t.sandbox.paypal.com/nvp"); // Sandbox testing
		else curl_setopt($connection,CURLOPT_URL,"https://api-3t.paypal.com/nvpasd"); // Live		
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
		echo $this->transaction;
		print_r($r);
		
		
		$_->ack = $r['ACK'];
		$_->errorcodes = $r['L_ERRORCODE'];
		$_->shorterror = $r['L_SHORTMESSAGE'];
		$_->longerror = $r['L_LONGMESSAGE'];
		$_->severity = $r['L_SEVERITYCODE'];
		$_->timestamp = $r['TIMESTAMP'];
		$_->correlationid = $r['CORRELATIONID'];
		$_->version = $r['VERSION'];
		$_->build = $r['BUILD'];
		
		$_->transactionid = $r['TRANSACTIONID'];
		$_->amt = $r['AMT'];
		$_->avscode = $r['AVSCODE'];
		$_->cvv2match = $r['CVV2MATCH'];

		return $_;
	}
	
	function settings () {
		global $Shopp;
		$Shopp->Settings->save('gateway_cardtypes',array("Visa","MasterCard","Discover","American Express"));
		?>
				
		var paypalpro_settings = function () {
			addSetting("PayPal Pro Login",
							{'name':'settings[PayPalPro][username]',
							 'id':'gateway_username',
							 'type':'text',
							 'size':'30',
							 'value':'<?php echo $this->settings['username']; ?>'},
							 "Enter your PayPal API Username.");

			addSetting("PayPal Pro Password",
							{'name':'settings[PayPalPro][password]',
							 'id':'gateway_password',
							 'type':'password',
							 'size':'16',
							 'value':'<?php echo $this->settings['password']; ?>'},
							 "Enter your PayPal API Password.");

			addSetting("PayPal Pro Signature",
							{'name':'settings[PayPalPro][signature]',
							 'id':'gateway_signature',
							 'type':'text',
							 'size':'48',
							 'value':'<?php echo $this->settings['signature']; ?>'},
							 "Enter your PayPal API Signature.");


			addSetting("PayPal Pro Test Mode",
							{'name':'settings[PayPalPro][testmode]',
							 'id':'gateway_testmode',
							 'type':'checkbox',
							 'value':'on',
							 'unchecked':'off',
							 'checked':<?php echo ($this->settings['testmode'] == "on")?'true':'false'; ?>},
							 "Enabled");
			}
			
			gatewayHandlers.register('<?php echo __FILE__; ?>',paypalpro_settings);

		<?
	}

} // end AuthorizeNet class

?>