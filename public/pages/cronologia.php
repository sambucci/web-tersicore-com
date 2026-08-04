<?php if (!defined('TERSICORE')) { http_response_code(404); exit; }

$meta['title'] = 'Cronologia';
$meta['description'] = 'La linea del tempo delle fonti primarie della danza occidentale e delle opere '
    . 'che la raffigurano, secolo per secolo, dal Quattrocento all\'Ottocento.';

$eventi = [];
foreach (fonti() as $f) {
    $eventi[] = ['anno' => (int)$f['anno'], 'kind' => 'fonte', 'titolo' => $f['titolo'],
                 'sub' => $f['autore'] . ', ' . $f['citta'], 'url' => '/fonti/' . $f['slug'] . '/'];
}
foreach (iconografia() as $o) {
    $y = (int)($o['anno_ord'] ?? 0);
    if ($y <= 0) continue;
    $eventi[] = ['anno' => $y, 'kind' => 'opera', 'titolo' => $o['titolo'],
                 'sub' => $o['autore'], 'url' => '/iconografia/' . $o['slug'] . '/',
                 'anno_label' => (string)$o['anno']];
}
usort($eventi, fn($a, $b) => $a['anno'] <=> $b['anno']);

$per_secolo = [];
foreach ($eventi as $e) { $per_secolo[century_of($e['anno'])][] = $e; }
ksort($per_secolo);

$KIND = ['fonte' => 'fonte', 'opera' => 'opera'];

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

    <nav class="rail" aria-label="Vai al secolo">
      <ul class="rail__track">
        <?php foreach ($per_secolo as $c => $rows): ?>
        <li><a href="#secolo-<?= (int)$c ?>">
          <span class="rail__n"><?= esc(roman((int)$c)) ?></span><?= count($rows) ?> voci
        </a></li>
        <?php endforeach; ?>
      </ul>
    </nav>

    <div class="measure">
      <?php foreach ($per_secolo as $c => $rows): ?>
      <h2 class="tl__century" id="secolo-<?= (int)$c ?>">Secolo <?= esc(roman((int)$c)) ?></h2>
      <ul class="tl">
        <?php foreach ($rows as $e): ?>
        <li data-kind="<?= esc($e['kind']) ?>">
          <span class="tl__year"><?= esc($e['anno_label'] ?? (string)$e['anno']) ?></span>
          <a class="tl__t" href="<?= esc($e['url']) ?>"><?= esc($e['titolo']) ?></a>
          <span class="tl__kind"><?= esc($KIND[$e['kind']]) ?></span>
          <span class="linklist__meta"><?= esc($e['sub']) ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php $content = ob_get_clean();
