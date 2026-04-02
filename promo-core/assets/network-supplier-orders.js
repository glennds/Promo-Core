/**
 * Network Supplier Orders - Network Admin JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        NSO_Admin.init();
    });
    
    var NSO_Admin = {
        
        init: function() {
            this.bindEvents();
        },
        
        bindEvents: function() {
            $('#nso-export-csv').on('click', this.handleExport);
            $('#nso-email-suppliers').on('click', this.handleEmailSuppliers);
            $(document).on('click', '.nso-send-individual-email', this.handleIndividualSupplierEmail);
            $(document).on('click', '.nso-send-test-email', this.handleTestEmail);
        },
        
        handleExport: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            
            // Get current filter values from URL parameters
            var params = new URLSearchParams(window.location.search);
            
            var data = {
                action: 'nso_export_orders',
                nonce: nsoAdmin.nonce,
                supplier: params.get('supplier') || '',
                site: params.get('site') || '0',
                status: params.get('status') || '',
                date_from: params.get('date_from') || '',
                date_to: params.get('date_to') || ''
            };
            
            // Show loading state
            $button.prop('disabled', true).text('Exporting...');
            
            // Create a form and submit it to trigger download
            var form = $('<form>', {
                'method': 'POST',
                'action': nsoAdmin.ajax_url
            });
            
            $.each(data, function(key, value) {
                form.append($('<input>', {
                    'type': 'hidden',
                    'name': key,
                    'value': value
                }));
            });
            
            $('body').append(form);
            form.submit();
            form.remove();
            
            // Reset button after delay
            setTimeout(function() {
                $button.prop('disabled', false).text('Export to CSV');
            }, 2000);
        },
        
        handleEmailSuppliers: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $result = $('#nso-email-result');
            var originalHtml = $button.html();
            
            if (!confirm('This will export orders for all suppliers and email them. Continue?')) {
                return;
            }
            
            // Show loading state
            $button.prop('disabled', true).html('<span class="dashicons dashicons-update dashicons-spin"></span> Processing...');
            $result.hide();
            
            $.ajax({
                url: nsoAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'nso_manual_export_email',
                    nonce: nsoAdmin.nonce,
                    date_from: $('#date_from').val() || '',
                    date_to: $('#date_to').val() || ''
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        var message = '<div class="notice notice-success" style="margin: 0; padding: 10px;">';
                        message += '<p><strong>✓ Export and Email Completed!</strong></p>';
                        message += '<ul>';
                        message += '<li>Suppliers processed: <strong>' + data.suppliers_processed + '</strong></li>';
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
                                message += '<strong>' + email.supplier_name + '</strong><br>';
                                message += '📧 <code>' + email.supplier_email + '</code><br>';
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
        
        handleIndividualSupplierEmail: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $row = $button.closest("tr");
            var supplierSlug = $button.data("supplier-slug");
            var supplierName = $button.data("supplier-name");
            var $status = $row.find('.nso-email-status[data-supplier="' + supplierSlug + '"]');
            var dateFrom = $row.find('.nso-manual-range-from').val();
            var dateTo = $row.find('.nso-manual-range-to').val();
            var dateFromTime = $row.find('.nso-manual-range-from-time').val();
            var dateToTime = $row.find('.nso-manual-range-to-time').val();
            var originalHtml = $button.html();
            
            var confirmMessage = dateFrom || dateTo
                ? 'Send the selected date range to ' + supplierName + '?'
                : 'Send the scheduled orders to ' + supplierName + '?';

            if (!confirm(confirmMessage)) {
                return;
            }
            
            // Show loading state
            $button.prop('disabled', true).html('<span class="dashicons dashicons-update dashicons-spin"></span> Sending...');
            $status.hide();
            
            $.ajax({
                url: nsoAdmin.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'nso_send_individual_supplier_email',
                    nonce: nsoAdmin.nonce,
                    supplier_slug: supplierSlug,
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
            var supplierSlug = $button.data("supplier-slug");
            var supplierName = $button.data("supplier-name");
            var $status = $row.find('.nso-email-status[data-supplier="' + supplierSlug + '"]');
            var dateFrom = $row.find('.nso-manual-range-from').val();
            var dateTo = $row.find('.nso-manual-range-to').val();
            var dateFromTime = $row.find('.nso-manual-range-from-time').val();
            var dateToTime = $row.find('.nso-manual-range-to-time').val();
            var testEmail = ($row.find('.nso-test-email').val() || '').trim();
            var originalHtml = $button.html();

            if (!testEmail) {
                alert('Please enter a test email address.');
                return;
            }

            var confirmMessage = dateFrom || dateTo
                ? 'Send the selected date range to ' + testEmail + ' (supplier ' + supplierName + ')?'
                : 'Send the scheduled orders to ' + testEmail + ' (supplier ' + supplierName + ')?';

            if (!confirm(confirmMessage)) {
                return;
            }

            // Show loading state
            $button.prop('disabled', true).html('<span class="dashicons dashicons-update dashicons-spin"></span> Sending...');
            $status.hide();

            $.ajax({
                url: nsoAdmin.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'nso_send_individual_supplier_email',
                    nonce: nsoAdmin.nonce,
                    supplier_slug: supplierSlug,
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


