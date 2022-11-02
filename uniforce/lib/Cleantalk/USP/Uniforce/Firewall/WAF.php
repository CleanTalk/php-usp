<?php

namespace Cleantalk\USP\Uniforce\Firewall;

use Cleantalk\USP\Common\State;
use Cleantalk\USP\Common\Storage;
use Cleantalk\USP\Uniforce\Helper;
use Cleantalk\USP\Variables\Server;
use Cleantalk\USP\Scanner\ScannerH;

class WAF extends \Cleantalk\USP\Uniforce\Firewall\FirewallModule {
	
	public $module_name = 'WAF';
	
	protected $waf_xss_check     = false;
	protected $waf_sql_check     = false;
	protected $waf_file_check    = false;
	protected $waf_exploit_check = false;
	
	private $waf_pattern       = array(); // Why WAF is triggered (reason)
	
	private $waf_sql_patterns = array();
	private $waf_exploit_patterns = array();
	private $waf_xss_patterns = array();
	
	public $waf_file_mime_check = array(
		'text/x-php',
		'text/plain',
		'image/x-icon',
	);
	
	/**
	 * FireWall_module constructor.
	 * Use this method to prepare any data for the module working.
	 *
	 * @param array $params
	 */
	public function __construct( $params = array() ){
		
		parent::__construct( $params );
		
	}
	
	/**
	 * Use this method to execute main logic of the module.
	 * @return mixed
	 */
	public function check() {
		
		$results = array();
		
		// Get signatures
		$signatures = $this->signatures__get();
		
		if ( $signatures ) {
			
			foreach ( $signatures as $signature ) {
				
				switch ( $signature['attack_type'] ) {
					
					case 'SQL_INJECTION':
						$this->waf_sql_patterns[] = $signature['body'];
						break;
					case 'XSS':
						$this->waf_xss_patterns[] = $signature['body'];
						break;
					case 'EXPLOIT':
						$this->waf_exploit_patterns[] = $signature['body'];
						break;
				}
			}
		}
		
		// XSS
		if( $this->waf_xss_check ){
			if($this->waf_xss_check($_POST) || $this->waf_xss_check($_GET) || $this->waf_xss_check($_COOKIE)){
				$results[] = array('ip' => end($this->ip_array), 'is_personal' => false, 'status' => 'DENY_BY_WAF_XSS', 'pattern' => $this->waf_pattern);
			}
		}
		
		// SQL-injection
		if( $this->waf_sql_check ){
			if($this->waf_sql_check($_POST) || $this->waf_sql_check($_GET)){
				$results[] = array('ip' => end($this->ip_array), 'is_personal' => false, 'status' => 'DENY_BY_WAF_SQL', 'pattern' => $this->waf_pattern);
			}
		}
		
		// File
		if ($this->waf_file_check ){
			if($this->waf_file_check()){
				$results[] = array('ip' => end($this->ip_array), 'is_personal' => false, 'status' => 'DENY_BY_WAF_FILE', 'pattern' => $this->waf_pattern);
			}
		}
		
		// Exploits
		if( $this->waf_exploit_check ){
			if($this->waf_exploit_check()){
				$results[] = array('ip' => end($this->ip_array), 'is_personal' => false, 'status' => 'DENY_BY_WAF_EXPLOIT', 'pattern' => $this->waf_pattern);
			}
		}
		
		if( ! $results )
			$results[] = array( 'status' => 'PASS' );
		
		foreach( $results as &$result ){
			$result = array_merge(
				$result,
				array(
					'ip'          => end( $this->ip_array ),
					'is_personal' => false,
					'module'      => 'WAF',
				)
			);
		}
		
		return $results;
		
	}

    /**
     * Get array of WAF signatures. Return array of signatures or false if no WAF rules found.
     * @return array|false
     */
    private function signatures__get(){
		
		$signatures_source = new Storage('signatures', null, '', 'csv', array(
			'id',
			'name',
			'body',
			'type',
			'attack_type',
			'submitted',
			'cci'
		) );

        $signatures = [];

        foreach ( $signatures_source->convertToArray() as $signature ) {
            if ( $signature['type'] === 'WAF_RULE' ) {
                $signatures[] = $signature;
            }
        }
        return !empty($signatures) ? $signatures : false;
	}
	
	/**
	 * Checks array for XSS-attack patterns
	 *
	 * @param $arr
	 *
	 * @return bool
	 */
	private function waf_xss_check( $arr ) {
		
		foreach( $arr as $name => $param ){
			
			// Recursion
			if( is_array( $param ) ){
				$result = $this->waf_xss_check( $param );
				if( $result === true )
					return true;
				continue;
			}
			
			//Check
			foreach( $this->waf_xss_patterns as $pattern ){
                $is_regexp = preg_match( '@^/.*/$@', $pattern ) || preg_match( '@^#.*#$@', $pattern );

                if (
                    ($is_regexp && preg_match( $pattern, $param)) ||
                    (stripos( $param, $pattern ) !== false)
                ) {
                    $this->waf_pattern = array( 'critical' => $pattern );
                    return true;
                }
			}
		}
		
		return false;
		
	}
	
	/**
	 * Checks array for SQL injections
	 *
	 * @param $arr
	 *
	 * @return bool
	 */
	private function waf_sql_check( $arr ) {
		
		foreach( $arr as $name => $param ){
			
			if( is_array( $param ) ){
				$result = $this->waf_sql_check( $param );
				if( $result === true )
					return true;
				continue;
			}
			
			foreach( $this->waf_sql_patterns as $pattern ){
                $is_regexp = preg_match( '@^/.*/$@', $pattern ) || preg_match( '@^#.*#$@', $pattern );

                if (
                    ($is_regexp && preg_match( $pattern, $param)) ||
                    (stripos( $param, $pattern ) !== false)
                ) {
                    $this->waf_pattern = array( 'critical' =>  $pattern );
                    return true;
                }
			}
		}
		
		return false;
		
	}
	
	/**
	 * Checks $_SERVER['QUERY_STRING'] for exploits
	 *
	 * @return bool
	 */
	private function waf_exploit_check() {
		
		foreach( $this->waf_exploit_patterns as $pattern ){
            $is_regexp = preg_match( '@^/.*/$@', $pattern ) || preg_match( '@^#.*#$@', $pattern );

            if (
                ($is_regexp && preg_match( $pattern, Server::get('QUERY_STRING'))) ||
                (stripos( Server::get('QUERY_STRING'), $pattern ) !== false)
            ) {
                $this->waf_pattern = array( 'critical' =>  $pattern );
                return true;
            }
		}
		
		return false;
		
	}
	
	/**
	 * Checks uploaded files for malicious code
	 *
	 * @return boolean Does the file contain malicious code
	 */
	private function waf_file_check() {
		
		if( ! empty( $_FILES ) ){
			foreach( $_FILES as $filez ){
				if ( ( empty($filez['errror'] ) || $filez['errror'] == UPLOAD_ERR_OK ) ) {
					$filez['tmp_name'] = is_array( $filez['tmp_name'] ) ? $filez['tmp_name'] : array( $filez['tmp_name'] );
					foreach( $filez['tmp_name'] as $file ){
						if(
							is_string( $file ) &&
							is_uploaded_file( $file ) &&
							is_readable( $file ) &&
							in_array( Helper::get_mime_type( $file ), $this->waf_file_mime_check )
						) {
							$fileh = new ScannerH( null, array( 'content' => file_get_contents( $file ) ) );
							if( empty( $fileh->error ) ){
								$fileh->process_file();
								if( ! empty( $fileh->verdict ) ){
									foreach( $fileh->verdict as $severity => $result ){
										$this->waf_pattern[$severity] = reset($result);
									}
									return true;
								}
							}
						}
					}
				}
			}
		}
		
		return false;
		
	}
}