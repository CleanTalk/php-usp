<?php


namespace Cleantalk\USP\Uniforce;

use Cleantalk\USP\Common\Err;
use Cleantalk\USP\Common\File;

class Cron extends \Cleantalk\USP\Common\Cron
{

    // Option name with cron data
    const CRON_FILE = CT_USP_CRON_FILE;

    /**
     * @return array|array[]
     */
    public static function getTasks(){
    	if( ! file_exists( self::CRON_FILE ) ){
    		file_put_contents(
    			self::CRON_FILE,
			    "<?php\n\n\$uniforce_tasks = array ();"
		    );
	    }
        require self::CRON_FILE;

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

    /**
     * Get the task info array.
     * @param string $task_name
     * @return array|array[]
     */
    public static function getTaskInfo($task_name) {
        return isset(self::getTasks()[$task_name]) ? self::getTasks()[$task_name] : array();
    }

    /**
     * Get the task next call.
     * @param string $task_name
     * @param string $format Date formatting. If set, will apply the format to the output date. If false, return string of timestamp. Default false.
     * @return false|string If $format param is set return formatted string, timestamp string otherwise. False on errors.
     */
    public static function getTaskNextCall($task_name, $format = false) {
        $out = false;
        $task_info = self::getTaskInfo($task_name);
        if ( !empty($task_info) && !empty($task_info['next_call'])) {
            if (is_string($format)) {
                $out = date($format, $task_info['next_call']);
            } else {
                $out = (string)$task_info['next_call'];
            }
        }
        return $out;
    }

    // Updates cron task, create task if not exists
    static public function updateTask($task, $handler, $period, $first_call = null, $params = array()){
        return static::removeTask($task) &&
            static::addTask($task, $handler, $period, $first_call, $params);
    }

    static public function removeTask($task)
    {
        $tasks = self::getTasks();

        if(!isset($tasks[$task]))
            return true;

        unset($tasks[$task]);

        return static::saveTasks( $tasks );
    }

    static public function addTask($task, $handler, $period, $first_call = null, $params = array())
    {
        // First call time() + preiod
        $first_call = !$first_call ? time() + $period : $first_call;

        $tasks = static::getTasks();

        if(isset($tasks[$task]))
            return false;

        // Task entry
        $tasks[$task] = array(
            'handler' => $handler,
            'next_call' => $first_call,
            'executed' => 0,
            'last_executed' => 0,
            'period' => $period,
            'params' => $params,
        );

        return static::saveTasks( $tasks );
    }

}
