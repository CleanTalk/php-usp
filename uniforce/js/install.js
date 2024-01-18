var key_check_timer   = 0,
    value             = false,
    is_email          = false,
    is_key            = false,
    is_password       = false,
    key_valid         = false,
    do_install        = false,
    advan_config_show = false,
    email             = null,
    user_token        = null,
    account_name_ob   = null,
    account_name      = null;

jQuery(document).ready(function($) {

    $( ".advanced_conf" ).hide();
    $('.show_more_icon').css('transform','rotate(' + 90 + 'deg)');

    // Checking and Highlighting access key onchange
    $('input[name="access_key_field"]').on('input', function(){

        clearTimeout(key_check_timer);

        var field = $(this);
        var value = field.val().trim();

        is_key   = value.search(/^[0-9a-zA-Z]*$/) === 0;

        if(is_key && value.length > 7){
            key_check_timer = setTimeout(function(){
                // field.addClass('loading');
                key_validate( value, field );
            }, do_install ? 5 : 2000);
        }
    });

    // Checking and Highlighting access key onchange
    $('input[name="email_field"]').on('input', function(){

        is_email = $(this).val().trim().search(/^\S+@\S+\.\S+$/) === 0;

        validate_installation();
    });

    // Checking and Highlighting access key onchange
    $('input[name="admin_password"]').on('input', function(){

        var field = $(this),
            value = $(this).val();

        is_password =  value.length >= 4  && value.search(/^[^\s]*$/) === 0;

        validate_installation();

        if( is_password ){
            $('#password_requirements').hide();
            field.css('border', '1px solid #04B66B');
            field.css('box-shadow', '0 0 8px #04B66B');
        }else{
            $('#password_requirements').show();
            field.css('box-shadow', '0 0 8px #F44336');
            field.css('border', '1px solid #F44336');
        }
    });

    // Advanced configuration btn
    $('#show_more_btn').click(function(){
        if (!advan_config_show) {
            $('.show_more_icon').css('transform','rotate(' + 0 + 'deg)');
            advan_config_show = true;
            $( ".advanced_conf" ).show();
        }
        else {
            $('.show_more_icon').css('transform','rotate(' + 90 + 'deg)');
            advan_config_show = false;
            $( ".advanced_conf" ).hide();

        }

    });

    // Install button
    $('.btn-setup').on('click', function(event){
        $('#install_preloader').css("display", "block");
        if( ! key_valid ){
            get_key();
            $('#install_preloader').css("display", "none");
        }
        else
            install();
    });

});

function validate_installation(){
    const installButton = $('.btn-setup');
    installButton.prop(
        'disabled',
        ! ( is_email && is_password )
    );
    if (!installButton.prop(
        'disabled')) {
        installButton.css('color', 'white')
    } else {
        installButton.css('color', 'inherit')
    }
}

function get_key(){

    let field = $('input[name="email_field"]');

    ctAJAX({
        data: {
            action: 'get_key',
            email: field.val().trim(),
            security: uniforce_security,
        },
        successCallback: function(result) {
            if(result.auth_key){
                do_install = true;
                $('input[name="access_key_field"]').val(result.auth_key)
                    .trigger('input');
            }else{
                $('#setup-form').hide();
                $('.setup-links').hide();
            }
        },
        spinner: function(){field.toggleClass('loading')}
    });
}

function key_validate( value, field ){
    ctAJAX(
        {
            data: {
                action: 'key_validate',
                key: value,
            },
            successCallback: function( result ){

                console.log( result );

                if(result.valid){

                    console.log( 'go!' );

                    key_valid = true;

                    validate_installation();


                    field.css('border', '1px solid #04B66B');
                    field.css('box-shadow', '0 0 8px #04B66B');

                    user_token      = result.user_token ? result.user_token : null;
                    account_name    = result.account_name ? result.account_name : null;
                    account_name_ob = result.account_name_ob ? result.account_name_ob : null;

                    if(do_install)
                        install();
                }else{
                    console.log( 'no go!' );
                    field.css('box-shadow', '0 0 8px #F44336');
                    field.css('border', '1px solid #F44336');
                    $('.btn-setup').prop('disabled', true);
                    do_install = false;
                }
                field.prop('disabled', false);
                field.blur();
            },
            spinner: function(){ field.toggleClass('loading') }
        }
    );
}

function install(){
    ctAJAX(
        {
            action: 'install',
            key: $('input[name="access_key_field"]').val().trim(),
            addition_scripts: $('input[name="addition_scripts"]').val().trim(),
            admin_password : $('input[name="admin_password"]').val().trim(),
            no_sql : $('input[name="no_sql"]').length,
            email: $('input[name="email_field"]').val().trim(),
            user_token: user_token,
            account_name_ob: account_name_ob,
        },
        function(result, data, params, obj) {
            if(result.success){
                jQuery('.alert-danger').hide(300);
                $('.alert-success').show(300);
                $('#setup-form').hide();
                $('.setup-links').hide();
            }else{
                do_install = false;
            }
    });
}
