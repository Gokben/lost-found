window.addEventListener('load', () => {
  const collator = new Intl.Collator('tr', {numeric:true, sensitivity:'base'});
  document.querySelectorAll('main table').forEach((table, tableIndex) => {
    const headers = [...(table.tHead?.rows[0]?.cells || [])];
    const body = table.tBodies[0];
    if (headers.length < 3 || !body || table.dataset.columnTools) return;
    table.dataset.columnTools = '1';
    const key = 'lf-v2-columns:' + location.pathname + ':' + (new URLSearchParams(location.search).get('view') || '') + ':' + tableIndex;
    const columns = headers.map((cell, id) => ({id, label:cell.innerText.replace(/\s+/g,' ').trim() || 'Seçim', fixed:!cell.innerText.trim()}));
    [...table.rows].forEach(row => {
      if (row.cells.length !== headers.length) return;
      [...row.cells].forEach((cell,id) => {cell.dataset.columnId = id;});
    });
    let order = columns.map(c=>c.id), hidden = [], sortId = null, direction = 1;
    let saved;
    try {saved = JSON.parse(localStorage.getItem(key) || 'null');} catch {}
    if (saved && Array.isArray(saved.order) && saved.order.length === columns.length && new Set(saved.order).size === columns.length && saved.order.every(id=>columns[id])) {
      order = saved.order;
      hidden = (saved.hidden || []).filter(id=>columns[id] && !columns[id].fixed);
      if (hidden.length >= columns.filter(c=>!c.fixed).length) hidden=[];
    }
    const toolbar = document.createElement('div');
    toolbar.className = 'v2-table-toolbar';
    const hint = document.createElement('span'); hint.textContent='Sıralamak için sütun başlığına tıklayın';
    const details = document.createElement('details'); details.className='v2-column-menu';
    const summary = document.createElement('summary'); summary.textContent='Sütunlar';
    const settings = document.createElement('div'); settings.className='v2-column-options';
    details.append(summary,settings); toolbar.append(hint,details);
    const wrapper = table.closest('.table-wrap,.selection');
    if(wrapper) wrapper.before(toolbar); else table.before(toolbar);
    const save = () => {try {localStorage.setItem(key,JSON.stringify({order,hidden}));}catch{}};
    const apply = () => {
      [...table.rows].forEach(row=>{
        const cells = [...row.cells];
        if(cells.length!==headers.length){if(cells.length===1)cells[0].colSpan=columns.length-hidden.length;return;}
        order.forEach(id=>{
          const cell=cells.find(c=>Number(c.dataset.columnId)===id);
          if(cell){cell.classList.toggle('v2-column-hidden',hidden.includes(id));row.append(cell);}
        });
      });
      save();
    };
    const render = () => {
      settings.replaceChildren();
      const heading=document.createElement('strong');heading.textContent='Gösterilecek sütunlar';settings.append(heading);
      order.forEach((id,index)=>{
        const column=columns[id]; if(column.fixed)return;
        const row=document.createElement('div');row.className='v2-column-option';
        const label=document.createElement('label'),check=document.createElement('input');
        check.type='checkbox';check.checked=!hidden.includes(id);
        check.addEventListener('change',()=>{
          const visible=columns.filter(c=>!c.fixed&&!hidden.includes(c.id)).length;
          if(!check.checked&&visible===1){check.checked=true;return;}
          hidden=check.checked?hidden.filter(c=>c!==id):[...hidden,id];apply();
        });
        label.append(check,document.createTextNode(column.label));row.append(label);
        settings.append(row);
      });
      const reset=document.createElement('button');reset.type='button';reset.className='v2-column-reset';reset.textContent='Varsayılan sütun düzeni';
      reset.addEventListener('click',()=>{order=columns.map(c=>c.id);hidden=[];apply();render();});settings.append(reset);
    };
    const value = (row,id) => {
      const cell=[...row.cells].find(c=>Number(c.dataset.columnId)===id);
      return (cell?.innerText || '').trim();
    };
    const compare = (a,b) => {
      const date=/^(\d{2})\.(\d{2})\.(\d{4})(?:\s+(\d{2}):(\d{2}))?$/;
      const da=a.match(date),db=b.match(date);
      if(da&&db)return Date.UTC(+da[3],+da[2]-1,+da[1],+(da[4]||0),+(da[5]||0))-Date.UTC(+db[3],+db[2]-1,+db[1],+(db[4]||0),+(db[5]||0));
      return collator.compare(a,b);
    };
    headers.forEach((header,id)=>{
      if(columns[id].fixed||/^(Eylemler|İşlemler|Görseller)$/i.test(columns[id].label))return;
      const button=document.createElement('button');button.type='button';button.className='v2-sort-heading';button.textContent=columns[id].label;
      const arrow=document.createElement('span');arrow.className='v2-sort-arrow';arrow.textContent='↕';button.append(arrow);
      header.replaceChildren(button);header.setAttribute('aria-sort','none');
      button.addEventListener('click',()=>{
        direction=sortId===id?-direction:1;sortId=id;
        headers.forEach(h=>{h.setAttribute('aria-sort','none');const a=h.querySelector('.v2-sort-arrow');if(a)a.textContent='↕';});
        header.setAttribute('aria-sort',direction===1?'ascending':'descending');arrow.textContent=direction===1?'↑':'↓';
        const rows=[...body.rows].filter(row=>row.cells.length===columns.length);
        rows.sort((a,b)=>compare(value(a,id),value(b,id))*direction);
        rows.forEach(row=>body.append(row));
        table.dispatchEvent(new CustomEvent('lf:table-sorted',{detail:{rows}}));
      });
    });
    document.addEventListener('click',event=>{if(!details.contains(event.target))details.open=false;});
    details.addEventListener('keydown',event=>{if(event.key==='Escape'){details.open=false;summary.focus();}});
    apply();render();
  });
});
