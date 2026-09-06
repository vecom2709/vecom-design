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
    <input type="hidden" name="zurueck" value="einstellungen?b=email">
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
      <input type="hidden" name="zurueck" value="einstellungen?b=email">
      <button class="knopf">Verbindung prüfen</button></form>
    <?php if ($versand['herkunft'] === 'verwaltung'): ?>
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="versand_schluessel_weg">
        <input type="hidden" name="zurueck" value="einstellungen?b=email">
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
    <input type="hidden" name="zurueck" value="einstellungen?b=email">
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
        <input type="hidden" name="zurueck" value="einstellungen?b=email">
        <button class="knopf">Testnachricht senden</button></form>
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0"
            data-frage="Nummer und Schlüssel löschen und den Zuruf abschalten?" data-ja="Ja, abschalten">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="zuruf_weg">
        <input type="hidden" name="zurueck" value="einstellungen?b=email">
        <button class="knopf">Entfernen</button></form>
    <?php endif; ?>
    <?php if ($zuruf['zuletzt'] !== ''): ?>
      <span style="color:var(--leise);font-size:12.5px">Zuletzt: <?= Fmt::h($zuruf['zuletzt']) ?></span>
    <?php endif; ?>
  </div>
</div>
