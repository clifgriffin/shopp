/*!
 * setup.js - General settings screen behaviors
 * Copyright © 2008-2010 by Ingenesis Limited
 * Licensed under the GPLv3 {@see license.txt}
 */

jQuery(document).ready(function () {
	var $ = jqnc(),
		activationButton = $('#activation-button'),
		activationStatus = $('#activation-status'),
		baseop = $('#base_operations'),
		baseopZone = $('#base_operations_zone');

	function activation (response,success,request) {
		var button = activationButton.attr('disabled',false).removeClass('updating'),
			keyin = $('#update-key'),
			code = (response instanceof Array)?response[0]:false,
			key = (response instanceof Array)?response[1]:false;
			type = (response instanceof Array)?response[2]:false;

		if (code === false) {
			button.attr('disabled',true);
			return activationStatus.html(keyStatus['-000']).addClass('activating').show();
		}

		if (button.hasClass('deactivation')) button.html(SHOPP_DEACTIVATE_KEY);
		else button.html(SHOPP_DEACTIVATE_KEY);

		if (code == "1") {
			if (type == "dev" && keyin.attr('type') == "text") keyin.replaceWith('<input type="password" name="updatekey" id="update-key" value="'+keyin.val()+'" readonly="readonly" size="40" />');
			else keyin.attr('readonly',true);
			button.html(SHOPP_DEACTIVATE_KEY).addClass('deactivation');
		} else {
			if (keyin.attr('type') == "password")
				keyin.replaceWith('<input type="text" name="updatekey" id="update-key" value="" size="40" />');
			keyin.attr('readonly',false);
			button.html(SHOPP_ACTIVATE_KEY).removeClass('deactivation');
		}

		if (code !== false) {
			activationStatus.html(keyStatus[code]);
			if (code != 0 && code != 1) activationStatus.addClass('activating').show();
			else activationStatus.removeClass('activating').show();
		} else activationStatus.addClass('activating').show();

	}

	if (activated) activation([1],'success');
	else activationStatus.show();

	activationButton.click(function () {
		$(this).html(SHOPP_CONNECTING+"&hellip;").attr('disabled',true).addClass('updating');
		if ($(this).hasClass('deactivation'))
			$.getJSON(deact_key_url+'&action=shopp_deactivate_key',activation);
		else $.getJSON(act_key_url+'&action=shopp_activate_key&key='+$('#update-key').val(),activation);
	}).html(activated?SHOPP_DEACTIVATE_KEY:SHOPP_ACTIVATE_KEY);

	if (!baseop.val() || baseop.val() == '') baseopZone.hide();
	if (!baseopZone.val()) baseopZone.hide();

	baseop.change(function() {
		if (baseop.val() == '') {
			baseopZone.hide();
			return true;
		}

		$.getJSON(zones_url+'&action=shopp_country_zones&country='+baseop.val(),
			function(data) {
				baseopZone.hide();
				baseopZone.empty();
				if (!data) return true;

				$.each(data, function(value,label) {
					option = $('<option></option>').val(value).html(label).appendTo('#base_operations_zone');
				});
				baseopZone.show();

		});
	});

	$('#selectall_targetmarkets').change(function () {
		if ($(this).attr('checked')) $('#target_markets input').not(this).attr('checked',true);
		else $('#target_markets input').not(this).attr('checked',false);
	});

	function addLabel (id,label,location) {
		if (isNaN(id)) return;
		var i = labelInputs.length+1,id = !id?i:id,
			entry = '<li id="item-'+i+'"><span>'+
					'<input type="text" name="settings[order_status]['+id+']" id="label-'+i+'" size="14" />'+
					'<button type="button" class="delete">'+
					'<img src="'+SHOPP_PLUGINURI+'/core/ui/icons/delete.png" alt="Delete" width="16" height="16" />'+
					'</button>'+
					'<button type="button" class="add">'+
					'<img src="'+SHOPP_PLUGINURI+'/core/ui/icons/add.png" alt="Add" width="16" height="16" />'+
					'</button></span></li>',
			li = !location?$(entry).appendTo('#order-statuslabels'):$(entry).insertAfter(location),
			input = li.find('input').val(label?label:''),
			deleteButton = li.find('button.delete').hide().click(function () {
				if (confirm(SHOPP_CONFIRM_DELETE_LABEL)) li.remove();
			}),
			addButton = li.find('button.add').click(function () {
				addLabel(null,null,'#'+li.attr('id'));
			}),
			wrap = li.find('span').hover(function() {
				if (i > 0) deleteButton.show();
			}, function () {
				deleteButton.hide();
			});

		labelInputs.push(li);
	}

	if (labels) for (var id in labels) addLabel(id,labels[id]);
	else addLabel();

});