<?php

/**
 * @group headless
 * @runTestsInSeparateProcesses
 */
class AllocationTest extends \PHPUnit\Framework\TestCase {

  private function schedule(int $id, int $oldestId, int $pending): array {
    return [
      'id' => $id,
      'oldest_id' => $oldestId,
      'pending' => $pending,
    ];
  }

  public function testOldestPendingReminderWinsOverScheduleId(): void {
    $allocation = _batchreminders_rank_and_allocate([
      $this->schedule(1, 200, 10),
      $this->schedule(99, 100, 10),
    ], 5);

    $this->assertSame([99 => 5], $allocation);
  }

  public function testBudgetIsNeverExceededInTotal(): void {
    $allocation = _batchreminders_rank_and_allocate([
      $this->schedule(10, 100, 100),
      $this->schedule(11, 101, 100),
      $this->schedule(12, 102, 100),
    ], 20);

    $this->assertSame(20, array_sum($allocation));
  }

  public function testBudgetRollsOverWhenOneScheduleIsExhausted(): void {
    $allocation = _batchreminders_rank_and_allocate([
      $this->schedule(10, 100, 3),
      $this->schedule(11, 101, 10),
      $this->schedule(12, 102, 10),
    ], 8);

    $this->assertSame([10 => 3, 11 => 5], $allocation);
  }

  public function testEmptyInputReturnsEmptyAllocation(): void {
    $this->assertSame([], _batchreminders_rank_and_allocate([], 20));
  }

  public function testSingleScheduleWithMorePendingThanBudgetIsCapped(): void {
    $allocation = _batchreminders_rank_and_allocate([
      $this->schedule(10, 100, 50),
    ], 7);

    $this->assertSame([10 => 7], $allocation);
  }

  public function testTieOnOldestIdIsBrokenByAscendingScheduleId(): void {
    $allocation = _batchreminders_rank_and_allocate([
      $this->schedule(30, 100, 10),
      $this->schedule(20, 100, 10),
    ], 12);

    $this->assertSame([20 => 10, 30 => 2], $allocation);
  }

  /**
   * BuildAllocationEvent listeners set 'sort_key' to override the default
   * oldest_id/id ordering (e.g. a site-specific category priority). Schedules
   * without an explicit sort_key must keep sorting on oldest_id/id, so
   * existing callers (and every other test in this class) are unaffected.
   */
  public function testExplicitSortKeyOverridesDefaultOldestIdOrdering(): void {
    $schedules = [
      $this->schedule(1, 100, 10) + ['sort_key' => [2, 1]],
      $this->schedule(2, 200, 10) + ['sort_key' => [1, 2]],
    ];

    $allocation = _batchreminders_rank_and_allocate($schedules, 5);

    // Schedule 2 has the higher-priority sort_key ([1, 2] < [2, 1]) despite
    // its oldest_id (200) being larger than schedule 1's (100).
    $this->assertSame([2 => 5], $allocation);
  }

  public function testMixOfExplicitAndDefaultSortKeyFallsBackPerSchedule(): void {
    $schedules = [
      // No sort_key: falls back to [oldest_id, id] = [50, 1].
      $this->schedule(1, 50, 10),
      // Explicit sort_key beats the other schedule's fallback even though
      // its own oldest_id (999) is much larger.
      $this->schedule(2, 999, 10) + ['sort_key' => [1, 2]],
    ];

    $allocation = _batchreminders_rank_and_allocate($schedules, 5);

    $this->assertSame([2 => 5], $allocation);
  }

}
