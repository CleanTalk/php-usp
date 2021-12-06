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

function usp_settings__plugin_state(){
	$usp = State::getInstance();
	if( version_compare( $usp->plugin_meta->version, $usp->plugin_meta->latest_version ) === -1 ){
		echo '<p class="text-center">There is a newer version. Update to the latest ' . $usp->plugin_meta->latest_version . '</p>';
		echo '<p class="text-center"><button id="btn-update" form="none" class="btn btn-setup" value="">Update</button><img class="preloader" src="img/preloader.gif"></p>';
	}elseif( version_compare( $usp->plugin_meta->version, $usp->plugin_meta->latest_version ) === 1 ){
		echo '<p class="text-center">You are using more than the latest version '. $usp->plugin_meta->version . '</p>';
	}else{
		echo '<p class="text-center">You are using the latest version '. $usp->plugin_meta->version . '</p>';
	}
}

function usp_settings__show_fw_statistics( $out = '' )
{
	$usp = State::getInstance();
	$fw_stats  = State::getInstance()->fw_stats;
	$bfp_stats = State::getInstance()->data->stat->bfp;

	if( $usp->settings->fw || $usp->settings->waf ) {
		echo 'Security FireWall was updated: ' . ( $fw_stats->last_update
				? date('M d Y H:i:s', $fw_stats->last_update)
				: 'never'
			) . '<br>';
		echo 'Security FireWall contains: ' . ( $fw_stats->entries ? $fw_stats->entries : 'no' ). ' entries. '
		     . ( State::getInstance()->fw_stats->updating ? '<b>Under updating now: ' . State::getInstance()->fw_stats->update_percent. '%</b>' : '' )
		     . '<br>';
		echo 'Security FireWall logs were sent: ' . ( $fw_stats->logs_sent_time
				? date('M d Y H:i:s', $fw_stats->logs_sent_time)
				: 'never.'
			) . '<br>';
		echo '<br>';
	}
	if( $usp->settings->bfp ) {
		echo 'BruteForce Protection was triggered: ' . $bfp_stats->count . ' times.<br>';
		echo 'BruteForce Protection logs were sent: ' . ( $bfp_stats->logs_sent_time
				? date( 'M d Y H:i:s', $bfp_stats->logs_sent_time )
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