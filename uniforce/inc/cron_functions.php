<?php

use Cleantalk\Uniforce\FireWall;
use Cleantalk\Common\Err;
use Cleantalk\Common\File;

function uniforce_sfw_update(){
	
	global $uniforce_apikey, $uniforce_sfw_protection;
	
	// SFW actions
	if( ! empty( $uniforce_apikey ) && ! empty( $uniforce_sfw_protection ) ){

		// Update SFW
		$result = FireWall::sfw_update( $uniforce_apikey );
		if( ! Err::check() ){
			File::replace__variable( CLEANTALK_CONFIG_FILE, 'uniforce_sfw_last_update', time() );
			File::replace__variable( CLEANTALK_CONFIG_FILE, 'uniforce_sfw_entries', $result );
		}
	}
	
	return ! Err::check() ? true : false;
}

function uniforce_sfw_logs_send(){
	
	global $uniforce_apikey, $uniforce_sfw_protection;
	
	// SFW actions
	if( ! empty( $uniforce_apikey ) && ! empty( $uniforce_sfw_protection ) ){

		// Send SFW logs
		$result = FireWall::logs__send( $uniforce_apikey, 'sfw_logs' );
		
		if( ! empty( $result['error'] ) )
			Err::add( $result['error'] );
		
		if( ! Err::check() )
			File::replace__variable( CLEANTALK_CONFIG_FILE, 'uniforce_sfw_last_logs_send', time() );
	}
	
	return ! Err::check() ? true : false;
}

function uniforce_waf_logs_send(){

    global $uniforce_apikey, $uniforce_waf_protection;

    // SFW actions
    if( ! empty( $uniforce_apikey ) && ! empty( $uniforce_waf_protection ) ){

        // Send SFW logs
        $result = FireWall::logs__send( $uniforce_apikey, 'waf_logs' );

        if( ! empty( $result['error'] ) )
            Err::add( $result['error'] );

        if( ! Err::check() ) {
            File::replace__variable( CLEANTALK_CONFIG_FILE, 'uniforce_waf_last_logs_send', time() );
            File::replace__variable( CLEANTALK_CONFIG_FILE, 'uniforce_waf_trigger_count', 0 );
        }

    }

    return ! Err::check() ? true : false;
}

function uniforce_bfp_logs_send(){

    global $uniforce_apikey, $uniforce_bfp_protection;

    // SFW actions
    if( ! empty( $uniforce_apikey ) && ! empty( $uniforce_bfp_protection ) ){

        // Send SFW logs
        $result = FireWall::logs__send( $uniforce_apikey, 'bfp_logs' );

        if( ! empty( $result['error'] ) )
            Err::add( $result['error'] );

        if( ! Err::check() ) {
            File::replace__variable( CLEANTALK_CONFIG_FILE, 'uniforce_bfp_last_logs_send', time() );
            File::replace__variable( CLEANTALK_CONFIG_FILE, 'uniforce_bfp_trigger_count', 0 );
        }

    }

    return ! Err::check() ? true : false;
}
