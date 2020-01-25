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
		
		$sfw = new FireWall();
		
		// Send SFW logs
		$result = $sfw->logs__send( $uniforce_apikey );
		
		if( ! empty( $result['error'] ) )
			Err::add( $result['error'] );
		
		if( ! Err::check() )
			File::replace__variable( CLEANTALK_CONFIG_FILE, 'uniforce_sfw_last_logs_send', time() );
	}
	
	return ! Err::check() ? true : false;
}
