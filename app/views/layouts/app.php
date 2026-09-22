<?php
/** @var string $content */
$u        = Auth::user();
$current  = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$base     = trim(base_path(), '/');
if ($base !== '' && str_starts_with($current, $base)) {
    $current = trim(substr($current, strlen($base)), '/');
}
$company  = (string) setting('company_name');
$aiReady  = AI::configured();

// Alert count for the sidebar, cached briefly per session
$alertCount = 0;
if ($u && can('notifications')) {
    $cache = $_SESSION['_alert_count'] ?? null;
    if (!$cache || $cache['t'] < time() - 120) {
        $cache = ['t' => time(), 'n' => count(Alerts::items())];
        $_SESSION['_alert_count'] = $cache;
    }
    $alertCount = (int) $cache['n'];
}

$nav = [
    '' => [
        ['', 'Dashboard', 'grid', null],
    ],
    'People' => [
        ['employees', 'Employees', 'users', 'employees'],
        ['candidates', 'CV screening', 'scan', 'candidates'],
        ['leave', 'Leave', 'calendar', 'leave'],
        ['attendance', 'Attendance', 'clock', 'attendance'],
    ],
    'Records' => [
        ['documents', 'Company documents', 'file', 'documents'],
        ['emp-documents', 'Employee documents', 'id', 'emp_documents'],
        ['assets', 'Vehicles & assets', 'car', 'assets'],
        ['tasks', 'Admin tasks', 'check', 'tasks'],
        ['alerts', 'Expiry alerts', 'bell', 'notifications'],
    ],
    'Workspace' => [
        ['ai-tools', 'AI integrations', 'spark', 'ai_tools'],
        ['apps', 'Applications', 'apps', 'apps'],
    ],
    'Administration' => [
        ['users', 'Users & access', 'shield', 'admin'],
        ['activity', 'Activity log', 'activity', 'admin'],
        ['settings', 'Settings', 'sliders', 'admin'],
    ],
];
$isActive = function (string $path) use ($current): bool {
    if ($path === '') {
        return $current === '';
    }
    return $current === $path || str_starts_with($current, $path . '/');
};

$suggestions = [
    'dashboard'   => ['Brief me on what needs attention this week', 'Which visas or IDs expire in the next 30 days?', 'Who is absent or on leave today?'],
    'employees'   => ['Summarise headcount by department', 'List employees whose passports expire within 6 months', 'Draft a welcome email for a new joiner'],
    'candidates'  => ['Compare the top candidates for each role', 'Write interview questions for an HR Executive', 'Draft a polite rejection email'],
    'leave'       => ['Which leave requests are pending approval?', 'Explain annual leave rules under Qatar Labour Law', 'Draft a leave approval message'],
    'attendance'  => ['Summarise today\'s attendance', 'Who was late this week?', 'Draft a reminder about working hours'],
    'documents'   => ['Which company documents need renewal soon?', 'What is needed to renew a commercial registration in Qatar?', 'Create a renewal checklist'],
    'emp-documents' => ['Which employee documents expire this month?', 'Draft a reminder to renew a health card'],
    'assets'      => ['Which vehicles need Istimara renewal soon?', 'List assets assigned to each employee', 'Draft an asset handover form'],
    'tasks'       => ['Plan my admin tasks for today by priority', 'Which tasks are overdue?'],
    'alerts'      => ['Group all upcoming expiries by owner', 'Draft an email to the PRO listing renewals due'],
];
$pm = $pageModule ?? '';
$chips = $suggestions[$pm] ?? $suggestions[explode('/', $pm)[0]] ?? ['What can you help me with?', 'Draft a salary certificate', 'Explain end-of-service gratuity in Qatar'];
if (($pageModule ?? '') === 'employees' && !empty($pageRecord) && !empty($e['name'])) {
    $first = explode(' ', (string) $e['name'])[0];
    $chips = ["Summarise {$first}'s file and anything expiring", "Draft a salary certificate for {$e['name']}", "Draft an experience letter for {$e['name']}"];
}
$config = [
    'base'    => base_path(),
    'csrf'    => Csrf::token(),
    'page'    => ['module' => $pm, 'record' => (int) ($pageRecord ?? 0)],
    'ai'      => $aiReady && can('assistant'),
    'alerts'  => setting('alert_browser') === '1' && can('notifications'),
    'company' => $company,
];
?><!doctype html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="color-scheme" content="dark light">
<title><?= e(($title ?? 'Dashboard') . ' — ' . $company) ?></title>
<script nonce="<?= e(App::$nonce) ?>">try{var t=localStorage.getItem('mhr-theme');if(t)document.documentElement.dataset.theme=t;}catch(e){}</script>
<link rel="preload" href="<?= e(asset('fonts/Geist-Variable.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/blue-theme.css')) ?>">
<link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><rect width="24" height="24" rx="6" fill="#0e1117"/><circle cx="12" cy="12" r="7" fill="none" stroke="#8ea2ff" stroke-width="1.6"/><circle cx="15.5" cy="12" r="1.6" fill="#8ea2ff"/></svg>') ?>">
</head>
<body>
<?php View::partial('icons'); ?>
<a class="sr-only" href="#main">Skip to content</a>
<div class="shell">
  <aside class="sidebar" id="sidebar" aria-label="Main navigation">
    <a class="brand" href="<?= e(url()) ?>">
      <span class="brand-mark"><?php View::partial('logo'); ?></span>
      <span><strong>AI Workspace</strong><span>HR & administration</span></span>
    </a>
    <nav class="nav">
      <?php foreach ($nav as $group => $items):
          $visible = array_filter($items, fn ($i) => $i[3] === null || can($i[3]));
          if (!$visible) continue; ?>
        <?php if ($group !== ''): ?><div class="nav-group"><?= e($group) ?></div><?php endif; ?>
        <?php foreach ($visible as [$path, $label, $ic, $perm]): ?>
          <a href="<?= e(url($path)) ?>" class="<?= $isActive($path) ? 'active' : '' ?>" <?= $isActive($path) ? 'aria-current="page"' : '' ?>>
            <?= icon($ic) ?><span><?= e($label) ?></span>
            <?php if ($path === 'alerts' && $alertCount > 0): ?><span class="count"><?= $alertCount > 99 ? '99+' : $alertCount ?></span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-foot">
      <div class="me">
        <span class="avatar"><?= e(initials($u['name'])) ?></span>
        <a href="<?= e(url('profile')) ?>" title="Your profile"><strong><?= e($u['name']) ?></strong><span><?= e(Auth::ROLES[$u['role']] ?? $u['role']) ?></span></a>
        <form method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?><button class="icon-btn" type="submit" title="Sign out" aria-label="Sign out"><?= icon('logout') ?></button></form>
      </div>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <button class="icon-btn menu-btn" type="button" data-action="menu" aria-label="Open menu" aria-controls="sidebar"><?= icon('menu') ?></button>
      <button class="search-trigger" type="button" data-action="palette" aria-label="Search">
        <?= icon('search') ?><span>Search people, documents, assets…</span><kbd data-kbd>Ctrl K</kbd>
      </button>
      <div class="topbar-right">
        <?php if (can('notifications')): ?>
          <a class="icon-btn" href="<?= e(url('alerts')) ?>" aria-label="Expiry alerts<?= $alertCount ? ", $alertCount items" : '' ?>" title="Expiry alerts"><?= icon('bell') ?><?php if ($alertCount): ?><span class="dot"></span><?php endif; ?></a>
        <?php endif; ?>
        <button class="icon-btn" type="button" data-action="theme" aria-label="Switch light or dark theme" title="Switch theme"><?= icon('sun') ?></button>
        <?php if (can('assistant')): ?>
          <button class="ask-btn" type="button" data-action="ai" aria-controls="ai-panel"><?= icon('spark') ?><span>Ask Meridian</span></button>
        <?php endif; ?>
      </div>
    </header>

    <main class="content" id="main">
      <?= $content ?>
    </main>
  </div>
</div>

<?php if (can('assistant')): ?>
<aside class="ai-panel" id="ai-panel" aria-label="AI assistant" aria-hidden="true">
  <div class="ai-head">
    <span class="ai-orb"><?= icon('spark') ?></span>
    <div class="grow">
      <h2>Meridian assistant</h2>
      <p><?= $aiReady ? (setting('ai_share_data', '1') === '1' ? 'Sees the live data you have access to' : 'General questions only') : 'Not set up yet' ?></p>
    </div>
    <button class="icon-btn" type="button" data-action="ai-clear" title="New conversation" aria-label="New conversation"><?= icon('refresh') ?></button>
    <button class="icon-btn" type="button" data-action="ai-close" aria-label="Close assistant"><?= icon('x') ?></button>
  </div>
  <div class="ai-body" id="ai-body" aria-live="polite">
    <div class="ai-intro" id="ai-intro">
      <?php if ($aiReady): ?>
        <h3>How can I help?</h3>
        <p>Ask about your people and records, or get help drafting letters, emails and policies. I can read what you can see in the app, but I never change data.</p>
        <div class="chips">
          <?php foreach ($chips as $c): ?><button class="chip" type="button" data-prompt="<?= e($c) ?>"><?= icon('spark') ?><?= e($c) ?></button><?php endforeach; ?>
        </div>
      <?php else: ?>
        <h3>Connect an AI provider</h3>
        <p>The assistant needs an API key from Anthropic, OpenAI or Google.<?= Auth::isAdmin() ? ' Add one in Settings — it is stored encrypted on your server.' : ' Ask an administrator to add one in Settings.' ?></p>
        <?php if (Auth::isAdmin()): ?><a class="btn btn-primary" href="<?= e(url('settings')) ?>#ai"><?= icon('key') ?>Open AI settings</a><?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
  <?php if ($aiReady): ?>
  <form class="ai-foot" id="ai-form">
    <div class="ai-input">
      <textarea id="ai-text" rows="1" placeholder="Ask anything…" aria-label="Message the assistant" maxlength="8000"></textarea>
      <button class="btn btn-primary" type="submit" aria-label="Send"><?= icon('send') ?></button>
    </div>
    <p class="ai-note">Answers can be wrong. Check important details before acting.</p>
  </form>
  <?php endif; ?>
</aside>
<?php endif; ?>
<div class="scrim" id="scrim"></div>

<div class="palette" id="palette" hidden role="dialog" aria-modal="true" aria-label="Search">
  <div class="palette-box">
    <div class="palette-input"><?= icon('search') ?><input id="palette-input" type="search" placeholder="Search or jump to a page…" autocomplete="off" aria-controls="palette-list"></div>
    <div class="palette-list" id="palette-list" role="listbox"></div>
    <div class="palette-foot"><span><kbd>↑</kbd> <kbd>↓</kbd> to move</span><span><kbd>Enter</kbd> to open</span><span><kbd>Esc</kbd> to close</span></div>
  </div>
</div>

<dialog id="confirm-dialog">
  <form method="dialog">
    <div class="dialog-head"><div><h2 id="confirm-title">Are you sure?</h2><p id="confirm-text"></p></div></div>
    <div class="dialog-body" style="display:flex;gap:10px;justify-content:flex-end">
      <button class="btn" value="cancel">Cancel</button>
      <button class="btn btn-primary" value="ok" id="confirm-ok">Delete</button>
    </div>
  </form>
</dialog>

<div class="toasts" id="toasts" role="status" aria-live="polite">
  <?php foreach (take_flashes() as $f): ?>
    <div class="toast t-<?= e($f['type']) ?>"><?= icon($f['type'] === 'error' ? 'alert' : ($f['type'] === 'success' ? 'check-circle' : 'info')) ?><span><?= e($f['message']) ?></span><button type="button" aria-label="Dismiss" data-action="dismiss"><?= icon('x', 'i i-sm') ?></button></div>
  <?php endforeach; ?>
</div>

<script type="application/json" id="app-config"><?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script type="application/json" id="nav-data"><?= json_encode(array_values(array_map(fn ($i) => ['title' => $i[1], 'url' => url($i[0]), 'icon' => $i[2]], array_filter(array_merge(...array_values($nav)), fn ($i) => $i[3] === null || can($i[3])))), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
