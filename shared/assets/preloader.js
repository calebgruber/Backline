window.addEventListener('load', () => {
  const el = document.getElementById('global-preloader');
  if (el) el.classList.add('hidden');
});

document.addEventListener('click', (e) => {
  const anchor = e.target.closest('a[href]');
  if (!anchor) return;
  const href = anchor.getAttribute('href') || '';
  if (href.startsWith('#') || href.startsWith('javascript:') || anchor.target === '_blank') return;
  const el = document.getElementById('global-preloader');
  if (el) el.classList.remove('hidden');
});
