<?php
/**
 * Live Prayer Times Gutenberg Block
 */

if (!defined('ABSPATH')) exit;

/**
 * Register the block
 */
function muslprti_register_live_prayer_times_block() {
    // Register block script
    wp_register_script(
        'muslprti-live-prayer-times-block',
        plugins_url('block.js', __FILE__),
        array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components'),
        filemtime(plugin_dir_path(__FILE__) . 'block.js')
    );

    // Register frontend script
    wp_register_script(
        'muslprti-live-prayer-times-frontend',
        plugins_url('frontend.js', __FILE__),
        array('jquery'),
        filemtime(plugin_dir_path(__FILE__) . 'frontend.js'),
        true
    );

    // Register block styles
    wp_register_style(
        'muslprti-live-prayer-times-style',
        plugins_url('style.css', __FILE__),
        array(),
        filemtime(plugin_dir_path(__FILE__) . 'style.css')
    );

    // Register the block
    register_block_type('prayer-times/live-prayer-times', array(
        'editor_script' => 'muslprti-live-prayer-times-block',
        'editor_style' => 'muslprti-live-prayer-times-style',
        'style' => 'muslprti-live-prayer-times-style',
        'script' => 'muslprti-live-prayer-times-frontend',
        'render_callback' => 'muslprti_render_live_prayer_times_block',
        'attributes' => array(
            'className' => array(
                'type' => 'string',
                'default' => '',
            ),
            'align' => array(
                'type' => 'string',
                'default' => 'center',
            ),
            'textColor' => array(
                'type' => 'string',
                'default' => '',
            ),
            'backgroundColor' => array(
                'type' => 'string',
                'default' => '',
            ),
            'headerColor' => array(
                'type' => 'string',
                'default' => '',
            ),
            'headerTextColor' => array(
                'type' => 'string',
                'default' => '',
            ),
            'highlightColor' => array(
                'type' => 'string',
                'default' => '',
            ),
            'clockColor' => array(
                'type' => 'string',
                'default' => '',
            ),
            'clockSize' => array(
                'type' => 'number',
                'default' => 40,
            ),
            'showDate' => array(
                'type' => 'boolean',
                'default' => true,
            ),
            'showHijriDate' => array(
                'type' => 'boolean',
                'default' => true,
            ),
            'showSunrise' => array(
                'type' => 'boolean',
                'default' => true,
            ),
            'tableStyle' => array(
                'type' => 'string',
                'default' => 'default',
            ),
            'fontSize' => array(
                'type' => 'number',
                'default' => 16,
            ),
            'showSeconds' => array(
                'type' => 'boolean',
                'default' => true,
            ),
            'showChanges' => array(
                'type' => 'boolean',
                'default' => true,
            ),
            'changeColor' => array(
                'type' => 'string',
                'default' => '#ff0000',
            ),
            'nextPrayerColor' => array(
                'type' => 'string',
                'default' => 'rgba(255, 255, 102, 0.3)',
            ),
        ),
    ));
}
add_action('init', 'muslprti_register_live_prayer_times_block');

/**
 * Render the Live Prayer Times block on the frontend
 */
function muslprti_render_live_prayer_times_block($attributes) {
    // Load Hijri date converter if needed
    if (isset($attributes['showHijriDate']) && $attributes['showHijriDate']) {
        require_once plugin_dir_path(dirname(dirname(__FILE__))) . 'includes/hijri-date-converter.php';
    }
    
    // Get timezone from settings
    $opts = get_option('muslprti_settings', []);
    $timezone = muslprti_get_timezone();
    $timeFormat = isset($opts['time_format']) ? $opts['time_format'] : '12hour';

    // Extract attributes for styling
    $className = isset($attributes['className']) ? $attributes['className'] : '';
    $align = isset($attributes['align']) ? $attributes['align'] : 'center';
    $textColor = isset($attributes['textColor']) ? $attributes['textColor'] : '';
    $backgroundColor = isset($attributes['backgroundColor']) ? $attributes['backgroundColor'] : '';
    $headerColor = isset($attributes['headerColor']) ? $attributes['headerColor'] : '';
    $headerTextColor = isset($attributes['headerTextColor']) ? $attributes['headerTextColor'] : '';
    $highlightColor = isset($attributes['highlightColor']) ? $attributes['highlightColor'] : '';
    $clockColor = isset($attributes['clockColor']) ? $attributes['clockColor'] : '';
    $clockSize = isset($attributes['clockSize']) ? $attributes['clockSize'] : 40;
    $showDate = isset($attributes['showDate']) ? $attributes['showDate'] : true;
    $showHijriDate = isset($attributes['showHijriDate']) ? $attributes['showHijriDate'] : true;
    $showSunrise = isset($attributes['showSunrise']) ? $attributes['showSunrise'] : true;
    $tableStyle = isset($attributes['tableStyle']) ? $attributes['tableStyle'] : 'default';
    $fontSize = isset($attributes['fontSize']) ? $attributes['fontSize'] : 16;
    $showSeconds = isset($attributes['showSeconds']) ? $attributes['showSeconds'] : true;
    $showChanges = isset($attributes['showChanges']) ? $attributes['showChanges'] : true;
    $changeColor = isset($attributes['changeColor']) ? $attributes['changeColor'] : '#ff0000';
    $nextPrayerColor = isset($attributes['nextPrayerColor']) ? $attributes['nextPrayerColor'] : 'rgba(255, 255, 102, 0.3)';
    
    // Create inline styles
    $container_style = "text-align: {$align};";
    if ($fontSize) {
        $container_style .= "font-size: {$fontSize}px;";
    }
    
    $row_style = '';
    if ($backgroundColor) {
        $row_style .= "background-color: {$backgroundColor};";
    }

    $table_style = '';
    if ($textColor) {
        $table_style .= "color: {$textColor};";
    }
    
    $header_style = '';
    if ($headerColor) {
        $header_style .= "background-color: {$headerColor};";
    }
    if ($headerTextColor) {
        $header_style .= "color: {$headerTextColor};";
    }
    
    $highlight_style = '';
    if ($highlightColor) {
        $highlight_style .= "color: {$highlightColor};";
    }

    $change_header_style = "color: {$changeColor};";
    if ($headerColor) {
        $change_header_style .= "background-color: {$headerColor};";
    }

    $clock_style = '';
    if ($clockColor) {
        $clock_style .= "color: {$clockColor};";
    }
    if ($clockSize) {
        $clock_style .= "font-size: {$clockSize}px;";
    }
    
    // Define icons for each prayer
    $icons_dir = plugins_url('assets/icons/', dirname(__DIR__));
    $prayer_icons = array(
        'fajr' => $icons_dir . 'fajr.svg',
        'sunrise' => $icons_dir . 'sunrise.svg',
        'dhuhr' => $icons_dir . 'dhuhr.svg',
        'asr' => $icons_dir . 'asr.svg',
        'maghrib' => $icons_dir . 'maghrib.svg',
        'isha' => $icons_dir . 'isha.svg',
        'athan' => $icons_dir . 'athan.svg',
        'iqama' => $icons_dir . 'iqama.svg',
    );
    
    // Generate a unique ID for this block instance
    $block_id = 'muslprti-live-' . uniqid();
    
    // Build the HTML output
    $output = '<div id="' . esc_attr($block_id) . '" class="wp-block-prayer-times-live-prayer-times ' . esc_attr($className) . '" 
               style="' . esc_attr($container_style) . '"
               data-show-seconds="' . esc_attr($showSeconds ? '1' : '0') . '"
               data-show-date="' . esc_attr($showDate ? '1' : '0') . '"
               data-show-hijri-date="' . esc_attr($showHijriDate ? '1' : '0') . '"
               data-show-sunrise="' . esc_attr($showSunrise ? '1' : '0') . '"
               data-show-changes="' . esc_attr($showChanges ? '1' : '0') . '"
               data-change-color="' . esc_attr($changeColor) . '"
               data-next-prayer-color="' . esc_attr($nextPrayerColor) . '"
               data-time-format="' . esc_attr($timeFormat) . '">';
    
    // Add the current time clock
    $output .= '<div class="live-prayer-clock" style="' . esc_attr($clock_style) . '">';
    $output .= '<span class="live-time">00:00:00</span>';
    $output .= '</div>';
    
    // Add date placeholder if enabled
    if ($showDate) {
        $output .= '<div class="prayer-times-date">';
        $output .= '<div class="gregorian-date">Loading date...</div>';
        if ($showHijriDate) {
            $output .= '<div class="hijri-date"></div>';
            $output .= '<div class="hijri-date-arabic" style="' . esc_attr($highlight_style) . '"></div>';
        }
        $output .= '</div>';
    }
    
    // Prayer times table (initial structure that will be populated via AJAX)
    $output .= '<table class="prayer-times-live-table ' . esc_attr('table-style-' . $tableStyle) . '" style="' . esc_attr($table_style) . '">';
    
    // Table header
    $output .= '<thead><tr style="' . esc_attr($header_style) . '">';
    $output .= '<th style="' . esc_attr($header_style) . '"></th><th style="' . esc_attr($header_style) . '"><img src="' . esc_url($prayer_icons['athan']) . '" alt="Athan" class="header-icon">Athan</th><th style="' . esc_attr($header_style) . '"><img src="' . esc_url($prayer_icons['iqama']) . '" alt="Iqama" class="header-icon">Iqama</th>';
    
    // Add the changes column header if enabled
    if ($showChanges) {
        $output .= '<th style="' . esc_attr($change_header_style) . '" class="changes-column"></th>';
    }
    
    $output .= '</tr></thead>';
    
    // Table body - Empty rows that will be filled by JavaScript
    $output .= '<tbody>';
    
    // Prayer rows - Create the structure with icons, but no times
    $prayer_map = array(
        'fajr' => array('name' => 'Fajr'),
        'sunrise' => array('name' => 'Sunrise'),
        'dhuhr' => array('name' => 'Dhuhr'),
        'asr' => array('name' => 'Asr'),
        'maghrib' => array('name' => 'Maghrib'),
        'isha' => array('name' => 'Isha')
    );
    
    foreach ($prayer_map as $prayer_key => $prayer_data) {
        // Skip sunrise if not showing
        if ($prayer_key === 'sunrise' && !$showSunrise) {
            continue;
        }
        
        $output .= '<tr' . ($prayer_key === 'sunrise' ? ' class="sunrise-row"' : '') . ' style="' . esc_attr($row_style) . '">';
        $output .= '<td class="prayer-name"><img src="' . esc_url($prayer_icons[$prayer_key]) . '" alt="' . esc_attr($prayer_data['name']) . '" class="prayer-icon"> ' . esc_html($prayer_data['name']) . '</td>';
        
        if ($prayer_key === 'sunrise') {
            // Sunrise has a single time column that spans both athan and iqama
            $output .= '<td class="athan-time" colspan="2">-</td>';
            
            // Add empty changes cell for sunrise if we have changes
            if ($showChanges) {
                $output .= '<td class="changes-column"></td>';
            }
        } else {
            // Regular prayer with athan and iqama
            $output .= '<td class="athan-time" style="' . esc_attr($highlight_style) . '">-</td>';
            $output .= '<td class="iqama-time">-</td>';
            
            // Add changes cell if enabled
            if ($showChanges) {
                $output .= '<td class="changes-column"></td>';
            }
        }
        
        $output .= '</tr>';
    }

    $output .= '</tbody>';
    $output .= '</table>';
    
    // Jumuah structure - Fixed to be a properly separated section
    $output .= '<div class="prayer-times-jumuah">';
    $output .= '<table class="jumuah-times-table ' . esc_attr('table-style-' . $tableStyle) . '" style="' . esc_attr($table_style) . '">';
    $output .= '<thead><tr style="' . esc_attr($header_style) . '">';
    $output .= '<th style="' . esc_attr($header_style) . '">' . esc_html__('Khutbah', 'muslim-prayer-times') . '</th>';
    $output .= '<th style="' . esc_attr($header_style) . '">' . esc_html__('Iqama', 'muslim-prayer-times') . '</th>';
    $output .= '</tr></thead>';
    $output .= '<tbody>';
    $output .= '</tbody>';
    $output .= '</table>';
    $output .= '</div>';
    
    $output .= '</div>'; // Close main container div

    return $output;
}

/**
 * Helper function to convert hex color to RGB
 */
function muslprti_hex2rgb($hex) {
    $hex = str_replace('#', '', $hex);
    
    if(strlen($hex) == 3) {
        $r = hexdec(substr($hex, 0, 1).substr($hex, 0, 1));
        $g = hexdec(substr($hex, 1, 1).substr($hex, 1, 1));
        $b = hexdec(substr($hex, 2, 1).substr($hex, 2, 1));
    } else {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    }
    
    return array('r' => $r, 'g' => $g, 'b' => $b);
}
