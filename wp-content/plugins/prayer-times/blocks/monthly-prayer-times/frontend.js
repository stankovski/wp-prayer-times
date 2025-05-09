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
        
        // Set up pagination buttons
        var $prevButton = $block.find('.prev-page');
        var $nextButton = $block.find('.next-page');
        var $currentMonthSpan = $block.find('.month-name');
        var $tableContainer = $block.find('.prayer-times-table-container');
        
        // Add click handler for next button
        $nextButton.on('click', function() {
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
            // Go to previous month
            currentMonth--;
            if (currentMonth < 1) {
                currentMonth = 12;
                currentYear--;
            }
            loadMonth(currentMonth, currentYear);
        });
        
        // Function to load a specific month via AJAX
        function loadMonth(month, year) {
            // Show loading indicator
            showLoading();
            
            // Make AJAX request
            $.ajax({
                url: prayertimes_monthly_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'prayertimes_monthly_prayer_times_pagination',
                    nonce: prayertimes_monthly_ajax.nonce,
                    month: month,
                    year: year,
                    show_sunrise: showSunrise ? '1' : '0',
                    show_iqama: showIqama ? '1' : '0',
                    highlight_today: highlightToday ? '1' : '0',
                    table_style: tableStyle
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
                        
                        // Check if we're at current month to manage button states
                        var now = new Date();
                        var isCurrentMonth = (now.getMonth() + 1) === month && now.getFullYear() === year;
                        var isPastLimit = (year < now.getFullYear() - 1) || 
                                          (year === now.getFullYear() - 1 && month < now.getMonth() + 1);
                        var isFutureLimit = (year > now.getFullYear() + 1) || 
                                            (year === now.getFullYear() + 1 && month > now.getMonth() + 1);
                        
                        // Optionally disable buttons if reaching past/future limits
                        // $prevButton.prop('disabled', isPastLimit);
                        // $nextButton.prop('disabled', isFutureLimit);
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
