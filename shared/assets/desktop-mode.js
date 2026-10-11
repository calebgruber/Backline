(() => {
  if (!document.body.classList.contains('desktop-mode')) return;

  const routeClass = [...document.body.classList].find((c) => c.startsWith('route-')) || '';
  const isWorkspace = routeClass === 'route-dash-lx' || routeClass === 'route-dash-sound';
  const isDashboard = routeClass === 'route-dash-home';

  const setLastTab = (path, showId, tab) => {
    if (!showId || !tab) return;
    localStorage.setItem(`desktop:lastTab:${path}:show:${showId}`, tab);
  };

  const getLastTab = (path, showId) => {
    if (!showId) return null;
    return localStorage.getItem(`desktop:lastTab:${path}:show:${showId}`);
  };

  if (isDashboard) {
    document.querySelectorAll('a[href*="/dash/lx?show="], a[href*="/dash/sound?show="]').forEach((link) => {
      link.addEventListener('click', (event) => {
        const a = event.currentTarget;
        if (!(a instanceof HTMLAnchorElement)) return;
        const url = new URL(a.href, window.location.origin);
        const show = url.searchParams.get('show');
        if (!show) return;
        const remembered = getLastTab(url.pathname, show);
        if (remembered) {
          url.searchParams.set('tab', remembered);
          a.href = url.toString();
        }
      });
    });
  }

  if (!isWorkspace) return;

  const appPath = window.location.pathname;
  const url = new URL(window.location.href);
  const showId = url.searchParams.get('show') || '';
  const currentTab = url.searchParams.get('tab') || 'info';
  if (showId) setLastTab(appPath, showId, currentTab);

  const tabNav = document.querySelector('.nav.nav-tabs');
  const banner = document.querySelector('.shop-workspace-banner');
  if (!tabNav || !banner) return;
  const container = banner.parentElement;
  if (!container) return;

  const siblings = [...container.children];
  const startIndex = siblings.indexOf(banner);
  if (startIndex < 0) return;
  const blockNodes = siblings.slice(startIndex);

  const shell = document.createElement('div');
  shell.className = 'desktop-shell';
  const sidebar = document.createElement('aside');
  sidebar.className = 'desktop-sidebar';
  const main = document.createElement('section');
  main.className = 'desktop-main';

  const sidebarCard = document.createElement('div');
  sidebarCard.className = 'card desktop-window';
  const sidebarBody = document.createElement('div');
  sidebarBody.className = 'card-body';
  const navTitle = document.createElement('h3');
  navTitle.className = 'card-title mb-2';
  navTitle.textContent = 'Workspace Navigation';
  sidebarBody.appendChild(navTitle);
  sidebarBody.appendChild(tabNav);
  sidebarCard.appendChild(sidebarBody);
  sidebar.appendChild(sidebarCard);

  const toolbarCard = document.createElement('div');
  toolbarCard.className = 'card desktop-toolbar desktop-window mb-3';
  toolbarCard.innerHTML = `
    <div class="card-body d-flex flex-wrap gap-2 align-items-center">
      <button type="button" class="btn btn-outline-secondary btn-sm" data-desktop-action="toggle-sidebar"><i class="ti ti-layout-sidebar-left-collapse"></i> Toggle Sidebar</button>
      <button type="button" class="btn btn-outline-primary btn-sm" data-desktop-action="focus-search"><i class="ti ti-search"></i> Search</button>
      <button type="button" class="btn btn-outline-primary btn-sm" data-desktop-action="save"><i class="ti ti-device-floppy"></i> Save</button>
      <button type="button" class="btn btn-outline-primary btn-sm" data-desktop-action="next-tab"><i class="ti ti-arrow-right"></i> Next Tab</button>
      <span class="text-secondary small ms-auto">Shortcuts: Ctrl/Cmd+S save · Ctrl+/ search · Ctrl+] next tab</span>
    </div>`;
  main.appendChild(toolbarCard);

  blockNodes.forEach((node) => {
    if (node === tabNav) return;
    if (node instanceof HTMLElement && node.classList.contains('card')) {
      node.classList.add('desktop-window');
    }
    main.appendChild(node);
  });

  shell.appendChild(sidebar);
  shell.appendChild(main);
  container.appendChild(shell);

  const saveButton = () => document.querySelector('.desktop-main form button[type="submit"], .desktop-main form .btn.btn-primary');
  const searchInput = () => document.querySelector('.desktop-main input[type="search"], .desktop-main input[name="item_search"], .desktop-main .shop-search');
  const tabLinks = () => [...document.querySelectorAll('.desktop-sidebar .nav-link')];

  document.querySelectorAll('[data-desktop-action]').forEach((button) => {
    button.addEventListener('click', () => {
      const action = button.getAttribute('data-desktop-action');
      if (action === 'toggle-sidebar') {
        document.body.classList.toggle('desktop-sidebar-collapsed');
      } else if (action === 'focus-search') {
        searchInput()?.focus();
      } else if (action === 'save') {
        const btn = saveButton();
        if (btn instanceof HTMLElement) btn.click();
      } else if (action === 'next-tab') {
        const links = tabLinks();
        if (!links.length) return;
        const active = links.findIndex((link) => link.classList.contains('active'));
        const next = links[(active + 1) % links.length];
        if (next instanceof HTMLAnchorElement) window.location.href = next.href;
      }
    });
  });

  document.addEventListener('keydown', (event) => {
    const key = event.key.toLowerCase();
    const meta = event.ctrlKey || event.metaKey;
    if (meta && key === 's') {
      event.preventDefault();
      const btn = saveButton();
      if (btn instanceof HTMLElement) btn.click();
    }
    if (meta && key === '/') {
      event.preventDefault();
      searchInput()?.focus();
    }
    if (meta && event.key === ']') {
      event.preventDefault();
      const links = tabLinks();
      if (!links.length) return;
      const active = links.findIndex((link) => link.classList.contains('active'));
      const next = links[(active + 1) % links.length];
      if (next instanceof HTMLAnchorElement) window.location.href = next.href;
    }
  });
})();
