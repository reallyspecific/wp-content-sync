<?php
/**
 * Plugin Name: Content Sync Tool
 * Plugin URI: https://reallyspecific.com/plugins/content-sync
 * Description: Copy content from one site to another.
 * Version: 1.0
 * Author: Really Specific Software
 * Author URI: https://www.reallyspecific.com
 * License: GPL3
 *
 * Text Domain: content-sync
 * Domain Path: /languages/
 *
 * Requires PHP: 8.1
 */

namespace ReallySpecific\ContentSync;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

require_once __DIR__ . "/util/load.php";

require_once __DIR__ . "/src/Client.php";
require_once __DIR__ . "/src/Integration.php";
require_once __DIR__ . "/src/Plugin.php";
require_once __DIR__ . "/src/Settings.php";
require_once __DIR__ . "/src/Server.php";

function load() {
	load_plugin_textdomain( 'content-sync', false, __DIR__ . '/languages' );
	new Plugin( __DIR__ );
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\load' );
