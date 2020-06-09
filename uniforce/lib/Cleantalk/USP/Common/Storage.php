<?php

namespace Cleantalk\USP\Common;

/**
 * @method array_values()
 */
class Storage extends \ArrayObject {

	/**
	 * Storage for dynamically called properties
	 * @var array
	 */
	public $storage_name;
	public $directory = CT_USP_DATA;

	public function __construct( $name, $data = array(), $directory = null ) {

		$this->directory = $directory ? $directory : $this->directory;
		$data = $data === null ? $this->get( $name ) : $data; // Get data from directory if it's unset
		parent::__construct( $data, \ArrayObject::STD_PROP_LIST|\ArrayObject::ARRAY_AS_PROPS );
		$this->storage_name = $name;
	}

	/**
	 * Converts array to \Cleantalk\USP\Common\Storage type.
	 * Recursive.
	 *
	 * @param $data_name
	 * @param array $data
	 *
	 * @return Storage
	 */
	public function convertToStorage( $data_name, $data ){
		foreach ( $data as $name => &$item ) {
			if ( is_array( $item ) ) {
				$item = $this->convertToStorage( $name, $item );
			}
		}
		return new \Cleantalk\USP\Common\Storage( $data_name, $data );
	}

	/**
	 * Converts \Cleantalk\USP\Common\Storage to array type.
	 * Recursive.
	 *
	 * @param null $data
	 *
	 * @return array
	 */
	public function convertToArray( $data = null ){
		$data = is_null( $data ) ? $this : $data;
		$tmp = array();
		foreach ( $data as $name => $item ) {
			$tmp[ $name ] = $item instanceof \Cleantalk\USP\Common\Storage
				? $this->convertToArray( $item )
				: $item;
		}
		return $tmp;
	}

	/**
	 * Get option from file
	 *      If file doesn't exist returns empty array
	 *          If variable in the file doesn't exist returns empty array
	 *
	 * @param $option_name
	 *
	 * @return array
	 */
	protected function get($option_name)
	{
		$filename = $this->directory . $option_name . '.php';
		if ( file_exists( $filename ) ){
			require $filename;
		}
		return isset($$option_name) ? $$option_name : array();
	}



	/**
	 * Saves option to file as array
	 * Creates file at /data/{$this->storage_name}.php if it doesn't exist
	 */
	public function save()
	{
		$filename = $this->directory . $this->storage_name . '.php';
		file_put_contents( $filename , "<?php\n");
		\Cleantalk\USP\Common\File::inject__variable(
			$this->directory . $this->storage_name . '.php',
			$this->storage_name,
			$this->convertToArray()
		);
	}

	/**
	 * Unset the option in the State class
	 * Deletes file (/data/{$this->storage_name}.php) if exists
	 *
	 * @param $option_name
	 */
	public function delete( $option_name = '' )
	{
		// Try to delete option with passed name
		if($option_name){
			if ( isset( $this->$option_name ) )
				unset($this->$option_name);

		// Delete this storage if no arguments passed
		}else{
			$option_name = $this->storage_name;
		}

		// Delete file with option
		$filename = $this->directory . $option_name . '.php';
		if ( file_exists( $filename ) ){
			unlink( $filename );
		}
	}

	/**
	 * Check if the storage is not empty
	 *
	 * @return bool
	 */
	public function is_empty(){
		foreach ( $this as $this1 ) {
			return ! isset( $this1 );
		}
		return true;
	}

	/**
	 * Attempt to get data from internal storage
	 *  then from $this->settings->key
	 *      then from $this->data storage
	 *          then from $this->get( $name ) method
	 * else rerun null
	 * @param $name
	 *
	 * @return mixed|null
	 */
	public function __get( $name )
	{
		if($this[$name])
			return $this[$name];

		$value = $this->get( $name )
			? $this->convertToStorage( $name, $this->get( $name ) )
			: new Storage( $name, array() );

		$this[$name] = $value;

		return $value;
	}

	public function __call($func, $argv)
	{
		if (!is_callable($func) || substr($func, 0, 6) !== 'array_')
		{
			throw new BadMethodCallException(__CLASS__.'->'.$func);
		}
		return call_user_func_array($func, array_merge(array($this->getArrayCopy()), $argv));
	}

	public function __set( $name, $value ){
		$this[ $name ] = $value;
	}

	public function __isset( $name ){
		return isset( $this[ $name ] );
	}
}