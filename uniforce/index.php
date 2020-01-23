<?php

use Cleantalk\Common\Err;
use Cleantalk\Variables\Post;
use Cleantalk\Variables\Server;

// Check requirements
if( version_compare( phpversion(), '5.6', '<' ) ) {
?>
    <h4 class="text-center">PHP version is <?php echo phpversion(); ?></h4>
    <h4 class="text-center">Universal Security Plugin by CleanTalk requires version 5.6 or higher.</h4>
    <h4 class="text-center">Please, contact your hosting provider to update it.</h4>
<?php
    die();
}

require_once 'inc' . DIRECTORY_SEPARATOR . 'common.php';

// ACTIONS ROUTING
if( Post::get( 'action' ) != '' && Post::get( 'security' ) != '' ) {

    require_once 'inc' . DIRECTORY_SEPARATOR . 'admin.php';

    if( Post::get( 'security' ) === md5( Server::get( 'SERVER_NAME' ) ) ) {

        switch( Post::get( 'action' ) ) {

            case 'key_validate' :
                uniforce_key_validate();
                break;

            case 'get_key' :
                uniforce_get_key();
                break;

            case 'install' :
                uniforce_do_install();
                break;

            default:
                die(Err::add('Unknown action')->get_last( 'as_json' ));
                break;

        }

    } elseif ( isset( $uniforce_security ) && Post::get( 'security' ) === $uniforce_security ) {

        switch( Post::get( 'action' ) ) {

            case 'login':
                $uniforce_apikey   = isset( $uniforce_apikey )   ? $uniforce_apikey   : null;
                $uniforce_password = isset( $uniforce_password ) ? $uniforce_password : null;
                $uniforce_email    = isset( $uniforce_email )    ? $uniforce_email    : null;
                uniforce_do_login( $uniforce_apikey, $uniforce_password, $uniforce_email );
                break;

            case 'logout':
                uniforce_do_logout();
                break;

            case 'save_settings':
                uniforce_do_save_settings();
                break;

            case 'uninstall':
                uniforce_do_uninstall();
                break;

            default:
                die(Err::add('Unknown action')->get_last( 'as_json' ));
                break;

        }

    } else {

        Err::add('Forbidden');

    }

}

// URL ROUTING
session_start();
require_once CLEANTALK_VIEW . 'header.php';

if( empty( $uniforce_is_installed ) ) {

    require_once CLEANTALK_VIEW . 'install.php';

} else {

    require_once CLEANTALK_VIEW . 'settings.php';

}

require_once CLEANTALK_VIEW . 'footer.php';
