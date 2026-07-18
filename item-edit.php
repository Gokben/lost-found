<?php
require __DIR__ . '/config.php';
require __DIR__ . '/item-images.php';
if (!function_exists('date_display')) {
    function date_display(?string $value): string {
        $value = trim((string)$value);
        if ($value === '') return '';
        try { return (new DateTimeImmutable($value, new DateTimeZone('Europe/Istanbul')))->format('d.m.Y'); }
        catch (Throwable) { return ''; }
    }
}
if (!function_exists('date_input_to_storage')) {
    function date_input_to_storage(?string $value): ?string {
        $value = trim((string)$value);
        if ($value === '') return null;
        foreach (['!d.m.Y','!Y-m-d','!Y-m-d\TH:i'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value, new DateTimeZone('Europe/Istanbul'));
            $errors = DateTimeImmutable::getLastErrors();
            if ($date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) return $date->format('Y-m-d');
        }
        return null;
    }
}
require_login();

function record_timestamp_display(?string $value): string {
    $value = trim((string)$value);
    if ($value === '') return 'Kayıt zamanı bulunamadı';
    try { return (new DateTimeImmutable($value, new DateTimeZone('Europe/Istanbul')))->format('d.m.Y H:i'); }
    catch (Throwable) { return 'Kayıt zamanı bulunamadı'; }
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM items WHERE id=?');
$stmt->execute([$id]);
$item = $stmt->fetch();
if (!$item) { http_response_code(404); exit('Kayıt bulunamadı.'); }
$error = '';
$locations = db()->query('SELECT name FROM location_definitions WHERE active=1 ORDER BY sort_order,name')->fetchAll();
if (!in_array('Oda', array_column($locations, 'name'), true)) $locations[] = ['name' => 'Oda'];
$roomNumbers = db()->query('SELECT room_number FROM room_definitions WHERE active=1 ORDER BY CAST(room_number AS INTEGER)')->fetchAll(PDO::FETCH_COLUMN);
$departments = db()->query('SELECT name FROM department_definitions WHERE active=1 ORDER BY sort_order,name')->fetchAll();
$storages = db()->query('SELECT name FROM storage_definitions WHERE active=1 ORDER BY sort_order,name')->fetchAll();
$definitions = db()->query('SELECT id,category,name FROM item_definitions WHERE active=1 ORDER BY sort_order,category,name')->fetchAll();
$statuses = ['Eşleşme bekliyor','Talep sahibinden eylem bekliyor','Yetkilendirilmiş kişi bekleniyor','Teslim edildi','Teslim edildi (Görüşüldü)','Depoda','Kargolandı','Tasfiye edildi'];
$currentDefinition = 'current';
foreach ($definitions as $definition) if ($definition['category'] === $item['category'] && $definition['name'] === $item['name']) { $currentDefinition = (string)$definition['id']; break; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $foundAt = date_input_to_storage($_POST['found_at'] ?? '');
    $deliveredAt = date_input_to_storage($_POST['delivered_at'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $roomNumber = trim($_POST['room_number'] ?? '');
    if ($location === 'Oda') {
        $location = $roomNumber;
        if (!preg_match('/^\d{3,4}$/', $roomNumber)) $error = 'Oda numarası 3 veya 4 rakam olmalıdır.';
        elseif (!in_array($roomNumber, $roomNumbers, true)) $error = 'Bu oda numarası daha önce kaydedilmemiş. Listeden mevcut bir oda numarası seçin.';
    }
    foreach (['item_no','found_at','location','found_department','item_definition','storage_location','status'] as $key) if (trim($_POST[$key] ?? '') === '') $error = 'Zorunlu alanları doldurun.';
    if (!$foundAt || (trim($_POST['delivered_at'] ?? '') !== '' && !$deliveredAt)) $error = 'Tarihleri gg.aa.yyyy biçiminde girin.';
    $category = $item['category']; $name = $item['name'];
    if (!$error && ($_POST['item_definition'] ?? 'current') !== 'current') {
        $definitionStmt = db()->prepare('SELECT category,name FROM item_definitions WHERE id=? AND active=1');
        $definitionStmt->execute([(int)$_POST['item_definition']]);
        $selected = $definitionStmt->fetch();
        if (!$selected) $error = 'Geçerli bir eşya seçin.';
        else { $category = $selected['category']; $name = $selected['name']; }
    }
    if (!$error) {
        $updatedAt = (new DateTimeImmutable('now', new DateTimeZone('Europe/Istanbul')))->format('Y-m-d H:i:s');
        $sql = 'UPDATE items SET item_no=?,serial_no=?,related_items=?,found_at=?,location=?,found_department=?,found_by=?,quantity=?,category=?,name=?,brand=?,color=?,details=?,storage_location=?,status=?,delivered_at=?,delivery_method=?,delivered_by=?,delivery_form_no=?,recorded_by=?,updated_at=? WHERE id=?';
        $values = [trim($_POST['item_no']),trim($_POST['serial_no'] ?? ''),trim($_POST['related_items'] ?? ''),$foundAt,$location,trim($_POST['found_department']),trim($_POST['found_by'] ?? ''),max(1,(int)($_POST['quantity'] ?? 1)),$category,$name,trim($_POST['brand'] ?? ''),trim($_POST['color'] ?? ''),trim($_POST['details'] ?? ''),trim($_POST['storage_location']),trim($_POST['status']),$deliveredAt,trim($_POST['delivery_method'] ?? ''),trim($_POST['delivered_by'] ?? ''),trim($_POST['delivery_form_no'] ?? ''),$_SESSION['user']['name'],$updatedAt,$id];
        try {
            db()->prepare($sql)->execute($values);
        } catch (PDOException $exception) {
            $error = $exception->getCode() === '23000'
                ? 'Bu eşya numarası başka bir kartta kullanılıyor. Benzersiz bir eşya numarası girin.'
                : 'Kayıt güncellenemedi. Lütfen alanları kontrol edip tekrar deneyin.';
        }
        if (!$error) {
            try {
                delete_item_images($id, $_POST['remove_image_ids'] ?? []);
                $error = upload_item_images($id, $_FILES['item_images'] ?? []);
            } catch (Throwable $exception) {
                error_log('Item image upload failed: ' . $exception->getMessage());
                $error = 'Görsel eklenemedi. Sunucudaki görsel depolama alanını kontrol edin.';
            }
        }
        if (!$error) redirect('index.php');
    }
}
function field(string $key): string { return e($_POST[$key] ?? $GLOBALS['item'][$key] ?? ''); }
function selected(string $value, string $current): string { return $value === $current ? 'selected' : ''; }
$isRoom = (bool)preg_match('/^\d{3,4}$/', (string)$item['location']);
$selectedLocation = $_POST['location'] ?? ($isRoom ? 'Oda' : $item['location']);
$roomValue = $_POST['room_number'] ?? ($isRoom ? $item['location'] : '');
$images = item_images_for_item($id);
$recordedAt = record_timestamp_display($item['created_at'] ?? '');
$updatedAt = record_timestamp_display($item['updated_at'] ?? $item['created_at'] ?? '');
?>
<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Eşya Düzenle | <?=APP_NAME?></title>
<link rel="icon" href="<?=url('assets/favicon.png')?>"><link rel="stylesheet" href="<?=url('assets/style.css')?>"><link rel="stylesheet" href="<?=url('assets/item-new.css')?>"><link rel="stylesheet" href="<?=url('assets/entry.css')?>"><link rel="stylesheet" href="<?=url('assets/item-images.css')?>"><link rel="stylesheet" href="<?=url('assets/required.css')?>"><link rel="stylesheet" href="<?=url('assets/home-link.css')?>"><link rel="stylesheet" href="<?=url('assets/amerce/fonts/fonts.css')?>"><link rel="stylesheet" href="<?=url('assets/amerce/css/bootstrap.min.css')?>"><link rel="stylesheet" href="<?=url('assets/amerce/css/styles.css')?>"><link rel="stylesheet" href="<?=url('assets/amerce-lf.css')?>"><link rel="stylesheet" href="<?=url('assets/vuexy-inspired.css')?>"><link rel="stylesheet" href="<?=url('assets/green-buttons.css?v=20260712-3')?>"><script src="<?=url('assets/theme.js?v=20260718-6')?>"></script></head><body>
<header><a class="brand brand-back-link" href="<?=url('index.php')?>"><img class="entry-brand-logo" src="<?=url('assets/kirpisoftware-logo-transparent-v2.png')?>" alt="Kirpisoft"><span><b>Lost &amp; Found</b></span></a><a class="home-link entry-home-icon" href="<?=url('index.php')?>" title="Ana sayfa" aria-label="Ana sayfa"><img src="<?=url('assets/home-icon.svg')?>" alt=""></a></header>
<main class="container entry-container"><div class="entry-title"><h1>Eşya Düzenle</h1><p><?=e($item['item_no'])?> numaralı eşyanın tüm bilgilerini güncelleyin.</p></div><?php if($error):?><div class="alert<?=str_starts_with($error,'Bu oda numarası')?' room-number-error':''?>"><?=e($error)?></div><?php endif?>
<form id="item-form" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="id" value="<?=$id?>"><section class="entry-panel"><div class="entry-grid">
<label>Eşya *<div class="search-select" data-search-select><input type="hidden" name="item_definition" value="<?=e($_POST['item_definition'] ?? $currentDefinition)?>" required><button class="search-select-trigger" type="button"><span><?=e($item['category'].' - '.$item['name'])?></span><i></i></button><div class="search-select-menu"><input class="search-select-input" type="search" placeholder="Eşya ara..."><div class="search-select-options" role="listbox"><button type="button" role="option" data-value="current" data-label="<?=e($item['category'].' - '.$item['name'])?>"><?=e($item['category'].' - '.$item['name'])?></button><?php foreach($definitions as $d):$label=$d['category'].' - '.$d['name'];?><button type="button" role="option" data-value="<?=(int)$d['id']?>" data-label="<?=e($label)?>"><?=e($label)?></button><?php endforeach?><p class="search-select-empty">Sonuç bulunamadı.</p></div></div></div></label>
<label>Eşya no *<input name="item_no" value="<?=field('item_no')?>" required></label><label>Seri no<input name="serial_no" value="<?=field('serial_no')?>"></label><label class="related-cards-field">İlgili kartlar<textarea name="related_items" rows="2" placeholder="Örn: F669054, F669055"><?=field('related_items')?></textarea></label>
<label>Bulunduğu yer *<select name="location" id="location-select" required><option value="">Yer seçiniz</option><?php foreach($locations as $locationRow):?><option <?=selected($locationRow['name'],$selectedLocation)?>><?=e($locationRow['name'])?></option><?php endforeach?></select></label><label id="room-number-field" hidden>Oda numarası *<input name="room_number" inputmode="numeric" pattern="\d{3,4}" maxlength="4" list="existing-room-numbers" value="<?=e($roomValue)?>" placeholder="Mevcut oda seçin"><datalist id="existing-room-numbers"><?php foreach($roomNumbers as $room):?><option value="<?=e($room)?>"><?php endforeach?></datalist></label><label>Bulunduğu tarih *<input name="found_at" value="<?=e($_POST['found_at'] ?? date_display($item['found_at']))?>" placeholder="gg.aa.yyyy" required></label>
<label>Bulan departman *<select name="found_department" required><option value="">Departman seçiniz</option><?php foreach($departments as $department):?><option <?=selected($department['name'],$_POST['found_department'] ?? $item['found_department'])?>><?=e($department['name'])?></option><?php endforeach?></select></label><label>Bulan bilgisi<input name="found_by" value="<?=field('found_by')?>"></label><label>Kaydeden<input value="<?=e($_SESSION['user']['name'])?>" readonly tabindex="-1"></label>
<label>Marka<input name="brand" value="<?=field('brand')?>"></label><label>Renk<input name="color" value="<?=field('color')?>"></label><label>Miktar<input type="number" name="quantity" min="1" value="<?=field('quantity')?>"></label><label>Depo *<select name="storage_location" required><option value="">Depo seçiniz</option><?php foreach($storages as $storage):?><option <?=selected($storage['name'],$_POST['storage_location'] ?? $item['storage_location'])?>><?=e($storage['name'])?></option><?php endforeach?></select></label><label>Durum *<select name="status" required><?php foreach($statuses as $status):?><option <?=selected($status,$_POST['status'] ?? $item['status'])?>><?=e($status)?></option><?php endforeach?></select></label>
<label>Teslimat tarihi<input name="delivered_at" value="<?=e($_POST['delivered_at'] ?? date_display($item['delivered_at']))?>" placeholder="gg.aa.yyyy"></label><label>Teslim şekli<input name="delivery_method" value="<?=field('delivery_method')?>"></label><label>Teslim eden<input name="delivered_by" value="<?=field('delivered_by')?>"></label><label>Teslim form no<input name="delivery_form_no" value="<?=field('delivery_form_no')?>"></label>
<label class="details-field">Açıklama<textarea name="details" rows="2"><?=field('details')?></textarea></label><div class="item-images-field">Görsel Ekle<div class="item-image-uploader" data-image-upload data-existing="<?=count($images)?>"><label class="item-image-upload-button camera-upload-button" title="Görsel ekle" aria-label="Görsel ekle"><img src="<?=url('assets/camera-icon.svg')?>" alt=""><input type="file" name="item_images[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple></label><div class="item-image-preview"><?php foreach($images as $image):?><label class="item-image-tile"><img src="<?=url($image['image_path'])?>" alt="Eşya görseli"><input type="checkbox" name="remove_image_ids[]" value="<?=(int)$image['id']?>"><button class="item-image-remove" type="button" onclick="this.parentElement.classList.toggle('marked');this.parentElement.querySelector('input').checked=this.parentElement.classList.contains('marked')">×</button></label><?php endforeach?></div><span class="item-image-count"><?=count($images)?>/4 görsel</span><span class="item-image-help">En fazla 4 görsel, her biri en çok 5 MB.</span></div></div><div class="record-meta-field"><div><span>Kayıt tarihi ve saati</span><strong><?=e($recordedAt)?></strong></div><div class="update-meta"><span>Güncelleme tarihi ve saati</span><strong><?=e($updatedAt)?></strong></div></div>
</div></section><div class="entry-actions"><a class="button secondary cancel-icon-button" href="<?=url('index.php')?>" title="Vazgeç" aria-label="Vazgeç"><img src="<?=url('assets/cancel-icon.svg')?>" alt=""></a><a class="button new-button add-related-action" href="<?=url('item-new.php?parent_id='.$id)?>" title="Eşya Ekle" aria-label="Eşya Ekle"><img src="<?=url('assets/add-item-icon.svg')?>" alt=""></a><a class="button new-button" target="_blank" href="<?=url('item-label.php?id='.$id)?>">Etiket Yazdır</a><button class="save-button" type="submit">Kaydet</button></div></form></main>
<script src="<?=url('assets/required.js')?>"></script><script src="<?=url('assets/item-images.js?v=20260718-4')?>"></script></body></html>
