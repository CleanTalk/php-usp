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
     * @var \Cleantalk\USP\Common\Storage
     */
    private $meta;
    
    /**
     * @var \Cleantalk\USP\Common\Storage
     */
    private $meta_temp;
    
    private $indexes_description;
    private $indexes;
    private $indexes_temp;
    private $indexed_column;
    private $index_type;
    
    // Query params
    private $columns;
    private $where;
    private $where_columns;
    private $offset;
    private $amount;
    
    
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

        $this->getMetaDataTemp(); // @todo handle error

        if (! $this->meta_temp->is_empty()) {
            $this->storage = new \Cleantalk\USP\File\Storage( $db_name, $this->meta_temp->cols );
            if ($this->meta_temp->indexes) {
                $this->getIndexesTemp();
            }
        }
    }

    public function insert($data)
    {
        $inserted = 0;

        for( $number = 0; isset( $data[ $number ] ); $number++ ){

            switch ( $this->addIndex( $number + 1, $data[ $number ] ) ){
                case true:

                    if( $this->storage->put( $data[ $number ] ) ){
                        $inserted++;
                    }
                    break;

                case false:

                    break;
            }

        }

        $this->meta->rows += $inserted;
        $this->meta->save();

        return $inserted;
    }

    public function insertTemp( $data ){
        
        $inserted = 0;
        
        for( $number = 0; isset( $data[ $number ] ); $number++ ){
            
            switch ( $this->addIndexTemp( $number + 1, $data[ $number ] ) ){
                case true:
                    
                    if( $this->storage->putTemp( $data[ $number ] ) ){
                        $inserted++;
                    }
                    break;
                
                case false:
                    
                    break;
            }
            
        }
        
        $this->meta_temp->rows += $inserted;
        $this->meta_temp->save();
        
        return $inserted;
    }

    public function delete() {
        
        // Clear indexes
        if( $this->meta->indexes ){
            
            foreach( $this->meta->indexes as &$index ){
                
                // @todo make multiple indexes support
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

    public function deleteTemp() {
        
        // Clear indexes
        if( $this->meta->indexes ){
            
            foreach( $this->meta->indexes as &$index ){
                
                // @todo make multiple indexes support
                $column_to_index = $index['columns'][0];
                
                switch( $index['type'] ){
                    case 'bintree':
                        $this->indexes_temp[ $column_to_index ]->clear_tree();
                        break;
                    case 'btree':
                        $this->indexes_temp[ $column_to_index ]->clear();
                        break;
                }
                $index['status'] = false;
            } unset( $index );
            
        }
        
        // Reset rows amount
        $this->meta_temp->rows = 0;
        $this->meta_temp->save();
        
        // Clear and delete a storage
        $this->storage->deleteTemp();
    }
    
    /**
     * Set what columns to select
     * Could be skipped
     *
     * @param mixed ...$cols
     *
     * @return FileDB
     */
    public function setWhat( ...$cols ){
        
        $cols = $cols ?: array_keys( $this->meta->cols );
    
        // Check columns for existence
        $result = $this->checkColumn( $cols );
        if ( $result !== true ) {
            Err::add( 'Unknown column: ' . $result );
        }else{
            $this->columns = $cols;
        }
        
        return $this;
    }
    
    /**
     * Set what columns and values should be selected
     * Check for columns right names
     *
     * @param array $where
     *
     * @return $this|bool
     */
    public function setWhere( $where = array() ){
        
        $where = $where ?: array_keys( $this->meta->cols );
        
        $result = $this->checkColumn( array_keys( $where ) );
        if ( $result !== true ) {
            Err::add( 'Unknown column in where: ' . $result );
        }else{
            $this->where = $where;
            $this->where_columns = array_keys( $where );
        }
        
        return $this;
    }
    
    /**
     * Checks and sets limits
     * Slice from the main result to get subresult starting from $offset, $amount length
     *
     * @param int $offset should be more than 0
     * @param int $amount should be more than 0
     *
     * @return $this|bool
     */
    public function setLimit( $offset, $amount ){
        
        if ( ! is_int( $offset ) && $offset >= 0 ) {
            Err::add( 'Offset value is wrong: ' . $offset );
            $this->offset = $offset;
        }
        
        if ( ! is_int( $amount ) && $amount > 0 ) {
            Err::add( 'Amount value is wrong: ' . $amount );
            $this->amount = $amount;
        }
        
        return $this;
    }
    
    
    /**
     * Fires the prepared request and check column names if passed
     * If no columns passed to select, returns all columns in result
     *
     * @param mixed ...$cols
     *
     * @return array|bool
     */
    public function select( ...$cols ){
        
        // @todo add error check from the setWhat, setWhere, setLimit
        
        // Set what columns to select if it's not
        if( ! $this->columns ){
            $this->setWhat( ...$cols );
        }
        
        // Set the where if it's not
        if( ! $this->where || ! $this->where_columns ){
            $this->setWhere();
        }
        
        // Check is "where" columns are indexed
        if( $this->where && $this->where_columns ){
            $this->isWhereIndexed();
        }
        
        $result = $this->getData();
        
        if( $result ){
            
            // Filter by requested columns
            foreach( $result as &$item ){
                foreach( $item as $column_name => $value ){
                    if( ! in_array( $column_name, $this->columns ) ){
                        unset( $item[ $column_name ] );
                    }
                }
            }
            
            // Filter by limit
            $result = array_slice( $result, (int) $this->offset, $this->amount );
        }
        
        return $result;
    }
    
    /**
     * Recursive
     * Check columns for existence
     *
     * @param $column
     *
     * @return bool
     */
    private function checkColumn( $column ) {
        
        if ( is_array( $column ) ) {
            foreach ( $column as $col ) {
                $result = $this->checkColumn( $col );
                if ( $result !== true ) {
                    return $result;
                }
            }
        } elseif ( ! isset( $this->meta->cols[ $column ] ) ) {
            return $column;
        }
        
        return true;
    }
    
    private function getData() {
        
        $addresses = array();
        
        foreach ( $this->where as $column => $values ){
            switch ( $this->index_type ){
                case 'binarytree':
                    foreach ( $values as $value ){
                        $addresses[] = $this->indexes[ $this->indexed_column ]->node__get_by_key( $value )->link;
                    }
                    break;
                case 'btree':
                    foreach ( $values as $value ){
                         $tree_result = $this->indexes[ $this->indexed_column ]->get( $value );
                         if( $tree_result !== false ){
                             foreach( $tree_result as $node ){
                                 $addresses[] = $node->getValue();
                             }
                         }
                    }
                    break;
            }
        }
        
        return $this->storage->get( $addresses );
    }
    
    /**
     * Recursive
     * Check columns for existence
     *
     * @param $column
     *
     * @return bool
     */
    private function isWhereIndexed( $column = null )
    {
        $column = $column ?: $this->where_columns;
        
        // Recursion
        if( is_array( $column ) ){
            foreach( $column as $column_name ){
                $result = $this->isWhereIndexed( $column_name );
                if( $result !== true ){
                    return $result;
                }
            }
        
        // One of where is not indexed
        }else{
            $indexed = false;
            foreach( $this->meta->indexes as $index ){
                if( in_array( $column, $index['columns'], true ) && $index['status'] === 'ready' ){
                    $indexed              = true;
                    $this->index_type     = $index['type'];
                    $this->indexed_column = $column;
                }
            }
    
            if( ! $indexed ){
                return false;
            }
        }
        
        return true;
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

    /**
     * Getting metadata for temp data and creates new file if not exists
     */
    private function getMetaDataTemp(){
        
        $this->meta_temp = new Storage( $this->name . '_meta', null );
        
        if( ! $this->meta_temp->is_empty() ){
            $this->meta_temp->line_length = array_sum( array_column( $this->meta_temp->cols, 'length' ) );
            $this->meta_temp->cols_num    = count( $this->meta_temp->cols );
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

    private function getIndexesTemp() {
        
        foreach( $this->meta_temp->indexes as $index ){
            // Index file name = databaseName_allColumnsNames.indexType
            $index_name =
                $this->name
                . '_' . lcfirst( array_reduce(
                    $index['columns'],
                    function($result, $item){ return $result . ucfirst( $item ); }
                ) )
                . '_temp.' . $index['type'];
            
            // @todo extend indexes on a few columns
            switch( $index['type'] ){
                
                case 'bintree':
                    $this->indexes_temp[ $index['columns'][0] ] = new BinaryTree( self::FS_PATH . $index_name );
                    break;
                    
                case 'btree':
                    $this->indexes_temp[ $index['columns'][0] ] = new BTree( self::FS_PATH . $index_name );
                    break;
            }
            
        }
        
    }

    private function addIndex($number, $data)
    {
        foreach ( $this->meta->indexes as $key => &$index ){

            // @todo this is a crunch
            $column_to_index = $index['columns'][0];
            $value_to_index = $data[ $column_to_index ];

            switch ( $index['type'] ){
                case 'bintree':
                    $result = $this->indexes[ $column_to_index ]->add_key( $value_to_index, $this->meta->rows + $number );
                    break;
                case 'btree':
                    $result = $this->indexes[ $column_to_index ]->put( $value_to_index, $this->meta->rows + $number );
                    break;
                default:
                    $result = false;
                    break;
            }

            if( is_int( $result ) && $result > 0 ){
                $index['status'] = 'ready';
                $out = true;
            }elseif( $result === true ){
                $out = false;
            }elseif( $result === false ){
                $out = false;
            }else{
                $out = false;
            }

            return $out;

        } unset( $index );
    }
    
    private function addIndexTemp( $number, $data ) {
        
        foreach ( $this->meta_temp->indexes as $key => &$index ){
	
	        // @todo this is a crunch
            $column_to_index = $index['columns'][0];
            $value_to_index = $data[ $column_to_index ];
            
            switch ( $index['type'] ){
                case 'bintree':
                    $result = $this->indexes_temp[ $column_to_index ]->add_key( $value_to_index, $this->meta_temp->rows + $number );
                    break;
                case 'btree':
                    $result = $this->indexes_temp[ $column_to_index ]->put( $value_to_index, $this->meta_temp->rows + $number );
                    break;
                default:
                    $result = false;
                    break;
            }
            
            if( is_int( $result ) && $result > 0 ){
	            $index['status'] = 'ready';
                $out = true;
            }elseif( $result === true ){
                $out = false;
            }elseif( $result === false ){
                $out = false;
            }else{
                $out = false;
            }
            
            return $out;
            
        } unset( $index );
    }
}