<?php
declare(strict_types=1);

if (!function_exists('ensure_category_schema')) {
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
        if (!in_array('category_id', $definitionColumns, true)) $pdo->exec('ALTER TABLE item_definitions ADD COLUMN category_id INTEGER NULL');

        $pdo->beginTransaction();
        try {
            $categoryNames = $pdo->query("SELECT category AS name FROM items WHERE TRIM(category)<>'' UNION SELECT category AS name FROM item_definitions WHERE TRIM(category)<>'' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
            $insert = $pdo->prepare('INSERT INTO category_definitions (name,sort_order) VALUES (?,?)');
            $find = $pdo->prepare('SELECT id FROM category_definitions WHERE LOWER(name)=LOWER(?) LIMIT 1');
            foreach ($categoryNames as $index => $name) {
                $find->execute([$name]);
                if (!$find->fetchColumn()) $insert->execute([$name, $index + 1]);
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
}
