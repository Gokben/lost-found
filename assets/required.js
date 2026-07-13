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
