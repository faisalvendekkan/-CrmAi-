<?php
$allOk = !in_array(false, array_column($checks, 'ok'), true);
$tzs = ['Asia/Qatar', 'Asia/Dubai', 'Asia/Riyadh', 'Asia/Kuwait', 'Asia/Bahrain', 'Asia/Muscat', 'Asia/Kolkata', 'Europe/London', 'UTC'];
?>
<form class="setup" method="post" action="<?= e(url('setup')) ?>" id="setup-form" autocomplete="off" data-busy>
  <?= csrf_field() ?>
  <div class="setup-side">
    <div class="brand">
      <span class="brand-mark"><?php View::partial('logo'); ?></span>
      <span><strong><?= e(APP_NAME) ?></strong><span>First-time setup</span></span>
    </div>
    <ol class="steps" id="steps">
      <li class="on"><span>Server check</span></li>
      <li><span>Database</span></li>
      <li><span>Company & admin</span></li>
      <li><span>AI assistant</span></li>
    </ol>
    <p class="foot">This setup runs once. When you finish, it is locked permanently and your database password is stored in a private file outside the website folder.</p>
  </div>

  <div class="setup-main">
    <!-- Step 1 -->
    <section class="setup-step on" data-step="1">
      <h2>Check the server</h2>
      <p class="lead">Meridian HR needs a few PHP features that Hostinger provides by default.</p>
      <ul class="checklist">
        <?php foreach ($checks as $c): $st = !$c['ok'] ? 'bad' : (!empty($c['warn']) ? 'warn' : 'ok'); ?>
          <li><span class="st <?= $st ?>"><?= $st === 'ok' ? '✓' : ($st === 'bad' ? '!' : '•') ?></span><span class="grow"><?= e($c['label']) ?><small><?= e($c['detail']) ?></small></span></li>
        <?php endforeach; ?>
      </ul>
      <?php if (!$allOk): ?>
        <div class="notice warn" style="margin-top:16px"><?= icon('alert') ?><span>Fix the items marked with ! and reload this page. In hPanel, open Advanced → PHP Configuration.</span></div>
      <?php endif; ?>
    </section>

    <!-- Step 2 -->
    <section class="setup-step" data-step="2">
      <h2>Connect the MySQL database</h2>
      <p class="lead">Create a database in hPanel → Databases → MySQL Databases, then enter the same details here.</p>
      <div class="form-grid">
        <div class="field"><label for="db_host">Host</label><input id="db_host" name="db_host" value="<?= e(old('db_host', 'localhost')) ?>" required></div>
        <div class="field"><label for="db_port">Port</label><input id="db_port" name="db_port" type="number" value="<?= e(old('db_port', '3306')) ?>" required></div>
        <div class="field full"><label for="db_name">Database name</label><input id="db_name" name="db_name" value="<?= e(old('db_name')) ?>" placeholder="u123456789_hr" required></div>
        <div class="field"><label for="db_user">Username</label><input id="db_user" name="db_user" value="<?= e(old('db_user')) ?>" placeholder="u123456789_admin" required></div>
        <div class="field"><label for="db_pass">Password</label><div class="pw-wrap"><input id="db_pass" name="db_pass" type="password" autocomplete="new-password"><button class="pw-toggle" type="button" data-action="pw-toggle" aria-label="Show password">Show</button></div></div>
      </div>
      <div style="margin-top:18px"><button class="btn" type="button" data-action="db-test"><?= icon('db') ?>Test connection</button></div>
      <p class="db-status" id="db-status" role="status"></p>
    </section>

    <!-- Step 3 -->
    <section class="setup-step" data-step="3">
      <h2 id="step3-title">Your company and administrator account</h2>
      <p class="lead" id="step3-lead">This account has full access, including users and settings. You can add more people after setup.</p>
      <div class="form-grid" id="new-install-fields">
        <div class="field"><label for="company_name">Company name</label><input id="company_name" name="company_name" value="<?= e(old('company_name')) ?>" placeholder="Al Noor Trading W.L.L."></div>
        <div class="field"><label for="timezone">Time zone</label><select id="timezone" name="timezone"><?php foreach ($tzs as $tz): ?><option <?= old('timezone', 'Asia/Qatar') === $tz ? 'selected' : '' ?>><?= e($tz) ?></option><?php endforeach; ?></select></div>
        <div class="field full"><label for="admin_name">Your full name</label><input id="admin_name" name="admin_name" value="<?= e(old('admin_name')) ?>" autocomplete="name"></div>
      </div>
      <div class="form-grid" style="margin-top:18px">
        <div class="field full"><label for="admin_email">Email</label><input id="admin_email" name="admin_email" type="email" value="<?= e(old('admin_email')) ?>" autocomplete="username" required></div>
        <div class="field"><label for="admin_password">Password</label><div class="pw-wrap"><input id="admin_password" name="admin_password" type="password" autocomplete="new-password" required data-strength="setup-meter"><button class="pw-toggle" type="button" data-action="pw-toggle" aria-label="Show password">Show</button></div><div class="strength" id="setup-meter"><i></i><i></i><i></i><i></i></div></div>
        <div class="field" id="confirm-field"><label for="admin_password_confirm">Repeat password</label><input id="admin_password_confirm" name="admin_password_confirm" type="password" autocomplete="new-password"></div>
        <p class="help full" id="pw-help" style="margin:-6px 0 0;font-size:12.5px;color:var(--muted)">At least 10 characters with letters and numbers. Stored with Argon2id hashing — nobody can read it, including us.</p>
        <label class="check full" id="sample-field"><input type="checkbox" name="sample_data" value="1" <?= old('sample_data') ? 'checked' : '' ?>><span>Add sample records<small>Eight example employees with documents, assets, leave and tasks so you can explore. Delete them any time.</small></span></label>
      </div>
    </section>

    <!-- Step 4 -->
    <section class="setup-step" data-step="4">
      <h2>AI assistant <span class="muted" style="font-weight:400">— optional</span></h2>
      <p class="lead">The assistant answers questions about your records and drafts letters and emails. Add an API key now or later in Settings. The key is encrypted on your server and never sent to browsers.</p>
      <div class="form-grid">
        <div class="field"><label for="ai_provider">Provider</label><select id="ai_provider" name="ai_provider"><?php foreach (AI::PROVIDERS as $k => $p): ?><option value="<?= e($k) ?>"><?= e($p['label']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="ai_model">Model <span class="muted" style="font-weight:400">(optional)</span></label><input id="ai_model" name="ai_model" placeholder="Default: <?= e(AI::PROVIDERS['anthropic']['model']) ?>"></div>
        <div class="field full"><label for="ai_key">API key</label><input id="ai_key" name="ai_key" type="password" autocomplete="off" placeholder="Leave empty to skip"><p class="help">Get a key at console.anthropic.com, platform.openai.com or aistudio.google.com.</p></div>
      </div>
      <div class="notice" style="margin-top:20px"><?= icon('lock') ?><span><strong>When you finish</strong>, the setup page closes for good. Your database login is saved to a private file that GitHub deployments never touch.</span></div>
    </section>

    <div class="setup-nav">
      <button class="btn btn-quiet" type="button" data-action="setup-back" hidden><?= icon('arrow-left') ?>Back</button>
      <span></span>
      <button class="btn btn-primary" type="button" data-action="setup-next" <?= $allOk ? '' : 'disabled' ?>>Continue</button>
      <button class="btn btn-primary" type="submit" data-action="setup-finish" hidden><?= icon('check-circle') ?>Finish setup</button>
    </div>
  </div>
</form>
