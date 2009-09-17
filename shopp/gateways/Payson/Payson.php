<?php
/**
 * Payson
 * @class Payson
 *
 * @author Jonathan Davis
 * @version 1.0.1
 * @copyright Ingenesis Limited, 27 May, 2009
 * @package Shopp
 * 
 * $Id$
 **/
class Payson {          
	var $type = "xco"; // Define as an External CheckOut/remote checkout processor
	var $testurl = 'https://www.payson.se/testagent/default.aspx';
	var $url = 'https://www.payson.se/merchant/default.aspx';
	var $transaction = array();
	var $settings = array();
	var $Response = false;
	var $checkout = true;

	function Payson () {
		
		global $Shopp;
		$this->settings = $Shopp->Settings->get('Payson');
		$this->settings['merchant_email'] = $Shopp->Settings->get('merchant_email');
		$this->settings['base_operations'] = $Shopp->Settings->get('base_operations');
		
		$loginproc = (isset($_POST['process-login']) 
			&& $_POST['process-login'] != 'false')?$_POST['process-login']:false;

		if (isset($_POST['checkout']) && 
			$_POST['checkout'] == "process" && 
			!$loginproc) $this->checkout();
		
		// Capture processed payment
		if (isset($_GET['Paysonref'])) $_POST['checkout'] = "confirmed";

	}
	
	function actions () {
		add_filter('shopp_confirm_url',array(&$this,'url'));
		add_filter('shopp_confirm_form',array(&$this,'form'));
	}
	
	/* Handle the checkout form */
	function checkout () {
		global $Shopp;
		if (empty($_POST['checkout'])) return false;

		// Save checkout data
		$Order = $Shopp->Cart->data->Order;

		if (isset($_POST['data'])) $Order->data = $_POST['data'];
		if (empty($Order->Customer))
			$Order->Customer = new Customer();
		$Order->Customer->updates($_POST);
		$Order->Customer->confirm_password = $_POST['confirm-password'];

		if (empty($Order->Billing))
			$Order->Billing = new Billing();
		$Order->Billing->updates($_POST['billing']);

		if (empty($Order->Shipping))
			$Order->Shipping = new Shipping();
			
		if ($_POST['shipping']) $Order->Shipping->updates($_POST['shipping']);
		if (!empty($_POST['shipmethod'])) $Order->Shipping->method = $_POST['shipmethod'];
		else $Order->Shipping->method = key($Shopp->Cart->data->ShipCosts);

		// Override posted shipping updates with billing address
		if ($_POST['sameshipaddress'] == "on")
			$Order->Shipping->updates($Order->Billing,
				array("_datatypes","_table","_key","_lists","id","created","modified"));

		$estimatedTotal = $Shopp->Cart->data->Totals->total;
		$Shopp->Cart->updated();
		$Shopp->Cart->totals();
		
		if ($Shopp->Cart->validate() !== true) {
			$_POST['checkout'] = false;
			return;
		}
		
		header("Location: ".add_query_arg('shopp_xco','Payson/Payson',$Shopp->link('confirm-order',false)));
		exit();
	}
	
	/**
	 * form()
	 * Builds a hidden form to submit to Payson when confirming the order for processing */
	function form ($form) {
		global $Shopp;
		$Order = $Shopp->Cart->data->Order;
		$Order->Totals = $Shopp->Cart->data->Totals;
		$Order->Items = $Shopp->Cart->contents;
		$Order->Cart = $Shopp->Cart->session;
		
		$_ = array();
		
		$_['Agentid']				= $this->settings['agentid'];
		$_['SellerEmail']			= $this->settings['email'];
		$_['GuaranteeOffered']		= $this->settings['guarantee'];
		$_['PaymentMethod']			= $this->settings['payment'];
		$_['Description']			= $this->settings['description'];
		
		$_['BuyerEmail']			= $Order->Customer->email;
		$_['BuyerFirstName']		= $Order->Customer->firstname;
		$_['BuyerLastname']			= $Order->Customer->lastname;
		
		$_['Cost']					= number_format($Order->Totals->subtotal+$Order->Totals->tax,2,",","");
		$_['ExtraCost']				= number_format($Order->Totals->shipping,2,",","");
		
		$_['RefNr']					= $Order->Cart;
		
		$_['OkUrl']					= add_query_arg('shopp_xco','Payson/Payson',$Shopp->link('confirm-order'));
		$_['CancelUrl']				= $Shopp->link('cart');
		
		$checkfields = array(
			$_['SellerEmail'],
			$_['Cost'],
			$_['ExtraCost'],
			$_['OkUrl'],
			$_['GuaranteeOffered'].$this->settings['key']
		);
		$_['MD5']					= md5(join(':',$checkfields));
				
		return $form.$this->format($_);
	}
		
	function process () {
		global $Shopp;
		new ShoppError('Payson process() called...','payson_process',SHOPP_DEBUG_ERR);
		
		// Validate the order notification
		$returned = array('Paysonref','OkURL','RefNr','MD5');
		foreach($returned as $key) {
			if (!isset($_GET[$key]) || empty($_GET[$key])) {
				new ShoppError(__('An unverifiable order was received from Payson. Possible fraudulent order attempt!','Shopp'),'paypal_trxn_verification',SHOPP_TRXN_ERR);
				return false;
			}
		}
		
		// Check for unique transaction id
		$Purchase = new Purchase($_GET['Paysonref'],'transactionid');
		if (!empty($Purchase->id)) return $Purchase; // Purchase already recorded

		session_unset();
		session_destroy();
		
		// Load the cart for the correct order
		$Shopp->Cart->session = $_GET['RefNr'];
		$Shopp->Cart->load($Shopp->Cart->session);

		$Order = $Shopp->Cart->data->Order;
		$Order->Totals = $Shopp->Cart->data->Totals;
		$Order->Items = $Shopp->Cart->contents;
		$Order->Cart = $Shopp->Cart->session;
		
		$checkfields = array(
			$_GET['OkURL'],
			$_GET['Paysonref'],
			$this->settings['key']
		);
		$checksum = md5(join('',$checkfields));

		if ($Order->Cart != $_GET['RefNr'] || $checksum != $_GET['MD5']) {
			new ShoppError(__('An order was received from Payson that could not be validated against existing pre-order data.  Possible order spoof attempt!','Shopp'),'payson_trxn_validation',SHOPP_TRXN_ERR);
			return false;
		} 
		// Transaction successful, save the order
		
		if ($authentication == "wordpress") {
			// Check if they've logged in
			// If the shopper is already logged-in, save their updated customer info
			if ($Shopp->Cart->data->login) {
				if (SHOPP_DEBUG) new ShoppError('Customer logged in, linking Shopp customer account to existing WordPress account.',false,SHOPP_DEBUG_ERR);
				get_currentuserinfo();
				global $user_ID;
				$Order->Customer->wpuser = $user_ID;
			}
			
			// Create WordPress account (if necessary)
			if (!$Order->Customer->wpuser) {
				if (SHOPP_DEBUG) new ShoppError('Creating a new WordPress account for this customer.',false,SHOPP_DEBUG_ERR);
				$Order->Customer->new_wpuser();
			}
		}

		// Create a WP-compatible password hash to go in the db
		if (empty($Order->Customer->id))
			$Order->Customer->password = wp_hash_password($Order->Customer->password);
		$Order->Customer->save();

		$Order->Billing->customer = $Order->Customer->id;
		$Order->Billing->card = substr($Order->Billing->card,-4);
		$Order->Billing->save();

		if (!empty($Order->Shipping->address)) {
			$Order->Shipping->customer = $Order->Customer->id;
			$Order->Shipping->save();
		}
		
		$Promos = array();
		foreach ($Shopp->Cart->data->PromosApplied as $promo)
			$Promos[$promo->id] = $promo->name;

		$Purchase = new Purchase();
		$Purchase->customer = $Order->Customer->id;
		$Purchase->billing = $Order->Billing->id;
		$Purchase->shipping = $Order->Shipping->id;
		$Purchase->data = $Order->data;
		$Purchase->promos = $Promos;
		$Purchase->copydata($Order->Customer);
		$Purchase->copydata($Order->Billing);
		$Purchase->copydata($Order->Shipping,'ship');
		$Purchase->copydata($Shopp->Cart->data->Totals);
		$Purchase->freight = $Shopp->Cart->data->Totals->shipping;
		$Purchase->gateway = "Payson";
		$Purchase->transactionid = $_GET['Paysonref'];
		$Purchase->fees = $_GET['Fee'];
		$Purchase->transtatus = "CHARGED";
		$Purchase->ip = $Shopp->Cart->ip;
		$Purchase->save();

		foreach($Shopp->Cart->contents as $Item) {
			$Purchased = new Purchased();
			$Purchased->copydata($Item);
			$Purchased->purchase = $Purchase->id;
			if (!empty($Purchased->download)) $Purchased->keygen();
			$Purchased->save();
			if ($Item->inventory) $Item->unstock();
		}
		
		return true;

	}
		
	function error () {}
	
	function transactionid() {
		return $_GET['Paysonref'];
	}
		
	function send () {
		$connection = curl_init();
		if ($this->settings['testmode'] == "on")
			curl_setopt($connection,CURLOPT_URL,$this->sandbox_url); // Sandbox testing
		else curl_setopt($connection,CURLOPT_URL,$this->checkout_url); // Live		
		curl_setopt($connection, CURLOPT_SSL_VERIFYPEER, 0); 
		curl_setopt($connection, CURLOPT_SSL_VERIFYHOST, 0); 
		curl_setopt($connection, CURLOPT_NOPROGRESS, 1); 
		curl_setopt($connection, CURLOPT_VERBOSE, 1); 
		curl_setopt($connection, CURLOPT_FOLLOWLOCATION,1); 
		curl_setopt($connection, CURLOPT_POST, 1); 
		curl_setopt($connection, CURLOPT_POSTFIELDS, $this->transaction); 
		curl_setopt($connection, CURLOPT_TIMEOUT, 5); 
		curl_setopt($connection, CURLOPT_USERAGENT, SHOPP_GATEWAY_USERAGENT); 
		curl_setopt($connection, CURLOPT_REFERER, "http://".$_SERVER['SERVER_NAME']); 
		curl_setopt($connection, CURLOPT_FAILONERROR, 1);
		curl_setopt($connection, CURLOPT_RETURNTRANSFER, 1);
		$buffer = curl_exec($connection);   
		if ($error = curl_error($connection)) 
			new ShoppError($error,'paypal_standard_connection',SHOPP_COMM_ERR);
		curl_close($connection);
		
		$this->Response = $buffer;
		return $this->Response;
	}
	
	function response () { /* Placeholder */ }

	/**
	 * encode()
	 * Builds a get/post encoded string from the provided $data */
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
	
	/**
	 * format()
	 * Generates hidden inputs based on the supplied $data */
	function format ($data) {
		$query = "";
		foreach($data as $key => $value) {
			if (is_array($value)) {
				foreach($value as $item)
					$query .= '<input type="hidden" name="'.$key.'[]" value="'.attribute_escape($item).'" />';
			} else {
				$query .= '<input type="hidden" name="'.$key.'" value="'.attribute_escape($value).'" />';
			}
		}
		return $query;
	}
	
	function tag ($property,$options=array()) {
		global $Shopp;
		switch ($property) {
			case "button":
				$args = array();
				$args['shopp_xco'] = 'Payson/Payson';
				if (isset($options['pagestyle'])) $args['pagestyle'] = $options['pagestyle'];
				$url = add_query_arg($args,$Shopp->link('checkout'));
				return '<a href="'.$url.'" class="right">'.__('Checkout with Payson','Shopp').' &raquo;</a>';
		}
	}

	// Required, but not used
	function billing () {}
	
	function url ($url) {
		if ($this->settings['testmode'] == "on") return $this->testurl;
		else return $this->url;
	}
	
	function settings () {
		?>
			<th scope="row" valign="top"><label for="payson-enabled">Payson</label></th> 
			<td><input type="hidden" name="settings[Payson][billing-required]" value="off" /><input type="hidden" name="settings[Payson][enabled]" value="off" /><input type="checkbox" name="settings[Payson][enabled]" value="on" id="payson-enabled"<?php echo ($this->settings['enabled'] == "on")?' checked="checked"':''; ?>/><label for="payson-enabled"> <?php _e('Enable','Shopp'); ?> Payson</label>
				<div id="payson-settings">
		
				<p><input type="text" name="settings[Payson][agentid]" id="payson-agentid" size="7" value="<?php echo $this->settings['agentid']; ?>"/><br />
				<?php _e('Enter your Payson Agent ID.','Shopp'); ?></p>

				<p><input type="text" name="settings[Payson][email]" id="payson-email" size="30" value="<?php echo $this->settings['email']; ?>"/><br />
				<?php _e('Enter your Payson seller email address.','Shopp'); ?></p>

				<p><input type="text" name="settings[Payson][key]" id="payson-key" size="40" value="<?php echo $this->settings['key']; ?>"/><br />
				<?php _e('Enter your Payson secret key.','Shopp'); ?></p>

				<p><input type="text" name="settings[Payson][description]" id="payson-description" size="40" value="<?php echo $this->settings['description']; ?>"/><br />
				<?php _e('Enter a name or description for your store.','Shopp'); ?></p>

				<p><select name="settings[Payson][payment]" id="payson-payment">
					<?php
						echo menuoptions(array(
							__('Credit Cards, Internet Banks &amp; Payson','Shopp'),
							__('Credit Cards Only','Shopp'),
							__('Internet Banks Only','Shopp'),
							__('Payson Account Funds Only','Shopp'),
							__('Internet Banks &amp; Payson Account Funds','Shopp')
						),$this->settings['payment']);
					?>
					</select><br />
				<?php _e('Choose the payment methods accepted.','Shopp'); ?></label></p>
								
				<p><label for="payson-guarantee"><input type="hidden" name="settings[Payson][guarantee]" value="0" /><input type="checkbox" name="settings[Payson][guarantee]" id="payson-guarantee" value="1"<?php if ($this->settings['guarantee'] == "1") echo ' checked="checked"'; ?> />
				<?php _e('Offer Payson Guarantee payments.','Shopp'); ?></label></p>

				<p><label for="payson-testmode"><input type="hidden" name="settings[Payson][testmode]" value="off" /><input type="checkbox" name="settings[Payson][testmode]" id="payson-testmode" value="on"<?php echo ($this->settings['testmode'] == "on")?' checked="checked"':''; ?> /> <?php _e('Enable Test Mode','Shopp'); ?></label></p>
				
				<input type="hidden" name="settings[Payson][path]" value="<?php echo gateway_path(__FILE__); ?>"  />
				<input type="hidden" name="settings[xco_gateways][]" value="<?php echo gateway_path(__FILE__); ?>"  />
				
				</div>
			</td>
		<?php
	}
	
	function registerSettings () {
		?>
		xcosettings('#payson-enabled','#payson-settings');
		<?php
	}

} // end Payson class

?>