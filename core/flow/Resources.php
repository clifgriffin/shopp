<?php
/**
 * Resources.php
 * 
 * Processes resource requests for non-HTML data
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, February  8, 2010
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @subpackage resources
 **/
class Resources {
	
	var $Settings = false;
	var $request = array();
	
	/**
	 * Resources constructor
	 *
	 * @author Jonathan Davis
	 * 
	 * @return void
	 **/
	function __construct () {
		global $Shopp,$wp;
		if (empty($wp->query_vars) && !(defined('WP_ADMIN') && isset($_GET['src']))) return;
		
		$this->Settings = &$Shopp->Settings;
		
		if (empty($wp->query_vars)) $this->request = $_GET;
		else $this->request = $wp->query_vars;
		
		add_action('shopp_resource_category_rss',array(&$this,'category_rss'));
		add_action('shopp_resource_download',array(&$this,'download'));

		// For secure, backend lookups
		if (defined('WP_ADMIN') && is_user_logged_in()) {
			add_action('shopp_resource_help',array(&$this,'help'));
			if (current_user_can('shopp_financials')) {
				add_action('shopp_resource_export_purchases',array(&$this,'export_purchases'));
				add_action('shopp_resource_export_customers',array(&$this,'export_customers'));
			}
		}
		
		if ( !empty( $this->request['src'] ) )
			do_action( 'shopp_resource_' . $this->request['src'] );

		die('-1');
	}
	
	/**
	 * Handles RSS-feed requests
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void Description...
	 **/
	function category_rss () {
		global $Shopp;
		require_once(SHOPP_FLOW_PATH.'/Storefront.php');
		$Storefront = new Storefront();
		$Storefront->catalog($this->request);
		header("Content-type: application/rss+xml; charset=utf-8");
		echo shopp_rss($Shopp->Category->rss());
		exit();
	}
	
	/**
	 * Delivers order export files to the browser
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function export_purchases () {

		if (!isset($_POST['settings']['purchaselog_columns'])) {
			$Purchase = Purchase::exportcolumns();
			$Purchased = Purchased::exportcolumns();
			$_POST['settings']['purchaselog_columns'] =
			 	array_keys(array_merge($Purchase,$Purchased));
			$_POST['settings']['purchaselog_headers'] = "on";
		}
		$this->Settings->saveform();
		
		$format = $this->Settings->get('purchaselog_format');
		if (empty($format)) $format = 'tab';
		
		switch ($format) {
			case "csv": new PurchasesCSVExport(); break;
			case "xls": new PurchasesXLSExport(); break;
			case "iif": new PurchasesIIFExport(); break;
			default: new PurchasesTabExport();
		}
		exit();
		
	}
	
	/**
	 * Delivers customer export files to the browser
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function export_customers () {

		if (!isset($_POST['settings']['customerexport_columns'])) {
			$Customer = Customer::exportcolumns();
			$Billing = Billing::exportcolumns();
			$Shipping = Shipping::exportcolumns();
			$_POST['settings']['customerexport_columns'] =
			 	array_keys(array_merge($Customer,$Billing,$Shipping));
			$_POST['settings']['customerexport_headers'] = "on";
		}

		$this->Settings->saveform();

		$format = $this->Settings->get('customerexport_format');
		if (empty($format)) $format = 'tab';

		switch ($format) {
			case "csv": new CustomersCSVExport(); break;
			case "xls": new CustomersXLSExport(); break;
			default: new CustomersTabExport();
		}
		exit();
	}
	
	/**
	 * Handles product file download requests
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void Description...
	 **/
	function download () {
		global $Shopp;
		$download = $this->request['shopp_download'];

		if (defined('WP_ADMIN')) {
			$forbidden = false;
			$Download = new ProductDownload($download);
		} else {
			$Order = &ShoppOrder();
			
			require_once(SHOPP_MODEL_PATH."/Purchased.php");
			$Purchased = new Purchased($download,"dkey");
			$Purchase = new Purchase($Purchased->purchase);
			
			$Download = new ProductDownload();
			$Download->loadby_dkey($download);

			$forbidden = false;
			// Purchase Completion check
			if ($Purchase->txnstatus != "CHARGED" 
				&& !SHOPP_PREPAYMENT_DOWNLOADS) {
				new ShoppError(__('This file cannot be downloaded because payment has not been received yet.','Shopp'),'shopp_download_limit');
				$forbidden = true;
			}
			
			// Account restriction checks
			if ($this->Settings->get('account_system') != "none"
				&& (!$Order->Customer->login
				|| $Order->Customer->id != $Purchase->customer)) {
					new ShoppError(__('You must login to access this download.','Shopp'),'shopp_download_limit',SHOPP_ERR);
					shopp_redirect($Shopp->link('account'));
			}
			
			// Download limit checking
			if ($this->Settings->get('download_limit') // Has download credits available
				&& $Purchased->downloads+1 > $this->Settings->get('download_limit')) {
					new ShoppError(__('This file can no longer be downloaded because the download limit has been reached.','Shopp'),'shopp_download_limit');
					$forbidden = true;
				}
					
			// Download expiration checking
			if ($this->Settings->get('download_timelimit') // Within the timelimit
				&& $Purchased->created+$this->Settings->get('download_timelimit') < mktime() ) {
					new ShoppError(__('This file can no longer be downloaded because it has expired.','Shopp'),'shopp_download_limit');
					$forbidden = true;
				}
			
			// IP restriction checks
			if ($this->Settings->get('download_restriction') == "ip"
				&& !empty($Purchase->ip) 
				&& $Purchase->ip != $_SERVER['REMOTE_ADDR']) {
					new ShoppError(__('The file cannot be downloaded because this computer could not be verified as the system the file was purchased from.','Shopp'),'shopp_download_limit');
					$forbidden = true;	
				}

			do_action_ref_array('shopp_download_request',array(&$Purchased));
		}
	
		if ($forbidden) {
			header("Status: 403 Forbidden");
		}
		
		if ($Download->download()) {
			$Purchased->downloads++;
			$Purchased->save();
			do_action_ref_array('shopp_download_success',array(&$Purchased));
			exit();
		}
	}

	/**
	 * Grabs interface help screencasts
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function help () {
		if (!isset($_GET['id'])) return;
		$request = array("ShoppScreencast" => $_GET['id']);
		$response = Shopp::callhome($request);
		echo $response;
		exit();
	}
	
} // END class Resources

?>