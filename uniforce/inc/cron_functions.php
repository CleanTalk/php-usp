<?php

use Cleantalk\Uniforce\SFW;
use Cleantalk\Common\Err;
use Cleantalk\Common\File;

function uniforce_sfw_update(){
	
	global $apikey, $spam_firewall;
	
	// SFW actions
	if( isset($apikey, $spam_firewall ) ){
		
		$sfw = new SFW();
		
		// Update SFW
		$result = $sfw->sfw_update( $apikey );
		if( ! Err::check() ){
			File::replace__variable( CLEANTALK_CONFIG_FILE, 'sfw_last_update', time() );
			File::replace__variable( CLEANTALK_CONFIG_FILE, 'sfw_entries', $result );
			
		}
	}
	
	return ! Err::check() ? true : false;
}

function uniforce_sfw_logs_send(){
	
	global $apikey, $spam_firewall;
	
	// SFW actions
	if( isset($apikey, $spam_firewall ) ){
		
		$sfw = new SFW();
		
		// Send SFW logs
		$result = $sfw->logs__send( $apikey );
		
		if( ! empty( $result['error'] ) )
			Err::add( $result['error'] );
		
		if( ! Err::check() )
			File::replace__variable( CLEANTALK_CONFIG_FILE, 'sfw_last_logs_send', time() );
	}
	
	return ! Err::check() ? true : false;
}
