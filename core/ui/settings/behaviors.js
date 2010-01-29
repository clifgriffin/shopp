
var ModuleSetting = function (module,name,label) {
	var $ = jQuery.noConflict();
	var i = 0;
	var _self = this;
	
	this.name = name;
	this.module = module;
	this.label = label;
	this.settings = new Array();
	this.columns = new Array();
	this.settingsTable = false;
	this.deleteButton = false;
	this.row = false;

	this.payment = function () {
		var id = this.label.toLowerCase();
		var ui = '';
		ui += '';
		ui += '<th scope="row"><label>'+this.name+'</label><br />';
		ui += '<input type="text" name="settings['+this.module+'][label]" value="'+this.label+'" id="'+id+'-label" size="16" tabindex="'+(i+1)+'00" class="selectall" /><br />';
		ui += '<small><label for="'+id+'-label">'+SHOPP_PAYMENT_OPTION+'</label></small>';
		ui += '</th>';
		this.row = $('<tr class="form-required" />').html(ui).appendTo('#payment-settings');
		
		var settingsTableCell = $('<td/>').appendTo(this.row);
		this.deleteButton = $('<button type="button" name="deleteRate" class="delete deleteRate" />').appendTo(settingsTableCell).hide();
		$('<img src="'+SHOPP_PLUGINURI+'/core/ui/icons/delete.png" width="16" height="16"  />').appendTo(this.deleteButton);

		var bodyBG = $('html').css('background-color');
		var deletingBG = "#ffebe8";
		this.row.hover(function () {
				_self.deleteButton.show();
			}, function () {
				_self.deleteButton.hide();
		});

		this.deleteButton.hover (function () {
				_self.row.animate({backgroundColor:deletingBG},250);
			},function() {
				_self.row.animate({backgroundColor:bodyBG},250);		
		});

		this.settingsTable = $('<table class="settings"/>').appendTo(settingsTableCell);
		$.each(this.settings,function (id,element) {
			var input = new SettingInput(_self.module,element.attrs,element.options);
			var markup = input.generate();
			$(markup).appendTo(_self.column(element.target));
			if (input.type == "multimenu") input.selectall();
		});
		
	}
	
 	this.newInput = function (column,attrs,options) {
		var input = {
			'target':column,
			'attrs':attrs,
			'options':options
		};
		this.settings.push(input);
	}
	
	this.column = function (index) {
		if (!this.columns[index]) return this.columns[index] = $('<td/>').appendTo(this.settingsTable)
		else return this.columns[index];
	}

}

var SettingInput = function (module,attrs,options) {
	var $ = jQuery.noConflict();
	var _self = this;
	
	var types = new Array('text','password','hidden','checkbox','menu','textarea','multimenu','p','button');

	if (!attrs.name) return '';
	
	this.type = ($.inArray(attrs.type,types) != -1)?attrs.type:'text';
	this.name = 'settings['+module+']['+attrs.name+']';
	this.value = (attrs.value)?attrs.value:'';
	this.normal = (attrs.normal)?attrs.normal:'';
	this.selected = (attrs.selected)?attrs.selected:0;
	this.checked = (attrs.checked)?attrs.checked:false;
	this.size = (attrs.size)?attrs.size:'20';
	this.cols = (attrs.size)?attrs.size:'40';
	this.rows = (attrs.size)?attrs.size:'3';
	this.classes = (attrs.classes)?attrs.classes:'';
	this.id = (attrs.id)?attrs.id:'settings-'+module.toLowerCase().replace(/[^\w]/,'-')+'-'+attrs.name.toLowerCase();
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
			$.each(this.options,function (id,label) {
				var id = _self.id+'-'+label.toLowerCase().replace(/[^\w]/,'-');
				var checked = '';
				if ($.inArray(label,selected) != -1) checked = ' checked="true"';
				html += '<li'+(alt?' class="odd"':'')+'>';
				html += '<input type="checkbox" name="'+_self.name+'[]" value="'+label+'" id="'+id+'"'+checked+' />';
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