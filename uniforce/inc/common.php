<?php
/**
 * Attaches
 * /lib/autoloader.php
 *
 * Sets all main constants
 */

use Cleantalk\Uniforce\Cron;
use Cleantalk\Common\Err;
use Cleantalk\Variables\Server;

if( ! defined( 'SPBCT_PLUGIN' ) )     define( 'SPBCT_PLUGIN', 'uniforce' );
if( ! defined( 'SPBCT_VERSION' ) )    define( 'SPBCT_VERSION', '1.0' );
if( ! defined( 'SPBCT_AGENT' ) )      define( 'SPBCT_AGENT', SPBCT_PLUGIN . '-' . str_replace( '.', '', SPBCT_VERSION ) );
if( ! defined( 'SPBCT_USER_AGENT' ) ) define( 'SPBCT_USER_AGENT', 'Cleantalk-Security-Universal-Plugin/' . SPBCT_VERSION );

define( 'DS', DIRECTORY_SEPARATOR );

// Directories
define( 'CT_USP_INC', realpath(__DIR__ ) . DS );
define( 'CT_USP_ROOT', realpath( CT_USP_INC . '..') . DS );
define( 'CT_USP_SITE_ROOT', realpath( CT_USP_ROOT . '..') . DS );
define( 'CT_USP_LIB', CT_USP_ROOT . 'lib' . DS );
define( 'CT_USP_VIEW', CT_USP_INC . 'pages' . DS );
define( 'CT_USP_DATA', CT_USP_ROOT . 'data' . DS );

// Files
define( 'CT_USP_CRON_FILE', CT_USP_ROOT . 'data' . DS . 'cron_data.php' );

require_once CT_USP_LIB . 'autoloader.php';

// URI
define( 'CT_USP_URI',      preg_replace( '/^(.*\/)(.*?.php)?/', '$1',  Server::get('REQUEST_URI') ) );
define( 'CT_USP_AJAX_URI', preg_replace( '/^(.*\/)(.*?.php)?/', '$1router.php',  Server::get('REQUEST_URI') ) );

// Create empty error object
Err::getInstance();

// Load settings, data and remote calls
$usp = new \Cleantalk\Common\State( 'settings', 'data', 'remote_calls' );
$usp->key = $usp->settings->key;
$usp->check_js = md5($usp->key);

//$usp->settings->save();
//$usp->data->save();

// Run scheduled tasks
$cron = new Cron();
$cron->checkTasks();
if( ! empty( $cron->tasks_to_run ) )
	require_once CT_USP_INC . 'cron_functions.php'; // File with cron wrappers
	$cron->runTasks();
unset( $cron );