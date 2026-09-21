<div class="page-head">
  <div>
    <a class="back" href="<?= e(url('candidates')) ?>"><?= icon('arrow-left', 'i i-sm') ?>CV screening</a>
    <h1>Screen a CV</h1>
    <p>PDF and Word files are read in your browser, then only the text is saved.</p>
  </div>
</div>

<form class="panel" method="post" action="<?= e(url('candidates/save')) ?>" data-busy id="cv-form">
  <?= csrf_field() ?>
  <div class="form-section">
    <h3>The CV</h3>
    <p>Drop a file or paste the text below.</p>
    <label class="dropzone" id="dropzone">
      <input type="file" id="cv-file" accept=".pdf,.docx,.txt" data-vendor="<?= e(url('assets/vendor')) ?>">
      <?= icon('upload') ?>
      <strong id="dz-title">Drop a CV here or click to choose</strong>
      <small id="dz-sub">PDF, DOCX or TXT, up to 10 MB</small>
    </label>
    <input type="hidden" name="file_name" id="file_name" value="<?= e(old('file_name')) ?>">
    <div class="field" style="margin-top:14px">
      <label for="cv_text">CV text</label>
      <textarea id="cv_text" name="cv_text" rows="8" placeholder="The extracted text appears here. You can also paste it." required><?= e(old('cv_text')) ?></textarea>
    </div>
  </div>
  <div class="form-section">
    <h3>Candidate and role</h3>
    <p>Keywords are the must-have skills. Each one found in the CV raises the score.</p>
    <div class="form-grid">
      <div class="field"><label for="name">Candidate name<span class="req">*</span></label><input id="name" name="name" value="<?= e(old('name')) ?>" required></div>
      <div class="field"><label for="role">Role applied for<span class="req">*</span></label><input id="role" name="role" value="<?= e(old('role')) ?>" list="roles" required autocomplete="off"><datalist id="roles"><?php foreach ($roles as $r => $k): ?><option value="<?= e($r) ?>" data-keywords="<?= e($k) ?>"><?php endforeach; ?></datalist></div>
      <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" value="<?= e(old('email')) ?>"></div>
      <div class="field"><label for="phone">Phone</label><input id="phone" name="phone" type="tel" value="<?= e(old('phone')) ?>"></div>
      <div class="field"><label for="experience">Years of experience</label><input id="experience" name="experience" type="number" min="0" max="60" step="0.5" value="<?= e(old('experience', '0')) ?>"></div>
      <div class="field full"><label for="keywords">Required keywords<span class="req">*</span></label><textarea id="keywords" name="keywords" rows="2" placeholder="Recruitment, Payroll, Qatar Labour Law, Excel" required><?= e(old('keywords')) ?></textarea><p class="help">Separate with commas. Picking a previous role fills in its keywords.</p></div>
      <?php if (AI::configured() && can('assistant')): ?>
        <label class="check full"><input type="checkbox" name="run_ai" value="1" checked><span>Add an AI review<small>Fit score, strengths, gaps, interview questions and a recommendation.</small></span></label>
      <?php endif; ?>
    </div>
  </div>
  <div class="form-actions">
    <button class="btn btn-primary" type="submit"><?= icon('scan') ?>Screen CV</button>
    <a class="btn btn-quiet" href="<?= e(url('candidates')) ?>">Cancel</a>
  </div>
</form>
