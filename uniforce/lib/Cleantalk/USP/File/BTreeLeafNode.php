<?php

namespace Cleantalk\USP\File;

class BTreeLeafNode
{
    
    public $key;
    public $value;
    
    public $link;
    public $link_right;
    public $link_left;
    
    
    //public function __construct( $key = null, $val = null, $link = null, $link_left = null )
    public function __construct( ...$args )
    {
        // Handling array on input
        $args = isset( $args[0] ) && is_array( $args[0] )
            ? $args[0]
            : $args;
        
        $args = array_values( $args );
        
        // Set missing params if there are
        for( $i = 0; $i < 4; $i++ ){
            $args[ $i ] = isset( $args[ $i ] ) ? $args[ $i ] : null;
        }
        
        $this->key        = $args[0];
        $this->value      = $args[1];
        $this->link_right = $args[2];
        $this->link_left  = $args[3];
        
        $this->link       = null;
    }
    
    /**
     * @return void
     */
    public function getValue()
    {
        return $this->value;
    }
}
