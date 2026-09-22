<?php $company = Config::installed() ? (string) setting('company_name') : APP_NAME; ?><!doctype html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="color-scheme" content="dark light">
<title><?= e(($title ?? 'Sign in') . ' — ' . $company) ?></title>
<script nonce="<?= e(App::$nonce) ?>">try{var t=localStorage.getItem('mhr-theme');if(t)document.documentElement.dataset.theme=t;}catch(e){}</script>
<link rel="preload" href="<?= e(asset('fonts/Geist-Variable.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/blue-theme.css')) ?>">
<link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><rect width="24" height="24" rx="6" fill="#0e1117"/><circle cx="12" cy="12" r="7" fill="none" stroke="#8ea2ff" stroke-width="1.6"/><circle cx="15.5" cy="12" r="1.6" fill="#8ea2ff"/></svg>') ?>">
</head>
<body>
<?php View::partial('icons'); ?>
<main class="bare">
  <?= $content ?>
</main>
<div class="toasts" id="toasts" role="status" aria-live="polite">
  <?php foreach (take_flashes() as $f): ?>
    <div class="toast t-<?= e($f['type']) ?>"><?= icon($f['type'] === 'error' ? 'alert' : ($f['type'] === 'success' ? 'check-circle' : 'info')) ?><span><?= e($f['message']) ?></span><button type="button" aria-label="Dismiss" data-action="dismiss"><?= icon('x', 'i i-sm') ?></button></div>
  <?php endforeach; ?>
</div>
<script type="application/json" id="app-config"><?= json_encode(['base' => base_path(), 'csrf' => Csrf::token()], JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
