<?php

namespace Cleantalk\USP\Uniforce;

use Cleantalk\USP\Variables\Server;

/**
 * CleanTalk SpamFireWall base class.
 * Compatible with any CMS.
 *
 * @depends       \Cleantalk\USP\Uniforce\Helper class
 * @depends       \Cleantalk\USP\Uniforce\API class
 * @depends       \Cleantalk\USP\Uniforce\DB class
 *
 * @version       4.0
 * @author        Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * @license       GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * @see           https://github.com/CleanTalk/php-antispam
 */
class Firewall extends \Cleantalk\USP\Security\Firewall{
	
	/**
	 * Creates Database driver instance.
	 *
	 * @param mixed $db database handler
	 */
	public function __construct( $db = null ){
		$this->db = new \Cleantalk\USP\File\FileDB( 'fw_nets' );
		parent::__construct( $db );
	}
	
}
