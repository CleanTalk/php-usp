<?php

namespace Cleantalk\Uniforce;

use Cleantalk\Variables\Server;

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

class Helper extends \Cleantalk\Common\Helper {
	
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
                CURLOPT_USERAGENT => 'SPBCT-wordpress/' . (defined('SPBC_VERSION') ? SPBC_VERSION : 'unknown') . '; ' . Server::get('SERVER_NAME'),
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
                global $wpdb;
                $param = $quotes . $wpdb->_real_escape($param) . $quotes;
                break;
        }
        return $param;
    }

    public static function time__get_interval_start( $interval = 300 ){
        return time() - ( ( time() - strtotime( date( 'd F Y' ) ) ) % $interval );
    }

    static function get_mime_type($data )
    {
        if( @file_exists( $data )){
            $mime = mime_content_type( $data );
        }else{
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_buffer($finfo, $data);
            finfo_close($finfo);
        }
        return $mime;
    }

    static function buffer__trim_and_clear_from_empty_lines( $buffer ){
        $buffer = (array) $buffer;
        foreach( $buffer as $indx => &$line ){
            $line = trim( $line );
            if($line === '')
                unset( $buffer[$indx] );
        }
        return $buffer;
    }

	static function buffer__parse__in_lines( $buffer ){
		$buffer = explode( "\n", $buffer );
		$buffer = self::buffer__trim_and_clear_from_empty_lines( $buffer );
		return $buffer;
	}

    static function buffer__parse__csv( $buffer ){
        $buffer = explode( "\n", $buffer );
        $buffer = self::buffer__trim_and_clear_from_empty_lines( $buffer );
        foreach($buffer as &$line){
            $line = str_getcsv($line, ',', '\'');
        }
        return $buffer;
    }

}