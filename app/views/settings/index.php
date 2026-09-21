<?php
$tzs = ['Asia/Qatar', 'Asia/Dubai', 'Asia/Riyadh', 'Asia/Kuwait', 'Asia/Bahrain', 'Asia/Muscat', 'Asia/Kolkata', 'Europe/London', 'UTC'];
$curTz = (string) setting('timezone');
if (!in_array($curTz, $tzs, true)) $tzs[] = $curTz;
$provider = AI::provider();

$mcpEndpoint = (is_https() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'your-domain') . url('mcp');
$mcpHasToken = setting('mcp_token_hash', '') !== '';
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
        <div class="field">
          <label for="ai_model">Model</label>
          <div style="display:flex;gap:8px;align-items:center">
            <select id="ai_model" name="ai_model" data-current="<?= e(AI::validModelId($provider, (string) setting('ai_model')) ? setting('ai_model') : '') ?>" style="min-width:0;flex:1">
              <option value="">Default: <?= e(AI::PROVIDERS[$provider]['model']) ?></option>
              <?php if (AI::validModelId($provider, (string) setting('ai_model')) && setting('ai_model') !== ''): ?>
                <option value="<?= e(setting('ai_model')) ?>" selected><?= e(setting('ai_model')) ?></option>
              <?php endif; ?>
            </select>
            <button class="btn btn-sm" type="button" data-action="ai-models">Fetch models</button>
          </div>
          <p class="help" id="ai-model-status">Models are fetched securely from the selected provider using your saved or newly entered API key.</p>
        </div>
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
      <button class="btn" type="button" data-action="ai-test"><?= icon('spark') ?>Test connection</button>
      <span class="soft" id="ai-test-result" style="font-size:13px"></span>
    </div>
  </form>
</div>

<form class="panel" method="post" action="<?= e(url('settings')) ?>" id="mcp" data-busy style="margin-top:20px">
  <?= csrf_field() ?><input type="hidden" name="section" value="mcp">
  <div class="panel-head">
    <div>
      <h2>MCP server</h2>
      <p>Connect Meridian HR securely to ChatGPT, Claude and other MCP clients.</p>
    </div>
    <?= setting('mcp_enabled', '0') === '1' && $mcpHasToken ? '<span class="badge tone-ok">Enabled</span>' : '<span class="badge">Off</span>' ?>
  </div>
  <div class="form-section" style="padding-top:0">
    <div class="form-grid">
      <div class="field full">
        <label>MCP endpoint</label>
        <input value="<?= e($mcpEndpoint) ?>" readonly>
        <p class="help">Remote HTTPS endpoint. Current tools are read-only: employee search/profile, attendance summary, pending leave, expiry alerts and HR dashboard summary.</p>
      </div>
      <div class="field full">
        <label for="mcp_token">Bearer token</label>
        <input id="mcp_token" name="mcp_token" type="password" autocomplete="new-password" minlength="32" placeholder="<?= $mcpHasToken ? 'Token configured — enter a new token to replace it' : 'Enter a strong token with at least 32 characters' ?>">
        <p class="help">Stored as a one-way password hash. The original token cannot be recovered, so save it securely before submitting.</p>
      </div>
    </div>
  </div>
  <div class="form-section">
    <div style="display:grid;gap:12px">
      <label class="check"><input type="checkbox" name="mcp_enabled" value="1" <?= setting('mcp_enabled', '0') === '1' ? 'checked' : '' ?>><span>Enable MCP server<small>Only requests with the correct bearer token can access the MCP tools.</small></span></label>
      <?php if ($mcpHasToken): ?><label class="check"><input type="checkbox" name="mcp_token_remove" value="1"><span>Remove saved MCP token<small>This also disables the MCP server.</small></span></label><?php endif; ?>
    </div>
  </div>
  <div class="form-actions">
    <button class="btn btn-primary" type="submit">Save MCP settings</button>
  </div>
</form>
