<?php

if (!defined('ABSPATH')) exit;

/**
 * Shared mappings between WordPress settings and SalahAPI format
 */

/**
 * Get calculation method name mapping (WP -> SalahAPI)
 */
function muslprti_get_method_name_map() {
    return array(
        'JAFARI' => 'jafari',
        'KARACHI' => 'karachi',
        'ISNA' => 'isna',
        'MWL' => 'mwl',
        'MAKKAH' => 'makkah',
        'EGYPT' => 'egypt',
        'TEHRAN' => 'tehran',
        'GULF' => 'gulf',
        'KUWAIT' => 'kuwait',
        'QATAR' => 'qatar',
        'SINGAPORE' => 'singapore',
        'FRANCE' => 'france',
        'TURKEY' => 'turkey',
        'RUSSIA' => 'russia',
        'DUBAI' => 'dubai',
        'CUSTOM' => 'other',
    );
}

/**
 * Get calculation method name mapping (SalahAPI -> WP)
 */
function muslprti_get_method_name_map_reverse() {
    return array_flip(muslprti_get_method_name_map());
}

/**
 * Get ASR calculation method mapping (WP -> SalahAPI)
 */
function muslprti_get_asr_method_map() {
    return array(
        'STANDARD' => 'standard',
        'HANAFI' => 'hanafi',
    );
}

/**
 * Get ASR calculation method mapping (SalahAPI -> WP)
 */
function muslprti_get_asr_method_map_reverse() {
    return array_flip(muslprti_get_asr_method_map());
}

/**
 * Get high latitude adjustment mapping (WP -> SalahAPI)
 */
function muslprti_get_high_lat_map() {
    return array(
        'NONE' => 'none',
        'MOTN' => 'middleOfTheNight',
        'ANGLE' => 'twilightAngle',
        'ONESEVENTH' => 'oneSeventh',
    );
}

/**
 * Get high latitude adjustment mapping (SalahAPI -> WP)
 */
function muslprti_get_high_lat_map_reverse() {
    return array_flip(muslprti_get_high_lat_map());
}

/**
 * Get high latitude adjustment mapping for salah-api library (WP -> Library)
 */
function muslprti_get_high_lat_library_map() {
    return array(
        'NONE' => 'None',
        'MOTN' => 'MiddleOfTheNight',
        'ANGLE' => 'AngleBased',
        'ONESEVENTH' => 'OneSeventh',
    );
}

/**
 * Convert SalahAPI method name to WordPress format
 */
function muslprti_convert_method_from_salahapi($salahapi_method) {
    $reverse_map = muslprti_get_method_name_map_reverse();
    return isset($reverse_map[$salahapi_method]) ? $reverse_map[$salahapi_method] : 'ISNA';
}

/**
 * Convert WordPress method name to SalahAPI format
 */
function muslprti_convert_method_to_salahapi($wp_method) {
    $map = muslprti_get_method_name_map();
    return isset($map[$wp_method]) ? $map[$wp_method] : 'isna';
}

/**
 * Convert SalahAPI ASR method to WordPress format
 */
function muslprti_convert_asr_from_salahapi($salahapi_asr) {
    $reverse_map = muslprti_get_asr_method_map_reverse();
    return isset($reverse_map[$salahapi_asr]) ? strtoupper($reverse_map[$salahapi_asr]) : 'STANDARD';
}

/**
 * Convert WordPress ASR method to SalahAPI format
 */
function muslprti_convert_asr_to_salahapi($wp_asr) {
    $map = muslprti_get_asr_method_map();
    return isset($map[$wp_asr]) ? $map[$wp_asr] : 'standard';
}

/**
 * Convert SalahAPI high latitude adjustment to WordPress format
 */
function muslprti_convert_high_lat_from_salahapi($salahapi_high_lat) {
    $reverse_map = muslprti_get_high_lat_map_reverse();
    return isset($reverse_map[$salahapi_high_lat]) ? strtoupper($reverse_map[$salahapi_high_lat]) : 'MOTN';
}

/**
 * Convert WordPress high latitude adjustment to SalahAPI format
 */
function muslprti_convert_high_lat_to_salahapi($wp_high_lat) {
    $map = muslprti_get_high_lat_map();
    return isset($map[$wp_high_lat]) ? $map[$wp_high_lat] : 'middleOfTheNight';
}

/**
 * Convert WordPress high latitude adjustment to library format
 */
function muslprti_convert_high_lat_to_library($wp_high_lat) {
    $map = muslprti_get_high_lat_library_map();
    return isset($map[$wp_high_lat]) ? $map[$wp_high_lat] : 'MiddleOfTheNight';
}
