/*!
 * address.js - Description
 * Copyright © 2012 by Ingenesis Limited. All rights reserved.
 * Licensed under the GPLv3 {@see license.txt}
 */

(function($) {
	jQuery.fn.upstate = function () {

		if ( typeof regions === 'undefined' ) return;

		$(this).change(function (e,init) {
			var $this = $(this),
				prefix = $this.attr('id').split('-')[0],
				country = $this.val(),
				state = $this.parents().find('#' + prefix + '-state'),
				menu = $this.parents().find('#' + prefix + '-state-menu'),
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
					if (menu.hasClass('auto-required')) menu.addClass('required');
				} else {
					if (menu.hasClass('auto-required')) menu.removeClass('required');
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

		return $(this);

	};

})(jQuery);

jQuery(document).ready(function($) {
	var sameaddr = $('.sameaddress'),
		shipFields = $('#shipping-address-fields'),
		billFields = $('#billing-address-fields'),
		keepLastValue = function () { // Save the current value of the field
			$(this).attr('data-last', $(this).val());
		};

	// Handle changes to the firstname and lastname fields
    $('#firstname,#lastname').each(keepLastValue).change(function () {
		var namefield = $(this); // Reference to the modified field
			lastfirstname = $('#firstname').attr('data-last'),
			lastlastname = $('#lastname').attr('data-last'),
			firstlast = ( ( $('#firstname').val() ).trim() + " " + ( $('#lastname').val() ).trim() ).trim();

			namefield.val( (namefield.val()).trim() );

		// Update the billing name and shipping name
		$('#billing-name,#shipping-name').each(function() {
			var value = $(this).val();

			if ( value.trim().length == 0 ) {
				// Empty billing or shipping name
				$('#billing-name,#shipping-name').val(firstlast);
			} else if ( '' != value && ( $('#firstname').val() == value || $('#lastname').val() == value ) ) {
				// Only one name entered (so far), add the other name
				$(this).val(firstlast);
			} else if ( 'firstname' == namefield.attr('id') && value.indexOf(lastlastname) != -1 ) {
				// firstname changed & last lastname matched
				$(this).val( value.replace(lastfirstname, namefield.val()).trim() );
			} else if ( 'lastname' == namefield.attr('id') && value.indexOf(lastfirstname) != -1 ) {
				// lastname changed & last firstname matched
				$(this).val( value.replace(lastlastname, namefield.val()).trim() );
			}

		});

    }).change(keepLastValue);

	// Update state/province
	$('#billing-country,#shipping-country').upstate();

	// Toggle same shipping address
	sameaddr.change(function (e,init) {
		var refocus = false,
			bc = $('#billing-country'),
			sc = $('#shipping-country'),
			prime = 'billing' == sameaddr.val() ? shipFields : billFields,
			alt   = 'shipping' == sameaddr.val() ? shipFields : billFields;

		if (sameaddr.is(':checked')) {
			prime.removeClass('half');
			alt.hide().find('.required').setDisabled(true);
		} else {
			prime.addClass('half');
			alt.show().find('.disabled:not(._important)').setDisabled(false);
			if (!init) refocus = true;
		}
		if (bc.is(':visible')) bc.trigger('change.localemenu',[init]);
		if (sc.is(':visible')) sc.trigger('change.localemenu',[init]);
		if (refocus) alt.find('input:first').focus();
	}).trigger('change',[true])
		.click(function () { $(this).change(); }); // For IE compatibility
});