/*!
 * cart.js - Shopp cart behaviors
 * Copyright © 2008-2010 by Ingenesis Limited
 * Licensed under the GPLv3 {@see license.txt}
 */

/**
 * Makes a request to add the selected product/product variation
 * to the shopper's cart
 **/
function addtocart (form) {
	var $ = jqnc(),
		options = $(form).find('select.options'),selections;
	if (options && options_default) {
		selections = true;
		for (menu in options) 
			if (options[menu].selectedIndex == 0 && options[menu][0].value == "") selections = false;

		if (!selections) {
			if (!options_required) options_required = "You must select the options for this item before you can add it to your shopping cart.";
			alert(options_required);
			return false;
		}
	}

	if ($(form).find('input.addtocart').hasClass('ajax-html')) 
		ShoppCartAjaxRequest(form.action,$(form).serialize(),'html');
	else if ($(form).find('input.addtocart').hasClass('ajax')) 
		ShoppCartAjaxRequest(form.action,$(form).serialize());
	else form.submit();

	return false;
}

/**
 * Overridable wrapper function to call cartajax.
 * Developers can recreate this function in their own
 * custom JS libraries to change the way cartajax is called.
 **/
function ShoppCartAjaxRequest (url,data,response) {
	if (!response) response = "json";
	var $ = jqnc(),
		datatype = ((response == 'json')?'json':'string');
	$.ajax({
		type:"POST",
		url:url,
		data:data+"&response="+response,
		timeout:10000,
		dataType:datatype,
		success:function (cart) {
			ShoppCartAjaxHandler(cart,response);
		},
		error:function () { }
	});
}

/**
 * Overridable wrapper function to handle cartajax responses.
 * Developers can recreate this function in their own
 * custom JS libraries to change the way the cart response
 * is processed and displayed to the shopper.
 **/
function ShoppCartAjaxHandler (cart,response) {
	var $ = jqnc(),
		widget = $('.widget_shoppcartwidget div.widget-all'),
		actions = widget.find('ul'),
		display = $('#shopp-cart-ajax'),
		item = $('<div class="added"></div>');

	if (response == "html") return display.html(cart);
	
	added = display.find('div.added').empty().hide(); // clear any previous additions
	if (added.length == 1) item = added;
	else item.prependTo(display).hide();
	
	if (cart.Item.image)
		$('<p><img src="'+cart.imguri+cart.Item.image.id+'" alt="" width="96"  height="96" /></p>').appendTo(item);
	$('<p />').html('<strong>'+cart.Item.name+'</strong>').appendTo(item);
	// if (cart.Item.optionlabel.length > 0)
	// 	$('<li></li>').html(cart.Item.optionlabel).appendTo(item);
	$('<p />').html(asMoney(cart.Item.unitprice)).appendTo(item);
	
	widget.find('p.status')
		.html('<a href="'+cart.url+'"><span id="shopp-sidecart-items">'+cart.Totals.quantity+'</span> '+
				'<strong>Items</strong> &mdash; <strong>Total</strong> '+
				'<span id="shopp-sidecart-total">'+asMoney(cart.Totals.total)+'</span></a>');

	if (actions.length != 1) actions = $('<ul />').appendTo(widget);
	actions.html('<li><a href="'+cart.url+'">'+cart.label+'</a></li><li><a href="'+cart.checkouturl+'">'+cart.checkoutLabel+'</a></li>');
	item.slideDown();
}

/**
 * DOM-ready initialization
 **/
jQuery(document).ready(function() {
	var $=jqnc();
	// Adds behaviors to shopping cart controls
	$('#cart #shipping-country').change(function () {
		this.form.submit();
	});
	
	// "Add to cart" button behaviors
	$('input.addtocart').each(function() {
		var button = $(this),
			form = button.parents('form.product');
		if (!form) return false;
		form.submit(function (e) {
			e.preventDefault();
			addtocart(this);
		});
		if (button.attr('type') == "button") 
			button.click(function() { form.submit(); });
	});

});