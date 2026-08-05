<?php if (!defined('TERSICORE')) { http_response_code(404); exit; }

/**
 * Generic prose page: renders data/content/<slug>.md.
 * Serves /metodo/, /privacy/, /contatti/, /termini/ and /chi-siamo/, which are
 * the same shape and differ only in their text and their metadata.
 */
$PAGINE = [
    'metodo' => [
        'titolo' => 'Metodo',
        'descr'  => 'Come sono selezionate e verificate le schede, che cosa il sito afferma, '
                  . 'che cosa non afferma, e la regola di ammissione anteriore al 1900.',
    ],
    'chi-siamo' => [
        'titolo' => 'Chi siamo',
        'descr'  => 'Che cos\'è tersicore.com, come sono reperite e verificate le voci, '
                  . 'come funzionano le correzioni e come si sostiene il sito.',
    ],
    'privacy' => [
        'titolo' => 'Informativa privacy e cookie',
        'descr'  => 'Quali dati raccoglie questo sito, su quale base giuridica, per quanto tempo '
                  . 'e quali diritti puoi esercitare. Nessun cookie, nessun banner.',
    ],
    'contatti' => [
        'titolo' => 'Contatti',
        'descr'  => 'Come scrivere a chi pubblica tersicore.com per correzioni, segnalazioni '
                  . 'di fonti e richieste relative ai dati personali.',
    ],
    'termini' => [
        'titolo' => 'Termini di utilizzo',
        'descr'  => 'Finalità del sito, assenza di garanzie sui contenuti, link esterni, '
                  . 'diritti sui contenuti e legge applicabile.',
    ],
];

$slug_pagina = $route;
if (!isset($PAGINE[$slug_pagina])) { $status = 404; require __DIR__ . '/404.php'; return; }

$html = markdown($slug_pagina);
if ($html === '') { $status = 404; require __DIR__ . '/404.php'; return; }

$meta['title']       = $PAGINE[$slug_pagina]['titolo'];
$meta['description'] = $PAGINE[$slug_pagina]['descr'];

ob_start(); ?>
<section class="section">
  <div class="shell">
    <div class="measure prose">
      <?= $html ?>
    </div>
  </div>
</section>
<?php $content = ob_get_clean();
