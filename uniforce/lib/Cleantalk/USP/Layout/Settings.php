<?php

namespace Cleantalk\USP\Layout;

use Cleantalk\USP\Common\State;

/**
 * // Tree
 * @method \Cleantalk\USP\Layout\Plain add_plain( string $name )
 */

class Settings {

	use \Cleantalk\USP\Templates\Tree;

	private $tab_headers = array();
	private $tab_headers_output = false;

	public $state;

	public function __construct() {
		$this->state = State::getInstance();
	}

	public function add_tab( $name ){

		$tab = new Tab( $name );
		$tab_header = new TabHeader( $name );

		$this->addChild ( $tab );
		$this->tab_headers[] = $tab_header;

		$tab->header = $tab_header;

		return $tab;
	}

	public function draw() {

		ob_start();

		if($this->children){
			foreach ($this->children as $child){
				if($child->type === 'tab' && !$this->tab_headers_output){

					$this->draw_tab_headers();
					$this->tab_headers_output = true;

				}

				$child->draw();
			}
		}

		ob_get_flush();
	}

	public function draw_tab_headers( $out = '' ) {
		if($this->tab_headers){
			echo '<div class="ctusp_tab_navigation">';
				foreach ($this->tab_headers as $tab_header){
					$out .= $tab_header->draw();
				}
			echo '</div>';
		}
	}

}