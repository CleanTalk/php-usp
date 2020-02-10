<?php
/**
 * Attaches
 * /config.php
 * /lib/autoloader.php
 *
 * Sets all main constants
 */

use Cleantalk\Uniforce\Cron;
use Cleantalk\Common\Err;
use Cleantalk\Variables\Server;

define( 'DS', DIRECTORY_SEPARATOR );

// Directories
define( 'CT_USP_INC', realpath(__DIR__ ) . DS );
define( 'CT_USP_ROOT', realpath( CT_USP_INC . '..') . DS );
define( 'CT_USP_SITE_ROOT', realpath( CT_USP_ROOT . '..') . DS );
define( 'CT_USP_LIB', CT_USP_ROOT . 'lib' . DS );
define( 'CT_USP_VIEW', CT_USP_INC . 'pages' . DS );

// Files
define( 'CT_USP_CONFIG_FILE', CT_USP_ROOT . 'config.php' );
define( 'CT_USP_CRON_FILE', CT_USP_ROOT . 'data' . DS . 'cron_data.php' );

require_once CT_USP_LIB . 'autoloader.php';
require_once CT_USP_ROOT . 'config.php';

// URI
define( 'CT_USP_URI', preg_replace( '/^(.*\/)(.*?.php)?/', '$1',  Server::get('REQUEST_URI') ) );

// Create empty error object
Err::getInstance();

// Run scheduled tasks
$cron = new Cron();
$cron->checkTasks();
if( ! empty( $cron->tasks_to_run ) )
	require_once CT_USP_INC . 'cron_functions.php'; // File with cron wrappers
	$cron->runTasks();
unset( $cron );