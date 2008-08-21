<?php
/**
 * Product class
 * Catalog products
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 28 March, 2008
 * @package shopp
 **/

require("Asset.php");
require("Spec.php");
require("Price.php");

class Product extends DatabaseObject {
	static $table = "product";
	var $prices = array();
	var $pricekey = array();
	var $categories = array();
	var $images = array();
	var $specs = array();
	var $ranges = array('max'=>array(),'min'=>array());
	
	function Product ($id=false,$key=false) {
		$this->init(self::$table);
		if ($this->load($id,$key)) return true;
		return false;
	}
		
	function load_prices () {
		$db = DB::get();
		
		$table = DatabaseObject::tablename(Price::$table);
		if (empty($this->id)) return false;
		$this->prices = $db->query("SELECT * FROM $table WHERE product=$this->id ORDER BY sortorder ASC",AS_ARRAY);
		
		// Build secondary lookup table using the combined optionkey
		foreach ($this->prices as &$price) {
			$this->pricekey[$price->optionkey] = $price;

			// While were at it, grab price and saleprice ranges
			if ($price->type != "N/A") {
				if ($price->price > 0) {
					if (empty($this->ranges['min']['price'])) 
						$this->ranges['min']['price'] = $this->ranges['max']['price'] = $price->price;
					if ($this->ranges['min']['price'] > $price->price) 
						$this->ranges['min']['price'] = $price->price;
					if ($this->ranges['max']['price'] < $price->price) 
						$this->ranges['max']['price'] = $price->price;
				}

				if ($price->saleprice > 0) {
					if (empty($this->ranges['min']['saleprice'])) 
						$this->ranges['min']['saleprice'] = $this->ranges['max']['saleprice'] = $price->saleprice;
					if ($this->ranges['min']['saleprice'] > $price->saleprice) 
						$this->ranges['min']['saleprice'] = $price->saleprice;
					if ($this->ranges['max']['saleprice'] < $price->saleprice) 
						$this->ranges['max']['saleprice'] = $price->saleprice;
				}
				
			}
		}
		
		
		return true;
	}
	
	function load_specs () {
		$db = DB::get();
		
		$table = DatabaseObject::tablename(Spec::$table);
		if (empty($this->id)) return false;
		$this->specs = $db->query("SELECT * FROM $table WHERE product=$this->id ORDER BY sortorder ASC",AS_ARRAY);
		return true;
	}

	function load_categories () {
		$db = DB::get();
		
		$table = DatabaseObject::tablename(Catalog::$table);
		if (empty($this->id)) return false;
		$this->categories = $db->query("SELECT * FROM $table WHERE product=$this->id",AS_ARRAY);
		return true;
	}

	function save_categories ($updates) {
		$db = DB::get();
		
		if (empty($updates)) $updates = array();
		
		$current = array();
		foreach ($this->categories as $catalog) $current[] = $catalog->category;

		$added = array_diff($updates,$current);
		$removed = array_diff($current,$updates);
		
		$table = DatabaseObject::tablename(Catalog::$table);
		
		foreach ($added as $id) {
			$db->query("INSERT $table SET category='$id',product='$this->id',created=now(),modified=now()");
		}
		
		foreach ($removed as $id) {
			$db->query("DELETE LOW_PRIORITY FROM $table WHERE category='$id' AND product='$this->id'"); 
		}
		
	}
	
	function load_images () {
		$db = DB::get();
		
		$table = DatabaseObject::tablename(Asset::$table);
		if (empty($this->id)) return false;
		$images = $db->query("SELECT id,name,properties,datatype,src FROM $table WHERE parent=$this->id AND context='product' AND (datatype='image' OR datatype='small' OR datatype='thumbnail') ORDER BY datatype,sortorder",AS_ARRAY);
		$total = 0;
		foreach ($images as $image) {
			$image->properties = unserialize($image->properties);
			if ($image->datatype == "image") $total++;
		}
		$this->images = $images;
		$this->images['total'] = $total;
		return true;
	}
		
	/**
	 * optionkey
	 * There is no Zul only XOR! */
	function optionkey ($ids=array()) {
		if (empty($ids)) return 0;
		foreach ($ids as $set => $id) 
			$key = $key ^ ($id*101);
		return $key;
	}
	
	/**
	 * save_imageorder()
	 * Updates the sortorder of image assets (source, featured and thumbnails)
	 * based on the provided array of image ids */
	function save_imageorder ($ordering) {
		$db = DB::get();
		$table = DatabaseObject::tablename(Asset::$table);
		foreach ($ordering as $i => $id) 
			$db->query("UPDATE LOW_PRIORITY $table SET sortorder='$i' WHERE id='$id' OR src='$id'");
		return true;
	}
	
	/**
	 * link_images()
	 * Updates the product id of the images to link to the product 
	 * when the product being saved is new (has no previous id assigned) */
	function link_images ($images) {
		$db = DB::get();
		$table = DatabaseObject::tablename(Asset::$table);
		
		$query = "UPDATE $table SET parent='$this->id',context='product' WHERE ";
		foreach ($images as $i => $id) {
			if ($i > 0) $query .= " OR ";
			$query .= "id=$id OR src=$id";
		}
		$db->query($query);
		return true;
	}
	
	
	/**
	 * delete_images()
	 * Delete provided array of image ids, removing the source image and
	 * all related images (featured and thumbnails) */
	function delete_images ($images) {
		$db = DB::get();
		$table = DatabaseObject::tablename(Asset::$table);
		
		$query = "DELETE LOW_PRIORITY FROM $table WHERE ";
		foreach ($images as $i => $id) {
			if ($i > 0) $query .= " OR ";
			$query .= "id=$id OR src=$id";
		}
		$db->query($query);
		return true;
	}
	
	/**
	 * Deletes the record associated with this object */
	function delete () {
		$db = DB::get();
		
		// Delete record
		$id = $this->{$this->_key};
		if (!empty($id)) $db->query("DELETE FROM $this->_table WHERE $this->_key='$id'");
		
		// Delete from categories
		$table = DatabaseObject::tablename(Catalog::$table);
		$db->query("DELETE LOW_PRIORITY FROM $table WHERE product='$this->id'");

		// Delete prices
		$table = DatabaseObject::tablename(Price::$table);
		$db->query("DELETE LOW_PRIORITY FROM $table WHERE product='$this->id'");

		// Delete specs
		$table = DatabaseObject::tablename(Spec::$table);
		$db->query("DELETE LOW_PRIORITY FROM $table WHERE product='$this->id'");

		// Delete images/files
		$table = DatabaseObject::tablename(Asset::$table);
		$db->query("DELETE LOW_PRIORITY FROM $table WHERE parent='$this->id' AND context='product'");

	}
	
	function tag ($property,$options=array()) {
		global $Shopp;
		$pages = $Shopp->Settings->get('pages');
		if (SHOPP_PERMALINKS) $imagepath = "/{$pages['catalog']['name']}/images/";
		else $imagepath = "?shopp_image=";
		
		switch ($property) {
			case "found": if (!empty($this->id)) return true; else return false; break;
			case "name": return $this->name; break;
			case "summary": return $this->summary; break;
			case "description": return wpautop($this->description); break;
			case "brand": return $this->brand; break;
			case "price":
				if (empty($this->prices)) $this->load_prices();
				if ($this->options > 1) {
					if ($this->ranges['min']['price'] == $this->ranges['max']['price'])
						return money($this->ranges['min']['price']);
					else return money($this->ranges['min']['price'])." &mdash; ".money($this->ranges['max']['price']);
				} else return money($this->prices[0]->price);
				break;
			case "onsale":
				if (empty($this->prices)) $this->load_prices();
				if (count($this->prices) > 1) {
					foreach($this->prices as $pricetag) {
						if ($pricetag->sale == "on" && $pricetag->type != "N/A") return true;
					}
					return false;
				} else return ($this->prices[0]->sale == "on" && $this->prices[0]->type != "N/A");
				break;
			case "saleprice":
				if (empty($this->prices)) $this->load_prices();
				if ($this->options > 1) {
					if ($this->ranges['min']['saleprice'] == $this->ranges['max']['saleprice']) 
						return money($this->ranges['min']['saleprice']);
					else return money($this->ranges['min']['saleprice'])." &mdash; ".money($this->ranges['max']['saleprice']);
				} else return money($this->prices[0]->saleprice);
				break;
			case "thumbnails":
				if (empty($this->images)) $this->load_images();
				$string = "";
				foreach ($this->images as $img) {
					if ($img->datatype == "thumbnail") $string .= '<img src="'.$imagepath.$img->id.'" alt="'.$img->datatype.'" width="'.$img->properties['width'].'" height="'.$img->properties['height'].'" />';
				}
				return $string;
				break;
			case "gallery":
				if (empty($this->images)) $this->load_images();
				$thumbsize = 30;
				$string = '<div id="gallery">';
				$previews = '<ul class="previews">';
				$firstPreview = true;
				$thumbs = '<ul>';
				$firstThumb = true;
				foreach ($this->images as $img) {
					if ($img->datatype == "small") {
						if ($firstPreview) {
							$previews .= '<li id="preview-fill"'.(($firstPreview)?' class="fill"':'').'>';
							$previews .= '<img src="'.$Shopp->uri.'/core/ui/icons/clear.png'.'" alt="'.$img->datatype.'" width="'.$img->properties['width'].'" height="'.$img->properties['height'].'" />';
							$previews .= '</li>';
						}
						
						$previews .= '<li id="preview-'.$img->src.'"'.(($firstPreview)?' class="active"':'').'>';
						$previews .= '<a href="'.$imagepath.$img->src.'/'.str_replace('small_','',$img->name).'" class="thickbox" rel="product-gallery">';
						$previews .= '<img src="'.$imagepath.$img->id.'" alt="'.$img->datatype.'" width="'.$img->properties['width'].'" height="'.$img->properties['height'].'" />';
						$previews .= '</a>';
						$previews .= '</li>';
						$firstPreview = false;
					}
					if ($img->datatype == "thumbnail" && $this->images['total'] > 1) {
						$thumbs .= '<li id="thumbnail-'.$img->src.'"'.(($firstThumb)?' class="first"':'').'>';
						$thumbs .= '<a href="javascript:shopp_preview('.$img->src.');"><img src="'.$imagepath.$img->id.'" alt="'.$img->datatype.'" width="'.$thumbsize.'" height="'.$thumbsize.'" /></a>';
						$thumbs .= '</li>';
						$firstThumb = false;
					}
					
				}
				$thumbs .= '</ul>';
				$previews .= '<li class="thumbnails">'.$thumbs.'</li>';
				$previews .= '</ul>';
				$string .= $previews."</div>";
				return $string;
				break;
			case "has-specs": 
				if (empty($this->specs)) $this->load_specs();
				if (count($this->specs) > 0) return true; else return false; break;
			case "specs":			
				if (!$this->specloop) {
					reset($this->specs);
					$this->specloop = true;
				} else next($this->specs);

				if (current($this->specs)) return true;
				else {
					$this->specloop = false;
					return false;
				}
				break;
			case "spec":
				$spec = current($this->specs);
				$string = "";
				$separator = ": ";
				if (isset($options['separator'])) $separator = $options['separator'];
				if (array_key_exists('name',$options) && array_key_exists('content',$options))
					$string = "{$spec->name}{$separator}{$spec->content}";
				else if (array_key_exists('name',$options)) $string = $spec->name;
				else if (array_key_exists('content',$options)) $string = $spec->content;
				else $string = "{$spec->name}{$separator}{$spec->content}";
				return $string;
				break;
			case "has-variations":
				if (isset($this->options['variations'])) return true; else return false; break;
				break;
			case "variations":
				$string = "";

				if (empty($options['label'])) $options['label'] = "on";
				if (empty($options['mode'])) $options['mode'] = "";

				if ($options['mode'] == "single") {
					if (!empty($options['before_menu'])) $string .= $options['before_menu']."\n";
					if (value_is_true($options['label'])) $string .= '<label for="product-options">Options: </label> '."\n";

					$string .= '<select name="price" id="product-options">';
					if (!empty($options['defaults'])) $string .= '<option value="">'.$options['defaults'].'</option>'."\n";
					
					foreach ($this->prices as $option) {
						$currently = ($option->sale == "on")?$option->saleprice:$option->price;
						$disabled = ($option->inventory == "on" && $option->stock == 0)?' disabled="disabled"':'';
						
						$price = '  ('.money($currently).')';
						if ($option->type != "N/A")
							$string .= '<option value="'.$option->id.'"'.$disabled.'>'.$option->label.$price.'</option>'."\n";
					}

					$string .= '</select>';
					if (!empty($options['after_menu'])) $string .= $options['after_menu']."\n";
					
				} else {
					foreach ($this->options['variations'] as $id => $menu) {
						if (!empty($options['before_menu'])) $string .= $options['before_menu']."\n";
						if (value_is_true($options['label'])) $string .= '<label for="options-'.$id.'">'.$menu['menu'].'</label> '."\n";

						$string .= '<select name="options['.$id.']" id="options-'.$id.'" class="options">';
						if (!empty($options['defaults'])) $string .= '<option value="">'.$options['defaults'].'</option>'."\n";
						foreach ($menu['label'] as $key => $option)
							$string .= '<option value="'.$menu['id'][$key].'">'.$option.'</option>'."\n";

						$string .= '</select>';
						if (!empty($options['after_menu'])) $string .= $options['after_menu']."\n";
					}
					?>
					<script type="text/javascript">
					//<![CDATA[
					(function($) {
						var pricing = <?php echo json_encode($this->pricekey); ?>;	// price lookup table
						
						$(document).ready(function () {
							var i = 0;
							var previous = false;
							var current = false;
							var menus = $('select.options');
							menus.each(function () {
								current = $(this);
								if (menus.length == 1) {
									optionPriceTags();
								} else if (i > 0) {
									previous.change(function () {
										if (menus.index(current) == menus.length-1) optionPriceTags();
										if (this.selectedIndex == 0) current.attr('disabled',true);
										else current.removeAttr('disabled');
									}).change();
								}
								
								previous = $(this);
								i++;
							});
							
							// Last menu needs pricing
							function optionPriceTags() {
								// Grab selections
								var selected = new Array();
								menus.not(current).each(function () {
									if ($(this).val() != "") selected.push($(this).val());
								});
								var keys = new Array();
								$(current).children().each(function () {
									if ($(this).val() != "") {
										var keys = selected.slice();
										keys.push($(this).val());
										var price = pricing[xorkey(keys)];
										if (price) {
											var pricetag = asMoney((price.sale == "on")?price.saleprice:price.price);
											$(this).attr('text',$(this).attr('text')+"  ("+pricetag+")");
											if (price.inventory == "on" && price.stock == 0) 
												$(this).attr('disabled',true);
										}
									}
								});
							}
						}); // document.ready
						
						// Magic key generator
						function xorkey (ids) {
							for (var key=0,i=0; i < ids.length; i++) 
								key = key ^ (ids[i]*101);
							return key;
						}
						
					})(jQuery)
					//]]>
					</script>
					<?php
				}
				
				return $string;
				break;
			case "has-addons":
				if (isset($this->options['addons'])) return true; else return false; break;
				break;
			case "addtocart":
				$string = "";
				$string .= '<input type="hidden" name="product" value="'.$this->id.'" />';
				if ($this->prices[0]->type != "N/A")
					$string .= '<input type="hidden" name="price" value="'.$this->prices[0]->id.'" />';
				$string .= '<input type="hidden" name="cart" value="add" />';
				$string .= '<input type="submit" name="addtocart" value="Add to Cart" class="addtocart" />';
				return $string;
		}
		
		
	}

} // end Product class

?>