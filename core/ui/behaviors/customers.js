/*!
 * customers.js - Behaviors for the order manager
 * Copyright © 2011 by Ingenesis Limited. All rights reserved.
 * Licensed under the GPLv3 {@see license.txt}
 */

jQuery(document).ready( function($) {

$.template('address-ui', $('#address-editor'));

function edit_address (type) {
	var $this = $(this),
		data = address[ type ],
		ui = $.tmpl('address-ui', data);

	if ( data && data.statemenu )
		$('#' + type + '-state-menu').html(data.statemenu).selectize({
			openOnFocus: true,
			diacritics: true,
			allowEmptyOption: true,
			selectOnTab: true,
			create: true
		});

	$('#' + type + '-country').upstate().selectize({
		openOnFocus: true,
		diacritics: true,
		allowEmptyOption: true,
		selectOnTab: true,
		create: false
	});

}

edit_address('billing');
edit_address('shipping');

});