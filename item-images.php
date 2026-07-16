<?php
declare(strict_types=1);

function ensure_item_images_schema(PDO $pdo): void
{
    static $ready = false;
    if ($ready) return;
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $pdo->exec('CREATE TABLE IF NOT EXISTS item_images (id INTEGER PRIMARY KEY AUTOINCREMENT, item_id INTEGER NOT NULL, image_path TEXT NOT NULL, sort_order INTEGER NOT NULL DEFAULT 0, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
    } else {
        $pdo->exec('CREATE TABLE IF NOT EXISTS item_images (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, item_id INT NOT NULL, image_path VARCHAR(500) NOT NULL, sort_order INT NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY item_images_item_id_idx (item_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }
    $ready = true;
}

function item_images_for_items(array $itemIds): array
{
    if (!$itemIds) return [];
    $pdo = db();
    ensure_item_images_schema($pdo);
    $itemIds = array_values(array_unique(array_map('intval', $itemIds)));
    $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
    $stmt = $pdo->prepare("SELECT id,item_id,image_path,sort_order FROM item_images WHERE item_id IN ($placeholders) ORDER BY item_id,sort_order,id");
    $stmt->execute($itemIds);
    $images = [];
    foreach ($stmt->fetchAll() as $row) $images[(int)$row['item_id']][] = $row;
    return $images;
}

function item_images_for_item(int $itemId): array
{
    return item_images_for_items([$itemId])[$itemId] ?? [];
}

function delete_item_images(int $itemId, array $imageIds): void
{
    $imageIds = array_values(array_unique(array_filter(array_map('intval', $imageIds))));
    if (!$imageIds) return;
    $pdo = db();
    ensure_item_images_schema($pdo);
    $placeholders = implode(',', array_fill(0, count($imageIds), '?'));
    $stmt = $pdo->prepare("SELECT id,image_path FROM item_images WHERE item_id=? AND id IN ($placeholders)");
    $stmt->execute(array_merge([$itemId], $imageIds));
    $rows = $stmt->fetchAll();
    $delete = $pdo->prepare('DELETE FROM item_images WHERE id=? AND item_id=?');
    foreach ($rows as $row) {
        $path = (string)$row['image_path'];
        if (str_starts_with($path, 'assets/uploads/items/')) {
            $file = __DIR__ . '/' . $path;
            if (is_file($file)) @unlink($file);
        }
        $delete->execute([(int)$row['id'], $itemId]);
    }
}

function upload_item_images(int $itemId, array $files): ?string
{
    if (empty($files['name']) || !is_array($files['name'])) return null;
    $pdo = db();
    ensure_item_images_schema($pdo);
    $selected = [];
    foreach ($files['name'] as $index => $name) {
        $error = (int)($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) continue;
        if ($error !== UPLOAD_ERR_OK) return 'Görsellerden biri yüklenemedi.';
        $size = (int)($files['size'][$index] ?? 0);
        if ($size < 1 || $size > 5 * 1024 * 1024) return 'Her görsel en fazla 5 MB olabilir.';
        $tmp = (string)($files['tmp_name'][$index] ?? '');
        $imageInfo = @getimagesize($tmp);
        $mime = is_array($imageInfo) ? ($imageInfo['mime'] ?? '') : '';
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if (!isset($extensions[$mime])) return 'Yalnızca JPG, PNG, WEBP veya GIF görsel yükleyebilirsiniz.';
        $selected[] = ['tmp' => $tmp, 'extension' => $extensions[$mime]];
    }
    if (!$selected) return null;
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM item_images WHERE item_id=?');
    $countStmt->execute([$itemId]);
    $currentCount = (int)$countStmt->fetchColumn();
    if ($currentCount + count($selected) > 4) return 'Her eşya kartına en fazla 4 görsel ekleyebilirsiniz.';
    $dir = __DIR__ . '/assets/uploads/items';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) return 'Görseller için klasör oluşturulamadı.';
    $positionStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0) FROM item_images WHERE item_id=?');
    $positionStmt->execute([$itemId]);
    $position = (int)$positionStmt->fetchColumn();
    $insert = $pdo->prepare('INSERT INTO item_images (item_id,image_path,sort_order) VALUES (?,?,?)');
    foreach ($selected as $file) {
        $name = 'item-' . $itemId . '-' . bin2hex(random_bytes(10)) . '.' . $file['extension'];
        if (!move_uploaded_file($file['tmp'], $dir . '/' . $name)) return 'Görsel kaydedilemedi.';
        $insert->execute([$itemId, 'assets/uploads/items/' . $name, ++$position]);
    }
    return null;
}
