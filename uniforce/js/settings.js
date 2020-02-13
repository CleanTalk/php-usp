jQuery(document).ready(function() {

    $('#btn-save-settings').on('click', function(event) {
        save_settings();
    });
    $("#btn-logout").on('click', function(event){
        if(confirm('Are you sure you want to logout?'))
            logout();
    });

    $("#btn-uninstall").on('click', function(event){
        if(confirm('Are you sure you want to uninstall the plugin?'))
            uninstall();
    });

    jQuery('.ctusp_tab_navigation').on('click', '.ctusp_tab_navigation-title', function (event) {
        spbc_switchTab(event.currentTarget);
    });

    let ctusp_tab = document.getElementsByClassName('ctusp_tab_navigation-title---settings')[0];

    // Switch tab
    if (ctusp_tab)
        spbc_switchTab(ctusp_tab);

});

function spbc_switchTab(tab, params){

    console.log(tab);

    var tab_name = tab.classList[1].replace('ctusp_tab_navigation-title---', '');

    jQuery('.ctusp_tab_navigation-title').removeClass('ctusp_tab_navigation-title--active');
    jQuery('.ctusp_tab').removeClass('ctusp_tab--active');
    jQuery(tab).addClass('ctusp_tab_navigation-title--active');
    jQuery('.ctusp_tab---'+tab_name).addClass('ctusp_tab--active');

    // if(!jQuery(tab).data('loaded')){
    //     var data = {
    //         action: 'spbc_settings__draw_elements',
    //         tab_name: tab_name,
    //         security: spbcSettings.ajax_nonce
    //     };
    //     var params = {
    //         callback: spbc_draw_settings_callback,
    //         notJson: true,
    //         additional: params || null,
    //     };
    //     spbc_sendAJAXRequest( data, params, tab );
    // }else if(params && params.action){
    //     switch (params.action){
    //         case 'highlight':
    //             spbcHighlightElement(params.target, params.times);
    //             break;
    //         case 'click':
    //             setTimeout(function(){
    //                 jQuery('#'+params.additional.target).click();
    //             }, 500);
    //             break;
    //     }
    // }
}

function logout() {
    ct_AJAX(
        {
            action: 'logout',
        },
        {
            callback: function(result, data, params, obj) {
                if (result.success)
                    location.reload();
            },
        }
    );
}

function save_settings(){
    ct_AJAX(
        {
            action: 'save_settings',
            apikey: $('input[name="apikey"]').val().trim(),
            uniforce_sfw_protection: $('#uniforce_sfw_protection').is(':checked') ? 1 : 0,
            uniforce_waf_protection: $('#uniforce_waf_protection').is(':checked') ? 1 : 0,
            uniforce_bfp_protection: $('#uniforce_bfp_protection').is(':checked') ? 1 : 0,
            uniforce_bfp_protection_url: $('#uniforce_bfp_protection_url').val().trim(),
        },
        {
            callback: function(result, data, params, obj) {
                if (result.success) {
                    $("body").overhang({
                        type: "success",
                        message: "Settings saved! Page will be updated in 3 seconds.",
                        duration: 3,
                        overlay: true,
                        // closeConfirm: true,
                        easing: 'linear'
                    });
                    setTimeout(function(){ location.reload(); }, 3000 );
                }
            },
            spinner: $('#btn-save-settings+.preloader'),
            button: $('#btn-save-settings'),
            error_handler: function(result, data, params, obj){
                $("body").overhang({
                    type: "error",
                    message: 'Error: ' + result.error,
                    duration: 43200,
                    overlay: true,
                    closeConfirm: true,
                    easing: 'linear'
                });
            }
        }
    );
}

function uninstall(){
    ct_AJAX(
        {
            action: 'uninstall',
        },
        {
            callback: function(result, data, params, obj) {
                if(result.success){
                    location.reload();
                }
            },
        }
    );

}