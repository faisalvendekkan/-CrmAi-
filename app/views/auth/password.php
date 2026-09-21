<div class="auth-card">
  <div class="auth-brand">
    <span class="brand-mark"><?php View::partial('logo'); ?></span>
    <h1>Set your password</h1>
    <p>Your administrator gave you a temporary password. Choose your own to continue.</p>
  </div>
  <form class="panel" method="post" action="<?= e(url('password')) ?>" data-busy>
    <?= csrf_field() ?>
    <div class="field">
      <label for="password">New password</label>
      <div class="pw-wrap">
        <input id="password" name="password" type="password" autocomplete="new-password" required minlength="10" data-strength="pw-meter">
        <button class="pw-toggle" type="button" data-action="pw-toggle" aria-label="Show password">Show</button>
      </div>
      <div class="strength" id="pw-meter" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
      <p class="help">At least 10 characters with letters and numbers.</p>
    </div>
    <div class="field">
      <label for="password_confirm">Repeat new password</label>
      <input id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" required>
    </div>
    <div class="field" style="margin-top:22px">
      <button class="btn btn-primary btn-block" type="submit">Save password</button>
    </div>
  </form>
  <form class="auth-foot" method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?><button class="btn btn-quiet btn-sm" type="submit">Sign out</button></form>
</div>
