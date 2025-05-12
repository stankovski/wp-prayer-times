/**
 * Live Prayer Times Frontend JS
 * Handles real-time clock functionality
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
    // Highlight the next prayer time
    highlightNextPrayer(block);
}

/**
 * Update all clock elements on the page
 */
function updateAllClocks() {
    const liveBlocks = document.querySelectorAll('.wp-block-prayer-times-live-prayer-times');
    
    // Get current time - using browser's timezone which should reflect user's local time
    // Note: Server timezone is already handled in the PHP side when generating prayer times
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
        prayerRows[0].classList.add('next-prayer');
    }
}
