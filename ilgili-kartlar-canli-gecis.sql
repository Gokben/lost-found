-- İlgili kartlar için ana kart bağlantısı.
ALTER TABLE items ADD COLUMN parent_item_id INT NULL DEFAULT NULL;
CREATE INDEX items_parent_item_id_idx ON items (parent_item_id);
