<?php
$matched = json_decode((string) $c['matched'], true) ?: [];
$missing = json_decode((string) $c['missing'], true) ?: [];
$v = (int) $c['score'];
$color = $v >= 75 ? 'var(--ok)' : ($v >= 50 ? 'var(--warn)' : 'var(--danger)');
$verdict = $v >= 75 ? 'Strong match' : ($v >= 50 ? 'Worth reviewing' : 'Low match');
?>
<a class="back" href="<?= e(url('candidates')) ?>"><?= icon('arrow-left', 'i i-sm') ?>CV screening</a>

<section class="panel">
  <div class="profile-head">
    <span class="score-ring lg" style="--v:<?= $v ?>;--c:<?= $color ?>"><?= $v ?>%</span>
    <div>
      <h1><?= e($c['name']) ?></h1>
      <div class="meta"><span><?= e($c['role']) ?></span><span class="muted">with</span><span><?= e(rtrim(rtrim((string) $c['experience'], '0'), '.') ?: '0') ?> years</span><span class="badge tone-<?= $v >= 75 ? 'ok' : ($v >= 50 ? 'warn' : 'danger') ?>"><?= $verdict ?></span><?= badge($c['status'], $statuses[$c['status']] ?? null) ?></div>
    </div>
    <div class="actions">
      <?php if (can('candidates', 'edit')): ?>
        <form method="post" action="<?= e(url('candidates/' . $c['id'] . '/status')) ?>" style="display:flex;gap:8px">
          <?= csrf_field() ?>
          <select name="status" aria-label="Stage" style="width:auto" data-autosubmit><?php foreach ($statuses as $k => $l): ?><option value="<?= e($k) ?>" <?= $c['status'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
        </form>
        <form method="post" action="<?= e(url('candidates/' . $c['id'] . '/delete')) ?>" data-confirm="Delete <?= e($c['name']) ?>?" data-confirm-text="The CV text and screening results are removed.">
          <?= csrf_field() ?><button class="btn btn-danger" type="submit"><?= icon('trash') ?>Delete</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
  <dl class="facts">
    <div><dt>Email</dt><dd><?= $c['email'] ? '<a href="mailto:' . e($c['email']) . '">' . e($c['email']) . '</a>' : '<span class="muted">—</span>' ?></dd></div>
    <div><dt>Phone</dt><dd><?= e($c['phone'] ?: '—') ?></dd></div>
    <div><dt>File</dt><dd><?= e($c['file_name'] ?: 'Pasted text') ?></dd></div>
    <div><dt>Screened</dt><dd><?= e(fmt_datetime($c['created_at'])) ?></dd></div>
  </dl>
</section>

<div class="grid-2" style="margin-top:16px">
  <section class="panel">
    <div class="panel-head">
      <div><h2>AI review</h2><p><?= $c['ai_score'] !== null ? 'Fit score ' . (int) $c['ai_score'] . ' out of 100' : 'Recruiter-style assessment of the full CV' ?></p></div>
      <?php if (can('candidates', 'edit') && can('assistant') && AI::configured()): ?>
        <form method="post" action="<?= e(url('candidates/' . $c['id'] . '/analyse')) ?>" data-busy><?= csrf_field() ?><button class="btn btn-sm" type="submit"><?= icon('spark', 'i i-sm') ?><?= $c['ai_summary'] ? 'Review again' : 'Run AI review' ?></button></form>
      <?php endif; ?>
    </div>
    <div class="panel-body">
      <?php if ($c['ai_summary']): ?>
        <div class="md" data-markdown><?= e($c['ai_summary']) ?></div>
      <?php else: ?>
        <p class="muted"><?= AI::configured() ? 'No AI review yet. Run one to get strengths, gaps and interview questions.' : 'Connect an AI provider in Settings to add AI reviews.' ?></p>
      <?php endif; ?>
    </div>
  </section>

  <div class="stack">
    <section class="panel">
      <div class="panel-head"><div><h2>Keywords</h2><p><?= count($matched) ?> of <?= count($matched) + count($missing) ?> found</p></div></div>
      <div class="panel-body">
        <div class="kw"><?php foreach ($matched as $k): ?><span class="hit"><?= e($k) ?></span><?php endforeach; ?><?php foreach ($missing as $k): ?><span class="miss"><?= e($k) ?></span><?php endforeach; ?></div>
      </div>
    </section>
    <section class="panel">
      <div class="panel-head"><div><h2>CV text</h2></div></div>
      <div class="panel-body"><p class="soft" style="white-space:pre-wrap;max-height:420px;overflow:auto;font-size:13px"><?= e($c['cv_text']) ?></p></div>
    </section>
  </div>
</div>
