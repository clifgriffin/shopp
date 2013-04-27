/*!
 * address.js - Description
 * Copyright © 2012 by Ingenesis Limited. All rights reserved.
 * Licensed under the GPLv3 {@see license.txt}
 */
jQuery(document).ready(function($) {

	$('#billing-country,#shipping-country').change(function (e,init) {
		var prefix = $(this).attr('id').split('-')[0],
			country = $(this).val(),
			state = $('#'+prefix+'-state'),
			menu = $('#'+prefix+'-state-menu'),
			options = '<option value=""></option>';

		if (menu.length == 0) return true;
		if (menu.hasClass('hidden')) menu.removeClass('hidden').hide();

		if (regions[country] || (init && menu.find('option').length > 1)) {
			state.setDisabled(true).addClass('_important').hide();
			if (regions[country]) {
				$.each(regions[country], function (value,label) {
					options += '<option value="'+value+'">'+label+'</option>';
				});
				if (!init) menu.empty().append(options).setDisabled(false).show().focus();
			}
			menu.setDisabled(false).show();
			$('label[for='+state.attr('id')+']').attr('for',menu.attr('id'));
		} else {
			menu.empty().setDisabled(true).hide();
			state.setDisabled(false).show().removeClass('_important');

			$('label[for='+menu.attr('id')+']').attr('for',state.attr('id'));
			if (!init) state.val('').focus();
		}
	}).trigger('change',[true]);

});
