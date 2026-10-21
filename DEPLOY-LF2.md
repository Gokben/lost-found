# Lost & Found V2 — /lf2 yayını

Bu dalın cPanel dağıtım hedefi `$HOME/public_html/lf2/` dizinidir.

- `/lf` mevcut uygulaması ile aynı MySQL veritabanı kullanılır.
- Sunucuda `config.lf2.example.php` örneğinden `lf2/config.php` oluşturun.
- DB_HOST, DB_NAME, DB_USER ve DB_PASS değerlerini mevcut `lf/config.php` dosyasından sunucuda kopyalayın. Bu dosyayı GitHub'a yüklemeyin.
- BASE_PATH canlıda `/lf2` olmalıdır. V2'nin oturum çerezi `krp_lostfound_v2` olarak ayrılmıştır.
- Fotoğraflar ve profil görselleri için `lf2/assets/uploads`, mevcut `lf/assets/uploads` dizinine sembolik bağlantı olmalıdır. Böylece iki sürüm aynı dosyaları kullanır. Bağlantı kurulmadan dosya yüklemeyi kullanmayın.
- Canlı veritabanında `database.sql` veya `local-init.php` çalıştırmayın.
- `.cpanel.yml` yalnızca uygulama kaynaklarını ve assets dizinini kopyalar. `config.php`, yerel veritabanı, yedekler ve SQL dökümü dağıtılmaz.

İlk yayında cPanel Dosya Yöneticisi veya Terminal ile yapılandırmayı ve ortak uploads bağlantısını tamamlayın. Sonrasında /lf2/login.php üzerinden giriş, liste, mevcut görseller ve yetkileri doğrulayın.