<div class="kopf"><div><h1>Einstellungen</h1></div></div>

<div class="block">
  <h2>Beispieldaten</h2>
  <p style="color:var(--dim);font-size:13.5px;margin:8px 0 16px;line-height:1.6">
    Drei vollständige Vorgänge in drei Sprachen — eine Bäckerei aus Aragona auf Italienisch,
    eine Ferienvermietung auf Deutsch, ein Keramikstudio auf Englisch. Damit füllt sich jede
    Ansicht und du siehst, wie die Verwaltung aussieht, wenn Kunden da sind.
  </p>

  <?php if ($beispiele > 0): ?>
    <div class="hinweis" style="background:rgba(251,191,36,.12);border-color:rgba(251,191,36,.35);color:var(--gelb)">
      Es sind gerade <b><?= (int) $beispiele ?></b> Beispielkunden geladen. Alle Zahlen im Dashboard
      rechnen darüber mit — sie sind also noch nicht deine echten Zahlen.
    </div>
    <p style="color:var(--dim);font-size:13.5px;margin-bottom:14px">
      Die Beispiele verschwinden von allein, sobald die erste echte Bestellung entsteht.
      Du kannst sie aber jederzeit selbst löschen. Gelöscht wird ausschließlich, was als
      Beispiel gekennzeichnet ist — an echte Daten kommt die Löschung gar nicht heran.
    </p>
    <form method="post" action="<?= Fmt::h(url('')) ?>">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="beispiel_loeschen">
      <input type="hidden" name="zurueck" value="einstellungen">
      <button class="knopf haupt">Beispieldaten jetzt löschen</button></form>

  <?php else: ?>
    <?php if ($echteDaten): ?>
      <div class="hinweis gut">Keine Beispieldaten geladen — die Verwaltung zeigt ausschließlich echte Vorgänge.</div>
      <p style="color:var(--dim);font-size:13.5px;margin-bottom:14px">
        Du hast schon echte Daten. Wenn du trotzdem Beispiele dazulegst, rechnen Umsatz und
        Anzahl im Dashboard beides zusammen — oben steht dann auf jeder Seite ein Hinweis,
        und mit einem Klick sind die Beispiele wieder weg.
      </p>
    <?php else: ?>
      <div class="hinweis">Noch keine Daten. Mit Beispieldaten siehst du sofort, wie alles zusammenspielt.</div>
    <?php endif; ?>
    <form method="post" action="<?= Fmt::h(url('')) ?>">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="beispiel_anlegen">
      <input type="hidden" name="zurueck" value="einstellungen">
      <button class="knopf haupt">Beispieldaten anlegen</button></form>
  <?php endif; ?>
</div>

<div class="block">
  <h2>Firmendaten</h2>
  <p style="color:var(--dim);font-size:13.5px;margin:8px 0 16px;line-height:1.6">
    Das, was oben auf jedem Beleg steht. Solange <b>keine Partita IVA</b> eingetragen ist, stellt die
    Verwaltung <b>Zahlungsbelege</b> aus — keine Rechnungen im steuerlichen Sinn. Sobald du die Nummer
    hier einträgst, heißen die Dokumente Rechnung und bekommen einen eigenen Nummernkreis ab 1.
  </p>
  <form method="post" action="<?= Fmt::h(url('')) ?>">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="firma_speichern">
    <input type="hidden" name="zurueck" value="einstellungen">
    <div class="zwei">
      <div>
        <div class="feld"><label>Firma</label><input name="firma_name" value="<?= Fmt::h((string) ($firma['firma_name'] ?? '')) ?>"></div>
        <div class="feld"><label>Inhaber</label><input name="firma_inhaber" value="<?= Fmt::h((string) ($firma['firma_inhaber'] ?? '')) ?>"></div>
        <div class="feld"><label>Straße und Hausnummer</label><input name="firma_strasse" value="<?= Fmt::h((string) ($firma['firma_strasse'] ?? '')) ?>"></div>
        <div style="display:flex;gap:10px">
          <div class="feld" style="flex:0 0 110px"><label>PLZ</label><input name="firma_plz" value="<?= Fmt::h((string) ($firma['firma_plz'] ?? '')) ?>"></div>
          <div class="feld" style="flex:1"><label>Ort</label><input name="firma_ort" value="<?= Fmt::h((string) ($firma['firma_ort'] ?? '')) ?>"></div>
        </div>
        <div class="feld"><label>Land</label><input name="firma_land" value="<?= Fmt::h((string) ($firma['firma_land'] ?? '')) ?>"></div>
      </div>
      <div>
        <div class="feld"><label>E-Mail</label><input name="firma_email" value="<?= Fmt::h((string) ($firma['firma_email'] ?? '')) ?>"></div>
        <div class="feld"><label>Telefon</label><input name="firma_telefon" value="<?= Fmt::h((string) ($firma['firma_telefon'] ?? '')) ?>"></div>
        <div class="feld"><label>Website</label><input name="firma_web" value="<?= Fmt::h((string) ($firma['firma_web'] ?? '')) ?>"></div>
        <div class="feld"><label>Bank</label><input name="firma_bank" value="<?= Fmt::h((string) ($firma['firma_bank'] ?? '')) ?>"></div>
        <div class="feld"><label>IBAN</label><input name="firma_iban" value="<?= Fmt::h((string) ($firma['firma_iban'] ?? '')) ?>"></div>
      </div>
    </div>

    <div class="zwei" style="margin-top:4px">
      <div>
        <div class="feld"><label>Partita IVA</label>
          <input name="firma_piva" placeholder="noch keine" value="<?= Fmt::h((string) ($firma['firma_piva'] ?? '')) ?>">
          <small style="color:var(--leise);font-size:12px">Leer lassen, solange du keine hast — dann bleiben es Belege.</small></div>
      </div>
      <div>
        <div class="feld"><label>Codice fiscale</label><input name="firma_steuernr" value="<?= Fmt::h((string) ($firma['firma_steuernr'] ?? '')) ?>"></div>
      </div>
    </div>
    <div class="zwei" style="margin-top:4px">
      <div>
        <div class="feld"><label>Steuerregime</label>
          <?php $reg = (string) ($firma['firma_regime'] ?? 'normal'); ?>
          <select name="firma_regime">
            <option value="normal" <?= $reg !== 'forfettario' ? 'selected' : '' ?>>Normal — IVA wird ausgewiesen</option>
            <option value="forfettario" <?= $reg === 'forfettario' ? 'selected' : '' ?>>Regime forfettario — keine IVA</option>
          </select>
          <small style="color:var(--leise);font-size:12px">Wirkt erst mit einer Partita IVA. Im forfettario steht der
            gesetzliche Hinweis nach L. 190/2014 automatisch auf der Rechnung, und ab 77,47 € der Vermerk zur Marca da bollo.</small></div>
      </div>
      <div>
        <div class="feld"><label>Mehrwertsteuersatz in Prozent</label>
          <input name="firma_mwst" style="max-width:140px" value="<?= Fmt::h((string) ($firma['firma_mwst'] ?? '0')) ?>">
          <small style="color:var(--leise);font-size:12px">Die Preise auf der Website gelten als Endpreise — die Steuer
            wird herausgerechnet, nicht aufgeschlagen.</small></div>
      </div>
    </div>

    <?php
      $hatPiva = trim((string) ($firma['firma_piva'] ?? '')) !== '';
      $satzEin = (float) str_replace(',', '.', (string) ($firma['firma_mwst'] ?? '0'));
    ?>
    <?php if (!$hatPiva): ?>
      <div class="hinweis<?= $satzEin > 0 ? ' warnung' : '' ?>" style="margin:14px 0 4px">
        <b>Solange keine Partita IVA hier steht</b>, heißen die Dokumente <b>Zahlungsbeleg</b> (BE-…), es wird
        <b>keine Steuer ausgewiesen</b> — auch dann nicht, wenn oben ein Satz eingetragen ist —, und unten steht
        der Satz „keine Rechnung im steuerlichen Sinn".
        <?php if ($satzEin > 0): ?>
          <br><br>Gerade stehen <b><?= Fmt::h(rtrim(rtrim(number_format($satzEin, 2, ',', '.'), '0'), ',')) ?> %</b> im
          Feld, ohne dass sie irgendwo wirken. Das ist in Ordnung als Vormerkung — sobald du die Nummer einträgst,
          greift der Satz sofort und aus den Belegen werden Rechnungen (RE-…) mit eigener Nummernreihe ab 0001.
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="hinweis gut" style="margin:14px 0 4px">
        <b>Partita IVA hinterlegt.</b> Die Dokumente heißen <b>Rechnung</b> (RE-…)
        <?= (string) ($firma['firma_regime'] ?? 'normal') === 'forfettario'
            ? 'und tragen den Hinweis nach L. 190/2014, ohne IVA.'
            : 'und weisen die Steuer aus.' ?>
      </div>
    <?php endif; ?>
    <div class="feld"><label>Hinweis auf dem Beleg</label>
      <textarea name="firma_hinweis" rows="3" placeholder="Den genauen Wortlaut gibt dir dein Commercialista."><?= Fmt::h((string) ($firma['firma_hinweis'] ?? '')) ?></textarea></div>
    <button class="knopf haupt">Firmendaten speichern</button>
  </form>
</div>

<div class="block">
  <h2>Cockpit-Schutz</h2>
  <?php
    $zugang = $_SESSION['cockpit_zugang'] ?? null;
    unset($_SESSION['cockpit_zugang']);
  ?>
  <?php if ($zugang): ?>
    <div class="hinweis gut" style="margin-bottom:14px">
      <b>Schreib dir das jetzt auf — es wird nicht wieder angezeigt.</b>
    </div>
    <table style="margin-bottom:16px"><tbody>
      <tr><td style="width:130px">Benutzername</td>
        <td><input readonly onclick="this.select()" value="<?= Fmt::h($zugang['benutzer']) ?>"></td></tr>
      <tr><td>Passwort</td>
        <td><input readonly onclick="this.select()" style="font-size:16px;letter-spacing:.04em"
                   value="<?= Fmt::h($zugang['passwort']) ?>"></td></tr>
    </tbody></table>
  <?php endif; ?>

  <?php if ($cockpit['geschuetzt'] === true): ?>
    <div class="hinweis gut">Das Cockpit ist geschützt — es antwortet mit 401 und fragt nach dem Passwort.
      <?php if ($cockpit['benutzer']): ?> Benutzer: <b><?= Fmt::h($cockpit['benutzer']) ?></b>.<?php endif; ?></div>
  <?php elseif ($cockpit['geschuetzt'] === false): ?>
    <div class="hinweis schlecht"><b>Das Cockpit steht offen.</b> Jeder, der die Adresse kennt, sieht deine Zahlen.</div>
  <?php else: ?>
    <div class="hinweis">Der Zustand ließ sich gerade nicht prüfen — die Adresse war nicht erreichbar.</div>
  <?php endif; ?>

  <p style="color:var(--dim);font-size:13.5px;line-height:1.65;margin:12px 0 14px">
    Die Verwaltung liegt auf demselben Server wie das Cockpit und kann den Schutz selbst setzen —
    ohne KAS und ohne FTP. Trag ein eigenes Passwort ein oder lass das Feld leer, dann wird eines
    erzeugt und genau einmal angezeigt. Gespeichert wird es nirgends; in der Datei auf dem Server
    steht nur seine Prüfsumme.
    Danach ruft die Verwaltung die Adresse selbst auf und sieht nach, ob wirklich 401 kommt.
  </p>

  <?php if (!$cockpit['beschreibbar']): ?>
    <div class="hinweis" style="background:rgba(251,191,36,.12);border-color:rgba(251,191,36,.35);color:var(--gelb)">
      In den Ordner <code>cockpit/</code> darf von hier aus nicht geschrieben werden. Im KAS unter
      Dateiverwaltung die Schreibrechte prüfen — dann geht es auf Knopfdruck.
    </div>
  <?php else: ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;margin:0">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="cockpit_schuetzen">
        <input type="hidden" name="zurueck" value="einstellungen">
        <div class="feld" style="margin:0"><label>Benutzername</label>
          <input name="benutzer" value="<?= Fmt::h((string) ($cockpit['benutzer'] ?: 'uwe')) ?>" style="width:180px"></div>
        <div class="feld" style="margin:0"><label>Passwort <span style="color:var(--leise);font-weight:400">— leer: wird erzeugt</span></label>
          <input name="passwort" type="password" autocomplete="new-password" placeholder="dein eigenes"
                 style="width:200px"></div>
        <button class="knopf haupt"><?= $cockpit['eingerichtet'] ? 'Neues Passwort setzen' : 'Jetzt schützen' ?></button>
      </form>
      <?php if ($cockpit['eingerichtet']): ?>
        <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0">
          <?= Csrf::feld() ?><input type="hidden" name="tat" value="cockpit_frei">
          <input type="hidden" name="zurueck" value="einstellungen">
          <button class="knopf">Schutz entfernen</button></form>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<div class="block">
  <h2>E-Mail-Versand</h2>

  <?php if ($versandTest): ?>
    <div class="hinweis <?= $versandTest['ok'] ? 'gut' : 'schlecht' ?>" style="margin-bottom:14px">
      <?= Fmt::h((string) $versandTest['text']) ?></div>
  <?php endif; ?>

  <?php if ($versand['herkunft'] === 'verwaltung'): ?>
    <div class="hinweis gut">Ein Schlüssel ist hier hinterlegt — er endet auf
      <b><?= Fmt::h($versand['ende']) ?></b>. Er hat Vorrang vor der Datei auf dem Server.</div>
  <?php elseif ($versand['herkunft'] === 'datei'): ?>
    <div class="hinweis">Es gilt der Schlüssel aus <code>config.local.php</code> auf dem Server.
      Trag hier einen ein, wenn du ihn ohne FTP ändern willst.</div>
  <?php else: ?>
    <div class="hinweis schlecht"><b>Es ist kein Schlüssel hinterlegt.</b> Ohne ihn geht keine
      einzige E-Mail raus — keine Eingangsbestätigung, kein Zahlungslink, kein Fragebogen.</div>
  <?php endif; ?>

  <p style="color:var(--dim);font-size:13.5px;line-height:1.65;margin:12px 0 14px">
    Den Schlüssel gibt es in Brevo unter <b>SMTP &amp; API → API- und MCP-Schlüsseln →
    Einen neuen API-Schlüssel generieren</b>. Brevo zeigt ihn genau einmal an. Hier wird er
    gespeichert und nie wieder angezeigt — nur seine letzten vier Zeichen, zum Wiedererkennen.
    Die Absenderadresse muss in Brevo als Absender verifiziert sein.
  </p>

  <form method="post" action="<?= Fmt::h(url('')) ?>">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="versand_speichern">
    <input type="hidden" name="zurueck" value="einstellungen">
    <div class="feld"><label>Brevo-Schlüssel <span style="color:var(--leise);font-weight:400">— leer lassen ändert nichts</span></label>
      <input name="key" type="password" autocomplete="new-password" spellcheck="false"
             placeholder="<?= $versand['herkunft'] === 'verwaltung' ? '•••• ' . Fmt::h($versand['ende']) : 'xkeysib-…' ?>"></div>
    <div class="feld"><label>Absenderadresse</label>
      <input name="from" value="<?= Fmt::h((string) $versand['from']) ?>" placeholder="kontakt@vecom-design.it"></div>
    <div class="feld"><label>Absendername</label>
      <input name="name" value="<?= Fmt::h((string) $versand['name']) ?>" placeholder="Vecom Design"></div>
    <div class="feld"><label>Meldungen an mich <span style="color:var(--leise);font-weight:400">— leer: an die Absenderadresse</span></label>
      <input name="to" value="<?= Fmt::h((string) $versand['to']) ?>" placeholder="kontakt@vecom-design.it"></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <button class="knopf haupt">Speichern und prüfen</button>
    </div>
  </form>

  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
    <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="versand_pruefen">
      <input type="hidden" name="zurueck" value="einstellungen">
      <button class="knopf">Verbindung prüfen</button></form>
    <?php if ($versand['herkunft'] === 'verwaltung'): ?>
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="versand_schluessel_weg">
        <input type="hidden" name="zurueck" value="einstellungen">
        <button class="knopf">Schlüssel entfernen</button></form>
    <?php endif; ?>
  </div>
</div>

<?php /* -----------------------------------------------------------------
     Zuruf aufs Handy. Steht bewusst direkt unter dem E-Mail-Versand: Es ist
     der zweite Kanal, und sein Sinn ist gerade, NICHT an Brevo zu haengen.
     ----------------------------------------------------------------- */ ?>
<div class="block">
  <h2>Zuruf aufs Handy (WhatsApp)</h2>

  <?php if ($zuruf['an'] && $zuruf['nummer'] !== '' && $zuruf['schluessel']): ?>
    <div class="hinweis gut">Eingeschaltet für <b><?= Fmt::h($zuruf['nummer']) ?></b>.
      Du bekommst eine Nachricht bei jeder neuen Anfrage und bei jeder Störung.</div>
  <?php elseif ($zuruf['nummer'] !== '' && $zuruf['schluessel']): ?>
    <div class="hinweis">Eingerichtet, aber ausgeschaltet.</div>
  <?php else: ?>
    <div class="hinweis">Noch nicht eingerichtet. Kostet nichts und dauert zwei Minuten.</div>
  <?php endif; ?>

  <p style="color:var(--dim);font-size:13.5px;line-height:1.65;margin:12px 0 14px">
    <b>So bekommst du den Schlüssel:</b> Speichere <b>+34 684 72 39 62</b> als Kontakt
    (Name egal) und schick ihm über WhatsApp genau diesen Satz:
    <code style="user-select:all">I allow callmebot to send me messages</code>.
    Nach ein bis zwei Minuten antwortet er mit deinem Schlüssel — eine Zahlenfolge.
    Die trägst du hier ein.
  </p>

  <p style="color:var(--leise);font-size:12.5px;line-height:1.6;margin:0 0 14px">
    Verschickt wird nur, <i>dass</i> etwas ist, und der Link zur Verwaltung — nie ein
    Kundenname, keine Adresse, nicht der Text einer Anfrage. Der Weg läuft über einen
    fremden Dienst, und was dort nicht ankommt, kann dort auch nicht liegen bleiben.
    Bei Störungen kommt höchstens alle 15 Minuten eine Nachricht je Art, sonst klingelt
    ein kaputter Mailversand das Handy leer. Der Zuruf ist Zugabe: Die Anfrage steht so
    oder so in der Verwaltung, auch wenn er ausfällt.
  </p>

  <?php /* autocomplete="new-password" und nicht "off": Chrome ignoriert "off"
       bei Passwortfeldern und fuellt sie trotzdem aus dem Passwortspeicher.
       Genau das ist beim Einrichten passiert — das Speichern der Nummer
       scheiterte an einem Schluessel, den niemand eingegeben hatte. Waere der
       eingefuellte Wert zufaellig eine Ziffernfolge gewesen, waere er
       stillschweigend als Schluessel gelandet und der Zuruf haette fuer immer
       ins Leere gefunkt. "new-password" respektieren die Browser. */ ?>
  <form method="post" action="<?= Fmt::h(url('')) ?>">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="zuruf_speichern">
    <input type="hidden" name="zurueck" value="einstellungen">
    <div class="feld"><label>Deine WhatsApp-Nummer <span style="color:var(--leise);font-weight:400">— mit Landesvorwahl</span></label>
      <input name="nummer" value="<?= Fmt::h($zuruf['nummer']) ?>" placeholder="+39 320 1234567"></div>
    <div class="feld"><label>Schlüssel von CallMeBot
      <span style="color:var(--leise);font-weight:400">— leer lassen ändert nichts</span></label>
      <input name="key" type="password" autocomplete="new-password" spellcheck="false"
             placeholder="<?= $zuruf['schluessel'] ? '•••• hinterlegt' : '1234567' ?>"></div>
    <label style="display:flex;gap:9px;align-items:center;margin-bottom:14px;cursor:pointer">
      <input type="checkbox" name="an" value="1" style="width:auto" <?= $zuruf['an'] ? 'checked' : '' ?>>
      <span>Zuruf einschalten</span></label>
    <button class="knopf haupt">Speichern</button>
  </form>

  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:12px">
    <?php if ($zuruf['nummer'] !== '' && $zuruf['schluessel']): ?>
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="zuruf_pruefen">
        <input type="hidden" name="zurueck" value="einstellungen">
        <button class="knopf">Testnachricht senden</button></form>
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0"
            onsubmit="return confirm('Nummer und Schlüssel löschen und den Zuruf abschalten?')">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="zuruf_weg">
        <input type="hidden" name="zurueck" value="einstellungen">
        <button class="knopf">Entfernen</button></form>
    <?php endif; ?>
    <?php if ($zuruf['zuletzt'] !== ''): ?>
      <span style="color:var(--leise);font-size:12.5px">Zuletzt: <?= Fmt::h($zuruf['zuletzt']) ?></span>
    <?php endif; ?>
  </div>
</div>

<div class="block">
  <h2>Zugänge</h2>
  <table style="margin-bottom:18px"><thead><tr><th>Name</th><th>E-Mail</th><th>Zuletzt angemeldet</th><th></th></tr></thead><tbody>
  <?php foreach ($zugaenge as $u): ?>
    <tr style="<?= (int) $u['active'] === 1 ? '' : 'opacity:.5' ?>">
      <td><?= Fmt::h($u['name']) ?>
        <?php if ((int) $u['id'] === Auth::id()): ?><span class="marke2" style="margin-left:6px">du</span><?php endif; ?>
        <?php if ((int) $u['active'] !== 1): ?><span class="marke2 schlecht" style="margin-left:6px">abgeschaltet</span><?php endif; ?></td>
      <td><?= Fmt::h($u['email']) ?></td>
      <td style="color:var(--leise)"><?= Fmt::h($u['last_login_at'] ? Fmt::seit($u['last_login_at']) : 'noch nie') ?></td>
      <td style="text-align:right"><?php if ((int) $u['id'] !== Auth::id()): ?>
        <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0">
          <?= Csrf::feld() ?><input type="hidden" name="tat" value="zugang_umschalten">
          <input type="hidden" name="zurueck" value="einstellungen">
          <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
          <button class="knopf"><?= (int) $u['active'] === 1 ? 'Abschalten' : 'Wieder anschalten' ?></button></form>
      <?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table>

  <div class="zwei">
    <div>
      <h3 style="font-size:13px;color:var(--leise);text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px">Eigenes Passwort ändern</h3>
      <form method="post" action="<?= Fmt::h(url('')) ?>">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="passwort_aendern">
        <input type="hidden" name="zurueck" value="einstellungen">
        <div class="feld"><label>Bisheriges Passwort</label><input type="password" name="alt" autocomplete="current-password" required></div>
        <div class="feld"><label>Neues Passwort</label><input type="password" name="neu" autocomplete="new-password" minlength="10" required>
          <small style="color:var(--leise);font-size:12px">Mindestens zehn Zeichen.</small></div>
        <div class="feld"><label>Noch einmal</label><input type="password" name="neu2" autocomplete="new-password" minlength="10" required></div>
        <button class="knopf haupt">Passwort ändern</button></form>
    </div>
    <div>
      <h3 style="font-size:13px;color:var(--leise);text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px">Weiteren Zugang anlegen</h3>
      <form method="post" action="<?= Fmt::h(url('')) ?>">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="zugang_anlegen">
        <input type="hidden" name="zurueck" value="einstellungen">
        <div class="feld"><label>Name</label><input name="name" required></div>
        <div class="feld"><label>E-Mail</label><input type="email" name="email" required></div>
        <div class="feld"><label>Passwort</label><input type="password" name="passwort" autocomplete="new-password" minlength="10" required></div>
        <button class="knopf">Zugang anlegen</button></form>
      <p style="color:var(--leise);font-size:12.5px;margin-top:10px">
        Ein Zugang sieht alles, was du siehst. Gib ihn nur an jemanden, dem du deine Bücher zeigen würdest.</p>
    </div>
  </div>
</div>

<div class="block">
  <h2>Was noch kommt</h2>
  <p style="color:var(--dim);font-size:13.5px;line-height:1.7">
    Statistiken über längere Zeiträume und die monatliche Betreuung als Stripe-Abo —
    die braucht ein freigeschaltetes Stripe-Konto.
  </p>
</div>
