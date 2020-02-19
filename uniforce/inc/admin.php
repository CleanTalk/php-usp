<?php

use Cleantalk\Common\API;
use Cleantalk\Common\Err;
use Cleantalk\Common\File;
use Cleantalk\Common\State;
use Cleantalk\Uniforce\Cron;
use Cleantalk\Uniforce\FireWall;
use Cleantalk\Variables\Post;
use Cleantalk\Variables\Server;

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

    $result['email'] = Post::get( 'email' );

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
						"\tnob_end_flush();\n"
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

	if( Post::get( 'admin_password' ) )
		$usp->data->password = hash( 'sha256', trim( Post::get( 'admin_password' ) ) );

	if( Post::get( 'email' ) )
		$usp->data->email = trim( Post::get( 'email' ) );

	if( Post::get( 'user_token' ) )
		$usp->data->user_token = trim( Post::get( 'user_token' ) );

	if( Post::get( 'account_name_ob' ) )
		$usp->data->account_name_ob =  trim( Post::get( 'account_name_ob' ) );

	$usp->data->security_key = hash( 'sha256', '~(o_O)~' . $usp->key . $usp->data->salt );
	$usp->data->modified_files = $modified_files;
	$usp->data->detected_cms = $cms['name'];
	$usp->data->is_installed  = true;

    $usp->settings->bfp_admin_page =  $cms['admin_page'];
	$usp->settings->key  = $api_key;

	$usp->data->save();
	$usp->settings->save();

}

/**
 * Modify cron
 */
function usp_install_cron(){

	Cron::addTask( 'sfw_update', 'uniforce_sfw_update', 86400, time() + 60 );
	Cron::addTask( 'security_send_logs', 'uniforce_security_send_logs', 3600 );
    Cron::addTask( 'fw_send_logs', 'uniforce_fw_send_logs', 3600 );
    Cron::addTask( 'clean_black_lists', 'uniforce_clean_black_lists', 86400 );
    Cron::addTask( 'update_signatures', 'usp_scanner__get_signatures', 86400 );

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

	// Deleting options and their files
	$usp->deleteOption( 'data' );
	$usp->deleteOption( 'settings' );
	$usp->deleteOption( 'remote_calls' );

	// Deleting cron tasks
	File::replace__variable( CT_USP_CRON_FILE, 'uniforce_tasks', array() );

	// Deleting SFW nets
	File::clean_file_full( CT_USP_ROOT . 'data' . DS . 'sfw_nets.php' );

	// Deleting any logs
    usp_uninstall_logs();

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

    session_start();

    // If password is set in config
    if( $password ){

        if( ( Post::get( 'login' ) == $apikey || Post::get( 'login' ) === $email ) && hash( 'sha256', trim( Post::get( 'password' ) ) ) == $password )
            setcookie('authentificated', State::getInstance()->data->security_key, 0, '/', null, false, true);
        else
            Err::add('Incorrect login or password');

    // No password is set. Check only login (access key).
    }elseif( Post::get( 'login' ) == $apikey ){
	    setcookie('authentificated', State::getInstance()->data->security_key, 0, '/', null, false, true);

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

	$usp->settings->key = Post::get( 'apikey' );

    // validate the new key
	usp_check_account_status();

	$usp->settings->fw             = (bool) Post::get( 'uniforce_sfw_protection' );
	$usp->settings->waf            = (bool) Post::get( 'uniforce_waf_protection' );
	$usp->settings->bfp            = (bool) Post::get( 'uniforce_bfp_protection' );
	$usp->settings->bfp_admin_page = Post::get( 'uniforce_bfp_protection_url' );

    // FireWall actions
    if( ( $usp->settings->fw || $usp->settings->waf ) && $usp->settings->key ){

            // Update SFW
            $result = FireWall::sfw_update( Post::get( 'apikey' ) );
            if( ! Err::check() ){
                $usp->data->stat->fw->last_update = time();
                $usp->data->stat->fw->entries = $result;
            }

            // Send FW logs
            $result = FireWall::logs__send( Post::get( 'apikey' ) );
            if( empty( $result['error'] ) && ! Err::check() ) {
                $usp->data->stat->fw->logs_sent_time = time();
                $usp->data->stat->fw->logs_sent_amount = $result['rows'];
            }

    // Cleaning up Firewall data
    } else {
	    File::clean_file_full( CT_USP_ROOT . 'data' . DS . 'sfw_nets.php' );
	    Cron::removeTask( 'sfw_update' );
	    Cron::removeTask( 'fw_send_logs' );
	    usp_uninstall_logs();
    }


    // BFP actions
    if( Post::get( 'uniforce_bfp_protection' ) && Post::get( 'apikey' ) ){
        if( Post::get( 'uniforce_bfp_protection' ) ) {

            // Send BFP logs
            $result = FireWall::security__logs__send( Post::get( 'apikey' ) );
            if( empty( $result['error'] ) && ! Err::check() ) {
                $usp->data->stat->bf->logs_sent_time = $result['rows'];
                $usp->data->stat->bf->logs_sent_amount = time();
                $usp->data->stat->bf->count = 0;
            }

        // Cleaning up Bruteforce protection data
        } else {
	        usp_uninstall_logs();
	        Cron::removeTask( 'fw_send_logs' );
	        Cron::removeTask( 'fw_send_logs' );
        }

    }

    $usp->data->save();
    $usp->settings->save();

    Err::check() or die(json_encode(array('success' => true)));
    die(Err::check_and_output( 'as_json' ));

}

function usp_check_account_status( $key = null ){

	$usp = State::getInstance();

	$key = $key ? $key : $usp->settings->key;

	// validate the new key
	$result = API::method__notice_paid_till(
		Post::get( 'apikey' ),
		preg_replace( '/http[s]?:\/\//', '', Server::get( 'SERVER_NAME' ), 1 ),
		'security'
	);
	if( ! empty( $result['error'] ) ){
		Err::add('Checking key failed', $result['error']);
		$usp->settings->key = Post::get( 'apikey' );
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
		$usp->data->account_name_ob = '';
		$usp->data->ip_license      = 0;
		$usp->data->valid           = 0;
		// $usp->data->notice_were_updated = $result[''];
	} else {
		$usp->settings->key = Post::get( 'apikey' );
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