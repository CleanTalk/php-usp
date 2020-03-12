<?php

namespace Cleantalk\File;

use Cleantalk\Common\Err;
use Cleantalk\Common\Storage;

class BTree {

	const BT_PATH = CT_USP_ROOT . 'data' . DIRECTORY_SEPARATOR;

	// Node structure
	public $max_elems_in_node = 100;
	public $key_size          = 10;
	public $val_size          = 6;
	public $link_size         = 8;
	public $eod               = "\xffend_of_data";
	public $end_of_node       = "\n";
	public $elem_size         = 1;
	public $node_size         = 2;

	public $node_params = array(
		'max_elems_in_node' => 100,
		'key_size'          => 10,
		'val_size'          => 6,
		'link_size'         => 8,
		'eod'               => "\xffend_of_data",
		'end_of_node'       => "\n",
	);
	// File
	private $file_path;
	private $stream;
	private $seek_position = 0;
	private $insert_position = 0;

	// Current state
	private $root_link = 0;

	/**
	 * @var BTree_node
	 */
	public $parent  = null;
	/**
	 * @var BTree_node
	 */
	public $current = null;

	public function __construct( $file_name ) {

		$this->file_path = self::BT_PATH . $file_name;

		$this->stream = fopen( $this->file_path, 'c+b' );

		$this->node_params['elem_size'] = $this->key_size + $this->val_size + $this->link_size;
		$this->node_params['node_size'] =
			$this->link_size * 2 +
            $this->max_elems_in_node * $this->node_params['elem_size'] +
            strlen( $this->eod ) +
            strlen( $this->end_of_node );
		$this->node_params['stream'] = $this->stream;
		$this->elem_size = $this->node_params['elem_size'];
		$this->node_size = $this->node_params['node_size'];


		if ( $this->stream ){

			$fsize = filesize( $this->file_path );
			$this->insert_position = $fsize ? $fsize : $this->link_size;
			$this->root_link = (int)fread( $this->stream, $this->link_size);

		}else
			Err::add( 'Failed to open file' );
	}

	public function insert( $key, $val ){

		$this->current = null;
		$elem = $this->get_elem( $key );

		// Key already exists
		if( $elem )
			return true;
		if( ! $this->current )
			$this->current = new BTree_node( $this->node_params, $this->root_link );

		// Insert in current node
		$this->current->insert( $key, $val );
		$this->current->link_parent = $this->parent ? $this->parent->link : '';

		// Recursively rebuild tree
		$result = $this->traverse_up();

		unset( $this->current, $this->parent );

		return $result;
	}

	private function traverse_up(){
//
		// Element hasn't reached maximum size
		if( $this->current->getSize() <= $this->max_elems_in_node ){

			fseek( $this->stream, $this->current->link );
			$result = fwrite( $this->stream, $this->current->serialize() );
			$this->insert_position = $this->insert_position == $this->link_size
				? $this->insert_position + $this->node_size
				: $this->insert_position;

		// Larger than maximum
		}else{

			if( ! $this->parent || $this->parent->is_empty() )
				$this->parent = null;

			$nodes = $this->current->split();


			// Left. Write it
			// To the current place
			$nodes['left'] = new BTree_node( $this->node_params, $nodes['left'] );
			$nodes['left']->link = $this->current->link;
			$nodes['left']->link_left = $this->current->link_left;
			$nodes['left']->link_parent = $this->parent
				? $this->parent->link
				: $this->insert_position + $this->node_size;

			fseek( $this->stream, $this->current->link );
			$result = fwrite( $this->stream, $nodes['left']->serialize() );

			// Right. Write it
			// To the insert position
			$nodes['right']            = new BTree_node( $this->node_params, $nodes['right'] );
			$nodes['right']->link_left = current( $nodes['middle'] )['link']; // set left link from middle elem
			$nodes['right']->link      = $this->insert_position;
			$nodes['right']->link_parent = $this->parent
				? $this->parent->link
				: $this->insert_position + $this->node_size;

			fseek( $this->stream, $this->insert_position );
			$result = $result + fwrite( $this->stream, $nodes['right']->serialize() );
			$this->insert_position += $this->node_size;


			// Middle
			$key = key( $nodes['middle'] );

			// Traverse up
			// Insert middle element
			if ( isset( $this->parent ) ) {

				$this->parent->insert(
					$key,
					$nodes['middle'][ $key ]['val'],
					$nodes['right']->link
				);

				$this->current = $this->parent;
				$this->parent  = new BTree_node( $this->node_params, $this->parent->link_parent );
				$result = $result + $this->traverse_up();

			}else{
				$this->parent = new BTree_node( $this->node_params, array() );

				$this->parent->link_left = $this->current->link;
				$this->parent->insert(
					$key,
					current( $nodes['middle'] )['val'],
					$nodes['right']->link
				);

				fseek( $this->stream, $this->insert_position );
				$result = $result + fwrite( $this->stream, $this->parent->serialize() );


				$this->set_root( $this->insert_position );
				$this->insert_position += $this->node_size;


			}
		}

		return $result;
	}



	public function get_elem( $key, $link = null ){

		$this->get_node( $link ? $link : $this->root_link );

		if( ! $this->current )
			return null;

		$result = $this->current->search( $key );


		// Searched key found
		if( $result['type'] == 'found' ){
			return $result['elem'];
		}

		// Search in child. Recursion.
		if( $result['type'] == 'child' && $result['link'] ){
			return $this->get_elem( $key, $result['link'] );
		}

		// Searched not exists
		if( $result['type'] == 'child' && ! $result['link'] ){
			return null;
		}

	}

	public function get_node( $link = 0 ){

		// Set parent if exists
		if( $this->current )
			$this->parent = $this->current;

		// Get node
		$node = new BTree_node( $this->node_params, $link );

		// Empty element
		if( $node->is_empty() )
			return null;

		$this->current = $node;
	}

	public function clear_tree(){

		// Delete all data
		ftruncate( $this->stream, 0 );

		// Drop insert position
		$this->insert_position = $this->link_size;
		$this->set_root( $this->link_size );
	}

	private function set_root( $new_root ){
		$this->root_link = $new_root;
		fseek( $this->stream, 0 );
		fwrite( $this->stream, str_pad( $this->root_link, $this->link_size, "\x00" ) );
	}

	/**
	 * @return false|resource
	 */
	public function getStream() {
		return $this->stream;
	}
}