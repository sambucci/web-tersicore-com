<?php if (!defined('TERSICORE')) { http_response_code(404); exit; }

$meta['title'] = 'Cronologia';
$meta['description'] = 'La linea del tempo delle fonti primarie della danza occidentale e delle opere '
    . 'che la raffigurano, secolo per secolo, dall\'antichità all\'Ottocento.';

$eventi = [];
foreach (fonti() as $f) {
    $eventi[] = ['anno' => (int)$f['anno'], 'kind' => 'fonte', 't' => $f['titolo'],
                 'sub' => $f['autore'] . ', ' . $f['citta'], 'url' => '/fonti/' . $f['slug'] . '/'];
}
foreach (iconografia() as $o) {
    $y = (int)($o['anno_ord'] ?? 0);
    if ($y === 0) continue;
    $eventi[] = ['anno' => $y, 'kind' => 'opera', 't' => $o['titolo'], 'sub' => $o['autore'],
                 'url' => '/iconografia/' . $o['slug'] . '/', 'anno_label' => $o['anno']];
}
usort($eventi, fn($a, $b) => $a['anno'] <=> $b['anno']);

$per_secolo = [];
foreach ($eventi as $e) {
    // Everything before the fifteenth century sits in one antiquity band rather
    // than producing twenty empty century headings.
    $c = $e['anno'] < 1400 ? 0 : century_of($e['anno']);
    $per_secolo[$c][] = $e;
}
ksort($per_secolo);

ob_start(); ?>
<section class="section">
  <div class="shell">
    <div class="measure">
      <h1>Cronologia</h1>
      <p class="lead">Le fonti e le opere figurative disposte sull'asse del tempo. I punti pieni sono
      trattati, manuali e sistemi di notazione; i punti vuoti sono opere d'arte.</p>
      <p class="count"><?= count($eventi) ?> voci datate</p>
      <hr class="rule-accent">
    </div>
  </div>
</section>

<nav class="rail rail--sticky" aria-label="Vai al secolo">
  <div class="shell">
    <ul class="rail__track">
      <?php foreach ($per_secolo as $c => $rows): ?>
      <li><a href="#secolo-<?= (int)$c ?>">
        <span class="rail__n"><?= $c === 0 ? 'Ant.' : esc(roman((int)$c)) ?></span><?= count($rows) ?> voci
      </a></li>
      <?php endforeach; ?>
    </ul>
  </div>
</nav>

<div class="shell">
  <?php foreach ($per_secolo as $c => $rows): ?>
  <section class="cent" id="secolo-<?= (int)$c ?>">
    <div class="cent__head">
      <h2><?= $c === 0 ? 'Antichità e Medioevo' : 'Secolo ' . esc(roman((int)$c)) ?></h2>
      <p class="meta"><?= count($rows) ?> voci</p>
    </div>
    <div class="cent__body">
      <ul class="spine">
        <?php foreach ($rows as $e): ?>
        <li data-kind="<?= esc($e['kind']) ?>">
          <span class="spine__yr"><?= esc($e['anno_label'] ?? (string)$e['anno']) ?></span>
          <a class="spine__t" href="<?= esc($e['url']) ?>"><?= esc($e['t']) ?></a>
          <span class="spine__sub"><?= esc($e['sub']) ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
  <?php endforeach; ?>
</div>
<?php $content = ob_get_clean();
