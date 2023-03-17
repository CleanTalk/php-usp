<?php

namespace Cleantalk\USP\Scanner;

class Helper {
	
	const signatures_version_file_url = 'https://cleantalk-security.s3.amazonaws.com/security_signatures/version.txt';
	const signatures_file_url         = 'https://cleantalk-security.s3.amazonaws.com/security_signatures/security_signatures_v2.csv.gz';
	
	public static function get_files( $offset = 0, $amount = 1500, $path = CT_USP_SITE_ROOT ) {
		
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
	
	/**
	 * Static.
	 * Gets and parses signatures from the Cloud
	 *
	 * @param int $last_signature_update Last update signatures timestamp
	 *
	 * @return array|bool|int|mixed|string|string[]
	 */
	static public function get_hashes__signature( $last_signature_update = 0 )
	{
		if( \Cleantalk\USP\Uniforce\Helper::http__request__get_response_code(self::signatures_version_file_url) == 200) {
			
			$latest_signatures = \Cleantalk\USP\Uniforce\Helper::http__request__get_content(self::signatures_version_file_url);
			
			if(strtotime($latest_signatures)){
				
				if(strtotime($last_signature_update) < strtotime($latest_signatures)){
					
					if(\Cleantalk\USP\Uniforce\Helper::http__request__get_response_code(self::signatures_file_url) == 200) {
						
						$gz_data = \Cleantalk\USP\Uniforce\Helper::http__request__get_content(self::signatures_file_url);
						
						if(empty($gz_data['error'])){
							
							if(function_exists('gzdecode')){
								
								$data = gzdecode($gz_data);
								
								if($data !== false){
                                    
                                    // Set map for file
                                    $map = strpos( self::signatures_file_url, '_mapped' ) !== false
                                        ? \Cleantalk\USP\Uniforce\Helper::buffer__csv__get_map( $data ) // Map from file
                                        : array( 'id', 'name', 'body', 'type', 'attack_type', 'submitted', 'cci' ); // Default map
                                    
									$out = array();
                                    while( $data ){
                                        $out[] = \Cleantalk\USP\Uniforce\Helper::buffer__csv__pop_line_to_array( $data, $map, true );
                                    }
                                    
									return $out;
								}else
									return array('error' => 'COULDNT_UNPACK');
							}else
								return array('error' => 'Function gzdecode not exists. Please update your PHP to version 5.4');
						}else
							return $gz_data;
					}else
						return array('error' =>'NO_FILE');
				}else
					return array('error' =>'UP_TO_DATE');
			}else
				return array('error' =>'WRONG_VERSION_FILE');
		}else
			return array('error' =>'NO_VERSION_FILE');
	}
	
	/**
	 * Getting real hashs of approved files
	 *
	 * @param string $cms CMS name
	 * @param string $type Type - approved/rejected
	 * @param $version
	 *
	 * @return array Array with all files hashes or Error Array
	 */
	static public function get_hashes__approved_files($cms, $type, $version) {

        $file_path = 'https://cleantalk-security.s3.amazonaws.com/extensions_checksums/' . $cms . '/' . $type . '/' . $version . '.csv.gz';
		
		if( \Cleantalk\USP\Uniforce\Helper::http__request($file_path, array(), 'get_code') == 200) {
			
			$gz_data = \Cleantalk\USP\Uniforce\Helper::http__request__get_content($file_path);
			
			if(empty($gz_data['error'])) {
				
				if ( function_exists( 'gzdecode' ) ) {
					
					$data = gzdecode( $gz_data );
					
					if ( $data !== false ) {
						
						$lines = \Cleantalk\USP\Uniforce\Helper::buffer__parse__csv($data);
						
						if( count( $lines ) > 0 ) {
							
							$result = array();
							
							foreach( $lines as $hash_info ) {
								
								if(empty($hash_info)) continue;
								
								preg_match('/.*\.(\S*)$/', $hash_info[0], $matches);
								$ext      = isset($matches[1]) ? $matches[1] : '';
								if(!in_array($ext, array('php','html'))) continue;
								
								$result[] = $hash_info;
								
							}
							
							if(count($result)){
								return $result;
							}else
								return array('error' =>'BAD_HASHES_FILE');
						} else {
							return array('error' => 'Empty hashes file');
						}
					} else {
						return array( 'error' => 'COULDNT_UNPACK' );
					}
				} else {
					return array( 'error' => 'Function gzdecode not exists. Please update your PHP to version 5.4' );
				}
			}
		}
    return array('error' =>'REMOTE_FILE_NOT_FOUND');
	}
	
	/**
	 * Scanning file
	 *
	 * @param string $root_path Path to CMS's root folder
	 * @param array $file_info Array with files data (path, real_full_hash, source_type, source, version), other is optional
	 * @return array|false
	 */
	static public function file__get__differences($root_path, $file_info)
	{
		if(file_exists($root_path.$file_info['path'])){
			
			if(is_readable($root_path.$file_info['path'])){
				
				/** @todo Add proper comparing mechanism
				// Comparing with original file (if it's exists) and getting difference
				if(!empty($file_info['real_full_hash']) && $file_info['real_full_hash'] !== $file_info['full_hash']){
				
				$file_original = \Cleantalk\USP\Scanner\Helper::file__get_original($file_info, $cms);
				
				if(!empty($file_original['error'])){
				
				$file = file($root_path.$file_info['path']);
				
				// Comparing files strings
				for($row = 0; !empty($file[$row]); $row++){
				if(isset($file[$row]) || isset($file_original[$row])){
				if(!isset($file[$row]))          $file[$row] = '';
				if(!isset($file_original[$row])) $file_original[$row] = '';
				if(strcmp(trim($file[$row]), trim($file_original[$row])) != 0){
				$difference[] = $row+1;
				}
				}
				}
				}
				}
				 */
				
			}else
				$output = array('error' => 'NOT_READABLE');
		}else
			$output = array('error' => 'NOT_EXISTS');
		
		return !empty($output) ? $output : false;
		
	}
	
	/**
	 * Get original file's content
	 *
	 * @param array $file_info Array with files data (path, real_full_hash, source_type, source), other is optional
	 *
	 * @return array File splitted by EOL
	 */
	static public function file__get_original($file_info)
	{
		$file_info['path'] = str_replace('\\', '/', $file_info['path']); // Replacing win slashes to Orthodox slashes =) in case of Windows
		
		switch( $file_info['source_type'] ){
			case 'PLUGIN':
				$file_info['path'] = preg_replace('@/wp-content/plugins/.*?/(.*)$@i', '$1',$file_info['path']);
				$url_path = 'https://plugins.svn.wordpress.org/'.$file_info['source'].'/tags/'.$file_info['version'].'/'.$file_info['path'];
				break;
			case 'THEME':
				$file_info['path'] = preg_replace('@/wp-content/themes/.*?/(.*)$@i', '$1',$file_info['path']);
				$url_path = 'https://themes.svn.wordpress.org/'.$file_info['source'].'/'.$file_info['version'].'/'.$file_info['path'];
				break;
			default:
				$url_path = 'https://cleantalk-security.s3.amazonaws.com/cms_sources/'.$file_info['source'].'/'.$file_info['version'].$file_info['path'];
				break;
		}
		
		if( \Cleantalk\USP\Uniforce\Helper::http__request__get_response_code($url_path) == 200 ){
			$out = \Cleantalk\USP\Uniforce\Helper::http__request__get_content($url_path);
		}else
			$out = array('error' => 'Couldn\'t get original file');
		
		return $out;
	}
	
	/**
	 * Checks if the current system is Windows or not
	 *
	 * @return boolean
	 */
	static function is_windows(){
		return strpos(strtolower(php_uname('s')), 'windows') !== false ? true : false;
	}
    
    /**
     * Returns number of string with a given char position
     *
     * @param string $file_path   String to search in
     * @param int $signature_body Character position
     * @param bool $is_regexp     Flag. Is signature is regular expression?
     *
     * @return int String number
     */
	public static function file__get_string_number_with_needle($file_path, $signature_body, $is_regexp = false){
        $file = file( $file_path );
        $out = 0;
        
        foreach( $file as $number => $line ){
            if(
                ( $is_regexp   && preg_match( $signature_body, $line ) ) ||
                ( ! $is_regexp && strripos( $line, stripslashes( $signature_body ) ) !== false )
            ){
                $out = $number;
            }
        }
        
        return $out;
	}
}