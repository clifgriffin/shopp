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
			shopp_enqueue_script('jquery-tmpl');
			shopp_enqueue_script('orders');
			shopp_localize_script( 'orders', '$om', array(
				'co' => __('Cancel Order','Shopp'),
				'mr' => __('Mark Refunded','Shopp'),
				'pr' => __('Process Refund','Shopp'),
				'dnc' => __('Do Not Cancel','Shopp'),
				'ro' => __('Refund Order','Shopp'),
				'cancel' => __('Cancel','Shopp'),
				'rr' => __('Reason for refund','Shopp'),
				'rc' => __('Reason for cancellation','Shopp'),
				'mc' => __('Mark Cancelled','Shopp')
			));

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
		if (preg_match("/\d+/",$_GET['id'])) {
			ShoppPurchase( new Purchase($_GET['id']) );
			ShoppPurchase()->load_purchased();
			ShoppPurchase()->load_events();
		} else ShoppPurchase( new Purchase() );
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
			'paged' => 1,
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

		if ( ! current_user_can('shopp_orders') )
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

		$statusLabels = shopp_setting('order_status');
		if (empty($statusLabels)) $statusLabels = array('');
		$txnstatus_labels = Lookup::txnstatus_labels();

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

		$pagenum = absint( $paged );
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
		$ordercount = DB::query("SELECT count(*) as total,SUM(total) AS sales,AVG(total) AS avgsale FROM $Purchase->_table $where ORDER BY created DESC LIMIT 1");
		$query = "SELECT * FROM $Purchase->_table $where ORDER BY created DESC LIMIT $start,$per_page";
		$Orders = DB::query($query,'array');

		$num_pages = ceil($ordercount->total / $per_page);
		$ListTable = ShoppUI::table_set_pagination ($this->screen, $ordercount->total, $num_pages, $per_page );

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

		$formatPref = shopp_setting('purchaselog_format');
		if (!$formatPref) $formatPref = 'tab';

		$columns = array_merge(Purchase::exportcolumns(),Purchased::exportcolumns());
		$selected = shopp_setting('purchaselog_columns');
		if (empty($selected)) $selected = array_keys($columns);

		$Gateways = array_merge($Shopp->Gateways->modules,array('FreeOrder' => $Shopp->Gateways->freeorder));


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
		include(SHOPP_ADMIN_PATH."/orders/events.php");
		include(SHOPP_ADMIN_PATH."/orders/ui.php");
		do_action('shopp_order_manager_layout');
	}

	/**
	 * Interface processor for the order manager
	 *
	 * @author Jonathan Davis
	 * @return void
	 **/
	function manager () {
		global $Shopp,$Notes;
		global $is_IIS;

		if ( ! current_user_can('shopp_orders') )
			wp_die(__('You do not have sufficient permissions to access this page.','Shopp'));

		$Purchase = ShoppPurchase();
		$Purchase->Customer = new Customer($Purchase->customer);
		$Gateway = $Purchase->gateway();

		if (!empty($_POST["send-note"])){
			$user = wp_get_current_user();
			shopp_add_order_event($Purchase->id,'notice',array(
				'note' => $_POST['note'],
				'user' => $user->ID
			));

			$Purchase->load_events();
		}

		// Handle Order note processing
		if (!empty($_POST['note']))
			$this->addnote($Purchase->id, stripslashes($_POST['note']), !empty($_POST['send-note']));

		if (!empty($_POST['delete-note'])) {
			$noteid = key($_POST['delete-note']);
			$Note = new MetaObject(array('id' => $noteid,'type'=>'order_note'));
			$Note->delete();
		}

		if (!empty($_POST['edit-note'])) {
			$noteid = key($_POST['note-editor']);
			$Note = new MetaObject($noteid);
			$Note->value->message = stripslashes($_POST['note-editor'][$noteid]);
			$Note->save();
		}
		$Notes = new ObjectMeta($Purchase->id,'purchase','order_note');

		if (isset($_POST['submit-shipments']) && isset($_POST['shipment']) && !empty($_POST['shipment'])) {
			$shipments = $_POST['shipment'];
			foreach ((array)$shipments as $shipment) {
				shopp_add_order_event($Purchase->id,'shipped',array(
					'tracking' => $shipment['tracking'],
					'carrier' => $shipment['carrier']
				));
			}
			$updated = __('Shipping notice sent.','Shopp');

			unset($_POST['ship-notice']);
			$Purchase->load_events();
		}

		if (isset($_POST['order-action']) && 'refund' == $_POST['order-action']) {
			$user = wp_get_current_user();
			$reason = (int)$_POST['reason'];
			$amount = floatvalue($_POST['amount']);

			if (str_true($_POST['mark'])) { // Force the order status
				shopp_add_order_event($Purchase->id,'notice',array(
					'user' => $user->ID,
					'note' => __('Marked Refunded','Shopp')
				));
				shopp_add_order_event($Purchase->id,'refunded',array(
					'txnid' => $Purchase->txnid,
					'gateway' => $Gateway->module,
					'amount' => $amount,
				));
			} else {
				shopp_add_order_event($Purchase->id,'refund',array(
					'txnid' => $Purchase->txnid,
					'gateway' => $Gateway->module,
					'amount' => $amount,
					'reason' => $reason,
					'user' => $user->ID
				));
			}


			if (!empty($_POST['message']))
				$this->addnote($Purchase->id,$_POST['message']);

			$Purchase->load_events();
		}

		if (isset($_POST['order-action']) && 'cancel' == $_POST['order-action']) {
			// unset($_POST['refund-order']);
			$user = wp_get_current_user();
			$reason = (int)$_POST['reason'];


			if (!empty($_POST['message']))
				$message = $_POST['message'];
			else
				$message = 0;


			if (str_true($_POST['mark'])) { // Force the order status
				shopp_add_order_event($Purchase->id,'notice',array(
					'user' => $user->ID,
					'note' => __('Marked Cancelled','Shopp')
				));
				shopp_add_order_event($Purchase->id,'voided',array(
					'txnorigin' => $Purchase->txnid,	// Original transaction ID (txnid of original Purchase record)
					'txnid' => time(),			// Transaction ID for the VOID event
					'gateway' => $Gateway->module		// Gateway handler name (module name from @subpackage)
				));
			} else {
				shopp_add_order_event($Purchase->id,'void',array(
					'txnid' => $Purchase->txnid,
					'gateway' => $Gateway->module,
					'reason' => $reason,
					'user' => $user->ID,
					'note' => $message
				));
			}

			if (!empty($_POST['message']))
				$this->addnote($Purchase->id,$_POST['message']);

			$Purchase->load_events();
		}

		if (isset($_POST['charge']) && $Gateway && $Gateway->captures) {
			$user = wp_get_current_user();

			shopp_add_order_event($Purchase->id,'capture',array(
				'txnid' => $Purchase->txnid,
				'gateway' => $Purchase->gateway,
				'amount' => $Purchase->capturable(),
				'user' => $user->ID
			));

			$Purchase->load_events();
		}

		$base = shopp_setting('base_operations');
		$targets = shopp_setting('target_markets');

		$carriers_menu = $carriers_json = array();
		$shipping_carriers = shopp_setting('shipping_carriers');
		$shipcarriers = Lookup::shipcarriers();

		if (empty($shipping_carriers)) {
			$serviceareas = array('*',$base['country']);
			foreach ($shipcarriers as $code => $carrier) {
				if (!in_array($carrier->areas,$serviceareas)) continue;
				$carriers_menu[$code] = $carrier->name;
				$carriers_json[$code] = array($carrier->name,$carrier->trackpattern);
			}
		} else {
			foreach ($shipping_carriers as $code) {
				$carriers_menu[$code] = $shipcarriers[$code]->name;
				$carriers_json[$code] = array($shipcarriers[$code]->name,$shipcarriers[$code]->trackpattern);
			}
		}
		unset($carrierdata);

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
		$table = DatabaseObject::tablename(Purchase::$table);
		$labels = shopp_setting('order_status');

		if (empty($labels)) return false;
		$status = array();

		$alltotal = DB::query("SELECT count(*) AS total FROM $table",'auto','col','total');
		$r = DB::query("SELECT status,COUNT(status) AS total FROM $table GROUP BY status ORDER BY status ASC",'array','index','status');

		$labels = array_merge(array( '' => __('All Orders','Shopp') ),$labels);
		foreach ($labels as $id => $label) {
			$_ = new StdClass();
			$_->label = $label;
			$_->id = $id;
			$_->total = 0;
			if ( isset($r[ $id ]) ) $_->total = (int)$r[$id]->total;
			if ('' === $id) $_->total = $alltotal;
			$status[$id] = $_;
		}

		return $status;
	}

	function addnote ($order, $message, $sent = false) {
		$user = wp_get_current_user();
		$Note = new MetaObject();
		$Note->parent = $order;
		$Note->context = 'purchase';
		$Note->type = 'order_note';
		$Note->name = 'note';
		$Note->value = new stdClass();
		$Note->value->author = $user->ID;
		$Note->value->message = $message;
		$Note->value->sent = $sent;
		$Note->save();
	}

} // END class Service

?>