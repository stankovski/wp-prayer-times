/**
 * Live Prayer Times Frontend JS
 * Handles real-time clock functionality and data loading via AJAX
 */
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all live prayer time blocks on the page
    const liveBlocks = document.querySelectorAll('.wp-block-prayer-times-live-prayer-times');
    liveBlocks.forEach(initLiveBlock);
    
    // Start global clock update
    updateAllClocks();
    // Update the clock every second
    setInterval(updateAllClocks, 1000);
});

/**
 * Initialize a live prayer times block
 */
function initLiveBlock(block) {
    // Get today's date in YYYY-MM-DD format
    const today = new Date();
    const dateStr = today.toISOString().split('T')[0];
    
    // Load initial prayer times data
    loadPrayerTimesData(block, dateStr);
    
    // Set up midnight refresh
    scheduleNextDayRefresh();
}

/**
 * Schedule a refresh for midnight to update to next day's data
 */
function scheduleNextDayRefresh() {
    const now = new Date();
    const tomorrow = new Date(now);
    tomorrow.setDate(tomorrow.getDate() + 1);
    tomorrow.setHours(0, 0, 10, 0); // 10 seconds after midnight
    
    const timeUntilMidnight = tomorrow - now;
    
    console.log('Scheduling next day refresh in ' + Math.floor(timeUntilMidnight/1000/60) + ' minutes');
    
    setTimeout(function() {
        // When midnight hits, refresh all blocks with new date
        const newDateStr = new Date().toISOString().split('T')[0];
        const liveBlocks = document.querySelectorAll('.wp-block-prayer-times-live-prayer-times');
        
        liveBlocks.forEach(function(block) {
            loadPrayerTimesData(block, newDateStr);
        });
        
        // Schedule the next midnight refresh
        scheduleNextDayRefresh();
    }, timeUntilMidnight);
}

/**
 * Load prayer times data from the server via AJAX
 */
function loadPrayerTimesData(block, dateStr) {
    // Store the date we're loading
    block.setAttribute('data-current-date', dateStr);
    
    // Show loading state
    const loadingElement = document.createElement('div');
    loadingElement.className = 'prayer-times-loading';
    loadingElement.textContent = 'Loading prayer times...';
    
    // Replace table with loading message if this is not initial load
    const existingTable = block.querySelector('.prayer-times-live-table');
    if (existingTable) {
        existingTable.style.opacity = '0.5';
    }
    
    // Build the AJAX URL with the date
    const ajaxUrl = prayerTimesLiveData.ajaxUrl + '/' + dateStr;
    
    // Make the AJAX request
    fetch(ajaxUrl, {
        method: 'GET',
        headers: {
            'X-WP-Nonce': prayerTimesLiveData.nonce,
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        // Update the block with the new data
        updateBlockWithData(block, data);
        
        // Remove loading state
        if (existingTable) {
            existingTable.style.opacity = '1';
        }
    })
    .catch(error => {
        console.error('Error fetching prayer times:', error);
        
        // Show error in the block
        if (existingTable) {
            existingTable.style.opacity = '1';
        }
    });
}

/**
 * Update the block with the new prayer times data
 */
function updateBlockWithData(block, data) {
    // Get configuration
    const showDate = block.getAttribute('data-show-date') === '1';
    const showHijriDate = block.getAttribute('data-show-hijri-date') === '1';
    const showSunrise = block.getAttribute('data-show-sunrise') === '1';
    const showChanges = block.getAttribute('data-show-changes') === '1';
    const changeColor = block.getAttribute('data-change-color') || '#ff0000';
    
    // Update date display if enabled
    if (showDate) {
        const dateElement = block.querySelector('.gregorian-date');
        if (dateElement) {
            dateElement.textContent = data.display_date;
        }
        
        if (showHijriDate) {
            const hijriElement = block.querySelector('.hijri-date');
            if (hijriElement && data.hijri_date) {
                hijriElement.textContent = data.hijri_date;
            }
            
            const hijriArabicElement = block.querySelector('.hijri-date-arabic');
            if (hijriArabicElement && data.hijri_date_arabic) {
                hijriArabicElement.textContent = data.hijri_date_arabic;
            }
        }
    }
    
    // Define the prayer mapping for easy access
    const prayerMap = {
        'fajr': {
            'athan': 'fajr_athan',
            'iqama': 'fajr_iqama',
            'row': block.querySelector('.prayer-times-live-table tbody tr:nth-child(1)')
        },
        'sunrise': {
            'time': 'sunrise',
            'row': block.querySelector('.prayer-times-live-table tbody tr.sunrise-row')
        },
        'dhuhr': {
            'athan': 'dhuhr_athan',
            'iqama': 'dhuhr_iqama',
            'row': block.querySelector('.prayer-times-live-table tbody tr:nth-child(' + (showSunrise ? '3' : '2') + ')')
        },
        'asr': {
            'athan': 'asr_athan',
            'iqama': 'asr_iqama',
            'row': block.querySelector('.prayer-times-live-table tbody tr:nth-child(' + (showSunrise ? '4' : '3') + ')')
        },
        'maghrib': {
            'athan': 'maghrib_athan',
            'iqama': 'maghrib_iqama',
            'row': block.querySelector('.prayer-times-live-table tbody tr:nth-child(' + (showSunrise ? '5' : '4') + ')')
        },
        'isha': {
            'athan': 'isha_athan',
            'iqama': 'isha_iqama',
            'row': block.querySelector('.prayer-times-live-table tbody tr:nth-child(' + (showSunrise ? '6' : '5') + ')')
        }
    };
    
    // Update the prayer times
    for (const prayer in prayerMap) {
        const prayerData = prayerMap[prayer];
        const row = prayerData.row;
        
        if (!row) continue;
        
        if (prayer === 'sunrise') {
            // Sunrise has a special format with just one time
            if (showSunrise) {
                const timeCell = row.querySelector('td:nth-child(2)');
                if (timeCell) {
                    timeCell.textContent = data.times.sunrise || '-';
                }
            }
        } else {
            // Regular prayers with athan and iqama
            const athanCell = row.querySelector('td:nth-child(2)');
            const iqamaCell = row.querySelector('td:nth-child(3)');
            
            if (athanCell) {
                athanCell.textContent = data.times[prayerData.athan] || '-';
            }
            
            if (iqamaCell) {
                iqamaCell.textContent = data.times[prayerData.iqama] || '-';
            }
        }
    }
    
    // Update changes column if applicable
    if (showChanges) {
        let changeHeaders = block.querySelectorAll('.changes-column');
        
        // Check if we have any upcoming changes
        let hasChanges = Object.keys(data.future_changes).length > 0;
        
        if (hasChanges) {
            // Sort changes by date
            const sortedDays = Object.keys(data.future_changes).sort();
            const earliestChange = data.future_changes[sortedDays[0]];
            
            // Update the header with the date of the earliest change
            if (changeHeaders.length > 0 && earliestChange) {
                // Get the table header for changes
                const changeHeader = block.querySelector('.prayer-times-live-table thead th.changes-column');
                if (changeHeader) {
                    changeHeader.textContent = earliestChange.date.split(',')[0]; // Just show day, like "Mon"
                    changeHeader.style.color = changeColor;
                }
                
                // Update each prayer row with its change if any
                for (const prayer in prayerMap) {
                    const prayerData = prayerMap[prayer];
                    const row = prayerData.row;
                    
                    if (!row) continue;
                    
                    // Get the changes cell
                    const changesCell = row.querySelector('.changes-column');
                    if (!changesCell) continue;
                    
                    // Clear any existing content
                    changesCell.innerHTML = '';
                    
                    // Check if there's a change for this prayer
                    if (prayer !== 'sunrise') { // Sunrise changes not tracked
                        // Look for changes in each day
                        for (const day of sortedDays) {
                            const dayChanges = data.future_changes[day].changes;
                            
                            // Check for iqama change
                            if (dayChanges[prayerData.iqama]) {
                                changesCell.innerHTML = '<span class="time-change">' + 
                                    dayChanges[prayerData.iqama].new_time + '</span>';
                                changesCell.style.color = changeColor;
                                break;
                            }
                            
                            // If no iqama change, check for athan change
                            if (dayChanges[prayerData.athan]) {
                                changesCell.innerHTML = '<span class="time-change">' + 
                                    dayChanges[prayerData.athan].new_time + '</span>';
                                changesCell.style.color = changeColor;
                                break;
                            }
                        }
                    }
                }
            }
        }
    }
    
    // Update Jumuah times if any
    if (data.jumuah_times && data.jumuah_times.length > 0) {
        const jumuahContainer = block.querySelector('.prayer-times-jumuah');
        
        if (jumuahContainer) {
            const jumuahTable = jumuahContainer.querySelector('table');
            const tableRow = jumuahTable.querySelector('tr');
            
            // Clear existing cells
            tableRow.innerHTML = '';
            
            // Add cells for each Jumuah time
            data.jumuah_times.forEach(function(jumuah) {
                const cell = document.createElement('td');
                cell.className = 'jumuah-time';
                
                const nameSpan = document.createElement('span');
                nameSpan.className = 'jumuah-label';
                nameSpan.textContent = jumuah.name;
                
                const timeSpan = document.createElement('span');
                timeSpan.className = 'jumuah-time-value';
                timeSpan.textContent = jumuah.time;
                
                cell.appendChild(nameSpan);
                cell.appendChild(timeSpan);
                tableRow.appendChild(cell);
            });
        }
    }
    
    // After updating the data, re-highlight the next prayer
    highlightNextPrayer(block);
}

/**
 * Update all clock elements on the page
 */
function updateAllClocks() {
    const liveBlocks = document.querySelectorAll('.wp-block-prayer-times-live-prayer-times');
    
    // Get current time - using browser's timezone which should reflect user's local time
    const now = new Date();
    
    liveBlocks.forEach(block => {
        // Get configuration from data attributes
        const showSeconds = block.getAttribute('data-show-seconds') === '1';
        const timeFormat = block.getAttribute('data-time-format') || '12hour';
        
        // Get time string based on format
        const timeStr = formatTime(now, timeFormat, showSeconds);
        
        // Update the clock display
        const clockElement = block.querySelector('.live-time');
        if (clockElement) {
            clockElement.innerHTML = timeStr;
        }
        
        // Check if we need to highlight a different prayer
        // Only recheck this every minute (when seconds is 0)
        if (now.getSeconds() === 0) {
            highlightNextPrayer(block);
        }
    });
}

/**
 * Format time according to specified format
 */
function formatTime(date, format, showSeconds) {
    let hours = date.getHours();
    const minutes = date.getMinutes().toString().padStart(2, '0');
    const seconds = date.getSeconds().toString().padStart(2, '0');
    let ampm = '';
    
    if (format === '12hour') {
        ampm = hours >= 12 ? ' PM' : ' AM';
        hours = hours % 12;
        hours = hours ? hours : 12; // Convert 0 to 12 for 12-hour format
        hours = hours.toString();
        
        return showSeconds ? 
            `${hours}:${minutes}<span class="clock-small">${seconds}</span><span class="clock-small">${ampm}</span>` : 
            `${hours}:${minutes}<span class="clock-small">${ampm}</span>`;
    } else {
        // 24-hour format
        hours = hours.toString().padStart(2, '0');
        return showSeconds ? 
            `${hours}:${minutes}<span class="clock-small">${seconds}</span>` : 
            `${hours}:${minutes}`;
    }
}

/**
 * Highlight the next upcoming prayer time
 */
function highlightNextPrayer(block) {
    const now = new Date();
    const prayerRows = block.querySelectorAll('.prayer-times-live-table tbody tr');
    let nextPrayerFound = false;
    
    // Clear any existing highlight
    prayerRows.forEach(row => {
        row.classList.remove('next-prayer');
        row.classList.remove('active-prayer');
    });
    
    // Get the time format being used
    const timeFormat = block.getAttribute('data-time-format') || '12hour';
    
    // Get the next prayer color from the data attribute
    const nextPrayerColor = block.getAttribute('data-next-prayer-color') || 'rgba(255, 255, 102, 0.3)';
    
    // Get all prayer times and find the next one
    for (let i = 0; i < prayerRows.length; i++) {
        const row = prayerRows[i];
        let timeCell = row.querySelector('td:nth-child(3)');  // Iqama time cell
        
        // For sunrise row which has a special format
        if (row.classList.contains('sunrise-row')) {
            timeCell = row.querySelector('td:nth-child(2)');  // Sunrise time is in second cell
        }
        
        if (!timeCell) continue;
        
        let timeText = timeCell.textContent.trim();
        if (timeText === '-') continue;
        
        let hour, minute;
        
        if (timeFormat === '12hour') {
            // Parse the time in 12-hour format (like "6:30 AM")
            const [timePart, meridiem] = timeText.split(' ');
            const [hourStr, minuteStr] = timePart.split(':');
            
            hour = parseInt(hourStr, 10);
            minute = parseInt(minuteStr, 10);
            
            // Convert to 24-hour format for comparison
            if (meridiem === 'PM' && hour < 12) {
                hour += 12;
            } else if (meridiem === 'AM' && hour === 12) {
                hour = 0;
            }
        } else {
            // Parse the time in 24-hour format (like "18:30")
            const [hourStr, minuteStr] = timeText.split(':');
            hour = parseInt(hourStr, 10);
            minute = parseInt(minuteStr, 10);
        }
        
        // Create a Date object for comparison
        const prayerTime = new Date();
        prayerTime.setHours(hour, minute, 0);
        
        // Check if this prayer is in the future
        if (prayerTime > now) {
            row.classList.add('next-prayer');
            // Apply the next prayer color inline
            const cells = row.querySelectorAll('td');
            cells.forEach(cell => {
                cell.style.backgroundColor = nextPrayerColor;
            });
            nextPrayerFound = true;
            break;
        }
        
        // If it's currently this prayer time (within 15 minutes after the iqama)
        const fifteenMinutesAfter = new Date(prayerTime);
        fifteenMinutesAfter.setMinutes(fifteenMinutesAfter.getMinutes() + 15);
        if (now >= prayerTime && now <= fifteenMinutesAfter) {
            row.classList.add('active-prayer');
        }
    }
    
    // If no next prayer found (all prayers for today have passed)
    // highlight the first prayer of the day (Fajr) as the next one
    if (!nextPrayerFound && prayerRows.length > 0) {
        const firstRow = prayerRows[0];
        firstRow.classList.add('next-prayer');
        // Apply the next prayer color inline
        const cells = firstRow.querySelectorAll('td');
        cells.forEach(cell => {
            cell.style.backgroundColor = nextPrayerColor;
        });
    }
}
