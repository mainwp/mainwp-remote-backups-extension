=== Remote Backup Extension ===
Plugin Name: MainWP Remote Backup Extension
Plugin URI: https://mainwp.com
Description: MainWP Remote Backup Extension is an extension for the MainWP plugin that enables you store your backups on different off site locations.
Version: 1.1
Author: MainWP
Author URI: https://mainwp.com
Icon URI:

== Installation ==
1. Please install plugin "MainWP Dashboard" and active it before install Remote Backup Extension plugin (get the MainWP Dashboard plugin from url:http://mainwp.com/)
1. Upload the `mainwp-remote-backup-extension` folder to the `/wp-content/plugins/` directory
1. Activate the Remote Backup Extension plugin through the 'Plugins' menu in WordPress

== Screenshots ==
1. Enable or Disable extension on the "Extensions" page in the dashboard

== Changelog ==

=1.1 = 3-4-2016
* Fixed: Missing icon issue

=1.0 = 2-17-2016
* Added: An auto update warning if the extension is not activated
* Added: Support for the new API management
* Added: Support for WP-CLI
* Updated: "Check for updates now" link is not vidible if extension is not activated

= 0.1.0 = 6-5-2015
* Enhancement: Updated S3 API
* Enhancement: Updated Dropbox API
* Fixed: Resume function for S3 uploads
* Fixed: Stability for FTP upload

= 0.0.8 = 
* Updated: Quick start guide layout

= 0.0.7 =
* Fixed: database issue when using the same remote destination on several of tasks
* Fixed: MD5 Hash error on Amazon S3
* Added: resume function when upload fails

= 0.0.6 =
* Added: Support for the API Manager

= 0.0.5 =
* Added: Several new subtasks to increase performance and reduce timeouts on Backups
* [FIX] FTP timeout on servers with a lot of files

= 0.0.4 =
* [FIX] Declaring wp_mail causing conflict with Mandrill

= 0.0.3 =
* [FIX] Cron backups do not upload to all external destinations

= 0.0.2 =
* [FIX] Table always shows "Server", even with remote destinations
* [FIX] Button "remote destination" not highlighted on edit backup task with remote destination

= 0.0.1 =
* First version
