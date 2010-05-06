/*!
 * calendar.js - Modal calendar date selector
 * Copyright © 2008-2010 by Ingenesis Limited
 * Licensed under the GPLv3 {@see license.txt}
 */
function PopupCalendar (target,month,year) {
	
	var _ = this, $ = jqnc(),
	DAYS_IN_MONTH = new Array(new Array(0, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31),
							  new Array(0, 31, 29, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31)),
	MONTH_NAMES = new Array('',ShoppSettings.month_jan, ShoppSettings.month_feb, ShoppSettings.month_mar, 
								ShoppSettings.month_apr, ShoppSettings.month_may, ShoppSettings.month_jun, 
								ShoppSettings.month_jul, ShoppSettings.month_aug, ShoppSettings.month_sep, 
								ShoppSettings.month_oct, ShoppSettings.month_nov, ShoppSettings.month_dec),
	WEEK_DAYS = new Array(ShoppSettings.weekday_sun, ShoppSettings.weekday_mon,ShoppSettings.weekday_tue, 
							ShoppSettings.weekday_wed, ShoppSettings.weekday_thu, ShoppSettings.weekday_fri, 
							ShoppSettings.weekday_sat),
	/* Date Constants */
	K_FirstMissingDays = 639787, /* 3 Sep 1752 */
	K_MissingDays = 11, /* 11 day correction */
	K_MaxDays = 42, /* max slots in a calendar map array */ 
	K_Thursday = 4, /* for reformation */ 
	K_Saturday = 6, /* 1 Jan 1 was a Saturday */ 
	K_Sept1752 = new Array(30, 31, 1, 2, 14, 15, 16, 
						   17, 18, 19, 20, 21, 22, 23, 
						   24, 25, 26, 27, 28, 29, 30, 
						   -1, -1, -1, -1, -1, -1, -1, 
						   -1, -1, -1, -1, -1, -1, -1, 
						   -1, -1, -1, -1, -1, -1, -1
						  ),
	today = new Date(),
	calendar = new Array(),
	dates = new Array(),

	// Literals
	disabled = 'disabled',
	scopeMonth = 'month',
	active = 'active',
	selected = 'selected',
	hover = 'hover';
	
	// Set today starting at 12am
	today = new Date(today.getFullYear(),today.getMonth(),today.getDate());
	_.selection = new Date();
	_.scope = scopeMonth;
	_.scheduling = true;

	_.render = function (month,day,year) {
		$(target).empty();
		
		var backarrow,previousMonth,nextarrow,title,dayname,thisMonth,thisYear,thisDate,
			i = 0, 
			w = 0,
			dayLabels = new Array(),
			weeks = new Array();

		if (!month) month = today.getMonth()+1;	
		if (!year) year = today.getFullYear();
		
		dates = this.getDayMap(month, year,0,true);
		
		backarrow = $('<span class="back">&laquo;</span>').appendTo(target);
		previousMonth = new Date(year,month-2,today.getDate());
		if (!_.scheduling || (_.scheduling && previousMonth >= today.getTime())) {
			backarrow.click(function () {
				_.scope = scopeMonth;
				_.selection = new Date(year,month-2);
				_.render(_.selection.getMonth()+1,1,_.selection.getFullYear());
				$(_).change();
			});
		}
		nextarrow = $('<span class="next">&raquo;</span>').click(function () {
			_.scope = scopeMonth;
			_.selection = new Date(year,month);
			_.render(_.selection.getMonth()+1,1,_.selection.getFullYear());
			$(_).change();
		}).appendTo(target);
		
		title = $('<h3></h3>').appendTo(target);
		$('<span class="month">'+MONTH_NAMES[month]+'</span>').appendTo(title);
		$('<span class="year">'+year.toString()+'</span>').appendTo(title);
		
		weeks[w] = $('<div class="week"></week>').appendTo(target);
		for (i = 0; i < WEEK_DAYS.length; i++) {
		 	dayname = WEEK_DAYS[i];
		 	dayLabels[i] = $('<div class="label">'+dayname.substr(0,3)+'</span>').appendTo(weeks[w]);
		}
		
		for (i = 0; i < dates.length; i++) {
			thisMonth = dates[i].getMonth()+1,
			thisYear = dates[i].getFullYear(),
			thisDate = new Date(thisYear,thisMonth-1,dates[i].getDate());
			
			// Start a new week
			if (i % 7 == 0) weeks[++w] = $('<div class="week"></div>').appendTo(target);
			if (dates[i] != -1) {
				calendar[i] = $('<div title="'+i+'">'+thisDate.getDate()+'</div>').appendTo(weeks[w]);
				calendar[i].date = thisDate;

				if (thisMonth != month) calendar[i].addClass(disabled);
				if (_.scheduling && thisDate.getTime() < today.getTime()) calendar[i].addClass(disabled);
				if (thisDate.getTime() == today.getTime()) calendar[i].addClass('today');

				calendar[i].hover(function () {
					$(this).addClass(hover);
				},function () {
					$(this).removeClass(hover);
				});
				
				calendar[i].mousedown(function () { $(this).addClass(active); });
				calendar[i].mouseup(function () { $(this).removeClass(active); });
				
				
				if (!_.scheduling || (_.scheduling && thisDate.getTime() >= today.getTime())) {
					calendar[i].click(function () {
						_.resetCalendar();
						if (!$(this).hasClass(disabled)) $(this).addClass(selected);
						
						_.selection = dates[$(this).attr('title')];
						_.scope = "day";

						if (_.selection.getMonth()+1 != month) {
	 						_.render(_.selection.getMonth()+1,1,_.selection.getFullYear());
							_.autoselect();
						} else {
							$(target).hide();
						}
						$(_).change();
					});
				}
			}
		}
		
		
	}
	
	_.autoselect = function () {
		for (var i = 0; i < dates.length; i++) 
			if (dates[i].getTime() == this.selection.getTime())
				calendar[i].addClass(selected);
	}
	
	_.resetCalendar = function () {
		for(var i = 0; i < calendar.length; i++)
			$(calendar[i]).removeClass(selected);
	}
	
	/**
	 * Fill an array of 42 integers with a calendar.  Assume for a moment 
	 * that you took the (maximum) 6 rows in a calendar and stretched them 
	 * out end to end. You would have 42 days or spaces. This routine 
	 * builds that calendar list for any month from Jan. 1 through Dec. 9999. 
	 * @param int month Month of the calendar
	 * @param int year Year of the calendar (4-digits)
	 * @param int sw Start of the week offset (0 for Sunday)
	 * @param boolean all Include previous and next month days
	 **/ 
	_.getDayMap = function (month, year, sw, all) {
		var i,pm,dm,dw,pw,ceiling,
			day = 1,
			c = 0,
			days = new Array(),
			last_month = (month - 1 == 0)? 12: month - 1,
			last_month_year = (last_month == 12)? year - 1: year;
	
		if(month == 9 && year == 1752) return K_Sept1752;
		
		for(i = 0; i < K_MaxDays; i++)
			days.push(-1);
	
		pm = DAYS_IN_MONTH[(_.is_leapyear(last_month_year))?1:0][last_month];	// Get the last day of the previous month
		dm = DAYS_IN_MONTH[(_.is_leapyear(year))?1:0][month];			// Get the last day of the selected month
		dw = _.dayInWeek(1, month, year, sw); // Find where the 1st day of the month starts in the week
		pw = _.dayInWeek(1, month, year, sw); // Find the 1st day of the last month in the week
			
		if (all) while(pw--) days[pw] = new Date(last_month_year,last_month-1,pm--);
		while(dm--) days[dw++] = new Date(year,month-1,day++);
		ceiling = days.length - dw;
		if (all) while(c < ceiling)
			days[dw++] = new Date(year,month,++c);
		
		return days;
	} 

	/**
	 * Return the day of the year 
	 **/ 
	_.dayInYear = function (day, month, year) {
	    var i,leap = _.is_leapyear(year)?1:0;
	    for(i = 1; i < month; i++)
			day += DAYS_IN_MONTH[leap][i];
	    return day;
	}

	/**
	 * Return the x based day number for any date from 1 Jan. 1 to 
	 * 31 Dec. 9999.  Assumes the Gregorian reformation eliminates 
	 * 3 Sep. 1752 through 13 Sep. 1752.  Returns Thursday for all 
	 * missing days. 
	 * @param int day Day of the date
	 * @param int month Month of the date
	 * @param int year Year of the date
	 * @param int sw Start of the week offset (0 for Sunday)
	 **/ 
	_.dayInWeek = function (day, month, year, sw) { 
		// Find 0 based day number for any date from Jan 1, 1 - Dec 31, 9999
		var daysSinceBC = (year - 1) * 365 + _.leapYearsSinceBC(year - 1) + _.dayInYear(day, month, year),
 			val = K_Thursday;
	    // Set val 
		if(daysSinceBC < K_FirstMissingDays) val = ((daysSinceBC - 1 + K_Saturday ) % 7); 
		if(daysSinceBC >= (K_FirstMissingDays + K_MissingDays)) val = (((daysSinceBC - 1 + K_Saturday) - K_MissingDays) % 7);

	    // Shift depending on the start day of the week
	    if (val <= sw) return val += (7 - sw);
	    else return val -= sw;

	} 
	
	_.is_leapyear = function (yr) {
		if (yr <= 1752) return !((yr) % 4);
		else return ((!((yr) % 4) && ((yr) % 100) > 0) || (!((yr) % 400)));
	}

	_.centuriesSince1700 = function (yr) {
		if (yr > 1700) return (Math.floor(yr / 100) - 17);
		else return 0;
	}

	_.quadCenturiesSince1700 = function (yr) {
		if (yr > 1600) return Math.floor((yr - 1600) / 400);
		else return 0;
	}

	_.leapYearsSinceBC = function (yr) {
		return (Math.floor(yr / 4) - _.centuriesSince1700(yr) + _.quadCenturiesSince1700(yr));
	}
	
}