/*!
 * image-settings.js - Description
 * Copyright © 2011 by Ingenesis Limited. All rights reserved.
 * Licensed under the GPLv3 {@see license.txt}
 */

jQuery(document).ready( function($) {
	$.template('editor',$('#editor'));
	var editing = false;

	$('#images a.edit, a.add-new').click(function (e) {
		e.preventDefault();
		var $this = $(this),
			row = $this.parents('tr').hide(),
			id = row.size() > 0?row.attr('id').substr(14):false,
			data = images[id]?images[id]:{name:''},
			ui = $.tmpl('editor',data),
			sm = ui.find('select.scaling-menu').val(data.scaling),
			qm = ui.find('select.quality-menu').val(data.quality),
			percentage = function () { $(this).val( asPercent( $(this).val() ) ); },
			ps = ui.find('.percentage').each(percentage).change(percentage),
			cancel = ui.find('a.cancel');

		$this.cancel = function (e) {
			if (e) e.preventDefault();
			editing = false;
			ui.remove();
			row.fadeIn('fast');
		};
		cancel.click($this.cancel);

		if (editing) editing.cancel(false);

		if (row.size() > 0) ui.insertAfter(row);
		else ui.prependTo('#image-setting-table');

		editing = $this;
	});

	$('#images a.delete').click(function() {
		if (confirm($is.confirm)) return true;
		else return false;
	});

});