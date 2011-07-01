/*!
 * orders.js - Behaviors for the order manager
 * Copyright © 2011 by Ingenesis Limited. All rights reserved.
 * Licensed under the GPLv3 {@see license.txt}
 */

jQuery(document).ready( function($) {

	$.template('shipment-ui',$('#shipment-ui'));
	$.template('shipnotice-ui',$('#shipnotice-ui'));

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
							debuglog(tracknum);
							$.each(carriers,function (c,r) {
								debuglog(new RegExp(r[1].substr( 1,r[1].length-2 )));
								if (tracknum.match( new RegExp(r[1].substr( 1,r[1].length-2 )) ) != null) {
									debuglog(c);
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
					ui.fadeRemove();
					$this.show();
				});

				ui.appendTo(headline);


		});

});