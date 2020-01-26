<?php

namespace Cleantalk\Uniforce;


use Cleantalk\Common\Err;
use Cleantalk\Common\File;
use Cleantalk\Uniforce\Helper;
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
               $uniforce_waf_last_logs_send,
               $uniforce_bfp_trigger_count,
               $uniforce_bfp_last_logs_send;

        $info = '';
        if( ! empty( $uniforce_sfw_protection ) && $uniforce_sfw_protection ) {
            $sfw_updated_time = $uniforce_sfw_last_update ? date('M d Y H:i:s', $uniforce_sfw_last_update) : 'never.';
            $sfw_send_logs_time = $uniforce_sfw_last_logs_send ? date('M d Y H:i:s', $uniforce_sfw_last_logs_send) : 'never.';
            $info .= 'Security FireWall was updated: ' . $sfw_updated_time . '<br>';
            $info .= 'Security FireWall contains: ' . $uniforce_sfw_entries . ' entires.<br>';
            $info .= 'Security FireWall logs were sent: ' . $sfw_send_logs_time . '<br>';
            $info .= '<br>';
        }
        if( ! empty( $uniforce_waf_protection ) && $uniforce_waf_protection ) {
            $waf_send_logs_time = $uniforce_waf_last_logs_send ? date('M d Y H:i:s', $uniforce_waf_last_logs_send) : 'never.';
            $info .= 'WebApplication FireWall was triggered: ' . $uniforce_waf_trigger_count . '<br>';
            $info .= 'WebApplication FireWall logs were sent: ' . $waf_send_logs_time . '<br>';
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

    public function ip__test()
    {
        $fw_results = array();
        $datafile_path = CLEANTALK_ROOT . 'data/sfw_nets.php';

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

    public function bfp_check()
    {
        return true;
    }

    //Add entries to SFW log
    public function update_logs( $ip, $result, $pattern = array() )
    {

        if ($ip === NULL || $result === NULL)
            return;

        global $salt;

        $time = time();
        $log_path = CLEANTALK_ROOT . 'data/sfw_logs/' . hash('sha256', $ip . $salt) . '.log';

        if ( file_exists($log_path) ) {

            $log = file_get_contents($log_path);
            $log = explode(',', $log);

            $all_entries = isset($log[1]) ? $log[1] : 0;
            $blocked_entries = isset($log[2]) ? $log[2] : 0;
            $blocked_entries = $result == 'blocked' ? $blocked_entries + 1 : $blocked_entries;

            $log = array( $ip, intval($all_entries) + 1, $blocked_entries, $time );

        } else {

            $blocked = $result == 'blocked' ? 1 : 0;
            $log = array($ip, 1, $blocked, $time);

        }

        file_put_contents( $log_path, implode(',', $log) );

    }

    public static function logs__send( $ct_key, $logs_type ) {

        $log_dir_path = CLEANTALK_ROOT . 'data/' . $logs_type;

        if( is_dir( $log_dir_path ) ){

            $log_files = array_diff( scandir( $log_dir_path ), array( '.', '..', 'index.php' ) );

            if( ! empty( $log_files ) ){

                //Compile logs
                $data = array();

                foreach ( $log_files as $log_file ){
                    $log = file_get_contents( $log_dir_path . DS . $log_file );
                    $log = explode( ',', $log );
                    $ip                = isset( $log[0] ) ? $log[0] : '';
                    $all_entries       = isset( $log[1] ) ? $log[1] : 0;
                    $blocked_entries   = isset( $log[2] ) ? $log[2] : 0;
                    $timestamp_entries = isset( $log[3] ) ? $log[3] : 0;
                    $data[] = array(
                        $ip,
                        $all_entries,
                        $all_entries - $blocked_entries,
                        $timestamp_entries
                    );
                }
                unset( $log_file );
                $result = API::method__security_logs( $ct_key, $data );

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

            if ( ! is_dir(CLEANTALK_ROOT . 'data') ) mkdir(CLEANTALK_ROOT . 'data');

            File::clean_file_full( CLEANTALK_ROOT . 'data' . DS . 'sfw_nets.php' );
            File::inject__variable(CLEANTALK_ROOT . 'data' . DS . 'sfw_nets.php', 'sfw_nets', $nets_for_save, 'yes');

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
            case 'DENY_BY_WAF_XSS':	    $reason = 'Blocked by Web Application Firewall: XSS atatck detected.'; break;
            case 'DENY_BY_WAF_SQL':	    $reason = 'Blocked by Web Application Firewall: SQL-injection detected.'; break;
            case 'DENY_BY_WAF_EXPLOIT':	$reason = 'Blocked by Web Application Firewall: Exploit detected.'; break;
            case 'DENY_BY_WAF_FILE':    $reason = 'Blocked by Web Application Firewall: Malicious files upload.'; break;
        }

        if( file_exists( CLEANTALK_INC . 'spbc_die_page.html' ) ){

            $spbc_die_page = file_get_contents(CLEANTALK_INC . 'spbc_die_page.html');

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