<?php

namespace Cleantalk\USP;

use Cleantalk\USP\Common\Err;
use Cleantalk\USP\Common\State;
use Cleantalk\USP\Scanner\Common;
use Cleantalk\USP\Scanner\Scanner;
use Cleantalk\USP\Uniforce\API;
use Cleantalk\USP\Uniforce\Helper;
use Cleantalk\USP\Scanner\Helper as ScannerHelper;
use Cleantalk\USP\Variables\Get;

class ScannerController {
	
	const table__scanner___files        = 'scanner_files';
	const table__scanner___links        = 'scanner_links';
	const table__scanner___backups      = 'scanner_backups';
	const table__scanner___backup_files = 'scanner_backup_files';
	
	private static $instance;
	
	/**
	 * DB handler
	 *
	 * @var DB
	 */
	private $db = null;
	
	/**
	 * Site root directory
	 *
	 * @var string
	 */
	private $root = '';
	
	/**
	 * Current action offset
	 *
	 * @var int
	 */
	private $offset = 0;
	
	/**
	 * Current action
	 *
	 * @var string
	 */
	private $state = '';
	
	function __construct( $root_dir, $db_params = null ){
		
		if( $db_params ){
			$this->db = \Cleantalk\USP\DB::getInstance(
				$db_params[0],
				$db_params[1],
				$db_params[2]
			);
		}
		
		$this->root   = $root_dir;
		$this->offset = intval( Get::get( 'offset' ) ) ?: $this->offset;
		$this->offset = intval( Get::get( 'amount' ) ) ?: $this->offset;
		$this->state  = strval( Get::get( 'state' ) )  ?: $this->state;
	}
	
	private static $states = array(
		'create_db',
		'clear_table',
		'get_signatures',
		'surface_analysis',
		'get_approved',
		'signature_analysis',
		'heuristic_analysis',
		'auto_cure',
		'frontend_analysis',
		'outbound_links',
	);
	
	public function action__scanner__controller(){
		
		$usp = State::getInstance();

		sleep(5);
		
		switch( $this->state ){
			
			// Creating DB
			case 'create_db':
				$result = $this->action__scanner__create_db();
				break;
			
			// Cleaning table
			case 'clear_table':
				$result = $this->action__scanner__clear_table(
					$this->offset,
					10000
				);
				break;
				
			//Signatures
			case 'get_signatures':
				
				$result = $this->action__scanner__get_signatures();
				
				break;
				
			// Searching for new files
			case 'surface_analysis':
				$result = $this->action__scanner__surface_analysis(
					$this->offset,
					1500,
					$this->root
				);
				break;
			
			// Searching for new files
			case 'get_approved':
				$result = $this->action__scanner__get_approved();
				break;
				
			// Signatures
			case 'signature_analysis':

				$result = $this->action__scanner__signature_analysis(
					$this->offset,
					10,
					$this->root
				);
				
				break;

			// Heuristic
			case 'analysis_heuristic':
				
				$result = $this->action__scanner__heuristic_analysis(
					$this->offset,
					10,
					$this->root
				);
				
				break;

			// Send result
			case 'send_results':

				$result = self::action__scanner__send_results( );
				$end = true;

				break;
		}

		// Make next call if everything is ok
		if( ! isset( $end ) && empty( $result['error'] ) ){
			
			$remote_call_params = array(
				'plugin_name'             => 'security',
				'spbc_remote_call_token'  => md5( $usp->settings->key ),
				'spbc_remote_call_action' => 'scanner__controller',
				'state'                   => $result['end'] ? $this->next_state( $this->state ) : $this->state,
				'offset'                  => $result['end'] ? 0 : $this->offset + $result['processed'],
			);
			
			Helper::http__request(
				CT_USP_AJAX_URI,
				$remote_call_params,
				'get async'
			);
			
		}

		// Delete or add an error
		empty( $result['error'] )
			? $usp->error_delete( $this->state, 'and_save_data', 'cron_scan' )
			: $usp->error_add( $this->state, $result, 'cron_scan' );

		return true;
	}
	
	/**
	 * Creates remote DB and get DB params
	 *
	 * @return array|bool[]
	 */
	public function action__scanner__create_db(){
		
		$usp = State::getInstance();
		
		$result = API::method__dbc2c_get_info( $usp->key );
//		$result = array();
		
		if( empty( $result['error'] ) ){
		
			$usp->data->db_request_string = 'mysql:host=' . $result['db_host'] . ';dbname=' . $result['db_name'] . ';charset=utf8';
			$usp->data->db_user           = $result['db_user'];
			$usp->data->db_password       = $result['db_password'];
			$usp->data->db_created        = $result['created'];
//			$usp->data->db_request_string = 'mysql:host=localhost;dbname=usp;charset=utf8';
//			$usp->data->db_user           = 'root';
//			$usp->data->db_password       = '';
//			$usp->data->db_created        = time();
			$usp->data->save();
			
			$out = array('success' => true, 'end' => true);
			
		}else
			$out = $result;
		
		return $out;
		
	}
	
	/**
	 * Clears all data about scanned files
	 *
	 * @param int $offset
	 * @param int $amount
	 *
	 * @return array
	 */
	public function action__scanner__clear_table( $offset = null, $amount = null ){
		
		if( ! $this->db )                           return array('error' => 'DB_NOT_PROVIDED');
		if( $this->db instanceof Cleantalk\USP\DB ) return array('error' => 'DB_BAD_CONNECTION');
		
		$offset = $offset ?: (int) Get::get('offset');
		$amount = $amount ?: (int) Get::get('amount');
		
		$result = $this->db->fetch_all(
			'SELECT path, fast_hash, status'
			. ' FROM ' . self::table__scanner___files
			. " LIMIT $offset, $amount;"
		);
		$checked = $this->db->rows_affected;
		
		$to_delete = array();
		foreach($result as $value){
			if( ! file_exists( $this->root . $value['path'] ) && $value['status'] != 'QUARANTINED' ){
				$to_delete[] = "'{$value['fast_hash']}'";
			}
		} unset($value);
		
		$deleted = 0;
		if( ! empty( $to_delete ) ){
			$deleted = $this->db->exec(
				'DELETE '
				. ' FROM ' . self::table__scanner___files
				. ' WHERE fast_hash IN (' . implode( ',', $to_delete ) . ');'
			);
		}
		
		$out = array(
			'processed' => (int) $checked,
			'deleted'   => (int) $deleted,
			'end'       => $checked < $amount,
		);
		
		// Count if needed
		if( $offset == 0 ){
			$result = $this->db->fetch_all(
				'SELECT count(fast_hash) as cnt'
				. ' FROM ' . self::table__scanner___files
			);
			$out['total'] = (int) $result[0]['cnt'];
		}
		
		if($deleted === false)
			$out['error'] = 'COULDNT_DELETE';

		return $out;
	}
	
	public function action__scanner__get_signatures(){
		
		if( ! $this->db )                           return array('error' => 'DB_NOT_PROVIDED');
		if( $this->db instanceof Cleantalk\USP\DB ) return array('error' => 'DB_BAD_CONNECTION');
		
		$usp = State::getInstance();
		
		$out = array(
			'success' => true,
		);
		
		if ( $usp->settings->scanner_signature_analysis ) {
			
			$result = ScannerHelper::get_hashes__signature($usp->data->stat->scanner->signature_last_update);
			
			if(empty($result['error'])){
				
				$signatures = new \Cleantalk\USP\Common\Storage( 'signatures', $result );
				$signatures->save();
				
				$usp->data->stat->scanner->signature_last_update = time();
				$usp->data->stat->scanner->signature_entries = count( $result );
				
				$path = CT_USP_DATA . 'signatures.php';
				
				$this->db->query('INSERT INTO '. self::table__scanner___files
                    .' (path, full_hash, fast_hash, mtime, size, perms, checked_signature, checked_heuristic, status, severity)'
					.' VALUES ("' . addslashes('\uniforce\data\signatures.php') .'", "' . md5_file( $path ) . '", "'. md5('\uniforce\data\signatures.php') .'", '. filemtime( $path ) .', '. filesize( $path ) .', '. fileperms( $path ) .', 1, 1, "APPROVED", NULL)'
					.' ON DUPLICATE KEY UPDATE'
						.' full_hash = VALUES(`full_hash`),'
						.' mtime = '. filemtime( $path ) .','
						.' size = '. filesize( $path ) .','
						.' perms = '. fileperms( $path ) .','
						.' status = "APPROVED",'
						.' severity = NULL,'
						.' weak_spots = NULL'
				);
				
			}elseif($result['error'] === 'UP_TO_DATE'){
				$out['success'] = 'UP_TO_DATE';
			}else
				$out['updated'] = count($result);
			
			$out['end'] = 1;
			
		}else
			Err::add('Signatures scan is disabled');
		
		return Err::check()
			? Err::check_and_output()
			: $out;
	}
	
	public function action__scanner__surface_analysis( $offset = null, $amount = null, $path = CT_USP_SITE_ROOT ){
		
		if( ! $this->db )                           return array('error' => 'DB_NOT_PROVIDED');
		if( $this->db instanceof Cleantalk\USP\DB ) return array('error' => 'DB_BAD_CONNECTION');
		
		$offset = $offset ?: (int) Get::get('offset');
		$amount = $amount ?: (int) Get::get('amount');
		
		$time_start = microtime(true);
		
		$path_to_scan = $path ?: realpath($this->root);
		$root_path    = realpath($this->root);
		$init_params = array(
			'fast_hash'        		=> true,
			'full_hash'       		=> true,
			'offset'                => $offset,
			'amount'                => $amount,
			'extensions'            => 'php, html, htm',
			'extensions_exceptions' => '',
			'file_exceptions'       => '',
			'files_mandatory' => array(),
			'dir_exceptions'  => array()
		);
		
		$scanner = new Scanner($path_to_scan, $root_path, $init_params);
		
		if( $scanner->files_count ){
			
			$sql_query =
				'INSERT INTO ' . self::table__scanner___files
		        . ' (`path`, `size`, `perms`, `mtime`,`status`,`fast_hash`, `full_hash`) VALUES ';
			
			$sql_query__params = array();
			foreach($scanner->files as $key => $file){
				
				$file['path'] = addslashes($file['path']);
				$sql_query__params[] = '("' . implode( '", "', $file ) .'")';
				
			} unset($key, $file);
			
			$sql_query .= implode( ',', $sql_query__params );
			$sql_query .= " ON DUPLICATE KEY UPDATE
			
			size           = VALUES(`size`),
			perms          = VALUES(`perms`),
			source_type    = source_type,
			source_name    = source_name,
			source_version = source_version,

			fast_hash = fast_hash,
			full_hash = VALUES(`full_hash`),
			real_full_hash = real_full_hash,
			
			checked_signature =
				IF(real_full_hash IS NOT NULL AND real_full_hash = VALUES(`full_hash`),
					1,
					IF(mtime <> VALUES(`mtime`) OR mtime IS NULL,
						0,
						checked_signature
					)
				),
			
			checked_heuristic =
				IF(real_full_hash IS NOT NULL AND real_full_hash = VALUES(`full_hash`),
					1,
					IF(mtime <> VALUES(`mtime`) OR mtime IS NULL,
						0,
						checked_heuristic
					)
				),
			
			status =
				IF(mtime <> VALUES(`mtime`) OR mtime IS NULL,
					IF(real_full_hash IS NULL,
						IF(checked_signature = 1 AND checked_heuristic = 1,
							status,
							'UNKNOWN'
						),
						IF(real_full_hash = VALUES(`full_hash`),
							'OK',
							'MODIFIED'
						)
					),
					status
				),
			
			mtime     = VALUES(`mtime`),
			
			severity  =
				IF(status <> 'OK' AND checked_signature = 1 AND checked_heuristic = 1,
					severity,
					NULL
				),
				
			weak_spots  =
				IF(checked_signature = 1 AND checked_heuristic = 1,
					weak_spots,
					NULL
				);";
			
			$success = $this->db->query($sql_query);
			
		}else
			$output  = array('error' => __FUNCTION__ . ' No files to scan',);
		
		if(isset($success)){
			$output  = array(
				'processed'   => $scanner->files_count,
				'dirs_count'  => $scanner->dirs_count,
				'end'         => $scanner->files_count < $amount,
				'exec_time'   => round(microtime(true) - $time_start, 3),
			);
		}
		
		if( $offset == 0 ){
			$scanner         = new Scanner(
				realpath( $this->root ),
				realpath( substr( $this->root, 0, - 1 ) ),
				array(
					'count'           => true,
					'file_exceptions' => '',
					'extensions'      => 'php, html, htm',
					'files_mandatory' => array(),
					'dir_exceptions'  => array()
				)
			);
			$output['total'] = (int) $scanner->files_count;
		}
		
		return $output;
	}
	
	/**
	 * Getting remote hashes of approved files
	 *
	 * @return array
	 */
	public function action__scanner__get_approved() {
		
		$result = ScannerHelper::get_hashes__approved_files('usp','approved', '1.0.0');
		
		if (empty($result['error'])) {
			
			$prepared_sql = $this->db->prepare('UPDATE '. self::table__scanner___files
			                                   .' SET
				checked_signature = 1,
				checked_heuristic = 1,
				status   =   \'APPROVED\',
				severity =   NULL
				WHERE path = :path AND full_hash = :full_hash;'
			);
			
			foreach ($result as $key => $value) {
				$prepared_sql->execute(array(
					':path' => $value[0],
					':full_hash' => $value[1],
				));
			}
		}
		
		return array(
			'end' => 1,
			'processed' => empty($result['error']) ? count($result) : 0,
		);
		
	}
	
	public function action__scanner__signature_analysis( $offset = null, $amount = null, $status = "'UNKNOWN','MODIFIED','OK','INFECTED'" ){
		
		if( ! $this->db )                           return array('error' => 'DB_NOT_PROVIDED');
		if( $this->db instanceof Cleantalk\USP\DB ) return array('error' => 'DB_BAD_CONNECTION');
		
		$status = Get::get( 'status' ) ? stripslashes( Get::get( 'status' ) ) : $status;
		$offset = $offset ?: (int) Get::get('offset');
		$amount = $amount ?: (int) Get::get('amount');

		$out = array(
			'found'     => 0,
			'processed' => 0,
		);
		
		if( $offset == 0 ){
			$result = $this->db->fetch_all(
				'SELECT COUNT(fast_hash) as cnt'
				.' FROM ' . self::table__scanner___files
				." WHERE checked_signature = 0"
			);
			$out['total'] = (int) $result[0]['cnt'];
		}
		
		$files_to_check = $this->db->fetch_all(
			'SELECT path, source_type, source_name, source_version, status, checked_signature, fast_hash, real_full_hash, full_hash, weak_spots, difference, severity'
			.' FROM ' . self::table__scanner___files
			." WHERE checked_signature = 0"
			." LIMIT $amount"
		);

		if ( $files_to_check ) {

			if ( ! empty( $files_to_check ) ) {
				
				$prepared_query = $this
					->db
					->prepare(
						'UPDATE ' . self::table__scanner___files
						. ' SET'
							.' checked_signature = 1,'
							.' status = :status,'
							.' severity = :severity,'
							.' weak_spots = :weak_spots'
							.' WHERE fast_hash = :fast_hash'
					);
				
				// Initialing results
				foreach ( $files_to_check as $file ) {
					
					$result = Scanner::file__scan__for_signatures( $this->root, $file, State::getInstance()->signatures->array_values() );
					
					if ( empty( $result['error'] ) ) {
						
						$status =     ! empty( $file['status'] ) && $file['status'] === 'MODIFIED' ? 'MODIFIED' : $result['status'];
						$weak_spots = ! empty( $result['weak_spots'] ) ? json_encode( $result['weak_spots'] ) : NULL;
						$severity =   ! empty( $file['severity'] )
							? $file['severity']
							: ( $result['severity'] ? $result['severity'] : null );
						
						$result_db = $prepared_query
							->execute(
								array(
									':status' => $status,
									':severity' => $severity,
									':weak_spots' => $weak_spots,
									':fast_hash' =>  $file['fast_hash'],
								)
							);
						
						$result['status'] !== 'OK' ? $out['found']++   : $out['found'];
						$result_db !== false       ? $out['scanned']++ : $out['scanned'];
						
					}else{
						// @todo Add exception Notice level
					}
					
					$out['processed']++;
				}
			}
		}
		
		$out['end'] = $out['processed'] < $amount;
		
		return $out;
	}

	public function action__scanner__heuristic_analysis( $offset = null, $amount = null, $path = '', $status = "'MODIFIED','UNKNOWN'" ) {
		
		if( ! $this->db )                           return array('error' => 'DB_NOT_PROVIDED');
		if( $this->db instanceof Cleantalk\USP\DB ) return array('error' => 'DB_BAD_CONNECTION');
		
		$status = Get::get( 'status' ) ? stripslashes( Get::get( 'status' ) ) : $status;
		$offset = $offset ?: (int) Get::get('offset');
		$amount = $amount ?: (int) Get::get('amount');
		$path = $path ?: (int) $this->root;

		$out = array(
			'found'     => 0,
			'processed' => 0,
		);
		
		if( $offset == 0 ){
			$result = $this->db->fetch_all(
				'SELECT COUNT(fast_hash) as cnt'
				.' FROM ' . self::table__scanner___files
				." WHERE checked_heuristic = 0"
			);
			$out['total'] = (int) $result[0]['cnt'];
		}
		
		$files_to_check = $this->db->fetch_all(
			'SELECT path, source_type, source_name, source_version, status, checked_heuristic, fast_hash, real_full_hash, full_hash, weak_spots, difference, severity'
			.' FROM ' . self::table__scanner___files
			." WHERE checked_heuristic = 0 AND (source_status <> 'OUTDATED' OR source_status IS NULL)"
			." LIMIT $amount"
		);
		
		if ( $files_to_check && count( $files_to_check )) {
			
			$prepared_query = $this->db->prepare('UPDATE '. self::table__scanner___files
				.' SET '
				.' checked_heuristic = 1,'
				.' status = ?,'
				.' severity = ?,'
				.' weak_spots = ?'
				.' WHERE fast_hash = ?'
			);
			
			foreach ( $files_to_check as $file ) {

				$result = Scanner::file__scan__heuristic( $this->root, $file );
				
				if(empty($result['error'])){
					
					$status     = $file['status'] === 'MODIFIED' ? 'MODIFIED'                           : $result['status'];
					$weak_spots = $result['weak_spots']          ? json_encode( $result['weak_spots'] ) : NULL;
					$severity   = $file['severity']
						? $file['severity']
						: ( $result['severity'] ? $result['severity'] : NULL );
					
					$result_db = $prepared_query->execute(
						array(
							$status,
							$severity,
							$weak_spots,
							$file['fast_hash'],
						)
					);
					
					$result['status'] !== 'OK' ? $out['found']++   : $out['found'];
					$result_db !== false       ? $out['scanned']++ : $out['scanned'];
					
				}else{
					// @todo Add exception Notice
				}

				$out['processed']++;

			}
		}
		
		$out['end'] = $out['processed'] < $amount;
		
		return $out;

	}

	public function action__scanner__send_results( ) {
		
		if( ! $this->db )                           return array('error' => 'DB_NOT_PROVIDED');
		if( $this->db instanceof Cleantalk\USP\DB ) return array('error' => 'DB_BAD_CONNECTION');
		
		$usp = State::getInstance();
		
		$total_scanned = $this->count_files_by_status( "'UNKNOWN','OK','APPROVED','MODIFIED','INFECTED','QUARANTINED'" );
		$bad_files     = $this->get_files_by_status( "'UNKNOWN', 'MODIFIED'",  array( 'path', 'full_hash', 'mtime', 'size', 'status') );
		
		$unknown  = array();
		$modified = array();
		
		if( count( $bad_files ) ){
			foreach( $bad_files as $file ){
				$file['path'] = Helper::is_windows() ? str_replace( '\\', '/', $file['path'] ) : $file['path'];
				if( $file['status'] == 'UNKNOWN' ){
					$unknown[ $file['path'] ] = array(
						$file['full_hash'],
						$file['mtime'],
						$file['size'],
					);
				} else{
					$modified[ $file['path'] ] = array(
						$file['full_hash'],
						$file['mtime'],
						$file['size'],
						$file['source_type'],
						$file['source'],
						$file['source_status'],
					);
				}
			}
		}

		// API. Sending files scan result
		$result = API::method__security_mscan_logs(
			$usp->key,
			$usp->service_id,
			date( 'Y-m-d H:i:s' ),
			$bad_files ? 'warning' : 'passed',
			$total_scanned,
			$modified,
			$unknown
		);
		
		if( empty( $result['error'] ) ){

			$usp->data->stat->scanner->last_sent        = time();
			$usp->data->stat->scanner->last_scan        = time();
			$usp->data->stat->scanner->last_scan_amount = isset($_GET['total_scanned']) ? $_GET['total_scanned'] : $total_scanned;

		}else
			Err::add('scanner_result_send', $result['error']);

		$usp->data->save();

		$result['end'] = 1;
		return $result;

	}
	
	public function get_files_by_status( $status, $data = '*' ) {
		$data = is_array( $data ) ? implode( ', ', $data ) : $data;
		return $this->db
			->fetch_all(
				'SELECT ' . $data
				.' FROM ' . self::table__scanner___files
				." WHERE status IN ( $status )");
	}
	
	public function count_files_by_status( $status ) {
		return $this->db->fetch_all(
			'SELECT COUNT(fast_hash) as cnt'
			.' FROM ' . self::table__scanner___files
			." WHERE status IN ( $status )")[0]['cnt'];
	}
	
	public function next_state( $state ){
		
		$state = self::$states[ array_search( $state, self::$states ) + 1 ];
		$usp = State::getInstance();
		$setting = 'scanner_' . $state;
		
		// Recursion
		if( isset( $usp->settings->$setting ) && $usp->settings->$setting === 0 ){
			$state = $this->next_state( $state );
			$this->offset = 0;
		}
		
		// Recursion. Base case
		return $state;
	}
}