<div class="page-head">
  <div>
    <h1>Users & access</h1>
    <p>Who can sign in and what each person can see or change. Administrators have full access.</p>
  </div>
  <div class="page-actions"><a class="btn btn-primary" href="<?= e(url('users/new')) ?>"><?= icon('plus') ?>Add user</a></div>
</div>

<section class="panel">
  <div class="table-wrap"><table>
    <thead><tr><th>Person</th><th>Role</th><th>Access</th><th>Status</th><th>Last active</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($users as $x):
          $perms = json_decode((string) $x['permissions'], true) ?: [];
          $n = count($perms); ?>
        <tr>
          <td><div class="row-link"><span class="avatar"><?= e(initials($x['name'])) ?></span><div><a class="cell-title" href="<?= e(url('users/' . $x['id'] . '/edit')) ?>"><?= e($x['name']) ?></a><?= (int) $x['is_owner'] ? ' <span class="badge tone-accent">Owner</span>' : '' ?><div class="cell-sub"><?= e($x['email']) ?></div></div></div></td>
          <td><?= e(Auth::ROLES[$x['role']] ?? $x['role']) ?></td>
          <td class="soft"><?= $x['role'] === 'admin' ? 'Everything' : ($n ? $n . ' of ' . count(Auth::PERMISSIONS) . ' areas' : 'Dashboard only') ?></td>
          <td><?= badge($x['status']) ?><?= (int) $x['must_change_password'] ? ' <span class="badge">Password reset pending</span>' : '' ?></td>
          <td class="soft nowrap"><?= e(time_ago($x['last_active_at'])) ?></td>
          <td class="actions"><div class="row-actions">
            <a class="icon-btn" href="<?= e(url('users/' . $x['id'] . '/edit')) ?>" aria-label="Edit"><?= icon('edit', 'i i-sm') ?></a>
            <?php if ((int) $x['id'] !== (int) user()['id']): ?>
              <form method="post" action="<?= e(url('users/' . $x['id'] . '/sign-out')) ?>" data-confirm="Sign out <?= e($x['name']) ?> everywhere?" data-confirm-text="They will need to sign in again on every device." data-confirm-ok="Sign out"><?= csrf_field() ?><button class="icon-btn" type="submit" title="Sign out on all devices" aria-label="Sign out on all devices"><?= icon('logout', 'i i-sm') ?></button></form>
              <?php if (!(int) $x['is_owner']): ?>
                <form method="post" action="<?= e(url('users/' . $x['id'] . '/delete')) ?>" data-confirm="Remove <?= e($x['name']) ?>?" data-confirm-text="They lose access immediately. Their past activity stays in the log." data-confirm-ok="Remove"><?= csrf_field() ?><button class="icon-btn" type="submit" aria-label="Remove"><?= icon('trash', 'i i-sm') ?></button></form>
              <?php endif; ?>
            <?php endif; ?>
          </div></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</section>

<div class="notice" style="margin-top:16px"><?= icon('shield') ?><span><strong>How access works.</strong> Managers can view or edit the areas you choose. Viewers can only look. Passwords are hashed with Argon2id, sign-in locks after five failed attempts, and idle sessions end after <?= (int) setting('session_minutes') ?> minutes.</span></div>
