<?php

namespace Cleantalk\USP\File;

class BTreeLeaf {
	
	// Node structure
	private $max_elems_in_node;
	private $key_size;
	private $val_size;
	private $link_size;
	private $eod;
	private $end_of_node;
	private $elem_size;
	private $leaf_size;

	private $node_structure = array(
		'left',
		'parent',
		'elements' => array(),
		'eod',
		'nulls'
	);

	// Data
	public $key    = null;
	public $parent = null;

	// State
	public $link;
	public $link_left;
	public $link_parent;

	public $elements = array();

	private $size;

	private $stream;
	
	public function __construct( $params, $link_or_elems ) {
		
		$this->stream = $params['stream'];

		foreach ( $params as $param_name => $value ) {
			$this->$param_name = $value;
		}
		
		if( is_array( $link_or_elems ) ){

			$this->elements = $link_or_elems;

		}else{
   
			$this->link = $link_or_elems === '' ? $this->link_size : $link_or_elems;

			// Set position to search
			fseek($this->stream, $this->link );
			
			// Read node
			$raw_leaf = fread( $this->stream, $this->leaf_size );

			$this->unserialize( $raw_leaf );

		}
	}

	public function insert( $key, $val, $link = '' ){
		
		// Adding new element
		$this->elements[] = array(
			'key' => $key,
			'val' => $val,
			'link' => $link,
		);
		$this->size++;
		
		// Sorting elements by key
		$keys = array_column( $this->elements, 'key' );
		array_multisort( $keys, SORT_ASC, SORT_NUMERIC, $this->elements );
	}
	
	/**
	 * Searching for element in leaf using a key
	 *
	 * @param $key_to_search
	 *
	 * @return false|array of BTreeLeafNode
	 */
	public function searchForKey( $key_to_search )
    {
        $first_node = new BTreeLeafNode( reset( $this->elements ) );
        $last_node  = new BTreeLeafNode( end(   $this->elements ) );
		
		// Leaf is empty
		if( $this->isEmpty() ){
            $out = false;
		
		// Leaf contains the exact key. Get all nodes with this key.
        }elseif( in_array( $key_to_search, array_column( $this->elements, 'key' ) ) ){
            $out = $this->getNodesByKey( $key_to_search );
			
		// No key found in this leaf. Get link to correct child
		// Check if it's on the right
		}elseif( $key_to_search > $last_node->key && $last_node->link_right ){
            $last_node->link = $last_node->link_right;
            $out = array( $last_node );
			
		// Check if it's on the left
		}elseif( $key_to_search < $first_node->key && $this->link_left ){
            $first_node->link = $this->link_left;
            $out = array( $first_node );
			
		// Get link from the middle
		}else{
   
			// Binary search
			$top = $this->size - 1;
			$bot = 0;
			$position = 0;
			while( $top >= $bot ){
				
				$position = (int) floor( ( $top + $bot ) / 2 );
				
				if( $this->elements[ $position ]['key'] < $key_to_search ){
                    $bot = $position + 1;
                }elseif( $this->elements[ $position ]['key'] > $key_to_search ){
                    $top = $position - 1;
                }
				
			}
            
            $node       = new BTreeLeafNode( $this->elements[ $position ] );
			
            $node->link = $node->key < $key_to_search
                ? $node->link_right
                : $node->link_left;
            
            $out = array( $node );
		}
		
		return isset( $out )
            ? $out
            : false;
	}
	
	/**
	 * Get all elements with such key from the node
	 *
	 * @param $key
	 *
	 * @return false|array of BTreeLeafNode
	 */
	private function getNodesByKey( $key ){
        
        $out = array();
	    
		foreach( $this->elements as $array_key => $element ){
			if( $element['key'] == $key ){
                $out[] = new BTreeLeafNode( $element );
            }
		}
		
		return $out ?: false;
	}
	
	public function split(){

		return array(
			'left'   => array_slice( $this->elements, 0, floor( $this->max_elems_in_node / 2 ), true  ),
			'middle' => array_slice( $this->elements, floor( $this->max_elems_in_node / 2 ), 1, true ),
			'right'  => array_slice( $this->elements, floor( $this->max_elems_in_node / 2 ) + 1, null, true ),
		);

	}

	private function unserialize( $leaf__raw ){
		
		if( strlen( $leaf__raw ) < $this->leaf_size )
			return null;
		
		// Get left link
		$this->link_left = str_replace( "\x00", '', substr(
			$leaf__raw, 0,
			$this->link_size ) );
		
		// Get left link
		$this->link_parent = str_replace( "\x00", '', substr( $leaf__raw, $this->link_size, $this->link_size ) );
		
		// Cut useless data
		$leaf__raw = substr( $leaf__raw, $this->link_size * 2, strpos( $leaf__raw, $this->eod ) - $this->link_size * 2 );
		
		// Get data from raw and write it to $this->node
        $previous_link = $this->link_left;
		while ( $leaf__raw )
        {
            $right_link       = str_replace("\x00", '', substr( $leaf__raw, $this->key_size + $this->val_size, $this->link_size ) );
            $this->elements[] = array(
                'key'       => str_replace( "\x00", '', substr( $leaf__raw, 0, $this->key_size ) ),
                'val'       => str_replace( "\x00", '', substr( $leaf__raw, $this->key_size, $this->val_size ) ),
                'link'      => $right_link,
                'link_left' => $previous_link,
            );
            $previous_link    = $right_link;
            $leaf__raw        = substr( $leaf__raw, $this->elem_size );
		}

		$this->size = $this->elements ? count( $this->elements ) : 0;
		
	}


	public function serialize( $raw = '' ){

		$raw .= str_pad( $this->link_left, $this->link_size, "\x00" );

		$raw .= str_pad( $this->link_parent, $this->link_size, "\x00" );

		foreach ( $this->elements as $elem ) {
			$raw .= str_pad( $elem['key'], $this->key_size, "\x00" );
			$raw .= str_pad( $elem['val'], $this->val_size, "\x00" );
			$raw .= str_pad( $elem['link'], $this->link_size, "\x00" );
		}

		$raw .= $this->eod;

		$raw = str_pad( $raw, $this->leaf_size - strlen( $this->end_of_node ), "\x00" );

		$raw .= $this->end_of_node;

		return $raw;
	}
	
	public function save( $position = 0 ){
		fseek( $this->stream, $this->link );
		return fwrite( $this->stream, $this->serialize() );
	}
	
	public function isEmpty(){
		return ! $this->elements;
	}

	/**
	 * @return mixed
	 */
	public function getSize() {
		return $this->size;
	}
	
}
