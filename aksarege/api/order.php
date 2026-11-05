<?php
/**
 * Sipariş alma uç noktası — POST JSON
 * Fiyatlar istemciden değil, veritabanından hesaplanır.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_out(['ok' => false, 'error' => 'Geçersiz istek.'], 405);
}

$raw = file_get_contents('php://input') ?: '';
if (strlen($raw) > 20000) { json_out(['ok' => false, 'error' => 'İstek çok büyük.'], 413); }
$in = json_decode($raw, true);
if (!is_array($in)) { json_out(['ok' => false, 'error' => 'Geçersiz veri.'], 400); }

// Bot tuzağı (gizli alan doluysa sessizce başarılı gibi davran)
if (!empty($in['website'])) {
    json_out(['ok' => true, 'order_no' => 'AE-' . date('ymd') . '-0000', 'total' => 0, 'lines' => [], 'payment_label' => '', 'bank' => null]);
}

// Basit hız sınırı: aynı IP'den 10 dakikada en fazla 5 sipariş
$ip = client_ip();
$st = db()->prepare("SELECT COUNT(*) FROM orders WHERE ip = ? AND created_at > ?");
$st->execute([$ip, date('c', time() - 600)]);
if ((int)$st->fetchColumn() >= 5) {
    json_out(['ok' => false, 'error' => 'Çok fazla sipariş denemesi yapıldı. Lütfen biraz sonra tekrar deneyin ya da bizi arayın.'], 429);
}

$clean = function ($v, int $max): string {
    $v = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$v)));
    return mb_substr($v, 0, $max);
};
$name     = $clean($in['name'] ?? '', 80);
$phoneRaw = preg_replace('/\D+/', '', (string)($in['phone'] ?? ''));
$city     = $clean($in['city'] ?? '', 40);
$district = $clean($in['district'] ?? '', 60);
$address  = $clean($in['address'] ?? '', 400);
$email    = $clean($in['email'] ?? '', 120);
$note     = mb_substr(trim(strip_tags((string)($in['note'] ?? ''))), 0, 600);
$payment  = (string)($in['payment'] ?? '');
$items    = is_array($in['items'] ?? null) ? array_slice($in['items'], 0, 30) : [];

if (str_starts_with($phoneRaw, '90') && strlen($phoneRaw) === 12) { $phoneRaw = substr($phoneRaw, 2); }
if (strlen($phoneRaw) === 10) { $phoneRaw = '0' . $phoneRaw; }

$err = null;
if (mb_strlen($name) < 3)                                   { $err = 'Lütfen adınızı ve soyadınızı yazın.'; }
elseif (!preg_match('/^0[2-5]\d{9}$/', $phoneRaw))          { $err = 'Lütfen geçerli bir telefon numarası yazın.'; }
elseif (!in_array($city, turkish_cities(), true))           { $err = 'Lütfen ilinizi seçin.'; }
elseif (mb_strlen($district) < 2)                           { $err = 'Lütfen ilçenizi yazın.'; }
elseif (mb_strlen($address) < 10)                           { $err = 'Lütfen açık adresinizi eksiksiz yazın.'; }
elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $err = 'E-posta adresi geçersiz.'; }
elseif (!array_key_exists($payment, payment_methods()))     { $err = 'Lütfen geçerli bir ödeme yöntemi seçin.'; }
elseif (empty($in['consent']))                              { $err = 'Lütfen sözleşmeyi onaylayın.'; }
if ($err) { json_out(['ok' => false, 'error' => $err], 422); }

$calc = calculate_order($items, $payment);
if (!$calc['lines']) {
    json_out(['ok' => false, 'error' => 'Sepetinizdeki ürünler şu anda satışta değil. Lütfen sayfayı yenileyip tekrar deneyin.'], 422);
}
$min = (float)setting('min_order', '0');
if ($min > 0 && $calc['subtotal'] < $min) {
    json_out(['ok' => false, 'error' => 'Minimum sipariş tutarı ' . money($min) . '.'], 422);
}

$phoneFmt = preg_replace('/^(\d{4})(\d{3})(\d{2})(\d{2})$/', '$1 $2 $3 $4', $phoneRaw);
$pdo = db();
$pdo->beginTransaction();
try {
    $pdo->prepare('INSERT INTO orders(order_no,created_at,status,customer_name,phone,email,city,district,address,payment_method,note,items,subtotal,discount,shipping,cod_fee,total,ip,user_agent,attribution)
                   VALUES(NULL,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([
            date('c'), 'yeni', $name, $phoneFmt, $email, $city, $district, $address, $payment, $note,
            json_encode($calc['lines'], JSON_UNESCAPED_UNICODE),
            $calc['subtotal'], $calc['discount'], $calc['shipping'], $calc['codFee'], $calc['total'],
            $ip, mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250),
            json_encode(meta_attribution(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    $id = (int)$pdo->lastInsertId();
    $orderNo = 'AE-' . date('ymd') . '-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT);
    $pdo->prepare('UPDATE orders SET order_no = ? WHERE id = ?')->execute([$orderNo, $id]);
    $pdo->commit();
} catch (Throwable $ex) {
    $pdo->rollBack();
    error_log('Aksar Ege sipariş hatası: ' . $ex->getMessage());
    json_out(['ok' => false, 'error' => 'Siparişiniz kaydedilemedi. Lütfen tekrar deneyin ya da bizi arayın.'], 500);
}

$pm = payment_methods()[$payment]['label'];
$bank = null;
if ($payment === 'havale') {
    $bank = ['bank' => setting('bank_name'), 'holder' => setting('account_holder'), 'iban' => setting('iban')];
}
$metaEventId = 'purchase.' . $orderNo;

// 1) Müşteriye hemen yanıt ver
$payload = json_encode([
    'ok' => true,
    'order_no' => $orderNo,
    'total' => $calc['total'],
    'lines' => array_map(fn($l) => ['id' => $l['id'], 'name' => $l['name'], 'qty' => $l['qty'], 'price' => $l['price']], $calc['lines']),
    'payment_label' => $pm,
    'bank' => $bank,
    'meta_event_id' => $metaEventId,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Content-Length: ' . strlen($payload));
header('Connection: close');
echo $payload;
finish_response();

// 2) Arka planda: Meta Dönüşümler API'sine Purchase olayı (tarayıcıdaki Pixel olayıyla aynı event_id)
if (capi_enabled()) {
    $custom = meta_custom_data(array_map(fn($l) => ['id' => $l['id'], 'qty' => $l['qty']], $calc['lines']));
    $custom['value'] = $calc['total'];
    $custom['order_id'] = $orderNo;
    $custom['payment_method'] = $payment;
    $url = (string)($_SERVER['HTTP_REFERER'] ?? '');
    if (parse_url($url, PHP_URL_HOST) !== parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) { $url = base_url() . '/'; }
    $event = meta_event('Purchase', $metaEventId, $url,
        meta_user_data(['email' => $email, 'phone' => $phoneRaw, 'name' => $name, 'city' => $city]), $custom);
    capi_dispatch($event);
    capi_flush(20);
}

// 3) E-posta bildirimi (panelde bildirim e-postası girildiyse)
$notify = setting('notify_email');
if ($notify !== '' && function_exists('mail')) {
    $body = "Yeni sipariş: $orderNo\n\n";
    foreach ($calc['lines'] as $l) { $body .= "- {$l['qty']} x {$l['name']} = " . money($l['total']) . "\n"; }
    $body .= "\nToplam: " . money($calc['total']) . "\nÖdeme: $pm\n\nMüşteri: $name\nTelefon: $phoneFmt\nAdres: $address, $district / $city\n";
    if ($note !== '') { $body .= "Not: $note\n"; }
    $body .= "\nPanel: " . base_url() . "/panel/?p=order&id=$id\n";
    $host = preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    $headers = "From: Aksar Ege <siparis@$host>\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    @mail($notify, '=?UTF-8?B?' . base64_encode("🫒 Yeni Sipariş $orderNo - " . money($calc['total'])) . '?=', $body, $headers);
}
