<?php
/**
 * Der Telefonassistent — alles zum Einrichten an einer Stelle.
 *
 * STRATO konfiguriert die API-Integration ueber HAR-artiges JSON. Diese Seite
 * erzeugt es fertig: Wer hier kopiert und drueben einfuegt, muss nichts
 * verstehen. Das ist der Sinn — die Anleitung des Anbieters richtet sich an
 * einen Entwickler, und einer sitzt hier nicht.
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

<div class="kopf"><div><h1>Telefonassistent</h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px">
    <?php if ($anzahl > 0): ?>
      <?= (int) $anzahl ?> <?= $anzahl === 1 ? 'Aufruf' : 'Aufrufe' ?> in den letzten 30 Tagen.
    <?php else: ?>
      Noch kein Aufruf. Trag die Konfigurationen unten bei STRATO ein.
    <?php endif; ?>
  </p></div>
  <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-right:8px">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="telefon_modus">
    <input type="hidden" name="zurueck" value="telefon">
    <select name="modus" onchange="this.form.submit()" style="min-width:150px">
      <?php foreach (Telefon::MODI as $wert => $wort): ?>
        <option value="<?= Fmt::h($wert) ?>" <?= $modus === $wert ? 'selected' : '' ?>><?= Fmt::h($wort) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <form method="post" action="<?= Fmt::h(url('')) ?>"
        data-frage="Der alte Schlüssel wird damit ungültig — STRATO ruft danach ins Leere, bis du den neuen dort einträgst. Fortfahren?"
        data-ja="Ja, neuen Schlüssel erzeugen">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="telefon_schluessel_neu">
    <input type="hidden" name="zurueck" value="telefon">
    <button class="knopf">Neuen Schlüssel erzeugen</button></form>
</div>

<?php /* ---------- Heute anrufen ---------- */ ?>
<?php if (!empty($rueckrufe)):
  $dringend = array_filter($rueckrufe, static fn(array $r): bool => $r['dringend']);
  $alt      = array_filter($rueckrufe, static fn(array $r): bool => $r['ueberfaellig']);
?>
<div class="block" style="border-color:<?= $alt ? 'var(--rot)' : 'var(--cyan)' ?>">
  <h2>Heute anrufen <span class="marke2"><?= count($rueckrufe) ?></span></h2>
  <p style="color:var(--leise);font-size:12.5px;margin:-4px 0 12px">
    Das Einzige auf dieser Seite, was du persönlich tun musst. Oben steht, was
    dringend ist — das hat der Anrufer selbst gesagt und schlägt jede Rechnung.
    Darunter sortiert, wie weit das Gespräch schon war: wer sechs Fragen beantwortet
    und einen Termin genommen hat, steht über dem, der „rufen Sie mal an“ gesagt hat.
    Bei gleichem Stand zuerst das Älteste — wer lange wartet, hat am ehesten schon
    aufgegeben. Der Grund steht immer daneben; die Zahl entscheidet nichts, sie sortiert.
    <?php if ($alt): ?><br><b>Rot heißt: liegt seit mehr als einem Tag.</b><?php endif; ?>
  </p>
  <table class="tab"><tbody>
    <?php foreach ($rueckrufe as $r): ?>
      <tr<?= $r['ueberfaellig'] ? ' style="background:rgba(255,90,90,.06)"' : '' ?>>
        <td style="white-space:nowrap;vertical-align:top;width:1%">
          <?php if ($r['dringend']): ?><span class="marke2 schlecht">dringend</span><br><?php endif; ?>
          <span style="color:var(--leise);font-size:12px">
            <?= $r['stunden'] < 24
                  ? 'vor ' . (int) $r['stunden'] . ' h'
                  : 'seit ' . (int) round($r['stunden'] / 24) . ' Tag' . (round($r['stunden'] / 24) == 1 ? '' : 'en') ?>
          </span>
        </td>
        <td style="vertical-align:top">
          <b><?php if ($r['kunde_id'] > 0): ?>
            <a href="<?= Fmt::h(url('kunden/' . (int) $r['kunde_id'])) ?>"><?= Fmt::h($r['wer']) ?></a>
          <?php else: ?><?= Fmt::h($r['wer']) ?><?php endif; ?></b>
          <?php if ($r['anliegen'] !== ''): ?>
            <div style="color:var(--leise);font-size:12.5px;margin-top:3px"><?= Fmt::h($r['anliegen']) ?></div>
          <?php endif; ?>
          <?php /* Der Grund steht neben der Zahl. Eine Bewertung ohne
                    Begruendung ist eine Behauptung ueber einen Menschen,
                    und die stellt hier keine Software auf. */ ?>
          <?php if (!empty($r['gruende'])): ?>
            <div style="color:var(--leise);font-size:12px;margin-top:4px">
              <?= Fmt::h(implode(' · ', $r['gruende'])) ?>
            </div>
          <?php endif; ?>
        </td>
        <td style="vertical-align:top;white-space:nowrap">
          <?php if ($r['nummer'] !== ''): ?>
            <a href="tel:<?= Fmt::h(preg_replace('/[^0-9+]/', '', $r['nummer']) ?? '') ?>"
               style="font-weight:600"><?= Fmt::h($r['nummer']) ?></a>
          <?php else: ?>
            <span style="color:var(--leise)">keine Nummer</span>
          <?php endif; ?>
          <?php if ($r['erreichbar'] !== ''): ?>
            <div style="color:var(--leise);font-size:12.5px;margin-top:3px">
              erreichbar: <?= Fmt::h($r['erreichbar']) ?></div>
          <?php else: ?>
            <div style="color:var(--leise);font-size:12.5px;margin-top:3px">kein Zeitfenster genannt</div>
          <?php endif; ?>
        </td>
        <td style="text-align:right;vertical-align:top;white-space:nowrap">
          <form method="post" action="<?= Fmt::h(url('')) ?>">
            <?= Csrf::feld() ?>
            <input type="hidden" name="tat" value="telefon_rueckruf_weg">
            <input type="hidden" name="eintrag" value="<?= (int) $r['id'] ?>">
            <input type="hidden" name="zurueck" value="telefon">
            <button class="knopf">Erledigt</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody></table>
</div>
<?php endif; ?>

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
  <div class="feld"><label>Schlüssel (Kopfzeile <code>X-Vecom-Telefon</code>)</label>
    <input readonly value="<?= Fmt::h((string) $schluessel) ?>" onclick="this.select()"></div>
  <p style="color:var(--leise);font-size:12.5px">
    Er gehört nicht in eine E-Mail und nicht in einen Chat. Kopieren, drüben einfügen, fertig.</p>
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
           . 'und rufst „melde“ auf.',
  'eig' => [
    'telefon'      => ['type' => 'string', 'description' => 'Rufnummer des Anrufers, wie sie hereinkommt'],
    'kundennummer' => ['type' => 'string', 'description' => 'Kunden-, Bestell- oder Angebotsnummer, falls genannt'],
    'name'         => ['type' => 'string', 'description' => 'Vor- und Nachname oder Betrieb, falls genannt'],
  ],
  'pflicht' => [],
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
  'pflicht' => [],
  'rumpf' => '{"aktion":"preis_auskunft","vorhaben":"{{ vorhaben }}"' . (static function () use ($fragen) {
      $r = ''; foreach (array_keys($fragen) as $f) { $r .= ',"' . $f . '":"{{ ' . $f . ' }}"'; }
      return $r;
  })() . '}',
];

$konfigs['lage'] = [
  'zweck' => 'Wie spät ist es, welcher Tag, und ist ein Rückruf heute realistisch? '
           . 'Immer aufrufen, bevor du einen Zeitpunkt zusagst — die Uhrzeit nie selbst schätzen.',
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
  'zweck' => 'Sieh dir die Website des Anrufers an, während er redet. Gibt zwei bis drei '
           . 'nachprüfbare Befunde als fertige Sätze zurück — nenne höchstens zwei davon. '
           . 'Sage nie etwas über Aussehen oder Gestaltung: geprüft wird nur Technik. '
           . 'Kommt „nichts_gefunden“, sag das ehrlich und verkaufe nichts. '
           . 'RATE NIE eine Adresse. Wird sie nicht gefunden, lass sie Buchstabe für Buchstabe '
           . 'nennen und versuche es genau noch einmal — danach nicht mehr, sondern „melde“.',
  'eig' => [
    'adresse'  => ['type' => 'string', 'minLength' => 4, 'maxLength' => 200,
                   'description' => 'Die Internetadresse, wie er sie nennt — buchstabieren lassen'],
    'sprache'  => ['type' => 'string', 'enum' => ['it', 'de', 'en'], 'description' => 'Sprache des Gesprächs'],
    'kunde_id' => ['type' => 'integer', 'description' => 'Nur wenn vorher gefunden'],
  ],
  'pflicht' => ['adresse'],
  'rumpf' => '{"aktion":"seite_ansehen","adresse":"{{ adresse }}","sprache":"{{ sprache }}",'
           . '"kunde_id":"{{ kunde_id }}"}',
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
           . 'die der Anrufer als Stille hört.',
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
  'pflicht' => [],
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
  'pflicht' => [],
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
  'pflicht' => [],
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

<?php /* ---------- Der Trichter ----------
         Ohne Zahlen weisst du in drei Monaten nicht, ob der Tarif sich traegt.
         Bewusst ueber 90 Tage: Bei ein paar Anrufen im Monat sagt eine
         Wochenzahl nichts. */ ?>
<?php $t = (array) ($trichter ?? []); ?>
<?php if ($t): ?>
<div class="block">
  <h2>Trägt es sich? <span class="mehr" style="font-weight:400;color:var(--leise)">letzte 90 Tage</span></h2>
  <p style="color:var(--leise);font-size:12.5px;margin:-4px 0 14px">
    Jede Zeile zählt <b>Gespräche</b>, nicht Dinge: von so vielen Anrufen ging ein
    Link raus, aus so vielen wurde ein ausgefüllter Bedarf, daraus eine Anfrage,
    daraus eine Bestellung. Interessant ist, <b>wo</b> es abreißt. Bleiben Anrufe
    ohne verschickten Link, fehlt Manuela das Argument; kommen Links ohne
    ausgefüllten Bedarf, ist der Weg zu lang.
    <br>Was nicht am Telefon angefangen hat, steht hier bewusst nicht drin — sonst
    wären es große Zahlen, die niemandem gehören. Ein Gespräch ist dabei eine
    Minute: Nachfragen in derselben Minute gehören zum selben Anruf. Eine
    Näherung, und deshalb steht sie hier.</p>
  <div class="trichter">
    <?php
      $stufen = [
        ['Anrufe', (int) ($t['anrufe'] ?? 0)],
        ['davon Link verschickt', (int) ($t['links'] ?? 0)],
        ['davon Bedarf ausgefüllt', (int) ($t['bedarf'] ?? 0)],
        ['davon Anfrage', (int) ($t['anfragen'] ?? 0)],
        ['davon Bestellung', (int) ($t['bestellungen'] ?? 0)],
      ];
      $groesste = max(1, ...array_column($stufen, 1));
      $vorher = null;
    ?>
    <?php foreach ($stufen as [$wort, $zahl]): ?>
      <div class="trichter__stufe">
        <div class="trichter__zahl"><?= (int) $zahl ?></div>
        <div class="trichter__balken"><span style="width:<?= max(2, (int) round($zahl / $groesste * 100)) ?>%"></span></div>
        <div class="trichter__wort"><?= Fmt::h($wort) ?><?php
          if ($vorher !== null && $vorher > 0): ?><i> · <?= (int) round($zahl / $vorher * 100) ?> %</i><?php
          endif; ?></div>
      </div>
    <?php $vorher = $zahl; endforeach; ?>
  </div>
  <?php if ((int) ($t['anrufe'] ?? 0) === 0): ?>
    <p style="color:var(--leise);font-size:12.5px;margin-top:10px">
      Noch kein Anruf. Die Zahlen füllen sich, sobald STRATO die Aktionen ruft.</p>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php /* ---------- Kommt die Anrufernummer an? ---------- */ ?>
<?php if (!empty($cli)): ?>
<div class="block">
  <h2>Kommt die Anrufernummer an?</h2>
  <p style="color:var(--leise);font-size:12.5px;margin:-4px 0 12px">
    Davon hängt ab, ob Manuela Bestandskunden am Telefon erkennt oder ob jeder erst
    Namen und Kundennummer buchstabieren muss. Zwischen dem Anrufer und der Verwaltung
    liegen zwei fremde Systeme — die Weiterleitung beim Telefonanbieter und STRATO.
    Ob die Nummer die Strecke überlebt, steht nirgends vollständig geschrieben.
    Also wird es hier abgelesen statt behauptet.</p>
  <?php if ($cli['kommt_an'] === true): ?>
    <p class="hinweis gut">Ja — bei <?= (int) $cli['mit'] ?> von
      <?= (int) ($cli['mit'] + $cli['ohne']) ?> Nachschlage-Aufrufen kam eine Rufnummer mit.
      Die Kundenerkennung funktioniert.</p>
  <?php elseif ($cli['kommt_an'] === false): ?>
    <p class="hinweis schlecht">Nein — bei <?= (int) $cli['ohne'] ?> Aufrufen kam keine
      Rufnummer mit. Prüfe beim Telefonanbieter die Einstellung <b>„Show"</b>: Sie muss auf
      <b>Caller’s number</b> stehen, nicht auf <em>Called number</em>. Bis dahin fragt
      Manuela nach Namen oder Kundennummer — das ist gebaut und funktioniert.</p>
  <?php else: ?>
    <p class="hinweis" style="border-color:var(--linie);color:var(--dim)">Noch nicht entschieden:
      <?= (int) $cli['mit'] ?> mit Nummer, <?= (int) $cli['ohne'] ?> ohne. Ein einzelner
      Anrufer kann seine Nummer auch selbst unterdrückt haben — nach ein paar Anrufen steht
      es fest.</p>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php /* ---------- Gespräche ohne Ergebnis ----------
   Steht direkt unter „Heute anrufen", weil es dieselbe Art Arbeit ist: Hier
   hat jemand angerufen, sie hat gearbeitet — nachgesehen, beraten, geholfen —
   und am Ende ist nichts herausgegangen. Kein Link, kein Rückruf, nichts.
   Von außen sieht das nicht nach Technik aus, sondern nach jemandem, der
   seine Zusagen nicht hält. Deshalb wird es nicht gemeldet, sondern
   hingestellt. */ ?>
<?php $off = (array) ($offen ?? []); ?>
<?php if ($off): ?>
<div class="block" style="border-color:var(--rot)">
  <h2>Angefangen und nichts daraus geworden <span class="marke2"><?= count($off) ?></span></h2>
  <p style="color:var(--leise);font-size:12.5px;margin:-4px 0 12px">
    In diesen Gesprächen hat Manuela gearbeitet, aber nichts verschickt und keinen
    Rückruf angelegt. Entweder hat der Anrufer aufgelegt — oder sie hat etwas zugesagt
    und nicht eingelöst. Beides ist einen Anruf wert, solange es frisch ist.</p>
  <table class="tab">
    <thead><tr><th>WANN</th><th>WER</th><th>WAS SCHON DA WAR</th></tr></thead>
    <tbody>
      <?php foreach ($off as $o): ?>
        <tr>
          <td style="white-space:nowrap;vertical-align:top">
            <?= Fmt::h(Fmt::zeit((string) $o['wann'])) ?>
            <div style="color:var(--leise);font-size:12px">
              <?= (int) $o['stunden'] < 24
                    ? 'vor ' . (int) $o['stunden'] . ' h'
                    : 'vor ' . (int) round(((int) $o['stunden']) / 24) . ' Tagen' ?></div>
          </td>
          <td style="vertical-align:top">
            <?php if ((int) $o['kunde_id'] > 0): ?>
              <a href="<?= Fmt::h(url('kunden/' . (int) $o['kunde_id'])) ?>"><?= Fmt::h((string) $o['wer']) ?></a>
            <?php else: ?><?= Fmt::h((string) $o['wer']) ?><?php endif; ?>
          </td>
          <td style="vertical-align:top;color:var(--leise);font-size:12.5px">
            <?php if ((string) $o['seite'] !== ''): ?>
              Seite angesehen: <b><?= Fmt::h((string) $o['seite']) ?></b><br>
            <?php endif; ?>
            <?php if (!empty($o['gefragt'])): ?>
              Beratung bis: <?= Fmt::h(implode(', ', array_map('strval', (array) $o['gefragt']))) ?><br>
            <?php endif; ?>
            <?= (int) $o['schritte'] ?> Schritt<?= (int) $o['schritte'] === 1 ? '' : 'e' ?> im Gespräch
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php /* ---------- Was sie sich angewöhnt hat ----------
   Warum das hier steht und nicht nur in einer Meldung: Eine Meldung klickt
   man weg. Ein Assistent driftet aber nicht an einem Tag, sondern über
   Wochen — und was man dagegen tun kann, muss dort stehen, wo man ohnehin
   nachsieht. Geändert wird nichts von allein: unten stehen Sätze zum
   Eintragen, eintragen muss sie ein Mensch. */ ?>
<?php $rb = $rueckblick ?? null; ?>
<?php if (is_array($rb) && !empty($rb['befunde'])): ?>
<div class="block" style="border-color:var(--gelb,#e0b400)">
  <h2>Was sie sich angewöhnt hat
    <span class="mehr" style="font-weight:400;color:var(--leise)">
      <?= (int) ($rb['gespraeche'] ?? 0) ?> Gespräche der letzten <?= (int) ($rb['tage'] ?? 7) ?> Tage</span></h2>
  <p style="color:var(--leise);font-size:12.5px;margin:-4px 0 12px">
    Jedes einzelne Gespräch sieht in Ordnung aus — sichtbar wird es erst im Muster.
    Rechts steht der Satz, der es abstellt: bei STRATO unter
    <b>Sprache, Stimme &amp; Verhalten → Verhalten im Telefonat</b> ergänzen.</p>
  <table class="tab">
    <thead><tr><th>WAS AUFFÄLLT</th><th>WAS DAGEGEN HILFT</th></tr></thead>
    <tbody>
      <?php foreach ($rb['befunde'] as $b): ?>
        <tr>
          <td style="vertical-align:top"><?= Fmt::h((string) ($b['satz'] ?? '')) ?></td>
          <td style="vertical-align:top;color:var(--leise)"><?= Fmt::h((string) ($b['vorschlag'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php if (!empty($rb['stand'])): ?>
    <p style="color:var(--leise);font-size:12px;margin:10px 0 0">
      Stand: <?= Fmt::h(Fmt::zeit((string) $rb['stand'])) ?></p>
  <?php endif; ?>
</div>
<?php elseif (is_array($rb)): ?>
<div class="block">
  <h2>Was sie sich angewöhnt hat</h2>
  <p style="color:var(--leise);font-size:12.5px;margin:-4px 0 0">
    Nichts aufgefallen in <?= (int) ($rb['gespraeche'] ?? 0) ?> Gesprächen der letzten
    <?= (int) ($rb['tage'] ?? 7) ?> Tage. Der Rückblick läuft einmal die Woche von selbst.</p>
</div>
<?php endif; ?>

<?php /* ---------- Woran es hakt ---------- */ ?>
<?php if (!empty($haken)): ?>
<div class="block">
  <h2>Woran es hakt <span class="mehr" style="font-weight:400;color:var(--leise)">letzte 90 Tage</span></h2>
  <p style="color:var(--leise);font-size:12.5px;margin:-4px 0 12px">
    Jede Zeile ist jemand, der angerufen hat, weil er nicht weiterkam. Zwanzig Anrufe
    zum Fragebogen sind kein Support-Fall, sondern ein Produktfehler — dann ist nicht
    der Assistent zu verbessern, sondern der Fragebogen.
    <br><b>Geholfen</b> heißt: Der Assistent hat wirklich etwas verschickt. Ob es dem
    Anrufer danach reichte, wissen wir nicht — das steht hier bewusst nicht.</p>
  <table class="tab">
    <thead><tr><th>Woran</th><th style="text-align:right">Anrufe</th>
               <th style="text-align:right">davon geholfen</th></tr></thead>
    <tbody>
    <?php
      $worte = ['fragebogen' => 'Fragebogen', 'bezahlung' => 'Bezahlung',
                'link_weg' => 'Link weg', 'vorschau' => 'Entwurf',
                'zugang' => 'Zugang zur eigenen Seite', 'sonstiges' => 'Sonstiges'];
    ?>
    <?php foreach ($haken as $h): ?>
      <tr>
        <td><?= Fmt::h($worte[$h['problem']] ?? $h['problem']) ?></td>
        <td style="text-align:right"><?= (int) $h['anzahl'] ?></td>
        <td style="text-align:right;color:var(--leise)"><?= (int) $h['geloest'] ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php /* ---------- Wissenslücken ---------- */ ?>
<?php if (!empty($luecken)): ?>
<div class="block" style="border-color:var(--cyan)">
  <h2>Was Manuela nicht wusste <span class="marke2"><?= count($luecken) ?></span></h2>
  <p style="color:var(--leise);font-size:12.5px;margin:-4px 0 12px">
    Die wertvollste Liste dieser Seite. Jede Zeile ist eine Frage, die ein echter
    Anrufer gestellt hat und auf die es keine Antwort gab. Beantworte sie in der
    Wissensbasis bei STRATO — dann verschwindet sie hier.</p>
  <table class="tab"><tbody>
    <?php foreach ($luecken as $l): ?>
      <tr>
        <td style="white-space:nowrap;color:var(--leise);font-size:12.5px"><?=
          Fmt::h(Fmt::zeit((string) ($l['wann'] ?? ''))) ?></td>
        <td><?= Fmt::h((string) ($l['frage'] ?? '')) ?></td>
        <td style="text-align:right">
          <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0">
            <?= Csrf::feld() ?><input type="hidden" name="tat" value="telefon_luecke_weg">
            <input type="hidden" name="zurueck" value="telefon">
            <input type="hidden" name="schluessel" value="<?= Fmt::h((string) ($l['schluessel'] ?? '')) ?>">
            <button class="knopf">Erledigt</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody></table>
</div>
<?php endif; ?>

<div class="block">
  <h2>Was der Assistent getan hat</h2>
  <p style="color:var(--leise);font-size:12.5px;margin:-4px 0 12px">
    Jeder Aufruf steht hier. Ein Assistent, der unbeobachtet in die Verwaltung schreibt,
    ist so viel wert wie das Vertrauen, das man ihm entgegenbringt — und das hält nur,
    solange man nachsehen kann.</p>
  <?php if (!$verlauf): ?>
    <p style="color:var(--leise);font-size:13px">Noch nichts.</p>
  <?php else: ?>
    <table class="tab"><thead><tr><th>Wann</th><th>Was</th><th>Kunde</th></tr></thead><tbody>
      <?php foreach ($verlauf as $z): ?>
        <tr>
          <td style="white-space:nowrap;color:var(--leise)"><?= Fmt::h(Fmt::zeit((string) $z['created_at'])) ?></td>
          <td><?= Fmt::h((string) $z['title']) ?></td>
          <td><?php if ($z['customer_id']): ?>
            <a href="<?= Fmt::h(url('kunden/' . (int) $z['customer_id'])) ?>"><?=
              Fmt::h((string) sicher(static fn() => Db::wert(
                'SELECT name FROM customers WHERE id = ?', [(int) $z['customer_id']], '—'), '—')) ?></a>
          <?php else: ?><span style="color:var(--leise)">—</span><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody></table>
  <?php endif; ?>
</div>
