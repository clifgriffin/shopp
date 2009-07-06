<?php
/**
 * Payson
 * @class Payson
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 27 May, 2009
 * @package Shopp
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
		
		if (isset($_POST['checkout']) && 
			$_POST['checkout'] == "process" && 
			!$loginproc) $this->checkout();
		
		// Capture processed payment
		if (isset($_POST['Paysonref'])) $_POST['checkout'] = "confirmed";

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
		
		echo "<pre>"; print_r($_POST); echo "</pre>";
		exit();
		
		// Validate the order notification
		if (empty($_POST['invoice']) || !$this->validipn())
			return new ShoppError(__('An unverifiable order notifcation was received from Payson. Possible fraudulent order attempt!','Shopp'),'paypal_trxn_verification',SHOPP_TRXN_ERR);
		
		session_unset();
		session_destroy();
		
		// Load the cart for the correct order
		$Shopp->Cart->session = $_POST['RefNr'];
		$Shopp->Cart->load();

		$Order = $Shopp->Cart->data->Order;
		$Order->Totals = $Shopp->Cart->data->Totals;
		$Order->Items = $Shopp->Cart->contents;
		$Order->Cart = $Shopp->Cart->session;

		// Validate the order data
		$validation = false;
		
		// Check for unique transaction id
		$Purchase = new Purchase($_POST['Paysonref'],'transactionid');
		
		
		$checkfields = array(
			$_POST['OkURL'],
			$_POST['PaysonRef'],
			$this->settings['key']
		);
		$checksum = md5(join('',$checkfields));
		
		if ($checksum == $_POST['MD5'] && empty($Purchase->id)) 
			$validation = true;

		if ($validation) $this->order();
		else new ShoppError(__('An order was received from Payson that could not be validated against existing pre-order data.  Possible order spoof attempt!','Shopp'),'payson_trxn_validation',SHOPP_TRXN_ERR);
		
		exit();
	}
	
	function order () {
		global $Shopp;
		$Order = $Shopp->Cart->data->Order;
		$Order->Totals = $Shopp->Cart->data->Totals;
		$Order->Items = $Shopp->Cart->contents;
		$Order->Cart = $Shopp->Cart->session;

		// Transaction successful, save the order
		
		// Create WordPress account (if necessary)
		if ($authentication == "wordpress" && 
			!$user = get_user_by_email($Order->Customer->email)) {
			require_once(ABSPATH."/wp-includes/registration.php");

			if (!empty($Order->Customer->login)) $handle = $Order->Customer->login;
			else {
				// No login provided, auto-generate login handle
				list($handle,$domain) = split("@",$Order->Customer->email);

				// The email handle exists, so use first name initial + lastname
				if (username_exists($handle)) 
					$handle = substr($Order->Customer->firstname,0,1).$Order->Customer->lastname;

				// That exists too *bangs head on wall*, ok add a random number too :P
				if (username_exists($handle)) 
					$handle .= rand(1000,9999);
			}
			
			if (username_exists($handle))
				new ShoppError(__('The login name you provided is already in use.  Please choose another login name.','Shopp'),'login_exists',SHOPP_ERR);
			
			// Create the WordPress account
			$wpuser = wp_insert_user(array(
				'user_login' => $handle,
				'user_pass' => $Order->Customer->password,
				'user_email' => $Order->Customer->email,
				'display_name' => $Order->Customer->firstname.' '.$Order->Customer->lastname,
				'nickname' => $handle,
				'first_name' => $Order->Customer->firstname,
				'last_name' => $Order->Customer->lastname
			));
			
			// Keep record of it in Shopp's customer records
			$Order->Customer->wpuser = $wpuser;
		}
		
		// If the shopper is already logged-in, save their updated customer info
		if ($Shopp->Cart->data->login && $authentication == "wordpress") {
			get_currentuserinfo();
			global $user_ID;
			$Order->Customer->wpuser = $user_ID;
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
		$Purchase->transactionid = $_POST['Paysonref'];
		$Purchase->fees = $_POST['Fee'];
		$Purchase->transtatus = "CHARGED";
		$Purchase->ip = $Shopp->Cart->ip;
		$Purchase->save();
		// echo "<pre>"; print_r($Purchase); echo "</pre>";

		foreach($Shopp->Cart->contents as $Item) {
			$Purchased = new Purchased();
			$Purchased->copydata($Item);
			$Purchased->purchase = $Purchase->id;
			if (!empty($Purchased->download)) $Purchased->keygen();
			$Purchased->save();
			if ($Item->inventory) $Item->unstock();
		}

		// Empty cart on successful order
		$Shopp->Cart->unload();
		session_destroy();

		// Start new cart session
		$Shopp->Cart = new Cart();
		session_start();
		
		// Keep the user loggedin
		if ($Shopp->Cart->data->login)
			$Shopp->Cart->loggedin($Order->Customer);
		
		// Save the purchase ID for later lookup
		$Shopp->Cart->data->Purchase = new Purchase($Purchase->id);
		$Shopp->Cart->data->Purchase->load_purchased();
		// $Shopp->Cart->save();

		// Allow other WordPress plugins access to Purchase data to extend
		// what Shopp does after a successful transaction
		do_action_ref_array('shopp_order_success',array(&$Shopp->Cart->data->Purchase));

		// Send the e-mail receipt
		$receipt = array();
		$receipt['from'] = '"'.get_bloginfo("name").'"';
		if ($Shopp->Settings->get('merchant_email')) 
			$receipt['from'] .= ' <'.$Shopp->Settings->get('merchant_email').'>';
		$receipt['to'] = "\"{$Purchase->firstname} {$Purchase->lastname}\" <{$Purchase->email}>";
		$receipt['subject'] = __('Order Receipt','Shopp');
		$receipt['receipt'] = $Shopp->Flow->order_receipt();
		$receipt['url'] = get_bloginfo('siteurl');
		$receipt['sitename'] = get_bloginfo('name');
		$receipt['orderid'] = $Purchase->id;
		
		$receipt = apply_filters('shopp_email_receipt_data',$receipt);
		
		// echo "<PRE>"; print_r($receipt); echo "</PRE>";
		shopp_email(SHOPP_TEMPLATES."/order.html",$receipt);
		
		if ($Shopp->Settings->get('receipt_copy') == 1) {
			$receipt['to'] = $Shopp->Settings->get('merchant_email');
			$receipt['subject'] = "New Order";
			shopp_email(SHOPP_TEMPLATES."/order.html",$receipt);
		}

		$ssl = true;
		// Test Mode will not require encrypted checkout
		if (strpos($gateway,"TestMode.php") !== false || isset($_GET['shopp_xco'])) $ssl = false;
		$link = $Shopp->link('receipt',$ssl);
		header("Location: $link");
		exit();
		
	}
	
	
	function error () {
		if (!empty($this->Response)) {
			
			$message = join("; ",$this->Response->l_longmessage);
			if (empty($message)) return false;
			return new ShoppError($message,'payson_transacton_error',SHOPP_TRXN_ERR,
				array('code'=>$code));
		}
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
				return '<p><a href="'.$url.'">'.__('Checkout with Payson','Shopp').'</a></p>';
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