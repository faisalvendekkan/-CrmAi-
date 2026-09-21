<div class="error-page">
  <div class="code"><?= (int) $code ?></div>
  <h1><?= e(match ((int) $code) { 403 => 'No access', 404 => 'Page not found', 419 => 'Session expired', 429 => 'Slow down', default => 'Something went wrong' }) ?></h1>
  <p><?= e($message) ?></p>
  <a class="btn" href="<?= e(url()) ?>"><?= icon('arrow-left') ?>Back to dashboard</a>
</div>
