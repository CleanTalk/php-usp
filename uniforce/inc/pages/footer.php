<?php

use Cleantalk\Variables\Server;

// Exit if accessed directly.
if ( ! defined( 'CLEANTALK_ROOT' ) ) {
    header('HTTP/1.0 403 Forbidden');
    exit ();
}
?>
                </div>
            </div>
        </div>
    <div class="row">
        <div class="col-sm-12">
            <p class="footer-text"><small>Please, check the extension for your CMS on our <a href="https://cleantalk.org/help/install" target="_blank" style="text-decoration: underline;">plugins page</a> before setup</small></p>
            <p class="footer-text"><small>It is highly recommended to create a backup before installation</small></p>
        </div>
    </div>
</div>
<!-- End setup-wizard wizard box -->

<footer class="container">

</footer>

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

</body>
</html>