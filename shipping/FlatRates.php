<?php
/**
 * FlatRates
 * Provides flat rate shipping calculations
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 27 April, 2008
 * @package shopp
 **/

class FlatRates {

	function FlatRates () {
	}
	
	function methods (&$ShipCalc) {
		$ShipCalc->methods[get_class($this).'::order'] = "Flat Rate on order";
		$ShipCalc->methods[get_class($this).'::item'] = "Flat Rate per item";
	}
	
	function calculate (&$Cart,$rate,$column) {
		list($ShipCalcClass,$process) = split("::",$rate['method']);
		switch($process) {
			case "item":
				$shipping = 0;
				foreach($Cart->contents as $item)
					$shipping += $item->quantity * $rate[$column][0];
				return $shipping;
				break;
			default:
				return $rate[$column][0];
		}
	}
	
	function ui () {
		?>
var FlatRates = function (methodid,table,rates) {
	table.empty();
	var headingsRow = $('<tr class="headings"/>').appendTo(table);

	$('<th scope="col">').appendTo(headingsRow);

	var domesticHeading = new Array();
	$.each(domesticAreas,function(key,area) {
		domesticHeading[key] = $('<th scope="col"><label for="'+area+'['+methodid+']">'+area+'</label></th>').appendTo(headingsRow);
	});
	var regionHeading = $('<th scope="col"><label for="'+region+'['+methodid+']">'+region+'</label></th>').appendTo(headingsRow);
	var worldwideHeading = $('<th scope="col"><label for="worldwide['+methodid+']">Worldwide</label></th>').appendTo(headingsRow);
	$('<th scope="col">').appendTo(headingsRow);

	var row = $('<tr/>').appendTo(table);

	$('<td/>').appendTo(row);

	$.each(domesticAreas,function(key,area) {
		var inputCell = $('<td/>').appendTo(row);
		if (!isNaN(key)) key = area;
		if (rates && rates[key] && rates[key][0]) var value = rates[key][0];
		else value = 0;
		$('<input name="settings[shipping_rates]['+methodid+']['+key+'][]" id="'+area+'['+methodid+']" class="selectall right" size="7" tabindex="'+(methodid+1)+'04" />').change(function() {
			this.value = asMoney(this.value);
		}).val(value).appendTo(inputCell).change();
	});
	
	var inputCell = $('<td/>').appendTo(row);
	if (rates && rates[region] && rates[region][0]) value = rates[region][0];
	else value = 0;
	$('<input name="settings[shipping_rates]['+methodid+']['+region+'][]"  id="'+region+'['+methodid+']" class="selectall right" size="7" tabindex="'+(methodid+1)+'05" />').change(function() {
		this.value = asMoney(this.value);
	}).val(value).appendTo(inputCell).change();
	
	var inputCell = $('<td/>').appendTo(row);
	if (rates && rates['Worldwide'][0]) value = rates['Worldwide'][0];
	else value = 0;
	intlInput = $('<input name="settings[shipping_rates]['+methodid+'][Worldwide][]" id="worldwide['+methodid+']" class="selectall right" size="7" tabindex="'+(methodid+1)+'06" />').change(function() {
		this.value = asMoney(this.value);
	}).val(value).appendTo(inputCell).change();	
	
	$('<td/>').appendTo(row);
	
	quickSelects();
}

methodHandlers.register('<?php echo get_class($this); ?>::order',FlatRates);
methodHandlers.register('<?php echo get_class($this); ?>::item',FlatRates);

		<?php		
	}

} // end flatrates class

?>