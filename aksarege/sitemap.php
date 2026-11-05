<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
header('Content-Type: application/xml; charset=utf-8');
$base = base_url();
$last = db()->query('SELECT MAX(updated_at) FROM products')->fetchColumn() ?: date('c');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
  <url>
    <loc><?= e($base) ?>/</loc>
    <lastmod><?= e(date('Y-m-d', strtotime($last))) ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>1.0</priority>
<?php foreach (products(true) as $p): foreach ($p['images'] as $im): ?>
    <image:image><image:loc><?= e($base . '/' . ltrim($im, '/')) ?></image:loc><image:title><?= e('Aksar Ege ' . $p['name']) ?></image:title></image:image>
<?php endforeach; endforeach; ?>
  </url>
</urlset>
