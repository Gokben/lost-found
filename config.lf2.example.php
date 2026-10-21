<?php
declare(strict_types=1);

const APP_NAME = 'KRP Lost & Found';
$configuredBasePath = getenv('APP_BASE_PATH');
$host = $_SERVER['HTTP_HOST'] ?? '';
$isLocalHost = str_starts_with($host, '127.0.0.1') || str_starts_with($host, 'localhost');
define('BASE_PATH', $configuredBasePath !== false ? $configuredBasePath : ($isLocalHost ? '' : '/lf2'));

const DB_HOST = 'localhost';
const DB_NAME = 'MEVCUT_LF_VERITABANI';
const DB_USER = 'MEVCUT_LF_KULLANICISI';
const DB_PASS = 'SUNUCUDA_GIRILECEK';
ini_set('session.use_strict_mode', '1');
if (getenv('APP_ENV') === 'local') {
    if (!is_dir(__DIR__ . '/storage')) mkdir(__DIR__ . '/storage', 0775, true);
    session_save_path(__DIR__ . '/storage');
}
session_name('krp_lostfound_v2');
session_start();

function db(): PDO
{
    static $pdo;
    if (!$pdo) {
        if (getenv('APP_ENV') === 'local') {
            $pdo = new PDO('sqlite:' . __DIR__ . '/storage/local.sqlite');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } else {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
        }
        ensure_category_schema($pdo);
        ensure_transfer_schema($pdo);
    }
    return $pdo;
}

function ensure_transfer_schema(PDO $pdo): void
{
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $pdo->exec('CREATE TABLE IF NOT EXISTS storage_transfers (id INTEGER PRIMARY KEY AUTOINCREMENT,item_id INTEGER NOT NULL,from_storage TEXT NOT NULL,to_storage TEXT NOT NULL,notes TEXT DEFAULT \'\',transferred_by TEXT NOT NULL,transferred_at TEXT NOT NULL)');
    } else {
        $pdo->exec('CREATE TABLE IF NOT EXISTS storage_transfers (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,item_id INT UNSIGNED NOT NULL,from_storage VARCHAR(255) NOT NULL,to_storage VARCHAR(255) NOT NULL,notes TEXT NULL,transferred_by VARCHAR(255) NOT NULL,transferred_at DATETIME NOT NULL,KEY storage_transfers_item_id (item_id),KEY storage_transfers_transferred_at (transferred_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }
}

function ensure_category_schema(PDO $pdo): void
{
    static $ready = false;
    if ($ready) return;
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $pdo->exec('CREATE TABLE IF NOT EXISTS category_definitions (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL COLLATE NOCASE UNIQUE, active INTEGER NOT NULL DEFAULT 1, sort_order INTEGER NOT NULL DEFAULT 0)');
        $itemColumns = array_column($pdo->query('PRAGMA table_info(items)')->fetchAll(), 'name');
        $definitionColumns = array_column($pdo->query('PRAGMA table_info(item_definitions)')->fetchAll(), 'name');
    } else {
        $pdo->exec('CREATE TABLE IF NOT EXISTS category_definitions (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, active TINYINT(1) NOT NULL DEFAULT 1, sort_order INT NOT NULL DEFAULT 0, UNIQUE KEY category_definitions_name_unique (name)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $itemColumns = array_column($pdo->query('DESCRIBE items')->fetchAll(), 'Field');
        $definitionColumns = array_column($pdo->query('DESCRIBE item_definitions')->fetchAll(), 'Field');
    }
    if (!in_array('category_id', $itemColumns, true)) $pdo->exec('ALTER TABLE items ADD COLUMN category_id INTEGER NULL');
    if (!in_array('updated_at', $itemColumns, true)) $pdo->exec('ALTER TABLE items ADD COLUMN updated_at ' . ($driver === 'sqlite' ? 'TEXT NULL' : 'DATETIME NULL'));
    if (!in_array('updated_by', $itemColumns, true)) $pdo->exec('ALTER TABLE items ADD COLUMN updated_by ' . ($driver === 'sqlite' ? 'TEXT NULL' : 'VARCHAR(255) NULL'));
    if (!in_array('category_id', $definitionColumns, true)) $pdo->exec('ALTER TABLE item_definitions ADD COLUMN category_id INTEGER NULL');
    $pdo->exec('UPDATE items SET updated_at=created_at WHERE updated_at IS NULL');

    $pdo->beginTransaction();
    try {
        $categoryNames = $pdo->query("SELECT category AS name FROM items WHERE TRIM(category)<>'' UNION SELECT category AS name FROM item_definitions WHERE TRIM(category)<>'' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
        $insert = $pdo->prepare('INSERT INTO category_definitions (name,sort_order) VALUES (?,?)');
        $find = $pdo->prepare('SELECT id FROM category_definitions WHERE LOWER(name)=LOWER(?) LIMIT 1');
        foreach ($categoryNames as $index => $name) {
            $find->execute([$name]);
            if (!$find->fetchColumn()) {
                $insert->execute([$name, $index + 1]);
            }
        }
        $categoryRows = $pdo->query('SELECT id,name FROM category_definitions')->fetchAll();
        $updateItems = $pdo->prepare('UPDATE items SET category_id=? WHERE category_id IS NULL AND LOWER(category)=LOWER(?)');
        $updateDefinitions = $pdo->prepare('UPDATE item_definitions SET category_id=? WHERE category_id IS NULL AND LOWER(category)=LOWER(?)');
        foreach ($categoryRows as $category) {
            $updateItems->execute([(int)$category['id'], $category['name']]);
            $updateDefinitions->execute([(int)$category['id'], $category['name']]);
        }
        $pdo->commit();
        $ready = true;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}

function url(string $path = ''): string { return BASE_PATH . ($path ? '/' . ltrim($path, '/') : ''); }
function ensure_transfer_batch(PDO $pdo): void {
    ensure_transfer_schema($pdo);
    $sqlite=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
    $columns=array_column($pdo->query($sqlite?'PRAGMA table_info(storage_transfers)':'DESCRIBE storage_transfers')->fetchAll(),$sqlite?'name':'Field');
    if(!in_array('batch_id',$columns,true))$pdo->exec('ALTER TABLE storage_transfers ADD COLUMN batch_id VARCHAR(64) NULL');
}
function e(?string $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function date_display(?string $value): string {
    $value = trim((string) $value);
    if ($value === '') return '';
    try { return (new DateTimeImmutable($value, new DateTimeZone('Europe/Istanbul')))->format('d.m.Y'); }
    catch (Throwable) { return ''; }
}
function date_input_to_storage(?string $value): ?string {
    $value = trim((string) $value);
    if ($value === '') return null;
    foreach (['!d.m.Y', '!Y-m-d', '!Y-m-d\\TH:i'] as $format) {
        $date = DateTimeImmutable::createFromFormat($format, $value, new DateTimeZone('Europe/Istanbul'));
        $errors = DateTimeImmutable::getLastErrors();
        if ($date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) return $date->format('Y-m-d');
    }
    return null;
}
function redirect(string $path): never { header('Location: ' . url($path)); exit; }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function verify_csrf(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(419); exit('Oturum doğrulanamadı.'); }
}
function require_login(): void { if (empty($_SESSION['user'])) redirect('login.php'); }
function is_admin(): bool { return ($_SESSION['user']['role'] ?? '') === 'Admin'; }
function is_read_only(): bool { return ($_SESSION['user']['role'] ?? '') === 'Viewer'; }
function can_manage_records(): bool { return !is_read_only(); }
function require_record_manager(): void { require_login(); if (!can_manage_records()) { http_response_code(403); exit('Bu kullanıcı yalnızca kayıtları görüntüleyebilir.'); } }
function require_admin(): void { require_login(); if (!is_admin()) { http_response_code(403); exit('Bu sayfaya erişim yetkiniz yok.'); } }
