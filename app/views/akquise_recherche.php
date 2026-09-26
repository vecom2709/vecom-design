<?php
/** @var array $laeufe @var array $branchen */
$akqTeil = 'recherche';
?>
<div class="kopf"><div><h1>Suchaufträge</h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px">Für den Alltag genügt „Jetzt suchen“ auf der Seite „Betriebe“. Hier geht es genauer:
    Ebene und Branchen selbst wählen, Aufträge ansehen und abbrechen. Dein Rechner holt jeden Auftrag nachts ab.</p></div></div>

<?php require __DIR__ . '/akquise_reiter.php'; ?>

<div class="zwei">
  <div class="block">
    <h2>Neuer Auftrag</h2>
    <form method="post" action="<?= Fmt::h(url('akquise')) ?>">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_lauf_anlegen"><input type="hidden" name="zurueck" value="akquise/recherche">
      <div class="reihe">
        <div class="feld"><label>Land</label><select name="land" required><option value="IT">Italien</option><option value="DE">Deutschland</option></select></div>
        <div class="feld"><label>Ebene</label><select name="ebene" required>
          <option value="auto">Automatisch (Ort, sonst Provinz, sonst Region)</option>
          <option value="stadt">Stadt / Comune</option><option value="kreis">Landkreis / Provincia</option>
          <option value="region">Bundesland / Regione</option><option value="plz">PLZ / CAP</option></select></div>
      </div>
      <div class="feld"><label>Gebiet (amtlicher Name, z. B. „Agrigento“, „Bad Kreuznach“, „Sicilia“, „92021“)</label>
        <input name="gebiet" required maxlength="120"></div>
      <div class="feld"><label>Branchen (keine Auswahl = alle)</label>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:4px 12px">
          <?php foreach ($branchen as $k => $b): ?>
            <label style="display:flex;gap:8px;align-items:center;font-size:13.5px"><input type="checkbox" name="branchen[]" value="<?= Fmt::h($k) ?>" style="width:auto">
              <?= Fmt::h((string) $b['de']) ?></label>
          <?php endforeach; ?>
        </div></div>
      <button class="knopf haupt">Auftrag anlegen</button>
      <p class="akq-klein" style="margin-top:8px">Große Gebiete (ganze Regionen) zerlegt der Worker selbst in Gemeinden und arbeitet sie nacheinander ab.
        Tipp: mit einer Stadt und zwei, drei Branchen anfangen — Qualität vor Menge.</p>
    </form>
  </div>
  <div class="block">
    <h2>So läuft der Worker</h2>
    <ol style="padding-left:18px;font-size:14px;color:var(--dim)">
      <li>Einmal: Schlüssel unter <a href="<?= Fmt::h(url('akquise/regeln')) ?>" style="text-decoration:underline">Compliance &amp; Versand</a> erzeugen und in <code>tools/akquise/.env</code> eintragen.</li>
      <li><code>npm run recherche</code> — holt Aufträge, meldet Firmen.</li>
      <li><code>npm run audit</code> — prüft Websites (Playwright, Lighthouse, eigene Prüfungen).</li>
      <li><code>npm run texte</code> — Claude deutet Befunde und schreibt Vorlagen (nur Score ≥ 51).</li>
      <li><code>npm run alles</code> — alle drei nacheinander; als geplante Windows-Aufgabe nachts.</li>
    </ol>
    <p class="akq-klein">Der Worker kann nichts versenden und nichts freigeben. Das geht nur hier.</p>
  </div>
</div>

<div class="block">
  <h2>Aufträge</h2>
  <?php if (!$laeufe): ?><div class="leer">Noch keine.</div><?php else: ?>
  <div class="tabellenrahmen"><table><thead><tr><th>#</th><th>Gebiet</th><th>Branchen</th><th>Status</th><th>Gefunden</th><th>Neu</th><th>Dubletten</th><th>Zeit</th><th></th></tr></thead><tbody>
    <?php foreach ($laeufe as $l): $br = $l['branchen'] ? (json_decode((string) $l['branchen'], true) ?: []) : []; ?>
      <tr><td class="akq-klein"><?= (int) $l['id'] ?></td>
        <td><?= Fmt::h((string) $l['land']) ?> · <?= Fmt::h((string) $l['ebene']) ?> <b><?= Fmt::h((string) $l['gebiet']) ?></b></td>
        <td class="akq-klein"><?= $br ? Fmt::h(implode(', ', array_map(static fn($x) => Akquise::branchenName((string) $x), $br))) : 'alle' ?></td>
        <td><span class="marke2 <?= ['fertig' => 'gut', 'fehler' => 'schlecht', 'laeuft' => 'warnung'][$l['status']] ?? '' ?>"><?= Fmt::h((string) $l['status']) ?></span>
          <?php if ($l['fehler']): ?><div class="akq-klein" style="color:var(--rot)"><?= Fmt::h(mb_strimwidth((string) $l['fehler'], 0, 160, '…')) ?></div><?php endif; ?></td>
        <td><?= (int) $l['gefunden'] ?></td><td><?= (int) $l['neu'] ?></td><td><?= (int) $l['dubletten'] ?></td>
        <td class="akq-klein"><?= Fmt::h(Fmt::zeit((string) ($l['beendet_am'] ?? $l['gestartet_am'] ?? $l['created_at']))) ?></td>
        <td><?php if (in_array($l['status'], ['wartet', 'laeuft'], true)): ?>
          <form method="post" action="<?= Fmt::h(url('akquise')) ?>"><?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_lauf_abbrechen">
            <input type="hidden" name="id" value="<?= (int) $l['id'] ?>"><button class="knopf" style="font-size:12px;padding:4px 10px">Abbrechen</button></form><?php endif; ?></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
</div>
