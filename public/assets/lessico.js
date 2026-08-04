/* Lexicon filter: progressive enhancement over the server-rendered list.
   Reads its data from a <script type="application/json"> block, which is markup
   and passes the Content-Security-Policy. With JavaScript off the full
   alphabetical list is already present and correct. */
(function () {
  'use strict';
  var dataEl = document.getElementById('lessico-data');
  var form   = document.getElementById('lessico-filter');
  var list   = document.getElementById('lessico-list');
  var countEl = document.getElementById('filter-count');
  if (!dataEl || !form || !list) return;

  var rows;
  try { rows = JSON.parse(dataEl.textContent || '[]'); } catch (e) { return; }
  if (!rows.length) return;

  var bySlug = {};
  var items = list.querySelectorAll('li[data-slug]');
  for (var i = 0; i < items.length; i++) { bySlug[items[i].getAttribute('data-slug')] = items[i]; }

  var groups = list.querySelectorAll('.alpha');
  var q = document.getElementById('q');
  var trad = document.getElementById('tradizione');
  var ep = document.getElementById('epoca');

  function norm(s) {
    return (s || '').toLowerCase()
      .replace(/[àáâ]/g, 'a').replace(/[èéê]/g, 'e').replace(/[ìíî]/g, 'i')
      .replace(/[òóô]/g, 'o').replace(/[ùúû]/g, 'u').replace(/ç/g, 'c')
      .replace(/[^a-z0-9]+/g, ' ').trim();
  }

  function apply() {
    var term = norm(q.value);
    var t = trad.value, e = ep.value, shown = 0;

    for (var i = 0; i < rows.length; i++) {
      var r = rows[i], el = bySlug[r.s];
      if (!el) continue;
      var ok = (!t || r.r === t) && (!e || r.e === e) && (!term || r.k.indexOf(term) !== -1);
      el.classList.toggle('is-hidden', !ok);
      if (ok) shown++;
    }
    // Hide a letter heading when every entry under it is filtered out.
    for (var g = 0; g < groups.length; g++) {
      var visible = groups[g].querySelectorAll('li[data-slug]:not(.is-hidden)').length;
      groups[g].classList.toggle('is-hidden', visible === 0);
    }
    if (countEl) countEl.textContent = shown === 1 ? '1 voce' : shown + ' voci';
  }

  form.addEventListener('submit', function (ev) { ev.preventDefault(); });
  form.addEventListener('input', apply);
  form.addEventListener('change', apply);
})();
