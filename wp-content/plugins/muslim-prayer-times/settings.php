<?php

if (!defined('ABSPATH')) exit;

use IslamicNetwork\PrayerTimes\PrayerTimes2;

// Include the admin AJAX handlers
require_once plugin_dir_path(__FILE__) . 'settings-ajax.php';

function muslprti_settings_menu() {
    add_options_page(
        'Muslim Prayer Times Settings',
        'Muslim Prayer Times',
        'manage_options',
        'muslprti-settings',
        'muslprti_settings_page'
    );
}
add_action('admin_menu', 'muslprti_settings_menu');

// Function to get list of supported PHP timezones
function muslprti_get_timezone_list() {
    $timezones = DateTimeZone::listIdentifiers(DateTimeZone::ALL);
    $timezone_options = array();
    
    foreach ($timezones as $timezone) {
        // Group timezones by region for better organization
        $parts = explode('/', $timezone);
        $region = $parts[0];
        
        if (!isset($timezone_options[$region])) {
            $timezone_options[$region] = array();
        }
        
        $timezone_options[$region][] = $timezone;
    }
    
    return $timezone_options;
}

// Register scripts and styles for the admin page
function muslprti_admin_scripts($hook) {
    if ($hook != 'settings_page_muslprti-settings') return;
    
    // Register and enqueue admin CSS
    wp_enqueue_style('muslprti-admin-styles', plugins_url('assets/css/admin-styles.css', __FILE__), array(), filemtime(plugin_dir_path(__FILE__) . 'assets/css/admin-styles.css'));
    
    // Register and enqueue main admin script
    wp_enqueue_script('muslprti-admin', plugins_url('js/admin.js', __FILE__), array('jquery'), '1.0.1', true);
    wp_localize_script('muslprti-admin', 'muslprtiAdmin', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'export_nonce' => wp_create_nonce('muslprti_generate_nonce'),
        'export_db_nonce' => wp_create_nonce('muslprti_export_db_nonce'), // New nonce
        'geocode_nonce' => wp_create_nonce('muslprti_geocode_nonce'),
        'import_preview_nonce' => wp_create_nonce('muslprti_import_preview_nonce'),
        'import_nonce' => wp_create_nonce('muslprti_import_nonce'),
        'hijri_preview_nonce' => wp_create_nonce('muslprti_hijri_preview_nonce') // Add new nonce for Hijri preview
    ));
    
    // Date format preview script
    $date_format_script = "
        jQuery(document).ready(function($) {
            // Update date format preview when format changes
            $('#muslprti_date_format').change(function() {
                var format = $(this).val();
                var today = new Date();
                var preview = '';
                
                switch(format) {
                    case 'Y-m-d':
                        preview = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');
                        break;
                    case 'n/j/y':
                        preview = (today.getMonth() + 1) + '/' + today.getDate() + '/' + String(today.getFullYear()).slice(-2);
                        break;
                    case 'n/j/Y':
                        preview = (today.getMonth() + 1) + '/' + today.getDate() + '/' + today.getFullYear();
                        break;
                    case 'm/d/y':
                        preview = String(today.getMonth() + 1).padStart(2, '0') + '/' + String(today.getDate()).padStart(2, '0') + '/' + String(today.getFullYear()).slice(-2);
                        break;
                    case 'm/d/Y':
                        preview = String(today.getMonth() + 1).padStart(2, '0') + '/' + String(today.getDate()).padStart(2, '0') + '/' + today.getFullYear();
                        break;
                    case 'd-m-Y':
                        preview = String(today.getDate()).padStart(2, '0') + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + today.getFullYear();
                        break;
                    case 'd/m/Y':
                        preview = String(today.getDate()).padStart(2, '0') + '/' + String(today.getMonth() + 1).padStart(2, '0') + '/' + today.getFullYear();
                        break;
                    case 'd.m.Y':
                        preview = String(today.getDate()).padStart(2, '0') + '.' + String(today.getMonth() + 1).padStart(2, '0') + '.' + today.getFullYear();
                        break;
                    case 'd/m/y':
                        preview = String(today.getDate()).padStart(2, '0') + '/' + String(today.getMonth() + 1).padStart(2, '0') + '/' + String(today.getFullYear()).slice(-2);
                        break;
                    case 'd-m-y':
                        preview = String(today.getDate()).padStart(2, '0') + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getFullYear()).slice(-2);
                        break;
                    case 'd.m.y':
                        preview = String(today.getDate()).padStart(2, '0') + '.' + String(today.getMonth() + 1).padStart(2, '0') + '.' + String(today.getFullYear()).slice(-2);
                        break;
                    case 'Y/m/d':
                        preview = today.getFullYear() + '/' + String(today.getMonth() + 1).padStart(2, '0') + '/' + String(today.getDate()).padStart(2, '0');
                        break;
                    case 'Y.m.d':
                        preview = today.getFullYear() + '.' + String(today.getMonth() + 1).padStart(2, '0') + '.' + String(today.getDate()).padStart(2, '0');
                        break;
                    case 'j/n/Y':
                        preview = today.getDate() + '/' + (today.getMonth() + 1) + '/' + today.getFullYear();
                        break;
                    case 'j-n-Y':
                        preview = today.getDate() + '-' + (today.getMonth() + 1) + '-' + today.getFullYear();
                        break;
                    case 'j.n.Y':
                        preview = today.getDate() + '.' + (today.getMonth() + 1) + '.' + today.getFullYear();
                        break;
                    default:
                        preview = 'Unknown format';
                }
                
                $('#date-format-preview').text('Today: ' + preview);
            });
            
            // Trigger initial preview
            $('#muslprti_date_format').trigger('change');
        });
    ";
    wp_add_inline_script('muslprti-admin', $date_format_script);
    
    // Hijri date preview script
    $hijri_script = "
        jQuery(document).ready(function($) {
            // Update Hijri date preview when offset changes
            $('#muslprti_hijri_offset').change(function() {
                var offset = $(this).val();
                $('#hijri-date-preview').html('Loading...');
                
                $.ajax({
                    url: muslprtiAdmin.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'muslprti_preview_hijri',
                        offset: offset,
                        nonce: muslprtiAdmin.hijri_preview_nonce
                    },
                    success: function(response) {
                        console.log('Hijri preview response:', response); // Debug log
                        if (response.success && response.data && response.data.hijri_date) {
                            $('#hijri-date-preview').html('Today: ' + response.data.hijri_date);
                        } else {
                            console.log('Invalid response structure:', response);
                            $('#hijri-date-preview').html('Error: Invalid response');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.log('Hijri preview error:', error, xhr.responseText); // Debug log
                        $('#hijri-date-preview').html('Error connecting to server: ' + error);
                    }
                });
            });
            
            // Trigger initial load to set the current value
            $('#muslprti_hijri_offset').trigger('change');
        });
    ";
    wp_add_inline_script('muslprti-admin', $hijri_script);
    
    // Iqama rules visibility script
    $iqama_rules_script = "
        jQuery(document).ready(function($) {
            // Handle conditional fields visibility
            $('.rule-radio').change(function() {
                var name = $(this).attr('name');
                var value = $(this).val();
                
                // Hide all related containers first
                $('div[data-parent=\"' + name + '\"]').hide();
                
                // Show the relevant container
                $('div[data-parent=\"' + name + '\"][data-show-for=\"' + value + '\"]').show();
            });
            
            // Initialize visibility
            $('.rule-radio:checked').each(function() {
                var name = $(this).attr('name');
                var value = $(this).val();
                
                $('div[data-parent=\"' + name + '\"]').hide();
                $('div[data-parent=\"' + name + '\"][data-show-for=\"' + value + '\"]').show();
            });
        });
    ";
    wp_add_inline_script('muslprti-admin', $iqama_rules_script);
    
    // Date range dropdown script
    $date_range_script = "
        jQuery(document).ready(function($) {
            // Initialize on page load
            if ($('#muslprti_period').val() === 'custom') {
                $('#custom_date_range').show();
            }
            
            // Set current dates as defaults for custom range
            var today = new Date();
            var nextMonth = new Date();
            nextMonth.setMonth(today.getMonth() + 1);
            
            $('#muslprti_start_date').val(today.toISOString().split('T')[0]);
            $('#muslprti_end_date').val(nextMonth.toISOString().split('T')[0]);
            
            // Handle changes
            $('#muslprti_period').change(function() {
                if ($(this).val() === 'custom') {
                    $('#custom_date_range').show();
                } else {
                    $('#custom_date_range').hide();
                }
            });
        });
    ";
    wp_add_inline_script('muslprti-admin', $date_range_script);
    
    // Add CSS for accordion
    wp_add_inline_style('admin-bar', '
        .muslprti-accordion {
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            margin-bottom: 20px;
            background: #fff;
        }
        .muslprti-accordion-header {
            padding: 15px;
            background: #f5f5f5;
            border-bottom: 1px solid #ccd0d4;
            cursor: pointer;
            font-weight: 600;
            position: relative;
        }
        .muslprti-accordion-header:hover {
            background: #f1f1f1;
        }
        .muslprti-accordion-header::after {
            content: "\\f140";
            font-family: dashicons;
            position: absolute;
            right: 15px;
            color: #777;
        }
        .muslprti-accordion-header.active::after {
            content: "\\f142";
        }
        .muslprti-accordion-content {
            padding: 15px;
            display: none;
        }
        .muslprti-accordion-content.active {
            display: block;
        }
        .iqama-rule-section {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .iqama-rule-section h3 {
            margin-top: 0;
        }
        .iqama-rule-option {
            margin-bottom: 10px;
        }
        .conditional-field {
            margin-left: 25px;
            padding: 10px;
            background: #f9f9f9;
            border-left: 3px solid #ddd;
            display: none;
        }
        .conditional-field.active {
            display: block;
        }
        .hijri-preview {
            display: inline-block;
            margin-left: 15px;
            padding: 5px 10px;
            background: #f8f8f8;
            border-radius: 4px;
            border: 1px solid #ddd;
            font-size: 14px;
        }
        .prayer-times-header {
            background-color: #fff;
            padding: 20px;
            border-radius: 4px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .prayer-times-header h1 {
            margin-top: 0;
        }
        .prayer-times-header p {
            font-size: 14px;
            line-height: 1.6;
        }
        .prayer-times-steps {
            background-color: #f9f9f9;
            border-left: 4px solid #0073aa;
            padding: 15px 20px;
            margin-bottom: 20px;
        }
        .prayer-times-steps ol {
            margin-left: 20px;
        }
        .prayer-times-steps li {
            margin-bottom: 10px;
        }
        .available-blocks {
            background-color: #f5f5f5;
            border: 1px solid #e0e0e0;
            padding: 15px 20px;
            margin-top: 15px;
        }
        .available-blocks h3 {
            margin-top: 0;
        }
        .github-link {
            font-weight: 600;
            text-decoration: none;
        }
    ');
}
add_action('admin_enqueue_scripts', 'muslprti_admin_scripts');

// All AJAX handlers have been moved to prayer-times-admin-ajax.php

function muslprti_settings_page() {
    if (!current_user_can('manage_options')) return;

    // Get existing settings
    $opts = get_option('muslprti_settings', []);
    
    // Handle general settings form submission
    if (isset($_POST['muslprti_general_settings_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['muslprti_general_settings_nonce'])), 'muslprti_save_general_settings')) {
        $opts['lat'] = isset($_POST['muslprti_lat']) ? floatval(wp_unslash($_POST['muslprti_lat'])) : 47.7623;
        $opts['lng'] = isset($_POST['muslprti_lng']) ? floatval(wp_unslash($_POST['muslprti_lng'])) : -122.2054;
        $opts['tz'] = isset($_POST['muslprti_tz']) ? sanitize_text_field(wp_unslash($_POST['muslprti_tz'])) : 'America/Los_Angeles';
        $opts['method'] = isset($_POST['muslprti_method']) ? sanitize_text_field(wp_unslash($_POST['muslprti_method'])) : 'ISNA';
        $opts['asr_calc'] = isset($_POST['muslprti_asr_calc']) ? sanitize_text_field(wp_unslash($_POST['muslprti_asr_calc'])) : 'STANDARD';
        
        // Save Hijri day offset
        $opts['hijri_offset'] = isset($_POST['muslprti_hijri_offset']) ? intval(wp_unslash($_POST['muslprti_hijri_offset'])) : 0;
        
        // Save time format setting
        $opts['time_format'] = isset($_POST['muslprti_time_format']) ? sanitize_text_field(wp_unslash($_POST['muslprti_time_format'])) : '12hour';
        
        // Save Jumuah times
        $opts['jumuah1'] = isset($_POST['muslprti_jumuah1']) ? sanitize_text_field(wp_unslash($_POST['muslprti_jumuah1'])) : '12:30';
        $opts['jumuah2'] = isset($_POST['muslprti_jumuah2']) ? sanitize_text_field(wp_unslash($_POST['muslprti_jumuah2'])) : '13:30';
        $opts['jumuah3'] = isset($_POST['muslprti_jumuah3']) ? sanitize_text_field(wp_unslash($_POST['muslprti_jumuah3'])) : '';
        
        // Save Jumuah custom names
        $opts['jumuah1_name'] = isset($_POST['muslprti_jumuah1_name']) ? sanitize_text_field(wp_unslash($_POST['muslprti_jumuah1_name'])) : 'Jumuah 1';
        $opts['jumuah2_name'] = isset($_POST['muslprti_jumuah2_name']) ? sanitize_text_field(wp_unslash($_POST['muslprti_jumuah2_name'])) : 'Jumuah 2';
        $opts['jumuah3_name'] = isset($_POST['muslprti_jumuah3_name']) ? sanitize_text_field(wp_unslash($_POST['muslprti_jumuah3_name'])) : 'Jumuah 3';
        
        update_option('muslprti_settings', $opts);
        echo '<div class="updated"><p>General Prayer Times settings saved.</p></div>';
    }
    
    // Handle Iqama rules form submission
    if (isset($_POST['muslprti_iqama_settings_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['muslprti_iqama_settings_nonce'])), 'muslprti_save_iqama_settings')) {
        // Save Iqama rules
        $opts['iqama_frequency'] = isset($_POST['muslprti_iqama_frequency']) ? sanitize_text_field(wp_unslash($_POST['muslprti_iqama_frequency'])) : 'weekly';
        
        // Fajr rules
        $opts['fajr_rule'] = isset($_POST['muslprti_fajr_rule']) ? sanitize_text_field(wp_unslash($_POST['muslprti_fajr_rule'])) : 'after_athan';
        $opts['fajr_minutes_after'] = isset($_POST['muslprti_fajr_minutes_after']) ? intval(wp_unslash($_POST['muslprti_fajr_minutes_after'])) : 20;
        $opts['fajr_minutes_before_shuruq'] = isset($_POST['muslprti_fajr_minutes_before_shuruq']) ? intval(wp_unslash($_POST['muslprti_fajr_minutes_before_shuruq'])) : 45;
        $opts['fajr_daily_change'] = isset($_POST['muslprti_fajr_daily_change']) ? 1 : 0;
        $opts['fajr_rounding'] = isset($_POST['muslprti_fajr_rounding']) ? intval(wp_unslash($_POST['muslprti_fajr_rounding'])) : 1;
        $opts['fajr_min_time'] = isset($_POST['muslprti_fajr_min_time']) ? sanitize_text_field(wp_unslash($_POST['muslprti_fajr_min_time'])) : '05:00';
        $opts['fajr_max_time'] = isset($_POST['muslprti_fajr_max_time']) ? sanitize_text_field(wp_unslash($_POST['muslprti_fajr_max_time'])) : '07:00';
        
        // Dhuhr rules
        $opts['dhuhr_rule'] = isset($_POST['muslprti_dhuhr_rule']) ? sanitize_text_field(wp_unslash($_POST['muslprti_dhuhr_rule'])) : 'after_athan';
        $opts['dhuhr_minutes_after'] = isset($_POST['muslprti_dhuhr_minutes_after']) ? intval(wp_unslash($_POST['muslprti_dhuhr_minutes_after'])) : 15;
        $opts['dhuhr_fixed_standard'] = isset($_POST['muslprti_dhuhr_fixed_standard']) ? sanitize_text_field(wp_unslash($_POST['muslprti_dhuhr_fixed_standard'])) : '13:30';
        $opts['dhuhr_fixed_dst'] = isset($_POST['muslprti_dhuhr_fixed_dst']) ? sanitize_text_field(wp_unslash($_POST['muslprti_dhuhr_fixed_dst'])) : '13:30';
        $opts['dhuhr_daily_change'] = isset($_POST['muslprti_dhuhr_daily_change']) ? 1 : 0;
        $opts['dhuhr_rounding'] = isset($_POST['muslprti_dhuhr_rounding']) ? intval(wp_unslash($_POST['muslprti_dhuhr_rounding'])) : 1;
        
        // Asr rules
        $opts['asr_rule'] = isset($_POST['muslprti_asr_rule']) ? sanitize_text_field(wp_unslash($_POST['muslprti_asr_rule'])) : 'after_athan';
        $opts['asr_minutes_after'] = isset($_POST['muslprti_asr_minutes_after']) ? intval(wp_unslash($_POST['muslprti_asr_minutes_after'])) : 15;
        $opts['asr_fixed_standard'] = isset($_POST['muslprti_asr_fixed_standard']) ? sanitize_text_field(wp_unslash($_POST['muslprti_asr_fixed_standard'])) : '16:30';
        $opts['asr_fixed_dst'] = isset($_POST['muslprti_asr_fixed_dst']) ? sanitize_text_field(wp_unslash($_POST['muslprti_asr_fixed_dst'])) : '16:30';
        $opts['asr_daily_change'] = isset($_POST['muslprti_asr_daily_change']) ? 1 : 0;
        $opts['asr_rounding'] = isset($_POST['muslprti_asr_rounding']) ? intval(wp_unslash($_POST['muslprti_asr_rounding'])) : 1;
        
        // Maghrib rules
        $opts['maghrib_minutes_after'] = isset($_POST['muslprti_maghrib_minutes_after']) ? intval(wp_unslash($_POST['muslprti_maghrib_minutes_after'])) : 5;
        $opts['maghrib_daily_change'] = isset($_POST['muslprti_maghrib_daily_change']) ? 1 : 0;
        $opts['maghrib_rounding'] = isset($_POST['muslprti_maghrib_rounding']) ? intval(wp_unslash($_POST['muslprti_maghrib_rounding'])) : 1;
        
        // Isha rules
        $opts['isha_rule'] = isset($_POST['muslprti_isha_rule']) ? sanitize_text_field(wp_unslash($_POST['muslprti_isha_rule'])) : 'after_athan';
        $opts['isha_minutes_after'] = isset($_POST['muslprti_isha_minutes_after']) ? intval(wp_unslash($_POST['muslprti_isha_minutes_after'])) : 15;
        $opts['isha_min_time'] = isset($_POST['muslprti_isha_min_time']) ? sanitize_text_field(wp_unslash($_POST['muslprti_isha_min_time'])) : '19:30';
        $opts['isha_max_time'] = isset($_POST['muslprti_isha_max_time']) ? sanitize_text_field(wp_unslash($_POST['muslprti_isha_max_time'])) : '22:00';
        $opts['isha_daily_change'] = isset($_POST['muslprti_isha_daily_change']) ? 1 : 0;
        $opts['isha_rounding'] = isset($_POST['muslprti_isha_rounding']) ? intval(wp_unslash($_POST['muslprti_isha_rounding'])) : 1;
        
        update_option('muslprti_settings', $opts);
        echo '<div class="updated"><p>Iqama rules settings saved.</p></div>';
    }
    
    // Load all settings after possible update
    $opts = get_option('muslprti_settings', []);
    $lat = isset($opts['lat']) ? $opts['lat'] : 47.7623;
    $lng = isset($opts['lng']) ? $opts['lng'] : -122.2054;
    $tz = muslprti_get_timezone();
    $method = isset($opts['method']) ? $opts['method'] : 'ISNA';
    $asr_calc = isset($opts['asr_calc']) ? $opts['asr_calc'] : 'STANDARD';
    $hijri_offset = isset($opts['hijri_offset']) ? $opts['hijri_offset'] : 0;
    $time_format = isset($opts['time_format']) ? $opts['time_format'] : '12hour';

    // Jumuah prayer times defaults
    $jumuah1 = isset($opts['jumuah1']) ? $opts['jumuah1'] : '12:30';
    $jumuah2 = isset($opts['jumuah2']) ? $opts['jumuah2'] : '13:30';
    $jumuah3 = isset($opts['jumuah3']) ? $opts['jumuah3'] : ''; // Empty by default for the third prayer
    
    // Jumuah custom names
    $jumuah1_name = isset($opts['jumuah1_name']) ? $opts['jumuah1_name'] : 'Jumuah 1';
    $jumuah2_name = isset($opts['jumuah2_name']) ? $opts['jumuah2_name'] : 'Jumuah 2';
    $jumuah3_name = isset($opts['jumuah3_name']) ? $opts['jumuah3_name'] : 'Jumuah 3';

    // Get timezone list
    $timezone_options = muslprti_get_timezone_list();
    
    // Common calculation methods
    $methods = [
        'JAFARI'      => 'Jafari',
        'KARACHI'     => 'Karachi',
        'ISNA'        => 'ISNA (North America)',
        'MWL'         => 'MWL (Muslim World League)',
        'MAKKAH'      => 'Umm Al-Qura, Makkah',
        'EGYPT'       => 'Egyptian General Authority',
        'TEHRAN'      => 'Tehran',
        'GULF'        => 'Gulf Region',
        'KUWAIT'      => 'Kuwait',
        'QATAR'       => 'Qatar',
        'SINGAPORE'   => 'Singapore',
        'FRANCE'      => 'France',
        'TURKEY'      => 'Turkey',
        'RUSSIA'      => 'Russia',
        'MOONSIGHTING'=> 'Moonsighting Committee',
        'DUBAI'       => 'Dubai (UAE)',
        'JAKIM'       => 'JAKIM (Malaysia)',
        'TUNISIA'     => 'Tunisia',
        'ALGERIA'     => 'Algeria',
        'KEMENAG'     => 'KEMENAG (Indonesia)',
        'MOROCCO'     => 'Morocco',
        'PORTUGAL'    => 'Portugal',
        'JORDAN'      => 'Jordan',
        'CUSTOM'      => 'Custom',
    ];
    
    // Iqama rules defaults
    $iqama_frequency = isset($opts['iqama_frequency']) ? $opts['iqama_frequency'] : 'weekly';
    
    // Fajr defaults
    $fajr_rule = isset($opts['fajr_rule']) ? $opts['fajr_rule'] : 'after_athan';
    $fajr_minutes_after = isset($opts['fajr_minutes_after']) ? $opts['fajr_minutes_after'] : 20;
    $fajr_minutes_before_shuruq = isset($opts['fajr_minutes_before_shuruq']) ? $opts['fajr_minutes_before_shuruq'] : 45;
    $fajr_daily_change = isset($opts['fajr_daily_change']) ? $opts['fajr_daily_change'] : 0;
    $fajr_rounding = isset($opts['fajr_rounding']) ? $opts['fajr_rounding'] : 1;
    $fajr_min_time = isset($opts['fajr_min_time']) ? $opts['fajr_min_time'] : '05:00';
    $fajr_max_time = isset($opts['fajr_max_time']) ? $opts['fajr_max_time'] : '07:00';
    
    // Dhuhr defaults
    $dhuhr_rule = isset($opts['dhuhr_rule']) ? $opts['dhuhr_rule'] : 'after_athan';
    $dhuhr_minutes_after = isset($opts['dhuhr_minutes_after']) ? $opts['dhuhr_minutes_after'] : 15;
    $dhuhr_fixed_standard = isset($opts['dhuhr_fixed_standard']) ? $opts['dhuhr_fixed_standard'] : '13:30';
    $dhuhr_fixed_dst = isset($opts['dhuhr_fixed_dst']) ? $opts['dhuhr_fixed_dst'] : '13:30';
    $dhuhr_daily_change = isset($opts['dhuhr_daily_change']) ? $opts['dhuhr_daily_change'] : 0;
    $dhuhr_rounding = isset($opts['dhuhr_rounding']) ? $opts['dhuhr_rounding'] : 1;
    
    // Asr defaults
    $asr_rule = isset($opts['asr_rule']) ? $opts['asr_rule'] : 'after_athan';
    $asr_minutes_after = isset($opts['asr_minutes_after']) ? $opts['asr_minutes_after'] : 15;
    $asr_fixed_standard = isset($opts['asr_fixed_standard']) ? $opts['asr_fixed_standard'] : '16:30';
    $asr_fixed_dst = isset($opts['asr_fixed_dst']) ? $opts['asr_fixed_dst'] : '16:30';
    $asr_daily_change = isset($opts['asr_daily_change']) ? $opts['asr_daily_change'] : 0;
    $asr_rounding = isset($opts['asr_rounding']) ? $opts['asr_rounding'] : 1;
    
    // Maghrib defaults
    $maghrib_minutes_after = isset($opts['maghrib_minutes_after']) ? $opts['maghrib_minutes_after'] : 5;
    $maghrib_daily_change = isset($opts['maghrib_daily_change']) ? $opts['maghrib_daily_change'] : 0;
    $maghrib_rounding = isset($opts['maghrib_rounding']) ? $opts['maghrib_rounding'] : 1;
    
    // Isha defaults
    $isha_rule = isset($opts['isha_rule']) ? $opts['isha_rule'] : 'after_athan';
    $isha_minutes_after = isset($opts['isha_minutes_after']) ? $opts['isha_minutes_after'] : 15;
    $isha_min_time = isset($opts['isha_min_time']) ? $opts['isha_min_time'] : '19:30';
    $isha_max_time = isset($opts['isha_max_time']) ? $opts['isha_max_time'] : '22:00';
    $isha_daily_change = isset($opts['isha_daily_change']) ? $opts['isha_daily_change'] : 0;
    $isha_rounding = isset($opts['isha_rounding']) ? $opts['isha_rounding'] : 1;
    ?>
    <div class="wrap">
        <!-- Banner Image -->
        <div class="prayer-times-banner">
            <div class="muslprti-banner" style="background-image: url('<?php echo esc_url(plugins_url('assets/admin-header.png', __FILE__)); ?>');"></div>
        </div>

        <!-- New Header Section with Instructions -->
        <div class="prayer-times-header">
            <h1>Muslim Prayer Times Settings</h1>
            <p>
                <strong>Muslim Prayer Times</strong> is a free and open source WordPress plugin. The source code is available on 
                <a href="https://github.com/stankovski/wp-prayer-times" target="_blank" class="github-link">GitHub</a>.
            </p>
            
            <div class="prayer-times-steps">
                <h2>Getting Started: How to Set Up Your Prayer Times</h2>
                <ol>
                    <li><strong>Step 1:</strong> First, provide general information about your location and select a prayer calculation method using the <strong>General Settings</strong> tab below.</li>
                    <li><strong>Step 2:</strong> Configure the <strong>Iqama Rules</strong> to determine how Iqama times will be calculated based on Athan times. These rules are only used to generate the CSV file.</li>
                    <li><strong>Step 3:</strong> Generate a CSV file with your prayer times using the Export/Import section, then review and adjust the times as necessary.</li>
                    <li><strong>Step 4:</strong> Import the CSV file back into the plugin to use these times on your website.</li>
                    <li><strong>Step 5:</strong> If you need to make changes to your prayer times in the future, you can export the existing data from the database, modify it, and re-import it. You can also re-generate the prayer times and re-import them. Note, each time you import the CSV file, it will only overwrite the days specified in the CSV file.</li>
                </ol>
            </div>
            
            <div class="available-blocks">
                <h3>Available Prayer Times Blocks</h3>
                <p>This plugin includes three blocks in the editor under the "Muslim Prayer Times" category:</p>
                <ul>
                    <li><strong>Daily Prayer Times Block:</strong> Show today's prayer times on your main page. This block is ideal for displaying the current day's prayers with scrolling capabilities to see upcoming days.</li>
                    <li><strong>Monthly Prayer Times Block:</strong> Display a full table of prayer times for the month. Perfect for providing a comprehensive view of prayer schedules.</li>
                    <li><strong>Live Prayer Times Block:</strong> Designed specifically for Digital Displays with automatic time updates and highlighting of the next prayer. Works best with the <a href="https://wordpress.org/plugins/digital-signage/" target="_blank">Digital Signage plugin</a>.</li>
                </ul>
                <p>Each block is highly customizable through the block editor interface. You can adjust colors, display options, and more to match your site's design.</p>
            </div>
        </div>
        
        <!-- Accordion Container -->
        <div class="muslprti-accordion">
            <!-- General Settings Section -->
            <div class="muslprti-accordion-header active">General Settings</div>
            <div class="muslprti-accordion-content active">
                <form method="post">
                    <?php wp_nonce_field('muslprti_save_general_settings', 'muslprti_general_settings_nonce'); ?>
                    <div class="card muslprti-card">
                        <h2>Find Coordinates by Address</h2>
                        <p>Enter an address to automatically find the latitude and longitude.</p>
                        
                        <div class="geocode-container">
                            <input type="text" id="muslprti_address" placeholder="Enter address, city, or place name" class="regular-text">
                            <button type="button" id="muslprti_geocode_btn" class="button">Find Coordinates</button>
                            <div id="muslprti_geocode_results" class="muslprti-geocode-results"></div>
                        </div>
                    </div>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="muslprti_lat">Latitude</label></th>
                            <td><input type="text" id="muslprti_lat" name="muslprti_lat" value="<?php echo esc_attr($lat); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="muslprti_lng">Longitude</label></th>
                            <td><input type="text" id="muslprti_lng" name="muslprti_lng" value="<?php echo esc_attr($lng); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="muslprti_tz">Timezone</label></th>
                            <td>
                                <select id="muslprti_tz" name="muslprti_tz" class="regular-text">
                                    <?php foreach ($timezone_options as $region => $list) : ?>
                                        <optgroup label="<?php echo esc_attr($region); ?>">
                                            <?php foreach ($list as $timezone) : ?>
                                                <option value="<?php echo esc_attr($timezone); ?>" <?php selected($tz, $timezone); ?>>
                                                    <?php echo esc_html($timezone); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Select the timezone for your location.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="muslprti_method">Calculation Method</label></th>
                            <td>
                                <select id="muslprti_method" name="muslprti_method">
                                    <?php foreach ($methods as $k => $v): ?>
                                        <option value="<?php echo esc_attr($k); ?>" <?php selected($method, $k); ?>><?php echo esc_html($v); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="muslprti_asr_calc">Asr Calculation Method</label></th>
                            <td>
                                <select id="muslprti_asr_calc" name="muslprti_asr_calc">
                                    <option value="STANDARD" <?php selected($asr_calc, 'STANDARD'); ?>>Standard (Shafi'i, Maliki, Hanbali)</option>
                                    <option value="HANAFI" <?php selected($asr_calc, 'HANAFI'); ?>>Hanafi</option>
                                </select>
                                <p class="description">Standard: Shadow length = object height<br>Hanafi: Shadow length = 2 × object height</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="muslprti_hijri_offset">Hijri Day Offset</label></th>
                            <td>
                                <select id="muslprti_hijri_offset" name="muslprti_hijri_offset">
                                    <option value="-2" <?php selected($hijri_offset, -2); ?>>-2 days</option>
                                    <option value="-1" <?php selected($hijri_offset, -1); ?>>-1 day</option>
                                    <option value="0" <?php selected($hijri_offset, 0); ?>>No adjustment</option>
                                    <option value="1" <?php selected($hijri_offset, 1); ?>>+1 day</option>
                                    <option value="2" <?php selected($hijri_offset, 2); ?>>+2 days</option>
                                </select>
                                <span id="hijri-date-preview" class="hijri-preview">
                                    <?php 
                                    // Load Hijri date converter
                                    require_once plugin_dir_path(__FILE__) . 'includes/hijri-date-converter.php';
                                    $today = muslprti_date('Y-m-d');
                                    $hijri_date = muslprti_convert_to_hijri($today, true, 'en', $hijri_offset);
                                    echo esc_html("Today: " . $hijri_date); 
                                    ?>
                                </span>
                                <p class="description">Adjust the calculated Hijri date if needed to match local moon sighting.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="muslprti_time_format">Time Format</label></th>
                            <td>
                                <select id="muslprti_time_format" name="muslprti_time_format">
                                    <option value="12hour" <?php selected($time_format, '12hour'); ?>>12-hour format (AM/PM)</option>
                                    <option value="24hour" <?php selected($time_format, '24hour'); ?>>24-hour format</option>
                                </select>
                                <p class="description">Choose how times will be displayed in all blocks and shortcodes.</p>
                            </td>
                        </tr>
                        <!-- Jumuah Prayer Times -->
                        <tr>
                            <th scope="row" colspan="2"><h3>Jumuah Prayer Times</h3></th>
                        </tr>
                        <tr>
                            <th scope="row"><label for="muslprti_jumuah1">Jumuah 1</label></th>
                            <td>
                                <input type="time" id="muslprti_jumuah1" name="muslprti_jumuah1" value="<?php echo esc_attr($jumuah1); ?>" class="regular-text">
                                <p class="description">Leave empty if there is no Jumuah prayer.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="muslprti_jumuah1_name">Jumuah 1 Name</label></th>
                            <td>
                                <input type="text" id="muslprti_jumuah1_name" name="muslprti_jumuah1_name" value="<?php echo esc_attr($jumuah1_name); ?>" class="regular-text">
                                <p class="description">Custom name for the first Jumuah prayer (e.g. "First Khutbah", "English Khutbah", etc.)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="muslprti_jumuah2">Jumuah 2</label></th>
                            <td>
                                <input type="time" id="muslprti_jumuah2" name="muslprti_jumuah2" value="<?php echo esc_attr($jumuah2); ?>" class="regular-text">
                                <p class="description">Leave empty if there is no second Jumuah prayer.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="muslprti_jumuah2_name">Jumuah 2 Name</label></th>
                            <td>
                                <input type="text" id="muslprti_jumuah2_name" name="muslprti_jumuah2_name" value="<?php echo esc_attr($jumuah2_name); ?>" class="regular-text">
                                <p class="description">Custom name for the second Jumuah prayer (e.g. "Second Khutbah", "Arabic Khutbah", etc.)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="muslprti_jumuah3">Jumuah 3</label></th>
                            <td>
                                <input type="time" id="muslprti_jumuah3" name="muslprti_jumuah3" value="<?php echo esc_attr($jumuah3); ?>" class="regular-text">
                                <p class="description">Leave empty if there is no third Jumuah prayer.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="muslprti_jumuah3_name">Jumuah 3 Name</label></th>
                            <td>
                                <input type="text" id="muslprti_jumuah3_name" name="muslprti_jumuah3_name" value="<?php echo esc_attr($jumuah3_name); ?>" class="regular-text">
                                <p class="description">Custom name for the third Jumuah prayer (e.g. "Third Khutbah", "Youth Khutbah", etc.)</p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Save General Settings', 'primary', 'submit_general'); ?>
                </form>
            </div>
            
            <!-- Iqama Rules Section -->
            <div class="muslprti-accordion-header">Iqama Rules</div>
            <div class="muslprti-accordion-content">
                <form method="post">
                    <?php wp_nonce_field('muslprti_save_iqama_settings', 'muslprti_iqama_settings_nonce'); ?>
                    <p>Define rules for calculating Iqama times based on Athan times.</p>
                    
                    <div class="iqama-rule-section">
                        <h3>General Settings</h3>
                        <div class="iqama-rule-option">
                            <label><strong>How often to change times:</strong></label>
                            <select name="muslprti_iqama_frequency" id="muslprti_iqama_frequency">
                                <option value="daily" <?php selected($iqama_frequency, 'daily'); ?>>Daily (Different times each day)</option>
                                <option value="weekly" <?php selected($iqama_frequency, 'weekly'); ?>>Weekly (Same times all week)</option>
                            </select>
                            <p class="description">Determines if Iqama times should change daily or remain the same throughout the week.</p>
                        </div>
                    </div>
                    
                    <div class="iqama-rule-section">
                        <h3>Fajr Iqama Rules</h3>
                        <div class="iqama-rule-option muslprti-iqama-rule-option">
                            <label>
                                <input type="checkbox" name="muslprti_fajr_daily_change" value="1" <?php checked($fajr_daily_change, 1); ?>>
                                Change Fajr times daily (overrides general setting for this prayer time)
                            </label>
                        </div>
                        <div class="iqama-rule-option">
                            <label><strong>Round Fajr times to:</strong></label>
                            <select name="muslprti_fajr_rounding">
                                <option value="1" <?php selected($fajr_rounding, 1); ?>>1 minute</option>
                                <option value="5" <?php selected($fajr_rounding, 5); ?>>5 minutes</option>
                                <option value="15" <?php selected($fajr_rounding, 15); ?>>15 minutes</option>
                                <option value="30" <?php selected($fajr_rounding, 30); ?>>30 minutes</option>
                            </select>
                            <p class="description">Times will be rounded to the nearest value selected above.</p>
                        </div>
                        <div class="iqama-rule-option">
                            <label><input type="radio" name="muslprti_fajr_rule" value="after_athan" <?php checked($fajr_rule, 'after_athan'); ?> class="rule-radio fajr-rule"> 
                                Minutes after Athan</label>
                            <div class="field-container muslprti-field-container">
                                <input type="number" name="muslprti_fajr_minutes_after" id="muslprti_fajr_minutes_after" value="<?php echo esc_attr($fajr_minutes_after); ?>" min="0" max="120" class="fajr-input" <?php echo $fajr_rule !== 'after_athan' ? 'disabled' : ''; ?>> minutes after Athan
                            </div>
                        </div>
                        
                        <div class="iqama-rule-option muslprti-iqama-rule-option-top">
                            <label><input type="radio" name="muslprti_fajr_rule" value="before_shuruq" <?php checked($fajr_rule, 'before_shuruq'); ?> class="rule-radio fajr-rule">
                                Minutes before Shuruq (sunrise)</label>
                            <div class="field-container muslprti-field-container">
                                <input type="number" name="muslprti_fajr_minutes_before_shuruq" id="muslprti_fajr_minutes_before_shuruq" value="<?php echo esc_attr($fajr_minutes_before_shuruq); ?>" min="15" max="120" class="fajr-input" <?php echo $fajr_rule !== 'before_shuruq' ? 'disabled' : ''; ?>> 
                                minutes before Shuruq
                                <p class="description">Note: For safety, this will never be less than 15 minutes before sunrise.</p>
                            </div>
                        </div>
                        
                        <div class="iqama-rule-option">
                            <p><strong>Time constraints (applies to all rules):</strong></p>
                            <p>
                                <label>Minimum Fajr time: <input type="time" name="muslprti_fajr_min_time" value="<?php echo esc_attr($fajr_min_time); ?>"></label><br>
                                <label>Maximum Fajr time: <input type="time" name="muslprti_fajr_max_time" value="<?php echo esc_attr($fajr_max_time); ?>"></label>
                            </p>
                            <p class="description">These settings ensure Fajr Iqama is never before the minimum time or after the maximum time. For "before Shuruq" rule, the maximum is automatically limited to the calculated time before sunrise.</p>
                        </div>
                    </div>
                    
                    <div class="iqama-rule-section">
                        <h3>Dhuhr Iqama Rules</h3>
                        <div class="iqama-rule-option muslprti-iqama-rule-option">
                            <label>
                                <input type="checkbox" name="muslprti_dhuhr_daily_change" value="1" <?php checked($dhuhr_daily_change, 1); ?>>
                                Change Dhuhr times daily (overrides general setting for this prayer time)
                            </label>
                        </div>
                        <div class="iqama-rule-option">
                            <label><strong>Round Dhuhr times to:</strong></label>
                            <select name="muslprti_dhuhr_rounding">
                                <option value="1" <?php selected($dhuhr_rounding, 1); ?>>1 minute</option>
                                <option value="5" <?php selected($dhuhr_rounding, 5); ?>>5 minutes</option>
                                <option value="15" <?php selected($dhuhr_rounding, 15); ?>>15 minutes</option>
                                <option value="30" <?php selected($dhuhr_rounding, 30); ?>>30 minutes</option>
                            </select>
                            <p class="description">Times will be rounded to the nearest value selected above.</p>
                        </div>
                        <div class="iqama-rule-option">
                            <label><input type="radio" name="muslprti_dhuhr_rule" value="after_athan" <?php checked($dhuhr_rule, 'after_athan'); ?> class="rule-radio dhuhr-rule"> 
                                Minutes after Athan</label>
                            <div class="field-container muslprti-field-container">
                                <input type="number" name="muslprti_dhuhr_minutes_after" value="<?php echo esc_attr($dhuhr_minutes_after); ?>" min="0" max="120" class="dhuhr-input" <?php echo $dhuhr_rule !== 'after_athan' ? 'disabled' : ''; ?>> minutes after Athan
                            </div>
                        </div>
                        
                        <div class="iqama-rule-option muslprti-iqama-rule-option-top">
                            <label><input type="radio" name="muslprti_dhuhr_rule" value="fixed_time" <?php checked($dhuhr_rule, 'fixed_time'); ?> class="rule-radio dhuhr-rule">
                                Fixed time (separate for Standard and DST)</label>
                            <div class="field-container muslprti-field-container">
                                <label>Standard Time: <input type="time" name="muslprti_dhuhr_fixed_standard" value="<?php echo esc_attr($dhuhr_fixed_standard); ?>" class="dhuhr-input" <?php echo $dhuhr_rule !== 'fixed_time' ? 'disabled' : ''; ?>></label><br>
                                <label>Daylight Saving Time: <input type="time" name="muslprti_dhuhr_fixed_dst" value="<?php echo esc_attr($dhuhr_fixed_dst); ?>" class="dhuhr-input" <?php echo $dhuhr_rule !== 'fixed_time' ? 'disabled' : ''; ?>></label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="iqama-rule-section">
                        <h3>Asr Iqama Rules</h3>
                        <div class="iqama-rule-option muslprti-iqama-rule-option">
                            <label>
                                <input type="checkbox" name="muslprti_asr_daily_change" value="1" <?php checked($asr_daily_change, 1); ?>>
                                Change Asr times daily (overrides general setting for this prayer time)
                            </label>
                        </div>
                        <div class="iqama-rule-option">
                            <label><strong>Round Asr times to:</strong></label>
                            <select name="muslprti_asr_rounding">
                                <option value="1" <?php selected($asr_rounding, 1); ?>>1 minute</option>
                                <option value="5" <?php selected($asr_rounding, 5); ?>>5 minutes</option>
                                <option value="15" <?php selected($asr_rounding, 15); ?>>15 minutes</option>
                                <option value="30" <?php selected($asr_rounding, 30); ?>>30 minutes</option>
                            </select>
                            <p class="description">Times will be rounded to the nearest value selected above.</p>
                        </div>
                        <div class="iqama-rule-option">
                            <label><input type="radio" name="muslprti_asr_rule" value="after_athan" <?php checked($asr_rule, 'after_athan'); ?> class="rule-radio asr-rule"> 
                                Minutes after Athan</label>
                            <div class="field-container muslprti-field-container">
                                <input type="number" name="muslprti_asr_minutes_after" value="<?php echo esc_attr($asr_minutes_after); ?>" min="0" max="120" class="asr-input" <?php echo $asr_rule !== 'after_athan' ? 'disabled' : ''; ?>> minutes after Athan
                            </div>
                        </div>
                        
                        <div class="iqama-rule-option muslprti-iqama-rule-option-top">
                            <label><input type="radio" name="muslprti_asr_rule" value="fixed_time" <?php checked($asr_rule, 'fixed_time'); ?> class="rule-radio asr-rule">
                                Fixed time (separate for Standard and DST)</label>
                            <div class="field-container muslprti-field-container">
                                <label>Standard Time: <input type="time" name="muslprti_asr_fixed_standard" value="<?php echo esc_attr($asr_fixed_standard); ?>" class="asr-input" <?php echo $asr_rule !== 'fixed_time' ? 'disabled' : ''; ?>></label><br>
                                <label>Daylight Saving Time: <input type="time" name="muslprti_asr_fixed_dst" value="<?php echo esc_attr($asr_fixed_dst); ?>" class="asr-input" <?php echo $asr_rule !== 'fixed_time' ? 'disabled' : ''; ?>></label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="iqama-rule-section">
                        <h3>Maghrib Iqama Rules</h3>
                        <div class="iqama-rule-option muslprti-iqama-rule-option">
                            <label>
                                <input type="checkbox" name="muslprti_maghrib_daily_change" value="1" <?php checked($maghrib_daily_change, 1); ?>>
                                Change Maghrib times daily (overrides general setting for this prayer time)
                            </label>
                        </div>
                        <div class="iqama-rule-option">
                            <label><strong>Round Maghrib times to:</strong></label>
                            <select name="muslprti_maghrib_rounding">
                                <option value="1" <?php selected($maghrib_rounding, 1); ?>>1 minute</option>
                                <option value="5" <?php selected($maghrib_rounding, 5); ?>>5 minutes</option>
                                <option value="15" <?php selected($maghrib_rounding, 15); ?>>15 minutes</option>
                                <option value="30" <?php selected($maghrib_rounding, 30); ?>>30 minutes</option>
                            </select>
                            <p class="description">Times will be rounded to the nearest value selected above.</p>
                        </div>
                        <div class="iqama-rule-option">
                            <p>Maghrib Iqama is
                            <input type="number" name="muslprti_maghrib_minutes_after" value="<?php echo esc_attr($maghrib_minutes_after); ?>" min="0" max="30"> 
                            minutes after sunset (Athan)</p>
                            <p class="description">Maghrib Iqama is typically 5-10 minutes after sunset.</p>
                        </div>
                    </div>
                    
                    <div class="iqama-rule-section">
                        <h3>Isha Iqama Rules</h3>
                        <div class="iqama-rule-option muslprti-iqama-rule-option">
                            <label>
                                <input type="checkbox" name="muslprti_isha_daily_change" value="1" <?php checked($isha_daily_change, 1); ?>>
                                Change Isha times daily (overrides general setting for this prayer time)
                            </label>
                        </div>
                        <div class="iqama-rule-option">
                            <label><strong>Round Isha times to:</strong></label>
                            <select name="muslprti_isha_rounding">
                                <option value="1" <?php selected($isha_rounding, 1); ?>>1 minute</option>
                                <option value="5" <?php selected($isha_rounding, 5); ?>>5 minutes</option>
                                <option value="15" <?php selected($isha_rounding, 15); ?>>15 minutes</option>
                                <option value="30" <?php selected($isha_rounding, 30); ?>>30 minutes</option>
                            </select>
                            <p class="description">Times will be rounded to the nearest value selected above.</p>
                        </div>
                        <div class="iqama-rule-option">
                            <label><input type="radio" name="muslprti_isha_rule" value="after_athan" <?php checked($isha_rule, 'after_athan'); ?> class="rule-radio"> 
                                Minutes after Athan</label>
                        </div>
                        <div class="conditional-field <?php echo $isha_rule === 'after_athan' ? 'active' : ''; ?>" id="isha_after_athan">
                            <input type="number" name="muslprti_isha_minutes_after" value="<?php echo esc_attr($isha_minutes_after); ?>" min="0" max="120"> minutes after Athan
                        </div>
                        
                        <div class="iqama-rule-option">
                            <p><strong>Time constraints (applies to all rules):</strong></p>
                            <p>
                                <label>Minimum Isha time: <input type="time" name="muslprti_isha_min_time" value="<?php echo esc_attr($isha_min_time); ?>"></label><br>
                                <label>Maximum Isha time: <input type="time" name="muslprti_isha_max_time" value="<?php echo esc_attr($isha_max_time); ?>"></label>
                            </p>
                            <p class="description">These settings ensure Isha Iqama is never before the minimum time or after the maximum time.</p>
                        </div>
                    </div>
                    
                    
                    <?php submit_button('Save Iqama Rules', 'primary', 'submit_iqama'); ?>
                </form>
            </div>
        </div>
        
        <!-- Export/Import Section (kept as is) -->
        <div class="card muslprti-card-top-margin">            
            <h2>Export/Import Prayer Times</h2>

            <h3>1. Generate Prayer Times</h3>
            <p>Generate prayer times based on your location settings in CSV format.</p>
            <div>
                <button type="button" id="muslprti_generate_btn" class="button">Generate Prayer Times</button>
                <select id="muslprti_period" class="muslprti-period-select">
                    <option value="7">7 days</option>
                    <option value="30" selected>30 days</option>
                    <option value="90">90 days</option>
                    <option value="365">365 days</option>
                    <option value="custom">Custom range</option>
                </select>
                <div id="custom_date_range" class="muslprti-custom-date-range">
                    <label for="muslprti_start_date">Start date:</label>
                    <input type="date" id="muslprti_start_date" class="muslprti-date-input">
                    <label for="muslprti_end_date">End date:</label>
                    <input type="date" id="muslprti_end_date">
                </div>
            </div>
            
            <h3 style="margin-top:20px;">2. Import Prayer Times</h3>
            <p>Import prayer times from a CSV file. The file should match the format of the exported CSV.</p>
            <form id="muslprti_import_form" enctype="multipart/form-data">
                <p>
                    <label for="muslprti_import_file"><strong>Select CSV file:</strong></label><br>
                    <input type="file" id="muslprti_import_file" name="import_file" accept=".csv">
                </p>
                <p>
                    <label for="muslprti_date_format"><strong>Date format in CSV:</strong></label><br>
                    <select id="muslprti_date_format" name="date_format">
                        <option value="Y-m-d">YYYY-MM-DD (ISO format)</option>
                        <option value="n/j/y">M/D/YY (Excel default)</option>
                        <option value="n/j/Y">M/D/YYYY (US format)</option>
                        <option value="m/d/y">MM/DD/YY (US format with leading zeros)</option>
                        <option value="m/d/Y">MM/DD/YYYY (US format with leading zeros)</option>
                        <option value="d/m/Y">DD/MM/YYYY (European format)</option>
                        <option value="d/m/y">DD/MM/YY (European format)</option>
                        <option value="d-m-Y">DD-MM-YYYY (European with dashes)</option>
                        <option value="d-m-y">DD-MM-YY (European with dashes)</option>
                        <option value="d.m.Y">DD.MM.YYYY (German/European format)</option>
                        <option value="d.m.y">DD.MM.YY (German/European format)</option>
                        <option value="Y/m/d">YYYY/MM/DD (Asian format)</option>
                        <option value="Y.m.d">YYYY.MM.DD (Asian with dots)</option>
                        <option value="j/n/Y">D/M/YYYY (Single digits, European)</option>
                        <option value="j-n-Y">D-M-YYYY (Single digits with dashes)</option>
                        <option value="j.n.Y">D.M.YYYY (Single digits with dots)</option>
                    </select>
                    <span id="date-format-preview" class="hijri-preview" style="margin-left: 15px;"></span>
                </p>
                <p>
                    <button type="button" id="muslprti_preview_btn" class="button">Preview Import</button>
                    <button type="button" id="muslprti_import_btn" class="button button-primary" disabled>Import Prayer Times</button>
                </p>
            </form>
            <div id="muslprti_import_preview" style="margin-top:10px;"></div>
            <div id="muslprti_import_result" style="margin-top:10px;"></div>

            <h3 style="margin-top:20px;">3. Export Prayer Times</h3>
            <p>Export existing prayer times from the database for further adjustment. 
                The file can be re-imported again.</p>
            <div>
                <button type="button" id="muslprti_export_db_btn" class="button">Export Existing Prayer Times</button>
            </div>
        </div>
    </div>
    <?php
}
