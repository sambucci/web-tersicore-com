<?php if (!defined('TERSICORE')) { http_response_code(404); exit; }

$F = fonti(); $L = lessico(); $I = iconografia();
$meta['title'] = SITE_NAME;
$meta['description'] = 'Le fonti primarie della danza occidentale dal Quattrocento all\'Ottocento: '
    . count($F) . ' trattati e sistemi di notazione, ' . count($L) . ' termini, ' . count($I)
    . ' opere, ognuno con il collegamento verificato alla copia digitalizzata.';
$meta['schema'] = [
    '@context' => 'https://schema.org', '@type' => 'WebSite',
    'name' => SITE_NAME, 'url' => SITE_URL, 'inLanguage' => 'it',
    'description' => $meta['description'],
];

// The spine: sources and artworks in one chronological column.
$eventi = [];
foreach ($F as $f) {
    $eventi[] = ['anno' => (int)$f['anno'], 'kind' => 'fonte', 't' => $f['titolo'],
                 'sub' => $f['autore'] . ', ' . $f['citta'], 'url' => '/fonti/' . $f['slug'] . '/'];
}
foreach ($I as $o) {
    $y = (int)($o['anno_ord'] ?? 0);
    if ($y > 1400) {
        $eventi[] = ['anno' => $y, 'kind' => 'opera', 't' => $o['titolo'], 'sub' => $o['autore'],
                     'url' => '/iconografia/' . $o['slug'] . '/', 'anno_label' => $o['anno']];
    }
}
usort($eventi, fn($a, $b) => $a['anno'] <=> $b['anno']);
$per_secolo = [];
foreach ($eventi as $e) { $per_secolo[century_of($e['anno'])][] = $e; }
ksort($per_secolo);

// One plate beside each century, drawn from that century where possible.
$ASIDE = [
    15 => ['plate-caroso-ballarino.webp',        'Pagina del Ballarino di Caroso con l\'intavolatura di liuto',
           'Fabritio Caroso, Il ballarino, 1581'],
    16 => ['plate-arbeau-orchesographie.webp',   'Pagina dell\'Orchesographie di Arbeau con la tavola dei passi',
           'Thoinot Arbeau, Orchésographie, 1589'],
    18 => ['plate-tomlinson-sarabanda.webp',     'Due danzatori incisi a figura intera sopra il tracciato dei loro passi',
           'Kellom Tomlinson, The Art of Dancing, 1735'],
    19 => ['fanny-elssler-nella-cachucha-anonimo-full.webp', 'Fanny Elssler in posa di danza nel costume della cachucha',
           'Fanny Elssler nella cachucha, 1840 circa'],
];
$LEAD = 'il-celebre-pas-de-quatre-di-jules-perrot-full.webp';

ob_start(); ?>

<section class="section home-intro">
  <div class="shell">
    <div class="measure">
      <h1>Le fonti primarie della danza occidentale</h1>
      <p class="lead">Dal Quattrocento all'Ottocento la danza è stata scritta prima di essere
      filmata: in trattati, manuali e sistemi di notazione che restano l'unica testimonianza
      diretta di come si ballava.</p>
      <p>Questo sito scheda quelle fonti e collega ciascuna alla copia digitalizzata presso
      l'istituzione che la conserva. Ogni collegamento è aperto e verificato uno per uno.</p>
      <hr class="rule-accent">
      <p class="meta"><?= count($F) ?> fonti<span class="meta__sep"></span><?= count($L) ?> voci di lessico<span class="meta__sep"></span><?= count($I) ?> opere</p>
    </div>
  </div>
</section>

<section class="section">
  <div class="shell">
    <?= plate($LEAD,
        'Quattro ballerine romantiche in posa, litografia del Pas de Quatre di Jules Perrot, 1845',
        'lead-plate', false,
        'Il celebre Pas de Quatre di Jules Perrot, danzato da Carlotta Grisi, Marie Taglioni, Lucile Grahn e Fanny Cerrito, 1845') ?>
  </div>
</section>

<nav class="rail rail--sticky" aria-label="Vai al secolo">
  <div class="shell">
    <ul class="rail__track">
      <?php foreach ($per_secolo as $c => $rows): ?>
      <li><a href="#secolo-<?= (int)$c ?>">
        <span class="rail__n"><?= esc(roman((int)$c)) ?></span><?= count($rows) ?> voci
      </a></li>
      <?php endforeach; ?>
    </ul>
  </div>
</nav>

<div class="shell">
  <?php foreach ($per_secolo as $c => $rows):
    $shown = array_slice($rows, 0, 12);
    $rest  = count($rows) - count($shown); ?>
  <section class="cent" id="secolo-<?= (int)$c ?>">
    <div class="cent__head">
      <h2>Secolo <?= esc(roman((int)$c)) ?></h2>
      <p class="meta"><?= count($rows) ?> voci datate</p>
    </div>
    <div class="cent__body">
      <ul class="spine">
        <?php foreach ($shown as $e): ?>
        <li data-kind="<?= esc($e['kind']) ?>">
          <span class="spine__yr"><?= esc($e['anno_label'] ?? (string)$e['anno']) ?></span>
          <a class="spine__t" href="<?= esc($e['url']) ?>"><?= esc($e['t']) ?></a>
          <span class="spine__sub"><?= esc($e['sub']) ?></span>
        </li>
        <?php endforeach; ?>
        <?php if ($rest > 0): ?>
        <li class="spine__more"><a href="/cronologia/#secolo-<?= (int)$c ?>">altre <?= $rest ?> voci del secolo</a></li>
        <?php endif; ?>
      </ul>
      <?php if (isset($ASIDE[$c])): [$img, $alt, $cap] = $ASIDE[$c]; ?>
      <div class="aside"><?= plate($img, $alt, 'plate--tight', true, $cap) ?></div>
      <?php endif; ?>
    </div>
  </section>
  <?php endforeach; ?>
</div>

<section class="section">
  <div class="shell">
    <div class="measure">
      <h2>Come leggere queste schede</h2>
      <p>Ogni fonte dichiara quale edizione è quella collegata. Ogni voce del lessico rimanda al
      trattato che per primo la documenta fra quelli schedati qui. Ogni opera figurativa porta
      l'autore, l'istituzione e la licenza.</p>
      <p><a href="/metodo/">Il metodo per esteso</a></p>
    </div>
  </div>
</section>

<?php $content = ob_get_clean();
