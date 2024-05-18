<?php

namespace InfinityScroll\ContentSync;

use InfinityScroll\Singleton;
use InfinityScroll\ContentSync\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'InfinityScroll\Singleton' ) ) {
	require_once __DIR__ . '/Singleton.php';
}

class Plugin extends Singleton {

	function __construct() {
		if ( static::is_initialized() ) {
			return;
		}
		add_action( 'admin_menu', 'InfinityScroll\ContentSync\Settings\install' );
	}

}