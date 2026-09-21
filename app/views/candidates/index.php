<?php
$ring = fn (int $v) => '<span class="score-ring" style="--v:' . $v . ';--c:' . ($v >= 75 ? 'var(--ok)' : ($v >= 50 ? 'var(--warn)' : 'var(--danger)')) . '">' . $v . '</span>';
?>
<div class="page-head">
  <div>
    <h1>CV screening</h1>
    <p>Upload a CV, list the must-have skills and get an instant match score. Add an AI review for a recruiter-style summary.</p>
  </div>
  <?php if (can('candidates', 'edit')): ?><div class="page-actions"><a class="btn btn-primary" href="<?= e(url('candidates/new')) ?>"><?= icon('upload') ?>Screen a CV</a></div><?php endif; ?>
</div>

<div class="metrics">
  <div class="metric"><b><?= (int) $stats['total'] ?></b><span>Candidates</span></div>
  <div class="metric tone-ok"><b><?= (int) $stats['strong'] ?></b><span>Strong match (75%+)</span></div>
  <div class="metric"><b><?= (int) $stats['shortlisted'] ?></b><span>Shortlisted or interviewing</span></div>
  <div class="metric"><b><?= $stats['avg'] !== null ? (int) $stats['avg'] . '%' : '—' ?></b><span>Average match</span></div>
</div>

<section class="panel">
  <form class="toolbar" method="get" action="<?= e(url('candidates')) ?>" role="search">
    <div class="search"><?= icon('search') ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="Search by name, role or skill…" aria-label="Search"></div>
    <select name="status" data-autosubmit aria-label="Status"><option value="">All stages</option><?php foreach ($statuses as $k => $l): ?><option value="<?= e($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
    <button class="btn" type="submit">Search</button>
  </form>
  <?php if (!$rows): ?>
    <div class="empty"><?= icon('scan') ?><strong><?= $q || $status ? 'No matches' : 'No candidates yet' ?></strong><?= $q || $status ? 'Try a different search.' : 'Screen the first CV to build your shortlist.' ?>
      <?php if (!$q && !$status && can('candidates', 'edit')): ?><div><a class="btn btn-primary" href="<?= e(url('candidates/new')) ?>"><?= icon('upload') ?>Screen a CV</a></div><?php endif; ?></div>
  <?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Candidate</th><th>Role</th><th>Experience</th><th>Match</th><th>Missing skills</th><th>Stage</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): $missing = json_decode((string) $r['missing'], true) ?: []; ?>
        <tr>
          <td><a class="cell-title" href="<?= e(url('candidates/' . $r['id'])) ?>"><?= e($r['name']) ?></a><div class="cell-sub"><?= e($r['email'] ?: time_ago($r['created_at'])) ?></div></td>
          <td><?= e($r['role']) ?></td>
          <td class="num"><?= e(rtrim(rtrim((string) $r['experience'], '0'), '.') ?: '0') ?> yrs</td>
          <td><span class="score"><?= $ring((int) $r['score']) ?><?php if ($r['ai_score'] !== null): ?><span class="badge tone-accent" title="AI fit score">AI <?= (int) $r['ai_score'] ?></span><?php endif; ?></span></td>
          <td class="soft" style="max-width:260px"><?= $missing ? e(implode(', ', array_slice($missing, 0, 4)) . (count($missing) > 4 ? ' +' . (count($missing) - 4) : '')) : '<span class="muted">None</span>' ?></td>
          <td><?= badge($r['status'], $statuses[$r['status']] ?? null) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
