var key_check_timer = 0,
	value    = false,
	is_empty = false,
	is_email = false,
	is_key   = false,
	key_valid = false,
	do_install = false;
	advan_config_show = false,
	email = null,
	user_token = null,
	account_name_ob = null;

/* Custom JS */
jQuery(document).ready(function($) {

	$( ".advanced_conf" ).hide();
	$('.show_more_icon').css('transform','rotate(' + 90 + 'deg)');
	
	/*---------- For Placeholder on IE9 and below -------------*/
	$('input, textarea').placeholder();
	
	/*----------- For icon rotation on input box foxus -------------------*/ 	
	$('input[name="access_key_field"], input[name="login"]').focus(function() {
  		$('.page-icon img').addClass('drop-icon');
	});
	
	/*----------- For icon rotation on input box blur -------------------*/ 	
	$('input[name="access_key_field"], input[name="login"]').blur(function() {
  		$('.page-icon img').removeClass('drop-icon');
	});

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

	// Checking and Highlighting access key onchange
	$('input[name="access_key_field"]').on('input', function(){
		
		clearInterval(key_check_timer);
		
		var field = $(this);
		
		value = field.val().trim(),
		is_empty = value == '' ? true : false,
		is_email = value.search(/^\S+@\S+\.\S+$/) == 0 ? true : false,
		is_key   = value.search(/^[0-9a-zA-Z]*$/) == 0 ? true : false;
		if(is_empty){
			$('.btn-setup').prop('disabled', true);
			return;
		}
		if(!is_key && !is_email){
			$('.btn-setup').prop('disabled', true);
			return;
		}
		if(is_email){
			$('.btn-setup').prop('disabled', false);
			return;
		}
		if(is_key && value.length > 7){
			key_check_timer = setTimeout(function(){
				// field.addClass('loading');
				key_validate( value, field );
			}, do_install ? 5 : 2000);						
		}
	});

	// Install button
	$('.btn-setup').on('click', function(event){
		if(is_email)
			get_key();
		if(!is_email && is_key && key_valid)
			install();
	});


	$('#btn-login').on('click', function(event) {
		login();
	});

	$("#btn-logout").on('click', function(event){
		if(confirm('Are you sure you want to logout?'))
			logout();
	});

	$('#btn-save-settings').on('click', function(event) {
		save_settings();
	});

	$("#btn-uninstall").on('click', function(event){
		if(confirm('Are you sure you want to uninstall the plugin?'))
			uninstall();
	});

	// Close alert
	$(".close").on('click', function(event){
	    $(".alert-danger").hide(300);
	});

	function get_key(){

		let field = $('input[name="access_key_field"]');

		ct_AJAX(
			{
				action: 'get_key',
				email: field.val().trim(),
				security: uniforce_security,
			},
			{
				callback: function(result, data, params, obj) {
					if(result.auth_key){
						do_install = true;
						field.val(result.auth_key);
						field.trigger('input');
						email = result.email ? result.email : null;
					}else{
						$('#setup-form').hide();
						$('.setup-links').hide();
					}
				},
				spinner: function(){field.toggleClass('loading')}
			}
		);
	}

	function key_validate( value, field ){
		ct_AJAX(
			{
				action: 'key_validate',
				key: value,
			},
			{
				callback: function(result, data, params, obj){
					if(result.valid){

						key_valid = true;

						field.css('border', '1px solid #04B66B');
						field.css('box-shadow', '0 0 8px #04B66B');

						$('.btn-setup').prop('disabled', false);

						user_token = result.user_token ? result.user_token : null;
						account_name_ob = result.account_name_ob ? result.account_name_ob : null;

						if(do_install)
							install();
					}else{
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
		ct_AJAX(
			{
				action: 'install',
				key: $('input[name="access_key_field"]').val().trim(),
				addition_scripts: $('input[name="addition_scripts"]').val().trim(),
				admin_password : $('input[name="admin_password"]').val().trim(),
				email: email,
				user_token: user_token,
				account_name_ob: account_name_ob,
			},
			{
				callback: function(result, data, params, obj) {
					if(result.success){
						jQuery('.alert-danger').hide(300);
						$('.alert-success').show(300);
						$('#setup-form').hide();
						$('.setup-links').hide();
					}else{
						do_install = false;
					}
				},
			}
		);
	}

	function login() {
		var login = $('input[name="login"]');
		var password = $('input[name="password"]').length
			? $('input[name="password"]').val().trim()
			: null;
		ct_AJAX(
			{
				action: 'login',
 				login: login.val().trim(),
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

});

