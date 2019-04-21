<?php

require_once dirname( __DIR__ ) . '/wpcm-helpers.php';

use PHPUnit\Framework\TestCase;

class WpcmHelpersTest extends TestCase {
	public function testCheckForDirectoriesWithComposer() {
		$curDir = realpath( "../../../.." );
		echo "Current directory $curDir\n";
		$dirList = composer_json_check( $curDir );
		var_dump( $dirList );
		$this->assertNotEmpty( $dirList );
	}


}
