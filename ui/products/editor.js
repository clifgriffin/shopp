/**
 * editor.js
 * Product editor behaviors
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 28 March, 2008
 * @package shopp
 **/
$j=jQuery.noConflict();

var pricingOptions = new Array();
var init = function () {
	$j('#addProductOption').click(function() {
		addProductOption();
		quickSelects();
	});
	
	if (prices && prices.length > 0) for(i = 0; i < prices.length; i++) addProductOption(prices[i]);
	else addProductOption();

	quickSelects();
}
var addProductOption = function (p) {
	
	i = pricingOptions.length;
	var row = $j('<tr id="row['+i+']"></tr>').addClass('form-field').appendTo('#pricing');
	var heading = $j('<th class="pricing-label"><label for="label['+i+']">Option Name</label><br /></th>').appendTo(row);
	var label = $j('<input type="text" name="price['+i+'][label]" value="Option '+(i+1)+'" id="label['+i+']" size="16" title="Enter a name for this product option (used when showing product variations)" class="selectall" tabindex="'+(i+1)+'00" />').appendTo(heading);
	var myid = $j('<input type="hidden" name="price['+i+'][id]" id="id['+i+']" />').appendTo(heading);
	var productid = $j('<input type="hidden" name="price['+i+'][product]" id="product['+i+']" />').appendTo(heading);

	var dataCell = $j('<td/>').appendTo(row);
	var deleteButton = $j('<button id="deleteButton['+i+']" class="deleteButton" type="button" title="Delete product option"></button>').appendTo(dataCell).hide();
	var deleteIcon = $j('<img src="/wp-content/plugins/shopp/ui/icons/delete.png" width="16" height="16" />').appendTo(deleteButton);

	var pricingTable = $j('<table/>').addClass('pricing-table').appendTo(dataCell);

	var headingsRow = $j('<tr/>').appendTo(pricingTable);
	var skuHeading = $j('<th><label for="sku['+i+']" title="Stock Keeping Unit">SKU</label></th>').appendTo(headingsRow);
	var priceHeading = $j('<th><label for="price['+i+']">Price</label></th>').appendTo(headingsRow);
	var salepriceHeading = $j('<th><label for="sale['+i+']"> Sale Price</label></th>').appendTo(headingsRow);
	var shippingHeading = $j('<th><label for="shipping['+i+']"> Shipping</label></th>').appendTo(headingsRow);
	var inventoryHeading = $j('<th><label for="inventory['+i+']"> Inventory</label></th>').appendTo(headingsRow);
	var settingsHeading = $j('<th>Other Settings</th>').appendTo(headingsRow);

	var salepriceToggle = $j('<input type="checkbox" name="price['+i+'][sale]" id="sale['+i+']" tabindex="'+(i+1)+'03" />').prependTo(salepriceHeading);
	var shippingToggle = $j('<input type="checkbox" name="price['+i+'][shipping]" id="shipping['+i+']" tabindex="'+(i+1)+'05" />').prependTo(shippingHeading);
	var inventoryToggle = $j('<input type="checkbox" name="price['+i+'][inventory]" id="inventory['+i+']" tabindex="'+(i+1)+'08" />').prependTo(inventoryHeading);
	
	var inputsRow = $j('<tr/>').appendTo(pricingTable);
	var skuCell = $j('<td/>').appendTo(inputsRow);
	var sku = $j('<input type="text" name="price['+i+'][sku]" id="sku['+i+']" size="10" title="Enter a unique tracking number for this product option." class="selectall" tabindex="'+(i+1)+'01" />').appendTo(skuCell);

	var priceCell = $j('<td/>').appendTo(inputsRow);
	var price = $j('<input type="text" name="price['+i+'][price]" id="price['+i+']" value="0" size="10" class="selectall right" tabindex="'+(i+1)+'02" />').appendTo(priceCell);

	var salepriceCell = $j('<td/>').appendTo(inputsRow);
	var salepriceStatus = $j('<span id="test['+i+']">Not on Sale</span>').addClass('status').appendTo(salepriceCell);
	var salepriceField = $j('<span/>').addClass('fields').appendTo(salepriceCell).hide();
	var saleprice = $j('<input type="text" name="price['+i+'][saleprice]" id="saleprice['+i+']" size="10" class="selectall right" tabindex="'+(i+1)+'04" />').appendTo(salepriceField);
	
	var shippingCell = $j('<td/>').appendTo(inputsRow);
	var shippingStatus = $j('<span>Shipping Disabled</span>').addClass('status').appendTo(shippingCell);
	var shippingFields = $j('<span/>').addClass('fields').appendTo(shippingCell).hide();
	var shippingDom = $j('<input type="text" name="price['+i+'][domship]" id="domship['+i+']" size="8" class="selectall right" tabindex="'+(i+1)+'06" />').appendTo(shippingFields);
	var shippingDomLabel = $j('<label for="domship['+i+']" title="Domestic"> Dom</label><br />').appendTo(shippingFields);
	var shippingIntl = $j('<input type="text" name="price['+i+'][intlship]" id="intlship['+i+']" size="8" class="selectall right" tabindex="'+(i+1)+'07" />').appendTo(shippingFields);
	var shippingIntlLabel = $j('<label for="intlship['+i+']" title="International"> Int\'l</label>').appendTo(shippingFields);

	var inventoryCell = $j('<td/>').appendTo(inputsRow);
	var inventoryStatus = $j('<span>Not Tracked</span>').addClass('status').appendTo(inventoryCell);
	var inventoryField = $j('<span/>').addClass('fields').appendTo(inventoryCell).hide();
	var stock = $j('<input type="text" name="price['+i+'][stock]" id="stock['+i+']" size="8" class="selectall" tabindex="'+(i+1)+'09" />').appendTo(inventoryField);
	var inventoryBr = $j('<br/>').appendTo(inventoryField);
	var inventoryLabel =$j('<label for="stock['+i+']">Qty in stock</label>').appendTo(inventoryField);
	
	var settingsCell = $j('<td/>').appendTo(inputsRow);
	var tax = $j('<input type="checkbox" name="price['+i+'][tax]" id="tax['+i+']" tabindex="'+(i+1)+'10" />').appendTo(settingsCell);
	var taxLabel = $j('<label for="tax['+i+']"> Not Taxable</label><br />').appendTo(settingsCell);
	var donation = $j('<input type="checkbox" name="price['+i+'][donation]" id="donation['+i+']" tabindex="'+(i+1)+'11" />').appendTo(settingsCell);
	var donationLabel = $j('<label for="donation['+i+']"> Donation</label><br />').appendTo(settingsCell);
	var download = $j('<input type="checkbox" name="price['+i+'][download]" id="download['+i+']" tabindex="'+(i+1)+'12" />').appendTo(settingsCell);
	var downloadLabel = $j('<label for="download['+i+']"> Download</label><br />').appendTo(settingsCell);

	var rowBG = row.css("background-color");
	var deletingBG = "#ffebe8";
	
	
	row.hover(function () {
			deleteButton.show();
		}, function () {
			deleteButton.hide();
	});
	
	deleteButton.hover (function () {
			row.animate({backgroundColor:deletingBG},250);
		},function() {
			row.animate({backgroundColor:rowBG},250);		
	});
	
	deleteButton.click(function () {
		if (pricingOptions.length > 1) {
			if (confirm("Are you sure you want to delete this product option?")) {
				row.remove();
				pricingOptions.splice(i,1);
				$j('#options').val(pricingOptions.length);
				$j('#deletePrices').val(($j('#deletePrices').val() == "")?myid.val():$j('#deletePrices').val()+','+myid.val());
			}
		}
	});
	
	salepriceToggle.change(function (e) {
		salepriceStatus.toggle();
		salepriceField.toggle();
	});

	shippingToggle.change(function () {
		shippingStatus.toggle();
		shippingFields.toggle();
	});
	
	inventoryToggle.change(function () {
		inventoryStatus.toggle();
		inventoryField.toggle();
	});
	
	price.change(function() { this.value = asMoney(this.value); }).change();
	saleprice.change(function() { this.value = asMoney(this.value); }).change();
	shippingDom.change(function() { this.value = asMoney(this.value); }).change();
	shippingIntl.change(function() { this.value = asMoney(this.value); }).change();
	
	if (p) {
		label.each(function() { this.value = p.label; });
		myid.each(function() { this.value = p.id; });
		productid.each(function() { this.value = p.product; });
		sku.each(function() { this.value = p.sku; });
		price.each(function() { this.value = asMoney(p.price); });

		if (p.sale == "on") salepriceToggle.each(function() { this.checked = true; }).change();
		if (p.shipping == "on") shippingToggle.each(function() { this.checked = true; }).change();
		if (p.inventory == "on") inventoryToggle.each(function() { this.checked = true; }).change();

		saleprice.val(asMoney(p.saleprice));
		shippingDom.val(asMoney(p.domship));
		shippingIntl.val(asMoney(p.intlship));
		stock.val(p.stock);

		if (p.tax == "off") tax.each(function() { this.checked = true; });
		if (p.donation == "on") donation.each(function() { this.checked = true; });
		if (p.download == "on") download.each(function() { this.checked = true; });
	}
	
	pricingOptions.push(row);
	$j('#options').val(pricingOptions.length);
}
