<?php
declare(strict_types=1);
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
if (preg_match('~^/assets/[a-zA-Z0-9/_ .-]+\.(css|js|png|jpg|jpeg|webp|svg|pdf|woff2?)$~', $path) && !str_contains($path,'..') && is_file(__DIR__.$path)) {
    header('Cache-Control: public, max-age=604800'); return false;
}
if ($path === '/google27b034596b591f0d.html') return false;
require __DIR__.'/index.php';
