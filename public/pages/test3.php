<?php if (!defined('TERSICORE')) { http_response_code(404); exit; }
// STYLE GATE VARIANT C: "Spina cronologica". Dark umber ground, bone text,
// Cormorant Garamond display. The home page IS the timeline: no hero, no card
// grid, a century spine with entries hanging off it and small inline plates.
$F = fonti(); $L = lessico(); $I = iconografia();

$eventi = [];
foreach ($F as $f) {
    $eventi[] = ['anno' => (int)$f['anno'], 'kind' => 'fonte', 't' => $f['titolo'],
                 'sub' => $f['autore'] . ', ' . $f['citta']];
}
foreach ($I as $o) {
    if ((int)($o['anno_ord'] ?? 0) > 1400) {
        $eventi[] = ['anno' => (int)$o['anno_ord'], 'kind' => 'opera', 't' => $o['titolo'], 'sub' => $o['autore']];
    }
}
usort($eventi, fn($a, $b) => $a['anno'] <=> $b['anno']);
$per_secolo = [];
foreach ($eventi as $e) { $per_secolo[century_of($e['anno'])][] = $e; }
ksort($per_secolo);
$PLATES = ['plate-caroso-ballarino.webp', 'plate-arbeau-orchesographie.webp',
           'plate-feuillet-choregraphie.webp', 'plate-feuillet-folie-despagne.webp'];
?><!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Variante C, spina cronologica · Tersicore</title>
<link rel="stylesheet" href="<?= esc(asset('assets/test3.css')) ?>">
</head>
<body>
<p class="gate">Variante C di 3 &nbsp;·&nbsp; spina cronologica &nbsp;·&nbsp;
  <a href="/test1/">A</a> <a href="/test2/">B</a></p>

<header class="head">
  <div class="shell head__in">
    <a class="wordmark" href="/test3/">Tersicore</a>
    <nav><ul>
      <li><a href="#">Fonti</a></li><li><a href="#">Lessico</a></li>
      <li><a href="#">Iconografia</a></li><li><a href="#">Cronologia</a></li><li><a href="#">Metodo</a></li>
    </ul></nav>
  </div>
</header>

<main>
  <section class="intro">
    <div class="shell">
      <h1>Le fonti primarie della danza occidentale</h1>
      <p class="lead">Cinque secoli di trattati, manuali e sistemi di notazione, disposti sull'asse
      del tempo. Ogni voce porta al facsimile digitale presso l'istituzione che lo conserva.</p>
      <p class="meta"><?= count($F) ?> fonti<span class="sep"></span><?= count($L) ?> voci di lessico<span class="sep"></span><?= count($I) ?> opere</p>
    </div>
  </section>

  <nav class="rail"><div class="shell"><ul>
    <?php foreach ($per_secolo as $c => $rows): ?>
    <li><a href="#s<?= (int)$c ?>"><span class="rail__n"><?= esc(roman((int)$c)) ?></span><?= count($rows) ?></a></li>
    <?php endforeach; ?>
  </ul></div></nav>

  <div class="shell">
    <?php $pi = 0; foreach ($per_secolo as $c => $rows): ?>
    <section class="cent" id="s<?= (int)$c ?>">
      <div class="cent__head">
        <h2>Secolo <?= esc(roman((int)$c)) ?></h2>
        <p class="meta"><?= count($rows) ?> voci</p>
      </div>
      <div class="cent__body">
        <ul class="spine">
          <?php foreach (array_slice($rows, 0, 14) as $e): ?>
          <li data-kind="<?= esc($e['kind']) ?>">
            <span class="yr"><?= (int)$e['anno'] ?></span>
            <a href="#"><?= esc($e['t']) ?></a>
            <span class="sub"><?= esc($e['sub']) ?></span>
          </li>
          <?php endforeach; ?>
          <?php if (count($rows) > 14): ?>
          <li class="more"><a href="#">altre <?= count($rows) - 14 ?> voci del secolo</a></li>
          <?php endif; ?>
        </ul>
        <?php if ($pi < count($PLATES)): ?>
        <figure class="side">
          <img src="<?= esc(asset('assets/img/' . $PLATES[$pi])) ?>" alt="" loading="lazy">
          <figcaption>Tavola dal secolo <?= esc(roman((int)$c)) ?>. Pubblico dominio.</figcaption>
        </figure>
        <?php endif; $pi++; ?>
      </div>
    </section>
    <?php endforeach; ?>
  </div>
</main>

<footer class="foot"><div class="shell">
  <p class="wordmark wordmark--foot">tersicore.com</p>
  <p class="meta">Variante C: fondo terra d'ombra, testo osso, Cormorant Garamond su Alegreya Sans,
  la cronologia come pagina iniziale, tavole affiancate alla spina.</p>
</div></footer>
</body>
</html>
