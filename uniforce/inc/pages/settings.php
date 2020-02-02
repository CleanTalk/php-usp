<?php

use Cleantalk\Common\Err;
use Cleantalk\Uniforce\FireWall;
use Cleantalk\Variables\Server;

// Exit if accessed directly.
if ( ! defined( 'CLEANTALK_ROOT' ) ) {
    header('HTTP/1.0 403 Forbidden');
    exit ();
}
?>
<!-- Login -->
<?php if( empty($_SESSION["authenticated"]) || $_SESSION["authenticated"] != 'true' ) : ?>

            <div class="setup-form">
                <!-- Start Error box -->
                <div class="alert alert-danger alert-dismissible fade in" style="display:none" role="alert">
                    <button type="button" class="close" > &times;</button>
                    <p id='error-msg'></p>
                </div>
                <!-- End Error box -->
                <?php if( ! empty( $uniforce_is_installed ) ) : ?>
                    <form action = 'javascript:void(null);' method="post" id='login-form'>
                        <input type="text" placeholder="Access key<?php if( isset( $uniforce_email, $uniforce_password ) ) echo ' or e-mail'; ?>" class="input-field" name="login" required/>

                        <?php if( ! empty( $uniforce_password ) ) : ?>
                            <input type="password" placeholder="Password" class="input-field" name="password"/>
                        <?php endif; ?>
                        <input type="hidden" id="uniforce_security" name="security" value="<?php echo $uniforce_security ?>">
                        <button type="submit" name="action" value="login" class="btn btn-setup" id="btn-login">Login</button>
                        <p>Don't know your access key? Get it <a href="https://cleantalk.org/my" target="_blank">here</a>.</p>
                    </form>
                <?php else : ?>
                    <h4 class="text-center">Please, <?php echo '<a href="' . Server::get( 'HOST_NAME' ) . '/uniforce/index.php">setup</a>'; ?> plugin first!</h4>
                <?php endif; ?>
            </div>

<!-- Settings -->
<?php else : ?>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-sm-12 settings-box">
        <div class="settings-links">
            <a href="#" class="text-danger" id='btn-uninstall' >Uninstall</a>
            <a href="#" id='btn-logout'>Log out </a>
        </div>
        <div class="clearfix"></div>
        <div>
            <!-- Start Error box -->
            <div class="alert alert-danger alert-dismissible fade in" style="<?php if( ! Err::check() ) echo 'display:none'; ?>" role="alert">
                <button type="button" class="close" > &times;</button>
                <p id='error-msg'><?php echo Err::check_and_output()['error']; ?></p>
            </div>
            <!-- End Error box -->
            <form action="javascript:void(null);" method="POST" class="form-horizontal" role="form">
                <div class="row">
                    <div class="col-xs-12 col-sm-12 col-md-6 col-lg-6">
                        <h4 class="text-center">Settings</h4>
                        <hr>
                        <div class="col-sm-12">
                            <div class="form-group row">
                                <input class="form-control" type="text" placeholder="Access key" id="auth_key" name = "apikey" value =<?php if (isset($uniforce_apikey)) echo $uniforce_apikey; ?>>
                                <p>Account registered for email: <?php echo !empty($uniforce_account_name_ob) ? $uniforce_account_name_ob : 'unkonown';  ?></p>
                            </div>
                            <div class="form-group row">
                                <label for="uniforce_sfw_protection">Enable Security FireWall</label>
                                <input type="checkbox" class="checkbox style-2 pull-right" id="uniforce_sfw_protection" name="uniforce_sfw_protection" <?php if (!empty($uniforce_sfw_protection)) echo "checked"; ?>>
                            </div>
                            <div class="form-group row">
                                <label for="uniforce_waf_protection">Enable WebApplication FireWall</label>
                                <input type="checkbox" class="checkbox style-2 pull-right" id="uniforce_waf_protection" name="uniforce_waf_protection" <?php if (!empty($uniforce_waf_protection)) echo "checked"; ?>>
                            </div>
                            <div class="form-group row">
                                <label for="uniforce_bfp_protection">Enable BruteForce protection</label>
                                <input type="checkbox" class="checkbox style-2 pull-right" id="uniforce_bfp_protection" name="uniforce_bfp_protection" <?php if (!empty($uniforce_bfp_protection)) echo "checked"; ?>>

                            </div>
                            <div class="form-group row">
                                <label for="uniforce_bfp_protection_url">Admin page URI</label>
                                <input type="text" class="checkbox style-2 pull-right" id="uniforce_bfp_protection_url" name="uniforce_bfp_protection_url" value="<?php echo $uniforce_cms_admin_page; ?>" <?php if (empty($uniforce_bfp_protection)) echo "disabled"; ?>>
                            </div>
                        </div>
                    </div>
                    <div class="col-xs-12 col-sm-12 col-md-6 col-lg-6">
                        <h4><p class="text-center">Statistics</p></h4>
                        <hr>
                        <p>Check detailed statistics on <a href="https://cleantalk.org/my<?php echo ! empty($uniforce_user_token) ? '?cp_mode=security&user_token='.$uniforce_user_token : ''; ?>" target="_blank">your Security dashboard</a></p>
                        <p>Presumably CMS: <?php echo $uniforce_detected_cms; ?></p>
                        <p>Modified files:</p>
                        <?php foreach($uniforce_modified_files as $file){;?>
                            <p>&nbsp; - <?php echo $file; ?></p>
                        <?php } ?>
                        <p><?php echo FireWall::get_module_statistics(); ?></p>
                    </div>
                </div>
                <input type="hidden" id="uniforce_security" name="security" value="<?php echo $uniforce_security ?>">
                <input type="hidden" name="action" value="save_settings">
                <div class="wrapper wrapper__center">
                    <button type="submit" class="btn btn-setup mt-sm-2" id='btn-save-settings' style="display: inline">Save</button>
                    <img class="preloader" src="img/preloader.gif" style="display: none;">
                </div>
            </form>

<!-- End Admin area box -->
<?php endif; ?>
