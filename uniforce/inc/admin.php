<?php

use Cleantalk\Common\API;
use Cleantalk\Common\Err;
use Cleantalk\Common\File;
use Cleantalk\Uniforce\Cron;
use Cleantalk\Uniforce\FireWall;
use Cleantalk\Variables\Post;
use Cleantalk\Variables\Server;

/**
 * Method notice_paid_till
 *
 * AJAX handler (returns json result)
 */
function uniforce_key_validate() {

    $result = API::method__notice_paid_till(
        Post::get( 'key' ),
        preg_replace( '/http[s]?:\/\//', '', Server::get( 'SERVER_NAME' ), 1 ),
        'security'
    );

    // $result['error'] = 'some';
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
function uniforce_get_key() {

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
function uniforce_do_install() {

    // Parsing key
    if( preg_match( '/^[a-z0-9]{1,20}$/', Post::get( 'key' ), $matches ) ){

        $api_key = $matches[0];
        $cms     = uniforce_detect_cms( CLEANTALK_SITE_ROOT . 'index.php' );

        $files_to_mod = array();

        // Add index.php to files for modification if exists
        $files_to_mod[] = 'index.php';

        // JOOMLA ONLY: Add administrator/index.php to files for modification if exists
        if( $cms['name'] == 'Joomla' ) {
            $files_to_mod[] = 'administrator/index.php';
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

                $file = CLEANTALK_SITE_ROOT . trim( $file_to_mod );
                if( file_exists($file) )
                    $tmp[] = $file;
            }
            $files_to_mod = $tmp;
        }

        Err::check() && die( Err::get_last( 'as_json' ) );

        if( !empty($files_to_mod) ){

            // Determine file to install Cleantalk script
            $exclusions = array();

            uniforce_install( $files_to_mod, $api_key, $cms, $exclusions );

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
function uniforce_install($files, $api_key, $cms, $exclusions ){
	
	foreach ($files as $file){
		
		$file_content = file_get_contents( $file );
		$php_open_tags  = preg_match_all("/(<\?)/", $file_content);
		$php_close_tags = preg_match_all("/(\?>)/", $file_content);
		$first_php_start = strpos($file_content, '<?');
		// Adding <?php to the start if it's not there
		if($first_php_start !== 0)
			File::inject__code($file, "<?php\n?>\n", 'start');
		
		if( ! Err::check() ){
			
			// Adding ? > to the end if it's not there
			if($php_open_tags <= $php_close_tags)
				File::inject__code($file, "\n<?php\n", 'end');
			
			if( ! Err::check() ){

				// Addition to the top of the script
				File::inject__code(
					$file,
					"\trequire_once( '" . CLEANTALK_SITE_ROOT . "uniforce/uniforce.php');",
					'(<\?php)|(<\?)',
					'top_code'
				);
				
				if( ! Err::check() ){
					
					// Addition to index.php Bottom (JavaScript test)
					File::inject__code(
						$file,
						"\tob_end_flush();\n"
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
	
	// Clean config
	if( ! Err::check() )
        uniforce_uninstall();
	
	// Install settings in cofig if everything is ok
	if( ! Err::check() )
        uniforce_install_config( $files, $api_key, $cms, $exclusions );
	
	// Set cron tasks
	if( ! Err::check() )
        uniforce_install_cron();
}

/**
 * Modify config
 *
 * @param $modified_files
 * @param $api_key
 * @param $cms
 * @param $exclusions
 */
function uniforce_install_config($modified_files, $api_key, $cms, $exclusions ){
	
	$path_to_config = CLEANTALK_ROOT . 'config.php';
	$salt = str_pad(rand(0, getrandmax()), 6, '0').str_pad(rand(0, getrandmax()), 6, '0');
	// Attention. Backwards order because inserting it step by step
	
	if( Post::get( 'admin_password' ) )
		File::inject__variable( $path_to_config, 'uniforce_password', hash( 'sha256', trim( Post::get( 'admin_password' ) ) ) );
	if( Post::get( 'email' ) )
		File::inject__variable( $path_to_config, 'uniforce_email', trim( Post::get( 'email' ) ) );
	if( Post::get( 'user_token' ) )
		File::inject__variable( $path_to_config, 'uniforce_user_token', trim( Post::get( 'user_token' ) ) );
	if( Post::get( 'account_name_ob' ) )
		File::inject__variable( $path_to_config, 'uniforce_account_name_ob', trim( Post::get( 'account_name_ob' ) ) );
	File::inject__variable( $path_to_config, 'uniforce_salt', $salt );
	File::inject__variable( $path_to_config, 'uniforce_security', hash( 'sha256', '~(o_O)~' . $salt ) );
	File::inject__variable( $path_to_config, 'uniforce_modified_files', $modified_files, true );
	if( $exclusions )
		File::inject__variable( $path_to_config, 'uniforce_exclusions', $exclusions, true );
	File::inject__variable( $path_to_config, 'uniforce_apikey', $api_key );
	File::inject__variable( $path_to_config, 'uniforce_detected_cms', $cms['name'] );
    File::inject__variable( $path_to_config, 'uniforce_cms_admin_page', $cms['admin_page'] );
	File::inject__variable( $path_to_config, 'uniforce_is_installed', true );

}

/**
 * Modify cron
 */
function uniforce_install_cron(){

	Cron::addTask( 'sfw_update', 'uniforce_sfw_update', 86400, time() + 60 );
	Cron::addTask( 'sfw_send_logs', 'uniforce_sfw_send_logs', 3600 );
    Cron::addTask( 'waf_send_logs', 'uniforce_waf_send_logs', 3600 );
    Cron::addTask( 'bfp_send_logs', 'uniforce_bfp_send_logs', 3600 );

}

/**
 *  Uninstall
 *
 * @param array $files
 * @return bool
 */
function uniforce_uninstall($files = array() ){
	
	global $uniforce_modified_files;
	
	// Clean files from config.php
	$files = empty($files) && isset($uniforce_modified_files)
		? $uniforce_modified_files
		: $files;
	
	$path_to_config = CLEANTALK_ROOT . 'config.php';
	File::clean__variable( $path_to_config, 'uniforce_security' );
	File::clean__variable( $path_to_config, 'uniforce_password' );
	File::clean__variable( $path_to_config, 'uniforce_salt' );
	File::clean__variable( $path_to_config, 'uniforce_apikey' );
	File::clean__variable( $path_to_config, 'uniforce_email' );
	File::clean__variable( $path_to_config, 'uniforce_user_token' );
	File::clean__variable( $path_to_config, 'uniforce_account_name_ob' );
	File::clean__variable( $path_to_config, 'uniforce_detected_cms' );
    File::clean__variable( $path_to_config, 'uniforce_cms_admin_page' );
	File::clean__variable( $path_to_config, 'uniforce_admin_password' );
	File::clean__variable( $path_to_config, 'uniforce_modified_files' );
	File::clean__variable( $path_to_config, 'uniforce_exclusions' );
	File::clean__variable( $path_to_config, 'uniforce_is_installed' );
	
	// Restore deafult settings
	File::replace__variable( $path_to_config, 'uniforce_sfw_last_update', 0 );
	File::replace__variable( $path_to_config, 'uniforce_sfw_last_logs_send', 0 );
	File::replace__variable( $path_to_config, 'uniforce_sfw_entries', 0 );
	File::replace__variable( $path_to_config, 'uniforce_sfw_protection', true );
	File::replace__variable( $path_to_config, 'uniforce_waf_protection', true );
	File::replace__variable( $path_to_config, 'uniforce_bfp_protection', true );
	
	// Deleting cron tasks
	File::replace__variable( CLEANTALK_CRON_FILE, 'uniforce_tasks', array() );
	
	// Deleting SFW nets
	File::clean_file_full( CLEANTALK_ROOT . 'data' . DS . 'sfw_nets.php' );

	// Deleting any logs
    uniforce_uninstall_logs();
	
	if(isset($files)){
		foreach ( $files as $file ){
			File::clean__tag( $file, 'top_code' );
			File::clean__tag( $file, 'bottom_code' );
		}
	}
	
	return ! Err::check();

}

/**
 * Unlink any logs files from the system
 */
function uniforce_uninstall_logs() {

    $log_dir_paths = array();
    $log_dir_paths[] = CLEANTALK_ROOT . 'data/sfw_logs';

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
function uniforce_detect_cms($path_to_index, $out = array( 'name' => 'Unknown', 'admin_page' => '' ) ){
	
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
function uniforce_do_login($apikey, $password, $email ) {

    // Simple brute force protection
    sleep(2);

    session_start();

    // If password is set in config
    if(isset($password)){
        if( ( Post::get( 'login' ) == $apikey || ( isset( $email ) && Post::get( 'login' ) == $email ) ) && hash( 'sha256', trim( Post::get( 'password' ) ) ) == $password ){
            $_SESSION['authenticated'] = 'true';
        }else
            Err::add('Incorrect login or password');

    // No password is set. Check only login.
    }elseif( ( Post::get( 'login' ) == $apikey ) ){
        $_SESSION['authenticated'] = 'true';

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
function uniforce_do_logout() {

    session_start();
    session_destroy();
    unset($_SESSION['authenticated']);
    die( json_encode( array( 'success' => true ) ) );

}

/**
 * Save settings handler
 * AJAX handler (returns json result)
 */
function uniforce_do_save_settings() {

    $path_to_config = CLEANTALK_ROOT . 'config.php';

    File::replace__variable( $path_to_config, 'uniforce_apikey', Post::get( 'apikey' ) );
    File::replace__variable( $path_to_config, 'uniforce_sfw_protection', (bool)Post::get( 'uniforce_sfw_protection' ) );
    File::replace__variable( $path_to_config, 'uniforce_waf_protection', (bool)Post::get( 'uniforce_waf_protection' ) );
    File::replace__variable( $path_to_config, 'uniforce_bfp_protection', (bool)Post::get( 'uniforce_bfp_protection' ) );
    File::replace__variable( $path_to_config, 'uniforce_cms_admin_page', Post::get( 'uniforce_bfp_protection_url' ) );

    // SFW actions
    if( Post::get( 'uniforce_sfw_protection' ) && Post::get( 'apikey' ) ){
        if( Post::get( 'uniforce_sfw_protection' ) ) {

            // Update SFW
            $result = FireWall::sfw_update( Post::get( 'apikey' ) );
            if( ! Err::check() ){
                File::replace__variable( $path_to_config, 'uniforce_sfw_last_update', time() );
                File::replace__variable( $path_to_config, 'uniforce_sfw_entries', $result );
            }

            // Send SFW logs
            $result = FireWall::logs__send( Post::get( 'apikey' ), 'sfw_logs' );
            if( empty( $result['error'] ) && ! Err::check() ) {
                File::replace__variable( $path_to_config, 'uniforce_sfw_last_logs_send', time() );
            }

        } else {
            // @ToDO replace variables to default, remove Cron tasks, clean data files
        }

    }

    // WAF actions
    if( Post::get( 'uniforce_waf_protection' ) && Post::get( 'apikey' ) ){
        if( Post::get( 'uniforce_waf_protection' ) ) {

            // Send WAF logs
            $result = FireWall::logs__send( Post::get( 'apikey' ), 'waf_logs' );
            if( empty( $result['error'] ) && ! Err::check() ) {
                File::replace__variable( $path_to_config, 'uniforce_waf_last_logs_send', time() );
                File::replace__variable( $path_to_config, 'uniforce_waf_trigger_count', 0 );
            }

        } else {
            // @ToDO replace variables to default, remove Cron tasks, clean data files
        }

    }

    // BFP actions
    if( Post::get( 'uniforce_bfp_protection' ) && Post::get( 'apikey' ) ){
        if( Post::get( 'uniforce_bfp_protection' ) ) {

            // Send BFP logs
            $result = FireWall::logs__send( Post::get( 'apikey' ), 'bfp_logs' );
            if( empty( $result['error'] ) && ! Err::check() ) {
                File::replace__variable( $path_to_config, 'uniforce_bfp_last_logs_send', time() );
                File::replace__variable( $path_to_config, 'uniforce_bfp_trigger_count', 0 );
            }

        } else {
            // @ToDO replace variables to default, remove Cron tasks, clean data files
        }

    }

    Err::check() or die(json_encode(array('success' => true)));
    die(Err::check_and_output( 'as_json' ));

}

/**
 * Uninstall handler
 * AJAX handler (returns json result)
 */
function uniforce_do_uninstall() {

        session_start();
        session_destroy();
        unset($_SESSION['authenticated']);
        uniforce_uninstall();

        Err::check() or die(json_encode(array('success' => true)));

        die(Err::check_and_output( 'as_json' ));

}