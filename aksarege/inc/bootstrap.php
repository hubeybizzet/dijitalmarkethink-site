<?php
/**
 * Aksar Ege — çekirdek dosya
 * Veritabanı (SQLite), ayarlar, ürünler ve yardımcı fonksiyonlar.
 */
declare(strict_types=1);

mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Istanbul');

define('APP_ROOT', dirname(__DIR__));
define('DATA_DIR', APP_ROOT . '/data');
define('UPLOAD_DIR', APP_ROOT . '/uploads');
if (!is_dir(DATA_DIR)) { @mkdir(DATA_DIR, 0755, true); }
if (!is_dir(UPLOAD_DIR)) { @mkdir(UPLOAD_DIR, 0755, true); }

// Veritabanı dosyasının adı ilk çalıştırmada rastgele üretilir; böylece sunucu
// .htaccess desteklemese bile dosya adresi tahmin edilemez.
(function () {
    $cfg = DATA_DIR . '/db-name.php';
    if (!is_file($cfg)) {
        $name = 'aksarege-' . bin2hex(random_bytes(12)) . '.sqlite';
        file_put_contents($cfg, "<?php return '$name';\n", LOCK_EX);
    }
    define('DB_FILE', DATA_DIR . '/' . basename((string)(include $cfg)));
})();

/* ------------------------------------------------------------------ */
/* Veritabanı                                                          */
/* ------------------------------------------------------------------ */
function db(): PDO
{
    static $pdo = null;
    if ($pdo) { return $pdo; }
    $fresh = !file_exists(DB_FILE);
    $pdo = new PDO('sqlite:' . DB_FILE, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode = WAL; PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 5000;');
    migrate($pdo);
    if ($fresh) { seed($pdo); }
    return $pdo;
}

function migrate(PDO $pdo): void
{
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS settings (
        key   TEXT PRIMARY KEY,
        value TEXT
    );
    CREATE TABLE IF NOT EXISTS products (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        slug        TEXT UNIQUE,
        name        TEXT NOT NULL,
        subtitle    TEXT DEFAULT '',
        short_desc  TEXT DEFAULT '',
        description TEXT DEFAULT '',
        features    TEXT DEFAULT '[]',
        pairing     TEXT DEFAULT '',
        price       REAL DEFAULT 0,
        old_price   REAL DEFAULT 0,
        unit        TEXT DEFAULT '',
        badge       TEXT DEFAULT '',
        images      TEXT DEFAULT '[]',
        active      INTEGER DEFAULT 1,
        in_stock    INTEGER DEFAULT 1,
        sort        INTEGER DEFAULT 0,
        updated_at  TEXT
    );
    CREATE TABLE IF NOT EXISTS orders (
        id             INTEGER PRIMARY KEY AUTOINCREMENT,
        order_no       TEXT UNIQUE,
        created_at     TEXT,
        status         TEXT DEFAULT 'yeni',
        customer_name  TEXT,
        phone          TEXT,
        email          TEXT,
        city           TEXT,
        district       TEXT,
        address        TEXT,
        payment_method TEXT,
        note           TEXT,
        items          TEXT,
        subtotal       REAL,
        discount       REAL,
        shipping       REAL,
        cod_fee        REAL,
        total          REAL,
        admin_note     TEXT DEFAULT '',
        tracking_no    TEXT DEFAULT '',
        ip             TEXT,
        user_agent     TEXT
    );
    CREATE TABLE IF NOT EXISTS reviews (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        name       TEXT,
        city       TEXT,
        text       TEXT,
        rating     INTEGER DEFAULT 5,
        active     INTEGER DEFAULT 1,
        created_at TEXT
    );
    CREATE TABLE IF NOT EXISTS login_attempts (
        ip         TEXT,
        created_at INTEGER
    );
    CREATE INDEX IF NOT EXISTS idx_orders_created ON orders(created_at);
    CREATE INDEX IF NOT EXISTS idx_orders_status  ON orders(status);
    ");
}

function default_settings(): array
{
    return [
        'brand_name'        => 'Aksar Ege',
        'company_name'      => 'Aksar Ege Gıda Ltd. Şti.',
        'slogan'            => "Ege'nin Doğal Lezzetleri",
        'phone'             => '0535 265 75 19',
        'whatsapp'          => '905352657519',
        'instagram'         => 'aksaregegida',
        'email'             => '',
        'notify_email'      => '',
        'address'           => 'Akhisar / Manisa',
        'announcement'      => "Türkiye'nin her yerine ÜCRETSİZ KARGO • Kapıda nakit veya kredi kartıyla ödeme",
        'bank_name'         => '',
        'account_holder'    => 'Aksar Ege Gıda Ltd. Şti.',
        'iban'              => '',
        'pay_cod_cash'      => '1',
        'pay_cod_card'      => '1',
        'pay_transfer'      => '1',
        'cod_fee'           => '0',
        'transfer_discount' => '0',
        'shipping_fee'      => '0',
        'free_shipping_min' => '0',
        'min_order'         => '0',
        'meta_pixel_id'     => '',
        'ga4_id'            => '',
        'site_url'          => '',
        'hero_image'        => '',
        'seo_title'         => 'Akhisar Doğal Yeşil Zeytin | Aksar Ege – Ücretsiz Kargo',
        'seo_description'   => "Akhisar'dan sofranıza doğal kırma, çizik, ızgara ve biber dolgulu yeşil zeytin. Ege'nin doğal lezzetleri ücretsiz kargo ve kapıda ödeme seçeneğiyle Türkiye'nin her yerine.",
        'admin_password'    => '',
        'distance_contract' => '',
        'kvkk_text'         => '',
        'return_policy'     => '',
    ];
}

function seed(PDO $pdo): void
{
    $ins = $pdo->prepare('INSERT OR IGNORE INTO settings(key,value) VALUES(?,?)');
    foreach (default_settings() as $k => $v) { $ins->execute([$k, $v]); }

    $p = 'assets/img/products/';
    $products = [
        [
            'slug' => 'kirma-yesil-zeytin',
            'name' => 'Kırma Yeşil Zeytin',
            'subtitle' => 'Crushed Green Olives',
            'badge' => 'En Çok Tercih Edilen',
            'short_desc' => "Akhisar bahçelerinden toplanan taze yeşil zeytinler tek tek kırılır, doğal salamurasında sabırla tatlanır. Çıtır dokusu ve ferah aromasıyla kahvaltının yıldızı.",
            'description' => "Hasat mevsiminde dalından toplanan yeşil zeytinler, aynı gün ayıklanır ve tek tek kırılarak doğal salamuraya yatırılır. Kırılan zeytin, salamurayı içine çeker; acılığını yavaş yavaş bırakır ve ortaya o meşhur çıtır, ferah, hafif ekşimsi Ege lezzeti çıkar.\n\nBir kavanoz açtığınızda mutfağınıza Ege'nin taze zeytinlik kokusu yayılır. Üzerine biraz zeytinyağı, kekik ve limon gezdirin; kahvaltı sofranız bir anda köy sofrasına dönüşsün.",
            'features' => ['Taze hasattan', 'Tek tek kırılır', 'Doğal salamura', 'Çıtır doku'],
            'pairing' => 'Kahvaltı • Köy peyniri • Domates & salatalık • Zeytinyağı & kekik',
            'images' => [$p.'kirma-yesil-zeytin-1.webp', $p.'kirma-yesil-zeytin-2.webp'],
            'sort' => 1,
        ],
        [
            'slug' => 'izgara-yesil-zeytin',
            'name' => 'Izgara Yeşil Zeytin',
            'subtitle' => 'Grilled Green Olives',
            'badge' => 'Gurme Seçim',
            'short_desc' => "Ateşte hafifçe ızgaralanan yeşil zeytinler, isli ve dumanlı eşsiz bir aromaya kavuşur. Mezelerin ve rakı sofralarının gözdesi.",
            'description' => "Seçilmiş iri yeşil zeytinler, ızgarada tek tek nar gibi kızartılır. Üzerinde kalan ızgara izleri, zeytine hafif isli, derin ve iştah açan bir aroma kazandırır.\n\nMeze tabağına koyduğunuz anda göz önce izlere, sonra tadına takılır. Misafirleriniz tek bir taneyle yetinemeyecek; tabak boşaldıkça \"Bunu nereden aldın?\" sorusu gelecek.",
            'features' => ['Tek tek ızgaralanır', 'İsli & dumanlı aroma', 'İri taneler', 'Meze dostu'],
            'pairing' => 'Meze tabağı • Peynir tabağı • Salata • Pizza & makarna',
            'images' => [$p.'izgara-yesil-zeytin.webp'],
            'sort' => 2,
        ],
        [
            'slug' => 'cizik-yesil-zeytin',
            'name' => 'Çizik Yeşil Zeytin',
            'subtitle' => 'Scratched Green Olives',
            'badge' => 'Geleneksel Tarif',
            'short_desc' => "Her biri çizilerek salamurasını iliklerine kadar çeken, sıkı dokulu ve hafif buruk Ege klasiği. Annelerimizin kurduğu zeytinlerin tadı.",
            'description' => "Çizik zeytin, Ege'de nesilden nesile aktarılan geleneksel bir yöntemle hazırlanır: her tanenin üzerine ince çizikler atılır, böylece zeytin doğal salamurasını yavaş yavaş, derinlemesine çeker.\n\nSonuç; sıkı, diri ve hafif buruk bir tat. Tıpkı büyüklerimizin evde kavanoz kavanoz kurduğu zeytinler gibi. Bir kez tadan, market zeytinine geri dönmek istemiyor.",
            'features' => ['Geleneksel yöntem', 'Sıkı & diri doku', 'Doğal salamura', 'Ev yapımı tadı'],
            'pairing' => 'Kahvaltı • Simit & çay • Gözleme • Ara öğün',
            'images' => [$p.'cizik-yesil-zeytin.webp'],
            'sort' => 3,
        ],
        [
            'slug' => 'biber-dolgulu-yesil-zeytin',
            'name' => 'Biber Dolgulu Yeşil Zeytin',
            'subtitle' => 'Red Pepper Green Olives',
            'badge' => 'Sofraların Neşesi',
            'short_desc' => "Çekirdeği çıkarılmış iri yeşil zeytinlerin içi kırmızı biberle doldurulur. Rengiyle sofrayı süsler, tadıyla herkesi kendine hayran bırakır.",
            'description' => "İri ve dolgun yeşil zeytinlerin çekirdekleri özenle çıkarılır, yerine kırmızı biber yerleştirilir. Yeşilin ve kırmızının buluştuğu her tane, sofranıza hem renk hem lezzet katar.\n\nÇekirdeksiz olduğu için çocuklar bile severek yer. Kahvaltıda, salatada, mezede ya da kokteyl sunumlarında; tabağa koyduğunuz an göz zevki başlar.",
            'features' => ['Çekirdeksiz', 'Kırmızı biber dolgulu', 'İri taneler', 'Sunumda şık'],
            'pairing' => 'Salatalar • Meze & kanepe • Kahvaltı • Kokteyl sunumu',
            'images' => [$p.'biber-dolgulu-yesil-zeytin.webp'],
            'sort' => 4,
        ],
        [
            'slug' => 'tadim-seti',
            'name' => '4\'lü Ege Tadım Seti',
            'subtitle' => 'Kırma • Izgara • Çizik • Biber Dolgulu',
            'badge' => 'Avantajlı Set',
            'short_desc' => "Kararsız kalanlara: dört farklı Ege lezzeti tek pakette. Kahvaltıdan mezeye her sofraya bir zeytin, her misafire bir iltifat.",
            'description' => "Kırma, Izgara, Çizik ve Biber Dolgulu yeşil zeytinlerimizin hepsini birden denemek isteyenler için hazırladığımız set. Sofranızda dört farklı tabak, dört farklı lezzet.\n\nKendinize de alabilirsiniz, sevdiklerinize Ege'den doğal bir hediye olarak da gönderebilirsiniz.",
            'features' => ['4 farklı lezzet', 'Hediyelik', 'Avantajlı fiyat', 'Ücretsiz kargo'],
            'pairing' => 'Kalabalık kahvaltılar • Misafir sofraları • Hediye',
            'images' => [$p.'tadim-seti.webp'],
            'sort' => 5,
        ],
    ];
    $st = $pdo->prepare('INSERT INTO products(slug,name,subtitle,short_desc,description,features,pairing,price,old_price,unit,badge,images,active,in_stock,sort,updated_at)
                          VALUES(:slug,:name,:subtitle,:short_desc,:description,:features,:pairing,0,0,:unit,:badge,:images,1,1,:sort,:t)');
    foreach ($products as $pr) {
        $st->execute([
            ':slug' => $pr['slug'], ':name' => $pr['name'], ':subtitle' => $pr['subtitle'],
            ':short_desc' => $pr['short_desc'], ':description' => $pr['description'],
            ':features' => json_encode($pr['features'], JSON_UNESCAPED_UNICODE),
            ':pairing' => $pr['pairing'], ':unit' => $pr['slug'] === 'tadim-seti' ? '4 Kavanoz' : 'Pet Kavanoz',
            ':badge' => $pr['badge'], ':images' => json_encode($pr['images'], JSON_UNESCAPED_SLASHES),
            ':sort' => $pr['sort'], ':t' => date('c'),
        ]);
    }
}

/* ------------------------------------------------------------------ */
/* Ayarlar                                                             */
/* ------------------------------------------------------------------ */
function settings(bool $refresh = false): array
{
    static $cache = null;
    if ($cache !== null && !$refresh) { return $cache; }
    $rows = db()->query('SELECT key, value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
    $cache = array_merge(default_settings(), $rows);
    return $cache;
}

function setting(string $key, string $default = ''): string
{
    $s = settings();
    return isset($s[$key]) && $s[$key] !== '' ? (string)$s[$key] : $default;
}

function save_setting(string $key, string $value): void
{
    db()->prepare('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value')
        ->execute([$key, $value]);
    settings(true);
}

/* ------------------------------------------------------------------ */
/* Ürünler                                                             */
/* ------------------------------------------------------------------ */
function hydrate_product(array $p): array
{
    $p['id'] = (int)$p['id'];
    $p['price'] = (float)$p['price'];
    $p['old_price'] = (float)$p['old_price'];
    $p['active'] = (int)$p['active'];
    $p['in_stock'] = (int)$p['in_stock'];
    $p['images'] = json_decode($p['images'] ?: '[]', true) ?: [];
    $p['features'] = json_decode($p['features'] ?: '[]', true) ?: [];
    $p['orderable'] = $p['active'] && $p['in_stock'] && $p['price'] > 0;
    return $p;
}

function products(bool $onlyActive = true): array
{
    $sql = 'SELECT * FROM products' . ($onlyActive ? ' WHERE active = 1' : '') . ' ORDER BY sort ASC, id ASC';
    return array_map('hydrate_product', db()->query($sql)->fetchAll());
}

function product(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM products WHERE id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    return $r ? hydrate_product($r) : null;
}

/* ------------------------------------------------------------------ */
/* Sipariş hesaplama (fiyatlar her zaman sunucuda hesaplanır)         */
/* ------------------------------------------------------------------ */
function payment_methods(): array
{
    $all = [
        'kapida_nakit' => ['label' => 'Kapıda Nakit Ödeme', 'desc' => 'Ürün elinize ulaştığında kuryeye nakit ödeyin.', 'icon' => 'cash'],
        'kapida_kart'  => ['label' => 'Kapıda Kredi Kartı', 'desc' => 'Kapıda kredi / banka kartınızla güvenle ödeyin.', 'icon' => 'card'],
        'havale'       => ['label' => 'Havale / EFT', 'desc' => 'Sipariş sonrası IBAN bilgisine ödeme yapın.', 'icon' => 'bank'],
    ];
    $map = ['kapida_nakit' => 'pay_cod_cash', 'kapida_kart' => 'pay_cod_card', 'havale' => 'pay_transfer'];
    $out = [];
    foreach ($all as $k => $v) {
        if (setting($map[$k], '0') === '1') { $out[$k] = $v; }
    }
    $disc = (float)setting('transfer_discount', '0');
    if (isset($out['havale']) && $disc > 0) {
        $out['havale']['desc'] = 'Havale / EFT ile ödemelerde %' . fmt_num($disc) . ' indirim!';
        $out['havale']['tag'] = '%' . fmt_num($disc) . ' indirim';
    }
    $fee = (float)setting('cod_fee', '0');
    foreach (['kapida_nakit', 'kapida_kart'] as $k) {
        if (isset($out[$k]) && $fee > 0) { $out[$k]['tag'] = '+' . money($fee) . ' hizmet bedeli'; }
    }
    return $out;
}

/**
 * @param array $items [[id, qty], ...]
 */
function calculate_order(array $items, string $payment): array
{
    $lines = [];
    $subtotal = 0.0;
    foreach ($items as $it) {
        $id = (int)($it['id'] ?? 0);
        $qty = max(0, min(99, (int)($it['qty'] ?? 0)));
        if (!$id || !$qty) { continue; }
        $p = product($id);
        if (!$p || !$p['orderable']) { continue; }
        $line = round($p['price'] * $qty, 2);
        $subtotal += $line;
        $lines[] = ['id' => $p['id'], 'name' => $p['name'], 'unit' => $p['unit'], 'price' => $p['price'], 'qty' => $qty, 'total' => $line];
    }
    $discount = 0.0;
    if ($payment === 'havale') {
        $discount = round($subtotal * ((float)setting('transfer_discount', '0')) / 100, 2);
    }
    $shippingFee = (float)setting('shipping_fee', '0');
    $freeMin = (float)setting('free_shipping_min', '0');
    $shipping = ($shippingFee > 0 && !($freeMin > 0 && $subtotal >= $freeMin)) ? $shippingFee : 0.0;
    $codFee = in_array($payment, ['kapida_nakit', 'kapida_kart'], true) ? (float)setting('cod_fee', '0') : 0.0;
    $total = round($subtotal - $discount + $shipping + $codFee, 2);
    return compact('lines', 'subtotal', 'discount', 'shipping', 'codFee', 'total');
}

function order_statuses(): array
{
    return [
        'yeni'      => ['Yeni', '#c2410c'],
        'onaylandi' => ['Onaylandı', '#1d4ed8'],
        'hazirlaniyor' => ['Hazırlanıyor', '#7c3aed'],
        'kargoda'   => ['Kargoda', '#0e7490'],
        'teslim'    => ['Teslim Edildi', '#15803d'],
        'iptal'     => ['İptal', '#6b7280'],
    ];
}

/* ------------------------------------------------------------------ */
/* Yardımcılar                                                         */
/* ------------------------------------------------------------------ */
function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fmt_num(float $n): string
{
    return rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ',');
}

function money(float $n): string
{
    return number_format($n, 2, ',', '.') . ' ₺';
}

function phone_digits(string $phone): string
{
    $d = preg_replace('/\D+/', '', $phone);
    if (str_starts_with($d, '90')) { return $d; }
    if (str_starts_with($d, '0')) { return '9' . $d; }
    return '90' . $d;
}

function base_url(): string
{
    $configured = rtrim(setting('site_url'), '/');
    if ($configured !== '') { return $configured; }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    $dir = preg_replace('#/(api|panel)$#', '', $dir);
    return ($https ? 'https' : 'http') . '://' . $host . $dir;
}

function asset(string $path): string
{
    $file = APP_ROOT . '/' . ltrim($path, '/');
    $v = is_file($file) ? '?v=' . filemtime($file) : '';
    return $path . $v;
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function json_out(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function turkish_cities(): array
{
    return ['Adana','Adıyaman','Afyonkarahisar','Ağrı','Aksaray','Amasya','Ankara','Antalya','Ardahan','Artvin','Aydın','Balıkesir','Bartın','Batman','Bayburt','Bilecik','Bingöl','Bitlis','Bolu','Burdur','Bursa','Çanakkale','Çankırı','Çorum','Denizli','Diyarbakır','Düzce','Edirne','Elazığ','Erzincan','Erzurum','Eskişehir','Gaziantep','Giresun','Gümüşhane','Hakkari','Hatay','Iğdır','Isparta','İstanbul','İzmir','Kahramanmaraş','Karabük','Karaman','Kars','Kastamonu','Kayseri','Kilis','Kırıkkale','Kırklareli','Kırşehir','Kocaeli','Konya','Kütahya','Malatya','Manisa','Mardin','Mersin','Muğla','Muş','Nevşehir','Niğde','Ordu','Osmaniye','Rize','Sakarya','Samsun','Şanlıurfa','Siirt','Sinop','Şırnak','Sivas','Tekirdağ','Tokat','Trabzon','Tunceli','Uşak','Van','Yalova','Yozgat','Zonguldak'];
}

/* ------------------------------------------------------------------ */
/* Yasal metin varsayılanları (panelden düzenlenebilir)               */
/* ------------------------------------------------------------------ */
function legal_text(string $key): string
{
    $custom = setting($key);
    if ($custom !== '') { return $custom; }
    $c = setting('company_name');
    $addr = setting('address');
    $phone = setting('phone');
    switch ($key) {
        case 'kvkk_text':
            return "$c olarak, 6698 sayılı Kişisel Verilerin Korunması Kanunu kapsamında veri sorumlusu sıfatıyla; sipariş formunda paylaştığınız ad-soyad, telefon, e-posta ve adres bilgilerinizi yalnızca siparişinizin alınması, onaylanması, kargolanması, faturalandırılması ve sizinle iletişim kurulması amacıyla işlemekteyiz.\n\nBilgileriniz, siparişin teslimi için anlaşmalı kargo firması dışında üçüncü kişilerle paylaşılmaz. KVKK'nın 11. maddesi kapsamındaki haklarınızı kullanmak için $phone numarasından bize ulaşabilirsiniz.";
        case 'distance_contract':
            return "MESAFELİ SATIŞ SÖZLEŞMESİ VE ÖN BİLGİLENDİRME\n\nSatıcı: $c — $addr — Tel: $phone\n\n1. Konu: İşbu sözleşme, alıcının internet sitesi üzerinden sipariş verdiği ürünlerin satışı ve teslimine ilişkin tarafların hak ve yükümlülüklerini 6502 sayılı Tüketicinin Korunması Hakkında Kanun ve Mesafeli Sözleşmeler Yönetmeliği hükümlerine göre düzenler.\n\n2. Ürün, fiyat ve ödeme: Ürünlerin cinsi, adedi, KDV dahil satış fiyatı ve ödeme şekli sipariş özetinde belirtildiği gibidir. Ödeme kapıda nakit, kapıda kredi kartı veya havale/EFT ile yapılabilir.\n\n3. Teslimat: Ürünler, sipariş onayından sonra en geç 30 gün içinde (genellikle çok daha kısa sürede) alıcının bildirdiği adrese kargo ile teslim edilir.\n\n4. Cayma hakkı: Mesafeli Sözleşmeler Yönetmeliği'nin 15. maddesi uyarınca, çabuk bozulabilen veya son kullanma tarihi geçebilecek gıda ürünlerinde ve ambalajı açılmış ürünlerde cayma hakkı kullanılamaz. Ayıplı mal teslimi halinde 6502 sayılı Kanun'dan doğan yasal haklarınız saklıdır. Kargo kaynaklı hasarlarda talepte bulunabilmek için, teslimat sırasında kargo görevlisine hasar tespit tutanağı tutturulması gerekir.\n\n5. Uyuşmazlık: Uyuşmazlıklarda Tüketici Hakem Heyetleri ve Tüketici Mahkemeleri yetkilidir.";
        case 'return_policy':
            return "Ürünlerimiz gıda ürünü olduğundan, ambalajı açılmış ürünlerde iade kabul edilememektedir.\n\nTeslimat sırasında paketi kargo görevlisinin yanında kontrol etmenizi öneririz. Pakette dışarıdan görülen bir hasar varsa kargo görevlisine hasar tespit tutanağı tutturunuz; tutanak olmadan teslim alınan paketlerde kargo kaynaklı hasar talepleri değerlendirilemez.\n\nAyıplı mal teslimi halinde 6502 sayılı Tüketicinin Korunması Hakkında Kanun'dan doğan yasal haklarınız saklıdır. Sorularınız için $phone numarasından bize ulaşabilirsiniz.";
    }
    return '';
}
