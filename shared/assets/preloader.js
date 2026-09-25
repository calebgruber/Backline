window.addEventListener('load', () => {
  const el = document.getElementById('global-preloader');
  if (el) el.classList.add('hidden');
});

document.addEventListener('click', (e) => {
  const anchor = e.target.closest('a[href]');
  if (!anchor) return;
  const rawHref = anchor.getAttribute('href') || '';
  const href = rawHref.trim().toLowerCase();
  if (
    href.startsWith('#') ||
    href.startsWith('javascript:') ||
    href.startsWith('data:') ||
    href.startsWith('vbscript:') ||
    anchor.target === '_blank'
  ) return;
  const el = document.getElementById('global-preloader');
  if (el) el.classList.remove('hidden');
});
