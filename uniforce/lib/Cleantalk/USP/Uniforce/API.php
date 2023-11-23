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
        $result = $do_check ? parent::check_response($result, 'security_logs') : $result;

        return $result;
    }

    static public function method__get_api_key($product_name, $email, $website, $platform, $timezone = null, $language = null, $user_ip = null, $wpms = false, $white_label = false, $hoster_api_key = '', $do_check = true)
    {
        $result = parent::method__get_api_key($product_name, $email, $website, $platform, $timezone = null, $language = null, $user_ip = null, $wpms = false, $white_label = false, $hoster_api_key = '', false);
        if ($do_check) {
            $parent_check_result = parent::check_response($result, 'get_api_key');
            if (is_array($parent_check_result) && isset($parent_check_result['error'])) {
                return $parent_check_result;
            } else {
                if (!is_string($result)) {
                    return array(
                        'error' => 'Unknown server response format.',
                    );
                } else {
                    $result = json_decode($result, true);
                    if(empty($result)){
                        return array(
                            'error' => 'JSON_DECODE_ERROR',
                        );
                    }
                    $result = isset($result['data']) ? $result['data'] : $result;

                    if ( ! isset($result['auth_key'])) {
                        $result['valid'] = 0;
                        if (isset($result['account_exists']) && $result['account_exists'] == 1) {
                            $result['error'] = 'Account already exists. Please, insert the access key from your CleanTalk control panel.';
                        } else {
                            $result['error'] = 'Unknown error. Please, insert the access key from your CleanTalk control panel.';
                        }
                    }
                    else {
                        $result['valid'] = 1;
                    }
                    return $result;
                }
            }
        }

        return $result;
    }
}
