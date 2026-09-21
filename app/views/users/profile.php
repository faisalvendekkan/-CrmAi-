<div class="page-head">
  <div>
    <h1>Your profile</h1>
    <p>Signed in as <?= e($u['email']) ?>, <?= e(strtolower(Auth::ROLES[$u['role']] ?? $u['role'])) ?>. Last sign-in <?= e(fmt_datetime($u['last_login_at'])) ?>.</p>
  </div>
</div>
<form class="panel" method="post" action="<?= e(url('profile')) ?>" data-busy style="max-width:760px">
  <?= csrf_field() ?>
  <div class="form-section">
    <h3>Name</h3>
    <p>Shown in the activity log and to other users.</p>
    <div class="field"><label for="name">Full name</label><input id="name" name="name" value="<?= e($u['name']) ?>" required maxlength="120"></div>
  </div>
  <div class="form-section">
    <h3>Change password</h3>
    <p>Changing it signs you out on every other device.</p>
    <div class="form-grid">
      <div class="field full"><label for="current_password">Current password</label><input id="current_password" name="current_password" type="password" autocomplete="current-password"></div>
      <div class="field"><label for="password">New password</label><div class="pw-wrap"><input id="password" name="password" type="password" autocomplete="new-password" data-strength="p-meter"><button class="pw-toggle" type="button" data-action="pw-toggle" aria-label="Show password">Show</button></div><div class="strength" id="p-meter"><i></i><i></i><i></i><i></i></div></div>
      <div class="field"><label for="password_confirm">Repeat new password</label><input id="password_confirm" name="password_confirm" type="password" autocomplete="new-password"></div>
    </div>
  </div>
  <div class="form-actions"><button class="btn btn-primary" type="submit">Save profile</button></div>
</form>
