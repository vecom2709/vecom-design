<?php
/**
 * DIE TELEFONASSISTENTIN — ALLES, WAS EINGESTELLT WIRD
 * ===========================================================================
 *
 * Hier steht, was Manuela IST. Was sie GETAN hat, steht unter
 * „Telefonassistent" — das ist Auswertung und gehört nicht zwischen
 * Eingabefelder. Wer eine Zahl nachsehen will, soll nicht an einem Schlüssel
 * vorbeikommen, den er versehentlich neu erzeugt.
 *
 * Drei Dinge in dieser Reihenfolge, weil sie aufeinander aufbauen:
 * der Betriebsmodus (was sie heute sagen darf), der Schlüssel (womit sie
 * überhaupt hereinkommt), der Zugang zu STRATO (womit wir die Gespräche
 * zurückholen) und zuletzt die vierzehn Konfigurationen (was sie kann).
 */
$basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');

/* Die Konfigurator-Antworten als Aufzaehlung. Sie muessen in Stratos Schema
   als enum stehen, damit das Modell aus einer Liste waehlt statt zu
   formulieren -- Freitext verwirft der Konfigurator ohnehin. */
$fragen = [];
foreach (Telefon::VORWEG as $f) {
    $frage = Baukasten::FRAGEN[$f] ?? null;
    if ($frage === null) { continue; }
    $fragen[$f] = ['art' => $frage['art'] ?? 'einfach',
                   'werte' => array_map('strval', array_keys($frage['optionen'] ?? []))];
}
?>

<div class="block">
  <h2>Betrieb</h2>
  <p style="color:var(--leise);font-size:12.5px;margin:-4px 0 14px">
    Was sie am Telefon über deine Lage sagen darf. „Urlaub" und „ausgelastet" ändern
    ihren Ton und die Termine, die sie anbietet — sie erfindet dazu nichts.</p>
  <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:flex;gap:10px;align-items:flex-end">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="telefon_modus">
    <input type="hidden" name="zurueck" value="einstellungen?b=telefon">
    <div class="feld" style="margin:0;min-width:220px"><label>Modus</label>
      <select name="modus">
        <?php foreach (Telefon::MODI as $wert => $wort): ?>
          <option value="<?= Fmt::h($wert) ?>" <?= $modus === $wert ? 'selected' : '' ?>><?= Fmt::h($wort) ?></option>
        <?php endforeach; ?>
      </select></div>
    <button class="knopf haupt">Übernehmen</button>
  </form>
</div>

<div class="block">
  <h2>Der Schlüssel</h2>
  <p style="color:var(--leise);font-size:12.5px;margin:-4px 0 12px">
    Er öffnet nur, was am Telefon gebraucht wird: nachschlagen, Preis schätzen, Tag und
    Uhrzeit erfahren, Konfigurator-Link schicken, Anliegen melden, Zusammenfassung senden,
    eine offene Frage notieren, jemandem Schritt für Schritt weiterhelfen.
    Nicht deinen Zugang, nicht Stripe, nicht die Zahlungen.
    Wird er bekannt, kann jemand Anfragen anlegen und Links an <b>hinterlegte</b> Adressen
    schicken — lästig, nicht gefährlich. Und oben ist er in zehn Sekunden neu.</p>
  <div class="feld"><label>Adresse (bei STRATO als URL)</label>
    <input readonly value="<?= Fmt::h((string) $adresse) ?>" onclick="this.select()"></div>
  <?php /* VERDECKT, WEIL EIN BILDSCHIRMFOTO SCHNELLER GEMACHT IST ALS EIN
           NEUER SCHLÜSSEL. Am 6.9. stand er offen auf dieser Seite und lag
           damit in einem Screenshot — danach musste er gewechselt werden.
           Kopieren geht trotzdem: dafür braucht ihn niemand zu sehen. */ ?>
  <div class="feld"><label>Schlüssel (Kopfzeile <code>X-Vecom-Telefon</code>)</label>
    <div style="display:flex;gap:8px;align-items:center">
      <input id="telefon_schluessel" type="password" readonly
             value="<?= Fmt::h((string) $schluessel) ?>" style="flex:1">
      <button class="knopf" type="button" data-kopieren="telefon_schluessel">Kopieren</button>
      <button class="knopf" type="button" onclick="var f=document.getElementById('telefon_schluessel');
              f.type = f.type === 'password' ? 'text' : 'password';
              this.textContent = f.type === 'password' ? 'Zeigen' : 'Verbergen';">Zeigen</button>
    </div></div>
  <p style="color:var(--leise);font-size:12.5px">
    Er steht verdeckt da, damit er nicht in ein Bildschirmfoto gerät. Er gehört nicht in eine
    E-Mail und nicht in einen Chat. Kopieren, drüben einfügen, fertig.</p>
  <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:12px"
        data-frage="Der alte Schlüssel wird damit ungültig — STRATO ruft danach ins Leere, bis du den neuen dort einträgst. Fortfahren?"
        data-ja="Ja, neuen Schlüssel erzeugen">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="telefon_schluessel_neu">
    <input type="hidden" name="zurueck" value="einstellungen?b=telefon">
    <button class="knopf">Neuen Schlüssel erzeugen</button></form>
</div>

<?php /* ---------- Der Rückweg: die Gespräche von STRATO ---------- */ ?>
<div class="block">
  <h2>Gespräche von STRATO holen
    <?php if ($strato['eingerichtet'] && $strato['fehler'] === ''): ?>
      <span class="marke2 gut">verbunden</span>
    <?php elseif ($strato['eingerichtet']): ?>
      <span class="marke2 schlecht">antwortet nicht</span>
    <?php else: ?>
      <span class="marke2">nicht eingerichtet</span>
    <?php endif; ?>
  </h2>

  <p style="color:var(--dim);font-size:13.5px;line-height:1.7;margin:8px 0 14px">
    Bei STRATO liegt zu jedem Anruf mehr, als die Oberfläche dort zeigt: Betreff,
    Zusammenfassung <b>und eine maschinelle Auswertung</b> — wie das Gespräch ausging, wie
    beteiligt der Anrufer war, welche Probleme auffielen und ob Manuela gegen ihre eigenen
    Anweisungen gehandelt hat. Mit einem Zugang holt die Verwaltung das stündlich herüber
    und legt es dauerhaft neben unsere eigene Spur. Ohne Zugang bleibt die Telefonseite bei
    dem, was wir selbst protokolliert haben.
  </p>

  <?php if ($strato['fehler'] !== ''): ?>
    <div class="hinweis schlecht"><?= Fmt::h($strato['fehler']) ?><br>
      <span style="font-size:12.5px">Ein Abmelden bei STRATO genügt, damit der Token abläuft.
      Dann hier neu hinterlegen — es ist dieselbe Handvoll Klicks wie beim ersten Mal.</span></div>
  <?php elseif ($strato['eingerichtet']): ?>
    <div class="hinweis gut">
      <?= (int) $strato['anzahl'] ?> Gespräche liegen hier.
      <?= $strato['zuletzt'] !== '' ? 'Zuletzt geholt ' . Fmt::h(Fmt::seit($strato['zuletzt'])) . '.' : 'Noch nicht geholt.' ?>
    </div>
  <?php endif; ?>

  <details style="margin-bottom:14px">
    <summary style="cursor:pointer;font-size:13px;color:var(--cyan)">Wo die beiden Angaben stehen</summary>
    <ol style="color:var(--dim);font-size:13px;line-height:1.9;padding-left:20px;margin:10px 0 0">
      <li>Bei STRATO anmelden, sodass die Gesprächsliste zu sehen ist.</li>
      <li>Mit <b>F12</b> die Entwicklerwerkzeuge öffnen, Reiter <b>Application</b> (Firefox: <b>Speicher</b>).</li>
      <li>Links unter <b>Cookies</b> die Adresse von STRATO wählen.</li>
      <li>Der Eintrag <code>sb-…-auth-token</code> enthält beides. Beginnt der Wert mit
          <code>base64-</code>, ist er kodiert — dann in der Konsole
          <code>JSON.parse(atob(…))</code>, oder frag mich, ich lese ihn aus.</li>
      <li>Aus dem entschlüsselten Inhalt: <b>refresh_token</b> in das zweite Feld.
          Der öffentliche Schlüssel (beginnt mit <code>eyJ</code>) steht im Seitenquelltext.</li>
    </ol>
    <p style="color:var(--leise);font-size:12.5px;margin-top:10px">
      Kein Passwort, nirgends. Der Auffrischungs-Token gilt nur für dieses eine Konto bei
      diesem einen Dienst, wird bei jeder Benutzung ausgetauscht und wird wertlos, sobald du
      dich bei STRATO abmeldest. Er gehört trotzdem nicht in eine E-Mail und nicht in einen Chat.
    </p>
  </details>

  <form method="post" action="<?= Fmt::h(url('')) ?>">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="strato_zugang">
    <input type="hidden" name="zurueck" value="einstellungen?b=telefon">
    <div class="feld"><label>Öffentlicher Schlüssel<?= $strato['eingerichtet'] ? ' (leer lassen = unverändert)' : '' ?></label>
      <input type="password" name="anon" autocomplete="off" placeholder="eyJhbGciOi…"></div>
    <div class="feld"><label>Auffrischungs-Token<?= $strato['eingerichtet'] ? ' (leer lassen = unverändert)' : '' ?></label>
      <input type="password" name="refresh" autocomplete="off" placeholder="aus dem Cookie sb-…-auth-token"></div>
    <button class="knopf haupt">Zugang hinterlegen und prüfen</button>
  </form>

  <?php if ($strato['eingerichtet']): ?>
    <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:12px">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="strato_holen">
      <input type="hidden" name="zurueck" value="einstellungen?b=telefon">
      <button class="knopf">Jetzt holen</button></form>
  <?php endif; ?>

  <?php /* DIE SPERRLISTE GEHÖRT SICHTBAR
           Was hier gelöscht wurde, liegt bei STRATO noch. Ohne diese Zeile
           wäre die Sperrliste eine Falle: Man löscht ein Gespräch, es kommt
           nicht wieder — und niemand weiß, warum ein Anruf, den man drüben
           sieht, hier fehlt. */ ?>
  <?php if (($strato['gesperrt'] ?? 0) > 0): ?>
    <div style="border-top:1px solid var(--linie);margin-top:16px;padding-top:14px">
      <p style="color:var(--dim);font-size:13px;line-height:1.65;margin:0 0 10px">
        <b><?= (int) $strato['gesperrt'] ?></b>
        <?= (int) $strato['gesperrt'] === 1 ? 'Gespräch wurde' : 'Gespräche wurden' ?>
        hier gelöscht und
        <?= (int) $strato['gesperrt'] === 1 ? 'wird' : 'werden' ?> beim Abgleich übersprungen.
        Bei STRATO
        <?= (int) $strato['gesperrt'] === 1 ? 'liegt es' : 'liegen sie' ?> noch — daran kommen
        wir nicht heran. Hebst du die Sperre auf,
        <?= (int) $strato['gesperrt'] === 1 ? 'kommt es' : 'kommen sie' ?> beim nächsten Lauf zurück.
      </p>
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0"
            data-frage="Die gelöschten Gespräche kommen beim nächsten Abgleich zurück, sofern STRATO sie noch hat. Fortfahren?"
            data-ja="Ja, Sperre aufheben">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="gespraeche_sperre_loesen">
        <input type="hidden" name="zurueck" value="einstellungen?b=telefon">
        <button class="knopf">Sperre aufheben</button></form>
    </div>
  <?php endif; ?>

  <?php if ($strato['eingerichtet']): ?>
    <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:12px"
          data-frage="Der Zugang wird gelöscht. Die schon geholten Gespräche bleiben — es kommen nur keine neuen mehr dazu. Fortfahren?"
          data-ja="Ja, Zugang löschen">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="strato_loeschen">
      <input type="hidden" name="zurueck" value="einstellungen?b=telefon">
      <button class="knopf">Zugang löschen</button></form>
  <?php endif; ?>
</div>

<?php
/* ---------- Die fertigen Konfigurationen ----------
   Der Rumpf wird als Zeichenkette gebaut und nicht durch json_encode
   gejagt: Stratos Platzhalter {{ name }} muessen woertlich stehen bleiben. */
$konfigs = [];

$konfigs['kunde_nachschlagen'] = [
  'zweck' => 'Wer ruft an? Schlägt über Rufnummer, Kundennummer oder Namen nach. '
           . 'Gibt Name, Betrieb, Sprache und Projektstand zurück — nie Beträge. '
           . 'IMMER zuerst aufrufen, bevor du „hilfe“, „angebot_link“, „uebergabe“, „melde“ oder '
           . '„zusammenfassung“ benutzt. Die zurückgegebene kunde_id gibst du danach bei jedem '
           . 'weiteren Werkzeug im selben Gespräch mit — ohne sie bekommt niemand einen Stand '
           . 'und keinen Link. Kommt kein Treffer, fragst du nach Rufnummer und Erreichbarkeit '
           . 'und rufst „melde“ auf. '
           . 'Kommt „schon_einmal“ zurück, sag den Satz aus „satz“ früh im Gespräch — '
           . 'einmal, nicht mehrmals. Widerspricht er, glaub ihm und frag neu. '
           . 'Kommt „website“ zurück, ist seine Internetadresse hinterlegt: Frag ihn dann NIE '
           . 'danach, sondern ruf „seite_ansehen“ mit der kunde_id auf. '
           . 'Kommt „website_achtung“, sag das früh — es ist meist der Grund seines Anrufs.',
  'eig' => [
    'telefon'      => ['type' => 'string', 'description' => 'Rufnummer des Anrufers, wie sie hereinkommt'],
    'kundennummer' => ['type' => 'string', 'description' => 'Kunden-, Bestell- oder Angebotsnummer, falls genannt'],
    'name'         => ['type' => 'string', 'description' => 'Vor- und Nachname oder Betrieb, falls genannt'],
  ],
  'pflicht' => ['sprache'],
  'rumpf' => '{"aktion":"kunde_nachschlagen","telefon":"{{ telefon }}","kundennummer":"{{ kundennummer }}","name":"{{ name }}"}',
];

$eigA = [
  'sprache' => ['type' => 'string', 'enum' => ['it', 'de', 'en'], 'description' => 'Sprache des Gesprächs'],
  'kunde_id' => ['type' => 'integer', 'description' => 'Nur wenn vorher nachgeschlagen und gefunden'],
  'email' => ['type' => 'string', 'format' => 'email', 'description' => 'Nur bei Neukunden, buchstabieren lassen'],
  'name' => ['type' => 'string', 'description' => 'Name für die Anrede'],
];
foreach ($fragen as $f => $inf) {
    $eigA[$f] = $inf['art'] === 'mehrfach'
        ? ['type' => 'array', 'items' => ['type' => 'string', 'enum' => $inf['werte']],
           'description' => 'Mehrfach möglich. Nur eintragen, was der Anrufer wirklich gesagt hat.']
        : ['type' => 'string', 'enum' => $inf['werte'],
           'description' => 'Nur eintragen, was der Anrufer wirklich gesagt hat.'];
}
$rumpfA = '{"aktion":"angebot_link","sprache":"{{ sprache }}","kunde_id":"{{ kunde_id }}",'
        . '"email":"{{ email }}","name":"{{ name }}"';
foreach (array_keys($fragen) as $f) { $rumpfA .= ',"' . $f . '":"{{ ' . $f . ' }}"'; }
$rumpfA .= '}';

$konfigs['angebot_link'] = [
  'zweck' => 'Schickt den Konfigurator-Link. Was am Telefon schon gesagt wurde, steht beim '
           . 'Öffnen drin. Bei Bestandskunden geht der Link nur an die hinterlegte Adresse. '
           . 'Sag erst „ist raus“, NACHDEM dieses Werkzeug „ok“ zurückgegeben hat — eine Zusage, '
           . 'die du nicht eingelöst hast, ist schlimmer als gar keine.',
  'eig' => $eigA, 'pflicht' => ['sprache'], 'rumpf' => $rumpfA,
];

$konfigs['preis_auskunft'] = [
  'zweck' => 'Was kostet das? Rechnet mit derselben Maschine wie der Konfigurator und das '
           . 'Angebot — die Zahlen sind immer die aktuellen. Antwort ist eine Spanne, nie ein '
           . 'Festpreis. Wenn der Anrufer schon etwas gesagt hat, seine Spanne; sonst die übliche. '
           . 'Kommt „ausserhalb“ zurück, nennst du KEINE Zahl: Das Vorhaben passt nicht in den '
           . 'Baukasten. Dann liest du den mitgegebenen Satz vor und bietest einen Termin an.',
  'eig' => (static function () use ($fragen) {
      $e = [];
      foreach ($fragen as $f => $inf) {
          $e[$f] = $inf['art'] === 'mehrfach'
              ? ['type' => 'array', 'items' => ['type' => 'string', 'enum' => $inf['werte']],
                 'description' => 'Nur eintragen, was der Anrufer wirklich gesagt hat.']
              : ['type' => 'string', 'enum' => $inf['werte'],
                 'description' => 'Nur eintragen, was der Anrufer wirklich gesagt hat.'];
      }
      $e['vorhaben'] = ['type' => 'string', 'maxLength' => 300,
          'description' => 'Was er sich wünscht, in seinen eigenen Worten — daran wird erkannt, '
                         . 'ob es überhaupt in den Baukasten passt'];
      return $e;
  })(),
  'pflicht' => ['sprache'],
  'rumpf' => '{"aktion":"preis_auskunft","vorhaben":"{{ vorhaben }}"' . (static function () use ($fragen) {
      $r = ''; foreach (array_keys($fragen) as $f) { $r .= ',"' . $f . '":"{{ ' . $f . ' }}"'; }
      return $r;
  })() . '}',
];

$konfigs['lage'] = [
  'zweck' => 'Wie spät ist es, welcher Tag, und ist ein Rückruf heute realistisch? '
           . 'Immer aufrufen, bevor du einen Zeitpunkt zusagst — die Uhrzeit nie selbst schätzen. '
           . '„ton“ sagt dir, wie du zu dieser Tageszeit klingen solltest: morgens frisch und knapp, '
           . 'abends ruhiger und ohne lange Fragebögen, nachts nur noch aufnehmen. '
           . 'Am Wochenende tust du nicht so, als säße jemand im Büro.',
  'eig' => [], 'pflicht' => [], 'rumpf' => '{"aktion":"lage"}',
];

$konfigs['melde'] = [
  'zweck' => 'Trägt ein Anliegen in die Verwaltung ein: Rückruf, Nachricht, Beschwerde '
           . 'oder „Link noch einmal schicken“. Beschwerden gelten immer als dringend. '
           . 'Das ist der Rettungsanker: Endet ein Gespräch, ohne dass etwas rausgegangen ist, '
           . 'rufst du wenigstens das hier auf — mit Rufnummer und Erreichbarkeit. '
           . 'Ein Anrufer, von dem nichts in der Verwaltung steht, ist verloren.',
  'eig' => [
    'art' => ['type' => 'string', 'enum' => array_keys(Telefon::ARTEN),
              'description' => 'Um welche Art Anliegen es geht'],
    'prioritaet' => ['type' => 'string', 'enum' => ['normal', 'dringend'],
                     'default' => 'normal', 'description' => 'Dringend nur, wenn es wirklich eilt'],
    'kunde_id' => ['type' => 'integer', 'description' => 'Nur wenn vorher gefunden'],
    'name' => ['type' => 'string', 'description' => 'Name des Anrufers'],
    'telefon' => ['type' => 'string', 'description' => 'Rückrufnummer'],
    'erreichbar' => ['type' => 'string', 'maxLength' => 160,
                     'description' => 'Wann er am besten erreichbar ist, in seinen Worten '
                                    . '(„ab 14 Uhr“, „nur vormittags“, „nicht Dienstag“)'],
    'text' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 4000,
               'description' => 'Das Anliegen in eigenen Worten des Anrufers'],
  ],
  'pflicht' => ['art', 'text'],
  'rumpf' => '{"aktion":"melde","art":"{{ art }}","prioritaet":"{{ prioritaet }}",'
           . '"kunde_id":"{{ kunde_id }}","name":"{{ name }}","telefon":"{{ telefon }}",'
           . '"erreichbar":"{{ erreichbar }}","text":"{{ text }}"}',
];

$konfigs['wissensluecke'] = [
  'zweck' => 'Wenn du eine Frage nicht sicher beantworten kannst: hier melden, statt zu raten. '
           . 'Danach sagen „das schaue ich nach und melde mich“ — und nichts erfinden.',
  'eig' => [
    'frage' => ['type' => 'string', 'minLength' => 5, 'maxLength' => 500,
                'description' => 'Die Frage des Anrufers, möglichst wörtlich'],
    'kunde_id' => ['type' => 'integer', 'description' => 'Nur wenn vorher gefunden'],
  ],
  'pflicht' => ['frage'],
  'rumpf' => '{"aktion":"wissensluecke","frage":"{{ frage }}","kunde_id":"{{ kunde_id }}"}',
];

$konfigs['hilfe'] = [
  'zweck' => 'Wenn jemand nicht weiterkommt: Fragebogen, Bezahlung, Link weg, Entwurf, Zugang. '
           . 'Sieht nach, wo DIESER Kunde steht, und gibt die Schritte in seiner Sprache zurück. '
           . 'Die Sätze aus „schritte“ vorlesen, einen nach dem anderen — nicht zusammenfassen, '
           . 'nichts dazuerfinden. Keine Beträge und nicht sagen, ob etwas offen ist: '
           . 'Das steht auf seiner Seite, und der Link dorthin geht nur an die hinterlegte Adresse. '
           . 'Klappt es beim zweiten Mal nicht, „versuch“ auf 2 setzen — dann übernimmt ein Mensch. '
           . 'Kommt „bekannt“: false zurück, gibst du keinen Stand und keinen Link heraus, sondern '
           . 'fragst nach Rufnummer und Erreichbarkeit und rufst „melde“ auf.',
  'eig' => [
    'problem' => ['type' => 'string',
                  'enum' => ['fragebogen', 'bezahlung', 'link_weg', 'vorschau', 'zugang', 'sonstiges'],
                  'description' => 'Woran es hakt. Im Zweifel „sonstiges“ — eine falsche Kategorie '
                                 . 'führt zu einer Anleitung für ein Problem, das er nicht hat'],
    'kunde_id' => ['type' => 'integer', 'description' => 'Nur wenn vorher gefunden'],
    'telefon'  => ['type' => 'string', 'description' => 'Rufnummer, falls noch nicht nachgeschlagen'],
    'versuch'  => ['type' => 'integer',
                   'description' => '0 beim ersten Anlauf, 1 beim zweiten, 2 wenn es wieder nicht ging'],
    'text'     => ['type' => 'string', 'maxLength' => 500,
                   'description' => 'Was genau nicht geht, in seinen Worten — nur beim Aufgeben nötig'],
  ],
  'pflicht' => ['problem'],
  'rumpf' => '{"aktion":"hilfe","problem":"{{ problem }}","kunde_id":"{{ kunde_id }}",'
           . '"telefon":"{{ telefon }}","versuch":"{{ versuch }}","text":"{{ text }}"}',
];

$konfigs['zusammenfassung'] = [
  'zweck' => 'Schickt dem Anrufer, was besprochen wurde. Nur nach ausdrücklicher Zustimmung.',
  'eig' => [
    'zustimmung' => ['type' => 'boolean', 'description' => 'Hat der Anrufer ausdrücklich zugestimmt?'],
    'sprache' => ['type' => 'string', 'enum' => ['it', 'de', 'en']],
    'kunde_id' => ['type' => 'integer', 'description' => 'Nur wenn vorher gefunden'],
    'email' => ['type' => 'string', 'format' => 'email', 'description' => 'Nur bei Neukunden'],
    'text' => ['type' => 'string', 'minLength' => 20, 'maxLength' => 6000,
               'description' => 'Die Zusammenfassung in der Sprache des Gesprächs'],
  ],
  'pflicht' => ['zustimmung', 'text'],
  'rumpf' => '{"aktion":"zusammenfassung","zustimmung":"{{ zustimmung }}","sprache":"{{ sprache }}",'
           . '"kunde_id":"{{ kunde_id }}","email":"{{ email }}","text":"{{ text }}"}',
];

/* ---------- Die sechs neuen: Beratung statt Auskunft ---------- */

$konfigs['seite_ansehen'] = [
  'zweck' => 'Sieh dir die Website wirklich an, während er redet — es gibt zwei Wege, und du '
           . 'wählst nicht, sondern gibst mit, was du hast. '
           . 'IST ER BESTANDSKUNDE (du hast eine „kunde_id“ aus „kunde_nachschlagen“): gib sie mit '
           . 'und frag NICHT nach der Adresse — sie steht in seiner Akte und wird von dort '
           . 'genommen. Kommt „quelle“: „verwaltung“ zurück, sag ihm die Adresse zur Bestätigung '
           . '(„Ihre Seite … , richtig?“), statt sie dir buchstabieren zu lassen. Steht in '
           . '„aus_verwaltung“ ein Satz, ist das das Wichtigste im ganzen Gespräch — sag ihn früh. '
           . 'IST ER KEIN KUNDE: gib die Adresse mit, so wie er sie genannt hat, auch ohne '
           . '„www“ und auch ohne Endung. Sie wird wirklich recherchiert — mit und ohne www, über '
           . 'https und http, mit anderen Endungen und anderen Schreibweisen —, und die wichtigsten '
           . 'Unterseiten werden mitgelesen. „Finde ich nicht“ kommt nur, wenn es die Adresse '
           . 'wirklich nirgends gibt. '
           . 'Sprich dann GENAU in dieser Reihenfolge, was in „gespraech“ steht: auftakt, befund, '
           . 'folge, frage — und sei danach still. Einen zweiten Befund nur, wenn er nachfragt. '
           . 'Lies nie die ganze Liste vor: Eine Mängelliste am Telefon macht keinen Kunden, '
           . 'sie macht jemanden, der sich schlecht fühlt. '
           . 'Sage nie etwas über Aussehen oder Gestaltung — geprüft wird nur, was messbar ist —, '
           . 'und erfinde keine Zahlen, keine Mitbewerber und keine Eile. '
           . 'Kommt „nichts_gefunden“, sag das ehrlich und verkaufe nichts. '
           . 'Kommt „andere_adresse“, sag zuerst, unter welcher Adresse du sie gefunden hast. '
           . 'RATE NIE. Wird sie nicht gefunden, lass buchstabieren und versuche es genau noch '
           . 'einmal — danach nicht mehr, sondern „melde“.',
  'eig' => [
    'adresse'  => ['type' => 'string', 'minLength' => 3, 'maxLength' => 200,
                   'description' => 'Die Internetadresse, wie er sie nennt — auch ohne Endung. '
                                  . 'Bei einem Bestandskunden mit „kunde_id“ leer lassen'],
    'sprache'  => ['type' => 'string', 'enum' => ['it', 'de', 'en'], 'description' => 'Sprache des Gesprächs'],
    'branche'  => ['type' => 'string',
                   'enum' => array_map('strval', array_keys(Baukasten::FRAGEN['branche']['optionen'] ?? [])),
                   'description' => 'Betriebsart, falls genannt — dann wird auch geprüft, was gerade '
                                  . 'diese Branche braucht (Speisekarte, Buchung, Arbeitsproben)'],
    'kunde_id' => ['type' => 'integer', 'description' => 'Aus „kunde_nachschlagen“. Immer mitgeben, '
                                  . 'wenn vorhanden — dann kommt die Adresse aus der Verwaltung'],
    'telefon'  => ['type' => 'string', 'maxLength' => 40,
                   'description' => 'Die Rufnummer des Anrufers, dieselbe wie bei '
                                  . '„kunde_nachschlagen“ — daran wird erkannt, dass mehrere '
                                  . 'Versuche zum selben Gespräch gehören'],
  ],
  'pflicht' => ['sprache'],
  'rumpf' => '{"aktion":"seite_ansehen","adresse":"{{ adresse }}","sprache":"{{ sprache }}",'
           . '"branche":"{{ branche }}","kunde_id":"{{ kunde_id }}","telefon":"{{ telefon }}"}',
];

$konfigs['beratung'] = [
  'zweck' => 'Der Konfigurator als Gespräch. Ruf ihn auf, sobald jemand eine neue Website will. '
           . 'Er gibt dir in „satz“ die nächste Frage — stelle genau diese. Die Antwort trägst du '
           . 'beim nächsten Aufruf als „antwort“ ein, zusammen mit „antwort_auf“ und dem '
           . '„gespraech“ aus der letzten Antwort. Nimm als Antwort nur einen Schlüssel aus '
           . '„optionen“; bei Mehrfachfragen mehrere mit Komma. Sobald „von_euro“ kommt, darfst du '
           . 'die Spanne nennen — immer als Spanne, nie als Festpreis. '
           . 'Kommt „ausserhalb“ zurück, nennst du KEINE Zahl: Das Vorhaben passt nicht in '
           . 'den Baukasten, du liest den Satz vor und bietest einen Termin an. '
           . 'Höchstens ein Werkzeug pro Gesprächszug — zwei hintereinander machen eine Pause, '
           . 'die der Anrufer als Stille hört. '
           . 'Kommt „abbrechen“ zurück, hörst du auf zu fragen: Er antwortet nicht mehr richtig. '
           . 'Dann sagst du, dass der Rest schriftlich schneller geht, fragst nach der '
           . 'E-Mail-Adresse und rufst „uebergabe“ auf — kein „nur noch eine Frage“.',
  'eig' => [
    'gespraech'   => ['type' => 'string', 'maxLength' => 48,
                      'description' => 'Der Wert aus der letzten Antwort. Beim ersten Aufruf leer lassen'],
    'sprache'     => ['type' => 'string', 'enum' => ['it', 'de', 'en'], 'description' => 'Sprache des Gesprächs'],
    'antwort_auf' => ['type' => 'string', 'enum' => Telefon::BERATUNG_REIHE,
                      'description' => 'Auf welche Frage sich die Antwort bezieht — das Feld aus „frage_zu“'],
    'antwort'     => ['type' => 'string', 'maxLength' => 200,
                      'description' => 'Ein Schlüssel aus „optionen“. Mehrere mit Komma, wenn die Frage '
                                     . 'mehrfach ist. Nichts anderes — Freitext wird verworfen'],
    'vorhaben'    => ['type' => 'string', 'maxLength' => 300,
                      'description' => 'Was er sich wünscht, in seinen eigenen Worten — beim ERSTEN '
                                     . 'Aufruf mitgeben. Daran wird erkannt, ob es überhaupt in den '
                                     . 'Baukasten passt'],
    'kunde_id'    => ['type' => 'integer', 'description' => 'Nur wenn vorher gefunden'],
  ],
  'pflicht' => ['sprache'],
  'rumpf' => '{"aktion":"beratung","gespraech":"{{ gespraech }}","sprache":"{{ sprache }}",'
           . '"antwort_auf":"{{ antwort_auf }}","antwort":"{{ antwort }}",'
           . '"vorhaben":"{{ vorhaben }}","kunde_id":"{{ kunde_id }}"}',
];

$konfigs['beleg'] = [
  'zweck' => 'Eine echte Kundenstimme statt eines Werbesatzes. Nenne höchstens eine, sinngemäß, '
           . 'mit dem Betrieb dazu. Kommt keine zurück, erfinde keine — sag stattdessen, '
           . 'dass Uwe Beispiele schickt.',
  'eig' => [
    'branche' => ['type' => 'string',
                  'enum' => array_map('strval', array_keys(Baukasten::FRAGEN['branche']['optionen'] ?? [])),
                  'description' => 'Betriebsart des Anrufers, falls genannt'],
    'sprache' => ['type' => 'string', 'enum' => ['it', 'de', 'en']],
  ],
  'pflicht' => ['sprache'],
  'rumpf' => '{"aktion":"beleg","branche":"{{ branche }}","sprache":"{{ sprache }}"}',
];

$konfigs['uebergabe'] = [
  'zweck' => 'Nach dem Gespräch: schickt schriftlich, worüber gesprochen wurde, die Spanne, '
           . 'einen Befund von seiner Seite und den halb ausgefüllten Fragebogen. Ruf sie auf, '
           . 'wenn die Beratung durch ist oder das Gespräch endet. Nenne danach keine Frist '
           . 'und sag nicht „melden Sie sich bald“. '
           . 'WICHTIG: Sag erst „ist raus“, NACHDEM dieses Werkzeug „ok“ zurückgegeben hat. '
           . 'Eine Zusage, die du nicht eingelöst hast, ist schlimmer als gar keine.',
  'eig' => [
    'gespraech' => ['type' => 'string', 'maxLength' => 48,
                    'description' => 'Der Wert aus „beratung“, damit der Fragebogen vorausgefüllt ist'],
    'sprache'   => ['type' => 'string', 'enum' => ['it', 'de', 'en']],
    'kunde_id'  => ['type' => 'integer', 'description' => 'Nur wenn vorher gefunden'],
    'email'     => ['type' => 'string', 'format' => 'email',
                    'description' => 'Nur bei Neukunden, buchstabieren lassen'],
    'name'      => ['type' => 'string', 'description' => 'Name für die Anrede'],
    'von_euro'  => ['type' => 'integer', 'description' => 'Untere Grenze aus „beratung“, falls genannt'],
    'bis_euro'  => ['type' => 'integer', 'description' => 'Obere Grenze aus „beratung“, falls genannt'],
    'befund'    => ['type' => 'string', 'maxLength' => 400,
                    'description' => 'Ein Satz aus „seite_ansehen“, wörtlich — sonst leer lassen'],
  ],
  'pflicht' => ['sprache'],
  'rumpf' => '{"aktion":"uebergabe","gespraech":"{{ gespraech }}","sprache":"{{ sprache }}",'
           . '"kunde_id":"{{ kunde_id }}","email":"{{ email }}","name":"{{ name }}",'
           . '"von_euro":"{{ von_euro }}","bis_euro":"{{ bis_euro }}","befund":"{{ befund }}"}',
];

$konfigs['wissen'] = [
  'zweck' => 'Pakete, Bausteine und Preise, wie sie in dieser Sekunde in der Verwaltung stehen. '
           . 'Immer hier nachsehen, statt eine Zahl aus dem Gedächtnis zu nennen. '
           . 'Diese Zahlen sind Einzelpreise — der Preis eines Projekts ist eine Spanne '
           . 'und kommt aus „beratung“.',
  'eig' => ['sprache' => ['type' => 'string', 'enum' => ['it', 'de', 'en']]],
  'pflicht' => ['sprache'],
  'rumpf' => '{"aktion":"wissen","sprache":"{{ sprache }}"}',
];

$konfigs['termin'] = [
  'zweck' => 'Ein fester Termin statt „er meldet sich“. Ohne „wann“ bekommst du freie Plätze — '
           . 'nenne höchstens drei davon, nicht die ganze Liste. Sagt er einen zu, ruf noch '
           . 'einmal auf und gib ihn in „wann“ genau so mit, wie er in der Liste stand.',
  'eig' => [
    'wann'     => ['type' => 'string', 'maxLength' => 16,
                   'description' => 'Ein Platz aus „frei“, wörtlich, z. B. 2026-09-08 15:00. '
                                  . 'Leer lassen, um die freien Plätze zu erfragen'],
    'kunde_id' => ['type' => 'integer', 'description' => 'Nur wenn vorher gefunden'],
    'name'     => ['type' => 'string', 'description' => 'Name des Anrufers'],
    'telefon'  => ['type' => 'string', 'description' => 'Rufnummer für den Anruf'],
    'anliegen' => ['type' => 'string', 'maxLength' => 500,
                   'description' => 'Worum es gehen soll, in seinen Worten'],
    'sprache'  => ['type' => 'string', 'enum' => ['it', 'de', 'en']],
  ],
  'pflicht' => ['sprache'],
  'rumpf' => '{"aktion":"termin","wann":"{{ wann }}","kunde_id":"{{ kunde_id }}",'
           . '"name":"{{ name }}","telefon":"{{ telefon }}","anliegen":"{{ anliegen }}",'
           . '"sprache":"{{ sprache }}"}',
];
?>

<div class="block">
  <h2>Die <?= count($konfigs) ?> Konfigurationen für STRATO</h2>
  <p style="color:var(--leise);font-size:12.5px;margin:-4px 0 14px">
    Bei STRATO unter <b>API-Integration → neu anlegen</b>. Für jede einzelne: den Block
    kopieren und einfügen. Der Schlüssel steht schon drin.
    <br>Die Antwortmöglichkeiten (<code>enum</code>) sind kein Beiwerk: Der Konfigurator nimmt
    nur seine eigenen Schlüsselwörter an. Was Manuela frei formuliert, wird verworfen — die
    Frage bleibt dann offen, statt falsch beantwortet zu werden.</p>

  <?php foreach ($konfigs as $name => $k):
    /* Gebaut wird in Telefon::konfigJson() -- dort steht auch, warum
       „properties“ ausdruecklich ein Objekt sein muss. */
    $text = Telefon::konfigJson($name, $k, $basis . '/telefon.php', (string) $schluessel);
  ?>
    <div style="margin-bottom:18px">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
        <b style="font-size:14px"><?= Fmt::h($name) ?></b>
        <span class="marke2"><?= count($k['pflicht']) ?> Pflichtfeld<?= count($k['pflicht']) === 1 ? '' : 'er' ?></span>
        <button class="knopf" type="button" data-kopieren="k_<?= Fmt::h($name) ?>">Kopieren</button>
      </div>
      <textarea id="k_<?= Fmt::h($name) ?>" readonly rows="8"
        style="width:100%;font-family:ui-monospace,monospace;font-size:11.5px;line-height:1.45"
      ><?= Fmt::h($text) ?></textarea>
    </div>
  <?php endforeach; ?>
</div>
