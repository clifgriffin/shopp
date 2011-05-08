<?php

add_filter('shoppapi_cartitem', array('ShoppCartItemAPI', '_cartitem'), 10, 4);

add_filter('shoppapi_cartitem_id', array('ShoppCartItemAPI', 'id'), 10, 3);
add_filter('shoppapi_cartitem_product', array('ShoppCartItemAPI', 'product'), 10, 3);
add_filter('shoppapi_cartitem_name', array('ShoppCartItemAPI', 'name'), 10, 3);
add_filter('shoppapi_cartitem_type', array('ShoppCartItemAPI', 'type'), 10, 3);
add_filter('shoppapi_cartitem_link', array('ShoppCartItemAPI', 'url'), 10, 3);
add_filter('shoppapi_cartitem_url', array('ShoppCartItemAPI', 'url'), 10, 3);
add_filter('shoppapi_cartitem_sku', array('ShoppCartItemAPI', 'sku'), 10, 3);
add_filter('shoppapi_cartitem_discount', array('ShoppCartItemAPI', 'discount'), 10, 3);
add_filter('shoppapi_cartitem_unitprice', array('ShoppCartItemAPI', 'unitprice'), 10, 3);
add_filter('shoppapi_cartitem_unittax', array('ShoppCartItemAPI', 'unittax'), 10, 3);
add_filter('shoppapi_cartitem_discounts', array('ShoppCartItemAPI', 'discounts'), 10, 3);
add_filter('shoppapi_cartitem_tax', array('ShoppCartItemAPI', 'tax'), 10, 3);
add_filter('shoppapi_cartitem_total', array('ShoppCartItemAPI', 'total'), 10, 3);
add_filter('shoppapi_cartitem_taxrate', array('ShoppCartItemAPI', 'taxrate'), 10, 3);
add_filter('shoppapi_cartitem_quantity', array('ShoppCartItemAPI', 'quantity'), 10, 3);
add_filter('shoppapi_cartitem_remove', array('ShoppCartItemAPI', 'remove'), 10, 3);
add_filter('shoppapi_cartitem_optionlabel', array('ShoppCartItemAPI', 'optionlabel'), 10, 3);
add_filter('shoppapi_cartitem_options', array('ShoppCartItemAPI', 'options'), 10, 3);
add_filter('shoppapi_cartitem_addonslist', array('ShoppCartItemAPI', 'addonslist'), 10, 3);
add_filter('shoppapi_cartitem_hasinputs', array('ShoppCartItemAPI', 'hasinputs'), 10, 3);
add_filter('shoppapi_cartitem_inputs', array('ShoppCartItemAPI', 'inputs'), 10, 3);
add_filter('shoppapi_cartitem_input', array('ShoppCartItemAPI', 'input'), 10, 3);
add_filter('shoppapi_cartitem_inputslist', array('ShoppCartItemAPI', 'inputslist'), 10, 3);
add_filter('shoppapi_cartitem_coverimage', array('ShoppCartItemAPI', 'coverimage'), 10, 3);
add_filter('shoppapi_cartitem_thumbnail', array('ShoppCartItemAPI', 'coverimage'), 10, 3);


/**
 * Provides support for the shopp('cartitem') tags
 *
 * @author Jonathan Davis, John Dillick
 * @since 1.2
 *
 **/
class ShoppCartItemAPI {

	function _cartitem ($result, $options, $property, $obj) {
		if (is_float($result)) {
			if (isset($options['currency']) && !value_is_true($options['currency'])) return $result;
			else return money($result);
		}
		if (!empty($result)) return $result;
			return false;
	}

	function id ($result, $options, $obj) { return $obj->_id; }

	function product ($result, $options, $obj) { return $obj->product; }

	function name ($result, $options, $obj) { return $obj->name; }

	function type ($result, $options, $obj) { return $obj->type; }

	function url ($result, $options, $obj) { return shoppurl(SHOPP_PRETTYURLS?$obj->slug:array('s_pid'=>$obj->product)); }

	function sku ($result, $options, $obj) { return $obj->sku; }

	function discount ($result, $options, $obj) { return (float)$obj->discount; }

	function unitprice ($result, $options, $obj) {
		$taxes = isset($options['taxes'])?value_is_true($options['taxes']):null;
		if (in_array($property,array('price','newprice','unitprice','total','tax','options')))
			$taxes = shopp_taxrate($taxes,$obj->taxable,$this) > 0?true:false;
		return (float)$obj->unitprice+($taxes?$obj->unittax:0);
	}

	function unittax ($result, $options, $obj) { return (float)$obj->unittax; }

	function discounts ($result, $options, $obj) { return (float)$obj->discounts; }

	function tax ($result, $options, $obj) { return (float)$obj->tax; }

	function total ($result, $options, $obj) {
		$taxes = isset($options['taxes'])?value_is_true($options['taxes']):null;
		if (in_array($property,array('price','newprice','unitprice','total','tax','options')))
			$taxes = shopp_taxrate($taxes,$obj->taxable,$this) > 0?true:false;
		return (float)$obj->total+($taxes?($obj->unittax*$obj->quantity):0);
	}

	function taxrate ($result, $options, $obj) { return percentage($obj->taxrate*100,array('precision' => 1)); }

	function quantity ($result, $options, $obj) {
		$result = $obj->quantity;
		if ($obj->type == "Donation" && $obj->donation['var'] == "on") return $result;
		if ($obj->type == "Subscription" || $obj->type == "Membership") return $result;
		if (isset($options['input']) && $options['input'] == "menu") {
			if (!isset($options['value'])) $options['value'] = $obj->quantity;
			if (!isset($options['options']))
				$values = "1-15,20,25,30,35,40,45,50,60,70,80,90,100";
			else $values = $options['options'];

			if (strpos($values,",") !== false) $values = explode(",",$values);
			else $values = array($values);
			$qtys = array();
			foreach ($values as $value) {
				if (strpos($value,"-") !== false) {
					$value = explode("-",$value);
					if ($value[0] >= $value[1]) $qtys[] = $value[0];
					else for ($i = $value[0]; $i < $value[1]+1; $i++) $qtys[] = $i;
				} else $qtys[] = $value;
			}
			$result = '<select name="items['.$obj->_id.']['.$property.']">';
			foreach ($qtys as $qty)
				$result .= '<option'.(($qty == $obj->quantity)?' selected="selected"':'').' value="'.$qty.'">'.$qty.'</option>';
			$result .= '</select>';
		} elseif (isset($options['input']) && valid_input($options['input'])) {
			if (!isset($options['size'])) $options['size'] = 5;
			if (!isset($options['value'])) $options['value'] = $obj->quantity;
			$result = '<input type="'.$options['input'].'" name="items['.$obj->_id.']['.$property.']" id="items-'.$obj->_id.'-'.$property.'" '.inputattrs($options).'/>';
		} else $result = $obj->quantity;
		return $result;
	}

	function remove ($result, $options, $obj) {
		$label = __("Remove");
		if (isset($options['label'])) $label = $options['label'];
		if (isset($options['class'])) $class = ' class="'.$options['class'].'"';
		else $class = ' class="remove"';
		if (isset($options['input'])) {
			switch ($options['input']) {
				case "button":
					$result = '<button type="submit" name="remove['.$obj->_id.']" value="'.$obj->_id.'"'.$class.' tabindex="">'.$label.'</button>'; break;
				case "checkbox":
				    $result = '<input type="checkbox" name="remove['.$obj->_id.']" value="'.$obj->_id.'"'.$class.' tabindex="" title="'.$label.'"/>'; break;
			}
		} else {
			$result = '<a href="'.href_add_query_arg(array('cart'=>'update','item'=>$obj->_id,'quantity'=>0),shoppurl(false,'cart')).'"'.$class.'>'.$label.'</a>';
		}
		return $result;
	}

	function optionlabel ($result, $options, $obj) { return $obj->option->label; }

	function options ($result, $options, $obj) {
		$class = "";
		if (!isset($options['before'])) $options['before'] = '';
		if (!isset($options['after'])) $options['after'] = '';
		if (isset($options['show']) &&
			strtolower($options['show']) == "selected")
			return (!empty($obj->option->label))?
				$options['before'].$obj->option->label.$options['after']:'';

		if (isset($options['class'])) $class = ' class="'.$options['class'].'" ';
		if (count($obj->variations) > 1) {
			$result .= $options['before'];
			$result .= '<input type="hidden" name="items['.$obj->_id.'][product]" value="'.$obj->product.'"/>';
			$result .= ' <select name="items['.$obj->_id.'][price]" id="items-'.$obj->_id.'-price"'.$class.'>';
			$result .= $obj->options($obj->priceline);
			$result .= '</select>';
			$result .= $options['after'];
		}
		return $result;
	}

	function addonslist ($result, $options, $obj) {
		if (empty($obj->addons)) return false;
		$defaults = array(
			'before' => '',
			'after' => '',
			'class' => '',
			'exclude' => '',
			'prices' => true,

		);
		$options = array_merge($defaults,$options);
		extract($options);

		$classes = !empty($class)?' class="'.join(' ',$class).'"':'';
		$excludes = explode(',',$exclude);
		$prices = value_is_true($prices);

		$result .= $before.'<ul'.$classes.'>';
		foreach ($obj->addons as $id => $addon) {
			if (in_array($addon->label,$excludes)) continue;

			$price = ($addon->onsale?$addon->promoprice:$addon->price);
			if ($obj->taxrate > 0) $price = $price+($price*$obj->taxrate);

			if ($prices) $pricing = " (".($addon->unitprice < 0?'-':'+').money($price).")";
			$result .= '<li>'.$addon->label.$pricing.'</li>';
		}
		$result .= '</ul>'.$after;
		return $result;
	}

	function hasinputs ($result, $options, $obj) { return (count($obj->data) > 0); }

	function inputs ($result, $options, $obj) {
		if (!isset($obj->_data_loop)) {
			reset($obj->data);
			$obj->_data_loop = true;
		} else next($obj->data);

		if (current($obj->data) !== false) return true;

		unset($obj->_data_loop);
		reset($obj->data);
		return false;
	}

	function input ($result, $options, $obj) {
		$data = current($obj->data);
		$name = key($obj->data);
		if (isset($options['name'])) return $name;
		return $data;
	}

	function inputslist ($result, $options, $obj) {
		if (empty($obj->data)) return false;
		$defaults = array(
			'class' => '',
			'exclude' => array(),
			'before' => '',
			'after' => '',
			'separator' => '<br />'
		);
		$options = array_merge($defaults,$options);
		extract($options);

		$classes = '';
		if (!empty($class)) $classes = ' class="'.$class.'"';

		$result .= $before.'<ul'.$classes.'>';
		foreach ($obj->data as $name => $data) {
			if (in_array($name,$excludes)) continue;
			if (is_array($data)) $data = join($separator,$data);
			$result .= '<li><strong>'.$name.'</strong>: '.$data.'</li>';
		}
		$result .= '</ul>'.$after;
		return $result;
	}

	function coverimage ($result, $options, $obj) {
		$defaults = array(
			'class' => '',
			'width' => 48,
			'height' => 48,
			'size' => false,
			'fit' => false,
			'sharpen' => false,
			'quality' => false,
			'bg' => false,
			'alt' => false,
			'title' => false
		);

		$options = array_merge($defaults,$options);
		extract($options);

		if ($obj->image !== false) {
			$img = $obj->image;

			if ($size !== false) $width = $height = $size;
			$scale = (!$fit)?false:esc_attr(array_search($fit,$img->_scaling));
			$sharpen = (!$sharpen)?false:esc_attr(min($sharpen,$img->_sharpen));
			$quality = (!$quality)?false:esc_attr(min($quality,$img->_quality));
			$fill = (!$bg)?false:esc_attr(hexdec(ltrim($bg,'#')));
			$scaled = $img->scaled($width,$height,$scale);

			$alt = empty($alt)?$img->alt:$alt;
			$title = empty($title)?$img->title:$title;
			$title = empty($title)?'':' title="'.esc_attr($title).'"';
			$class = !empty($class)?' class="'.esc_attr($class).'"':'';

			if (!empty($options['title'])) $title = ' title="'.esc_attr($options['title']).'"';
			$alt = esc_attr(!empty($img->alt)?$img->alt:$obj->name);
			return '<img src="'.add_query_string($img->resizing($width,$height,$scale,$sharpen,$quality,$fill),shoppurl($img->id,'images')).'"'.$title.' alt="'.$alt.'" width="'.$scaled['width'].'" height="'.$scaled['height'].'"'.$class.' />';
		}
	}
}

?>