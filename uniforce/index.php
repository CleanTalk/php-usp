<?php

    // Check requirements
    if( version_compare( phpversion(), '5.6', '<' ) ){ ?>

        <h4 class="text-center">PHP version is <?php echo phpversion(); ?></h4>
        <h4 class="text-center">Universal Security Plugin by CleanTalk requires version 5.6 or higher.</h4>
        <h4 class="text-center">Please, contact your hosting provider to update it.</h4>

<?php
    }else{
        header('Location: router.php');
    }
