-- Üç depoyu HK Depo altında birleştirir; mevcut başka depolara dokunmaz.
START TRANSACTION;

INSERT IGNORE INTO storage_definitions (name, active, sort_order)
VALUES ('HK Depo', 1, 999);

UPDATE items
SET storage_location = 'HK Depo'
WHERE storage_location IN ('Nurcan Hanım Kasa', 'Nurcan Hanım Ofis', 'HK Buzdolabı');

DELETE FROM storage_definitions
WHERE name IN ('Nurcan Hanım Kasa', 'Nurcan Hanım Ofis', 'HK Buzdolabı');

COMMIT;
