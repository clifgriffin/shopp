<?php
/**
 * Install
 * 
 * Flow controller for installation and upgrades
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, January  6, 2010
 * @package shopp
 * @subpackage shopp
 **/

/**
 * Install
 *
 * @package shopp
 * @author Jonathan Davis
 **/
class Install {
	
	/**
	 * Install constructor
	 *
	 * @return void
	 * @author Jonathan Davis
	 **/
	function __construct ( () {
		
	}

	/**
	 * parse function
	 *
	 * @return void
	 * @author Jonathan Davis
	 **/
	function parse () {

	}

	function update () {
		global $Shopp,$wp_version;
		$db = DB::get();
		$log = array();

		if (!isset($_POST['update'])) die("Update Failed: Update request is invalid.  No update specified.");
		if (!isset($_POST['type'])) die("Update Failed: Update request is invalid. Update type not specified");
		if (!isset($_POST['password'])) die("Update Failed: Update request is invalid. No FTP password provided.");

		$updatekey = $this->Settings->get('updatekey');
		
		$credentials = $this->Settings->get('ftp_credentials');
		if (empty($credentials)) {
			// Try to load from WordPress settings
			$credentials = get_option('ftp_credentials');
			if (!$credentials) $credentials = array();
		}
		
		// Make sure we can connect to FTP
		$ftp = new FTPClient($credentials['hostname'],$credentials['username'],$_POST['password']);
		if (!$ftp->connected) die("ftp-failed");
		else $log[] = "Connected with FTP successfully.";
		
		// Get zip functions from WP Admin
		if (class_exists('PclZip')) $log[] = "ZIP library available.";
		else {
			@require_once(ABSPATH.'wp-admin/includes/class-pclzip.php');
			$log[] = "ZIP library loaded.";
		}
		
		// Put site in maintenance mode
		if ($this->Settings->get('maintenance') != "on") {
			$this->Settings->save("maintenance","on");
			$log[] = "Enabled maintenance mode.";
		}
		
		// Find our temporary filesystem workspace
		$tmpdir = defined('SHOPP_TEMP_PATH') ? SHOPP_TEMP_PATH : sys_get_temp_dir();
		$tmpdir = sanitize_path($tmpdir);
		
		$log[] = "Found temp directory: $tmpdir";
		
		// Download the new version of Shopp
		$updatefile = tempnam($tmpdir,"shopp_update_");
		if (($download = fopen($updatefile, 'wb')) === false) {
			$log[] = "A temporary file could not be created under $tmpdir, trying WordPress upload directory instead.";
			$tmpdir = trailingslashit(WP_CONTENT_DIR."/uploads");
			$updatefile = tempnam($tmpdir,"shopp_update_");
			$log[] = "Found temp directory: $tmpdir";
			if (($download = fopen($updatefile, 'wb')) === false)
				die(join("\n\n",$log)."\n\nUpdate Failed: Cannot save the Shopp update to the temporary workspace because of a write permission error.");
		}
		
		$query = build_query_request(array(
			"ShoppServerRequest" => "download-update",
			"ver" => "1.0",
		));
		
		$data = build_query_request(array(
			"key" => $updatekey['key'],
			"core" => SHOPP_VERSION,
			"wp" => $wp_version,
			"site" => get_bloginfo('siteurl'),
			"update" => $_POST['update']
		));
		
		$connection = curl_init();
		curl_setopt($connection, CURLOPT_URL, SHOPP_HOME."?".$query); 
		curl_setopt($connection, CURLOPT_USERAGENT, SHOPP_GATEWAY_USERAGENT); 
		curl_setopt($connection, CURLOPT_HEADER, 0);
		curl_setopt($connection, CURLOPT_POST, 1); 
		curl_setopt($connection, CURLOPT_POSTFIELDS, $data);
		curl_setopt($connection, CURLOPT_TIMEOUT, 20); 
	    curl_setopt($connection, CURLOPT_FILE, $download); 
		curl_exec($connection); 
		curl_close($connection);
		fclose($download);

		$downloadsize = filesize($updatefile);
		// Report error message returned by the server request
		if (filesize($updatefile) < 256) die(join("\n\n",$log)."\nUpdate Failed: ".file_get_contents($updatefile));
		
		// Nothing downloaded... couldn't reach the server?
		if (filesize($updatefile) == 0) die(join("\n\n",$log)."\n\Update Failed: The download did not complete succesfully.");
		
		// Download successful, log the size
		$log[] = "Downloaded update of ".number_format($downloadsize)." bytes";
		
		// Extract data
		$log[] = "Unpacking updates...";
		$archive = new PclZip($updatefile);
		$files = $archive->extract(PCLZIP_OPT_EXTRACT_AS_STRING);
		if (!is_array($files)) die(join("\n\n",$log)."\n\nUpdate Failed: The downloaded update did not complete or is corrupted and cannot be used.");
		else unlink($updatefile);
		$target = trailingslashit($tmpdir);
		
		// Move old updates that still exist in $tmpdir to a new location
		if (file_exists($target.$files[0]['filename']) 
			&& is_dir($target.$files[0]['filename']))
			rename($target.$files[0]['filename'],$updatefile.'_old_update');
		
		// Create file structure in working path target
		foreach ($files as $file) {
			if (!$file['folder'] ) {
				if (file_put_contents($target.$file['filename'], $file['content']))
					@chmod($target.$file['filename'], 0644);
			} else {
				if (!is_dir($target.$file['filename'])) {
					if (!@mkdir($target.$file['filename'],0755,true)) 
						die(join("\n\n",$log)."\n\nUpdate Failed: Couldn't create directory $target{$file['filename']}");
				}				
			}
		}
		$log[] = "Successfully unpacked the update.";
		
		// FTP files to make it "easier" than dealing with permissions
		$log[] = "Updating files via FTP connection";
		switch($_POST['type']) {
			case "core":
				$results = $ftp->update($target.$files[0]['filename'],$Shopp->path);
				if (!empty($results)) die(join("\n\n",$log).join("\n\n",$results)."\n\nFTP transfer failed.");
				break;
			case "Payment Gateway":
				$results = $ftp->update($target.$files[0]['filename'],
							$Shopp->path.'/'."gateways".'/'.$files[0]['filename']);
				if (!empty($results)) die(join("\n\n",$log).join("\n\n",$results)."\n\nFTP transfer failed.");
				break;
			case "Shipping Module":
				$results = $ftp->update($target.$files[0]['filename'],
							$Shopp->path.'/'."shipping".'/'.$files[0]['filename']);
				if (!empty($results)) die(join("\n\n",$log).join("\n\n",$results)."\n\nFTP transfer failed.");
				break;
		}
		
		echo "updated"; // Report success!
		exit();
	}
		
	function upgrade () {
		global $Shopp,$table_prefix;
		$db = DB::get();
		require_once(ABSPATH.'wp-admin/includes/upgrade.php');

		// Check for the schema definition file
		if (!file_exists(SHOPP_DBSCHEMA))
		 	die("Could not upgrade the Shopp database tables because the table definitions file is missing: ".SHOPP_DBSCHEMA);
		
		ob_start();
		include(SHOPP_DBSCHEMA);
		$schema = ob_get_contents();
		ob_end_clean();
		
		// Update the table schema
		$tables = preg_replace('/;\s+/',';',$schema);
		dbDelta($tables);
		
		$this->setup_regions();
		$this->setup_countries();
		$this->setup_zones();
		$this->setup_areas();
		$this->setup_vat();
		
		// Update the version number
		$settings = DatabaseObject::tablename(Settings::$table);
		$db->query("UPDATE $settings SET value='".SHOPP_VERSION." WHERE name='version'");
		$db->query("DELETE FROM $settings WHERE name='data_model' OR name='shipcalc_lastscan");
		
		return true;
	}

	function callhome ($request=array(),$data=array()) {
		$query = build_query_request($request);
		$data = build_query_request($data);
		
		$connection = curl_init(); 
		curl_setopt($connection, CURLOPT_URL, SHOPP_HOME."?".$query); 
		curl_setopt($connection, CURLOPT_USERAGENT, SHOPP_GATEWAY_USERAGENT); 
		curl_setopt($connection, CURLOPT_HEADER, 0);
		curl_setopt($connection, CURLOPT_POST, 1); 
		curl_setopt($connection, CURLOPT_POSTFIELDS, $data);
		curl_setopt($connection, CURLOPT_TIMEOUT, 20); 
		curl_setopt($connection, CURLOPT_RETURNTRANSFER, 1); 
		$result = curl_exec($connection); 
		curl_close ($connection);
		
		return $result;
	}


	/**
	 * setup()
	 * Initialize default install settings and lists */
	function setup () {
		
		$this->setup_regions();
		$this->setup_countries();
		$this->setup_zones();
		$this->setup_areas();
		$this->setup_vat();

		$this->Settings->save('show_welcome','on');	
		$this->Settings->save('display_welcome','on');	
		
		// General Settings
		$this->Settings->save('version',SHOPP_VERSION);
		$this->Settings->save('shipping','on');	
		$this->Settings->save('order_status',array('Pending','Completed'));	
		$this->Settings->save('shopp_setup','completed');
		$this->Settings->save('maintenance','off');
		$this->Settings->save('dashboard','on');

		// Checkout Settings
		$this->Settings->save('order_confirmation','ontax');	
		$this->Settings->save('receipt_copy','1');	
		$this->Settings->save('account_system','none');	

		// Presentation Settings
		$this->Settings->save('theme_templates','off');
		$this->Settings->save('row_products','3');
		$this->Settings->save('catalog_pagination','25');
		$this->Settings->save('product_image_order','ASC');
		$this->Settings->save('product_image_orderby','sortorder');
		$this->Settings->save('gallery_small_width','240');
		$this->Settings->save('gallery_small_height','240');
		$this->Settings->save('gallery_small_sizing','1');
		$this->Settings->save('gallery_small_quality','2');
		$this->Settings->save('gallery_thumbnail_width','96');
		$this->Settings->save('gallery_thumbnail_height','96');
		$this->Settings->save('gallery_thumbnail_sizing','1');
		$this->Settings->save('gallery_thumbnail_quality','3');
		
		// System Settinggs
		$this->Settings->save('image_storage_pref','db');
		$this->Settings->save('product_storage_pref','db');
		$this->Settings->save('uploader_pref','flash');
		$this->Settings->save('script_loading','global');

		// Payment Gateway Settings
		$this->Settings->save('PayPalExpress',array('enabled'=>'off'));
		$this->Settings->save('GoogleCheckout',array('enabled'=>'off'));
	}

	function regions () {
		global $Shopp;
		include_once("init.php");
		$regions = apply_filters('shopp_setup_global_regions',get_global_regions());
		$this->Settings->save('regions',$regions);
	}
	
	function countries () {
		global $Shopp;
		include_once("init.php");
		$countries = apply_filters('shopp_setup_country_table',get_countries());
		$this->Settings->save('countries',addslashes(serialize($countries)),false);
	}
	
	function zones () {
		global $Shopp;
		include_once("init.php");
		$zones = apply_filters('shopp_setup_country_zones',get_country_zones());
		$this->Settings->save('zones',$zones,false);
	}

	function areas () {
		global $Shopp;
		include_once("init.php");
		$areas = apply_filters('shopp_setup_country_areas',get_country_areas());
		$this->Settings->save('areas',$areas,false);
	}

	function vat () {
		global $Shopp;
		include_once("init.php");
		$countries = apply_filters('shopp_setup_vat_countries',get_vat_countries());
		$this->Settings->save('vat_countries',$countries,false);
	}


} // end Install class

?>