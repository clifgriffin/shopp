<?php
/**
 * Customer class
 * Customer contact information
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 28 March, 2008
 * @package shopp
 * @since 1.0
 * @subpackage customer
 **/

require("Billing.php");
require("Shipping.php");

class Customer extends DatabaseObject {
	static $table = "customer";
	
	var $login = false;
	var $info = false;
	var $newuser = false;
	
	var $accounts = "none";		// Account system setting
	var $loginname = false;		// Account login name
	var $merchant = "";

	var $pages = array();		// Account pages
	var $menus = array();		// Account menus
	
	function __construct ($id=false,$key=false) {
		global $Shopp;
		
		$this->accounts = $Shopp->Settings->get('account_system');
		$this->merchant = $Shopp->Settings->get('merchant_email');
		
		$this->init(self::$table);
		$this->load($id,$key);
		if (!empty($this->id)) $this->load_info();

		$this->listeners();
	}
	
	function __wakeup () {
		$this->listeners();
	}
	
	function listeners () {
		add_action('parse_request',array(&$this,'menus'));
		add_action('shopp_account_management',array(&$this,'management'));
	}
	
	/**
	 * Loads customer 'info' meta data
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function load_info () {
		$this->info = new ObjectMeta($this->id,'customer');
		if (!$this->info) $this->info = new ObjectMeta();
	}
	
	function save () {
		parent::save();
		
		if (empty($this->info) || !is_array($this->info)) return true; 
		foreach ((array)$this->info as $name => $value) {
			$Meta = new MetaObject(array(
				'parent' => $this->id,
				'context' => 'customer',
				'type' => 'meta',
				'name' => $name
			));
			$Meta->parent = $this->id;
			$Meta->context = 'customer';
			$Meta->type = 'meta';
			$Meta->name = $name;
			$Meta->value = $value;
			$Meta->save();
		}
	}
	
	function addpage ($request,$label,$visible=true,$callback=false,$position=0) {
		$this->pages[$request] = new CustomerAccountPage($request,$label,$callback);
		if ($visible) {
			array_splice($this->menus,$position,0,array(&$this->pages[$request]));
		}
	}
	
	function menus () {
		global $wp;
		$this->pages = array();
		$this->menus = array();
		$this->addpage('logout',__('Logout','Shopp'));
		$this->addpage('history',__('Order History','Shopp'),true,array(&$this,'load_orders'));
		$this->addpage('downloads',__('Downloads','Shopp'),true,array(&$this,'load_downloads'));
		$this->addpage('account',__('My Account','Shopp'));
		
		// Pages with not in menu navigation
		$this->addpage('order','Order',false,array(&$this,'order'));
		$this->addpage('recover','Password Recovery',false);

		if (isset($wp->query_vars['acct']) && $wp->query_vars['acct'] == "rp") $this->reset_password($_GET['key']);
		if (isset($_POST['recover-login'])) $this->recovery();

		do_action_ref_array('shopp_account_menu',array(&$this));
	}
	
	/**
	 * Management menu controller for the account manager
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return boolean|string output based on the account menu request
	 **/
	function management () {
	
		if (isset($_GET['acct']) && isset($this->pages[$_GET['acct']]) 
				&& isset($this->pages[$_GET['acct']]->handler) 
				&& is_callable($this->pages[$_GET['acct']]->handler))
			call_user_func($this->pages[$_GET['acct']]->handler);

		if (!empty($_POST['customer'])) {
			
			$_POST['phone'] = preg_replace('/[^\d\(\)\-+\. (ext|x)]/','',$_POST['phone']);
			
			$this->updates($_POST);
			if (isset($_POST['info'])) $this->info = $_POST['info'];
			
			if (!empty($_POST['password']) && $_POST['password'] == $_POST['confirm-password']) {
				$this->password = wp_hash_password($_POST['password']);
				if($this->accounts == "wordpress" && !empty($this->wpuser)) wp_set_password( $_POST['password'], $this->wpuser );
				$this->_password_change = true;
			} else {
				if (!empty($_POST['password'])) new ShoppError(__('The passwords you entered do not match. Please re-enter your passwords.','Shopp'), 'customer_account_management');
			}
			$this->save();
			$this->load_info();
			$this->_saved = true;
		}
		
	}
	
	function order () {
		global $Shopp;
		
		if (!empty($_POST['vieworder']) && !empty($_POST['purchaseid'])) {
			
			$Purchase = new Purchase($_POST['purchaseid']);
			if ($Purchase->email == $_POST['email']) {
				$Shopp->Purchase = $Purchase;
				$Purchase->load_purchased();
				ob_start();
				include(SHOPP_TEMPLATES."/receipt.php");
				$content = ob_get_contents();
				ob_end_clean();
				return apply_filters('shopp_account_vieworder',$content);
			}
		}
		
		if (!empty($_GET['acct']) && !empty($_GET['id']) && $this->login) {
			$Purchase = new Purchase($_GET['id']);
			if ($Purchase->customer == $this->id) {
				$Shopp->Purchase = $Purchase;
				$Purchase->load_purchased();
				ob_start();
				include(SHOPP_TEMPLATES."/receipt.php");
				$content = ob_get_contents();
				ob_end_clean();
			} else {
				new ShoppError(sprintf(__('Order number %s could not be found in your order history.','Shopp'),esc_html($_GET['id'])),'customer_order_history',SHOPP_AUTH_ERR);
				unset($_GET['acct']);
				return false;
			}

		}
	}
	
	
	/**
	 * Password recovery processing
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.1
	 * 
	 * @return void
	 **/
	function recovery () {
		global $Shopp;
		$errors = array();
		
		// Check email or login supplied
		if (empty($_POST['account-login'])) {
			if ($this->accounts == "wordpress") $errors[] = new ShoppError(__('Enter an email address or login name','Shopp'));
			else $errors[] = new ShoppError(__('Enter an email address','Shopp'));
		} else {
			// Check that the account exists
			if (strpos($_POST['account-login'],'@') !== false) {
				$RecoveryCustomer = new Customer($_POST['account-login'],'email');
				if (!$RecoveryCustomer->id)
					$errors[] = new ShoppError(__('There is no user registered with that email address.','Shopp'),'password_recover_noaccount',SHOPP_AUTH_ERR);
			} else {
				$user_data = get_userdatabylogin($_POST['account-login']);
				$RecoveryCustomer = new Customer($user_data->ID,'wpuser');
				if (empty($RecoveryCustomer->id))
					$errors[] = new ShoppError(__('There is no user registered with that login name.','Shopp'),'password_recover_noaccount',SHOPP_AUTH_ERR);				
			}
		}
		
		// return errors
		if (!empty($errors)) return;

		// Generate new key
		$RecoveryCustomer->activation = wp_generate_password(20, false);
		do_action_ref_array('shopp_generate_password_key', array(&$RecoveryCustomer));
		$RecoveryCustomer->save();

		$subject = apply_filters('shopp_recover_password_subject', sprintf(__('[%s] Password Recovery Request','Shopp'),get_option('blogname')));
		
		$_ = array();
		$_[] = 'From: "'.get_option('blogname').'" <'.$Shopp->Settings->get('merchant_email').'>';
		$_[] = 'To: '.$RecoveryCustomer->email;
		$_[] = 'Subject: '.$subject;
		$_[] = '';
		$_[] = __('A request has been made to reset the password for the following site and account:','Shopp');
		$_[] = get_option('siteurl');
		$_[] = '';
		if (isset($_POST['email-login']))
			$_[] = sprintf(__('Email: %s','Shopp'), $RecoveryCustomer->email);
		if (isset($_POST['loginname-login']))
			$_[] = sprintf(__('Login name: %s','Shopp'), $user_data->user_login);
		$_[] = '';
		$_[] = __('To reset your password visit the following address, otherwise just ignore this email and nothing will happen.');
		$_[] = '';
		$_[] = add_query_arg(array('acct'=>'rp','key'=>$RecoveryCustomer->activation),shoppurl(false,'account'));
		$message = apply_filters('shopp_recover_password_message',$_);
		
		if (!shopp_email(join("\r\n",$message))) {
			new ShoppError(__('The e-mail could not be sent.'),'password_recovery_email',SHOPP_ERR);
			shopp_redirect(add_query_arg('acct','recover',shoppurl(false,'account')));
		} else {
			new ShoppError(__('Check your email address for instructions on resetting the password for your account.','Shopp'),'password_recovery_email',SHOPP_ERR);
		}

	}
	
	function reset_password ($activation) {
		if ($this->accounts == "none") return;
		
		$user_data = false;
		$activation = preg_replace('/[^a-z0-9]/i', '', $activation);

		$errors = array();
		if (empty($activation) || !is_string($activation))
			$errors[] = new ShoppError(__('Invalid key','Shopp'));
		
		$RecoveryCustomer = new Customer($activation,'activation');
		if (empty($RecoveryCustomer->id)) 
			$errors[] = new ShoppError(__('Invalid key','Shopp'));
		
		if (!empty($errors)) return false;

		// Generate a new random password
		$password = wp_generate_password();
		
		do_action_ref_array('password_reset', array(&$RecoveryCustomer,$password));
		
		$RecoveryCustomer->password = wp_hash_password($password);
		if ($this->accounts == "wordpress") {
			$user_data = get_userdata($RecoveryCustomer->wpuser);
			wp_set_password($password, $user_data->ID);
		}
		
		$RecoveryCustomer->activation = '';
		$RecoveryCustomer->save();
		
		$subject = apply_filters('shopp_reset_password_subject', sprintf(__('[%s] New Password','Shopp'),get_option('blogname')));
		
		$Settings =& ShoppSettings();
		$_ = array();
		$_[] = 'From: "'.get_option('blogname').'" <'.$Settings->get('merchant_email').'>';
		$_[] = 'To: '.$RecoveryCustomer->email;
		$_[] = 'Subject: '.$subject;
		$_[] = '';
		$_[] = sprintf(__('Your new password for %s:','Shopp'),get_option('siteurl'));
		$_[] = '';
		if ($user_data)
			$_[] = sprintf(__('Login name: %s','Shopp'), $user_data->user_login);
		$_[] = sprintf(__('Password: %s'), $password) . "\r\n";
		$_[] = '';
		$_[] = __('Click here to login:').' '.shoppurl(false,'account');
		$message = apply_filters('shopp_reset_password_message',$_);
		
		if (!shopp_email(join("\r\n",$message))) {
			new ShoppError(__('The e-mail could not be sent.'),'password_reset_email',SHOPP_ERR);
			shopp_redirect(add_query_arg('acct','recover',shoppurl(false,'account')));
		} else new ShoppError(__('Check your email address for your new password.','Shopp'),'password_reset_email',SHOPP_ERR);

		unset($_GET['acct']);
	}
	
	function notification () {
		global $Shopp;
		$Settings =& ShoppSettings();
		// The blogname option is escaped with esc_html on the way into the database in sanitize_option
		// we want to reverse this for the plain text arena of emails.
		$blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

		$_ = array();
		$_[] = 'From: "'.get_option('blogname').'" <'.$Settings->get('merchant_email').'>';
		$_[] = 'To: '.$Settings->get('merchant_email');
		$_[] = 'Subject: '.sprintf(__('[%s] New Customer Registration','Shopp'),$blogname);
		$_[] = '';
		$_[] = sprintf(__('New customer registration on your "%s" store:','Shopp'), $blogname);
		$_[] = sprintf(__('E-mail: %s','Shopp'), stripslashes($this->email));

		if (!shopp_email(join("\r\n",$_)))
			new ShoppError('The new account notification e-mail could not be sent.','new_account_email',SHOPP_ADMIN_ERR);
		elseif (SHOPP_DEBUG) new ShoppError('A new account notification e-mail was sent to the merchant.','new_account_email',SHOPP_DEBUG_ERR);
		if (empty($this->password)) return;

		$_ = array();
		$_[] = 'From: "'.get_option('blogname').'" <'.$Settings->get('merchant_email').'>';
		$_[] = 'To: '.$this->email;
		$_[] = 'Subject: '.sprintf(__('[%s] New Customer Registration','Shopp'),$blogname);
		$_[] = '';
		$_[] = sprintf(__('New customer registration on your "%s" store:','Shopp'), $blogname);
		$_[] = sprintf(__('E-mail: %s','Shopp'), stripslashes($this->email));
		$_[] = sprintf(__('Password: %s'), $this->password);
		$_[] = '';
		$_[] = shoppurl(false,'account',$Shopp->Gateways->secure);
		
		if (!shopp_email(join("\r\n",$_)))
			new ShoppError('The customer\'s account notification e-mail could not be sent.','new_account_email',SHOPP_ADMIN_ERR);
		elseif (SHOPP_DEBUG) new ShoppError('A new account notification e-mail was sent to the customer.','new_account_email',SHOPP_DEBUG_ERR);
	}
	
	function load_downloads () {
		if (empty($this->id)) return false;
		$db =& DB::get();
		$orders = DatabaseObject::tablename(Purchase::$table);
		$purchases = DatabaseObject::tablename(Purchased::$table);
		$asset = DatabaseObject::tablename(ProductDownload::$table);
		$query = "(SELECT p.dkey AS dkey,p.id,p.purchase,p.download as download,p.name AS name,p.optionlabel,p.downloads,o.total,o.created,f.id as download,f.name as filename,f.value AS filedata 
			FROM $purchases AS p 
			LEFT JOIN $orders AS o ON o.id=p.purchase 
			LEFT JOIN $asset AS f ON f.parent=p.price 
			WHERE o.customer=$this->id AND context='price' AND type='download')
			UNION
			(SELECT a.name AS dkey,p.id,p.purchase,a.value AS download,ao.name AS name,p.optionlabel,p.downloads,o.total,o.created,f.id as download,f.name as filename,f.value AS filedata 
			FROM wp_shopp_purchased AS p
			RIGHT JOIN wp_shopp_meta AS a ON a.parent=p.id AND a.type='download' AND a.context='purchased'
			LEFT JOIN wp_shopp_meta AS ao ON a.parent=p.id AND ao.type='addon' AND ao.context='purchased'
			LEFT JOIN wp_shopp_purchase AS o ON o.id=p.purchase
			LEFT JOIN wp_shopp_meta AS f on f.id=a.value 
			WHERE o.customer=9 AND f.context='price' AND f.type='download') ORDER BY created DESC";
		$this->downloads = $db->query($query,AS_ARRAY);
		foreach ($this->downloads as &$download) {
			$download->filedata = unserialize($download->filedata);
			foreach ($download->filedata as $property => $value) {
				$download->{$property} = $value;
			}
		}
	}

	function load_orders ($filters=array()) {
		if (empty($this->id)) return false;
		global $Shopp;
		$db =& DB::get();
		
		$where = '';
		if (isset($filters['where'])) $where = " AND {$filters['where']}";
		$orders = DatabaseObject::tablename(Purchase::$table);
		$purchases = DatabaseObject::tablename(Purchased::$table);
		$query = "SELECT o.* FROM $orders AS o WHERE o.customer=$this->id $where ORDER BY created DESC";
		$Shopp->purchases = $db->query($query,AS_ARRAY);
		foreach($Shopp->purchases as &$p) {
			$Purchase = new Purchase();
			$Purchase->updates($p);
			$p = $Purchase;
		}
	}
	
	function create_wpuser () {
		require_once(ABSPATH."/wp-includes/registration.php");
		if (empty($this->loginname)) return false;
		if (!validate_username($this->loginname)) {
			new ShoppError(__('This login name is invalid because it uses illegal characters. Please enter a valid login name.','Shopp'),'login_exists',SHOPP_ERR);
			return false;
		}
		if (username_exists($this->loginname)){
			new ShoppError(__('The login name is already registered. Please choose another login name.','Shopp'),'login_exists',SHOPP_ERR);
			return false;
		}
		if (empty($this->password)) $this->password = wp_generate_password(12,true);
		
		// Create the WordPress account
		$wpuser = wp_insert_user(array(
			'user_login' => $this->loginname,
			'user_pass' => $this->password,
			'user_email' => $this->email,
			'display_name' => $this->firstname.' '.$this->Customer->lastname,
			'nickname' => $handle,
			'first_name' => $this->firstname,
			'last_name' => $this->lastname
		));
		if (!$wpuser) return false;

		// Link the WP user ID to this customer record
		$this->wpuser = $wpuser;
		
		// Send email notification of the new account
		wp_new_user_notification( $wpuser, $this->password );
		$this->password = "";
		if (SHOPP_DEBUG) new ShoppError('Successfully created the WordPress user for the Shopp account.',false,SHOPP_DEBUG_ERR);
		
		$this->newuser = true;
		
		return true;
	}
	
	function taxrule ($rule) {
		switch ($rule['p']) {
			case "customer-type": return ($rule['v'] == $this->type); break;
		}
		return false;
	}
	
	function exportcolumns () {
		$prefix = "c.";
		return array(
			$prefix.'firstname' => __('Customer\'s First Name','Shopp'),
			$prefix.'lastname' => __('Customer\'s Last Name','Shopp'),
			$prefix.'email' => __('Customer\'s Email Address','Shopp'),
			$prefix.'phone' => __('Customer\'s Phone Number','Shopp'),
			$prefix.'company' => __('Customer\'s Company','Shopp'),
			$prefix.'marketing' => __('Customer\'s Marketing Preference','Shopp'),
			$prefix.'info' => __('Customer\'s Custom Information','Shopp'),
			$prefix.'created' => __('Customer Created Date','Shopp'),
			$prefix.'modified' => __('Customer Last Updated Date','Shopp'),
			);
	}
	
	function tag ($property,$options=array()) {
		global $Shopp;
		
		$Order =& $Shopp->Order;
		$checkout = false;
		if (isset($Shopp->Flow->Controller->checkout))
			$checkout = $Shopp->Flow->Controller->checkout;
		
		// Return strings with no options
		switch ($property) {
			case "url": 
				return shoppurl(array('acct'=>null),'account',$Shopp->Gateways->secure); break;
			case "action": 
				$action = null;
				if (isset($this->pages[$_GET['acct']])) $action = $_GET['acct'];
				return shoppurl(array('acct'=>$action),'account'); 
				break;

			case "accounturl": return shoppurl(false,'account'); break;
			case "recover-url": return add_query_arg('acct','recover',shoppurl(false,'account'));
			case "registration-form":
				$regions = Lookup::country_zones();
				add_storefrontjs("var regions = ".json_encode($regions).";",true);
				return $_SERVER['REQUEST_URI'];
				break;
			case "registration-errors":
				$Errors =& ShoppErrors();
				if (!$Errors->exist(SHOPP_ERR)) return false;
				ob_start();
				include(SHOPP_TEMPLATES.'/errors.php');
				$markup = ob_get_contents();
				ob_end_clean();
				return $markup;
				break;
			case "register":
				return '<input type="submit" name="shopp_registration" value="Register" />';
				break;
			case "process":
				if (!empty($_GET['acct']) && isset($this->pages[$_GET['acct']])) return $_GET['acct'];
				return false;

			case "loggedin": return $Shopp->Order->Customer->login; break;
			case "notloggedin": return (!$Shopp->Order->Customer->login && $Shopp->Settings->get('account_system') != "none"); break;
			case "login-label": 
				$accounts = $Shopp->Settings->get('account_system');
				$label = __('Email Address','Shopp');
				if ($accounts == "wordpress") $label = __('Login Name','Shopp');
				if (isset($options['label'])) $label = $options['label'];
				return $label;
				break;
			case "email-login": 
			case "loginname-login": 
			case "account-login": 
				$id = "account-login".($checkout?"-checkout":'');
				if (!empty($_POST['account-login']))
					$options['value'] = $_POST['account-login'];
				if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
				return '<input type="text" name="account-login" id="'.$id.'"'.inputattrs($options).' />';
				break;
			case "password-login": 
				if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
				$id = "password-login".($checkout?"-checkout":'');
				
				if (!empty($_POST['password-login']))
					$options['value'] = $_POST['password-login']; 
				return '<input type="password" name="password-login" id="'.$id.'"'.inputattrs($options).' />';
				break;
			case "recover-button":
				if (!isset($options['value'])) $options['value'] = __('Get New Password','Shopp');
 					return '<input type="submit" name="recover-login" id="recover-button"'.inputattrs($options).' />';
				break;
			case "submit-login": // Deprecating
			case "login-button":
				if (!isset($options['value'])) $options['value'] = __('Login','Shopp');
				$string = "";
				$id = "submit-login";
				
				$request = $_GET;
				if (isset($request['acct']) && $request['acct'] == "logout") unset($request['acct']);
				
				if ($checkout) {
					$id .= "-checkout";
					$string .= '<input type="hidden" name="process-login" id="process-login" value="false" />';
					$string .= '<input type="hidden" name="redirect" value="checkout" />';
				} else $string .= '<input type="hidden" name="process-login" value="true" /><input type="hidden" name="redirect" value="'.shoppurl($request,'account',$Order->security()).'" />';
				$string .= '<input type="submit" name="submit-login" id="'.$id.'"'.inputattrs($options).' />';
				return $string;
				break;
			case "profile-saved": 
				$saved = (isset($this->_saved) && $this->_saved);
				unset($this->_saved);
				return $saved;
			case "password-changed": 
				$change = (isset($this->_password_change) && $this->_password_change);
				unset($this->_password_change);
				return $change;
			case "errors-exist": return true;
				$Errors = &ShoppErrors();
				return ($Errors->exist(SHOPP_AUTH_ERR));
				break;
			case "login-errors": // @deprecated
			case "errors":
				if (!apply_filters('shopp_show_account_errors',true)) return false;
				$Errors = &ShoppErrors();
				if (!$Errors->exist(SHOPP_AUTH_ERR)) return false;

				ob_start();
				include(SHOPP_TEMPLATES."/errors.php");
				$errors = ob_get_contents();
				ob_end_clean();
				return $errors;
				break;
				
			case "menu":
				if (!isset($this->_menu_looping)) {
					reset($this->menus);
					$this->_menu_looping = true;
				} else next($this->menus);
				
				if (current($this->menus) !== false) return true;
				else {
					unset($this->_menu_looping);
					reset($this->menus);
					return false;
				}
				break;
			case "management":
				$page = current($this->menus);
				if (array_key_exists('url',$options)) return shoppurl(array('acct'=>$page->request),'account');
				if (array_key_exists('action',$options)) return $page->request;
				return $page->label;
			case "accounts": return $Shopp->Settings->get('account_system'); break;
			case "hasaccount": 
				$system = $Shopp->Settings->get('account_system');
				if ($system == "wordpress") return ($this->wpuser != 0);
				elseif ($system == "shopp") return (!empty($this->password));
				else return false;
			case "wpuser-created": return $this->newuser;
			case "order-lookup":
				$auth = $Shopp->Settings->get('account_system');
				if ($auth != "none") return true;
			
				if (!empty($_POST['vieworder']) && !empty($_POST['purchaseid'])) {
					require_once("Purchase.php");
					$Purchase = new Purchase($_POST['purchaseid']);
					if ($Purchase->email == $_POST['email']) {
						$Shopp->Purchase = $Purchase;
						$Purchase->load_purchased();
						ob_start();
						include(SHOPP_TEMPLATES."/receipt.php");
						$content = ob_get_contents();
						ob_end_clean();
						return apply_filters('shopp_order_lookup',$content);
					}
				}

				ob_start();
				include(SHOPP_ADMIN_PATH."/orders/account.php");
				$content = ob_get_contents();
				ob_end_clean();
				return apply_filters('shopp_order_lookup',$content);
				break;

			case "firstname": 
				if (isset($options['mode']) && $options['mode'] == "value") return $this->firstname;
				if (!empty($this->firstname))
					$options['value'] = $this->firstname; 
				return '<input type="text" name="firstname" id="firstname"'.inputattrs($options).' />';
				break;
			case "lastname":
				if (isset($options['mode']) && $options['mode'] == "value") return $this->lastname;
				if (!empty($this->lastname))
					$options['value'] = $this->lastname; 
				return '<input type="text" name="lastname" id="lastname"'.inputattrs($options).' />'; 
				break;
			case "company":
				if (isset($options['mode']) && $options['mode'] == "value") return $this->company;
				if (!empty($this->company))
					$options['value'] = $this->company; 
				return '<input type="text" name="company" id="company"'.inputattrs($options).' />'; 
				break;
			case "email":
				if (isset($options['mode']) && $options['mode'] == "value") return $this->email;
				if (!empty($this->email))
					$options['value'] = $this->email; 
				return '<input type="text" name="email" id="email"'.inputattrs($options).' />';
				break;
			case "loginname":
				if (isset($options['mode']) && $options['mode'] == "value") return $this->loginname;
				if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
				if (!empty($this->loginname))
					$options['value'] = $this->loginname; 
				return '<input type="text" name="loginname" id="login"'.inputattrs($options).' />';
				break;
			case "password":
				if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
				if (isset($options['mode']) && $options['mode'] == "value") 
					return strlen($this->password) == 34?str_pad('&bull;',8):$this->password;
				$options['value'] = "";
				return '<input type="password" name="password" id="password"'.inputattrs($options).' />';
				break;
			case "confirm-password":
				if (!isset($options['autocomplete'])) $options['autocomplete'] = "off";
				$options['value'] = ""; 
				return '<input type="password" name="confirm-password" id="confirm-password"'.inputattrs($options).' />';
				break;
			case "phone": 
				if (isset($options['mode']) && $options['mode'] == "value") return $this->phone;
				if (!empty($this->phone))
					$options['value'] = $this->phone; 
				return '<input type="text" name="phone" id="phone"'.inputattrs($options).' />'; 
				break;
			case "hasinfo":
			case "has-info":
				if (!is_object($this->info) || empty($this->info->meta)) return false;
				if (!isset($this->_info_looping)) {
					reset($this->info->meta);
					$this->_info_looping = true;
				} else next($this->info->meta);
				
				if (current($this->info->meta) !== false) return true;
				else {
					unset($this->_info_looping);
					reset($this->info->meta);
					return false;
				}
				break;
			case "info":
				$defaults = array(
					'mode' => 'input',
					'type' => 'text',
					'name' => false,
					'value' => false
				);
				$options = array_merge($defaults,$options);
				extract($options);
				
				if ($this->_info_looping)
					$info = current($this->info->meta);
				elseif ($name !== false && is_object($this->info->named[$name]))
					$info = $this->info->named[$name];

				switch ($mode) {
					case "name": return $info->name; break;
					case "value": return $info->value; break;
				}

				if (!$name && !empty($info->name)) $options['name'] = $info->name;
				elseif (!$name) return false;

				if (!$value && !empty($info->value)) $options['value'] = $info->value;
				
				$allowed_types = array("text","password","hidden","checkbox","radio");
				$type = in_array($type,$allowed_types)?$type:'hidden';
				return '<input type="'.$type.'" name="info['.$options['name'].']" id="customer-info-'.sanitize_title_with_dashes($options['name']).'"'.inputattrs($options).' />'; 
				break;

			// SHIPPING TAGS
			case "shipping": return $Order->Shipping;
			case "shipping-address": 
				if ($options['mode'] == "value") return $Order->Shipping->address;
				if (!empty($Order->Shipping->address))
					$options['value'] = $Order->Shipping->address; 
				return '<input type="text" name="shipping[address]" id="shipping-address" '.inputattrs($options).' />';
				break;
			case "shipping-xaddress":
				if ($options['mode'] == "value") return $Order->Shipping->xaddress;
				if (!empty($Order->Shipping->xaddress))
					$options['value'] = $Order->Shipping->xaddress; 
				return '<input type="text" name="shipping[xaddress]" id="shipping-xaddress" '.inputattrs($options).' />';
				break;
			case "shipping-city":
				if ($options['mode'] == "value") return $Order->Shipping->city;
				if (!empty($Order->Shipping->city))
					$options['value'] = $Order->Shipping->city; 
				return '<input type="text" name="shipping[city]" id="shipping-city" '.inputattrs($options).' />';
				break;
			case "shipping-province":
			case "shipping-state":
				if ($options['mode'] == "value") return $Order->Shipping->state;
				if (!isset($options['selected'])) $options['selected'] = false;
				if (!empty($Order->Shipping->state)) {
					$options['selected'] = $Order->Shipping->state;
					$options['value'] = $Order->Shipping->state;
				}
				$countries = Lookup::countries();
				$output = false;
				$country = $base['country'];
				if (!empty($Order->Shipping->country))
					$country = $Order->Shipping->country;
				if (!array_key_exists($country,$countries)) $country = key($countries);

				if (empty($options['type'])) $options['type'] = "menu";
				$regions = Lookup::country_zones();
				$states = $regions[$country];
				if (is_array($states) && $options['type'] == "menu") {
					$label = (!empty($options['label']))?$options['label']:'';
					$output = '<select name="shipping[state]" id="shipping-state" '.inputattrs($options,$select_attrs).'>';
					$output .= '<option value="" selected="selected">'.$label.'</option>';
				 	$output .= menuoptions($states,$options['selected'],true);
					$output .= '</select>';
				} else if ($options['type'] == "menu") {
					$options['disabled'] = 'disabled';
					$options['class'] = ($options['class']?" ":null).'unavailable'; 
					$label = (!empty($options['label']))?$options['label']:'';
					$output = '<select name="shipping[state]" id="shipping-state" '.inputattrs($options,$select_attrs).'></select>';				
				} else $output .= '<input type="text" name="shipping[state]" id="shipping-state" '.inputattrs($options).'/>';
				return $output;
				break;
			case "shipping-postcode":
				if ($options['mode'] == "value") return $Order->Shipping->postcode;
				if (!empty($Order->Shipping->postcode))
					$options['value'] = $Order->Shipping->postcode; 				
				return '<input type="text" name="shipping[postcode]" id="shipping-postcode" '.inputattrs($options).' />'; break;
			case "shipping-country": 
				if ($options['mode'] == "value") return $Order->Shipping->country;
				$base = $Shopp->Settings->get('base_operations');
				if (!empty($Order->Shipping->country))
					$options['selected'] = $Order->Shipping->country;
				else if (empty($options['selected'])) $options['selected'] = $base['country'];
				
				$countries = $Shopp->Settings->get('target_markets');
				
				$output = '<select name="shipping[country]" id="shipping-country" '.inputattrs($options,$select_attrs).'>';
			 	$output .= menuoptions($countries,$options['selected'],true);
				$output .= '</select>';
				return $output;
				break;
			case "same-shipping-address":
				$label = __("Same shipping address","Shopp");
				if (isset($options['label'])) $label = $options['label'];
				$checked = ' checked="checked"';
				if (isset($options['checked']) && !value_is_true($options['checked'])) $checked = '';
				$output = '<label for="same-shipping"><input type="checkbox" name="sameshipaddress" value="on" id="same-shipping" '.$checked.' /> '.$label.'</label>';
				return $output;
				break;
			case "residential-shipping-address":
				$label = __("Residential shipping address","Shopp");
				if (isset($options['label'])) $label = $options['label'];
				if (isset($options['checked']) && value_is_true($options['checked'])) $checked = ' checked="checked"';
				$output = '<label for="residential-shipping"><input type="hidden" name="shipping[residential]" value="no" /><input type="checkbox" name="shipping[residential]" value="yes" id="residential-shipping" '.$checked.' /> '.$label.'</label>';
				return $output;
				break;

			// BILLING TAGS
			case "billing-address":
				if ($options['mode'] == "value") return $Order->Billing->address;
				if (!empty($Order->Billing->address))
					$options['value'] = $Order->Billing->address;			
				return '<input type="text" name="billing[address]" id="billing-address" '.inputattrs($options).' />';
				break;
			case "billing-xaddress":
				if ($options['mode'] == "value") return $Order->Billing->xaddress;
				if (!empty($Order->Billing->xaddress))
					$options['value'] = $Order->Billing->xaddress;			
				return '<input type="text" name="billing[xaddress]" id="billing-xaddress" '.inputattrs($options).' />';
				break;
			case "billing-city":
				if ($options['mode'] == "value") return $Order->Billing->city;
				if (!empty($Order->Billing->city))
					$options['value'] = $Order->Billing->city;			
				return '<input type="text" name="billing[city]" id="billing-city" '.inputattrs($options).' />'; 
				break;
			case "billing-province": 
			case "billing-state": 
				if ($options['mode'] == "value") return $Order->Billing->state;
				if (!isset($options['selected'])) $options['selected'] = false;
				if (!empty($Order->Billing->state)) {
					$options['selected'] = $Order->Billing->state;
					$options['value'] = $Order->Billing->state;
				}
				if (empty($options['type'])) $options['type'] = "menu";
				$countries = Lookup::countries();

				$output = false;
				$country = $base['country'];
				if (!empty($Order->Billing->country))
					$country = $Order->Billing->country;
				if (!array_key_exists($country,$countries)) $country = key($countries);

				$regions = Lookup::country_zones();
				$states = $regions[$country];
				if (is_array($states) && $options['type'] == "menu") {
					$label = (!empty($options['label']))?$options['label']:'';
					$output = '<select name="billing[state]" id="billing-state" '.inputattrs($options,$select_attrs).'>';
					$output .= '<option value="" selected="selected">'.$label.'</option>';
				 	$output .= menuoptions($states,$options['selected'],true);
					$output .= '</select>';
				} else if ($options['type'] == "menu") {
					$options['disabled'] = 'disabled';
					$options['class'] = ($options['class']?" ":null).'unavailable';
					$label = (!empty($options['label']))?$options['label']:'';
					$output = '<select name="billing[state]" id="billing-state" '.inputattrs($options,$select_attrs).'></select>';					
				} else $output .= '<input type="text" name="billing[state]" id="billing-state" '.inputattrs($options).'/>';
				return $output;
				break;
			case "billing-postcode":
				if ($options['mode'] == "value") return $Order->Billing->postcode;
				if (!empty($Order->Billing->postcode))
					$options['value'] = $Order->Billing->postcode;			
				return '<input type="text" name="billing[postcode]" id="billing-postcode" '.inputattrs($options).' />';
				break;
			case "billing-country": 
				if ($options['mode'] == "value") return $Order->Billing->country;
				$base = $Shopp->Settings->get('base_operations');

				if (!empty($Order->Billing->country))
					$options['selected'] = $Order->Billing->country;
				else if (empty($options['selected'])) $options['selected'] = $base['country'];

				$countries = $Shopp->Settings->get('target_markets');
				
				$output = '<select name="billing[country]" id="billing-country" '.inputattrs($options,$select_attrs).'>';
			 	$output .= menuoptions($countries,$options['selected'],true);
				$output .= '</select>';
				return $output;
				break;
				
			case "save-button":
				if (!isset($options['label'])) $options['label'] = __('Save','Shopp');
				$result = '<input type="hidden" name="customer" value="true" />';
				$result .= '<input type="submit" name="save" id="save-button"'.inputattrs($options).' />'; 
				return $result;
				break;
			case "marketing": 
				if ($options['mode'] == "value") return $this->marketing;
				if (!empty($this->marketing) && value_is_true($this->marketing)) $options['checked'] = true;
				$attrs = array("accesskey","alt","checked","class","disabled","format",
					"minlength","maxlength","readonly","size","src","tabindex",
					"title");
				$input = '<input type="hidden" name="marketing" value="no" />';
				$input .= '<input type="checkbox" name="marketing" id="marketing" value="yes" '.inputattrs($options,$attrs).' />'; 
				return $input;
				break;
			
			
			// Downloads UI tags
			case "hasdownloads":
			case "has-downloads": return (!empty($this->downloads)); break;
			case "downloads":
				if (empty($this->downloads)) return false;
				if (!isset($this->_dowload_looping)) {
					reset($this->downloads);
					$this->_dowload_looping = true;
				} else next($this->downloads);
			
				if (current($this->downloads) !== false) return true;
				else {
					unset($this->_dowload_looping);
					reset($this->downloads);
					return false;
				}
				break;
			case "download":
				$download = current($this->downloads);
				$df = get_option('date_format');
				$properties = unserialize($download->properties);
				$string = '';
				if (array_key_exists('id',$options)) $string .= $download->download;
				if (array_key_exists('purchase',$options)) $string .= $download->purchase;
				if (array_key_exists('name',$options)) $string .= $download->name;
				if (array_key_exists('variation',$options)) $string .= $download->optionlabel;
				if (array_key_exists('downloads',$options)) $string .= $download->downloads;
				if (array_key_exists('key',$options)) $string .= $download->dkey;
				if (array_key_exists('created',$options)) $string .= $download->created;
				if (array_key_exists('total',$options)) $string .= money($download->total);
				if (array_key_exists('filetype',$options)) $string .= $properties['mimetype'];
				if (array_key_exists('size',$options)) $string .= readableFileSize($download->size);
				if (array_key_exists('date',$options)) $string .= _d($df,mktimestamp($download->created));
				if (array_key_exists('url',$options)) 
					$string .= SHOPP_PRETTYURLS?
						shoppurl("download/$download->dkey"):
						shoppurl(array('shopp_download'=>$download->dkey),'account');
				
				return $string;
				break;
				
			// Downloads UI tags
			case "haspurchases":
			case "has-purchases":
				$filters = array();
				if (isset($options['daysago'])) 
					$filters['where'] = "UNIX_TIMESTAMP(o.created) > UNIX_TIMESTAMP()-".($options['daysago']*86400);
				if (empty($Shopp->purchases)) $this->load_orders($filters);
				return (!empty($Shopp->purchases));
				break;
			case "purchases":
				if (!isset($this->_purchaseloop)) {
					reset($Shopp->purchases);
					$Shopp->Purchase = current($Shopp->purchases);
					$this->_purchaseloop = true;
				} else {
					$Shopp->Purchase = next($Shopp->purchases);
				}

				if (current($Shopp->purchases) !== false) return true;
				else {
					unset($this->_purchaseloop);
					return false;
				}
				break;
			case "receipt": // DEPRECATED
			case "order":
				return shoppurl(array('acct'=>'order','id'=>$Shopp->Purchase->id),'account');
				break;

		}
	}

} // end Customer class

class CustomersExport {
	var $sitename = "";
	var $headings = false;
	var $data = false;
	var $defined = array();
	var $customer_cols = array();
	var $billing_cols = array();
	var $shipping_cols = array();
	var $selected = array();
	var $recordstart = true;
	var $content_type = "text/plain";
	var $extension = "txt";
	var $set = 0;
	var $limit = 1024;
	
	function CustomersExport () {
		global $Shopp;
		
		$this->customer_cols = Customer::exportcolumns();
		$this->billing_cols = Billing::exportcolumns();
		$this->shipping_cols = Shipping::exportcolumns();
		$this->defined = array_merge($this->customer_cols,$this->billing_cols,$this->shipping_cols);
		
		$this->sitename = get_bloginfo('name');
		$this->headings = ($Shopp->Settings->get('customerexport_headers') == "on");
		$this->selected = $Shopp->Settings->get('customerexport_columns');
		$Shopp->Settings->save('customerexport_lastexport',mktime());
	}
	
	function query ($request=array()) {
		$db =& DB::get();
		if (empty($request)) $request = $_GET;
		
		if (!empty($request['start'])) {
			list($month,$day,$year) = explode("/",$request['start']);
			$starts = mktime(0,0,0,$month,$day,$year);
		}
		
		if (!empty($request['end'])) {
			list($month,$day,$year) = explode("/",$request['end']);
			$ends = mktime(0,0,0,$month,$day,$year);
		}
		
		$where = "WHERE c.id IS NOT NULL ";
		if (isset($request['s']) && !empty($request['s'])) $where .= " AND (id='{$request['s']}' OR firstname LIKE '%{$request['s']}%' OR lastname LIKE '%{$request['s']}%' OR CONCAT(firstname,' ',lastname) LIKE '%{$request['s']}%' OR transactionid LIKE '%{$request['s']}%')";
		if (!empty($request['start']) && !empty($request['end'])) $where .= " AND  (UNIX_TIMESTAMP(c.created) >= $starts AND UNIX_TIMESTAMP(c.created) <= $ends)";
		
		$customer_table = DatabaseObject::tablename(Customer::$table);
		$billing_table = DatabaseObject::tablename(Billing::$table);
		$shipping_table = DatabaseObject::tablename(Shipping::$table);
		$offset = $this->set*$this->limit;
		
		$c = 0; $columns = array();
		foreach ($this->selected as $column) $columns[] = "$column AS col".$c++;
		$query = "SELECT ".join(",",$columns)." FROM $customer_table AS c LEFT JOIN $billing_table AS b ON c.id=b.customer LEFT JOIN $shipping_table AS s ON c.id=s.customer $where ORDER BY c.created ASC LIMIT $offset,$this->limit";
		$this->data = $db->query($query,AS_ARRAY);
	}

	// Implement for exporting all the data
	function output () {
		if (!$this->data) $this->query();
		if (!$this->data) return false;
		header("Content-type: $this->content_type; charset=UTF-8");
		header("Content-Disposition: attachment; filename=\"$this->sitename Customer Export.$this->extension\"");
		header("Content-Description: Delivered by WordPress/Shopp ".SHOPP_VERSION);
		header("Cache-Control: maxage=1");
		header("Pragma: public");

		$this->begin();
		if ($this->headings) $this->heading();
		$this->records();
		$this->end();
	}
	
	function begin() {}
	
	function heading () {
		foreach ($this->selected as $name)
			$this->export($this->defined[$name]);
		$this->record();
	}
	
	function records () {
		while (!empty($this->data)) {
			foreach ($this->data as $key => $record) {
				foreach(get_object_vars($record) as $column)
					$this->export($this->parse($column));
				$this->record();
			}
			$this->set++;
			$this->query();
		}
	}
	
	function parse ($column) {
		if (preg_match("/^[sibNaO](?:\:.+?\{.*\}$|\:.+;$|;$)/",$column)) {
			$list = unserialize($column);
			$column = "";
			foreach ($list as $name => $value)
				$column .= (empty($column)?"":";")."$name:$value";
		}
		return $column;
	}

	function end() {}
	
	// Implement for exporting a single value
	function export ($value) {
		echo ($this->recordstart?"":"\t").$value;
		$this->recordstart = false;
	}
	
	function record () {
		echo "\n";
		$this->recordstart = true;
	}
	
}

class CustomersTabExport extends CustomersExport {
	function CustomersTabExport () {
		parent::CustomersExport();
		$this->output();
	}
}

class CustomersCSVExport extends CustomersExport {
	function CustomersCSVExport () {
		parent::CustomersExport();
		$this->content_type = "text/csv";
		$this->extension = "csv";
		$this->output();
	}
	
	function export ($value) {
		$value = str_replace('"','""',$value);
		if (preg_match('/^\s|[,"\n\r]|\s$/',$value)) $value = '"'.$value.'"';
		echo ($this->recordstart?"":",").$value;
		$this->recordstart = false;
	}
	
}

class CustomersXLSExport extends CustomersExport {
	function CustomersXLSExport () {
		parent::CustomersExport();
		$this->content_type = "application/vnd.ms-excel";
		$this->extension = "xls";
		$this->c = 0; $this->r = 0;
		$this->output();
	}
	
	function begin () {
		echo pack("ssssss", 0x809, 0x8, 0x0, 0x10, 0x0, 0x0);
	}
	
	function end () {
		echo pack("ss", 0x0A, 0x00);
	}
	
	function export ($value) {
		if (preg_match('/^[\d\.]+$/',$value)) {
		 	echo pack("sssss", 0x203, 14, $this->r, $this->c, 0x0);
			echo pack("d", $value);
		} else {
			$l = strlen($value);
			echo pack("ssssss", 0x204, 8+$l, $this->r, $this->c, 0x0, $l);
			echo $value;
		}
		$this->c++;
	}
	
	function record () {
		$this->c = 0;
		$this->r++;
	}
}

/**
 * CustomerAccountPage class
 *
 * A property container for Shopp's customer account page meta
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package customer
 **/
class CustomerAccountPage {
	var $request = "";
	var $label = "";
	var $handler = false;
	
	function __construct ($request,$label,$handler) {
		$this->request = $request;
		$this->label = $label;
		$this->handler = $handler;
	}
	
} // END class CustomerAccountPage 

?>