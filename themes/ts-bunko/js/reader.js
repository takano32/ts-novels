/* リーダー UI (設計 specs/S.27.md): 素の JS + localStorage。外部依存なし。
   設定は <html data-ts-*> 属性 (style.css のトークンが反応) + localStorage 'ts-reader'。
   しおりは URL パス単位のスクロール位置 (端末内完結、サーバに送らない)。 */
(function () {
  'use strict';
  var KEY = 'ts-reader';
  var BM_KEY = 'ts-bookmarks';
  var html = document.documentElement;

  function load(k) {
    try { return JSON.parse(localStorage.getItem(k) || '{}'); } catch (e) { return {}; }
  }
  function save(k, v) {
    try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) { /* プライベートモード等 */ }
  }

  var settings = load(KEY);
  var defaults = { size: '1', lh: '1', font: 'mincho', dark: 'auto', orig: '0' };

  function apply() {
    ['size', 'lh', 'font', 'dark', 'orig'].forEach(function (k) {
      var v = settings[k] || defaults[k];
      if (v === defaults[k] && k !== 'dark') { html.removeAttribute('data-ts-' + k); }
      else if (k === 'dark' && v === 'auto') { html.removeAttribute('data-ts-dark'); }
      else { html.setAttribute('data-ts-' + k, v); }
    });
    document.querySelectorAll('[data-ts-set]').forEach(function (btn) {
      var kv = btn.getAttribute('data-ts-set').split(':');
      var cur = settings[kv[0]] || defaults[kv[0]];
      btn.setAttribute('aria-pressed', String(cur === kv[1]));
    });
  }

  var toolbar = document.querySelector('[data-ts-toolbar]');
  if (toolbar) {
    toolbar.hidden = false;
    toolbar.addEventListener('click', function (ev) {
      var btn = ev.target.closest('[data-ts-set]');
      if (!btn) return;
      var kv = btn.getAttribute('data-ts-set').split(':');
      // 同じ値をもう一度押したら既定に戻す (ダークは auto に)
      settings[kv[0]] = (settings[kv[0]] === kv[1]) ? defaults[kv[0]] : kv[1];
      save(KEY, settings);
      apply();
    });
  }
  apply();

  // ---- 原本配色: raw フォールバックの話だけ、本文先頭の印から当時のページ配色を写す
  //      (切替そのものは上の orig 設定 → html[data-ts-orig]。specs/S.239.md) ----
  var pal = document.querySelector('.ts-reader.is-raw .ts-orig-palette');
  if (pal) {
    var box = pal.closest('.ts-reader');
    var COLOR = /^(#[0-9a-f]{3}([0-9a-f]{3})?|[a-z]+)$/i;
    ['bg', 'fg', 'link', 'vlink'].forEach(function (k) {
      var v = pal.getAttribute('data-' + k);
      if (v && COLOR.test(v)) box.style.setProperty('--ts-orig-' + k, v);
    });
    var img = pal.getAttribute('data-bgimg');
    if (img && /^\/assets\/annex-img\/[^"'()\\\s]+$/.test(img)) {
      box.style.setProperty('--ts-orig-bgimg', 'url("' + img + '")');
    }
  }

  // ---- キーボード話ナビ (← 前の話 / → 次の話) ----
  document.addEventListener('keydown', function (ev) {
    if (ev.altKey || ev.ctrlKey || ev.metaKey) return;
    var tag = (ev.target.tagName || '').toLowerCase();
    if (tag === 'input' || tag === 'textarea') return;
    var sel = ev.key === 'ArrowLeft' ? 'a[rel="prev"]' : ev.key === 'ArrowRight' ? 'a[rel="next"]' : null;
    if (!sel) return;
    var a = document.querySelector(sel);
    if (a) location.href = a.href;
  });

  // ---- しおり: 読んだ位置を自動保存し、再訪時に「続きから読む」を出す ----
  var reader = document.querySelector('.ts-reader');
  if (!reader) return;
  var path = location.pathname;
  var marks = load(BM_KEY);
  var saved = marks[path];

  if (saved && saved > 400 && Math.abs(window.scrollY - saved) > 400) {
    var btn = document.createElement('button');
    btn.className = 'ts-resume';
    btn.type = 'button';
    btn.textContent = '続きから読む';
    btn.addEventListener('click', function () {
      window.scrollTo({ top: saved, behavior: 'smooth' });
      btn.remove();
    });
    document.body.appendChild(btn);
    setTimeout(function () { if (btn.parentNode) btn.remove(); }, 15000);
  }

  var t = null;
  window.addEventListener('scroll', function () {
    if (t) return;
    t = setTimeout(function () {
      t = null;
      marks = load(BM_KEY);
      var bottom = window.scrollY + window.innerHeight >= document.body.scrollHeight - 50;
      if (bottom) { delete marks[path]; } // 読み終えたらしおりを消す
      else if (window.scrollY > 400) { marks[path] = window.scrollY; }
      else { return; }
      // 溜まりすぎ防止: 200 件を超えたら古そうなものから間引く
      var keys = Object.keys(marks);
      if (keys.length > 200) delete marks[keys[0]];
      save(BM_KEY, marks);
    }, 1500);
  }, { passive: true });
})();
