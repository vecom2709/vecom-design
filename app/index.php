<?php
declare(strict_types=1);

/* Verteiler der Verwaltungsplattform. Jede Anfrage laeuft hier durch. */

foreach (['Config','Db','Status','Csrf','Auth','Fmt','Events','Kennzahlen'] as $k) {
    require_once __DIR__ . "/src/$k.php";
}

date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));
Auth::start();

$basis = Config::basis();
$pfad  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
if ($basis !== '' && str_starts_with($pfad, $basis)) {
    $pfad = substr($pfad, strlen($basis));
}
$pfad  = trim($pfad, '/');
$teile = $pfad === '' ? [] : explode('/', $pfad);
$route = $teile[0] ?? '';
$id    = isset($teile[1]) && ctype_digit($teile[1]) ? (int) $teile[1] : null;
$unter = $teile[1] ?? null;
$post  = $_SERVER['REQUEST_METHOD'] === 'POST';

function url(string $ziel = ''): string { return Config::basis() . '/' . ltrim($ziel, '/'); }
function weiter(string $ziel): never { header('Location: ' . url($ziel)); exit; }
function ansicht(string $datei, array $daten = []): void {
    // $route steht im aeusseren Gueltigkeitsbereich. Ohne dieses global ist es
    // in layout.php leer — dann steht im Menue immer "Dashboard" hervorgehoben,
    // egal wo man ist, und Formulare im Rahmen wissen nicht, wohin zurueck.
    global $route;
    extract($daten, EXTR_SKIP);
    $inhaltsdatei = __DIR__ . "/views/$datei.php";
    require __DIR__ . '/views/layout.php';
}

/**
 * Eine Abfrage, die auch dann noch eine Seite liefert, wenn die Tabelle
 * dahinter erst mit der naechsten Aktualisierung entsteht. Zwischen Deploy
 * und Klick auf "Jetzt aktualisieren" liegen ein paar Minuten — in denen
 * soll keine Ansicht auf die Nase fallen.
 */
function sicher(callable $fn, mixed $ersatz = []): mixed {
    try { return $fn(); } catch (Throwable $e) { return $ersatz; }
}

/** Ein Textfeld mit einer Angabe je Zeile in eine Liste verwandeln. */
function zeilen(string $text): array {
    return array_values(array_filter(array_map('trim', preg_split('~\R~', $text) ?: [])));
}

/** Baut aus den Formularfeldern die Texte je Sprache fuer die Website. */
function paketTexte(array $post): array {
    $aus = [];
    foreach (['it', 'de', 'en'] as $l) {
        $eintrag = [
            'name'     => trim((string) ($post["t_{$l}_name"] ?? '')),
            'sub'      => trim((string) ($post["t_{$l}_sub"] ?? '')),
            'ideal'    => trim((string) ($post["t_{$l}_ideal"] ?? '')),
            'features' => zeilen((string) ($post["t_{$l}_features"] ?? '')),
        ];
        // Leere Sprachen gar nicht erst speichern — dann greift der Haupttext.
        if ($eintrag['name'] !== '' || $eintrag['sub'] !== '' || $eintrag['ideal'] !== '' || $eintrag['features']) {
            $aus[$l] = $eintrag;
        }
    }
    return $aus;
}

/* ---------- Anmeldung ---------- */
if ($route === 'anmelden') {
    $fehler = null;
    if ($post) {
        Csrf::pruefen();
        if (Auth::anmelden((string) ($_POST['email'] ?? ''), (string) ($_POST['passwort'] ?? ''))) {
            weiter('');
        }
        $fehler = 'E-Mail oder Passwort stimmt nicht.';
    }
    require __DIR__ . '/views/anmelden.php';
    exit;
}
if ($route === 'abmelden') { Auth::abmelden(); weiter('anmelden'); }

Auth::nurAdmin();

/* Die Datenbank bringt sich beim Oeffnen selbst auf Stand: offene
   Aktualisierungen einspielen und, solange noch gar nichts da ist,
   Beispieldaten anlegen. Frueher wartete beides auf einen Knopfdruck —
   das hat nur dafuer gesorgt, dass hochgeladener Code halb arbeitete. */
require_once __DIR__ . '/src/Einrichtung.php';
$einrichtung = Einrichtung::selbsttaetig();
if ($einrichtung['migrationen']) {
    Events::protokoll('system_migration', 'Datenbank von selbst aktualisiert: '
        . implode(', ', $einrichtung['migrationen'])
        . ($einrichtung['texte'] ? ' · Website-Texte bei ' . $einrichtung['texte'] . ' Paket(en) ergänzt' : ''));
    $_SESSION['gut'] = 'Die Datenbank wurde auf den neuesten Stand gebracht ('
        . count($einrichtung['migrationen']) . ' Aktualisierung(en)).';
}
if ($einrichtung['beispiele'] > 0) {
    Events::protokoll('beispieldaten', 'Beispieldaten von selbst angelegt');
    $_SESSION['gut'] = ($_SESSION['gut'] ?? '')
        . ' Weil noch nichts da war, stehen jetzt Beispieldaten drin — oben kannst du sie jederzeit löschen.';
}
if ($einrichtung['fehler'] !== null) {
    $_SESSION['fehler'] = 'Die Datenbank konnte nicht vollständig aktualisiert werden: ' . $einrichtung['fehler'];
}

/* ---------- Lebenszeichen fuer die laufende Aktualisierung ---------- */
if ($route === 'puls') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode([
        'zeit'        => date('c'),
        'meldungen'   => (int) Db::wert('SELECT COUNT(*) FROM notifications WHERE read_at IS NULL'),
        'nachrichten' => (int) Db::wert("SELECT COUNT(*) FROM messages WHERE read_at IS NULL AND sender='kunde'"),
        'bestellungen'=> (int) Db::wert('SELECT COUNT(*) FROM orders'),
        'letzte'      => (int) Db::wert('SELECT COALESCE(MAX(id),0) FROM activities'),
    ]);
    exit;
}

/* ---------- Schreibende Vorgaenge ---------- */
if ($post) {
    // Eine zu grosse Datei verwirft der Server, bevor PHP sie sieht — dann
    // sind $_POST und $_FILES leer und die CSRF-Pruefung schlaegt fehl. Der
    // Grund waere dann falsch benannt.
    require_once __DIR__ . '/src/Ablage.php';
    if (Ablage::zuGrossFuerDenServer()) {
        $_SESSION['fehler'] = 'Die Datei ist größer als ' . Fmt::bytes(Ablage::grenze())
            . ' und wurde vom Server abgewiesen.';
        weiter('');
    }
    Csrf::pruefen();
    $tat = (string) ($_POST['tat'] ?? '');
    try {
        switch ($tat) {
            case 'kunde_speichern':
                $daten = [
                    'name' => trim((string) $_POST['name']), 'email' => mb_strtolower(trim((string) $_POST['email'])),
                    'phone' => trim((string) ($_POST['phone'] ?? '')) ?: null,
                    'company' => trim((string) ($_POST['company'] ?? '')) ?: null,
                    'industry' => trim((string) ($_POST['industry'] ?? '')) ?: null,
                    'street' => trim((string) ($_POST['street'] ?? '')) ?: null,
                    'zip' => trim((string) ($_POST['zip'] ?? '')) ?: null,
                    'city' => trim((string) ($_POST['city'] ?? '')) ?: null,
                    'country' => trim((string) ($_POST['country'] ?? '')) ?: null,
                    'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null,
                ];
                if ($daten['name'] === '' || !filter_var($daten['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Name und eine gültige E-Mail sind Pflicht.');
                }
                $kid = (int) ($_POST['id'] ?? 0);
                if ($kid > 0) {
                    $vorher = Db::one('SELECT * FROM customers WHERE id = ?', [$kid]);
                    Db::update('customers', $kid, $daten);
                    Events::pruefspur('aendern', 'customer', $kid, $vorher ?? [], $daten);
                } else {
                    $kid = Events::kundeFinden($daten);
                }
                weiter('kunden/' . $kid);

            case 'anfrage_bestellung':
                require_once __DIR__ . '/src/Anfrage.php';
                $bid = Anfrage::zuBestellung((int) ($_POST['id'] ?? 0), (int) ($_POST['paket_id'] ?? 0));
                weiter('bestellungen/' . $bid);

            case 'anfrage_status':
                require_once __DIR__ . '/src/Anfrage.php';
                Anfrage::status((int) ($_POST['id'] ?? 0), (string) ($_POST['status'] ?? ''));
                weiter('anfragen/' . (int) ($_POST['id'] ?? 0));

            case 'paket_speichern':
                $daten = [
                    'name' => trim((string) $_POST['name']),
                    'slug' => trim((string) ($_POST['slug'] ?? '')) ?: strtolower(preg_replace('~[^a-z0-9]+~i', '-', (string) $_POST['name'])),
                    'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
                    'price_cents' => (int) round(((float) str_replace(',', '.', (string) $_POST['preis'])) * 100),
                    'monthly_cents' => (int) round(((float) str_replace(',', '.', (string) ($_POST['monat'] ?? '0'))) * 100),
                    'currency' => 'EUR',
                    'pages_count' => trim((string) ($_POST['pages_count'] ?? '')) ?: null,
                    'delivery_days' => trim((string) ($_POST['delivery_days'] ?? '')) ?: null,
                    'seo' => trim((string) ($_POST['seo'] ?? '')) ?: null,
                    'hosting' => trim((string) ($_POST['hosting'] ?? '')) ?: null,
                    'extras' => trim((string) ($_POST['extras'] ?? '')) ?: null,
                    'features' => json_encode(zeilen((string) ($_POST['features'] ?? '')), JSON_UNESCAPED_UNICODE),
                    'sub' => trim((string) ($_POST['sub'] ?? '')) ?: null,
                    'ideal' => trim((string) ($_POST['ideal'] ?? '')) ?: null,
                    'detail_url' => trim((string) ($_POST['detail_url'] ?? '')) ?: null,
                    'texte' => json_encode(paketTexte($_POST), JSON_UNESCAPED_UNICODE),
                    'active' => isset($_POST['active']) ? 1 : 0,
                    'oeffentlich' => isset($_POST['oeffentlich']) ? 1 : 0,
                    'direktkauf' => isset($_POST['direktkauf']) ? 1 : 0,
                    'popular' => isset($_POST['popular']) ? 1 : 0,
                    'sort' => (int) ($_POST['sort'] ?? 0),
                ];
                if ($daten['name'] === '') { throw new RuntimeException('Der Name fehlt.'); }
                $pid = (int) ($_POST['id'] ?? 0);
                if ($pid > 0) { Db::update('packages', $pid, $daten); }
                else { $pid = Db::insert('packages', $daten); }
                Events::pruefspur($pid ? 'speichern' : 'anlegen', 'package', $pid, [], ['name' => $daten['name']]);
                weiter('pakete');

            case 'paket_loeschen':
                $pid = (int) $_POST['id'];
                // Beispielbestellungen zaehlen hier nicht: Sie sollen ein Paket
                // nicht festhalten, das Uwe wieder loswerden will.
                $benutzt = (int) sicher(static fn() => Db::wert('SELECT COUNT(*) FROM orders WHERE package_id = ? AND demo = 0', [$pid]),
                    Db::wert('SELECT COUNT(*) FROM orders WHERE package_id = ?', [$pid]));
                if ($benutzt > 0) { throw new RuntimeException("Das Paket hängt an $benutzt Bestellung(en) und wird deshalb nicht gelöscht. Deaktiviere es stattdessen."); }
                Db::run('DELETE FROM packages WHERE id = ?', [$pid]);
                Events::pruefspur('loeschen', 'package', $pid);
                weiter('pakete');

            case 'bestellung_anlegen':
                $bid = Events::bestellungAnlegen((int) $_POST['customer_id'], (int) $_POST['package_id'],
                    trim((string) ($_POST['notes'] ?? '')) ?: null);
                weiter('bestellungen/' . $bid);

            case 'bestellung_status':
                Events::bestellungStatus((int) $_POST['id'], (string) $_POST['status']);
                weiter('bestellungen/' . (int) $_POST['id']);

            case 'zahlung_bestaetigen':
                Events::zahlungBestaetigen((int) $_POST['id'], trim((string) ($_POST['referenz'] ?? '')) ?: null,
                    (string) ($_POST['anbieter'] ?? 'manuell'));
                weiter('bestellungen/' . (int) $_POST['order_id']);

            case 'zahlungslink':
                require_once __DIR__ . '/src/Zahlung/Anbieter.php';
                require_once __DIR__ . '/src/Zahlung/Stripe.php';
                $z = Db::one('SELECT * FROM payments WHERE id = ?', [(int) $_POST['id']]);
                if (!$z) { throw new RuntimeException('Zahlung nicht gefunden.'); }
                if ($z['status'] === 'bezahlt') { throw new RuntimeException('Diese Rate ist bereits bezahlt.'); }
                $b = Db::one('SELECT * FROM orders WHERE id = ?', [(int) $z['order_id']]);
                $k = Db::one('SELECT * FROM customers WHERE id = ?', [(int) $b['customer_id']]);
                $stripe = new StripeAnbieter();
                $url = $stripe->bezahlseite($z, $b, $k);
                Db::update('payments', (int) $z['id'], [
                    'provider' => 'stripe', 'status' => 'in_bearbeitung',
                    'link_url' => $url, 'link_bis' => date('Y-m-d H:i:s', strtotime('+24 hours')),
                ]);
                Events::protokoll('zahlungslink', 'Zahlungslink erstellt: ' . ($z['bezeichnung'] ?: 'Zahlung')
                    . ' · ' . Fmt::geld((int) $z['amount_cents']), (int) $b['customer_id'], (int) $b['id']);
                weiter('bestellungen/' . (int) $b['id']);

            case 'zahlung_fehler':
                Events::zahlungFehlgeschlagen((int) $_POST['id'], trim((string) ($_POST['grund'] ?? '')));
                weiter('bestellungen/' . (int) $_POST['order_id']);

            case 'projekt_status':
                Events::projektStatus((int) $_POST['id'], (string) $_POST['status']);
                weiter('projekte/' . (int) $_POST['id']);

            case 'projekt_felder':
                $pid = (int) $_POST['id'];
                $daten = [
                    'deadline' => ($_POST['deadline'] ?? '') !== '' ? (string) $_POST['deadline'] : null,
                    'priority' => (string) ($_POST['priority'] ?? 'normal'),
                    'preview_url' => trim((string) ($_POST['preview_url'] ?? '')) ?: null,
                ];
                Db::update('projects', $pid, $daten);
                Events::pruefspur('aendern', 'project', $pid, [], $daten);
                weiter('projekte/' . $pid);

            case 'stripe_speichern':
                require_once __DIR__ . '/src/Einrichtung.php';
                $alt = Config::all();
                $bisher = (array) ($alt['stripe'] ?? []);
                $modus  = ($_POST['modus'] ?? 'test') === 'live' ? 'live' : 'test';
                $geheim = trim((string) ($_POST['geheim'] ?? ''));
                $whsec  = trim((string) ($_POST['webhook_geheim'] ?? ''));

                // Leer gelassene Felder behalten ihren bisherigen Wert — so laesst
                // sich der Modus umstellen, ohne die Schluessel neu einzutippen.
                if ($geheim === '') { $geheim = (string) ($bisher['geheim'] ?? ''); }
                if ($whsec === '')  { $whsec  = (string) ($bisher['webhook_geheim'] ?? ''); }

                if ($geheim !== '' && !preg_match('~^(sk|rk)_(test|live)_~', $geheim)) {
                    throw new RuntimeException('Das sieht nicht nach einem geheimen Stripe-Schlüssel aus (er beginnt mit sk_test_ oder sk_live_).');
                }
                if ($whsec !== '' && !str_starts_with($whsec, 'whsec_')) {
                    throw new RuntimeException('Das Webhook-Geheimnis beginnt mit whsec_.');
                }
                if ($geheim !== '' && $modus === 'live' && str_contains($geheim, '_test_')) {
                    throw new RuntimeException('Livemodus gewählt, aber der Schlüssel ist ein Testschlüssel.');
                }
                if ($geheim !== '' && $modus === 'test' && str_contains($geheim, '_live_')) {
                    throw new RuntimeException('Testmodus gewählt, aber der Schlüssel ist ein Liveschlüssel. Im Testmodus fließt kein echtes Geld — das ist Absicht.');
                }

                $alt['stripe'] = ['modus' => $modus, 'geheim' => $geheim, 'webhook_geheim' => $whsec]
                    + array_diff_key($bisher, array_flip(['modus', 'geheim', 'webhook_geheim']));
                if (!Einrichtung::konfigSchreiben(dirname(__DIR__) . '/app/config.local.php', $alt)) {
                    throw new RuntimeException('app/config.local.php konnte nicht geschrieben werden.');
                }
                Db::run("UPDATE integrations SET status=?, last_error=NULL WHERE ikey='stripe'",
                    [$geheim !== '' && $whsec !== '' ? 'verbunden' : 'nicht_verbunden']);
                // Die Schluessel selbst tauchen nirgends im Protokoll auf.
                Events::protokoll('integration', 'Stripe-Zugangsdaten gespeichert (Modus: ' . $modus . ')');
                Events::pruefspur('speichern', 'integration', null, [], ['dienst' => 'stripe', 'modus' => $modus]);
                weiter('integrationen');

            case 'direktkauf_test':
                $an = isset($_POST['an']) ? '1' : '0';
                Db::run("INSERT INTO settings (skey, svalue) VALUES ('direktkauf_test', ?)
                         ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)", [$an]);
                Events::protokoll('integration', $an === '1'
                    ? 'Kaufknopf auch im Testmodus sichtbar geschaltet'
                    : 'Kaufknopf im Testmodus wieder ausgeblendet');
                weiter('integrationen');

            case 'migrieren':
                require_once __DIR__ . '/src/Einrichtung.php';
                $neu = Einrichtung::migrieren();
                $ergaenzt = $neu ? Einrichtung::texteNachtragen() : 0;
                Events::protokoll('system_migration', $neu
                    ? 'Datenbank aktualisiert: ' . implode(', ', $neu)
                      . ($ergaenzt ? " · Website-Texte bei $ergaenzt Paket(en) ergänzt" : '')
                    : 'Datenbank war bereits aktuell');
                weiter($_POST['zurueck'] ?? '');

            case 'fragebogen_einladen':
                require_once __DIR__ . '/src/Onboarding.php';
                $pid = (int) $_POST['id'];
                if (!Onboarding::einladen($pid, true)) {
                    throw new RuntimeException('Die Einladung ging nicht raus. Steht der Brevo-Schlüssel? Ist der Fragebogen schon abgeschlossen?');
                }
                $_SESSION['gut'] = 'Fragebogen verschickt.';
                weiter('projekte/' . $pid);

            case 'rechnung_erzeugen':
                require_once __DIR__ . '/src/Rechnung.php';
                $zid = (int) $_POST['id'];
                $neu = Rechnung::ausZahlung($zid);
                $_SESSION['gut'] = $neu !== null
                    ? Rechnung::bezeichnung() . ' erstellt.'
                    : 'Dazu gibt es schon einen Beleg — oder die Zahlung ist nicht als bezahlt gebucht.';
                weiter($_POST['zurueck'] ?? 'rechnungen');

            case 'rechnung_schicken':
                require_once __DIR__ . '/src/Rechnung.php';
                require_once __DIR__ . '/src/Mail.php';
                $r = Db::one('SELECT * FROM invoices WHERE id = ?', [(int) $_POST['id']]);
                if (!$r) { throw new RuntimeException('Beleg nicht gefunden.'); }
                $k = Db::one('SELECT * FROM customers WHERE id = ?', [(int) $r['customer_id']]);
                $ziel = rtrim((string) Config::get('website', ''), '/');
                $wort = Rechnung::bezeichnung();
                $ok = Mail::senden('rechnung', (string) $k['email'],
                    $wort . ' ' . $r['invoice_no'],
                    "Hallo " . $k['name'] . ",\n\nanbei der " . $wort . ' ' . $r['invoice_no']
                    . ' über ' . Fmt::geld((int) $r['total_cents'], (string) $r['currency']) . ".\n\n"
                    . "Zum Herunterladen:\n" . $ziel . Config::basis() . '/rechnungen/' . (int) $r['id'] . "/pdf\n\n"
                    . "Herzliche Grüße\nUwe Vetter · Vecom Design\n",
                    ['customer_id' => (int) $r['customer_id'],
                     'order_id' => $r['order_id'] !== null ? (int) $r['order_id'] : null,
                     'antwortAn' => Mail::eigeneAdresse()]);
                if ($ok) { Db::update('invoices', (int) $r['id'], ['sent_at' => date('Y-m-d H:i:s')]); }
                $_SESSION['gut'] = $ok ? 'Verschickt.' : 'Der Versand hat nicht geklappt — siehe Nachrichten.';
                weiter('rechnungen/' . (int) $r['id']);

            case 'cockpit_schuetzen':
                require_once __DIR__ . '/src/Cockpit.php';
                $e = Cockpit::einrichten(trim((string) ($_POST['benutzer'] ?? 'uwe')) ?: 'uwe');
                if (!$e['ok']) { throw new RuntimeException((string) $e['grund']); }
                // Genau einmal anzeigen, danach ist es weg. Im Protokoll steht
                // nur, DASS es passiert ist — nie das Passwort.
                $_SESSION['cockpit_zugang'] = ['benutzer' => $e['benutzer'], 'passwort' => $e['passwort']];
                Events::protokoll('cockpit', 'Passwortschutz für /cockpit/ eingerichtet (Benutzer '
                    . $e['benutzer'] . ', Verfahren ' . $e['verfahren'] . ')');
                if ($e['bestaetigt']) {
                    $_SESSION['gut'] = 'Das Cockpit ist geschützt. Das Passwort steht unten — schreib es dir auf.';
                } else {
                    $_SESSION['fehler'] = (string) $e['grund'];
                    $_SESSION['gut'] = 'Das Passwort steht unten — schreib es dir auf.';
                }
                weiter('einstellungen');

            case 'cockpit_frei':
                require_once __DIR__ . '/src/Cockpit.php';
                Cockpit::entfernen();
                Events::protokoll('cockpit', 'Passwortschutz für /cockpit/ entfernt');
                $_SESSION['gut'] = 'Der Schutz ist weg — das Cockpit ist wieder offen.';
                weiter('einstellungen');

            case 'passwort_aendern':
                // Das eigene Passwort. Das alte muss stimmen — sonst koennte
                // jemand an einem offen stehenden Rechner den Zugang uebernehmen.
                $alt  = (string) ($_POST['alt'] ?? '');
                $neu1 = (string) ($_POST['neu'] ?? '');
                $neu2 = (string) ($_POST['neu2'] ?? '');
                $ich  = Db::one('SELECT * FROM users WHERE id = ?', [Auth::id()]);
                if (!$ich || !password_verify($alt, (string) $ich['password_hash'])) {
                    throw new RuntimeException('Das bisherige Passwort stimmt nicht.');
                }
                if (mb_strlen($neu1) < 10) {
                    throw new RuntimeException('Das neue Passwort braucht mindestens zehn Zeichen.');
                }
                if ($neu1 !== $neu2) {
                    throw new RuntimeException('Die beiden neuen Passwörter sind nicht gleich.');
                }
                Db::update('users', (int) $ich['id'], ['password_hash' => password_hash($neu1, PASSWORD_DEFAULT)]);
                Events::pruefspur('passwort', 'user', (int) $ich['id']);
                $_SESSION['gut'] = 'Passwort geändert.';
                weiter('einstellungen');

            case 'zugang_anlegen':
                $name  = trim((string) ($_POST['name'] ?? ''));
                $mail  = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
                $pass  = (string) ($_POST['passwort'] ?? '');
                if ($name === '' || !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Name und eine gültige E-Mail sind Pflicht.');
                }
                if (mb_strlen($pass) < 10) {
                    throw new RuntimeException('Das Passwort braucht mindestens zehn Zeichen.');
                }
                if (Db::one('SELECT id FROM users WHERE email = ?', [$mail])) {
                    throw new RuntimeException('Diese Adresse hat schon einen Zugang.');
                }
                $uid = Db::insert('users', [
                    'email' => $mail, 'password_hash' => password_hash($pass, PASSWORD_DEFAULT),
                    'name' => $name, 'role' => 'admin', 'active' => 1,
                ]);
                Events::protokoll('zugang', 'Zugang angelegt: ' . $name);
                Events::pruefspur('anlegen', 'user', $uid, [], ['email' => $mail]);
                $_SESSION['gut'] = 'Zugang angelegt.';
                weiter('einstellungen');

            case 'zugang_umschalten':
                $uid = (int) $_POST['id'];
                if ($uid === Auth::id()) {
                    throw new RuntimeException('Den eigenen Zugang kannst du nicht abschalten.');
                }
                $u = Db::one('SELECT * FROM users WHERE id = ?', [$uid]);
                if (!$u) { throw new RuntimeException('Zugang nicht gefunden.'); }
                $an = (int) $u['active'] === 1 ? 0 : 1;
                if ($an === 0 && (int) Db::wert("SELECT COUNT(*) FROM users WHERE active = 1 AND role = 'admin'") <= 1) {
                    throw new RuntimeException('Das ist der letzte aktive Zugang — der bleibt an.');
                }
                Db::update('users', $uid, ['active' => $an]);
                Events::pruefspur($an ? 'aktivieren' : 'abschalten', 'user', $uid);
                $_SESSION['gut'] = $an ? 'Zugang wieder aktiv.' : 'Zugang abgeschaltet.';
                weiter('einstellungen');

            case 'firma_speichern':
                require_once __DIR__ . '/src/Firma.php';
                Firma::speichern($_POST);
                Events::protokoll('einstellungen', 'Firmendaten gespeichert');
                $_SESSION['gut'] = 'Firmendaten gespeichert.';
                weiter('einstellungen');

            case 'restzahlung_anfordern':
                require_once __DIR__ . '/src/Nachricht.php';
                $bid = (int) $_POST['id'];
                $pr = Db::one('SELECT id FROM projects WHERE order_id = ?', [$bid]);
                if (!$pr) { throw new RuntimeException('Zu dieser Bestellung gibt es kein Projekt.'); }
                $_SESSION['gut'] = Nachricht::restzahlungAnfordern((int) $pr['id'])
                    ? 'Die Restzahlung ist angefordert — der Kunde hat die E-Mail mit dem Zahlungslink.'
                    : 'Nichts zu tun: Entweder ist nichts mehr offen, oder die Anforderung ging schon raus.';
                weiter('bestellungen/' . $bid);

            case 'nachricht_senden':
                require_once __DIR__ . '/src/Nachricht.php';
                $pid = (int) $_POST['id'];
                Nachricht::schreiben($pid, (string) ($_POST['text'] ?? ''), 'admin');
                $_SESSION['gut'] = 'Nachricht ist raus — der Kunde bekommt sie auch per E-Mail.';
                weiter('projekte/' . $pid);

            case 'datei_hoch':
                require_once __DIR__ . '/src/Ablage.php';
                $pid = (int) $_POST['id'];
                $pr = Db::one('SELECT * FROM projects WHERE id = ?', [$pid]);
                if (!$pr) { throw new RuntimeException('Projekt nicht gefunden.'); }
                Ablage::annehmen($_FILES['datei'] ?? [], $pid, (int) $pr['customer_id'], 'admin');
                Events::protokoll('datei_hoch', 'Datei hinterlegt: ' . ($_FILES['datei']['name'] ?? ''),
                    (int) $pr['customer_id'], $pr['order_id'] !== null ? (int) $pr['order_id'] : null, $pid);
                $_SESSION['gut'] = 'Datei liegt beim Projekt — der Kunde sieht sie auf seiner Seite.';
                weiter('projekte/' . $pid);

            case 'datei_weg':
                require_once __DIR__ . '/src/Ablage.php';
                $did = (int) $_POST['id'];
                $d = Db::one('SELECT * FROM files WHERE id = ?', [$did]);
                if (!$d) { throw new RuntimeException('Datei nicht gefunden.'); }
                Ablage::loeschen($did);
                Events::protokoll('datei_weg', 'Datei gelöscht: ' . $d['orig_name'],
                    $d['customer_id'] !== null ? (int) $d['customer_id'] : null, null,
                    $d['project_id'] !== null ? (int) $d['project_id'] : null);
                $_SESSION['gut'] = 'Datei gelöscht.';
                weiter('projekte/' . (int) $d['project_id']);

            case 'website_speichern':
                require_once __DIR__ . '/src/Monitoring.php';
                $pid = (int) $_POST['project_id'];
                $pr = Db::one('SELECT * FROM projects WHERE id = ?', [$pid]);
                if (!$pr) { throw new RuntimeException('Projekt nicht gefunden.'); }

                $domain = strtolower(trim((string) ($_POST['domain'] ?? '')));
                $domain = preg_replace('~^https?://~', '', $domain);
                $domain = rtrim((string) $domain, '/');
                if ($domain === '' || !preg_match('~^[a-z0-9.-]+\.[a-z]{2,}$~', $domain)) {
                    throw new RuntimeException('Bitte eine Domain wie beispiel.it eintragen — ohne https:// davor.');
                }
                $url = trim((string) ($_POST['url'] ?? '')) ?: 'https://' . $domain;

                $daten = [
                    'domain' => $domain, 'url' => $url,
                    'monitoring' => isset($_POST['monitoring']) ? 1 : 0,
                ];
                $w = Db::one('SELECT * FROM websites WHERE project_id = ?', [$pid]);
                if ($w) {
                    Db::update('websites', (int) $w['id'], $daten);
                } else {
                    $daten += ['project_id' => $pid, 'customer_id' => (int) $pr['customer_id'],
                               'status' => 'nicht_veroeffentlicht'];
                    Db::insert('websites', $daten);
                }
                Events::protokoll('website_gespeichert', 'Website hinterlegt: ' . $domain,
                    (int) $pr['customer_id'], $pr['order_id'] !== null ? (int) $pr['order_id'] : null, $pid);
                $_SESSION['gut'] = 'Website gespeichert.';
                weiter('projekte/' . $pid);

            case 'website_pruefen':
                require_once __DIR__ . '/src/Monitoring.php';
                $wid = (int) $_POST['id'];
                $e = Monitoring::eine($wid);
                if ($e === null) { throw new RuntimeException('Website nicht gefunden.'); }
                $_SESSION['gut'] = $e['ok']
                    ? 'Erreichbar — ' . (int) $e['pruefung']['ms'] . ' ms, Status ' . (int) $e['pruefung']['status'] . '.'
                    : 'Nicht erreichbar: ' . ($e['pruefung']['fehler'] ?? 'unbekannter Grund');
                weiter($_POST['zurueck'] ?? 'monitoring');

            case 'cron_jetzt':
                require_once __DIR__ . '/src/Cron.php';
                $b = Cron::laufen(true);
                $_SESSION['gut'] = 'Lauf erledigt: ' . json_encode($b, JSON_UNESCAPED_UNICODE);
                weiter('monitoring');

            case 'aufgabe_anlegen':
                $pid = (int) $_POST['id'];
                $titel = trim((string) ($_POST['titel'] ?? ''));
                if ($titel === '') { throw new RuntimeException('Die Aufgabe braucht einen Namen.'); }
                Db::insert('tasks', [
                    'project_id' => $pid, 'title' => mb_substr($titel, 0, 255),
                    'due_date' => ($_POST['due_date'] ?? '') !== '' ? (string) $_POST['due_date'] : null,
                    'sort' => (int) Db::wert('SELECT COALESCE(MAX(sort),0)+1 FROM tasks WHERE project_id = ?', [$pid]),
                ]);
                weiter('projekte/' . $pid);

            case 'aufgabe_weg':
                $aid = (int) $_POST['id'];
                $a = Db::one('SELECT project_id FROM tasks WHERE id = ?', [$aid]);
                if (!$a) { throw new RuntimeException('Aufgabe nicht gefunden.'); }
                Db::run('DELETE FROM tasks WHERE id = ?', [$aid]);
                weiter('projekte/' . (int) $a['project_id']);

            case 'aufgaben_vorlage':
                // Bei jedem Webdesign-Projekt sind es dieselben Schritte. Sie
                // von Hand zwoelfmal einzutippen ist verlorene Zeit.
                $pid = (int) $_POST['id'];
                $vorhanden = array_column(Db::all('SELECT title FROM tasks WHERE project_id = ?', [$pid]), 'title');
                $n = (int) Db::wert('SELECT COALESCE(MAX(sort),0) FROM tasks WHERE project_id = ?', [$pid]);
                $zahl = 0;
                foreach ([
                    'Fragebogen auswerten',
                    'Struktur und Seitenaufbau abstimmen',
                    'Entwurf Startseite',
                    'Unterseiten umsetzen',
                    'Texte einpflegen',
                    'Bilder aufbereiten und einbinden',
                    'Auf dem Handy prüfen',
                    'SEO-Grundlagen setzen',
                    'Vorschau an den Kunden schicken',
                    'Änderungen einarbeiten',
                    'Domain und SSL prüfen',
                    'Veröffentlichen und übergeben',
                ] as $titel) {
                    if (in_array($titel, $vorhanden, true)) { continue; }
                    Db::insert('tasks', ['project_id' => $pid, 'title' => $titel, 'sort' => ++$n]);
                    $zahl++;
                }
                $_SESSION['gut'] = $zahl > 0 ? "$zahl Aufgaben eingefügt." : 'Die Vorlage steht schon vollständig da.';
                weiter('projekte/' . $pid);

            case 'aufgabe_umschalten':
                $aid = (int) $_POST['id'];
                $a = Db::one('SELECT * FROM tasks WHERE id = ?', [$aid]);
                if (!$a) { throw new RuntimeException('Aufgabe nicht gefunden.'); }
                Db::update('tasks', $aid, ['done' => (int) $a['done'] === 1 ? 0 : 1]);
                weiter('projekte/' . (int) $a['project_id']);

            case 'nachrichten_gelesen':
                $pid = (int) $_POST['id'];
                Db::run("UPDATE messages SET read_at = NOW() WHERE project_id = ? AND read_at IS NULL AND sender = 'kunde'", [$pid]);
                weiter('projekte/' . $pid);

            case 'beispiel_anlegen':
                require_once __DIR__ . '/src/Beispieldaten.php';
                $wieviele = Beispieldaten::anlegen();
                $_SESSION['gut'] = $wieviele > 0
                    ? "Beispieldaten angelegt: $wieviele Vorgänge in drei Sprachen. Sie verschwinden von allein, sobald die erste echte Bestellung kommt."
                    : 'Es waren schon Beispieldaten da.';
                Events::protokoll('beispieldaten', 'Beispieldaten angelegt');
                weiter('einstellungen');

            case 'beispiel_loeschen':
                require_once __DIR__ . '/src/Beispieldaten.php';
                $zeilen = Beispieldaten::entfernen();
                $_SESSION['gut'] = "Beispieldaten entfernt ($zeilen Einträge). Echte Daten wurden nicht angerührt.";
                Events::protokoll('beispieldaten', 'Beispieldaten von Hand entfernt');
                weiter($_POST['zurueck'] ?? 'einstellungen');

            case 'fragebogen_link':
                // Nur den Zugang erzeugen — zum Weitergeben ueber WhatsApp
                // oder am Telefon, ohne den Umweg ueber eine E-Mail.
                require_once __DIR__ . '/src/Onboarding.php';
                $pid = (int) $_POST['id'];
                $fb = Db::one('SELECT id FROM questionnaires WHERE project_id = ?', [$pid]);
                if (!$fb) { throw new RuntimeException('Zu diesem Projekt gibt es keinen Fragebogen.'); }
                Onboarding::token((int) $fb['id']);
                $_SESSION['gut'] = 'Der Zugangslink steht jetzt unten und lässt sich kopieren.';
                weiter('projekte/' . $pid);

            case 'fragebogen_erinnern':
                require_once __DIR__ . '/src/Onboarding.php';
                $anzahl = Onboarding::erinnerungen((int) ($_POST['tage'] ?? Onboarding::ERINNERUNG_NACH_TAGEN));
                $_SESSION['gut'] = $anzahl === 0
                    ? 'Es war keine Erinnerung fällig.'
                    : "Erinnerungen verschickt: $anzahl.";
                weiter('onboarding');

            case 'meldungen_gelesen':
                Db::run('UPDATE notifications SET read_at = NOW() WHERE read_at IS NULL');
                weiter('benachrichtigungen');
        }
        throw new RuntimeException('Unbekannter Vorgang.');
    } catch (Throwable $e) {
        $_SESSION['fehler'] = $e->getMessage();
        weiter($_POST['zurueck'] ?? '');
    }
}

/* ---------- Ansichten ---------- */
switch ($route) {
    case '':
    case 'dashboard':
        ansicht('dashboard', [
            'z' => Kennzahlen::alle(),
            'verlauf' => Kennzahlen::umsatzverlauf(),
            'pakete' => Kennzahlen::beliebtestePakete(),
            'aktivitaeten' => Kennzahlen::letzteAktivitaeten(),
            'deadlines' => Kennzahlen::naheDeadlines(),
            'meldungen' => Kennzahlen::meldungen(),
        ]);
        break;

    case 'kunden':
        if ($unter === 'neu') { ansicht('kunde_form', ['k' => null]); break; }
        if ($id !== null) {
            $k = Db::one('SELECT * FROM customers WHERE id = ?', [$id]);
            if (!$k) { http_response_code(404); exit('Kunde nicht gefunden.'); }
            if (($teile[2] ?? '') === 'bearbeiten') { ansicht('kunde_form', ['k' => $k]); break; }
            ansicht('kunde', [
                'k' => $k,
                'bestellungen' => Db::all('SELECT * FROM orders WHERE customer_id = ? ORDER BY id DESC', [$id]),
                'projekte' => Db::all('SELECT * FROM projects WHERE customer_id = ? ORDER BY id DESC', [$id]),
                'zahlungen' => Db::all('SELECT p.*, o.order_no FROM payments p JOIN orders o ON o.id = p.order_id WHERE o.customer_id = ? ORDER BY p.id DESC', [$id]),
                'aktivitaeten' => Db::all('SELECT * FROM activities WHERE customer_id = ? ORDER BY id DESC LIMIT 20', [$id]),
            ]);
            break;
        }
        $q = trim((string) ($_GET['q'] ?? ''));
        $wo = $q !== '' ? 'WHERE c.name LIKE :q OR c.email LIKE :q OR c.company LIKE :q' : '';
        ansicht('kunden', ['q' => $q, 'liste' => Db::all(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) AS bestellungen,
                    (SELECT COUNT(*) FROM projects p WHERE p.customer_id = c.id) AS projekte
             FROM customers c $wo ORDER BY c.created_at DESC",
            $q !== '' ? ['q' => "%$q%"] : [])]);
        break;

    case 'pakete':
        if ($unter === 'neu') { ansicht('paket_form', ['p' => null]); break; }
        if ($id !== null) {
            $p = Db::one('SELECT * FROM packages WHERE id = ?', [$id]);
            if (!$p) { http_response_code(404); exit('Paket nicht gefunden.'); }
            ansicht('paket_form', ['p' => $p]);
            break;
        }
        ansicht('pakete', ['liste' => Db::all(
            'SELECT p.*, (SELECT COUNT(*) FROM orders o WHERE o.package_id = p.id) AS bestellungen
             FROM packages p ORDER BY p.sort, p.price_cents')]);
        break;

    case 'bestellungen':
        if ($unter === 'neu') {
            ansicht('bestellung_form', [
                'kunden' => Db::all('SELECT id, name, company, email FROM customers ORDER BY name'),
                'pakete' => Db::all('SELECT * FROM packages WHERE active = 1 ORDER BY sort, price_cents'),
            ]);
            break;
        }
        if ($id !== null) {
            $b = Db::one('SELECT o.*, c.name AS kunde, c.email AS kunde_email FROM orders o JOIN customers c ON c.id = o.customer_id WHERE o.id = ?', [$id]);
            if (!$b) { http_response_code(404); exit('Bestellung nicht gefunden.'); }
            ansicht('bestellung', [
                'b' => $b,
                'zahlungen' => Db::all('SELECT * FROM payments WHERE order_id = ? ORDER BY id', [$id]),
                'projekt' => Db::one('SELECT * FROM projects WHERE order_id = ?', [$id]),
                'aktivitaeten' => Db::all('SELECT * FROM activities WHERE order_id = ? ORDER BY id DESC', [$id]),
            ]);
            break;
        }
        $q = trim((string) ($_GET['q'] ?? ''));
        $st = (string) ($_GET['status'] ?? '');
        $sort = in_array($_GET['sort'] ?? '', ['datum', 'betrag', 'kunde'], true) ? $_GET['sort'] : 'datum';
        $bed = []; $args = [];
        if ($q !== '')  { $bed[] = '(o.order_no LIKE :q OR c.name LIKE :q OR o.package_name LIKE :q)'; $args['q'] = "%$q%"; }
        if ($st !== '') { $bed[] = 'o.status = :st'; $args['st'] = $st; }
        $wo = $bed ? 'WHERE ' . implode(' AND ', $bed) : '';
        $ord = ['datum' => 'o.ordered_at DESC', 'betrag' => 'o.price_cents DESC', 'kunde' => 'c.name ASC'][$sort];
        ansicht('bestellungen', ['q' => $q, 'st' => $st, 'sort' => $sort, 'liste' => Db::all(
            "SELECT o.*, c.name AS kunde,
                    (SELECT status FROM payments p WHERE p.order_id = o.id ORDER BY p.id DESC LIMIT 1) AS zahlstatus,
                    (SELECT id FROM projects pr WHERE pr.order_id = o.id) AS projekt_id
             FROM orders o JOIN customers c ON c.id = o.customer_id $wo ORDER BY $ord", $args)]);
        break;

    case 'projekte':
        require_once __DIR__ . '/src/Onboarding.php';
        if ($id !== null) {
            $p = Db::one('SELECT p.*, c.name AS kunde, c.email AS kunde_email, o.order_no
                          FROM projects p JOIN customers c ON c.id = p.customer_id
                          LEFT JOIN orders o ON o.id = p.order_id WHERE p.id = ?', [$id]);
            if (!$p) { http_response_code(404); exit('Projekt nicht gefunden.'); }
            ansicht('projekt', [
                'p' => $p,
                'website' => Db::one('SELECT * FROM websites WHERE project_id = ?', [$id]),
                'fragebogen' => Db::one('SELECT * FROM questionnaires WHERE project_id = ?', [$id]),
                'mails' => sicher(static fn() => Db::all('SELECT * FROM mails WHERE project_id = ? ORDER BY id DESC LIMIT 12', [$id])),
                'aufgaben' => Db::all('SELECT * FROM tasks WHERE project_id = ? ORDER BY sort, id', [$id]),
                'nachrichten' => Db::all('SELECT * FROM messages WHERE project_id = ? ORDER BY created_at, id', [$id]),
                'kundenlink' => sicher(static function () use ($id) {
                    require_once __DIR__ . '/src/Nachricht.php';
                    return Nachricht::link($id);
                }, null),
                'dateien' => sicher(static fn() => Db::all(
                    'SELECT * FROM files WHERE project_id = ? ORDER BY id DESC', [$id])),
                'pruefungen' => sicher(static fn() => Db::all(
                    'SELECT c.* FROM website_checks c JOIN websites w ON w.id = c.website_id
                     WHERE w.project_id = ? ORDER BY c.id DESC LIMIT 8', [$id])),
                'aktivitaeten' => Db::all('SELECT * FROM activities WHERE project_id = ? ORDER BY id DESC', [$id]),
            ]);
            break;
        }
        $st = (string) ($_GET['status'] ?? '');
        $wo = $st !== '' ? 'WHERE p.status = :st' : '';
        ansicht('projekte', ['st' => $st, 'liste' => Db::all(
            "SELECT p.*, c.name AS kunde, w.status AS website_status
             FROM projects p JOIN customers c ON c.id = p.customer_id
             LEFT JOIN websites w ON w.project_id = p.id $wo
             ORDER BY FIELD(p.status,'abgeschlossen') ASC, p.deadline IS NULL, p.deadline ASC",
            $st !== '' ? ['st' => $st] : [])]);
        break;

    case 'dateien':
        require_once __DIR__ . '/src/Ablage.php';
        if ($id !== null) {
            $d = Db::one('SELECT * FROM files WHERE id = ?', [$id]);
            if (!$d) { http_response_code(404); exit('Datei nicht gefunden.'); }
            Ablage::ausliefern($d);
        }
        ansicht('dateien', ['liste' => sicher(static fn() => Db::all(
            "SELECT f.*, c.name AS kunde, c.company AS firma, p.name AS projekt
             FROM files f
             LEFT JOIN customers c ON c.id = f.customer_id
             LEFT JOIN projects  p ON p.id = f.project_id
             ORDER BY f.id DESC LIMIT 200")),
            'bereit' => Ablage::bereit()]);
        break;

    case 'anfragen':
        require_once __DIR__ . '/src/Anfrage.php';
        if ($id !== null) {
            $a = Db::one('SELECT * FROM anfragen WHERE id = ?', [$id]);
            if (!$a) { http_response_code(404); exit('Anfrage nicht gefunden.'); }
            ansicht('anfrage', ['a' => $a, 'pakete' => Db::all(
                'SELECT id, name, price_cents, currency FROM packages WHERE active = 1 ORDER BY sort, price_cents')]);
            break;
        }
        ansicht('anfragen', ['liste' => Db::all(
            'SELECT * FROM anfragen ORDER BY created_at DESC LIMIT 200')]);
        break;

    case 'nachrichten':
        ansicht('nachrichten', ['liste' => sicher(static fn() => Db::all(
            "SELECT m.*, c.name AS kunde, c.company AS firma, p.name AS projekt
             FROM messages m
             JOIN customers c ON c.id = m.customer_id
             LEFT JOIN projects p ON p.id = m.project_id
             ORDER BY m.read_at IS NULL DESC, m.id DESC LIMIT 200"))]);
        break;

    case 'aktivitaeten':
        ansicht('aktivitaeten', ['liste' => Db::all(
            'SELECT a.*, c.name AS kunde FROM activities a LEFT JOIN customers c ON c.id = a.customer_id
             ORDER BY a.id DESC LIMIT 200')]);
        break;

    case 'benachrichtigungen':
        ansicht('benachrichtigungen', ['liste' => Db::all('SELECT * FROM notifications ORDER BY id DESC LIMIT 200')]);
        break;

    case 'suche':
        $q = trim((string) ($_GET['q'] ?? ''));
        $t = "%$q%";
        ansicht('suche', ['q' => $q, 'treffer' => $q === '' ? [] : [
            'Kunden' => Db::all('SELECT id, name AS titel, email AS neben FROM customers WHERE name LIKE ? OR email LIKE ? OR company LIKE ? LIMIT 10', [$t,$t,$t]),
            'Bestellungen' => Db::all('SELECT id, order_no AS titel, package_name AS neben FROM orders WHERE order_no LIKE ? OR package_name LIKE ? LIMIT 10', [$t,$t]),
            'Projekte' => Db::all('SELECT id, name AS titel, status AS neben FROM projects WHERE name LIKE ? LIMIT 10', [$t]),
            'Websites' => Db::all('SELECT id, domain AS titel, status AS neben FROM websites WHERE domain LIKE ? LIMIT 10', [$t]),
            'Rechnungen' => Db::all('SELECT id, invoice_no AS titel, status AS neben FROM invoices WHERE invoice_no LIKE ? LIMIT 10', [$t]),
        ]]);
        break;

    case 'zahlungen':
        ansicht('zahlungen', ['liste' => Db::all(
            "SELECT p.*, o.order_no, c.name AS kunde, c.id AS kunde_id
             FROM payments p JOIN orders o ON o.id = p.order_id JOIN customers c ON c.id = o.customer_id
             ORDER BY FIELD(p.status,'ausstehend','in_bearbeitung','fehlgeschlagen') DESC, p.id DESC")]);
        break;

    case 'integrationen':
        require_once __DIR__ . '/src/Zahlung/Anbieter.php';
        require_once __DIR__ . '/src/Zahlung/Stripe.php';
        $stripe = new StripeAnbieter();
        ansicht('integrationen', [
            'stripe'   => $stripe,
            'liste'    => Db::all('SELECT * FROM integrations ORDER BY category, name'),
            'ereignisse' => Db::all('SELECT * FROM webhook_events ORDER BY id DESC LIMIT 25'),
            'offen'    => (int) Db::wert("SELECT COUNT(*) FROM webhook_events WHERE status = 'fehler'"),
        ]);
        break;

    case 'onboarding':
        require_once __DIR__ . '/src/Onboarding.php';
        ansicht('onboarding', [
            'liste' => sicher(static fn() => Db::all(
                "SELECT q.*, c.name AS kunde, c.company AS firma, c.email AS kunde_email,
                        p.name AS projekt, p.status AS projekt_status
                 FROM questionnaires q
                 JOIN customers c ON c.id = q.customer_id
                 JOIN projects  p ON p.id = q.project_id
                 ORDER BY FIELD(q.status,'offen') DESC, q.id DESC")),
            'mails' => sicher(static fn() => Db::all('SELECT * FROM mails ORDER BY id DESC LIMIT 30')),
        ]);
        break;

    case 'einstellungen':
        require_once __DIR__ . '/src/Beispieldaten.php';
        require_once __DIR__ . '/src/Firma.php';
        ansicht('einstellungen', [
            'beispiele'  => sicher(static fn() => Beispieldaten::anzahl(), 0),
            'echteDaten' => sicher(static fn() => Beispieldaten::echteDatenDa(), true),
            'firma'      => sicher(static fn() => Firma::alle(), []),
            'cockpit'    => sicher(static function () {
                require_once __DIR__ . '/src/Cockpit.php';
                return ['geschuetzt' => Cockpit::geschuetzt(), 'eingerichtet' => Cockpit::eingerichtet(),
                        'beschreibbar' => Cockpit::beschreibbar(), 'benutzer' => Cockpit::benutzer(),
                        'adresse' => Cockpit::adresse()];
            }, ['geschuetzt' => null, 'eingerichtet' => false, 'beschreibbar' => false, 'benutzer' => null, 'adresse' => '']),
            'zugaenge'   => sicher(static fn() => Db::all(
                'SELECT id, name, email, role, active, last_login_at, created_at
                 FROM users ORDER BY active DESC, id')),
        ]);
        break;

    case 'monitoring':
        require_once __DIR__ . '/src/Monitoring.php';
        require_once __DIR__ . '/src/Cron.php';
        ansicht('monitoring', [
            'liste' => sicher(static fn() => Db::all(
                "SELECT w.*, c.name AS kunde, c.company AS firma, p.name AS projekt,
                        (SELECT COUNT(*) FROM website_checks k WHERE k.website_id = w.id
                           AND k.checked_at >= NOW() - INTERVAL 30 DAY) AS pruefungen,
                        (SELECT COUNT(*) FROM website_checks k WHERE k.website_id = w.id
                           AND k.checked_at >= NOW() - INTERVAL 30 DAY AND k.ok = 1) AS gute
                 FROM websites w
                 JOIN customers c ON c.id = w.customer_id
                 LEFT JOIN projects p ON p.id = w.project_id
                 ORDER BY w.monitoring DESC, FIELD(w.status,'offline','fehler','ssl_problem','domain_problem') DESC, w.domain")),
            'letzte' => sicher(static fn() => Db::all(
                "SELECT k.*, w.domain FROM website_checks k JOIN websites w ON w.id = k.website_id
                 ORDER BY k.id DESC LIMIT 20")),
            'adresse' => sicher(static fn() => Cron::adresse(), ''),
            'lauf'    => sicher(static fn() => Cron::zuletzt(), null),
            'bilanz'  => sicher(static fn() => Cron::letzteBilanz(), null),
        ]);
        break;

    case 'rechnungen':
        require_once __DIR__ . '/src/Rechnung.php';
        if ($id !== null) {
            $r = sicher(static fn() => Db::one(
                'SELECT r.*, c.name AS kunde, c.company AS firma, c.email AS kunde_email, o.order_no
                 FROM invoices r JOIN customers c ON c.id = r.customer_id
                 LEFT JOIN orders o ON o.id = r.order_id WHERE r.id = ?', [$id]), null);
            if (!$r) { http_response_code(404); exit('Beleg nicht gefunden.'); }
            if (($teile[2] ?? '') === 'pdf') {
                $daten = Rechnung::pdf($r);
                header('Content-Type: application/pdf');
                header('Content-Length: ' . strlen($daten));
                header('Content-Disposition: attachment; filename="' . Rechnung::dateiname($r) . '"');
                header('X-Content-Type-Options: nosniff');
                echo $daten;
                exit;
            }
            ansicht('rechnung', ['r' => $r, 'posten' => Rechnung::posten($r)]);
            break;
        }
        ansicht('rechnungen', [
            'liste' => sicher(static fn() => Db::all(
                'SELECT r.*, c.name AS kunde, c.company AS firma, o.order_no
                 FROM invoices r JOIN customers c ON c.id = r.customer_id
                 LEFT JOIN orders o ON o.id = r.order_id
                 ORDER BY r.id DESC LIMIT 300')),
            'summe' => (int) sicher(static fn() => Db::wert(
                'SELECT COALESCE(SUM(total_cents),0) FROM invoices WHERE YEAR(issued_at) = ?', [date('Y')]), 0),
            'ohneBeleg' => sicher(static fn() => Db::all(
                "SELECT p.*, o.order_no, c.name AS kunde, c.company AS firma
                 FROM payments p
                 JOIN orders o ON o.id = p.order_id
                 JOIN customers c ON c.id = o.customer_id
                 LEFT JOIN invoices r ON r.payment_id = p.id
                 WHERE p.status = 'bezahlt' AND r.id IS NULL ORDER BY p.id DESC")),
            'istRechnung' => Rechnung::istRechnung(),
        ]);
        break;

    case 'statistiken':
        ansicht('spaeter', ['bereich' => $route]);
        break;

    default:
        http_response_code(404);
        ansicht('spaeter', ['bereich' => 'unbekannt']);
}
