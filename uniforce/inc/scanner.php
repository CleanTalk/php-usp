<?php

use Cleantalk\USP\Common\State;
use Cleantalk\USP\Common\Storage;
use Cleantalk\USP\DB;
use Cleantalk\USP\Layout\ListTable;
use Cleantalk\USP\Scanner\Scanner;
use Cleantalk\USP\Uniforce\API;
use Cleantalk\USP\Uniforce\Helper;
use Cleantalk\USP\Variables\Post;

/**
 * Sends file for analysis via security_mscan_files method
 *
 * @param bool|string $file_id
 *
 * @return array|bool|mixed|string[]
 */
function spbc_scanner_file_send( $file_id = null ){
    
    $file_id = $file_id ?: Post::get('file_id', 'hash');
    
	if($file_id){
	    
        if( State::getInstance()->data->no_sql )
            return spbc_scanner_file_send___no_sql( $file_id );
        
        $usp = State::getInstance();
        $db  = DB::getInstance(
            $usp->data->db_request_string,
            $usp->data->db_user,
            $usp->data->db_password
        );
    
        $root_path = substr(CT_USP_SITE_ROOT, 0 ,-1);
        
		// Getting file info.
		$file_info = $db->fetch_all('SELECT *'
            .' FROM scanner_files'
            .' WHERE fast_hash = "' . $file_id . '"')[0];
		
		// Scan file before send it
		// Heuristic
		$result_heur = Scanner::file__scan__heuristic($root_path, $file_info);
		if(!empty($result['error'])){
			$output = array('error' =>'RESCACNNING_FAILED');
			die(json_encode($output));
		}
		
		// Signature
		$signatures = new Storage('signatures', null, '', 'csv', array(
			'id',
			'name',
			'body',
			'type',
			'attack_type',
			'submitted',
			'cci'
		) );
		$signatures = $signatures->convertToArray();
		$result_sign = Scanner::file__scan__for_signatures($root_path, $file_info, $signatures);
		if(!empty($result['error'])){
			$output = array('error' =>'RESCACNNING_FAILED');
			die(json_encode($output));
		}

		$result = Helper::array_merge__save_numeric_keys__recursive($result_sign, $result_heur);

//		\Cleantalk\USP\DB::getInstance()->update(
//			SPBC_TBL_SCAN_FILES,
//			array(
//				'checked'    => $file_info['checked'],
//				'status'     => $file_info['status'] === 'MODIFIED' ? 'MODIFIED' : $result['status'],
//				'severity'   => $result['severity'],
//				'weak_spots' => json_encode($result['weak_spots']),
//				'full_hash'  => md5_file($root_path.$file_info['path']),
//			),
//			array( 'fast_hash' => $file_info['fast_hash'] ),
//			array( '%s', '%s', '%s', '%s', '%s' ),
//			array( '%s' )
//		);
		$file_info['weak_spots'] = $result['weak_spots'];
		$file_info['full_hash']  = md5_file($root_path.$file_info['path']);

		if(!empty($file_info)){
			if(file_exists($root_path.$file_info['path'])){
				if(is_readable($root_path.$file_info['path'])){
					if(filesize($root_path.$file_info['path']) > 0){
						if(filesize($root_path.$file_info['path']) < 1048570){

							// Getting file && API call
							$file = file_get_contents($root_path.$file_info['path']);
							$result = API::method__security_mscan_files($usp->settings->key, $file_info['path'], $file, $file_info['full_hash'], $file_info['weak_spots']);

							if(empty($result['error'])){
								if($result['result']){

									// Updating "last_sent"
									$sql_result = $db->execute('UPDATE scanner_files SET last_sent = ' . time() . ' WHERE fast_hash = "' . $file_id . '"');

									if($sql_result !== false){
										$output = array('success' => true, 'result' => $result);
									}else
										$output = array('error' =>'DB_COULDNT_UPDATE_ROW');
								}else
									$output = array('error' =>'API_RESULT_IS_NULL');
							}else
								$output = $result;
						}else
							$output = array('error' =>'FILE_SIZE_TO_LARGE');
					}else
						$output = array('error' =>'FILE_SIZE_ZERO');
				}else
					$output = array('error' =>'FILE_NOT_READABLE');
			}else
				$output = array('error' =>'FILE_NOT_EXISTS');
		}else
			$output = array('error' =>'FILE_NOT_FOUND');
	}else
		$output = array('error' =>'WRONG_FILE_ID');

	return $output;
}

/**
 * Deletes the file
 *
 * @param bool|string $file_id
 *
 * @return array|bool[]|mixed|string|string[]
 */
function spbc_scanner_file_delete( $file_id = false ){
    
    $file_id = $file_id ?: Post::get('file_id', 'hash');
    
	if($file_id){
	    
        if( State::getInstance()->data->no_sql )
            return spbc_scanner_file_delete___no_sql( $file_id );
        
        $usp = State::getInstance();
        $db  = DB::getInstance(
            $usp->data->db_request_string,
            $usp->data->db_user,
            $usp->data->db_password
        );
        
        $root_path = substr(CT_USP_SITE_ROOT, 0 ,-1);
        
		// Getting file info.
		$file_info = $db->fetch_all('SELECT *'
            .' FROM scanner_files'
          .' WHERE fast_hash = "' . $file_id . '"')[0];

		if(!empty($file_info)){
			
			$file_path = $file_info['status'] == 'QUARANTINED' ? $file_info['q_path'] : $root_path.$file_info['path'];
			
			if(file_exists($root_path.$file_info['path'])){
				if(is_writable($root_path.$file_info['path'])){
					
					// Getting file && API call
					$remeber = file_get_contents($file_path);
					$result = unlink($file_path);

					if($result){
						
						$response = Helper::http__request(
							CT_USP_URI,
							array(),
							'dont_split_to_array get',
							array( CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0, )
						);
						
						if( empty( $response['error'] ) ){
							
							if( Helper::http__request__get_response_code( CT_USP_URI ) && ! Helper::search_page_errors( $response ) ){
								// Deleting row from DB
								$db->execute('DELETE FROM scanner_files WHERE fast_hash = "'.$file_id.'"');
							}else{
								$output = array('error' =>'WEBSITE_RESPONSE_BAD');
								$result = file_put_contents( $file_path, $remeber );
								$output['error'] .= $result === false ? ' REVERT_FAILED' : ' REVERT_OK';
							}
							$output = array('success' => true);
						}else{
							$output = $response;
							$result = file_put_contents( $file_path, $remeber );
							$output['error'] .= $result === false ? ' REVERT_FAILED' : ' REVERT_OK';
						}
					}else
						$output = array('error' =>'FILE_COULDNT_DELETE');
				}else
					$output = array('error' =>'FILE_NOT_WRITABLE');
			}else
				$output = array('error' =>'FILE_NOT_EXISTS');
		}else
			$output = array('error' =>'FILE_NOT_FOUND');
	}else
		$output = array('error' =>'WRONG_FILE_ID');

	return $output;
}

/**
 * Outputs JSON representation of a file
 *
 * @param bool|string $file_id
 */
function spbc_scanner_file_view( $file_id = false ){

    $file_id = $file_id ?: Post::get('file_id', 'hash');
    
	if($file_id){

        $usp = State::getInstance();
        $db  = DB::getInstance(
            $usp->data->db_request_string,
            $usp->data->db_user,
            $usp->data->db_password
        );

		$root_path = substr(CT_USP_SITE_ROOT, 0 ,-1);

		// Getting file info.
		// Getting file info.
		$file_info = $db->fetch_all('SELECT *'
            .' FROM scanner_files'
            .' WHERE fast_hash = "' . $file_id . '"')[0];
		
		if ( ! empty( $file_info ) ) {
			
			$file_path = $file_info['status'] == 'QUARANTINED' ? $file_info['q_path'] : $root_path.$file_info['path'];
			
			if ( file_exists( $file_path ) ) {
				if ( is_readable( $file_path ) ) {

					// Getting file && API call
					$file = file( $root_path . $file_info['path'] );

					if($file !== false && count($file)){

						$file_text = array();
						for($i=0; isset($file[$i]); $i++){
							$file_text[$i+1] = htmlspecialchars($file[$i]);
							$file_text[$i+1] = preg_replace("/[^\S]{4}/", "&nbsp;", $file_text[$i+1]);
						}

						if(!empty($file_text)){
							$output = array(
								'success' => true,
								'file' => $file_text,
								'file_path' => $root_path . $file_info['path'],
								'difference' => $file_info['difference'],
								'weak_spots' => $file_info['weak_spots']
							);

						}else
							$output = array('error' =>'FILE_TEXT_EMPTY');
					}else
						$output = array('error' =>'FILE_EMPTY');
				}else
					$output = array('error' =>'FILE_NOT_READABLE');
			}else
				$output = array('error' =>'FILE_NOT_EXISTS');
		}else
			$output = array('error' =>'FILE_NOT_FOUND');
	}else
		$output = array('error' =>'WRONG_FILE_ID');

	die(json_encode( $output, true ));
}

function spbc_scanner__display__prepare_data__files( &$table ){

	$usp = State::getInstance();
	$signatures = new Storage('signatures', null, '', 'csv', array(
		'id',
		'name',
		'body',
		'type',
		'attack_type',
		'submitted',
		'cci'
	) );
	$signatures = $signatures->convertToArray();
	
	if($table->items_count){

		$root = substr(CT_USP_SITE_ROOT, 0, -1);

		foreach($table->rows as $key => $row){

			// Filtering row actions
			if( ( isset( $row->last_sent ) && $row->last_sent > $row->mtime ) || $row->size == 0 || $row->size > 1048570)
				unset($row->actions['send']);
			if( ! isset( $row->severity ) )
				unset($row->actions['view_bad']);
			if( isset( $row->status ) && $row->status === 'quarantined' )
				unset($row->actions['quarantine']);
			
			$table->items[] = array(
				'cb'       => $row->fast_hash,
				'uid'      => $row->fast_hash,
				'size'     => substr(number_format($row->size, 2, ',', ' '), 0, -3),
				'perms'    => $row->perms,
				'mtime'    => date('M d Y H:i:s', $row->mtime),
				'path'     => strlen($root.$row->path) >= 40
					? '<div class="spbcShortText">...' . substr($row->path, -40) . '</div><div class="spbcFullText --hide">' . $root . $row->path . '</div>'
					: $root . $row->path,
				'actions' => $row->actions,
			);
			
			if(isset($row->weak_spots)){
				$weak_spots = json_decode($row->weak_spots, true);
				if($weak_spots){
					if(!empty($weak_spots['SIGNATURES'])){
						foreach ($weak_spots['SIGNATURES'] as $string => $weak_spot_in_string) {
							foreach ($weak_spot_in_string as $weak_spot) {

								$index = array_search(
									$weak_spot,
									array_column($signatures, 'id')
								);
								$signature = $signatures[ $index ];
								$ws_string = '<span class="--red">'. $signature['attack_type'] .': </span>'
								             .(strlen($signature['name']) > 30
										? substr($signature['name'], 0, 30).'...'
										: $signature['name']);
							}
						}
					}elseif(!empty($weak_spots['CRITICAL'])){
						foreach ($weak_spots['CRITICAL'] as $string => $weak_spot_in_string) {
							foreach ($weak_spot_in_string as $weak_spot) {
								$ws_string = '<span class="--red">Heuristic: </span>'
								             .(strlen($weak_spot) > 30
										? substr($weak_spot, 0, 30).'...'
										: $weak_spot);
							}
						}
					}elseif(!empty($weak_spots['DANGER'])) {
						foreach ( $weak_spots['DANGER'] as $string => $weak_spot_in_string ) {
							foreach ( $weak_spot_in_string as $weak_spot ) {
								$ws_string = '<span class="--orange1">Suspicious: </span>'
								             . ( strlen( $weak_spot ) > 30
										? substr( $weak_spot, 0, 30 ) . '...'
										: $weak_spot );
							}
						}
					}elseif(!empty($weak_spots['SUSPICIOUS'])) {
						foreach ( $weak_spots['SUSPICIOUS'] as $string => $weak_spot_in_string ) {
							foreach ( $weak_spot_in_string as $weak_spot ) {
								$ws_string = '<span class="--orange">Suspicious: </span>'
								             . ( strlen( $weak_spot ) > 30
										? substr( $weak_spot, 0, 30 ) . '...'
										: $weak_spot );
							}
						}
					}else{
						$ws_string = '';
					}
				}else
					$ws_string = '';

				$table->items[$key]['weak_spots'] = $ws_string;
			}
		}
	}
}

function usp_scanner__display(){

	$usp = State::getInstance();

	// Key is bad
	if(!$usp->valid) {

		$button = '<input type="button" class="button button-primary" value="' . __( 'To setting', 'security-malware-firewall' ) . '"  />';
		$link   = sprintf(
			'<a	href="#" onclick="usp_switchTab(\'settings\', {target: \'#ctusp_field---key\', action: \'highlight\', times: 3});">%s</a>',
			$button
		);
		echo '<div style="margin: 10px auto; text-align: center;"><h3 style="margin: 5px; display: inline-block;">' . __( 'Please, enter valid API key.', 'security-malware-firewall' ) . '</h3>' . $link . '</div>';

		return;
	}

	// Key is ok
	if ( $usp->valid && ! $usp->moderate ) {

		$button = '<input type="button" class="button button-primary" value="' . __( 'RENEW', 'security-malware-firewall' ) . '"  />';
		$link   = sprintf( '<a target="_blank" href="https://cleantalk.org/my/bill/security?cp_mode=security&utm_source=wp-backend&utm_medium=cpc&utm_campaign=WP%%20backend%%20trial_security&user_token=%s">%s</a>', $usp->user_token, $button );
		echo '<div style="margin-top: 10px;"><h3 style="margin: 5px; display: inline-block;">' . __( 'Please renew your security license.', 'security-malware-firewall' ) . '</h3>' . $link . '</div>';

		return;
	}

	// Key is ok
	if ( ! $usp->settings->scanner_heuristic_analysis && ! $usp->settings->scanner_signature_analysis ) {

		$button = '<input type="button" class="button button-primary" value="' . __( 'To setting', 'security-malware-firewall' ) . '"  />';
		$link   = sprintf(
			'<a	href="#" onclick="usp_switchTab(\'settings\', {target: \'.ctusp_group---malware_scanner\', action: \'highlight\', times: 3});">%s</a>',
			$button
		);
		echo '<div style="margin: 10px auto; text-align: center;"><h3 style="margin: 5px; display: inline-block;">' . __( 'All types of scannig is switched off, please, enable at least one.', 'security-malware-firewall' ) . '</h3>' . $link . '</div>';

		return;
	}

	// Info about last scanning
	echo '<p class="spbc_hint text-center">';
		if( !$usp->data->stat->scanner->last_scan )
			echo __('System hasn\'t been scanned yet. Please, perform the scan to secure the website. ', 'security-malware-firewall');
		else{
			if ( $usp->data->stat->scanner->last_scan < time() - 86400 * 7 )
				echo  __('Website hasn\'t been scanned for a long time.', 'security-malware-firewall');
			printf(
				__('Website last scan was performed on %s, %d files were scanned. ', 'security-malware-firewall'),
				date( 'M d Y H:i:s', $usp->data->stat->scanner->last_scan ),
				$usp->data->stat->scanner->last_scan_amount
			);

		}
	echo '</p>';

	// Statistics link
	echo '<p class="spbc_hint text-center">';
		echo sprintf(
			__('%sView all scan results for this website%s', 'security-malware-firewall'),
			'<a target="blank" href="https://cleantalk.org/my/logs_mscan?service='.$usp->service_id . '&user_token='. Cleantalk\USP\Common\State::getInstance()->user_token .'">',
			'</a>'
		);
	echo '</p>';

	// Start scan button
	echo '<div style="text-align: center;">'
	     .'<button id="spbc_perform_scan" class="btn btn-setup" type="button">'
	     .__('Perform scan', 'security-malware-firewall')
	     .'</button>'
	     .'<img  class="preloader" src="'.CT_USP_URI.'img/preloader.gif" />'
     .'</div>'
	 .'<br>';


	echo '<p class="spbc_hint spbc_hint_warning spbc_hint_warning__long_scan text-center" style="display: none; margin-top: 5px;">'
		. __('A lot of files found to scan. It would take time.', 'security-malware-firewall')
		. '</p>';
	// Stages
	echo '<div id="spbc_scaner_progress_overall text-center" class="--hide" style="padding-bottom: 10px;">';

		echo '<span class="spbc_overall_scan_status_clear_table">'      .__('Preparing', 'security-malware-firewall')                          .'</span> -> '
			.'<span class="spbc_overall_scan_status_count_files">'            .__('Counting files', 'security-malware-firewall')                     .'</span> -> ';
		if ( $usp->settings->scanner_signature_analysis )
			echo '<span class="spbc_overall_scan_status_scan_signatures">'.__('Signature analysis', 'security-malware-firewall').'</span> -> ';
		if ( $usp->settings->scanner_heuristic_analysis )
			echo '<span class="spbc_overall_scan_status_scan_heuristic">'.__('Heuristic analysis', 'security-malware-firewall').'</span> -> ';
		echo '<span class="spbc_overall_scan_status_send_results">'.__('Sending results', 'security-malware-firewall').'</span>';

	echo '</div>';

	echo '<div id="spbc_dialog" title="File output" style="overflow: initial;"></div>';

	// Progressbar
	echo '<div id="spbc_scaner_progress_bar" class="--hide" style="height: 22px;"><div class="spbc_progressbar_counter"><span></span></div></div>';
	
	if( $usp->data->stat->scanner->last_scan ){
		
		$db = DB::getInstance(
			$usp->data->db_request_string,
			$usp->data->db_user,
			$usp->data->db_password
		);
		
		if( $db ){
			
			$table = new ListTable(
				$db,
				array(
					'sql'               => array(
						'table' => 'scanner_files',
						'where' => ' WHERE status = \'INFECTED\'',
						'add_col' => array(
							'fast_hash'
						),
					),
					'columns'           => array(
//						'cb'         => array('heading' => '<input type=checkbox>',	'class' => 'check-column',),
						'path'       => array( 'heading' => 'Path', 'primary' => true, ),
						'size'       => array( 'heading' => 'Size, bytes', ),
						'perms'      => array( 'heading' => 'Permissions', ),
						'weak_spots' => array( 'heading' => 'Detected' ),
						'mtime'      => array( 'heading' => 'Last Modified', ),
					),
					'func_data_prepare' => 'spbc_scanner__display__prepare_data__files',
					'if_empty_items'    => '<p class="text-center" style="margin-top: 20px;">' . __( 'No threats to display', 'security-malware-firewall' ) . '</p>',
					'html_before'       => '<p>' . __( 'These files may not contain malicious code but they use very dangerous PHP functions and constructions! PHP developers don\'t recommend to use it and it looks very suspicious.', 'security-malware-firewall' ) . '</p>',
					'actions'           => array(
						'send'   => array( 'name' => 'Send for Analysis', ),
						'view'   => array( 'name'    => 'View',
						                   'handler' => 'spbc_scanner_button_file_view_event(this);',
						),
						'view_bad'   => array('name' => 'View Bad Code', 'handler' => 'spbc_scanner_button_file_view_bad_event(this);',),
						'delete' => array( 'name' => 'Delete', ),
//						'quarantine' => array('name' => 'Quarantine it',),
					),
		//			'bulk_actions'  => array(
		//				'send'       => array('name' => 'Send',),
		//				'delete'  => array('name' => 'Delete',),
		////				'approve'    => array('name' => 'Approve',),
		////				'quarantine' => array('name' => 'Quarantine it',),
		//			),
		//			'sortable' => array('path', 'size', 'perms', 'mtime',),
					'pagination'        => array(
						'page'     => 1,
						'per_page' => ListTable::$NUMBER_ELEMENTS_TO_VIEW,
					),
					'order_by'          => array( 'path' => 'asc' ),
				)
			);
			
			$table->get_data()
			      ->display();
		}
	}
}

function usp_scanner__display__count__files___no_sql(){
	return State::getInstance()->scan_result ? State::getInstance()->scan_result->count() : 0;
}

function usp_scanner__display__get_data__files___no_sql( $offset = 0, $limit = 20, $order_by = '', $direction = 'DESC' ) {
	return array_slice(
		State::getInstance()->scan_result->array_values(),
		$offset,
		$limit
	);
}

function usp_scanner__display__prepare_data__files___no_sql( &$table ){
	
	$usp = State::getInstance();
	$signatures = new Storage('signatures', null, '', 'csv', array(
		'id',
		'name',
		'body',
		'type',
		'attack_type',
		'submitted',
		'cci'
	) );
	$signatures = $signatures->convertToArray();
	
	if($table->items_count){
		
		$root = substr(CT_USP_SITE_ROOT, 0, -1);
		
		foreach($table->rows as $key => $row){
			
			// Filtering row actions
			if( ( isset( $row->last_sent ) && $row->last_sent > $row->mtime ) || $row->size == 0 || $row->size > 1048570)
				unset($row->actions['send']);
			if( ! isset( $row->severity ) )
				unset($row->actions['view_bad']);
			if( isset( $row->status ) && $row->status === 'quarantined' )
				unset($row->actions['quarantine']);
			
			$table->items[] = array(
				'cb'       => $row->fast_hash,
				'uid'      => $row->fast_hash,
				'size'     => substr(number_format($row->size, 2, ',', ' '), 0, -3),
				'perms'    => $row->perms,
				'mtime'    => date('M d Y H:i:s', $row->mtime),
				'path'     => strlen($root.$row->path) >= 40
					? '<div class="spbcShortText">...' . substr($row->path, -40) . '</div><div class="spbcFullText --hide">' . $root . $row->path . '</div>'
					: $root . $row->path,
				'actions' => $row->actions,
			);
			
			if(isset($row->weak_spots)){
				$weak_spots = json_decode($row->weak_spots, true);
				if($weak_spots){
					if(!empty($weak_spots['SIGNATURES'])){
						foreach ($weak_spots['SIGNATURES'] as $string => $weak_spot_in_string) {
							foreach ($weak_spot_in_string as $weak_spot) {
								
								$index = array_search(
									$weak_spot,
									array_column($signatures, 'id')
								);
								$signature = $signatures[ $index ];
								$ws_string = '<span class="--red">'. $signature['attack_type'] .': </span>'
								             .(strlen($signature['name']) > 30
										? substr($signature['name'], 0, 30).'...'
										: $signature['name']);
							}
						}
					}elseif(!empty($weak_spots['CRITICAL'])){
						foreach ($weak_spots['CRITICAL'] as $string => $weak_spot_in_string) {
							foreach ($weak_spot_in_string as $weak_spot) {
								$ws_string = '<span class="--red">Heuristic: </span>'
								             .(strlen($weak_spot) > 30
										? substr($weak_spot, 0, 30).'...'
										: $weak_spot);
							}
						}
					}elseif(!empty($weak_spots['DANGER'])) {
						foreach ( $weak_spots['DANGER'] as $string => $weak_spot_in_string ) {
							foreach ( $weak_spot_in_string as $weak_spot ) {
								$ws_string = '<span class="--orange1">Suspicious: </span>'
								             . ( strlen( $weak_spot ) > 30
										? substr( $weak_spot, 0, 30 ) . '...'
										: $weak_spot );
							}
						}
					}elseif(!empty($weak_spots['SUSPICIOUS'])) {
						foreach ( $weak_spots['SUSPICIOUS'] as $string => $weak_spot_in_string ) {
							foreach ( $weak_spot_in_string as $weak_spot ) {
								$ws_string = '<span class="--orange">Suspicious: </span>'
								             . ( strlen( $weak_spot ) > 30
										? substr( $weak_spot, 0, 30 ) . '...'
										: $weak_spot );
							}
						}
					}else{
						$ws_string = '';
					}
				}else
					$ws_string = '';
				
				$table->items[$key]['weak_spots'] = $ws_string;
			}
		}
	}
}

function usp_scanner__display___no_sql(){
	
	$usp = State::getInstance();
	
	// Key is bad
	if(!$usp->valid) {
		
		$button = '<input type="button" class="button button-primary" value="' . __( 'To setting', 'security-malware-firewall' ) . '"  />';
		$link   = sprintf(
			'<a	href="#" onclick="usp_switchTab(\'settings\', {target: \'#ctusp_field---key\', action: \'highlight\', times: 3});">%s</a>',
			$button
		);
		echo '<div style="margin: 10px auto; text-align: center;"><h3 style="margin: 5px; display: inline-block;">' . __( 'Please, enter valid API key.', 'security-malware-firewall' ) . '</h3>' . $link . '</div>';
		
		return;
	}
	
	// Key is ok
	if ( $usp->valid && ! $usp->moderate ) {
		
		$button = '<input type="button" class="button button-primary" value="' . __( 'RENEW', 'security-malware-firewall' ) . '"  />';
		$link   = sprintf( '<a target="_blank" href="https://cleantalk.org/my/bill/security?cp_mode=security&utm_source=wp-backend&utm_medium=cpc&utm_campaign=WP%%20backend%%20trial_security&user_token=%s">%s</a>', $usp->user_token, $button );
		echo '<div style="margin-top: 10px;"><h3 style="margin: 5px; display: inline-block;">' . __( 'Please renew your security license.', 'security-malware-firewall' ) . '</h3>' . $link . '</div>';
		
		return;
	}
	
	// Key is ok
	if ( ! $usp->settings->scanner_heuristic_analysis && ! $usp->settings->scanner_signature_analysis ) {
		
		$button = '<input type="button" class="button button-primary" value="' . __( 'To setting', 'security-malware-firewall' ) . '"  />';
		$link   = sprintf(
			'<a	href="#" onclick="usp_switchTab(\'settings\', {target: \'.ctusp_group---malware_scanner\', action: \'highlight\', times: 3});">%s</a>',
			$button
		);
		echo '<div style="margin: 10px auto; text-align: center;"><h3 style="margin: 5px; display: inline-block;">' . __( 'All types of scannig is switched off, please, enable at least one.', 'security-malware-firewall' ) . '</h3>' . $link . '</div>';
		
		return;
	}
	
	// Info about last scanning
	echo '<p class="spbc_hint text-center">';
	if( !$usp->data->stat->scanner->last_scan )
		echo __('System hasn\'t been scanned yet. Please, perform the scan to secure the website. ', 'security-malware-firewall');
	else{
		if ( $usp->data->stat->scanner->last_scan < time() - 86400 * 7 )
			echo  __('Website hasn\'t been scanned for a long time.', 'security-malware-firewall');
		printf(
			__('Website last scan was performed on %s, %d files were scanned. ', 'security-malware-firewall'),
			date( 'M d Y H:i:s', $usp->data->stat->scanner->last_scan ),
			$usp->data->stat->scanner->last_scan_amount
		);
		
	}
	echo '</p>';
	
	// Statistics link
	echo '<p class="spbc_hint text-center">';
	echo sprintf(
		__('%sView all scan results for this website%s', 'security-malware-firewall'),
		'<a target="blank" href="https://cleantalk.org/my/logs_mscan?service='.$usp->service_id . '&user_token='. Cleantalk\USP\Common\State::getInstance()->user_token .'">',
		'</a>'
	);
	echo '</p>';
	
	// Start scan button
	echo '<div style="text-align: center;">'
	     .'<button id="spbc_perform_scan" class="btn btn-setup" type="button">'
	     .__('Perform scan', 'security-malware-firewall')
	     .'</button>'
	     .'<img  class="preloader" src="'.CT_USP_URI.'img/preloader.gif" />'
	     .'</div>';
	echo '<br>';
	
	echo '<p class="spbc_hint spbc_hint_warning spbc_hint_warning__long_scan text-center" style="display: none; margin-top: 5px;">'
	     . __('A lot of files found to scan. It would take time.', 'security-malware-firewall')
	     . '</p>';
	// Stages
	echo '<div id="spbc_scaner_progress_overall text-center" class="--hide" style="padding-bottom: 10px;">';
	
	echo '<span class="spbc_overall_scan_status_clear_table">'      .__('Preparing', 'security-malware-firewall')                          .'</span> -> '
	     .'<span class="spbc_overall_scan_status_count_files">'            .__('Counting files', 'security-malware-firewall')                     .'</span> -> ';
	if ( $usp->settings->scanner_signature_analysis )
		echo '<span class="spbc_overall_scan_status_scan_signatures">'.__('Signature analysis', 'security-malware-firewall').'</span> -> ';
	if ( $usp->settings->scanner_heuristic_analysis )
		echo '<span class="spbc_overall_scan_status_scan_heuristic">'.__('Heuristic analysis', 'security-malware-firewall').'</span> -> ';
	echo '<span class="spbc_overall_scan_status_send_results">'.__('Sending results', 'security-malware-firewall').'</span>';
	
	echo '</div>';
	
	echo '<div id="spbc_dialog" title="File output" style="overflow: initial;"></div>';
	
	// Progressbar
	echo '<div id="spbc_scaner_progress_bar" class="--hide" style="height: 22px;"><div class="spbc_progressbar_counter"><span></span></div></div>';
	
	$table = new ListTable(
		NULL,
		array(
			'columns' => array(
//				'cb'         => array('heading' => '<input type=checkbox>',	'class' => 'check-column',),
				'path'       => array('heading' => 'Path','primary' => true,),
				'size'       => array('heading' => 'Size, bytes',),
				'perms'      => array('heading' => 'Permissions',),
				'weak_spots' => array('heading' => 'Detected'),
				'mtime'      => array('heading' => 'Last Modified',),
			),
			'func_data_total'   => 'usp_scanner__display__count__files___no_sql',
			'func_data_get'     => 'usp_scanner__display__get_data__files___no_sql',
			'func_data_prepare' => 'usp_scanner__display__prepare_data__files___no_sql',
			'if_empty_items' => '<p class="text-center" style="margin-top: 20px;">'.__('No threats to display', 'security-malware-firewall').'</p>',
			'html_before' => '<p>' . __('These files may not contain malicious code but they use very dangerous PHP functions and constructions! PHP developers don\'t recommend to use it and it looks very suspicious.', 'security-malware-firewall') . '</p>',
			'actions' => array(
				'send'       => array('name' => 'Send for Analysis',),
				'view'    => array('name' => 'View', 'handler' => 'spbc_scanner_button_file_view_event(this);',),
//				'view_bad'   => array('name' => 'View Bad Code', 'handler' => 'spbc_scanner_button_file_view_bad_event(this);',),
				'delete'  => array('name' => 'Delete',),
//				'quarantine' => array('name' => 'Quarantine it',),
			),
//			'bulk_actions'  => array(
//				'send'       => array('name' => 'Send',),
//				'delete'  => array('name' => 'Delete',),
////				'approve'    => array('name' => 'Approve',),
////				'quarantine' => array('name' => 'Quarantine it',),
//			),
//			'sortable' => array('path', 'size', 'perms', 'mtime',),
			'pagination' => array(
				'page'     => 1,
				'per_page' => ListTable::$NUMBER_ELEMENTS_TO_VIEW,
			),
			'order_by'  => array('path' => 'asc'),
		)
	);
	
	$table->get_data();
	$table->display();
}

/**
 * Sends file for analysis via security_mscan_files method
 *
 * @param bool|string $file_id
 *
 * @return array|bool|mixed|string[]
 */
function spbc_scanner_file_send___no_sql( $file_id = false ){
 
	$usp = State::getInstance();
	
	$root_path = substr(CT_USP_SITE_ROOT, 0 ,-1);
	
    $file_id = $file_id ?: Post::get('file_id', 'hash');
	
	if($file_id){
		
		// Getting file info.
		$index = array_search(
			$file_id,
			array_column($usp->scan_result->convertToArray(), 'fast_hash')
		);
		$file_info = $usp->scan_result->$index;

//		// Scan file before send it
//		@todo make heuristic rescan
//		// Heuristic
//		$result_heur = Controller::file__scan__heuristic($root_path, $file_info);
//		if(!empty($result['error'])){
//			$output = array('error' =>'RESCACNNING_FAILED');
//			if($direct_call) return $output; else die(json_encode($output));
//		}
//		@todo make signature rescan
//		// Signature
//		$signatures = $wpdb->get_results('SELECT * FROM '. SPBC_TBL_SCAN_SIGNATURES, ARRAY_A);
//		$result_sign = Controller::file__scan__for_signatures($root_path, $file_info, $signatures);
//		if(!empty($result['error'])){
//			$output = array('error' =>'RESCACNNING_FAILED');
//			if($direct_call) return $output; else die(json_encode($output));
//		}

//		$result = Helper::array_merge__save_numeric_keys__recursive($result_sign, $result_heur);

//		$wpdb->update(
//			SPBC_TBL_SCAN_FILES,
//			array(
//				'checked'    => $file_info['checked'],
//				'status'     => $file_info['status'] === 'MODIFIED' ? 'MODIFIED' : $result['status'],
//				'severity'   => $result['severity'],
//				'weak_spots' => json_encode($result['weak_spots']),
//				'full_hash'  => md5_file($root_path.$file_info['path']),
//			),
//			array( 'fast_hash' => $file_info['fast_hash'] ),
//			array( '%s', '%s', '%s', '%s', '%s' ),
//			array( '%s' )
//		);
//		$file_info['weak_spots'] = $result['weak_spots'];
//		$file_info['full_hash']  = md5_file($root_path.$file_info['path']);
		
		if(!empty($file_info)){
			if(file_exists($root_path.$file_info['path'])){
				if(is_readable($root_path.$file_info['path'])){
					if(filesize($root_path.$file_info['path']) > 0){
						if(filesize($root_path.$file_info['path']) < 1048570){
							
							// Getting file && API call
							$file = file_get_contents($root_path.$file_info['path']);
							$result = API::method__security_mscan_files($usp->settings->key, $file_info['path'], $file, $file_info['full_hash'], $file_info['weak_spots']);
							
							if(empty($result['error'])){
								if($result['result']){

//									// Updating "last_sent"
//									$sql_result = $wpdb->query('UPDATE '.SPBC_TBL_SCAN_FILES.' SET last_sent = '.current_time('timestamp').' WHERE fast_hash = "'.$file_id.'"');

//									if($sql_result !== false){
									$output = array('success' => true, 'result' => $result);
//									}else
//										$output = array('error' =>'DB_COULDNT_UPDATE_ROW');
								}else
									$output = array('error' =>'API_RESULT_IS_NULL');
							}else
								$output = $result;
						}else
							$output = array('error' =>'FILE_SIZE_TO_LARGE');
					}else
						$output = array('error' =>'FILE_SIZE_ZERO');
				}else
					$output = array('error' =>'FILE_NOT_READABLE');
			}else
				$output = array('error' =>'FILE_NOT_EXISTS');
		}else
			$output = array('error' =>'FILE_NOT_FOUND');
	}else
		$output = array('error' =>'WRONG_FILE_ID');
	
	return $output;
}

/**
 * Deletes the file
 *
 * @param bool|string $file_id
 *
 * @return bool[]|string[]
 */
function spbc_scanner_file_delete___no_sql( $file_id = false ){
	
	$usp = State::getInstance();
	
	$root_path = substr(CT_USP_SITE_ROOT, 0 ,-1);
    
    $file_id = $file_id ?: Post::get('file_id', 'hash');
	
	if($file_id){
		
		// Getting file info.
		$index = array_search(
			$file_id,
			array_column($usp->scan_result->convertToArray(), 'fast_hash')
		);
		$file_info = $usp->scan_result->$index;
		
		if(!empty($file_info)){
			if(file_exists($root_path.$file_info['path'])){
				if(is_writable($root_path.$file_info['path'])){
					
					// Getting file && API call
					$result = unlink($root_path.$file_info['path']);
					
					if($result){
						
						// Deleting row from DB
						unset($usp->scan_result->$index);
						$usp->scan_result->save();
						
						$output = array('success' => true);
						
					}else
						$output = array('error' =>'FILE_COULDNT_DELETE');
				}else
					$output = array('error' =>'FILE_NOT_WRITABLE');
			}else
				$output = array('error' =>'FILE_NOT_EXISTS');
		}else
			$output = array('error' =>'FILE_NOT_FOUND');
	}else
		$output = array('error' =>'WRONG_FILE_ID');
	
	return $output;
}

/**
 * Outputs JSON representation of a file
 *
 * @param bool|string $file_id
 */
function spbc_scanner_file_view___no_sql( $file_id = false ){
    
    $file_id = $file_id ?: Post::get('file_id', 'hash');
	
	if($file_id){
		
		$root_path = substr(CT_USP_SITE_ROOT, 0 ,-1);
		$usp = State::getInstance();
		
		// Getting file info.
		$index = array_search(
			$file_id,
			array_column($usp->scan_result->convertToArray(), 'fast_hash')
		);
		$file_info = $usp->scan_result->$index;
		
		if ( ! empty( $file_info ) ) {
			if ( file_exists( $root_path . $file_info['path'] ) ) {
				if ( is_readable( $root_path . $file_info['path'] ) ) {
					
					// Getting file && API call
					$file = file( $root_path . $file_info['path'] );
					
					if($file !== false && count($file)){
						
						$file_text = array();
						for($i=0; isset($file[$i]); $i++){
							$file_text[$i+1] = htmlspecialchars($file[$i]);
							$file_text[$i+1] = preg_replace("/[^\S]{4}/", "&nbsp;", $file_text[$i+1]);
						}
						
						if(!empty($file_text)){
							$output = array(
								'success' => true,
								'file' => $file_text,
								'file_path' => $root_path . $file_info['path'],
								'difference' => $file_info['difference'],
								'weak_spots' => $file_info['weak_spots']
							);
							
						}else
							$output = array('error' =>'FILE_TEXT_EMPTY');
					}else
						$output = array('error' =>'FILE_EMPTY');
				}else
					$output = array('error' =>'FILE_NOT_READABLE');
			}else
				$output = array('error' =>'FILE_NOT_EXISTS');
		}else
			$output = array('error' =>'FILE_NOT_FOUND');
	}else
		$output = array('error' =>'WRONG_FILE_ID');
	
	die(json_encode( $output, true ));
}