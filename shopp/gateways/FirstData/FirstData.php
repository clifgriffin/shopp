<?php
/**
 * FirstData
 * @class FirstData
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 12 March, 2009
 * @package Shopp
 **/

require_once(SHOPP_PATH."/core/model/XMLdata.php");

class FirstData {
	var $transaction = array();
	var $settings = array();
	var $Response = false;
	var $cards = array("Visa","MasterCard","Discover","Amex","Diners Club","JCB");
	// var $url = "https://staging.linkpt.net/lpc/servlet/lppay"; // Staging URL
	var $url = "https://secure.linkpt.net/lpcentral/servlet/lppay";
	
	function FirstData (&$Order="") {
		global $Shopp;
		$this->settings = $Shopp->Settings->get('FirstData');
		$this->settings['merchant_email'] = $Shopp->Settings->get('merchant_email');
		if (!isset($this->settings['cards'])) $this->settings['cards'] = $this->cards;
		
		if (!empty($Order)) $this->build($Order);
		return true;
	}
	
	function process () {
		$this->Response = $this->send();
		$status = $this->Response->getElementContent('r_approved');

		if ($status == "APPROVED") return true;
		else return false;
	}
	
	function transactionid () {
		$transaction = $this->Response->getElementContent('r_ref');
		if (!empty($transaction)) return $transaction;
		return false;
	}
	
	function error () {
		if (empty($this->Response)) return false;
		$message = $this->Response->getElementContent('r_error');
		if (class_exists('ShoppError')) {
			if (empty($message)) return new ShoppError(__("An unknown error occurred while processing this transaction.  Please contact the site administrator.","Shopp"),'firstdata_trxn_error',SHOPP_TRXN_ERR);
			return new ShoppError($message,'firstdata_trxn_error',SHOPP_TRXN_ERR);
		} else {
			$Error = new stdClass();
			$Error->code = 'firstdata_trxn_error';
			$Error->message = $message;
			return $Error;
		}
	}
	
	function build ($Order) {
		
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
				$_[] = '<chargetotal>'.number_format($Order->Totals->total,2,'.','').'</chargetotal>';
				$_[] = '<subtotal>'.number_format($Order->Totals->subtotal,2,'.','').'</subtotal>';
				$_[] = '<tax>'.number_format($Order->Totals->tax,2,'.','').'</tax>';
				$_[] = '<shipping>'.number_format($Order->Totals->shipping,2,'.','').'</shipping>';
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
			foreach($Order->Items as $Item) {
				$_[] = '<item>';
					$_[] = '<description>'.$Item->name.' '.((sizeof($Item->options) > 1)?' ('.$Item->optionlabel.')':'').'</description>';
					$_[] = '<id>'.$Item->product.'</id>';
					$_[] = '<price>'.$Item->unitprice.'</price>';
					$_[] = '<quantity>'.$Item->quantity.'</quantity>';
				$_[] = '</item>';
			}
			$_[] = '</items>';
			
		$_[] = '</order>';
		
		$this->transaction = join("",$_);
	}
	
	function send () {
		$connection = curl_init();
		curl_setopt($connection,CURLOPT_URL,$this->url); // Live		
		curl_setopt($connection, CURLOPT_SSL_VERIFYPEER, 0); 
		curl_setopt($connection, CURLOPT_SSL_VERIFYHOST, 0); 
		curl_setopt($connection, CURLOPT_SSLCERT, 
				dirname(__FILE__).DIRECTORY_SEPARATOR.$this->settings['storenumber'].'.pem'); 
		curl_setopt($connection, CURLOPT_PORT, 1129); 
		curl_setopt($connection, CURLOPT_NOPROGRESS, 1); 
		curl_setopt($connection, CURLOPT_VERBOSE, 1); 
		curl_setopt($connection, CURLOPT_FOLLOWLOCATION,0); 
		curl_setopt($connection, CURLOPT_POST, 1); 
		curl_setopt($connection, CURLOPT_POSTFIELDS, $this->transaction); 
		curl_setopt($connection, CURLOPT_TIMEOUT, 5); 
		curl_setopt($connection, CURLOPT_USERAGENT, SHOPP_GATEWAY_USERAGENT); 
		curl_setopt($connection, CURLOPT_REFERER, "https://".$_SERVER['SERVER_NAME']); 
		curl_setopt($connection, CURLOPT_RETURNTRANSFER, 1);
		$buffer = curl_exec($connection);
		if ($error = curl_error($connection)) {
			if (class_exists('ShoppError')) new ShoppError($error,'firstdata_connection',SHOPP_COMM_ERR);
		}
		curl_close($connection);
		
		$this->Response = new XMLdata(trim('<response>'.$buffer.'</response>'));
		return $this->Response;
	}
		
	function settings () {
		?>
		<tr id="FirstData-settings" class="addon">
			<th scope="row" valign="top">FirstData</th>
			<td>
				<div><input type="text" name="settings[FirstData][storenumber]" id="FirstData_storenumber" value="<?php echo $this->settings['storenumber']; ?>" size="16" /><br /><label for="FirstData_customerid"><?php _e('Enter your FirstData store number.'); ?></label></div>
				<div><input type="hidden" name="settings[FirstData][testmode]" value="off" /><input type="checkbox" name="settings[FirstData][testmode]" id="FirstData_testmode" value="on"<?php echo ($this->settings['testmode'] == "on")?' checked="checked"':''; ?> /><label for="FirstData_testmode"> <?php _e('Enable test mode'); ?></label></div>
				<div><strong>Accept these cards:</strong>
				<ul class="cards"><?php foreach($this->cards as $id => $card): 
					$checked = "";
					if (in_array($card,$this->settings['cards'])) $checked = ' checked="checked"';
				?>
					<li><input type="checkbox" name="settings[FirstData][cards][]" id="FirstData_cards_<?php echo $id; ?>" value="<?php echo $card; ?>" <?php echo $checked; ?> /><label for="FirstData_cards_<?php echo $id; ?>"> <?php echo $card; ?></label></li>
				<?php endforeach; ?></ul></div>
				
				<input type="hidden" name="module[<?php echo basename(__FILE__); ?>]" value="FirstData" />
				
			</td>
		</tr>
		<?php
	}
	
	function registerSettings () {
		?>
		gatewayHandlers.register('<?php echo addslashes(__FILE__); ?>','FirstData-settings');
		<?php
	}

} // end FirstData class

?>