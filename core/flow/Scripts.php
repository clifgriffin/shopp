<?php
/**
 * Scripts.php
 *
 * Controller for browser script queueing and delivery
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, May  5, 2010
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @since 1.0
 * @subpackage scripts
 **/

/**
 * Scripts
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package shopp
 **/
/** From BackPress */
if (!class_exists('WP_Scripts')) {
	require( ABSPATH . WPINC . '/class.wp-dependencies.php' );
	require( ABSPATH . WPINC . '/class.wp-scripts.php' );
}

class ShoppScripts extends WP_Scripts {

	function __construct() {
		do_action_ref_array( 'shopp_default_scripts', array(&$this) );

		add_action('wp_enqueue_scripts', array(&$this,'wp_dependencies'),1);
		add_action('admin_head', array(&$this,'wp_dependencies'),1);

		add_action('wp_head', array(&$this,'print_head_scripts'),15);
		add_action('admin_head', array(&$this,'print_head_scripts'),15);
		add_action('wp_footer', array(&$this,'print_footer_scripts'),15);
		add_action('admin_footer', array(&$this,'print_footer_scripts'),15);

	}

	function do_item( $handle, $group = false ) {
		if(parent::do_item($handle,$group))
			$this->print_code .= $this->print_script_custom($handle);
	}

	function print_head_scripts() {
		global $concatenate_scripts;

		if ( ! did_action('shopp_print_scripts') )
			do_action('shopp_print_scripts');

		script_concat_settings();
		$this->do_concat = $concatenate_scripts;
		$this->do_head_items();

		if ( apply_filters('shopp_print_head_scripts', true) )
			$this->print_script_request();

		$this->reset();
		return $this->done;
	}

	function print_footer_scripts() {
		global $concatenate_scripts;

		if ( ! did_action('shopp_print_footer_scripts') )
			do_action('shopp_print_footer_scripts');

		script_concat_settings();
		$concatenate_scripts = defined('CONCATENATE_SCRIPTS') ? CONCATENATE_SCRIPTS : true;
		$this->do_concat = $concatenate_scripts;
		$this->do_footer_items();

		if ( apply_filters('shopp_print_footer_scripts', true) )
			$this->print_script_request();

		$this->reset();
		return $this->done;
	}

	function print_script_request () {
		global $compress_scripts;

		$zip = $compress_scripts ? 1 : 0;
		if ( $zip && defined('ENFORCE_GZIP') && ENFORCE_GZIP )
			$zip = 'gzip';

		if ( !empty($this->concat) ) {
			$ver = md5("$this->concat_version");
			if (shopp_setting('script_server') == 'plugin') {
				$src = trailingslashit(get_bloginfo('url')) . "?sjsl=" . trim($this->concat, ', ') . "&c={$zip}&ver=$ver";
				if (is_ssl()) $src = str_replace('http://','https://',$src);
			} else $src = $this->base_url . "scripts.php?c={$zip}&load=" . trim($this->concat, ', ') . "&ver=$ver";
			echo "<script type='text/javascript' src='" . esc_attr($src) . "'></script>\n";
		}

		if ( !empty($this->print_code) ) {
			echo "<script type='text/javascript'>\n";
			echo "/* <![CDATA[ */\n";
			echo $this->print_code;
			echo "/* ]]> */\n";
			echo "</script>\n";
		}

		if ( !empty($this->print_html) )
			echo $this->print_html;
	}
	function print_scripts_l10n( $handle, $echo = true ) {
		if ( empty($this->registered[$handle]->extra['l10n']) || empty($this->registered[$handle]->extra['l10n'][0]) || !is_array($this->registered[$handle]->extra['l10n'][1]) )
			return false;

		$object_name = $this->registered[$handle]->extra['l10n'][0];

		$data = "var $object_name = {";
		$eol = '';
		foreach ( $this->registered[$handle]->extra['l10n'][1] as $var => $val ) {
			if ( 'l10n_print_after' == $var ) {
				$after = $val;
				continue;
			}
			$data .= "$eol$var: \"" . esc_js( $val ) . '"';
			$eol = ",";
		}
		$data .= "};\n";
		$data .= isset($after) ? "$after\n" : '';

		if ( $echo ) {
			echo "<script type='text/javascript'>\n";
			echo "/* <![CDATA[ */\n";
			echo $data;
			echo "/* ]]> */\n";
			echo "</script>\n";
			return true;
		} else {
			return $data;
		}
	}


	function all_deps( $handles, $recursion = false, $group = false ) {
		$r = parent::all_deps( $handles, $recursion );
		if ( !$recursion )
			$this->to_do = apply_filters( 'shopp_print_scripts_array', $this->to_do );
		return $r;
	}

	function wp_dependencies () {
		global $wp_scripts;

		if ( !is_a($wp_scripts, 'WP_Scripts') )
			$wp_scripts = new WP_Scripts();

		$wpscripts = array_keys($wp_scripts->registered);

		$deps = array();
		foreach ($this->queue as $handle) {
			if (!isset($this->registered[$handle]) || !isset($this->registered[$handle]->deps)) continue;
			$wpdeps = array_intersect($this->registered[$handle]->deps,$wpscripts);
			$mydep = array_diff($this->registered[$handle]->deps,$wpdeps);
			$this->registered[$handle]->deps = $mydep;
		}

		if (!empty($wpdeps)) foreach ((array)$wpdeps as $handle) wp_enqueue_script($handle);

	}

	function print_script_custom ($handle) {
		return !empty($this->registered[$handle]->extra['code'])?$this->registered[$handle]->extra['code']:false;
	}


} // END class ShoppScripts

function shopp_default_scripts (&$scripts) {

	$script = basename(__FILE__);
	$schema = ( !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) != 'off' ) ? 'https://' : 'http://';
	if (defined('SHOPP_PLUGINURI')) $url = SHOPP_PLUGINURI.'/core'.'/';
	else $url = preg_replace("|$script.*|i", '', $schema . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);

	$scripts->base_url = $url;
	$scripts->default_version = mktime(false,false,false,1,1,2010);
	$scripts->default_dirs = array('/ui/behaviors/','/ui/products');

	// $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '_dev' : '';

	$scripts->add('shopp', '/ui/behaviors/shopp.js', array('jquery'), '20100101');
	$scripts->add_data('shopp', 'group', 1);

	$scripts->add('jquery-tmpl', '/ui/behaviors/jquery/jquery.tmpl.js', array('jquery'), '20110401');
	$scripts->add_data('jquery-tmpl', 'group', 1);

	$scripts->add('address', '/ui/behaviors/address.js', array('jquery','shopp'), '20100101');
	$scripts->add_data('address', 'group', 1);

	$scripts->add('cart', '/ui/behaviors/cart.js', array('jquery','shopp'), '20100101');
	$scripts->add_data('cart', 'group', 1);

	$scripts->add('catalog', '/ui/behaviors/catalog.js', array('jquery','shopp'), '20100101');
	$scripts->add_data('catalog', 'group', 1);

	$scripts->add('calendar', '/ui/behaviors/calendar.js', array('jquery','shopp'), '20100101');
	$scripts->add_data('calendar', 'group', 1);

	$scripts->add('checkout', '/ui/behaviors/checkout.js', array('jquery','shopp'), '20100101');
	$scripts->add_data('checkout', 'group', 1);

	$scripts->add('colorbox', '/ui/behaviors/colorbox.js', array('jquery'), '20100101');
	$scripts->add_data('colorbox', 'group', 1);

	$scripts->add('ocupload', '/ui/behaviors/ocupload.js', array('jquery'), '20100101');
	$scripts->add_data('ocupload', 'group', 1);

	$scripts->add('orders', '/ui/behaviors/orders.js', array('jquery'), '20100101');
	$scripts->add_data('orders', 'group', 1);

	$scripts->add('scalecrop', '/ui/behaviors/scalecrop.js', array('jquery','jquery-ui-core','jquery-ui-draggable'), '20100101');
	$scripts->add_data('scalecrop', 'group', 1);

	$scripts->add('priceline', '/ui/behaviors/priceline.js', array('jquery','shopp'), '20100101');
	$scripts->add_data('priceline', 'group', 1);

	$scripts->add('editors', '/ui/behaviors/editors.js', array('jquery','jquery-ui-sortable'), '20100101');
	$scripts->add_data('editors', 'group', 1);

	$scripts->add('product-editor', '/ui/products/editor.js', array('jquery','priceline'), '20100101');
	$scripts->add_data('product-editor', 'group', 1);

	$scripts->add('category-editor', '/ui/categories/category.js', array('jquery','priceline'), '20100101');
	$scripts->add_data('category-editor', 'group', 1);

	$scripts->add('category-arrange', '/ui/categories/arrange.js', array('jquery','shopp'), '20100101');
	$scripts->add_data('category-arrange', 'group', 1);

	$scripts->add('products-arrange', '/ui/categories/products.js', array('jquery'), '20100101');
	$scripts->add_data('products-arrange', 'group', 1);

	$scripts->add('setup', '/ui/behaviors/setup.js', array('jquery'), '20100101');
	$scripts->add_data('setup', 'group', 1);

	$scripts->add('pageset', '/ui/behaviors/pageset.js', array('jquery'), '20100101');
	$scripts->add_data('pageset', 'group', 1);

	$scripts->add('payments', '/ui/behaviors/payments.js', array('jquery'), '20100101');
	$scripts->add_data('payments', 'group', 1);

	$scripts->add('shiprates', '/ui/behaviors/shiprates.js', array('jquery'), '20100101');
	$scripts->add_data('shiprates', 'group', 1);

	$scripts->add('taxrates', '/ui/behaviors/taxrates.js', array('jquery'), '20110721');
	$scripts->add_data('taxrates', 'group', 1);

	$scripts->add('imageset', '/ui/behaviors/imageset.js', array('jquery'), '20110518');
	$scripts->add_data('imageset', 'group', 1);

	$scripts->add('system', '/ui/behaviors/system.js', array('jquery'), '20120307');
	$scripts->add_data('system', 'group', 1);

	$scripts->add('shopp-swfobject', '/ui/behaviors/swfupload/plugins/swfupload.swfobject.js', array(), '2202');
	$scripts->add_data('shopp-swfobject', 'group', 1);

	$scripts->add('shopp-swfupload-queue', '/ui/behaviors/swfupload/plugins/swfupload.queue.js', array(), '2202');
	$scripts->add_data('shopp-swfupload-queue', 'group', 1);

	$scripts->add('swfupload', '/ui/behaviors/swfupload/swfupload.js', array('jquery','shopp-swfobject'), '2202');
	$scripts->add_data('swfupload', 'group', 1);

	$scripts->add('suggest', '/ui/behaviors/suggest.js', array('jquery'), '20110330');
	$scripts->add_data('suggest', 'group', 1);

	$scripts->add('search-select', '/ui/behaviors/searchselect.js', array('jquery'), '20110401');
	$scripts->add_data('search-select', 'group', 1);

	$scripts->add('membership-editor', '/ui/memberships/editor.js', array('jquery','jquery-tmpl','search-select'), '20110401');
	$scripts->add_data('membership-editor', 'group', 1);

	$scripts->add('labelset', '/ui/behaviors/labelset.js', array('jquery','jquery-tmpl'), '20110508');
	$scripts->add_data('labelset', 'group', 1);

}

/**
 * Register new JavaScript file.
 */
function shopp_register_script( $handle, $src, $deps = array(), $ver = false, $in_footer = false ) {
	global $ShoppScripts;
	if ( !is_a($ShoppScripts, 'ShoppScripts') )
		$ShoppScripts = new ShoppScripts();

	$ShoppScripts->add( $handle, $src, $deps, $ver );
	if ( $in_footer )
		$ShoppScripts->add_data( $handle, 'group', 1 );
}

function shopp_localize_script( $handle, $object_name, $l10n ) {
	global $ShoppScripts;
	if ( !is_a($ShoppScripts, 'ShoppScripts') )
		return false;

	return $ShoppScripts->localize( $handle, $object_name, $l10n );
}

function shopp_custom_script ($handle, $code) {
	global $ShoppScripts;
	if ( !is_a($ShoppScripts, 'ShoppScripts') )
		return false;

	$code = !empty($ShoppScripts->registered[$handle]->extra['code'])?$ShoppScripts->registered[$handle]->extra['code'].$code:$code;
	return $ShoppScripts->add_data( $handle, 'code', $code );
}

/**
 * Remove a registered script.
 */
function shopp_deregister_script( $handle ) {
	global $ShoppScripts;
	if ( !is_a($ShoppScripts, 'ShoppScripts') )
		$ShoppScripts = new ShoppScripts();

	$ShoppScripts->remove( $handle );
}

/**
 * Enqueues script.
 *
 * Registers the script if src provided (does NOT overwrite) and enqueues.
*/
function shopp_enqueue_script( $handle, $src = false, $deps = array(), $ver = false, $in_footer = false ) {
	global $ShoppScripts;
	if ( !is_a($ShoppScripts, 'ShoppScripts') )
		$ShoppScripts = new ShoppScripts();

	if ( $src ) {
		$_handle = explode('?', $handle);
		$ShoppScripts->add( $_handle[0], $src, $deps, $ver );
		if ( $in_footer )
			$ShoppScripts->add_data( $_handle[0], 'group', 1 );
	}
	$ShoppScripts->enqueue( $handle );
}

/**
 * Check whether script has been added to WordPress Scripts.
 *
 * The values for list defaults to 'queue', which is the same as enqueue for
 * scripts.
 *
 * @param string $handle Handle used to add script.
 * @param string $list Optional, defaults to 'queue'. Others values are 'registered', 'queue', 'done', 'to_do'
 * @return bool
 */
function shopp_script_is( $handle, $list = 'queue' ) {
	global $ShoppScripts;
	if ( !is_a($ShoppScripts, 'ShoppScripts') )
		$ShoppScripts = new ShoppScripts();

	$query = $ShoppScripts->query( $handle, $list );

	if ( is_object( $query ) )
		return true;

	return $query;
}

/**
 * Handle Shopp script dependencies in the WP script queue
 *
 * @author Jonathan Davis
 * @since 1.1
 *
 * @return void
 **/
function shopp_dependencies () {
	global $ShoppScripts,$wp_scripts;
	if ( !is_a($ShoppScripts, 'ShoppScripts') )
		$ShoppScripts = new ShoppScripts();

	foreach ($wp_scripts->queue as $handle) {
		$deps = $wp_scripts->registered[$handle]->deps;
		$shoppdeps = array_intersect($deps,array_keys($ShoppScripts->registered));
		foreach ($shoppdeps as $key => $s_handle) {
			shopp_enqueue_script($s_handle);
			array_splice($deps,$key,1);
		}
		$wp_scripts->registered[$handle]->deps = $deps;
	}
}

add_action('shopp_default_scripts', 'shopp_default_scripts');

?>