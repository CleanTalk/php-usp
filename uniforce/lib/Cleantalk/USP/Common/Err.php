<?php

namespace Cleantalk\USP\Common;

/**
 * Class Err
 * Uses singleton template.
 * Errors handling
 *
 * @package Cleantalk
 */
class Err{
	
	private static $instance;
	private $errors = [];
	
	public function __construct(){}
    public function __wakeup(){}
    public function __clone(){}
	
	/**
	 * Constructor
	 */
	public static function getInstance(){
		if (!isset(self::$instance)) {
            self::$instance = new self;
            self::$instance->init();
		}
		return self::$instance;
	}
	
	/**
	 * Alternative constructor
	 */
	private function init(){
	
	}
	
	/**
	 * Adds new error
	 *
	 */
	public static function add(){
		self::getInstance()->errors[] = implode(': ', func_get_args());
		return self::$instance;
	}

	public function append( $string ){
		$this->errors[ count( $this->errors ) - 1 ] = $string . ': ' . end( self::getInstance()->errors );
	}
	
	public static function prepend( $string ){
		$str = array_pop( self::$instance->errors );
		array_push( self::$instance->errors, $string . ': ' . $str );
	}
	
	public static function get_last( $output_style = 'bool' ){
		$out = (bool) self::$instance->errors;
		if( $output_style === 'as_json'){
            $out = json_encode( array( 'error' => end( self::$instance->errors ) ), true );
        }
		if( $output_style === 'string'){
            $out = array( 'error' => end( self::$instance->errors ) );
        }
		return $out;
	}
	
	public static function get_all( $output_style = 'string' ){
		$out = self::$instance->errors;
		if( $output_style === 'as_json'){
            $out = json_encode( self::$instance->errors, true );
        }
		return $out;
	}
	
	public static function check(){
		return (bool)self::$instance->errors;
	}
	
	public static function check_and_output( $output_style = 'string' ){
		if(self::check())
			return self::get_last( $output_style );
		else
			return false;
	}
}