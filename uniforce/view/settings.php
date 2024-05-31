<?php

use Cleantalk\USP\Common\Err;
use Cleantalk\USP\Common\State;
use Cleantalk\USP\Uniforce\FireWall;

// Exit if accessed directly.
if ( ! defined( 'CT_USP_ROOT' ) ) {
    header('HTTP/1.0 403 Forbidden');
    exit ();
}

require_once CT_USP_INC . 'settings.php';
require_once CT_USP_INC . 'scanner.php';

usp_localize_script( 'spbc_TableData',
    array(
        'warning_bulk'       => uniforce_translate('Are sure you want to perform these actions?', 'security-malware-firewall'),
        'warning_default'    => uniforce_translate('Do you want to proceed?', 'security-malware-firewall'),
        'warning_delte'      => uniforce_translate('This can\'t be undone and could damage your website. Are you sure?', 'security-malware-firewall'),
        'warning_replace'    => uniforce_translate('This can\'t be undone. Are you sure?', 'security-malware-firewall'),
        'warning_quarantine' => uniforce_translate('This can\'t be undone and could damage your website. Are you sure?', 'security-malware-firewall'),
    )
);

$localize_array = array(

    // PARAMS

    // Settings / Statuses
    'scaner_enabled'    => State::getInstance()->valid ? 1 : 0,
    'scaner_status'     => State::getInstance()->valid ? 1 : 0,
    'check_heuristic'   => State::getInstance()->settings->scanner_heuristic_analysis  ? 1 : 0,
    'check_signature'   => State::getInstance()->settings->scanner_signature_analysis  ? 1 : 0,
//	'wp_content_dir'    => realpath(WP_CONTENT_DIR),
    'wp_root_dir'       =>  realpath(CT_USP_SITE_ROOT),
    // Params
    'on_page' => 20,

    'settings' => array(

        // Common
        'no_sql' => State::getInstance()->data->no_sql ? 1 : 0,

        // Do not create DB if created or OpenSSL is not installed
        'scanner_create_db'          => State::getInstance()->data->db_created || State::getInstance()->data->no_sql
            ? 0
            : 1,
        'scanner_surface_analysis'   => State::getInstance()->data->no_sql ? 0 : 1,
        'scanner_get_approved'       => State::getInstance()->data->no_sql ? 0 : 1,
        'scanner_heuristic_analysis' => State::getInstance()->settings->scanner_heuristic_analysis,
        'scanner_get_signatures'     => State::getInstance()->settings->scanner_signature_analysis,
        'scanner_signature_analysis' => State::getInstance()->settings->scanner_signature_analysis,
        'scanner_auto_cure'          => State::getInstance()->settings->scanner_auto_cure,
        'scanner_outbound_links'     => State::getInstance()->settings->scanner_outbound_links,
        'scanner_frontend_analysis'  => State::getInstance()->settings->scanner_frontend_analysis,
    ),

    //TRANSLATIONS

    //Confirmation
    'scan_modified_confiramation'           => uniforce_translate( 'There is more than 30 modified files and this could take time. Do you want to proceed?', 'security-malware-firewall' ),
    'warning_about_cancel'                  => uniforce_translate( 'Scan will be performed in the background mode soon.', 'security-malware-firewall' ),
    'delete_warning'                        => uniforce_translate( 'Are you sure you want to delete the file? It can not be undone.' ),
    // Buttons
    'button_scan_perform'                   => uniforce_translate('Perform scan', 'security-malware-firewall'),
    'button_scan_pause'                     => uniforce_translate('Pause scan',   'security-malware-firewall'),
    'button_scan_resume'                    => uniforce_translate('Resume scan',  'security-malware-firewall'),
    // Progress bar
    'progressbar_create_db'                 => uniforce_translate('Creating remote database',        'security-malware-firewall'),
    'progressbar_get_signatures'            => uniforce_translate('Receiving signatures', 'security-malware-firewall'),
    'progressbar_clear_table'               => uniforce_translate('Preparing',        'security-malware-firewall'),
    'progressbar_get_hashes'                => uniforce_translate('Receiving hashes', 'security-malware-firewall'),
    'progressbar_get_approved'              => uniforce_translate('Receiving approved files', 'security-malware-firewall'),
    // Scanning core
    'progressbar_count_files'               => uniforce_translate('Counting files',             'security-malware-firewall'),
    'progressbar_surface_analysis'          => uniforce_translate('Scanning for modifications', 'security-malware-firewall'),
    'progressbar_signature_analysis'        => uniforce_translate('Searching for signatures',    'security-malware-firewall'),
    'progressbar_heuristic_analysis'        => uniforce_translate('Heuristic analysis',         'security-malware-firewall'),
    //Cure
    'progressbar_cure_backup'               => uniforce_translate('Backup', 'security-malware-firewall'),
    'progressbar_count_cure'                => uniforce_translate('Count cure', 'security-malware-firewall'),
    'progressbar_cure'                      => uniforce_translate('cure', 'security-malware-firewall'),
    // Links
    'progressbar_count_links'               => uniforce_translate('Counting links', 'security-malware-firewall'),
    'progressbar_scan_links'                => uniforce_translate('Scanning links', 'security-malware-firewall'),
    // Frontend
    'progressbar_frontend_count'            => uniforce_translate('Counting pages', 'security-malware-firewall'),
    'progressbar_frontend_scan'             => uniforce_translate('Scanning pages', 'security-malware-firewall'),
    // Other
    'progressbar_send_results'              => uniforce_translate('Sending results', 'security-malware-firewall'),
    // Warnings
    'result_text_bad_template'              => uniforce_translate('Recommend to scan all (%s) of the found files to make sure the website is secure.', 'security-malware-firewall'),
    'result_text_good_template'             => uniforce_translate('No threats are found.', 'security-malware-firewall'),
    //Misc
    'look_below_for_scan_res'               => uniforce_translate('Look below for scan results.', 'security-malware-firewall'),
    'last_scan_was_just_now'        => uniforce_translate('Website last scan was just now. %s files were scanned.', 'security-malware-firewall'),
    'last_scan_was_just_now_links'  => uniforce_translate('Website last scan was just now. %s files were scanned. %s outbound links were found.', 'security-malware-firewall'),
);

if ( ! CT_USP_UNIFORCE_LITE ) {
    $localize_array['view_all_results'] = sprintf(
        uniforce_translate('</br>%sView all scan results for this website%s', 'security-malware-firewall'),
        '<a target="blank" href="https://cleantalk.org/my/logs_mscan?service=' . Cleantalk\USP\Common\State::getInstance()->service_id . '&user_token='. Cleantalk\USP\Common\State::getInstance()->user_token .'">',
        '</a>'
    );
}

usp_localize_script( 'spbc_ScannerData', $localize_array);

usp_localize_script( 'usp',
        array(
	        'remote_call_token' => strtolower( md5( State::getInstance()->settings->key ) )
        )
);

?>

<body class="fade-in">
    <div class="container" id="layout-block">
        <div class="row" style="margin-top: 80px">
            <div class="col-sm-12 settings-box">
                <div class="clearfix"></div>

                <?php if ( ! CT_USP_UNIFORCE_LITE ) { ?>

                    <!-- Uninstall Logout buttons -->
                    <div class="settings-links">
                        <a href="#" id='btn-logout'>Log out </a>
                    </div>

                <?php } ?>

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
                        <p id='error-msg'><?php $error = Err::check_and_output(); echo isset( $error ) ? $error : ''; ?></p>
                    </div>
                    <!-- End Error box -->

                    <div class="row">
                        <div class="col-xs-12 col-sm-12 col-md-12 col-lg-12">

                            <?php

                            $settings = new \Cleantalk\USP\Layout\Settings();

                            if ( ! CT_USP_UNIFORCE_LITE  ) {
                                // Tab summary
                                $settings
                                    ->add_tab('summary')
                                    ->setActive()
                                    ->add_group('your_security_dashboard')
                                    ->add_plain('dashboard')
                                    ->setHtml('<p class="text-center">Check detailed statistics on <a href="https://cleantalk.org/my' . ( State::getInstance()->data->user_token ? '?cp_mode=security&user_token=' . State::getInstance()->data->user_token : '') . '" target="_blank">your Security dashboard</a></p>')
                                    ->getParent(2)
                                    ->add_group('plugin_state')
                                    ->add_plain('123')
                                    ->setCallback('usp_settings__plugin_state')
                                    ->getParent(2)
                                    ->add_group('statistics')
                                    ->add_plain('stat')
                                    ->setCallback('usp_settings__show_fw_statistics')
                                    ->getParent(2)
                                    ->add_group('malware_scanner_statistics')
                                    ->add_plain('stat')
                                    ->setCallback('usp_settings__show_scanner_statistics')
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
                                    ->setHtml_after('</p>Account registered for email: ' . State::getInstance()->data->account_name_ob . '</p>')
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
                                    ->setDescription('Specify the site admin area page address to protect it from brute-force attacks. <br />Example: http://yoursite.com/admin_area')
                                    ->getParent()
                                    ->add_field('bfp_login_form_fields')
                                    ->setInput_type('text')
                                    ->setTitle('Add the field names presented in the login form (For unknown CMS)')
                                    ->setDescription('Specify the unique fields names of the login form. These fields input will be checked by brute-force protection. No quotes, separated by comma. <br /> Example: user_name_custom, user_pwd_custom')
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
                                    ->getParent( 2)
                                    ->add_group('Change password')
                                    ->add_field('old_password')
                                    ->set_title('Type the old password')
                                    ->setInput_type('password')
                                    ->getParent()
                                    ->add_field('new_password')
                                    ->set_title('Type the new password')
                                    ->setInput_type('password')
                                    ->getParent()
                                    ->add_field('new_password_confirm')
                                    ->set_title('Confirm the new password')
                                    ->setInput_type('password')
                                    ->getParent()
                                    ->add_field('change_admin_password')
                                    ->setInput_type('button')
                                    ->setTitle('Change password')
                                    ->setDescription('Changing admin password<img class="preloader" src="img/preloader.gif">')
                                    ->getParent()
                                    ->add_group( 'Danger Zone' )
                                    ->add_field( 'uninstall' )
                                    ->setInput_type( 'button' )
                                    ->setDisabled( true )
                                    ->setTitle( 'Uninstall' )
                                    ->setDescription( 'Completely uninstall the module from site.' )
                                    ->getParent()
                                    ->add_plain()
                                    ->setHtml(
                                        '<input
                                                    id="ctusp_field---uninstall_confirmation"
                                                    form="none"
                                                    type="text"
                                                    placeholder="Type \'uninstall\' to enable button"
                                                    class="" value=""
                                                    style="display: inline-block; width: 220px; padding: 6px 12px; border-radius: 4px; border: 1px #999 solid;">
                                                 ')
                                    ->getParent(2)
                                    ->add_plain()
                                    ->setHtml(
                                        '<div class="text-center">
                                                    <button type="submit" class="btn btn-setup" id=\'btn-save-settings\' name="action" value="save_settings">Save</button>
                                                    <img class="preloader" src="php-usp-For-uniforce-lite/uniforce/img/preloader.gif">
                                                </div>');
                            }
                            // Controller
                            $settings
                                ->add_tab( 'malware_scanner' )
                                ->add_group( 'common')
                                ->setTitle('')
                                ->add_group( 'common2')
                                ->setCallback(
                                    'usp_scanner__display'
                                    . ( State::getInstance()->data->no_sql ? '___no_sql' : '' )
                                );

                            $settings->draw();
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
