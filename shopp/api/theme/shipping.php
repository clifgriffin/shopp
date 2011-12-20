<?php
/**
* ShoppShippingThemeAPI - Provided theme api tags.
*
* @version 1.0
* @since 1.2
* @package shopp
* @subpackage ShoppShippingThemeAPI
*
**/

/**
 * Provides shopp('shipping') theme API functionality
 *
 * Used primarily in the summary.php template
 *
 * @author Jonathan Davis, John Dillick
 * @since 1.2
 *
 **/
class ShoppShippingThemeAPI implements ShoppAPI {
	static $register = array(
	'url' => 'url',
	'hasestimates' => 'has_options',
	'hasoptions' => 'has_options',
	'options' => 'options',
	'methods' => 'options',
	'optionmenu' => 'option_menu',
	'methodmenu' => 'option_menu',
	'optionname' => 'option_name',
	'methodname' => 'option_name',
	'methodslug' => 'option_slug',
	'optionslug' => 'option_slug',
	'optionselected' => 'option_selected',
	'methodselected' => 'option_selected',
	'optioncost' => 'option_cost',
	'methodcost' => 'option_cost',
	'optionselector' => 'option_selector',
	'methodselector' => 'option_selector',
	'optiondelivery' => 'option_delivery',
	'methoddelivery' => 'option_delivery',
	'updatebutton' => 'update_button'
	);

	static function _apicontext () { return 'shipping'; }

	/**
	 * _setobject - returns the global context object used in the shopp('cart') call
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 **/
	static function _setobject ($Object, $object) {
		if ( is_object($Object) && is_a($Object, 'Order') && isset($Object->Cart) && 'shipping' == strtolower($object) )
			return $Object->Cart;
		else if ( strtolower($object) != 'shipping' ) return $Object; // not mine, do nothing

		$Order =& ShoppOrder();
		return $Order->Cart;
	}

	static function has_options ($result, $options, $O) { return apply_filters('shopp_shipping_hasestimates',!empty($O->shipping));  }

	static function option_selector ($result, $options, $O) {
		global $Shopp;
		$method = current($O->shipping);

		$checked = '';
		if ((isset($Shopp->Order->Shipping->method) &&
			$Shopp->Order->Shipping->method == $method->slug))
				$checked = ' checked="checked"';

		$result = '<input type="radio" name="shipmethod" value="'.esc_attr($method->slug).'" class="shopp shipmethod" '.$checked.' />';
		return $result;
	}

	static function option_selected ($result, $options, $O) {
		global $Shopp;
		$method = current($O->shipping);
		return ((isset($Shopp->Order->Shipping->method) &&
			$Shopp->Order->Shipping->method == $method->slug));
	}

	static function option_slug ($result, $options, $O) {
		$option = current($O->shipping);
		return $option->slug;
	}

	static function option_cost ($result, $options, $O) {
		$option = current($O->shipping);
		return money($option->amount);
	}

	static function option_delivery ($result, $options, $O) {
		$option = current($O->shipping);
		if (!$option->delivery) return "";
		return self::_delivery_format($option->delivery, $options);
	}

	static function _delivery_format( $estimate, $options = array() ) {
		$periods = array("h"=>3600,"d"=>86400,"w"=>604800,"m"=>2592000);
		$defaults = array(
			'dateformat' => get_option('date_format'),
			'dateseparator' => '&mdash;',
		);
		$options = array_merge($defaults, $options);
		extract( $options );
		if ( ! $dateformat ) $dateformat = 'F j, Y';

		$estimates = explode("-",$estimate);
		if ( empty($estimates) ) return "";

		if (count($estimates) > 1 && $estimates[0] == $estimates[1])
			$estimates = array($estimates[0]);

		$result = "";
		for ( $i = 0; $i < count($estimates); $i++ ) {
			list ( $interval, $p ) = sscanf($estimates[$i],'%d%s');
			if (empty($interval)) $interval = 1;
			if (empty($p)) $p = 'd';
			if (!empty($result)) $result .= $dateseparator;
			$result .= _d( $dateformat, current_time('timestamp') + $interval * $periods[$p] );
		}
		return $result;
	}

	static function option_menu ($result, $options, $O) {
		$Order = ShoppOrder();

		$defaults = array(
			'difference' => true,
			'times' => false,
			'class' => false,
			'dateformat' => get_option('date_format'),
			'dateseparator' => '&mdash;',
		);

		$options = array_merge($defaults, $options);
		extract($options);

		$classes = 'shopp shipmethod';
		if ( ! empty($class) ) $classes = $class.' '.$classes;

		$_ = array();
		$selected_option = false;
		if ( isset($Order->Shipping->method) ) $selected_option = $O->shipping[$Order->Shipping->method];

		$_[] = '<select name="shipmethod" class="'.$classes.'">';
		foreach ( $O->shipping as $method ) {
			$cost = money($method->amount);
			$delivery = false;
			if ( str_true($times) && ! empty($method->delivery) ) {
				$delivery = self::_delivery_format($method->delivery, $options).' ';
			}
			if ( $selected_option && str_true($difference) ) {
				$diff = $method->amount - $selected_option->amount;
				$pre = $diff < 0 ? '-' : '+';
				$cost = $pre.money(abs($diff));
			}

			$selected = $selected_option && $selected_option->slug == $method->slug ?' selected="selected"' : false;

			$_[] = '<option value="'.esc_attr($method->slug).'"'.$selected.'>'.$method->name.' ( '.$delivery.$cost.' )</option>';
		}
		$_[] = '</select>';
		return join("",$_);
	}

	static function option_name ($result, $options, $O) {
		$option = current($O->shipping);
		return $option->name;
	}

	static function options ($result, $options, $O) {
		if (!isset($O->sclooping)) $O->sclooping = false;
		if (!$O->sclooping) {
			reset($O->shipping);
			$O->sclooping = true;
		} else next($O->shipping);

		if (current($O->shipping) !== false) return true;
		else {
			$O->sclooping = false;
			reset($O->shipping);
			return false;
		}
	}

	static function url ($result, $options, $O) { return is_shopp_page('checkout')?shoppurl(false,'confirm-order'):shoppurl(false,'cart'); }

	/**
	 * Displays an update button for shipping method form if JavaScript is disabled
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @return void
	 **/
	static function update_button ($result, $options, $O) {
		$submit_attrs = array('title','value','disabled','tabindex','accesskey','class');
		$stdclasses = "update-button hide-if-js";
		$defaults = array(
			'value' => __('Update Shipping','Shopp'),
			'class' => ''
		);
		$options = array_merge($defaults,$options);
		$options['class'] .= " $stdclasses";
		return '<input type="submit" name="update-shipping"'.inputattrs($options,$submit_attrs).' />';
	}

}

?>