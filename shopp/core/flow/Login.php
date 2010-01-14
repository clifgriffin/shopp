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
	
	/**
	 * Login constructor
	 *
	 * @author Jonathan Davis
	 * 
	 * @return void
	 **/
	function __construct () {
		
	}
	
	/**
	 * logins ()
	 * Handle login processing */
	function logins () {
		global $Shopp;
		if (!$this->data->Order->Customer) {
			$this->data->Order->Customer = new Customer();
			$this->data->Order->Billing = new Billing();
			$this->data->Order->Shipping = new Shipping();
		}
		
		$authentication = $Shopp->Settings->get('account_system');

		if (isset($_GET['acct']) && isset($this->data->Order->Customer) 
			&& $_GET['acct'] == "logout") {
				if ($authentication == "wordpress" && $this->data->login)
					add_action('shopp_logout','wp_clear_auth_cookie');					
				return $this->logout();
		}

		switch ($authentication) {
			case "wordpress":
				if ($this->data->login) add_action('wp_logout',array(&$this,'logout'));

				// See if the wordpress user is already logged in
				$user = wp_get_current_user();

				if (!empty($user->ID) && !$this->data->login) {
					if ($Account = new Customer($user->ID,'wpuser')) {
						$this->loggedin($Account);
						$this->data->Order->Customer->wpuser = $user->ID;
						break;
					}
				}
				
				if (empty($_POST['process-login'])) return false;
				
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
				if (!isset($_POST['process-login'])) return false;
				if ($_POST['process-login'] != "true") return false;
				$mode = "loginname";
				if (!empty($_POST['account-login']) && strpos($_POST['account-login'],'@') !== false)
					$mode = "email";
				$this->auth($_POST['account-login'],$_POST['password-login'],$mode);
				break;
		}

	}
	
	/**
	 * auth ()
	 * Authorize login credentials */
	function auth ($id,$password,$type='email') {
		global $Shopp;
		$db = DB::get();
		$authentication = $Shopp->Settings->get('account_system');
		switch($authentication) {
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

				if ($Account = new Customer($user->ID,'wpuser')) {
					$this->loggedin($Account);
					$this->data->Order->Customer->wpuser = $user->ID;
					add_action('wp_logout',array(&$this,'logout'));
				}
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

		$this->loggedin($Account);
		
	}
	
	/**
	 * loggedin()
	 * Initialize login data */
	function loggedin ($Account) {
		$this->data->login = true;
		$this->data->Order->Customer = $Account;
		unset($this->data->Order->Customer->password);
		$this->data->Order->Billing = new Billing($Account->id,'customer');
		$this->data->Order->Billing->card = "";
		$this->data->Order->Billing->cardexpires = "";
		$this->data->Order->Billing->cardholder = "";
		$this->data->Order->Billing->cardtype = "";
		$this->data->Order->Shipping = new Shipping($Account->id,'customer');
		if (empty($this->data->Order->Shipping->id))
			$this->data->Order->Shipping->copydata($this->data->Order->Billing);
		do_action_ref_array('shopp_login',array(&$Account));
	}
	
	/**
	 * logout()
	 * Clear the session account data */
	function logout () {
		do_action('shopp_logout');
		$this->data->login = false;
		$this->data->Order->wpuser = false;
		$this->data->Order->Customer->id = false;
		$this->data->Order->Billing->id = false;
		$this->data->Order->Billing->customer = false;
		$this->data->Order->Shipping->id = false;
		$this->data->Order->Shipping->customer = false;
		session_commit();
	}
	

} // END ckass Login

?>