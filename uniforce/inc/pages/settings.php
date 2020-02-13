<?php

use Cleantalk\Common\Err;
use Cleantalk\Uniforce\FireWall;

// Exit if accessed directly.
if ( ! defined( 'CT_USP_ROOT' ) ) {
    header('HTTP/1.0 403 Forbidden');
    exit ();
}

require_once CT_USP_INC . 'settings.php';

?>

<body class="fade-in">
    <div class="container" id="layout-block">
        <div class="row" style="margin-top: 80px">
            <div class="col-sm-12 settings-box">
                <div class="clearfix"></div>

                <!-- Uninstall Logout buttons -->
                <div class="settings-links">
                    <a href="#" class="text-danger" id='btn-uninstall' >Uninstall</a>
                    <a href="#" id='btn-logout'>Log out </a>
                </div>

                <!-- Icon and title -->
                <div class="page-icon animated bounceInDown">
                    <img src="img/logo.png" alt="Cleantalk logo" />
                </div>
                <div class="logo">
                    <h3> - Universal Security Plugin - </h3>
                </div>

                <div>
                    <!-- Start Error box -->
                    <div class="alert alert-danger alert-dismissible fade in" style="<?php if( ! Err::check() ) echo 'display:none'; ?>" role="alert">
                        <button type="button" class="close" > &times;</button>
                        <p id='error-msg'><?php echo Err::check_and_output()['error']; ?></p>
                    </div>
                    <!-- End Error box -->
                    <form action="javascript:void(null);" method="POST" class="form-horizontal" role="form">
                        <div class="row">
                            <div class="col-xs-12 col-sm-12 col-md-12 col-lg-12">

                                <?php

                                $settings = new \Cleantalk\Layout\Settings();

                                // Tab summary
                                $settings
	                                ->add_tab('summary')
	                                    ->setActive()
                                        ->add_group('your_security_dashboard')
                                            ->add_plain('dashboard')
                                                ->setHtml('<p class="text-center">Check detailed statistics on <a href="https://cleantalk.org/my<?php echo ! empty($uniforce_user_token) ? \'?cp_mode=security&user_token=\'.$uniforce_user_token : \'\'; ?>" target="_blank">your Security dashboard</a></p>')
                                        ->getParent(2)
                                        ->add_group('statistics')
                                            ->add_plain('stat')
                                                ->setCallback('usp_settings__show_fw_statistics')
                                        ->getParent(2)
                                            ->add_group('detected_cms')
                                                ->add_plain('detected_cms')
                                                    ->setCallback('ctusp_settings__show_cms')
                                        ->getParent(2)
                                        ->add_group('modified_files')
                                            ->add_plain('modified_files')
                                                ->setCallback('ctusp_settings__show_modified_files');

                                    // Settings
                                    $settings
	                                    ->add_tab( 'settings' )
                                            ->add_group('access_key')
                                                ->add_field('key')
                                                    ->setInput_type('text')
                                                    ->setTitle('')
                                                    ->setHtml_after('</p>Account registered for email: ' . $usp->data->account_name_ob . '</p>')
                                            ->getParent( 2)
                                            ->add_group( 'firewall')
        	                                    ->add_field( 'fw' )
                                                    ->setTitle('Security Firewall')
                                                    ->setDescription('Firewall filters requests from malicious IP addresses.')
                                                ->getParent()
                                                ->add_field('waf')
                                                    ->setTitle('Web Application Firewall')
                                                    ->setDescription('Catches dangerous stuff like: XSS, MySQL-injections and malicious uploaded files.')
                                            ->getParent(2)
                                            ->add_group('miscellaneous')
                                                ->add_field( 'bfp_enable')
                                                    ->setTitle('Bruteforce protection')
                                                    ->setDescription('Bruteforce protection for login forms.')
                                            ->getParent()
                                                ->add_field('bfp_admin_page_uri')
                                                    ->setInput_type('text')
	                                                ->setTitle('Admin page URI');

                                    // Scanner
                                    $settings
                                         ->add_tab( 'scanner' )
                                            ->add_group( 'common')
                                                ->setTitle('');

                                    $settings->draw();

                                ?>
                            </div>
                        </div>
                        <input type="hidden" id="uniforce_security" name="security" value="<?php echo $usp->data->security_key ?>">
                        <input type="hidden" name="action" value="save_settings">
                        <div class="text-center">
                            <button type="submit" class="btn btn-setup mt-sm-2" id='btn-save-settings' style="display: inline">Save</button>
                            <img class="preloader" src="img/preloader.gif" style="display: none;">
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>