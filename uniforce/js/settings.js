jQuery(document).ready(function() {

    $('#btn-save-settings').on('click', function(event) {
        save_settings();
    });
    $("#btn-logout").on('click', function(event){
        if(confirm('Are you sure you want to logout?'))
            logout();
    });

    // Uninstall button
    $("#ctusp_field---uninstall_confirmation").on('input', function(event){
        console.log(event.target);
        let val = $(event.target).val(),
            id = 'ctusp_field---uninstall';
        if(val === 'uninstall')
            uspSettingsDependencies(id, 1);
        else
            uspSettingsDependencies(id, 0);
    });
    $("#ctusp_field---uninstall").on('click', function(event){
        if(confirm('Are you sure you want to uninstall the plugin?'))
            uninstall();
    });

    jQuery('.ctusp_tab_navigation').on('click', '.ctusp_tab_navigation-title', function (event) {
        usp_switchTab(event.currentTarget);
    });

    let tab_name = document.getElementsByClassName('ctusp_tab_navigation-title---'
        + (location.search.match(/tab=(\S*?)(&|$)/)
            ? location.search.match(/tab=(\S*?)(&|$)/)[1]
            : '')
        )[0] || 'summary';

    // Switch tab
    usp_switchTab(tab_name);

});

function usp_switchTab(tab, params){

    tab = typeof tab === 'string' ? document.getElementsByClassName('ctusp_tab_navigation-title---'+tab)[0] : tab;
    var tab_name = tab.classList[1].replace('ctusp_tab_navigation-title---', '');

    jQuery('.ctusp_tab_navigation-title').removeClass('ctusp_tab_navigation-title--active');
    jQuery('.ctusp_tab').removeClass('ctusp_tab--active');
    jQuery(tab).addClass('ctusp_tab_navigation-title--active');
    jQuery('.ctusp_tab---'+tab_name).addClass('ctusp_tab--active');

    // AJAX load
    if(false && !jQuery(tab).data('loaded')){
        // var data = {
        //     action: 'spbc_settings__draw_elements',
        //     tab_name: tab_name,
        //     security: spbcSettings.ajax_nonce
        // };
        // var params = {
        //     callback: spbc_draw_settings_callback,
        //     notJson: true,
        //     additional: params || null,
        // };
        // usp_AJAX( data, params, tab );
    }else if(params && params.action){
        switch (params.action){
            case 'highlight':
                usp_HighlightElement(params.target, params.times);
                break;
            case 'click':
                setTimeout(function(){
                    jQuery('#'+params.additional.target).click();
                }, 500);
                break;
        }
    }
}

function logout() {
    usp_AJAX(
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

    let form = document.getElementById('usp_form-settings');
    let data = {};

    for ( let key in form.elements ){
        if(!isNaN(+key)){
            let attr = form.elements[key].name;
            let val;

            // Get value for certain type if input
            switch (form.elements[key].type) {
                case 'checkbox':
                    val = form.elements[key].checked ? 1 : 0;
                    break;
                // text, submit, button, textarea
                default:
                    val = form.elements[key].value;
                    break;
                // @todo get every type of input
            }

            data[ attr ] = val;
        }
    }

    usp_AJAX(
        data,
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
                    setTimeout(function(){ location.href='?tab=settings'; }, 3000 );
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
    usp_AJAX(
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

// Hightlights element
function usp_HighlightElement(selector, times){
    times = times-1 || 0;
    jQuery(selector).addClass('ctusp--highlighted');
    jQuery(selector).animate({opacity: "0.4" }, 600, 'linear', function(){
        jQuery(selector).animate({opacity: "1" }, 600, 'linear', function(){
            if(times>0){
                usp_HighlightElement(selector, times);
            }else{
                jQuery(selector).removeClass('ctusp--highlighted');
            }
        });
    });
}

// Settings dependences
function uspSettingsDependencies(settingsIDs, enable){

    if(typeof settingsIDs === 'string'){
        tmp = [];
        tmp.push(settingsIDs);
        settingsIDs = tmp;
    }

    enable = typeof enable === 'undefined' ? null : +enable;

    settingsIDs.forEach(function(settingID, i, arr){

        // settingID = settingID.indexOf( 'spbc_setting_' ) === -1 ? 'spbc_setting_'+settingID : settingID;

        console.log(settingID);

        var elem = document.getElementById(settingID),
            do_disable = function(){elem.setAttribute('disabled', 'disabled');},
            do_enable  = function(){elem.removeAttribute('disabled');};

        if(enable !== null) // Set
            enable === 1 ? do_enable() : do_disable();
        else // Switch
            elem.getAttribute('disabled') === null ? do_disable() : do_enable();

    });
}