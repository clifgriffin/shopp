/*!
 * shopp.js - Shopp behavioral utility library
 * Copyright © 2008-2010 by Ingenesis Limited
 * Licensed under the GPLv3 {@see license.txt}
 */

function jqnc () {
	return jQuery.noConflict();
}

/**
 * Returns a copy/clone of an object
 **/
function copyOf (src) {
	var target = new Object(),v;
	for (v in src) target[v] = src[v];
	return target;
}

/**
 * Provides indexOf method for browsers that
 * that don't implement JavaScript 1.6 (IE for example)
 **/
if (!Array.indexOf) {
	Array.prototype.indexOf = function(obj) {
		for (var i = 0; i < this.length; i++)
			if (this[i] == obj) return i;
		return -1;
	}
}

function getCurrencyFormat () {
	if (!ShoppSettings) return false;
	return {
		"cpos":ShoppSettings.cp,
		"currency":ShoppSettings.c,
		"precision":parseInt(ShoppSettings.p),
		"decimals":ShoppSettings.d,
		"thousands":ShoppSettings.t
	}
}

function defaultCurrencyFormat () {
	return {
		"cpos":true,
		"currency":"$",
		"precision":2,
		"decimals":".",
		"thousands":","
	}
}

/**
 * Add notation to an integer to display it as money.
 * @param int n Number to convert
 * @param array f Format settings
 **/
function asMoney (n,f) {
	var currencyFormat = getCurrencyFormat();
	if (currencyFormat && !f) f = copyOf(currencyFormat);
	if (!f || !f.currency) f = defaultCurrencyFormat();

	n = formatNumber(n,f);
	if (f.cpos) return f.currency+n;
	return n+f.currency;
}

/**
 * Add notation to an integer to display it as a percentage.
 * @param int n Number to convert
 * @param array f Format settings
 **/
function asPercent (n,f) {
	var currencyFormat = getCurrencyFormat();
	if (currencyFormat && !f) f = copyOf(currencyFormat);
	if (!f) f = defaultCurrencyFormat();

	f.precision = 1;
	return formatNumber(n,f)+"%";
}

/**
 * Formats a number to denote thousands with decimal precision.
 * @param int n Number to convert
 * @param array f Format settings
 **/
function formatNumber (n,f) {
	if (!f) f = defaultCurrencyFormat();

	n = asNumber(n);
	var digits,i,d = n.toFixed(f.precision).toString().split(".");

	n = "";
	if (f.indian) {
		digits = d[0].slice(0,-3);
		n = d[0].slice(-3,d[0].length) + ((n.length > 0)?f.thousands+n:n);
		for (i = 0; i < (digits.length / 2); i++) 
			n = digits.slice(-2*(i+1),digits.length+(-2 * i)) + ((n.length > 0)?f.thousands+n:n);
	} else {
		for (i = 0; i < (d[0].length / 3); i++) 
			n = d[0].slice(-3*(i+1),d[0].length+(-3 * i)) + ((n.length > 0)?f.thousands + n:n);
	}

	if (f.precision > 0) n += f.decimals + d[1];
	return n;

}

/**
 * Convert a field with numeric and non-numeric characters
 * to a true integer for calculations.
 * @param int n Number to convert
 * @param array f Format settings
 **/
function asNumber (n,f) {
	if (!n) return 0;
	var currencyFormat = getCurrencyFormat();
	if (currencyFormat && !f) f = copyOf(currencyFormat);
	if (!f || !f.currency) f = defaultCurrencyFormat();
	
	if (n instanceof Number) return new Number(n.toFixed(f.precision));

	n = n.toString().replace(new RegExp(/[^\d\.\,]/g),""); // Reove any non-numeric string data
	n = n.toString().replace(new RegExp('\\'+f.thousands,'g'),""); // Remove thousands

	if (f.precision > 0)
		n = n.toString().replace(new RegExp('\\'+f.decimals,'g'),"."); // Convert decimal delimter
		
	if (isNaN(new Number(n)))
		n = n.replace(new RegExp(/\./g),"").replace(new RegExp(/\,/),"\.");

	return new Number(n);
}

/**
 * Utility class to build a list of functions (callbacks) 
 * to be executed as needed
 **/
function CallbackRegistry () {
	this.callbacks = new Array();

	this.register = function (name,callback) {
		this.callbacks[name] = callback;
	}

	this.call = function(name,arg1,arg2,arg3) {
		this.callbacks[name](arg1,arg2,arg3);
	}
	
	this.get = function(name) {
		return this.callbacks[name];
	}
}

/**
 * Find fields that need display formatting and 
 * run the approriate formatting.
 */
function formatFields () {
	var $ = jqnc(),
		f = $('input');
		
	f.each(function (i,e) {
		e = $(e);
		if (e.hasClass('currency')) {
			e.change(function() {
				$(e).val(asMoney($(e).val()));
			}).change();
		}
	});
}

if (!Number.prototype.roundFixed) {
	Number.prototype.roundFixed = function(precision) {
		var power = Math.pow(10, precision || 0);
		return String(Math.round(this * power)/power);
	}
}

//
// Catalog Behaviors
//
function ProductOptionsMenus (target,hideDisabled,pricing,taxrate) {
	var $ = jqnc(),
		i = 0,
		previous = false,
		current = false,
		menucache = new Array(),
		menus = $(target),
		disabled = 'disabled';
		
	if (!taxrate) taxrate = 0;

	menus.each(function (id,menu) {
		current = menu;
		menucache[id] = $(menu).children();			
		if ($.browser.msie) disabledHandler(menu);
		if (id > 0)	previous = menus[id-1];
		if (menus.length == 1) {
			optionPriceTags();
		} else if (previous) {
			$(previous).change(function () {
				if (menus.index(current) == menus.length-1) optionPriceTags();
				if (this.selectedIndex == 0 && 
					this.options[0].value == "") $(menu).attr(disabled,true);
				else $(menu).removeAttr(disabled);
			}).change();
		}
		i++;
	});
		
	// Last menu needs pricing
	function optionPriceTags () {
		// Grab selections
		var selected = new Array(),
			currentSelection = $(current).val();
		menus.not(current).each(function () {
			if ($(this).val() != "") selected.push($(this).val());
		});
		$(current).empty();
		menucache[menus.index(current)].each(function (id,option) {
			$(option).appendTo($(current));
		});
		$(current).val(currentSelection);

		$(current).children('option').each(function () {
			var p,tax,pricetag,optiontext,previoustag,
				option = $(this),
				keys = selected.slice(),
				price = pricing[xorkey(keys)];
			if (option.val() != "") {
				keys.push(option.val());
				if (!price) price = pricing[xorkey_deprecated(keys)];
				if (price) {
					p = new Number(price.onsale?price.promoprice:price.price);
					tax = new Number(p*taxrate);
					pricetag = asMoney(new Number(p+tax));
					optiontext = option.attr('text');
					previoustag = optiontext.lastIndexOf("(");
					if (previoustag != -1) optiontext = optiontext.substr(0,previoustag);
					option.attr('text',optiontext+"  ("+pricetag+")");
					if ((price.inventory == "on" && price.stock == 0) || price.type == "N/A") {
						if (option.attr('selected')) 
							option.parent().attr('selectedIndex',0);
						if (hideDisabled) option.remove();
						else optionDisable(this);
					
					} else option.removeAttr(disabled).show();
					if (price.type == "N/A" && hideDisabled) option.remove();
				}
			}
		});
	}

	// Magic key generator
	function xorkey (ids) {
		for (var key=0,i=0; i < ids.length; i++) 
			key = key ^ (ids[i]*7001);
		return key;
	}

	function xorkey_deprecated (ids) {
		for (var key=0,i=0; i < ids.length; i++) 
			key = key ^ (ids[i]*101);
		return key;
	}
	
	function optionDisable (option) {
		$(option).attr(disabled,true);
		if (!$.browser.msie) return;
		$(option).css('color','#ccc');
	}
	
	function disabledHandler (menu) {
		$(menu).change(function () {
			if (!this.options[this.selectedIndex].disabled) {
				this.lastSelected = this.selectedIndex;
				return true;
			}
			if (this.lastSelected) this.selectedIndex = this.lastSelected;
			else {
				var firstEnabled = $(this).children('option:not(:disabled)').get(0);
				this.selectedIndex = firstEnabled?firstEnabled.index:0;
			}				
		});
	}		
	
}


//
// Cart Behaviors
//

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


//
// Generic behaviors
//

/**
 * Usability behavior to add automatic select-all to a field 
 * when activating the field by mouse click
 **/
function quickSelects (target) {
	jQuery('input.selectall').mouseup(function () { this.select(); });
}

/**
 * Hooks callbacks to button events
 **/
function buttonHandlers () {
	(function($) {
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
	})(jQuery)
}

function validateForms () {
	jQuery('form.validate').submit(function () {
		if (validate(this)) return true;
		else return false;
	});
}

/**
 * catalogViewHandler ()
 * Handles catalog view changes
 **/
function catalogViewHandler () {
	var $=jqnc(),
		display = $('#shopp'),
		expires = new Date();
	expires.setTime(expires.getTime()+(30*86400000));

	display.find('ul.views li button.list').click(function () {
		display.removeClass('grid').addClass('list');
		document.cookie = 'shopp_catalog_view=list; expires='+expires+'; path=/';
	});
	display.find('ul.views li button.grid').click(function () {
		display.removeClass('list').addClass('grid');
		document.cookie = 'shopp_catalog_view=grid; expires='+expires+'; path=/';
	});
}

/**
 * Adds behaviors to shopping cart controls
 **/
function cartHandlers () {
	jQuery('#cart #shipping-country').change(function () {
		this.form.submit();
	});
}

/**
 * Create gallery viewing for a set of images
 **/
function ShoppGallery (id,evt) {
	var $ = jqnc(),
		gallery = $(id);
		thumbnails = gallery.find('ul.thumbnails li');
		previews = gallery.find('ul.previews');
	if (!evt) evt = 'click';

	thumbnails.bind(evt,function () {
		var previous,target = $('#'+$(this).attr('class').split(' ')[0]);
		if (!target.hasClass('active')) {
			previous = gallery.find('ul.previews li.active');
			target.addClass('active').hide();
			if (previous.length) {
				previous.fadeOut(800,function() {
					previous.removeClass('active');
				});
			}
			target.appendTo(previews).fadeIn(500);
		}
	});
}

function ShoppSlideshow (element,duration,delay,fx,order) {
	var $ = jqnc(),_ = this,effects;
	_.element = $(element);
	effects = {
		'fade':[{'display':'none'},{'opacity':'show'}],
		'slide-down':[{'display':'block','top':_.element.height()*-1},{'top':0}],
		'slide-up':[{'display':'block','top':_.element.height()},{'top':0}],
		'slide-left':[{'display':'block','left':_.element.width()*-1},{'left':0}],
		'slide-right':[{'display':'block','left':_.element.width()},{'left':0}],
		'wipe':[{'display':'block','height':0},{'height':_.element.height()}]
	},
	ordering = ['normal','reverse','shuffle'];
	
	_.duration = (!duration)?800:duration;
	_.delay = (!delay)?7000:delay;
	fx = (!fx)?'fade':fx;
	_.effect = (!effects[fx])?effects['fade']:effects[fx];
	order = (!order)?'normal':order;
	_.order = ($.inArray(order,ordering) != -1)?order:'normal';
	
	_.slides = $(_.element).find('li:not(li.clear)').hide().css('visibility','visible');;
	_.total = _.slides.length;
	_.slide = 0;
	_.shuffling = new Array();
	_.startTransition = function () {
		var index,selected,prev = $(self.slides).find('.active').removeClass('active');
		$(_.slides[_.slide]).css(_.effect[0]).appendTo(_.element).animate(
				_.effect[1],
				_.duration,
				function () {
					prev.css(_.effect[0]);
				}
			).addClass('active');

		switch (_.order) {
			case "shuffle": 
				if (_.shuffling.length == 0) {
					_.shuffleList();
					index = $.inArray(_.slide,_.shuffling);
					if (index != -1) _.shuffling.splice(index,1);						
				}
				selected = Math.floor(Math.random()*_.shuffling.length);
				_.slide = _.shuffling[selected];
				_.shuffling.splice(selected,1);
				break;
			case "reverse": _.slide = (_.slide-1 < 0)?_.slides.length-1:_.slide-1; break;
			default: _.slide = (_.slide+1 == _.total)?0:_.slide+1;
		}
		
		if (_.slides.length == 1) return;
		setTimeout(_.startTransition,_.delay);
	}
	
	_.transitionTo = function (slide) {
		_.slide = slide;
		_.startTransition();
	}
	
	_.shuffleList = function () {
		for (var i = 0; i < _.total; i++) _.shuffling.push(i);
	}
	
	_.startTransition();
}

function slideshows () {
	var $ = jqnc(),classes,options,map;
	$('ul.slideshow').each(function () {
		classes = $(this).attr('class');
		options = {};
		map = {
			'fx':new RegExp(/([\w_-]+?)\-fx/),
			'order':new RegExp(/([\w_-]+?)\-order/),
			'duration':new RegExp(/duration\-(\d+)/),
			'delay':new RegExp(/delay\-(\d+)/)
		};
		$.each(map,function (name,pattern) {
			if (option = classes.match(pattern)) options[name] = option[1];
		});
		new ShoppSlideshow(this,options['duration'],options['delay'],options['fx'],options['order']);
	});
}

function ShoppCarousel (element,duration) {
	var $ = jqnc(),visible,spacing,
		_ = this,
		carousel = $(element),
		list = carousel.find('ul'),
		items = list.find('> li');

	_.duration = (!duration)?800:duration;
	_.cframe = carousel.find('div.frame');

	visible = Math.floor(_.cframe.innerWidth() / items.outerWidth());
	spacing = Math.round(((_.cframe.innerWidth() % items.outerWidth())/items.length)/2);

	items.css('margin','0 '+spacing+'px');
		
	_.pageWidth = (items.outerWidth()+(spacing*2)) * visible;
	_.page = 1;
	_.pages = Math.ceil(items.length / visible);
	
	// Fill in empty slots
	if ((items.length % visible) != 0) {
		list.append( new Array(visible - (items.length % visible)+1).join('<li class="empty" style="width: '+items.outerWidth()+'px; height: 1px; margin: 0 '+spacing+'px"/>') );
		items = list.find('> li');
	}
	
	items.filter(':first').before(items.slice(-visible).clone().addClass('cloned'));
	items.filter(':last').after(items.slice(0,visible).clone().addClass('cloned'));
	items = list.find('> li');
	
	_.cframe.scrollLeft(_.pageWidth);

	_.scrollLeft = carousel.find('button.left');
	_.scrollRight = carousel.find('button.right');
	
	_.scrolltoPage = function (page) {
		var dir = page < _.page?-1:1,
			delta = Math.abs(_.page-page),
			scrollby = _.pageWidth*dir*delta;
		
		_.cframe.filter(':not(:animated)').animate({
			'scrollLeft':'+='+scrollby
		},_.duration,function() {
			if (page == 0) {
				_.cframe.scrollLeft(_.pageWidth*_.pages);
				page = _.pages;
			} else if (page > _.pages) {
				_.cframe.scrollLeft(_.pageWidth);
				page = 1;
			}
			_.page = page;
		});
	}
	
	_.scrollLeft.click(function () {
		return _.scrolltoPage(_.page-1);
	});

	_.scrollRight.click(function () {
		return _.scrolltoPage(_.page+1);
	});
	
}
function carousels () {
	var $ = jqnc(),classes,options,map;
	$('div.carousel').each(function () {
		classes = $(this).attr('class');
		options = {};
		map = { 'duration':new RegExp(/duration\-(\d+)/) };
		$.each(map,function (name,pattern) {
			if (option = classes.match(pattern)) options[name] = option[1];
		});
		new ShoppCarousel(this,options['duration']);
	});
}

function htmlentities (string) {
	if (!string) return "";
	string = string.replace(new RegExp(/&#(\d+);/g), function() {
		return String.fromCharCode(RegExp.$1);
	});
	return string;
}

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

function validate (form) {
	if (!form) return false;
	var $ = jqnc(),
		passed = true,
		passwords = new Array(),
		error = new Array(),
		inputs = $(form).find('input,select'),
		required = 'required',
		title = 'title';
		
	$.each(inputs,function (id,field) {
		input = $(field)
		if (input.attr('disabled') == true) return;
		
		if (input.hasClass(required) && input.val() == "")
			error = new Array(ShoppSettings.REQUIRED_FIELD.replace(/%s/,input.attr(title)),field);
		
		if (input.hasClass(required) && input.attr('type') == "checkbox" && !input.attr('checked'))
			error = new Array(ShoppSettings.REQUIRED_CHECKBOX.replace(/%s/,input.attr(title)),field);
		
		if (input.hasClass('email') && !input.val().match(new RegExp('^[a-zA-Z0-9._-]+@([a-zA-Z0-9.-]+\.)+[a-zA-Z0-9.-]{2,4}$')))
			error = new Array(ShoppSettings.INVALID_EMAIL,field);
			
		if (chars = input.attr('class').match(new RegExp('min(\\d+)'))) {
			if (input.val().length < chars[1])
				error = new Array(ShoppSettings.MIN_LENGTH.replace(/%s/,input.attr(title)).replace(/%d/,chars[1]),field);
		}
		
		if (input.hasClass('passwords')) {
			passwords.push(field);
			if (passwords.length == 2 && passwords[0].value != passwords[1].value)
				error = new Array(ShoppSettings.PASSWORD_MISMATCH,passwords[1]);
		}
			
	});

	if (error.length > 0) {
		error[1].focus();
		alert(error[0]);
		passed = false;
	}
	return passed;
}

jQuery(document).ready(function() {
	var $=jqnc();
	validateForms();
	formatFields();
	buttonHandlers();
	cartHandlers();
	catalogViewHandler();
	quickSelects();
	slideshows();
	carousels();
	if ($.fn.colorbox) {
		$('a.shopp-zoom').colorbox();
		$('a.shopp-zoom.gallery').attr('rel','gallery').colorbox({slideshow:true});
	}
});

// Initialize placehoder variables
var options_required,
	options_default,
	productOptions = new Array();