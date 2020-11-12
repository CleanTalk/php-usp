<?php

namespace Cleantalk\USP\Templates;

trait Singleton{

	public function __construct(){}
//	public function __wakeup(){}
	public function __clone(){}
	
	/**
	 * Constructor
	 *
	 * @param array $params
	 *
	 * @return $this
	 */
	public static function getInstance( ...$params ){
		if( ! isset( static::$instance ) ){
			static::$instance = new static();
			if( ! empty( $params) )
				static::$instance->init( ...$params );
		}
		return static::$instance;
	}
	
	/**
	 * Alternative constructor
	 *
	 * @param array $params
	 */
	private function init( ...$params ){
		//self::$instance = parent::construct();
	}
	
}