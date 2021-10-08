<?php

use Cleantalk\USP\Common\RemoteCalls;
use Cleantalk\USP\Common\State;
use Cleantalk\USP\File\FileStorage;
use Cleantalk\USP\Uniforce\Firewall\BFP;
use Cleantalk\USP\Uniforce\Firewall\FW;

define( 'USP_DASHBOARD', true );

require_once 'inc' . DIRECTORY_SEPARATOR . 'common.php';  // Common stuff

require_once 'uniforce.php';

require_once 'inc' . DIRECTORY_SEPARATOR . 'actions.php'; // Actions

//$result = FW::update( State::getInstance()->key );

$data = [
    [ 'network' => '1',  'mask' => '9', 'status' => '1', 'is_personal' => 0 ],
    [ 'network' => '2',  'mask' => '9', 'status' => '1', 'is_personal' => 0 ],
    [ 'network' => '3',  'mask' => '9', 'status' => '1', 'is_personal' => 0 ],
    [ 'network' => '4',  'mask' => '9', 'status' => '1', 'is_personal' => 0 ],
    [ 'network' => '5',  'mask' => '9', 'status' => '1', 'is_personal' => 0 ],
    [ 'network' => '6',  'mask' => '9', 'status' => '1', 'is_personal' => 0 ],
    [ 'network' => '7',  'mask' => '9', 'status' => '1', 'is_personal' => 0 ],
    [ 'network' => '8',  'mask' => '9', 'status' => '1', 'is_personal' => 0 ],
    [ 'network' => '9',  'mask' => '9', 'status' => '1', 'is_personal' => 0 ],
    [ 'network' => '10', 'mask' => '9', 'status' => '1', 'is_personal' => 0 ],
];

$db = new \Cleantalk\USP\File\FileDB( 'test' );

if( \Cleantalk\USP\Variables\Request::get( 'db_action' ) === 'delete' ){
    $db->delete();
}

if( \Cleantalk\USP\Variables\Request::get( 'db_action' ) === 'insert' ){
    $db->insert( $data );
}

if(
    \Cleantalk\USP\Variables\Request::get( 'db_action' ) === 'get' &&
    \Cleantalk\USP\Variables\Request::is_set( 'needles'  )
){
    $needles = explode( ',', \Cleantalk\USP\Variables\Request::get( 'needles' ) );
    $db_results = $db
        ->set_columns( 'network', 'mask', 'status', 'is_personal' )
        ->set_where( array( 'network' => $needles, ) )
        ->set_limit( 0, 20 )
        ->select();
}

die();

// URL ROUTING
switch (true){
	// Installation
	case \Cleantalk\USP\Common\State::getInstance()->data->is_installed === false:
		$page = 'install';
		break;
	// Login
	case \Cleantalk\USP\Variables\Cookie::get('authentificated') !== \Cleantalk\USP\Common\State::getInstance()->data->security_key:
        $additional_js = array(
            'ct_js_test',
        );
	    $page = 'login';
        break;
    // Settings
    case \Cleantalk\USP\Variables\Cookie::get('authentificated') === \Cleantalk\USP\Common\State::getInstance()->data->security_key:
	    $additional_js = array(
	    	'scanner-plugin',
	    	'scanner',
		    'table',
		    'https://cdn.polyfill.io/v1/polyfill.js?features=es6',
	    );
	    $additional_css = array(
		    'settings-scanner',
	        'settings-table',
		    'jquery-ui.min',
	    );
	    $page = 'settings';
        break;
}

// Common script for all pages
require_once CT_USP_VIEW . 'header.php';

	// Page content
	require_once CT_USP_VIEW . $page . '.php';

// Footer
require_once CT_USP_VIEW . 'footer.php';
