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
     * @param null|string $validation_filter Filter name to run validation
     * @param null|string $sanitize_filter   Filter name to run sanitizing
	 *
	 * @return string
	 */
	public static function get( $name, $validation_filter = null, $sanitize_filter = null  ){
	    
        $variable = static::getInstance()->get_variable( $name );
        
        if( $validation_filter && ! static::validation($variable, $validation_filter) ){
            return false;
        }
        
        if( $sanitize_filter ){
            $variable = static::sanitize($variable, $sanitize_filter);
        }
        
        return $variable;
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
    
    /**
     * Runs validation for input parameter
     *
     * Now contains filters: hash
     *
     * @param mixed $input   Input to validate
     * @param string $filter Validation filter name
     *
     * @return bool
     */
    public static function validation($input, $filter){
        
        switch( $filter ){
            
            // validation filter for hash
            case 'hash':
                return preg_match('#^[a-zA-Z0-9]{8,128}$#', $input) === 1;
                break;
        }
        
        return true;
    }
    
    /**
     * Runs sanitizing process for input
     *
     * Now contains no filters
     *
     * @param mixed $input   Input to sanitize
     * @param string $filter Sanitizing filter name
     *
     * @return bool
     */
    public static function sanitize($input, $filter){
        
        switch( $filter ){
            
            // XSS. Recursive.
            case 'xss':
                $input_filtered = preg_replace( '#[\'"].*?>.*?<#i', '', $input );
                return $input === $input_filtered
                    ? htmlspecialchars( $input_filtered )
                    : static::sanitize( $input_filtered, 'xss');
            
            // URL
            case 'url':
                return preg_replace( '#[^a-zA-Z0-9$\-_.+!*\'(),{}|\\^~\[\]`<>\#%";\/?:@&=.]#i', '', $input );
                
            default:
                $output = $input;
        }
        
        return $output;
    }
}