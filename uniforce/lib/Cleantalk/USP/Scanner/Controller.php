<?php

namespace Cleantalk\USP\Scanner;

use Cleantalk\USP\Common\Err;
use Cleantalk\USP\Common\State;
use Cleantalk\USP\Uniforce\API;
use Cleantalk\USP\Uniforce\Helper;
use Cleantalk\USP\Variables\Get;

class Controller {

	private static $states = array(
		'clear_table',
		'signatures_scan',
		'heuristic_scan',
		'send_results',
	);

	private $current_stage = '';

	public static function get_files( $offset = 0, $amount = 1500, $path = CT_USP_SITE_ROOT ) {

		$usp = State::getInstance();

		$path_to_scan = realpath($path);
		$root_path = realpath( substr( CT_USP_SITE_ROOT, 0, - 1 ) );
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

		return $scanner->files_count
			? $scanner->files
			: false;
	}

	public static function action__scanner__get_signatures(){

		$usp = State::getInstance();

		$out = array(
			'success' => true,
		);

		if ( $usp->settings->scanner_signature_analysis ) {

			$result = \Cleantalk\USP\Scanner\Helper::get_hashes__signature($usp->data->stat->scanner->signature_last_update);

			if(empty($result['error'])){

				$signatures = new \Cleantalk\USP\Common\Storage( 'signatures', $result );
				$signatures->save();

				$usp->data->stat->scanner->signature_last_update = time();
				$usp->data->stat->scanner->signature_entries = count( $result );

			}elseif($result['error'] === 'UP_TO_DATE'){
				$out['success'] = 'UP_TO_DATE';
			}else
				$out['updated'] = count($result);
		}else
			Err::add('Signatures scan is disabled');

		return Err::check()
			? Err::check_and_output()
			: $out;
	}

	public static function action__scanner__controller(){

		$usp = State::getInstance();

		sleep(5);

		$state = Get::get('state')
			? Get::get('state')
			: 'clear_table';

		$prev_state = $state;
		$additional_params = array();

		switch($state){

			// Cleaning table
			case 'clear_table':
				self::action__scanner__clear_table();
				$state = array_search( $state, self::$states );
				break;

			// Signatures
			case 'signature_scan':
				if(empty($usp->settings->scanner_signature_analysis)){
					$state = array_search( $state, self::$states );
					break;
				}

				$result = self::action__scanner__scan_signatures(
					(int) Get::get( 'offset' ),
					10,
					substr( CT_USP_SITE_ROOT, 0, - 1 )
				);
				if(empty($result['error'])){
					if($result['processed'] != 10)
						$state = 'heuristic_scan';
				}
				break;

			// Heuristic
			case 'heuristic_scan':
				if(empty($usp->settings->scanner_heuristic_analysis)){
					$state = 'cure_backup';
					break;
				}

				$result = self::action__scanner__scan_heuristic(
					(int) Get::get('offset'),
					10
				);

				if(empty($result['error'])){
					if($result['processed'] != 10)
						$state = 'send_results';
				}
				break;

			// Send result
			case 'send_results':

				$result = spbc_scanner_send_results(true, $usp->data->scanner->cron);
				$end = true;

				break;
		}

		// Make next call if everything is ok
		if(!isset($end) && empty($result['error'])){
			$def_params = array(
				'plugin_name'             => 'security',
				'spbc_remote_call_token'  => md5($usp->settings->key),
				'spbc_remote_call_action' => 'scanner__controller',
				'state'                   => $state
			);
			Helper::http__request(
				CT_USP_AJAX_URI,
				array_merge($def_params, $additional_params),
				'get async'
			);
		}

		// Delete or add an error
		empty($result['error'])
			? $usp->error_delete($prev_state, 'and_save_data', 'cron_scan')
			: $usp->error_add($prev_state, $result, 'cron_scan');

		return true;
	}

	/**
	 * Clears all data about scanned files
	 *
	 * @return array
	 */
	public static function action__scanner__clear_table(){

		State::getInstance()->scan_result->count()
			? State::getInstance()->scan_result->delete()
			: null;

		return array(
			'processed' => 1,
			'success' => 1,
		);
	}

	public static function action__scanner__count_files( $path = CT_USP_SITE_ROOT ){

		$path_to_scan = realpath( $path );
		$root_path    = realpath(substr( CT_USP_SITE_ROOT, 0, -1 ) );
		$init_params  = array(
			'count'          => true,
			'file_exceptions' => '',
			'extensions'      => 'php, html, htm',
			'files_mandatory' => array(),
			'dir_exceptions'  => array()
		);
		$scanner = new Scanner($path_to_scan, $root_path, $init_params);

		return array(
			'total' => $scanner->files_count,
		);
	}

	public static function action__scanner__scan_signatures( $offset = 0, $amount = 10, $path = CT_USP_SITE_ROOT ){

		$offset = Get::get( 'offset' ) ? Get::get( 'offset' )             : $offset;
		$amount = Get::get( 'amount' ) ? Get::get( 'amount' )             : $amount;
		$path   = Get::get( 'path' )   ? realpath( Get::get( 'path' ) )   : realpath( $path );

		$usp = State::getInstance();

		$out = array(
			'found'     => 0,
			'processed' => 0,
		);

		$files_to_check = self::get_files( $offset, $amount );

		if ( $files_to_check ) {

			$scanned = 0;
			$found = 0;

			if ( ! empty( $files_to_check ) ) {

				// Initialing results

				foreach ( $files_to_check as $file ) {

					$result = Scanner::file__scan__for_signatures( CT_USP_SITE_ROOT, $file, $usp->signatures->array_values() );

					if ( empty( $result['error'] ) ) {

						if ( $result['status'] !== 'OK' ) {

							$usp->scan_result[] = array(
								'path'       => $file['path'],
								'size'       => $file['size'],
								'perms'      => $file['perms'],
								'mtime'      => $file['mtime'],
								'weak_spots' => json_encode($result['weak_spots']),
								'fast_hash'  => $file['fast_hash'],
								'full_hash'  => $file['full_hash'],
							);

							$out['found']++;

						}

					}else{
						// @todo Add exception Notice level
					}

					$out['processed']++;
				}

				$usp->scan_result->save();

			}

		}
		return $out;
	}

	public static function action__scanner__scan_heuristic( $offset = 0, $amount = 10, $path = CT_USP_SITE_ROOT ) {

		$offset = Get::get( 'offset' ) ? Get::get( 'offset' )             : $offset;
		$amount = Get::get( 'amount' ) ? Get::get( 'amount' )             : $amount;
		$path   = Get::get( 'path' )   ? realpath( Get::get( 'path' ) )   : realpath( $path );

		$usp = State::getInstance();

		$out = array(
			'found'     => 0,
			'processed' => 0,
		);

		$files_to_check = self::get_files( $offset, $amount );

		if ( $files_to_check ) {
			if ( count( $files_to_check ) ) {
				foreach ( $files_to_check as $file ) {

					$result = Scanner::file__scan__heuristic( CT_USP_SITE_ROOT, $file );

					if ( empty( $result['error'] ) ) {

						if ( $result['status'] !== 'OK' ) {

							$usp->scan_result[] = array(
								'path'       => $file['path'],
								'size'       => $file['size'],
								'perms'      => $file['perms'],
								'mtime'      => $file['mtime'],
								'weak_spots' => json_encode($result['weak_spots']),
								'fast_hash'  => $file['fast_hash'],
								'full_hash'  => $file['full_hash'],
							);

							$out['found'] ++;

						}

					}else{
						// @todo Add exception Notice
					}

					$out['processed']++;

				}

				$usp->scan_result->save();
			}
		}

		return $out;

	}

	public static function action__scanner__send_results( $total_scanned = 0 ) {

		$usp = State::getInstance();

		$total_scanned = $total_scanned ? $total_scanned : Get::get( 'total_scanned' );

		$files = $usp->scan_result->convertToArray();

		$files_count = count( $files );

		$unknown  = array();
		$modified = array();
		if($files_count){
			foreach ( $files as $file ) {
				$file['path'] = Helper::is_windows() ? str_replace('\\', '/', $file['path']) : $file['path'];
				$modified[ $file['path'] ] = array(
					$file['full_hash'],
					$file['mtime'],
					$file['size'],
					'CORE',
					'unknown',
					'UNKNOWN',
				);
			}
		}

		// API. Sending files scan result
		$result = API::method__security_mscan_logs(
			$usp->key,
			$usp->service_id,
			date( 'Y-m-d H:i:s' ),
			$files_count ? 'warning' : 'passed',
			$total_scanned,
			$modified,
			$unknown
		);

		if(empty($result['error'])){

			$usp->data->stat->scanner->last_sent        = time();
			$usp->data->stat->scanner->last_scan        = time();
			$usp->data->stat->scanner->last_scan_amount = isset($_GET['total_scanned']) ? $_GET['total_scanned'] : $total_scanned;

		}else
			Err::add('scanner_result_send', $result['error']);

		$usp->data->save();

		return $result;

	}
}