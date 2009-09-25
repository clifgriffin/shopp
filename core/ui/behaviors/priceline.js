function Pricelines () {
	var $=jQuery.noConflict();
	this.idx = 0;
	this.row = new Object();
	this.variations = new Array();
	this.linked = new Array();

	this.add = function (options,data,target,attachment) {
		var key = xorkey(options);
		var p = new Priceline(this.idx,options,data,target,attachment);
		this.row[key] = p;

		if (data.context == "variation") {
			if (attachment) {
				var targetkey = parseInt(target.optionkey.val());
				var index = $.inArray(targetkey,this.variations);
				if (index != -1) {
					if (attachment == "before") this.variations.splice(index,0,xorkey(p.options));
				 	else this.variations.splice(index+1,0,xorkey(p.options));
				}
			} else this.variations.push(xorkey(p.options));
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
			console.log("Looping variations");
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
			console.log("Looping variations");
			this.row[key].unlinkInputs();
		}
		this.linked.splice(0,1);
	}

	this.updateVariationLinks = function () {
		if (!this.linked) return;
		for (var key in this.row) {
			console.log("Looping variations");
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

function Priceline (id,options,data,target,attachment) {
	var $ = jQuery.noConflict();
	var _self = this;
	this.id = id;
	this.options = options;
	this.data = data;
	this.label = false;
	this.links = new Array();
	this.inputs = new Array();

	var interfaces = new Object();

	// Give this entry a unique runtime id
	var i = this.id;

	// Build the interface
	this.row = $('<div id="row-'+i+'" class="priceline" />');
	if (attachment == "after") this.row.insertAfter(target);
	else if (attachment == "before") this.row.insertBefore(target);
	else this.row.appendTo(target);

	var heading = $('<div class="pricing-label" />').appendTo(this.row);
	var labelText = $('<label for="label['+i+']" />').appendTo(heading);

	this.label = $('<input type="hidden" name="price['+i+'][label]" id="label['+i+']" />').appendTo(heading);
	this.label.change(function () { labelText.text($(this).val()); });

	var myid = $('<input type="hidden" name="price['+i+'][id]" id="priceid-'+i+'" />').appendTo(heading);
	var productid = $('<input type="hidden" name="price['+i+'][product]" id="product['+i+']" />').appendTo(heading);
	var context = $('<input type="hidden" name="price['+i+'][context]" />').appendTo(heading);
	var optionkey = $('<input type="hidden" name="price['+i+'][optionkey]" class="optionkey" />').appendTo(heading);
	this.row.optionkey = optionkey;
	var optionids = $('<input type="hidden" name="price['+i+'][options]" />').appendTo(heading);
	var sortorder = $('<input type="hidden" name="sortorder[]" value="'+i+'" />').appendTo(heading);

	var typeOptions = "";
	$(priceTypes).each(function (t,option) { typeOptions += '<option value="'+option.value+'">'+option.label+'</option>'; });
	var type = $('<select name="price['+i+'][type]" id="type-'+i+'"></select>').html(typeOptions).appendTo(heading);

	var dataCell = $('<div class="pricing-ui clear" />').appendTo(this.row);

	var pricingTable = $('<table/>').addClass('pricing-table').appendTo(dataCell);

	var headingsRow = $('<tr/>').appendTo(pricingTable);	
	var inputsRow = $('<tr/>').appendTo(pricingTable);

	var priceHeading = $('<th/>').appendTo(headingsRow);
	var priceLabel = $('<label for="price['+i+']">'+PRICE_LABEL+'</label>').appendTo(priceHeading);
	var priceCell = $('<td/>').appendTo(inputsRow);
	var price = $('<input type="text" name="price['+i+'][price]" id="price['+i+']" value="0" size="10" class="selectall right"  />').appendTo(priceCell);
	$('<br />').appendTo(priceCell);

	$('<input type="hidden" name="price['+i+'][tax]" value="on" />').appendTo(priceCell);
	var tax = $('<input type="checkbox" name="price['+i+'][tax]" id="tax['+i+']" value="off" />').appendTo(priceCell);
	var taxLabel = $('<label for="tax['+i+']"> '+NOTAX_LABEL+'</label><br />').appendTo(priceCell);

	var salepriceHeading = $('<th><label for="sale['+i+']"> '+SALE_PRICE_LABEL+'</label></th>').appendTo(headingsRow);
	var salepriceToggle = $('<input type="checkbox" name="price['+i+'][sale]" id="sale['+i+']" />').prependTo(salepriceHeading);
	$('<input type="hidden" name="price['+i+'][sale]" value="off" />').prependTo(salepriceHeading);

	var salepriceCell = $('<td/>').appendTo(inputsRow);
	var salepriceStatus = $('<span>'+NOT_ON_SALE_TEXT+'</span>').addClass('status').appendTo(salepriceCell);
	var salepriceField = $('<span/>').addClass('fields').appendTo(salepriceCell).hide();
	var saleprice = $('<input type="text" name="price['+i+'][saleprice]" id="saleprice['+i+']" size="10" class="selectall right" />').appendTo(salepriceField);

	var donationHeading = $('<th/>').appendTo(headingsRow);
	var donationCell = $('<td width="80%" />').appendTo(inputsRow);
	$('<input type="hidden" name="price['+i+'][donation][var]" value="off" />').appendTo(donationCell);
	var donationVar = $('<input type="checkbox" name="price['+i+'][donation][var]" id="donation-var['+i+']" value="on" />').appendTo(donationCell);
	$('<label for="donation-var['+i+']"> '+DONATIONS_VAR_LABEL+'</label><br />').appendTo(donationCell);
	$('<input type="hidden" name="price['+i+'][donation][min]" value="off" />').appendTo(donationCell);
	var donationMin = $('<input type="checkbox" name="price['+i+'][donation][min]" id="donation-min['+i+']" value="on" />').appendTo(donationCell);
	$('<label for="donation-min['+i+']"> '+DONATIONS_MIN_LABEL+'</label>').appendTo(donationCell);

	var shippingHeading = $('<th><label for="shipping-'+i+'"> '+SHIPPING_LABEL+'</label></th>').appendTo(headingsRow);
	var shippingToggle = $('<input type="checkbox" name="price['+i+'][shipping]" id="shipping-'+i+'" />').prependTo(shippingHeading);
	$('<input type="hidden" name="price['+i+'][shipping]" value="off" />').prependTo(shippingHeading);

	var shippingCell = $('<td/>').appendTo(inputsRow);
	var shippingStatus = $('<span>'+FREE_SHIPPING_TEXT+'</span>').addClass('status').appendTo(shippingCell);
	var shippingFields = $('<span/>').addClass('fields').appendTo(shippingCell).hide();
	var weight = $('<input type="text" name="price['+i+'][weight]" id="weight['+i+']" size="8" class="selectall right" />').appendTo(shippingFields);
	var shippingWeightLabel = $('<label for="weight['+i+']" title="Weight"> '+WEIGHT_LABEL+((weightUnit)?' ('+weightUnit+')':'')+'</label><br />').appendTo(shippingFields);
	var shippingfee = $('<input type="text" name="price['+i+'][shipfee]" id="shipfee['+i+']" size="8" class="selectall right" />').appendTo(shippingFields);
	var shippingFeeLabel = $('<label for="shipfee['+i+']" title="Additional shipping fee calculated per quantity ordered (for handling costs, etc)"> '+SHIPFEE_LABEL+'</label><br />').appendTo(shippingFields);

	var inventoryHeading = $('<th><label for="inventory['+i+']"> '+INVENTORY_LABEL+'</label></th>').appendTo(headingsRow);
	var inventoryToggle = $('<input type="checkbox" name="price['+i+'][inventory]" id="inventory['+i+']" />').prependTo(inventoryHeading);
	$('<input type="hidden" name="price['+i+'][inventory]" value="off" />').prependTo(salepriceHeading);
	var inventoryCell = $('<td/>').appendTo(inputsRow);
	var inventoryStatus = $('<span>'+NOT_TRACKED_TEXT+'</span>').addClass('status').appendTo(inventoryCell);
	var inventoryField = $('<span/>').addClass('fields').appendTo(inventoryCell).hide();
	var stock = $('<input type="text" name="price['+i+'][stock]" id="stock['+i+']" size="8" class="selectall right" />').appendTo(inventoryField);
	var inventoryLabel =$('<label for="stock['+i+']"> '+IN_STOCK_LABEL+'</label>').appendTo(inventoryField);
	var inventoryBr = $('<br/>').appendTo(inventoryField);
	var sku = $('<input type="text" name="price['+i+'][sku]" id="sku['+i+']" size="8" title="Enter a unique tracking number for this product option." class="selectall" />').appendTo(inventoryField);
	var skuLabel =$('<label for="sku['+i+']" title="'+SKU_LABEL_HELP+'"> '+SKU_LABEL+'</label>').appendTo(inventoryField);

	var downloadHeading = $('<th><label for="download['+i+']">Product Download</label></th>').appendTo(headingsRow);
	var downloadCell = $('<td width="31%" />').appendTo(inputsRow);
	var downloadFile = $('<div></div>').html('No product download.').appendTo(downloadCell);

	var uploadHeading = $('<td rowspan="2" class="controls" width="75" />').appendTo(headingsRow);
	if (storage == "fs") {
		var filePathCell = $('<div></div>').prependTo(downloadCell).hide();
		var filePath = $('<input type="text" name="price['+i+'][downloadpath]" value="" title="Enter file path relative to: '+productspath+'" class="filepath" />').appendTo(filePathCell).change(function () {
			$(this).removeClass('warning').addClass('verifying');
			$.ajax({url:fileverify_url+'&action=wp_ajax_shopp_verify_file',
					type:"POST",
					data:'filepath='+$(this).val(),
					timeout:10000,
					dataType:'text',
					success:function (results) {
						filePath.removeClass('verifying');
						if (results == "OK") return;
						if (results == "NULL") filePath.addClass("warning").attr('title',FILE_NOT_FOUND_TEXT);
						if (results == "ISDIR") filePath.addClass("warning").attr('title',FILE_ISDIR_TEXT);
						if (results == "READ") filePath.addClass("warning").attr('title',FILE_NOT_READ_TEXT);
					}
			});
		});
		var filePathButton = $('<button type="button" class="button-secondary"><small>By File Path</small></button>').appendTo(uploadHeading).click(function () {
			filePathCell.slideToggle();
		});

	}

	var uploadHolder = $('<div id="flash-product-uploader-'+i+'"></div>').appendTo(uploadHeading);
	var uploadButton = $('<button type="button" class="button-secondary"><small>'+UPLOAD_FILE_BUTTON_TEXT+'</small></button>').appendTo(uploadHeading);

	var uploader = new FileUploader($(uploadHolder).attr('id'),uploadButton,i,downloadFile);

	this.disable = function () {
		type.val('N/A').trigger('change.value');
	}
	
	this.setOptions = function(options) {
		var update = false;
		if (options) {
			if (options != this.options) update = true;
			this.options = options;
		}
		optionkey.val(xorkey(this.options));
		if (update) this.updateLabel();
	}
	
	this.updateKey = function () {
		optionkey.val(xorkey(this.options));
	}
	
	this.updateLabel = function () {
		var string = "";
		var ids = "";
		if (this.options) {
			$(this.options).each(function(index,id) {
				if (string == "") string = $(productOptions[id]).val();
				else string += ", "+$(productOptions[id]).val();
				if (ids == "") ids = id;
				else ids += ","+id;
			});
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
			if ($(input).attr('type') == "checkbox") type = "click.linkedinputs";
			$(input).bind(type,function () {
				var value = $(this).val();
				var checked = $(this).attr('checked');
				$.each(_self.links,function (l,option) {
					$.each(Pricelines.linked[option],function (id,key) {
						if (key == xorkey(_self.options)) return;
						if (!Pricelines.row[key]) return;
						if ($(input).attr('type') == "checkbox")
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
	
	this.inputs = new Array(
		type,price,tax,salepriceToggle,saleprice,donationVar,donationMin,
		shippingToggle,weight,shippingfee,inventoryToggle,stock,sku);
	this.updateKey();
	this.updateLabel();
	
	interfaces['All'] = new Array(priceHeading, priceCell, salepriceHeading, salepriceCell, shippingHeading, shippingCell, inventoryHeading, inventoryCell, downloadHeading, downloadCell, uploadHeading, donationHeading, donationCell);
	if (pricesPayload) {		
		interfaces['Shipped'] = new Array(priceHeading, priceCell, salepriceHeading, salepriceCell, shippingHeading, shippingCell, inventoryHeading, inventoryCell);
		interfaces['Download'] = new Array(priceHeading, priceCell, salepriceHeading, salepriceCell, downloadHeading, downloadCell, uploadHeading);
		interfaces['Virtual'] = new Array(priceHeading, priceCell, salepriceHeading, salepriceCell, inventoryHeading, inventoryCell);
	} else {
		interfaces['Shipped'] = new Array(priceHeading, priceCell, shippingHeading, shippingCell);
		interfaces['Virtual'] = new Array(priceHeading, priceCell);
		interfaces['Download'] = new Array(priceHeading, priceCell);
	}
	interfaces['Donation'] = new Array(priceHeading, priceCell, donationHeading, donationCell);

	// Alter the interface depending on the type of price line
	type.bind('change.value',function () {
		var ui = type.val();
		$.each(interfaces['All'],function() { $(this).hide(); });
		priceLabel.html(PRICE_LABEL);
		if (interfaces[ui])
			$.each(interfaces[ui],function() { $(this).show(); });
		if ($(this).val() == "Donation") {
			priceLabel.html(AMOUNT_LABEL);
			tax.attr('checked','true').trigger('change.value');
		}
	});

	// Optional input's checkbox toggle behavior
	salepriceToggle.bind('change.value',function () {
		if (this.checked) { salepriceStatus.hide(); salepriceField.show(); }
		else { salepriceStatus.show(); salepriceField.hide(); }
		if ($.browser.msie) $(this).blur();
	}).click(function () {
		if ($.browser.msie) $(this).trigger('change.value');
		if (this.checked) saleprice.focus().select();
	
	});

	shippingToggle.bind('change.value',function () {
		if (this.checked) { shippingStatus.hide(); shippingFields.show(); }
		else { shippingStatus.show(); shippingFields.hide(); }
		if ($.browser.msie) $(this).blur();
	}).click(function () {
		if ($.browser.msie) $(this).trigger('change.value');
		if (this.checked) weight.focus().select();
	});

	inventoryToggle.bind('change.value',function () {
		if (this.checked) { inventoryStatus.hide(); inventoryField.show(); }
		else { inventoryStatus.show(); inventoryField.hide(); }
		if ($.browser.msie) $(this).blur();
	}).click(function () {
		if ($.browser.msie) $(this).trigger('change.value');
		if (this.checked) stock.focus().select();
	});

	price.bind('change.value',function() { this.value = asMoney(this.value); }).trigger('change.value');
	saleprice.bind('change.value',function() { this.value = asMoney(this.value); }).trigger('change.value');
	shippingfee.bind('change.value',function() { this.value = asMoney(this.value); }).trigger('change.value');
	weight.bind('change.value',function() { var num = new Number(this.value); this.value = num.roundFixed(3); }).trigger('change.value');

	// Set the context for the db
	if (data && data.context) context.val(data.context);
	else context.val('product');

	// Set field values if we are rebuilding a priceline from 
	// database data
	if (data && data.label) {
		this.label.val(htmlentities(data.label)).change();
		type.val(data.type);
		myid.val(data.id);
	
		productid.val(data.product);
		sku.val(data.sku);
		price.val(asMoney(data.price));

		if (data.sale == "on") salepriceToggle.attr('checked','true').trigger('change.value');
		if (data.shipping == "on") shippingToggle.attr('checked','true').trigger('change.value');
		if (data.inventory == "on") inventoryToggle.attr('checked','true').trigger('change.value');
	
		if (data.donation) {
			if (data.donation['var'] == "on") donationVar.attr('checked',true);
			if (data.donation['min'] == "on") donationMin.attr('checked',true);
		}

		saleprice.val(asMoney(data.saleprice));
		shippingfee.val(asMoney(data.shipfee));
		weight.val(data.weight).trigger('change.value');
		stock.val(data.stock);
	
		if (data.download) {
			if (data.filedata.mimetype)	data.filedata.mimetype = data.filedata.mimetype.replace(/\//gi," ");
			downloadFile.attr('class','file '+data.filedata.mimetype).html(data.filename+'<br /><small>'+readableFileSize(data.filesize)+'</small>').click(function () {
				window.location.href = adminurl+"admin.php?page=shopp-lookup&download="+data.download;
			});

		}
		if (data.tax == "off") tax.attr('checked','true');
	} else {
		if (type.val() == "Shipped") shippingToggle.attr('checked','true').trigger('change.value');
	}

	// Improve usability for quick data entry by
	// causing fields to automatically select all
	// contents when focused/activated
	quickSelects(this.row);

	// Initialize the interface by triggering the
	// priceline type change behavior 
	type.change();

}