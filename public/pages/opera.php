<?php if (!defined('TERSICORE')) { http_response_code(404); exit; }

$o = by_key(iconografia(), 'slug')[$slug] ?? null;
if (!$o) { $status = 404; require __DIR__ . '/404.php'; return; }

$meta['title']       = $o['titolo'];
$meta['description'] = excerpt((string)($o['danza_raffigurata'] ?? ''), 155);
$meta['og_type']     = 'article';
if (!empty($o['immagine']) && is_file(__DIR__ . '/../assets/img/' . basename($o['immagine']))) {
    $meta['og_image'] = asset('assets/img/' . basename($o['immagine']));
}
$meta['schema'] = [
    '@context' => 'https://schema.org', '@type' => 'VisualArtwork',
    'name' => $o['titolo'],
    'creator' => ['@type' => 'Person', 'name' => $o['autore']],
    'dateCreated' => (string)$o['anno'],
    'artMedium' => $o['tecnica'] ?? null,
    'url' => SITE_URL . '/iconografia/' . $o['slug'] . '/',
];

ob_start(); ?>
<section class="section">
  <div class="shell">
    <?php if (!empty($o['immagine'])): ?>
      <?= plate($o['immagine'], $o['alt'] ?? $o['titolo'], 'plate--wide', false,
                $o['titolo'] . ', ' . $o['autore']) ?>
    <?php endif; ?>
    <div class="measure">
      <p class="label">Iconografia</p>
      <h1><?= esc($o['titolo']) ?></h1>
      <?= meta_row([$o['autore'], (string)$o['anno'], $o['tecnica'] ?? '', $o['istituzione'] ?? '']) ?>

      <?php if (!empty($o['danza_raffigurata'])): ?>
      <div class="prose"><p><?= nl2br(esc($o['danza_raffigurata'])) ?></p></div>
      <?php endif; ?>

      <?php if (!empty($o['url_opera'])): ?>
      <div class="block">
        <p class="block__label">L'opera presso l'istituzione</p>
        <p class="block__body"><a href="<?= esc($o['url_opera']) ?>"><?= esc($o['istituzione'] ?: 'Scheda dell\'opera') ?></a></p>
        <?php if (!empty($o['licenza'])): ?>
        <p class="block__note">Riproduzione: <?= esc($o['licenza']) ?><?php
          if (!empty($o['autore_credito'])): ?>, <?= esc($o['autore_credito']) ?><?php endif; ?></p>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if (!empty($o['termini_correlati'])): ?>
      <p class="label">Termini illustrati</p>
      <ul class="tags">
        <?php foreach ($o['termini_correlati'] as $tc):
          $r = termine_by_name((string)$tc); ?>
        <li><?php if ($r): ?><a href="/lessico/<?= esc($r['slug']) ?>/"><?= esc($tc) ?></a><?php
            else: ?><span><?= esc($tc) ?></span><?php endif; ?></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>

      <?php if (!empty($o['fonti_correlate'])): ?>
      <h2>Fonti collegate</h2>
      <ul class="linklist">
        <?php foreach ($o['fonti_correlate'] as $fid):
          $r = fonte_by_id((string)$fid); if (!$r) continue; ?>
        <li><a href="/fonti/<?= esc($r['slug']) ?>/"><?= esc($r['titolo']) ?></a>
          <span class="linklist__meta"><?= esc($r['autore']) ?>, <?= esc((string)$r['anno']) ?></span></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>

      <p><a href="/iconografia/">Tutte le opere</a></p>
    </div>
  </div>
</section>
<?php $content = ob_get_clean();
