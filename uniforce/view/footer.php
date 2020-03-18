<?php

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
<script src="js/<?php echo $page; ?>.js?v=<?php echo SPBCT_VERSION; ?>"></script>
<?php
if( isset($additional_js) ){
	foreach ($additional_js as $script){
		echo '<script src="js/' . $script . '.js?v=' . SPBCT_VERSION . '"></script>';
	}
}
?>
<script type='text/javascript'>
    var uniforce_security = "<?php echo $page !== 'login' ? \Cleantalk\Common\State::getInstance()->data->security_key : 'login' ?>";
    var uniforce_ajax_url = "<?php echo CT_USP_AJAX_URI; ?>";
</script>