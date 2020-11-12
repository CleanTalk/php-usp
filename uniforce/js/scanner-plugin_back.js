(function($){

    // Wrappers
    $.spbc = {

        scanner: {

            // Controller
            control: function(result, data, start) { return spbcObj.spbcScannerPlugin('control', result, data, start) },

            // Common
            data:  function(param, data) { return spbcObj.spbcScannerPlugin('data', param, data)  },
            ajax:  function(data)        { return spbcObj.spbcScannerPlugin('ajax', data)         },
            start: function()            { return spbcObj.spbcScannerPlugin('start')              },
            end:   function()            { return spbcObj.spbcScannerPlugin('end')                },
            pause: function(result, data){ return spbcObj.spbcScannerPlugin('pause', result, data)},
            resume:function()            { return spbcObj.spbcScannerPlugin('resume')             },

            // Debug
            clear:           function() { return spbcObj.spbcScannerPlugin('clear')          },
            clear_callback:  function() { return spbcObj.spbcScannerPlugin('clear_callback') },

            // Actions

            clearTable:    function()                     { return spbcObj.spbcScannerPlugin('clearTable')},
            count:         function()                     { return spbcObj.spbcScannerPlugin('count')},
            scan:          function(status)               { return spbcObj.spbcScannerPlugin('scan',  status)},
            scanModified_sign:  function(status)          { return spbcObj.spbcScannerPlugin('scanModified_sign',  status)},
            scanModified_heur:  function(status)          { return spbcObj.spbcScannerPlugin('scanModified_heur',  status)},
            scanLinks:     function()                     { return spbcObj.spbcScannerPlugin('scanLinks')},
            sendResults:   function()                     { return spbcObj.spbcScannerPlugin('sendResults')},

            // Callbacks
            count_callback:         function(result, data) { return spbcObj.spbcScannerPlugin('count_callback',         result, data) },
            scanModified_callback:  function(result, data) { return spbcObj.spbcScannerPlugin('scanModified_callback',  result, data) },
            sendResults_callback:   function(result, data) { return spbcObj.spbcScannerPlugin('sendResults_callback',   result, data) },

        },
    };

    $.fn.spbcScannerPlugin = function( param ){

        var scanner = jQuery.spbc.scanner;

        // Methods
        var methods = {
            init: function(settings) {
                console.log('init');
                this.data(settings);
                window.spbcObj = this;
            },
            start: function(opt){
                opt.progressbar.show(500)
                    .progressbar('option', 'value', 0);
                opt.progress_overall.show(500);
                opt.button.html(spbcScaner.button_scan_pause);
                opt.spinner.css({display: 'inline'});
            },
            end: function(opt){
                opt.progressbar.hide(500)
                    .progressbar('option', 'value', 100);
                opt.progress_overall.hide(500);
                opt.button.html(spbcScaner.button_scan_perform);
                opt.spinner.css({display: 'none'});
                this.removeData('status');
                this.data('total_links', 0)
                    .data('plug', false)
                    .data('total_scanned', 0);
            },
            resume: function(opt){
                console.log('RESUME');
                opt.button.html(spbcScaner.button_scan_pause);
                opt.spinner.css({display: 'inline'});
                opt.paused = false;
            },
            pause: function(result, data, opt){
                console.log('PAUSE');
                opt.button.html(spbcScaner.button_scan_resume);
                opt.spinner.css({display: 'none'});
                opt.result = result;
                opt.data = data;
                opt.paused = true;
            },
            data: function(param, data){
                if(typeof data === 'undefined'){
                    if(param === 'all')
                        return this.data();
                    return this.data(param);
                }
                this.data(param, data);
            },
            clear: function(){
                console.log('CLEAR');
                scanner.start();
                this.data('scan_status', 'clear')
                    .data('callback', scanner.clear_callback);
                var data = { action : 'spbc_scanner_clear' };
                scanner.ajax(data);
            },
            clear_callback: function(){
                console.log('CLEARED');
                scanner.end();
            },

            // AJAX request
            ajax: function(data, opt){

                // Default prarams
                var notJson = this.data('notJson') || false;

                console.log(opt.status);

                // Changing text and precent
                if(opt.prev_action != data.action && typeof opt.progressbar !== 'undefined'){
                    opt.progress_overall.children('span')
                        .removeClass('spbc_bold')
                        .filter('.spbc_overall_scan_status_'+opt.status)
                        .addClass('spbc_bold');
                    opt.progressbar.progressbar('option', 'value', 0);
                    opt.progressbar_text.text(spbcScaner['progressbar_'+opt.status] + ' - 0%');
                }
                this.data('prev_action', data.action);

                // Default params
                data.spbc_remote_call_token = usp.remote_call_token; // Adding security code
                data.spbc_remote_call_action = 'scanner__' + opt.status; // Adding security code
                data.plugin_name = 'spbc'; // Adding security code

                jQuery.ajax({
                    type: "GET",
                    url: uniforce_ajax_url,
                    data: data,
                    success: function(result){
                        if(!notJson) result = JSON.parse(result);
                        if(result.error){
                            console.log(result); console.log(data);	console.log(opt);
                            alert('Error happens: ' + (result.error || 'Unkown'));
                            setTimeout(function(){ scanner.end(); }, 1000);
                        }else{
                            console.log(result); console.log(data);	console.log(opt);
                            if(result.processed)
                                opt.button.data('precent_completed', opt.precent_completed + result.processed / opt.scan_precent);
                            if(typeof opt.progressbar !== 'undefined'){
                                opt.progressbar.progressbar('option', 'value', Math.floor(opt.precent_completed));
                                opt.progressbar_text.text(spbcScaner['progressbar_'+opt.status] + ' - ' + Math.floor(opt.precent_completed) + '%');
                            }
                            if(typeof opt.callback !== 'undefined'){
                                setTimeout(function(){
                                    opt.callback(result, data);
                                }, 1000);
                            }
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown){
                        scanner.end();
                        console.log('SPBC_AJAX_ERROR');
                        console.log(jqXHR);
                        console.log(textStatus);
                        console.log(errorThrown);
                        if(errorThrown)
                            alert(errorThrown);
                    },
                    timeout: opt.timeout,
                });
            },

            // CONTROL

            control: function(result, data, action, opt){

                this.data('callback', scanner.control)
                    .data('precent_completed', 100);

                if(typeof action !== 'undefined' && action){
                    if(opt.status == null){
                        scanner.start();
                        scanner.clearTable();
                        return;
                    }else{
                        if(opt.paused == true){
                            scanner.resume();
                            result = opt.result;
                            data = opt.data;
                        }else{
                            scanner.pause(result, data);
                            return;
                        }
                    }
                }
                if(opt.paused == true) return;

                setTimeout(function(){
                    switch(opt.status){

                        case 'clear_table':
                            scanner.count();
                            break;

                        case 'count_files':
                            if(result.total > 30)
                                opt.warnings.long_scan.show(500);
                            if( +spbcScaner.check_signature ){ scanner.scanModified_sign('UNKNOWN,MODIFIED,OK,INFECTED', 'NO,YES_HEURISTIC');  return;}
                            if( +spbcScaner.check_heuristic ){ scanner.scanModified_heur('UNKNOWN,MODIFIED,OK,INFECTED', 'NO,YES_SIGNATURE');  return;}
                            scanner.sendResults();
                            break;

                        case 'scan_signatures':
                            if( +spbcScaner.check_heuristic ){ scanner.scanModified_heur('UNKNOWN,MODIFIED,OK,INFECTED', 'NO,YES_SIGNATURE');  return;}
                            scanner.sendResults();
                            break;

                        case 'scan_heuristic':
                            scanner.sendResults();
                            break;

                        // Send results
                        case 'send_results':
                            scanner.end();
                            opt.button.data('status', null);
                            location.href=location.origin+location.pathname+"?tab=malware_scanner";
                            break;

                        default:

                            break;
                    }
                }, 300);
            },

            // ACTIONS
           clearTable: function(){
                console.log('CLEAR_TABLE');
                this.data('status', 'clear_table');
                scanner.ajax({action : 'scanner__clear_table'});
            },

            count: function(opt){
                console.log('COUNT_FILES');
                this.data('status', 'count_files')
                    .data('callback', scanner.count_callback);
                scanner.ajax({
                    action : 'spbc_scanner_count_files',
                });
            },
            count_callback: function(result, data, opt){
                console.log('FILES COUNTED');
                this.data('total_scanned', this.data('total_scanned') + +result.total)
                    .data('scan_precent', +result.total / 98);
                scanner.control(result);
            },

            scanModified_sign: function(status, opt){
                console.log('SCAN MODIFIED FILES');
                this.data('status', 'scan_signatures')
                    .data('precent_completed', 0)
                    .data('callback', scanner.scanModified_callback)
                    .data('timeout',  60000);
                data = {
                    action : 'spbc_scanner_scan_signatures',
                    offset : 0,
                    amount : 50,
                    status : status
                };
                scanner.ajax(data);
            },
            scanModified_heur: function(status, opt){
                console.log('SCAN MODIFIED FILES');
                this.data('status', 'scan_heuristic')
                    .data('precent_completed', 0)
                    .data('callback', scanner.scanModified_callback)
                    .data('timeout',  60000);
                data = {
                    action : 'spbc_scanner_scan_heuristic',
                    offset : 0,
                    amount : 5,
                    status : status
                };
                scanner.ajax(data);
            },
            scanModified_callback: function(result, data, opt){
                console.log('MODIFIED FILES SCANNING');
                if(result.processed >= data.amount){
                    data.offset += result.processed;
                    scanner.ajax(data);
                    return;
                }
                console.log('MODIFIED FILES END');
                opt.progressbar.progressbar('option', 'value', 100);
                opt.progressbar_text.text(spbcScaner['progressbar_'+opt.status] + ' - 100%');
                scanner.control();
            },

            sendResults: function(){
                console.log('SEND RESULTS');
                this.data('status', 'send_results')
                    .data('callback', scanner.sendResults_callback);
                var data = {
                    action: 'spbc_scanner_send_results',
                    total_scanned: this.data('total_scanned'),
                };
                if( +spbcScaner.check_links )
                    data.total_links = this.data('total_links');
                scanner.ajax(data);
            },
            sendResults_callback: function(result, data, opt){
                console.log('RESULTS_SENT');
                if( +spbcScaner.check_links ){
                    opt.button.parent().next().html(spbcScaner.last_scan_was_just_now_links.printf(data.total_scanned, data.total_links));
                }else{
                    opt.button.parent().next().html(spbcScaner.last_scan_was_just_now.printf(data.total_scanned));
                }

                // jQuery('#spbc_scanner_status_icon').attr('src', spbcSettings.img_path + '/yes.png');
                scanner.control();
            },
            get_next_status: function( state ){

                state = states[ states.indexOf( state ) + 1 ];

                if( typeof settings[state]  !== 'undefined' && settings[state] === 0 ){
                    state = this.get_next_status( state );
                }

                return state;
            },
        };

        // Initialing
        if( typeof param === 'object' ){
            var settings = $.extend({

                status: null,
                paused_status: null,
                paused: false,

                total_links: 0,
                total_scanned: 0,

                button: null,
                spinner: null,

                progress_overall: null,
                progressbar: null,
                progressbar_text: null,

                callback: null,
                timeout: 60000,
            }, param);
            return methods.init.apply(this, [settings]);

        // Methods call. Passing current settings to each function as the last element.
        }else if(typeof methods[param]==='function'){
            var args = Array.prototype.slice.call(arguments, 1);
            if(param !== 'data')
                args.push(this.data());
            return methods[param].apply(this, args);

        // Error
        }else{
            $.error( 'Method "' +  param + '" is unset for jQuery.spbcScannerPlugin' );
        }
    };

})(jQuery);