<?php
/**
 * Authorize.Net
 * @class AuthorizeNet
 *
 * @author Jonathan Davis
 * @version 1.1
 * @copyright Ingenesis Limited, 30 March, 2008
 * @package Shopp
 * @since 1.1
 * @subpackage AuthorizeNet
 * 
 * $Id$
 **/

class AuthorizeNet extends GatewayFramework implements GatewayModule {

	var $cards = array("visa", "mc", "amex", "disc", "jcb", "dc");

	var $liveurl = 'https://secure.authorize.net/gateway/transact.dll';
	var $testurl = 'https://test.authorize.net/gateway/transact.dll';

	function AuthorizeNet () {
		parent::__construct();
		$this->setup('login','password','testmode');
	}
	
	function actions () {
		add_action('shopp_process_order',array(&$this,'process'));
	}
	
	function process () {
		$transaction = $this->build();
		$Response = $this->send($transaction);
		if ($Response->code == '1') { // success
			$this->Order->transaction($this->txnid($Response),'CHARGED');
			return;
		} elseif ($Response->code == '4') { // flagged for merchant review or risk management
			$this->Order->transaction($this->txnid($Response),'PENDING');
			return;
		} else $this->error($Response);
	}
	
	function txnid ($Response) {
		if (empty($Response->transactionid)) return parent::txnid();
		return $Response->transactionid;
	}
	
	function error ($Response) {
		return new ShoppError($Response->reason,'authorize_net_error',SHOPP_TRXN_ERR,
			array('code'=>$Response->reasoncode));
	}
	
	function build () {
		$Order = $this->Order;
		$_ = array();

		// Options
		$_['x_test_request']		= ($this->settings['testmode'] == "on")?"TRUE":"FALSE"; // Set "TRUE" while testing
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
		$_['x_amount']				= $Order->Cart->Totals->total;
		$_['x_customer_ip']			= $_SERVER["REMOTE_ADDR"];
		$_['x_fp_sequence']			= mktime();
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
		$_['x_freight']				= $Order->Cart->Totals->shipping;
		$_['x_tax']					= $Order->Cart->Totals->tax;
		
		// Line Items
		$i = 1;
		foreach($Order->Cart->contents as $Item) {
			$_['x_line_item'][] = ($i++)."<|>".substr($Item->name,0,31)."<|>".((sizeof($Item->options) > 1)?" (".substr($Item->optionlabel,0,253).")":"")."<|>".(int)$Item->quantity."<|>".number_format($Item->unitprice,$this->precision,'.','')."<|>".(($Item->tax)?"Y":"N");
		}
		
		return $this->encode($_);
	}
	
	function send ($data) {
		if ($this->settings['testmode'] == "on") $url = $this->testurl;
		else $url = $this->liveurl;
		$url = apply_filters('shopp_authorize_net_url',$url);
		return $this->response(parent::send($data,$url));
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
			 $_->cvv2response) = explode(",",$buffer);
		return $_;
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
			'label' => __('Enter your AuthorizeNet Login ID.','Shopp')
		));

		$this->ui->password(1,array(
			'name' => 'password',
			'value' => $this->settings['password'],
			'size' => '24',
			'label' => __('Enter your AuthorizeNet Password or Transaction Key.','Shopp')
		));

		$this->ui->checkbox(1,array(
			'name' => 'testmode',
			'checked' => $this->settings['testmode'],
			'label' => __('Enable test mode','Shopp')
		));

	}

} // END class AuthorizeNet

?>