<?php

namespace Cleantalk\USP\Layout;

/**
 * // Tree
 * @method \Cleantalk\USP\Layout\Field add_field( string $name )
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
 * * // Local
 * @method $this setTitle( string $string )
 * @method $this setDescription( string $string )
 * @method $this setIcon( string $string )
 */

class Group extends Element {

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
	 * Path to display icon
	 * @var string
	 */
	public $icon;

	public function __construct( $name ) {

		$this->setTitle( str_replace( '_', ' ', ucfirst( $name ) ) );

		// @todo default values for group

		return parent::__construct( $name, 'group' );
	}

	public function draw_element() {

		echo '<div class="ctusp_group'
		     . ' ' . 'ctusp_group' . '---' . $this->getName()
		     . '">';

			echo $this->title ? '<h3 class="ctusp_group-title">'. $this->title .'</h3>' : '';

			echo $this->draw_children();

		echo '</div>';
	}

}