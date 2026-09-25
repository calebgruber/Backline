window.addEventListener('load', () => {
  const el = document.getElementById('global-preloader');
  if (el) el.classList.add('hidden');
});

const showPreloader = () => {
  const el = document.getElementById('global-preloader');
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

document.addEventListener('click', (e) => {
  const anchor = e.target.closest('a[href]');
  if (!anchor) return;
  if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
  if (!isNavigableAnchor(anchor)) return;
  showPreloader();
});

document.addEventListener('keydown', (e) => {
  if (e.defaultPrevented || e.key !== 'Enter' || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
  const active = document.activeElement;
  if (!(active instanceof HTMLAnchorElement) || !active.hasAttribute('href')) return;
  if (!isNavigableAnchor(active)) return;
  showPreloader();
});
