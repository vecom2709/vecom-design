<?php
/** @var array $f @var ?array $audit @var array $befunde @var array $gate */
/* DER ANRUFZETTEL (26.09.2026)

   Uwes Regel: Deutschsprachige Betriebe bekommen Text UND Anruf, und
   anrufen tut er selbst. Der Zettel nimmt ihm ab, was beim Telefonieren
   stoert: Wie fange ich an, was habe ich gefunden, was sage ich, wenn
   jemand abwinkt. Der Einstieg kommt aus den geprueften Befunden -- kein
   Satz, den die Website nicht belegt.

   Preise stehen hier absichtlich nicht: Beim ersten Kontakt nie ein Preis,
   immer der Weg zum Bedarfsrechner (Uwes Regel vom 24.09.2026). */
$akqTeil = 'firma';
$fid = (int) $f['id'];
$abs = AkquiseText::absender();
$vorname = explode(' ', trim($abs['inhaber']))[0] ?? 'Uwe';
$person = trim((string) $f['ansprechpartner']);
$gesperrt = (int) $f['gesperrt'] === 1;

/* Die drei Gespraechspunkte: belegt, mit gepflegtem Satz, das Wichtigste zuerst. */
$punkte = [];
foreach (AkquiseScore::topBefunde(array_values(array_filter($befunde, static fn($b) => $b['status'] === 'VERIFIED'))) as $b) {
    $s = AkquiseText::saetze($b, $f, 'de');
    if ($s !== null) { $punkte[] = ['titel' => (string) $b['titel'], 'satz' => $s[0], 'wirkung' => $s[1], 'loesung' => $s[2]]; }
    if (count($punkte) === 3) { break; }
}
$einwaende = [
    ['Kein Interesse.', 'Verstehe ich gut. Darf ich Ihnen die drei Punkte trotzdem kurz per E-Mail schicken — ohne Angebot, einfach zum Nachlesen?',
        'Ja → „Per E-Mail schicken“. Nein → freundlich verabschieden, „Kein Interesse“.'],
    ['Wir haben schon jemanden für die Website.', 'Prima, dann sind Sie gut versorgt. Die Punkte können Sie gern weitergeben — soll ich sie Ihnen schicken?', ''],
    ['Was kostet das?', 'Das hängt ganz davon ab, was Sie brauchen. Unser Bedarfsrechner zeigt es Ihnen in zwei Minuten, unverbindlich — ich schicke Ihnen den Link.',
        'Keinen Preis nennen. Link: vecom-design.it/zugang.php'],
    ['Gerade keine Zeit.', 'Kein Problem. Wann passt es Ihnen besser — oder soll ich Ihnen die Punkte einfach schicken?', 'Rückruftermin in die Notiz.'],
    ['Woher haben Sie meine Nummer?', 'Die steht auf Ihrer Website' . ($f['quelle'] === 'osm' ? ' bzw. im öffentlichen Kartenverzeichnis OpenStreetMap' : '')
        . '. Wenn Sie keine Anrufe von uns möchten, trage ich Sie sofort aus.', 'Möchte keine Anrufe → „Kein Interesse“ (sperrt dauerhaft).'],
];
?>
<style>
  .az{max-width:880px}
  .az-tel{font-size:26px;font-weight:700;letter-spacing:.02em}
  .az-tel a{color:var(--cyan)}
  .az-sag{background:var(--flaeche2);border-left:3px solid var(--cyan);border-radius:10px;padding:12px 16px;margin:8px 0;font-size:15.5px;line-height:1.6}
  .az-punkt{margin:0 0 12px}
  .az-punkt b{display:block}
  .az-ein{border:1px solid var(--linie);border-radius:12px;padding:10px 14px;margin-bottom:8px}
  .az-ein summary{cursor:pointer;font-weight:600}
  .az-knoepfe{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
  @media print{ .nav,.akq-reiter,.az-ergebnis,.kopfzeile,header.mobil{display:none!important} body{background:#fff;color:#000} .az-sag{border-color:#999;background:#f3f3f3} }
</style>
<div class="az">
<div class="kopf"><div>
  <p class="akq-klein"><a href="<?= Fmt::h(url('akquise/' . $fid)) ?>">← <?= Fmt::h((string) $f['name']) ?></a></p>
  <h1>Anrufzettel</h1>
  <?php if ($f['telefon']): ?><p class="az-tel"><a href="tel:<?= Fmt::h(preg_replace('~[^0-9+]~', '', (string) $f['telefon'])) ?>"><?= Fmt::h((string) $f['telefon']) ?></a></p>
  <?php else: ?><p class="akq-klein" style="margin:8px 0">Keine Telefonnummer bekannt — auf der Website nachsehen und auf der Firmenseite unter „Angaben ändern“ eintragen.</p><?php endif; ?>
  <p class="akq-klein"><?= Fmt::h(implode(' · ', array_filter([(string) $f['name'], $person, (string) $f['stadt'], (string) ($f['domain'] ?? '')]))) ?></p>
</div></div>

<?php require __DIR__ . '/akquise_reiter.php'; ?>

<?php if ($gesperrt || in_array($gate['status'], [AkquiseGate::NICHT, AkquiseGate::UNKLAR], true)): ?>
  <div class="block"><div class="leer">Hier nicht anrufen: <?= Fmt::h($gesperrt ? 'der Betrieb steht auf der Sperrliste.' : implode(' ', $gate['gruende'])) ?></div></div>
<?php else: ?>

<div class="block">
  <h2>1 · Einstieg</h2>
  <div class="az-sag">„Guten Tag<?= $person !== '' ? ', ' . Fmt::h($person) : '' ?>, hier ist <?= Fmt::h($abs['inhaber']) ?> von <?= Fmt::h($abs['firma']) ?>.
    Ich mache Websites für Betriebe <?= $f['stadt'] ? 'wie Ihren' : 'in Ihrer Branche' ?> und habe mir <?= Fmt::h((string) ($f['domain'] ?: 'Ihre Website')) ?> angesehen.
    Mir sind <?= count($punkte) > 1 ? count($punkte) . ' konkrete Dinge' : 'eine konkrete Sache' ?> aufgefallen, die Sie vielleicht interessieren.
    Haben Sie zwei Minuten?“</div>
  <p class="akq-klein">Wenn nicht die Inhaberin oder der Inhaber dran ist: „Wer kümmert sich bei Ihnen um die Website?“ — Namen notieren.</p>
</div>

<div class="block">
  <h2>2 · Was aufgefallen ist</h2>
  <?php if (!$punkte): ?>
    <div class="leer">Keine geprüften Befunde mit fertigem Satz. <?= $f['url'] ? 'Ohne konkreten Anlass lieber nicht anrufen.' : 'Keine eigene Website — Einstieg: „Ich habe gesehen, dass Sie noch keine eigene Website haben …“' ?></div>
  <?php endif; ?>
  <?php foreach ($punkte as $i => $p): ?>
    <div class="az-punkt"><b><?= $i + 1 ?>. <?= Fmt::h($p['satz']) ?></b>
      <span class="akq-klein"><?= Fmt::h($p['wirkung']) ?> — Unser Weg: <?= Fmt::h($p['loesung']) ?></span></div>
  <?php endforeach; ?>
  <div class="az-sag">„Das ließe sich mit überschaubarem Aufwand lösen. Soll ich Ihnen die Punkte mit Bildschirmfotos schicken — oder gehen wir sie kurz zusammen durch?“</div>
</div>

<div class="block">
  <h2>3 · Wenn jemand sagt …</h2>
  <?php foreach ($einwaende as [$sagt, $antwort, $tipp]): ?>
    <details class="az-ein"><summary>„<?= Fmt::h($sagt) ?>“</summary>
      <div class="az-sag">„<?= Fmt::h($antwort) ?>“</div>
      <?php if ($tipp !== ''): ?><p class="akq-klein"><?= Fmt::h($tipp) ?></p><?php endif; ?></details>
  <?php endforeach; ?>
  <p class="akq-klein" style="margin-top:8px">Nie Druck, nie Angst („Sie verlieren Kunden“), nie einen Preis. Ein Nein ist ein Nein.</p>
</div>

<div class="block az-ergebnis" id="ergebnis">
  <h2>4 · Wie ist es gelaufen?</h2>
  <form method="post" action="<?= Fmt::h(url('akquise')) ?>">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_anruf"><input type="hidden" name="firma" value="<?= $fid ?>">
    <input type="hidden" name="zurueck" value="akquise/<?= $fid ?>/anruf">
    <label class="akq-haken"><input type="checkbox" name="anlass" value="1" checked>
      Geschäftsnummer von der Website oder aus einem öffentlichen Verzeichnis, konkreter Anlass, kein Widerspruch bekannt.</label>
    <div class="feld"><label>Notiz (mit wem gesprochen, Rückruf wann, was vereinbart)</label><input name="notiz" maxlength="200"></div>
    <div class="feld"><label>E-Mail-Adresse, falls „bitte per Mail schicken“</label><input name="email" type="email" value="<?= Fmt::h((string) $f['email']) ?>"></div>
    <div class="az-knoepfe">
      <button class="knopf haupt" name="ergebnis" value="per_mail">Per E-Mail schicken</button>
      <button class="knopf" name="ergebnis" value="interesse">Interesse — Termin</button>
      <button class="knopf" name="ergebnis" value="kein_interesse">Kein Interesse</button>
    </div>
    <p class="akq-klein" style="margin-top:8px">„Per E-Mail schicken“ hält die Einwilligung fest (wer, wann, wie) — danach ist die E-Mail erlaubt.
      „Kein Interesse“ sperrt den Betrieb dauerhaft.</p>
  </form>
  <form method="post" action="<?= Fmt::h(url('akquise')) ?>" style="margin-top:12px;border-top:1px solid var(--linie);padding-top:12px;display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_anruf_nicht_erreicht"><input type="hidden" name="firma" value="<?= $fid ?>">
    <div style="flex:1 1 240px"><label class="akq-klein">Notiz</label><input name="notiz" maxlength="200" placeholder="z. B. Mailbox, morgen 10 Uhr nochmal"></div>
    <button class="knopf">Nicht erreicht</button>
  </form>
</div>
<?php endif; ?>
</div>
