<?php
/* Partner › Reservierungen (Akquise-CRM Modul F, 06.10.2026). Nur Admin.
   Uwe: Ampel „24h/48h Partner, 72h Sie“, Provision „Sie entscheiden je Fall“, Auswertung „Funnel + Reaktionszeit“.
   Daten: $reservierungen, $auswertung, $partner, $entscheide. */
$prH = static fn(?string $s): string => Fmt::h((string) $s);
$prAmpel = ['gelb' => ['🟡', 'seit über 24 h'], 'rot' => ['🔴', 'seit über 48 h — Partner wurde erinnert'], 'admin' => ['⛔', 'seit über 72 h — du entscheidest']];
$prOffen = count(array_filter($reservierungen, static fn($r) => ($r['signal']['stufe'] ?? '') !== ''));
?>
<style>
  .pr-tab{width:100%;border-collapse:collapse;font-size:13.5px}
  .pr-tab th{font-size:11.5px;text-transform:uppercase;letter-spacing:.05em;color:var(--leise);text-align:left;padding:6px 8px;border-bottom:1px solid var(--linie)}
  .pr-tab td{padding:8px;border-bottom:1px solid var(--linie);vertical-align:top}
  .pr-tab td.z{font-variant-numeric:tabular-nums;text-align:right}
  .pr-rot td{background:rgba(255,138,138,.06)}
  .pr-sig{font-size:13px} .pr-sig small{display:block;color:var(--leise)}
  .pr-tat summary{cursor:pointer;color:var(--cyan);font-size:13px}
  .pr-tat form{margin:8px 0 0;display:flex;flex-direction:column;gap:6px;max-width:320px}
  .pr-tat select,.pr-tat input{width:100%}
  .pr-prov{display:flex;gap:12px;font-size:13px} .pr-prov label{display:flex;gap:5px;align-items:center} .pr-prov input{width:auto}
  .pr-wrap{overflow-x:auto}
</style>
<div class="kopf"><div><h1>Reservierungen der Partner</h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px">Neue Reservierungen gelten <?= AkquisePartner::TAGE ?> Tage, in den letzten <?= AkquisePartner::VERLAENGERN_AB ?> Tagen einmal verlängerbar.
    Meldet sich ein reservierter Betrieb (positive Antwort, Website-Check) und der Partner reagiert nicht: nach 24 h steht es in seinem „Heute“, nach 48 h bekommt er einen Hinweis, nach 72 h du.</p></div></div>

<div class="block">
  <h2>Offene Signale <span class="marke2"><?= $prOffen ?></span></h2>
  <?php if (!$reservierungen): ?><div class="leer">Gerade ist kein Betrieb bei einem Partner reserviert.</div><?php else: ?>
  <div class="pr-wrap"><table class="pr-tab">
    <thead><tr><th>Betrieb</th><th>Partner</th><th>Reserviert bis</th><th>Signal</th><th>Was tun</th></tr></thead>
    <tbody>
    <?php foreach ($reservierungen as $r): $s = $r['signal']; $st = $s['stufe'] ?? ''; ?>
      <tr class="<?= in_array($st, ['rot', 'admin'], true) ? 'pr-rot' : '' ?>">
        <td><a href="<?= $prH(url('akquise/' . (int) $r['firma_id'])) ?>"><?= $prH((string) $r['firma']) ?></a><small style="display:block;color:var(--leise)"><?= $prH((string) $r['stadt']) ?></small></td>
        <td><?= $prH((string) $r['partner']) ?><?= $r['herkunft'] === 'vecom' ? '<small style="display:block;color:var(--leise)">Anrufliste von Vecom</small>' : '' ?></td>
        <td><?= $prH(date('d.m.Y', strtotime((string) $r['bis']))) ?><?= !empty($r['verlaengert_am']) ? '<small style="display:block;color:var(--leise)">einmal verlängert</small>' : '' ?></td>
        <td class="pr-sig"><?php if ($s): ?><?= $st !== '' ? $prAmpel[$st][0] . ' ' : '' ?><?= $prH($s['wort']) ?><small>vor <?= (int) $s['stunden'] ?> h<?= $st !== '' ? ' · ' . $prH($prAmpel[$st][1]) : ' · noch im Rahmen' ?></small><?php else: ?><span style="color:var(--leise)">—</span><?php endif; ?></td>
        <td class="pr-tat">
          <details><summary>Entscheiden …</summary>
            <form method="post" action="<?= $prH(url('')) ?>"><?= Csrf::feld() ?><input type="hidden" name="tat" value="partner_res_entscheiden"><input type="hidden" name="firma" value="<?= (int) $r['firma_id'] ?>">
              <select name="aktion" required>
                <option value="uebernommen">Vecom übernimmt</option>
                <option value="neu_zugewiesen">Neu zuweisen an …</option>
                <option value="geloest">Reservierung lösen (wieder frei)</option>
              </select>
              <select name="neu_partner" aria-label="Neuer Partner"><option value="">— neuer Partner (nur bei „Neu zuweisen“) —</option>
                <?php foreach ($partner as $pp): if ((int) $pp['id'] === (int) $r['partner_id']) { continue; } ?><option value="<?= (int) $pp['id'] ?>"><?= $prH((string) $pp['name']) ?></option><?php endforeach; ?></select>
              <div class="pr-prov" role="radiogroup" aria-label="Provision für <?= $prH((string) $r['partner']) ?>"><span>Provision <?= $prH((string) $r['partner']) ?>:</span>
                <label><input type="radio" name="provision" value="bleibt" required> bleibt</label><label><input type="radio" name="provision" value="entfaellt"> entfällt</label></div>
              <input name="grund" maxlength="255" placeholder="Grund (für die Prüfspur)">
              <button class="knopf klein">Entscheidung festhalten</button>
            </form>
          </details>
          <?php if ($s): /* Erinnern nur, wo es etwas zu erinnern gibt */ ?>
          <form method="post" action="<?= $prH(url('')) ?>" style="margin-top:6px"><?= Csrf::feld() ?><input type="hidden" name="tat" value="partner_res_erinnern"><input type="hidden" name="firma" value="<?= (int) $r['firma_id'] ?>">
            <button class="knopf klein">Partner erinnern</button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>

<div class="block">
  <h2>Partner im Vergleich</h2>
  <p class="akq-klein" style="margin:-4px 0 10px">Funnel aus den Leads der Partner (wer im Angebot steht, zählt auch als kontaktiert und interessiert). Angebote, Umsatz (bezahlt, seit der Zuordnung) und Provision aus den zugeordneten Kunden. Reaktionszeit: Median der Stunden zwischen positiver Antwort und der ersten Aktivität des Partners danach, letzte 90 Tage.</p>
  <?php if (!$auswertung): ?><div class="leer">Noch keine aktiven Partner.</div><?php else: ?>
  <div class="pr-wrap"><table class="pr-tab">
    <thead><tr><th>Partner</th><th class="z">Reserviert</th><th class="z">Kontaktiert</th><th class="z">Interesse</th><th class="z">Angebot</th><th class="z">Kunde</th><th class="z">Angebote gesendet</th><th class="z">Umsatz</th><th class="z">Provision</th><th class="z">Reaktion</th><th class="z">Warnungen</th></tr></thead>
    <tbody><?php foreach ($auswertung as $a): ?>
      <tr><td><?= $prH($a['name']) ?></td><td class="z"><?= (int) $a['reserviert'] ?></td><td class="z"><?= (int) $a['kontaktiert'] ?></td><td class="z"><?= (int) $a['interesse'] ?></td>
        <td class="z"><?= (int) $a['angebot'] ?></td><td class="z"><?= (int) $a['kunde'] ?></td>
        <td class="z"><?= (int) $a['angebote_gesendet'] ?></td><td class="z"><?= Fmt::geld((int) $a['umsatz']) ?></td><td class="z"><?= Fmt::geld((int) $a['provision']) ?></td><td class="z"><?= $a['reaktion_h'] === null ? '—' : (int) $a['reaktion_h'] . ' h' ?></td>
        <td class="z"><?= (int) $a['warnungen'] > 0 ? '<b style="color:#ff9b9b">' . (int) $a['warnungen'] . '</b>' : '0' ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>

<?php if ($entscheide): ?>
<div class="block">
  <h2>Letzte Entscheidungen</h2>
  <ul style="margin:0;padding-left:18px;font-size:13.5px;line-height:1.6;color:var(--dim)">
    <?php foreach ($entscheide as $e): ?>
      <li><?= $prH(date('d.m.Y H:i', strtotime((string) $e['created_at']))) ?> · <?= $prH((string) $e['firma']) ?> ·
        <?= $prH(['uebernommen' => 'Vecom übernimmt', 'neu_zugewiesen' => 'neu zugewiesen an ' . ($e['neu'] ?? '—'), 'geloest' => 'gelöst'][$e['aktion']] ?? (string) $e['aktion']) ?>
        (von <?= $prH((string) $e['partner']) ?>, Provision <?= $e['provision'] === 'bleibt' ? 'bleibt' : 'entfällt' ?>)<?= $e['grund'] ? ' — ' . $prH((string) $e['grund']) : '' ?> · <?= $prH((string) $e['von']) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>
