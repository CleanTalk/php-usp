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
<script src="js/custom.js?v=<?php echo SPBCT_VERSION; ?>"></script>
<script type='text/javascript'>
    var uniforce_security = document.getElementById('uniforce_security').value;
    var uniforce_ajax_url = location.href;
</script>