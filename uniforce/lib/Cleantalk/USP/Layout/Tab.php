<?php

namespace Cleantalk\USP\Layout;

/**
 * // Tree
 * @method \Cleantalk\USP\Layout\Group add_group( string $name )
 * @method \Cleantalk\USP\Layout\Plain add_plain( string $name )
 *
 * From \Cleantalk\USP\Layout\Element
 * @method $this setDisplay( string $string )
 * @method $this setCallback( string $string )
 * @method $this setHtml_before( string $string )
 * @method $this setHtml_after( string $string )
 * @method $this setJs_before( string $string )
 * @method $this setJs_after( string $string )
 *
 * // Local
 * @method $this setHeader( string $string )
 * @method $this setTitle( string $string )
 * @method $this setDescription( string $string )
 * @method $this setIcon( string $string )
 * @method $this setPreloader( string $string )
 * @method $this setAjax( string $string )
 */

class Tab extends Element {

	/**
	 * Tab Header
	 * @var TabHeader
	 */
	public $header;

	/**
	 * Display title
	 * @var string
	 */
	public $title;

	/**
	 * Display description
	 * @var string
	 */
	public $description;

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

	/**
	 * Preloader icon
	 * @var string
	 */
	public $preloader;

	/**
	 * Flag. Determs if the tab should load with AJAX
	 * @var boolean
	 */
	public $ajax;

	public function __construct( $name ){

		$this->setTitle( str_replace( '_', ' ', ucfirst( $name ) ) );

		// @todo default values for tab

		return parent::__construct( $name, 'tab' );
	}

	protected function draw_element() {

		echo '<div class="ctusp_tab ctusp_tab---' . $this->getName() . ($this->active ? ' ctusp_tab--active' : '') . '">';

			$this->draw_children();

		echo '</div>';
	}

	/**
	 * @param bool $active
	 *
	 * @return Tab
	 */
	public function setActive() {
		$this->active = true;
		$this->header->setActive();
		return $this;
	}

}