<?php
/**
 * Order
 * 
 * Order data container and middleware object
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, January 12, 2010
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @subpackage transact
 **/

/**
 * Order
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package transact
 **/
class Order {
	
	var $Customer = false;
	var $Shipping = false;
	var $Billing = false;
	var $Cart = false;
	
	/**
	 * Order constructor
	 *
	 * @author Jonathan Davis
	 * 
	 * @return void
	 **/
	function __construct () {
		$this->Cart = new Cart();
		$this->Customer = new Customer();
		$this->Billing = new Billing();
		$this->Shipping = new Shipping();

		$this->Shipping->destination();
		
		$this->listeners();
	}
	
	function __wakeup () {
		$this->listeners();
	}
	
	function listeners () {
		add_action('shopp_checkout', array(&$this,'checkout'));
		add_action('shopp_update_destination',array(&$this->Shipping,'destination'));
	}
	
	/**
	 * Checkout form processing
	 *
	 * Handles taking user input from the checkout form and
	 * processing the information into useable order data
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function checkout () {

	}
	
	/**
	 * validate()
	 * Validate checkout form order data before processing */
	function validate () {
		global $Shopp;
		$authentication = $Shopp->Settings->get('account_system');
		
		if (empty($_POST['firstname']))
			return new ShoppError(__('You must provide your first name.','Shopp'),'cart_validation');

		if (empty($_POST['lastname']))
			return new ShoppError(__('You must provide your last name.','Shopp'),'cart_validation');

		$rfc822email =	'([^\\x00-\\x20\\x22\\x28\\x29\\x2c\\x2e\\x3a-\\x3c\\x3e\\x40\\x5b-\\x5d\\x7f-\\xff]+|\\x22([^\\x0d'.
						'\\x22\\x5c\\x80-\\xff]|\\x5c[\\x00-\\x7f])*\\x22)(\\x2e([^\\x00-\\x20\\x22\\x28\\x29\\x2c\\x2e'.
						'\\x3a-\\x3c\\x3e\\x40\\x5b-\\x5d\\x7f-\\xff]+|\\x22([^\\x0d\\x22\\x5c\\x80-\\xff]|\\x5c[\\x00-\\x7f])*'.
						'\\x22))*\\x40([^\\x00-\\x20\\x22\\x28\\x29\\x2c\\x2e\\x3a-\\x3c\\x3e\\x40\\x5b-\\x5d\\x7f-\\xff]+'.
						'|\\x5b([^\\x0d\\x5b-\\x5d\\x80-\\xff]|\\x5c[\\x00-\\x7f])*\\x5d)(\\x2e([^\\x00-\\x20\\x22\\x28'.
						'\\x29\\x2c\\x2e\\x3a-\\x3c\\x3e\\x40\\x5b-\\x5d\\x7f-\\xff]+|\\x5b([^\\x0d\\x5b-\\x5d\\x80-\\xff]'.
						'|\\x5c[\\x00-\\x7f])*\\x5d))*';
		if(!preg_match("!^$rfc822email$!", $_POST['email']))
			return new ShoppError(__('You must provide a valid e-mail address.','Shopp'),'cart_validation');
			
		if ($authentication == "wordpress" && !$this->data->login) {
			require_once(ABSPATH."/wp-includes/registration.php");
			
			// Validate possible wp account names for availability
			if(isset($_POST['login'])){
				if(username_exists($_POST['login'])) 
					return new ShoppError(__('The login name you provided is not available.  Try logging in if you have previously created an account.'), 'cart_validation');
			} else { // need to find a usuable login
				list($handle,$domain) = explode("@",$_POST['email']);
				if(!username_exists($handle)) $_POST['login'] = $handle;
				
				$handle = $_POST['firstname'].substr($_POST['lastname'],0,1);				
				if(!isset($_POST['login']) && !username_exists($handle)) $_POST['login'] = $handle;
				
				$handle = substr($_POST['firstname'],0,1).$_POST['lastname'];
				if(!isset($_POST['login']) && !username_exists($handle)) $_POST['login'] = $handle;
				
				$handle .= rand(1000,9999);
				if(!isset($_POST['login']) && !username_exists($handle)) $_POST['login'] = $handle;
				
				if(!isset($_POST['login'])) return new ShoppError(__('A login is not available for creation with the information you provided.  Please try a different email address or name, or try logging in if you previously created an account.'),'cart_validation');
			}
			if(SHOPP_DEBUG) new ShoppError('Login set to '. $_POST['login'] . ' for WordPress account creation.',false,SHOPP_DEBUG_ERR);			 
			$ExistingCustomer = new Customer($_POST['email'],'email');
			if (email_exists($_POST['email']) || !empty($ExistingCustomer->id))
				return new ShoppError(__('The email address you entered is already in use. Try logging in if you previously created an account, or enter another email address to create your new account.','Shopp'),'cart_validation');
		} elseif ($authentication == "shopp"  && !$this->data->login) {
			$ExistingCustomer = new Customer($_POST['email'],'email');
			if (!empty($ExistingCustomer->id)) 
				return new ShoppError(__('The email address you entered is already in use. Try logging in if you previously created an account, or enter another email address to create a new account.','Shopp'),'cart_validation');
		}

		// Validate WP account
		if (isset($_POST['login']) && empty($_POST['login']))
			return new ShoppError(__('You must enter a login name for your account.','Shopp'),'cart_validation');

		if (isset($_POST['login'])) {
			require_once(ABSPATH."/wp-includes/registration.php");
			if (username_exists($_POST['login']))
				return new ShoppError(__('The login name you provided is already in use. Try logging in if you previously created that account, or enter another login name for your new account.','Shopp'),'cart_validation');
		}

		if (isset($_POST['password'])) {
			if (empty($_POST['password']) || empty($_POST['confirm-password']))
				return new ShoppError(__('You must provide a password for your account and confirm it to ensure correct spelling.','Shopp'),'cart_validation');
			if ($_POST['password'] != $_POST['confirm-password']) {
				$_POST['password'] = ""; $_POST['confirm-password'] = "";
				return new ShoppError(__('The passwords you entered do not match. Please re-enter your passwords.','Shopp'),'cart_validation');				
			}
		}

		if (empty($_POST['billing']['address']) || strlen($_POST['billing']['address']) < 4) 
			return new ShoppError(__('You must enter a valid street address for your billing information.','Shopp'),'cart_validation');

		if (empty($_POST['billing']['postcode'])) 
			return new ShoppError(__('You must enter a valid postal code for your billing information.','Shopp'),'cart_validation');

		if (empty($_POST['billing']['country'])) 
			return new ShoppError(__('You must select a country for your billing information.','Shopp'),'cart_validation');

		// Skip validating billing details for free purchases 
		// and remote checkout systems
		if ((int)$this->data->Totals->total == 0
			|| !empty($_GET['shopp_xco'])) return apply_filters('shopp_validate_checkout',true);

		if (empty($_POST['billing']['card'])) 
			return new ShoppError(__('You did not provide a credit card number.','Shopp'),'cart_validation');

		if (empty($_POST['billing']['cardtype'])) 
			return new ShoppError(__('You did not select a credit card type.','Shopp'),'cart_validation');
			
		// credit card validation
		switch(strtolower($_POST['billing']['cardtype'])) {
			case "american express":
			case "amex": $pattern = '/^3[4,7]\d{13}$/'; break;
			case "diner's club":
			case "diners club": $pattern = '/^3[0,6,8]\d{12}$/'; break;
			case "discover": $pattern = '/^6011-?\d{4}-?\d{4}-?\d{4}$/'; break;
			case "mastercard": $pattern = '/^5[1-5]\d{2}-?\d{4}-?\d{4}-?\d{4}$/'; break;
			case "visa": $pattern = '/^4\d{3}-?\d{4}-?\d{4}-?\d{4}$/'; break;
			default: $pattern = false;
		}
		if ($pattern && !preg_match($pattern,$_POST['billing']['card'])) 
			return new ShoppError(__('The credit card number you provided is invalid.','Shopp'),'cart_validation');

		// credit card checksum validation
		$cs = 0;
		$cc = str_replace("-","",$_POST['billing']['card']);
		$code = strrev(str_replace("-","",$_POST['billing']['card']));
		for ($i = 0; $i < strlen($code); $i++) {
			$d = intval($code[$i]);
			if ($i & 1) $d *= 2;
			$cs += $d % 10;
			if ($d > 9) $cs += 1;
		}
		if ($cs % 10 != 0)
			return new ShoppError(__('The credit card number you provided is not valid.','Shopp'),'cart_validation');
			
		if (empty($_POST['billing']['cardexpires-mm'])) 
			return new ShoppError(__('You did not enter the month the credit card expires.','Shopp'),'cart_validation');

		if (empty($_POST['billing']['cardexpires-yy'])) 
			return new ShoppError(__('You did not enter the year the credit card expires.','Shopp'),'cart_validation');

		if (!empty($_POST['billing']['cardexpires-mm']) && !empty($_POST['billing']['cardexpires-yy']) 
		 	&& $_POST['billing']['cardexpires-mm'] < date('n') && $_POST['billing']['cardexpires-yy'] <= date('y')) 
			return new ShoppError(__('The credit card expiration date you provided has already expired.','Shopp'),'cart_validation');
		
		if (strlen($_POST['billing']['cardholder']) < 2) 
			return new ShoppError(__('You did not enter the name on the credit card you provided.','Shopp'),'cart_validation');
		
		if (strlen($_POST['billing']['cvv']) < 3) 
			return new ShoppError(__('You did not enter a valid security ID for the card you provided. The security ID is a 3 or 4 digit number found on the back of the credit card.','Shopp'),'cart_validation');
				
		return apply_filters('shopp_validate_checkout',true);
	}

	/**
	 * validorder()
	 * Validates order data during checkout processing to verify that sufficient information exists to process. */
	function validorder () {		
		$Order = $this->data->Order;
		$Customer = $Order->Customer;
		$Shipping = $this->data->Order->Shipping;
		$errorindex = 0;
		
		if (empty($this->contents)) { 
			new ShoppError(__("There are no items in the cart."),'invalid_order'.$errorindex++,SHOPP_TXN_ERR);
			return false;
		}
		
		$stock = true;
		foreach ($this->contents as $item) { 
			if (!$item->instock()){
				new ShoppError(sprintf(__("%s does not have sufficient stock to process order."),
				$item->name . ($item->optionlabel?" ({$item->optionlabel})":"")),
				'invalid_order'.$errorindex++,SHOPP_TXN_ERR);
				$stock = false;
			} 
		}
		
		if (!$stock) return false;

		if (empty($Order)) {
			new ShoppError(__("Missing order data."),'invalid_order'.$errorindex++,SHOPP_TXN_ERR); 
			return false;
		}
		
		$hasCustInfo = true;
		if (!$Customer) $hasCustInfo = false; // No Customer

		// Always require name and email
		if (empty($Customer->firstname) || empty($Customer->lastname)) $hasCustInfo = false;
		if (empty($Customer->email) ) $hasCustInfo = false;

		if (!$hasCustInfo) new ShoppError(__('There is not enough customer information to process the order.','Shopp'),'invalid_order'.$errorindex++,SHOPP_TXN_ERR);
		return $hasCustInfo;
		
		// Check for shipped items but no Shipping information
		$hasShipInfo = true;
		if ($this->data->Shipping) {
			if (empty($Shipping->address)) $hasShipInfo = false;
			if (empty($Shipping->country)) $hasShipInfo = false;
			if (empty($Shipping->postcode)) $hasShipInfo = false;
		}
		if (!$hasShipInfo) new ShoppError(__('The shipping address information is incomplete.  The order can not be processed','Shopp'),'invalid_order'.$errorindex++,SHOPP_TXN_ERR);
		return $hasShipInfo;
	}
	

} // END class Order


?>