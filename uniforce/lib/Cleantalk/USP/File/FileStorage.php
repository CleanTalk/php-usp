<?php

namespace Cleantalk\USP\File;

use Cleantalk\USP\Common\Err;
use Cleantalk\USP\Common\Storage;

class FileStorage {

//	use \Cleantalk\USP\Templates\FluidInterface;
//	use \Cleantalk\USP\Templates\Singleton;

	const FS_PATH = CT_USP_ROOT . 'data' . DIRECTORY_SEPARATOR;
	const FS_MEMORY_CAP = 5 * 1024 * 1024; // 5 MiB


	private $salt;

	// Name of storage
	private $name;

	// Paths
	private $data_file;

	/**
	 * @var Storage
	 */
	private $meta;

	/**
	 * @var resource
	 */
	private $stream;

	// Query
	private $columns = array();
	private $where = array();
	private $where_columns = array();
	private $offset = 0;
	private $amount = 0;

	// Data
	private $row_separator = "\n";
	private $row_placeholder = "\x00";

	private $buffer;
	private $buffer_row;
	private $buffer_output;

	// Indexes
	private $indexes = null;
	private $indexed_column;
	private $index_type;

	public function __construct( $name, $median = 500000000 ) {

		// Set file storage name
		$this->name = $name;

//		if ( is_file( self::DB_PATH . $db_name ) )
//			mkdir( self::DB_PATH . $db_name );

		// Set paths
		$this->data_file  = self::FS_PATH . $name;

		// Read metadata
		$this->get_meta();

		// Set indexes only if we have information about them
		if( ! $this->meta->is_empty() )
			$this->get_indexes();

		$this->stream = fopen( $this->data_file, 'a+b' );

		return $this;
	}

	public function set_index_median( $median = null ){
		current( $this->indexes )
			->root__create(
				$median ? $median : $this->meta->median,
				0
			);
	}

	private function get_indexes() {
		foreach ( $this->meta->indexes as $name => $index ){
			switch ( $index['type'] ) {
				case 'hash':
//					$result = $this->index__create__hash( $data[ $index ] );
					break;
				case 'binary_tree':
					$this->indexes[ $name ] = new BinaryTree( "{$this->name}_{$name}_{$index['type']}" );
					break;
				case 'b_tree':
					$this->indexes[ $name ] = new BTree( "{$this->name}_{$name}_{$index['type']}" );
					break;
			}
		}
	}

	/**
	 * Getting metadata and creates new file if not exists
	 */
	private function get_meta() {
		$this->meta = new Storage( $this->name . '_meta', null );
	}

	/**
	 * Reset and set metadata
	 *
	 * @param $meta
	 *
	 * @return $this
	 */
	public function set_meta( $meta ) {

		if ( $this->meta ) {
			$this->meta->delete();
			$this->meta = null;
		}

		$this->get_meta();

		$i = 0;
		$this->meta->line_length = strlen( $this->row_separator );
		foreach ( $meta['cols'] as $name => $col ) {
			$this->meta->cols[ $name ]['type']   = $col['type'];
			$this->meta->cols[ $name ]['length'] = $col['length'];
			$this->meta->line_length             += $col['length'];
			$i ++;
		}
		$this->meta->description = $meta['description']      ? $meta['description'] : 'no description';
		$this->meta->indexes     = isset( $meta['indexes'] ) ? $meta['indexes']       : array();
		$this->meta->cols_num    = $i;
		$this->meta->rows        = 0;
		$this->meta->median      = 5000000000;
		$this->meta->save();

		return $this;
	}

	public function update_meta( $name, $value ) {
		$this->meta->$name = $value;
		$this->meta->save();
	}

	// Check column names
	public function set_columns( ...$cols ){

		$cols = $cols ? $cols : array_keys( $this->meta->cols );

		$result = $this->check__column( $cols );
		if ( $result !== true ) {
			Err::add( 'Unknown column: ' . $result );
			return false;
		}

		$this->columns = $cols;

		return $this;
	}

	// Check limits
	public function set_limit( $offset = 0, $amount = 0 ){

		if ( gettype( $offset ) !== 'integer' && $offset >= 0 ) {
			Err::add( 'Offset value is wrong: ' . $offset );
			return false;
		}

		if ( gettype( $amount ) !== 'integer' && $amount > 0 ) {
			Err::add( 'Amount value is wrong: ' . $amount );
			return false;
		}

		$this->offset = $offset;
		$this->amount = $amount;

		return $this;
	}

	// Where
	// Check for right names
	public function set_where( $where ){

		$result = $this->check__column( array_keys( $where ) );
		if ( $result !== true ) {
			Err::add( 'Unknown column: ' . $result );
			return false;
		}

		$this->where = $where;
		$this->where_columns = array_keys( $where );

		return $this;
	}

	/**
	 * @return array|bool
	 */
	public function select() {

//		$query = is_string( $query ) ? $this->parse_query() : $query;

		//
		if( ! $this->columns ) $this->set_columns();

		// Going on...
		// Indexes exist and correct. Getting data by indexes.
		if ( $this->where && $this->check__column_index( $this->where ) ) {
            // @ToDo temporary solution. The index sometimes is wrong. And we have to check the network by alternative getting data way
            $data = $this->get_data__by_index(
                $this->columns,
                $this->offset,
                $this->amount
            );
            if( $data == null ) {
                $data = $this->get_data__by_bruteforce(
                    $this->columns,
                    $this->offset,
                    $this->amount
                );
            }
            return $data;
			// No index. Bruteforce solution.
		} else {
			return $this->get_data__by_bruteforce(
				$this->columns,
				$this->offset,
				$this->amount
			);
		}
	}

	/**
	 * @param $data
	 *
	 * @return bool|float|int
	 */
	public function insert( $data ) {

		$this->insert__actions_with_data( $data );

		fseek( $this->stream, 0, SEEK_END );
		$res = fwrite( $this->stream, $data );
		if ( ! $res ) {
			$err = error_get_last();
			Err::add( $err['message'] );
			return false;
		} else {
			$inserted = $res / ( $this->meta->line_length );
			$this->meta->rows += $inserted;
			$this->meta->save();
			return $inserted;
		}
	}

	public function delete() {

		// Clear file
		ftruncate( $this->stream, 0 );

		// Clear indexes and data about indexes
		foreach ( $this->meta->indexes as $column_name => &$column ){
			switch ( $column['type'] ){
				case 'hash':
					$this->indexes[ $column_name ]->clear_hash();
					break;
				case 'binary_tree':
					$this->indexes[ $column_name ]->clear_tree();
					break;
				case 'b_tree':
					$this->indexes[ $column_name ]->clear_tree();
					break;
			}
			$column['status'] = false;
		}

		// Null additional data
		$this->meta->rows = 0;
		$this->meta->save();
	}

	private function get_data__by_index( $cols, $offset, $limit ) {

		$addresses = array();

		foreach ( $this->where as $column => $values){

			switch ( $this->index_type ){
				case 'binary_tree':
					foreach ( $values as $value )
						$addresses[] = $this->indexes[ $this->indexed_column ]->node__get_by_key( $value )->link;
					break;
				case 'b_tree':
					foreach ( $values as $value )
						$addresses[] = $this->indexes[ $this->indexed_column ]->get_elem( $value )['val'];
					break;
			}
		}

		error_log(var_export($addresses,1));
		$this->get_rows__to_buffer( $addresses );
        error_log(var_export($this->buffer,1));
		if( ! $this->buffer )
			return false;

		while ( $this->buffer ) {
			$res = $this->buffer__pop_line_to_array( $cols );
			if( $res )
				$this->buffer_output[] = $res;
		}

		return $this->buffer_output;
	}

	private function get_data__by_bruteforce( $cols, $offset = 0, $amount = 0 ) {

		$this->get_rows_range__to_buffer( $offset, $amount );
		if( ! $this->buffer )
			return false;

		while ( $this->buffer ) {
			$res = $this->buffer__pop_line_to_array( $cols );
			if( $res )
				$this->buffer_output[] = $res;
		}

		return $this->buffer_output;
	}

	/**
	 * @param int $offset
	 * @param int $amount
	 *
	 * @return void
	 */
	private function get_rows_range__to_buffer( $offset = 0, $amount = 0 ) {

		$byte_offset = $offset * ( $this->meta->line_length );
		$byte_amount = $amount * ( $this->meta->line_length );
		$byte_amount = $byte_amount > self::FS_MEMORY_CAP || $byte_amount === 0 ? self::FS_MEMORY_CAP : $byte_amount;

		// Set needed position
		if( fseek( $this->stream, $byte_offset, SEEK_SET ) === -1){
			Err::add('Can not find file position: '. error_get_last()['message']);
		}

		// Get data
		$this->buffer = fread( $this->stream, $byte_amount);
		if( ! $this->buffer ){
			Err::add('Can not read data: '. error_get_last()['message']);
		}
	}

	private function get_rows__to_buffer( $addresses = array() ) {

		foreach ( $addresses as $address ){

		    if( ! $address )
		        continue;

			$byte_offset = ($address - 1)  * ( $this->meta->line_length );
			$byte_amount = $this->meta->line_length;

			// Set needed position
			if( fseek( $this->stream, $byte_offset, SEEK_SET ) === -1){
				Err::add('Can not find file position: '. error_get_last()['message']);
			}

			// Get data
			$this->buffer .= fread( $this->stream, $byte_amount);

			if( ! $this->buffer ){
				Err::add('Can not read data: '. error_get_last()['message']);
			}
		}

	}

	private function buffer__pop_line(){
		$pos          = strpos( $this->buffer, $this->row_separator );
		$line         = substr( $this->buffer, 0, $pos );
		$this->buffer = substr_replace( $this->buffer, '', 0, $pos + 1 );
		return $line;
	}

	/**
	 * @param $cols
	 *
	 * @return array|bool|false|null
	 */
	private function buffer__pop_line_to_array( $cols ){

		$this->buffer_row = $this->buffer__pop_line();

		$read_line_offset = 0;
		foreach ( $this->meta->cols as $name => $col ){

			// Skip if we don't want this col
			if( ! in_array( $name, $cols ) ) {
				$read_line_offset += $col['length'];
				continue;

			// Select this col
			}else{

				$tmp = str_replace(
					$this->row_placeholder,
					'',
					substr( $this->buffer_row, $read_line_offset, $col['length'] )
				);

				if( $this->where_columns && in_array( $name, $this->where_columns ) )
					if ( ! in_array( $tmp, $this->where[ $name ] ) )
						return false;

				$line[] = $tmp;
				$read_line_offset += $col['length'];
			}
		}


		if( $cols )
			$line = array_combine( $cols, $line );

		return $line;
	}

	/**
	 * Recursive
	 * Check columns for existence
	 *
	 * @param $column
	 *
	 * @return bool
	 */
	private function check__column( $column ) {
		if ( is_array( $column ) ) {
			foreach ( $column as $col ) {
				$result = $this->check__column( $col );
				if ( $result !== true ) {
					return $result;
				}
			}
		} else {
			if ( ! isset( $this->meta->cols[ $column ] ) ) {
				return $column;
			}
		}

		return true;
	}

	/**
	 * Recursive
	 * Check columns for existence
	 *
	 * @param $column
	 *
	 * @return bool
	 */
	private function check__column_index( $column = '' ) {
		if ( is_array( $column ) ) {
			foreach ( $column as $name => $col ) {
				$result = $this->check__column_index( $name );
				if ( $result !== true ) {
					return $result;
				}
			}
		} else {
			if ( ! ( isset( $this->meta->indexes[ $column ] ) && $this->meta->indexes[ $column ]['status'] === 'ready' ) ) {
				return false;
			}else{
				$this->indexed_column = $column;
				$this->index_type     = $this->meta->indexes[ $column ]['type'];
			}
		}

		return true;
	}

	private function insert__actions_with_data( &$data ){

		$columns_name = array_keys( $this->meta->cols );
		$indexes      = array_keys( $this->meta->indexes );

		$number = 0;
		$data_raw = '';
		$result = true;
		foreach ( $data as &$datum ) {

			if(
				$this->insert__check_data_format( $datum ) &&
				$this->insert__create_index( ++$number, $datum, $indexes, $columns_name ) &&
				$this->insert__convert_data_to_storage_format( $datum, $columns_name )
			){
				$data_raw .= $datum . "\n";
			}else{
				$number--;
			}
		}

		$data = $data_raw;
	}

	private function insert__check_data_format( &$data ) {
		if ( count( $data ) === $this->meta->cols_num ){
			return true;
		}else{
			Err::add( 'Cols number does not match. Given ' . count( $data ) . ', needed: ' . $this->meta->cols_num );
			return false;
		}
	}

	private function insert__convert_data_to_storage_format( &$data, $columns_name ) {

		$tmp = '';
		foreach ( $data as $name => $col ) {
			$tmp .= str_pad(
				substr(
					$col,
					0,
					$this->meta->cols[ $columns_name [ $name ] ]['length']
				),
				$this->meta->cols[ $columns_name [ $name ] ]['length'],
				"\x00",
				STR_PAD_LEFT
			);
		}
		$data = $tmp;

		return true;
	}

	private function insert__create_index( $number, $data, $indexes, $columns_name ) {

		foreach ( $indexes as $index ){

			switch ( $this->meta->indexes[ $index ]['type'] ){
				case 'hash':
					$result = $this->index__create__hash( $data[ $index ], $number, $data[ array_search( $index, $columns_name ) ]  );
					break;
				case 'binary_tree':
					$result = $this->index__create__bin_tree( $index, $number, $data[ array_search( $index, $columns_name ) ] );
					break;
				case 'b_tree':
					$result = $this->index__create__b_tree( $index, $number, $data[ array_search( $index, $columns_name ) ] );
					break;
				default:
					$result = false;
					break;
			}

			if( is_int( $result ) && $result > 0 ){
				$this->meta->indexes[ $index ]['status'] = 'ready';
				$out = true;
			}elseif( $result === true ){
				Err::add('Insertion', 'Duplicate key for column "' . $index . '": ' . $data[ array_search( $index, $columns_name ) ] );
				$out = false;
			}elseif( $result === false ){
				Err::add('Insertion', 'No index added for column "' . $index . '": ' . array_search( $index, $columns_name ) );
				$out = false;
			}else{
				$out = false;
			}

			return $out;

		}
	}

	private function index__create__hash( $index, $number, $key ) {
//		return $this->indexes[ $index ]->node__add( $key, $this->meta->rows + $number );
		return false;
	}

	private function index__create__bin_tree( $index, $number, $key ) {
		return $this->indexes[ $index ]->add_key( $key, $this->meta->rows + $number );
	}

	private function index__create__b_tree( $index, $number, $key ) {
		return $this->indexes[ $index ]->insert( $key, $this->meta->rows + $number );
	}
}