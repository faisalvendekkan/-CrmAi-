<?php
$key = $m['key'];
$return = input('return', '', 'get');
if (!preg_match('#^employees/\d+$#', $return)) $return = '';
$cancel = $return !== '' ? url($return) : ($key === 'employees' && $id ? url("employees/$id") : url($key));
?>
<div class="page-head">
  <div>
    <a class="back" href="<?= e($cancel) ?>"><?= icon('arrow-left', 'i i-sm') ?><?= e($return !== '' ? 'Back to employee' : $m['label']) ?></a>
    <h1><?= e($title) ?></h1>
  </div>
</div>

<form class="panel" method="post" action="<?= e(url("$key/save")) ?>" data-busy <?= $key === 'leave' ? 'data-leave-form' : '' ?>>
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $id ?>">
  <input type="hidden" name="return" value="<?= e($return) ?>">
  <div class="form-section">
    <div class="form-grid">
      <?php foreach ($m['fields'] as $col => $f):
          if (!empty($f['computed'])) {
              if ($key === 'leave') { ?>
                <div class="field"><span class="label">Days</span><p class="num" style="height:40px;display:flex;align-items:center" id="leave-days"><?= e(isset($row['days']) && $row['days'] !== '' ? rtrim(rtrim((string) $row['days'], '0'), '.') . ' days' : 'Pick the dates') ?></p></div>
              <?php }
              continue;
          }
          $v = (string) ($row[$col] ?? '');
          $idAttr = 'f_' . $col;
          $req = !empty($f['required']);
          $ph = isset($f['placeholder']) ? ' placeholder="' . e($f['placeholder']) . '"' : '';
      ?>
        <div class="field <?= !empty($f['full']) ? 'full' : '' ?>">
          <label for="<?= $idAttr ?>"><?= e($f['label']) ?><?= $req ? '<span class="req" aria-hidden="true">*</span>' : '' ?></label>
          <?php switch ($f['type']):
              case 'textarea': ?>
                <textarea id="<?= $idAttr ?>" name="<?= e($col) ?>" maxlength="5000" <?= $req ? 'required' : '' ?><?= $ph ?>><?= e($v) ?></textarea>
              <?php break; case 'select': ?>
                <select id="<?= $idAttr ?>" name="<?= e($col) ?>">
                  <?php foreach ($f['options'] as $ov => $ol): ?><option value="<?= e($ov) ?>" <?= $v === (string) $ov ? 'selected' : '' ?>><?= e($ol) ?></option><?php endforeach; ?>
                </select>
              <?php break; case 'employee': ?>
                <select id="<?= $idAttr ?>" name="<?= e($col) ?>" <?= $req ? 'required' : '' ?>>
                  <option value="">Choose an employee</option>
                  <?php foreach ($employees as $emp): ?><option value="<?= (int) $emp['id'] ?>" <?= $v === (string) $emp['id'] ? 'selected' : '' ?>><?= e($emp['name'] . ($emp['department'] ? ' — ' . $emp['department'] : '')) ?></option><?php endforeach; ?>
                </select>
              <?php break; default:
                  $type = in_array($f['type'], ['email', 'tel', 'date', 'time', 'number'], true) ? $f['type'] : 'text';
                  $list = isset($f['suggest']) ? 'dl_' . $col : null;
                  if ($type === 'time') $v = substr($v, 0, 5); ?>
                <input id="<?= $idAttr ?>" name="<?= e($col) ?>" type="<?= $type ?>" value="<?= e($v) ?>" maxlength="255" <?= $req ? 'required' : '' ?> <?= $list ? 'list="' . $list . '" autocomplete="off"' : '' ?><?= $ph ?>>
                <?php if ($list): ?><datalist id="<?= $list ?>"><?php foreach (Modules::suggestions($f['suggest']) as $s): ?><option value="<?= e($s) ?>"><?php endforeach; ?></datalist><?php endif; ?>
          <?php endswitch; ?>
          <?php if (isset($f['expiry']) && valid_date($v)): ?><p class="help"><?= expiry_badge($v) ?></p><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="form-actions">
    <button class="btn btn-primary" type="submit"><?= $id ? 'Save changes' : 'Add ' . e($m['singular']) ?></button>
    <a class="btn btn-quiet" href="<?= e($cancel) ?>">Cancel</a>
  </div>
</form>
