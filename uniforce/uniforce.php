<?php

// Config
require_once 'inc' . DIRECTORY_SEPARATOR . 'common.php';

if( empty( $uniforce_apikey ) )
    return;

$spbct_checkjs_val = md5( $uniforce_apikey );
global $apbct_checkjs_val;

// Security FireWall
if ( $uniforce_sfw_protection ) {

    $is_sfw_check  = true;
    $sfw           = new \Cleantalk\Uniforce\SFW();
    // Doing Security FireWall protection.

}

// WebApplication FireWall
if ( $uniforce_waf_protection ) {

    $is_waf_check  = true;
    $waf           = new \Cleantalk\Uniforce\WAF();
    // Doing WebApplication FireWall protection.

}

// BruteForce Protection
if ( $uniforce_bfp_protection ) {

    $is_bfp_check  = true;
    $bfp           = new \Cleantalk\Uniforce\BFP();
    // Doing BruteForce protection.

}

// Set Cookies test for cookie test
$uniforce_timestamp = time();
setcookie('spbct_timestamp',     $uniforce_timestamp,                   0, '/');
setcookie('spbct_cookies_test',  md5($uniforce_apikey.$uniforce_timestamp), 0, '/');
setcookie('spbct_timezone',      '0',                             0, '/');
setcookie('spbct_fkp_timestamp', '0',                             0, '/');
setcookie('spbct_pointer_data',  '0',                             0, '/');
setcookie('spbct_ps_timestamp',  '0',                             0, '/');