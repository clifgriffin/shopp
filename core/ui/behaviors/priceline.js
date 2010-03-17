var Pricelines = function () {
	var $=jQuery.noConflict();
	this.idx = 0;
	this.row = new Object();
	this.variations = new Array();
	this.addons = new Array();
	this.linked = new Array();

	this.add = function (options,data,target,attachment) {
		if (!data) data = {'context':'product'};
		
		if (data.context == "variation") {
			var key = xorkey(options);
			var p = new Priceline(this.idx,options,data,target,attachment);
			this.row[key] = p;

			if (attachment) {
				var targetkey = parseInt(target.optionkey.val());
				var index = $.inArray(targetkey,this.variations);
				if (index != -1) {
					if (attachment == "before") this.variations.splice(index,0,xorkey(p.options));
				 	else this.variations.splice(index+1,0,xorkey(p.options));
				}
			} else this.variations.push(xorkey(p.options));
		}
		if (data.context == "addon") {
			var p = new Priceline(this.idx,options,data,target,attachment);
			this.row[this.idx] = p;
		}
		if (data.context == "product") {
			var p = new Priceline(0,options,data,target,attachment);
			this.row[0] = p;
		}

		$('#prices').val(this.idx++);
	}
	
	this.exists = function (key) {
		if (this.row[key]) return true;
		return false;
	}
	
	this.remove = function (row) {
		var index = $.inArray(row,this.variations);
		if (index != -1) this.variations.splice(index,1);
		
		this.row[row].row.remove(); // Remove UI
		delete this.row[row];		// Remove data
	}
	
	this.reorderVariation = function (key,options) {
		var variation = this.row[key];
		variation.row.appendTo('#variations-pricing');
		variation.setOptions(options);
		
		var index = $.inArray(key,this.variations);
		if (index == -1) return;
		this.variations.splice(index,1);
		this.variations.push(xorkey(variation.options));
	}
	
	this.reorderAddon = function (id,pricegroup) {
		var addon = this.row[id];
		addon.row.appendTo(pricegroup);
	}
	
	this.updateVariationsUI = function (type) {
		for (var i in this.variations) {
			var key = this.variations[i];
			if (!Pricelines.row[key]) {
				delete this.variations[i]; continue;
			}
			var row = Pricelines.row[key];
			row.updateTabIndex(i);	// Re-number tab indexes
			if (type && type == "tabs") continue;
			row.unlinkInputs();			// Reset linking
			for (var option in this.linked) {
				if ($.inArray(option,this.row[key].options) != -1) {
					if (!this.linked[option][key]) this.linked[option].push(key);
					this.row[key].linkInputs(option);
				}
			}
		}
	}
		
	this.linkVariations = function (option) {
		if (!option) return;
		for (var key in this.row) {
			if ($.inArray(option.toString(),this.row[key].options) != -1) {
				if (!this.linked[option]) this.linked[option] = new Array();
				this.linked[option].push(key);
				this.row[key].linkInputs(option);
			}
		}
	}
	
	this.unlinkVariations = function (option) {
		if (!option) return;
		if (!this.linked[option]) return;
		for (var row in this.linked[option]) 
			this.linked[option][key].unlinkInputs(option);
		this.linked.splice(option,1);
	}
	
	this.unlinkAll = function () {
		for (var key in this.row) {
			this.row[key].unlinkInputs();
		}
		this.linked.splice(0,1);
	}

	this.updateVariationLinks = function () {
		if (!this.linked) return;
		for (var key in this.row) {
			this.row[key].unlinkInputs();
		}
		for (var option in this.linked) {
			this.linked[option] = false;
			this.linkVariations(option);
		}
	}
	
	this.allLinked = function () {
		if (this.linked[0]) return true;
		return false;
	}
	
	this.linkAll = function () {
		this.unlinkAll();
		this.linked = new Array();
		this.linked[0] = new Array();
		for (var key in this.row) {
			if (key == 0) continue;
			this.linked[0].push(key);
			this.row[key].linkInputs(0);
		}
	}
	
}

var Priceline = function (id,options,data,target,attachment) {
	var $ = jQuery.noConflict();
	var _self = this;
	this.id = id;				// Index position in the Pricelines.rows array
	this.options = options;		// Option indexes for options linked to this priceline
	this.data = data;			// The data associated with this priceline
	this.label = false;			// The label of the priceline
	this.links = new Array();	// Option linking registry
	this.inputs = new Array();	// Inputs registry

	// Give this entry a unique runtime id
	var i = this.id;

	// Build the interface
	var fn = 'price['+i+']'; // Field name base
	
	this.row = $('<div id="row-'+i+'" class="priceline" />');
	if (attachment == "after") this.row.insertAfter(target);
	else if (attachment == "before") this.row.insertBefore(target);
	else this.row.appendTo(target);

	var heading = $('<div class="pricing-label" />').appendTo(this.row);
	var labelText = $('<label for="label-'+i+'" />').appendTo(heading);

	this.label = $('<input type="hidden" name="price['+i+'][label]" id="label-'+i+'" />').appendTo(heading);
	this.label.change(function () { labelText.text($(this).val()); });
	
	$('<input type="hidden" name="'+fn+'[id]" id="priceid-'+i+'" value="'+data.id+'" />'+
		'<input type="hidden" name="'+fn+'[product]" id="product-'+i+'" />'+
		'<input type="hidden" name="'+fn+'[context]" id="context-'+i+'"/>'+
		'<input type="hidden" name="'+fn+'[optionkey]" id="optionkey-'+i+'" class="optionkey" />'+
		'<input type="hidden" name="'+fn+'[options]" id="options-'+i+'" value="" />'+
		'<input type="hidden" name="sortorder[]" id="sortorder-'+i+'" value="'+i+'" />').appendTo(heading);

	var myid = $('#priceid-'+i);
	var context = $('#context-'+i);
	var optionids = $('#options-'+i);
	var sortorder = $('#sortorder-'+i);
	var optionkey = $('#optionkey-'+i).appendTo(heading);
	this.row.optionkey = optionkey;
	
	var typeOptions = "";
	$(priceTypes).each(function (t,option) { typeOptions += '<option value="'+option.value+'">'+option.label+'</option>'; });
	var type = $('<select name="price['+i+'][type]" id="type-'+i+'"></select>').html(typeOptions).appendTo(heading);

	if (data && data.label) {
		this.label.val(htmlentities(data.label)).change();
		type.val(data.type);
	}

	var dataCell = $('<div class="pricing-ui clear" />').appendTo(this.row);	
	var pricingTable = $('<table/>').addClass('pricing-table').appendTo(dataCell);
	var headingsRow = $('<tr/>').appendTo(pricingTable);	
	var inputsRow = $('<tr/>').appendTo(pricingTable);

	// Build individual fields
	this.price = function (price,tax) {
		var hd = $('<th><label for="price-'+i+'">'+PRICE_LABEL+'</label></th>').appendTo(headingsRow);
		var ui = $('<td><input type="text" name="'+fn+'[price]" id="price-'+i+'" value="0" size="10" class="selectall money right" /><br />'+
					 '<input type="hidden" name="'+fn+'[tax]" value="on" /><input type="checkbox" name="'+fn+'[tax]" id="tax-'+i+'" value="off" />'+
					 '<label for="tax-'+i+'"> '+NOTAX_LABEL+'</label><br /></td>').appendTo(inputsRow);

		this.p = $('#price-'+i).val(price);
		this.t = $('#tax-'+i).attr('checked',tax == "off"?true:false);
	}
	
	this.saleprice = function (toggle,saleprice) {
		var hd = $('<th><input type="hidden" name="'+fn+'[sale]" value="off" />'+
					'<input type="checkbox" name="'+fn+'[sale]" id="sale-'+i+'" />'+
					'<label for="sale-'+i+'"> '+SALE_PRICE_LABEL+'</label></th>').appendTo(headingsRow);
		var ui = $('<td><span class="status">'+NOT_ON_SALE_TEXT+'</span><span class="ui">'+
					'<input type="text" name="'+fn+'[saleprice]" id="saleprice-'+i+'" size="10" class="selectall money right" />'+
					'</span></td>').appendTo(inputsRow);

		dis = ui.find('span.status');
		ui = ui.find('span.ui').hide();

		this.sp = $('#saleprice-'+i);
		this.sp.val(saleprice);

		this.spt = $('#sale-'+i).attr('checked',(toggle == "on"?true:false)).toggler(dis,ui,this.sp);
	}
	
	this.donation = function (price,tax,variable,minimum) {
		var hd = $('<th><label for="price-'+i+'"> '+AMOUNT_LABEL+'</label></th>').appendTo(headingsRow);
		var ui = $('<td><input type="text" name="'+fn+'[price]" id="price-'+i+'" value="0" size="10" class="selectall money right" /><br />'+
					 '<input type="hidden" name="'+fn+'[tax]" value="on" /><input type="checkbox" name="'+fn+'[tax]" id="tax-'+i+'" value="off" />'+
					 '<label for="tax-'+i+'"> '+NOTAX_LABEL+'</label><br /></td>').appendTo(inputsRow);

		this.p = $('#price-'+i).val(price);
		this.t = $('#tax-'+i).attr('checked',tax == "on"?false:true);
		
		var hd2 = $('<th />').appendTo(headingsRow);
		var ui2 = $('<td width="80%"><input type="hidden" name="'+fn+'[donation][var]" value="off" />'+
					'<input type="checkbox" name="'+fn+'[donation][var]" id="donation-var-'+i+'" value="on" />'+
					'<label for="donation-var-'+i+'"> '+DONATIONS_VAR_LABEL+'</label><br />'+
					'<input type="hidden" name="'+fn+'[donation][min]" value="off" />'+
					'<input type="checkbox" name="'+fn+'[donation][min]" id="donation-min-'+i+'" value="on" />'+
					'<label for="donation-min-'+i+'"> '+DONATIONS_MIN_LABEL+'</label><br /></td>').appendTo(inputsRow);
					
		this.dv = $('#donation-var-'+i).attr('checked',variable == "on"?true:false);
		this.dm = $('#donation-min-'+i).attr('checked',minimum == "on"?true:false);
	}
	
	this.shipping = function (toggle,weight,fee,dimensions) {
		var hd = $('<th><input type="hidden" name="'+fn+'[shipping]" value="off" /><input type="checkbox" name="'+fn+'[shipping]" id="shipping-'+i+'" /><label for="shipping-'+i+'"> '+SHIPPING_LABEL+'</label></th>').appendTo(headingsRow);
		var ui = $('<td><span class="status">'+FREE_SHIPPING_TEXT+'</span>'+
					'<span class="ui"><input type="text" name="'+fn+'[weight]" id="weight-'+i+'" size="8" class="selectall right" />'+
					'<label for="weight-'+i+'" id="weight-label-'+i+'" title="'+WEIGHT_LABEL+'"> '+WEIGHT_LABEL+((weightUnit)?' ('+weightUnit+')':'')+'</label><br />'+
					'<input type="text" name="'+fn+'[shipfee]" id="shipfee-'+i+'" size="8" class="selectall money right" />'+
					'<label for="shipfee-'+i+'" title="'+SHIPFEE_XTRA+'"> '+SHIPFEE_LABEL+'</label><br />'+
					'</span></td>').appendTo(inputsRow);

		dis = ui.find('span.status');
		inf = ui.find('span.ui').hide();

		if (!weight) weight = 0;
		this.w = $('#weight-'+i);
		this.w.val(weight).bind('change.value',function () {
			var num = new Number(this.value); this.value = num.roundFixed(3);
		}).trigger('change.value');

		this.fee = $('#shipfee-'+i);
		this.fee.val(fee);
		
		this.st = hd.find('#shipping-'+i).attr('checked',(toggle == "off"?false:true)).toggler(dis,inf,this.w);
		
		if (dimensionsRequired) {
			$('#weight-label-'+i).html(' '+dimensionUnit+'<sup>3</sup>/'+weightUnit);
			var dc = $('<div class="dimensions">'+
				'<div class="inline">'+
				'<input type="text" name="'+fn+'[dimensions][weight]" id="dimensions-weight-'+i+'" size="4" class="selectall right weight" />'+
				(weightUnit?'<label>'+weightUnit+'&nbsp;</label>':'')+'<br />'+
				'<label for="dimensions-weight-'+i+'" title="'+WEIGHT_LABEL+'"> '+WEIGHT_LABEL+'</label>'+
				'</div>'+
				'<div class="inline">'+
				'<input type="text" name="'+fn+'[dimensions][length]" id="dimensions-length-'+i+'" size="4" class="selectall right" />'+
				'<label> x </label><br />'+
				'<label for="dimensions-length-'+i+'" title="'+LENGTH_LABEL+'"> '+LENGTH_LABEL+'</label>'+
				'</div>'+
				'<div class="inline">'+
				'<input type="text" name="'+fn+'[dimensions][width]" id="dimensions-width-'+i+'" size="4" class="selectall right" />'+
				'<label> x </label><br /><label for="dimensions-width-'+i+'" title="'+WIDTH_LABEL+'"> '+WIDTH_LABEL+'</label>'+
				'</div>'+
				'<div class="inline">'+
				'<input type="text" name="'+fn+'[dimensions][height]" id="dimensions-height-'+i+'" size="4" class="selectall right" />'+
				'<label>'+dimensionUnit+'</label><br />'+
				'<label for="dimensions-height-'+i+'" title="'+HEIGHT_LABEL+'"> '+HEIGHT_LABEL+'</label>'+
				'</div>'+
				'</div>').hide().appendTo(ui);
			if (!dimensions) {
				dimensions = {};
				dimensions.weight = 0; dimensions.length = 0; dimensions.width = 0; dimensions.height = 0;
			}
			
			var dw = $('#dimensions-weight-'+i).val(dimensions.weight).bind('change.value',function () {
				var num = new Number(this.value); this.value = num.roundFixed(0);
			}).trigger('change.value');
			
			var dl = $('#dimensions-length-'+i).val(dimensions.length).bind('change.value',function () {
				var num = new Number(this.value); this.value = num.roundFixed(0);
			}).trigger('change.value');

			var dwd = $('#dimensions-width-'+i).val(dimensions.width).bind('change.value',function () {
				var num = new Number(this.value); this.value = num.roundFixed(0);
			}).trigger('change.value');

			var dh = $('#dimensions-height-'+i).val(dimensions.height).bind('change.value',function () {
				var num = new Number(this.value); this.value = num.roundFixed(0);
			}).trigger('change.value');
			
			var weight = this.w;
			var toggleDimensions = function () {
				weight.toggleClass('extoggle');
				dc.toggle(); dw.focus();
				var d = 0; var w = 0;
				dc.find('input').each(function (id,dims) {
					if ($(dims).hasClass('weight')) { w = dims.value; }
					else {
						if (d == 0) d = dims.value;
						else d *= dims.value;
					}
				});
				if (!isNaN(d/w)) weight.val((d/w)).trigger('change.value');
			}

			dh.blur(toggleDimensions);
			weight.click(toggleDimensions);
			weight.attr('readonly',true);

		}
		
	}
	
	this.inventory = function (toggle,stock,sku) {
		var hd = $('<th><input type="hidden" name="'+fn+'[inventory]" value="off" />'+
					'<input type="checkbox" name="'+fn+'[inventory]" id="inventory-'+i+'" />'+
					'<label for="inventory-'+i+'"> '+INVENTORY_LABEL+'</label></th>').appendTo(headingsRow);
		var ui = $('<td><span class="status">'+NOT_TRACKED_TEXT+'</span>'+
					'<span class="ui"><input type="text" name="'+fn+'[stock]" id="stock-'+i+'" size="8" class="selectall right" />'+
					'<label for="stock-'+i+'"> '+IN_STOCK_LABEL+'</label><br />'+
					'<input type="text" name="'+fn+'[sku]" id="sku-'+i+'" size="8" title="'+SKU_XTRA+'" class="selectall" />'+
					'<label for="sku-'+i+'" title="'+SKU_LABEL_HELP+'"> '+SKU_LABEL+'</label></span></td>').appendTo(inputsRow);

		dis = ui.find('span.status');
		ui = ui.find('span.ui').hide();

		if (!stock) stock = 0;
		this.stock = $('#stock-'+i);
		this.stock.val(stock).trigger('change.value',function() { var n = new Number(this.value); this.value = n; });

		this.sku = $('#sku-'+i);
		this.sku.val(sku);
		
		this.it = hd.find('#inventory-'+i).attr('checked',(toggle == "on"?true:false)).toggler(dis,ui,this.stock);
	}
	
	this.download = function (fileid,filename,filedata) {
		var hd = $('<th><label for="download-'+i+'">'+PRODUCT_DOWNLOAD_LABEL+'</label></th>').appendTo(headingsRow);
		var ui = $('<td width="31%"><input type="hidden" name="'+name+'[downloadpath]" id="download_path"/><div id="file-'+i+'">'+NO_DOWNLOAD+'</div></td>').appendTo(inputsRow);
		
		var hd2 = $('<td rowspan="2" class="controls" width="75"><button type="button" class="button-secondary" style="white-space: nowrap;" id="file-selector-'+i+'"><small>'+SELECT_FILE_BUTTON_TEXT+'&hellip;</small></button></td>').appendTo(headingsRow);

		this.file = $('#file-'+i);
		this.selector = $('#file-selector-'+i).FileChooser(i,this.file);
		
		if (fileid) {
			if (filedata.mime) filedata.mime = filedata.mime.replace(/\//gi," ");
			this.file.attr('class','file '+filedata.mime).html(filename+'<br /><small>'+readableFileSize(filedata.size)+'</small>').click(function () {
				window.location.href = adminurl+"admin.php?src=download&shopp_download="+fileid;
			});
		}
	}
	
	$.fn.toggler = function (s,ui,f) {
		this.bind('change.value',function () {
			if (this.checked) { s.hide(); ui.show(); }
			else { s.show(); ui.hide(); }
			if ($.browser.msie) $(this).blur();
		}).click(function () {
			if ($.browser.msie) $(this).trigger('change.value');
			if (this.checked) f.focus().select();
		}).trigger('change.value');
		return $(this);
	}
	
	this.Shipped = function (data) {
		this.price(data.price,data.tax);
		this.saleprice(data.saleprice);
		this.shipping(data.shipping,data.weight,data.shipfee,data.dimensions);
		this.inventory(data.inventory,data.stock,data.sku);
	}

	this.Virtual = function (data) {
		this.price(data.price,data.tax);
		this.saleprice(data.saleprice);
		this.inventory(data.inventory,data.stock,data.sku);
	}

	this.Download = function (data) {
		this.price(data.price,data.tax);
		this.saleprice(data.saleprice);
		this.download(data.download,data.filename,data.filedata);
	}
	
	this.Donation = function (data) {
		this.donation(data.price,data.tax,data.donation['var'],data.donation['min']);
	}
	
	// Alter the interface depending on the type of price line
	type.bind('change.value',function () {
		headingsRow.empty();
		inputsRow.empty();
		var ui = type.val();
		if (ui == "Shipped") _self.Shipped(data);
		if (ui == "Virtual") _self.Virtual(data);
		if (ui == "Download") _self.Download(data);
		if (ui == "Donation") _self.Donation(data);

		// Global behaviors
		inputsRow.find('input.money').bind('change.value',function () {
			this.value = asMoney(this.value);
		}).trigger('change.value');
		quickSelects(inputsRow);
		
	}).trigger('change.value');
	
	
	// Setup behaviors
	this.disable = function () { type.val('N/A').trigger('change.value'); }
	
	// Set the context for the db
	if (data && data.context) context.val(data.context);
	else context.val('product');
	
	this.setOptions = function(options) {
		var update = false;
		if (options) {
			if (options != this.options) update = true;
			this.options = options;
		}
		if (context.val() == "variation")
			optionkey.val(xorkey(this.options));
		if (update) this.updateLabel();
	}
	
	this.updateKey = function () {
		optionkey.val(xorkey(this.options));
	}
	
	this.updateLabel = function () {
		var type = context.val();

		var string = "";
		var ids = "";
		if (this.options) {
			if (type == "variation") {
				$(this.options).each(function(index,id) {
					if (string == "") string = $(productOptions[id]).val();
					else string += ", "+$(productOptions[id]).val();
					if (ids == "") ids = id;
					else ids += ","+id;
				});
			}
			if (type == "addon") {
				string = $(productAddons[this.options]).val();
				ids = this.options;
			}
		}
		if (string == "") string = DEFAULT_PRICELINE_LABEL;
		this.label.val(htmlentities(string)).change();
		optionids.val(ids);
	}
	
	this.updateTabIndex = function (row) {
		row = new Number(row);
		$.each(this.inputs,function(i,input) {
			$(input).attr('tabindex',((row+1)*100)+i);
		});
	}
	
	this.linkInputs = function (option) {
		this.links.push(option);
		$.each(this.inputs,function (i,input) {
			if (!input) return;
			var type = "change.linkedinputs";
			var elem = $(input);
			if (elem.attr('type') == "checkbox") type = "click.linkedinputs";
			$(input).bind(type,function () {
				var value = $(this).val();
				var checked = $(this).attr('checked');
				$.each(_self.links,function (l,option) {
					$.each(Pricelines.linked[option],function (id,key) {
						if (key == xorkey(_self.options)) return;
						if (!Pricelines.row[key]) return;
						if (elem.attr('type') == "checkbox")
							$(Pricelines.row[key].inputs[i]).attr('checked',checked);
						else $(Pricelines.row[key].inputs[i]).val(value);
						$(Pricelines.row[key].inputs[i]).trigger('change.value');
					});
				});
			});
		});
	}
	
	this.unlinkInputs = function (option) {
		if (option !== false) {
			index = $.inArray(option,this.links);
			this.links.splice(index,1);
		}
		$.each(this.inputs,function (i,input) {
			if (!input) return;
			var type = "blur.linkedinputs";
			if ($(input).attr('type') == "checkbox") type = "click.linkedinputs";
			$(input).unbind(type);
		});
	}

	if (type.val() != "N/A")
		this.inputs = new Array(
			type,this.p,this.t,this.spt,this.sp,this.dv,this.dm,
			this.st,this.w,this.fee,this.it,this.stock,this.sku);

	this.updateKey();
	this.updateLabel();

}