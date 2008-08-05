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
		$db =& DB::get();
		
		$category_table = DatabaseObject::tablename(Category::$table);
		$this->categories = $db->query("select cat.*,count(sc.product) as products from $category_table as cat left join $this->_table as sc on sc.category=cat.id group by cat.id order by parent,name",AS_ARRAY);
		$this->categories = sort_tree($this->categories);
	}
	
	function tag ($property,$options=array()) {
		global $Shopp;
		$pages = $Shopp->Settings->get('pages');
		if (SHOPP_PERMALINKS) $path = "/{$pages[0]['name']}";
		else $page = "?page_id={$pages[0]['id']}";
				
		switch ($property) {
			case "category-list":
				if (empty($this->categories)) $this->load_categories();
				$string = "";
				$depth = 0;
				$parent = false;
				foreach ($this->categories as &$category) {
					if ($category->depth > $depth) {
						$parent = &$previous;
						if (!isset($parent->path)) $parent->path = $parent->slug;
						$string .= '<ul>';
					}
					if ($category->depth < $depth) $string .= '</ul>';
					
					if (SHOPP_PERMALINKS) $link = $path.'/category'.'/'.$category->uri;
					else $link = $page.'&shopp_category='.$category->id;
					
					$string .= '<li><a href="'.$link.'">'.$category->name.'</a></li>';

					$previous = &$category;
					$depth = $category->depth;
				}
				for ($i = 0; $i < $depth; $i++) $string .= "</ul>";
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
					else $trail = '<li>'.$Shopp->Category->name.'</li>';
					
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
				$trail = '<li><a href="'.((SHOPP_PERMALINKS)?$path:$page).'">'.$pages[0]['title'].'</a>'.((empty($trail))?'':$separator).'</li>'.$trail;
				return '<ul class="breadcrumb">'.$trail.'</ul>';
				break;
				
		}
	}

} // end Catalog class

?>