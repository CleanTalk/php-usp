<?php

use Cleantalk\USP\Common\Err;
use Cleantalk\USP\Common\State;
use Cleantalk\USP\Variables\Post;
use Cleantalk\USP\Variables\Server;

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

			case 'logout':
				usp_do_logout();
				break;

			case 'save_settings':
				usp_do_save_settings();
				break;

			case 'uninstall':
				usp_do_uninstall();
				break;

            case 'change_admin_password' :
                usp_do_change_admin_password();
                break;

			case 'spbc_tbl-action--row':
				call_user_func( '\Cleantalk\USP\Layout\ListTable::ajax__row_action_handler' );
				break;

			case 'spbc_tbl-pagination':
				call_user_func( '\Cleantalk\USP\Layout\ListTable::ajax__pagination_handler' );
				break;

			case 'spbc_scanner_file_view':
				require_once CT_USP_INC . 'scanner.php';
				State::getInstance()->data->no_sql ? spbc_scanner_file_view___no_sql() : spbc_scanner_file_view();
				break;

			case 'update':
				$updater = new \Cleantalk\USP\Updater\Updater( CT_USP_ROOT );
				$result = $updater->update(
					State::getInstance()->plugin_meta->version,
					State::getInstance()->plugin_meta->latest_version
				);
				if( empty( $result['error'] ) ){
					State::getInstance()->plugin_meta->previous_version = State::getInstance()->plugin_meta->version;
					State::getInstance()->plugin_meta->version          = State::getInstance()->plugin_meta->latest_version;
					State::getInstance()->plugin_meta->save();
				}
				die(json_encode( $result, true ));
				break;
				
			default:
				die(Err::add('Unknown action')->get_last( 'as_json' ));
				break;

		}
	}elseif ( Post::get( 'security' ) === 'login'){

		if ( Post::get( 'action' ) === 'login' ) {
			require_once CT_USP_ROOT . 'uniforce.php';
			usp_do_login(
				State::getInstance()->settings->key,
				State::getInstance()->data->password,
				State::getInstance()->data->email
			);
		}

	} else {
		Err::add('Forbidden');
	}

}
