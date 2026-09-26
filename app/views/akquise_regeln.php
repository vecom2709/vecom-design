<?php
/** @var array $regeln @var array $grenzen @var bool $konfigurator @var string $telefon @var array $sperrliste
 *  @var bool $schluesselDa @var ?string $schluesselEinmal @var int $heute @var int $blockiert */
$akqTeil = 'regeln';
$klasse = static fn(string $s): string => ['CONTACT_ALLOWED' => 'gut', 'REVIEW_REQUIRED' => 'warnung', 'DO_NOT_EMAIL' => 'schlecht'][$s] ?? '';
?>
<div class="kopf"><div><h1>Compliance &amp; Versand</h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px">Die Regeln des Gates sind Daten, nicht Code: Recht ändert sich, und das Nachziehen
    gehört hierher, mit Quelle und Prüfdatum. <b>Keine Rechtsberatung</b> — vor dem ersten Versand anwaltlich prüfen lassen.</p></div></div>

<?php require __DIR__ . '/akquise_reiter.php'; ?>

<div class="block">
  <h2>Regeln des Gates <span class="akq-klein" style="font-weight:400">· Reihenfolge: Sperrliste → keine Zweitansprache → Land → Regel → sonst UNKNOWN</span></h2>
  <div class="tabellenrahmen"><table><thead><tr><th>Land</th><th>Kanal</th><th>Grundlage</th><th>Ergebnis · Begründung · Quelle</th></tr></thead><tbody>
    <?php foreach ($regeln as $r): ?>
      <tr><td><b><?= Fmt::h((string) $r['land']) ?></b></td><td><?= Fmt::h(AkquiseGate::KANAELE[(string) $r['kanal']] ?? (string) $r['kanal']) ?></td>
        <td class="akq-klein"><?= Fmt::h(['ohne' => 'ohne Einwilligung', 'einwilligung' => 'mit Einwilligung', 'bestandskunde' => 'Bestandskunde'][$r['bedingung']] ?? (string) $r['bedingung']) ?></td>
        <td>
          <details><summary style="cursor:pointer;list-style:none;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <span class="marke2 <?= $klasse((string) $r['ergebnis']) ?>"><?= Fmt::h(AkquiseGate::STATUS[(string) $r['ergebnis']] ?? '') ?></span>
            <?= (int) $r['aktiv'] ? '' : '<span class="marke2">inaktiv</span>' ?>
            <span class="akq-klein">geprüft <?= $r['geprueft_am'] ? Fmt::h(Fmt::datum((string) $r['geprueft_am'])) : '—' ?> · bearbeiten</span></summary>
            <form method="post" action="<?= Fmt::h(url('akquise')) ?>" style="margin-top:10px"
                  data-frage="Regel ändern? Alle Firmen dieses Landes werden danach neu eingestuft." data-ja="Ja, speichern">
              <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_regel_speichern"><input type="hidden" name="regel" value="<?= (int) $r['id'] ?>">
              <input type="hidden" name="zurueck" value="akquise/regeln">
              <div class="reihe">
                <div class="feld"><label>Ergebnis</label><select name="ergebnis"><?php foreach (AkquiseGate::STATUS as $k => $w): ?>
                  <option value="<?= $k ?>"<?= $r['ergebnis'] === $k ? ' selected' : '' ?>><?= Fmt::h($w) ?> (<?= $k ?>)</option><?php endforeach; ?></select></div>
                <div class="feld"><label>Geprüft am</label><input type="date" name="geprueft_am" value="<?= Fmt::h((string) $r['geprueft_am']) ?>"></div>
              </div>
              <div class="feld"><label>Begründung</label><textarea name="begruendung" rows="3"><?= Fmt::h((string) $r['begruendung']) ?></textarea></div>
              <div class="feld"><label>Quelle</label><input name="quelle" value="<?= Fmt::h((string) $r['quelle']) ?>"></div>
              <label style="display:flex;gap:8px;align-items:center;font-size:13.5px;margin-bottom:10px"><input type="checkbox" name="aktiv" value="1" style="width:auto"<?= (int) $r['aktiv'] ? ' checked' : '' ?>> aktiv</label>
              <button class="knopf">Regel speichern</button>
            </form>
          </details>
          <div class="akq-klein" style="margin-top:4px"><?= Fmt::h((string) $r['begruendung']) ?>
            <?php if ($r['quelle']): ?> · <a href="<?= Fmt::h((string) $r['quelle']) ?>" target="_blank" rel="noopener noreferrer" style="text-decoration:underline">Quelle</a><?php endif; ?></div>
        </td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
</div>

<div class="zwei">
  <div class="block">
    <h2>Versand und Grenzen</h2>
    <p class="akq-klein" style="margin-bottom:10px">Heute verschickt: <?= $heute ?> · in 30 Tagen vom Gate verhindert: <?= $blockiert ?></p>
    <form method="post" action="<?= Fmt::h(url('akquise')) ?>"
          data-frage="Versandregeln speichern? Ist der Versand eingeschaltet, kann jede freigegebene E-Mail mit erlaubtem Gate mit einem Klick hinausgehen." data-ja="Ja, speichern">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_grenzen_speichern"><input type="hidden" name="zurueck" value="akquise/regeln">
      <label style="display:flex;gap:8px;align-items:center;font-size:14px;margin-bottom:12px">
        <input type="checkbox" name="akq_versand_an" value="1" style="width:auto"<?= $grenzen['versand_an'] ? ' checked' : '' ?>>
        <b>E-Mail-Versand eingeschaltet</b> <span class="akq-klein">(auch dann nur freigegebene Texte mit erlaubtem Gate)</span></label>
      <div class="reihe">
        <div class="feld"><label>Höchstens je Tag</label><input type="number" name="akq_limit_tag" min="0" max="200" value="<?= (int) $grenzen['tag'] ?>"></div>
        <div class="feld"><label>Höchstens je Stunde</label><input type="number" name="akq_limit_stunde" min="0" max="50" value="<?= (int) $grenzen['stunde'] ?>"></div>
        <div class="feld"><label>Pause zwischen zwei (Sekunden)</label><input type="number" name="akq_pause_sekunden" min="0" value="<?= (int) $grenzen['pause'] ?>"></div>
        <div class="feld"><label>Dieselbe Domain frühestens nach (Tagen)</label><input type="number" name="akq_limit_domain_tage" min="1" value="<?= (int) $grenzen['domain_tage'] ?>"></div>
        <div class="feld"><label>Stopp nach Fehlschlägen in 24 h</label><input type="number" name="akq_fehler_grenze" min="1" value="<?= (int) $grenzen['fehler'] ?>"></div>
        <div class="feld"><label>Stopp nach Unzustellbaren in 7 Tagen</label><input type="number" name="akq_bounce_grenze" min="1" value="<?= (int) $grenzen['bounce'] ?>"></div>
      </div>
      <div class="feld"><label>Telefon in der Signatur</label><input name="akq_absender_telefon" value="<?= Fmt::h($telefon) ?>" placeholder="+39 …"></div>
      <label style="display:flex;gap:8px;align-items:center;font-size:13.5px;margin-bottom:12px">
        <input type="checkbox" name="akq_konfigurator_link" value="1" style="width:auto"<?= $konfigurator ? ' checked' : '' ?>> Link zum Bedarfsrechner in jede Vorlage</label>
      <button class="knopf">Speichern</button>
    </form>
  </div>

  <div class="block">
    <h2>Worker-Schlüssel</h2>
    <?php if ($schluesselEinmal): ?>
      <div class="hinweis gut" style="margin-bottom:10px">Einmalig sichtbar. Auf „Kopieren“ klicken — auf deinem Rechner übernimmt
        <code>npm run verbinden</code> den Schlüssel dann aus der Zwischenablage (er wird dabei nirgends angezeigt).</div>
      <div style="display:flex;gap:8px;margin-bottom:10px">
        <input id="akq_schluessel" readonly value="<?= Fmt::h($schluesselEinmal) ?>" onclick="this.select()" style="font-family:ui-monospace,monospace">
        <button class="knopf haupt" type="button" data-kopieren="akq_schluessel">Kopieren</button></div>
    <?php else: ?>
      <p class="akq-klein" style="margin-bottom:10px"><?= $schluesselDa ? 'Ein Schlüssel ist hinterlegt. Er wird nie wieder angezeigt.' : 'Noch kein Schlüssel — die Tür für den Worker ist zu.' ?></p>
    <?php endif; ?>
    <form method="post" action="<?= Fmt::h(url('akquise')) ?>" <?= $schluesselDa ? 'data-frage="Neuen Schlüssel erzeugen? Der alte gilt sofort nicht mehr — der Worker braucht dann den neuen." data-ja="Ja, neu erzeugen"' : '' ?>>
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_schluessel_neu"><input type="hidden" name="zurueck" value="akquise/regeln">
      <button class="knopf"><?= $schluesselDa ? 'Neuen Schlüssel erzeugen' : 'Schlüssel erzeugen' ?></button></form>
    <p class="akq-klein" style="margin-top:10px">Der Worker darf mit diesem Schlüssel Firmen, Befunde und Textvorschläge melden — nie senden, freigeben oder sperren.</p>
  </div>
</div>

<div class="block" id="sperrliste">
  <h2>Sperrliste (DO NOT CONTACT) <span class="akq-klein" style="font-weight:400">· <?= count($sperrliste) ?> Einträge</span></h2>
  <form method="post" action="<?= Fmt::h(url('akquise')) ?>" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-bottom:14px">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_sperre_eintragen"><input type="hidden" name="zurueck" value="akquise/regeln#sperrliste">
    <div><label class="akq-klein">Art</label><select name="art" style="width:auto"><option value="domain">Domain</option><option value="email">E-Mail</option><option value="telefon">Telefon</option></select></div>
    <div style="flex:1 1 200px"><label class="akq-klein">Wert</label><input name="wert" required></div>
    <div style="flex:2 1 260px"><label class="akq-klein">Grund</label><input name="grund" placeholder="z. B. Anruf 24.09.: möchte nicht kontaktiert werden"></div>
    <button class="knopf">Sperren</button>
  </form>
  <?php if ($sperrliste): ?>
  <div class="tabellenrahmen"><table><thead><tr><th>Art</th><th>Wert</th><th>Grund</th><th>Quelle</th><th>Seit</th><th></th></tr></thead><tbody>
    <?php foreach ($sperrliste as $s): ?>
      <tr><td><?= Fmt::h((string) $s['art']) ?></td><td><?= Fmt::h((string) $s['wert']) ?></td><td class="akq-klein"><?= Fmt::h((string) $s['grund']) ?></td>
        <td><span class="marke2"><?= Fmt::h((string) $s['quelle']) ?></span></td><td class="akq-klein"><?= Fmt::h(Fmt::datum((string) $s['created_at'])) ?></td>
        <td><?php if (!in_array($s['quelle'], ['abmeldung', 'antwort'], true)): ?>
          <form method="post" action="<?= Fmt::h(url('akquise')) ?>" data-frage="Diesen Sperreintrag löschen? Er steht danach nur noch in der Prüfspur." data-ja="Ja, löschen">
            <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_sperre_loeschen"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
            <input type="hidden" name="zurueck" value="akquise/regeln#sperrliste"><button class="knopf" style="font-size:12px;padding:4px 10px">Löschen</button></form>
          <?php else: ?><span class="akq-klein">Widerspruch — bleibt</span><?php endif; ?></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
</div>
