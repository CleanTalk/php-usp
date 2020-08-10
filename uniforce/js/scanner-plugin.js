'use strict';

class spbc_Scanner{

    first_start = true;

    root  = '';
    settings = '';
    states = [
        'create_db',
        'get_signatures',
        'clear_table',
        'surface_analysis',
        'get_approved',
        'signature_analysis',
        'heuristic_analysis',
        // 'auto_cure',
        // 'frontend_analysis',
        // 'outbound_links',
        'send_results',
    ];
    state = null;
    offset = 0;
    $amount = 0;
    total_scanned = 0;
    scan_percent = 0;
    percent_completed = 0;

    paused_status = '';
    paused = false;

    button = null;
    spinner = null;

    progress_overall = null;
    progressbar = null;
    progressbar_text = null;

    timeout = 60000;

    constructor ( settings ) {

        console.log('init');

        for( let key in settings ){
            if( typeof this[key] !== 'undefined' ){
                this[key] = settings[key];
            }
        }

    };

    get_next_state(state) {

        state = state === null ? this.states[0] : this.states[ this.states.indexOf( state ) + 1 ];

        if (typeof this.settings[ 'scanner_' + state ] !== 'undefined' && this.settings[ 'scanner_' + state ] === 0)
            state = this.get_next_state( state );

        return state;
    };

    // Processing response from backend
    successCallback( result ){

        this.scan_percent = result.total !== 0 ? 100 / result.total : 1;

        if( result.end !== true && result.end !== 1 ){
            this.set_percents( this.percent_completed + Math.floor( result.processed / this.scan_percent ) );
            this.offset = this.offset + result.processed;
        }else{
            this.set_percents( 100 );
            this.offset = 0;
        }

        console.log( result );

        setTimeout(() => {

            // Changing text and percent
            if ( result.end ) {

                this.state = this.get_next_state( this.state );

                if (typeof this.state === 'undefined'){
                    this.end( true );
                    return;
                }

                this.set_percents( 0 );
                this.progress_overall.children('span')
                    .removeClass('spbc_bold')
                    .filter('.spbc_overall_scan_status_' + this.state)
                    .addClass('spbc_bold');
            }

            this.controller( result );

        }, 2000);

    };

    set_percents( percent ){
        this.percent_completed = percent;
        this.progressbar.progressbar( 'option', 'value', this.percent_completed );
        this.progressbar_text.text( spbc_ScannerData[ 'progressbar_' + this.state ] + ' - ' + this.percent_completed + '%' );
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

        this.end( false );

    };

    errorOutput( msg ){
        alert( msg );
    };

    controller( result ) {

        // // AJAX params
        let data = {
            spbc_remote_call_token: usp.remote_call_token,
            spbc_remote_call_action: 'scanner__' + this.state, // Adding security code
            plugin_name: 'spbc', // Adding security code
            offset: this.offset,
        };

        switch (this.state) {

            case 'clear_table':        this.amount = 1000; break;
            case 'surface_analysis':   this.amount = 500; break;
            case 'signature_analysis': this.amount = 10; break;
            case 'heuristic_analysis': this.amount = 10; break;
            case 'auto_cure':          this.amount = 5; break;
            case 'send_results':

                break;

            default:

                break;
        }

        data.amount = this.amount;

        if( typeof this.state !== 'undefined' )
            ctAJAX({
                data: data,
                type: 'GET',
                successCallback: this.successCallback,
                complete: null,
                errorOutput: this.errorOutput,
                context: this,
            });

    };

    start(){

        this.state = this.get_next_state( null );

        this.set_percents( 0 );
        this.progressbar.show(500);
        this.progress_overall.show(500);
        this.button.attr('disabled', 'disabled');
        this.spinner.css({display: 'inline'});

        setTimeout(() => {
            this.controller();
        }, 1000);

    };

    end( reload ){

        this.progressbar.hide(500);
        this.progress_overall.hide(500);
        this.button.removeAttr('disabled', 'disabled');
        this.spinner.css({display: 'none'});
        this.state = this.states[0];
        this.total_links = 0;
        this.plug = false;
        this.total_scanned = 0;

        if(reload){
            document.location = document.location;
        }

    };

};