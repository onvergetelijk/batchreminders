<div class="crm-content-block batchreminders-status">
  <style>
    .batchreminders-actions { display: flex; align-items: center; gap: 12px; margin: 0 0 16px; flex-wrap: wrap; }
    .batchreminders-pause-button { padding: 6px 16px; }
    .batchreminders-pause-button.is-paused { background: #b71c1c; border-color: #b71c1c; color: #fff; }
    .batchreminders-warning { color: #b71c1c; font-weight: 600; }
    .batchreminders-ok { color: #2e7d32; font-weight: 600; }
    .batchreminders-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin: 0 0 18px; }
    .batchreminders-summary-item { border: 1px solid #ddd; background: #fff; padding: 10px 12px; border-radius: 4px; }
    .batchreminders-summary-item h3 { margin: 0 0 4px; color: #555; font-size: 12px; text-transform: uppercase; }
    .batchreminders-summary-item .batchreminders-value { font-size: 20px; font-weight: 700; }
    .batchreminders-table { width: 100%; border-collapse: collapse; margin: 0 0 16px; }
    .batchreminders-table th, .batchreminders-table td { border: 1px solid #ddd; padding: 5px 8px; text-align: left; vertical-align: top; }
    .batchreminders-table th { background: #f3f3f3; }
    .batchreminders-inactive { color: #777; font-style: italic; }
    .batchreminders-reload { font-size: 12px; }
  </style>

  <div class="batchreminders-actions">
    <button type="button" id="batchreminders-pause-toggle" class="crm-button batchreminders-pause-button{if $paused} is-paused{/if}">
      {if $paused}{ts}Resume scheduled reminders{/ts}{else}{ts}Pause scheduled reminders{/ts}{/if}
    </button>
    {if $paused}
      <span class="batchreminders-warning">{ts}Paused: no scheduled reminders will be rendered or sent until resumed.{/ts}</span>
    {else}
      <span class="batchreminders-ok">{ts}Running: each cron run may process one batch.{/ts}</span>
    {/if}
  </div>

  <div class="batchreminders-summary">
    <div class="batchreminders-summary-item">
      <h3>{ts}Pause status{/ts}</h3>
      <div class="batchreminders-value {if $paused}batchreminders-warning{else}batchreminders-ok{/if}">
        {if $paused}{ts}Paused{/ts}{else}{ts}Not paused{/ts}{/if}
      </div>
    </div>
    <div class="batchreminders-summary-item">
      <h3>{ts}Batch size{/ts}</h3>
      <div class="batchreminders-value">{$batchSize}</div>
    </div>
    <div class="batchreminders-summary-item">
      <h3>{ts}Total pending{/ts}</h3>
      <div class="batchreminders-value">{$totalPending}</div>
    </div>
    <div class="batchreminders-summary-item">
      <h3>{ts}Last sent{/ts}</h3>
      {if $lastSent}
        <div class="batchreminders-value">{$minutesAgo} {ts}min ago{/ts}</div>
        <div>{$lastSent|escape}</div>
      {else}
        <div class="batchreminders-value batchreminders-warning">{ts}Never{/ts}</div>
      {/if}
    </div>
  </div>

  <h2>{ts}Pending reminders by schedule{/ts}</h2>
  <table class="batchreminders-table">
    <thead>
      <tr>
        <th>{ts}Reminder ID{/ts}</th>
        <th>{ts}Title{/ts}</th>
        <th>{ts}Template ID{/ts}</th>
        <th>{ts}Template title{/ts}</th>
        <th>{ts}Status{/ts}</th>
        <th>{ts}Pending{/ts}</th>
        <th>{ts}Next run{/ts}</th>
        <th>{ts}Oldest waiting ID{/ts}</th>
      </tr>
    </thead>
    <tbody>
      {foreach from=$rows item=row}
        <tr>
          <td><a href="{crmURL p='civicrm/admin/scheduleReminders/edit' q="action=update&id=`$row.id`&reset=1"}">{$row.id}</a></td>
          <td>{$row.title|escape}</td>
          <td>
            {if $row.templateId}
              <a href="{crmURL p='civicrm/admin/messageTemplates/add' q="action=update&id=`$row.templateId`&reset=1"}" target="_blank">{$row.templateId}</a>
            {else}
              —
            {/if}
          </td>
          <td>{$row.templateTitle|escape|default:'—'}</td>
          <td>
            {if $row.is_active}
              <span class="batchreminders-ok">{ts}Active{/ts}</span>
            {else}
              <span class="batchreminders-inactive">{ts}Inactive{/ts}</span>
            {/if}
          </td>
          <td>{$row.pending}</td>
          <td>{$row.nextRun}</td>
          <td>{$row.oldest_id}</td>
        </tr>
      {foreachelse}
        <tr>
          <td colspan="8">{ts}No pending scheduled reminders.{/ts}</td>
        </tr>
      {/foreach}
    </tbody>
  </table>

  <h2>{ts}Recently sent (last 3 hours){/ts}</h2>
  <table class="batchreminders-table">
    <thead>
      <tr>
        <th>{ts}Schedule{/ts}</th>
        <th>{ts}Sent{/ts}</th>
        <th>{ts}Last sent{/ts}</th>
      </tr>
    </thead>
    <tbody>
      {foreach from=$recent item=r}
        <tr>
          <td>{$r.title|escape}</td>
          <td>{$r.sentCount}</td>
          <td>{$r.lastSent|escape}</td>
        </tr>
      {foreachelse}
        <tr>
          <td colspan="3">{ts}Nothing sent in the last 3 hours.{/ts}</td>
        </tr>
      {/foreach}
    </tbody>
  </table>

  <p class="batchreminders-reload">
    {ts}As of{/ts} {$now|escape} —
    <a href="#" onclick="location.reload(); return false;">{ts}reload now{/ts}</a> ·
    {ts}auto-refresh in{/ts} <span id="batchreminders-refresh-countdown">30</span>s
  </p>

  <script>
    (function() {
      var isPaused = {if $paused}true{else}false{/if};
      var button = document.getElementById('batchreminders-pause-toggle');
      var refreshSeconds = 30;
      var refreshEl = document.getElementById('batchreminders-refresh-countdown');

      setInterval(function() {
        refreshSeconds -= 1;
        if (refreshEl) {
          refreshEl.textContent = Math.max(0, refreshSeconds);
        }
        if (refreshSeconds <= 0) {
          location.reload();
        }
      }, 1000);

      if (!button) {
        return;
      }

      button.addEventListener('click', function() {
        var nextPaused = !isPaused;
        var message = nextPaused
          ? '{ts escape="js"}Pause all scheduled reminders? Nothing will be rendered or sent until you resume.{/ts}'
          : '{ts escape="js"}Resume scheduled reminders? The next cron run will render and send another batch.{/ts}';

        if (!confirm(message)) {
          return;
        }

        button.disabled = true;
        CRM.api4('Setting', 'set', { values: { batchreminders_paused: nextPaused } }).then(
          function() {
            location.reload();
          },
          function(err) {
            alert('{ts escape="js"}Could not update the pause setting{/ts}: ' + (err && err.error_message ? err.error_message : '?'));
            button.disabled = false;
          }
        );
      });
    })();
  </script>
</div>
