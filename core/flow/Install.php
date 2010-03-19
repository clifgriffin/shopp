<?php
/**
 * Install.php
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
 * ShoppInstallation
 *
 * @package shopp
 * @author Jonathan Davis
 **/
class ShoppInstallation extends FlowController {
	
	/**
	 * Install constructor
	 *
	 * @return void
	 * @author Jonathan Davis
	 **/
	function __construct () {
		$this->Settings = new Settings();
		add_action('shopp_activate',array(&$this,'activate'));
		add_action('shopp_deactivate',array(&$this,'deactivate'));
		add_action('shopp_setup',array(&$this,'setup'));
		add_action('shopp_autoupdate',array(&$this,'update'));
		add_action('shopp_post_upgrade',array(&$this,'postupgrade'));
	}

	/**
	 * parse function
	 *
	 * @return void
	 * @author Jonathan Davis
	 **/
	function parse () {

	}

	/**
	 * activate()
	 * Installs the tables and initializes settings */
	function activate () {
		global $wpdb,$wp_rewrite;

		// If no settings are available,
		// no tables exist, so this is a
		// new install
		if ($this->Settings->unavailable) {
			$this->install();
		}
		
		$ver = $this->Settings->get('version');
		if (!empty($ver) && $ver != SHOPP_VERSION) {
			$this->dbupgrades($ver);
		}
				
		if ($this->Settings->get('shopp_setup')) {
			$this->Settings->save('maintenance','off');
			$this->Settings->save('shipcalc_lastscan','');
			
			// Publish/re-enable Shopp pages
			$filter = "";
			$pages = $this->Settings->get('pages');
			foreach ($pages as $page) $filter .= ($filter == "")?"ID={$page['id']}":" OR ID={$page['id']}";	
			if ($filter != "") $wpdb->query("UPDATE $wpdb->posts SET post_status='publish' WHERE $filter");
			
			// Update rewrite rules
			$wp_rewrite->flush_rules();
			$wp_rewrite->wp_rewrite_rules();
			
		}
		
		if ($this->Settings->get('show_welcome') == "on")
			$this->Settings->save('display_welcome','on');
	}
	
	function deactivate () {
		global $Shopp,$wpdb,$wp_rewrite;
		if (!isset($Shopp->Settings)) return;
		
		// Unpublish/disable Shopp pages
		$filter = "";
		$pages = $Shopp->Settings->get('pages');
		if (!is_array($pages)) return true;
		foreach ($pages as $page) $filter .= ($filter == "")?"ID={$page['id']}":" OR ID={$page['id']}";	
		if ($filter != "") $wpdb->query("UPDATE $wpdb->posts SET post_status='draft' WHERE $filter");

		// Update rewrite rules
		$wp_rewrite->flush_rules();
		$wp_rewrite->wp_rewrite_rules();

		$this->Settings->save('data_model','');

		return true;
	}
	
	function install () {
		global $wpdb,$wp_rewrite,$wp_version,$table_prefix;
		$db = DB::get();

		// Install tables
		if (!file_exists(SHOPP_DBSCHEMA)) {
		 	trigger_error("Could not install the Shopp database tables because the table definitions file is missing: ".SHOPP_DBSCHEMA);
			exit();
		}

		ob_start();
		include(SHOPP_DBSCHEMA);
		$schema = ob_get_contents();
		ob_end_clean();

		$db->loaddata($schema);
		unset($schema);

		$parent = 0;
		foreach (Storefront::$Pages as $key => &$page) {
			if (!empty($this->Pages['catalog']['id'])) $parent = $this->Pages['catalog']['id'];
			$query = "INSERT $wpdb->posts SET post_title='{$page['title']}',
											  post_name='{$page['name']}',
											  post_content='{$page['content']}',
											  post_parent='$parent',
											  post_author='1',
											  post_status='publish',
											  post_type='page',
											  post_date=now(),
											  post_date_gmt=utc_timestamp(),
											  post_modified=now(),
											  post_modified_gmt=utc_timestamp(),
											  comment_status='closed',
											  ping_status='closed',
											  post_excerpt='',
											  to_ping='',     
											  pinged='',      
											  post_content_filtered='',
											  menu_order=0";
			$wpdb->query($query);
			$page['id'] = $wpdb->insert_id;
			$page['permalink'] = get_permalink($page['id']);
			if ($key == "checkout") $page['permalink'] = str_replace("http://","https://",$page['permalink']);
			$wpdb->query("UPDATE $wpdb->posts SET guid='{$page['permalink']}' WHERE ID={$page['id']}");
			$page['permalink'] = preg_replace('|https?://[^/]+/|i','',$page['permalink']);
		}

		$this->Settings->save("pages",$this->Pages);
	}
		
	function dbupgrades ($version) {
		global $Shopp,$table_prefix;
		$db = DB::get();
		$db_version = $this->Settings->get('dbschema_version');
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

		if ($dbschema_version != DB::$schema)
			$this->updates_100();
		
		// Update the version number
		$settings = DatabaseObject::tablename(Settings::$table);
		$db->query("UPDATE $settings SET value='".SHOPP_VERSION."' WHERE name='version'");
		$db->query("DELETE FROM $settings WHERE name='data_model' OR name='shipcalc_lastscan");
		
		return true;
	}

	/**
	 * Installed roles and capabilities used for Shopp
	 *
	 * Capabilities						Role
	 * _______________________________________________
	 * 
	 * shopp_settings					admin
	 * shopp_settings_checkout
	 * shopp_settings_payments
	 * shopp_settings_shipping
	 * shopp_settings_taxes
	 * shopp_settings_presentation
	 * shopp_settings_system
	 * shopp_settings_update
	 * shopp_financials					merchant
	 * shopp_promotions
	 * shopp_products
	 * shopp_categories
	 * shopp_orders						shopp-csr
	 * shopp_customers
	 * shopp_menu
	 *
	 * @author John Dillick
	 * @since 1.1
	 * 
	 **/
	function roles () {
		global $wp_roles; // WP_Roles roles container
		if(!$wp_roles) $wp_roles = new WP_Roles();
		$shopp_roles = array('administrator'=>'Administrator', 'shopp-merchant'=>__('Merchant','Shopp'), 'shopp-csr'=>__('Customer Service Rep','Shopp'));
		$caps['shopp-csr'] = array('shopp_customers', 'shopp_orders','shopp_menu','read');
		$caps['shopp-merchant'] = array_merge($caps['shopp-csr'], 
			array('shopp_categories', 
				'shopp_products', 
				'shopp_promotions',
				'shopp_financials'));
		$caps['administrator'] = array_merge($caps['shopp-merchant'], 
			array('shopp_settings_update', 
				'shopp_settings_system', 
				'shopp_settings_presentation', 
				'shopp_settings_taxes', 
				'shopp_settings_shipping', 
				'shopp_settings_payments', 
				'shopp_settings_checkout',
				'shopp_settings'));
		$wp_roles->remove_role('shopp-csr');
		$wp_roles->remove_role('shopp-merchant');
		
		foreach($shopp_roles as $role => $display) {
			if($wp_roles->is_role($role)) {
				foreach($caps[$role] as $cap) $wp_roles->add_cap($role, $cap, true);
			} else {
				$wp_roles->add_role($role, $display, array_fill_keys($caps[$role],true));
			}
		}
	}

	/**
	 * setup()
	 * Initialize default install settings and lists */
	function setup () {
		
		$this->Settings->save('show_welcome','on');	
		$this->Settings->save('display_welcome','on');	
		
		// General Settings
		$this->Settings->save('version',SHOPP_VERSION);
		$this->Settings->save('dbschema_version',DB::$schema);
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
		$this->Settings->save('uploader_pref','flash');
		$this->Settings->save('script_loading','global');

		// Payment Gateway Settings
		$this->Settings->save('PayPalExpress',array('enabled'=>'off'));
		$this->Settings->save('GoogleCheckout',array('enabled'=>'off'));
		
		// Setup Roles and Capabilities
		$this->roles();
	}
	
	function updates_100 () {
		$db =& DB::get();
		
		// Update specs
		$meta_table = DatabaseObject::tablename('meta');
		$spec_table = DatabaseObject::tablename('spec');
		$db->query("INSERT INTO $meta_table (parent,context,type,name,value,numeral,sortorder,created,modified)
					SELECT product,'product','spec',name,content,numeral,sortorder,created,modified FROM $spec_table");
					

		// Update purchase table
		$purchase_table = DatabaseObject::tablename('purchase');
		$db->query("UPDATE $purchase_table SET txnid=transactionid,txnstatus=transtatus");

		// Update image assets
		$meta_table = DatabaseObject::tablename('meta');
		$asset_table = DatabaseObject::tablename('asset');
		$db->query("INSERT INTO $meta_table (parent,context,type,name,value,numeral,sortorder,created,modified)
							SELECT parent,context,'image','processing',CONCAT_WS('::',name,value,size,properties,LENGTH(data)),'0',sortorder,created,modified FROM $asset_table WHERE datatype='image'");
		$records = $db->query("SELECT id,value FROM $meta_table WHERE type='image' AND name='processing'",AS_ARRAY);
		foreach ($records as $r) {
			list($name,$value,$size,$properties,$datasize) = explode("::",$r->value);
			$p = unserialize($properties);
			$value = new StdClass();
			$value->width = $p['width'];
			$value->height = $p['height'];
			$value->alt = $p['alt'];
			$value->title = $p['title'];
			$value->filename = $name;
			$value->mime = $p['mimetype'];
			$value->size = $size;
			if ($datasize > 0) {
				$value->storage = "DBStorage";
				$value->uri = $r->id;
			} else {
				$value->storage = "FSStorage";
				$value->uri = $name;
			}
			$value = mysql_real_escape_string(serialize($value));
			$db->query("UPDATE $meta_table set name='original',value='$value' WHERE id=$r->id"); 			
		}
		
		// Update product downloads
		$meta_table = DatabaseObject::tablename('meta');
		$asset_table = DatabaseObject::tablename('asset');
		$query = "INSERT INTO $meta_table (parent,context,type,name,value,numeral,sortorder,created,modified)
					SELECT parent,context,'download','processing',CONCAT_WS('::',name,value,size,properties,LENGTH(data)),'0',sortorder,created,modified FROM $asset_table WHERE datatype='download' AND parent != 0";
		$db->query($query);
		$records = $db->query("SELECT id,value FROM $meta_table WHERE type='download' AND name='processing'",AS_ARRAY);
		foreach ($records as $r) {
			list($name,$value,$size,$properties,$datasize) = explode("::",$r->value);
			$p = unserialize($properties);
			$value = new StdClass();
			$value->filename = $name;
			$value->mime = $p['mimetype'];
			$value->size = $size;
			if ($datasize > 0) {
				$value->storage = "DBStorage";
				$value->uri = $r->id;
			} else {
				$value->storage = "FSStorage";
				$value->uri = $name;
			}
			$value = mysql_real_escape_string(serialize($value));
			$db->query("UPDATE $meta_table set name='$name',value='$value' WHERE id=$r->id");
		}
		
		$this->roles(); // Setup Roles and Capabilities
		
		$this->Settings->save('dbschema_version',DB::$schema);
	}
	
	/**
	 * Perform automatic updates for the core plugin and addons
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function update () {
		global $parent_file,$submenu_file;
		
		$plugin = isset($_REQUEST['plugin']) ? trim($_REQUEST['plugin']) : '';
		$addon = isset($_REQUEST['addon']) ? trim($_REQUEST['addon']) : '';
		$type = isset($_REQUEST['type']) ? trim($_REQUEST['type']) : '';

		if ( ! current_user_can('update_plugins') )
			wp_die(__('You do not have sufficient permissions to update plugins for this blog.'));
		
		
		if (SHOPP_PLUGINFILE == $plugin) {
			// check_admin_referer('upgrade-plugin_' . $plugin);
			$title = __('Upgrade Shopp','Shopp');
			$parent_file = 'plugins.php';
			$submenu_file = 'plugins.php';
			require_once(ABSPATH.'wp-admin/admin-header.php');

			$nonce = 'upgrade-plugin_' . $plugin;
			$url = 'update.php?action=shopp&plugin=' . $plugin;

			$upgrader = new ShoppCore_Upgrader( new Shopp_Upgrader_Skin( compact('title', 'nonce', 'url', 'plugin') ) );
			$upgrader->upgrade($plugin);

			include(ABSPATH.'/wp-admin/admin-footer.php');
		} elseif ('gateway' == $type ) {
			// check_admin_referer('upgrade-shopp-addon_' . $plugin);
			$title = sprintf(__('Upgrade Shopp Add-on','Shopp'),'Shopp');
			$parent_file = 'plugins.php';
			$submenu_file = 'plugins.php';
			require_once(ABSPATH.'wp-admin/admin-header.php');

			$nonce = 'upgrade-shopp-addon_' . $plugin;
			$url = 'update.php?action=shopp&addon='.$addon.'&type='.$type;

			$upgrader = new ShoppAddon_Upgrader( new Shopp_Upgrader_Skin( compact('title', 'nonce', 'url', 'addon') ) );
			$upgrader->upgrade($addon,'gateway');

			include(ABSPATH.'/wp-admin/admin-footer.php');

		} elseif ('shipping' == $type ) {
			// check_admin_referer('upgrade-shopp-addon_' . $plugin);
			$title = sprintf(__('Upgrade Shopp Add-on','Shopp'),'Shopp');
			$parent_file = 'plugins.php';
			$submenu_file = 'plugins.php';
			require_once(ABSPATH.'wp-admin/admin-header.php');

			$nonce = 'upgrade-shopp-addon_' . $plugin;
			$url = 'update.php?action=shopp&addon='.$addon.'&type='.$type;

			$upgrader = new ShoppAddon_Upgrader( new Shopp_Upgrader_Skin( compact('title', 'nonce', 'url', 'addon') ) );
			$upgrader->upgrade($addon,'shipping');

			include(ABSPATH.'/wp-admin/admin-footer.php');
		} elseif ('storage' == $type ) {
			// check_admin_referer('upgrade-shopp-addon_' . $plugin);
			$title = sprintf(__('Upgrade Shopp Add-on','Shopp'),'Shopp');
			$parent_file = 'plugins.php';
			$submenu_file = 'plugins.php';
			require_once(ABSPATH.'wp-admin/admin-header.php');

			$nonce = 'upgrade-shopp-addon_' . $plugin;
			$url = 'update.php?action=shopp&addon='.$addon.'&type='.$type;

			$upgrader = new ShoppAddon_Upgrader( new Shopp_Upgrader_Skin( compact('title', 'nonce', 'url', 'addon') ) );
			$upgrader->upgrade($addon,'storage');

			include(ABSPATH.'/wp-admin/admin-footer.php');
		}
	}

} // END class ShoppInstallation

if (!class_exists('Plugin_Upgrader'))
	require_once(ABSPATH."wp-admin/includes/class-wp-upgrader.php");

/**
 * Shopp_Upgrader class
 * 
 * Provides foundational functionality specific to Shopp update 
 * processing classes.
 * 
 * Extensions derived from the WordPress WP_Upgrader & Plugin_Upgrader classes:
 * @see wp-admin/includes/class-wp-upgrader.php
 * 
 * @copyright WordPress {@link http://codex.wordpress.org/Copyright_Holders}
 * 
 * @author Jonathan Davis
 * @since 1.1
 * @package shopp
 * @subpackage installation
 **/
class Shopp_Upgrader extends Plugin_Upgrader {
	
	function download_package($package) {

		if ( ! preg_match('!^(http|https|ftp)://!i', $package) && file_exists($package) ) //Local file or remote?
			return $package; //must be a local file..

		if ( empty($package) )
			return new WP_Error('no_package', $this->strings['no_package']);

		$this->skin->feedback('downloading_package', $package);

		$download_file = $this->download_url($package);

		if ( is_wp_error($download_file) )
			return new WP_Error('download_failed', $this->strings['download_failed'], $download_file->get_error_message());

		return $download_file;
	}
	
	function download_url ( $url ) {
		//WARNING: The file is not automatically deleted, The script must unlink() the file.
		if ( ! $url )
			return new WP_Error('http_no_url', __('Invalid URL Provided'));

		$request = parse_url($url);
		parse_str($request['query'],$query);
		$tmpfname = wp_tempnam($query['update'].".zip");
		if ( ! $tmpfname )
			return new WP_Error('http_no_file', __('Could not create Temporary file'));

		$handle = @fopen($tmpfname, 'wb');
		if ( ! $handle )
			return new WP_Error('http_no_file', __('Could not create Temporary file'));

		$response = wp_remote_get($url, array('timeout' => 300));

		if ( is_wp_error($response) ) {
			fclose($handle);
			unlink($tmpfname);
			return $response;
		}

		if ( $response['response']['code'] != '200' ){
			fclose($handle);
			unlink($tmpfname);
			return new WP_Error('http_404', trim($response['response']['message']));
		}

		fwrite($handle, $response['body']);
		fclose($handle);

		return $tmpfname;
	}
	
	function unpack_package($package, $delete_package = true, $clear_working = true) {
		global $wp_filesystem;

		$this->skin->feedback('unpack_package');

		$upgrade_folder = $wp_filesystem->wp_content_dir() . 'upgrade/';

		//Clean up contents of upgrade directory beforehand.
		if ($clear_working) {
			error_log("clearing working dir for $package");
			$upgrade_files = $wp_filesystem->dirlist($upgrade_folder);
			if ( !empty($upgrade_files) ) {
				foreach ( $upgrade_files as $file )
					$wp_filesystem->delete($upgrade_folder . $file['name'], true);
			}
		}

		//We need a working directory
		$working_dir = $upgrade_folder . basename($package, '.zip');

		// Clean up working directory
		if ( $wp_filesystem->is_dir($working_dir) )
			$wp_filesystem->delete($working_dir, true);

		// Unzip package to working directory
		$result = unzip_file($package, $working_dir); //TODO optimizations, Copy when Move/Rename would suffice?

		// Once extracted, delete the package if required.
		if ( $delete_package )
			unlink($package);

		if ( is_wp_error($result) ) {
			$wp_filesystem->delete($working_dir, true);
			return $result;
		}
		$this->working_dir = $working_dir;

		return $working_dir;
	}
	
}

/**
 * ShoppCore_Upgrader class
 * 
 * Adds auto-update support for the core plugin.
 * 
 * Extensions derived from the WordPress WP_Upgrader & Plugin_Upgrader classes:
 * @see wp-admin/includes/class-wp-upgrader.php
 * 
 * @copyright WordPress {@link http://codex.wordpress.org/Copyright_Holders}
 * 
 * @author Jonathan Davis
 * @since 1.1
 * @package shopp
 * @subpackage installation
 **/
class ShoppCore_Upgrader extends Shopp_Upgrader {
	
	function upgrade_strings() {
		$this->strings['up_to_date'] = __('Shopp is at the latest version.','Shopp');
		$this->strings['no_package'] = __('Shopp upgrade package not available.','Shopp');
		$this->strings['downloading_package'] = sprintf(__('Downloading update from <span class="code">%s</span>.'),SHOPP_HOME);;
		$this->strings['deactivate_plugin'] = __('Deactivating Shopp.','Shopp');
		$this->strings['remove_old'] = __('Removing the old version of Shopp.','Shopp');
		$this->strings['remove_old_failed'] = __('Could not remove the old Shopp.','Shopp');
		$this->strings['process_failed'] = __('Shopp upgrade Failed.','Shopp');
		$this->strings['process_success'] = __('Shopp upgraded successfully.','Shopp');
	}
		
	function upgrade($plugin) {
		$Settings = &ShoppSettings();
		$this->init();
		$this->upgrade_strings();
		
		$current = $Settings->get('updates');
		if ( !isset( $current->response[ $plugin ] ) ) {
			$this->skin->set_result(false);
			$this->skin->error('up_to_date');
			$this->skin->after();
			return false;
		}

		// Get the URL to the zip file
		$r = $current->response[ $plugin ];
		
		add_filter('upgrader_pre_install', array(&$this, 'addons'), 10, 2);
		// add_filter('upgrader_pre_install', array(&$this, 'deactivate_plugin_before_upgrade'), 10, 2);
		add_filter('upgrader_clear_destination', array(&$this, 'delete_old_plugin'), 10, 4);

		$this->run(array(
					'package' => $r->package,
					'destination' => WP_PLUGIN_DIR,
					'clear_destination' => true,
					'clear_working' => true,
					'hook_extra' => array(
					'plugin' => $plugin
					)
				));

		// Cleanup our hooks, incase something else does a upgrade on this connection.
		remove_filter('upgrader_pre_install', array(&$this, 'addons'));
		// remove_filter('upgrader_pre_install', array(&$this, 'deactivate_plugin_before_upgrade'));
		remove_filter('upgrader_clear_destination', array(&$this, 'delete_old_plugin'));

		if ( ! $this->result || is_wp_error($this->result) )
			return $this->result;

		// Force refresh of plugin update information
		$Settings->set('updates',false);
	}
		
	function addons ($return,$plugin) {
		$Settings = ShoppSettings();
		$current = $Settings->get('updates');

		if ( !isset( $current->response[ $plugin['plugin'].'/addons' ] ) ) return $return;
		$addons = $current->response[ $plugin['plugin'].'/addons' ];

		if (count($addons) > 0) {
			$upgrader = new ShoppAddon_Upgrader( $this->skin );
			$upgrader->addon_core_updates($addons,$this->working_dir);
		}
				
	}

}

/**
 * ShoppAddon_Upgrader class
 * 
 * Adds auto-update support for individual Shopp add-ons.
 * 
 * Extensions derived from the WordPress WP_Upgrader & Plugin_Upgrader classes:
 * @see wp-admin/includes/class-wp-upgrader.php
 * 
 * @copyright WordPress {@link http://codex.wordpress.org/Copyright_Holders}
 * 
 * @author Jonathan Davis
 * @since 1.1
 * @package shopp
 * @subpackage installation
 **/
class ShoppAddon_Upgrader extends Shopp_Upgrader {
	
	var $addon = false;
	var $addons_dir = false;
	var $destination = false;
	
	function upgrade_strings () {
		$this->strings['up_to_date'] = __('The add-on is at the latest version.','Shopp');
		$this->strings['no_package'] = __('Upgrade package not available.');
		$this->strings['downloading_package'] = sprintf(__('Downloading update from <span class="code">%s</span>.'),SHOPP_HOME);
		$this->strings['unpack_package'] = __('Unpacking the update.');
		$this->strings['deactivate_plugin'] = __('Deactivating the add-on.','Shopp');
		$this->strings['remove_old'] = __('Removing the old version of the add-on.','Shopp');
		$this->strings['remove_old_failed'] = __('Could not remove the old add-on.','Shopp');
		$this->strings['process_failed'] = __('Add-on upgrade Failed.','Shopp');
		$this->strings['process_success'] = __('Add-on upgraded successfully.','Shopp');
		$this->strings['include_success'] = __('Add-on included successfully.','Shopp');
	}
	
	function install ($package) {

		$this->init();
		$this->install_strings();

		$this->run(array(
					'package' => $package,
					'destination' => WP_PLUGIN_DIR,
					'clear_destination' => false, //Do not overwrite files.
					'clear_working' => true,
					'hook_extra' => array()
					));

		// Force refresh of plugin update information
		$Settings = ShoppSettings();
		$Settings->set('updates',false);

	}
	
	function addon_core_updates ($addons,$working_core) {
		$Settings = ShoppSettings();

		$this->init();
		$this->upgrade_strings();

		$current = $Settings->get('updates');

		add_filter('upgrader_destination_selection', array(&$this, 'destination_selector'), 10, 2);

		$all = count($addons);
		$i = 1;
		foreach ($addons as $addon) {

			// Get the URL to the zip file
			$this->addon = $addon->slug;
			
			$this->show_before = sprintf( '<h4>' . __('Updating addon %1$d of %2$d...') . '</h4>', $i++, $all );
			
			switch ($addon->type) {
				case "gateway": $addondir = '/shopp/gateways'; break;
				case "shipping": $addondir = '/shopp/shipping'; break;
				case "storage": $addondir = '/shopp/storage'; break;
				default: $addondir = '/';
			}
			
			$this->run(array(
						'package' => $addon->package,
						'destination' => $working_core.$addondir,
						'clear_working' => false,
						'with_core' => true,
						'hook_extra' => array(
							'addon' => $addon
						)
			));
		}
		
		// Cleanup our hooks, in case something else does an upgrade on this connection.
		remove_filter('upgrader_destination_selection', array(&$this, 'destination_selector'));

		if ( ! $this->result || is_wp_error($this->result) )
			return $this->result;
		
	}
	
	function upgrade ($addon,$type) {
		$Settings = ShoppSettings();

		$this->init();
		$this->upgrade_strings();

		switch ($type) {
			case "gateway": $this->addons_dir = SHOPP_GATEWAYS; break;
			case "shipping": $this->addons_dir = SHOPP_SHIPPING; break;
			case "storage": $this->addons_dir = SHOPP_STORAGE; break;
			default: $this->addons_dir = SHOPP_PLUGINDIR;
		}

		$current = $Settings->get('updates');
		if ( !isset( $current->response[ SHOPP_PLUGINFILE.'/addons' ][$addon] ) ) {
			$this->skin->set_result(false);
			$this->skin->error('up_to_date');
			$this->skin->after();
			return false;
		}

		// Get the URL to the zip file
		$r = $current->response[ SHOPP_PLUGINFILE.'/addons' ][$addon];
		$this->addon = $r->slug;

		add_filter('upgrader_destination_selection', array(&$this, 'destination_selector'), 10, 2);

		$this->run(array(
					'package' => $r->package,
					'destination' => $this->addons_dir,
					'clear_destination' => true,
					'clear_working' => true,
					'hook_extra' => array(
						'addon' => $addon
					)
		));

		// Cleanup our hooks, in case something else does an upgrade on this connection.
		remove_filter('upgrader_destination_selection', array(&$this, 'destination_selector'));

		if ( ! $this->result || is_wp_error($this->result) )
			return $this->result;

		// Force refresh of plugin update information
		$Settings->set('updates',false);
	}
	
	function run ($options) {
		global $wp_filesystem;
		$defaults = array( 	'package' => '', //Please always pass this.
							'destination' => '', //And this
							'clear_destination' => false,
							'clear_working' => true,
							'is_multi' => false,
							'with_core' => false,
							'hook_extra' => array() //Pass any extra $hook_extra args here, this will be passed to any hooked filters.
						);

		$options = wp_parse_args($options, $defaults);
		extract($options);

		//Connect to the Filesystem first.
		$res = $this->fs_connect( array(WP_CONTENT_DIR, $destination) );
		if ( ! $res ) //Mainly for non-connected filesystem.
			return false;

		if ( is_wp_error($res) ) {
			$this->skin->error($res);
			return $res;
		}

		if ( !$with_core ) // call $this->header separately if running multiple times
			$this->skin->header();

		$this->skin->before();

		//Download the package (Note, This just returns the filename of the file if the package is a local file)
		$download = $this->download_package( $package );
		if ( is_wp_error($download) ) {
			$this->skin->error($download);
			return $download;
		}

		//Unzip's the file into a temporary directory
		$working_dir = $this->unpack_package( $download,true,($with_core)?false:true );
		if ( is_wp_error($working_dir) ) {
			$this->skin->error($working_dir);
			return $working_dir;
		}
		
		// Determine the final destination
		$source_files = array_keys( $wp_filesystem->dirlist($working_dir) );
		if ( 1 == count($source_files)) {
			$this->destination = $source_files[0];
			if ($wp_filesystem->is_dir(trailingslashit($destination) . trailingslashit($source_files[0])))
				$destination = trailingslashit($destination) . trailingslashit($source_files[0]);
			// else $destination = trailingslashit($destination) . $source_files[0];
		}

		//With the given options, this installs it to the destination directory.
		$result = $this->install_package( array(
											'source' => $working_dir,
											'destination' => $destination,
											'clear_destination' => $clear_destination,
											'clear_working' => $clear_working,
											'hook_extra' => $hook_extra
										) );

		$this->skin->set_result($result);

		if ( is_wp_error($result) ) {
			$this->skin->error($result);
			$this->skin->feedback('process_failed');
		} else {
			// Install Suceeded
			if ($with_core) $this->skin->feedback('include_success');
			else $this->skin->feedback('process_success');
		}

		if ( !$with_core ) {
			$this->skin->after();
			$this->skin->footer();
		}

		return $result;
	}
	
	function plugin_info () {
		if ( ! is_array($this->result) )
			return false;
		if ( empty($this->result['destination_name']) )
			return false;

		$plugin = get_plugins('/' . $this->result['destination_name']); //Ensure to pass with leading slash
		if ( empty($plugin) )
			return false;

		$pluginfiles = array_keys($plugin); //Assume the requested plugin is the first in the list

		return $this->result['destination_name'] . '/' . $pluginfiles[0];
	}	

	function install_package ($args = array()) {
		global $wp_filesystem;
		$defaults = array( 'source' => '', 'destination' => '', //Please always pass these
						'clear_destination' => false, 'clear_working' => false,
						'hook_extra' => array());

		$args = wp_parse_args($args, $defaults);
		extract($args);

		@set_time_limit( 300 );

		if ( empty($source) || empty($destination) )
			return new WP_Error('bad_request', $this->strings['bad_request']);

		$this->skin->feedback('installing_package');

		$res = apply_filters('upgrader_pre_install', true, $hook_extra);
		if ( is_wp_error($res) )
			return $res;

		//Retain the Original source and destinations
		$remote_source = $source;
		$local_destination = $destination;

		$source_isdir = true;
		$source_files = array_keys( $wp_filesystem->dirlist($remote_source) );
		$remote_destination = $wp_filesystem->find_folder($local_destination);
		
		//Locate which directory to copy to the new folder, This is based on the actual folder holding the files.
		if ( 1 == count($source_files) && $wp_filesystem->is_dir( trailingslashit($source) . $source_files[0] . '/') ) //Only one folder? Then we want its contents. 
			$source = trailingslashit($source) . trailingslashit($source_files[0]);
		elseif ( count($source_files) == 0 )
				return new WP_Error('bad_package', $this->strings['bad_package']); //There are no files?
		else $source_isdir = false; //Its only a single file, The upgrader will use the foldername of this file as the destination folder. foldername is based on zip filename.

		//Hook ability to change the source file location..
		$source = apply_filters('upgrader_source_selection', $source, $remote_source, $this);
		if ( is_wp_error($source) )
			return $source;

		//Has the source location changed? If so, we need a new source_files list.
		if ( $source !== $remote_source )
			$source_files = array_keys( $wp_filesystem->dirlist($source) );

		//Protection against deleting files in any important base directories.
		if (in_array( $destination, array(ABSPATH, WP_CONTENT_DIR, WP_PLUGIN_DIR, WP_CONTENT_DIR . '/themes',SHOPP_GATEWAYS,SHOPP_SHIPPING,SHOPP_STORAGE) ) && $source_isdir) {
			$remote_destination = trailingslashit($remote_destination) . trailingslashit(basename($source));
			$destination = trailingslashit($destination) . trailingslashit(basename($source));
		}
				
		// Clear destination
		if ( $wp_filesystem->is_dir($remote_destination) && $source_isdir ) {
			if ( $clear_destination ) {
				//We're going to clear the destination if theres something there
				$this->skin->feedback('remove_old');
				$removed = $wp_filesystem->delete($remote_destination, true);
				$removed = apply_filters('upgrader_clear_destination', $removed, $local_destination, $remote_destination, $hook_extra);

				if ( is_wp_error($removed) )
					return $removed;
				else if ( ! $removed )
					return new WP_Error('remove_old_failed', $this->strings['remove_old_failed']);
			} else {
				//If we're not clearing the destination folder and something exists there allready, Bail.
				//But first check to see if there are actually any files in the folder.
				$_files = $wp_filesystem->dirlist($remote_destination);
				if ( ! empty($_files) ) {
					$wp_filesystem->delete($remote_source, true); //Clear out the source files.
					return new WP_Error('folder_exists', $this->strings['folder_exists'], $remote_destination );
				}
			}
		}
		
		// Create destination if needed
		if (!$wp_filesystem->exists($remote_destination) && $source_isdir) {
			if (!$wp_filesystem->mkdir($remote_destination, FS_CHMOD_DIR) )
				return new WP_Error('mkdir_failed', $this->strings['mkdir_failed'], $remote_destination);
		}

		// Copy new version of item into place.
		$result = copy_dir($source, $remote_destination);
		if ( is_wp_error($result) ) {
			if ( $clear_working )
				$wp_filesystem->delete($remote_source, true);
			return $result;
		}

		//Clear the Working folder?
		if ( $clear_working )
			$wp_filesystem->delete($remote_source, true);

		$destination_name = basename( str_replace($local_destination, '', $destination) );
		if ( '.' == $destination_name )
			$destination_name = '';

		$this->result = compact('local_source', 'source', 'source_name', 'source_files', 'destination', 'destination_name', 'local_destination', 'remote_destination', 'clear_destination', 'delete_source_dir');

		$res = apply_filters('upgrader_post_install', true, $hook_extra, $this->result);
		if ( is_wp_error($res) ) {
			$this->result = $res;
			return $res;
		}

		//Bombard the calling function will all the info which we've just used.
		return $this->result;
	}

	function source_selector ($source, $remote_source) {
		global $wp_filesystem;
		
		$source_files = array_keys( $wp_filesystem->dirlist($source) );
		if (count($source_files) == 1) $source = trailingslashit($source).$source_files[0];

		return $source;
	}

	function destination_selector ($destination, $remote_destination) {
		global $wp_filesystem;
		
		if (strpos(basename($destination),'.tmp') !== false)
			$destination = trailingslashit(dirname($destination));
			
		return $destination;
	}	
	
}

/**
 * Shopp_Upgrader_Skin class
 * 
 * Shopp-ifies the auto-upgrade process.
 * 
 * Extensions derived from the WordPress Plugin_Upgrader_Skin class:
 * @see wp-admin/includes/class-wp-upgrader.php
 * 
 * @copyright WordPress {@link http://codex.wordpress.org/Copyright_Holders}
 * @author Jonathan Davis
 * @since 1.1
 * @package shopp
 * @subpackage installation
 **/
class Shopp_Upgrader_Skin extends Plugin_Upgrader_Skin {

	/**
	 * Custom heading for Shopp
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void Description...
	 **/
	function header() {
		if ( $this->done_header )
			return;
		$this->done_header = true;
		echo '<div class="wrap shopp">';
		echo screen_icon();
		echo '<h2>' . $this->options['title'] . '</h2>';
	}
	
	/**
	 * Displays a return to plugins page button after installation
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function after() {
		$this->feedback('<a href="' . admin_url('plugins.php') . '" title="' . esc_attr__('Return to Plugins page') . '" target="_parent" class="button-secondary">' . __('Return to Plugins page') . '</a>');
	}
	
} // END class Shopp_Upgrader_Skin

?>