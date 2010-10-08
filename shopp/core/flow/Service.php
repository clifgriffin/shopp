<?php
/**
 * Service
 * 
 * Flow controller for order management interfaces
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, January 6, 2010
 * @package shopp
 * @subpackage shopp
 **/

/**
 * Service
 *
 * @package shopp
 * @since 1.1
 * @author Jonathan Davis
 **/
class Service extends AdminController {
	
	/**
	 * Service constructor
	 *
	 * @return void
	 * @author Jonathan Davis
	 **/
	function __construct () {
		parent::__construct();

		if (isset($_GET['id'])) {
			wp_enqueue_script('postbox');
			shopp_enqueue_script('colorbox');
			add_action('load-toplevel_page_shopp-orders',array(&$this,'workflow'));
			add_action('load-toplevel_page_shopp-orders',array(&$this,'layout'));
			do_action('shopp_order_management_scripts');
			
		} else add_action('admin_print_scripts',array(&$this,'columns'));
		do_action('shopp_order_admin_scripts');

	}
	
	/**
	 * admin
	 *
	 * @return void
	 * @author Jonathan Davis
	 **/
	function admin () {
		if (!empty($_GET['id'])) $this->manager();
		else $this->orders();
	}
	
	function workflow () {
		global $Shopp;
		if (preg_match("/\d+/",$_GET['id'])) {
			$Shopp->Purchase = new Purchase($_GET['id']);
			$Shopp->Purchase->load_purchased();
		} else $Shopp->Purchase = new Purchase();
	}

	/**
	 * Interface processor for the orders list interface
	 *
	 * @author Jonathan Davis
	 * 
	 * @return void
	 **/
	function orders () {
		global $Shopp,$Orders;
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
		
		if ( !(is_shopp_userlevel() || current_user_can('shopp_orders')) )
			wp_die(__('You do not have sufficient permissions to access this page.','Shopp'));

		if ($page == "shopp-orders"
						&& !empty($deleting)
						&& !empty($selected) 
						&& is_array($selected)
						&& current_user_can('shopp_delete_orders')) {
			foreach($selected as $selection) {
				$Purchase = new Purchase($selection);
				$Purchase->load_purchased();
				foreach ($Purchase->purchased as $purchased) {
					$Purchased = new Purchased($purchased->id);
					$Purchased->delete();
				}
				$Purchase->delete();
			}
		}

		$statusLabels = $this->Settings->get('order_status');
		if (empty($statusLabels)) $statusLabels = array('');
		$txnStatusLabels = array(
			'PENDING' => __('Pending','Shopp'),
			'CHARGED' => __('Charged','Shopp'),
			'REFUNDED' => __('Refunded','Shopp'),
			'VOID' => __('Void','Shopp')
			);

		if ($update == "order"
						&& !empty($selected) 
						&& is_array($selected)) {
			foreach($selected as $selection) {
				$Purchase = new Purchase($selection);
				$Purchase->status = $newstatus;
				$Purchase->save();
			}
		}

		$Purchase = new Purchase();
		
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

		$pagenum = absint( $pagenum );
		if ( empty($pagenum) )
			$pagenum = 1;
		if( !$per_page || $per_page < 0 )
			$per_page = 20;
		$start = ($per_page * ($pagenum-1)); 
		
		$where = '';
		if (!empty($status) || $status === '0') $where = "WHERE status='$status'";
		
		if (!empty($s)) {
			$s = stripslashes($s);
			if (preg_match_all('/(\w+?)\:(?="(.+?)"|(.+?)\b)/',$s,$props,PREG_SET_ORDER) > 0) {
				foreach ($props as $search) {
					$keyword = !empty($search[2])?$search[2]:$search[3];
					switch(strtolower($search[1])) {
						case "txn": $where .= (empty($where)?"WHERE ":" AND ")."txnid='$keyword'"; break;
						case "gateway": $where .= (empty($where)?"WHERE ":" AND ")."gateway LIKE '%$keyword%'"; break;
						case "cardtype": $where .= ((empty($where))?"WHERE ":" AND ")."cardtype LIKE '%$keyword%'"; break;
						case "address": $where .= ((empty($where))?"WHERE ":" AND ")."(address LIKE '%$keyword%' OR xaddress='%$keyword%')"; break;
						case "city": $where .= ((empty($where))?"WHERE ":" AND ")."city LIKE '%$keyword%'"; break;
						case "province":
						case "state": $where .= ((empty($where))?"WHERE ":" AND ")."state='$keyword'"; break;
						case "zip":
						case "zipcode":
						case "postcode": $where .= ((empty($where))?"WHERE ":" AND ")."postcode='$keyword'"; break;
						case "country": $where .= ((empty($where))?"WHERE ":" AND ")."country='$keyword'"; break;
					}
				}
				if (empty($where)) $where .= ((empty($where))?"WHERE ":" AND ")." (id='$s' OR CONCAT(firstname,' ',lastname) LIKE '%$s%')";	
			} elseif (strpos($s,'@') !== false) {
				 $where .= ((empty($where))?"WHERE ":" AND ")." email='$s'";	
			} else $where .= ((empty($where))?"WHERE ":" AND ")." (id='$s' OR CONCAT(firstname,' ',lastname) LIKE '%$s%')";	
		}
		if (!empty($starts) && !empty($ends)) $where .= ((empty($where))?"WHERE ":" AND ").' (UNIX_TIMESTAMP(created) >= '.$starts.' AND UNIX_TIMESTAMP(created) <= '.$ends.')';
		if (!empty($customer)) $where .= ((empty($where))?"WHERE ":" AND ")."customer=$customer";
		$ordercount = $db->query("SELECT count(*) as total,SUM(total) AS sales,AVG(total) AS avgsale FROM $Purchase->_table $where ORDER BY created DESC");
		$query = "SELECT * FROM $Purchase->_table $where ORDER BY created DESC LIMIT $start,$per_page";
		$Orders = $db->query($query,AS_ARRAY);

		$num_pages = ceil($ordercount->total / $per_page);
		$page_links = paginate_links( array(
			'base' => add_query_arg( 'pagenum', '%#%' ),
			'format' => '',
			'total' => $num_pages,
			'current' => $pagenum
		));
		
		$ranges = array(
			'all' => __('Show All Orders','Shopp'),
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
			'xls' => __('Microsoft&reg; Excel.xls','Shopp'),
			'iif' => __('Intuit&reg; QuickBooks.iif','Shopp')
			);
		
		$formatPref = $Shopp->Settings->get('purchaselog_format');
		if (!$formatPref) $formatPref = 'tab';
		
		$columns = array_merge(Purchase::exportcolumns(),Purchased::exportcolumns());
		$selected = $Shopp->Settings->get('purchaselog_columns');
		if (empty($selected)) $selected = array_keys($columns);
		
		include(SHOPP_ADMIN_PATH."/orders/orders.php");
	}      
	
	/**
	 * Registers the column headers for the orders list interface
	 *
	 * Uses the WordPress 2.7 function register_column_headers to provide
	 * customizable columns that can be toggled to show or hide
	 *
	 * @author Jonathan Davis
	 * @return void
	 **/
	function columns () {
		shopp_enqueue_script('calendar');
		register_column_headers('toplevel_page_shopp-orders', array(
			'cb'=>'<input type="checkbox" />',
			'order'=>__('Order','Shopp'),
			'name'=>__('Name','Shopp'),
			'destination'=>__('Destination','Shopp'),
			'txn'=>__('Transaction','Shopp'),
			'date'=>__('Date','Shopp'),
			'total'=>__('Total','Shopp'))
		);
	}
	
	/**
	 * Provides overall layout for the order manager interface
	 *
	 * Makes use of WordPress postboxes to generate panels (box) content
	 * containers that are customizable with drag & drop, collapsable, and
	 * can be toggled to be hidden or visible in the interface.
	 *
	 * @author Jonathan Davis
	 * @return
	 **/
	function layout () {
		global $Shopp;
		$Admin =& $Shopp->Flow->Admin;
		include(SHOPP_ADMIN_PATH."/orders/ui.php");
	}
	
	/**
	 * Interface processor for the order manager
	 *
	 * @author Jonathan Davis
	 * @return void
	 **/
	function manager () {
		global $Shopp,$UI,$Notes;
		global $is_IIS;

		if ( !(is_shopp_userlevel() || current_user_can('shopp_orders')) )
			wp_die(__('You do not have sufficient permissions to access this page.','Shopp'));

		$Purchase = $Shopp->Purchase;
		$Purchase->Customer = new Customer($Purchase->customer);

		// Handle Order note processing
		if (!empty($_POST['note'])) {
			$user = wp_get_current_user();
			$Note = new MetaObject();
			$Note->parent = $Purchase->id;
			$Note->context = 'purchase';
			$Note->type = 'order_note';
			$Note->name = 'note';
			$Note->value = new stdClass();
			$Note->value->author = $user->ID;
			$Note->value->message = $_POST['note'];
			$Note->save();
		}
		if (!empty($_POST['delete-note'])) {
			$noteid = key($_POST['delete-note']);
			$Note = new MetaObject($noteid);
			$Note->delete();
		}
		if (!empty($_POST['edit-note'])) {
			$noteid = key($_POST['note-editor']);
			$Note = new MetaObject($noteid);
			$Note->value->message = $_POST['note-editor'][$noteid];
			$Note->save();
		}
		$Notes = new ObjectMeta($Purchase->id,'purchase','order_note');
		
		if (!empty($_POST['update'])) {
			check_admin_referer('shopp-save-order');
			
			if ($_POST['txnstatus'] != $Purchase->txnstatus)
				do_action_ref_array('shopp_order_txnstatus_update',array(&$_POST['txnstatus'],&$Purchase));
				
			
			$Purchase->updates($_POST);
			
			$mailstatus = false;
			if ($_POST['notify'] == "yes") {
				$labels = $this->Settings->get('order_status');
				// Save a reference to this purchase in Shopp 
				// so the Template API works when generating the receipt
				$Shopp->Purchase =& $Purchase;
				
				// Send the e-mail notification
				$addressee = "$Purchase->firstname $Purchase->lastname";
				$address = "$Purchase->email";
				
				$email = array();
				$email['from'] = '"'.get_bloginfo("name").'"';
				if ($Shopp->Settings->get('merchant_email')) 
					$email['from'] .= ' <'.$Shopp->Settings->get('merchant_email').'>';
				if($is_IIS) $email['to'] = $address;
				else $email['to'] = '"'.html_entity_decode($addressee,ENT_QUOTES).'" <'.$address.'>';
				$email['subject'] = __('Order Updated','Shopp');
				$email['url'] = get_bloginfo('siteurl');
				$email['sitename'] = get_bloginfo('name');

				if ($_POST['receipt'] == "yes")
					$email['receipt'] = $Purchase->receipt();

				$email['status'] = strtoupper($labels[$Purchase->status]);
				$email['message'] = wpautop(stripslashes($_POST['message']));

				if (file_exists(SHOPP_TEMPLATES."/notification.html")) $template = SHOPP_TEMPLATES."/notification.html";
				if (file_exists(SHOPP_TEMPLATES."/notify.php")) $template = SHOPP_TEMPLATES."/notify.php";

				if (shopp_email($template,$email)) $mailsent = true;
				
			}
			
			$Purchase->save();
			if ($mailsent) $updated = __('Order status updated & notification email sent.','Shopp');
			else $updated = __('Order status updated.','Shopp');
		}	
		
		$targets = $this->Settings->get('target_markets');
		$UI->txnStatusLabels = Lookup::payment_status_labels();
		$UI->statusLabels = $this->Settings->get('order_status');
		if (empty($statusLabels)) $statusLabels = array('');
						
		include(SHOPP_ADMIN_PATH."/orders/order.php");
	}
	
	/**
	 * Retrieves the number of orders in each customized order status label
	 *
	 * @author Jonathan Davis
	 * @return void
	 **/
	function status_counts () {
		$db = DB::get();
		
		$table = DatabaseObject::tablename(Purchase::$table);
		$labels = $this->Settings->get('order_status');

		if (empty($labels)) return false;
		$status = array();

		$r = $db->query("SELECT status,COUNT(status) AS total FROM $table GROUP BY status ORDER BY status ASC",AS_ARRAY);
		foreach ($labels as $id => $label) {
			$_ = new StdClass();
			$_->label = $label;
			$_->id = $id;
			$_->total = isset($r[$id])?(int)$r[$id]->total:0;
			$status[$id] = $_;
		}
		
		return $status;
	}
	
} // END class Service

?>