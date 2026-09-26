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
<?php
  /* DER PROBELAUF (26.09.2026) -- oben, weil er alles darunter entschaerft:
     Solange er an ist, legt die Verwaltung beim KAS nichts an und aendert
     nichts, sondern schreibt ins Protokoll, was sie tun wuerde. */
  require_once __DIR__ . '/../../src/Kas.php';
  $kasProbe = sicher(static fn() => Kas::probelauf(), true);
  $kasProbeEnv = getenv('KAS_DRY_RUN') !== false && getenv('KAS_DRY_RUN') !== '';
  $kasProbeLetzte = $kasProbe ? sicher(static fn() => Db::all("SELECT created_at, title AS message FROM activities WHERE type = 'kas_probelauf'
                                       ORDER BY id DESC LIMIT 5"), []) : [];
?>
<div class="block"><h2>KAS: Probelauf <span class="mehr"><span class="marke2 <?= $kasProbe ? 'warn' : 'gut' ?>"><?= $kasProbe ? 'an' : 'aus' ?></span></span></h2>
  <p style="color:var(--dim);font-size:13.5px;line-height:1.65;margin-bottom:12px">
    <?php if ($kasProbe): ?>
      Beim KAS wird <b>nichts angelegt und nichts geändert</b> — kein Account, keine Domain, kein Postfach.
      Aufträge, die eingerichtet werden müssten, bleiben stehen; im Protokoll steht, was geschähe.
      Lesen (Speicher, Accountliste) geht weiter.
    <?php else: ?>
      Die Verwaltung legt beim KAS wirklich an, sobald ein Auftrag bezahlt und zugestimmt ist.
    <?php endif; ?>
  </p>
  <?php if ($kasProbeEnv): ?>
    <p style="color:var(--leise);font-size:12.5px">Gesteuert über die Server-Variable <code>KAS_DRY_RUN</code> — der Schalter hier wirkt erst, wenn sie entfernt ist.</p>
  <?php else: ?>
    <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0 0 10px">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="<?= $kasProbe ? 'kas_probelauf_aus' : 'kas_probelauf_an' ?>">
      <button class="knopf"><?= $kasProbe ? 'Probelauf ausschalten' : 'Probelauf einschalten' ?></button>
    </form>
  <?php endif; ?>
  <?php if ($kasProbeLetzte): ?>
    <table class="schlicht"><tbody>
      <?php foreach ($kasProbeLetzte as $kpl): ?>
        <tr><td style="width:1%;white-space:nowrap;color:var(--leise)"><?= Fmt::h(Fmt::zeit((string) $kpl['created_at'])) ?></td>
            <td style="font-size:12.5px"><?= Fmt::h((string) $kpl['message']) ?></td></tr>
      <?php endforeach; ?>
    </tbody></table>
  <?php endif; ?>
</div>

<?php $berichtAn = (string) sicher(static fn() => Db::wert("SELECT svalue FROM settings WHERE skey = 'hosting_bericht'", [], '1'), '1') !== '0'; ?>
<div class="block"><h2>Monatsbericht an Hosting-Kunden <span class="mehr"><span class="marke2 <?= $berichtAn ? 'gut' : '' ?>"><?= $berichtAn ? 'an' : 'aus' ?></span></span></h2>
  <p style="color:var(--dim);font-size:13.5px;line-height:1.65;margin-bottom:12px">
    Ab dem Ersten jedes Monats bekommt jeder laufende Hosting-Kunde eine kurze Mail in seiner Sprache:
    Website erreichbar, HTTPS gültig bis …, Speicher belegt von vereinbart. Es steht nur drin, was gemessen ist —
    fehlt jede Messung, geht keine Mail. Frühestens 20 Tage nach dem Einrichten, nie an gesperrte Kunden.
  </p>
  <form method="post" action="<?= Fmt::h(url('')) ?>">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="<?= $berichtAn ? 'hosting_bericht_aus' : 'hosting_bericht_an' ?>">
    <button class="knopf"><?= $berichtAn ? 'Monatsbericht ausschalten' : 'Monatsbericht einschalten' ?></button>
  </form>
</div>

<div class="block"><h2>Cronjob im KAS</h2>
  <p style="color:var(--dim);font-size:13.5px;line-height:1.65;margin-bottom:12px">
    Der Webspace hat keinen eigenen Dienst, der von allein läuft. Der Anstoß kommt vom
    KAS: Er ruft alle zehn Minuten diese Adresse auf. Ohne den Schlüssel darin passiert nichts.
  </p>
  <?php /* 25.09.2026: Der Eintrag geht auch per KAS-Schnittstelle (add_cronjob). */ ?>
  <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0 0 12px">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="cron_kas_anlegen">
    <button class="knopf">Im KAS eintragen lassen</button>
    <span style="color:var(--leise);font-size:12.5px;margin-left:8px">Alle zehn Minuten, per HTTPS. Steht er schon da, passiert nichts.</span>
  </form>
  <div class="feld"><label>Oder diese Adresse von Hand im KAS eintragen</label>
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
