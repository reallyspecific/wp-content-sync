<?php
/**
 * Plugin Name: Content Sync Tool
 * Plugin URI: https://infinity-scroll.io/plugins/content-sync
 * Description: Copy content from one site to another.
 * Version: 1.0
 * Author: Infinity Scroll
 * Author URI: https://www.infinityscroll.io
 * License: GPL2
 *
 * Text Domain: content-sync
 * Domain Path: /languages/
 *
 */

namespace InfinityScroll\ContentSync;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

require_once __DIR__ . "/src/Client.php";
require_once __DIR__ . "/src/Plugin.php";
require_once __DIR__ . "/src/Settings.php";
require_once __DIR__ . "/src/Server.php";

function init() {
	if ( ! is_admin() ) {
		return;
	}
	// Load plugin text domain
	load_plugin_textdomain( 'content-sync', false, basename( dirname( __FILE__ ) ) . '/languages' );
	Plugin::init();
}

add_action( 'init', __NAMESPACE__ . '\init' );
