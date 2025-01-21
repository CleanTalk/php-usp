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
    current_estimated_time = 30;
    total_files_count = null;
    stages_estimated_data = {
        clear_table : {
            'seconds_to_add' : 0,
            'exec_time': 2,
            'exec_time_collected' : false,
            'stage_start_time' : false,
            'est_stage_time': null
        },
        signature_analysis : {
            'seconds_to_add' : 0,
            'exec_time': 5,
            'exec_time_collected' : false,
            'stage_start_time' : false,
            'est_stage_time': null
        },
        heuristic_analysis : {
            'seconds_to_add' : 0,
            'exec_time': 5,
            'stage_start_time' : false,
            'exec_time_collected' : false,
            'est_stage_time': null
        },
        surface_analysis : {
            'seconds_to_add' : 0,
            'exec_time': 2,
            'stage_start_time' : false,
            'exec_time_collected' : false,
            'est_stage_time': null
        },
    }
    estimated_output = null;
    elapsed_output = null;
    scanner_start_time = null;

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

        if( this.scan_percent === 0 && typeof result.total !== 'undefined' )
            this.scan_percent = 100 / result.total;

        if( result.end !== true && result.end !== 1 ){
            this.set_percents( this.percent_completed + result.processed * this.scan_percent );
            this.offset = this.offset + result.processed;
        }else{
            this.set_percents( 100 );
            this.scan_percent = 0;
            this.offset = 0;
        }

        console.log( result );

        this.calculate_estimated_time(result);

        setTimeout(() => {

            // Changing text and percent
            if ( result.end ) {

                this.state = this.get_next_state( this.state );

                if (typeof this.state === 'undefined'){
                    this.end( true );
                    return;
                }

                this.set_percents( 0 );
                this.scan_percent = 0;
                this.offset = 0;
                this.progress_overall.children('span')
                    .removeClass('spbc_bold')
                    .filter('.spbc_overall_scan_status_' + this.state)
                    .addClass('spbc_bold');
            }

            this.controller( result );

        }, 2000);

    };

    calculate_estimated_time(result) {
        let current_time = Math.floor(Date.now() / 1000);
        let elapsedTime = current_time - this.scanner_start_time;

        //cheat to keep the estimated time more than 0
        if ((this.current_estimated_time - elapsedTime) < 15) {
            this.current_estimated_time += 15
        }

        //desc elapsed time
        this.estimated_time_output = this.current_estimated_time - elapsedTime;

        //collect total files and estimated efficiency
        if (result.hasOwnProperty('total') && !this.total_files_count) {
            this.total_files_count = result.total;
        }

        const stage = this.state.toString();

        let currentStageData = this.stages_estimated_data[stage];

        if (!this.stages_estimated_data.hasOwnProperty(stage)) {
            currentStageData = {
                'seconds_to_add' : 0,
                'exec_time': 2,
                'exec_time_collected' : false,
                'stage_start_time' : false,
                'est_stage_time': null
            };
        }

        //collect stage avg time
        if (currentStageData.exec_time_collected === false && +currentStageData.stage_start_time) {
            currentStageData.exec_time =
                current_time - currentStageData.stage_start_time;
            currentStageData.exec_time_collected = true;
            //add est_stage_time
            if (typeof this.amount !== undefined && this.total_files_count && !currentStageData.est_stage_time) {
                currentStageData.est_stage_time = Math.floor(this.total_files_count / this.amount) * currentStageData.exec_time
                this.current_estimated_time += currentStageData.est_stage_time;
            }
        } else {
            currentStageData.stage_start_time = current_time;
        }

        // remove est_stage_time from global estimated
        if (result.hasOwnProperty('end') && result.end && currentStageData.exec_time_collected) {
            const realTimeGoneForStage = current_time - currentStageData.stage_start_time;
            const diffBetweenRealAndEstimatedStageTime = currentStageData.est_stage_time - realTimeGoneForStage;
            this.current_estimated_time = this.current_estimated_time - diffBetweenRealAndEstimatedStageTime;
        }

        //collect how much seconds need to be added for total time
        if (+this.total_files_count && currentStageData.exec_time_collected && typeof this.amount !== undefined) {
            //calc secs
            if (this.amount !== 0 && currentStageData.seconds_to_add === 0) {
                currentStageData.seconds_to_add =
                    Math.floor(this.total_files_count / this.amount) * currentStageData.exec_time
                this.current_estimated_time += currentStageData.seconds_to_add;
            }
        }

        this.stages_estimated_data[stage] = currentStageData;

        function secondsToHms(fullTime) {
            fullTime = Number(fullTime);
            var h = Math.floor(fullTime / 3600);
            var m = Math.floor(fullTime % 3600 / 60);
            var s = Math.floor(fullTime % 3600 % 60);

            var hDisplay = h > 0 ? h + (h == 1 ? " hour, " : " hours, ") : "";
            var mDisplay = m > 0 ? m + (m == 1 ? " minute, " : " minutes, ") : "";
            var sDisplay = s > 0 ? s + (s == 1 ? " second" : " seconds") : "";
            return hDisplay + mDisplay + sDisplay;
        }

        //convert to human-readable
        this.estimated_time_output = secondsToHms(this.estimated_time_output);

        elapsedTime = secondsToHms(elapsedTime);
        //output the result
        this.estimated_output.text(this.estimated_time_output);
        this.elapsed_output.text(elapsedTime);
    }

    set_percents( percent ){
        this.percent_completed = Math.floor( percent * 100 ) / 100;
        this.progressbar.progressbar( 'option', 'value', this.percent_completed );
        this.progressbar_text.text( spbc_ScannerData[ 'progressbar_' + this.state ] + ' - ' + Math.floor( percent * 100 ) / 100 + '%' );
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
                var error_string = 'Unexpected error: ' + status;
                if( typeof error !== 'undefined' )
                    error_string += ' Additional info: ' + error;
                errorOutput( error_string );
            }
        }else if(xhr.status === 500){
            errorOutput( 'Internal server error.');
        }else
            errorOutput( 'Unexpected response code:' + xhr.status );

        if( this.progressbar )
            this.progressbar.fadeOut('slow');

        this.end();

    };

    additional_error(){
      this.end();
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
            no_sql: this.settings['no_sql'],
            background_scan_stop: true,
        };

        // Check UniforceLite installer
        let uriParams = new URL(document.location.toString()).searchParams;
        let uniforceLite = uriParams.get("uniforce_lite");
        if ( uniforceLite === '1' ) {
            data.uniforce_lite = 1;
        }

        var params = {
            data: data,
            type: 'GET',
            successCallback: this.successCallback,
            complete: null,
            errorOutput: this.errorOutput,
            context: this,
            timeout: 40000,
        };

        switch (this.state) {

            case 'get_signatures':     params.timeout = 60000; break;
            case 'clear_table':        this.amount = 1000;     break;
            case 'surface_analysis':   this.amount = 500;      break;
            case 'signature_analysis': this.amount = this.settings['no_sql'] === 1 ? 200 : 250;       break;
            case 'heuristic_analysis': this.amount = this.settings['no_sql'] === 1 ? 200 : 250;      break;
            case 'auto_cure':          this.amount = 5;        break;
            case 'send_results':                               break;
            default:                                           break;
        }

        data.amount = this.amount;

        if( typeof this.state !== 'undefined' )
            ctAJAX( params );

    };

    start(){

        this.state = this.get_next_state( null );

        this.set_percents( 0 );
        this.progressbar.show(500);
        this.progress_overall.show(500);
        this.button.attr('disabled', 'disabled');
        this.spinner.css({display: 'inline'});
        this.scanner_start_time = Math.floor(Date.now() / 1000);

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
