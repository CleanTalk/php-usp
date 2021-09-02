<?php

namespace Cleantalk\USP\Common;

/**
 * CleanTalk abstract Data Base driver.
 * Shows what should be inside.
 * Uses singleton pattern.
 *
 * @version 1.0
 * @author Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * @license GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * @see https://github.com/CleanTalk/php-antispam
*/

interface DB
{
	/**
	 * Executes a query to DB
	 *
	 * @param string $query
	 *
	 * @return mixed
	 */
	function q( $query );
	
	/**
	 * Safely replace place holders
	 *
	 * @param string $query
	 * @param array $param
	 *
	 * @return $this
	 */
	function prepare($query, $param = array() );
	
	/**
	 * Run any raw request
	 *
	 * @param $query
	 *
	 * @return bool|int Raw result
	 */
	function execute($query);
	
	/**
	 * Fetch first column from query.
	 * May receive raw or prepared query.
	 *
	 * @param bool $query
	 * @param bool $response_type
	 *
	 * @return array|object|void|null
	 */
	function fetch($query = false, $response_type = false);
	
	/**
	 * Fetch all result from query.
	 * May receive raw or prepared query.
	 *
	 * @param bool $query
	 * @param bool $response_type
	 *
	 * @return array|object|null
	 */
	function fetch_all($query = false, $response_type = false);
}