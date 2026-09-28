<?php
if (!function_exists('ensure_transfer_schema')) {
function ensure_transfer_schema(PDO $pdo): void
{
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $pdo->exec('CREATE TABLE IF NOT EXISTS storage_transfers (id INTEGER PRIMARY KEY AUTOINCREMENT,item_id INTEGER NOT NULL,from_storage TEXT NOT NULL,to_storage TEXT NOT NULL,notes TEXT DEFAULT \'\',transferred_by TEXT NOT NULL,transferred_at TEXT NOT NULL)');
    } else {
        $pdo->exec('CREATE TABLE IF NOT EXISTS storage_transfers (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,item_id INT UNSIGNED NOT NULL,from_storage VARCHAR(255) NOT NULL,to_storage VARCHAR(255) NOT NULL,notes TEXT NULL,transferred_by VARCHAR(255) NOT NULL,transferred_at DATETIME NOT NULL,KEY storage_transfers_item_id (item_id),KEY storage_transfers_transferred_at (transferred_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }
}

}
if (!function_exists('ensure_transfer_batch')) {
function ensure_transfer_batch(PDO $pdo): void {
    ensure_transfer_schema($pdo);
    $sqlite=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
    $columns=array_column($pdo->query($sqlite?'PRAGMA table_info(storage_transfers)':'DESCRIBE storage_transfers')->fetchAll(),$sqlite?'name':'Field');
    if(!in_array('batch_id',$columns,true))$pdo->exec('ALTER TABLE storage_transfers ADD COLUMN batch_id VARCHAR(64) NULL');
}
}
