<?php
/**
 * Product.php
 *
 * Database management of catalog products
 *
 * @author Jonathan Davis
 * @version 1.1
 * @copyright Ingenesis Limited, July, 2010
 * @package shopp
 * @since 1.0
 * @subpackage products
 **/

require_once("Asset.php");
require_once("Price.php");
require_once("Promotion.php");

class Product extends WPPostTypeObject {
	static $table = 'posts';
	static $_taxonomies = array(
		'shopp_category' => 'categories',
		'shopp_tag' => 'tags'
	);

	var $_post_type = 'shopp_product';

	protected $_map = array(
		'id' => 'ID',
		'name' => 'post_title',
		'slug' => 'post_name',
		'summary' => 'post_excerpt',
		'description' => 'post_content',
		'status' => 'post_status',
		'publish' => 'post_date',
		'modified' => 'post_modified'
	);

	var $prices = array();
	var $pricekey = array();
	var $priceid = array();
	var $categories = array();
	var $tags = array();
	var $images = array();
	var $specs = array();
	var $meta = array();
	var $max = array();
	var $min = array();
	var $summary = false;
	var $onsale = false;
	var $freeshipping = false;
	var $outofstock = false;
	var $stock = 0;
	var $options = 0;

	/**
	 * Product constructor
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @return void
	 **/
	function __construct ($id=false,$key=false) {
		$this->init(self::$table,'ID');
		$this->load($id,$key);
	}

	/**
	 * Loads relational data into the Product object
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @todo Add restat option handling to pass on to the price loader
	 *
	 * @return void
	 **/
	function load_data ($options=array('prices','specs','images','categories','tags','meta','summary'),&$products=array()) {
		$loaders = array(
		//  'name'     'callback_method'
			'prices' 	=> 'load_prices',
			'images' 	=> 'load_meta',
			'specs' 	=> 'load_meta',
			'meta' 		=> 'load_meta',
			'categories' => 'load_taxonomies',
			'tags' 		=> 'load_taxonomies',
			'summary' 	=> 'load_summary'

		);

		$options = array_map('strtolower',$options);
		$load = array_flip(array_intersect($options,array_keys($loaders)));
		$loadcalls = array_unique(array_values(array_intersect_key($loaders,$load)));

		if (!empty($products) ) {
			$ids = join(',',array_keys($products));
			$this->products = &$products;
		} else $ids = $this->id;
		if ( empty($ids) ) return;

		foreach ($loadcalls as $loadmethod) {
			if (method_exists($this,$loadmethod))
				call_user_func_array(array($this,$loadmethod),array($ids));
		}

	}

	function load_summary ($ids) {
		if ( empty($ids) ) return;
		$Object = new ProductSummary();

		DB::query("SELECT * FROM $Object->_table WHERE product IN ($ids)",'array',array($this,'summary'));
	}

	/**
	 * Loads price records and populates the product
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @return void
	 **/
	function load_prices ($ids) {
		if ( empty($ids) ) return;
		$Object = new Price();

		DB::query("SELECT * FROM $Object->_table WHERE product IN ($ids) ORDER BY product",'array',array($this,'pricing'));

		if ( $this->_last_product != false
				&& $this->_last_product != $price->product
				&& isset($this->products[$this->_last_product]) )
			$this->products[$this->_last_product]->sumup();

	}

	function load_meta ($ids) {
		if ( empty($ids) ) return;
		$Object = new ObjectMeta();

		DB::query("SELECT * FROM $Object->_table WHERE context='product' AND parent IN ($ids) ORDER BY sortorder",'array',array($this,'metaloader'),'parent','metatype','name',false);
	}

	/**
	 * Loads assigned taxonomies
	 *
	 * Loads both shopp_category and shopp_tag built-in custom taxonomies as well
	 * as other user-defined taxonomies assigned to the Shopp product custom post type
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @return void
	 **/
	function load_taxonomies ($ids) {
		if ( empty($ids) ) return;

		if (isset($this->products) && !empty($this->products)) {
			$products = &$this->products;
			$ids = array_keys($this->products);
		} else $ids = array($this->id);

		$taxonomies = get_object_taxonomies( $this->_post_type );
		$terms = wp_get_object_terms($ids,$taxonomies,array('fields' => 'all_with_object_id'));

		foreach ($terms as $term) { // Map wp taxonomy data to object meta
			if (!isset($term->term_id) || empty($term->term_id)) continue; 		// Skip invalid entries
			if (!isset($term->object_id) || empty($term->object_id)) continue;	// Skip invalid entries
			if (!isset(Product::$_taxonomies[$term->taxonomy])) continue;
			$property = Product::$_taxonomies[$term->taxonomy];

			if (isset($products[$term->object_id]))
				$target = $products[$term->object_id];
			else $target = $this;

			if (is_array($target->$property)) // Map term to object
				$target->{$property}[ $term->term_id ] = $term;

		} // END foreach ($terms)
	}

	function metaloader (&$records,&$record,$id='id',$property=false,$collate=true,$merge=false) {

		if (isset($this->products) && !empty($this->products)) $products = &$this->products;

		$metamap = array(
			'image' => 'images',
			'setting' => 'settings',
			'spec' => 'specs'
		);

		$metaclass = array(
			'image' => 'ProductImage',
			'spec' => 'Spec',
			'meta' => 'MetaObject'
		);

		if ($property == 'metatype')
			$property = isset($metamap[$record->type])?$metamap[$record->type]:'meta';

		if (isset($metaclass[$record->type])) {
			$ObjectClass = $metaclass[$record->type];
			$Object = new $ObjectClass();
			$Object->populate($record);
			if (method_exists($Object,'expopulate'))
				$Object->expopulate();
			$record = $Object;
		}
		if ('images' == $property) $collate = 'id';

		parent::metaloader($records,$record,$products,$id,$property,$collate,$merge);
	}

	/**
	 * Aggregates product pricing information
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param array $options shopp() tag option list
	 * @return void
	 **/
	function pricing (&$records,&$price,$restat=false) {

		if ( isset($this->products) && !empty($this->products) ) {
			if ( !isset($this->products[$price->product]) ) return false;

			if ( $this->_last_product != false
					&& $this->_last_product != $price->product
					&& isset($this->products[$this->_last_product]) )
				$this->products[$this->_last_product]->sumup();

			$target = &$this->products[$price->product];

			$this->_last_product = $price->product;
		} else $target = &$this;

		$target->prices[] = $price;

		// Variation range index/properties
		$varranges = array('price' => 'price','saleprice'=>'promoprice');

		$variations = ($target->variations == 'on');
		$freeshipping = true;

		// do_action('shopp_init_product_pricing');
		// foreach ($this->prices as $i => &$price) {
			$price->price = (float)$price->price;
			$price->saleprice = (float)$price->saleprice;
			$price->shipfee = (float)$price->shipfee;
			$price->promoprice = (float)$price->promoprice;

			// Build secondary lookup table using the price id as the key
			$target->priceid[$price->id] = $price;

			if (defined('WP_ADMIN') && !isset($options['taxes'])) $options['taxes'] = true;
			if (defined('WP_ADMIN') && value_is_true($options['taxes']) && $price->tax == "on") {
				$Settings =& ShoppSettings();
				$base = $Settings->get('base_operations');
				if ($base['vat']) {
					$Taxes = new CartTax();
					$taxrate = $Taxes->rate($target);
					$price->price += $price->price*$taxrate;
					$price->saleprice += $price->saleprice*$taxrate;
				}
			}

			if ($price->type == "N/A" || $price->context == "addon" || ($i > 0 && !$variations)) return;

			// Build third lookup table using the combined optionkey
			$target->pricekey[$price->optionkey] = $price;

			// Boolean flag for custom product sales
			$target->sale = 'off';
			if ($price->sale == 'on') {
				$target->sale = 'on'; $price->onsale = true;
			}

			if ($price->inventory == 'on') {
				$target->stock += $price->stock;
				$target->inventory = 'on';
				$price->stocked = true;
			}

			if ($price->freeshipping == '0' || $price->shipping == 'on')
				$freeshipping = false;

			// Calculate catalog discounts if not already calculated
			if (empty($price->promoprice)) {
				$Price = new Price();
				$Price->updates($price);
				$Price->discounts();
				$price->promoprice = $Price->promoprice;
			}

			if ($price->promoprice < $price->price) $target->onsale = $price->onsale = true;

			// Grab price and saleprice ranges (minimum - maximum)
			if (!$price->price) $price->price = 0;
			if ($price->stocked) $varranges['stock'] = 'stock';

			// do_action_ref_array('shopp_product_stats',array(&$price));

			foreach ($varranges as $name => $prop) {
				if (!isset($price->$prop)) continue;

				if (!isset($target->min[$name])) $target->min[$name] = $price->$prop;
				else $target->min[$name] = min($target->min[$name],$price->$prop);
				if ($target->min[$name] == $price->$prop) $target->min[$name.'_tax'] = ($price->tax == "on");


				if (!isset($target->max[$name])) $target->max[$name] = $price->$prop;
				else $target->max[$name] = max($target->max[$name],$price->$prop);
				if ($target->max[$name] == $price->$prop) $target->max[$name.'_tax'] = ($price->tax == "on");
			}

			// Determine savings ranges
			if ($price->onsale && isset($target->min['price']) && isset($target->min['saleprice'])) {

				if (!isset($target->min['saved'])) {
					$target->min['saved'] = $price->price;
					$target->min['savings'] = 100;
					$target->max['saved'] = $target->max['savings'] = 0;
				}

				$target->min['saved'] = min($target->min['saved'],($price->price-$price->promoprice));
				$target->max['saved'] = max($target->max['saved'],($price->price-$price->promoprice));

				// Find lowest savings percentage
				if ($target->min['saved'] == ($price->price-$price->promoprice))
					$target->min['savings'] = (1 - $price->promoprice/($price->price == 0?1:$price->price))*100;
				if ($target->max['saved'] == ($price->price-$price->promoprice))
					$target->max['savings'] = (1 - $price->promoprice/($price->price == 0?1:$price->price))*100;
			}

			// Determine weight ranges
			if($price->weight && $price->weight > 0) {
				if(!isset($target->min['weight'])) $target->min['weight'] = $target->max['weight'] = $price->weight;
				$target->min['weight'] = min($target->min['weight'],$price->weight);
				$target->max['weight'] = max($target->max['weight'],$price->weight);
			}

		// } // end foreach($price)

		// Update stats
		$target->maxprice = $target->max['price'];
		$target->minprice = $target->min['price'];
		if ($target->sale == 'on') $target->minprice = $target->min['saleprice'];

		// $this->save_stats();
		// do_action('shopp_product_pricing_done');

		if ($target->inventory && $target->stock <= 0) $target->outofstock = true;
		if ($freeshipping) $target->freeshipping = true;
	}

	/**
	 * Detect if the product is currently published
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return boolean
	 **/
	function published () {
		return ($this->status == "publish" && time() >= $this->publish);
	}

	/**
	 * Merges specs with identical names into an array of values
	 *
	 * @todo Determine if merge_specs is necessary with new collation capabilities of the DB query loader
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	function merge_specs () {
		$merged = array();
		foreach ($this->specs as $key => $spec) {
			if (!isset($merged[$spec->name])) $merged[$spec->name] = $spec;
			else {
				if (!is_array($merged[$spec->name]->value))
					$merged[$spec->name]->value = array($merged[$spec->name]->value);
				$merged[$spec->name]->value[] = $spec->value;
			}
		}
		$this->specs = $merged;
	}

	/**
	 * Returns the number of this product sold
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return int
	 **/
	function sold () {
		$db =& DB::get();
		$purchased = DatabaseObject::tablename(Purchased::$table);
		$r = $db->query("SELECT count(*) AS sold FROM $purchased WHERE product=$this->id LIMIT 1");
		return $r->sold;
	}

	/**
	 * Populates the product with summary data
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @return void
	 **/
	function summary (&$records,&$data) {

		$Summary = new ProductSummary();

		$properties = array_keys($Summary->_datatypes);
		$ignore = array('id','product');
		if (isset($data->sumid)) $this->sumid = $data->sumid;
		else $this->sumid = $data->id;
		foreach ($properties as $property) {
			if (in_array($property,$ignore)) continue;
			$this->{$property} = isset($data->{$property})?($data->{$property}):false;
		}

	}

	/**
	 * Calculates aggregate product stats
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @param object $Price The price record to calculate against
	 * @return void
	 **/
	function sumprice ($Price) {
		if ($Price->type == 'N/A' || $Price->context == 'addon' || (float)$Price->promoprice == 0) return;

		if ($this->maxprice === false) $this->maxprice = (float)$Price->promoprice;
		else $this->maxprice = max($this->maxprice,$Price->promoprice);

		if ($this->minprice === false) $this->minprice = (float)$Price->promoprice;
		else $this->minprice = min($this->minprice,$Price->promoprice);

		if ('on' == $Price->sale) $this->sale = $Price->sale;

		if ('on' == $Price->inventory) {
			$this->inventory = $Price->inventory;
			if (!$this->stock) $this->stock = $Price->stock;
			else $this->stock += $Price->stock;
		} else if (!$this->inventory) $this->inventory = 'off';

		if (!isset($this->_soldcount)) { // Only recalculate sold count once
			$this->sold = $this->_soldcount = $this->sold();
			$this->_soldcount = true;
		}

	}

	function resum () {
		$this->sale = $this->inventory = 'off';
		$this->stock = $this->maxprice = $this->minprice = $this->sold = 0;
	}

	/**
	 * Saves generated stats to the product record
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @param array $stats The stat properties to update
	 * @return void
	 **/
	function sumup () {
		if (empty($this->id)) return;
		$Summary = new ProductSummary();
		$Summary->copydata($this);

		if (!empty($this->sumid)) $Summary->id = $this->sumid;
		$Summary->product = $this->id;
		$Summary->save();
	}

	/**
	 * optionkey
	 * There is no Zul only XOR! */
	function optionkey ($ids=array(),$deprecated=false) {
		if ($deprecated) $factor = 101;
		else $factor = 7001;
		if (empty($ids)) return 0;
		$key = 0;
		foreach ($ids as $set => $id)
			$key = $key ^ ($id*$factor);
		return $key;
	}

	/**
	 * save_imageorder()
	 * Updates the sortorder of image assets (source, featured and thumbnails)
	 * based on the provided array of image ids */
	function save_imageorder ($ordering) {
		$table = DatabaseObject::tablename(ProductImage::$table);
		foreach ($ordering as $i => $id)
			DB::query("UPDATE LOW_PRIORITY $table SET sortorder='$i' WHERE (id='$id' AND parent='$this->id' AND context='product' AND type='image')");
		return true;
	}

	/**
	 * link_images()
	 * Updates the product id of the images to link to the product
	 * when the product being saved is new (has no previous id assigned) */
	function link_images ($images) {
		if (empty($images)) return false;
		$table = DatabaseObject::tablename(ProductImage::$table);
		$set = "id=".join(' OR id=',$images);
		$query = "UPDATE $table SET parent='$this->id',context='product' WHERE ".$set;
		DB::query($query);
		return true;
	}

	/**
	 * update_images()
	 * Updates the image details for all cached images */
	function update_images ($images) {
		if (!is_array($images)) return false;

		foreach ($images as $img) {
			$Image = new ProductImage($img['id']);
			$Image->title = $img['title'];
			$Image->alt = $img['alt'];

			if (!empty($img['cropping'])) {
				require_once(SHOPP_PATH."/core/model/Image.php");

				foreach ($img['cropping'] as $id => $cropping) {
					if (empty($cropping)) continue;
					$Cropped = new ProductImage($id);

					list($Cropped->settings['dx'],
						$Cropped->settings['dy'],
						$Cropped->settings['cropscale']) = explode(',', $cropping);
					extract($Cropped->settings);

					$Resized = new ImageProcessor($Image->retrieve(),$Image->width,$Image->height);
					$scaled = $Image->scaled($width,$height,$scale);
					$scale = $Cropped->_scaling[$scale];
					$quality = ($quality === false)?$Cropped->_quality:$quality;

					$Resized->scale($scaled['width'],$scaled['height'],$scale,$alpha,$fill,(int)$dx,(int)$dy,(float)$cropscale);

					// Post sharpen
					if ($sharpen !== false) $Resized->UnsharpMask($sharpen);
					$Cropped->data = $Resized->imagefile($quality);
					if (empty($Cropped->data)) return false;

					$Cropped->size = strlen($Cropped->data);
					if ($Cropped->store( $Cropped->data ) === false)
						return false;

					$Cropped->save();

				}
			}

			$Image->save();
		}

		return true;
	}


	/**
	 * delete_images()
	 * Delete provided array of image ids, removing the source image and
	 * all related images (small and thumbnails) */
	function delete_images ($images) {
		$db = &DB::get();
		$imagetable = DatabaseObject::tablename(ProductImage::$table);
		$imagesets = "";
		foreach ($images as $image) {
			$imagesets .= (!empty($imagesets)?" OR ":"");
			$imagesets .= "((context='product' AND parent='$this->id' AND id='$image') OR (context='image' AND parent='$image'))";
		}
		if (!empty($imagesets))
			$db->query("DELETE FROM $imagetable WHERE type='image' AND ($imagesets)");
		return true;
	}

	/**
	 * Deletes the record associated with this object */
	function delete () {
		$db = DB::get();
		$id = $this->{$this->_key};
		if (empty($id)) return false;

		// Delete from categories
		$table = DatabaseObject::tablename(Catalog::$table);
		$db->query("DELETE LOW_PRIORITY FROM $table WHERE product='$id'");

		// Delete prices
		$table = DatabaseObject::tablename(Price::$table);
		$db->query("DELETE LOW_PRIORITY FROM $table WHERE product='$id'");

		// Delete images/files
		$table = DatabaseObject::tablename(ProductImage::$table);

		// Delete images
		$images = array();
		$src = $db->query("SELECT id FROM $table WHERE parent='$id' AND context='product' AND type='image'",AS_ARRAY);
		foreach ($src as $img) $images[] = $img->id;
		$this->delete_images($images);

		// Delete product meta (specs, images, downloads)
		$table = DatabaseObject::tablename(MetaObject::$table);
		$db->query("DELETE LOW_PRIORITY FROM $table WHERE parent='$id' AND context='product'");

		// Delete record
		$db->query("DELETE FROM $this->_table WHERE $this->_key='$id'");

	}

	function duplicate () {
		$db =& DB::get();

		$this->load_data(array('prices','specs','categories','tags','images','taxes'=>'false'));
		$this->id = '';
		$this->name = $this->name.' '.__('copy','Shopp');
		$this->slug = sanitize_title_with_dashes($this->name);

		// Check for an existing product slug
		$existing = $db->query("SELECT slug FROM $this->_table WHERE slug='$this->slug' LIMIT 1");
		if ($existing) {
			$suffix = 2;
			while($existing) {
				$altslug = substr($this->slug, 0, 200-(strlen($suffix)+1)). "-$suffix";
				$existing = $db->query("SELECT slug FROM $this->_table WHERE slug='$altslug' LIMIT 1");
				$suffix++;
			}
			$this->slug = $altslug;
		}
		$this->created = '';
		$this->modified = '';

		$this->save();

		// Copy prices
		foreach ($this->prices as $price) {
			$Price = new Price();
			$Price->updates($price,array('id','product','created','modified'));
			$Price->product = $this->id;
			$Price->save();
		}

		// Copy sepcs
		foreach ($this->specs as $spec) {
			$Spec = new Spec();
			$Spec->updates($spec,array('id','parent','created','modified'));
			$Spec->parent = $this->id;
			$Spec->save();
		}

		// Copy categories
		$categories = array();
		foreach ($this->categories as $category) $categories[] = $category->id;
		$this->categories = array();
		$this->save_categories($categories);

		// Copy tags
		$taglist = array();
		foreach ($this->tags as $tag) $taglist[] = $tag->name;
		$this->tags = array();
		$this->save_tags($taglist);

		// Copy product images
		foreach ($this->images as $ProductImage) {
			$Image = new ProductImage();
			$Image->updates($ProductImage,array('id','parent','created','modified'));
			$Image->parent = $this->id;
			$Image->save();
		}

	}

	function taxrule ($rule) {
		switch ($rule['p']) {
			case "product-name": return ($rule['v'] == $this->name); break;
			case "product-tags":
				if (!isset($this->tagskey)) return false;
				else return (in_array($rule['v'],array_keys($this->tagskey)));
				break;
			case "product-category":
				if (!isset($this->categorieskey)) return false;
				else return (in_array($rule['v'],array_keys($this->categorieskey))); break;
		}
		return false;
	}

	function tag ($property,$options=array()) {
		global $Shopp;

		$select_attrs = array('title','required','class','disabled','required','size','tabindex','accesskey');
		$submit_attrs = array('title','class','value','disabled','tabindex','accesskey');

		switch ($property) {
			case "link":
			case "url":
				return get_post_permalink($this->id);
				break;
			case "found":
				if (empty($this->id)) return false;
				$load = array('prices','images','specs','tags','categories');
				if (isset($options['load'])) $load = explode(",",$options['load']);
				$this->load_data($load);
				return true;
				break;
			case "relevance": return (string)$this->score; break;
			case "id": return $this->id; break;
			case "name": return apply_filters('shopp_product_name',$this->name); break;
			case "slug": return $this->slug; break;
			case "summary": return apply_filters('shopp_product_summary',$this->summary); break;
			case "description":
				return apply_filters('shopp_product_description',$this->description);
			case "isfeatured":
			case "is-featured":
				return ($this->featured == "on"); break;
			case "price":
			case "saleprice":
				if (empty($this->prices)) $this->load_data(array('prices'));
				$defaults = array(
					'taxes' => null,
					'starting' => ''
				);
				$options = array_merge($defaults,$options);
				extract($options);

				if (!is_null($taxes)) $taxes = value_is_true($taxes);

				$min = $this->min[$property];
				$mintax = $this->min[$property.'_tax'];

				$max = $this->max[$property];
				$maxtax = $this->max[$property.'_tax'];

				$taxrate = shopp_taxrate($taxes,$this->prices[0]->tax,$this);

				if ('saleprice' == $property) $pricetag = $this->prices[0]->promoprice;
				else $pricetag = $this->prices[0]->price;

				if (count($this->options) > 0) {
					$taxrate = shopp_taxrate($taxes,true,$this);
					$mintax = $mintax?$min*$taxrate:0;
					$maxtax = $maxtax?$max*$taxrate:0;

					if ($min == $max) return money($min+$mintax);
					else {
						if (!empty($starting)) return "$starting ".money($min+$mintax);
						return money($min+$mintax)." &mdash; ".money($max+$maxtax);
					}
				} else return money($pricetag+($pricetag*$taxrate));

				break;
			case "taxrate":
				return shopp_taxrate(null,true,$this);
				break;
			case "weight":
				if(empty($this->prices)) $this->load_data(array('prices'));
				$defaults = array(
					'unit' => $Shopp->Settings->get('weight_unit'),
					'min' => $this->min['weight'],
					'max' => $this->max['weight'],
					'units' => true,
					'convert' => false
				);
				$options = array_merge($defaults,$options);
				extract($options);

				if(!isset($this->min['weight'])) return false;

				if ($convert !== false) {
					$min = convert_unit($min,$convert);
					$max = convert_unit($max,$convert);
					if (is_null($units)) $units = true;
					$unit = $convert;
				}

				$range = false;
				if ($min != $max) {
					$range = array($min,$max);
					sort($range);
				}

				$string = ($min == $max)?round($min,3):round($range[0],3)." - ".round($range[1],3);
				$string .= value_is_true($units) ? " $unit" : "";
				return $string;
				break;
			case "onsale":
				if (empty($this->prices)) $this->load_data(array('prices'));
				if (empty($this->prices)) return false;
				return $this->onsale;
				break;
			case "has-savings": return ($this->onsale && $this->min['saved'] > 0); break;
			case "savings":
				if (empty($this->prices)) $this->load_data(array('prices'));
				if (!isset($options['taxes'])) $options['taxes'] = null;

				$taxrate = shopp_taxrate($options['taxes']);
				$range = false;

				if (!isset($options['show'])) $options['show'] = '';
				if ($options['show'] == "%" || $options['show'] == "percent") {
					if ($this->options > 1) {
						if (round($this->min['savings']) != round($this->max['savings'])) {
							$range = array($this->min['savings'],$this->max['savings']);
							sort($range);
						}
						if (!$range) return percentage($this->min['savings'],array('precision' => 0)); // No price range
						else return percentage($range[0],array('precision' => 0))." &mdash; ".percentage($range[1],array('precision' => 0));
					} else return percentage($this->max['savings'],array('precision' => 0));
				} else {
					if ($this->options > 1) {
						if (round($this->min['saved']) != round($this->max['saved'])) {
							$range = array($this->min['saved'],$this->max['saved']);
							sort($range);
						}
						if (!$range) return money($this->min['saved']+($this->min['saved']*$taxrate)); // No price range
						else return money($range[0]+($range[0]*$taxrate))." &mdash; ".money($range[1]+($range[1]*$taxrate));
					} else return money($this->max['saved']+($this->max['saved']*$taxrate));
				}
				break;
			case "freeshipping":
				if (empty($this->prices)) $this->load_data(array('prices'));
				return $this->freeshipping;
			case "hasimages":
			case "has-images":
				if (empty($this->images)) $this->load_data(array('images'));
				return (!empty($this->images));
				break;
			case "images":
				if (!$this->images) return false;
				if (!isset($this->_images_loop)) {
					reset($this->images);
					$this->_images_loop = true;
				} else next($this->images);

				if (current($this->images) !== false) return true;
				else {
					unset($this->_images_loop);
					return false;
				}
				break;
			case "coverimage":
				// Force select the first loaded image
				unset($options['id']);
				$options['index'] = 0;
			case "thumbnail": // deprecated
			case "image":
				if (empty($this->images)) $this->load_data(array('images'));
				if (!(count($this->images) > 0)) return "";

				// Compatibility defaults
				$_size = 96;
				$_width = $Shopp->Settings->get('gallery_thumbnail_width');
				$_height = $Shopp->Settings->get('gallery_thumbnail_height');
				if (!$_width) $_width = $_size;
				if (!$_height) $_height = $_size;

				$defaults = array(
					'img' => false,
					'id' => false,
					'index' => false,
					'class' => '',
					'width' => false,
					'height' => false,
					'size' => false,
					'fit' => false,
					'sharpen' => false,
					'quality' => false,
					'bg' => false,
					'alt' => '',
					'title' => '',
					'zoom' => '',
					'zoomfx' => 'shopp-zoom',
					'property' => false
				);
				$options = array_merge($defaults,$options);
				extract($options);

				// Select image by database id
				if ($id !== false) {
					for ($i = 0; $i < count($this->images); $i++) {
						if ($img->id == $id) {
							$img = $this->images[$i]; break;
						}
					}
					if (!$img) return "";
				}

				// Select image by index position in the list
				if ($index !== false && isset($this->images[$index]))
					$img = $this->images[$index];

				// Use the current image pointer by default
				if (!$img) $img = current($this->images);

				if ($size !== false) $width = $height = $size;
				if (!$width) $width = $_width;
				if (!$height) $height = $_height;

				$scale = $fit?array_search($fit,$img->_scaling):false;
				$sharpen = $sharpen?min($sharpen,$img->_sharpen):false;
				$quality = $quality?min($quality,$img->_quality):false;
				$fill = $bg?hexdec(ltrim($bg,'#')):false;

				list($width_a,$height_a) = array_values($img->scaled($width,$height,$scale));
				if ($size == "original") {
					$width_a = $img->width;
					$height_a = $img->height;
				}
				if ($width_a === false) $width_a = $width;
				if ($height_a === false) $height_a = $height;

				$alt = esc_attr(empty($alt)?(empty($img->alt)?$img->name:$img->alt):$alt);
				$title = empty($title)?$img->title:$title;
				$titleattr = empty($title)?'':' title="'.esc_attr($title).'"';
				$classes = empty($class)?'':' class="'.esc_attr($class).'"';

				$src = shoppurl($img->id,'images');
				if (SHOPP_PERMALINKS) $src = trailingslashit($src).$img->filename;

				if ($size != "original")
					$src = add_query_string($img->resizing($width,$height,$scale,$sharpen,$quality,$fill),$src);

				switch (strtolower($property)) {
					case "id": return $img->id; break;
					case "url":
					case "src": return $src; break;
					case "title": return $title; break;
					case "alt": return $alt; break;
					case "width": return $width_a; break;
					case "height": return $height_a; break;
					case "class": return $class; break;
				}

				$imgtag = '<img src="'.$src.'"'.$titleattr.' alt="'.$alt.'" width="'.$width_a.'" height="'.$height_a.'" '.$classes.' />';

				if (value_is_true($zoom))
					return '<a href="'.shoppurl($img->id,'images').'/'.$img->filename.'" class="'.$zoomfx.'" rel="product-'.$this->id.'">'.$imgtag.'</a>';

				return $imgtag;
				break;
			case "gallery":
				if (empty($this->images)) $this->load_data(array('images'));
				if (empty($this->images)) return false;
				$styles = '';
				$_size = 240;
				$_width = $Shopp->Settings->get('gallery_small_width');
				$_height = $Shopp->Settings->get('gallery_small_height');

				if (!$_width) $_width = $_size;
				if (!$_height) $_height = $_size;

				$defaults = array(

					// Layout settings
					'margins' => 20,
					'rowthumbs' => false,
					// 'thumbpos' => 'after',

					// Preview image settings
					'p.size' => false,
					'p.width' => false,
					'p.height' => false,
					'p.fit' => false,
					'p.sharpen' => false,
					'p.quality' => false,
					'p.bg' => false,
					'p.link' => true,
					'rel' => '',

					// Thumbnail image settings
					'thumbsize' => false,
					'thumbwidth' => false,
					'thumbheight' => false,
					'thumbfit' => false,
					'thumbsharpen' => false,
					'thumbquality' => false,
					'thumbbg' => false,

					// Effects settings
					'zoomfx' => 'shopp-zoom',
					'preview' => 'click',
					'colorbox' => '{}'


				);
				$optionset = array_merge($defaults,$options);

				// Translate dot names
				$options = array();
				$keys = array_keys($optionset);
				foreach ($keys as $key)
					$options[str_replace('.','_',$key)] = $optionset[$key];
				extract($options);

				if ($p_size > 0)
					$_width = $_height = $p_size;

				$width = $p_width > 0?$p_width:$_width;
				$height = $p_height > 0?$p_height:$_height;

				$preview_width = $width;

				$previews = '<ul class="previews">';
				$firstPreview = true;

				// Find the max dimensions to use for the preview spacing image
				$maxwidth = $maxheight = 0;
				foreach ($this->images as $img) {
					$scale = $p_fit?false:array_search($p_fit,$img->_scaling);
					$scaled = $img->scaled($width,$height,$scale);
					$maxwidth = max($maxwidth,$scaled['width']);
					$maxheight = max($maxheight,$scaled['height']);
				}

				if ($maxwidth == 0) $maxwidth = $width;
				if ($maxheight == 0) $maxheight = $height;

				$p_link = value_is_true($p_link);

				foreach ($this->images as $img) {

					$scale = $p_fit?array_search($p_fit,$img->_scaling):false;
					$sharpen = $p_sharpen?min($p_sharpen,$img->_sharpen):false;
					$quality = $p_quality?min($p_quality,$img->_quality):false;
					$fill = $p_bg?hexdec(ltrim($p_bg,'#')):false;
					$scaled = $img->scaled($width,$height,$scale);

					if ($firstPreview) { // Adds "filler" image to reserve the dimensions in the DOM
						$href = shoppurl(SHOPP_PERMALINKS?trailingslashit('000'):'000','images');
						$previews .= '<li id="preview-fill"'.(($firstPreview)?' class="fill"':'').'>';
						$previews .= '<img src="'.add_query_string("$maxwidth,$maxheight",$href).'" alt=" " width="'.$maxwidth.'" height="'.$maxheight.'" />';
						$previews .= '</li>';
					}
					$title = !empty($img->title)?' title="'.esc_attr($img->title).'"':'';
					$alt = esc_attr(!empty($img->alt)?$img->alt:$img->filename);

					$previews .= '<li id="preview-'.$img->id.'"'.(($firstPreview)?' class="active"':'').'>';

					$href = shoppurl(SHOPP_PERMALINKS?trailingslashit($img->id).$img->filename:$img->id,'images');
					if ($p_link) $previews .= '<a href="'.$href.'" class="gallery product_'.$this->id.' '.$options['zoomfx'].'"'.(!empty($rel)?' rel="'.$rel.'"':'').'>';
					// else $previews .= '<a name="preview-'.$img->id.'">'; // If links are turned off, leave the <a> so we don't break layout
					$previews .= '<img src="'.add_query_string($img->resizing($width,$height,$scale,$sharpen,$quality,$fill),shoppurl($img->id,'images')).'"'.$title.' alt="'.$alt.'" width="'.$scaled['width'].'" height="'.$scaled['height'].'" />';
					if ($p_link) $previews .= '</a>';
					$previews .= '</li>';
					$firstPreview = false;
				}
				$previews .= '</ul>';

				$thumbs = "";
				$twidth = $preview_width+$margins;

				if (count($this->images) > 1) {
					$default_size = 64;
					$_thumbwidth = $Shopp->Settings->get('gallery_thumbnail_width');
					$_thumbheight = $Shopp->Settings->get('gallery_thumbnail_height');
					if (!$_thumbwidth) $_thumbwidth = $default_size;
					if (!$_thumbheight) $_thumbheight = $default_size;

					if ($thumbsize > 0) $thumbwidth = $thumbheight = $thumbsize;

					$width = $thumbwidth > 0?$thumbwidth:$_thumbwidth;
					$height = $thumbheight > 0?$thumbheight:$_thumbheight;

					$firstThumb = true;
					$thumbs = '<ul class="thumbnails">';
					foreach ($this->images as $img) {
						$scale = $thumbfit?array_search($thumbfit,$img->_scaling):false;
						$sharpen = $thumbsharpen?min($thumbsharpen,$img->_sharpen):false;
						$quality = $thumbquality?min($thumbquality,$img->_quality):false;
						$fill = $thumbbg?hexdec(ltrim($thumbbg,'#')):false;
						$scaled = $img->scaled($width,$height,$scale);

						$title = !empty($img->title)?' title="'.esc_attr($img->title).'"':'';
						$alt = esc_attr(!empty($img->alt)?$img->alt:$img->name);

						$thumbs .= '<li id="thumbnail-'.$img->id.'" class="preview-'.$img->id.(($firstThumb)?' first':'').'">';
						$thumbs .= '<img src="'.add_query_string($img->resizing($width,$height,$scale,$sharpen,$quality,$fill),shoppurl($img->id,'images')).'"'.$title.' alt="'.$alt.'" width="'.$scaled['width'].'" height="'.$scaled['height'].'" />';
						$thumbs .= '</li>'."\n";
						$firstThumb = false;
					}
					$thumbs .= '</ul>';

				}
				if ($rowthumbs > 0) $twidth = ($width+$margins+2)*(int)$rowthumbs;

				$result = '<div id="gallery-'.$this->id.'" class="gallery">'.$previews.$thumbs.'</div>';
				$script = "\t".'ShoppGallery("#gallery-'.$this->id.'","'.$preview.'"'.($twidth?",$twidth":"").');';
				add_storefrontjs($script);

				return $result;

				break;
			case "has-categories":
				if (empty($this->categories)) $this->load_data(array('categories'));
				if (count($this->categories) > 0) return true; else return false; break;
			case "categories":
				if (!isset($this->_categories_loop)) {
					reset($this->categories);
					$this->_categories_loop = true;
				} else next($this->categories);

				if (current($this->categories) !== false) return true;
				else {
					unset($this->_categories_loop);
					return false;
				}
				break;
			case "in-category":
				if (empty($this->categories)) $this->load_data(array('categories'));
				if (isset($options['id'])) $field = "id";
				if (isset($options['name'])) $field = "name";
				if (isset($options['slug'])) $field = "slug";
				foreach ($this->categories as $category)
					if ($category->{$field} == $options[$field]) return true;
				return false;
			case "category":
				$category = current($this->categories);
				if (isset($options['show'])) {
					if ($options['show'] == "id") return $category->id;
					if ($options['show'] == "slug") return $category->slug;
				}
				return $category->name;
				break;
			case "hastags":
			case "has-tags":
				if (empty($this->tags)) $this->load_data(array('tags'));
				if (count($this->tags) > 0) return true; else return false; break;
			case "tags":
				if (!isset($this->_tags_loop)) {
					reset($this->tags);
					$this->_tags_loop = true;
				} else next($this->tags);

				if (current($this->tags) !== false) return true;
				else {
					unset($this->_tags_loop);
					return false;
				}
				break;
			case "tagged":
				if (empty($this->tags)) $this->load_data(array('tags'));
				if (isset($options['id'])) $field = "id";
				if (isset($options['name'])) $field = "name";
				foreach ($this->tags as $tag)
					if ($tag->{$field} == $options[$field]) return true;
				return false;
			case "tag":
				$tag = current($this->tags);
				if (isset($options['show'])) {
					if ($options['show'] == "id") return $tag->id;
				}
				return $tag->name;
				break;
			case "hasspecs":
			case "has-specs":
				if (empty($this->specs)) $this->load_data(array('specs'));
				if (count($this->specs) > 0) {
					$this->merge_specs();
					return true;
				} else return false; break;
			case "specs":
				if (!isset($this->_specs_loop)) {
					reset($this->specs);
					$this->_specs_loop = true;
				} else next($this->specs);

				if (current($this->specs) !== false) return true;
				else {
					unset($this->_specs_loop);
					return false;
				}
				break;
			case "spec":
				$string = "";
				$separator = ": ";
				$delimiter = ", ";
				if (isset($options['separator'])) $separator = $options['separator'];
				if (isset($options['delimiter'])) $separator = $options['delimiter'];

				$spec = current($this->specs);
				if (is_array($spec->value)) $spec->value = join($delimiter,$spec->value);

				if (isset($options['name'])
					&& !empty($options['name'])
					&& isset($this->specskey[$options['name']])) {
						$spec = $this->specskey[$options['name']];
						if (is_array($spec)) {
							if (isset($options['index'])) {
								foreach ($spec as $index => $entry)
									if ($index+1 == $options['index'])
										$content = $entry->value;
							} else {
								foreach ($spec as $entry) $contents[] = $entry->value;
								$content = join($delimiter,$contents);
							}
						} else $content = $spec->value;
					$string = apply_filters('shopp_product_spec',$content);
					return $string;
				}

				if (isset($options['name']) && isset($options['content']))
					$string = "{$spec->name}{$separator}".apply_filters('shopp_product_spec',$spec->value);
				elseif (isset($options['name'])) $string = $spec->name;
				elseif (isset($options['content'])) $string = apply_filters('shopp_product_spec',$spec->value);
				else $string = "{$spec->name}{$separator}".apply_filters('shopp_product_spec',$spec->value);
				return $string;
				break;
			case "has-variations":
				return ($this->variations == "on" && (!empty($this->options['v']) || !empty($this->options))); break;
			case "variations":

				$string = "";

				if (!isset($options['mode'])) {
					if (!isset($this->_prices_loop)) {
						reset($this->prices);
						$this->_prices_loop = true;
					} else next($this->prices);
					$price = current($this->prices);

					if ($price && ($price->type == 'N/A' || $price->context != 'variation'))
						next($this->prices);

					if (current($this->prices) !== false) return true;
					else {
						unset($this->_prices_loop);
						return false;
					}
					return true;
				}

				if ($this->outofstock) return false; // Completely out of stock, hide menus
				if (!isset($options['taxes'])) $options['taxes'] = null;

				$defaults = array(
					'defaults' => '',
					'disabled' => 'show',
					'pricetags' => 'show',
					'before_menu' => '',
					'after_menu' => '',
					'label' => 'on',
					'required' => __('You must select the options for this item before you can add it to your shopping cart.','Shopp')
					);
				$options = array_merge($defaults,$options);

				if ($options['mode'] == "single") {
					if (!empty($options['before_menu'])) $string .= $options['before_menu']."\n";
					if (value_is_true($options['label'])) $string .= '<label for="product-options'.$this->id.'">'. __('Options').': </label> '."\n";

					$string .= '<select name="products['.$this->id.'][price]" id="product-options'.$this->id.'">';
					if (!empty($options['defaults'])) $string .= '<option value="">'.$options['defaults'].'</option>'."\n";

					foreach ($this->prices as $pricetag) {
						if ($pricetag->context != "variation") continue;

						if (!isset($options['taxes']))
							$taxrate = shopp_taxrate(null,$pricetag->tax);
						else $taxrate = shopp_taxrate(value_is_true($options['taxes']),$pricetag->tax);
						$currently = ($pricetag->sale == "on")?$pricetag->promoprice:$pricetag->price;
						$disabled = ($pricetag->inventory == "on" && $pricetag->stock == 0)?' disabled="disabled"':'';

						$price = '  ('.money($currently).')';
						if ($pricetag->type != "N/A")
							$string .= '<option value="'.$pricetag->id.'"'.$disabled.'>'.$pricetag->label.$price.'</option>'."\n";
					}
					$string .= '</select>';
					if (!empty($options['after_menu'])) $string .= $options['after_menu']."\n";

				} else {
					if (!isset($this->options)) return;

					$menuoptions = $this->options;
					if (!empty($this->options['v'])) $menuoptions = $this->options['v'];

					$baseop = $Shopp->Settings->get('base_operations');
					$precision = $baseop['currency']['format']['precision'];

					if (!isset($options['taxes']))
						$taxrate = shopp_taxrate(null,true,$this);
					else $taxrate = shopp_taxrate(value_is_true($options['taxes']),true,$this);

					$pricekeys = array();
					foreach ($this->pricekey as $key => $pricing) {
						$filter = array('');
						$_ = new StdClass();
						if ($pricing->type != "Donation")
							$_->p = ((isset($pricing->onsale)
										&& $pricing->onsale == "on")?
											(float)$pricing->promoprice:
											(float)$pricing->price);
						$_->i = ($pricing->inventory == "on");
						$_->s = ($pricing->inventory == "on")?$pricing->stock:false;
						$_->tax = ($pricing->tax == "on");
						$_->t = $pricing->type;
						$pricekeys[$key] = $_;
					}

					ob_start();
?><?php if (!empty($options['defaults'])): ?>
	sjss.opdef = true;
<?php endif; ?>
<?php if (!empty($options['required'])): ?>
	sjss.opreq = "<?php echo $options['required']; ?>";
<?php endif; ?>
	pricetags[<?php echo $this->id; ?>] = <?php echo json_encode($pricekeys); ?>;
	new ProductOptionsMenus('select<?php if (!empty($Shopp->Category->slug)) echo ".category-".$Shopp->Category->slug; ?>.product<?php echo $this->id; ?>.options',{<?php if ($options['disabled'] == "hide") echo "disabled:false,"; ?><?php if ($options['pricetags'] == "hide") echo "pricetags:false,"; ?><?php if (!empty($taxrate)) echo "taxrate:$taxrate,"?>prices:pricetags[<?php echo $this->id; ?>]});
<?php
					$script = ob_get_contents();
					ob_end_clean();

					add_storefrontjs($script);

					foreach ($menuoptions as $id => $menu) {
						if (!empty($options['before_menu'])) $string .= $options['before_menu']."\n";
						if (value_is_true($options['label'])) $string .= '<label for="options-'.$menu['id'].'">'.$menu['name'].'</label> '."\n";
						$category_class = isset($Shopp->Category->slug)?'category-'.$Shopp->Category->slug:'';
						$string .= '<select name="products['.$this->id.'][options][]" class="'.$category_class.' product'.$this->id.' options" id="options-'.$menu['id'].'">';
						if (!empty($options['defaults'])) $string .= '<option value="">'.$options['defaults'].'</option>'."\n";
						foreach ($menu['options'] as $key => $option)
							$string .= '<option value="'.$option['id'].'">'.$option['name'].'</option>'."\n";

						$string .= '</select>';
					}
					if (!empty($options['after_menu'])) $string .= $options['after_menu']."\n";
				}

				return $string;
				break;
			case "variation":
				$variation = current($this->prices);

				if (!isset($options['taxes'])) $options['taxes'] = null;
				else $options['taxes'] = value_is_true($options['taxes']);
				$taxrate = shopp_taxrate($options['taxes'],$variation->tax,$this);

				$weightunit = (isset($options['units']) && !value_is_true($options['units']) ) ? false : $Shopp->Settings->get('weight_unit');

				$string = '';
				if (array_key_exists('id',$options)) $string .= $variation->id;
				if (array_key_exists('label',$options)) $string .= $variation->label;
				if (array_key_exists('type',$options)) $string .= $variation->type;
				if (array_key_exists('sku',$options)) $string .= $variation->sku;
				if (array_key_exists('price',$options)) $string .= money($variation->price+($variation->price*$taxrate));
				if (array_key_exists('saleprice',$options)) {
					if (isset($options['promos']) && !value_is_true($options['promos'])) {
						$string .= money($variation->saleprice+($variation->saleprice*$taxrate));
					} else $string .= money($variation->promoprice+($variation->promoprice*$taxrate));
				}
				if (array_key_exists('stock',$options)) $string .= $variation->stock;
				if (array_key_exists('weight',$options)) $string .= round($variation->weight, 3) . ($weightunit ? " $weightunit" : false);
				if (array_key_exists('shipfee',$options)) $string .= money(floatvalue($variation->shipfee));
				if (array_key_exists('sale',$options)) return ($variation->sale == "on");
				if (array_key_exists('shipping',$options)) return ($variation->shipping == "on");
				if (array_key_exists('tax',$options)) return ($variation->tax == "on");
				if (array_key_exists('inventory',$options)) return ($variation->inventory == "on");
				return $string;
				break;
			case "has-addons":
				return ($this->addons == "on" && !empty($this->options['a'])); break;
				break;
			case "addons":

				$string = "";

				if (!isset($options['mode'])) {
					if (!$this->priceloop) {
						reset($this->prices);
						$this->priceloop = true;
					} else next($this->prices);
					$thisprice = current($this->prices);

					if ($thisprice && $thisprice->type == "N/A")
						next($this->prices);

					if ($thisprice && $thisprice->context != "addon")
						next($this->prices);

					if (current($this->prices) !== false) return true;
					else {
						$this->priceloop = false;
						return false;
					}
					return true;
				}

				if ($this->outofstock) return false; // Completely out of stock, hide menus
				if (!isset($options['taxes'])) $options['taxes'] = null;

				$defaults = array(
					'defaults' => '',
					'disabled' => 'show',
					'before_menu' => '',
					'after_menu' => ''
					);

				$options = array_merge($defaults,$options);

				if (!isset($options['label'])) $options['label'] = "on";
				if (!isset($options['required'])) $options['required'] = __('You must select the options for this item before you can add it to your shopping cart.','Shopp');
				if ($options['mode'] == "single") {
					if (!empty($options['before_menu'])) $string .= $options['before_menu']."\n";
					if (value_is_true($options['label'])) $string .= '<label for="product-options'.$this->id.'">'. __('Options').': </label> '."\n";

					$string .= '<select name="products['.$this->id.'][price]" id="product-options'.$this->id.'">';
					if (!empty($options['defaults'])) $string .= '<option value="">'.$options['defaults'].'</option>'."\n";

					foreach ($this->prices as $pricetag) {
						if ($pricetag->context != "addon") continue;

						if (isset($options['taxes']))
							$taxrate = shopp_taxrate(value_is_true($options['taxes']),$pricetag->tax,$this);
						else $taxrate = shopp_taxrate(null,$pricetag->tax,$this);
						$currently = ($pricetag->sale == "on")?$pricetag->promoprice:$pricetag->price;
						$disabled = ($pricetag->inventory == "on" && $pricetag->stock == 0)?' disabled="disabled"':'';

						$price = '  ('.money($currently).')';
						if ($pricetag->type != "N/A")
							$string .= '<option value="'.$pricetag->id.'"'.$disabled.'>'.$pricetag->label.$price.'</option>'."\n";
					}

					$string .= '</select>';
					if (!empty($options['after_menu'])) $string .= $options['after_menu']."\n";

				} else {
					if (!isset($this->options['a'])) return;

					$taxrate = shopp_taxrate($options['taxes'],true,$this);

					// Index addon prices by option
					$pricing = array();
					foreach ($this->prices as $pricetag) {
						if ($pricetag->context != "addon") continue;
						$pricing[$pricetag->options] = $pricetag;
					}

					foreach ($this->options['a'] as $id => $menu) {
						if (!empty($options['before_menu'])) $string .= $options['before_menu']."\n";
						if (value_is_true($options['label'])) $string .= '<label for="options-'.$menu['id'].'">'.$menu['name'].'</label> '."\n";
						$category_class = isset($Shopp->Category->slug)?'category-'.$Shopp->Category->slug:'';
						$string .= '<select name="products['.$this->id.'][addons][]" class="'.$category_class.' product'.$this->id.' addons" id="addons-'.$menu['id'].'">';
						if (!empty($options['defaults'])) $string .= '<option value="">'.$options['defaults'].'</option>'."\n";
						foreach ($menu['options'] as $key => $option) {

							$pricetag = $pricing[$option['id']];

							if (isset($options['taxes']))
								$taxrate = shopp_taxrate(value_is_true($options['taxes']),$pricetag->tax,$this);
							else $taxrate = shopp_taxrate(null,$pricetag->tax,$this);

							$currently = ($pricetag->sale == "on")?$pricetag->promoprice:$pricetag->price;
							if ($taxrate > 0) $currently = $currently+($currently*$taxrate);
							$string .= '<option value="'.$option['id'].'">'.$option['name'].' (+'.money($currently).')</option>'."\n";
						}

						$string .= '</select>';
					}
					if (!empty($options['after_menu'])) $string .= $options['after_menu']."\n";

				}

				return $string;
				break;

			case "donation":
			case "amount":
			case "quantity":
				if ($this->outofstock) return false;

				$inputs = array('text','menu');
				$defaults = array(
					'value' => 1,
					'input' => 'text', // accepts text,menu
					'labelpos' => 'before',
					'label' => '',
					'options' => '1-15,20,25,30,40,50,75,100',
					'size' => 3
				);
				$options = array_merge($defaults,$options);
				$_options = $options;
				extract($options);

				unset($_options['label']); // Interferes with the text input value when passed to inputattrs()
				$labeling = '<label for="quantity-'.$this->id.'">'.$label.'</label>';

				if (!isset($this->_prices_loop)) reset($this->prices);
				$variation = current($this->prices);
				$_ = array();

				if ("before" == $labelpos) $_[] = $labeling;
				if ("menu" == $input) {
					if ($this->inventory && $this->max['stock'] == 0) return "";

					if (strpos($options,",") !== false) $options = explode(",",$options);
					else $options = array($options);

					$qtys = array();
					foreach ((array)$options as $v) {
						if (strpos($v,"-") !== false) {
							$v = explode("-",$v);
							if ($v[0] >= $v[1]) $qtys[] = $v[0];
							else for ($i = $v[0]; $i < $v[1]+1; $i++) $qtys[] = $i;
						} else $qtys[] = $v;
					}
					$_[] = '<select name="products['.$this->id.'][quantity]" id="quantity-'.$this->id.'">';
					foreach ($qtys as $qty) {
						$amount = $qty;
						$selection = (isset($this->quantity))?$this->quantity:1;
						if ($variation->type == "Donation" && $variation->donation['var'] == "on") {
							if ($variation->donation['min'] == "on" && $amount < $variation->price) continue;
							$amount = money($amount);
							$selection = $variation->price;
						} else {
							if ($this->inventory && $amount > $this->max['stock']) continue;
						}
						$selected = ($qty==$selection)?' selected="selected"':'';
						$_[] = '<option'.$selected.' value="'.$qty.'">'.$amount.'</option>';
					}
					$_[] = '</select>';
				} elseif (valid_input($input)) {
					if ($variation->type == "Donation" && $variation->donation['var'] == "on") {
						if ($variation->donation['min']) $_options['value'] = $variation->price;
						$_options['class'] .= " currency";
					}
					$_[] = '<input type="'.$input.'" name="products['.$this->id.'][quantity]" id="quantity-'.$this->id.'"'.inputattrs($_options).' />';
				}

				if ("after" == $labelpos) $_[] = $labeling;
				return join("\n",$_);
				break;
			case "input":
				if (!isset($options['type']) ||
					($options['type'] != "menu" && $options['type'] != "textarea" && !valid_input($options['type']))) $options['type'] = "text";
				if (!isset($options['name'])) return "";
				if ($options['type'] == "menu") {
					$result = '<select name="products['.$this->id.'][data]['.$options['name'].']" id="data-'.$options['name'].'-'.$this->id.'"'.inputattrs($options,$select_attrs).'>';
					if (isset($options['options']))
						$menuoptions = preg_split('/,(?=(?:[^\"]*\"[^\"]*\")*(?![^\"]*\"))/',$options['options']);
					if (is_array($menuoptions)) {
						foreach($menuoptions as $option) {
							$selected = "";
							$option = trim($option,'"');
							if (isset($options['default']) && $options['default'] == $option)
								$selected = ' selected="selected"';
							$result .= '<option value="'.$option.'"'.$selected.'>'.$option.'</option>';
						}
					}
					$result .= '</select>';
				} elseif ($options['type'] == "textarea") {
					if (isset($options['cols'])) $cols = ' cols="'.$options['cols'].'"';
					if (isset($options['rows'])) $rows = ' rows="'.$options['rows'].'"';
					$result .= '<textarea name="products['.$this->id.'][data]['.$options['name'].']" id="data-'.$options['name'].'-'.$this->id.'"'.$cols.$rows.inputattrs($options).'>'.$options['value'].'</textarea>';
				} else {
					$result = '<input type="'.$options['type'].'" name="products['.$this->id.'][data]['.$options['name'].']" id="data-'.$options['name'].'-'.$this->id.'"'.inputattrs($options).' />';
				}

				return $result;
				break;
			case "outofstock":
				if ($this->outofstock) {
					$label = isset($options['label'])?$options['label']:$Shopp->Settings->get('outofstock_text');
					$string = '<span class="outofstock">'.$label.'</span>';
					return $string;
				} else return false;
				break;
			case "buynow":
				if (!isset($options['value'])) $options['value'] = __("Buy Now","Shopp");
			case "addtocart":

				if (!isset($options['class'])) $options['class'] = "addtocart";
				else $options['class'] .= " addtocart";
				if (!isset($options['value'])) $options['value'] = __("Add to Cart","Shopp");
				$string = "";

				if ($this->outofstock) {
					$string .= '<span class="outofstock">'.$Shopp->Settings->get('outofstock_text').'</span>';
					return $string;
				}
				if (isset($options['redirect']) && !isset($options['ajax']))
					$string .= '<input type="hidden" name="redirect" value="'.$options['redirect'].'" />';

				$string .= '<input type="hidden" name="products['.$this->id.'][product]" value="'.$this->id.'" />';

				if (!empty($this->prices[0]) && $this->prices[0]->type != "N/A")
					$string .= '<input type="hidden" name="products['.$this->id.'][price]" value="'.$this->prices[0]->id.'" />';

				if (!empty($Shopp->Category)) {
					if (SHOPP_PRETTYURLS)
						$string .= '<input type="hidden" name="products['.$this->id.'][category]" value="'.$Shopp->Category->uri.'" />';
					else
						$string .= '<input type="hidden" name="products['.$this->id.'][category]" value="'.((!empty($Shopp->Category->id))?$Shopp->Category->id:$Shopp->Category->slug).'" />';
				}

				$string .= '<input type="hidden" name="cart" value="add" />';
				if (isset($options['ajax'])) {
					if ($options['ajax'] == "html") $options['class'] .= ' ajax-html';
					else $options['class'] .= " ajax";
					$string .= '<input type="hidden" name="ajax" value="true" />';
					$string .= '<input type="button" name="addtocart" '.inputattrs($options).' />';
				} else {
					$string .= '<input type="submit" name="addtocart" '.inputattrs($options).' />';
				}

				return $string;
		}


	}

} // END class Product

class ProductSummary extends DatabaseObject {
	static $table = 'summary';

	function __construct ($id=false,$key='product') {
		$this->init(self::$table);
		$this->load($id,$key);
	}

} // END class ProductSummary


class Spec extends MetaObject {

	function __construct ($id=false) {
		$this->init(self::$table);
		$this->load($id);
		$this->context = 'product';
		$this->type = 'spec';
	}

	function updates ($data,$ignores=array()) {
		parent::updates($data,$ignores);
		if (preg_match('/^.*?(\d+[\.\,\d]*).*$/',$this->value))
			$this->numeral = preg_replace('/^.*?(\d+[\.\,\d]*).*$/','$1',$this->value);
	}

} // END class Spec

?>