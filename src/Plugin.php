<?php

namespace ReallySpecific\ContentSync;

use ReallySpecific\Util;
use ReallySpecific\ContentSync\Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Util\maybe_load( 'Singleton' );

class Plugin  {

	private $root_path = null;

	private static $state = null;

	private $services = [];

	function __construct( $root_path = null ) {
		if ( static::$state ) {
			// plugin is already initialized, silently fail
			return;
		}
		static::$state = $this;

		$this->services['server'] = new Server();

		if ( is_admin() ) {
			add_action( 'admin_menu', __NAMESPACE__ . '\Settings\install' );
			add_action( 'init', __NAMESPACE__ . '\Integration\install' );
		}
		static::$root_path = $root_path ?: dirname( __DIR__ );
	}

	public function service( $name ) {
		return $this->services[ $name ];
	}

	public static function get_service( $name ) {
		return static::$state->service( $name );
	}

	public function get_root_path() {
		return $this->root_path;
	}

	public function get_url( $relative_path = null ) {
		return plugins_url( $relative_path, $this->get_root_path() . '/content-sync.php' );
	}

}