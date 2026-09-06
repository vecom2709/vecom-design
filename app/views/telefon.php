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
      Noch kein Aufruf. Trag die vier Konfigurationen unten bei STRATO ein.
    <?php endif; ?>
  </p></div>
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
    Er öffnet genau vier Dinge: nachschlagen, Konfigurator-Link schicken, Anliegen melden,
    Zusammenfassung senden. Nicht deinen Zugang, nicht Stripe, nicht die Zahlungen.
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
/* ---------- Die vier fertigen Konfigurationen ----------
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
    'text' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 4000,
               'description' => 'Das Anliegen in eigenen Worten des Anrufers'],
  ],
  'pflicht' => ['art', 'text'],
  'rumpf' => '{"aktion":"melde","art":"{{ art }}","prioritaet":"{{ prioritaet }}",'
           . '"kunde_id":"{{ kunde_id }}","name":"{{ name }}","telefon":"{{ telefon }}","text":"{{ text }}"}',
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
  <h2>Die vier Konfigurationen für STRATO</h2>
  <p style="color:var(--leise);font-size:12.5px;margin:-4px 0 14px">
    Bei STRATO unter <b>API-Integration → neu anlegen</b>. Für jede der vier: den Block
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
