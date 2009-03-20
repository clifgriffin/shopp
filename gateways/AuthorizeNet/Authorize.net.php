<?php
/**
 * Authorize.Net
 * @class AuthorizeNet
 *
 * @author Jonathan Davis
 * @version 1.0.3
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
		$this->settings = $Shopp->Settings->get('AuthorizeNet');
		$this->settings['merchant_email'] = $Shopp->Settings->get('merchant_email');
		if (!isset($this->settings['cards'])) $this->settings['cards'] = $this->cards;

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
		if (!empty($this->Response)) 
			return new ShoppError($this->Response->reason,'authorize_net_error',SHOPP_TRXN_ERR,
				array('code'=>$this->Response->reasoncode));
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
			$_['x_line_item'][] = ($i++)."<|>".substr($Item->name,0,31)."<|>".((sizeof($Item->options) > 1)?" (".substr($Item->optionlabel,0,253).")":"")."<|>".number_format($Item->quantity,2)."<|>".number_format($Item->unitprice,2,'.','')."<|>".(($Item->tax)?"Y":"N");
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
		curl_setopt($connection, CURLOPT_URL,"https://secure.authorize.net/gateway/transact.dll");
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
			new ShoppError($error,'authorize_net_connection',SHOPP_COMM_ERR);
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
		?>
		<tr id="authorize-net-settings" class="addon">
			<th scope="row" valign="top">Authorize.Net</th>
			<td>
				<div><input type="text" name="settings[AuthorizeNet][login]" id="authorize_net_login" value="<?php echo $this->settings['login']; ?>" size="16" /><br /><label for="authorize_net_login"><?php _e('Enter your AuthorizeNet Login ID.'); ?></label></div>
				<div><input type="password" name="settings[AuthorizeNet][password]" id="authorize_net_password" value="<?php echo $this->settings['password']; ?>" size="24" /><br /><label for="authorize_net_password"><?php _e('Enter your AuthorizeNet Password or Transaction Key.'); ?></label></div>
				<div><input type="hidden" name="settings[AuthorizeNet][testmode]" value="off"><input type="checkbox" name="settings[AuthorizeNet][testmode]" id="authorize_net_testmode" value="on"<?php echo ($this->settings['testmode'] == "on")?' checked="checked"':''; ?> /><label for="authorize_net_testmode"> <?php _e('Enable test mode'); ?></label></div>
				<div><strong>Accept these cards:</strong>
				<ul class="cards"><?php foreach($this->cards as $id => $card): 
					$checked = "";
					if (in_array($card,$this->settings['cards'])) $checked = ' checked="checked"';
				?>
					<li><input type="checkbox" name="settings[AuthorizeNet][cards][]" id="authorize_net_cards_<?php echo $id; ?>" value="<?php echo $card; ?>" <?php echo $checked; ?> /><label for="authorize_net_cards_<?php echo $id; ?>"> <?php echo $card; ?></label></li>
				<?php endforeach; ?></ul></div>
				
				<input type="hidden" name="module[<?php echo basename(__FILE__); ?>]" value="AuthorizeNet" />
			</td>
		</tr>
		<?php
	}
	
	function registerSettings () {
		?>
		gatewayHandlers.register('<?php echo addslashes(__FILE__); ?>','authorize-net-settings');
		<?php
	}

} // end AuthorizeNet class

?>