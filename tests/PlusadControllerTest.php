<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for procPlusadwrite in plusadController
 */
class PlusadControllerTest extends TestCase
{
	protected function setUp(): void
	{
		Context::reset();
		TestHelper::reset();
		pointModel::reset();
		ModuleObject::resetInstances();
	}

	/**
	 * Helper to create a controller with default module settings
	 */
	private function createController(string $adtime = '1,2,4,8,128,256'): plusadController
	{
		$controller = new plusadController();
		$controller->module_info->adtime    = $adtime;
		$controller->module_info->adpoint   = 10;
		$controller->module_info->boldpoint = 100;
		$controller->module_info->colorpoint = 300;
		$controller->module_info->domain_list = '';
		return $controller;
	}

	/**
	 * Helper to set up a logged-in user in Context
	 */
	private function setLoggedIn(int $member_srl, string $nick_name = 'tester'): stdClass
	{
		$logged_info = new stdClass();
		$logged_info->member_srl = $member_srl;
		$logged_info->nick_name  = $nick_name;
		Context::set('logged_info', $logged_info);
		return $logged_info;
	}

	/**
	 * Helper to set up a successful insert_ad query result
	 */
	private function setupInsertAdSuccess(): void
	{
		$output = new BaseObject();
		TestHelper::setQueryResult('plusad.insert_ad', $output);

		$pointOutput = new BaseObject();
		$pointOutput->data = [];
		TestHelper::setQueryResult('plusad.getadpoint', $pointOutput);
	}

	// -------------------------------------------------------------------------
	// Max allowed time tests
	// -------------------------------------------------------------------------

	/**
	 * Test: time equals max configured value → allowed
	 * adtime = "1,2,4,8,128,256", time = 256 → should pass validation
	 */
	public function testMaxAllowedTimeEqualsConfigMax(): void
	{
		$controller = $this->createController('1,2,4,8,128,256');
		$this->setLoggedIn(1);
		pointModel::setTestPoint(1, 99999);
		$this->setupInsertAdSuccess();

		Context::set('time', 256);
		Context::set('bold', 'no');
		Context::set('color', 'no');
		Context::set('ad_content', 'Test ad');
		Context::set('ad_url', '');
		Context::set('success_return_url', '/redirect');

		$result = $controller->procPlusadwrite();

		// Should succeed (no error returned)
		$this->assertNull($result);
	}

	/**
	 * Test: time exceeds max configured value → error
	 * adtime = "1,2,4,8,128,256", time = 257 → should fail
	 */
	public function testTimeExceedsConfigMax(): void
	{
		$controller = $this->createController('1,2,4,8,128,256');
		$this->setLoggedIn(1);

		Context::set('time', 257);
		Context::set('bold', 'no');
		Context::set('color', 'no');
		Context::set('ad_content', 'Test ad');
		Context::set('ad_url', '');

		$result = $controller->procPlusadwrite();

		$this->assertInstanceOf(BaseObject::class, $result);
		$this->assertFalse($result->toBool());
		$this->assertEquals('광고 허용시간을 초과하였습니다.', $result->getMessage());
	}

	/**
	 * Test: previously hardcoded 128 is no longer the limit when config has 256
	 * adtime = "1,2,4,8,128,256", time = 200 → should pass (between 128 and 256)
	 */
	public function testTimeBetweenOldLimitAndNewMax(): void
	{
		$controller = $this->createController('1,2,4,8,128,256');
		$this->setLoggedIn(1);
		pointModel::setTestPoint(1, 99999);
		$this->setupInsertAdSuccess();

		Context::set('time', 200);
		Context::set('bold', 'no');
		Context::set('color', 'no');
		Context::set('ad_content', 'Test ad');
		Context::set('ad_url', '');
		Context::set('success_return_url', '/redirect');

		$result = $controller->procPlusadwrite();

		// Should succeed (200 <= 256)
		$this->assertNull($result);
	}

	/**
	 * Test: adtime not configured → fallback max is 128
	 * time = 129 → should fail
	 */
	public function testFallbackMaxTimeWhenAdtimeEmpty(): void
	{
		$controller = $this->createController('');
		$this->setLoggedIn(1);

		Context::set('time', 129);
		Context::set('bold', 'no');
		Context::set('color', 'no');
		Context::set('ad_content', 'Test ad');
		Context::set('ad_url', '');

		$result = $controller->procPlusadwrite();

		$this->assertInstanceOf(BaseObject::class, $result);
		$this->assertFalse($result->toBool());
		$this->assertEquals('광고 허용시간을 초과하였습니다.', $result->getMessage());
	}

	// -------------------------------------------------------------------------
	// Point module disabled tests
	// -------------------------------------------------------------------------

	/**
	 * Test: Point module disabled → point check is skipped (ad registers even with 0 points)
	 */
	public function testPointModuleDisabledSkipsPointCheck(): void
	{
		$controller = $this->createController('1,2,4,8,24');
		$this->setLoggedIn(1);
		// User has 0 points but point module is disabled
		pointModel::setTestPoint(1, 0);

		// Disable point module via config
		$config = new stdClass();
		$config->able_module = 'N';
		TestHelper::$moduleConfigs['point'] = $config;

		$this->setupInsertAdSuccess();

		Context::set('time', 1);
		Context::set('bold', 'no');
		Context::set('color', 'no');
		Context::set('ad_content', 'Test ad');
		Context::set('ad_url', '');
		Context::set('success_return_url', '/redirect');

		$result = $controller->procPlusadwrite();

		// Should succeed despite 0 points
		$this->assertNull($result);
	}

	/**
	 * Test: Point module enabled → point check is enforced (ad fails with 0 points)
	 */
	public function testPointModuleEnabledEnforcesPointCheck(): void
	{
		$controller = $this->createController('1,2,4,8,24');
		$this->setLoggedIn(1);
		pointModel::setTestPoint(1, 0);

		// Enable point module explicitly
		$config = new stdClass();
		$config->able_module = 'Y';
		TestHelper::$moduleConfigs['point'] = $config;

		Context::set('time', 1);
		Context::set('bold', 'no');
		Context::set('color', 'no');
		Context::set('ad_content', 'Test ad');
		Context::set('ad_url', '');

		$result = $controller->procPlusadwrite();

		$this->assertInstanceOf(BaseObject::class, $result);
		$this->assertFalse($result->toBool());
		$this->assertEquals('포인트가 부족합니다', $result->getMessage());
	}

	/**
	 * Test: Point module disabled → no point deduction on successful ad registration
	 */
	public function testPointModuleDisabledSkipsPointDeduction(): void
	{
		$controller = $this->createController('1,2,4,8,24');
		$this->setLoggedIn(1);
		pointModel::setTestPoint(1, 0);

		// Disable point module
		$config = new stdClass();
		$config->able_module = 'N';
		TestHelper::$moduleConfigs['point'] = $config;

		$this->setupInsertAdSuccess();

		Context::set('time', 1);
		Context::set('bold', 'no');
		Context::set('color', 'no');
		Context::set('ad_content', 'Test ad');
		Context::set('ad_url', '');
		Context::set('success_return_url', '/redirect');

		$controller->procPlusadwrite();

		// No point operations should have occurred
		$this->assertCount(0, TestHelper::$pointCalls);
	}

	/**
	 * Test: Point module enabled → points are deducted on successful ad registration
	 */
	public function testPointModuleEnabledDeductsPoints(): void
	{
		$controller = $this->createController('1,2,4,8,24');
		$this->setLoggedIn(1);
		pointModel::setTestPoint(1, 99999);

		$config = new stdClass();
		$config->able_module = 'Y';
		TestHelper::$moduleConfigs['point'] = $config;

		$this->setupInsertAdSuccess();

		Context::set('time', 1);
		Context::set('bold', 'no');
		Context::set('color', 'no');
		Context::set('ad_content', 'Test ad');
		Context::set('ad_url', '');
		Context::set('success_return_url', '/redirect');

		$controller->procPlusadwrite();

		// One point deduction should have occurred
		$this->assertCount(1, TestHelper::$pointCalls);
		$call = TestHelper::$pointCalls[0];
		$this->assertEquals(1, $call['member_srl']);
		$this->assertEquals('minus', $call['mode']);
	}
}
