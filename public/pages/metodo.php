<?php if (!defined('TERSICORE')) { http_response_code(404); exit; }

$meta['title'] = 'Metodo';
$meta['description'] = 'Come sono selezionate e verificate le schede, che cosa il sito afferma, '
    . 'che cosa non afferma, e la regola di ammissione anteriore al 1900.';

$html = markdown('metodo');

ob_start(); ?>
<section class="section">
  <div class="shell">
    <div class="measure prose">
      <?php if ($html): ?><?= $html ?><?php else: ?>
      <h1>Metodo</h1>
      <p>Pagina in preparazione.</p>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php $content = ob_get_clean();
