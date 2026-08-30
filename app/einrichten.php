<?php
declare(strict_types=1);
/* ==========================================================================
   Einmalige Einrichtung der Verwaltungsplattform.

   Aufruf:  https://vecom-design.it/app/einrichten.php

   Drei Riegel schuetzen diese Seite:
   1. Sie arbeitet nur, solange app/config.local.php noch nicht existiert.
      Danach ist sie wirkungslos.
   2. Sie verlangt einen einmaligen Schluessel. Hier steht nur dessen
      Pruefsumme — daraus laesst sich der Schluessel nicht zurueckrechnen.
   3. Eingegebene Werte landen nie als Text in der Konfigurationsdatei,
      sondern ueber var_export — so kann kein Feld Code einschleusen.
   ========================================================================== */

require __DIR__ . '/src/Db.php';
require __DIR__ . '/src/Einrichtung.php';

const SCHLUESSEL_PRUEFSUMME = 'b31dd4abbe9ff6a1aaf359afef7d0e7684b16b36dd58d8147aaa77eee4c372af';

$konfigPfad = __DIR__ . '/config.local.php';
$fertig     = is_file($konfigPfad);
$fehler     = [];
$erfolg     = null;
$konfigText = null;
$eingabe = [
    'schluessel' => '', 'host' => 'localhost', 'name' => '', 'user' => '', 'pass' => '',
    'admin_name' => 'Uwe Vetter', 'admin_email' => 'kontakt@vecom-design.it', 'admin_pass' => '',
];

if (!$fertig && $_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($eingabe as $k => $alt) { $eingabe[$k] = trim((string) ($_POST[$k] ?? $alt)); }

    if (!hash_equals(SCHLUESSEL_PRUEFSUMME, hash('sha256', $eingabe['schluessel']))) {
        $fehler[] = 'Der Einrichtungsschlüssel stimmt nicht.';
        usleep(400000);                       // bremst systematisches Raten
    }
    foreach (['name' => 'Datenbankname', 'user' => 'Datenbank-Benutzer', 'pass' => 'Datenbank-Passwort'] as $k => $t) {
        if ($eingabe[$k] === '') { $fehler[] = "$t fehlt."; }
    }
    if (!filter_var($eingabe['admin_email'], FILTER_VALIDATE_EMAIL)) { $fehler[] = 'Die Admin-E-Mail ist ungültig.'; }
    if (mb_strlen($eingabe['admin_pass']) < 10) { $fehler[] = 'Das Admin-Passwort braucht mindestens 10 Zeichen.'; }

    if (!$fehler) {
        // Erst verbinden, dann schreiben — eine falsche Angabe soll keine Datei hinterlassen.
        try {
            $pdo = new PDO(
                sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $eingabe['host'], $eingabe['name']),
                $eingabe['user'], $eingabe['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 8]
            );
            unset($pdo);
        } catch (Throwable $e) {
            $fehler[] = 'Verbindung zur Datenbank nicht möglich. Prüfe Name, Benutzer und Passwort im KAS.';
        }
    }

    if (!$fehler) {
        $konfig = [
            'db' => ['host' => $eingabe['host'], 'name' => $eingabe['name'],
                     'user' => $eingabe['user'], 'pass' => $eingabe['pass']],
            'basis' => '/app', 'firma' => 'Vecom Design', 'mwst' => 0.0, 'zeitzone' => 'Europe/Rome',
        ];
        if (!Einrichtung::konfigSchreiben($konfigPfad, $konfig)) {
            $fehler[] = 'Die Datei app/config.local.php konnte nicht geschrieben werden — der Ordner ist nicht beschreibbar.';
            $konfigText = Einrichtung::konfigText($konfig);
        } else {
            try {
                require_once __DIR__ . '/src/Config.php';
                $schritte = [];
                $neu = Einrichtung::migrieren();
                $schritte[] = $neu ? count($neu) . ' Migration(en) eingespielt' : 'Tabellen waren bereits vorhanden';
                $schritte[] = 'Zugang: ' . Einrichtung::admin($eingabe['admin_name'], $eingabe['admin_email'], $eingabe['admin_pass']);
                $schritte[] = 'Pakete: ' . implode(', ', Einrichtung::pakete());
                $erfolg = $schritte;
                $fertig = true;
            } catch (Throwable $e) {
                @unlink($konfigPfad);          // nichts Halbfertiges zuruecklassen
                $fehler[] = 'Beim Anlegen der Tabellen ist etwas schiefgegangen: ' . $e->getMessage();
            }
        }
    }
}
$h = static fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Verwaltung einrichten — Vecom Design</title>
<link rel="stylesheet" href="assets/admin.css">
</head>
<body>
<div class="anmeldung" style="max-width:560px">
  <div class="marke" style="justify-content:center;font-size:18px"><b>VECOM</b>&nbsp;Verwaltung einrichten</div>

  <?php if ($erfolg): ?>
    <div class="block">
      <div class="hinweis gut">Fertig. Die Verwaltung ist eingerichtet.</div>
      <ul class="verlauf">
        <?php foreach ($erfolg as $s): ?><li><span class="punkt" style="background:var(--gruen)"></span><span><?= $h($s) ?></span></li><?php endforeach; ?>
      </ul>
      <p style="color:var(--dim);font-size:13px;margin:12px 0">Diese Seite arbeitet ab jetzt nicht mehr — sie erkennt die
      vorhandene Konfiguration und nimmt keine Eingaben mehr an.</p>
      <a class="knopf haupt" href="./">Zur Verwaltung</a>
    </div>

  <?php elseif ($fertig): ?>
    <div class="block">
      <div class="hinweis gut">Schon eingerichtet.</div>
      <p style="color:var(--dim);font-size:13.5px">Es gibt bereits eine <code>config.local.php</code>. Aus Sicherheitsgründen
      nimmt diese Seite dann keine Eingaben mehr an. Soll neu eingerichtet werden, muss die Datei zuerst vom
      Webspace entfernt werden.</p>
      <a class="knopf haupt" href="./">Zur Verwaltung</a>
    </div>

  <?php else: ?>
    <div class="block">
      <?php foreach ($fehler as $f): ?><div class="hinweis schlecht"><?= $h($f) ?></div><?php endforeach; ?>
      <?php if ($konfigText !== null): ?>
        <p style="color:var(--dim);font-size:13px">Lege die Datei <code>app/config.local.php</code> von Hand mit diesem Inhalt an:</p>
        <textarea rows="12" readonly style="font-family:ui-monospace,monospace;font-size:12px"><?= $h($konfigText) ?></textarea>
      <?php endif; ?>

      <form method="post">
        <div class="feld"><label>Einrichtungsschlüssel</label>
          <input name="schluessel" value="<?= $h($eingabe['schluessel']) ?>" required autofocus
                 placeholder="xxxxx-xxxxx-xxxxx-xxxxx" autocomplete="off"></div>

        <div style="padding:12px 0 6px;font-size:11px;text-transform:uppercase;letter-spacing:.09em;color:var(--leise)">Datenbank aus dem KAS</div>
        <div class="reihe">
          <div class="feld"><label>Server</label><input name="host" value="<?= $h($eingabe['host']) ?>"></div>
          <div class="feld"><label>Datenbankname</label><input name="name" value="<?= $h($eingabe['name']) ?>" required placeholder="d0xxxxxx"></div>
        </div>
        <div class="reihe">
          <div class="feld"><label>Benutzer</label><input name="user" value="<?= $h($eingabe['user']) ?>" required placeholder="d0xxxxxx"></div>
          <div class="feld"><label>Passwort</label><input type="password" name="pass" required autocomplete="new-password"></div>
        </div>

        <div style="padding:12px 0 6px;font-size:11px;text-transform:uppercase;letter-spacing:.09em;color:var(--leise)">Dein Zugang</div>
        <div class="reihe">
          <div class="feld"><label>Name</label><input name="admin_name" value="<?= $h($eingabe['admin_name']) ?>" required></div>
          <div class="feld"><label>E-Mail</label><input type="email" name="admin_email" value="<?= $h($eingabe['admin_email']) ?>" required></div>
        </div>
        <div class="feld"><label>Passwort (mindestens 10 Zeichen)</label>
          <input type="password" name="admin_pass" required autocomplete="new-password"></div>

        <button class="knopf haupt" style="width:100%;justify-content:center">Einrichten</button>
        <p style="color:var(--leise);font-size:12.5px;margin-top:12px">Legt die Tabellen an, richtet deinen Zugang ein und
        übernimmt die drei Pakete von vecom-design.it. Die Zugangsdaten landen ausschließlich in
        <code>app/config.local.php</code> auf deinem Webspace — nie im Repository.</p>
      </form>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
