<?php if (!defined('TERSICORE')) { http_response_code(404); exit; }

$I = iconografia();
usort($I, fn($a, $b) => ($a['anno_ord'] ?? 0) <=> ($b['anno_ord'] ?? 0));
$meta['title'] = 'Iconografia';
$meta['description'] = count($I) . ' opere d\'arte di pubblico dominio che raffigurano la danza, '
    . 'dall\'antichità all\'Ottocento, collegate alle fonti e ai termini che illustrano.';

ob_start(); ?>
<section class="section">
  <div class="shell">
    <div class="measure">
      <h1>Iconografia</h1>
      <p class="lead">Le immagini che mostrano la danza dove i trattati la descrivono: vasi, affreschi,
      miniature, dipinti e incisioni, tutti di pubblico dominio, ciascuno collegato alle fonti e ai
      termini che illustra.</p>
      <p class="count"><?= count($I) ?> opere</p>
      <hr class="rule-accent">
    </div>
    <ul class="grid grid--3">
      <?php foreach ($I as $o): ?>
      <li class="card">
        <a class="card__link" href="/iconografia/<?= esc($o['slug']) ?>/">
          <?php if (!empty($o['immagine']) && plate($o['immagine'], '')): ?>
            <?= plate($o['immagine'], $o['alt'] ?? $o['titolo'], 'plate--tight') ?>
          <?php else: ?>
            <span class="card__noimg"><?= esc($o['tecnica'] ?? 'opera') ?></span>
          <?php endif; ?>
          <h3 class="card__title"><?= esc($o['titolo']) ?></h3>
          <p class="card__meta"><?= esc($o['autore']) ?><span class="meta__sep"></span><?= esc((string)$o['anno']) ?></p>
        </a>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php $content = ob_get_clean();
