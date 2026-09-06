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

<div class="block">
  <h2>Der Schlüssel</h2>
  <p style="color:var(--leise);font-size:12.5px;margin:-4px 0 12px">
    Er öffnet nur, was am Telefon gebraucht wird: nachschlagen, Preis schätzen, Tag und
    Uhrzeit erfahren, Konfigurator-Link schicken, Anliegen melden, Zusammenfassung senden,
    eine offene Frage notieren. Nicht deinen Zugang, nicht Stripe, nicht die Zahlungen.
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
           . 'Gibt Name, Betrieb, Sprache und Projektstand zurück — nie Beträge.',
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
           . 'Öffnen drin. Bei Bestandskunden geht der Link nur an die hinterlegte Adresse.',
  'eig' => $eigA, 'pflicht' => ['sprache'], 'rumpf' => $rumpfA,
];

$konfigs['preis_auskunft'] = [
  'zweck' => 'Was kostet das? Rechnet mit derselben Maschine wie der Konfigurator und das '
           . 'Angebot — die Zahlen sind immer die aktuellen. Antwort ist eine Spanne, nie ein '
           . 'Festpreis. Wenn der Anrufer schon etwas gesagt hat, seine Spanne; sonst die übliche.',
  'eig' => (static function () use ($fragen) {
      $e = [];
      foreach ($fragen as $f => $inf) {
          $e[$f] = $inf['art'] === 'mehrfach'
              ? ['type' => 'array', 'items' => ['type' => 'string', 'enum' => $inf['werte']],
                 'description' => 'Nur eintragen, was der Anrufer wirklich gesagt hat.']
              : ['type' => 'string', 'enum' => $inf['werte'],
                 'description' => 'Nur eintragen, was der Anrufer wirklich gesagt hat.'];
      }
      return $e;
  })(),
  'pflicht' => [],
  'rumpf' => '{"aktion":"preis_auskunft"' . (static function () use ($fragen) {
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
           . 'oder „Link noch einmal schicken". Beschwerden gelten immer als dringend.',
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
                                    . '(„ab 14 Uhr", „nur vormittags", „nicht Dienstag")'],
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
           . 'Danach sagen „das schaue ich nach und melde mich" — und nichts erfinden.',
  'eig' => [
    'frage' => ['type' => 'string', 'minLength' => 5, 'maxLength' => 500,
                'description' => 'Die Frage des Anrufers, möglichst wörtlich'],
    'kunde_id' => ['type' => 'integer', 'description' => 'Nur wenn vorher gefunden'],
  ],
  'pflicht' => ['frage'],
  'rumpf' => '{"aktion":"wissensluecke","frage":"{{ frage }}","kunde_id":"{{ kunde_id }}"}',
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
    $j = [
      'name' => $name,
      'description' => $k['zweck'],
      'parameters' => ['type' => 'object', 'properties' => $k['eig'],
                       'required' => $k['pflicht']],
      'request' => [
        'method' => 'POST',
        'url' => $basis . '/telefon.php',
        'headers' => [
          ['name' => 'Content-Type', 'value' => 'application/json'],
          ['name' => 'X-Vecom-Telefon', 'value' => (string) $schluessel],
        ],
        'postData' => ['mimeType' => 'application/json', 'text' => '@@RUMPF@@'],
      ],
    ];
    $text = json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    /* Der Rumpf wird nachtraeglich eingesetzt, damit {{ }} und die
       Anfuehrungszeichen darin so stehen, wie STRATO sie erwartet. */
    $text = str_replace('"@@RUMPF@@"', json_encode($k['rumpf'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $text);
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
