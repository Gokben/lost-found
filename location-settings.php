<?php
require_once __DIR__.'/config.php';
require_login();
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($_POST['action'] ?? '') !== 'location_add') {
        http_response_code(403);
        exit('Bu bölümde yalnızca yeni yer ekleyebilirsiniz.');
    }
    $name = trim((string)($_POST['location_name'] ?? ''));
    if ($name === '' || mb_strlen($name) > 255) {
        $error = 'Yer adı 1–255 karakter olmalıdır.';
    } else {
        try {
            $order = (int)db()->query('SELECT COALESCE(MAX(sort_order),0)+1 FROM location_definitions')->fetchColumn();
            $statement = db()->prepare('INSERT INTO location_definitions (name,sort_order) VALUES (?,?)');
            $statement->execute([$name,$order]);
            redirect('admin.php?location_added=1#yerler');
        } catch (PDOException $exception) {
            $error = 'Yer eklenemedi. Aynı isimde bir yer tanımı bulunuyor olabilir.';
        }
    }
}
if (isset($_GET['location_added'])) $message = 'Yer tanımı eklendi.';
$locations = db()->query('SELECT name FROM location_definitions WHERE active=1 ORDER BY sort_order,name')->fetchAll();
?>
<!doctype html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Bulunduğu yerler | <?=e(APP_NAME)?></title>
<link rel="icon" href="<?=url('assets/favicon.png')?>">
<link rel="stylesheet" href="<?=url('assets/style.css')?>">
<link rel="stylesheet" href="<?=url('assets/entry.css')?>">
<link rel="stylesheet" href="<?=url('assets/admin.css')?>">
<link rel="stylesheet" href="<?=url('assets/dashboard-header.css')?>">
<script src="<?=url('assets/theme.js?v=20260718-6')?>"></script>
<link rel="stylesheet" href="<?=url('assets/v2-design.css?v=20261021-1')?>">
</head><body class="admin-page">
<header class="app-header"><div class="header-top">
<a class="brand" href="<?=url('index.php')?>"><img class="brand-logo" src="<?=url('assets/kirpisoftware-logo-transparent-v2.png')?>" alt=""><b>Lost &amp; Found</b></a>
<div class="header-tools"><a href="<?=url('index.php')?>" style="color:#fff">Ana sayfa</a></div>
</div></header>
<main class="container entry-container admin-container">
<div id="v2-settings-tabs" role="tablist" aria-label="Ayarlar bölümleri"><button type="button" role="tab" aria-selected="true" aria-controls="v2-panel-yerler" id="v2-tab-yerler">Bulunduğu yerler</button></div>
<div id="v2-panel-yerler" class="v2-settings-panel" role="tabpanel" aria-labelledby="v2-tab-yerler">
<?php if($message):?><div class="success" role="status"><?=e($message)?></div><?php endif?>
<?php if($error):?><div class="alert" role="alert"><?=e($error)?></div><?php endif?>
<form class="panel location-add" method="post" action="<?=url('admin.php#yerler')?>">
<input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="location_add">
<input name="location_name" placeholder="Yeni yer adı" aria-label="Yeni yer adı" maxlength="255" required value="<?=e($error ? $name ?? '' : '')?>">
<button type="submit" class="primary" aria-label="Yer ekle" title="Yer ekle">Ekle</button>
</form>
<section class="panel location-list" aria-label="Bulunduğu yer tanımları">
<?php foreach($locations as $location):?><div><span><?=e($location['name'])?></span></div><?php endforeach?>
</section></div></main></body></html>
