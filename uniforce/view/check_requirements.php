<?php

    $no_sql = false;
    $no_pdo_found = false;
    $php_version_failed = false;
    $curl_failed = false;
	$show_errors = ini_get( 'display_errors' );
	ini_set( 'display_errors', 0);
    if (class_exists('\PDO')) {
        try{
            $db = new \Cleantalk\USP\DB();
            $db->init(
                'mysql:host=db2c.cleantalk.org;charset=utf8',
                'test_user',
                'oMae9Neid8yi'
            );
            unset($db);
        }catch(Exception $e){
            $no_sql = true;
        }
    } else {
        $no_pdo_found = true;
    }

    $php_version_failed = version_compare(phpversion(), '5.6', '<' );
    $curl_failed = !function_exists('curl_exec');
	ini_set( 'display_errors', $show_errors);

    // Check if the openssl extension is installed
    define( 'CT_USP__NO_SQL', $no_sql );
?>

<?php if( $php_version_failed || $no_pdo_found || $curl_failed) : ?>
	<!DOCTYPE html>
	<html>
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="shortcut icon" href="img/ct_logo.png">
		<link href="https://fonts.googleapis.com/css?family=Lato" rel="stylesheet">

		<title>Universal Anti-Spam Plugin by CleanTalk</title>
		<!-- Bootstrap core CSS -->
		<link href="css/bootstrap.css" rel="stylesheet">

		<!-- Custom styles -->
		<link href="css/setup-wizard.css" rel="stylesheet">

		<link href="css/animate-custom.css" rel="stylesheet">

	</head>
	<body class="fade-in">
	<!-- start setup wizard box -->
	<div class="container" id="setup-block">
		<div class="row">
			<div class="col-sm-6 col-md-4 col-sm-offset-3 col-md-offset-4">

				<div class="setup-box clearfix animated flipInY">
					<div class="page-icon animated bounceInDown text-center">
						<img  src="img/ct_logo.png" alt="Cleantalk logo" style="width: 100%"/>
					</div>
					<div class="setup-logo" style="text-align: center">
						<h3> - Universal Anti-Spam Plugin - </h3>
					</div>
					<hr />
					<div class="setup-form">
                        <?php if ($no_pdo_found) : ?>
                            <h4><p class="text-center">No PDO drivers found.</p></h4>
                            <p class="text-center">The plugin requires PDO MySQL driver to be installed and configured in PHP environment</p>
                            <p class="text-center">Please, contact your hosting provider to update it.</p>
                        <?php endif;  ?>
                        <?php if ($php_version_failed) : ?>
                            <h4><p class="text-center">PHP version is <?php echo phpversion(); ?></p></h4>
                            <p class="text-center">The plugin requires version 5.6 or higher.</p>
                            <p class="text-center">Please, contact your hosting provider to update it.</p>
                        <?php endif;  ?>
                        <?php if ($curl_failed) : ?>
                            <h4><p class="text-center">Curl extension is not installed or undeclared in php.ini</p></h4>
                            <p class="text-center">The plugin requires CURL installed.</p>
                            <p class="text-center">Please, contact your hosting provider to update it.</p>
                        <?php endif;  ?>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-sm-12">
                    <br/>
					<p class="footer-text text-center"><small>Please, check the extension for your CMS on our <a href="https://cleantalk.org/help/install" target="_blank" style="text-decoration: underline;">plugins page</a> before setup</small></p>
					<p class="footer-text text-center"><small>It is highly recommended to create a backup before installation</small></p>
				</div>
			</div>
		</div>
		<!-- End setup-wizard wizard box -->

		<footer class="container">

		</footer>

		<script src="js/jquery.min.js"></script>
        <script src="js/bootstrap.min.js"></script>
		<script src="js/jquery-ui.min.js"></script>
		<script src="js/placeholder-shim.min.js"></script>
		<script src="js/common.js?v=2.0"></script>
		<script src="js/custom.js?v=2.0"></script>
		<script type='text/javascript'>
            var security = '<?php echo md5( Server::get( 'SERVER_NAME' ) ) ?>';
            var ajax_url = location.href;
		</script>

	</body>
	</html>
	<?php
	die();
endif;
