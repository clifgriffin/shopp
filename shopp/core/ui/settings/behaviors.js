
var ModuleSetting = function (module,name,label,multi) {
	var $ = jQuery.noConflict();
	var _self = this;
	var methods = 0;
	
	this.name = name;
	this.module = module;
	
	this.label = label;
	this.settings = new Array();
	this.ui = new Array();

	this.tables = new Array();
	this.columns = new Array();
	this.settingsTable = false;
	this.deleteButton = false;

	this.multi = multi;

	this.payment = function () {
		if (this.label instanceof Array) {
			if (this.label[methods]) var label = this.label[methods];
			else var label = this.name;
		} else var label = this.label;
		var id = label.toLowerCase().replace(/[^\w+]/,'-');
		var ui = '';
		ui += '';
		ui += '<th scope="row"><label>'+this.name+'</label><br />';
		if (this.multi)
			ui += '<input type="text" name="settings['+this.module+'][label]['+methods+']" value="'+label+'" id="'+id+'-label" size="16" class="selectall" /><br />';
		else ui += '<input type="text" name="settings['+this.module+'][label]" value="'+label+'" id="'+id+'-label" size="16" class="selectall" /><br />';
		ui += '<small><label for="'+id+'-label">'+SHOPP_PAYMENT_OPTION+'</label></small>';
		ui += '</th>';
		var row = $('<tr class="form-required" />').html(ui).appendTo('#payment-settings');
		
		var settingsTableCell = $('<td/>').appendTo(row);
		var deleteButton = $('<button type="button" name="deleteRate" class="delete deleteRate" />').appendTo(settingsTableCell).hide();
		$('<img src="'+SHOPP_PLUGINURI+'/core/ui/icons/delete.png" width="16" height="16"  />').appendTo(deleteButton);
		this.deleteButton.row = row;
		var bodyBG = $('html').css('background-color');
		var deletingBG = "#ffebe8";

		$('#active-gateways').val($('#active-gateways').val()+","+this.module);

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
				var index = $.inArray(_self.module,gateways);
				gateways.splice(index,1);
				$('#active-gateways').val(gateways.join());
				$('#payment-option-menu option[value='+_self.module+']').attr('disabled',false);
			}
		});

		this.tables[methods] = $('<table class="settings"/>').appendTo(settingsTableCell);
		
		$.each(this.settings,function (id,element) {
			if (_self.multi) var input = new SettingInput(_self.module,element.attrs,element.options,methods);
			else var input = new SettingInput(_self.module,element.attrs,element.options);
			var markup = input.generate();
			$(markup).appendTo(_self.column(element.target,methods));
			if (input.type == "multimenu") input.selectall();
		});
		if (this.multi) {
			methods++;
			if (this.label[methods]) this.payment();
		}
	}
	
 	this.newInput = function (column,attrs,options) {
		var input = {
			'target':column,
			'attrs':attrs,
			'options':options
		};
		this.settings.push(input);
	}
		
	this.column = function (index,methods) {
		if (!this.columns[methods]) this.columns[methods] = new Array();
		if (!this.columns[methods][index]) return this.columns[methods][index] = $('<td/>').appendTo(this.tables[methods])
		else return this.columns[methods][index];
	}
	
	this.behaviors = function () {}
	
}

var SettingInput = function (module,attrs,options,method) {
	var $ = jQuery.noConflict();
	var _self = this;

	var types = new Array('text','password','hidden','checkbox','menu','textarea','multimenu','p','button');

	if (!attrs.name) return '';
	
	this.type = ($.inArray(attrs.type,types) != -1)?attrs.type:'text';
	this.name = 'settings['+module+']['+attrs.name+']';
	if (method !== undefined ) this.name += '['+method+']'	;
	if (attrs.value) {
		if (attrs.value instanceof Array) {
			if (attrs.value[method]) this.value = attrs.value[method];
			else this.value = '';
		} else this.value = attrs.value;
	} else this.value = '';
	
	this.normal = (attrs.normal)?attrs.normal:'';
	this.selected = (attrs.selected)?attrs.selected:0;
	this.checked = (attrs.checked)?attrs.checked:false;
	this.size = (attrs.size)?attrs.size:'20';
	this.cols = (attrs.size)?attrs.size:'40';
	this.rows = (attrs.size)?attrs.size:'3';
	this.classes = (attrs.classes)?attrs.classes:'';
	this.id = (attrs.id)?attrs.id:'settings-'+module.toLowerCase().replace(/[^\w+]/,'-')+'-'+attrs.name.toLowerCase();
	if (method !== undefined ) this.id += '-'+method;
	this.options = options;
	this.content = (attrs.content)?attrs.content:'';
	this.label = (attrs.label)?attrs.label:false;	
	
	this.generate = function () {
		if (!this.name) return;
		if (this.type == "p") return this.paragraph();
		if (this.type == "button") return this.button();
		if (this.type == "checkbox") return this.checkbox();
		if (this.type == "menu") return this.menu();
		if (this.type == "multimenu") return this.multimenu();
		if (this.type == "textarea") return this.textarea();
		return this.text();
	}
	
	this.text = function () {
		var html = '<div><input type="'+this.type+'" name="'+this.name+'" value="'+this.value+'" size="'+this.size+'" class="'+this.classes+'" id="'+this.id+'" />';
		if (this.label) html += '<br /><label for="'+this.id+'">'+this.label+'</label></div>\n';
		return html;
	}

	this.textarea = function () {
		var html = '<div><textarea name="'+this.name+'" cols="'+this.cols+'" rows="'+this.rows+'" class="'+this.classes+'" id="'+this.id+'">'+this.value+'</textarea>';
		if (this.label) html += '<br /><label for="'+this.id+'">'+this.label+'</label></div>\n';
		return html;
	}

	this.checkbox = function () {
		var html = '<div><label for="'+this.id+'">';
		html += '<input type="hidden" name="'+this.name+'" value="'+this.normal+'" id="'+this.id+'-default" />';
		html += '<input type="'+this.type+'" name="'+this.name+'" value="'+this.value+'" class="'+this.classes+'" id="'+this.id+'"'+((this.checked)?' checked="checked"':'')+' />';
		if (this.label) html += '&nbsp;'+this.label;
		html += '</label></div>\n';
		return html;
	}

	this.menu = function () {
		var html = '<div>';
		html += '<select name="'+this.name+'" value="'+this.value+'" class="'+this.classes+'" id="'+this.id+'">';
		if (this.options) {
			$.each(this.options,function (value,label) {
				html += '<option'+(value?' value="'+value+'"':'')+'>'+label+'</option>';
			});
		}
		html += '</select>';
		
		if (this.label) html += '<br /><label for="'+this.id+'">'+this.label+'</label></div>\n';
		return html;
	}
	
	this.multimenu = function () {
		var html = '<div><div class="multiple-select">';
		html += '<ul id="'+this.id+'" class="'+_self.classes+'">';
		if (this.options) {
			html += '<li><input type="checkbox" name="select-all" id="'+this.id+'-select-all" class="selectall" /><label for="'+this.id+'-select-all"><strong>'+SHOPP_SELECT_ALL+'</strong></label></li>';

			var alt = true;
			var selected = this.selected;
			$.each(this.options,function (key,label) {
				var id = _self.id+'-'+label.toLowerCase().replace(/[^\w]/,'-');
				var checked = '';
				if ($.inArray(key,selected) != -1) checked = ' checked="true"';
				html += '<li'+(alt?' class="odd"':'')+'>';
				html += '<input type="checkbox" name="'+_self.name+'[]" value="'+key+'" id="'+id+'"'+checked+' />';
				html += '<label for="'+id+'">'+label+'</label>';				
				html += '</li>';
				alt = !alt;
			});
		}
		html += '</ul></div>';
		
		if (this.label) html += '<br /><label for="'+this.id+'">'+this.label+'</label></div>\n';
		
		return html;
	}

	this.button = function () {
		classes = (this.classes)?' class="button-secondary '+this.classes+'"':' class="button-secondary"';
		var html = '<div><button name="'+this.name+'" value="'+this.value+'" size="'+this.size+'" id="'+this.id+'"'+classes+'>'+this.label+'</button></div>\n';
		return html;
	}
	
	this.paragraph = function () {
		var id = (this.id)?' id="'+this.id+'"':'';
		var classes = (this.classes)?' class="'+this.classes+'"':'';
		var html = '';
		if (this.label) html += '<label><strong>'+this.label+'</strong></label>';
		html += '<div'+id+classes+'>'+this.content+'</div>';
		return html;
	}
	
	this.selectall = function () {
		var id = this.id;
		$('#'+id+'-select-all').change(function () {
			if (this.checked) $('#'+id+' input').attr('checked',true);
			else $('#'+id+' input').attr('checked',false);
		});
	}
	
}