<?php

namespace Civi\Batchreminders\Event;

use Civi\Core\Event\GenericHookEvent;

/**
 * Fired once per cron run, before ranking pending schedules for this run's
 * shared batch budget (see _batchreminders_build_allocation()).
 *
 * Listeners may:
 * - unset a schedule from `$event->schedules` to exclude it entirely from
 *   this run (it will render/send nothing, same as having zero pending);
 *   useful for site-specific validation that decides a schedule's template
 *   is not safe to send this run.
 * - overwrite a schedule's `sort_key` to change its priority. Schedules
 *   default to `sort_key = [oldest_id, id]` (oldest pending reminder first,
 *   schedule id as a tie-break). A `sort_key` may be any PHP-comparable
 *   value; arrays are compared element-by-element, which is how a
 *   multi-criteria priority (e.g. day, then category, then id) is expressed.
 *
 * Event name: 'civi.batchreminders.buildAllocation'
 */
class BuildAllocationEvent extends GenericHookEvent {

  const EVENT_NAME = 'civi.batchreminders.buildAllocation';

  /**
   * Pending schedules for this run, keyed by action_schedule id.
   *
   * @var array<int,array{id:int,title:string,oldest_id:int,pending:int,sort_key:array}>
   */
  public array $schedules = [];

  /**
   * @param array<int,array{id:int,title:string,oldest_id:int,pending:int,sort_key:array}> $schedules
   */
  public function __construct(array $schedules) {
    $this->schedules = $schedules;
  }

}
