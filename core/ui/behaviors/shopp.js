/*!
 * shopp.js - Shopp behavioral utility library
 * Copyright © 2008-2010 by Ingenesis Limited
 * Licensed under the GPLv3 {@see license.txt}
 */

/**
 * Provides shorthand for returning a clean jQuery object
 **/
function jqnc () { return jQuery.noConflict(); }

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

/**
 * Returns the currency format from Shopp settings
 **/
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

/**
 * Returns a default currency format $#,###.##
 **/
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
function asPercent (n,f,p) {
	var currencyFormat = getCurrencyFormat();
	if (currencyFormat && !f) f = copyOf(currencyFormat);
	if (!f) f = defaultCurrencyFormat();

	f.precision = p?p:1;
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
 * Rounds Number objects to a specified precision
 **/
if (!Number.prototype.roundFixed) {
	Number.prototype.roundFixed = function(precision) {
		var power = Math.pow(10, precision || 0);
		return String(Math.round(this * power)/power);
	}
}

/**
 * Usability behavior to add automatic select-all to a field 
 * when activating the field by mouse click
 **/
function quickSelects (target) {
	jQuery('input.selectall').mouseup(function () { this.select(); });
}

/**
 * Converts HTML-encoded entities
 **/
function htmlentities (string) {
	if (!string) return "";
	string = string.replace(new RegExp(/&#(\d+);/g), function() {
		return String.fromCharCode(RegExp.$1);
	});
	return string;
}

/**
 * Parse JSON data with native browser parsing or
 * as a last resort use evil(), er... eval()
 **/
jQuery.parseJSON = function (data) {
	if (typeof (JSON) !== 'undefined' && 
		typeof (JSON.parse) === 'function') {
			try {
				return JSON.parse(data);
			} catch (e) {
				return false;
			}
	} else return eval('(' + data + ')');
}

/**
 * DOM-ready initialization
 **/
jQuery(document).ready(function() {
	var $=jqnc();
		
	// Automatically reformat currency and money inputs
	$('input.currency, input.money').change(function () { 
		this.value = asMoney(this.value); }).change();
	
	quickSelects();
	
});