<?php if (!defined('TERSICORE')) { http_response_code(404); exit; }

$f = by_key(fonti(), 'slug')[$slug] ?? null;
if (!$f) { $status = 404; require __DIR__ . '/404.php'; return; }

$meta['title']       = $f['titolo'];
$meta['description'] = excerpt((string)($f['rilevanza'] ?? ''), 155);
$meta['og_type']     = 'article';
$meta['schema'] = [
    '@context' => 'https://schema.org', '@type' => 'Book',
    'name' => $f['titolo'], 'author' => ['@type' => 'Person', 'name' => $f['autore']],
    'datePublished' => (string)$f['anno'], 'inLanguage' => $f['lingua'] ?? 'it',
    'locationCreated' => $f['citta'] ?? null,
    'url' => SITE_URL . '/fonti/' . $f['slug'] . '/',
];

$termini_qui = termini_da_fonte($f['id']);
$opere       = opere_per_fonte($f['id']);

ob_start(); ?>
<section class="section">
  <div class="shell">
    <?php if (!empty($f['immagine'])): ?>
      <?= plate($f['immagine'], $f['alt_immagine'] ?? ('Frontespizio di ' . $f['titolo']), 'plate--wide', false) ?>
    <?php endif; ?>
    <div class="measure">
      <p class="label"><?= esc(era_label($f['era'])) ?></p>
      <h1><?= esc($f['titolo']) ?></h1>
      <?= meta_row([
            $f['autore'],
            (string)$f['anno'],
            $f['citta'] ?? '',
            lingua_label($f['lingua'] ?? ''),
            tipo_label($f['tipo'] ?? ''),
          ]) ?>

      <?php if (!empty($f['rilevanza'])): ?>
      <div class="prose"><p><?= nl2br(esc($f['rilevanza'])) ?></p></div>
      <?php endif; ?>

      <?php if (!empty($f['scansione']['url'])): ?>
      <div class="block">
        <p class="block__label">Copia digitalizzata</p>
        <p class="block__body">
          <a href="<?= esc($f['scansione']['url']) ?>"><?= esc($f['scansione']['istituzione'] ?? 'Facsimile digitale') ?></a>
        </p>
        <?php if (!empty($f['scansione']['edizione'])): ?>
        <p class="block__note"><?= esc($f['scansione']['edizione']) ?></p>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if (!empty($f['documenta'])): ?>
      <p class="label">Documenta</p>
      <ul class="tags">
        <?php foreach ($f['documenta'] as $d):
          $t = termine_by_name((string)$d); ?>
        <li><?php if ($t): ?><a href="/lessico/<?= esc($t['slug']) ?>/"><?= esc($d) ?></a><?php
            else: ?><span><?= esc($d) ?></span><?php endif; ?></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>

      <?php if ($termini_qui): ?>
      <h2>Termini documentati per la prima volta qui</h2>
      <ul class="linklist">
        <?php foreach ($termini_qui as $t): ?>
        <li><a href="/lessico/<?= esc($t['slug']) ?>/"><?= esc($t['termine']) ?></a>
          <span class="linklist__meta"><?= esc($t['tradizione'] ?? '') ?></span></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>

      <?php if (!empty($f['fonti_correlate'])): ?>
      <h2>Fonti correlate</h2>
      <ul class="linklist">
        <?php foreach ($f['fonti_correlate'] as $fid):
          $r = fonte_by_id((string)$fid); if (!$r) continue; ?>
        <li><a href="/fonti/<?= esc($r['slug']) ?>/"><?= esc($r['titolo']) ?></a>
          <span class="linklist__meta"><?= esc($r['autore']) ?>, <?= esc((string)$r['anno']) ?></span></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>

    <?php if ($opere): ?>
    <h2>Iconografia collegata</h2>
    <ul class="grid grid--3">
      <?php foreach ($opere as $o): ?>
      <li class="card">
        <a class="card__link" href="/iconografia/<?= esc($o['slug']) ?>/">
          <?php if (!empty($o['immagine'])): ?><?= plate($o['immagine'], $o['alt'] ?? $o['titolo'], 'plate--tight') ?>
          <?php else: ?><span class="card__noimg">opera</span><?php endif; ?>
          <h3 class="card__title"><?= esc($o['titolo']) ?></h3>
          <p class="card__meta"><?= esc($o['autore']) ?><span class="meta__sep"></span><?= esc((string)$o['anno']) ?></p>
        </a>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>
</section>
<?php $content = ob_get_clean();
