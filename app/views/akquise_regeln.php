<?php
/** @var array $regeln @var array $grenzen @var bool $konfigurator @var string $telefon @var array $sperrliste
 *  @var bool $schluesselDa @var ?string $schluesselEinmal @var int $heute @var int $blockiert */
/* Regeln & Versand -- bewusst in Alltagssprache. Oben steht, was eingerichtet
   ist und was fehlt; darunter dieselben Formulare wie zuvor (gleiche tat- und
   Feldnamen), nur geordnet: erst das, was man oefter braucht, dann das, was
   man einmal einrichtet. Selten Benoetigtes steckt in aufklappbaren Teilen. */
$akqTeil = 'regeln';
require_once dirname(__DIR__) . '/src/AkquiseBriefdienst.php';
require_once dirname(__DIR__) . '/src/AkquisePostfach.php';
require_once dirname(__DIR__) . '/src/AkquiseCheck.php';
$ckAn = AkquiseCheck::an();
$ckZahl = (int) Db::wert('SELECT COUNT(*) FROM akq_checks WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)', [], 0);
$ckOffen = AkquiseCheck::offen();
$bdDa = AkquiseBriefdienst::bereit(); $bdTest = AkquiseBriefdienst::test();
$pfZ = AkquisePostfach::zugang(); $pfDa = AkquisePostfach::bereit();
$pfStand = json_decode((string) Db::wert("SELECT svalue FROM settings WHERE skey = 'akq_postfach_stand'", [], ''), true) ?: [];
$pfFehler = (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'akq_postfach_fehler'", [], '');
$pfZahl = (int) Db::wert("SELECT COUNT(*) FROM akq_antworten WHERE nachricht_id IS NOT NULL AND eingang_am >= DATE_SUB(NOW(), INTERVAL 30 DAY)", [], 0);

$farbe = [AkquiseGate::ERLAUBT => 'gut', AkquiseGate::PRUEFEN => 'warnung', AkquiseGate::NICHT => 'schlecht', AkquiseGate::UNKLAR => ''];
$kurz  = [AkquiseGate::ERLAUBT => 'Ja', AkquiseGate::PRUEFEN => 'Nach Prüfung', AkquiseGate::NICHT => 'Nein', AkquiseGate::UNKLAR => 'Lieber nicht'];
$landName = ['IT' => 'Italien', 'DE' => 'Deutschland', 'AT' => 'Österreich', 'CH' => 'Schweiz', 'FR' => 'Frankreich'];
$bedName = ['ohne' => 'ohne Einwilligung', 'einwilligung' => 'mit Einwilligung', 'bestandskunde' => 'bei Bestandskunden'];
$artName = ['domain' => 'Webseite', 'email' => 'E-Mail', 'telefon' => 'Telefon', 'firma' => 'Betrieb'];
$quelleName = ['hand' => 'von Hand', 'abmeldung' => 'Abmeldelink', 'antwort' => 'Antwort'];
$dauer = static fn(int $s): string => $s >= 60 && $s % 60 === 0 ? ($s / 60) . ' Min.' : $s . ' Sek.';

/* Die Regeltabelle als Raster: Weg × Land. Inaktive Regeln zaehlen nicht. */
$laender = []; $raster = []; $geprueft = '';
foreach ($regeln as $r) {
    if (!(int) $r['aktiv']) { continue; }
    $laender[(string) $r['land']] = true;
    $raster[(string) $r['kanal']][(string) $r['land']][(string) $r['bedingung']] = (string) $r['ergebnis'];
    if ($r['geprueft_am'] && ($geprueft === '' || $r['geprueft_am'] < $geprueft)) { $geprueft = (string) $r['geprueft_am']; }
}
$reihe = ['ohne' => 0, 'einwilligung' => 1, 'bestandskunde' => 2];
foreach ($raster as &$k) { foreach ($k as &$z) { uksort($z, static fn($a, $b) => ($reihe[$a] ?? 9) <=> ($reihe[$b] ?? 9)); } unset($z); } unset($k);
uksort($laender, static fn($a, $b) => ($a === 'IT' ? 0 : 1) <=> ($b === 'IT' ? 0 : 1) ?: strcmp($a, $b));

/* "Auf einen Blick": je Punkt Ampel, Satz und Sprungziel. */
$testAn = AkquiseGate::testbetrieb();
$blick = [
    ['Testbetrieb', $testAn ? 'gelb' : 'gruen', $testAn ? 'An · Akquise-Mails werden nur simuliert.' : 'Aus · freigegebene Mails gehen wirklich raus.', '#schalter'],
    ['E-Mail-Versand', $grenzen['stop'] ? 'rot' : ($grenzen['versand_an'] ? 'gruen' : 'gelb'),
        $grenzen['stop'] ? 'Notbremse gezogen — es geht nichts raus.' : ($grenzen['versand_an'] ? 'An · höchstens ' . (int) $grenzen['tag'] . ' Mails am Tag.' : 'Aus · keine Mail geht automatisch raus.'), '#versand'],
    AkquiseGate::briefAn()
        ? ['Briefe per Post', $bdDa ? ($bdTest ? 'gelb' : 'gruen') : '', $bdDa ? ($bdTest ? 'Testbetrieb · nichts wird gedruckt.' : 'Eingerichtet · Briefe gehen wirklich raus.') : 'Noch nicht eingerichtet.', '#briefdienst']
        : ['Briefe per Post', '', 'Ausgeschaltet · es gibt nur E-Mail mit Einwilligung.', '#briefdienst'],
    ['Antworten einlesen', $pfFehler !== '' ? 'rot' : ($pfDa ? 'gruen' : ''),
        $pfFehler !== '' ? 'Verbindung klappt nicht — bitte prüfen.' : ($pfDa ? 'Verbunden · liest alle 10 Minuten.' : 'Noch nicht eingerichtet.'), '#postfach'],
    ['Nie kontaktieren', 'gruen', count($sperrliste) . ' Einträge — werden immer übersprungen.', '#sperrliste'],
    ['Heute', '', $heute . ' Mails verschickt · ' . $blockiert . ' in 30 Tagen zurückgehalten.', '#versand'],
    ['Website-Check', $ckAn ? 'gruen' : '', $ckAn ? $ckZahl . ' Checks in 30 Tagen' . ($ckOffen ? ' · ' . $ckOffen . ' offen' : '') . '.' : 'Aus · die Seite nimmt nichts an.', '#check'],
    ['Verbindung zum PC', $schluesselDa ? 'gruen' : '', $schluesselDa ? 'Eingerichtet.' : 'Nicht eingerichtet (nur für die Suche am PC nötig).', '#rechner'],
];
?>
<style>
  .rg-blick{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:10px;margin:0 0 18px}
  .rg-blick a{display:block;padding:13px 15px;border:1px solid var(--linie);border-radius:14px;background:var(--flaeche);color:inherit;transition:border-color .18s}
  .rg-blick a:hover,.rg-blick a:focus-visible{border-color:var(--linie2)}
  .rg-blick b{display:block;font-size:14.5px;margin-bottom:4px}
  .rg-blick .akq-ampel{white-space:normal;align-items:flex-start;line-height:1.4}
  .rg-blick .akq-ampel i{margin-top:4px}
  .rg-erkl{color:var(--dim);font-size:14px;line-height:1.55;max-width:70ch;margin:0 0 12px}
  .rg-schritte{margin:0 0 16px;padding-left:20px;font-size:14px;color:var(--dim);line-height:1.6}
  .rg-raster{width:100%;border-collapse:collapse;font-size:14px}
  .rg-raster th,.rg-raster td{padding:10px 12px;border-bottom:1px solid var(--linie);text-align:left;vertical-align:top}
  .rg-raster thead th{font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:var(--leise);font-weight:600}
  .rg-raster tbody th{font-weight:600;white-space:nowrap}
  .rg-zeile{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:0 0 4px}
  .rg-legende{display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:8px 24px;margin:14px 0 0;font-size:13px;color:var(--dim)}
  .rg-legende div{display:flex;gap:10px;align-items:baseline}.rg-legende .marke2{flex:none;min-width:104px;justify-content:center}
  .rg-auf{margin-top:14px;border-top:1px solid var(--linie);padding-top:12px}
  .rg-auf>summary{cursor:pointer;font-size:14px;color:var(--dim)}
  .rg-auf>summary:hover{color:var(--text)}
  .rg-auf[open]>summary{margin-bottom:12px}
  .rg-regel{border:1px solid var(--linie);border-radius:12px;padding:10px 12px;margin-bottom:8px;background:var(--flaeche2)}
  .rg-regel>summary{cursor:pointer;display:flex;gap:8px;align-items:center;flex-wrap:wrap;font-size:13.5px}
  .rg-regel[open]>summary{margin-bottom:10px}
  .rg-abschnitt{font-size:13px;text-transform:uppercase;letter-spacing:.08em;color:var(--leise);margin:26px 0 10px;font-weight:600}
  .rg-drei{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;align-items:start}
  .rg-drei .block{margin:0}
  .rg-stand{font-size:13px;margin:0 0 12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
  .rg-grenzen{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:0 12px}
  .rg-grenzen .akq-klein{display:block;margin-top:3px}
  .rg-sperre{max-height:420px;overflow:auto}
  .rg-an{font-size:15px;padding:12px 14px;border:1px solid var(--linie);border-radius:12px;background:var(--flaeche2)}
  .rg-an b{color:var(--text)}
  @media (max-width:560px){ .rg-drei{grid-template-columns:1fr}
    .rg-raster thead{display:none} .rg-raster tr{display:grid;grid-template-columns:1fr 1fr;border-bottom:1px solid var(--linie);padding:8px 0}
    .rg-raster th,.rg-raster td{border:0;padding:4px 6px} .rg-raster tbody th{grid-column:1/-1}
    .rg-raster td::before{content:attr(data-land);display:block;font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--leise);margin-bottom:4px}
    .rg-legende .marke2{min-width:96px} }
</style>

<div class="kopf"><div><h1>Regeln &amp; Versand</h1>
  <p class="rg-erkl" style="margin-top:6px">Hier legst du fest, wen Vecom wie anschreiben darf und worüber verschickt wird.
    Das meiste richtest du einmal ein und fasst es danach nicht mehr an.</p></div></div>

<?php require __DIR__ . '/akquise_reiter.php'; ?>

<h2 class="rg-abschnitt" style="margin-top:4px">Auf einen Blick</h2>
<div class="rg-blick">
  <?php foreach ($blick as [$titel, $ampel, $satz, $ziel]): ?>
    <a href="<?= Fmt::h($ziel) ?>"><b><?= Fmt::h($titel) ?></b><span class="akq-ampel <?= $ampel ?>"><i aria-hidden="true"></i><span><?= Fmt::h($satz) ?></span></span></a>
  <?php endforeach; ?>
</div>

<div class="block" id="schalter">
  <h2>Schalter</h2>
  <p class="rg-erkl">Was ohne deinen Klick laufen darf. Die Notbremse oben rechts stoppt zusätzlich alles, auch was du von Hand verschickst.</p>
  <form method="post" action="<?= Fmt::h(url('akquise')) ?>" data-frage="Schalter speichern? Im Echtbetrieb gehen freigegebene Akquise-Mails und Folge-Mails wirklich raus." data-ja="Ja, speichern">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_schalter_speichern">
    <div class="rg-grenzen" style="gap:0 18px">
      <?php foreach (AkquiseGate::SCHALTER as $sk => [, , $sName, $sText]): ?>
        <label class="akq-haken"><input type="checkbox" name="schalter[<?= $sk ?>]" value="1"<?= AkquiseGate::schalterSelbst($sk) ? ' checked' : '' ?>>
          <span><b><?= Fmt::h($sName) ?></b><br><span class="akq-klein"><?= Fmt::h($sText) ?><?= $sk !== 'automatik' && AkquiseGate::schalterSelbst($sk) && !AkquiseGate::schalter($sk) ? ' — ruht, weil die Automatik aus ist.' : '' ?></span></span></label>
      <?php endforeach; ?>
    </div>
    <div class="rg-an" style="margin:6px 0 12px">
      <label class="akq-haken" style="margin:0 0 8px"><input type="radio" name="testbetrieb" value="1"<?= $testAn ? ' checked' : '' ?>>
        <span><b>Testbetrieb</b><br><span class="akq-klein">Alles läuft wie echt — Gate, Freigabe, Grenzen —, aber statt einer Mail steht „simuliert“ im Protokoll.</span></span></label>
      <label class="akq-haken" style="margin:0"><input type="radio" name="testbetrieb" value="0"<?= $testAn ? '' : ' checked' ?>>
        <span><b>Echtbetrieb</b><br><span class="akq-klein">Freigegebene Mails gehen wirklich raus (nur an Betriebe mit „Ja“ und nur bei eingeschaltetem E-Mail-Versand).</span></span></label>
    </div>
    <button class="knopf haupt">Speichern</button>
  </form>
</div>

<div class="block" id="regeln">
  <h2>Wen darf Vecom wie anschreiben?</h2>
  <p class="rg-erkl">Bevor irgendetwas rausgeht, fragt Vecom der Reihe nach:</p>
  <ol class="rg-schritte">
    <li>Steht der Betrieb bei <b>„Nie kontaktieren“</b>? → Dann nie.</li>
    <li>Wurde er <b>schon einmal angeschrieben</b>? → Dann nicht noch einmal.</li>
    <li>Was sagt die <b>Tabelle</b> für sein Land und diesen Weg? → Gibt es keine passende Zeile: lieber nicht.</li>
  </ol>
  <div class="tabellenrahmen"><table class="rg-raster"><thead><tr><th>Weg</th>
    <?php foreach (array_keys($laender) as $l): ?><th><?= Fmt::h($landName[$l] ?? $l) ?></th><?php endforeach; ?></tr></thead><tbody>
    <?php foreach (AkquiseGate::KANAELE as $kanal => $kanalName): if (empty($raster[$kanal])) { continue; } ?>
      <tr><th><?= Fmt::h($kanalName) ?><?= $kanal === 'brief' && !AkquiseGate::briefAn() ? ' <span class="marke2">ausgeschaltet</span>' : '' ?></th>
        <?php foreach (array_keys($laender) as $l): $zelle = $raster[$kanal][$l] ?? []; ?>
          <td data-land="<?= Fmt::h($landName[$l] ?? $l) ?>"><?php if (!$zelle): ?><span class="marke2">Lieber nicht</span>
            <?php else: foreach ($zelle as $bed => $erg): ?>
              <div class="rg-zeile"><span class="marke2 <?= $farbe[$erg] ?? '' ?>"><?= Fmt::h($kurz[$erg] ?? $erg) ?></span>
                <?php if (count($zelle) > 1 || $bed !== 'ohne'): ?><span class="akq-klein"><?= Fmt::h($bedName[$bed] ?? $bed) ?></span><?php endif; ?></div>
            <?php endforeach; endif; ?></td>
        <?php endforeach; ?></tr>
    <?php endforeach; ?>
  </tbody></table></div>
  <div class="rg-legende">
    <div><span class="marke2 gut">Ja</span><span>Vecom darf schicken — automatisch nur per E-Mail und nur bei eingeschaltetem Versand.</span></div>
    <div><span class="marke2 warnung">Nach Prüfung</span><span>Du schaust dir den Betrieb an und entscheidest selbst.</span></div>
    <div><span class="marke2 schlecht">Nein</span><span>Gesperrt.</span></div>
    <div><span class="marke2">Lieber nicht</span><span>Rechtslage unklar — deshalb ebenfalls gesperrt.</span></div>
  </div>
  <p class="akq-klein" style="margin-top:12px">Stand der Regeln: <?= $geprueft !== '' ? 'geprüft am ' . Fmt::h(Fmt::datum($geprueft)) : 'noch nicht geprüft' ?>.
    Keine Rechtsberatung — vor dem ersten echten Versand anwaltlich prüfen lassen.</p>

  <details class="rg-auf"><summary>Regeln ändern (nur nötig, wenn sich das Recht ändert)</summary>
    <p class="akq-klein" style="margin-bottom:10px">Nach dem Speichern werden alle Betriebe dieses Landes neu eingestuft. Begründung und Quelle helfen später beim Nachvollziehen.</p>
    <?php foreach ($regeln as $r): $erg = (string) $r['ergebnis']; ?>
      <details class="rg-regel"><summary>
        <b><?= Fmt::h($landName[(string) $r['land']] ?? (string) $r['land']) ?></b> ·
        <?= Fmt::h(AkquiseGate::KANAELE[(string) $r['kanal']] ?? (string) $r['kanal']) ?>
        <span class="akq-klein"><?= Fmt::h($bedName[(string) $r['bedingung']] ?? (string) $r['bedingung']) ?></span>
        <span class="marke2 <?= $farbe[$erg] ?? '' ?>"><?= Fmt::h($kurz[$erg] ?? $erg) ?></span>
        <?= (int) $r['aktiv'] ? '' : '<span class="marke2">abgeschaltet</span>' ?></summary>
        <form method="post" action="<?= Fmt::h(url('akquise')) ?>"
              data-frage="Regel ändern? Alle Betriebe dieses Landes werden danach neu eingestuft." data-ja="Ja, speichern">
          <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_regel_speichern"><input type="hidden" name="regel" value="<?= (int) $r['id'] ?>">
          <input type="hidden" name="zurueck" value="akquise/regeln">
          <div class="reihe">
            <div class="feld"><label>Darf Vecom?</label><select name="ergebnis"><?php foreach (AkquiseGate::STATUS as $k => $w): ?>
              <option value="<?= $k ?>"<?= $erg === $k ? ' selected' : '' ?>><?= Fmt::h($w) ?></option><?php endforeach; ?></select></div>
            <div class="feld"><label>Zuletzt geprüft am</label><input type="date" name="geprueft_am" value="<?= Fmt::h((string) $r['geprueft_am']) ?>"></div>
          </div>
          <div class="feld"><label>Warum?</label><textarea name="begruendung" rows="3"><?= Fmt::h((string) $r['begruendung']) ?></textarea></div>
          <div class="feld"><label>Quelle (Link zum Gesetz oder Artikel)</label><input name="quelle" value="<?= Fmt::h((string) $r['quelle']) ?>"></div>
          <label class="akq-haken"><input type="checkbox" name="aktiv" value="1"<?= (int) $r['aktiv'] ? ' checked' : '' ?>> Regel gilt</label>
          <button class="knopf">Regel speichern</button>
        </form>
      </details>
    <?php endforeach; ?>
  </details>
</div>

<div class="block" id="versand">
  <h2>E-Mails verschicken</h2>
  <p class="rg-erkl">Ist der Versand aus, geht keine einzige Mail automatisch raus. Ist er an, verschickt Vecom nur Texte, die du freigegeben hast —
    und nur an Betriebe, bei denen oben „Ja“ steht. Heute verschickt: <b><?= $heute ?></b> · in 30 Tagen zurückgehalten: <b><?= $blockiert ?></b>.</p>
  <form method="post" action="<?= Fmt::h(url('akquise')) ?>"
        data-frage="Einstellungen speichern? Ist der Versand eingeschaltet, kann jede freigegebene Mail an einen erlaubten Betrieb mit einem Klick hinausgehen." data-ja="Ja, speichern">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_grenzen_speichern"><input type="hidden" name="zurueck" value="akquise/regeln#versand">
    <label class="akq-haken rg-an"><input type="checkbox" name="akq_versand_an" value="1"<?= $grenzen['versand_an'] ? ' checked' : '' ?>>
      <span><b>E-Mail-Versand einschalten</b><br><span class="akq-klein">Die Notbremse oben rechts stoppt jederzeit alles.</span></span></label>
    <div class="reihe">
      <div class="feld"><label>Deine Telefonnummer unter jeder Mail</label><input name="akq_absender_telefon" value="<?= Fmt::h($telefon) ?>" placeholder="+39 …"></div>
    </div>
    <label class="akq-haken"><input type="checkbox" name="akq_konfigurator_link" value="1"<?= $konfigurator ? ' checked' : '' ?>>
      Link zum Bedarfsrechner in jede Mail einfügen</label>

    <p class="akq-klein" style="margin:4px 0 0">Schutz vor Spam-Verdacht: höchstens <b><?= (int) $grenzen['tag'] ?></b> Mails am Tag und <b><?= (int) $grenzen['stunde'] ?></b> pro Stunde,
      mindestens <?= Fmt::h($dauer((int) $grenzen['pause'])) ?> Abstand, derselbe Betrieb frühestens nach <?= (int) $grenzen['domain_tage'] ?> Tagen wieder.
      Automatischer Stopp nach <?= (int) $grenzen['fehler'] ?> Fehlern an einem Tag oder <?= (int) $grenzen['bounce'] ?> unzustellbaren Mails in einer Woche.</p>
    <details class="rg-auf"><summary>Diese Grenzen ändern</summary>
      <div class="rg-grenzen">
        <div class="feld"><label>Mails je Tag</label><input type="number" name="akq_limit_tag" min="0" max="200" value="<?= (int) $grenzen['tag'] ?>"><span class="akq-klein">Empfohlen: 10</span></div>
        <div class="feld"><label>Mails je Stunde</label><input type="number" name="akq_limit_stunde" min="0" max="50" value="<?= (int) $grenzen['stunde'] ?>"><span class="akq-klein">Empfohlen: 3</span></div>
        <div class="feld"><label>Abstand zwischen zwei Mails (Sekunden)</label><input type="number" name="akq_pause_sekunden" min="0" value="<?= (int) $grenzen['pause'] ?>"><span class="akq-klein">Empfohlen: 120</span></div>
        <div class="feld"><label>Derselbe Betrieb wieder nach (Tagen)</label><input type="number" name="akq_limit_domain_tage" min="1" value="<?= (int) $grenzen['domain_tage'] ?>"><span class="akq-klein">Empfohlen: 180</span></div>
        <div class="feld"><label>Stopp nach Fehlern an einem Tag</label><input type="number" name="akq_fehler_grenze" min="1" value="<?= (int) $grenzen['fehler'] ?>"><span class="akq-klein">Empfohlen: 3</span></div>
        <div class="feld"><label>Stopp nach Unzustellbaren in 7 Tagen</label><input type="number" name="akq_bounce_grenze" min="1" value="<?= (int) $grenzen['bounce'] ?>"><span class="akq-klein">Empfohlen: 2</span></div>
      </div>
    </details>
    <button class="knopf haupt" style="margin-top:14px">Speichern</button>
  </form>
</div>

<div class="block" id="sperrliste">
  <h2>Nie kontaktieren <span class="akq-klein" style="font-weight:400">· <?= count($sperrliste) ?> Einträge</span></h2>
  <p class="rg-erkl">Wer hier steht, bekommt nie wieder etwas von Vecom — keine Mail, keinen Brief, keinen Anruf.
    Abmeldungen und „kein Interesse“-Antworten landen automatisch hier und bleiben für immer.</p>
  <form method="post" action="<?= Fmt::h(url('akquise')) ?>" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-bottom:14px">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_sperre_eintragen"><input type="hidden" name="zurueck" value="akquise/regeln#sperrliste">
    <div><label class="akq-klein">Was sperren?</label><select name="art" style="width:auto"><option value="domain">Webseite</option><option value="email">E-Mail</option><option value="telefon">Telefon</option></select></div>
    <div style="flex:1 1 200px"><label class="akq-klein">Adresse oder Nummer</label><input name="wert" required placeholder="z. B. pizzeria-roma.it"></div>
    <div style="flex:2 1 260px"><label class="akq-klein">Grund (für dich)</label><input name="grund" placeholder="z. B. Anruf 24.09.: möchte nicht kontaktiert werden"></div>
    <button class="knopf">Eintragen</button>
  </form>
  <?php if ($sperrliste): ?>
  <div class="tabellenrahmen rg-sperre"><table><thead><tr><th>Was</th><th>Adresse / Nummer</th><th>Grund</th><th>Woher</th><th>Seit</th><th></th></tr></thead><tbody>
    <?php foreach ($sperrliste as $s): ?>
      <tr><td><?= Fmt::h($artName[(string) $s['art']] ?? (string) $s['art']) ?></td><td><?= Fmt::h((string) $s['wert']) ?></td><td class="akq-klein"><?= Fmt::h((string) $s['grund']) ?></td>
        <td><span class="marke2"><?= Fmt::h($quelleName[(string) $s['quelle']] ?? (string) $s['quelle']) ?></span></td><td class="akq-klein"><?= Fmt::h(Fmt::datum((string) $s['created_at'])) ?></td>
        <td><?php if (!in_array($s['quelle'], ['abmeldung', 'antwort'], true)): ?>
          <form method="post" action="<?= Fmt::h(url('akquise')) ?>" data-frage="Diesen Eintrag entfernen? Der Betrieb könnte danach wieder angeschrieben werden." data-ja="Ja, entfernen">
            <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_sperre_loeschen"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
            <input type="hidden" name="zurueck" value="akquise/regeln#sperrliste"><button class="knopf klein">Entfernen</button></form>
          <?php else: ?><span class="akq-klein">bleibt</span><?php endif; ?></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
</div>

<h2 class="rg-abschnitt">Einmal einrichten</h2>
<div class="rg-drei">
  <div class="block" id="check">
    <h2>Website-Check auf deiner Seite</h2>
    <p class="rg-erkl">Betriebe prüfen ihre Website selbst unter <a href="/website-check.php" target="_blank" rel="noopener" style="text-decoration:underline">vecom-design.it/website-check.php</a>.
      Sie sehen sofort sechs Punkte und wählen getrennt: ausführliche Analyse (dann darfst du antworten) und Werbe-Einwilligung (erst nach Bestätigung per Mail).</p>
    <p class="rg-stand"><span class="akq-ampel <?= $ckAn ? 'gruen' : '' ?>"><i aria-hidden="true"></i>
      <?= $ckAn ? 'An · ' . $ckZahl . ' Checks in 30 Tagen' . ($ckOffen ? ', ' . $ckOffen . ' offen' : '') : 'Aus' ?></span></p>
    <form method="post" action="<?= Fmt::h(url('akquise')) ?>"><?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_check_schalten">
      <input type="hidden" name="an" value="<?= $ckAn ? '' : '1' ?>">
      <button class="knopf"><?= $ckAn ? 'Ausschalten' : 'Einschalten' ?></button></form>
    <p class="akq-klein" style="margin-top:10px">Name, E-Mail und Telefon werden nach <?= AkquiseCheck::FRIST_TAGE ?> Tagen gelöscht, wenn daraus weder Einwilligung noch Auftrag wurde.</p>
  </div>

  <?php if (!AkquiseGate::briefAn()): /* Briefe ausgeschaltet (27.09.2026): kein Brief, keine Brief-Serie, kein „Vecom soll anschreiben“. */ ?>
  <div class="block" id="briefdienst">
    <h2>Briefe per Post</h2>
    <p class="rg-stand"><span class="akq-ampel"><i aria-hidden="true"></i>Ausgeschaltet</span></p>
    <p class="rg-erkl">Kein Brief, keine Brief-Serie, kein Druckblatt, und Partner sehen „Vecom soll anschreiben“ nicht.
      Kontakt gibt es nur per E-Mail — und die nur mit Einwilligung. Schlüssel und Einstellungen des Briefdienstes bleiben gespeichert.</p>
    <form method="post" action="<?= Fmt::h(url('akquise')) ?>" data-frage="Briefe wieder einschalten? Dann schlägt die Verwaltung wieder Briefe vor, und die Brief-Serie ist zurück." data-ja="Ja, einschalten">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_brief_schalten"><input type="hidden" name="an" value="1">
      <button class="knopf">Briefe wieder einschalten</button></form>
  </div>
  <?php else: ?>
  <div class="block" id="briefdienst">
    <h2>Briefe per Post <span class="akq-klein" style="font-weight:400">· nur Italien</span></h2>
    <form method="post" action="<?= Fmt::h(url('akquise')) ?>" style="margin:0 0 10px"><?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_brief_schalten">
      <input type="hidden" name="an" value=""><button class="knopf klein">Briefe ganz ausschalten</button></form>
    <p class="rg-erkl">Freigegebene Briefe druckt, kuvertiert und frankiert ufficiopostale.com. Vor jedem Brief siehst du Preis und Blatt — raus geht er erst mit deinem Klick.</p>
    <p class="rg-stand"><span class="akq-ampel <?= $bdDa ? ($bdTest ? 'gelb' : 'gruen') : '' ?>"><i aria-hidden="true"></i>
      <?= $bdDa ? ($bdTest ? 'Eingerichtet · Testbetrieb (nichts wird gedruckt, nichts kostet)' : 'Eingerichtet · echter Betrieb (Briefe kosten)') : 'Noch nicht eingerichtet' ?></span></p>
    <details class="rg-auf" style="margin:0 0 12px;border:0;padding:0"><summary>So bekommst du den Schlüssel</summary>
      <ol class="rg-schritte" style="margin:0"><li>Bei console.openapi.com anmelden.</li><li>Bereich „Ufficio Postale“ öffnen.</li>
        <li>Einen Schlüssel mit dem Recht <code>POST/GET/PATCH ws.ufficiopostale.com/ordinarie</code> erzeugen.</li><li>Unten einfügen und speichern.</li></ol></details>
    <form method="post" action="<?= Fmt::h(url('akquise')) ?>" style="display:grid;gap:8px"
          <?= $bdTest ? '' : 'data-frage="Briefdienst-Einstellungen speichern? Im echten Betrieb gehen bestätigte Briefe wirklich raus und kosten." data-ja="Ja, speichern"' ?>>
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_briefdienst_speichern">
      <div class="feld" style="margin:0"><label>Schlüssel</label>
        <input type="password" name="token" autocomplete="off" placeholder="<?= $bdDa ? 'leer lassen = bleibt wie er ist' : 'Schlüssel einfügen' ?>"></div>
      <div class="feld" style="margin:0"><label>Versandart</label><select name="produkt">
        <?php $bdProdukt = AkquiseBriefdienst::produkt(); foreach (AkquiseBriefdienst::PRODUKTE as $pk => [$pName, $pPreis]): ?>
          <option value="<?= $pk ?>"<?= $bdProdukt === $pk ? ' selected' : '' ?>><?= Fmt::h($pName . ' — ' . $pPreis) ?></option><?php endforeach; ?></select>
        <span class="akq-klein">Den echten Preis zeigt jede Vorschau vor dem Versand. Posta Massiva braucht beim Schlüssel zusätzlich die Berechtigung <code>ws.ufficiopostale.com/posta_massiva</code> — erst im Testbetrieb ausprobieren.</span></div>
      <label class="akq-haken"><input type="checkbox" name="test" value="1" <?= $bdTest ? 'checked' : '' ?>> Testbetrieb — nichts wird gedruckt, nichts kostet</label>
      <?php if ($bdDa): ?><label class="akq-haken"><input type="checkbox" name="loeschen" value="1"> Schlüssel entfernen</label><?php endif; ?>
      <button class="knopf" style="justify-self:start">Speichern</button></form>
  </div>
  <?php endif; ?>

  <?php /* WhatsApp Business (28.09.2026, Uwe: Ja zu V3) -- nur an Betriebe mit WhatsApp-Einwilligung. */
        require_once dirname(__DIR__) . '/src/WhatsAppCloud.php';
        $wa = WhatsAppCloud::einstellungen();
        $waV = sicher(static fn() => WhatsAppCloud::vorlagen(), []);
        $waFarbe = ['APPROVED' => 'var(--gut)', 'REJECTED' => 'var(--schlecht, #e5534b)', 'fehler' => 'var(--schlecht, #e5534b)']; ?>
  <div class="block" id="whatsapp">
    <h2>WhatsApp Business <span class="akq-klein" style="font-weight:400">· nur mit Einwilligung</span></h2>
    <p class="rg-erkl">Betriebe, die ausdrücklich auch WhatsApp erlaubt haben, bekommen die Folge-Schritte als von Meta genehmigte WhatsApp-Vorlage statt als Mail.
      Ohne diese Einwilligung geht nie etwas per WhatsApp raus. „STOP“ als Antwort sperrt den Betrieb sofort; jede andere Antwort landet bei den Antworten.</p>
    <details<?= WhatsAppCloud::bereit() ? '' : ' open' ?>><summary class="akq-klein" style="cursor:pointer">So richtest du es ein (einmal, etwa 20 Minuten)</summary>
      <ol class="akq-klein" style="line-height:1.7">
        <li>Auf <b>business.facebook.com</b> das Unternehmen „Vecom Design“ anlegen bzw. wählen und unter <b>WhatsApp-Konten</b> ein Konto erstellen.</li>
        <li>Auf <b>developers.facebook.com</b> eine App vom Typ „Business“ anlegen und das Produkt <b>WhatsApp</b> hinzufügen. Dort die Telefonnummer verbinden. Achtung: Eine Nummer, die in der normalen WhatsApp-Business-App läuft, geht nur über „Coexistence“ gleichzeitig — sonst eine zweite Nummer nehmen.</li>
        <li>Unter WhatsApp → <b>API-Einrichtung</b> die <b>Telefonnummer-ID</b> und die <b>WhatsApp-Business-Konto-ID</b> abschreiben und unten eintragen.</li>
        <li>Unter <b>Unternehmenseinstellungen → Systembenutzer</b> einen Systembenutzer anlegen, ihm App und WhatsApp-Konto zuweisen und einen <b>dauerhaften Schlüssel</b> mit <code>whatsapp_business_messaging</code> und <code>whatsapp_business_management</code> erzeugen. Unten eintragen.</li>
        <li>In der App unter <b>Einstellungen → Allgemein</b> das <b>App-Geheimnis</b> kopieren und unten eintragen.</li>
        <li>WhatsApp → <b>Konfiguration → Webhook</b>: Rückruf-URL <code><?= Fmt::h($wa['webhook']) ?></code>, Überprüfungsschlüssel <code><?= Fmt::h($wa['pruefwort']) ?></code>, dann das Feld <b>messages</b> abonnieren.</li>
        <li>Hier „Vorlagen bei Meta anmelden“ drücken, nach einiger Zeit „Stand abrufen“. Sobald Schritte auf <b>APPROVED</b> stehen, gehen sie raus (Schalter „Folge per WhatsApp“ und „Folge-Mails“ an).</li>
      </ol></details>
    <form method="post" action="<?= Fmt::h(url('akquise')) ?>" class="rg-grenzen" style="margin-top:10px">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_wa_speichern">
      <div><label class="akq-klein" for="wa_nummer">Telefonnummer-ID</label><input id="wa_nummer" name="nummer_id" inputmode="numeric" value="<?= Fmt::h($wa['nummer_id']) ?>"></div>
      <div><label class="akq-klein" for="wa_konto">WhatsApp-Business-Konto-ID</label><input id="wa_konto" name="konto_id" inputmode="numeric" value="<?= Fmt::h($wa['konto_id']) ?>"></div>
      <div><label class="akq-klein" for="wa_token">Dauerhafter Schlüssel <?= $wa['token'] ? '(hinterlegt — leer lassen zum Behalten)' : '' ?></label><input id="wa_token" name="token" type="password" autocomplete="off"></div>
      <div><label class="akq-klein" for="wa_geheim">App-Geheimnis <?= $wa['app_geheim'] ? '(hinterlegt — leer lassen zum Behalten)' : '' ?></label><input id="wa_geheim" name="app_geheim" type="password" autocomplete="off"></div>
      <button class="knopf" style="justify-self:start">Speichern</button>
    </form>
    <?php if ($waV): ?>
      <table style="margin-top:12px"><thead><tr><th>Schritt</th><th>Vorlage</th><th>Stand bei Meta</th></tr></thead><tbody>
        <?php foreach ($waV as $wS => $wJe): foreach ($wJe as $wSp => $wZ): ?>
          <tr><td class="akq-klein"><?= (int) $wS ?> · <?= strtoupper(Fmt::h($wSp)) ?></td><td class="akq-klein" title="<?= Fmt::h($wZ['text']) ?>"><code><?= Fmt::h($wZ['name']) ?></code></td>
            <td class="akq-klein" style="color:<?= $waFarbe[$wZ['meta_status']] ?? 'inherit' ?>"><?= Fmt::h($wZ['meta_status']) ?><?= $wZ['meta_grund'] ? ' — ' . Fmt::h((string) $wZ['meta_grund']) : '' ?></td></tr>
        <?php endforeach; endforeach; ?>
      </tbody></table>
      <?php if (WhatsAppCloud::bereit()): ?>
        <div class="knoepfe" style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap">
          <form method="post" action="<?= Fmt::h(url('akquise')) ?>"><?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_wa_anmelden"><button class="knopf">Vorlagen bei Meta anmelden</button></form>
          <form method="post" action="<?= Fmt::h(url('akquise')) ?>"><?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_wa_stand"><button class="knopf">Stand abrufen</button></form>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="block" id="postfach">
    <h2>Antworten automatisch einlesen</h2>
    <p class="rg-erkl">Vecom schaut alle 10 Minuten in dein Postfach und ordnet Antworten dem richtigen Betrieb zu.
      „Kein Interesse“ kommt sofort auf „Nie kontaktieren“, Interesse meldet sich bei dir.
      Vecom antwortet nicht, löscht nichts und markiert nichts als gelesen.</p>
    <p class="rg-stand"><span class="akq-ampel <?= $pfFehler !== '' ? 'rot' : ($pfDa ? 'gruen' : '') ?>"><i aria-hidden="true"></i>
      <?= $pfDa ? 'Verbunden: ' . Fmt::h($pfZ['nutzer']) : 'Noch nicht eingerichtet' ?>
      <?= !empty($pfStand['am']) ? ' · zuletzt ' . Fmt::h(Fmt::datum((string) $pfStand['am'])) . ' ' . Fmt::h(substr((string) $pfStand['am'], 11, 5)) : '' ?>
      <?= $pfZahl ? ' · ' . $pfZahl . ' Antworten in 30 Tagen' : '' ?></span></p>
    <?php if ($pfFehler !== ''): ?><p class="akq-klein" style="color:var(--rot,#ef6b5b);margin:0 0 10px">Letzter Fehler: <?= Fmt::h($pfFehler) ?></p><?php endif; ?>
    <form method="post" action="<?= Fmt::h(url('akquise')) ?>" style="display:grid;gap:8px">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_postfach_speichern">
      <div class="feld" style="margin:0"><label>Server</label><input name="host" value="<?= Fmt::h($pfZ['host']) ?>" placeholder="w0123456.kasserver.com" autocomplete="off">
        <span class="akq-klein">Bei All-Inkl steht er im KAS unter E-Mail. Feld leeren und speichern = Verbindung trennen.</span></div>
      <div class="feld" style="margin:0"><label>Benutzer</label><input name="nutzer" value="<?= Fmt::h($pfZ['nutzer']) ?>" autocomplete="off" placeholder="m0123456 oder kontakt@vecom-design.it"></div>
      <div class="feld" style="margin:0"><label>Passwort</label><input type="password" name="passwort" autocomplete="new-password" placeholder="<?= $pfDa ? 'leer lassen = bleibt' : 'Passwort des Postfachs' ?>"></div>
      <details class="rg-auf" style="margin:0;border:0;padding:0"><summary>Weitere Angaben (meist nicht nötig)</summary>
        <div class="reihe">
          <div class="feld"><label>Port</label><input name="port" inputmode="numeric" value="<?= (int) ($pfZ['port'] ?: 993) ?>"></div>
          <div class="feld"><label>Ordner</label><input name="ordner" value="<?= Fmt::h($pfZ['ordner']) ?>"></div>
        </div></details>
      <button class="knopf" style="justify-self:start">Speichern</button>
    </form>
    <?php if ($pfDa): ?>
      <form method="post" action="<?= Fmt::h(url('akquise')) ?>" style="margin-top:8px"><?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_postfach_jetzt">
        <button class="knopf">Jetzt prüfen</button></form>
    <?php endif; ?>
  </div>

  <div class="block" id="rechner">
    <h2>Verbindung zu deinem PC</h2>
    <p class="rg-erkl">Nur nötig, wenn die Betriebssuche auf deinem PC läuft. Der PC darf damit Betriebe und Textvorschläge melden — nie etwas verschicken, freigeben oder sperren.</p>
    <?php if ($schluesselEinmal): ?>
      <div class="hinweis gut" style="margin-bottom:10px">Nur jetzt sichtbar. Auf „Kopieren“ klicken — am PC übernimmt
        <code>npm run verbinden</code> den Schlüssel aus der Zwischenablage.</div>
      <div style="display:flex;gap:8px;margin-bottom:10px">
        <input id="akq_schluessel" readonly value="<?= Fmt::h($schluesselEinmal) ?>" onclick="this.select()" style="font-family:ui-monospace,monospace">
        <button class="knopf haupt" type="button" data-kopieren="akq_schluessel">Kopieren</button></div>
    <?php else: ?>
      <p class="rg-stand"><span class="akq-ampel <?= $schluesselDa ? 'gruen' : '' ?>"><i aria-hidden="true"></i>
        <?= $schluesselDa ? 'Eingerichtet (der Schlüssel wird nie wieder angezeigt)' : 'Nicht eingerichtet' ?></span></p>
    <?php endif; ?>
    <form method="post" action="<?= Fmt::h(url('akquise')) ?>" <?= $schluesselDa ? 'data-frage="Neuen Schlüssel erzeugen? Der alte gilt sofort nicht mehr — der PC braucht dann den neuen." data-ja="Ja, neu erzeugen"' : '' ?>>
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_schluessel_neu"><input type="hidden" name="zurueck" value="akquise/regeln#rechner">
      <button class="knopf"><?= $schluesselDa ? 'Neuen Schlüssel erzeugen' : 'Verbindung einrichten' ?></button></form>
  </div>
</div>
