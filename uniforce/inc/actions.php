<?php

use Cleantalk\Common\Err;
use Cleantalk\Common\State;
use Cleantalk\Variables\Post;
use Cleantalk\Variables\Server;

// ACTIONS ROUTING
if( Post::is_set('action', 'security') ) {

	require_once 'inc' . DIRECTORY_SEPARATOR . 'admin.php';

	if( Post::get( 'security' ) === md5( Server::get( 'SERVER_NAME' ) ) ) {

		switch( Post::get( 'action' ) ) {

			case 'key_validate' :
				usp_key_validate();
				break;

			case 'get_key' :
				usp_get_key();
				break;

			case 'install' :
				usp_do_install();
				break;

			default:
				die(Err::add('Unknown action')->get_last( 'as_json' ));
				break;

		}

	} elseif ( Post::get( 'security' ) === State::getInstance()->security_key ) {

		switch( Post::get( 'action' ) ) {

			case 'login':
				usp_do_login(
					State::getInstance()->settings->key,
					State::getInstance()->data->password,
					State::getInstance()->data->email
				);
				break;

			case 'logout':
				usp_do_logout();
				break;

			case 'save_settings':
				usp_do_save_settings();
				break;

			case 'uninstall':
				usp_do_uninstall();
				break;

			default:
				die(Err::add('Unknown action')->get_last( 'as_json' ));
				break;

		}

	} else {
		Err::add('Forbidden');
	}

}
