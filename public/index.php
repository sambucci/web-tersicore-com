<?php
define('TERSICORE', 1);
require __DIR__ . '/lib.php';

$raw = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');

// Generated files, served before path canonicalisation so the extension survives.
if ($raw === '/sitemap.xml') {
    require __DIR__ . '/pages/sitemap.php';
    exit;
}

// Canonical form: lowercase, one trailing slash. Anything else redirects once.
$canon = $raw === '/' ? '/' : '/' . trim(strtolower($raw), '/') . '/';
if ($canon !== $raw) {
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: ' . $canon . ($qs !== '' ? '?' . $qs : ''), true, 301);
    exit;
}
$path = $canon;
$seg  = $path === '/' ? [] : explode('/', trim($path, '/'));

$meta = [
    'title'       => SITE_NAME,
    'description' => '',
    'canonical'   => SITE_URL . $path,
    'og_type'     => 'website',
    'og_image'    => null,
    'schema'      => null,
    'body_class'  => '',
];
$content = '';
$status  = 200;

$route = $seg[0] ?? '';
$slug  = $seg[1] ?? '';
$n     = count($seg);

switch (true) {
    case $path === '/':                            require __DIR__ . '/pages/home.php'; break;

    case $route === 'fonti'        && $n === 1:    require __DIR__ . '/pages/fonti-index.php'; break;
    case $route === 'fonti'        && $n === 2:    require __DIR__ . '/pages/fonte.php'; break;
    case $route === 'lessico'      && $n === 1:    require __DIR__ . '/pages/lessico-index.php'; break;
    case $route === 'lessico'      && $n === 2:    require __DIR__ . '/pages/termine.php'; break;
    case $route === 'iconografia'  && $n === 1:    require __DIR__ . '/pages/iconografia-index.php'; break;
    case $route === 'iconografia'  && $n === 2:    require __DIR__ . '/pages/opera.php'; break;
    case $route === 'cronologia'   && $n === 1:    require __DIR__ . '/pages/cronologia.php'; break;
    case $route === 'metodo'       && $n === 1:    require __DIR__ . '/pages/metodo.php'; break;
    case $route === 'crediti'      && $n === 1:    require __DIR__ . '/pages/crediti.php'; break;

    // Style-gate variants. Temporary: removed once the direction is picked.
    case in_array($route, ['test1', 'test2', 'test3'], true) && $n === 1:
        require __DIR__ . '/pages/' . $route . '.php';
        exit;

    default:
        $status = 404;
        require __DIR__ . '/pages/404.php';
}

http_response_code($status);
header('Content-Type: text/html; charset=utf-8');
require __DIR__ . '/inc/head.php';
echo $content;
require __DIR__ . '/inc/footer.php';
