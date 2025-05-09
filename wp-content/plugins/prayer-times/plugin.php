<?php
/*
Plugin Name: Prayer Times
Plugin URI: https://example.com/prayer-times
Description: A WordPress plugin for managing and displaying Islamic prayer times.
Version: 1.0
Author: Your Name
Author URI: https://example.com
*/

if (!defined('ABSPATH')) exit;

// Define constants
define('PRAYERTIMES_VERSION', '1.0');
define('PRAYERTIMES_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PRAYERTIMES_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PRAYERTIMES_IQAMA_TABLE', 'prayer_times');

// Include required files
require_once PRAYERTIMES_PLUGIN_DIR . 'includes/hijri-date-converter.php';
require_once PRAYERTIMES_PLUGIN_DIR . 'admin/admin.php';
require_once PRAYERTIMES_PLUGIN_DIR . 'blocks/daily-prayer-times/index.php';
require_once PRAYERTIMES_PLUGIN_DIR . 'blocks/daily-prayer-times/block.php';
require_once PRAYERTIMES_PLUGIN_DIR . 'blocks/monthly-prayer-times/block.php';

// ... rest of the plugin code ...
