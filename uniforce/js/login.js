jQuery(document).ready(function($) {

    $('#btn-login').on('click', function(event) {
        login();
    });

});
function login() {
    var login = $('input[name="login"]');
    var password = $('input[name="password"]').length
        ? $('input[name="password"]').val().trim()
        : null;
    usp_AJAX(
        {
            action: 'login',
            login: login.val().trim(),
            spbct_login_form: 1,
            password: password,
        },
        {
            callback: function(result, data, params, obj) {
                if (result.passed)
                    location.reload();
            },
            spinner: function(){ login.toggleClass('loading') }
        }
    );
}

