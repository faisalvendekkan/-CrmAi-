<?php
$editItem = is_array($edit ?? null) ? $edit : null;
$formKind = $editItem["kind"] ?? "prompt";
$formTitle = $editItem["title"] ?? "";
$formCategory = $editItem["category"] ?? "";
$formContent = $editItem["content"] ?? "";
$formPinned = (int) ($editItem["is_pinned"] ?? 0) === 1;
?>
<div class="page-head">
  <div>
    <h1>Prompts & Notes</h1>
    <p>Keep reusable AI prompts and private working notes in one place.</p>
  </div>
  <?php if ($canEdit): ?>
    <div class="page-actions">
      <a class="btn btn-primary" href="#workspace-editor"><?= icon("plus") ?> Add new</a>
    </div>
  <?php endif; ?>
</div>

<section class="panel">
  <form class="toolbar" method="get" action="<?= e(url("workspace")) ?>">
    <div class="search">
      <?= icon("search") ?>
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search prompts and notes…">
    </div>
    <select name="kind" aria-label="Filter type">
      <option value="all" <?= $kind === "all" ? "selected" : "" ?>>All types</option>
      <option value="prompt" <?= $kind === "prompt" ? "selected" : "" ?>>Prompts</option>
      <option value="note" <?= $kind === "note" ? "selected" : "" ?>>Notes</option>
    </select>
    <button class="btn" type="submit">Filter</button>
    <?php if ($q !== "" || $kind !== "all"): ?>
      <a class="btn btn-quiet" href="<?= e(url("workspace")) ?>">Clear</a>
    <?php endif; ?>
  </form>

  <?php if (!$items): ?>
    <div class="empty">
      <?= icon("file") ?>
      <strong>No prompts or notes yet</strong>
      Add your first reusable prompt or note below.
    </div>
  <?php else: ?>
    <div class="workspace-grid">
      <?php foreach ($items as $item): ?>
        <article class="workspace-card">
          <div class="workspace-card-head">
            <div class="chips">
              <span class="badge tone-accent"><?= e(ucfirst($item["kind"])) ?></span>
              <?php if (!empty($item["category"])): ?>
                <span class="badge"><?= e($item["category"]) ?></span>
              <?php endif; ?>
            </div>
            <?php if ((int) $item["is_pinned"] === 1): ?>
              <span class="workspace-pinned">Pinned</span>
            <?php endif; ?>
          </div>

          <h3><?= e($item["title"]) ?></h3>
          <div class="workspace-text"><?= nl2br(e($item["content"])) ?></div>

          <?php if ($canEdit): ?>
            <div class="workspace-actions">
              <a class="btn btn-sm" href="<?= e(url("workspace", ["edit" => $item["id"]])) ?>#workspace-editor">Edit</a>

              <form method="post" action="<?= e(url("workspace/" . $item["id"] . "/pin")) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-quiet" type="submit">
                  <?= (int) $item["is_pinned"] === 1 ? "Unpin" : "Pin" ?>
                </button>
              </form>

              <form method="post" action="<?= e(url("workspace/" . $item["id"] . "/delete")) ?>" data-confirm="Delete this item?">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-danger" type="submit">Delete</button>
              </form>
            </div>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php if ($canEdit): ?>
<section class="panel" id="workspace-editor" style="margin-top:16px">
  <div class="panel-head">
    <div>
      <h2><?= $editItem ? "Edit item" : "Add prompt or note" ?></h2>
      <p>Prompts are reusable AI instructions. Notes are for internal reference.</p>
    </div>
    <?php if ($editItem): ?>
      <a class="btn btn-sm" href="<?= e(url("workspace")) ?>#workspace-editor">Cancel edit</a>
    <?php endif; ?>
  </div>

  <form method="post" action="<?= e(url("workspace/save")) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) ($editItem["id"] ?? 0) ?>">

    <div class="form-section">
      <div class="form-grid">
        <div class="field">
          <label for="workspace-kind">Type</label>
          <select id="workspace-kind" name="kind">
            <option value="prompt" <?= $formKind === "prompt" ? "selected" : "" ?>>Prompt</option>
            <option value="note" <?= $formKind === "note" ? "selected" : "" ?>>Note</option>
          </select>
        </div>

        <div class="field">
          <label for="workspace-category">Category</label>
          <input id="workspace-category" type="text" name="category" maxlength="100" value="<?= e($formCategory) ?>" placeholder="HR, Recruitment, Admin, AI…">
        </div>

        <div class="field full">
          <label for="workspace-title">Title <span class="req">*</span></label>
          <input id="workspace-title" type="text" name="title" maxlength="180" required value="<?= e($formTitle) ?>" placeholder="Example: Recruitment screening prompt">
        </div>

        <div class="field full">
          <label for="workspace-content">Content <span class="req">*</span></label>
          <textarea id="workspace-content" name="content" rows="10" required placeholder="Write or paste the prompt / note here…"><?= e($formContent) ?></textarea>
        </div>

        <div class="field full">
          <label class="check">
            <input type="checkbox" name="is_pinned" value="1" <?= $formPinned ? "checked" : "" ?>>
            <span>Pin to top<small>Keep this item above other prompts and notes.</small></span>
          </label>
        </div>
      </div>
    </div>

    <div class="form-actions">
      <button class="btn btn-primary" type="submit"><?= icon("check") ?> <?= $editItem ? "Update" : "Save" ?></button>
      <?php if ($editItem): ?>
        <a class="btn" href="<?= e(url("workspace")) ?>">Cancel</a>
      <?php endif; ?>
    </div>
  </form>
</section>
<?php endif; ?>
