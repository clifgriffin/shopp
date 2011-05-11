<?php

add_filter('shoppapi_shipping_url', array('ShoppCartShippingAPI', 'url'), 10, 3);
add_filter('shoppapi_shipping_hasestimates', array('ShoppCartShippingAPI', 'hasestimates'), 10, 3);
add_filter('shoppapi_shipping_options', array('ShoppCartShippingAPI', 'options'), 10, 3);
add_filter('shoppapi_shipping_methods', array('ShoppCartShippingAPI', 'options'), 10, 3);
add_filter('shoppapi_shipping_optionmenu', array('ShoppCartShippingAPI', 'optionmenu'), 10, 3);
add_filter('shoppapi_shipping_methodmenu', array('ShoppCartShippingAPI', 'optionmenu'), 10, 3);
add_filter('shoppapi_shipping_optionname', array('ShoppCartShippingAPI', 'optionname'), 10, 3);
add_filter('shoppapi_shipping_methodname', array('ShoppCartShippingAPI', 'optionname'), 10, 3);
add_filter('shoppapi_shipping_methodselected', array('ShoppCartShippingAPI', 'methodselected'), 10, 3);
add_filter('shoppapi_shipping_optioncost', array('ShoppCartShippingAPI', 'optioncost'), 10, 3);
add_filter('shoppapi_shipping_methodcost', array('ShoppCartShippingAPI', 'optioncost'), 10, 3);
add_filter('shoppapi_shipping_methodselector', array('ShoppCartShippingAPI', 'methodselector'), 10, 3);
add_filter('shoppapi_shipping_optiondelivery', array('ShoppCartShippingAPI', 'optiondelivery'), 10, 3);
add_filter('shoppapi_shipping_methoddelivery', array('ShoppCartShippingAPI', 'optiondelivery'), 10, 3);


/**
 * Provides shopp('shipping') theme API functionality
 *
 * Used primarily in the summary.php template
 *
 * @author Jonathan Davis, John Dillick
 * @since 1.2
 *
 **/
class ShoppCartShippingAPI {
	function hasestimates ($result, $options, $O) { return apply_filters('shopp_shipping_hasestimates',!empty($O->shipping));  }

	function methodselector ($result, $options, $O) {
		global $Shopp;
		$method = current($O->shipping);

		$checked = '';
		if ((isset($Shopp->Order->Shipping->method) &&
			$Shopp->Order->Shipping->method == $method->name))
				$checked = ' checked="checked"';

		$result = '<input type="radio" name="shipmethod" value="'.urlencode($method->name).'" class="shopp shipmethod" '.$checked.' />';
		return $result;
	}

	function methodselected ($result, $options, $O) {
		global $Shopp;
		$method = current($O->shipping);
		return ((isset($Shopp->Order->Shipping->method) &&
			$Shopp->Order->Shipping->method == $method->name));
	}

	function optioncost ($result, $options, $O) {
		$option = current($O->shipping);
		return money($option->amount);
	}

	function optiondelivery ($result, $options, $O) {
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

	function optionmenu ($result, $options, $O) {
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

	function optionname ($result, $options, $O) {
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