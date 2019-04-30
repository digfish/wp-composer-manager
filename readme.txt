=== WP Composer Manager ===
Contributors: digitalfisherman
Donate link: https://digfish.org/
Tags: composer package-manager development
Requires at least: 5.0
Tested up to: 5.1.1
Stable tag: trunk
Requires PHP: 5.2.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WP Composer Manager allows the integration of composer in your wordpress installation.

== Description ==

This is a plugin aimed at Wordpress developers that use composer the manages the dependencies of their projects. It allows to invoke composer from the Wordpress dashboard, for things like adding new dependencies/packages or automatically updating all the dependencies.

At the most basic level, it shows the current plugins/themes that are using composer, the PHP Dependency Manager that has replaced PEAR as _de-facto_ , being used by the majority the PHP frameworks.

If you are a Wordpress developer that uses composer and you are using a hosting platform that does not provide shell access, this plugin is for you!




== Installation ==


1. Upload the plugin files to the `/wp-content/plugins/wp-composer-manager` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Settings->Plugin Name screen to configure the plugin
4. Verify if the directory says that the composer.phar file is present
5. Create the composer home directory as the plugin prompts you to do so

== Frequently Asked Questions ==

= What is the composer home directory and where will be located ? =

The composer home directory is automatically created for composer and is where composer stores its cache for archives and files that is not needed to be downloaded again. The plugin asks for you to initialize this directory at the time you activate it. By default and at this time, the composer-home will be located at your `wp-content` directory in your wordpress instalaction.

= Which composer commands are supported? =

By now, allows you to view the contents of composer.json, update the plugins that are using composer, and add dependencies to the plugin using composer search through the dashboard.

== Screenshots ==

1. dashboard-screen.jpg
2. composer-searching.jpg

== Changelog ==

= 0.0.1 =
* First version

= 0.1 =
* Pushed to githun


== Upgrade Notice ==

= 0.1 =
First version. Not applicable.

