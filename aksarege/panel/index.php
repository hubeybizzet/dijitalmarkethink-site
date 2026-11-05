<?php
/**
 * Aksar Ege — Yönetim Paneli
 * Siparişler, ürünler & fiyatlar, yorumlar ve site ayarları.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name('aesess');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => $secure]);
session_start();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];

function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">'; }
function check_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.');
    }
}
function flash(?string $msg = null, string $type = 'ok'): ?array
{
    if ($msg !== null) { $_SESSION['flash'] = [$msg, $type]; return null; }
    $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f;
}
function go(string $q = ''): never { header('Location: ./' . ($q ? '?' . $q : '')); exit; }
/** Türkçe sayı girişini çözer: "1.250,50" → 1250.5 · "1250,5" → 1250.5 · "1.250" → 1250 · "12.5" → 12.5 */
function num($v): float
{
    $v = preg_replace('/[^\d,.]/', '', (string)$v);
    if ($v === '') { return 0.0; }
    if (str_contains($v, ',')) { $v = str_replace(['.', ','], ['', '.'], $v); }
    elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $v)) { $v = str_replace('.', '', $v); }
    return round((float)$v, 2);
}
/** Form alanı için binlik ayraçsız gösterim: 1250.5 → "1250,5" */
function fmt_input(float $n): string { return rtrim(rtrim(number_format($n, 2, ',', ''), '0'), ','); }

/** Görsel yükleme: GD ile yeniden kodlar (güvenli), en fazla 1400px, WebP olarak kaydeder. */
function save_upload(array $f, string $prefix): ?string
{
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) { return null; }
    if ($f['size'] > 12 * 1024 * 1024) { return null; }
    $info = @getimagesize($f['tmp_name']);
    if (!$info) { return null; }
    switch ($info[2]) {
        case IMAGETYPE_JPEG: $im = @imagecreatefromjpeg($f['tmp_name']); break;
        case IMAGETYPE_PNG:  $im = @imagecreatefrompng($f['tmp_name']); break;
        case IMAGETYPE_WEBP: $im = @imagecreatefromwebp($f['tmp_name']); break;
        default: return null;
    }
    if (!$im) { return null; }
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($f['tmp_name']);
        $o = $exif['Orientation'] ?? 1;
        if ($o == 3) { $im = imagerotate($im, 180, 0); } elseif ($o == 6) { $im = imagerotate($im, -90, 0); } elseif ($o == 8) { $im = imagerotate($im, 90, 0); }
    }
    $w = imagesx($im); $h = imagesy($im); $max = 1400;
    if (max($w, $h) > $max) {
        $r = $max / max($w, $h);
        $nw = (int)round($w * $r); $nh = (int)round($h * $r);
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false); imagesavealpha($dst, true);
        imagecopyresampled($dst, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($im); $im = $dst;
    }
    $name = $prefix . '-' . date('ymd') . '-' . bin2hex(random_bytes(4));
    if (function_exists('imagewebp')) {
        imagewebp($im, UPLOAD_DIR . "/$name.webp", 84); $file = "$name.webp";
    } else {
        imagejpeg($im, UPLOAD_DIR . "/$name.jpg", 86); $file = "$name.jpg";
    }
    imagedestroy($im);
    return 'uploads/' . $file;
}
function delete_upload(string $path): void
{
    if (str_starts_with($path, 'uploads/') && !str_contains($path, '..')) { @unlink(APP_ROOT . '/' . $path); }
}

$p = $_GET['p'] ?? 'dashboard';
$s = settings();
$needsSetup = setting('admin_password') === '';
$logged = !empty($_SESSION['admin']) && ($_SESSION['admin_hash'] ?? '') === substr(setting('admin_password'), -16);

/* ------------------------------------------------------------------ */
/* Kurulum / Giriş / Çıkış                                            */
/* ------------------------------------------------------------------ */
$authError = '';
if ($needsSetup && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'setup') {
    check_csrf();
    $p1 = (string)($_POST['password'] ?? ''); $p2 = (string)($_POST['password2'] ?? '');
    if (mb_strlen($p1) < 8) { $authError = 'Şifre en az 8 karakter olmalı.'; }
    elseif ($p1 !== $p2) { $authError = 'Şifreler eşleşmiyor.'; }
    else {
        $hash = password_hash($p1, PASSWORD_DEFAULT);
        save_setting('admin_password', $hash);
        session_regenerate_id(true);
        $_SESSION['admin'] = true; $_SESSION['admin_hash'] = substr($hash, -16);
        flash('Paneliniz hazır! Önce Ürünler sayfasından fiyatlarınızı girin.');
        go('p=products');
    }
}
if (!$needsSetup && !$logged && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'login') {
    check_csrf();
    $ip = client_ip();
    db()->prepare('DELETE FROM login_attempts WHERE created_at < ?')->execute([time() - 900]);
    $st = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ?'); $st->execute([$ip]);
    if ((int)$st->fetchColumn() >= 8) {
        $authError = 'Çok fazla hatalı deneme. 15 dakika sonra tekrar deneyin.';
    } elseif (password_verify((string)($_POST['password'] ?? ''), setting('admin_password'))) {
        db()->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
        session_regenerate_id(true);
        $_SESSION['admin'] = true; $_SESSION['admin_hash'] = substr(setting('admin_password'), -16);
        go();
    } else {
        db()->prepare('INSERT INTO login_attempts(ip, created_at) VALUES(?, ?)')->execute([$ip, time()]);
        usleep(600000);
        $authError = 'Şifre hatalı.';
    }
}
if ($p === 'logout') { $_SESSION = []; session_destroy(); go(); }

if ($needsSetup || !$logged) {
    ?><!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
    <title>Panel Girişi · Aksar Ege</title><link rel="icon" href="../assets/img/brand/favicon-64.png"><link rel="stylesheet" href="panel.css"></head>
    <body class="auth"><form method="post" class="auth__box">
      <img src="../assets/img/brand/logo-256.webp" alt="Aksar Ege" width="96" height="96">
      <h1><?= $needsSetup ? 'Panel Kurulumu' : 'Yönetim Paneli' ?></h1>
      <p><?= $needsSetup ? 'Panelinize erişmek için bir şifre belirleyin. Bu şifreyi kimseyle paylaşmayın.' : 'Siparişlerinizi görmek için giriş yapın.' ?></p>
      <?php if ($authError): ?><div class="alert alert--err"><?= e($authError) ?></div><?php endif; ?>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $needsSetup ? 'setup' : 'login' ?>">
      <label>Şifre<input type="password" name="password" required minlength="<?= $needsSetup ? 8 : 1 ?>" autocomplete="<?= $needsSetup ? 'new-password' : 'current-password' ?>" autofocus></label>
      <?php if ($needsSetup): ?><label>Şifre (tekrar)<input type="password" name="password2" required minlength="8" autocomplete="new-password"></label><?php endif; ?>
      <button class="btn btn--primary btn--block"><?= $needsSetup ? 'Şifreyi Kaydet ve Başla' : 'Giriş Yap' ?></button>
      <a href="../" class="auth__back">← Siteye dön</a>
    </form></body></html><?php
    exit;
}

/* ------------------------------------------------------------------ */
/* İşlemler (POST)                                                     */
/* ------------------------------------------------------------------ */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    check_csrf();
    $a = $_POST['action'] ?? '';
    $pdo = db();

    if ($a === 'order_update') {
        $id = (int)$_POST['id'];
        $status = array_key_exists($_POST['status'] ?? '', order_statuses()) ? $_POST['status'] : 'yeni';
        $pdo->prepare('UPDATE orders SET status=?, tracking_no=?, admin_note=? WHERE id=?')
            ->execute([$status, mb_substr(trim((string)$_POST['tracking_no']), 0, 80), mb_substr(trim((string)$_POST['admin_note']), 0, 2000), $id]);
        flash('Sipariş güncellendi.'); go("p=order&id=$id");
    }
    if ($a === 'order_status_quick') {
        $id = (int)$_POST['id'];
        if (array_key_exists($_POST['status'] ?? '', order_statuses())) {
            $pdo->prepare('UPDATE orders SET status=? WHERE id=?')->execute([$_POST['status'], $id]);
            flash('Sipariş durumu güncellendi.');
        }
        go('p=orders' . (!empty($_POST['back']) ? '&' . preg_replace('/[^a-z0-9=&_%-]/i', '', (string)$_POST['back']) : ''));
    }
    if ($a === 'order_delete') {
        $pdo->prepare('DELETE FROM orders WHERE id=?')->execute([(int)$_POST['id']]);
        flash('Sipariş silindi.'); go('p=orders');
    }

    if ($a === 'prices_bulk') {
        $st = $pdo->prepare('UPDATE products SET price=?, old_price=?, active=?, in_stock=?, updated_at=? WHERE id=?');
        foreach ((array)($_POST['price'] ?? []) as $id => $price) {
            $st->execute([max(0, num($price)), max(0, num($_POST['old_price'][$id] ?? 0)), isset($_POST['active'][$id]) ? 1 : 0, isset($_POST['in_stock'][$id]) ? 1 : 0, date('c'), (int)$id]);
        }
        flash('Fiyatlar ve durumlar kaydedildi.'); go('p=products');
    }

    if ($a === 'product_save') {
        $id = (int)($_POST['id'] ?? 0);
        $old = $id ? product($id) : null;
        $images = $old['images'] ?? [];
        // Görsel sırası / silme
        $keep = array_values(array_filter((array)($_POST['images'] ?? []), fn($x) => in_array($x, $images, true)));
        foreach (array_diff($images, $keep) as $gone) { delete_upload($gone); }
        $images = $keep;
        $main = (string)($_POST['main_image'] ?? '');
        if ($main && in_array($main, $images, true)) { $images = array_values(array_unique(array_merge([$main], $images))); }
        if (!empty($_FILES['new_images']['name'][0])) {
            foreach ($_FILES['new_images']['name'] as $k => $_) {
                $file = ['name' => $_FILES['new_images']['name'][$k], 'tmp_name' => $_FILES['new_images']['tmp_name'][$k], 'error' => $_FILES['new_images']['error'][$k], 'size' => $_FILES['new_images']['size'][$k]];
                if ($path = save_upload($file, 'urun')) { $images[] = $path; }
            }
        }
        $name = trim((string)$_POST['name']) ?: 'Yeni Ürün';
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtr(mb_strtolower($name), ['ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u', 'â' => 'a', 'î' => 'i'])), '-');
        $features = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string)$_POST['features']))));
        $data = [
            ':name' => $name, ':subtitle' => trim((string)$_POST['subtitle']), ':short_desc' => trim((string)$_POST['short_desc']),
            ':description' => trim((string)$_POST['description']), ':features' => json_encode($features, JSON_UNESCAPED_UNICODE),
            ':pairing' => trim((string)$_POST['pairing']), ':price' => max(0, num($_POST['price'])), ':old_price' => max(0, num($_POST['old_price'])),
            ':unit' => trim((string)$_POST['unit']), ':badge' => trim((string)$_POST['badge']),
            ':images' => json_encode(array_values($images), JSON_UNESCAPED_SLASHES),
            ':active' => isset($_POST['active']) ? 1 : 0, ':in_stock' => isset($_POST['in_stock']) ? 1 : 0,
            ':sort' => (int)$_POST['sort'], ':t' => date('c'),
        ];
        if ($id) {
            $data[':id'] = $id;
            $pdo->prepare('UPDATE products SET name=:name, subtitle=:subtitle, short_desc=:short_desc, description=:description, features=:features, pairing=:pairing, price=:price, old_price=:old_price, unit=:unit, badge=:badge, images=:images, active=:active, in_stock=:in_stock, sort=:sort, updated_at=:t WHERE id=:id')->execute($data);
        } else {
            $exists = $pdo->prepare('SELECT COUNT(*) FROM products WHERE slug=?'); $exists->execute([$slug]);
            $data[':slug'] = $exists->fetchColumn() ? $slug . '-' . substr(bin2hex(random_bytes(2)), 0, 4) : $slug;
            $pdo->prepare('INSERT INTO products(slug,name,subtitle,short_desc,description,features,pairing,price,old_price,unit,badge,images,active,in_stock,sort,updated_at) VALUES(:slug,:name,:subtitle,:short_desc,:description,:features,:pairing,:price,:old_price,:unit,:badge,:images,:active,:in_stock,:sort,:t)')->execute($data);
            $id = (int)$pdo->lastInsertId();
        }
        flash('Ürün kaydedildi.'); go("p=product&id=$id");
    }
    if ($a === 'product_delete') {
        $pr = product((int)$_POST['id']);
        if ($pr) { foreach ($pr['images'] as $im) { delete_upload($im); } }
        $pdo->prepare('DELETE FROM products WHERE id=?')->execute([(int)$_POST['id']]);
        flash('Ürün silindi.'); go('p=products');
    }

    if ($a === 'review_save') {
        $id = (int)($_POST['id'] ?? 0);
        $vals = [mb_substr(trim((string)$_POST['name']), 0, 60), mb_substr(trim((string)$_POST['city']), 0, 40), mb_substr(trim((string)$_POST['text']), 0, 600), max(1, min(5, (int)$_POST['rating'])), isset($_POST['active']) ? 1 : 0];
        if ($vals[0] === '' || $vals[2] === '') { flash('İsim ve yorum zorunludur.', 'err'); go('p=reviews'); }
        if ($id) { $vals[] = $id; $pdo->prepare('UPDATE reviews SET name=?, city=?, text=?, rating=?, active=? WHERE id=?')->execute($vals); }
        else { $vals[] = date('c'); $pdo->prepare('INSERT INTO reviews(name,city,text,rating,active,created_at) VALUES(?,?,?,?,?,?)')->execute($vals); }
        flash('Yorum kaydedildi.'); go('p=reviews');
    }
    if ($a === 'review_delete') {
        $pdo->prepare('DELETE FROM reviews WHERE id=?')->execute([(int)$_POST['id']]);
        flash('Yorum silindi.'); go('p=reviews');
    }

    if ($a === 'settings_save') {
        $text = ['brand_name', 'company_name', 'slogan', 'phone', 'whatsapp', 'instagram', 'email', 'notify_email', 'address', 'announcement', 'bank_name', 'account_holder', 'iban', 'ga4_id', 'site_url', 'seo_title', 'seo_description', 'distance_contract', 'kvkk_text', 'return_policy'];
        $_POST['ga4_id'] = preg_replace('/[^A-Z0-9-]/', '', strtoupper((string)($_POST['ga4_id'] ?? '')));
        $_POST['iban'] = strtoupper(trim(preg_replace('/\s+/', ' ', (string)($_POST['iban'] ?? ''))));
        $_POST['whatsapp'] = preg_replace('/\D/', '', (string)($_POST['whatsapp'] ?? ''));
        $_POST['instagram'] = ltrim(trim((string)($_POST['instagram'] ?? '')), '@');
        foreach ($text as $k) { if (isset($_POST[$k])) { save_setting($k, trim((string)$_POST[$k])); } }
        foreach (['cod_fee', 'transfer_discount', 'shipping_fee', 'free_shipping_min', 'min_order'] as $k) { if (isset($_POST[$k])) { save_setting($k, (string)max(0, num($_POST[$k]))); } }
        foreach (['pay_cod_cash', 'pay_cod_card', 'pay_transfer'] as $k) { save_setting($k, isset($_POST[$k]) ? '1' : '0'); }
        if (!empty($_POST['hero_remove'])) { delete_upload(setting('hero_image')); save_setting('hero_image', ''); }
        if (!empty($_FILES['hero_image']['name'])) {
            if ($path = save_upload($_FILES['hero_image'], 'hero')) { delete_upload(setting('hero_image')); save_setting('hero_image', $path); }
            else { flash('Görsel yüklenemedi (JPG, PNG veya WebP, en fazla 12 MB).', 'err'); go('p=settings'); }
        }
        flash('Ayarlar kaydedildi.'); go('p=settings');
    }
    if ($a === 'meta_save') {
        save_setting('meta_pixel_id', preg_replace('/\D/', '', (string)($_POST['meta_pixel_id'] ?? '')));
        save_setting('meta_test_code', strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($_POST['meta_test_code'] ?? ''))));
        $ver = trim((string)($_POST['meta_api_version'] ?? ''));
        save_setting('meta_api_version', preg_match('/^v\d{2}\.\d$/', $ver) ? $ver : 'v26.0');
        $tok = trim((string)($_POST['meta_capi_token'] ?? ''));
        if (!empty($_POST['meta_token_remove'])) { save_setting('meta_capi_token', ''); }
        elseif ($tok !== '') { save_setting('meta_capi_token', preg_replace('/\s+/', '', $tok)); }
        flash('Meta ayarları kaydedildi.' . (capi_enabled() ? ' Bağlantıyı “Test olayı gönder” ile doğrulayın.' : ''));
        go('p=meta');
    }
    if ($a === 'meta_test') {
        if (!capi_enabled()) { flash('Önce Veri Seti (Pixel) ID ve erişim anahtarını girin.', 'err'); go('p=meta'); }
        $ev = meta_event('PageView', 'panel-test.' . bin2hex(random_bytes(6)), base_url() . '/', meta_user_data());
        [$ok, $code, $resp] = capi_post([$ev]);
        capi_log($ev, $ok ? 'ok (test)' : 'hata (test)', $code, $resp);
        $msg = $ok
            ? 'Bağlantı başarılı! Meta yanıtı: ' . $resp . (setting('meta_test_code') ? ' — Olay Yöneticisi › Test Olayları sekmesinde görünecektir.' : '')
            : 'Bağlantı başarısız (HTTP ' . $code . '): ' . $resp;
        flash($msg, $ok ? 'ok' : 'err'); go('p=meta');
    }
    if ($a === 'meta_flush') {
        $r = capi_flush(500, true);
        flash($r['sent'] . ' olay gönderildi, kuyrukta ' . $r['left'] . ' olay kaldı.', $r['left'] ? 'warn' : 'ok'); go('p=meta');
    }
    if ($a === 'password_change') {
        if (!password_verify((string)$_POST['current'], setting('admin_password'))) { flash('Mevcut şifre hatalı.', 'err'); go('p=settings'); }
        if (mb_strlen((string)$_POST['new']) < 8) { flash('Yeni şifre en az 8 karakter olmalı.', 'err'); go('p=settings'); }
        $hash = password_hash((string)$_POST['new'], PASSWORD_DEFAULT);
        save_setting('admin_password', $hash);
        $_SESSION['admin_hash'] = substr($hash, -16);
        flash('Şifreniz değiştirildi.'); go('p=settings');
    }
}

/* ------------------------------------------------------------------ */
/* CSV dışa aktarma                                                    */
/* ------------------------------------------------------------------ */
if ($p === 'export') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="aksarege-siparisler-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Sipariş No', 'Tarih', 'Durum', 'Ad Soyad', 'Telefon', 'E-posta', 'İl', 'İlçe', 'Adres', 'Ürünler', 'Ödeme', 'Ara Toplam', 'İndirim', 'Kargo', 'Kapıda Ödeme Bedeli', 'Toplam', 'Müşteri Notu', 'Kargo Takip', 'Yönetici Notu'], ';');
    $stat = order_statuses(); $pms = ['kapida_nakit' => 'Kapıda Nakit', 'kapida_kart' => 'Kapıda Kart', 'havale' => 'Havale/EFT'];
    foreach (db()->query('SELECT * FROM orders ORDER BY id DESC') as $o) {
        $items = implode(', ', array_map(fn($l) => $l['qty'] . 'x ' . $l['name'], json_decode($o['items'], true) ?: []));
        fputcsv($out, [$o['order_no'], date('d.m.Y H:i', strtotime($o['created_at'])), $stat[$o['status']][0] ?? $o['status'], $o['customer_name'], $o['phone'], $o['email'], $o['city'], $o['district'], $o['address'], $items, $pms[$o['payment_method']] ?? $o['payment_method'], fmt_num((float)$o['subtotal']), fmt_num((float)$o['discount']), fmt_num((float)$o['shipping']), fmt_num((float)$o['cod_fee']), fmt_num((float)$o['total']), $o['note'], $o['tracking_no'], $o['admin_note']], ';');
    }
    exit;
}

/* ------------------------------------------------------------------ */
/* Görünüm                                                             */
/* ------------------------------------------------------------------ */
$statuses = order_statuses();
$payLabels = ['kapida_nakit' => 'Kapıda Nakit', 'kapida_kart' => 'Kapıda Kredi Kartı', 'havale' => 'Havale / EFT'];
$newCount = (int)db()->query("SELECT COUNT(*) FROM orders WHERE status='yeni'")->fetchColumn();
$flash = flash();
function badge(string $status): string
{
    $st = order_statuses()[$status] ?? [$status, '#666'];
    return '<span class="badge" style="--c:' . $st[1] . '">' . e($st[0]) . '</span>';
}
function order_source_label(array $att, bool $compact = false): string
{
    $src = strtolower((string)($att['utm_source'] ?? ''));
    $meta = !empty($att['fbclid']) || preg_match('/facebook|instagram|^fb$|^ig$|meta/', $src);
    if ($meta) { return '<span class="badge badge--meta" style="--c:#1d4ed8">Meta reklam</span>'; }
    if ($src !== '') { return '<span class="badge" style="--c:#7c3aed">' . e($att['utm_source']) . '</span>'; }
    return $compact ? '' : '<small>Doğrudan / organik</small>';
}
function wa_link(string $phone, string $text): string { return 'https://wa.me/' . phone_digits($phone) . '?text=' . rawurlencode($text); }
$nav = ['dashboard' => ['Özet', 'M3 12 12 3l9 9M5 10v10h14V10'], 'orders' => ['Siparişler', 'M6 7h12l-1 13H7zM9 7V5a3 3 0 0 1 6 0v2'], 'products' => ['Ürünler & Fiyatlar', 'M7 3h10v4H7zM6 7h12v14H6zM6 12h12'], 'reviews' => ['Yorumlar', 'M4 5h16v11H9l-5 4z'], 'meta' => ['Meta Reklam', 'M3 17l6-6 4 4 8-8M15 7h6v6'], 'settings' => ['Ayarlar', 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19 12a7 7 0 0 0-.1-1.2l2-1.6-2-3.4-2.4 1a7 7 0 0 0-2-1.2L14 3h-4l-.5 2.6a7 7 0 0 0-2 1.2l-2.4-1-2 3.4 2 1.6a7 7 0 0 0 0 2.4l-2 1.6 2 3.4 2.4-1a7 7 0 0 0 2 1.2L10 21h4l.5-2.6a7 7 0 0 0 2-1.2l2.4 1 2-3.4-2-1.6c.1-.4.1-.8.1-1.2z']];
$active = in_array($p, ['order'], true) ? 'orders' : (in_array($p, ['product'], true) ? 'products' : $p);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title><?= e($nav[$active][0] ?? 'Panel') ?> · Aksar Ege Panel</title>
<link rel="icon" href="../assets/img/brand/favicon-64.png">
<link rel="stylesheet" href="panel.css?v=<?= filemtime(__DIR__ . '/panel.css') ?>">
</head>
<body>
<aside class="side">
  <a href="./" class="side__brand"><img src="../assets/img/brand/logo-256.webp" alt="" width="44" height="44"><span><strong>Aksar Ege</strong><small>Yönetim Paneli</small></span></a>
  <nav>
    <?php foreach ($nav as $key => [$label, $path]): ?>
    <a href="?p=<?= $key ?>" class="<?= $active === $key ? 'on' : '' ?>"><svg viewBox="0 0 24 24"><path d="<?= $path ?>"/></svg><?= $label ?><?php if ($key === 'orders' && $newCount): ?><b><?= $newCount ?></b><?php endif; ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="side__foot">
    <a href="../" target="_blank">↗ Siteyi görüntüle</a>
    <a href="?p=logout">Çıkış yap</a>
  </div>
</aside>
<main class="main">
<?php if ($flash): ?><div class="alert alert--<?= e($flash[1]) ?>"><?= e($flash[0]) ?></div><?php endif; ?>

<?php if ($p === 'dashboard'):
    $today = date('Y-m-d');
    $q = fn(string $sql, array $a = []) => (function () use ($sql, $a) { $st = db()->prepare($sql); $st->execute($a); return $st->fetchColumn(); })();
    $todayCount = (int)$q("SELECT COUNT(*) FROM orders WHERE substr(created_at,1,10)=?", [$today]);
    $monthRev = (float)$q("SELECT COALESCE(SUM(total),0) FROM orders WHERE status!='iptal' AND substr(created_at,1,7)=?", [date('Y-m')]);
    $allCount = (int)$q("SELECT COUNT(*) FROM orders");
    $allRev = (float)$q("SELECT COALESCE(SUM(total),0) FROM orders WHERE status!='iptal'");
    $noPrice = array_filter(products(false), fn($x) => $x['active'] && $x['price'] <= 0);
    $latest = db()->query('SELECT * FROM orders ORDER BY id DESC LIMIT 8')->fetchAll();
?>
  <header class="head"><h1>Hoş geldiniz 👋</h1><p><?= date('d.m.Y') ?> · Mağazanızın kısa özeti</p></header>
  <?php if ($noPrice): ?>
  <div class="alert alert--warn"><strong><?= count($noPrice) ?> üründe fiyat girilmemiş.</strong> Bu ürünler sitede "Fiyat için sorun" olarak görünür ve sepete eklenemez. <a href="?p=products">Fiyatları şimdi girin →</a></div>
  <?php endif; ?>
  <div class="stats">
    <div class="stat stat--hl"><small>Bekleyen (yeni) sipariş</small><strong><?= $newCount ?></strong><a href="?p=orders&status=yeni">Görüntüle →</a></div>
    <div class="stat"><small>Bugünkü siparişler</small><strong><?= $todayCount ?></strong></div>
    <div class="stat"><small>Bu ayki ciro</small><strong><?= money($monthRev) ?></strong></div>
    <div class="stat"><small>Toplam sipariş / ciro</small><strong><?= $allCount ?></strong><span><?= money($allRev) ?></span></div>
  </div>
  <section class="box">
    <div class="box__head"><h2>Son siparişler</h2><a href="?p=orders" class="btn btn--sm">Tümü</a></div>
    <?php if (!$latest): ?><p class="empty">Henüz sipariş yok. Siparişler geldikçe burada görünecek.</p><?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>No</th><th>Tarih</th><th>Müşteri</th><th>Tutar</th><th>Ödeme</th><th>Durum</th></tr></thead>
      <tbody><?php foreach ($latest as $o): ?>
        <tr onclick="location='?p=order&id=<?= $o['id'] ?>'"><td><a href="?p=order&id=<?= $o['id'] ?>"><?= e($o['order_no']) ?></a></td><td><?= date('d.m H:i', strtotime($o['created_at'])) ?></td><td><?= e($o['customer_name']) ?><small><?= e($o['city']) ?></small></td><td><strong><?= money((float)$o['total']) ?></strong></td><td><?= e($payLabels[$o['payment_method']] ?? '') ?></td><td><?= badge($o['status']) ?></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
  </section>

<?php elseif ($p === 'orders'):
    $status = $_GET['status'] ?? '';
    $search = trim((string)($_GET['q'] ?? ''));
    $page = max(1, (int)($_GET['page'] ?? 1)); $per = 30;
    $where = []; $args = [];
    if ($status && isset($statuses[$status])) { $where[] = 'status = ?'; $args[] = $status; }
    if ($search !== '') { $where[] = '(customer_name LIKE ? OR phone LIKE ? OR order_no LIKE ? OR city LIKE ?)'; array_push($args, "%$search%", "%$search%", "%$search%", "%$search%"); }
    $w = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $st = db()->prepare("SELECT COUNT(*) FROM orders$w"); $st->execute($args); $total = (int)$st->fetchColumn();
    $st = db()->prepare("SELECT * FROM orders$w ORDER BY id DESC LIMIT $per OFFSET " . (($page - 1) * $per)); $st->execute($args); $rows = $st->fetchAll();
    $counts = db()->query('SELECT status, COUNT(*) c FROM orders GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
    $back = http_build_query(array_filter(['status' => $status, 'q' => $search, 'page' => $page > 1 ? $page : null]));
?>
  <header class="head head--row"><div><h1>Siparişler</h1><p>Toplam <?= $total ?> sipariş</p></div><a class="btn" href="?p=export">⬇ Excel (CSV) indir</a></header>
  <div class="tabs">
    <a href="?p=orders" class="<?= !$status ? 'on' : '' ?>">Tümü <b><?= array_sum($counts) ?></b></a>
    <?php foreach ($statuses as $k => [$l, $c]): ?><a href="?p=orders&status=<?= $k ?>" class="<?= $status === $k ? 'on' : '' ?>" style="--c:<?= $c ?>"><?= $l ?> <b><?= (int)($counts[$k] ?? 0) ?></b></a><?php endforeach; ?>
  </div>
  <form class="search" method="get"><input type="hidden" name="p" value="orders"><?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?><input name="q" value="<?= e($search) ?>" placeholder="İsim, telefon, il veya sipariş no ile ara…"><button class="btn">Ara</button></form>
  <section class="box">
    <?php if (!$rows): ?><p class="empty">Bu filtrede sipariş bulunamadı.</p><?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>No</th><th>Tarih</th><th>Müşteri</th><th>Ürünler</th><th>Tutar</th><th>Ödeme</th><th>Durum</th><th></th></tr></thead>
      <tbody><?php foreach ($rows as $o): $items = json_decode($o['items'], true) ?: []; ?>
        <tr>
          <td><a href="?p=order&id=<?= $o['id'] ?>"><strong><?= e($o['order_no']) ?></strong></a></td>
          <td><?= date('d.m.Y', strtotime($o['created_at'])) ?><small><?= date('H:i', strtotime($o['created_at'])) ?></small></td>
          <td><?= e($o['customer_name']) ?><?= order_source_label(json_decode((string)($o['attribution'] ?? ''), true) ?: [], true) ?><small><?= e($o['phone']) ?> · <?= e($o['city']) ?></small></td>
          <td class="items"><?= e(implode(', ', array_map(fn($l) => $l['qty'] . '× ' . $l['name'], $items))) ?><?php if ($o['note']): ?><small class="note">📝 <?= e(mb_strimwidth($o['note'], 0, 60, '…')) ?></small><?php endif; ?></td>
          <td><strong><?= money((float)$o['total']) ?></strong></td>
          <td><?= e($payLabels[$o['payment_method']] ?? '') ?></td>
          <td><form method="post" class="quick"><?= csrf_field() ?><input type="hidden" name="action" value="order_status_quick"><input type="hidden" name="id" value="<?= $o['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
            <select name="status" onchange="this.form.submit()" style="--c:<?= $statuses[$o['status']][1] ?? '#666' ?>"><?php foreach ($statuses as $k => [$l]): ?><option value="<?= $k ?>"<?= $o['status'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></form></td>
          <td class="acts"><a class="ico ico--wa" target="_blank" rel="noopener" title="WhatsApp" href="<?= e(wa_link($o['phone'], "Merhaba {$o['customer_name']}, Aksar Ege'den yazıyoruz. {$o['order_no']} numaralı siparişiniz için teşekkür ederiz 🫒")) ?>">WA</a><a class="ico" title="Ara" href="tel:+<?= phone_digits($o['phone']) ?>">☎</a></td>
        </tr>
      <?php endforeach; ?></tbody>
    </table></div>
    <?php if ($total > $per): ?><div class="pager"><?php for ($i = 1; $i <= ceil($total / $per); $i++): ?><a class="<?= $i === $page ? 'on' : '' ?>" href="?<?= e(http_build_query(array_filter(['p' => 'orders', 'status' => $status, 'q' => $search, 'page' => $i]))) ?>"><?= $i ?></a><?php endfor; ?></div><?php endif; ?>
    <?php endif; ?>
  </section>

<?php elseif ($p === 'order'):
    $st = db()->prepare('SELECT * FROM orders WHERE id=?'); $st->execute([(int)($_GET['id'] ?? 0)]); $o = $st->fetch();
    if (!$o): ?><p class="empty">Sipariş bulunamadı. <a href="?p=orders">Siparişlere dön</a></p><?php else: $items = json_decode($o['items'], true) ?: []; ?>
  <header class="head head--row"><div><a href="?p=orders" class="back">← Siparişler</a><h1><?= e($o['order_no']) ?> <?= badge($o['status']) ?></h1><p><?= date('d.m.Y H:i', strtotime($o['created_at'])) ?></p></div>
    <div class="head__acts"><a class="btn btn--wa" target="_blank" rel="noopener" href="<?= e(wa_link($o['phone'], "Merhaba {$o['customer_name']}, Aksar Ege'den yazıyoruz. {$o['order_no']} numaralı siparişiniz (" . money((float)$o['total']) . ") için teşekkür ederiz 🫒 Siparişinizi onaylıyor musunuz?")) ?>">WhatsApp</a><a class="btn" href="tel:+<?= phone_digits($o['phone']) ?>">☎ Ara</a><button class="btn btn--ghost" onclick="print()">🖨 Yazdır</button></div></header>
  <div class="grid2">
    <section class="box">
      <h2>Müşteri & teslimat</h2>
      <dl class="dl">
        <dt>Ad Soyad</dt><dd><?= e($o['customer_name']) ?></dd>
        <dt>Telefon</dt><dd><a href="tel:+<?= phone_digits($o['phone']) ?>"><?= e($o['phone']) ?></a></dd>
        <?php if ($o['email']): ?><dt>E-posta</dt><dd><a href="mailto:<?= e($o['email']) ?>"><?= e($o['email']) ?></a></dd><?php endif; ?>
        <dt>Adres</dt><dd><?= e($o['address']) ?><br><strong><?= e($o['district']) ?> / <?= e($o['city']) ?></strong></dd>
        <dt>Ödeme</dt><dd><strong><?= e($payLabels[$o['payment_method']] ?? $o['payment_method']) ?></strong></dd>
        <?php $att = json_decode((string)($o['attribution'] ?? ''), true) ?: []; ?>
        <dt>Kaynak</dt><dd><?= order_source_label($att) ?><?php if (!empty($att['utm_campaign'])): ?><br><small>Kampanya: <?= e($att['utm_campaign']) ?><?= !empty($att['utm_content']) ? ' · Reklam: ' . e($att['utm_content']) : '' ?></small><?php endif; ?></dd>
      </dl>
      <?php if ($o['note']): ?><div class="cnote"><strong>📝 Müşteri notu</strong><p><?= nl2br(e($o['note'])) ?></p></div><?php endif; ?>
    </section>
    <section class="box">
      <h2>Ürünler</h2>
      <table class="table table--plain"><tbody>
        <?php foreach ($items as $l): ?><tr><td><?= (int)$l['qty'] ?> ×</td><td><?= e($l['name']) ?><small><?= money((float)$l['price']) ?> / adet</small></td><td class="r"><?= money((float)$l['total']) ?></td></tr><?php endforeach; ?>
      </tbody></table>
      <dl class="sum">
        <dt>Ara toplam</dt><dd><?= money((float)$o['subtotal']) ?></dd>
        <?php if ((float)$o['discount'] > 0): ?><dt>Havale indirimi</dt><dd>−<?= money((float)$o['discount']) ?></dd><?php endif; ?>
        <dt>Kargo</dt><dd><?= (float)$o['shipping'] > 0 ? money((float)$o['shipping']) : 'Ücretsiz' ?></dd>
        <?php if ((float)$o['cod_fee'] > 0): ?><dt>Kapıda ödeme bedeli</dt><dd><?= money((float)$o['cod_fee']) ?></dd><?php endif; ?>
        <dt class="t">Toplam</dt><dd class="t"><?= money((float)$o['total']) ?></dd>
      </dl>
    </section>
  </div>
  <section class="box no-print">
    <h2>Sipariş yönetimi</h2>
    <form method="post" class="form">
      <?= csrf_field() ?><input type="hidden" name="action" value="order_update"><input type="hidden" name="id" value="<?= $o['id'] ?>">
      <div class="row3">
        <label>Durum<select name="status"><?php foreach ($statuses as $k => [$l]): ?><option value="<?= $k ?>"<?= $o['status'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
        <label>Kargo takip no<input name="tracking_no" value="<?= e($o['tracking_no']) ?>" placeholder="Örn: 1234567890"></label>
      </div>
      <label>Yönetici notu (sadece siz görürsünüz)<textarea name="admin_note" rows="3"><?= e($o['admin_note']) ?></textarea></label>
      <div class="form__acts"><button class="btn btn--primary">Kaydet</button>
        <?php if ($o['tracking_no']): ?><a class="btn btn--wa" target="_blank" rel="noopener" href="<?= e(wa_link($o['phone'], "Merhaba {$o['customer_name']}, {$o['order_no']} numaralı Aksar Ege siparişiniz kargoya verildi 🚚 Kargo takip numaranız: {$o['tracking_no']}. Afiyet olsun! 🫒")) ?>">Takip no'yu WhatsApp'tan gönder</a><?php endif; ?>
      </div>
    </form>
    <form method="post" onsubmit="return confirm('Bu sipariş kalıcı olarak silinsin mi?')" class="danger-zone"><?= csrf_field() ?><input type="hidden" name="action" value="order_delete"><input type="hidden" name="id" value="<?= $o['id'] ?>"><button class="btn btn--danger btn--sm">Siparişi sil</button></form>
  </section>
  <?php endif; ?>

<?php elseif ($p === 'products'): $all = products(false); ?>
  <header class="head head--row"><div><h1>Ürünler & Fiyatlar</h1><p>Fiyatları buradan hızlıca girin. Fiyatı 0 olan ürün sitede "Fiyat için sorun" olarak görünür.</p></div><a class="btn btn--primary" href="?p=product">+ Yeni ürün</a></header>
  <section class="box">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="prices_bulk">
    <div class="table-wrap"><table class="table table--products">
      <thead><tr><th></th><th>Ürün</th><th>Satış fiyatı (₺)</th><th>Eski fiyat (₺) <small>üstü çizili</small></th><th>Sitede</th><th>Stokta</th><th></th></tr></thead>
      <tbody><?php foreach ($all as $pr): ?>
        <tr class="<?= $pr['active'] ? '' : 'off' ?>">
          <td><img src="../<?= e($pr['images'][0] ?? 'assets/img/brand/logo-256.webp') ?>" alt="" width="48" height="60"></td>
          <td><strong><?= e($pr['name']) ?></strong><small><?= e($pr['unit']) ?></small></td>
          <td><input class="money" name="price[<?= $pr['id'] ?>]" value="<?= $pr['price'] > 0 ? e(fmt_input($pr['price'])) : '' ?>" placeholder="0,00" inputmode="decimal"></td>
          <td><input class="money" name="old_price[<?= $pr['id'] ?>]" value="<?= $pr['old_price'] > 0 ? e(fmt_input($pr['old_price'])) : '' ?>" placeholder="—" inputmode="decimal"></td>
          <td><label class="sw"><input type="checkbox" name="active[<?= $pr['id'] ?>]"<?= $pr['active'] ? ' checked' : '' ?>><i></i></label></td>
          <td><label class="sw"><input type="checkbox" name="in_stock[<?= $pr['id'] ?>]"<?= $pr['in_stock'] ? ' checked' : '' ?>><i></i></label></td>
          <td><a class="btn btn--sm btn--ghost" href="?p=product&id=<?= $pr['id'] ?>">Düzenle</a></td>
        </tr>
      <?php endforeach; ?></tbody>
    </table></div>
    <div class="form__acts"><button class="btn btn--primary">Fiyatları kaydet</button></div>
    </form>
  </section>

<?php elseif ($p === 'product'):
    $pr = isset($_GET['id']) ? product((int)$_GET['id']) : null;
    $pr = $pr ?: ['id' => 0, 'name' => '', 'subtitle' => '', 'short_desc' => '', 'description' => '', 'features' => [], 'pairing' => '', 'price' => 0, 'old_price' => 0, 'unit' => 'Pet Kavanoz', 'badge' => '', 'images' => [], 'active' => 1, 'in_stock' => 1, 'sort' => 10];
?>
  <header class="head"><a href="?p=products" class="back">← Ürünler</a><h1><?= $pr['id'] ? e($pr['name']) : 'Yeni ürün' ?></h1></header>
  <form method="post" enctype="multipart/form-data" class="form grid2 grid2--wide">
    <?= csrf_field() ?><input type="hidden" name="action" value="product_save"><input type="hidden" name="id" value="<?= (int)$pr['id'] ?>">
    <section class="box">
      <h2>Ürün bilgileri</h2>
      <label>Ürün adı *<input name="name" value="<?= e($pr['name']) ?>" required></label>
      <div class="row3">
        <label>Alt başlık<input name="subtitle" value="<?= e($pr['subtitle']) ?>" placeholder="Crushed Green Olives"></label>
        <label>Rozet<input name="badge" value="<?= e($pr['badge']) ?>" placeholder="En Çok Tercih Edilen"></label>
      </div>
      <label>Kısa açıklama <small>(ürün kartında görünür)</small><textarea name="short_desc" rows="3"><?= e($pr['short_desc']) ?></textarea></label>
      <label>Detaylı açıklama <small>(ürün penceresinde görünür; paragrafları boş satırla ayırın)</small><textarea name="description" rows="7"><?= e($pr['description']) ?></textarea></label>
      <div class="row3">
        <label>Özellikler <small>(her satıra bir tane)</small><textarea name="features" rows="4"><?= e(implode("\n", $pr['features'])) ?></textarea></label>
        <label>Yanına yakışanlar<textarea name="pairing" rows="4"><?= e($pr['pairing']) ?></textarea></label>
      </div>
    </section>
    <div>
      <section class="box">
        <h2>Fiyat & durum</h2>
        <div class="row3">
          <label>Satış fiyatı (₺)<input class="money" name="price" value="<?= $pr['price'] > 0 ? e(fmt_input($pr['price'])) : '' ?>" placeholder="0,00" inputmode="decimal"></label>
          <label>Eski fiyat (₺)<input class="money" name="old_price" value="<?= $pr['old_price'] > 0 ? e(fmt_input($pr['old_price'])) : '' ?>" placeholder="İsteğe bağlı" inputmode="decimal"></label>
        </div>
        <div class="row3">
          <label>Birim / gramaj<input name="unit" value="<?= e($pr['unit']) ?>" placeholder="1 kg Pet Kavanoz"></label>
          <label>Sıra<input type="number" name="sort" value="<?= (int)$pr['sort'] ?>"></label>
        </div>
        <label class="check"><input type="checkbox" name="active"<?= $pr['active'] ? ' checked' : '' ?>> Sitede göster</label>
        <label class="check"><input type="checkbox" name="in_stock"<?= $pr['in_stock'] ? ' checked' : '' ?>> Stokta var</label>
      </section>
      <section class="box">
        <h2>Görseller</h2>
        <p class="hint">İlk görsel ana görseldir. Dikey (4:5) fotoğraflar en iyi sonucu verir.</p>
        <div class="imgs">
          <?php foreach ($pr['images'] as $k => $im): ?>
          <div class="img"><img src="../<?= e($im) ?>" alt="" width="120" height="150"><input type="hidden" name="images[]" value="<?= e($im) ?>">
            <label><input type="radio" name="main_image" value="<?= e($im) ?>"<?= $k === 0 ? ' checked' : '' ?>> Ana</label>
            <button type="button" onclick="this.closest('.img').remove()">Kaldır</button></div>
          <?php endforeach; ?>
        </div>
        <label>Yeni görsel ekle<input type="file" name="new_images[]" accept="image/jpeg,image/png,image/webp" multiple></label>
      </section>
      <div class="form__acts"><button class="btn btn--primary btn--lg">Ürünü kaydet</button></div>
    </div>
  </form>
  <?php if ($pr['id']): ?>
  <form method="post" class="danger-zone" onsubmit="return confirm('Bu ürün kalıcı olarak silinsin mi? (Gizlemek için “Sitede göster” işaretini kaldırmanız yeterli.)')"><?= csrf_field() ?><input type="hidden" name="action" value="product_delete"><input type="hidden" name="id" value="<?= (int)$pr['id'] ?>"><button class="btn btn--danger btn--sm">Ürünü sil</button></form>
  <?php endif; ?>

<?php elseif ($p === 'meta'):
    $s = settings(true);
    $queue = (int)db()->query('SELECT COUNT(*) FROM capi_queue')->fetchColumn();
    $stats = db()->prepare("SELECT event_name, SUM(status LIKE 'ok%') ok, SUM(status NOT LIKE 'ok%') bad FROM capi_log WHERE created_at > ? GROUP BY event_name ORDER BY ok DESC");
    $stats->execute([date('c', time() - 86400)]); $stats = $stats->fetchAll();
    $logs = db()->query('SELECT * FROM capi_log ORDER BY id DESC LIMIT 40')->fetchAll();
    $lastErr = db()->query("SELECT last_error FROM capi_queue ORDER BY id DESC LIMIT 1")->fetchColumn();
    $tok = $s['meta_capi_token'];
?>
  <header class="head"><h1>Meta Reklam — Pixel & Dönüşümler API'si</h1><p>Reklamlardan gelen hiçbir dönüşüm kaçmasın diye olaylar hem tarayıcıdan (Pixel) hem sunucudan (Dönüşümler API'si) aynı olay kimliğiyle gönderilir; Meta bunları tekilleştirir.</p></header>
  <div class="stats">
    <div class="stat<?= meta_pixel_id() ? '' : ' stat--off' ?>"><small>Veri seti (Pixel)</small><strong><?= meta_pixel_id() ? '✓ Bağlı' : '— Yok' ?></strong><span><?= e(meta_pixel_id() ?: 'ID girilmedi') ?></span></div>
    <div class="stat<?= capi_enabled() ? '' : ' stat--off' ?>"><small>Dönüşümler API'si</small><strong><?= capi_enabled() ? '✓ Aktif' : '— Kapalı' ?></strong><span><?= $tok ? 'Anahtar: ••••' . e(substr($tok, -6)) : 'Erişim anahtarı yok' ?></span></div>
    <div class="stat"><small>Son 24 saatte gönderilen</small><strong><?= array_sum(array_column($stats, 'ok')) ?></strong><span><?= array_sum(array_column($stats, 'bad')) ?> başarısız deneme</span></div>
    <div class="stat<?= $queue ? ' stat--warn' : '' ?>"><small>Yeniden denenecek (kuyruk)</small><strong><?= $queue ?></strong><span><?= $queue ? 'Otomatik tekrar deneniyor' : 'Bekleyen olay yok' ?></span></div>
  </div>
  <?php if ($s['meta_test_code']): ?><div class="alert alert--warn"><strong>Test kodu aktif (<?= e($s['meta_test_code']) ?>).</strong> Sunucu olayları şu an Olay Yöneticisi'nin “Test Olayları” sekmesine gidiyor. Testi bitirince test kodunu silip kaydedin; aksi halde bu olaylar reklam optimizasyonunda sayılmaz.</div><?php endif; ?>
  <?php if ($queue && $lastErr): ?><div class="alert alert--err">Son hata: <?= e(mb_strimwidth((string)$lastErr, 0, 300, '…')) ?></div><?php endif; ?>
  <div class="grid2">
    <section class="box">
      <h2>Bağlantı ayarları</h2>
      <form method="post" class="form"><?= csrf_field() ?><input type="hidden" name="action" value="meta_save">
        <label>Veri Seti (Pixel) ID<input name="meta_pixel_id" value="<?= e($s['meta_pixel_id']) ?>" placeholder="123456789012345" inputmode="numeric"></label>
        <label>Dönüşümler API'si erişim anahtarı <small><?= $tok ? '(kayıtlı — değiştirmek için yenisini yapıştırın)' : '' ?></small><input name="meta_capi_token" type="password" autocomplete="off" placeholder="<?= $tok ? '••••••••' . e(substr($tok, -6)) : 'EAAG…' ?>"></label>
        <?php if ($tok): ?><label class="check"><input type="checkbox" name="meta_token_remove" value="1"> Kayıtlı anahtarı sil</label><?php endif; ?>
        <div class="row3">
          <label>Test olay kodu <small>(yalnızca test sırasında)</small><input name="meta_test_code" value="<?= e($s['meta_test_code']) ?>" placeholder="TEST12345"></label>
          <label>Graph API sürümü<input name="meta_api_version" value="<?= e($s['meta_api_version'] ?: 'v26.0') ?>" placeholder="v26.0"></label>
        </div>
        <div class="form__acts"><button class="btn btn--primary">Kaydet</button></div>
      </form>
      <div class="form__acts" style="margin-top:1rem">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="meta_test"><button class="btn">⚡ Test olayı gönder</button></form>
        <?php if ($queue): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="meta_flush"><button class="btn">↻ Kuyruğu şimdi gönder</button></form><?php endif; ?>
      </div>
    </section>
    <section class="box">
      <h2>Kurulum adımları</h2>
      <ol class="steps-list">
        <li><strong>Meta Olay Yöneticisi</strong>'ni açın (business.facebook.com › Olay Yöneticisi) ve web sitenizin <strong>veri setini</strong> seçin. Veri setiniz yoksa “Veri kaynaklarını bağla › Web” ile oluşturun.</li>
        <li>Veri seti adının altındaki <strong>ID</strong>'yi kopyalayıp “Veri Seti (Pixel) ID” alanına yapıştırın.</li>
        <li><strong>Ayarlar</strong> sekmesinde “Dönüşümler API'si” bölümünden <strong>“Erişim anahtarı oluştur”</strong>a tıklayın, anahtarı kopyalayıp buraya yapıştırın ve kaydedin.</li>
        <li><strong>Test Olayları</strong> sekmesindeki test kodunu “Test olay kodu” alanına girin, kaydedin ve <strong>Test olayı gönder</strong>'e basın. Siteyi gezip sepete ürün ekleyin; olaylar hem “Tarayıcı” hem “Sunucu” olarak görünmeli ve “Tekilleştirildi” yazmalı.</li>
        <li>Test bitince <strong>test kodunu silip kaydedin</strong>.</li>
        <li>Olay Yöneticisi › Ayarlar'da alan adınızı <strong>doğrulayın</strong> ve Toplu Olay Ölçümü (AEM) için <strong>Purchase</strong> olayını en üst önceliğe alın.</li>
      </ol>
      <p class="hint">Gönderilen olaylar: PageView, ViewContent, AddToCart, InitiateCheckout, AddPaymentInfo, Contact (arama/WhatsApp tıklaması) ve Purchase. Purchase olayında müşteri adı, telefonu, e-postası ve ili SHA-256 ile şifrelenerek gönderilir (Gelişmiş Eşleştirme).</p>
    </section>
  </div>
  <section class="box">
    <div class="box__head"><h2>Son 24 saat — olay bazında</h2></div>
    <?php if (!$stats): ?><p class="empty">Henüz sunucu olayı yok. Dönüşümler API'si etkinleştirildikten sonra olaylar burada görünür.</p><?php else: ?>
    <div class="table-wrap"><table class="table"><thead><tr><th>Olay</th><th>Başarılı</th><th>Başarısız deneme</th></tr></thead><tbody>
      <?php foreach ($stats as $r): ?><tr><td><strong><?= e($r['event_name']) ?></strong></td><td><?= (int)$r['ok'] ?></td><td><?= (int)$r['bad'] ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </section>
  <section class="box">
    <div class="box__head"><h2>Son gönderimler</h2></div>
    <?php if (!$logs): ?><p class="empty">Kayıt yok.</p><?php else: ?>
    <div class="table-wrap"><table class="table"><thead><tr><th>Zaman</th><th>Olay</th><th>Olay kimliği</th><th>Durum</th><th>Meta yanıtı</th></tr></thead><tbody>
      <?php foreach ($logs as $l): $okRow = str_starts_with($l['status'], 'ok'); ?>
      <tr><td><?= date('d.m H:i:s', strtotime($l['created_at'])) ?></td><td><strong><?= e($l['event_name']) ?></strong></td><td><small><?= e($l['event_id']) ?></small></td>
        <td><span class="badge" style="--c:<?= $okRow ? '#15803d' : '#b4321f' ?>"><?= e($l['status']) ?><?= $l['http_code'] ? ' · ' . (int)$l['http_code'] : '' ?></span></td>
        <td><small><?= e(mb_strimwidth((string)$l['response'], 0, 140, '…')) ?></small></td></tr>
      <?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </section>

<?php elseif ($p === 'reviews'): $rows = db()->query('SELECT * FROM reviews ORDER BY id DESC')->fetchAll(); $edit = null;
    if (isset($_GET['id'])) { foreach ($rows as $r) { if ((int)$r['id'] === (int)$_GET['id']) { $edit = $r; } } } ?>
  <header class="head"><h1>Müşteri Yorumları</h1><p>Müşterilerinizden gelen gerçek yorumları (WhatsApp, Instagram vb.) buraya ekleyin. En az bir yorum eklendiğinde sitede “Müşterilerimiz ne diyor?” bölümü görünür.</p></header>
  <div class="grid2">
    <section class="box">
      <h2><?= $edit ? 'Yorumu düzenle' : 'Yeni yorum ekle' ?></h2>
      <form method="post" class="form"><?= csrf_field() ?><input type="hidden" name="action" value="review_save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
        <div class="row3"><label>Müşteri adı *<input name="name" value="<?= e($edit['name'] ?? '') ?>" placeholder="Ayşe K." required></label><label>Şehir<input name="city" value="<?= e($edit['city'] ?? '') ?>" placeholder="İzmir"></label></div>
        <label>Yorum *<textarea name="text" rows="4" required><?= e($edit['text'] ?? '') ?></textarea></label>
        <div class="row3"><label>Puan<select name="rating"><?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"<?= (int)($edit['rating'] ?? 5) === $i ? ' selected' : '' ?>><?= str_repeat('★', $i) ?></option><?php endfor; ?></select></label>
        <label class="check check--pad"><input type="checkbox" name="active"<?= ($edit['active'] ?? 1) ? ' checked' : '' ?>> Sitede göster</label></div>
        <div class="form__acts"><button class="btn btn--primary">Kaydet</button><?php if ($edit): ?><a href="?p=reviews" class="btn btn--ghost">Vazgeç</a><?php endif; ?></div>
      </form>
    </section>
    <section class="box">
      <h2>Yorumlar (<?= count($rows) ?>)</h2>
      <?php if (!$rows): ?><p class="empty">Henüz yorum eklenmedi.</p><?php endif; ?>
      <?php foreach ($rows as $r): ?>
      <div class="rev<?= $r['active'] ? '' : ' off' ?>"><div><strong><?= e($r['name']) ?></strong> <small><?= e($r['city']) ?> · <?= str_repeat('★', (int)$r['rating']) ?><?= $r['active'] ? '' : ' · gizli' ?></small><p><?= e($r['text']) ?></p></div>
        <div class="rev__acts"><a class="btn btn--sm btn--ghost" href="?p=reviews&id=<?= $r['id'] ?>">Düzenle</a>
        <form method="post" onsubmit="return confirm('Yorum silinsin mi?')"><?= csrf_field() ?><input type="hidden" name="action" value="review_delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn--sm btn--danger">Sil</button></form></div></div>
      <?php endforeach; ?>
    </section>
  </div>

<?php elseif ($p === 'settings'): $s = settings(true); ?>
  <header class="head"><h1>Ayarlar</h1><p>İletişim, ödeme, kargo, reklam takibi ve SEO ayarları.</p></header>
  <form method="post" enctype="multipart/form-data" class="form">
    <?= csrf_field() ?><input type="hidden" name="action" value="settings_save">
    <div class="grid2">
      <section class="box">
        <h2>📞 İletişim & marka</h2>
        <div class="row3"><label>Marka adı<input name="brand_name" value="<?= e($s['brand_name']) ?>"></label><label>Şirket ünvanı<input name="company_name" value="<?= e($s['company_name']) ?>"></label></div>
        <div class="row3"><label>Telefon (görünen)<input name="phone" value="<?= e($s['phone']) ?>"></label><label>WhatsApp numarası<input name="whatsapp" value="<?= e($s['whatsapp']) ?>" placeholder="905352657519"></label></div>
        <div class="row3"><label>Instagram kullanıcı adı<input name="instagram" value="<?= e($s['instagram']) ?>"></label><label>Adres / konum<input name="address" value="<?= e($s['address']) ?>"></label></div>
        <div class="row3"><label>Sitede görünen e-posta<input name="email" type="email" value="<?= e($s['email']) ?>" placeholder="İsteğe bağlı"></label><label>Yeni sipariş bildirim e-postası<input name="notify_email" type="email" value="<?= e($s['notify_email']) ?>" placeholder="siparis@ornek.com"></label></div>
        <label>Slogan<input name="slogan" value="<?= e($s['slogan']) ?>"></label>
        <label>Üst duyuru şeridi<input name="announcement" value="<?= e($s['announcement']) ?>"></label>
      </section>
      <section class="box">
        <h2>💳 Ödeme & kargo</h2>
        <label class="check"><input type="checkbox" name="pay_cod_cash"<?= $s['pay_cod_cash'] === '1' ? ' checked' : '' ?>> Kapıda nakit ödeme</label>
        <label class="check"><input type="checkbox" name="pay_cod_card"<?= $s['pay_cod_card'] === '1' ? ' checked' : '' ?>> Kapıda kredi kartı ile ödeme</label>
        <label class="check"><input type="checkbox" name="pay_transfer"<?= $s['pay_transfer'] === '1' ? ' checked' : '' ?>> Havale / EFT</label>
        <div class="row3"><label>Kapıda ödeme hizmet bedeli (₺)<input class="money" name="cod_fee" value="<?= e(fmt_input((float)$s['cod_fee'])) ?>"></label><label>Havale/EFT indirimi (%)<input class="money" name="transfer_discount" value="<?= e(fmt_input((float)$s['transfer_discount'])) ?>"></label></div>
        <div class="row3"><label>Kargo ücreti (₺) <small>0 = ücretsiz</small><input class="money" name="shipping_fee" value="<?= e(fmt_input((float)$s['shipping_fee'])) ?>"></label><label>Ücretsiz kargo alt limiti (₺)<input class="money" name="free_shipping_min" value="<?= e(fmt_input((float)$s['free_shipping_min'])) ?>"></label></div>
        <label>Minimum sipariş tutarı (₺) <small>0 = yok</small><input class="money" name="min_order" value="<?= e(fmt_input((float)$s['min_order'])) ?>"></label>
        <h3>Havale / EFT bilgileri</h3>
        <div class="row3"><label>Banka adı<input name="bank_name" value="<?= e($s['bank_name']) ?>" placeholder="Ziraat Bankası"></label><label>Alıcı adı<input name="account_holder" value="<?= e($s['account_holder']) ?>"></label></div>
        <label>IBAN<input name="iban" value="<?= e($s['iban']) ?>" placeholder="TR00 0000 0000 0000 0000 0000 00"></label>
      </section>
      <section class="box">
        <h2>🖼 Ana görsel (hero)</h2>
        <p class="hint">Üst bölümün arka planına doğal bir görsel (örn. zeytinlik, bahçe) yükleyebilirsiniz. Yatay ve yüksek çözünürlüklü fotoğraf önerilir. Boş bırakılırsa doğal krem tonlu arka plan kullanılır.</p>
        <?php if ($s['hero_image']): ?><img src="../<?= e($s['hero_image']) ?>" alt="" class="hero-prev"><label class="check"><input type="checkbox" name="hero_remove" value="1"> Görseli kaldır</label><?php endif; ?>
        <label>Görsel yükle<input type="file" name="hero_image" accept="image/jpeg,image/png,image/webp"></label>
      </section>
      <section class="box">
        <h2>📈 Analiz & SEO</h2>
        <p class="hint">Meta Pixel ve Dönüşümler API'si ayarları <a href="?p=meta">Meta Reklam</a> sayfasındadır.</p>
        <label>Google Analytics 4 ID<input name="ga4_id" value="<?= e($s['ga4_id']) ?>" placeholder="G-XXXXXXXXXX"></label>
        <h3>SEO</h3>
        <label>Site adresi <small>(örn. https://aksarege.com — boşsa otomatik)</small><input name="site_url" value="<?= e($s['site_url']) ?>" placeholder="https://"></label>
        <label>Sayfa başlığı (title) <small><span data-count="seo_title"></span>/60</small><input name="seo_title" value="<?= e($s['seo_title']) ?>" maxlength="90"></label>
        <label>Açıklama (meta description) <small><span data-count="seo_description"></span>/160</small><textarea name="seo_description" rows="3" maxlength="300"><?= e($s['seo_description']) ?></textarea></label>
      </section>
    </div>
    <section class="box">
      <h2>📄 Yasal metinler</h2>
      <p class="hint">Boş bırakırsanız sitede hazır şablon metin gösterilir. Kendi metninizi yazmak için doldurun.</p>
      <label>Mesafeli Satış Sözleşmesi<textarea name="distance_contract" rows="5" placeholder="<?= e(mb_strimwidth(legal_text('distance_contract'), 0, 160, '…')) ?>"><?= e($s['distance_contract']) ?></textarea></label>
      <label>KVKK Aydınlatma Metni<textarea name="kvkk_text" rows="4"><?= e($s['kvkk_text']) ?></textarea></label>
      <label>İade & Değişim Koşulları<textarea name="return_policy" rows="4"><?= e($s['return_policy']) ?></textarea></label>
    </section>
    <div class="form__acts sticky-save"><button class="btn btn--primary btn--lg">Ayarları kaydet</button></div>
  </form>

  <section class="box">
    <h2>🔒 Panel şifresini değiştir</h2>
    <form method="post" class="form row3"><?= csrf_field() ?><input type="hidden" name="action" value="password_change">
      <label>Mevcut şifre<input type="password" name="current" required autocomplete="current-password"></label>
      <label>Yeni şifre<input type="password" name="new" required minlength="8" autocomplete="new-password"></label>
      <div class="form__acts"><button class="btn">Şifreyi değiştir</button></div>
    </form>
  </section>
  <script>
  document.querySelectorAll('[data-count]').forEach(function (c) {
    var f = document.querySelector('[name="' + c.dataset.count + '"]');
    var u = function () { c.textContent = f.value.length; }; f.addEventListener('input', u); u();
  });
  </script>
<?php else: ?>
  <p class="empty">Sayfa bulunamadı. <a href="./">Panele dön</a></p>
<?php endif; ?>
</main>
</body>
</html>
