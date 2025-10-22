=== Muslim Prayer Times ===
Contributors: stankovski
Tags: prayer times, muslim, islamic, mosque, salah
Requires at least: 5.0
Tested up to: 6.8.3
Stable tag: 1.0.2
Requires PHP: 7.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Add accurate prayer times and iqama schedules to your WordPress site using blocks or shortcodes.

== Description ==

Muslim Prayer Times is a powerful plugin that allows you to display daily and monthly prayer times on your WordPress website. Perfect for mosques, Islamic centers, or any Muslim website.

= Features =
* Daily prayer times display with both Athan and Iqama times
* Monthly prayer times calendar
* Live prayer times display that updates automatically
* Customizable calculation methods (MWL, ISNA, Egypt, etc.)
* Blocks and shortcodes for easy integration
* Admin interface to manage Iqama times
* Automatic location-based prayer times
* Customizable display options with multiple styles
* Hijri date conversion with adjustment options
* Jumuah (Friday) prayer time management
* Responsive design for all devices

== Installation ==

1. Upload the `muslim-prayer-times` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure settings under Settings > Muslim Prayer Times
4. Add prayer times to your posts or pages using blocks or shortcodes

== How to Use ==

= Setting Up Prayer Times =

1. **Configure Basic Settings**:
   * Go to Settings > Muslim Prayer Times in your WordPress dashboard
   * Enter your location's coordinates (latitude and longitude) or use the "Find Coordinates by Address" feature
   * Select your timezone and prayer calculation method
   * Set Hijri date adjustment if needed
   * Configure Jumuah (Friday) prayer times if applicable

2. **Configure Iqama Rules**:
   * In the Iqama Rules tab, set rules for calculating Iqama times based on Athan times
   * You can choose different rules for each prayer (Fajr, Dhuhr, Asr, Maghrib, Isha)
   * Options include minutes after Athan, fixed times, or specific rules like "minutes before sunrise" for Fajr

3. **Generate and Import Prayer Times**:
   * Use the Export/Import section to generate prayer times based on your settings
   * Review and adjust the generated CSV file if needed
   * Import the CSV back into the plugin to set up your prayer schedule

= Using the Blocks =

The plugin includes three blocks that can be added to any post or page:

1. **Daily Prayer Times Block**:
   * Shows the current day's prayer schedule with navigation for upcoming days
   * Ideal for homepage display or mosque information pages
   * Customizable colors, fonts, and display options

2. **Monthly Prayer Times Block**:
   * Displays a complete monthly calendar of prayer times
   * Perfect for providing visitors with a comprehensive prayer schedule
   * Options to show/hide various elements like sunrise times

3. **Live Prayer Times Block**:
   * Shows real-time prayer schedule with a live clock
   * Automatically highlights the next upcoming prayer
   * Ideal for digital displays in mosques when used with the [Digital Signage](https://wordpress.org/plugins/digital-signage/) plugin

To add a block:
1. Edit any post or page
2. Click the "+" button to add a block
3. Search for "Prayer Times" or look in the "Muslim Prayer Times" category
4. Select the block you want to add
5. Customize the block settings in the sidebar

= Customization Options =

Each block comes with extensive customization options:

* Change colors for text, backgrounds, and headers
* Adjust font sizes
* Show or hide elements (date, Hijri date, sunrise times, etc.)
* Choose from different table styles
* Set text alignment
* Customize the display of upcoming prayer time changes

= For Developers =

This plugin is open-source and available on GitHub: [https://github.com/stankovski/wp-prayer-times](https://github.com/stankovski/wp-prayer-times)

Developers can extend the plugin's functionality or customize it further by:
* Adding custom styling with CSS
* Creating new blocks or shortcodes
* Integrating with other mosque management plugins

== Frequently Asked Questions ==

= How do I display prayer times on my page? =

You can use either the Gutenberg blocks (Daily Prayer Times, Monthly Prayer Times, Live Prayer Times) or shortcodes. The blocks provide a visual interface for customization, while shortcodes can be used in classic editor or text widgets.

= Can I customize the calculation method? =

Yes, you can select from multiple standard calculation methods in the plugin settings, including ISNA, MWL, Egyptian, Umm Al-Qura, and many others. This allows you to match the calculation method used by your local mosque or Islamic organization.

= How can I adjust Hijri dates? =

The plugin includes Hijri date conversion with an offset option. If the automatically calculated Hijri date doesn't match your local moon sighting committee's determination, you can adjust it by +/- 2 days in the settings.

= Can I display multiple Jumuah prayer times? =

Yes, the plugin supports up to three different Jumuah (Friday prayer) times, each with its own customizable label. This is perfect for mosques that offer multiple Khutbahs in different languages.

= How do I update prayer times if they change? =

You can export your existing prayer times, make changes in a spreadsheet application, and re-import them. Alternatively, you can regenerate prayer times with updated settings and import the new times.

= Is the plugin mobile-friendly? =

Yes, all prayer time displays are fully responsive and will adapt to different screen sizes, from desktop to mobile devices.

== Screenshots ==

1. Daily prayer times display showing Athan and Iqama times
2. Daily prayer times editor
3. Monthly prayer times calendar view
4. Live prayer times display for digital signage
5. Admin settings page with prayer time configuration
6. Import/export interface for prayer times management

== Changelog ==

= 1.0 =
* Initial release with daily, monthly, and live prayer times features
* Gutenberg blocks support
* Hijri date conversion
* Iqama rules configuration
* Prayer times import/export functionality

== Upgrade Notice ==

= 1.0 =
Initial release of Muslim Prayer Times plugin.

= 1.0.3 =
Bug fixes.