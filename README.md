# Batch Reminders

## The Problem

CiviCRM's built-in "Send Scheduled Reminders" Scheduled Job (`Job.send_reminder`, which calls `CRM_Core_BAO_ActionSchedule::processQueue()`) renders and sends all pending recipients for every active reminder schedule in one cron invocation. On sites with many reminders, large recipient lists, or slow token rendering, one cron run can take a long time or exhaust shared-hosting execution-time and memory limits.

## How It Works

This extension hooks into `civi.actionSchedule.prepareMailingQuery` to cap the scheduled-reminder render query, then uses `hook_civicrm_alterMailParams` as a send-side safety cap. Both controls read the same `batchreminders_batch_size` setting, so the render limit and send limit cannot drift apart. It also reuses CiviCRM core's `mailThrottleTime` setting, in microseconds, to pace individual scheduled-reminder sends.

## No Separate Cron Required

This extension does not register a new Scheduled Job and requires no shell script or SSH access. It piggybacks entirely on CiviCRM's existing "Send Scheduled Reminders" job. For finer-grained batching on hosts with only very basic cron access, such as Plesk where you can only schedule a URL fetch or a single command, set that job's run frequency to "Always" under Administer -> System Settings -> Scheduled Jobs, then schedule CiviCRM's own cron entry point to run every few minutes. For example, a Plesk cron job can run:

```sh
curl -s "https://YOURSITE/sites/all/modules/civicrm/bin/cron.php?name=CRONUSER&pass=CRONPASS&key=YOURSITEKEY"
```

You can also use `cv api Job.execute` or Drush, whichever the host already supports. Every invocation processes one more small batch.

## Settings

| Setting | Default | Purpose |
| --- | --- | --- |
| `batchreminders_batch_size` | `20` | Maximum scheduled-reminder recipients to render and send per cron run. |
| `batchreminders_paused` | off | Emergency pause switch; when on, no scheduled reminders are rendered or sent. |

Read or update the settings with API4:

```sh
cv api4 Setting.get '{"where":[["name","IN",["batchreminders_batch_size","batchreminders_paused"]]]}'
cv api4 Setting.set '{"values":{"batchreminders_batch_size":20,"batchreminders_paused":false}}'
```

## Status Page

Use Administer -> CiviMail -> Batch Reminders Status, or go directly to `civicrm/batchreminders/status`, for the pause button and queue overview.

## License

Copyright (C) 2026, magnolia61 <richard.van.oosterhout@gmail.com>

Licensed under the GNU Affero General Public License 3.0. The full text is in
[LICENSE.txt](LICENSE.txt).
