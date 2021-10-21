<?php

namespace Cleantalk\USP\Updater;

use Cleantalk\USP\Common\File;
use Cleantalk\USP\Common\State;
use Cleantalk\USP\Uniforce\Helper;
use ZipArchive;
use Cleantalk\USP\Uniforce\API;

/**
 * CleanTalk Updater class.
 *
 * @Version       1.1.0
 * @author        Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * @license       GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * @see           https://github.com/CleanTalk/php-antispam
 */
class Updater {
	
	private $plugin_name;
	private $version_current;
	
	private $root_path;
	private $download_path;
	private $backup_path;
	
	public function __construct( $root_path ){
		
		$this->plugin_name     = $this->getPluginName();
		$this->version_current = $this->getCurrentVersion();
		
		$this->root_path = $root_path;
		$this->download_path = $root_path . DS . 'downloads' . DS;
		$this->backup_path = $root_path . DS . 'backup';
	}
    
    public function update( $current_version, $new_version ){
        
        $this->deleteDownloads();
        
        // Download
        $path = $this->downloadArchiveByVersion( $new_version );
        if( ! empty( $path['error'] ) )
            return $path;
        
        // Extract
        $extract_result = $this->extractArchive( $path, $new_version );
        if( ! empty( $extract_result['error'] ) )
            return $extract_result;
        
        // Backup
        if( ! $this->backup() )
            return array( 'error' => 'Fail to backup previous version.' );
        
        // Delete current
        if( ! File::delete( $this->root_path, array( 'downloads', 'backup', 'data' ) ) ){
            $rollback_result = $this->rollback() ? 'success' : 'failed';
            return array( 'error' => 'Fail to delete previous version. Rollback: ' . $rollback_result );
        }
        
        // Install
        if( ! $this->install( $new_version ) ){
            $rollback_result = $this->rollback() ? 'success' : 'failed';
            return array( 'error' => 'Fail install new version. Rollback: ' . $rollback_result );
        }
        
        // Update
        $update_result = $this->runUpdateActions( $current_version, $new_version );
        if( ! empty( $update_result['error'] ) ){
            $rollback_result = $this->rollback() ? 'success' : 'failed';
            return array( 'error' => $update_result['error'] . ' Rollback: ' . $rollback_result );
        }
        
        $this->deleteBackup();
        $this->deleteDownloads();
        
        return array( 'success' => true );
    }
    
    /**
     * @return string|null
     */
    private function getCurrentVersion(){
        $version = defined( 'SPBCT_VERSION' ) ? SPBCT_VERSION : null;
        return $version
            ? $this->versionStandardization( $version )
            : null;
    }
    
    /**
     * @return mixed
     */
    private function getPluginName(){
        return defined( 'SPBCT_PLUGIN' ) ? SPBCT_PLUGIN : null;
    }
    
    /**
     * Recursive
     * Multiple HTTP requests
     * Check URLs for to find latest version. Checking response code from URL.
     *
     * @param null $version_to_check
     * @param int $version_type_to_check
     *
     * @return array|string|null
     */
    public function getLatestVersion( $version = null, $version_type_to_check = 0 ){
        
        $version = $version ? $version : $this->version_current;
        
        switch( $version_type_to_check ){
            
            case 0:
                $version_to_check = array( $version[0] + 1, 0, 0 );
                break;
            case 1:
                $version_to_check = array( $version[0], $version[1] + 1, 0 );
                break;
            case 2:
                $version_to_check = array( $version[0], $version[1], $version[2] + 1 );
                break;
            
            // Unacceptable version type. We have the latest version. Return it.
            default:
                return implode( '.', array_slice( $version, 0, 3 ) );
                break;
        }
        
        if( $this->isVersionExists( $version_to_check ) ){
            
            return $this->getLatestVersion( $version_to_check, $version_type_to_check );
        }
        
        $version_to_check[ $version_type_to_check ]--;
        if( isset( $version_to_check[ $version_type_to_check + 1 ] ) &&
            $this->version_current[ $version_type_to_check ] === $version_to_check[ $version_type_to_check ]
        ){
            $version_to_check[ $version_type_to_check + 1 ] = $this->version_current[ $version_type_to_check + 1 ];
        }
        
        return $this->getLatestVersion( $version_to_check, $version_type_to_check + 1 );
        
    }
	
	private function isVersionExists( $version_to_check ){
	       
        return Helper::http__request__get_response_code( $this->getURLToCheck( $version_to_check ) ) == 200;
    }
	
	/**
	 * Assemble URL to check UniForce version archive
	 *
	 * @param $version
	 *
	 * @return string
	 */
	private function getURLToCheck( $version ){
		
		$version = is_array( $version )
			? implode( '.', $version )
			: $version;
		
		switch( $this->plugin_name ){
			case 'uniforce':
				return 'https://github.com/CleanTalk/php-usp/releases/tag/' . $version;
				break;
			case 'uni':
				break;
		}
	}
	
	private function getDownloadURL( $version ){
		
		$version = is_array( $version )
			? implode( '.', $version )
			: $version;
		
		switch( $this->plugin_name ){
			case 'uniforce':
				return 'https://github.com/CleanTalk/php-usp/releases/download/' . $version . '/UniForce-' . $version . '.zip';
				break;
			case 'uni':
				break;
		}
	}
	
	/**
	 * Split version to major, minor, fix parts.
	 * Set it to 0 if not found
	 *
	 * @param $version
	 *
	 * @return string
	 */
	private function versionStandardization( $version ){
		
		$version = $version === 'dev' ? '1.0.0' : $version;
		$version = explode('.', $version);
		$version = !empty($version) ? $version : array();
		
		// Version
		$version[0] = !empty($version[0]) ? (int)$version[0] : 0; // Major
		$version[1] = !empty($version[1]) ? (int)$version[1] : 0; // Minor
		$version[2] = !empty($version[2]) ? (int)$version[2] : 0; // Fix
		
		return $version;
	}
	
	private function downloadArchiveByVersion( $version ){
		$url = $this->getDownloadURL( $version );
		return Helper::http__download_remote_file( $url, $this->download_path );
	}
	
	private function extractArchive( $path, $version ){
		
		$path_to_extract_in = $this->download_path . $version . DS;
		
		if( ! is_dir( $path_to_extract_in ) )
			mkdir( $path_to_extract_in );
		
		$zip = new ZipArchive();
		
		if( ! $zip->open( $path ) )
			return array( 'error' => 'Installation: Unable to open archive.' );
		
		if( ! $zip->extractTo( $path_to_extract_in ) )
			return array( 'error' => 'Installation: Fail to extract archive.' );
		
		$zip->close();
		
		return $path_to_extract_in;
	}
	
	/**
	 * Runs update scripts for each version
	 *
	 * @param $current_version
	 * @param $new_version
	 *
	 * @return array|bool
	 */
	private function runUpdateActions( $current_version, $new_version ){
	    
		$current_version = $this->versionStandardization( $current_version );
		$new_version     = $this->versionStandardization( $new_version );
		
		$current_version_str = implode( '.', $current_version );
		$new_version_str     = implode( '.', $new_version );
		
		for( $ver_major = $current_version[0]; $ver_major <= $new_version[0]; $ver_major ++ ){
			for( $ver_minor = 0; $ver_minor <= 100; $ver_minor ++ ){
				for( $ver_fix = 0; $ver_fix <= 10; $ver_fix ++ ){
					
					if( version_compare( "{$ver_major}.{$ver_minor}.{$ver_fix}", $current_version_str, '<=' ) )
						continue;
                    
                    if( method_exists( 'Cleantalk\USP\Updater\UpdaterScripts', "update_to_{$ver_major}_{$ver_minor}_{$ver_fix}" ) ){
						$result = call_user_func( "Cleantalk\USP\Updater\UpdaterScripts::update_to_{$ver_major}_{$ver_minor}_{$ver_fix}" );
						if( ! empty( $result['error'] ) ){
							return $result;
						}
					}
					
					if( version_compare( "{$ver_major}.{$ver_minor}.{$ver_fix}", $new_version_str, '>=' ) )
						break( 2 );
					
				}
			}
		}
		
		return true;
	}
	
	private function install( $new_version ){
		return File::copy(
			$this->download_path . $new_version . DS . 'uniforce',
			$this->root_path,
			array( 'downloads', 'backup', 'data' )
		);
	}
	
	private function backup(){
		return File::copy( $this->root_path, $this->backup_path, array( 'downloads', 'backup' ) );
	}
	
	private function deleteBackup(){
		return File::delete( $this->backup_path );
	}
	
	private function deleteDownloads(){
		return File::delete( $this->download_path );
	}
	
	private function rollback(){
		if( File::copy( $this->backup_path, $this->root_path, array( 'downloads', 'backup' ) ) ){
			$this->deleteBackup();
			return true;
		}else
			return false;
	}
}