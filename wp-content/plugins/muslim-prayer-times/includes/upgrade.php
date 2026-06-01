<?php

if (!defined('ABSPATH')) exit;

/**
 * Determine whether a column exists on the iqama times table.
 *
 * @param string $table_name Fully-qualified table name.
 * @param string $column_name Column to look for.
 * @return bool True when the column already exists.
 */
function muslprti_column_exists($table_name, $column_name) {
    global $wpdb;

    $column_exists = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
            DB_NAME,
            $table_name,
            $column_name
        )
    );

    return !empty($column_exists);
}

/**
 * Run idempotent schema migrations for the prayer times table.
 *
 * Each migration checks INFORMATION_SCHEMA before issuing an ALTER TABLE so the
 * function is safe to run repeatedly.
 *
 * @return bool True when at least one migration changed the schema.
 */
function muslprti_upgrade_database() {
    global $wpdb;
    $table_name = $wpdb->prefix . MUSLPRTI_IQAMA_TABLE;
    $changed    = false;

    // v1.1 — sunrise column.
    if (!muslprti_column_exists($table_name, 'sunrise')) {
        // Table name must be escaped separately as wpdb->prepare() doesn't handle identifiers.
        $wpdb->query("ALTER TABLE " . esc_sql($table_name) . " ADD COLUMN sunrise time DEFAULT NULL AFTER fajr_iqama");
        $changed = true;
    }

    // v1.4 — optional Asr athan columns (SalahAPI 1.1: asr_athan_standard, asr_athan_hanafi).
    if (!muslprti_column_exists($table_name, 'asr_athan_standard')) {
        $wpdb->query("ALTER TABLE " . esc_sql($table_name) . " ADD COLUMN asr_athan_standard time DEFAULT NULL AFTER asr_iqama");
        $changed = true;
    }

    if (!muslprti_column_exists($table_name, 'asr_athan_hanafi')) {
        $wpdb->query("ALTER TABLE " . esc_sql($table_name) . " ADD COLUMN asr_athan_hanafi time DEFAULT NULL AFTER asr_athan_standard");
        $changed = true;
    }

    // Record the schema version we have migrated to.
    update_option('muslprti_db_version', MUSLPRTI_DB_VERSION);

    return $changed;
}