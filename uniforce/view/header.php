<html lang="en">
    <head>
        <meta name="robots" content="noindex, nofollow">
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="shortcut icon" href="img/ct_logo.png">
<!--        <link href="https://fonts.googleapis.com/css?family=Lato" rel="stylesheet">-->

        <title>
            <?php
            $title = defined('CT_USP_UNIFORCE_LITE') && CT_USP_UNIFORCE_LITE
                ? "UniForce Lite, Malware scanner"
                : "UniForce, Universal Security Plugin";
            echo $title;
            ?>
        </title>

        <!-- CSS -->

        <link href="css/reset.css" rel="stylesheet">

        <!-- Bootstrap core CSS -->
        <link href="css/bootstrap.css" rel="stylesheet">

        <!-- Plugins-->
        <link href="css/overhang.min.css" rel="stylesheet">

        <!-- Animation -->
        <link href="css/animate-custom.css" rel="stylesheet">

        <!-- Layout common -->
        <link href="css/layout.css" rel="stylesheet">

        <!-- Color Scheme -->
        <link href="css/color-scheme.css" rel="stylesheet">

        <!-- Icons -->
        <link href="css/icons.css" rel="stylesheet">

        <!-- Custom page style -->
        <link href="css/<?php echo $page ?>.css" rel="stylesheet">
        <?php
            if( isset( $additional_css ) ){
	            $attach_css_string = '';
	            foreach( $additional_css as $style ){
		            $attach_css_string .= strpos( $style, 'http' ) === 0
			            ? '<link href="' . $style . '" rel="stylesheet">'
			            : '<link href="css/' . $style . '.css?v=' . SPBCT_VERSION . '" rel="stylesheet">';
	            }
	            echo $attach_css_string;
            }
        ?>

    </head>
