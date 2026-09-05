<?php
/* ==========================================================================
   Das Schreibfeld fuer eine Nachricht an den Kunden — Betreff, Vorlage, Text.

   Wird von der Kundenakte und von der Vorgangsseite eingebunden. Erwartet:
     $nfTat      — 'kunde_nachricht' oder 'nachricht_senden'
     $nfId       — Kunden- bzw. Projekt-ID (was die Aktion braucht)
     $nfKennung  — 'VD-2026-0005' oder 'VD-K-0017'
     $nfVorlagen — aus Vorlage::fuer()
     $nfVorname  — fuer den Platzhalter im Textfeld
     $nfZurueck  — Adresse, auf die nach dem Senden zurueckgesprungen wird ('' = keine)
     $nfVorwahl  — Schluessel einer Vorlage, die schon ausgewaehlt sein soll ('' = keine)
     $nfBetreff  — Betreff, der schon dringestehen soll ('' = leer)
     $nfText     — Text, der schon dringestehen soll ('' = leer)

   Betreff und Text koennen vorbelegt werden, weil der Bedarf eine fertige
   Preisnachricht mitbringt: Sie ist keine Vorlage im Sinne der Auswahlliste
   (sie enthaelt gerechnete Zahlen dieses einen Falls), soll aber genauso
   fertig dastehen. Die Vorlagenauswahl ueberschreibt sie nur nach Rueckfrage.

   Die Kennung steht fest vor dem Betreff und ist nicht editierbar: Sie ist
   kein Text, sondern ein Aktenzeichen. Aendert man sie von Hand, faellt genau
   das weg, wozu sie da ist — dass zusammengehoerige Mails zusammenbleiben.
   ========================================================================== */
$nfNr = 'nf' . substr(md5((string) $nfTat . '-' . (string) $nfId), 0, 6);
/* Wer von einem Knopf herkommt ("Link schicken"), soll die Vorlage schon
   ausgewaehlt und ausgefuellt vorfinden — sonst haette der Knopf nur einen
   Sprung gespart und keinen Handgriff. */
$nfVorwahl = (string) ($nfVorwahl ?? '');
$nfBetreff = (string) ($nfBetreff ?? '');
$nfText    = (string) ($nfText ?? '');
$nfGewaehlt = -1;
if ($nfVorwahl !== '') {
    foreach ($nfVorlagen as $i => $vl) {
        if ((string) ($vl['schluessel'] ?? '') === $nfVorwahl) { $nfGewaehlt = (int) $i; break; }
    }
}
?>
<form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:12px" id="<?= $nfNr ?>">
  <?= Csrf::feld() ?>
  <input type="hidden" name="tat" value="<?= Fmt::h((string) $nfTat) ?>">
  <input type="hidden" name="id" value="<?= (int) $nfId ?>">
  <?php if (($nfZurueck ?? '') !== ''): ?>
    <input type="hidden" name="zurueck" value="<?= Fmt::h((string) $nfZurueck) ?>">
  <?php endif; ?>

  <?php if ($nfVorlagen): ?>
    <div class="feld">
      <label for="<?= $nfNr ?>_v">Vorlage</label>
      <select id="<?= $nfNr ?>_v">
        <option value="">— eigener Text —</option>
        <?php foreach (Vorlage::gruppen() as $gruppe => $wie): ?>
          <optgroup label="<?= Fmt::h((string) ($wie['de'] ?? $gruppe)) ?>">
            <?php foreach ($nfVorlagen as $i => $vl): ?>
              <?php if ($vl['gruppe'] !== $gruppe) { continue; } ?>
              <option value="<?= (int) $i ?>" <?= $nfGewaehlt === (int) $i ? 'selected' : '' ?>><?= Fmt::h($vl['name']) ?></option>
            <?php endforeach; ?>
          </optgroup>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>

  <div class="feld">
    <label for="<?= $nfNr ?>_b">Betreff</label>
    <?php /* Die Kennung steht jetzt HINTER dem Betreff, nicht davor — hier
             genauso wie in der Mail. Eckige Klammern am Anfang sind das
             Merkmal von Verteilerlisten, und der Kunde soll zuerst die Sache
             lesen, nicht ein Aktenzeichen. */ ?>
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
      <input id="<?= $nfNr ?>_b" name="betreff" style="flex:1 1 220px;width:auto"
             maxlength="180" placeholder="Worum es geht" value="<?= Fmt::h($nfBetreff) ?>">
      <span style="white-space:nowrap;font-size:12.5px;letter-spacing:.03em;color:var(--leise);
                   border:1px solid var(--linie);border-radius:8px;padding:8px 11px"
            title="Diese Kennung hängt die Verwaltung an jeden Betreff — damit zusammengehörige Mails zusammenbleiben.">· <?= Fmt::h((string) $nfKennung) ?></span>
    </div>
  </div>

  <div class="feld"><textarea id="<?= $nfNr ?>_t" name="text"
    rows="<?= $nfText !== '' ? max(8, min(30, substr_count($nfText, "\n") + 3)) : 8 ?>" required
    placeholder="Hallo <?= Fmt::h((string) $nfVorname) ?>, …"><?= Fmt::h($nfText) ?></textarea></div>

  <?php /* Nicht blau: Das Nachrichtenfeld steht auf jeder Vorgangsseite und
           ist nie "der naechste Schritt". Blau bleibt dem vorbehalten, was
           die Fuehrung gerade meint -- sonst heisst blau bald nichts mehr. */ ?>
  <button class="knopf">Senden</button>
  <span style="color:var(--leise);font-size:12.5px;margin-left:8px">Geht als E-Mail raus und steht auf seiner Seite.</span>
</form>
<?php if ($nfVorlagen): ?>
<script>
(function () {
  var f = document.getElementById(<?= json_encode($nfNr) ?>);
  if (!f) { return; }
  // JSON_HEX_TAG wandelt spitze Klammern um. Ohne das wuerde ein schliessendes
  // Skript-Tag in einem Vorlagentext diesen Block vorzeitig beenden. Heute
  // steht in keiner Vorlage so etwas — aber Vorlagen sind zum Bearbeiten da.
  // (Dieser Kommentar hat es beim ersten Anlauf selbst ausgeloest, weil das
  //  Tag woertlich darin stand. Deshalb steht es hier nicht mehr.)
  var v = <?= json_encode(array_map(static fn($x) => ['betreff' => $x['betreff'], 'text' => $x['text']],
      $nfVorlagen), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var wahl = f.querySelector('select'),
      betreff = f.querySelector('input[name=betreff]'),
      text = f.querySelector('textarea');
  function einsetzen(x) {
    betreff.value = x.betreff;
    text.value = x.text;
    wachsen();
    text.focus();
    text.setSelectionRange(0, 0);
    text.scrollTop = 0;
  }

  wahl.addEventListener('change', function () {
    var x = v[this.value];
    if (!x) { return; }
    // Ueberschreiben nur nach Rueckfrage: Wer schon getippt hat, soll seine
    // Arbeit nicht durch einen Klick daneben verlieren. Die Frage steht dabei
    // an der Auswahl statt im Fenster des Browsers -- sie haelt so nicht die
    // ganze Seite an, und der schon getippte Text bleibt derweil sichtbar.
    var wieder = this;
    if (text.value.trim() !== '') {
      if (typeof window.vecomFrage === 'function') {
        window.vecomFrage(wahl, 'Den geschriebenen Text durch die Vorlage ersetzen?',
                          'Ja, ersetzen', function () { einsetzen(x); });
      } else if (!confirm('Den geschriebenen Text durch die Vorlage ersetzen?')) {
        wieder.value = ''; return;
      } else {
        einsetzen(x);
      }
      wieder.value = '';
      return;
    }
    einsetzen(x);
  });

  // Die Vorlagen sind ganze Briefe. In sieben Zeilen muesste man in einem
  // winzigen Fenster scrollen, um zu sehen, was man verschickt — also waechst
  // das Feld mit dem Text mit.
  function wachsen() {
    text.style.height = 'auto';
    text.style.height = Math.min(text.scrollHeight + 4, 900) + 'px';
  }
  text.addEventListener('input', wachsen);

  // Kommt die Seite mit schon gewaehlter Vorlage, wird sie hier einmal
  // eingesetzt: ein "change" feuert beim Laden nicht von selbst.
  if (wahl.value !== '' && text.value.trim() === '' && v[wahl.value]) {
    betreff.value = v[wahl.value].betreff;
    text.value = v[wahl.value].text;
  }
  wachsen();
})();
</script>
<?php endif; ?>
