/* Custom JS */
jQuery(document).ready(function($) {

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

	// Close alert
	$(".close").on('click', function(event){
		$(".alert-danger").hide(300);
	});
});