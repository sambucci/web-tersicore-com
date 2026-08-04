<?php
if (!defined('TERSICORE')) { http_response_code(404); exit; }

const SITE_NAME = 'Tersicore';
const SITE_URL  = 'https://tersicore.com';
const SITE_TAGLINE = 'le fonti della danza occidentale';

const ERE = [
    'quattrocento'  => ['label' => 'Quattrocento',      'range' => '1400 - 1500'],
    'cinquecento'   => ['label' => 'Cinquecento',       'range' => '1500 - 1600'],
    'seisettecento' => ['label' => 'Sei e Settecento',  'range' => '1600 - 1800'],
    'ottocento'     => ['label' => 'Ottocento',         'range' => '1800 - 1900'],
];

const TRADIZIONI = [
    'italiana' => 'italiana', 'francese' => 'francese', 'inglese' => 'inglese',
    'tedesca' => 'tedesca', 'spagnola' => 'spagnola', 'internazionale' => 'internazionale',
];

function esc(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Cache-busting asset URL. */
function asset(string $path): string {
    $fs = __DIR__ . '/' . ltrim($path, '/');
    $v = is_file($fs) ? filemtime($fs) : 0;
    return '/' . ltrim($path, '/') . '?v=' . $v;
}

function data_load(string $name): array {
    static $cache = [];
    if (isset($cache[$name])) return $cache[$name];
    $file = __DIR__ . '/data/' . $name . '.json';
    if (!is_file($file)) return $cache[$name] = [];
    $d = json_decode((string)file_get_contents($file), true);
    return $cache[$name] = is_array($d) ? $d : [];
}

function fonti(): array        { return data_load('fonti'); }
function lessico(): array      { return data_load('lessico'); }
function iconografia(): array  { return data_load('iconografia'); }
function credits(): array      { return data_load('credits'); }

/** Index a dataset by a key, e.g. by_key(fonti(), 'slug'). */
function by_key(array $rows, string $key): array {
    $out = [];
    foreach ($rows as $r) { if (isset($r[$key])) $out[$r[$key]] = $r; }
    return $out;
}

function fonte_by_id(string $id): ?array {
    static $m = null;
    if ($m === null) $m = by_key(fonti(), 'id');
    return $m[$id] ?? null;
}

function termine_by_slug(string $slug): ?array {
    static $m = null;
    if ($m === null) $m = by_key(lessico(), 'slug');
    return $m[$slug] ?? null;
}

/** Look up a lexicon entry by its display term, case- and accent-insensitively. */
function termine_by_name(string $name): ?array {
    static $m = null;
    if ($m === null) {
        $m = [];
        foreach (lessico() as $t) { $m[norm_key($t['termine'])] = $t; }
    }
    return $m[norm_key($name)] ?? null;
}

function norm_key(string $s): string {
    $s = mb_strtolower(trim($s), 'UTF-8');
    $s = strtr($s, ['à'=>'a','á'=>'a','â'=>'a','è'=>'e','é'=>'e','ê'=>'e','ì'=>'i','í'=>'i',
                    'î'=>'i','ò'=>'o','ó'=>'o','ô'=>'o','ù'=>'u','ú'=>'u','û'=>'u','ç'=>'c','ñ'=>'n']);
    return preg_replace('/[^a-z0-9]+/', ' ', $s) ?? $s;
}

function markdown(string $name): string {
    $file = __DIR__ . '/data/content/' . $name . '.md';
    if (!is_file($file)) return '';
    require_once __DIR__ . '/vendor/Parsedown.php';
    static $pd = null;
    if ($pd === null) {
        $pd = new Parsedown();
        $pd->setMarkupEscaped(false);   // content files are trusted, authored in-repo
        $pd->setBreaksEnabled(false);
    }
    return $pd->text((string)file_get_contents($file));
}

/**
 * Credit record for a shipped image file, keyed by basename.
 * Each artwork ships twice: a 16:10 card crop and an uncropped '-full' version
 * for the entry page. Both carry the same credit, so the suffix is stripped
 * before lookup rather than duplicated in credits.json.
 */
function credit_for(string $file): ?array {
    static $m = null;
    if ($m === null) $m = by_key(credits(), 'file');
    $b = basename($file);
    if (isset($m[$b])) return $m[$b];
    $base = preg_replace('/-full(\.[a-z0-9]+)$/i', '$1', $b);
    return $m[$base] ?? null;
}

/**
 * The site's one identity component: an image in an asymmetric print mount
 * with a letterpress caption naming author, institution and licence.
 */
function plate(?string $file, string $alt, string $class = '', bool $lazy = true, string $caption_extra = ''): string {
    if (!$file) return '';
    $rel = 'assets/img/' . basename($file);
    if (!is_file(__DIR__ . '/' . $rel)) return '';
    $c = credit_for($file);
    $loading = $lazy ? 'lazy' : 'eager';
    $h  = '<figure class="plate ' . esc($class) . '">';
    $h .= '<span class="plate__mount"><img src="' . esc(asset($rel)) . '" alt="' . esc($alt)
        . '" loading="' . $loading . '" decoding="async"></span>';
    if ($c || $caption_extra) {
        $h .= '<figcaption class="plate__cap">';
        if ($caption_extra) $h .= '<span class="plate__capline">' . esc($caption_extra) . '</span>';
        if ($c) $h .= '<span class="plate__credit">' . image_credit_text($c) . '</span>';
        $h .= '</figcaption>';
    }
    return $h . '</figure>';
}

function image_credit_text(array $c): string {
    $bits = [];
    if (!empty($c['author']))      $bits[] = esc($c['author']);
    if (!empty($c['institution'])) $bits[] = esc($c['institution']);
    // CC BY and CC BY-SA make naming the photographer a licence condition.
    if (!empty($c['photo_credit'])) $bits[] = 'foto ' . esc($c['photo_credit']);
    if (!empty($c['license'])) {
        $lic = esc($c['license']);
        $bits[] = !empty($c['license_url'])
            ? '<a href="' . esc($c['license_url']) . '">' . $lic . '</a>'
            : $lic;
    }
    return implode(', ', $bits);
}

function meta_row(array $bits): string {
    $bits = array_values(array_filter($bits, fn($b) => $b !== '' && $b !== null));
    if (!$bits) return '';
    return '<p class="meta">' . implode('<span class="meta__sep"></span>', array_map('esc', $bits)) . '</p>';
}

function era_label(string $key): string { return ERE[$key]['label'] ?? $key; }

/** First N characters of plain text, for meta descriptions. */
function excerpt(string $text, int $len = 155): string {
    $t = trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?? '');
    if (mb_strlen($t, 'UTF-8') <= $len) return $t;
    $cut = mb_substr($t, 0, $len - 1, 'UTF-8');
    $sp  = mb_strrpos($cut, ' ', 0, 'UTF-8');
    return rtrim(mb_substr($cut, 0, $sp ?: null, 'UTF-8'), " ,.;:") . '.';
}

function century_of(int $year): int { return (int)floor(($year - 1) / 100) + 1; }

function roman(int $n): string {
    $map = ['M'=>1000,'CM'=>900,'D'=>500,'CD'=>400,'C'=>100,'XC'=>90,'L'=>50,
            'XL'=>40,'X'=>10,'IX'=>9,'V'=>5,'IV'=>4,'I'=>1];
    $out = '';
    foreach ($map as $sym => $val) { while ($n >= $val) { $out .= $sym; $n -= $val; } }
    return $out;
}

/** Sort helper honouring Italian accents well enough for an index list. */
function sort_by(array $rows, string $key): array {
    usort($rows, fn($a, $b) => strcmp(norm_key((string)($a[$key] ?? '')), norm_key((string)($b[$key] ?? ''))));
    return $rows;
}

const LINGUE = [
    'ita' => 'italiano', 'fra' => 'francese', 'eng' => 'inglese', 'ted' => 'tedesco',
    'spa' => 'spagnolo', 'por' => 'portoghese', 'lat' => 'latino',
    'lat/ita' => 'latino e italiano', 'ita/ted' => 'italiano e tedesco',
];
const TIPI = [
    'trattato' => 'trattato', 'manuale' => 'manuale',
    'notazione' => 'sistema di notazione', 'metodo' => 'metodo',
];

function lingua_label(string $k): string { return LINGUE[$k] ?? $k; }
function tipo_label(string $k): string   { return TIPI[$k] ?? $k; }

/** Sources belonging to one era, oldest first. */
function fonti_by_era(string $era): array {
    $out = array_values(array_filter(fonti(), fn($f) => ($f['era'] ?? '') === $era));
    usort($out, fn($a, $b) => ($a['anno'] ?? 0) <=> ($b['anno'] ?? 0));
    return $out;
}

/** Lexicon entries whose first attestation is this source. */
function termini_da_fonte(string $fonte_id): array {
    $out = [];
    foreach (lessico() as $t) {
        if (($t['prima_attestazione']['fonte_id'] ?? '') === $fonte_id) $out[] = $t;
    }
    return sort_by($out, 'termine');
}

/** Artworks linked to a source. */
function opere_per_fonte(string $fonte_id): array {
    $out = [];
    foreach (iconografia() as $o) {
        if (in_array($fonte_id, $o['fonti_correlate'] ?? [], true)) $out[] = $o;
    }
    return $out;
}
