<?php

use Cleantalk\USP\Common\State;
use Cleantalk\USP\Variables\Cookie;

/**
 * JavaScript test for sender
 * return null|0|1;
 */
function spbct_js_test() {
    if( isset( $_COOKIE['spbct_checkjs'] ) ){
        if( $_COOKIE['spbct_checkjs'] == md5( State::getInstance()->key ) )
            return 1;
        else
            return 0;
    }else{
        return null;
    }
}

/**
 * Translate function
 *
 * @param string $string
 * @param string $text_domain
 *
 * @return string
 */
function __( $string, $text_domain ='' ){
	return $string;
}

/**
 * Translate function
 * Outputs string
 *
 * @param string $string
 * @param string $text_domain
 *
 * @return void
 * @output string Translation
 */
function _e( $string, $text_domain ){
	echo $string;
}

function usp_localize_script( $name, $data ) {
	$data = json_encode($data);
	echo "<script>/*<![CDATA[*/var $name = $data;/*]]>*/</script>";
}

function usp__is_admin()
{
    return Cookie::get( 'authentificated' ) === State::getInstance()->data->security_key;
}
