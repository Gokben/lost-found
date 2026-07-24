-- Canlı MySQL veritabanında bir kez çalıştırın.
-- "Sadece görüntüleme" yetkisini desteklemek için role sütununa Viewer değerini ekler.
ALTER TABLE users
  MODIFY role ENUM('Admin','User','Viewer') NOT NULL DEFAULT 'User';

-- Önceki denemelerde boş değere dönüşmüş roller varsa geri yükleyin.
UPDATE users
  SET role = 'User'
  WHERE role = '' OR role IS NULL;
