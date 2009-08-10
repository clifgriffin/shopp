<?php
/**
 * PayPal Pro
 * @class PayPalPro
 *
 * @author Jonathan Davis
 * @version 1.0.6
 * @copyright Ingenesis Limited, 19 August, 2008
 * @package Shopp
 **/

class PayPalPro {
	var $transaction = array();
	var $settings = array();
	var $Response = false;
	var $cards = array("Visa","MasterCard","Discover","Amex");
	var $sandboxurl = "https://api-3t.sandbox.paypal.com/nvp";
	var $liveurl = "https://api-3t.paypal.com/nvp";
	var $currencies = array("USD", "AUD", "CAD", "EUR", "GBP", "JPY");
	
	function PayPalPro (&$Order="") {
		global $Shopp;
		$this->settings = $Shopp->Settings->get('PayPalPro');
		$this->settings['merchant_email'] = $Shopp->Settings->get('merchant_email');
		$this->settings['base_operations'] = $Shopp->Settings->get('base_operations');
		if (!isset($this->settings['cards'])) $this->settings['cards'] = $this->cards;
		
		$this->settings['currency_code'] = $this->currencies[0]; // Use USD by default
		if (in_array($this->settings['base_operations']['currency']['code'],$this->currencies))
			$this->settings['currency_code'] = $this->settings['base_operations']['currency']['code'];
		
		if (!empty($Order)) $this->build($Order);
		return true;
	}
	
	function process () {
		$this->Response = $this->send();
		if ($this->Response->ack == "Success" || 
			$this->Response->ack == "SuccessWithWarning") return true;
		else return false;
	}
	
	function transactionid () {
		if (!empty($this->Response)) return $this->Response->transactionid;
	}
	
	function error () {
		if (!empty($this->Response)) {
			$message = $this->Response->longerror[0];
			$code = $this->Response->errorcodes[0];
			if (empty($message)) return false;
			return new ShoppError($message,'paypal_pro_transaction_error',SHOPP_TRXN_ERR);
		}
	}
	
	function build ($Order) {
		$_ = array();

		// Options
		$_['USER'] 					= $this->settings['username'];
		$_['PWD'] 					= $this->settings['password'];
		$_['SIGNATURE']				= $this->settings['signature'];

		$_['VERSION']				= "52.0";
		$_['METHOD']				= "DoDirectPayment";
		$_['PAYMENTACTION']			= "Sale";
		$_['IPADDRESS']				= $_SERVER["REMOTE_ADDR"];
		$_['RETURNFMFDETAILS']		= "1"; // optional - return fraud management filter data
		
		// Customer Contact
		$_['FIRSTNAME']				= $Order->Customer->firstname;
		$_['LASTNAME']				= $Order->Customer->lastname;
		$_['EMAIL']					= $Order->Customer->email;
		$_['PHONENUM']				= $Order->Customer->phone;
		
		// Billing
		$_['CREDITCARDTYPE']		= $Order->Billing->cardtype;
		$_['ACCT']					= $Order->Billing->card;
		$_['EXPDATE']				= date("mY",$Order->Billing->cardexpires);
		$_['CVV2']					= $Order->Billing->cvv;
		$_['STREET']				= $Order->Billing->address;
		$_['STREET2']				= $Order->Billing->xaddress;
		$_['CITY']					= $Order->Billing->city;
		$_['STATE']					= $Order->Billing->state;
		$_['ZIP']					= $Order->Billing->postcode;
		$_['COUNTRYCODE']			= $Order->Billing->country;

		if ($Order->Billing->country == "UK") // PayPal uses ISO 3361-1
			$_['COUNTRYCODE'] = "GB";
		
		// Shipping
		if (!empty($Order->Shipping->address) &&
				!empty($Order->Shipping->city) &&
				!empty($Order->Shipping->state) && 
				!empty($Order->Shipping->postcode) && 
				!empty($Order->Shipping->country)) {		
			$_['SHIPTONAME'] 			= $Order->Customer->firstname.' '.$Order->Customer->lastname;
			$_['SHIPTOPHONENUM']		= $Order->Customer->phone;
			$_['SHIPTOSTREET']			= $Order->Shipping->address;
			$_['SHIPTOSTREET2']			= $Order->Shipping->xaddress;
			$_['SHIPTOCITY']			= $Order->Shipping->city;
			$_['SHIPTOSTATE']			= $Order->Shipping->state;
			$_['SHIPTOZIP']				= $Order->Shipping->postcode;
			$_['SHIPTOCOUNTRYCODE']		= $Order->Shipping->country;
			if ($Order->Shipping->country == "UK") // PayPal uses ISO 3361-1
				$_['SHIPTOCOUNTRYCODE'] = "GB";

		}
		
		// Transaction
		$_['CURRENCYCODE']			= $this->settings['currency_code'];
		$_['AMT']					= number_format($Order->Totals->total,2);
		$_['ITEMAMT']				= number_format($Order->Totals->subtotal - 
													$Order->Totals->discount,2);
		$_['SHIPPINGAMT']			= number_format($Order->Totals->shipping,2);
		$_['TAXAMT']				= number_format($Order->Totals->tax,2);
		
		if (isset($Order->data['paypal-custom']))
			$_['CUSTOM'] = htmlentities($Order->data['paypal-custom']);
		
		// Line Items
		foreach($Order->Items as $i => $Item) {
			$_['L_NAME'.$i]			= $Item->name.((!empty($Item->optionlabel))?' '.$Item->optionlabel:'');
			$_['L_AMT'.$i]			= number_format($Item->unitprice,2);
			$_['L_NUMBER'.$i]		= $i;
			$_['L_QTY'.$i]			= $Item->quantity;
			$_['L_TAXAMT'.$i]		= number_format($Item->tax,2);
		}

		if ($Order->Totals->discount != 0) {
			$discounts = array();
			foreach($Shopp->Cart->data->PromosApplied as $promo)
				$discounts[] = $promo->name;
			
			$i++;
			$_['L_NUMBER'.$i]		= $i;
			$_['L_NAME'.$i]			= join(", ",$discounts);
			$_['L_AMT'.$i]			= number_format($Order->Totals->discount*-1,2);
			$_['L_QTY'.$i]			= 1;
			$_['L_TAXAMT'.$i]		= number_format(0,2);
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
		if ($this->settings['testmode'] == "on")
			curl_setopt($connection,CURLOPT_URL,$this->sandboxurl); // Sandbox testing
		else curl_setopt($connection,CURLOPT_URL,$this->liveurl); // Live		
		curl_setopt($connection, CURLOPT_SSL_VERIFYPEER, 0); 
		curl_setopt($connection, CURLOPT_SSL_VERIFYHOST, 0); 
		curl_setopt($connection, CURLOPT_NOPROGRESS, 1); 
		curl_setopt($connection, CURLOPT_VERBOSE, 1); 
		curl_setopt($connection, CURLOPT_FOLLOWLOCATION,0); 
		curl_setopt($connection, CURLOPT_POST, 1); 
		curl_setopt($connection, CURLOPT_POSTFIELDS, $this->transaction); 
		curl_setopt($connection, CURLOPT_TIMEOUT, 30); 
		curl_setopt($connection, CURLOPT_USERAGENT, SHOPP_GATEWAY_USERAGENT); 
		curl_setopt($connection, CURLOPT_REFERER, "https://".$_SERVER['SERVER_NAME']); 
		curl_setopt($connection, CURLOPT_RETURNTRANSFER, 1);
		$buffer = curl_exec($connection);
		if ($error = curl_error($connection)) 
			new ShoppError($error,'paypal_pro_connection',SHOPP_COMM_ERR);
		curl_close($connection);

		$Response = $this->response($buffer);
		return $Response;
	}
	
	function response ($buffer) {
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
		?>
		<tr id="paypalpro-settings">
			<th scope="row" valign="top">PayPal Pro</th>
			<td>                                        
				
				<div><input type="text" name="settings[PayPalPro][username]" id="paypal_pro_username" value="<?php echo $this->settings['username']; ?>" size="30" /><br /><label for="paypal_pro_username"><?php _e('Enter your PayPal API Username.'); ?></label></div>
				<div><input type="password" name="settings[PayPalPro][password]" id="paypal_pro_password" value="<?php echo $this->settings['password']; ?>" size="16" /><br /><label for="paypal_pro_password"><?php _e('Enter your PayPal API Password.'); ?></label></div>
				<div><input type="text" name="settings[PayPalPro][signature]" id="paypal_pro_signature" value="<?php echo $this->settings['signature']; ?>" size="48" /><br /><label for="paypal_pro_signature"><?php _e('Enter your PayPal API Signature.'); ?></label></div>
				<div><input type="hidden" name="settings[PayPalPro][testmode]" value="off" /><input type="checkbox" name="settings[PayPalPro][testmode]" id="paypal_pro_testmode" value="on"<?php echo ($this->settings['testmode'] == "on")?' checked="checked"':''; ?> /><label for="paypal_pro_testmode"> <?php _e('Test Mode Enabled'); ?></label></div>
				
				<div><strong>Accept these cards:</strong>
				<ul class="cards"><?php foreach($this->cards as $id => $card): 
					$checked = "";
					if (in_array($card,$this->settings['cards'])) $checked = ' checked="checked"';
				?>
					<li><input type="checkbox" name="settings[PayPalPro][cards][]" id="paypalpro_cards_<?php echo $id; ?>" value="<?php echo $card; ?>" <?php echo $checked; ?> /><label for="paypalpro_cards_<?php echo $id; ?>"> <?php echo $card; ?></label></li>
				<?php endforeach; ?></ul></div>
				
				<input type="hidden" name="module[<?php echo basename(__FILE__); ?>]" value="PayPalPro" />
			</td>
		</tr>
		<?php
	}
	
	function registerSettings () {
		?>
		gatewayHandlers.register('<?php echo addslashes(gateway_path(__FILE__)); ?>','paypalpro-settings');
		<?php
	}

} // end PayPalPro class

?>