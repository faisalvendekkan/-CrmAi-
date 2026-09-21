<div class="page-head">
  <div>
    <a class="back" href="<?= e(url('settings')) ?>"><?= icon('arrow-left', 'i i-sm') ?>Settings</a>
    <h1>Activity log</h1>
    <p>Sign-ins, changes, exports and settings updates, newest first.</p>
  </div>
</div>
<section class="panel">
  <?php if (!$rows): ?><div class="empty"><?= icon('activity') ?><strong>No activity yet</strong></div><?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>When</th><th>Who</th><th>What</th><th>Area</th><th>IP address</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="nowrap num soft" title="<?= e($r['created_at']) ?>"><?= e(fmt_datetime($r['created_at'])) ?></td>
          <td class="nowrap"><?= e($r['user_name'] ?? 'System') ?></td>
          <td><?= e($r['summary'] ?: humanize($r['action'])) ?></td>
          <td><span class="badge"><?= e(humanize($r['module'] ?: 'app')) ?></span></td>
          <td class="num soft"><?= e($r['ip']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <div class="pager"><span>Page <?= $page ?> of <?= $pages ?></span><div class="page-actions">
    <?php if ($page > 1): ?><a class="btn btn-sm" href="<?= e(url('activity', ['page' => $page - 1])) ?>">Newer</a><?php endif; ?>
    <?php if ($page < $pages): ?><a class="btn btn-sm" href="<?= e(url('activity', ['page' => $page + 1])) ?>">Older</a><?php endif; ?>
  </div></div>
  <?php endif; ?>
</section>
