<?php
/** Akquise-CRM Modul H (06.10.2026): eine Kampagne — Trichter bis zum Umsatz, je Kanal und Textvariante, die Betriebe.
 *  @var array $k @var array $zeilen @var array $w */
$akqTeil = 'kampagnen';
$kaH = static fn(?string $s): string => Fmt::h((string) $s);
$kaGeld = Rechte::geld();
$kaFilter = json_decode((string) ($k['filter_json'] ?? ''), true) ?: [];
$kaFw = [];
if (isset($kaFilter['branche'])) { $kaFw[] = Akquise::branchenName((string) $kaFilter['branche']); }
if (isset($kaFilter['stadt'])) { $kaFw[] = (string) $kaFilter['stadt']; }
if (isset($kaFilter['prio'])) { $kaPs = AkquisePrio::STUFEN[$kaFilter['prio']] ?? $kaFilter['prio']; $kaFw[] = 'Priorität ' . (is_array($kaPs) ? ($kaPs[1] ?? '') : $kaPs); }
if (isset($kaFilter['spalte'])) { $kaFw[] = 'Stufe ' . (AkquiseCrm::SPALTEN[$kaFilter['spalte']] ?? $kaFilter['spalte']); }
$kaG = $w['gesamt'];
$kaMax = max(1, (int) $kaG['betriebe']);
$kaAktiv = (string) $k['status'] === 'aktiv';
$kaTabelle = static function (array $gruppen, callable $name) use ($kaH, $kaGeld): string {
    if (!$gruppen) { return '<div class="leer">Noch nichts verschickt.</div>'; }
    $h = '<div class="tabellenrahmen"><table class="ka-tab"><thead><tr><th></th><th>Kontaktiert</th><th>Antwort</th><th>Interesse</th><th>Angebot</th><th>Gewonnen</th>' . ($kaGeld ? '<th>Auftragswert</th>' : '') . '</tr></thead><tbody>';
    foreach ($gruppen as $g => $x) {
        $h .= '<tr><td>' . $kaH($name((string) $g)) . '</td><td>' . (int) $x['kontaktiert'] . '</td>';
        foreach (['antwort', 'interesse', 'angebot', 'gewonnen'] as $s) { $h .= '<td>' . (int) $x[$s] . '<small>' . AkquiseKampagne::anteil((int) $x[$s], (int) $x['kontaktiert']) . '</small></td>'; }
        $h .= ($kaGeld ? '<td>' . Fmt::geld((int) $x['auftragswert']) . '</td>' : '') . '</tr>';
    }
    return $h . '</tbody></table></div>';
};
?>
<div class="kopf"><div><p class="akq-klein"><a href="<?= $kaH(url('akquise/kampagnen')) ?>">← Alle Kampagnen</a></p>
  <h1><?= $kaH((string) $k['name']) ?> <?= $kaAktiv ? '' : '<span class="marke2">beendet</span>' ?></h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px">Filter beim Anlegen: <?= $kaH($kaFw ? implode(' · ', $kaFw) : '—') ?> · angelegt <?= $kaH(date('d.m.Y', strtotime((string) $k['created_at']))) ?> von <?= $kaH((string) $k['angelegt_von']) ?></p></div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <?php if ($kaAktiv): ?>
      <a class="knopf haupt" href="<?= $kaH(url('akquise/heute?kampagne=' . (int) $k['id'])) ?>">Damit arbeiten</a>
      <a class="knopf" href="<?= $kaH(url('akquise/pipeline?kampagne=' . (int) $k['id'])) ?>">Pipeline</a>
    <?php endif; ?>
    <form method="post" action="<?= $kaH(url('akquise')) ?>"><?= Csrf::feld() ?><input type="hidden" name="tat" value="<?= $kaAktiv ? 'akq_kampagne_beenden' : 'akq_kampagne_wieder' ?>"><input type="hidden" name="kampagne" value="<?= (int) $k['id'] ?>">
      <button class="knopf"><?= $kaAktiv ? 'Beenden' : 'Wieder aktiv' ?></button></form>
  </div></div>
<?php require __DIR__ . '/akquise_reiter.php'; ?>
<style>
  .ka-tab td,.ka-tab th{text-align:right;white-space:nowrap}
  .ka-tab td:first-child,.ka-tab th:first-child{text-align:left;white-space:normal}
  .ka-tab small{display:block;color:var(--leise);font-size:11.5px}
  .ka-tr{display:grid;grid-template-columns:150px minmax(0,1fr) 90px;gap:6px 12px;align-items:center;font-size:13.5px}
  .ka-tr i{display:block;height:10px;border-radius:999px;background:linear-gradient(115deg,#b98a31,#f7e6ae 45%,#c49438)}
  .ka-tr span:nth-child(3n){text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap} .ka-tr small{color:var(--leise);display:inline-block;width:48px;text-align:right}
  .ka-geld{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:8px;margin-top:14px}
  .ka-geld div{background:var(--flaeche2);border:1px solid var(--linie);border-radius:10px;padding:9px 11px;font-size:12.5px;color:var(--leise)}
  .ka-geld b{display:block;font-size:18px;color:var(--text)}
  .ka-zwei{display:grid;grid-template-columns:1fr;gap:0}
  @media (max-width:560px){.ka-tr{grid-template-columns:110px minmax(0,1fr) 70px}}
</style>

<div class="block">
  <h2 style="font-size:15px;margin:0 0 10px">Trichter</h2>
  <div class="ka-tr">
    <?php $vor = null; foreach (AkquiseKampagne::STUFEN as $s => $wort): $n = (int) $kaG[$s]; ?>
      <span><?= $kaH($wort) ?></span><span><i style="width:<?= max(1, (int) round(sqrt($n / $kaMax) * 100)) ?>%"></i></span>
      <span><?= $n ?><small><?= $vor !== null ? AkquiseKampagne::anteil($n, (int) $kaG[$vor]) : '' ?></small></span>
    <?php $vor = $s; endforeach; ?>
  </div>
  <?php if ($kaGeld): ?>
    <div class="ka-geld">
      <div><b><?= Fmt::geld((int) $kaG['auftragswert']) ?></b>Auftragswert</div>
      <div><b><?= Fmt::geld((int) $kaG['bezahlt']) ?></b>Bezahlt</div>
      <div><b><?= Fmt::geld((int) $kaG['provision']) ?></b>Partner-Provision</div>
      <div><b><?= (int) $kaG['gewonnen'] > 0 ? Fmt::geld((int) round((int) $kaG['auftragswert'] / (int) $kaG['gewonnen'])) : '—' ?></b>je gewonnenem Betrieb</div>
    </div>
  <?php endif; ?>
  <p class="akq-klein" style="margin:10px 0 0">Gezählt je Betrieb ab seiner Aufnahme. Der Balken ist wurzelskaliert, die kleine Zahl ist der Anteil an der Stufe davor.<?= $kaGeld ? ' Geld nur aus Bestellungen, Zahlungen und gebuchter Provision.' : '' ?></p>
</div>

<div class="ka-zwei">
  <div class="block"><h2 style="font-size:15px;margin:0 0 10px">Je Kanal</h2>
    <?= $kaTabelle($w['kanal'], static fn(string $g): string => AkquiseGate::KANAELE[$g] ?? $g) ?></div>
  <div class="block"><h2 style="font-size:15px;margin:0 0 10px">Je Textvariante</h2>
    <?= $kaTabelle($w['variante'], static fn(string $g): string => $g === 'Variante A' ? 'A — Befunde zuerst' : ($g === 'Variante B' ? 'B — Skizze zuerst' : $g)) ?></div>
</div>

<div class="block">
  <h2 style="font-size:15px;margin:0 0 10px">Betriebe <span class="marke2"><?= count($zeilen) ?></span></h2>
  <div class="tabellenrahmen"><table class="akq-tab"><thead><tr><th>Betrieb</th><th>Stufe</th><th>Aufgenommen</th><th></th></tr></thead><tbody>
    <?php foreach (array_slice($zeilen, 0, 500) as $z): ?>
      <tr><td><a href="<?= $kaH(url('akquise/' . (int) $z['id'])) ?>"><?= $kaH((string) $z['name']) ?></a><span class="akq-klein"> · <?= $kaH((string) $z['stadt']) ?></span></td>
        <td><?= $kaH(AkquiseCrm::SPALTEN[(string) $z['spalte']] ?? (string) $z['spalte']) ?></td>
        <td class="akq-klein"><?= $kaH(date('d.m.Y', strtotime((string) $z['hinzu_am']))) ?></td>
        <td><form method="post" action="<?= $kaH(url('akquise')) ?>"><?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_kampagne_weg"><input type="hidden" name="firma" value="<?= (int) $z['id'] ?>">
          <input type="hidden" name="kampagne" value="<?= (int) $k['id'] ?>"><input type="hidden" name="zurueck" value="akquise/kampagnen/<?= (int) $k['id'] ?>">
          <button class="knopf klein" title="Nur aus der Kampagne — der Betrieb bleibt">Herausnehmen</button></form></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
  <?php if (count($zeilen) > 500): ?><p class="akq-klein">Die ersten 500 von <?= count($zeilen) ?> — gezählt werden alle.</p><?php endif; ?>
  <p class="akq-klein" style="margin-top:8px">Einzelne Betriebe nehmen Sie in der Firmenakte unter „Profil & Notizen“ dazu.</p>
</div>
