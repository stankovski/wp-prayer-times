<?php
/**
 * Shortcode wrapper functions for Prayer Times blocks
 */

if (!defined('ABSPATH')) exit;

/**
 * Convert shortcode attributes to block attributes
 * 
 * @param array $atts Shortcode attributes
 * @param array $defaults Default values
 * @return array Block-compatible attributes
 */
function muslprti_convert_shortcode_atts_to_block_atts($atts, $defaults) {
    // Parse shortcode attributes
    $atts = shortcode_atts($defaults, $atts);
    
    // Convert boolean values from strings to actual booleans
    foreach ($atts as $key => $value) {
        if ($value === 'true') {
            $atts[$key] = true;
        } elseif ($value === 'false') {
            $atts[$key] = false;
        } elseif (is_numeric($value) && !strpos($value, '.')) {
            $atts[$key] = intval($value);
        } elseif (is_numeric($value)) {
            $atts[$key] = floatval($value);
        }
    }
    
    return $atts;
}

/**
 * Monthly Prayer Times shortcode
 */
function muslprti_monthly_prayer_times_shortcode($atts) {
    // Default attributes matching the block's defaults
    $defaults = array(
        'className' => '',
        'align' => 'center',
        'textColor' => '',
        'backgroundColor' => '',
        'headerColor' => '',
        'tableStyle' => 'default',
        'fontSize' => 16,
        'showSunrise' => 'true',
        'showIqama' => 'true',
        'highlightToday' => 'true',
    );
    
    // Convert attributes format
    $block_atts = muslprti_convert_shortcode_atts_to_block_atts($atts, $defaults);
    
    // Use the block's render function
    return muslprti_render_monthly_prayer_times_block($block_atts);
}
add_shortcode('muslprti_monthly_prayer_times', 'muslprti_monthly_prayer_times_shortcode');

/**
 * Live Prayer Times shortcode
 */
function muslprti_live_prayer_times_shortcode($atts) {
    // Default attributes matching the block's defaults
    $defaults = array(
        'className' => '',
        'align' => 'center',
        'textColor' => '',
        'backgroundColor' => '',
        'headerColor' => '',
        'clockColor' => '',
        'clockSize' => 40,
        'showDate' => 'true',
        'showHijriDate' => 'true', 
        'showSunrise' => 'true',
        'tableStyle' => 'default',
        'fontSize' => 16,
        'showSeconds' => 'true',
        'timeFormat' => '12hour',
    );
    
    // Convert attributes format
    $block_atts = muslprti_convert_shortcode_atts_to_block_atts($atts, $defaults);
    
    // Enqueue the frontend script
    wp_enqueue_script('muslprti-live-prayer-times-frontend');
    
    // Use the block's render function
    return muslprti_render_live_prayer_times_block($block_atts);
}
add_shortcode('muslprti_live_prayer_times', 'muslprti_live_prayer_times_shortcode');

/**
 * Daily Prayer Times shortcode
 */
function muslprti_daily_prayer_times_shortcode($atts) {
    // Default attributes matching the block's defaults
    $defaults = array(
        'className' => '',
        'align' => 'center',
        'textColor' => '',
        'backgroundColor' => '',
        'headerColor' => '',
        'showDate' => 'true',
        'showHijriDate' => 'true',
        'showSunrise' => 'true',
        'tableStyle' => 'default',
        'fontSize' => 16,
        'showArrows' => 'true',
    );
    
    // Convert attributes format
    $block_atts = muslprti_convert_shortcode_atts_to_block_atts($atts, $defaults);
    
    // Enqueue the carousel script
    wp_enqueue_script('muslprti-prayer-times-carousel');
    
    // Use the block's render function
    return muslprti_render_daily_prayer_times_block($block_atts);
}
add_shortcode('muslprti_daily_prayer_times', 'muslprti_daily_prayer_times_shortcode');

/**
 * Current Date shortcode
 * 
 * @param array $atts Shortcode attributes
 * @return string Current date in specified format
 */
function muslprti_current_date_shortcode($atts) {
    // Default attributes
    $defaults = array(
        'format' => 'F j, Y', // Default format: January 1, 2026
    );
    
    $atts = shortcode_atts($defaults, $atts);
    
    // Get current date with plugin's timezone
    return muslprti_date($atts['format']);
}
add_shortcode('muslprti_current_date', 'muslprti_current_date_shortcode');

/**
 * Current Hijri Date shortcode
 * 
 * @param array $atts Shortcode attributes
 * @return string Current date in Hijri calendar
 */
function muslprti_current_hijri_date_shortcode($atts) {
    // Default attributes
    $defaults = array(
        'language' => 'en', // 'en' or 'ar'
    );
    
    $atts = shortcode_atts($defaults, $atts);
    
    // Load Hijri date converter
    require_once plugin_dir_path(__FILE__) . 'hijri-date-converter.php';
    
    // Get current date
    $today = muslprti_date('Y-m-d');
    
    // Get Hijri offset from settings
    $opts = get_option('muslprti_settings', []);
    $hijri_offset = isset($opts['hijri_offset']) ? $opts['hijri_offset'] : 0;
    
    // Convert to Hijri date
    return muslprti_convert_to_hijri($today, true, $atts['language'], $hijri_offset);
}
add_shortcode('muslprti_current_hijri_date', 'muslprti_current_hijri_date_shortcode');
