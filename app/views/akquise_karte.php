<?php
/** @var array $punkte
 *  Karte mit Chancen (26.09.2026). Leaflet liegt bei uns (assets/vendor/leaflet,
 *  BSD-2); nur die Kartenkacheln kommen von OpenStreetMap -- in der Verwaltung,
 *  nie auf einer Kundenseite. Keine Kontaktdaten in den Punkten. */
$akqTeil = 'karte';
$zahl = ['gruen' => 0, 'gelb' => 0, 'grau' => 0, 'rot' => 0, 'blau' => 0];
foreach ($punkte as $pk) { $zahl[$pk['f']]++; }
?>
<div class="kopf"><div><h1>Karte</h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px">Wo die Chancen liegen. Grün: keine Website oder starke Chance · Gelb: Chance · Grau: schon angesprochen oder gering · Blau: Kunde · Rot: nicht ansprechen.</p></div></div>
<?php require __DIR__ . '/akquise_reiter.php'; ?>
<link rel="stylesheet" href="/assets/vendor/leaflet/leaflet.css">
<style>
  #akq_karte{height:min(70vh,680px);border-radius:14px;border:1px solid var(--linie);background:#080d1c}
  .akq-legende{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 10px}
  .akq-legende label{display:inline-flex;gap:6px;align-items:center;font-size:13px;padding:5px 11px;border:1px solid var(--linie);border-radius:999px;cursor:pointer;white-space:nowrap;margin:0;width:auto}
  .akq-legende input{width:auto;min-height:0;margin:0;padding:0}
  .akq-legende i{width:11px;height:11px;border-radius:50%;display:inline-block}
  .leaflet-popup-content{font:13.5px/1.45 system-ui,sans-serif}
  .leaflet-popup-content a{color:#8a6a22;font-weight:600}
</style>
<div class="block">
  <?php if (!$punkte): ?>
    <div class="leer">Noch keine Betriebe mit Standort. Die Recherche übernimmt die Koordinaten aus OpenStreetMap.</div>
  <?php else: ?>
    <div class="akq-legende">
      <?php foreach (['gruen' => ['#34d39b', 'Starke Chance'], 'gelb' => ['#e8b64c', 'Chance'], 'grau' => ['#8b847a', 'Angesprochen / gering'], 'blau' => ['#5aa9ff', 'Kunde'], 'rot' => ['#ef6b5b', 'Nicht ansprechen']] as $k => [$farbe, $wort]): ?>
        <label><input type="checkbox" data-farbe="<?= $k ?>" <?= $k === 'rot' ? '' : 'checked' ?>><i style="background:<?= $farbe ?>"></i><?= Fmt::h($wort) ?> (<?= (int) $zahl[$k] ?>)</label>
      <?php endforeach; ?>
    </div>
    <div id="akq_karte" role="region" aria-label="Karte der Betriebe"></div>
    <script type="application/json" id="akq_punkte"><?= json_encode($punkte, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    <script src="/assets/vendor/leaflet/leaflet.js"></script>
    <script>
    (function () {
      var P = JSON.parse(document.getElementById('akq_punkte').textContent);
      var FARBE = { gruen: '#34d39b', gelb: '#e8b64c', grau: '#8b847a', rot: '#ef6b5b', blau: '#5aa9ff' };
      var karte = L.map('akq_karte', { preferCanvas: true });
      L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>' }).addTo(karte);
      var ebenen = {}, grenzen = [];
      Object.keys(FARBE).forEach(function (f) { ebenen[f] = L.layerGroup(); });
      var basis = <?= json_encode(url('akquise/')) ?>;
      P.forEach(function (p) {
        var m = L.circleMarker([p.la, p.lo], { radius: p.f === 'gruen' ? 7 : 5, color: '#0a0908', weight: 1, fillColor: FARBE[p.f], fillOpacity: .92 });
        var box = document.createElement('div');
        var a = document.createElement('a'); a.href = basis + p.id; a.textContent = p.n; box.appendChild(a);
        var z = document.createElement('div'); z.textContent = [p.b, p.o, p.s !== null ? 'Chance ' + p.s : 'keine Website'].filter(Boolean).join(' · '); box.appendChild(z);
        m.bindPopup(box);
        ebenen[p.f].addLayer(m);
        if (p.f !== 'rot') { grenzen.push([p.la, p.lo]); }
      });
      document.querySelectorAll('.akq-legende input').forEach(function (c) {
        var f = c.dataset.farbe;
        if (c.checked) { ebenen[f].addTo(karte); }
        c.addEventListener('change', function () { if (c.checked) { ebenen[f].addTo(karte); } else { karte.removeLayer(ebenen[f]); } });
      });
      if (grenzen.length) { karte.fitBounds(grenzen, { padding: [30, 30], maxZoom: 14 }); } else { karte.setView([37.5, 14], 7); }
    })();
    </script>
  <?php endif; ?>
</div>
