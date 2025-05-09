jQuery(document).ready(function($) {
    // Accordion functionality
    $('.prayertimes-accordion-header').on('click', function() {
        // Toggle active class on header
        $(this).toggleClass('active');
        
        // Toggle content section
        $(this).next('.prayertimes-accordion-content').toggleClass('active');
    });
    
    // Geocoding functionality (if it exists in original code)
    if ($('#prayertimes_geocode_btn').length > 0) {
        $('#prayertimes_geocode_btn').on('click', function() {
            var address = $('#prayertimes_address').val();
            if (!address) {
                $('#prayertimes_geocode_results').html('<div class="notice notice-error inline"><p>Please enter an address to search.</p></div>');
                return;
            }
            
            $(this).text('Searching...').prop('disabled', true);
            
            $.ajax({
                url: ptpAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'prayertimes_geocode',
                    nonce: ptpAdmin.geocode_nonce,
                    address: address
                },
                success: function(response) {
                    $('#prayertimes_geocode_btn').text('Find Coordinates').prop('disabled', false);
                    
                    if (response.success) {
                        var result = response.data;
                        $('#prayertimes_lat').val(result.lat);
                        $('#prayertimes_lng').val(result.lon);
                        $('#prayertimes_geocode_results').html('<div class="notice notice-success inline"><p>Found: ' + result.display_name + '</p></div>');
                    } else {
                        $('#prayertimes_geocode_results').html('<div class="notice notice-error inline"><p>Error: ' + response.data + '</p></div>');
                    }
                },
                error: function() {
                    $('#prayertimes_geocode_btn').text('Find Coordinates').prop('disabled', false);
                    $('#prayertimes_geocode_results').html('<div class="notice notice-error inline"><p>Error connecting to server.</p></div>');
                }
            });
        });
    }
    
    // Generate prayer times functionality for generating calculated times
    $('#prayertimes_generate_btn').on('click', function() {
        $(this).text('Generating...').prop('disabled', true);
        $('#prayertimes_export_result').html('<p>Generating prayer times, please wait...</p>');
        
        // Get the selected period value - fix the ID to match your HTML
        var selectedPeriod = $('#prayertimes_period').val();
        console.log("Selected period:", selectedPeriod); // Debug info
        
        $.ajax({
            url: ptpAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'prayertimes_generate_times',
                nonce: ptpAdmin.export_nonce,
                period: selectedPeriod
            },
            success: function(response) {
                $('#prayertimes_generate_btn').text('Generate Prayer Times').prop('disabled', false);
                
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
                    
                    $('#prayertimes_export_result').html('<div class="notice notice-success inline"><p>Prayer times generated successfully. Download should begin automatically.</p></div>');
                } else {
                    $('#prayertimes_export_result').html('<div class="notice notice-error inline"><p>Error: ' + response.data + '</p></div>');
                    console.error("Error:", response.data);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $('#prayertimes_generate_btn').text('Generate Prayer Times').prop('disabled', false);
                $('#prayertimes_export_result').html('<div class="notice notice-error inline"><p>Error connecting to server.</p></div>');
                console.error("AJAX error:", textStatus, errorThrown);
            }
        });
    });
    
    // Export prayer times from database functionality
    $('#prayertimes_export_db_btn').on('click', function() {
        $(this).text('Exporting...').prop('disabled', true);
        $('#prayertimes_export_result').html('<p>Exporting prayer times from database, please wait...</p>');
        
        $.ajax({
            url: ptpAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'prayertimes_export_db',
                nonce: ptpAdmin.export_db_nonce
            },
            success: function(response) {
                $('#prayertimes_export_db_btn').text('Export Existing Prayer Times').prop('disabled', false);
                
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
                    
                    $('#prayertimes_export_result').html('<div class="notice notice-success inline"><p>Prayer times exported successfully. Download should begin automatically.</p></div>');
                } else {
                    $('#prayertimes_export_result').html('<div class="notice notice-error inline"><p>Error: ' + response.data + '</p></div>');
                }
            },
            error: function() {
                $('#prayertimes_export_db_btn').text('Export Existing Prayer Times').prop('disabled', false);
                $('#prayertimes_export_result').html('<div class="notice notice-error inline"><p>Error connecting to server.</p></div>');
            }
        });
    });
    
    // Preview import functionality
    $('#prayertimes_preview_btn').on('click', function() {
        var fileInput = $('#prayertimes_import_file')[0];
        
        if (!fileInput.files || !fileInput.files[0]) {
            $('#prayertimes_import_preview').html('<div class="notice notice-error inline"><p>Please select a CSV file to import.</p></div>');
            return;
        }
        
        var file = fileInput.files[0];
        
        // Check if file is a CSV
        if (file.type !== 'text/csv' && !file.name.endsWith('.csv')) {
            $('#prayertimes_import_preview').html('<div class="notice notice-error inline"><p>Please select a valid CSV file.</p></div>');
            return;
        }
        
        // Create FormData object
        var formData = new FormData();
        formData.append('action', 'prayertimes_import_preview');
        formData.append('nonce', ptpAdmin.import_preview_nonce);
        formData.append('import_file', file);
        
        $(this).text('Previewing...').prop('disabled', true);
        
        // Send AJAX request
        $.ajax({
            url: ptpAdmin.ajaxurl,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(response) {
                $('#prayertimes_preview_btn').text('Preview Import').prop('disabled', false);
                
                if (response.success) {
                    var data = response.data;
                    var preview = data.preview;
                    
                    // Enable import button
                    $('#prayertimes_import_btn').prop('disabled', false);
                    
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
                    
                    $('#prayertimes_import_preview').html(html);
                } else {
                    $('#prayertimes_import_preview').html('<div class="notice notice-error inline"><p>Error: ' + response.data + '</p></div>');
                }
            },
            error: function() {
                $('#prayertimes_preview_btn').text('Preview Import').prop('disabled', false);
                $('#prayertimes_import_preview').html('<div class="notice notice-error inline"><p>Error connecting to server.</p></div>');
            }
        });
    });
    
    // Import functionality
    $('#prayertimes_import_btn').on('click', function() {
        var fileInput = $('#prayertimes_import_file')[0];
        
        if (!fileInput.files || !fileInput.files[0]) {
            $('#prayertimes_import_result').html('<div class="notice notice-error inline"><p>Please select a CSV file to import.</p></div>');
            return;
        }
        
        if (!confirm('Are you sure you want to import these prayer times? This will overwrite any existing times for the same dates.')) {
            return;
        }
        
        var file = fileInput.files[0];
        var formData = new FormData();
        formData.append('action', 'prayertimes_import');
        formData.append('nonce', ptpAdmin.import_nonce);
        formData.append('import_file', file);
        
        $(this).text('Importing...').prop('disabled', true);
        
        // Send AJAX request
        $.ajax({
            url: ptpAdmin.ajaxurl,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(response) {
                $('#prayertimes_import_btn').text('Import Prayer Times').prop('disabled', false);
                
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
                    
                    $('#prayertimes_import_result').html(html);
                    
                    // Clear the preview
                    $('#prayertimes_import_preview').empty();
                    
                    // Disable import button until next preview
                    $('#prayertimes_import_btn').prop('disabled', true);
                } else {
                    $('#prayertimes_import_result').html('<div class="notice notice-error inline"><p>Error: ' + response.data + '</p></div>');
                }
            },
            error: function() {
                $('#prayertimes_import_btn').text('Import Prayer Times').prop('disabled', false);
                $('#prayertimes_import_result').html('<div class="notice notice-error inline"><p>Error connecting to server.</p></div>');
            }
        });
    });
});
