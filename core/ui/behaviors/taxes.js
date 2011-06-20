/*!
 * taxes.js - Tax rate settings behaviors
 * Copyright © 2008-2010 by Ingenesis Limited
 * Licensed under the GPLv3 {@see license.txt}
 */

function TaxRate (data) {

	var $ = jqnc(),
		id = ratesidx++,
		rules = 0,
		ratetable = $('#tax-rates'),
		logicSelect = '<select name="settings[taxrates]['+id+'][logic]"><option value="any">'+ANY_OPTION+'</option><option value="all">'+ALL_OPTION+'</option></select>',
		ui = '<th scope="row" valign="top"><p><input type="text" name="settings[taxrates]['+id+'][rate]" value="" size="6" class="selectall" /></p></th>'+
				'<td><div class="controls"></div><ul class="conditions"><li class="origin">'+
				'<select name="settings[taxrates]['+id+'][country]" class="country"></select><select name="settings[taxrates]['+id+'][zone]" class="zone"></select>'+
				'</li><li class="scope"><p>'+APPLY_LOGIC.replace('%s',logicSelect)+':</p></li></ul></td>',
		row = $('<tr/>').html(ui).appendTo(ratetable),
		rate = row.find('th p input'),
		controls = row.find('div.controls'),
		conditions = row.find('td ul.conditions'),
		origin = conditions.find('li.origin'),
		scope = conditions.find('li.scope').hide(),
		countryMenu = conditions.find('select.country'),
		zoneMenu = conditions.find('select.zone'),
		selectedZone = false,
		selectedCountry = false,
		localToggle = $('<label><input type="checkbox" name="settings[taxrates]['+id+'][localrates]" value="on" /> '+LOCAL_RATES+'</label>').appendTo(controls),
		addButtonSrc = '<button type="button" class="add"><img src="'+SHOPP_PLUGINURI+'/core/ui/icons/add.png" alt="+" width="16" height="16" /></button>',
		deleteButtonSrc = '<button type="button" class="delete"><img src="'+SHOPP_PLUGINURI+'/core/ui/icons/delete.png" alt="-" width="16" height="16" /></button>',
		deleteButton = $(deleteButtonSrc).appendTo(controls),
		countryOptions = '';

	$.each(countries, function(value,label) {
		countryOptions += '<option value="'+value+'">'+label+'</option>';
	});

	countryMenu.html(countryOptions).change(function () {
		var $this = $(this);
		if (!selectedCountry) selectedCountry = $this.val();
		if ($.inArray(selectedCountry,countriesInUse) != -1)
			countriesInUse.splice($.inArray(selectedCountry,countriesInUse),1);
		selectedCountry = $this.val();
		if (!zones[selectedCountry]) countriesInUse.push(selectedCountry);
		// ratetable.trigger('disableCountriesInUse');

		// Update zone Menu
		zoneMenu.hide().empty(); // Clear out the zone menu to start from scratch

		if (zones[selectedCountry]) {
			var selectNext = false;
			// Add country zones to the zone menu
			$.each(zones[$(countryMenu).val()], function(value,label) {
				if ($.inArray(value,zonesInUse) != -1) option = $('<option></option>').attr('disabled',true).val(value).html(label).appendTo(zoneMenu);
				else option = $('<option></option>').val(value).html(label).appendTo(zoneMenu);
				if (selectNext) { // If the previous option was disabled, select this one in the menu
					selectNext = false;
					option.attr('selected',true);
				}
				// This option is seleted but disabled, we need to select the next option
				if (option.attr('selected') && option.attr('disabled')) selectNext = true;
			});
			// All of the zones have been selected, disable the country in the country menu
			if (selectNext) {
				allCountryZonesInUse.push($(countryMenu).val());
				// ratetable.trigger('disableCountriesInUse');
				countryMenu.attr('selectedIndex',countryMenu.attr('selectedIndex')+1).change();
			}
		}

		// Hide the zone menu if there are no zones for the selected country
		if (zoneMenu.children().length == 0) {
			zoneMenu.hide();
		} else zoneMenu.show(); // Show the zone menu when there are zones
		zoneMenu.change();

	}).change();

	rate.change(function () { this.value = asPercent(this.value,false,3,true); }).change();
	row.dequeue().hover(function () { controls.show(); },function () { controls.fadeOut('fast'); });
	deleteButton.click(function () { row.fadeOut('fast',function () { row.remove(); }); });
	localToggle.change(function () { LocalRates((data.locals?data.locals:false)); });
	new AddRuleButton(origin);
	quickSelects();
	if (data) load(data);


	function TaxRateRule (target,d) {
		var ruleid = rules++,
			ui = '<li><select name="settings[taxrates]['+id+'][rules]['+ruleid+'][p]" class="property"></select>&nbsp;<input type="text" name="settings[taxrates]['+id+'][rules]['+ruleid+'][v]" size="25" class="value" /></li>',
			rule = $(ui),
			property = rule.find('select.property'),
			value = rule.find('input.value'),
			options = '';

		$.each(RULE_LANG, function(value,label) {
			options += '<option value="'+value+'">'+label+'</option>';
		});

		property.html(options);

		if (d) {
			if (d.p) property.val(d.p);
			if (d.v) value.val(d.v);
		}

		property.change(function () {
			value.unbind('keydown').unbind('keypress').suggest(
				sugg_url+'&action=shopp_suggestions&t='+$(this).val(),
				{ delay:500, minchars:2 }
			);
		}).change();

		new DeleteRuleButton(rule);
		new AddRuleButton(rule);
		if (target == origin) {
			scope.show();
			rule.appendTo(conditions);
			return;
		}
		if (target) rule.insertAfter(target);
		else rule.appendTo(conditions);

	}

	function LocalRates (d) {
		var label,counter,ui,instructions,ratelist,uploadButton,pos,
			src = '<div class="local-rates"><div class="label"><label>'+LOCAL_RATES+' <span class="counter"></span><input type="hidden" name="settings[taxrates]['+id+'][locals]" value="" /></label><button type="button" name="toggle" class="toggle">&nbsp;</button></div><div class="ui"><p>'+LOCAL_RATE_INSTRUCTIONS+'</p><ul></ul><button type="button" name="upload" class="button-secondary">Upload</button></div>',
			panel = origin.find('div.local-rates');

		if (!panel.get(0)) panel = $(src).appendTo(origin);
		else panel.toggle();

		ui = panel.find('div.ui');
		label = panel.find('div.label');
		toggle = label.find('button.toggle');
		label.unbind('click').click(function () { ui.slideToggle('fast'); toggle.trigger('toggle.clicked'); });
		counter = label.find('span.counter');
		instructions = ui.find('p');
		ratelist = ui.find('ul');
		uploadButton = ui.find('button');

		toggle.bind('toggle.clicked',function () {
			var $button = $(this),
				step = 20,
				max = 180;

			function openIcon () {
				pos += step;
				$button.css('background-position',pos+'px top');
				if (pos < 0) setTimeout(openIcon,20);
				else $button.css('background-position',null).removeClass('closed');
			}

			function closeIcon () {
				pos -= step;
				$button.css('background-position',pos+'px top');
				if (Math.abs(pos) < max) setTimeout(closeIcon,20);
				else $button.css('background-position',null).addClass('closed');
			}

			if (pos < 0) return setTimeout(openIcon,20);
			else return setTimeout(closeIcon,20);

		});

		zoneMenu.change(function () {
			var locales = false;
			if (localities[countryMenu.val()] && localities[countryMenu.val()][$(this).val()])
				locales = localities[countryMenu.val()][$(this).val()];
			listings(locales);
		});

		uploadButton.upload({
			name: 'shopp',
			action: upload_url,
			params: {
				'action':'shopp_upload_local_taxes'
			},
			onSubmit: function() {
				uploadButton.attr('disabled',true).addClass('updating').parent().css('width','100%');
			},
			onComplete: function(results) {
				uploadButton.removeAttr('disabled').removeClass('updating');
				try {
					r = $.parseJSON(results);
					if (r.error) alert(r.error);
					else listings(r);
				} catch (ex) { alert(LOCAL_RATES_UPLOADERR); }
			}
		});

		if (d) {
			ui.hide();
			pos = -180;
			toggle.addClass('closed');
			listings(d);
		}

		function listings (list) {
			var ratesrc = '',count = 0;
			ratelist.html('');
			counter.html('');
			if (!list) return instructions.show();
			else instructions.hide();

			$.each(list, function(index,element) {
				var label = index,value = element;
				if (list instanceof Array) { label = element; value = 0; }
				ratesrc += '<li><label><input type="text" name="settings[taxrates]['+id+'][locals]['+label+']" size="6" value="'+value+'" /> '+label+'</label></li>';
				count++;
			});

			ratelist.html(ratesrc).find('input').focus(function() { this.select(); }).change(function () {
				this.value = asPercent(this.value,false,3,true);
				$(this).attr('title', asPercent( asNumber(this.value)+asNumber(rate.val()),false,3,true ) );
			}).change();

			counter.html('('+count+')');
		}

	}

	function DeleteRuleButton (target) {
		var button = $(deleteButtonSrc).prependTo(target).click(function () {
			if (conditions.find('li').size() == 3) scope.hide();
			target.fadeOut('fast',function () { target.remove(); });
		});
		target.hover(function () { button.css('opacity',1); },function () { button.animate({'opacity':0},'fast'); });
	}

	function AddRuleButton (target) {
		$(addButtonSrc).appendTo(target).click(function () {
			new TaxRateRule(target);
		});
	}

	function load (d) {
		if (!d) return;
		if (d.rate) rate.val(d.rate).change();
		if (d.country) countryMenu.val(d.country).change();
		if (d.zone) zoneMenu.val(d.zone).change();
		if (d.logic) scope.find('select').val(d.logic).change();
		if (d.localrates && d.localrates == "on") localToggle.find('input').attr('checked',true).change();

		if (d.rules) {
			$.each(d.rules,function (id,r) {
				new TaxRateRule(origin,r);
			});
		}
	}

}

jQuery(document).ready(function () {
	var $ = jqnc();
	if (!ratetable.get(0)) return;

	$('#add-taxrate').click(function() { new TaxRate(); });

	ratetable.empty();
	if (rates) $(rates).each(function () { new TaxRate(this); });
	else new TaxRate();

});
