<?php
/**
 * PayPal Payflow Pro
 * @class PayflowPro
 *
 * @author John Dillick
 * @version 1.0
 * @copyright Ingenesis Limited, 17 Oct, 2010
 * @package Shopp
 * @since 1.1.4.1
 * @subpackage PayflowPro
 * 
 * $Id$
 **/

class PayflowPro extends GatewayFramework implements GatewayModule {
	var $secure = true;
	var $host = array ('live' => 'payflowpro.paypal.com', 
		'test' => 'pilot-payflowpro.paypal.com');
	var $cards = array("visa","mc","disc","amex");

	function actions () {
		add_action('shopp_process_order',array(&$this,'process'));
	}
		
	function process () {
		global $Shopp;
		
		$this->build();
		
		$h = array();
		$h['Content-Length'] = strlen($this->transaction); 
		$h['Content-Type'] = 'text/namevalue';
		$h['Host'] = ($this->settings['testmode'] == 'on' ? $this->host['test'] : $this->host['live']);	
		$h['X-VPS-REQUEST-ID'] = md5($this->transaction.$Shopp->Shopping->session);
		$h['X-VPS-CLIENT-TIMEOUT'] = SHOPP_GATEWAY_TIMEOUT;
		$h['X-VPS-VIT-INTEGRATION-PRODUCT'] = 'Shopp plugin for WordPress';
		$h['X-VPS-VIT-INTEGRATION-VERSION'] = SHOPP_VERSION;

		$response = $this->send($this->transaction,'https://'.$h['Host'],false,array( CURLOPT_HTTPHEADER => $h ));
		$response = $this->response($response);
		
		if ($response->result == 0 && $response->msg == "Approved") {
			$this->Order->transaction($response->txnid,'CHARGED');
		} else {
			return new ShoppError($response->msg, 'payflow_pro_error', SHOPP_TRXN_ERR);
		}
	}
	
	function build () {
		$_ = array();
		$precision = $this->settings['base_operations']['currency']['format']['precision'];
		
		// Payflow Account info
		$_['USER']					= (!empty($this->settings['username']) ? $this->settings['username'] : $this->settings['vendor']);
		$_['VENDOR']				= $this->settings['vendor'];
		$_['PARTNER']				= !empty($this->settings['partner']) ? $this->settings['partner'] : 'PayPal';
		$_['PWD']					= $this->settings['password'];

		// Customer Contact
		$_['FIRSTNAME']				= $this->Order->Customer->firstname;
		$_['LASTNAME']				= $this->Order->Customer->lastname;
		$_['EMAIL']					= $this->Order->Customer->email;
		$_['PHONENUM']				= $this->Order->Customer->phone;
				
		// Billing info
		$_['TENDER'] 				= 'C';
		$_['TRXTYPE'] 				= 'S';
		$_['ACCT']					= $this->Order->Billing->card;
		$_['EXPDATE']				= date("mY",$this->Order->Billing->cardexpires);
		$_['CVV2']					= $this->Order->Billing->cvv;
		$_['STREET']				= $this->Order->Billing->address;
		$_['ZIP']					= $this->Order->Billing->postcode;
		
		// Transaction
		$_['AMT']					= number_format($this->Order->Cart->Totals->total,$precision);

		if (!empty($this->Order->Shipping->address) &&
				!empty($this->Order->Shipping->city) &&
				!empty($this->Order->Shipping->state) && 
				!empty($this->Order->Shipping->postcode)) {
				$_['SHIPTOFIRSTNAME']		= $this->Order->Customer->firstname;
				$_['SHIPTOLASTNAME']		= $this->Order->Customer->lastname;
				$_['SHIPTOSTREET']			= $this->Order->Shipping->address;
				$_['SHIPTOCITY']			= $this->Order->Shipping->city;
				$_['SHIPTOSTATE']			= $this->Order->Shipping->state;
				$_['SHIPTOZIP']				= $this->Order->Shipping->postcode;							
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
		
		return $this->transaction;
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
			'label' => __('Enter your Payflow Pro user account for this site.  Leave this blank if you have not setup multiple users on your Payflow Pro account.','Shopp')
		));

		$this->ui->text(1,array(
			'name' => 'vendor',
			'size' => 30,
			'value' => $this->settings['vendor'],
			'label' => __('Enter your Payflow Pro merchant login ID that you created when you registered your account.','Shopp')
		));

		$this->ui->text(1,array(
			'name' => 'partner',
			'size' => 30,
			'value' => !empty($this->settings['partner']) ? $this->settings['partner'] : 'PayPal',
			'label' => __('Enter your Payflow Pro resellers ID.  If you purchased your account directly from PayPal, use PayPal','Shopp')
		));

		$this->ui->password(1,array(
			'name' => 'password',
			'size' => 16,
			'value' => $this->settings['password'],
			'label' => __('Enter your Payflow Pro account password.','Shopp')
		));

		$this->ui->checkbox(1,array(
			'name' => 'testmode',
			'checked' => $this->settings['testmode'],
			'label' => __('Enable test mode','Shopp')
		));
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
		// RESULT=0 &PNREF=V19A2E49D876 &RESPMSG=Approved &AUTHCODE=041PNI &AVSADDR=Y &AVSZIP=Y &IAVS=N
		$_->result = $r['RESULT'];
		$_->msg = $r['RESPMSG'];
		$_->txnid = $r['PNREF'];
		$_->auth = $r['AUTHCODE'];
		return $_;
	}
	
}

?>