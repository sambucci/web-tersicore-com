<?php if (!defined('TERSICORE')) { http_response_code(404); exit; }
// STYLE GATE VARIANT A: "Sala delle stampe". Stone mount ground, paper surfaces
// lighter than the page, oxide accent, EB Garamond over Alegreya Sans, text-led
// opening, plates in asymmetric print mounts.
$F = fonti(); $L = lessico(); $I = iconografia();
$sample = array_slice(fonti_by_era('cinquecento'), 0, 3);
?><!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Variante A, sala delle stampe · Tersicore</title>
<link rel="stylesheet" href="<?= esc(asset('assets/test1.css')) ?>">
</head>
<body>
<p class="gate">Variante A di 3 &nbsp;·&nbsp; sala delle stampe &nbsp;·&nbsp;
  <a href="/test2/">B</a> <a href="/test3/">C</a></p>

<header class="head">
  <div class="shell head__in">
    <a class="wordmark" href="/test1/"><span>Tersicore</span></a>
    <nav><ul>
      <li><a href="#">Fonti</a></li><li><a href="#">Lessico</a></li>
      <li><a href="#">Iconografia</a></li><li><a href="#">Cronologia</a></li><li><a href="#">Metodo</a></li>
    </ul></nav>
  </div>
</header>

<main>
  <section class="sec">
    <div class="shell">
      <div class="measure">
        <h1>Le fonti primarie della danza occidentale</h1>
        <p class="lead">Dal Quattrocento all'Ottocento la danza è stata scritta prima di essere
        filmata: in trattati, manuali e sistemi di notazione che restano l'unica testimonianza
        diretta di come si ballava.</p>
        <p>Questo sito scheda quelle fonti e collega ciascuna alla copia digitalizzata presso
        l'istituzione che la conserva. Ogni collegamento è aperto e verificato uno per uno.</p>
        <hr class="rule">
        <p class="meta"><?= count($F) ?> fonti<span class="sep"></span><?= count($L) ?> voci di lessico<span class="sep"></span><?= count($I) ?> opere</p>
      </div>
    </div>
  </section>

  <section class="sec">
    <div class="shell">
      <figure class="plate">
        <span class="plate__mount"><img src="<?= esc(asset('assets/img/plate-feuillet-folie-despagne.webp')) ?>"
          alt="Pagina di notazione coreografica della Folie d'Espagne di Pécour, incisa da Feuillet"></span>
        <figcaption>
          <span class="cap__t">Folie d'Espagne di M. Pécour, messa in coreografia da M. Feuillet, 1700</span>
          <span class="cap__c">Bibliothèque nationale de France, pubblico dominio</span>
        </figcaption>
      </figure>
    </div>
  </section>

  <section class="sec">
    <div class="shell">
      <h2>Le quattro epoche</h2>
      <div class="eras">
        <?php foreach (ERE as $k => $e): $n = count(fonti_by_era($k)); if (!$n) continue; ?>
        <div class="era"><h3><?= esc($e['label']) ?></h3>
          <p class="meta"><?= esc($e['range']) ?><span class="sep"></span><?= $n ?> fonti</p></div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="sec">
    <div class="shell">
      <h2>Fonti del Cinquecento</h2>
      <ul class="grid">
        <?php $imgs = ['card-caroso-ballarino.webp','card-arbeau-orchesographie.webp','card-feuillet-choregraphie.webp'];
        foreach ($sample as $i => $f): ?>
        <li class="card"><a href="#">
          <figure class="plate plate--sm"><span class="plate__mount">
            <img src="<?= esc(asset('assets/img/' . $imgs[$i % 3])) ?>" alt="" loading="lazy"></span></figure>
          <h3><?= esc($f['titolo']) ?></h3>
          <p class="meta"><?= esc($f['autore']) ?><span class="sep"></span><?= esc((string)$f['anno']) ?></p>
        </a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
</main>

<footer class="foot"><div class="shell">
  <p class="foot__mark">tersicore.com</p>
  <p class="meta">Variante A: fondo pietra da passe-partout, superfici più chiare della pagina,
  accento ossido, EB Garamond su Alegreya Sans, apertura testuale, tavole montate.</p>
</div></footer>
</body>
</html>
