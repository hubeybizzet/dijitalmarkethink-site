# Aksar Ege — Tek Sayfa Satış Sitesi + Sipariş Paneli

Akhisar'dan doğal zeytin satışı için hazırlanmış, dönüşüm odaklı tek sayfalık site
ve sipariş/ürün yönetim paneli. Saf PHP + SQLite ile çalışır; ek kurulum, Composer
veya Node gerektirmez. Türkiye'deki standart cPanel/Plesk hostinglerin hepsinde çalışır.

## Gereksinimler
- PHP 8.1 veya üstü (pdo_sqlite ve gd eklentileri — hostinglerde varsayılan olarak açıktır)
- Apache (önerilir, `.htaccess` dosyaları hazır) veya Nginx (aşağıdaki nota bakın)

## Kurulum (5 dakika)
1. `aksarege` klasörünün **içindeki** tüm dosyaları hostinginizin `public_html` klasörüne yükleyin
   (gizli dosyalar `.htaccess` dahil).
2. `data` ve `uploads` klasörlerinin yazılabilir olduğundan emin olun (genelde 755 yeterlidir).
3. Tarayıcıdan **hemen** `https://alanadiniz.com/panel/` adresine girin ve panel şifrenizi belirleyin.
   (Şifre ilk girişte belirlenir — siteyi yükledikten sonra bu adımı geciktirmeyin.)
4. **Ürünler & Fiyatlar** sayfasından fiyatları girip **Fiyatları kaydet**'e basın.
   Fiyatı girilmemiş ürünler sitede "Fiyat için sorun" + WhatsApp butonuyla görünür.
5. **Ayarlar** sayfasından:
   - Havale/EFT için banka adı ve IBAN
   - Yeni sipariş bildirim e-postası
   - Meta Pixel ID ve Google Analytics 4 ID
   - Site adresi (SEO için, örn. `https://aksarege.com`)
6. SSL kurulduysa `.htaccess` içindeki HTTPS yönlendirme satırlarının başındaki `#` işaretlerini kaldırın.
7. Google Search Console'a `https://alanadiniz.com/sitemap.xml` adresini ekleyin.

## Panel özellikleri (`/panel/`)
- **Özet:** bekleyen siparişler, günlük sipariş sayısı, aylık ciro
- **Siparişler:** duruma göre filtreleme (Yeni, Onaylandı, Hazırlanıyor, Kargoda, Teslim Edildi, İptal),
  arama, tek tıkla müşteriyi arama / WhatsApp'tan yazma, kargo takip numarasını WhatsApp'tan gönderme,
  yazdırma, Excel (CSV) dışa aktarma
- **Ürünler & Fiyatlar:** tüm fiyatları tek ekrandan girme, eski (üstü çizili) fiyat, stok ve
  sitede gösterme anahtarları, ürün ekleme / düzenleme / görsel yükleme
- **Yorumlar:** müşterilerden gelen gerçek yorumları ekleyin; ilk yorumla birlikte sitede yorum bölümü açılır
- **Ayarlar:** iletişim bilgileri, ödeme yöntemleri, kapıda ödeme bedeli, havale indirimi, kargo ücreti,
  ücretsiz kargo limiti, minimum sipariş, hero görseli, Pixel/GA4, SEO başlık/açıklama, yasal metinler, şifre

## Sitedeki dönüşüm özellikleri
- Telefon numarasına tıklayınca **Şimdi Ara** / **WhatsApp ile İletişime Geç** seçenekleri
- Kapıda nakit, kapıda kredi kartı, havale/EFT ödeme seçenekleri ve sipariş notu
- Sepet çekmecesi, sepete uçan ürün animasyonu, mobilde sabit alt bar (Ara · WhatsApp · Sipariş)
- Sipariş sonrası "Siparişimi WhatsApp'tan da ilet" butonu (sipariş özeti hazır mesajla)
- Meta Pixel olayları: PageView, ViewContent, AddToCart, InitiateCheckout, Contact, Purchase
- SEO: başlık/açıklama, Open Graph, canonical, Schema.org (Store, Product, Offer, FAQPage),
  otomatik `sitemap.xml` ve `robots.txt`, görsel boyutları, lazy-load

## Nginx kullanıyorsanız
`.htaccess` dosyaları Nginx'te çalışmaz. Sunucu ayarına şunları ekleyin:
```
location ~ ^/(data|inc)/ { deny all; }
location ~ ^/uploads/.*\.php$ { deny all; }
location = /sitemap.xml { rewrite ^ /sitemap.php last; }
location = /robots.txt  { rewrite ^ /robots.php last; }
```
(Veritabanı dosya adı ilk çalıştırmada rastgele üretildiği için ek bir güvenlik katmanı da vardır.)

## Yedekleme
Tüm siparişler, ürünler ve ayarlar `data/` klasöründeki tek bir `.sqlite` dosyasındadır.
Bu klasörü ve `uploads/` klasörünü düzenli olarak yedekleyin.

## Yerelde çalıştırma
```
cd aksarege
php -S localhost:8000
```
Ardından `http://localhost:8000` (site) ve `http://localhost:8000/panel/` (panel).
