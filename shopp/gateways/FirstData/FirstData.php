<?php
/**
 * FirstData
 *
 * @author Jonathan Davis
 * @version 1.1
 * @copyright Ingenesis Limited, 12 March, 2009
 * @package Shopp
 * @since 1.1
 * @subpackage FirstData
 * 
 * $Id$
 **/

require_once(SHOPP_PATH."/core/model/XML.php");

class FirstData extends GatewayFramework implements GatewayModule {

	var $secure = true;

	var $cards = array("visa","mc","disc","amex","dc","jcb");

	var $testurl = "https://staging.linkpt.net/lpc/servlet/lppay";
	var $liveurl = "https://secure.linkpt.net/lpcentral/servlet/lppay";
	
	function __construct () {
		parent::__construct();
		$this->setup('storenumber','testmode');
	}
	
	function actions () {
		add_action('shopp_process_order',array(&$this,'process'));
	}
	
	function process () {
		$transaction = $this->build();
		$XML = new XMLData($this->send($transaction));
		if (!$XML) return false;

		if ($XML->getElementContent('r_approved') == "APPROVED") {
			$this->Order->transaction($this->txnid($XML),'CHARGED');
			return;
		}
		
		$this->error($XML);
	}
	
	function txnid ($XML) {
		$transaction = $XML->getElementContent('r_ref');
		if (!empty($transaction)) return $transaction;
		return parent::txnid();
	}
	
	function error ($XML) {
		if (empty($XML)) return false;
		$message = $XML->getElementContent('r_error');
		if (empty($message)) return new ShoppError(__("An unknown error occurred while processing this transaction.  Please contact the site administrator.","Shopp"),'firstdata_trxn_error',SHOPP_TRXN_ERR);
		return new ShoppError($message,'firstdata_trxn_error',SHOPP_TRXN_ERR);
	}
	
	function build () {
		$Order = $this->Order;
		$result = "live";
		if ($this->settings['testmode'] == "on") $result = "good";
				
		$_ = array();
		$_[] = '<order>';
			$_[] = '<merchantinfo>';
				$_[] = '<configfile>'.$this->settings['storenumber'].'</configfile>';
				$_[] = '<appname>'.SHOPP_GATEWAY_USERAGENT.'</appname>';
			$_[] = '</merchantinfo>';
			$_[] = '<orderoptions>';
				$_[] = '<ordertype>Sale</ordertype>';
				$_[] = '<result>'.$result.'</result>';
			$_[] = '</orderoptions>';
			$_[] = '<payment>';
				$_[] = '<chargetotal>'.number_format($Order->Cart->Totals->total,$this->precision,'.','').'</chargetotal>';
				$_[] = '<subtotal>'.number_format($Order->Cart->Totals->subtotal - $Order->Cart->Totals->discount,$this->precision,'.','').'</subtotal>';
				$_[] = '<tax>'.number_format($Order->Cart->Totals->tax,$this->precision,'.','').'</tax>';
				$_[] = '<shipping>'.number_format($Order->Cart->Totals->shipping,$this->precision,'.','').'</shipping>';
			$_[] = '</payment>';
			$_[] = '<creditcard>';
				$_[] = '<cardnumber>'.$Order->Billing->card.'</cardnumber>';
				$_[] = '<cardexpmonth>'.date("m",$Order->Billing->cardexpires).'</cardexpmonth>';
				$_[] = '<cardexpyear>'.date("y",$Order->Billing->cardexpires).'</cardexpyear>';
				$_[] = '<cvmvalue>'.$Order->Billing->cvv.'</cvmvalue>';
				$_[] = '<cvmindicator>provided</cvmindicator>';
			$_[] = '</creditcard>';
			$_[] = '<transactiondetails>';
				$_[] = '<transactionorigin>Eci</transactionorigin>';
				$_[] = '<taxexempt>N</taxexempt>';
				$_[] = '<ip>'.$_SERVER['REMOTE_ADDR'].'</ip>';
			$_[] = '</transactiondetails>';
			$_[] = '<billing>';
				$_[] = '<name>'.$Order->Customer->firstname.' '.$Order->Customer->lastname.'</name>';
				$_[] = '<company>'.$Order->Customer->company.'</company>';
				$_[] = '<addrnum>'.preg_replace('/^(\d+).*?$/','$1',$Order->Billing->address).'</addrnum>';
				$_[] = '<address1>'.$Order->Billing->address.'</address1>';
				$_[] = '<address2>'.$Order->Billing->xaddress.'</address2>';
				$_[] = '<city>'.$Order->Billing->city.'</city>';
				$_[] = '<state>'.$Order->Billing->state.'</state>';
				$_[] = '<zip>'.$Order->Billing->postcode.'</zip>';
				$_[] = '<country>'.$Order->Billing->country.'</country>';
			$_[] = '</billing>';
			$_[] = '<shipping>';
				$_[] = '<name>'.$Order->Customer->firstname.' '.$Order->Customer->lastname.'</name>';
				$_[] = '<address1>'.$Order->Shipping->address.'</address1>';
				$_[] = '<address2>'.$Order->Shipping->xaddress.'</address2>';
				$_[] = '<city>'.$Order->Shipping->city.'</city>';
				$_[] = '<state>'.$Order->Shipping->state.'</state>';
				$_[] = '<zip>'.$Order->Shipping->postcode.'</zip>';
				$_[] = '<country>'.$Order->Shipping->country.'</country>';
			$_[] = '</shipping>';

			$_[] = '<items>';
			foreach($Order->Cart->contents as $Item) {
				$_[] = '<item>';
					$_[] = '<description>'.htmlentities($Item->name.' '.((sizeof($Item->options) > 1)?' ('.$Item->optionlabel.')':'')).'</description>';
					$_[] = '<id>'.$Item->product.'</id>';
					$_[] = '<price>'.number_format($Item->unitprice,$this->precision,'.','').'</price>';
					$_[] = '<quantity>'.$Item->quantity.'</quantity>';
				$_[] = '</item>';
			}
			$_[] = '</items>';
			
		$_[] = '</order>';

		return implode("",$_);
	}
	
	function send ($data) {
		$url = ($this->settings['testmode'] == "on")?$this->testurl:$this->liveurl;
		
		$certificate = dirname(__FILE__).'/'.$this->settings['storenumber'].'.pem';
		
		if (!file_exists($certificate)) {
			new ShoppError(__('No certificate file is installed for FirstData','Shopp'),'firstdata_certificate',SHOPP_TRXN_ERR);
			return false;
		}
		
		$connection = curl_init();
		curl_setopt($connection,CURLOPT_URL,$url);		
		curl_setopt($connection, CURLOPT_SSL_VERIFYPEER, 0); 
		curl_setopt($connection, CURLOPT_SSL_VERIFYHOST, 0); 
		curl_setopt($connection, CURLOPT_SSLCERT, $certificate); 
		curl_setopt($connection, CURLOPT_PORT, 1129); 
		curl_setopt($connection, CURLOPT_NOPROGRESS, 1); 
		curl_setopt($connection, CURLOPT_VERBOSE, 1); 
		curl_setopt($connection, CURLOPT_FOLLOWLOCATION,0); 
		curl_setopt($connection, CURLOPT_POST, 1); 
		curl_setopt($connection, CURLOPT_POSTFIELDS, $data); 
		curl_setopt($connection, CURLOPT_TIMEOUT, SHOPP_GATEWAY_TIMEOUT); 
		curl_setopt($connection, CURLOPT_USERAGENT, SHOPP_GATEWAY_USERAGENT); 
		curl_setopt($connection, CURLOPT_REFERER, "https://".$_SERVER['SERVER_NAME']); 
		curl_setopt($connection, CURLOPT_RETURNTRANSFER, 1);
		$buffer = curl_exec($connection);
		if ($error = curl_error($connection)) {
			if (class_exists('ShoppError'))  new ShoppError($error,'firstdata_connection',SHOPP_COMM_ERR);
			return false;
		}
		curl_close($connection);
		
		return trim('<response>'.$buffer.'</response>');
	}
		
	function settings () {
		$this->ui->cardmenu(0,array(
			'name' => 'cards',
			'selected' => $this->settings['cards']
		),$this->cards);

		$this->ui->text(1,array(
			'name' => 'storenumber',
			'size' => 30,
			'value' => $this->settings['storenumber'],
			'label' => __('Enter your FirstData store number.','Shopp')
		));

		$this->ui->checkbox(1,array(
			'name' => 'testmode',
			'checked' => $this->settings['testmode'],
			'label' => __('Enable test mode','Shopp')
		));
		
	}
	
} // END class FirstData

?>