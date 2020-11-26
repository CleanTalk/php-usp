<?php

namespace Cleantalk\USP\File;

use Cleantalk\USP\Common\Err;
use Cleantalk\USP\Common\Storage;

class BinaryTree {

	const BT_PATH = CT_USP_ROOT . 'data' . DIRECTORY_SEPARATOR;

	// File
	private $file_path;
	private $stream;
	private $seek_position = 0;
	private $insert_position = 0;

	// Structure
	private $elem_length = 48;

	// Current state
	private $root   = 0;

	/**
	 * @var BinaryTree_node
	 */
	public $parent  = null;
	/**
	 * @var BinaryTree_node
	 */
	public $current = null;

	public function __construct( $file_name, $median = null ) {

		$this->file_path = self::BT_PATH . $file_name;

		$this->stream = fopen( $this->file_path, 'c+b' );

		if ( $this->stream ){

			$this->insert_position = filesize( $this->file_path );

//			$node = $this->node__get_by_position( 0 );
//			if( $node )
//				$this->current = new BinaryTree_node( $node );
		}else
			Err::add( 'Failed to open file' );
	}

	public function root__create( $key, $link ){
		$node = new BinaryTree_node( $key, 0, $link );
		fwrite( $this->stream, $node->raw );
		$this->seek_position   = 0;
		$this->insert_position += $this->elem_length;
	}

	public function node__add( $key, $link = '0' ){

		$this->seek_position = 0;
		$node = $this->node__get_by_key( $key );

		if( $node instanceof BinaryTree_node )
			$result = true;
		else{

			// Add link from parent
			if ( $key < $this->current->key )
				$this->current->left = $this->insert_position;
			else
				$this->current->right = $this->insert_position;
			// Get raw text via regenerating node
			$node = new BinaryTree_node( $this->current );
			fseek( $this->stream, $this->current->position );
			$result = fwrite( $this->stream, $node->raw );

			// Generate and insert child
			$node = new BinaryTree_node( $key, $this->insert_position, $link );
			fseek( $this->stream, $this->insert_position );
			$res = fwrite( $this->stream, $node->raw );
			$result = $result & $res;

			// Set insert position to the end of file
			$this->insert_position += $this->elem_length;
		}

		return $result;
	}

	public function node__get_by_key( $key = null ){

		if( $this->current )
			$this->parent =  $this->current;

		// Set position to search
		fseek($this->stream, $this->seek_position );

		// Get node
		$node_raw = fread( $this->stream, $this->elem_length );

		$node = new BinaryTree_node( $node_raw, $this->seek_position );

		if( $node ){

			$this->current = $node;

			if( $this->current->key == $key ){
				$this->seek_position = 0;
				$this->current = null;
				$this->parent = null;
				return $node;
			}else{
				$this->seek_position = $this->current->key > $key
					? $this->current->left
					: $this->current->right;

				if($this->seek_position === 0)
					return null;

				return $this->node__get_by_key( $key );
			}

		}else
			return null;
	}

	public function node__get_by_position( $position ){

		fseek($this->stream, $position );

		$node_raw = fread( $this->stream, $this->elem_length );

		$node = new BinaryTree_node( $node_raw, $position );

		if( $node )
			$this->current = $node;

		return $node ? $node : null;
	}

	public function clear_tree(){
		// Delete all data
		ftruncate( $this->stream, 0 );
		// Drop insert position
		$this->insert_position = 0;
		
		// Delete file
		unlink( $this->file_path );
	}
}