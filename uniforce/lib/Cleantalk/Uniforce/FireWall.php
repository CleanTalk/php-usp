<?php

namespace Cleantalk\Uniforce;

use Cleantalk\Common\Err;
use Cleantalk\Common\File;
use Cleantalk\Variables\Get;
use Cleantalk\Variables\Server;

class FireWall extends \Cleantalk\Security\FireWall
{

    public $bfp_enabled       = true;

    public function __construct($params = array())
    {
        parent::__construct($params);

        $this->bfp_enabled = isset($params['waf_enabled']) ? (bool)$params['waf_enabled'] : false;

    }

    public static function get_module_statistics()
    {
        global $uniforce_sfw_protection,
               $uniforce_waf_protection,
               $uniforce_bfp_protection,
               $uniforce_sfw_last_update,
               $uniforce_sfw_entries,
               $uniforce_sfw_last_logs_send,
               $uniforce_waf_trigger_count,
               $uniforce_sfw_trigger_count,
               $uniforce_bfp_trigger_count,
               $uniforce_bfp_last_logs_send;

        $info = '';
        if( (! empty( $uniforce_sfw_protection ) && $uniforce_sfw_protection) || (! empty( $uniforce_waf_protection ) && $uniforce_waf_protection) ) {
            $sfw_updated_time = $uniforce_sfw_last_update ? date('M d Y H:i:s', $uniforce_sfw_last_update) : 'never.';
            $sfw_send_logs_time = $uniforce_sfw_last_logs_send ? date('M d Y H:i:s', $uniforce_sfw_last_logs_send) : 'never.';
            $info .= 'Security FireWall was updated: ' . $sfw_updated_time . '<br>';
            $info .= 'Security FireWall contains: ' . $uniforce_sfw_entries . ' entires.<br>';
            $info .= 'Security FireWall logs were sent: ' . $sfw_send_logs_time . '<br>';
            $info .= '<br>';
        }
        if( ! empty( $uniforce_bfp_protection ) && $uniforce_bfp_protection ) {
            $bfp_send_logs_time = $uniforce_bfp_last_logs_send ? date('M d Y H:i:s', $uniforce_bfp_last_logs_send) : 'never.';
            $info .= 'BruteForce Protection was triggered: ' . $uniforce_bfp_trigger_count . '<br>';
            $info .= 'BruteForce Protection logs were sent: ' . $bfp_send_logs_time . '<br>';
            $info .= '<br>';
        }
        return $info;
    }

    static public function ip__get($ip_types = array('real', 'remote_addr', 'x_forwarded_for', 'x_real_ip', 'cloud_flare')){

        $result = (array)Helper::ip__get($ip_types);

        global $uniforce_apikey;

        if(isset($_GET['spbct_test_ip'], $_GET['spbct_test'], $uniforce_apikey) && $_GET['spbct_test'] == md5($uniforce_apikey)){
            $ip_type = Helper::ip__validate($_GET['spbct_test_ip']);
            $test_ip = $ip_type == 'v6' ? Helper::ip__v6_normalize($_GET['spbct_test_ip']) : $_GET['spbct_test_ip'];
            if($ip_type)
                $result['test'] = $test_ip;
        }

        return $result;
    }

    public function ip__test()
    {
        $fw_results = array();
        $datafile_path = CT_USP_ROOT . 'data/sfw_nets.php';

        if ( file_exists($datafile_path) ) {
            require_once $datafile_path;
            if ( ! empty( $sfw_nets ) ) {

                foreach ( $this->ip_array as $ip_origin => $current_ip ) {

                    $ip_type = Helper::ip__validate($current_ip);

                    // v4
                    if ($ip_type && $ip_type == 'v4') {

                        $current_ip_v4 = sprintf("%u", ip2long($current_ip));

                        $found_network['found'] = false;

                        foreach ( $sfw_nets as $net ) {
                            if ( $net[3] == sprintf("%u", ip2long($current_ip)) & $net[7] ) {
                                $found_network['found']       = true;
                                $found_network['network']     = $net[3];
                                $found_network['mask']        = $net[7];
                                $found_network['status']      = $net[8];
                                $found_network['is_personal'] = $net[10];
                            }
                        }

                    // v6
                    } elseif ( $ip_type ) {

                        $current_ip_txt = explode(':', $current_ip);
                        $current_ip_1 = hexdec($current_ip_txt[0] . $current_ip_txt[1]);
                        $current_ip_2 = hexdec($current_ip_txt[2] . $current_ip_txt[3]);
                        $current_ip_3 = hexdec($current_ip_txt[4] . $current_ip_txt[5]);
                        $current_ip_4 = hexdec($current_ip_txt[6] . $current_ip_txt[7]);

                        foreach ($sfw_nets as $net) {
                            if (
                                $net[0] == sprintf("%u", ip2long($current_ip)) & $net[4] &&
                                $net[1] == sprintf("%u", ip2long($current_ip)) & $net[5] &&
                                $net[2] == sprintf("%u", ip2long($current_ip)) & $net[6] &&
                                $net[3] == sprintf("%u", ip2long($current_ip)) & $net[7]
                            ) {
                                $found_network['found']       = true;
                                $found_network['network']     = $net[3];
                                $found_network['mask']        = $net[7];
                                $found_network['status']      = $net[8];
                                $found_network['is_personal'] = $net[10];
                            }
                        }
                    }

                    // In base
                    if ( $found_network['found'] ) {

                        switch ( $found_network['status'] ) {
                            case 2:
                                $fw_results[] = array('ip' => $current_ip, 'is_personal' => (bool)$found_network['is_personal'], 'status' => 'PASS_BY_TRUSTED_NETWORK',);
                                $this->tc_skip = true;
                                break;
                            case 1:
                                $fw_results[] = array('ip' => $current_ip, 'is_personal' => (bool)$found_network['is_personal'], 'status' => 'PASS_BY_WHITELIST',);
                                $this->tc_skip = true;
                                break;
                            case 0:
                                $fw_results[] = array('ip' => $current_ip, 'is_personal' => (bool)$found_network['is_personal'], 'status' => 'DENY',);
                                break;
                            case -1:
                                $fw_results[] = array('ip' => $current_ip, 'is_personal' => (bool)$found_network['is_personal'], 'status' => 'DENY_BY_NETWORK',);
                                break;
                            case -2:
                                $fw_results[] = array('ip' => $current_ip, 'is_personal' => (bool)$found_network['is_personal'], 'status' => 'DENY_BY_DOS',);
                                break;
                        }

                        // Not in base
                    } else
                        $fw_results[] = array('ip' => $current_ip, 'is_personal' => false, 'status' => 'PASS',);
                }

                $current_fw_result_priority = 0;

                foreach ($fw_results as $fw_result) {
                    $priority = array_search($fw_result['status'], $this->statuses_priority) + ($fw_result['is_personal'] ? count($this->statuses_priority) : 0);
                    if ($priority >= $current_fw_result_priority) {
                        $current_fw_result_priority = $priority;
                        $this->result = $fw_result['status'];
                        $this->passed_ip = $fw_result['ip'];
                        $this->blocked_ip = $fw_result['ip'];
                    }
                }

                if ( ! $this->tc_enabled && $priority == 0 ) {
                    $this->result = null;
                    $this->passed_ip = '';
                    $this->blocked_ip = '';
                }

            } else {
                // @ToDo error handler
                // throw new Exception( 'UniForce: Data file ' . $datafile_path . ' is empty.');
            }
        } else {
            // @ToDo error handler
            // throw new Exception( 'UniForce: Data file ' . $datafile_path . ' does not exist.');
        }


    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function bfp_check()
    {
        global $spbct_checkjs_val;

        if( isset( $_COOKIE['spbct_authorized'] ) && $_COOKIE['spbct_authorized'] == $spbct_checkjs_val ) {
            return true;
        }

        self::security__update_auth_logs( 'auth_failed' );

        $black_list = CT_USP_ROOT . 'data/bfp_blacklist.php';
        $fast_black_list = CT_USP_ROOT . 'data/bfp_fast_blacklist.php';
        $block_time = 3600; // 1 hour
        $allowed_count = 10;
        $allowed_interval = 900; // 15 min

        if ( ! file_exists($black_list) ) {
            throw new \Exception( 'UniForce: Blacklist ' . $black_list . ' does not exist.');
        }

        require_once $black_list;

        if ( ! isset( $bad_ips ) ) {
            throw new \Exception( 'UniForce: Blacklist ' . $black_list . ' have the wrong format.');
        }

        if ( ! file_exists($fast_black_list) ) {
            throw new \Exception( 'UniForce: Fast-Blacklist ' . $fast_black_list . ' does not exist.');
        }

        require_once $fast_black_list;

        if ( ! isset( $fast_bad_ips ) ) {
            throw new \Exception( 'UniForce: Fast-Blacklist ' . $fast_black_list . ' have the wrong format.');
        }

        $current_ip = Helper::ip__get( array('real') );

        $found_ip['found'] = false;

        // Check against black list
        if( ! empty( $bad_ips ) ) {
            foreach( $bad_ips as $bad_ip => $bad_ip_added ) {
                if( $bad_ip == $current_ip ) {
                    $found_ip['found'] = true;
                    $found_ip['added'] = $bad_ip_added;
                }
            }
        }

        if( $found_ip['found'] ) {
            if( $found_ip['added'] + $block_time < time() ) {
                // Remove the IP from the blacklist and proceed the checking
                unset( $bad_ips[$current_ip] );
                File::replace__variable( $black_list, 'bad_ips', $bad_ips );
            } else {
                $this->result = 'DENY_BY_BFP';
                $this->blocked_ip = $current_ip;
                return false;
            }
        }

        $found_ip['found'] = false;

        $js_on = spbct_js_test();

        // Check count of logins
        if( ! empty( $fast_bad_ips ) ) {
            foreach( $fast_bad_ips as $fast_bad_ip => $fast_bad_ip_info ) {
                if( $fast_bad_ip == $current_ip && $fast_bad_ip_info['added'] + $allowed_interval > time() ) {
                    $found_ip['found'] = true;
                    $found_ip['added'] = $fast_bad_ip_info['added'];
                    $found_ip['js_on'] = $js_on;
                    $found_ip['count'] = ++$fast_bad_ip_info['count'];
                } else {
                    unset( $fast_bad_ips[$current_ip] );
                }
            }
        }

        if( $found_ip['found'] ) {
            if( $found_ip['js_on'] == 1 ) {
                //increased allowed count to 20 if JS is on!
                $allowed_count = $allowed_count * 2;
            }
            if( $found_ip['count'] > $allowed_count ) {
                // Check count of the logins and move the IP to the black list.
                $bad_ips[$current_ip] = time();
                File::replace__variable( $black_list, 'bad_ips', $bad_ips );
                unset( $fast_bad_ips[$current_ip] );
                File::replace__variable( $fast_black_list, 'fast_bad_ips', $fast_bad_ips );
                $this->result = 'DENY_BY_BFP';
                $this->blocked_ip = $current_ip;
                return false;
            } else {
                $fast_bad_ips[$current_ip] = array(
                    'added' => $found_ip['added'],
                    'js_on' => $found_ip['js_on'],
                    'count' => $found_ip['count']
                );
                File::replace__variable( $fast_black_list, 'fast_bad_ips', $fast_bad_ips );
                return true;
            }
        } else {
            $fast_bad_ips[$current_ip] = array(
                'added' => time(),
                'js_on' => $js_on,
                'count' => 1
            );
            File::replace__variable( $fast_black_list, 'fast_bad_ips', $fast_bad_ips );
            return true;
        }

    }

    public static function is_logged_in( $cms ) {

        switch ( $cms ) {
            case 'Joomla' :
                if( class_exists('JFactory') ) {
                    $user = \JFactory::getUser();
                    if( $user->id ) {
                        if( empty( $_COOKIE['spbct_authorized'] ) ) {
                            self::security__update_auth_logs( 'login' );
                        }
                        return true;
                    }
                } else {
                    return false;
                }
                break;
            case 'Drupal7' :
                global $user;
                if( isset( $user->uid ) && $user->uid != 0 ) {
                    if( empty( $_COOKIE['spbct_authorized'] ) ) {
                        self::security__update_auth_logs( 'login' );
                    }
                    return true;
                } else {
                    return false;
                }
                break;
            case 'Drupal8' :
                if( class_exists('Drupal') ) {
                    $current= \Drupal::currentUser();
                    if ( ! $current->id() ) {
                        return false;
                    }
                    else {
                        if( empty( $_COOKIE['spbct_authorized'] ) ) {
                            self::security__update_auth_logs( 'login' );
                        }
                        return true;
                    }
                }
                break;
            case 'Bitrix' :
                if( class_exists( 'CUser') ) {
                    if( \CUser::IsAuthorized() ) {
                        if( empty( $_COOKIE['spbct_authorized'] ) ) {
                            self::security__update_auth_logs( 'login' );
                        }
                        return true;
                    } else {
                        return false;
                    }
                }
                break;
            default :
                // @ToDo implement universal logic for coockies checking
                return true;
                break;
        }

    }

    public static function security__update_auth_logs( $event ) {

        $params['event'] = $event;
        $params['page_url'] = Server::get('HTTP_HOST') . Server::get('REQUEST_URI');
        $params['user_agent'] = Server::get('HTTP_USER_AGENT');

        self::security__update_logs( $params );

    }

    public function update_logs( $ip, $result, $pattern = array() )
    {

        if ($ip === NULL || $result === NULL)
            return;

        global $salt;

        // Parameters
        $time            = time();
        $page_url        = addslashes((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
        $page_url        = substr($page_url, 0 , 4096);
        $http_user_agent = !empty($_SERVER['HTTP_USER_AGENT'])
            ? addslashes(htmlspecialchars(substr($_SERVER['HTTP_USER_AGENT'], 0, 300)))
            : 'unknown';
        $request_method  = $_SERVER['REQUEST_METHOD'];
        $x_forwarded_for = !empty($_SERVER['HTTP_X_FORWARDED_FOR'])
            ? $_SERVER['HTTP_X_FORWARDED_FOR']
            : '';
        $x_forwarded_for = addslashes(htmlspecialchars(substr($x_forwarded_for, 0 , 15)));
        $id              = md5($ip.$http_user_agent.$result);
        $pattern         = !empty($pattern)
            ? json_encode($pattern)
            : '';

        $log_path = CT_USP_ROOT . 'data/fw_logs/' . hash('sha256', $ip . $salt . $result) . '.log';

        if ( file_exists($log_path) ) {

            $log = file_get_contents($log_path);
            $log = explode(',', $log);

            $all_entries = isset($log[5]) ? $log[5] : 0;

            $log = array(
                $id,
                $ip,
                $time,
                $result,
                empty($pattern) ? NULL : $pattern,
                intval($all_entries) + 1,
                $page_url,
                $http_user_agent,
                $request_method,
                empty($x_forwarded_for) ? NULL : $x_forwarded_for,
            );

        } else {

            $log = array(
                $id,
                $ip,
                $time,
                $result,
                empty($pattern) ? NULL : $pattern,
                1,
                $page_url,
                $http_user_agent,
                $request_method,
                empty($x_forwarded_for) ? NULL : $x_forwarded_for,
            );

        }

        file_put_contents( $log_path, implode(',', $log), LOCK_EX );

    }

    public static function security__update_logs( $params = null ) {

        global $salt;

        $params_default = array(
            'event'        => null,
            'page_url'     => null,
            'user_agent'   => null,
            // @ToDo Unused params. Implement this logic to the next releases
            'page'         => null,
            'page_time'    => null,
            'browser_sign' => null,
        );
        $params = array_merge($params_default, $params);

        // Cutting to 1024 symbols
        $params['user_agent'] = is_string($params['user_agent'])
            ? substr($params['user_agent'], 0, 1024)
            : $params['user_agent'];

        $auth_ip = Helper::ip__get( array('real') );

        $values = array(
            'datetime'     => date('Y-m-d H:i:s'),
            'event'        => $params['event'],
            'auth_ip'      => $auth_ip,
            'page_url'     => $params['page_url'],
            'user_agent'   => $params['user_agent'],
        );

        // Inserting to the logs.
        $log_path = CT_USP_ROOT . 'data/security_logs/' . hash('sha256', $auth_ip . $salt . $values['event']) . '.log';

        if( file_exists( $log_path ) ) {

            $log = file_get_contents($log_path);
            $log = explode(',', $log);

            $attempts = isset($log[8]) ? $log[8] : 0;

            $log = array(
                $values['event'],
                $values['auth_ip'],
                $values['datetime'],
                $values['page_url'],
                $values['user_agent'],
                $values['page'],
                $values['page_time'],
                $values['browser_sign'],
                intval($attempts) + 1,
            );

        } else {
            $log = array(
                $values['event'],
                $values['auth_ip'],
                $values['datetime'],
                $values['page_url'],
                $values['user_agent'],
                $values['page'],
                $values['page_time'],
                $values['browser_sign'],
                $values['attempts'] = 1,
            );
        }


        file_put_contents( $log_path, implode(',', $log), LOCK_EX );

    }

    /**
     * Send logs about bruteforce, auth, e.t.c.
     *
     * @param $ct_key
     */
    public static function security__logs__send( $ct_key ) {

        $log_dir_path = CT_USP_ROOT . 'data/security_logs';

        if( is_dir( $log_dir_path ) ) {

            $log_files = array_diff(scandir($log_dir_path), array('.', '..', 'index.php'));

            if (!empty($log_files)) {

                //Compile logs
                $data = array();

                foreach ( $log_files as $log_file ) {
                    $log = file_get_contents($log_dir_path . DS . $log_file);
                    $log = explode(',', $log);

                    if( strval($log[8]) > 0 ) {
                        for( $i = 0; strval($log[8]) > $i; $i++ ) {
                            $data[] = array(
                                'datetime' => 	    strval($log[2]),
                                'user_login' =>     null,
                                'event' => 		    strval($log[0]),
                                'auth_ip' => 	    strpos(':', $log[1]) === false ? (int)sprintf('%u', ip2long($log[1])) : (string)$log[1],
                                'page_url' => 		strval($log[3]),
                                'event_runtime' => 	null,
                                'role' => 	        null,
                            );
                        }
                    } else {
                        $data[] = array(
                            'datetime' => 	    strval($log[2]),
                            'user_login' =>     null,
                            'event' => 		    strval($log[0]),
                            'auth_ip' => 	    strpos(':', $log[1]) === false ? (int)sprintf('%u', ip2long($log[1])) : (string)$log[1],
                            'page_url' => 		strval($log[3]),
                            'event_runtime' => 	null,
                            'role' => 	        null,
                        );
                    }


                    // Adding user agent if it's login event
                    if(in_array(strval($log[0]), array( 'login', 'login_2fa', 'login_new_device', 'logout', ))){
                        $data[] = array_merge(
                            array_pop($data),
                            array(
                                'user_agent' => $log[4],
                            )
                        );
                    }
                }

                $result = API::method__security_logs( $ct_key, $data );

                if(empty($result['error'])){

                    //Clear local table if it's ok.
                    if( $result['rows'] == count( $data ) ){

                        foreach ( $log_files as $log_file ){
                            unlink( $log_dir_path . DS . $log_file );
                        }

                        return $result;

                    }else
                        return array( 'error' => 'SENT_AND_RECEIVED_LOGS_COUNT_DOESNT_MACH' );
                }else
                    return $result;
            }else
                return array( 'error' => 'NO_LOGS_TO_SEND' );
        }else
            return array( 'error' => 'NO_LOGS_TO_SEND' );

    }

    /**
     * Send logs about firewall - sfw, waf
     *
     * @param $ct_key
     * @return array|bool|mixed
     */
    public static function logs__send( $ct_key ) {

        $log_dir_path = CT_USP_ROOT . 'data/fw_logs';

        if( is_dir( $log_dir_path ) ){

            $log_files = array_diff( scandir( $log_dir_path ), array( '.', '..', 'index.php' ) );

            if( ! empty( $log_files ) ){

                //Compile logs
                $data = array();

                foreach ( $log_files as $log_file ){
                    $log = file_get_contents( $log_dir_path . DS . $log_file );
                    $log = explode( ',', $log );

                    $to_data = array(
                        'datetime'        => isset( $log[2] ) ? date('Y-m-d H:i:s', $log[2]) : 0,
                        'page_url'        => isset( $log[6] ) ? $log[6] : 0,
                        'visitor_ip'      => isset( $log[1] ) ? ( Helper::ip__validate($log[1]) == 'v4' ? (int)sprintf('%u', ip2long($log[1])) : (string)$log[1] ) : 0,
                        'http_user_agent' => isset( $log[7] ) ? $log[7] : 0,
                        'request_method'  => isset( $log[8] ) ? $log[8] : 0,
                        'x_forwarded_for' => isset( $log[9] ) ? $log[9] : 0,
                        'hits'            => isset( $log[5] ) ? $log[5] : 0,
                    );

                    // Legacy
                    switch($log[3]){
                        case 'PASS_BY_TRUSTED_NETWORK': $to_data['status_efw'] = 3;  break;
                        case 'PASS_BY_WHITELIST':       $to_data['status_efw'] = 2;  break;
                        case 'PASS':                    $to_data['status_efw'] = 1;  break;
                        case 'DENY':                    $to_data['status_efw'] = 0;  break;
                        case 'DENY_BY_NETWORK':         $to_data['status_efw'] = -1; break;
                        case 'DENY_BY_DOS':             $to_data['status_efw'] = -2; break;
                        case 'DENY_BY_WAF_XSS':         $to_data['status_efw'] = -3; $to_data['waf_attack_type'] = 'XSS';           $to_data['waf_comment'] = $log[4]; break;
                        case 'DENY_BY_WAF_SQL':         $to_data['status_efw'] = -4; $to_data['waf_attack_type'] = 'SQL_INJECTION'; $to_data['waf_comment'] = $log[4]; break;
                        case 'DENY_BY_WAF_FILE':        $to_data['status_efw'] = -5; $to_data['waf_attack_type'] = 'MALWARE';       $to_data['waf_comment'] = $log[4]; break;
                        case 'DENY_BY_WAF_EXPLOIT':     $to_data['status_efw'] = -6; $to_data['waf_attack_type'] = 'EXPLOIT';       $to_data['waf_comment'] = $log[4]; break;
                    }

                    switch($log[3]){
                        case 'PASS_BY_TRUSTED_NETWORK': $to_data['status'] = 3;  break;
                        case 'PASS_BY_WHITELIST':       $to_data['status'] = 2;  break;
                        case 'PASS':                    $to_data['status'] = 1;  break;
                        case 'DENY':                    $to_data['status'] = 0;  break;
                        case 'DENY_BY_NETWORK':         $to_data['status'] = -1; break;
                        case 'DENY_BY_DOS':             $to_data['status'] = -2; break;
                        case 'DENY_BY_WAF_XSS':         $to_data['status'] = -3; $to_data['waf_attack_type'] = 'XSS';           $to_data['waf_comment'] = $log[4]; break;
                        case 'DENY_BY_WAF_SQL':         $to_data['status'] = -4; $to_data['waf_attack_type'] = 'SQL_INJECTION'; $to_data['waf_comment'] = $log[4]; break;
                        case 'DENY_BY_WAF_FILE':        $to_data['status'] = -5; $to_data['waf_attack_type'] = 'MALWARE';       $to_data['waf_comment'] = $log[4]; break;
                        case 'DENY_BY_WAF_EXPLOIT':     $to_data['status'] = -6; $to_data['waf_attack_type'] = 'EXPLOIT';       $to_data['waf_comment'] = $log[4]; break;
                    }

                    $data[] = $to_data;
                }
                unset($log_file);

                $result = API::method__security_logs__sendFWData( $ct_key, $data );

                //Checking answer and deleting all lines from the table
                if( empty( $result['error'] ) ){

                    if( $result['rows'] == count( $data ) ){

                        foreach ( $log_files as $log_file ){
                            unlink( $log_dir_path . DS . $log_file );
                        }

                        return $result;
                    }else
                        return array( 'error' => 'SENT_AND_RECEIVED_LOGS_COUNT_DOESNT_MACH' );
                }else
                    return $result;
            }else
                return array( 'error' => 'NO_LOGS_TO_SEND' );
        }else
            return array( 'error' => 'NO_LOGS_TO_SEND' );
    }

    public static function sfw_update($uniforce_apikey, $file_url = null, $immediate = false)
    {

        //TODO unzip file and remote calls
        $result = API::method__security_firewall_data($uniforce_apikey);

        if (empty($result['error'])) {

            $nets_for_save = array();

            foreach ($result as $entry) {

                if (empty($entry)) {
                    continue;
                }

                $ip = $entry[0];
                $mask = $entry[1];
                $status = isset($entry[2]) ? $entry[2] : 0;
                $is_personal = isset($entry[3]) ? intval($entry[3]) : 0;

                // IPv4
                if (is_numeric($ip)) {
                    $mask = sprintf('%u', ip2long(long2ip(-1 << (32 - (int)$mask))));
                    $nets_for_save[] = array(0, 0, 0, $ip, 0, 0, 0, $mask, $status, 0, $is_personal);
                    // IPv6
                } else {
                    $ip = substr($ip, 1, -1); // Cut ""
                    $ip = Helper::ip__v6_normalize($ip); // Normalize
                    $ip = explode(':', $ip);

                    $ip_1 = hexdec($ip[0] . $ip[1]);
                    $ip_2 = hexdec($ip[2] . $ip[3]);
                    $ip_3 = hexdec($ip[4] . $ip[5]);
                    $ip_4 = hexdec($ip[6] . $ip[7]);

                    $ip_1 = $ip_1 ? $ip_1 : 0;
                    $ip_2 = $ip_2 ? $ip_2 : 0;
                    $ip_3 = $ip_3 ? $ip_3 : 0;
                    $ip_4 = $ip_4 ? $ip_4 : 0;

                    for ($k = 1; $k < 5; $k++) {
                        $curr = 'mask_' . $k;
                        $curr = pow(2, 32) - pow(2, 32 - ($mask - 32 >= 0 ? 32 : $mask));
                        $mask = ($mask - 32 <= 0 ? 0 : $mask - 32);
                    }
                    $nets_for_save[] = array($ip_1, $ip_2, $ip_3, $ip_4, $mask_1, $mask_2, $mask_3, $mask_4, $status, 1, $is_personal);
                }

            };

            if (!empty($_SERVER['HTTP_HOST'])) {
                $exclusions[] = Helper::dns__resolve(Server::get('HTTP_HOST'));
                $exclusions[] = '127.0.0.1';
                foreach ($exclusions as $exclusion) {
                    if (Helper::ip__validate($exclusion) && sprintf('%u', ip2long($exclusion))) {
                        $nets_for_save[] = array(0, 0, 0, sprintf('%u', ip2long($exclusion)), 0, 0, 0, sprintf('%u', 4294967295 << (32 - 32)), 2, 0, 0);
                    }
                }
            }

            if ( ! is_dir( CT_USP_ROOT . 'data') ) mkdir( CT_USP_ROOT . 'data');

            File::clean_file_full( CT_USP_ROOT . 'data' . DS . 'sfw_nets.php' );
            File::inject__variable( CT_USP_ROOT . 'data' . DS . 'sfw_nets.php', 'sfw_nets', $nets_for_save, 'yes');

            $out = count($nets_for_save);

        } else {

            Err::add('SpamFirewall update', $result['error']);
            $out = 0;

        }

        return $out;

    }

    public function _die($service_id, $reason = '', $additional_reason = ''){

        // Adding block reason
        switch($reason){
            case 'DENY':                $reason = 'Blacklisted'; break;
            case 'DENY_BY_NETWORK':	    $reason = 'Hazardous network';	               break;
            case 'DENY_BY_DOS':         $reason = 'Blocked by DoS prevention system'; break;
            case 'DENY_BY_WAF_XSS':	    $reason = 'Blocked by Web Application Firewall: XSS attack detected.'; break;
            case 'DENY_BY_WAF_SQL':	    $reason = 'Blocked by Web Application Firewall: SQL-injection detected.'; break;
            case 'DENY_BY_WAF_EXPLOIT':	$reason = 'Blocked by Web Application Firewall: Exploit detected.'; break;
            case 'DENY_BY_WAF_FILE':    $reason = 'Blocked by Web Application Firewall: Malicious files upload.'; break;
            case 'DENY_BY_BFP':         $reason = 'Blocked by Brute Force Protection: Too many invalid log-ins.'; break;
        }

        if( file_exists( CT_USP_INC . 'spbc_die_page.html' ) ){

            $spbc_die_page = file_get_contents( CT_USP_INC . 'spbc_die_page.html');

            $spbc_die_page = str_replace( "{TITLE}", 'Blocked: Security by CleanTalk',     $spbc_die_page );
            $spbc_die_page = str_replace( "{REMOTE_ADDRESS}", $this->blocked_ip,     $spbc_die_page );
            $spbc_die_page = str_replace( "{SERVICE_ID}",     $service_id,           $spbc_die_page );
            $spbc_die_page = str_replace( "{HOST}",           Server::get('HTTP_HOST'), $spbc_die_page );
            $spbc_die_page = str_replace( "{TEST_TITLE}",     (!empty(Get::get('spbct_test')) ? 'This is the testing page for Security FireWall' : ''), $spbc_die_page );
            $spbc_die_page = str_replace( "{REASON}",         $reason, $spbc_die_page );
            $spbc_die_page = str_replace( "{GENERATED_TIMESTAMP}",    time(), $spbc_die_page );
            $spbc_die_page = str_replace( "{FALSE_POSITIVE_WARNING}", 'Maybe you\'ve been blocked by a mistake. Please refresh the page (press CTRL + F5) or try again later.', $spbc_die_page );

            if( headers_sent() === false ){
                header('Expires: '.date(DATE_RFC822, mktime(0, 0, 0, 1, 1, 1971)));
                header('Cache-Control: no-store, no-cache, must-revalidate');
                header('Cache-Control: post-check=0, pre-check=0', FALSE);
                header('Pragma: no-cache');
                header("HTTP/1.0 403 Forbidden");
                $spbc_die_page = str_replace("{GENERATED}", "", $spbc_die_page);
            }else{
                $spbc_die_page = str_replace("{GENERATED}", "<h2 class='second'>The page was generated at&nbsp;".date("D, d M Y H:i:s")."</h2>",$spbc_die_page);
            }

        }

        die( $spbc_die_page );
    }

}