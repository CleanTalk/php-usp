<?php

namespace Cleantalk\Common;

use Cleantalk\Variables\Get;

class RemoteCalls
{

	const COOLDOWN = 10;

	public static function check() {
		return Get::is_set('spbc_remote_call_token', 'spbc_remote_call_action', 'plugin_name') && in_array(Get::get('plugin_name'), array('security','spbc'))
			? true
			: false;
	}

	public static function perform(){

		$usp = State::getInstance();

		$action = strtolower(Get::get('spbc_remote_call_action'));
		$token  = strtolower(Get::get('spbc_remote_call_token'));

		if ( isset( $usp->remote_calls->$action ) ) {

			$cooldown = isset($usp->remote_calls->$action->cooldown)
				? $usp->remote_calls->$action->cooldown
				: self::COOLDOWN;
			$pass_cooldown = Helper::ip__get(array('real')) === filter_input(INPUT_SERVER, 'SERVER_ADDR');
//			$pass_cooldown = false; // Temp crutch

			if(time() - $usp->remote_calls->$action->last_call >= $cooldown
				 || $pass_cooldown
			){

				$usp->remote_calls->$action->last_call = time();
				$usp->remote_calls->save();

				// Check API key
				if($token == strtolower(md5($usp->settings->key)) ){

					$action = 'action__'.$action;

					// Scanner actions
					if ( strpos( $action, 'scanner__' ) !== false ) {
						if ( method_exists( '\Cleantalk\Scanner\Controller', $action ) ) {
							$out = \Cleantalk\Scanner\Controller::$action();
						}

					// Common actions
					}else if(method_exists('RemoteCalls', $action)){

						sleep( (int) Get::get('delay') ); // Delay before perform action;
						$out = RemoteCalls::$action();

					}else
						Err::add('UNKNOWN_ACTION_METHOD');
				}else
					Err::add('WRONG_TOKEN');
			}else
				Err::add('TOO_MANY_ATTEMPTS');
		}else
			Err::add('UNKNOWN_ACTION');

		die( Err::check()
			? Err::check_and_output( 'as_json' )
			: json_encode($out)
		);
	}

	/**
	 *
	 */
	static function action__close_renew_banner() {
		$usp = State::getInstance();
		$usp->data->notice_show = 0;
		$usp->data->save();
		// Updating cron task
		Cron::updateTask('access_key_notices', 'spbc_access_key_notices', 86400);
		die('OK');
	}

	static function action__update_plugin() {
		add_action('template_redirect', 'spbc_update', 1);
	}

	static function action__update_security_firewall() {
		$result = spbc_security_firewall_update(true);
		die(empty($result['error']) ? 'OK' : 'FAIL '.json_encode(array('error' => $result['error'])));
	}

	static function action__drop_security_firewall() {
		$result = spbc_security_firewall_drop();
		die(empty($result['error']) ? 'OK' : 'FAIL '.json_encode(array('error' => $result['error'])));
	}

	static function action__download__quarantine_file() {
		$result = spbc_scanner_file_download(true, Get::get('file_id'));
		if(empty($result['error'])){
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename='.$result['file_name']);
		}
		die(empty($result['error'])
			? $result['file_content']
			: 'FAIL '.json_encode(array('error' => $result['error'])));
	}

	static function action__update_settings() {
		$usp = State::getInstance();
		$source = $_GET;
		foreach($usp->default_settings as $setting => $value){
			if ( isset( $source[ $setting ] ) ) {
				$var = $source[$setting];
				$type = gettype($usp->settings->$setting);
				settype($var, $type);
				if($type == 'string')
					$var = preg_replace(array('/=/', '/`/'), '', $var);
				$usp->settings->$setting = $var;
			}
		}
		$usp->settings->save();
		die('OK');
	}

	static function action__backup_signatures_files() {
		$result = spbc_backup__files_with_signatures();
		die(empty($result['error'])
			? 'OK'
			: 'FAIL '.json_encode(array('error' => $result['error'])));
	}

	static function action__rollback_repair() {
		$result = spbc_rollback(Get::get('backup_id'));
		die(empty($result['error'])
			? 'OK'
			: 'FAIL '.json_encode(array('error' => $result['error'])));
	}

	static function action__scanner_clear_hashes() {
		$result = true;
		switch(Get::get('type')){
			case 'plugins':            delete_option(SPBC_PLUGINS);                             break;
			case 'themes':             delete_option(SPBC_THEMES);                              break;
			case 'plugins_and_themes': delete_option(SPBC_THEMES); delete_option(SPBC_PLUGINS); break;
			case 'all':                $result = spbc_scanner_clear__all();                          break;
			default:                   $result = spbc_scanner_clear__all();                          break;
		}
		die(empty($result['error'])
			? 'OK'
			: 'FAIL '.json_encode(array('error' => 'COULDNT_CLEAR_ALL_DB_ERROR')));
	}

	static function action__scanner_signatures_update() {
		$result = spbc_scanner__signatures_update();
		die(empty($result['error'])
			? 'OK' . ' ' . (!empty($result['success']) ? $result['success'] : '')
			: 'FAIL '.json_encode(array('error' => $result['error'])));
	}

	static function action__scanner__controller() {
		return spbc_scanner__controller();
	}

	static function action__scanner__get_remote_hashes() {
		spbc_scanner_get_remote_hashes();
	}

	static function action__scanner__count_hashes_plug() {
		spbc_scanner_count_hashes_plug();
	}

	static function action__scanner__get_remote_hashes__plug() {
		spbc_scanner_get_remote_hashes__plug();
	}

	static function action__scanner__clear_table() {
		usp_scanner__clear_table();
	}

	static function action__scanner__count_files() {
		spbc_scanner_count_files();
	}

	static function action__scanner__scan() {
		spbc_scanner_scan();
	}

	static function action__scanner__count_files__by_status() {
		spbc_scanner_count_files__by_status();
	}

	static function action__scanner__scan_heuristic() {
		spbc_scanner_scan_signatures();
	}

	static function action__scanner__scan_signatures() {
		spbc_scanner_scan_signatures();
	}

	static function action__scanner__backup_sigantures() {
		spbc_backup__files_with_signatures();
	}

	static function action__scanner__count_cure() {
		spbc_scanner_count_cure();
	}

	static function action__scanner__cure() {
		spbc_scanner_cure();
	}

	static function action__scanner__links_count() {
		spbc_scanner_links_count();
	}

	static function action__scanner__links_scan() {
		spbc_scanner_links_scan();
	}

	static function action__scanner__frontend_scan() {
		spbc_scanner_frontend__scan();
	}

	static function action_scanner__send_results() {
		spbc_scanner_send_results();
	}
}
