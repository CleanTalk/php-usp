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