<?php
/** Akquise-CRM Modul H (06.10.2026): Kampagnen — feste Gruppen von Betrieben mit Trichter bis zum Umsatz.
 *  Uwe: „Feste Gruppe aus Filter“, „Bis zum Umsatz“, „Als Filter überall“. Geld sieht nur der Admin.
 *  @var array $uebersicht @var array $branchen @var array $werte @var int $kampagne */
$akqTeil = 'kampagnen';
$kaH = static fn(?string $s): string => Fmt::h((string) $s);
$kaGeld = Rechte::geld();
?>
<div class="kopf"><div><h1>Kampagnen</h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px">Eine Kampagne ist eine feste Gruppe von Betrieben, zum Beispiel „Restaurants Sciacca Herbst“. Gezählt wird je Betrieb ab seiner Aufnahme.
    Verschickt wird dadurch nichts — jede Nachricht bleibt einzeln geprüft.</p></div></div>
<?php require __DIR__ . '/akquise_reiter.php'; ?>
<style>
  .ka-form{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:8px;align-items:end}
  .ka-form .breit{grid-column:span 2}
  .ka-form .feld{margin:0} .ka-form select,.ka-form input{width:100%}
  .ka-tab td,.ka-tab th{text-align:right;white-space:nowrap}
  .ka-tab td:first-child,.ka-tab th:first-child{text-align:left;white-space:normal}
  .ka-tab small{display:block;color:var(--leise);font-size:11.5px}
  .ka-aus{opacity:.6}
</style>

<div class="block">
  <h2 style="font-size:15px;margin:0 0 10px">Neue Kampagne</h2>
  <form method="post" action="<?= $kaH(url('akquise')) ?>" class="ka-form"><?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_kampagne_neu">
    <div class="feld breit"><label for="ka-name">Name</label><input id="ka-name" name="name" maxlength="120" required placeholder="z. B. Restaurants Sciacca Herbst"></div>
    <div class="feld"><label for="ka-branche">Branche</label><select id="ka-branche" name="branche"><option value="">— alle —</option>
      <?php foreach (array_keys($branchen) as $k): ?><option value="<?= $kaH((string) $k) ?>"><?= $kaH(Akquise::branchenName((string) $k)) ?></option><?php endforeach; ?></select></div>
    <div class="feld"><label for="ka-stadt">Ort</label><input id="ka-stadt" name="stadt" list="ka-orte" maxlength="120"><datalist id="ka-orte"><?php foreach ($werte['stadt'] ?? [] as $o): ?><option value="<?= $kaH((string) $o) ?>"><?php endforeach; ?></datalist></div>
    <div class="feld"><label for="ka-prio">Priorität</label><select id="ka-prio" name="prio"><option value="">— alle —</option>
      <?php foreach (AkquisePrio::STUFEN as $k => $w): ?><option value="<?= $kaH((string) $k) ?>"><?= $kaH(implode(' ', (array) $w)) ?></option><?php endforeach; ?></select></div>
    <div class="feld"><label for="ka-spalte">Stufe</label><select id="ka-spalte" name="spalte"><option value="">— alle —</option>
      <?php foreach (AkquiseCrm::SPALTEN as $k => $w): if (in_array($k, ['gesperrt', 'kein_interesse'], true)) { continue; } ?><option value="<?= $kaH($k) ?>"><?= $kaH($w) ?></option><?php endforeach; ?></select></div>
    <div><button class="knopf haupt">Kampagne anlegen</button></div>
  </form>
  <p class="akq-klein" style="margin:8px 0 0">Übernommen werden alle passenden Betriebe (höchstens <?= AkquiseKampagne::MAX_BETRIEBE ?>), gesperrte nie. Danach ändert sich die Gruppe nur von Hand — so bleiben die Zahlen vergleichbar.</p>
</div>

<div class="block">
  <h2 style="font-size:15px;margin:0 0 10px">Alle Kampagnen</h2>
  <?php if (!$uebersicht): ?><div class="leer">Noch keine Kampagne. Oben anlegen — zum Beispiel alle Restaurants in einem Ort.</div><?php else: ?>
  <div class="tabellenrahmen"><table class="ka-tab"><thead><tr><th>Kampagne</th>
    <?php foreach (AkquiseKampagne::STUFEN as $w): ?><th><?= $kaH($w) ?></th><?php endforeach; ?>
    <?php if ($kaGeld): ?><th>Auftragswert</th><th>Bezahlt</th><?php endif; ?></tr></thead><tbody>
    <?php foreach ($uebersicht as ['k' => $k, 'w' => $w]): $vor = null; ?>
      <tr class="<?= $k['status'] !== 'aktiv' ? 'ka-aus' : '' ?>">
        <td><a href="<?= $kaH(url('akquise/kampagnen/' . (int) $k['id'])) ?>"><?= $kaH((string) $k['name']) ?></a><?= (int) $kampagne === (int) $k['id'] ? ' <span class="marke2 gut">Arbeitsfilter</span>' : '' ?>
          <small><?= $k['status'] === 'aktiv' ? 'seit ' . $kaH(date('d.m.Y', strtotime((string) $k['created_at']))) : 'beendet' ?></small></td>
        <?php foreach (array_keys(AkquiseKampagne::STUFEN) as $s): ?>
          <td><?= (int) $w[$s] ?><?php if ($vor !== null): ?><small><?= AkquiseKampagne::anteil((int) $w[$s], (int) $w[$vor]) ?></small><?php endif; ?></td>
        <?php $vor = $s; endforeach; ?>
        <?php if ($kaGeld): ?><td><?= Fmt::geld((int) $w['auftragswert']) ?></td><td><?= Fmt::geld((int) $w['bezahlt']) ?></td><?php endif; ?>
      </tr>
    <?php endforeach; ?>
  </tbody></table></div>
  <p class="akq-klein" style="margin-top:8px">Die kleine Zahl ist der Anteil an der Stufe davor. „Kontaktiert“ = eine Nachricht ging raus (oder von Hand vermerkt); „Interesse“ = positive Antwort oder Termin.</p>
  <?php endif; ?>
</div>
