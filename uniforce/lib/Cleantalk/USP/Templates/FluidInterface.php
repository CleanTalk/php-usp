<?php

namespace Cleantalk\USP\Templates;

trait FluidInterface {

	/**
	 * Fluid interface
	 *
	 * @param $name
	 * @param $arguments
	 *
	 * @return $this
	 */
	public function __call( $name, $arguments ) {

		// Get
		if ( stripos( $name, 'get' ) === 0 ) {
			$var = lcfirst( substr( $name, 3) );
			return $this->$var;
		}

		// Set
		if ( stripos( $name, 'set' ) === 0 ) {
			$var = lcfirst( substr( $name, 3) );
			$this->$var = $arguments[0];
			return $this;
		}

		// Add
		if ( stripos( $name, 'add_' ) === 0 ) {
			$namespace = preg_replace('/^(.*)?(\\\\.*)$/', '$1', __CLASS__ );
			$class = ucfirst( substr( $name, 4) );
			$class = '\\' . $namespace . '\\' . $class;
			$child = new $class( isset( $arguments[0] ) ? $arguments[0] : ''  );
			$this->addChild ( $child );
			return $child;
		}

		// Save
		if ( stripos( $name, 'save' ) === 0 ) {
			$var = lcfirst( substr( $name, 4) );
			// @todo support for camelNotation with many words. Example saveRemoteCalls
			$this->save( $var );
			return $this;
		}
	}

}