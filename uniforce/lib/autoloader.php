<?php

spl_autoload_register( 'ct_usp_autoloader' );

/**
 * Autoloader for \Cleantalk\* classes
 *
 * @param string $class
 *
 * @return void
 */
function ct_usp_autoloader( $class ){
	
	// Register class auto loader
	// Custom modules
	if( strpos( $class, 'Cleantalk\\USP' ) !== false ){
		$class = str_replace( '\\', DIRECTORY_SEPARATOR, $class );
		$class_file = __DIR__ . DIRECTORY_SEPARATOR . $class . '.php';
		if( file_exists( $class_file ) ){
			require_once( $class_file );
		}
	}
}