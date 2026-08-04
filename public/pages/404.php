<?php if (!defined('TERSICORE')) { http_response_code(404); exit; }

$meta['title']       = 'Pagina non trovata';
$meta['description'] = 'La pagina richiesta non esiste su questo sito.';
$meta['canonical']   = SITE_URL . '/';

ob_start(); ?>
<section class="section">
  <div class="shell">
    <div class="measure">
      <p class="label">Errore 404</p>
      <h1>Pagina non trovata</h1>
      <p class="lead">L'indirizzo richiesto non corrisponde a nessuna scheda del sito. Può darsi che
      sia stato digitato in modo diverso, oppure che la pagina non sia mai esistita.</p>
      <hr class="rule-accent">
      <ul class="linklist">
        <li><a href="/fonti/">Fonti</a><span class="linklist__meta">trattati, manuali e sistemi di notazione</span></li>
        <li><a href="/lessico/">Lessico</a><span class="linklist__meta">i nomi delle danze e dei passi</span></li>
        <li><a href="/iconografia/">Iconografia</a><span class="linklist__meta">le opere che raffigurano la danza</span></li>
        <li><a href="/cronologia/">Cronologia</a><span class="linklist__meta">la linea del tempo</span></li>
      </ul>
    </div>
  </div>
</section>
<?php $content = ob_get_clean();
