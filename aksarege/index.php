<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';

$s         = settings();
$products  = products(true);
$reviews   = db()->query('SELECT * FROM reviews WHERE active = 1 ORDER BY id DESC LIMIT 12')->fetchAll();
$payments  = payment_methods();
$base      = base_url();
$brand     = setting('brand_name', 'Aksar Ege');
$phone     = setting('phone');
$waNumber  = phone_digits(setting('whatsapp', $phone));
$telHref   = 'tel:+' . phone_digits($phone);
$waText    = rawurlencode("Merhaba, $brand zeytinleri hakkında bilgi almak istiyorum.");
$waHref    = "https://wa.me/$waNumber?text=$waText";
$insta     = setting('instagram');
$heroImg   = setting('hero_image');
$orderable = array_values(array_filter($products, fn($p) => $p['orderable']));
$byslug    = [];
foreach ($products as $p) { $byslug[$p['slug']] = $p; }

$shippingFee = (float)setting('shipping_fee', '0');
$freeMin     = (float)setting('free_shipping_min', '0');
$freeShipping = $shippingFee <= 0;
$shipText = $freeShipping ? 'Ücretsiz Kargo' : ($freeMin > 0 ? money($freeMin) . ' üzeri ücretsiz kargo' : 'Hızlı Kargo');

$title = setting('seo_title');
$desc  = setting('seo_description');
$ogImg = $base . '/assets/img/og-image.jpg';

$faqs = [
    ['Kargo ücreti ödeyecek miyim?', $freeShipping
        ? "Hayır. Akhisar'dan Türkiye'nin her yerine kargo ücretsizdir. Ödediğiniz tutar, sepette gördüğünüz tutardır."
        : ($freeMin > 0 ? money($freeMin) . " ve üzeri siparişlerde kargo ücretsizdir. Altındaki siparişlerde kargo ücreti " . money($shippingFee) . "'dir." : "Kargo ücreti " . money($shippingFee) . "'dir ve sipariş özetinde açıkça gösterilir.")],
    ['Hangi ödeme yöntemlerini kullanabilirim?', 'Kapıda nakit ödeme, kapıda kredi/banka kartı ile ödeme ve havale/EFT seçeneklerinden dilediğinizi seçebilirsiniz. Kapıda ödemede ürünü teslim alırken ödersiniz; peşin ödeme yapmanız gerekmez.'],
    ['Siparişim ne zaman elime ulaşır?', "Siparişiniz onaylandıktan sonra özenle paketlenip kargoya verilir. Bulunduğunuz ile göre çoğunlukla 1–3 iş günü içinde kapınızdadır. Kargo takip numaranız tarafınıza iletilir."],
    ['Zeytinler kargoda zarar görür mü?', "Zeytinlerimiz sızdırmaz kapaklı kavanozlarda, darbelere karşı özenle paketlenerek gönderilir. Teslim alırken paketi kuryenin yanında kontrol etmenizi öneririz; dışarıdan bir hasar görürseniz kargo görevlisine hasar tespit tutanağı tutturun."],
    ['Zeytinleri nasıl saklamalıyım?', 'Açılmamış kavanozları serin ve güneş görmeyen bir yerde saklayın. Açtıktan sonra buzdolabında muhafaza edin, zeytinlerin salamurasının içinde kalmasına ve temiz, kuru bir kaşık kullanmaya özen gösterin.'],
    ['Zeytinyağı ve sarımsak da satıyor musunuz?', "Evet! Ege'nin doğal zeytinyağı ve sarımsağı için de sipariş alıyoruz. Güncel ürün ve fiyat bilgisi için bizi arayabilir ya da WhatsApp'tan yazabilirsiniz."],
    ['Toplu veya kurumsal sipariş verebilir miyim?', "Elbette. Restoran, kafe, otel ya da toplu hediye siparişleriniz için " . $phone . " numarasından bize ulaşın, size özel fiyat sunalım."],
];

/* -------- JSON-LD -------- */
$ld = [];
$ld[] = [
    '@context' => 'https://schema.org',
    '@type' => 'Store',
    '@id' => $base . '/#store',
    'name' => $brand,
    'legalName' => setting('company_name'),
    'description' => $desc,
    'url' => $base . '/',
    'logo' => $base . '/assets/img/brand/logo-512.png',
    'image' => $ogImg,
    'telephone' => '+' . phone_digits($phone),
    'priceRange' => '₺₺',
    'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Akhisar', 'addressRegion' => 'Manisa', 'addressCountry' => 'TR'],
    'areaServed' => ['@type' => 'Country', 'name' => 'Türkiye'],
    'paymentAccepted' => 'Kapıda Nakit, Kapıda Kredi Kartı, Havale/EFT',
    'currenciesAccepted' => 'TRY',
    'sameAs' => $insta ? ['https://www.instagram.com/' . $insta . '/'] : [],
];
$ld[] = ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => $brand, 'url' => $base . '/', 'inLanguage' => 'tr-TR'];
$itemList = ['@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => "$brand Doğal Zeytin Çeşitleri", 'itemListElement' => []];
foreach ($products as $i => $p) {
    $prod = [
        '@type' => 'Product',
        'name' => "$brand " . $p['name'],
        'description' => $p['short_desc'],
        'image' => array_map(fn($im) => $base . '/' . ltrim($im, '/'), $p['images']),
        'brand' => ['@type' => 'Brand', 'name' => $brand],
        'category' => 'Gıda > Zeytin',
        'sku' => 'AE-' . str_pad((string)$p['id'], 3, '0', STR_PAD_LEFT),
    ];
    if ($p['price'] > 0) {
        $prod['offers'] = [
            '@type' => 'Offer',
            'price' => number_format($p['price'], 2, '.', ''),
            'priceCurrency' => 'TRY',
            'availability' => $p['in_stock'] ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'itemCondition' => 'https://schema.org/NewCondition',
            'url' => $base . '/#urun-' . $p['slug'],
            'seller' => ['@id' => $base . '/#store'],
            'shippingDetails' => [
                '@type' => 'OfferShippingDetails',
                'shippingRate' => ['@type' => 'MonetaryAmount', 'value' => $freeShipping ? '0' : number_format($shippingFee, 2, '.', ''), 'currency' => 'TRY'],
                'shippingDestination' => ['@type' => 'DefinedRegion', 'addressCountry' => 'TR'],
                'deliveryTime' => [
                    '@type' => 'ShippingDeliveryTime',
                    'handlingTime' => ['@type' => 'QuantitativeValue', 'minValue' => 0, 'maxValue' => 1, 'unitCode' => 'DAY'],
                    'transitTime' => ['@type' => 'QuantitativeValue', 'minValue' => 1, 'maxValue' => 3, 'unitCode' => 'DAY'],
                ],
            ],
        ];
    }
    if ($reviews) {
        $avg = array_sum(array_map(fn($r) => (int)$r['rating'], $reviews)) / count($reviews);
        $prod['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => round($avg, 1), 'reviewCount' => count($reviews)];
    }
    $itemList['itemListElement'][] = ['@type' => 'ListItem', 'position' => $i + 1, 'item' => $prod];
}
$ld[] = $itemList;
$ld[] = [
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => array_map(fn($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]], $faqs),
];

/* -------- İstemci verisi -------- */
$clientData = [
    'products' => array_map(fn($p) => [
        'id' => $p['id'], 'slug' => $p['slug'], 'name' => $p['name'], 'unit' => $p['unit'],
        'price' => $p['price'], 'old_price' => $p['old_price'], 'orderable' => $p['orderable'],
        'image' => $p['images'][0] ?? '', 'images' => $p['images'], 'subtitle' => $p['subtitle'],
        'description' => $p['description'], 'features' => $p['features'], 'pairing' => $p['pairing'], 'badge' => $p['badge'],
    ], $products),
    'settings' => [
        'transfer_discount' => (float)setting('transfer_discount', '0'),
        'cod_fee' => (float)setting('cod_fee', '0'),
        'shipping_fee' => $shippingFee,
        'free_shipping_min' => $freeMin,
        'min_order' => (float)setting('min_order', '0'),
        'whatsapp' => $waNumber,
        'brand' => $brand,
        'pixel' => setting('meta_pixel_id') !== '',
        'ga4' => setting('ga4_id') !== '',
    ],
    'payments' => $payments,
];

function img_tag(string $src, string $alt, string $class = '', bool $lazy = true, int $w = 800, int $h = 1000, string $extra = ''): string
{
    return '<img src="' . e($src) . '" alt="' . e($alt) . '" width="' . $w . '" height="' . $h . '"'
        . ($class ? ' class="' . e($class) . '"' : '')
        . ($lazy ? ' loading="lazy" decoding="async"' : ' fetchpriority="high" decoding="async"')
        . $extra . '>';
}
$img = fn(string $slug, int $i = 0) => $byslug[$slug]['images'][$i] ?? ($byslug[$slug]['images'][0] ?? '');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<meta name="keywords" content="Akhisar zeytini, doğal zeytin, yeşil zeytin, kırma zeytin, çizik zeytin, ızgara zeytin, biber dolgulu zeytin, köy zeytini, Ege zeytini, zeytin satın al, kapıda ödeme zeytin">
<meta name="robots" content="index, follow, max-image-preview:large">
<meta name="author" content="<?= e(setting('company_name')) ?>">
<link rel="canonical" href="<?= e($base) ?>/">
<meta name="theme-color" content="#2c3a1a">
<meta name="geo.region" content="TR-45">
<meta name="geo.placename" content="Akhisar, Manisa">
<meta name="format-detection" content="telephone=no">

<meta property="og:type" content="website">
<meta property="og:locale" content="tr_TR">
<meta property="og:site_name" content="<?= e($brand) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:url" content="<?= e($base) ?>/">
<meta property="og:image" content="<?= e($ogImg) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="<?= e($brand) ?> doğal yeşil zeytin çeşitleri">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($title) ?>">
<meta name="twitter:description" content="<?= e($desc) ?>">
<meta name="twitter:image" content="<?= e($ogImg) ?>">

<link rel="icon" type="image/png" sizes="32x32" href="assets/img/brand/favicon-32.png">
<link rel="icon" type="image/png" sizes="64x64" href="assets/img/brand/favicon-64.png">
<link rel="apple-touch-icon" href="assets/img/brand/apple-touch-icon.png">
<link rel="manifest" href="manifest.webmanifest">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="preload" as="image" href="<?= e($img('kirma-yesil-zeytin')) ?>" fetchpriority="high">
<link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">

<?php foreach ($ld as $block): ?>
<script type="application/ld+json"><?= json_encode($block, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php endforeach; ?>

<?php if ($px = setting('meta_pixel_id')): ?>
<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
fbq('init', <?= json_encode($px) ?>); fbq('track', 'PageView');
</script>
<?php endif; ?>
<?php if ($ga = setting('ga4_id')): ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($ga) ?>"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config',<?= json_encode($ga) ?>);</script>
<?php endif; ?>
</head>
<body>
<!-- ==================== SVG İKON SETİ ==================== -->
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <defs>
    <symbol id="i-truck" viewBox="0 0 24 24"><path d="M3 7h11v9H3zM14 10h4l3 3v3h-7z"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17.5" cy="17.5" r="1.8"/></symbol>
    <symbol id="i-cash" viewBox="0 0 24 24"><rect x="2.5" y="6" width="19" height="12" rx="2.5"/><circle cx="12" cy="12" r="2.6"/><path d="M6 9.5v5M18 9.5v5"/></symbol>
    <symbol id="i-card" viewBox="0 0 24 24"><rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="M2.5 10h19M6.5 15h4"/></symbol>
    <symbol id="i-bank" viewBox="0 0 24 24"><path d="M3 9.5 12 4l9 5.5M4.5 10v8M9.5 10v8M14.5 10v8M19.5 10v8M3 20h18"/></symbol>
    <symbol id="i-leaf" viewBox="0 0 24 24"><path d="M5 19c0-8 5-14 15-15-1 10-7 15-15 15z"/><path d="M5 19 13 11"/></symbol>
    <symbol id="i-phone" viewBox="0 0 24 24"><path d="M5 3.5h3.5l1.8 4.5-2.3 1.5a11 11 0 0 0 6.5 6.5l1.5-2.3 4.5 1.8V19a2 2 0 0 1-2 2A17 17 0 0 1 3 5.5a2 2 0 0 1 2-2z"/></symbol>
    <symbol id="i-wa" viewBox="0 0 24 24"><path stroke="none" fill="currentColor" d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></symbol>
    <symbol id="i-insta" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r=".8" fill="currentColor"/></symbol>
    <symbol id="i-star" viewBox="0 0 24 24"><path fill="currentColor" stroke="none" d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/></symbol>
    <symbol id="i-check" viewBox="0 0 24 24"><path d="m5 12.5 4.5 4.5L19 7.5"/></symbol>
    <symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 3 4.5 6v6c0 4.5 3.2 7.8 7.5 9 4.3-1.2 7.5-4.5 7.5-9V6z"/><path d="m8.8 12 2.3 2.3 4.2-4.6"/></symbol>
    <symbol id="i-box" viewBox="0 0 24 24"><path d="M3.5 7.5 12 3l8.5 4.5v9L12 21l-8.5-4.5z"/><path d="M3.5 7.5 12 12l8.5-4.5M12 12v9"/></symbol>
    <symbol id="i-bag" viewBox="0 0 24 24"><path d="M5 8h14l-1.2 11.2a2 2 0 0 1-2 1.8H8.2a2 2 0 0 1-2-1.8z"/><path d="M9 8V6.5a3 3 0 0 1 6 0V8"/></symbol>
    <symbol id="i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol>
    <symbol id="i-minus" viewBox="0 0 24 24"><path d="M5 12h14"/></symbol>
    <symbol id="i-x" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></symbol>
    <symbol id="i-arrow" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></symbol>
    <symbol id="i-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></symbol>
    <symbol id="i-drop" viewBox="0 0 24 24"><path d="M12 3s6 6.5 6 11a6 6 0 0 1-12 0c0-4.5 6-11 6-11z"/></symbol>
    <symbol id="i-heart" viewBox="0 0 24 24"><path d="M12 20s-7.5-4.6-7.5-10A4.3 4.3 0 0 1 12 7.4 4.3 4.3 0 0 1 19.5 10c0 5.4-7.5 10-7.5 10z"/></symbol>
    <symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
    <symbol id="i-pin" viewBox="0 0 24 24"><path d="M12 21s7-6.2 7-11.5a7 7 0 0 0-14 0C5 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></symbol>
    <symbol id="i-chat" viewBox="0 0 24 24"><path d="M4 5h16v11H9l-5 4z"/></symbol>
    <symbol id="i-gift" viewBox="0 0 24 24"><rect x="3.5" y="8" width="17" height="4" rx="1"/><path d="M5 12v8h14v-8M12 8v12M12 8c-1.5-3-5-3.5-5-1.2C7 8 9 8 12 8zm0 0c1.5-3 5-3.5 5-1.2C17 8 15 8 12 8z"/></symbol>
    <symbol id="i-spark" viewBox="0 0 24 24"><path d="M12 3c.6 4.2 2.8 6.4 7 7-4.2.6-6.4 2.8-7 7-.6-4.2-2.8-6.4-7-7 4.2-.6 6.4-2.8 7-7z"/></symbol>
    <symbol id="i-chev" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></symbol>
    <symbol id="i-hand" viewBox="0 0 24 24"><path d="M7 11V6.5a1.5 1.5 0 0 1 3 0V11m0-1V4.5a1.5 1.5 0 0 1 3 0V10m0 0V5.5a1.5 1.5 0 0 1 3 0V12m0-3a1.5 1.5 0 0 1 3 0v5a7 7 0 0 1-7 7h-.5a6.5 6.5 0 0 1-5.5-3l-3-5a1.5 1.5 0 0 1 2.5-1.6L7 13"/></symbol>
    <symbol id="i-jar" viewBox="0 0 24 24"><rect x="7" y="2.5" width="10" height="3.5" rx="1"/><path d="M8 6h8a3 3 0 0 1 3 3v10a2.5 2.5 0 0 1-2.5 2.5h-9A2.5 2.5 0 0 1 5 19V9a3 3 0 0 1 3-3z"/><path d="M5 11h14M5 17h14"/></symbol>
    <!-- dekoratif zeytin dalı -->
    <symbol id="branch" viewBox="0 0 240 120">
      <path d="M6 96C60 82 118 64 232 18" fill="none" stroke="#5b6b2e" stroke-width="3" stroke-linecap="round"/>
      <g fill="#7d8f45">
        <path d="M40 87c4-18 18-30 34-30-4 17-17 28-34 30z"/>
        <path d="M58 82c-15-6-24-20-22-34 14 6 22 18 22 34z" fill="#93a65a"/>
        <path d="M96 70c5-17 20-27 36-26-5 16-19 26-36 26z"/>
        <path d="M112 66c-14-8-21-22-18-36 13 8 19 21 18 36z" fill="#a3b46b"/>
        <path d="M150 50c6-16 21-25 37-22-6 15-21 23-37 22z"/>
        <path d="M168 44c-13-9-18-24-14-37 12 9 17 23 14 37z" fill="#93a65a"/>
        <path d="M200 31c7-13 20-20 33-17-6 12-19 19-33 17z"/>
      </g>
      <g>
        <ellipse cx="80" cy="88" rx="9" ry="12" transform="rotate(-25 80 88)" fill="#6d8a2a"/>
        <ellipse cx="77" cy="84" rx="2.4" ry="3.6" transform="rotate(-25 77 84)" fill="#c9d98a" opacity=".7"/>
        <ellipse cx="136" cy="70" rx="9" ry="12" transform="rotate(-20 136 70)" fill="#879f34"/>
        <ellipse cx="133" cy="66" rx="2.4" ry="3.6" transform="rotate(-20 133 66)" fill="#dbe7a0" opacity=".7"/>
        <ellipse cx="190" cy="48" rx="8" ry="11" transform="rotate(-15 190 48)" fill="#5e7a22"/>
      </g>
    </symbol>
    <symbol id="olive" viewBox="0 0 40 52">
      <ellipse cx="20" cy="27" rx="15" ry="21" fill="#7f9a2f"/>
      <ellipse cx="20" cy="27" rx="15" ry="21" fill="url(#oliveShade)"/>
      <ellipse cx="14" cy="18" rx="4" ry="7" fill="#e3edb0" opacity=".55"/>
      <path d="M20 6c1-3 4-5 7-5" stroke="#5b6b2e" stroke-width="2" fill="none" stroke-linecap="round"/>
    </symbol>
    <radialGradient id="oliveShade" cx=".35" cy=".3" r=".8"><stop offset="0" stop-color="#b6cc5a" stop-opacity=".0"/><stop offset="1" stop-color="#2f3d0d" stop-opacity=".55"/></radialGradient>
  </defs>
</svg>

<a class="skip" href="#urunler">Ürünlere geç</a>

<!-- ==================== DUYURU ŞERİDİ ==================== -->
<div class="announce" role="note">
  <div class="announce__track">
    <?php for ($k = 0; $k < 2; $k++): ?>
    <span><svg class="ic"><use href="#i-truck"/></svg><?= e(setting('announcement')) ?></span>
    <span><svg class="ic"><use href="#i-leaf"/></svg>Akhisar'dan doğal zeytin</span>
    <span><svg class="ic"><use href="#i-box"/></svg>Sızdırmaz kavanoz, özenli paketleme</span>
    <?php endfor; ?>
  </div>
</div>

<!-- ==================== HEADER ==================== -->
<header class="header" id="top">
  <div class="container header__inner">
    <a href="#top" class="logo" aria-label="<?= e($brand) ?> ana sayfa">
      <img src="assets/img/brand/logo-256.webp" alt="<?= e($brand) ?> logosu" width="52" height="52">
      <span class="logo__text"><strong>Aksar Ege</strong><small><?= e(setting('slogan')) ?></small></span>
    </a>
    <nav class="nav" id="nav" aria-label="Ana menü">
      <a href="#urunler">Ürünler</a>
      <a href="#dogallik">Doğallık</a>
      <a href="#faydalar">Faydaları</a>
      <a href="#sss">S.S.S.</a>
      <a href="#siparis" class="nav__cta">Sipariş Ver</a>
    </nav>
    <div class="header__actions">
      <button class="phone-pill" data-contact type="button" aria-label="Telefon: <?= e($phone) ?>">
        <span class="phone-pill__icon"><svg class="ic"><use href="#i-phone"/></svg></span>
        <span class="phone-pill__txt"><small>Sipariş Hattı</small><?= e($phone) ?></span>
      </button>
      <button class="cart-btn" type="button" data-open-cart aria-label="Sepeti aç">
        <svg class="ic"><use href="#i-bag"/></svg><span class="cart-count" data-cart-count>0</span>
      </button>
      <button class="burger" type="button" aria-label="Menüyü aç" aria-expanded="false" aria-controls="nav"><span></span><span></span><span></span></button>
    </div>
  </div>
</header>

<main>
<!-- ==================== HERO ==================== -->
<section class="hero<?= $heroImg ? ' hero--photo' : '' ?>"<?= $heroImg ? ' style="--hero-img:url(\'' . e($heroImg) . '\')"' : '' ?>>
  <svg class="hero__branch hero__branch--1" aria-hidden="true"><use href="#branch"/></svg>
  <svg class="hero__branch hero__branch--2" aria-hidden="true"><use href="#branch"/></svg>
  <div class="container hero__grid">
    <div class="hero__copy">
      <p class="eyebrow reveal"><svg class="ic"><use href="#i-pin"/></svg> Akhisar / Manisa · Zeytinin başkentinden</p>
      <h1 class="reveal" style="--d:.05s">Ege'nin <em>doğal</em> lezzeti,<br>Akhisar'dan <span class="hl">sofranıza.</span></h1>
      <p class="hero__lead reveal" style="--d:.1s">Dalından toplanan, geleneksel yöntemlerle sabırla hazırlanan yeşil zeytinler. Kavanozu açtığınız an mutfağınıza Ege'nin zeytinlik kokusu yayılır.</p>
      <ul class="hero__chips reveal" style="--d:.15s">
        <li><svg class="ic"><use href="#i-truck"/></svg><?= e($shipText) ?></li>
        <li><svg class="ic"><use href="#i-cash"/></svg>Kapıda Ödeme</li>
        <li><svg class="ic"><use href="#i-leaf"/></svg>%100 Doğal Lezzet</li>
      </ul>
      <div class="hero__cta reveal" style="--d:.2s">
        <a href="#urunler" class="btn btn--primary btn--lg">Zeytinleri Keşfet <svg class="ic"><use href="#i-arrow"/></svg></a>
        <a href="<?= e($waHref) ?>" class="btn btn--wa btn--lg" target="_blank" rel="noopener" data-track="wa"><svg class="ic"><use href="#i-wa"/></svg> WhatsApp'tan Yaz</a>
      </div>
      <p class="hero__note reveal" style="--d:.25s"><svg class="ic"><use href="#i-phone"/></svg> Telefonla sipariş: <button type="button" class="link-btn" data-contact><?= e($phone) ?></button></p>
    </div>

    <div class="hero__visual" aria-hidden="true">
      <div class="hero__glow"></div>
      <figure class="jar jar--l"><?= img_tag($img('izgara-yesil-zeytin'), 'Izgara yeşil zeytin', '', false) ?></figure>
      <figure class="jar jar--r"><?= img_tag($img('biber-dolgulu-yesil-zeytin'), 'Biber dolgulu yeşil zeytin', '', false) ?></figure>
      <figure class="jar jar--c"><?= img_tag($img('kirma-yesil-zeytin'), 'Kırma yeşil zeytin', '', false) ?></figure>
      <div class="seal">
        <svg viewBox="0 0 120 120"><defs><path id="sealPath" d="M60 60m-44 0a44 44 0 1 1 88 0a44 44 0 1 1-88 0"/></defs>
          <text><textPath href="#sealPath" textLength="272" lengthAdjust="spacingAndGlyphs">DOĞAL • KALİTELİ • GÜVENİLİR • </textPath></text></svg>
        <span><svg class="ic"><use href="#i-leaf"/></svg></span>
      </div>
      <svg class="float-olive float-olive--1"><use href="#olive"/></svg>
      <svg class="float-olive float-olive--2"><use href="#olive"/></svg>
      <svg class="float-olive float-olive--3"><use href="#olive"/></svg>
      <div class="hero__tag hero__tag--1"><svg class="ic"><use href="#i-hand"/></svg><span><strong>Elle seçilir</strong>Tane tane, özenle</span></div>
      <div class="hero__tag hero__tag--2"><svg class="ic"><use href="#i-heart"/></svg><span><strong>Misafir favorisi</strong>"Bunu nereden aldın?"</span></div>
    </div>
  </div>
</section>

<!-- ==================== GÜVEN ŞERİDİ ==================== -->
<section class="trust" aria-label="Neden Aksar Ege">
  <div class="container trust__grid">
    <div class="trust__item reveal"><span class="trust__ic"><svg class="ic"><use href="#i-truck"/></svg></span><div><strong><?= e($shipText) ?></strong><small>Türkiye'nin her yerine</small></div></div>
    <div class="trust__item reveal" style="--d:.05s"><span class="trust__ic"><svg class="ic"><use href="#i-cash"/></svg></span><div><strong>Kapıda Ödeme</strong><small>Nakit veya kredi kartı</small></div></div>
    <div class="trust__item reveal" style="--d:.1s"><span class="trust__ic"><svg class="ic"><use href="#i-leaf"/></svg></span><div><strong>Doğal & Geleneksel</strong><small>Ege usulü hazırlanır</small></div></div>
    <div class="trust__item reveal" style="--d:.15s"><span class="trust__ic"><svg class="ic"><use href="#i-shield"/></svg></span><div><strong>Güvenli Paketleme</strong><small>Sızdırmaz, özenli ambalaj</small></div></div>
  </div>
</section>

<!-- ==================== ÜRÜNLER ==================== -->
<section class="section products" id="urunler" aria-labelledby="urunler-baslik">
  <div class="container">
    <header class="section__head reveal">
      <p class="kicker"><svg class="ic"><use href="#i-spark"/></svg> Ege'nin Doğal Lezzetleri</p>
      <h2 id="urunler-baslik">Dalından kavanoza, <em>doğallığıyla</em> zeytin</h2>
      <p>Her biri Akhisar'ın güneşini almış, her biri ayrı bir lezzet hikâyesi. Sofranıza en çok yakışanı seçin; kargosu bizden.</p>
    </header>

    <div class="product-grid">
      <?php foreach ($products as $i => $p): $first = $p['images'][0] ?? ''; $second = $p['images'][1] ?? ''; ?>
      <article class="pcard<?= str_contains($p['slug'], 'set') ? ' pcard--wide' : '' ?> reveal" style="--d:<?= $i * 0.06 ?>s" id="urun-<?= e($p['slug']) ?>" data-product="<?= $p['id'] ?>">
        <button class="pcard__media" type="button" data-quickview="<?= $p['id'] ?>" aria-label="<?= e($p['name']) ?> detaylarını gör">
          <?php if ($p['badge']): ?><span class="pcard__badge"><?= e($p['badge']) ?></span><?php endif; ?>
          <?php if ($p['old_price'] > $p['price'] && $p['price'] > 0): ?><span class="pcard__off">%<?= (int)round((1 - $p['price'] / $p['old_price']) * 100) ?> indirim</span><?php endif; ?>
          <?= img_tag($first, "$brand {$p['name']} - Akhisar doğal zeytin", 'pcard__img', $i > 1) ?>
          <?php if ($second): ?><?= img_tag($second, "$brand {$p['name']} yakın çekim", 'pcard__img pcard__img--alt') ?><?php endif; ?>
          <span class="pcard__zoom">Detayları gör</span>
        </button>
        <div class="pcard__body">
          <p class="pcard__sub"><?= e($p['subtitle']) ?></p>
          <h3 class="pcard__title"><?= e($p['name']) ?></h3>
          <p class="pcard__desc"><?= e($p['short_desc']) ?></p>
          <?php if ($p['features']): ?>
          <ul class="pcard__feats">
            <?php foreach (array_slice($p['features'], 0, 4) as $f): ?><li><svg class="ic"><use href="#i-check"/></svg><?= e($f) ?></li><?php endforeach; ?>
          </ul>
          <?php endif; ?>
          <div class="pcard__foot">
            <div class="price">
              <?php if ($p['price'] > 0): ?>
                <?php if ($p['old_price'] > $p['price']): ?><del><?= money($p['old_price']) ?></del><?php endif; ?>
                <strong><?= money($p['price']) ?></strong>
                <?php if ($p['unit']): ?><small><?= e($p['unit']) ?></small><?php endif; ?>
              <?php else: ?>
                <strong class="price--soon">Fiyat için sorun</strong><small>WhatsApp veya telefonla</small>
              <?php endif; ?>
            </div>
            <?php if ($p['orderable']): ?>
              <div class="pcard__buy">
                <div class="qty" data-qty>
                  <button type="button" data-step="-1" aria-label="Azalt"><svg class="ic"><use href="#i-minus"/></svg></button>
                  <input type="number" value="1" min="1" max="99" inputmode="numeric" aria-label="Adet">
                  <button type="button" data-step="1" aria-label="Arttır"><svg class="ic"><use href="#i-plus"/></svg></button>
                </div>
                <button class="btn btn--primary btn--add" type="button" data-add="<?= $p['id'] ?>"><svg class="ic"><use href="#i-bag"/></svg> Sepete Ekle</button>
              </div>
            <?php elseif (!$p['in_stock']): ?>
              <span class="pcard__oos">Yeni hasat yolda — çok yakında</span>
            <?php else: ?>
              <a class="btn btn--wa" target="_blank" rel="noopener" data-track="wa" href="https://wa.me/<?= $waNumber ?>?text=<?= rawurlencode("Merhaba, {$p['name']} hakkında fiyat ve sipariş bilgisi almak istiyorum.") ?>"><svg class="ic"><use href="#i-wa"/></svg> WhatsApp'tan Sipariş</a>
            <?php endif; ?>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>

    <p class="products__extra reveal"><svg class="ic"><use href="#i-drop"/></svg> <strong>Zeytinyağı ve sarımsak</strong> da var! Doğal zeytinyağımız ve sarımsağımız için <a href="<?= e($waHref) ?>" target="_blank" rel="noopener" data-track="wa">WhatsApp'tan bilgi alın</a> ya da <button type="button" class="link-btn" data-contact>bizi arayın</button>.</p>
  </div>
</section>

<!-- ==================== ALINTI ŞERİDİ ==================== -->
<section class="quote-band" aria-label="Lezzet notu">
  <div class="container">
    <p class="reveal">“Dalından toplandığı günün tazeliği,<br><em>kavanozdaki her tanede.</em>”</p>
  </div>
  <div class="marquee" aria-hidden="true">
    <div class="marquee__track">
      <?php for ($k = 0; $k < 2; $k++): ?>
      <span>Kırma</span><svg><use href="#olive"/></svg><span>Izgara</span><svg><use href="#olive"/></svg><span>Çizik</span><svg><use href="#olive"/></svg><span>Biber Dolgulu</span><svg><use href="#olive"/></svg><span>Zeytinyağı</span><svg><use href="#olive"/></svg><span>Sarımsak</span><svg><use href="#olive"/></svg>
      <?php endfor; ?>
    </div>
  </div>
</section>

<!-- ==================== DOĞALLIK / DALDAN SOFRAYA ==================== -->
<section class="section story" id="dogallik" aria-labelledby="dogallik-baslik">
  <div class="container story__grid">
    <div class="story__media reveal">
      <figure class="story__img story__img--a"><?= img_tag($img('cizik-yesil-zeytin'), 'Çizik yeşil zeytin kavanozu - geleneksel Ege usulü') ?></figure>
      <figure class="story__img story__img--b"><?= img_tag($img('kirma-yesil-zeytin', 1), 'Kırma yeşil zeytin yakın çekim - doğal salamura') ?></figure>
      <div class="story__stamp"><strong>Akhisar</strong><span>Manisa</span></div>
    </div>
    <div class="story__copy">
      <p class="kicker reveal"><svg class="ic"><use href="#i-leaf"/></svg> Maksimum doğallık</p>
      <h2 id="dogallik-baslik" class="reveal">Daldan sofraya,<br><em>doğanın kendi hızında.</em></h2>
      <p class="reveal">Akhisar, yüzyıllardır zeytiniyle anılan bir Ege kasabası. Bizim zeytinlerimiz de bu toprakların bereketinden doğuyor. Acele etmiyoruz; zeytini, doğanın ona verdiği zamanla olgunlaştırıyoruz.</p>
      <ol class="steps">
        <li class="reveal"><span class="steps__n">01</span><div><strong>Hasat</strong><p>Zeytinler en lezzetli oldukları dönemde dalından toplanır.</p></div></li>
        <li class="reveal"><span class="steps__n">02</span><div><strong>Elle Ayıklama</strong><p>Tane tane seçilir; yalnızca sağlam, dolgun zeytinler kavanoza girer.</p></div></li>
        <li class="reveal"><span class="steps__n">03</span><div><strong>Doğal Salamura</strong><p>Kırılır, çizilir ya da ızgaralanır; geleneksel Ege usulüyle sabırla tatlandırılır.</p></div></li>
        <li class="reveal"><span class="steps__n">04</span><div><strong>Özenli Paketleme</strong><p>Sızdırmaz kavanozlarda, kırılmaya karşı korunarak kapınıza yola çıkar.</p></div></li>
      </ol>
      <a href="#siparis" class="btn btn--primary reveal">Doğal Lezzete Ulaşın <svg class="ic"><use href="#i-arrow"/></svg></a>
    </div>
  </div>
</section>

<!-- ==================== MİSAFİR İLTİFATLARI ==================== -->
<section class="section praise" aria-labelledby="praise-baslik">
  <div class="container praise__grid">
    <div class="praise__copy">
      <p class="kicker kicker--light reveal"><svg class="ic"><use href="#i-heart"/></svg> Sofranızın yıldızı olun</p>
      <h2 id="praise-baslik" class="reveal">Siz ikram edin,<br><em>iltifatları toplayın.</em></h2>
      <p class="reveal">Kahvaltı sofranıza bir tabak Aksar Ege zeytini koyduğunuzda, misafirlerinizden duyacaklarınıza şimdiden hazır olun. Çünkü gerçek, doğal bir zeytinin tadı hemen fark edilir.</p>
      <a href="#urunler" class="btn btn--gold reveal">Soframa Lezzet Katmak İstiyorum <svg class="ic"><use href="#i-arrow"/></svg></a>
    </div>
    <div class="praise__bubbles" aria-label="Misafirlerinizden duyabileceğiniz sözler">
      <p class="bubble reveal" style="--d:.0s">“Bu zeytini nereden aldın? <strong>Ben de istiyorum!</strong>”</p>
      <p class="bubble bubble--r reveal" style="--d:.1s">“Tam köydeki gibi… <strong>Çocukluğumun kahvaltıları</strong> aklıma geldi.”</p>
      <p class="bubble reveal" style="--d:.2s">“Izgara zeytin mi bu? <strong>Bayıldım!</strong>”</p>
      <p class="bubble bubble--r reveal" style="--d:.3s">“Market zeytinine hiç benzemiyor, <strong>bambaşka bir tat.</strong>”</p>
      <p class="bubble reveal" style="--d:.4s">“Sofran ne kadar şık olmuş, <strong>o biber dolgulular</strong> harika duruyor.”</p>
    </div>
  </div>
</section>

<!-- ==================== HANGİ ZEYTİN HANGİ SOFRAYA ==================== -->
<section class="section pairing" aria-labelledby="pairing-baslik">
  <div class="container">
    <header class="section__head reveal">
      <p class="kicker"><svg class="ic"><use href="#i-sun"/></svg> Lezzet rehberi</p>
      <h2 id="pairing-baslik">Hangi zeytin, <em>hangi sofraya?</em></h2>
      <p>Kararsız mı kaldınız? Size en uygun zeytini birlikte bulalım.</p>
    </header>
    <div class="pair-grid">
      <?php
      $pairs = [
        ['kirma-yesil-zeytin', 'Pazar Kahvaltısı', 'Çıtır kırma zeytin, köy peyniri ve demli çay. Pazar sabahlarının vazgeçilmez üçlüsü.'],
        ['izgara-yesil-zeytin', 'Meze & Akşam Sofrası', 'Isli aromasıyla meze tabağının yıldızı. Dostlarla uzun akşam sohbetlerine eşlik eder.'],
        ['cizik-yesil-zeytin', 'Simit & Çay Molası', 'Sıkı dokusu ve buruk tadıyla, sıcak simidin yanında tam bir Ege klasiği.'],
        ['biber-dolgulu-yesil-zeytin', 'Salata & Şık Sunum', 'Çekirdeksiz ve renkli; salatada, kanepede, çocukların tabağında hep ilk o biter.'],
      ];
      foreach ($pairs as $j => [$slug, $t, $d]): if (!isset($byslug[$slug])) continue; ?>
      <a class="pair reveal" style="--d:<?= $j * .07 ?>s" href="#urun-<?= e($slug) ?>">
        <span class="pair__img"><?= img_tag($img($slug), $byslug[$slug]['name'], '', true, 800, 1000) ?></span>
        <span class="pair__body"><small><?= e($byslug[$slug]['name']) ?></small><strong><?= e($t) ?></strong><span><?= e($d) ?></span><em>Ürüne git <svg class="ic"><use href="#i-arrow"/></svg></em></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ==================== FAYDALAR ==================== -->
<section class="section benefits" id="faydalar" aria-labelledby="faydalar-baslik">
  <svg class="benefits__branch" aria-hidden="true"><use href="#branch"/></svg>
  <div class="container">
    <header class="section__head reveal">
      <p class="kicker"><svg class="ic"><use href="#i-heart"/></svg> Lezzetli olduğu kadar faydalı</p>
      <h2 id="faydalar-baslik">Akdeniz sofrasının <em>yeşil hazinesi</em></h2>
      <p>Zeytin, binlerce yıldır Ege ve Akdeniz mutfağının baş tacı. Her tanesinde doğanın sunduğu değerli besinler saklı.</p>
    </header>
    <div class="benefit-grid">
      <div class="benefit reveal"><span class="benefit__ic"><svg class="ic"><use href="#i-drop"/></svg></span><h3>Değerli Yağlar</h3><p>Akdeniz beslenmesinin temeli olan tekli doymamış yağ asitlerini (oleik asit) doğal olarak içerir.</p></div>
      <div class="benefit reveal" style="--d:.05s"><span class="benefit__ic"><svg class="ic"><use href="#i-shield"/></svg></span><h3>Doğal Antioksidanlar</h3><p>Polifenoller ve E vitamini gibi doğal antioksidanlar barındırır.</p></div>
      <div class="benefit reveal" style="--d:.1s"><span class="benefit__ic"><svg class="ic"><use href="#i-leaf"/></svg></span><h3>Lifli & Doyurucu</h3><p>Birkaç tanesi bile tok tutar; ara öğünler için doğal ve hafif bir seçenek.</p></div>
      <div class="benefit reveal" style="--d:.15s"><span class="benefit__ic"><svg class="ic"><use href="#i-spark"/></svg></span><h3>Mineral Kaynağı</h3><p>Demir, kalsiyum ve bakır gibi mineraller içerir.</p></div>
      <div class="benefit reveal" style="--d:.2s"><span class="benefit__ic"><svg class="ic"><use href="#i-sun"/></svg></span><h3>Gerçek Zeytin Tadı</h3><p>Geleneksel yöntemlerle hazırlandığı için zeytinin kendi doğal tadını ve aromasını korur.</p></div>
      <div class="benefit reveal" style="--d:.25s"><span class="benefit__ic"><svg class="ic"><use href="#i-heart"/></svg></span><h3>Her Sofraya Uygun</h3><p>Kahvaltıdan mezeye, salatadan pizzaya; her tabağa lezzet ve renk katar.</p></div>
    </div>
    <p class="benefits__note">* Genel beslenme bilgisidir; tedavi amaçlı değildir.</p>
  </div>
</section>

<?php if ($reviews): ?>
<!-- ==================== MÜŞTERİ YORUMLARI ==================== -->
<section class="section reviews" aria-labelledby="yorum-baslik">
  <div class="container">
    <header class="section__head reveal">
      <p class="kicker"><svg class="ic"><use href="#i-star"/></svg> Müşterilerimiz ne diyor?</p>
      <h2 id="yorum-baslik">Bir kez deneyen, <em>bir daha sipariş veriyor</em></h2>
    </header>
    <div class="review-grid">
      <?php foreach ($reviews as $k => $r): ?>
      <figure class="review reveal" style="--d:<?= ($k % 3) * .06 ?>s">
        <div class="stars" aria-label="<?= (int)$r['rating'] ?> yıldız"><?php for ($x = 0; $x < 5; $x++): ?><svg class="ic<?= $x < (int)$r['rating'] ? '' : ' off' ?>"><use href="#i-star"/></svg><?php endfor; ?></div>
        <blockquote>“<?= e($r['text']) ?>”</blockquote>
        <figcaption><strong><?= e($r['name']) ?></strong><?= $r['city'] ? ' · ' . e($r['city']) : '' ?></figcaption>
      </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ==================== NASIL SİPARİŞ VERİLİR ==================== -->
<section class="section howto" aria-labelledby="howto-baslik">
  <div class="container">
    <header class="section__head reveal">
      <p class="kicker"><svg class="ic"><use href="#i-clock"/></svg> 1 dakikada sipariş</p>
      <h2 id="howto-baslik">Ege'nin lezzeti <em>3 adımda</em> kapınızda</h2>
    </header>
    <div class="howto__grid">
      <div class="howto__step reveal"><span>1</span><svg class="ic"><use href="#i-jar"/></svg><h3>Zeytininizi seçin</h3><p>Dilediğiniz çeşitten dilediğiniz kadar sepetinize ekleyin.</p></div>
      <div class="howto__step reveal" style="--d:.08s"><span>2</span><svg class="ic"><use href="#i-pin"/></svg><h3>Adresinizi yazın</h3><p>Ödeme yönteminizi seçin; isterseniz sipariş notunuzu ekleyin.</p></div>
      <div class="howto__step reveal" style="--d:.16s"><span>3</span><svg class="ic"><use href="#i-truck"/></svg><h3>Kapıda teslim alın</h3><p>Siparişiniz özenle paketlenir, kapınıza kadar <?= $freeShipping ? 'ücretsiz ' : '' ?>gelir.</p></div>
    </div>
  </div>
</section>

<!-- ==================== SİPARİŞ ==================== -->
<section class="section order" id="siparis" aria-labelledby="siparis-baslik">
  <div class="container">
    <header class="section__head reveal">
      <p class="kicker"><svg class="ic"><use href="#i-bag"/></svg> Güvenli sipariş</p>
      <h2 id="siparis-baslik">Siparişinizi <em>hemen</em> oluşturun</h2>
      <p>Kapıda nakit, kapıda kredi kartı ya da havale/EFT ile ödeyin. Peşin ödeme zorunluluğu yok.</p>
    </header>

    <?php if (!$orderable): ?>
    <div class="order__soon reveal">
      <svg class="ic"><use href="#i-wa"/></svg>
      <h3>Siparişlerinizi şimdilik telefon ve WhatsApp üzerinden alıyoruz</h3>
      <p>Güncel fiyatlarımızı öğrenmek ve hemen sipariş vermek için bize yazın ya da arayın; birkaç dakika içinde dönüş yapalım.</p>
      <div class="order__soon-cta">
        <a href="<?= e($waHref) ?>" class="btn btn--wa btn--lg" target="_blank" rel="noopener" data-track="wa"><svg class="ic"><use href="#i-wa"/></svg> WhatsApp ile Sipariş</a>
        <button type="button" class="btn btn--outline btn--lg" data-contact><svg class="ic"><use href="#i-phone"/></svg> <?= e($phone) ?></button>
      </div>
    </div>
    <?php else: ?>
    <form class="order__grid" id="orderForm" novalidate>
      <div class="order__form card reveal">
        <h3 class="card__title"><span>1</span> Teslimat bilgileri</h3>
        <div class="field-row">
          <label class="field"><span>Ad Soyad *</span><input name="name" autocomplete="name" required maxlength="80" placeholder="Adınız ve soyadınız"></label>
          <label class="field"><span>Telefon *</span><input name="phone" type="tel" autocomplete="tel" inputmode="tel" required maxlength="20" placeholder="05XX XXX XX XX"></label>
        </div>
        <div class="field-row">
          <label class="field"><span>İl *</span>
            <select name="city" required autocomplete="address-level1">
              <option value="">İl seçin</option>
              <?php foreach (turkish_cities() as $c): ?><option><?= e($c) ?></option><?php endforeach; ?>
            </select>
          </label>
          <label class="field"><span>İlçe *</span><input name="district" required maxlength="60" autocomplete="address-level2" placeholder="İlçe"></label>
        </div>
        <label class="field"><span>Açık adres *</span><textarea name="address" rows="2" required maxlength="400" autocomplete="street-address" placeholder="Mahalle, sokak, bina ve daire no"></textarea></label>
        <label class="field"><span>E-posta <em>(isteğe bağlı)</em></span><input name="email" type="email" autocomplete="email" maxlength="120" placeholder="ornek@mail.com"></label>

        <h3 class="card__title"><span>2</span> Ödeme yöntemi</h3>
        <div class="paygrid" role="radiogroup" aria-label="Ödeme yöntemi">
          <?php $firstPay = true; foreach ($payments as $key => $pm): ?>
          <label class="pay">
            <input type="radio" name="payment" value="<?= e($key) ?>"<?= $firstPay ? ' checked' : '' ?>>
            <span class="pay__box">
              <span class="pay__ic"><svg class="ic"><use href="#i-<?= e($pm['icon']) ?>"/></svg></span>
              <span class="pay__txt"><strong><?= e($pm['label']) ?></strong><small><?= e($pm['desc']) ?></small></span>
              <?php if (!empty($pm['tag'])): ?><span class="pay__tag"><?= e($pm['tag']) ?></span><?php endif; ?>
              <span class="pay__radio"></span>
            </span>
          </label>
          <?php $firstPay = false; endforeach; ?>
        </div>

        <h3 class="card__title"><span>3</span> Sipariş notu <em>(isteğe bağlı)</em></h3>
        <label class="field"><span class="sr">Sipariş notu</span><textarea name="note" rows="3" maxlength="600" placeholder="Örn: Hediye paketi olsun, öğleden sonra teslim edilsin, kapı zili çalışmıyor…"></textarea></label>
        <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
      </div>

      <aside class="order__summary card reveal" style="--d:.08s">
        <h3 class="card__title"><svg class="ic"><use href="#i-bag"/></svg> Sipariş özeti</h3>
        <div class="summary__items" data-summary-items>
          <p class="summary__empty">Sepetiniz henüz boş. <a href="#urunler">Zeytinlere göz atın</a> ya da aşağıdan hızlıca ekleyin.</p>
        </div>
        <div class="quick-add" data-quick-add>
          <p>Hızlı ekle:</p>
          <div class="quick-add__list">
            <?php foreach ($orderable as $p): ?>
            <button type="button" class="chip" data-add="<?= $p['id'] ?>" data-qty-fixed="1"><svg class="ic"><use href="#i-plus"/></svg><?= e($p['name']) ?></button>
            <?php endforeach; ?>
          </div>
        </div>
        <dl class="totals">
          <div><dt>Ara toplam</dt><dd data-t="subtotal">0,00 ₺</dd></div>
          <div data-row="discount" hidden><dt>Havale/EFT indirimi</dt><dd data-t="discount">−0,00 ₺</dd></div>
          <div><dt>Kargo</dt><dd data-t="shipping"><span class="free">Ücretsiz</span></dd></div>
          <div data-row="cod" hidden><dt>Kapıda ödeme bedeli</dt><dd data-t="cod">0,00 ₺</dd></div>
          <div class="totals__grand"><dt>Toplam</dt><dd data-t="total">0,00 ₺</dd></div>
        </dl>
        <label class="consent">
          <input type="checkbox" name="consent" required>
          <span><button type="button" class="link-btn" data-legal="distance_contract">Mesafeli Satış Sözleşmesi</button>'ni ve <button type="button" class="link-btn" data-legal="kvkk_text">KVKK Aydınlatma Metni</button>'ni okudum, onaylıyorum.</span>
        </label>
        <p class="form-error" data-form-error role="alert" hidden></p>
        <button class="btn btn--primary btn--lg btn--block btn--submit" type="submit" data-submit>
          <span class="btn__label"><svg class="ic"><use href="#i-check"/></svg> Siparişi Tamamla</span>
          <span class="btn__spinner" aria-hidden="true"></span>
        </button>
        <ul class="summary__trust">
          <li><svg class="ic"><use href="#i-shield"/></svg> Bilgileriniz güvende, yalnızca teslimat için kullanılır.</li>
          <li><svg class="ic"><use href="#i-phone"/></svg> Siparişiniz alındıktan sonra onay için sizinle iletişime geçilir.</li>
          <li><svg class="ic"><use href="#i-box"/></svg> Sızdırmaz kavanozlarda, özenle paketlenerek gönderilir.</li>
        </ul>
      </aside>
    </form>
    <?php endif; ?>
  </div>
</section>

<!-- ==================== SSS ==================== -->
<section class="section faq" id="sss" aria-labelledby="sss-baslik">
  <div class="container faq__grid">
    <header class="section__head section__head--left reveal">
      <p class="kicker"><svg class="ic"><use href="#i-chat"/></svg> Merak edilenler</p>
      <h2 id="sss-baslik">Sıkça sorulan <em>sorular</em></h2>
      <p>Aklınıza takılan başka bir şey mi var? Bir telefon kadar yakınız.</p>
      <div class="faq__cta">
        <button type="button" class="btn btn--outline" data-contact><svg class="ic"><use href="#i-phone"/></svg> <?= e($phone) ?></button>
      </div>
    </header>
    <div class="faq__list">
      <?php foreach ($faqs as $k => $f): ?>
      <details class="faq__item reveal" style="--d:<?= $k * .04 ?>s"<?= $k === 0 ? ' open' : '' ?>>
        <summary><?= e($f[0]) ?><svg class="ic"><use href="#i-chev"/></svg></summary>
        <div class="faq__a"><p><?= e($f[1]) ?></p></div>
      </details>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ==================== SON ÇAĞRI ==================== -->
<section class="final-cta" aria-labelledby="final-baslik">
  <svg class="final-cta__branch" aria-hidden="true"><use href="#branch"/></svg>
  <div class="container final-cta__inner reveal">
    <img src="assets/img/brand/logo-256.webp" alt="" width="96" height="96" loading="lazy" class="final-cta__logo">
    <h2 id="final-baslik">Yarın sabah kahvaltınızda<br><em>Ege'nin doğal lezzeti</em> olsun.</h2>
    <p>Akhisar'dan Türkiye'nin her yerine <?= $freeShipping ? 'ücretsiz kargo, ' : '' ?>kapıda ödeme kolaylığıyla.</p>
    <div class="final-cta__btns">
      <a href="#siparis" class="btn btn--gold btn--lg">Hemen Sipariş Ver <svg class="ic"><use href="#i-arrow"/></svg></a>
      <button type="button" class="btn btn--ghost btn--lg" data-contact><svg class="ic"><use href="#i-phone"/></svg> <?= e($phone) ?></button>
    </div>
  </div>
</section>
</main>

<!-- ==================== FOOTER ==================== -->
<footer class="footer">
  <div class="container footer__grid">
    <div class="footer__brand">
      <img src="assets/img/brand/logo-256.webp" alt="<?= e($brand) ?> logosu" width="72" height="72" loading="lazy">
      <p><strong><?= e(setting('company_name')) ?></strong><br>🌿 Ege'nin doğal lezzetleri<br>🫒 Doğallığıyla Zeytin • Zeytinyağı • Sarımsak<br>📍 Akhisar'dan Türkiye'nin her yerine</p>
    </div>
    <div>
      <h3>Ürünlerimiz</h3>
      <ul>
        <?php foreach ($products as $p): ?><li><a href="#urun-<?= e($p['slug']) ?>"><?= e($p['name']) ?></a></li><?php endforeach; ?>
      </ul>
    </div>
    <div>
      <h3>İletişim</h3>
      <ul>
        <li><button type="button" class="link-btn" data-contact><svg class="ic"><use href="#i-phone"/></svg> <?= e($phone) ?></button></li>
        <li><a href="<?= e($waHref) ?>" target="_blank" rel="noopener" data-track="wa"><svg class="ic"><use href="#i-wa"/></svg> WhatsApp Sipariş Hattı</a></li>
        <?php if ($insta): ?><li><a href="https://www.instagram.com/<?= e($insta) ?>/" target="_blank" rel="noopener"><svg class="ic"><use href="#i-insta"/></svg> @<?= e($insta) ?></a></li><?php endif; ?>
        <?php if (setting('email')): ?><li><a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a></li><?php endif; ?>
        <li><svg class="ic"><use href="#i-pin"/></svg> <?= e(setting('address')) ?></li>
      </ul>
    </div>
    <div>
      <h3>Bilgi</h3>
      <ul>
        <li><button type="button" class="link-btn" data-legal="distance_contract">Mesafeli Satış Sözleşmesi</button></li>
        <li><button type="button" class="link-btn" data-legal="return_policy">İade & Değişim</button></li>
        <li><button type="button" class="link-btn" data-legal="kvkk_text">KVKK Aydınlatma Metni</button></li>
      </ul>
      <div class="footer__pay"><span><svg class="ic"><use href="#i-cash"/></svg> Kapıda Nakit</span><span><svg class="ic"><use href="#i-card"/></svg> Kapıda Kart</span><span><svg class="ic"><use href="#i-bank"/></svg> Havale/EFT</span></div>
    </div>
  </div>
  <div class="container footer__bottom">
    <p>© <?= date('Y') ?> <?= e(setting('company_name')) ?> · Tüm hakları saklıdır.</p>
    <a href="#top" class="to-top">Başa dön ↑</a>
  </div>
</footer>

<!-- ==================== SABİT ÖĞELER ==================== -->
<a class="wa-float" href="<?= e($waHref) ?>" target="_blank" rel="noopener" aria-label="WhatsApp ile yazın" data-track="wa">
  <svg class="ic"><use href="#i-wa"/></svg><span class="wa-float__tip">Sorunuz mu var? Yazın 👋</span>
</a>

<nav class="mbar" aria-label="Hızlı işlemler">
  <button type="button" data-contact><svg class="ic"><use href="#i-phone"/></svg><span>Ara</span></button>
  <a href="<?= e($waHref) ?>" target="_blank" rel="noopener" data-track="wa"><svg class="ic"><use href="#i-wa"/></svg><span>WhatsApp</span></a>
  <a href="#siparis" class="mbar__cta"><svg class="ic"><use href="#i-bag"/></svg><span data-mbar-label>Sipariş Ver</span></a>
</nav>

<!-- İletişim seçimi -->
<div class="modal" id="contactModal" role="dialog" aria-modal="true" aria-labelledby="contactTitle" hidden>
  <div class="modal__backdrop" data-close></div>
  <div class="modal__panel modal__panel--sm">
    <button class="modal__x" type="button" data-close aria-label="Kapat"><svg class="ic"><use href="#i-x"/></svg></button>
    <img src="assets/img/brand/logo-256.webp" alt="" width="72" height="72" class="contact__logo">
    <h2 id="contactTitle">Size nasıl ulaşalım?</h2>
    <p class="contact__num"><?= e($phone) ?></p>
    <div class="contact__opts">
      <a class="btn btn--primary btn--lg btn--block" href="<?= e($telHref) ?>" data-track="call"><svg class="ic"><use href="#i-phone"/></svg> Şimdi Ara</a>
      <a class="btn btn--wa btn--lg btn--block" href="<?= e($waHref) ?>" target="_blank" rel="noopener" data-track="wa"><svg class="ic"><use href="#i-wa"/></svg> WhatsApp ile İletişime Geç</a>
    </div>
    <p class="contact__hint">Siparişlerinizi telefonla veya WhatsApp üzerinden de alıyoruz.</p>
  </div>
</div>

<!-- Ürün detay -->
<div class="modal" id="productModal" role="dialog" aria-modal="true" aria-labelledby="pmTitle" hidden>
  <div class="modal__backdrop" data-close></div>
  <div class="modal__panel modal__panel--lg">
    <button class="modal__x" type="button" data-close aria-label="Kapat"><svg class="ic"><use href="#i-x"/></svg></button>
    <div class="pm" data-pm></div>
  </div>
</div>

<!-- Yasal metinler -->
<div class="modal" id="legalModal" role="dialog" aria-modal="true" aria-labelledby="legalTitle" hidden>
  <div class="modal__backdrop" data-close></div>
  <div class="modal__panel">
    <button class="modal__x" type="button" data-close aria-label="Kapat"><svg class="ic"><use href="#i-x"/></svg></button>
    <h2 id="legalTitle"></h2>
    <div class="legal__body"></div>
  </div>
</div>
<template id="legal-distance_contract" data-title="Mesafeli Satış Sözleşmesi"><?= nl2br(e(legal_text('distance_contract'))) ?></template>
<template id="legal-kvkk_text" data-title="KVKK Aydınlatma Metni"><?= nl2br(e(legal_text('kvkk_text'))) ?></template>
<template id="legal-return_policy" data-title="İade & Değişim Koşulları"><?= nl2br(e(legal_text('return_policy'))) ?></template>

<!-- Sipariş başarılı -->
<div class="modal" id="successModal" role="dialog" aria-modal="true" aria-labelledby="okTitle" hidden>
  <div class="modal__backdrop" data-close></div>
  <div class="modal__panel modal__panel--sm success">
    <button class="modal__x" type="button" data-close aria-label="Kapat"><svg class="ic"><use href="#i-x"/></svg></button>
    <div class="success__check"><svg viewBox="0 0 52 52"><circle cx="26" cy="26" r="24"/><path d="m15 27 7 7 15-16"/></svg></div>
    <h2 id="okTitle">Siparişiniz alındı! 🫒</h2>
    <p>Sipariş numaranız: <strong data-ok-no></strong></p>
    <p class="success__total">Toplam: <strong data-ok-total></strong></p>
    <div class="success__bank" data-ok-bank hidden></div>
    <p class="success__msg">Teşekkür ederiz! Siparişinizi onaylamak için en kısa sürede sizinle iletişime geçeceğiz. Ege'nin doğal lezzeti yakında sofranızda.</p>
    <a class="btn btn--wa btn--block" data-ok-wa target="_blank" rel="noopener"><svg class="ic"><use href="#i-wa"/></svg> Siparişimi WhatsApp'tan da İlet</a>
  </div>
</div>

<!-- Sepet çekmecesi -->
<div class="drawer" id="cartDrawer" aria-hidden="true">
  <div class="drawer__backdrop" data-close-cart></div>
  <aside class="drawer__panel" role="dialog" aria-modal="true" aria-labelledby="cartTitle">
    <header class="drawer__head">
      <h2 id="cartTitle"><svg class="ic"><use href="#i-bag"/></svg> Sepetim</h2>
      <button type="button" class="modal__x" data-close-cart aria-label="Kapat"><svg class="ic"><use href="#i-x"/></svg></button>
    </header>
    <div class="drawer__ship" data-ship-progress></div>
    <div class="drawer__items" data-drawer-items></div>
    <footer class="drawer__foot">
      <div class="drawer__total"><span>Ara toplam</span><strong data-drawer-total>0,00 ₺</strong></div>
      <a href="#siparis" class="btn btn--primary btn--lg btn--block" data-goto-checkout>Siparişi Tamamla <svg class="ic"><use href="#i-arrow"/></svg></a>
      <p class="drawer__note"><svg class="ic"><use href="#i-cash"/></svg> Kapıda ödeme · <svg class="ic"><use href="#i-truck"/></svg> <?= e($shipText) ?></p>
    </footer>
  </aside>
</div>

<div class="toast" role="status" aria-live="polite" hidden></div>

<script>window.AE = <?= json_encode($clientData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;</script>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
