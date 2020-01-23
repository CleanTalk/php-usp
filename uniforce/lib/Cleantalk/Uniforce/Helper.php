<?php

namespace Cleantalk\Uniforce;

/**
 * Cleantalk's hepler class
 * 
 * Mostly contains request's wrappers.
 *
 * @version 2.4
 * @package Cleantalk
 * @subpackage Helper
 * @author Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * @license GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * @see https://github.com/CleanTalk/php-antispam 
 *
 */

class Helper extends \Cleantalk\Common\Helper{
	
	static public function http__user_agent(){
		return defined( 'SPBCT_USER_AGENT' ) ? SPBCT_USER_AGENT : static::DEFAULT_USER_AGENT;
	}
	
}