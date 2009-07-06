<?php
/**
 * PayJunction
 * @class PayJunction
 *
 * @author Jonathan Davis
 * @version 1.0.0
 * @copyright Ingenesis Limited, 28 May, 2008
 * @package Shopp
 **/

class PayJunction {
	var $transaction = array();
	var $settings = array();
	var $Response = false;
	var $production = "https://payjunction.com/quick_link";
	var $demo = "https://demo.payjunction.com/quick_link";
	var $cards = array("Visa", "MasterCard", "American Express", "Discover");
	var $codes = array(
		"FE" => "There was a format error with your Trinity Gateway Service (API) request.",
		"AE" => "Address verification failed because address did not match.",
		"ZE" => "Address verification failed because zip did not match.",
		"XE" => "Address verification failed because zip and address did not match.",
		"YE" => "Address verification failed because zip and address did not match.",
		"OE" => "Address verification failed because address or zip did not match.",
		"UE" => "Address verification failed because cardholder address unavailable.",
		"RE" => "Address verification failed because address verification system is not working.",
		"SE" => "Address verification failed because address verification system is unavailable.",
		"EE" => "Address verification failed because transaction is not a mail or phone order.",
		"GE" => "Address verification failed because international support is unavailable.",
		"CE" => "Declined because CVV2/CVC2 code did not match.",
		"NL" => "Aborted because of a system error, please try again later.",
		"AB" => "Aborted because of an upstream system error, please try again later.",
		"04" => "Declined. Pick up card.",
		"07" => "Declined. Pick up card (Special Condition).",
		"41" => "Declined. Pick up card (Lost).",
		"43" => "Declined. Pick up card (Stolen).",
		"13" => "Declined because of the amount is invalid.",
		"14" => "Declined because the card number is invalid.",
		"80" => "Declined because of an invalid date.",
		"05" => "Declined. Do not honor.",
		"51" => "Declined because of insufficient funds.",
		"N4" => "Declined because the amount exceeds issuer withdrawal limit.",
		"61" => "Declined because the amount exceeds withdrawal limit.",
		"62" => "Declined because of an invalid service code (restricted).",
		"65" => "Declined because the card activity limit exceeded.",
		"93" => "Declined because there a violation (the transaction could not be completed).",
		"06" => "Declined because address verification failed.",
		"54" => "Declined because the card has expired.",
		"15" => "Declined because there is no such issuer.",
		"96" => "Declined because of a system error.",
		"N7" => "Declined because of a CVV2/CVC2 mismatch.",
		"M4" => "Declined.",
		"DT" => "Duplicate Transaction"
	);

	function PayJunction (&$Order="") {
		global $Shopp;
		$this->settings = $Shopp->Settings->get('PayJunction');
		$this->settings['merchant_email'] = $Shopp->Settings->get('merchant_email');
		if (!isset($this->settings['cards'])) $this->settings['cards'] = $this->cards;

		if (!empty($Order)) $this->build($Order);
		return true;
	}
	
	function process () {
		$this->send();
		if ($this->Response->dc_response_code == "00" || 
			$this->Response->dc_response_code == "85") return true;
		else return false;
	}
	
	function transactionid () {
		if (!empty($this->Response)) return $this->Response->dc_transaction_id;
	}
	
	function error () {
		if (!empty($this->Response)) 
			return new ShoppError($this->Response->dc_response_message,'payjunction_error',SHOPP_TRXN_ERR,
				array('code'=>$this->Response->dc_response_code));
	}
	
	function build (&$Order) {
		$_ = array();

		// Options
		$_['dc_logon'] 				= $this->settings['login'];
		$_['dc_password'] 			= $this->settings['password'];
		if ($this->settings['testmode'] == "on") {
			$_['dc_logon'] 			= "pj-ql-01";
			$_['dc_password'] 		= "pj-ql-01p";
		}
		
		$_['dc_version'] 			= "1.2";
		$_['dc_transaction_type']	= "AUTHORIZATION_CAPTURE";
		$_['dc_security']			= "true";
	
		// Customer Contact
		$_['x_first_name']			= $Order->Customer->firstname;
		$_['x_last_name']			= $Order->Customer->lastname;
		$_['x_email']				= $Order->Customer->email;
		$_['x_phone']				= $Order->Customer->phone;
		
		// Billing
		$_['dc_name']				= $Order->Billing->cardholder;
		$_['dc_number']				= $Order->Billing->card;
		$_['dc_expiration_month']	= date("m",$Order->Billing->cardexpires);
		$_['dc_expiration_year']	= date("Y",$Order->Billing->cardexpires);
		$_['dc_verification_number']= $Order->Billing->cvv;

		$_['dc_address']			= $Order->Billing->address.", ".$Order->Billing->xaddress;
		$_['dc_city']				= $Order->Billing->city;
		$_['dc_state']				= $Order->Billing->state;
		$_['dc_zipcode']			= $Order->Billing->postcode;
		$_['dc_country']			= $Order->Billing->country;
		
		// Transaction
		// $_['dc_transaction_id']		= $Order->Cart;
		$_['dc_transaction_amount']	= number_format($Order->Totals->subtotal,2);
		$_['dc_shipping_amount']	= number_format($Order->Totals->shipping,2);
		$_['dc_tax_amount']			= number_format($Order->Totals->tax,2);
		
		$this->transaction = $this->encode($_);
		return true;
	}
	
	function send () {
		$url = $this->production;
		if ($this->settings['testmode'] == "on") $url = $this->demo;

		$connection = curl_init();
		curl_setopt($connection, CURLOPT_URL,$url);
		curl_setopt($connection, CURLOPT_SSL_VERIFYPEER, 0); 
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
		if ($error = curl_error($connection)) 
			new ShoppError($error,'payjunction_connection',SHOPP_COMM_ERR);
		curl_close($connection);

		$this->Response = $this->response($buffer);
		return $this->Response;
	}
	
	function response ($buffer) {
		$data = explode(chr(28),$buffer);
		$_ = new stdClass();
		foreach ($data as $pair) {
			list($key,$value) = explode("=",$pair);
			$_->{$key} = $value;
		}
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
	
	function settings () {
		global $Shopp;
		?>
		<tr id="payjunction-settings" class="addon">
			<th scope="row" valign="top">PayJunction</th>
			<td>
				<div><input type="text" name="settings[PayJunction][login]" id="payjunction_loginname" value="<?php echo $this->settings['login']; ?>" size="16" /><br /><label for="payjunction_loginname"><?php _e('Enter your PayJunction login name.'); ?></label></div>
				<div><input type="password" name="settings[PayJunction][password]" id="payjunction_pw" value="<?php echo $this->settings['password']; ?>" size="24" /><br /><label for="payjunction_pw"><?php _e('Enter your PayJunction password.'); ?></label></div>
				<div><input type="hidden" name="settings[PayJunction][testmode]" value="off"><input type="checkbox" name="settings[PayJunction][testmode]" id="payjunction_testmode" value="on"<?php echo ($this->settings['testmode'] == "on")?' checked="checked"':''; ?> /><label for="payjunction_testmode"> <?php _e('Enable test mode'); ?></label></div>
				<div><strong>Accept these cards:</strong>
				<ul class="cards"><?php foreach($this->cards as $id => $card): 
					$checked = "";
					if (in_array($card,$this->settings['cards'])) $checked = ' checked="checked"';
				?>
					<li><input type="checkbox" name="settings[PayJunction][cards][]" id="payjunction_cards_<?php echo $id; ?>" value="<?php echo $card; ?>" <?php echo $checked; ?> /><label for="payjunction_cards_<?php echo $id; ?>"> <?php echo $card; ?></label></li>
				<?php endforeach; ?></ul></div>
				
				<input type="hidden" name="module[<?php echo basename(__FILE__); ?>]" value="PayJunction" />
			</td>
		</tr>
		<?php
	}
	
	function registerSettings () {
		?>
		gatewayHandlers.register('<?php echo addslashes(gateway_path(__FILE__)); ?>','payjunction-settings');
		<?php
	}

} // end PayJunction class

?>