<?php
/**
 * Registers any additional scripts or data needed for the Daily Prayer Times block
 */

if (!defined('ABSPATH')) exit;

/**
 * Enqueues the block editor script and adds plugin URL data
 */
function prayertimes_daily_prayer_times_editor_assets() {
    // Get the block script
    $block_script = plugins_url('block.js', __FILE__);
    
    // Register the script with WordPress
    wp_register_script(
        'prayertimes-daily-prayer-times-block',
        $block_script,
        array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components'),
        filemtime(plugin_dir_path(__FILE__) . 'block.js')
    );
    
    // Add plugin URL data to be used in JavaScript
    wp_localize_script('prayertimes-daily-prayer-times-block', 'wpPrayerTimesData', array(
        'pluginUrl' => plugins_url('', dirname(dirname(__FILE__)))
    ));
}
add_action('enqueue_block_editor_assets', 'prayertimes_daily_prayer_times_editor_assets');

/**
 * Enqueues the carousel script and styles for the frontend
 */
function prayertimes_daily_prayer_times_frontend_assets() {
    // Only enqueue on frontend, not in admin
    if (!is_admin()) {
        wp_enqueue_script(
            'prayertimes-prayer-times-carousel',
            plugins_url('carousel.js', __FILE__),
            array(),
            filemtime(plugin_dir_path(__FILE__) . 'carousel.js'),
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'prayertimes_daily_prayer_times_frontend_assets');
