<?php

/**
 * Settings for batchreminders.
 *
 * batchreminders_batch_size is intentionally one setting for both the render
 * limit (civi.actionSchedule.prepareMailingQuery LIMIT) and the send-side
 * fallback (hook_civicrm_alterMailParams). If those were separate values, one
 * could render recipients that are not sent, wasting expensive token work that
 * would be repeated next run, or allow sending more than was rendered. One
 * shared cap keeps both sides aligned.
 */
return [
  'batchreminders_batch_size' => [
    'group_name' => 'Batch Reminders Preferences',
    'group' => 'batchreminders',
    'name' => 'batchreminders_batch_size',
    'type' => 'Integer',
    'default' => 20,
    'add' => '3.1',
    'title' => 'Batch size for scheduled reminders (render + send per cron run)',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Maximum number of scheduled-reminder recipients to render and send in one Send Scheduled Reminders run. This one number caps both the render-query LIMIT and the send-side safety net, so they can never drift apart.',
    'help_text' => 'Use one shared cap for rendering and sending to avoid rendering recipients that cannot be sent in the same cron run.',
  ],
  'batchreminders_paused' => [
    'group_name' => 'Batch Reminders Preferences',
    'group' => 'batchreminders',
    'name' => 'batchreminders_paused',
    'type' => 'Boolean',
    'default' => 0,
    'add' => '3.1',
    'title' => 'Pause scheduled reminders',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'While enabled, nothing is rendered or sent for any scheduled reminder until this is turned off again. This is a manual emergency brake, not a replacement for disabling one specific reminder.',
    'help_text' => 'Use the Batch Reminders Status page to pause or resume all scheduled reminders.',
  ],
];
