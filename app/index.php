<?php
declare(strict_types=1);

/* Verteiler der Verwaltungsplattform. Jede Anfrage laeuft hier durch. */

foreach (['Config','Db','Status','Csrf','Auth','Fmt','Events','Kennzahlen'] as $k) {
    require_once __DIR__ . "/src/$k.php";
}

/* Auffangnetz. Jede Route laedt weiterhin ausdruecklich, was sie braucht --
   das bleibt die Dokumentation, welche Seite auf welchem Baustein sitzt.
   Vergisst eine Ansicht aber eine Klasse, gab es bisher einen weissen
   Bildschirm mitten in einer Tabelle. Ab hier wird sie stattdessen
   nachgeladen. Nur Namen aus src/, nichts aus der Anfrage. */
spl_autoload_register(static function (string $klasse): void {
    if (!preg_match('/^[A-Z][A-Za-z0-9]*$/', $klasse)) { return; }
    $datei = __DIR__ . '/src/' . $klasse . '.php';
    if (is_file($datei)) { require_once $datei; }
});

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

/**
 * Nach einem erledigten Vorgang dorthin zurueck, wo der Knopf stand.
 *
 * Frueher sprang jede Aktion auf ihre angestammte Seite — "Fragebogen
 * verschickt" landete immer im Projekt, auch wenn man den Knopf woanders
 * gedrueckt hatte. Mit mehreren Ansichten auf dieselben Vorgaenge ist das
 * ein Sprung aus dem Zusammenhang heraus. Steht kein Ziel im Formular,
 * bleibt alles wie bisher.
 */
function zurueck(string $vorgabe): never { weiter(trim((string) ($_POST['zurueck'] ?? '')) ?: $vorgabe); }
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
            case 'angebot_aus_bedarf':
                require_once __DIR__ . '/src/Angebot.php';
                $neu = Angebot::ausBedarf((int) ($_POST['id'] ?? 0));
                if ($neu !== null) { weiter('angebote/' . $neu); }
                zurueck('bedarf');

            case 'angebot_zeilen':
                require_once __DIR__ . '/src/Angebot.php';
                $aid = (int) ($_POST['id'] ?? 0);
                // Derselbe Knopfleiste-Trick wie anderswo: Das Kreuz an einer
                // Zeile schickt dasselbe Formular ab, nur mit "weg".
                $weg = (int) ($_POST['weg'] ?? 0);
                if ($weg > 0) { Angebot::zeileWeg($aid, $weg); }
                else { Angebot::zeilenSpeichern($aid, (array) ($_POST['menge'] ?? []), (array) ($_POST['preis'] ?? [])); }
                zurueck('angebote/' . $aid);

            case 'angebot_baustein':
                require_once __DIR__ . '/src/Angebot.php';
                $aid = (int) ($_POST['id'] ?? 0);
                Angebot::bausteinDazu($aid, (string) ($_POST['slug'] ?? ''), (int) ($_POST['menge'] ?? 1));
                zurueck('angebote/' . $aid);

            case 'angebot_freie_zeile':
                require_once __DIR__ . '/src/Angebot.php';
                require_once __DIR__ . '/src/Baukasten.php';
                $aid = (int) ($_POST['id'] ?? 0);
                Angebot::freieZeile($aid, (string) ($_POST['bezeichnung'] ?? ''),
                    Baukasten::centsAus((string) ($_POST['preis'] ?? '0')), 1,
                    !empty($_POST['monatlich']));
                zurueck('angebote/' . $aid);

            case 'angebot_senden':
                require_once __DIR__ . '/src/Angebot.php';
                $aid = (int) ($_POST['id'] ?? 0);
                Angebot::senden($aid);
                zurueck('angebote/' . $aid);

            case 'bedarf_loeschen':
                require_once __DIR__ . '/src/Bedarf.php';
                $bid = (int) ($_POST['id'] ?? 0);
                $weg = Bedarf::loeschen($bid);
                $_SESSION[$weg ? 'gut' : 'fehler'] = $weg
                    ? 'Der Bedarf ist gelöscht.'
                    : 'An diesem Bedarf hängt ein Angebot. Lösche erst das Angebot.';
                zurueck('bedarf');

            case 'bedarf_aufraeumen':
                require_once __DIR__ . '/src/Bedarf.php';
                /* Zwei Knoepfe, eine Aktion: Der zweite verlangt das Wort,
                   weil er auch abgesendete Anfragen trifft. */
                $alles = !empty($_POST['alles']);
                if ($alles) {
                    $wort = mb_strtoupper(trim((string) ($_POST['bestaetigung'] ?? '')));
                    if (!in_array($wort, ['LOESCHEN', 'LÖSCHEN'], true)) {
                        throw new RuntimeException('Zum Leeren muss „LÖSCHEN" im Feld stehen. Es ist nichts passiert.');
                    }
                }
                $weg = Bedarf::aufraeumen($alles);
                $_SESSION['gut'] = $weg === 0
                    ? 'Es war nichts zum Aufräumen da.'
                    : $weg . ' Einträge sind weg.';
                zurueck('bedarf');

            case 'angebot_zusage':
                require_once __DIR__ . '/src/Angebot.php';
                $aid = (int) ($_POST['id'] ?? 0);
                $bid = Angebot::zusagenVonHand($aid);
                if ($bid === null) {
                    throw new RuntimeException('Dieses Angebot lässt sich nicht buchen — es ist kein verschicktes.');
                }
                $_SESSION['gut'] = 'Zusage vermerkt. Die Bestellung steht mit dem Betrag aus dem Angebot.';
                weiter('bestellungen/' . $bid);

            case 'angebot_neufassung':
                require_once __DIR__ . '/src/Angebot.php';
                $aid = (int) ($_POST['id'] ?? 0);
                // Mit dem Gegenvorschlag als Grundlage, wenn einer da ist und
                // der Knopf ihn meint -- sonst mit den Zeilen von jetzt.
                $neu = Angebot::neuFassung($aid, !empty($_POST['aus_wunsch']));
                // Geht es nicht (Entwurf oder schon angenommen), bleibt man,
                // wo man war -- die Seite sagt dann selbst, warum.
                zurueck('angebote/' . ($neu ?? $aid));

            case 'preise_anheben':
                require_once __DIR__ . '/src/Baukasten.php';
                require_once __DIR__ . '/src/Einfuehrung.php';
                // Nur, wenn die Phase wirklich am Ende ist. Sonst waere ein
                // versehentlich abgeschickter Knopf eine stille Preisrunde.
                if (Baukasten::gesperrt()) {
                    $_SESSION['fehler'] = 'Der Baukasten ist gesperrt — die Preise wurden nicht angehoben.';
                    zurueck('baukasten');
                }
                if (Einfuehrung::erreicht()) {
                    $wie = Einfuehrung::anwenden();
                    Baukasten::sperren(true);
                    Events::melden('einfuehrung_ende', 'Einführungspreise beendet', 'gut',
                        $wie . ' Bausteine um ' . Einfuehrung::erhoehung() . ' Prozent angehoben', '/baukasten');
                }
                zurueck('baukasten');

            case 'empfehlung_aufraeumen':
                require_once __DIR__ . '/src/Empfehlung.php';
                $wie = Empfehlung::aufraeumen(true);
                Events::protokoll('empfehlung_aufraeumen', $wie . ' verwaiste Empfehlungen entfernt');
                $_SESSION['gut'] = $wie === 0
                    ? 'Es war nichts Verwaistes da.'
                    : $wie . ' verwaiste ' . ($wie === 1 ? 'Empfehlung' : 'Empfehlungen') . ' entfernt.';
                zurueck('empfehlungen');

            case 'empfehlung_zuordnen':
                require_once __DIR__ . '/src/Empfehlung.php';
                $kid = (int) ($_POST['kunde'] ?? 0);
                $eid = (int) ($_POST['id'] ?? 0);
                if ($kid > 0 && $eid > 0) { Empfehlung::zuordnen($eid, $kid); }
                zurueck('empfehlungen');

            /* DIE SPERRE FUER DEN BAUKASTEN
               ----------------------------------------------------------
               Ein abgeschaltetes Eingabefeld ist die halbe Sicherung: Es
               haelt die Maus auf, nicht das Formular. Deshalb prueft der
               Server noch einmal, was der Browser schon zeigt. */
            case 'baukasten_sperre':
                require_once __DIR__ . '/src/Baukasten.php';
                $zu = (string) ($_POST['zu'] ?? '1') === '1';
                Baukasten::sperren($zu);
                Events::protokoll('baukasten_sperre', $zu ? 'Baukasten gesperrt' : 'Baukasten entsperrt');
                $_SESSION['gut'] = $zu
                    ? 'Der Baukasten ist wieder zu.'
                    : 'Der Baukasten ist offen. Nach dem Speichern sperrt er sich von selbst wieder.';
                zurueck('baukasten');

            case 'bausteine_speichern':
                require_once __DIR__ . '/src/Baukasten.php';
                if (Baukasten::gesperrt()) {
                    $_SESSION['fehler'] = 'Der Baukasten ist gesperrt — es wurde nichts geändert.';
                    zurueck('baukasten');
                }
                // Alles in einem Rutsch: Preise pflegt man selten, dann aber
                // mehrere auf einmal. Eine Transaktion, damit eine halbe
                // Preisrunde nicht stehen bleibt.
                $von   = (array) ($_POST['von'] ?? []);
                $bis   = (array) ($_POST['bis'] ?? []);
                $aktiv = (array) ($_POST['aktiv'] ?? []);
                $wie   = 0;
                Db::transaktion(static function () use ($von, $bis, $aktiv, &$wie) {
                    foreach ($von as $bid => $wert) {
                        $bid = (int) $bid;
                        if ($bid < 1) { continue; }
                        $u = Baukasten::centsAus((string) $wert);
                        $o = Baukasten::centsAus((string) ($bis[$bid] ?? ''));
                        // Eine Obergrenze unter der Untergrenze ist keine
                        // Spanne, sondern ein Tippfehler. Dann lieber gar
                        // keine Obergrenze als eine verkehrte.
                        if ($o > 0 && $o < $u) { $o = 0; }
                        Db::update('bausteine', $bid, [
                            'preis_cents'     => $u,
                            'preis_bis_cents' => $o,
                            'aktiv'           => isset($aktiv[$bid]) ? 1 : 0,
                        ]);
                        $wie++;
                    }
                });
                /* Nach getaner Arbeit faellt das Schloss von selbst wieder
                   zu. Eine Sperre, an die man nach dem Aendern denken muss,
                   ist nach dem dritten Mal offen und bleibt es. */
                Baukasten::sperren(true);
                Events::protokoll('baukasten_preise', $wie . ' Bausteine gespeichert, Baukasten wieder gesperrt');
                $_SESSION['gut'] = $wie . ' Bausteine gespeichert. Der Baukasten ist wieder zu.';
                zurueck('baukasten');

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
                    'tax_code' => mb_strtoupper(trim((string) ($_POST['tax_code'] ?? ''))) ?: null,
                    'vat_id'   => trim((string) ($_POST['vat_id'] ?? '')) ?: null,
                    'sdi'      => trim((string) ($_POST['sdi'] ?? '')) ?: null,
                ];
                // Die Sprache entscheidet, in welcher jede automatische Mail
                // an diesen Kunden hinausgeht. Sie wird beim Anfragen gesetzt;
                // hier laesst sie sich richtigstellen.
                $sp = strtolower(trim((string) ($_POST['sprache'] ?? '')));
                if (in_array($sp, ['it', 'de', 'en'], true)) { $daten['sprache'] = $sp; }
                if ($daten['name'] === '' || !filter_var($daten['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Name und eine gültige E-Mail sind Pflicht.');
                }
                $kid = (int) ($_POST['id'] ?? 0);
                if ($kid > 0) {
                    $vorher = Db::one('SELECT * FROM customers WHERE id = ?', [$kid]);
                    Db::update('customers', $kid, $daten);
                    Events::pruefspur('aendern', 'customer', $kid, $vorher ?? [], $daten);
                } else {
                    /* Die E-Mail ist eindeutig. Gibt es sie schon, legt
                       kundeFinden keinen zweiten an, sondern gibt den ersten
                       zurueck -- richtig, aber stumm: Man tippt einen Kunden
                       ein, landet auf einer fremden Akte und rätselt. Also
                       sagen, was passiert ist. */
                    $schon = sicher(static fn() => Db::one(
                        'SELECT id, name FROM customers WHERE email = ?', [$daten['email']]), null);
                    $kid = Events::kundeFinden($daten);
                    if ($schon) {
                        $_SESSION['fehler'] = 'Diese E-Mail gehört schon zu „' . $schon['name']
                            . '" — angelegt wurde nichts. Du bist auf seiner Akte.';
                    }
                }
                zurueck('kunden/' . $kid);

            /* ------------------------------------------------------------
               MEHRBEDARF

               Der Fragebogen sagt etwas anderes als das Angebot. Zwei Wege
               fuehren hier raus, und beide haken denselben Unterschied ab:
               entweder wird eine Rate daraus, oder es war ein Gespraech.
               ------------------------------------------------------------ */
            case 'mehrbedarf_nachtrag':
                require_once __DIR__ . '/src/Umfang.php';
                $pid = (int) ($_POST['id'] ?? 0);
                $jetzt = Umfang::mehrbedarf($pid);
                if ($jetzt === null) {
                    $_SESSION['fehler'] = 'Da ist nichts mehr offen — vielleicht war jemand schneller.';
                    zurueck('projekte/' . $pid);
                }
                /* Zwischen dem Anzeigen und dem Klick kann der Kunde etwas
                   geaendert haben. Dann waere ein Nachtrag ueber den alten
                   Betrag eine Rechnung ueber etwas, das so nicht mehr
                   gewuenscht ist. */
                if ((string) ($_POST['signatur'] ?? '') !== (string) $jetzt['signatur']) {
                    $_SESSION['fehler'] = 'Der Kunde hat inzwischen etwas geändert. Schau es dir noch einmal an.';
                    zurueck('projekte/' . $pid);
                }
                $zid = Umfang::nachtrag($pid);
                if ($zid === null) {
                    $_SESSION['fehler'] = 'Daraus lässt sich keine Rate machen — es steht kein Betrag dahinter.';
                    zurueck('projekte/' . $pid);
                }
                $bid = (int) Db::wert('SELECT order_id FROM payments WHERE id = ?', [$zid], 0);
                $_SESSION['gut'] = 'Der Nachtrag steht als Rate auf der Bestellung. Jetzt fehlt nur noch der Zahlungslink.';
                zurueck('bestellungen/' . $bid . '?tun=zahlungslink');

            case 'mehrbedarf_erledigt':
                require_once __DIR__ . '/src/Umfang.php';
                $pid = (int) ($_POST['id'] ?? 0);
                $jetzt = Umfang::mehrbedarf($pid);
                if ($jetzt === null) { zurueck('projekte/' . $pid); }
                if ((string) ($_POST['signatur'] ?? '') !== (string) $jetzt['signatur']) {
                    $_SESSION['fehler'] = 'Der Kunde hat inzwischen etwas geändert. Schau es dir noch einmal an.';
                    zurueck('projekte/' . $pid);
                }
                Umfang::abhaken((int) $jetzt['fragebogen_id'], (string) $jetzt['signatur']);
                $_SESSION['gut'] = 'Abgehakt. Kreuzt der Kunde später etwas Weiteres an, meldet es sich wieder.';
                zurueck('projekte/' . $pid);

            case 'zahlungslink_senden':
                require_once __DIR__ . '/src/Mail.php';
                require_once __DIR__ . '/src/Texte.php';
                $zid = (int) ($_POST['id'] ?? 0);
                $z = Db::one('SELECT * FROM payments WHERE id = ?', [$zid]);
                $bst = $z ? Db::one('SELECT o.*, c.name AS kunde, c.email AS kunde_email, c.sprache AS kunde_sprache
                                     FROM orders o JOIN customers c ON c.id = o.customer_id WHERE o.id = ?',
                                     [(int) $z['order_id']]) : null;
                if (!$z || !$bst || !$z['link_url']) { throw new RuntimeException('Für diese Zahlung gibt es noch keinen Link.'); }
                $spr = (string) ($bst['kunde_sprache'] ?: 'it');
                $was = ['it' => ['anzahlung' => 'l’acconto', 'restzahlung' => 'il saldo',
                                 'gesamt' => 'il pagamento', 'nachtrag' => 'le voci aggiunte'],
                        'de' => ['anzahlung' => 'die Anzahlung', 'restzahlung' => 'die Restzahlung',
                                 'gesamt' => 'die Zahlung', 'nachtrag' => 'die zusätzlich gewünschten Punkte'],
                        'en' => ['anzahlung' => 'the deposit', 'restzahlung' => 'the balance',
                                 'gesamt' => 'the payment', 'nachtrag' => 'the additional items']
                       ][$spr][(string) $z['art']] ?? (string) $z['art'];
                [$betreff, $text] = Texte::mail('zahlungslink', $spr, [
                    'name' => (string) $bst['kunde'], 'paket' => (string) $bst['package_name'],
                    'was' => $was, 'betrag' => Fmt::geld((int) $z['amount_cents'], (string) $z['currency']),
                    'link' => (string) $z['link_url'],
                ]);
                Mail::senden('zahlungslink', (string) $bst['kunde_email'], $betreff, $text,
                    ['customer_id' => (int) $bst['customer_id'], 'order_id' => (int) $bst['id'], 'payment_id' => $zid]);
                // Keine Meldung: Der Knopf wurde gerade gedrueckt, und die
                // gruene Zeile oben sagt es schon. Dass die Mail rausging,
                // steht im Mailprotokoll des Vorgangs.
                $_SESSION['gut'] = 'Der Zahlungslink ist an ' . $bst['kunde_email'] . ' raus.';
                zurueck('bestellungen/' . (int) ($_POST['order_id'] ?? $bst['id']));

            case 'kunde_nachricht':
                require_once __DIR__ . '/src/Nachricht.php';
                require_once __DIR__ . '/src/Anfrage.php';
                $kid = (int) ($_POST['id'] ?? 0);
                // Wenn eine offene Anfrage da ist, kommt ihr Link mit in die Mail.
                $tok = sicher(static fn() => Db::wert(
                    'SELECT token FROM anfragen WHERE customer_id = ? AND order_id IS NULL ORDER BY id DESC LIMIT 1',
                    [$kid], ''), '');
                Nachricht::vorab($kid, (string) ($_POST['text'] ?? ''), 'admin',
                    $tok ? Anfrage::link((string) $tok) : null,
                    (string) ($_POST['betreff'] ?? ''));
                $_SESSION['gut'] = 'Nachricht ist raus — der Kunde bekommt sie per E-Mail.';
                zurueck('kunden/' . $kid);

            case 'kunde_datei':
                require_once __DIR__ . '/src/Ablage.php';
                $kid = (int) ($_POST['id'] ?? 0);
                Ablage::annehmen($_FILES['datei'] ?? [], null, $kid, 'admin');
                zurueck('kunden/' . $kid);

            /* Zwei Wege, einen Kunden loszuwerden — und ein getipptes Wort
               davor. Ein Klick allein ist zu wenig fuer etwas, das sich
               nicht rueckgaengig machen laesst. */
            case 'kunde_loeschen':
                require_once __DIR__ . '/src/Kunde.php';
                $kid  = (int) ($_POST['id'] ?? 0);
                $wort = mb_strtoupper(trim((string) ($_POST['bestaetigung'] ?? '')));
                // Der zweite Weg vernichtet auch Belege. Er verlangt deshalb
                // ein anderes, laengeres Wort — nicht damit es schwerer wird,
                // sondern damit niemand aus Gewohnheit das falsche tippt.
                $auchBelege = !empty($_POST['auch_belege']);
                $erwartet = $auchBelege
                    ? ['ALLES LOESCHEN', 'ALLES LÖSCHEN']
                    : ['LOESCHEN', 'LÖSCHEN'];
                if (!in_array($wort, $erwartet, true)) {
                    throw new RuntimeException('Zum Löschen muss „' . $erwartet[1]
                        . '" im Feld stehen. Es ist nichts passiert.');
                }
                $weg = Kunde::loeschen($kid, $auchBelege);
                $_SESSION['gut'] = 'Kunde „' . $weg['name'] . '" gelöscht — '
                    . $weg['zeilen'] . ' Einträge'
                    . ($weg['dateien'] > 0 ? ' und ' . $weg['dateien'] . ' Datei(en)' : '')
                    . ' sind weg.'
                    . ($weg['belege']
                        ? ' Vernichtet wurden dabei auch die Belege '
                          . implode(', ', array_column($weg['belege'], 'nummer'))
                          . ' — sie stehen mit Betrag und Datum in der Prüfspur.'
                        : '');
                weiter('kunden');

            case 'kunde_anonymisieren':
                require_once __DIR__ . '/src/Kunde.php';
                $kid = (int) ($_POST['id'] ?? 0);
                $wort = mb_strtoupper(trim((string) ($_POST['bestaetigung'] ?? '')));
                if ($wort !== 'ANONYM') {
                    throw new RuntimeException('Zum Anonymisieren muss ANONYM im Feld stehen. Es ist nichts passiert.');
                }
                $an = Kunde::anonymisieren($kid);
                $_SESSION['gut'] = 'Kunde ' . $an['nummer'] . ' anonymisiert. '
                    . ($an['belege'] > 0
                        ? $an['belege'] . ' Beleg(e) behalten ihren Empfänger und bleiben in den Büchern. '
                        : '')
                    . $an['zeilen'] . ' Einträge geleert'
                    . ($an['dateien'] > 0 ? ', ' . $an['dateien'] . ' Datei(en) gelöscht' : '') . '.';
                weiter('kunden/' . $kid);

            case 'zustellbarkeit_pruefen':
                require_once __DIR__ . '/src/Zustellbarkeit.php';
                $z = Zustellbarkeit::pruefen();
                $_SESSION[$z['stand'] === 'gut' ? 'gut' : 'fehler'] = match ($z['stand']) {
                    'gut'       => 'SPF, DKIM und DMARC stehen für ' . $z['domain'] . '.',
                    'unbekannt' => 'Die Einträge liessen sich von hier aus nicht nachschlagen.',
                    default     => 'An den Einträgen für ' . $z['domain'] . ' stimmt etwas nicht — siehe unten.',
                };
                zurueck('monitoring');

            case 'zustellbarkeit_probe':
                require_once __DIR__ . '/src/Zustellbarkeit.php';
                $pr = Zustellbarkeit::probemail((string) ($_POST['an'] ?? ''));
                $_SESSION[$pr['ok'] ? 'gut' : 'fehler'] = $pr['text'];
                zurueck('monitoring');

            /* Vorschau: eintragen und freischalten sind zweierlei.
               Der Kunde sieht den Entwurf erst nach dem Freischalten — vorher
               steht bei ihm ein grauer Kasten mit dem Satz, dass es hier
               erscheinen wird. */
            case 'vorschau_speichern':
                $pid = (int) ($_POST['id'] ?? 0);
                $url = trim((string) ($_POST['preview_url'] ?? ''));
                if ($url !== '' && !preg_match('~^https?://~i', $url)) { $url = 'https://' . $url; }
                if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
                    throw new RuntimeException('Das sieht nicht nach einer Adresse aus. Es wurde nichts geändert.');
                }
                Db::update('projects', $pid, ['preview_url' => $url !== '' ? $url : null]);
                Events::pruefspur('aendern', 'project', $pid, [], ['preview_url' => $url]);
                // Eine bestehende Freigabe bleibt: Der Kunde klickt weiter
                // denselben Knopf und sieht ab sofort die neue Adresse. Eine
                // zweite E-Mail bekommt er nicht — er hat nichts Neues zu tun.
                $frei = sicher(static fn() => Db::wert(
                    'SELECT vorschau_frei_am FROM projects WHERE id = ?', [$pid], null), null);
                $_SESSION['gut'] = $url === ''
                    ? 'Vorschau-Adresse entfernt.'
                    : ('Vorschau-Adresse gespeichert.' . ($frei !== null
                        ? ' Der Kunde sieht ab sofort die neue Adresse.'
                        : ' Der Kunde sieht sie noch nicht — dazu freischalten.'));
                zurueck('vorgaenge');

            case 'vorschau_frei':
                require_once __DIR__ . '/src/Nachricht.php';
                $pid = (int) ($_POST['id'] ?? 0);
                $url = (string) sicher(static fn() => Db::wert(
                    'SELECT preview_url FROM projects WHERE id = ?', [$pid], ''), '');
                if (trim($url) === '') {
                    throw new RuntimeException('Ohne Vorschau-Adresse gibt es nichts freizuschalten. '
                        . 'Trag sie zuerst ein — sonst bekommt der Kunde eine E-Mail und findet nichts.');
                }
                Db::update('projects', $pid, ['vorschau_frei_am' => date('Y-m-d H:i:s')]);
                // Der Projektstand zieht mit, damit beides nicht auseinanderlaeuft.
                // melden = false: Die E-Mail schicken wir gleich selbst, und zwar
                // genau einmal.
                sicher(static fn() => Events::projektStatus($pid, 'vorschau', false), null);
                Events::protokoll('vorschau_frei', 'Vorschau für den Kunden freigeschaltet', null, null, $pid);
                // Ob die E-Mail schon einmal draussen war, muss VOR dem
                // Verschicken feststehen — danach ist sie es in jedem Fall,
                // und die Rueckmeldung waere nicht mehr zu unterscheiden.
                require_once __DIR__ . '/src/Mail.php';
                $schonMal = (bool) sicher(static fn() => Mail::schonGeschickt('vorschau', 'project_id', $pid), false);
                $raus = (bool) sicher(static fn() => Nachricht::vorschauBereit($pid), false);
                $_SESSION['gut'] = 'Vorschau ist freigeschaltet.' . match (true) {
                    $raus     => ' Der Kunde hat die E-Mail bekommen.',
                    $schonMal => ' Eine zweite E-Mail bekommt er nicht — die erste ist schon draußen. '
                                 . 'Auf seiner Seite sieht er den Entwurf sofort.',
                    default   => ' Die E-Mail ging nicht raus — er sieht die Vorschau aber auf seiner Seite.',
                };
                zurueck('vorgaenge');

            case 'vorschau_sperren':
                $pid = (int) ($_POST['id'] ?? 0);
                Db::update('projects', $pid, ['vorschau_frei_am' => null]);
                Events::protokoll('vorschau_gesperrt', 'Vorschau wieder gesperrt', null, null, $pid);
                $_SESSION['gut'] = 'Vorschau ist wieder gesperrt. Der Kunde sieht sie nicht mehr.';
                zurueck('vorgaenge');

            case 'stimme_frei':
                require_once __DIR__ . '/src/Stimme.php';
                Stimme::veroeffentlichen((int) ($_POST['id'] ?? 0));
                $_SESSION['gut'] = 'Die Stimme steht jetzt auf der Website.';
                zurueck('stimmen');

            case 'stimme_weg':
                require_once __DIR__ . '/src/Stimme.php';
                Stimme::verstecken((int) ($_POST['id'] ?? 0));
                $_SESSION['gut'] = 'Von der Website genommen.';
                zurueck('stimmen');

            case 'stimme_erlaubnis':
                require_once __DIR__ . '/src/Stimme.php';
                Stimme::erlaubnisSetzen((int) ($_POST['id'] ?? 0), true);
                $_SESSION['gut'] = 'Vermerkt. Jetzt lässt sie sich veröffentlichen.';
                zurueck('stimmen');

            case 'abo_anlegen':
                require_once __DIR__ . '/src/Abo.php';
                $kid = (int) ($_POST['id'] ?? 0);
                require_once __DIR__ . '/src/Ausgabe.php';
                // Leer heisst "wie im Paket". Ein Betrag hier ueberschreibt ihn,
                // fuer den Fall, dass am Telefon etwas anderes vereinbart war.
                $freiMonat = trim((string) ($_POST['betrag'] ?? ''));
                $aid = Abo::anlegen($kid, [
                    'paket_slug'   => (string) ($_POST['paket_slug'] ?? ''),
                    'zahlart'      => (string) ($_POST['zahlart'] ?? 'karte'),
                    'projekt_id'   => (int) ($_POST['projekt_id'] ?? 0) ?: null,
                    'betrag_cents' => $freiMonat !== '' ? Ausgabe::cents($freiMonat) : null,
                ]);
                $a = Db::one('SELECT * FROM abos WHERE id = ?', [$aid]);
                $_SESSION['gut'] = 'Betreuung angelegt: ' . $a['paket_name'] . ', '
                    . Fmt::geld((int) $a['betrag_cents'], (string) $a['currency']) . ' im Monat. '
                    . 'Mindestlaufzeit bis ' . Fmt::datum((string) $a['mindestlaufzeit_bis']) . '.'
                    . ((string) $a['zahlart'] === 'manuell'
                        ? ' Abgerechnet wird von Hand — solange Stripe nicht bereit ist, geht es nicht anders.'
                        : '');
                zurueck('kunden/' . $kid);

            case 'abo_kuendigen':
                require_once __DIR__ . '/src/Abo.php';
                $aid = (int) ($_POST['id'] ?? 0);
                $a = Db::one('SELECT * FROM abos WHERE id = ?', [$aid]);
                if (!$a) { throw new RuntimeException('Vertrag nicht gefunden.'); }
                $e = Abo::kuendigen($aid, 'uwe');
                $_SESSION['gut'] = $e['schon']
                    ? 'Der Vertrag war schon gekündigt — er läuft bis ' . Fmt::datum($e['ende']) . '.'
                    : ('Gekündigt zum ' . Fmt::datum($e['ende']) . '. '
                       . ($e['mail'] ? 'Der Kunde hat die Bestätigung bekommen.'
                                     : 'Die Bestätigung ging nicht raus — bitte selbst Bescheid geben.'));
                zurueck('kunden/' . (int) $a['customer_id']);

            case 'ausgabe_speichern':
                require_once __DIR__ . '/src/Ausgabe.php';
                $aid = (int) ($_POST['id'] ?? 0);
                $datei = $_FILES['beleg'] ?? null;
                $aid = Ausgabe::speichern($_POST, is_array($datei) ? $datei : null, $aid);
                $_SESSION['gut'] = 'Ausgabe gespeichert.';
                weiter('ausgaben/' . $aid);

            case 'ausgabe_loeschen':
                require_once __DIR__ . '/src/Ausgabe.php';
                Ausgabe::loeschen((int) ($_POST['id'] ?? 0));
                $_SESSION['gut'] = 'Der Eintrag ist weg.';
                weiter('ausgaben');

            case 'steuerakte_bauen':
                // Von Hand anstossen, wenn es nicht bis zur Nacht warten soll.
                require_once __DIR__ . '/src/Steuerakte.php';
                @set_time_limit(300);
                $sj = (int) ($_POST['jahr'] ?? date('Y'));
                Steuerakte::archivieren($sj);
                $_SESSION['gut'] = 'Das Paket für ' . $sj . ' ist neu gebaut und liegt bereit.';
                zurueck('steuerakte');

            case 'kundenlink_neu':
                // Zieht den alten Zugang zurueck. Gedacht fuer den Fall, dass
                // ein Kunde den Link weitergegeben hat — oder ihn selbst nicht
                // mehr haben soll. Der alte Link zeigt danach nichts mehr.
                require_once __DIR__ . '/src/Kundenzugang.php';
                $kid = (int) ($_POST['id'] ?? 0);
                if ($kid <= 0) { throw new RuntimeException('Kein Kunde angegeben.'); }
                $neuLink = Kundenzugang::link(Kundenzugang::neu($kid));
                $_SESSION['gut'] = 'Neuer Zugangslink erzeugt. Der alte gilt nicht mehr — '
                    . 'schick dem Kunden den neuen: ' . $neuLink;
                zurueck('kunden/' . $kid);

            case 'anfrage_bestellung':
                require_once __DIR__ . '/src/Anfrage.php';
                $bid = Anfrage::zuBestellung((int) ($_POST['id'] ?? 0), (int) ($_POST['paket_id'] ?? 0));
                zurueck('bestellungen/' . $bid);

            case 'anfrage_status':
                require_once __DIR__ . '/src/Anfrage.php';
                Anfrage::status((int) ($_POST['id'] ?? 0), (string) ($_POST['status'] ?? ''));
                zurueck('anfragen/' . (int) ($_POST['id'] ?? 0));

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
                // Der Preis darf abweichen — siehe Events::bestellungAnlegen.
                // Leere Felder heissen "wie im Paket", nicht "null Euro".
                require_once __DIR__ . '/src/Ausgabe.php';
                $freierPreis = trim((string) ($_POST['preis'] ?? ''));
                $freiProzent = trim((string) ($_POST['prozent'] ?? ''));
                $bid = Events::bestellungAnlegen((int) $_POST['customer_id'], (int) $_POST['package_id'],
                    trim((string) ($_POST['notes'] ?? '')) ?: null,
                    $freierPreis !== '' ? Ausgabe::cents($freierPreis) : null,
                    $freiProzent !== '' ? (int) $freiProzent : null,
                    trim((string) ($_POST['bezeichnung'] ?? '')) ?: null);
                weiter('bestellungen/' . $bid);

            case 'bestellung_status':
                Events::bestellungStatus((int) $_POST['id'], (string) $_POST['status']);
                zurueck('bestellungen/' . (int) $_POST['id']);

            case 'zahlung_bestaetigen':
                /* Die Bestellnummer steht nur dort im Formular, wo es eine
                   Bestellung gibt. Monatsraten aus der Betreuung haben keine
                   — sie kommen von der Kundenseite und tragen ihr Ziel selbst
                   im Feld "zurueck". Ohne den Umweg ueber ?? gaebe es hier
                   eine Warnung und einen Sprung nach "bestellungen/0". */
                Events::zahlungBestaetigen((int) $_POST['id'], trim((string) ($_POST['referenz'] ?? '')) ?: null,
                    (string) ($_POST['anbieter'] ?? 'manuell'));
                zurueck('bestellungen/' . (int) ($_POST['order_id'] ?? 0));

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
                    'link_url' => $url, 'link_bis' => date('Y-m-d H:i:s', strtotime('+' . Events::LINK_GILT_TAGE . ' days')),
                ]);
                Events::protokoll('zahlungslink', 'Zahlungslink erstellt: ' . ($z['bezeichnung'] ?: 'Zahlung')
                    . ' · ' . Fmt::geld((int) $z['amount_cents']), (int) $b['customer_id'], (int) $b['id']);
                zurueck('bestellungen/' . (int) $b['id']);

            case 'zahlung_fehler':
                Events::zahlungFehlgeschlagen((int) $_POST['id'], trim((string) ($_POST['grund'] ?? '')));
                zurueck('bestellungen/' . (int) $_POST['order_id']);

            case 'projekt_status':
                $pid = (int) $_POST['id'];
                $neuerStand = (string) $_POST['status'];
                Events::projektStatus($pid, $neuerStand);
                // Der Stand sagt "Vorschau", der Kunde hat aber nichts zum
                // Anklicken: Dann sagen wir es hier, statt ihn auf eine leere
                // Seite zu schicken.
                if ($neuerStand === 'vorschau') {
                    $frei = sicher(static fn() => Db::one(
                        'SELECT preview_url, vorschau_frei_am FROM projects WHERE id = ?', [$pid]), []);
                    if (trim((string) ($frei['preview_url'] ?? '')) === '') {
                        $_SESSION['fehler'] = 'Der Stand steht auf „Vorschau“, aber es ist keine '
                            . 'Vorschau-Adresse eingetragen. Der Kunde sieht nichts zum Anklicken.';
                    } elseif (($frei['vorschau_frei_am'] ?? null) === null) {
                        $_SESSION['fehler'] = 'Der Stand steht auf „Vorschau“, die Adresse ist aber noch '
                            . 'nicht freigeschaltet. Der Kunde sieht sie erst nach dem Freischalten.';
                    }
                }
                zurueck('projekte/' . $pid);

            case 'projekt_felder':
                $pid = (int) $_POST['id'];
                $daten = [
                    'deadline' => ($_POST['deadline'] ?? '') !== '' ? (string) $_POST['deadline'] : null,
                    'priority' => (string) ($_POST['priority'] ?? 'normal'),
                    'preview_url' => trim((string) ($_POST['preview_url'] ?? '')) ?: null,
                ];
                Db::update('projects', $pid, $daten);
                Events::pruefspur('aendern', 'project', $pid, [], $daten);
                zurueck('projekte/' . $pid);

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
                zurueck('projekte/' . $pid);

            case 'rechnung_erzeugen':
                require_once __DIR__ . '/src/Rechnung.php';
                $zid = (int) $_POST['id'];
                $neu = Rechnung::ausZahlung($zid);
                $_SESSION['gut'] = $neu !== null
                    ? Rechnung::bezeichnung() . ' erstellt.'
                    : 'Dazu gibt es schon einen Beleg — oder die Zahlung ist nicht als bezahlt gebucht.';
                zurueck($_POST['zurueck'] ?? 'rechnungen');

            case 'rechnung_schicken':
                /* Frueher stand hier ein fest eingetippter deutscher Text mit
                   einem Link auf die Verwaltung — ein italienischer Kunde bekam
                   deutsche Post und musste sich das Blatt selbst abholen. Jetzt
                   derselbe Weg wie beim automatischen Versand: dreisprachig,
                   mit dem PDF im Anhang, Wortlaut an einer Stelle. */
                require_once __DIR__ . '/src/Rechnung.php';
                $r = Db::one('SELECT * FROM invoices WHERE id = ?', [(int) $_POST['id']]);
                if (!$r) { throw new RuntimeException('Beleg nicht gefunden.'); }
                $ok = Rechnung::verschicken($r);
                $_SESSION['gut'] = $ok ? 'Verschickt.' : 'Der Versand hat nicht geklappt — siehe Nachrichten.';
                zurueck('rechnungen/' . (int) $r['id']);

            case 'cockpit_schuetzen':
                require_once __DIR__ . '/src/Cockpit.php';
                // Ein leeres Feld heisst weiterhin: das System denkt sich eins
                // aus. Wer selbst eins waehlt, bekommt es nicht noch einmal
                // angezeigt — er kennt es ja.
                $eigenes = trim((string) ($_POST['passwort'] ?? ''));
                if ($eigenes !== '') {
                    if (mb_strlen($eigenes) < 10) {
                        $_SESSION['fehler'] = 'Das Passwort ist zu kurz — mindestens zehn Zeichen.';
                        weiter('einstellungen');
                    }
                    // Zeilenumbrueche wuerden die .htpasswd-Datei zerreissen.
                    if (preg_match('~[\r\n\t]~', $eigenes)) {
                        $_SESSION['fehler'] = 'Das Passwort darf keine Zeilenumbrüche enthalten.';
                        weiter('einstellungen');
                    }
                }
                $e = Cockpit::einrichten(trim((string) ($_POST['benutzer'] ?? 'uwe')) ?: 'uwe',
                                         $eigenes !== '' ? $eigenes : null);
                if (!$e['ok']) { throw new RuntimeException((string) $e['grund']); }
                $selbstGewaehlt = $eigenes !== '';
                // Genau einmal anzeigen, danach ist es weg. Im Protokoll steht
                // nur, DASS es passiert ist — nie das Passwort.
                if (!$selbstGewaehlt) {
                    $_SESSION['cockpit_zugang'] = ['benutzer' => $e['benutzer'], 'passwort' => $e['passwort']];
                }
                Events::protokoll('cockpit', 'Passwortschutz für /cockpit/ eingerichtet (Benutzer '
                    . $e['benutzer'] . ', Verfahren ' . $e['verfahren'] . ')');
                if ($e['bestaetigt']) {
                    $_SESSION['gut'] = $selbstGewaehlt
                        ? 'Das Cockpit ist geschützt — mit deinem Passwort.'
                        : 'Das Cockpit ist geschützt. Das Passwort steht unten — schreib es dir auf.';
                } else {
                    $_SESSION['fehler'] = (string) $e['grund'];
                    $_SESSION['gut'] = $selbstGewaehlt
                        ? 'Gesetzt — aber ungeprüft, siehe oben.'
                        : 'Das Passwort steht unten — schreib es dir auf.';
                }
                weiter('einstellungen');

            case 'versand_speichern':
                require_once __DIR__ . '/src/Versand.php';
                $fehler = Versand::speichern(
                    (string) ($_POST['key'] ?? ''),
                    (string) ($_POST['from'] ?? ''),
                    (string) ($_POST['name'] ?? ''),
                    (string) ($_POST['to'] ?? '')
                );
                if ($fehler) {
                    $_SESSION['fehler'] = implode(' ', $fehler);
                } else {
                    // Im Protokoll steht, DASS gespeichert wurde — nie der Schlüssel.
                    Events::protokoll('versand', 'Zugangsdaten für den E-Mail-Versand geändert');
                    // Gleich nachsehen, ob es wirklich geht. Ein gespeicherter
                    // Schlüssel ist noch kein gültiger.
                    $_SESSION['versand_test'] = Versand::pruefen();
                    $_SESSION['gut'] = 'Gespeichert.';
                }
                weiter('einstellungen');

            /* Der Zuruf aufs Handy. Nummer und Schluessel traegt Uwe selbst
               ein — sie stehen nur in der Datenbank auf dem Webspace. */
            case 'zuruf_speichern':
                require_once __DIR__ . '/src/Zuruf.php';
                $fehler = Zuruf::speichern(
                    (string) ($_POST['nummer'] ?? ''),
                    (string) ($_POST['key'] ?? ''),
                    !empty($_POST['an'])
                );
                if ($fehler) {
                    $_SESSION['fehler'] = implode(' ', $fehler);
                } else {
                    // Im Protokoll steht, DASS gespeichert wurde — nie der Schluessel.
                    Events::protokoll('zuruf', 'Einstellungen für den Zuruf aufs Handy geändert');
                    $_SESSION['gut'] = 'Gespeichert. Schick dir am besten gleich eine Testnachricht.';
                }
                zurueck('einstellungen');

            case 'zuruf_pruefen':
                require_once __DIR__ . '/src/Zuruf.php';
                $e = Zuruf::pruefen();
                $_SESSION[$e['ok'] ? 'gut' : 'fehler'] = $e['text'];
                zurueck('einstellungen');

            case 'zuruf_weg':
                require_once __DIR__ . '/src/Zuruf.php';
                Zuruf::entfernen();
                Events::protokoll('zuruf', 'Zuruf aufs Handy abgeschaltet');
                $_SESSION['gut'] = 'Der Zuruf ist aus, Nummer und Schlüssel sind gelöscht.';
                zurueck('einstellungen');

            case 'versand_pruefen':
                require_once __DIR__ . '/src/Versand.php';
                $_SESSION['versand_test'] = Versand::pruefen();
                weiter('einstellungen');

            case 'versand_schluessel_weg':
                require_once __DIR__ . '/src/Versand.php';
                Versand::schluesselEntfernen();
                Events::protokoll('versand', 'Hinterlegter Brevo-Schlüssel entfernt');
                $_SESSION['gut'] = 'Der Schlüssel ist entfernt. Es gilt wieder, was in config.local.php steht.';
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

            case 'abo_abrechnen':
                /* Einen Monat anlegen. Von Hand, weil Uwe manchmal einen Monat
                   nachtragen will — die Automatik im Cron legt nur an, was
                   nach dem Kalender faellig geworden ist. */
                require_once __DIR__ . '/src/Abo.php';
                $aid = (int) $_POST['id'];
                $monat = trim((string) ($_POST['monat'] ?? ''));
                $neu = Abo::abrechnen($aid, $monat !== '' ? $monat : null);
                $_SESSION['gut'] = $neu !== null
                    ? 'Der Monat steht als offene Rate. Fordere sie an, damit der Kunde davon erfährt.'
                    : 'Dieser Monat ist schon abgerechnet — oder der Vertrag läuft nicht mehr so lange.';
                zurueck((string) ($_POST['zurueck'] ?? 'abos'));

            case 'abo_anfordern':
                require_once __DIR__ . '/src/Abo.php';
                $zid = (int) $_POST['id'];
                $_SESSION['gut'] = match (Abo::anfordern($zid)) {
                    'raus'           => 'Die Aufforderung ist raus — ab jetzt läuft die Frist von sieben Tagen.',
                    'versand_fehler' => 'Der Versand hat nicht geklappt — siehe Nachrichten. Die Rate bleibt ohne Frist stehen.',
                    default          => 'Nichts zu tun: Entweder ist sie bezahlt, oder die Aufforderung ging schon raus.',
                };
                zurueck((string) ($_POST['zurueck'] ?? 'abos'));

            /* ---------- Werkstatt ---------- */

            case 'briefing_bauen':
                /* Erzeugen UND festhalten, in einem Schritt. Ein Briefing,
                   das nur auf dem Bildschirm entsteht, ist in Monat 14
                   verschwunden — und dann faengt jede Aenderung an einer
                   betreuten Seite wieder bei null an. */
                require_once __DIR__ . '/src/Briefing.php';
                $pid = (int) $_POST['id'];
                Briefing::speichern($pid);
                $_SESSION['gut'] = 'Das Briefing steht. Kopieren, Claude öffnen, einfügen.';
                zurueck('projekte/' . $pid);

            case 'chat_merken':
                require_once __DIR__ . '/src/Briefing.php';
                $pid = (int) $_POST['id'];
                Briefing::chatMerken($pid, (string) ($_POST['url'] ?? ''));
                $_SESSION['gut'] = trim((string) ($_POST['url'] ?? '')) !== ''
                    ? 'Gemerkt — der Knopf führt jetzt direkt dorthin.'
                    : 'Die Adresse ist wieder raus.';
                zurueck('projekte/' . $pid);

            case 'muster_speichern':
                require_once __DIR__ . '/src/Muster.php';
                $mid = Muster::speichern(
                    isset($_POST['id']) ? (int) $_POST['id'] : null, $_POST);
                $_SESSION['gut'] = 'Gespeichert. Ab jetzt schlägt das Briefing ihn vor, wo er passt.';
                weiter('muster/' . $mid);

            /* ============================================================
               PROTOKOLLE AUFRAEUMEN

               Zwei Listen wachsen ewig und werden dadurch unbrauchbar: die
               verschickten Mails und der Verlauf. Beide sind Protokolle, kein
               Beleg — was fuers Finanzamt zaehlt, liegt in Rechnungen und
               Zahlungen und wird hier nirgends angefasst.

               Ein Unterschied bleibt und ist wichtig: Eine GESENDETE Mail ist
               nicht nur ein Eintrag. Die Verwaltung liest an ihr ab, ob der
               Kunde etwas schon bekommen hat -- der Zahlungslink, die
               Erinnerung, die Mahnstufe. Wer sie loescht, setzt die Fuehrung
               an dieser Stelle zurueck. Deshalb fragt die Seite dort nach und
               sagt, was passiert. Ein Fehlversuch traegt nichts davon; der
               geht ohne Rueckfrage.
               ============================================================ */
            case 'mail_loeschen':
                $mid = (int) ($_POST['id'] ?? 0);
                $m = Db::one('SELECT * FROM mails WHERE id = ?', [$mid]);
                if (!$m) { throw new RuntimeException('Diesen Eintrag gibt es nicht.'); }
                Db::run('DELETE FROM mails WHERE id = ?', [$mid]);
                $_SESSION['gut'] = (string) $m['status'] === 'gesendet'
                    ? 'Der Eintrag ist weg. Die Verwaltung weiß jetzt nicht mehr, dass diese '
                      . 'Mail draußen war — der Schritt kann wieder auftauchen.'
                    : 'Der Fehlversuch ist weg.';
                zurueck('onboarding');

            case 'mails_fehler_loeschen':
                $weg = (int) Db::wert("SELECT COUNT(*) FROM mails WHERE status <> 'gesendet'");
                Db::run("DELETE FROM mails WHERE status <> 'gesendet'");
                $_SESSION['gut'] = $weg === 0
                    ? 'Es stand kein Fehlversuch in der Liste.'
                    : ($weg === 1 ? 'Ein Fehlversuch ist weg.' : $weg . ' Fehlversuche sind weg.');
                zurueck('onboarding');

            case 'aktivitaet_loeschen':
                Db::run('DELETE FROM activities WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
                $_SESSION['gut'] = 'Der Eintrag ist weg.';
                zurueck('aktivitaeten');

            case 'muster_loeschen':
                require_once __DIR__ . '/src/Muster.php';
                Muster::loeschen((int) $_POST['id']);
                $_SESSION['gut'] = 'Der Baustein ist weg.';
                weiter('muster');

            case 'abnahme_pruefen':
                require_once __DIR__ . '/src/Abnahme.php';
                $pid = (int) $_POST['id'];
                $erg = Abnahme::fuerProjekt($pid);
                $schlecht = (int) ($erg['zaehler']['schlecht'] ?? 0);
                $_SESSION['gut'] = $schlecht === 0
                    ? 'Nichts zu beanstanden — ' . (int) ($erg['zaehler']['gut'] ?? 0) . ' Punkte in Ordnung.'
                    : $schlecht . ' Punkt(e) zu beheben. Sie stehen oben in der Liste.';
                zurueck('projekte/' . $pid);

            case 'standard_speichern':
                require_once __DIR__ . '/src/Standard.php';
                Standard::speichern((string) ($_POST['text'] ?? ''), !empty($_POST['anhaengen']));
                $_SESSION['gut'] = trim((string) ($_POST['text'] ?? '')) === ''
                    ? 'Zurück auf die Vorgabe.'
                    : 'Die Hausregeln stehen. Sie gelten ab dem nächsten Briefing.';
                zurueck('standard');

            case 'standard_gesehen':
                require_once __DIR__ . '/src/Standard.php';
                Standard::alsGesehenMerken();
                $_SESSION['gut'] = 'Abgehakt. Ändern kannst du sie jederzeit — sie gilt ab dem nächsten Briefing.';
                zurueck('standard');

            case 'claude_projekt':
                require_once __DIR__ . '/src/Standard.php';
                Standard::claudeProjektSpeichern((string) ($_POST['url'] ?? ''));
                $_SESSION['gut'] = trim((string) ($_POST['url'] ?? '')) !== ''
                    ? 'Eingetragen — die Briefing-Knöpfe öffnen ab jetzt dieses Projekt.'
                    : 'Wieder raus. Die Knöpfe öffnen jetzt einen freien Chat.';
                zurueck('standard');

            case 'mahnung_schicken':
                /* Stufe 2 und 3 gehen nur von Hand raus — deshalb dieser
                   Knopf und kein Cronjob. Die Stufe kommt aus dem Formular,
                   aber Mahnung::schicken prueft selbst, ob sie dran ist:
                   Zweimal dieselbe Mahnung waere schlimmer als keine. */
                require_once __DIR__ . '/src/Mahnung.php';
                $zid = (int) $_POST['id'];
                $stufe = max(1, min(3, (int) ($_POST['stufe'] ?? 2)));
                $bid = (int) Db::wert('SELECT order_id FROM payments WHERE id = ?', [$zid], 0);
                $_SESSION['gut'] = match (Mahnung::schicken($zid, $stufe)) {
                    'raus'           => Mahnung::name($stufe) . ' ist raus — der Kunde hat sie samt frischem Zahlungslink.',
                    'versand_fehler' => 'Sie wäre dran gewesen, aber der Versand hat nicht geklappt — siehe Nachrichten.',
                    default          => 'Nichts zu tun: Entweder ist die Rate bezahlt, oder diese Stufe ging schon raus.',
                };
                // Monatsraten aus der Betreuung haben keine Bestellung; sie
                // tragen ihr Ziel im Feld "zurueck". Die 'bestellungen/0'
                // hier ist nur die Vorgabe fuer den alten Weg.
                zurueck($bid > 0 ? 'bestellungen/' . $bid : 'kunden/' . (int) Db::wert(
                    'SELECT a.customer_id FROM payments p JOIN abos a ON a.id = p.abo_id WHERE p.id = ?', [$zid], 0));

            case 'restzahlung_anfordern':
                require_once __DIR__ . '/src/Nachricht.php';
                $bid = (int) $_POST['id'];
                $pr = Db::one('SELECT id FROM projects WHERE order_id = ?', [$bid]);
                if (!$pr) { throw new RuntimeException('Zu dieser Bestellung gibt es kein Projekt.'); }
                $_SESSION['gut'] = Nachricht::restzahlungAnfordern((int) $pr['id'])
                    ? 'Die Restzahlung ist angefordert — der Kunde hat die E-Mail mit dem Zahlungslink.'
                    : 'Nichts zu tun: Entweder ist nichts mehr offen, oder die Anforderung ging schon raus.';
                zurueck('bestellungen/' . $bid);

            case 'nachricht_senden':
                require_once __DIR__ . '/src/Nachricht.php';
                $pid = (int) $_POST['id'];
                Nachricht::schreiben($pid, (string) ($_POST['text'] ?? ''), 'admin',
                    (string) ($_POST['betreff'] ?? ''));
                $_SESSION['gut'] = 'Nachricht ist raus — der Kunde bekommt sie auch per E-Mail.';
                zurueck('projekte/' . $pid);

            case 'datei_hoch':
                require_once __DIR__ . '/src/Ablage.php';
                $pid = (int) $_POST['id'];
                $pr = Db::one('SELECT * FROM projects WHERE id = ?', [$pid]);
                if (!$pr) { throw new RuntimeException('Projekt nicht gefunden.'); }
                Ablage::annehmen($_FILES['datei'] ?? [], $pid, (int) $pr['customer_id'], 'admin');
                Events::protokoll('datei_hoch', 'Datei hinterlegt: ' . ($_FILES['datei']['name'] ?? ''),
                    (int) $pr['customer_id'], $pr['order_id'] !== null ? (int) $pr['order_id'] : null, $pid);
                $_SESSION['gut'] = 'Datei liegt beim Projekt — der Kunde sieht sie auf seiner Seite.';
                zurueck('projekte/' . $pid);

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
                zurueck('projekte/' . (int) $d['project_id']);

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
                zurueck('projekte/' . $pid);

            case 'website_pruefen':
                require_once __DIR__ . '/src/Monitoring.php';
                $wid = (int) $_POST['id'];
                $e = Monitoring::eine($wid);
                if ($e === null) { throw new RuntimeException('Website nicht gefunden.'); }
                $_SESSION['gut'] = $e['ok']
                    ? 'Erreichbar — ' . (int) $e['pruefung']['ms'] . ' ms, Status ' . (int) $e['pruefung']['status'] . '.'
                    : 'Nicht erreichbar: ' . ($e['pruefung']['fehler'] ?? 'unbekannter Grund');
                zurueck($_POST['zurueck'] ?? 'monitoring');

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
                zurueck('projekte/' . $pid);

            case 'aufgabe_weg':
                $aid = (int) $_POST['id'];
                $a = Db::one('SELECT project_id FROM tasks WHERE id = ?', [$aid]);
                if (!$a) { throw new RuntimeException('Aufgabe nicht gefunden.'); }
                Db::run('DELETE FROM tasks WHERE id = ?', [$aid]);
                zurueck('projekte/' . (int) $a['project_id']);

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
                zurueck('projekte/' . $pid);

            case 'aufgabe_umschalten':
                $aid = (int) $_POST['id'];
                $a = Db::one('SELECT * FROM tasks WHERE id = ?', [$aid]);
                if (!$a) { throw new RuntimeException('Aufgabe nicht gefunden.'); }
                Db::update('tasks', $aid, ['done' => (int) $a['done'] === 1 ? 0 : 1]);
                zurueck('projekte/' . (int) $a['project_id']);

            case 'nachrichten_gelesen':
                $pid = (int) $_POST['id'];
                Db::run("UPDATE messages SET read_at = NOW() WHERE project_id = ? AND read_at IS NULL AND sender = 'kunde'", [$pid]);
                zurueck('projekte/' . $pid);

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
                zurueck('projekte/' . $pid);

            case 'fragebogen_erinnern':
                require_once __DIR__ . '/src/Onboarding.php';
                $anzahl = Onboarding::erinnerungen((int) ($_POST['tage'] ?? Onboarding::ERINNERUNG_NACH_TAGEN));
                $_SESSION['gut'] = $anzahl === 0
                    ? 'Es war keine Erinnerung fällig.'
                    : "Erinnerungen verschickt: $anzahl.";
                weiter('onboarding');

            case 'meldungen_gelesen':
                Db::run('UPDATE notifications SET read_at = NOW() WHERE read_at IS NULL');
                zurueck('benachrichtigungen');

            /* Meldungen wegraeumen. Eine Meldung ist ein Zuruf, kein Beleg —
               was passiert ist, steht im Verlauf und in der Pruefspur, und die
               bleiben unangetastet. Deshalb darf sie weg, sobald sie erledigt
               ist. */
            case 'meldung_gelesen':
                Db::run('UPDATE notifications SET read_at = NOW() WHERE id = ? AND read_at IS NULL',
                    [(int) ($_POST['id'] ?? 0)]);
                zurueck('benachrichtigungen');

            case 'meldung_weg':
                Db::run('DELETE FROM notifications WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
                zurueck('benachrichtigungen');

            case 'meldungen_weg':
                // Nur Gelesenes. Eine Warnung, die noch niemand gesehen hat,
                // raeumt dieser Knopf nicht weg — das waere genau der stille
                // Ausfall, gegen den die Meldungen da sind.
                $anzahl = Db::run('DELETE FROM notifications WHERE read_at IS NOT NULL')->rowCount();
                $_SESSION['gut'] = $anzahl === 0
                    ? 'Es war nichts Gelesenes da.'
                    : $anzahl . ' gelesene Meldung(en) gelöscht.';
                zurueck('benachrichtigungen');
        }
        throw new RuntimeException('Unbekannter Vorgang.');
    } catch (Throwable $e) {
        $_SESSION['fehler'] = $e->getMessage();
        weiter($_POST['zurueck'] ?? '');
    }
}

/* ---------- Ansichten ---------- */
switch ($route) {
    /* ------------------------------------------------------------------
       Heute und Vorgaenge — die neue, kuerzere Art durch dieselben Daten.
       Die alten Seiten bleiben daneben bestehen; hier entsteht nichts
       Neues, es wird nur anders gebuendelt.
       ------------------------------------------------------------------ */
    case '':
    case 'heute':
        require_once __DIR__ . '/src/Vorgang.php';
        require_once __DIR__ . '/src/Mail.php';
        require_once __DIR__ . '/src/Anfrage.php';
        $arbeit = sicher(static fn() => Vorgang::arbeitsliste(), ['du' => [], 'kunde' => [], 'ruht' => []]);
        $offen  = 0;
        foreach (array_merge($arbeit['du'], $arbeit['kunde'], $arbeit['ruht']) as $v) {
            $offen += (int) $v['offen_cent'];
        }
        ansicht('heute', [
            'liste'     => $arbeit,
            'offenGeld' => $offen,
            /* Was auf einen zukommt -- nicht, was gewesen ist. Ablaufende
               Angebote, liegengebliebene Fragebogen, fehlendes Material,
               anstehende Restzahlungen. Ist nichts faellig, kommt eine leere
               Liste zurueck und die Seite schweigt. */
            'faellig'   => sicher(static fn() => Vorgang::faellig(), []),
            // Nur das, was wirklich klemmt. Info-Meldungen gehoeren nicht
            // auf eine Arbeitsliste — sonst sieht man den Fehler nicht mehr.
            'stoerungen' => sicher(static fn() => Db::all(
                "SELECT * FROM notifications
                  WHERE read_at IS NULL AND level IN ('warnung','schlecht')
                  ORDER BY id DESC LIMIT 8")),
        ]);
        break;

    case 'vorgaenge':
        require_once __DIR__ . '/src/Vorgang.php';
        require_once __DIR__ . '/src/Umfang.php';    // die Ansicht schreibt Haken als Wörter
        require_once __DIR__ . '/src/Baukasten.php'; // und den Bedarf auf Deutsch
        require_once __DIR__ . '/src/Texte.php';
        require_once __DIR__ . '/src/Mail.php';
        require_once __DIR__ . '/src/Anfrage.php';
        require_once __DIR__ . '/src/Nachricht.php';
        if ($unter !== null && $unter !== '') {
            $v = sicher(static fn() => Vorgang::laden((string) $unter), null);
            if (!$v) { http_response_code(404); exit('Diesen Vorgang gibt es nicht.'); }
            // Wer die Seite oeffnet, hat das Gespraech gelesen.
            if ($v['kunde_id']) {
                sicher(static fn() => Db::run(
                    "UPDATE messages SET read_at = NOW()
                      WHERE customer_id = ? AND sender = 'kunde' AND read_at IS NULL",
                    [(int) $v['kunde_id']]), 0);
            }
            require_once __DIR__ . '/src/Texte.php';
            require_once __DIR__ . '/src/Onboarding.php';
            require_once __DIR__ . '/src/Vorlage.php';
            $vkid = (int) ($v['kunde_id'] ?? 0);
            ansicht('vorgang', [
                'v' => $v,
                'vorlagen' => $vkid > 0 ? sicher(static fn() => Vorlage::fuer($vkid), []) : [],
                'kennung'  => $vkid > 0 ? sicher(static fn() => Vorlage::kennung($vkid), '') : '',
                'pakete' => sicher(static fn() => Db::all(
                    'SELECT * FROM packages WHERE active = 1 ORDER BY sort, price_cents')),
            ]);
            break;
        }
        ansicht('vorgaenge', ['liste' => sicher(static fn() => Vorgang::alle(), [])]);
        break;

    /* Die Zahlen bleiben, sie sind nur nicht mehr das Erste, was man sieht.
       "Was ist zu tun" gehoert auf die Startseite, "wie lief es" eine Ebene
       tiefer -- die Leiste oben sagt ohnehin das eine, und die Startseite
       sollte die Liste sein, die sie zusammenfasst. */
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
        // Die Steuerbegriffe richten sich nach der Sprache des Kunden.
        require_once __DIR__ . '/src/Kunde.php';
        if ($unter === 'neu') { ansicht('kunde_form', ['k' => null]); break; }
        if ($id !== null) {
            $k = Db::one('SELECT * FROM customers WHERE id = ?', [$id]);
            if (!$k) { http_response_code(404); exit('Kunde nicht gefunden.'); }
            if (($teile[2] ?? '') === 'bearbeiten') { ansicht('kunde_form', ['k' => $k]); break; }
            // Wer die Akte oeffnet, hat gelesen. Dasselbe passiert beim
            // Oeffnen eines Projekts — nur gab es fuer die Nachrichten ohne
            // Projekt bisher keine Stelle, an der es passiert waere.
            require_once __DIR__ . '/src/Nachricht.php';
            require_once __DIR__ . '/src/Kunde.php';
            require_once __DIR__ . '/src/Vorlage.php';
            sicher(static fn() => Nachricht::gelesenKunde($id), 0);
            ansicht('kunde', [
                'k' => $k,
                // Was einer Loeschung im Weg steht und was mitginge — beides
                // gehoert vor den Knopf, nicht in eine Fehlermeldung danach.
                'riegel' => sicher(static fn() => Kunde::riegel($id), []),
                'umfang' => sicher(static fn() => Kunde::umfang($id), []),
                'belege' => sicher(static fn() => Kunde::belege($id), []),
                'anonym' => sicher(static fn() => Kunde::istAnonym($k), false),
                'bestellungen' => Db::all('SELECT * FROM orders WHERE customer_id = ? ORDER BY id DESC', [$id]),
                'projekte' => Db::all('SELECT * FROM projects WHERE customer_id = ? ORDER BY id DESC', [$id]),
                // LINKS VERBUNDEN, NICHT FEST
                // Monatsraten aus der Betreuung haengen an keiner Bestellung.
                // Mit dem alten festen JOIN auf orders fielen sie hier heraus
                // — der Kunde hatte bezahlt, in seiner Akte stand nichts.
                'zahlungen' => Db::all(
                    'SELECT p.*, COALESCE(o.order_no, CONCAT(\'Betreuung \', p.abrechnungsmonat)) AS order_no
                       FROM payments p
                       LEFT JOIN orders o ON o.id = p.order_id
                       LEFT JOIN abos   a ON a.id = p.abo_id
                      WHERE COALESCE(o.customer_id, a.customer_id) = ?
                      ORDER BY p.id DESC', [$id]),
                'aktivitaeten' => Db::all('SELECT * FROM activities WHERE customer_id = ? ORDER BY id DESC LIMIT 20', [$id]),
                'nachrichten' => sicher(static fn() => Db::all(
                    'SELECT * FROM messages WHERE customer_id = ? ORDER BY id ASC LIMIT 100', [$id])),
                'dateien' => sicher(static fn() => Db::all(
                    'SELECT * FROM files WHERE customer_id = ? ORDER BY id DESC LIMIT 60', [$id])),
                // Vorlagen samt Betreff, in der Sprache des Kunden und mit
                // eingesetzten Angaben. Siehe app/src/Vorlage.php.
                'vorlagen' => sicher(static fn() => Vorlage::fuer($id), []),
                'kennung'  => sicher(static fn() => Vorlage::kennung($id), ''),
                // Kommt jemand vom Knopf "Link schicken", ist die Vorlage
                // schon gewaehlt, wenn er unten ankommt.
                'vorwahl'  => preg_replace('~[^a-z_]~', '', strtolower((string) ($_GET['vorlage'] ?? ''))),
            ]);
            break;
        }
        $q = trim((string) ($_GET['q'] ?? ''));
        /* EIN PLATZHALTER, NICHT VIER
           ----------------------------------------------------------------
           Hier stand "c.name LIKE :q OR c.email LIKE :q OR c.company LIKE :q".
           Derselbe Name dreimal — und die Verbindung laeuft mit
           ATTR_EMULATE_PREPARES = false, wo MariaDB genau das nicht kann.
           Die Kundensuche warf also bei jedem eingetippten Wort einen
           Fehler 500. Aufgefallen ist es nie, weil die Liste ohne
           Suchbegriff tadellos laedt.

           CONCAT_WS statt vieler ODER: ein Platzhalter, und die durchsuchten
           Felder stehen als eine Liste da, die man beim Lesen sieht. Als
           Nebenwirkung findet "Mario Trattoria" den Kunden auch dann, wenn
           das eine im Namen und das andere in der Firma steht. */
        $wo = $q !== ''
            ? "WHERE CONCAT_WS(' ', c.kundennr, c.name, c.email, c.company) LIKE :q"
            : '';
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

    case 'baukasten':
        require_once __DIR__ . '/src/Baukasten.php';
        sicher(static fn() => Baukasten::sicherstellen());
        require_once __DIR__ . '/src/Einfuehrung.php';
        ansicht('baukasten', [
            'liste' => sicher(static fn() => Db::all(
                'SELECT * FROM bausteine ORDER BY sortierung, id'), []),
            'phase' => sicher(static fn() => [
                'laeuft'    => Einfuehrung::laeuft(),
                'zaehler'   => Einfuehrung::zaehler(),
                'ziel'      => Einfuehrung::ziel(),
                'rest'      => Einfuehrung::restplaetze(),
                'erhoehung' => Einfuehrung::erhoehung(),
                'erreicht'  => Einfuehrung::erreicht(),
                'vorschau'  => Einfuehrung::erreicht() ? Einfuehrung::vorschau() : [],
            ], ['laeuft' => false]),
        ]);
        break;

    case 'angebote':
        require_once __DIR__ . '/src/Baukasten.php';
        require_once __DIR__ . '/src/Angebot.php';
        if ($id !== null) {
            $a = sicher(static fn() => Db::one(
                'SELECT a.*, c.name AS kunde FROM angebote a
                   JOIN customers c ON c.id = a.customer_id WHERE a.id = ?', [$id]), null);
            if (!$a) { http_response_code(404); exit('Angebot nicht gefunden.'); }
            /* Ist es raus, gehoert das Schreibfeld gleich daneben: Der
               Kunde bekommt den Link nicht von selbst, und ein Angebot, das
               niemand kennt, ist keins. Die Vorlage steht vorgewaehlt da. */
            require_once __DIR__ . '/src/Vorlage.php';
            ansicht('angebot', [
                'a'          => $a,
                'positionen' => sicher(static fn() => Angebot::positionen($id), []),
                'katalog'    => sicher(static fn() => Baukasten::katalog(), []),
                'vorlagen'   => sicher(static fn() => Vorlage::fuer((int) $a['customer_id']), []),
                'kennung'    => (string) sicher(static fn() => Vorlage::kennung((int) $a['customer_id']), ''),
            ]);
            break;
        }
        ansicht('angebote', ['liste' => sicher(static fn() => Db::all(
            "SELECT a.*, c.name AS kunde FROM angebote a
               LEFT JOIN customers c ON c.id = a.customer_id
              ORDER BY FIELD(a.status,'entwurf','gesendet','angenommen','abgelehnt','abgelaufen','zurueckgezogen'),
                       a.created_at DESC LIMIT 200"), [])]);
        break;

    case 'empfehlungen':
        require_once __DIR__ . '/src/Empfehlung.php';
        ansicht('empfehlungen', [
            'offen'  => sicher(static fn() => Empfehlung::offeneNennungen(), []),
            /* Zeilen, auf die nichts mehr zeigt -- Reste aus der Zeit, bevor
               Empfehlungen mit dem Kunden gingen, oder aus einem Probelauf. */
            'verwaist' => sicher(static fn() => Empfehlung::aufraeumen(false), 0),
            'liste'  => sicher(static fn() => Db::all(
                "SELECT e.*, ke.name AS empfehler, ke.rabatt_prozent, ke.rabatt_bis,
                        kg.name AS geworbener
                   FROM empfehlungen e
                   LEFT JOIN customers ke ON ke.id = e.empfehler_id
                   LEFT JOIN customers kg ON kg.id = e.geworbener_id
                  ORDER BY e.created_at DESC LIMIT 200"), []),
            'kunden' => sicher(static fn() => Db::all(
                'SELECT id, name, company FROM customers ORDER BY name'), []),
            'prozent' => Empfehlung::prozent(),
            'monate'  => Empfehlung::monate(),
        ]);
        break;

    case 'bedarf':
        require_once __DIR__ . '/src/Baukasten.php';
        require_once __DIR__ . '/src/Texte.php';   // die Ansichten schreiben die Antworten deutsch
        require_once __DIR__ . '/src/Bedarf.php';
        if ($id !== null) {
            $b = Db::one('SELECT * FROM bedarf WHERE id = ?', [$id]);
            if (!$b) { http_response_code(404); exit('Bedarf nicht gefunden.'); }
            $antworten = Bedarf::antworten($b);
            $katalog   = Baukasten::katalog(false);
            $aktive    = Baukasten::katalog();
            $rechnung  = Baukasten::rechnen($antworten, $aktive);

            /* Der Vorschlagspreis und die fertige Nachricht dazu. Beide
               entstehen erst hier und werden nirgends gespeichert: Sie sind
               eine Ansicht auf den heutigen Katalog, keine Zusage. Wer sie
               speichern wuerde, haette morgen eine zweite Wahrheit neben dem
               Angebot. */
            $vorschlag = Baukasten::vorschlag($rechnung, $aktive);
            /* Das Briefing zum Kopieren. Braucht keinen Kunden — es ist eine
               Ansicht auf die Antworten, kein Vorgang. */
            $bauprompt = (string) sicher(
                static fn() => Bedarf::bauprompt($b, $antworten, $vorschlag, $aktive), '');
            /* Ein Bedarf zeigt auf einen Kunden -- der aber geloescht sein
               kann, etwa nach einem Testlauf. Vorher stand dann trotzdem
               "Zum Kunden" da und darunter ein Sendeformular, das ins Leere
               gegangen waere. Einmal nachsehen ist billiger als der Fehler
               danach. */
            $kundeDa = $b['customer_id'] && (int) sicher(static fn() => Db::wert(
                'SELECT COUNT(*) FROM customers WHERE id = ?', [(int) $b['customer_id']], 0), 0) > 0;

            $preisnachricht = ['betreff' => '', 'text' => ''];
            $vorlagen = [];
            $kennung  = '';
            if ($kundeDa && $vorschlag['summe_cents'] > 0) {
                require_once __DIR__ . '/src/Vorlage.php';
                $preisnachricht = sicher(
                    static fn() => Bedarf::preisnachricht($b, $vorschlag, $aktive),
                    ['betreff' => '', 'text' => '']);
                $vorlagen = sicher(static fn() => Vorlage::fuer((int) $b['customer_id']), []);
                $kennung  = (string) sicher(static fn() => Vorlage::kennung((int) $b['customer_id']), '');
            }

            ansicht('bedarf', [
                'b'         => $b,
                'antworten' => $antworten,
                'katalog'   => $katalog,
                'rechnung'  => $rechnung,
                'vorschlag' => $vorschlag,
                'bauprompt' => $bauprompt,
                'nachricht' => $preisnachricht,
                'vorlagen'  => $vorlagen,
                'kennung'   => $kennung,
                'kundeDa'   => $kundeDa,
                'angebotId' => (int) sicher(static fn() => Db::wert(
                    'SELECT id FROM angebote WHERE bedarf_id = ? LIMIT 1', [$id], 0), 0),
            ]);
            break;
        }
        /* Jeder Aufruf des Konfigurators legt eine Zeile an -- auch der, bei
           dem jemand nur kurz hineinsieht und sofort wieder geht. Diese
           Zeilen sagen nichts und waren nach einem Tag Testen schon in der
           Ueberzahl. Sie bleiben stehen (jemand, der bis Schritt vier kommt
           und dann aufhoert, ist ein Hinweis), aber in der Liste steht nur
           noch ihre Anzahl. Sichtbar ist, wer etwas angekreuzt hat. */
        $leerFilter = "(antworten IS NULL OR antworten = '' OR antworten = '[]' OR antworten = '{}')";
        ansicht('bedarfe', [
            'liste' => sicher(static fn() => Db::all(
                "SELECT * FROM bedarf
                  WHERE status <> 'offen' OR NOT $leerFilter
                  ORDER BY FIELD(status,'abgesendet','angebot','offen','verworfen'), created_at DESC
                  LIMIT 200"), []),
            'leer' => (int) sicher(static fn() => Db::wert(
                "SELECT COUNT(*) FROM bedarf WHERE status = 'offen' AND $leerFilter", [], 0), 0),
        ]);
        break;

    case 'bestellungen':
        require_once __DIR__ . '/src/Mail.php';   // die Ansicht fragt, ob der Link schon raus ist
        if ($unter === 'neu') {
            ansicht('bestellung_form', [
                'kunden' => Db::all('SELECT id, name, company, email FROM customers ORDER BY name'),
                /* Liegt fuer einen Kunden schon ein Angebot, ist das die
                   bessere Grundlage als ein Paket plus abgetippter Preis:
                   Dort stehen Betrag, Posten und Anzahlung schon richtig. */
                'angebote' => sicher(static fn() => Db::all(
                    "SELECT a.id, a.nummer, a.summe_cents, a.status, c.name AS kunde
                       FROM angebote a JOIN customers c ON c.id = a.customer_id
                      WHERE a.order_id IS NULL AND a.status IN ('entwurf','gesendet')
                      ORDER BY a.id DESC LIMIT 20"), []),
                // Alles ausser der Betreuung. Die Betreuungspakete haben
                // keinen Einmalpreis — sie standen hier als Nullzeilen in der
                // Liste und haetten eine Bestellung ueber null Euro angelegt.
                // Sie werden in der Kundenakte als Vertrag gebucht, nicht hier.
                'pakete' => Db::all("SELECT * FROM packages WHERE active = 1
                                       AND (art IS NULL OR art <> 'betreuung')
                                     ORDER BY sort, price_cents"),
            ]);
            break;
        }
        require_once __DIR__ . '/src/Mahnung.php';   // die Ansicht zeigt den Mahnstand
        if ($id !== null) {
            $b = Db::one('SELECT o.*, c.name AS kunde, c.email AS kunde_email, c.sprache AS kunde_sprache
                            FROM orders o JOIN customers c ON c.id = o.customer_id WHERE o.id = ?', [$id]);
            if (!$b) { http_response_code(404); exit('Bestellung nicht gefunden.'); }
            /* Das Vertragsblatt, so wie der Kunde es bekommt. Damit laesst es
               sich nachschicken oder ausdrucken, ohne die Mail zu suchen. */
            /* Die Forderungsaufstellung — alles, was ein Anwalt fuer einen
               decreto ingiuntivo braucht, auf einem Blatt. */
            if (($teile[2] ?? '') === 'forderung') {
                require_once __DIR__ . '/src/Forderung.php';
                $daten = Forderung::pdf((int) $b['id']);
                if ($daten === '') { http_response_code(503); exit('Das Blatt ließ sich nicht bauen.'); }
                header('Content-Type: application/pdf');
                header('Content-Length: ' . strlen($daten));
                header('Content-Disposition: attachment; filename="' . Forderung::dateiname($b) . '"');
                header('X-Content-Type-Options: nosniff');
                echo $daten;
                exit;
            }
            if (($teile[2] ?? '') === 'vertrag') {
                require_once __DIR__ . '/src/Vertragsblatt.php';
                $daten = Vertragsblatt::pdf((int) $b['id']);
                if ($daten === '') { http_response_code(503); exit('Das Blatt ließ sich nicht bauen.'); }
                header('Content-Type: application/pdf');
                header('Content-Length: ' . strlen($daten));
                header('Content-Disposition: attachment; filename="'
                    . Vertragsblatt::dateiname($b, Vertragsblatt::sprache($b, ['sprache' => $b['kunde_sprache']])) . '"');
                header('X-Content-Type-Options: nosniff');
                echo $daten;
                exit;
            }
            /* Die Ansicht zeigt vor dem Senden, was rausgeht -- und baut den
               Text mit derselben Funktion, die ihn danach verschickt. Zwei
               Fassungen desselben Briefes waeren zwei Wahrheiten. */
            require_once __DIR__ . '/src/Texte.php';
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
        // Derselbe Fehler wie in der Kundensuche: dreimal :q, und die
        // Bestellsuche antwortete mit 500, sobald man etwas eintippte.
        if ($q !== '')  {
            $bed[] = "CONCAT_WS(' ', o.order_no, c.name, c.kundennr, o.package_name) LIKE :q";
            $args['q'] = "%$q%";
        }
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
        require_once __DIR__ . '/src/Texte.php';   // die Ansicht beschriftet den Fragebogen
        require_once __DIR__ . '/src/Umfang.php';  // und vergleicht ihn mit dem Angebot
        require_once __DIR__ . '/src/Standard.php';// und zeigt, wohin der Briefing-Knopf fuehrt
        require_once __DIR__ . '/src/Abnahme.php'; // und was die letzte Pruefung ergab
        if ($id !== null) {
            $p = Db::one('SELECT p.*, c.name AS kunde, c.email AS kunde_email, o.order_no
                          FROM projects p JOIN customers c ON c.id = p.customer_id
                          LEFT JOIN orders o ON o.id = p.order_id WHERE p.id = ?', [$id]);
            if (!$p) { http_response_code(404); exit('Projekt nicht gefunden.'); }
            ansicht('projekt', [
                'p' => $p,
                'website' => Db::one('SELECT * FROM websites WHERE project_id = ?', [$id]),
                'fragebogen' => Db::one('SELECT * FROM questionnaires WHERE project_id = ?', [$id]),
                /* Der Unterschied zwischen Beauftragtem und Angekreuztem.
                   Null, wenn beides zusammenpasst -- dann steht im Projekt
                   auch nichts davon. */
                'mehrbedarf' => sicher(static fn() => Umfang::mehrbedarf($id), null),
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
        require_once __DIR__ . '/src/Texte.php';   // die Ansicht schreibt die Antworten deutsch
        if ($id !== null) {
            $a = Db::one('SELECT * FROM anfragen WHERE id = ?', [$id]);
            if (!$a) { http_response_code(404); exit('Anfrage nicht gefunden.'); }

            /* Kam die Anfrage aus dem Konfigurator, ist ihre Nachricht keine
               geschriebene Nachricht, sondern eine erzeugte Zusammenfassung —
               und zwar in der Sprache des Kunden, weil er sie auf seiner
               eigenen Seite liest. Fuer die Verwaltung wird sie hier frisch
               auf Deutsch gerechnet. Vorher stand hier Italienisch, und wer
               die Anfrage las, musste raten, was angekreuzt war. */
            $bedarf = sicher(static fn() => Db::one(
                'SELECT * FROM bedarf WHERE anfrage_id = ? LIMIT 1', [$id]), null);
            $bAntworten = [];
            $bVorschlag = null;
            $bKatalog   = [];
            if ($bedarf) {
                require_once __DIR__ . '/src/Baukasten.php';
                require_once __DIR__ . '/src/Bedarf.php';
                $bAntworten = sicher(static fn() => Bedarf::antworten($bedarf), []);
                $bKatalog   = sicher(static fn() => Baukasten::katalog(false), []);
                $aktiv      = sicher(static fn() => Baukasten::katalog(), []);
                $bVorschlag = sicher(static fn() => Baukasten::vorschlag(
                    Baukasten::rechnen($bAntworten, $aktiv), $aktiv), null);
            }

            ansicht('anfrage', [
                'a'          => $a,
                'bedarf'     => $bedarf,
                'bAntworten' => $bAntworten,
                'bKatalog'   => $bKatalog,
                'bVorschlag' => $bVorschlag,
                'pakete'     => Db::all(
                    'SELECT id, name, price_cents, currency FROM packages WHERE active = 1 ORDER BY sort, price_cents'),
            ]);
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
        ansicht('benachrichtigungen', [
            // Ungelesenes zuerst, darin das Neueste oben. Nach Nummer allein
            // sortiert rutscht eine frische Warnung unter alte gelesene
            // Zeilen — auf einer Liste, die man wegen der Warnungen aufmacht.
            'liste'   => Db::all(
                'SELECT * FROM notifications ORDER BY read_at IS NOT NULL, id DESC LIMIT 200'),
            'offen'   => (int) Db::wert('SELECT COUNT(*) FROM notifications WHERE read_at IS NULL'),
            'gelesen' => (int) Db::wert('SELECT COUNT(*) FROM notifications WHERE read_at IS NOT NULL'),
        ]);
        break;

    case 'suche':
        $q = trim((string) ($_GET['q'] ?? ''));
        $t = "%$q%";
        /* Angebote und Bedarf fehlten -- ausgerechnet die beiden, nach denen
           man am ehesten sucht, wenn man eine Nummer aus einer Mail hat.
           Der Firmenname zaehlt beim Kunden mit; wer "Trattoria" tippt,
           meint selten den Vornamen. */
        ansicht('suche', ['q' => $q, 'treffer' => $q === '' ? [] : [
            'Kunden' => sicher(fn() => Db::all(
                'SELECT id, COALESCE(NULLIF(company,\'\'), name) AS titel,
                        TRIM(CONCAT(COALESCE(kundennr, \'\'), \' · \', email)) AS neben
                   FROM customers
                  WHERE name LIKE ? OR email LIKE ? OR company LIKE ? OR kundennr LIKE ?
                  LIMIT 10', [$t,$t,$t,$t])),
            'Angebote' => sicher(fn() => Db::all(
                'SELECT a.id, a.nummer AS titel, CONCAT(c.name, " · ", a.status) AS neben
                   FROM angebote a JOIN customers c ON c.id = a.customer_id
                  WHERE a.nummer LIKE ? OR a.titel LIKE ? OR c.name LIKE ? OR c.company LIKE ?
                  ORDER BY a.id DESC LIMIT 10', [$t,$t,$t,$t])),
            'Bedarf' => sicher(fn() => Db::all(
                'SELECT id, COALESCE(NULLIF(firma,\'\'), name) AS titel, email AS neben
                   FROM bedarf WHERE name LIKE ? OR firma LIKE ? OR email LIKE ?
                  ORDER BY id DESC LIMIT 10', [$t,$t,$t])),
            'Bestellungen' => sicher(fn() => Db::all(
                'SELECT id, order_no AS titel, package_name AS neben FROM orders
                  WHERE order_no LIKE ? OR package_name LIKE ? LIMIT 10', [$t,$t])),
            'Projekte' => sicher(fn() => Db::all(
                'SELECT id, name AS titel, status AS neben FROM projects WHERE name LIKE ? LIMIT 10', [$t])),
            'Websites' => sicher(fn() => Db::all(
                'SELECT id, domain AS titel, status AS neben FROM websites WHERE domain LIKE ? LIMIT 10', [$t])),
            'Rechnungen' => sicher(fn() => Db::all(
                'SELECT id, invoice_no AS titel, status AS neben FROM invoices WHERE invoice_no LIKE ? LIMIT 10', [$t])),
        ]]);
        break;

    case 'zahlungen':
        /* Die Verbindung war ein INNER JOIN ueber orders — eine Betreuungsrate
           haengt aber an einem Vertrag, nicht an einer Bestellung, und waere
           damit aus der Zahlungsliste ganz verschwunden. */
        ansicht('zahlungen', ['liste' => Db::all(
            "SELECT p.*, o.order_no, a.paket_name AS abo_paket,
                    c.name AS kunde, c.id AS kunde_id
             FROM payments p
             LEFT JOIN orders    o ON o.id = p.order_id
             LEFT JOIN abos      a ON a.id = p.abo_id
             LEFT JOIN customers c ON c.id = COALESCE(o.customer_id, a.customer_id)
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

    case 'werkstatt':
        /* Alles, was gebaut wurde oder gebaut wird, auf einem Blatt. Die
           Angaben kommen aus drei Tabellen, die es laengst gibt — neu ist
           nur, dass sie nebeneinander stehen. */
        require_once __DIR__ . '/src/Standard.php';
        ansicht('werkstatt', [
            'einrichtung' => sicher(static fn() => Standard::einrichtungsstand(),
                ['punkte' => [], 'offen' => [], 'gesamt' => 0]),
            'claudeZiel'  => sicher(static fn() => Standard::claudeZiel(), 'https://claude.ai/new'),
            'liste' => sicher(static fn() => Db::all(
            "SELECT p.id, p.name, p.status, p.progress, p.deadline, p.preview_url,
                    p.briefing, p.briefing_am, p.chat_url, p.abnahme, p.abnahme_am,
                    c.id AS kunde_id, c.name AS kunde, c.company, c.kundennr,
                    w.url AS live, w.domain, w.last_status, w.ssl_expires_at
               FROM projects p
               JOIN customers c ON c.id = p.customer_id
               LEFT JOIN websites w ON w.project_id = p.id
              ORDER BY FIELD(p.status,'abgeschlossen') ASC,
                       p.deadline IS NULL, p.deadline ASC, p.id DESC"), []),
        ]);
        break;

    case 'muster':
        require_once __DIR__ . '/src/Muster.php';
        if ($unter === 'neu') { ansicht('muster_form', ['m' => null]); break; }
        if ($id !== null) {
            $m = Muster::eines($id);
            if (!$m) { http_response_code(404); exit('Baustein nicht gefunden.'); }
            ansicht('muster_form', ['m' => $m]);
            break;
        }
        ansicht('muster', ['liste' => Muster::alle()]);
        break;

    case 'standard':
        require_once __DIR__ . '/src/Standard.php';
        ansicht('standard', [
            'text'      => Standard::text(),
            'eigener'   => Standard::eigener(),
            'gesehenAm' => Standard::gesehenAm(),
            'gesehen'   => Standard::gesehen(),
            'anhaengen' => Standard::anhaengen(),
            'projekt'   => Standard::claudeProjekt(),
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
            'zuruf'      => sicher(static function () {
                require_once __DIR__ . '/src/Zuruf.php';
                return ['an' => Zuruf::an(), 'nummer' => Zuruf::nummer(),
                        'schluessel' => Zuruf::hatSchluessel(), 'zuletzt' => Zuruf::zuletzt()];
            }, ['an' => false, 'nummer' => '', 'schluessel' => false, 'zuletzt' => '']),
            'versand'    => sicher(static function () {
                require_once __DIR__ . '/src/Versand.php';
                return ['herkunft' => Versand::herkunft(), 'ende' => Versand::schluesselEnde(),
                        'from' => Versand::absender(), 'name' => Versand::name(),
                        'to' => Versand::meldungenAn()];
            }, ['herkunft' => 'keine', 'ende' => '', 'from' => '', 'name' => '', 'to' => '']),
            'versandTest' => $_SESSION['versand_test'] ?? null,
        ]);
        unset($_SESSION['versand_test']);
        break;

    case 'stimmen':
        require_once __DIR__ . '/src/Stimme.php';
        ansicht('stimmen', ['liste' => sicher(static fn() => Stimme::alle(), [])]);
        break;

    case 'abos':
        require_once __DIR__ . '/src/Abo.php';
        ansicht('abos', [
            'liste' => sicher(static fn() => Abo::alle(), []),
            'monatlich' => (int) sicher(static fn() => Abo::monatlich(), 0),
        ]);
        break;

    case 'ausgaben':
        require_once __DIR__ . '/src/Ausgabe.php';

        if ($id !== null) {
            $a = sicher(static fn() => Ausgabe::eine($id), null);
            if (!$a) { http_response_code(404); exit('Diese Ausgabe gibt es nicht.'); }

            // Die hinterlegte Datei. Sie liegt ausserhalb des Webs — der
            // einzige Weg dorthin fuehrt durch die Anmeldung.
            if (($teile[2] ?? '') === 'datei') {
                $pfad = Ausgabe::dateipfad($a);
                if ($pfad === null) { http_response_code(404); exit('Zu diesem Beleg liegt keine Datei.'); }
                header('Content-Type: ' . ((string) ($a['mime'] ?? 'application/octet-stream')));
                header('Content-Length: ' . (string) filesize($pfad));
                header('Content-Disposition: inline; filename="'
                    . str_replace('"', '', (string) ($a['orig_name'] ?: $a['beleg_nr'])) . '"');
                header('X-Content-Type-Options: nosniff');
                readfile($pfad);
                exit;
            }
            ansicht('ausgabe_form', ['a' => $a, 'naechste' => (string) $a['beleg_nr']]);
            break;
        }

        if (($unter ?? '') === 'neu') {
            ansicht('ausgabe_form', ['a' => null, 'naechste' => sicher(static fn() => Ausgabe::naechsteNummer(), 'EA-…')]);
            break;
        }

        $jahre = sicher(static fn() => Ausgabe::jahre(), []);
        $jahr  = isset($_GET['jahr']) ? (int) $_GET['jahr'] : (int) ($jahre[0] ?? date('Y'));
        ansicht('ausgaben', [
            'jahre' => $jahre,
            'jahr'  => $jahr,
            'liste' => sicher(static fn() => Ausgabe::alle($jahr), []),
            'summe' => sicher(static fn() => Ausgabe::summe($jahr),
                              ['anzahl' => 0, 'brutto' => 0, 'rc_netto' => 0, 'rc_iva' => 0]),
        ]);
        break;

    case 'steuerakte':
        require_once __DIR__ . '/src/Steuerakte.php';
        $jahr = $id !== null ? (int) $id : 0;
        $was  = (string) ($teile[2] ?? '');

        // Die einzelnen Tabellen. Jede fuer sich abrufbar, weil der
        // Commercialista meistens genau eine davon will und nicht das
        // ganze Paket.
        $tabellen = [
            'verzeichnis'    => ['verzeichnis-',            static fn() => Steuerakte::verzeichnis($jahr)],
            'einnahmen'      => ['einnahmen-nach-zahlung-', static fn() => Steuerakte::einnahmenCsv($jahr)],
            'abgrenzung'     => ['abgrenzung-',             static fn() => Steuerakte::abgrenzungCsv($jahr)],
            'forderungen'    => ['offene-forderungen-',    static fn() => Steuerakte::forderungenCsv($jahr)],
            'ausgaben'       => ['ausgaben-',               static fn() => Steuerakte::ausgabenCsv($jahr)],
            'reversecharge'  => ['reverse-charge-',         static fn() => Steuerakte::reverseChargeCsv($jahr)],
        ];
        if ($jahr > 0 && isset($tabellen[$was])) {
            [$vorne, $bauen] = $tabellen[$was];
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $vorne . $jahr . '.csv"');
            echo $bauen();
            exit;
        }
        if ($jahr > 0 && $was === 'paket') {
            // Liegt das Paket vom naechtlichen Lauf schon fertig da und ist
            // es von heute, geht es sofort raus. Sonst wird es gebaut — das
            // kann bei vielen Belegen dauern, weil jedes PDF einzeln
            // entsteht, und der Server darf dabei nicht nach dreissig
            // Sekunden aussteigen und eine halbe Datei ausliefern.
            @set_time_limit(300);
            $fertig = sicher(static fn() => Steuerakte::archiv($jahr), ['stand' => null, 'datei' => '']);
            $frisch = $fertig['stand'] !== null && strtotime((string) $fertig['stand']) > strtotime('-12 hours');
            $datei  = $frisch ? (string) $fertig['datei'] : Steuerakte::paket($jahr);
            header('Content-Type: application/zip');
            header('Content-Length: ' . (string) filesize($datei));
            header('Content-Disposition: attachment; filename="' . Steuerakte::paketname($jahr) . '"');
            readfile($datei);
            if (!$frisch) { @unlink($datei); }
            exit;
        }

        $jahre = Steuerakte::jahre();
        // Auch ein Jahr, in dem es nur Ausgaben gibt, ist ein Jahr.
        require_once __DIR__ . '/src/Ausgabe.php';
        foreach (sicher(static fn() => Ausgabe::jahre(), []) as $j) {
            if (!in_array($j, $jahre, true)) { $jahre[] = $j; }
        }
        rsort($jahre);
        $uebersicht = $ausgaben = $grenzen = $archiv = [];
        foreach ($jahre as $j) {
            $uebersicht[$j] = sicher(static fn() => Steuerakte::zusammenfassung($j), null);
            $ausgaben[$j]   = sicher(static fn() => Ausgabe::summe($j),
                                     ['anzahl' => 0, 'brutto' => 0, 'rc_netto' => 0, 'rc_iva' => 0]);
            $grenzen[$j]    = sicher(static fn() => Steuerakte::grenzen($j),
                                     ['summe' => 0, 'waehrung' => 'EUR', 'anteil' => 0.0, 'warnung' => null]);
            $archiv[$j]     = sicher(static fn() => Steuerakte::archiv($j), ['stand' => null, 'bytes' => 0]);
        }
        ansicht('steuerakte', [
            'jahre'      => $jahre,
            'uebersicht' => array_filter($uebersicht),
            'ausgaben'   => $ausgaben,
            'grenzen'    => $grenzen,
            'archiv'     => $archiv,
            'fristen'    => sicher(static fn() => Steuerakte::fristen(), []),
        ]);
        break;

    case 'monitoring':
        require_once __DIR__ . '/src/Monitoring.php';
        require_once __DIR__ . '/src/Cron.php';
        require_once __DIR__ . '/src/Zustellbarkeit.php';
        ansicht('monitoring', [
            // Der gespeicherte Befund, keine frische Abfrage: Eine haengende
            // Namensaufloesung darf diese Seite nicht festhalten.
            'zustell' => sicher(static fn() => Zustellbarkeit::stand(), []),
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
