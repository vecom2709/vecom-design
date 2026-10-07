<?php
/** @var array $anzeigen @var array $seiten */
/* ANZEIGEN & BRANCHEN-SEITEN (28.09.2026, Uwe: Ja zu W1 und W3).
   Oben die Entwürfe der Woche für Facebook/Instagram und Google -- mit den
   echten, anonymen Zahlen aus den Prüfungen. Geschaltet wird hier nichts:
   Uwe kopiert, was er nehmen will, Budget und Start entscheidet er im
   Werbekonto. Unten die öffentlichen Branchen-Seiten, auf die die Anzeigen
   zeigen. */
$akqTeil = 'anzeigen';
?>
<div class="kopf"><div><h1>Anzeigen &amp; Branchen-Seiten</h1>
  <p style="color:var(--leise);font-size:var(--fs-klein);margin-top:6px">Jeden Montag fertige Anzeigentexte mit echten Durchschnittswerten je Branche und Ort — anonym, ab <?= BranchenStatistik::MIN ?> geprüften Betrieben.
    Nichts wird geschaltet: kopieren, im Werbekonto einfügen, Budget selbst wählen.</p></div></div>
<?php require __DIR__ . '/akquise_reiter.php'; ?>
<style>
  .az{display:grid;gap:12px}
  .az-karte{border:1px solid var(--linie);border-radius:12px;padding:12px 14px;background:var(--flaeche2)}
  .az-karte.erledigt{opacity:.55}
  .az-kopf{display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-bottom:8px}
  .az-karte label{display:block;font-size:var(--fs-mini);color:var(--leise);text-transform:uppercase;letter-spacing:.05em;margin:8px 0 3px}
  .az-karte textarea{width:100%;box-sizing:border-box;font-size:13.5px;line-height:1.5;padding:8px 10px;resize:vertical}
  .az-zahl{font-size:var(--fs-klein);color:var(--leise)}
  .az-seiten td,.az-seiten th{white-space:nowrap}
</style>

<div class="block">
  <div class="az-kopf"><h2 style="font-size:15px;margin:0">Entwürfe (letzte drei Wochen)</h2>
    <form method="post" action="<?= Fmt::h(url('akquise')) ?>"><?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_anzeigen_jetzt">
      <button class="knopf">Jetzt neu rechnen</button></form></div>
  <?php if (!$anzeigen): ?>
    <div class="leer">Noch keine Entwürfe. Sie entstehen, sobald mindestens eine Branche in einem Ort <?= BranchenStatistik::MIN ?> geprüfte Websites hat (die Prüfung läuft nachts auf deinem PC).</div>
  <?php else: ?>
    <div class="az">
    <?php foreach ($anzeigen as $a): $t = (array) (json_decode((string) $a['texte'], true) ?: []); $zeilen = 0; ?>
      <div class="az-karte <?= $a['status'] === 'erledigt' ? 'erledigt' : '' ?>">
        <div class="az-kopf"><b><?= Fmt::h($a['kanal'] === 'meta' ? 'Facebook / Instagram' : 'Google') ?> · <?= Fmt::h($a['slug']) ?> · <?= Fmt::h(strtoupper((string) $a['sprache'])) ?></b>
          <span class="az-zahl"><?= Fmt::h($a['woche']) ?></span>
          <form method="post" action="<?= Fmt::h(url('akquise')) ?>"><?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_anzeige_erledigt"><input type="hidden" name="anzeige" value="<?= (int) $a['id'] ?>">
            <button class="knopf"><?= $a['status'] === 'erledigt' ? 'Wieder offen' : 'Übernommen ✓' ?></button></form></div>
        <?php if ($a['kanal'] === 'meta'): ?>
          <label>Haupttext</label><textarea readonly rows="3"><?= Fmt::h((string) ($t['text'] ?? '')) ?></textarea>
          <label>Überschrift (≤ 40)</label><textarea readonly rows="1"><?= Fmt::h((string) ($t['ueberschrift'] ?? '')) ?></textarea>
          <label>Beschreibung (≤ 30)</label><textarea readonly rows="1"><?= Fmt::h((string) ($t['beschreibung'] ?? '')) ?></textarea>
        <?php else: ?>
          <label>Anzeigentitel (je ≤ 30)</label><textarea readonly rows="5"><?= Fmt::h(implode("\n", (array) ($t['titel'] ?? []))) ?></textarea>
          <label>Beschreibungen (je ≤ 90)</label><textarea readonly rows="2"><?= Fmt::h(implode("\n", (array) ($t['texte'] ?? []))) ?></textarea>
        <?php endif; ?>
        <label>Ziel-Adresse</label><textarea readonly rows="1"><?= Fmt::h((string) ($t['ziel'] ?? '')) ?></textarea>
      </div>
    <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="block">
  <h2 style="font-size:15px;margin:0 0 10px">Öffentliche Branchen-Seiten (<?= count($seiten) ?>)</h2>
  <?php if (!$seiten): ?>
    <div class="leer">Noch keine Seite: Dafür braucht eine Branche in einem Ort <?= BranchenStatistik::MIN ?> geprüfte Websites.</div>
  <?php else: ?>
    <div class="tabellenrahmen"><table class="az-seiten"><thead><tr><th>Seite</th><th>Betriebe</th><th>geprüft</th><th>ohne Website</th><th>langsam</th><th>Handy</th><th>Kontakt</th><th>Google</th></tr></thead><tbody>
      <?php foreach ($seiten as $s): $w = $s['werte']; ?>
        <tr><td><a href="<?= Fmt::h(BranchenStatistik::adresse($s, $s['land'] === 'DE' ? 'de' : 'it')) ?>" target="_blank" rel="noopener"><?= Fmt::h(Akquise::branchenName($s['branche']) . ' · ' . $s['ort']) ?></a></td>
          <td><?= (int) $s['n'] ?></td><td><?= (int) $s['geprueft'] ?></td>
          <?php foreach (['ohne', 'langsam', 'handy', 'kontakt', 'google'] as $k): ?><td><?= (int) ($w[$k] ?? 0) ?> %</td><?php endforeach; ?></tr>
      <?php endforeach; ?>
    </tbody></table></div>
    <p class="akq-klein" style="margin-top:8px">Übersicht für Google: <a href="/siti-web/" target="_blank" rel="noopener">/siti-web/</a> · Sitemap: <a href="/sitemap-branchen.php" target="_blank" rel="noopener">/sitemap-branchen.php</a></p>
  <?php endif; ?>
</div>
