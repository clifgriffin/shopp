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

require("Address.php");

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

		$this->accounts = shopp_setting('account_system');
		$this->merchant = shopp_setting('merchant_email');

		$this->init(self::$table);
		$this->load($id,$key);
		if (!empty($this->id)) $this->load_info();

		$this->listeners();
	}

	function __wakeup () {
		$this->listeners();
	}

	function listeners () {
		add_action('parse_request',array($this,'menus'));
		add_action('shopp_account_management',array($this,'management'));
		add_action('shopp_logged_out', array($this, 'logout'));
	}

	/**
	 * simplify - get a simplified version of the customer object
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 * @return stdClass returns stdClass simplified version of the customer object
	 **/
	function simplify () {
		$map = array('id', 'wpuser', 'firstname', 'lastname', 'email', 'phone', 'company', 'marketing', 'type');
		$_ = new stdClass;

		foreach ( $map as $property ) {
			if ( isset($this->{$property}) ) $_->{$property} = $this->{$property};
		}

		if ( isset($this->info) && ! empty($this->info) ) $_->info = $this->info;
		return $_;
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
		$this->pages = array();
		$this->menus = array();
		$this->addpage('logout',__('Logout','Shopp'));
		$this->addpage('history',__('Order History','Shopp'),true,array(&$this,'load_orders'));
		$this->addpage('downloads',__('Downloads','Shopp'),true,array(&$this,'load_downloads'));
		$this->addpage('account',__('My Account','Shopp'));

		// Pages with not in menu navigation
		$this->addpage('order','Order',false,array(&$this,'order'));
		$this->addpage('recover','Password Recovery',false);

		$request = get_query_var('acct');
		if ('rp' == $request) $this->reset_password($_GET['key']);
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

	/**
	 * Determines if the customer is logged in, and checks for wordpress login if necessary
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 * @return bool true if logged in, false if not logged in
	 **/
	function logged_in () {
		if ( 'wordpress' == $this->accounts ) {
			$user = wp_get_current_user();
			return apply_filters('shopp_customer_login_check', $user->ID == $this->wpuser && $this->login );
		}

		return apply_filters('shopp_customer_login_check', $this->login);
	}

	function logout () {
		$this->login = false;
	}

	function order () {
		global $Shopp;

		if (!empty($_POST['vieworder']) && !empty($_POST['purchaseid'])) {

			$Purchase = new Purchase($_POST['purchaseid']);
			if ($Purchase->email == $_POST['email']) {
				$Shopp->Purchase = $Purchase;
				$Purchase->load_purchased();
				ob_start();
				locate_shopp_template(array('receipt.php'),true);
				$content = ob_get_contents();
				ob_end_clean();
				return apply_filters('shopp_account_vieworder',$content);
			}
		}

		if (!empty($_GET['acct']) && !empty($_GET['id']) && $this->logged_in()) {
			$Purchase = new Purchase($_GET['id']);
			if ($Purchase->customer == $this->id) {
				$Shopp->Purchase = $Purchase;
				$Purchase->load_purchased();
				ob_start();
				locate_shopp_template(array('receipt.php'),true);
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
		$_[] = 'From: "'.get_option('blogname').'" <'.shopp_setting('merchant_email').'>';
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

		if (!shopp_email(join("\n",$message))) {
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

		$_ = array();
		$_[] = 'From: "'.get_option('blogname').'" <'.shopp_setting('merchant_email').'>';
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

		if (!shopp_email(join("\n",$message))) {
			new ShoppError(__('The e-mail could not be sent.'),'password_reset_email',SHOPP_ERR);
			shopp_redirect(add_query_arg('acct','recover',shoppurl(false,'account')));
		} else new ShoppError(__('Check your email address for your new password.','Shopp'),'password_reset_email',SHOPP_ERR);

		unset($_GET['acct']);
	}

	function notification () {
		global $Shopp;
		// The blogname option is escaped with esc_html on the way into the database in sanitize_option
		// we want to reverse this for the plain text arena of emails.
		$blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

		$_ = array();
		$_[] = 'From: "'.get_option('blogname').'" <'.shopp_setting('merchant_email').'>';
		$_[] = 'To: '.shopp_setting('merchant_email');
		$_[] = 'Subject: '.sprintf(__('[%s] New Customer Registration','Shopp'),$blogname);
		$_[] = '';
		$_[] = sprintf(__('New customer registration on your "%s" store:','Shopp'), $blogname);
		$_[] = sprintf(__('E-mail: %s','Shopp'), stripslashes($this->email));

		if (!shopp_email(join("\n",$_)))
			new ShoppError('The new account notification e-mail could not be sent.','new_account_email',SHOPP_ADMIN_ERR);
		elseif (SHOPP_DEBUG) new ShoppError('A new account notification e-mail was sent to the merchant.','new_account_email',SHOPP_DEBUG_ERR);
		if (empty($this->password)) return;

		$_ = array();
		$_[] = 'From: "'.get_option('blogname').'" <'.shopp_setting('merchant_email').'>';
		$_[] = 'To: '.$this->email;
		$_[] = 'Subject: '.sprintf(__('[%s] New Customer Registration','Shopp'),$blogname);
		$_[] = '';
		$_[] = sprintf(__('New customer registration on your "%s" store:','Shopp'), $blogname);
		$_[] = sprintf(__('E-mail: %s','Shopp'), stripslashes($this->email));
		$_[] = sprintf(__('Password: %s'), $this->password);
		$_[] = '';
		$_[] = shoppurl(false,'account',$Shopp->Gateways->secure);

		if (!shopp_email(join("\n",$_)))
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
			FROM $purchases AS p
			RIGHT JOIN $asset AS a ON a.parent=p.id AND a.type='download' AND a.context='purchased'
			LEFT JOIN $asset AS ao ON a.parent=p.id AND ao.type='addon' AND ao.context='purchased'
			LEFT JOIN $orders AS o ON o.id=p.purchase
			LEFT JOIN $asset AS f on f.id=a.value
			WHERE o.customer=$this->id AND f.context='price' AND f.type='download') ORDER BY created DESC";
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

		$where = '';
		if (isset($filters['where'])) $where = " AND {$filters['where']}";
		$orders = DatabaseObject::tablename(Purchase::$table);
		$purchases = DatabaseObject::tablename(Purchased::$table);
		$query = "SELECT o.* FROM $orders AS o WHERE o.customer=$this->id $where ORDER BY created DESC";

		$PurchaseLoader = new Purchase();
		global $Shopp;
		$Shopp->purchases = DB::query($query,'array',array($PurchaseLoader,'loader'));
	}

	function create_wpuser () {
		require(ABSPATH.'/wp-includes/registration.php');
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

		if (apply_filters('shopp_notify_new_wpuser',true)) {
			// Send email notification of the new account
			wp_new_user_notification( $wpuser, $this->password );
		}

		$this->password = "";
		if (SHOPP_DEBUG) new ShoppError('Successfully created the WordPress user for the Shopp account.',false,SHOPP_DEBUG_ERR);

		$this->newuser = true;

		return true;
	}

	function wpuser_notify ($user,$password) {
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
			// $prefix.'info' => __('Customer\'s Custom Information','Shopp'), @todo Re-enable by switching to customer meta data in 1.2
			$prefix.'created' => __('Customer Created Date','Shopp'),
			$prefix.'modified' => __('Customer Last Updated Date','Shopp'),
			);
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
		$this->billing_cols = BillingAddress::exportcolumns();
		$this->shipping_cols = ShippingAddress::exportcolumns();
		$this->defined = array_merge($this->customer_cols,$this->billing_cols,$this->shipping_cols);

		$this->sitename = get_bloginfo('name');
		$this->headings = (shopp_setting('customerexport_headers') == "on");
		$this->selected = shopp_setting('customerexport_columns');
		shopp_set_setting('customerexport_lastexport',mktime());
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
		$billing_table = DatabaseObject::tablename(BillingAddress::$table);
		$shipping_table = DatabaseObject::tablename(ShippingAddress::$table);
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