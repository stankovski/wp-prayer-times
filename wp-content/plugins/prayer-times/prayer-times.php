<?php
/*
Plugin Name: Prayer Times Shortcode
Description: Adds a shortcode to display prayer times inside posts.
Version: 1.0
Author: Example
*/

if (!defined('ABSPATH')) exit;

// Import the PrayerTimes class
use IslamicNetwork\PrayerTimes\PrayerTimes;

// Define table name as a constant
define('PRAYERTIMES_IQAMA_TABLE', 'prayertimes_iqama_times');

// Define version for database upgrades
define('PRAYERTIMES_DB_VERSION', '1.1');

// Function to check if dependencies are installed
function prayertimes_check_dependencies() {
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        return false;
    }
    
    require_once $autoload;
    if (!class_exists('\IslamicNetwork\PrayerTimes\PrayerTimes')) {
        return false;
    }
    
    return true;
}

// Include the upgrade script
require_once __DIR__ . '/includes/upgrade.php';

// Add this after the plugin activation function or near the end of the file
function prayertimes_check_for_upgrades() {
    $current_db_version = get_option('prayertimes_db_version', '1.0');
    
    // If the database version is outdated, run upgrades
    if (version_compare($current_db_version, PRAYERTIMES_DB_VERSION, '<')) {
        $result = prayertimes_upgrade_database();
        if ($result) {
            // Optionally show an admin notice that upgrade was successful
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>Prayer Times database has been upgraded successfully.</p></div>';
            });
        }
    }
}
add_action('plugins_loaded', 'prayertimes_check_for_upgrades');

// Plugin activation hook
register_activation_hook(__FILE__, 'prayertimes_plugin_activate');

// Setup database tables and initial data
function prayertimes_plugin_activate() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . PRAYERTIMES_IQAMA_TABLE;
    $charset_collate = $wpdb->get_charset_collate();
    
    // SQL to create the iqama times table
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        day date NOT NULL,
        fajr_athan time DEFAULT NULL,
        fajr_iqama time DEFAULT NULL,
        sunrise time DEFAULT NULL,
        dhuhr_athan time DEFAULT NULL,
        dhuhr_iqama time DEFAULT NULL,
        asr_athan time DEFAULT NULL,
        asr_iqama time DEFAULT NULL,
        maghrib_athan time DEFAULT NULL,
        maghrib_iqama time DEFAULT NULL,
        isha_athan time DEFAULT NULL,
        isha_iqama time DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (day)
    ) $charset_collate;";
    
    // Execute the SQL using dbDelta() for safe table creation
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Check if dependencies are installed
    if (!prayertimes_check_dependencies()) {
        add_action('admin_notices', 'prayertimes_missing_dependencies_notice');
    }
}

// Admin notice for missing dependencies
function prayertimes_missing_dependencies_notice() {
    ?>
    <div class="notice notice-error">
        <p>
            <strong>Prayer Times Plugin Error:</strong> Required dependencies are missing. 
            Please run <code>composer install</code> in the plugin directory or contact your administrator.
        </p>
    </div>
    <?php
}

// Display admin notice if dependencies are missing
if (!prayertimes_check_dependencies()) {
    add_action('admin_notices', 'prayertimes_missing_dependencies_notice');
}

// Settings defaults
function prayertimes_get_option($key, $default) {
    $opts = get_option('prayertimes_settings', []);
    return isset($opts[$key]) ? $opts[$key] : $default;
}

/**
 * Register custom block category for Prayer Times blocks
 */
function prayer_times_register_block_category($categories) {
    return array_merge(
        $categories,
        [
            [
                'slug'  => 'prayer-times',
                'title' => __('Prayer Times', 'prayer-times'),
                'icon'  => null, // You can add a custom SVG icon here
            ],
        ]
    );
}
// For WordPress 5.8+
add_filter('block_categories_all', 'prayer_times_register_block_category');
// For backwards compatibility (pre WordPress 5.8)
add_filter('block_categories', 'prayer_times_register_block_category');


// --- Settings Page ---
require_once __DIR__ . '/settings.php';

// --- Blocks ---
require_once __DIR__ . '/blocks/daily-prayer-times/index.php';
require_once __DIR__ . '/blocks/daily-prayer-times/block.php';
require_once __DIR__ . '/blocks/monthly-prayer-times/index.php';
require_once __DIR__ . '/blocks/monthly-prayer-times/block.php';
require_once __DIR__ . '/blocks/live-prayer-times/index.php';
require_once __DIR__ . '/blocks/live-prayer-times/block.php';

// --- Shortcodes ---
require_once __DIR__ . '/includes/shortcodes.php';
