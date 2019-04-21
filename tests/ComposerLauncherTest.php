<?php


use PHPUnit\Framework\TestCase;

require dirname( __DIR__ ) . "/composer-launcher.php";
require dirname( __DIR__ ) . "/wpcm-helpers.php";

class ComposerLauncherTest extends TestCase {

	public function testSearch() {
		$launcher = new ComposerLauncher();
		$output   = $launcher->search( 'fakery' );
		echo "\n---SEARCH RESULTS ---\n";
		print_r( $output );
		$this->assertNotEmpty( $output );
	}

	protected function setUp() {
		if ( ! defined( 'WP_CONTENT_DIR' ) ) {
			define( 'WP_CONTENT_DIR', __DIR__ );
		}
		parent::setUp();
//		ob_start();
	}

	protected function tearDown() {
		parent::tearDown();
	}

	/*	public function testRequire() {
			$launcher = new ComposerLauncher();
			$output = $launcher->require_('mockery/mockery');

			print "-- OUTPUT FROM COMPOSER --\n";
			print join( "\n", $output );

			$this->assertDirectoryExists('vendor');
		}*/

	/*	public function testUpdate() {
			$launcher = new ComposerLauncher();
			$output = $launcher->update();
			$this->assertDirectoryExists('vendor');
			$this->assertFileExists('composer.lock');
			ob_start();
			//deleteDir('vendor');
			//unlink('composer.lock');
			ob_end_clean();
		}*/

	protected function deleteAllComposerGeneratedFiles() {
		chdir( __DIR__ );
		deleteDir( 'vendor' );
		deleteDir( 'folder1/vendor' );
		deleteDir( 'folder2/vendor' );

	}

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
