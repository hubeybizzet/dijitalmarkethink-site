<?php
/**
 * Birinci taraf olay uç noktası → Meta Dönüşümler API'si
 * Tarayıcı, Pixel'e gönderdiği olayın aynısını (aynı event_id ile) buraya da iletir.
 * Reklam engelleyiciler Pixel'i engellese bile bu istek sitenin kendi alan adına gittiği için ulaşır.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';

header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit; }

// Yalnızca kendi sitemizden gelen istekleri kabul et
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '');
$host = (string)($_SERVER['HTTP_HOST'] ?? '');
if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== parse_url('http://' . $host, PHP_URL_HOST)) { http_response_code(403); exit; }

if (!capi_enabled()) { http_response_code(204); exit; }

$in = json_decode((string)file_get_contents('php://input', false, null, 0, 8000), true);
$name = (string)($in['event_name'] ?? '');
$eventId = (string)($in['event_id'] ?? '');
if (!is_array($in) || !in_array($name, META_EVENTS, true) || $name === 'Purchase' || !preg_match('/^[A-Za-z0-9._-]{8,100}$/', $eventId)) {
    http_response_code(400); exit;
}

// Kötüye kullanıma karşı: aynı IP'den dakikada en fazla 120 olay
$st = db()->prepare('SELECT COUNT(*) FROM capi_log WHERE ip = ? AND created_at > ?');
$st->execute([meta_client_ip(), date('c', time() - 60)]);
if ((int)$st->fetchColumn() >= 120) { http_response_code(429); exit; }

// Olay adresi: yalnızca kendi alan adımız
$url = (string)($in['url'] ?? '');
if (parse_url($url, PHP_URL_HOST) !== parse_url('http://' . $host, PHP_URL_HOST)) { $url = base_url() . '/'; }
$url = mb_substr($url, 0, 1000);

// custom_data sunucuda, veritabanındaki fiyatlarla üretilir (istemciden gelen tutara güvenilmez)
$items = is_array($in['items'] ?? null) ? $in['items'] : [];
$custom = in_array($name, ['ViewContent', 'AddToCart', 'InitiateCheckout', 'AddPaymentInfo'], true) ? meta_custom_data($items) : [];
if ($name === 'Contact' && !empty($in['method']) && in_array($in['method'], ['phone', 'whatsapp'], true)) {
    $custom['contact_method'] = $in['method'];
}
if ($name === 'AddPaymentInfo' && !empty($in['payment']) && array_key_exists($in['payment'], payment_methods())) {
    $custom['payment_method'] = $in['payment'];
}

$event = meta_event($name, $eventId, $url, meta_user_data(), $custom);

// Tarayıcıyı bekletme: önce yanıtla, sonra Meta'ya gönder
http_response_code(204);
header('Content-Length: 0');
header('Connection: close');
finish_response();

capi_dispatch($event);
capi_flush(20);
