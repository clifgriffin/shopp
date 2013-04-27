<?php
/**
 * Login
 *
 * Controller for handling logins
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, January 12, 2010
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @subpackage shopp
 **/

/**
 * Login
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package shopp
 **/
class Login {

	var $Customer = false;
	var $Billing = false;
	var $Shipping = false;

	function __construct () {

		$this->Customer =& ShoppOrder()->Customer;
		$this->Billing =& ShoppOrder()->Billing;
		$this->Shipping =& ShoppOrder()->Shipping;


		switch (shopp_setting('account_system')) {
			case 'shopp':
				add_action('shopp_logout',array($this,'logout'));
				break;
			case 'wordpress':
				add_action('set_logged_in_cookie',array($this,'wplogin'),10,4);
				add_action('wp_logout',array($this,'logout'));
				add_action('shopp_logout','wp_logout',1);
				break;
		}

		$this->process();

	}

	/**
	 * Handle Shopp login processing
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @return void
	 **/
	function process () {

		if (isset($_POST['shopp_registration'])) $this->registration();

		if (isset($_REQUEST['acct']) && $_REQUEST['acct'] == "logout" || isset($_REQUEST['logout'])) {
			// Set the last logged out action to save the session and redirect to remove the logout request
			add_action('shopp_logged_out',array($this,'redirect'),100);
			// Trigger the logout
			do_action('shopp_logout');
		}

		if ("wordpress" == shopp_setting('account_system')) {
			// See if the wordpress user is already logged in
			$user = wp_get_current_user();

			// Wordpress user logged in, but Shopp customer isn't
			if (!empty($user->ID) && !$this->Customer->logged_in()) {
				if ($Account = new Customer($user->ID,'wpuser')) {
					$this->login($Account);
					$this->Customer->wpuser = $user->ID;
					return;
				}
			}
		}

		if ( !isset($_POST['submit-login']) ) return false;

		// Prevent checkout form from processing
		remove_all_actions('shopp_process_checkout');

		switch (shopp_setting('account_system')) {
			case "shopp":
				$mode = "loginname";
				if (!empty($_POST['account-login']) && strpos($_POST['account-login'],'@') !== false)
					$mode = "email";
				$this->auth($_POST['account-login'],$_POST['password-login'],$mode);
				break;
			case "wordpress":
				if (!empty($_POST['account-login'])) {
					if (strpos($_POST['account-login'],'@') !== false) $mode = "email";
					else $mode = "loginname";
					$loginname = $_POST['account-login'];
				} else {
					new ShoppError(__('You must provide a valid login name or email address to proceed.'), 'missing_account', SHOPP_AUTH_ERR);
				}

				if ($loginname) {
					$this->auth($loginname,$_POST['password-login'],$mode);
				}
				break;
		}

	}

	/**
	 * Authorize login
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @param int $id The supplied identifying credential
	 * @param string $password The password provided for login authentication
	 * @param string $type (optional) Type of identifying credential provided (defaults to 'email')
	 * @return void
	 **/
	function auth ($id,$password,$type='email') {

		$errors = array(
			'empty_username' => __('The login field is empty.','Shopp'),
			'empty_password' => __('The password field is empty.','Shopp'),
			'invalid_email' => __('No customer account was found with that email.','Shopp'),
			'invalid_username' => __('No customer account was found with that login.','Shopp'),
			'incorrect_password' => __('The password is incorrect.','Shopp')
		);

		switch(shopp_setting('account_system')) {
			case 'shopp':
				$Account = new Customer($id,'email');

				if (empty($Account)) {
					new ShoppError( $errors['invalid_email'],'invalid_account',SHOPP_AUTH_ERR );
					return;
				}

				if (!wp_check_password($password,$Account->password)) {
					new ShoppError( $errors['incorrect_password'],'incorrect_password',SHOPP_AUTH_ERR );
					return;
				}

				break;

  		case 'wordpress':
			if('email' == $type){
				$user = get_user_by_email($id);
				if ($user) $loginname = $user->user_login;
				else {
					new ShoppError( $errors['invalid_email'],'invalid_account',SHOPP_AUTH_ERR );
					return;
				}
			} else $loginname = $id;

			$user = wp_authenticate($loginname,$password);
			if (is_wp_error($user)) { // WordPress User Authentication failed
				$code = $user->get_error_code();
				if ( isset($errors[ $code ]) ) new ShoppError( $errors[ $code ],'invalid_account',SHOPP_AUTH_ERR );
				else {
					$messages = $user->get_error_messages();
					foreach ($messages as $message)
						new ShoppError( sprintf(__('Unknown login error: %s'),$message),'unknown_login_error',SHOPP_AUTH_ERR);
				}
				return;
			} else {
				wp_set_auth_cookie($user->ID);
				do_action('wp_login', $loginname);
				wp_set_current_user($user->ID,$user->user_login);

				return;
			}
  			break;
			default: return;
		}

		$this->login($Account);
		do_action('shopp_auth');

	}

	/**
	 * Login to the linked Shopp account when logging into WordPress
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param $cookie N/A
	 * @param $expire N/A
	 * @param $expiration N/A
	 * @param int $user_id The WordPress user ID
	 * @return void
	 **/
	function wplogin ($cookie,$expire,$expiration,$user_id) {
		if ($Account = new Customer($user_id,'wpuser')) {
			$this->login($Account);
			add_action('wp_logout',array(&$this,'logout'));
		}
	}

	/**
	 * Initialize Shopp customer login data
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @return void
	 **/
	function login ($Account) {
		if ($this->Customer->login) return; // Prevent login pong (Shopp login <-> WP login)
		$this->Customer->copydata($Account,"",array());
		$this->Customer->login = true;
		unset($this->Customer->password);
		$this->Billing->load($Account->id,'customer');
		$this->Billing->card = "";
		$this->Billing->cardexpires = "";
		$this->Billing->cardholder = "";
		$this->Billing->cardtype = "";
		$this->Shipping->load($Account->id,'customer');
		if (empty($this->Shipping->id))
			$this->Shipping->copydata($this->Billing);

		// Login WP user if not logged in
		if ('wordpress' == shopp_setting('account_system') && !get_current_user_id()) {
			$user = get_user_by('id',$this->Customer->wpuser);
			@wp_set_auth_cookie($user->ID);
			wp_set_current_user($user->ID,$user->user_login);
		}

		do_action_ref_array('shopp_login',array(&$this->Customer));
	}

	/**
	 * Clear the Customer-related session data
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @return void
	 **/
	function logout () {
		if ('none' == shopp_setting('account_system')) return;
		$this->Customer = new Customer();
		$this->Billing = new BillingAddress();
		$this->Shipping = new ShippingAddress();
		$this->Shipping->locate();

		do_action_ref_array('shopp_logged_out',array(&$this->Customer));
	}

	function registration () {
		$Errors =& ShoppErrors();

		if (isset($_POST['info'])) $this->Customer->info = stripslashes_deep($_POST['info']);

		$_POST = apply_filters('shopp_customer_registration',$_POST);

		$this->Customer = new Customer();
		$this->Customer->updates($_POST);

		if (isset($_POST['confirm-password']))
			$this->Customer->confirm_password = $_POST['confirm-password'];

		$this->Billing = new BillingAddress();
		if (isset($_POST['billing']))
			$this->Billing->updates($_POST['billing']);

		$this->Shipping = new ShippingAddress();
		if (isset($_POST['shipping']))
			$this->Shipping->updates($_POST['shipping']);

		// Override posted shipping updates with billing address
		if (str_true($_POST['sameshipaddress']))
			$this->Shipping->updates($this->Billing,
				array("_datatypes","_table","_key","_lists","id","created","modified"));

		// WordPress account integration used, customer has no wp user
		if ("wordpress" == shopp_setting('account_system') && empty($this->Customer->wpuser)) {
			if ( $wpuser = get_current_user_id() ) $this->Customer->wpuser = $wpuser; // use logged in WordPress account
			else $this->Customer->create_wpuser(); // not logged in, create new account
		}

		if ($Errors->exist(SHOPP_ERR)) return false;

		// New customer, save hashed password
		if (empty($this->Customer->id) && !empty($this->Customer->password))
			$this->Customer->password = wp_hash_password($this->Customer->password);
		else unset($this->Customer->password); // Existing customer, do not overwrite password field!

		$this->Customer->save();
		if ($Errors->exist(SHOPP_ERR)) return false;

		$this->Billing->customer = $this->Customer->id;
		$this->Billing->save();

		if (!empty($this->Shipping->address)) {
			$this->Shipping->customer = $this->Customer->id;
			$this->Shipping->save();
		}

		do_action('shopp_customer_registered',$this->Customer);

		if (!empty($this->Customer->id)) $this->login($this->Customer);

		shopp_redirect(shoppurl(false,'account'));
	}

	function redirect () {
		global $Shopp;
		session_commit(); // Save the session just prior to redirect
		if (!empty($_POST['redirect'])) {
			if ($_POST['redirect'] == "checkout") shopp_redirect(shoppurl(false,'checkout',$Shopp->Gateways->secure));
			else shopp_safe_redirect($_POST['redirect']);
			exit();
		}
		shopp_safe_redirect(shoppurl(false,'account',$Shopp->Gateways->secure));
		exit();
	}

} // END class Login

?>