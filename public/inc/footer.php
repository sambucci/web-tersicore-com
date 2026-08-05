<?php if (!defined('TERSICORE')) { http_response_code(404); exit; } ?>
</main>
<footer class="site-foot">
  <div class="shell site-foot__in">
    <p class="site-foot__mark">tersicore.com</p>
    <nav class="site-foot__nav" aria-label="Piè di pagina">
      <ul>
        <li><a href="/fonti/">Fonti</a></li>
        <li><a href="/lessico/">Lessico</a></li>
        <li><a href="/iconografia/">Iconografia</a></li>
        <li><a href="/cronologia/">Cronologia</a></li>
        <li><a href="/metodo/">Metodo</a></li>
        <li><a href="/crediti/">Crediti</a></li>
      </ul>
      <ul class="site-foot__legal">
        <li><a href="/chi-siamo/">Chi siamo</a></li>
        <li><a href="/contatti/">Contatti</a></li>
        <li><a href="/privacy/">Privacy e cookie</a></li>
        <li><a href="/termini/">Termini di utilizzo</a></li>
      </ul>
    </nav>
    <p class="site-foot__note">
      Le opere schedate sono anteriori al 1900 e di pubblico dominio. Le riproduzioni ospitate qui provengono
      da Wikimedia Commons e recano licenza esplicita o dichiarazione di pubblico dominio, con il credito
      completo in <a href="/crediti/">crediti</a>. Le scansioni restano presso le istituzioni che le conservano
      e sono raggiunte per collegamento.
    </p>
  </div>
</footer>
<script data-goatcounter="https://tersicore-com.goatcounter.com/count" async src="//gc.zgo.at/count.js"></script>
<script src="<?= esc(asset('assets/site.js')) ?>"></script>
<?php foreach (($GLOBALS['page_scripts'] ?? []) as $s): ?>
<script src="<?= esc(asset($s)) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
