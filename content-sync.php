<?php
/**
 * Plugin Name: Content Sync Tool
 * Plugin URI: https://reallyspecific.com/software/content-sync
 * Update URI: https://reallyspecific.com/software/content-sync/updates.json
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

namespace ReallySpecific\WP_ContentSync;
use ReallySpecific\WP_Util as Util;
use ReallySpecific\WP_Util\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

require_once __DIR__ . "/util/load.php";

require_once __DIR__ . "/src/Client.php";
require_once __DIR__ . "/src/Integration.php";
require_once __DIR__ . "/src/Plugin.php";
require_once __DIR__ . "/src/Settings.php";
require_once __DIR__ . "/src/Server.php";

Util\class_loader('Plugin');

function load() {
	load_plugin_textdomain( 'content-sync', false, __DIR__ . '/languages' );
	$plugin = new Plugin( [
		'slug' => 'content-sync',
		'file' => __FILE__,
	] );
	$plugin->attach_service( 'init', 'server', __NAMESPACE__ . '\Server' );
	if ( is_admin() ) {
		$plugin->attach_service( 'init', 'client', __NAMESPACE__ . '\Client' );
	}
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\load' );
