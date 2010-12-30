<?php
/**
 * ShoppFacetedMenuWidget class
 * A WordPress widget for showing a drilldown search menu for category products
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 8 June, 2009
 * @package shopp
 **/

if (class_exists('WP_Widget')) {
	
class ShoppFacetedMenuWidget extends WP_Widget {

    function ShoppFacetedMenuWidget() {
        parent::WP_Widget(false, 
			$name = __('Shopp Faceted Menu','Shopp'), 
			array('description' => __('Category products drill-down search menu','Shopp'))
		);
    }

    function widget($args, $options) {		
		global $Shopp;
		if (!empty($args)) extract($args);

		if (empty($options['title'])) $options['title'] = __('Product Filters','Shopp');
		$title = $before_title.$options['title'].$after_title;

		if (!empty($Shopp->Category->id) && $Shopp->Category->facetedmenus == "on") {
			$menu = $Shopp->Category->tag('faceted-menu',$options);
			echo $before_widget.$title.$menu.$after_widget;			
		}
    }

    function update($new_instance, $old_instance) {				
        return $new_instance;
    }

    function form($options) {				
		?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title'); ?></label>
		<input type="text" name="<?php echo $this->get_field_name('title'); ?>" id="<?php echo $this->get_field_id('title'); ?>" class="widefat" value="<?php echo $options['title']; ?>"></p>
		<?php
    }

} // class ShoppFacetedMenuWidget

register_widget('ShoppFacetedMenuWidget');

}