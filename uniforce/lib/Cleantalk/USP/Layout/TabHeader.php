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
 *
 * // Local
 * @method $this setTitle( string $string )
 */

class TabHeader extends Element {

	/**
	 * Display title
	 * @var string
	 */
	public $title;

	/**
	 * Flag. Determs if the tab is active by default.
	 * Only one tab could be active.
	 * @var boolean
	 */
	public $active;

	/**
	 * Path to display icon
	 * @var string
	 */
	public $icon;

	public function __construct( $name ){

		$this->setTitle( str_replace( '_', ' ', ucfirst( $name ) ) );

		// @todo default values for tab

		return parent::__construct( $name, 'tab_header' );
	}

	protected function draw_element() {
		echo '<h2 class="ctusp_tab_navigation-title ctusp_tab_navigation-title---'. $this->getName() .' '. ($this->active ? 'ctusp_tab_navigation-title--active' : '') . '">'
		       . '<i class="'. ( $this->icon ? $this->icon : 'icon-search' ) .'"></i>'
		       . $this->title
		       . '</h2>';
	}

	/**
	 *
	 */
	public function setActive() {
		$this->active = true;
	}

}