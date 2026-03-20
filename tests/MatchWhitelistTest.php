<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for matchWhitelist function in plusadController
 */
class MatchWhitelistTest extends TestCase
{
	protected function setUp(): void
	{
		Context::reset();
		TestHelper::reset();
		ModuleObject::resetInstances();
	}

	/**
	 * Helper to create a plusadController instance
	 */
	private function createController(): plusadController
	{
		return new plusadController();
	}

	/**
	 * Test: Empty domain_list → whitelist disabled
	 * When no domains are configured, non-empty URLs are blocked.
	 * Empty URL always passes.
	 */
	public function testEmptyDomainListDisablesWhitelist(): void
	{
		$controller = $this->createController();
		$controller->module_info->domain_list = '';

		// Empty URL always returns true
		$this->assertTrue($controller->matchWhitelist(''));

		// Non-empty URLs are blocked when whitelist is empty
		$this->assertFalse($controller->matchWhitelist('http://example.com'));
		$this->assertFalse($controller->matchWhitelist('https://example.com'));
		$this->assertFalse($controller->matchWhitelist('http://test.org'));
	}

	/**
	 * Test: Null domain_list behaves same as empty
	 */
	public function testNullDomainListDisablesWhitelist(): void
	{
		$controller = $this->createController();
		$controller->module_info->domain_list = null;

		$this->assertTrue($controller->matchWhitelist(''));
		$this->assertFalse($controller->matchWhitelist('http://example.com'));
	}

	/**
	 * Test: Single domain (example.com)
	 * Only URLs on example.com should be allowed.
	 * Matching should be domain-only, path is irrelevant.
	 */
	public function testSingleDomainWhitelist(): void
	{
		$controller = $this->createController();
		$controller->module_info->domain_list = 'example.com';

		// Matching domain passes
		$this->assertTrue($controller->matchWhitelist('http://example.com'));
		$this->assertTrue($controller->matchWhitelist('https://example.com'));

		// Matching domain with various paths passes (domain-only comparison)
		$this->assertTrue($controller->matchWhitelist('http://example.com/'));
		$this->assertTrue($controller->matchWhitelist('http://example.com/path/to/page'));
		$this->assertTrue($controller->matchWhitelist('https://example.com/some/path'));
		$this->assertTrue($controller->matchWhitelist('http://example.com/page?query=value'));
		$this->assertTrue($controller->matchWhitelist('https://example.com/page#anchor'));

		// Non-matching domain fails
		$this->assertFalse($controller->matchWhitelist('http://other.com'));
		$this->assertFalse($controller->matchWhitelist('https://notexample.com'));
		$this->assertFalse($controller->matchWhitelist('http://example.org'));
	}

	/**
	 * Test: Two domains separated by \n (example.com and example1.com)
	 * Both domains should be allowed, others should be blocked.
	 */
	public function testMultipleDomainWhitelist(): void
	{
		$controller = $this->createController();
		$controller->module_info->domain_list = "example.com\nexample1.com";

		// First domain passes
		$this->assertTrue($controller->matchWhitelist('http://example.com'));
		$this->assertTrue($controller->matchWhitelist('https://example.com'));
		$this->assertTrue($controller->matchWhitelist('http://example.com/path'));

		// Second domain passes
		$this->assertTrue($controller->matchWhitelist('http://example1.com'));
		$this->assertTrue($controller->matchWhitelist('https://example1.com'));
		$this->assertTrue($controller->matchWhitelist('http://example1.com/path'));

		// Non-matching domain fails
		$this->assertFalse($controller->matchWhitelist('http://other.com'));
		$this->assertFalse($controller->matchWhitelist('https://example2.com'));
		$this->assertFalse($controller->matchWhitelist('http://notexample.com'));
	}

	/**
	 * Test: Domain matching is path-independent
	 * Regardless of the path, only the domain should matter.
	 */
	public function testDomainMatchingIgnoresPath(): void
	{
		$controller = $this->createController();
		$controller->module_info->domain_list = 'example.com';

		// Same domain with different paths all pass
		$this->assertTrue($controller->matchWhitelist('http://example.com/'));
		$this->assertTrue($controller->matchWhitelist('http://example.com/page'));
		$this->assertTrue($controller->matchWhitelist('http://example.com/deep/path/to/resource'));
		$this->assertTrue($controller->matchWhitelist('http://example.com/page?query=value'));
		$this->assertTrue($controller->matchWhitelist('https://example.com/page#anchor'));

		// Different domain with various paths all fail
		$this->assertFalse($controller->matchWhitelist('http://other.com/'));
		$this->assertFalse($controller->matchWhitelist('http://other.com/page'));
		$this->assertFalse($controller->matchWhitelist('http://other.com/deep/path'));
	}

	/**
	 * Test: \r\n line endings are handled correctly
	 */
	public function testWindowsLineEndings(): void
	{
		$controller = $this->createController();
		$controller->module_info->domain_list = "example.com\r\nexample1.com";

		$this->assertTrue($controller->matchWhitelist('http://example.com'));
		$this->assertTrue($controller->matchWhitelist('http://example1.com'));
		$this->assertFalse($controller->matchWhitelist('http://other.com'));
	}
}
