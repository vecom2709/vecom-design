<?php
/** @var array $kommend @var array $vorbei @var array $plan @var array $e @var bool $an @var int $freiZahl */
/* Termine (27.09.2026). Oben die nächsten Gespräche, darunter die eigenen
   Sprechzeiten. Die Buchungsseite ist vecom-design.it/termin.php. */
$akqTeil = 'termine';
$wt = [1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag'];
$themen = ['neu' => 'Neue Website', 'ueberarbeiten' => 'Website erneuern', 'analyse' => 'Analyse besprechen', 'preis' => 'Preise und Ablauf', 'sonst' => 'Etwas anderes'];
?>
<div class="kopf"><div><h1>Termine</h1>
  <p style="color:var(--leise);font-size:var(--fs-klein);margin-top:6px">Interessenten buchen selbst unter
    <a href="/termin.php" target="_blank" rel="noopener" style="text-decoration:underline">vecom-design.it/termin.php</a> — aus deinen Sprechzeiten unten.
    Bestätigung und Erinnerung am Vortag gehen automatisch raus.</p></div></div>

<?php require __DIR__ . '/akquise_reiter.php'; ?>

<div class="block" id="kommend">
  <h2>Nächste Gespräche <span class="akq-klein" style="font-weight:400">· <?= count($kommend) ?></span></h2>
  <?php if (!$kommend): ?><p class="akq-klein"><?= $freiZahl ? 'Noch keine Buchung. ' . $freiZahl . ' freie Zeiten sind buchbar.' : 'Noch keine Buchung — und keine freien Zeiten: Trag unten deine Sprechzeiten ein.' ?></p>
  <?php else: ?>
  <div class="tabellenrahmen"><table><tbody>
    <?php foreach ($kommend as $t): $b = strtotime((string) $t['beginn']); ?>
      <tr><td style="width:150px"><b><?= Fmt::h(AkquiseTermin::zeitText($b, 'de')) ?></b><div class="akq-klein"><?= $t['art'] === 'video' ? 'Video' : 'Telefon' ?> · <?= Fmt::h(strtoupper((string) $t['sprache'])) ?></div></td>
          <td><?= Fmt::h((string) $t['name']) ?><?= $t['firma'] ? ' · ' . Fmt::h((string) $t['firma']) : '' ?>
            <div class="akq-klein"><a href="mailto:<?= Fmt::h((string) $t['email']) ?>" style="text-decoration:underline"><?= Fmt::h((string) $t['email']) ?></a><?= $t['telefon'] ? ' · ' . Fmt::h((string) $t['telefon']) : '' ?>
              · <?= Fmt::h($themen[$t['thema']] ?? (string) $t['thema']) ?><?= $t['firma_id'] ? ' · <a href="' . Fmt::h(url('akquise/' . (int) $t['firma_id'])) . '" style="color:var(--cyan)">Betrieb</a>' : '' ?></div>
            <?php if ($t['nachricht']): ?><div class="akq-klein" style="margin-top:4px">„<?= Fmt::h((string) $t['nachricht']) ?>“</div><?php endif; ?></td>
          <td style="text-align:right;white-space:nowrap">
            <form method="post" action="<?= Fmt::h(url('akquise')) ?>" style="display:inline"><?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_termin_erledigt"><input type="hidden" name="termin" value="<?= (int) $t['id'] ?>"><button class="knopf klein">Erledigt</button></form>
            <form method="post" action="<?= Fmt::h(url('akquise')) ?>" style="display:inline" data-frage="Termin absagen? <?= Fmt::h((string) $t['name']) ?> bekommt eine Mail mit dem Link zu einer neuen Zeit." data-ja="Ja, absagen"><?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_termin_absagen"><input type="hidden" name="termin" value="<?= (int) $t['id'] ?>"><button class="knopf klein">Absagen</button></form></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
</div>

<div class="block" id="zeiten">
  <h2>Deine Sprechzeiten</h2>
  <p class="akq-klein" style="margin:0 0 12px">Je Tag Zeitfenster wie <code>10:00-12:00, 15:00-17:30</code>. Leer = an dem Tag keine Termine.</p>
  <form method="post" action="<?= Fmt::h(url('akquise')) ?>"><?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_termin_einstellungen">
    <div class="rg-grenzen" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:0 12px">
      <?php foreach ($wt as $n => $name): ?>
        <div class="feld"><label><?= $name ?></label><input name="plan[<?= $n ?>]" value="<?= Fmt::h((string) ($plan[$n] ?? '')) ?>" placeholder="z. B. 10:00-12:00"></div>
      <?php endforeach; ?>
    </div>
    <div class="reihe">
      <div class="feld"><label>Dauer je Gespräch (Minuten)</label><input type="number" name="dauer" min="10" max="120" value="<?= (int) $e['dauer'] ?>"></div>
      <div class="feld"><label>Frühestens buchbar in (Stunden)</label><input type="number" name="vorlauf" min="0" max="168" value="<?= (int) $e['vorlauf'] ?>"></div>
      <div class="feld"><label>So viele Tage im Voraus</label><input type="number" name="tage" min="1" max="60" value="<?= (int) $e['tage'] ?>"></div>
    </div>
    <div class="feld"><label>Gesperrte Tage (Urlaub, Feiertag) — z. B. 24.12.2026, 25.12.2026</label>
      <input name="gesperrt" value="<?= Fmt::h(implode(', ', array_map(static fn($d) => date('d.m.Y', strtotime($d)), $e['gesperrt']))) ?>"></div>
    <label class="akq-haken"><input type="checkbox" name="an" value="1"<?= $an ? ' checked' : '' ?>> Buchungsseite nimmt Termine an</label>
    <button class="knopf haupt">Speichern</button>
  </form>
</div>

<?php if ($vorbei): ?>
<details class="block"><summary style="cursor:pointer"><b>Vergangene und abgesagte</b> <span class="akq-klein">· <?= count($vorbei) ?></span></summary>
  <div class="tabellenrahmen" style="margin-top:10px"><table><tbody>
    <?php foreach ($vorbei as $t): ?>
      <tr><td class="akq-klein" style="width:150px"><?= Fmt::h(AkquiseTermin::zeitText(strtotime((string) $t['beginn']), 'de')) ?></td><td><?= Fmt::h((string) $t['name']) ?><?= $t['firma'] ? ' · ' . Fmt::h((string) $t['firma']) : '' ?></td>
        <td><span class="marke2 <?= $t['status'] === 'erledigt' ? 'gut' : '' ?>"><?= Fmt::h($t['status'] === 'abgesagt' ? 'abgesagt (' . ($t['abgesagt_von'] === 'vecom' ? 'von dir' : 'vom Kunden') . ')' : ($t['status'] === 'erledigt' ? 'erledigt' : 'vorbei')) ?></span></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
</details>
<?php endif; ?>
