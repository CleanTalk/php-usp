<?php

namespace Cleantalk\Scanner;

use Cleantalk\Uniforce\Helper;

class Scanner
{
	public $path         = ''; // Main path
	public $path_lenght  = 0;
	
	/** @var array Description Extensions to check */
	public $ext             = array();
	/** @var array Exception for extensions */
	public $ext_except      = array();
	
	/** @var array Exception for files paths */
	public $files_except    = array();
	
	/** @var array Exception for directories */
	public $dirs_except     = array(); 
	
	/** @var array Mandatory check for files paths */
	public $files_mandatory = array(); 
	
	/** @var array Mandatory check for directories */
	public $dirs_mandatory  = array();
	
	public $files = array();
	public $dirs  = array();
	
	public $files_count = 0;
	public $dirs_count  = 0;
	
	private $file_start = 0;
	private $file_curr  = 0;
	private $file_max   = 1000000;
	
	function __construct($path, $rootpath, $params = array('count' => true))
	{
		// INITIALING PARAMS
		
		// Main directory
		$path = realpath($path);
		if(!is_dir($path))     die("Scan '$path' isn't directory");
		if(!is_dir($rootpath)) die("Root '$rootpath' isn't directory");
		$this->path_lenght = strlen($rootpath);
		
		// Processing filters		
		$this->ext          = !empty($params['extensions'])            ? $this->filter_params($params['extensions'])             : array();
		$this->ext_except   = !empty($params['extensions_exceptions']) ? $this->filter_params($params['extensions_exceptions'])  : array();
		$this->files_except = !empty($params['file_exceptions'])       ? $this->filter_params($params['file_exceptions'])        : array();
		$this->dirs_except  = !empty($params['dir_exceptions'])        ? $this->filter_params($params['dir_exceptions'])         : array();
		
		// Mandatory files and dirs
		$this->files_mandatory = !empty($params['files_mandatory']) ? $this->filter_params($params['files_mandatory']) : array();
		$this->dirs_mandatory  = !empty($params['dirs_mandatory'])  ? $this->filter_params($params['dirs_mandatory'])  : array();
		
		// Initialing counters
		$this->file_start =   isset($params['offset']) ? $params['offset'] : 0;
		$this->file_max   =   isset($params['offset']) && isset($params['amount']) ? $params['offset'] + $params['amount'] : 1000000;
		
		// DO STUFF
		
		// Only count files
		if(!empty($params['count'])){
			$this->count_files__mandatory($this->files_mandatory);
			$this->count_files_in_dir($path);
			return;
		}
		// Getting files and dirs considering filters
		$this->get_files__mandatory($this->files_mandatory);
		$this->get_file_structure($path);
		// Files
		$this->files_count = count($this->files);
		$this->file__details($this->files, $this->path_lenght);
		
		// Directories
		// $this->dirs[]['path'] = $path;
		// $this->dirs_count = count($this->dirs);
		// $this->dir__details($this->dirs, $this->path_lenght);

		
	}
	
	/**
	 * * Function coverting icoming parametrs to array even if it is a string like 'some, example, string'
	 *
	 * @param $filter
	 *
	 * @return array|null
	 */
	public function filter_params($filter)
	{
		if(!empty($filter)){
			if(!is_array($filter)){
				if(strlen($filter)){
					$filter = explode(',', $filter);
				}
			}
			foreach($filter as $key => &$val){
				$val = trim($val);
			}
			return $filter;
		}else{
			return null;
		}
	}
	
	/**
	 * Counts given mandatory files
	 * 
	 * @param array $files Files to count
	 */
	public function count_files__mandatory($files){
		foreach($files as $file){
			if(is_file($file))
				$this->files_count++;
		}
	}
	
	/**
	 * Count files in directory
	 * 
	 * @param string $main_path Path to count files in
	 */
	public function count_files_in_dir($main_path)
	{		
		$paths = array_merge(glob($main_path.'/.*', GLOB_NOSORT), glob($main_path.'/*', GLOB_NOSORT));
		
		foreach($paths as $path){
			
			// Excluding $path/. and $path/.. directories from the set
			if(preg_match('/\.$/', $path))
				continue;
			
			if(is_file($path)){
				
				// Extensions filter
				if(!empty($this->ext_except)){
					$tmp = explode('.', $path);
					if(in_array($tmp[count($tmp)-1], $this->ext_except))
						continue;
				}
				// Extensions filter
				if(!empty($this->ext)){
					$tmp = explode('.', $path);
					if(!in_array($tmp[count($tmp)-1], $this->ext))
						continue;
				}
				// Filenames exception filter
				if(!empty($this->files_except)){
					if(in_array(basename($path), $this->files_except))
						continue;
				}
				
				// Dirnames filter
				foreach($this->dirs_except as $dir_except){
					if(preg_match('/'.$dir_except.'/', $path)){
						continue(2);
					}
				}
				
				$this->files_count++;
				
			}elseif(is_dir($path)){
				
				// Dirnames filter
				foreach($this->dirs_except as $dir_except){
					if(preg_match('/'.$dir_except.'/', $path)){
						continue(2);
					}
				}
				$this->count_files_in_dir($path);
			}
		}
	}
	
	/**
	 * Getting mandatory files
	 * 
	 * @param array $files Files to get
	 */
	public function get_files__mandatory($files){
		foreach($files as $file){
			if(is_file($file)){
				$this->files[]['path'] = $file;
				$this->file_curr++;
			}
		}
	}
	
	/**
	 * Get all files from directory
	 * 
	 * @param string $main_path Path to get files from
	 * @return void
	 */
	public function get_file_structure($main_path)
	{
		$paths = array_merge(glob($main_path.'/.*', GLOB_NOSORT), glob($main_path.'/*', GLOB_NOSORT));
		
		foreach($paths as $path){
			
			// Excluding $path/. and $path/.. directories from the set
			if(preg_match('/\.$/', $path))
				continue;
			
			// Return if file limit is reached
			if($this->file_curr >= $this->file_max)
				return;
			
			if(is_file($path)){
				
				// Extensions filter
				if(!empty($this->ext)){
					$tmp = explode('.', $path);
					if(!in_array($tmp[count($tmp)-1], $this->ext))
						continue;
				}
				
				// Extensions exception filter
				if(!empty($this->ext_except)){
					$tmp = explode('.', $path);
					if(in_array($tmp[count($tmp)-1], $this->ext_except))
						continue;
				}
				
				// Filenames exception filter
				if(!empty($this->files_except)){
					if(in_array(basename($path), $this->files_except))
						continue;
				}
				
				// Dirnames filter
				foreach($this->dirs_except as $dir_except){
					if(preg_match('/'.$dir_except.'/', $path)){
						continue(2);
					}
				}
				
				$this->file_curr++;
				
				// Skip if start is not reached
				if($this->file_curr-1 < $this->file_start)
					continue;
				
				$this->files[]['path'] = $path;
				
			}elseif(is_dir($path)){
				
				// Dirnames filter
				foreach($this->dirs_except as $dir_except){
					if(preg_match('/'.$dir_except.'/', $path))
						continue(2);
				}
				
				$this->get_file_structure($path);
				if($this->file_curr > $this->file_start)
					$this->dirs[]['path'] = $path;
				
			}elseif(is_link($path)){
				error_log('LINK FOUND: ' . $path);
			}
		}
	}

	static public function get_hashes__signature($last_signature_update)
	{
		$version_file_url = 'https://s3-us-west-2.amazonaws.com/cleantalk-security/security_signatures/version.txt';

		if(Helper::http__request__get_response_code($version_file_url) == 200) {

			$latest_signatures = Helper::http__request__get_content($version_file_url);

			if(strtotime($latest_signatures)){

				if(strtotime($last_signature_update) < strtotime($latest_signatures)){

					// _v2 since 2.31 version
					$file_url = 'https://s3-us-west-2.amazonaws.com/cleantalk-security/security_signatures/security_signatures_v2.csv.gz';

					if(Helper::http__request__get_response_code($file_url) == 200) {

						$gz_data = Helper::http__request__get_content($file_url);

						if(empty($gz_data['error'])){

							if(function_exists('gzdecode')){

								$data = gzdecode($gz_data);

								if($data !== false){

									$lines = Helper::buffer__parse__csv($data);

									$out = array();
									foreach($lines as $line){
										$out[] = array(
											'id' => !isset($line[0]) ? '' : $line[0],
											'name' => !isset($line[1]) ? '' : $line[1],
											'body' => !isset($line[2]) ? '' : stripcslashes($line[2]),
											'type' => !isset($line[3]) ? '' : $line[3],
											'attack_type' => !isset($line[4]) ? '' : $line[4],
											'submitted' => !isset($line[5]) ? '' : $line[5],
											'cci' => !isset($line[6]) ? '' : stripcslashes($line[6]),
										);
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
	 * Getting file details like last modified time, size, permissions
	 *  
	 * @param array $file_list Array of abolute paths to files
	 * @param int $path_offset Length of CMS root path
	 */
	public function file__details($file_list, $path_offset)
	{

		foreach($file_list as $key => $val){
			// Cutting file's path, leave path from CMS ROOT to file
			$this->files[$key]['path']  = substr(self::is_windows() ? str_replace('/', '\\', $val['path']) : $val['path'], $path_offset);
			$this->files[$key]['mtime'] = filemtime($val['path']);
			$this->files[$key]['perms'] = substr(decoct(fileperms($val['path'])), 3);
			$this->files[$key]['size']  = filesize($val['path']);

			// Fast hash
			$this->files[$key]['fast_hash']  = md5($this->files[$key]['path']);
			
			// Full hash
			$this->files[$key]['full_hash'] = is_readable($val['path'])
				? md5_file($val['path'])
				: 'unknown';

		}

	}

	/**
	 * Getting dir details
	 * 
	 * @param array $dir_list Array of abolute paths to directories
	 * @param int $path_offset Length of CMS root path
	 */
	public function dir__details($dir_list, $path_offset)
	{
		foreach($dir_list as $key => $val){
			$this->dirs[$key]['path']  = substr(self::is_windows() ? str_replace('/', '\\', $val['path']) : $val['path'], $path_offset);
			$this->dirs[$key]['mtime'] = filemtime($val['path']);
			$this->dirs[$key]['perms'] = substr(decoct(fileperms($val['path'])), 2);
		}
	}
	
	/**
	 * Scanning file
	 * 
	 * @param string $root_path Path to CMS's root folder
	 * @param array $file_info Array with files data (path, real_full_hash, source_type, source, version), other is optional
	 * @return array|false
	 */
	static public function file__scan__differences($root_path, $file_info)
	{		
		if(file_exists($root_path.$file_info['path'])){
			
			if(is_readable($root_path.$file_info['path'])){
				
				/** @todo Add proper comparing mechanism
				// Comparing with original file (if it's exists) and getting difference
				if(!empty($file_info['real_full_hash']) && $file_info['real_full_hash'] !== $file_info['full_hash']){
					
					$file_original = self::file__get_original($file_info, $cms);
					
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
	 * Scan file thru malware sinatures
	 * 
	 * @param string $root_path Path to CMS's root folder
	 * @param array $file_info Array with files data (path, real_full_hash, source_type, source, version), other is optional
	 * @param array $signatures Set of signatures
	 * 
	 * @return array|false False or Array of found bad sigantures
	 */
	static public function file__scan__for_signatures($root_path, $file_info, $signatures)
	{
		if(file_exists($root_path.$file_info['path'])){
			
			if(is_readable($root_path.$file_info['path'])){
				
				$verdict = array();

				foreach ((array)$signatures as $signature){
					switch ($signature['type']) {
						
						case 'FILE':
							if($file_info['full_hash'] === $signature['body']){
								/** @todo Add new type FILE */
								$verdict['SIGNATURES'][1][] = $signature['id'];
							}
							break;
						
						case 'CODE_PHP':
							$file_content = file_get_contents($root_path.$file_info['path']);
							if(strripos($file_content, $signature['body']) !== false){
								$string_number = self::file__get_string_number_with_needle($file_content, strripos($file_content, $signature['body']));
								/** @todo Add new type CODE_PHP */
								$verdict['SIGNATURES'][$string_number][] = $signature['id'];
							}
							break;
					}
				}
				
				$file_info['weak_spots'] = !empty($file_info['weak_spots']) ? json_decode($file_info['weak_spots'], true) : array();
				$verdict = Helper::array_merge__save_numeric_keys__recursive($file_info['weak_spots'], $verdict);
				
				// Processing results
				if(!empty($verdict)){
					$output['weak_spots'] = $verdict;
					$output['severity']   = 'CRITICAL';
					$output['status']     = 'INFECTED';
				}else{
					$output['weak_spots'] = null;
					$output['severity']   = null;
					$output['status']     = 'OK';
				}
				
			}else
				$output = array('error' => 'NOT_READABLE');
		}else
			$output = array('error' => 'NOT_EXISTS');
		
		return $output;
	}
	
	/**
	 * Scan file thru heuristic
	 * 
	 * @param string $root_path Path to CMS's root folder
	 * @param array $file_info Array with files data (path, real_full_hash, source_type, source, version), other is optional
	 * 
	 * @return array|false False or Array of found bad constructs sorted by severity
	 */
	static public function file__scan__heuristic($root_path, $file_info)
	{
		if(file_exists($root_path.$file_info['path'])){
			
			if(is_readable($root_path.$file_info['path'])){
				
				
				$scanner = new ScannerH( $root_path . $file_info['path']);
				if ( !empty( $scanner -> errors ) )
					return $scanner -> errors;
				$scanner -> process_file();
				
				$file_info['weak_spots'] = !empty($file_info['weak_spots']) ? json_decode($file_info['weak_spots'], true) : array();
								
				$verdict = Helper::array_merge__save_numeric_keys__recursive($file_info['weak_spots'], $scanner->verdict);
				
				// Processing results
				if(!empty($verdict)){
					$output['weak_spots'] = $verdict;
					$output['severity']   = array_key_exists('CRITICAL', $verdict) ? 'CRITICAL' : (array_key_exists('DANGER', $verdict) ? 'DANGER' : 'SUSPICIOUS');
					$output['status']     = 'INFECTED';
				}else{
					$output['weak_spots'] = null;
					$output['severity']   = null;
					$output['status']     = 'OK';
				}
				
			}else
				$output = array('error' => 'NOT_READABLE');
		}else
			$output = array('error' => 'NOT_EXISTS');
		
		return $output;
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
				$url_path = 'http://cleantalk-security.s3.amazonaws.com/cms_sources/'.$file_info['source'].'/'.$file_info['version'].$file_info['path'];
				break;
		}
		
		if( Helper::http__request__get_response_code($url_path) == 200 ){
			$user_agent = ini_get('user_agent');
			ini_set('user_agent', 'Secuirty Plugin by CleanTalk');
			$out = Helper::http__request__get_content($url_path);
			ini_set('user_agent', $user_agent);
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
	 * @param string $haystack String to search in
	 * @param int    $position Character position
	 *
	 * @return int String nubmer
	 */
	static function file__get_string_number_with_needle($haystack, $position){
		return count(explode(PHP_EOL, substr($haystack, 0, $position)));
	}
}
