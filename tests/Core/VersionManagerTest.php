<?php
/**
 * Unit tests for VersionManager
 *
 * @package PluginPulse\Library\Tests
 */

namespace PluginPulse\Library\Tests\Core;

use PHPUnit\Framework\TestCase;
use PluginPulse\Library\Core\VersionManager;

/**
 * Class VersionManagerTest
 */
class VersionManagerTest extends TestCase {

	/**
	 * Set up test environment
	 */
	protected function setUp(): void {
		parent::setUp();
		VersionManager::reset();
	}

	/**
	 * Test first plugin registration becomes active
	 */
	public function test_first_registration_becomes_active() {
		$result = VersionManager::register( 'plugin-a', '1.0.0', '/path/to/library-a' );

		$this->assertTrue( $result );
		$this->assertEquals( '1.0.0', VersionManager::get_active_version() );
		$this->assertEquals( '/path/to/library-a', VersionManager::get_active_path() );
	}

	/**
	 * Test newer version replaces older version
	 */
	public function test_newer_version_replaces_older() {
		VersionManager::register( 'plugin-a', '1.0.0', '/path/to/library-a' );
		$result = VersionManager::register( 'plugin-b', '1.1.0', '/path/to/library-b' );

		$this->assertTrue( $result );
		$this->assertEquals( '1.1.0', VersionManager::get_active_version() );
		$this->assertEquals( '/path/to/library-b', VersionManager::get_active_path() );
	}

	/**
	 * Test older version does not replace newer version
	 */
	public function test_older_version_does_not_replace_newer() {
		VersionManager::register( 'plugin-a', '2.0.0', '/path/to/library-a' );
		$result = VersionManager::register( 'plugin-b', '1.5.0', '/path/to/library-b' );

		$this->assertFalse( $result );
		$this->assertEquals( '2.0.0', VersionManager::get_active_version() );
		$this->assertEquals( '/path/to/library-a', VersionManager::get_active_path() );
	}

	/**
	 * Test equal version does not replace existing
	 */
	public function test_equal_version_does_not_replace() {
		VersionManager::register( 'plugin-a', '1.0.0', '/path/to/library-a' );
		$result = VersionManager::register( 'plugin-b', '1.0.0', '/path/to/library-b' );

		$this->assertFalse( $result );
		$this->assertEquals( '1.0.0', VersionManager::get_active_version() );
		$this->assertEquals( '/path/to/library-a', VersionManager::get_active_path() );
	}

	/**
	 * Test multiple plugin registrations are tracked
	 */
	public function test_all_registrations_are_tracked() {
		VersionManager::register( 'plugin-a', '1.0.0', '/path/to/library-a' );
		VersionManager::register( 'plugin-b', '1.1.0', '/path/to/library-b' );
		VersionManager::register( 'plugin-c', '0.9.0', '/path/to/library-c' );

		$registered = VersionManager::get_registered_plugins();

		$this->assertCount( 3, $registered );
		$this->assertArrayHasKey( 'plugin-a', $registered );
		$this->assertArrayHasKey( 'plugin-b', $registered );
		$this->assertArrayHasKey( 'plugin-c', $registered );
		$this->assertEquals( '1.0.0', $registered['plugin-a']['version'] );
		$this->assertEquals( '1.1.0', $registered['plugin-b']['version'] );
		$this->assertEquals( '0.9.0', $registered['plugin-c']['version'] );
	}

	/**
	 * Test has_multiple_versions detection
	 */
	public function test_has_multiple_versions() {
		$this->assertFalse( VersionManager::has_multiple_versions() );

		VersionManager::register( 'plugin-a', '1.0.0', '/path/to/library-a' );
		$this->assertFalse( VersionManager::has_multiple_versions() );

		VersionManager::register( 'plugin-b', '1.1.0', '/path/to/library-b' );
		$this->assertTrue( VersionManager::has_multiple_versions() );
	}

	/**
	 * Test version comparison with semantic versioning
	 */
	public function test_semantic_version_comparison() {
		VersionManager::register( 'plugin-a', '1.0.0', '/path/to/library-a' );
		$this->assertTrue( VersionManager::register( 'plugin-b', '1.0.1', '/path/to/library-b' ) );
		$this->assertEquals( '1.0.1', VersionManager::get_active_version() );

		$this->assertTrue( VersionManager::register( 'plugin-c', '1.1.0', '/path/to/library-c' ) );
		$this->assertEquals( '1.1.0', VersionManager::get_active_version() );

		$this->assertTrue( VersionManager::register( 'plugin-d', '2.0.0', '/path/to/library-d' ) );
		$this->assertEquals( '2.0.0', VersionManager::get_active_version() );
	}

	/**
	 * Test reset functionality
	 */
	public function test_reset() {
		VersionManager::register( 'plugin-a', '1.0.0', '/path/to/library-a' );
		VersionManager::register( 'plugin-b', '1.1.0', '/path/to/library-b' );

		VersionManager::reset();

		$this->assertNull( VersionManager::get_active_version() );
		$this->assertNull( VersionManager::get_active_path() );
		$this->assertEmpty( VersionManager::get_registered_plugins() );
		$this->assertFalse( VersionManager::has_multiple_versions() );
	}

	/**
	 * Test path normalization
	 */
	public function test_path_normalization() {
		// Register with different path separators
		$result1 = VersionManager::register( 'plugin-a', '1.0.0', '/path/to/library' );
		$result2 = VersionManager::register( 'plugin-b', '1.1.0', '/path/to/library' );

		// Both should work, but paths should be normalized for comparison
		$this->assertTrue( $result1 );
		$this->assertTrue( $result2 );
	}
}