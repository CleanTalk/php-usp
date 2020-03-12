// Printf for JS
String.prototype.printf = function(){
    var formatted = this;
    for( var arg in arguments ) {
        var before_formatted = formatted.substring(0, formatted.indexOf("%s", 0));
        var after_formatted  = formatted.substring(formatted.indexOf("%s", 0)+2, formatted.length);
        formatted = before_formatted + arguments[arg] + after_formatted;
    }
    return formatted;
};

function spbc_scanner_button_file_view_event(obj){
    var self = jQuery(obj);
    var data = {
        action: 'spbc_scanner_file_view',
        file_id: self.parent().attr('uid'),
    };
    var params = {
        spinner: self.parent().siblings('.tbl-preloader--tiny'),
        callback: spbc_scannerButtonFileView_callback,
    };
    usp_AJAX(data, params);
}

function spbc_scannerButtonFileView_callback(result, data, params){
    console.log('FILE_VIEWED');
    var row_template = '<div class="spbc_view_file_row_wrapper"><span class="spbc_view_file_row_num">%s</span><p class="spbc_view_file_row">%s</p><br /></div>';
    jQuery('#spbc_dialog').empty();
    for(row in result.file){
        jQuery('#spbc_dialog').append(row_template.printf(row, result.file[row]));
    }

    var content_height = Object.keys(result.file).length * 19 + 19,
        visible_height = (document.documentElement.clientHeight) / 100 * 75;
    var overflow = content_height < visible_height ? 'no_scroll' : 'scroll';

    jQuery('#spbc_dialog').data('overflow', overflow);
    jQuery('#spbc_dialog').dialog({
        modal:true,
        title: result.file_path,
        position: { my: "center top", at: "top+7%" , of: window },
        width: +(jQuery('body').width() / 100 * 70),
        height: overflow == 'scroll' ? visible_height : content_height,
        minHeight: 300,
        show: { effect: "blind", duration: 500 },
        draggable: false,
        resizable: false,
        closeText: "X",
        open: function(event, ui) {
            console.log(jQuery(event.target).data('overflow'));
            document.body.style.overflow = 'hidden';
            if(jQuery(event.target).data('overflow') == 'scroll') event.target.style.overflow = 'scroll';
        },
        beforeClose: function(event, ui) { document.body.style.overflow = 'auto'; },
    });
}

jQuery(document).ready(function(){

    // Preparing progressbar
    jQuery('#spbc_scaner_progress_bar').progressbar({
        value: 0,
        create: function( event, ui ) {
            event.target.style.position = 'relative';
            event.target.style.marginBottom = '12px';
        },
        change: function(event, ui){
            jQuery('.spbc_progressbar_counter span').text(jQuery(event.target).progressbar('option', 'value') + ' %');
        },
    });

    // Preparing accordion
    jQuery('#spbc_scan_accordion').accordion({
        header: "h3",
        heightStyle: 'content',
        collapsible: true,
        active: false,
        beforeActivate: function(event, ui){
            // jQuery( "#spbc_scan_accordion" ).accordion( "option", "active", 2 );
        },
    });

    // Init scanner plugin
    jQuery('#spbc_perform_scan').spbcScannerPlugin({
        status: null,
        paused: false,
        button: jQuery('#spbc_perform_scan'),
        spinner: jQuery('#spbc_perform_scan').next(),
        callback: null,
        progress_overall: jQuery('#spbc_scaner_progress_overall'),
        progressbar: jQuery('#spbc_scaner_progress_bar'),
        progressbar_text: jQuery('.spbc_progressbar_counter span'),
        wrapper: document.getElementsByClassName('spbc_unchecked_file_list'),
        warnings: {
            long_scan: jQuery('.spbc_hint_warning__long_scan'),
            outdated:  jQuery('.spbc_hint_warning__outdated')
        }
    });

    jQuery('#spbc_perform_scan').on('click', function(){
        jQuery.spbc.scanner.control(null, null, true);
    });

    //DEBUG
    // Clear table
    jQuery('#spbc_scanner_clear').on('click', function(){
        jQuery.spbc.scanner.clear();
    });

});