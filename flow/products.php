<?php
/**
 * Products flow controller
 * Handles requests for the products admin screen
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 28 March, 2008
 * @package shopp
 **/

require("{$this->path}/model/Product.php");

function product_editor() {
	global $Shopp,$Product;
	$db =& DB::get();
	
	
	if ($_GET['edit'] != "new") {
		$Product = new Product($_GET['edit']);
		$Product->load_prices();
	} else $Product = new Product();
	
	if (!empty($_POST['save'])) save_product($Product);
	
	include("{$Shopp->path}/ui/products/editor.html");
	exit();
	
}

function products_list() {
	global $Shopp,$Products;
	$db =& DB::get();
	
	$Products = $db->query("SELECT * FROM shopp_product",AS_ARRAY);
	include("{$Shopp->path}/ui/products/products.html");
	exit();
}

function save_product($Product) {
	
	$Product->updates($_POST);
	$Product->save();

	if (!empty($_POST['price']) && is_array($_POST['price'])) {
		
		// Delete prices that were marked for removal
		if (!empty($_POST['deletePrices'])) {
			$deletes = array();
			if (strpos($_POST['deletePrices'],","))	$deletes = split(',',$_POST['deletePrices']);
			else $deletes = array($_POST['deletePrices']);
			
			foreach($deletes as $option) {
				$Price = new Price($option);
				$Price->delete();
			}
		}
		
		// Save prices that there are updates for
		foreach($_POST['price'] as $option) {
			if (empty($option['id'])) {
				$Price = new Price();
				$option['product'] = $Product->id;
			} else $Price = new Price($option['id']);
			
			$Price->updates($option);
			$Price->save();
		}
	}
	
	products_list();
}

?>