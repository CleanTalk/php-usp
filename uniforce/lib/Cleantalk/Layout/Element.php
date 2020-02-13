<?php

namespace Cleantalk\Layout;

/**
 * Base class for setting's element.
 * @method string getName()
 *
 * @method $this setType( string $type )
 * @method $this setDisplay( string $string )
 * @method $this setCallback( string $string )
 * @method $this setHtml_before( string $string )
 * @method $this setHtml_after( string $string )
 * @method $this setJs_before( string $string )
 * @method $this setJs_after( string $string )
 */

class Element extends Settings {

	public $name;
	public $type;
	public $display;
	public $callback;
	public $html_before;
	public $html_after;
	public $js_before;
	public $js_after;

	public function __construct( $name, $type ) {
		$this->setName( $name );
		$this->setType( $type );
		parent::__construct();
		return $this;
	}

	public function draw( $out = '' ) {

		$out .= $this->js_before();
		$out .= $this->html_before();

		// Custom output for concrete element
		$out .= $this->callback
			? call_user_func( $this->callback )
			: $this->draw_element();

		$out .= $this->html_after();
		$out .= $this->js_after();

		return $out;

	}

	public function draw_children( $out = '' ){
		if($this->children){
			foreach ($this->children as $child){
				$out .= $child->draw();
			}
		}
		return $out;
	}

	/**
	 * @return string
	 */
	protected function draw_element(){
		return '';
	}

	public function html_before() {
		return $this->html_before ? $this->html_before : '';
	}

	public function html_after() {
		return $this->html_after ? $this->html_after : '';
	}

	public function js_before() {
		return $this->js_before ? $this->js_before : '';
	}

	public function js_after() {
		return $this->js_after ? $this->js_after : '';
	}

	/**
	 * @param mixed $name
	 */
	protected function setName( $name ) {
		$this->name = $name;
	}

}