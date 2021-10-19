<?php

namespace Cleantalk\USP\File;

use Cleantalk\USP\Common\Err;

class BTree {
	
	/**
	 * Length of BTree meta data in the start of the file
	 * @var int
	 */
	private $meta_length = 181;
	private $meta_param_length = 20;
	
	private $default_tree_meta = array(
		'max_elems_in_node' => 51,
		'key_size'          => 11,
		'val_size'          => 11,
		'link_size'         => 8,
		'eod'               => "\xffend_of_data",
		'end_of_node'       => "\n",
		'root_link'         => 181,
		'elem_size'         => 0,
		'leaf_size'         => 0,
	);
	
	private $leaf_params = array();
	
	// Leaf structure
	private $max_elems_in_node;
	private $key_size;
	private $val_size;
	private $link_size;
	private $eod;
	private $end_of_node;
	
	// Misc
	private $elem_size;
	private $leaf_size;
	
	/**
	 * Link to start of the BTree
	 * @var int
	 */
	private $root_link;
	
	// File
	private $file_path;
	private $stream;
	private $seek_position = 0;
	private $insert_position;

	// Current state

	/**
	 * @var BTreeLeaf
	 */
	public $parentLeaf  = null;
	
	/**
	 *
	 * @var BTreeLeaf
	 */
	public $currentLeaf = null;
    
    public function __construct( $file_path ) {

		$this->file_path = $file_path;
		$this->stream = fopen( $this->file_path, 'c+b' );
		
		if ( $this->stream ){
		
			// Set default meta if BTree file is empty
			if( ! filesize( $this->file_path ) )
				$this->setBTreeMeta();
			
			$this->getBTreeMeta();
			
			$this->insert_position = $this->getInsertPosition();
			
		}else
			Err::add( 'Failed to open file' );
		
	}
    
    /**
     * Inserts new pair key val to the tree
     *
     * @param int  $key
     * @param int  $val
     * @param int  $link
     * @param int $link_to_leaf
     *
     * @return int|false Amount of bytes written or false on error
     */
	public function put( $key, $val, $link = null, $link_to_leaf = null ){
	    
        // Insert into specific leaf
        if( $link_to_leaf ){
            $this->setCurrentLeaf( new BTreeLeaf( $this->leaf_params, $link_to_leaf ) );
            
	    // Insert into leaf
        }else{
            $this->setCurrentLeaf( $this->getLeafToInsertIn( $key ) );
        }
        
        $this->currentLeaf->insert( $key, $val, $link );
        
		/* Check leaf size */
        $result = $this->currentLeaf->getSize() < $this->max_elems_in_node
            ? $this->currentLeaf->save()
            : $this->rebuildTree();
		
		$this->unsetCurrentLeaf();
		
		return $result;
	}
	
    /**
     * Recursive.
     *
     * Search for element in tree
     *
     * @param mixed $key to search for
     * @param int $link to the supposed leaf with an element
     *
     * @return false|array of BTreeLeafNode
     */
    public function get( $key, $link = 0 ){
        
        $out = array();
        
        $this->setCurrentLeaf( new BTreeLeaf( $this->leaf_params, $link ?: $this->root_link ) );
        
        // Empty tree
        if( $this->isCurrentLeafEmpty() ){
            return false;
        }
        
        $found = $this->currentLeaf->searchForKey( $key );
        
        // No BTreeLeafNode was found
        if( $found === false ){
            return false;
        }
        
        $leaf_links_to_search = array();
        foreach( $found as $node ){
            
            if( $node->key == $key ){
                
                $out[]                  = $node;
                if( $node->link_right ) $leaf_links_to_search[] = $node->link_right;
                if( $node->link_left )  $leaf_links_to_search[] = $node->link_left;
                
            }elseif( $node->link ){
                $leaf_links_to_search[] = $node->link;
            }
        }
        $leaf_links_to_search = array_unique( $leaf_links_to_search );
        
        foreach( $leaf_links_to_search as $leaf_link_to_search ){
            
            $sub_result = $this->get( $key, $leaf_link_to_search );
            
            $out = ! $sub_result
                ? $out
                : array_merge( $out, $sub_result );
        }
        
        return $out;
    }
    
    public function clear(){
        
        // Delete all data
        ftruncate( $this->stream, 0 );
        
        // Drop insert position
        $this->insert_position = $this->link_size;
        $this->setRootLink( $this->link_size );
        
        // Delete file
        unlink( $this->file_path );
    }
    
    /**
     * @param mixed $key
     * @param int $link
     *
     * @return BTreeLeaf
     */
    private function getLeafToInsertIn( $key, $link = 0 ){
        
        $leaf = new BTreeLeaf( $this->leaf_params, $link ?: $this->root_link );
        
        $leaf_nodes = $leaf->searchForKey( $key );
        
        if( $leaf_nodes !== false && $leaf_nodes[0]->link ){
            return $this->getLeafToInsertIn( $key, $leaf_nodes[0]->link );
        }
        
        return $leaf;
    }
    
    /**
     * Recursive.
     *
     * Rebuilding tree
     *
     * @return int|false Amount of bytes written or false on error
     */
	private function rebuildTree(){
		
		$nodes = $this->currentLeaf->split();
		
		$parent_link = $this->currentLeaf->link_parent ?: $this->insert_position + $this->leaf_size;
		
		// Changing link in daughter leafs in right nodes
		foreach( $nodes['right'] as $node ){
			if( $node['link'] ){
				$node = new BTreeLeaf( $this->leaf_params, (int) $node['link'] );
				$node->link_parent = (int) $this->insert_position;
				$node->save();
			}
		}
		
		// Changing link in daughter leafs in middle node
		if( current( $nodes['middle'] )['link'] ){
			$node              = new BTreeLeaf( $this->leaf_params, (int) current( $nodes['middle'] )['link'] );
			$node->link_parent = (int) $this->insert_position;
			$node->save();
		}
		
		// Left. Write it
		// To the current place
		$nodes['left']              = new BTreeLeaf( $this->leaf_params, $nodes['left'] );
		$nodes['left']->link        = $this->currentLeaf->link;
		$nodes['left']->link_left   = $this->currentLeaf->link_left;
		$nodes['left']->link_parent = $parent_link;
		$result = $nodes['left']->save();
		
		// Right. Write it
		// To the insert position
		$nodes['right']              = new BTreeLeaf( $this->leaf_params, $nodes['right'] );
		$nodes['right']->link_left   = current( $nodes['middle'] )['link']; // set left link from middle elem
		$nodes['right']->link        = $this->insert_position;
		$nodes['right']->link_parent = $parent_link;
		$result += $nodes['right']->save();
		$this->insert_position = $this->insert_position + $this->leaf_size;
		
		// Middle value to traverse
		$key = key( $nodes['middle'] );
		
		// Traverse up insert middle element
		// Insert in parent if it exists
		if ( $this->currentLeaf->link_parent ){
            $result += $this->put(
                $nodes['middle'][ $key ]['key'],
                $nodes['middle'][ $key ]['val'],
                $nodes['right']->link,
                $this->currentLeaf->link_parent
            );
			
		// Insert in new node and make it ROOT
		}else{
            $params = array_merge(
                $this->leaf_params,
                array( 'link' => $this->insert_position, 'link_left' => $this->currentLeaf->link )
            );
            
            $elems = array( array(
                'key'  => $nodes['middle'][ $key ]['key'],
                'val'  => $nodes['middle'][ $key ]['val'],
                'link' => $nodes['right']->link,
            ) );
            
            $this->setCurrentLeaf( new BTreeLeaf( $params, $elems ) );
			
			$result                += $this->currentLeaf->save();
			$this->insert_position += $this->leaf_size;
			
			$this->setRootLink( $this->currentLeaf->link );
			
		}
		
		return $result;
	}
	
	private function setRootLink( $new_root ){
		$this->root_link = $new_root;
		$this->setBTreeMeta();
	}
	
	/**
	 * @return false|int
	 */
	private function getInsertPosition(){
		$insert_position = filesize( $this->file_path );
		$insert_position = $insert_position ? $insert_position : $this->meta_length + $this->leaf_size;
		return $insert_position;
	}
	
	/**
	 * @param BTreeLeaf $currentLeaf
	 *
	 * @return BTreeLeaf
	 */
	private function setCurrentLeaf( BTreeLeaf $currentLeaf ){
		$this->currentLeaf = $currentLeaf;
		return $this->currentLeaf;
	}
	
	/**
     * Set $this->currentLeaf to null
     *
	 * @return void
	 */
	private function unsetCurrentLeaf(){
		$this->currentLeaf = null;
	}
	
	/**
	 * Check if the current leaf is set
	 *
	 * @return bool
	 */
	private function isCurrentLeafEmpty(){
		return $this->currentLeaf->isEmpty();
	}
    
    private function getBTreeMeta(){
        
        fseek( $this->stream, 0 );
        $raw_meta = fread( $this->stream, $this->meta_length );
        foreach( $this->default_tree_meta as $meta_name => $val ){
            
            $meta_value__sanitized = str_replace( "\x00", '', substr( $raw_meta, 0, $this->meta_param_length ) );
            settype( $meta_value__sanitized, gettype( $val ) );
            $this->$meta_name = $meta_value__sanitized;
            $this->leaf_params[ $meta_name ] = $meta_value__sanitized;
            
            // Erasing what we have read already
            $raw_meta = substr( $raw_meta, $this->meta_param_length );
        }
        
        $this->leaf_params['stream'] = $this->stream;
        
    }
    
    private function setBTreeMeta(){
        
        fseek( $this->stream, 0 );
        
        $raw_meta = '';
        foreach( $this->default_tree_meta as $meta_name => &$meta_value ){
            
            if( $meta_name === 'elem_size'){
                $meta_value = $this->default_tree_meta['key_size'] +
                              $this->default_tree_meta['val_size'] +
                              $this->default_tree_meta['link_size'];
            }
            
            if( $meta_name === 'leaf_size'){
                $meta_value = $this->default_tree_meta['link_size'] * 2 +
                              $this->default_tree_meta['max_elems_in_node'] * $this->default_tree_meta['elem_size'] +
                              strlen( $this->default_tree_meta['eod'] ) +
                              strlen( $this->default_tree_meta['end_of_node'] );
            }
            
            $meta_value = isset( $this->$meta_name ) ? $this->$meta_name : $meta_value;
            
            $raw_meta .= str_pad( $meta_value, $this->meta_param_length, "\x00" );
            
        } unset( $meta_value );
        
        $raw_meta .= "\n";
        
        fwrite( $this->stream, $raw_meta, $this->meta_length );
        
    }
}
