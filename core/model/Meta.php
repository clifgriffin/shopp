<?php
/**
 * Meta.php
 * 
 * The meta object abstract
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, February 10, 2010
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @subpackage meta
 **/

class ObjectMeta {
	static $table = "meta";

	var $loaded = false;
	var $meta = array();
	var $named = array();
	
	function __construct ($parent=false,$context='product',$type=false,$sort='sortorder') {
		$this->_table = DatabaseObject::tablename(self::$table);
		
		$params = array(
			'parent' => $parent,
			'context' => $context
		);
		if ($type !== false) $params['type'] = $type;
		if ($parent !== false) $this->load($params);
	}
	
	function load () {
		$db = &DB::get();

		$args = func_get_args();
		if (empty($args[0])) return false;
		if (!is_array($args[0])) return false;

		$where = "";
		foreach ($args[0] as $key => $id) 
			$where .= ($where == ""?"":" AND ")."$key='".$db->escape($id)."'";

		$r = $db->query("SELECT * FROM $this->_table WHERE $where",AS_ARRAY);
		foreach ($r as $row) {
			$meta = new MetaObject();
			$meta->populate($row,'',array());
			
			$this->meta[$meta->id] = $meta;
			
			// if (isset($this->named[$meta->name])) {
			// 	$this->named[$meta->name] = array($this->named[$meta->name]);
			// 	$this->named[$meta->name][] = &$meta;
			// } else $this->named[$meta->name] = &$meta;
		}
		
		if (count($row) == 0) $this->loaded = false;
		$this->loaded = true;

		return $this->loaded;
	}
	
}

/**
 * MetaObject
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package shopp
 **/
class MetaObject extends DatabaseObject {
	static $table = "meta";
	
	var $context = 'product';
	var $type = 'meta';
	
	/**
	 * Meta constructor
	 *
	 * @author Jonathan Davis
	 * 
	 * @return void
	 **/
	function __construct ($id=false,$key='id') {
		$this->init(self::$table);
		$this->load($id,$key);
	}
	
} // END class Meta

?>