<?php

namespace Cleantalk\USP\Uniforce\Firewall;

/*
 * The abstract class for any FireWall modules.
 * Compatible with any CMS.
 *
 * @version       1.0
 * @author        Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * @license       GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * @since 2.49
 */

use Cleantalk\USP\Variables\Get;
use Cleantalk\USP\Variables\Server;

class FirewallModule extends \Cleantalk\USP\Security\Firewall\FirewallModule {
	
	/**
	 * FireWall_module constructor.
	 * Use this method to prepare any data for the module working.
	 *
	 * @param array $params
	 */
	public function __construct( $params = array() ){
		
		$this->die_page__file = file_exists( __DIR__ . DS . 'die_page_' . strtolower( $this->module_name ) . '.html' )
			? __DIR__ . DS . 'die_page_' . strtolower( $this->module_name ) . '.html'
			: null;
		
		parent::__construct( $params );
	}
	
	/**
	 * Use this method to handle logs updating by the module.
	 *
	 * @param array $fw_result
	 *
	 * @return void
	 */
	public static function update_log( $fw_result ){
		
		global $salt;
		
		$log_item = array(
			'ip'              => $fw_result['ip'],
			'time'            => time(),
			'status'          => $fw_result['status'],
			'pattern'         => ! empty( $fw_result['pattern'] )
				? json_encode( $fw_result['pattern'] )
				: '',
			'page_url'        => substr(
				addslashes(( Server::get('HTTPS') != 'off' ? 'https://' : 'http://') . Server::get('HTTP_HOST').Server::get('REQUEST_URI')),
				0,
				4096
			),
			'http_user_agent' => Server::get('HTTP_USER_AGENT')
				? addslashes(htmlspecialchars(substr(Server::get('HTTP_USER_AGENT'), 0, 300)))
				: 'unknown',
			'request_method'  => Server::get( 'REQUEST_METHOD' ),
			'x_forwarded_for' => addslashes( htmlspecialchars( substr( Server::get( 'HTTP_X_FORWARDED_FOR' ), 0, 15 ) ) ),
			'network'         => $fw_result['network'],
			'mask'            => $fw_result['mask'],
			'is_personal'     => $fw_result['is_personal'],
		);
		
		$log_item['id'] = md5( $fw_result['ip'] . $log_item['http_user_agent'] . $fw_result['status'] );
		
		$log_path = CT_USP_ROOT . 'data/fw_logs/' . hash('sha256', $fw_result['ip'] . $salt . $fw_result['status']) . '.log';
		
		if( file_exists( $log_path ) ){
			
			$log = file_get_contents($log_path);
			$log = str_getcsv( $log );
			
			$all_entries = isset($log[5]) ? $log[5] : 0;
			
			$log = array(
				$log_item['id'],
				$log_item['ip'],
				$log_item['time'],
				$log_item['status'],
				empty($log_item['pattern']) ? NULL : $log_item['pattern'],
				intval($all_entries) + 1,
				$log_item['page_url'],
				$log_item['http_user_agent'],
				$log_item['request_method'],
				empty($log_item['x_forwarded_for']) ? NULL : $log_item['x_forwarded_for'],
			);
			
		} else {
			
			$log = array(
				$log_item['id'],
				$log_item['ip'],
				$log_item['time'],
				$log_item['status'],
				empty($log_item['pattern']) ? NULL : $log_item['pattern'],
				1,
				$log_item['page_url'],
				$log_item['http_user_agent'],
				$log_item['request_method'],
				empty($log_item['x_forwarded_for']) ? NULL : $log_item['x_forwarded_for'],
			);
			
		}
        
        $fd = fopen( $log_path, 'w' );
        if( $fd ){
            flock( $fd, LOCK_EX );
            fputcsv( $fd, $log );
            fclose( $fd );
        }
	}
	
	/**
	 * Shows DIE page.
	 * Stops script executing.
	 *
	 * @param $result
	 */
	public function _die( $result ){
		
		// Common actions for all modules
		parent::_die( $result );
		
		// Adding block reason
		switch( $result['status'] ){
			case 'DENY':                $reason = __('Blacklisted', 'security-malware-firewall');                      break;
			case 'DENY_BY_NETWORK':	    $reason = __('Hazardous network', 'security-malware-firewall');	               break;
			case 'DENY_BY_DOS':         $reason = __('Blocked by DoS prevention system', 'security-malware-firewall'); break;
			case 'DENY_BY_WAF_XSS':	    $reason = __('Blocked by Web Application Firewall: XSS attack detected.',    'security-malware-firewall'); break;
			case 'DENY_BY_WAF_SQL':	    $reason = __('Blocked by Web Application Firewall: SQL-injection detected.', 'security-malware-firewall'); break;
			case 'DENY_BY_WAF_EXPLOIT':	$reason = __('Blocked by Web Application Firewall: Exploit detected.',       'security-malware-firewall'); break;
			case 'DENY_BY_WAF_FILE':    $reason = __('Blocked by Web Application Firewall: Malicious files upload.', 'security-malware-firewall'); break;
			case 'DENY_BY_BFP':         $reason = __('Blocked by BruteForce Protection: Too many invalid logins.',   'security-malware-firewall'); break;
			default :                   $reason = __('Blacklisted', 'security-malware-firewall');                      break;
		}
		
		if( $this->die_page__file ){
			
			$die_page_template = file_get_contents($this->die_page__file );
			
			$status = $result['status'] == 'PASS_SFW__BY_WHITELIST' ? '1' : '0';
			$cookie_val = md5( $result['ip'] . $this->api_key ) . $status;
			
			// Translation
			$replaces = array(
				'{TITLE}' => __('Blocked: Security by CleanTalk', 'security-malware-firewall'),
				'{TEST_TITLE}' => Get::get('spbct_test')
					? __('This is the testing page for Security FireWall', 'security-malware-firewall')
					: '',
				'{REASON}' => $reason,
				'{GENERATED_TIMESTAMP}' => time(),
				'{FALSE_POSITIVE_WARNING}' => __('Maybe you\'ve been blocked by a mistake. Please refresh the page (press CTRL + F5) or try again later.', 'security-malware-firewall'),
				
				
				'{REMOTE_ADDRESS}'                 => $result['ip'],
				'{SERVICE_ID}'                     => $this->state->data->service_id,
				'{HOST}'                           => Server::get( 'HTTP_HOST' ),
				'{GENERATED}'                      => '<h2 class="second">The page was generated at '.date("D, d M Y H:i:s"). '</h2>',
			);
			
			foreach( $replaces as $place_holder => $replace ){
				$die_page_template = str_replace( $place_holder, $replace, $die_page_template );
			}
			
			die($die_page_template);
			
		}else{
//			die("IP BLACKLISTED. Blocked by Security Firewall " . $result['ip'], "Blacklisted", Array('response'=>403));
			die("IP BLACKLISTED. Blocked by Security Firewall " . $result['ip'] );
		}
		
	}
	
}