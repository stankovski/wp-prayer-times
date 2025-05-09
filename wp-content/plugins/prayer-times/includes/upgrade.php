<?php

if (!defined('ABSPATH')) exit;

/**
 * Add sunrise column to the prayer times table
 */
function prayertimes_upgrade_database() {
    global $wpdb;
    $table_name = $wpdb->prefix . PRAYERTIMES_IQAMA_TABLE;
    
    // Check if the column already exists
    $column_exists = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
            DB_NAME,
            $table_name,
            'sunrise'
        )
    );
    
    // Only add the column if it doesn't exist
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN sunrise time DEFAULT NULL AFTER fajr_iqama");
        error_log('Prayer Times Plugin: Sunrise column added to database');
        update_option('prayertimes_db_version', '1.1');
    }
    
    return empty($column_exists) ? true : false;
}