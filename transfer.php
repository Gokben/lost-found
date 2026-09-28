<?php
require __DIR__.'/config.php'; require_once __DIR__.'/transfer-schema.php'; require_record_manager(); $pdo=db(); ensure_transfer_batch($pdo); $error='';
$storages=$pdo->query('SELECT name FROM storage_definitions WHERE active=1 ORDER BY sort_order,name')->fetchAll(PDO::FETCH_COLUMN);
$ids=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['item_ids']??[])),fn($id)=>$id>0)));
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf(); $target=trim($_POST['to_storage']??''); $notes=trim($_POST['notes']??'');
 if(!$ids)$error='En az bir eşya seçin.';
 elseif(!in_array($target,$storages,true))$error='Geçerli bir hedef depo seçin.';
 elseif(mb_strlen($notes)>512)$error='Not en fazla 512 karakter olabilir.';
 if(!$error)try{
 $pdo->beginTransaction();
 $q=$pdo->prepare('SELECT id,storage_location FROM items WHERE id IN ('.implode(',',array_fill(0,count($ids),'?')).')'.($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''));
 $q->execute($ids);$selected=$q->fetchAll();
 if(count($selected)!==count($ids))throw new RuntimeException('Bir eşya bulunamadı. Seçimi yenileyin.');
 foreach($selected as $item)if($item['storage_location']===$target)throw new RuntimeException('Seçilen eşyalardan biri zaten hedef depoda. Seçimi kontrol edin.');
 $batch=bin2hex(random_bytes(16));$now=(new DateTimeImmutable('now',new DateTimeZone('Europe/Istanbul')))->format('Y-m-d H:i:s');
 $u=$pdo->prepare('UPDATE items SET storage_location=?,updated_by=?,updated_at=? WHERE id=?');
 $t=$pdo->prepare('INSERT INTO storage_transfers(item_id,from_storage,to_storage,notes,transferred_by,transferred_at,batch_id) VALUES(?,?,?,?,?,?,?)');
 foreach($selected as $item){$u->execute([$target,$_SESSION['user']['name'],$now,$item['id']]);$t->execute([$item['id'],$item['storage_location'],$target,$notes,$_SESSION['user']['name'],$now,$batch]);}
 $printId=(int)$pdo->lastInsertId();$pdo->commit();redirect('transfer.php?transfer_id='.$printId);
 }catch(Throwable $ex){if($pdo->inTransaction())$pdo->rollBack();$error=$ex instanceof RuntimeException?$ex->getMessage():'Transfer kaydedilemedi.';}
}
$items=$pdo->query('SELECT id,item_no,serial_no,category,name,quantity,storage_location,found_at,location,found_by,recorded_by,color FROM items ORDER BY id DESC')->fetchAll();
$history=$pdo->query('SELECT t.*,i.item_no,i.category,i.name,i.color FROM storage_transfers t LEFT JOIN items i ON i.id=t.item_id ORDER BY t.id DESC LIMIT 100')->fetchAll();
$profileStmt=db()->prepare('SELECT setting_value FROM settings WHERE setting_key=?');$profileStmt->execute(['profile_'.(int)$_SESSION['user']['id']]);$profile=json_decode((string)$profileStmt->fetchColumn(),true)?:[];$avatar=$profile['avatar']??'';
?>
<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Depo Transferi</title><link rel="stylesheet" href="<?=url('assets/style.css')?>"><link rel="stylesheet" href="<?=url('assets/vuexy-inspired.css')?>"><style>.selection{max-height:440px;overflow:auto}.selection input{width:auto}.selection table{min-width:650px}.search{padding:20px;display:flex;gap:20px;align-items:center}.search input{max-width:480px}.picked{background:#f0edff}</style><link rel="stylesheet" href="<?=url('assets/dashboard-header.css')?>"><link rel="stylesheet" href="<?=url('assets/green-buttons.css?v=20260712-3')?>"><script src="<?=url('assets/theme.js?v=20260712-2')?>"></script></head><body><header class="app-header">
  <div class="header-top">
    <a class="brand brand-back-link" href="<?=url('index.php')?>" title="Ana sayfa"><img class="brand-logo" style="display:block!important;width:38px!important;height:38px!important;object-fit:contain!important" src="<?=url('assets/kirpisoftware-logo-transparent-v2.png')?>" alt="Kirpisoft"><span><b>Lost &amp; Found</b></span></a>
    <div class="header-tools">
      <button type="button" title="Arama" aria-label="Arama">⌕</button><button type="button" title="Dil">TR</button><button id="theme-toggle" type="button" title="Gece görünümüne geç" aria-label="Görünümü değiştir"><span class="theme-icon">☼</span></button>
      <div class="account-menu"><button id="account-toggle" class="account-toggle" type="button" aria-expanded="false"><span class="user-avatar"><?php if($avatar):?><img src="<?=url($avatar)?>" alt="Profil"><?php else:?><?=e(mb_strtoupper(mb_substr($_SESSION['user']['name'],0,1)))?><?php endif?></span><span class="user-name"><?=e($_SESSION['user']['name'])?><small><?=e($_SESSION['user']['role'])?></small></span><span class="account-chevron">⌄</span></button>
        <div id="account-dropdown" class="account-dropdown"><div class="account-card-head"><span class="user-avatar large"><?php if($avatar):?><img src="<?=url($avatar)?>" alt="Profil"><?php else:?><?=e(mb_strtoupper(mb_substr($_SESSION['user']['name'],0,1)))?><?php endif?></span><span><?=e($_SESSION['user']['name'])?><small><?=e($_SESSION['user']['role'])?></small></span></div><a href="<?=url('profile.php')?>"><span>♙</span> Profilim</a><?php if(is_admin()):?><a href="<?=url('admin.php')?>"><span>⚙</span> Ayarlar</a><?php endif?><a class="dropdown-logout" href="<?=url('logout.php')?>">Çıkış <span>↪</span></a></div>
      </div>
    </div>
  </div>
  <nav class="main-nav">
    <a href="<?=url('index.php')?>"><span>⌂</span> Ana Sayfa</a>
    <a href="<?=url('index.php')?>"><span>▣</span> Bulunan Eşyalar</a>
    <a href="<?=url('item-new.php')?>"><span>＋</span> Eşya Ekle</a>
    <a class="active" href="<?=url('transfer.php')?>"><span>⇄</span> Depo Transferi</a>
    <a href="<?=url('index.php?view=deliveries')?>"><span>▥</span> Teslimatlar</a><a href="#"><span>▤</span> Raporlar</a>
    <?php if(is_admin()):?><div class="settings-nav-menu"><a id="settings-nav-toggle" href="<?=url('admin.php')?>" aria-haspopup="true" aria-expanded="false"><span>⚙</span> Ayarlar <span class="settings-nav-chevron">⌄</span></a><div class="settings-nav-dropdown" id="settings-nav-dropdown"><a href="<?=url('admin.php#genel')?>"><span>⚙</span> Genel</a><a href="<?=url('admin.php#yerler')?>"><span>⌖</span> Bulunduğu yerler</a><a href="<?=url('admin.php#departmanlar')?>"><span>▦</span> Departmanlar</a><a href="<?=url('admin.php#depolar')?>"><span>▣</span> Depolar</a><a href="<?=url('admin.php#kategoriler')?>"><span>◆</span> Kategoriler</a><a href="<?=url('admin.php#esyalar')?>"><span>◆</span> Eşyalar</a><a href="<?=url('admin.php#kullanicilar')?>"><span>♙</span> Kullanıcılar</a></div></div><?php endif?>
  </nav>
</header><main class="container"><div class="title-row"><div><h1>Depo Transferi</h1><p>Eşyaları seçin ve birlikte hedef depoya aktarın.</p></div></div>
<?php if($error):?><div class="alert"><?=e($error)?></div><?php endif?>
<?php if(isset($_GET['transfer_id'])):?><div class="panel search">Transfer kaydedildi. <a class="button primary" target="_blank" href="<?=url('transfer-print.php?id='.(int)$_GET['transfer_id'])?>">Toplu transfer formunu yazdır</a></div><?php endif?>
<form method="post" class="panel"><input type="hidden" name="csrf" value="<?=csrf()?>"><div class="search"><input id="search" type="search" placeholder="Eşya no, seri no, eşya, depo veya tarih ara (18.01.2026)" aria-label="Eşya ara"><strong id="count"></strong></div><div class="selection"><table><thead><tr><th><input id="all" type="checkbox" aria-label="Görünen eşyaları seç"></th><th>Eşya no / Seri no</th><th>Bulunma tarihi</th><th>Eşyanın tanımı</th><th>Bulunan yer</th><th>Teslim eden personel</th><th>Kayıt eden</th><th>Miktar</th><th>Mevcut depo</th></tr></thead><tbody>
<?php foreach($items as $item):?>
<?php $foundDate=DateTimeImmutable::createFromFormat('!Y-m-d',substr((string)$item['found_at'],0,10)); $dateText=$foundDate?$foundDate->format('d.m.Y'):''; ?>
<tr data-item-url="<?=e(url('item-edit.php?id='.(int)$item['id']))?>" tabindex="0" style="cursor:pointer" data-search="<?=e(implode(' ',array_values($item)).' '.$dateText.' '.str_replace('.','/',$dateText))?>"><td><input type="checkbox" name="item_ids[]" value="<?=(int)$item['id']?>" aria-label="<?=e($item['item_no'].' seç')?>" <?=in_array((int)$item['id'],$ids,true)?'checked':''?>></td><td><?=e($item['item_no'])?><small><?=e($item['serial_no'])?></small></td><td><?=e($dateText)?></td><td><?=e($item['category'].' / '.$item['name'].($item['color']?' — '.$item['color']:''))?></td><td><?=e($item['location'])?></td><td><?=e($item['found_by'])?></td><td><?=e($item['recorded_by'])?></td><td><?=(int)$item['quantity']?></td><td><?=e($item['storage_location'])?></td></tr><?php endforeach?></tbody></table></div><div class="form-grid"><label>Hedef depo *<select name="to_storage" required><option value="">Depo seçiniz</option><?php foreach($storages as $s):?><option <?=($_POST['to_storage']??'')===$s?'selected':''?>><?=e($s)?></option><?php endforeach?></select></label><label>İşlemi yapan<input readonly value="<?=e($_SESSION['user']['name'])?>"></label><label class="wide">Transfer notu<textarea name="notes" maxlength="512" rows="3"><?=e($_POST['notes']??'')?></textarea></label><div class="wide actions"><button class="primary">Seçilenleri Transfer Et</button></div></div></form>
<section class="panel" style="margin-top:24px"><h2 style="padding:20px">Son Transferler</h2><form method="get" action="<?=url('transfer-print.php')?>" target="_blank" id="history-print"><div class="search"><button id="print-selected" class="primary" title="Seçilen transferleri yazdır" aria-label="Seçilen transferleri yazdır" disabled><svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle"><path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v7H6z"/><circle cx="18" cy="12" r=".5"/></svg></button><span id="history-count">0 transfer seçildi</span></div><div class="table-wrap"><table><thead><tr><th><input type="checkbox" id="history-all" aria-label="Tüm son transferleri seç"></th><th>Tarih</th><th>Eşya no</th><th>Eşyanın tanımı</th><th>Çıkış deposu</th><th>Hedef depo</th><th>İşlemi yapan</th></tr></thead><tbody><?php foreach($history as $t):?><tr><td><input type="checkbox" name="transfer_ids[]" value="<?=(int)$t['id']?>" aria-label="<?=e($t['item_no'].' transferini seç')?>"></td><td><?=e(date('d.m.Y H:i',strtotime($t['transferred_at'])))?></td><td><?=e($t['item_no'])?></td><td><?=e(trim(($t['category']??'').' / '.($t['name']??''), ' /').(!empty($t['color'])?' — '.$t['color']:''))?></td><td><?=e($t['from_storage'])?></td><td><?=e($t['to_storage'])?></td><td><?=e($t['transferred_by'])?></td></tr><?php endforeach?></tbody></table></div></form></section></main>
<script>
document.querySelectorAll('[data-item-url]').forEach(row=>{
 const openItem=()=>window.open(row.dataset.itemUrl,'_blank','noopener');
 row.addEventListener('click',event=>{if(event.target.closest('input,button,a,select,textarea'))return;openItem()});
 row.addEventListener('keydown',event=>{if(event.target!==row||event.key!=='Enter')return;event.preventDefault();openItem()});
});
</script>
<script>
const historyChecks=[...document.querySelectorAll('#history-print input[name="transfer_ids[]"]')],historyAll=document.getElementById('history-all');
function updateHistorySelection(){const count=historyChecks.filter(c=>c.checked).length;document.getElementById('history-count').textContent=count+' transfer seçildi';document.getElementById('print-selected').disabled=count===0;historyAll.checked=historyChecks.length>0&&count===historyChecks.length;historyAll.indeterminate=count>0&&count<historyChecks.length;}
historyChecks.forEach(c=>c.addEventListener('change',updateHistorySelection));historyAll.addEventListener('change',()=>{historyChecks.forEach(c=>c.checked=historyAll.checked);updateHistorySelection()});document.getElementById('history-print').addEventListener('submit',event=>{if(!historyChecks.some(c=>c.checked))event.preventDefault()});updateHistorySelection();
</script>
<script>
const rows=[...document.querySelectorAll('[data-search]')],all=document.getElementById('all'),search=document.getElementById('search');
const pageSize=10;let page=1,selectedOnly=false;
const showSelected=document.createElement('button');showSelected.type='button';showSelected.textContent='Seçilenleri göster';showSelected.setAttribute('aria-pressed','false');document.getElementById('count').after(showSelected);
showSelected.addEventListener('click',()=>{selectedOnly=!selectedOnly;page=1;render()});
const pager=document.createElement('div');pager.className='search';
const previous=document.createElement('button');previous.type='button';previous.textContent='Önceki';
const pageInfo=document.createElement('span');
const next=document.createElement('button');next.type='button';next.textContent='Sonraki';
pager.append(previous,pageInfo,next);document.querySelector('.selection').after(pager);
function update(){
 document.getElementById('count').textContent=rows.filter(r=>r.querySelector('input').checked).length+' eşya seçildi';
 showSelected.textContent=selectedOnly?'Tüm eşyaları göster':'Seçilenleri göster';showSelected.setAttribute('aria-pressed',String(selectedOnly));
 rows.forEach(r=>r.classList.toggle('picked',r.querySelector('input').checked));
 const visible=rows.filter(r=>!r.hidden);
 all.checked=visible.length>0&&visible.every(r=>r.querySelector('input').checked);
 all.indeterminate=!all.checked&&visible.some(r=>r.querySelector('input').checked);
}
function render(){
 const query=search.value.trim().toLocaleLowerCase('tr');
 const matches=rows.filter(r=>selectedOnly?r.querySelector('input').checked:r.dataset.search.toLocaleLowerCase('tr').includes(query));
 const pages=Math.max(1,Math.ceil(matches.length/pageSize));page=Math.min(page,pages);
 rows.forEach(r=>r.hidden=true);matches.slice((page-1)*pageSize,page*pageSize).forEach(r=>r.hidden=false);
 pageInfo.textContent='Sayfa '+page+' / '+pages+' — '+matches.length+' eşya';
 previous.disabled=page===1;next.disabled=page===pages;update();
}
rows.forEach(r=>r.querySelector('input').addEventListener('change',()=>selectedOnly?render():update()));
search.addEventListener('input',()=>{page=1;render()});
previous.addEventListener('click',()=>{page--;render()});
next.addEventListener('click',()=>{page++;render()});
all.addEventListener('change',()=>{rows.filter(r=>!r.hidden).forEach(r=>r.querySelector('input').checked=all.checked);selectedOnly?render():update()});
render();
</script><script>const root=document.documentElement,themeButton=document.getElementById('theme-toggle'),themeIcon=themeButton.querySelector('.theme-icon');function setTheme(theme){root.dataset.theme=theme;localStorage.setItem('lf-theme',theme);const dark=theme==='dark';themeIcon.textContent=dark?'☾':'☼';themeButton.title=dark?'Gündüz görünümüne geç':'Gece görünümüne geç'}setTheme(localStorage.getItem('lf-theme')||'light');themeButton.addEventListener('click',()=>setTheme(root.dataset.theme==='dark'?'light':'dark'));const accountButton=document.getElementById('account-toggle'),dropdown=document.getElementById('account-dropdown');accountButton.addEventListener('click',event=>{event.stopPropagation();const open=dropdown.classList.toggle('open');accountButton.setAttribute('aria-expanded',open)});document.addEventListener('click',event=>{if(!dropdown.contains(event.target)){dropdown.classList.remove('open');accountButton.setAttribute('aria-expanded','false')}});</script><?php if(is_admin()):?><script defer src="<?=url('assets/admin-menu.js?v=20260717-1')?>"></script><?php endif?></body></html>
