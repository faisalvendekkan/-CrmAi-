<?php
$prev = date('Y-m-d', strtotime($date . ' -1 day'));
$next = date('Y-m-d', strtotime($date . ' +1 day'));
$isToday = $date === today();
?>
<div class="page-head">
  <div>
    <h1>Attendance</h1>
    <p>Mark the daily roster in one pass. Changes save instantly.</p>
  </div>
  <div class="page-actions">
    <a class="btn" href="<?= e(url('attendance/records')) ?>"><?= icon('clock') ?>All records</a>
    <?php if ($canEdit && $unmarked): ?>
      <form method="post" action="<?= e(url('attendance/mark-all')) ?>" data-confirm="Mark <?= $unmarked ?> unmarked employees as present?" data-confirm-text="People on approved leave are marked as on leave instead." data-confirm-ok="Mark present">
        <?= csrf_field() ?><input type="hidden" name="date" value="<?= e($date) ?>">
        <button class="btn btn-primary" type="submit"><?= icon('check') ?>Mark the rest present</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="metrics">
  <?php foreach ($statuses as $s => $l): ?><div class="metric"><b data-count="<?= e($s) ?>"><?= $counts[$s] ?></b><span><?= e($l) ?></span></div><?php endforeach; ?>
  <div class="metric"><b data-count="unmarked"><?= $unmarked ?></b><span>Not marked</span></div>
</div>

<section class="panel">
  <form class="toolbar" method="get" action="<?= e(url('attendance')) ?>">
    <a class="icon-btn" href="<?= e(url('attendance', ['date' => $prev, 'dept' => $dept ?: null])) ?>" aria-label="Previous day"><?= icon('arrow-left') ?></a>
    <input type="date" name="date" value="<?= e($date) ?>" style="width:auto" data-autosubmit aria-label="Date">
    <a class="icon-btn" href="<?= e(url('attendance', ['date' => $next, 'dept' => $dept ?: null])) ?>" aria-label="Next day" style="transform:scaleX(-1)"><?= icon('arrow-left') ?></a>
    <?php if (!$isToday): ?><a class="btn btn-quiet btn-sm" href="<?= e(url('attendance', ['dept' => $dept ?: null])) ?>">Today</a><?php endif; ?>
    <span class="soft" style="margin-right:auto"><?= e(date('l, j F Y', strtotime($date))) ?></span>
    <select name="dept" data-autosubmit aria-label="Department"><option value="">All departments</option><?php foreach ($depts as $d): ?><option <?= $dept === $d ? 'selected' : '' ?>><?= e($d) ?></option><?php endforeach; ?></select>
  </form>
  <?php if (!$rows): ?>
    <div class="empty"><?= icon('users') ?><strong>No active employees</strong>Add employees first, then mark attendance here.</div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Employee</th><th>Status</th><th>Check in / out</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr class="roster-row" data-employee="<?= (int) $r['id'] ?>" data-date="<?= e($date) ?>">
            <td>
              <a class="cell-title" href="<?= e(url('employees/' . $r['id'])) ?>"><?= e($r['name']) ?></a>
              <div class="cell-sub"><?= e(trim(($r['designation'] ?: '') . ($r['department'] ? ', ' . $r['department'] : ''), ', ')) ?></div>
              <?php if ($r['leave_type']): ?><div class="leave-hint">Approved <?= e(humanize($r['leave_type'])) ?> leave</div><?php endif; ?>
            </td>
            <td>
              <?php if ($canEdit): ?>
                <div class="seg" role="radiogroup" aria-label="Status for <?= e($r['name']) ?>">
                  <?php foreach ($statuses as $s => $l): ?>
                    <label class="t-<?= ['present' => 'ok', 'late' => 'warn', 'absent' => 'danger', 'on_leave' => 'accent', 'remote' => 'accent'][$s] ?>"><input type="radio" name="st_<?= (int) $r['id'] ?>" value="<?= e($s) ?>" <?= $r['status'] === $s ? 'checked' : '' ?> data-mark><?= e($l) ?></label>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <?= $r['status'] ? badge($r['status']) : '<span class="muted">Not marked</span>' ?>
              <?php endif; ?>
            </td>
            <td class="roster-time">
              <?php if ($canEdit): ?>
                <div style="display:flex;gap:6px;align-items:center">
                  <input type="time" value="<?= e($r['check_in'] ? substr($r['check_in'], 0, 5) : '') ?>" data-field="check_in" aria-label="Check in">
                  <input type="time" value="<?= e($r['check_out'] ? substr($r['check_out'], 0, 5) : '') ?>" data-field="check_out" aria-label="Check out">
                </div>
              <?php else: ?>
                <?= e(($r['check_in'] ? substr($r['check_in'], 0, 5) : '—') . ' / ' . ($r['check_out'] ? substr($r['check_out'], 0, 5) : '—')) ?>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>
