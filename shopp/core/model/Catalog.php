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

class Catalog extends DatabaseObject {
	static $table = "catalog";
	
	function Catalog () {
		$this->init(self::$table);
	}
	
	function load_categories () {
		$db = DB::get();
		
		$category_table = DatabaseObject::tablename(Category::$table);
		$this->categories = $db->query("select cat.*,count(sc.product) as products from $category_table as cat left join $this->_table as sc on sc.category=cat.id group by cat.id order by parent,name",AS_ARRAY);
		$this->categories = sort_tree($this->categories);
	}
	
	function tag ($property,$options=array()) {
		global $Shopp;
		$pages = $Shopp->Settings->get('pages');
		if (SHOPP_PERMALINKS) $path = "/{$pages['catalog']['name']}";
		else $page = "?page_id={$pages['catalog']['id']}";
				
		switch ($property) {
			case "url": return $Shopp->link('catalog');
			case "category-list":
				if (empty($this->categories)) $this->load_categories();
				$string = "";
				$depth = 0;
				$parent = false;
				$title = $Shopp->Settings->get('category_menu_title');
				if (empty($title)) $title = "";
				$string .= '<li class="shopp categories">'.$title.'<ul>';
				foreach ($this->categories as &$category) {
					if ($category->depth > $depth) {
						$parent = &$previous;
						if (!isset($parent->path)) $parent->path = $parent->slug;
						$string .= '<ul>';
					}
					if ($category->depth < $depth) $string .= '</ul>';
					
					if (SHOPP_PERMALINKS) $link = $path.'/category'.$category->uri;
					else $link = $page.'&amp;shopp_category='.$category->id;
					
					$string .= '<li><a href="'.$link.'">'.$category->name.'</a></li>';

					$previous = &$category;
					$depth = $category->depth;
				}
				for ($i = 0; $i < $depth; $i++) $string .= "</ul>";
				$string .= '</ul></li>';
				return $string;
				break;
			case "breadcrumb":
				if (empty($this->categories)) $this->load_categories();
				$separator = "&nbsp;&raquo; ";
				if (isset($options['separator'])) $separator = $options['separator'];
				if (!empty($Shopp->Category)) {
					
					// Find current category in tree
					for ($i = count($this->categories); $i > 0; $i--)
						if ($Shopp->Category->id == $this->categories[$i]->id) break;
					
					if (SHOPP_PERMALINKS) $link = $path.'/category'.'/'.$Shopp->Category->uri;
					else $link = $page.'&shopp_category='.$Shopp->Category->id;

					if (!empty($Shopp->Product)) $trail = '<li><a href="'.$link.'">'.$Shopp->Category->name.'</a></li>';
					else if (!empty($Shopp->Category->name)) $trail = '<li>'.$Shopp->Category->name.'</li>';
					
					// Build category names path by going from the target category up the parent chain
					$parentkey = $this->categories[$i]->parentkey;
					while ($parentkey > -1) {
						$tree_category = $this->categories[$parentkey];
						if (SHOPP_PERMALINKS) $link = $path.'/category'.'/'.$tree_category->uri;
						else $link = $page.'&shopp_category='.$tree_category->id;
						$trail = '<li><a href="'.$link.'">'.$tree_category->name.'</a>'.((empty($trail))?'':$separator).'</li>'.$trail;
						$parentkey = $tree_category->parentkey;
					}
				}
				$trail = '<li><a href="'.((SHOPP_PERMALINKS)?$path:$page).'">'.$pages['catalog']['title'].'</a>'.((empty($trail))?'':$separator).'</li>'.$trail;
				return '<ul class="breadcrumb">'.$trail.'</ul>';
				break;
				
			case "new-products":
				$Shopp->Category = new Category();
				$Shopp->Category->newest();
				ob_start();
				include("{$Shopp->Flow->basepath}/templates/category.php");
				$content = ob_get_contents();
				ob_end_clean();
				return $content;
				break;
		}
	}

} // end Catalog class

?>