<?php

use Cleantalk\Variables\Server;

// Exit if accessed directly.
if ( ! defined( 'CLEANTALK_ROOT' ) ) {
    header('HTTP/1.0 403 Forbidden');
    exit ();
}
?>
<div class="setup-form">

    <!-- Check requirements -->
    <?php if( version_compare( phpversion(), '5.6', '<' ) ) : ?>
        <h4 class="text-center">PHP version is <?php echo phpversion(); ?></h4>
        <h4 class="text-center">The plugin requires version 5.6 or higher.</h4>
        <h4 class="text-center">Please, contact your hosting provider to update it.</h4>

        <!-- Installation form -->
    <?php else : ?>
        <div class="alert alert-success alert-dismissible fade in" style="display:none; word-wrap: break-word;" role="alert">
            <strong style="text-align: center; display: block;">Success!</strong>
            <br />
            <p>Enter your <a class="underlined" href="https://cleantalk.org/my/">CleanTalk dashboard</a> to view statistics.</p>
            <br />
            <p>You can manage settings <?php echo '<a href="' . CLEANTALK_URI . '">here</a>'; ?>.</p>
        </div>
        <!-- Start Error box -->
        <div class="alert alert-danger alert-dismissible fade in" style="display:none" role="alert">
            <button type="button" class="close" > &times;</button>
            <p id='error-msg'></p>
        </div> <!-- End Error box -->
        <form action = 'javascript:void(null);' method="post" id='setup-form'>
            <div style="text-align: center">
                <input type="text" placeholder="Access key or e-mail" class="input-field" name="access_key_field" required style="display: inline;"/>
                <img class="preloader" src="img/preloader.gif" style="display: none;" alt="">
            </div>
            <p>
                <button type="button" class="btn" id="show_more_btn" style="background-color:transparent">
                    Advanced configuration (optional)
                    <img  class ="show_more_icon" src="img/expand_more.png" alt="Show more" style="width:24px; height:24px;"/>
                </button>
            </p>
            <div class ="advanced_conf">
                <p class="text-center"><small>Set admin password</small>
                    <img data-toggle="tooltip" data-placement="top" src="img/help_icon.png" title="If leave is blank you will have to use your API-key to authenticate to the settings page." style="width:10px; height:10px;" alt="">
                </p>
                <input type="password" name="admin_password" class="input-field" placeholder="Password" />
                <p><small>Additional scripts</small>&nbsp
                    <img data-toggle="tooltip" data-placement="top" src="img/help_icon.png" title="Universal Security plugin will write protection code to index.php file by default. If your contact or registration contact forms are located in different files/scripts, list them here separated by commas. Example: register.php, contact.php" style="width:10px; height:10px;" alt="">
                </p>
                <input type="text" class="input-field" name="addition_scripts" />
            </div>
            <input type="hidden" id="uniforce_security" name="security" value="<?php echo md5( Server::get( 'SERVER_NAME' ) ) ?>">
            <button type="submit" class="btn btn-setup" disabled>Install</button>
        </form>
        <div class="setup-links">
            <a href="https://cleantalk.org/publicoffer" target="_blank">
                License agreement
            </a>
            <br />
            <a href="https://cleantalk.org/register?platform=uniforce&website=<?php echo $_SERVER['SERVER_NAME']; ?>" target="_blank">
                Don't have an account? <strong>Create here!</strong>
            </a>
        </div>
    <?php endif; ?>

</div>
