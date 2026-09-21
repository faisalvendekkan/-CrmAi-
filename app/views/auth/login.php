<div class="auth-card">
  <div class="auth-brand">
    <span class="brand-mark"><?php View::partial('logo'); ?></span>
    <h1><?= e(setting('company_name')) ?></h1>
    <p>Sign in to HR & administration</p>
  </div>
  <form class="panel" method="post" action="<?= e(url('login')) ?>" data-busy>
    <?= csrf_field() ?>
    <div class="field">
      <label for="email">Email</label>
      <input id="email" name="email" type="email" autocomplete="username" required autofocus value="<?= e(old('email')) ?>">
    </div>
    <div class="field">
      <label for="password">Password</label>
      <div class="pw-wrap">
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <button class="pw-toggle" type="button" data-action="pw-toggle" aria-label="Show password">Show</button>
      </div>
    </div>
    <div class="field" style="margin-top:22px">
      <button class="btn btn-primary btn-block" type="submit"><?= icon('lock') ?>Sign in</button>
    </div>
  </form>
  <p class="auth-foot">Forgot your password? Ask an administrator to set a new one for you.</p>
</div>
