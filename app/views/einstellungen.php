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
    <div class="feld"><label>Mehrwertsteuersatz in Prozent</label>
      <input name="firma_mwst" style="max-width:140px" value="<?= Fmt::h((string) ($firma['firma_mwst'] ?? '0')) ?>">
      <small style="color:var(--leise);font-size:12px">0 heißt: keine Steuer ausgewiesen. Die Preise auf der Website
        gelten als Endpreise — bei einem Satz über 0 wird die Steuer herausgerechnet, nicht aufgeschlagen.</small></div>
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
    ohne KAS und ohne FTP. Das Passwort wird dabei erzeugt, einmal angezeigt und nirgends
    gespeichert; in der Datei auf dem Server steht nur seine Prüfsumme.
    Danach ruft die Verwaltung die Adresse selbst auf und sieht nach, ob wirklich 401 kommt.
  </p>

  <?php if (!$cockpit['beschreibbar']): ?>
    <div class="hinweis" style="background:rgba(251,191,36,.12);border-color:rgba(251,191,36,.35);color:var(--gelb)">
      In den Ordner <code>cockpit/</code> darf von hier aus nicht geschrieben werden. Im KAS unter
      Dateiverwaltung die Schreibrechte prüfen — dann geht es auf Knopfdruck.
    </div>
  <?php else: ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:flex;gap:8px;align-items:flex-end;margin:0">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="cockpit_schuetzen">
        <input type="hidden" name="zurueck" value="einstellungen">
        <div class="feld" style="margin:0"><label>Benutzername</label>
          <input name="benutzer" value="<?= Fmt::h((string) ($cockpit['benutzer'] ?: 'uwe')) ?>" style="width:180px"></div>
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
