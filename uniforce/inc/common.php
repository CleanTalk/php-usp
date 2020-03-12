<?php
/**
 * Attaches
 * /lib/autoloader.php
 *
 * Sets all main constants
 */

use Cleantalk\Variables\Server;

if( ! defined( 'SPBCT_PLUGIN' ) )     define( 'SPBCT_PLUGIN', 'uniforce' );
if( ! defined( 'SPBCT_VERSION' ) )    define( 'SPBCT_VERSION', '2.0' );
if( ! defined( 'SPBCT_AGENT' ) )      define( 'SPBCT_AGENT', SPBCT_PLUGIN . '-' . str_replace( '.', '', SPBCT_VERSION ) );
if( ! defined( 'SPBCT_USER_AGENT' ) ) define( 'SPBCT_USER_AGENT', 'Cleantalk-Security-Universal-Plugin/' . SPBCT_VERSION );

define( 'DS', DIRECTORY_SEPARATOR );

// Directories
define( 'CT_USP_INC', realpath(__DIR__ ) . DS );
define( 'CT_USP_ROOT', realpath( CT_USP_INC . '..') . DS );
define( 'CT_USP_SITE_ROOT', realpath( CT_USP_ROOT . '..') . DS );
define( 'CT_USP_LIB', CT_USP_ROOT . 'lib' . DS );
define( 'CT_USP_VIEW', CT_USP_ROOT . DS . 'view' . DS );
define( 'CT_USP_DATA', CT_USP_ROOT . 'data' . DS );

require_once CT_USP_LIB . 'autoloader.php';
require_once( CT_USP_INC . 'functions.php' );

// URI
define( 'CT_USP_URI',      'http://' . Server::get('HTTP_HOST') . preg_replace( '/^(\/.*?\/).*/', '$1', parse_url( Server::get('REQUEST_URI'), PHP_URL_PATH ) ) );
define( 'CT_USP_AJAX_URI', parse_url( Server::get('REQUEST_URI') )['path'] );

// Load settings, data and remote calls data
new \Cleantalk\Common\State( 'settings', 'data', 'remote_calls' );

// Create empty error object
Cleantalk\Common\Err::getInstance();

// Run scheduled tasks
define( 'CT_USP_CRON_FILE', CT_USP_ROOT . 'data' . DS . 'cron.php' );
$cron = new Cleantalk\Uniforce\Cron();
$cron->checkTasks();
if( ! empty( $cron->tasks_to_run ) )
	require_once CT_USP_INC . 'cron_functions.php'; // File with cron wrappers
	$cron->runTasks();
unset( $cron );