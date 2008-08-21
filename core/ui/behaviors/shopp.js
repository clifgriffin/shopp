
/**
 * stripe()
 * 
 * Adds alternating classes to table row cells.
 */
function stripe () {
	// the flag we'll use to keep track of 
	// whether the current row is odd or even
	var even = false;

	// if arguments are provided to specify the colors
	// of the even & odd rows, then use the them;
	// otherwise use the following defaults:
	var evenClass = arguments[1] ? arguments[1] : "even";
	var oddClass = arguments[2] ? arguments[2] : "odd";
	
	// Get every table in the document,
	// if there are none, abort
	var tables = document.getElementsByTagName("table");
	if (! tables) return;
	
	for (var g = 0; g < tables.length; g++) {
					
		// by definition, tables can have more than one tbody
		// element, so we'll have to get the list of child
		// <tbody>s 
		var tbodies = tables[g].getElementsByTagName("tbody");

		// and iterate through them...
		for (var h = 0; h < tbodies.length; h++) {

			// Only process tables where the table has stripe class
			// or the tbody has stripe class
			if (tables[g].hasClass('stripe') || tbodies[h].hasClass('stripe')) {
				
				// find all the <tr> elements... 
				var trs = tbodies[h].getElementsByTagName("tr");
				// ... and iterate through them
				for (var i = 0; i < trs.length; i++) {

					trs[i].addClass( even ? evenClass : oddClass );
					even = !even; // flip from odd to even, or vice-versa

				} // end trs loop
				
			} // end if 'stripe' class
			
		} // end tbodies loop
			
	} // end tables loop
}

// function dynamicLabels (target) {
// 	var inputs = (target)?target.getElements('.label'):document.getElements('.label');
// 
// 	inputs.each(function(input) {
// 
// 		// Remove label class for any fields whose values have changed in 
// 		// a script, not the interface, likely after being labelled 
// 		if (input.hasClass('labelled') && input.value != input.title)
// 			input.removeClass('labelled');
// 
// 		// Label empty fields that are not read-only or already labelled
// 		if (input.value == "" && !input.hasClass('labelled') && !(input.readOnly || input.disabled)) {
// 			input.value = input.title;
// 			input.addClass('labelled');
// 		}
// 
// 		// Remove labels for any fields that are read-only that
// 		// likely got labelled before they became read-only
// 		if (input.hasClass('labelled') && (input.readOnly || input.disabled)) {
// 			input.value = "";
// 			input.removeClass('labelled');
// 		}
// 		
// 
// 		// Label and unlabel based on user interaction
// 		input.addEvents({
// 			'focus': function (e) {
// 				if (input.value == input.title) {
// 					input.value = "";
// 					input.removeClass('labelled');
// 				}
// 			},
// 			'blur': function (e) {
// 				if (input.value == "" && !(input.readOnly || input.disabled)) {
// 					input.value = input.title;
// 					input.addClass('labelled');
// 				}
// 			}			
// 		});
// 		
// 	});
// 
// }

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

// function youAreHere () {
// 	if (!$('navigation')) return;
// 	var places = $('navigation').getElements('li');
// 	places.each(function(location) {
// 		if (location.getAttribute('rel') == document.body.id) location.addClass("youarehere");
// 	});
// }

/**
 * asMoney ()
 * 
 * Add notation to an integer to display it as money.
 **/
function asMoney (number,digits,currency,separator,decimal) {
	if (digits == null) var digits = 2;
	if (!currency) var currency = "$";
	if (!separator) var separator = ",";
	if (!decimal) var decimal = ".";
	
	number = asNumber(number);
	var d = number.toFixed(digits).toString().split(".");
	var number = "";
	for (var i = 0; i < (d[0].length / 3); i++) 
		number = d[0].slice(-3*(i+1),d[0].length+(-3 * i)) + ((number.length > 0)?separator + number:number);
	if (digits > 0) number += decimal + d[1];
	return currency + number;
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
