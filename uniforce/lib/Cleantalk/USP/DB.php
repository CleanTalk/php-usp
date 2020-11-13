<?php

namespace Cleantalk\USP;

use \Cleantalk\USP\Templates\Singleton as Singleton;

class DB extends \PDO implements \Cleantalk\USP\Common\DB {
	
	use Singleton;
	
	private static $instance;
	
	/**
	 * @var string
	 */
	public $query = '';
	
	/**
	 * @var \PDOStatement
	 */
	public $query_result = '';
	
	/**
	 * @var int
	 */
	public $rows_affected;
	
	function init( ...$params ){
		
		if( $params[0] ){
			$dsn      = $params[0];
			$username = $params[1];
			$password = $params[2];
			$options  = $params[3] ? $params[3] : array(
				\PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION, // Handle errors as an exceptions
				\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,        // Set default fetch mode as associative array
				\PDO::MYSQL_ATTR_SSL_CA => CT_USP_DATA_SSL_CERT . 'ca.pem',
				\PDO::MYSQL_ATTR_SSL_CERT => CT_USP_DATA_SSL_CERT . 'client-cert.pem',
				\PDO::MYSQL_ATTR_SSL_KEY => CT_USP_DATA_SSL_CERT . 'client-key.pem',
			);
			
			parent::__construct( $dsn, $username, $password, $options );
			
		}else{
			self::$instance = null;
		}
		
	}
	
	/**
	 * Safely replace place holders
	 *
	 * @param string $query
	 * @param array $param
	 *
	 * @return bool|\PDOStatement
	 */
	function prepare( $query, $param = array() ) {
		return parent::prepare( $query, $param );
	}
	
	/**
	 * Executes a query to DB
	 *
	 * @param string $query
	 * @param int $mode
	 * @param null $arg3
	 * @param array $ctorargs
	 *
	 * @return false|mixed|\PDOStatement
	 */
	function query( $query, $mode = \PDO::ATTR_DEFAULT_FETCH_MODE, $arg3 = null, $ctorargs = array() ){
		$this->query = $query;
		$this->query_result = parent::query( $query );
		$this->rows_affected = $this->query_result->rowCount();
		return $this->query_result;
	}
	
	/**
	 * @param $query
	 *
	 * @return bool|int|void
	 */
	function execute( $query ) {
		$this->query = $query;
		$this->rows_affected = parent::exec( $query );
		return $this->rows_affected;
	}
	
	
	/**
	 * Fetch first column from query.
	 * May receive raw or prepared query.
	 *
	 * @param string $query
	 * @param string $response_type
	 *
	 * @return array|object|void|null
	 */
	function fetch( $query = '', $response_type = 'array' ) {
		
		if( $this->query !== $query)
			
			$this->query( $query );
		
		switch( $response_type ){
			case 'array':
				$response_type = \PDO::FETCH_ASSOC;
				break;
			case 'obj':
				$response_type = \PDO::FETCH_OBJ;
				break;
			case 'num':
				$response_type = \PDO::FETCH_NUM;
				break;
		}
		
		return $this->query_result
			->fetch( $response_type );
		
	}
	
	/**
	 * Fetch all result from query.
	 * May receive raw or prepared query.
	 *
	 * @param string $query
	 * @param string $response_type
	 *
	 * @return array|object|null
	 */
	function fetch_all( $query = '', $response_type = 'array' ) {
		
		switch($response_type){
			case 'array':
				$response_type = \PDO::FETCH_ASSOC;
				break;
			case 'obj':
				$response_type = \PDO::FETCH_OBJ;
				break;
			case 'num':
				$response_type = \PDO::FETCH_NUM;
				break;
		}
		
		return parent::query( $query )
             ->fetchAll( $response_type );
	}
}