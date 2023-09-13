<?php

namespace Cleantalk\USP\Uniforce\Firewall;

use Cleantalk\USP\Common\File;
use Cleantalk\USP\Common\State;
use Cleantalk\USP\Uniforce\API;
use Cleantalk\USP\Uniforce\Helper;
use Cleantalk\USP\Variables\Cookie;
use Cleantalk\USP\Variables\Get;
use Cleantalk\USP\Variables\Post;
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

		if( $this->is_login_page && ! $this->is_logged_in && $this->do_check && isset( $this->ip_array['real'] ) ){

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
				}elseif( isset( $bfp_blacklist_fast->$current_ip__real ) ){
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
		$this->state->data->stat->bfp->count++;
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

        if( is_array( $fw_result ) && $fw_result['status'] !== 'DENY_BY_BFP' ){
            $fw_result = 'auth_failed';
        }

		global $salt;

		$params_default = array(

			// Necessary
			'event'    => null,
			'auth_ip'  => isset( $fw_result['ip'] ) ? $fw_result['ip'] : Helper::ip__get( array( 'real' ) ),
			'time'     => time(),

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
			$params['time'],
			$params['page_url'],
			$params['user_agent'],
			$params['page'],
			$params['page_time'],
			$params['browser_sign'],
			isset($log[8]) ? (int) $log[8] + 1 : 1,
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

					// Skip bad files
					if( ! isset( $log[0], $log[1], $log[2], $log[3], $log[4], $log[5], $log[6], $log[7], $log[8] ) ){
					    unlink( $log_dir_path . DS . $log_file );
					    continue;
                    }

                    $_log = array(
                        'event' => $log[0],
                        'ip' => $log[1],
                        'timestamp' => $log[2],
                        'page_url' => $log[3],
                        'http_user_agent' => $log[4],
                        //unused
//                        'page' => $log[5],
//                        'page_time' => $log[6],
//                        'browser_sign' => $log[7],
                        'hits' => $log[8],
                    );

                    //datetime legacy
                    if ( !empty($_log['timestamp']) && !Helper::arg_to_timestamp($_log['timestamp']) ){
                        $_log['datetime'] = $_log['timestamp'];
                    } else {
                        $_log['datetime'] = !empty($_log['timestamp'])
                            ? gmdate('Y-m-d H:i:s', $_log['timestamp'])
                            : gmdate('Y-m-d H:i:s', 0);
                    }

                    //timestamp conversion
                    if ( !empty($_log['timestamp']) && Helper::arg_to_timestamp($_log['timestamp']) ){
                        $_log['timestamp'] = Helper::arg_to_timestamp($_log['timestamp']);
                    } else {
                        $_log['timestamp'] = 0;
                    }


					$auth_ip = $_log['ip'] ? (string) $_log['ip']: '0.0.0.0';

					if( (int) $_log['hits'] > 0 ){ //todo AG: for what this for cycle?
						for( $i = 0; (int) $_log['hits'] > $i; $i ++ ){
							$data[] = array(
								'datetime'      => $_log['datetime'],
								'datetime_gmt'  => $_log['timestamp'],
								'user_login'    => null,
								'event'         => (string) $_log['event'],
								'auth_ip'       => strpos( ':', $auth_ip ) === false
                                    ? (int) sprintf( '%u', ip2long( $auth_ip ) )
                                    : $auth_ip,
								'page_url'      => (string) $_log['page_url'],
								'event_runtime' => null,
								'role'          => null,
							);
						}
					} else {
                        $data[] = array(
                            'datetime'      => $_log['datetime'],
                            'datetime_gmt'  => $_log['timestamp'],
                            'user_login'    => null,
                            'event'         => (string) $_log['event'],
                            'auth_ip'       => strpos( ':', $auth_ip ) === false
                                ? (int) sprintf( '%u', ip2long( $auth_ip ) )
                                : $auth_ip,
                            'page_url'      => (string) $_log['page_url'],
                            'event_runtime' => null,
                            'role'          => null,
                        );
					}

					// Adding user agent if it's login event
					if( in_array( (string) $_log['event'], array( 'login', 'login_2fa', 'login_new_device', 'logout', ) ) ){
						$data[] = array_merge(
							array_pop( $data ),
							array(
								'user_agent' => $_log['http_user_agent'],
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
				return false;
				break;
		}
	}

    /**
     * Checking the post request for markers
     *
     * @return bool
     */
    public static function is_login_page() {
        $usp = State::getInstance();

        if(mb_strtolower($usp->detected_cms) === 'unknown') {
            if(isset($_POST) && !empty($_POST)) {
                $number_matches = 0;
                $number_pass_matches = 0;

                // Markers for searching in field names and request uri
                $form_field_markers = array(
                    'user',
                    'username',
                    'login'
                );
                $pass_field_markers = array(
                    'pass', 'password', 'psw'
                );

                if($usp->settings->bfp_login_form_fields) {
                    $usp->settings->bfp_login_form_fields = str_replace(' ', '', $usp->settings->bfp_login_form_fields);
                    $form_field_markers = explode(',', $usp->settings->bfp_login_form_fields);
                }

                // Search in POST
                foreach ($_POST as $key => $value) {
                    if(in_array(strtolower($key), $form_field_markers)) {
                        $number_matches++;
                    }
                    if(in_array(strtolower($key), $pass_field_markers)) {
                        $number_pass_matches++;
                    }
                    if(is_array($value)) {
                        foreach ($value as $k => $v) {
                            if(in_array(strtolower($k), $form_field_markers)) {
                                $number_matches++;
                            }
                            if(in_array(strtolower($k), $pass_field_markers)) {
                                $number_pass_matches++;
                            }
                        }
                    }
                }

                // Search in Request URI
                foreach ($form_field_markers as $marker) {
                    if(strpos($_SERVER['REQUEST_URI'], $marker) !== false) {
                        $number_matches++;
                    }
                }
                foreach ($pass_field_markers as $marker) {
                    if(strpos($_SERVER['REQUEST_URI'], $marker) !== false) {
                        $number_pass_matches++;
                    }
                }

                // Search in Reference URI
                if(isset($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER']) {
                    foreach ($form_field_markers as $marker) {
                        if(strpos($_SERVER['HTTP_REFERER'], $marker) !== false) {
                            $number_matches++;
                        }
                    }
                }
                if(isset($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER']) {
                    foreach ($pass_field_markers as $marker) {
                        if(strpos($_SERVER['HTTP_REFERER'], $marker) !== false) {
                            $number_pass_matches++;
                        }
                    }
                }

                // Results
                if($usp->settings->bfp_login_form_fields && ($number_matches >= 2 || $number_matches > 0 && $number_pass_matches > 0) ) {
                    return true;
                }

                if($number_matches >= 2 && $number_pass_matches > 0) {
                    return true;
                }
            }
        } else {
            return ( $usp->settings->bfp_admin_page && Server::has_string( 'REQUEST_URI', $usp->settings->bfp_admin_page ) ) ||
                ( defined( 'USP_DASHBOARD' ) && Post::get( 'login' ) );
        }

        return false;
    }
}
