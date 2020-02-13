<?php

use Cleantalk\Common\State;

function ctusp_settings__show_cms(){
	return '<p>Presumably CMS:' . State::getInstance()->data->detected_cms . '</p>';
}

function ctusp_settings__show_modified_files(){
//	var_dump( State::getInstance()->data->modified_files->convertToArray );
	$out = '<p>Modified files:</p>';
	foreach( State::getInstance()->data->modified_files->convertToArray() as $file ){
		$out .= '<p>&nbsp; - ' . $file . '</p>';
	}
	return $out;
}

function usp_settings__show_fw_statistics( $out = '' )
{
	$usp = State::getInstance();
	$stat = State::getInstance()->data->stats;

	if( $usp->settings->fw || $usp->settings->waf ) {
		$out .= 'Security FireWall was updated: ' . ( $stat->fw->last_update ? date('M d Y H:i:s', $stat->fw->last_update) : 'never' ) . '<br>';
		$out .= 'Security FireWall contains: ' . $stat->fw->entries . ' entires.<br>';
		$out .= 'Security FireWall logs were sent: ' . $stat->fw->logs_sent_time ? date('M d Y H:i:s', $stat->fw->logs_sent_time) : 'never.' . '<br>';
		$out .= '<br>';
	}
	if( $usp->settings->bf ) {
		$bfp_send_logs_time = $uniforce_bfp_last_logs_send ? date('M d Y H:i:s', $uniforce_bfp_last_logs_send) : 'never.';
		$out .= 'BruteForce Protection was triggered: ' . $stat->bf->logs_sent_amount . '<br>';
		$out .= 'BruteForce Protection logs were sent: ' . $stat->bf->logs_sent_time . '<br>';
		$out .= '<br>';
	}
	return $out;
}