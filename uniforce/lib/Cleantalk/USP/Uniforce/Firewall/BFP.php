<?php

namespace Cleantalk\USP\Uniforce\Firewall;

use Cleantalk\USP\Common\File;
use Cleantalk\USP\Common\State;
use Cleantalk\USP\Uniforce\API;
use Cleantalk\USP\Uniforce\Helper;
use Cleantalk\USP\Variables\Cookie;
use Cleantalk\USP\Variables\Get;
use Cleantalk\USP\Variables\Server;

class BFP extends \Cleantalk\USP\Uniforce\Firewall\FirewallModule {
	
	public $module_name = 'BFP';
	
	// Flags
	protected $is_logged_in  = false;
	protected $is_login_page = false;
	protected $do_check      = false;
	
	protected $allowed_interval = 900;
	protected $bf_limit = 5;
	protected $block_period = 3600;
	
	protected $chance_to_clean = 100;
	
	/**
	 * FireWall_module constructor.
	 * Use this method to prepare any data for the module working.
	 *
	 * @param array $params
	 */
	public function __construct( $params = array() ){
		
		parent::__construct( $params );
		
	}
	
	public function check(){
		
		$results = array();
		
		if( $this->is_login_page && ! $this->is_logged_in && $this->do_check ){
			
			$block_time = 20 * 60; // 20 minutes
			$allowed_count = 2;
			$allowed_interval = 900; // 15 min
			
			$bfp_blacklist      = State::getInstance()->bfp_blacklist;
			$bfp_blacklist_fast = State::getInstance()->bfp_blacklist_fast;
			
			$found_ip = null;
			$current_ip__real = $this->ip_array['real'];
			
			// Check against black list
			foreach( $bfp_blacklist as $bad_ip => $bad_ip__details ){
				if( $bad_ip === $current_ip__real ){
					$found_ip = $bad_ip;
					$found_ip__details = $bad_ip__details;
				}
				
			} unset( $bad_ip, $bad_ip__details );
			
			if( $found_ip ) {
				
				// Remove the IP from the blacklist and proceed the checking
				if( $found_ip__details->added + $block_time < time() ) {
					unset( $bfp_blacklist->$current_ip__real );
					$bfp_blacklist->save();
				}else{
					$results[] = array( 'status' => 'DENY_BY_BFP', );
				}
				
			}
			
			// Check count of logins
			$found_ip = null;
			$js_on    = spbct_js_test();
			
			foreach( $bfp_blacklist_fast as $bad_ip => $bad_ip__details ){
				
				if( $bad_ip === $current_ip__real && $bad_ip__details->added + $allowed_interval > time() ){
					$found_ip = $bad_ip;
					$found_ip__details = array(
						'added' => $bad_ip__details->added,
						'js_on' => $js_on,
						'count' => ++$bad_ip__details->count,
					);
				}elseif( $bfp_blacklist_fast->$current_ip__real ){
                    unset( $bfp_blacklist_fast->$current_ip__real );
					$bfp_blacklist_fast->save();
				}
				
			} unset( $bad_ip, $bad_ip__details );
			
			if( $found_ip ) {
				
				//increased allowed count to 20 if JS is on!
				if( $found_ip__details['js_on'] == 1 )
					$allowed_count = $allowed_count * 2;
				
				// Check count of the logins and move the IP to the black list.
				if( $found_ip__details['count'] > $allowed_count ){
					
					$bfp_blacklist->$current_ip__real['added'] = time();
					$bfp_blacklist->save();
					
					unset( $bfp_blacklist_fast->$current_ip__real );
					$bfp_blacklist_fast->save();
					
					$results[] = array( 'status' => 'DENY_BY_BFP', );
					
				}else{
					
					$bfp_blacklist_fast->$found_ip = $found_ip__details;
					$bfp_blacklist_fast->save();
					
				}
				
			}else{
				$bfp_blacklist_fast->$current_ip__real = array(
					'added' => time(),
					'js_on' => $js_on,
					'count' => 1
				);
				$bfp_blacklist_fast->save();
				
			}
			
			// Make the result standard
			foreach( $results as &$result ){
				$result = array_merge( $result, array(
					'ip'          => $current_ip__real,
					'is_personal' => false,
					'status'      => 'DENY_BY_BFP',
					'module'      => 'BFP'
				) );
			}
			
		}
		
		return $results;
		
	}
	
	/**
	 *
	 *
	 * @param $result
	 */
	public function actions_for_denied( $result ){
		$this->state->data->stat->bfp->count;
		$this->state->data->save();
	}
	
	/**
	 * @param array|string $fw_result
	 */
	public static function update_log( $fw_result ) {
		
		// Updating common firewall log
		if( is_array( $fw_result ) && $fw_result['status'] !== 'PASS' ){
			parent::update_log( $fw_result );
			return;
		}
			
		global $salt;
		
		$params_default = array(
			
			// Necessary
			'event'    => null,
			'auth_ip'  => isset( $fw_result['ip'] ) ? $fw_result['ip'] : Helper::ip__get( array( 'real' ) ),
			'datetime' => gmdate( 'Y-m-d H:i:s' ),
			
			// Unnecessary
			'page_url'   => substr( Server::get( 'HTTP_HOST' ) . Server::get( 'REQUEST_URI' ), 0, 1024 ),
			'user_agent' => substr( Server::get( 'HTTP_USER_AGENT' ), 0, 1024 ),
			
			// @ToDo Unused params. Implement this logic to the next releases
			'page'         => null,
			'page_time'    => null,
			'browser_sign' => null,
		);
		$params = array_merge( $params_default, array( 'event' => $fw_result, ) );
		
		// Inserting to the logs.
		$log_path = CT_USP_ROOT . 'data/security_logs/' . hash('sha256', $params['auth_ip'] . $salt . $params['event']) . '.log';
		
		if( file_exists( $log_path ) )
			$log = explode( ',', file_get_contents( $log_path ) );
		
		$log = array(
			$params['event'],
			$params['auth_ip'],
			$params['datetime'],
			$params['page_url'],
			$params['user_agent'],
			$params['page'],
			$params['page_time'],
			$params['browser_sign'],
			isset($log[8]) ? intval( $log[8] ) + 1 : 1,
		);
		
		$fd = fopen( $log_path, 'w' );
		if( $fd ){
            flock( $fd, LOCK_EX );
            fputcsv( $fd, $log );
            fclose( $fd );
        }
	}
	
	/**
	 * Sends security log
	 *
	 * @param string $ct_key
	 *
	 * @return array|bool|int[]|mixed|string[]
	 */
	public static function send_log( $ct_key ){
		
		$log_dir_path = CT_USP_ROOT . 'data/security_logs';
		
		if( is_dir( $log_dir_path ) ){
			
			$log_files = array_diff( scandir( $log_dir_path ), array( '.', '..', 'index.php' ) );
			
			if( ! empty( $log_files ) ){
				
				//Compile logs
				$data = array();
				
				foreach( $log_files as $log_file ){
					
					$log = file_get_contents( $log_dir_path . DS . $log_file );
					$log = str_getcsv( $log );
					
					if( strval( $log[8] ) > 0 ){
						for( $i = 0; strval( $log[8] ) > $i; $i ++ ){
							$data[] = array(
								'datetime'      => strval( $log[2] ),
								'user_login'    => null,
								'event'         => strval( $log[0] ),
								'auth_ip'       => strpos( ':', $log[1] ) === false ? (int) sprintf( '%u', ip2long( $log[1] ) ) : (string) $log[1],
								'page_url'      => strval( $log[3] ),
								'event_runtime' => null,
								'role'          => null,
							);
						}
					}else{
						$data[] = array(
							'datetime'      => strval( $log[2] ),
							'user_login'    => null,
							'event'         => strval( $log[0] ),
							'auth_ip'       => strpos( ':', $log[1] ) === false ? (int) sprintf( '%u', ip2long( $log[1] ) ) : (string) $log[1],
							'page_url'      => strval( $log[3] ),
							'event_runtime' => null,
							'role'          => null,
						);
					}
					
					// Adding user agent if it's login event
					if( in_array( strval( $log[0] ), array( 'login', 'login_2fa', 'login_new_device', 'logout', ) ) ){
						$data[] = array_merge(
							array_pop( $data ),
							array(
								'user_agent' => $log[4],
							)
						);
					}
				}
				
				$result = API::method__security_logs( $ct_key, $data );
				
				if( empty( $result['error'] ) ){
					
					//Clear local table if it's ok.
					if( $result['rows'] == count( $data ) ){
						
						foreach( $log_files as $log_file ){
							if( file_exists( $log_dir_path . DS . $log_file ) )
								unlink( $log_dir_path . DS . $log_file );
						}
						
						return $result;
						
					}else{
						return array( 'error' => 'SENT_AND_RECEIVED_LOGS_COUNT_DOESNT_MACH' );
					}
				}else{
					return $result;
				}
			}else{
				return array( 'rows' => 0 );
			} // No logs. Log file is empty.
		}else{
			return array( 'rows' => 0 );
		} // No logs. Directory is not exists.
		
	}
	
	public static function is_logged_in( $cms ) {
		
		$cms = defined( 'USP_DASHBOARD' ) ? 'UniForce' : $cms;
		
		switch ( $cms ) {
			case 'Joomla' :
				return class_exists('JFactory') && \JFactory::getUser()->id;
				break;
			case 'Drupal7' :
				global $user;
				return isset( $user->uid ) && $user->uid != 0;
				break;
			case 'Drupal8' :
				return class_exists('Drupal') && !! \Drupal::currentUser()->id();
				break;
			case 'Bitrix' :
				return class_exists( 'CUser') && \CUser::IsAuthorized();
				break;
			case 'OpenCart' :
				// @ToDo we have to find a way to detect admin logging in
				return true;
				break;
			case 'UniForce':
				return Cookie::get( 'authentificated' ) === State::getInstance()->data->security_key;
				break;
			default :
				// @ToDo implement universal logic for cookies checking
				return true;
				break;
		}
	}
}