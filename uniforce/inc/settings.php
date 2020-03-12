<?php

use Cleantalk\Common\State;

function ctusp_settings__show_cms(){
	echo '<p>Presumably CMS:' . State::getInstance()->data->detected_cms . '</p>';
}

function ctusp_settings__show_modified_files(){
	echo '<p>Modified files:</p>';
	foreach( State::getInstance()->data->modified_files->convertToArray() as $file ){
		echo '<p>&nbsp; - ' . $file . '</p>';
	}
}

function usp_settings__show_fw_statistics( $out = '' )
{
	$usp = State::getInstance();
	$stat = State::getInstance()->data->stat;

	if( $usp->settings->fw || $usp->settings->waf ) {
		echo 'Security FireWall was updated: ' . ( $stat->fw->last_update
				? date('M d Y H:i:s', $stat->fw->last_update)
				: 'never'
			) . '<br>';
		echo 'Security FireWall contains: ' . ( $stat->fw->entries ? $stat->fw->entries : 'no' ). ' entires.<br>';
		echo 'Security FireWall logs were sent: ' . ( $stat->fw->logs_sent_time
				? date('M d Y H:i:s', $stat->fw->logs_sent_time)
				: 'never.'
			) . '<br>';
		echo '<br>';
	}
	if( $usp->settings->bfp ) {
		$bfp_send_logs_time = $stat->bfp->logs_sent_time ?  : 'never.';
		echo 'BruteForce Protection was triggered: ' . $stat->bfp->count . ' times.<br>';
		echo 'BruteForce Protection logs were sent: ' . ( $stat->bfp->logs_sent_time
				? date( 'M d Y H:i:s', $stat->bfp->logs_sent_time )
				: 'never'
			). '<br>';
		echo '<br>';
	}
}