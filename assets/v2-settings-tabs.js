document.addEventListener('DOMContentLoaded', () => {
  const main = document.querySelector('.admin-container');
  if (!main || document.getElementById('v2-settings-tabs')) return;
  const definitions = [['genel','Genel'],['yerler','Bulunduğu yerler'],['odalar','Odalar'],['departmanlar','Departmanlar'],['depolar','Depolar'],['kategoriler','Kategoriler'],['esyalar','Eşyalar'],['kullanicilar','Kullanıcılar']];
  const starts = [...main.children].filter(el => definitions.some(([id]) => el.id === id));
  const groups = new Map();
  starts.forEach(start => {
    const id = start.id;
    const panel = document.createElement('div');
    panel.id = 'v2-panel-' + id;
    panel.className = 'v2-settings-panel';
    panel.setAttribute('role', 'tabpanel');
    panel.setAttribute('aria-labelledby', 'v2-tab-' + id);
    start.before(panel);
    let node = start;
    while (node && !groups.has(node.id)) {
      if (node !== start && starts.includes(node)) break;
      const next = node.nextElementSibling;
      panel.append(node);
      node = next;
    }
    groups.set(id, panel);
  });
  const nav = document.createElement('div');
  nav.id = 'v2-settings-tabs';
  nav.setAttribute('role', 'tablist');
  nav.setAttribute('aria-label', 'Ayarlar bölümleri');
  const buttons = [];
  const select = (id, updateUrl = false) => {
    if (!groups.has(id)) id = 'genel';
    groups.forEach((panel, key) => { panel.hidden = key !== id; });
    buttons.forEach(button => {
      const active = button.dataset.section === id;
      button.setAttribute('aria-selected', String(active));
      button.tabIndex = active ? 0 : -1;
    });
    if (updateUrl) history.replaceState(null, '', '#' + id);
  };
  definitions.forEach(([id, label]) => {
    if (!groups.has(id)) return;
    const button = document.createElement('button');
    button.type = 'button';
    button.id = 'v2-tab-' + id;
    button.dataset.section = id;
    button.textContent = label;
    button.setAttribute('role', 'tab');
    button.setAttribute('aria-controls', 'v2-panel-' + id);
    button.addEventListener('click', () => select(id, true));
    button.addEventListener('keydown', event => {
      const index = buttons.indexOf(button);
      let next;
      if (event.key === 'ArrowRight') next = (index + 1) % buttons.length;
      if (event.key === 'ArrowLeft') next = (index - 1 + buttons.length) % buttons.length;
      if (event.key === 'Home') next = 0;
      if (event.key === 'End') next = buttons.length - 1;
      if (next === undefined) return;
      event.preventDefault();
      buttons[next].focus();
      select(buttons[next].dataset.section, true);
    });
    buttons.push(button);
    nav.append(button);
  });
  const title = main.querySelector('.entry-title');
  if (title) title.after(nav); else main.prepend(nav);
  window.addEventListener('hashchange', () => select(location.hash.slice(1)));
  select(location.hash.slice(1));
  document.documentElement.classList.remove('v2-settings-loading');
});
