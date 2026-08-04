<?php if (!defined('TERSICORE')) { http_response_code(404); exit; }
// STYLE GATE VARIANT B: "Frontespizio". Near-white bone ground, high-contrast
// Didone display (Playfair), symmetrical centred title-page opening between
// hairline rules, full-bleed detail crops at high zoom instead of mounted plates.
$F = fonti(); $L = lessico(); $I = iconografia();
$sample = array_slice(fonti_by_era('cinquecento'), 0, 3);
?><!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Variante B, frontespizio · Tersicore</title>
<link rel="stylesheet" href="<?= esc(asset('assets/test2.css')) ?>">
</head>
<body>
<p class="gate">Variante B di 3 &nbsp;·&nbsp; frontespizio &nbsp;·&nbsp;
  <a href="/test1/">A</a> <a href="/test3/">C</a></p>

<header class="head">
  <div class="shell head__in">
    <a class="wordmark" href="/test2/">Tersicore</a>
    <nav><ul>
      <li><a href="#">Fonti</a></li><li><a href="#">Lessico</a></li>
      <li><a href="#">Iconografia</a></li><li><a href="#">Cronologia</a></li><li><a href="#">Metodo</a></li>
    </ul></nav>
  </div>
</header>

<main>
  <!-- the title page: centred, symmetrical, ruled above and below -->
  <section class="title">
    <div class="shell">
      <p class="title__over">Trattati · manuali · sistemi di notazione</p>
      <hr class="hair">
      <h1>Le fonti primarie<br>della danza occidentale</h1>
      <hr class="hair">
      <p class="title__sub">Dal Quattrocento all'Ottocento</p>
      <p class="title__nums"><?= count($F) ?> fonti &nbsp;·&nbsp; <?= count($L) ?> voci &nbsp;·&nbsp; <?= count($I) ?> opere</p>
    </div>
  </section>

  <!-- full-bleed detail crop at high zoom, no mount, no overlay -->
  <figure class="bleed">
    <img src="<?= esc(asset('assets/img/hero-feuillet-detail.webp')) ?>"
         alt="Particolare della notazione coreografica incisa da Raoul-Auger Feuillet, 1700">
    <figcaption class="shell">Raoul-Auger Feuillet, notazione della Folie d'Espagne, 1700.
      Bibliothèque nationale de France, pubblico dominio.</figcaption>
  </figure>

  <section class="sec">
    <div class="shell measure">
      <p class="lead">La danza è stata scritta prima di essere filmata. Quello che resta di cinque
      secoli di pratica sta in un numero ristretto di libri, e ogni scheda di questo sito porta al
      facsimile digitale presso l'istituzione che conserva l'esemplare.</p>
    </div>
  </section>

  <section class="sec">
    <div class="shell">
      <h2 class="centered">Le quattro epoche</h2>
      <div class="eras">
        <?php foreach (ERE as $k => $e): $n = count(fonti_by_era($k)); if (!$n) continue; ?>
        <div class="era">
          <p class="era__n"><?= $n ?></p>
          <h3><?= esc($e['label']) ?></h3>
          <p class="meta"><?= esc($e['range']) ?></p>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="sec">
    <div class="shell">
      <h2 class="centered">Fonti del Cinquecento</h2>
      <ul class="grid">
        <?php $imgs = ['hero-caroso-detail.webp','hero-arbeau-detail.webp','hero-feuillet-detail.webp'];
        foreach ($sample as $i => $f): ?>
        <li class="card"><a href="#">
          <span class="card__img"><img src="<?= esc(asset('assets/img/' . $imgs[$i % 3])) ?>" alt="" loading="lazy"></span>
          <h3><?= esc($f['titolo']) ?></h3>
          <p class="meta"><?= esc($f['autore']) ?> · <?= esc((string)$f['anno']) ?></p>
        </a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
</main>

<footer class="foot"><div class="shell">
  <p class="wordmark wordmark--foot">tersicore.com</p>
  <p class="meta">Variante B: fondo osso, Didone ad alto contrasto (Playfair) su Alegreya Sans,
  apertura a frontespizio centrato fra filetti, ritagli di dettaglio a piena larghezza.</p>
</div></footer>
</body>
</html>
