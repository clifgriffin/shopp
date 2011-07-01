<?php
/**
 * OrderEvent Renderer system
 *
 * Description…
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, June 28, 2011
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @since 1.0
 * @subpackage shopp
 **/

add_action('shopp_order_manager_event',array('OrderEventRenderer','display'));

/**
 * OrderEventDisplay
 *
 * @author Jonathan Davis
 * @since 1.2
 * @package shopp
 **/
class OrderEventRenderer {

	protected $Event;

	var $markup = array();

	/**
	 * OrderEventRenderer constructor
	 *
	 * @author Jonathan Davis
	 *
	 * @return void
	 **/
	function __construct (OrderEventMessage $Event) {
		$this->Event = $Event;
		$this->load();
	}

	function load () {
		if (empty($this->Event)) return;

		// Remap event object information for formatting
		$map = array(
			'type' => 'name',
			'amount' => 'amount',
			'date' => 'created'
		);
		$message = array_combine($this->Event->_xcols,$this->Event->_xcols);
		$map = array_merge($map,$message);

		foreach ($map as $property => $data)
			$this->$property = $this->Event->$data;
	}

	static function renderer (OrderEventMessage $Event) {
		$Renderer = get_class($Event).'Renderer';
		if (!class_exists($Renderer)) $Renderer = __CLASS__;

		return new $Renderer($Event);
	}

	static function display (OrderEventMessage $Event) {
		$UI = self::renderer($Event);
		$UI->render();
	}

	function content () {
		$_ = array();
		$_['date'] = $this->date();
		$_['name'] = $this->name();
		$_['details'] = $this->details();
		$_['amount'] = $this->amount();
		return $_;
	}

	function render () {
		$markup = $this->content();

		// Format into table cells
		foreach ($markup as $name => &$content)
			$content = $this->cell($content,$name);

		// Format into table row
		$markup = $this->row($markup);
		echo $markup;
	}

	function cell ($content,$name) {
		return '<td class="'.$name.'">'.$content.'</td>';
	}

	function row ($content) {
		return '<tr class="'.str_replace('-',' ',$this->type).'">'.join('',$content).'</tr>';
	}

	function strong ($content) {
		return '<strong>'.$content.'</strong>';
	}

	function name () {
		return $this->type;
	}

	function details () {
		return '';
	}

	function amount () {
		return '';
	}

	function date () {
		$today = mktime(0,0,0);
		$date = date(get_option('date_format'),$this->date);
		$time = date(get_option('time_format'),$this->date);

		$weekdays = array(
			__('Sunday','Shopp'),
			__('Monday','Shopp'),
			__('Tuesday','Shopp'),
			__('Wednesday','Shopp'),
			__('Thursday','Shopp'),
			__('Friday','Shopp'),
			__('Saturday','Shopp')
		);

		if ($this->date > $today-(86400*7))
			$date = $weekdays[date('w',$this->date)];
		if ($this->date > $today-86400)
			$date = __('Yesterday','Shopp');
		if ($this->date > $today)
			$date = __('Today','Shopp');

		return '<span class="day">'.$date.'</span> <span class="time">'.$time.'</span>';
	}

} // END class OrderEventDisplay

class TxnOrderEventRenderer extends OrderEventRenderer {

	function name () {
		return sprintf(__('Transaction %s successful','Shopp'),$this->type);
	}

	function details () {
		if ('' == $this->method.$this->payid) return '';
		$details = $this->method;
		if (!empty($this->payid)) $details .= " ($this->payid)";
		return $details;
	}

	function amount() {
		return money($this->amount);
	}

}

class TxnFailOrderEventRenderer extends TxnOrderEventRenderer {

	function name () {
		return sprintf(__('Transaction %s failed','Shopp'),$this->type);
	}

	function details () {
		return $this->message;
	}

	function amount () {
		return parent::amount();
	}

}

class AuthOrderEventRenderer extends TxnOrderEventRenderer {

	function name () {
		return __('Authorized payment','Shopp');
	}

}

class AuthFailOrderEventRenderer extends TxnFailOrderEventRenderer {

	function name () {
		return __('Authorized failed','Shopp');
	}

}

class CaptureOrderEventRenderer extends TxnOrderEventRenderer {

	function name () {
		return __('Payment received','Shopp');
	}

}

class CaptureFailOrderEventRenderer extends TxnFailOrderEventRenderer {

	function name () {
		return __('Payment failed','Shopp');
	}

}

class RefundOrderEventRenderer extends TxnOrderEventRenderer {

	function name () {
		return __('Refund issued','Shopp');
	}

	function amount () {
		return '-'.parent::amount();
	}

}

class RefundFailOrderEventRenderer extends TxnFailOrderEventRenderer {

	function name () {
		return __('Refund failed','Shopp');
	}

	function amount () {
		return '-'.parent::amount();
	}

}

class VoidOrderEventRenderer extends TxnOrderEventRenderer {

	function name () {
		return __('Order canceled','Shopp');
	}

	function amount () {
		return '-'.parent::amount();
	}

}

class ShippedOrderEventRenderer extends OrderEventRenderer {

	function name () {
		return __('Order shipped','Shopp');
	}

	function details () {
		return sprintf('%s: %s',$this->carrier_name(),$this->tracklink());
	}

	function carrier () {
		if (isset($this->Carrier)) return;
		$carriers = Lookup::shipcarriers();
		$this->Carrier = $carriers[$this->carrier];
	}

	function carrier_name () {
		$this->carrier();
		if (isset($this->Carrier->name) && !empty($this->Carrier->name))
			return $this->Carrier->name;
		return $this->carrier;
	}

	function trackurl () {
		$this->carrier();
		if (isset($this->Carrier->trackurl))
			return sprintf($this->Carrier->trackurl,$this->tracking);
	}

	function tracklink () {
		$url = $this->trackurl();
		if (empty($url)) return $this->tracking;
		return sprintf('<a href="%s" target="_top">%s</a>',$url,$this->tracking);
	}

}


?>