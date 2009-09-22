<?php
/**
 * NetCash
 * @class NetCash
 *
 * @author Jonathan Davis
 * @version 1.0.1
 * @copyright Ingenesis Limited, 27 May, 2009
 * @package Shopp
 * 
 * $Id$
 **/

class NetCash {          
	var $type = "xco"; // Define as an External CheckOut/remote checkout processor
	var $checkout_url = 'https://gateway.netcash.co.za/vvonline/ccnetcash.asp';
	var $transaction = array();
	var $settings = array();
	var $Response = false;
	var $checkout = true;


	function NetCash () {
		global $Shopp;
		$this->settings = $Shopp->Settings->get('NetCash');
		$this->settings['merchant_email'] = $Shopp->Settings->get('merchant_email');
		$this->settings['base_operations'] = $Shopp->Settings->get('base_operations');

		$this->ipn = add_query_arg('shopp_xorder','NetCash',$Shopp->link('catalog',true));

		$loginproc = (isset($_POST['process-login']) 
			&& $_POST['process-login'] != 'false')?$_POST['process-login']:false;
			
		if (isset($_POST['checkout']) && 
			$_POST['checkout'] == "process" && 
			!$loginproc) $this->checkout();
		
		// Capture processed payment
		if (isset($_GET['Reference'])) $_POST['checkout'] = "confirmed";

		add_action('shopp_save_payment_settings',array(&$this,'saveSettings'));
	}
	
	function actions () {
		add_filter('shopp_confirm_url',array(&$this,'url'));
		add_filter('shopp_confirm_form',array(&$this,'form'));
	}
	
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
		
		header("Location: ".add_query_arg('shopp_xco','NetCash/NetCash',$Shopp->link('confirm-order',false)));
		exit();
	}
	
	/**
	 * form()
	 * Builds a hidden form to submit to NetCash when confirming the order for processing */
	function form ($form) {
		global $Shopp;
		$Order = $Shopp->Cart->data->Order;
		$Order->Totals = $Shopp->Cart->data->Totals;
		$Order->Items = $Shopp->Cart->contents;
		$Order->Cart = $Shopp->Cart->session;
		
		$_ = array();
		$_['m_1'] = $this->settings['username'];
		$_['m_2'] = $this->settings['password'];
		$_['m_3'] = $this->settings['PIN'];
		$_['p1'] = $this->settings['terminal'];
		$_['p2'] = mktime();
		$_['p3'] = get_bloginfo('sitename');
		$_['p4'] = number_format($Order->Totals->total,2);
		$_['p10'] = $Shopp->link('checkout');
		$_['Budget'] = 'Y';
		$_['m_4'] = $Order->Cart;
		// $_['m_5'] = '';		
		// $_['m_6'] = '';
		$_['m_9'] = $Order->Customer->email;
		$_['m_10'] = 'shopp_xco=NetCash/NetCash';
		
		return $form.$this->format($_);
	}
		
	function process () {
		global $Shopp;
		new ShoppError('NetCash sent an order for processing...','netcash_process',SHOPP_DEBUG_ERR);

		// Validate the order notification
		$returned = array('TransactionAccepted','CardHolderIpAddr','Reference','Amount','Extra1');
		foreach($returned as $key) {
			if (!isset($_GET[$key]) || empty($_GET[$key])) {
				new ShoppError(__('An unverifiable order was received from NetCash. Possible fraudulent order attempt!','Shopp'),'netcash_trxn_verification',SHOPP_TRXN_ERR);
				return false;
			}
		}
		
		if ($_GET['TransactionAccepted'] != 'true') {
			new ShoppError(__('The transaction failed: ','Shopp').$_GET['Reason'],'netcash_trxn_verification',SHOPP_TRXN_ERR);
			return false;
		}
		
		// Check for unique transaction id
		$Purchase = new Purchase($_GET['Extra1'],'transactionid');

		// session_unset();
		// session_destroy();

		// Load the cart for the correct order
		$Shopp->Cart->session = $_GET['Extra1'];
		$Shopp->Cart->load($Shopp->Cart->session);
				
		$Order = $Shopp->Cart->data->Order;
		$Order->Totals = $Shopp->Cart->data->Totals;
		$Order->Items = $Shopp->Cart->contents;
		$Order->Cart = $Shopp->Cart->session;

		$spoof = __('An order was received from NetCash that could not be validated against existing pre-order data.  Possible order spoof attempt!','Shopp');
		if ($Order->Cart != $_GET['Extra1']) {
			new ShoppError($spoof,'netcash_trxn_validation',SHOPP_TRXN_ERR);
			new ShoppError('The session did not match the NetCash transaction reference number. (Session ID: '.$Order->Cart.')','netcash_trxn_validation',SHOPP_DEBUG_ERR);
		}
		
		if (!empty($Purchase->id)) {
			new ShoppError($spoof,'netcash_trxn_validation',SHOPP_TRXN_ERR);
			new ShoppError('A purchase already exists for the transaction sent by NetCash. (Purchase ID: '.$Purchase->id.')','netcash_trxn_validation',SHOPP_DEBUG_ERR);
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
			$Order->Shipping->customer = $Order->Customxer->id;
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
		$Purchase->gateway = "NetCash";
		$Purchase->cardtype = "NetCash";
		$Purchase->transactionid = $_GET['Reference'];
		// $Purchase->fees = $_GET['Fee'];
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
		
		return $Purchase;
	}	
	
	function error () {
		if (!empty($this->Response)) {
			
			$message = join("; ",$this->Response->l_longmessage);
			if (empty($message)) return false;
			return new ShoppError($message,'netcash_trxn_error',SHOPP_TRXN_ERR,
				array('code'=>$code));
		}
	}
		
	function send () {
		$connection = curl_init();
		curl_setopt($connection,CURLOPT_URL,$this->checkout_url); // Live		
		curl_setopt($connection, CURLOPT_SSL_VERIFYPEER, 0); 
		curl_setopt($connection, CURLOPT_SSL_VERIFYHOST, 0); 
		curl_setopt($connection, CURLOPT_NOPROGRESS, 1); 
		curl_setopt($connection, CURLOPT_VERBOSE, 1); 
		curl_setopt($connection, CURLOPT_FOLLOWLOCATION,1); 
		curl_setopt($connection, CURLOPT_POST, 1); 
		curl_setopt($connection, CURLOPT_POSTFIELDS, $this->transaction); 
		curl_setopt($connection, CURLOPT_TIMEOUT, 10); 
		curl_setopt($connection, CURLOPT_USERAGENT, SHOPP_GATEWAY_USERAGENT); 
		curl_setopt($connection, CURLOPT_REFERER, "http://".$_SERVER['SERVER_NAME']); 
		curl_setopt($connection, CURLOPT_FAILONERROR, 1);
		curl_setopt($connection, CURLOPT_RETURNTRANSFER, 1);
		$buffer = curl_exec($connection);   
		if ($error = curl_error($connection)) 
			new ShoppError($error,'netcash_connection',SHOPP_COMM_ERR);
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
				$args['shopp_xco'] = 'NetCash/NetCash';
				if (isset($options['pagestyle'])) $args['pagestyle'] = $options['pagestyle'];
				$label = (isset($options['netcash-label']))?$options['netcash-label']:'Checkout with NetCash';
				$url = add_query_arg($args,$Shopp->link('checkout'));
				return '<p><a href="'.$url.'" class="netcash_checkout">'.$label.'</a></p>';
		}
	}

	// Required, but not used
	function billing () {}
	
	function url ($url) {
		return $this->checkout_url;
	}
	
	function settings () {
		?>
			<th scope="row" valign="top"><label for="netcash-enabled">NetCash</label></th> 
			<td><input type="hidden" name="settings[NetCash][billing-required]" value="off" /><input type="hidden" name="settings[NetCash][enabled]" value="off" /><input type="checkbox" name="settings[NetCash][enabled]" value="on" id="netcash-enabled"<?php echo ($this->settings['enabled'] == "on")?' checked="checked"':''; ?>/><label for="netcash-enabled"> <?php _e('Enable','Shopp'); ?> NetCash</label>
				<div id="netcash-settings">
		
				<p><input type="text" name="settings[NetCash][username]" id="netcash-username" size="30" value="<?php echo $this->settings['username']; ?>"/><br />
				<?php _e('Enter your NetCash account username.','Shopp'); ?></p>

				<p><input type="password" name="settings[NetCash][password]" id="netcash-password" size="30" value="<?php echo $this->settings['password']; ?>"/><br />
				<?php _e('Enter your NetCash account password.','Shopp'); ?></p>

				<p><input type="text" name="settings[NetCash][PIN]" id="netcash-PIN" size="8" value="<?php echo $this->settings['PIN']; ?>"/><br />
				<?php _e('Enter your NetCash account PIN.','Shopp'); ?></p>
				<p><input type="text" name="settings[NetCash][terminal]" id="netcash-terminal" size="5" value="<?php echo $this->settings['terminal']; ?>"/><br />
				<?php _e('Enter your NetCash terminal number.','Shopp'); ?></p>

				<?php if (!empty($this->settings['apiurl'])): ?>
				<p><input type="text" name="settings[NetCash][apiurl]" id="netcash-apiurl" size="48" value="<?php echo $this->settings['apiurl']; ?>" readonly="readonly" class="select" /><br />
				<strong>Copy this URL to your NetCash backoffice as both the Accept and Reject callback URLs found under:<br />credit cards &rarr; Credit Service Administration &rarr; Adjust Gateway Defaults</strong></p>
				<?php endif;?>
				
				<input type="hidden" name="settings[NetCash][path]" value="<?php echo gateway_path(__FILE__); ?>"  />
				<input type="hidden" name="settings[xco_gateways][]" value="<?php echo gateway_path(__FILE__); ?>"  />
				
				</div>
			</td>
		<?php
	}
	
	function registerSettings () {
		?>
		xcosettings('#netcash-enabled','#netcash-settings');
		<?php
	}

	function saveSettings () {
		global $Shopp;
		if (!empty($_POST['settings']['NetCash']['username']) 
			&& !empty($_POST['settings']['NetCash']['password'])
			&& !empty($_POST['settings']['NetCash']['PIN'])
			&& !empty($_POST['settings']['NetCash']['terminal'])) {

			$NetCash = new NetCash();
			$url = $Shopp->link('checkout',false);
			$_POST['settings']['NetCash']['apiurl'] = $url;
		}
	}

} // end NetCash class

?>