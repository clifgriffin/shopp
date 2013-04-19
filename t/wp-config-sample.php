<?php
// ** MySQL settings ** //

// these will be used by the copy of wordpress being tested.
// wordpress/wp-config.php will be ignored.

// WARNING WARNING WARNING!
// ShoppTests will DROP ALL TABLES in the database named below.
// DO NOT use a production database or one that is shared with something else.

define('DB_NAME', 'putyourdbnamehere');    // The name of the database
define('DB_USER', 'usernamehere');     // Your MySQL username
define('DB_PASSWORD', 'yourpasswordhere'); // ...and password
define('DB_HOST', 'localhost');    // 99% chance you won't need to change this value
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');

// You can have multiple installations in one database if you give each a unique prefix
$table_prefix  = 'wp_';   // Only numbers, letters, and underscores please!

// Change this to localize WordPress.  A corresponding MO file for the
// chosen language must be installed to wp-content/languages.
// For example, install de.mo to wp-content/languages and set WPLANG to 'de'
// to enable German language support.
define('WPLANG', '');

// Set this to the full path to WordPress on the file system
define('ABSPATH','/absolute/path/to/wordpress/');
?>