<?php

namespace ReallySpecific\ContentSync;

use ReallySpecific\Util;
use ReallySpecific\ContentSync\Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

Util\maybe_load( 'Singleton' );

class Plugin extends Util\Singleton {

	protected static $instance = null;

	function __construct() {
		if ( static::is_initialized() ) {
			return;
		}
		add_action( 'init', [ __NAMESPACE__ . '\Server', 'init' ] );
		if ( is_admin() ) {
			load_plugin_textdomain( 'content-sync', false, basename( dirname( __FILE__ ) ) . '/languages' );
			add_action( 'admin_menu', __NAMESPACE__ . '\Settings\install' );
			add_action( 'init', __NAMESPACE__ . '\Integration\install' );
		}
	}

}