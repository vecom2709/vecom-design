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
  #akq_karte{height:min(70vh,680px);border-radius:14px;border:1px solid var(--linie);background:#091128}
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
<?php /* Gebietsplan (07.10.2026, Kunden finden 13): welche Gebiete durchsucht sind, welches als Nächstes dran ist. */
$gb = $gebiete ?? ['plan' => [], 'orte' => [], 'auto' => false]; ?>
<div class="block" id="gebiete">
  <h2 style="margin:0 0 4px">Gebietsplan</h2>
  <p class="akq-klein" style="margin:0 0 12px">Ist die Automatik an, legt die Verwaltung jeden Tag das nächste fällige Gebiet als Suche an — nur, wenn „Betriebe suchen“ eingeschaltet ist und nichts wartet. Ein Gebiet ist nach <?= KundenFinden::GEBIET_TAGE ?> Tagen wieder fällig.</p>
  <?php if ($gb['plan']): ?>
  <div class="tabellenrahmen"><table><thead><tr><th>Gebiet</th><th>Zuletzt durchsucht</th><th>Gefunden / neu</th><th>Stand</th></tr></thead><tbody>
    <?php foreach ($gb['plan'] as $g): ?>
      <tr><td><b><?= Fmt::h((string) $g['gebiet']) ?></b></td>
        <td><?= $g['fertig_am'] ? Fmt::h(date('d.m.Y', strtotime((string) $g['fertig_am']))) : '<span class="akq-klein">noch nie</span>' ?></td>
        <td><?= (int) $g['gefunden'] ?> / <?= (int) $g['neu'] ?></td>
        <td><?= match ((string) $g['status']) { 'wartet' => '⏳ wartet auf deinen PC', 'laeuft' => '🔎 läuft', 'fehler' => '⚠ Fehler beim letzten Lauf', default => $g['faellig'] ? 'fällig' : '✓ aktuell' } ?></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
  <?php if (Rechte::darfTat('akq_gebiete_speichern')): ?>
  <form method="post" action="<?= Fmt::h(url('akquise')) ?>" style="margin-top:12px;display:grid;gap:10px;max-width:640px"><?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_gebiete_speichern">
    <label for="akq_plan">Gebiete, eines je Zeile (z. B. „Provinz Agrigento“, „Sciacca“, „Landkreis Mainz-Bingen“)</label>
    <textarea id="akq_plan" name="plan" rows="7"><?= Fmt::h((string) ($planText ?? '')) ?></textarea>
    <label class="akq-haken" style="margin:0"><input type="checkbox" name="auto" value="1" <?= !empty($gb['auto']) ? 'checked' : '' ?> style="width:auto;min-height:0"> Automatik: das nächste fällige Gebiet selbst anlegen</label>
    <button class="knopf haupt" style="justify-self:start">Speichern</button>
  </form>
  <?php endif; ?>
</div>
<?php if ($gb['orte']): ?>
<div class="block">
  <h2 style="margin:0 0 8px">Abdeckung je Ort</h2>
  <div class="tabellenrahmen"><table><thead><tr><th>Ort</th><th>Betriebe</th><th>ohne Website</th><th>Chancen</th><th>angesprochen</th><th>Kunden</th><th>zuletzt gesucht</th></tr></thead><tbody>
    <?php foreach (array_slice($gb['orte'], 0, 80) as $o): ?>
      <tr><td><a href="<?= Fmt::h(url('akquise') . '?stadt=' . rawurlencode((string) $o['stadt'])) ?>"><?= Fmt::h((string) $o['stadt']) ?></a><?= (string) $o['kreis'] !== '' ? ' <span class="akq-klein">' . Fmt::h((string) $o['kreis']) . '</span>' : '' ?></td>
        <td><?= (int) $o['betriebe'] ?></td><td><?= (int) $o['ohne_web'] ?></td><td><?= (int) $o['chancen'] ?></td><td><?= (int) $o['angesprochen'] ?></td><td><?= (int) $o['kunden'] ?></td>
        <td><?= $o['zuletzt'] ? Fmt::h(date('d.m.Y', strtotime((string) $o['zuletzt']))) : '—' ?></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
</div>
<?php endif; ?>
