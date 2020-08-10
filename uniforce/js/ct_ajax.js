'use strict';

class CTAJAX{

	url = '';

	// Data to send
	data = null;

	// Optional params
	button       = null;
	spinner      = null;
	progressbar  = null;
	obj          = null;
	context      = this;
	type         = 'POST';
	dataType     = 'json';
	timeout      = 30000;

	obj          = null;

	constructor( params, callback ) {

		if( !! callback ){

			this.data = params;
			this.successCallback = callback;

		}else{

			// Set params
			for( let key in params ){
				if( typeof this[key] !== 'undefined' ){
					this[key] = params[key];
				}
			}

			// Disable button
			if( this.button ){
				this.button = jQuery( this.button );
				this.button.attr('disabled', 'disabled');
				this.button.css('cursor', 'not-allowed');
			}

			// Show spinner
			if( this.spinner ){
				if( typeof this.spinner == 'function' )
					this.spinner();
				if( typeof this.spinner == 'object' ){
					this.spinner = jQuery( this.spinner );
					this.spinner.css('display', 'inline');
				}
			}
		}

		this.data.security = uniforce_security; // Adding security code
		this.url = uniforce_ajax_url; // Adding security code

	}

	success( response ){

		if( !! response.error ){

			this.error(
				{status: 200, responseText: response.error},
				response.error,
				response.msg
			);

		}else{
			if( this.successCallback )
				this.successCallback( response, this.data, this.obj );
		}

	};

	successCallback( response, obj ){
		alert( response );
	}

	complete( response ){

		// Enable button
		if( this.button ){
			this.button.removeAttr('disabled');
			this.button.css('cursor', 'pointer');
		}

		if( this.spinner && typeof this.spinner === 'function') this.spinner();                      // Hide spinner
		if( this.spinner && typeof this.spinner === 'object')   this.spinner.css('display', 'none'); // Hide spinner

	};

	error( xhr, status, error ){

		let errorOutput = typeof this.errorOutput === 'function' ? this.errorOutput : function( msg ){ alert( msg ) };

		console.log( '%c APBCT_AJAX_ERROR', 'color: red;' );
		console.log( status );
		console.log( error );
		console.log( xhr );

		if( xhr.status === 200 ){
			if( status === 'parsererror' ){
				errorOutput( 'Unexpected response from server. See console for details.' );
				console.log( '%c ' + xhr.responseText, 'color: pink;' );
			}else {
				errorOutput( 'Unexpected error:' + status + ' Additional info: ' + error );
			}
		}else if(xhr.status === 500){
			errorOutput( 'Internal server error.');
		}else
			errorOutput( 'Unexpected response code:' + xhr.status );

		if( this.progressbar ) this.progressbar.fadeOut('slow');

	};

	errorOutput( msg ){
		jQuery('.alert-danger').show(300);
		jQuery('#error-msg').text( msg );
	};

	call(){

		let params = {

			data: this.data,

			url: this.url,
			type: this.type,
			context: this.context,
			dataType: this.dataType,
			timeout: this.timeout,

			success: this.success,
			complete: this.complete,
			error: this.error,
		};

		jQuery.ajax( params );

	};

}

function ctAJAX( params, callback ){
	new CTAJAX( params, callback )
		.call();
}