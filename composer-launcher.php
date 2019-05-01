<?php

if (composerPharExists())
	include_once "phar:///" . __DIR__ . "/composer.phar/src/bootstrap.php";
else throw new \RuntimeException("Composer.phar is not present !");

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


class FacadeIO extends ConsoleIO {

	private $startTime;
	private $verbosityMap;

	public function writeError( $messages, $newline = true, $verbosity = self::NORMAL ) {
		$this->doWrite( $messages, $newline, true, $verbosity );
	}


	private function doWrite( $messages, $newline, $stderr, $verbosity ) {
		$sfVerbosity = $this->verbosityMap[ $verbosity ];
		if ( $sfVerbosity > $this->output->getVerbosity() ) {
			return;
		}


		if ( OutputInterface::VERBOSITY_QUIET === 0 ) {
			$sfVerbosity = OutputInterface::OUTPUT_NORMAL;
		}

		if ( null !== $this->startTime ) {
			$memoryUsage = memory_get_usage() / 1024 / 1024;
			$timeSpent   = microtime( true ) - $this->startTime;
			$messages    = array_map( function ( $message ) use ( $memoryUsage, $timeSpent ) {
				return sprintf( '[%.1fMiB/%.2fs] %s', $memoryUsage, $timeSpent, $message );
			}, (array) $messages );
		}

		if ( true === $stderr && $this->output instanceof ConsoleOutputInterface ) {
			$this->output->getErrorOutput()->write( $messages, $newline, $sfVerbosity );
			$this->lastMessageErr = implode( $newline ? "\n" : '', (array) $messages );

			return;
		}

		$this->output->write( $messages, $newline, $sfVerbosity );
		$this->lastMessage = implode( $newline ? "\n" : '', (array) $messages );
	}

}

class FacadeOutput extends ConsoleOutput {

	var $buffer;
	var $formatter;
	private $stderr;
	private $stream;


	public function __construct( $verbosity = self::VERBOSITY_NORMAL, $decorated = null, OutputFormatterInterface $formatter = null ) {
		$this->stream = $this->openOutputStream();
		parent::__construct( $this->stream, $verbosity, $decorated, $formatter );

		$this->formatter = $formatter;

		$actualDecorated = $this->isDecorated();
		$this->stdout    = new StreamOutput( $this->openOutputStream(), $verbosity, $decorated, $this->getFormatter() );
//		$this->stderr    = new StreamOutput( $this->openErrorStream(), $verbosity, $decorated, $this->getFormatter() );


		if ( null === $decorated ) {
			$this->setDecorated( $actualDecorated && $this->stdout->isDecorated() );
		}

		$this->buffer = array();
	}

	private function openOutputStream() {
		$outputStream = $this->hasStdoutSupport() ? 'php://stdout' : 'php://output';

		return @fopen( $outputStream, 'w' ) ?: fopen( 'php://output', 'w' );
	}

	public function getBuffer() {
		return $this->buffer;
	}

	public function write( $messages, $newline = false, $options = self::OUTPUT_NORMAL ) {
		$messages = (array) $messages;


		$this->buffer = array_merge( $this->buffer, $messages );

		$types = self::OUTPUT_NORMAL | self::OUTPUT_RAW | self::OUTPUT_PLAIN;
		$type  = $types & $options ?: self::OUTPUT_NORMAL;

		$verbosities = self::VERBOSITY_QUIET | self::VERBOSITY_NORMAL | self::VERBOSITY_VERBOSE | self::VERBOSITY_VERY_VERBOSE | self::VERBOSITY_DEBUG;
		$verbosity   = $verbosities & $options ?: self::VERBOSITY_NORMAL;

		if ( $verbosity > $this->getVerbosity() ) {
			return;
		}

		foreach ( $messages as $message ) {
			switch ( $type ) {
				case OutputInterface::OUTPUT_NORMAL:
					$message = $this->formatter->format( $message );
					break;
				case OutputInterface::OUTPUT_RAW:
					break;
				case OutputInterface::OUTPUT_PLAIN:
					$message = strip_tags( $this->formatter->format( $message ) );
					break;
			}

			$this->doWrite( $message, $newline );
		}
	}

	protected function doWrite( $message, $newline ) {
		if ( $newline ) {
			$message .= PHP_EOL;
		}


		if ( false === @fwrite( $this->stream, $message ) ) {

			throw new RuntimeException( 'Unable to write output.' );
		}


		fflush( $this->stream );
	}

	private function openErrorStream() {
		$errorStream = $this->hasStderrSupport() ? 'php://stderr' : 'php://output';

		return fopen( $errorStream, 'w' );
	}


	/*	public function write( $messages, $newline = false, $options = 0 ) {
			if (is_array($messages)) {
				print join(" ",$messages);
			} else if (is_string($messages)) {
				print $messages;
			} else {
				var_dump($messages);
			}
		}

		public function writeln( $messages, $options = 0 ) {
			$this->write($messages . "\n");
		}*/


}

class FacadeApplication extends Application {
	var $output = null;
	private $catchExceptions = true;
	private $hasPluginCommands = false;
	private $disablePluginsByDefault = false;

	/**
	 * @return array
	 */
	public function getBuffer() {
		if ( ! empty( $this->output ) ) {
			return $this->output->getBuffer();
		} else {
			return null;
		}
	}

	public function run( InputInterface $input = null, OutputInterface $output = null ) {

		/*		set_error_handler(function ($errno , $errstr, $errfile, $errline, $errcontext ) {
					$this->output[] = $errstr;
				});*/


		putenv( "COMPOSER_DISABLE_XDEBUG_WARN=1" );
		putenv( "COMPOSER_ALLOW_XDEBUG=0" );
		if ( null === $input ) {
			$input = new ArgvInput();
		}

		if ( null === $this->output ) {
			$styles    = self::createAdditionalStyles();
			$formatter = new OutputFormatter( false, $styles );


			$this->output = new FacadeOutput( ConsoleOutput::VERBOSITY_NORMAL, null, $formatter );
		}

		$this->configureIO( $input, $this->output );

		try {
			$e        = null;
			$exitCode = $this->doRun( $input, $this->output );
		} catch ( \Exception $e ) {
		}

		if ( null !== $e ) {
			if ( ! $this->catchExceptions ) {
				throw $e;
			}

			if ( $this->output instanceof ConsoleOutputInterface ) {
				$this->renderException( $e, $this->output->getErrorOutput() );
			} else {
				$this->renderException( $e, $this->output );
			}

			$exitCode = $this->getExitCodeForThrowable( $e );
		}

		if ( $exitCode > 255 ) {
			$exitCode = 255;
		}


		//$this->output = $output->getOutput();

//		print join( "\n", $output->getOutput() );


		return $exitCode;
	}

	public static function createAdditionalStyles() {
		return array(
			'highlight' => new OutputFormatterStyle( 'red' ),
			'warning'   => new OutputFormatterStyle( 'black', 'yellow' ),
		);
	}

	public function doRun( InputInterface $input, OutputInterface $output ) {
		$this->disablePluginsByDefault = $input->hasParameterOption( '--no-plugins' );

		/*		$io = $this->io = new ConsoleIO($input, $output, new HelperSet(array(
					new QuestionHelper(),
				)));*/

		$io = $this->io = new FacadeIO( $input, $output, new HelperSet( array(
			new QuestionHelper(),
		) ) );

		ErrorHandler::register( $io );

		if ( $input->hasParameterOption( '--no-cache' ) ) {
			$io->writeError( 'Disabling cache usage', true, IOInterface::DEBUG );
			putenv( 'COMPOSER_CACHE_DIR=' . ( Platform::isWindows() ? 'nul' : '/dev/null' ) );
		}


		if ( $newWorkDir = $this->getNewWorkingDir( $input ) ) {
			$oldWorkingDir = getcwd();
			chdir( $newWorkDir );
			$io->writeError( 'Changed CWD to ' . getcwd(), true, IOInterface::DEBUG );
		}


		$commandName = '';
		if ( $name = $this->getCommandName( $input ) ) {
			try {
				$commandName = $this->find( $name )->getName();
			} catch ( CommandNotFoundException $e ) {

				$commandName = false;
			} catch ( \InvalidArgumentException $e ) {
			}
		}


		if ( $io->isInteractive() && ! $newWorkDir && ! in_array( $commandName, array(
				'',
				'list',
				'init',
				'about',
				'help',
				'diagnose',
				'self-update',
				'global',
				'create-project'
			), true ) && ! file_exists( Factory::getComposerFile() ) ) {
			$dir  = dirname( getcwd() );
			$home = realpath( getenv( 'HOME' ) ?: getenv( 'USERPROFILE' ) ?: '/' );


			while ( dirname( $dir ) !== $dir && $dir !== $home ) {
				if ( file_exists( $dir . '/' . Factory::getComposerFile() ) ) {
					if ( $io->askConfirmation( '<info>No composer.json in current directory, do you want to use the one at ' . $dir . '?</info> [<comment>Y,n</comment>]? ', true ) ) {
						$oldWorkingDir = getcwd();
						chdir( $dir );
					}
					break;
				}
				$dir = dirname( $dir );
			}
		}

		if ( ! $this->disablePluginsByDefault && ! $this->hasPluginCommands && 'global' !== $commandName ) {
			try {
				foreach ( $this->getPluginCommands() as $command ) {
					if ( $this->has( $command->getName() ) ) {
						$io->writeError( '<warning>Plugin command ' . $command->getName() . ' (' . get_class( $command ) . ') would override a Composer command and has been skipped</warning>' );
					} else {
						$this->add( $command );
					}
				}
			} catch ( NoSslException $e ) {

			}

			$this->hasPluginCommands = true;
		}


		$isProxyCommand = false;
		if ( $name = $this->getCommandName( $input ) ) {
			try {
				$command        = $this->find( $name );
				$commandName    = $command->getName();
				$isProxyCommand = ( $command instanceof Command\BaseCommand && $command->isProxyCommand() );
			} catch ( \InvalidArgumentException $e ) {
			}
		}

		if ( ! $isProxyCommand ) {
			$io->writeError( sprintf(
				'Running %s (%s) with %s on %s',
				Composer::getVersion(),
				Composer::RELEASE_DATE,
				defined( 'HHVM_VERSION' ) ? 'HHVM ' . HHVM_VERSION : 'PHP ' . PHP_VERSION,
				function_exists( 'php_uname' ) ? php_uname( 's' ) . ' / ' . php_uname( 'r' ) : 'Unknown OS'
			), true, IOInterface::DEBUG );

			if ( PHP_VERSION_ID < 50302 ) {
				$io->writeError( '<warning>Composer only officially supports PHP 5.3.2 and above, you will most likely encounter problems with your PHP ' . PHP_VERSION . ', upgrading is strongly recommended.</warning>' );
			}

			if ( extension_loaded( 'xdebug' ) && ! getenv( 'COMPOSER_DISABLE_XDEBUG_WARN' ) ) {
				$io->writeError( '<warning>You are running composer with xdebug enabled. This has a major impact on runtime performance. See https://getcomposer.org/xdebug</warning>' );
			}

			if ( defined( 'COMPOSER_DEV_WARNING_TIME' ) && $commandName !== 'self-update' && $commandName !== 'selfupdate' && time() > COMPOSER_DEV_WARNING_TIME ) {
				$io->writeError( sprintf( '<warning>Warning: This development build of composer is over 60 days old. It is recommended to update it by running "%s self-update" to get the latest version.</warning>', $_SERVER['PHP_SELF'] ) );
			}

			if ( getenv( 'COMPOSER_NO_INTERACTION' ) ) {
				$input->setInteractive( false );
			}

			if ( ! Platform::isWindows() && function_exists( 'exec' ) && ! getenv( 'COMPOSER_ALLOW_SUPERUSER' ) ) {
				if ( function_exists( 'posix_getuid' ) && posix_getuid() === 0 ) {
					if ( $commandName !== 'self-update' && $commandName !== 'selfupdate' ) {
						$io->writeError( '<warning>Do not run Composer as root/super user! See https://getcomposer.org/root for details</warning>' );
					}
					if ( $uid = (int) getenv( 'SUDO_UID' ) ) {


						Silencer::call( 'exec', "sudo -u \\#{$uid} sudo -K > /dev/null 2>&1" );
					}
				}

				Silencer::call( 'exec', 'sudo -K > /dev/null 2>&1' );
			}


			Silencer::call( function () use ( $io ) {
				$tempfile = sys_get_temp_dir() . '/temp-' . md5( microtime() );
				if ( ! ( file_put_contents( $tempfile, __FILE__ ) && ( file_get_contents( $tempfile ) == __FILE__ ) && unlink( $tempfile ) && ! file_exists( $tempfile ) ) ) {
					$io->writeError( sprintf( '<error>PHP temp directory (%s) does not exist or is not writable to Composer. Set sys_temp_dir in your php.ini</error>', sys_get_temp_dir() ) );
				}
			} );


			$file = Factory::getComposerFile();
			if ( is_file( $file ) && is_readable( $file ) && is_array( $composer = json_decode( file_get_contents( $file ), true ) ) ) {
				if ( isset( $composer['scripts'] ) && is_array( $composer['scripts'] ) ) {
					foreach ( $composer['scripts'] as $script => $dummy ) {
						if ( ! defined( 'Composer\Script\ScriptEvents::' . str_replace( '-', '_', strtoupper( $script ) ) ) ) {
							if ( $this->has( $script ) ) {
								$io->writeError( '<warning>A script named ' . $script . ' would override a Composer command and has been skipped</warning>' );
							} else {
								$description = null;

								if ( isset( $composer['scripts-descriptions'][ $script ] ) ) {
									$description = $composer['scripts-descriptions'][ $script ];
								}

								$this->add( new ScriptAliasCommand( $script, $description ) );
							}
						}
					}
				}
			}
		}

		try {
			if ( $input->hasParameterOption( '--profile' ) ) {
				$startTime = microtime( true );
				$this->io->enableDebugging( $startTime );
			}

			//$result = parent::doRun( $input, $output );
			$result = SymfonyApplication::doRun( $input, $output );

			if ( isset( $oldWorkingDir ) ) {
				chdir( $oldWorkingDir );
			}

			if ( isset( $startTime ) ) {
				$io->writeError( '<info>Memory usage: ' . round( memory_get_usage() / 1024 / 1024, 2 ) . 'MiB (peak: ' . round( memory_get_peak_usage() / 1024 / 1024, 2 ) . 'MiB), time: ' . round( microtime( true ) - $startTime, 2 ) . 's' );
			}

			restore_error_handler();

			return $result;
		} catch ( ScriptExecutionException $e ) {
			return $e->getCode();
		} catch ( \Exception $e ) {
			$this->hintCommonErrors( $e );
			restore_error_handler();
			throw $e;
		}
	}

	private function getNewWorkingDir( InputInterface $input ) {
		$workingDir = $input->getParameterOption( array( '--working-dir', '-d' ) );
		if ( false !== $workingDir && ! is_dir( $workingDir ) ) {
			throw new \RuntimeException( 'Invalid working directory specified, ' . $workingDir . ' does not exist.' );
		}

		return $workingDir;
	}

	private function getPluginCommands() {
		$commands = array();

		$composer = $this->getComposer( false, false );
		if ( null === $composer ) {
			$composer = Factory::createGlobal( $this->io, false );
		}

		if ( null !== $composer ) {
			$pm = $composer->getPluginManager();
			foreach (
				$pm->getPluginCapabilities( 'Composer\Plugin\Capability\CommandProvider', array(
					'composer' => $composer,
					'io'       => $this->io
				) ) as $capability
			) {
				$newCommands = $capability->getCommands();
				if ( ! is_array( $newCommands ) ) {
					throw new \UnexpectedValueException( 'Plugin capability ' . get_class( $capability ) . ' failed to return an array from getCommands' );
				}
				foreach ( $newCommands as $command ) {
					if ( ! $command instanceof Command\BaseCommand ) {
						throw new \UnexpectedValueException( 'Plugin capability ' . get_class( $capability ) . ' returned an invalid value, we expected an array of Composer\Command\BaseCommand objects' );
					}
				}
				$commands = array_merge( $commands, $newCommands );
			}
		}

		return $commands;
	}

	function hintCommonErrors( $exception ) {
		$io = $this->getIO();

		Silencer::suppress();
		try {
			$composer = $this->getComposer( false, true );
			if ( $composer ) {
				$config = $composer->getConfig();

				$minSpaceFree = 1024 * 1024;
				if ( ( ( $df = disk_free_space( $dir = $config->get( 'home' ) ) ) !== false && $df < $minSpaceFree )
				     || ( ( $df = disk_free_space( $dir = $config->get( 'vendor-dir' ) ) ) !== false && $df < $minSpaceFree )
				     || ( ( $df = disk_free_space( $dir = sys_get_temp_dir() ) ) !== false && $df < $minSpaceFree )
				) {
					$io->writeError( '<error>The disk hosting ' . $dir . ' is full, this may be the cause of the following exception</error>', true, IOInterface::QUIET );
				}
			}
		} catch ( \Exception $e ) {
		}
		Silencer::restore();

		if ( Platform::isWindows() && false !== strpos( $exception->getMessage(), 'The system cannot find the path specified' ) ) {
			$io->writeError( '<error>The following exception may be caused by a stale entry in your cmd.exe AutoRun</error>', true, IOInterface::QUIET );
			$io->writeError( '<error>Check https://getcomposer.org/doc/articles/troubleshooting.md#-the-system-cannot-find-the-path-specified-windows- for details</error>', true, IOInterface::QUIET );
		}

		if ( false !== strpos( $exception->getMessage(), 'fork failed - Cannot allocate memory' ) ) {
			$io->writeError( '<error>The following exception is caused by a lack of memory or swap, or not having swap configured</error>', true, IOInterface::QUIET );
			$io->writeError( '<error>Check https://getcomposer.org/doc/articles/troubleshooting.md#proc-open-fork-failed-errors for details</error>', true, IOInterface::QUIET );
		}
	}

	private function getExitCodeForThrowable( $throwable ) {
		$exitCode = $throwable->getCode();
		if ( is_numeric( $exitCode ) ) {
			$exitCode = (int) $exitCode;
			if ( 0 === $exitCode ) {
				$exitCode = 1;
			}
		} else {
			$exitCode = 1;
		}

		return $exitCode;
	}
}

/**
 * Class ComposerLauncher
 * Main class that wraps the calls to the composer executable
 */
class ComposerLauncher {

	static $composer_cache_dir = WP_CONTENT_DIR . '/composer-home';
	var $application;
	var $last_output_lines_count = 0;
	var $executeOutput = null;


	/**
	 * ComposerLauncher constructor.
	 */
	public function __construct() {
		self::init();
		$this->application = new FacadeApplication();
	}


	/**
	 * verifies if the homedir was already initialized, if not, it
	 * throws an exception
	 */
	function init() {

		$composer_cache = self::$composer_cache_dir;

		if ( ! self::doesGlobalComposerJsonExists() ) {

			throw new \RuntimeException( "COMPOSER_HOME was not initialized ! Initialize it!" );
		}

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
		$application = new FacadeApplication();
		self::initEnv();
		$application->setAutoExit( false );
		$exitCode = $application->run( new StringInput( "$cmd $options" ) );
		$output   = $application->getBuffer();

		return $output;
	}

	/**
	 * sets some environment variables and overrides PHP configuration
	 */
	static function initEnv() {
		putenv( "COMPOSER_HOME=" . self::$composer_cache_dir );
		ini_set( 'memory_limit', - 1 );

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

	/** main invoker of composer
	 *
	 * @param $cmd the composer command (search, update, require, selfupdate, ...)
	 * @param string $options extra options to be passed to the command
	 * @param string $dir the directory in which the command should be issued
	 *
	 * @return array lines of the output of command
	 * @throws Exception
	 */
	protected function execute( $cmd, $options = '', $dir = '' ) {
		ob_start();
		self::initEnv();
		echo "Present working dir is " . getcwd() . "\n";
		if ( ! empty( $dir ) && is_dir( $dir ) ) {
			$options .= " -d $dir";
		}
		$final_cmdline = "$cmd $options";
		echo "Invoking composer with '$final_cmdline'";
		$input = new StringInput( $final_cmdline );


		$this->application->setAutoExit( false );
		$exitCode            = $this->application->run( $input );
		$output              = $this->application->getBuffer();
		$this->executeOutput = ob_get_clean();

		return $output;
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

		return $this->filterSearchResults( $q, $outputLines );
	}

	private function filterSearchResults( $q, $searchOutput ) {
		$filtered = array_filter( $searchOutput, function ( $line ) {
			return preg_match( "/[\w-]+?\/[\w-]+?\s.+/", $line );
		} );

		return array_values( $filtered );
	}

	/** returns the most current output of composer execution
	 * TODO not working at this time
	 * @return string
	 */
	public function lastOutput() {
		if ( empty( $this->application ) ) {
			return "Null Composer instance";
		}
		$output_r = $this->application->getBuffer();
		if ( empty ( $output_r ) ) {
			return "Buffer null or empty!";
		}
		$curr_output_lines_count       = count( $output_r );
		$new_output                    = array_slice( $output_r, $this->last_output_lines_count, $curr_output_lines_count );
		$this->last_output_lines_count = $curr_output_lines_count;

		return join( "\n", $new_output );
	}

}
