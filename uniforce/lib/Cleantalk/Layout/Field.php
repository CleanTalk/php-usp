<?php

namespace Cleantalk\Layout;

/**
 * From \Cleantalk\Layout\Element
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
 * @method $this setInput_type( string $string )
 * @method $this setDef_class( string $string )
 * @method $this setTitle_first( string $string )
 * @method $this setClass( string $string )
 * @method $this setParent( string $string )
 * @method $this setChild_fields( string $string )
 */

class Field extends Element {

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
	 * <input type = "?">
	 * @var string
	 */
	public $input_type;

	/**
	 * Default CSS class
	 * @var string
	 */
	public $def_class;

	/**
	 * Output title before element
	 * @var string
	 */
	public $title_first;

	/**
	 * Additional CSS class
	 * @var string
	 */
	public $class;

	/**
	 * Prent fields
	 * @var string
	 */
	public $parent_field = null;

	/**
	 * Child fields
	 * @var string
	 */
	public $child_fields;

	public function __construct( $name ){

		$this->setTitle( str_replace( '_', ' ', ucfirst( $name ) ) );

		// @todo default values for field

		$this->input_type = 'checkbox';
		$this->def_class = 'ctusp_field';

		return parent::__construct( $name, 'field' );
	}

	public function draw_element() {

		$out = '<div class="' . $this->def_class . ($this->class ? ' ' . $this->class : '') . ($this->parent_field ? ' ctusp_field--sub' : '') . '">';

			$draw_function = 'draw_element__' . $this->input_type;
			$out .= $this->$draw_function();

		$out .= '</div>';

		return $out;
	}

	public function draw_element__text() {

		$out = '';

		$name = $this->getName();

		if($this->title_first)
			$out .= '<label for="ctusp_field---' . $name . '" class="ctusp_field-title ctusp_field-title--' . $this->input_type . '">' . $this->title . '</label>&nbsp;';

		$out .= '<input type="text" id="ctusp_field---'. $name .'" name="ctusp_setting---'. $name .'" '
             . 'class="' . ($this->class ? $this->class : ''). '" '
		     .'value="'.($this->state->settings->$name ? $this->state->settings->$name : '').'" '
		     .( $this->parent_field && !$this->state->settings->{$this->parent_field} ? ' disabled="disabled"' : '')
		     .(!$this->child_fields ? '' : ' onchange="spbcSettingsDependencies([\''.implode("','",$this->child_fields).'\'])"')
		     .' />';

		if(!$this->title_first)
			$out .= '&nbsp;<label for="ctusp_field---' . $name . '" class="ctusp_field-title ctusp_field-title--' . $this->input_type . '">' . $this->title . '</label>';

		$out .= $this->description
			?'<div class="ctusp_field-description">'. $this->description .'</div>'
			: '';

		return $out;
	}

	public function draw_element__checkbox() {

		$name = $this->getName();

		$out = '<input type="checkbox" id="ctusp_field---' . $this->getName() . '" name="ctusp_setting---[' . $this->getName() . ']" value="1" '
		     .($this->state->settings->$name == '1' ? ' checked' : '')
		       .( $this->parent_field && !$this->state->settings->{$this->parent_field} ? ' disabled="disabled"' : '')
		     .($this->child_fields ? '' : ' onchange="spbcSettingsDependencies([\''.implode("','",$this->child_fields).'\'])"')
		     .' />';
		$out .= isset($this->title)
			? '<label for="ctusp_setting---'.$this->getName().'" class="ctusp_field-title ctusp_field-title--'.$this->input_type.'">'.$this->title.'</label>'
			: '';
		$out .= $this->description
			?'<div class="ctusp_field-description">'. $this->description .'</div>'
			: '';

		return $out;
	}

}