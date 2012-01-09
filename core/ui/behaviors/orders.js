/*!
 * orders.js - Behaviors for the order manager
 * Copyright © 2011 by Ingenesis Limited. All rights reserved.
 * Licensed under the GPLv3 {@see license.txt}
 */

jQuery(document).ready( function($) {

	$.template('shipment-ui',$('#shipment-ui'));
	$.template('shipnotice-ui',$('#shipnotice-ui'));
	$.template('refund-ui',$('#refund-ui'));

	var manager = $('#order-manage'),
		headline = manager.find('div.headline'),

		cmo = '',
		ctr = $.each(carriers,function (code,carrier) {
			cmo += '<option value="'+code+'">'+carrier[0]+'</option>';
		}),

		shipbtn = $('#shipnote-button').click(function (e) {
			e.preventDefault();
			var $this = $(this).hide(),
				shipments = [],
				ui = $.tmpl('shipnotice-ui',{shipmentnum:2}),
				addshipli = ui.find('ol li'),

				upaddshipnum = function () {
					addshipli.find('span.number').text(shipments.length+1+'.');
				},

				addbtn = ui.find('#addship-button').click(function (e) {
					e.preventDefault();

					var shipnum = shipments.length,
						shipment = $.tmpl('shipment-ui',{id:shipnum,num:shipnum+1}),

						carriermenu = shipment.find('select').html(cmo),

						tracking = shipment.find('.tracking').change(function () {
							var tracknum = $(this).val().toUpperCase().replace(/[^A-Z0-9]/,'');
							$.each(carriers,function (c,r) {
								if (tracknum.match( new RegExp(r[1].substr( 1,r[1].length-2 )) ) != null) {
									carriermenu.val(c);
									return false;
								}
							});
						}),

						deletebtn = shipment.find('button.delete').click(function (e) {
							e.preventDefault();
							shipment.remove();
							shipments.splice(shipnum,1);
							upaddshipnum();

						}).hide();

					if (shipnum > 0)
						shipment.hover(function() {deletebtn.show();},function () {deletebtn.fadeOut('fast');});
					shipments.push(shipment.insertBefore(addshipli));
					upaddshipnum();

				}).click(),

				cancel = ui.find('#cancel-ship').click(function (e) {
					e.preventDefault();
					ui.fadeRemove('fast',function () {
						$this.show();
					});
				});

				ui.appendTo(headline);

		}),

		refundbtn = $('#cancel-order, #refund-button').click(function (e) {
			e.preventDefault();

			var $this = $(this).hide(),
				data = ('refund-button' == $this.attr('id') ?
					{ action:'refund',title:$om.ro,cancel:$om.cancel,mark:$om.mr,process:$om.pr,reason: $om.rr } :
					{ action:'cancel',title:$om.co,cancel:$om.dnc,mark:$om.mc,process:$om.co,reason: $om.rc,disable_amount: ' disabled="disabled"' }),
				ui = $.tmpl('refund-ui',data),

				cancel = ui.find('#cancel-refund').click(function (e) {
					e.preventDefault();
					ui.fadeRemove('fast',function () {
						$this.show();
					});

				});

			ui.appendTo(headline);
		}),

		printbtn = $('#print-button').click(function (e) {
			e.preventDefault();
			var frame = $('#print-receipt').get(0), fw = frame.contentWindow;
			if ($.browser.opera || $.browser.msie) {
				var preview = window.open(fw.location.href+"&print=auto");
				$(preview).load(function () {	preview.close(); });
			} else {
				fw.focus();
				fw.print();
			}
			return false;
		});


});