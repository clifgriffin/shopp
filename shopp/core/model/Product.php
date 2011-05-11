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

class Product extends DatabaseObject {
	static $table = "product";
	var $api = 'product';

	var $prices = array();
	var $pricekey = array();
	var $priceid = array();
	var $categories = array();
	var $tags = array();
	var $images = array();
	var $specs = array();
	var $max = array();
	var $min = array();
	var $onsale = false;
	var $freeshipping = false;
	var $outofstock = false;
	var $stock = 0;
	var $options = 0;

	function __construct ($id=false,$key=false) {
		$this->init(self::$table);
		$this->load($id,$key);
	}

	/**
	 * Loads specified relational data associated with the product
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param array $options List of data to load (prices, images, categories, tags, specs)
	 * @param array $products List of products to load data for
	 * @return void
	 **/
	function load_data ($options=false,&$products=false) {
		global $Shopp;
		$db =& DB::get();

		// Load object schemas on request
		$catalogtable = DatabaseObject::tablename(Catalog::$table);
		$ct_id = get_catalog_taxonomy_id('category');
		$tt_id = get_catalog_taxonomy_id('tag');

		$Dataset = array();
		if (in_array('prices',$options)) {
			$this->prices = array();
			$promotable = DatabaseObject::tablename(Promotion::$table);
			$assettable = DatabaseObject::tablename(ProductDownload::$table);

			$Dataset['prices'] = new Price();
			$Dataset['prices']->_datatypes['promos'] = "MAX(promo.status)";
			$Dataset['prices']->_datatypes['promotions'] = "group_concat(promo.name)";
			$Dataset['prices']->_datatypes['percentoff'] = "SUM(IF (promo.type='Percentage Off',promo.discount,0))";
			$Dataset['prices']->_datatypes['amountoff'] = "SUM(IF (promo.type='Amount Off',promo.discount,0))";
			$Dataset['prices']->_datatypes['freeshipping'] = "SUM(IF (promo.type='Free Shipping',1,0))";
			$Dataset['prices']->_datatypes['buyqty'] = "IF (promo.type='Buy X Get Y Free',promo.buyqty,0)";
			$Dataset['prices']->_datatypes['getqty'] = "IF (promo.type='Buy X Get Y Free',promo.getqty,0)";
			$Dataset['prices']->_datatypes['download'] = "download.id";
			$Dataset['prices']->_datatypes['filename'] = "download.name";
			$Dataset['prices']->_datatypes['filedata'] = "download.value";
		}

		if (in_array('images',$options)) {
			$this->images = array();
			$Dataset['images'] = new ProductImage();
			array_merge($Dataset['images']->_datatypes,$Dataset['images']->_xcols);
		}

		if (in_array('categories',$options)) {
			$this->categories = array();
			$Dataset['categories'] = new Category();
			unset($Dataset['categories']->_datatypes['priceranges']);
			unset($Dataset['categories']->_datatypes['specs']);
			unset($Dataset['categories']->_datatypes['options']);
			unset($Dataset['categories']->_datatypes['prices']);
		}

		if (in_array('specs',$options)) {
			$this->specs = array();
			$Dataset['specs'] = new Spec();
		}

		if (in_array('tags',$options)) {
			$this->tags = array();
			$Dataset['tags'] = new CatalogTag();
		}

		// Determine the maximum columns to allocate
		$maxcols = 0;
		foreach ($Dataset as $set) {
			$cols = count($set->_datatypes);
			if ($cols > $maxcols) $maxcols = $cols;
		}

		// Prepare product list depending on single product or entire list
		$ids = array();
		if (isset($products) && is_array($products)) {
			foreach ($products as $product) $ids[] = $product->id;
		} else $ids[0] = $this->id;

		// Skip if there are no product ids
		if (empty($ids) || empty($ids[0])) return false;

		// Build the mega-query
		foreach ($Dataset as $rtype => $set) {

			// Allocate generic columns for record data
			$columns = array(); $i = 0;
			foreach ($set->_datatypes as $key => $datatype)
				$columns[] = ((strpos($datatype,'.')!==false)?"$datatype":"{$set->_table}.$key")." AS c".($i++);
			for ($i = $i; $i < $maxcols; $i++)
				$columns[] = "'' AS c$i";

			$cols = join(',',$columns);

			// Build object-specific selects and UNION them
			$where = "";
			if (isset($query)) $query .= " UNION ";
			else $query = "";
			switch($rtype) {
				case "prices":
					foreach ($ids as $id) $where .= ((!empty($where))?" OR ":"")."$set->_table.product=$id";
					$query .= "(SELECT '$set->_table' as dataset,$set->_table.product AS product,'$rtype' AS rtype,'' AS alphaorder,$set->_table.sortorder AS sortorder,$cols FROM $set->_table
								LEFT JOIN $assettable AS download ON $set->_table.id=download.parent AND download.context='price' AND download.type='download'
								LEFT JOIN $promotable AS promo ON 0 < FIND_IN_SET(promo.id,$set->_table.discounts) AND
								promo.target='Catalog' AND
								(promo.status='enabled' AND ((UNIX_TIMESTAMP(starts)=1 AND UNIX_TIMESTAMP(ends)=1)
								OR (".time()." > UNIX_TIMESTAMP(starts) AND ".time()." < UNIX_TIMESTAMP(ends))
								OR (UNIX_TIMESTAMP(starts)=1 AND ".time()." < UNIX_TIMESTAMP(ends))
								OR (".time()." > UNIX_TIMESTAMP(starts) AND UNIX_TIMESTAMP(ends)=1) ))
								WHERE $where GROUP BY $set->_table.id)";
					break;
				case "images":
					$ordering = $Shopp->Settings->get('product_image_order');
					if (empty($ordering)) $ordering = "ASC";
					$orderby = $Shopp->Settings->get('product_image_orderby');

					$sortorder = "0";
					if ($orderby == "sortorder" || $orderby == "created") {
						if ($orderby == "created") $orderby = "UNIX_TIMESTAMP(created)";
						switch ($ordering) {
							case "DESC": $sortorder = "$orderby*-1"; break;
							case "RAND": $sortorder = "RAND()"; break;
							default: $sortorder = "$orderby";
						}
					}

					$alphaorder = "''";
					if ($orderby == "name") {
						switch ($ordering) {
							case "DESC": $alphaorder = "$orderby"; break;
							case "RAND": $alphaorder = "RAND()"; break;
							default: $alphaorder = "$orderby";
						}
					}

					foreach ($ids as $id) $where .= ((!empty($where))?" OR ":"")."parent=$id";
					$where = "($where) AND context='product' AND type='image'";
					$query .= "(SELECT '$set->_table' as dataset,parent AS product,'$rtype' AS rtype,$alphaorder AS alphaorder,$sortorder AS sortorder,$cols FROM $set->_table WHERE $where ORDER BY $orderby)";
					break;
				case "specs":
					foreach ($ids as $id) $where .= ((!empty($where))?" OR ":"")."parent=$id AND context='product' AND type='spec'";
					$query .= "(SELECT '$set->_table' as dataset,parent AS product,'$rtype' AS rtype,'' AS alphaorder,sortorder AS sortorder,$cols FROM $set->_table WHERE $where)";
					break;
				case "categories":
					foreach ($ids as $id) $where .= ((!empty($where))?" OR ":"")."catalog.product=$id";
					$where = "($where) AND catalog.taxonomy='$ct_id'";
					$query .= "(SELECT '$set->_table' as dataset,catalog.product AS product,'$rtype' AS rtype,$set->_table.name AS alphaorder,0 AS sortorder,$cols FROM $catalogtable AS catalog LEFT JOIN $set->_table ON catalog.parent=$set->_table.id AND catalog.taxonomy='$ct_id' WHERE $where)";
					break;
				case "tags":
					foreach ($ids as $id) $where .= ((!empty($where))?" OR ":"")."catalog.product=$id";
					$where = "($where) AND catalog.taxonomy='$tt_id'";
					$query .= "(SELECT '$set->_table' as dataset,catalog.product AS product,'$rtype' AS rtype,$set->_table.name AS alphaorder,0 AS sortorder,$cols FROM $catalogtable AS catalog LEFT JOIN $set->_table ON catalog.parent=$set->_table.id AND catalog.taxonomy='$tt_id' WHERE $where)";
					break;
			}
		}

		// Add order by columns
		$query .= " ORDER BY sortorder";
		// die($query);

		// Execute the query
		$data = $db->query($query,AS_ARRAY);

		// Process the results into specific product object data in a product set

		foreach ($data as $row) {
			if (is_array($products) && isset($products[$row->product]))
				$target = $products[$row->product];
			else $target = $this;

			$record = new stdClass(); $i = 0; $name = "";
			foreach ($Dataset[$row->rtype]->_datatypes AS $key => $datatype) {
				$column = 'c'.$i++;
				$record->{$key} = '';
				if ($key == "name") $name = $row->{$column};
				if (!empty($row->{$column})) {
					if (preg_match("/^[sibNaO](?:\:.+?\{.*\}$|\:.+;$|;$)/",$row->{$column}))
						$row->{$column} = unserialize($row->{$column});
					$record->{$key} = $row->{$column};
				}
			}

			if ($row->rtype == "images") {
				$image = new ProductImage();
				$image->copydata($record,false,array());
				$image->expopulate();
				$name = $image->filename;
				$record = $image;

				// Reset the product's loaded images if the image was already
				// loaded from another context (like Category::load_products())
				if (isset($target->{$row->rtype}[0]) && $target->{$row->rtype}[0]->id == $image->id)
					$target->{$row->rtype} = array();
			}

			if ($row->rtype == "prices") {
				// Handle expanding price settings
				if (!empty($record->settings)) {
					$sets = array('donation','recurring','membership');
					foreach ($sets as $setting)
						if (isset($record->settings[$setting])) $record->{$setting} = $record->settings[$setting];
				}
			}

			$target->{$row->rtype}[] = $record;
			if (!empty($name)) {
				if (isset($target->{$row->rtype.'key'}[$name]))
					$target->{$row->rtype.'key'}[$name] = array($target->{$row->rtype.'key'}[$name],$record);
				else $target->{$row->rtype.'key'}[$name] = $record;
			}
		}

		if (is_array($products)) {
			foreach ($products as $product) if (!empty($product->prices)) $product->pricing($options);
		} else {
			if (!empty($this->prices)) $this->pricing($options);
		}

	} // end load_data()

	/**
	 * Aggregates product pricing information
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param array $options shopp() tag option list
	 * @return void
	 **/
	function pricing ($options = array()) {

		// Variation range index/properties
		$varranges = array('price' => 'price','saleprice'=>'promoprice');

		$variations = ($this->variations == "on");
		$freeshipping = true;
		$this->inventory = false;

		// By default, run stat calculations if no stat data exists
		if ( in_array('restat',$options)
				|| $this->maxprice+$this->minprice+$this->stock == 0) {
			add_action('shopp_init_product_pricing',array(&$this,'reset_stats'));
			add_action('shopp_product_stats',array(&$this,'stats'));
			add_action('shopp_product_pricing_done',array(&$this,'save_stats'));
		}

		do_action('shopp_init_product_pricing');
		foreach ($this->prices as $i => &$price) {
			$price->price = (float)$price->price;
			$price->saleprice = (float)$price->saleprice;
			$price->shipfee = (float)$price->shipfee;
			$price->promoprice = (float)$price->promoprice;

			// Build secondary lookup table using the price id as the key
			$this->priceid[$price->id] = $price;

			if (defined('WP_ADMIN') && !isset($options['taxes'])) $options['taxes'] = true;
			if (defined('WP_ADMIN') && value_is_true($options['taxes']) && $price->tax == "on") {
				$Settings =& ShoppSettings();
				$base = $Settings->get('base_operations');
				if ($base['vat']) {
					$Taxes = new CartTax();
					$taxrate = $Taxes->rate($this);
					$price->price += $price->price*$taxrate;
					$price->saleprice += $price->saleprice*$taxrate;
				}
			}

			if ($price->type == "N/A" || $price->context == "addon" || ($i > 0 && !$variations)) continue;

			// Build third lookup table using the combined optionkey
			$this->pricekey[$price->optionkey] = $price;

			// Boolean flag for custom product sales
			$price->onsale = false;
			if ($price->sale == "on")
				$this->onsale = $price->onsale = true;

			$price->stocked = false;
			if ($price->inventory == "on") {
				$this->stock += $price->stock;
				$this->inventory = $price->stocked = true;
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

			if ($price->promoprice < $price->price) $this->onsale = $price->onsale = true;

			// Grab price and saleprice ranges (minimum - maximum)
			if (!$price->price) $price->price = 0;
			if ($price->stocked) $varranges['stock'] = 'stock';

			do_action_ref_array('shopp_product_stats',array(&$price));

			foreach ($varranges as $name => $prop) {
				if (!isset($price->$prop)) continue;

				if (!isset($this->min[$name])) $this->min[$name] = $price->$prop;
				else $this->min[$name] = min($this->min[$name],$price->$prop);
				if ($this->min[$name] == $price->$prop) $this->min[$name.'_tax'] = ($price->tax == "on");


				if (!isset($this->max[$name])) $this->max[$name] = $price->$prop;
				else $this->max[$name] = max($this->max[$name],$price->$prop);
				if ($this->max[$name] == $price->$prop) $this->max[$name.'_tax'] = ($price->tax == "on");
			}

			// Determine savings ranges
			if ($price->onsale && isset($this->min['price']) && isset($this->min['saleprice'])) {

				if (!isset($this->min['saved'])) {
					$this->min['saved'] = $price->price;
					$this->min['savings'] = 100;
					$this->max['saved'] = $this->max['savings'] = 0;
				}

				$this->min['saved'] = min($this->min['saved'],($price->price-$price->promoprice));
				$this->max['saved'] = max($this->max['saved'],($price->price-$price->promoprice));

				// Find lowest savings percentage
				if ($this->min['saved'] == ($price->price-$price->promoprice))
					$this->min['savings'] = (1 - $price->promoprice/($price->price == 0?1:$price->price))*100;
				if ($this->max['saved'] == ($price->price-$price->promoprice))
					$this->max['savings'] = (1 - $price->promoprice/($price->price == 0?1:$price->price))*100;
			}

			// Determine weight ranges
			if($price->weight && $price->weight > 0) {
				if(!isset($this->min['weight'])) $this->min['weight'] = $this->max['weight'] = $price->weight;
				$this->min['weight'] = min($this->min['weight'],$price->weight);
				$this->max['weight'] = max($this->max['weight'],$price->weight);
			}

		} // end foreach($price)

		do_action('shopp_product_pricing_done');
		// if ($buildstats) $this->save_stats();

		if ($this->inventory && $this->stock <= 0) $this->outofstock = true;
		if ($freeshipping) $this->freeshipping = true;
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
	 * Calculates aggregate product stats
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @param object $Price The price record to calculate against
	 * @return void
	 **/
	function stats ($Price) {
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

	function reset_stats () {
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
	function save_stats ($stats = array('sale','inventory','stock','maxprice','minprice','sold')) {
		$default = array('sale','inventory','stock','maxprice','minprice','sold');
		if(empty($stats)) $stats = $default;

		$db = DB::get();
		if (empty($this->id)) return;

		$statdata = new stdClass();
		$statdata->_datatype = array();
		foreach ($stats as $stat) {
			$statdata->_datatypes[$stat] = 'string';
			$statdata->$stat = $this->$stat;
		}
		$data = $db->prepare($statdata);
		$dataset = $this->dataset($data);

		if (empty($dataset)) return;

		$query = "UPDATE LOW_PRIORITY $this->_table SET $dataset WHERE $this->_key=$this->id";
		$db->query($query);
	}

	/**
	 * Saves product category assignments to the catalog
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param array $updates Updated list of category ids the product is assigned to
	 * @return void
	 **/
	function save_categories ($updates) {
		$db = DB::get();

		$taxonomy = get_catalog_taxonomy_id('category');

		if (empty($updates)) $updates = array();

		$current = array();
		foreach ($this->categories as $category) $current[] = $category->id;

		$added = array_diff($updates,$current);
		$removed = array_diff($current,$updates);

		$table = DatabaseObject::tablename(Catalog::$table);

		if (!empty($added)) {
			foreach ($added as $id) {
				if (empty($id)) continue;
				$db->query("INSERT $table SET parent='$id',taxonomy='$taxonomy',product='$this->id',created=now(),modified=now()");
			}
		}

		if (!empty($removed)) {
			foreach ($removed as $id) {
				if (empty($id)) continue;
				$db->query("DELETE LOW_PRIORITY FROM $table WHERE parent='$id' AND taxonomy='$taxonomy' AND product='$this->id'");
			}

		}

	}

	function save_tags ($updates) {
		$db = DB::get();

		$taxonomy = get_catalog_taxonomy_id('tag');

		if (empty($updates)) $updates = array();
		$updates = stripslashes_deep($updates);

		$current = array();
		foreach ($this->tags as $tag) $current[] = $tag->name;

		$added = array_diff($updates,$current);
		$removed = array_diff($current,$updates);

		if (!empty($added)) {
			$catalog = DatabaseObject::tablename(Catalog::$table);
			$tagtable = DatabaseObject::tablename(CatalogTag::$table);
			$where = "";
			foreach ($added as $tag) $where .= ($where == ""?"":" OR ")."name='".$db->escape($tag)."'";
			$results = $db->query("SELECT id,name FROM $tagtable WHERE $where",AS_ARRAY);
			$exists = array();
			foreach ($results as $tag) $exists[$tag->id] = $tag->name;

			foreach ($added as $tag) {
				if (empty($tag)) continue; // No empty tags
				$tagid = array_search($tag,$exists);

				if (!$tagid) {
					$Tag = new CatalogTag();
					$Tag->name = $tag;
					$Tag->save();
					$tagid = $Tag->id;
				}

				if (!empty($tagid))
					$db->query("INSERT $catalog
								SET parent='$tagid',
									taxonomy='$taxonomy',
									product='$this->id',
									created=now(),
									modified=now()");
			}
		}

		if (!empty($removed)) {
			$catalog = DatabaseObject::tablename(Catalog::$table);
			foreach ($removed as $tag) {
				// Ensure loading tag records by case-sensitive name with BINARY casting
				$Tag = new CatalogTag($tag,'BINARY name');
				if (!empty($Tag->id))
					$db->query("DELETE LOW_PRIORITY FROM $catalog WHERE parent='$Tag->id' AND type='$taxonomy' AND product='$this->id'");
			}
		}
	}

	function save_taxonomy ($taxonomy,$updates) {
		$db = DB::get();

		if (!catalog_taxonomy_exists($taxonomy))
			return new ShoppError(sprintf(__('Cannot save the product taxonomy updates because "%s" is not a valid taxonomy.'),$taxonomy),'invalid_taxonomy',SHOPP_ADMIN_ERR);

		$type = get_catalog_taxonomy_id($taxonomy);
		if (empty($type)) {
			$type = save_catalog_taxomony($taxonomy);
			if (empty($type))
				return new ShoppError(sprintf(__('Cannot save the product taxonomy updates because a database failure prevented "%s" reserving the taxonomy.'),$taxonomy),'save_taxonomy',SHOPP_ADMIN_ERR);
		}

		if (empty($updates)) $updates = array();
		$updates = stripslashes_deep($updates);

		$current = array();
		foreach ($this->taxonomies[$taxonomy] as $t) $current[] = $t->name;

		$added = array_diff($updates,$current);
		$removed = array_diff($current,$updates);

		if (!empty($added)) {
			$catalog = DatabaseObject::tablename(Catalog::$table);
			$taxonomies = DatabaseObject::tablename(CatalogTaxonomy::$table);
			$where = "";
			foreach ($added as $tag) $where .= ($where == ""?"":" OR ")."name='".$db->escape($tag)."'";
			$results = $db->query("SELECT id,name FROM $taxonomies WHERE $where",AS_ARRAY);
			$exists = array();
			foreach ($results as $r) $exists[$r->id] = $r->name;

			foreach ($added as $t) {
				if (empty($t)) continue; // No empty tags
				$id = array_search($t,$exists);

				if (!$id) {
					$Entry = new CatalogTaxonomy($taxonomy);
					$Entry->name = $t;
					$Entry->save();
					$id = $Entry->id;
				}

				if (!empty($id))
					$db->query("INSERT $catalog SET parent='$id',type='$type',product='$this->id',created=now(),modified=now()");

			}
		}

		if (!empty($removed)) {
			$catalog = DatabaseObject::tablename(Catalog::$table);
			foreach ($removed as $tag) {
				// Ensure loading tag records by case-sensitive name with BINARY casting
				$Tag = new CatalogTag($tag,'BINARY name');
				if (!empty($Tag->id))
					$db->query("DELETE LOW_PRIORITY FROM $catalog WHERE parent='$Tag->id' AND type='$type' AND product='$this->id'");
			}
		}
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
		$db = DB::get();
		$table = DatabaseObject::tablename(ProductImage::$table);
		foreach ($ordering as $i => $id)
			$db->query("UPDATE LOW_PRIORITY $table SET sortorder='$i' WHERE (id='$id' AND parent='$this->id' AND context='product' AND type='image')");
		return true;
	}

	/**
	 * link_images()
	 * Updates the product id of the images to link to the product
	 * when the product being saved is new (has no previous id assigned) */
	function link_images ($images) {
		if (empty($images)) return false;
		$db = DB::get();
		$table = DatabaseObject::tablename(ProductImage::$table);
		$set = "id=".join(' OR id=',$images);
		$query = "UPDATE $table SET parent='$this->id',context='product' WHERE ".$set;
		$db->query($query);
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

	/**
	 * Provides shopp('product') template API functionality
	 *
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @deprecated 1.2
	 * @return mixed
	 *
	 **/
	function tag ($property,$options=array()) {
		if (is_array($options)) $options['return'] = 'on';
		else $options .= (!empty($options)?"&":"").'return=on';
		return shopp($this,$property,$options);
	}

} // END class Product

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