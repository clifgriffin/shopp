<?php
/**
 * Free Option
 *
 * Provides a free shipping rate not included in shipping estimates
 *
 * @author Jonathan Davis
 * @version 1.2
 * @copyright Ingenesis Limited, January 19, 2010
 * @package shopp
 * @since 1.2
 * @subpackage FreeOption
 *
 * $Id: FreeOption.php 510 2009-09-22 14:14:09Z jond $
 **/

class FreeOption extends ShippingFramework implements ShippingModule {

	function methods () {
		return __('Free Option','Shopp');
	}

	function init () {}
	function calcitem ($id,$Item) {}

	function calculate ($options,$Order) {

		foreach ($this->methods as $slug => $method) {
			$amount = $this->tablerate($method['table']);
			if ($amount === false) continue; // Skip methods that don't match at all
			$rate = array(
				'slug' => $slug,
				'name' => $method['label'],
				'amount' => $amount,
				'delivery' => $this->delivery($method),
				'items' => false
			);
			$options[$slug] = new ShippingOption($rate,false);
		}
		return $options;
	}

	function settings () {
		$this->ui->flatrates(0,array(
			'norates' => true,
			'table' => $this->settings['table']
		));

	}

} // END class FreeOption

?>