document.querySelectorAll('label').forEach(label => {
  if (!label.querySelector('input:required, select:required, textarea:required')) return;
  if (label.querySelector(':scope > .required-star')) return;
  const node = [...label.childNodes].find(item => item.nodeType === 3 && item.textContent.trim());
  if (!node) return;
  node.textContent = node.textContent.replace(/\s*\*\s*$/, '');
  const star = document.createElement('span');
  star.className = 'required-star';
  star.textContent = '*';
  node.after(star);
});

const itemForm = document.getElementById('item-form');
if (itemForm) itemForm.addEventListener('submit', event => {
  const item = document.querySelector('input[name="item_definition"]');
  if (item.value) return;
  event.preventDefault();
  const select = document.querySelector('[data-search-select]');
  select.classList.add('open');
  select.querySelector('.search-select-trigger').setAttribute('aria-expanded', 'true');
  select.querySelector('.search-select-input').focus();
});

document.querySelectorAll('[data-search-select]').forEach(select => {
  const hidden = select.querySelector('input[type=hidden]');
  const trigger = select.querySelector('.search-select-trigger');
  const caption = trigger.querySelector('span');
  const search = select.querySelector('.search-select-input');
  const options = [...select.querySelectorAll('[role=option]')];
  const close = () => { select.classList.remove('open'); trigger.setAttribute('aria-expanded', 'false'); };
  trigger.addEventListener('click', () => { select.classList.toggle('open'); trigger.setAttribute('aria-expanded', String(select.classList.contains('open'))); if (select.classList.contains('open')) search.focus(); });
  search.addEventListener('input', () => options.forEach(option => option.hidden = !option.dataset.label.toLocaleLowerCase('tr-TR').includes(search.value.toLocaleLowerCase('tr-TR'))));
  options.forEach(option => option.addEventListener('click', () => { hidden.value = option.dataset.value; caption.textContent = option.dataset.label; close(); }));
  document.addEventListener('click', event => { if (!select.contains(event.target)) close(); });
});

const locationSelect = document.getElementById('location-select');
const roomField = document.getElementById('room-number-field');
if (locationSelect && roomField) {
  const roomInput = roomField.querySelector('input');
  const toggleRoom = () => { const active = locationSelect.value === 'Oda'; roomField.hidden = !active; roomInput.required = active; };
  locationSelect.addEventListener('change', toggleRoom); toggleRoom();
}

const relatedValue = document.getElementById('related-items-value');
const relatedRows = document.getElementById('related-card-rows');
if (relatedValue && relatedRows) {
  const sync = () => relatedValue.value = [...relatedRows.querySelectorAll('input')].map(input => input.value.trim()).filter(Boolean).join(', ');
  const add = (value = '') => { const row = document.createElement('div'); row.className = 'related-card-row'; row.innerHTML = '<input placeholder="Kart numarası (örn. F669054)"><button type="button" aria-label="Kartı kaldır">×</button>'; const input = row.querySelector('input'); input.value = value; input.addEventListener('input', sync); row.querySelector('button').addEventListener('click', () => { row.remove(); sync(); }); relatedRows.append(row); };
  document.getElementById('add-related-card').addEventListener('click', () => add());
  relatedValue.value.split(',').map(value => value.trim()).filter(Boolean).forEach(add);
  document.querySelectorAll('.card-tab').forEach(tab => tab.addEventListener('click', () => { document.querySelectorAll('.card-tab').forEach(node => node.classList.toggle('active', node === tab)); document.querySelectorAll('.card-tab-content').forEach(node => node.classList.toggle('active', node.id === tab.dataset.tab)); }));
}
