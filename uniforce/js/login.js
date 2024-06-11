jQuery(document).ready(function($) {

    $('#login-form').submit(function(event){
        event.preventDefault();
        login();
    });

});
function login() {
    var login = $('input[name="login"]');
    var password = $('input[name="password"]').length
        ? $('input[name="password"]').val().trim()
        : null;
    ctAJAX({
        data: {
            action: 'login',
            login: login.val().trim(),
            spbct_login_form: 1,
            password: password,
        },
        successCallback: function(result, data, params, obj) {
            if (result.passed) {
                //if session cookies is cached try to set cookie via js
                if (document.cookie.indexOf("authentificated") === -1 && typeof result.hash !== 'undefined') {
                    ctSetCookie("authentificated", result.hash);
                }
                location.reload();
            }
        },
        spinner: function(){ login.toggleClass('loading') }
    });
}

