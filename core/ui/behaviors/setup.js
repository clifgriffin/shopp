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
			var $this = $(this),
				$parent = $this.parent(),
				activate = act_key_url+'&action=shopp_activate_key&key='+$('#update-key').val(),
				deactivate = deact_key_url+'&action=shopp_deactivate_key',
				request = activate;

			$this.val($sl.connecting).attr('disabled',true).addClass('updating');
			$parent.spin({ lines: 8, length: 2, width: 2, radius: 3, className: 'spin', left: '7px', color: '#444' });

			if ( $(this).hasClass('deactivation') ) request = deactivate;
			$.getJSON(request,activation).fail(failure).always(completed);

		}).html(activated?$sl.deactivate_button:$sl.activate_button),

		activation = function (response,success,request) {
			var button = activationButton.attr('disabled',false).removeClass('updating'),
				spinner = button.parent().spin(false),
				keyin = $('#update-key'),
				code = (response instanceof Array)?response[0]:false,
				key = (response instanceof Array)?response[1]:false,
				type = (response instanceof Array)?response[2]:false;

			if (!response || code === false) {
				button.attr('disabled',true);
				activationStatus.html($sl['k_001']).addClass('activating').show();
				return;
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
				activationStatus.html($sl[code]?$sl[code]:'A problem with your WordPress install causing problems with activation/deactivation.').show();
			} else activationStatus.addClass('activating').show();

		},

		failure = function (request,status) {
			var code = 'k_1';
			if ( 'parsererror' == status ) code = 'k_001';
			activationStatus.html($sl[code]).addClass('activating').show();
			activationButton.val($sl.fail);
		},

		completed = function (request,status) {
			var button = activationButton.removeClass('updating'),
				spinner = button.parent().spin(false);
		}

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