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
			File::replace__variable( CT_USP_CONFIG_FILE, 'uniforce_sfw_last_update', time() );
			File::replace__variable( CT_USP_CONFIG_FILE, 'uniforce_sfw_entries', $result );
		}
	}
	
	return ! Err::check() ? true : false;
}

function uniforce_fw_logs_send(){
	
	global $uniforce_apikey, $uniforce_sfw_protection;
	
	// SFW actions
	if( ! empty( $uniforce_apikey ) && ! empty( $uniforce_sfw_protection ) ){

		// Send SFW logs
		$result = FireWall::logs__send( $uniforce_apikey );
		
		if( ! empty( $result['error'] ) )
			Err::add( $result['error'] );
		
		if( ! Err::check() ) {
            File::replace__variable( CT_USP_CONFIG_FILE, 'uniforce_sfw_last_logs_send', time() );
            File::replace__variable( CT_USP_CONFIG_FILE, 'uniforce_waf_trigger_count', 0 );
        }

	}
	
	return ! Err::check() ? true : false;
}

function uniforce_security_logs_send(){

    global $uniforce_apikey, $uniforce_bfp_protection;

    // SFW actions
    if( ! empty( $uniforce_apikey ) && ! empty( $uniforce_bfp_protection ) ){

        // Send SFW logs
        $result = FireWall::security__logs__send( $uniforce_apikey );

        if( ! empty( $result['error'] ) )
            Err::add( $result['error'] );

        if( ! Err::check() ) {
            File::replace__variable( CT_USP_CONFIG_FILE, 'uniforce_bfp_last_logs_send', time() );
            File::replace__variable( CT_USP_CONFIG_FILE, 'uniforce_bfp_trigger_count', 0 );
        }

    }

    return ! Err::check() ? true : false;
}

function uniforce_clean_black_lists() {

    // Remove entries older than 1 hour
    $black_list = CT_USP_ROOT . 'data/bfp_blacklist.php';
    $fast_black_list = CT_USP_ROOT . 'data/bfp_fast_blacklist.php';

    // Black list clean
    if ( file_exists($black_list) ) {
        require_once $black_list;
        $need_to_rewrite = false;
        if ( isset( $bad_ips ) ) {
            if( ! empty( $bad_ips ) ) {
                foreach( $bad_ips as $bad_ip => $bad_ip_added ) {
                    if( $bad_ip_added + 3600 < time() ) {
                        unset( $bad_ips[$bad_ip] );
                        $need_to_rewrite = true;
                    }
                }
            }
        }
        if( $need_to_rewrite ) {
            File::replace__variable( $black_list, 'bad_ips', $bad_ips );
        }
    }

    // Fast black list clean
    if ( file_exists($fast_black_list) ) {
        require_once $fast_black_list;
        $need_to_rewrite = false;
        if ( isset( $fast_bad_ips ) ) {
            if( ! empty( $fast_bad_ips ) ) {
                foreach( $fast_bad_ips as $fast_bad_ip => $fast_bad_ip_info ) {
                    if( $fast_bad_ip_info['added'] + 3600 < time() ) {
                        unset( $fast_bad_ips[$fast_bad_ip] );
                        $need_to_rewrite = true;
                    }
                }
            }
        }
        if( $need_to_rewrite ) {
            File::replace__variable( $fast_black_list, 'bad_ips', $bad_ips );
        }
    }

}
