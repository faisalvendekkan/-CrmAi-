/* Meridian HR — client behaviour. No inline handlers (strict CSP). */
(() => {
  'use strict';

  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const cfgEl = $('#app-config');
  const CFG = cfgEl ? JSON.parse(cfgEl.textContent) : { base: '', csrf: '' };
  const url = (p) => (CFG.base || '') + '/' + String(p).replace(/^\//, '');
  const isMac = /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);

  const api = async (path, body, method = 'POST') => {
    const res = await fetch(url(path), {
      method,
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-Token': CFG.csrf,
        'X-Requested-With': 'fetch',
      },
      body: method === 'GET' ? undefined : JSON.stringify(body || {}),
    });
    let data = {};
    try { data = await res.json(); } catch (e) { data = { ok: false, error: 'Unexpected response from the server.' }; }
    if (!res.ok && data.ok !== false) data = { ok: false, error: 'Request failed (' + res.status + ').' };
    return data;
  };

  const escapeHtml = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  // ---------- Toasts ----------
  const toast = (message, type = 'info') => {
    const box = $('#toasts');
    if (!box) return;
    const icon = type === 'error' ? 'alert' : type === 'success' ? 'check-circle' : 'info';
    const el = document.createElement('div');
    el.className = 'toast t-' + type;
    el.innerHTML = '<svg class="i"><use href="#i-' + icon + '"></use></svg><span></span><button type="button" aria-label="Dismiss" data-action="dismiss"><svg class="i i-sm"><use href="#i-x"></use></svg></button>';
    el.querySelector('span').textContent = message;
    box.appendChild(el);
    autoHide(el);
  };
  const autoHide = (el) => setTimeout(() => el.remove(), el.classList.contains('t-error') ? 12000 : 6000);
  $$('#toasts .toast').forEach(autoHide);

  // ---------- Theme ----------
  const themeBtn = $('[data-action="theme"]');
  const setThemeIcon = () => {
    const use = themeBtn && themeBtn.querySelector('use');
    if (use) use.setAttribute('href', document.documentElement.dataset.theme === 'light' ? '#i-moon' : '#i-sun');
  };
  setThemeIcon();

  // ---------- Global click delegation ----------
  document.addEventListener('click', (ev) => {
    const t = ev.target.closest('[data-action], [data-prompt], [data-tool-edit], [data-close]');
    if (!t) return;
    const a = t.dataset.action;

    if (t.hasAttribute('data-prompt')) { ev.preventDefault(); AI.ask(t.dataset.prompt); return; }
    if (t.hasAttribute('data-tool-edit')) { ev.preventDefault(); openToolDialog(t.dataset.toolEdit); return; }
    if (t.hasAttribute('data-close')) { const d = t.closest('dialog'); if (d) d.close(); return; }

    switch (a) {
      case 'dismiss': t.closest('.toast').remove(); break;
      case 'theme': {
        const next = document.documentElement.dataset.theme === 'light' ? 'dark' : 'light';
        document.documentElement.dataset.theme = next;
        try { localStorage.setItem('mhr-theme', next); } catch (e) {}
        setThemeIcon();
        break;
      }
      case 'menu': $('#sidebar').classList.toggle('open'); $('#scrim').classList.toggle('show'); break;
      case 'palette': Palette.open(); break;
      case 'ai': AI.open(); break;
      case 'ai-close': AI.close(); break;
      case 'ai-clear': AI.clear(); break;
      case 'pw-toggle': {
        const input = t.parentElement.querySelector('input');
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        t.textContent = show ? 'Hide' : 'Show';
        t.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        break;
      }
      case 'db-test': Setup.testDb(); break;
      case 'setup-next': Setup.next(); break;
      case 'setup-back': Setup.back(); break;
      case 'ai-test': aiTest(t); break;
    }
  });

  const scrim = $('#scrim');
  if (scrim) scrim.addEventListener('click', () => { $('#sidebar')?.classList.remove('open'); AI.close(); scrim.classList.remove('show'); });

  // ---------- Forms: busy state, auto-submit, confirm ----------
  document.addEventListener('submit', (ev) => {
    const form = ev.target;
    if (form.hasAttribute('data-confirm') && !form.dataset.confirmed) {
      ev.preventDefault();
      confirmDialog(form.dataset.confirm, form.dataset.confirmText || '', form.dataset.confirmOk || 'Delete').then((ok) => {
        if (ok) { form.dataset.confirmed = '1'; form.requestSubmit ? form.requestSubmit() : form.submit(); }
      });
      return;
    }
    if (form.hasAttribute('data-busy')) {
      const btn = ev.submitter || form.querySelector('[type=submit]');
      if (btn) setTimeout(() => btn.classList.add('is-busy'), 0);
    }
  });

  document.addEventListener('change', (ev) => {
    const el = ev.target;
    if (el.matches('[data-autosubmit]') && el.form) el.form.submit();
  });

  const confirmDialog = (title, text, okLabel) => new Promise((resolve) => {
    const d = $('#confirm-dialog');
    if (!d || !d.showModal) { resolve(window.confirm(title)); return; }
    $('#confirm-title').textContent = title;
    $('#confirm-text').textContent = text;
    $('#confirm-ok').textContent = okLabel;
    d.returnValue = '';
    d.showModal();
    d.addEventListener('close', () => resolve(d.returnValue === 'ok'), { once: true });
  });

  // ---------- Password strength ----------
  document.addEventListener('input', (ev) => {
    const el = ev.target;
    if (!el.dataset || !el.dataset.strength) return;
    const meter = document.getElementById(el.dataset.strength);
    if (!meter) return;
    const v = el.value;
    let s = 0;
    if (v.length >= 10) s++;
    if (/[a-z]/i.test(v) && /\d/.test(v)) s++;
    if (/[^A-Za-z0-9]/.test(v) || /[A-Z]/.test(v) && /[a-z]/.test(v)) s++;
    if (v.length >= 14) s++;
    meter.dataset.s = v ? String(Math.max(1, s)) : '0';
  });

  // ---------- Keyboard shortcuts ----------
  $$('[data-kbd]').forEach((k) => { k.textContent = isMac ? '⌘ K' : 'Ctrl K'; });
  document.addEventListener('keydown', (ev) => {
    if ((ev.metaKey || ev.ctrlKey) && ev.key.toLowerCase() === 'k') { ev.preventDefault(); Palette.toggle(); return; }
    if (ev.key === 'Escape') { Palette.close(); AI.close(); $('#sidebar')?.classList.remove('open'); scrim?.classList.remove('show'); }
    if (ev.key === '/' && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName) && $('#palette')) { ev.preventDefault(); Palette.open(); }
  });

  // ---------- Command palette ----------
  const Palette = (() => {
    const root = $('#palette');
    if (!root) return { open() {}, close() {}, toggle() {} };
    const input = $('#palette-input');
    const list = $('#palette-list');
    const nav = JSON.parse(($('#nav-data') || { textContent: '[]' }).textContent);
    let items = [];
    let sel = 0;
    let timer = null;
    let seq = 0;

    const render = (results) => {
      items = results;
      sel = 0;
      if (!items.length) { list.innerHTML = '<div class="empty" style="padding:28px">No results. Try a name, reference or plate number.</div>'; return; }
      let html = '';
      let group = null;
      items.forEach((it, i) => {
        if (it.group !== group) { group = it.group; html += '<div class="palette-group">' + escapeHtml(group) + '</div>'; }
        html += '<div class="palette-item' + (i === 0 ? ' sel' : '') + '" role="option" data-i="' + i + '"><svg class="i"><use href="#i-' + (it.icon || 'chevron') + '"></use></svg><span>' + escapeHtml(it.title) + '</span>' + (it.sub ? '<small>' + escapeHtml(it.sub) + '</small>' : '') + '</div>';
      });
      list.innerHTML = html;
    };
    const pages = (q) => nav.filter((n) => !q || n.title.toLowerCase().includes(q.toLowerCase())).map((n) => ({ group: 'Go to', title: n.title, url: n.url, icon: n.icon }));
    const search = (q) => {
      const base = pages(q);
      if (CFG.ai && q.length > 2) base.push({ group: 'Ask', title: 'Ask Meridian: “' + q + '”', ask: q, icon: 'spark' });
      render(base);
      clearTimeout(timer);
      if (q.length < 2) return;
      const mine = ++seq;
      timer = setTimeout(async () => {
        const res = await api('api/search?q=' + encodeURIComponent(q), null, 'GET');
        if (mine !== seq || !res.ok) return;
        const found = res.results.map((r) => ({ ...r, icon: iconFor(r.group) }));
        render([...found, ...base]);
      }, 180);
    };
    const iconFor = (g) => ({ 'Employees': 'user', 'Company documents': 'file', 'Employee documents': 'id', 'Vehicles & assets': 'car', 'Admin tasks': 'check', 'Candidates': 'scan' }[g] || 'chevron');
    const highlight = () => $$('.palette-item', list).forEach((el, i) => { el.classList.toggle('sel', i === sel); if (i === sel) el.scrollIntoView({ block: 'nearest' }); });
    const go = (i) => {
      const it = items[i];
      if (!it) return;
      if (it.ask) { close(); AI.ask(it.ask); return; }
      window.location.href = it.url;
    };
    const open = () => { root.hidden = false; input.value = ''; search(''); setTimeout(() => input.focus(), 10); };
    const close = () => { root.hidden = true; };
    input.addEventListener('input', () => search(input.value.trim()));
    input.addEventListener('keydown', (ev) => {
      if (ev.key === 'ArrowDown') { ev.preventDefault(); sel = Math.min(items.length - 1, sel + 1); highlight(); }
      else if (ev.key === 'ArrowUp') { ev.preventDefault(); sel = Math.max(0, sel - 1); highlight(); }
      else if (ev.key === 'Enter') { ev.preventDefault(); go(sel); }
    });
    list.addEventListener('click', (ev) => { const el = ev.target.closest('.palette-item'); if (el) go(+el.dataset.i); });
    root.addEventListener('click', (ev) => { if (ev.target === root) close(); });
    return { open, close, toggle: () => (root.hidden ? open() : close()) };
  })();

  // ---------- Markdown (safe subset) ----------
  const inline = (s) => s
    .replace(/`([^`]+)`/g, '<code>$1</code>')
    .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
    .replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>')
    .replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');

  const markdown = (src) => {
    const lines = escapeHtml(String(src).replace(/\r/g, '')).split('\n');
    let out = '';
    let i = 0;
    while (i < lines.length) {
      const line = lines[i];
      if (/^```/.test(line)) {
        let code = '';
        i++;
        while (i < lines.length && !/^```/.test(lines[i])) { code += lines[i] + '\n'; i++; }
        i++;
        out += '<pre><code>' + code + '</code></pre>';
        continue;
      }
      if (/^\s*\|.*\|\s*$/.test(line) && i + 1 < lines.length && /^\s*\|?\s*:?-{2,}/.test(lines[i + 1])) {
        const cells = (l) => l.trim().replace(/^\||\|$/g, '').split('|').map((c) => inline(c.trim()));
        let html = '<table><thead><tr>' + cells(line).map((c) => '<th>' + c + '</th>').join('') + '</tr></thead><tbody>';
        i += 2;
        while (i < lines.length && /^\s*\|.*\|\s*$/.test(lines[i])) { html += '<tr>' + cells(lines[i]).map((c) => '<td>' + c + '</td>').join('') + '</tr>'; i++; }
        out += html + '</tbody></table>';
        continue;
      }
      const h = line.match(/^(#{1,4})\s+(.*)$/);
      if (h) { out += '<h' + Math.max(3, h[1].length + 1) + '>' + inline(h[2]) + '</h' + Math.max(3, h[1].length + 1) + '>'; i++; continue; }
      if (/^\s*([-*_])\1{2,}\s*$/.test(line)) { out += '<hr>'; i++; continue; }
      if (/^\s*[-*•]\s+/.test(line) || /^\s*\d+[.)]\s+/.test(line)) {
        const ordered = /^\s*\d+[.)]\s+/.test(line);
        let html = ordered ? '<ol>' : '<ul>';
        while (i < lines.length && (ordered ? /^\s*\d+[.)]\s+/ : /^\s*[-*•]\s+/).test(lines[i])) {
          html += '<li>' + inline(lines[i].replace(ordered ? /^\s*\d+[.)]\s+/ : /^\s*[-*•]\s+/, '')) + '</li>';
          i++;
        }
        out += html + (ordered ? '</ol>' : '</ul>');
        continue;
      }
      if (/^&gt;\s?/.test(line)) {
        let q = '';
        while (i < lines.length && /^&gt;\s?/.test(lines[i])) { q += inline(lines[i].replace(/^&gt;\s?/, '')) + '<br>'; i++; }
        out += '<blockquote>' + q + '</blockquote>';
        continue;
      }
      if (line.trim() === '') { i++; continue; }
      let p = inline(line);
      i++;
      while (i < lines.length && lines[i].trim() !== '' && !/^(#{1,4}\s|```|\s*[-*•]\s|\s*\d+[.)]\s|&gt;|\s*\|)/.test(lines[i])) { p += '<br>' + inline(lines[i]); i++; }
      out += '<p>' + p + '</p>';
    }
    return out;
  };
  $$('[data-markdown]').forEach((el) => { el.innerHTML = markdown(el.textContent); });

  // ---------- AI assistant ----------
  const AI = (() => {
    const panel = $('#ai-panel');
    if (!panel) return { open() {}, close() {}, clear() {}, ask() {} };
    const body = $('#ai-body');
    const intro = $('#ai-intro');
    const form = $('#ai-form');
    const text = $('#ai-text');
    const KEY = 'mhr-chat';
    let history = [];
    let busy = false;
    try { history = JSON.parse(sessionStorage.getItem(KEY) || '[]'); } catch (e) { history = []; }

    const save = () => { try { sessionStorage.setItem(KEY, JSON.stringify(history.slice(-30))); } catch (e) {} };
    const scroll = () => { body.scrollTop = body.scrollHeight; };
    const add = (role, content) => {
      const wrap = document.createElement('div');
      wrap.className = 'msg msg-' + (role === 'user' ? 'user' : 'ai');
      if (role === 'user') {
        const d = document.createElement('div');
        d.textContent = content;
        wrap.appendChild(d);
      } else if (role === 'error') {
        wrap.innerHTML = '<div class="msg-err"></div>';
        wrap.firstChild.textContent = content;
      } else {
        wrap.innerHTML = '<span class="ai-orb"><svg class="i"><use href="#i-spark"></use></svg></span><div class="md"></div>';
        const md = wrap.querySelector('.md');
        md.innerHTML = markdown(content);
        const tools = document.createElement('div');
        tools.className = 'msg-tools';
        tools.innerHTML = '<button type="button"><svg class="i i-sm"><use href="#i-copy"></use></svg>Copy</button>';
        tools.firstChild.addEventListener('click', () => {
          navigator.clipboard.writeText(content).then(() => { tools.firstChild.lastChild.textContent = 'Copied'; setTimeout(() => { tools.firstChild.lastChild.textContent = 'Copy'; }, 1600); });
        });
        md.appendChild(tools);
      }
      body.appendChild(wrap);
      if (intro) intro.hidden = true;
      scroll();
      return wrap;
    };
    history.forEach((m) => add(m.role, m.content));

    const open = () => {
      panel.classList.add('open');
      panel.setAttribute('aria-hidden', 'false');
      if (window.innerWidth < 900) scrim.classList.add('show');
      setTimeout(() => text && text.focus(), 200);
    };
    const close = () => {
      panel.classList.remove('open');
      panel.setAttribute('aria-hidden', 'true');
      if (!$('#sidebar')?.classList.contains('open')) scrim?.classList.remove('show');
    };
    const clear = () => {
      history = [];
      save();
      $$('.msg', body).forEach((m) => m.remove());
      if (intro) intro.hidden = false;
      text && text.focus();
    };
    const send = async (content) => {
      content = String(content || '').trim();
      if (!content || busy || !CFG.ai) return;
      busy = true;
      history.push({ role: 'user', content });
      add('user', content);
      const typing = document.createElement('div');
      typing.className = 'msg msg-ai';
      typing.innerHTML = '<span class="ai-orb"><svg class="i"><use href="#i-spark"></use></svg></span><div class="typing" aria-label="Thinking"><i></i><i></i><i></i></div>';
      body.appendChild(typing);
      scroll();
      const res = await api('api/ai/chat', { messages: history.slice(-16), page: CFG.page });
      typing.remove();
      busy = false;
      if (res.ok) {
        history.push({ role: 'assistant', content: res.reply });
        add('assistant', res.reply);
      } else {
        history.pop();
        add('error', res.error || 'The assistant could not answer. Try again.');
      }
      save();
    };
    const ask = (prompt) => { open(); if (CFG.ai) send(prompt); };

    if (form) {
      form.addEventListener('submit', (ev) => {
        ev.preventDefault();
        const v = text.value;
        text.value = '';
        text.style.height = '';
        send(v);
      });
      text.addEventListener('keydown', (ev) => {
        if (ev.key === 'Enter' && !ev.shiftKey && !ev.isComposing) { ev.preventDefault(); form.requestSubmit(); }
      });
      text.addEventListener('input', () => { text.style.height = 'auto'; text.style.height = Math.min(180, text.scrollHeight) + 'px'; });
    }
    return { open, close, clear, ask };
  })();

  // ---------- Attendance roster ----------
  $$('.roster-row').forEach((row) => {
    const post = async (status) => {
      const radio = row.querySelector('input[type=radio]:checked');
      const st = status || (radio && radio.value);
      if (!st) return;
      row.classList.add('saving');
      const res = await api('attendance/mark', {
        employee_id: row.dataset.employee,
        date: row.dataset.date,
        status: st,
        check_in: row.querySelector('[data-field=check_in]').value,
        check_out: row.querySelector('[data-field=check_out]').value,
      });
      row.classList.remove('saving');
      if (!res.ok) { toast(res.error || 'Could not save attendance.', 'error'); return; }
      if (res.check_in) row.querySelector('[data-field=check_in]').value = res.check_in;
      recount();
    };
    row.addEventListener('change', (ev) => {
      if (ev.target.matches('[data-mark]')) post(ev.target.value);
      else if (ev.target.matches('[data-field]')) {
        if (row.querySelector('input[type=radio]:checked')) post();
        else toast('Choose a status first, then the time is saved.', 'info');
      }
    });
  });
  const recount = () => {
    const counts = { unmarked: 0 };
    $$('.roster-row').forEach((row) => {
      const r = row.querySelector('input[type=radio]:checked');
      const k = r ? r.value : 'unmarked';
      counts[k] = (counts[k] || 0) + 1;
    });
    $$('[data-count]').forEach((el) => { el.textContent = counts[el.dataset.count] || 0; });
  };

  // ---------- CV upload & text extraction ----------
  const cvFile = $('#cv-file');
  if (cvFile) {
    const dz = $('#dropzone');
    const vendor = cvFile.dataset.vendor;
    const loadScript = (src) => new Promise((resolve, reject) => {
      if ($('script[src="' + src + '"]')) { resolve(); return; }
      const s = document.createElement('script');
      s.src = src; s.onload = resolve; s.onerror = () => reject(new Error('Could not load ' + src));
      document.head.appendChild(s);
    });
    const extract = async (file) => {
      const name = file.name.toLowerCase();
      if (name.endsWith('.txt')) return file.text();
      const buf = await file.arrayBuffer();
      if (name.endsWith('.pdf')) {
        await loadScript(vendor + '/pdfjs/pdf.min.js');
        window.pdfjsLib.GlobalWorkerOptions.workerSrc = vendor + '/pdfjs/pdf.worker.min.js';
        const pdf = await window.pdfjsLib.getDocument({ data: buf }).promise;
        let text = '';
        for (let p = 1; p <= Math.min(pdf.numPages, 30); p++) {
          const page = await pdf.getPage(p);
          const c = await page.getTextContent();
          text += c.items.map((it) => it.str).join(' ') + '\n\n';
        }
        return text;
      }
      if (name.endsWith('.docx')) {
        await loadScript(vendor + '/mammoth/mammoth.browser.min.js');
        const r = await window.mammoth.extractRawText({ arrayBuffer: buf });
        return r.value;
      }
      throw new Error('Use a PDF, DOCX or TXT file.');
    };
    const handle = async (file) => {
      if (!file) return;
      if (file.size > 10 * 1024 * 1024) { toast('This file is larger than 10 MB.', 'error'); return; }
      $('#dz-title').textContent = 'Reading ' + file.name + '…';
      try {
        const text = (await extract(file)).replace(/[ \t]+/g, ' ').replace(/\n{3,}/g, '\n\n').trim();
        if (text.length < 30) throw new Error('No readable text found. Scanned CVs need to be pasted as text.');
        $('#cv_text').value = text;
        $('#file_name').value = file.name;
        dz.classList.add('ready');
        $('#dz-title').textContent = file.name;
        $('#dz-sub').textContent = text.length.toLocaleString() + ' characters extracted. Choose another file to replace it.';
        const nameField = $('#name');
        if (nameField && !nameField.value) {
          const first = text.split('\n').map((l) => l.trim()).find((l) => /^[A-Za-z][A-Za-z .'-]{3,40}$/.test(l) && l.split(' ').length <= 4);
          if (first) nameField.value = first;
        }
      } catch (e) {
        dz.classList.remove('ready');
        $('#dz-title').textContent = 'Drop a CV here or click to choose';
        toast(e.message || 'Could not read this file.', 'error');
      }
    };
    cvFile.addEventListener('change', () => handle(cvFile.files[0]));
    ['dragenter', 'dragover'].forEach((e) => dz.addEventListener(e, (ev) => { ev.preventDefault(); dz.classList.add('over'); }));
    ['dragleave', 'drop'].forEach((e) => dz.addEventListener(e, (ev) => { ev.preventDefault(); dz.classList.remove('over'); }));
    dz.addEventListener('drop', (ev) => handle(ev.dataTransfer.files[0]));

    const role = $('#role');
    role && role.addEventListener('change', () => {
      const opt = $$('#roles option').find((o) => o.value === role.value);
      const kw = $('#keywords');
      if (opt && kw && !kw.value.trim()) kw.value = opt.dataset.keywords || '';
    });
  }

  // ---------- Leave form: live day count ----------
  const leaveForm = $('[data-leave-form]');
  if (leaveForm) {
    const from = $('#f_start_date');
    const to = $('#f_end_date');
    const out = $('#leave-days');
    const calc = () => {
      if (!from.value || !to.value) { out.textContent = 'Pick the dates'; return; }
      const d = Math.round((new Date(to.value) - new Date(from.value)) / 86400000) + 1;
      out.textContent = d > 0 ? d + (d === 1 ? ' day' : ' days') : 'End date is before start date';
      if (!to.value || to.value < from.value) to.min = from.value;
    };
    from.addEventListener('change', () => { if (!to.value) to.value = from.value; calc(); });
    to.addEventListener('change', calc);
  }

  // ---------- User form: role presets ----------
  const userForm = $('[data-user-form]');
  if (userForm) {
    const apply = () => {
      const role = (userForm.querySelector('[data-role]:checked') || {}).value;
      $('#perm-section').hidden = role === 'admin';
      $$('[data-edit-level]', userForm).forEach((r) => {
        r.disabled = role === 'viewer';
        if (role === 'viewer' && r.checked) {
          r.checked = false;
          const view = r.closest('.seg').querySelector('input[value=view]');
          if (view) view.checked = true;
        }
      });
    };
    userForm.addEventListener('change', (ev) => { if (ev.target.matches('[data-role]')) apply(); });
    apply();
  }

  // ---------- Tools dialog ----------
  const openToolDialog = (json) => {
    const d = $('#tool-dialog');
    if (!d) return;
    const f = d.querySelector('form');
    const data = json ? JSON.parse(json) : { id: 0, name: '', category: '', url: '', description: '' };
    ['id', 'name', 'category', 'url', 'description'].forEach((k) => { f.elements[k].value = data[k] || (k === 'id' ? 0 : ''); });
    d.querySelector('[data-title]').textContent = data.id ? 'Edit ' + data.name : d.querySelector('[data-title]').textContent.replace(/^Edit .*/, 'Add');
    d.showModal();
  };

  // ---------- Settings: test AI, provider model hint ----------
  const aiTest = async (btn) => {
    const out = $('#ai-test-result');
    btn.classList.add('is-busy');
    out.textContent = 'Testing…';
    const res = await api('settings/ai-test', {});
    btn.classList.remove('is-busy');
    out.textContent = res.ok ? 'Connected: ' + res.reply : res.error;
    out.style.color = res.ok ? 'var(--ok)' : 'var(--danger)';
  };
  const provSel = $('#ai_provider');
  if (provSel && $('#ai_model')) {
    provSel.addEventListener('change', () => {
      const opt = provSel.selectedOptions[0];
      const def = opt.dataset.model || { anthropic: 'claude-sonnet-5', openai: 'gpt-4.1-mini', gemini: 'gemini-2.5-flash' }[provSel.value];
      $('#ai_model').placeholder = 'Default: ' + def;
    });
  }

  // ---------- Browser notifications (once per day) ----------
  const notifyBox = $('[data-notify-permission]');
  if (notifyBox) {
    notifyBox.addEventListener('change', () => {
      if (notifyBox.checked && 'Notification' in window && Notification.permission === 'default') Notification.requestPermission();
    });
  }
  if (CFG.alerts && 'Notification' in window) {
    const today = new Date().toISOString().slice(0, 10);
    let last = '';
    try { last = localStorage.getItem('mhr-notified') || ''; } catch (e) {}
    if (last !== today) {
      const run = async () => {
        const res = await api('api/alerts/browser', null, 'GET');
        if (!res.ok || !res.enabled || (!res.expired && !res.due)) return;
        new Notification(CFG.company + ': renewals need attention', {
          body: res.expired + ' expired, ' + res.due + ' due soon' + (res.first ? '. First: ' + res.first : ''),
          tag: 'mhr-expiry',
        });
        try { localStorage.setItem('mhr-notified', today); } catch (e) {}
      };
      if (Notification.permission === 'granted') run();
    }
  }

  // ---------- Setup wizard ----------
  const Setup = (() => {
    const form = $('#setup-form');
    if (!form) return { next() {}, back() {}, testDb() {} };
    const steps = $$('.setup-step', form);
    const marks = $$('#steps li');
    const backBtn = $('[data-action="setup-back"]');
    const nextBtn = $('[data-action="setup-next"]');
    const finish = $('[data-action="setup-finish"]');
    const status = $('#db-status');
    let cur = 0;
    let dbOk = false;
    let existing = false;

    const show = (i) => {
      cur = i;
      steps.forEach((s, k) => s.classList.toggle('on', k === i));
      marks.forEach((m, k) => { m.classList.toggle('on', k === i); m.classList.toggle('done', k < i); });
      backBtn.hidden = i === 0;
      nextBtn.hidden = i === steps.length - 1;
      finish.hidden = i !== steps.length - 1;
      const first = steps[i].querySelector('input:not([type=hidden]):not([type=checkbox]), select');
      if (first && i > 0) setTimeout(() => first.focus(), 50);
    };
    const valid = (i) => {
      const fields = $$('input, select, textarea', steps[i]).filter((f) => !f.closest('[hidden]'));
      for (const f of fields) { if (!f.checkValidity()) { f.reportValidity(); return false; } }
      return true;
    };
    const setMode = () => {
      $('#new-install-fields').hidden = existing;
      $('#confirm-field').hidden = existing;
      $('#sample-field').hidden = existing;
      $('#pw-help').hidden = existing;
      $('#setup-meter').hidden = existing;
      $('#step3-title').textContent = existing ? 'Reconnect your existing data' : 'Your company and administrator account';
      $('#step3-lead').textContent = existing
        ? 'This database already contains Meridian HR data. Sign in with an existing administrator account to reconnect it. Nothing is deleted.'
        : 'This account has full access, including users and settings. You can add more people after setup.';
      ['company_name', 'admin_name'].forEach((id) => { $('#' + id).required = !existing; });
      $('#admin_password_confirm').required = !existing;
    };
    const testDb = async () => {
      if (!valid(1)) return false;
      status.className = 'db-status';
      status.textContent = 'Connecting…';
      const data = Object.fromEntries(['db_host', 'db_port', 'db_name', 'db_user', 'db_pass'].map((k) => [k, form.elements[k].value]));
      const res = await api('setup/check-database', data);
      dbOk = !!res.ok;
      existing = !!res.existing;
      status.className = 'db-status ' + (res.ok ? 'ok' : 'bad');
      status.textContent = res.ok
        ? 'Connected to MySQL ' + res.version.split('-')[0] + (existing ? '. Existing Meridian HR data found — you will reconnect it.' : '. The database is ready.')
        : res.error;
      setMode();
      return dbOk;
    };
    ['db_host', 'db_port', 'db_name', 'db_user', 'db_pass'].forEach((k) => form.elements[k].addEventListener('input', () => { dbOk = false; }));
    const next = async () => {
      if (!valid(cur)) return;
      if (cur === 1 && !dbOk && !(await testDb())) return;
      if (cur === 2 && !existing) {
        const p = form.elements.admin_password.value;
        if (p.length < 10 || !/[a-z]/i.test(p) || !/\d/.test(p)) { toast('Use at least 10 characters with letters and numbers.', 'error'); return; }
        if (p !== form.elements.admin_password_confirm.value) { toast('The two passwords do not match.', 'error'); return; }
      }
      show(Math.min(steps.length - 1, cur + 1));
    };
    const back = () => show(Math.max(0, cur - 1));
    form.addEventListener('keydown', (ev) => {
      if (ev.key === 'Enter' && ev.target.tagName === 'INPUT' && cur < steps.length - 1) { ev.preventDefault(); next(); }
    });
    // Jump to the right step if the server returned an error with remembered input
    if (form.elements.db_name.value) { show(form.elements.admin_email.value ? 2 : 1); }
    return { next, back, testDb };
  })();
})();
