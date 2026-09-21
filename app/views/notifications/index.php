<?php
$window = (int) setting('alert_window', 30);
$expired = array_filter($items, fn ($i) => $i['risk'] === 'expired');
$due = array_filter($items, fn ($i) => $i['risk'] === 'due');
$byCat = [];
foreach ($items as $i) { $byCat[$i['category']][] = $i; }
?>
<div class="page-head">
  <div>
    <h1>Expiry alerts</h1>
    <p>Everything that has expired or falls due within <?= $window ?> days, across IDs, visas, documents, vehicles and deadlines.</p>
  </div>
  <?php if (can('assistant') && AI::configured() && $items): ?><div class="page-actions"><button class="btn" type="button" data-prompt="Draft an email to our PRO and admin team listing every renewal that is overdue or due soon, grouped by urgency, with dates."><?= icon('spark') ?>Draft renewal email</button></div><?php endif; ?>
</div>

<div class="metrics">
  <div class="metric <?= $expired ? 'tone-danger' : '' ?>"><b><?= count($expired) ?></b><span>Expired</span></div>
  <div class="metric <?= $due ? 'tone-warn' : '' ?>"><b><?= count($due) ?></b><span>Due within <?= $window ?> days</span></div>
  <div class="metric"><b><?= count($enabled) ?></b><span>Categories watched</span></div>
</div>

<div class="grid-2">
  <section class="panel">
    <div class="panel-head"><div><h2>Alerts</h2><p>Soonest first</p></div></div>
    <?php if (!$items): ?>
      <div class="empty"><?= icon('check-circle') ?><strong>All clear</strong>Nothing has expired or is due within <?= $window ?> days.</div>
    <?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>Item</th><th>Type</th><th>Date</th><th>When</th></tr></thead>
        <tbody>
          <?php foreach ($items as $i): ?>
            <tr>
              <td><a class="cell-title" href="<?= e(url($i['link'])) ?>"><?= e($i['title']) ?></a><div class="cell-sub"><?= e(Alerts::CATEGORIES[$i['category']]) ?></div></td>
              <td><?= e($i['label']) ?></td>
              <td class="num nowrap"><?= e(fmt_date($i['date'])) ?></td>
              <td><span class="badge tone-<?= $i['risk'] === 'expired' ? 'danger' : 'warn' ?>"><?= e(rel_days($i['days'])) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </section>

  <form class="panel" method="post" action="<?= e(url('alerts/settings')) ?>">
    <?= csrf_field() ?>
    <div class="panel-head"><div><h2>Alert settings</h2><p>Applies to everyone</p></div></div>
    <fieldset <?= $canEdit ? '' : 'disabled' ?> style="border:0;margin:0;padding:0">
      <div class="form-section" style="padding-top:0">
        <span class="label">Alert window</span>
        <div class="seg" role="radiogroup" aria-label="Alert window">
          <?php foreach ([7, 15, 30, 60, 90] as $d): ?><label><input type="radio" name="alert_window" value="<?= $d ?>" <?= $window === $d ? 'checked' : '' ?>><?= $d ?> days</label><?php endforeach; ?>
        </div>
      </div>
      <div class="form-section">
        <h3>Watch these</h3>
        <p>Turn off categories you do not track.</p>
        <div style="display:grid;gap:10px">
          <?php foreach (Alerts::CATEGORIES as $k => $l): ?>
            <label class="check"><input type="checkbox" name="categories[]" value="<?= e($k) ?>" <?= in_array($k, $enabled, true) ? 'checked' : '' ?>><span><?= e($l) ?></span></label>
          <?php endforeach; ?>
          <label class="check"><input type="checkbox" name="alert_include_expired" value="1" <?= setting('alert_include_expired') === '1' ? 'checked' : '' ?>><span>Keep showing expired items<small>Until they are renewed or updated</small></span></label>
        </div>
      </div>
      <div class="form-section">
        <h3>Notify me</h3>
        <p>Browser alerts show once a day while the app is open.</p>
        <div style="display:grid;gap:10px">
          <label class="check"><input type="checkbox" name="alert_browser" value="1" <?= setting('alert_browser') === '1' ? 'checked' : '' ?> data-notify-permission><span>Desktop notifications in the browser</span></label>
          <label class="check"><input type="checkbox" name="digest_enabled" value="1" <?= setting('digest_enabled') === '1' ? 'checked' : '' ?>><span>Daily email digest<small>Needs a cron job in hPanel. See the README.</small></span></label>
          <div class="field"><label for="digest_recipients">Email recipients</label><input id="digest_recipients" name="digest_recipients" value="<?= e(setting('digest_recipients')) ?>" placeholder="hr@company.qa, pro@company.qa"></div>
        </div>
      </div>
      <?php if ($canEdit): ?><div class="form-actions"><button class="btn btn-primary" type="submit">Save alert settings</button></div><?php endif; ?>
    </fieldset>
  </form>
</div>
