<?php
/**
 * Styles.php
 *
 * Controller for browser stylesheet queueing and delivery
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, May 2014
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @since 1.0
 **/

defined( 'WPINC' ) || header( 'HTTP/1.1 403' ) & exit; // Prevent direct access

/** From BackPress */
if ( ! class_exists('WP_Scripts') ) {
	require( ABSPATH . WPINC . '/class.wp-dependencies.php' );
	require( ABSPATH . WPINC . '/class.wp-styles.php' );
}

add_action('shopp_default_styles', array('ShoppStyles', 'defaults'));

class ShoppStyles extends WP_Styles {

	public function __construct() {

		do_action('shopp_default_styles', $this);

		// add_action('wp_enqueue_scripts', array($this, 'wp_dependencies'), 1);
		// add_action('admin_head', array($this, 'wp_dependencies'), 1);

		add_action('wp_head', array($this, 'dostyles'), 8);
		add_action('admin_print_styles', array($this, 'dostyles'), 15);

	}

	public static function defaults ( ShoppStyles $styles ) {
		$script = basename(__FILE__);
		$schema = ( ! empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) != 'off' ) ? 'https://' : 'http://';
		if ( defined('SHOPP_PLUGINURI') ) $url = SHOPP_PLUGINURI . '/core';
		else $url = preg_replace("|$script.*|i", '', $schema . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);

		$styles->base_url = $url;

		// Short checksum for cache control that changes with Shopp versions while masking it somewhat
		$styles->default_version = hash('crc32b', ABSPATH . ShoppVersion::release());
		$styles->default_dirs = array('/ui/styles/');

		$styles->add('admin', '/ui/styles/admin.css');
		$styles->add('admin-rtl', '/ui/styles/rtl.css');
		$styles->add('backmenu', '/ui/styles/backmenu.css');
		$styles->add('catalog', '/ui/styles/catalog.css');
		$styles->add('colorbox', '/ui/styles/colorbox.css');
		$styles->add('dashboard', '/ui/styles/dashboard.css');
		$styles->add('icons', '/ui/styles/icons.css');
		$styles->add('menus', '/ui/styles/menu.css');
		$styles->add('welcome', '/ui/styles/welcome.css'); // Fix icons

	}

	public function dostyles () {
		global $concatenate_scripts;

		if ( ! did_action('shopp_print_styles') )
			do_action('shopp_print_styles');

		$this->do_concat = defined('CONCATENATE_SCRIPTS') ? CONCATENATE_SCRIPTS : true;

		$this->do_items();

		if ( ! empty($this->do_concat) )
			echo $this->linked();

		$this->reset();

		return $this->done;
	}

	public function linked () {
			global $compress_scripts;

			$zip = $compress_scripts ? 1 : 0;
			if ( $zip && defined('ENFORCE_GZIP') && ENFORCE_GZIP )
				$zip = 'gzip';

			$debug = (int)( defined('SCRIPT_DEBUG') && SCRIPT_DEBUG );

			if ( ! empty($this->concat) ) {

				$url = trailingslashit(get_bloginfo('url'));
				$script = SHOPP_PLUGINURI . '/services/styles.php';
				$ver = hash('crc32b', "$this->concat_version");
				$stylesheets = trim($this->concat, ', ');

				if ( shopp_setting('script_server') == 'plugin' ) {
					$url = add_query_arg('scss', $stylesheets, $url);
				} else $url = add_query_arg('load', $stylesheets, $script);

				$href = add_query_arg(array(
					'c' => $zip,
					'ver' => $ver,
					'debug' => $debug
				), $url);

				echo "<link rel='stylesheet' href='" . esc_attr($href) . "' type='text/css' media='all' />\n";

			}

			if ( ! empty($this->print_html) )
				echo $this->print_html;
	}

	public function in_default_dir($src) {
		if ( ! $this->default_dirs )
			return true;

		foreach ( (array) $this->default_dirs as $test ) {
			if ( false !== strpos($src, $test) )
				return true;
		}
		return false;
	}

}