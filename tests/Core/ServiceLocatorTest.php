<?php
/**
 * Unit tests for ServiceLocator
 *
 * @package PluginPulse\Library\Tests
 */

namespace PluginPulse\Library\Tests\Core;

use PHPUnit\Framework\TestCase;
use PluginPulse\Library\Core\ServiceLocator;

/**
 * Class ServiceLocatorTest
 */
class ServiceLocatorTest extends TestCase {

	/**
	 * Set up test environment
	 */
	protected function setUp(): void {
		parent::setUp();
		ServiceLocator::reset();
	}

	/**
	 * Test successful plugin registration
	 */
	public function test_plugin_registration_success() {
		$config = array(
			'slug'            => 'test-plugin',
			'name'            => 'Test Plugin',
			'version'         => '1.0.0',
			'library_version' => '1.0.0',
		);

		$result = ServiceLocator::register_plugin( $config );

		$this->assertTrue( $result );
		$this->assertTrue( ServiceLocator::is_plugin_registered( 'test-plugin' ) );
		$this->assertEquals( 1, ServiceLocator::get_plugin_count() );
	}

	/**
	 * Test registration fails with missing required fields
	 */
	public function test_registration_fails_with_missing_fields() {
		$config = array(
			'slug' => 'test-plugin',
			'name' => 'Test Plugin',
			// Missing version and library_version
		);

		$result = ServiceLocator::register_plugin( $config );

		$this->assertFalse( $result );
		$this->assertFalse( ServiceLocator::is_plugin_registered( 'test-plugin' ) );
	}

	/**
	 * Test duplicate registration is prevented
	 */
	public function test_duplicate_registration_prevented() {
		$config = array(
			'slug'            => 'test-plugin',
			'name'            => 'Test Plugin',
			'version'         => '1.0.0',
			'library_version' => '1.0.0',
		);

		$result1 = ServiceLocator::register_plugin( $config );
		$result2 = ServiceLocator::register_plugin( $config );

		$this->assertTrue( $result1 );
		$this->assertFalse( $result2 );
		$this->assertEquals( 1, ServiceLocator::get_plugin_count() );
	}

	/**
	 * Test get_plugin returns correct data
	 */
	public function test_get_plugin_returns_correct_data() {
		$config = array(
			'slug'            => 'test-plugin',
			'name'            => 'Test Plugin',
			'version'         => '1.0.0',
			'library_version' => '1.0.0',
			'icon'            => 'dashicons-admin-tools',
		);

		ServiceLocator::register_plugin( $config );
		$plugin = ServiceLocator::get_plugin( 'test-plugin' );

		$this->assertIsArray( $plugin );
		$this->assertEquals( 'test-plugin', $plugin['slug'] );
		$this->assertEquals( 'Test Plugin', $plugin['name'] );
		$this->assertEquals( '1.0.0', $plugin['version'] );
		$this->assertEquals( '1.0.0', $plugin['library_version'] );
		$this->assertEquals( 'dashicons-admin-tools', $plugin['icon'] );
	}

	/**
	 * Test get_plugin returns null for non-existent plugin
	 */
	public function test_get_plugin_returns_null_for_nonexistent() {
		$plugin = ServiceLocator::get_plugin( 'nonexistent-plugin' );

		$this->assertNull( $plugin );
	}

	/**
	 * Test get_all_plugins returns all registered plugins
	 */
	public function test_get_all_plugins() {
		$config1 = array(
			'slug'            => 'plugin-a',
			'name'            => 'Plugin A',
			'version'         => '1.0.0',
			'library_version' => '1.0.0',
		);

		$config2 = array(
			'slug'            => 'plugin-b',
			'name'            => 'Plugin B',
			'version'         => '2.0.0',
			'library_version' => '1.1.0',
		);

		ServiceLocator::register_plugin( $config1 );
		ServiceLocator::register_plugin( $config2 );

		$plugins = ServiceLocator::get_all_plugins();

		$this->assertCount( 2, $plugins );
		$this->assertArrayHasKey( 'plugin-a', $plugins );
		$this->assertArrayHasKey( 'plugin-b', $plugins );
	}

	/**
	 * Test menu registration tracking
	 */
	public function test_menu_registration_tracking() {
		$this->assertFalse( ServiceLocator::is_menu_registered() );

		ServiceLocator::set_menu_registered();

		$this->assertTrue( ServiceLocator::is_menu_registered() );
	}

	/**
	 * Test plugin with diagnostics callback
	 */
	public function test_plugin_with_diagnostics_callback() {
		$diagnostics_data = array(
			'php_version' => '7.4.0',
			'wp_version'  => '6.0.0',
		);

		$config = array(
			'slug'                 => 'test-plugin',
			'name'                 => 'Test Plugin',
			'version'              => '1.0.0',
			'library_version'      => '1.0.0',
			'diagnostics_callback' => function() use ( $diagnostics_data ) {
				return $diagnostics_data;
			},
		);

		ServiceLocator::register_plugin( $config );
		$plugin = ServiceLocator::get_plugin( 'test-plugin' );

		$this->assertIsCallable( $plugin['diagnostics_callback'] );
		$this->assertEquals( $diagnostics_data, call_user_func( $plugin['diagnostics_callback'] ) );
	}

	/**
	 * Test get_plugin_diagnostics
	 */
	public function test_get_plugin_diagnostics() {
		$diagnostics_data = array( 'test' => 'data' );

		$config = array(
			'slug'                 => 'test-plugin',
			'name'                 => 'Test Plugin',
			'version'              => '1.0.0',
			'library_version'      => '1.0.0',
			'diagnostics_callback' => function() use ( $diagnostics_data ) {
				return $diagnostics_data;
			},
		);

		ServiceLocator::register_plugin( $config );
		$result = ServiceLocator::get_plugin_diagnostics( 'test-plugin' );

		$this->assertEquals( $diagnostics_data, $result );
	}

	/**
	 * Test get_plugin_diagnostics returns null for plugin without callback
	 */
	public function test_get_plugin_diagnostics_returns_null_without_callback() {
		$config = array(
			'slug'            => 'test-plugin',
			'name'            => 'Test Plugin',
			'version'         => '1.0.0',
			'library_version' => '1.0.0',
		);

		ServiceLocator::register_plugin( $config );
		$result = ServiceLocator::get_plugin_diagnostics( 'test-plugin' );

		$this->assertNull( $result );
	}

	/**
	 * Test get_all_diagnostics aggregates data from multiple plugins
	 */
	public function test_get_all_diagnostics() {
		$config1 = array(
			'slug'                 => 'plugin-a',
			'name'                 => 'Plugin A',
			'version'              => '1.0.0',
			'library_version'      => '1.0.0',
			'diagnostics_callback' => function() {
				return array( 'data_a' => 'value_a' );
			},
		);

		$config2 = array(
			'slug'                 => 'plugin-b',
			'name'                 => 'Plugin B',
			'version'              => '2.0.0',
			'library_version'      => '1.1.0',
			'diagnostics_callback' => function() {
				return array( 'data_b' => 'value_b' );
			},
		);

		ServiceLocator::register_plugin( $config1 );
		ServiceLocator::register_plugin( $config2 );

		$diagnostics = ServiceLocator::get_all_diagnostics();

		$this->assertCount( 2, $diagnostics );
		$this->assertArrayHasKey( 'plugin-a', $diagnostics );
		$this->assertArrayHasKey( 'plugin-b', $diagnostics );
		$this->assertEquals( 'Plugin A', $diagnostics['plugin-a']['plugin_name'] );
		$this->assertEquals( 'Plugin B', $diagnostics['plugin-b']['plugin_name'] );
		$this->assertEquals( 'value_a', $diagnostics['plugin-a']['data']['data_a'] );
		$this->assertEquals( 'value_b', $diagnostics['plugin-b']['data']['data_b'] );
	}

	/**
	 * Test unregister_plugin
	 */
	public function test_unregister_plugin() {
		$config = array(
			'slug'            => 'test-plugin',
			'name'            => 'Test Plugin',
			'version'         => '1.0.0',
			'library_version' => '1.0.0',
		);

		ServiceLocator::register_plugin( $config );
		$this->assertTrue( ServiceLocator::is_plugin_registered( 'test-plugin' ) );

		$result = ServiceLocator::unregister_plugin( 'test-plugin' );

		$this->assertTrue( $result );
		$this->assertFalse( ServiceLocator::is_plugin_registered( 'test-plugin' ) );
	}

	/**
	 * Test unregister_plugin returns false for non-existent plugin
	 */
	public function test_unregister_nonexistent_plugin() {
		$result = ServiceLocator::unregister_plugin( 'nonexistent-plugin' );

		$this->assertFalse( $result );
	}

	/**
	 * Test reset functionality
	 */
	public function test_reset() {
		$config = array(
			'slug'            => 'test-plugin',
			'name'            => 'Test Plugin',
			'version'         => '1.0.0',
			'library_version' => '1.0.0',
		);

		ServiceLocator::register_plugin( $config );
		ServiceLocator::set_menu_registered();

		ServiceLocator::reset();

		$this->assertEquals( 0, ServiceLocator::get_plugin_count() );
		$this->assertFalse( ServiceLocator::is_menu_registered() );
		$this->assertEmpty( ServiceLocator::get_all_plugins() );
	}

	/**
	 * Test slug sanitization
	 */
	public function test_slug_sanitization() {
		$config = array(
			'slug'            => 'Test Plugin!@#',
			'name'            => 'Test Plugin',
			'version'         => '1.0.0',
			'library_version' => '1.0.0',
		);

		ServiceLocator::register_plugin( $config );

		// Slug should be sanitized
		$this->assertFalse( ServiceLocator::is_plugin_registered( 'Test Plugin!@#' ) );
		$this->assertTrue( ServiceLocator::is_plugin_registered( 'testplugin' ) );
	}
}