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

// Featured source: the treatise that gives the site its name, else the oldest.
$featured = null;
foreach ($F as $f) { if (($f['slug'] ?? '') === 'blasis-code-of-terpsichore') { $featured = $f; break; } }
if (!$featured && $F) { $featured = fonti_by_era('cinquecento')[0] ?? $F[0]; }

// Opening plate: the first artwork that has a shipped image.
$opening = null;
foreach ($I as $o) { if (!empty($o['immagine'])) { $opening = $o; break; } }

$centuries = [];
foreach ($F as $f) { $c = century_of((int)$f['anno']); $centuries[$c] = ($centuries[$c] ?? 0) + 1; }
ksort($centuries);

ob_start(); ?>

<section class="section">
  <div class="shell">
    <div class="measure">
      <h1>Le fonti primarie della danza occidentale</h1>
      <p class="lead">Dal Quattrocento all'Ottocento la danza è stata scritta prima di essere filmata:
      in trattati, manuali e sistemi di notazione che restano l'unica testimonianza diretta di come si
      ballava. Questo sito scheda quelle fonti e collega ciascuna alla copia digitalizzata presso
      l'istituzione che la conserva.</p>
      <p>Ogni collegamento è aperto e verificato uno per uno. Le schede coprono
      <?= count($F) ?> opere a stampa e manoscritte, <?= count($L) ?> voci di lessico legate alla fonte
      che per prima le documenta, <?= count($I) ?> opere d'arte di pubblico dominio.</p>
      <hr class="rule-accent">
    </div>
  </div>
</section>

<?php if ($opening): ?>
<section class="section">
  <div class="shell">
    <?= plate($opening['immagine'], $opening['alt'] ?? $opening['titolo'], 'plate--wide', false,
              $opening['titolo'] . ($opening['anno'] ? ', ' . $opening['anno'] : '')) ?>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="shell">
    <h2>Le quattro epoche</h2>
    <div class="grid grid--3">
      <?php foreach (ERE as $key => $era):
        $n = count(fonti_by_era($key)); if (!$n) continue; ?>
      <div class="era-block">
        <h3><a href="/fonti/#<?= esc($key) ?>"><?= esc($era['label']) ?></a></h3>
        <p class="count"><?= esc($era['range']) ?><span class="meta__sep"></span><?= $n ?> fonti</p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if ($featured): ?>
<section class="section">
  <div class="shell">
    <div class="measure">
      <p class="label">Fonte in evidenza</p>
      <h2><a href="/fonti/<?= esc($featured['slug']) ?>/"><?= esc($featured['titolo']) ?></a></h2>
      <?= meta_row([$featured['autore'], (string)$featured['anno'], $featured['citta']]) ?>
      <p><?= esc(excerpt((string)($featured['rilevanza'] ?? ''), 320)) ?></p>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($centuries): ?>
<section class="section">
  <div class="shell">
    <h2>Per secolo</h2>
    <nav class="rail" aria-label="Secoli">
      <ul class="rail__track">
        <?php foreach ($centuries as $c => $n): ?>
        <li><a href="/cronologia/#secolo-<?= (int)$c ?>">
          <span class="rail__n"><?= esc(roman((int)$c)) ?></span>
          <?= $n ?> fonti
        </a></li>
        <?php endforeach; ?>
      </ul>
    </nav>
  </div>
</section>
<?php endif; ?>

<?php $content = ob_get_clean();
