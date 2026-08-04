<?php if (!defined('TERSICORE')) { http_response_code(404); exit; }
$title = $meta['title'] === SITE_NAME
    ? SITE_NAME . ' · ' . SITE_TAGLINE
    : $meta['title'] . ' · ' . SITE_NAME;
$ogimg = $meta['og_image'] ?? asset('assets/img/og-tersicore.webp');
$nav = [
    '/fonti/'       => 'Fonti',
    '/lessico/'     => 'Lessico',
    '/iconografia/' => 'Iconografia',
    '/cronologia/'  => 'Cronologia',
    '/metodo/'      => 'Metodo',
];
?><!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= esc($title) ?></title>
<?php if ($meta['description']): ?><meta name="description" content="<?= esc($meta['description']) ?>">
<?php endif; ?><link rel="canonical" href="<?= esc($meta['canonical']) ?>">
<meta property="og:site_name" content="<?= esc(SITE_NAME) ?>">
<meta property="og:type" content="<?= esc($meta['og_type']) ?>">
<meta property="og:title" content="<?= esc($title) ?>">
<meta property="og:url" content="<?= esc($meta['canonical']) ?>">
<?php if ($meta['description']): ?><meta property="og:description" content="<?= esc($meta['description']) ?>">
<?php endif; ?><meta property="og:image" content="<?= esc(SITE_URL . $ogimg) ?>">
<meta property="og:locale" content="it_IT">
<meta name="twitter:card" content="summary_large_image">
<link rel="preload" href="<?= esc(asset('assets/fonts/ebgaramond-var-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= esc(asset('assets/fonts/alegreyasans-400-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= esc(asset('assets/style.css')) ?>">
<link rel="icon" href="<?= esc(asset('assets/favicon.svg')) ?>" type="image/svg+xml">
<?php if ($meta['schema']): ?>
<script type="application/ld+json"><?= json_encode($meta['schema'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<?php endif; ?>
</head>
<body<?= $meta['body_class'] ? ' class="' . esc($meta['body_class']) . '"' : '' ?>>
<a class="skip" href="#main">Vai al contenuto</a>
<header class="site-head">
  <div class="shell site-head__in">
    <a class="wordmark" href="/"><span class="wordmark__word">Tersicore</span></a>
    <nav class="nav" aria-label="Sezioni">
      <ul>
<?php foreach ($nav as $href => $label):
        $cur = str_starts_with($GLOBALS['path'] ?? '/', $href); ?>
        <li><a href="<?= esc($href) ?>"<?= $cur ? ' aria-current="page"' : '' ?>><?= esc($label) ?></a></li>
<?php endforeach; ?>
      </ul>
    </nav>
  </div>
</header>
<main id="main">
