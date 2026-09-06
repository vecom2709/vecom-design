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
          <input type="hidden" name="zurueck" value="einstellungen?b=zugaenge">
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
        <input type="hidden" name="zurueck" value="einstellungen?b=zugaenge">
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
        <input type="hidden" name="zurueck" value="einstellungen?b=zugaenge">
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
        <input type="hidden" name="zurueck" value="einstellungen?b=zugaenge">
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
          <input type="hidden" name="zurueck" value="einstellungen?b=zugaenge">
          <button class="knopf">Schutz entfernen</button></form>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
