#!/usr/bin/php -q
<?php
/**
 * ShoppTests
 *
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited,  6 October, 2009
 * @package
 **/

/**
 * Initialize
 **/


require('wp-config.php');

if (!defined('SHOPP_SQL_DATAFILE')) define('SHOPP_SQL_DATAFILE','shopptest.sql');
system('mysql -u '.DB_USER.' --password='.DB_PASSWORD.' '.DB_NAME.' < '.SHOPP_SQL_DATAFILE);

require_once(ABSPATH.'wp-settings.php');

error_reporting(E_ALL);
ini_set('display_errors', true);

// require_once('PHPUnit.php');
require_once('PHPUnit/Autoload.php');
require_once('xHTMLvalidator.php');

// Abstraction Layer
class ShoppTestCase extends PHPUnit_Framework_TestCase {

	function __construct () {}

	protected $backupGlobals = FALSE;
	var $_time_limit = 120; // max time in seconds for a single test function
	var $validator = false;

	function setUp() {
		// error types taken from PHPUnit_Framework_TestResult::run
		$this->_phpunit_err_mask = E_USER_ERROR | E_NOTICE | E_STRICT;
		$this->_old_handler = set_error_handler(array(&$this, '_error_handler'));
		if (is_null($this->_old_handler)) {
			restore_error_handler();
		}

		set_time_limit($this->_time_limit);
		$db =& DB::get();
		if (!$db->dbh) $db->connect(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST);
	}

	function tearDown() {
		global $Shopp;
		// $Shopp->Catalog = false;
		// $Shopp->Category = false;
		// $Shopp->Product = false;
		if (!is_null($this->_old_handler)) {
			restore_error_handler();
		}
	}

	function assertValidMarkup ($string) {
		$validator = new xHTMLvalidator();
		$this->assertTrue($validator->validate($string),
			'Failed to validate: '.$validator->showErrors()."\n$string");
	}

	/**
	 * Treat any error, which wasn't handled by PHPUnit as a failure
	 */
	function _error_handler($errno, $errstr, $errfile, $errline) {
		// @ in front of statement
		if (error_reporting() == 0) {
			return;
		}
		// notices and strict warnings are passed on to the phpunit error handler but don't trigger an exception
		if ($errno | $this->_phpunit_err_mask) {
			PHPUnit_Util_ErrorHandler::handleError($errno, $errstr, $errfile, $errline);
		}
		// warnings and errors trigger an exception, which is included in the test results
		else {
			//TODO: we should raise custom exception here, sth like WP_PHPError
			throw new PHPUnit_Framework_Error(
				$errstr,
				$errno,
				$errfile,
				$errline,
				$trace
			);
		}
	}

	function _current_action() {
		global $wp_current_action;
		if (!empty($wp_current_action))
			return $wp_current_action[count($wp_current_action)-1];
	}

	function _query_filter($q) {
		$now = microtime(true);
		$delta = $now - $this->_q_ts;
		$this->_q_ts = $now;

		$bt = debug_backtrace();
		$caller = '';
		foreach ($bt as $trace) {
			if (strtolower(@$trace['class']) == 'wpdb')
				continue;
			elseif (strtolower(@$trace['function']) == __FUNCTION__)
				continue;
			elseif (strtolower(@$trace['function']) == 'call_user_func_array')
				continue;
			elseif (strtolower(@$trace['function']) == 'apply_filters')
				continue;

			$caller = $trace['function'];
			break;
		}

		#$this->_queries[] = array($caller, $q);
		$delta = sprintf('%0.6f', $delta);
		echo "{$delta} {$caller}: {$q}\n";
		@++$this->_queries[$caller];
		return $q;
	}

	// call these to record and display db queries
	function record_queries() {
		#$this->_queries = array();
		#$this->_q_ts = microtime(true);
		#add_filter('query', array(&$this, '_query_filter'));
		#define('SAVEQUERIES', true);
		global $wpdb;
		$wpdb->queries = array();
	}

	function dump_queries() {
		#remove_filter('query', array(&$this, '_query_filter'));
		#asort($this->_queries);
		#dmp($this->_queries);
		#$this->_queries = array();
		global $wpdb;
		dmp($wpdb->queries);
	}

	function dump_query_summary() {
		$out = array();
		global $wpdb;
		foreach ($wpdb->queries as $q) {
				@$out[$q[2]][0] += 1; // number of queries
				@$out[$q[2]][1] += $q[1]; // query time
		}
		dmp($out);
	}

	// pretend that a given URL has been requested
	function http($url) {
		// note: the WP and WP_Query classes like to silently fetch parameters
		// from all over the place (globals, GET, etc), which makes it tricky
		// to run them more than once without very carefully clearing everything
		$_GET = $_POST = array();
		foreach (array('query_string', 'id', 'postdata', 'authordata', 'day', 'currentmonth', 'page', 'pages', 'multipage', 'more', 'numpages', 'pagenow') as $v)
			unset($GLOBALS[$v]);
		$parts = parse_url($url);
		if (isset($parts['scheme'])) {
			$req = $parts['path'];
			if (isset($parts['query'])) {
				$req .= '?' . $parts['query'];
				// parse the url query vars into $_GET
				parse_str($parts['query'], $_GET);
			}
		}
		else {
			$req = $url;
		}

		$_SERVER['REQUEST_URI'] = $req;
		unset($_SERVER['PATH_INFO']);

		wp_cache_flush();
		unset($GLOBALS['wp_query'], $GLOBALS['wp_the_query']);
		$GLOBALS['wp_the_query'] = new WP_Query();
		$GLOBALS['wp_query'] = $GLOBALS['wp_the_query'];
		$GLOBALS['wp'] = new WP();

		// clean out globals to stop them polluting wp and wp_query
		foreach ($GLOBALS['wp']->public_query_vars as $v) {
			unset($GLOBALS[$v]);
		}
		foreach ($GLOBALS['wp']->private_query_vars as $v) {
			unset($GLOBALS[$v]);
		}

		if (isset($parts['query'])) $GLOBALS['wp']->main($parts['query']);
	}

	// various helper functions for creating and deleting posts, pages etc

	// as it suggests: delete all posts and pages
	function _delete_all_posts() {
		global $wpdb;

		$all_posts = $wpdb->get_col("SELECT ID from {$wpdb->posts}");
		if ($all_posts) {
			foreach ($all_posts as $id)
				wp_delete_post($id);
		}
	}

	// insert a given number of trivial posts, each with predictable title, content and excerpt
	function _insert_quick_posts($num, $type='post', $more = array()) {
		for ($i=0; $i<$num; $i++)
			$this->post_ids[] = wp_insert_post(array_merge(array(
				'post_author' => $this->author->ID,
				'post_status' => 'publish',
				'post_title' => "{$type} title {$i}",
				'post_content' => "{$type} content {$i}",
				'post_excerpt' => "{$type} excerpt {$i}",
				), $more));
	}

	function _insert_quick_comments($post_id, $num=3) {
		for ($i=0; $i<$num; $i++) {
			wp_insert_comment(array(
				'comment_post_ID' => $post_id,
				'comment_author' => "Commenter $i",
				'comment_author_url' => "http://example.com/$i/",
				'comment_approved' => 1,
				));
		}
	}

	// insert a given number of trivial pages, each with predictable title, content and excerpt
	function _insert_quick_pages($num) {
		$this->_insert_quick_posts($num, 'page');
	}

	// import a WXR file
	function _import_wp($filename, $users = array(), $fetch_files = true) {
		$importer = new WP_Import();
		$path = realpath($filename);
		assert('!empty($path)');
		assert('is_file($path)');

		$author_in = array();
		$user_create = array();
		$userselect = array();
		foreach ($users as $k=>$v)
			$userselect[$k] = '0';
		$i=0;
		foreach ($users as $author => $user) {
			$author_in[$i] = $author;
			$user_create[$i] = $user;
			$i++;
		}

		$_POST = array('author_in' => $author_in, 'user_create'=>$user_create, 'user_select'=>$userselect);

		// this is copied from WP_Import::import()
		// we can't call that function directly because it expects a file ID
		$file = realpath($filename);
		global $wpdb;
		$qcount_start = $wpdb->num_queries;
		#add_filter('query', 'dmp_filter');
		if (is_callable(array(&$importer, 'import_file'))) {
			$importer->fetch_attachments = $fetch_files;
			$importer->import_file($file);
		}
		else {
			$importer->file = $file;
			$importer->get_authors_from_post();
			$importer->get_entries(array(&$importer, 'process_post'));
			$importer->process_categories();
			$importer->process_tags();
			$importer->process_posts();
		}
		#remove_filter('query', 'dmp_filter');
		$qcount_delta = $wpdb->num_queries - $qcount_start;
		dmp("import query count: $qcount_delta");
		$_POST = array();
	}

	// Generate PHP source code containing unit tests for the current blog contents.
	// When run, the tests will check that the content of the blog exactly matches what it is now,
	// with a separate test function for each post.
	function _generate_post_content_test(&$posts, $separate_funcs = true) {
		global $wpdb;

		$out = '';
		if (!$separate_funcs)
			$out .= "\n\tfunction test_all_posts() {\n";
		foreach ($posts as $i=>$post) {
			if ($separate_funcs)
				$out .= "\n\tfunction test_post_{$i}() {\n";
			$out .= "\t\t\$post = \$this->posts[{$i}];\n";
			foreach (array_keys(get_object_vars($post)) as $field) {
				if ($field == 'guid') {
					if ($post->post_type == 'attachment')
						$out .= "\t\t".'$this->assertEquals(wp_get_attachment_url($post->ID), $post->guid);'."\n";
					else
						$out .= "\t\t".'$this->assertEquals("'.addcslashes($post->guid, "\$\n\r\t\"\\").'", $post->guid);'."\n";
				}
				elseif ($field == 'post_parent' and !empty($post->post_parent)) {
					$parent_index = 0;
					foreach (array_keys($posts) as $index) {
						if ( $posts[$index]->ID == $post->post_parent ) {
							$parent_index = $index;
							break;
						}
					}
					$out .= "\t\t".'$this->assertEquals($this->posts['.$parent_index.']->ID, $post->post_parent);'."\n";
				}
				elseif ($field == 'post_author')
					$out .= "\t\t".'$this->assertEquals(get_profile(\'ID\', \''.($wpdb->get_var("SELECT user_login FROM {$wpdb->users} WHERE ID={$post->post_author}")).'\'), $post->post_author);'."\n";
				elseif ($field != 'ID')
					$out .= "\t\t".'$this->assertEquals("'.addcslashes($post->$field, "\$\n\r\t\"\\").'", $post->'.$field.');'."\n";
			}
			$cats = wp_get_post_categories($post->ID, array('fields'=>'all'));
			$out .= "\t\t".'$cats = wp_get_post_categories($post->ID, array("fields"=>"all"));'."\n";
			$out .= "\t\t".'$this->assertEquals('.count($cats).', count($cats));'."\n";
			if ($cats) {
				foreach ($cats as $j=>$cat) {
					$out .= "\t\t".'$this->assertEquals(\''.addslashes($cat->name).'\', $cats['.$j.']->name);'."\n";
					$out .= "\t\t".'$this->assertEquals(\''.addslashes($cat->slug).'\', $cats['.$j.']->slug);'."\n";
				}
			}

			$tags = wp_get_post_tags($post->ID);
			$out .= "\t\t".'$tags = wp_get_post_tags($post->ID);'."\n";
			$out .= "\t\t".'$this->assertEquals('.count($tags).', count($tags));'."\n";
			if ($tags) {
				foreach ($tags as $j=>$tag) {
					$out .= "\t\t".'$this->assertEquals(\''.addslashes($tag->name).'\', $tags['.$j.']->name);'."\n";
					$out .= "\t\t".'$this->assertEquals(\''.addslashes($tag->slug).'\', $tags['.$j.']->slug);'."\n";
				}
			}

			$meta = $wpdb->get_results("SELECT DISTINCT meta_key FROM {$wpdb->postmeta} WHERE post_id = $post->ID");
			#$out .= "\t\t".'$this->assertEquals('.count($postmeta).', count($meta));'."\n";
			foreach ($meta as $m) {
				#$out .= "\t\t".'$meta = get_post_meta($post->ID, \''.addslashes($m->meta_key).'\', true);'."\n";
				$out .= "\t\t".'$this->assertEquals('.var_export(get_post_meta($post->ID, $m->meta_key, false), true).', get_post_meta($post->ID, \''.addslashes($m->meta_key).'\', false));'."\n";
			}


			$comments = $wpdb->get_results($wpdb->prepare("SELECT * FROM $wpdb->comments WHERE comment_post_ID = %d ORDER BY comment_date DESC", $post->ID));

			$out .= "\t\t".'$comments = $wpdb->get_results($wpdb->prepare("SELECT * FROM $wpdb->comments WHERE comment_post_ID = %d ORDER BY comment_date DESC", $post->ID));'."\n";
			$out .= "\t\t".'$this->assertEquals('.count($comments).', count($comments));'."\n";
			foreach ($comments as $k=>$comment) {
				$out .= "\t\t".'$this->assertEquals(\''.addslashes($comment->comment_author).'\', $comments['.$k.']->comment_author);'."\n";
				$out .= "\t\t".'$this->assertEquals(\''.addslashes($comment->comment_author_email).'\', $comments['.$k.']->comment_author_email);'."\n";
				$out .= "\t\t".'$this->assertEquals(\''.addslashes($comment->comment_author_url).'\', $comments['.$k.']->comment_author_url);'."\n";
				$out .= "\t\t".'$this->assertEquals(\''.addslashes($comment->comment_author_IP).'\', $comments['.$k.']->comment_author_IP);'."\n";
				$out .= "\t\t".'$this->assertEquals(\''.addslashes($comment->comment_date).'\', $comments['.$k.']->comment_date);'."\n";
				$out .= "\t\t".'$this->assertEquals(\''.addslashes($comment->comment_date_gmt).'\', $comments['.$k.']->comment_date_gmt);'."\n";
				$out .= "\t\t".'$this->assertEquals(\''.addslashes($comment->comment_karma).'\', $comments['.$k.']->comment_karma);'."\n";
				$out .= "\t\t".'$this->assertEquals(\''.addslashes($comment->comment_approved).'\', $comments['.$k.']->comment_approved);'."\n";
				$out .= "\t\t".'$this->assertEquals(\''.addslashes($comment->comment_agent).'\', $comments['.$k.']->comment_agent);'."\n";
				$out .= "\t\t".'$this->assertEquals(\''.addslashes($comment->comment_type).'\', $comments['.$k.']->comment_type);'."\n";
				$out .= "\t\t".'$this->assertEquals(\''.addslashes($comment->comment_parent).'\', $comments['.$k.']->comment_parent);'."\n";
				$out .= "\t\t".'$this->assertEquals(\''.addslashes($comment->comment_user_id).'\', $comments['.$k.']->comment_user_id);'."\n";
			}


			if ($separate_funcs)
				$out .= "\t}\n\n";
			else
				$out .= "\n\n";
		}
		if (!$separate_funcs)
			$out .= "\t}\n\n";
		return $out;
	}

	/**
	 * Drops all tables from the WordPress database
	 */
	function _drop_tables() {
		global $wpdb;
		$tables = $wpdb->get_col('SHOW TABLES;');
		foreach ($tables as $table)
			$wpdb->query("DROP TABLE IF EXISTS {$table}");
	}

	function _dump_tables($table /*, table2, .. */) {
		$args = func_get_args();
		$table_list = join(' ', $args);
		system('mysqldump -u '.DB_USER.' --password='.DB_PASSWORD.' -cqnt '.DB_NAME.' '.$table_list);
	}

	function _load_sql_datafile($file) {
		$lines = file($file);

		global $wpdb;
		foreach ($lines as $line) {
			if ( !trim($line) or preg_match('/^-- /', $line) )
				continue;
			$wpdb->query($line);
		}
	}

	// add a user of the specified type
	function _make_user($role = 'administrator', $user_login = '', $pass='', $email='') {
		if (!$user_login)
			$user_login = rand_str();
		if (!$pass)
			$pass = rand_str();
		if (!$email)
			$email = rand_str().'@example.com';

		// we're testing via the add_user()/edit_user() functions, which expect POST data
		$_POST = array(
			'role' => $role,
			'user_login' => $user_login,
			'pass1' => $pass,
			'pass2' => $pass,
			'email' => $email,
		);

		$this->user_ids[] = $id = add_user();

		$_POST = array();
		return $id;
	}

} // end ShoppTestCase class

function shopp_run_tests($classes, $classname='') {
	$suite = new PHPUnit_Framework_TestSuite();
	foreach ($classes as $testcase)
	if (!$classname or strtolower($testcase) == strtolower($classname)) {
		$suite->addTestSuite($testcase);
	}

	#return PHPUnit::run($suite);
	$result = new PHPUnit_Framework_TestResult;
	require_once('PHPUnit/TextUI/ResultPrinter.php');
	$printer = new PHPUnit_TextUI_ResultPrinter(NULL,true,true);
	$result->addListener($printer);
	return array($suite->run($result), $printer);
}

function get_all_test_cases() {
	$test_classes = array();
	$skipped_classes = explode(',',SHOPP_SKIP_TESTS);
	$all_classes = get_declared_classes();
	// only classes that extend ShoppTestCase and have names that don't start with _ are included
	foreach ($all_classes as $class)
		if ($class{0} != '_' && is_descendent_class('ShoppTestCase', $class) && !in_array($class,$skipped_classes))
			$test_classes[] = $class;
	return $test_classes;
}

function is_descendent_class($parent, $class) {

	$ancestor = strtolower(get_parent_class($class));

	while ($ancestor) {
		if ($ancestor == strtolower($parent)) return true;
		$ancestor = strtolower(get_parent_class($ancestor));
	}

	return false;
}

function get_shopp_test_files($dir) {
	$tests = array();
	$dh = opendir($dir);
	while (($file = readdir($dh)) !== false) {
		if ($file{0} == '.') continue;
		$path = realpath($dir . DIRECTORY_SEPARATOR . $file);
		$fileparts = pathinfo($file);
		if (is_file($path) and $fileparts['extension'] == 'php')
			$tests[] = $path;
		elseif (is_dir($path))
			$tests = array_merge($tests, get_shopp_test_files($path));
	}
	closedir($dh);

	return $tests;
}

function shopptests_print_result($printer, $result) {
	$printer->printResult($result, timer_stop());
	/*
	echo $result->toString();
	echo "\n", str_repeat('-', 40), "\n";
	if ($f = intval($result->failureCount()))
		echo "$f failures\n";
	if ($e = intval($result->errorCount()))
		echo "$e errors\n";

	if (!$f and !$e)
		echo "PASS (".$result->runCount()." tests)\n";
	else
		echo "FAIL (".$result->runCount()." tests)\n";
	*/
}

// Main Procedures
global $Shopp;
$db = DB::get();

if (defined('SHOPP_IMAGES_PATH')) $Shopp->Settings->registry['image_path'] = SHOPP_IMAGES_PATH;
if (defined('SHOPP_PRODUCTS_PATH')) $Shopp->Settings->registry['products_path'] = SHOPP_PRODUCTS_PATH;
if (!defined('SHOPP_SKIP_TESTS')) define('SHOPP_SKIP_TESTS','');

define('SHOPP_TESTS_DIR',dirname(__FILE__).'/tests');
$files = get_shopp_test_files(SHOPP_TESTS_DIR);
// $files = array(SHOPP_TESTS_DIR."/CartTotalsTests.php");
foreach ($files as $file) require_once($file);
$tests = get_all_test_cases();
list ($result, $printer) = shopp_run_tests($tests);
shopptests_print_result($printer,$result);

?>