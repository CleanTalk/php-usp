<?php


namespace Cleantalk\USP\File;


use Cleantalk\USP\Common\Err;
use Cleantalk\USP\Common\Storage;

class FileDB {
    
    const FS_PATH = CT_USP_ROOT . 'data' . DIRECTORY_SEPARATOR;
    
    /**
     * @var \Cleantalk\USP\File\Storage
     */
    private $storage;
    
    /**
     * @var string
     */
    private $name;
    
    /**
     * @var Storage
     */
    private $meta;
    
    private $indexes_description;
    private $indexes;
    
    
    /**
     * FileDB constructor.
     *
     * @param string $db_name Name of DB
     */
    public function __construct( $db_name ) {
        
        // Set file storage name
        $this->name = $db_name;
        $this->getMetaData(); // @todo handle error
    
        if( ! $this->meta->is_empty() ){
            
            $this->storage = new \Cleantalk\USP\File\Storage( $db_name, $this->meta->cols );
    
            // Set indexes only if we have information about them
            if( $this->meta->indexes ){
                $this->getIndexes();
            }
        }
    }
    
    /**
     * Getting metadata and creates new file if not exists
     */
    private function getMetaData(){
        
        $this->meta = new Storage( $this->name . '_meta', null );
        
        if( ! $this->meta->is_empty() ){
            $this->meta->line_length = array_sum( array_column( $this->meta->cols, 'length' ) );
            $this->meta->cols_num    = count( $this->meta->cols );
        }
    }
    
    private function getIndexes() {
        
        foreach( $this->meta->indexes as $index ){
            // Index file name = databaseName_allColumnsNames.indexType
            $index_name =
                $this->name
                . '_' . lcfirst( array_reduce(
                    $index['columns'],
                    function($result, $item){ return $result . ucfirst( $item ); }
                ) )
                . '.' . $index['type'];
            
            // @todo extend indexes on a few columns
            switch( $index['type'] ){
                
                case 'bintree':
                    $this->indexes[ $index['columns'][0] ] = new BinaryTree( self::FS_PATH . $index_name );
                    break;
                    
                case 'btree':
                    $this->indexes[ $index['columns'][0] ] = new BTree( self::FS_PATH . $index_name );
                    break;
            }
            
        }
        
    }
    
    public function insert( $data ){
    
        var_dump( $data );
        
        $inserted = 0;
    
        for( $number = 0; isset( $data[ $number ] ); $number++ ){
	
	        switch ($this->addIndex( $number + 1, $data[ $number ] ) ){
		        case true:
		        	
		        	break;
		        	
                case false:
                    
                    break;
	        }
        	
            if( $this->storage->putRow( $data[ $number ] ) ){
                $this->addIndex( $number + 1, $data[ $number ] );
                $inserted++;
            }
            
        }
        
        $this->meta->rows += $inserted;
        $this->meta->save();

        return $inserted;
    }
    
    private function addIndex( $number, $data ) {
        
        foreach ( $this->meta->indexes as $key => &$index ){
	
	        // @todo this is a crunch
            $column_to_index = $index['columns'][0];
            $value_to_index = $data[ $column_to_index ];
            
            switch ( $index['type'] ){
                case 'bintree':
                    $result = $this->indexes[ $column_to_index ]->add_key( $value_to_index, $this->meta->rows + $number );
                    break;
                case 'btree':
                    $result = $this->indexes[ $column_to_index ]->put( $value_to_index, $this->meta->rows + $number, null, true );
                    break;
                default:
                    $result = false;
                    break;
            }
            
            if( is_int( $result ) && $result > 0 ){
	            $index['status'] = 'ready';
                $out = true;
            }elseif( $result === true ){
//                Err::add('Insertion', 'Duplicate key for column "' . $index . '": ' . $data[ array_search( $index, $columns_name ) ] );
                $out = false;
            }elseif( $result === false ){
//                Err::add('Insertion', 'No index added for column "' . $index . '": ' . array_search( $index, $columns_name ) );
                $out = false;
            }else{
                $out = false;
            }
            
            return $out;
            
        } unset( $index );
    }
    
    public function delete() {
        
        // Clear indexes
        if( $this->meta->indexes ){
            
            foreach( $this->meta->indexes as &$index ){
	
            	// @todo crunch
	            $column_to_index = $index['columns'][0];
            	
                switch( $index['type'] ){
                    case 'bintree':
                        $this->indexes[ $column_to_index ]->clear_tree();
                        break;
                    case 'btree':
                        $this->indexes[ $column_to_index ]->clear();
                        break;
                }
                $index['status'] = false;
            } unset( $index );
            
        }
        
        // Reset rows amount
        $this->meta->rows = 0;
        $this->meta->save();
        
        // Clear and delete a storage
        $this->storage->delete();
    }
    
}