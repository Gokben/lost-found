const settingsNavToggle = document.getElementById('settings-nav-toggle');
const settingsNavDropdown = document.getElementById('settings-nav-dropdown');
const accountNavToggle = document.getElementById('account-toggle');
const accountNavDropdown = document.getElementById('account-dropdown');
const settingsNavMenu = settingsNavToggle.closest('.settings-nav-menu');
let settingsCloseTimer;

function openSettingsMenu() {
  clearTimeout(settingsCloseTimer);
  settingsNavDropdown.classList.add('open');
  settingsNavToggle.setAttribute('aria-expanded', 'true');
  accountNavDropdown.classList.remove('open');
  accountNavToggle.setAttribute('aria-expanded', 'false');
}

function closeSettingsMenu() {
  settingsNavDropdown.classList.remove('open');
  settingsNavToggle.setAttribute('aria-expanded', 'false');
}

settingsNavToggle.addEventListener('click', event => {
  event.preventDefault();
  event.stopPropagation();
  if (settingsNavDropdown.classList.contains('open')) closeSettingsMenu();
  else openSettingsMenu();
});

settingsNavMenu.addEventListener('mouseenter', () => {
  openSettingsMenu();
});

settingsNavMenu.addEventListener('mouseleave', () => {
  settingsCloseTimer = setTimeout(closeSettingsMenu, 120);
});

settingsNavDropdown.querySelectorAll('a').forEach(link => {
  link.addEventListener('click', closeSettingsMenu);
});

document.addEventListener('click', event => {
  if (settingsNavDropdown.contains(event.target)) return;
  closeSettingsMenu();
});
