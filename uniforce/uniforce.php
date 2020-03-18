<?php

use Cleantalk\Common\File;
use Cleantalk\Common\State;
use Cleantalk\Uniforce\FireWall;
use Cleantalk\Variables\Cookie;
use Cleantalk\Variables\Get;
use Cleantalk\Variables\Post;
use Cleantalk\Variables\Server;

// Config
require_once 'inc' . DIRECTORY_SEPARATOR . 'common.php';

$usp = State::getInstance();

if( ! $usp->key )
    return;

// Helper functions
require_once( CT_USP_INC . 'functions.php' );

// Security FireWall
if ( $usp->settings->fw || $usp->settings->waf || $usp->settings->bfp ) {

    $params = array(
        'bfp_enabled'       => $usp->settings->bfp,
        'waf_enabled'       => $usp->settings->waf,
        'waf_xss_check'     => $usp->settings->waf_xss_check,
        'waf_sql_check'     => $usp->settings->waf_sql_check,
        'waf_exploit_check' => $usp->settings->waf_exploit_check,
    );

    $firewall = new FireWall( $params );

    // BruteForce protection.
    if( $usp->settings->bfp ) {
        // @ToDo If the login form is on front page?!?!?
        if( ($usp->settings->bfp_admin_page && Server::has_string('REQUEST_URI', $usp->settings->bfp_admin_page ) ) ||
            ( defined( 'USP_DASHBOARD' ) && Post::get( 'login' ) )
        ) {

            // Catching buffer and doing protection
            ob_start( 'uniforce_attach_js' );

            if( ! empty( $_POST ) && isset( $_POST['spbct_login_form'] ) ) {

                try {
                    $bfp_result = $firewall->bfp_check();
                } catch ( Exception $exception ) {
                    error_log( var_export( $exception->getMessage(), 1 ));
                }

                if( ! $bfp_result ) {
	                ++$usp->data->stat->bfp->counter;
	                $usp->data->save();
                    $firewall->update_logs( $firewall->blocked_ip, $firewall->result );
                    $firewall->_die( $usp->data->account_name_ob, $firewall->result );
                }

            }

        }
    }


    // Skip the check
    // Set skip test cookie
    if(!empty($_GET['access'])){

        if( Get::get('access') === $usp->key ) {
	        setcookie( 'spbc_firewall_pass_key', md5( Server::get( 'REMOTE_ADDR' ) . $usp->key ), time() + 1200, '/' );
            return;
        }
    }

    //Pass the ckeck if cookie is set.
    foreach( $firewall->ip_array as $spbc_cur_ip ){
	    if ( ! empty( Cookie::get( 'spbc_firewall_pass_key' ) ) && Cookie::get( 'spbc_firewall_pass_key' ) == md5( $spbc_cur_ip . $usp->key ) ) {
            return;
        }
    }

    // Log authorized users actions
    if( ! empty( Cookie::get('spbct_authorized') ) ) {
        FireWall::security__update_auth_logs( 'view' );
    }

    // Spam FireWall check
    if( $usp->key ) {
        $firewall->ip__test();
    }
    // WebApplication FireWall check
    if( $usp->settings->waf ) {
        $firewall->waf__test();
    }

    if( strpos( $firewall->result, 'DENY') !== false ){
        if( Get::is_set('spbc_remote_call_token', 'spbc_remote_call_action', 'plugin_name' ) ) {
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
        $firewall->_die( $usp->data->account_name_ob, $firewall->result, $firewall->waf_result );
    // Whitelisted in DB
    }elseif( strpos($firewall->result, 'PASS') !== false ){
        $firewall->update_logs( $firewall->passed_ip, $firewall->result );
        if( ! headers_sent() ){
            setcookie ('spbc_firewall_pass_key', md5($firewall->passed_ip.$usp->key), 300, '/');
        }
    }

}

function uniforce_attach_js( $buffer ){

    if(
        !(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') // No ajax
        && preg_match('/^\s*(<!doctype|<html)[\s\S]*html>\s*$/i', $buffer) == 1 // Only for HTML documents
    ){
        $html_addition =
            '<script>var spbct_checkjs_val = "' . md5( State::getInstance()->key ) . '";</script>'
            .'<script src="/uniforce/js/ct_js_test.js"></script>'
            .'<script src="/uniforce/js/ct_ajax_catch.js"></script>';
        $buffer = preg_replace(
            '/<\/body>(\s|<.*>)*<\/html>\s*$/i',
            $html_addition.'</body></html>',
            $buffer,
            1
        );
    }

    if( State::getInstance()->settings->bfp && FireWall::is_logged_in( State::getInstance()->detected_cms ) ) {
        setcookie( 'spbct_authorized', md5( State::getInstance()->key ), 0, '/' );
    } else {
        if( ! empty( Cookie::get('spbct_authorized') ) ) {
            FireWall::security__update_auth_logs( 'logout' );
        }
        setcookie( 'spbct_authorized', md5( State::getInstance()->key ), time()-3600, '/' );
    }

    return $buffer;
}

// Set Cookies test for cookie test
setcookie('spbct_timestamp',     time(),                        0, '/');
setcookie( 'spbct_cookies_test', md5( $usp->key . time() ), 0, '/');
setcookie('spbct_timezone',      '0',                           0, '/');
setcookie('spbct_fkp_timestamp', '0',                           0, '/');
setcookie('spbct_pointer_data',  '0',                           0, '/');
setcookie('spbct_ps_timestamp',  '0',                           0, '/');