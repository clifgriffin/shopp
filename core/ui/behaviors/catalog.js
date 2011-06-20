/*!
 * catalog.js - Shopp catalog behaviors library
 * Copyright © 2008-2010 by Ingenesis Limited
 * Licensed under the GPLv3 {@see license.txt}
 */

/**
 * Product variation option menu behaviors
 **/
function ProductOptionsMenus (target,settings) {
	var $ = jqnc(),
		i = 0,
		previous = false,
		current = false,
		menucache = new Array(),
		menus = $(target),
		disabled = 'disabled',
		defaults = {
			disabled:true,
			pricetags:true,
			taxrate:0,
			prices:{}
		},
		settings = $.extend(defaults,settings);

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

			var p,tax,optiontext,previoustag,pricetag="",
				option = $(this),
				keys = selected.slice(),
				price;
			if (option.val() != "") {
				keys.push(option.val());
				price = settings.prices[xorkey(keys)] || settings.prices[xorkey(keys,'deprecated')];
				if (price) {
					if (price.p && settings.pricetags) {
						p = new Number(price.p);
						tax = price.tax?new Number(p*settings.taxrate):0;
						pricetag = "  ("+asMoney(new Number(p+tax))+")";
					}
					optiontext = option.text();
					previoustag = optiontext.lastIndexOf("(");
					if (previoustag != -1) optiontext = optiontext.substr(0,previoustag);
					option.text(optiontext+pricetag);
					if ($.browser.msie) option.css('color','#373737');
					if ((price.i && !price.s) || price.t == "N/A") {
						if (option.attr('selected'))
							option.parent().attr('selectedIndex',0);
						if (!settings.disabled) option.remove();
						else optionDisable(option);
					} else option.removeAttr(disabled).show();
					if (price.t == "N/A" && !settings.disabled) option.remove();
				} else {
					if (!settings.disabled) option.remove();
					else optionDisable(option);
				}
			}
		});
	}

	// Magic key generator
	function xorkey (ids,deprecated) {
		for (var key=0,i=0,mod=deprecated?101:7001; i < ids.length; i++)
			key = key ^ (ids[i]*mod);
		return key;
	}

	function optionDisable (option) {
		option.attr(disabled,true);
		if (!$.browser.msie) return;
		option.css('color','#ccc');
	}

	function disabledHandler (menu) {
		$(menu).change(function () {
			var _ = this,firstEnabled;
			if (!_.options[_.selectedIndex].disabled) {
				_.lastSelected = _.selectedIndex;
				return true;
			}
			if (_.lastSelected) _.selectedIndex = _.lastSelected;
			else {
				firstEnabled = $(_).children('option:not(:disabled)').get(0);
				_.selectedIndex = firstEnabled?firstEnabled.index:0;
			}
		});
	}

}


/**
 * Toggles catalog grid and list view changes
 **/
function catalogViewHandler () {
	var $=jqnc(),
		display = $('#shopp'),
		expires = new Date(),
		toggles = {'list':'grid','grid':'list'};
	expires.setTime(expires.getTime()+(30*86400000));

	$.each(toggles,function (view,lastview) {
		display.find('ul.views li button.'+view).click(function () {
			display.removeClass(lastview).addClass(view);
			document.cookie = 'shopp_catalog_view='+view+'; expires='+expires+'; path=/';
		}).hover(function () { $(this).toggleClass('hover'); });
	});
}

/**
 * Create a gallery viewing for a set of images
 **/
function ShoppGallery (id,evt,tw) {
	var $ = jqnc(),
		gallery = $(id),
		previews = gallery.find('ul.previews'),
		thumbnails = gallery.find('ul.thumbnails li');
	if (!evt) evt = 'click';
	if (tw) gallery.find('ul.thumbnails').css('width',tw+'px');

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

/**
 * Generate a slideshow from a list of images
 **/
function ShoppSlideshow (element,duration,delay,fx,order) {
	var $ = jqnc(),_ = this,effects;
	_.element = $(element);
	var effects = {
		'fade':[{'display':'none'},{'opacity':'show'}],
		'slide-down':[{'display':'block','top':_.element.height()*-1},{'top':0}],
		'slide-up':[{'display':'block','top':_.element.height()},{'top':0}],
		'slide-left':[{'display':'block','left':_.element.width()*-1},{'left':0}],
		'slide-right':[{'display':'block','left':_.element.width()},{'left':0}],
		'wipe':[{'display':'block','height':0},{'height':_.element.height()}]
	},ordering = ['normal','reverse','shuffle'];

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
	};

	_.transitionTo = function (slide) {
		_.slide = slide;
		_.startTransition();
	};

	_.shuffleList = function () {
		for (var i = 0; i < _.total; i++) _.shuffling.push(i);
	};

	_.startTransition();
}

/**
 * Auto-initialize slideshow behaviors for ul's with a 'slideshow' class
 **/
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

/**
 * Generate a carousel (looping slider) of images
 **/
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
	};

	_.scrollLeft.click(function () {
		return _.scrolltoPage(_.page-1);
	});

	_.scrollRight.click(function () {
		return _.scrolltoPage(_.page+1);
	});

}

/**
 * Auto-initialize carousel behaviors for divs with a 'carousel' class
 **/
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

/**
 * Form validation
 **/
function validate (form) {
	if (!form) return false;
	var $ = jqnc(),
		passed = true,
		passwords = new Array(),
		error = new Array(),
		inputs = $(form).find('input,select,textarea').not(':hidden'),
		required = 'required',
		title = 'title';

	$.each(inputs,function (id,field) {
		input = $(field).removeClass('error');
		label = $('label[for=' + input.attr('id') + ']').removeClass('error');

		if (input.attr('disabled') == true) return;

		if (input.hasClass(required) && input.val() == "")
			error = new Array(sjss.REQUIRED_FIELD.replace(/%s/,input.attr(title)),field);

		if (input.hasClass(required) && input.attr('type') == "checkbox" && !input.attr('checked'))
			error = new Array(sjss.REQUIRED_CHECKBOX.replace(/%s/,input.attr(title)),field);

		if (input.hasClass('email') && !input.val().match(new RegExp('^[a-zA-Z0-9._-]+@([a-zA-Z0-9.-]+\.)+[a-zA-Z0-9.-]{2,4}$')))
			error = new Array(sjss.INVALID_EMAIL,field);

		if ( input.attr('class') && ( chars = input.attr('class').match( new RegExp('min(\\d+)') ) ) ) {
			if (input.val() != "" && input.val().length < chars[1])
				error = new Array(sjss.MIN_LENGTH.replace(/%s/,input.attr(title)).replace(/%d/,chars[1]),field);
		}

		if (input.hasClass('passwords')) {
			passwords.push(field);
			if (passwords.length == 2 && passwords[0].value != passwords[1].value)
				error = new Array(sjss.PASSWORD_MISMATCH,passwords[1]);
		}

		if (error[1] && error[1].id == input.attr('id')) {
			input.addClass('error');
			label.addClass('error');
		}

	});

	form.shopp_validate = false;
	$(form).trigger('shopp_validate',[error]);
	if (form.shopp_validate) {
		error = form.shopp_validate;
		if (error[1] && $('#'+error[1].id).length > 0) {
			$('#'+error[1].id).addClass('error');
			$('label[for=' + error[1].id + ']').addClass('error');
		}

	}

	if (error.length > 0) {
		error[1].focus();
		if ($(form).hasClass('validation-alerts')) alert(error[0]);
		passed = false;
	}
	return passed;
}

/**
 * Auto-initialize form validation forms with a 'validate' class
 **/
function validateForms () {
	var $=jqnc(),forms = $('form.validate');
	forms.bind('submit.validate',function (e) {
		return validate(this);
	});
}

/**
 * DOM-ready initializations
 **/
jQuery(document).ready(function() {
	var $=jqnc();
	validateForms();
	catalogViewHandler();
	slideshows();
	carousels();
	if ($.fn.colorbox) {
		$('a.shopp-zoom').colorbox({photo:true});
		$('a.shopp-zoom.gallery').each(function () {
			var id = $(this).attr('class').match(/product\_(\d+)/)[1];
			if (typeof(cbo) != "undefined") $(this).attr('rel','gallery-'+id).colorbox(cbo);
			else $(this).attr('rel','gallery-'+id).colorbox({slideshow:true,slideshowSpeed:3500});
		});
	}
	$('select.shopp-orderby-menu').change(function () { this.form.submit(); });
	$('select.shopp-categories-menu').change(function () { document.location.href = $(this).val(); });
	if (sjss.nocache) $(window).unload(function () { return; });
});