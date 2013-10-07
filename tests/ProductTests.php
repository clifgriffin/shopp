<?php
/**
 * ProductTests - tests for the product model (ShoppProduct)
 */
class ProductDevAPITests extends ShoppTestCase
{
	public static function setUpBeforeClass() {
		shopp_add_product(array(
			'name' => 'Gold Command Uniform',
			'publish' => array('flag' => true),
			'featured' => true,
			'summary' => 'Old style gold command division uniforms for your crew.',
			'description' => "Command uniforms ranged in color depending on the type of fabric used. While they were generally described as gold, some of the darker variants had a distinct greenish hue, while the dress uniforms, and a captain's alternate tunic were clearly green.",
			'tags' => array('terms' => array('Starfleet', 'Federation')),
			'specs' => array(
				'Department' => 'Command',
				'Color' => 'Gold'
			),
			'variants' => array(
				'menu' => array(
					'Size' => array('Small','Large')
				),
				0 => array(
					'option' => array('Size' => 'Small'),
					'type' => 'Shipped',
					'price' => 19.99,
					'sale' => array('flag'=>true, 'price' => 9.99),
					'shipping' => array('flag' => true, 'fee' => 0, 'weight' => 0.1, 'length' => 0.3, 'width' => 0.3, 'height' => 0.1),
					'inventory' => array(
						'flag' => true,
						'stock' => 5,
						'sku' => 'SFU-001-S'
					)
				),
				2 => array(
					'option' => array('Size' => 'Large'),
					'type' => 'Shipped',
					'price' => 32.95,
					'sale' => array('flag'=>true, 'price' => 24.95),
					'shipping' => array('flag' => true, 'fee' => 0, 'weight' => 0.1, 'length' => 0.3, 'width' => 0.3, 'height' => 0.1),
					'inventory' => array(
						'flag' => true,
						'stock' => 1,
						'sku' => 'SFU-001-L'
					)
				)
			)
		));
	}

	public static function tearDownBeforeClass() {
		$Product = shopp_product('gold-command-uniform', 'slug');
		shopp_rmv_product($Product->id);
	}

	public function test_product_duplication() {
		$Product = shopp_product('gold-command-uniform', 'slug');
		$Product->duplicate();
		#$Product = shopp_product('gold-command-uniform-2', 'slug');
		#$this->assertInstanceOf('ShoppProduct', $Product);
	}
}
