<?php

namespace Cleantalk\USP\Layout;

/**
 * From \Cleantalk\USP\Layout\Element
 * @method $this setDisplay( string $string )
 * @method $this setCallback( string $string )
 * @method $this setHtml_before( string $string )
 * @method $this setHtml_after( string $string )
 * @method $this setJs_before( string $string )
 * @method $this setJs_after( string $string )
 * @method $this setDeafult_css_class( string $string )
 *
 * * // Local
 * @method $this setHtml( string $string )
 */


class Plain extends Element {

	/**
	 * Display title
	 * @var string
	 */
	public $html = '';

	/**
	 * Plain constructor.
	 *
	 * @param $name
	 */
	public function __construct( $name ) {
		return parent::__construct( $name, 'plain' );
	}

	protected function draw_element() {
		echo $this->html;
	}

}