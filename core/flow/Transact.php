<?php
/**
 * Transact
 * 
 * Flow dispatcher for transaction handling
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, January 12, 2010
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @subpackage shopp
 **/


/**
 * Transact
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package shopp
 **/
class Transact extends FlowController {
	
	/**
	 * Transact constructor
	 *
	 * @author Jonathan Davis
	 * 
	 * @return void
	 **/
	function __construct () {
		add_action('parse_request',array(&$this,'parse'));
	}
	
	/**
	 * Parses the request for transaction messages and dispatches checkout events
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function parse () {
		
	}
	

} // END class Transact

?>