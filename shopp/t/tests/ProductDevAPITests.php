<?php
/**
* ProductDevAPITests - tests for the product dev api
*/
class ProductDevAPITests extends ShoppTestCase
{

	function test_add_product () {
		// $_data = array(
		// 	'name' => 'string', 		// string - the product name
		// 	'slug' => 'string', 		// string - the product slug (optional)
		// 	'publish' => 'publish',		// array - flag => bool, publishtime => array(month => int, day => int, year => int, hour => int, minute => int, meridian => AM/PM)
		// 	'categories' => 'terms',	// array of shopp category terms
		// 	'tags' => 'terms', 			// array of shopp tag terms
		// 	'terms' => 'terms', 		// array of taxonomy_type => type, terms => array of terms
		// 	'description' => 'string', 	// string - the product description text
		// 	'summary' => 'string', 		// string - the product summary text
		// 	'specs' => 'array', 		// array - spec name => spec value pairs
		// 	'single' => 'variant',		// array - single variant
		// 	'variants' => 'variants', 	// array - menu => options, count => # of variants, 0-# => variant
		// 	'addons' => 'variants', 	// array of addon arrays
		// 	'featured' => 'bool', 		// bool - product flag
		// 	'packaging' => 'bool', 		// bool - packaging flag
		// 	'processing' => 'processing'// array - flag => bool, min => days, max => days)
		// );

		// variants structure
		// $_variants = array(
		// 	'menu' => 'array',		// two dimensional array creates option permutations
		// 							// examples:
		// 							// $option['Color']['Blue']
		// 							// $option['Color']['Red]
		// 							// $option['Size']['Large']
		// 							// $option['Size']['Small']
		//
		// 	'count' => 'int',		// Number of variants
		// 	'#'	=> 'variant'		// number indexed elements are each a variant
		// );
		//

		$data = array(
			'name' => "St. John's Bay® Color Block Windbreaker",
			'publish' => array( 'flag' => false,
								'publishtime' => array('month' => 12,
								'day' => 25,
								'year' => 2011,
								'hour' => 0,
								'minute' => 0,
								'meridian' => 'AM')
			 					),
			'description' => "This water-repellent windbreaker offers lightweight protection on those gusty days.

			hood with drawstring
			zip front
			2 inner pockets
			on-seam pockets
			contrast side panels
			elastic cuffs
			side elastic on bottom
			contrast mesh lining
			polyester microfiber
			polyester mesh lining
			washable
			imported",
			'summary' => "This water-repellent windbreaker offers lightweight protection on those gusty days.",
			'featured' => true,
			'categories'=> array('terms' => array(5)),
			'tags'=>array('terms'=>array('action')),
			'specs'=>array('pockets'=>2, 'drawstring'=>'yes','washable'=>'yes'),
			'variants'=>array(
				'menu' => array(
					'Size' => array('medium','large','x-large','small','xx-large','large-tall','x-large tall','2x-large tall','2x-large'),
					'Color' => array('Black/Grey Colorbi', 'Navy Baby Solid','Red/Iron Colorbloc','Iron Solid','Dark Avocado Soil')
				)
			)
		);
		// // single/variant/addon structure
		// $_variant = array(
		// 	'option' => 'array',	// array option example: Color=>Blue, Size=>Small
		// 	'type' => 'enum',		// string - Shipped, Virtual, Download, Donation, Subscription, Disabled ( Price::types() )
		// 	'taxed' => 'bool',		// bool - flag variant as taxable
		// 	'price' => 'float',		// float - Price of variant
		// 	'sale' => 'sale',		// array - flag => bool, price => Sale price of variant
		// 	'shipping' => 'shipping', 	// array - flag => bool, fee, weight, height, width, length
		// 	'inventory'=> 'inventory',	// array - flag => bool, stock, sku
		// 	'donation'=> 'donation',	// (optional - needed only for Donation type) array of settings (variable, minumum)
		// 	'subscription'=>'subscription'	// (optional - needed only for Subscription type) array of subscription settings
		// );
		$data['variants'][] = array(
			'option' => array('Size'=>'medium', 'Color' => 'Navy Baby Solid'),
			'type' => 'Shipped',
			'price' => 40.00,
			'sale' => array('flag'=>true, 'price' => 19.99),
			'shipping' => array('flag'=>true, 'fee'=>1.50, 'weight'=>1.1, 'length'=>10.0, 'width'=>10.0, 'height'=>2.0),
			'inventory'=>array('flag'=>true, 'stock'=>10, 'sku'=>'WINDBREAKER1')
		);

		$Product = shopp_add_product($data);
		print_r($Product);

	}

}
?>