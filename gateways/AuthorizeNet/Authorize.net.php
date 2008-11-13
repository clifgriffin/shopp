<?php
/**
 * Authorize.Net
 * @class AuthorizeNet
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 30 March, 2008
 * @package Shopp
 **/

class AuthorizeNet {
	var $transaction = array();
	var $settings = array();
	var $Response = false;
	var $cards = array("Visa", "MasterCard", "American Express", "Discover", "JCB", "Diner’s Club", "EnRoute");

	function AuthorizeNet (&$Order="") {
		global $Shopp;
		$this->settings = $Shopp->Settings->get('Authorize.Net');
		$this->settings['merchant_email'] = $Shopp->Settings->get('merchant_email');
		
		if (!empty($Order)) $this->build($Order);
		return true;
	}
	
	function process () {
		$this->Response = $this->send();
		if ($this->Response->code == 1) return true;
		else return false;
	}
	
	function transactionid () {
		if (!empty($this->Response)) return $this->Response->transactionid;
	}
	
	function error () {
		if (!empty($this->Response)) {
			$Error = new stdClass();
			$Error->code = $this->Response->reasoncode;
			$Error->message = $this->Response->reason;
			return $Error;
		}
	}
	
	function build (&$Order) {
		$_ = array();

		// Options
		$_['x_test_request']		= $this->settings['testmode']; // Set "TRUE" while testing
		$_['x_login'] 				= $this->settings['login'];
		$_['x_password'] 			= $this->settings['password'];
		$_['x_Delim_Data'] 			= "TRUE"; 
		$_['x_Delim_Char'] 			= ","; 
		$_['x_Encap_Char'] 			= ""; 
		$_['x_version'] 			= "3.1";
		$_['x_relay_response']		= "FALSE";
		$_['x_type'] 				= "AUTH_CAPTURE";
		$_['x_method']				= "CC";
		$_['x_email_customer']		= "FALSE";
		$_['x_merchant_email']		= $this->settings['merchant_email'];
		
		// Required Fields
		$_['x_amount']				= $Order->Totals->total;
		$_['x_customer_ip']			= $_SERVER["REMOTE_ADDR"];
		$_['x_fp_sequence']			= $Order->Cart;
		$_['x_fp_timestamp']		= time();
		// $_['x_fp_hash']				= hash_hmac("md5","{$_['x_login']}^{$_['x_fp_sequence']}^{$_['x_fp_timestamp']}^{$_['x_amount']}",$_['x_password']);
		
		// Customer Contact
		$_['x_first_name']			= $Order->Customer->firstname;
		$_['x_last_name']			= $Order->Customer->lastname;
		$_['x_email']				= $Order->Customer->email;
		$_['x_phone']				= $Order->Customer->phone;
		
		// Billing
		$_['x_card_num']			= $Order->Billing->card;
		$_['x_exp_date']			= date("my",$Order->Billing->cardexpires);
		$_['x_card_code']			= $Order->Billing->cvv;
		$_['x_address']				= $Order->Billing->address;
		$_['x_city']				= $Order->Billing->city;
		$_['x_state']				= $Order->Billing->state;
		$_['x_zip']					= $Order->Billing->postcode;
		$_['x_country']				= $Order->Billing->country;
		
		// Shipping
		$_['x_ship_to_first_name']  = $Order->Customer->firstname;
		$_['x_ship_to_last_name']	= $Order->Customer->lastname;
		$_['x_ship_to_address']		= $Order->Shipping->address;
		$_['x_ship_to_city']		= $Order->Shipping->city;
		$_['x_ship_to_state']		= $Order->Shipping->state;
		$_['x_ship_to_zip']			= $Order->Shipping->postcode;
		$_['x_ship_to_country']		= $Order->Shipping->country;
		
		// Transaction
		$_['x_freight']				= $Order->Totals->shipping;
		$_['x_tax']					= $Order->Totals->tax;
		
		// Line Items
		$i = 1;
		foreach($Order->Items as $Item) {
			$_['x_line_item'][] = ($i++)."<|>".$Item->name.((sizeof($Item->options) > 1)?" (".$Item->optionlabel.")":"")."<|><|>".$Item->quantity."<|>".$Item->unitprice."<|>".(($Item->tax)?"Y":"N");
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
		curl_setopt($connection,CURLOPT_URL,"https://secure.authorize.net/gateway/transact.dll");
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
		curl_close($connection);

		$Response = $this->response($buffer);
		return $Response;
	}
	
	function response ($buffer) {
		$_ = new stdClass();

		list($_->code,
			 $_->subcode,
			 $_->reasoncode,
			 $_->reason,
			 $_->authcode,
			 $_->avs,
			 $_->transactionid,
			 $_->invoicenum,
			 $_->description,
			 $_->amount,
			 $_->method,
			 $_->type,
			 $_->customerid,
			 $_->firstname,
			 $_->lastname,
			 $_->company,
			 $_->address,
			 $_->city,
			 $_->state,
			 $_->zip,
			 $_->country,
			 $_->phone,
			 $_->fax,
			 $_->email,
			 $_->ship_to_first_name,
			 $_->ship_to_last_name,
			 $_->ship_to_company,
			 $_->ship_to_address,
			 $_->ship_to_city,
			 $_->ship_to_state,
			 $_->ship_to_zip,
			 $_->ship_to_country,
			 $_->tax,
			 $_->duty,
			 $_->freight,
			 $_->taxexempt,
			 $_->ponum,
			 $_->md5hash,
			 $_->cvv2code,
			 $_->cvv2response) = split(",",$buffer);
		return $_;
	}
	
	function settings () {
		global $Shopp;
		$settings = $Shopp->Settings->get('Authorize.Net');
		?>
		<tr id="authorize-net-settings" class="form-field">
			<th scope="row" valign="top">Authorize.Net</th>
			<td>
				<div><input type="text" name="settings[Authorize.Net][login]" id="authorize_net_login" value="<?php echo $settings['login']; ?>" size="16" /><br /><label for="authorize_net_login"><?php _e('Enter your Authorize.net Login ID.'); ?></label></div>
				<p><input type="password" name="settings[Authorize.Net][password]" id="authorize_net_password" value="<?php echo $settings['password']; ?>" size="24" /><br /><label for="authorize_net_password"><?php _e('Enter your Authorize.net Password or Transaction Key.'); ?></label></p>
				<p><input type="hidden" name="settings[Authorize.Net][testmode]" value="off"><input type="checkbox" name="settings[Authorize.Net][testmode]" id="authorize_net_testmode" value="on"<?php echo ($settings['testmode'] == "on")?' checked="checked"':''; ?> /><label for="authorize_net_testmode"> <?php _e('Enable test mode'); ?></label></p>
				
			</td>
		</tr>
		<?
	}
	
	function registerSettings () {
		?>
		gatewayHandlers.register('<?php echo __FILE__; ?>','authorize-net-settings');
		<?php
	}

} // end AuthorizeNet class

?>