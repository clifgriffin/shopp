<?php
/**
 * Support.php
 *
 * Shopp Support class for shopplugin.com resources
 *
 * @author Jonathan Davis
 * @copyright Ingenesis Limited, May 2013
 * @license (@see license.txt)
 * @package shopp
 * @version 1.0
 * @since 1.3
 **/

defined( 'WPINC' ) || header( 'HTTP/1.1 403' ) & exit; // Prevent direct access

class ShoppSupport {

	const HOMEPAGE  = 'https://shopplugin.com/';
	const WORKSHOPP = 'https://workshopp.com/';
	const SUPPORT   = 'https://shopplugin.com/support/';
	const FORUMS    = 'https://shopplugin.com/forums/';
	const STORE     = 'https://shopplugin.com/store/';
	const DOCS      = 'https://shopplugin.com/docs/';
	const API       = 'https://shopplugin.com/api/';
	const KB        = 'https://shopplugin.com/kb/';

	/**
	 * Checks for available updates
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return array List of available updates
	 **/
	public static function updates () {

		if ( ! wp_next_scheduled('shopp_check_updates') )
			wp_schedule_event(time(), 'twicedaily', 'shopp_check_updates');

		global $pagenow;
		if ( is_admin()
			&& 'plugins.php' == $pagenow
			&& isset($_GET['action'])
			&& 'deactivate' == $_GET['action']) return array();

		$updates = new StdClass();
		if (function_exists('get_site_transient')) $plugin_updates = get_site_transient('update_plugins');
		else $plugin_updates = get_transient('update_plugins');

		switch ( current_filter() ) {
			case 'load-update-core.php': $timeout = 60; break; // 1 minute
			case 'load-plugins.php': // 1 hour
			case 'load-update.php': $timeout = 3600; break;
			default: $timeout = 43200; // 12 hours
		}

		$justchecked = isset( $plugin_updates->last_checked_shopp ) && $timeout > ( time() - $plugin_updates->last_checked_shopp );
		$changed = isset($plugin_updates->response[ SHOPP_PLUGINFILE ]);
		if ( $justchecked && ! $changed ) return;

		$Shopp = Shopp::object();
		$addons = array_merge(
			$Shopp->Gateways->checksums(),
			$Shopp->Shipping->checksums(),
			$Shopp->Storage->checksums()
		);

		$request = array('ShoppServerRequest' => 'update-check');
		/**
		 * Update checks collect environment details for faster support service only,
		 * none of it is linked to personally identifiable information.
		 **/
		$data = array(
			'core' => ShoppVersion::release(),
			'addons' => join("-", $addons),
			'site' => get_bloginfo('url'),
		);

		if ( shopp_setting_enabled('support_data') ) {
			$optional = array(
				'wp' => get_bloginfo('version').(is_multisite()?' (multisite)':''),
				'mysql' => mysql_get_server_info(),
				'php' => phpversion(),
				'uploadmax' => ini_get('upload_max_filesize'),
				'postmax' => ini_get('post_max_size'),
				'memlimit' => ini_get('memory_limit'),
				'server' => $_SERVER['SERVER_SOFTWARE'],
				'agent' => $_SERVER['HTTP_USER_AGENT']
			);
			$data = array_merge($data, $optional);
		}

		$response = ShoppSupport::callhome($request, $data);

		if ($response == '-1') return; // Bad response, bail
		$response = unserialize($response);
		unset($updates->response);

		if ( isset($response->key) && ! Shopp::str_true($response->key) )
			delete_transient('shopp_activation');

		if ( isset($response->addons) ) {
			$updates->response[ SHOPP_PLUGINFILE . '/addons' ] = $response->addons;
			unset($response->addons);
		}

		if ( isset($response->id) )
			$updates->response[ SHOPP_PLUGINFILE ] = $response;

		if (isset($updates->response)) {
			shopp_set_setting('updates', $updates);

			// Add Shopp to the WP plugin update notification count
			if ( isset($updates->response[ SHOPP_PLUGINFILE ]) )
				$plugin_updates->response[ SHOPP_PLUGINFILE ] = $updates->response[ SHOPP_PLUGINFILE ];

		} else unset($plugin_updates->response[ SHOPP_PLUGINFILE ]); // No updates, remove Shopp from the plugin update count

		$plugin_updates->last_checked_shopp = time();
		if ( function_exists('set_site_transient') ) set_site_transient('update_plugins', $plugin_updates);
		else set_transient('update_plugins', $plugin_updates);

		return $updates;
	}

	/**
	 * Loads the change log for an available update
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	public static function changelog () {
		if ( 'shopp' != $_REQUEST['plugin'] ) return;

		$request = array('ShoppServerRequest' => 'changelog');

		if ( isset($_GET['core']) && ! empty($_GET['core']) )
			$request['core'] = $_GET['core'];

		if ( isset($_GET['addon']) && ! empty($_GET['addon']) )
			$request['addons'] = $_GET['addon'];

		$data = array();
		$response = ShoppSupport::callhome($request, $data);

		include SHOPP_ADMIN_PATH . '/help/changelog.php';
		exit;
	}


	public static function pluginsnag ( $file, $plugin_data ) {

		if ( self::earlyupdates() ) return;

		$current = get_site_transient( 'update_plugins' );
		if ( isset( $current->response[ SHOPP_PLUGINFILE ] ) ) return;

		if ( is_network_admin() || ! is_multisite() ) {
		$wp_list_table = _get_list_table('WP_Plugins_List_Table');
			echo '<tr class="plugin-update-tr"><td colspan="' . $wp_list_table->get_column_count() . '" class="plugin-update colspanchange"><div class="update-message">';
			echo self::buykey();
			echo '<style type="text/css">#shopp th,#shopp td{border-bottom:0;}</style>';
			echo '</div></td></tr>';
		}

	}

	public static function addons ( $meta, $plugin) {
		if ( SHOPP_PLUGINFILE != $plugin ) return $meta;

		$Shopp = Shopp::object();
		$builtin = array(
			'Shopp2Checkout', 'ShoppPayPalStandard', 'ShoppOfflinePayment', 'ShoppTestMode',
			'FreeOption', 'ItemQuantity', 'ItemRates', 'OrderAmount', 'OrderRates', 'OrderWeight', 'PercentageAmount',
			'DBStorage', 'FSStorage'
		);
		$builtin = array_flip($builtin);

		$modules = array_merge(
			$Shopp->Gateways->modules,
			$Shopp->Shipping->modules,
			$Shopp->Storage->modules
		);

		$installed = array_diff_key($modules, $builtin);

		if ( empty($installed) ) return $meta;
		$label = Shopp::_mi('**Add-ons:**');
		foreach ( $installed as $addon ) {
			$entry = array($label, $addon->name, $addon->version);
			if ( $label ) $label = '';
			$meta[] = trim(join(' ', $entry));
		}
		return $meta;
	}

	public static function earlyupdates () {
		$updates = shopp_setting('updates');

		$core = isset($updates->response[ SHOPP_PLUGINFILE ]) ? $updates->response[ SHOPP_PLUGINFILE ] : false;
		$addons = isset($updates->response[ SHOPP_PLUGINFILE . '/addons' ]) ? $updates->response[ SHOPP_PLUGINFILE . '/addons'] : false;

		if ( ! $core && ! $addons ) return false;

		$plugin_name = 'Shopp';
		$plugin_slug = strtolower($plugin_name);
		$store_url = ShoppSupport::STORE;
		$account_url = "$store_url/account/";

		$updates = array();

		if ( ! empty($core)	// Core update available
				&& isset($core->new_version)	// New version info available
				&& version_compare($core->new_version, ShoppVersion::release(), '>') // New version is greater than current version
			) {
			$details_url = admin_url('plugin-install.php?tab=plugin-information&plugin=' . $plugin_slug . '&core=' . $core->new_version . '&TB_iframe=true&width=600&height=800');

			$updates[] = Shopp::_mi('%2$s Shopp %1$s is available %3$s from shopplugin.com now.', $core->new_version, '<a href="' . $details_url . '" class="thickbox" title="' . esc_attr($plugin_name) . '">', '</a>');
		}

	    if ( ! empty($addons) ) {
			// Addon update messages
			$addonupdates = array();
			foreach ( (array)$addons as $addon )
				$addonupdates[] = $addon->name . ' ' . $addon->new_version;

			if ( count($addons) > 1 ) {
				$last = array_pop($addonupdates);
				$updates[] = Shopp::_mi('Add-on updates are available for %s &amp; %s.', join(', ', $addonupdates), $last);
			} elseif ( count($addons) == 1 )
				$updates[] = Shopp::_mi('An add-on update is available for %s.', $addonupdates[0]);
		}

		if ( is_network_admin() || ! is_multisite() ) {

			$wp_list_table = _get_list_table('WP_Plugins_List_Table');
			echo '<tr class="plugin-update-tr"><td colspan="' . $wp_list_table->get_column_count() . '" class="plugin-update colspanchange"><div class="update-message">';
			Shopp::_emi(
				'You&apos;re missing out on important updates! %1$s &nbsp; %2$s Buy a Shopp Support Key! %3$s', empty($updates) ? '' : join(' ', $updates), '<a href="' . ShoppSupport::STORE . '" class="button button-primary">', '</a>'
			);
			echo '<style type="text/css">#shopp th,#shopp td{border-bottom:0;}</style>';
			echo '</div></td></tr>';
		}


		return true;
	}

	public static function wpupdate ( $file, $plugin_data ) {
		echo ' '; echo self::buykey();
	}

	public static function reminder () {
		$userid = get_current_user_id();

		$lasttime = (int)get_user_meta($userid, 'shopp_nonag');
		$dismissed = ( current_time('timestamp') - $lasttime ) < ( rand(2,5) * 86400 );
		if ( ! current_user_can('shopp_settings') || ShoppSupport::activated() || $dismissed ) return '';

		$url = add_query_arg('action', 'shopp_nonag', wp_nonce_url(admin_url('admin-ajax.php'), 'wp_ajax_shopp_nonag'));
		$_ = array();
		$_[] = '<div id="shopp-activation-nag" class="notice wp-core-ui">';

		if ( ! $setupscreen ) $_[] = '<p class="dismiss shoppui-remove-sign alignright"></p>';

		$_[] = '<p class="nag">' . self::buykey() . '</p>';
		$_[] = '</div>';

		$_[] = '<script type="text/javascript">';
		$_[] = 'jQuery(document).ready(function($){var id="#shopp-activation-nag",el=$(id).click(function(){window.open($(this).find("a").attr("href"),"_blank");}).find(".dismiss").click(function(){$(id).remove();$.ajax(\'' . $url . '\');});});';
		$_[] = '</script>';
		return join('', $_);
	}

	public static function buykey () {
		return Shopp::_mi('You&apos;re missing out on **expert support**, **early access** to Shopp updates, and **one-click add-on updates**! Don&apos;t have a Shopp Support Key? %sBuy a Shopp Support Key!%s', '<a href="' . ShoppSupport::STORE . '" class="button button-primary" target="_blank">', '</a>');
	}

	/**
	 * Communicates with the Shopp update service server
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param array $request (optional) A list of request variables to send
	 * @param array $data (optional) A list of data variables to send
	 * @param array $options (optional)
	 * @return string The response from the server
	 **/
	public static function callhome ($request=array(), $data=array(), $options=array()) {
		$query = http_build_query(array_merge(array('ver'=>'1.2'), $request), '', '&');
		$data = http_build_query($data, '', '&');

		$defaults = array(
			'method' => 'POST',
			'timeout' => 20,
			'redirection' => 7,
			'httpversion' => '1.0',
			'user-agent' => SHOPP_GATEWAY_USERAGENT.'; '.get_bloginfo( 'url' ),
			'blocking' => true,
			'headers' => array(),
			'cookies' => array(),
			'body' => $data,
			'compress' => false,
			'decompress' => true,
			'sslverify' => false
		);
		$params = array_merge($defaults, $options);

		$URL = ShoppSupport::HOMEPAGE . "?$query";
		// error_log('CALLHOME REQUEST ------------------');
		// error_log($URL);
		// error_log(json_encode($params));
		$connection = new WP_Http();
		$result = $connection->request($URL, $params);
		// error_log(json_encode($result));
		// error_log('-------------- END CALLHOME REQUEST');
		extract($result);

		if ( isset($response['code']) && 200 != $response['code'] ) { // Fail, fallback to http instead
			$URL = str_replace('https://', 'http://', $URL);
			$connection = new WP_Http();
			$result = $connection->request($URL, $params);
			extract($result);
		}

		if ( is_wp_error($result) ) {
			$errors = array(); foreach ($result->errors as $errname => $msgs) $errors[] = join(' ', $msgs);
			$errors = join(' ', $errors);

			shopp_add_error("Shopp: ".Lookup::errors('callhome', 'fail')." $errors ".Lookup::errors('contact', 'admin')." (WP_HTTP)", SHOPP_ADMIN_ERR);

			return false;
		} elseif ( empty($result) || !isset($result['response']) ) {
			shopp_add_error("Shopp: ".Lookup::errors('callhome', 'noresponse'), SHOPP_ADMIN_ERR);
			return false;
		} else extract($result);

		if ( isset($response['code']) && 200 != $response['code'] ) {
			$error = Lookup::errors('callhome', 'http-'.$response['code']);
			if (empty($error)) $error = Lookup::errors('callhome', 'http-unkonwn');
			shopp_add_error("Shopp: $error", 'callhome_comm_err', SHOPP_ADMIN_ERR);
			return $body;
		}

		return $body;

	}

	/**
	 * Checks if the support key is activated
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @return boolean True if activated, false otherwise
	 **/
	public static function activated () {
		if ( class_exists('ShoppSupportKey', false) )
			return ShoppSupportKey::activated();
		return false;
	}

} // END class ShoppSupport