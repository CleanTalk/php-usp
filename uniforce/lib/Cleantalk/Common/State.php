<?php

namespace Cleantalk\Common;

/*
 * 
 * CleanTalk Security State class
 * 
 * @package Security Plugin by CleanTalk
 * @subpackage State
 * @Version 2.0
 * @author Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * @license GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 *
 */

/**
 * @property Storage settings
 * @property Storage data
 * @property Storage remote_calls
 * @property Storage scan_result
 *
 */
class State extends \Cleantalk\Common\Storage{

	use \Cleantalk\Templates\FluidInterface;
	use \Cleantalk\Templates\Singleton;

	public static $instance;

	public $default_settings = array(

		// Authentication
		'bfp'                  => 1,
		'bfp_admin_page'       => '',
		'block_timer__1_fails' => 3,
		'block_timer__5_fails' => 10,

		// Access Key
		'key' => '',

		// Firewall
		'fw' => 1,

		// Traffic Control
		'traffic_control_enabled'            => 1,
		'traffic_control_autoblock_amount'   => 1000,
		'traffic_control_autoblock_period'   => 3600,

		// Web Application Firewall
		'waf'                              => 1,
		'waf_xss_check'                    => 1,
		'waf_sql_check'                    => 1,
		'waf_file_check'                   => 1,
		'waf_exploit_check'                => 1,

		// Scanner
		'scanner_auto_start'		       => 1,
		'scanner_outbound_links'		   => 0,
		'scanner_outbound_links_mirrors'   => '',
		'scanner_heuristic_analysis'	   => 1,
		'scanner_signature_analysis'       => 1,
		'scanner_auto_cure'                => 1,
		'scanner_frontend_analysis'        => 1,

		// Misc
		'backend_logs_enable'              => 1,
	);

	public $default_data = array(

		// Application
		'is_installed'   => false,
		'detected_cms'   => 'Unknown',
		'modified_files' => array(),
		'plugin_version' => 1,

		//Security
		'salt'         => '',
		'security_key' => '',

		// User data
		'email'    => '',
		'password' => '',

		// Stats
		'stat' => array(
			'fw' => array(
				'logs_sent_time' => 0,
				'logs_sent_amount' => 0,
				'entries' => 0,
				'last_update' => 0,
				'count' => 0,
			),
			'bfp' => array(
				'logs_sent_time' => 0,
				'logs_sent_amount' => 0,
				'count' => 0,
			),
			'scanner' => array(
				'last_scan' => 0,
				'last_scan_amount' => 0,
				'signature_last_update'	=> 0,
				'signature_entries'	=> 0,
			),
			'php_logs' => array(
				'last_sent' => 0,
			),
		),

		// Account
		'key_is_ok' => false,
		'valid'     => false,
			// notice_paid_till
			'notice_show'         => false,
			'notice_renew'        => false,
			'notice_trial'        => false,
			'notice_review'       => false,
			'notice_were_updated' => false,
			'user_token'          => '',
			'spam_count'          => 0,
			'moderate_ip'         => false,
			'moderate'            => false,
			'service_id'          => 0,
			'license_trial'       => false,
			'account_name_ob'     => 'unknown',
			'ip_license'          => false,

		'scanner' => array(
			'cron' => array(
				'state'         => 'get_hashes',
				'total_scanned' => 0,
				'offset'        => 0,
			),
			'cured' => array(),
		),
		'2fa_keys'          => array(),
	);

	public $def_remote_calls = array(

	// Common
		'close_renew_banner'       => array('last_call' => 0,),
		'update_plugin'            => array('last_call' => 0,),
		'update_security_firewall' => array('last_call' => 0, 'cooldown' => 3),
		'drop_security_firewall'   => array('last_call' => 0,),
		'update_settings'          => array('last_call' => 0,),

	// Inner
		'download__quarantine_file' => array('last_call' => 0, 'cooldown' => 3),

	// Backups
		'backup_signatures_files' => array('last_call' => 0,),
		'rollback_repair'         => array('last_call' => 0,),

	// Scanner
		'scanner_signatures_update'        => array('last_call' => 0,),
		'scanner_clear_hashes'             => array('last_call' => 0,),

		'scanner__controller'              => array('last_call' => 0, 'cooldown' => 3),
		'scanner__get_remote_hashes'       => array('last_call' => 0,),
		'scanner__count_hashes_plug'       => array('last_call' => 0,),
		'scanner__get_remote_hashes__plug' => array('last_call' => 0,),
		'scanner__clear_table'             => array('last_call' => 0,),
		'scanner__count_files'             => array('last_call' => 0,),
		'scanner__scan'                    => array('last_call' => 0,),
		'scanner__count_files__by_status'  => array('last_call' => 0,),
		'scanner__scan_heuristic'          => array('last_call' => 0,),
		'scanner__scan_signatures'         => array('last_call' => 0,),
		'scanner__count_cure'              => array('last_call' => 0,),
		'scanner__cure'                    => array('last_call' => 0,),
		'scanner__links_count'             => array('last_call' => 0,),
		'scanner__links_scan'              => array('last_call' => 0,),
		'scanner__frontend_scan'           => array('last_call' => 0,),
		'scanner__send_results'            => array('last_call' => 0,),
	);

	public function __construct( ...$options )
	{
		// Default options to get
		$options = $options ? $options : array( 'settings', 'data' );

		if( self::$instance )
			return self::$instance;

		foreach($options as $option_name){

			$option = $this->get( $option_name );

			// Default options
			if ( $option_name === 'settings' ) {
				$option = is_array( $option ) ? array_merge( $this->default_settings, $option ) : $this->default_settings;
			}
			
			// Default data
			if($option_name === 'data'){
				$option = is_array( $option ) ? array_merge( $this->default_data, $option ) : $this->default_data;

				// Generate during construction if unset
				if(empty($option['salt']))
					$option['salt'] = str_pad(rand(0, getrandmax()), 6, '0').str_pad(rand(0, getrandmax()), 6, '0');
				if(empty($option['security_key']))
					$option['security_key'] = md5( isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '' );

			}
			
			// Default errors
			if ( $option_name === 'errors' ) {
				$option = is_array( $option ) ? array_merge( $this->def_errors, $option ) : $this->def_errors;
			}
			
			// Default remote calls
			if ( $option_name === 'remote_calls' ) {
				$option = is_array( $option ) ? array_merge( $this->def_remote_calls, $option ) : $this->def_remote_calls;
			}

			$this->$option_name = $this->convertToStorage( $option_name, $option );
		}

		self::$instance = $this;

	}

	/**
	 * Magic. Handles unexisting properties.
	 * Returns certain options. From the top level of State.
	 * for ->key returns settings->key
	 *      for ->* returns data->* if it's set
	 *          for every other occurrences pass call to Storage->__get()
	 *
	 * @param $name
	 *
	 * @return mixed|null
	 */
	public function __get( $name ) {
		return $name === 'key'
			? $this->settings->key
			: ( isset( $this->data->$name )
				? $this->data->$name
				: parent::__get( $name )
			);
	}
}
