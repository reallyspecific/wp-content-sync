<?php

namespace InfinityScroll;

abstract class Singleton {

	protected static $instance = null;

	abstract public function __construct();

	public static function &init() {
		if ( is_null( static::$instance ) ) {
			static::$instance = new static();
		}
		return static::get_instance();
	}

	public static function is_initialized() {
		return ! is_null( static::$instance );
	}

	public static function &get_instance() {
		return static::$instance;
	}

}