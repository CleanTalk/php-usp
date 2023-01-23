<?php

namespace Cleantalk\USP\Security;

use Cleantalk\USP\Common\Helper;
use Cleantalk\USP\Uniforce\Firewall\FirewallModule;
use Cleantalk\USP\Variables\Get;

/**
 * CleanTalk SpamFireWall base class.
 * Compatible with any CMS.
 *
 * @version       4.0
 * @author        Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * @license       GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * @see           https://github.com/CleanTalk/php-antispam
 */
class Firewall
{
	
	public $ip_array = Array();
	
	// Database
	protected $db;
	
	//Debug
	public $debug;
	
	private $statuses_priority = array(
		'PASS',
		'DENY',
		'DENY_BY_SEC_FW',
		'DENY_BY_SPAM_FW',
		'DENY_BY_NETWORK',
		'DENY_BY_BFP',
		'DENY_BY_DOS',
		'DENY_BY_WAF_SQL',
		'DENY_BY_WAF_XSS',
		'DENY_BY_WAF_EXPLOIT',
		'DENY_BY_WAF_FILE',
		'PASS_BY_WHITELIST',
		'PASS_BY_TRUSTED_NETWORK', // Highest
	);
	
	public $fw_modules = array();
	
	/**
	 * Creates Database driver instance.
	 *
	 * @param mixed $db database handler
	 */
	public function __construct( $db = null  ){
		
		$this->debug    = !! Get::get( 'debug' );
		$this->ip_array = $this->ip__get( array('real'), true );
		
		if( isset( $db ) )
			$this->db       = $db;
	}
	
	/**
	 * Getting arrays of IP (REMOTE_ADDR, X-Forwarded-For, X-Real-Ip, Cf_Connecting_Ip)
	 *
	 * @param array $ips_input type of IP you want to receive
	 * @param bool  $v4_only
	 *
	 * @return array|mixed|null
	 */
	public function ip__get( $ips_input = array( 'real', 'remote_addr', 'x_forwarded_for', 'x_real_ip', 'cloud_flare' ), $v4_only = true ){
		
		$result = Helper::ip__get( $ips_input, $v4_only );
		
		return ! empty( $result ) ? array( 'real' => $result ) : array();
		
	}
	
	/**
	 * Loads the FireWall module to the array.
	 * For inner usage only.
	 * Not returns anything, the result is private storage of the modules.
	 *
	 * @param FirewallModule $module
	 */
	public function module__load( FirewallModule $module ){
		
		if( ! $this->module__is_loaded( $module ) ){
			$module->setDb( $this->db );
			$module->ip__append_additional( $this->ip_array );
			$this->fw_modules[ $module->module_name ] = $module;
			$module->setIpArray( $this->ip_array );
		}
		
	}
	
	public function module__is_loaded( FirewallModule $module = null ){
		return $module && in_array( $module, $this->fw_modules );
	}
	
	public function module__is_loaded__any(){
		return (bool) $this->fw_modules;
	}
	
	/**
	 * Do main logic of the module.
	 *
	 * @return void   returns die page or set cookies
	 */
	public function run() {
		
		$results = array();
		
		foreach( $this->fw_modules as $module ){
			
			// Check
			// Module should return not empty result!
			$module_results = $module->check();
			
			if( ! empty( $module_results ) ) {
				
				// Prioritize
				$module_result = $this->prioritize( $module_results );
				
				// Perform middle action if module require it
				if( method_exists( $module, 'middle_action') )
					$module->middle_action( $module_result );
				
				// Push to all results
				$results[ $module->module_name ] = $module_result;
			}
			
			// Break protection logic if it whitelisted or trusted network.
			if( $this->is_whitelisted( $results ) )
				break;
			
		}
		
        // Get the prime result
		$result = $this->prioritize( $results );
		
		// Write log. Each module use their own log system
		$curr_module = $this->fw_modules[ $result['module'] ];
		
		if( $curr_module ){
		
			$curr_module::update_log( $result );
			
			// Do finish action - die or set cookies
			// Blocked
			if( strpos( $result['status'], 'DENY' ) !== false ){
				$this->fw_modules[ $result['module'] ]->actions_for_denied( $result );
				$this->fw_modules[ $result['module'] ]->_die( $result );
				
			// Allowed
			}else
				$this->fw_modules[ $result['module'] ]->actions_for_passed( $result );
		
		}
		
	}
	
	/**
	 * Sets priorities for firewall results.
	 * It generates one main result from multi-level results array.
	 *
	 * @param array $results
	 *
	 * @return array Single element array of result
	 */
	private function prioritize( $results ){

        $final_priority = 0;
        $result = array(
            'status' => 'PASS',
            'ip' => '',
        );

        if ( is_array($results) ) {
            foreach ( $results as $fw_result ) {
                /**
                 * Calculating priority. Records that have status PASS_BY_TRUSTED_NETWORK gain hardcoded highest priority.
                 * Personal records are next by priority in accordance of this->statuses_priority.
                 */
                $status_priority_from_table = array_search($fw_result['status'], $this->statuses_priority);
                $is_personal_flag = isset($fw_result['is_personal']) && $fw_result['is_personal'];
                $is_trusted_network_flag = isset($fw_result['status']) && $fw_result['status'] == 'PASS_BY_TRUSTED_NETWORK';
                //used to gain maximum priority
                $total_count_of_statuses = count($this->statuses_priority);
                $current_record_priority = $status_priority_from_table;
                if ( $is_personal_flag || $is_trusted_network_flag ) {
                    //set maximum priority
                    $current_record_priority += $total_count_of_statuses;
                }
                //set new final priority if it is less than current record priority
                if ( $current_record_priority >= $final_priority ) {
                    $final_priority = $current_record_priority;
					//proceed result array
					$result = array(

						// Necessary params
						'module'       => $fw_result['module'],
						'ip'           => $fw_result['ip'],
						'status'       => $fw_result['status'],

						// FW
						'is_personal'  => !empty( $fw_result['is_personal'] ) ? (int)$fw_result['is_personal'] : 0,
						'country_code' => !empty( $fw_result['country_code'] ) ? $fw_result['country_code'] : '',
						'network'      => !empty( $fw_result['network'] ) ? $fw_result['network'] : 0,
						'mask'         => !empty( $fw_result['mask'] ) ? $fw_result['mask'] : 0,

						// WAF
						'pattern'      => !empty( $fw_result['pattern'] ) ? $fw_result['pattern'] : array(),

						// Security
						'event'        => !empty( $fw_result['event'] ) ? $fw_result['event'] : 0,
					);
				}
			}
		}

		return $result;

	}
	
	/**
	 * Check the result if it whitelisted or trusted network
	 *
	 * @param array $results
	 *
	 * @return bool
	 */
	private function is_whitelisted( $results ) {
		
		foreach ( $results as $fw_result ) {
			if (
				strpos( $fw_result['status'], 'PASS_BY_TRUSTED_NETWORK' ) !== false ||
				strpos( $fw_result['status'], 'PASS_BY_WHITELIST' ) !== false
			) {
				return true;
			}
		}
		return false;
		
	}
	
	/**
	 * Use this method to handle logs updating by the module.
	 *
	 * @param array $fw_result
	 *
	 * @return void
	 */
	public function update_log( $fw_result ){}
}
