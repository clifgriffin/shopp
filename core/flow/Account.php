<?php
/**
 * Account
 * 
 * Flow controller for the customer management interfaces
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, January  6, 2010
 * @package shopp
 * @subpackage shopp
 **/

class Account extends AdminController {
	
	/**
	 * Account constructor
	 *
	 * @return void
	 * @author Jonathan Davis
	 **/
	function __construct () {
		parent::__construct();
		if (!empty($_GET['id'])) {
			wp_enqueue_script('postbox');
			shopp_enqueue_script('colorbox');
			do_action('shopp_customer_editor_scripts');
			add_action('admin_head',array(&$this,'layout'));			
		} else add_action('admin_print_scripts',array(&$this,'columns'));
		do_action('shopp_customer_admin_scripts');
	}
	
	/**
	 * Parses admin requests to determine the interface to render
	 *
	 * @author Jonathan Davis
	 * @return void
	 **/
	function admin () {
		if (!empty($_GET['id'])) $this->editor();
		else $this->customers();
	}

	/**
	 * Interface processor for the customer list screen
	 *
	 * Handles processing customer list actions and displaying the 
	 * customer list screen
	 *
	 * @author Jonathan Davis
	 * @return void
	 **/
	function customers () {
		global $Shopp,$Customers,$wpdb;
		$db = DB::get();
		
		$defaults = array(
			'page' => false,
			'deleting' => false,
			'selected' => false,
			'update' => false,
			'newstatus' => false,
			'pagenum' => 1,
			'per_page' => false,
			'start' => '',
			'end' => '',
			'status' => false,
			's' => '',
			'range' => '',
			'startdate' => '',
			'enddate' => '',
		);

		$args = array_merge($defaults,$_GET);
		extract($args, EXTR_SKIP);
		
		if ($page == $this->Admin->pagename('customers')
				&& !empty($deleting) 
				&& !empty($selected) 
				&& is_array($selected)
				&& current_user_can('shopp_delete_customers')) {
			foreach($selected as $deletion) {
				$Customer = new Customer($deletion);
				$Billing = new Billing($Customer->id,'customer');
				$Billing->delete();
				$Shipping = new Shipping($Customer->id,'customer');
				$Shipping->delete();
				$Customer->delete();
			}
		}
		
		if (!empty($_POST['save'])) {
			check_admin_referer('shopp-save-customer');

			if ($_POST['id'] != "new") {
				$Customer = new Customer($_POST['id']);
				$Billing = new Billing($Customer->id,'customer');
				$Shipping = new Shipping($Customer->id,'customer');
			} else $Customer = new Customer();
			
			$Customer->updates($_POST);
			
			if (!empty($_POST['new-password']) && !empty($_POST['confirm-password'])
				&& $_POST['new-password'] == $_POST['confirm-password']) {
					$Customer->password = wp_hash_password($_POST['new-password']);
					if (!empty($Customer->wpuser)) wp_set_password($_POST['new-password'], $Customer->wpuser);
				}
			
			$Customer->info = false; // No longer used from DB
			$Customer->save();
			
			foreach ($_POST['info'] as $id => $info) {
				$Meta = new MetaObject($id);
				$Meta->value = $info;
				$Meta->save();
			}
			
			if (isset($Customer->id)) $Billing->customer = $Customer->id;
			$Billing->updates($_POST['billing']);
			$Billing->save();

			if (isset($Customer->id)) $Shipping->customer = $Customer->id;
			$Shipping->updates($_POST['shipping']);
			$Shipping->save();

		}

		$pagenum = absint( $pagenum );
		if ( empty($pagenum) )
			$pagenum = 1;
		if( !$per_page || $per_page < 0 )
			$per_page = 20;
		$index = ($per_page * ($pagenum-1)); 
		
		if (!empty($start)) {
			$startdate = $start;
			list($month,$day,$year) = explode("/",$startdate);
			$starts = mktime(0,0,0,$month,$day,$year);
		}
		if (!empty($end)) {
			$enddate = $end;
			list($month,$day,$year) = explode("/",$enddate);
			$ends = mktime(23,59,59,$month,$day,$year);
		}
		
		$customer_table = DatabaseObject::tablename(Customer::$table);
		$billing_table = DatabaseObject::tablename(Billing::$table);
		$purchase_table = DatabaseObject::tablename(Purchase::$table);
		$users_table = $wpdb->users;
		
		$where = '';
		if (!empty($s)) {
			$s = stripslashes($s);
			if (preg_match_all('/(\w+?)\:(?="(.+?)"|(.+?)\b)/',$s,$props,PREG_SET_ORDER)) {
				foreach ($props as $search) {
					$keyword = !empty($search[2])?$search[2]:$search[3];
					switch(strtolower($search[1])) {
						case "company": $where .= ((empty($where))?"WHERE ":" AND ")."c.company LIKE '%$keyword%'"; break;
						case "login": $where .= ((empty($where))?"WHERE ":" AND ")."u.user_login LIKE '%$keyword%'"; break;
						case "address": $where .= ((empty($where))?"WHERE ":" AND ")."(b.address LIKE '%$keyword%' OR b.xaddress='%$keyword%')"; break;
						case "city": $where .= ((empty($where))?"WHERE ":" AND ")."b.city LIKE '%$keyword%'"; break;
						case "province":
						case "state": $where .= ((empty($where))?"WHERE ":" AND ")."b.state='$keyword'"; break;
						case "zip":
						case "zipcode":
						case "postcode": $where .= ((empty($where))?"WHERE ":" AND ")."b.postcode='$keyword'"; break;
						case "country": $where .= ((empty($where))?"WHERE ":" AND ")."b.country='$keyword'"; break;
					}
				}
			} elseif (strpos($s,'@') !== false) {
				 $where .= ((empty($where))?"WHERE ":" AND ")."c.email='$s'";	
			} else $where .= ((empty($where))?"WHERE ":" AND ")." (c.id='$s' OR CONCAT(c.firstname,' ',c.lastname) LIKE '%$s%' OR c.company LIKE '%$s%')";

		}
		if (!empty($starts) && !empty($ends)) $where .= ((empty($where))?"WHERE ":" AND ").' (UNIX_TIMESTAMP(c.created) >= '.$starts.' AND UNIX_TIMESTAMP(c.created) <= '.$ends.')';

		$customercount = $db->query("SELECT count(*) as total FROM $customer_table AS c $where");
		$query = "SELECT c.*,b.city,b.state,b.country, u.user_login, SUM(p.total) AS total,count(distinct p.id) AS orders FROM $customer_table AS c LEFT JOIN $purchase_table AS p ON p.customer=c.id LEFT JOIN $billing_table AS b ON b.customer=c.id LEFT JOIN $users_table AS u ON u.ID=c.wpuser AND (c.wpuser IS NULL OR c.wpuser !=0) $where GROUP BY c.id ORDER BY c.created DESC LIMIT $index,$per_page";
		$Customers = $db->query($query,AS_ARRAY);

		$num_pages = ceil($customercount->total / $per_page);
		$page_links = paginate_links( array(
			'base' => add_query_arg( 'pagenum', '%#%' ),
			'format' => '',
			'total' => $num_pages,
			'current' => $pagenum
		));
		
		$ranges = array(
			'all' => __('Show New Customers','Shopp'),
			'today' => __('Today','Shopp'),
			'week' => __('This Week','Shopp'),
			'month' => __('This Month','Shopp'),
			'quarter' => __('This Quarter','Shopp'),
			'year' => __('This Year','Shopp'),
			'yesterday' => __('Yesterday','Shopp'),
			'lastweek' => __('Last Week','Shopp'),
			'last30' => __('Last 30 Days','Shopp'),
			'last90' => __('Last 3 Months','Shopp'),
			'lastmonth' => __('Last Month','Shopp'),
			'lastquarter' => __('Last Quarter','Shopp'),
			'lastyear' => __('Last Year','Shopp'),
			'lastexport' => __('Last Export','Shopp'),
			'custom' => __('Custom Dates','Shopp')
			);
		
		$exports = array(
			'tab' => __('Tab-separated.txt','Shopp'),
			'csv' => __('Comma-separated.csv','Shopp'),
			'xls' => __('Microsoft&reg; Excel.xls','Shopp')			
			);
		
		
		$formatPref = $Shopp->Settings->get('customerexport_format');
		if (!$formatPref) $formatPref = 'tab';
		
		$columns = array_merge(Customer::exportcolumns(),Billing::exportcolumns(),Shipping::exportcolumns());
		$selected = $Shopp->Settings->get('customerexport_columns');
		if (empty($selected)) $selected = array_keys($columns);
		
		$authentication = $Shopp->Settings->get('account_system');
		
		include(SHOPP_ADMIN_PATH."/customers/customers.php");
		
	}
	
	/**
	 * Registers the column headers for the customer list screen
	 *
	 * @author Jonathan Davis
	 * @return void
	 **/
	function columns () {
		shopp_enqueue_script('calendar');
		register_column_headers('shopp_page_shopp-customers', array(
			'cb'=>'<input type="checkbox" />',
			'name'=>__('Name','Shopp'),
			'login'=>__('Login','Shopp'),
			'email'=>__('Email','Shopp'),
			'location'=>__('Location','Shopp'),
			'orders'=>__('Orders','Shopp'),
			'joined'=>__('Joined','Shopp'))
		);
		
	}

	/**
	 * Builds the interface layout for the customer editor
	 *
	 * @author Jonathan Davis
	 * @return void Description...
	 **/
	function layout () {
		global $Shopp;
		$Admin =& $Shopp->Flow->Admin;
		include(SHOPP_ADMIN_PATH."/customers/ui.php");
	}
	
	/**
	 * Interface processor for the customer editor
	 *
	 * Handles rendering the interface, processing updated customer details 
	 * and handing saving them back to the database
	 *
	 * @author Jonathan Davis
	 * @return void
	 **/
	function editor () {
		global $Shopp,$Customer;
		$db =& DB::get();
		
		if ( !(is_shopp_userlevel() || current_user_can('shopp_customers')) )
			wp_die(__('You do not have sufficient permissions to access this page.'));


		if ($_GET['id'] != "new") {
			$Customer = new Customer($_GET['id']);
			$Customer->Billing = new Billing($Customer->id,'customer');
			$Customer->Shipping = new Shipping($Customer->id,'customer');
			if (empty($Customer->id)) 
				wp_die(__('The requested customer record does not exist.','Shopp'));
		} else $Customer = new Customer();

		if (empty($Customer->info->meta)) remove_meta_box('customer-info','shopp_page_shopp-customers','normal');
		
		$purchase_table = DatabaseObject::tablename(Purchase::$table);
		$r = $db->query("SELECT count(id) AS purchases,SUM(total) AS total FROM $purchase_table WHERE customer='$Customer->id' LIMIT 1");
		
		$Customer->orders = $r->purchases;
		$Customer->total = $r->total;
		
		
		$countries = array(''=>'&nbsp;');
		$countrydata = Lookup::countries();
		foreach ($countrydata as $iso => $c) {
			if (isset($_POST['settings']) && $_POST['settings']['base_operations']['country'] == $iso) 
				$base_region = $c['region'];
			$countries[$iso] = $c['name'];
		}
		$Customer->countries = $countries;

		$regions = Lookup::country_zones();
		$Customer->billing_states = array_merge(array(''=>'&nbsp;'),(array)$regions[$Customer->Billing->country]);
		$Customer->shipping_states = array_merge(array(''=>'&nbsp;'),(array)$regions[$Customer->Shipping->country]);

		include(SHOPP_ADMIN_PATH."/customers/editor.php");
	}
	

} // END class Account

?>