<?php


namespace Cleantalk\USP\Updater;


use Cleantalk\USP\Common\State;
use Cleantalk\USP\Common\File;
use Cleantalk\USP\Uniforce\API;
use Cleantalk\USP\Uniforce\Helper;
use Cleantalk\USP\Variables\Server;
use Cleantalk\USP\Uniforce\Cron;

class UpdaterScripts
{
    public static function update_to_3_5_0(){
        
        $replacement_path = CT_USP_ROOT . 'replacement';
        $root_path = CT_USP_ROOT;
        
        File::copy(
            $replacement_path . DS . 'fw_nets_meta.php',
            $root_path . 'data' . DS . 'fw_nets_meta.php'
        );
        File::delete($replacement_path);
        
       Cron::updateTask('sfw_update', 'uniforce_fw_update', 86400, time()+10);
        
        // Check if cloud MySQL is accessible
        $sql_accessible = true;
        $show_errors = ini_get( 'display_errors' );
        ini_set( 'display_errors', 0);
        try{
            $db = \Cleantalk\USP\DB::getInstance(
                'mysql:host=db2c.cleantalk.org;charset=utf8',
                'test_user',
                'oMae9Neid8yi'
            );
        }catch( \Exception $e ){
            $sql_accessible = false;
        }
        ini_set( 'display_errors', $show_errors);
        
        // Call the method once again if cloud MySQL is accessible
        if( $sql_accessible ){
            
            $usp = State::getInstance();
            $result = API::method__dbc2c_get_info( $usp->key, true );
            
            if( empty( $result['error'] ) ){
                $usp->data->db_request_string = 'mysql:host=' . $result['db_host'] . ';dbname=' . $result['db_name'] . ';charset=utf8';
                $usp->data->db_user           = $result['db_user'];
                $usp->data->db_password       = $result['db_password'];
                $usp->data->db_created        = $result['created'];
                $usp->data->save();
            }
        }
    }
}