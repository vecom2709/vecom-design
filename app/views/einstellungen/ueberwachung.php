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
  <?php $cronWeg = $cronWeg ?? ''; /* AI Office Stufe 0 (06.10.2026): Schlüssel als HTTP-Passwort statt in der Adresse */ ?>
  <div class="hinweis<?= $cronWeg === 'passwort' ? ' gut' : '' ?>" id="cron-weg" style="margin-top:14px">
    <?php if ($cronWeg === 'passwort'): ?>
      Der KAS schickt den Schlüssel als HTTP-Passwort — er steht in keinem Serverprotokoll.
    <?php else: ?>
      Zuletzt kam der Schlüssel <b><?= $cronWeg === 'kopf' ? 'im Kopf' : 'in der Adresse' ?></b>. Sicherer: im KAS beim Cronjob
      unter <b>Erweiterte Einstellungen</b> als <b>HTTP-Benutzer</b> „cron“ und als <b>HTTP-Passwort</b> den Teil hinter
      <code>schluessel=</code> eintragen und in der URL nur <code><?= Fmt::h((string) preg_replace('~\?.*$~', '', (string) $adresse)) ?></code> lassen.
      Die Adresse mit Schlüssel funktioniert weiter, bis du umgestellt hast.
    <?php endif; ?>
  </div>
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
<?php $ddiag = $_SESSION['domain_diagnose'] ?? null; unset($_SESSION['domain_diagnose']); ?>
<?php $sa = $sicherungAussen ?? ['eingerichtet' => false, 'fingerabdruck' => '', 'abgeholt' => null, 'probe' => null];
      $saProbe = $sa['probe'] ?? null; $saAb = $sa['abgeholt'] ?? null; ?>
<div class="block" id="sicherung"><h2>Sicherung außer Haus
  <span class="mehr"><span class="marke2 <?= $sa['eingerichtet'] ? 'gut' : 'warn' ?>"><?= $sa['eingerichtet'] ? 'eingerichtet' : 'fehlt' ?></span></span></h2>
  <p style="color:var(--dim);font-size:13.5px;line-height:1.65;margin-bottom:12px">
    Dein Windows-Rechner holt jede Nacht den Datenbankauszug und alle Kundendateien ab, verschlüsselt —
    lesen kann sie nur er. Einmal die Woche spielt er die Sicherung probeweise in eine eigene Datenbank ein
    und meldet, ob alles da ist. Ohne Passwort: Der Rechner unterschreibt jede Anfrage mit seinem Schlüssel.</p>
  <?php if ($sa['eingerichtet']): ?>
    <table class="schlicht"><tbody>
      <tr><td style="width:34%">Schlüssel des Rechners</td><td><code><?= Fmt::h($sa['fingerabdruck']) ?></code></td></tr>
      <tr><td>Zuletzt abgeholt</td><td><?= $saAb ? Fmt::h(Fmt::zeit((string) $saAb['am']) . ' · ' . $saAb['name']) : '<span style="color:var(--gelb)">noch nie</span>' ?></td></tr>
      <tr><td>Letzte Probe</td><td><?php if ($saProbe): ?>
        <span class="marke2 <?= !empty($saProbe['ok']) ? 'gut' : 'schlecht' ?>"><?= !empty($saProbe['ok']) ? 'in Ordnung' : 'Problem' ?></span>
        <?= Fmt::h(Fmt::zeit((string) $saProbe['am'])) ?> · <?= (int) $saProbe['tabellen'] ?> Tabellen
        <?php if (isset($saProbe['zeilen']['customers'])): ?> · <?= (int) $saProbe['zeilen']['customers'] ?> Kunden<?php endif; ?>
        <?php if (!empty($saProbe['fehler'])): ?><br><small style="color:var(--rot)"><?= Fmt::h((string) $saProbe['fehler']) ?></small><?php endif; ?>
      <?php else: ?><span style="color:var(--gelb)">noch keine</span><?php endif; ?></td></tr>
    </tbody></table>
  <?php endif; ?>
  <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:12px">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="sicherung_schluessel"><input type="hidden" name="zurueck" value="einstellungen?b=ueberwachung">
    <div class="feld"><label for="sicherung-oeffentlich"><?= $sa['eingerichtet'] ? 'Neuen öffentlichen Schlüssel eintragen' : 'Öffentlicher Schlüssel des Rechners' ?></label>
      <textarea id="sicherung-oeffentlich" name="oeffentlich" rows="4" placeholder="-----BEGIN PUBLIC KEY-----" style="font-family:monospace;font-size:12px"></textarea></div>
    <button class="knopf<?= $sa['eingerichtet'] ? '' : ' haupt' ?>">Schlüssel eintragen</button>
    <span style="color:var(--leise);font-size:12.5px;margin-left:8px">Steht auf dem Rechner in <code>Vecom-Sicherung\oeffentlich.pem</code>. Nur der öffentliche Teil — er ist kein Geheimnis.</span>
  </form>
  <?php if ($sa['eingerichtet']): ?>
    <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:10px">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="sicherung_schluessel_weg"><input type="hidden" name="zurueck" value="einstellungen?b=ueberwachung">
      <button class="knopf">Schlüssel entfernen</button></form>
  <?php endif; ?>
</div>

<div class="block"><h2>Domainprüfung testen</h2>
  <p style="color:var(--dim);font-size:13.5px;line-height:1.65;margin-bottom:10px">
    Prüft auf diesem Server je eine vergebene und eine sicher freie Domain (.it und .com), Stufe für Stufe.
    .it hat keinen RDAP-Dienst — ohne WHOIS (Port 43) lässt sich eine freie .it-Domain hier nie bestätigen.</p>
  <form method="post" action="<?= Fmt::h(url('')) ?>"><?= Csrf::feld() ?><input type="hidden" name="tat" value="domainpruefung_testen">
    <button class="knopf">Jetzt testen</button></form>
  <?php if (is_array($ddiag)): ?>
    <table class="schlicht" style="margin-top:10px"><thead><tr><th>Domain</th><th>RDAP</th><th>WHOIS</th><th>DNS</th><th>Zeit</th></tr></thead><tbody>
      <?php foreach ($ddiag as $dz): ?>
        <tr><td><code><?= Fmt::h($dz['domain']) ?></code></td><td><?= Fmt::h($dz['rdap']) ?></td><td><?= Fmt::h($dz['whois']) ?></td>
          <td><?= Fmt::h($dz['dns']) ?></td><td><?= Fmt::h((string) $dz['sekunden']) ?> s</td></tr>
      <?php endforeach; ?>
    </tbody></table>
    <?php $dWhois = array_filter($ddiag, static fn($x) => str_ends_with($x['domain'], '.it') && $x['whois'] !== 'unklar'); ?>
    <p style="font-size:13px;margin-top:8px;color:<?= $dWhois ? 'var(--gruen,#6fcf97)' : 'var(--rot)' ?>">
      <?= $dWhois ? 'WHOIS kommt durch — .it wird automatisch geprüft.'
                  : 'WHOIS kommt von diesem Server nicht durch — .it bitte selbst prüfen und beim Anbieten „Selbst geprüft“ anhaken.' ?></p>
  <?php endif; ?>
</div>

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
