(() => {
  'use strict';
  const layout = document.createElement('style');
  layout.textContent = '.entry-grid .add-related-item{grid-column:3 / 4!important;align-self:end!important;min-height:105px!important;display:flex!important;align-items:center!important;justify-content:center!important;padding:12px!important;background:#fffaf5!important;border:1px dashed #f97316!important;border-radius:9px!important}.entry-grid .add-related-item .new-button{width:100%!important;max-width:190px!important;min-height:48px!important;margin:0!important;border-radius:8px!important;font-size:17px!important;font-weight:700!important}@media(max-width:1200px){.entry-grid .add-related-item{grid-column:auto!important}}';
  document.head.append(layout);
  document.querySelectorAll('.item-image-tile > input[type=checkbox]').forEach(input => input.hidden = true);
  document.addEventListener('click', event => {
    if (event.target.closest('.item-image-remove')) { event.preventDefault(); event.stopPropagation(); }
  });
  document.querySelectorAll('[data-image-upload]').forEach(area => {
    const input = area.querySelector('input[type=file]');
    const preview = area.querySelector('.item-image-preview');
    const count = area.querySelector('.item-image-count');
    const existing = Number(area.dataset.existing || 0);
    let pending = [];
    const refresh = () => {
      const remaining = Math.max(0, 4 - existing);
      pending = pending.slice(0, remaining);
      const transfer = new DataTransfer(); pending.forEach(file => transfer.items.add(file)); input.files = transfer.files;
      preview.querySelectorAll('[data-new-preview]').forEach(node => node.remove());
      pending.forEach(file => { const tile = document.createElement('div'); tile.className = 'item-image-tile'; tile.dataset.newPreview = '1'; const image = document.createElement('img'); image.src = URL.createObjectURL(file); image.alt = file.name; tile.append(image); preview.append(tile); });
      count.textContent = `${existing + pending.length}/4 görsel`;
    };
    input?.addEventListener('change', () => {
      [...input.files].forEach(file => {
        const duplicate = pending.some(current => current.name === file.name && current.size === file.size && current.lastModified === file.lastModified);
        if (!duplicate && pending.length < 4 - existing) pending.push(file);
      });
      refresh();
    });
    refresh();
  });
  const lightbox = document.createElement('div');
  lightbox.className = 'image-lightbox';
  lightbox.innerHTML = '<div class="image-lightbox-content"><button class="image-lightbox-close" type="button">×</button><button class="image-lightbox-prev" type="button">‹</button><img alt="Eşya görseli"><button class="image-lightbox-next" type="button">›</button><span class="image-lightbox-counter"></span></div>';
  document.body.append(lightbox);
  let images = [], current = 0;
  const image = lightbox.querySelector('img'), counter = lightbox.querySelector('.image-lightbox-counter');
  const show = index => { if (!images.length) return; current = (index + images.length) % images.length; image.src = images[current]; counter.textContent = `${current + 1} / ${images.length}`; lightbox.querySelector('.image-lightbox-prev').hidden = images.length < 2; lightbox.querySelector('.image-lightbox-next').hidden = images.length < 2; };
  image.addEventListener('click', () => { if (images.length > 1) show(current + 1); });
  document.addEventListener('click', event => {
    const trigger = event.target.closest('[data-item-images]');
    if (trigger) { images = JSON.parse(trigger.dataset.itemImages || '[]'); show(0); lightbox.classList.add('open'); return; }
    if (event.target === lightbox || event.target.closest('.image-lightbox-close')) lightbox.classList.remove('open');
    if (event.target.closest('.image-lightbox-prev')) show(current - 1);
    if (event.target.closest('.image-lightbox-next')) show(current + 1);
  });
  document.addEventListener('keydown', event => { if (!lightbox.classList.contains('open')) return; if (event.key === 'Escape') lightbox.classList.remove('open'); if (event.key === 'ArrowLeft') show(current - 1); if (event.key === 'ArrowRight') show(current + 1); });
})();
