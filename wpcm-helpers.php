<?php

/** delete recursively the entire dirtree specified by $dirPath
 * TODO: is ignoring hidden files, if it finds one, the directory and the file are not removed
 *
 * @param $dirPath
 */
function deleteDir( $dirPath ) {
	if ( ! is_dir( $dirPath ) ) {
		throw new InvalidArgumentException( '$dirPath must be a directory' );
	}
	if ( substr( $dirPath, strlen( $dirPath ) - 1, 1 ) != '/' ) {
		$dirPath .= '/';
	}
	$files = glob( $dirPath . '*', GLOB_MARK );
	foreach ( $files as $file ) {
		if ( is_dir( $file ) ) {
			deleteDir( $file );
		} else {
			echo "Deleting $file.\n";
			unlink( $file );
		}
	}
	echo "+Deleting dir $dirPath\n";
	@rmdir( $dirPath );
}

/**
 * Crawls recursively the path for the presence of folders containing some filename
 *
 * @param $path
 * @param $filename
 *
 * @return array the directories (relative to the WP install root) containg the filename
 */
function _check_file_dirtree( $path, $filename ) {
	if ( ! is_dir( $path ) ) {
		throw new InvalidArgumentException( "$path is not a directory!" );
	}

	$foundDir = array();

	$dh = opendir( $path );
	while ( ( $file = readdir( $dh ) ) !== false ) {
		if ( $file == '.' or $file == '..' or $file == 'vendor' ) {
			continue;
		}
		$abspath     = realpath( $path );
		$absfilepath = "$abspath/$file";
		if ( $file == "composer.json" ) {
			$foundDir[] = $abspath;
		}

		if ( is_dir( $absfilepath ) ) {
			$subdir = $absfilepath;

			$foundSubDirs = _check_file_dirtree( $subdir, $filename );

			$foundDir = array_merge( $foundDir, $foundSubDirs );
		}
	}
	closedir( $dh );

	return $foundDir;
}

/**
 * Lists all the folders containing a composer.json file in it in the WP installation dir
 *
 * @param $parent_path
 *
 * @return array
 */
function composer_json_check( $parent_path ) {
	if ( ! is_dir( $parent_path ) ) {
		throw new InvalidArgumentException( "$parent_path is not a valid path!" );
	}
	chdir( $parent_path );
	$dirs_with_composer_json = _check_file_dirtree( $parent_path, "composer.json" );

	$shortened_dirs = array_map( function ( $dir ) {
		return str_replace( ABSPATH, '', $dir );
	}, $dirs_with_composer_json );

	//$complete_path = array_map(function($dir) { return $dir; },$dirs_with_composer_json);
	return $shortened_dirs;
}

/** Formats an url with the desired action to be used on the dashboard
 *
 * @param $action the name of the actiong
 *
 * @return string the query string to be added to the url
 */
function ci_url( $action ) {
	return "?page=" . WPCM_TEXT_DOMAIN . "&action=$action";
}

/** Giving the action name that needs parameters, builds  the query string
 * from the action name and an array containing the parameters
 *
 * @param $action
 * @param $param_actions
 *
 * @return string
 */
function ci_url_action( $action, $param_actions ) {
	return ci_url( $action ) . "&" . http_build_query( $param_actions );
}

/** Generates a A HTML element for some url with the specified text to
 * be shown on the webpage
 *
 * @param $url
 * @param $title
 *
 * @return string
 */
function ci_link( $url, $title ) {
	return "<A href='$url'>$title</A>";
}

/** Given a filepath, uses the last path segment to use it as a
 * title for the path
 */
function title_from_path( $path ) {
	$matches = preg_split( '/\//', $path );
	if ( count( $matches ) > 0 ) {

		return end( $matches );
	}

	return null;
}

/** Shows a formatted error message in the webpage
 *
 * @param $message
 */
function ci_error( $message ) {
	echo "<P style='color:red'>$message</P>";
}
