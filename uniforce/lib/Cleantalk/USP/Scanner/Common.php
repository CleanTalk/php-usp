<?php


namespace Cleantalk\USP\Scanner;

use Cleantalk\USP\DB;
use Cleantalk\USP\Scanner\Helper as ScannerHelper;

class Common {
	
	private $db;
	private $table__signatures;
	
	public function __construct( $db_config = array() ) {
		
		if( $db_config ){
			list( $username, $password, $options ) = $db_config;
			$this->db = DB::getInstance( $username, $password, $options );
		}else{
			$this->db = DB::getInstance();
		}
		
	}
	
	/**
	 * Counting files
	 *
	 * @param $root_path
	 * @param null $path
	 * @param array $init_params
	 *
	 * @return int
	 */
	static public function count_files( $root_path, $path = null, $init_params = array() ){
		
		$path = $path ?: $root_path;
		
		$path_to_scan = realpath( $path );
		$root_path    = realpath( substr( $root_path, 0, - 1 ) );
		$init_params  = $init_params ?: array(
			'count'           => true,
			'file_exceptions' => '',
			'extensions'      => 'php, html, htm',
			'files_mandatory' => array(),
			'dir_exceptions'  => array()
		);
		
		$scanner = new Scanner( $path_to_scan, $root_path, $init_params );
		
		return $scanner->files_count;
	}
	
	/**
	 *
	 *
	 * @param int    $offset
	 * @param int    $amount
	 * @param string $root_path
	 * @param string $path
	 * @param array  $signatures
	 *
	 * @return int[]
	 */
	public function signatures_scan( $offset, $amount, $root_path, $path = null, $signatures = array() ){
		
		$out = array(
			'found'     => 0,
			'processed' => 0,
		);
		
		$path       = $path ?: $root_path;
		$signatures = $signatures ?: $this->get_signatures();
		
		$files_to_check = ScannerHelper::get_files( $offset, $amount );
		
		if ( $files_to_check ) {
			
			$scanned = 0;
			$found = 0;
			
			if ( ! empty( $files_to_check ) ) {
				
				// Initialing results
				
				foreach ( $files_to_check as $file ) {
					
					$result = Scanner::file__scan__for_signatures( $root_path, $file, $signatures );
					
					if ( empty( $result['error'] ) ) {
						
						if ( $result['status'] !== 'OK' ) {
							
							$scan_result[] = array(
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
				
				$scan_result;
				
			}
			
		}
		return $out;
	}
	
	/**
	 * @return array|object|null
	 */
	private function get_signatures() {
		
		return $this->db->fetch_all("SELECT * FROM {$this->table__signatures};");
		
	}
	
	private function save_result( $result ){
		
		
		
		$this->db->prepare()->query();
		
	}
}