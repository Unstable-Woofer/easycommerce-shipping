var array_conditions;
var array_shipping_classes;
var array_current_methods;
var plugin_id;
var last_row_id=0;

function create_rate() {
    var rate = {
        min: '',
        max: '',
        shipping_fee: '',
    };

    return rate;
}

function create_row_rate_input(rate) {
    if (rate == null || rate == undefined)
        rate = create_rate();

    var html = '<tr>\
        <td>\
            <input class="easycommerce-shipping-rate-checkbox" type="checkbox" />\
        </td>\
        <td>\
            <select name="rate[' + last_row_id + '][condition]">' + generate_html_conditions(rate.condition) + '</select>\
        </td>\
        <td>\
            <input type="text" name="rate[' + last_row_id + '][min]" value="' + rate.min + '" />\
        </td>\
        <td>\
            <input type="text" name="rate[' + last_row_id + '][max]"  value="' + rate.max + '" />\
        </td>\
        <td>\
            <select name="rate[' + last_row_id + '][shipping_class]">' + generate_html_shipping_classes(rate.shipping_class) + '</select>\
        </td>\
        <td>\
            <input type="text" name="rate[' + last_row_id + '][shipping_fee]"  value="' + rate.shipping_fee + '" />\
        </td>';

    jQuery('#easycommerce-shipping-rates').append(html);

    last_row_id++;
}

function generate_html_conditions(value) {
    var html="";

    for (var key in array_conditions) {
        if (key == value)
            html+=`<option value="${key}" selected="selected">${array_conditions[key]}</option>`;
        else
            html+=`<option value="${key}">${array_conditions[key]}</option>`;
    }

    return html;
}

function generate_html_shipping_classes(value) {
    var html='<option value="" selected="selected"></option>';

    for (var key in array_shipping_classes) {
        var shipping_class = array_shipping_classes[key];

        if (shipping_class['term_id'] == value)
            html+=`<option value="${shipping_class['term_id']}" selected="selected">${shipping_class['name']}</option>`;
        else
            html+=`<option value="${shipping_class['term_id']}">${shipping_class['name']}</option>`;
    }

    return html;
}

jQuery(document).ready(function() {
    var params = new URLSearchParams(window.location.search);
    var action = params.has('action') ? params.get('action') : false;

    if (action == 'edit') {
        for (var rate_id in array_current_methods['method_table_rates']) {
            create_row_rate_input(array_current_methods['method_table_rates'][rate_id]);
        }
    }

    if (jQuery('#easycommerce-shipping-methods-table-actions').length == 1) {
        jQuery('#easycommerce-shipping-methods-table-actions .add').attr('disabled', false);
        jQuery('#easycommerce-shipping-methods-table-actions .add').on('click', function() {
            window.location.href=jQuery('#easycommerce-shipping-methods-table-actions .add').data('url');

            return false;
        });

        jQuery('#easycommerce-shipping-methods-table-actions .delete').on('click', function() {
            var url = jQuery('#easycommerce-shipping-methods-table-actions .delete').data('url');
            var first = true;

            jQuery('#easycommerce-shipping-methods-table input[type="checkbox"]').each(function() {
                if (jQuery(this).is(':checked')) {
                    first ? (url += '=') : (url += ',');
                    first=false;

                    url += jQuery(this).val();
                }
            });

            window.location.href=url;

            return false;
        });

        jQuery('#easycommerce-shipping-methods-table input[type="checkbox"]').on('click', function() {
            jQuery('#easycommerce-shipping-methods-table-actions .delete').attr('disabled', !jQuery('#easycommerce-shipping-methods-table input[type="checkbox"]').is(':checked'))
        }); 
    }

    if (jQuery('#easycommerce-shipping-table-rate-buttons').length == 1) {
        var actionButtons = jQuery('#easycommerce-shipping-table-rate-buttons').find('button:disabled');
        actionButtons.prop('disabled', false);

        jQuery('#easycommerce-shipping-table-rate-buttons .add').on('click', function() {
            create_row_rate_input(null);

            return false;
        });

        jQuery('#easycommerce-shipping-table-rate-buttons .delete').on('click', function() {
            var ratesToDelete = jQuery(this).closest('table').find('.easycommerce-shipping-rate-checkbox:checked');

            jQuery.each(ratesToDelete, function() {
                jQuery(this).closest('tr').remove();
            });

            return false;
        });

        create_row_rate_input(null);
    }
});