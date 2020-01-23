<?php

//Settings
	$uniforce_sfw_protection = true;
	$uniforce_waf_protection = true;
	$uniforce_bfp_protection = true;

// Statistics
	$uniforce_sfw_last_update = 0;
	$uniforce_sfw_entries = 0;
	$uniforce_sfw_last_logs_send = 0;

// Response language
$uniforce_response_lang = 'en';

if( ! defined( 'SPBCT_PLUGIN' ) )     define( 'SPBCT_PLUGIN', 'uniforce' );
if( ! defined( 'SPBCT_VERSION' ) )    define( 'SPBCT_VERSION', '1.0' );
if( ! defined( 'SPBCT_AGENT' ) )      define( 'SPBCT_AGENT', SPBCT_PLUGIN . '-' . str_replace( '.', '', SPBCT_VERSION ) );
if( ! defined( 'SPBCT_USER_AGENT' ) ) define( 'SPBCT_USER_AGENT', 'Cleantalk-Security-Universal-Plugin/' . SPBCT_VERSION );