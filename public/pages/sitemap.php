<?php if (!defined('TERSICORE')) { http_response_code(404); exit; }

header('Content-Type: application/xml; charset=utf-8');

$urls = [
    ['/',              '1.0'],
    ['/fonti/',        '0.9'],
    ['/lessico/',      '0.9'],
    ['/iconografia/',  '0.8'],
    ['/cronologia/',   '0.7'],
    ['/metodo/',       '0.5'],
    ['/chi-siamo/',    '0.5'],
    ['/crediti/',      '0.3'],
    ['/contatti/',     '0.3'],
    ['/privacy/',      '0.2'],
    ['/termini/',      '0.2'],
];
foreach (fonti()       as $f) { $urls[] = ['/fonti/' . $f['slug'] . '/', '0.8']; }
foreach (lessico()     as $t) { $urls[] = ['/lessico/' . $t['slug'] . '/', '0.7']; }
foreach (iconografia() as $o) { $urls[] = ['/iconografia/' . $o['slug'] . '/', '0.6']; }

$lastmod = gmdate('Y-m-d', (int)@filemtime(__DIR__ . '/../data/fonti.json') ?: time());

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as [$loc, $prio]) {
    echo "  <url>\n";
    echo '    <loc>' . esc(SITE_URL . $loc) . "</loc>\n";
    echo '    <lastmod>' . $lastmod . "</lastmod>\n";
    echo '    <priority>' . $prio . "</priority>\n";
    echo "  </url>\n";
}
echo "</urlset>\n";
