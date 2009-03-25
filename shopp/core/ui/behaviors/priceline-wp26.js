function addPriceLine (target,options,data,attachment) {

	// Give this entry a unique runtime id
	var i = pricingidx;

	// Build the interface
	var row = $('<tr id="row['+i+']"></tr>');
	if (attachment == "after") row.insertAfter(target);
	else if (attachment == "before") row.insertBefore(target);
	else row.appendTo(target);

	var heading = $('<th class="pricing-label"></th>').appendTo(row);
	var labelText = $('<label for="label['+i+']"></label>').appendTo(heading);
	
	var label = $('<input type="hidden" name="price['+i+'][label]" id="label['+i+']" />').appendTo(heading);
	label.change(function () { labelText.text($(this).val()); });

	var myid = $('<input type="hidden" name="price['+i+'][id]" id="id['+i+']" />').appendTo(heading);
	var productid = $('<input type="hidden" name="price['+i+'][product]" id="product['+i+']" />').appendTo(heading);
	var context = $('<input type="hidden" name="price['+i+'][context]" />').appendTo(heading);
	var optionkey = $('<input type="hidden" name="price['+i+'][optionkey]" />').appendTo(heading);
	var optionids = $('<input type="hidden" name="price['+i+'][options]" />').appendTo(heading);
	var sortorder = $('<input type="hidden" name="sortorder[]" value="'+i+'" />').appendTo(heading);

	var dataCell = $('<td/>').appendTo(row);

	var pricingTable = $('<table/>').addClass('pricing-table').appendTo(dataCell);

	var headingsRow = $('<tr/>').appendTo(pricingTable);	
	var inputsRow = $('<tr/>').appendTo(pricingTable);

	var typeHeading = $('<th><label for="type-'+i+'">'+TYPE_LABEL+'</label></th>').appendTo(headingsRow);
	var typeCell = $('<td/>').appendTo(inputsRow);
	var typeOptions = "";
	$(priceTypes).each(function (t,option) { typeOptions += '<option value="'+option.value+'">'+option.label+'</option>'; });
	var type = $('<select name="price['+i+'][type]" id="type-'+i+'" tabindex="'+(i+1)+'02"></select>').html(typeOptions).appendTo(typeCell);

	var priceHeading = $('<th/>').appendTo(headingsRow);
	var priceLabel = $('<label for="price['+i+']">'+PRICE_LABEL+'</label>').appendTo(priceHeading);
	var priceCell = $('<td/>').appendTo(inputsRow);
	var price = $('<input type="text" name="price['+i+'][price]" id="price['+i+']" value="0" size="10" class="selectall right" tabindex="'+(i+1)+'03" />').appendTo(priceCell);
	$('<br />').appendTo(priceCell);

	$('<input type="hidden" name="price['+i+'][tax]" tabindex="'+(i+1)+'04" value="on" />').appendTo(priceCell);
	var tax = $('<input type="checkbox" name="price['+i+'][tax]" id="tax['+i+']" tabindex="'+(i+1)+'04" value="off" />').appendTo(priceCell);
	var taxLabel = $('<label for="tax['+i+']"> '+NOTAX_LABEL+'</label><br />').appendTo(priceCell);

	var salepriceHeading = $('<th><label for="sale['+i+']"> '+SALE_PRICE_LABEL+'</label></th>').appendTo(headingsRow);
	var salepriceToggle = $('<input type="checkbox" name="price['+i+'][sale]" id="sale['+i+']" tabindex="'+(i+1)+'05" />').prependTo(salepriceHeading);
	$('<input type="hidden" name="price['+i+'][sale]" value="off" />').prependTo(salepriceHeading);

	var salepriceCell = $('<td/>').appendTo(inputsRow);
	var salepriceStatus = $('<span>'+NOT_ON_SALE_TEXT+'</span>').addClass('status').appendTo(salepriceCell);
	var salepriceField = $('<span/>').addClass('fields').appendTo(salepriceCell).hide();
	var saleprice = $('<input type="text" name="price['+i+'][saleprice]" id="saleprice['+i+']" size="10" class="selectall right" tabindex="'+(i+1)+'06" />').appendTo(salepriceField);

	var donationHeading = $('<th/>').appendTo(headingsRow);
	var donationCell = $('<td width="58%" />').appendTo(inputsRow);
	$('<input type="hidden" name="price['+i+'][donation][var]" value="off" />').appendTo(donationCell);
	var donationVar = $('<input type="checkbox" name="price['+i+'][donation][var]" id="donation-var['+i+']" tabindex="'+(i+1)+'05" value="on" />').appendTo(donationCell);
	$('<label for="donation-var['+i+']"> '+DONATIONS_VAR_LABEL+'</label><br />').appendTo(donationCell);
	$('<input type="hidden" name="price['+i+'][donation][min]" value="off" />').appendTo(donationCell);
	var donationMin = $('<input type="checkbox" name="price['+i+'][donation][min]" id="donation-min['+i+']" tabindex="'+(i+1)+'06" value="on" />').appendTo(donationCell);
	$('<label for="donation-min['+i+']"> '+DONATIONS_MIN_LABEL+'</label>').appendTo(donationCell);

	var shippingHeading = $('<th><label for="shipping-'+i+'"> '+SHIPPING_LABEL+'</label></th>').appendTo(headingsRow);
	var shippingToggle = $('<input type="checkbox" name="price['+i+'][shipping]" id="shipping-'+i+'" tabindex="'+(i+1)+'07" />').prependTo(shippingHeading);
	$('<input type="hidden" name="price['+i+'][shipping]" value="off" />').prependTo(shippingHeading);

	var shippingCell = $('<td/>').appendTo(inputsRow);
	var shippingStatus = $('<span>'+FREE_SHIPPING_TEXT+'</span>').addClass('status').appendTo(shippingCell);
	var shippingFields = $('<span/>').addClass('fields').appendTo(shippingCell).hide();
	var weight = $('<input type="text" name="price['+i+'][weight]" id="weight['+i+']" size="8" class="selectall right" tabindex="'+(i+1)+'08" />').appendTo(shippingFields);
	var shippingWeightLabel = $('<label for="weight['+i+']" title="Weight"> '+WEIGHT_LABEL+((weightUnit)?' ('+weightUnit+')':'')+'</label><br />').appendTo(shippingFields);
	var shippingfee = $('<input type="text" name="price['+i+'][shipfee]" id="shipfee['+i+']" size="8" class="selectall right" tabindex="'+(i+1)+'08" />').appendTo(shippingFields);
	var shippingFeeLabel = $('<label for="shipfee['+i+']" title="Additional shipping fee calculated per quantity ordered (for handling costs, etc)"> '+SHIPFEE_LABEL+'</label><br />').appendTo(shippingFields);

	var inventoryHeading = $('<th><label for="inventory['+i+']"> '+INVENTORY_LABEL+'</label></th>').appendTo(headingsRow);
	var inventoryToggle = $('<input type="checkbox" name="price['+i+'][inventory]" id="inventory['+i+']" tabindex="'+(i+1)+'10" />').prependTo(inventoryHeading);
	$('<input type="hidden" name="price['+i+'][inventory]" value="off" />').prependTo(salepriceHeading);
	var inventoryCell = $('<td/>').appendTo(inputsRow);
	var inventoryStatus = $('<span>'+NOT_TRACKED_TEXT+'</span>').addClass('status').appendTo(inventoryCell);
	var inventoryField = $('<span/>').addClass('fields').appendTo(inventoryCell).hide();
	var stock = $('<input type="text" name="price['+i+'][stock]" id="stock['+i+']" size="8" class="selectall right" tabindex="'+(i+1)+'11" />').appendTo(inventoryField);
	var inventoryLabel =$('<label for="stock['+i+']"> '+IN_STOCK_LABEL+'</label>').appendTo(inventoryField);
	var inventoryBr = $('<br/>').appendTo(inventoryField);
	var sku = $('<input type="text" name="price['+i+'][sku]" id="sku['+i+']" size="8" title="Enter a unique tracking number for this product option." class="selectall" tabindex="'+(i+1)+'12" />').appendTo(inventoryField);
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
		var filePathButton = $('<button type="button" class="button-secondary" tabindex="'+(i+1)+'14"><small>By File Path</small></button>').appendTo(uploadHeading).click(function () {
			filePathCell.slideToggle();
		});
	
	}

	var uploadHolder = $('<div id="flash-product-uploader-'+i+'"></div>').appendTo(uploadHeading);
	var uploadButton = $('<button type="button" class="button-secondary" tabindex="'+(i+1)+'13"><small>'+UPLOAD_FILE_BUTTON_TEXT+'</small></button>').appendTo(uploadHeading);

	var uploader = new FileUploader($(uploadHolder).attr('id'),uploadButton,i,downloadFile);
		
	// Build an object to reference and control/update this entry
	var Pricing = new Object();
	Pricing.id = pricingidx;
	Pricing.options = options;
	Pricing.data = data;
	Pricing.row = row;
	Pricing.label = label;
	Pricing.disable = function () { type.val('N/A').change(); }
	Pricing.updateKey = function () { optionkey.val(xorkey(this.options)); }
	Pricing.updateLabel = function () {
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
	Pricing.updateKey();
	Pricing.updateLabel();
	
	var interfaces = new Object();
	interfaces['All'] = new Array(priceHeading, priceCell, salepriceHeading, salepriceCell, shippingHeading, shippingCell, inventoryHeading, inventoryCell, downloadHeading, downloadCell, uploadHeading, donationHeading, donationCell);
	if (pricesPayload) {		
		interfaces['Shipped'] = new Array(priceHeading, priceCell, salepriceHeading, salepriceCell, shippingHeading, shippingCell, inventoryHeading, inventoryCell);
		interfaces['Download'] = new Array(priceHeading, priceCell, salepriceHeading, salepriceCell, downloadHeading, downloadCell, uploadHeading);
	} else {
		interfaces['Shipped'] = new Array(priceHeading, priceCell, shippingHeading, shippingCell);
		interfaces['Download'] = new Array(priceHeading, priceCell);
	}
	interfaces['Donation'] = new Array(priceHeading, priceCell, donationHeading, donationCell);

	// Alter the interface depending on the type of price line
	type.change(function () {
		var ui = type.val();
		for (var e in interfaces['All']) $(interfaces['All'][e]).hide();
		priceLabel.html(PRICE_LABEL);
		if (interfaces[ui])
			for (var e in interfaces[ui]) $(interfaces[ui][e]).show();
		if (type.val() == "Donation") {
			priceLabel.html(AMOUNT_LABEL);
			tax.attr('checked','true').change();
		}
	});

	// Optional input's checkbox toggle behavior
	salepriceToggle.change(function () {
		salepriceStatus.toggle();
		salepriceField.toggle();
		if ($.browser.msie) $(this).blur();
	}).click(function () {
		if ($.browser.msie) $(this).change();
		if (this.checked) saleprice.focus().select();
	
	});

	shippingToggle.change(function () {
		shippingStatus.toggle();
		shippingFields.toggle();
		if ($.browser.msie) $(this).blur();
	}).click(function () {
		if ($.browser.msie) $(this).change();
		if (this.checked) weight.focus().select();
	});

	inventoryToggle.change(function () {
		inventoryStatus.toggle();
		inventoryField.toggle();
		if ($.browser.msie) $(this).blur();
	}).click(function () {
		if ($.browser.msie) $(this).change();
		if (this.checked) stock.focus().select();
	});

	price.change(function() { this.value = asMoney(this.value); }).change();
	saleprice.change(function() { this.value = asMoney(this.value); }).change();
	shippingfee.change(function() { this.value = asMoney(this.value); }).change();

	// Set the context for the db
	if (data && data.context) context.val(data.context);
	else context.val('product');

	// Set field values if we are rebuilding a priceline from 
	// database data
	if (data && data.label) {
		label.val(htmlentities(data.label)).change();
		type.val(data.type);
		myid.val(data.id);
	
		productid.val(data.product);
		sku.val(data.sku);
		price.val(asMoney(data.price));

		if (data.sale == "on") salepriceToggle.attr('checked','true').change();
		if (data.shipping == "on") shippingToggle.attr('checked','true').change();
		if (data.inventory == "on") inventoryToggle.attr('checked','true').change();
	
		if (data.donation) {
			if (data.donation['var'] == "on") donationVar.attr('checked',true);
			if (data.donation['min'] == "on") donationMin.attr('checked',true);
		}

		saleprice.val(asMoney(data.saleprice));
		shippingfee.val(asMoney(data.shipfee));
		weight.val(data.weight);
		stock.val(data.stock);
	
		if (data.download) {
			if (data.filedata.mimetype)	data.filedata.mimetype = data.filedata.mimetype.replace(/\//gi," ");
			downloadFile.attr('class','file '+data.filedata.mimetype).html(data.filename+'<br /><small>'+readableFileSize(data.filesize)+'</small>').click(function () {
				window.location.href = siteurl+"/wp-admin/admin.php?page=shopp/lookup&download="+data.download;
			});

		}
		if (data.tax == "off") tax.attr('checked','true');
	} else {
		if (type.val() == "Shipped") shippingToggle.attr('checked','true').change();
	}

	// Improve usability for quick data entry by
	// causing fields to automatically select all
	// contents when focused/activated
	quickSelects(row);

	// Initialize the interface by triggering the
	// priceline type change behavior 
	type.change();

	// Store the price line reference object
	if (options) pricingOptions[xorkey(options)] = Pricing;	
	$('#prices').val(pricingidx++);

	return row;
}
