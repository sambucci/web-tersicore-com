<?php if (!defined('TERSICORE')) { http_response_code(404); exit; }

$L = sort_by(lessico(), 'termine');
$meta['title'] = 'Lessico';
$meta['description'] = count($L) . ' termini della danza storica, dal Quattrocento all\'Ottocento, '
    . 'ognuno legato alla fonte primaria che per prima lo documenta.';
$GLOBALS['page_scripts'] = ['assets/lessico.js'];

// Data block for the filter. This is markup, not executable script, so it passes the CSP.
$filter_data = [];
foreach ($L as $t) {
    $filter_data[] = [
        's' => $t['slug'],
        't' => $t['termine'],
        'r' => $t['tradizione'] ?? '',
        'e' => $t['epoca'] ?? '',
        'k' => norm_key($t['termine'] . ' ' . implode(' ', $t['varianti'] ?? [])),
    ];
}

$gruppi = [];
foreach ($L as $t) {
    $l = mb_strtoupper(mb_substr(norm_key($t['termine']), 0, 1, 'UTF-8'), 'UTF-8');
    $gruppi[$l][] = $t;
}
ksort($gruppi);

ob_start(); ?>
<section class="section">
  <div class="shell">
    <div class="measure">
      <h1>Lessico</h1>
      <p class="lead">I nomi delle danze, dei passi e delle figure come li scrivono le fonti. Ogni voce
      rimanda al trattato o al manuale che per primo la documenta, con il collegamento al facsimile.</p>
      <p class="count"><?= count($L) ?> voci</p>
      <hr class="rule-accent">
    </div>

    <form class="filter" id="lessico-filter" role="search" aria-label="Filtra il lessico">
      <div class="filter__field">
        <label for="q">Cerca</label>
        <input type="search" id="q" name="q" autocomplete="off" placeholder="termine o variante">
      </div>
      <div class="filter__field">
        <label for="tradizione">Tradizione</label>
        <select id="tradizione" name="tradizione">
          <option value="">tutte</option>
          <?php foreach (TRADIZIONI as $k => $v): ?>
          <option value="<?= esc($k) ?>"><?= esc($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter__field">
        <label for="epoca">Epoca</label>
        <select id="epoca" name="epoca">
          <option value="">tutte</option>
          <?php foreach (ERE as $k => $v): ?>
          <option value="<?= esc($k) ?>"><?= esc($v['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <p class="filter__count" id="filter-count" aria-live="polite"><?= count($L) ?> voci</p>
    </form>

    <div class="measure" id="lessico-list">
      <?php foreach ($gruppi as $letter => $rows): ?>
      <section class="alpha" data-letter="<?= esc($letter) ?>">
        <h2 class="alpha__letter"><?= esc($letter) ?></h2>
        <ul class="linklist">
          <?php foreach ($rows as $t): ?>
          <li data-slug="<?= esc($t['slug']) ?>">
            <a href="/lessico/<?= esc($t['slug']) ?>/"><?= esc($t['termine']) ?></a>
            <span class="linklist__meta"><?= esc($t['tradizione'] ?? '') ?><?php
              if (!empty($t['epoca'])): ?><span class="meta__sep"></span><?= esc(era_label($t['epoca'])) ?><?php endif; ?></span>
          </li>
          <?php endforeach; ?>
        </ul>
      </section>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<script type="application/json" id="lessico-data"><?= json_encode($filter_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<?php $content = ob_get_clean();
