<?php
/**
 * Remote
 *
 * Remote API controller resource classes
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, August  8, 2012
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package
 * @since 1.0
 * @subpackage
 **/

/**
 * Remote
 *
 * @author Jonathan Davis
 * @since
 * @package
 **/
class ShoppRemoteAPIServiceFramework {

	private $codes = array(
		'100' => 'Continue',
		'101' => 'Switching Protocols',
		'200' => 'OK',
		'201' => 'Created',
		'202' => 'Accepted',
		'203' => 'Non-Authoritative Information',
		'204' => 'No Content',
		'205' => 'Reset Content',
		'206' => 'Partial Content',
		'300' => 'Multiple Choices',
		'301' => 'Moved Permanently',
		'302' => 'Found',
		'303' => 'See Other',
		'304' => 'Not Modified',
		'305' => 'Use Proxy',
		'307' => 'Temporary Redirect',
		'400' => 'Bad Request',
		'401' => 'Unauthorized',
		'402' => 'Payment Required',
		'403' => 'Forbidden',
		'404' => 'Not Found',
		'405' => 'Method Not Allowed',
		'406' => 'Not Acceptable',
		'409' => 'Conflict',
		'410' => 'Gone',
		'411' => 'Length Required',
		'412' => 'Precondition Failed',
		'413' => 'Request Entity Too Large',
		'414' => 'Request-URI Too Long',
		'415' => 'Unsupported Media Type',
		'416' => 'Requested Range Not Satisfiable',
		'417' => 'Expectation Failed',
		'500' => 'Internal Server Error',
		'501' => 'Not Implemented',
		'503' => 'Service Unavailable'
	);

	/**
	 * Remote constructor
	 *
	 * @author Jonathan Davis
	 *
	 * @return void
	 **/
	function __construct () {
		add_action( 'shopp_remoteapi_unauthorized',	array(__CLASS__,'unauthorized') );
		add_action( 'shopp_remoteapi_forbidden',	array(__CLASS__,'forbidden') );
		add_action( 'shopp_remoteapi_notfound',		array(__CLASS__,'notfound') );
	}

	/**
	 * OK (200)
	 *
	 * Standard response for successful HTTP requests.
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @return void
	 **/
	function ok () {
		header('Content-Type: text/plain');
		status_header('200');
		ShoppRemoteAPIServer::done();
	}

	/**
	 * No Content (204)
	 *
	 * The server successfully processed the request, but is not returning any content.
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @return void
	 **/
	function nocontent () {
		header('Content-Type: text/plain');
		status_header('204');
		echo "Moved to Trash.";
		ShoppRemoteAPIServer::done();
	}

	/**
	 * Internal Error (500)
	 *
	 * A generic error message, given when no more specific message is suitable.
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @return void
	 **/
	function internalerror ($msg = 'Internal Server Error') {
		header('Content-Type: text/plain');
		status_header('500');
		echo $msg;
		ShoppRemoteAPIServer::done();
	}

	/**
	 * Not Implemented (501)
	 *
	 * The server either does not recognize the request method, or it lacks the ability to fulfill the request.
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @return void
	 **/
	function notimplemented ($msg = 'Not Implemented') {
		header('Content-Type: text/plain');
		status_header('501');
		echo $msg;
		ShoppRemoteAPIServer::done();
	}

	/**
	 * Bad Request (400)
	 *
	 * The request cannot be fulfilled due to bad syntax.
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @return void
	 **/
	function badrequest () {
		header('Content-Type: text/plain');
		status_header('400');
		ShoppRemoteAPIServer::done();
	}

	/**
	 * Length Required (411)
	 *
	 * The request did not specify the length of its content, which is required by the requested resource.
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @return void
	 **/
	function lengthrequired () {
		header("HTTP/1.1 411 Length Required");
		header('Content-Type: text/plain');
		status_header('411');
		ShoppRemoteAPIServer::done();

	}

	/**
	 * Unsupported Media Type (415)
	 *
	 * The request entity has a media type which the server or resource does not support. For example, the client uploads an image as image/svg+xml, but the server requires that images use a different format.
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @return void
	 **/
	function invalidmedia () {
		header("HTTP/1.1 415 Unsupported Media Type");
		header('Content-Type: text/plain');
		ShoppRemoteAPIServer::done();

	}

	/**
	 * Forbidden (403)
	 *
	 * Genesis allowed is not! Is resource forbidden!
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @param string $reason The message/reason for the status code
	 * @return void
	 **/
	function forbidden ($reason = 'Access denied.') {
		header('Content-Type: text/plain');
		status_header('403');
		echo $reason;
		ShoppRemoteAPIServer::done();
	}

	/**
	 * Not Found (404)
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @return void
	 **/
	function notfound () {
		header('Content-Type: text/plain');
		status_header('404');
		ShoppRemoteAPIServer::done();
	}

	/**
	 * Method Not Allowed (405)
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @param string $allow The allowed methods (GET,POST,PUT,etc)
	 * @return void
	 **/
	function notallowed ( $allow ) {
		header('Allow: ' . join(',', $allow));
		status_header('405');
		ShoppRemoteAPIServer::done();
	}

	/**
	 * Found (302)
	 *
	 * Redirect/Moved temporarily
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @param string $url The redirect URL
	 * @return void
	 **/
	function found ( $url ) {
		$escaped_url = esc_attr($url);
		$content = <<<EOD
<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
<html>
  <head>
    <title>302 Found</title>
  </head>
<body>
  <h1>Found</h1>
  <p>The document has moved <a href="$escaped_url">here</a>.</p>
  </body>
</html>

EOD;
		header('HTTP/1.1 302 Moved');
		header('Content-Type: text/html');
		header('Location: ' . $url);
		echo $content;
		ShoppRemoteAPIServer::done();

	}

	/**
	 * Client Error (400)
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @param string $msg The message/reason for the status code
	 * @return void
	 **/
	function clienterror($msg = 'Client Error') {
		header('Content-Type: text/plain');
		status_header('400');
		ShoppRemoteAPIServer::done();

	}

	/**
	 * Created (201)
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @param int $id Database record ID
	 * @param string $content The content to output
	 * @param string $type The type of the content
	 * @return void
	 **/
	function created ($id, $content, $type) {
		$url = false;	// @todo Implement status 201 (created) content URL
		$edit = false;	// @todo Implement status 201 (created) edit URL
		$mime = false;	// @todo Implement status 201 (created) mimetype
		header("Content-Type: $mime");
		if (isset($url)) header('Content-Location: ' . $url);
		header('Location: ' . $edit);
		status_header('201');
		echo $content;
		ShoppRemoteAPIServer::done();
	}

	/**
	 * Unauthorized (401)
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @param string $msg The message/reason for the status code
	 * @return void
	 **/
	function unauthorized ( $msg = 'Unauthorized' ) {
		nocache_headers();
		header('WWW-Authenticate: Basic realm="Shopp Remote API"');
		header("HTTP/1.1 401 $msg");
		header('Status: 401 ' . $msg);
		header('Content-Type: text/html');
		$content = <<<EOD
<!DOCTYPE html>
<html>
  <head>
    <title>401 Unauthorized</title>
  </head>
<body>
    <h1>401 Unauthorized</h1>
    <p>$msg</p>
  </body>
</html>

EOD;
		echo $content;
		exit();
	}

	/**
	 * Calls the request handler
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @return mixed Result for the request
	 **/
	function call ($response,$resource,$query) {
		list($call,$id) = $resource;
		return apply_filters('shopp_remoteapi_request_'.sanitize_key($call),$response,$resource,$query);
	}


} // END class Remote

/**
 * ShoppRESTService
 *
 * Implements a RESTful web service for Shopp through WordPress
 *
 * @author Jonathan Davis
 * @since 1.3
 * @package shopp
 * @subpackage remoteapi
 **/
class ShoppRESTService extends ShoppRemoteAPIServiceFramework {

	function __construct() {
		parent::__construct();
		add_filter('shopp_remoteapi_service_rest_get',array(__CLASS__,'get'),10,3);
		add_filter('shopp_remoteapi_service_rest',array(__CLASS__,'simplify'),10,3);
		add_filter('shopp_remoteapi_service_rest',array(__CLASS__,'encode'),10,3);
	}

	static public function encode ($response) {
		if ( is_null($response) ) return null;
		return json_encode($response);
	}

	/**
	 * Cleans up Shopp DatabaseObject internal meta data
	 *
	 * Shopp DatabaseObject uses internal object instance properties as stateful
	 * information for the object's methods. When retrieving these objects over
	 * the Remote API, this information is unnecessary wire weight and provides
	 * no functional support for the remote system.
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @param mixed $entry The current response entries to be encoded
	 * @return string The simplified object data
	 **/
	static public function simplify ($entry) {

		$array = false;
		if ( is_object($entry) ) $array = get_object_vars($entry);
		elseif ( is_array($entry) ) $array = $entry;

		// Recursively simplify entries in arrays or object properties
		if ( is_array($array) ) {
			foreach ($array as $id => $record)
				$array[$id] = self::simplify($record);
			$entry = $array;
		}

		// If this is a DatabaseObject, use the json method to strip out the internal object meta
		if ( is_subclass_of($entry,'DatabaseObject',false) )
			$entry = $entry->json();

		return $entry;
	}

	static public function get ($response,$resource,$query) {
		return self::call($response,$resource,$query);
	}

}
