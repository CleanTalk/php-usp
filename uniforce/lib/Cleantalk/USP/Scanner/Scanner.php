<?php

namespace Cleantalk\USP\Scanner;

use Cleantalk\USP\Scanner\Helper as ScannerHelper;
use Cleantalk\USP\Uniforce\Helper as Helper;

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
			$this->files[$key]['path']  = substr(Helper::is_windows() ? str_replace('/', '\\', $val['path']) : $val['path'], $path_offset);
			$this->files[$key]['size']  = filesize($val['path']);
			$this->files[$key]['perms'] = substr(decoct(fileperms($val['path'])), 3);
			$this->files[$key]['mtime'] = filemtime($val['path']);
			$this->files[$key]['status'] = 'UNKNOWN';

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
			$this->dirs[$key]['path']  = substr(Helper::is_windows() ? str_replace('/', '\\', $val['path']) : $val['path'], $path_offset);
			$this->dirs[$key]['mtime'] = filemtime($val['path']);
			$this->dirs[$key]['perms'] = substr(decoct(fileperms($val['path'])), 2);
		}
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
				    
                    if( $signature['type'] === 'FILE' ){
                        if( $file_info['full_hash'] === $signature['body'] ){
                            $verdict['SIGNATURES'][1][] = $signature['id'];
                        }
                    }
                    
                    if( in_array( $signature['type'], array('CODE_PHP', 'CODE_JS', 'CODE_HTML' ) ) ) {
                        $file_content = file_get_contents( $root_path . $file_info['path'] );
                        $is_regexp = preg_match( '/^\/.*\/$/', $signature['body'] );
                        if(
                            ( $is_regexp   && preg_match( $signature['body'], $file_content ) ) ||
                            ( ! $is_regexp && strripos( $file_content, stripslashes( $signature['body'] ) ) !== false )
                        ){
                            $line_number = ScannerHelper::file__get_string_number_with_needle( $file_info['path'], $signature['body'], $is_regexp );
                            $verdict['SIGNATURES'][ $line_number ][] = $signature['id'];
                        }
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
	
	
}
