<?php


namespace Cleantalk\USP\Uniforce;

use Cleantalk\USP\Common\Err;
use Cleantalk\USP\Common\File;

class Cron extends \Cleantalk\USP\Common\Cron
{

    // Option name with cron data
    const CRON_FILE = CT_USP_CRON_FILE;

    public static function getTasks(){
    	if( ! file_exists( self::CRON_FILE ) ){
    		file_put_contents(
    			self::CRON_FILE,
			    "<?php\nglobal \$uniforce_tasks;\n\$uniforce_tasks = array ();"
		    );
	    }
        require self::CRON_FILE;

        global $uniforce_tasks;

        if (empty($uniforce_tasks)) {
            return array();
        }

        return $uniforce_tasks;
    }

    // Save option with tasks
    public static function saveTasks( $tasks ){
        File::replace__variable( self::CRON_FILE, 'uniforce_tasks', $tasks );
        return ! Err::check();
    }

}