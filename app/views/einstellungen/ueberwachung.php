<?php
/* DER TAKTGEBER
   ==========================================================================
   Der Webspace hat keinen Dienst, der von allein läuft. Alles, was ohne
   Klick geschieht — Website-Prüfungen, Erinnerungen, Mahnungen, die
   Gespräche von STRATO —, hängt an dieser einen Adresse und daran, dass der
   KAS sie regelmäßig aufruft. Deshalb steht sie hier bei den Einstellungen
   und nicht nur beim Monitoring: Wer sie vergisst, verliert nicht eine
   Anzeige, sondern jede Automatik. */
?>
<div class="block"><h2>Cronjob im KAS</h2>
  <p style="color:var(--dim);font-size:13.5px;line-height:1.65;margin-bottom:12px">
    Der Webspace hat keinen eigenen Dienst, der von allein läuft. Der Anstoß kommt vom
    KAS: Er ruft alle zehn Minuten diese Adresse auf. Ohne den Schlüssel darin passiert nichts.
  </p>
  <div class="feld"><label>Diese Adresse im KAS eintragen</label>
    <input readonly onclick="this.select()" value="<?= Fmt::h((string) $adresse) ?>"></div>
  <ol style="color:var(--dim);font-size:13.5px;line-height:1.9;padding-left:20px;margin:0">
    <li>Im KAS links auf <b>Tools</b> → <b>Cronjobs</b></li>
    <li><b>Neuen Cronjob anlegen</b></li>
    <li>Bei <b>URL</b> die Adresse oben einfügen</li>
    <li>Intervall: <b>alle 10 Minuten</b></li>
    <li>Speichern — fertig</li>
  </ol>
  <p style="color:var(--leise);font-size:12.5px;margin-top:12px">
    Der Schlüssel gehört nicht in eine E-Mail und nicht in einen Chat. Wer ihn hat, kann den
    Lauf anstoßen — mehr nicht, aber das reicht als Grund, ihn für sich zu behalten.
  </p>
  <?php if ($bilanz): ?>
    <p style="color:var(--leise);font-size:12px;margin-top:14px;word-break:break-all">
      Letzte Bilanz: <?= Fmt::h(json_encode($bilanz, JSON_UNESCAPED_UNICODE)) ?></p>
  <?php endif; ?>
</div>

<?php /* WAS DIESER SERVER KANN (25.09.2026)
         Welche PHP-Erweiterungen da sind, entscheidet, was automatisch geht.
         Bisher liess sich das nur im KAS nachsehen -- oder raten. Hier steht
         es, wie der Server es selbst meldet. */ ?>
<?php
  $sysPunkte = [
      ['PHP ' . PHP_VERSION, true, 'die Sprache der Verwaltung'],
      ['soap', extension_loaded('soap'), 'KAS-Schnittstelle: Accounts, Domains, Postfächer anlegen'],
      ['curl', extension_loaded('curl'), 'Stripe, Domainprüfung, alte Seiten lesen, VIES'],
      ['openssl', extension_loaded('openssl'), 'Tresor für Zugangsdaten, verschlüsseltes IMAP'],
      ['ftp', extension_loaded('ftp'), 'Verbindungstest beim 1:1-Umzug einer Website'],
      ['zip', class_exists('ZipArchive'), 'alte Website als ZIP sichern'],
      ['dom', class_exists('DOMDocument'), 'Texte aus alten Seiten lesen'],
      ['gd', extension_loaded('gd'), 'Bildvorschauen in Ablage und Dashboard'],
      ['fastcgi_finish_request', function_exists('fastcgi_finish_request'), 'alte Website sofort nach dem Öffnen des Fragebogens lesen (sonst im Cron)'],
      ['imap', extension_loaded('imap'), 'nicht nötig — der E-Mail-Umzug spricht IMAP selbst'],
  ];
?>
<div class="block"><h2>Was dieser Server kann</h2>
  <table class="schlicht"><tbody>
    <?php foreach ($sysPunkte as [$sysName, $sysDa, $sysWozu]): ?>
      <tr><td style="width:1%;white-space:nowrap"><?= $sysDa ? '✓' : '✗' ?></td>
        <td style="width:30%"><code><?= Fmt::h($sysName) ?></code></td>
        <td style="color:var(--dim)"><?= Fmt::h($sysWozu) ?></td></tr>
    <?php endforeach; ?>
    <tr><td></td><td><code>max_execution_time</code></td><td style="color:var(--dim)"><?= Fmt::h((string) ini_get('max_execution_time')) ?> s
      — die Umzüge arbeiten in Portionen von höchstens 40 s</td></tr>
  </tbody></table>
</div>
