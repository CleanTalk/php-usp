<?php

use Cleantalk\USP\Common\State;

// Exit if accessed directly.
if ( ! defined( 'CT_USP_ROOT' ) ) {
    header('HTTP/1.0 403 Forbidden');
    exit ();
}

?>

<script src="js/jquery.min.js"></script>
<script src="js/jquery-ui.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/overhang.min.js"></script>
<script src="js/placeholder-shim.min.js"></script>
<script src="js/common.js?v=<?php echo SPBCT_VERSION; ?>"></script>
<script src="js/ct_ajax.js?v=<?php echo SPBCT_VERSION; ?>"></script>
<script src="js/<?php echo $page; ?>.js?v=<?php echo SPBCT_VERSION; ?>"></script>
<script type='text/javascript'>
    var spbct_checkjs_val = "<?php echo md5( State::getInstance()->key ) ?>";
    var uniforce_security = "<?php echo $page !== 'login' ? \Cleantalk\USP\Common\State::getInstance()->data->security_key : 'login' ?>";
    var uniforce_ajax_url = "<?php echo CT_USP_AJAX_URI; ?>";
</script>
<?php
if( isset($additional_js) ){
    $attach_js_string = '';
	foreach ($additional_js as $script){
	    $attach_js_string .= strpos( $script, 'http') === 0
            ? '<script src="' . $script . '"></script>'
            : '<script src="js/' . $script . '.js?v=' . SPBCT_VERSION . '"></script>';
	}
	echo $attach_js_string;
}
?>
