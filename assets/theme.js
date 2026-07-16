(function(){
  try{document.documentElement.dataset.theme=localStorage.getItem('lf-theme')||'light'}catch(error){document.documentElement.dataset.theme='light'}
})();

document.addEventListener('DOMContentLoaded',()=>{
  if(!location.pathname.endsWith('/user-edit.php'))return;
  const base=location.pathname.replace(/user-edit\.php$/,'');
  const header=document.querySelector('header');if(!header)return;
  header.className='app-header user-edit-app-header';
  header.innerHTML='<div class="user-edit-brand"><img src="'+base+'assets/kirpisoftware-logo-transparent-v2.png" alt="Lost & Found"><strong>Lost &amp; Found</strong></div><nav class="user-edit-nav"><a href="'+base+'index.php">⌂ <span>Ana Sayfa</span></a><a href="'+base+'index.php">▣ <span>Bulunan Eşyalar</span></a><a href="'+base+'item-new.php">＋ <span>Eşya Ekle</span></a><a href="#">◎ <span>Talepler</span></a><a href="#">⇄ <span>Eşleşmeler</span></a><a href="#">▥ <span>Teslimatlar</span></a><a href="#">▤ <span>Raporlar</span></a><a class="settings-link" href="'+base+'admin.php#kullanicilar">⚙ <span>Ayarlar</span>⌄</a></nav>';
  const style=document.createElement('style');style.textContent='.user-edit-app-header{height:164px!important;padding:0!important;display:block!important;background:#fff!important;border-bottom:1px solid #e8e6ec!important;box-shadow:0 2px 8px rgba(47,43,61,.06)!important}.user-edit-brand{height:79px;display:flex;align-items:center;gap:14px;padding:0 28px}.user-edit-brand img{width:52px;height:52px;object-fit:contain}.user-edit-brand strong{font-size:28px;color:#7367f0}.user-edit-nav{height:84px;display:flex;align-items:center;gap:11px;padding:0 38px;border-top:1px solid #f0eef3}.user-edit-nav a{display:flex;align-items:center;gap:8px;padding:13px 12px;color:#5d596c;text-decoration:none;font-size:21px;white-space:nowrap}.user-edit-nav a:hover{color:#7367f0}.user-edit-nav .settings-link{margin-left:auto;background:linear-gradient(135deg,#ff9f43,#ff7b32);color:#fff;border-radius:9px;box-shadow:0 5px 14px rgba(255,126,48,.3);padding:15px 20px}@media(max-width:1100px){.user-edit-nav{overflow:auto;padding:0 18px}.user-edit-nav a{font-size:16px}.user-edit-brand{padding:0 18px}}';document.head.append(style);
});
