window.addEventListener('load', () => {
  const el = document.getElementById('global-preloader');
  const text = document.getElementById('global-preloader-text');
  if (text) text.textContent = 'Loading…';
  if (el) el.classList.add('hidden');
});

const showPreloader = (label) => {
  const el = document.getElementById('global-preloader');
  const text = document.getElementById('global-preloader-text');
  if (text) text.textContent = (label || 'Loading…');
  if (el) el.classList.remove('hidden');
};

const isNavigableAnchor = (anchor) => {
  const rawHref = anchor.getAttribute('href') || '';
  const href = rawHref.trim().toLowerCase();
  if (
    href.startsWith('#') ||
    href.startsWith('javascript:') ||
    href.startsWith('data:') ||
    href.startsWith('vbscript:') ||
    anchor.target === '_blank' ||
    (anchor.target && anchor.target !== '_self') ||
    anchor.hasAttribute('download')
  ) return false;
  return true;
};

const resolvesToSameDocument = (anchor) => {
  try {
    const target = new URL(anchor.href, window.location.href);
    return target.origin === window.location.origin &&
      target.pathname === window.location.pathname &&
      target.search === window.location.search;
  } catch {
    return true;
  }
};

document.addEventListener('click', (e) => {
  const anchor = e.target.closest('a[href]');
  if (!anchor) return;
  if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
  if (!isNavigableAnchor(anchor)) return;
  if (resolvesToSameDocument(anchor)) return;
  let label = '';
  try {
    const target = new URL(anchor.href, window.location.href);
    const inApp = window.location.pathname.startsWith('/lx') || window.location.pathname.startsWith('/snd');
    if (inApp && target.pathname === '/dash/home') label = 'Exiting app…';
  } catch {
    label = '';
  }
  showPreloader(label);
});

document.addEventListener('keydown', (e) => {
  if (e.defaultPrevented || e.key !== 'Enter' || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
  const active = document.activeElement;
  if (!(active instanceof HTMLAnchorElement) || !active.hasAttribute('href')) return;
  if (!isNavigableAnchor(active)) return;
  if (resolvesToSameDocument(active)) return;
  let label = '';
  try {
    const target = new URL(active.href, window.location.href);
    const inApp = window.location.pathname.startsWith('/lx') || window.location.pathname.startsWith('/snd');
    if (inApp && target.pathname === '/dash/home') label = 'Exiting app…';
  } catch {
    label = '';
  }
  showPreloader(label);
});
