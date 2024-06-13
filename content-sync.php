<?php
/**
 * Plugin Name: Content Sync
 * Plugin URI: https://reallyspecific.com/software/content-sync
 * Update URI: https://reallyspecific.com/software/content-sync/updates.json
 * Description: Copy content from one site to another.
 * Version: 1.0
 * Author: Really Specific Software
 * Author URI: https://reallyspecific.com
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
require_once __DIR__ . "/src/Server.php";

Util\class_loader('Plugin');

function load() {
	$plugin = new Plugin( [
		'slug'        => 'content-sync',
		'file'        => __FILE__,
		'i18n_domain' => 'rs-content-sync',
	] );
	$plugin->attach_service(
		load_action:  'init',
		service_name: 'server',
		callback:     __NAMESPACE__ . '\Server',
	);
	$plugin->attach_service(
		load_action:  'init',
		service_name: 'client',
		callback:     __NAMESPACE__ . '\Client'
	);
	Integration\install( $plugin );
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\load' );
