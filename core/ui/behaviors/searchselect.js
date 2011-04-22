/*!
 * searchselect.js - Search Selector UI behaviors
 * Copyright © 2011 by Ingenesis Limited. All rights reserved.
 * Licensed under the GPLv3 {@see license.txt}
 */

function SearchSelector (type,parent,url,field,label,classname,freeform) {
	var $ = jqnc(),
		_ = this,
		$this = $(this),form,
		ui = $('<ul class="search-select">'+
					'<li><input type="text" name="entry" class="input" /></li>'+
				'</ul>'),
		$input = ui.find('input.input').focus(function () {
			ui.find('li.selected').removeClass('selected');
			$(document).unbind('keydown');
			$(this).unbind('keydown').keydown(_.keyhandler);
		});

		_.ui = ui;

		_.stopEvent = function (e) {
			if (e.preventDefault)
				e.preventDefault();
			if (e.stopPropagation)
				e.stopPropagation();

			e.cancelBubble = true;
			e.returnValue = false;
		};

		_.keyhandler = function (e) {
			var $input = $(this),
				entry = $input.val();


			if (!(/8$|9$|13$|46$|37$|39$/.test(e.keyCode))) return;
			var selection = ui.find('li.item.selected'),previous;

			if ($(e.target).hasClass('input')) {
				previous = $input.parent().prev();

				switch (e.keyCode) {

					case 9: // tab key
					case 13: // return key
						_.stopEvent(e);
						if (freeform && entry.length > 0) {
							ui.append(_.newItem('',entry));
							$input.val('').parent().appendTo(ui);
							$input.focus();
						}
						break;
					case 188: // comma key
						if (freeform && entry.length > 0) {
							_.stopEvent(e);
							ui.append(_.newItem('',entry));
							$input.val('').parent().appendTo(ui);
							$input.focus();
						}
					case 37:	// left arrow
					case 8:		// backspace key
					case 46:	// delete key
						if ($input.val().length > 0) break;
						_.stopEvent(e);
						if (!previous.length) break;
						$input.blur();
						previous.click();
						break;
				}
				return;
			}

			if (selection.length) {
				_.stopEvent(e);

				switch (e.keyCode) {
					case 8:  // backspace key
					case 46: // delete key
						selection.fadeRemove('fast',function () {
							$input.focus();
						});
						break;
					case 37:	// left arrow
						selection.prev('li.item').click();
						break;
					case 39: // right arrow
						selection.next('li').click();
						break;

				}
			}
		};

	_.newItem = function (id,label) {
		var ui = $('<li class="item"><input type="hidden" name="'+field+'['+id+']" value="'+label+'" />'+label+'<a href="#" class="remove"></a></li>').hoverClass().click(function (e) {
				if (e.preventDefault)
					e.preventDefault();
				if (e.stopPropagation)
					e.stopPropagation();

				e.cancelBubble = true;
				e.returnValue = false;

				ui.parent().find('li.item.selected').removeClass('selected');
				$(this).addClass('selected');
				$(document).unbind('keydown').keydown(_.keyhandler);
			});

			ui.find('a.remove').click(function () {
				ui.fadeRemove('fast');
			});
		return ui;
	};

	_.selection = function () {
		var $input = $(this),
			selection = $input.val(),
			selectid = $input.attr('alt'),
			$inputli = $input.parent(),
			$ui = $inputli.parent();

		$ui.append(_.newItem(selectid,selection));
		$inputli.appendTo($ui);
		$input.val('').attr('alt','').focus();
	};

	ui.appendTo(parent).click(function () { $input.focus(); }).suggest(
			url+'&action=shopp_suggestions&s='+type,
			{	delay:300,
				minchars:2,
				format:'json',
				showOnFocus:true,
				autoSelect:true,
				label:label,
				resultsClass:'search-select-results'+(classname?' '+classname:''),
				onSelect:this.selection
			});

	return this;
}