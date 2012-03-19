<?php
/**
 * ShoppAccountWidget class
 * A WordPress widget to show the account login or account menu if logged in
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 8 June, 2009
 * @package shopp
 **/

if ( class_exists('WP_Widget') && ! class_exists('ShoppAccountWidget') ) {

class ShoppAccountWidget extends WP_Widget {

    function __construct() {
        parent::__construct(
			'shopp-account',
			__('Shopp Account','Shopp'),
			array('description' => __('Customer account management dashboard','Shopp'))
		);
    }

    function widget($args, $options) {
		if (!empty($args)) extract($args);

		$loggedin = ShoppCustomer()->logged_in();
		// Hide login form on account page when not logged in to prevent duplicate forms
		if (is_account_page() && !$loggedin) return '';

		$defaults = array(
			'title' => $loggedin?__('Your Account','Shopp'):__('Login','Shopp'),
		);
		$options = array_merge($defaults,$options);
		extract($options);

		$title = $before_title.$title.$after_title;


		remove_filter('shopp_show_account_errors',array(&$this,'showerrors'));
		$Page = new AccountStorefrontPage();

		$menu = $Page->content('','menu');
		echo $before_widget.$title.$menu.$after_widget;

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

if (shopp_setting('account_system') == "none") return;
register_widget('ShoppAccountWidget');

}
?>