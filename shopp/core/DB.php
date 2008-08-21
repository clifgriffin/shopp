<?php

define("AS_ARRAY",false);
define("DBPREFIX","shopp_");


class Singleton {
	static function &get() {
		static $me;
		if (!isset($me)) $me = new DB();
		return $me;
	}
}

class DB extends Singleton {
	// Define datatypes for MySQL
	var $_datatypes = array("int" => array("int", "bit", "bool", "boolean"),
							"float" => array("float", "double", "decimal", "real"),
							"string" => array("char", "binary", "varbinary", "text", "blob"),
							"list" => array("enum","set"),
							"date" => array("date", "time", "year")
							);
	var $results = array();
	var $queries = array();
	var $dbh = false;


	function DB () {
		global $wpdb;
		$this->dbh = $wpdb->dbh;
	}

	
	/* 
	 * Connects to the database server */
	function connect($user, $password, $database, $host) {
		$this->dbh = @mysql_connect($host, $user, $password);
		if (!$this->dbh) trigger_error("Could not connect to the database server '$host'.");
		else $this->select($database);
	}
	
	/**
	 * Select the database to use for our connection */
	function select($database) {
		if(!@mysql_select_db($database,$this->dbh)) 
			trigger_error("Could not select the '$database' database.");
	}
	
	/**
	 * Escape contents of the string for safe insertion into the db */
	function escape($string) {
		return addslashes($string);
	}
	
	/**
	 * Send a query to the database */
	function query($query, $output=true, $errors=true) {
		if (_DEBUG_) $this->queries[] = $query;
		$result = @mysql_query($query, $this->dbh);
		// echo "<pre>QUERY: $query</pre>";
	
		// Error handling
		if ($this->dbh &&  $error = mysql_error($this->dbh)) {
			if ($errors) trigger_error("Query failed.<br /><br />$error<br /><tt>$query</tt>");
			else return false;
		}
				
		// Results handling
		if ( preg_match("/^\\s*(create|drop|insert|delete|update|replace) /i",$query) ) {
			$this->affected = mysql_affected_rows();
			if ( preg_match("/^\\s*(insert|replace) /i",$query) ) {
				$insert = @mysql_fetch_object(@mysql_query("SELECT LAST_INSERT_ID() AS id", $this->dbh));
				return $insert->id;
			}
			
			if ($this->affected > 0) return $this->affected;
			else return true;
		} else {
			if ($result === true) return true;
			$this->results = array();
			while ($row = @mysql_fetch_object($result)) {
				$this->results[] = $row;
			}
			@mysql_free_result($result);
			if ($output && sizeof($this->results) == 1) $this->results = $this->results[0];
			return $this->results;
		}
	
	}
		
	function datatype($type) {
		foreach($this->_datatypes as $datatype => $patterns) {
			foreach($patterns as $pattern) {				
				if (strpos($type,$pattern) !== false) return $datatype;
			}
		}
		return false;
	}
	
	/**
	 * Prepare the data properties for entry into
	 * the database */
	function prepare($object) {
		$data = array();
		
		// Go through each data property of the object
		foreach(get_object_vars($object) as $property => $value) {
			// If the property is has a _datatype
			// it belongs in the database and needs
			// to be prepared
			
			if (isset($object->_datatypes[$property])) {				
				
				// Process the data
				switch ($object->_datatypes[$property]) {
					case "string":
						// Escape characters in strings as needed
						if (is_array($value)) $value = serialize($value);
						$data[$property] = "'".$value."'";
						break;	
					case "list":
						// If value is empty, skip setting the field
						// so it inherits the default value in the db
						if (!empty($value)) 
							$data[$property] = "'$value'";
						break;
					case "date":
						// If the date is an integer, convert it to an
						// sql YYYY-MM-DD HH:MM:SS format
						if (is_int($value)) {
							$data[$property] = "'".mkdatetime($value)."'";
						// If it's an empty date, set it to now()'s timestamp
						} else if (empty($value)) {
							$data[$property] = "now()";
						// Otherwise it's already ready, so pass it through
						} else {
							$data[$property] = "'$value'";
						}
						break;
					case "int":
					case "float":
						$value = preg_replace("/[^0-9\.]/","", $value);
						$data[$property] = "'$value'";
						break;
					default:
						// Anything not needing processing
						// passes through into the object
						$data[$property] = "'$value'";
				}
				
			}
			
		}
				
		return $data;
	}
	
	/**
	 * Get the list of possible values for an enum() or set() column */
	function column_options($table = null, $column = null) {
		if ( ! ($table && $column)) return array();
		$r = $this->query("SHOW COLUMNS FROM $table LIKE '$column'");
		if ( strpos($r[0]->Type,"enum('") )
			$list = substr($r[0]->Type, 6, strlen($r[0]->Type) - 8);
			
		if ( strpos($r[0]->Type,"set('") )
			$list = substr($r[0]->Type, 5, strlen($r[0]->Type) - 7);
	
		return split("','",$list);
	}
	
	/**
	 * Send a large set of queries to the database. */
	function loaddata ($queries) {
		$queries = explode(";\n", $queries);
		array_pop($queries);
		foreach ($queries as $query) if (!empty($query)) $this->query($query);
		return true;
	}
	
	
} // END class DB


/* class DatabaseObject
 * Generic database glueware between the database and the active data model */

class DatabaseObject {
	
	function DatabaseObject () {
		// Constructor	
	}
	
	/**
	 * Initializes the db object by grabbing table schema
	 * so we know how to work with this table */
	function init ($table,$key="id") {
		$db = DB::get();
		global $Shopp;
		
		$this->_table = $this->tablename($table); // So we know what the table name is
		$this->_key = $key;				// So we know what the primary key is
		$this->_datatypes = array();	// So we know the format of the table
		$this->_lists = array();		// So we know the options for each list
		$this->_defaults = array();		// So we know the default values for each field
				
		if (isset($Shopp->Settings)) {
			$Tables = $Shopp->Settings->get('data_model');
			if (isset($Tables[$this->_table])) {
				$this->_datatypes = $Tables[$this->_table]->_datatypes;
				$this->_lists = $Tables[$this->_table]->_lists;
				foreach($this->_datatypes as $property => $type) $this->{$property} = $this->_defaults[$property];
				return true;
			}
		}
		
		if (!$r = $db->query("SHOW COLUMNS FROM $this->_table",true,false)) return false;
		

		// Map out the table definition into our data structure
		foreach($r as $object) {	
			$property = $object->Field;
			$this->{$property} = $object->Default;
			$this->_datatypes[$property] = $db->datatype($object->Type);
			$this->_defaults[$property] = $object->Default;
						
			// Grab out options from list fields
			if ($db->datatype($object->Type) == "list") {
				$values = str_replace("','", ",", substr($object->Type,strpos($object->Type,"'")+1,-2));
				$this->_lists[$property] = explode(",",$values);
			}
		}
		
		if (isset($Shopp->Settings)) {
			$Tables[$this->_table] = new StdClass();
			$Tables[$this->_table]->_datatypes = $this->_datatypes;
			$Tables[$this->_table]->_lists = $this->_lists;
			$Tables[$this->_table]->_defaults = $this->_defaults;
			$Shopp->Settings->save('data_model',$Tables);
		}
	}
	
	/**
	 * Load a single record by the primary key */
	function load ($id=false,$key=false) {
		$db = DB::get();

		if (!$id) return false;
		if (!$key) $key = $this->_key;

		$r = $db->query("SELECT * FROM $this->_table WHERE $key='$id'");
		$this->populate($r);
		
		if (!empty($this->id)) return true;
		return false;
	}

	/**
	 * Processes a bulk string of semi-colon separated SQL queries */
	function loaddata ($queries) {
		$queries = explode(";\n", $queries);
		array_pop($queries);
		foreach ($queries as $query) if (!empty($query)) $this->query($query);
		return true;
	}
	
	function tablename ($table) {
		return DBPREFIX.$table;
	}
	
	/**
	 * Save a record, updating when we have a value for the primary key,
	 * inserting a new record when we don't */
	function save () {
		$db = DB::get();
		
		$data = $db->prepare($this);
		$id = $this->{$this->_key};
		// Update record
		if (!empty($id)) {
			if (isset($data['modified'])) $data['modified'] = "now()";
			$dataset = $this->dataset($data);
			$db->query("UPDATE $this->_table SET $dataset WHERE $this->_key=$id");
			return true;
		// Insert new record
		} else {
			if (isset($data['created'])) $data['created'] = "now()";
			if (isset($data['modified'])) $data['modified'] = "now()";
			$dataset = $this->dataset($data);
			//print "INSERT $this->_table SET $dataset";
			$this->id = $db->query("INSERT $this->_table SET $dataset");
			return $this->id;
		}

	}
	
	/**
	 * Deletes the record associated with this object */
	function delete () {
		$db = DB::get();
		// Delete record
		$id = $this->{$this->_key};
		if (!empty($id)) $db->query("DELETE FROM $this->_table WHERE $this->_key='$id'");
		else return false;
	}

	/**
	 * Populate the object properties from a set of 
	 * loaded results  */
	function populate($data) {
		if(empty($data)) return false;
		foreach(get_object_vars($data) as $property => $value) {
			if (empty($this->_datatypes[$property])) continue;
			// Process the data
			switch ($this->_datatypes[$property]) {
				case "date":
					$this->{$property} = mktimestamp($value);
					break;
				case "int":
				case "float":
				case "string":
					// If string has been serialized, unserialize it
					if (preg_match("/^[sibNaO](?:\:.+?\{.*\}$|\:.+;$|;$)/",$value)) $value = unserialize($value);
				default:
					// Anything not needing processing
					// passes through into the object
					$this->{$property} = $value;
			}			
		}
	}
	
	/**
	 * Build an SQL-ready string of the prepared data
	 * for entry into the database  */
	function dataset($data) {
		$query = "";
		foreach($data as $property => $value) {
			if (!empty($query)) $query .= ", ";
			$query .= "$property=$value";
		}
		return $query;
	}

	/**
	 * Populate the object properties from a set of 
	 * form post inputs  */
	function updates($data,$ignores = array()) {
		foreach ($data as $key => $value) {
			if (!is_null($value) && 
				!in_array($key,$ignores) && 
				property_exists($this, $key) ) {
				$this->{$key} = $value;
			}	
		}
	}

}  // END class DatabaseObject

/**
 * Compiles a set of arguments into useable SQL syntax fragments
 * that make selecting data from a DB table easier and more flexible when 
 * developing dynamic interfaces. */
class DBfilter {
	var $all;
	var $and_all;
	var $conditions;
	var $conditionals = array();
	var $and_conditions;
	var $order;
	var $limit;

	function DBfilter ($args) {
		
		foreach($args as $arg) {
			if (strpos($arg,"(") === false && strpos($arg,")") === false) {
				list($key,$value) = split("=",$arg);
				if (strpos($value,",")) $values = split(",",$value);
			
				switch($key) {
					case "limit":
						if (isset($values)) $this->limit = "LIMIT $values";
						else $this->limit = "LIMIT $value";
						break;
					case "orderby":
						$this->order = "ORDER BY $value";
						break;
					case "order":
						if (!empty($this->order)) {
							if (strpos(strtoupper($value),"DESC") !== false)
								$this->order .= " DESC";
						}
						break;
					default:
						if (!empty($value))	$this->conditionals[$key] = "$key='".addslashes($value)."'";
				}
			} else {
				$this->conditionals[] = $arg;
			}
		}
		
		$string = "";
		foreach($this->conditionals as $condition) $string .= (empty($string))?"$condition":" AND $condition";
		$this->conditions = (!empty($string))?"$string":"true";
		$this->and_conditions = (!empty($string))?" AND $string":"";
		
		if (!empty($this->order)) $string .= " {$this->order}";
		if (!empty($this->limit)) $string .= " {$this->limit}";
		
		$this->all = $string;
		$this->and_all = (sizeof($this->conditionals))?"AND $string":"$string";
		
		return $this;

	}

}

?>