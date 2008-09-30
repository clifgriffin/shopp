<div class="wrap shopp">
	<?php if (!empty($updated)): ?><div id="message" class="updated fade"><p><?php echo $updated; ?></p></div><?php endif; ?>
	<h2><?php _e('Tax Settings','Shopp'); ?></h2>
	<?php include("navigation.php"); ?>

	<form name="settings" id="taxes" action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="post">
		<?php wp_nonce_field('shopp-settings-taxes'); ?>
		
		<table class="form-table"> 
			<tr class="form-field form-required"> 
				<th scope="row" valign="top"><label for="taxes-toggle"><?php _e('Calculate Taxes','Shopp'); ?></label></th> 
				<td><input type="hidden" name="settings[taxes]" value="off" /><input type="checkbox" name="settings[taxes]" value="on" id="taxes-toggle"<?php if ($this->Settings->get('taxes') == "on") echo ' checked="checked"'?> /><label for="taxes-toggle"> <?php _e('Enabled','Shopp'); ?></label><br /> 
	            <?php _e('Enables tax calculations.  Disable if you are exclusively selling non-taxable items.','Shopp'); ?></td>
			</tr>
			<tr class="form-field form-required"> 
				<th scope="row" valign="top"><label for="taxrate[i]"><?php _e('Tax Rates','Shopp'); ?></label></th> 
				<td>
					<?php if ($this->Settings->get('target_markets')): ?>
					<table id="taxrates-table"><tr><td></td></tr></table>
	            <button type="button" id="add-taxrate" class="button-secondary"><img src="<?php echo SHOPP_PLUGINURI; ?>/core/ui/icons/add.png" alt="+" width="16" height="16" /> <?php _e('Add a Tax Rate','Shopp'); ?></button>
					<?php else: ?>
					<p><strong><?php _e('Note:','Shopp'); ?></strong> <?php _e('You must select the target markets you will be selling to under','Shopp'); ?> <a href="?page=<?php echo $this->Admin->settings ?>&amp;edit=general"><?php _e('General settings','Shopp'); ?></a> <?php _e('before you can setup tax rates.','Shopp'); ?></p>
					<?php endif; ?>
				</td> 
			</tr>

		</table>
		<p class="submit"><input type="submit" class="button" name="save" value="<?php _e('Save Changes','Shopp'); ?>" /></p>
	</form>
</div>

<script type="text/javascript">
//<![CDATA[
helpurl = "<?php echo SHOPP_DOCS; ?>Taxes_Settings";

(function($) {

var disableZonesInUse = function () {
	$('#taxrates-table tr select.zone option').each (function () {
		if ($.inArray($(this).val(),zonesInUse) != -1 && !this.selected)
			$(this).attr({'disabled':'disabled'});
	});
}

var addTaxRate = function (r) {
	i = taxrates.length;
	var row = $('<tr/>').appendTo('#taxrates-table');
	
	var rateCell = $('<td></td>').html(' %').appendTo(row);
	var rate = $('<input type="text" name="settings[taxrates]['+i+'][rate]" value="" id="settings-taxrates-'+i+'-rate" size="4" class="selectall right" />').prependTo(rateCell);
	var deleteButton = $('<button id="deleteButton-'+i+'" class="deleteButton" type="button" title="Delete tax rate"></button>').prependTo(rateCell).hide();
	var deleteIcon = $('<img src="<?php echo SHOPP_PLUGINURI; ?>/core/ui/icons/delete.png" width="16" height="16" />').appendTo(deleteButton);
	
	var countryCell = $('<td></td>').appendTo(row);
	var countryMenu = $('<select name="settings[taxrates]['+i+'][country]" id="country-'+i+'"></select>').appendTo(countryCell);
	
	$.each(countries, function(value,label) {
		option = $('<option></option>').val(value).html(label).appendTo(countryMenu);
	});
	
	var zoneCell = $('<td></td>').appendTo(row);
	var zoneMenu = $('<select name="settings[taxrates]['+i+'][zone]" id="zone-'+i+'" class="zone"></select>').appendTo(zoneCell);

	var updateZoneMenu = function () {
		zoneMenu.empty();
		if (zones[$(countryMenu).val()]) {
			$.each(zones[$(countryMenu).val()], function(value,label) {
				if ($.inArray(value,zonesInUse) != -1) option = $('<option disabled="disabled"></option>').val(value).html(label).appendTo(zoneMenu);				
				else option = $('<option></option>').val(value).html(label).appendTo(zoneMenu);
			});
		}
		if (zoneMenu.children().length == 0) zoneMenu.hide();
		else zoneMenu.show();
	}
	
	$(row).hover(function() {
			if (i > 0) deleteButton.show();
		},function() {
			deleteButton.hide();
	});
	
	$(deleteButton).click(function () {
		if (taxrates.length > 1) {
			if (confirm("Are you sure you want to delete this tax rate?")) {
				row.remove();
				taxrates.splice(i,1);
			}
		}
	});
	
	$(countryMenu).change(function () {
		updateZoneMenu();
	}).change();
	
	$(zoneMenu).change(function () {
		if ($.inArray(currentZone,zonesInUse) != -1)
			zonesInUse.splice($.inArray(currentZone,zonesInUse),1);
		currentZone = $(zoneMenu).val();
		zonesInUse.push(currentZone);
		disableZonesInUse();
	});
	
	if (r) {
		rate.val(r.rate);
		countryMenu.val(r.country).change();
		zoneMenu.val(r.zone).change();
	} else {
		countryMenu.val(base.country).change();
		if ($.inArray(base.zone,zonesInUse) == -1)
			zoneMenu.val(base.zone).change();
		else zoneMenu.change();
	}
	
	var currentZone = zoneMenu.val();

	taxrates.push(row);
	quickSelects();
	
}

if ($('#taxrates-table')) {
	var rates = <?php echo json_encode($rates); ?>;
	var base = <?php echo json_encode($base); ?>;
	var countries = <?php echo json_encode($countries); ?>;
	var zones = <?php echo json_encode($zones); ?>;
	var taxrates = new Array();
	var zonesInUse = new Array();


	$('#add-taxrate').click(function() {
		addTaxRate();
	});

	$('#taxrates-table').empty();
	if (rates) {
		for (i in rates) {
			addTaxRate(rates[i]);
		}
	} else addTaxRate();	
}
})(jQuery)

//]]>
</script>