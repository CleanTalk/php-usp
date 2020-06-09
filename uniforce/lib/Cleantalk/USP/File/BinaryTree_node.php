<?php

namespace Cleantalk\USP\File;

use Cleantalk\USP\Templates\FluidInterface;

class BinaryTree_node {

	use FluidInterface;

	// Structure
	private $key_length = 12;
	private $link_length = 12;
	private $link_hex_length = 12;

	// Data
	public $left   = null;
	public $right  = null;
	public $key    = null;
	public $link   = null;
	public $position;
	public $raw = '';

	public function __construct( $node, $position = null, $link = null  ) {

		if( $node instanceof BinaryTree_node ){
			$position = $node->position;
			$node = $this->create( $node->key, $node->link, $node->left, $node->right );
		}

		if ( isset( $link ) )
			$node = $this->create( $node, $link );

		if( isset( $position ) )
			$node = $this->parse( $node );

		if( $node ){
			$this->position = $position;
			$this->right = $node['right'];
			$this->left  = $node['left'];
			$this->key   = $node['key'];
			$this->link  = $node['link'];
		}else
			return null;
	}

	private function parse( $node_raw ){

		$this->raw = $node_raw;

		$node['left']     = hexdec( substr( $node_raw, 0, $this->link_hex_length ) );
		$node['right']    = hexdec( substr( $node_raw, $this->link_hex_length, $this->link_hex_length ) );
		$node['key']      = str_replace( "\x00", '', substr( $node_raw, $this->link_hex_length * 2, $this->key_length ) );
		$node['link']     = str_replace( "\x00", '', substr( $node_raw, $this->link_hex_length * 2 + $this->key_length, $this->link_length ) );

		return $node['key'] ? $node : null;
	}

	/**
	 * Magic
	 *
	 * @param $key
	 * @param $link
	 *
	 * @return string
	 */
	private function create( $key, $link, $left_link = '', $right_link = ''){
		$left_link  = dechex($left_link)  === '0' ? '' : dechex($left_link);
		$right_link = dechex($right_link) === '0' ? '' : dechex($right_link);
		$this->raw =
			   str_pad( $left_link, $this->link_hex_length, "\x00", STR_PAD_RIGHT )// Left link
	         . str_pad( $right_link, $this->link_hex_length, "\x00", STR_PAD_RIGHT ) // Right link
	         . str_pad( $key, $this->key_length, "\x00", STR_PAD_RIGHT ) // Key
	         . str_pad( $link, $this->link_length, "\x00", STR_PAD_RIGHT ); // Link

		return $this->raw;
	}

	public function update(){

	}


}