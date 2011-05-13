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
	'hasestimates' => 'has_estimates',
	'options' => 'options',
	'methods' => 'options',
	'optionmenu' => 'option_menu',
	'methodmenu' => 'option_menu',
	'optionname' => 'option_name',
	'methodname' => 'option_name',
	'methodselected' => 'method_selected',
	'optioncost' => 'option_cost',
	'methodcost' => 'option_cost',
	'methodselector' => 'method_selector',
	'optiondelivery' => 'option_delivery',
	'methoddelivery' => 'option_delivery'
	);

	static function _apicontext () { return "shipping"; }

	/**
	 * _setobject - returns the global context object used in the shopp('cart') call
	 *
	 * @author John Dillick
	 * @since 1.2
	 *
	 **/
	static function _setobject ($Object, $object) {
		if ( strtolower($object) != 'shipping' ) return $Object; // not mine, do nothing
		else {
			$Order =& ShoppOrder();
			return $Order->Cart;
		}
	}

	function has_estimates ($result, $options, $O) { return apply_filters('shopp_shipping_hasestimates',!empty($O->shipping));  }

	function method_selector ($result, $options, $O) {
		global $Shopp;
		$method = current($O->shipping);

		$checked = '';
		if ((isset($Shopp->Order->Shipping->method) &&
			$Shopp->Order->Shipping->method == $method->name))
				$checked = ' checked="checked"';

		$result = '<input type="radio" name="shipmethod" value="'.urlencode($method->name).'" class="shopp shipmethod" '.$checked.' />';
		return $result;
	}

	function method_selected ($result, $options, $O) {
		global $Shopp;
		$method = current($O->shipping);
		return ((isset($Shopp->Order->Shipping->method) &&
			$Shopp->Order->Shipping->method == $method->name));
	}

	function option_cost ($result, $options, $O) {
		$option = current($O->shipping);
		return money($option->amount);
	}

	function option_delivery ($result, $options, $O) {
		$periods = array("h"=>3600,"d"=>86400,"w"=>604800,"m"=>2592000);
		$option = current($O->shipping);
		if (!$option->delivery) return "";
		$estimates = explode("-",$option->delivery);
		$format = get_option('date_format');
		if (count($estimates) > 1
			&& $estimates[0] == $estimates[1]) $estimates = array($estimates[0]);
		$result = "";
		for ($i = 0; $i < count($estimates); $i++) {
			list($interval,$p) = sscanf($estimates[$i],'%d%s');
			if (empty($interval)) $interval = 1;
			if (empty($p)) $p = 'd';
			if (!empty($result)) $result .= "&mdash;";
			$result .= _d($format,mktime()+($interval*$periods[$p]));
		}
		return $result;
	}

	function option_menu ($result, $options, $O) {
		global $Shopp;
		// @todo Add options for differential pricing and estimated delivery dates
		$_ = array();
		$_[] = '<select name="shipmethod" class="shopp shipmethod">';
		foreach ($O->shipping as $method) {
			$selected = ((isset($Shopp->Order->Shipping->method) &&
				$Shopp->Order->Shipping->method == $method->name))?' selected="selected"':false;

			$_[] = '<option value="'.$method->name.'"'.$selected.'>'.$method->name.' &mdash '.money($method->amount).'</option>';
		}
		$_[] = '</select>';
		return join("",$_);
	}

	function option_name ($result, $options, $O) {
		$option = current($O->shipping);
		return $option->name;
	}

	function options ($result, $options, $O) {
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

	function url ($result, $options, $O) { return is_shopp_page('checkout')?shoppurl(false,'confirm-order'):shoppurl(false,'cart'); }

}

?>