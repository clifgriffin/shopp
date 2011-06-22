/*!
 * payments.js - Payment method settings UI behaviors
 * Copyright © 2011 by Ingenesis Limited. All rights reserved.
 * Licensed under the GPLv3 {@see license.txt}
 */

jQuery(document).ready( function($) {
	$.each(gateways,function (index,gateway) {
		$.template(gateway+'-editor',$('#'+gateway+'-editor'));
	});

	var editing = false,
		menu = $('#payment-option-menu'),
		notice = $('#no-payment-settings'),

		AddPayment = function (e) {
			if (e) e.preventDefault();
			if (editing) editing.cancel(false);
			notice.hide();
			var $this = $(this),
				row = $this.parents('tr').hide(),
				selected = menu.val().toLowerCase(),
				id = row.size() > 0?row.attr('id').substr(16):selected,
				ui = $.tmpl(id+'-editor'),
				cancel = ui.find('a.cancel'),
				selectall = ui.find('input.selectall-toggle').change(function (e) {
					var $this = $(this),
						options = $this.parents('ul').find('input');
					options.attr('checked',$this.attr('checked'));
				});

			if (row.size() == 0) row = $('#payment-setting-'+id).hide();
			menu.get(0).selectedIndex = 0;

			$this.cancel = function (e) {
				if (e) e.preventDefault();
				editing = false;
				ui.remove();
				row.fadeIn('fast');
				if (notice.size() > 0) notice.show();
			};
			cancel.click($this.cancel);

			if (row.size() > 0) ui.insertAfter(row);
			else ui.prependTo('#payments-settings-table');

			editing = $this;

		};

	$('#payments a.edit').click(AddPayment);
	$('#payment-option-menu').change(AddPayment);

	$('#payments a.delete').click(function() {
		if (confirm($ps.confirm)) return true;
		else return false;
	});


});