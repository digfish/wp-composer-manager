<?php
//namespace Digfish\ComposerInstaller;

/**
 * Plugin Name: WP Composer Manager
 * Plugin URI: https://github.com/digfish/wp-composer-manager-plugin
 * Description: WP Composer Manager allows to download the Composer package manager and use it to download dependencies on plugins/themes that has dependencies and you don't have access to a shell
 * Version: 0.0.1
 * Author: digfish
 * Author URI: https://github.com/digfish/
 * License: GPL3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: wpcm
 * Domain Path: /languages
 *
 * @package wp-composer-manager
 */

define( 'WPCM_TEXT_DOMAIN', 'wpcm' );
define( 'WPCM_PLUGIN_NAME', 'WP Composer Manager' );

include_once( __DIR__ . "/wpcm-options.php" );
include_once( __DIR__ . "/wpcm-helpers.php" );
include_once( __DIR__ . "/composer-launcher.php" );

function plugin_instantiate() {
	global $composer_launcher;
	if ( ! isset( $composer_launcher ) ) {
		try {
			$composer_launcher = new ComposerLauncher();
		} catch ( \Exception $ex ) {
			$output = $ex->getMessage();
		}
	}
}

add_action( 'plugins_loaded', 'plugin_instantiate', 5 );
