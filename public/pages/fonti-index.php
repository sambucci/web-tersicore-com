<?php if (!defined('TERSICORE')) { http_response_code(404); exit; }

$F = fonti();
$meta['title'] = 'Fonti';
$meta['description'] = count($F) . ' trattati, manuali e sistemi di notazione della danza occidentale '
    . 'dal Quattrocento all\'Ottocento, ognuno con il collegamento alla copia digitalizzata presso l\'istituzione che la conserva.';

ob_start(); ?>
<section class="section">
  <div class="shell">
    <div class="measure">
      <h1>Fonti</h1>
      <p class="lead">I trattati, i manuali e i sistemi di notazione che documentano la danza occidentale
      fra il Quattrocento e l'Ottocento, ordinati per epoca. Ogni scheda porta al facsimile digitale
      presso l'istituzione che conserva l'esemplare.</p>
      <p class="count"><?= count($F) ?> fonti schedate</p>
      <hr class="rule-accent">
    </div>
  </div>
</section>

<?php foreach (ERE as $key => $era):
  $rows = fonti_by_era($key);
  if (!$rows) continue; ?>
<section class="section" id="<?= esc($key) ?>">
  <div class="shell">
    <h2><?= esc($era['label']) ?></h2>
    <p class="count"><?= esc($era['range']) ?><span class="meta__sep"></span><?= count($rows) ?> fonti</p>
    <ul class="grid grid--3">
      <?php foreach ($rows as $f): ?>
      <li class="card">
        <a class="card__link" href="/fonti/<?= esc($f['slug']) ?>/">
          <?php if (!empty($f['immagine']) && plate($f['immagine'], '')): ?>
            <?= plate($f['immagine'], $f['alt_immagine'] ?? ('Frontespizio di ' . $f['titolo']), 'plate--tight') ?>
          <?php else: ?>
            <span class="card__noimg"><?= esc(tipo_label($f['tipo'])) ?></span>
          <?php endif; ?>
          <h3 class="card__title"><?= esc($f['titolo']) ?></h3>
          <p class="card__meta"><?= esc($f['autore']) ?><span class="meta__sep"></span><?= esc((string)$f['anno']) ?></p>
        </a>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endforeach; ?>
<?php $content = ob_get_clean();
