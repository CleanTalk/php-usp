<?php

use Cleantalk\USP\Common\State;

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
	$stat = State::getInstance()->fw_stats;

	if( $usp->settings->fw || $usp->settings->waf ) {
		echo 'Security FireWall was updated: ' . ( $stat->last_update
				? date('M d Y H:i:s', $stat->last_update)
				: 'never'
			) . '<br>';
		echo 'Security FireWall contains: ' . ( $stat->entries ? $stat->entries : 'no' ). ' entires.<br>';
		echo 'Security FireWall logs were sent: ' . ( $stat->logs_sent_time
				? date('M d Y H:i:s', $stat->logs_sent_time)
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

function usp_settings__show_scanner_statistics(){
	
	$usp = State::getInstance();
	$stat = State::getInstance()->data->stat;
	
	if( State::getInstance()->data->no_sql )
	echo '<div class="alert alert-warning" role="alert">
            <p id="error-msg">Warning: Malware scanner will use local database to store scan results. Please, check your OpenSSL module for PHP.</p>
        </div>';
	
	echo 'Last scan: ' . ( $stat->scanner->last_scan ? date('M d Y H:i:s', $stat->scanner->last_scan) : 'never' ) . '<br>';
	echo 'Number of scanned files at the last scan: ' . $stat->scanner->last_scan_amount . '<br>';
	
	echo '<br>';
	
	echo 'Signature last update: ' . ( $stat->scanner->signature_last_update
			? date('M d Y H:i:s', $stat->scanner->signature_last_update)
			: 'never.'
		) . '<br>';
	echo 'Signatures in local base: ' . $stat->scanner->signature_entries . '.<br>';
	
}