<?php
/**
 * Registers any additional scripts or data needed for the Live Prayer Times block
 */

if (!defined('ABSPATH')) exit;

/**
 * Enqueues the block editor script and adds plugin URL data
 */
function prayertimes_live_prayer_times_editor_assets() {
    // Get the block script
    $block_script = plugins_url('block.js', __FILE__);
    
    // Register the script with WordPress
    wp_register_script(
        'prayertimes-live-prayer-times-block',
        $block_script,
        array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components'),
        filemtime(plugin_dir_path(__FILE__) . 'block.js')
    );
    
    // Add plugin URL data to be used in JavaScript
    wp_localize_script('prayertimes-live-prayer-times-block', 'wpPrayerTimesData', array(
        'pluginUrl' => plugins_url('', dirname(dirname(__FILE__)))
    ));
}
add_action('enqueue_block_editor_assets', 'prayertimes_live_prayer_times_editor_assets');

/**
 * Enqueues frontend scripts for the block
 */
function prayertimes_live_prayer_times_frontend_assets() {
    // Only enqueue on frontend, not in admin
    if (!is_admin()) {
        wp_enqueue_script(
            'prayertimes-live-prayer-times-frontend',
            plugins_url('frontend.js', __FILE__),
            array('jquery'),
            filemtime(plugin_dir_path(__FILE__) . 'frontend.js'),
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'prayertimes_live_prayer_times_frontend_assets');
