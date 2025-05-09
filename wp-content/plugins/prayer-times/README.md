# Prayer Times WordPress Plugin

This plugin provides a shortcode to display Islamic prayer times in your WordPress posts and pages.

## Installation

### Option 1: Standard Installation
1. Upload the plugin folder to your `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Run `composer install` in the plugin directory to install dependencies

### Option 2: Manual Dependency Installation
If you don't have Composer available:
1. Upload the plugin folder to your `/wp-content/plugins/` directory
2. Download the [Islamic Network Prayer Times library](https://github.com/islamic-network/prayer-times)
3. Extract it to the `vendor/` directory inside the plugin folder
4. Activate the plugin through the 'Plugins' menu in WordPress

## Usage

Use the shortcode `[prayer_times]` in your posts or pages to display prayer times.

Optional parameters:
- `lat`: Latitude (default: from settings)
- `lng`: Longitude (default: from settings)
- `tz`: Timezone (default: from settings)
- `method`: Calculation method (default: ISNA)
- `asr_calc`: Asr calculation method (Standard or Hanafi)

Example:
```
[prayer_times lat="47.7623" lng="-122.2054" tz="America/Los_Angeles" method="ISNA" asr_calc="Standard"]
```

## Configuration

Go to Settings > Prayer Times to configure default settings for the plugin.
