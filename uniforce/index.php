<?php

    // Check PHP requirements
    if( version_compare( phpversion(), '5.6', '<' ) ){ ?>
        <div style="text-align: center">
            <h4>PHP version is <?php echo phpversion(); ?></h4>
            <h4>Universal Security Plugin by CleanTalk requires version 5.6 or higher.</h4>
            <h4>Please, contact your hosting provider to update it.</h4>
        </div>
<?php
    // Check Wordpress installation
    }elseif( file_exists(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'index.php') &&
        stripos( file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'index.php' ), '@package wordpress') !== false
    ){
?>
        <div style="text-align: center">
            <h4></h4>
            <h4>Universal Security Plugin by CleanTalk can not be installed on WordPress.</h4>
            <h4>Please, use <a href="https://wordpress.org/plugins/security-malware-firewall/">plugin</a> from Wordpress catalog.</h4>
        </div>
<?php
    }else{
        header('Location: router.php');
    }
