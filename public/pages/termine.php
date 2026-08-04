<?php if (!defined('TERSICORE')) { http_response_code(404); exit; }

$t = termine_by_slug($slug);
if (!$t) { $status = 404; require __DIR__ . '/404.php'; return; }

$meta['title']       = $t['termine'];
$meta['description'] = excerpt((string)($t['definizione'] ?? ''), 155);
$meta['og_type']     = 'article';
$meta['schema'] = [
    '@context' => 'https://schema.org', '@type' => 'DefinedTerm',
    'name' => $t['termine'], 'description' => excerpt((string)($t['definizione'] ?? ''), 300),
    'inDefinedTermSet' => ['@type' => 'DefinedTermSet', 'name' => 'Lessico della danza storica', 'url' => SITE_URL . '/lessico/'],
    'url' => SITE_URL . '/lessico/' . $t['slug'] . '/',
];

$fonte = !empty($t['prima_attestazione']['fonte_id']) ? fonte_by_id($t['prima_attestazione']['fonte_id']) : null;

ob_start(); ?>
<section class="section">
  <div class="shell">
    <div class="measure">
      <p class="label">Lessico</p>
      <h1><?= esc($t['termine']) ?></h1>
      <?= meta_row([
            !empty($t['tradizione']) ? 'tradizione ' . $t['tradizione'] : '',
            !empty($t['epoca']) ? era_label($t['epoca']) : '',
          ]) ?>

      <?php if (!empty($t['varianti'])): ?>
      <p class="label">Varianti attestate</p>
      <ul class="tags"><?php foreach ($t['varianti'] as $v): ?><li><span><?= esc($v) ?></span></li><?php endforeach; ?></ul>
      <?php endif; ?>

      <div class="prose"><p><?= nl2br(esc($t['definizione'] ?? '')) ?></p></div>

      <?php if ($fonte): ?>
      <div class="block">
        <p class="block__label">Prima attestazione</p>
        <p class="block__body">
          <a href="/fonti/<?= esc($fonte['slug']) ?>/"><?= esc($fonte['titolo']) ?></a>,
          <?= esc($fonte['autore']) ?>, <?= esc((string)$fonte['anno']) ?>
        </p>
        <?php if (!empty($t['prima_attestazione']['riferimento'])): ?>
        <p class="block__note"><?= esc($t['prima_attestazione']['riferimento']) ?></p>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if (!empty($t['termini_correlati'])): ?>
      <h2>Termini correlati</h2>
      <ul class="linklist">
        <?php foreach ($t['termini_correlati'] as $tc):
          $r = termine_by_name((string)$tc); if (!$r) continue; ?>
        <li><a href="/lessico/<?= esc($r['slug']) ?>/"><?= esc($r['termine']) ?></a>
          <span class="linklist__meta"><?= esc($r['tradizione'] ?? '') ?></span></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>

      <p><a href="/lessico/">Tutte le voci del lessico</a></p>
    </div>
  </div>
</section>
<?php $content = ob_get_clean();
