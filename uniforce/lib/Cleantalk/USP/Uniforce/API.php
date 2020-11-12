<?php

namespace Cleantalk\USP\Uniforce;

/**
 * Class CleantalkAPI.
 * Compatible only with Wordpress.
 *
 * @depends       \Cleantalk\USP\Common\API
 * 
 * @version       1.0
 * @author        Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * @license       GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * @see           https://github.com/CleanTalk/wordpress-antispam
 */
class API extends \Cleantalk\USP\Common\API{
	
	static public function get_agent(){
		return defined( 'SPBCT_AGENT' ) ? SPBCT_AGENT : static::DEFAULT_AGENT;
	}

    /**
     * Wrapper for security_logs API method.
     * Sends Securitty Firewall logs to the cloud.
     *
     * @param string $api_key
     * @param array  $data
     * @param bool   $do_check
     *
     * @return array|bool|mixed
     */
    static public function method__security_logs__sendFWData($api_key, $data, $do_check = true)
    {

        $request = array(
            'auth_key'    => $api_key,
            'method_name' => 'security_logs',
            'timestamp'   => time(),
            'data_fw'     => json_encode($data),
            'rows_fw'     => count($data),
        );

        $result = static::send_request($request);
        $result = $do_check ? static::check_response($result, 'security_logs') : $result;

        return $result;
    }
	
}