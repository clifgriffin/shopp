<?php
/**
 * PayJunction
 * @class PayJunction
 *
 * @author Jonathan Davis
 * @version 1.1
 * @copyright Ingenesis Limited, 28 May, 2009
 * @package Shopp
 * @since 1.1
 * @subpackage PayJunction
 * 
 * $Id$
 **/

class PayJunction extends GatewayFramework implements GatewayModule {

	var $secure = true;
	
	var $production = "https://payjunction.com/quick_link";
	var $demo = "https://demo.payjunction.com/quick_link";
	
	var $cards = array("visa", "mc", "amex", "disc");
	
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

	function PayJunction () {
		parent::__construct();
		$this->setup('login','password','testmode');
	}
	
	function actions () {
		add_action('shopp_process_order',array(&$this,'process'));
	}
	
	function process () {
		$Response = $this->send($this->build());

		if ($Response->dc_response_code == "00" || 
			$Response->dc_response_code == "85") {
				$this->Order->transaction($Response->dc_transaction_id,'CHARGED');
			return;
		}
				
		new ShoppError($Response->dc_response_message,'payjunction_error',SHOPP_TRXN_ERR,
			array('code'=>$Response->dc_response_code));
		
	}
	
	function build () {
		$Order = $this->Order;
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
		$_['dc_transaction_amount']	= number_format($Order->Cart->Totals->subtotal - 
													$Order->Cart->Totals->discount,$this->precision);
		$_['dc_shipping_amount']	= number_format($Order->Cart->Totals->shipping,$this->precision);
		$_['dc_tax_amount']			= number_format($Order->Cart->Totals->tax,$this->precision);
		
		return $this->encode($_);
	}
	
	function send ($message) {
		$url = $this->production;
		if ($this->settings['testmode'] == "on") $url = $this->demo;
		return $this->response(parent::send($message,$url));
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
		$this->ui->cardmenu(0,array(
			'name' => 'cards',
			'selected' => $this->settings['cards']
		),$this->cards);

		$this->ui->text(1,array(
			'name' => 'login',
			'value' => $this->settings['login'],
			'size' => '16',
			'label' => __('Enter your PayJunction login name.','Shopp')
		));

		$this->ui->password(1,array(
			'name' => 'password',
			'value' => $this->settings['password'],
			'size' => '24',
			'label' => __('Enter your PayJunction password.','Shopp')
		));
		
		$this->ui->checkbox(1,array(
			'name' => 'testmode',
			'checked' => $this->settings['testmode'],
			'label' => __('Enable test mode','Shopp')
		));
	}
	
} // END class PayJunction

?>