<?php

use Cleantalk\Uniforce\BFP;
use Cleantalk\Uniforce\FireWall;
use Cleantalk\Variables\Cookie;
use Cleantalk\Variables\Server;

// Config
require_once 'inc' . DIRECTORY_SEPARATOR . 'common.php';

if( empty( $uniforce_apikey ) )
    return;

$spbct_checkjs_val = md5( $uniforce_apikey );
global $apbct_checkjs_val;

// Security FireWall
if ( ! empty( $uniforce_sfw_protection ) || ! empty( $uniforce_waf_protection ) || ! empty( $uniforce_bfp_protection ) ) {

    $params = array(
        'bfp_enabled'       => ! empty( $uniforce_bfp_protection ) ? (bool) $uniforce_bfp_protection : false,
        'waf_enabled'       => ! empty( $uniforce_waf_protection ) ? (bool) $uniforce_waf_protection : false,
        'waf_xss_check'     => ! empty( $uniforce_waf_protection ) ? (bool) $uniforce_waf_protection : false,
        'waf_sql_check'     => ! empty( $uniforce_waf_protection ) ? (bool) $uniforce_waf_protection : false,
        'waf_exploit_check' => ! empty( $uniforce_waf_protection ) ? (bool) $uniforce_waf_protection : false,
    );

    $firewall = new FireWall( $params );

    // BruteForce protection.
    if( $uniforce_bfp_protection ) {
        if( ! empty( $uniforce_cms_admin_page ) && $uniforce_cms_admin_page != '' && stripos( Server::get('REQUEST_URI'), $uniforce_cms_admin_page ) !== false ) {

            $firewall->bfp_check();

        }
    }


    // Skip the check
    // Set skip test cookie
    if(!empty($_GET['access'])){
        $apbct_key = $uniforce_apikey;
        // @ToDo need to review this expression
        if( ( $_GET['access'] === $uniforce_apikey || ( $uniforce_apikey !== false && $_GET['access'] === $uniforce_apikey ) ) ){
            setcookie ('spbc_firewall_pass_key', md5(Server::get('REMOTE_ADDR').$uniforce_apikey), time()+1200, '/');
            return;
        }
    }

    //Pass the ckeck if cookie is set.
    foreach( $firewall->ip_array as $spbc_cur_ip ){
        if( ! empty( Cookie::get('spbc_firewall_pass_key') ) && Cookie::get('spbc_firewall_pass_key') == md5($spbc_cur_ip.$uniforce_apikey) ) {
            return;
        }
    }

    // Spam FireWall check
    if( $uniforce_sfw_protection ) {
        $firewall->ip__test();
    }

    // WebApplication FireWall check
    if( $uniforce_waf_protection ) {
        $firewall->waf__test();
    }

    if( strpos( $firewall->result, 'DENY') !== false ){
        if( isset($_GET['spbc_remote_call_token'], $_GET['spbc_remote_call_action'], $_GET['plugin_name']) ){
            $resolved = gethostbyaddr($firewall->blocked_ip);
            if( $resolved && preg_match('/cleantalk\.org/', $resolved) === 1 || $resolved === 'back' ){
                $firewall->result = 'PASS_BY_TRUSTED_NETWORK';
                $firewall->passed_ip = $firewall->blocked_ip;
            }
        }
    }

    // Blacklisted in DB
    if( strpos($firewall->result, 'DENY') !== false ){
        $firewall->update_logs( $firewall->blocked_ip, $firewall->result, $firewall->waf_pattern );
        $firewall->_die( $uniforce_account_name_ob, $firewall->result, $firewall->waf_result );
    // Whitelisted in DB
    }elseif( strpos($firewall->result, 'PASS') !== false ){
        $firewall->update_logs( $firewall->passed_ip, $firewall->result );
        if( ! headers_sent() ){
            setcookie ('spbc_firewall_pass_key', md5($firewall->passed_ip.$uniforce_apikey), 300, '/');
        }
    }

}

// Set Cookies test for cookie test
$uniforce_timestamp = time();
setcookie('spbct_timestamp',     $uniforce_timestamp,                   0, '/');
setcookie('spbct_cookies_test',  md5($uniforce_apikey.$uniforce_timestamp), 0, '/');
setcookie('spbct_timezone',      '0',                             0, '/');
setcookie('spbct_fkp_timestamp', '0',                             0, '/');
setcookie('spbct_pointer_data',  '0',                             0, '/');
setcookie('spbct_ps_timestamp',  '0',                             0, '/');