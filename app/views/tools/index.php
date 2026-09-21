<?php $isAi = $kind === 'ai'; ?>
<div class="page-head">
  <div>
    <h1><?= e($title) ?></h1>
    <p><?= $isAi ? 'Shortcuts to the AI tools your team uses. For questions about your own data, use Ask Meridian in the top bar.' : 'The web apps your team opens every day, in one place.' ?></p>
  </div>
  <?php if ($canEdit): ?><div class="page-actions"><button class="btn btn-primary" type="button" data-tool-edit><?= icon('plus') ?>Add <?= $isAi ? 'AI tool' : 'application' ?></button></div><?php endif; ?>
</div>

<?php if (!$tools): ?>
  <section class="panel"><div class="empty"><?= icon($isAi ? 'spark' : 'apps') ?><strong>Nothing here yet</strong>Add the tools your team uses so everyone finds them in one place.</div></section>
<?php else: ?>
  <div class="tools">
    <?php foreach ($tools as $t): ?>
      <article class="tool">
        <div class="tool-top">
          <span class="monogram"><?= e(initials($t['name'])) ?></span>
          <div><h3><?= e($t['name']) ?></h3><div class="cat"><?= e($t['category'] ?: ($isAi ? 'AI tool' : 'Application')) ?></div></div>
        </div>
        <p><?= e($t['description'] ?: parse_url($t['url'], PHP_URL_HOST)) ?></p>
        <div class="tool-foot">
          <a class="tool-open" href="<?= e($t['url']) ?>" target="_blank" rel="noopener noreferrer">Open <?= icon('external', 'i i-sm') ?></a>
          <?php if ($canEdit): ?>
            <div class="row-actions">
              <button class="icon-btn" type="button" aria-label="Edit <?= e($t['name']) ?>" data-tool-edit='<?= e(json_encode(['id' => $t['id'], 'name' => $t['name'], 'category' => $t['category'], 'url' => $t['url'], 'description' => $t['description']])) ?>'><?= icon('edit', 'i i-sm') ?></button>
              <form method="post" action="<?= e(url('tools/' . $t['id'] . '/delete')) ?>" data-confirm="Remove <?= e($t['name']) ?>?" data-confirm-ok="Remove"><?= csrf_field() ?><button class="icon-btn" type="submit" aria-label="Remove"><?= icon('trash', 'i i-sm') ?></button></form>
            </div>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($canEdit): ?>
<dialog id="tool-dialog">
  <form method="post" action="<?= e(url('tools/save')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="kind" value="<?= e($kind) ?>">
    <input type="hidden" name="id" value="0">
    <div class="dialog-head"><div><h2 data-title>Add <?= $isAi ? 'AI tool' : 'application' ?></h2><p>Opens in a new tab for everyone with access.</p></div><button class="icon-btn" type="button" data-close aria-label="Close"><?= icon('x') ?></button></div>
    <div class="dialog-body">
      <div class="form-grid">
        <div class="field"><label for="t_name">Name</label><input id="t_name" name="name" required maxlength="100"></div>
        <div class="field"><label for="t_cat">Category</label><input id="t_cat" name="category" maxlength="80" list="t_cats"><datalist id="t_cats"><?php foreach ($cats as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist></div>
        <div class="field full"><label for="t_url">Web address</label><input id="t_url" name="url" type="url" required placeholder="https://"></div>
        <div class="field full"><label for="t_desc">What it is used for</label><input id="t_desc" name="description" maxlength="300"></div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px"><button class="btn" type="button" data-close>Cancel</button><button class="btn btn-primary" type="submit">Save</button></div>
    </div>
  </form>
</dialog>
<?php endif; ?>
