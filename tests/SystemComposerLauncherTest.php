<?php


use PHPUnit\Framework\TestCase;

require dirname( __DIR__ ) . "/system-composer-launcher.php";
require dirname( __DIR__ ) . "/wpcm-helpers.php";

class SystemComposerLauncherTest extends TestCase {


	protected function setUp() {
		if ( ! defined( 'WP_CONTENT_DIR' ) ) {
			define( 'WP_CONTENT_DIR', __DIR__ );
		}
		parent::setUp();
	}

	protected function tearDown() {
		parent::tearDown();
	}

	public function testDetectComposer() {
		$composer_location = SystemComposerLauncher::detect_composer();
		echo $composer_location;
		$this->assertEquals($composer_location,'/usr/bin/composer');
	}

	public function testDoesGlobalComposerJsonExists() {
		SystemComposerLauncher::init();
		$this->assertTrue (SystemComposerLauncher::doesGlobalComposerJsonExists());
	}

	public function testSearch() {
		$launcher = new SystemComposerLauncher();
		$output   = $launcher->search( 'fakery' );
		//echo "\n---SEARCH RESULTS ---\n";
		//var_dump( $output );
		$this->assertNotEmpty( $output );
	}


		public function testRequire() {
				$launcher = new SystemComposerLauncher();
				$output = $launcher->require_('mockery/mockery');

				print "-- OUTPUT FROM COMPOSER --\n";
				print  $output;

				$this->assertDirectoryExists('vendor');
			}

		public function testUpdate() {
			$launcher = new SystemComposerLauncher();
			$output = $launcher->update();
			//echo $output;
			$this->assertDirectoryExists('vendor');
			$this->assertFileExists('composer.lock');
			ob_start();
			//deleteDir('vendor');
			//unlink('composer.lock');
			ob_end_clean();
		}

/*	protected function deleteAllComposerGeneratedFiles() {
		chdir( __DIR__ );
		deleteDir( 'vendor' );
		deleteDir( 'folder1/vendor' );
		deleteDir( 'folder2/vendor' );

	}*/

	/*public function testUpdateSubdirs() {
		chdir('folder1');
		echo("On " . getcwd(). "/n");
		ComposerLauncher::update();
		$this->assertDirectoryExists('vendor');
		chdir('../folder2');
		echo("On " . getcwd() . "/n");
		ComposerLauncher::update();
		$this->assertDirectoryExists('vendor');
	}*/
}
