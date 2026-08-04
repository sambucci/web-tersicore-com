<?php if (!defined('TERSICORE')) { http_response_code(404); exit; }

$C = credits();
usort($C, fn($a, $b) => strcmp((string)($a['title'] ?? ''), (string)($b['title'] ?? '')));
$meta['title'] = 'Crediti';
$meta['description'] = 'Autore, istituzione e licenza di ogni immagine ospitata su questo sito.';

ob_start(); ?>
<section class="section">
  <div class="shell">
    <div class="measure">
      <h1>Crediti</h1>
      <p class="lead">Ogni immagine ospitata qui proviene da Wikimedia Commons e reca una licenza
      esplicita o una dichiarazione di pubblico dominio. Qui sotto sono elencate una per una con
      l'autore effettivo, l'istituzione che conserva l'originale e la licenza.</p>
      <p>Le scansioni dei trattati non sono ospitate su questo sito: restano presso le istituzioni che
      le conservano e sono raggiunte per collegamento dalle schede delle fonti.</p>
      <hr class="rule-accent">

      <?php if (!$C): ?>
      <p>Nessuna immagine di terzi è attualmente ospitata sul sito.</p>
      <?php else: ?>
      <p class="count"><?= count($C) ?> immagini</p>
      <ul class="linklist">
        <?php foreach ($C as $c): ?>
        <li>
          <?php if (!empty($c['source_url'])): ?>
          <a href="<?= esc($c['source_url']) ?>"><?= esc($c['title'] ?: $c['file']) ?></a>
          <?php else: ?>
          <strong><?= esc($c['title'] ?: $c['file']) ?></strong>
          <?php endif; ?>
          <span class="linklist__meta">
            <?= esc($c['author'] ?? 'autore non indicato') ?><?php
            if (!empty($c['institution'])): ?><span class="meta__sep"></span><?= esc($c['institution']) ?><?php endif;
            if (!empty($c['license'])): ?><span class="meta__sep"></span><?php
              if (!empty($c['license_url'])): ?><a href="<?= esc($c['license_url']) ?>"><?= esc($c['license']) ?></a><?php
              else: ?><?= esc($c['license']) ?><?php endif;
            endif;
            if (!empty($c['modifications'])): ?><span class="meta__sep"></span><?= esc($c['modifications']) ?><?php endif; ?>
          </span>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php $content = ob_get_clean();
