<?php

//namespace Digfish\ComposerInstaller;


function ci_create_ci_menu() {
	//add_options_page( 'Composer Installer Settings', 'wp-composer-manager plug-in', 'manage_options', 'wp-composer-manager', 'wpcm_dash_page' );
	add_menu_page( WPCM_PLUGIN_NAME, WPCM_PLUGIN_NAME, 'administrator', WPCM_TEXT_DOMAIN, 'wpcm_dash_page', 'dashicons-layout', 2 );
}

add_action( 'admin_menu', 'ci_create_ci_menu' );

/**
 * Verifies if the composer.phar archive exists and if not, downloads it
 */
function download_composer() {
	// https://getcomposer.org/composer.phar

	$composer_download_url = 'https://getcomposer.org/composer.phar';

	if ( composerPharExists() ) {
		return;
	}

	echo "<P>Downloading $composer_download_url ... </P>";
	$ch          = curl_init( $composer_download_url );
	$plugin_path = plugin_dir_path( __FILE__ );
	$fp          = fopen( "$plugin_path/composer.phar", 'w' );
	curl_setopt( $ch, CURLOPT_FILE, $fp );
	curl_setopt( $ch, CURLOPT_HEADER, 0 );

	curl_exec( $ch );
	curl_close( $ch );
	fclose( $fp );
	echo "<P>Composer Download complete! </P>";

}

/**
 * Verifies if composer.phar exists in the plugin dir
 * @return bool
 */
function composerPharExists() {
	$plugin_path = plugin_dir_path( __FILE__ );

	return file_exists( "$plugin_path/composer.phar" );
}

/**
 * initializes the composer homedir at WP_CONTENT
 * @return array|void
 */
function init_home() {
	global $composer_launcher;
	if ( homeExists() ) {
		return;
	}
	$home_location = WP_CONTENT_DIR . '/composer-home';


	if ( ! file_exists( $composer_cache ) || ! is_dir( $composer_cache ) ) {
		mkdir( $home_location );
		$output = ComposerLauncher::generateGlobalComposerJson();

		return $output;
	}
}

/**
 * verifies it the composer home directory already exists
 * @return bool
 */
function homeExists() {
	return ComposerLauncher::doesGlobalComposerJsonExists();
	//return is_dir(WP_CONTENT_DIR . '/composer-home');
}

function absolute_path( $path ) {
	if ( ! file_exists( $absolute_path ) || ! is_dir( $absolute_path ) ) {
		return ABSPATH . '/' . $path;
	} else {
		return $path;
	}
}

/**
 * callback for the dash mainpage
 */
function wpcm_dash_page() {
	global $composer_launcher;
	$output      = array();
	$http_method = $_SERVER['REQUEST_METHOD'];
//	echo $http_method;
//	d( $_REQUEST );
	$action = $_REQUEST['action'];
	if ( ! empty( $action ) ) {
		switch ( $action ) {
			case 'download-composer':
				download_composer();
				break;
			case 'init-home':
				$output = init_home();
				break;

			case 'view':
				break;
			case 'update':
				$output = $composer_launcher->update();
				break;
			case 'require':
				$package = $_REQUEST['package'];
				$path    = $_REQUEST['path'];

				$path = absolute_path( $path );
				if ( ! empty( $package ) && ! empty( $path ) ) {
					$output = $composer_launcher->require_( $package, '', $path );
				} else {
					ci_error( "Path or package to require on not specified!" );
				}
				break;
			case 'select-update':
				$project_dir = $_REQUEST['project_to_check'];
				$project_dir = absolute_path( $project_dir );
				if ( ! empty( $project_dir ) ) {
					echo "<P>Switching to the directory $project_dir ...</P>";
					//chdir($project_dir);
					$output = $composer_launcher->update( '', $project_dir );
				} else {
					ci_error( "Path to update not specified!" );
				}
				break;
			case 'empty-cache':
				ob_start();
				$composer_launcher->emptyCache();
				$output = ob_get_clean();
				break;
			default:
				echo "<P style='color:red'>Action '$action' not implemented !</P>";

		}

		if ( is_array( $output ) ) {
			$output = join( "\n", $output );
		}
	}

	// adds the JSON2 and JQueryUI JS frameworks
	wp_enqueue_script( 'json2' );
	wp_enqueue_script( 'jquery-ui-dialog' );
	// dash main view page
	include_once( "wpcm-dash-page.php" );
}


add_action( 'admin_init', 'ci_admin_init' );

function ci_admin_init() {

}

// supposed to show in real time the composer execution, output
// not working at this time
add_action( 'wp_ajax_query_composer_launcher_output', function () {
	global $composer_launcher;
	echo $composer_launcher->lastOutput();
} );

// calls composer search and return the results
add_action( 'wp_ajax_composer_search', function () {
	global $composer_launcher;
	$q = $_REQUEST['q'];
	ob_start();
	$results = $composer_launcher->search( $q );
	ob_get_clean();
	if ( is_array( $results ) ) {
		header( 'Content-type: application/json' );
		echo json_encode( $results );
	} else {
		return $results;
	}
	wp_die();
} );

// sends the composer.json in the selected project (plugin) folder
add_action( 'wp_ajax_view_composer_json', function () {
	global $composer_launcher;
	$project_dir = $_REQUEST['project_to_check'];
	$project_dir = absolute_path( $project_dir );
	if ( ! empty( $project_dir ) ) {
		$contents = file_get_contents( "$project_dir/composer.json" );
		echo $contents;
	}
	wp_die();

} );
