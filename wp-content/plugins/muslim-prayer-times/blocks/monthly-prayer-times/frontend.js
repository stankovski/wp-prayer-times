/**
 * Frontend JavaScript for Monthly Prayer Times block
 */
jQuery(document).ready(function($) {
    // Initialize pagination for all monthly prayer time blocks on the page
    $('.wp-block-prayer-times-monthly-prayer-times').each(function() {
        initMonthlyPrayerTimes($(this));
    });
    
    // Function to initialize pagination for a specific monthly prayer times instance
    function initMonthlyPrayerTimes($block) {
        var blockId = $block.attr('id');
        var initialMonth = parseInt($block.data('month'));
        var initialYear = parseInt($block.data('year'));
        var currentMonth = initialMonth;
        var currentYear = initialYear;
        var showSunrise = $block.data('show-sunrise');
        var showIqama = $block.data('show-iqama');
        var highlightToday = $block.data('highlight-today');
        var tableStyle = $block.data('table-style');
        var reportType = $block.data('report-type') || 'monthly';
        var showPagination = $block.data('show-pagination');
        
        // Set up pagination buttons
        var $prevButton = $block.find('.prev-page');
        var $nextButton = $block.find('.next-page');
        var $currentMonthSpan = $block.find('.month-name');
        var $tableContainer = $block.find('.prayer-times-table-container');
        
        // Skip pagination setup for non-monthly report types or when pagination is disabled
        if (reportType !== 'monthly' || !showPagination) {
            return;
        }
        
        // Initially check adjacent months for prayer times
        checkAdjacentMonths(initialMonth, initialYear);
        
        // Add click handler for next button
        $nextButton.on('click', function() {
            if ($(this).prop('disabled')) return;
            
            // Go to next month
            currentMonth++;
            if (currentMonth > 12) {
                currentMonth = 1;
                currentYear++;
            }
            loadMonth(currentMonth, currentYear);
        });
        
        // Add click handler for previous button
        $prevButton.on('click', function() {
            if ($(this).prop('disabled')) return;
            
            // Go to previous month
            currentMonth--;
            if (currentMonth < 1) {
                currentMonth = 12;
                currentYear--;
            }
            loadMonth(currentMonth, currentYear);
        });
        
        // Function to check if adjacent months have prayer times
        function checkAdjacentMonths(month, year) {
            // Calculate previous and next month/year
            var prevMonth = month - 1;
            var prevYear = year;
            if (prevMonth < 1) {
                prevMonth = 12;
                prevYear--;
            }
            
            var nextMonth = month + 1;
            var nextYear = year;
            if (nextMonth > 12) {
                nextMonth = 1;
                nextYear++;
            }
            
            // Check previous month
            checkMonthAvailability(prevMonth, prevYear, function(hasRecords) {
                $prevButton.prop('disabled', !hasRecords);
                if (!hasRecords) {
                    $prevButton.addClass('disabled');
                } else {
                    $prevButton.removeClass('disabled');
                }
            });
            
            // Check next month
            checkMonthAvailability(nextMonth, nextYear, function(hasRecords) {
                $nextButton.prop('disabled', !hasRecords);
                if (!hasRecords) {
                    $nextButton.addClass('disabled');
                } else {
                    $nextButton.removeClass('disabled');
                }
            });
        }
        
        // Function to check if a month has prayer times
        function checkMonthAvailability(month, year, callback) {
            $.ajax({
                url: muslprti_monthly_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'muslprti_check_month_availability',
                    nonce: muslprti_monthly_ajax.nonce,
                    month: month,
                    year: year
                },
                success: function(response) {
                    if (response.success) {
                        callback(response.data.has_records);
                    } else {
                        // Default to false on error
                        callback(false);
                    }
                },
                error: function() {
                    // Default to false on error
                    callback(false);
                }
            });
        }
        
        // Function to load a specific month via AJAX
        function loadMonth(month, year) {
            // Show loading indicator
            showLoading();
            
            // Make AJAX request
            $.ajax({
                url: muslprti_monthly_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'muslprti_monthly_prayer_times_pagination',
                    nonce: muslprti_monthly_ajax.nonce,
                    month: month,
                    year: year,
                    show_sunrise: showSunrise ? '1' : '0',
                    show_iqama: showIqama ? '1' : '0',
                    highlight_today: highlightToday ? '1' : '0',
                    table_style: tableStyle,
                    report_type: reportType
                },
                success: function(response) {
                    if (response.success) {
                        // Update table HTML
                        $tableContainer.html(response.data.table_html);
                        
                        // Update month header
                        $currentMonthSpan.text(response.data.month_name + ' ' + year);
                        
                        // Update current month/year state
                        currentMonth = month;
                        currentYear = year;
                        
                        // Check adjacent months for availability
                        checkAdjacentMonths(month, year);
                    } else {
                        // Display error message
                        $tableContainer.html('<p class="error">Error loading prayer times: ' + response.data + '</p>');
                    }
                    
                    // Hide loading indicator
                    hideLoading();
                },
                error: function() {
                    // Display error message
                    $tableContainer.html('<p class="error">Network error when loading prayer times. Please try again.</p>');
                    
                    // Hide loading indicator
                    hideLoading();
                }
            });
        }
        
        // Show loading indicator
        function showLoading() {
            if ($tableContainer.find('.prayer-times-loading').length === 0) {
                $tableContainer.css('position', 'relative').append('<div class="prayer-times-loading"></div>');
            }
        }
        
        // Hide loading indicator
        function hideLoading() {
            $tableContainer.find('.prayer-times-loading').remove();
        }
    }
});
