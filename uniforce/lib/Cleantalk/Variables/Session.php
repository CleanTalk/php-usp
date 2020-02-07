<?php

namespace Cleantalk\Variables;

/**
 * Class Session
 * Safety handler for $_SESSION
 *
 * @usage \Cleantalk\Variables\Session::get( $name );
 *
 * @package Cleantalk\Variables
 */
class Session extends SuperGlobalVariables{

	static $instance;

	/**
	 * Gets given $_POST variable and save it to memory
	 * @param $name
	 *
	 * @return mixed|string
	 */
	protected function get_variable( $name ){

		// Return from memory. From $this->variables
		if(isset(static::$instance->variables[$name]))
			return static::$instance->variables[$name];

		if( function_exists( 'filter_input' ) )
			$value = filter_input( INPUT_SESSION, $name );

		if( empty( $value ) )
			$value = isset( $_SESSION[ $name ] ) ? $_SESSION[ $name ]	: '';

		// Remember for further calls
		static::getInstance()->remember_variable( $name, $value );

		return $value;
	}
}