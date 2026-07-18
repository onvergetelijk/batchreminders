<?php
use CRM_Batchreminders_ExtensionUtil as E;

class CRM_Batchreminders_Page_Status extends CRM_Core_Page {

  public function run(): void {
    CRM_Utils_System::setTitle(E::ts('Batch Reminders Status'));

    if (!CRM_Core_Permission::check('administer CiviCRM')) {
      CRM_Utils_System::permissionDenied();
      return;
    }

    $paused = _batchreminders_paused();

    // Preview of what the next cron run would actually send per schedule.
    // Reuses the real allocator (same event/ranking the live run uses), so this
    // can never drift from what actually happens. While paused, the render
    // listener limits every schedule to 0 regardless of allocation, so show 0
    // here too rather than a number that would not actually go out.
    $nextRunAlloc = $paused ? [] : _batchreminders_build_allocation();

    $pendingDao = CRM_Core_DAO::executeQuery("
      SELECT S.id, S.title, S.is_active, S.msg_template_id, T.msg_title,
             COUNT(L.id) AS pending, MIN(L.id) AS oldest_id
      FROM civicrm_action_log      L
      JOIN civicrm_action_schedule S ON S.id = L.action_schedule_id
      LEFT JOIN civicrm_msg_template T ON T.id = S.msg_template_id
      WHERE L.action_date_time IS NULL
      GROUP BY S.id, S.title, S.is_active, S.msg_template_id, T.msg_title
      ORDER BY MIN(L.id), S.id
    ");

    $rows = [];
    $totalPending = 0;
    while ($pendingDao->fetch()) {
      $id = (int) $pendingDao->id;
      $pending = (int) $pendingDao->pending;
      $totalPending += $pending;
      $rows[] = [
        'id' => $id,
        'title' => (string) $pendingDao->title,
        'is_active' => (bool) $pendingDao->is_active,
        'pending' => $pending,
        'oldest_id' => (int) $pendingDao->oldest_id,
        'templateId' => $pendingDao->msg_template_id !== NULL ? (int) $pendingDao->msg_template_id : NULL,
        'templateTitle' => (string) ($pendingDao->msg_title ?? ''),
        'nextRun' => (int) ($nextRunAlloc[$id] ?? 0),
      ];
    }

    $lastSentDao = CRM_Core_DAO::executeQuery("
      SELECT MAX(action_date_time) AS last_sent
      FROM civicrm_action_log
      WHERE action_date_time IS NOT NULL
    ");
    $lastSent = ($lastSentDao->fetch() && $lastSentDao->last_sent) ? (string) $lastSentDao->last_sent : NULL;
    $minutesAgo = $lastSent ? (int) round((time() - strtotime($lastSent)) / 60) : NULL;

    // Recent sends (last 3 hours), grouped by schedule, for a quick "is this
    // actually moving" glance without needing to know the site's own cron
    // schedule (unlike a "next run in" prediction, which depends on cron
    // configuration this extension has no knowledge of).
    $recentDao = CRM_Core_DAO::executeQuery("
      SELECT S.id, S.title, COUNT(*) AS sent_count, MAX(L.action_date_time) AS last_sent
      FROM civicrm_action_log L
      JOIN civicrm_action_schedule S ON S.id = L.action_schedule_id
      WHERE L.action_date_time >= (NOW() - INTERVAL 3 HOUR)
      GROUP BY S.id, S.title
      ORDER BY MAX(L.action_date_time) DESC
    ");
    $recent = [];
    while ($recentDao->fetch()) {
      $recent[] = [
        'id' => (int) $recentDao->id,
        'title' => (string) $recentDao->title,
        'sentCount' => (int) $recentDao->sent_count,
        'lastSent' => (string) $recentDao->last_sent,
      ];
    }

    $this->assign('paused', $paused);
    $this->assign('batchSize', _batchreminders_batch_size());
    $this->assign('totalPending', $totalPending);
    $this->assign('rows', $rows);
    $this->assign('lastSent', $lastSent);
    $this->assign('minutesAgo', $minutesAgo);
    $this->assign('recent', $recent);
    $this->assign('now', date('Y-m-d H:i:s'));

    parent::run();
  }

}
