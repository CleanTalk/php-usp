<?php

namespace Cleantalk\USP\Uniforce\Firewall;

use Cleantalk\USP\Common\Err;
use Cleantalk\USP\Common\State;
use Cleantalk\USP\File\FileStorage;
use Cleantalk\USP\Uniforce\API;
use Cleantalk\USP\Uniforce\Helper;
use Cleantalk\USP\Variables\Get;
use Cleantalk\USP\Variables\Server;
use Cleantalk\USP\File\FileDB;

class FW extends \Cleantalk\USP\Uniforce\Firewall\FirewallModule {
    
    // Domains which are skipping exclusions
    private static $test_domains = array( 'lc', 'loc', 'lh', 'test' );
    
    public $module_name = 'FW';
	
	/**
	 * @var bool
	 */
	protected $test;
	
	// Additional params
	protected $api_key = '';
	
	protected $real_ip;
	
	/**
	 * FireWall_module constructor.
	 * Use this method to prepare any data for the module working.
	 *
	 * @param array $params
	 */
	public function __construct( $params = array() ){
		
		parent::__construct( $params );
		
	}
	
	/**
	 * @param $ips
	 */
	public function ip__append_additional( &$ips ){
		
		$this->real_ip = isset( $ips['real'] ) ? $ips['real'] : null;
		
		if( Get::get('spbct_test') == md5( $this->api_key ) ){
			$ip_type = Helper::ip__validate( Get::get('spbct_test_ip') );
			$test_ip = $ip_type == 'v6' ? Helper::ip__v6_normalize( Get::get('spbct_test_ip') ) : Get::get('spbct_test_ip');
			if( $ip_type ){
				$ips['test']   = $test_ip;
				$this->test_ip = $test_ip;
				$this->test    = true;
			}
		}
	}
	
	/**
	 * Check every IP using FireWall data table.
	 *
	 * @return array
	 */
	public function check() {
		
		$results = array();
		
		foreach( $this->ip_array as $ip_origin => $current_ip ) {
			
			$ip_type = Helper::ip__validate($current_ip);
			
			// IPv4 query
			if( $ip_type && $ip_type === 'v4' ){
			 
				$current_ip_v4 = sprintf( "%u", ip2long( $current_ip ) );
				// Creating IPs to search
				for ( $needles = array(), $m = 6; $m <= 32; $m ++ ) {
					$mask      = str_repeat( '1', $m );
					$mask      = str_pad( $mask, 32, '0' );
					$needles[] = sprintf( "%u", bindec( $mask & base_convert( $current_ip_v4, 10, 2 ) ) );
				}
				$needles = array_unique( $needles );
				
				$db = new FileDB( 'fw_nets' );
				$db_results = $db
					->setWhere( array( 'network' => $needles, ) )
					->setLimit( 0, 20 )
					->select( 'network', 'mask', 'status', 'is_personal' );
     
				for( $i = 0; isset( $db_results[ $i ] ); $i ++ ){
                    if( ! Helper::ip__mask_match(
                        $current_ip,
                        long2ip( $db_results[ $i ]['network'] ). '/' . Helper::ip__mask__long_to_number( $db_results[ $i ]['mask'] )
                    ) ){
                        unset( $db_results[ $i ] );
                    }
				}
			}
			
			// In base
			if( ! empty( $db_results ) ) {
				
				foreach( $db_results as $entry ) {
					
					$result_entry = array(
						'module' => $this->module_name,
						'ip' => $current_ip,
						'is_personal' => (int)$entry['is_personal'],
//						'country_code' => $entry['country_code'],
						'network' => $entry['network'],
						'mask' => $entry['mask'],
					);
					
					switch ( $entry['status'] ) {
						case 2:	 $result_entry = array_merge( $result_entry, array('status' => 'PASS_BY_TRUSTED_NETWORK', ) ); break;
						case 1:	 $result_entry = array_merge( $result_entry, array('status' => 'PASS_BY_WHITELIST', ) );       break;
						case 0:	 $result_entry = array_merge( $result_entry, array('status' => 'DENY', ) );                    break;
						case -1: $result_entry = array_merge( $result_entry, array('status' => 'DENY_BY_NETWORK', ) );         break;
						case -2: $result_entry = array_merge( $result_entry, array('status' => 'DENY_BY_DOS', ) );             break;
						case -3: $result_entry = array_merge( $result_entry, array('status' => 'DENY_BY_SEC_FW', ) );          break;
						case -4: $result_entry = array_merge( $result_entry, array('status' => 'DENY_BY_SPAM_FW', ) );         break;
					}
					
					$results[] = $result_entry;
				}
				
				// Not in base
			}else {
				
				$results[] = array(
					'module' => $this->module_name,
					'ip' => $current_ip,
					'is_personal' => false,
//					'country_code' => null,
					'network' => null,
					'mask' => null,
					'status' => 'PASS',
				);
				
			}
			
		}
		
		return $results;
		
	}
	
	/**
	 * Sends and wipe SFW log
	 *
	 * @param string $ct_key API key
	 *
	 * @return array|bool array('error' => STRING)
	 */
	public static function send_log( $ct_key ){
		
		$log_dir_path = CT_USP_ROOT . 'data/fw_logs';
		
		if( ! is_dir( $log_dir_path ) )
			return array( 'rows' => 0 );
		
		$log_files = array_diff( scandir( $log_dir_path ), array( '.', '..', 'index.php' ) );
		
		if( ! empty( $log_files ) ){
			
			//Compile logs
			$data = array();
		
			foreach ( $log_files as $log_file ){
				
				$log = file_get_contents( $log_dir_path . DS . $log_file );
				$log = str_getcsv( $log );
				
				//Compile log
				$to_data = array(
					'datetime'         => isset( $log[2] ) ? gmdate('Y-m-d H:i:s', $log[2]) : 0,
					'datetime_gmt'     => isset( $log[2] ) ? $log[2] : 0,
					'page_url'         => isset( $log[6] ) ? $log[6] : 0,
					'visitor_ip'       => isset( $log[1] ) ? ( Helper::ip__validate( $log[1] ) == 'v4' ? (int) sprintf( '%u', ip2long( $log[1] ) ) : (string) $log[1] ) : 0,
					'http_user_agent'  => isset( $log[7] ) ? $log[7] : 0,
					'request_method'   => isset( $log[8] ) ? $log[8] : 0,
					'x_forwarded_for'  => isset( $log[9] ) ? $log[9] : 0,
					'is_personal'      => isset( $log[10] ) ? $log[10] : null,
					'matched_networks' => isset( $log[11] ) ? $log[11] . '/' . $log[12] : null,
					'hits'             => isset( $log[5] ) ? $log[5] : 0,
				);
				
				// Legacy
				switch( $log[3] ){
					case 'PASS_BY_TRUSTED_NETWORK': $to_data['status_efw'] = 3;  break;
					case 'PASS_BY_WHITELIST':       $to_data['status_efw'] = 2;  break;
					case 'PASS':                    $to_data['status_efw'] = 1;  break;
					case 'DENY':                    $to_data['status_efw'] = 0;  break;
					case 'DENY_BY_NETWORK':         $to_data['status_efw'] = -1; break;
					case 'DENY_BY_DOS':             $to_data['status_efw'] = -2; break;
					case 'DENY_BY_WAF_XSS':         $to_data['status_efw'] = -3; $to_data['waf_comment'] = $log[4]; break;
					case 'DENY_BY_WAF_SQL':         $to_data['status_efw'] = -4; $to_data['waf_comment'] = $log[4]; break;
					case 'DENY_BY_WAF_FILE':        $to_data['status_efw'] = -5; $to_data['waf_comment'] = $log[4]; break;
					case 'DENY_BY_WAF_EXPLOIT':     $to_data['status_efw'] = -6; $to_data['waf_comment'] = $log[4]; break;
					case 'DENY_BY_BFP':             $to_data['status_efw'] = -7; break;
					case 'DENY_BY_SEC_FW':          $to_data['status_efw'] = -8; break;
					case 'DENY_BY_SPAM_FW':         $to_data['status_efw'] = -9; break;
				}
				
				switch( $log[3] ){
					case 'PASS_BY_TRUSTED_NETWORK': $to_data['status'] = 3;  break;
					case 'PASS_BY_WHITELIST':       $to_data['status'] = 2;  break;
					case 'PASS':                    $to_data['status'] = 1;  break;
					case 'DENY':                    $to_data['status'] = 0;  break;
					case 'DENY_BY_NETWORK':         $to_data['status'] = -1; break;
					case 'DENY_BY_DOS':             $to_data['status'] = -2; break;
					case 'DENY_BY_WAF_XSS':         $to_data['status'] = -3; $to_data['waf_comment'] = $log[4]; break;
					case 'DENY_BY_WAF_SQL':         $to_data['status'] = -4; $to_data['waf_comment'] = $log[4]; break;
					case 'DENY_BY_WAF_FILE':        $to_data['status'] = -5; $to_data['waf_comment'] = $log[4]; break;
					case 'DENY_BY_WAF_EXPLOIT':     $to_data['status'] = -6; $to_data['waf_comment'] = $log[4]; break;
					case 'DENY_BY_BFP':             $to_data['status'] = -7; break;
					case 'DENY_BY_SEC_FW':          $to_data['status'] = -8; break;
					case 'DENY_BY_SPAM_FW':         $to_data['status'] = -9; break;
				}
				
				$data[] = $to_data;
				
			} unset($key, $value, $result, $to_data);
			
			//Sending the request
			$result = API::method__security_logs__sendFWData( $ct_key, $data );
			
			//Checking answer and deleting all lines from the table
			if( empty( $result['error'] ) ){
				
				if( $result['rows'] == count( $data ) ){
					
					foreach ( $log_files as $log_file ){
						unlink( $log_dir_path . DS . $log_file );
					}
					
					return $result;
				}else
					return array( 'error' => 'SENT_AND_RECEIVED_LOGS_COUNT_DOESNT_MACH' );
			}else
				return $result;
		}else
			return array( 'rows' => 0 );
	}
	
	public static function update( $api_key ){
		
		$multifile_url    = Get::get( 'multifile_url' );
		$url_count        = Get::get( 'url_count' );
		$current_file_num = Get::get( 'current_file_num' );
		
        $files = isset( State::getInstance()->fw_stats['updating_folder'] )
            ? glob( State::getInstance()->fw_stats['updating_folder'] . DS . '/*csv.gz' )
            : array();
		
		// Get multifiles
		if( ! $multifile_url ){

            if ( State::getInstance()->fw_stats->updating ) {
                return ['error' => 'Updating is under process.'];
            }

            State::getInstance()->fw_stats->updating       = true;
            State::getInstance()->fw_stats->save();

			$result = self::update__get_multifiles( $api_key );
			if( ! empty( $result['error'] ) ){
                return $result;
            }
            
            $update_folder = self::update__prepare_upd_dir( CT_USP_ROOT . DS . 'fw_files' );
            if( ! empty( $update_folder['error'] ) ){
                return $update_folder;
            }
            
            State::getInstance()->fw_stats->updating_folder = CT_USP_ROOT . DS . 'fw_files';
            $download_files_result = Helper::http__download_remote_file__multi( $result['file_urls'], State::getInstance()->fw_stats->updating_folder );
			if( empty( $download_files_result['error'] ) ){

				State::getInstance()->fw_stats->update_percent = 0;
				State::getInstance()->fw_stats->entries        = 0;
				State::getInstance()->fw_stats->update_start   = time();
				State::getInstance()->fw_stats->save();
				
				Helper::http__request(
					Server::get( 'HTTP_HOST' ) . CT_USP_AJAX_URI,
					array(
						'spbc_remote_call_token'  => md5( $api_key ),
						'spbc_remote_call_action' => 'update_security_firewall',
						'plugin_name'             => 'spbc',
						
						// Additional params
						'multifile_url'           => $result['multifile_url'],
						'url_count'               => count( $result['file_urls'] ),
						'current_file_num'        => 0,
					),
					array( 'get', 'async' )
				);
				
			}else
				return $result;
			
		// Write to DB
		}elseif( count( $files ) ){
			
			$result = self::update__write_to_db( reset( $files ) );
			
			if( empty( $result['error'] ) ){
                
                if( file_exists(reset($files)) ){
                    unlink(reset($files));
                }
			 
				//Increment firewall entries
				State::getInstance()->fw_stats->entries += $result;
				State::getInstance()->fw_stats->update_percent = round( ( ( (int) $current_file_num + 1 ) / (int) $url_count ), 2) * 100;
				State::getInstance()->fw_stats->save();

                // Make sure to write all fs actions
                sleep(3);

				// Make next call
				Helper::http__request(
					Server::get( 'HTTP_HOST' ) . CT_USP_AJAX_URI,
					array(
						'spbc_remote_call_token'  => md5( $api_key ),
						'spbc_remote_call_action' => 'update_security_firewall',
						'plugin_name'             => 'spbc',

						// Additional params
						'multifile_url'           => $multifile_url,
						'url_count'               => $url_count,
						'current_file_num'        => ++ $current_file_num,
					),
					array( 'get', 'async' )
				);

			}else
				return $result;
			
		// Write exclusions
		}else{
			
			$result = self::update__write_to_db__exclusions();
			usleep( 500000 );
			
			if( empty( $result['error'] ) ){
				
				//Increment firewall entries
				State::getInstance()->fw_stats->entries        += $result;
				State::getInstance()->fw_stats->updating       = false;
				State::getInstance()->fw_stats->update_percent = 0;
				State::getInstance()->fw_stats->last_update    = time();
				State::getInstance()->fw_stats->updated_in     = time() - State::getInstance()->fw_stats->update_start;
				State::getInstance()->fw_stats->save();

			}else
				return $result;
		}
	}
    
    public static function update__prepare_upd_dir( $dir_name ){
        
        global $spbc;
        
        if( $dir_name === '' ) {
            return array( 'error' => 'FW dir can not be blank.' );
        }
    
        $dir_name .= DS;
        
        if( ! is_dir( $dir_name ) && ! mkdir( $dir_name ) ){
            
            return ! is_writable( CT_USP_ROOT )
                ? array( 'error' => 'Can not to make FW dir. Low permissions: ' . fileperms( CT_USP_ROOT ) )
                : array( 'error' => 'Can not to make FW dir. Unknown reason.' );
            
        } else {
            $files = glob( $dir_name . '/*' );
            if( $files === false ){
                return array( 'error' => 'Can not find FW files.' );
            }
            if( count( $files ) === 0 ){
                return (bool) file_put_contents( $dir_name . 'index.php', '<?php' . PHP_EOL );
            }
            foreach( $files as $file ){
                if( is_file( $file ) && unlink( $file ) === false ){
                    return array( 'error' => 'Can not delete the FW file: ' . $file );
                }
            }
        }
        
        return (bool) file_put_contents( $dir_name . 'index.php', '<?php' );
    }
    
    
    /**
	 * Gets multifile with data to update Firewall.
	 *
	 * @param string $spbc_key
	 *
	 * @return array
	 */
	static public function update__get_multifiles( $spbc_key ){
		
		// Getting remote file name
		$result = API::method__security_firewall_data_file( $spbc_key, 'multifiles' );
		
		if(empty($result['error'])){
            
            usleep( 500000 );
		    
			if( !empty($result['file_url']) ){
				
				$file_url = $result['file_url'];
				
				$response_code = Helper::http__request__get_response_code($file_url);
				
				if( empty( $response_code['error'] ) ){
					
					if( $response_code === 200 || $response_code === 501 ){
							
						if( preg_match( '/multifiles/', $file_url ) ){
							
							$gz_data = Helper::http__request__get_content($file_url);
							
							if( empty( $gz_data['error'] ) ){
								
								if(Helper::get_mime_type($gz_data, 'application/x-gzip')){
									
									if(function_exists('gzdecode')) {
										
										$data = gzdecode( $gz_data );
										
										if($data !== false){
											
											$result__clear_db = self::clear_data();
											
											if( empty( $result__clear_db['error'] ) ){
												
												return array(
													'multifile_url' => $file_url,
													'file_urls'     => array_column(Helper::buffer__parse__csv($data), 0),
												);
												
											}else
												return $result__clear_db;
										}else
											return array('error' => 'COULDNT_UNPACK');
									}else
										return array('error' => 'Function gzdecode not exists. Please update your PHP to version 5.4');
								}else
									return array('error' => 'WRONG_REMOTE_FILE');
							}else
								return array('error' => 'COULD_NOT_GET_MULTIFILE: ' . $gz_data['error'] );
						}else
							return array('error' => 'WRONG_REMOTE_FILE');
					} else
						return array('error' => 'NO_REMOTE_FILE_FOUND');
				}else
					return array('error' => 'MULTIFILE_COULD_NOT_GET_RESPONSE_CODE: '. $response_code['error'] );
			}else
				return array('error' => 'BAD_RESPONSE');
		}else
			return $result;
	}
	
	/**
	 * Writes entries from remote files to Firewall database.
	 *
	 * @param string $file_url
	 *
	 * @return array|bool|int|mixed|string
	 */
	public static function update__write_to_db( $file_url ){
		
		$data = Helper::get_data_from_local_gz( $file_url );
		
		if ( ! Err::check() ) {
		 
			$db = new FileDB( 'fw_nets' );
			$networks_to_skip = array();
			if( in_array( Server::get_domain(), self::$test_domains ) ){
                $networks_to_skip[] = ip2long( '127.0.0.1' );
            }
			
			
			$inserted = 0;
			while( $data !== '' ){
				
				for(
					$i = 0, $nets_for_save = array();
					$i < 2500 && $data !== '';
					$i++
				){
					
					$entry = Helper::buffer__csv__pop_line_to_array( $data );
                    if( in_array($entry[0], $networks_to_skip ) ){
                        continue;
                    }
					
					$nets_for_save[] = array(
						'network'         => $entry[0],
						'mask'        => sprintf( '%u', bindec( str_pad( str_repeat( '1', $entry[1] ), 32, 0, STR_PAD_RIGHT ) ) ),
//						'comment'     => $entry[2]; // Comment from use,
						'status'      => isset( $entry[3] ) ? $entry[3] : 0,
						'is_personal' => isset( $entry[4] ) ? intval( $entry[4] ) : 0,
//						'country'     => isset( $entry[5] ) ? trim( $entry[5], '"' ) : 0,
					);
					
				}
				
				if( ! empty( $nets_for_save ) ){
					
					$inserted += $db->insert( $nets_for_save );
					
					if ( Err::check() ){
						Err::prepend( 'Updating FW' );
						error_log( var_export( Err::get_all( 'string' ), true ) );
						return array( 'error' => Err::get_last( 'string' ), );
					}
					
				}else
					Err::add( 'Updating FW', 'No data to save' );
			}
			
			return $inserted;
			
		}else
			Err::prepend( 'Updating FW' );
	}
	
	/**
	 * Adding local exclusions to to the FireWall database.
	 *
	 * @param array $exclusions
	 *
	 * @return array|bool|int|mixed|string
	 */
	static public function update__write_to_db__exclusions( $exclusions = array() ){
		
		//Exclusion for servers IP (SERVER_ADDR)
		if ( Server::get('HTTP_HOST') ) {
			
			// Exceptions for local hosts
			if( ! in_array( Server::get_domain(), self::$test_domains ) ){
				$exclusions[] = Helper::dns__resolve( Server::get( 'HTTP_HOST' ) );
				$exclusions[] = '127.0.0.1';
			}
			
			foreach ( $exclusions as $exclusion ) {
				if (Helper::ip__validate($exclusion) && sprintf('%u', ip2long($exclusion))) {
					$nets_for_save[] = array(
						'network'         => sprintf('%u', ip2long($exclusion)),
						'mask'        => sprintf( '%u', bindec( str_pad( str_repeat( '1', 32 ), 32, 0, STR_PAD_RIGHT ) ) ),
						'status'      => 2,
						'is_personal' => 0,
					);
				}
			}
		}
		
		$db = new FileDB( 'fw_nets' );
		
		if( isset( $nets_for_save ) ){
			
			$inserted = $db->insert( $nets_for_save );
		
			if ( Err::check() ){
				Err::prepend('Updating FW exclusions');
				error_log( var_export( Err::get_all('string'), true ) );
				return array( 'error' => Err::get_last( 'string' ), );
			}
			
			return $inserted;
			
		}else
			return 0;
	}
	
	/**
	 * Clear SFW table
	 *
	 * @return bool[]
	 */
	public static function clear_data() {
		
		// Clean current database
		$db = new FileDB( 'fw_nets' );
		$db->delete();
		
		return array( 'success' => true, );
		
	}
}