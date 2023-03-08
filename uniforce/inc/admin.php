<?php

use Cleantalk\USP\Common\API;
use Cleantalk\USP\Common\Err;
use Cleantalk\USP\Common\File;
use Cleantalk\USP\Common\State;
use Cleantalk\USP\Uniforce\Cron;
use Cleantalk\USP\Uniforce\FireWall;
use Cleantalk\USP\Uniforce\Helper;
use Cleantalk\USP\Variables\Post;
use Cleantalk\USP\Variables\Server;

/**
 * Method notice_paid_till
 *
 * AJAX handler (returns json result)
 */
function usp_key_validate() {

    $result = API::method__notice_paid_till(
        Post::get( 'key' ),
        preg_replace( '/http[s]?:\/\//', '', Server::get( 'SERVER_NAME' ), 1 ),
        'security'
    );

    if( ! empty( $result['error'] ) ){
        $result['error'] = 'Checking key failed: ' . $result['error'];
    }

    die( json_encode( $result ) );

}

/**
 * Method get_api_key
 *
 * AJAX handler (returns json result)
 */
function usp_get_key() {

    $result = API::method__get_api_key(
        'security',
        Post::get( 'email' ),
        Server::get( 'SERVER_NAME' ),
        'uniforce'
    );

    if( ! empty( $result['exists'] ) ){
        $result['error'] = 'This website already registered!';
    }
    if( ! empty( $result['error'] ) ){
        $result['error'] = 'Getting key error: ' . $result['error'];
    }

    die( json_encode( $result ) );

}

/**
 * Check files to modify and runs installation
 *
 * AJAX handler (returns json result)
 */
function usp_do_install() {

    // Parsing key
    if( preg_match( '/^[a-z0-9]{1,20}$/', Post::get( 'key' ), $matches ) ){

        $api_key = $matches[0];
        $cms     = usp_detect_cms( CT_USP_SITE_ROOT . 'index.php' );

        $files_to_mod = array();

        // Add index.php to files for modification if exists
        $files_to_mod[] = 'index.php';

        // JOOMLA ONLY: Add administrator/index.php to files for modification if exists
        if( $cms['name'] == 'Joomla' ) {
            $files_to_mod[] = 'administrator/index.php';
        }

        // BITRIX ONLY: Add bitrix/admin/index.php to files for modification if exists
        if( $cms['name'] == 'Bitrix' ) {
            $files_to_mod[] = 'bitrix/admin/index.php';
        }

        // OPENCART ONLY: Add admin/index.php to files for modification if exists
        if( $cms['name'] == 'OpenCart' ) {
            $files_to_mod[] = 'admin/index.php';
        }

        //Additional scripts to modify
        if( Post::get( 'addition_scripts' ) ){
            // Merging
            $additional_files = explode( ",", Post::get( 'addition_scripts' ) );
            $files_to_mod     = array_unique( array_merge( $files_to_mod, $additional_files ) );
        }

        if( $files_to_mod ){
            $tmp = array();
            foreach ( $files_to_mod as $file_to_mod ){

                // Check for absolute paths
                if(
                    preg_match( '/^[\/\\\\].*/', $file_to_mod) || // Root for *nix systems
                    preg_match( '/^[A-Za-z]:\/.*/', $file_to_mod)     // Root for windows systems
                ){
                    Err::add( 'File paths should be relative' );
                    break;
                }

                // Check for .. upper directory access
                if(
                preg_match( '/^\.\.[\/\\\\].*/', $file_to_mod) // Access to upper levels
                ){
                    Err::add( 'Script for modification should be in the current folder or lower. You can not access upper leveled folders.' );
                    break;
                }

                $file = CT_USP_SITE_ROOT . trim( $file_to_mod );
                if( file_exists($file) )
                    $tmp[] = $file;
            }
            $files_to_mod = $tmp;
        }

        Err::check() && die( Err::get_last( 'as_json' ) );

        if( !empty($files_to_mod) ){

            // Determine file to install Cleantalk script
            $exclusions = array();

            usp_install( $files_to_mod, $api_key, $cms, $exclusions );

        }else
            Err::add( 'All files for script paths are unavailable' );
    }else
        Err::add( 'Key is bad. Key is "' . Post::get( 'key' ) . '"' );

    // Check for errors and output result
    $out = Err::check()
        ? Err::get_last( 'string' )
        : array( 'success' => true );

    die( json_encode( $out ) );

}

/**
 *  Modify files
 *
 * @param $files
 * @param $api_key
 * @param $cms
 * @param $exclusions
 */
function usp_install($files, $api_key, $cms, $exclusions ){
	
	foreach ($files as $file){
		
		$file_content = file_get_contents( $file );
        // Check if short PHP tags used
        if( preg_match( "/<\?[^(php)]/", $file_content ) ) {
            $open_php_tag = '<?';
        } else {
            $open_php_tag = '<?php';
        }
		$php_open_tags  = preg_match_all("/(<\?)/", $file_content);
		$php_close_tags = preg_match_all("/(\?>)/", $file_content);
		$first_php_start = strpos($file_content, '<?');
		// Adding <?php to the start if it's not there
		if($first_php_start !== 0)
			File::inject__code($file, "$open_php_tag\n?>\n", 'start');
		
		if( ! Err::check() ){
			
			// Adding ? > to the end if it's not there
			if($php_open_tags <= $php_close_tags)
				File::inject__code($file, "\n$open_php_tag\n" . PHP_EOL, 'end');
			
			if( ! Err::check() ){

				// Addition to the top of the script
				File::inject__code(
					$file,
					"\trequire_once( '" . CT_USP_SITE_ROOT . "uniforce/uniforce.php');",
					'(<\?php)|(<\?)',
					'top_code'
				);
				
				if( ! Err::check() ){
					
					// Addition to index.php Bottom (JavaScript test)
					File::inject__code(
						$file,
						"\t\nif(ob_get_contents()){\nob_end_flush();\n}\n"
						."\tif(isset(\$_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower(\$_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'){\n"
						."\t\tdie();\n"
						."\t}",
						'end',
						'bottom_code'
					);
					
				}
			}
		}
	}

	// Install settings in cofig if everything is ok
	if( ! Err::check() )
        usp_install_config( $files, $api_key, $cms, $exclusions );
	
	// Set cron tasks
	if( ! Err::check() )
        usp_install_cron();

	usp_check_account_status( $api_key );
}

/**
 * Modify config
 *
 * @param $modified_files
 * @param $api_key
 * @param $cms
 * @param $exclusions
 */
function usp_install_config($modified_files, $api_key, $cms, $exclusions ){

    $usp = State::getInstance();
    $pass = '';
    $login = '';

    if( Post::get( 'admin_password' ) ) {
        $usp->data->password = hash( 'sha256', trim( Post::get( 'admin_password' ) ) );
        $pass = trim( Post::get( 'admin_password' ) );
    }


    if( Post::get( 'email' ) ) {
        $usp->data->email = trim( Post::get( 'email' ) );
        $login = $usp->data->email;
    }


    if( Post::get( 'user_token' ) )
        $usp->data->user_token = trim( Post::get( 'user_token' ) );

    usp_send_pass_to_email(trim(Post::get('email')), $login, $pass);

    if( Post::get( 'account_name_ob' ) )
        $usp->data->account_name_ob =  trim( Post::get( 'account_name_ob' ) );

    $usp->data->security_key = hash( 'sha256', '~(o_O)~' . $usp->key . $usp->data->salt );
    $usp->data->modified_files = $modified_files;
    $usp->data->detected_cms = $cms['name'];
    $usp->data->is_installed  = true;
    $usp->data->no_sql  = (boolean)Post::get( 'no_sql' );
    $usp->data->save();

    $usp->settings->bfp_admin_page =  $cms['admin_page'];
    $usp->settings->key  = $api_key;
    $usp->settings->save();

    $usp->plugin_meta->is_installed  = true;
    $usp->plugin_meta->version = SPBCT_VERSION;
    if ( empty($usp->plugin_meta->latest_version) ) {
        $updater = new \Cleantalk\USP\Updater\Updater(CT_USP_ROOT);
        $usp->plugin_meta->latest_version = $updater->getLatestVersion();
    }
    $usp->plugin_meta->save();
}

function usp_send_pass_to_email($to, $login, $pass)
{
    $host = $_SERVER['HTTP_HOST'] ?: 'Your Site';
    //$to = trim( Post::get( 'email' ) );
    $subject = 'UniForce settings password for ' . $host;
    $message = "Hi,<br><br>
                Your credentials to get access to settings of Uniforce (Universal security plugin by CleanTalk) are bellow,<br><br>
                Login: $login<br>
                Password: $pass <br>
                Settings URL: https://$host/uniforce/ <br>
                Dashboard: https://cleantalk.org/my/?cp_mode=security <br><br>
                --<br>
                With regards,<br>
                CleanTalk team.";

    $headers  = 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";

    // Sending password
    mail(
        $to,
        $subject,
        $message,
        $headers
    );
}

/**
 * Modify cron
 */
function usp_install_cron(){

	Cron::addTask( 'sfw_update', 'uniforce_fw_update', 86400, time() + 20 );
	Cron::addTask( 'security_send_logs', 'uniforce_security_send_logs', 3600 );
    Cron::addTask( 'fw_send_logs', 'uniforce_fw_send_logs', 3600 );
    Cron::addTask( 'clean_black_lists', 'uniforce_clean_black_lists', 86400 );
    Cron::addTask( 'update_signatures', 'usp_scanner__get_signatures', 86400, time() + 10 );
    Cron::addTask( 'check_for_updates', 'usp_get_latest_version', 86400, time() );

}

/**
 *  Uninstall
 *
 * @return bool
 */
function usp_uninstall(){
	
	$usp = State::getInstance();

	foreach ( $usp->data->modified_files as $file ){
		File::clean__tag( $file, 'top_code' );
		File::clean__tag( $file, 'bottom_code' );
	}

	// Deleting FW data
	$db = new \Cleantalk\USP\File\FileDB( 'fw_nets' );
	$db->delete();

	// Deleting options and their files
	$usp->delete( 'data' );
	$usp->delete( 'settings' );
	$usp->delete( 'remote_calls' );
	$usp->delete( 'scan_result' );
	$usp->delete( 'signatures' );
	$usp->delete( 'fw_stats' );
	$usp->delete( 'plugin_meta' );
	
	$usp->delete( 'bfp_blacklist' );
	$usp->delete( 'bfp_blacklist_fast' );

	// Deleting cron tasks
	unlink( CT_USP_CRON_FILE );

	// Deleting any logs
    usp_uninstall_logs();

	setcookie('authentificated', 0, time()-86400, '/', null, false, true);

	return ! Err::check();

}

/**
 * Unlink any logs files from the system
 */
function usp_uninstall_logs() {

    $log_dir_paths[] = CT_USP_ROOT . 'data/security_logs';
    $log_dir_paths[] = CT_USP_ROOT . 'data/fw_logs';

    foreach ( $log_dir_paths as $log_dir_path ) {

        $log_files = array_diff( scandir( $log_dir_path ), array( '.', '..', 'index.php' ) );
        if( ! empty( $log_files ) ){
            foreach ( $log_files as $log_file ){
                unlink( $log_dir_path . DS . $log_file );
            }
        }
    }
}

/**
 *  Detecting CMS
 *
 * @param $path_to_index
 * @param string $out
 * @return array
 */
function usp_detect_cms($path_to_index, $out = array( 'name' => 'Unknown', 'admin_page' => '' ) ){
	
	if( is_file($path_to_index) ){
	
		// Detecting CMS
		$index_file = file_get_contents( $path_to_index );
		
		//X-Cart 4
		if (preg_match('/(xcart_4_.*?)/', $index_file))
			$out = array( 'name' => 'X-Cart 4', 'admin_page' => '' );
		//osTicket
		if (preg_match('/osticket/i', $index_file))
			$out = array( 'name' => 'osTicket', 'admin_page' => '' );
		// PrestaShop
		if (preg_match('/(PrestaShop.*?)/', $index_file))
			$out = array( 'name' => 'PrestaShop', 'admin_page' => '' );
		// Question2Answer
		if (preg_match('/(Question2Answer.*?)/', $index_file))
			$out = array( 'name' => 'Question2Answer', 'admin_page' => '' );
		// FormTools
		if (preg_match('/(use\sFormTools.*?)/', $index_file))
			$out = array( 'name' => 'FormTools', 'admin_page' => '' );
		// SimplaCMS
		if (preg_match('/(Simpla CMS.*?)/', $index_file))
			$out = array( 'name' => 'Simpla CMS', 'admin_page' => '' );
        // Joomla!
        if (preg_match('/(JOOMLA.*?)/i', $index_file))
            $out = array( 'name' => 'Joomla', 'admin_page' => '/administrator' );
        // Drupal 7
        if(preg_match('/(DRUPAL_ROOT.*?)/', $index_file))
            $out = array( 'name' => 'Drupal7', 'admin_page' => '' );
        // Drupal 8
        if(preg_match('/(DrupalKernel.*?)/', $index_file))
            $out = array( 'name' => 'Drupal8', 'admin_page' => '' );
        // Bitrix
        if(preg_match('/(bitrix.*?)/', $index_file))
            $out = array( 'name' => 'Bitrix', 'admin_page' => '/bitrix/admin' );
        // OpenCart
        if(
            file_exists( CT_USP_SITE_ROOT . '/system/modification.xml' ) &&
            preg_match( '/(OpenCart.*?)/', file_get_contents( CT_USP_SITE_ROOT . 'system/modification.xml' ) )
        ) {
            $out = array( 'name' => 'OpenCart', 'admin_page' => '/admin' );
        }
		if(preg_match('/(IN_PHPBB\',\strue)/', $index_file))
			$out = array( 'name' => 'phpBB', 'admin_page' => '/' );

	}
	
	return $out;

}

/**
 *  Login handler
 *  AJAX handler (returns json result)
 *
 * @param $apikey
 * @param $password
 * @param $email
 */
function usp_do_login($apikey, $password, $email ) {

    // Simple brute force protection
    sleep(2);

    // If password is set in config
    if( $password ){

        if( ( Post::get( 'login' ) == $apikey || Post::get( 'login' ) === $email ) && hash( 'sha256', trim( Post::get( 'password' ) ) ) == $password )
            setcookie('authentificated', State::getInstance()->data->security_key, 0, '/', null, false, true);
        else
            Err::add('Incorrect login or password');
        
    // No match
    }else
        Err::add('Incorrect login');

    Err::check() or die(json_encode(array('passed' => true)));
    die(Err::check_and_output( 'as_json' ));

}

/**
 * Logout handler
 * AJAX handler (returns json result)
 */
function usp_do_logout() {

	setcookie('authentificated', 0, time()-86400, '/', null, false, true);

    die( json_encode( array( 'success' => true ) ) );
}

/**
 * Save settings handler
 * AJAX handler (returns json result)
 */
function usp_do_save_settings() {

	$usp = State::getInstance();

	// Set missing settings.
	$settings =array();
	foreach($usp->default_settings as $setting => $value){
		$settings[$setting] = Post::get( $setting ) !== ''
			? Post::get( $setting, null, 'xss' )
			: $value;
		settype($settings[$setting], gettype($value));
	} unset($setting, $value);
	
	// Recognizing new key
	$new_key_is_set = $usp->settings->key !== $settings['key'];
	
	// Set values
	foreach ( $settings as $setting => $value) {
		$usp->settings->$setting = $value;
	} unset($setting, $value);

    // validate the new key
	$usp->data->key_is_ok = usp_check_account_status();
    
    // BFP actions
    if( $usp->settings->key ){

            // Send BFP logs
            $result = \Cleantalk\USP\Uniforce\Firewall\BFP::send_log( $usp->settings->key );
            if( empty( $result['error'] ) && ! Err::check() ) {
                $usp->data->stat->bfp->logs_sent_time = time();
                $usp->data->stat->bfp->logs_sent_amount = $result['rows'];
                $usp->data->stat->bfp->count = 0;
            }
    }
	
	if( $new_key_is_set ){
		$scanner_controller = new \Cleantalk\USP\ScannerController(
			CT_USP_SITE_ROOT
		);
		$scanner_controller->action__scanner__create_db();
	}
    
    // Update signatures
    if( $usp->settings->scanner_signature_analysis ){
	    $scanner_controller = new \Cleantalk\USP\ScannerController( CT_USP_SITE_ROOT );
	    $scanner_controller->action__scanner__get_signatures();
    }
	
	$usp->data->save();
	$usp->settings->save();
 
	// FireWall actions
	// Last in the list because it can overwrite the data in the the remote call it makes
	if( ( $usp->settings->fw || $usp->settings->waf ) && $usp->settings->key ){
		
		// Update SFW
		Helper::http__request(
			Server::get('HTTP_HOST') . CT_USP_AJAX_URI,
			array(
				'spbc_remote_call_token'  => md5( $usp->settings->key ),
				'spbc_remote_call_action' => 'update_security_firewall',
				'plugin_name'             => 'security',
				'file_urls'               => '',
			),
			'get async'
		);
		
		// Send FW logs
		$result = \Cleantalk\USP\Uniforce\Firewall\FW::send_log( $usp->settings->key );
		
		if( empty( $result['error'] ) && ! Err::check() ) {
			$usp->fw_stats->logs_sent_time = time();
			$usp->fw_stats->logs_sent_amount = $result['rows'];
			$usp->fw_stats->save();
		}
		
		// Cleaning up Firewall data
	} else {
		// Deleting FW data
		$db = new \Cleantalk\USP\File\FileDB( 'fw_nets' );
		$db->delete();
		State::getInstance()->data->save();
		Cron::removeTask( 'sfw_update' );
		Cron::removeTask( 'fw_send_logs' );
		usp_uninstall_logs();
	}

    Err::check() or die(json_encode(array('success' => true)));
    die(Err::check_and_output( 'as_json' ));

}

function usp_check_account_status( $key = null ){

	$usp = State::getInstance();

	$key = $key ? $key : $usp->settings->key;

	// validate the new key
	$result = API::method__notice_paid_till(
		$key,
		preg_replace( '/http[s]?:\/\//', '', Server::get( 'SERVER_NAME' ), 1 ),
		'security'
	);
	if( ! empty( $result['error'] ) ){
		Err::add('Checking key failed', $result['error']);
		$usp->data->notice_show     = 0;
		$usp->data->notice_renew    = 0;
		$usp->data->notice_trial    = 0;
		$usp->data->notice_review   = 0;
		$usp->data->user_token      = '';
		$usp->data->spam_count      = 0;
		$usp->data->moderate_ip     = 0;
		$usp->data->moderate        = 0;
		$usp->data->service_id      = 0;
		$usp->data->license_trial   = 0;
		$usp->data->account_name = '';
		$usp->data->account_name_ob = '';
		$usp->data->ip_license      = 0;
		$usp->data->valid           = 0;
		// $usp->data->notice_were_updated = $result[''];
	} else {
		$usp->data->notice_show     = isset( $result['show_notice'] ) ? $result['show_notice'] : 0;
		$usp->data->notice_renew    = isset( $result['renew'] ) ? $result['renew'] : 0;
		$usp->data->notice_trial    = isset( $result['trial'] ) ? $result['trial'] : 0;
		$usp->data->notice_review   = isset( $result['show_review'] ) ? $result['show_review'] : 0;
		$usp->data->user_token      = isset( $result['user_token'] ) ? $result['user_token'] : '';
		$usp->data->spam_count      = isset( $result['spam_count'] ) ? $result['spam_count'] : 0;
		$usp->data->moderate_ip     = isset( $result['moderate_ip'] ) ? $result['moderate_ip'] : 0;
		$usp->data->moderate        = isset( $result['moderate'] ) ? $result['moderate'] : 0;
		$usp->data->service_id      = isset( $result['service_id'] ) ? $result['service_id'] : 0;
		$usp->data->license_trial   = isset( $result['license_trial'] ) ? $result['license_trial'] : 0;
		$usp->data->account_name_ob = isset( $result['account_name_ob'] ) ? $result['account_name_ob'] : '';
		$usp->data->ip_license      = isset( $result['ip_license'] ) ? $result['ip_license'] : 0;
		$usp->data->valid           = isset( $result['valid'] ) ? $result['valid'] : 0;
		// $usp->data->notice_were_updated = $result[''];
	}

	$usp->data->save();
	$usp->settings->save();

	return $usp->valid;
}

/**
 * Uninstall handler
 * AJAX handler (returns json result)
 */
function usp_do_uninstall() {

	setcookie('authentificated', 0, time()-86400, '/', null, false, true);

    usp_uninstall();

    Err::check() or die(json_encode(array('success' => true)));
    die(Err::check_and_output( 'as_json' ));
}

/**
 * AJAX handler for the changing admin password logic
 *
 * @return string json
 */
function usp_do_change_admin_password()
{
    $usp = State::getInstance();

    // Changing password logic
    // 1 if the fields not empty
    if ( Post::get('old_password') && Post::get('new_password') && Post::get('new_password_confirm') ) {

        // 2 if the old password is right
        if ( $usp->data->password !== hash( 'sha256', trim(Post::get('old_password'))) ) {
            Err::add('Changing admin password', 'The old password is wrong');
            die(Err::check_and_output( 'as_json' ));
        }

        // 3 if the password is too short
        if ( strlen(Post::get('new_password')) < 8 ) {
            Err::add('Changing admin password', 'Password must be more than 8 characters');
            die(Err::check_and_output( 'as_json' ));
        }

        // 4 if the new password confirmed
        if ( Post::get('new_password') !== Post::get('new_password_confirm') ) {
            Err::add('Changing admin password', 'New password is not confirmed');
            die(Err::check_and_output( 'as_json' ));
        }

        // 5 save the new password
        $usp->data->password = hash('sha256', trim(Post::get('new_password')));
        $usp->data->save();

        usp_send_pass_to_email($usp->data->email, $usp->data->email, Post::get('new_password'));

    } else {
        Err::add('Changing admin password', 'All fields are required');
    }

    Err::check() or die(json_encode(array('success' => true)));
    die(Err::check_and_output( 'as_json' ));
}
