jQuery(document).ready(function(){

    // Table. Row actions handler
    spbc_tbl__bulk_actions__listen();
    spbc_tbl__row_actions__listen();
    spbc_tbl__pagination__listen();
    spbc_tbl__sort__listen();
    usp_showHide__listen();

});

// TABLE BULK ACTIONS
spbc_bulk_action = null;
function spbc_tbl__bulk_actions__listen(){
    jQuery('.tbl-bulk_actions--apply')
        .off('click')
        .on('click', function(){

        if(!spbc_bulk_action && !confirm(spbc_TableData.warning_bulk))
            return;

        var self = spbc_bulk_action || jQuery(this);
        spbc_bulk_action = self;
        var action = self.siblings('select').children()[self.siblings('select').first()[0].selectedIndex].value;
        if(self.parents('.tbl-root').find('.cb-select').is(':checked')){
            if(self.parents('.tbl-root').find('.cb-select:checked').first().parents('tr').find('.tbl-row_action--'+action)[0]){
                self.parents('.tbl-root').find('.cb-select:checked').first().parents('tr').find('.tbl-row_action--'+action).click();
                self.parents('.tbl-root').find('.cb-select:checked').first().prop('checked', false);
            }else{
                self.parents('.tbl-root').find('.cb-select:checked').first().prop('checked', false);
                self.click();
            }
        }else{
            spbc_bulk_action = null;
        }
    });
}

// TABLE ROW ACTIONS
function spbc_tbl__row_actions__listen(){
    jQuery('.tbl-row_action--ajax')
        .off('click')
        .on('click', function(){
        console.log('spbc_tbl__row_actions__listen click');
        var self = jQuery(this);
        var data = {
            action: 'spbc_tbl-action--row',
            add_action: self.attr('row-action'),
            id: self.parent().attr('uid'),
            cols: self.parent().attr('cols_amount'),
            isUFLite: checkUFLiteInstance(),
        };
        var params = {
            callback: spbc_tbl__row_actions__callback,
            spinner: self.parent().siblings('.tbl-preloader--tiny'),
        };
        if(!spbc_bulk_action){
            var confirmation = spbc_TableData['warning_'+self.attr('row-action')] || spbc_TableData.warning_default;
            var checkConfirm = checkUFLiteInstance() ? true : confirm(confirmation);
            if(checkConfirm) {
                ctAJAX({
                    data: data,
                    successCallback: spbc_tbl__row_actions__callback,
                    spinner: self.parent().siblings('.tbl-preloader--tiny'),
                    obj: self.parents('tr'),
                });
            }
        }
        if(spbc_bulk_action){
            ctAJAX({
                data: data,
                successCallback: spbc_tbl__row_actions__callback,
                spinner: self.parent().siblings('.tbl-preloader--tiny'),
                obj: self.parents('tr'),
            });
        }
    });
}

// Callback for TABLE ROW ACTIONS
function spbc_tbl__row_actions__callback( result, data, obj ){
    if(result.color)    {obj.css({background: result.background, color: result.color});}
    if(result.html)     {obj.html(result.html); setTimeout(function(){obj.fadeOut(300);}, 1500);}
    if(result.temp_html){
        var tmp=obj.html();
        obj.html(result.temp_html);
        setTimeout(function(){
            obj.html(tmp).css({background: 'inherit'}).find('.column-primary .row-actions .tbl-row_action--'+data.add_action).remove();
            usp_showHide__listen();
        },5000);
    }
    if(spbc_bulk_action)
        spbc_bulk_action.click();
}

// TABLE PAGINATION ACTIONS
function spbc_tbl__pagination__listen(){

    var spbc_tbl__pagination__listen_handler = function( button, action ){
            var params = {
                data: {
                    action: 'spbc_tbl-pagination',
                    page:   button.parents('.tbl-pagination--wrapper').attr( action + '_page' ),
                    args:   window[ 'args_' + button.parents('.tbl-root').attr('id') ],
                },
                successCallback: spbc_tbl__pagination__callback,
                dataType: 'html',
                type:     'POST',
                obj:      button.parents('.tbl-root'),
                spinner:  button.siblings('.tbl-preloader--small'),
            };

            // Get num for GO action from input
            if( action === 'go')
                params.data.page = button.siblings('.tbl-pagination--curr_page').val()

            ctAJAX( params );
        };
    jQuery('.tbl-pagination--go').on('click', function(){
        spbc_tbl__pagination__listen_handler( jQuery(this), 'go' );
        ctAJAX( params );
    });
    jQuery('.tbl-pagination--prev').on('click', function(){
        spbc_tbl__pagination__listen_handler( jQuery(this), 'prev' );
    });
    jQuery('.tbl-pagination--next').on('click', function(){
        spbc_tbl__pagination__listen_handler( jQuery(this), 'next' );
    });
    jQuery('.tbl-pagination--end').on('click', function(){
        spbc_tbl__pagination__listen_handler( jQuery(this), 'last' );
    });
    jQuery('.tbl-pagination--start').on('click', function(){
        spbc_tbl__pagination__listen_handler( jQuery(this), 'start' );
    });
}

function spbc_scanner__switch_table(obj, table){
    var obj = jQuery(obj);
    console.log(obj.parent().attr('uid'));
    var data = {action: 'spbc_tbl-switch', table: table, test: 'test', domain: obj.parent().attr('uid'),};
    var params = {callback: spbc_tbl__pagination__callback, notJson: true,};
    usp_AJAX(data, params, obj.parents('.tbl-root'));
}

// Callback for TABLE PAGINATION ACTIONS
function spbc_tbl__pagination__callback(result, data, obj){
    jQuery(obj)
        .html(result)
        .find('.tbl-pagination--button').removeAttr('disabled');
    spbc_tbl__bulk_actions__listen();
    spbc_tbl__row_actions__listen();
    spbc_tbl__pagination__listen();
    spbc_tbl__sort__listen();
    spbcStartShowHide();
}

// TABLE SORT ACTIONS
function spbc_tbl__sort__listen(){

    var self = jQuery(this);
    var obj = self.parents('.tbl-root');
    jQuery('.tbl-column-sortable').on('click', function(){
        ctAJAX({
            data: {
                action: 'spbc_tbl-sort',
                order_by: self.attr('id'),
                order: self.attr('sort_direction'),
                args: eval('args_'+obj.attr('id')),
            },
            successCallback: spbc_tbl__sort__callback,
            dataType: 'text'
        });
    });
}

// Shows/hides full text
function usp_showHide__listen(){
    jQuery('.spbcShortText')
        .off('mouseover' )
        .on('mouseover', function(){ jQuery(this).next().show(); });
    jQuery('.spbcFullText')
        .off('mouseout' )
        .on('mouseout',   function(){ jQuery(this).hide();  });
}

// Callback for TABLE SORT ACTIONS
function spbc_tbl__sort__callback(result, data, params, obj){
    jQuery(obj).html(result);
    spbc_tbl__bulk_actions__listen();
    spbc_tbl__row_actions__listen();
    spbc_tbl__pagination__listen();
    spbc_tbl__sort__listen();
}
