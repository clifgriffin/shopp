<?php
/**
 * Promote
 *
 * Flow controller for promotion management interfaces
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, January 6, 2010
 * @package shopp
 * @subpackage promotions
 **/

/**
 * Promote
 *
 * @package promotions
 * @author Jonathan Davis
 **/
class Promote extends AdminController {
	var $Notice = false;

	/**
	 * Promote constructor
	 *
	 * @return void
	 * @author Jonathan Davis
	 **/
	function __construct () {
		parent::__construct();
		if (!empty($_GET['id'])) {
			wp_enqueue_script('postbox');
			shopp_enqueue_script('colorbox');
			shopp_enqueue_script('calendar');
			do_action('shopp_promo_editor_scripts');
			add_action('admin_head',array(&$this,'layout'));
		} else add_action('admin_print_scripts',array(&$this,'columns'));
		do_action('shopp_promo_admin_scripts');
	}

	/**
	 * Parses admin requests to determine which interface to render
	 *
	 * @return void
	 * @author Jonathan Davis
	 **/
	function admin () {
		global $Shopp;
		if (!empty($_GET['id'])) $this->editor();
		else $this->promotions();
	}

	/**
	 * Interface processor for the promotions list manager
	 *
	 * @author Jonathan Davis
	 * @return void
	 **/
	function promotions () {
		global $Shopp;

		if ( ! current_user_can('shopp_promotions') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		$table = DatabaseObject::tablename(Promotion::$table);

		$defaults = array(
			'page' => false,
			'action' => false,
			'selected' => array(),
			'pagenum' => 1,
			'per_page' => 20,
			's' => '',
			);

		$args = array_merge($defaults,$_GET);
		extract($args,EXTR_SKIP);
		if (!is_array($selected)) $selected = array($selected);

		$url = add_query_arg(array_merge($_GET,array('page'=>'shopp-promotions')),admin_url('admin.php'));
		$f = array('action','selected','s');
		$url = remove_query_arg( $f, $url );

		if ('shopp-promotions' == $page && $action !== false) {
			switch ( $action ) {
				case 'enable': Promotion::enableset($selected); break;
				case 'disable': Promotion::disableset($selected); break;
				case 'delete': Promotion::deleteset($selected); break;
			}
		}

		if (!empty($_POST['save'])) {
			check_admin_referer('shopp-save-promotion');

			if ($_POST['id'] != "new") {
				$Promotion = new Promotion($_POST['id']);
			} else $Promotion = new Promotion();

			if (!empty($_POST['starts']['month']) && !empty($_POST['starts']['date']) && !empty($_POST['starts']['year']))
				$_POST['starts'] = mktime(0,0,0,$_POST['starts']['month'],$_POST['starts']['date'],$_POST['starts']['year']);
			else $_POST['starts'] = 1;

			if (!empty($_POST['ends']['month']) && !empty($_POST['ends']['date']) && !empty($_POST['ends']['year']))
				$_POST['ends'] = mktime(23,59,59,$_POST['ends']['month'],$_POST['ends']['date'],$_POST['ends']['year']);
			else $_POST['ends'] = 1;
			if (isset($_POST['rules'])) $_POST['rules'] = stripslashes_deep($_POST['rules']);

			$Promotion->updates($_POST);
			$Promotion->save();

			do_action_ref_array('shopp_promo_saved',array(&$Promotion));

			// $Promotion->reset_discounts();
			if ($Promotion->target == "Catalog")
				$Promotion->catalog_discounts();

			// Force reload of the session promotions to include any updates
			$Shopp->Promotions->reload();

		}

		$pagenum = absint( $pagenum );
		if ( empty($pagenum) )
			$pagenum = 1;
		$start = ($per_page * ($pagenum-1));

		$where = array();
		if (!empty($s)) $where[] = "name LIKE '%$s%'";

		$select = DB::select(array(
			'table' => $table,
			'columns' => 'SQL_CALC_FOUND_ROWS *',
			'where' => $where,
			'limit' => "$start,$per_page"
		));

		$Promotions = DB::query($select,'array');

		$count = DB::query("SELECT FOUND_ROWS() as total",'auto','col','total');

		$status = array(
			'enabled' => __('Enabled','Shopp'),
			'disabled' => __('Disabled','Shopp')
		);

		$num_pages = ceil($count / $per_page);
		$page_links = paginate_links( array(
			'base' => add_query_arg( 'pagenum', '%#%' ),
			'format' => '',
			'total' => $num_pages,
			'current' => $pagenum
		));

		include(SHOPP_PATH.'/core/ui/promotions/promotions.php');
	}

	/**
	 * Registers the column headers for the promotions list interface
	 *
	 * @author Jonathan Davis
	 * @return void
	 **/
	function columns () {
		register_column_headers('shopp_page_shopp-promotions', array(
			'cb'=>'<input type="checkbox" />',
			'name'=>__('Name','Shopp'),
			'discount'=>__('Discount','Shopp'),
			'applied'=>__('Applied To','Shopp'),
			'eff'=>__('Status','Shopp'))
		);
	}

	/**
	 * Generates the layout for the promotion editor
	 *
	 * @author Jonathan Davis
	 * @return void
	 **/
	function layout () {
		global $Shopp;
		$Admin =& $Shopp->Flow->Admin;
		include(SHOPP_PATH."/core/ui/promotions/ui.php");
	}

	/**
	 * Interface processor for the promotion editor
	 *
	 *
	 *
	 * @author Jonathan Davis
	 * @return void
	 **/
	function editor () {
		global $Shopp;

		if ( ! current_user_can('shopp_promotions') )
			wp_die(__('You do not have sufficient permissions to access this page.'));


		if ($_GET['id'] != "new") {
			$Promotion = new Promotion($_GET['id']);
		} else $Promotion = new Promotion();

		include(SHOPP_PATH."/core/ui/promotions/editor.php");
	}


} // end Promote class

?>