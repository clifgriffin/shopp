function quickSelects (target) {
	(function($) {
	if (!target) target = $('.selectall');
	else target = $(target).find('.selectall');
	$(target).each(function(input) {
		$(this).mouseup(function (e) {
			this.select();
		});
	});
	})(jQuery)
}

/**
 * asMoney ()
 * 
 * Add notation to an integer to display it as money.
 **/
function asMoney (number,format) {
	if (currencyFormat && !format) format = currencyFormat;
	if (!format) {
		format = {
			"cpos":true,
			"currency":"$",
			"precision":2,
			"decimals":".",
			"thousands":","
		}
	}
	if (digits == null) var digits = 2;
	if (!currency) var currency = "$";
	if (!separator) var separator = ",";
	if (!decimal) var decimal = ".";
	
	number = asNumber(number);
	var d = number.toFixed(format['precision']).toString().split(".");
	var number = "";
	for (var i = 0; i < (d[0].length / 3); i++) 
		number = d[0].slice(-3*(i+1),d[0].length+(-3 * i)) + ((number.length > 0)?format['thousands'] + number:number);
	if (format['precision'] > 0) number += format['decimals'] + d[1];
	if (format['cpos']) return format['currency']+number;
	return number+format['currency'];
}

/**
 * asNumber ()
 * 
 * Convert a field with numeric and non-numeric characters
 * to a true integer for calculations.
 **/
var asNumber = function(number) {
	if (!number) number = 0;
	number = number.toString().replace(new RegExp(/[^0-9\.\,]/g),"");
	if (isNaN(new Number(number))) {
		number = number.replace(new RegExp(/\./g),"").replace(new RegExp(/\,/),"\.");
	}
	
	return new Number(number);
}

var CallbackRegistry = function() {
	this.callbacks = new Array();

	this.register = function (name,callback) {
		this.callbacks[name] = callback;
	}

	this.call = function(name,arg1,arg2,arg3) {
		this.callbacks[name](arg1,arg2,arg3);
	}	
}

function addEvent( obj, type, fn ) {
	if ( obj.addEventListener ) {
		obj.addEventListener( type, fn, false );
	}
	else if ( obj.attachEvent ) {
		var eProp = type + fn;
		obj["e"+eProp] = fn;
		obj[eProp] = function() { obj["e"+eProp]( window.event ); };
		obj.attachEvent( "on"+type, obj[eProp] );
	}
	else {
		obj['on'+type] = fn;
	}
};


function removeEvent( obj, type, fn ) {
	if ( obj.removeEventListener ) {
		obj.removeEventListener( type, fn, false );
	}
	else if ( obj.detachEvent ) {
		var eProp = type + fn;
		obj.detachEvent( "on"+type, obj[eProp] );
		obj['e'+eProp] = null;
		obj[eProp] = null;
	}
	else {
		obj['on'+type] = null;
	}
};

/**
 * formatFields ()
 * 
 * Find fields that need display formatting and 
 * run the approriate formatting.
 */
function formatFields () {
	(function($) {
	var f = $('input');
	for (i = 0; i < f.elements.length; i++) {
		if (f.elements[i].className.match("currency")) f.elements[i].value = asMoney(f.elements[i].value);
	}
	})(jQuery)
}


function addtocart () {
	this.form.submit();
}

function buttonHandlers () {
	var inputs = document.getElementsByTagName('input');
	for (var i = 0; i < inputs.length; i++) {
		var input = inputs[i];
		if (input.className.indexOf('addtocart') != -1) input.onclick = addtocart;
	}
}

function cartHandlers () {
	var form = document.getElementById('cart');
	if (form) {
		var shipcountry = document.getElementById('shipping-country');
		if (shipcountry) {
			shipcountry.onchange = function () {
				action.value = "estimates";
				form.submit();
			}
		}
	}
}

function shopp_debug () {
	(function($) {
		var overlay = $('<div id="debug" class="shopp overlay"></div>').appendTo(document.body);
		var debug = $('<div id="debug" class="shopp"></div>').appendTo(document.body);
		$('<h3>Shopp Debug Console</h3>').appendTo(debug);
		$('<h4></h4>').html('Memory:').appendTo(debug);
		$('<p></p>').html(memory_profile).appendTo(debug);
		$('<h4></h4>').html('Queries:').appendTo(debug);
		$('<p></p>').html('WP Total: '+wpquerytotal+'<br />Shopp Total: '+shoppquerytotal).appendTo(debug);
		$('<h4></h4>').html('Query Statements:').appendTo(debug);
		var querylist = $('<ul></ul>').appendTo(debug);
		for (var q in shoppqueries) {
			$("<li></li>").html(shoppqueries[q]).appendTo(querylist);
		}

		if (shoppobjectdump) {
			$('<h4></h4>').html('Objects:').appendTo(debug);
			$('<pre></pre>').html(shoppobjectdump).appendTo(debug);
		}
		
		
		debug.click(function () {
			overlay.remove();
			debug.remove();
		});
		
		return true;
	})(jQuery)
}

function shopp_preview(id) {
	(function($) {
		var target = $('#preview-'+id);
		if (!target.hasClass('active')) {
			target.addClass('active').hide();
			target.appendTo('#gallery ul.previews').fadeIn(500,function() {
					$('#gallery ul.previews li').not('li:last').removeClass('active');
			});
		}
	})(jQuery)
}

addEvent(window,'load',function () {
	buttonHandlers();
	cartHandlers();
});

// Fix for ThickBox
var tb_pathToImage = "/wp-content/plugins/shopp/icons/loading.gif";
var tb_closeImage = "/wp-includes/js/thickbox/tb-close.png";
