<?php
/**
 * PayPal Pro
 * @class PayPalPro
 *
 * @author Jonathan Davis
 * @version 1.2
 * @copyright Ingenesis Limited, 19 August, 2008
 * @package Shopp
 * @since 1.1
 * @subpackage PayPalPro
 * 
 * $Id$
 **/

class PayPalPro extends GatewayFramework implements GatewayModule {

	var $secure = true;
	var $cards = array("visa","mc","disc","amex","maes","solo"); 
	
	// Specific cardtype mappings (standard symbol to PayPal Pro expected)
	var $cardMap = array("Visa"=>"Visa","MC"=>"MasterCard","Disc"=>"Discover","Amex"=>"Amex", "Maes"=>"Maestro", "Solo"=>"Solo");

	var $sandboxurl = "https://api-3t.sandbox.paypal.com/nvp";
	var $liveurl = "https://api-3t.paypal.com/nvp";

	var $currencies = array("USD", "AUD", "CAD", "EUR", "GBP", "JPY");
	
	function __construct () {
		$Settings = ShoppSettings();
		$base = $Settings->get('base_operations');
		$currency = in_array($base['currency']['code'],$this->currencies) ? $base['currency']['code'] : $this->currencies[0]; // Use USD by default
		if ($currency != 'GBP') $this->cards = array_diff($this->cards, array("maes","solo"));	

		parent::__construct(); // parent constructor after UK cards filter
		$this->settings['base_operations'] = $base;
		$this->settings['currency_code'] = $currency;
	}
	
	function actions () {
		add_action('shopp_process_order',array(&$this,'process'));
	}
	
	function process () {
		
		if ($this->settings['testmode'] == "on") $this->url = $this->sandboxurl;
		else $this->url = $this->liveurl;
		
 		$response = $this->send($this->build(),$this->url);
		$response = $this->response($response);

		if ($response->ack == "Success" || 
			$response->ack == "SuccessWithWarning") {
			$this->Order->transaction($response->transactionid,'CHARGED');
		} else {
			$message = $response->longerror[0];
			$code = $response->errorcodes[0];
			return new ShoppError($message,'paypalpro_txn_error',SHOPP_TRXN_ERR);
		}
	}
	
	function build () {
		global $Shopp;
		$_ = array();
		$precision = $this->settings['base_operations']['currency']['format']['precision'];

		// Options
		$_['USER'] 					= $this->settings['username'];
		$_['PWD'] 					= $this->settings['password'];
		$_['SIGNATURE']				= $this->settings['signature'];

		$_['VERSION']				= "52.0";
		$_['METHOD']				= "DoDirectPayment";
		$_['PAYMENTACTION']			= "Sale";
		$_['IPADDRESS']				= $_SERVER["REMOTE_ADDR"];
		$_['RETURNFMFDETAILS']		= "1"; // optional - return fraud management filter data
		$_['BUTTONSOURCE']			= 'shopplugin.net[WPP]';
		
		// Customer Contact
		$_['FIRSTNAME']				= $this->Order->Customer->firstname;
		$_['LASTNAME']				= $this->Order->Customer->lastname;
		$_['EMAIL']					= $this->Order->Customer->email;
		$_['PHONENUM']				= $this->Order->Customer->phone;
		
		// Billing
		$card = Lookup::paycard($this->Order->Billing->cardtype);
		$_['CREDITCARDTYPE']		= isset($card->symbol)?$this->cardMap[$card->symbol]:'';
		$_['ACCT']					= $this->Order->Billing->card;
		$_['EXPDATE']				= date("mY",$this->Order->Billing->cardexpires);
		$_['CVV2']					= $this->Order->Billing->cvv;
		$_['STARTDATE']				= isset($this->Order->Billing->start)?$this->Order->Billing->start:'';
		$_['ISSUENUMBER']			= isset($this->Order->Billing->issue)?$this->Order->Billing->issue:'';
		$_['STREET']				= $this->Order->Billing->address;
		$_['STREET2']				= $this->Order->Billing->xaddress;
		$_['CITY']					= $this->Order->Billing->city;
		$_['STATE']					= $this->Order->Billing->state;
		$_['ZIP']					= $this->Order->Billing->postcode;
		$_['COUNTRYCODE']			= $this->Order->Billing->country;

		if ($this->Order->Billing->country == "UK") // PayPal uses ISO 3361-1
			$_['COUNTRYCODE'] = "GB";
		
		// Shipping
		if (!empty($this->Order->Shipping->address) &&
				!empty($this->Order->Shipping->city) &&
				!empty($this->Order->Shipping->state) && 
				!empty($this->Order->Shipping->postcode) && 
				!empty($this->Order->Shipping->country)) {		
			$_['SHIPTONAME'] 			= $this->Order->Customer->firstname.' '.$this->Order->Customer->lastname;
			$_['SHIPTOPHONENUM']		= $this->Order->Customer->phone;
			$_['SHIPTOSTREET']			= $this->Order->Shipping->address;
			$_['SHIPTOSTREET2']			= $this->Order->Shipping->xaddress;
			$_['SHIPTOCITY']			= $this->Order->Shipping->city;
			$_['SHIPTOSTATE']			= $this->Order->Shipping->state;
			$_['SHIPTOZIP']				= $this->Order->Shipping->postcode;
			$_['SHIPTOCOUNTRYCODE']		= $this->Order->Shipping->country;
			if ($this->Order->Shipping->country == "UK") // PayPal uses ISO 3361-1
				$_['SHIPTOCOUNTRYCODE'] = "GB";

		}
		
		// Transaction
		$_['CURRENCYCODE']			= $this->settings['currency_code'];
		$_['AMT']					= number_format($this->Order->Cart->Totals->total,$precision);
		$_['ITEMAMT']				= number_format(round($this->Order->Cart->Totals->subtotal,$precision) - 
													round($this->Order->Cart->Totals->discount,$precision),$precision);
		$_['SHIPPINGAMT']			= number_format($this->Order->Cart->Totals->shipping,$precision);
		$_['TAXAMT']				= number_format($this->Order->Cart->Totals->tax,$precision);
		
		if (isset($this->Order->data['paypal-custom']))
			$_['CUSTOM'] = htmlentities($this->Order->data['paypal-custom']);
		
		// Line Items
		foreach($this->Order->Cart->contents as $i => $Item) {
			$_['L_NAME'.$i]			= $Item->name.((!empty($Item->option->label))?' '.$Item->option->label:'');
			$_['L_AMT'.$i]			= number_format($Item->unitprice,$precision);
			$_['L_NUMBER'.$i]		= $i;
			$_['L_QTY'.$i]			= $Item->quantity;
			// $_['L_TAXAMT'.$i]		= number_format($Item->tax,$precision);
		}

		if ($this->Order->Cart->Totals->discount != 0) {
			$discounts = array();
			// foreach($Shopp->Cart->data->PromosApplied as $promo)
			// 	$discounts[] = $promo->name;
			
			$i++;
			$_['L_NUMBER'.$i]		= $i;
			$_['L_NAME'.$i]			= join(", ",$discounts);
			$_['L_AMT'.$i]			= number_format($this->Order->Cart->Totals->discount*-1,$precision);
			$_['L_QTY'.$i]			= 1;
			$_['L_TAXAMT'.$i]		= number_format(0,$precision);
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
		$this->transaction .= "&INVNUM=".md5($this->transaction.$Shopp->Shopping->session);
		return $this->transaction;
	}
		
	function response ($buffer) {
		if (empty($buffer)) return false;
		$_ = new stdClass();
		$r = array();
		$pairs = explode("&",$buffer);
		foreach($pairs as $pair) {
			list($key,$value) = explode("=",$pair);
			
			if (preg_match("/(\w*?)(\d+)/",$key,$matches)) {
				if (!isset($r[$matches[1]])) $r[$matches[1]] = array();
				$r[$matches[1]][$matches[2]] = urldecode($value);
			} else $r[$key] = urldecode($value);
		}
		
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
		$this->ui->cardmenu(0,array(
			'name' => 'cards',
			'selected' => $this->settings['cards']
		),$this->cards);

		$this->ui->text(1,array(
			'name' => 'username',
			'size' => 30,
			'value' => $this->settings['username'],
			'label' => __('Enter your PayPal API Username.','Shopp')
		));

		$this->ui->password(1,array(
			'name' => 'password',
			'size' => 16,
			'value' => $this->settings['password'],
			'label' => __('Enter your PayPal API Password.','Shopp')
		));

		$this->ui->text(1,array(
			'name' => 'signature',
			'size' => 48,
			'value' => $this->settings['signature'],
			'label' => __('Enter your PayPal API Signature.','Shopp')
		));

		$this->ui->checkbox(1,array(
			'name' => 'testmode',
			'checked' => $this->settings['testmode'],
			'label' => sprintf(__('Use the %s','Shopp'),'<a href="http://docs.shopplugin.net/PayPal_Sandbox" target="shoppdocs">PayPal Sandbox</a>')
		));
	}

} // END class PayPalPro

?>