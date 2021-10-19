<?php

namespace Cleantalk\USP\Uniforce;

use Cleantalk\USP\Variables\Server;

/**
 * Cleantalk's hepler class
 * 
 * Mostly contains request's wrappers.
 *
 * @version 2.4
 * @package Cleantalk
 * @subpackage Helper
 * @author Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * @license GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * @see https://github.com/CleanTalk/php-antispam 
 *
 */

class Helper extends \Cleantalk\USP\Common\Helper {
	
	static public function http__user_agent(){
		return defined( 'SPBCT_USER_AGENT' ) ? SPBCT_USER_AGENT : static::DEFAULT_USER_AGENT;
	}

	/**
     * Function sends raw http request
     *
     * May use 4 presets(combining possible):
     * get_code - getting only HTTP response code
     * async    - async requests
     * get      - GET-request
     * ssl      - use SSL
     *
     * @param string       $url     URL
     * @param array        $data    POST|GET indexed array with data to send
     * @param string|array $presets String or Array with presets: get_code, async, get, ssl, dont_split_to_array
     * @param array        $opts    Optional option for CURL connection
     *
     * @return mixed|array|string (array || array('error' => true))
     */
    static public function http__request($url, $data = array(), $presets = null, $opts = array())
    {
        // Set APBCT User-Agent and passing data to parent method
        $opts = self::array_merge__save_numeric_keys(
            array(
                CURLOPT_USERAGENT => 'SPBCT-uni/' . (defined('SPBCT_VERSION') ? SPBCT_VERSION : 'unknown') . '; ' . Server::get('SERVER_NAME'),
            ),
            $opts
        );

        return parent::http__request($url, $data, $presets, $opts);
    }

    /**
     * Escapes MySQL params
     *
     * @param string|int $param
     * @param string     $quotes
     *
     * @return int|string
     */
    public static function db__prepare_param($param, $quotes = '\'')
    {
        if(is_array($param)){
            foreach($param as &$par){
                $par = self::db__prepare_param($par);
            }
        }
        switch(true){
            case is_numeric($param):
                $param = intval($param);
                break;
            case is_string($param) && strtolower($param) == 'null':
                $param = 'NULL';
                break;
            case is_string($param):
                $param = $quotes . \PDO::prepare ( $param ) . $quotes;
                break;
        }
        return $param;
    }

}