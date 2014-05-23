<?php
/**
 * Session.php
 *
 * Session management system
 *
 * @author Jonathan Davis
 * @copyright Ingenesis Limited, March 2008
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @subpackage shopplib
 * @since 1.0
 **/

defined( 'WPINC' ) || header( 'HTTP/1.1 403' ) & exit; // Prevent direct access

abstract class SessionObject {

	public $_table;
	public $session;
	public $ip;
	public $data;
	public $stash = 0;
	public $created;
	public $modified;
	public $path = '';

	public $secure = false;

	/**
	 * The session manager constructor
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	public function __construct () {
		if ( ! defined('SHOPP_SECURE_KEY') )
			define('SHOPP_SECURE_KEY','shopp_sec_'.COOKIEHASH);

		// Close out any early session calls
		if( session_id() ) session_write_close();

		if ( ! $this->handling() )
			trigger_error('The session handlers could not be initialized.',E_USER_NOTICE);
		else shopp_debug( 'Session started ' . str_repeat('-', 64) );

		register_shutdown_function('session_write_close');
	}

	/**
	 * Register session handlers
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	protected function handling () {
		return session_set_save_handler(
			array( $this, 'open' ),		// Open
			array( $this, 'close' ),	// Close
			array( $this, 'load' ),		// Read
			array( $this, 'save' ),		// Write
			array( $this, 'unload' ),	// Destroy
			array( $this, 'clean' )		// Garbage Collection
		);
	}

	/**
	 * Initializing routine for the session management.
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return boolean
	 **/
	public function open ( $path, $name ) {
		$this->path = $path;
		if ( defined('SHOPP_TEMP_PATH') ) $this->path = sanitize_path(realpath(SHOPP_TEMP_PATH));
        if ( ! is_dir($this->path) ) mkdir($this->path, 0777);

		if ( empty($this->session) ) $this->session = session_id();	// Grab our session id
		$this->ip = $_SERVER['REMOTE_ADDR'];						// Save the IP address making the request

		$this->clean();	// Clean up abandoned sessions

		if ( ! isset($_COOKIE[ SHOPP_SECURE_KEY ]) ) $this->securekey();
		return true;
	}

	/**
	 * Placeholder function as we are working with a persistant
	 * database as opposed to file handlers.
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return boolean
	 **/
	public function close () {
		return true;
	}

	/**
	 * Gets data from the session data table and loads Member
	 * objects into the User from the loaded data.
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return boolean
	 **/
	public function load ( $id ) {
		if ( is_robot() || empty($this->session) ) return true;

		$loaded = false;
		$query = "SELECT * FROM $this->_table WHERE session='$this->session'";

		if ( $result = sDB::query($query) ) {
			if ( '!' == substr($result->data,0,1) ) {
				$key = $_COOKIE[SHOPP_SECURE_KEY];

				if ( empty($key) && ! is_ssl() ) Shopp::redirect( Shopp::force_ssl( Shopp::raw_request_url(), true ) );

				$this->secured(true); // Maintain session security

				$readable = sDB::query("SELECT AES_DECRYPT('" .
										mysql_real_escape_string(
											base64_decode(
												substr($result->data, 1)
											)
										) . "','$key') AS data", 'auto', 'col', 'data');
				$result->data = $readable;

			}
			$this->ip = $result->ip;
			$this->data = unserialize($result->data);
			$this->stash = $result->stash;
			$this->created = sDB::mktime($result->created);
			$this->modified = sDB::mktime($result->modified);
			$loaded = true;

			do_action('shopp_session_loaded');
		} else {
			$now = current_time('mysql');
			if ( ! empty($this->session) )
				sDB::query("INSERT INTO $this->_table (session, ip, data, created, modified)
							VALUES ('$this->session','$this->ip','','$now','$now')");
		}

		do_action('shopp_session_load');

		// Read standard session data
		if ( @file_exists("$this->path/sess_$id") )
			return (string) @file_get_contents("$this->path/sess_$id");

		return $loaded;
	}

	/**
	 * Deletes the session data from the database, unregisters the
	 * session and releases all the objects.
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return boolean
	 **/
	public function unload () {
		if( empty($this->session) ) return false;

		if ( ! sDB::query("DELETE FROM $this->_table WHERE session='$this->session'") )
			trigger_error("Could not clear session data.");

		// Handle clean-up of file storage sessions
        if ( is_writable("$this->path/sess_$id") )
			@unlink($file);

		unset($this->session, $this->ip, $this->data);
		return true;
	}

	/**
	 * Save the session data to our session table in the database.
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return boolean
	 **/
	public function save ( $id, $session ) {

		// Don't update the session for prefetch requests (via <link rel="next" /> tags) currently FF-only
		if (isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] == "prefetch") return false;

		$data = sDB::escape( addslashes(serialize($this->data)) );

		if ($this->secured() && is_ssl()) {
			$key = isset($_COOKIE[SHOPP_SECURE_KEY])?$_COOKIE[SHOPP_SECURE_KEY]:'';
			if (!empty($key) && $key !== false) {
				shopp_debug('Cart saving in secure mode!');
				$secure = sDB::query("SELECT AES_ENCRYPT('$data','$key') AS data");
				$data = "!".base64_encode($secure->data);
			} else {
				return false;
			}
		}

		$now = current_time('mysql');
		$query = "UPDATE $this->_table SET ip='$this->ip',stash='$this->stash',data='$data',modified='$now' WHERE session='$this->session'";

		if ( ! sDB::query($query) )
			trigger_error("Could not save session updates to the database.");

		do_action('shopp_session_saved');


		// Save standard session data for compatibility
		if ( ! empty($session) )
			return false === file_put_contents("$this->path/sess_$id",$session) ? false : true;

		return true;
	}

	/**
	 * Garbage collection routine for cleaning up old and expired
	 * sessions.
	 *
	 * 1.3 Added support for shopping session cold storage
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * @version 1.3
	 *
	 * @return boolean
	 **/
	public function clean ( $lifetime = false ) {
		if ( empty($this->session) ) return false;

		$timeout = SHOPP_SESSION_TIMEOUT;
		$now = current_time('mysql');

		if ( ! sDB::query("DELETE FROM $this->_table WHERE $timeout < UNIX_TIMESTAMP('$now') - UNIX_TIMESTAMP(modified)") )
			trigger_error("Could not delete cached session data.");

		// Garbage collection for file-system sessions
		if( $dh = opendir($this->path) ) {

		    while( ( $file = readdir($dh) ) !== false ) {
		    	if ( false === strpos($file, 'sess_') ) continue;

		    	$file = $this->path . "/$file";

		        if ( filemtime($file) + $lifetime < time() && is_writable($file) ) {
			    	if ( @unlink($file) === false ) {
				    	break;
			    	}
		        }
		    }

		    closedir($dh);
		}

		return true;
	}

	/**
	 * Check or set the security setting for the session
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return boolean
	 **/
	public function secured ( $setting = null ) {
		if ( is_null($setting) ) return $this->secure;
		$this->secure = ($setting);

		shopp_debug( $this->secure ? 'Switching the session to secure mode.' : 'Switching the session to unsecure mode.' );

		return $this->secure;
	}

	/**
	 * Generate the session security key
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return string
	 **/
	private function securekey () {
		if ( ! is_ssl() ) return false;

		$expiration = time() + SHOPP_SESSION_TIMEOUT;
		if ( defined('SECRET_AUTH_KEY') && '' != SECRET_AUTH_KEY ) $key = SECRET_AUTH_KEY;
		else $key = md5( serialize($this->data) . time() );
		$content = hash_hmac('sha256', $this->session . '|' . $expiration, $key);

		$success = false;
		if ( version_compare(phpversion(), '5.2.0', 'ge') )
			$success = setcookie(SHOPP_SECURE_KEY, $content, 0, '/', '', true, true);
		else $success = setcookie(SHOPP_SECURE_KEY, $content, 0, '/', '', true);

		if ( $success ) return $content;
		else return false;
	}


}