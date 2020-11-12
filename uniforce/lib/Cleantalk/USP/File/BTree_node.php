<?php

namespace Cleantalk\USP\File;

class BTree_node {

	// Node structure
	public $max_elems_in_node = null;
	public $key_size          = null;
	public $val_size          = null;
	public $link_size         = null;
	public $eod               = null;
	public $end_of_node       = null;
	public $elem_size         = null;
	public $node_size         = null;

	public $params;

//	private $node_structure = array(
//		'left',
//		'parent',
//		'elements' => array(),
//		'eod',
//		'nulls'
//	);
//
//	private $elem_structure = array(
//		'key' => array(
//			'val' => '',
//			'link' => '',
//		),
//	);

	// Data
	public $left   = null;
	public $right  = null;
	public $key    = null;
	public $parent = null;
	public $raw = '';

	// State
	public $link;
	public $link_left;
	public $link_parent;

	public $node;

	private $size;

	private $stream;


	public function __construct( $params, $link_or_elems ) {

		$this->stream = $params['stream'];

		foreach ( $params as $param_name => $value ) {
			$this->$param_name = $value;
		}

		if( is_array( $link_or_elems ) ){

			$this->node = $link_or_elems;

		}else{

			$this->link = $link_or_elems === '' ? $this->link_size : $link_or_elems;

			// Set position to search
			fseek($this->stream, $this->link );

			// Read node
			$raw = fread( $this->stream, $this->node_size );

			$this->unserialize( $raw );

		}
	}

	public function insert( $key_to_insert, $val_to_insert, $link_to_insert = null ){
		$this->node[ $key_to_insert ] = array(
			'val' => $val_to_insert,
			'link' => ! isset( $link_to_insert ) ? str_repeat( "\x00", $this->link_size ) : $link_to_insert,
		);
		ksort( $this->node );
		$this->size++;
	}

	public function search( $key_to_search ){

		// Search key in this node
		if ( isset( $this->node[ $key_to_search ] ) ){
			return array(
				'type' => 'found',
				'elem'  => array(
					'key' => $key_to_search,
					'val' => $this->node[ $key_to_search ]['val'],
					'link' => $this->node[ $key_to_search ]['link'],
				),
			);
		}

		// No key found in this node. Get link to correct child
		// Check if it's right or left
		end( $this->node );
		if ( $key_to_search > key( $this->node ) ) {
			return array(
				'type' => 'child',
				'link' => current( $this->node )['link'],
			);
		}

		// Check if it in the right or left nodes
		reset( $this->node );
		if ( $key_to_search < key( $this->node ) ) {
			return array(
				'type' => 'child',
				'link' => $this->link_left,
			);
		}

		// Get link from the middle
		$prev_key = null;
		foreach ( $this->node as $key => $elem ){
			if ( $key_to_search < $key && isset( $this->node[ $prev_key ] ) )
				return array(
					'type' => 'child',
					'link'  => $this->node[ $prev_key ]['link'],
				);
			$prev_key = $key;
		}

		// For emergence case
		return null;
	}

	public function split(){

		return array(
			'left'   => array_slice( $this->node, 0, floor( $this->max_elems_in_node / 2 ), true  ),
			'middle' => array_slice( $this->node, floor( $this->max_elems_in_node / 2 ), 1, true ),
			'right'  => array_slice( $this->node, floor( $this->max_elems_in_node / 2 ) + 1, null, true ),
		);

	}

	private function unserialize( $node_raw ){

		// Get left link
		$this->link_left = str_replace( "\x00", '', substr( $node_raw, 0, $this->link_size ) );

		// Get left link
		$this->link_parent = str_replace( "\x00", '', substr( $node_raw, $this->link_size, $this->link_size ) );

		// Cut useless data
		$node_raw = substr( $node_raw, $this->link_size * 2, strpos( $node_raw, $this->eod ) - $this->link_size * 2 );

		// Get data from raw and write it to $this->node
		while ( $node_raw ){

			$key = str_replace( "\x00", '', substr( $node_raw, 0, $this->key_size ) );

			$this->node[ $key ] = array(
				'val' => str_replace( "\x00", '', substr($node_raw, $this->key_size, $this->val_size ) ),
				'link' => str_replace( "\x00", '', substr($node_raw, $this->key_size + $this->val_size, $this->link_size ) ),
			);
			$node_raw = substr( $node_raw, $this->elem_size );
		}

		$this->size = $this->node ? count( $this->node ) : 0;

		return $this->node;
	}


	public function serialize( $raw = '' ){

		$raw .= str_pad( $this->link_left, $this->link_size, "\x00" );

		$raw .= str_pad( $this->link_parent, $this->link_size, "\x00" );

		foreach ( $this->node as $key => $elem ) {
			$raw .= str_pad( $key, $this->key_size, "\x00" );
			$raw .= str_pad( $elem['val'], $this->val_size, "\x00" );
			$raw .= str_pad( $elem['link'], $this->link_size, "\x00" );
		}

		$raw .= $this->eod;

		$raw = str_pad( $raw, $this->node_size - strlen( $this->end_of_node ), "\x00" );

		$raw .= $this->end_of_node;

		return $raw;
	}

	public function is_empty(){
		return ! $this->node && true;
	}

	/**
	 * @return mixed
	 */
	public function getSize() {
		return $this->size;
	}

}
