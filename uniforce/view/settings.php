<?php

use Cleantalk\Common\Err;
use Cleantalk\Uniforce\FireWall;

// Exit if accessed directly.
if ( ! defined( 'CT_USP_ROOT' ) ) {
    header('HTTP/1.0 403 Forbidden');
    exit ();
}

require_once CT_USP_INC . 'settings.php';
require_once CT_USP_INC . 'scanner.php';

usp_localize_script( 'spbcTable',
    array(
        'warning_bulk'       => __('Are sure you want to perform these actions?', 'security-malware-firewall'),
        'warning_default'    => __('Do you want to proceed?', 'security-malware-firewall'),
        'warning_delte'      => __('This can\'t be undone and could damage your website. Are you sure?', 'security-malware-firewall'),
        'warning_replace'    => __('This can\'t be undone. Are you sure?', 'security-malware-firewall'),
        'warning_quarantine' => __('This can\'t be undone and could damage your website. Are you sure?', 'security-malware-firewall'),
    )
);

usp_localize_script( 'spbcScaner', array(

	// PARAMS

	// Settings / Statuses
	'scaner_enabled'    => \Cleantalk\Common\State::getInstance()->valid ? 1 : 0,
	'scaner_status'     => \Cleantalk\Common\State::getInstance()->valid ? 1 : 0,
	'check_heuristic'   => \Cleantalk\Common\State::getInstance()->settings->scanner_heuristic_analysis  ? 1 : 0,
	'check_signature'   => \Cleantalk\Common\State::getInstance()->settings->scanner_signature_analysis  ? 1 : 0,
//	'wp_content_dir'    => realpath(WP_CONTENT_DIR),
	'wp_root_dir'       =>  realpath(CT_USP_SITE_ROOT),
	// Params
	'on_page' => 20,

	//TRANSLATIONS

	//Confirmation
	'scan_modified_confiramation' => __('There is more than 30 modified files and this could take time. Do you want to proceed?', 'security-malware-firewall'),
	'warning_about_cancel' => __('Scan will be performed in the background mode soon.', 'security-malware-firewall'),
	'delete_warning' => __('Are you sure you want to delete the file? It can not be undone.'),
	// Buttons
	'button_scan_perform'                   => __('Perform scan', 'security-malware-firewall'),
	'button_scan_pause'                     => __('Pause scan',   'security-malware-firewall'),
	'button_scan_resume'                    => __('Resume scan',  'security-malware-firewall'),
	// Progress bar
	'progressbar_get_hashes'                => __('Receiving hashes', 'security-malware-firewall'),
	'progressbar_count_hashes_plug'         => __('Counting plugins and themes', 'security-malware-firewall'),
	'progressbar_get_hashes_plug'           => __('Receiving plugins hashes', 'security-malware-firewall'),
	'progressbar_clear_table'               => __('Preparing',        'security-malware-firewall'),
	// Scanning core
	'progressbar_count_files'                     => __('Counting files',             'security-malware-firewall'),
	'progressbar_scan'                      => __('Scanning for modifications', 'security-malware-firewall'),
	'progressbar_count_modified_heur'       => __('Counting not checked',        'security-malware-firewall'),
	'progressbar_scan_heuristic'        => __('Heuristic analysis',         'security-malware-firewall'),
	'progressbar_count_modified_sign'       => __('Counting not checked',        'security-malware-firewall'),
	'progressbar_scan_signatures'        => __('Serching for signatures',    'security-malware-firewall'),
	//Cure
	'progressbar_cure_backup'               => __('Backuping', 'security-malware-firewall'),
	'progressbar_count_cure'                => __('Count cure', 'security-malware-firewall'),
	'progressbar_cure'                      => __('cure', 'security-malware-firewall'),
	// Links
	'progressbar_count_links'               => __('Counting links', 'security-malware-firewall'),
	'progressbar_scan_links'                => __('Scanning links', 'security-malware-firewall'),
	// Frontend
	'progressbar_frontend_count'            => __('Counting pages', 'security-malware-firewall'),
	'progressbar_frontend_scan'             => __('Scanning pages', 'security-malware-firewall'),
	// Other
	'progressbar_send_results'              => __('Sending results', 'security-malware-firewall'),
	// Warnings
	'result_text_bad_template' => __('Recommend to scan all (%s) of the found files to make sure the website is secure.', 'security-malware-firewall'),
	'result_text_good_template' => __('No threats are found.', 'security-malware-firewall'),
	//Misc
	'look_below_for_scan_res' => __('Look below for scan results.', 'security-malware-firewall'),
	'view_all_results'        => sprintf(
		__('</br>%sView all scan results for this website%s', 'security-malware-firewall'),
		'<a target="blank" href="https://cleantalk.org/my/logs_mscan?service='.$usp->service_id.'">',
		'</a>'
	),
	'last_scan_was_just_now'        => __('Website last scan was just now. %s files were scanned.', 'security-malware-firewall'),
	'last_scan_was_just_now_links'  => __('Website last scan was just now. %s files were scanned. %s outbound links were found.', 'security-malware-firewall'),
));

usp_localize_script( 'usp',
        array(
	        'remote_call_token' => strtolower( md5( \Cleantalk\Common\State::getInstance()->settings->key ) )
        )
);

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
                                        ->setHtml_before('<form id="usp_form-settings" action="javascript:void(null);">')
                                        ->setHtml_after('</form>')
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
                                            ->add_field( 'bfp')
                                                ->setTitle('Bruteforce protection')
                                                ->setDescription('Bruteforce protection for login forms.')
                                        ->getParent()
                                            ->add_field('bfp_admin_page')
                                                ->setInput_type('text')
                                                ->setTitle('Admin page URI')
	                                    ->getParent( 2)
	                                    ->add_group( 'malware_scanner')
                                            ->add_field( 'scanner_auto_start' )
                                                ->setTitle('Automatically start scanner')
                                                ->setDescription('Scan website automatically each 24 hours.')
                                            ->getParent()
                                            ->add_field('scanner_heuristic_analysis')
                                                ->setTitle('Heuristic analysis')
                                                ->setDescription('Will search for dangerous code in modified file unknown files.')
                                            ->getParent()
                                            ->add_field('scanner_signature_analysis')
                                                ->setTitle('Signature analysis')
                                                ->setDescription('Will search for known malicious signatures in files.')
                                        ->getParent(2)
                                            ->add_plain()
                                                ->setHtml(
                                                    '<div class="text-center">
                                                        <button type="submit" class="btn btn-setup" id=\'btn-save-settings\' name="action" value="save_settings">Save</button>
                                                        <img class="preloader" src="img/preloader.gif">
                                                    </div>');

                                // Controller
                                $settings
                                     ->add_tab( 'malware_scanner' )
                                        ->add_group( 'common')
                                            ->setTitle('')
	                                        ->add_group( 'common2')
                                            ->setCallback('usp_scanner__display');

                                $settings->draw();

                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>