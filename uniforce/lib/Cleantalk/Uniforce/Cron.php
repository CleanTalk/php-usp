<?php


namespace Cleantalk\Uniforce;

use Cleantalk\Common\Err;
use Cleantalk\Common\File;

class Cron extends \Cleantalk\Common\Cron
{

    // Option name with cron data
    const CRON_FILE = CLEANTALK_ROOT . 'data'. DS . 'cron_data.php';

    public static function getTasks(){
        require self::CRON_FILE;
        return $uniforce_tasks;
    }

    // Save option with tasks
    public static function saveTasks( $tasks ){
        File::replace__variable( self::CRON_FILE, 'uniforce_tasks', $tasks );
        return ! Err::check();
    }

}