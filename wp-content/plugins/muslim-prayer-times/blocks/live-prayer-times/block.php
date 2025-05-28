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
        filemtime(plugin_dir_path(__FILE__) . 'block.js'),
        false
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
    
    // Register dynamic styles handle that will receive inline CSS
    wp_register_style(
        'muslprti-live-prayer-times-dynamic-style',
        false, // No actual CSS file
        array(),
        '1.0.0' // Version parameter to avoid caching issues
    );
    wp_enqueue_style('muslprti-live-prayer-times-dynamic-style');

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
    $timeFormat = isset($opts['time_format']) ? sanitize_text_field($opts['time_format']) : '12hour';

    // Extract attributes for styling with sanitization
    $className = isset($attributes['className']) ? sanitize_html_class($attributes['className']) : '';
    $align = isset($attributes['align']) ? sanitize_text_field($attributes['align']) : 'center';
    $textColor = isset($attributes['textColor']) ? sanitize_hex_color($attributes['textColor']) : '';
    $backgroundColor = isset($attributes['backgroundColor']) ? sanitize_hex_color($attributes['backgroundColor']) : '';
    $headerColor = isset($attributes['headerColor']) ? sanitize_hex_color($attributes['headerColor']) : '';
    $headerTextColor = isset($attributes['headerTextColor']) ? sanitize_hex_color($attributes['headerTextColor']) : '';
    $highlightColor = isset($attributes['highlightColor']) ? sanitize_hex_color($attributes['highlightColor']) : '';
    $clockColor = isset($attributes['clockColor']) ? sanitize_hex_color($attributes['clockColor']) : '';
    $clockSize = isset($attributes['clockSize']) ? absint($attributes['clockSize']) : 40;
    $showDate = isset($attributes['showDate']) ? (bool)$attributes['showDate'] : true;
    $showHijriDate = isset($attributes['showHijriDate']) ? (bool)$attributes['showHijriDate'] : true;
    $showSunrise = isset($attributes['showSunrise']) ? (bool)$attributes['showSunrise'] : true;
    $tableStyle = isset($attributes['tableStyle']) ? sanitize_text_field($attributes['tableStyle']) : 'default';
    $fontSize = isset($attributes['fontSize']) ? absint($attributes['fontSize']) : 16;
    $showSeconds = isset($attributes['showSeconds']) ? (bool)$attributes['showSeconds'] : true;
    $showChanges = isset($attributes['showChanges']) ? (bool)$attributes['showChanges'] : true;
    $changeColor = isset($attributes['changeColor']) ? sanitize_hex_color($attributes['changeColor']) : '#ff0000';
    $nextPrayerColor = isset($attributes['nextPrayerColor']) ? sanitize_text_field($attributes['nextPrayerColor']) : 'rgba(255, 255, 102, 0.3)';
    
    // Create CSS for dynamic styling
    $block_id = 'muslprti-live-prayer-times-' . uniqid();
    
    // Build CSS rules with proper escaping
    $dynamic_css = "
        #{$block_id} {
            text-align: " . esc_attr($align) . ";
            " . ($fontSize ? "font-size: " . esc_attr($fontSize) . "px;" : "") . "
        }
        
        #{$block_id} .prayer-time-row {
            " . ($backgroundColor ? "background-color: " . esc_attr($backgroundColor) . ";" : "") . "
        }
        
        #{$block_id} table {
            " . ($textColor ? "color: " . esc_attr($textColor) . ";" : "") . "
        }
        
        #{$block_id} th,
        #{$block_id} .prayer-times-header {
            " . ($headerColor ? "background-color: " . esc_attr($headerColor) . ";" : "") . "
            " . ($headerTextColor ? "color: " . esc_attr($headerTextColor) . ";" : "") . "
        }
        
        #{$block_id} .highlight-time {
            " . ($highlightColor ? "color: " . esc_attr($highlightColor) . ";" : "") . "
        }
        
        #{$block_id} .change-header {
            color: " . esc_attr($changeColor) . ";
            " . ($headerColor ? "background-color: " . esc_attr($headerColor) . ";" : "") . "
        }
        
        #{$block_id} .prayer-times-next {
            background-color: " . esc_attr($nextPrayerColor) . ";
        }
    ";
    
    // Add the dynamic CSS to our registered style handle
    wp_add_inline_style('muslprti-live-prayer-times-dynamic-style', $dynamic_css);
    
    // Clock styling
    $clock_style = '';
    if ($clockColor) {
        $clock_style .= "color: " . esc_attr($clockColor) . ";";
    }
    if ($clockSize) {
        $clock_style .= "font-size: " . esc_attr($clockSize) . "px;";
    }
    
    // Define icons for each prayer
    $icons_dir = plugins_url('assets/icons/', dirname(__DIR__));
    $prayer_icons = array(
        'fajr' => esc_url($icons_dir . 'fajr.svg'),
        'sunrise' => esc_url($icons_dir . 'sunrise.svg'),
        'dhuhr' => esc_url($icons_dir . 'dhuhr.svg'),
        'asr' => esc_url($icons_dir . 'asr.svg'),
        'maghrib' => esc_url($icons_dir . 'maghrib.svg'),
        'isha' => esc_url($icons_dir . 'isha.svg'),
        'athan' => esc_url($icons_dir . 'athan.svg'),
        'iqama' => esc_url($icons_dir . 'iqama.svg'),
    );
    
    // Build the HTML output with proper escaping
    $output = '<div id="' . esc_attr($block_id) . '" class="wp-block-prayer-times-live-prayer-times ' . esc_attr($className) . '" 
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
        $output .= '<div class="gregorian-date">' . esc_html__('Loading date...', 'muslim-prayer-times') . '</div>';
        if ($showHijriDate) {
            $output .= '<div class="hijri-date"></div>';
            $output .= '<div class="hijri-date-arabic"></div>';
        }
        $output .= '</div>';
    }
    
    // Prayer times table (initial structure that will be populated via AJAX)
    $output .= '<table class="prayer-times-live-table ' . esc_attr('table-style-' . $tableStyle) . '">';
    
    // Table header
    $output .= '<thead><tr>';
    $output .= '<th></th><th><img src="' . esc_url($prayer_icons['athan']) . '" alt="' . esc_attr__('Athan', 'muslim-prayer-times') . '" class="header-icon">' . esc_html__('Athan', 'muslim-prayer-times') . '</th><th><img src="' . esc_url($prayer_icons['iqama']) . '" alt="' . esc_attr__('Iqama', 'muslim-prayer-times') . '" class="header-icon">' . esc_html__('Iqama', 'muslim-prayer-times') . '</th>';
    
    // Add the changes column header if enabled
    if ($showChanges) {
        $output .= '<th class="changes-column"></th>';
    }
    
    $output .= '</tr></thead>';
    
    // Table body - Empty rows that will be filled by JavaScript
    $output .= '<tbody>';
    
    // Prayer rows - Create the structure with icons, but no times
    $prayer_map = array(
        'fajr' => array('name' => esc_html__('Fajr', 'muslim-prayer-times')),
        'sunrise' => array('name' => esc_html__('Sunrise', 'muslim-prayer-times')),
        'dhuhr' => array('name' => esc_html__('Dhuhr', 'muslim-prayer-times')),
        'asr' => array('name' => esc_html__('Asr', 'muslim-prayer-times')),
        'maghrib' => array('name' => esc_html__('Maghrib', 'muslim-prayer-times')),
        'isha' => array('name' => esc_html__('Isha', 'muslim-prayer-times'))
    );
    
    foreach ($prayer_map as $prayer_key => $prayer_data) {
        // Skip sunrise if not showing
        if ($prayer_key === 'sunrise' && !$showSunrise) {
            continue;
        }
        
        $output .= '<tr' . ($prayer_key === 'sunrise' ? ' class="sunrise-row"' : '') . '>';
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
            $output .= '<td class="athan-time">-</td>';
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
    $output .= '<table class="jumuah-times-table ' . esc_attr('table-style-' . $tableStyle) . '">';
    $output .= '<thead><tr class="prayer-times-header">';
    $output .= '<th>' . esc_html__('Khutbah', 'muslim-prayer-times') . '</th>';
    $output .= '<th>' . esc_html__('Iqama', 'muslim-prayer-times') . '</th>';
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
    $hex = sanitize_hex_color($hex);
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
    
    return array('r' => absint($r), 'g' => absint($g), 'b' => absint($b));
}
