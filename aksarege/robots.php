<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');
echo "User-agent: *\nAllow: /\nDisallow: /panel/\nDisallow: /api/\n\nSitemap: " . base_url() . "/sitemap.xml\n";
