<?php

/**
 * @group headless
 * @runTestsInSeparateProcesses
 */
class AlterMailParamsTest extends \PHPUnit\Framework\TestCase {

  protected function setUp(): void {
    Civi::$stubSettings = [];
    Civi::$statics = [];
    CRM_Core_DAO::$stubPausedRaw = NULL;
  }

  private function makeParams(int $scheduleId = 123): array {
    return [
      'contact_id' => 456,
      'toEmail' => 'test@example.org',
      'subject' => 'Scheduled reminder',
      'entity_id' => $scheduleId,
      'groupName' => 'Scheduled Reminder Sender',
      'entity' => 'action_schedule',
    ];
  }

  /**
   * Params shaped like a non-reminder send (e.g. a mailtest, a CiviMail bulk
   * send, or any other outgoing mail). The hook must leave these completely
   * untouched, even while paused/over the batch limit — it has no business
   * governing mail it did not originate.
   */
  private function makeUnrelatedParams(): array {
    return [
      'contact_id' => 456,
      'toEmail' => 'test@example.org',
      'subject' => 'Some other mail',
    ];
  }

  public function testMailWithinBatchLimitIsNotAborted(): void {
    Civi::$stubSettings['batchreminders_batch_size'] = 2;

    $params = $this->makeParams();
    batchreminders_civicrm_alterMailParams($params, 'singleEmail');

    $this->assertArrayNotHasKey('abortMailSend', $params);
    $this->assertArrayNotHasKey('abort', $params);
  }

  public function testMailOnceBatchLimitIsReachedIsAbortedWithAbortMailSendOnly(): void {
    Civi::$stubSettings['batchreminders_batch_size'] = 1;

    $first = $this->makeParams();
    batchreminders_civicrm_alterMailParams($first, 'singleEmail');
    $this->assertArrayNotHasKey('abortMailSend', $first);

    $second = $this->makeParams();
    batchreminders_civicrm_alterMailParams($second, 'singleEmail');

    $this->assertArrayHasKey('abortMailSend', $second);
    $this->assertTrue($second['abortMailSend']);
    $this->assertArrayNotHasKey('abort', $second, 'CRM_Utils_Mail::send() checks abortMailSend, not abort.');
  }

  public function testPausedMailIsAbortedImmediatelyWithoutConsumingBatch(): void {
    Civi::$stubSettings['batchreminders_batch_size'] = 1;
    CRM_Core_DAO::$stubPausedRaw = serialize(TRUE);

    $paused = $this->makeParams();
    batchreminders_civicrm_alterMailParams($paused, 'singleEmail');

    $this->assertArrayHasKey('abortMailSend', $paused);
    $this->assertTrue($paused['abortMailSend']);
    $this->assertArrayNotHasKey('abort', $paused);

    CRM_Core_DAO::$stubPausedRaw = serialize(FALSE);

    $afterResume = $this->makeParams();
    batchreminders_civicrm_alterMailParams($afterResume, 'singleEmail');

    $this->assertArrayNotHasKey('abortMailSend', $afterResume, 'Paused mails must not consume the per-run sent counter.');
  }

  /**
   * A pause flipped on while a run is already executing must take effect on the
   * very next mail in that same run, not merely on the next cron tick. This
   * guards the direct civicrm_setting read in _batchreminders_paused() against
   * regressing to a per-process-cached Civi::settings() call.
   */
  public function testPauseSetMidRunIsRespectedByTheNextMail(): void {
    Civi::$stubSettings['batchreminders_batch_size'] = 25;
    CRM_Core_DAO::$stubPausedRaw = serialize(FALSE);

    $beforePause = $this->makeParams();
    batchreminders_civicrm_alterMailParams($beforePause, 'singleEmail');
    $this->assertArrayNotHasKey('abortMailSend', $beforePause);

    CRM_Core_DAO::$stubPausedRaw = serialize(TRUE);

    $afterPause = $this->makeParams();
    batchreminders_civicrm_alterMailParams($afterPause, 'singleEmail');
    $this->assertArrayHasKey('abortMailSend', $afterPause);
    $this->assertTrue($afterPause['abortMailSend']);
  }

  /**
   * Regression: pausing (or hitting the batch limit) must only affect actual
   * scheduled-reminder sends. Mail without groupName=Scheduled Reminder Sender
   * / entity=action_schedule (e.g. mailtest, CiviMail) must pass through
   * completely untouched.
   */
  public function testUnrelatedMailIsNeverTouchedEvenWhenPausedOrOverLimit(): void {
    Civi::$stubSettings['batchreminders_batch_size'] = 1;
    CRM_Core_DAO::$stubPausedRaw = serialize(TRUE);

    $whilePaused = $this->makeUnrelatedParams();
    batchreminders_civicrm_alterMailParams($whilePaused, 'singleEmail');
    $this->assertArrayNotHasKey('abortMailSend', $whilePaused, 'Non-reminder mail must not be aborted while paused.');

    CRM_Core_DAO::$stubPausedRaw = serialize(FALSE);

    // Exhaust the batch limit with a real reminder send, then confirm an
    // unrelated mail right after is still left alone.
    $reminder = $this->makeParams();
    batchreminders_civicrm_alterMailParams($reminder, 'singleEmail');
    $this->assertArrayNotHasKey('abortMailSend', $reminder);

    $overLimit = $this->makeUnrelatedParams();
    batchreminders_civicrm_alterMailParams($overLimit, 'singleEmail');
    $this->assertArrayNotHasKey('abortMailSend', $overLimit, 'Non-reminder mail must not be aborted by the reminder batch limit.');
  }

}
