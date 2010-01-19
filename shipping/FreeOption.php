<?php
/**
 * Free Option
 * 
 * Provides a free shipping rate not included in shipping estimates
 *
 * @author Jonathan Davis
 * @version 1.1
 * @copyright Ingenesis Limited, January 19, 2010
 * @package shopp
 * @since 1.1 dev
 * @subpackage FreeOption
 * 
 * $Id: FreeOption.php 510 2009-09-22 14:14:09Z jond $
 **/

class FreeOption extends ShippingFramework implements ShippingModule {

	function methods () {
		return array(__("Free Option","Shopp"));
	}
	
	function init () {}
	function calcitem ($id,$Item) {}
	
	function calculate ($options,$Order) {
		foreach ($this->rates as $rate) {
			$rate['amount'] = 0;
			if (isset($rate['name']) && isset($rate['amount']))
				$options[$rate['name']] = new ShippingOption($rate,false);
		}
		return $options;
	}
	
	function ui () {
?>
var FreeOption = function (methodid,table,rates) {
	table.empty();
	var headingsRow = $('<tr class="headings"/>').appendTo(table);
}
methodHandlers.register('<?php echo $this->module; ?>',FreeOption);
		<?php		
	}

} // END class FreeOption

?>