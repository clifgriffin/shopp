<?php
/**
 * AuthorizeNet class
 * 
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 30 March, 2008
 * @package Shopp
 **/


class AuthorizeNet {
	var $transaction = array();

	function AuthorizeNet (&$Order) {
		$this->build($Order);		
		return true;
	}
	
	function process () {
		$response = $this->send();
		print "<p style='white-space: pre'>";
		print $response;
		print "</p>";
		exit();
	}
	
	function build ($Order) {
		$_ = array();
		
		// Options
		$_['x_test_request']		= "TRUE"; // Set while testing
		$_['x_Delim_Data'] 			= "TRUE"; 
		$_['x_Delim_Char'] 			= ","; 
		$_['x_Encap_Char'] 			= ""; 
		$_['x_version'] 			= "3.1";
		$_['x_login'] 				= "KMX937";
		$_['x_password'] 			= "1Foot12Inches";
		$_['x_relay_response']		= "FALSE";
		$_['x_type'] 				= "AUTH_CAPTURE";
		$_['x_method']				= "CC";
		$_['x_email_customer']		= "TRUE";
		$_['x_merchant_email']		= "jond@ingenesis.net";
		
		// Required Fields
		$_['x_amount']				= $Order->Cart->data->total;
		$_['x_customer_ip']			= $_SERVER["REMOTE_ADDR"];
		$_['x_fp_sequence']			= $Order->Cart->session;
		$_['x_fp_timestamp']		= time();
		// $_['x_fp_hash']				= hash_hmac("md5","{$_['x_login']}^{$_['x_fp_sequence']}^{$_['x_fp_timestamp']}^{$_['x_amount']}",$_['x_password']);
		
		// Customer Contact
		$_['x_first_name']			= $Order->Customer->firstname;
		$_['x_last_name']			= $Order->Customer->lastname;
		$_['x_email']				= $Order->Customer->email;
		$_['x_phone']				= $Order->Customer->phone;
		
		// Billing
		$_['x_card_num']			= $Order->Billing->card;
		$_['x_exp_date']			= $Order->Billing->cardexpires;
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
		$_['x_freight']				= $Order->Cart->data->shipping;
		$_['x_tax']					= $Order->Cart->data->tax;
		
		// Line Items
		$i = 1;
		foreach($Order->Cart->contents as $Item) {
			$_['x_line_item'][] = ($i++)."<|>".$Item->name.((sizeof($Item->options) > 1)?" ".$Item->option:"")."<|><|>".$Item->quantity."<|>".$Item->unitprice."<|>".(($Item->tax)?"Y":"N");
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
		// curl_setopt($connection, CURLOPT_REFERER, $referer); 
		curl_setopt($connection, CURLOPT_RETURNTRANSFER, 1);
		$buffer = curl_exec($connection);
		curl_close($connection);
		return $buffer;
	}

	
} // end AuthorizeNet class

?>