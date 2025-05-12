## Muslim Prayer Times Plugin for WordPress

### Description

Muslim Prayer Times is a powerful plugin that allows you to display daily and monthly prayer times on your WordPress website. Perfect for mosques, Islamic centers, or any Muslim website.

#### Features
* Daily prayer times display with both Athan and Iqama times
* Monthly prayer times calendar
* Live prayer times display that updates automatically
* Customizable calculation methods (MWL, ISNA, Egypt, etc.)
* Blocks and shortcodes for easy integration
* Admin interface to manage Iqama times
* Automatic location-based prayer times

#### Installation

1. Upload the `prayer-times` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure settings under Settings > Muslim Prayer Times
4. Add prayer times to your posts or pages using blocks or shortcodes

#### Frequently Asked Questions

*How do I display prayer times on my page?*

You can use either the Gutenberg blocks (Daily Prayer Times, Monthly Prayer Times, Live Prayer Times) or shortcodes like [prayer_times].

*Can I customize the calculation method?*

Yes, you can select from multiple standard calculation methods in the plugin settings.

#### Screenshots

1. Daily prayer times display showing Athan and Iqama times
2. Monthly prayer times calendar view
3. Admin settings page

### Development

1. Start the environment:
   ```
   docker-compose up
   ```

2. Access WordPress at [http://localhost:8000](http://localhost:8000)  
   Access phpMyAdmin at [http://localhost:8080](http://localhost:8080) (user: root, password: root)

3. To stop the environment:
   ```
   docker-compose down
   ```

### Debugging with VS Code

1. Open this folder in VS Code.
2. Install the "PHP Debug" extension.
3. Make sure Xdebug is enabled in your PHP container.
4. Use the launch configurations in `.vscode/launch.json` to start debugging.
