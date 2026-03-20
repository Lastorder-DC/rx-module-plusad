<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for dispPlusadCancel function in plusadView
 *
 * Settings: cancel_threshold_percent = 10%, cancel_fee_percent = 10%
 * Refund formula: ad_point × (100 - round(elapsed%) - fee%) / 100, minimum 0
 */
class PlusadCancelTest extends TestCase
{
	protected function setUp(): void
	{
		Context::reset();
		TestHelper::reset();
		ModuleObject::resetInstances();
	}

	/**
	 * Helper to create a plusadView instance with default module settings
	 */
	private function createView(): plusadView
	{
		$view = new plusadView();
		$view->module_info->cancel_threshold_percent = 10;
		$view->module_info->cancel_fee_percent = 10;
		return $view;
	}

	/**
	 * Helper to create ad data with a specific elapsed time percentage
	 *
	 * Uses a very large total duration (10,000,000 seconds ≈ 115 days)
	 * to minimize the impact of timing differences between test setup and execution.
	 */
	private function createAdData(int $member_srl, int $ad_point, float $elapsed_percent): stdClass
	{
		$total_seconds = 10000000;
		$elapsed_seconds = intval($total_seconds * $elapsed_percent / 100);
		$now = time();

		$ad = new stdClass();
		$ad->ad_srl = 1;
		$ad->member_srl = $member_srl;
		$ad->ad_point = $ad_point;
		$ad->regdate = date('Y-m-d H:i:s', $now - $elapsed_seconds);
		$ad->enddate = date('Y-m-d H:i:s', $now - $elapsed_seconds + $total_seconds);
		$ad->time = intval($total_seconds / 3600);
		$ad->ad_content = 'Test ad';
		$ad->ad_url = '';
		return $ad;
	}

	/**
	 * Helper to set up query results for a successful cancel flow
	 */
	private function setupQueryResults(stdClass $ad): void
	{
		// getad returns the ad data
		$getOutput = new BaseObject();
		$getOutput->data = $ad;
		TestHelper::setQueryResult('plusad.getad', $getOutput);

		// deletead succeeds
		$deleteOutput = new BaseObject();
		TestHelper::setQueryResult('plusad.deletead', $deleteOutput);
	}

	/**
	 * Test: Cancel without login → error
	 */
	public function testCancelWithoutLogin(): void
	{
		$view = $this->createView();
		Context::set('ad_srl', 1);
		Context::set('logged_info', null);

		$result = $view->dispPlusadCancel();

		$this->assertInstanceOf(BaseObject::class, $result);
		$this->assertFalse($result->toBool());
		$this->assertEquals('로그인후 이용가능합니다', $result->getMessage());
	}

	/**
	 * Test: Cancel when ad is not found → error
	 */
	public function testCancelAdNotFound(): void
	{
		$view = $this->createView();

		$logged_info = new stdClass();
		$logged_info->member_srl = 1;
		Context::set('logged_info', $logged_info);
		Context::set('ad_srl', 999);

		// getad returns null (ad not found)
		$output = new BaseObject();
		$output->data = null;
		TestHelper::setQueryResult('plusad.getad', $output);

		$result = $view->dispPlusadCancel();

		$this->assertInstanceOf(BaseObject::class, $result);
		$this->assertFalse($result->toBool());
		$this->assertEquals('광고 정보를 찾을 수 없습니다.', $result->getMessage());
	}

	/**
	 * Test: Cancel ad that belongs to another user → error
	 */
	public function testCancelNotOwnAd(): void
	{
		$view = $this->createView();

		$logged_info = new stdClass();
		$logged_info->member_srl = 1;
		Context::set('logged_info', $logged_info);
		Context::set('ad_srl', 1);

		// Ad belongs to a different user (member_srl = 2)
		$ad = $this->createAdData(2, 10000, 5);
		$output = new BaseObject();
		$output->data = $ad;
		TestHelper::setQueryResult('plusad.getad', $output);

		$result = $view->dispPlusadCancel();

		$this->assertInstanceOf(BaseObject::class, $result);
		$this->assertFalse($result->toBool());
		$this->assertEquals('본인의 광고만 취소할 수 있습니다.', $result->getMessage());
	}

	/**
	 * Test: Cancel with <0.5% elapsed → success with correct refund
	 *
	 * ad_point = 10000, elapsed ≈ 0.3%, cancel_fee = 10%
	 * deduction = round(0.3) + 10 = 0 + 10 = 10%
	 * refund = intval(10000 × (100 - 10) / 100) = 9000
	 */
	public function testCancelUnderHalfPercent(): void
	{
		$view = $this->createView();

		$logged_info = new stdClass();
		$logged_info->member_srl = 1;
		Context::set('logged_info', $logged_info);
		Context::set('ad_srl', 1);
		Context::set('success_return_url', '/redirect');

		$ad = $this->createAdData(1, 10000, 0.3);
		$this->setupQueryResults($ad);

		$result = $view->dispPlusadCancel();

		// Success: function returns void (null)
		$this->assertNull($result);

		// Verify point refund
		$this->assertCount(1, TestHelper::$pointCalls);
		$pointCall = TestHelper::$pointCalls[0];
		$this->assertEquals(1, $pointCall['member_srl']);
		$this->assertEquals(9000, $pointCall['point']);
		$this->assertEquals('add', $pointCall['mode']);

		// Verify deletead was called
		$deleteQueries = array_filter(TestHelper::$queries, function ($q) {
			return $q['queryId'] === 'plusad.deletead';
		});
		$this->assertCount(1, $deleteQueries);
	}

	/**
	 * Test: Cancel with ~10% elapsed → success with correct refund
	 *
	 * ad_point = 10000, elapsed ≈ 9.99% (just under 10% threshold), cancel_fee = 10%
	 * deduction = round(9.99) + 10 = 10 + 10 = 20%
	 * refund = intval(10000 × (100 - 20) / 100) = 8000
	 */
	public function testCancelAtTenPercent(): void
	{
		$view = $this->createView();

		$logged_info = new stdClass();
		$logged_info->member_srl = 1;
		Context::set('logged_info', $logged_info);
		Context::set('ad_srl', 1);
		Context::set('success_return_url', '/redirect');

		// Use 9.99% elapsed (just under the 10% threshold)
		$ad = $this->createAdData(1, 10000, 9.99);
		$this->setupQueryResults($ad);

		$result = $view->dispPlusadCancel();

		// Success: function returns void (null)
		$this->assertNull($result);

		// Verify point refund
		$this->assertCount(1, TestHelper::$pointCalls);
		$pointCall = TestHelper::$pointCalls[0];
		$this->assertEquals(1, $pointCall['member_srl']);
		$this->assertEquals(8000, $pointCall['point']);
		$this->assertEquals('add', $pointCall['mode']);

		// Verify deletead was called
		$deleteQueries = array_filter(TestHelper::$queries, function ($q) {
			return $q['queryId'] === 'plusad.deletead';
		});
		$this->assertCount(1, $deleteQueries);
	}

	/**
	 * Test: Cancel with 20% elapsed → failure (exceeds threshold)
	 */
	public function testCancelAtTwentyPercentFails(): void
	{
		$view = $this->createView();

		$logged_info = new stdClass();
		$logged_info->member_srl = 1;
		Context::set('logged_info', $logged_info);
		Context::set('ad_srl', 1);

		$ad = $this->createAdData(1, 10000, 20);
		$output = new BaseObject();
		$output->data = $ad;
		TestHelper::setQueryResult('plusad.getad', $output);

		$result = $view->dispPlusadCancel();

		// Should return error
		$this->assertInstanceOf(BaseObject::class, $result);
		$this->assertFalse($result->toBool());
		$this->assertStringContainsString('10%', $result->getMessage());

		// No point refund should occur
		$this->assertCount(0, TestHelper::$pointCalls);

		// No delete query should be executed
		$deleteQueries = array_filter(TestHelper::$queries, function ($q) {
			return $q['queryId'] === 'plusad.deletead';
		});
		$this->assertCount(0, $deleteQueries);
	}
}
