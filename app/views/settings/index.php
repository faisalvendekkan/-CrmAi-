<?php
$tzs = ['Asia/Qatar', 'Asia/Dubai', 'Asia/Riyadh', 'Asia/Kuwait', 'Asia/Bahrain', 'Asia/Muscat', 'Asia/Kolkata', 'Europe/London', 'UTC'];
$curTz = (string) setting('timezone');
if (!in_array($curTz, $tzs, true)) $tzs[] = $curTz;
$provider = AI::provider();
?>
<div class="page-head">
  <div>
    <h1>Settings</h1>
    <p>Company details, the AI assistant, security and backups.</p>
  </div>
  <div class="page-actions"><a class="btn" href="<?= e(url('activity')) ?>"><?= icon('activity') ?>Activity log</a></div>
</div>

<div class="grid-eq">
  <div class="stack">
    <form class="panel" method="post" action="<?= e(url('settings')) ?>" data-busy>
      <?= csrf_field() ?><input type="hidden" name="section" value="general">
      <div class="panel-head"><div><h2>Company</h2><p>Shown in the sidebar, emails and AI drafts</p></div></div>
      <div class="form-section" style="padding-top:0">
        <div class="form-grid">
          <div class="field full"><label for="company_name">Company name</label><input id="company_name" name="company_name" value="<?= e(setting('company_name')) ?>" required maxlength="120"></div>
          <div class="field"><label for="timezone">Time zone</label><select id="timezone" name="timezone"><?php foreach ($tzs as $tz): ?><option <?= $curTz === $tz ? 'selected' : '' ?>><?= e($tz) ?></option><?php endforeach; ?></select></div>
          <div class="field"><label for="annual_leave_days">Annual leave per year</label><input id="annual_leave_days" name="annual_leave_days" type="number" min="0" max="60" value="<?= e(setting('annual_leave_days')) ?>"><p class="help">Qatar law: 21 days, 28 after five years of service.</p></div>
          <div class="field"><label for="session_minutes">Sign out after inactivity</label><select id="session_minutes" name="session_minutes"><?php foreach ([30 => '30 minutes', 60 => '1 hour', 120 => '2 hours', 240 => '4 hours', 480 => '8 hours'] as $v => $l): ?><option value="<?= $v ?>" <?= (int) setting('session_minutes') === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
        </div>
      </div>
      <div class="form-actions"><button class="btn btn-primary" type="submit">Save company settings</button></div>
    </form>

    <section class="panel">
      <div class="panel-head"><div><h2>Security & data</h2><p>How this installation is protected</p></div></div>
      <ul class="list">
        <li><?= icon('lock') ?><div class="grow"><div class="title">Private configuration</div><div class="sub"><?= $outside ? 'Stored outside the website folder, safe from GitHub deploys' : 'Stored in storage/private, blocked from the web' ?></div></div><span class="badge tone-<?= $outside ? 'ok' : 'warn' ?>"><?= $outside ? 'Protected' : 'Check' ?></span></li>
        <li><?= icon('shield') ?><div class="grow"><div class="title">Connection</div><div class="sub"><?= is_https() ? 'HTTPS with strict transport security' : 'Not using HTTPS. Turn on SSL in hPanel, then enable the redirect in .htaccess.' ?></div></div><span class="badge tone-<?= is_https() ? 'ok' : 'danger' ?>"><?= is_https() ? 'Secure' : 'Action needed' ?></span></li>
        <li><?= icon('db') ?><div class="grow"><div class="title">Database schema</div><div class="sub">Version <?= $schema ?>. New migrations run automatically after each deploy.</div></div></li>
        <li><?= icon('download') ?><div class="grow"><div class="title">Full backup</div><div class="sub">Every record as a JSON file. Also use hPanel backups for the database.</div></div><a class="btn btn-sm" href="<?= e(url('settings/backup')) ?>">Download</a></li>
      </ul>
    </section>
  </div>

  <form class="panel" method="post" action="<?= e(url('settings')) ?>" id="ai" data-busy>
    <?= csrf_field() ?><input type="hidden" name="section" value="ai">
    <div class="panel-head"><div><h2>AI assistant</h2><p><?= $aiUsage ?> requests this month</p></div><?= AI::configured() ? '<span class="badge tone-ok">Connected</span>' : '<span class="badge">Off</span>' ?></div>
    <div class="form-section" style="padding-top:0">
      <div class="form-grid">
        <div class="field"><label for="ai_provider">Provider</label><select id="ai_provider" name="ai_provider"><?php foreach (AI::PROVIDERS as $k => $p): ?><option value="<?= e($k) ?>" <?= $provider === $k ? 'selected' : '' ?> data-model="<?= e($p['model']) ?>"><?= e($p['label']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="ai_model">Model</label><input id="ai_model" name="ai_model" value="<?= e(setting('ai_model')) ?>" placeholder="Default: <?= e(AI::PROVIDERS[$provider]['model']) ?>"></div>
        <div class="field full">
          <label for="ai_key">API key</label>
          <input id="ai_key" name="ai_key" type="password" autocomplete="off" placeholder="<?= $keyHint ? 'Saved key ' . e($keyHint) . ' — paste a new key to replace it' : 'Paste your API key' ?>">
          <p class="help">Encrypted on your server. It is never shown again or sent to browsers.</p>
        </div>
      </div>
    </div>
    <div class="form-section">
      <div style="display:grid;gap:12px">
        <label class="check"><input type="checkbox" name="ai_enabled" value="1" <?= setting('ai_enabled') === '1' ? 'checked' : '' ?>><span>Turn on the assistant<small>Available to users with AI assistant access</small></span></label>
        <label class="check"><input type="checkbox" name="ai_share_data" value="1" <?= setting('ai_share_data', '1') === '1' ? 'checked' : '' ?>><span>Let the assistant read company data<small>Sends only what each user can already see to your AI provider, per question. Turn off for general help only.</small></span></label>
        <?php if ($keyHint): ?><label class="check"><input type="checkbox" name="ai_key_remove" value="1"><span>Remove the saved key</span></label><?php endif; ?>
      </div>
    </div>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit">Save AI settings</button>
      <?php if ($keyHint): ?><button class="btn" type="button" data-action="ai-test"><?= icon('spark') ?>Test connection</button><?php endif; ?>
      <span class="soft" id="ai-test-result" style="font-size:13px"></span>
    </div>
  </form>
</div>
