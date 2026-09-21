<?php
$key = $m['key'];
$canEdit = can($m['perm'], 'edit');
$cols = array_filter($m['fields'], fn ($f) => !empty($f['list']));
$titleCol = array_key_first($cols);
$hasFilter = array_filter($filters, fn ($v) => $v !== '');
$q = fn (array $extra) => url($key, array_filter(array_merge($filters, ['page' => null], $extra), fn ($v) => $v !== null && $v !== ''));
?>
<div class="page-head">
  <div>
    <?php if ($key === 'attendance/records'): ?><a class="back" href="<?= e(url('attendance')) ?>"><?= icon('arrow-left', 'i i-sm') ?>Daily roster</a><?php endif; ?>
    <h1><?= e($m['label']) ?></h1>
    <p><?= e($m['intro']) ?></p>
  </div>
  <div class="page-actions">
    <a class="btn" href="<?= e(url("$key/export")) ?>"><?= icon('download') ?>Export CSV</a>
    <?php if ($canEdit): ?><a class="btn btn-primary" href="<?= e(url("$key/new")) ?>"><?= icon('plus') ?>Add <?= e($m['singular']) ?></a><?php endif; ?>
  </div>
</div>

<div class="metrics">
  <?php foreach ($summary as [$label, $value, $tone, $link]):
      $active = $link && array_intersect_assoc($link, $filters) === $link; ?>
    <a class="metric <?= $value && $tone !== 'neutral' ? 'tone-' . e($tone) : '' ?> <?= $active ? 'is-active' : '' ?>" href="<?= e($link ? $q($active ? array_map(fn () => null, $link) : $link) : url($key)) ?>">
      <b><?= (int) $value ?></b><span><?= e($label) ?></span>
    </a>
  <?php endforeach; ?>
</div>

<section class="panel">
  <form class="toolbar" method="get" action="<?= e(url($key)) ?>" role="search">
    <div class="search"><?= icon('search') ?><input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Search <?= e(strtolower($m['label'])) ?>…" aria-label="Search"></div>
    <?php if (isset($m['fields']['status']['options'])): ?>
      <select name="status" aria-label="Status" data-autosubmit>
        <option value="">All statuses</option>
        <?php foreach ($m['fields']['status']['options'] as $v => $l): ?><option value="<?= e($v) ?>" <?= $filters['status'] === $v ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
      </select>
    <?php endif; ?>
    <?php if (isset($m['fields']['asset_type'])): ?>
      <select name="type" aria-label="Type" data-autosubmit>
        <option value="">All types</option>
        <?php foreach ($m['fields']['asset_type']['options'] as $v => $l): ?><option value="<?= e($v) ?>" <?= $filters['type'] === $v ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
      </select>
    <?php endif; ?>
    <?php if ($depts): ?>
      <select name="dept" aria-label="Department" data-autosubmit>
        <option value="">All departments</option>
        <?php foreach ($depts as $d): ?><option <?= $filters['dept'] === $d ? 'selected' : '' ?>><?= e($d) ?></option><?php endforeach; ?>
      </select>
    <?php endif; ?>
    <?php if ($filters['expiry'] !== ''): ?><input type="hidden" name="expiry" value="<?= e($filters['expiry']) ?>"><?php endif; ?>
    <button class="btn" type="submit">Search</button>
    <?php if ($hasFilter): ?><a class="btn btn-quiet" href="<?= e(url($key)) ?>">Clear</a><?php endif; ?>
  </form>

  <?php if (!$rows): ?>
    <div class="empty">
      <?= icon($m['icon']) ?>
      <?php if ($hasFilter): ?>
        <strong>No matches</strong>Nothing matches these filters. Try a different search or clear the filters.
      <?php else: ?>
        <strong>No <?= e(strtolower($m['label'])) ?> yet</strong>Add the first <?= e($m['singular']) ?> to start tracking it here.
        <?php if ($canEdit): ?><div><a class="btn btn-primary" href="<?= e(url("$key/new")) ?>"><?= icon('plus') ?>Add <?= e($m['singular']) ?></a></div><?php endif; ?>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr>
          <?php foreach ($cols as $col => $f): ?><th scope="col"><?= e($f['label']) ?></th><?php endforeach; ?>
          <th><span class="sr-only">Actions</span></th>
        </tr></thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <?php foreach ($cols as $col => $f): ?>
                <td>
                  <?php if ($col === $titleCol && $f['type'] !== 'employee'): ?>
                    <?php $href = $key === 'employees' ? url('employees/' . $r['id']) : ($canEdit ? url("$key/{$r['id']}/edit") : null); ?>
                    <?php if ($href): ?><a class="cell-title" href="<?= e($href) ?>"><?= e($r[$col]) ?></a><?php else: ?><span class="cell-title"><?= e($r[$col]) ?></span><?php endif; ?>
                    <?php if ($key === 'employees' && $r['employee_no']): ?><div class="cell-sub"><?= e($r['employee_no']) ?></div><?php endif; ?>
                  <?php else: ?>
                    <?php View::partial('cell', ['f' => $f, 'col' => $col, 'r' => $r]); ?>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
              <td class="actions">
                <div class="row-actions">
                  <?php if ($key === 'leave' && $canEdit && $r['status'] === 'pending'): ?>
                    <form method="post" action="<?= e(url('leave/' . $r['id'] . '/decide')) ?>" style="display:inline-flex;gap:4px">
                      <?= csrf_field() ?>
                      <button class="btn btn-sm" name="decision" value="approved">Approve</button>
                      <button class="btn btn-sm btn-quiet" name="decision" value="rejected">Reject</button>
                    </form>
                  <?php endif; ?>
                  <?php if ($canEdit): ?>
                    <a class="icon-btn" href="<?= e(url("$key/{$r['id']}/edit")) ?>" title="Edit" aria-label="Edit"><?= icon('edit', 'i i-sm') ?></a>
                    <form method="post" action="<?= e(url("$key/{$r['id']}/delete")) ?>" data-confirm="Delete this <?= e($m['singular']) ?>?" data-confirm-text="<?= $key === 'employees' ? 'Their documents, leave and attendance records are removed too. This cannot be undone.' : 'This cannot be undone.' ?>">
                      <?= csrf_field() ?><button class="icon-btn" type="submit" title="Delete" aria-label="Delete"><?= icon('trash', 'i i-sm') ?></button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="pager">
      <span><?= $total ?> <?= $total === 1 ? 'record' : 'records' ?><?= $pages > 1 ? ", page $page of $pages" : '' ?></span>
      <?php if ($pages > 1): ?>
        <div class="page-actions">
          <?php if ($page > 1): ?><a class="btn btn-sm" href="<?= e($q(['page' => $page - 1])) ?>">Previous</a><?php endif; ?>
          <?php if ($page < $pages): ?><a class="btn btn-sm" href="<?= e($q(['page' => $page + 1])) ?>">Next</a><?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</section>
