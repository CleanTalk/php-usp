<?php

namespace Cleantalk\Uniforce;

/**
 * Class CleantalkAPI.
 * Compatible only with Wordpress.
 *
 * @depends       \Cleantalk\Common\API
 * 
 * @version       1.0
 * @author        Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * @license       GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * @see           https://github.com/CleanTalk/wordpress-antispam
 */
class API extends \Cleantalk\Common\API{
	
	static public function get_agent(){
		return defined( 'SPBCT_AGENT' ) ? SPBCT_AGENT : static::DEFAULT_AGENT;
	}
	
}