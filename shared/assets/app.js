/* global app.js – shared across all Backline pages */

(function () {
  'use strict';

  /* ── Theme toggle ─────────────────────────────── */
  const THEME_KEY = 'cg-theme';

  function applyTheme(theme) {
    document.documentElement.setAttribute('data-bs-theme', theme);
    try {
      localStorage.setItem(THEME_KEY, theme);
    } catch (e) {
      // ignore storage write failures (private mode/policy)
    }
    const btn = document.getElementById('theme-toggle');
    if (btn) {
      btn.textContent = theme === 'dark' ? '☀️' : '🌙';
      btn.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
    }
  }

  function initTheme() {
    let saved = null;
    try {
      saved = localStorage.getItem(THEME_KEY);
    } catch (e) {
      saved = null;
    }
    saved = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    applyTheme(saved);
  }

  function toggleTheme() {
    const current = document.documentElement.getAttribute('data-bs-theme') || 'light';
    applyTheme(current === 'dark' ? 'light' : 'dark');
  }

  /* ── Dismissible alerts ───────────────────────── */
  function initAlerts() {
    document.querySelectorAll('.alert-close').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const alert = btn.closest('.alert');
        if (alert) {
          alert.style.opacity = '0';
          alert.style.transform = 'translateY(-4px)';
          alert.style.transition = 'opacity 200ms, transform 200ms';
          setTimeout(function () { alert.remove(); }, 200);
        }
      });
    });
  }

  /* ── Mobile sidebar toggle ────────────────────── */
  function initMobileMenu() {
    const btn = document.getElementById('mobile-menu-btn');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebar-overlay');

    if (!btn || !sidebar) return;
    function syncSidebarA11y() {
      var isMobile = window.matchMedia('(max-width: 768px)').matches;
      var isOpen = sidebar.classList.contains('open');
      sidebar.setAttribute('aria-hidden', isMobile && !isOpen ? 'true' : 'false');
      if (overlay) {
        overlay.classList.toggle('hidden', !isOpen);
      }
    }
    syncSidebarA11y();
    window.addEventListener('resize', syncSidebarA11y);

    btn.addEventListener('click', function () {
      sidebar.classList.toggle('open');
      syncSidebarA11y();
    });

    if (overlay) {
      overlay.addEventListener('click', function () {
        sidebar.classList.remove('open');
        syncSidebarA11y();
      });
    }
  }

  /* ── Auto-dismiss flash messages ─────────────── */
  function initFlash() {
    const flash = document.querySelectorAll('.alert[data-auto-dismiss]');
    flash.forEach(function (el) {
      const delay = parseInt(el.getAttribute('data-auto-dismiss'), 10) || 4000;
      setTimeout(function () {
        el.style.opacity = '0';
        el.style.transition = 'opacity 400ms';
        setTimeout(function () { el.remove(); }, 400);
      }, delay);
    });
  }

  /* ── Confirm dangerous actions ────────────────── */
  function initConfirm() {
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
      el.addEventListener('click', function (e) {
        const msg = el.getAttribute('data-confirm') || 'Are you sure?';
        if (!window.confirm(msg)) e.preventDefault();
      });
    });
  }

  /* ── Password strength indicator ─────────────── */
  function initPasswordStrength() {
    const pw = document.getElementById('password');
    const bar = document.getElementById('pw-strength-bar');
    if (!pw || !bar) return;
    const fill = bar.querySelector('.progress-fill');
    if (!fill) return;

    pw.addEventListener('input', function () {
      const val = pw.value;
      let score = 0;
      if (val.length >= 8)  score++;
      if (/[A-Z]/.test(val)) score++;
      if (/[0-9]/.test(val)) score++;
      if (/[^A-Za-z0-9]/.test(val)) score++;

      const pct = (score / 4) * 100;
      const colors = ['#ef4444', '#f59e0b', '#3b82f6', '#10b981'];
      fill.style.width = pct + '%';
      fill.style.background = colors[score - 1] || '#e2e8f0';
    });
  }

  /* ── Color picker preview ─────────────────────── */
  function initColorPreview() {
    document.querySelectorAll('input[type="color"][data-preview]').forEach(function (inp) {
      const target = document.getElementById(inp.getAttribute('data-preview'));
      if (!target) return;
      inp.addEventListener('input', function () {
        target.style.background = inp.value;
      });
    });
  }

  /* ── Dynamic form rows (add / remove) ────────── */
  function initDynamicRows() {
    document.querySelectorAll('[data-add-row]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const container = document.getElementById(btn.getAttribute('data-add-row'));
        if (!container) return;
        const template  = container.querySelector('[data-row-template]');
        if (!template) return;
        const clone = template.cloneNode(true);
        const cloneSuffix = Date.now() + '-' + Math.random().toString(36).slice(2, 8);
        clone.removeAttribute('data-row-template');
        clone.removeAttribute('hidden');
        clone.removeAttribute('aria-hidden');
        clone.classList.remove('d-none', 'hidden');
        const idMap = new Map();
        clone.querySelectorAll('[id]').forEach(function (el, index) {
          const oldId = el.id;
          const newId = oldId + '-clone-' + cloneSuffix + '-' + index;
          idMap.set(oldId, newId);
          el.id = newId;
        });
        clone.querySelectorAll('[for],[aria-describedby],[aria-labelledby]').forEach(function (el) {
          ['for', 'aria-describedby', 'aria-labelledby'].forEach(function (attr) {
            const value = el.getAttribute(attr);
            if (!value) return;
            const remapped = value
              .split(/\s+/)
              .map(function (token) { return idMap.get(token) || token; })
              .join(' ');
            el.setAttribute(attr, remapped);
          });
        });
        clone.querySelectorAll('[list]').forEach(function (el) {
          const value = el.getAttribute('list');
          if (!value) return;
          el.setAttribute('list', idMap.get(value) || value);
        });
        clone.querySelectorAll('input, select, textarea').forEach(function (el) {
          if (el instanceof HTMLInputElement) {
            if (el.type === 'checkbox' || el.type === 'radio') {
              el.checked = false;
            } else {
              el.value = '';
            }
            return;
          }
          if (el instanceof HTMLSelectElement) {
            if (el.options.length > 0) {
              el.selectedIndex = 0;
            }
            return;
          }
          el.value = '';
        });
        container.appendChild(clone);
      });
    });

    document.addEventListener('click', function (e) {
      const removeTrigger = e.target.closest('[data-remove-row]');
      if (removeTrigger) {
        e.preventDefault();
        const row = removeTrigger.closest('[data-row]');
        if (row) row.remove();
      }
    });
  }

  /* ── Page loader ──────────────────────────────── */
  function initPageLoader() {
    var loader = document.getElementById('page-loader');
    if (!loader) return;

    function startLoader() {
      loader.classList.remove('pg-done');
      loader.style.opacity  = '1';
      loader.style.animation = 'none';
      void loader.offsetWidth; // force reflow to restart animation
      loader.style.animation = '';
    }

    function maybeStartForLink(link, event) {
      if (!link) return;
      var href = link.getAttribute('href') || '';
      if (href === '' || href.charAt(0) === '#') return;
      var parsed;
      try {
        parsed = new URL(href, window.location.href);
      } catch (err) {
        return;
      }
      if (!/^https?:$/.test(parsed.protocol) || parsed.origin !== window.location.origin) return;
      var targetAttr = (link.getAttribute('target') || '').trim().toLowerCase();
      if ((targetAttr !== '' && targetAttr !== '_self') || link.hasAttribute('download')) return;
      if (event && (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey)) return;
      var currentNoHash = window.location.origin + window.location.pathname + window.location.search;
      var targetNoHash = parsed.origin + parsed.pathname + parsed.search;
      if (currentNoHash === targetNoHash) return;
      startLoader();
    }

    // Intercept same-origin link clicks
    document.addEventListener('click', function (e) {
      if (typeof e.button === 'number' && e.button !== 0) return;
      maybeStartForLink(e.target.closest('a[href]'), e);
    });

    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      maybeStartForLink(e.target.closest('a[href]'), e);
    });

    // Intercept form submits
    document.addEventListener('submit', function (e) {
      if (e.defaultPrevented) return;
      var form = e.target;
      if (!(form instanceof HTMLFormElement)) return;
      var target = (form.getAttribute('target') || '').trim().toLowerCase();
      if (target !== '' && target !== '_self') return;
      startLoader();
    });
  }

  /* ── Bootstrap on DOMContentLoaded ───────────── */
  document.addEventListener('DOMContentLoaded', function () {
    /* Complete the page loader */
    var loader = document.getElementById('page-loader');
    if (loader) loader.classList.add('pg-done');

    initTheme();
    initAlerts();
    initMobileMenu();
    initFlash();
    initConfirm();
    initPasswordStrength();
    initColorPreview();
    initDynamicRows();
    initPageLoader();

    /* Theme toggle button */
    const themeBtn = document.getElementById('theme-toggle');
    if (themeBtn) themeBtn.addEventListener('click', toggleTheme);
  });

})();
