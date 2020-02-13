<?php

namespace Cleantalk\Templates;
/**
//* @method \Cleantalk\Common\State save()
*/
trait Storage {

	/**
	 * Storage for dynamically called properties
	 * @var array
	 */
	public $name;
	public $storage = array();

	public function __construct( $name, $data ) {
		$this->storage = $data;
		$this->name    = $name;
	}

	/**
	 * @param $data_name
	 * @param array $data
	 *
	 * @return Storage
	 */
	public function convertToStorage( $data_name, array $data ){
		foreach ( $data as $name => &$item ) {
			if ( is_array( $item ) ) {
				$item = $this->convertToStorage( $name, $item );
			}
		}
		return new \Cleantalk\Common\Storage( $data_name, $data );
	}

	public function convertToArray( $data = null ){
		$data = is_null( $data ) ? $this : $data;
		$data = $data->storage;
		foreach ( $data as $name => &$item ) {
			if ( $item instanceof \Cleantalk\Common\Storage ) {
				$item = $this->convertToArray( $item );
			}
		}
		return $data;
	}

	/**
	 * Saves option to file as array
	 * Creates file if it doesn't exist*
	 */
	public function save()
	{
		$filename = CT_USP_DATA . $this->name . '.php';
		if ( !file_exists( $filename ) ){
			file_put_contents( $filename , "<?php\n");
			\Cleantalk\Common\File::inject__variable(
				CT_USP_DATA . $this->name . '.php',
				$this->name,
				$this->convertToArray()
			);
		}else{
			\Cleantalk\Common\File::replace__variable(
				CT_USP_DATA . $this->name . '.php',
				$this->name,
				$this->convertToArray()
			);
		}
	}

	public function __get($name)
	{
		return isset( $this->storage[ $name ] )
			? $this->storage[ $name ]
			: ( isset($this->data->$name )
				? $this->data->$name
				: null
			);
	}

	public function __set($name, $value)
	{
		$this->storage[ $name ] = $value;
	}

	public function __isset($name)
	{
		return isset( $this->storage[ $name ] );
	}

	public function __unset($name)
	{
		unset( $this->storage[ $name ] );
	}

}