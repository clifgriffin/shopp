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
	var $request = array();

	/**
	 * Resources constructor
	 *
	 * @author Jonathan Davis
	 *
	 * @return void
	 **/
	function __construct ( $request = array() ) {

		if (empty($request) && !( defined('WP_ADMIN') && isset($request['src']) ))
			return;

		$this->request = empty($request)?$_GET:$request;

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
		if (!current_user_can('shopp_financials') || !current_user_can('shopp_export_orders')) exit();

		if (!isset($_POST['settings']['purchaselog_columns'])) {
			$Purchase = Purchase::exportcolumns();
			$Purchased = Purchased::exportcolumns();
			$_POST['settings']['purchaselog_columns'] =
			 	array_keys(array_merge($Purchase,$Purchased));
			$_POST['settings']['purchaselog_headers'] = "on";
		}
		shopp_set_formsettings(); // Save workflow setting

		$format = shopp_setting('purchaselog_format');
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
		if (!current_user_can('shopp_export_customers')) exit();
		if (!isset($_POST['settings']['customerexport_columns'])) {
			$Customer = Customer::exportcolumns();
			$Billing = BillingAddress::exportcolumns();
			$Shipping = ShippingAddress::exportcolumns();
			$_POST['settings']['customerexport_columns'] =
			 	array_keys(array_merge($Customer,$Billing,$Shipping));
			$_POST['settings']['customerexport_headers'] = "on";
		}

		shopp_set_formsettings(); // Save workflow setting

		$format = shopp_setting('customerexport_format');
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
	 * @return void
	 **/
	function download () {
		global $Shopp;
		$download = $this->request['shopp_download'];
		$Purchase = false;
		$Purchased = false;

		if (defined('WP_ADMIN')) {
			$forbidden = false;
			$Download = new ProductDownload($download);
		} else {
			$Order = ShoppOrder();

			$Download = new ProductDownload();
			$Download->loadby_dkey($download);

			$Purchased = $Download->purchased();

			$Purchase = new Purchase($Purchased->purchase);
			$Purchase->load_events();

			$name = $Purchased->name.(!empty($Purchased->optionlabel)?' ('.$Purchased->optionlabel.')':'');

			$paidstatus = array( /* For 1.1 */ 'CHARGED', /* For 1.2+ */'captured' );

			$accounts = ('none' != shopp_setting('account_system'));

			$forbidden = false;
			// Purchase Completion check
			if ( !in_array($Purchase->txnstatus,$paidstatus) && !SHOPP_PREPAYMENT_DOWNLOADS) {
				new ShoppError(sprintf(__('"%s" cannot be downloaded because payment has not been received yet.','Shopp'),$name),'shopp_download_limit');
				$forbidden = true;
			}

			// Account restriction checks
			if ($accounts && !ShoppCustomer()->logged_in()) {
				new ShoppError(__('You must login to download purchases.','Shopp'),'shopp_download_limit');
				$forbidden = true;
			}

			// File owner authorization check
			if ($accounts && ShoppCustomer()->id != $Purchase->customer) {
				new ShoppError(__('You are not authorized to download the requested file.','Shopp'),'shopp_download_unauthorized');
				$forbidden = true;
			}

			// Download limit checking
			if (shopp_setting('download_limit') // Has download credits available
				&& $Purchased->downloads+1 > shopp_setting('download_limit')) {
					new ShoppError(sprintf(__('"%s" is no longer available for download because the download limit has been reached.','Shopp'),$name),'shopp_download_limit');
					$forbidden = true;
				}

			// Download expiration checking
			if (shopp_setting('download_timelimit') // Within the timelimit
				&& $Purchased->created+shopp_setting('download_timelimit') < current_time('timestamp') ) {
					new ShoppError(sprintf(__('"%s" is no longer available for download because it has expired.','Shopp'),$name),'shopp_download_limit');
					$forbidden = true;
				}

			// IP restriction checks
			if (shopp_setting('download_restriction') == "ip"
				&& !empty($Purchase->ip)
				&& $Purchase->ip != $_SERVER['REMOTE_ADDR']) {
					new ShoppError(sprintf(__('"%s" cannot be downloaded because your computer could not be verified as the system the file was purchased from.','Shopp'),$name),'shopp_download_limit');
					$forbidden = true;
				}

			do_action_ref_array('shopp_download_request',array(&$Purchased));
		}

		if (apply_filters('shopp_download_forbidden',$forbidden)) {
			shopp_redirect(add_query_arg('downloads','',shoppurl(false,'account')),true,303);
		}

		// Send the download
		$download = $Download->download();

		if (is_a($download,'ShoppError')) {
			// If the result is an error redirect to the account downloads page
			shopp_redirect(add_query_arg('downloads','',shoppurl(false,'account')),true,303);
		} else {
			do_action_ref_array('shopp_download_success',array(&$Purchased,$Purchase,$Download)); // @deprecated use shopp_download_order_event instead

			shopp_add_order_event($Purchase->id,'download',array(
				'purchased' => $Purchased->id,		// Purchased line item ID (or add-on meta record ID)
				'download' => $Download->id,		// Download ID (meta record)
				'ip' => ShoppShopping()->ip,		// IP address of the download
				'customer' => ShoppCustomer()->id	// Authenticated customer
			));
		}

		exit();
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

		$keysetting = Shopp::keysetting();
		$key = $keysetting['k'];

		$site = get_bloginfo('siteurl');

		$request = array("ShoppScreencast" => $_GET['id'],'key'=>$key,'site'=>$site);
		$response = Shopp::callhome($request);
		echo $response;
		exit();
	}

} // END class Resources

?>