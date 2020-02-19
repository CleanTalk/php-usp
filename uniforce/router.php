<?php

use Cleantalk\Common\RemoteCalls;

require_once 'inc' . DIRECTORY_SEPARATOR . 'common.php';  // Common stuff
require_once 'inc' . DIRECTORY_SEPARATOR . 'actions.php'; // Actions

// URL ROUTING
switch (true){
	// Installation
	case empty( \Cleantalk\Common\State::getInstance()->data->is_installed ):
		$page = 'install';
		break;
	// Login
	case \Cleantalk\Variables\Cookie::get('authenticated') !== 'true':
		$page = 'login';
        break;
    // Settings
    case \Cleantalk\Variables\Cookie::get('authenticated') === 'true':
	    $additional_js = array(
	    	'scanner-plugin',
	    	'scanner',
		    'table',
	    );
	    $additional_css = array(
		    'settings-scanner',
	        'settings-table',
		    'jquery-ui.min'
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