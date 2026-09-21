<?php
$id = (int) $u['id'];
$owner = (int) $u['is_owner'] === 1;
$self = $id === (int) user()['id'];
?>
<div class="page-head">
  <div>
    <a class="back" href="<?= e(url('users')) ?>"><?= icon('arrow-left', 'i i-sm') ?>Users & access</a>
    <h1><?= e($title) ?></h1>
  </div>
</div>

<form class="panel" method="post" action="<?= e(url('users/save')) ?>" data-busy data-user-form>
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= $id ?>">
  <div class="form-section">
    <h3>Account</h3>
    <p>They sign in with this email.</p>
    <div class="form-grid">
      <div class="field"><label for="name">Full name<span class="req">*</span></label><input id="name" name="name" value="<?= e($u['name']) ?>" required maxlength="120"></div>
      <div class="field"><label for="email">Email<span class="req">*</span></label><input id="email" name="email" type="email" value="<?= e($u['email']) ?>" required autocomplete="off"></div>
      <div class="field">
        <label for="password"><?= $id ? 'New password' : 'Temporary password' ?><?= $id ? '' : '<span class="req">*</span>' ?></label>
        <div class="pw-wrap"><input id="password" name="password" type="password" autocomplete="new-password" <?= $id ? '' : 'required' ?> data-strength="u-meter" placeholder="<?= $id ? 'Leave empty to keep the current password' : '' ?>"><button class="pw-toggle" type="button" data-action="pw-toggle" aria-label="Show password">Show</button></div>
        <div class="strength" id="u-meter"><i></i><i></i><i></i><i></i></div>
      </div>
      <div class="field" style="display:flex;align-items:flex-end;padding-bottom:10px">
        <label class="check"><input type="checkbox" name="must_change_password" value="1" <?= $id ? '' : 'checked' ?>><span>Ask them to set their own password at first sign-in</span></label>
      </div>
    </div>
  </div>

  <div class="form-section">
    <h3>Role</h3>
    <p><?= $owner ? 'The owner is always an active administrator.' : ($self ? 'You cannot change your own role.' : 'Choose a starting point, then fine-tune access below.') ?></p>
    <div class="seg" role="radiogroup" aria-label="Role">
      <?php foreach (Auth::ROLES as $k => $l): ?>
        <label><input type="radio" name="role" value="<?= e($k) ?>" <?= $u['role'] === $k ? 'checked' : '' ?> <?= ($owner || $self) && $u['role'] !== $k ? 'disabled' : '' ?> data-role><?= e($l) ?></label>
      <?php endforeach; ?>
    </div>
    <?php if (!$owner && !$self): ?>
      <div class="seg" style="margin-left:10px" role="radiogroup" aria-label="Status">
        <label><input type="radio" name="status" value="active" <?= $u['status'] === 'active' ? 'checked' : '' ?>>Active</label>
        <label><input type="radio" name="status" value="suspended" <?= $u['status'] === 'suspended' ? 'checked' : '' ?>>Suspended</label>
      </div>
    <?php else: ?><input type="hidden" name="status" value="active"><?php endif; ?>
  </div>

  <div class="form-section" id="perm-section" <?= $u['role'] === 'admin' ? 'hidden' : '' ?>>
    <h3>Access by area</h3>
    <p>Viewers are limited to view access even if edit is selected.</p>
    <div class="table-wrap"><table>
      <thead><tr><th>Area</th><th>Access</th></tr></thead>
      <tbody>
        <?php foreach (Auth::PERMISSIONS as $k => $l): $cur = $perms[$k] ?? 'none'; ?>
          <tr>
            <td class="cell-title"><?= e($l) ?></td>
            <td><div class="seg">
              <?php foreach (['none' => 'No access', 'view' => 'View', 'edit' => 'View & edit'] as $lv => $ll): ?>
                <label><input type="radio" name="perm[<?= e($k) ?>]" value="<?= $lv ?>" <?= $cur === $lv ? 'checked' : '' ?> <?= $lv === 'edit' ? 'data-edit-level' : '' ?>><?= $ll ?></label>
              <?php endforeach; ?>
            </div></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>

  <div class="form-actions">
    <button class="btn btn-primary" type="submit"><?= $id ? 'Save changes' : 'Add user' ?></button>
    <a class="btn btn-quiet" href="<?= e(url('users')) ?>">Cancel</a>
  </div>
</form>
