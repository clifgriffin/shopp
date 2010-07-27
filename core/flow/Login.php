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

	var $accounts = "none";		// Account system setting
	
	function __construct () {
		global $Shopp;
		
		$this->accounts = $Shopp->Settings->get('account_system');
		
		$this->Customer =& $Shopp->Order->Customer;
		$this->Billing =& $Shopp->Order->Billing;
		$this->Shipping =& $Shopp->Order->Shipping;
		
		add_action('shopp_logout',array(&$this,'logout'));

		if ($this->accounts == "wordpress") {
			add_action('set_logged_in_cookie',array(&$this,'wplogin'),10,4);
			add_action('wp_logout',array(&$this,'logout'));
			add_action('shopp_logout','wp_clear_auth_cookie');					
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
		global $Shopp;
		
		if (isset($_GET['acct']) && $_GET['acct'] == "logout")
			return do_action('shopp_logout');
			
		if ("wordpress" == $this->accounts) {
			// See if the wordpress user is already logged in
			$user = wp_get_current_user();

			// Wordpress user logged in, but Shopp customer isn't
			if (!empty($user->ID) && !$this->Customer->login) {
				if ($Account = new Customer($user->ID,'wpuser')) {
					$this->login($Account);
					$this->Customer->wpuser = $user->ID;
					break;
				}
			}
		}
			
		if (empty($_POST['process-login'])) return false;
		if ($_POST['process-login'] != "true") return false;
		
		// Prevent checkout form from processing
		remove_all_actions('shopp_process_checkout');

		switch ($this->accounts) {
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
			case "shopp":
				$mode = "loginname";
				if (!empty($_POST['account-login']) && strpos($_POST['account-login'],'@') !== false)
					$mode = "email";
				$this->auth($_POST['account-login'],$_POST['password-login'],$mode);
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
		global $Shopp;
		
		$db = DB::get();
		switch($this->accounts) {
			case "shopp":
				$Account = new Customer($id,'email');

				if (empty($Account)) {
					new ShoppError(__("No customer account was found with that email.","Shopp"),'invalid_account',SHOPP_AUTH_ERR);
					return false;
				} 

				if (!wp_check_password($password,$Account->password)) {
					new ShoppError(__("The password is incorrect.","Shopp"),'invalid_password',SHOPP_AUTH_ERR);
					return false;
				}	
						
				break;
				
  		case "wordpress":
			if($type == 'email'){
				$user = get_user_by_email($id);
				if ($user) $loginname = $user->user_login;
				else {
					new ShoppError(__("No customer account was found with that email.","Shopp"),'invalid_account',SHOPP_AUTH_ERR);
					return false;
				}
			} else $loginname = $id;
			$user = wp_authenticate($loginname,$password);
			if (!is_wp_error($user)) {
				wp_set_auth_cookie($user->ID, false, $Shopp->secure);
				do_action('wp_login', $loginname);

				return true;
			} else { // WordPress User Authentication failed
				$_e = $user->get_error_code();
				if($_e == 'invalid_username') new ShoppError(__("No customer account was found with that login.","Shopp"),'invalid_account',SHOPP_AUTH_ERR);
				else if($_e == 'incorrect_password') new ShoppError(__("The password is incorrect.","Shopp"),'invalid_password',SHOPP_AUTH_ERR);
				else new ShoppError(__('Unknown login error: ').$_e,false,SHOPP_AUTH_ERR);
				return false;
			}
  			break;
			default: return false;
		}

		$this->login($Account);
		
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
		global $Shopp;
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
		$this->Customer->login = false;
		$this->Customer->wpuser = false;
		$this->Customer->id = false;
		$this->Billing->id = false;
		$this->Billing->customer = false;
		$this->Shipping->id = false;
		$this->Shipping->customer = false;
		session_commit();
	}
	

} // END class Login

?>