<?

/* 
 * Patch for filter_var()
 */
if(!function_exists('filter_var')){
	
	define('FILTER_VALIDATE_IP', 'ip');
	define('FILTER_FLAG_IPV4', 'ipv4');
	define('FILTER_FLAG_IPV6', 'ipv6');
	define('FILTER_VALIDATE_EMAIL', 'email');
	define('FILTER_FLAG_EMAIL_UNICODE', 'unicode');
	
	function filter_var($variable, $filter, $option = false){
		if($filter == 'ip'){
			if($option == 'ipv4'){
				if(preg_match("/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/", $variable, $matches)){
					$variable = $matches[1];
					return $variable;
				}
			}
			if($option == 'ipv6'){
				if(preg_match("/\s*(([:.]{0,7}[0-9a-fA-F]{0,4}){1,8})\s*/", $variable, $matches)){
					$variable = $matches[1];
					return $variable;
				}
			}
		}
		if($filter == 'email'){
			if($option == 'unicode' || $option == false){
				if(preg_match("/\s*(\S*@\S*\.\S*)\s*/", $variable, $matches)){
					$variable = $matches[1];
					return $variable;
				}
			}
		}
	}
}
