<?php

/**
 * JavaScript test for sender
 * return null|0|1;
 */
function spbct_js_test() {
    global $uniforce_apikey;
    if( isset( $_COOKIE['spbct_checkjs'] ) ){
        if( $_COOKIE['spbct_checkjs'] == md5( $uniforce_apikey ) )
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