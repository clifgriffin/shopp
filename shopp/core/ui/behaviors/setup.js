/*!
 * setup.js - General settings screen behaviors
 * Copyright © 2008-2010 by Ingenesis Limited
 * Licensed under the GPLv3 {@see license.txt}
 */

jQuery(document).ready(function ($) {
	var baseop = $('#base_operations'),
		baseopZone = $('#base_operations_zone'),
		activationStatus = $('#activation-status'),

		activationButton = $('#activation-button').click(function () {
			$(this).val($sl.connecting).attr('disabled',true).addClass('updating');
			if ($(this).hasClass('deactivation'))
				$.getJSON(deact_key_url+'&action=shopp_deactivate_key',activation);
			else $.getJSON(act_key_url+'&action=shopp_activate_key&key='+$('#update-key').val(),activation);
		}).html(activated?$sl.deactivate_button:$sl.activate_button),

		activation = function (response,success,request) {
			var button = activationButton.attr('disabled',false).removeClass('updating'),
				keyin = $('#update-key'),
				code = (response instanceof Array)?response[0]:false,
				key = (response instanceof Array)?response[1]:false;
				type = (response instanceof Array)?response[2]:false;

			if (code === false) {
				button.attr('disabled',true);
				return activationStatus.html($sl['ks_000']).addClass('activating').show();
			}

			if (button.hasClass('deactivation')) button.html($sl.deactivate_button);
			else button.html($sl.deactivate_button);

			if (code == '1') {
				if (type == "dev" && keyin.attr('type') == "text") keyin.replaceWith('<input type="password" name="updatekey" id="update-key" value="'+keyin.val()+'" readonly="readonly" size="56" />');
				else keyin.attr('readonly',true);
				button.val($sl.deactivate_button).addClass('deactivation');
			} else {
				if (keyin.attr('type') == "password")
					keyin.replaceWith('<input type="text" name="updatekey" id="update-key" value="" size="56" />');
				keyin.attr('readonly',false);
				button.val($sl.activate_button).removeClass('deactivation');
			}

			if (code !== false) {
				if (code != '0' && code != '1') activationStatus.addClass('activating');
				else activationStatus.removeClass('activating');
				code = 'ks'+code.toString().replace(/\-/,'_');
				status = $('<div/>').html($sl[code]).text();
				activationStatus.html(status).show();
			} else activationStatus.addClass('activating').show();

		};

	if (activated) activation([1],'success');
	else activationStatus.show();

	if (!baseop.val() || baseop.val() == '') baseopZone.hide();
	if (!baseopZone.val()) baseopZone.hide();

	baseop.change(function() {
		if (baseop.val() == '') {
			baseopZone.hide().attr('disabled',true);
			return true;
		}

		$.getJSON(zones_url+'&action=shopp_country_zones&country='+baseop.val(),
			function(data) {
				baseopZone.hide().empty().attr('disabled',true);;
				if (!data) return true;

				$.each(data, function(value,label) {
					option = $('<option></option>').val(value).html(label).appendTo('#base_operations_zone');
				});
				baseopZone.show().removeAttr('disabled');

		});
	});

	$('#selectall_targetmarkets').change(function () {
		if ($(this).attr('checked')) $('#target_markets input').not(this).attr('checked',true);
		else $('#target_markets input').not(this).attr('checked',false);
	});

});