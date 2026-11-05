<?php
/**
 * Meta Pixel + Dönüşümler API'si (CAPI)
 *
 * - Tarayıcı (Pixel) ve sunucu (CAPI) olayları aynı event_id ile gönderilir → Meta tekilleştirir.
 * - _fbp / _fbc çerezleri sunucu tarafında yazılır (Safari ITP'nin 7 günlük JS çerez sınırına takılmaz).
 * - Reklam engelleyici Pixel'i engellese bile olaylar birinci taraf uç noktadan (api/event.php) CAPI'ye ulaşır.
 * - Gönderilemeyen olaylar kuyruğa alınır ve 6 gün boyunca otomatik yeniden denenir.
 */
declare(strict_types=1);

const META_EVENTS = ['PageView', 'ViewContent', 'AddToCart', 'InitiateCheckout', 'AddPaymentInfo', 'Contact', 'Lead', 'Purchase'];

function meta_pixel_id(): string { return preg_replace('/\D/', '', setting('meta_pixel_id')); }
function capi_enabled(): bool { return meta_pixel_id() !== '' && setting('meta_capi_token') !== ''; }
function meta_graph_base(): string { return rtrim(getenv('AE_GRAPH_BASE') ?: 'https://graph.facebook.com', '/'); }

/* ------------------------------------------------------------------ */
/* Çerezler & ziyaretçi kimliği                                        */
/* ------------------------------------------------------------------ */
function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function set_first_party_cookie(string $name, string $value, int $days, bool $httpOnly = false): void
{
    if (headers_sent()) { return; }
    setcookie($name, $value, [
        'expires' => time() + $days * 86400, 'path' => '/', 'secure' => is_https(),
        'httponly' => $httpOnly, 'samesite' => 'Lax',
    ]);
    $_COOKIE[$name] = $value;
}

/**
 * Sayfa çıktısından önce çağrılır: ziyaretçi kimliği, _fbp, _fbc ve reklam kaynağı (UTM) çerezlerini yazar/yeniler.
 */
function meta_init_cookies(): void
{
    // Kalıcı anonim ziyaretçi kimliği (external_id)
    $vid = $_COOKIE['ae_vid'] ?? '';
    if (!preg_match('/^[a-f0-9]{32}$/', $vid)) { $vid = bin2hex(random_bytes(16)); }
    set_first_party_cookie('ae_vid', $vid, 400, true);

    // _fbp (Pixel ile aynı biçim). Varsa süresini uzatır, yoksa oluşturur.
    $fbp = $_COOKIE['_fbp'] ?? '';
    if (!preg_match('/^fb\.\d\.\d{10,13}\.\d+$/', $fbp)) {
        $fbp = 'fb.1.' . (int)(microtime(true) * 1000) . '.' . random_int(1000000000, 2147483647);
    }
    set_first_party_cookie('_fbp', $fbp, 90);

    // _fbc: reklamdan gelişte fbclid parametresinden üretilir (fbclid büyük/küçük harfi korunur)
    $fbclid = isset($_GET['fbclid']) ? preg_replace('/[^A-Za-z0-9_\-]/', '', (string)$_GET['fbclid']) : '';
    $fbc = $_COOKIE['_fbc'] ?? '';
    if ($fbclid !== '' && !str_ends_with($fbc, '.' . $fbclid)) {
        $fbc = 'fb.1.' . (int)(microtime(true) * 1000) . '.' . $fbclid;
    }
    if (preg_match('/^fb\.\d\.\d{10,13}\..+$/', $fbc)) { set_first_party_cookie('_fbc', $fbc, 90); }

    // Reklam kaynağı (son tıklama) — siparişle birlikte panelde gösterilir
    $utm = [];
    foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'] as $k) {
        if (!empty($_GET[$k])) { $utm[$k] = mb_substr(strip_tags((string)$_GET[$k]), 0, 120); }
    }
    if ($utm || $fbclid !== '') {
        $src = $utm + ['fbclid' => $fbclid !== '', 'ts' => date('c')];
        set_first_party_cookie('ae_src', base64_encode(json_encode($src, JSON_UNESCAPED_UNICODE)), 30, true);
    }
}

function meta_visitor_id(): string { return preg_match('/^[a-f0-9]{32}$/', $_COOKIE['ae_vid'] ?? '') ? $_COOKIE['ae_vid'] : ''; }

function meta_attribution(): array
{
    $src = json_decode((string)base64_decode((string)($_COOKIE['ae_src'] ?? ''), true), true);
    $out = is_array($src) ? $src : [];
    if (!empty($_COOKIE['_fbc'])) { $out['fbc'] = (string)$_COOKIE['_fbc']; }
    if (!empty($_COOKIE['_fbp'])) { $out['fbp'] = (string)$_COOKIE['_fbp']; }
    return $out;
}

function meta_client_ip(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $h) {
        $v = trim(explode(',', (string)($_SERVER[$h] ?? ''))[0]);
        if ($v !== '' && filter_var($v, FILTER_VALIDATE_IP)) { return $v; }
    }
    return '';
}

/* ------------------------------------------------------------------ */
/* Kullanıcı verisi (Meta'nın normalizasyon kurallarına göre)          */
/* ------------------------------------------------------------------ */
function meta_hash(string $v): string { return hash('sha256', $v); }

function meta_ascii_lower(string $v): string
{
    $v = strtr($v, ['I' => 'ı', 'İ' => 'i']);
    $v = mb_strtolower(trim($v), 'UTF-8');
    return strtr($v, ['ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u', 'â' => 'a', 'î' => 'i', 'û' => 'u']);
}

/**
 * @param array $customer ['email','phone','name','city']
 */
function meta_user_data(array $customer = []): array
{
    $ud = [
        'client_ip_address' => meta_client_ip(),
        'client_user_agent' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
        'country' => [meta_hash('tr')],
    ];
    if ($vid = meta_visitor_id()) { $ud['external_id'] = [meta_hash($vid)]; }
    if (!empty($_COOKIE['_fbp'])) { $ud['fbp'] = (string)$_COOKIE['_fbp']; }
    if (!empty($_COOKIE['_fbc'])) { $ud['fbc'] = (string)$_COOKIE['_fbc']; }

    if (!empty($customer['email'])) { $ud['em'] = [meta_hash(mb_strtolower(trim($customer['email'])))]; }
    if (!empty($customer['phone'])) { $ud['ph'] = [meta_hash(phone_digits($customer['phone']))]; }
    if (!empty($customer['name'])) {
        $parts = preg_split('/\s+/u', trim($customer['name']));
        $fn = preg_replace('/[^a-z]/', '', meta_ascii_lower($parts[0] ?? ''));
        $ln = preg_replace('/[^a-z]/', '', meta_ascii_lower(count($parts) > 1 ? end($parts) : ''));
        if ($fn !== '') { $ud['fn'] = [meta_hash($fn)]; }
        if ($ln !== '') { $ud['ln'] = [meta_hash($ln)]; }
    }
    if (!empty($customer['city'])) {
        $ct = preg_replace('/[^a-z]/', '', meta_ascii_lower($customer['city']));
        if ($ct !== '') { $ud['ct'] = [meta_hash($ct)]; }
    }
    return array_filter($ud, fn($v) => $v !== '' && $v !== []);
}

/**
 * Sepet/ürün listesinden custom_data üretir. Fiyatlar her zaman veritabanından alınır.
 * @param array $items [['id'=>..,'qty'=>..], ...]
 */
function meta_custom_data(array $items): array
{
    $contents = []; $value = 0.0; $num = 0; $names = [];
    foreach (array_slice($items, 0, 30) as $it) {
        $p = product((int)($it['id'] ?? 0));
        if (!$p) { continue; }
        $q = max(1, min(99, (int)($it['qty'] ?? 1)));
        $contents[] = ['id' => (string)$p['id'], 'quantity' => $q, 'item_price' => round($p['price'], 2)];
        $value += $p['price'] * $q; $num += $q; $names[] = $p['name'];
    }
    if (!$contents) { return []; }
    $cd = [
        'currency' => 'TRY', 'value' => round($value, 2), 'content_type' => 'product',
        'content_ids' => array_column($contents, 'id'), 'contents' => $contents, 'num_items' => $num,
    ];
    if (count($names) === 1) { $cd['content_name'] = $names[0]; }
    return $cd;
}

function meta_event(string $name, string $eventId, string $url, array $userData, array $customData = []): array
{
    $e = [
        'event_name' => $name,
        'event_time' => time(),
        'event_id' => $eventId,
        'event_source_url' => $url,
        'action_source' => 'website',
        'user_data' => $userData,
    ];
    if ($customData) { $e['custom_data'] = $customData; }
    return $e;
}

/* ------------------------------------------------------------------ */
/* Gönderim, kayıt ve yeniden deneme kuyruğu                          */
/* ------------------------------------------------------------------ */
/** @return array{0:bool,1:int,2:string} */
function capi_post(array $events, bool $useTestCode = true): array
{
    $url = meta_graph_base() . '/' . setting('meta_api_version', 'v26.0') . '/' . meta_pixel_id() . '/events';
    $body = ['data' => array_values($events), 'access_token' => setting('meta_capi_token')];
    if ($useTestCode && ($tc = setting('meta_test_code')) !== '') { $body['test_event_code'] = $tc; }
    $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $json, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 8,
        ]);
        $resp = (string)curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($resp === '' && curl_errno($ch)) { $resp = 'Bağlantı hatası: ' . curl_error($ch); }
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/json\r\n", 'content' => $json, 'timeout' => 8, 'ignore_errors' => true]]);
        $resp = (string)@file_get_contents($url, false, $ctx);
        $code = isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m) ? (int)$m[1] : 0;
    }
    return [$code >= 200 && $code < 300, $code, mb_substr($resp, 0, 1000)];
}

function capi_log(array $event, string $status, int $code, string $resp): void
{
    db()->prepare('INSERT INTO capi_log(created_at, event_name, event_id, status, http_code, response, ip) VALUES(?,?,?,?,?,?,?)')
        ->execute([date('c'), $event['event_name'], $event['event_id'], $status, $code, $resp, meta_client_ip()]);
    if (random_int(1, 50) === 1) {
        db()->exec('DELETE FROM capi_log WHERE id <= (SELECT MAX(id) - 2000 FROM capi_log)');
    }
}

/** Olayı hemen gönderir; başarısız olursa kuyruğa alır. */
function capi_dispatch(array $event): bool
{
    if (!capi_enabled()) { return false; }
    [$ok, $code, $resp] = capi_post([$event]);
    if ($ok) { capi_log($event, 'ok', $code, $resp); return true; }
    db()->prepare('INSERT INTO capi_queue(event_json, attempts, last_error, created_at, next_try) VALUES(?,?,?,?,?)')
        ->execute([json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 1, mb_substr("$code $resp", 0, 500), date('c'), time() + 60]);
    capi_log($event, 'kuyruk', $code, $resp);
    return false;
}

/** Kuyruktaki olayları toplu olarak yeniden dener. Meta 7 günden eski olayları kabul etmediği için 6 günden eskiler atılır. */
function capi_flush(int $limit = 20, bool $force = false): array
{
    if (!capi_enabled()) { return ['sent' => 0, 'left' => 0]; }
    $pdo = db();
    $pdo->prepare('DELETE FROM capi_queue WHERE created_at < ?')->execute([date('c', time() - 6 * 86400)]);
    $st = $pdo->prepare('SELECT * FROM capi_queue WHERE next_try <= ? ORDER BY id LIMIT ' . max(1, $limit));
    $st->execute([$force ? PHP_INT_MAX : time()]);
    $rows = $st->fetchAll();
    $sent = 0;
    if ($rows) {
        $events = array_map(fn($r) => json_decode($r['event_json'], true), $rows);
        [$ok, $code, $resp] = capi_post($events);
        $ids = implode(',', array_map(fn($r) => (int)$r['id'], $rows));
        if ($ok) {
            $pdo->exec("DELETE FROM capi_queue WHERE id IN ($ids)");
            foreach ($events as $ev) { capi_log($ev, 'ok (tekrar)', $code, $resp); }
            $sent = count($rows);
        } else {
            foreach ($rows as $r) {
                $delay = min(6 * 3600, 60 * (2 ** min(10, (int)$r['attempts'])));
                $pdo->prepare('UPDATE capi_queue SET attempts = attempts + 1, last_error = ?, next_try = ? WHERE id = ?')
                    ->execute([mb_substr("$code $resp", 0, 500), time() + $delay, $r['id']]);
            }
        }
    }
    $left = (int)$pdo->query('SELECT COUNT(*) FROM capi_queue')->fetchColumn();
    return ['sent' => $sent, 'left' => $left];
}

/** Yanıtı tarayıcıya hemen gönderip bağlantıyı kapatır; ardından PHP arka planda çalışmaya devam eder. */
function finish_response(): void
{
    ignore_user_abort(true);
    if (function_exists('fastcgi_finish_request')) { fastcgi_finish_request(); return; }
    if (function_exists('litespeed_finish_request')) { litespeed_finish_request(); return; }
    while (ob_get_level() > 0) { ob_end_flush(); }
    flush();
}
