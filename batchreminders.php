<?php

if (!class_exists('CRM_Batchreminders_ExtensionUtil', FALSE)) {
  require_once __DIR__ . '/batchreminders.civix.php';
}

use CRM_Batchreminders_ExtensionUtil as E;
use Civi\Batchreminders\Event\BuildAllocationEvent;

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function batchreminders_civicrm_config(&$config): void {
  _batchreminders_civix_civicrm_config($config);

  static $listenerRegistered = FALSE;
  if ($listenerRegistered) {
    return;
  }
  $listenerRegistered = TRUE;

  Civi::dispatcher()->addListener('civi.actionSchedule.prepareMailingQuery', '_batchreminders_limit_mailing_query');
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function batchreminders_civicrm_install(): void {
  _batchreminders_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function batchreminders_civicrm_enable(): void {
  _batchreminders_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * Adds the status page under Administer > CiviMail when that menu exists. The
 * page also remains available at its direct URL if the CiviMail menu is absent.
 */
function batchreminders_civicrm_navigationMenu(&$nodes): void {
  _batchreminders_civix_navigationMenu($nodes);

  $civiMailNode = &_batchreminders_find_navigation_node($nodes, 'CiviMail');
  if ($civiMailNode === NULL) {
    return;
  }

  if (!isset($civiMailNode['child']) || !is_array($civiMailNode['child'])) {
    $civiMailNode['child'] = [];
  }

  foreach ($civiMailNode['child'] as $child) {
    if (($child['attributes']['name'] ?? '') === 'Batch Reminders Status') {
      return;
    }
  }

  $newNavId = _batchreminders_max_navigation_id($nodes) + 1;
  $civiMailNode['child'][$newNavId] = [
    'attributes' => [
      'label' => E::ts('Batch Reminders Status'),
      'name' => 'Batch Reminders Status',
      'url' => 'civicrm/batchreminders/status?reset=1',
      'permission' => 'administer CiviCRM',
      'operator' => NULL,
      'separator' => NULL,
      'parentID' => $civiMailNode['attributes']['navID'] ?? NULL,
      'navID' => $newNavId,
      'active' => 1,
    ],
  ];
}

/**
 * Find a navigation-menu node by name and return it by reference for editing.
 */
function &_batchreminders_find_navigation_node(array &$nodes, string $name) {
  foreach ($nodes as &$node) {
    if (($node['attributes']['name'] ?? '') === $name) {
      return $node;
    }
    if (isset($node['child']) && is_array($node['child'])) {
      $found = &_batchreminders_find_navigation_node($node['child'], $name);
      if ($found !== NULL) {
        return $found;
      }
    }
  }

  $null = NULL;
  return $null;
}

/**
 * Get the highest navID in a navigation-menu tree.
 */
function _batchreminders_max_navigation_id(array $nodes): int {
  $max = 0;
  foreach ($nodes as $navId => $node) {
    $max = max($max, (int) $navId, (int) ($node['attributes']['navID'] ?? 0));
    if (isset($node['child']) && is_array($node['child'])) {
      $max = max($max, _batchreminders_max_navigation_id($node['child']));
    }
  }
  return $max;
}

/**
 * Implements hook_civicrm_alterMailParams().
 *
 * This is a send-side fallback for the render query limit. The exact key is
 * abortMailSend because CRM_Utils_Mail::send() checks that key before sending.
 *
 * Scoped to CRM_Core_BAO_ActionSchedule::sendReminderEmail() specifically
 * (groupName/entity are the exact values it sets on $mailParams, see
 * CRM/Core/BAO/ActionSchedule.php) — every other mail send in CiviCRM core
 * also fires this hook with $context === 'singleEmail', so without this
 * check the pause/batch-limit below would silently abort ALL CLI-sent mail
 * (mailtest, transactional mail, etc.), not just scheduled reminders.
 */
function batchreminders_civicrm_alterMailParams(&$params, $context): void {
  static $sentCount = 0;

  if (php_sapi_name() !== 'cli') {
    return;
  }

  if (($params['groupName'] ?? NULL) !== 'Scheduled Reminder Sender' || ($params['entity'] ?? NULL) !== 'action_schedule') {
    return;
  }

  if (_batchreminders_paused()) {
    $params['abortMailSend'] = TRUE;
    return;
  }

  $batchLimit = _batchreminders_batch_size();
  if ($sentCount >= $batchLimit) {
    $params['abortMailSend'] = TRUE;
    return;
  }

  $throttleUsec = _batchreminders_throttle_usec();
  if ($sentCount > 0 && $throttleUsec > 0) {
    usleep($throttleUsec);
  }

  $sentCount++;
}

/**
 * Shared batch size for both the render LIMIT and the send-side fallback.
 */
function _batchreminders_batch_size(): int {
  $size = (int) (Civi::settings()->get('batchreminders_batch_size') ?: 20);
  return max(1, $size);
}

/**
 * Global pause switch for scheduled reminders.
 *
 * Deliberately bypasses Civi::settings(): CiviCRM's SettingsBag loads settings
 * once per PHP process and never re-reads the database afterwards. A "Send
 * Scheduled Reminders" run can take minutes when token rendering is slow, so a
 * pause flipped on while such a run is already in progress would otherwise not
 * take effect until the next cron tick, sending mail against the caller's
 * explicit intent in the meantime. Reading civicrm_setting directly, on every
 * call, makes the pause take effect immediately even inside an already-running
 * process.
 */
function _batchreminders_paused(): bool {
  $domainId = (int) CRM_Core_Config::domainID();
  $raw = CRM_Core_DAO::singleValueQuery(
    'SELECT value FROM civicrm_setting WHERE name = %1 AND domain_id = %2 AND contact_id IS NULL',
    [
      1 => ['batchreminders_paused', 'String'],
      2 => [$domainId, 'Integer'],
    ]
  );

  return $raw === NULL ? FALSE : (bool) @unserialize($raw);
}

/**
 * Pause between scheduled-reminder sends, in microseconds.
 *
 * This intentionally reuses CiviCRM core's mailThrottleTime setting from Mailer
 * Settings. Core applies that setting to bulk CiviMail sends and SMS jobs, but
 * the scheduled-reminder path sends through CRM_Utils_Mail::send() without using
 * the throttle. Sleeping here gives reminders the same pacing control without a
 * parallel extension-specific setting. The value is passed directly to usleep().
 */
function _batchreminders_throttle_usec(): int {
  return max(0, (int) Civi::settings()->get('mailThrottleTime'));
}

/**
 * Listener for civi.actionSchedule.prepareMailingQuery.
 *
 * Limits CiviCRM core's per-schedule recipient query before token rendering.
 */
function _batchreminders_limit_mailing_query($event): void {
  if (php_sapi_name() !== 'cli') {
    return;
  }

  if (!isset(Civi::$statics['batchreminders']) || !is_array(Civi::$statics['batchreminders'])) {
    Civi::$statics['batchreminders'] = [];
  }
  $state = &Civi::$statics['batchreminders'];

  if (_batchreminders_paused()) {
    if (empty($state['pause_logged'])) {
      Civi::log()->info('batchreminders: scheduled reminders are paused; rendering is limited to zero for this run.');
      $state['pause_logged'] = TRUE;
    }
    $event->query->limit(0);
    return;
  }

  if (!array_key_exists('allocation', $state)) {
    $state['allocation'] = _batchreminders_build_allocation();
    if (!empty($state['allocation'])) {
      Civi::log()->debug('batchreminders: render allocation for this run: ' . json_encode($state['allocation']));
    }
  }

  $scheduleId = (int) ($event->actionSchedule->id ?? 0);
  $budget = (int) ($state['allocation'][$scheduleId] ?? 0);
  $event->query->limit($budget);

  if ($budget > 0) {
    Civi::log()->debug("batchreminders: render limit for schedule {$scheduleId}: LIMIT {$budget}");
  }
}

/**
 * Build the global per-run allocation from currently pending active schedules.
 *
 * Dispatches BuildAllocationEvent (civi.batchreminders.buildAllocation) before
 * ranking, so a site-specific extension can exclude a schedule (e.g. failed
 * template validation) or override its priority (e.g. a category-based
 * ordering instead of plain oldest-first) without this extension knowing
 * anything about that site's rules.
 *
 * @return array<int,int>
 *   Map of action_schedule_id to recipient budget.
 */
function _batchreminders_build_allocation(): array {
  $dao = CRM_Core_DAO::executeQuery("
    SELECT S.id, S.title, MIN(L.id) AS oldest_id, COUNT(L.id) AS pending
    FROM civicrm_action_log L
    JOIN civicrm_action_schedule S ON S.id = L.action_schedule_id
    WHERE L.action_date_time IS NULL
      AND S.is_active = 1
    GROUP BY S.id, S.title
  ");

  $schedules = [];
  while ($dao->fetch()) {
    $id = (int) $dao->id;
    $schedules[$id] = [
      'id' => $id,
      'title' => (string) $dao->title,
      'oldest_id' => (int) $dao->oldest_id,
      'pending' => (int) $dao->pending,
      'sort_key' => [(int) $dao->oldest_id, $id],
    ];
  }

  $event = new BuildAllocationEvent($schedules);
  Civi::dispatcher()->dispatch(BuildAllocationEvent::EVENT_NAME, $event);

  return _batchreminders_rank_and_allocate(array_values($event->schedules), _batchreminders_batch_size());
}

/**
 * Rank schedules by sort_key (oldest pending reminder first by default) and
 * allocate a shared run budget.
 *
 * @param array<int,array{id:int,oldest_id:int,pending:int,sort_key?:array}> $schedules
 * @param int $batchSize
 *
 * @return array<int,int>
 *   Map of action_schedule_id to allocated count, excluding zero allocations.
 */
function _batchreminders_rank_and_allocate(array $schedules, int $batchSize): array {
  usort($schedules, function(array $a, array $b): int {
    $keyA = $a['sort_key'] ?? [(int) ($a['oldest_id'] ?? 0), (int) ($a['id'] ?? 0)];
    $keyB = $b['sort_key'] ?? [(int) ($b['oldest_id'] ?? 0), (int) ($b['id'] ?? 0)];
    return $keyA <=> $keyB;
  });

  $allocation = [];
  $remaining = max(0, $batchSize);
  foreach ($schedules as $schedule) {
    if ($remaining <= 0) {
      break;
    }

    $pending = max(0, (int) ($schedule['pending'] ?? 0));
    $give = min($remaining, $pending);
    if ($give > 0) {
      $allocation[(int) $schedule['id']] = $give;
      $remaining -= $give;
    }
  }

  return $allocation;
}
