<?php
/**
 * Registers any additional scripts or data needed for the Monthly Prayer Times block
 */

if (!defined('ABSPATH')) exit;

/**
 * Enqueues the block editor script and adds plugin URL data
 */
function muslprti_monthly_prayer_times_editor_assets() {
    // Get the block script
    $block_script = plugins_url('block.js', __FILE__);
    
    // Register the script with WordPress
    wp_register_script(
        'muslprti-monthly-prayer-times-block',
        $block_script,
        array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components'),
        filemtime(plugin_dir_path(__FILE__) . 'block.js')
    );
    
    // Add plugin URL data to be used in JavaScript
    wp_localize_script('muslprti-monthly-prayer-times-block', 'wpPrayerTimesData', array(
        'pluginUrl' => plugins_url('', dirname(dirname(__FILE__)))
    ));
}
add_action('enqueue_block_editor_assets', 'muslprti_monthly_prayer_times_editor_assets');

/**
 * Enqueues frontend scripts for the block
 */
function muslprti_monthly_prayer_times_frontend_assets() {
    wp_register_script(
        'muslprti-monthly-prayer-times-frontend',
        plugins_url('frontend.js', __FILE__),
        array('jquery'),
        filemtime(plugin_dir_path(__FILE__) . 'frontend.js'),
        true
    );
    
    wp_localize_script('muslprti-monthly-prayer-times-frontend', 'muslprti_monthly_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('muslprti_monthly_prayer_times_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'muslprti_monthly_prayer_times_frontend_assets');
