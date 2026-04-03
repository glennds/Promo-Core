/**
 * Network Order Management - Network Admin JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        NOM_Admin.init();
    });
    
    var NOM_Admin = {
        
        init: function() {
            this.initEnhancedSelects();
            this.bindEvents();
        },

        initEnhancedSelects: function() {
            $('.nom-enhanced-multiselect').each(function() {
                var $select = $(this);

                if ($select.hasClass('select2-hidden-accessible')) {
                    return;
                }

                var config = {
                    width: '100%',
                    closeOnSelect: false,
                    allowClear: true,
                    placeholder: $select.data('placeholder') || '',
                    dropdownCssClass: 'nom-select2-dropdown'
                };

                if ($.fn.selectWoo) {
                    $select.selectWoo(config);
                } else if ($.fn.select2) {
                    $select.select2(config);
                }
            });
        },
        
        bindEvents: function() {
            $('#nom-export-csv').on('click', this.handleExport);
            $('#nom-email-warehouses').on('click', this.handleEmailWarehouses);
            $(document).on('click', '.nom-send-individual-email', this.handleIndividualWarehouseEmail);
            $(document).on('click', '.nom-send-test-email', this.handleTestEmail);
        },

        getFilterValues: function(selector) {
            var value = $(selector).val();

            if (!value) {
                return [];
            }

            return $.isArray(value) ? value : [value];
        },

        appendHiddenInputs: function(form, key, value) {
            if ($.isArray(value)) {
                $.each(value, function(_, item) {
                    form.append($('<input>', {
                        type: 'hidden',
                        name: key + '[]',
                        value: item
                    }));
                });

                return;
            }

            form.append($('<input>', {
                type: 'hidden',
                name: key,
                value: value
            }));
        },
        
        handleExport: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            
            var data = {
                action: 'nom_export_orders',
                nonce: nomAdmin.nonce,
                warehouse: NOM_Admin.getFilterValues('#warehouse'),
                site: NOM_Admin.getFilterValues('#site'),
                status: NOM_Admin.getFilterValues('#status'),
                date_from: $('#date_from').val() || '',
                date_to: $('#date_to').val() || ''
            };
            
            // Show loading state
            $button.prop('disabled', true).text('Exporting...');
            
            // Create a form and submit it to trigger download
            var form = $('<form>', {
                'method': 'POST',
                'action': nomAdmin.ajax_url
            });
            
            $.each(data, function(key, value) {
                NOM_Admin.appendHiddenInputs(form, key, value);
            });
            
            $('body').append(form);
            form.submit();
            form.remove();
            
            // Reset button after delay
            setTimeout(function() {
                $button.prop('disabled', false).text('Export to CSV');
            }, 2000);
        },
        
        handleEmailWarehouses: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $result = $('#nom-email-result');
            var originalHtml = $button.html();
            
            if (!confirm('This will export orders for all warehouses and email them. Continue?')) {
                return;
            }
            
            // Show loading state
            $button.prop('disabled', true).html('<span class="dashicons dashicons-update dashicons-spin"></span> Processing...');
            $result.hide();
            
            $.ajax({
                url: nomAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'nom_manual_export_email',
                    nonce: nomAdmin.nonce,
                    date_from: $('#date_from').val() || '',
                    date_to: $('#date_to').val() || ''
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        var message = '<div class="notice notice-success" style="margin: 0; padding: 10px;">';
                        message += '<p><strong>✓ Export and Email Completed!</strong></p>';
                        message += '<ul>';
                        message += '<li>Warehouses processed: <strong>' + data.warehouses_processed + '</strong></li>';
                        message += '<li>Emails sent: <strong>' + data.emails_sent + '</strong></li>';
                        if (data.range_description) {
                            message += '<li>Reporting window: <strong>' + data.range_description + '</strong></li>';
                        }
                        if (data.message) {
                            message += '<li>' + data.message + '</li>';
                        }
                        
                        // Show successful email details
                        if (data.emails_success && data.emails_success.length > 0) {
                            message += '<li><details open><summary style="cursor: pointer; color: #2271b1;"><strong>✉️ Emails sent to:</strong></summary>';
                            message += '<ul style="margin-top: 8px;">';
                            data.emails_success.forEach(function(email) {
                                message += '<li style="margin: 5px 0; padding: 8px; background: #f0f6fc; border-left: 3px solid #2271b1;">';
                                message += '<strong>' + email.warehouse_name + '</strong><br>';
                                message += '📧 <code>' + email.warehouse_email + '</code><br>';
                                message += '📦 Orders: <strong>' + email.order_count + '</strong><br>';
                                message += '📄 File: <code>' + email.file_name + '</code>';
                                message += '</li>';
                            });
                            message += '</ul></details></li>';
                        }
                        
                        if (data.errors && data.errors.length > 0) {
                            message += '<li style="color: #d63638;">Errors: <strong>' + data.errors.length + '</strong></li>';
                            message += '<li><details><summary style="cursor: pointer;">Show errors</summary><ul>';
                            data.errors.forEach(function(error) {
                                message += '<li style="color: #d63638;">' + error + '</li>';
                            });
                            message += '</ul></details></li>';
                        }
                        
                        message += '</ul></div>';
                        $result.html(message).show();
                    } else {
                        var errorMsg = '<div class="notice notice-error" style="margin: 0; padding: 10px;">';
                        errorMsg += '<p><strong>✗ Error:</strong> ' + (response.data.message || 'Unknown error') + '</p>';
                        if (response.data.errors) {
                            errorMsg += '<ul>';
                            response.data.errors.forEach(function(error) {
                                errorMsg += '<li>' + error + '</li>';
                            });
                            errorMsg += '</ul>';
                        }
                        errorMsg += '</div>';
                        $result.html(errorMsg).show();
                    }
                },
                error: function(xhr, status, error) {
                    var errorMsg = '<div class="notice notice-error" style="margin: 0; padding: 10px;">';
                    errorMsg += '<p><strong>✗ AJAX Error:</strong> ' + error + '</p>';
                    errorMsg += '</div>';
                    $result.html(errorMsg).show();
                },
                complete: function() {
                    // Reset button
                    $button.prop('disabled', false).html(originalHtml);
                }
            });
        },
        
        handleIndividualWarehouseEmail: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $row = $button.closest("tr");
            var warehouseSlug = $button.data("warehouse-slug");
            var warehouseName = $button.data("warehouse-name");
            var $status = $row.find('.nom-email-status[data-warehouse="' + warehouseSlug + '"]');
            var dateFrom = $row.find('.nom-manual-range-from').val();
            var dateTo = $row.find('.nom-manual-range-to').val();
            var dateFromTime = $row.find('.nom-manual-range-from-time').val();
            var dateToTime = $row.find('.nom-manual-range-to-time').val();
            var originalHtml = $button.html();
            
            var confirmMessage = dateFrom || dateTo
                ? 'Send the selected date range to ' + warehouseName + '?'
                : 'Send the scheduled orders to ' + warehouseName + '?';

            if (!confirm(confirmMessage)) {
                return;
            }
            
            // Show loading state
            $button.prop('disabled', true).html('<span class="dashicons dashicons-update dashicons-spin"></span> Sending...');
            $status.hide();
            
            $.ajax({
                url: nomAdmin.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'nom_send_individual_warehouse_email',
                    nonce: nomAdmin.nonce,
                    warehouse_slug: warehouseSlug,
                    date_from: dateFrom,
                    date_to: dateTo,
                    date_from_time: dateFromTime,
                    date_to_time: dateToTime
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        var message = '<span style="color: #00a32a;">?o" ' + data.message + '</span>';
                        $status.html(message).show();
                        
                        // Hide success message after 5 seconds
                        setTimeout(function() {
                            $status.fadeOut();
                        }, 5000);
                    } else {
                        var errorMsg = '<span style="color: #d63638;">?o- ' + (response.data.message || 'Failed to send email') + '</span>';
                        $status.html(errorMsg).show();
                        
                        // Hide error message after 7 seconds
                        setTimeout(function() {
                            $status.fadeOut();
                        }, 7000);
                    }
                },
                error: function(xhr, status, error) {
                    var errorText = error || status || 'Unknown';
                    var serverText = xhr && xhr.responseText ? ' (' + xhr.responseText.substring(0, 200) + ')' : '';
                    var errorMsg = '<span style="color: #d63638;">?o- AJAX Error: ' + errorText + serverText + '</span>';
                    $status.html(errorMsg).show();
                    
                    setTimeout(function() {
                        $status.fadeOut();
                    }, 7000);
                },
                complete: function() {
                    // Reset button
                    $button.prop('disabled', false).html(originalHtml);
                }
            });
        },

        handleTestEmail: function(e) {
            e.preventDefault();

            var $button = $(this);
            var $row = $button.closest("tr");
            var warehouseSlug = $button.data("warehouse-slug");
            var warehouseName = $button.data("warehouse-name");
            var $status = $row.find('.nom-email-status[data-warehouse="' + warehouseSlug + '"]');
            var dateFrom = $row.find('.nom-manual-range-from').val();
            var dateTo = $row.find('.nom-manual-range-to').val();
            var dateFromTime = $row.find('.nom-manual-range-from-time').val();
            var dateToTime = $row.find('.nom-manual-range-to-time').val();
            var testEmail = ($row.find('.nom-test-email').val() || '').trim();
            var originalHtml = $button.html();

            if (!testEmail) {
                alert('Please enter a test email address.');
                return;
            }

            var confirmMessage = dateFrom || dateTo
                ? 'Send the selected date range to ' + testEmail + ' (warehouse ' + warehouseName + ')?'
                : 'Send the scheduled orders to ' + testEmail + ' (warehouse ' + warehouseName + ')?';

            if (!confirm(confirmMessage)) {
                return;
            }

            // Show loading state
            $button.prop('disabled', true).html('<span class="dashicons dashicons-update dashicons-spin"></span> Sending...');
            $status.hide();

            $.ajax({
                url: nomAdmin.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'nom_send_individual_warehouse_email',
                    nonce: nomAdmin.nonce,
                    warehouse_slug: warehouseSlug,
                    date_from: dateFrom,
                    date_to: dateTo,
                    date_from_time: dateFromTime,
                    date_to_time: dateToTime,
                    test_email: testEmail
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        var message = '<span style="color: #00a32a;">✔ ' + data.message + '</span>';
                        $status.html(message).show();

                        // Hide success message after 5 seconds
                        setTimeout(function() {
                            $status.fadeOut();
                        }, 5000);
                    } else {
                        var errorMsg = '<span style="color: #d63638;">✖ ' + (response.data.message || 'Failed to send test email') + '</span>';
                        $status.html(errorMsg).show();

                        // Hide error message after 7 seconds
                        setTimeout(function() {
                            $status.fadeOut();
                        }, 7000);
                    }
                },
                error: function(xhr, status, error) {
                    var errorText = error || status || 'Unknown';
                    var serverText = xhr && xhr.responseText ? ' (' + xhr.responseText.substring(0, 200) + ')' : '';
                    var errorMsg = '<span style="color: #d63638;">✖ AJAX Error: ' + errorText + serverText + '</span>';
                    $status.html(errorMsg).show();

                    setTimeout(function() {
                        $status.fadeOut();
                    }, 7000);
                },
                complete: function() {
                    // Reset button
                    $button.prop('disabled', false).html(originalHtml);
                }
            });
        }
    };

})(jQuery);


