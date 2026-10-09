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
/* CSP vorerst nur melden (Etappe 0b, 05.10.2026): blockiert nichts, sagt im Monitoring, was blockiert würde. */
Csp::melden('verwaltung');

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

/* ==========================================================================
   „MEHR" — WIE FORTGESCHRITTENES AUS DEM WEG GEHT, OHNE ZU FEHLEN

   Beide Funktionen umschliessen einen Teil einer Seite. Im einfachen Modus
   wird daraus eine Schublade: zugeklappt, beschriftet, einen Klick entfernt.
   Im vollen Modus geben sie nichts aus — der Inhalt steht dann offen da, und
   die Seite sieht aus wie vorher.

   Deshalb darf zwischen mehr_auf() und mehr_zu() alles stehen, was auch
   ohne sie dort stuende. Kein Inhalt zieht um, keiner verschwindet, und ein
   Formular darin funktioniert unveraendert -- eine zugeklappte <details>
   schickt ihre Felder mit.

       <?php mehr_auf('Mehr Möglichkeiten'); ?>
         ... Bloecke ...
       <?php mehr_zu(); ?>

   Der Titel sagt, was drinliegt, nicht dass es etwas gibt: „Was der Kunde
   sieht" ist brauchbar, „Erweitert" nicht.
   ========================================================================== */

/**
 * Öffnet die Schublade — oder gibt nichts aus, wenn alles offen stehen soll.
 *
 * $offen ist der Grund, warum daraus keine Falle wird: Liegt der Handgriff,
 * der gerade dran ist, in dieser Schublade, steht sie offen. Sonst waere der
 * eine Knopf, auf den die Fuehrung zeigt, hinter einem Klick versteckt --
 * und eine Fuehrung, die auf etwas Unsichtbares zeigt, ist keine.
 */
function mehr_auf(string $titel, int|string|null $hinweis = null, bool $offen = false): void {
    require_once __DIR__ . '/src/Modus.php';
    if (!Modus::einfach()) { return; }
    /* Der Hinweis darf eine Zahl sein oder ein Satzstueck ("3.742,50 € offen").
       Eine nackte 1 an einer Schublade ist keine Auskunft -- sie sagt, dass
       gezaehlt wurde, aber nicht was. Null und leer heissen: nichts zu sagen. */
    $text = is_int($hinweis) ? ($hinweis > 0 ? (string) $hinweis : '') : trim((string) $hinweis);
    printf('<details class="mehrblock"%s><summary><span>%s</span>%s</summary><div class="mehrblock__inhalt">',
        $offen ? ' open' : '',
        Fmt::h($titel),
        $text !== '' ? '<b>' . Fmt::h($text) . '</b>' : '');
}

/** Schliesst sie wieder. */
function mehr_zu(): void {
    require_once __DIR__ . '/src/Modus.php';
    if (!Modus::einfach()) { return; }
    echo '</div></details>';
}

/* ==========================================================================
   DIE DATEN EINES KUNDEN — AN EINER STELLE

   Die Kundenakte ist seit dem Umbau nicht mehr nur eine eigene Seite,
   sondern auch eine Schublade der Vorgangsseite. Zwei Aufrufer, eine
   Aufbereitung: Stuenden die Abfragen zweimal da, liefe die Schublade
   irgendwann einer Aenderung hinterher, und niemand wuesste, welche der
   beiden Ansichten stimmt.
   ========================================================================== */
function datenKunde(int $id, array $k): array {
    require_once __DIR__ . '/src/Kunde.php';
    require_once __DIR__ . '/src/Vorlage.php';
    return [

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
                'kundenMails' => sicher(static function () use ($id): array { require_once __DIR__ . '/src/KundeMails.php'; return KundeMails::liste($id); }, []),
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
    ];
}

/* Dasselbe fuer ein Projekt: eigene Seite und Schublade der Vorgangsseite
   lesen aus derselben Aufbereitung. */
function datenProjekt(int $id, array $p): array {
    require_once __DIR__ . '/src/Onboarding.php';
    require_once __DIR__ . '/src/Texte.php';
    require_once __DIR__ . '/src/Umfang.php';
    require_once __DIR__ . '/src/Standard.php';
    require_once __DIR__ . '/src/Abnahme.php';
    require_once __DIR__ . '/src/Werkstatt.php';
    return [

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
                /* Material und Paket getrennt: Das Paket ist kein Anhang
                   zwischen den Uploads des Kunden, sondern das Ergebnis. */
                'dateien' => sicher(static fn() => Db::all(
                    "SELECT * FROM files WHERE project_id = ? AND rolle <> 'paket' ORDER BY id DESC", [$id])),
                'paket' => sicher(static fn() => Db::one(
                    "SELECT * FROM files WHERE project_id = ? AND rolle = 'paket' ORDER BY id DESC LIMIT 1", [$id])),
                'pruefungen' => sicher(static fn() => Db::all(
                    'SELECT c.* FROM website_checks c JOIN websites w ON w.id = c.website_id
                     WHERE w.project_id = ? ORDER BY c.id DESC LIMIT 8', [$id])),
                'aktivitaeten' => Db::all('SELECT * FROM activities WHERE project_id = ? ORDER BY id DESC', [$id]),
    ];
}

/**
 * Eine Ansicht rendern und an ihren Marken zerlegen — siehe app/src/Teile.php,
 * wo auch steht, warum das besser ist, als die Bloecke ein zweites Mal zu
 * schreiben. Hier nur die kurze Hand fuer die Ansichten.
 */
function teile(string $datei, array $daten): array {
    require_once __DIR__ . '/src/Teile.php';
    return Teile::ausAnsicht(__DIR__ . "/views/$datei.php", $daten);
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
    $hinweis = isset($_GET['zeit']) ? 'Aus Sicherheitsgründen abgemeldet — eine Stunde ohne Klick oder zwölf Stunden insgesamt.' : null;
    if (!empty($_SESSION['anm_hinweis'])) { $hinweis = (string) $_SESSION['anm_hinweis']; unset($_SESSION['anm_hinweis']); }
    if ($post) {
        Csrf::pruefen();
        $email = (string) ($_POST['email'] ?? '');
        /* Kam Uwe von Claudes „Verbinden“ (AI Office Stufe 2), geht es nach der Anmeldung dorthin
           zurück. Vorher lesen: Die Anmeldung leert die Sitzung. Nur diese eine Adresse, sonst nichts. */
        $nachAnmeldung = (string) ($_SESSION['nach_anmeldung'] ?? '');
        if (Auth::anmelden($email, (string) ($_POST['passwort'] ?? ''))) {
            if (preg_match('~^claude-erlauben\?a=[a-f0-9]{32}$~', $nachAnmeldung)) { weiter($nachAnmeldung); }
            weiter('');
        }
        $minuten = Auth::gesperrt($email);
        if ($minuten > 0) { http_response_code(429); }
        $fehler = $minuten > 0
            ? "Zu viele Fehlversuche. Bitte in $minuten Minuten noch einmal."
            : 'E-Mail oder Passwort stimmt nicht.';
    }
    require __DIR__ . '/views/anmelden.php';
    exit;
}
/* Passwort vergessen (09.10.2026): zwei Seiten vor dem Riegel, beide ohne Anmeldung. */
if ($route === 'passwort-vergessen') {
    $fertig = false;
    if ($post) {
        Csrf::pruefen();
        /* Live antwortete diese Seite am 09.10.2026 mit 500 und leerem Text — und weder Link noch
           Meldung entstanden. Ohne Fehlerprotokoll auf dem Webspace war nicht zu sehen, warum.
           Jetzt landet jeder Absturz hier in settings.passwort_fehler (lesbar im phpMyAdmin),
           auch ein nicht abfangbarer. Die Seite sagt trotzdem dasselbe wie immer. */
        register_shutdown_function(static function (): void {
            $f = error_get_last();
            if ($f === null || !in_array($f['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) { return; }
            try {
                Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)',
                    ['passwort_fehler', date('Y-m-d H:i:s') . ' ' . mb_substr($f['message'] . ' @ ' . basename($f['file']) . ':' . $f['line'], 0, 900)]);
            } catch (Throwable $e) { /* nichts mehr zu retten */ }
        });
        /* Die Migrationen laufen sonst erst nach der Anmeldung — und genau die geht hier nicht.
           Ohne diesen Schritt fehlte die Tabelle beim ersten vergessenen Passwort nach dem Deploy. */
        try { require_once __DIR__ . '/src/Einrichtung.php'; Einrichtung::migrieren(); } catch (Throwable $e) { /* dann zeigt es die Anmeldung später */ }
        try {
            Auth::linkAnfordern((string) ($_POST['email'] ?? ''));
        } catch (Throwable $e) {
            try {
                Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)',
                    ['passwort_fehler', date('Y-m-d H:i:s') . ' ' . get_class($e) . ': ' . mb_substr($e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine(), 0, 900)]);
            } catch (Throwable $e2) { /* dann bleibt nur das Server-Protokoll */ }
        }
        $fertig = true;
    }
    require __DIR__ . '/views/passwort.php';
    exit;
}
if ($route === 'passwort-neu') {
    $token = (string) ($_POST['t'] ?? $_GET['t'] ?? '');
    $fehler = null;
    if ($post) {
        Csrf::pruefen();
        $n1 = (string) ($_POST['neu1'] ?? ''); $n2 = (string) ($_POST['neu2'] ?? '');
        if ($n1 !== $n2) { $fehler = 'Die beiden Passwörter sind nicht gleich.'; }
        elseif (mb_strlen($n1) < 10) { $fehler = 'Das Passwort braucht mindestens 10 Zeichen.'; }
        elseif (Auth::passwortSetzen($token, $n1)) { $_SESSION['anm_hinweis'] = 'Das neue Passwort gilt. Melde dich damit an.'; weiter('anmelden'); }
        else { $fehler = 'Der Link ist abgelaufen oder schon benutzt. Fordere einen neuen an.'; }
    }
    $linkGilt = Auth::linkPruefen($token) !== null;
    require __DIR__ . '/views/passwort.php';
    exit;
}
if ($route === 'abmelden') { Auth::abmelden(); weiter('anmelden'); }

/* Claude fragt um Erlaubnis (AI Office Stufe 2, 07.10.2026): Wer dafür erst angemeldet werden muss,
   soll danach wieder auf der Erlaubnis-Seite landen, nicht auf „Heute“. */
if ($route === 'claude-erlauben' && preg_match('/^[a-f0-9]{32}$/', (string) ($_GET['a'] ?? ''))) {
    $_SESSION['nach_anmeldung'] = 'claude-erlauben?a=' . (string) $_GET['a'];
}
Auth::$nurPuls = $route === 'puls';
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

/* ---------- Rollen (05.10.2026): Seite und Tat gegen Rechte.php ----------
   Steht vor allen Seiten und allen schreibenden Vorgängen; für Admins ändert sich nichts. */
require_once __DIR__ . '/src/Rechte.php';
if (!Rechte::darfSeite($route)) {
    http_response_code(403);
    $_SESSION['fehler'] = 'Diese Seite ist für deine Rolle nicht freigegeben.';
    weiter('heute');
}
if ($post && !Rechte::darfTat((string) ($_POST['tat'] ?? ''))) {
    Csrf::pruefen();
    $_SESSION['fehler'] = Rechte::rolle() === 'lesen' ? 'Mit der Rolle „Nur lesen“ lässt sich nichts ändern.' : 'Das darf in deiner Rolle nur ein Admin.';
    zurueck('heute');
}
/* ---------- Rückfrage auch auf dem Server (05.10.2026, Spezifikation 71) ----------
   Jede Tat aus Ablauf::TRAGWEITE braucht das „Ja“ der Rückfrage — nicht nur im Browser.
   Fehlt es, passiert nichts, und die Seite sagt, warum. */
require_once __DIR__ . '/src/Ablauf.php';
if ($post && !Ablauf::bestaetigt($_POST)) {
    Csrf::pruefen();
    $_SESSION['fehler'] = 'Nichts ausgeführt: Dieser Schritt braucht deine Bestätigung. Bitte den Knopf noch einmal drücken und die Rückfrage mit „Ja“ beantworten.';
    zurueck('heute');
}
/* Jede Mail, die dieser Klick auslöst, trägt ihn: Tat und Name (07.10.2026, Mail-Spur).
   Freigabe::genehmigen setzt für seine Tat „freigabe“ darüber. */
if ($post) {
    require_once __DIR__ . '/src/Mail.php';
    Mail::$ausloeser = ['art' => 'knopf', 'ref' => (string) ($_POST['tat'] ?? ''), 'wer' => Auth::name()];
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

/* ---------- Akquise: eigenes Modul, eigener Verteiler ----------
   Die Handgriffe (tat=akq_…) stehen in akquise_route.php, nicht in der
   grossen Liste unten -- das Modul soll sich als Ganzes lesen lassen. Der
   Verteiler der Seiten weiter unten fuehrt fuer GET in dieselbe Datei. */
if ($route === 'akquise' && $post) {
    require __DIR__ . '/akquise_route.php';
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

            /* Akquise-CRM Modul G (06.10.2026): Angebot direkt aus dem Betrieb — ein Knopf.
               Legt den Kunden an, wenn es ihn noch nicht gibt, und den Entwurf (Preisrechner oder Festpreis).
               Gesendet wird nur im Angebotseditor. Nur Admin: Preise gehören nicht zur Mitarbeit. */
            case 'angebot_aus_akquise':
                require_once __DIR__ . '/src/AkquiseKunde.php';
                require_once __DIR__ . '/src/Baukasten.php';
                $aaF = (int) ($_POST['firma'] ?? 0);
                $aaC = trim((string) ($_POST['festpreis'] ?? '')) !== '' ? Baukasten::centsAus((string) $_POST['festpreis']) : null;
                $aaR = AkquiseKunde::angebotAnlegen($aaF, $aaC);
                if (!$aaR['ok']) { $_SESSION['fehler'] = (string) $aaR['fehler']; weiter('akquise/' . $aaF . '#auftrag'); }
                Events::pruefspur('angebot_aus_akquise', 'angebote', (int) $aaR['angebot'], [], ['firma_id' => $aaF, 'customer_id' => (int) $aaR['kunde']]);
                $_SESSION['gut'] = 'Angebotsentwurf angelegt' . (!empty($aaR['neu']) ? ', dazu der Kunde (#' . (int) $aaR['kunde'] . ')' : '') . '.'
                    . (!empty($aaR['partner']) ? ' ' . $aaR['partner'] . '.' : '') . ' Prüfen und hier senden — vorher geht nichts raus.';
                weiter('angebote/' . (int) $aaR['angebot']);

            /* Festpreis-Angebot (01.10.2026): Betrag fest, Bausteine teilen ihn */
            case 'angebot_festpreis_neu':
                require_once __DIR__ . '/src/Angebot.php';
                require_once __DIR__ . '/src/Baukasten.php';
                $afK = (int) ($_POST['customer_id'] ?? 0);
                $afN = Angebot::festpreisNeu($afK, Baukasten::centsAus((string) ($_POST['festpreis'] ?? '0')), (string) ($_POST['sprache'] ?? ''));
                if (!is_int($afN)) { $_SESSION['fehler'] = $afN; weiter('kunden/' . $afK); }
                $_SESSION['gut'] = 'Festpreis-Angebot angelegt. Jetzt die Bausteine hinzufügen — sie teilen sich den Betrag.';
                weiter('angebote/' . $afN);

            case 'angebot_festpreis':
                require_once __DIR__ . '/src/Angebot.php';
                require_once __DIR__ . '/src/Baukasten.php';
                $aid = (int) ($_POST['id'] ?? 0);
                $afT = trim((string) ($_POST['festpreis'] ?? ''));
                $f = Angebot::festpreisSetzen($aid, $afT === '' || !empty($_POST['aus']) ? null : Baukasten::centsAus($afT));
                $_SESSION[$f === null ? 'gut' : 'fehler'] = $f ?? ($afT === '' || !empty($_POST['aus']) ? 'Festpreis entfernt — die Zeilen behalten ihre Preise.' : 'Festpreis gesetzt und auf die Zeilen verteilt.');
                zurueck('angebote/' . $aid);

            case 'angebot_zeilen':
                require_once __DIR__ . '/src/Angebot.php';
                $aid = (int) ($_POST['id'] ?? 0);
                // Derselbe Knopfleiste-Trick wie anderswo: Das Kreuz an einer
                // Zeile schickt dasselbe Formular ab, nur mit "weg".
                $weg = (int) ($_POST['weg'] ?? 0);
                if ($weg > 0) { Angebot::zeileWeg($aid, $weg); }
                else { Angebot::zeilenSpeichern($aid, (array) ($_POST['menge'] ?? []), (array) ($_POST['preis'] ?? []), (array) ($_POST['optional'] ?? [])); }
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
                if (Angebot::senden($aid)) {
                    require_once __DIR__ . '/src/Freigabe.php';
                    Freigabe::vonHandErledigt('angebot_senden', ['angebot' => $aid]);
                }
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

            /* Partner Academy verwalten (Etappe 2, 05.10.2026): Module schalten, eigene PDFs, „Neue Schulung“. */
            case 'academy_modul':
                require_once __DIR__ . '/src/Academy.php';
                $amR = trim((string) ($_POST['reihe'] ?? ''));
                $amP = (string) ($_POST['pflicht'] ?? '');
                $amOk = Academy::schalterSetzen((string) ($_POST['slug'] ?? ''), !empty($_POST['aktiv']),
                    $amP === '' ? null : $amP === '1', $amR === '' ? null : (int) $amR);
                $_SESSION[$amOk ? 'gut' : 'fehler'] = $amOk ? 'Modul gespeichert.' : 'Dieses Modul gibt es nicht.';
                zurueck('academy');

            case 'academy_pdf':
                require_once __DIR__ . '/src/Academy.php';
                $apF = $_FILES['datei'] ?? null;
                if (!is_array($apF) || (int) ($apF['error'] ?? 4) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $apF['tmp_name'])) {
                    $_SESSION['fehler'] = 'Keine Datei angekommen (höchstens ' . (Academy::PDF_MAX >> 20) . ' MB).';
                    zurueck('academy');
                }
                $apR = Academy::pdfSpeichern((string) $apF['tmp_name'], (string) $apF['name'], (string) ($_POST['titel'] ?? ''),
                    (string) ($_POST['kategorie'] ?? 'eigene'), (string) ($_POST['sprache'] ?? 'alle'), (int) ($_POST['ersetze'] ?? 0));
                if (is_int($apR)) {
                    Events::protokoll('academy_pdf', 'Academy-PDF ' . ((int) ($_POST['ersetze'] ?? 0) > 0 ? 'ersetzt' : 'hochgeladen') . ': ' . mb_substr((string) ($_POST['titel'] ?? ''), 0, 80));
                    $_SESSION['gut'] = 'PDF gespeichert. Partner sehen es sofort in der Bibliothek.';
                } else {
                    $_SESSION['fehler'] = ['zu_gross' => 'Die Datei ist zu groß (höchstens ' . (Academy::PDF_MAX >> 20) . ' MB).', 'kein_pdf' => 'Das ist keine PDF-Datei.',
                        'titel' => 'Bitte einen Titel angeben.', 'unbekannt' => 'Das Dokument zum Ersetzen gibt es nicht.'][$apR] ?? 'Speichern ging nicht.';
                }
                zurueck('academy');

            case 'academy_pdf_archiv':
                require_once __DIR__ . '/src/Academy.php';
                Academy::pdfArchivieren((int) ($_POST['id'] ?? 0), !empty($_POST['archiv']));
                $_SESSION['gut'] = !empty($_POST['archiv']) ? 'PDF archiviert — Partner sehen es nicht mehr.' : 'PDF ist wieder sichtbar.';
                zurueck('academy');

            case 'academy_medium':
                require_once __DIR__ . '/src/Academy.php';
                $amF = $_FILES['datei'] ?? null;
                if (!is_array($amF) || (int) ($amF['error'] ?? 4) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $amF['tmp_name'])) {
                    $_SESSION['fehler'] = 'Keine Datei angekommen (höchstens ' . (Academy::MEDIEN_MAX >> 20) . ' MB).';
                    zurueck('academy');
                }
                [$amMod, $amLek] = array_pad(explode(':', (string) ($_POST['lektion'] ?? ''), 2), 2, '');
                $amR = Academy::medienSpeichern((string) $amF['tmp_name'], $amMod, (int) $amLek, (string) ($_POST['sprache'] ?? 'alle'), (string) ($_POST['titel'] ?? ''));
                if (is_int($amR)) {
                    Events::protokoll('academy_medium', 'Academy: Medium zu ' . $amMod . ' Lektion ' . ((int) $amLek + 1) . ' hochgeladen');
                    $_SESSION['gut'] = 'Gespeichert — erscheint sofort in der Lektion.';
                } else {
                    $_SESSION['fehler'] = ['zu_gross' => 'Zu groß (höchstens ' . (Academy::MEDIEN_MAX >> 20) . ' MB). Video vorher verkleinern.', 'typ' => 'Nur MP4/WebM-Video oder MP3/M4A-Audio.', 'lektion' => 'Diese Lektion gibt es nicht.'][$amR] ?? 'Speichern ging nicht.';
                }
                zurueck('academy');

            case 'academy_medium_weg':
                require_once __DIR__ . '/src/Academy.php';
                Academy::medienLoeschen((int) ($_POST['id'] ?? 0));
                $_SESSION['gut'] = 'Medium entfernt.';
                zurueck('academy');

            case 'academy_zert_widerruf':
                require_once __DIR__ . '/src/Academy.php';
                Academy::zertifikatWiderrufen((int) ($_POST['id'] ?? 0), !empty($_POST['widerrufen']));
                Events::protokoll('academy_zert', 'Academy-Zertifikat #' . (int) ($_POST['id'] ?? 0) . (!empty($_POST['widerrufen']) ? ' widerrufen' : ' wieder gültig'));
                $_SESSION['gut'] = !empty($_POST['widerrufen']) ? 'Zertifikat widerrufen — die Prüfseite zeigt es als ungültig.' : 'Zertifikat ist wieder gültig.';
                zurueck('academy');

            case 'academy_sim':
                require_once __DIR__ . '/src/Academy.php';
                require_once __DIR__ . '/src/AcademySimulator.php';
                $asOk = AcademySimulator::schalten(!empty($_POST['an']), !empty($_POST['datenschutz']));
                $_SESSION[$asOk ? 'gut' : 'fehler'] = $asOk ? 'Gesprächssimulator gespeichert.' : 'Einschalten geht nur mit bestätigter Datenschutzprüfung.';
                zurueck('academy');

            case 'academy_melden':
                require_once __DIR__ . '/src/Academy.php';
                $amM = Academy::melden((string) ($_POST['ziel'] ?? ''));
                if ($amM['ok']) {
                    Events::protokoll('academy_melden', 'Neue Schulung gemeldet: ' . (string) ($_POST['ziel'] ?? '') . ' (' . $amM['partner'] . ' Partner, ' . $amM['push'] . ' Hinweise)');
                    $_SESSION['gut'] = 'Gemeldet: ' . $amM['partner'] . ' Partner, ' . $amM['push'] . ' Hinweise aufs Handy. Der Hinweis steht 14 Tage auf der Academy-Startseite.';
                } else {
                    $_SESSION['fehler'] = 'Dieses Ziel gibt es nicht (oder es ist abgeschaltet).';
                }
                zurueck('academy');

            case 'empfehlung_zuordnen':
                require_once __DIR__ . '/src/Empfehlung.php';
                $kid = (int) ($_POST['kunde'] ?? 0);
                $eid = (int) ($_POST['id'] ?? 0);
                if ($kid > 0 && $eid > 0) { Empfehlung::zuordnen($eid, $kid); }
                zurueck('empfehlungen');

            /* ---------- Partnerprogramm (26.09.2026) ---------- */
            case 'partner_einstellungen':
                require_once __DIR__ . '/src/Partner.php';
                $f = Partner::einstellungenSetzen($_POST);
                $_SESSION[$f === null ? 'gut' : 'fehler'] = $f ?? 'Gespeichert. Gilt für alle künftigen Provisionen.';
                zurueck('partner');

            case 'partner_anlegen':
                require_once __DIR__ . '/src/Partner.php';
                $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
                if (trim((string) ($_POST['name'] ?? '')) === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $_SESSION['fehler'] = 'Name und gültige E-Mail-Adresse, bitte.';
                    zurueck('partner');
                }
                if (Db::wert("SELECT id FROM partner WHERE email = ? AND status <> 'abgelehnt'", [$email], null) !== null) {
                    $_SESSION['fehler'] = 'Zu dieser E-Mail gibt es schon einen Partner.';
                    zurueck('partner');
                }
                $wunsch = trim((string) ($_POST['code'] ?? ''));
                $pc = $wunsch !== '' ? Partner::codePruefen($wunsch) : ['code' => '', 'fehler' => null];
                if ($pc['fehler'] !== null) { $_SESSION['fehler'] = $pc['fehler']; zurueck('partner'); }
                $pid = Partner::anlegen(['name' => $_POST['name'], 'email' => $email, 'firma' => $_POST['firma'] ?? '', 'code' => $pc['code'],
                    'steuer_nr' => $_POST['steuer_nr'] ?? '', 'sprache' => $_POST['sprache'] ?? 'it', 'status' => 'aktiv']);
                Events::pruefspur('partner_angelegt', 'partner', $pid, [], ['email' => $email]);
                $ok = Partner::schreiben($pid, 'partner_willkommen');
                $_SESSION[$ok ? 'gut' : 'fehler'] = $ok ? 'Partner angelegt, Willkommensmail ist raus. Er bestätigt die Vereinbarung auf seiner Partnerseite.'
                                                        : 'Partner angelegt — die Willkommensmail ging NICHT raus. Postausgang prüfen.';
                weiter('partner/' . $pid);

            /* Admins schauen ins Partner-Dashboard, ohne Code (06.10.2026, Uwe): Einmal-Ticket, partner.php öffnet nur zum Lesen. */
            case 'partner_ansehen':
                if (!Auth::istAdmin()) { throw new RuntimeException('Nur Admins können ins Partner-Dashboard schauen.'); }
                require_once __DIR__ . '/src/Partner.php';
                require_once __DIR__ . '/src/PartnerAdminBlick.php';
                $abT = PartnerAdminBlick::ticket((int) ($_POST['id'] ?? 0), (int) ($_SESSION['uid'] ?? 0), Auth::name());
                header('Location: /partner.php?admin=' . $abT, true, 303);
                exit;

            case 'partner_annehmen':
            case 'partner_pausieren':
            case 'partner_aktivieren':
            case 'partner_ablehnen':
                require_once __DIR__ . '/src/Partner.php';
                $pid = (int) ($_POST['id'] ?? 0);
                $neu = ['partner_annehmen' => 'aktiv', 'partner_aktivieren' => 'aktiv',
                        'partner_pausieren' => 'pausiert', 'partner_ablehnen' => 'abgelehnt'][$tat];
                Partner::statusSetzen($pid, $neu);
                $_SESSION['gut'] = ['partner_annehmen' => 'Angenommen — die Willkommensmail mit Link und Partnerseite ist raus.',
                                    'partner_aktivieren' => 'Wieder aktiv.', 'partner_pausieren' => 'Pausiert: neue Klicks und Käufe zählen nicht, Offenes bleibt.',
                                    'partner_ablehnen' => 'Abgelehnt. Es ging keine Mail raus.'][$tat];
                weiter('partner/' . $pid);

            /* Schutz der Vecom-Unterlagen (30.09.2026, Uwe: ja) */
            case 'partner_freischalten':
                require_once __DIR__ . '/src/PartnerSchutz.php';
                $pid = (int) ($_POST['id'] ?? 0);
                $r = PartnerSchutz::freischalten($pid, Auth::name() !== '' ? Auth::name() : 'Verwaltung');
                $_SESSION[$r === 'ok' ? 'gut' : 'fehler'] = ['ok' => 'Freigeschaltet — der Partner sieht seinen Bereich wieder und bekommt eine kurze Mail.',
                    'fehlt' => 'Er hat der aktuellen Fassung noch nicht mit beiden Haken zugestimmt — erst dann geht das.',
                    'kein_partner' => 'Partner nicht gefunden.'][$r];
                if (($_POST['zurueck'] ?? '') === 'liste') { weiter('partner#schutz'); }
                weiter('partner/' . $pid . '#schutz');

            case 'partner_sperren':
                require_once __DIR__ . '/src/PartnerSchutz.php';
                $pid = (int) ($_POST['id'] ?? 0);
                $ok = PartnerSchutz::sperren($pid, (string) ($_POST['grund'] ?? ''), Auth::name() !== '' ? Auth::name() : 'Verwaltung');
                $_SESSION[$ok ? 'gut' : 'fehler'] = $ok ? 'Gesperrt: Der Partner sieht nur noch die Sperrseite. Sein Link zählt weiter, bis du ihn pausierst.' : 'Partner nicht gefunden.';
                weiter('partner/' . $pid . '#schutz');

            case 'partner_verstoss':
                require_once __DIR__ . '/src/PartnerSchutz.php';
                $pid = (int) ($_POST['id'] ?? 0);
                $f = Partner::laden($pid) ? PartnerSchutz::verstossErfassen($pid, $_POST, Auth::name() !== '' ? Auth::name() : 'Verwaltung') : 'Partner nicht gefunden.';
                $_SESSION[$f === null ? 'gut' : 'fehler'] = $f ?? 'Festgehalten — steht jetzt in der Akte für den Anwalt.';
                weiter('partner/' . $pid . '#schutz');

            case 'partner_schutz_einstellungen':
                require_once __DIR__ . '/src/PartnerSchutz.php';
                $scPen = Partner::centsAusEingabe((string) ($_POST['partner_penale'] ?? ''));
                $scMon = (int) ($_POST['partner_kundenschutz_monate'] ?? 0);
                $scDom = mb_strtolower(trim((string) ($_POST['partner_fallen_domain'] ?? '')));
                if ($scPen === null || $scPen < 10000 || $scPen > 5000000) { $_SESSION['fehler'] = 'Vertragsstrafe zwischen 100 € und 50.000 €, bitte.'; weiter('partner#schutz'); }
                if ($scMon < 1 || $scMon > 60) { $_SESSION['fehler'] = 'Kundenschutz zwischen 1 und 60 Monaten (gesetzlich höchstens 5 Jahre).'; weiter('partner#schutz'); }
                if ($scDom !== '' && !preg_match('~^(?=.{4,190}$)([a-z0-9-]+\.)+[a-z]{2,}$~', $scDom)) { $_SESSION['fehler'] = 'Die Domain sieht nicht gültig aus (z. B. vecom-kontrolle.it).'; weiter('partner#schutz'); }
                $scVor = ['partner_penale_cents' => Partner::einstellung('partner_penale_cents'), 'partner_kundenschutz_monate' => Partner::einstellung('partner_kundenschutz_monate'), 'partner_fallen_domain' => Partner::einstellung('partner_fallen_domain')];
                $scNeu = ['partner_penale_cents' => (string) $scPen, 'partner_kundenschutz_monate' => (string) $scMon, 'partner_fallen_domain' => $scDom];
                foreach ($scNeu as $k => $v) { Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$k, $v]); }
                Events::pruefspur('partner_schutz', 'settings', null, $scVor, $scNeu);
                $_SESSION['gut'] = 'Gespeichert. Neue Zustimmungen bekommen diese Zahlen in den Wortlaut.';
                weiter('partner#schutz');

            /* Partner-Tracking (30.09.2026) */
            case 'tracking_einstellungen':
                require_once __DIR__ . '/src/Spur.php';
                $f = Spur::einstellungenSetzen($_POST);
                $_SESSION[$f === null ? 'gut' : 'fehler'] = $f ?? 'Gespeichert.';
                weiter('tracking#einstellungen');

            /* Zielgruppen und Recherche (Marketing-Studio Schritt 1, 01.10.2026) */
            case 'zielgruppe_freigeben':
            case 'zielgruppe_verwerfen':
                require_once __DIR__ . '/src/MkZielgruppe.php';
                $mzId = (int) ($_POST['id'] ?? 0);
                $f = $tat === 'zielgruppe_freigeben' ? MkZielgruppe::freigeben($mzId) : MkZielgruppe::verwerfen($mzId);
                $_SESSION[$f === null ? 'gut' : 'fehler'] = $f ?? ($tat === 'zielgruppe_freigeben' ? 'Freigegeben — jetzt kann Claude Inhalte und Kampagnen dafür schreiben (Knopf oben).' : 'Verworfen.');
                /* Z4 (01.10.2026, Uwe: „Freigeben = Kampagne läuft“): Das Ja startet gleich die Kampagne —
                   Beiträge, auf Wunsch Anzeigen und Bilder wie im Autopilot des Landes, Freigabe per Telegram. */
                if ($f === null && $tat === 'zielgruppe_freigeben') {
                    require_once __DIR__ . '/src/MkAuftrag.php';
                    require_once __DIR__ . '/src/MkAutopilot.php';
                    $mzZ = MkZielgruppe::laden($mzId);
                    $mzSchon = (int) Db::wert("SELECT COUNT(*) FROM mk_auftraege WHERE art = 'inhalte' AND status <> 'abgebrochen' AND created_at >= NOW() - INTERVAL 7 DAY AND parameter LIKE ?", ['%"zielgruppe_id":' . $mzId . ',%'], 0);
                    /* S6: Die Landingpage der Zielgruppe gleich mitschreiben lassen (geht erst nach deinem Ja online). */
                    if ($mzZ !== null) { require_once __DIR__ . '/src/MkSeite.php'; if (MkSeite::fuerZielgruppe($mzId) === null) { sicher(static fn() => MkSeite::anlegen($mzId), null); } }
                    if ($mzZ !== null && $mzSchon === 0) {
                        $mzE = MkAutopilot::einstellung((string) $mzZ['land']);
                        $mzK = MkAuftrag::anlegenKampagne($mzId, ['organisch' => '1', 'anzeigen' => $mzE['anzeigen'] ? '1' : '', 'bilder' => $mzE['bilder'] ? '1' : '', 'autopilot' => '1']);
                        $_SESSION['gut'] = is_int($mzK)
                            ? 'Freigegeben — und die Kampagne läuft: Claude schreibt die Beiträge' . ($mzE['anzeigen'] ? ' und Anzeigen' : '') . ($mzE['bilder'] ? ', Kie.ai oder Blender machen die Bilder' : '') . '. Sind sie fertig, kommt eine Telegram-Nachricht zum Absegnen.'
                            : 'Freigegeben. Die Kampagne startet noch nicht: ' . $mzK;
                    }
                }
                weiter($tat === 'zielgruppe_freigeben' || MkZielgruppe::laden($mzId) !== null ? 'zielgruppen/' . $mzId : 'zielgruppen');

            /* Recherche per Knopf (01.10.2026, Uwe: „soll automatisch starten, wenn … geklickt wird“) */
            /* Marketing-Studio 5 (01.10.2026): Zielgruppen und Recherche sind eine Seite; „in beiden
               Ländern“ legt je Land einen Auftrag an (Uwe: „Deutsch und Italienisch gleichermaßen“). */
            case 'recherche_starten':
                require_once __DIR__ . '/src/MkAuftrag.php';
                require_once __DIR__ . '/src/MkLand.php';
                $maLand = strtoupper((string) ($_POST['land'] ?? 'IT'));
                $maLand = isset(MkLand::NAMEN[$maLand]) ? $maLand : 'IT';
                $maGut = []; $maFehl = [];
                /* Z2 (01.10.2026, Uwe: „DE und IT gleich oft“): Die Runde über alle Branchen läuft immer in beiden Ländern. */
                foreach (!empty($_POST['beide']) || (string) ($_POST['branche'] ?? '') === '' ? [$maLand, MkLand::andere($maLand)] : [$maLand] as $maL) {
                    $maErg = MkAuftrag::anlegen((string) ($_POST['branche'] ?? ''), $maL);
                    if (is_int($maErg)) { $maGut[] = MkLand::name($maL); } else { $maFehl[] = MkLand::name($maL) . ': ' . $maErg; }
                }
                if ($maGut) { $_SESSION['gut'] = 'Recherche angestoßen (' . implode(' und ', $maGut) . '). Dein PC holt sie in den nächsten fünf Minuten ab; Claude braucht dann etwa 10–20 Minuten je Land. Die Seite zeigt den Stand.'; }
                if ($maFehl) { $_SESSION['fehler'] = implode(' · ', $maFehl); }
                weiter('zielgruppen?land=' . $maLand . '#auftraege');

            case 'kommentar_schalten':
            case 'kommentar_abo':
                /* S1 (01.10.2026): „Kommentiere STICHWORT“ → automatische Nachricht */
                require_once __DIR__ . '/src/MkKommentar.php';
                if ($tat === 'kommentar_schalten') {
                    MkKommentar::schalten(($_POST['an'] ?? '') === '1');
                    $_SESSION['gut'] = MkKommentar::an() ? 'Eingeschaltet: Wer ein Stichwort kommentiert, bekommt die Nachricht mit dem Check-Link. Neue Beiträge sagen ab jetzt „Kommentiere …“.' : 'Ausgeschaltet.';
                } else {
                    $kaR = MkKommentar::abonnieren();
                    $_SESSION[$kaR['ok'] ? 'gut' : 'fehler'] = $kaR['ok'] ? 'Die Seite meldet Kommentare jetzt an Vecom (Facebook). Für Instagram einmal das Feld „comments“ in der Meta-App abonnieren — Anleitung darunter.' : 'Nicht geklappt: ' . $kaR['grund'];
                }
                weiter('kanaele#kommentar');

            /* S6 (01.10.2026): Landingpage je Zielgruppe — schreiben lassen, online stellen, offline nehmen */
            case 'seite_schreiben':
            case 'seite_freigeben':
            case 'seite_offline':
            case 'seite_verwerfen':
                require_once __DIR__ . '/src/MkSeite.php';
                $msZg = (int) ($_POST['zielgruppe'] ?? 0);
                $msId = (int) ($_POST['id'] ?? 0);
                if ($tat === 'seite_schreiben') {
                    $msR = MkSeite::anlegen($msZg);
                    $_SESSION[is_int($msR) ? 'gut' : 'fehler'] = is_int($msR) ? 'Claude schreibt die Seite, sobald dein PC nachfragt (alle 5 Minuten). Danach liegt sie hier zum Ansehen und Freigeben.' : $msR;
                } else {
                    $msR = match ($tat) { 'seite_freigeben' => MkSeite::freigeben($msId), 'seite_offline' => MkSeite::offline($msId), default => MkSeite::entwurfVerwerfen($msId) };
                    $_SESSION[$msR === null ? 'gut' : 'fehler'] = $msR ?? match ($tat) {
                        'seite_freigeben' => 'Die Seite ist online. Neue Beiträge und Anzeigen dieser Zielgruppe führen ab jetzt dorthin.',
                        'seite_offline' => 'Offline genommen — Beiträge führen wieder auf den Website-Check.', default => 'Entwurf verworfen.' };
                }
                weiter('zielgruppen/' . $msZg . '#seite');

            /* Ein-Klick-Kampagne (Marketing-Studio 6, Uwe: „ja“ zu U3) — auf Wunsch auch für dieselbe Branche im anderen Land. */
            case 'woche_werben':
                /* M2 (01.10.2026, Uwe: „Ein Knopf je Woche“): Zielgruppe, Beiträge, Bilder, Plan — ein Klick, ein Ja per Telegram. */
                require_once __DIR__ . '/src/MkAuftrag.php';
                require_once __DIR__ . '/src/MkAutopilot.php';
                require_once __DIR__ . '/src/MkLand.php';
                $wwLand = strtoupper((string) ($_POST['land'] ?? ''));
                $wwLand = isset(MkLand::NAMEN[$wwLand]) ? $wwLand : MkLand::wahl();
                [$wwR, $wwZ] = MkAutopilot::jetzt($wwLand);
                $_SESSION[is_int($wwR) ? 'gut' : 'fehler'] = is_int($wwR)
                    ? MkLand::name($wwLand) . ': Diese Woche wird für „' . (string) $wwZ['titel'] . '“ geworben. Claude schreibt jetzt, die Bilder folgen; sind sie fertig, kommt die Telegram-Nachricht — Stück für Stück Ja oder Nein.'
                    : $wwR;
                weiter(match ((string) ($_POST['zurueck'] ?? '')) { 'marketing', 'mkstart' => 'marketing', 'zahlen' => 'zahlen', default => 'freigabe' });

            case 'kampagne_starten':
                require_once __DIR__ . '/src/MkAuftrag.php';
                require_once __DIR__ . '/src/MkLand.php';
                $mkZgId = (int) ($_POST['id'] ?? 0);
                $mkZgs = [$mkZgId];
                if (!empty($_POST['beide']) && ($mkZg = Db::one('SELECT branche, land FROM mk_zielgruppen WHERE id = ?', [$mkZgId]))) {
                    $mkGegen = Db::wert("SELECT id FROM mk_zielgruppen WHERE branche = ? AND land = ? AND (status = 'freigegeben' OR vorher IS NOT NULL)", [$mkZg['branche'], MkLand::andere((string) $mkZg['land'])], null);
                    if ($mkGegen !== null) { $mkZgs[] = (int) $mkGegen; }
                }
                $mkGut = 0; $mkFehl = [];
                foreach ($mkZgs as $mkZi) {
                    $maErg = MkAuftrag::anlegenKampagne($mkZi, $_POST);
                    if (is_int($maErg)) { $mkGut++; } else { $mkFehl[] = $maErg; }
                }
                if ($mkGut > 0) { $_SESSION['gut'] = ($mkGut > 1 ? 'Zwei Kampagnen gestartet (beide Länder).' : 'Kampagne gestartet.') . ' Dein PC holt den Auftrag in den nächsten fünf Minuten ab; Claude schreibt etwa 5–15 Minuten' . (!empty($_POST['bilder']) ? ', danach entstehen die Bilder' : '') . '. Die Entwürfe gehst du unter „Freigeben“ durch.'; }
                if ($mkFehl) { $_SESSION['fehler'] = implode(' · ', array_unique($mkFehl)); }
                weiter('zielgruppen/' . $mkZgId . '#kampagne');

            /* Freigabe-Stapel (Marketing-Studio 6): Ja gibt frei und plant ein, Nein verwirft, Später legt nach hinten. */
            case 'stapel_ja':
            case 'stapel_nein':
            case 'stapel_spaeter':
                require_once __DIR__ . '/src/MkVeroeffentlichen.php';
                $msId = (int) ($_POST['id'] ?? 0);
                $_SESSION['mk_spaeter'] = array_values(array_diff(array_map('intval', (array) ($_SESSION['mk_spaeter'] ?? [])), [$msId]));
                if ($tat === 'stapel_ja') {
                    $msE = MkVeroeffentlichen::stapelJa($msId);
                    $_SESSION[$msE['ok'] ? 'gut' : 'fehler'] = $msE['text'];
                } elseif ($tat === 'stapel_nein') {
                    $f = MkInhalt::verwerfen($msId);
                    $_SESSION[$f === null ? 'gut' : 'fehler'] = $f ?? 'Verworfen.';
                } else {
                    $_SESSION['mk_spaeter'][] = $msId;
                }
                weiter('freigabe');

            /* Demo-Vorschau (Marketing-Studio 10, Uwe: „ja“ zu S1): erst nach deinem Ja geht der Link an den Interessenten. */
            case 'demo_freigeben':
            case 'demo_nochmal':
            case 'demo_verwerfen':
            case 'demo_loeschen':     // 06.10.2026: auch aus der Kundenakte — löschen und verlängern
            case 'demo_verlaengern':
                require_once __DIR__ . '/src/MkDemo.php';
                $dmId = (int) ($_POST['id'] ?? 0);
                $dmD = MkDemo::laden($dmId);
                $f = match ($tat) {
                    'demo_freigeben'   => MkDemo::freigeben($dmId),
                    'demo_nochmal'     => MkDemo::nochmal($dmId, (string) ($_POST['hinweis'] ?? '')),
                    'demo_loeschen'    => MkDemo::loeschen($dmId),
                    'demo_verlaengern' => MkDemo::verlaengern($dmId),
                    default            => MkDemo::verwerfen($dmId),
                };
                $_SESSION[$f === null ? 'gut' : 'fehler'] = $f ?? match ($tat) {
                    'demo_freigeben'   => 'Freigegeben — der Link ist per Mail an den Interessenten unterwegs und steht in seinem Bereich (30 Tage gültig).',
                    'demo_nochmal'     => 'Dein PC baut die Vorschau neu' . (trim((string) ($_POST['hinweis'] ?? '')) !== '' ? ' — mit deinem Hinweis' : '') . '. Danach wieder ansehen und freigeben.',
                    'demo_loeschen'    => 'Gelöscht — der Link ist ab sofort nicht mehr erreichbar.',
                    'demo_verlaengern' => 'Verlängert — der Link gilt jetzt ' . MkDemo::GUELTIG_TAGE . ' Tage länger.',
                    default            => 'Verworfen. Der Interessent bekommt nichts.',
                };
                if (($_POST['zurueck'] ?? '') === 'kunde' && $dmD) { weiter('kunden/' . (int) $dmD['customer_id'] . '#demos'); }
                weiter(MkDemo::freigabeLink((string) ($dmD['sprache'] ?? 'it')));

            /* Marketing-Studio 11: Motor für Bilder/Videos und Nachtschicht; im Stapel das bessere Bild wählen. */
            case 'motor_speichern':
                require_once __DIR__ . '/src/MkMedium.php';
                $f = MkMedium::motorSpeichern($_POST);
                $mo = MkMedium::motor();
                $_SESSION[$f === null ? 'gut' : 'fehler'] = $f ?? 'Gespeichert: Bilder ' . MkMedium::MOTOREN['bild'][$mo['bild']] . ', Videos ' . MkMedium::MOTOREN['video'][$mo['video']]
                    . ($mo['nacht_an'] ? ', 3D nachts ' . sprintf('%02d–%02d Uhr', $mo['nacht_von'], $mo['nacht_bis']) : ', 3D jederzeit') . '.';
                weiter('freigabe?land=' . (strtoupper((string) ($_POST['land'] ?? '')) === 'DE' ? 'DE' : 'IT') . '#motor');

            case 'anfrage_annehmen':
            case 'anfrage_erneut':
                /* Anfrage aus dem E-Mail-Einstieg (01.10.2026): als Kunde anlegen, um gleich
                   antworten und ein Angebot schicken zu können -- oder den Link neu senden. */
                require_once __DIR__ . '/src/Zugang.php';
                $azId = (int) ($_POST['zugang_id'] ?? 0);
                $az = Db::one('SELECT * FROM zugaenge WHERE id = ?', [$azId]);
                if (!$az) { $_SESSION['fehler'] = 'Diese Anfrage gibt es nicht mehr.'; weiter('kunden#anfragen'); }
                if ($tat === 'anfrage_erneut') {
                    $azOk = Zugang::erneutSenden($azId);
                    $_SESSION[$azOk ? 'gut' : 'fehler'] = $azOk ? 'Zugangslink noch einmal an ' . (string) $az['email'] . ' verschickt.'
                        : 'Die Mail ging nicht raus — Stand unter Einstellungen › E-Mail ansehen.';
                    $azZu = (string) ($_POST['zurueck'] ?? '');
                    weiter(preg_match('~^[a-z][a-z0-9/_?=&#.-]{0,120}$~i', $azZu) ? $azZu : 'kunden#anfragen');
                }
                if ($az['customer_id'] !== null) { weiter('kunden/' . (int) $az['customer_id']); }
                $azKid = Zugang::annehmen((array) $az, false);
                Events::pruefspur('anfrage_annehmen', 'zugaenge', $azId, ['customer_id' => null], ['customer_id' => $azKid]);
                $_SESSION['gut'] = 'Als Kunde angelegt. Hier können Sie antworten und ein Angebot schicken — der Interessent kann seinen Link aus der Mail weiterhin öffnen.';
                weiter('kunden/' . $azKid);

            case 'g3_wunsch_ja':
            case 'g3_wunsch_nein':
                /* W4: Wunsch eines Partners — erst nach Uwes Ja rechnet der PC. */
                require_once __DIR__ . '/src/MkMedium.php';
                $g3wId = (int) ($_POST['auftrag_id'] ?? 0);
                if (MkMedium::wunschEntscheiden($g3wId, $tat === 'g3_wunsch_ja')) {
                    Events::pruefspur($tat, 'mk_auftraege', $g3wId, ['status' => 'pruefen'], ['status' => $tat === 'g3_wunsch_ja' ? 'wartet' : 'abgebrochen']);
                    $_SESSION['gut'] = $tat === 'g3_wunsch_ja' ? 'Freigegeben — dein PC rechnet es in der nächsten Nachtschicht.' : 'Abgelehnt. Der Partner sieht „bitte anders formulieren“.';
                } else { $_SESSION['fehler'] = 'Dieser Wunsch ist schon entschieden.'; }
                weiter('freigabe#partner3d');

            /* Titelbilder der Partnerseiten (03.10.2026, B1/B3/B4): bestellen, freigeben, verwerfen. */
            case 'koepfe_bestellen':
                require_once __DIR__ . '/src/PartnerKopf.php';
                $pkN = PartnerKopf::bestellen();
                $_SESSION[$pkN > 0 ? 'gut' : 'fehler'] = $pkN > 0 ? $pkN . ' Titelbilder bestellt — dein PC rechnet sie in der nächsten Nachtschicht. Danach hier ansehen und freigeben.' : 'Es fehlt nichts, oder alles ist schon beim PC.';
                weiter('freigabe#koepfe');
            case 'kopf_freigeben':
            case 'kopf_verwerfen':
                require_once __DIR__ . '/src/PartnerKopf.php';
                $pkId = (int) ($_POST['kopf_id'] ?? 0);
                if ($tat === 'kopf_freigeben') {
                    $pkF = PartnerKopf::freigeben($pkId);
                    if ($pkF === null) { Events::pruefspur($tat, 'partner_koepfe', $pkId, ['status' => 'wartet'], ['status' => 'frei']); $_SESSION['gut'] = 'Freigegeben — steht ab sofort auf den passenden Partnerseiten.'; }
                    else { $_SESSION['fehler'] = $pkF; }
                } elseif (PartnerKopf::verwerfen($pkId)) {
                    Events::pruefspur($tat, 'partner_koepfe', $pkId, ['status' => 'wartet'], ['status' => 'verworfen']);
                    $_SESSION['gut'] = 'Verworfen. „Rechnen lassen“ bestellt es mit neuem Blickwinkel neu.';
                } else { $_SESSION['fehler'] = 'Dieses Titelbild ist schon entschieden.'; }
                weiter('freigabe#koepfe');

            /* Studio (03.10.2026): ein Eintrag aus dem Katalog — immer nur einer gleichzeitig. */
            case 'studio_produzieren':
                require_once __DIR__ . '/src/MkStudio.php';
                $stR = MkStudio::produzieren((string) ($_POST['katalog'] ?? ''), ($_POST['art'] ?? '') === 'bild' ? 'bild' : 'video', (string) ($_POST['motor'] ?? 'auto'),
                    (string) ($_POST['sprache'] ?? 'de'), (string) ($_POST['format'] ?? ''));
                if (is_int($stR)) { $_SESSION['gut'] = 'Im Studio angestoßen. Kie.ai entsteht in wenigen Minuten, 3D in der nächsten Nachtschicht — danach hier ansehen und freigeben.'; }
                else { $_SESSION['fehler'] = $stR; }
                weiter('freigabe#partner3d');

            /* 03.10.2026 (Uwe: „die 3D-Videos und Bilder … lösche alle“): umkehrbar verworfen, Dateien bleiben bis zum endgültigen Löschen. */
            case 'galerie_leeren':
                require_once __DIR__ . '/src/MkStudio.php';
                $glR = MkStudio::galerieLeeren();
                $_SESSION['gut'] = $glR['medien'] . ' Bilder/Videos aus Dashboard und Verwaltung genommen, ' . $glR['auftraege'] . ' offene Aufträge abgebrochen.';
                weiter('freigabe#partner3d');

            case 'vecom_spot':
                /* Werbespot (01.10.2026): Vecom-Spot über alle Branchen-Szenen, landet in der Galerie unten. */
                require_once __DIR__ . '/src/MkMedium.php';
                require_once __DIR__ . '/src/MkStudio.php';
                /* Seit dem Studio (03.10.2026) geht auch der Vecom-Spot nur, wenn nichts anderes läuft. */
                $vsR = MkStudio::laeuft() !== null ? 'Es läuft schon ein Studio-Auftrag — der Spot geht, sobald er fertig ist.' : MkStudio::produzieren('v_spot', 'video', 'spot', (string) ($_POST['sprache'] ?? 'it'), (string) ($_POST['format'] ?? '9:16'));
                if (is_int($vsR)) { $_SESSION['gut'] = 'Vecom-Werbespot liegt bereit — dein PC rechnet ihn in der nächsten Nachtschicht (etwa eine Stunde). Danach hier ansehen und freigeben.'; }
                else { $_SESSION['fehler'] = $vsR; }
                weiter('freigabe#partner3d');

            case 'galerie_starter':
            case 'galerie_freigeben':
            case 'galerie_verwerfen':
                require_once __DIR__ . '/src/MkMedium.php';
                if ($tat === 'galerie_starter') {
                    $g3N = MkMedium::starterpaket('it');
                    $_SESSION['gut'] = $g3N . ' 3D-Aufträge für die Partner-Galerie liegen bereit — dein PC rechnet sie in der nächsten Nachtschicht. Danach hier ansehen und freigeben.';
                } else {
                    $g3M = MkMedium::laden((int) ($_POST['medium_id'] ?? 0));
                    if ($g3M === null || (int) $g3M['inhalt_id'] !== 0 || ((int) $g3M['galerie'] !== 1 && empty($g3M['partner_id']))) { $_SESSION['fehler'] = 'Kein Galerie-Bild.'; }
                    else {
                        MkMedium::status((int) $g3M['id'], $tat === 'galerie_freigeben' ? 'gewaehlt' : 'verworfen');
                        Events::pruefspur($tat, 'mk_medien', (int) $g3M['id'], ['status' => $g3M['status']], ['status' => $tat === 'galerie_freigeben' ? 'gewaehlt' : 'verworfen']);
                        $_SESSION['gut'] = $tat === 'galerie_freigeben' ? (!empty($g3M['partner_id']) ? 'Freigegeben — der Partner sieht es jetzt in seinem Dashboard.' : 'Steht jetzt in der Galerie aller Partner.') : 'Verworfen.';
                        if ($tat === 'galerie_freigeben') {
                            /* Schalter „Neue Videos sofort aufs Handy“ (03.10.2026, PartnerAutomatik) */
                            require_once __DIR__ . '/src/PartnerAutomatik.php';
                            $g3Push = (static function (int $id): int { try { return PartnerAutomatik::medienMelden($id); } catch (Throwable $e) { return 0; } })((int) $g3M['id']);
                            if ($g3Push > 0) { $_SESSION['gut'] .= ' ' . $g3Push . ' Partner per Handy benachrichtigt.'; }
                        }
                    }
                }
                weiter('freigabe#partner3d');

            case 'stapel_medium':
                require_once __DIR__ . '/src/MkMedium.php';
                $f = MkMedium::status((int) ($_POST['medium_id'] ?? 0), 'gewaehlt');
                if ($f !== null) { $_SESSION['fehler'] = $f; }
                weiter('freigabe?land=' . (strtoupper((string) ($_POST['land'] ?? '')) === 'DE' ? 'DE' : 'IT'));

            /* Wochen-Autopilot (Marketing-Studio 7, Uwe: „ja“ zu U4) — je Land, ab Werk aus. */
            case 'autopilot_speichern':
                require_once __DIR__ . '/src/MkAutopilot.php';
                $apLand = strtoupper((string) ($_POST['land'] ?? ''));
                $f = MkAutopilot::speichern($apLand, $_POST);
                $apN = $f === null ? MkAutopilot::naechsterLauf($apLand) : null;
                $_SESSION[$f === null ? 'gut' : 'fehler'] = $f ?? (!empty($_POST['an'])
                    ? 'Autopilot für ' . MkLand::name($apLand) . ' ist an — nächster Lauf ' . ($apN !== null && $apN <= time() + 600 ? 'beim nächsten Cronlauf' : date('d.m. \u\m H:i', (int) $apN)) . '.'
                    : 'Autopilot für ' . MkLand::name($apLand) . ' ist aus.');
                weiter('freigabe?land=' . $apLand . '#autopilot');

            case 'uebersetzen_starten':
                require_once __DIR__ . '/src/MkAuftrag.php';
                $maErg = MkAuftrag::anlegenUebersetzen();
                $_SESSION[is_int($maErg) ? 'gut' : 'fehler'] = is_int($maErg)
                    ? 'Übersetzung angestoßen. Dein PC holt sie in den nächsten fünf Minuten ab; Claude braucht dann wenige Minuten.'
                    : $maErg;
                weiter((($_POST['zurueck'] ?? '') === 'inhalte' ? 'inhalte' : 'zielgruppen') . '?land=IT#auftraege');

            /* Content-Studio (Marketing-Studio Schritt 2, 01.10.2026) */
            case 'inhalte_erstellen':
                require_once __DIR__ . '/src/MkAuftrag.php';
                $maErg = MkAuftrag::anlegenInhalte($_POST);
                $_SESSION[is_int($maErg) ? 'gut' : 'fehler'] = is_int($maErg)
                    ? 'Schreibauftrag angestoßen. Dein PC holt ihn in den nächsten fünf Minuten ab; Claude braucht dann etwa 5–15 Minuten.'
                    : $maErg;
                weiter('inhalte#auftraege');

            case 'inhalte_abbrechen':
                require_once __DIR__ . '/src/MkAuftrag.php';
                $f = MkAuftrag::abbrechen((int) ($_POST['id'] ?? 0));
                $_SESSION[$f === null ? 'gut' : 'fehler'] = $f ?? 'Abgebrochen.';
                weiter('inhalte#auftraege');

            /* Bilder und Videos (Marketing-Studio Schritt 3, 01.10.2026) */
            case 'medium_erzeugen':
                require_once __DIR__ . '/src/MkMedium.php';
                $mmInhalt = (int) ($_POST['id'] ?? 0);
                $maErg = MkMedium::anlegen($mmInhalt, (string) ($_POST['medium'] ?? 'bild'), (string) ($_POST['modell'] ?? ''), (string) ($_POST['format'] ?? ''), !empty($_POST['sofort']), (string) ($_POST['eigen'] ?? ''));
                /* Marketing-Studio 11: Was angestoßen wurde, steht in den Aufträgen — Kie.ai sofort, 3D in der Nachtschicht (oder sofort). */
                $mmNeu = is_int($maErg) ? Db::all("SELECT parameter FROM mk_auftraege WHERE art = 'medien' AND status = 'wartet' AND parameter LIKE ?", ['%"inhalt_id":' . $mmInhalt . ',%']) : [];
                $mmDrei = (bool) array_filter($mmNeu, static fn($z) => str_contains((string) $z['parameter'], '"drei_d":true'));
                $mmKie = (bool) array_filter($mmNeu, static fn($z) => !str_contains((string) $z['parameter'], '"drei_d":true'));
                $mmM = MkMedium::motor();
                $_SESSION[is_int($maErg) ? 'gut' : 'fehler'] = is_int($maErg)
                    ? trim(($mmKie ? 'Kie.ai: Dein PC prüft zuerst dein Guthaben, dann entsteht ' . (($_POST['medium'] ?? 'bild') === 'video' ? 'das Video (etwa 2–5 Minuten).' : 'das Bild (etwa 1 Minute).') : '')
                        . ($mmDrei ? ' 3D: ' . (!empty($_POST['sofort']) || MkMedium::imFenster() ? 'dein PC rechnet jetzt' : 'dein PC rechnet in der Nachtschicht (ab ' . $mmM['nacht_von'] . ' Uhr)') . ' — Bild etwa 5–10 Minuten, Film etwa 1–2 Stunden.' : ''))
                    : $maErg;
                weiter('inhalte/' . $mmInhalt . '#medien');

            /* 01.10.2026: Bild oder Video frei per Prompt — ohne vorhandenen Beitrag */
            case 'medium_frei':
                require_once __DIR__ . '/src/MkMedium.php';
                require_once __DIR__ . '/src/MkLand.php';
                $mfArt = (string) ($_POST['medium'] ?? 'bild');
                $mfErg = MkMedium::frei($mfArt, (string) ($_POST['eigen'] ?? ''), (string) ($_POST['format'] ?? ''), (string) ($_POST['land'] ?? MkLand::wahl()), (string) ($_POST['modell'] ?? ''));
                if (!is_int($mfErg)) { $_SESSION['fehler'] = $mfErg; weiter('inhalte#perprompt'); }
                $_SESSION['gut'] = 'Kie.ai: Dein PC prüft zuerst dein Guthaben, dann entsteht ' . ($mfArt === 'video' ? 'das Video (etwa 2–5 Minuten).' : 'das Bild (etwa 1 Minute).') . ' Es erscheint hier unter „Bilder und Videos“.';
                weiter('inhalte/' . $mfErg . '#medien');

            case 'medium_status':
                require_once __DIR__ . '/src/MkMedium.php';
                $mm = MkMedium::laden((int) ($_POST['medium_id'] ?? 0));
                $f = MkMedium::status((int) ($_POST['medium_id'] ?? 0), (string) ($_POST['status'] ?? ''));
                if ($f !== null) { $_SESSION['fehler'] = $f; }
                weiter('inhalte/' . (int) ($mm['inhalt_id'] ?? 0) . '#medien');

            case 'medien_abbrechen':
                require_once __DIR__ . '/src/MkAuftrag.php';
                $f = MkAuftrag::abbrechen((int) ($_POST['auftrag'] ?? 0));
                $_SESSION[$f === null ? 'gut' : 'fehler'] = $f ?? 'Abgebrochen.';
                weiter('inhalte/' . (int) ($_POST['id'] ?? 0) . '#medien');

            /* Verpasstes nachposten (03.10.2026): nächster freier Platz oder gewählte Zeit; ein Entwurf wird mit diesem Klick freigegeben. */
            case 'nachposten':
            case 'nachposten_alle':
                require_once __DIR__ . '/src/MkNachposten.php';
                if ($tat === 'nachposten_alle') {
                    $npA = MkNachposten::alle(in_array((string) ($_POST['land'] ?? ''), ['IT', 'DE'], true) ? (string) $_POST['land'] : null);
                    $_SESSION[$npA['verteilt'] > 0 ? 'gut' : 'fehler'] = $npA['verteilt'] . ' Beiträge auf die nächsten freien Plätze gelegt' . ($npA['nicht'] > 0 ? ', ' . $npA['nicht'] . ' gehen nur als Paket.' : '.');
                    weiter('inhalte#nachposten');
                }
                $npId = (int) ($_POST['id'] ?? 0);
                $npZeit = trim((string) ($_POST['wann_frei'] ?? ''));
                // „Zu dieser Zeit“ ohne Zeit darf nie still auf den nächsten Platz fallen.
                $npR = !empty($_POST['mit_zeit']) && $npZeit === '' ? ['ok' => false, 'text' => 'Bitte erst Datum und Uhrzeit wählen.']
                     : MkNachposten::nachposten($npId, !empty($_POST['mit_zeit']) ? $npZeit : '');
                $_SESSION[$npR['ok'] ? 'gut' : 'fehler'] = $npR['text'];
                weiter(!empty($_POST['zurueck_einzeln']) ? 'inhalte/' . $npId . '#posten' : 'inhalte#nachposten');

            /* Veröffentlichen (Marketing-Studio Schritt 4, 01.10.2026) */
            case 'inhalt_posten':
            case 'inhalt_planen':
                require_once __DIR__ . '/src/MkVeroeffentlichen.php';
                $miId = (int) ($_POST['id'] ?? 0);
                if ($tat === 'inhalt_posten') {
                    $mvE = MkVeroeffentlichen::jetzt($miId);
                    $_SESSION[$mvE['ok'] ? 'gut' : 'fehler'] = $mvE['ok']
                        ? (!empty($mvE['wartet']) ? 'Instagram verarbeitet das Video noch — der nächste Cronlauf veröffentlicht es.' : 'Veröffentlicht.')
                        : (string) $mvE['grund'];
                } else {
                    $f = MkVeroeffentlichen::planen($miId, trim((string) ($_POST['wann'] ?? '')));
                    $_SESSION[$f === null ? 'gut' : 'fehler'] = $f ?? (trim((string) ($_POST['wann'] ?? '')) === '' ? 'Planung aufgehoben.' : 'Geplant — der Cronlauf veröffentlicht es zur gewählten Zeit.');
                }
                weiter('inhalte/' . $miId . '#posten');

            /* Marketing-Studio 9: Vorher/Nachher aus einem fertigen Projekt (nur mit Zustimmung des Kunden). */
            case 'vorher_nachher_erstellen':
                require_once __DIR__ . '/src/MkVorherNachher.php';
                $vnKunde = (int) ($_POST['kunde'] ?? 0);
                $vnPunkte = null;
                foreach (MkVorherNachher::kandidaten() as $vnK) {
                    if ((int) $vnK['id'] !== $vnKunde) { continue; }
                    /* Gemessen, nicht behauptet: der Website-Check der neuen Seite, so wie ihn jeder Besucher machen kann. */
                    $vnC = sicher(static function () use ($vnK): array { require_once __DIR__ . '/src/PartnerSeite.php'; return PartnerSeite::kurzcheck((string) $vnK['domain'], 'verwaltung'); }, ['ok' => false]);
                    if (!empty($vnC['ok'])) { $vnPunkte = count(array_filter((array) $vnC['punkte'], static fn($pp) => ($pp['stand'] ?? '') === 'gut')); }
                }
                $maErg = MkVorherNachher::erstellen($vnKunde, $vnPunkte);
                $_SESSION[is_int($maErg) ? 'gut' : 'fehler'] = is_int($maErg)
                    ? 'Drei Entwürfe angelegt. Dein PC fotografiert jetzt die neue Website und setzt das Vorher/Nachher-Bild zusammen (in den nächsten fünf Minuten).'
                    : $maErg;
                weiter('inhalte#vorher-nachher');

            /* Marketing-Studio 8: freigegebenen Beitrag den Partnern zum Teilen geben (mit ihrem Link). */
            case 'inhalt_partner':
                require_once __DIR__ . '/src/MkPartnerBeitraege.php';
                $miId = (int) ($_POST['id'] ?? 0);
                $f = MkPartnerBeitraege::setzen($miId, !empty($_POST['an']));
                $_SESSION[$f === null ? 'gut' : 'fehler'] = $f ?? (!empty($_POST['an']) ? 'Die Partner sehen den Beitrag jetzt im Portal (Reiter Werben) — mit ihrem eigenen Link.' : 'Für Partner zurückgezogen.');
                weiter('inhalte/' . $miId . '#posten');

            case 'inhalt_speichern':
            case 'inhalt_freigeben':
            case 'inhalt_veroeffentlicht':
            case 'inhalt_verwerfen':
                require_once __DIR__ . '/src/MkInhalt.php';
                $miId = (int) ($_POST['id'] ?? 0);
                $f = match ($tat) {
                    'inhalt_speichern'       => MkInhalt::speichern($miId, $_POST),
                    'inhalt_freigeben'       => MkInhalt::freigeben($miId, (int) ($_POST['kampagne'] ?? 0) ?: null),
                    'inhalt_veroeffentlicht' => MkInhalt::veroeffentlicht($miId),
                    default                  => MkInhalt::verwerfen($miId),
                };
                $_SESSION[$f === null ? 'gut' : 'fehler'] = $f ?? match ($tat) {
                    'inhalt_speichern' => 'Gespeichert.',
                    /* G3 (01.10.2026): Freigeben plant überall gleich — wie Stapel und Telegram. */
                    'inhalt_freigeben' => (static function () use ($miId): string { require_once __DIR__ . '/src/MkVeroeffentlichen.php'; return MkVeroeffentlichen::nachFreigabe($miId); })(),
                    'inhalt_veroeffentlicht' => 'Als veröffentlicht vermerkt. Klicks und Leads siehst du in der Kampagne.',
                    default => 'Verworfen.',
                };
                weiter($tat === 'inhalt_verwerfen' && $f === null ? 'inhalte' : 'inhalte/' . $miId);

            case 'recherche_abbrechen':
                require_once __DIR__ . '/src/MkAuftrag.php';
                $f = MkAuftrag::abbrechen((int) ($_POST['id'] ?? 0));
                $_SESSION[$f === null ? 'gut' : 'fehler'] = $f ?? 'Abgebrochen.';
                weiter('zielgruppen#auftraege');

            case 'recherche_status':
                require_once __DIR__ . '/src/MkZielgruppe.php';
                $f = MkZielgruppe::rechercheStatus((int) ($_POST['id'] ?? 0), (string) ($_POST['status'] ?? ''));
                if ($f !== null) { $_SESSION['fehler'] = $f; }
                /* Zurück dorthin, wo der Knopf stand: die Liste (mit Filter) oder eine Zielgruppe. */
                $mrZ = (string) ($_POST['zurueck'] ?? '');
                weiter(preg_match('~^zielgruppen/\d+$~', $mrZ) ? $mrZ . '#funde' : 'zielgruppen' . (preg_match('/^[A-Za-z=&_0-9-]*$/', $mrZ) && $mrZ !== '' ? '?' . $mrZ : '') . '#funde');

            /* Kampagnen (Growth Engine Phase 3, 30.09.2026, Uwe: „ja“) */
            case 'kampagne_anlegen':
                require_once __DIR__ . '/src/MkKampagne.php';
                $mkErg = MkKampagne::anlegen($_POST);
                if (is_string($mkErg)) { $_SESSION['fehler'] = $mkErg; weiter('kampagnen#neu'); }
                $_SESSION['gut'] = 'Kampagne angelegt — der Link ist fertig zum Teilen.';
                weiter('kampagnen/' . $mkErg);

            case 'kampagne_aendern':
                require_once __DIR__ . '/src/MkKampagne.php';
                $mkId = (int) ($_POST['id'] ?? 0);
                $f = MkKampagne::aendern($mkId, $_POST);
                $_SESSION[$f === null ? 'gut' : 'fehler'] = $f ?? 'Gespeichert.';
                weiter('kampagnen/' . $mkId);

            /* TikTok täglich (03.10.2026) — Schalter; Fragen stellt jedes Stück selbst (Freigabe) */
            case 'tiktok_takt':
                require_once __DIR__ . '/src/MkTiktokTakt.php';
                MkTiktokTakt::schalten(($_POST['an'] ?? '') === '1');
                $_SESSION['gut'] = MkTiktokTakt::an() ? 'TikTok täglich ist an. Der nächste Cronlauf gibt den ersten Schreibauftrag an deinen PC.' : 'TikTok täglich ist aus.';
                weiter('kanaele#pf-tiktok');

            /* P1/P2 (01.10.2026): Kanäle verbinden */
            case 'kanal_pruefen':
                require_once __DIR__ . '/src/MkKanaele.php';
                $kzR = MkKanaele::pruefen((string) ($_POST['kanal'] ?? ''));
                $_SESSION[$kzR['ok'] ? 'gut' : 'fehler'] = $kzR['text'];
                weiter('kanaele');

            case 'kanal_meta_speichern':
                require_once __DIR__ . '/src/MetaSeite.php';
                MetaSeite::speichern($_POST);
                Events::pruefspur('meta_einstellungen', 'settings', null, [], ['seite_id' => MetaSeite::einstellungen()['seite_id'], 'ig_id' => MetaSeite::einstellungen()['ig_id']]);
                /* 01.10.2026: Mit neuem Schlüssel gleich prüfen — das trägt Seite, Instagram und WhatsApp selbst ein. */
                if (trim((string) ($_POST['token'] ?? '')) !== '') {
                    require_once __DIR__ . '/src/MkKanaele.php';
                    $kmR = MkKanaele::pruefen('facebook');
                    $_SESSION[$kmR['ok'] ? 'gut' : 'fehler'] = 'Gespeichert und geprüft: ' . $kmR['text'];
                } else {
                    $_SESSION['gut'] = 'Gespeichert. Jetzt „Verbindung prüfen“ drücken.';
                }
                weiter('kanaele');

            /* „Mit Meta verbinden“ (01.10.2026): ein Klick statt Schlüssel abschreiben */
            case 'meta_login_speichern':
            case 'meta_login':
                require_once __DIR__ . '/src/MetaLogin.php';
                if ($tat === 'meta_login_speichern') {
                    try { $f = MetaLogin::speichern($_POST); } catch (Throwable $e) { $f = $e->getMessage(); }
                    $_SESSION[$f === null ? 'gut' : 'fehler'] = $f ?? 'Gespeichert. Jetzt „Mit Meta verbinden“ drücken.';
                    weiter('kanaele#meta');
                }
                $_SESSION['meta_zustand'] = bin2hex(random_bytes(16));
                $mlUrl = MetaLogin::adresse($_SESSION['meta_zustand']);
                if ($mlUrl === null) { $_SESSION['fehler'] = 'Erst das App-Geheimnis der Meta-App speichern (App-Einstellungen › Allgemein).'; weiter('kanaele#meta'); }
                header('Location: ' . $mlUrl);
                exit;

            /* P4 (01.10.2026): LinkedIn, Google-Profil, YouTube, TikTok verbinden */
            case 'plattform_speichern':
            case 'plattform_verbinden':
            case 'plattform_trennen':
                require_once __DIR__ . '/src/MkPlattform.php';
                $pfP = (string) ($_POST['plattform'] ?? '');
                if (!isset(MkPlattform::ALLE[$pfP])) { $_SESSION['fehler'] = 'Unbekannte Plattform.'; weiter('kanaele#voll'); }
                if ($tat === 'plattform_speichern') {
                    try { $f = MkPlattform::speichern($pfP, $_POST); } catch (Throwable $e) { $f = $e->getMessage(); }
                    $_SESSION[$f === null ? 'gut' : 'fehler'] = $f ?? MkPlattform::ALLE[$pfP][0] . ' gespeichert.';
                    weiter('kanaele#pf-' . $pfP);
                }
                if ($tat === 'plattform_trennen') { MkPlattform::trennen($pfP); $_SESSION['gut'] = MkPlattform::ALLE[$pfP][0] . ' getrennt.'; weiter('kanaele#pf-' . $pfP); }
                $_SESSION['pf_zustand'] = bin2hex(random_bytes(16));
                $_SESSION['pf_plattform'] = $pfP;
                $pfUrl = MkPlattform::verbindenAdresse($pfP, $_SESSION['pf_zustand']);
                if ($pfUrl === null) { $_SESSION['fehler'] = 'Erst Client-ID und Client-Secret speichern.'; weiter('kanaele#pf-' . $pfP); }
                header('Location: ' . $pfUrl);
                exit;

            /* TikTok: Bestätigungsseite je Video (02.10.2026) — nie automatisch, nur nach diesem Klick */
            case 'tiktok_senden':
                require_once __DIR__ . '/src/MkInhalt.php';
                require_once __DIR__ . '/src/MkMedium.php';
                require_once __DIR__ . '/src/MkPlattform.php';
                $ttId = (int) ($_POST['id'] ?? 0);
                $ttX = MkInhalt::laden($ttId);
                $ttModus = ($_POST['modus'] ?? '') === 'entwurf' ? 'entwurf' : 'posten';
                if ($ttX === null || $ttX['plattform'] !== 'tiktok' || $ttX['status'] !== 'freigegeben') {
                    $_SESSION['fehler'] = 'Nur freigegebene TikTok-Stücke gehen an TikTok.'; weiter('inhalte/' . $ttId);
                }
                $ttVideo = MkMedium::gewaehlt($ttId, 'video');
                if ($ttVideo === null) { $_SESSION['fehler'] = 'Für dieses Stück ist noch kein Video gewählt.'; weiter('inhalte/' . $ttId . '#medien'); }
                $ttE = MkPlattform::ttSenden($ttX, $ttVideo, $_POST, $ttModus);
                $_SESSION[$ttE['ok'] ? 'gut' : 'fehler'] = MkPlattform::ttAbschliessen($ttId, $ttE, $ttModus);
                weiter('tiktok/' . $ttId);

            case 'inhalt_neu_versuchen':
                require_once __DIR__ . '/src/MkVeroeffentlichen.php';
                $kzE = MkVeroeffentlichen::jetzt((int) ($_POST['id'] ?? 0));
                $_SESSION[$kzE['ok'] ? 'gut' : 'fehler'] = $kzE['ok'] ? (!empty($kzE['wartet']) ? 'Instagram verarbeitet das Video noch — der nächste Lauf veröffentlicht es.' : 'Veröffentlicht.') : 'Wieder nicht geklappt: ' . $kzE['grund'];
                weiter('kanaele');

            /* K1–K3 (01.10.2026): Kampagnen löschen, ins Archiv, leere aufräumen */
            case 'kampagne_loeschen':
                require_once __DIR__ . '/src/MkKampagne.php';
                $mkId = (int) ($_POST['id'] ?? 0);
                $mkName = (string) (MkKampagne::laden($mkId)['name'] ?? '');
                $f = MkKampagne::loeschen($mkId, ($_POST['endgueltig'] ?? '') === '1');
                if ($f === 'zahlen') { $_SESSION['fehler'] = 'An dieser Kampagne hängen Besuche, Kosten oder Beiträge. Unten wählen: ins Archiv (Zahlen bleiben) oder endgültig löschen.'; weiter('kampagnen/' . $mkId . '#loeschen'); }
                $_SESSION[$f === null ? 'gut' : 'fehler'] = $f ?? 'Kampagne „' . $mkName . '“ gelöscht.';
                weiter($f === null ? 'kampagnen' : 'kampagnen/' . $mkId . '#loeschen');

            case 'kampagne_archivieren':
                require_once __DIR__ . '/src/MkKampagne.php';
                $mkId = (int) ($_POST['id'] ?? 0);
                $mkZur = ($_POST['zurueck'] ?? '') === '1';
                $f = MkKampagne::archivieren($mkId, $mkZur);
                $_SESSION[$f === null ? 'gut' : 'fehler'] = $f ?? ($mkZur ? 'Wieder aktiv — der Link zählt wieder.' : 'Im Archiv. Die Zahlen bleiben, der Link führt still auf die Startseite.');
                weiter(($_POST['zurueck_zu'] ?? '') === 'liste' ? 'kampagnen' : 'kampagnen/' . $mkId);

            case 'kampagnen_aufraeumen':
                require_once __DIR__ . '/src/MkKampagne.php';
                require_once __DIR__ . '/src/MkLand.php';
                $mkN = MkKampagne::aufraeumen(MkLand::wahl());
                $_SESSION['gut'] = $mkN > 0 ? $mkN . ' leere Kampagnen entfernt (kein Klick, keine Kosten, kein Beitrag).' : 'Nichts aufzuräumen.';
                weiter('kampagnen');

            case 'werbemittel_anlegen':
                require_once __DIR__ . '/src/MkKampagne.php';
                $mkId = (int) ($_POST['id'] ?? 0);
                $mkErg = MkKampagne::werbemittelAnlegen($mkId, $_POST);
                $_SESSION[is_string($mkErg) ? 'fehler' : 'gut'] = is_string($mkErg) ? $mkErg : 'Werbemittel angelegt — es hat seinen eigenen Link.';
                weiter('kampagnen/' . $mkId . '#werbemittel');

            case 'kampagne_kosten':
                require_once __DIR__ . '/src/MkKampagne.php';
                $mkId = (int) ($_POST['id'] ?? 0);
                $f = MkKampagne::kostenAnlegen($mkId, $_POST);
                $_SESSION[$f === null ? 'gut' : 'fehler'] = $f ?? 'Kosten eingetragen.';
                weiter('kampagnen/' . $mkId . '#kosten');

            /* Telegram Growth Engine T1 (01.10.2026): eigener Kanal-Einladungslink je Kampagne. */
            case 'kampagne_telegram_link':
                require_once __DIR__ . '/src/TelegramWachstum.php';
                $mkId = (int) ($_POST['id'] ?? 0);
                $tgE = TelegramWachstum::einladungAnlegen($mkId, Auth::name());
                if ($tgE['ok']) { Events::pruefspur('telegram_einladung', 'mk_kampagnen', $mkId, [], ['link' => $tgE['link'] ?? '']); }
                $_SESSION[$tgE['ok'] ? 'gut' : 'fehler'] = $tgE['text'];
                weiter('kampagnen/' . $mkId . '#telegram');

            /* Telegram Growth Engine T5 (01.10.2026, Uwe: „ja“): Verzeichnisse und Kooperationen. */
            case 'verzeichnis_vorbereiten':
                require_once __DIR__ . '/src/Verzeichnisse.php';
                $vzId = (int) ($_POST['id'] ?? 0);
                $vzE = Verzeichnisse::vorbereiten($vzId);
                $_SESSION[$vzE['ok'] ? 'gut' : 'fehler'] = $vzE['text'];
                zurueck('verzeichnisse?e=' . $vzId . '#v-' . $vzId);   // aus dem Ausfüll-Fenster zurück dorthin

            case 'verzeichnis_stand':
                require_once __DIR__ . '/src/Verzeichnisse.php';
                $vzId = (int) ($_POST['id'] ?? 0);
                $f = Verzeichnisse::status($vzId, (string) ($_POST['status'] ?? ''), (string) ($_POST['eintrag_url'] ?? ''),
                    isset($_POST['notiz']) ? (string) $_POST['notiz'] : null);
                $_SESSION[$f === null ? 'gut' : 'fehler'] = $f ?? 'Gespeichert: ' . (Verzeichnisse::STATUS[(string) $_POST['status']] ?? '') . '.';
                zurueck('verzeichnisse?e=' . $vzId . '#v-' . $vzId);

            /* Anmeldungen (01.10.2026, Uwe: „direkt auf die Seiten zum Registrieren, danach automatisch“) */
            case 'anmeldung_oeffnen':
                require_once __DIR__ . '/src/MkAnmeldungen.php';
                $anO = MkAnmeldungen::verzeichnisOeffnen((int) ($_POST['id'] ?? 0));
                if ($anO['ok']) { header('Location: ' . $anO['url'], true, 303); exit; }
                $_SESSION['fehler'] = $anO['text'];
                weiter('marketing#anmeldungen');

            case 'konto_vermerken':
                require_once __DIR__ . '/src/MkAnmeldungen.php';
                $f = MkAnmeldungen::kontoVermerken((string) ($_POST['konto'] ?? ''), ($_POST['angelegt'] ?? '1') === '1');
                $_SESSION[$f === null ? 'gut' : 'fehler'] = $f ?? (($_POST['angelegt'] ?? '1') === '1' ? 'Vermerkt. Sobald der Schlüssel unter Kanäle steht, postet Vecom dort selbst.' : 'Vermerk zurückgenommen.');
                weiter('marketing#anmeldungen');

            case 'verzeichnis_weg':   // 01.10.2026: selbst aufgenommene Einträge wieder entfernen
                require_once __DIR__ . '/src/Verzeichnisse.php';
                $f = Verzeichnisse::entfernen((int) ($_POST['id'] ?? 0));
                $_SESSION[$f === null ? 'gut' : 'fehler'] = $f ?? 'Eintrag entfernt.';
                weiter('verzeichnisse');

            case 'verzeichnis_anlegen':
                require_once __DIR__ . '/src/Verzeichnisse.php';
                $vzErg = Verzeichnisse::anlegen($_POST);
                $_SESSION[is_string($vzErg) ? 'fehler' : 'gut'] = is_string($vzErg) ? $vzErg : 'Aufgenommen.';
                weiter(is_string($vzErg) ? 'verzeichnisse#neu' : 'verzeichnisse?e=' . $vzErg . '#v-' . $vzErg);

            case 'verzeichnis_kanal_link':
                require_once __DIR__ . '/src/Verzeichnisse.php';
                require_once __DIR__ . '/src/TelegramWachstum.php';
                $vzId = (int) ($_POST['id'] ?? 0);
                $vzE = Verzeichnisse::laden($vzId);
                if ($vzE === null || empty($vzE['kampagne_id'])) {
                    $_SESSION['fehler'] = 'Erst „Eintrag vorbereiten“ — dann gibt es eine Kampagne, an der der Kanal-Link hängt.';
                } else {
                    $tgE = TelegramWachstum::einladungAnlegen((int) $vzE['kampagne_id'], Auth::name());
                    if ($tgE['ok']) { Events::pruefspur('telegram_einladung', 'mk_kampagnen', (int) $vzE['kampagne_id'], [], ['link' => $tgE['link'] ?? '', 'verzeichnis' => $vzId]); }
                    $_SESSION[$tgE['ok'] ? 'gut' : 'fehler'] = $tgE['text'];
                }
                weiter('verzeichnisse?e=' . $vzId . '#v-' . $vzId);

            case 'kampagne_kosten_loeschen':
                require_once __DIR__ . '/src/MkKampagne.php';
                $mkId = MkKampagne::kostenLoeschen((int) ($_POST['kosten_id'] ?? 0));
                $_SESSION['gut'] = 'Kosten entfernt.';
                weiter($mkId !== null ? 'kampagnen/' . $mkId . '#kosten' : 'kampagnen');

            case 'partner_bedingungen':
                require_once __DIR__ . '/src/Partner.php';
                $pid = (int) ($_POST['id'] ?? 0);
                $f = Partner::bedingungenSetzen($pid, $_POST);
                $_SESSION[$f === null ? 'gut' : 'fehler'] = $f ?? 'Gespeichert — gilt für künftige Provisionen dieses Partners.';
                weiter('partner/' . $pid);

            case 'partner_zuordnen':
                require_once __DIR__ . '/src/Partner.php';
                $pid = (int) ($_POST['id'] ?? 0);
                $r = Partner::zuordnen((int) ($_POST['kunde'] ?? 0), $pid, 'hand');
                $_SESSION[$r === 'zugeordnet' ? 'gut' : 'fehler'] = [
                    'zugeordnet' => 'Kunde zugeordnet. Künftige Zahlungen bringen diesem Partner Provision.',
                    'schon' => 'Der Kunde gehört schon zu einem Partner — es wird nicht umgehängt.',
                    'schon_kunde' => 'Der Kunde hat schon vorher gekauft.',
                    'selbst' => 'Das ist der Partner selbst — eigene Käufe bringen keine Provision.',
                    'empfehlung' => 'Der Kunde kam über eine Kundenempfehlung (Rabatt) — nicht zusätzlich einem Partner.',
                    'kein_partner' => 'Der Partner ist nicht aktiv.'][$r] ?? 'Nicht zugeordnet.';
                weiter('partner/' . $pid);

            case 'partner_provision_frei':
                require_once __DIR__ . '/src/Partner.php';
                Partner::freigeben((int) ($_POST['provision'] ?? 0));
                weiter('partner/' . (int) ($_POST['id'] ?? 0));

            case 'partner_provision_streichen':
                require_once __DIR__ . '/src/Partner.php';
                Partner::streichen((int) ($_POST['provision'] ?? 0), (string) ($_POST['grund'] ?? ''));
                weiter('partner/' . (int) ($_POST['id'] ?? 0));

            case 'partner_auszahlen':
                require_once __DIR__ . '/src/PartnerWege.php';
                $r = PartnerWege::auszahlen((int) ($_POST['id'] ?? 0));
                $_SESSION[$r['ok'] ? 'gut' : 'fehler'] = $r['text'];
                weiter('partner/' . (int) ($_POST['id'] ?? 0));

            case 'partner_verrechnen':
                require_once __DIR__ . '/src/PartnerWege.php';
                $r = PartnerWege::verrechnen((int) ($_POST['id'] ?? 0), (int) ($_POST['zahlung'] ?? 0));
                $_SESSION[$r['ok'] ? 'gut' : 'fehler'] = $r['text'];
                weiter('partner/' . (int) ($_POST['id'] ?? 0));

            case 'partner_sepa':
                require_once __DIR__ . '/src/PartnerWege.php';
                $r = PartnerWege::sepaDatei();
                if (!$r['ok']) { $_SESSION['fehler'] = $r['text']; zurueck('partner'); }
                header('Content-Type: application/xml; charset=utf-8');
                header('Content-Disposition: attachment; filename="vecom-provisionen-' . date('Y-m-d-His') . '.xml"');
                echo $r['xml'];
                exit;

            case 'partner_auszahlung_bestaetigen':
            case 'partner_auszahlung_abbrechen':
                require_once __DIR__ . '/src/PartnerWege.php';
                $ok = $tat === 'partner_auszahlung_bestaetigen'
                    ? PartnerWege::bestaetigen((int) ($_POST['auszahlung'] ?? 0))
                    : PartnerWege::abbrechen((int) ($_POST['auszahlung'] ?? 0));
                $_SESSION[$ok ? 'gut' : 'fehler'] = $ok ? ($tat === 'partner_auszahlung_bestaetigen'
                    ? 'Als ausgeführt gebucht — der Partner bekommt seine Mail.' : 'Abgebrochen — die Provisionen sind wieder auszahlungsbereit.')
                    : 'Diese Auszahlung ist nicht mehr offen.';
                zurueck('partner');

            case 'partner_wege':
                require_once __DIR__ . '/src/PartnerWege.php';
                $an = array_values(array_intersect(array_keys(PartnerWege::WEGE), (array) ($_POST['wege'] ?? [])));
                if (!$an) { $_SESSION['fehler'] = 'Mindestens ein Weg muss an sein.'; zurueck('partner'); }
                $vorher = Partner::einstellung('partner_wege');
                Db::run("INSERT INTO settings (skey, svalue) VALUES ('partner_wege', ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)", [implode(',', $an)]);
                Events::pruefspur('partner_wege', 'settings', null, ['partner_wege' => $vorher], ['partner_wege' => implode(',', $an)]);
                $_SESSION['gut'] = 'Gespeichert.';
                zurueck('partner');

            case 'partner_auszahlen_stripe':
                require_once __DIR__ . '/src/Partner.php';
                $r = Partner::auszahlenStripe((int) ($_POST['id'] ?? 0));
                $_SESSION[$r['ok'] ? 'gut' : 'fehler'] = $r['text'];
                weiter('partner/' . (int) ($_POST['id'] ?? 0));

            case 'partner_auszahlen_hand':
                require_once __DIR__ . '/src/Partner.php';
                $r = Partner::auszahlenHand((int) ($_POST['id'] ?? 0), (string) ($_POST['referenz'] ?? ''));
                $_SESSION[$r['ok'] ? 'gut' : 'fehler'] = $r['text'];
                weiter('partner/' . (int) ($_POST['id'] ?? 0));

            case 'partner_konto_pruefen':
                require_once __DIR__ . '/src/Partner.php';
                $pp = Partner::laden((int) ($_POST['id'] ?? 0));
                $_SESSION['gut'] = $pp && Partner::kontoPruefen($pp) ? 'Stripe: Das Konto kann Überweisungen empfangen.'
                                                                     : 'Stripe: Das Konto ist noch nicht fertig eingerichtet.';
                weiter('partner/' . (int) ($_POST['id'] ?? 0));

            case 'partner_stripe_neu':
                /* Bewusste Tat in der Akte (28.09.2026): nie automatisch. */
                require_once __DIR__ . '/src/Partner.php';
                $r = trim((string) ($_POST['land'] ?? '')) !== ''
                    ? Partner::stripeLandUmstellen((int) ($_POST['id'] ?? 0), (string) $_POST['land'], Auth::name() ?: 'Vecom')
                    : Partner::stripeNeuEinrichten((int) ($_POST['id'] ?? 0), Auth::name() ?: 'Vecom');
                $_SESSION[$r['ok'] ? 'gut' : 'fehler'] = $r['text'];
                weiter('partner/' . (int) ($_POST['id'] ?? 0));

            case 'partner_aktion':
                /* Zentrale Aktion für alle Partnerseiten (28.09.2026). */
                require_once __DIR__ . '/src/Partner.php';
                require_once __DIR__ . '/src/PartnerMarketing.php';
                $r = PartnerMarketing::aktionSpeichern(!empty($_POST['an']), (string) ($_POST['bis'] ?? ''), (array) ($_POST['texte'] ?? []));
                $_SESSION[$r === 'ok' ? 'gut' : 'fehler'] = ['ok' => 'Aktion gespeichert.', 'datum' => 'Bitte ein Enddatum wählen.', 'text' => 'Bitte mindestens einen Text eintragen.'][$r];
                if ($r === 'ok') { Events::protokoll('partner_aktion', 'Partner-Aktion ' . (!empty($_POST['an']) ? 'gespeichert bis ' . (string) ($_POST['bis'] ?? '') : 'ausgeschaltet')); }
                weiter('partner#aktion');

            case 'partner_klicks_null':
                /* Uwe, 28.09.2026: „Resete alle Klicks auf 0 … dann zählen erst weitere Klicks“ -- nur Besuche und Kanal-Klicks. */
                require_once __DIR__ . '/src/Partner.php';
                $r = Partner::klicksZuruecksetzen(Auth::name() ?: 'Vecom');
                $_SESSION['gut'] = 'Klicks auf 0: ' . $r['besuche'] . ' Besuche und ' . $r['kanal'] . ' Kanal-Klicks archiviert. Gezählt wird ab heute.';
                weiter('partner');

            case 'partner_stripe_alle_pruefen':
                /* Den Stand aller angefangenen Partnerkonten bei Stripe abholen -- nur lesen. */
                require_once __DIR__ . '/src/Partner.php';
                $n = Partner::stripeAlleAuffrischen();
                $_SESSION['gut'] = 'Stripe: Stand von ' . $n['geprueft'] . ' Partnerkonto' . ($n['geprueft'] === 1 ? '' : 'en') . ' abgeholt'
                    . ($n['fehler'] > 0 ? ' — ' . $n['fehler'] . ' ohne Antwort.' : '.');
                weiter('partner');

            case 'partner_loeschen':
                require_once __DIR__ . '/src/Partner.php';
                $r = Partner::loeschen((int) ($_POST['id'] ?? 0));
                $_SESSION[$r['ok'] ? 'gut' : 'fehler'] = $r['text'];
                if ($r['ok'] && !empty($r['ganz'])) { weiter('partner'); }
                weiter('partner/' . (int) ($_POST['id'] ?? 0));

            case 'kunde_zusammenfuehren':
            case 'kunde_dublette_nein':
                /* Dubletten (Phase 9b, Uwe: „Vorschlag + Zusammenführen per Klick“): nur der Admin, Zusammenführen mit Rückfrage (SCHWER). */
                require_once __DIR__ . '/src/KundenDubletten.php';
                if ($tat === 'kunde_dublette_nein') {
                    KundenDubletten::verschieden((int) ($_POST['a'] ?? 0), (int) ($_POST['b'] ?? 0), Auth::id());
                    $_SESSION['gut'] = 'Vermerkt: Die beiden sind verschieden — der Vorschlag kommt nicht wieder.';
                    weiter('kunden/dubletten');
                }
                [$kdZiel, $kdWeg] = array_map('intval', array_pad(explode(':', (string) ($_POST['paar'] ?? '')), 2, '0'));
                $kdR = KundenDubletten::zusammenfuehren($kdZiel, $kdWeg, Auth::id());
                $_SESSION[$kdR['ok'] ? 'gut' : 'fehler'] = $kdR['text'];
                weiter($kdR['ok'] ? 'kunden/' . $kdZiel : 'kunden/dubletten');

            case 'partner_test_anlegen':
            case 'partner_test_zuruecksetzen':
                /* Testpartner (Phase 9b): nur der Admin (nicht in TATEN_MITARBEIT). */
                require_once __DIR__ . '/src/PartnerTest.php';
                if ($tat === 'partner_test_anlegen') {
                    $tpP = PartnerTest::anlegen();
                    $_SESSION['gut'] = 'Testpartner angelegt (Code ' . $tpP['code'] . '). „Als Testpartner öffnen“ — den Gerätecode findest du unter „Was nicht läuft“.';
                } else {
                    $tpN = PartnerTest::zuruecksetzen();
                    $_SESSION['gut'] = 'Testpartner zurückgesetzt' . ($tpN ? ' (' . array_sum($tpN) . ' Einträge gelöscht).' : ' — es gab nichts zu löschen.');
                }
                weiter('partner#testpartner');

            case 'automation_schalten':
            case 'freigabe_genehmigen':
            case 'freigabe_ablehnen':
            case 'freigabe_zurueckstellen':
                /* AI Freigaben (AI Office Stufe 1, 06.10.2026): nur der Admin. Genehmigen ruft dieselbe
                   Methode wie der Knopf der Tat (Freigabe::ausfuehren); die Rückfrage der Tat steht am Formular. */
                require_once __DIR__ . '/src/Freigabe.php';
                $frId = (int) ($_POST['id'] ?? 0);
                $frWer = Auth::name() !== '' ? Auth::name() : 'Verwaltung';
                $frR = match ($tat) {
                    'freigabe_genehmigen' => Freigabe::genehmigen($frId, $frWer, array_intersect_key($_POST, ['text' => 1, 'betreff' => 1]), 'verwaltung', Auth::id()),
                    'freigabe_ablehnen' => Freigabe::ablehnen($frId, $frWer, trim((string) ($_POST['grund'] ?? ''))),
                    default => Freigabe::zurueckstellen($frId, (int) ($_POST['tage'] ?? 1), $frWer),
                };
                $_SESSION[$frR['ok'] ? 'gut' : 'fehler'] = $frR['text'] . (!empty($frR['post']) ? ' ' . str_replace("\n", ' · ', (string) $frR['post']) : '');
                zurueck('ai-freigaben#f' . $frId);

            case 'claude_erlauben':
            case 'claude_ablehnen':
                /* Claudes Lesezugang (AI Office Stufe 2, 07.10.2026): Uwe sagt Ja oder Nein auf der
                   Erlaubnis-Seite. Danach geht es zurück zu Claude — an die Rücksprungadresse, die beim
                   Anmelden des Programms gegen Claudes eigene geprüft wurde (ClaudeZugang::RUECKWEGE). */
                require_once __DIR__ . '/src/ClaudeZugang.php';
                $czWer = Auth::name() !== '' ? Auth::name() : 'Verwaltung';
                $czZiel = $tat === 'claude_erlauben'
                    ? ClaudeZugang::erlauben((string) ($_POST['a'] ?? ''), (int) Auth::id(), $czWer)
                    : ClaudeZugang::ablehnen((string) ($_POST['a'] ?? ''), $czWer);
                unset($_SESSION['nach_anmeldung']);
                if ($czZiel === null) {
                    $_SESSION['fehler'] = 'Diese Anfrage gilt nicht mehr (sie hält zehn Minuten). In Claude einfach noch einmal „Verbinden“ wählen.';
                    weiter('einstellungen?b=claude');
                }
                header('Location: ' . $czZiel, true, 303);
                exit;

            case 'umsatz_chance_verwerfen':
                /* Umsatz-Spürhund (AI Office Stufe 3): „lohnt nicht“ — die Chance bleibt danach weg. Nichts geht raus. */
                require_once __DIR__ . '/src/Spuerhund.php';
                $_SESSION[Spuerhund::verwerfen((int) ($_POST['id'] ?? 0), (string) ($_POST['grund'] ?? ''), Auth::name() !== '' ? Auth::name() : 'Verwaltung') ? 'gut' : 'fehler']
                    = 'Verworfen — der Spürhund meldet diese Chance nicht wieder.';
                zurueck('umsatz-chancen');

            case 'ki_texte_speichern':
                /* KI-Texte (07.10.2026, Vorschläge 11–13): Schalter je Bereich und Monatsbudget. Den Schlüssel trägt Uwe in config.local.php ein. */
                require_once __DIR__ . '/src/Ki.php';
                [$kiV, $kiN] = Ki::speichern($_POST);
                Events::pruefspur('ki_texte', 'settings', null, ['an' => $kiV['an'], 'budget' => $kiV['budget_cent'], 'bereiche' => $kiV['bereiche']],
                    ['an' => $kiN['an'], 'budget' => $kiN['budget_cent'], 'bereiche' => $kiN['bereiche']]);
                $_SESSION['gut'] = 'Gespeichert. ' . ($kiN['an'] ? ($kiN['schluessel'] ? 'Die KI schreibt mit.' : 'Es fehlt noch der Schlüssel in config.local.php (ki_schluessel).') : 'Die KI ist aus — es gehen nur die festen Vorlagen.');
                zurueck('einstellungen?b=claude#ki');

            case 'kunde_ki_schalten':
                /* Vorschlag 13: für einen Kunden nur die festen Vorlagen. */
                $kkId = (int) ($_POST['id'] ?? 0);
                $kkAus = (string) ($_POST['aus'] ?? '') === '1';
                Db::run('UPDATE customers SET ki_aus = ? WHERE id = ?', [$kkAus ? 1 : 0, $kkId]);
                Events::pruefspur('kunde_ki', 'customers', $kkId, [], ['ki_aus' => $kkAus ? 1 : 0]);
                $_SESSION['gut'] = $kkAus ? 'Für diesen Kunden schreibt die KI nichts mehr dazu — nur die festen Vorlagen.' : 'Die KI schreibt für diesen Kunden wieder mit.';
                zurueck('kunden/' . $kkId . '#emails');

            case 'claude_entziehen':
            case 'claude_zugang_schalten':
            case 'morgenbriefing_jetzt':
                require_once __DIR__ . '/src/ClaudeZugang.php';
                $czWer = Auth::name() !== '' ? Auth::name() : 'Verwaltung';
                if ($tat === 'claude_entziehen') {
                    $czOk = ClaudeZugang::entziehen((int) ($_POST['id'] ?? 0), 'entzogen von ' . $czWer);
                    $_SESSION[$czOk ? 'gut' : 'fehler'] = $czOk ? 'Verbindung entzogen. Claude kommt damit nicht mehr herein.' : 'Diese Verbindung war schon zu.';
                } elseif ($tat === 'claude_zugang_schalten') {
                    $czAn = (string) ($_POST['an'] ?? '') === '1';
                    ClaudeZugang::schalten($czAn, $czWer);
                    $_SESSION['gut'] = $czAn ? 'Claude-Zugang ist an. Verbinden geht wieder.' : 'Claude-Zugang ist aus — alle Verbindungen sind entzogen.';
                } else {
                    require_once __DIR__ . '/src/Morgenbriefing.php';
                    $mbR = Morgenbriefing::senden();
                    $_SESSION[$mbR['gesendet'] > 0 ? 'gut' : 'fehler'] = $mbR['gesendet'] > 0
                        ? 'Morgenbriefing an dein Telegram geschickt.'
                        : ($mbR['weg'] === 'zuruf' ? 'Telegram nicht erreichbar — das Briefing ging über den Ersatzweg.' : 'Telegram ist nicht verbunden (Einstellungen → Telegram).');
                }
                zurueck('einstellungen?b=claude');

            /* Prüfung 07.10.2026 (Befund 4): „sicherung_schluessel“ fiel früher in den Zweig von Claudes
               Erlaubnis durch — der Schlüssel ließ sich nicht eintragen. Jetzt hier, wohin er gehört. */
            case 'sicherung_schluessel':
            case 'sicherung_schluessel_weg':
                if (!Auth::istAdmin()) { throw new RuntimeException('Die Sicherung außer Haus richtet nur ein Admin ein.'); }
                /* Sicherung außer Haus (AI Office Stufe 0, 06.10.2026): nur der Admin, öffentlicher Schlüssel des Rechners. */
                require_once __DIR__ . '/src/SicherungAussen.php';
                $siWer = Auth::name() !== '' ? Auth::name() : 'Verwaltung';
                if ($tat === 'sicherung_schluessel_weg') {
                    SicherungAussen::schluesselEntfernen($siWer);
                    $_SESSION['gut'] = 'Schlüssel entfernt. Der Rechner holt nichts mehr ab.';
                } else {
                    $siR = SicherungAussen::schluesselSetzen((string) ($_POST['oeffentlich'] ?? ''), $siWer);
                    $_SESSION[$siR['ok'] ? 'gut' : 'fehler'] = $siR['text'];
                }
                zurueck('einstellungen?b=ueberwachung#sicherung');

            case 'ausgang_senden':
            case 'ausgang_alle_senden':
            case 'ausgang_verwerfen':
                /* Zurückgehaltene Mails aus dem Not-Aus (AI Office Stufe 0, 06.10.2026). Nur der Admin
                   (keine Mitarbeit-Tat); senden erst, wenn der Not-Aus gelöst ist. */
                require_once __DIR__ . '/src/Ausgang.php';
                $agWer = Auth::name() !== '' ? Auth::name() : 'Verwaltung';
                if ($tat === 'ausgang_alle_senden') {
                    if (Automation::notAus()) { $_SESSION['fehler'] = 'Erst den Not-Aus lösen — dann senden.'; zurueck('automationen#gehalten'); }
                    $agR = Ausgang::alleSenden($agWer);
                    $_SESSION[$agR['fehler'] === 0 ? 'gut' : 'fehler'] = $agR['gesendet'] . ' gesendet'
                        . ($agR['fehler'] > 0 ? ', ' . $agR['fehler'] . ' gingen nicht raus und warten weiter.' : '.');
                    zurueck('automationen#gehalten');
                }
                $agR = $tat === 'ausgang_senden'
                    ? Ausgang::senden((int) ($_POST['id'] ?? 0), $agWer)
                    : Ausgang::verwerfen((int) ($_POST['id'] ?? 0), $agWer);
                $_SESSION[$agR['ok'] ? 'gut' : 'fehler'] = $agR['text'];
                zurueck('automationen#gehalten');

            case 'automation_notaus':
            case 'automation_weiter':
                /* Automation Center (Phase 8, 06.10.2026, Uwe: „Schalten Admin, Not-Aus alle“): Schalten und Lösen nur
                   der Admin (Rechte::darfTat), den Not-Aus ziehen darf auch die Mitarbeit (TATEN_MITARBEIT). */
                require_once __DIR__ . '/src/Automation.php';
                $amWer = Auth::name() !== '' ? Auth::name() : 'Verwaltung';
                if ($tat === 'automation_notaus') {
                    Automation::notAusZiehen($amWer);
                    $_SESSION['gut'] = 'Not-Aus gezogen. Keine Automation schickt mehr etwas raus — Prüfungen und Sicherung laufen weiter.';
                    zurueck('automationen');
                }
                if ($tat === 'automation_weiter') {
                    Automation::notAusLoesen($amWer);
                    $_SESSION['gut'] = 'Not-Aus gelöst. Beim nächsten Lauf arbeiten alle eingeschalteten Automationen wieder.';
                    zurueck('automationen');
                }
                $amRegel = (string) ($_POST['regel'] ?? '');
                $amAn = ($_POST['an'] ?? '') === '1';
                $_SESSION[Automation::schalten($amRegel, $amAn, Auth::id()) ? 'gut' : 'fehler'] = isset(Automation::REGELN[$amRegel])
                    ? '„' . Automation::REGELN[$amRegel][0] . '“ ist ' . ($amAn ? 'eingeschaltet.' : 'ausgeschaltet — sie läuft erst wieder, wenn du sie einschaltest.')
                    : 'Unbekannte Automation.';
                weiter('automationen#' . rawurlencode($amRegel));

            case 'partner_res_entscheiden':
            case 'partner_res_erinnern':
                /* Akquise-CRM F (06.10.2026): Reservierung übernehmen / neu zuweisen / lösen — immer mit Provisionsentscheidung (Uwe: „Sie entscheiden je Fall“). */
                require_once __DIR__ . '/src/AkquisePartner.php';
                $prF = (int) ($_POST['firma'] ?? 0);
                if ($tat === 'partner_res_erinnern') {
                    $prOk = AkquisePartner::erinnern($prF);
                    $_SESSION[$prOk ? 'gut' : 'fehler'] = $prOk ? 'Erinnerung ist raus — als Hinweis aufs Handy des Partners (wenn er die App hat).' : 'Dieser Betrieb ist bei keinem Partner reserviert.';
                    weiter('partner-reservierungen');
                }
                $prR = AkquisePartner::entscheiden($prF, (string) ($_POST['aktion'] ?? ''), (string) ($_POST['provision'] ?? ''), (string) ($_POST['grund'] ?? ''), Auth::name(),
                    (int) ($_POST['neu_partner'] ?? 0) ?: null);
                $_SESSION[$prR['ok'] ? 'gut' : 'fehler'] = $prR['ok'] ? 'Erledigt — steht im Verlauf des Betriebs und in der Prüfspur.' : $prR['fehler'];
                weiter('partner-reservierungen');

            case 'partner_news_senden':
            case 'partner_news_zurueck':
                /* Neu von Vecom (Phase 7b-2): an eine Zielgruppe senden (Rückfrage aus Ablauf::TRAGWEITE) oder zurückziehen. */
                require_once __DIR__ . '/src/PartnerNews.php';
                if ($tat === 'partner_news_zurueck') {
                    $_SESSION[PartnerNews::zurueckziehen((int) ($_POST['id'] ?? 0)) ? 'gut' : 'fehler'] = 'Zurückgezogen — die Meldung steht bei keinem Partner mehr auf der Startseite.';
                    weiter('partner-meldungen');
                }
                [$nwZ, $nwW] = array_pad(explode(':', (string) ($_POST['ziel_kombi'] ?? 'alle:'), 2), 2, '');
                $nwR = PartnerNews::senden(['ziel' => $nwZ, 'ziel_wert' => $nwW] + $_POST, Auth::id());
                $_SESSION[$nwR['ok'] ? 'gut' : 'fehler'] = $nwR['ok']
                    ? 'Gesendet an ' . $nwR['an'] . ' Partner (' . $nwR['push'] . ' davon mit Hinweis aufs Handy).'
                    : (['titel' => 'Der italienische Titel fehlt (mindestens 3 Zeichen).', 'text' => 'Der italienische Text fehlt (mindestens 10 Zeichen).',
                        'ziel' => 'Unbekannte Zielgruppe.', 'link' => 'Der Link muss mit https:// oder / beginnen.', 'leer' => 'In dieser Zielgruppe ist gerade kein aktiver Partner — nichts gesendet.'][$nwR['grund']] ?? 'Nicht gesendet.');
                weiter('partner-meldungen');

            case 'partner_ticket_antwort':
            case 'partner_ticket_stand':
                /* Support (Phase 7b, 05.10.2026): in ein Ticket antworten (Mail + Push wie bisher über PartnerPost) und/oder
                   den Stand setzen. Antwort und Stand in einem Formular: „Antworten und erledigt“ ist der häufigste Fall. */
                require_once __DIR__ . '/src/PartnerTicket.php';
                $ptPid = (int) ($_POST['id'] ?? 0); $ptTid = (int) ($_POST['ticket'] ?? 0);
                $ptText = trim((string) ($_POST['text'] ?? ''));
                $ptStand = (string) ($_POST['stand'] ?? '');
                $ptMeld = [];
                if (!PartnerTicket::laden($ptPid, $ptTid)) { $_SESSION['fehler'] = 'Ticket nicht gefunden.'; weiter('partner/' . $ptPid . '#nachrichten'); }
                if ($ptText !== '') {
                    $ptR = PartnerTicket::antworten($ptPid, $ptTid, $ptText, 'vecom', Auth::id());
                    $ptMeld[] = $ptR === 'ok' ? 'Antwort verschickt' : 'Antwort nicht verschickt';
                }
                if ($ptStand !== '' && PartnerTicket::standSetzen($ptTid, $ptStand)) {
                    $ptMeld[] = 'Stand: ' . ['offen' => 'offen', 'in_arbeit' => 'in Arbeit', 'erledigt' => 'erledigt'][$ptStand];
                }
                $_SESSION[$ptMeld ? 'gut' : 'fehler'] = $ptMeld ? implode(' · ', $ptMeld) . '.' : 'Nichts geändert — Antwort leer und Stand gleich.';
                weiter('partner/' . $ptPid . '#ticket-' . $ptTid);

            case 'partner_nachricht':
                /* Antwort an einen Partner (26.09.2026): geht per Mail und als
                   Hinweis aufs Handy, steht danach auf seiner Seite. */
                require_once __DIR__ . '/src/PartnerPost.php';
                try {
                    PartnerPost::schreiben((int) ($_POST['id'] ?? 0), (string) ($_POST['text'] ?? ''), 'vecom', Auth::id());
                    $_SESSION['gut'] = 'Antwort verschickt.';
                } catch (InvalidArgumentException $e) { $_SESSION['fehler'] = 'Die Nachricht ist leer.'; }
                weiter('partner/' . (int) ($_POST['id'] ?? 0) . '#nachrichten');

            case 'partner_mediathek':
                /* Mediathek der Partner (Phase 3, 05.10.2026): Karte anlegen oder ändern, mit Bild (neu gerechnet, ohne EXIF). */
                require_once __DIR__ . '/src/PartnerMediathek.php';
                $pmId = (int) ($_POST['id'] ?? 0) > 0 ? (int) $_POST['id'] : null;
                $pmF = $_FILES['bild'] ?? null;
                $pmPfad = is_array($pmF) && (int) ($pmF['error'] ?? 4) === UPLOAD_ERR_OK && is_uploaded_file((string) $pmF['tmp_name']) ? (string) $pmF['tmp_name'] : null;
                if (is_array($pmF) && in_array((int) ($pmF['error'] ?? 4), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                    $_SESSION['fehler'] = 'Das Bild ist zu groß (höchstens ' . intdiv(PartnerMediathek::BILD_MAX_BYTE, 1_000_000) . ' MB).';
                    weiter('partner/mediathek' . ($pmId ? '#m-' . $pmId : ''));
                }
                $pmR = PartnerMediathek::speichern($_POST, $pmId, $pmPfad, $pmPfad !== null ? (int) $pmF['size'] : 0);
                if ($pmR['ok']) {
                    Events::protokoll('partner_mediathek', 'Mediathek-Karte ' . ($pmId ? 'geändert' : 'angelegt') . ': #' . $pmR['id'], null, null, null, ['id' => $pmR['id']]);
                    $_SESSION['gut'] = 'Gespeichert.' . ((string) ($_POST['status'] ?? '') === 'aktiv' ? ' Die Partner sehen die Karte sofort.' : ' Sichtbar wird sie mit dem Stand „aktiv“.');
                    weiter('partner/mediathek#m-' . $pmR['id']);
                }
                $_SESSION['fehler'] = ['zweck' => 'Bitte einen Zweck wählen.', 'titel' => 'Bitte einen Titel angeben (mindestens in einer Sprache).',
                    'inhalt' => 'Eine Karte braucht einen Text, ein Bild oder einen Verweis.', 'bild_gross' => 'Das Bild ist zu groß.',
                    'bild_art' => 'Das Bild muss JPG, PNG oder WebP sein.', 'fehlt' => 'Diese Karte gibt es nicht mehr.'][$pmR['grund']] ?? 'Speichern ging nicht.';
                weiter('partner/mediathek' . ($pmId ? '#m-' . $pmId : '?neu=1'));

            case 'partner_mediathek_status':
                require_once __DIR__ . '/src/PartnerMediathek.php';
                $pmId = (int) ($_POST['id'] ?? 0);
                if (PartnerMediathek::statusSetzen($pmId, (string) ($_POST['status'] ?? ''))) {
                    Events::protokoll('partner_mediathek', 'Mediathek-Karte #' . $pmId . ': ' . (string) $_POST['status'], null, null, null, ['id' => $pmId]);
                    $_SESSION['gut'] = (string) $_POST['status'] === 'aktiv' ? 'Aktiv — die Partner sehen die Karte jetzt.' : 'Stand geändert.';
                }
                weiter('partner/mediathek#m-' . $pmId);

            case 'partner_vorlage':
                /* Werbevorlage, FAQ oder Leitfaden in Uwes Fassung (27.09.2026). Leer = Standard. */
                require_once __DIR__ . '/src/PartnerVorlagen.php';
                $pvS = (string) ($_POST['schluessel'] ?? '');
                $pvFehler = [];
                foreach (['it', 'de', 'en'] as $pvL) {
                    $pvR = PartnerVorlagen::speichern($pvS, $pvL, (string) ($_POST['text'][$pvL] ?? ''));
                    if ($pvR !== 'ok') {
                        $pvFehler[] = strtoupper($pvL) . ': ' . (str_starts_with($pvR, 'platzhalter:') ? substr($pvR, 12) . ' fehlt — ohne ihn fehlt dem Partner der Link oder sein Name'
                            : ($pvR === 'zu_lang' ? 'zu lang (höchstens ' . PartnerVorlagen::MAX . ' Zeichen)' : 'unbekannte Vorlage'));
                    }
                }
                if ($pvFehler) { $_SESSION['fehler'] = 'Nicht alles gespeichert — ' . implode(' · ', $pvFehler); }
                else { $_SESSION['gut'] = 'Gespeichert. Die Partner sehen die neue Fassung sofort.'; }
                Events::protokoll('partner_vorlage', 'Partner-Vorlage bearbeitet: ' . $pvS, null, null, null, ['schluessel' => $pvS]);
                weiter('partner/vorlagen#v-' . preg_replace('~[^a-z0-9]+~', '-', $pvS));

            case 'partner_seite_zurueck':
                require_once __DIR__ . '/src/PartnerSeite.php';
                PartnerSeite::zuruecksetzen((int) ($_POST['id'] ?? 0));
                Events::protokoll('partner_seite_zurueck', 'Empfehlungsseite eines Partners auf Standard zurückgesetzt', null, null, null, ['partner_id' => (int) ($_POST['id'] ?? 0)]);
                $_SESSION['gut'] = 'Die Seite steht wieder auf Standard (Foto und Satz bleiben).';
                weiter('partner/' . (int) ($_POST['id'] ?? 0));

            case 'partner_profil_weg':
                /* Foto und Satz einer Empfehlungsseite entfernen (26.09.2026):
                   Beides steht öffentlich auf unserer Domain. */
                require_once __DIR__ . '/src/Partner.php';
                Db::run('UPDATE partner SET foto = NULL, foto_am = NULL, profil_satz = NULL WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
                Events::protokoll('partner_profil_weg', 'Empfehlungsseite eines Partners geleert', null, null, null, ['partner_id' => (int) ($_POST['id'] ?? 0)]);
                $_SESSION['gut'] = 'Foto und Satz sind entfernt.';
                weiter('partner/' . (int) ($_POST['id'] ?? 0));

            case 'partner_code':
                require_once __DIR__ . '/src/Partner.php';
                $f = Partner::codeSetzen((int) ($_POST['id'] ?? 0), (string) ($_POST['code'] ?? ''));
                $_SESSION[$f === null ? 'gut' : 'fehler'] = $f ?? 'Neuer Code gespeichert. Der alte Link führt ab jetzt nirgends mehr hin.';
                weiter('partner/' . (int) ($_POST['id'] ?? 0));

            case 'partner_lauf_freigeben':
            case 'partner_lauf_auszahlen':
                /* Auszahlungslauf (Phase 5): nur die gewählten Partner. Freigeben ändert nur den Status, Auszahlen geht je
                   Partner über PartnerWege::auszahlen — mit allen Prüfungen dort. Beides mit Rückfrage (Ablauf::TRAGWEITE). */
                require_once __DIR__ . '/src/PartnerGeld.php';
                $laufIds = array_map('intval', (array) ($_POST['partner'] ?? []));
                if (!$laufIds) { $_SESSION['fehler'] = 'Kein Partner gewählt.'; weiter('auszahlungen'); }
                if ($tat === 'partner_lauf_freigeben') {
                    $laufN = PartnerGeld::sammelFreigabe($laufIds);
                    $_SESSION['gut'] = $laufN . ' Provision' . ($laufN === 1 ? '' : 'en') . ' freigegeben.';
                } else {
                    $_SESSION['lauf_ergebnis'] = PartnerGeld::sammelAuszahlung($laufIds);
                    $laufOk = count(array_filter($_SESSION['lauf_ergebnis'], static fn($r) => $r['ok']));
                    $_SESSION[$laufOk > 0 ? 'gut' : 'fehler'] = $laufOk . ' von ' . count($_SESSION['lauf_ergebnis']) . ' Auszahlungen angestoßen — Einzelheiten unten.';
                }
                weiter('auszahlungen');

            case 'partner_vecom_adresse':
            case 'vecom_adressen_lesen':
                /* E-Mail-Center (Phase 7a, 05.10.2026): die BESTEHENDE @vecom-Adresse zuordnen (Uwe: „Email Adressen
                   bestehen schon“) oder die Liste aus dem KAS lesen. Im KAS wird dabei nichts angelegt oder verändert. */
                require_once __DIR__ . '/src/PartnerMail.php';
                $pmId = (int) ($_POST['id'] ?? 0);
                if ($tat === 'partner_vecom_adresse') {
                    $pmR = PartnerMail::adresseSetzen($pmId, (string) ($_POST['adresse'] ?? ''));
                    $_SESSION[$pmR === 'ok' ? 'gut' : 'fehler'] = ['ok' => 'Gespeichert. Mit Adresse hat der Partner das E-Mail-Center, ohne nicht.',
                        'form' => 'Nur eine Adresse @' . PartnerMail::DOMAIN . ' (Kleinbuchstaben, Ziffern, Punkt, Bindestrich).',
                        'belegt' => 'Diese Adresse ist schon einem anderen Partner zugeordnet.', 'partner' => 'Partner nicht gefunden.'][$pmR];
                } else {
                    $pmN = PartnerMail::kasLesen();
                    $pmAuto = $pmN > 0 ? PartnerMail::automatischZuordnen() : [];
                    $_SESSION[$pmN > 0 ? 'gut' : 'fehler'] = $pmN > 0 ? $pmN . ' Adressen @' . PartnerMail::DOMAIN . ' gelesen (nur lesen)' . ($pmAuto ? ', ' . count($pmAuto) . ' eindeutig zugeordnet: ' . implode(', ', array_column($pmAuto, 'adresse')) : ', keine eindeutig zuzuordnen') . '.'
                        : 'Keine Adressen gelesen — der KAS-Zugang des Kontos von vecom-design.it fehlt (Einstellungen › Zugänge & Schutz) oder stimmt nicht. Die Adresse kann trotzdem von Hand eingetragen werden.';
                }
                weiter('partner/' . $pmId . '#vecom-adresse');
            case 'partner_kurzlink':
            case 'partner_kurzlink_sperren':
                /* Kurzlink (Phase 4, 05.10.2026): Uwe setzt einen Namen (ohne Obergrenze) oder sperrt einen. Gesperrt führt nirgends hin. */
                require_once __DIR__ . '/src/Partner.php';
                require_once __DIR__ . '/src/PartnerKurzlink.php';
                $klId = (int) ($_POST['id'] ?? 0);
                if ($tat === 'partner_kurzlink') {
                    $klF = Partner::laden($klId) ? PartnerKurzlink::setzen($klId, (string) ($_POST['name'] ?? ''), true) : 'form';
                    $_SESSION[$klF === null ? 'gut' : 'fehler'] = $klF === null ? 'Kurzlink gespeichert. Der bisherige Name führt weiter zum Partner.'
                        : (['form' => '3 bis 30 Zeichen: Buchstaben, Ziffern und Bindestrich, am Anfang ein Buchstabe.', 'gesperrt' => 'Dieser Name ist reserviert.',
                            'belegt' => 'Diesen Namen hat schon ein anderer Partner (oder er ist gesperrt).'][$klF] ?? 'Nicht gespeichert.');
                } else {
                    $klOk = Db::wert('SELECT COUNT(*) FROM partner_kurznamen WHERE name = ? AND partner_id = ?', [(string) ($_POST['name'] ?? ''), $klId], 0) > 0
                        && PartnerKurzlink::sperren((string) ($_POST['name'] ?? ''));
                    $_SESSION[$klOk ? 'gut' : 'fehler'] = $klOk ? 'Gesperrt. Der Link führt ab jetzt auf die Startseite, der Name wird nie neu vergeben.' : 'Nicht gesperrt.';
                }
                weiter('partner/' . $klId);

            case 'bewertung_bitten':
                /* Seit AI Office Stufe 4 (07.10.2026) in Nachricht::bewertungBitten — dieselbe Tat genehmigt Uwe in AI Freigaben. */
                require_once __DIR__ . '/src/Nachricht.php';
                $bwR = Nachricht::bewertungBitten((int) ($_POST['id'] ?? 0));
                $_SESSION[$bwR['ok'] ? 'gut' : 'fehler'] = $bwR['text'];
                if ($bwR['ok']) { require_once __DIR__ . '/src/Freigabe.php'; Freigabe::vonHandErledigt('bewertung_bitten', ['kunde' => (int) ($_POST['id'] ?? 0)]); }
                zurueck('kunden/' . (int) ($_POST['id'] ?? 0));

            case 'einmalig_erledigt':
                require_once __DIR__ . '/src/Einmalig.php';
                if (Einmalig::erledigt((string) ($_POST['schluessel'] ?? ''))) { $_SESSION['gut'] = 'Abgehakt.'; }
                zurueck('heute');

            case 'einfuehrung_gesehen':
                require_once __DIR__ . '/src/Hilfe.php';
                if (Auth::id() !== null) { Hilfe::merken((int) Auth::id()); }
                zurueck('heute');

            case 'partner_connect_pruefen':
                require_once __DIR__ . '/src/Partner.php';
                $r = Partner::connectPruefen();
                $_SESSION[$r['ok'] ? 'gut' : 'fehler'] = $r['text'];
                weiter('partner#wege');

            case 'partner_token_neu':
                require_once __DIR__ . '/src/Partner.php';
                Partner::tokenNeu((int) ($_POST['id'] ?? 0));
                $_SESSION['gut'] = 'Neuer Zugang erzeugt — der alte Link zur Partnerseite gilt nicht mehr.';
                weiter('partner/' . (int) ($_POST['id'] ?? 0));

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
                require_once __DIR__ . '/src/Bezahllink.php';
                $zid = (int) ($_POST['id'] ?? 0);
                $z = Db::one('SELECT * FROM payments WHERE id = ?', [$zid]);
                $bst = $z ? Db::one('SELECT o.*, c.name AS kunde, c.email AS kunde_email, c.sprache AS kunde_sprache
                                     FROM orders o JOIN customers c ON c.id = o.customer_id WHERE o.id = ?',
                                     [(int) $z['order_id']]) : null;
                if (!$z || !$bst || !$z['link_url']) { throw new RuntimeException('Für diese Zahlung gibt es noch keinen Link.'); }
                // Auch ein schon erzeugter Link geht nicht vor dem Fragebogen raus.
                require_once __DIR__ . '/src/Onboarding.php';
                if (Onboarding::brauchtVorPreis((int) $bst['id']) && !Onboarding::fertig((int) $bst['customer_id'])) {
                    throw new RuntimeException('Erst der große Fragebogen, dann der Zahlungslink. '
                        . 'Solange der Kunde ihn nicht abgeschickt hat, steht der Preis nicht fest.');
                }
                // Und auch er geht nicht raus, solange das Angebot nicht angenommen ist.
                require_once __DIR__ . '/src/Angebot.php';
                $wartetS = Angebot::wartetAufZusage((int) $bst['id']);
                if ($wartetS !== null) { throw new RuntimeException(Angebot::warumKeinZahlungslink($wartetS)); }
                // Die Mail selbst steht seit dem 06.10.2026 in Nachricht -- derselbe Text
                // fuer diesen Knopf und fuer den automatischen Weg nach der Annahme.
                require_once __DIR__ . '/src/Nachricht.php';
                Nachricht::zahlungslinkMail($zid);
                // Keine Meldung: Der Knopf wurde gerade gedrueckt, und die
                // gruene Zeile oben sagt es schon. Dass die Mail rausging,
                // steht im Mailprotokoll des Vorgangs.
                $_SESSION['gut'] = 'Der Zahlungslink ist an ' . $bst['kunde_email'] . ' raus.';
                zurueck('bestellungen/' . (int) ($_POST['order_id'] ?? $bst['id']));

            case 'kunde_nachricht':
                // Seit 06.10.2026 in Nachricht::anKunde — dieselbe Stelle für die AI-Freigabe.
                require_once __DIR__ . '/src/Nachricht.php';
                $kid = (int) ($_POST['id'] ?? 0);
                Nachricht::anKunde($kid, (string) ($_POST['text'] ?? ''), (string) ($_POST['betreff'] ?? ''));
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
                /* Die Quelltext-Adresse steht im selben Formular, weil sie
                   dieselbe Frage beantwortet wie die Vorschau: Wo ist die
                   Seite? Nur eben fuer den, der sie aendern soll. Fehlt das
                   Feld -- etwa bei einem aelteren Formular --, bleibt der
                   bisherige Wert stehen statt geloescht zu werden. */
                $aendern = ['preview_url' => $url !== '' ? $url : null];
                if (array_key_exists('repo_url', $_POST)) {
                    $repo = trim((string) $_POST['repo_url']);
                    if ($repo !== '' && !preg_match('~^https?://~i', $repo)) { $repo = 'https://' . $repo; }
                    if ($repo !== '' && !filter_var($repo, FILTER_VALIDATE_URL)) {
                        throw new RuntimeException('Die Quelltext-Adresse sieht nicht nach einer Adresse aus. '
                            . 'Es wurde nichts geändert.');
                    }
                    $aendern['repo_url'] = $repo !== '' ? $repo : null;
                }
                Db::update('projects', $pid, $aendern);
                Events::pruefspur('aendern', 'project', $pid, [], $aendern);
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
                /* Der Ablauf steht seit 06.10.2026 in Nachricht::vorschauFreischalten — dieselbe
                   Stelle ruft auch eine genehmigte AI-Freigabe auf (AI Office Stufe 1). */
                require_once __DIR__ . '/src/Nachricht.php';
                $_SESSION['gut'] = Nachricht::vorschauFreischalten((int) ($_POST['id'] ?? 0))['text'];
                zurueck('vorgaenge');

            case 'vorschau_sperren':
                $pid = (int) ($_POST['id'] ?? 0);
                /* Die Abnahme geht mit zu. Wer nicht hinsehen darf, darf erst
                   recht nicht abnehmen -- und ein offener Abnahmeknopf ueber
                   einem gesperrten Entwurf waere genau der Fall, den 036
                   abstellen sollte. */
                Db::update('projects', $pid, ['vorschau_frei_am' => null, 'abnahme_frei_am' => null]);
                Events::protokoll('vorschau_gesperrt', 'Vorschau wieder gesperrt', null, null, $pid);
                $_SESSION['gut'] = 'Vorschau ist wieder gesperrt. Der Kunde sieht sie nicht mehr '
                                 . 'und kann auch nicht abnehmen.';
                zurueck('vorgaenge');

            /* ANSEHEN UND ABNEHMEN SIND ZWEIERLEI
               ------------------------------------------------------------
               Der zweite Schalter. Solange er zu ist, darf der Kunde den
               Entwurf ansehen, schreiben und Aenderungen wuenschen -- aber
               nicht abnehmen. An der Abnahme haengen Restzahlung und
               Veroeffentlichung; sie darf nicht nebenbei passieren, und
               schon gar nicht, bevor er die Seite ueberhaupt gesehen hat. */
            case 'abnahme_frei':
                require_once __DIR__ . '/src/Nachricht.php';
                $pid = (int) ($_POST['id'] ?? 0);
                $vp = (array) sicher(static fn() => Db::one(
                    'SELECT preview_url, vorschau_frei_am FROM projects WHERE id = ?', [$pid]), []);
                if (trim((string) ($vp['preview_url'] ?? '')) === ''
                    || ($vp['vorschau_frei_am'] ?? null) === null) {
                    throw new RuntimeException('Erst muss er die Seite ansehen koennen. '
                        . 'Schalte die Vorschau frei, dann die Abnahme — sonst nimmt er etwas ab, '
                        . 'das er nie gesehen hat.');
                }
                Db::update('projects', $pid, ['abnahme_frei_am' => date('Y-m-d H:i:s')]);
                Events::protokoll('abnahme_frei', 'Abnahme für den Kunden freigeschaltet', null, null, $pid);
                $rausAb = (bool) sicher(static fn() => Nachricht::abnahmeBereit($pid), false);
                $_SESSION['gut'] = 'Die Abnahme ist freigeschaltet. ' . ($rausAb
                    ? 'Der Kunde hat die Nachricht bekommen, dass die Seite fertig ist.'
                    : 'Die E-Mail ging nicht raus — auf seiner Seite steht es trotzdem.');
                zurueck('vorgaenge');

            case 'abnahme_sperren':
                $pid = (int) ($_POST['id'] ?? 0);
                Db::update('projects', $pid, ['abnahme_frei_am' => null]);
                Events::protokoll('abnahme_gesperrt', 'Abnahme wieder gesperrt', null, null, $pid);
                $_SESSION['gut'] = 'Die Abnahme ist wieder zu. Ansehen kann er die Seite weiterhin.';
                zurueck('vorgaenge');

            /* Marketing Center (03.10.2026, Phase 1): Katalog, Einkauf, Marge.
               Fehleingaben werfen InvalidArgumentException; der Fang unten
               zeigt sie als Meldung, gespeichert wird dann nichts. */
            case 'wm_standard':
                require_once __DIR__ . '/src/Werbemittel.php';
                Werbemittel::standardSetzen(
                    (int) trim((string) ($_POST['marge_prozent'] ?? '')),
                    (int) (Werbemittel::leerOderEuro($_POST['mindestmarge_eur'] ?? '') ?? 0));
                if (isset($_POST['zahlkosten_prozent'])) {
                    Werbemittel::zahlkostenSetzen((int) round(((float) str_replace(',', '.', (string) $_POST['zahlkosten_prozent'])) * 10),
                        (int) (Werbemittel::leerOderEuro($_POST['zahlkosten_fix_eur'] ?? '') ?? 0));
                }
                $_SESSION['gut'] = 'Marge und Zahlungskosten gespeichert — sie gelten ab sofort für alle Produkte ohne eigene Regel.';
                zurueck('werbemittel');

            case 'wm_produkt':
                require_once __DIR__ . '/src/Werbemittel.php';
                $wmId = Werbemittel::produktSpeichern($_POST, (int) ($_POST['id'] ?? 0));
                $_SESSION['gut'] = (int) ($_POST['id'] ?? 0) > 0 ? 'Produkt gespeichert.' : 'Produkt angelegt. Jetzt Varianten mit Einkaufspreis eintragen.';
                zurueck('werbemittel#wm-' . $wmId);

            case 'wm_angebot':
                require_once __DIR__ . '/src/Werbemittel.php';
                Werbemittel::angebotSpeichern((int) ($_POST['variante_id'] ?? 0), $_POST);
                $_SESSION['gut'] = 'Angebot gespeichert — je Lieferland gilt das günstigste.';
                zurueck('werbemittel');

            case 'wm_angebot_weg':
                require_once __DIR__ . '/src/Werbemittel.php';
                Werbemittel::angebotLoeschen((int) ($_POST['variante_id'] ?? 0), (string) ($_POST['anbieter'] ?? ''), (string) ($_POST['land'] ?? 'IT'));
                $_SESSION['gut'] = 'Angebot entfernt.';
                zurueck('werbemittel');

            case 'wm_variante':
                require_once __DIR__ . '/src/Werbemittel.php';
                $wmVid = Werbemittel::varianteSpeichern((int) ($_POST['produkt_id'] ?? 0), $_POST, (int) ($_POST['id'] ?? 0));
                if (array_key_exists('gelato_artikel', $_POST)) {
                    require_once __DIR__ . '/src/Gelato.php';
                    Gelato::artikelSetzen($wmVid, (string) $_POST['gelato_artikel'], max(1, (int) ($_POST['gelato_menge'] ?? 1)));
                }
                $_SESSION['gut'] = 'Variante gespeichert.';
                zurueck('werbemittel');

            /* Marketing Center, Phase 3 (03.10.2026): Bestellungen weiterführen.
               Jede Tat prüft den Ausgangsstatus in WmBestellung selbst; ein
               Klick auf eine veraltete Seite ändert dann nichts. */
            case 'wm_zahlweg_stripe':
            case 'wm_zahlweg_anfrage':
                require_once __DIR__ . '/src/WmBestellung.php';
                WmBestellung::zahlwegSetzen($tat === 'wm_zahlweg_stripe' ? 'stripe' : 'anfrage');
                $_SESSION['gut'] = $tat === 'wm_zahlweg_stripe'
                    ? (WmBestellung::zahlweg() === 'stripe' ? 'Stripe ist eingeschaltet: Partner zahlen beim Bestellen direkt.' : 'Eingestellt — aber ohne Stripe-Schlüssel bleibt es bei „Anfrage“.')
                    : 'Zahlweg „Anfrage“: Bestellungen werden gespeichert, du klärst die Zahlung.';
                zurueck('werbemittel');

            case 'wm_automatik_an':
            case 'wm_automatik_aus':
                require_once __DIR__ . '/src/WmBestellung.php';
                WmBestellung::automatikSetzen($tat === 'wm_automatik_an');
                $_SESSION['gut'] = $tat === 'wm_automatik_an' ? 'Automatik an: Nach der Zahlung geht der Auftrag von selbst an die Druckerei.' : 'Automatik aus: Du gibst jeden Auftrag selbst frei.';
                zurueck('werbemittel');

            case 'wm_artikel':
                require_once __DIR__ . '/src/Druckerei.php';
                Druckerei::artikelSetzen((int) ($_POST['variante_id'] ?? 0), (string) ($_POST['anbieter'] ?? ''), (string) ($_POST['artikel'] ?? ''), max(1, (int) ($_POST['menge'] ?? 1)));
                $_SESSION['gut'] = 'Artikelnummer gespeichert.';
                zurueck('werbemittel');

            case 'wm_gelato_preise':
                // Alle Druckereien mit Preis-Schnittstelle (Gelato, Printful) — je eine Zeile, mit dem Grund, wenn eine absagt.
                require_once __DIR__ . '/src/Druckerei.php';
                require_once __DIR__ . '/src/Werbemittel.php';
                $wmZeilen = []; $wmGut = false;
                foreach (Druckerei::mitPreisen() as $wmK) {
                    $wmN = $wmK::preiseAktualisieren();
                    $wmGut = $wmGut || $wmN > 0;
                    $wmZeilen[] = $wmN > 0 ? $wmK . ': ' . $wmN . ' Preise geholt und eingetragen.' : $wmK . ': keine Preise — ' . ($wmK::$letzterGrund !== '' ? $wmK::$letzterGrund : 'nichts (kein Artikel zugeordnet?)');
                }
                // Dazu die Druckflächen bei Printful (nur lesen) — Grundlage für jede neue Printful-Gestaltung.
                require_once __DIR__ . '/src/Printful.php';
                if (Printful::bereit()) { $wmF = Printful::druckflaechenHolen(); $wmZeilen[] = 'Printful-Druckflächen: ' . $wmF . ' Produkte abgefragt.'; }
                // Und die Gelato-Artikel der neuen Produkte (Poster, nur lesen) — die Nummern kommen so aus Gelato selbst.
                require_once __DIR__ . '/src/Gelato.php';
                if (Gelato::bereit()) { Gelato::$letzterGrund = ''; $wmG = Gelato::artikelSuchen(); $wmZeilen[] = 'Gelato-Artikel: ' . $wmG . ' gefunden' . ($wmG === 0 && Gelato::$letzterGrund !== '' ? ' — ' . Gelato::$letzterGrund : '') . '.'; }
                $_SESSION[$wmGut ? 'gut' : 'fehler'] = $wmZeilen ? implode(' · ', $wmZeilen) : 'Keine Druckerei mit Preis-Schnittstelle angebunden.';
                zurueck('werbemittel');

            case 'wm_probe':
                require_once __DIR__ . '/src/Druckerei.php';
                $wmA = (string) ($_POST['anbieter'] ?? '');
                $wmR = Druckerei::probeSenden($wmA);
                $_SESSION[$wmR['ok'] ? 'gut' : 'fehler'] = $wmR['ok']
                    ? 'Probe-Entwurf bei ' . $wmA . ' angelegt (Nummer ' . ($wmR['id'] ?? '') . ', Bezug ' . ($wmR['ref'] ?? '') . '). Im Dashboard der Druckerei ansehen und wieder löschen — nicht bestätigen.'
                    : 'Probe an ' . $wmA . ' nicht angelegt: ' . $wmR['grund'];
                zurueck('werbemittel');

            case 'wm_gelato_senden':
            case 'wm_gelato_zurueck':
                require_once __DIR__ . '/src/Gelato.php';
                $wmBid = (int) ($_POST['id'] ?? 0);
                if ($tat === 'wm_gelato_senden') {
                    $wmR = Gelato::entwurfSenden($wmBid);
                    if ($wmR['ok']) {
                        Events::protokoll('wm_gelato_entwurf', 'Werbemittel-Bestellung #' . $wmBid . ' als Entwurf an Gelato', null, null, null, ['wm_bestellung' => $wmBid, 'gelato' => $wmR['id'] ?? '']);
                        $_SESSION['gut'] = 'Entwurf bei Gelato angelegt. Jetzt im Gelato-Dashboard prüfen und bestätigen — erst dann wird gedruckt und berechnet.';
                    } else { $_SESSION['fehler'] = $wmR['grund']; }
                } else {
                    if (Gelato::zuruecksetzen($wmBid)) { $_SESSION['gut'] = 'Zurückgesetzt — die Bestellung kann wieder gesendet werden.'; }
                    else { $_SESSION['fehler'] = 'Nichts zurückgesetzt — es steht kein Fehler an.'; }
                }
                zurueck('werbemittel/bestellungen#b' . $wmBid);

            case 'wm_b_zugestellt':
            case 'wm_b_reklamation':
                /* Partner-Shop Phase 6a (05.10.2026): zugestellt von Hand; über eine Reklamation entscheiden
                   (Neudruck, Gutschrift, abgelehnt) — Geld und Druck macht Uwe selbst, hier steht der Entscheid. */
                require_once __DIR__ . '/src/WmBestellung.php';
                $wmBid = (int) ($_POST['id'] ?? 0);
                try {
                    $wmOk = $tat === 'wm_b_zugestellt' ? WmBestellung::zugestellt($wmBid, null, 'verwaltung')
                        : WmBestellung::reklamationEntscheiden($wmBid, (string) ($_POST['entscheid'] ?? ''), (string) ($_POST['antwort'] ?? ''));
                    $_SESSION[$wmOk ? 'gut' : 'fehler'] = $wmOk ? 'Gespeichert.' : 'Nichts geändert — die Bestellung steht schon auf einem anderen Stand.';
                } catch (InvalidArgumentException $e) { $_SESSION['fehler'] = $e->getMessage(); }
                zurueck('werbemittel/bestellungen#b' . $wmBid);

            case 'wm_b_bezahlt':
            case 'wm_b_drucker':
            case 'wm_b_versendet':
            case 'wm_b_storno':
                require_once __DIR__ . '/src/WmBestellung.php';
                $wmBid = (int) ($_POST['id'] ?? 0);
                $wmOk = match ($tat) {
                    'wm_b_bezahlt'   => WmBestellung::vonHandBezahlt($wmBid, (string) ($_POST['wie'] ?? 'ueberweisung')),
                    'wm_b_drucker'   => WmBestellung::beimDrucker($wmBid, (string) ($_POST['anbieter'] ?? ''), (string) ($_POST['ref'] ?? '')),
                    'wm_b_versendet' => WmBestellung::versendet($wmBid, (string) ($_POST['tracking'] ?? ''), (string) ($_POST['url'] ?? '')),
                    default          => WmBestellung::stornieren($wmBid),
                };
                if ($wmOk) {
                    Events::protokoll($tat, 'Werbemittel-Bestellung #' . $wmBid . ': ' . substr($tat, 5), null, null, null, ['wm_bestellung' => $wmBid]);
                    $_SESSION['gut'] = 'Gespeichert.';
                } else {
                    $_SESSION['fehler'] = 'Nichts geändert — die Bestellung steht schon auf einem anderen Stand. Seite neu laden.';
                }
                zurueck('werbemittel/bestellungen#b' . $wmBid);

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

            case 'hosting_vorschlag':
                /* Uwe prueft die Wunschdomain und bietet sie dem Kunden an.
                   Nur eine FREIE Domain wird angeboten — ein Angebot, das
                   sich hinterher als vergeben herausstellt, ist ein
                   gebrochenes Versprechen mit Vertrag dran. Die Mail an den
                   Kunden geht sofort raus; zustimmen muss er selbst. */
                require_once __DIR__ . '/src/Hosting.php';
                require_once __DIR__ . '/src/Domainpruefung.php';
                $kid = (int) ($_POST['id'] ?? 0);
                if ($kid <= 0) { throw new RuntimeException('Kein Kunde angegeben.'); }
                /* Ein Angebot, auf das der Kunde noch nicht geantwortet hat,
                   darf geaendert werden (26.09.2026: vorher brach jeder zweite
                   Versuch mit "hat schon einen Hosting-Vorgang" ab, und der
                   Kunde sah nichts Neues). Zugestimmtes bleibt unberuehrt. */
                $hAlt = Hosting::fuerKunde($kid);
                if ($hAlt && (string) $hAlt['status'] !== 'vorgeschlagen') {
                    throw new RuntimeException('Dieser Kunde hat schon zugestimmt (' . $hAlt['domain'] . ') — ein neues Angebot ersetzt keine Vereinbarung.');
                }
                $hd = Domainpruefung::normalisieren((string) ($_POST['domain'] ?? ''));
                if ($hd === null) { throw new RuntimeException('Das ist keine gültige Domain.'); }
                $hp = Domainpruefung::pruefen($hd);
                $hSelbst = !empty($_POST['selbst_geprueft']);
                /* "unklar" ist bei .it die Regel, wenn der Server kein WHOIS
                   (Port 43) nach aussen darf -- .it hat keinen RDAP-Dienst
                   (rdap.nic.it gibt es nicht, gemessen 26.09.). Dann zaehlt
                   Uwes eigene Pruefung, ausdruecklich angehakt. "vergeben"
                   wird nie angeboten. */
                if ((string) $hp['stand'] === 'vergeben') {
                    throw new RuntimeException('Die Domain ' . $hd . ' ist vergeben — angeboten wird nur, was frei ist.');
                }
                if ((string) $hp['stand'] !== 'frei' && !$hSelbst) {
                    throw new RuntimeException('Die Domain ' . $hd . ' ließ sich nicht automatisch als frei bestätigen (Stand: '
                        . Domainpruefung::wort((string) $hp['stand'], 'de') . '). Selbst prüfen (Domainbestellsystem oder web-whois.nic.it) und „Selbst geprüft“ anhaken.');
                }
                if ($hAlt) {
                    Db::run('UPDATE hosting_auftraege SET domain = ?, domain_aktion = ? WHERE id = ?', [$hd, 'neu', (int) $hAlt['id']]);
                    $hid = (int) $hAlt['id'];
                } else {
                    $hid = Db::insert('hosting_auftraege', Hosting::vorgabeFelder() + [
                        'customer_id' => $kid, 'project_id' => null, 'domain' => $hd,
                        'status' => 'vorgeschlagen', 'preis_cents' => Hosting::preisCents(),
                    ]);
                }
                Events::protokoll('hosting_vorschlag', 'Wunschdomain vorgeschlagen: ' . $hd
                    . ((string) $hp['stand'] !== 'frei' ? ' (Verfügbarkeit von Hand geprüft)' : '')
                    . ($hAlt ? ' — ersetzt ' . $hAlt['domain'] : ''), $kid);
                $hMail = false;
                try {
                    require_once __DIR__ . '/src/Mail.php';
                    require_once __DIR__ . '/src/Texte.php';
                    require_once __DIR__ . '/src/Kundenzugang.php';
                    $hk = Db::one('SELECT * FROM customers WHERE id = ?', [$kid]);
                    $hs = in_array((string) ($hk['sprache'] ?? ''), ['it', 'de', 'en'], true)
                        ? (string) $hk['sprache'] : 'it';
                    if ($hk && trim((string) $hk['email']) !== '') {
                        [$hb, $ht] = Texte::mail('hosting_angebot', $hs, [
                            'name'   => (string) $hk['name'],
                            'domain' => $hd,
                            'link'   => Kundenzugang::linkFuer($kid),
                        ]);
                        $hMail = Mail::senden('hosting_angebot', (string) $hk['email'], $hb, $ht,
                            ['customer_id' => $kid, 'antwortAn' => Mail::eigeneAdresse()]);
                    }
                } catch (Throwable $e) { $hMail = false; }
                $_SESSION['gut'] = 'Die Domain ' . $hd . ' ist dem Kunden angeboten ('
                    . Fmt::geld(Hosting::preisCents()) . ' im Monat). '
                    . ($hMail ? 'Die Angebots-Mail ist raus — entscheiden tut er auf seiner Seite.'
                              : 'Die Mail ging nicht raus — schick ihm seinen Zugangslink von Hand.');
                zurueck('kunden/' . $kid);

            case 'hosting_anlegen':
                /* Der Handgriff neben der Automatik: anlegen, ohne auf
                   Zahlung oder finale Freigabe zu warten. Der Riegel "nur
                   aus zugestimmt" sitzt im Werkzeug und bleibt. */
                require_once __DIR__ . '/src/Hosting.php';
                $hid = (int) ($_POST['id'] ?? 0);
                $he = Hosting::anlegen($hid);
                if ($he['ok']) { $_SESSION['gut'] = 'Angelegt: ' . $he['text']; }
                else { $_SESSION['fehler'] = 'Nicht angelegt: ' . $he['text']; }
                zurueck((string) ($_POST['zurueck'] ?? ''));

            case 'cron_kas_anlegen':
                /* Den Cronjob der Verwaltung selbst im KAS eintragen -- statt
                   ihn abzutippen. Gibt es ihn schon, passiert nichts. */
                require_once __DIR__ . '/src/Kas.php';
                require_once __DIR__ . '/src/Cron.php';
                $cz = Kas::cronjobs();
                if (!$cz['ok']) { $_SESSION['fehler'] = 'KAS nicht erreichbar: ' . $cz['text']; zurueck('einstellungen?b=ueberwachung'); }
                $schon = array_filter($cz['urls'], static fn($u) => str_contains((string) $u, 'cron.php') && str_contains((string) $u, Cron::schluessel()));
                if ($schon) { $_SESSION['gut'] = 'Der Cronjob steht schon im KAS.'; zurueck('einstellungen?b=ueberwachung'); }
                $ce = Kas::cronjobAnlegen(Cron::adresse());
                $_SESSION[$ce['ok'] ? 'gut' : 'fehler'] = $ce['text'];
                zurueck('einstellungen?b=ueberwachung');

            case 'altseite_sichern':
                /* Phase 6a: nur lesen, was oeffentlich ist -- gesammelt wird im
                   Cron, das Ergebnis liegt als ZIP in der Ablage. */
                require_once __DIR__ . '/src/Altseite.php';
                Altseite::anlegen((int) ($_POST['id'] ?? 0), (string) ($_POST['adresse'] ?? ''));
                $_SESSION['gut'] = 'Die alte Seite wird gesichert. Das dauert ein paar Cronläufe; danach liegt eine ZIP-Datei in der Ablage.';
                zurueck((string) ($_POST['zurueck'] ?? ''));

            case 'mailumzug_anfragen':
            case 'mailumzug_jetzt':
            case 'mailumzug_abbrechen':
                /* Phase 6b: den E-Mail-Umzug anfragen, einen Lauf sofort
                   anstossen (statt auf den Cron zu warten) oder abbrechen. */
                require_once __DIR__ . '/src/Mailumzug.php';
                $mid = (int) ($_POST['id'] ?? 0);
                if ($tat === 'mailumzug_anfragen') {
                    $neu = Mailumzug::anfragen($mid, (string) ($_POST['adresse'] ?? ''), (string) ($_POST['ziel'] ?? ''));
                    require_once __DIR__ . '/src/Mail.php';
                    require_once __DIR__ . '/src/Texte.php';
                    require_once __DIR__ . '/src/Kundenzugang.php';
                    $mk = Db::one('SELECT * FROM customers WHERE id = ?', [$mid]);
                    $mu = Db::one('SELECT * FROM mailumzuege WHERE id = ?', [$neu]);
                    $sp = in_array((string) ($mk['sprache'] ?? ''), ['it', 'de', 'en'], true) ? (string) $mk['sprache'] : 'it';
                    [$mb, $mt] = Texte::mail('mailumzug_anfrage', $sp, ['name' => (string) ($mk['name'] ?? ''),
                        'alt' => (string) $mu['adresse'], 'neu' => (string) $mu['ziel_adresse'], 'seite' => Kundenzugang::linkFuer($mid)]);
                    $ok = $mk && Mail::senden('mailumzug_anfrage', (string) $mk['email'], $mb, $mt,
                        ['customer_id' => $mid, 'antwortAn' => Mail::eigeneAdresse()]);
                    $_SESSION[$ok ? 'gut' : 'fehler'] = $ok ? 'Angefragt — der Kunde stimmt auf seiner Seite zu und gibt die Passwörter dort ein.'
                        : 'Angefragt, aber die Mail ging nicht raus — schick ihm seinen Link von Hand.';
                } elseif ($tat === 'mailumzug_jetzt') {
                    $_SESSION['gut'] = 'Lauf: ' . Mailumzug::weiter($mid) . '.';
                } else {
                    Mailumzug::abbrechen($mid);
                    $_SESSION['gut'] = 'Angehalten. Die Passwörter sind gelöscht.';
                }
                zurueck((string) ($_POST['zurueck'] ?? ''));

            case 'seitenumzug_anfragen':
            case 'seitenumzug_zeigen':
            case 'seitenumzug_testen':
            case 'seitenumzug_schritt':
            case 'seitenumzug_fertig':
            case 'seitenumzug_abbrechen':
                /* Phase 6c: den 1:1-Umzug begleiten. Kopieren tust du; hier
                   stehen Zustimmung, Zugang, Test und Checkliste. */
                require_once __DIR__ . '/src/Seitenumzug.php';
                $sid = (int) ($_POST['id'] ?? 0);
                if ($tat === 'seitenumzug_anfragen') {
                    $neu = Seitenumzug::anfragen($sid, (string) ($_POST['adresse'] ?? ''));
                    require_once __DIR__ . '/src/Mail.php';
                    require_once __DIR__ . '/src/Texte.php';
                    require_once __DIR__ . '/src/Kundenzugang.php';
                    $sk = Db::one('SELECT * FROM customers WHERE id = ?', [$sid]);
                    $sp = in_array((string) ($sk['sprache'] ?? ''), ['it', 'de', 'en'], true) ? (string) $sk['sprache'] : 'it';
                    [$sb, $st] = Texte::mail('seitenumzug_anfrage', $sp, ['name' => (string) ($sk['name'] ?? ''),
                        'adresse' => (string) Db::wert('SELECT adresse FROM seitenumzuege WHERE id = ?', [$neu], ''),
                        'seite' => Kundenzugang::linkFuer($sid)]);
                    $ok = $sk && Mail::senden('seitenumzug_anfrage', (string) $sk['email'], $sb, $st,
                        ['customer_id' => $sid, 'antwortAn' => Mail::eigeneAdresse()]);
                    $_SESSION[$ok ? 'gut' : 'fehler'] = $ok ? 'Angefragt — der Kunde hat die Mail und stimmt auf seiner Seite zu.'
                        : 'Angefragt, aber die Mail ging nicht raus — schick ihm seinen Link von Hand.';
                } elseif ($tat === 'seitenumzug_zeigen') {
                    $z = Seitenumzug::zugangLesen($sid);
                    if ($z !== null) { $_SESSION['seitenumzug_zugang'][$sid] = $z; } else { $_SESSION['fehler'] = 'Kein Zugang hinterlegt.'; }
                } elseif ($tat === 'seitenumzug_testen') {
                    $r = Seitenumzug::testen($sid);
                    $_SESSION[$r && $r['ok'] ? 'gut' : 'fehler'] = $r ? (string) $r['text'] : 'Kein Zugang hinterlegt.';
                } elseif ($tat === 'seitenumzug_schritt') {
                    if (!Seitenumzug::schritt($sid, (string) ($_POST['schritt'] ?? ''), ($_POST['wert'] ?? '') === '1')) {
                        $_SESSION['fehler'] = 'Geht nur in Reihenfolge — die Sicherung zuerst.';
                    }
                } else {
                    $ok = Seitenumzug::beenden($sid, $tat === 'seitenumzug_fertig');
                    $_SESSION[$ok ? 'gut' : 'fehler'] = $ok ? 'Erledigt. Der Zugang zum alten Webspace ist gelöscht.'
                        : 'Abschließen geht erst, wenn alle Schritte abgehakt sind.';
                }
                zurueck((string) ($_POST['zurueck'] ?? ''));

            /* AI Office Stufe 5 (07.10.2026): Migration Center, DNS-Schutz, Exit-Paket, Domain-Bestellcheckliste.
               Alles nur Admin (keine Tat steht in Rechte::TATEN_MITARBEIT). Raus geht nur der Exit-Link,
               und der nur mit Uwes Ja (AI Freigaben oder Rückfrage RAUS). */
            case 'migration_anlegen':
            case 'migration_preflight':
            case 'migration_stand':
                require_once __DIR__ . '/src/MigrationCenter.php';
                $mcWer = Auth::name() !== '' ? Auth::name() : 'Verwaltung';
                $mcId = (int) ($_POST['id'] ?? 0);
                try {
                    if ($tat === 'migration_anlegen') {
                        $mcId = MigrationCenter::anlegen((int) ($_POST['kunde'] ?? 0), (string) ($_POST['domain'] ?? ''), $mcWer, (string) ($_POST['notiz'] ?? ''));
                        $_SESSION['gut'] = 'Umzug angelegt. Als Nächstes: Pre-Flight.';
                    } elseif ($tat === 'migration_preflight') {
                        $mcB = MigrationCenter::preflight($mcId, $mcWer);
                        $_SESSION[$mcB['ampel'] === 'rot' ? 'fehler' : 'gut'] = $mcB['ampel'] === 'rot'
                            ? 'Pre-Flight: Es gibt Blocker — siehe Bericht.' : 'Pre-Flight ' . ($mcB['ampel'] === 'ok' ? 'ohne Befund' : 'mit Hinweisen') . ' — der Umzug ist bereit.';
                    } else {
                        $mcOk = MigrationCenter::stand($mcId, (string) ($_POST['nach'] ?? ''), (string) ($_POST['grund'] ?? ''), $mcWer);
                        $_SESSION[$mcOk ? 'gut' : 'fehler'] = $mcOk ? 'Stand gesetzt.' : 'Dieser Wechsel geht von hier aus nicht.';
                    }
                } catch (InvalidArgumentException $e) {
                    $_SESSION['fehler'] = $e->getMessage();
                }
                zurueck($mcId > 0 ? 'umzuege?id=' . $mcId : 'umzuege');

            case 'dns_festhalten':
            case 'dns_zurueck':
                require_once __DIR__ . '/src/DnsSchutz.php';
                $dsDomain = DnsSchutz::domain((string) ($_POST['domain'] ?? ''));
                try {
                    if ($tat === 'dns_festhalten') {
                        $dsId = DnsSchutz::vonHand($dsDomain);
                        $_SESSION['gut'] = 'DNS-Stand #' . $dsId . ' festgehalten.';
                    } else {
                        $dsR = DnsSchutz::zurueckrollen((int) ($_POST['id'] ?? 0), Auth::name() !== '' ? Auth::name() : 'Verwaltung');
                        $_SESSION[$dsR['ok'] ? 'gut' : 'fehler'] = $dsR['text'];
                    }
                } catch (InvalidArgumentException $e) {
                    $_SESSION['fehler'] = $e->getMessage();
                }
                zurueck('umzuege?dns=' . rawurlencode($dsDomain));

            case 'exit_paket_erstellen':
            case 'exit_link_vorschlagen':
            case 'exit_link_senden':
            case 'exit_link_sperren':
                require_once __DIR__ . '/src/ExitPaket.php';
                $exWer = Auth::name() !== '' ? Auth::name() : 'Verwaltung';
                $exKunde = (int) ($_POST['kunde'] ?? 0);
                if ($tat === 'exit_paket_erstellen') {
                    $exR = ExitPaket::erstellen($exKunde, (array) ($_POST['inhalt'] ?? []), $exWer);
                    if ($exR['ok'] && ($_POST['link'] ?? '') === '1') {
                        $exL = ExitPaket::linkVorschlagen((int) $exR['id'], $exWer);
                        $exR['text'] .= ' ' . $exL['text'];
                    }
                } elseif ($tat === 'exit_link_vorschlagen') {
                    $exR = ExitPaket::linkVorschlagen((int) ($_POST['id'] ?? 0), $exWer);
                } elseif ($tat === 'exit_link_senden') {
                    $exR = ExitPaket::linkSenden((int) ($_POST['id'] ?? 0));
                    if ($exR['ok']) { require_once __DIR__ . '/src/Freigabe.php'; Freigabe::vonHandErledigt('exit_link_senden', ['paket' => (int) ($_POST['id'] ?? 0)]); }
                } else {
                    $exOk = ExitPaket::linkSperren((int) ($_POST['id'] ?? 0));
                    $exR = ['ok' => $exOk, 'text' => $exOk ? 'Der Link ist ab sofort ungültig. Die Datei bleibt an der Kundenakte.' : 'Es gab keinen gültigen Link.'];
                }
                $_SESSION[$exR['ok'] ? 'gut' : 'fehler'] = $exR['text'];
                zurueck($exKunde > 0 ? 'kunden/' . $exKunde . '#exit' : 'umzuege');

            case 'domain_bestellung_freigeben':
            case 'domain_bestellt':
                require_once __DIR__ . '/src/Hosting.php';
                $dbId = (int) ($_POST['id'] ?? 0);
                if ($tat === 'domain_bestellung_freigeben') {
                    $dbR = Hosting::bestellungFreigeben($dbId, Auth::name() !== '' ? Auth::name() : 'Verwaltung');
                    $_SESSION[$dbR['ok'] ? 'gut' : 'fehler'] = $dbR['text'];
                } else {
                    $dbOk = Hosting::bestelltMarkieren($dbId);
                    $_SESSION[$dbOk ? 'gut' : 'fehler'] = $dbOk ? 'Vermerkt. Sobald die Nameserver auf All-Inkl zeigen, geht es von selbst weiter.' : 'Erst die Bestellung freigeben.';
                }
                zurueck((string) ($_POST['zurueck'] ?? ''));

            case 'umzug_code_zeigen':
            case 'umzug_beantragt':
            case 'umzug_pruefen':
                /* Phase 5: Code einmal zeigen (fuers Domainbestellsystem),
                   Antrag vermerken (loescht den Code), neu nachsehen. */
                require_once __DIR__ . '/src/Domainumzug.php';
                $uid = (int) ($_POST['id'] ?? 0);
                if ($tat === 'umzug_code_zeigen') {
                    $code = Domainumzug::codeLesen($uid);
                    if ($code !== null && $code !== '') { $_SESSION['umzug_code'][$uid] = $code; }
                    else { $_SESSION['fehler'] = 'Kein Auth-Code hinterlegt.'; }
                } elseif ($tat === 'umzug_beantragt') {
                    if (Domainumzug::beantragt($uid)) {
                        $_SESSION['gut'] = 'Vermerkt. Der Auth-Code ist gelöscht; der Cron sieht nach, wann die Nameserver umstehen.';
                    } else {
                        $_SESSION['fehler'] = 'Nicht vermerkt — ohne hinterlegten Auth-Code gibt es keinen Antrag.';
                    }
                } else {
                    Domainumzug::nachsehen($uid);
                    $_SESSION['gut'] = 'DNS und Transfersperre neu gelesen.';
                }
                zurueck((string) ($_POST['zurueck'] ?? ''));

            case 'hosting_sperren':
            case 'hosting_entsperren':
                require_once __DIR__ . '/src/Hosting.php';
                $hs = Hosting::zugangSperren((int) ($_POST['id'] ?? 0), $tat === 'hosting_sperren');
                $_SESSION[$hs['ok'] ? 'gut' : 'fehler'] = $hs['text'];
                zurueck((string) ($_POST['zurueck'] ?? ''));

            case 'hosting_speicher_kas':
                require_once __DIR__ . '/src/Hosting.php';
                $hs = Hosting::speicherAufKas((int) ($_POST['id'] ?? 0));
                $_SESSION[$hs['ok'] ? 'gut' : 'fehler'] = $hs['text'];
                zurueck((string) ($_POST['zurueck'] ?? ''));

            case 'hosting_speicher_vereinbaren':
                require_once __DIR__ . '/src/Hosting.php';
                $gbNeu = (float) str_replace(',', '.', (string) ($_POST['gb'] ?? ''));
                $hs = Hosting::speicherAendern((int) ($_POST['id'] ?? 0), (int) round($gbNeu * 1024));
                $_SESSION[$hs['ok'] ? 'gut' : 'fehler'] = $hs['text'];
                zurueck((string) ($_POST['zurueck'] ?? ''));

            case 'hosting_technik':
                require_once __DIR__ . '/src/Hosting.php';
                $hs = Hosting::technikWunsch((int) ($_POST['id'] ?? 0), !empty($_POST['mit_datenbank']), !empty($_POST['mit_ftp']));
                $_SESSION[$hs['ok'] ? 'gut' : 'fehler'] = $hs['text'];
                zurueck((string) ($_POST['zurueck'] ?? ''));

            case 'hosting_technik_zeigen':
                /* Wie die KAS-Passwoerter beim Anlegen: einmal in die Session,
                   die Ansicht zeigt und loescht sie. Nie in die URL. */
                require_once __DIR__ . '/src/Hosting.php';
                $hid = (int) ($_POST['id'] ?? 0);
                $t = Hosting::technikAbrufen($hid);
                if ($t === null) { $_SESSION['fehler'] = 'Keine Zugangsdaten für Datenbank oder FTP abgelegt.'; }
                else { $_SESSION['hosting_technik'] = ['id' => $hid, 'daten' => $t]; }
                zurueck((string) ($_POST['zurueck'] ?? ''));

            case 'veroeffentlichen':
                /* Die fertige Seite per FTPS auf die Kundendomain -- nur auf
                   diesen Klick, mit Sicherung vorher (Veroeffentlichung). */
                require_once __DIR__ . '/src/Veroeffentlichung.php';
                $vpid = (int) ($_POST['id'] ?? 0);
                $veV = (int) ($_POST['version'] ?? 0);   // AutoBuild Phase 6: eine bestimmte Fassung (auch Zurückrollen)
                $ve = sicher(static fn() => Veroeffentlichung::veroeffentlichen($vpid, null, null, $veV > 0 ? $veV : null), ['ok' => false, 'text' => 'Unerwarteter Fehler beim Veröffentlichen.']);
                $_SESSION[$ve['ok'] ? 'gut' : 'fehler'] = $ve['text'];
                zurueck('projekte/' . $vpid);

            /* AutoBuild Phase 6 (06.10.2026): Testfassung und Prüfung je Fassung. */
            case 'version_netlify':
            case 'version_staging':
            case 'version_geprueft':
            case 'version_review':
            case 'version_vorschau':
                require_once __DIR__ . '/src/Versionen.php';
                $vrV = Versionen::laden((int) ($_POST['version'] ?? 0));
                $vrPid = (int) ($_POST['id'] ?? 0);
                if (!$vrV || (int) $vrV['project_id'] !== $vrPid) { throw new RuntimeException('Fassung gehört nicht zu diesem Projekt.'); }
                if ($tat === 'version_netlify') {
                    $vrE = Versionen::aufNetlify((int) $vrV['id'], Auth::name());
                    $_SESSION[$vrE['ok'] ? 'gut' : 'fehler'] = $vrE['text'];
                } elseif ($tat === 'version_staging') {
                    Versionen::stagingEintragen((int) $vrV['id'], (string) ($_POST['url'] ?? ''), Auth::name());
                    $_SESSION['gut'] = 'Testadresse für V' . (int) $vrV['nummer'] . ' eingetragen — ansehen, dann „geprüft“.';
                } elseif ($tat === 'version_vorschau') {   // Phase 8: geprüfte Fassung wird die Kundenvorschau
                    $vrK = Versionen::alsKundenvorschau((int) $vrV['id'], Auth::name());
                    $_SESSION[$vrK['ok'] ? 'gut' : 'fehler'] = $vrK['text'];
                } elseif ($tat === 'version_review') {   // Phase 7: Review von Hand für eine Fassung
                    $vrR = Versionen::reviewAnstossen((int) $vrV['id'], Auth::name());
                    $_SESSION[is_int($vrR) ? 'gut' : 'fehler'] = is_int($vrR) ? 'Review für V' . (int) $vrV['nummer'] . ' wartet auf deinen PC.' : $vrR;
                } else {
                    Versionen::geprueft((int) $vrV['id'], Auth::name());
                    $_SESSION['gut'] = 'V' . (int) $vrV['nummer'] . ' ist als geprüft markiert und darf live.';
                }
                zurueck('projekte/' . $vrPid . '#versionen');

            case 'domainpruefung_testen':
                /* Misst auf diesem Server, welche Stufe der Domainpruefung
                   durchkommt -- statt zu raten, ob der Hoster Port 43 sperrt. */
                require_once __DIR__ . '/src/Domainpruefung.php';
                $_SESSION['domain_diagnose'] = Domainpruefung::diagnose();
                zurueck('einstellungen?b=ueberwachung');

            case 'hosting_registrierung':
                require_once __DIR__ . '/src/Hosting.php';
                $hr = Hosting::registrierungNachsehen(null, null, null, (int) ($_POST['id'] ?? 0));
                $_SESSION[$hr > 0 ? 'gut' : 'fehler'] = $hr > 0
                    ? 'Die Domain ist registriert und zeigt auf All-Inkl — HTTPS ist geprüft, der Kunde hat Bescheid.'
                    : 'Noch nicht: Die Nameserver zeigen (noch) nicht auf All-Inkl. Nach der Bestellung dauert es meist Minuten bis wenige Stunden.';
                zurueck((string) ($_POST['zurueck'] ?? ''));

            case 'hosting_https':
                require_once __DIR__ . '/src/Hosting.php';
                $hs = Hosting::httpsPruefen((int) ($_POST['id'] ?? 0));
                $_SESSION[$hs['status'] === 'ok' ? 'gut' : 'fehler'] = 'HTTPS: ' . $hs['text'];
                zurueck((string) ($_POST['zurueck'] ?? ''));

            case 'hosting_abgleich':
                /* "Jetzt abgleichen": Speicher und KAS-Grenzen sofort lesen,
                   nicht erst beim naechsten Tageslauf. */
                require_once __DIR__ . '/src/Hosting.php';
                Db::run("DELETE FROM settings WHERE skey = 'kas_speicher_am'");
                $hs = Hosting::speicherPruefen();
                $_SESSION['gut'] = 'Abgeglichen: ' . (int) $hs['gelesen'] . ' Account(s) gelesen'
                    . ($hs['abweichend'] ? ', ' . (int) $hs['abweichend'] . ' mit abweichendem Speicher' : '') . '.';
                zurueck((string) ($_POST['zurueck'] ?? ''));

            case 'kas_reseller_lesen':
                /* Nur lesende Aufrufe; der Stand wird ohne Passwort gemerkt, und
                   offene Angebote folgen der Aufteilung (Hosting::resellerAktualisieren,
                   dieselbe Funktion wie im taeglichen Lauf). */
                require_once __DIR__ . '/src/Kas.php';
                require_once __DIR__ . '/src/Hosting.php';
                $rs = Hosting::resellerAktualisieren();
                Events::protokoll('integration', 'KAS-Reseller ausgelesen' . ($rs['fehler'] ? ' (mit ' . count($rs['fehler']) . ' Fehler)' : ''));
                $_SESSION[$rs['fehler'] ? 'fehler' : 'gut'] = $rs['fehler']
                    ? ($rs['gelesen'] ? 'Teilweise gelesen: ' : 'Nicht übernommen, der letzte Stand bleibt: ') . implode(' · ', $rs['fehler'])
                    : 'Ausgelesen.' . ($rs['angepasst'] ? ' ' . $rs['angepasst'] . ' offene(s) Angebot(e) folgen der neuen Aufteilung.' : '');
                zurueck('einstellungen?b=reseller');

            case 'hosting_bericht_an':
            case 'hosting_bericht_aus':
                Db::run("INSERT INTO settings (skey, svalue) VALUES ('hosting_bericht', ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)",
                    [$tat === 'hosting_bericht_an' ? '1' : '0']);
                Events::protokoll('integration', 'Monatsbericht an Hosting-Kunden ' . ($tat === 'hosting_bericht_an' ? 'eingeschaltet' : 'ausgeschaltet'));
                $_SESSION['gut'] = $tat === 'hosting_bericht_an' ? 'Monatsbericht an.' : 'Monatsbericht aus — es geht keiner mehr raus.';
                zurueck('einstellungen?b=ueberwachung');

            case 'kas_probelauf_an':
            case 'kas_probelauf_aus':
                Db::run("INSERT INTO settings (skey, svalue) VALUES ('kas_probelauf', ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)",
                    [$tat === 'kas_probelauf_an' ? '1' : '0']);
                Events::protokoll('integration', 'KAS-Probelauf ' . ($tat === 'kas_probelauf_an' ? 'eingeschaltet' : 'ausgeschaltet'));
                $_SESSION['gut'] = $tat === 'kas_probelauf_an'
                    ? 'Probelauf an: Beim KAS wird nichts angelegt oder geändert, nur aufgeschrieben, was geschähe.'
                    : 'Probelauf aus: Die Verwaltung legt beim KAS jetzt wirklich an.';
                zurueck('einstellungen?b=ueberwachung');

            case 'hosting_weiter':
                /* Phase 3: gescheiterte oder von Hand markierte Schritte
                   noch einmal -- nur was fehlt, nie ein zweiter Durchlauf. */
                require_once __DIR__ . '/src/Hosting.php';
                $he = Hosting::wiederholen((int) ($_POST['id'] ?? 0));
                if ($he['ok']) { $_SESSION['gut'] = $he['text']; }
                else { $_SESSION['fehler'] = $he['text']; }
                zurueck((string) ($_POST['zurueck'] ?? ''));

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
                zurueck('finanzamt');

            case 'kundenlink_neu':
                // Zieht den alten Zugang zurueck. Gedacht fuer den Fall, dass
                // ein Kunde den Link weitergegeben hat — oder ihn selbst nicht
                // mehr haben soll. Der alte Link zeigt danach nichts mehr.
                require_once __DIR__ . '/src/Kundenzugang.php';
                $kid = (int) ($_POST['id'] ?? 0);
                if ($kid <= 0) { throw new RuntimeException('Kein Kunde angegeben.'); }
                $neuLink = Kundenzugang::link(Kundenzugang::neu($kid));
                /* Wer den Link zurückzieht, meint: Jemand Falsches hatte Zugang.
                   Dann gilt auch die Telegram-Verbindung nicht mehr, die mit
                   diesem Link hergestellt worden sein kann (30.09.2026). */
                $tgGeloest = false;
                try { require_once __DIR__ . '/src/TelegramKunde.php'; $tgGeloest = TelegramKunde::trennen($kid, 'Zugangslink zurückgezogen'); } catch (Throwable $e) { }
                $_SESSION['gut'] = 'Neuer Zugangslink erzeugt. Der alte gilt nicht mehr — '
                    . ($tgGeloest ? 'auch die Telegram-Verbindung ist gelöst. ' : '')
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
                /* Vertragsregeln (Migration 052). Leer heisst Standard, nicht 0 --
                   eine Mindestlaufzeit von null Monaten waere ein anderer Vertrag. */
                foreach (['mindest_monate', 'kuendigung_tage', 'inklusiv_minuten'] as $vr) {
                    if (array_key_exists($vr, $_POST)) {
                        $daten[$vr] = trim((string) $_POST[$vr]) === '' ? null : max(0, (int) $_POST[$vr]);
                    }
                }
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

            case 'zahlung_nachfragen':
                /* BEI STRIPE NACHFRAGEN (22.09.2026)
                   Uwe: "Wenn der Kunde bezahlt hat, wird das in der Verwaltung
                   nicht angezeigt." Der Grund ist immer derselbe: Der Webhook
                   kam nicht an -- falscher Modus, anderes Signaturgeheimnis,
                   Endpunkt nicht eingetragen -- und der naechtliche Abgleich
                   lief noch nicht oder gar nicht. Hier fragt ein Klick
                   nach, statt darauf zu warten. Gebucht wird ueber denselben
                   Weg wie beim Webhook, mit derselben Betragspruefung. */
                require_once __DIR__ . '/src/Zahlung/Anbieter.php';
                require_once __DIR__ . '/src/Zahlung/Stripe.php';
                $nz = Db::one('SELECT * FROM payments WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
                if (!$nz) { throw new RuntimeException('Zahlung nicht gefunden.'); }
                if ((string) $nz['status'] === 'bezahlt') {
                    $_SESSION['gut'] = 'Diese Rate steht schon als bezahlt.';
                    zurueck('bestellungen/' . (int) ($nz['order_id'] ?? 0));
                }
                $nSitzung = trim((string) ($nz['provider_sitzung'] ?? ''));
                if ($nSitzung === '') {
                    throw new RuntimeException('Zu dieser Rate gibt es keine Bezahlseite bei Stripe — '
                        . 'erzeuge zuerst einen Zahlungslink.');
                }
                $nStripe = new StripeAnbieter();
                if (!$nStripe->bereit()) { throw new RuntimeException('Stripe ist nicht eingerichtet.'); }
                $nS = $nStripe->sitzungLesen($nSitzung);
                if (!empty($nS['bezahlt'])) {
                    $nWie = Events::zahlungVonStripe((int) $nz['id'], (string) $nS['referenz'],
                        (int) $nS['betrag'], (string) $nS['waehrung']);
                    $_SESSION['gut'] = $nWie === 'gebucht'
                        ? 'Stripe sagt: bezahlt. Die Rate ist jetzt gebucht.'
                        : ($nWie === 'abweichung'
                            ? 'Stripe hat gezahlt bekommen, aber einen anderen Betrag — steht als Meldung.'
                            : 'Stripe sagt: bezahlt; gebucht war die Rate schon.');
                } elseif (!empty($nS['abgelaufen'])) {
                    $_SESSION['gut'] = 'Die Bezahlseite ist abgelaufen, bezahlt wurde nicht. '
                        . 'Erzeuge einen neuen Zahlungslink.';
                } else {
                    $_SESSION['gut'] = 'Stripe sagt: noch nicht bezahlt.';
                }
                zurueck('bestellungen/' . (int) ($nz['order_id'] ?? 0));

            case 'zahlungslink':
                require_once __DIR__ . '/src/Zahlung/Anbieter.php';
                require_once __DIR__ . '/src/Zahlung/Stripe.php';
                $z = Db::one('SELECT * FROM payments WHERE id = ?', [(int) $_POST['id']]);
                if (!$z) { throw new RuntimeException('Zahlung nicht gefunden.'); }
                if ($z['status'] === 'bezahlt') { throw new RuntimeException('Diese Rate ist bereits bezahlt.'); }
                $b = Db::one('SELECT * FROM orders WHERE id = ?', [(int) $z['order_id']]);
                $k = Db::one('SELECT * FROM customers WHERE id = ?', [(int) $b['customer_id']]);
                // Kein Zahlungslink vor dem grossen Fragebogen (21.09.2026).
                require_once __DIR__ . '/src/Onboarding.php';
                if (Onboarding::brauchtVorPreis((int) $b['id']) && !Onboarding::fertig((int) $b['customer_id'])) {
                    throw new RuntimeException('Erst der große Fragebogen, dann der Zahlungslink. '
                        . 'Solange der Kunde ihn nicht abgeschickt hat, steht der Preis nicht fest.');
                }
                // Und kein Zahlungslink, solange ein Angebot auf die Zusage wartet (22.09.2026).
                require_once __DIR__ . '/src/Angebot.php';
                $wartet = Angebot::wartetAufZusage((int) $b['id']);
                if ($wartet !== null) { throw new RuntimeException(Angebot::warumKeinZahlungslink($wartet)); }
                $stripe = new StripeAnbieter();
                /* Erst die alte Bezahlseite fragen (Prüfung 07.10.2026, Befund 10). */
                require_once __DIR__ . '/src/Bezahllink.php';
                $zlAlt = Bezahllink::alteSitzungPruefen($z, $stripe);
                if ($zlAlt === 'bezahlt') { $_SESSION['gut'] = 'Diese Rate ist schon bezahlt — eben gebucht. Kein neuer Link nötig.'; zurueck('bestellungen/' . (int) $b['id']); }
                if ($zlAlt === 'offen') { $_SESSION['gut'] = 'Die bisherige Bezahlseite gilt noch: ' . (string) $z['link_url']; zurueck('bestellungen/' . (int) $b['id']); }
                $url = $stripe->bezahlseite($z, $b, $k);
                Db::update('payments', (int) $z['id'], [
                    'provider' => 'stripe', 'status' => 'in_bearbeitung',
                    // Die Nummer der Bezahlseite bleibt stehen: Ohne sie kann der
                    // Abgleich spaeter nicht nachfragen, ob bezahlt wurde.
                    'provider_sitzung' => $stripe->letzteSitzung(),
                    'link_url' => $url, 'link_bis' => date('Y-m-d H:i:s', strtotime('+' . Events::LINK_GILT_TAGE . ' days')),
                ]);
                Events::protokoll('zahlungslink', 'Zahlungslink erstellt: ' . ($z['bezeichnung'] ?: 'Zahlung')
                    . ' · ' . Fmt::geld((int) $z['amount_cents']), (int) $b['customer_id'], (int) $b['id']);
                zurueck('bestellungen/' . (int) $b['id']);

            case 'zahlung_fehler':
                Events::zahlungFehlgeschlagen((int) $_POST['id'], trim((string) ($_POST['grund'] ?? '')));
                zurueck('bestellungen/' . (int) $_POST['order_id']);

            /* AutoBuild Phase 4 (06.10.2026): Bausperre und Not-Aus. Ziehen darf jede Mitarbeit, aufheben und von Hand
               freigeben nur ein Admin (Rechte + hier noch einmal). */
            case 'bau_stopp':
            case 'bau_stopp_alle':
            case 'bau_weiter':
            case 'bau_weiter_alle':
            case 'bau_von_hand':
                require_once __DIR__ . '/src/Bausperre.php';
                $bsPid = (int) ($_POST['id'] ?? 0);
                if (in_array($tat, ['bau_weiter', 'bau_weiter_alle', 'bau_von_hand'], true) && !Auth::istAdmin()) {
                    throw new RuntimeException('Aufheben und von Hand freigeben darf nur ein Admin.');
                }
                match ($tat) {
                    'bau_stopp'       => Bausperre::stoppen($bsPid, Auth::name(), (string) ($_POST['grund'] ?? '')),
                    'bau_stopp_alle'  => Bausperre::alleSetzen(true, Auth::name()),
                    'bau_weiter'      => Bausperre::weiter($bsPid, Auth::name()),
                    'bau_weiter_alle' => Bausperre::alleSetzen(false, Auth::name()),
                    default           => Bausperre::vonHandFreigeben($bsPid, Auth::name(), (string) ($_POST['grund'] ?? '')),
                };
                $_SESSION['gut'] = match ($tat) {
                    'bau_stopp'       => 'KI für dieses Projekt gestoppt — keine Änderungen, keine Vorschau, kein Paket, kein Livegang, bis ein Admin sie wieder freigibt.',
                    'bau_stopp_alle'  => 'Alle automatischen Builds sind gestoppt.',
                    'bau_weiter'      => 'Die KI darf an diesem Projekt wieder arbeiten.',
                    'bau_weiter_alle' => 'Automatische Builds sind wieder erlaubt.',
                    default           => 'Bausperre von Hand aufgehoben — steht mit Begründung in der Prüfspur.',
                };
                weiter($bsPid > 0 ? 'projekte/' . $bsPid : 'projekte');

            /* AutoBuild Phase 9 (06.10.2026): Lieferprüfung, Live-Prüfung, Übergabe — nur Admin. */
            case 'lieferung_bestaetigen':
            case 'lieferung_livecheck':
            case 'uebergabe_erstellen':
            case 'uebergabe_frei':
            case 'uebergabe_zu':
                require_once __DIR__ . '/src/Lieferung.php';
                $lgPid = (int) ($_POST['id'] ?? 0);
                if (!Auth::istAdmin()) { throw new RuntimeException('Livegang und Übergabe macht nur ein Admin.'); }
                if ($tat === 'lieferung_bestaetigen') {
                    Lieferung::bestaetigen($lgPid, (int) ($_POST['version'] ?? 0), array_map('strval', (array) ($_POST['haken'] ?? [])), Auth::name(), (string) ($_POST['notiz'] ?? ''));
                    $_SESSION['gut'] = 'Lieferprüfung abgehakt — die Fassung darf jetzt live.';
                } elseif ($tat === 'lieferung_livecheck') {
                    $lgC = Lieferung::liveCheck($lgPid);
                    $_SESSION[$lgC['ok'] ? 'gut' : 'fehler'] = $lgC['text'];
                } elseif ($tat === 'uebergabe_erstellen') {
                    Lieferung::uebergabeErstellen($lgPid, Auth::name());
                    $_SESSION['gut'] = 'Übergabe erstellt — lesen, dann dem Kunden zeigen.';
                } else {
                    Lieferung::uebergabeFreigeben($lgPid, Auth::name(), $tat === 'uebergabe_frei');
                    $_SESSION['gut'] = $tat === 'uebergabe_frei' ? 'Der Kunde sieht die Übergabe ab sofort auf seiner Seite.' : 'Übergabe vor dem Kunden verborgen.';
                }
                weiter('projekte/' . $lgPid . '#livegang');

            /* AutoBuild Phase 8 (06.10.2026): Kundenwünsche — erfassen, einordnen (Scope), umsetzen lassen. */
            case 'wunsch_neu':
            case 'wunsch_einordnen':
            case 'wunsch_umsetzen':
                require_once __DIR__ . '/src/Wunsch.php';
                $wuPid = (int) ($_POST['id'] ?? 0);
                if ($tat === 'wunsch_neu') {
                    Wunsch::erfassen($wuPid, (string) ($_POST['text'] ?? ''), 'vecom');
                    $_SESSION['gut'] = 'Wunsch eingetragen — jetzt einordnen.';
                } elseif ($tat === 'wunsch_einordnen') {
                    $wuW = Wunsch::laden((int) ($_POST['wunsch'] ?? 0));
                    if (!$wuW || (int) $wuW['project_id'] !== $wuPid) { throw new RuntimeException('Wunsch gehört nicht zu diesem Projekt.'); }
                    Wunsch::einordnen((int) $wuW['id'], (string) ($_POST['status'] ?? ''), Auth::name(), (string) ($_POST['grund'] ?? ''),
                        isset($_POST['minuten']) && trim((string) $_POST['minuten']) !== '' ? (int) $_POST['minuten'] : null);
                    $_SESSION['gut'] = 'Eingeordnet: ' . (Wunsch::STATUS[(string) $_POST['status']] ?? '') . '. Der Kunde sieht den neuen Stand auf seiner Seite.';
                } else {
                    $wuE = Wunsch::umsetzen($wuPid, Auth::name());
                    $_SESSION[is_int($wuE) ? 'gut' : 'fehler'] = is_int($wuE) ? 'Die Wünsche gehen an Claude — eine neue Fassung mit Tests und Review folgt.' : $wuE;
                }
                weiter('projekte/' . $wuPid . '#wuensche');

            /* AutoBuild Phase 5 (06.10.2026): Bau-Warteschlange — Analyse und Pflichtenheft über den PC. */
            case 'bau_auftrag':
            case 'bau_auftrag_abbrechen':
            case 'bau_uebernehmen':
                require_once __DIR__ . '/src/BauAuftrag.php';
                $baPid = (int) ($_POST['id'] ?? 0);
                if ($tat === 'bau_auftrag') {
                    $baArt = (string) ($_POST['art'] ?? '');
                    $baErg = in_array($baArt, BauAuftrag::KNOPF, true) ? BauAuftrag::anlegen($baPid, $baArt, Auth::name(), (string) ($_POST['hinweis'] ?? '')) : 'Diese Auftragsart lässt sich hier nicht anstoßen.';
                    if (is_string($baErg)) { $_SESSION['fehler'] = $baErg; zurueck('projekte/' . $baPid . '#bauen'); }
                    $_SESSION['gut'] = BauAuftrag::name((string) $_POST['art']) . ' wartet auf deinen PC — er holt den Auftrag innerhalb von fünf Minuten ab.';
                } elseif ($tat === 'bau_auftrag_abbrechen') {
                    $baF = BauAuftrag::abbrechen((int) ($_POST['auftrag'] ?? 0), Auth::name());
                    if ($baF !== null) { $_SESSION['fehler'] = $baF; zurueck('projekte/' . $baPid . '#bauen'); }
                    $_SESSION['gut'] = 'Auftrag abgebrochen.';
                } else {
                    $baA = BauAuftrag::laden((int) ($_POST['auftrag'] ?? 0));
                    if (!$baA || (int) $baA['project_id'] !== $baPid) { throw new RuntimeException('Auftrag gehört nicht zu diesem Projekt.'); }
                    BauAuftrag::uebernehmen((int) $baA['id'], Auth::name());
                    $_SESSION['gut'] = BauAuftrag::name((string) $baA['art']) . ' übernommen — diese Fassung gilt jetzt für das Projekt.';
                }
                weiter('projekte/' . $baPid . '#bauen');

            case 'projekt_status':
                $pid = (int) $_POST['id'];
                $neuerStand = (string) $_POST['status'];
                /* Keine Seite gilt als veroeffentlicht, bevor HTTPS geprueft
                   ist -- sofern die Domain bei uns liegt (26.09.2026). */
                if ($neuerStand === 'online') {
                    require_once __DIR__ . '/src/Hosting.php';
                    $sperre = sicher(static fn() => Hosting::httpsSperre($pid), null);
                    if ($sperre !== null) { $_SESSION['fehler'] = $sperre; zurueck('projekte/' . $pid); }
                }
                Events::projektStatus($pid, $neuerStand, true, true);   // Uwes eigener Klick: jeder Stand erlaubt
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
                /* Der Vorschau-Link steht NICHT mehr hier drin.
                   Er hat einen eigenen Schalter daneben (vorschau_speichern),
                   weil Eintragen und Freischalten zweierlei sind. Stuende er
                   weiter in diesem Formular, wuerde jedes Speichern der
                   Eckdaten ihn loeschen, sobald das Feld fehlt. */
                $daten = [
                    'deadline' => ($_POST['deadline'] ?? '') !== '' ? (string) $_POST['deadline'] : null,
                    'priority' => (string) ($_POST['priority'] ?? 'normal'),
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
                $pk     = trim((string) ($_POST['oeffentlich'] ?? ''));
                $whcon  = trim((string) ($_POST['webhook_geheim_connect'] ?? ''));   // Connect-Endpunkt (Partnerkonten), wahlweise

                // Leer gelassene Felder behalten ihren bisherigen Wert — so laesst
                // sich der Modus umstellen, ohne die Schluessel neu einzutippen.
                if ($geheim === '') { $geheim = (string) ($bisher['geheim'] ?? ''); }
                if ($whsec === '')  { $whsec  = (string) ($bisher['webhook_geheim'] ?? ''); }
                if ($pk === '')     { $pk     = (string) ($bisher['oeffentlich'] ?? ''); }
                if ($pk === '-')    { $pk     = ''; }
                if ($whcon === '')  { $whcon  = (string) ($bisher['webhook_geheim_connect'] ?? ''); }
                if ($whcon === '-') { $whcon  = ''; }
                if ($whcon !== '' && !str_starts_with($whcon, 'whsec_')) {
                    throw new RuntimeException('Das Connect-Webhook-Geheimnis beginnt mit whsec_.');
                }

                if ($geheim !== '' && !preg_match('~^(sk|rk)_(test|live)_~', $geheim)) {
                    throw new RuntimeException('Das sieht nicht nach einem geheimen Stripe-Schlüssel aus (er beginnt mit sk_test_ oder sk_live_).');
                }
                if ($whsec !== '' && !str_starts_with($whsec, 'whsec_')) {
                    throw new RuntimeException('Das Webhook-Geheimnis beginnt mit whsec_.');
                }
                if ($pk !== '' && !preg_match('~^pk_(test|live)_[A-Za-z0-9]+$~', $pk)) {
                    throw new RuntimeException('Der öffentliche Schlüssel beginnt mit pk_test_ oder pk_live_.');
                }
                if ($pk !== '' && !str_contains($pk, '_' . $modus . '_')) {
                    throw new RuntimeException('Der öffentliche Schlüssel passt nicht zum Modus (' . ($modus === 'live' ? 'Livemodus braucht pk_live_' : 'Testmodus braucht pk_test_') . ').');
                }
                if ($geheim !== '' && $modus === 'live' && str_contains($geheim, '_test_')) {
                    throw new RuntimeException('Livemodus gewählt, aber der Schlüssel ist ein Testschlüssel.');
                }
                if ($geheim !== '' && $modus === 'test' && str_contains($geheim, '_live_')) {
                    throw new RuntimeException('Testmodus gewählt, aber der Schlüssel ist ein Liveschlüssel. Im Testmodus fließt kein echtes Geld — das ist Absicht.');
                }

                $alt['stripe'] = ['modus' => $modus, 'geheim' => $geheim, 'webhook_geheim' => $whsec, 'webhook_geheim_connect' => $whcon, 'oeffentlich' => $pk]
                    + array_diff_key($bisher, array_flip(['modus', 'geheim', 'webhook_geheim', 'webhook_geheim_connect', 'oeffentlich']));
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

            case 'telefon_modus':
                /* Der Urlaubsschalter. Ohne ihn raet das Sprachmodell die
                   Lage -- und zwar ueberzeugend falsch. */
                require_once __DIR__ . '/src/Telefon.php';
                Telefon::modusSetzen((string) ($_POST['modus'] ?? 'normal'));
                weiter($_POST['zurueck'] ?? 'telefon');
                break;

            /* ---------- Chef-Modus: das Codewort ---------- */
            case 'chef_codewort':
                require_once __DIR__ . '/src/Chef.php';
                $wort = trim((string) ($_POST['codewort'] ?? ''));
                // Leer lassen = unverändert, damit ein versehentliches Speichern
                // das gesetzte Wort nicht löscht. Entfernen geht über den eigenen Knopf.
                if ($wort !== '' && ($mangel = Chef::codewortMangel($wort)) !== null) {
                    $_SESSION['fehler'] = $mangel;
                } elseif ($wort !== '') {
                    Chef::codewortSetzen($wort);
                    $_SESSION['gut'] = 'Chef-Modus-Codewort gespeichert. Sag es Manuela im Gespräch, um den Modus zu öffnen.';
                } else {
                    $_SESSION['gut'] = 'Nichts geändert — das Feld war leer.';
                }
                zurueck($_POST['zurueck'] ?? 'einstellungen?b=telefon');
                break;

            case 'chef_pin':
                require_once __DIR__ . '/src/Chef.php';
                $fehler = Chef::pinSetzen((string) ($_POST['pin'] ?? ''));
                if ($fehler !== null) { $_SESSION['fehler'] = $fehler; }
                else { $_SESSION['gut'] = trim((string) ($_POST['pin'] ?? '')) === ''
                    ? 'PIN entfernt — das Codewort allein öffnet den Chef-Modus.'
                    : 'PIN gespeichert. Manuela fragt nach dem Codewort jetzt auch nach der PIN.'; }
                zurueck($_POST['zurueck'] ?? 'einstellungen?b=telefon');
                break;

            case 'chef_sperre_weg':
                require_once __DIR__ . '/src/Chef.php';
                Chef::sperreAufheben();
                Events::protokoll('chef_sperre_weg', 'Sperre des Chef-Modus von Hand aufgehoben');
                $_SESSION['gut'] = 'Sperre aufgehoben.';
                zurueck($_POST['zurueck'] ?? 'einstellungen?b=telefon');
                break;

            case 'chef_freigeben':
                require_once __DIR__ . '/src/Chef.php';
                $r = Chef::freigeben((int) ($_POST['id'] ?? 0));
                $_SESSION[$r['ok'] ? 'gut' : 'fehler'] = $r['text'];
                zurueck($_POST['zurueck'] ?? 'einstellungen?b=telefon');
                break;

            case 'chef_verwerfen':
                require_once __DIR__ . '/src/Chef.php';
                $_SESSION['gut'] = Chef::verwerfen((int) ($_POST['id'] ?? 0)) ? 'Verworfen — nichts geändert.' : 'War schon erledigt.';
                zurueck($_POST['zurueck'] ?? 'einstellungen?b=telefon');
                break;

            case 'chef_codewort_weg':
                require_once __DIR__ . '/src/Chef.php';
                Chef::codewortSetzen('');
                $_SESSION['gut'] = 'Codewort entfernt — der Chef-Modus ist aus.';
                zurueck($_POST['zurueck'] ?? 'einstellungen?b=telefon');
                break;

            /* ---------- Die Merkliste ---------- */
            case 'merkliste_setzen':
                require_once __DIR__ . '/src/Telefon.php';
                $erg = Telefon::merklisteSetzen((string) ($_POST['nummer'] ?? ''),
                                                (string) ($_POST['notiz'] ?? ''));
                $_SESSION[$erg['ok'] ? 'gut' : 'fehler'] = $erg['text'];
                zurueck('einstellungen?b=telefon');
                break;

            case 'merkliste_weg':
                require_once __DIR__ . '/src/Telefon.php';
                $_SESSION['gut'] = Telefon::merklisteWeg((string) ($_POST['ende'] ?? ''))
                    ? 'Wieder normal. Beim naechsten Anruf beraet sie ganz gewoehnlich.'
                    : 'Diese Nummer stand nicht auf der Liste.';
                zurueck('einstellungen?b=telefon');
                break;

            /* ---------- Der Rueckweg von STRATO ---------- */
            case 'kas_zugang':
                /* Wie bei Stripe und STRATO: Die Angaben kommen aus dem
                   Browser, landen nur in app/config.local.php (Rechte 600)
                   und nie im Repository — das ist oeffentlich. Leer lassen
                   heisst "unveraendert". */
                require_once __DIR__ . '/src/Kas.php';
                require_once __DIR__ . '/src/Einrichtung.php';
                $alt = is_file(dirname(__DIR__) . '/app/config.local.php')
                     ? (array) (include dirname(__DIR__) . '/app/config.local.php') : [];
                $bisher = (array) ($alt['kas'] ?? []);
                $login  = trim((string) ($_POST['login'] ?? ''));
                $pass   = (string) ($_POST['passwort'] ?? '');
                if ($login === '') { $login = (string) ($bisher['login'] ?? ''); }
                if ($pass === '')  { $pass  = (string) ($bisher['passwort'] ?? ''); }
                if ($login !== '' && !preg_match('/^[a-z][a-z0-9_]{2,30}$/i', $login)) {
                    $_SESSION['fehler'] = 'Das sieht nicht wie ein KAS-Login aus (w… oder Reseller-Login).';
                    zurueck('einstellungen?b=zugaenge');
                }
                $alt['kas'] = ['login' => $login, 'passwort' => $pass];
                if (!Einrichtung::konfigSchreiben(dirname(__DIR__) . '/app/config.local.php', $alt)) {
                    $_SESSION['fehler'] = 'app/config.local.php konnte nicht geschrieben werden.';
                    zurueck('einstellungen?b=zugaenge');
                }
                /* Der Test gehoert zum Speichern: Ein Zugang, der still
                   falsch daliegt, faellt erst auf, wenn man ihn braucht.
                   Mit den frischen Werten — die Konfiguration dieses
                   Aufrufs ist noch die alte. */
                Events::protokoll('integration', 'KAS-Zugangsdaten gespeichert');
                Kas::zugangFrisch($login, $pass);
                $probe = Kas::pruefen();
                $_SESSION[$probe['ok'] ? 'gut' : 'fehler'] = $probe['ok']
                    ? $probe['text']
                    : 'Gespeichert, aber die Prüfung schlug fehl: ' . $probe['text'];
                zurueck('einstellungen?b=zugaenge');
                break;

            case 'kas_domain_zugang':
                /* Phase 7a (05.10.2026, Uwe: „unter dem kas wo auch vecom design läuft“): der Zugang des Kontos von
                   vecom-design.it — nur zum LESEN der @vecom-Adressen. Wie beim Reseller-Zugang: nur in
                   app/config.local.php, nie im Repository. Gleich danach lesen und eindeutig zuordnen. */
                require_once __DIR__ . '/src/Einrichtung.php';
                require_once __DIR__ . '/src/PartnerMail.php';
                $alt = is_file(dirname(__DIR__) . '/app/config.local.php')
                     ? (array) (include dirname(__DIR__) . '/app/config.local.php') : [];
                $bisher = (array) ($alt['kas_domain'] ?? []);
                $login  = trim((string) ($_POST['login'] ?? '')) ?: (string) ($bisher['login'] ?? '');
                $pass   = (string) ($_POST['passwort'] ?? '') ?: (string) ($bisher['passwort'] ?? '');
                if ($login === '' || !preg_match('/^[a-z][a-z0-9_]{2,30}$/i', $login)) {
                    $_SESSION['fehler'] = 'Das sieht nicht wie ein KAS-Login aus (w…).';
                    zurueck('einstellungen?b=zugaenge');
                }
                $alt['kas_domain'] = ['login' => $login, 'passwort' => $pass];
                if (!Einrichtung::konfigSchreiben(dirname(__DIR__) . '/app/config.local.php', $alt)) {
                    $_SESSION['fehler'] = 'app/config.local.php konnte nicht geschrieben werden.';
                    zurueck('einstellungen?b=zugaenge');
                }
                Events::protokoll('integration', 'KAS-Zugang für vecom-design.it (nur lesen) gespeichert');
                PartnerMail::kasDomainFrisch($login, $pass);   // die Konfiguration dieses Aufrufs ist noch die alte
                $kdN = PartnerMail::kasLesen();
                $kdAuto = $kdN > 0 ? PartnerMail::automatischZuordnen() : [];
                $_SESSION[$kdN > 0 ? 'gut' : 'fehler'] = $kdN > 0
                    ? 'Gespeichert. ' . $kdN . ' Adressen @' . PartnerMail::DOMAIN . ' gelesen' . ($kdAuto ? ', ' . count($kdAuto) . ' eindeutig zugeordnet: ' . implode(', ', array_column($kdAuto, 'adresse')) : ', keine eindeutig zuzuordnen — dann in der Partner-Akte auswählen') . '.'
                    : 'Gespeichert, aber keine Adressen gelesen. Stimmen Login und KAS-Passwort (nicht das MembersArea-Passwort)?';
                zurueck('einstellungen?b=zugaenge');
                break;

            case 'kas_account_anlegen':
                /* Legt einen Unter-Account beim Reseller an. Die beiden
                   Passwoerter erzeugt der Server, zeigt sie genau einmal
                   (Session, die Ansicht loescht sie nach dem Anzeigen) und
                   speichert sie nirgends. */
                require_once __DIR__ . '/src/Kas.php';
                $erg = Kas::accountAnlegen((string) ($_POST['kommentar'] ?? ''));
                if ($erg['ok']) {
                    Events::protokoll('kas_account',
                        'KAS-Account angelegt' . ($erg['login'] !== '' ? ': ' . $erg['login'] : '')
                        . ' — ' . mb_substr(trim((string) ($_POST['kommentar'] ?? '')), 0, 80));
                    $_SESSION['kas_neu'] = ['login' => $erg['login'],
                                            'kas' => $erg['kas_passwort'],
                                            'ftp' => $erg['ftp_passwort']];
                    $_SESSION['gut'] = $erg['text'] . ' Die Zugangsdaten stehen unten — sie werden genau einmal angezeigt.';
                } else {
                    $_SESSION['fehler'] = $erg['text'];
                }
                zurueck('einstellungen?b=zugaenge');
                break;

            case 'kas_pruefen':
                require_once __DIR__ . '/src/Kas.php';
                $probe = Kas::pruefen();
                if ($probe['ok'] && $probe['accounts']) {
                    $logins = array_slice(array_column($probe['accounts'], 'login'), 0, 12);
                    $probe['text'] .= ' (' . implode(', ', $logins) . ')';
                }
                $_SESSION[$probe['ok'] ? 'gut' : 'fehler'] = $probe['text'];
                zurueck('einstellungen?b=zugaenge');
                break;

            case 'strato_zugang':
                /* Beide Angaben kommen aus seinem Browser ueber diese
                   Felder -- nie ueber einen Chat, nie ueber eine E-Mail.
                   Leer lassen heisst "unveraendert": So kann er den Token
                   auswechseln, ohne den oeffentlichen Schluessel noch einmal
                   heraussuchen zu muessen. */
                require_once __DIR__ . '/src/Strato.php';
                $anonNeu = trim((string) ($_POST['anon'] ?? ''));
                $refNeu  = trim((string) ($_POST['refresh'] ?? ''));
                if ($anonNeu === '') { $anonNeu = Strato::wert('strato_anon'); }
                if ($refNeu === '')  { $refNeu  = Strato::wert('strato_refresh'); }
                $erg = Strato::zugangSetzen($anonNeu, $refNeu);
                if ($erg['ok']) {
                    $ab = Strato::abgleichen();
                    $_SESSION['gut'] = 'Der Zugang steht. '
                        . (($ab['ok'] ?? false)
                            ? (int) ($ab['gesehen'] ?? 0) . ' Gespräche geholt, davon '
                              . (int) ($ab['neu'] ?? 0) . ' neu.'
                            : 'Die Gespräche kommen mit dem nächsten Lauf.');
                } else {
                    $_SESSION['fehler'] = $erg['text'];
                }
                zurueck('einstellungen?b=telefon');
                break;

            case 'strato_anmeldung':
                /* E-Mail und Passwort nur ueber dieses Feld, nie ueber einen
                   Chat. Das Passwort wird versiegelt und sofort ausprobiert. */
                require_once __DIR__ . '/src/Strato.php';
                $erg = Strato::anmeldungSetzen((string) ($_POST['email'] ?? ''), (string) ($_POST['passwort'] ?? ''));
                $_SESSION[$erg['ok'] ? 'gut' : 'fehler'] = $erg['text'];
                zurueck('einstellungen?b=telefon');
                break;

            case 'strato_anmeldung_weg':
                require_once __DIR__ . '/src/Strato.php';
                Strato::anmeldungLoeschen();
                $_SESSION['gut'] = 'Anmeldung entfernt. Läuft die Sitzung ab, musst du den Token wieder von Hand hinterlegen.';
                zurueck('einstellungen?b=telefon');
                break;

            case 'strato_werkzeuge':
                /* Vierzehn Blöcke von Hand kopieren macht niemand viermal.
                   Geschrieben wird ausschliesslich config.tools -- Stimme,
                   Tempo, Begruessung und der Verhaltenstext drueben bleiben
                   Zeichen fuer Zeichen, wie sie sind. */
                require_once __DIR__ . '/src/Strato.php';
                $erg = Strato::werkzeugeUebertragen();
                $_SESSION[$erg['ok'] ? 'gut' : 'fehler'] = $erg['text'];
                zurueck('einstellungen?b=telefon');
                break;

            case 'strato_verhalten_schreiben':
                /* Wenn die STRATO-Oberfläche den Text nicht speichert (26.09.2026):
                   derselbe Weg wie die Werkzeuge, nur ein Feld, danach nachlesen. */
                require_once __DIR__ . '/src/Strato.php';
                $vFeld = trim((string) ($_POST['feld'] ?? ''));
                $erg = Strato::verhaltenSchreiben($vFeld !== '' ? $vFeld : null);
                $_SESSION[$erg['ok'] ? 'gut' : 'fehler'] = $erg['text'];
                // Die Auswahl gilt nur für den nächsten Seitenaufruf.
                $_SESSION['verhalten_wahl'] = $erg['wahl'] ?? null;
                zurueck('einstellungen?b=telefon#verhalten');

            case 'strato_verhalten':
                /* Nur lesen: steht drüben die Fassung des Verhaltenstexts,
                   die hier gepflegt wird? Geschrieben wird nichts. */
                require_once __DIR__ . '/src/Strato.php';
                $erg = Strato::verhaltenStand();
                $_SESSION[$erg['ok'] && ($erg['stand'] ?? '') === 'aktuell' ? 'gut' : 'fehler'] = $erg['text'];
                zurueck('einstellungen?b=telefon#verhalten');
                break;

            case 'strato_holen':
                require_once __DIR__ . '/src/Strato.php';
                $ab = Strato::abgleichen();
                if ($ab['ok'] ?? false) {
                    $_SESSION['gut'] = (int) ($ab['gesehen'] ?? 0) . ' Gespräche gesehen, '
                        . (int) ($ab['neu'] ?? 0) . ' neu, '
                        . (int) ($ab['geaendert'] ?? 0) . ' aktualisiert.'
                        . (($ab['nachgetragen'] ?? 0) > 0
                            ? ' ' . (int) $ab['nachgetragen'] . ' zugesagte, aber nicht '
                              . 'festgehaltene Punkte stehen jetzt auf „Heute anrufen".' : '');
                } else {
                    $_SESSION['fehler'] = 'Es kam nichts an: ' . (Strato::fehler() ?: 'kein Zugang hinterlegt.');
                }
                zurueck('einstellungen?b=telefon');
                break;

            case 'strato_loeschen':
                /* Die geholten Gespraeche bleiben. Sie gehoeren ihm, nicht
                   dem Zugang -- und ein geloeschter Zugang ist kein Grund,
                   ein halbes Jahr Gespraechsverlauf wegzuwerfen. */
                require_once __DIR__ . '/src/Strato.php';
                Strato::zugangLoeschen();
                $_SESSION['gut'] = 'Der Zugang ist gelöscht. Die schon geholten Gespräche bleiben.';
                zurueck('einstellungen?b=telefon');
                break;

            /* ---------- Löschen: Gespräche und Verlauf ---------- */
            case 'gespraech_loeschen':
            case 'verlauf_loeschen':
                /* DAS BESTÄTIGEN IST DER GANZE PUNKT
                   ---------------------------------------------------------
                   Im ersten Anlauf wird übersprungen, woran noch etwas
                   hängt: ein Rückruf, auf den jemand wartet, eine Frage, die
                   noch offen steht, ein Gespräch, aus dem etwas werden
                   sollte. Was übersprungen wurde, kommt als Frage zurück --
                   mit dem Grund, nicht bloß als Zahl. Erst der zweite Klick
                   nimmt es mit. */
                require_once __DIR__ . '/src/Strato.php';
                require_once __DIR__ . '/src/Telefon.php';

                $auchOffene = (string) ($_POST['auch_offene'] ?? '') === '1';
                $ids = $_POST['ids'] ?? [];
                if (!is_array($ids)) { $ids = [$ids]; }
                $alter = trim((string) ($_POST['aelter_als'] ?? ''));

                if ($tat === 'gespraech_loeschen') {
                    $erg = $alter !== ''
                        ? Strato::loeschenAelterAls((int) $alter, $auchOffene)
                        : Strato::loeschen($ids, $auchOffene);
                    $wort = 'Gespräch';
                } else {
                    $erg = $alter !== ''
                        ? Telefon::verlaufAelterAls((int) $alter, $auchOffene)
                        : Telefon::verlaufLoeschen($ids, $auchOffene);
                    $wort = 'Eintrag';
                }

                $teile = [];
                if ($erg['weg'] > 0) {
                    $teile[] = $erg['weg'] . ' ' . $wort . ($erg['weg'] === 1 ? '' : 'e') . ' gelöscht.';
                    if (($erg['spur'] ?? 0) > 0) {
                        $teile[] = 'Dazu ' . (int) $erg['spur'] . ' Einträge aus dem Verlauf.';
                    }
                }
                if ($erg['offen']) {
                    /* Die Frage wird für die nächste Seite hinterlegt, samt
                       den Kennungen -- sonst müsste man sie noch einmal
                       zusammensuchen und der zweite Klick träfe womöglich
                       etwas anderes als der erste. */
                    $_SESSION['loeschfrage'] = [
                        'tat'   => $tat,
                        'ids'   => array_column($erg['offen'], 'id'),
                        'liste' => $erg['offen'],
                    ];
                    $n = count($erg['offen']);
                    $teile[] = $n === 1
                        ? 'Ein ' . $wort . ' blieb stehen — da hängt noch etwas dran.'
                        : $n . ' ' . $wort . 'e blieben stehen — daran hängt noch etwas.';
                } else {
                    unset($_SESSION['loeschfrage']);
                }
                if (!$teile) { $teile[] = 'Nichts gelöscht.'; }
                $_SESSION[$erg['offen'] ? 'fehler' : 'gut'] = implode(' ', $teile);
                zurueck('telefon');
                break;

            case 'loeschfrage_abbrechen':
                unset($_SESSION['loeschfrage']);
                zurueck('telefon');
                break;

            case 'gespraeche_sperre_loesen':
                /* Der Rückweg. Was hier gelöscht wurde, liegt bei STRATO
                   noch -- wer die Sperre aufhebt, holt es beim nächsten
                   Abgleich zurück. Das gehört sichtbar, sonst wäre die
                   Sperrliste eine Falle statt einer Einstellung. */
                require_once __DIR__ . '/src/Strato.php';
                $n = Strato::sperreLoesen();
                $_SESSION['gut'] = $n . ' Sperre' . ($n === 1 ? '' : 'n') . ' aufgehoben. '
                    . 'Beim nächsten Abgleich kommen die Gespräche zurück, sofern STRATO sie noch hat.';
                zurueck('einstellungen?b=telefon');
                break;

            case 'telefon_luecke_weg':
                require_once __DIR__ . '/src/Telefon.php';
                Telefon::lueckeWeg((string) ($_POST['schluessel'] ?? ''));
                weiter($_POST['zurueck'] ?? 'telefon');
                break;

            case 'telefon_rueckruf_weg':
                /* Abgehakt heisst hier: eine Zeile mehr im Protokoll, keine
                   geaenderte. Wer spaeter wissen will, wie lange jemand
                   gewartet hat, kann es nachlesen. */
                require_once __DIR__ . '/src/Telefon.php';
                Telefon::rueckrufErledigt((int) ($_POST['eintrag'] ?? 0));
                weiter($_POST['zurueck'] ?? 'telefon');
                break;

            case 'telefon_schluessel_neu':
                /* Der alte wird damit wertlos. Genau dafuer ist er da: Ein
                   Schluessel, der im Klartext bei einem fremden Anbieter
                   liegt, muss in zehn Sekunden zu tauschen sein. */
                require_once __DIR__ . '/src/Telefon.php';
                Telefon::neuerSchluessel();
                Events::protokoll('telefon_schluessel', 'Telefon-Schlüssel neu erzeugt');
                weiter($_POST["zurueck"] ?? "telefon");
                break;

            /* Der Schalter fuer die ganze Verwaltung. Steht bewusst nicht
               in einem Unterbereich der Einstellungen: Er betrifft jede
               Seite, also gehoert er dorthin, wo man ihn beim ersten Blick
               auf die Einstellungen sieht. */
            case 'bedienung':
                require_once __DIR__ . '/src/Modus.php';
                $einfachAn = (string) ($_POST['einfach'] ?? '') === 'ja';
                Modus::setzen($einfachAn);
                Events::protokoll('bedienung', $einfachAn
                    ? 'Bedienung auf einfach gestellt'
                    : 'Bedienung auf alles anzeigen gestellt');
                $_SESSION['gut'] = $einfachAn
                    ? 'Einfache Ansicht. Fortgeschrittenes liegt hinter „Mehr" — nichts ist weg.'
                    : 'Volle Ansicht. Alles steht wieder offen da.';
                zurueck('einstellungen');

            case 'migrieren':
                require_once __DIR__ . '/src/Einrichtung.php';
                $neu = Einrichtung::migrieren();
                $ergaenzt = $neu ? Einrichtung::texteNachtragen() : 0;
                Events::protokoll('system_migration', $neu
                    ? 'Datenbank aktualisiert: ' . implode(', ', $neu)
                      . ($ergaenzt ? " · Website-Texte bei $ergaenzt Paket(en) ergänzt" : '')
                    : 'Datenbank war bereits aktuell');
                weiter($_POST['zurueck'] ?? '');

            case 'fragebogen_vorab':
                /* Der grosse Fragebogen VOR dem Preis (21.09.2026). Legt ihn an,
                   falls es ihn noch nicht gibt, und schickt die Einladung --
                   beim zweiten Klick noch einmal. */
                require_once __DIR__ . '/src/Onboarding.php';
                require_once __DIR__ . '/src/Mail.php';
                require_once __DIR__ . '/src/Texte.php';
                $fkid = (int) ($_POST['id'] ?? 0);
                if (Onboarding::fertig($fkid)) {
                    $_SESSION['gut'] = 'Der Fragebogen ist schon ausgefüllt — jetzt ist der Preis dran.';
                    weiter($_POST['zurueck'] ?? ('kunden/' . $fkid));
                }
                $schonRaus = (Onboarding::aktuell($fkid)['eingeladen_am'] ?? null) !== null;
                if (!Onboarding::einladenVorab($fkid, $schonRaus)) {
                    throw new RuntimeException('Die Einladung ging nicht raus. Steht der Brevo-Schlüssel? '
                        . 'Der Fragebogen selbst liegt trotzdem auf der Seite des Kunden.');
                }
                $_SESSION['gut'] = $schonRaus ? 'Erinnerung an den Fragebogen verschickt.' : 'Fragebogen verschickt.';
                weiter($_POST['zurueck'] ?? ('kunden/' . $fkid));

            case 'fragebogen_einladen':
                require_once __DIR__ . '/src/Onboarding.php';
                $pid = (int) $_POST['id'];
                if (!Onboarding::einladen($pid, true)) {
                    throw new RuntimeException('Die Einladung ging nicht raus. Steht der Brevo-Schlüssel? Ist der Fragebogen schon abgeschlossen?');
                }
                $_SESSION['gut'] = 'Fragebogen verschickt.';
                require_once __DIR__ . '/src/Freigabe.php';
                Freigabe::vonHandErledigt('fragebogen_einladen', ['projekt' => $pid]);
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

            /* AutoBuild Phase 10 (07.10.2026): Betrieb, Kostenwächter, Bauregeln. */
            case 'betrieb_pruefen':
            case 'betrieb_grenze':
            case 'betrieb_einstellungen':
            case 'zusatzangebot':
                if (!Auth::istAdmin()) { throw new RuntimeException('Das darf nur ein Admin.'); }
                require_once __DIR__ . '/src/Betrieb.php';
                require_once __DIR__ . '/src/Lieferung.php';
                $btPid = (int) ($_POST['id'] ?? 0);
                if ($tat === 'betrieb_pruefen') {
                    $btL = Lieferung::liveCheck($btPid);
                    $btA = Betrieb::abweichung($btPid);
                    $btS = Betrieb::seitencheck($btPid);
                    $_SESSION[$btL['ok'] && $btA['ok'] && $btS['ok'] ? 'gut' : 'fehler'] = $btL['text'] . ' ' . $btA['text'] . ' Seitenprüfung: ' . ($btS['befunde'] === 0 ? 'alles in Ordnung.' : $btS['befunde'] . ' Befund(e).');
                    zurueck('projekte/' . $btPid . '#betrieb');
                } elseif ($tat === 'betrieb_grenze') {
                    $_SESSION['gut'] = 'Freigegeben: Grenze jetzt ' . Betrieb::grenzeErhoehen($btPid, Auth::name()) . ' Bauläufe.';
                    zurueck('projekte/' . $btPid . '#betrieb');
                } elseif ($tat === 'betrieb_einstellungen') {
                    Betrieb::einstellungenSpeichern((int) round(((float) str_replace(',', '.', trim((string) ($_POST['stundensatz'] ?? '0')))) * 100), (int) ($_POST['grenze'] ?? Betrieb::GRENZE_VORGABE), Auth::name());
                    $_SESSION['gut'] = 'Gespeichert.';
                    zurueck('betrieb');
                }
                require_once __DIR__ . '/src/Wunsch.php';
                $btW = Wunsch::laden((int) ($_POST['wunsch'] ?? 0));
                if (!$btW || (int) $btW['project_id'] !== $btPid) { throw new RuntimeException('Wunsch gehört nicht zu diesem Projekt.'); }
                $btAng = Betrieb::zusatzangebot((int) $btW['id'], (int) ($_POST['minuten'] ?? 60), Auth::name());
                if (!is_int($btAng)) { throw new RuntimeException($btAng); }
                $_SESSION['gut'] = 'Zusatzangebot als Entwurf angelegt — ansehen, anpassen und selbst schicken. Nimmt der Kunde an, den Wunsch auf „Zusatz angenommen“ setzen.';
                zurueck('angebote/' . $btAng);

            case 'bauregel_neu':
            case 'bauregel_aendern':
            case 'bauregel_schalten':
            case 'bauregel_vorschlag':
                if (!Auth::istAdmin()) { throw new RuntimeException('Bauregeln ändert nur ein Admin.'); }
                require_once __DIR__ . '/src/BauRegeln.php';
                if ($tat === 'bauregel_neu') { BauRegeln::hinzufuegen((string) ($_POST['text'] ?? ''), Auth::name()); $_SESSION['gut'] = 'Regel aufgenommen — gilt ab dem nächsten Bauauftrag.'; }
                elseif ($tat === 'bauregel_aendern') { BauRegeln::aendern((int) ($_POST['regel'] ?? 0), (string) ($_POST['text'] ?? ''), Auth::name()); $_SESSION['gut'] = 'Regel geändert (neue Version).'; }
                elseif ($tat === 'bauregel_schalten') { BauRegeln::schalten((int) ($_POST['regel'] ?? 0), ($_POST['an'] ?? '') === '1', Auth::name()); $_SESSION['gut'] = 'Gespeichert (neue Version).'; }
                else {
                    $brJa = ($_POST['entscheidung'] ?? '') === 'ja';
                    BauRegeln::vorschlagEntscheiden((int) ($_POST['vorschlag'] ?? 0), $brJa, Auth::name());
                    $_SESSION['gut'] = $brJa ? 'Vorschlag angenommen — die Regel gilt ab dem nächsten Bauauftrag.' : 'Vorschlag abgelehnt — er kommt nicht wieder.';
                }
                zurueck('bauregeln');

            case 'rechnung_gutschrift':
                /* Gutschrift / Nota di credito (07.10.2026, Vorschlag 11): eine ausgestellte Rechnung
                   wird nie geändert oder gelöscht — korrigiert wird mit einem eigenen Dokument. */
                if (!Auth::istAdmin()) { throw new RuntimeException('Gutschriften stellt nur ein Admin aus.'); }
                require_once __DIR__ . '/src/Rechnung.php';
                /* Prüfung 07.10.2026 (Befund 13): „1.234,56“ richtig lesen; „voller Rest“ nur per Haken — ein Tippfehler
                   ergab früher 0 und damit eine Gutschrift über den ganzen Rest. */
                require_once __DIR__ . '/src/Ausgabe.php';
                if (!empty($_POST['alles'])) { $gsCent = 0; }
                else {
                    $gsCent = Ausgabe::cents((string) ($_POST['betrag'] ?? ''));
                    if ($gsCent <= 0) { throw new RuntimeException('Bitte einen Betrag eintragen (z. B. 150,00) — oder „den ganzen offenen Betrag“ anhaken.'); }
                }
                $gsId = Rechnung::gutschrift((int) $_POST['id'], $gsCent, (string) ($_POST['grund'] ?? ''), (Auth::name() ?: 'admin'));
                $_SESSION['gut'] = 'Gutschrift erstellt. Sie geht erst an den Kunden, wenn Sie sie schicken.';
                zurueck('rechnungen/' . $gsId);

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

            /* Telegram (30.09.2026). Der Token landet verschluesselt in settings;
               im Protokoll steht nur, DASS etwas geaendert wurde. */
            case 'telegram_speichern':
                require_once __DIR__ . '/src/Telegram.php';
                $e = Telegram::tokenSpeichern((string) ($_POST['token'] ?? ''));
                if ($e['ok']) { Events::protokoll('telegram', 'Telegram: Bot-Token hinterlegt'); }
                $_SESSION[$e['ok'] ? 'gut' : 'fehler'] = $e['text'];
                weiter('einstellungen?b=telegram');

            case 'telegram_anmelden':
                require_once __DIR__ . '/src/Telegram.php';
                $e = Telegram::anmelden();
                if ($e['ok']) { Events::protokoll('telegram', 'Telegram: Webhook angemeldet'); }
                $_SESSION[$e['ok'] ? 'gut' : 'fehler'] = $e['text'];
                weiter('einstellungen?b=telegram');

            case 'telegram_pruefen':
                require_once __DIR__ . '/src/Telegram.php';
                $_SESSION['telegram_pruefung'] = Telegram::pruefen();
                weiter('einstellungen?b=telegram');

            case 'telegram_abmelden':
                require_once __DIR__ . '/src/Telegram.php';
                $e = Telegram::abmelden();
                if ($e['ok']) { Events::protokoll('telegram', 'Telegram: Webhook abgemeldet'); }
                $_SESSION[$e['ok'] ? 'gut' : 'fehler'] = $e['text'];
                weiter('einstellungen?b=telegram');

            /* Stufe 3: dein eigenes Telegram als Fenster zur Verwaltung. */
            case 'telegram_admin_verbinden':
                require_once __DIR__ . '/src/TelegramAdmin.php';
                $tgA = TelegramAdmin::verbindungslink((int) Auth::id());
                if ($tgA === '') {
                    $_SESSION['fehler'] = 'Erst den Bot einrichten (Token + Webhook).';
                    weiter('einstellungen?b=telegram');
                }
                header('Location: ' . $tgA, true, 303);
                exit;

            case 'telegram_admin_trennen':
                require_once __DIR__ . '/src/TelegramAdmin.php';
                TelegramAdmin::trennen((int) Auth::id());
                Events::protokoll('telegram', 'Telegram: Verwaltung vom Chat getrennt');
                $_SESSION['gut'] = 'Dein Telegram ist von der Verwaltung getrennt.';
                weiter('einstellungen?b=telegram');

            /* Der Kanal (30.09.2026): hinterlegen und Beiträge veröffentlichen.
               Das Veröffentlichen fragt vorher nach (Ablauf::TRAGWEITE). */
            case 'telegram_kanal_speichern':
                require_once __DIR__ . '/src/Telegram.php';
                $e = Telegram::kanalSetzen((string) ($_POST['kanal'] ?? ''), (string) ($_POST['kanal_link'] ?? ''));
                if ($e['ok']) { Events::protokoll('telegram', 'Telegram: Kanal hinterlegt oder geändert'); }
                $_SESSION[$e['ok'] ? 'gut' : 'fehler'] = $e['text'];
                weiter('einstellungen?b=telegram');

            /* Kanal statt Bot (01.10.2026): wer in den Bot-Chat darf. */
            case 'telegram_bot_offen':
                require_once __DIR__ . '/src/Telegram.php';
                $tgOffen = !empty($_POST['offen']);
                Telegram::setzen('tg_bot_offen', $tgOffen ? '1' : '0');
                Events::pruefspur('telegram_bot_offen', 'settings', null, [], ['offen' => $tgOffen]);
                $_SESSION['gut'] = $tgOffen ? 'Der Bot-Chat ist wieder für alle offen (Interessenten und verbundene Kunden).'
                    : 'Der Bot-Chat ist nur noch für Sie. Alle anderen bekommen den Weg in den Kanal und ins Vecom-Fenster.';
                weiter('einstellungen?b=telegram');

            case 'telegram_app_speichern':
                require_once __DIR__ . '/src/Telegram.php';
                $tgAppName = trim((string) ($_POST['app'] ?? ''));
                if ($tgAppName !== '' && !preg_match('/^[A-Za-z0-9_]{3,30}$/', $tgAppName)) {
                    $_SESSION['fehler'] = 'Der Kurzname besteht aus 3 bis 30 Buchstaben, Ziffern oder Unterstrichen — wie bei @BotFather.';
                    weiter('einstellungen?b=telegram');
                }
                Telegram::setzen('tg_app_name', $tgAppName);
                Events::protokoll('telegram', 'Telegram: Mini-App ' . ($tgAppName !== '' ? '„' . $tgAppName . '“ eingetragen' : 'ausgetragen'));
                $_SESSION['gut'] = $tgAppName !== '' ? 'Mini-App eingetragen. Jetzt „Menü im Kanal aktualisieren“, damit die Knöpfe sie öffnen.' : 'Mini-App ausgetragen — die Knöpfe führen wieder in den Bot.';
                weiter('einstellungen?b=telegram');

            case 'telegram_kanal_menue':
                require_once __DIR__ . '/src/Telegram.php';
                $e = Telegram::kanalMenue();   // zweisprachig, Fenster in der Sprache des Nutzers (01.10.2026)
                if ($e['ok']) { Events::protokoll('telegram_kanal', 'Menü-Beitrag im Telegram-Kanal veröffentlicht oder aktualisiert'); }
                $_SESSION[$e['ok'] ? 'gut' : 'fehler'] = $e['text'];
                weiter('einstellungen?b=telegram');

            case 'telegram_kanal_posten':
                require_once __DIR__ . '/src/Telegram.php';
                $tgText = (string) ($_POST['text'] ?? '');
                $e = Telegram::kanalPosten($tgText, (string) ($_POST['knopf'] ?? ''));
                if ($e['ok']) {
                    Events::protokoll('telegram_kanal', 'Beitrag im Telegram-Kanal veröffentlicht: ' . mb_substr(preg_replace('/\s+/u', ' ', $tgText), 0, 80));
                } else {
                    $_SESSION['telegram_entwurf'] = mb_substr($tgText, 0, Telegram::KANAL_MAX);
                }
                $_SESSION[$e['ok'] ? 'gut' : 'fehler'] = $e['text'];
                weiter('einstellungen?b=telegram');

            case 'telegram_gruppe_pruefen':   // Kanal-Vorschlag 5 (01.10.2026): Kommentare mit Schutz
                require_once __DIR__ . '/src/TelegramGruppe.php';
                $e = TelegramGruppe::pruefen();
                Events::protokoll('telegram_kanal', 'Kommentare unter dem Kanal geprüft: ' . ($e['ok'] ? 'geschützt' : 'noch nicht bereit'));
                $_SESSION[$e['ok'] ? 'gut' : 'fehler'] = $e['text'];
                weiter('einstellungen?b=telegram#kommentare');

            case 'telegram_umfrage':   // Kanal-Vorschlag 6 (01.10.2026): Umfrage im Kanal (Rückfrage über TRAGWEITE)
                require_once __DIR__ . '/src/TelegramUmfrage.php';
                $tuFrage = (string) ($_POST['frage'] ?? '');
                $tuAntw = array_slice(array_map('strval', (array) ($_POST['antwort'] ?? [])), 0, TelegramUmfrage::ANTWORTEN_MAX);
                $e = TelegramUmfrage::senden($tuFrage, $tuAntw);
                if ($e['ok']) {
                    Events::protokoll('telegram_kanal', 'Umfrage im Telegram-Kanal gesendet: ' . mb_substr(preg_replace('/\s+/u', ' ', $tuFrage), 0, 80));
                } else {
                    $_SESSION['telegram_umfrage_entwurf'] = ['frage' => mb_substr($tuFrage, 0, 400), 'antwort' => array_map(static fn($a) => mb_substr($a, 0, 140), $tuAntw)];
                }
                $_SESSION[$e['ok'] ? 'gut' : 'fehler'] = $e['text'];
                weiter('einstellungen?b=telegram#umfrage');

            case 'telegram_umfrage_ende':
                require_once __DIR__ . '/src/TelegramUmfrage.php';
                $e = TelegramUmfrage::beenden((int) ($_POST['id'] ?? 0));
                if ($e['ok']) { Events::protokoll('telegram_kanal', 'Umfrage im Telegram-Kanal beendet'); }
                $_SESSION[$e['ok'] ? 'gut' : 'fehler'] = $e['text'];
                weiter('einstellungen?b=telegram#umfrage');

            case 'telegram_plan':   // Kanal-Vorschlag 2 (01.10.2026): Redaktionsplan
                require_once __DIR__ . '/src/TelegramKanalPlan.php';
                TelegramKanalPlan::speichern($_POST);
                $tpE = TelegramKanalPlan::einstellung();
                $_SESSION['gut'] = $tpE['an'] ? 'Redaktionsplan an: jeden ' . MkAutopilot::TAGE[$tpE['tag']] . ' ab ' . $tpE['stunde'] . ' Uhr schreibt Claude drei Kanal-Beiträge zur Freigabe.'
                    : 'Redaktionsplan aus.';
                weiter('einstellungen?b=telegram#plan');

            case 'telegram_plan_jetzt':
                require_once __DIR__ . '/src/TelegramKanalPlan.php';
                [$tpR, $tpZ] = TelegramKanalPlan::anstossen();
                $_SESSION[is_int($tpR) ? 'gut' : 'fehler'] = is_int($tpR)
                    ? 'Claude schreibt drei Kanal-Beiträge für „' . $tpZ['titel'] . '“ (Italienisch und Deutsch). Sind sie da, kommen sie zur Freigabe — per Telegram und unter Freigabe.'
                    : (string) $tpR;
                weiter('einstellungen?b=telegram#plan');

            case 'telegram_weg':
                require_once __DIR__ . '/src/Telegram.php';
                Telegram::entfernen();
                Events::protokoll('telegram', 'Telegram: Bot-Token entfernt, Webhook abgemeldet');
                $_SESSION['gut'] = 'Der Bot ist abgemeldet, Token und Prüfwort sind gelöscht.';
                weiter('einstellungen?b=telegram');

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
                $rolle = (string) ($_POST['rolle'] ?? 'mitarbeit');
                if (!isset(Rechte::ROLLEN[$rolle])) { throw new RuntimeException('Rolle unbekannt.'); }
                $uid = Db::insert('users', [
                    'email' => $mail, 'password_hash' => password_hash($pass, PASSWORD_DEFAULT),
                    'name' => $name, 'role' => $rolle, 'active' => 1,
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

            /* Zugang löschen (05.10.2026, Uwe: „Zugänge können auch gelöscht werden in Verwaltung“).
               Nie der eigene, nie der letzte aktive Admin. Eine Telegram-Verbindung dieses Zugangs
               wird vorher getrennt, offene Telegram-Codes verfallen. Die Prüfspur bleibt stehen. */
            case 'zugang_loeschen':
                $uid = (int) ($_POST['id'] ?? 0);
                if ($uid === Auth::id()) { throw new RuntimeException('Den eigenen Zugang kannst du nicht löschen.'); }
                $u = Db::one('SELECT * FROM users WHERE id = ?', [$uid]);
                if (!$u) { throw new RuntimeException('Zugang nicht gefunden.'); }
                if ($u['role'] === 'admin' && (int) $u['active'] === 1
                    && (int) Db::wert("SELECT COUNT(*) FROM users WHERE active = 1 AND role = 'admin'") <= 1) {
                    throw new RuntimeException('Das ist der letzte aktive Admin — der bleibt.');
                }
                try { require_once __DIR__ . '/src/TelegramAdmin.php'; TelegramAdmin::trennen($uid); } catch (Throwable $e) { }
                Db::transaktion(static function () use ($uid): void {
                    Db::run('UPDATE telegram_chats SET admin_verbunden = NULL WHERE admin_verbunden = ?', [$uid]);
                    Db::run('DELETE FROM telegram_codes WHERE user_id = ? AND benutzt_am IS NULL', [$uid]);
                    Db::run('DELETE FROM users WHERE id = ?', [$uid]);
                }, 3);
                Events::pruefspur('loeschen', 'user', $uid, ['name' => $u['name'], 'email' => $u['email'], 'role' => $u['role']], []);
                $_SESSION['gut'] = 'Zugang von ' . $u['name'] . ' gelöscht.';
                zurueck('einstellungen?b=zugaenge');

            /* Rollen (05.10.2026): nur für andere Zugänge — die eigene Rolle ändert ein anderer Admin. */
            case 'zugang_rolle':
                $uid = (int) ($_POST['id'] ?? 0);
                $rolle = (string) ($_POST['rolle'] ?? '');
                if ($uid === Auth::id()) { throw new RuntimeException('Die eigene Rolle ändert ein anderer Admin.'); }
                if (!isset(Rechte::ROLLEN[$rolle])) { throw new RuntimeException('Rolle unbekannt.'); }
                $u = Db::one('SELECT * FROM users WHERE id = ?', [$uid]);
                if (!$u) { throw new RuntimeException('Zugang nicht gefunden.'); }
                if ($rolle !== 'admin' && $u['role'] === 'admin' && (int) $u['active'] === 1
                    && (int) Db::wert("SELECT COUNT(*) FROM users WHERE active = 1 AND role = 'admin'") <= 1) {
                    throw new RuntimeException('Das ist der letzte aktive Admin — der bleibt Admin.');
                }
                Db::update('users', $uid, ['role' => $rolle]);
                Events::pruefspur('rolle', 'user', $uid, ['role' => $u['role']], ['role' => $rolle]);
                $_SESSION['gut'] = 'Rolle von ' . $u['name'] . ' geändert — gilt sofort.';
                zurueck('einstellungen?b=zugaenge');

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
                require_once __DIR__ . '/src/Freigabe.php';
                $zid = (int) $_POST['id'];
                Freigabe::vonHandErledigt('abo_anfordern', ['zahlung' => $zid]);
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
            /* ============================================================
               DIE SPRACHE MIT EINEM KLICK

               Sie steckte bisher in der Kundenakte hinter "Bearbeiten",
               zwischen Adresse und Steuernummer. Wer am Telefon merkt, dass
               ein Kunde Deutsch spricht, soll das dort korrigieren koennen,
               wo es ihm auffaellt -- nicht in einem Formular mit zwoelf
               Feldern, das er danach speichern muss.

               Eine Aenderung von Hand gilt als bestaetigt: Du hast mit dem
               Menschen geredet, das ist eine bessere Auskunft als jede
               Vermutung aus einer Seitenversion.
               ============================================================ */
            /* ============================================================
               WIE HOCH GEBAUT WIRD

               Der Bau-Prompt rechnet die Stufe aus Preis und Branche — das
               trifft es meistens. Dieser Knopf ist fuer die anderen Faelle:
               den Kunden, der mehr will als sein Budget vermuten laesst, und
               den, bei dem Ruhe richtig ist, obwohl viel bezahlt wurde. Leer
               heisst wieder "rechne es aus".
               ============================================================ */
            case 'ambition_setzen':
                $apid = (int) ($_POST['id'] ?? 0);
                $ast  = strtoupper(trim((string) ($_POST['ambition'] ?? '')));
                if ($ast !== '' && !in_array($ast, ['A', 'B', 'C', 'D'], true)) {
                    throw new RuntimeException('Unbekannte Ambitionsstufe.');
                }
                Db::update('projects', $apid, ['ambition' => $ast !== '' ? $ast : null]);
                /* Das Briefing traegt die Stufe im Kopf — steht dort noch die
                   alte, waere die Aenderung folgenlos, und genau das faellt
                   erst auf, wenn die Seite falsch gebaut ist. */
                sicher(static function () use ($apid) {
                    require_once __DIR__ . '/src/Briefing.php';
                    return Briefing::speichern($apid);
                }, null);
                $_SESSION['gut'] = $ast === ''
                    ? 'Die Stufe wird wieder aus Preis und Branche gerechnet. Das Briefing ist neu erzeugt.'
                    : 'Stufe ' . $ast . ' gesetzt, das Briefing ist neu erzeugt.';
                zurueck('werkstatt');

            case 'sprache_setzen':
                require_once __DIR__ . '/src/Onboarding.php';
                $skid = (int) ($_POST['id'] ?? 0);
                $ssp  = strtolower(trim((string) ($_POST['sprache'] ?? '')));
                if (!in_array($ssp, ['it', 'de', 'en'], true)) {
                    throw new RuntimeException('Unbekannte Sprache.');
                }
                Onboarding::spracheMerken($skid, $ssp, true);
                $_SESSION['gut'] = 'Ab jetzt bekommt der Kunde alles auf '
                    . ['it' => 'Italienisch', 'de' => 'Deutsch', 'en' => 'Englisch'][$ssp] . '.';
                zurueck('kunden/' . $skid);

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

            /* ---------- Werkstatt nach aussen ---------- */

            case 'werkstatt_schluessel_neu':
                /* Derselbe Gedanke wie beim Telefonschluessel: Ein Schluessel,
                   der auf einem Rechner in einer Datei liegt, muss in zehn
                   Sekunden zu tauschen sein. Der alte wird damit wertlos. */
                require_once __DIR__ . '/src/Werkstatt.php';
                Werkstatt::neuerSchluessel();
                Events::protokoll('werkstatt_schluessel', 'Werkstatt-Schlüssel neu erzeugt');
                $_SESSION['gut'] = 'Neuer Schlüssel. Der alte gilt ab sofort nicht mehr — '
                                 . 'trag den neuen dort ein, wo du baust.';
                zurueck('standard');

            case 'werkstatt_schluessel_weg':
                require_once __DIR__ . '/src/Werkstatt.php';
                Werkstatt::schluesselEntfernen();
                Events::protokoll('werkstatt_schluessel', 'Werkstatt-Schlüssel entfernt');
                $_SESSION['gut'] = 'Die Tür ist zu. Ohne Schlüssel antwortet werkstatt.php niemandem.';
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
                require_once __DIR__ . '/src/Freigabe.php';
                Freigabe::vonHandErledigt('mahnung_schicken', ['zahlung' => $zid, 'stufe' => $stufe]);
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
                require_once __DIR__ . '/src/Freigabe.php';
                Freigabe::vonHandErledigt('restzahlung_anfordern', ['projekt' => (int) $pr['id']]);
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

            /* ---------- Das Website-Paket -------------------------------
               Die fertige Seite als ZIP: hinterlegen (von Hand oder ueber
               die Werkstatt), freigeben, dem Kunden schicken.

               WARUM DIE E-MAIL EINEN LINK TRAEGT UND NICHT DAS ZIP
               Ein Website-Paket hat schnell dreissig Megabyte. Als Anhang
               kommt es bei den meisten Postfaechern gar nicht erst an —
               Gmail nimmt 25 MB, viele Firmenserver 10 —, und was ankommt,
               landet wegen des ZIP im Spam. Der Link fuehrt auf seine
               Projektseite, die er ohnehin kennt, und bleibt gueltig. */
            case 'paket_hoch':
                require_once __DIR__ . '/src/Ablage.php';
                $pid = (int) $_POST['id'];
                $pr = Db::one('SELECT * FROM projects WHERE id = ?', [$pid]);
                if (!$pr) { throw new RuntimeException('Projekt nicht gefunden.'); }
                $pName = (string) ($_FILES['datei']['name'] ?? '');
                if (!preg_match('~\.zip$~i', $pName)) {
                    throw new RuntimeException('Das Paket muss eine .zip sein — so kann es jeder öffnen.');
                }
                $phId = Ablage::annehmen($_FILES['datei'] ?? [], $pid, (int) $pr['customer_id'], 'admin', 'paket');
                require_once __DIR__ . '/src/Versionen.php';   // AutoBuild Phase 6: nummerierte Fassung
                $phV = Versionen::erfassen($pid, (int) $phId, 'hand', (string) ($_POST['notiz'] ?? ''));
                Events::protokoll('paket_neu', 'Website-Paket V' . (int) Db::wert('SELECT nummer FROM projekt_versionen WHERE id = ?', [$phV], 0) . ' hinterlegt: ' . $pName,
                    (int) $pr['customer_id'], $pr['order_id'] !== null ? (int) $pr['order_id'] : null, $pid);
                $_SESSION['gut'] = ($pr['paket_frei_am'] ?? null) !== null
                    ? 'Paket liegt bereit — der Kunde sieht ab sofort diese Fassung.'
                    : 'Paket liegt bereit. Der Kunde sieht es noch nicht.';
                zurueck('projekte/' . $pid);

            case 'paket_frei':
                $pid = (int) $_POST['id'];
                $pr = Db::one('SELECT * FROM projects WHERE id = ?', [$pid]);
                if (!$pr) { throw new RuntimeException('Projekt nicht gefunden.'); }
                $hatPaket = (int) Db::wert(
                    "SELECT COUNT(*) FROM files WHERE project_id = ? AND rolle = 'paket'", [$pid], 0);
                if ($hatPaket === 0) {
                    throw new RuntimeException('Es liegt noch kein Paket da, das freigegeben werden könnte.');
                }
                Db::update('projects', $pid, ['paket_frei_am' => date('Y-m-d H:i:s')]);
                Events::protokoll('paket_frei', 'Website-Paket für den Kunden freigegeben',
                    (int) $pr['customer_id'], $pr['order_id'] !== null ? (int) $pr['order_id'] : null, $pid);
                $_SESSION['gut'] = 'Freigegeben. Der Kunde findet es auf seiner Projektseite.';
                zurueck('projekte/' . $pid);

            case 'paket_zu':
                $pid = (int) $_POST['id'];
                Db::update('projects', $pid, ['paket_frei_am' => null]);
                Events::protokoll('paket_zu', 'Freigabe des Website-Pakets zurückgenommen', null, null, $pid);
                $_SESSION['gut'] = 'Zurückgenommen. Der Kunde sieht es nicht mehr.';
                zurueck('projekte/' . $pid);

            case 'paket_mail':
                require_once __DIR__ . '/src/Nachricht.php';
                $pid = (int) $_POST['id'];
                $raus = Nachricht::paketFertig($pid);
                $_SESSION[$raus ? 'gut' : 'schlecht'] = $raus
                    ? 'Die E-Mail ist raus — mit dem Link auf seine Projektseite.'
                    : 'Die E-Mail ging nicht raus. Das Paket liegt trotzdem auf seiner Seite.';
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
                /* Zurueck, woher der Klick kam: aus der Kundenakte in die
                   Kundenakte, aus dem Projekt ins Projekt. Eine Datei am
                   Kunden ohne Projekt sprang vorher nach projekte/0 — 404. */
                $zielWeg = trim((string) ($_POST['zurueck'] ?? ''));
                if ($zielWeg !== '') { zurueck($zielWeg); }
                if ($d['project_id'] !== null) { zurueck('projekte/' . (int) $d['project_id']); }
                if ($d['customer_id'] !== null) { zurueck('kunden/' . (int) $d['customer_id']); }
                zurueck('');

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
                // Nur Prüfungen (05.10.2026): kein Geld, keine Mails, keine Posts — siehe Cron::jetztPruefen.
                require_once __DIR__ . '/src/Cron.php';
                $b = Cron::jetztPruefen();
                $_SESSION['gut'] = 'Prüfung erledigt: ' . json_encode($b, JSON_UNESCAPED_UNICODE);
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

            case 'meldungen_alle_weg':
                // Auf Uwes Wunsch (26.09.2026) auch Ungelesenes -- nur mit der Rückfrage im Formular.
                $anzahl = Db::run('DELETE FROM notifications')->rowCount();
                $_SESSION['gut'] = $anzahl . ' Meldung(en) gelöscht. Was davon noch zutrifft, meldet sich frühestens morgen wieder.';
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
        require_once __DIR__ . '/src/Haengt.php';
        require_once __DIR__ . '/src/Mail.php';
        require_once __DIR__ . '/src/Anfrage.php';
        require_once __DIR__ . '/src/Ablauf.php';
        /* Erst wegräumen, was sich erledigt hat -- sonst zählt „Heute“ Meldungen,
           deren Anlass vorbei ist (Meldungen, 26.09.2026). */
        require_once __DIR__ . '/src/Meldungen.php';
        sicher(static fn() => Meldungen::aufraeumen(), 0);
        $arbeit = sicher(static fn() => Vorgang::arbeitsliste(), ['du' => [], 'kunde' => [], 'ruht' => []]);
        /* WAS VON SELBST NACHRUECKT
           ------------------------------------------------------------------
           Der Projektstand ist ein gespeichertes Wort, die Fuehrung rechnet
           aus Tatsachen. Wo das Wort hinterherhinkt, zieht Ablauf es nach --
           aber nur vorwaerts, nur bei den vier Staenden, die nichts aus dem
           Haus lassen, und nur, wenn die Tatsache schon in der Datenbank
           steht. Hier, weil die Liste ohnehin jeden Vorgang geladen hat:
           Es kostet keine einzige zusaetzliche Abfrage, solange sich nichts
           aendert. */
        $nachgezogen = sicher(static function () use ($arbeit) {
            $zahl = 0;
            foreach (array_merge($arbeit['du'], $arbeit['kunde'], $arbeit['ruht']) as $v) {
                if (Ablauf::nachziehen($v) !== null) { $zahl++; }
            }
            return $zahl;
        }, 0);
        $offen  = 0;
        foreach (array_merge($arbeit['du'], $arbeit['kunde'], $arbeit['ruht']) as $v) {
            $offen += (int) $v['offen_cent'];
        }
        ansicht('heute', [
            'liste'     => $arbeit,
            'offenGeld' => $offen,
            'nachgezogen' => $nachgezogen,
            /* Was auf einen zukommt -- nicht, was gewesen ist. Ablaufende
               Angebote, liegengebliebene Fragebogen, fehlendes Material,
               anstehende Restzahlungen. Ist nichts faellig, kommt eine leere
               Liste zurueck und die Seite schweigt. */
            /* EINE LISTE STATT DREIER
               ---------------------------------------------------------
               „Das laeuft nicht", „Demnaechst faellig" und die Faelle, in
               denen einfach nichts passiert, meinten alle dasselbe und
               nannten es verschieden. Der dritte hatte gar keinen Kasten
               und ist der gefaehrlichste: Stille loest nichts aus.

               Die Arbeitsliste wird weitergereicht, weil sie hier schon
               geladen ist — sonst liefe der ganze Durchlauf durch alle
               Vorgaenge ein zweites Mal. Siehe app/src/Haengt.php. */
            'haengt' => sicher(static fn() => Haengt::alles($arbeit), []),
            /* Wie viel insgesamt haengt — auch das, was nicht mehr in die
               Liste passt. Ohne diese Zahl waere die Deckelung eine Luege. */
            'hGesamt' => (int) sicher(static fn() => Haengt::anzahl($arbeit), 0),
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
            require_once __DIR__ . '/src/Ablauf.php';
            $v = sicher(static fn() => Vorgang::laden((string) $unter), null);
            if (!$v) { http_response_code(404); exit('Diesen Vorgang gibt es nicht.'); }
            /* Auch hier, damit der Stand stimmt, wenn jemand direkt auf einen
               Vorgang springt statt ueber die Liste. Aendert sich etwas, wird
               es sofort angezeigt statt erst beim naechsten Aufruf. */
            $zug = sicher(static fn() => Ablauf::nachziehen($v), null);
            if ($zug !== null && isset($v['projekt']['status'])) {
                $v['projekt']['status'] = $zug['nach'];
            }
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
                'zug' => $zug,
                /* DIE LEISTE DER OFFENEN VORGAENGE
                   -------------------------------------------------------
                   Dieselbe Quelle wie "Heute", und mit Absicht: Stuenden
                   hier andere Vorgaenge oder eine andere Reihenfolge als
                   dort, waeren es zwei Meinungen darueber, was dringend ist
                   — und man wuesste nie, welcher man folgen soll. */
                'leiste' => sicher(static fn() => Vorgang::arbeitsliste(),
                    ['du' => [], 'kunde' => [], 'ruht' => []]),

                /* EIN BILDSCHIRM JE KUNDE
                   -------------------------------------------------------
                   Bis hierher gab es vier Seiten zu einem Kunden: Vorgang,
                   Kundenakte, Bestellung, Projekt. Jede fuer sich richtig,
                   zusammen die Frage, auf welcher von vieren man haette
                   sein muessen.

                   Jetzt liegen Kundenakte und Projekt als Schubladen auf
                   dieser Seite — dieselben Bloecke, nicht nachgebaute.
                   Die alten Seiten bleiben unter ihrer Adresse erreichbar,
                   damit kein Verweis ins Leere zeigt.

                   Gebaut wird nur, was es wirklich gibt: ohne Projekt keine
                   Projektschublade. */
                'akte' => $vkid > 0 ? sicher(static function () use ($vkid) {
                    $k = Db::one('SELECT * FROM customers WHERE id = ?', [$vkid]);
                    return $k ? datenKunde($vkid, $k) + ['eingebettet' => true] : null;
                }, null) : null,
                'projektteile' => ($v['projekt_id'] ?? null) !== null
                    ? sicher(static function () use ($v) {
                        $pid = (int) $v['projekt_id'];
                        $pr = Db::one('SELECT p.*, c.name AS kunde, c.email AS kunde_email, c.kundennr, o.order_no
                                         FROM projects p JOIN customers c ON c.id = p.customer_id
                                         LEFT JOIN orders o ON o.id = p.order_id WHERE p.id = ?', [$pid]);
                        return $pr ? teile('projekt', datenProjekt($pid, $pr)) : [];
                    }, [])
                    : [],
                'vorlagen' => $vkid > 0 ? sicher(static fn() => Vorlage::fuer($vkid), []) : [],
                'kennung'  => $vkid > 0 ? sicher(static fn() => Vorlage::kennung($vkid), '') : '',
                // Dieselbe Auswahl wie auf der Anfrageseite und aus demselben
                // Grund: Was hier steht, wird zu einer Website-Bestellung.
                'pakete' => sicher(static fn() => Db::all(
                    "SELECT * FROM packages
                      WHERE active = 1 AND art = 'website' AND price_cents > 0
                      ORDER BY sort, price_cents")),
            ]);
            break;
        }
        /* WARUM HIER AUCH DIE KUNDEN OHNE VORGANG GEZAEHLT WERDEN
           ------------------------------------------------------------------
           Uwe, 14.09.2026: „In der Verwaltung werden die schon hinterlegten
           Kunden nicht mehr angezeigt wie Cavaleri."

           Seit dem Umbau heisst die Tuer im Menue „Kunden" und fuehrt hierher
           — auf die Vorgangsliste. Ein Vorgang entsteht aber aus einer
           Bestellung oder einer Anfrage (Vorgang::alle()). Ein Kunde, der von
           Hand angelegt wurde und weder das eine noch das andere hat, kommt
           darin nicht vor: Er stand hinter einer Tuer mit seinem Namen und
           war trotzdem nicht da. Auffindbar blieb er nur ueber die Suche —
           also nur, wenn man seinen Namen schon kennt.

           Gezaehlt wird deshalb nicht mit einer eigenen Abfrage, sondern
           gegen genau die Liste, die gleich angezeigt wird: alle Kunden minus
           die, die hier vorkommen. Eine zweite Abfrage koennte anders zaehlen
           als die Liste zeigt, und dann stuende auf der Seite eine Zahl, die
           sich nicht nachzaehlen laesst. */
        $vgListe = sicher(static fn() => Vorgang::alle(), []);
        $vgDrin = [];
        foreach ($vgListe as $vgV) {
            $vgK = (int) ($vgV['kunde_id'] ?? 0);
            if ($vgK > 0) { $vgDrin[$vgK] = true; }
        }
        ansicht('vorgaenge', [
            'liste'   => $vgListe,
            'kunden'  => (int) sicher(static fn() => Db::wert('SELECT COUNT(*) FROM customers'), 0),
            'gezeigt' => count($vgDrin),
        ]);
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
        if ($unter === 'dubletten') {   // Phase 9b: Vorschläge, zusammengeführt wird nur per Klick
            require_once __DIR__ . '/src/KundenDubletten.php';
            ansicht('kunden_dubletten', ['paare' => sicher(static fn() => KundenDubletten::vorschlaege(), []), 'admin' => Auth::istAdmin()]);
            break;
        }
        if ($id !== null) {
            $k = Db::one('SELECT * FROM customers WHERE id = ?', [$id]);
            if (!$k) { http_response_code(404); exit('Kunde nicht gefunden.'); }
            if (($teile[2] ?? '') === 'bearbeiten') { ansicht('kunde_form', ['k' => $k]); break; }
            if (($teile[2] ?? '') === 'mail' && ctype_digit((string) ($teile[3] ?? ''))) {
                /* Eine verschickte E-Mail so, wie sie hinausging (07.10.2026). „roh“ ist der Briefbogen
                   selbst — im Rahmen, ohne Skripte (CSP sandbox). */
                require_once __DIR__ . '/src/KundeMails.php';
                $kmE = KundeMails::eine($id, (int) $teile[3]);
                if (!$kmE) { http_response_code(404); exit('E-Mail nicht gefunden.'); }
                if (($teile[4] ?? '') === 'roh') {
                    header('Content-Security-Policy: ' . KundeMails::CSP);
                    header('X-Content-Type-Options: nosniff');
                    header('Content-Type: text/html; charset=utf-8');
                    echo $kmE['html'] !== '' ? $kmE['html']
                        : '<!doctype html><meta charset="utf-8"><pre style="font:15px/1.6 system-ui,sans-serif;white-space:pre-wrap;padding:16px">' . Fmt::h($kmE['text']) . '</pre>';
                    exit;
                }
                ansicht('kunde_mail', ['k' => $k, 'm' => $kmE]);
                break;
            }
            // Wer die Akte oeffnet, hat gelesen. Dasselbe passiert beim
            // Oeffnen eines Projekts — nur gab es fuer die Nachrichten ohne
            // Projekt bisher keine Stelle, an der es passiert waere.
            require_once __DIR__ . '/src/Nachricht.php';
            require_once __DIR__ . '/src/Kunde.php';
            require_once __DIR__ . '/src/Vorlage.php';
            sicher(static fn() => Nachricht::gelesenKunde($id), 0);
            ansicht('kunde', datenKunde($id, $k));
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
        require_once __DIR__ . '/src/Zugang.php';
        ansicht('kunden', ['q' => $q, 'anfragen' => sicher(static fn() => Zugang::offene(30), []), 'liste' => Db::all(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) AS bestellungen,
                    (SELECT COUNT(*) FROM projects p WHERE p.customer_id = c.id) AS projekte,
                    (SELECT pa.name FROM partner_zuordnungen z JOIN partner pa ON pa.id = z.partner_id WHERE z.customer_id = c.id LIMIT 1) AS partner_name,
                    (SELECT z.partner_id FROM partner_zuordnungen z WHERE z.customer_id = c.id LIMIT 1) AS partner_id,
                    (SELECT z.quelle FROM partner_zuordnungen z WHERE z.customer_id = c.id LIMIT 1) AS partner_quelle
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

    case 'academy':
        /* Partner Academy verwalten (Etappe 2, 05.10.2026): nur Zahlen, keine Namen. */
        require_once __DIR__ . '/src/Academy.php';
        /* Ansehen in der Verwaltung: eigenes PDF (auch archiviert) oder eine eingebaute Unterlage mit Standardsatz. */
        /* Muster-Zertifikat (05.10.2026): so sieht es ein Partner nach bestandenem Test. Nummer VA-0000-0000 ist nie gültig. */
        if (isset($_GET['zert_muster'])) {
            require_once __DIR__ . '/src/AcademyPdf.php';
            $acSp = in_array($_GET['sprache'] ?? 'de', Academy::SPRACHEN, true) ? (string) $_GET['sprache'] : 'de';
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="academy-zertifikat-muster.pdf"');
            header('Cache-Control: private, no-store');
            echo AcademyPdf::zertifikat(['name' => 'Maria Musterfrau', 'nummer' => 'VA-0000-0000', 'ergebnis' => 95, 'ausgestellt_am' => date('Y-m-d H:i:s')], $acSp);
            exit;
        }
        if (isset($_GET['pdf']) || isset($_GET['vorschau'])) {
            $acPdf = null;
            if (isset($_GET['pdf'])) {
                $acPdf = Db::wert('SELECT datei FROM academy_dokumente WHERE id = ?', [(int) $_GET['pdf']], null);
            } elseif (isset(Academy::DOKUMENTE[(string) $_GET['vorschau']])) {
                require_once __DIR__ . '/src/AcademyPdf.php';
                $acPdf = AcademyPdf::erzeugen((string) $_GET['vorschau'], in_array($_GET['sprache'] ?? 'de', Academy::SPRACHEN, true) ? (string) $_GET['sprache'] : 'de', []);
            }
            if (!is_string($acPdf)) { http_response_code(404); exit('Nicht gefunden.'); }
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="academy.pdf"');
            header('Cache-Control: private, no-store');
            header('X-Content-Type-Options: nosniff');
            echo $acPdf; exit;
        }
        ansicht('academy', [
            'stat'    => sicher(static fn() => Academy::statistik(30), []),
            'module'  => Academy::inhalte('de')['alle_module'],
            'schalter'=> Academy::schalter(),
            'eigene'  => sicher(static fn() => Db::all('SELECT id, titel, kategorie, sprache, version, dateiname, groesse, archiviert, updated_at FROM academy_dokumente ORDER BY archiviert, updated_at DESC'), []),
            'docs'    => Academy::dokumente('de'),
            'neu'     => Academy::neu(),
            'zertifikate' => sicher(static fn() => Db::all('SELECT z.id, z.nummer, z.name, z.ergebnis, z.ausgestellt_am, z.widerrufen_am FROM academy_zertifikate z ORDER BY z.id DESC LIMIT 100'), []),
            'versuche' => sicher(static fn() => Db::one('SELECT COUNT(*) AS n, SUM(bestanden) AS b FROM academy_abschluss'), []),
            'medien'  => sicher(static fn() => Db::all('SELECT id, modul, lektion, sprache, art, titel, groesse, created_at FROM academy_medien ORDER BY modul, lektion, id'), []),
            'sim'     => (static function () { require_once __DIR__ . '/src/AcademySimulator.php'; return AcademySimulator::stand(); })(),
        ]);
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

    /* TÜR „PARTNER“ (Phase 9, 06.10.2026, Uwe: „Eigene Tür Partner“): eigene Reiter für Auszahlungen,
       Support und Meldungen — vorher Unterseiten und Kästen der Partnerliste. */
    case 'auszahlungen':   // Auszahlungslauf mit Sammelfreigabe (Phase 5) — nur Admin (Rechte::SEITEN)
        require_once __DIR__ . '/src/Partner.php';
        require_once __DIR__ . '/src/PartnerGeld.php';
        ansicht('partner_auszahlungslauf', ['lauf' => PartnerGeld::lauf(), 'ergebnis' => $_SESSION['lauf_ergebnis'] ?? null,
            'unterwegs' => sicher(static fn() => Db::all("SELECT a.*, p.name FROM partner_auszahlungen a JOIN partner p ON p.id = a.partner_id
                                                           WHERE a.status = 'offen' ORDER BY a.id DESC LIMIT 50"), [])]);
        unset($_SESSION['lauf_ergebnis']);
        break;

    case 'umzuege':
        /* Migration Center (AI Office Stufe 5, 07.10.2026): alle Umzüge als Vorgang, Pre-Flight, DNS-Stände. */
        require_once __DIR__ . '/src/MigrationCenter.php';
        sicher(static fn() => MigrationCenter::abgleich(), null);
        $mcDns = DnsSchutz::domain((string) ($_GET['dns'] ?? ''));
        ansicht('umzuege', [
            'liste' => sicher(static fn() => MigrationCenter::liste(), []),
            'vorgang' => isset($_GET['id']) ? sicher(static fn() => MigrationCenter::vorgang((int) $_GET['id']), null) : null,
            'dnsDomain' => $mcDns,
            'dnsStaende' => $mcDns !== '' ? sicher(static fn() => DnsSchutz::fuerDomain($mcDns, 30), []) : [],
            'dnsUebersicht' => sicher(static fn() => DnsSchutz::uebersicht(), []),
            'kundenWahl' => sicher(static fn() => Db::all("SELECT id, name, company FROM customers WHERE demo = 0 AND anonym_am IS NULL ORDER BY COALESCE(NULLIF(company, ''), name) LIMIT 800"), []),
        ]);
        break;

    case 'umsatz-chancen':
        /* Der Umsatz-Spürhund (AI Office Stufe 3, V5, 07.10.2026): was er gefunden hat, mit Grund und Richtwert. */
        require_once __DIR__ . '/src/Spuerhund.php';
        // Beim Öffnen frisch nachsehen — sonst stünde ein heute angenommenes Angebot bis morgen als Chance da.
        sicher(static fn() => Spuerhund::lauf(), null);
        ansicht('umsatz_chancen', [
            'offen' => sicher(static fn() => Spuerhund::offen(), []),
            'vorbei' => sicher(static fn() => Spuerhund::vorbei(12), []),
        ]);
        break;

    case 'claude-erlauben':
        /* Claude fragt um Lesezugang (AI Office Stufe 2, 07.10.2026). Uwe sieht, wer fragt, was erlaubt
           wird und wie lange — und sagt Ja oder Nein. Die Anfrage selbst hat claude-oauth.php geprüft. */
        require_once __DIR__ . '/src/ClaudeZugang.php';
        unset($_SESSION['nach_anmeldung']);
        ansicht('claude_erlauben', [
            'anfrage' => sicher(static fn() => ClaudeZugang::anfrage((string) ($_GET['a'] ?? '')), null),
            'an' => sicher(static fn() => ClaudeZugang::an(), false),
        ]);
        break;

    case 'ai-freigaben':
        /* AI Freigaben (AI Office Stufe 1, 06.10.2026): Vorschläge von Claude/Werkstatt und die Mails,
           die der Not-Aus zurückhält — eine Seite für alles, was auf Uwes Ja wartet. */
        require_once __DIR__ . '/src/Freigabe.php';
        require_once __DIR__ . '/src/Ausgang.php';
        ansicht('ai_freigaben', [
            'offen' => sicher(static fn() => Freigabe::offen(), []),
            'ruhend' => sicher(static fn() => Freigabe::ruhend(), []),
            'entschieden' => $frEntschieden = sicher(static fn() => Freigabe::entschieden(15), []),
            // Mail-Spur: was jede genehmigte Freigabe verschickt hat, mit Text (07.10.2026)
            'mailsJeFreigabe' => sicher(static function () use (&$frEntschieden): array {
                require_once __DIR__ . '/src/MailSpur.php';
                $aus = [];
                foreach ((array) $frEntschieden as $f) { $aus[(int) $f['id']] = MailSpur::zuFreigabe((int) $f['id']); }
                return $aus;
            }, []),
            'gehalten' => sicher(static fn() => Ausgang::offen(), []),
            'notaus' => sicher(static fn() => Automation::notAus(), false),
            'fokus' => (int) $id,
        ]);
        break;

    case 'partner-support':
        require_once __DIR__ . '/src/PartnerTicket.php';
        $psStand = in_array($_GET['stand'] ?? '', ['erledigt', 'alle'], true) ? (string) $_GET['stand'] : '';
        ansicht('partner_support', ['tickets' => sicher(static fn() => PartnerTicket::verwaltungListe($psStand), []), 'stand' => $psStand]);
        break;

    case 'partner-meldungen':
        require_once __DIR__ . '/src/Partner.php';
        ansicht('partner_meldungen', []);
        break;

    case 'partner-reservierungen':
        /* Akquise-CRM F (06.10.2026): alle aktiven Reservierungen mit Ampel, Funnel + Reaktionszeit je Partner. */
        require_once __DIR__ . '/src/AkquisePartner.php';
        ansicht('partner_reservierungen', [
            'reservierungen' => sicher(static fn() => AkquisePartner::reservierungen(), []),
            'auswertung' => sicher(static fn() => AkquisePartner::auswertung(), []),
            'partner' => sicher(static fn() => Db::all("SELECT id, name FROM partner WHERE status = 'aktiv' AND COALESCE(test, 0) = 0 ORDER BY name"), []),
            'entscheide' => sicher(static fn() => Db::all('SELECT e.*, f.name AS firma, p.name AS partner, n.name AS neu FROM partner_entscheide e JOIN akq_firmen f ON f.id = e.firma_id
                JOIN partner p ON p.id = e.partner_id LEFT JOIN partner n ON n.id = e.neu_partner_id ORDER BY e.id DESC LIMIT 20'), []),
        ]);
        break;

    case 'partner':
        require_once __DIR__ . '/src/Partner.php';
        if ($unter === 'vorlagen') {
            require_once __DIR__ . '/src/PartnerVorlagen.php';
            ansicht('partner_vorlagen', ['katalog' => PartnerVorlagen::katalog()]);
            break;
        }
        /* Phase 9: Beträge, Belege und Auszahlungen nur für den Admin (Uwe: „Partner ja, Geld nein“). */
        if (!Rechte::geld() && ($unter === 'auszahlungslauf' || isset($_GET['beleg']))) {
            http_response_code(403); $_SESSION['fehler'] = 'Provisionen und Auszahlungen sieht nur der Admin.'; weiter('partner');
        }
        if ($unter === 'auszahlungslauf') { weiter('auszahlungen'); }   // alte Adresse (Phase 9: eigener Reiter)
        if ($unter === 'mediathek') {   // Mediathek der Partner (Phase 3)
            require_once __DIR__ . '/src/PartnerMediathek.php';
            if (isset($_GET['bild'])) {   // Vorschau auch für Entwürfe — nur in der Verwaltung
                $pmB = Db::wert('SELECT bild FROM partner_mediathek WHERE id = ? AND bild IS NOT NULL', [(int) $_GET['bild']], null);
                if (!is_string($pmB) || $pmB === '') { http_response_code(404); exit; }
                header('Content-Type: image/webp'); header('Cache-Control: private, max-age=300'); header('X-Content-Type-Options: nosniff');
                echo $pmB; exit;
            }
            ansicht('partner_mediathek', ['karten' => PartnerMediathek::verwaltungListe()]);
            break;
        }
        if ($id !== null && isset($_GET['akte'])) {   // Akte für den Anwalt (30.09.2026)
            require_once __DIR__ . '/src/PartnerSchutz.php';
            $pdf = PartnerSchutz::aktePdf($id, (string) $_GET['akte'] === 'de' ? 'de' : 'it');
            if ($pdf === null) { http_response_code(404); exit('Partner nicht gefunden.'); }
            Events::protokoll('partner_akte', 'Akte für den Anwalt erstellt', null, null, null, ['partner_id' => $id]);
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="akte-partner-' . $id . '-' . date('Y-m-d') . '.pdf"');
            echo $pdf; exit;
        }
        if ($id !== null && isset($_GET['beleg'])) {
            $a = Db::one('SELECT id FROM partner_auszahlungen WHERE id = ? AND partner_id = ?', [(int) $_GET['beleg'], $id]);
            $pdf = $a ? Partner::belegPdf((int) $a['id']) : null;
            if ($pdf === null) { http_response_code(404); exit('Beleg nicht gefunden.'); }
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="provision-' . (int) $a['id'] . '.pdf"');
            echo $pdf; exit;
        }
        if ($id !== null) {
            $pa = Partner::laden($id);
            if (!$pa) { http_response_code(404); exit('Partner nicht gefunden.'); }
            require_once __DIR__ . '/src/PartnerWege.php';
            require_once __DIR__ . '/src/PartnerPost.php';
            require_once __DIR__ . '/src/PartnerTicket.php';
            $paTickets = sicher(static fn() => PartnerTicket::fuerVerwaltung($id, 100), []);   // vor „gelesen“, damit „neu“ stimmt
            sicher(static fn() => PartnerPost::gelesen($id, 'vecom'));
            ansicht('partner_akte', [
                'p' => $pa,
                'tickets' => $paTickets,
                'nachrichten' => sicher(static fn() => PartnerPost::verlauf($id, 100), []),
                'weg' => PartnerWege::weg($pa),
                'offeneRaten' => (int) ($pa['customer_id'] ?? 0) > 0 ? sicher(static fn() => Db::all(
                    "SELECT z.id, z.bezeichnung, z.amount_cents, z.faellig_am FROM payments z
                       LEFT JOIN orders o ON o.id = z.order_id LEFT JOIN abos a ON a.id = z.abo_id
                      WHERE COALESCE(o.customer_id, a.customer_id) = ?
                        AND z.status NOT IN ('bezahlt','rueckerstattet','teilweise_erstattet','storniert','abgebrochen')
                      ORDER BY z.faellig_am, z.id", [(int) $pa['customer_id']]), []) : [],
                'satz' => Partner::satzFuer($pa),
                'summen' => Partner::summen($id),
                'zahlen' => Partner::kennzahlen($id),
                'provisionen' => sicher(static fn() => Db::all(
                    'SELECT pp.*, c.name AS kunde FROM partner_provisionen pp LEFT JOIN customers c ON c.id = pp.customer_id
                      WHERE pp.partner_id = ? ORDER BY pp.id DESC LIMIT 200', [$id]), []),
                'auszahlungen' => sicher(static fn() => Db::all('SELECT * FROM partner_auszahlungen WHERE partner_id = ? ORDER BY id DESC', [$id]), []),
                'kunden' => sicher(static fn() => Db::all(
                    'SELECT z.*, c.name, c.company FROM partner_zuordnungen z JOIN customers c ON c.id = z.customer_id
                      WHERE z.partner_id = ? ORDER BY z.created_at DESC', [$id]), []),
                'alleKunden' => sicher(static fn() => Db::all(
                    'SELECT c.id, c.name, c.company FROM customers c LEFT JOIN partner_zuordnungen z ON z.customer_id = c.id
                      WHERE z.customer_id IS NULL AND c.anonym_am IS NULL ORDER BY c.name'), []),
            ]);
            break;
        }
        require_once __DIR__ . '/src/PartnerWege.php';
        ansicht('partner', [
            'offeneAuszahlungen' => sicher(static fn() => Db::all(
                "SELECT a.*, p.name FROM partner_auszahlungen a JOIN partner p ON p.id = a.partner_id
                  WHERE a.status = 'offen' ORDER BY a.id"), []),
            'handarbeit' => sicher(static fn() => PartnerWege::handarbeit(), []),
            'auswertung' => sicher(static fn() => Partner::auswertung(12), []),
            'rangliste' => sicher(static function () { require_once __DIR__ . '/src/PartnerSteuerung.php'; return PartnerSteuerung::rangliste((string) ($_GET['sort'] ?? 'umsatz')); }, []),
            'sortierung' => (string) ($_GET['sort'] ?? 'umsatz'),
            'liste' => sicher(static fn() => Db::all(
                "SELECT p.*,
                        (SELECT COALESCE(SUM(anzahl),0) FROM partner_klicks k WHERE k.partner_id = p.id) AS klicks,
                        (SELECT COUNT(*) FROM partner_zuordnungen z WHERE z.partner_id = p.id) AS kunden,
                        (SELECT COALESCE(SUM(provision_cents),0) FROM partner_provisionen pp WHERE pp.partner_id = p.id
                            AND pp.status IN ('wartet','freigabe','bereit')) AS offen,
                        (SELECT COALESCE(SUM(provision_cents),0) FROM partner_provisionen pp WHERE pp.partner_id = p.id
                            AND pp.status = 'ausgezahlt') AS ausgezahlt
                   FROM partner p WHERE p.status <> 'geloescht'
                  ORDER BY FIELD(p.status,'bewerbung','aktiv','pausiert','abgelehnt'), p.created_at DESC"), []),
            'geloescht' => sicher(static fn() => Db::all("SELECT id, name FROM partner WHERE status = 'geloescht' ORDER BY id DESC"), []),
            'einbehaltMonat' => (int) sicher(static fn() => Db::wert(
                "SELECT COALESCE(SUM(einbehalt_cents),0) FROM partner_provisionen WHERE status = 'ausgezahlt'
                   AND ausgezahlt_am >= DATE_FORMAT(NOW() - INTERVAL 1 MONTH, '%Y-%m-01') AND ausgezahlt_am < DATE_FORMAT(NOW(), '%Y-%m-01')", [], 0), 0),
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
               Ansicht auf die Antworten, kein Vorgang.

               Aber es braucht Antworten. Am 13.09.2026 an einem leeren Bedarf
               gesehen (jemand hat den Konfigurator geoeffnet und sofort
               geschlossen): Das Briefing stand vollstaendig da und zaehlte
               unter "das ist kalkuliert und bezahlt" Grundgeruest, Texte und
               Bilder auf — bei null Antworten. Dieselbe Falle wie bei der
               Spanne, die vor der Korrektur b7d4af0 stillschweigend vier
               Seiten annahm. Deshalb hier dieselbe Schwelle: Ohne
               genugGesagt() kein Briefing. Die Ansicht laesst den Block dann
               ganz weg. */
            $bauprompt = Baukasten::genugGesagt($antworten)
                ? (string) sicher(static fn() => Bedarf::bauprompt($b, $antworten, $vorschlag, $aktive), '')
                : '';
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
                /* Der Preis geht erst raus, wenn der grosse Fragebogen zurueck ist. */
                'fragebogenFertig' => $kundeDa && (bool) sicher(static function () use ($b) {
                    require_once __DIR__ . '/src/Onboarding.php';
                    return Onboarding::fertig((int) $b['customer_id']);
                }, false),
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
            /* S4 (24.09.2026): Traegt der E-Mail-Einstieg? Derselbe Trichter
               fuer den neuen und den alten Weg, nebeneinander. */
            'zugangTrichter' => sicher(static function () {
                require_once __DIR__ . '/src/Zugang.php';
                return Zugang::trichter(90);
            }, []),
        ]);
        break;

    case 'bestellungen':
        require_once __DIR__ . '/src/Mail.php';   // die Ansicht fragt, ob der Link schon raus ist
        if ($unter === 'neu') {
            ansicht('bestellung_form', [
                /* Neueste zuerst (01.10.2026: Kunden ohne Namen standen als leere Zeile oben). */
                'kunden' => Db::all('SELECT id, name, company, email, kundennr FROM customers ORDER BY created_at DESC, id DESC'),
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
                                       AND (art IS NULL OR art NOT IN ('betreuung', 'hosting'))
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
        require_once __DIR__ . '/src/Werkstatt.php';// und ob Claude Code hereindarf
        if ($id !== null) {
            $p = Db::one('SELECT p.*, c.name AS kunde, c.email AS kunde_email, c.kundennr, o.order_no
                          FROM projects p JOIN customers c ON c.id = p.customer_id
                          LEFT JOIN orders o ON o.id = p.order_id WHERE p.id = ?', [$id]);
            if (!$p) { http_response_code(404); exit('Projekt nicht gefunden.'); }
            if (($teile[2] ?? '') === 'betriebsbericht.pdf') {
                require_once __DIR__ . '/src/Betrieb.php';
                $bbMonat = preg_match('~^\d{4}-\d{2}$~', (string) ($_GET['monat'] ?? '')) ? (string) $_GET['monat'] : null;
                $bbPdf = Betrieb::berichtPdf($id, $bbMonat);
                if ($bbPdf === '') { http_response_code(404); exit('Die Seite ist noch nicht im Betrieb.'); }
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="Betriebsbericht-' . $id . '-' . ($bbMonat ?? date('Y-m', strtotime('first day of last month'))) . '.pdf"');
                echo $bbPdf;
                exit;
            }
            if (($teile[2] ?? '') === 'uebergabe.pdf') {
                require_once __DIR__ . '/src/Lieferung.php';
                $upd = Lieferung::uebergabePdf($id);
                if ($upd === '') { http_response_code(404); exit('Keine Übergabe vorhanden.'); }
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="Uebergabe-' . $id . '.pdf"');
                echo $upd;
                exit;
            }
            ansicht('projekt', datenProjekt($id, $p));
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

    /* ======================================================================
       VIER SEITEN, DIE ES GAB UND DIE NIEMAND ERREICHTE

       Am 13.09.2026 beim Durchrendern aller Menuepunkte gefunden: „Ausgaben",
       „Betreuung", „Kundenstimmen" und „Fuers Finanzamt" standen seit jeher
       im Menue und antworteten mit 404. Die Ansichten lagen fertig unter
       app/views/, die Klassen dahinter auch — nur der Weg dorthin fehlte.

       Aufgefallen ist es nie, weil niemand sie anklickte; und niemand
       klickte sie an, weil sie unter „Alles andere" in der zweiten Haelfte
       einer Liste von vierundzwanzig standen. Genau das meint „nichts darf
       unuebersichtlich sein": Ein Menuepunkt, der ins Leere zeigt, faellt in
       einem kurzen Menue am ersten Tag auf.
       ====================================================================== */

    case 'ausgaben':
        require_once __DIR__ . '/src/Ausgabe.php';
        if ($unter === 'neu' || $id !== null) {
            $a = $id !== null ? sicher(static fn() => Ausgabe::eine($id), null) : null;
            if ($id !== null && !$a) { http_response_code(404); exit('Ausgabe nicht gefunden.'); }
            /* Die Datei eines Belegs — nur ueber PHP, wie alles Hochgeladene. */
            if ($a && ($teile[2] ?? '') === 'datei') {
                $pfad = (string) sicher(static fn() => Ausgabe::dateipfad($a), '');
                if ($pfad === '' || !is_file($pfad)) { http_response_code(404); exit('Keine Datei.'); }
                header('Content-Type: application/octet-stream');
                header('Content-Length: ' . (string) filesize($pfad));
                header('Content-Disposition: attachment; filename="beleg-' . (int) $a['id'] . '"');
                header('X-Content-Type-Options: nosniff');
                readfile($pfad);
                exit;
            }
            ansicht('ausgabe_form', [
                'a' => $a,
                'naechste' => sicher(static fn() => Ausgabe::naechsteNummer(), ''),
            ]);
            break;
        }
        $jahre = (array) sicher(static fn() => Ausgabe::jahre(), []);
        $jahr  = (int) ($_GET['jahr'] ?? ($jahre[0] ?? date('Y')));
        ansicht('ausgaben', [
            'jahre' => $jahre,
            'jahr'  => $jahr,
            'liste' => sicher(static fn() => Ausgabe::alle($jahr), []),
            'summe' => sicher(static fn() => Ausgabe::summe($jahr),
                ['anzahl' => 0, 'brutto' => 0, 'rc_netto' => 0, 'rc_iva' => 0]),
        ]);
        break;

    case 'abos':
        require_once __DIR__ . '/src/Abo.php';
        require_once __DIR__ . '/src/Leistungen.php';
        /* Phase 4: Vertraege, Hosting und was darauf wartet -- eine Seite. */
        ansicht('abos', [
            'liste'     => sicher(static fn() => Abo::alle(), []),
            'monatlich' => (int) sicher(static fn() => Abo::monatlich(), 0),
            'zahlen'    => sicher(static fn() => Leistungen::kennzahlen(), []),
            'warten'    => sicher(static fn() => Leistungen::warten(), []),
            'hosting'   => sicher(static fn() => Leistungen::hosting(), []),
        ]);
        break;

    case 'werbemittel':
        require_once __DIR__ . '/src/Werbemittel.php';
        if (($teile[1] ?? '') === 'pdf' && ctype_digit((string) ($teile[2] ?? ''))) {
            /* Freigegebene Druckdatei eines Partners (Phase 2): genau die gespeicherte Datei. */
            $wmD = Werbemittel::datei((int) $teile[2], null);
            if (!$wmD) { http_response_code(404); exit('Druckdatei nicht gefunden.'); }
            /* ?f=druck: dieselbe Gestaltung im Beschnitt der Druckerei (Flyer: 1 mm für Flyeralarm). */
            $wmDruck = ($_GET['f'] ?? '') === 'druck' && !empty($wmD['datei_druck']);
            header('Content-Type: application/pdf');
            header('X-Content-Type-Options: nosniff');
            header('Content-Disposition: inline; filename="druckdatei-' . (int) $wmD['id'] . '-' . substr((string) $wmD['datei_hash'], 0, 8) . ($wmDruck ? '-druckerei' : '') . '.pdf"');
            echo $wmDruck ? $wmD['datei_druck'] : $wmD['datei']; exit;
        }
        if (($teile[1] ?? '') === 'vorlagenfoto') {
            /* Produktfoto der Druckerei je Gestaltung (Musterdaten), wie der Partner es sieht (04.10.2026). */
            $wmVf = Werbemittel::vorlagenfoto((string) ($_GET['v'] ?? ''), (string) ($_GET['st'] ?? ''), (string) ($_GET['l'] ?? ''));
            if ($wmVf === null) { http_response_code(404); exit('—'); }
            header('Content-Type: image/jpeg');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: private, max-age=3600');
            echo $wmVf; exit;
        }
        if (($teile[1] ?? '') === 'bestellungen') {
            require_once __DIR__ . '/src/WmBestellung.php';
            if (isset($_GET['foto'])) {   // Foto einer Reklamation (Phase 6a) — nur in der Verwaltung
                $wmRf = WmBestellung::reklamationFoto((int) $_GET['foto']);
                if ($wmRf === null) { http_response_code(404); exit; }
                header('Content-Type: image/webp'); header('Cache-Control: private, max-age=300'); header('X-Content-Type-Options: nosniff');
                echo $wmRf; exit;
            }
            ansicht('werbemittel_bestellungen', ['liste' => WmBestellung::verwaltung(), 'zahlweg' => WmBestellung::zahlweg()]);
            break;
        }
        if (($teile[1] ?? '') === 'vorschau') {
            /* Als Partner ansehen (03.10.2026): dieselbe Ansicht wie im
               Partnerbereich, aber in der Verwaltung gerendert — nicht über den
               Token-Link, der ein Partner-Cookie setzen und Downloads
               protokollieren würde. Nur lesen: keine Formulare, keine Links. */
            require_once __DIR__ . '/src/Partner.php';
            $wmSp = in_array($_GET['sprache'] ?? '', Werbemittel::SPRACHEN, true) ? (string) $_GET['sprache'] : 'de';
            $wmListe = sicher(static fn() => Db::all("SELECT id, name, code FROM partner WHERE status = 'aktiv' ORDER BY name LIMIT 300"), []);
            $wmPa = isset($_GET['p']) ? Partner::laden((int) $_GET['p']) : null;
            if (!$wmPa && $wmListe) { $wmPa = Partner::laden((int) $wmListe[0]['id']); }
            ansicht('werbemittel_vorschau', [
                'katalog' => Werbemittel::katalog($wmSp, true),
                'sprache' => $wmSp, 'liste' => $wmListe,
                'partner' => $wmPa ?: ['id' => 0, 'name' => 'Maria Rossi', 'code' => 'MUSTER', 'email' => 'maria@example.com', 'sprache' => $wmSp],
            ]);
            break;
        }
        require_once __DIR__ . '/src/WmBestellung.php';
        ansicht('werbemittel', ['wm' => Werbemittel::verwaltung(), 'freigaben' => sicher(static fn() => Werbemittel::freigaben(), []),
            'zahlweg' => WmBestellung::zahlweg(), 'zahlwegGewollt' => (string) sicher(static fn() => Db::wert("SELECT svalue FROM settings WHERE skey = 'wm_zahlweg'", [], 'anfrage'), 'anfrage'),
            'offeneBestellungen' => (int) sicher(static fn() => Db::wert("SELECT COUNT(*) FROM wm_bestellungen WHERE status IN ('angefragt', 'offen', 'bezahlt', 'beim_drucker')"), 0)]);
        break;

    case 'stimmen':
        require_once __DIR__ . '/src/Stimme.php';
        ansicht('stimmen', ['liste' => sicher(static fn() => Stimme::alle(), [])]);
        break;

    /* WARUM DIESE SEITE „finanzamt" HEISST UND NICHT „steuerakte"
       ----------------------------------------------------------------------
       Weil es unter app/ einen Ordner „steuerakte" gibt: dort liegen die
       fertigen Jahrespakete. Die Umleitung in app/.htaccess laesst echte
       Verzeichnisse ausdruecklich in Ruhe (RewriteCond !-d), und der Ordner
       sperrt sich selbst mit „Require all denied". Ein Aufruf von
       /app/steuerakte waere also nicht diese Seite, sondern ein 403 — und
       zwar erst, sobald das erste Paket geschrieben ist. Vorher haette es
       jahrelang funktioniert.

       Der Menuepunkt heisst ohnehin „Fürs Finanzamt". Jetzt heisst die
       Adresse genauso. */
    case 'finanzamt':
        require_once __DIR__ . '/src/Steuerakte.php';
        /* Die Seite bietet je Jahr sieben Sachen zum Herunterladen an. Sie
           gehen alle durch dieselbe Tuer: /steuerakte/<jahr>/<was>. */
        if ($id !== null && ($teile[2] ?? '') !== '') {
            $jahr = (int) $id;
            $was  = (string) $teile[2];
            $csv = static function (string $inhalt, string $name): never {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $name . '"');
                header('X-Content-Type-Options: nosniff');
                echo "\xEF\xBB\xBF" . $inhalt;   // BOM, damit Excel die Umlaute nimmt
                exit;
            };
            try {
                switch ($was) {
                    case 'paket':
                        $zip = Steuerakte::paket($jahr);
                        if (!is_file($zip)) { throw new RuntimeException('Das Paket gibt es noch nicht.'); }
                        header('Content-Type: application/zip');
                        header('Content-Length: ' . (string) filesize($zip));
                        header('Content-Disposition: attachment; filename="' . Steuerakte::paketname($jahr) . '"');
                        header('X-Content-Type-Options: nosniff');
                        readfile($zip);
                        exit;
                    case 'einnahmen':    $csv(Steuerakte::einnahmenCsv($jahr),      "einnahmen-$jahr.csv");
                    case 'forderungen':  $csv(Steuerakte::forderungenCsv($jahr),    "forderungen-$jahr.csv");
                    case 'abgrenzung':   $csv(Steuerakte::abgrenzungCsv($jahr),     "jahreswechsel-$jahr.csv");
                    case 'ausgaben':     $csv(Steuerakte::ausgabenCsv($jahr),       "ausgaben-$jahr.csv");
                    case 'reversecharge':$csv(Steuerakte::reverseChargeCsv($jahr),  "reverse-charge-$jahr.csv");
                    case 'verzeichnis':  $csv(Steuerakte::verzeichnis($jahr),       "belegverzeichnis-$jahr.csv");
                }
            } catch (Throwable $e) {
                $_SESSION['schlecht'] = 'Das ließ sich nicht erzeugen: ' . $e->getMessage();
                weiter('finanzamt');
            }
            http_response_code(404);
            exit('Das gibt es hier nicht.');
        }

        $jahre = (array) sicher(static fn() => Steuerakte::jahre(), []);
        $uebersicht = $ausgabenJ = $grenzen = $archiv = [];
        foreach ($jahre as $j) {
            $j = (int) $j;
            $uebersicht[$j] = sicher(static fn() => Steuerakte::zusammenfassung($j), []);
            /* Die Summe, nicht die Liste: Die Ansicht liest anzahl, brutto,
               rc_netto -- mit der Liste der Belege fehlten "Ausgaben" und
               "Reverse Charge" (gefunden bei der Durchsicht am 26.09.2026). */
            $ausgabenJ[$j]  = sicher(static fn() => Ausgabe::summe($j),
                ['anzahl' => 0, 'brutto' => 0, 'rc_netto' => 0, 'rc_iva' => 0]);
            $grenzen[$j]    = sicher(static fn() => Steuerakte::grenzen($j),
                ['summe' => 0, 'waehrung' => 'EUR', 'anteil' => 0.0, 'warnung' => null]);
            $archiv[$j]     = sicher(static fn() => Steuerakte::archiv($j),
                ['stand' => null, 'bytes' => 0]);
        }
        ansicht('steuerakte', [
            'jahre'      => $jahre,
            'uebersicht' => $uebersicht,
            'ausgaben'   => $ausgabenJ,
            'grenzen'    => $grenzen,
            'archiv'     => $archiv,
            'fristen'    => sicher(static fn() => Steuerakte::fristen(), []),
        ]);
        break;

    /* Damit alles laeuft — die Einrichtung an einer Stelle statt verstreut
       auf sieben Seiten. Siehe app/src/Bereit.php, wo auch steht, welcher
       Vorfall sie ausgeloest hat. */
    case 'bereit':
        require_once __DIR__ . '/src/Bereit.php';
        ansicht('bereit', ['punkte' => sicher(static fn() => Bereit::punkte(), [])]);
        break;

    case 'dateien':
        require_once __DIR__ . '/src/Ablage.php';
        if ($id !== null) {
            $d = Db::one('SELECT * FROM files WHERE id = ?', [$id]);
            if (!$d) { http_response_code(404); exit('Datei nicht gefunden.'); }
            if (!Ablage::darfVerwaltung($d)) { http_response_code(403); exit('Sicherungen lädt nur ein Admin herunter.'); }
            /* Drei Wege zu derselben Datei: das Bild fuer die Liste, das
               Bild fuer die Grossansicht, und die Datei selbst. Die beiden
               Bilder rechnet Ablage neu — inline geht nur, was wir selbst
               erzeugt haben (siehe dort). Ohne "art" bleibt es beim
               Herunterladen, damit alte Verweise weiter stimmen. */
            $art = (string) ($_GET['art'] ?? '');
            if ($art === 'vorschau' || $art === 'gross') {
                Ablage::vorschauAusliefern($d,
                    $art === 'gross' ? Ablage::VORSCHAU_GROSS : Ablage::VORSCHAU_KLEIN);
            }
            Ablage::ausliefern($d);
        }

        /* DIE LISTE WAECHST MIT JEDEM KUNDEN
           ---------------------------------------------------------------
           Zweihundert Zeilen ohne Filter sind nach einem halben Jahr keine
           Liste mehr, sondern ein Haufen. Gefiltert wird nach dem, wonach
           man wirklich sucht: nach wem, und zu welchem Projekt. */
        $fKunde   = (int) ($_GET['kunde'] ?? 0);
        $fProjekt = (int) ($_GET['projekt'] ?? 0);

        $wo = [];
        $arg = [];
        if ($fKunde > 0)   { $wo[] = 'f.customer_id = :k'; $arg['k'] = $fKunde; }
        if ($fProjekt > 0) { $wo[] = 'f.project_id = :p';  $arg['p'] = $fProjekt; }
        $woSql = $wo ? ' WHERE ' . implode(' AND ', $wo) : '';

        ansicht('dateien', [
            'liste' => sicher(static fn() => Db::all(
                "SELECT f.*, c.name AS kunde, c.company AS firma, p.name AS projekt
                 FROM files f
                 LEFT JOIN customers c ON c.id = f.customer_id
                 LEFT JOIN projects  p ON p.id = f.project_id
                 $woSql
                 ORDER BY f.id DESC LIMIT 200", $arg)),
            /* Nur, wer wirklich Dateien hat. Eine Auswahlliste mit allen
               Kunden, von denen die meisten nie etwas geschickt haben, ist
               kein Filter, sondern ein zweites Suchproblem. */
            'kunden' => sicher(static fn() => Db::all(
                "SELECT c.id, COALESCE(NULLIF(c.company,''), c.name) AS wer, COUNT(*) AS n
                   FROM files f JOIN customers c ON c.id = f.customer_id
                  GROUP BY c.id, wer ORDER BY wer")),
            'projekte' => sicher(static fn() => Db::all(
                "SELECT p.id, p.name, COUNT(*) AS n
                   FROM files f JOIN projects p ON p.id = f.project_id
                  GROUP BY p.id, p.name ORDER BY p.id DESC")),
            'fKunde' => $fKunde, 'fProjekt' => $fProjekt,
            'bilder' => Ablage::bilderMoeglich(),
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
                /* NUR ECHTE WEBSITE-PAKETE MIT PREIS
                   ----------------------------------------------------------
                   Ungefiltert stand in dieser Auswahl auch das
                   Betreuungspaket -- ein Monatsvertrag, angeboten als
                   Website-Bestellung -- und der Sammelposten
                   "Individuelles Angebot" zu 0,00 €. Wer eines davon waehlte,
                   legte eine Bestellung ueber nichts an. */
                /* UND oeffentlich = 1, seit dem 13.09.2026.
                   ----------------------------------------------------------
                   Ohne diese Bedingung standen hier weiter Starter 499,
                   Business 899 und Premium 1.499 zur Auswahl -- die drei
                   Pakete, die am 12.09.2026 abgeschafft wurden. Sie sind
                   absichtlich nur unsichtbar und nicht geloescht, weil
                   Bestellungen und Belege an ihnen haengen (Migration 025 und
                   043). Wer sie hier gewaehlt haette, haette eine neue
                   Bestellung ueber einen Preis angelegt, den es nicht mehr
                   gibt -- und der Kunde haette ihn schriftlich.
                   Bleibt die Liste leer, verschwindet der ganze Block: Der Weg
                   fuehrt ueber Konfigurator und Angebot. Stellt Uwe eines Tages
                   wieder ein Festpreis-Paket auf die Seite, ist es hier von
                   selbst zurueck. */
                'pakete'     => Db::all(
                    "SELECT id, name, price_cents, currency FROM packages
                      WHERE active = 1 AND oeffentlich = 1
                        AND art = 'website' AND price_cents > 0
                      ORDER BY sort, price_cents"),
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
        // Die Ansicht rechnet den Weiter-Prompt und die Stufe je Kachel.
        require_once __DIR__ . '/src/Briefing.php';
        ansicht('werkstatt', [
            'einrichtung' => sicher(static fn() => Standard::einrichtungsstand(),
                ['punkte' => [], 'offen' => [], 'gesamt' => 0]),
            'claudeZiel'  => sicher(static fn() => Standard::claudeZiel(), 'https://claude.ai/new'),
            'liste' => sicher(static fn() => Db::all(
            "SELECT p.id, p.name, p.status, p.progress, p.deadline, p.preview_url,
                    p.briefing, p.briefing_am, p.chat_url, p.abnahme, p.abnahme_am,
                    p.ambition, p.customer_id,
                    c.id AS kunde_id, c.name AS kunde, c.company, c.kundennr,
                    c.industry,
                    o.price_cents,
                    w.url AS live, w.domain, w.last_status, w.ssl_expires_at
               FROM projects p
               JOIN customers c ON c.id = p.customer_id
               LEFT JOIN orders o ON o.id = p.order_id
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
        require_once __DIR__ . '/src/Werkstatt.php';
        ansicht('standard', [
            'text'      => Standard::text(),
            'eigener'   => Standard::eigener(),
            'gesehenAm' => Standard::gesehenAm(),
            'gesehen'   => Standard::gesehen(),
            'anhaengen' => Standard::anhaengen(),
            'projekt'   => Standard::claudeProjekt(),
            'wSchluessel' => sicher(static fn() => Werkstatt::schluessel(), ''),
            'wAdresse'    => sicher(static fn() => Werkstatt::adresse(), ''),
        ]);
        break;

    case 'einstellungen':
        /* EINE SEITE, SIEBEN BEREICHE
           ------------------------------------------------------------------
           Geladen wird nur, was der gewaehlte Bereich braucht. Alles auf
           einmal hiesse: bei jedem Aufruf der Firmendaten die Stripe-Klasse
           bauen, die Webhook-Ereignisse holen und vierzehn
           Konfigurationsbloecke erzeugen -- fuer eine Seite, auf der zwei
           Felder geaendert werden. */
        $b = (string) ($_GET['b'] ?? 'firma');
        $daten = [];

        if ($b === 'firma') {
            require_once __DIR__ . '/src/Firma.php';
            $daten['firma'] = sicher(static fn() => Firma::alle(), []);
        }

        if ($b === 'zugaenge') {
            $daten['zugaenge'] = sicher(static fn() => Db::all(
                'SELECT id, name, email, role, active, last_login_at, created_at
                 FROM users ORDER BY active DESC, id'));
            $daten['cockpit'] = sicher(static function () {
                require_once __DIR__ . '/src/Cockpit.php';
                return ['geschuetzt' => Cockpit::geschuetzt(), 'eingerichtet' => Cockpit::eingerichtet(),
                        'beschreibbar' => Cockpit::beschreibbar(), 'benutzer' => Cockpit::benutzer(),
                        'adresse' => Cockpit::adresse()];
            }, ['geschuetzt' => null, 'eingerichtet' => false, 'beschreibbar' => false,
                'benutzer' => null, 'adresse' => '']);
        }

        if ($b === 'email') {
            $daten['versand'] = sicher(static function () {
                require_once __DIR__ . '/src/Versand.php';
                return ['herkunft' => Versand::herkunft(), 'ende' => Versand::schluesselEnde(),
                        'from' => Versand::absender(), 'name' => Versand::name(),
                        'to' => Versand::meldungenAn()];
            }, ['herkunft' => 'keine', 'ende' => '', 'from' => '', 'name' => '', 'to' => '']);
            $daten['versandTest'] = $_SESSION['versand_test'] ?? null;
            unset($_SESSION['versand_test']);
            $daten['zuruf'] = sicher(static function () {
                require_once __DIR__ . '/src/Zuruf.php';
                return ['an' => Zuruf::an(), 'nummer' => Zuruf::nummer(),
                        'schluessel' => Zuruf::hatSchluessel(), 'zuletzt' => Zuruf::zuletzt()];
            }, ['an' => false, 'nummer' => '', 'schluessel' => false, 'zuletzt' => '']);
        }

        if ($b === 'bezahlung') {
            require_once __DIR__ . '/src/Zahlung/Anbieter.php';
            require_once __DIR__ . '/src/Zahlung/Stripe.php';
            $daten['stripe']     = new StripeAnbieter();
            $daten['liste']      = sicher(static fn() => Db::all('SELECT * FROM integrations ORDER BY category, name'));
            /* Nur Stripe: Seit dem 30.09.2026 landen auch die Updates des
               Telegram-Bots in webhook_events (doppelte Zustellung abfangen) --
               ohne den Filter stuenden hier 25 Chat-Klicks statt der Zahlungen. */
            $daten['ereignisse'] = sicher(static fn() => Db::all("SELECT * FROM webhook_events WHERE provider = 'stripe' ORDER BY id DESC LIMIT 25"));
            $daten['offen']      = (int) sicher(static fn() => Db::wert(
                "SELECT COUNT(*) FROM webhook_events WHERE provider = 'stripe' AND status = 'fehler'", [], 0), 0);
        }

        if ($b === 'telegram') {
            require_once __DIR__ . '/src/Telegram.php';
            $daten['telegram'] = sicher(static fn() => Telegram::stand(), ['token' => false, 'ende' => '', 'name' => '',
                'angemeldet' => '', 'bereit' => false, 'adresse' => '', 'chats' => 0, 'abgeschickt' => 0, 'letzte' => '']);
            $daten['telegramPruefung'] = $_SESSION['telegram_pruefung'] ?? null;
            $daten['telegramAdmin'] = sicher(static function () { require_once __DIR__ . '/src/TelegramAdmin.php'; return TelegramAdmin::chat((int) Auth::id()); }, null);
            $daten['telegramEntwurf'] = (string) ($_SESSION['telegram_entwurf'] ?? '');
            /* Kanal-Vorschläge 2, 5, 6 (01.10.2026): Kommentare, Umfragen, Redaktionsplan — alles aus der eigenen Datenbank. */
            $daten['tgGruppe'] = sicher(static function () { require_once __DIR__ . '/src/TelegramGruppe.php'; return TelegramGruppe::stand(); }, ['id' => '', 'titel' => '', 'name' => '', 'schutz' => false]);
            $daten['tgUmfragen'] = sicher(static function () { require_once __DIR__ . '/src/TelegramUmfrage.php'; return TelegramUmfrage::liste(5); }, []);
            $daten['tgUmfrageEntwurf'] = (array) ($_SESSION['telegram_umfrage_entwurf'] ?? []);
            $daten['tgPlan'] = sicher(static function () { require_once __DIR__ . '/src/TelegramKanalPlan.php';
                return ['e' => TelegramKanalPlan::einstellung(), 'naechster' => TelegramKanalPlan::naechsterLauf(), 'thema' => TelegramKanalPlan::thema(TelegramKanalPlan::naechsterLauf() ?? time())]; }, null);
            unset($_SESSION['telegram_pruefung'], $_SESSION['telegram_entwurf'], $_SESSION['telegram_umfrage_entwurf']);
        }

        if ($b === 'telefon') {
            require_once __DIR__ . '/src/Telefon.php';
            require_once __DIR__ . '/src/Baukasten.php';
            require_once __DIR__ . '/src/Strato.php';
            $daten['schluessel'] = sicher(static fn() => Telefon::schluessel(), '');
            $daten['adresse']    = sicher(static fn() => Telefon::adresse(), '');
            $daten['modus']      = sicher(static fn() => Telefon::modus(), 'normal');
            $daten['merkliste']  = sicher(static fn() => Telefon::merkliste(), []);
            $daten['strato']     = sicher(static fn() => [
                'eingerichtet' => Strato::eingerichtet(),
                'fehler'       => Strato::fehler(),
                'zuletzt'      => Strato::zuletzt(),
                'anzahl'       => (int) Db::wert('SELECT COUNT(*) FROM telefon_gespraeche', [], 0),
                'gesperrt'     => Strato::gesperrt(),
                'werkzeuge_am' => Strato::werkzeugeAm(),
            ], ['eingerichtet' => false, 'fehler' => '', 'zuletzt' => '', 'anzahl' => 0,
                'gesperrt' => 0, 'werkzeuge_am' => '']);
        }

        if ($b === 'ueberwachung') {
            require_once __DIR__ . '/src/Cron.php';
            $daten['adresse'] = sicher(static fn() => Cron::adresse(), '');
            $daten['lauf']    = sicher(static fn() => Cron::zuletzt(), null);
            $daten['bilanz']  = sicher(static fn() => Cron::letzteBilanz(), null);
            $daten['cronWeg'] = (string) sicher(static fn() => Db::wert("SELECT svalue FROM settings WHERE skey = 'cron_weg'", [], ''), '');
            $daten['sicherungAussen'] = sicher(static function () {
                require_once __DIR__ . '/src/SicherungAussen.php';
                return SicherungAussen::stand();
            }, ['eingerichtet' => false, 'fingerabdruck' => '', 'abgeholt' => null, 'probe' => null]);
        }

        if ($b === 'claude') {
            /* Claudes Lesezugang, das Morgenbriefing und das Wissen (AI Office Stufe 2, 07.10.2026). */
            require_once __DIR__ . '/src/ClaudeZugang.php';
            require_once __DIR__ . '/src/Wissen.php';
            require_once __DIR__ . '/src/Morgenbriefing.php';
            $daten['claude'] = sicher(static fn() => ['an' => ClaudeZugang::an(), 'adresse' => ClaudeZugang::ressource(),
                'verbindungen' => ClaudeZugang::verbindungen(), 'griffe' => ClaudeZugang::letzteGriffe(20)],
                ['an' => false, 'adresse' => '', 'verbindungen' => [], 'griffe' => []]);
            $daten['wissen'] = sicher(static fn() => Wissen::stand(), null);
            $daten['briefing'] = (string) sicher(static fn() => Morgenbriefing::text(Morgenbriefing::daten()), '');
            $daten['briefingZuletzt'] = (string) sicher(static fn() => Db::wert("SELECT svalue FROM settings WHERE skey = 'cron_morgenbriefing'", [], ''), '');
            $daten['telegramVerbunden'] = (bool) sicher(static function () { require_once __DIR__ . '/src/TelegramAdmin.php'; return TelegramAdmin::chat((int) Auth::id()) !== null; }, false);
            $daten['ki'] = sicher(static function () { require_once __DIR__ . '/src/Ki.php'; return Ki::stand(); }, null);
        }

        if ($b === 'daten') {
            require_once __DIR__ . '/src/Beispieldaten.php';
            $daten['beispiele']  = sicher(static fn() => Beispieldaten::anzahl(), 0);
            $daten['echteDaten'] = sicher(static fn() => Beispieldaten::echteDatenDa(), true);
        }

        ansicht('einstellungen', $daten);
        break;

    /* Die Integrationen sind ein Bereich der Einstellungen geworden. Die
       alte Adresse bleibt gueltig -- Lesezeichen und alte Meldungen zeigen
       darauf, und eine tote Adresse ist eine schlechtere Auskunft als eine
       Weiterleitung. */
    case 'integrationen':
        weiter('einstellungen?b=bezahlung');
        break;

    case 'telefon':
        /* Der Telefonassistent. Alles, was zum Einrichten bei STRATO noetig
           ist, plus die Spur dessen, was er getan hat. */
        require_once __DIR__ . '/src/Telefon.php';
        require_once __DIR__ . '/src/Strato.php';

        /* Die Gespraeche von STRATO. Zeitraum und Filter stehen in der
           Adresse, damit ein gefundener Blick sich verschicken und
           wiederfinden laesst -- „mit Befund, letzte 90 Tage" ist eine
           Frage, die man mehr als einmal stellt. */
        $tageG  = max(1, min(365, (int) ($_GET['t'] ?? 30)));
        $filterG = (string) ($_GET['f'] ?? '');
        $gespraecheG = sicher(static fn() => Strato::gespraeche($tageG, $filterG, 120), []);

        /* Und zu jedem die eigene Spur. In einer Schleife, weil ein JOIN
           ueber ein Zeitfenster in SQL zwar ginge, aber niemand ihn danach
           noch lesen koennte -- und es sind hoechstens hundertzwanzig. */
        $spurenG = [];
        foreach ($gespraecheG as $gg) {
            $spurenG[(string) $gg['id']] = sicher(static fn() => Strato::spur($gg), []);
        }

        ansicht('telefon', [
            'gespraeche' => $gespraecheG,
            'spuren'     => $spurenG,
            'zahlen'     => sicher(static fn() => Strato::zahlen($tageG), ['anrufe' => 0]),
            'strato'     => sicher(static fn() => [
                'eingerichtet' => Strato::eingerichtet(),
                'fehler'       => Strato::fehler(),
                'zuletzt'      => Strato::zuletzt(),
                'gesperrt'     => Strato::gesperrt(),
            ], ['eingerichtet' => false, 'fehler' => '', 'zuletzt' => '', 'gesperrt' => 0]),
            /* Die Rückfrage aus dem letzten Löschversuch. Sie steht in der
               Sitzung und nicht in der Adresse: Sie soll genau einmal
               beantwortet werden und danach weg sein. */
            'loeschfrage'=> (static function () {
                $f = $_SESSION['loeschfrage'] ?? null;
                unset($_SESSION['loeschfrage']);
                return $f;
            })(),
            'verlauf'    => sicher(static fn() => Db::all(
                "SELECT * FROM activities WHERE type LIKE 'telefon\\_%'
                  ORDER BY id DESC LIMIT 40"), []),
            'anzahl'     => sicher(static fn() => (int) Db::wert(
                "SELECT COUNT(*) FROM activities WHERE type LIKE 'telefon\\_%'
                   AND created_at >= NOW() - INTERVAL 30 DAY", [], 0), 0),
            'modus'      => sicher(static fn() => Telefon::modus(), 'normal'),
            'luecken'    => sicher(static fn() => Telefon::luecken(), []),
            /* DER TRICHTER
               Ohne ihn weisst du in drei Monaten nicht, ob der Tarif sich
               traegt. Was gezaehlt wird und warum nur das, steht bei
               Telefon::trichter() -- dort, wo die Pruefkette es nachrechnen
               kann. */
            'trichter'   => sicher(static fn() => Telefon::trichter(90), []),
            /* Woran Anrufer haengen bleiben. Zwanzig Anrufe zum Fragebogen
               sind kein Support-Fall, sondern ein Produktfehler. */
            'haken'      => sicher(static fn() => Telefon::haken(90), []),
            /* Wer heute einen Anruf erwartet. Steht bewusst ganz oben auf
               der Seite: Es ist das Einzige hier, was jemand persönlich
               tun muss. */
            'rueckrufe'  => sicher(static fn() => Telefon::rueckrufe(30), []),
            /* Gemessen, nicht geraten: ob die Rufnummer des Anrufers die
               Strecke über zwei fremde Systeme überlebt. */
            'cli'        => sicher(static fn() => Telefon::anrufernummer(90), []),
            /* Was sie sich angewöhnt hat. Der Cron schreibt ihn einmal die
               Woche; hier steht der letzte Stand, damit die Vorschläge nicht
               nur in einer Meldung stehen, die man wegklickt. */
            'rueckblick' => sicher(static fn() => Telefon::letzterRueckblick(), null),
            /* Gespräche, in denen etwas anfing und nichts herauskam. Das ist
               der Fall vom 6.9., 22:23: alles richtig gemacht, den Link
               zugesagt — und nie verschickt. */
            'offen'      => sicher(static fn() => Telefon::offeneGespraeche(7), []),
        ]);
        break;

    case 'akquise':
        require __DIR__ . '/akquise_route.php';
        exit;

    case 'pruefspur':
        /* Prüfspur (Phase 9): nur der Admin (nicht in Rechte::SEITEN). Nur lesen, Geheimes geschwärzt. */
        require_once __DIR__ . '/src/Pruefspur.php';
        $psF = ['wer' => mb_substr((string) ($_GET['wer'] ?? ''), 0, 80), 'tat' => mb_substr(preg_replace('~[^a-z0-9_]~', '', (string) ($_GET['tat'] ?? '')) ?? '', 0, 60),
                'objekt' => mb_substr(preg_replace('~[^a-z0-9_]~', '', (string) ($_GET['objekt'] ?? '')) ?? '', 0, 40), 'objekt_id' => max(0, (int) ($_GET['objekt_id'] ?? 0)),
                'von' => (string) ($_GET['von'] ?? ''), 'bis' => (string) ($_GET['bis'] ?? '')];
        ansicht('pruefspur', ['f' => $psF, 'spur' => sicher(static fn() => Pruefspur::lesen($psF + ['vor' => max(0, (int) ($_GET['vor'] ?? 0))]), ['zeilen' => [], 'weiter' => null]),
                              'auswahl' => sicher(static fn() => Pruefspur::auswahl(), ['wer' => [], 'objekt' => []])]);
        break;

    case 'automationen':
        /* Automation Center (Phase 8): alle Regeln nach Bereich, Schalter, Not-Aus, Probelauf (nur lesen). */
        require_once __DIR__ . '/src/Automation.php';
        require_once __DIR__ . '/src/Cron.php';
        $amProbe = (string) ($_GET['probe'] ?? '');
        ansicht('automationen', [
            'liste' => sicher(static fn() => Automation::liste(), []),
            'notaus' => sicher(static fn() => Automation::notAusStand(), ['an' => false, 'am' => '', 'von' => '']),
            'probeRegel' => isset(Automation::REGELN[$amProbe]) ? $amProbe : '',
            'probe' => $amProbe !== '' ? sicher(static fn() => Automation::probe($amProbe), null) : null,
            'lauf' => sicher(static fn() => Cron::zuletzt(), null),
            'admin' => Auth::istAdmin(),
            'darfNotaus' => Rechte::darfTat('automation_notaus'),
            'gehalten' => sicher(static function () { require_once __DIR__ . '/src/Ausgang.php'; return Ausgang::offen(); }, []),
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

    case 'betrieb':
        /* AutoBuild Phase 10: alle Live-Seiten mit Ampel. */
        require_once __DIR__ . '/src/Betrieb.php';
        ansicht('betrieb', ['liste' => sicher(static fn() => Betrieb::uebersicht(), []), 'admin' => Auth::istAdmin()]);
        break;

    case 'bauregeln':
        require_once __DIR__ . '/src/BauRegeln.php';
        require_once __DIR__ . '/src/Standard.php';
        ansicht('bauregeln', ['admin' => Auth::istAdmin()]);
        break;

    case 'rechnungsmuster':
        /* Muster-Rechnung ohne Datenbankeintrag (Einstellungen → Firmendaten, 07.10.2026). */
        Auth::nurAdmin();
        require_once __DIR__ . '/src/Rechnung.php';
        $muDaten = Rechnung::muster((string) ($_GET['fall'] ?? 'beleg'), (string) ($_GET['sprache'] ?? 'de'));
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="Muster-' . preg_replace('~[^a-z_]~', '', (string) ($_GET['fall'] ?? 'beleg')) . '.pdf"');
        header('X-Content-Type-Options: nosniff');
        echo $muDaten;
        exit;

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
            if (($teile[2] ?? '') === 'xml') {
                /* FatturaPA zum Hochladen beim SDI (Fatture e Corrispettivi) oder für den Commercialista.
                   Verschickt wird nichts automatisch. */
                require_once __DIR__ . '/src/FatturaPa.php';
                $fx = FatturaPa::erzeugen((int) $r['id']);
                header('Content-Type: application/xml; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $fx['name'] . '"');
                header('X-Content-Type-Options: nosniff');
                echo $fx['xml'];
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
                "SELECT COALESCE(SUM(IF(doc_typ = 'gutschrift', -total_cents, total_cents)),0) FROM invoices WHERE YEAR(issued_at) = ?", [date('Y')]), 0),
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

    /* Besucher (26.09.2026): der Entwurf vom 25.09. eingebunden -- was die
       Website zählt, ohne IP und ohne Keks. Umsatz steht unter „Zahlen“. */
    case 'tracking':   // Partner-Tracking (30.09.2026, Uwe: „Alles“)
        require_once __DIR__ . '/src/Spur.php';
        require_once __DIR__ . '/src/Geo.php';
        if ($unter === 'live' || ($_SERVER['HTTP_X_TEIL'] ?? '') !== '') {
            $live = Spur::live();
            header('Cache-Control: no-store');
            require __DIR__ . '/views/tracking_live.php';
            exit;
        }
        $z = Spur::zeitraum((string) ($_GET['z'] ?? '30'), (string) ($_GET['von'] ?? ''), (string) ($_GET['bis'] ?? ''));
        $f = ['partner' => max(0, (int) ($_GET['partner'] ?? 0))];
        foreach (['land', 'geraet', 'quelle', 'status'] as $fk) {
            $fv = (string) ($_GET[$fk] ?? '');
            $f[$fk] = preg_match('/^[A-Za-z_-]{1,30}$/', $fv) ? $fv : '';
        }
        $detail = null;
        if ($f['partner'] > 0 && ($dp = Partner::laden($f['partner'])) !== null) {
            $kl = static fn(string $zs): int => Spur::kennzahlen(...array_merge(array_slice(Spur::zeitraum($zs), 0, 2), [['partner' => (int) $dp['id']]]))['klicks'];
            require_once __DIR__ . '/lib/qrcode.php';
            $qr = QRCode::getMinimumQRCode(Partner::link($dp), QR_ERROR_CORRECT_LEVEL_M);
            $qn = $qr->getModuleCount(); $qd = '';
            for ($qy = 0; $qy < $qn; $qy++) { for ($qx = 0; $qx < $qn; $qx++) { if ($qr->isDark($qy, $qx)) { $qd .= "M{$qx},{$qy}h1v1h-1z"; } } }
            $detail = ['partner' => $dp, 'k' => Spur::kennzahlen($z[0], $z[1], $f),
                'klicks' => ['heute' => $kl('heute'), '7' => $kl('7'), '30' => $kl('30'), 'gesamt' => Partner::klicksImmer((int) $dp['id'])],
                'provision' => (int) (Partner::summen((int) $dp['id'])['ausgezahlt'] ?? 0) + (int) (Partner::summen((int) $dp['id'])['bereit'] ?? 0) + (int) (Partner::summen((int) $dp['id'])['wartet'] ?? 0),
                'qr' => '<svg viewBox="0 0 ' . $qn . ' ' . $qn . '" role="img" aria-label="QR-Code des Empfehlungslinks" shape-rendering="crispEdges"><path fill="#000" d="' . $qd . '"/></svg>'];
        }
        $journey = isset($_GET['besuch']) ? Spur::journey((int) $_GET['besuch']) : null;
        $einst = [];
        foreach (array_keys(Spur::STANDARD) as $ek) { $einst[$ek] = Spur::einstellung($ek); }
        ansicht('tracking', [
            'z' => $z, 'f' => $f, 'k' => Spur::kennzahlen($z[0], $z[1], $f), 'heute' => Spur::heuteSaetze(),
            'tabelle' => Spur::partnerTabelle($z[0], $z[1], $f), 'funnel' => Spur::funnel($z[0], $z[1], $f), 'herkunft' => Spur::herkunft($z[0], $z[1], $f),
            'besuche' => Spur::besuche($z[0], $z[1], $f), 'live' => Spur::live(), 'detail' => $detail, 'journey' => $journey, 'einst' => $einst,
            'partnerListe' => Db::all("SELECT id, name FROM partner WHERE status IN ('aktiv','pausiert') ORDER BY name"),
        ]);
        break;

    case 'marketing':   // G1 (01.10.2026, Uwe: Ja): Marketing beginnt mit vier Schritten je Land
        require_once __DIR__ . '/src/MkStart.php';
        require_once __DIR__ . '/src/MkKanaele.php';
        $msLand = MkLand::wahl();
        ansicht('mk_start', ['land' => $msLand, 'st' => MkStart::schritte($msLand), 'fehl' => MkKanaele::fehlgeschlagen($msLand),
            'anm' => sicher(static function () use ($msLand): ?array { require_once __DIR__ . '/src/MkAnmeldungen.php'; return MkAnmeldungen::liste($msLand); }, null)]);
        break;

    case 'zahlen':   // Marketing · Zahlen (Growth Engine Phase 2, 30.09.2026; seit G1 unter „Zahlen“)
        require_once __DIR__ . '/src/MkKennzahlen.php';
        $mkZ = MkKennzahlen::zeitraum((string) ($_GET['z'] ?? '30'), (string) ($_GET['von'] ?? ''), (string) ($_GET['bis'] ?? ''));
        ansicht('marketing', [
            'z' => $mkZ, 'd' => MkKennzahlen::ueberblick($mkZ),
            'sicht' => ($_GET['sicht'] ?? '') === 'chef' ? 'chef' : 'alles',
        ]);
        break;

    case 'telegram':    // Telegram Growth Engine T2 (01.10.2026, Uwe: „Ja mach T2“) — Reiter unter Marketing
        require_once __DIR__ . '/src/TelegramZahlen.php';
        $tgZ = MkKennzahlen::zeitraum((string) ($_GET['z'] ?? '30'), (string) ($_GET['von'] ?? ''), (string) ($_GET['bis'] ?? ''));
        /* Kanal-Links (01.10.2026): fehlende Einladungslinks beim Öffnen anlegen — aus der Verwaltung, nie von der öffentlichen kanal.php. */
        $tgOrte = sicher(static fn() => TelegramWachstum::kanalLinksSicherstellen(), []);
        ansicht('telegram', ['z' => $tgZ, 'd' => TelegramZahlen::dashboard($tgZ), 'orte' => $tgOrte]);
        break;

    case 'seite-vorschau':   // S6: Landingpage ansehen, bevor sie online geht
        require_once __DIR__ . '/src/MkSeite.php';
        $svS = $id !== null ? Db::one('SELECT slug FROM mk_seiten WHERE id = ?', [$id]) : null;
        $svS = $svS ? MkSeite::zumAnzeigen((string) $svS['slug'], true) : null;
        $svG = $svS ? MkSeite::geruest((string) $svS['sprache'], dirname(__DIR__)) : null;
        if (!$svS || $svG === null) { http_response_code(404); ansicht('spaeter', ['bereich' => 'unbekannt']); break; }
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
        echo MkSeite::html($svS, $svG, (string) Config::get('website', 'https://vecom-design.it'), true);
        exit;

    case 'bewertung-karte':   // S3 (01.10.2026, Uwe: Ja): QR „Bewerten Sie uns“ zum Ausdrucken — persönlich übergeben, nicht per Mail
        require_once __DIR__ . '/src/Firma.php';
        require_once __DIR__ . '/src/QrBild.php';
        $bkLink = (string) Firma::get('firma_google_bewertung');
        if (preg_match('~^https://~', $bkLink) !== 1) { $_SESSION['fehler'] = 'Erst den Google-Bewertungslink unter Einstellungen › Firma eintragen.'; weiter('verzeichnisse#google-profil'); }
        header('Cache-Control: no-store');
        $bkQr = QrBild::svg($bkLink, 300, 1);
        ?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Bewerten Sie uns — Vecom Design</title>
<style>@page{size:A6;margin:8mm}body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;color:#141414;background:#fff}
.karte{display:grid;gap:4mm;justify-items:center;text-align:center;padding:6mm;max-width:105mm;margin:0 auto}
h1{font-size:22pt;margin:0;line-height:1.15}.it{font-size:15pt;color:#444;margin:0}.qr{width:62mm;height:62mm}.qr svg{width:100%;height:100%}
.klein{font-size:10pt;color:#555;margin:0}.marke{font-weight:800;letter-spacing:.08em;font-size:12pt}@media screen{body{background:#eee}.karte{background:#fff;margin:10mm auto;box-shadow:0 2px 14px rgba(0,0,0,.15)}}</style></head>
<body><div class="karte"><div class="marke">VECOM DESIGN</div><h1>Wie war's?</h1><p class="it">Com'è andata? · Lasci una recensione</p>
<div class="qr" role="img" aria-label="QR-Code zur Google-Bewertung"><?= $bkQr ?></div>
<p class="klein">Code scannen — Ihre Bewertung auf Google hilft anderen Betrieben bei der Wahl. Danke!</p></div>
<script>window.addEventListener('load',function(){setTimeout(function(){window.print();},300);});</script></body></html><?php
        exit;

    case 'meta-rueckruf':   // „Mit Meta verbinden“: Rückruf (nur angemeldet, Zustand aus der Sitzung)
        require_once __DIR__ . '/src/MetaLogin.php';
        $mlOk = hash_equals((string) ($_SESSION['meta_zustand'] ?? ''), (string) ($_GET['state'] ?? '')) && ($_SESSION['meta_zustand'] ?? '') !== '';
        unset($_SESSION['meta_zustand']);
        if (!$mlOk) { $_SESSION['fehler'] = 'Die Rückmeldung von Meta passt nicht zu deinem Klick — bitte noch einmal „Mit Meta verbinden“ drücken.'; weiter('kanaele#meta'); }
        if (isset($_GET['error'])) { $_SESSION['fehler'] = 'Meta hat abgebrochen: ' . mb_substr((string) ($_GET['error_description'] ?? $_GET['error']), 0, 200); weiter('kanaele#meta'); }
        $mlR = MetaLogin::rueckruf((string) ($_GET['code'] ?? ''));
        Events::pruefspur('meta_verbunden', 'settings', null, [], ['ok' => $mlR['ok'], 'seite_id' => MetaSeite::einstellungen()['seite_id'], 'ig_id' => MetaSeite::einstellungen()['ig_id']]);
        $_SESSION[$mlR['ok'] ? 'gut' : 'fehler'] = $mlR['text'];
        weiter('kanaele');

    case 'plattform-rueckruf':   // P4: OAuth-Rückruf von LinkedIn, Google, YouTube, TikTok (nur angemeldet, Zustand aus der Sitzung)
        require_once __DIR__ . '/src/MkPlattform.php';
        $pfP = (string) ($_GET['p'] ?? ($unter ?? ''));   // TikTok: /plattform-rueckruf/tiktok (ohne Abfrageteil)
        $pfOk = isset(MkPlattform::ALLE[$pfP]) && ($_SESSION['pf_plattform'] ?? '') === $pfP
             && hash_equals((string) ($_SESSION['pf_zustand'] ?? ''), (string) ($_GET['state'] ?? ''));
        unset($_SESSION['pf_zustand'], $_SESSION['pf_plattform']);
        if (!$pfOk) { $_SESSION['fehler'] = 'Die Rückmeldung der Plattform passt nicht zu deinem Klick — bitte noch einmal „Verbinden“ drücken.'; weiter('kanaele#voll'); }
        if (isset($_GET['error'])) { $_SESSION['fehler'] = 'Die Plattform hat abgelehnt: ' . mb_substr((string) ($_GET['error_description'] ?? $_GET['error']), 0, 200); weiter('kanaele#pf-' . $pfP); }
        $pfF = MkPlattform::rueckruf($pfP, (string) ($_GET['code'] ?? ''));
        $_SESSION[$pfF === null ? 'gut' : 'fehler'] = $pfF ?? MkPlattform::ALLE[$pfP][0] . ' ist verbunden.';
        weiter('kanaele#pf-' . $pfP);

    case 'kanaele':   // P1 (01.10.2026): Kanäle verbinden — Stand, Prüfen, was nicht rausging
        require_once __DIR__ . '/src/MkKanaele.php';
        require_once __DIR__ . '/src/MetaSeite.php';
        require_once __DIR__ . '/src/MkLand.php';
        ansicht('kanaele', ['stand' => MkKanaele::stand(), 'fehl' => MkKanaele::fehlgeschlagen(MkLand::wahl()), 'geplant' => MkKanaele::geplant(),
            'me' => MetaSeite::einstellungen(),
            'ml' => (static function (): array { require_once __DIR__ . '/src/MetaLogin.php'; return MetaLogin::einstellungen(); })(),
            'pf' => (static function (): array { require_once __DIR__ . '/src/MkPlattform.php'; $a = []; foreach (array_keys(MkPlattform::ALLE) as $p) { $a[$p] = MkPlattform::einstellungen($p) + ['bereit' => MkPlattform::bereit($p)]; } return $a; })(),
            'handy' => sicher(static function (): array { if (!is_file(__DIR__ . '/src/MkHandy.php')) { return ['bereit' => false, 'text' => '']; } require_once __DIR__ . '/src/MkHandy.php'; return MkHandy::stand(); }, ['bereit' => false, 'text' => ''])]);
        break;

    case 'kanal-karte':   // QR-Aufsteller „Folgen Sie uns auf Telegram“ (01.10.2026, Uwe: „Alles“ — Vorschlag 7), Beitritte zählen für „qr“
        require_once __DIR__ . '/src/TelegramWachstum.php';
        require_once __DIR__ . '/src/QrBild.php';
        $kkKanal = Telegram::kanal();
        $kkName = preg_match('~t\.me/([A-Za-z0-9_]{4,64})~', (string) $kkKanal['link'], $kkM) ? $kkM[1] : '';
        if ($kkName === '') { $_SESSION['fehler'] = 'Erst den Kanal verbinden (Einstellungen › Telegram).'; weiter('telegram'); }
        header('Cache-Control: no-store');
        $kkQr = QrBild::svg(TelegramWachstum::kanalOrtLink('qr'), 300, 1);
        ?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Folgen Sie uns auf Telegram — Vecom Design</title>
<style>@page{size:A6;margin:8mm}body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;color:#141414;background:#fff}
.karte{display:grid;gap:3.5mm;justify-items:center;text-align:center;padding:6mm;max-width:105mm;margin:0 auto}
h1{font-size:21pt;margin:0;line-height:1.15}.de{font-size:14pt;color:#444;margin:0}.qr{width:58mm;height:58mm}.qr svg{width:100%;height:100%}
.name{font-size:13pt;font-weight:700;margin:0;color:#229ED9}.klein{font-size:10pt;color:#555;margin:0;line-height:1.4}.marke{font-weight:800;letter-spacing:.08em;font-size:12pt}
@media screen{body{background:#eee}.karte{background:#fff;margin:10mm auto;box-shadow:0 2px 14px rgba(0,0,0,.15)}}</style></head>
<body><div class="karte"><div class="marke">VECOM DESIGN</div><h1>Folgen Sie uns auf Telegram</h1><p class="de">Seguici su Telegram · Follow us on Telegram</p>
<div class="qr" role="img" aria-label="QR-Code zum Telegram-Kanal"><?= $kkQr ?></div>
<p class="name">@<?= Fmt::h($kkName) ?></p>
<p class="klein">Neuigkeiten, Beispiele und Tipps rund um Websites für Betriebe und Unternehmen — auf Deutsch, Italienisch und Englisch.<br>Novità, esempi e consigli sui siti web per attività e aziende.</p></div>
<script>window.addEventListener('load',function(){setTimeout(function(){window.print();},300);});</script></body></html><?php
        exit;

    case 'verzeichnisse':   // Telegram Growth Engine T5 (01.10.2026, Uwe: „ja“) — Verzeichnisse und Kooperationen
        require_once __DIR__ . '/src/Verzeichnisse.php';
        require_once __DIR__ . '/src/Telegram.php';
        Verzeichnisse::sicherstellen();
        ansicht('verzeichnisse', ['liste' => Verzeichnisse::liste(), 'offen' => (int) ($_GET['e'] ?? 0)]);
        break;

    case 'ausfuellen':   // Ausfüll-Knopf für Verzeichnisse (01.10.2026, Uwe: „per Knopfdruck“ → „ja“) — kleines Fenster, ohne Rahmen
        require_once __DIR__ . '/src/Verzeichnisse.php';
        Verzeichnisse::sicherstellen();
        $afHost = strtolower(trim((string) ($_GET['h'] ?? '')));
        $afOrigin = trim((string) ($_GET['o'] ?? ''));
        $afOk = Verzeichnisse::zielOk($afHost, $afOrigin);
        $afE = $afOk ? Verzeichnisse::fuerHost($afHost) : null;
        $afSp = in_array($_GET['sp'] ?? '', ['it', 'de', 'en'], true) ? (string) $_GET['sp'] : 'it';
        header('Cache-Control: no-store');
        require __DIR__ . '/views/ausfuellen.php';
        exit;

    case 'kampagnen':   // Kampagnen-Links (Growth Engine Phase 3, 30.09.2026, Uwe: „ja“)
        require_once __DIR__ . '/src/MkKennzahlen.php';
        require_once __DIR__ . '/src/MkKampagne.php';
        $mkZ = MkKennzahlen::zeitraum((string) ($_GET['z'] ?? '30'), (string) ($_GET['von'] ?? ''), (string) ($_GET['bis'] ?? ''));
        if ($id !== null) {
            $mkK = MkKampagne::laden($id);
            if ($mkK === null) { http_response_code(404); ansicht('spaeter', ['bereich' => 'unbekannt']); break; }
            $mkZahlen = MkKampagne::zahlen($mkZ[0], $mkZ[1]);
            ansicht('kampagne', [
                'z' => $mkZ, 'k' => $mkK, 'zahl' => $mkZahlen[$id] ?? [], 'jeWerbemittel' => MkKampagne::zahlen($mkZ[0], $mkZ[1], $id),
                'werbemittel' => MkKampagne::werbemittel($id), 'kosten' => MkKampagne::kosten($id),
                'kostenZeitraum' => MkKampagne::kostenJe($mkZ[0], $mkZ[1])[$id] ?? 0,
                'belege' => MkKampagne::freieBelege(), 'kontakte' => MkKampagne::kontakte($id),
                'nutzung' => MkKampagne::nutzung($id),
                'tgKampagne' => (static function () use ($mkK, $mkZ, $id) {   // Telegram Growth Engine T1
                    require_once __DIR__ . '/src/TelegramWachstum.php';
                    return ['bot' => TelegramWachstum::botLink($mkK), 'kanal' => Telegram::kanal(), 'einladung' => TelegramWachstum::einladung($id),
                            'zahl' => TelegramWachstum::kampagne($mkK, $mkZ[0], $mkZ[1])];
                })(),
            ]);
            break;
        }
        require_once __DIR__ . '/src/MkLand.php';
        $mkF = ['plattform' => (string) ($_GET['plattform'] ?? ''), 'status' => (string) ($_GET['status'] ?? ''), 'branche' => (string) ($_GET['branche'] ?? ''), 'land' => MkLand::wahl()];
        ansicht('kampagnen', ['z' => $mkZ, 'f' => $mkF, 'l' => MkKampagne::liste($mkZ[0], $mkZ[1], $mkF), 'offen' => MkLand::offen(),
            'leereZahl' => count(sicher(static fn() => MkKampagne::leere($mkF['land']), []))]);
        break;

    case 'zielgruppen':   // Marketing-Studio 1 und 5: Zielgruppen und Recherche auf einer Seite, je Land getrennt
        require_once __DIR__ . '/src/MkZielgruppe.php';
        require_once __DIR__ . '/src/MkLand.php';
        require_once __DIR__ . '/src/MkAuftrag.php';
        require_once __DIR__ . '/src/AkquiseSteuerung.php';
        if ($id !== null) {
            $mz = MkZielgruppe::laden($id);
            if ($mz === null) { http_response_code(404); ansicht('spaeter', ['bereich' => 'unbekannt']); break; }
            $_SESSION['mk_land'] = (string) $mz['land'];   // im Land der Zielgruppe bleiben, wenn man weiterklickt
            ansicht('zielgruppe', ['z' => $mz, 'daten' => MkZielgruppe::datengrundlage((string) $mz['branche'], (string) $mz['land']),
                'funde' => MkZielgruppe::recherche(['branche' => (string) $mz['branche'], 'land' => (string) $mz['land']], 20),
                'kampagnen' => sicher(static fn() => Db::all("SELECT id, name, code, status, plattform FROM mk_kampagnen WHERE partner_id IS NULL AND (zielgruppe_id = ? OR (branche = ? AND land = ?))
                                                               ORDER BY FIELD(status, 'aktiv', 'pausiert', 'beendet'), id DESC LIMIT 12", [$id, (string) $mz['branche'], (string) $mz['land']]), []),
                'inhalteZahl' => (int) sicher(static fn() => Db::wert("SELECT COUNT(*) FROM mk_inhalte WHERE zielgruppe_id = ? AND status <> 'verworfen'", [$id], 0), 0),
                'gegenstueck' => sicher(static fn() => Db::one("SELECT id, titel, status FROM mk_zielgruppen WHERE branche = ? AND land = ? AND (status = 'freigegeben' OR vorher IS NOT NULL)", [(string) $mz['branche'], MkLand::andere((string) $mz['land'])]), null),
                'kampagneLaeuft' => (bool) sicher(static fn() => Db::wert("SELECT COUNT(*) FROM mk_auftraege WHERE art = 'inhalte' AND status IN ('wartet','laeuft') AND parameter LIKE ?", ['%"zielgruppe_id":' . $id . ',%'], 0), false),
                /* S6: Landingpage der Zielgruppe und ihr letzter Auftrag */
                'mkSeite' => sicher(static function () use ($id): ?array { require_once __DIR__ . '/src/MkSeite.php'; return MkSeite::fuerZielgruppe($id); }, null),
                'mkSeiteAuftrag' => sicher(static fn() => Db::one("SELECT status, ergebnis, created_at FROM mk_auftraege WHERE art = 'seite' AND parameter = ? ORDER BY id DESC LIMIT 1", [json_encode(['zielgruppe_id' => $id])]) ?: null, null),
                'zahlen' => sicher(static function () use ($id, $mz): array {   // was die Kampagnen dieser Zielgruppe gebracht haben (30 Tage)
                    $z = MkKampagne::zahlen(date('Y-m-d', strtotime('-30 days')), date('Y-m-d'));
                    $s = MkKampagne::LEER;
                    foreach (Db::all('SELECT id FROM mk_kampagnen WHERE partner_id IS NULL AND (zielgruppe_id = ? OR (branche = ? AND land = ?))', [$id, (string) $mz['branche'], (string) $mz['land']]) as $r) {
                        foreach ($s as $k => $_) { $s[$k] += (int) ($z[(int) $r['id']][$k] ?? 0); }
                    }
                    return $s;
                }, MkKampagne::LEER),
                'pc' => sicher(static fn() => AkquiseSteuerung::stand(), ['pc_wach' => false, 'pc_alter' => null])]);
            break;
        }
        $mzLand = MkLand::wahl();
        $mrF = ['art' => (string) ($_GET['art'] ?? ''), 'branche' => (string) ($_GET['branche'] ?? ''), 'status' => (string) ($_GET['status'] ?? '')];
        ansicht('zielgruppen', ['land' => $mzLand, 'liste' => MkZielgruppe::alle($mzLand), 'fehlend' => MkZielgruppe::fehlend(8, $mzLand),
            'f' => $mrF, 'funde' => MkZielgruppe::recherche($mrF + ['land' => $mzLand], 60), 'offen' => MkLand::offen(),
            'auftraege' => sicher(static fn() => MkAuftrag::liste(6, ['recherche', 'uebersetzen'], $mzLand), []),
            'ohneDeutsch' => $mzLand === 'IT' ? (int) sicher(static fn() => MkZielgruppe::zahlOhneUebersetzung(), 0) : 0,
            'pc' => sicher(static fn() => AkquiseSteuerung::stand(), ['pc_wach' => false, 'pc_alter' => null])]);
        break;

    case 'recherche':   // seit Marketing-Studio 5 Teil von „Zielgruppen & Recherche“ — alte Links und Meldungen führen dorthin
        $mrQ = array_filter(['art' => (string) ($_GET['art'] ?? ''), 'branche' => (string) ($_GET['branche'] ?? ''), 'status' => (string) ($_GET['status'] ?? '')],
            static fn($v) => preg_match('/^[a-z_]{1,30}$/', $v) === 1);
        weiter('zielgruppen' . ($mrQ ? '?' . http_build_query($mrQ) : '') . '#funde');

    case 'freigabe':   // Freigabe-Stapel (Marketing-Studio 6, Uwe: „ja“ zu U3)
        require_once __DIR__ . '/src/MkVeroeffentlichen.php';
        require_once __DIR__ . '/src/MkLand.php';
        require_once __DIR__ . '/src/AkquiseSteuerung.php';
        $msLand = MkLand::wahl();
        $msX = MkInhalt::naechster($msLand, (array) ($_SESSION['mk_spaeter'] ?? []));
        ansicht('freigabe', ['land' => $msLand, 'x' => $msX, 'offen' => MkLand::offen(),
            'demos' => sicher(static function () use ($msLand): array { require_once __DIR__ . '/src/MkDemo.php'; return MkDemo::liste($msLand); }, []),   // Marketing-Studio 10
            'rest' => (int) Db::wert("SELECT COUNT(*) FROM mk_inhalte WHERE status = 'entwurf' AND land = ?", [$msLand], 0),
            'zg' => $msX && $msX['zielgruppe_id'] ? MkZielgruppe::laden((int) $msX['zielgruppe_id']) : null,
            'medien' => $msX ? sicher(static fn() => MkMedium::zuInhalt((int) $msX['id']), []) : [],
            'bildLaeuft' => $msX ? (int) sicher(static fn() => Db::wert("SELECT COUNT(*) FROM mk_auftraege WHERE art = 'medien' AND (status = 'laeuft' OR (status = 'wartet' AND parameter NOT LIKE '%\"drei_d\":true%')) AND parameter LIKE ?", ['%"inhalt_id":' . (int) $msX['id'] . ',%'], 0), 0) : 0,
            'dreiDWartet' => $msX ? (int) sicher(static fn() => Db::wert("SELECT COUNT(*) FROM mk_auftraege WHERE art = 'medien' AND status = 'wartet' AND parameter LIKE '%\"drei_d\":true%' AND parameter LIKE ?", ['%"inhalt_id":' . (int) $msX['id'] . ',%'], 0), 0) : 0,
            'geplant' => sicher(static fn() => Db::all("SELECT id, titel, plattform, land, geplant_am FROM mk_inhalte WHERE status = 'freigegeben' AND geplant_am IS NOT NULL ORDER BY geplant_am LIMIT 14"), []),
            'autopilot' => (static function () use ($msLand): array {   // Marketing-Studio 7
                require_once __DIR__ . '/src/MkAutopilot.php';
                return ['e' => MkAutopilot::einstellung($msLand), 'naechster' => MkAutopilot::naechsterLauf($msLand), 'zg' => sicher(static fn() => MkAutopilot::naechsteZielgruppe($msLand), null),
                        'telegram' => (int) sicher(static fn() => Db::wert('SELECT COUNT(*) FROM telegram_chats WHERE admin_verbunden IS NOT NULL', [], 0), 0) > 0];
            })()]);
        break;

    case 'demo':      // Demo-Vorschau ansehen, bevor du freigibst (Marketing-Studio 10) — dieselbe Sandbox wie öffentlich
        require_once __DIR__ . '/src/MkDemo.php';
        $dm = $id !== null ? MkDemo::laden($id) : null;
        if ($dm === null || trim((string) $dm['html']) === '') { http_response_code(404); ansicht('spaeter', ['bereich' => 'unbekannt']); break; }
        MkDemo::kopfzeilen();
        echo MkDemo::seite($dm, true);
        exit;

    case 'medien':    // Bilder und Videos (Schritt 3): nur angemeldet, nur über PHP
        require_once __DIR__ . '/src/MkMedium.php';
        $mm = $id !== null ? MkMedium::laden($id) : null;
        if ($mm === null) { http_response_code(404); ansicht('spaeter', ['bereich' => 'unbekannt']); break; }
        MkMedium::ausliefern($mm, isset($_GET['laden']));

    case 'inhalte':   // Content-Studio (Marketing-Studio Schritt 2, 01.10.2026)
        require_once __DIR__ . '/src/MkInhalt.php';
        require_once __DIR__ . '/src/MkAuftrag.php';
        require_once __DIR__ . '/src/AkquiseSteuerung.php';
        if ($id !== null) {
            $mi = MkInhalt::laden($id);
            if ($mi === null) { http_response_code(404); ansicht('spaeter', ['bereich' => 'unbekannt']); break; }
            if (isset($_GET['paket']) && in_array($mi['status'], ['freigegeben', 'veroeffentlicht'], true)) {
                require_once __DIR__ . '/src/MkVeroeffentlichen.php';
                MkVeroeffentlichen::paketSenden($mi);
            }
            require_once __DIR__ . '/src/MkMedium.php';
            ansicht('inhalt', ['x' => $mi, 'zg' => $mi['zielgruppe_id'] ? MkZielgruppe::laden((int) $mi['zielgruppe_id']) : null,
                'medien' => sicher(static fn() => MkMedium::zuInhalt($id), []),
                'medienAuftraege' => sicher(static fn() => Db::all("SELECT * FROM mk_auftraege WHERE art = 'medien' AND parameter LIKE ? ORDER BY id DESC LIMIT 6", ['%"inhalt_id":' . $id . ',%']), []),
                'pc' => sicher(static fn() => AkquiseSteuerung::stand(), ['pc_wach' => false, 'pc_alter' => null]),
                'kampagnen' => Db::all("SELECT id, name, code FROM mk_kampagnen WHERE status <> 'beendet' AND partner_id IS NULL ORDER BY id DESC LIMIT 60"),
                'funde' => $mi['fund_ids'] ? Db::all('SELECT id, art, titel FROM mk_recherche WHERE id IN (' . implode(',', array_map('intval', explode(',', (string) $mi['fund_ids']))) . ')') : []]);
            break;
        }
        require_once __DIR__ . '/src/MkLand.php';
        $miLand = MkLand::wahl();
        $miZgWahl = (int) ($_GET['zielgruppe'] ?? 0);
        if ($miZgWahl > 0 && ($miZgLand = Db::wert('SELECT land FROM mk_zielgruppen WHERE id = ?', [$miZgWahl], null)) !== null && $miZgLand !== $miLand && !isset($_GET['land'])) {
            $miLand = (string) $miZgLand; $_SESSION['mk_land'] = $miLand;   // Link von einer Zielgruppe: in deren Land wechseln
        }
        $miF = ['status' => (string) ($_GET['status'] ?? ''), 'art' => (string) ($_GET['art'] ?? ''), 'plattform' => (string) ($_GET['plattform'] ?? ''), 'zielgruppe' => $miZgWahl, 'land' => $miLand];
        ansicht('inhalte', ['f' => $miF, 'land' => $miLand, 'liste' => MkInhalt::liste($miF), 'zahl' => MkInhalt::zaehlen($miLand), 'offen' => MkLand::offen(),
            'zielgruppen' => Db::all("SELECT id, branche, land, titel, status FROM mk_zielgruppen WHERE (status = 'freigegeben' OR vorher IS NOT NULL) AND land = ? ORDER BY titel", [$miLand]),
            'alleZg' => Db::all('SELECT id, titel FROM mk_zielgruppen WHERE land = ? ORDER BY titel', [$miLand]),
            'kampagnen' => Db::all("SELECT id, name FROM mk_kampagnen WHERE status <> 'beendet' AND land IN (?, '') AND partner_id IS NULL ORDER BY id DESC LIMIT 60", [$miLand]),
            'auftraege' => sicher(static fn() => MkAuftrag::liste(6, 'inhalte', $miLand), []),
            'ohneDeutsch' => $miLand === 'IT' ? (int) sicher(static fn() => count(MkZielgruppe::ohneUebersetzung(100)['inhalte']), 0) : 0,
            'vorherNachher' => sicher(static function (): array { require_once __DIR__ . '/src/MkVorherNachher.php'; return MkVorherNachher::kandidaten(); }, []),
            'pc' => sicher(static fn() => AkquiseSteuerung::stand(), ['pc_wach' => false, 'pc_alter' => null])]);
        break;

    case 'tiktok':   // Bestätigungsseite je TikTok-Video (02.10.2026, TikTok Content Sharing Guidelines)
        require_once __DIR__ . '/src/MkInhalt.php';
        require_once __DIR__ . '/src/MkMedium.php';
        require_once __DIR__ . '/src/MkPlattform.php';
        $ttX = $id !== null ? MkInhalt::laden($id) : null;
        if ($ttX === null || $ttX['plattform'] !== 'tiktok' || !in_array($ttX['status'], ['freigegeben', 'veroeffentlicht'], true)) {
            http_response_code(404); ansicht('spaeter', ['bereich' => 'unbekannt']); break;
        }
        $ttIds = json_decode((string) ($ttX['post_ids'] ?? ''), true) ?: [];
        if (isset($_GET['stand'])) {
            header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
            echo json_encode(MkPlattform::ttStand((string) ($ttIds['tt'] ?? '')), JSON_UNESCAPED_UNICODE);
            exit;
        }
        $ttVideo = MkMedium::gewaehlt($id, 'video');
        $ttPfad = $ttVideo ? MkMedium::ordner() . '/' . basename((string) $ttVideo['datei']) : '';
        ansicht('tiktok', ['x' => $ttX, 'video' => $ttVideo, 'konto' => MkPlattform::ttKonto(),
            'dauer' => $ttPfad !== '' && is_file($ttPfad) ? MkPlattform::mp4Dauer($ttPfad) : null,
            'freigabe' => MkPlattform::einstellungen('tiktok')['freigabe'],
            'ki' => $ttVideo !== null && trim((string) ($ttVideo['modell'] ?? '')) !== '']);
        break;

    case 'statistiken':
        require_once __DIR__ . '/src/Statistik.php';
        require_once __DIR__ . '/src/Zugang.php';
        ansicht('statistiken', [
            'besuche' => Statistik::besuche(),
            'demos'   => Statistik::demos(),
            'trichter'=> sicher(static fn() => Zugang::trichter(), []),
        ]);
        break;

    default:
        http_response_code(404);
        ansicht('spaeter', ['bereich' => 'unbekannt']);
}
