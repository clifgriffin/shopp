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

function copyOf (src) {
	for (v in src) this[v] = src[v];
}

/**
 * asMoney ()
 * 
 * Add notation to an integer to display it as money.
 **/
function asMoney (number,format) {
	if (currencyFormat && !format) format = copyOf(currencyFormat);
	if (!format) {
		format = {
			"cpos":true,
			"currency":"$",
			"precision":2,
			"decimals":".",
			"thousands":","
		}
	}
	
	number = formatNumber(number,format);
	if (format['cpos']) return format['currency']+number;
	return number+format['currency'];
}

/**
 * asPercent ()
 * 
 * Add notation to an integer to display it as a percentage.
 **/
function asPercent (number,format) {
	if (currencyFormat && !format) format = copyOf(currencyFormat);
	if (!format) {
		format = {
			"decimals":".",
			"thousands":","
		}
	}
	format['precision'] = 1;
	return formatNumber(number,format)+"%";
}

/**
 * formatNumber ()
 * 
 * Formats a number to denote thousands with decimal precision.
 **/
function formatNumber (number,format) {
	if (!format) {
		format = {
			"precision":2,
			"decimals":".",
			"thousands":","
		}
	}

	number = asNumber(number);
	var d = number.toFixed(format['precision']).toString().split(".");
	var number = "";
	for (var i = 0; i < (d[0].length / 3); i++) 
		number = d[0].slice(-3*(i+1),d[0].length+(-3 * i)) + ((number.length > 0)?format['thousands'] + number:number);
	if (format['precision'] > 0) number += format['decimals'] + d[1];
	return number;
	
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

function PopupCalendar (target,month,year) {
	
	var _self = this;
	var DAYS_IN_MONTH = new Array(new Array(0, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31),
								  new Array(0, 31, 29, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31)
								 );


	var MONTH_NAMES = new Array("","January","February","March","April","May","June","July","August","September","October","November","December");
	var WEEK_DAYS = new Array("Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday");

	/* Date Constants */
	var K_FirstMissingDays = 639787; /* 3 Sep 1752 */
	var K_MissingDays = 11; /* 11 day correction */
	var K_MaxDays = 42; /* max slots in a calendar map array */ 
	var K_Thursday = 4; /* for reformation */ 
	var K_Saturday = 6; /* 1 Jan 1 was a Saturday */ 
	var K_Sept1752 = new Array(30, 31, 1, 2, 14, 15, 16, 
							   17, 18, 19, 20, 21, 22, 23, 
							   24, 25, 26, 27, 28, 29, 30, 
							   -1, -1, -1, -1, -1, -1, -1, 
							   -1, -1, -1, -1, -1, -1, -1, 
							   -1, -1, -1, -1, -1, -1, -1
							  );
	var today = new Date();
	today = new Date(today.getFullYear(),today.getMonth(),today.getDate());
	var calendar = new Array();
	var dates = new Array();
	var selection = new Date();
	_self.selection = selection;
	var scope = "month";
	_self.scope = scope;

	this.render = function (month,day,year) {
		$(target).empty();

		if (!month) month = today.getMonth()+1;	
		if (!year) year = today.getFullYear();
		
		dates = this.getDayMap(month, year,0,true);
		var dayLabels = new Array();
		var weekdays = new Array();
		var weeks = new Array();

		var last_month = (month - 1 < 1)? 12: month - 1;
		var next_month = (month + 1 > 12)? 1: month + 1;
		var lm_year  = (last_month == 12)? year - 1: year;
		var nm_year  = (next_month == 1)? year + 1: year;
		
		var i = 0,w = 0;
		
		var backarrow = $('<span class="back">&laquo;</span>').appendTo(target);
		var previousMonth = new Date(year,month-2,today.getDate());
		if (previousMonth >= today.getTime()) {
			backarrow.click(function () {
				_self.scope = "month";
				_self.selection = new Date(year,month-2);
				_self.render(_self.selection.getMonth()+1,1,_self.selection.getFullYear());
				$(_self).change();
			});
		}
		var nextarrow = $('<span class="next">&raquo;</span>').appendTo(target);
		nextarrow.click(function () {
			_self.scope = "month";
			_self.selection = new Date(year,month);
			_self.render(_self.selection.getMonth()+1,1,_self.selection.getFullYear());
			$(_self).change();
		});
		
		var title = $('<h3></h3>').appendTo(target);
		$('<span class="month">'+MONTH_NAMES[month]+'</span>').appendTo(title);
		$('<span class="year">'+year.toString()+'</span>').appendTo(title);
		
		weeks[w] = $('<div class="week"></week>').appendTo(target);
		for (i = 0; i < WEEK_DAYS.length; i++) {
		 	var dayname = WEEK_DAYS[i];
		 	dayLabels[i] = $('<div class="label">'+dayname.substr(0,3)+'</span>').appendTo(weeks[w]);
		}
		
		for (i = 0; i < dates.length; i++) {
			var thisMonth = dates[i].getMonth()+1;
			var thisYear = dates[i].getFullYear();
			var thisDate = new Date(thisYear,thisMonth-1,dates[i].getDate());
			
			// Start a new week
			if (i % 7 == 0) weeks[++w] = $('<div class="week"></div>').appendTo(target);
			if (dates[i] != -1) {
				calendar[i] = $('<div title="'+i+'">'+thisDate.getDate()+'</div>').appendTo(weeks[w]);
				calendar[i].date = thisDate;

				if (thisMonth != month) calendar[i].addClass('disabled');
				if (thisDate.getTime() < today.getTime()) calendar[i].addClass('disabled');
				if (thisDate.getTime() == today.getTime()) calendar[i].addClass('today');

				calendar[i].hover(function () {
					$(this).addClass('hover');
				},function () {
					$(this).removeClass('hover');
				});
				
				calendar[i].mousedown(function () { $(this).addClass('active');	});
				calendar[i].mouseup(function () { $(this).removeClass('active'); });
				
				
				if (thisDate.getTime() >= today.getTime()) {
					calendar[i].click(function () {
						_self.resetCalendar();
						if (!$(this).hasClass("disabled")) $(this).addClass("selected");
						
						_self.selection = dates[$(this).attr('title')];
						_self.scope = "day";

						if (_self.selection.getMonth()+1 != month) {
	 						_self.render(_self.selection.getMonth()+1,1,_self.selection.getFullYear());
							_self.autoselect();
						} else {
							$(target).hide();
						}
						$(_self).change();
					});
				}
			}
		}
		
		
	}
	
	this.autoselect = function () {
		for (var i = 0; i < dates.length; i++) {
			if (dates[i].getTime() == selection.getTime()) {
				calendar[i].addClass('selected');
			}
		}
	}
	
	this.resetCalendar = function () {
		for(var i = 0; i < calendar.length; i++) {
			$(calendar[i]).removeClass('selected');
		}
	}
	
	/* getDayMap() -- 
	 * Fill in an array of 42 integers with a calendar.  Assume for a moment 
	 * that you took the (maximum) 6 rows in a calendar and stretched them 
	 * out end to end.  You would have 42 numbers or spaces.  This routine 
	 * builds that array for any month from Jan. 1 through Dec. 9999. */ 

	this.getDayMap = function (month, year, start_week, all) {
		var day = 1;
		var c = 0;
		var days = new Array();
		var last_month = (month - 1 == 0)? 12: month - 1;
		var last_month_year = (last_month == 12)? year - 1: year;
	
		if(month == 9 && year == 1752) return K_Sept1752;
		
		for(var i = 0; i < K_MaxDays; i++) {
			days.push(-1);
		}
	
		var pm = DAYS_IN_MONTH[(this.is_leapyear(last_month_year))?1:0][last_month];	// Get the last day of the previous month
		var dm = DAYS_IN_MONTH[(this.is_leapyear(year))?1:0][month];			// Get the last day of the selected month
		var dw = this.dayInWeek(1, month, year, start_week); // Find where the 1st day of the month starts in the week
		var pw = this.dayInWeek(1, month, year, start_week); // Find the 1st day of the last month in the week
			
		if (all) while(pw--) days[pw] = new Date(last_month_year,last_month-1,pm--);
		while(dm--) days[dw++] = new Date(year,month-1,day++);
		var ceiling = days.length - dw;
		if (all) while(c < ceiling)
			days[dw++] = new Date(year,month,++c);
		
		return days;
	} 

	/* dayInYear() -- 
	 * return the day of the year */ 
	this.dayInYear = function (day, month, year) {
	    var leap = (this.is_leapyear( year ))?1:0; 
	    for(var i = 1; i < month; i++) {
			day += DAYS_IN_MONTH[leap][i];
		}
	    return day;
	} 

	/* dayInWeek() -- 
	 * return the x based day number for any date from 1 Jan. 1 to 
	 * 31 Dec. 9999.  Assumes the Gregorian reformation eliminates 
	 * 3 Sep. 1752 through 13 Sep. 1752.  Returns Thursday for all 
	 * missing days. */ 
	this.dayInWeek = function (day, month, year, start_week) { 
		// Find 0 based day number for any date from Jan 1, 1 - Dec 31, 9999
		var daysSinceBC = (year - 1) * 365 + this.leapYearsSinceBC(year - 1) + this.dayInYear(day, month, year);
	    var val = K_Thursday;
	    // Set val 
		if(daysSinceBC < K_FirstMissingDays) val = ((daysSinceBC - 1 + K_Saturday ) % 7); 
		if(daysSinceBC >= (K_FirstMissingDays + K_MissingDays)) val = (((daysSinceBC - 1 + K_Saturday) - K_MissingDays) % 7);

	    // Shift depending on the start day of the week
	    if (val <= start_week) return val += (7 - start_week);
	    else return val -= start_week;

	} 
	
	this.is_leapyear = function (yr) {
		if (yr <= 1752) return !((yr) % 4);
		else return ((!((yr) % 4) && ((yr) % 100) > 0) || (!((yr) % 400)));
	}

	this.centuriesSince1700 = function (yr) {
		if (yr > 1700) return (Math.floor(yr / 100) - 17);
		else return 0;
	}

	this.quadCenturiesSince1700 = function (yr) {
		if (yr > 1600) return Math.floor((yr - 1600) / 400);
		else return 0;
	}

	this.leapYearsSinceBC = function (yr) {
		return (Math.floor(yr / 4) - this.centuriesSince1700(yr) + this.quadCenturiesSince1700(yr));
	}

	
}

addEvent(window,'load',function () {
	buttonHandlers();
	cartHandlers();
});

// Fix for ThickBox
var tb_pathToImage = "/wp-content/plugins/shopp/core/ui/icons/loading.gif";
var tb_closeImage = "/wp-includes/js/thickbox/tb-close.png";
