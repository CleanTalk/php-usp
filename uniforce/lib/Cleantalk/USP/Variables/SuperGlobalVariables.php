<?php

namespace Cleantalk\USP\Variables;

/**
 * Class ServerVariables
 * Safety handler for ${_SOMETHING}
 *
 * @depends \Cleantalk\USP\Common\Singleton
 *
 * @usage \Cleantalk\USP\Variables\{SOMETHING}::get( $name );
 *
 * @package Cleantalk\USP\Variables
 */
class SuperGlobalVariables{
	
	use \Cleantalk\USP\Templates\Singleton;
	
	static $instance;
	
	/**
	 * @var array Contains saved variables
	 */
	public $variables = [];

	/**
	 * Check if set of variables is exists
	 *
	 * @param mixed ...$names Names of global variables
	 *
	 * @return bool
	 */
	public static function is_set( ...$names ){
		$result = true;
		foreach ( $names as $name ){
			$result = $result && static::getInstance()->get_variable( $name ) !== '';
		}
		return $result;
	}

	/**
	 * Gets variable from ${_SOMETHING}
	 *
	 * @param string $name Variable name
	 *
	 * @return string
	 */
	public static function get( $name ){
		return static::getInstance()->get_variable( $name );
	}
	
	/**
	 * BLUEPRINT
	 * Gets given ${_SOMETHING} variable and seva it to memory
	 * @param $name
	 *
	 * @return mixed|string
	 */
	protected function get_variable( $name ){
		return true;
	}
	
	/**
	 * Save variable to $this->variables[]
	 *
	 * @param string $name
	 * @param string $value
	 */
	protected function remember_variable( $name, $value ){
		static::$instance->variables[$name] = $value;
	}
	
	/**
	 * Checks if variable contains given string
	 *
	 * @param string $var    Haystack to search in
	 * @param string $string Needle to search
	 *
	 * @return bool|int
	 */
	static function has_string( $var, $string ){
		return stripos( self::get( $var ), $string ) !== false;
	}
	
	/**
	 * Checks if variable equal to $param
	 *
	 * @param string $var   Variable to compare
	 * @param string $param Param to compare
	 *
	 * @return bool|int
	 */
	static function equal( $var, $param ){
		return self::get( $var ) == $param;
	}
}