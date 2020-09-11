<?php

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

/*
 * Patch for apache_request_headers()
 * If Apache web server is missing then making
 */
if( !function_exists('apache_request_headers') ){
    function apache_request_headers(){

        $headers = array();
        foreach($_SERVER as $key => $val){
            if(preg_match('/\AHTTP_/', $key)){
                $server_key = preg_replace('/\AHTTP_/', '', $key);
                $key_parts = explode('_', $server_key);
                if(count($key_parts) > 0 and strlen($server_key) > 2){
                    foreach($key_parts as $part_index => $part){
                        $key_parts[$part_index] = function_exists('mb_strtolower') ? mb_strtolower($part) : strtolower($part);
                        $key_parts[$part_index][0] = strtoupper($key_parts[$part_index][0]);
                    }
                    $server_key = implode('-', $key_parts);
                }
                $headers[$server_key] = $val;
            }
        }
        return $headers;
    }
}
