<?php


use Composer\Command\ScriptAliasCommand;
use Composer\Composer;
use Composer\Console\Application;
use Composer\Factory;
use Composer\IO\ConsoleIO;
use Composer\IO\IOInterface;
use Composer\Util\ErrorHandler;
use Composer\Util\Platform;
use Composer\Util\Silencer;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleOutputInteface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;




/**
 * Class SystemComposerLauncher
 * Main class that wraps the calls to the composer executable
 */
class SystemComposerLauncher {

	static $composer_cache_dir;
	static $composer_location;

	var $last_output_lines_count = 0;
	var $executeOutput = null;


	/**
	 * SystemComposerLauncher constructor.
	 */
	public function __construct() {
		self::init();

	}


	/**
	 * verifies if the homedir was already initialized, if not, it
	 * throws an exception
	 */
	static function init() {

		self::$composer_location = self::detect_composer();
		if (empty(self::$composer_location)) {
			throw new \RuntimeException("Couldn't detect composer installed in your system. Please install it!");
		}
		self::$composer_cache_dir = getenv('HOME') .  '/.composer';
		if ( ! self::doesGlobalComposerJsonExists() ) {
			throw new \RuntimeException( "COMPOSER_HOME was not initialized ! Initialize it!" );
		}

	}

	static function detect_composer() {
		$default_location = PHP_PREFIX.'/local/bin/composer';
		if (file_exists($default_location)) {
			return $default_location;
		} else {
			$path_locations = preg_split('/:/',getenv('PATH'));
			foreach ($path_locations as $location) {
				if (file_exists("$location/composer")) {
					return "$location/composer";
				}
			}
		}
		return null;
	}

	/**
	 * verifies if the file composer.json in the Composer home dir exists
	 * @return bool
	 */
	static function doesGlobalComposerJsonExists() {
		$global_ComposerJson = self::$composer_cache_dir . "/composer.json";
		return file_exists( $global_ComposerJson );
	}

	/**
	 * invokes composer to automatically generate
	 * the composer home dir if there is not one
	 * @return array
	 */
	static function generateGlobalComposerJson() {
		return self::exec( "global", "require" );
	}

	/**
	 * static main invoker of composer
	 *
	 * @param $cmd
	 * @param $options
	 *
	 * @return array
	 * @throws Exception
	 */
	static function exec( $cmd, $options ) {
		self::initEnv();
		$complete_cmd = self::$composer_location . " $cmd $options";
		$exit_code = PHP_INT_MIN;
		ob_start();
		passthru($complete_cmd,$exit_code);
		$output   = ob_get_clean();

		return $output;
	}

	/**
	 * sets some environment variables and overrides PHP configuration
	 */
	static function initEnv() {
		putenv( "COMPOSER_HOME=" . self::$composer_cache_dir );
		ini_set( 'memory_limit', - 1 );
		ini_set('output_buffering', 'off');

	}

	/** main invoker of composer
	 *
	 * @param $cmd the composer command (search, update, require, selfupdate, ...)
	 * @param string $options extra options to be passed to the command
	 * @param string $dir the directory in which the command should be issued
	 *
	 * @return array lines of the output of command
	 * @throws Exception
	 */
/*	protected function execute( $cmd, $options = '', $dir = '' ) {
		ob_start();
		self::initEnv();
		echo "Present working dir is " . getcwd() . "\n";
		if ( ! empty( $dir ) && is_dir( $dir ) ) {
			$options .= " -d $dir";
		}
		$final_cmdline = self::$composer_location . " $cmd $options";
		echo "Invoking '$final_cmdline'\n";

		$exit_code = PHP_INT_MIN;
		$descriptor_spec = array(
			0 => array("pipe", "r"),   // stdin is a pipe that the child will read from
			1 => array("pipe", "w"),   // stdout is a pipe that the child will write to
			2 => array("pipe", "w")    // stderr is a pipe that the child will write to
		);
		flush();
		$buffer = "";
//		passthru($final_cmdline,$exit_code);
		ob_implicit_flush(true);ob_end_flush();
		$handle = proc_open($final_cmdline,$descriptor_spec,$pipes,realpath('./'),getenv());

		if (is_resource($handle)) {
			while ( $line = fgets( $pipes[2] ) ) {
				echo $line;
				$buffer .= $line;
				flush();
			}
		}
		proc_close($handle);
		$this->executeOutput = $buffer;
//		$this->executeOutput = ob_get_clean();
		//ob_end_clean();

		return $this->executeOutput;
	}
*/

	protected function execute( $cmd, $options = '', $dir = '' ) {

		self::initEnv();
		echo "Present working dir is " . getcwd() . "\n";
		if ( ! empty( $dir ) && is_dir( $dir ) ) {
			$options .= " -d $dir";
		}
		$final_cmdline = self::$composer_location . " $cmd $options";
		echo "Invoking '$final_cmdline'\n";

		$exit_code = PHP_INT_MIN;
		$descriptor_spec = array(
			0 => array("pipe", "r"),   // stdin is a pipe that the child will read from
			1 => array("pipe", "w"),   // stdout is a pipe that the child will write to
			2 => array("pipe", "w")    // stderr is a pipe that the child will write to
		);
		$buffer = "";
		$error_buffer = "";

		if ($fp = popen($final_cmdline,'r')) {
			while (!feof($fp)) {
				$line = fgets($fp);
				echo $line;
				$buffer .= $line;
				flush();
			}
			fclose($fp);
		}
/*		$process = proc_open($final_cmdline,$descriptor_spec,$pipes,realpath('./'),getenv());
		$line="";$error="";
		if (is_resource($process)) {
			while ( ($line = fgets( $pipes[1], 2048 )) !== FALSE) {
				$buffer .= $line;
				print $line;
				flush();
			}
			while ( ($error = fgets($pipes[2],2048)) !== FALSE) {
				$error_buffer .= $error;
				print $error;
				flush();
			}
		}
		fclose($pipes[0]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		$exit_code =		proc_close($process);*/
		$this->executeOutput = $buffer ."\/". $error_buffer;
//		$this->executeOutput = ob_get_clean();
		//ob_end_clean();

		return $this->executeOutput;
	}


	/**
	 * empties the case, ie, it deletes
	 * the entire composer home dir
	 */
	function emptyCache() {
		deleteDir( self::$composer_cache_dir );
	}

	/** it calls 'composer update' command with options
	 * in the directory specified
	 *
	 * @param string $options
	 * @param string $dir
	 *
	 * @return array
	 * @throws Exception
	 */
	function update( $options = '', $dir = '' ) {
		return $this->execute( 'update', $options, $dir );
	}


	/** gets the output of the composer launcher (not the composer executable)
	 * @return null
	 */
	function getExecuteOutput() {
		return $this->executeOutput;
	}

	/** invokes the 'composer install' command
	 * @return array
	 * @throws Exception
	 */
	function install() {
		return $this->execute( 'install' );
	}

	/** invokes the 'composer require' command
	 * with the package/dependency to be installed
	 *
	 * @param $package the name of the package
	 * @param string $options extra options of the command
	 * @param string $dir see execute method
	 *
	 * @return array see execute method
	 * @throws Exception see execute method
	 */
	function require_( $package, $options = '', $dir = '' ) {
		return $this->execute( "require", $package . " $options", $dir );
	}

	/** execute a 'composer search' with some specified term
	 *
	 * @param $q the search terms
	 *
	 * @return array output of command with the search results filtered
	 * @throws Exception
	 */
	function search( $q ) {
		$outputLines = $this->execute( "search", $q );
		if (is_string($outputLines)) {
			$outputLines = preg_split( '/\n/', $outputLines );
		}
		return $this->filterSearchResults( $q, $outputLines );
	}

	private function filterSearchResults( $q, $searchOutput ) {
		$filtered = array_filter( $searchOutput, function ( $line ) {
			return preg_match( "/[\w-]+?\/[\w-]+?\s.+/", $line );
		} );

		return array_values( $filtered );
	}


}
