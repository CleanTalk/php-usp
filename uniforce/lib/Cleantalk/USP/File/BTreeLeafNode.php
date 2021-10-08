<?php

namespace Cleantalk\USP\File;

class BTreeLeafNode
{
    
    public $key;
    public $value;
    public $link_right;
    public $link_left;
    
    public $link;
    
    public function __construct( $key = null, $val = null, $link = null, $link_left = null )
    {
        $this->key   = $key;
        $this->value = $val;
        
        $this->link_left  = $link_left;
        $this->link_right = $link;
        
        $this->link = $link;
    }
    
    /**
     * @return void
     */
    public function getValues()
    {
        $this->value;
    }
}
