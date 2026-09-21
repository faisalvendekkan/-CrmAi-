<?php
$fields = Modules::get('employees')['fields'];
$ret = 'employees/' . $e['id'];
$left = max(0, $entitled - $used);
$pct = $entitled > 0 ? min(100, $used / $entitled * 100) : 0;
$fmtNum = fn ($n) => rtrim(rtrim(number_format((float) $n, 1, '.', ''), '0'), '.');
?>
<a class="back" href="<?= e(url('employees')) ?>"><?= icon('arrow-left', 'i i-sm') ?>Employees</a>

<section class="panel">
  <div class="profile-head">
    <span class="avatar lg"><?= e(initials($e['name'])) ?></span>
    <div>
      <h1><?= e($e['name']) ?></h1>
      <div class="meta">
        <span><?= e($e['designation'] ?: 'No designation') ?></span>
        <span class="muted">in</span>
        <span><?= e($e['department'] ?: 'No department') ?></span>
        <?= badge($e['status'], $fields['status']['options'][$e['status']] ?? null) ?>
      </div>
    </div>
    <div class="actions">
      <?php if (can('assistant') && AI::configured()): ?><button class="btn" type="button" data-prompt="Summarise <?= e($e['name']) ?>'s file: documents, expiries, leave balance and anything that needs action."><?= icon('spark') ?>Summarise</button><?php endif; ?>
      <?php if (can('employees', 'edit')): ?><a class="btn btn-primary" href="<?= e(url('employees/' . $e['id'] . '/edit')) ?>"><?= icon('edit') ?>Edit</a><?php endif; ?>
    </div>
  </div>
  <dl class="facts">
    <?php foreach (['employee_no', 'nationality', 'email', 'phone', 'joining_date', 'qid', 'qid_expiry', 'passport', 'passport_expiry', 'visa_expiry'] as $k): ?>
      <div><dt><?= e($fields[$k]['label']) ?></dt><dd><?php
        if ($fields[$k]['type'] === 'date') echo isset($fields[$k]['expiry']) ? expiry_badge($e[$k]) : e(fmt_date($e[$k]));
        elseif ($k === 'email' && $e[$k]) echo '<a href="mailto:' . e($e[$k]) . '">' . e($e[$k]) . '</a>';
        elseif ($k === 'phone' && $e[$k]) echo '<a href="tel:' . e(preg_replace('/[^\d+]/', '', $e[$k])) . '">' . e($e[$k]) . '</a>';
        else echo $e[$k] ? e($e[$k]) : '<span class="muted">—</span>';
      ?></dd></div>
    <?php endforeach; ?>
  </dl>
  <?php if ($e['notes']): ?><div style="padding:14px 20px;border-top:1px solid var(--line)"><span class="label">Notes</span><p class="soft" style="white-space:pre-wrap"><?= e($e['notes']) ?></p></div><?php endif; ?>
</section>

<div class="grid-2" style="margin-top:16px">
  <div class="stack">
    <?php if (can('emp_documents')): ?>
    <section class="panel">
      <div class="panel-head"><div><h2>Documents</h2><p>Health card, permits, licences</p></div>
        <?php if (can('emp_documents', 'edit')): ?><a class="btn btn-sm" href="<?= e(url('emp-documents/new', ['employee_id' => $e['id'], 'return' => $ret])) ?>"><?= icon('plus', 'i i-sm') ?>Add</a><?php endif; ?></div>
      <?php if (!$docs): ?><div class="empty" style="padding:24px">No documents recorded.</div><?php else: ?>
        <div class="table-wrap"><table><thead><tr><th>Document</th><th>Reference</th><th>Expiry</th><th></th></tr></thead><tbody>
          <?php foreach ($docs as $d): ?>
            <tr><td class="cell-title"><?= e($d['doc_type']) ?></td><td><?= e($d['reference'] ?: '—') ?></td><td><?= expiry_badge($d['expiry_date']) ?></td>
              <td class="actions"><?php if (can('emp_documents', 'edit')): ?><a class="icon-btn" href="<?= e(url('emp-documents/' . $d['id'] . '/edit', ['return' => $ret])) ?>" aria-label="Edit"><?= icon('edit', 'i i-sm') ?></a><?php endif; ?></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if (can('leave')): ?>
    <section class="panel">
      <div class="panel-head"><div><h2>Leave history</h2></div>
        <?php if (can('leave', 'edit')): ?><a class="btn btn-sm" href="<?= e(url('leave/new', ['employee_id' => $e['id'], 'return' => $ret])) ?>"><?= icon('plus', 'i i-sm') ?>Request leave</a><?php endif; ?></div>
      <?php if (!$leave): ?><div class="empty" style="padding:24px">No leave recorded.</div><?php else: ?>
        <div class="table-wrap"><table><thead><tr><th>Type</th><th>Dates</th><th>Days</th><th>Status</th><th></th></tr></thead><tbody>
          <?php foreach ($leave as $l): ?>
            <tr><td><?= e(humanize($l['leave_type'])) ?></td><td class="num nowrap"><?= e(fmt_date($l['start_date'], false)) ?> – <?= e(fmt_date($l['end_date'])) ?></td><td class="num"><?= $fmtNum($l['days']) ?></td><td><?= badge($l['status']) ?></td>
              <td class="actions">
                <?php if (can('leave', 'edit') && $l['status'] === 'pending'): ?>
                  <form method="post" action="<?= e(url('leave/' . $l['id'] . '/decide')) ?>" style="display:inline-flex;gap:4px"><?= csrf_field() ?><input type="hidden" name="return" value="<?= e($ret) ?>"><button class="btn btn-sm" name="decision" value="approved">Approve</button><button class="btn btn-sm btn-quiet" name="decision" value="rejected">Reject</button></form>
                <?php endif; ?>
              </td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </section>
    <?php endif; ?>
  </div>

  <div class="stack">
    <?php if (can('leave')): ?>
    <section class="panel">
      <div class="panel-head"><div><h2>Annual leave <?= date('Y') ?></h2><p><?= $fmtNum($used) ?> of <?= $fmtNum($entitled) ?> days used</p></div><b class="num" style="font-size:22px;font-weight:550"><?= $fmtNum($left) ?> <span class="muted" style="font-size:13px;font-weight:400">left</span></b></div>
      <div class="panel-body"><div class="meter"><i style="width:<?= round($pct, 1) ?>%"></i></div></div>
    </section>
    <?php endif; ?>

    <?php if (can('attendance')): ?>
    <section class="panel">
      <div class="panel-head"><div><h2>Attendance, last 30 days</h2></div></div>
      <div class="panel-body">
        <?php if (!$att): ?><p class="muted">No attendance recorded.</p><?php else: ?>
        <div class="att-legend">
          <?php foreach (['present' => 'Present', 'remote' => 'Remote', 'late' => 'Late', 'on_leave' => 'On leave', 'absent' => 'Absent'] as $s => $lbl): ?>
            <span><i class="sw c-<?= $s ?>"></i><?= $lbl ?><b><?= (int) ($att[$s] ?? 0) ?></b></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </section>
    <?php endif; ?>

    <?php if (can('assets')): ?>
    <section class="panel">
      <div class="panel-head"><div><h2>Assigned assets</h2></div></div>
      <?php if (!$assets): ?><div class="empty" style="padding:24px">No vehicles or equipment assigned.</div><?php else: ?>
        <ul class="list"><?php foreach ($assets as $a): ?>
          <li><?= icon($a['asset_type'] === 'Vehicle' ? 'car' : 'apps') ?><a class="grow" href="<?= e(can('assets', 'edit') ? url('assets/' . $a['id'] . '/edit') : url('assets')) ?>"><div class="title"><?= e($a['item']) ?></div><div class="sub"><?= e($a['asset_type'] . ($a['reference'] ? ', ' . $a['reference'] : '')) ?></div></a></li>
        <?php endforeach; ?></ul>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if (can('assistant') && AI::configured()): ?>
    <section class="panel">
      <div class="panel-head"><div><h2>Draft with AI</h2><p>Letters use this employee's details</p></div></div>
      <div class="panel-body chips">
        <?php foreach (['Salary certificate', 'Experience letter', 'NOC letter', 'Visa renewal reminder', 'Warning letter'] as $doc): ?>
          <button class="chip" type="button" data-prompt="Draft a <?= e(strtolower($doc)) ?> for <?= e($e['name']) ?>, ready to print on company letterhead."><?= icon('spark') ?><?= e($doc) ?></button>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>
  </div>
</div>
