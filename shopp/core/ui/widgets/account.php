<?php
/**
 * ShoppCartWidget class
 * A WordPress widget for showing a drilldown search menu for category products
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 8 June, 2009
 * @package shopp
 **/

if (class_exists('WP_Widget')) {
	
class ShoppAccountWidget extends WP_Widget {

    function ShoppAccountWidget() {
        parent::WP_Widget(false, 
			$name = __('Shopp Account','Shopp'), 
			array('description' => __('Account login &amp; management','Shopp'))
		);
    }

    function widget($args, $options) {		
		global $Shopp;
		if (!empty($args)) extract($args);
		
		if (empty($options['title'])) $options['title'] = __('Your Account','Shopp');
		$title = $before_title.$options['title'].$after_title;
		$request = $_GET;
		unset($_GET['acct']);
		unset($_GET['id']);
		remove_filter('shopp_account_template','shoppdiv');
		add_filter('shopp_show_account_errors',array(&$this,'showerrors'));
		$sidecart = $Shopp->Flow->Controller->account_page();

		echo $before_widget.$title.$sidecart.$after_widget;
		$_GET = array_merge($_GET,$request);
		remove_filter('shopp_show_account_errors',array(&$this,'showerrors'));
		add_filter('shopp_account_template','shoppdiv');
    }

    function update($new_instance, $old_instance) {				
        return $new_instance;
    }

	function showerrors () {
		return false;
	}

    function form($options) {				
		?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title'); ?></label>
		<input type="text" name="<?php echo $this->get_field_name('title'); ?>" id="<?php echo $this->get_field_id('title'); ?>" class="widefat" value="<?php echo $options['title']; ?>"></p>
		<?php
    }

} // END class ShoppAccountWidget

global $Shopp;
if ($Shopp->Settings->get('account_system') == "none") return;

register_widget('ShoppAccountWidget');

}