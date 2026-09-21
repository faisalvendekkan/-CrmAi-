<?php
$hour = (int) date('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$first = explode(' ', trim($u['name']))[0];
$expiredN = count(array_filter($horizon, fn ($a) => $a['days'] < 0));
$windowN  = count(array_filter($horizon, fn ($a) => $a['days'] >= 0 && $a['days'] <= $window));
$laterN   = count(array_filter($horizon, fn ($a) => $a['days'] > $window));

// Position blips on the track: overdue zone 0–11%, then 12%–98% for 0–90 days.
$stack = [];
$blips = [];
foreach ($horizon as $a) {
    $x = $a['days'] < 0 ? max(1.5, 10 - (abs($a['days']) / 60) * 8.5) : 12 + ($a['days'] / 90) * 86;
    $bucket = (int) round($x / 1.6);
    $n = $stack[$bucket] = ($stack[$bucket] ?? -1) + 1;
    $y = 50 + ($n === 0 ? 0 : (($n % 2 ? -1 : 1) * ceil($n / 2) * 16));
    if ($y < 8 || $y > 78) continue;
    $cls = $a['days'] < 0 ? 'r-expired' : ($a['days'] <= $window ? '' : 'r-later');
    $blips[] = [$x, $y, $cls, $a];
}
$windowPct = 12 + (min($window, 90) / 90) * 86 - 12;
?>
<div class="page-head greeting">
  <div>
    <h1><?= e("$greet, $first") ?></h1>
    <p><?= e(date('l, j F Y')) ?>. <?= $attentionTotal ? e($attentionTotal . ' ' . ($attentionTotal === 1 ? 'item needs' : 'items need') . ' attention within ' . $window . ' days.') : 'Nothing is due within your alert window.' ?></p>
  </div>
  <?php if (can('assistant') && AI::configured()): ?>
    <div class="page-actions"><button class="btn" type="button" data-prompt="Brief me on what needs attention this week, grouped by urgency."><?= icon('spark') ?>Brief me</button></div>
  <?php endif; ?>
</div>

<section class="panel horizon" aria-labelledby="horizon-title">
  <div class="horizon-head">
    <div>
      <h2 id="horizon-title">Renewal horizon</h2>
      <p>Every ID, visa, document, vehicle and deadline over the next 90 days.</p>
    </div>
    <div class="horizon-figures">
      <div class="f-danger"><b><?= $expiredN ?></b><span>Overdue</span></div>
      <div class="f-accent"><b><?= $windowN ?></b><span>Within <?= (int) $window ?> days</span></div>
      <div><b><?= $laterN ?></b><span>Later</span></div>
    </div>
  </div>
  <div class="track" role="list" aria-label="Upcoming expiries">
    <div class="track-overdue"></div>
    <div class="track-window" style="width:<?= number_format($windowPct, 2, '.', '') ?>%" data-label="Alert window"></div>
    <?php if (!$blips): ?><div class="horizon-empty">No expiries or deadlines in the next 90 days.</div><?php endif; ?>
    <?php foreach ($blips as [$x, $y, $cls, $a]): ?>
      <a class="blip <?= $cls ?>" role="listitem" href="<?= e(url($a['link'])) ?>" style="left:<?= number_format($x, 2, '.', '') ?>%;top:<?= $y ?>%" data-tip="<?= e($a['title'] . ' — ' . $a['label'] . ', ' . rel_days($a['days'])) ?>" aria-label="<?= e($a['title'] . ', ' . $a['label'] . ', ' . rel_days($a['days'])) ?>"></a>
    <?php endforeach; ?>
    <span class="tick first" style="left:0">Overdue</span>
    <span class="tick" style="left:12%">Today</span>
    <span class="tick" style="left:<?= 12 + 86 / 3 ?>%">30 days</span>
    <span class="tick" style="left:<?= 12 + 86 * 2 / 3 ?>%">60 days</span>
    <span class="tick" style="left:98%">90 days</span>
  </div>
</section>

<?php if ($stats): ?>
<div class="metrics">
  <?php if (isset($stats['employees'])): ?><a class="metric" href="<?= e(url('employees')) ?>"><b><?= $stats['employees'] ?></b><span>Active employees</span></a><?php endif; ?>
  <?php if (isset($stats['on_leave'])): ?><a class="metric" href="<?= e(url('leave', ['status' => 'approved'])) ?>"><b><?= $stats['on_leave'] ?></b><span>On leave today</span></a><?php endif; ?>
  <?php if (isset($stats['pending_leave'])): ?><a class="metric <?= $stats['pending_leave'] ? 'tone-warn' : '' ?>" href="<?= e(url('leave', ['status' => 'pending'])) ?>"><b><?= $stats['pending_leave'] ?></b><span>Leave to approve</span></a><?php endif; ?>
  <?php if (isset($stats['open_tasks'])): ?><a class="metric" href="<?= e(url('tasks')) ?>"><b><?= $stats['open_tasks'] ?></b><span>Open tasks<?= $stats['overdue_tasks'] ? ', ' . $stats['overdue_tasks'] . ' overdue' : '' ?></span></a><?php endif; ?>
</div>
<?php endif; ?>

<div class="grid-2">
  <section class="panel">
    <div class="panel-head">
      <div><h2>Needs attention</h2><p>Soonest first, within your <?= (int) $window ?>-day alert window</p></div>
      <?php if (can('notifications')): ?><a class="link-more" href="<?= e(url('alerts')) ?>">All alerts<?= icon('chevron', 'i i-sm') ?></a><?php endif; ?>
    </div>
    <?php if (!$attention): ?>
      <div class="empty"><?= icon('check-circle') ?><strong>All clear</strong>No renewals or deadlines fall inside the alert window.</div>
    <?php else: ?>
      <ul class="list">
        <?php foreach ($attention as $a): $t = $a['days'] < 0 ? 'danger' : 'warn'; ?>
          <li>
            <a class="grow" href="<?= e(url($a['link'])) ?>"><div class="title"><?= e($a['title']) ?></div><div class="sub"><?= e($a['sub']) ?></div></a>
            <div class="when tone-<?= $t ?>"><?= e(rel_days($a['days'])) ?><div class="muted"><?= e(fmt_date($a['date'])) ?></div></div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <div class="stack">
    <?php if ($attendance): $total = max(1, $attendance['active']); ?>
    <section class="panel">
      <div class="panel-head"><div><h2>Attendance today</h2><p><?= $attendance['active'] - $attendance['unmarked'] ?> of <?= $attendance['active'] ?> marked</p></div><a class="link-more" href="<?= e(url('attendance')) ?>">Roster<?= icon('chevron', 'i i-sm') ?></a></div>
      <div class="panel-body">
        <div class="att-bar" aria-hidden="true">
          <?php foreach (['present', 'remote', 'late', 'on_leave', 'absent'] as $s): if (!empty($attendance['marks'][$s])): ?><i class="c-<?= $s ?>" style="width:<?= round($attendance['marks'][$s] / $total * 100, 2) ?>%"></i><?php endif; endforeach; ?>
        </div>
        <div class="att-legend">
          <?php foreach (['present' => 'Present', 'remote' => 'Remote', 'late' => 'Late', 'on_leave' => 'On leave', 'absent' => 'Absent'] as $s => $l): ?>
            <span><i class="sw c-<?= $s ?>"></i><?= $l ?><b><?= (int) ($attendance['marks'][$s] ?? 0) ?></b></span>
          <?php endforeach; ?>
          <span><i class="sw c-unmarked"></i>Not marked<b><?= $attendance['unmarked'] ?></b></span>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <?php if (can('leave')): ?>
    <section class="panel">
      <div class="panel-head"><div><h2>Leave to approve</h2></div><a class="link-more" href="<?= e(url('leave', ['status' => 'pending'])) ?>">All leave<?= icon('chevron', 'i i-sm') ?></a></div>
      <?php if (!$pending): ?>
        <div class="empty" style="padding:24px">No pending requests.</div>
      <?php else: ?>
        <ul class="list">
          <?php foreach ($pending as $l): ?>
            <li>
              <div class="grow"><div class="title"><?= e($l['name'] ?? 'Unknown') ?></div><div class="sub"><?= e(humanize($l['leave_type'])) ?>, <?= e(fmt_date($l['start_date'], false)) ?> – <?= e(fmt_date($l['end_date'])) ?> (<?= (float) $l['days'] ?> d)</div></div>
              <?php if (can('leave', 'edit')): ?>
                <form method="post" action="<?= e(url('leave/' . $l['id'] . '/decide')) ?>" class="row-actions" style="opacity:1">
                  <?= csrf_field() ?><input type="hidden" name="return" value="dashboard">
                  <button class="btn btn-sm" name="decision" value="approved">Approve</button>
                  <button class="btn btn-sm btn-quiet" name="decision" value="rejected">Reject</button>
                </form>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if (can('tasks')): ?>
    <section class="panel">
      <div class="panel-head"><div><h2>Open tasks</h2></div><a class="link-more" href="<?= e(url('tasks')) ?>">All tasks<?= icon('chevron', 'i i-sm') ?></a></div>
      <?php if (!$tasks): ?>
        <div class="empty" style="padding:24px">No open tasks.</div>
      <?php else: ?>
        <ul class="list">
          <?php foreach ($tasks as $t): $d = days_until($t['deadline']); ?>
            <li>
              <span class="prio p-<?= e($t['priority']) ?>" title="<?= e(humanize($t['priority'])) ?> priority"></span>
              <a class="grow" href="<?= e(url('tasks/' . $t['id'] . '/edit')) ?>"><div class="title"><?= e($t['title']) ?></div><div class="sub"><?= e($t['owner'] ?: 'No owner') ?></div></a>
              <?php if ($d !== null): ?><div class="when <?= $d < 0 ? 'tone-danger' : ($d <= 2 ? 'tone-warn' : 'muted') ?>"><?= e(rel_days($d)) ?></div><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
    <?php endif; ?>
  </div>
</div>
