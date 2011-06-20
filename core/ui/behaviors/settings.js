/*!
 * settings.js - Module settings UI library
 * Copyright © 2010 by Ingenesis Limited
 * Licensed under the GPLv3 {@see license.txt}
 */

function ModuleSetting (module,name,label,multi) {
	var $ = jqnc(), _ = this, methods = 0;

	_.name = name;
	_.module = module;

	_.label = label;
	_.settings = new Array();
	_.ui = new Array();

	_.tables = new Array();
	_.columns = new Array();
	_.settingsTable = false;
	_.deleteButton = false;

	_.multi = multi;

	_.payment = function () {
		if (_.label instanceof Array) {
			if (_.label[methods]) label = _.label[methods];
			else label = _.name;
		} else label = _.label;

		var id = label.toLowerCase().replace(/[^\w+]/,'-'),
			labelName = 'settings['+_.module+']'+(_.multi?'[label]['+methods+']':'[label]'),
		 	ui = '<th scope="row"><label>'+_.name+'</label><br />'+
				'<input type="text" name="'+labelName+'" value="'+label+'" id="'+id+'-label" size="16" class="selectall" /><br />'+
				'<small><label for="'+id+'-label">'+SHOPP_PAYMENT_OPTION+'</label></small></th>',
		 	row = $('<tr />').html(ui).appendTo('#payment-settings'),
		 	settingsTableCell = $('<td/>').appendTo(row),
		 	deleteButton = $('<button type="button" name="deleteRate" class="delete deleteRate"><img src="'+SHOPP_PLUGINURI+'/core/ui/icons/delete.png" width="16" height="16" /></button>').appendTo(settingsTableCell).hide(),
			bodyBG = $('html').css('background-color'),
			deletingBG = "#ffebe8";

		$('#active-gateways').val($('#active-gateways').val()+","+_.module);

		row.hover(function () {
				deleteButton.show();
			}, function () {
				deleteButton.hide();
		});

		deleteButton.hover (function () {
				row.animate({backgroundColor:deletingBG},250);
			},function() {
				row.animate({backgroundColor:bodyBG},250);
		});

		deleteButton.click (function () {
			if (confirm(SHOPP_DELETE_PAYMENT_OPTION)) {
				row.remove();
				gateways = $('#active-gateways').val().split(",");
				var index = $.inArray(_.module,gateways);
				gateways.splice(index,1);
				$('#active-gateways').val(gateways.join());
				$('#payment-option-menu option[value='+_.module+']').attr('disabled',false);
			}
		});

		_.tables[methods] = $('<table class="settings"/>').appendTo(settingsTableCell);

		$.each(_.settings,function (id,element) {
			var input,markup;
			if (_.multi) input = new SettingInput(_.module,element.attrs,element.options,methods);
			else input = new SettingInput(_.module,element.attrs,element.options);
			markup = input.generate();
			$(markup).appendTo(_.column(element.target,methods));
			if (input.type == "multimenu") input.selectall();
		});
		if (_.multi) {
			methods++;
			if (_.label instanceof Array && _.label[methods]) _.payment();
		}
	};

	_.shipping = function () {
		if (_.label instanceof Array) {
			if (_.label[methods]) label = _.label[methods];
			else label = _.name;
		} else label = _.label;
		var id = label.toLowerCase().replace(/[^\w+]/,'-'),
			labelName = 'settings['+_.module+']'+(_.multi?'[label]['+methods+']':'[label]'),
			ui = '<th scope="row"><label>'+_.name+'</label><br />'+
				'<input type="text" name="'+labelName+'" value="'+label+'" id="'+id+'-label" size="16" class="selectall" /><br />'+
				'<small><label for="'+id+'-label">'+SHOPP_PAYMENT_OPTION+'</label></small></th>',
			row = $('<tr />').html(ui).appendTo('#payment-settings'),
			settingsTableCell = $('<td/>').appendTo(row),
			deleteButton = $('<button type="button" name="deleteRate" class="delete deleteRate"><img src="'+SHOPP_PLUGINURI+'/core/ui/icons/delete.png" width="16" height="16" /></button>').appendTo(settingsTableCell).hide(),
			bodyBG = $('html').css('background-color'),
			deletingBG = "#ffebe8";

		$('#active-gateways').val($('#active-gateways').val()+","+_.module);

		row.hover(function () {
				deleteButton.show();
			}, function () {
				deleteButton.hide();
		});

		deleteButton.hover (function () {
				row.animate({backgroundColor:deletingBG},250);
			},function() {
				row.animate({backgroundColor:bodyBG},250);
		});

		deleteButton.click (function () {
			if (confirm(SHOPP_DELETE_PAYMENT_OPTION)) {
				row.remove();
				gateways = $('#active-gateways').val().split(",");
				var index = $.inArray(_.module,gateways);
				gateways.splice(index,1);
				$('#active-gateways').val(gateways.join());
				$('#payment-option-menu option[value='+_.module+']').attr('disabled',false);
			}
		});

		_.tables[methods] = $('<table class="settings"/>').appendTo(settingsTableCell);

		$.each(_.settings,function (id,element) {
			var input,markup;
			if (_.multi) input = new SettingInput(_.module,element.attrs,element.options,methods);
			else input = new SettingInput(_.module,element.attrs,element.options);
			markup = input.generate();
			$(markup).appendTo(_.column(element.target,methods));
			if (input.type == "multimenu") input.selectall();
		});
		if (_.multi) {
			methods++;
			if (_.label[methods]) _.payment();
		}
	};

	_.storage = function () {
		$.each(_.settings,function (id,element) {
			element.attrs.setting = _.setting;
			var markup,input = new SettingInput(_.module,element.attrs,element.options);
			input.name += '['+_.setting+']';
			input.id += '-'+_.setting;
			if (element.attrs.value) {
				if (element.attrs.value instanceof Object) {
					if (element.attrs.value[_.setting]) input.value = element.attrs.value[_.setting];
					else input.value = '';
				} else input.value = element.attrs.value;
			} else input.value = '';

			if (element.attrs.selected) {
				if (element.attrs.selected instanceof Object) {
					if (element.attrs.selected[_.setting]) input.selected = element.attrs.selected[_.setting];
					else input.selected = 0;
				} else input.selected = element.attrs.selected;
			} else input.selected = 0;


			markup = input.generate();
			$(markup).appendTo(_.element);
			if (input.type == "multimenu") input.selectall();
		});
	};

 	_.newInput = function (column,attrs,options) {
		var input = {
			'target':column,
			'attrs':attrs,
			'options':options
		};
		_.settings.push(input);
	};

	_.column = function (index,methods) {
		if (!_.columns[methods]) _.columns[methods] = new Array();
		if (!_.columns[methods][index]) return _.columns[methods][index] = $('<td/>').appendTo(_.tables[methods]);
		else return _.columns[methods][index];
	};

	_.behaviors = function () {};

};

function SettingInput (module,attrs,options,method) {
	var $ = jqnc(), _ = this,
		types = new Array('text','password','hidden','checkbox','menu','textarea','multimenu','p','button');

	if (!attrs.name) return '';

	_.type = ($.inArray(attrs.type,types) != -1)?attrs.type:'text';
	_.name = 'settings['+module+']['+attrs.name+']';
	if (method !== undefined ) _.name += '['+method+']';
	if (attrs.value) {
		if (attrs.value instanceof Array) {
			if (attrs.value[method]) _.value = attrs.value[method];
			else _.value = '';
		} else _.value = attrs.value;
	} else _.value = '';

	_.normal = (attrs.normal)?attrs.normal:'';
	_.keyed = (attrs.keyed)?(attrs.keyed == 'true'?true:false):true;
	_.selected = (attrs.selected)?attrs.selected:false;
	_.checked = (attrs.checked)?attrs.checked:false;
	_.readonly = (attrs.readonly)?'readonly':false;
	_.size = (attrs.size)?attrs.size:'20';
	_.cols = (attrs.size)?attrs.size:'40';
	_.rows = (attrs.size)?attrs.size:'3';
	_.classes = (attrs.classes)?attrs.classes:'';
	_.id = (attrs.id)?attrs.id:'settings-'+module.toLowerCase().replace(/[^\w+]/,'-')+'-'+attrs.name.toLowerCase();
	if (method !== undefined ) _.id += '-'+method;
	_.options = options;
	_.content = (attrs.content)?attrs.content:'';
	_.label = (attrs.label)?attrs.label:false;
	if (_.label instanceof Object && attrs.setting)
		_.label = attrs.label[attrs.setting];

	_.generate = function () {
		if (!_.name) return;
		if (_.type == "p") return _.paragraph();
		if (_.type == "button") return _.button();
		if (_.type == "checkbox") return _.checkbox();
		if (_.type == "menu") return _.menu();
		if (_.type == "multimenu") return _.multimenu();
		if (_.type == "textarea") return _.textarea();
		return _.text();
	};

	_.text = function () {
		var readonly = (_.readonly)?' readonly="readonly"':'',
		 	html = '<div><input type="'+_.type+'" name="'+_.name+'" value="'+_.value+'" size="'+_.size+'" class="'+_.classes+'" id="'+_.id+'"'+readonly+' />';
		if (_.label) html += '<br /><label for="'+_.id+'">'+_.label+'</label></div>\n';
		return html;
	};

	_.textarea = function () {
		var html = '<div><textarea name="'+_.name+'" cols="'+_.cols+'" rows="'+_.rows+'" class="'+_.classes+'" id="'+_.id+'">'+_.value+'</textarea>';
		if (_.label) html += '<br /><label for="'+_.id+'">'+_.label+'</label></div>\n';
		return html;
	};

	_.checkbox = function () {
		var html = '<div><label for="'+_.id+'">';
		html += '<input type="hidden" name="'+_.name+'" value="'+_.normal+'" id="'+_.id+'-default" />';
		html += '<input type="'+_.type+'" name="'+_.name+'" value="'+_.value+'" class="'+_.classes+'" id="'+_.id+'"'+((_.checked)?' checked="checked"':'')+' />';
		if (_.label) html += '&nbsp;'+_.label;
		html += '</label></div>\n';
		return html;
	};

	_.menu = function () {
		var select,value,
			selected = _.selected,
		 	keyed = _.keyed,
		 	html = '<div>';
		html += '<select name="'+_.name+'" class="'+_.classes+'" id="'+_.id+'">';
		if (_.options) {
			$.each(_.options,function (val,label) {
				value = (keyed && val !== false)?' value="'+val+'"':'';
				select = ((keyed && selected == val) || selected == label)?' selected="selected"':'';
				html += '<option'+value+select+'>'+label+'</option>';
			});
		}
		html += '</select>';

		if (_.label) html += '<br /><label for="'+_.id+'">'+_.label+'</label></div>\n';
		return html;
	};

	_.multimenu = function () {
		var html = '<div><div class="multiple-select">',
			alt = true,
			selected = _.selected;
		html += '<ul id="'+_.id+'" class="'+_.classes+'">';
		if (_.options) {
			html += '<li><input type="checkbox" name="select-all" id="'+_.id+'-select-all" class="selectall" /><label for="'+_.id+'-select-all"><strong>'+SHOPP_SELECT_ALL+'</strong></label></li>';

			$.each(_.options,function (key,label) {
				var id = _.id+'-'+label.toLowerCase().replace(/[^\w]/,'-'),
					checked = '';
				if ($.inArray(key,selected) != -1) checked = ' checked="true"';
				html += '<li'+(alt?' class="odd"':'')+'>';
				html += '<input type="checkbox" name="'+_.name+'[]" value="'+key+'" id="'+id+'"'+checked+' />';
				html += '<label for="'+id+'">'+label+'</label>';
				html += '</li>';
				alt = !alt;
			});
		}
		html += '</ul></div>';

		if (_.label) html += '<br /><label for="'+_.id+'">'+_.label+'</label></div>\n';

		return html;
	};

	_.button = function () {
		classes = (_.classes)?' class="button-secondary '+_.classes+'"':' class="button-secondary"';
		type = (_.type)?' type="'+_.type+'"':'';
		var html = '<div><button'+type+' name="'+_.name+'" value="'+_.value+'" id="'+_.id+'"'+classes+'>'+_.label+'</button></div>\n';
		return html;
	};

	_.paragraph = function () {
		var id = (_.id)?' id="'+_.id+'"':'',
		 	classes = (_.classes)?' class="'+_.classes+'"':'',
		 	html = '';
		if (_.label) html += '<label><strong>'+_.label+'</strong></label>';
		html += '<div'+id+classes+'>'+_.content+'</div>';
		return html;
	};

	_.selectall = function () {
		var id = _.id;
		$('#'+id+'-select-all').change(function () {
			if (this.checked) $('#'+id+' input').attr('checked',true);
			else $('#'+id+' input').attr('checked',false);
		});
	};

};