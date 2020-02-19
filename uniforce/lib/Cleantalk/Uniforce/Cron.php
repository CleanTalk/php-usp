<?php


namespace Cleantalk\Uniforce;

use Cleantalk\Common\Err;
use Cleantalk\Common\File;

class Cron extends \Cleantalk\Common\Cron
{

    // Option name with cron data
    const CRON_FILE = CT_USP_CRON_FILE;

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