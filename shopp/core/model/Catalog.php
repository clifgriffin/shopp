<?php
/**
 * Catalog class
 * 
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited,  9 April, 2008
 * @package shopp
 **/

require_once("Category.php");
require_once("Tag.php");

class Catalog extends DatabaseObject {
	static $table = "catalog";

	var $smarts = array("FeaturedProducts","BestsellerProducts","NewProducts","OnSaleProducts");
	
	function Catalog ($type="catalog") {
		$this->init(self::$table);
		$this->type = $type;
	}
	
	function load_categories ($limits=false,$showsmarts=false) {
		$db = DB::get();
		
		if ($limits) $limit = " LIMIT {$limits[0]},{$limits[1]}";
		else $limit = "";
		
		$category_table = DatabaseObject::tablename(Category::$table);
		$this->categories = $db->query("SELECT cat.*,count(sc.product) AS products FROM $category_table AS cat LEFT JOIN $this->_table AS sc ON sc.category=cat.id GROUP BY cat.id ORDER BY parent DESC,name ASC$limit",AS_ARRAY);
		$this->categories = sort_tree($this->categories);
		
		if ($showsmarts == "before" || $showsmarts == "after")
			$this->smart_categories($showsmarts);
			
		return true;
	}
	
	function smart_categories ($method) {
		foreach ($this->smarts as $SmartCategory) {
			$category = new $SmartCategory(array("noload" => true));
			switch($method) {
				case "before": array_unshift($this->categories,$category); break; 
				default: array_push($this->categories,$category);
			}
		}
	}
	
	function load_tags ($limits=false) {
		$db = DB::get();
		
		if ($limits) $limit = " LIMIT {$limits[0]},{$limits[1]}";
		else $limit = "";
		
		$tagtable = DatabaseObject::tablename(Tag::$table);
		$this->tags = $db->query("SELECT t.*,count(sc.product) AS products FROM $tagtable AS t LEFT JOIN $this->_table AS sc ON sc.tag=t.id GROUP BY t.id ORDER BY t.name ASC$limit",AS_ARRAY);
		return true;
	}
	
		
	function tag ($property,$options=array()) {
		global $Shopp;
		$pages = $Shopp->Settings->get('pages');
		if (SHOPP_PERMALINKS) $path = trailingslashit(get_bloginfo('wpurl'))."{$pages['catalog']['name']}";
		else $page = trailingslashit(get_bloginfo('wpurl'))."?page_id={$pages['catalog']['id']}";
				
		switch ($property) {
			case "url": return $Shopp->link('catalog');
			case "tagcloud":
				if (!empty($options['levels'])) $levels = $options['levels'];
				else $levels = 7;
				if (empty($this->tags)) $this->load_tags();
				$min = -1; $max = -1;
				foreach ($this->tags as $tag) {
					if ($min == -1 || $tag->products < $min) $min = $tag->products;
					if ($max == -1 || $tag->products > $max) $max = $tag->products;
				}
				$string = '<ul class="shopp tagcloud">';
				foreach ($this->tags as $tag) {
					$level = round((1-$tag->products/$max)*$levels)+1;
					if (SHOPP_PERMALINKS) $link = $path.'/tag/'.str_replace(" ","+",$tag->name).'/';
					else $link = $page.'&amp;shopp_tag='.str_replace(" ","+",$tag->name);
					$string .= '<li class="level-'.$level.'"><a href="'.$link.'">'.$tag->name.'</a></li>';
				}
				$string .= '</ul>';
				return $string;
				break;
			case "category-list":
				if (empty($this->categories)) $this->load_categories(false,$options['showsmart']);
				$string = "";
				$depth = 0;
				$parent = false;
				$showall = false;
				if (isset($options['showall'])) $showall = $options['showall'];

				$title = $options['title'];
				if (empty($title)) $title = "";
				if (value_is_true($options['dropdown'])) {
					$string .= $title;
					$string .= '<form><select name="shopp_cats" id="shopp-categories-menu">';
					$string .= '<option value="">Select category&hellip;</option>';
					foreach ($this->categories as &$category) {
						if ($category->products > 0) // Only show categories with products
							if (value_is_true($options['hierarchy']) && $category->depth > $depth) {
								$parent = &$previous;
								if (!isset($parent->path)) $parent->path = '/'.$parent->slug;
							}
							
							
							$padding = str_repeat("&nbsp;",$category->depth*3);

							if (SHOPP_PERMALINKS) $link = $path.'/category/'.$category->uri;
							else $link = $page.'&amp;shopp_category='.$category->id;

							$products = '';
							if (value_is_true($options['products'])) $products = '&nbsp;&nbsp;('.$category->products.')';

							$string .= '<option value="'.$link.'">'.$padding.$category->name.$products.'</option>';
							$previous = &$category;
							$depth = $category->depth;
						
					}
					$string .= '</select></form>';
					$string .= '<script type="text/javascript">';
					$string .= 'var menu = document.getElementById(\'shopp-categories-menu\');';
					$string .= 'if (menu) {';
					$string .= '	menu.onchange = function () {';
					$string .= '		document.location.href = this.options[this.selectedIndex].value;';
					$string .= '	}';
					$string .= '}';
					$string .= '</script>';
					
				} else {
					$string .= $title.'<ul>';
					foreach ($this->categories as &$category) {
						if (value_is_true($options['hierarchy']) && $category->depth > $depth) {
							$parent = &$previous;
							if (!isset($parent->path)) $parent->path = $parent->slug;
							$string .= '<ul class="children">';
						}
						if (value_is_true($options['hierarchy']) && $category->depth < $depth) $string .= '</ul>';
					
						if (SHOPP_PERMALINKS) $link = $path.'/category/'.$category->uri;
						else $link = $page.'&amp;shopp_category='.$category->id;
					
						$products = '';
						if (value_is_true($options['products'])) $products = ' ('.$category->products.')';
					
						if (value_is_true($showall) || $category->products > 0 || $category->smart) // Only show categories with products
							$string .= '<li><a href="'.$link.'">'.$category->name.'</a>'.$products.'</li>';

						$previous = &$category;
						$depth = $category->depth;
					}
					if (value_is_true($options['hierarchy']))
						for ($i = 0; $i < $depth; $i++) $string .= "</ul>";
					$string .= '</ul>';
				}
				return $string;
				break;
			case "views":
				if (isset($Shopp->Category->controls)) return false;
				$string .= '<ul class="views">';
				if (isset($options['label'])) $string .= '<li>'.$options['label'].'</li>';
				$string .= '<li><button type="button" class="grid"></button></li>';
				$string .= '<li><button type="button" class="list"></button></li>';
				$string .= '</ul>';
				return $string;
			case "breadcrumb":
				if (isset($Shopp->Category->controls)) return false;
				if (empty($this->categories)) $this->load_categories();
				$separator = "&nbsp;&raquo; ";
				if (isset($options['separator'])) $separator = $options['separator'];
				if (!empty($Shopp->Category)) {
					
					// Find current category in tree
					for ($i = count($this->categories); $i > 0; $i--)
						if ($Shopp->Category->id == $this->categories[$i]->id) break;
					
					if (SHOPP_PERMALINKS) $link = $path.'/category/'.$Shopp->Category->uri;
					else {
						if (isset($Shopp->Category->smart)) $link = $page.'&shopp_category='.$Shopp->Category->slug;
						else $link = $page.'&shopp_category='.$Shopp->Category->id;
					}

					if (!empty($Shopp->Product)) $trail = '<li><a href="'.$link.'">'.$Shopp->Category->name.'</a></li>';
					else if (!empty($Shopp->Category->name)) $trail = '<li>'.$Shopp->Category->name.'</li>';
					
					// Build category names path by going from the target category up the parent chain
					$parentkey = $this->categories[$i]->parentkey;
					while ($parentkey > -1) {
						$tree_category = $this->categories[$parentkey];
						if (SHOPP_PERMALINKS) $link = $path.'/category/'.$tree_category->uri;
						else $link = $page.'&shopp_category='.$tree_category->id;
						$trail = '<li><a href="'.$link.'">'.$tree_category->name.'</a>'.((empty($trail))?'':$separator).'</li>'.$trail;
						$parentkey = $tree_category->parentkey;
					}
				}
				$trail = '<li><a href="'.((SHOPP_PERMALINKS)?$path:$page).'">'.$pages['catalog']['title'].'</a>'.((empty($trail))?'':$separator).'</li>'.$trail;
				return '<ul class="breadcrumb">'.$trail.'</ul>';
				break;
			case "new-products":
				if ($property == "new-products") $Shopp->Category = new NewProducts($options);
			case "featured-products":
				if ($property == "featured-products") $Shopp->Category = new FeaturedProducts($options);
			case "onsale-products":
				if ($property == "onsale-products") $Shopp->Category = new OnSaleProducts($options);
			case "bestseller-products":
				if ($property == "bestseller-products") $Shopp->Category = new BestsellerProducts($options);
			case "category":
				if ($property == "category") {
					if (isset($options['name'])) $Shopp->Category = new Category($options['name'],'name');
					else if (isset($options['id'])) $Shopp->Category = new Category($options['id']);
				}
				if (isset($options['controls']) && !value_is_true($options['controls'])) 
					$Shopp->Category->controls = false;
				ob_start();
				include(SHOPP_TEMPLATES."/category.php");
				$content = ob_get_contents();
				ob_end_clean();
				return $content;
				break;
		}
	}

} // end Catalog class

?>