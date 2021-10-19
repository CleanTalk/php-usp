<?php

define( 'USP_DASHBOARD', true );

require_once 'inc' . DIRECTORY_SEPARATOR . 'common.php';  // Common stuff

require_once 'uniforce.php';

require_once 'inc' . DIRECTORY_SEPARATOR . 'actions.php'; // Actions

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
