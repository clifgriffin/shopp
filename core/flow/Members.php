<?php
/**
 * Members.php
 *
 * Memberships admin and access controller
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, March 28, 2011
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @since 1.0
 * @subpackage shopp
 **/

require(SHOPP_MODEL_PATH.'/Membership.php');
/**
 * Members
 *
 * @author Jonathan Davis
 * @since 1.2
 * @package shopp
 **/
class Members extends AdminController {

	/**
	 * Members constructor
	 *
	 * @return void
	 * @author Jonathan Davis
	 **/
	function __construct () {
		parent::__construct();
		if (!empty($_GET['id'])) {
			wp_enqueue_script('postbox');
			wp_enqueue_script('jquery-ui-draggable');
			shopp_enqueue_script('jquery-tmpl');
			shopp_enqueue_script('suggest');
			shopp_enqueue_script('search-select');
			shopp_enqueue_script('membership-editor');
			shopp_enqueue_script('colorbox');
			do_action('shopp_membership_editor_scripts');
			add_action('admin_head',array(&$this,'layout'));
		} else add_action('admin_print_scripts',array(&$this,'columns'));
		do_action('shopp_membership_admin_scripts');
	}

	/**
	 * Parses admin requests to determine the interface to render
	 *
	 * @author Jonathan Davis
	 * @return void
	 **/
	function admin () {
		if (!empty($_GET['id'])) $this->editor();
		else $this->memeberships();
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
	function memeberships () {
		global $Shopp;
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

		if ($page == $this->Admin->pagename('memberships')
				&& !empty($deleting)
				&& !empty($selected)
				&& is_array($selected)
				&& current_user_can('shopp_delete_memberships')) {
			foreach($selected as $deletion) {
				$Membership = new Membership($deletion);
				$Membership->delete();
			}
		}

		if (!empty($_POST['save'])) {
			check_admin_referer('shopp-save-membership');

			if ($_POST['id'] != "new") {
				$Membership = new Membership($_POST['id']);
			} else $Membership = new Membership();

			$Membership->updates($_POST);
			$Membership->save();

			// foreach ($_POST['access'] as $type => $items) {
			// 	$AccessRule = new MembershipAccess($Membership->id,$type,'allow');
			// 	$AccessRule->save();
			//
			// 	// Determine the catalog entries for access taxonomy
			// 	// foreach ($access as $id => $name) {
			// 	//
			// 	// }
			// }

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

		$membership_table = DatabaseObject::tablename(Membership::$table);
		$Membership = new Membership();

		$where = '';
		// if (!empty($s)) {
		// 	$s = stripslashes($s);
		// 	if (preg_match_all('/(\w+?)\:(?="(.+?)"|(.+?)\b)/',$s,$props,PREG_SET_ORDER)) {
		// 		foreach ($props as $search) {
		// 			$keyword = !empty($search[2])?$search[2]:$search[3];
		// 			switch(strtolower($search[1])) {
		// 				case "company": $where .= ((empty($where))?"WHERE ":" AND ")."c.company LIKE '%$keyword%'"; break;
		// 				case "login": $where .= ((empty($where))?"WHERE ":" AND ")."u.user_login LIKE '%$keyword%'"; break;
		// 				case "address": $where .= ((empty($where))?"WHERE ":" AND ")."(b.address LIKE '%$keyword%' OR b.xaddress='%$keyword%')"; break;
		// 				case "city": $where .= ((empty($where))?"WHERE ":" AND ")."b.city LIKE '%$keyword%'"; break;
		// 				case "province":
		// 				case "state": $where .= ((empty($where))?"WHERE ":" AND ")."b.state='$keyword'"; break;
		// 				case "zip":
		// 				case "zipcode":
		// 				case "postcode": $where .= ((empty($where))?"WHERE ":" AND ")."b.postcode='$keyword'"; break;
		// 				case "country": $where .= ((empty($where))?"WHERE ":" AND ")."b.country='$keyword'"; break;
		// 			}
		// 		}
		// 	} elseif (strpos($s,'@') !== false) {
		// 		 $where .= ((empty($where))?"WHERE ":" AND ")."c.email='$s'";
		// 	} else $where .= ((empty($where))?"WHERE ":" AND ")." (c.id='$s' OR CONCAT(c.firstname,' ',c.lastname) LIKE '%$s%' OR c.company LIKE '%$s%')";
		//
		// }
		// if (!empty($starts) && !empty($ends)) $where .= ((empty($where))?"WHERE ":" AND ").' (UNIX_TIMESTAMP(c.created) >= '.$starts.' AND UNIX_TIMESTAMP(c.created) <= '.$ends.')';

		$count = $db->query("SELECT count(*) as total FROM $customer_table AS c $where");
		$query = "SELECT *
					FROM $Membership->_table
					WHERE parent='$Membership->parent'
						AND context='$Membership->context'
						AND type='$Membership->type'
					LIMIT $index,$per_page";

		$Memberships = $db->query($query,AS_ARRAY);

		$num_pages = ceil($count->total / $per_page);
		$page_links = paginate_links( array(
			'base' => add_query_arg( 'pagenum', '%#%' ),
			'format' => '',
			'total' => $num_pages,
			'current' => $pagenum
		));

		$authentication = $Shopp->Settings->get('account_system');


		include(SHOPP_ADMIN_PATH."/memberships/memberships.php");

	}

	/**
	 * Registers the column headers for the customer list screen
	 *
	 * @author Jonathan Davis
	 * @return void
	 **/
	function columns () {
		shopp_enqueue_script('calendar');
		register_column_headers('shopp_page_shopp-memberships', array(
			'cb'=>'<input type="checkbox" />',
			'name'=>__('Name','Shopp'),
			'type'=>__('Type','Shopp'),
			'products'=>__('Products','Shopp'),
			'members'=>__('Members','Shopp')
		));

	}

	/**
	 * Builds the interface layout for the customer editor
	 *
	 * @author Jonathan Davis
	 * @return void Description...
	 **/
	function layout () {
		global $Shopp,$ruletypes,$rulegroups;
		$Admin =& $Shopp->Flow->Admin;

		$rulegroups = apply_filters('shopp_access_rule_groups',array(
			'wp' => __('WordPress','Shopp'),
			'shopp' => __('Shopp','Shopp')
		));
		$ruletypes = apply_filters('shopp_access_rule_types',array(
			'wp_posts' => __('Posts','Shopp'),
			'wp_pages' => __('Pages','Shopp'),
			'wp_categories' => __('Categories','Shopp'),
			'wp_tags' => __('Tags','Shopp'),
			'wp_media' => __('Media','Shopp'),

			'shopp_memberships' => __('Memberships','Shopp'),
			'shopp_products' => __('Products','Shopp'),
			'shopp_categories' => __('Categories','Shopp'),
			'shopp_tags' => __('Tags','Shopp'),
			'shopp_promotions' => __('Promotions','Shopp'),
			'shopp_downloads' => __('Downloads','Shopp')
		));

		include(SHOPP_ADMIN_PATH."/memberships/ui.php");
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
		global $Shopp,$ruletypes,$rulegroups;
		$db =& DB::get();

		if ( !(is_shopp_userlevel() || current_user_can('shopp_memberships')) )
			wp_die(__('You do not have sufficient permissions to access this page.'));


		if ($_GET['id'] != "new") {
			$Membership = new Membership($_GET['id']);
			if (empty($Membership->id))
				wp_die(__('The requested membership record does not exist.','Shopp'));
		} else $Customer = new Customer();

		include(SHOPP_ADMIN_PATH."/memberships/editor.php");
	}


} // END class Members

?>