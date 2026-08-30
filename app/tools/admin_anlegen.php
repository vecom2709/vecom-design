<?php
declare(strict_types=1);
/* Legt einen Admin-Zugang an oder setzt dessen Passwort neu.
   Aufruf:  php app/tools/admin_anlegen.php "Uwe Vetter" kontakt@vecom-design.it
   Das Passwort wird danach abgefragt und erscheint nie in der Befehlszeile
   und nie in der Verlaufsdatei der Shell. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Nur auf der Kommandozeile.'); }
require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/Db.php';

$name  = $argv[1] ?? null;
$email = isset($argv[2]) ? mb_strtolower(trim($argv[2])) : null;
if (!$name || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit("Aufruf: php app/tools/admin_anlegen.php \"Name\" adresse@example.com\n");
}
fwrite(STDERR, "Passwort (Eingabe bleibt sichtbar, Fenster danach schliessen): ");
$pw = trim((string) fgets(STDIN));
if (strlen($pw) < 10) { exit("Zu kurz — mindestens 10 Zeichen.\n"); }

$hash = password_hash($pw, PASSWORD_DEFAULT);
$da = Db::one('SELECT id FROM users WHERE email = ?', [$email]);
if ($da) {
    Db::update('users', (int) $da['id'], ['password_hash' => $hash, 'name' => $name, 'role' => 'admin', 'active' => 1]);
    echo "Passwort gesetzt fuer $email\n";
} else {
    Db::insert('users', ['email' => $email, 'password_hash' => $hash, 'name' => $name, 'role' => 'admin', 'active' => 1]);
    echo "Admin angelegt: $email\n";
}
