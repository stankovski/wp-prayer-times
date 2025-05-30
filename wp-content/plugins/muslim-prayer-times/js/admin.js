jQuery(document).ready(function($) {
    // Accordion functionality
    $('.muslprti-accordion-header').on('click', function() {
        // Toggle active class on header
        $(this).toggleClass('active');
        
        // Toggle content section
        $(this).next('.muslprti-accordion-content').toggleClass('active');
    });
    
    // Radio button handling for Iqama rules
    $('.rule-radio').on('change', function() {
        var name = $(this).attr('name');
        var value = $(this).val();
        var prayer = name.replace('muslprti_', '').replace('_rule', '');
        
        // Disable all inputs for this prayer
        $('.' + prayer + '-input').prop('disabled', true);
        
        // Enable inputs based on selected rule
        if (value === 'after_athan') {
            $('input[name="muslprti_' + prayer + '_minutes_after"]').prop('disabled', false);
        } else if (value === 'before_shuruq') {
            $('input[name="muslprti_' + prayer + '_minutes_before_shuruq"]').prop('disabled', false);
        } else if (value === 'fixed_time') {
            $('input[name="muslprti_' + prayer + '_fixed_standard"]').prop('disabled', false);
            $('input[name="muslprti_' + prayer + '_fixed_dst"]').prop('disabled', false);
        }
    });
    
    // Initialize radio button states on page load
    $('.rule-radio:checked').each(function() {
        $(this).trigger('change');
    });
    
    // Geocoding functionality (if it exists in original code)
    if ($('#muslprti_geocode_btn').length > 0) {
        $('#muslprti_geocode_btn').on('click', function() {
            var address = $('#muslprti_address').val();
            if (!address) {
                $('#muslprti_geocode_results').html('<div class="notice notice-error inline"><p>Please enter an address to search.</p></div>');
                return;
            }
            
            $(this).text('Searching...').prop('disabled', true);
            
            $.ajax({
                url: muslprtiAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'muslprti_geocode',
                    nonce: muslprtiAdmin.geocode_nonce,
                    address: address
                },
                success: function(response) {
                    $('#muslprti_geocode_btn').text('Find Coordinates').prop('disabled', false);
                    
                    if (response.success) {
                        var result = response.data;
                        $('#muslprti_lat').val(result.lat);
                        $('#muslprti_lng').val(result.lon);
                        $('#muslprti_geocode_results').html('<div class="notice notice-success inline"><p>Found: ' + result.display_name + '</p></div>');
                    } else {
                        $('#muslprti_geocode_results').html('<div class="notice notice-error inline"><p>Error: ' + response.data + '</p></div>');
                    }
                },
                error: function() {
                    $('#muslprti_geocode_btn').text('Find Coordinates').prop('disabled', false);
                    $('#muslprti_geocode_results').html('<div class="notice notice-error inline"><p>Error connecting to server.</p></div>');
                }
            });
        });
    }
    
    // Show/hide custom date range fields based on period selection
    $('#muslprti_period').on('change', function() {
        if ($(this).val() === 'custom') {
            $('#custom_date_range').show();
        } else {
            $('#custom_date_range').hide();
        }
    });
    
    // Generate prayer times functionality for generating calculated times
    $('#muslprti_generate_btn').on('click', function() {
        $(this).text('Generating...').prop('disabled', true);
        $('#muslprti_export_result').html('<p>Generating prayer times, please wait...</p>');
        
        // Get the selected period value - fix the ID to match your HTML
        var selectedPeriod = $('#muslprti_period').val();
        console.log("Selected period:", selectedPeriod); // Debug info
        
        var data = {
            action: 'muslprti_generate_times',
            nonce: muslprtiAdmin.export_nonce,
            period: selectedPeriod
        };
        
        // Add custom date range if selected
        if (selectedPeriod === 'custom') {
            data.start_date = $('#muslprti_start_date').val();
            data.end_date = $('#muslprti_end_date').val();
            
            if (!data.start_date || !data.end_date) {
                $('#muslprti_generate_btn').text('Generate Prayer Times').prop('disabled', false);
                $('#muslprti_export_result').html('<div class="notice notice-error inline"><p>Please specify both start and end dates for custom date range.</p></div>');
                return;
            }
            
            // Validate date range
            var startDate = new Date(data.start_date);
            var endDate = new Date(data.end_date);
            
            if (startDate > endDate) {
                $('#muslprti_generate_btn').text('Generate Prayer Times').prop('disabled', false);
                $('#muslprti_export_result').html('<div class="notice notice-error inline"><p>Start date cannot be after end date.</p></div>');
                return;
            }
            
            // Set a max range of 730 days (2 years) to prevent server overload
            var daysDiff = Math.floor((endDate - startDate) / (1000 * 60 * 60 * 24));
            if (daysDiff > 730) {
                $('#muslprti_generate_btn').text('Generate Prayer Times').prop('disabled', false);
                $('#muslprti_export_result').html('<div class="notice notice-error inline"><p>Custom date range cannot exceed 2 years (730 days).</p></div>');
                return;
            }
            
            console.log("Custom date range:", data.start_date, "to", data.end_date);
        }
        
        $.ajax({
            url: muslprtiAdmin.ajaxurl,
            type: 'POST',
            data: data,
            success: function(response) {
                $('#muslprti_generate_btn').text('Generate Prayer Times').prop('disabled', false);
                
                console.log("AJAX response:", response); // Debug info
                
                if (response.success) {
                    // Create a download link for the CSV file
                    var csvContent = response.data.content;
                    var filename = response.data.filename;
                    
                    // Debug info
                    console.log("CSV filename:", filename);
                    console.log("CSV content length:", csvContent.length);
                    
                    var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                    var link = document.createElement('a');
                    var url = URL.createObjectURL(blob);
                    
                    link.setAttribute('href', url);
                    link.setAttribute('download', filename);
                    link.style.visibility = 'hidden';
                    
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    $('#muslprti_export_result').html('<div class="notice notice-success inline"><p>Prayer times generated successfully. Download should begin automatically.</p></div>');
                } else {
                    $('#muslprti_export_result').html('<div class="notice notice-error inline"><p>Error: ' + response.data + '</p></div>');
                    console.error("Error:", response.data);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $('#muslprti_generate_btn').text('Generate Prayer Times').prop('disabled', false);
                $('#muslprti_export_result').html('<div class="notice notice-error inline"><p>Error connecting to server.</p></div>');
                console.error("AJAX error:", textStatus, errorThrown);
            }
        });
    });
    
    // Export prayer times from database functionality
    $('#muslprti_export_db_btn').on('click', function() {
        $(this).text('Exporting...').prop('disabled', true);
        $('#muslprti_export_result').html('<p>Exporting prayer times from database, please wait...</p>');
        
        $.ajax({
            url: muslprtiAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'muslprti_export_db',
                nonce: muslprtiAdmin.export_db_nonce
            },
            success: function(response) {
                $('#muslprti_export_db_btn').text('Export Existing Prayer Times').prop('disabled', false);
                
                if (response.success) {
                    // Create a download link for the CSV file
                    var csvContent = response.data.content;
                    var filename = response.data.filename;
                    
                    var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                    var link = document.createElement('a');
                    var url = URL.createObjectURL(blob);
                    
                    link.setAttribute('href', url);
                    link.setAttribute('download', filename);
                    link.style.visibility = 'hidden';
                    
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    $('#muslprti_export_result').html('<div class="notice notice-success inline"><p>Prayer times exported successfully. Download should begin automatically.</p></div>');
                } else {
                    $('#muslprti_export_result').html('<div class="notice notice-error inline"><p>Error: ' + response.data + '</p></div>');
                }
            },
            error: function() {
                $('#muslprti_export_db_btn').text('Export Existing Prayer Times').prop('disabled', false);
                $('#muslprti_export_result').html('<div class="notice notice-error inline"><p>Error connecting to server.</p></div>');
            }
        });
    });
    
    // Preview import functionality
    $('#muslprti_preview_btn').on('click', function() {
        var fileInput = $('#muslprti_import_file')[0];
        
        if (!fileInput.files || !fileInput.files[0]) {
            $('#muslprti_import_preview').html('<div class="notice notice-error inline"><p>Please select a CSV file to import.</p></div>');
            return;
        }
        
        var file = fileInput.files[0];
        
        // Check if file is a CSV
        if (file.type !== 'text/csv' && !file.name.endsWith('.csv')) {
            $('#muslprti_import_preview').html('<div class="notice notice-error inline"><p>Please select a valid CSV file.</p></div>');
            return;
        }
        
        // Create FormData object
        var formData = new FormData();
        formData.append('action', 'muslprti_import_preview');
        formData.append('nonce', muslprtiAdmin.import_preview_nonce);
        formData.append('import_file', file);
        
        $(this).text('Previewing...').prop('disabled', true);
        
        // Send AJAX request
        $.ajax({
            url: muslprtiAdmin.ajaxurl,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(response) {
                $('#muslprti_preview_btn').text('Preview Import').prop('disabled', false);
                
                if (response.success) {
                    var data = response.data;
                    var preview = data.preview;
                    
                    // Enable import button
                    $('#muslprti_import_btn').prop('disabled', false);
                    
                    // Generate preview table
                    var html = '<h4>Preview (' + preview.length + ' rows of ' + data.total_rows + ' total)</h4>';
                    html += '<div style="max-height: 300px; overflow-y: auto;">';
                    html += '<table class="widefat striped" style="min-width: 100%;">';
                    
                    // Table header
                    html += '<thead><tr>';
                    html += '<th>Date</th>';
                    html += '<th>Fajr Athan</th><th>Fajr Iqama</th>';
                    html += '<th>Sunrise</th>'; 
                    html += '<th>Dhuhr Athan</th><th>Dhuhr Iqama</th>';
                    html += '<th>Asr Athan</th><th>Asr Iqama</th>';
                    html += '<th>Maghrib Athan</th><th>Maghrib Iqama</th>';
                    html += '<th>Isha Athan</th><th>Isha Iqama</th>';
                    html += '</tr></thead>';
                    
                    // Table body
                    html += '<tbody>';
                    var hasErrors = false;
                    
                    preview.forEach(function(row) {
                        var rowClass = '';
                        
                        // Check for date errors
                        if (row.day_error) {
                            rowClass = 'class="error"';
                            hasErrors = true;
                        }
                        
                        html += '<tr ' + rowClass + '>';
                        html += '<td>' + (row.day_error ? '<span style="color:red;">' + row.day + ' (Invalid format)</span>' : row.day) + '</td>';
                        html += '<td>' + (row.fajr_athan || '') + '</td>';
                        html += '<td>' + (row.fajr_iqama || '') + '</td>';
                        html += '<td>' + (row.sunrise || '') + '</td>';
                        html += '<td>' + (row.dhuhr_athan || '') + '</td>';
                        html += '<td>' + (row.dhuhr_iqama || '') + '</td>';
                        html += '<td>' + (row.asr_athan || '') + '</td>';
                        html += '<td>' + (row.asr_iqama || '') + '</td>';
                        html += '<td>' + (row.maghrib_athan || '') + '</td>';
                        html += '<td>' + (row.maghrib_iqama || '') + '</td>';
                        html += '<td>' + (row.isha_athan || '') + '</td>';
                        html += '<td>' + (row.isha_iqama || '') + '</td>';
                        html += '</tr>';
                    });
                    
                    html += '</tbody></table></div>';
                    
                    if (hasErrors) {
                        html += '<p class="notice notice-warning" style="margin-top: 10px; padding: 10px;">Warning: Some rows contain invalid date formats. These rows will be skipped during import.</p>';
                    }
                    
                    $('#muslprti_import_preview').html(html);
                } else {
                    $('#muslprti_import_preview').html('<div class="notice notice-error inline"><p>Error: ' + response.data + '</p></div>');
                }
            },
            error: function() {
                $('#muslprti_preview_btn').text('Preview Import').prop('disabled', false);
                $('#muslprti_import_preview').html('<div class="notice notice-error inline"><p>Error connecting to server.</p></div>');
            }
        });
    });
    
    // Import functionality
    $('#muslprti_import_btn').on('click', function() {
        var fileInput = $('#muslprti_import_file')[0];
        
        if (!fileInput.files || !fileInput.files[0]) {
            $('#muslprti_import_result').html('<div class="notice notice-error inline"><p>Please select a CSV file to import.</p></div>');
            return;
        }
        
        if (!confirm('Are you sure you want to import these prayer times? This will overwrite any existing times for the same dates.')) {
            return;
        }
        
        var file = fileInput.files[0];
        var formData = new FormData();
        formData.append('action', 'muslprti_import');
        formData.append('nonce', muslprtiAdmin.import_nonce);
        formData.append('import_file', file);
        
        $(this).text('Importing...').prop('disabled', true);
        
        // Send AJAX request
        $.ajax({
            url: muslprtiAdmin.ajaxurl,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(response) {
                $('#muslprti_import_btn').text('Import Prayer Times').prop('disabled', false);
                
                if (response.success) {
                    var data = response.data;
                    
                    var html = '<div class="notice notice-success inline"><p>Successfully imported ' + data.success_count + ' prayer time records.</p>';
                    
                    if (data.error_count > 0) {
                        html += '<p>Encountered ' + data.error_count + ' errors during import.</p>';
                        html += '<div style="max-height:150px;overflow-y:auto;margin-top:10px;padding:10px;background:#f8f8f8;border:1px solid #ddd;">';
                        html += '<ul style="margin:0;padding-left:20px;">';
                        
                        data.errors.forEach(function(error) {
                            html += '<li>' + error + '</li>';
                        });
                        
                        html += '</ul></div>';
                    }
                    
                    html += '</div>';
                    
                    $('#muslprti_import_result').html(html);
                    
                    // Clear the preview
                    $('#muslprti_import_preview').empty();
                    
                    // Disable import button until next preview
                    $('#muslprti_import_btn').prop('disabled', true);
                } else {
                    $('#muslprti_import_result').html('<div class="notice notice-error inline"><p>Error: ' + response.data + '</p></div>');
                }
            },
            error: function() {
                $('#muslprti_import_btn').text('Import Prayer Times').prop('disabled', false);
                $('#muslprti_import_result').html('<div class="notice notice-error inline"><p>Error connecting to server.</p></div>');
            }
        });
    });
});
