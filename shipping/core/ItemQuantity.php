<?php
/**
 * Item Quantity Tiers
 *
 * Provides shipping calculations based on the total quantity of items ordered
 *
 * @author Jonathan Davis
 * @version 1.1
 * @copyright Ingenesis Limited, 27 April, 2008
 * @package shopp
 * @since 1.1 dev
 * @subpackage ItemQuantity
 *
 * $Id$
 **/

class ItemQuantity extends ShippingFramework implements ShippingModule {

	var $items = 0;

	function init () {
		$this->items = 0;
	}

	function methods () {
		return array('range' => __("Item Quantity Tiers","Shopp"));
	}

	function calculate ($options,$Order) {
		foreach ($this->rates as $rate) {
			$column = $this->ratecolumn($rate);
			foreach ($rate['max'] as $id => $value) {
				if (!(int)$value) $rate['amount'] = $rate[$column][$id];
				if ($this->items <= $value) {
					$rate['amount'] = $rate[$column][$id];
					break;
				}
			}
			$options[$rate['name']] = new ShippingOption($rate);
		}
		return $options;
	}

	function calcitem ($id,$Item) {
		$this->items += $Item->quantity;
	}

	function ui () {
		?>

var ItemQuantityRange = function (methodid,table,rates) {
	table.empty();
	var headingsRow = $('<tr class="headings"/>').appendTo(table);

	$('<th scope="col" class="units"><label for="max-'+methodid+'-0"><?php echo addslashes(__('By Item Quantity','Shopp')); ?></label></th>').appendTo(headingsRow);
	$.each(domesticAreas,function(key,area) {
		$('<th scope="col"><label for="'+area+'-'+methodid+'-0">'+area+'</label></th>').appendTo(headingsRow);
	});
	$('<th scope="col"><label for="'+region+'-'+methodid+'-0">'+region+'</label></th>').appendTo(headingsRow);
	$('<th scope="col"><label for="worldwide-'+methodid+'-0"><?php echo addslashes(__('Worldwide','Shopp')); ?></label></th>').appendTo(headingsRow);
	$('<th scope="col">').appendTo(headingsRow);

	if (rates && rates['max']) {
		$.each(rates['max'],function(rowid,rate) {
			var row = AddItemQuantityRangeRow(methodid,table,rates);
			row.appendTo(table);
			quickSelects();
		});
	} else {
		var row = AddItemQuantityRangeRow(methodid,table);
		row.appendTo(table);
		quickSelects();
	}
}

function AddItemQuantityRangeRow(methodid,table,rates) {
	var rows = $(table).find('tbody').children().not('tr.headings');
	var id = rows.length;

	var row = $('<tr/>');

	var unitCell = $('<td class="units"></td>').appendTo(row);
	$('<label for="max-'+methodid+'-'+id+'"><?php echo addslashes(__("Up to","Shopp")); ?> <label>').appendTo(unitCell);
	if (rates && rates['max'] && rates['max'][id] !== false) value = rates['max'][id];
	else if (id > 1) value = "+";
	else value = 1;
	var maxInput = $('<input type="text" name="settings[shipping_rates]['+methodid+'][max][]" class="selectall right" size="7" id="max-'+methodid+'-'+id+'" tabindex="'+(methodid+1)+'02" />').change(function() {
		if (!(this.value == "+" || this.value == ">")) this.value = asNumber(this.value);
	}).val(value=="+"||value==">"?value:asNumber(value)).appendTo(unitCell);

	$('<span> = </span>').appendTo(unitCell);

	$.each(domesticAreas,function(key,area) {
		var inputCell = $('<td/>').appendTo(row);
		if (!isNaN(key)) key = area;
		if (rates && rates[key] && rates[key][id]) value = rates[key][id];
		else value = 0;
		$('<input type="text" name="settings[shipping_rates]['+methodid+']['+key+'][]" id="'+area+'-'+methodid+'-'+id+'" class="selectall right" size="7" tabindex="'+(methodid+1)+'04" />').change(function() {
			this.value = asMoney(this.value);
		}).val(asMoney(new Number(value))).appendTo(inputCell);
	});

	var inputCell = $('<td/>').appendTo(row);
	if (rates && rates[region] && rates[region][id]) value = rates[region][id];
	else value = 0;
	$('<input type="text" name="settings[shipping_rates]['+methodid+']['+region+'][]"  id="'+region+'-'+methodid+'-'+id+'" class="selectall right" size="7" tabindex="'+(methodid+1)+'05" />').change(function() {
		this.value = asMoney(this.value);
	}).val(asMoney(new Number(value))).appendTo(inputCell);

	var inputCell = $('<td/>').appendTo(row);
	if (rates && rates['Worldwide'] && rates['Worldwide'][id]) value = rates['Worldwide'][id];
	else value = 0;
	worldwideInput = $('<input type="text" name="settings[shipping_rates]['+methodid+'][Worldwide][]" id="worldwide-'+methodid+'-'+id+'"  class="selectall right" size="7" tabindex="'+(methodid+1)+'06" />').change(function() {
		this.value = asMoney(this.value);
	}).val(asMoney(new Number(value))).appendTo(inputCell);

	var rowCtrlCell = $('<td class="rowctrl" />').appendTo(row);
	var deleteButton = $('<button type="button" name="delete"></button>').appendTo(rowCtrlCell);
	if (rows.length == 0) {
		deleteButton.attr('class','disabled');
		deleteButton.attr('disabled','disabled');
	}
	deleteButton.click(function() {
		$(row).remove();
	});
	$('<img src="<?php echo SHOPP_PLUGINURI; ?>/core/ui/icons/delete.png" width="16" height="16" />').appendTo(deleteButton);
	var addButton = $('<button type="button" name="add" tabindex="'+(methodid+1)+'07"></button>').appendTo(rowCtrlCell);
	$('<img src="<?php echo SHOPP_PLUGINURI; ?>/core/ui/icons/add.png" width="16" height="16" />').appendTo(addButton);
	addButton.click(function() {
		insertedRow = AddItemQuantityRangeRow(methodid,table);
		$(insertedRow).insertAfter($(row));
		quickSelects();
	});

	return row;
}

methodHandlers.register('<?php echo get_class($this); ?>::range',ItemQuantityRange);

		<?php
	}

} // end flatrates class

?>