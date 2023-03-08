<?php

use Cleantalk\USP\Common\State;
use Cleantalk\USP\Variables\Cookie;
use Cleantalk\USP\Variables\Post;
use Cleantalk\USP\Variables\Server;

// Config
require_once 'inc' . DIRECTORY_SEPARATOR . 'common.php';

$usp = State::getInstance();

if( ! $usp->key )
    return;

// Helper functions
require_once( CT_USP_INC . 'functions.php' );

global $usp_is_js_attached;

$usp_is_js_attached = false;

if( $usp->settings->fw || $usp->settings->waf || $usp->settings->bfp ){

	// Security FireWall
	$firewall = new \Cleantalk\USP\Uniforce\Firewall();
	
	if( $usp->settings->fw && ! $usp->fw_stats->updating && $usp->fw_stats->entries )
		$firewall->module__load( new \Cleantalk\USP\Uniforce\Firewall\FW(
			array(
				'state'   => $usp,
				'api_key' => $usp->key,
			)
		) );
	
	if( $usp->settings->waf )
		$firewall->module__load( new \Cleantalk\USP\Uniforce\Firewall\WAF(
			array(
				'waf_xss_check'     => true,
				'waf_sql_check'     => true,
				'waf_file_check'    => true,
				'waf_exploit_check' => true,
                'api_key' => $usp->key,
                'state'   => $usp,
			)
		) );
	
	if( $usp->settings->bfp ){
		
		$firewall->module__load( new \Cleantalk\USP\Uniforce\Firewall\BFP(
            array(
                'is_login_page' => \Cleantalk\USP\Uniforce\Firewall\BFP::is_login_page(),
                'is_logged_in'  => \Cleantalk\USP\Uniforce\Firewall\BFP::is_logged_in( $usp->detected_cms ),
                'do_check'      => Cookie::get( 'spbct_authorized' ) !== md5( State::getInstance()->key ) ||
                    Post::get( 'spbct_login_form' ),
                'state'         => $usp,
                'api_key' => $usp->key,
            )
		) );
	}
	
	//Pass the check if cookie is set.
	foreach( $firewall->ip_array as $spbc_cur_ip ) {
		if( Cookie::get( 'spbc_firewall_pass_key' ) == md5( $spbc_cur_ip . $usp->key ) )
			return;
	}
	
	if( $firewall->module__is_loaded__any() && ! usp__is_admin() ){
		$firewall->run();
	}
	
}

// Catching buffer and doing protection
ob_start( 'uniforce_attach_js' );

// Log authorized users actions
if( Cookie::get('spbct_authorized') )
    \Cleantalk\USP\Uniforce\Firewall\BFP::update_log( 'view' );

function uniforce_attach_js( $buffer ){
    global $usp_is_js_attached;
    if ($usp_is_js_attached){
        return $buffer;
    }

    if(
        !(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') // No ajax
        && preg_match('/^\s*(<!doctype|<!DOCTYPE|<html)/i', $buffer) == 1 // Only for HTML documents
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

    if( State::getInstance()->settings->bfp && State::getInstance()->detected_cms ){

	    if( \Cleantalk\USP\Uniforce\Firewall\BFP::is_logged_in( State::getInstance()->detected_cms ) ) {

		    if( ! Cookie::get( 'spbct_authorized' ) ){
			    setcookie( 'spbct_authorized', md5( State::getInstance()->key ), 0, '/' );
			    \Cleantalk\USP\Uniforce\Firewall\BFP::update_log( 'login' );
			    \Cleantalk\USP\Uniforce\Firewall\BFP::send_log( State::getInstance()->key );
		    }
		    
	    }else{

		    if( Cookie::get('spbct_authorized') ) {
			    \Cleantalk\USP\Uniforce\Firewall\BFP::update_log( 'logout' );
			    setcookie( 'spbct_authorized', md5( State::getInstance()->key ), time()-3600, '/' );
		    }

		    if( Post::get( 'spbct_login_form' ) )
			    \Cleantalk\USP\Uniforce\Firewall\BFP::update_log( 'auth_failed' );

	    }
    }

    $usp_is_js_attached = true;

    return $buffer;
}

// Set Cookies test for cookie test
setcookie('spbct_timestamp',     time(),                        0, '/');
setcookie( 'spbct_cookies_test', md5( $usp->key . time() ), 0, '/');
setcookie('spbct_timezone',      '0',                           0, '/');
setcookie('spbct_fkp_timestamp', '0',                           0, '/');
setcookie('spbct_pointer_data',  '0',                           0, '/');
setcookie('spbct_ps_timestamp',  '0',                           0, '/');