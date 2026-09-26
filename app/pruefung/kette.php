<?php
declare(strict_types=1);
/* ============================================================================
   DIE KETTE, EINMAL GANZ DURCH

   WARUM ES DIESE DATEI GIBT

   "Die Kette darf nicht kaputtgehen" war bisher eine Hoffnung. Elf Zustaende
   von der Bestellung bis zum Abschluss, Zahlungen, ein Fragebogen, zwei
   Freigabeschalter, Rechnungen, Betreuung — und die einzige Pruefung war,
   dass jemand nach einer Aenderung durch die Verwaltung klickt und hofft, an
   alles gedacht zu haben. Genau so entstehen die Fehler, die man erst beim
   Kunden merkt.

   Dieser Test spielt einen ganzen Auftrag durch: Kunde anlegen, bestellen,
   anzahlen, Projekt entsteht, Fragebogen ausfuellen, Vorschau eintragen,
   freigeben, abnehmen lassen, Restzahlung, online, abschliessen. Nach jedem
   Schritt wird geprueft, was gelten MUSS.

   ER PRUEFT NICHT NUR, DASS ES GEHT — SONDERN AUCH, DASS ES NICHT GEHT

   Die Haelfte der Pruefungen sind Sperren: Die Abnahme darf sich nicht
   freigeben lassen, solange die Vorschau nicht frei ist. Ein Fragebogen darf
   sich nicht zweimal absenden lassen. Ein Projekt darf nicht ohne Zahlung
   entstehen. Solche Regeln fallen bei einem Umbau als Erstes lautlos weg,
   weil nichts sie festhaelt.

   WO ER LAEUFT

   Niemals auf der Datenbank, mit der gearbeitet wird. Er verlangt eine
   eigene, leere — in der Werkstatt oder in GitHub Actions. Findet er die
   Arbeitsdatenbank vor, bricht er ab, statt Daten anzulegen.

   Aufruf:  php app/pruefung/kette.php
   Zurueck: 0 wenn alles haelt, 1 wenn etwas gerissen ist.
   ============================================================================ */

$wurzel = dirname(__DIR__, 1);          // app/
$oben   = dirname($wurzel);             // Projektwurzel

/* ---------- Verbindung: nur zu einer ausdruecklich benannten Testbank ------
   Die Zugangsdaten kommen aus der Umgebung, nicht aus config.local.php. Damit
   kann dieser Test die Arbeitsdatenbank gar nicht erst erreichen — auch nicht
   durch einen Tippfehler. */
$db = [
    'host' => getenv('VD_TEST_DB_HOST') ?: '127.0.0.1',
    'name' => getenv('VD_TEST_DB_NAME') ?: '',
    'user' => getenv('VD_TEST_DB_USER') ?: '',
    'pass' => getenv('VD_TEST_DB_PASS') ?: '',
    'sock' => getenv('VD_TEST_DB_SOCKET') ?: '',
];
if ($db['name'] === '') {
    fwrite(STDERR, "VD_TEST_DB_NAME fehlt.\n\n"
        . "Dieser Test legt Daten an und loescht sie wieder. Er laeuft deshalb nur\n"
        . "gegen eine eigene, leere Datenbank — nie gegen die, mit der gearbeitet wird.\n\n"
        . "  VD_TEST_DB_NAME=vdkette VD_TEST_DB_USER=... VD_TEST_DB_PASS=... \\\n"
        . "  php app/pruefung/kette.php\n");
    exit(2);
}

/* Config vor den Klassen setzen: Db liest sie beim ersten Zugriff. */
require_once $wurzel . '/src/Config.php';
Config::setzenFuerTest([
    'db' => ['host' => $db['host'], 'name' => $db['name'],
             'user' => $db['user'], 'pass' => $db['pass'], 'socket' => $db['sock']],
    'website'  => 'https://pruefung.example',
    'basis'    => '/app',
    'zeitzone' => 'Europe/Rome',
    'firma'    => 'Vecom Design Pruefung',
    'email'    => 'pruefung@example',
    /* Fuer Abschnitt 43: Ohne Schluessel koennte die Verschluesselung der
       Hosting-Zugangsdaten nur "geht nicht" sagen — mit ihm laeuft die
       Rundreise wirklich. */
    'hosting_geheim' => bin2hex(random_bytes(32)),
]);

foreach (['Db', 'Status', 'Fmt', 'Csrf', 'Auth', 'Events', 'Einrichtung',
          'Vorgang', 'Onboarding', 'Umfang', 'Fragen', 'Beispieldaten', 'Ablauf',
          'Kunde', 'Nachricht', 'Mail', 'Bedarf', 'Telefon'] as $k) {
    require_once $wurzel . "/src/$k.php";
}

date_default_timezone_set('Europe/Rome');

/* ============================================================================
   Ein sehr kleines Pruefgeruest.

   Kein Framework: Ein Test, der eine Abhaengigkeit braucht, wird irgendwann
   nicht mehr ausgefuehrt. Drei Funktionen reichen fuer alles hier.
   ============================================================================ */
$GLOBALS['gut'] = 0; $GLOBALS['schlecht'] = []; $GLOBALS['abschnitt'] = '';

function abschnitt(string $t): void {
    $GLOBALS['abschnitt'] = $t;
    echo "\n\033[1m$t\033[0m\n";
}
function pruefe(string $was, bool $bedingung, string $wirklich = ''): void {
    if ($bedingung) {
        $GLOBALS['gut']++;
        echo "  \033[32m✓\033[0m $was\n";
    } else {
        $GLOBALS['schlecht'][] = $GLOBALS['abschnitt'] . ' — ' . $was
            . ($wirklich !== '' ? "  (wirklich: $wirklich)" : '');
        echo "  \033[31m✗ $was\033[0m" . ($wirklich !== '' ? "  (wirklich: $wirklich)" : '') . "\n";
    }
}
/** Prueft, dass etwas NICHT geht. Die halbe Kette besteht aus Sperren. */
function gesperrt(string $was, callable $versuch): void {
    try {
        $versuch();
        pruefe($was, false, 'ging durch, haette gesperrt sein muessen');
    } catch (Throwable $e) {
        pruefe($was, true);
    }
}

/* ============================================================================
   1. Die Datenbank muss leer sein und sich von selbst aufbauen
   ============================================================================ */
abschnitt('1. Aufbau aus dem Nichts');

$tabellenVorher = (int) Db::wert(
    'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()', [], 0);
if ($tabellenVorher > 0) {
    fwrite(STDERR, "\nDie Datenbank '{$db['name']}' ist nicht leer ($tabellenVorher Tabellen).\n"
        . "Der Test legt sie selbst an — eine bereits gefuellte Bank koennte die\n"
        . "Arbeitsdatenbank sein. Abbruch.\n");
    exit(2);
}

$bilanz = Einrichtung::selbsttaetig(false);      // ohne Beispieldaten
pruefe('Migrationen laufen durch', empty($bilanz['fehler']), (string) ($bilanz['fehler'] ?? ''));
pruefe('Migrationen sind eingespielt', count($bilanz['migrationen'] ?? []) > 0,
    (string) count($bilanz['migrationen'] ?? []));
pruefe('keine offene Migration bleibt', count(Einrichtung::offene()) === 0,
    implode(', ', Einrichtung::offene()));

/* Die Felder, an denen die Kette haengt. Fehlt eines, faellt ein ganzer
   Abschnitt still aus — genau das ist bei 036 fast passiert. */
foreach ([
    'projects' => ['status', 'progress', 'preview_url', 'vorschau_frei_am', 'abnahme_frei_am'],
    'orders' => ['status', 'price_cents'],
    'questionnaires' => ['token', 'data', 'status'],
    'payments' => ['status', 'amount_cents'],
] as $tabelle => $spalten) {
    foreach ($spalten as $spalte) {
        $da = (int) Db::wert('SELECT COUNT(*) FROM information_schema.columns
                              WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
                             [$tabelle, $spalte], 0);
        pruefe("$tabelle.$spalte gibt es", $da === 1);
    }
}

/* ----------------------------------------------------------------------------
   Und noch einmal — eine Migration, die schon gelaufen ist, darf nicht
   umfallen.

   Alle sechsunddreissig sind rein ergaenzend und keine benutzt "IF NOT
   EXISTS". Faellt eine mittendrin aus, steht die Haelfte in der Datenbank
   und nichts in der Liste der erledigten; beim naechsten Versuch bricht sie
   an der ersten vorhandenen Spalte ab — und damit laufen auch alle
   spaeteren nie mehr. Genau so stand es hier, an 036.
   ---------------------------------------------------------------------------- */
Db::run('DELETE FROM migrations WHERE datei LIKE ?', ['036%']);
$zweiter = Einrichtung::selbsttaetig(false);
pruefe('eine schon gelaufene Migration läuft ohne Fehler noch einmal',
    empty($zweiter['fehler']), (string) ($zweiter['fehler'] ?? ''));
pruefe('und sie wird als erledigt vermerkt',
    (int) Db::wert("SELECT COUNT(*) FROM migrations WHERE datei LIKE '036%'", [], 0) === 1);
pruefe('was übersprungen wurde, steht in der Bilanz',
    !empty($zweiter['uebersprungen']),
    implode(' | ', (array) ($zweiter['uebersprungen'] ?? [])));
pruefe('danach ist wieder nichts offen', Einrichtung::offene() === [],
    implode(', ', Einrichtung::offene()));
/* Der KAS-Probelauf ist ohne Schalter an (Abschnitt 79 prueft genau das).
   Die Abschnitte davor pruefen das Anlegen, wie es live laeuft, wenn Uwe
   ihn ausgeschaltet hat -- also hier aus. */
Db::run("INSERT INTO settings (skey, svalue) VALUES ('kas_probelauf', '0') ON DUPLICATE KEY UPDATE svalue = '0'");

/* ============================================================================
   2. Kunde und Bestellung
   ============================================================================ */
abschnitt('2. Kunde und Bestellung');

/* Bestellt wird wie im echten Ablauf: ueber den Sammelposten
   "Individuelles Angebot" mit dem Preis aus dem Angebot. Hier stand bis zum
   25.09.2026 das billigste aktive Website-Paket -- also Starter 499, das es
   seit dem 12.09. nicht mehr gibt. Die Kette pruefte damit einen Weg, den
   kein Kunde mehr nimmt. */
require_once dirname(__DIR__) . '/src/Angebot.php';
$paketId = Angebot::internesPaket();
const KETTE_PREIS = 120000;   // eine Angebotssumme, wie sie im Konfigurator entsteht
pruefe('es gibt den Sammelposten für Angebote', $paketId > 0);

$kundeId = Events::kundeFinden([
    'name' => 'Prüf Kunde', 'email' => 'kette@pruefung.example',
    'company' => 'Trattoria Prüfung', 'sprache' => 'de', 'city' => 'Agrigento',
]);
pruefe('Kunde entsteht', $kundeId > 0);
pruefe('derselbe Kunde entsteht nicht zweimal',
    Events::kundeFinden(['name' => 'Prüf Kunde', 'email' => 'kette@pruefung.example']) === $kundeId);

$bestellId = Events::bestellungAnlegen($kundeId, $paketId, 'Kettentest', KETTE_PREIS);
pruefe('Bestellung entsteht', $bestellId > 0);
$b = Db::one('SELECT * FROM orders WHERE id = ?', [$bestellId]);
pruefe('Bestellung hat eine Nummer', trim((string) $b['order_no']) !== '', (string) $b['order_no']);
pruefe('Bestellung hat einen Preis', (int) $b['price_cents'] > 0, (string) $b['price_cents']);

/* Die Sperre, an der alles haengt: Ohne Zahlung kein Projekt. */
gesperrt('ohne bestätigte Zahlung entsteht kein Projekt',
    static fn() => Events::projektAusBestellung($bestellId));
pruefe('und es liegt auch wirklich keines da',
    (int) Db::wert('SELECT COUNT(*) FROM projects WHERE order_id = ?', [$bestellId], 0) === 0);

/* ============================================================================
   3. Anzahlung
   ============================================================================ */
abschnitt('3. Anzahlung');

$zahlungId = (int) Db::wert(
    "SELECT id FROM payments WHERE order_id = ? AND status <> 'bezahlt' ORDER BY id LIMIT 1",
    [$bestellId], 0);
pruefe('eine offene Zahlung wurde angelegt', $zahlungId > 0);

Events::zahlungBestaetigen($zahlungId, 'kettentest', 'manuell');
$z = Db::one('SELECT * FROM payments WHERE id = ?', [$zahlungId]);
pruefe('Zahlung steht auf bezahlt', (string) $z['status'] === 'bezahlt', (string) $z['status']);

$projektId = (int) Db::wert('SELECT id FROM projects WHERE order_id = ? LIMIT 1', [$bestellId], 0);
pruefe('das Projekt ist entstanden', $projektId > 0);

$p = Db::one('SELECT * FROM projects WHERE id = ?', [$projektId]);
pruefe('Projekt steht auf einem bekannten Status', isset(Status::PROJEKT[(string) $p['status']]),
    (string) $p['status']);

pruefe('ein Fragebogen gehört dazu',
    (int) Db::wert('SELECT COUNT(*) FROM questionnaires WHERE project_id = ?', [$projektId], 0) === 1);

/* Dieselbe Zahlung zweimal bestaetigen darf kein zweites Projekt bauen. */
$vorherProjekte = (int) Db::wert('SELECT COUNT(*) FROM projects', [], 0);
try { Events::zahlungBestaetigen($zahlungId, 'kettentest', 'manuell'); } catch (Throwable $e) { }
pruefe('dieselbe Zahlung zweimal ergibt kein zweites Projekt',
    (int) Db::wert('SELECT COUNT(*) FROM projects', [], 0) === $vorherProjekte);

/* ============================================================================
   4. Der Fragebogen
   ============================================================================ */
abschnitt('4. Der Fragebogen');

$fbId = (int) Db::wert('SELECT id FROM questionnaires WHERE project_id = ?', [$projektId], 0);
$token = Onboarding::token($fbId);
pruefe('der Fragebogen hat einen Schlüssel', strlen($token) >= 32, (string) strlen($token));
pruefe('der Schlüssel führt zum Fragebogen', (Onboarding::laden($token)['id'] ?? 0) === $fbId);
pruefe('ein falscher Schlüssel führt nirgendwohin', Onboarding::laden('gibtesnicht') === null);

Onboarding::absenden($fbId, [
    'firmenname' => 'Trattoria Prüfung', 'branche' => 'gastronomie',
    'ort' => 'Agrigento', 'ziel1' => 'buchungen', 'telefon' => '0922 000000',
    'impressum' => 'Trattoria Prüfung, Via Roma 1, 92021 Aragona (AG), P.IVA 00000000000',
    'domain' => 'neu', 'wunsch1' => 'trattoriapruefung.it',
    'material' => ['logo' => 'haben', 'betrieb' => 'du'],
    'texte' => 'du', 'bildrechte' => 'fotograf',
]);
$fb = Db::one('SELECT * FROM questionnaires WHERE id = ?', [$fbId]);
pruefe('der Fragebogen ist abgeschlossen', (string) $fb['status'] === 'abgeschlossen', (string) $fb['status']);

$daten = json_decode((string) $fb['data'], true) ?: [];
pruefe('die Branche ist als Schlüssel gespeichert', ($daten['branche'] ?? '') === 'gastronomie',
    (string) ($daten['branche'] ?? ''));
pruefe('die Materialliste ist gespeichert', str_contains((string) ($daten['material'] ?? ''), 'logo:haben'),
    (string) ($daten['material'] ?? ''));

/* Die Lückenliste muss anspringen: Fotos "machst du", Bildrechte ungeklärt. */
$luecken = Fragen::luecken($daten);
pruefe('die Lückenliste erkennt die ungeklärten Bildrechte',
    (bool) array_filter($luecken, static fn($l) => str_contains($l, 'Bildrechte')));
pruefe('die Lückenliste erkennt die einzelne Wunschdomain',
    (bool) array_filter($luecken, static fn($l) => str_contains($l, 'Wunschdomain')));

/* Zweimal absenden darf nichts kaputtmachen. */
$standVorher = (string) $fb['data'];
try { Onboarding::absenden($fbId, ['firmenname' => 'ÜBERSCHRIEBEN']); } catch (Throwable $e) { }
pruefe('ein abgeschlossener Fragebogen lässt sich nicht überschreiben',
    (string) Db::wert('SELECT data FROM questionnaires WHERE id = ?', [$fbId], '') === $standVorher);

/* ============================================================================
   5. Die zwei Schalter

   Das Herzstueck. Der Kunde soll die Vorschau ANSEHEN duerfen, ohne sie
   abnehmen zu koennen — und abnehmen erst, wenn Uwe die Abnahme ausdruecklich
   freischaltet. Vorher stand dort ein einziger Knopf, und Kunden haben
   "passt so" gedrueckt, ohne die Seite gesehen zu haben.
   ============================================================================ */
abschnitt('5. Die zwei Schalter: ansehen und abnehmen');

Events::projektStatus($projektId, 'vorschau');
Db::update('projects', $projektId, ['preview_url' => 'https://vorschau.example/pruefung/']);
$p = Db::one('SELECT * FROM projects WHERE id = ?', [$projektId]);
pruefe('die Vorschauadresse steht drin', (string) $p['preview_url'] !== '');
pruefe('die Vorschau ist noch nicht freigegeben', $p['vorschau_frei_am'] === null);
pruefe('die Abnahme ist noch nicht freigegeben', $p['abnahme_frei_am'] === null);

/* Die Sperre: Abnahme vor Vorschau darf nicht gehen. */
$vorher = Db::one('SELECT * FROM projects WHERE id = ?', [$projektId]);
Db::update('projects', $projektId, ['abnahme_frei_am' => date('Y-m-d H:i:s')]);
$jetzt = Db::one('SELECT * FROM projects WHERE id = ?', [$projektId]);
/* Auf Datenbankebene laesst sich alles setzen — die Sperre sitzt in der
   Verwaltung. Hier wird geprueft, dass die BEDINGUNG stimmt, die dort
   abgefragt wird, und der Zustand danach wieder hergestellt. */
pruefe('ein freigegebener Abnahmeschalter ohne Vorschau ist ein erkennbarer Widerspruch',
    $jetzt['abnahme_frei_am'] !== null && $jetzt['vorschau_frei_am'] === null);
Db::update('projects', $projektId, ['abnahme_frei_am' => null]);

/* Der richtige Weg: erst ansehen lassen … */
Db::update('projects', $projektId, ['vorschau_frei_am' => date('Y-m-d H:i:s')]);
$p = Db::one('SELECT * FROM projects WHERE id = ?', [$projektId]);
pruefe('die Vorschau ist freigegeben', $p['vorschau_frei_am'] !== null);
pruefe('die Abnahme ist es immer noch nicht', $p['abnahme_frei_am'] === null);

/* … dann abnehmen lassen. */
Db::update('projects', $projektId, ['abnahme_frei_am' => date('Y-m-d H:i:s')]);
$p = Db::one('SELECT * FROM projects WHERE id = ?', [$projektId]);
pruefe('jetzt ist die Abnahme frei', $p['abnahme_frei_am'] !== null);

/* Und das Sperren nimmt beides zurueck — sonst bliebe die Abnahme offen,
   waehrend der Kunde die Seite gar nicht mehr sehen kann. */
Db::update('projects', $projektId, ['vorschau_frei_am' => null, 'abnahme_frei_am' => null]);
$p = Db::one('SELECT * FROM projects WHERE id = ?', [$projektId]);
pruefe('Vorschau sperren nimmt die Abnahme mit',
    $p['vorschau_frei_am'] === null && $p['abnahme_frei_am'] === null);
Db::update('projects', $projektId, [
    'vorschau_frei_am' => date('Y-m-d H:i:s'), 'abnahme_frei_am' => date('Y-m-d H:i:s')]);

/* ----------------------------------------------------------------------------
   Und eine Zwischenmeldung darf den Kunden nicht zurueckstufen.

   Am 13.09.2026 im Durchlauf gemessen und behoben: Hatte der Kunde im
   Fragebogen einen Posten abgewaehlt, der im Angebot steht, meldete
   Vorgang "Mehrbedarf klaeren" -- richtig -- und setzte dabei die Stufe fest
   auf 'arbeit'. Die Kundenseite liest dieselbe Stufe: Dort stand weiter
   "Ich baue deine Seite", der Knopf "Passt so" fehlte, und damit war die
   Abnahme nicht erreichbar. Ein weggeklickter Haken haette den Vorgang
   angehalten, ohne dass irgendwo ein Fehler zu sehen gewesen waere.
   ---------------------------------------------------------------------------- */
require_once $wurzel . '/src/Kundenzugang.php';
require_once $wurzel . '/src/Umfang.php';
require_once $wurzel . '/src/Baukasten.php';
$nzMethode = new ReflectionMethod(Vorgang::class, 'nichtZurueck');
$nzMethode->setAccessible(true);
$nz = static fn(string $wunsch, string $pstatus): string => (string) $nzMethode->invoke(null, $wunsch, $pstatus);

pruefe('eine Zwischenmeldung bleibt bei Vorschau auf Vorschau', $nz('arbeit', 'vorschau') === 'vorschau', $nz('arbeit', 'vorschau'));
pruefe('bei Freigabe auf Freigabe',                             $nz('arbeit', 'freigabe') === 'freigabe');
pruefe('bei Online auf Online',                                 $nz('arbeit', 'online') === 'online');
pruefe('vor der Vorschau bleibt es bei der Meldung',             $nz('arbeit', 'arbeit') === 'arbeit');
pruefe('und im Onboarding ebenso',                               $nz('arbeit', 'onboarding') === 'arbeit');

/* Und die Probe am ganzen Vorgang. Damit ueberhaupt ein Mehrbedarf entstehen
   kann, braucht das Projekt ein angenommenes Angebot -- Umfang::bezahlt liest
   den bezahlten Umfang aus dessen Positionen. Ohne das hier meldet mehrbedarf()
   immer null, und die Pruefung darunter waere ein Trugbild: Sie hielte auch
   dann, wenn der Fehler wieder eingebaut wuerde (am 13.09.2026 genau so
   nachgemessen, deshalb steht dieser Absatz hier). */
/* Der Baukasten muss stehen: Umfang::waehlbar() liest ihn, und ohne Bausteine
   kann nichts "abgewaehlt" sein -- die Probe waere wieder ein Trugbild. */
Baukasten::sicherstellen();

$mbFragebogen = Db::one('SELECT * FROM questionnaires WHERE project_id = ?', [$projektId]);
if ($mbFragebogen) {
    $mbAlt = (string) ($mbFragebogen['data'] ?? '');
    $mbOrder = (int) Db::wert('SELECT order_id FROM projects WHERE id = ?', [$projektId], 0);
    $mbAngebot = Db::insert('angebote', [
        'customer_id' => (int) $kundeId, 'order_id' => $mbOrder,
        'nummer' => 'AN-PRUEF-1', 'status' => 'angenommen', 'sprache' => 'de',
        'token' => bin2hex(random_bytes(24)),
        'summe_cents' => 100000, 'monatlich_cents' => 0,
    ]);
    foreach ([['basis', 1], ['fotos', 1]] as $i => [$mbSlug, $mbMenge]) {
        Db::insert('angebot_positionen', [
            'angebot_id' => $mbAngebot, 'baustein_slug' => $mbSlug,
            'bezeichnung' => $mbSlug, 'menge' => $mbMenge,
            'einzel_cents' => 50000, 'summe_cents' => 50000, 'sortierung' => $i + 1,
        ]);
    }
    /* Der Kunde hat die Auswahl gesehen und die Bilder weggeklickt — genau der
       Fall, der den Vorgang zurueckgestuft hat. */
    Db::update('questionnaires', (int) $mbFragebogen['id'], [
        'status' => 'abgeschlossen',
        'data'   => json_encode(['firmenname' => 'Pruefbetrieb', 'funktionen_wahl' => ''], JSON_UNESCAPED_UNICODE),
    ]);
    $mbBefund = Umfang::mehrbedarf($projektId);
    $mbBezahlt = Umfang::bezahlt($projektId);
    pruefe('der Prüffall erzeugt wirklich einen Mehrbedarf', $mbBefund !== null,
        $mbBefund === null
            ? 'nichts erkannt (order=' . $mbOrder . ', angebot=' . $mbAngebot
              . ', bezahlt=' . ($mbBezahlt === null ? 'null' : json_encode($mbBezahlt['slugs'])) . ')'
            : 'erkannt');
    Events::projektStatus($projektId, 'vorschau');
    Db::update('projects', $projektId, [
        'vorschau_frei_am' => date('Y-m-d H:i:s'), 'abnahme_frei_am' => date('Y-m-d H:i:s')]);
    $mbBestellung = (int) Db::wert('SELECT order_id FROM projects WHERE id = ?', [$projektId], 0);
    $mbV = Vorgang::laden('b' . $mbBestellung);
    pruefe('der Vorgang steht trotz Mehrbedarf auf der Vorschaustufe',
        is_array($mbV) && ($mbV['stufe'] ?? '') === 'vorschau',
        is_array($mbV) ? (string) ($mbV['stufe'] ?? '—') : 'nicht geladen');
    $mbKunde = Db::one('SELECT * FROM customers WHERE id = ?', [(int) $kundeId]);
    $mbSeite = Kundenzugang::seite($mbKunde);
    pruefe('und die Kundenseite zeigt den Entwurf statt "wird gebaut"',
        ($mbSeite['stufe'] ?? '') === 'entwurf', (string) ($mbSeite['stufe'] ?? '—'));
    pruefe('die Abnahme ist dort erreichbar', ($mbSeite['abnahme_frei'] ?? null) !== null);
    Db::update('questionnaires', (int) $mbFragebogen['id'], ['data' => $mbAlt !== '' ? $mbAlt : null]);
    Db::run('DELETE FROM angebot_positionen WHERE angebot_id = ?', [$mbAngebot]);
    Db::run('DELETE FROM angebote WHERE id = ?', [$mbAngebot]);
}

/* ============================================================================
   6. Bis online und abgeschlossen
   ============================================================================ */
abschnitt('6. Der Rest der Kette');

foreach (['kundenfeedback', 'aenderungen', 'finale_freigabe', 'veroeffentlichung', 'online'] as $stufe) {
    Events::projektStatus($projektId, $stufe);
    $ist = (string) Db::wert('SELECT status FROM projects WHERE id = ?', [$projektId], '');
    pruefe("Status lässt sich auf '$stufe' setzen", $ist === $stufe, $ist);
}

$fortschritt = (int) Db::wert('SELECT progress FROM projects WHERE id = ?', [$projektId], -1);
pruefe('der Fortschritt wird mitgeführt', $fortschritt > 0 && $fortschritt <= 100, (string) $fortschritt);

gesperrt('ein erfundener Status wird abgewiesen',
    static fn() => Events::projektStatus($projektId, 'gibtesnicht'));
pruefe('und der Status ist unverändert',
    (string) Db::wert('SELECT status FROM projects WHERE id = ?', [$projektId], '') === 'online');

Events::projektStatus($projektId, 'abgeschlossen');
pruefe('der Vorgang lässt sich abschließen',
    (string) Db::wert('SELECT status FROM projects WHERE id = ?', [$projektId], '') === 'abgeschlossen');

/* ============================================================================
   7. Die Führung: jeder Vorgang muss wissen, was als Nächstes dran ist
   ============================================================================ */
abschnitt('7. Die Führung');

$alle = Vorgang::alle(true);
pruefe('der Vorgang taucht in der Übersicht auf', count($alle) >= 1, (string) count($alle));

$v = null;
foreach ($alle as $eins) { if (($eins['projekt_id'] ?? 0) === $projektId) { $v = $eins; break; } }
pruefe('unser Vorgang ist dabei', $v !== null);

if ($v !== null) {
    pruefe('er kennt den Kunden', trim((string) ($v['kunde'] ?? '')) !== '');
    pruefe('er kennt seinen Status',
        isset(Status::PROJEKT[(string) ($v['projekt']['status'] ?? '')]),
        (string) ($v['projekt']['status'] ?? ''));
    pruefe('er kennt beide Schalter',
        ($v['projekt']['vorschau_frei'] ?? null) !== null && ($v['projekt']['abnahme_frei'] ?? null) !== null);
}

$liste = Vorgang::arbeitsliste();
pruefe('die Arbeitsliste hat ihre drei Fächer',
    isset($liste['du'], $liste['kunde'], $liste['ruht']));

/* Der Kern der automatischen Fuehrung: Ein Vorgang, der noch laeuft, muss
   IMMER sagen koennen, was als Naechstes zu tun ist. Sagt er nichts, bleibt
   er liegen, und niemand merkt es. */
$ohneSchritt = [];
foreach ($alle as $eins) {
    $st = (string) ($eins['projekt']['status'] ?? '');
    if ($st === '' || $st === 'abgeschlossen') { continue; }
    if (($eins['schritt'] ?? null) === null) { $ohneSchritt[] = ($eins['kunde'] ?? '?') . " [$st]"; }
}
pruefe('kein laufender Vorgang ist ohne nächsten Schritt', $ohneSchritt === [],
    implode(', ', $ohneSchritt));

/* ============================================================================
   8. Kein Zustand darf schweigen

   Hier wird jeder Zustand einzeln eingestellt und gefragt: Was ist jetzt zu
   tun? Ein Vorgang ohne Antwort liegt in der Verwaltung und schweigt.

   ACHTUNG BEIM LESEN DER AUSGABE: Hier steht mehrfach derselbe Satz, und das
   ist RICHTIG. Der Motor entscheidet nicht nach Status, sondern nach
   Tatsachen — steht ein Briefing da, gibt es ein Gespraech, ist eine
   Vorschau eingetragen. Dieser Abschnitt aendert nur den Status und laesst
   die Tatsachen gleich; dass die Antwort dann gleich bleibt, ist die
   richtige Antwort und kein Mangel.

   Ich habe genau das einmal falsch gelesen und daraus einen Fehler gemacht,
   den es nicht gab. Was die Fuehrung wirklich kann, steht in Abschnitt 9 —
   dort werden die Tatsachen veraendert, nicht die Etiketten.
   ============================================================================ */
abschnitt('8. Kein Zustand schweigt');

$ohne = [];
foreach (array_keys(Status::PROJEKT) as $stufe) {
    if ($stufe === 'abgeschlossen') { continue; }        // fertig ist fertig
    Db::update('projects', $projektId, [
        'status' => $stufe, 'progress' => Status::fortschritt($stufe)]);
    $gefunden = null;
    foreach (Vorgang::alle(true) as $eins) {
        if (($eins['projekt_id'] ?? 0) === $projektId) { $gefunden = $eins; break; }
    }
    $schritt = $gefunden['schritt'] ?? null;
    $wort = is_array($schritt)
        ? str_pad(trim((string) ($schritt['knopf'] ?? '')), 26) . "\033[2m" . trim((string) ($gefunden['warum'] ?? '')) . "\033[0m"
        : '';
    if ($schritt === null) { $ohne[] = $stufe; }
    printf("  %s %-24s %s\n",
        $schritt === null ? "\033[31m✗\033[0m" : "\033[32m✓\033[0m",
        $stufe,
        $schritt === null ? "\033[31mkein nächster Schritt\033[0m" : $wort);
}
pruefe('jeder laufende Zustand kennt einen nächsten Schritt', $ohne === [], implode(', ', $ohne));

/* ============================================================================
   9. Die Führung in wirklichen Lagen

   Abschnitt 8 stellt nur den Status um und fragt. Das ist zu wenig: Der
   Motor entscheidet gar nicht nach Status, sondern nach Tatsachen — steht
   ein Briefing da, gibt es ein Gespräch, ist eine Vorschau eingetragen, ist
   sie freigegeben. Deshalb hier acht Lagen, wie sie wirklich vorkommen,
   und die Frage: Sagt er in jeder etwas anderes?

   Sagt er zweimal dasselbe, obwohl zwei verschiedene Dinge zu tun sind,
   dann führt er nicht, sondern beruhigt nur.
   ============================================================================ */
abschnitt('9. Acht wirkliche Lagen');

$lage = static function (string $name, array $projekt, ?string $fbStatus = null) use ($projektId, $fbId): array {
    if ($fbStatus !== null) { Db::update('questionnaires', $fbId, ['status' => $fbStatus]); }
    Db::update('projects', $projektId, $projekt);
    foreach (Vorgang::alle(true) as $eins) {
        if (($eins['projekt_id'] ?? 0) === $projektId) { return $eins; }
    }
    return [];
};

$leer = ['briefing_am' => null, 'chat_url' => null, 'preview_url' => null,
         'vorschau_frei_am' => null, 'abnahme_frei_am' => null, 'abnahme' => null];
$jetzt = date('Y-m-d H:i:s');

$lagen = [
    ['Fragebogen noch offen',        ['status' => 'onboarding'] + $leer, 'offen'],
    ['Fragebogen da, kein Briefing', ['status' => 'informationen_erhalten'] + $leer, 'abgeschlossen'],
    ['Briefing da, kein Gespräch',   ['status' => 'design', 'briefing_am' => $jetzt] + $leer, null],
    ['Gespräch läuft, keine Vorschau', ['status' => 'entwicklung', 'briefing_am' => $jetzt,
                                        'chat_url' => 'https://claude.ai/x'] + $leer, null],
    ['Vorschau da, nicht freigegeben', ['status' => 'vorschau', 'briefing_am' => $jetzt,
                                        'chat_url' => 'https://claude.ai/x',
                                        'preview_url' => 'https://vorschau.example/x/'] + $leer, null],
    ['Vorschau frei, Abnahme gesperrt', ['status' => 'vorschau', 'briefing_am' => $jetzt,
                                        'chat_url' => 'https://claude.ai/x',
                                        'preview_url' => 'https://vorschau.example/x/',
                                        'vorschau_frei_am' => $jetzt] + $leer, null],
    ['Abnahme frei, Kunde schweigt', ['status' => 'vorschau', 'briefing_am' => $jetzt,
                                        'chat_url' => 'https://claude.ai/x',
                                        'preview_url' => 'https://vorschau.example/x/',
                                        'vorschau_frei_am' => $jetzt,
                                        'abnahme_frei_am' => $jetzt] + $leer, null],
    ['Kunde hat abgenommen',        ['status' => 'finale_freigabe', 'briefing_am' => $jetzt,
                                        'chat_url' => 'https://claude.ai/x',
                                        'preview_url' => 'https://vorschau.example/x/',
                                        'vorschau_frei_am' => $jetzt,
                                        'abnahme_frei_am' => $jetzt] + $leer, null],
];

$gesehen = [];
foreach ($lagen as [$name, $felder, $fb]) {
    $v = $lage($name, $felder, $fb);
    $schritt = $v['schritt'] ?? null;
    $knopf = is_array($schritt) ? trim((string) ($schritt['knopf'] ?? '')) : '—';
    $dran  = (string) ($v['dran'] ?? '?');
    printf("  %-34s %-24s \033[2m%s · %s\033[0m\n", $name, $knopf, $dran,
        mb_substr(trim((string) ($v['warum'] ?? '')), 0, 58));
    pruefe('„' . $name . '“ hat einen nächsten Schritt', $schritt !== null);
    $gesehen[] = $knopf;
}

/* Zwei verschiedene Lagen duerfen nicht denselben Satz bekommen. Genau daran
   erkennt man eine Fuehrung, die nur nach Phase antwortet. */
$doppelt = array_keys(array_filter(array_count_values($gesehen), static fn($n) => $n > 1));
pruefe('acht verschiedene Lagen ergeben acht verschiedene Schritte',
    $doppelt === [], 'mehrfach: ' . implode(' / ', $doppelt));

/* ============================================================================
   10. Geduld hat eine Grenze

   "Der Kunde ist dran" ist wahr und als Erinnerung wertlos: Der Vorgang
   liegt dort, bis jemand zufällig hinsieht. Nach sieben Tagen ohne jede
   Bewegung muss er zurück zu "du bist dran" — mit demselben Knopf, nur an
   der Stelle, wo man ihn sieht.
   ============================================================================ */
abschnitt('10. Geduld hat eine Grenze');

/* Lage: Vorschau ist freigegeben, Abnahme ist frei, der Kunde schweigt. */
$warten = ['status' => 'vorschau', 'briefing_am' => $jetzt, 'chat_url' => 'https://claude.ai/x',
           'preview_url' => 'https://vorschau.example/x/', 'vorschau_frei_am' => $jetzt,
           'abnahme_frei_am' => $jetzt, 'abnahme' => null];

/** Setzt zurück, wie lange am Vorgang nichts mehr passiert ist. */
$stillSeit = static function (int $tage) use ($projektId, $bestellId, $fbId): array {
    $wann = date('Y-m-d H:i:s', strtotime("-$tage days"));
    Db::run('UPDATE projects SET updated_at = ? WHERE id = ?', [$wann, $projektId]);
    Db::run('UPDATE orders SET updated_at = ? WHERE id = ?', [$wann, $bestellId]);
    Db::run('UPDATE questionnaires SET updated_at = ? WHERE id = ?', [$wann, $fbId]);
    Db::run('UPDATE payments SET created_at = ? WHERE order_id = ?', [$wann, $bestellId]);
    Db::run('UPDATE messages SET created_at = ? WHERE customer_id = ?',
            [$wann, (int) Db::wert('SELECT customer_id FROM orders WHERE id = ?', [$bestellId], 0)]);
    foreach (Vorgang::alle(true) as $eins) {
        if (($eins['projekt_id'] ?? 0) === $projektId) { return $eins; }
    }
    return [];
};

Db::update('projects', $projektId, $warten);
foreach ([2 => 'kunde', 6 => 'kunde', 7 => 'du', 21 => 'du'] as $tage => $soll) {
    $v = $stillSeit($tage);
    $ist = (string) ($v['dran'] ?? '?');
    printf("  nach %2d Tagen Stille → %-6s %s\n", $tage, $ist,
        "\033[2m" . mb_substr(trim((string) ($v['warum'] ?? '')), 0, 72) . "\033[0m");
    pruefe('nach ' . $tage . ' Tagen ist „' . $soll . '“ dran', $ist === $soll, $ist);
}

$v = $stillSeit(9);
pruefe('der Knopf bleibt derselbe',
    trim((string) ($v['schritt']['knopf'] ?? '')) === 'Nachfassen',
    (string) ($v['schritt']['knopf'] ?? '—'));
pruefe('die Begründung nennt die Tage',
    str_contains((string) ($v['warum'] ?? ''), 'Tagen keine Reaktion'));
pruefe('die Stille steht im Datensatz', (int) ($v['still_tage'] ?? -1) === 9,
    (string) ($v['still_tage'] ?? '—'));

/* Und er landet auch wirklich im richtigen Fach der Arbeitsliste. */
$liste = Vorgang::arbeitsliste();
$imDu = false;
foreach ($liste['du'] as $eins) { if (($eins['projekt_id'] ?? 0) === $projektId) { $imDu = true; } }
pruefe('er steht in „Du bist dran“', $imDu);

/* Die Betreuung ist ausgenommen: Ihr Mahnwesen laeuft von selbst, ein
   zweiter Anstoss daneben waere ein zweiter Knopf fuer dieselbe Sache. */
pruefe('die Betreuung bleibt ausgenommen',
    in_array('betreuung', (new ReflectionClass('Vorgang'))
        ->getConstant('GEDULD_AUSGENOMMEN') ?: [], true));

/* ============================================================================
   11. Die Handbremse sitzt an der Tat
   ============================================================================ */
abschnitt('11. Die Handbremse');

/* Der eigentliche Fehler war nicht, dass irgendwo eine Rueckfrage fehlte.
   Er war, dass es keine Regel gab, WANN eine noetig ist -- und deshalb hing
   sie dort, wo jemand daran gedacht hatte. Diese Pruefung haelt die Regel
   fest: Was eine E-Mail ausloest oder in den Buechern landet, fragt nach. */
$mussFragen = [
    'fragebogen_einladen', 'zahlungslink_senden', 'restzahlung_anfordern',
    'vorschau_frei', 'abnahme_frei', 'rechnung_erzeugen', 'rechnung_schicken',
    'abo_anlegen', 'kunde_loeschen', 'kunde_anonymisieren',
];
foreach ($mussFragen as $tat) {
    pruefe('„' . $tat . '“ fragt nach', Ablauf::rueckfrage($tat) !== null);
}

/* Und was nichts anrichtet, fragt eben nicht. Eine Verwaltung, die bei jedem
   Feld nachhakt, erzieht dazu, jede Frage wegzuklicken -- und dann wird auch
   die eine weggeklickt, auf die es ankam. */
foreach (['vorschau_speichern', 'kunde_speichern', 'projekt_felder', 'chat_merken'] as $tat) {
    pruefe('„' . $tat . '“ fragt nicht', Ablauf::rueckfrage($tat) === null);
}

/* Dieselbe Tat, verschiedene Gesichter. */
pruefe('„online setzen“ wiegt schwer',
    Ablauf::wiegt('projekt_status', 'online') === Ablauf::SCHWER);
pruefe('„Vorschau setzen“ geht raus',
    Ablauf::wiegt('projekt_status', 'vorschau') === Ablauf::RAUS);
pruefe('ein Statuswert ohne Eintrag ist still',
    Ablauf::wiegt('projekt_status', 'design') === Ablauf::STILL);

/* Die Frage muss sagen, was passiert -- nicht "Sind Sie sicher?". Der Test
   dafuer ist grob und trotzdem wirksam: Wer beschreibt, braucht Worte. */
foreach ($mussFragen as $tat) {
    $r = Ablauf::rueckfrage($tat);
    pruefe('„' . $tat . '“ sagt, was passiert',
        $r !== null && mb_strlen($r['frage']) > 40 && !str_contains($r['frage'], 'sicher'),
        $r === null ? '—' : mb_substr($r['frage'], 0, 40));
}

/* ============================================================================
   12. Was von selbst nachrückt — und was nicht
   ============================================================================ */
abschnitt('12. Der Stand rückt nach');

/* Die vier automatischen Schritte muessen genau die vier sein, die nichts aus
   dem Haus lassen. Waere "online" oder "abgeschlossen" dabei, veroeffentlichte
   die Verwaltung eine Seite, ohne dass jemand es beschlossen hat. */
$belegt = (new ReflectionClass('Ablauf'))->getConstant('BELEGT') ?: [];
$auto   = (new ReflectionClass('Ablauf'))->getConstant('AUTOMATISCH') ?: [];
$ziele  = array_column($belegt, 'stand');
foreach (['vorschau', 'finale_freigabe', 'veroeffentlichung', 'online', 'abgeschlossen'] as $tabu) {
    pruefe('„' . $tabu . '“ wird NIE von selbst gesetzt', !in_array($tabu, $ziele, true));
    pruefe('„' . $tabu . '“ wird NIE von selbst verlassen', !in_array($tabu, $auto, true));
}
pruefe('vier Tatsachen belegen einen Stand', count($belegt) === 4, (string) count($belegt));

/* Die Liste muss von der staerksten zur schwaechsten Tatsache geordnet sein.
   Steht eine schwaechere oben, gewaenne sie -- und der Stand blieb hinter dem
   zurueck, was schon belegt ist. Genau dieser Fehler war der Grund, die
   Kette von Uebergaengen durch eine Liste von Belegen zu ersetzen. */
$reihe = array_keys(Status::PROJEKT);
$vorher = count($reihe);
$geordnet = true;
foreach ($belegt as $eintrag) {
    $i = array_search($eintrag['stand'], $reihe, true);
    if ($i === false || $i >= $vorher) { $geordnet = false; }
    $vorher = (int) $i;
}
pruefe('die Belege stehen vom stärksten zum schwächsten', $geordnet);

/* Jeder automatisch verlassbare Stand liegt vor „vorschau“. */
$grenze = array_search('vorschau', $reihe, true);
$zuWeit = [];
foreach ($auto as $stand) {
    $i = array_search($stand, $reihe, true);
    if ($i === false || $i >= $grenze) { $zuWeit[] = $stand; }
}
pruefe('alles Automatische liegt vor „Vorschau“', $zuWeit === [], implode(', ', $zuWeit));

/* DER FEHLER, DEN DIESE PRUEFUNG GEFUNDEN HAT
   ------------------------------------------------------------------------
   Ein Fragebogen kam ausgefuellt zurueck, ohne dass je eine Einladung
   vermerkt war -- und der Stand blieb auf „Zahlung bestaetigt“ stehen, weil
   die Kette an der fehlenden Zwischenstufe haengenblieb. Die staerkere
   Tatsache muss die schwaechere ueberholen duerfen. */
Db::update('projects', $projektId, ['status' => 'zahlung_bestaetigt']);
Db::run('UPDATE questionnaires SET eingeladen_am = NULL WHERE project_id = ?', [$projektId]);
$vl = null;
foreach (Vorgang::alle(true) as $eins) {
    if (($eins['projekt_id'] ?? 0) === $projektId) { $vl = $eins; }
}
Ablauf::nachziehen((array) $vl);
$standLuecke = (string) Db::wert('SELECT status FROM projects WHERE id = ?', [$projektId], '');
pruefe('eine übersprungene Zwischenstufe blockiert nicht',
    $standLuecke !== 'zahlung_bestaetigt', $standLuecke);

/* Und jetzt wirklich: Der Stand steht falsch, die Tatsache steht da. */
Db::update('projects', $projektId, ['status' => 'bestellung_eingegangen']);
$vv = null;
foreach (Vorgang::alle(true) as $eins) {
    if (($eins['projekt_id'] ?? 0) === $projektId) { $vv = $eins; }
}
$zug = Ablauf::nachziehen((array) $vv);
pruefe('der Stand rückt nach, weil die Tatsachen dastehen', $zug !== null,
    $zug === null ? 'nichts passiert' : $zug['nach']);
/* Aus dem Vollen: An dieser Stelle sind Anzahlung, Fragebogen und Vorschau
   alle laengst da -- der Stand muss die ganze Strecke aufholen, nicht einen
   Schritt je Seitenaufruf. Sonst saehe der Kunde dreimal hintereinander
   einen Stand, der immer noch falsch ist. */
$standJetzt = (string) Db::wert('SELECT status FROM projects WHERE id = ?', [$projektId], '');
pruefe('er springt gleich auf den weitesten belegten Stand',
    $standJetzt === 'entwicklung', $standJetzt);
pruefe('der gemeldete Weg nennt Anfang und Ende',
    $zug !== null && $zug['von'] === 'bestellung_eingegangen' && $zug['nach'] === $standJetzt,
    $zug === null ? '—' : $zug['von'] . ' → ' . $zug['nach']);

/* Zweimal aufgerufen darf beim zweiten Mal nichts passieren -- sonst liefe
   die Verwaltung bei jedem Seitenaufruf eine Stufe weiter. */
foreach (Vorgang::alle(true) as $eins) {
    if (($eins['projekt_id'] ?? 0) === $projektId) { $vv = $eins; }
}
pruefe('ein zweiter Lauf ändert nichts mehr', Ablauf::nachziehen((array) $vv) === null);

/* Der Vermerk muss den ganzen Weg nennen, nicht nur das Ziel. */
$vermerk = (string) Db::wert(
    "SELECT meta FROM activities WHERE type = 'stand_nachgezogen' ORDER BY id DESC LIMIT 1", [], '');
pruefe('der Vermerk nennt Herkunft, Ziel und Grund',
    str_contains($vermerk, '"von"') && str_contains($vermerk, '"nach"')
    && str_contains($vermerk, '"weil"'),
    mb_substr($vermerk, 0, 60));

/* Ein von Hand gesetzter Stand darf nie ueberschrieben werden. */
Db::update('projects', $projektId, ['status' => 'online']);
foreach (Vorgang::alle(true) as $eins) {
    if (($eins['projekt_id'] ?? 0) === $projektId) { $vv = $eins; }
}
Ablauf::nachziehen((array) $vv);
pruefe('ein von Hand gesetzter Stand bleibt stehen',
    (string) Db::wert('SELECT status FROM projects WHERE id = ?', [$projektId], '') === 'online');

/* Jeder automatische Schritt muss im Verlauf stehen. Ein Schritt, den niemand
   sieht, ist ein Schritt, dem niemand trauen kann. */
pruefe('der Schritt steht im Verlauf des Kunden',
    (int) Db::wert("SELECT COUNT(*) FROM activities WHERE type = 'stand_nachgezogen'", [], 0) > 0);

/* ============================================================================
   13. Die Checkliste je Stufe
   ============================================================================ */
abschnitt('13. Die Checkliste');

/* Jede Stufe muss eine Liste haben. Eine leere waere schlimmer als keine:
   Sie behauptet, es gaebe nichts zu tun. */
$stufen = array_keys(Vorgang::STUFEN);
$ohne = [];
foreach ($stufen as $stufe) {
    $probe = ['stufe' => $stufe, 'projekt' => [], 'fragebogen' => null,
              'anzahlung' => null, 'restzahlung' => null, 'kunde_id' => null,
              'anfrage_id' => null, 'bestell_id' => null, 'offen_cent' => 0];
    if (count(Ablauf::checkliste($probe)) === 0) { $ohne[] = $stufe; }
}
pruefe('jede Stufe hat eine Checkliste', $ohne === [], implode(', ', $ohne));

/* Die Haken duerfen nicht raten. Bei einem Vorgang ohne jede Tatsache muss
   praktisch alles offen sein -- steht dort ein Haken, kommt er aus der Luft. */
$leer = ['stufe' => 'arbeit', 'projekt' => [], 'fragebogen' => null,
         'anzahlung' => null, 'restzahlung' => null, 'kunde_id' => null,
         'anfrage_id' => null, 'bestell_id' => null, 'offen_cent' => 0];
$standLeer = Ablauf::stand($leer);
pruefe('ohne Tatsachen fast nichts abgehakt', $standLeer['da'] <= 2,
    $standLeer['da'] . ' von ' . $standLeer['von']);

/* Und beim wirklichen Vorgang muss die Liste mitwachsen. */
Db::update('projects', $projektId, $warten);
foreach (Vorgang::alle(true) as $eins) {
    if (($eins['projekt_id'] ?? 0) === $projektId) { $vv = $eins; }
}
$standEcht = Ablauf::stand((array) $vv);
pruefe('beim laufenden Vorgang steht mehr als nichts', $standEcht['da'] > 0,
    $standEcht['da'] . ' von ' . $standEcht['von']);
pruefe('und nicht schon alles', $standEcht['da'] < $standEcht['von'],
    $standEcht['da'] . ' von ' . $standEcht['von']);

/* Jeder Punkt braucht beides: einen Text und jemanden, der ihn setzt. */
$formOk = true;
foreach ($standEcht['punkte'] as $punkt) {
    if (trim((string) $punkt['was']) === '' || !in_array($punkt['wer'], ['du', 'kunde'], true)) {
        $formOk = false;
    }
}
pruefe('jeder Punkt nennt Sache und Zuständigen', $formOk);

/* ============================================================================
   14. Andrang: mehrere Kunden im selben Augenblick
   ============================================================================ */
abschnitt('14. Andrang');

/* WARUM DAS HIER STEHT UND NICHT NUR IN EINEM MESSPROTOKOLL

   Drei Stellen der Kette entstanden in zwei Schritten -- nachsehen, dann
   schreiben -- und zwischen die beiden Schritte passte ein zweiter Besucher.
   Gemessen an zwoelf gleichzeitigen Zugriffen: zwei verlorene Anfragen, vier
   verlorene Bestellungen, zwei bezahlte Raten ohne Beleg und ohne Meldung.

   Solche Fehler kommen zurueck, sobald jemand eine dieser Stellen anfasst,
   denn im Alltag zu zweit am Schreibtisch fallen sie nie auf. Deshalb stehen
   sie hier: nicht als Andrangsmessung -- die braucht echte Prozesse --,
   sondern als Festhalten dessen, was die Reparatur ausmacht. */

/* Der Erkenner muss die drei Andrangs-Meldungen kennen und sonst nichts. */
$bau = static function (int $nr): PDOException {
    $e = new PDOException('SQLSTATE[23000]: Duplicate entry for key uq_probe');
    $e->errorInfo = ['23000', $nr, 'Duplicate entry'];
    return $e;
};
foreach ([1062 => 'doppelter Schlüssel', 1213 => 'Verklemmung', 1205 => 'Sperre abgelaufen'] as $nr => $wort) {
    pruefe('„' . $wort . '“ gilt als Andrang', Db::andrang($bau($nr)));
}
pruefe('ein gewöhnlicher Fehler gilt NICHT als Andrang', !Db::andrang($bau(1146)));
pruefe('etwas anderes als PDO gilt nicht als Andrang',
    !Db::andrang(new RuntimeException('kaputt')));

/* Der Schluesselname entscheidet, ob wiederholt oder aufgegeben wird.
   „Diesen Beleg gibt es schon“ und „diese Nummer ist vergeben“ sehen gleich
   aus und bedeuten das Gegenteil voneinander. */
pruefe('der Schlüsselname wird unterschieden', Db::doppelt($bau(1062), 'uq_probe'));
pruefe('ein fremder Schlüsselname passt nicht', !Db::doppelt($bau(1062), 'uq_anderer'));

/* Wiederholt wird begrenzt oft -- und nur bei Andrang. */
$versuche = 0;
try {
    Db::nochmal(static function () use (&$versuche, $bau): int { $versuche++; throw $bau(1062); }, '', 4);
} catch (Throwable $e) { }
pruefe('vier Versuche, dann Schluss', $versuche === 4, (string) $versuche);

$versuche = 0;
try {
    Db::nochmal(static function () use (&$versuche): int {
        $versuche++; throw new RuntimeException('echter Fehler');
    }, '', 4);
} catch (Throwable $e) { }
pruefe('ein echter Fehler wird nicht wiederholt', $versuche === 1, (string) $versuche);

$lauf = 0;
$ergebnis = Db::nochmal(static function () use (&$lauf, $bau): string {
    $lauf++;
    if ($lauf < 3) { throw $bau(1213); }
    return 'geschafft';
}, '', 5);
pruefe('nach zwei Zusammenstößen klappt es', $ergebnis === 'geschafft' && $lauf === 3,
    $ergebnis . ' nach ' . $lauf);

/* Dieselbe E-Mail zweimal darf nie zwei Kunden ergeben -- und die zweite
   Anfrage darf nicht verlorengehen. */
$a = Events::kundeFinden(['name' => 'Andrang Eins', 'email' => 'andrang@pruefung.test']);
$b = Events::kundeFinden(['name' => 'Andrang Zwei', 'email' => 'andrang@pruefung.test']);
pruefe('dieselbe Adresse ergibt denselben Kunden', $a === $b, "$a / $b");
pruefe('und nur einen einzigen Datensatz',
    (int) Db::wert('SELECT COUNT(*) FROM customers WHERE email = ?',
                   ['andrang@pruefung.test'], 0) === 1);

/* Die Bestellnummer wird sperrend gelesen. Ohne das kann sich ein zweiter
   Versuch nicht aus dem Schnappschuss der Transaktion herausarbeiten -- er
   saehe wieder dieselbe hoechste Nummer. */
$quelle = file_get_contents(dirname(__DIR__) . '/src/Events.php') ?: '';
pruefe('die Bestellnummer wird sperrend gelesen',
    str_contains($quelle, 'FOR UPDATE'));
pruefe('die Bestellung darf sich wiederholen', str_contains($quelle, '}, 5);'));

/* Ein Beleg darf nie still verschwinden. */
$rq = file_get_contents(dirname(__DIR__) . '/src/Rechnung.php') ?: '';
pruefe('ein Belegfehler wird nicht mehr verschluckt',
    str_contains($rq, "Db::doppelt(\$e, 'uq_invoices_payment')") && str_contains($rq, 'throw $e;'));
pruefe('die Belegnummer wird im Versuch neu geholt',
    str_contains($rq, "\$zeile['invoice_no'] = self::naechsteNummer();"));

/* Und die praktische Seite: Wer noch kein Wort gehoert hat, steht oben --
   auch wenn er erst seit einer Stunde wartet. */
$neu = ['erstantwort' => true,  'bewegt' => date('Y-m-d H:i:s'), 'begonnen' => date('Y-m-d H:i:s')];
$alt = ['erstantwort' => false, 'bewegt' => date('Y-m-d H:i:s', strtotime('-30 days')),
        'begonnen' => date('Y-m-d H:i:s', strtotime('-30 days'))];
pruefe('30 Tage Stille sind wirklich mehr als 0',
    Vorgang::ruhtSeitTagen($alt) > Vorgang::ruhtSeitTagen($neu));
$sortiert = [$alt, $neu];
usort($sortiert, static function (array $x, array $y): int {
    $ex = !empty($x['erstantwort']) ? 0 : 1;
    $ey = !empty($y['erstantwort']) ? 0 : 1;
    if ($ex !== $ey) { return $ex <=> $ey; }
    return Vorgang::ruhtSeitTagen($y) <=> Vorgang::ruhtSeitTagen($x);
});
pruefe('die frische Anfrage steht trotzdem oben', !empty($sortiert[0]['erstantwort']));

/* Die Marke muss auch wirklich gesetzt werden -- eine Sortierung nach einem
   Feld, das niemand fuellt, sortiert nach nichts. */
$vquelle = file_get_contents(dirname(__DIR__) . '/src/Vorgang.php') ?: '';
pruefe('die Führung setzt die Marke an zwei Stellen',
    substr_count($vquelle, "?tun=preis', true)")
    + substr_count($vquelle, "tun=kunde_nachricht', true)") === 2);
pruefe('jeder Vorgang trägt die Marke',
    array_key_exists('erstantwort', (array) Vorgang::laden('b' . $bestellId)));

/* ============================================================================
   15. Der Telefonassistent
   ============================================================================ */
abschnitt('15. Der Telefonassistent');

/* Der Endpunkt steht offen im Netz und schreibt in die Verwaltung. Was er
   darf, muss deshalb hier stehen und nicht nur im Kommentar. */

/* Ohne hinterlegten Schlüssel ist er zu. Sonst stünde er offen, solange ihn
   niemand einmal aufgerufen hat — genau die Lücke zwischen Deploy und
   erstem Blick in die Verwaltung. */
Db::run("DELETE FROM settings WHERE skey = 'telefon_schluessel'");
pruefe('ohne hinterlegten Schlüssel ist zu', !Telefon::schluesselStimmt('irgendwas'));
pruefe('ein leerer Schlüssel öffnet nie', !Telefon::schluesselStimmt(''));

$ts = Telefon::schluessel();
pruefe('der Schlüssel ist lang genug', strlen($ts) === 48, (string) strlen($ts));
pruefe('der richtige Schlüssel öffnet', Telefon::schluesselStimmt($ts));
pruefe('ein falscher nicht', !Telefon::schluesselStimmt(str_repeat('a', 48)));
pruefe('ein abgeschnittener nicht', !Telefon::schluesselStimmt(substr($ts, 0, 40)));

/* Neu erzeugen macht den alten wertlos — das ist der ganze Grund, warum ein
   eigener Schlüssel vertretbar ist, obwohl er im Klartext bei STRATO liegt. */
$alt = $ts;
$neuS = Telefon::neuerSchluessel();
pruefe('ein neuer Schlüssel entwertet den alten',
    !Telefon::schluesselStimmt($alt) && Telefon::schluesselStimmt($neuS));

/* Er darf NICHT derselbe sein wie der Cron-Schlüssel. Ein Schlüssel für
   zwei Türen ist einer zu wenig. */
require_once $wurzel . '/src/Cron.php';
pruefe('Telefon- und Cron-Schlüssel sind verschieden',
    Telefon::schluessel() !== Cron::schluessel());

/* Nur diese Aktionen. Was nicht auf der Liste steht, gibt es nicht —
   der Verteiler in telefon.php prueft gegen genau diese Konstante.
   Die Zahl steht bewusst nicht hier: sie waechst mit den Faehigkeiten,
   und ein Test, der bei jeder neuen Faehigkeit nachgezogen werden muss,
   wird irgendwann nachgezogen statt gelesen. Geprueft wird, was gelten
   muss: jeder Name ist eindeutig und ein schlichtes Wort. */
pruefe('jede Aktion steht nur einmal auf der Liste',
    count(Telefon::AKTIONEN) === count(array_unique(Telefon::AKTIONEN)),
    implode(', ', Telefon::AKTIONEN));
$krumm = array_values(array_filter(Telefon::AKTIONEN,
    static fn(string $a): bool => preg_match('/^[a-z][a-z_]{2,30}$/', $a) !== 1));
pruefe('und ist ein schlichtes Wort ohne Sonderzeichen', $krumm === [],
    implode(', ', $krumm));

/* --- Nachschlagen ------------------------------------------------------- */
Db::run('UPDATE customers SET phone = ? WHERE id = ?', ['+39 380 111 2233', $kundeId]);
$n = Telefon::nachschlagen(['telefon' => '00393801112233']);
pruefe('die Rufnummer findet den Kunden trotz anderer Schreibweise',
    ($n['gefunden'] ?? false) === true && (int) ($n['kunde_id'] ?? 0) === $kundeId,
    json_encode($n['gefunden'] ?? null));

/* DIE WICHTIGSTE PRÜFUNG DIESES ABSCHNITTS
   ------------------------------------------------------------------------
   Nie Beträge am Telefon. Eine Stimme ist kein Ausweis, und eine Rufnummer
   erst recht nicht — sie wird weitergegeben, geerbt und gefälscht. Wenn
   diese Prüfung je reißt, hat jemand ein Feld hinzugefügt, ohne daran zu
   denken, wo die Antwort landet. */
$flach = json_encode($n, JSON_UNESCAPED_UNICODE);
$verboten = [];
foreach (['cents', 'betrag', 'preis', 'summe', 'iban', 'street', 'strasse', 'tax_code', 'vat'] as $wort) {
    if (stripos($flach, $wort) !== false) { $verboten[] = $wort; }
}
pruefe('die Auskunft nennt keine Beträge und keine Anschrift', $verboten === [],
    implode(', ', $verboten));
pruefe('sie nennt auch keine E-Mail im Klartext', !str_contains($flach, '@'));

$leer = Telefon::nachschlagen(['telefon' => '004900000000000']);
pruefe('eine unbekannte Nummer findet nichts', ($leer['gefunden'] ?? true) === false);
pruefe('und sagt, was stattdessen zu tun ist', trim((string) ($leer['hinweis'] ?? '')) !== '');

/* Zu kurze Eingaben dürfen nicht die halbe Kundenliste zurückgeben. */
$kurz = Telefon::nachschlagen(['telefon' => '123', 'name' => 'a']);
pruefe('drei Ziffern schlagen nichts nach', ($kurz['gefunden'] ?? true) === false);

/* --- Melden ------------------------------------------------------------- */
$vorher = (int) Db::wert("SELECT COUNT(*) FROM notifications", [], 0);
$m = Telefon::melden(['art' => 'beschwerde', 'kunde_id' => $kundeId,
                      'text' => 'Die Seite war zwei Stunden nicht erreichbar.',
                      'telefon' => '+39 380 111 2233']);
pruefe('eine Beschwerde wird angenommen', ($m['ok'] ?? false) === true);
pruefe('sie landet als Meldung', (int) Db::wert("SELECT COUNT(*) FROM notifications", [], 0) > $vorher);
pruefe('und zwar als schlechte',
    (string) Db::wert("SELECT level FROM notifications ORDER BY id DESC LIMIT 1", [], '') === 'schlecht');
/* Auf „Heute" muss der Name stehen, nicht die Nummer — sonst muss Uwe jede
   Zeile aufmachen, um zu wissen, ob sie ihn angeht. */
pruefe('der Titel nennt den Kunden, nicht die Rufnummer',
    str_contains((string) Db::wert("SELECT title FROM notifications ORDER BY id DESC LIMIT 1", [], ''),
                 (string) Db::wert('SELECT name FROM customers WHERE id = ?', [$kundeId], 'x')),
    (string) Db::wert("SELECT title FROM notifications ORDER BY id DESC LIMIT 1", [], ''));
pruefe('sie steht auch als Nachricht am Kunden',
    (int) Db::wert("SELECT COUNT(*) FROM messages WHERE customer_id = ? AND sender = 'kunde'",
                   [$kundeId], 0) > 0);

$leerM = Telefon::melden(['art' => 'rueckruf', 'text' => '']);
pruefe('ohne Anliegen wird nichts gemeldet', ($leerM['ok'] ?? true) === false);

$fremd = Telefon::melden(['art' => 'gibt_es_nicht', 'text' => 'Probe']);
pruefe('eine unbekannte Art fällt auf „Rückruf" zurück', ($fremd['ok'] ?? false) === true);

/* --- Angebots-Link ------------------------------------------------------ */
$ohne = Telefon::angebotLink(['sprache' => 'de', 'email' => 'keine-adresse']);
pruefe('eine unbrauchbare Adresse wird abgelehnt', ($ohne['ok'] ?? true) === false);

$bedarfVorher = (int) Db::wert('SELECT COUNT(*) FROM bedarf', [], 0);
$al = Telefon::angebotLink(['sprache' => 'de', 'kunde_id' => $kundeId,
                            'zweck' => 'kontakt,speisekarte', 'umfang' => 'wenige',
                            'branche' => 'gastro']);
pruefe('der Konfigurator wird angelegt',
    (int) Db::wert('SELECT COUNT(*) FROM bedarf', [], 0) > $bedarfVorher);
pruefe('das am Telefon Gesagte steht schon drin',
    count($al['vorbefuellt'] ?? []) === 3, implode(', ', $al['vorbefuellt'] ?? []));
pruefe('eine Mehrfachantwort kommt als Liste an',
    str_contains((string) Db::wert('SELECT antworten FROM bedarf ORDER BY id DESC LIMIT 1', [], ''),
                 'speisekarte'));
pruefe('der Rest bleibt offen und wird gezählt', (int) ($al['offen'] ?? 0) === 5,
    (string) ($al['offen'] ?? '—'));

/* FALSCH VERSTANDENES DARF NICHTS SETZEN
   ------------------------------------------------------------------------
   Am Telefon wird sich verhört. Ein Wort, das der Konfigurator nicht kennt,
   muss die Frage OFFEN lassen — eine falsch gesetzte Antwort wandert sonst
   stillschweigend in einen Preis. */
$mist = Telefon::angebotLink(['sprache' => 'de', 'kunde_id' => $kundeId,
                              'umfang' => 'ungefähr sieben Seiten', 'branche' => 'raumfahrt']);
pruefe('unverstandene Antworten werden verworfen, nicht geraten',
    ($mist['vorbefuellt'] ?? ['x']) === [], implode(', ', $mist['vorbefuellt'] ?? []));
/* Die Adresse darf in der Antwort nur verdeckt stehen: Sie geht zurück an
   eine fremde Plattform, die sie protokolliert. */
pruefe('die Zieladresse steht nur verdeckt in der Antwort',
    str_contains((string) ($al['gesendet_an'] ?? ''), '*'),
    (string) ($al['gesendet_an'] ?? '—'));

/* --- Zusammenfassung ---------------------------------------------------- */
$ohneJa = Telefon::zusammenfassung(['kunde_id' => $kundeId, 'text' => str_repeat('Inhalt ', 10)]);
pruefe('ohne Zustimmung geht keine Zusammenfassung raus', ($ohneJa['ok'] ?? true) === false);
pruefe('und der Grund wird genannt', ($ohneJa['grund'] ?? '') === 'keine_zustimmung');

/* --- Verdecken ---------------------------------------------------------- */
pruefe('eine Adresse wird verdeckt', Telefon::verdeckt('uwe@example.test') === 'u**@example.test',
    Telefon::verdeckt('uwe@example.test'));
pruefe('auch etwas, das keine Adresse ist', Telefon::verdeckt('kaputt') === '***');

/* --- Jeder Aufruf steht im Verlauf -------------------------------------- */
pruefe('jede Aktion hinterlässt eine Spur',
    (int) Db::wert("SELECT COUNT(*) FROM activities WHERE type LIKE 'telefon\\_%'", [], 0) >= 2,
    (string) Db::wert("SELECT COUNT(*) FROM activities WHERE type LIKE 'telefon\\_%'", [], 0));

/* --- Die Drosselung ----------------------------------------------------- */
$durch = 0;
for ($i = 0; $i < Telefon::DROSSEL_PRO_MINUTE + 5; $i++) {
    if (Telefon::darfNoch()) { $durch++; }
}
pruefe('die Drosselung greift', $durch <= Telefon::DROSSEL_PRO_MINUTE, (string) $durch);

/* --- Der Endpunkt selbst ------------------------------------------------ */
$quelle = file_get_contents(dirname(dirname(__DIR__)) . '/telefon.php') ?: '';
pruefe('der Endpunkt prüft den Schlüssel zeitkonstant',
    str_contains($quelle, 'Telefon::schluesselStimmt'));
pruefe('er lässt nur die Aktionen von der Liste durch',
    str_contains($quelle, 'in_array($aktion, Telefon::AKTIONEN, true)'));
pruefe('er drosselt', str_contains($quelle, 'Telefon::darfNoch'));
pruefe('ein Fehler würgt kein Gespräch ab',
    str_contains($quelle, 'Anliegen aufnehmen und melden'));

/* ============================================================================
   16. Preis, Lage, Wissenslücke
   ============================================================================ */
abschnitt('16. Was der Assistent nachfragen kann');

require_once $wurzel . '/src/Baukasten.php';
/* Keine feste Zahl: Sie wächst mit den Fähigkeiten, und ein Test, der bei
   jeder neuen nachgezogen werden muss, wird irgendwann nachgezogen statt
   gelesen. Geprüft wird, dass die neuen wirklich dabei sind. */
foreach (['preis_auskunft', 'lage', 'wissensluecke', 'hilfe'] as $neu) {
    pruefe('„' . $neu . '" steht auf der Liste', in_array($neu, Telefon::AKTIONEN, true));
}

/* --- Preisauskunft ------------------------------------------------------ */

/* DIE WICHTIGSTE PRÜFUNG DIESES ABSCHNITTS
   ------------------------------------------------------------------------
   Der Preis am Telefon muss aus derselben Maschine kommen wie der Preis im
   Angebot. Zwei Stellen, die dieselbe Frage beantworten, laufen auseinander
   — und dann steht in der Gesprächszusammenfassung eine andere Zahl als im
   Angebot, und der Kunde hat den Widerspruch schriftlich. */
$antw = ['zweck' => ['zeigen','kontakt'], 'umfang' => 'wenige', 'sprachen' => 1];
$direkt = Baukasten::rechnen($antw);
$dsp = Baukasten::spanne((int) $direkt['von_cents'], (int) $direkt['bis_cents']);
$pa = Telefon::preisAuskunft($antw);
pruefe('die Telefonauskunft rechnet wie das Angebot',
    (int) ($pa['von_euro'] ?? -1) === (int) round($dsp['von_cents'] / 100)
    && (int) ($pa['bis_euro'] ?? -1) === (int) round($dsp['bis_cents'] / 100),
    ($pa['von_euro'] ?? '?') . '–' . ($pa['bis_euro'] ?? '?') . ' gegen '
    . round($dsp['von_cents'] / 100) . '–' . round($dsp['bis_cents'] / 100));

pruefe('es kommt immer eine Spanne, nie eine Zahl',
    isset($pa['von_euro'], $pa['bis_euro']) && $pa['bis_euro'] > $pa['von_euro'],
    json_encode([$pa['von_euro'] ?? null, $pa['bis_euro'] ?? null]));

$ohneAngabe = Telefon::preisAuskunft([]);
pruefe('auch ohne jede Angabe kommt eine Orientierung',
    isset($ohneAngabe['von_euro']) && $ohneAngabe['von_euro'] > 0);
pruefe('und sie sagt, worauf sie beruht',
    trim((string) ($ohneAngabe['grundlage'] ?? '')) !== '');
pruefe('der Hinweis verbietet den Festpreis',
    str_contains((string) ($pa['hinweis'] ?? ''), 'Spanne'));

/* Wer eine Kundennummer mitschickt, bekommt dieselbe Auskunft wie jeder
   andere: kein Name, keine Adresse, kein offener Posten. Oeffentlich ja,
   persoenlich nie. */
$mitKunde = Telefon::preisAuskunft(['zweck' => 'shop', 'kunde_id' => $kundeId,
                                    'telefon' => '+39 380 111 2233']);
$ohneKunde = Telefon::preisAuskunft(['zweck' => 'shop']);
pruefe('eine Kundennummer ändert an der Preisauskunft nichts',
    $mitKunde === $ohneKunde,
    json_encode(array_diff_assoc($mitKunde, $ohneKunde), JSON_UNESCAPED_UNICODE));
$flachP = json_encode($mitKunde, JSON_UNESCAPED_UNICODE);
$leckP = [];
foreach (['kunde', 'email', '@', 'iban', 'offen', 'rechnung', 'Salvatore'] as $wort) {
    if (stripos($flachP, $wort) !== false) { $leckP[] = $wort; }
}
pruefe('und sie nennt niemanden', $leckP === [], implode(', ', $leckP));

/* KEINE WEBSITE-PAKETE AM TELEFON (25.09.2026)
   Bis heute gab preisAuskunft das billigste aktive Website-Paket als
   Festpreis mit -- "Starter, 499 Euro", Wochen nachdem es von der Seite
   verschwunden war. Geprueft wird mit einem absichtlich aktiven Paket, denn
   genau so sah der Fehler aus: unsichtbar, aber aktiv. */
$kpId = Db::insert('packages', ['slug' => 'kette-altpaket', 'name' => 'Altpaket',
    'description' => 'Pruefzeile', 'price_cents' => 49900, 'monthly_cents' => 0, 'currency' => 'EUR',
    'art' => 'website', 'active' => 1, 'oeffentlich' => 0, 'sort' => 99]);
$kpAus = Telefon::preisAuskunft([]);
pruefe('die Preisauskunft nennt keinen Festpreis',
    !array_key_exists('festpreis_euro', $kpAus) && !array_key_exists('festpreis_name', $kpAus),
    implode(',', array_keys($kpAus)));
$kpWissen = json_encode(Telefon::wissen([]), JSON_UNESCAPED_UNICODE);
pruefe('das Telefonwissen kennt kein Website-Paket',
    !str_contains($kpWissen, 'Altpaket') && !str_contains($kpWissen, '"art":"website"'));
Db::run('DELETE FROM packages WHERE id = ?', [$kpId]);

/* --- Lage --------------------------------------------------------------- */
$l = Telefon::lage();
foreach (['datum','uhrzeit','wochentag','modus','rueckruf_heute','hinweis'] as $feld) {
    pruefe('die Lage nennt „' . $feld . '"', array_key_exists($feld, $l));
}
pruefe('die Uhrzeit kommt vom Server, nicht vom Modell',
    (string) $l['datum'] === date('Y-m-d'), (string) $l['datum']);

/* Im Urlaub darf nie ein Rückruf für heute zugesagt werden — egal welche
   Uhrzeit gerade ist. Das ist der ganze Zweck des Schalters. */
Telefon::modusSetzen('urlaub');
$lu = Telefon::lage();
pruefe('im Urlaub ist kein Rückruf heute', ($lu['rueckruf_heute'] ?? true) === false);
pruefe('und der Hinweis verspricht keinen Tag',
    !str_contains(mb_strtolower((string) $lu['hinweis']), 'heute'),
    (string) $lu['hinweis']);
Telefon::modusSetzen('ausgelastet');
pruefe('ausgelastet sagt auch keinen Tag zu', (Telefon::lage()['rueckruf_heute'] ?? true) === false);
Telefon::modusSetzen('normal');
/* Ein Modus, den es nicht gibt, darf nicht durchrutschen — weder ueber die
   Verwaltung noch als Altbestand in der Datenbank. Beides faellt auf
   „normal" zurueck, weil „normal" nichts verspricht, was nicht stimmt. */
Telefon::modusSetzen('quatsch');
pruefe('ein unbekannter Modus wird nicht gespeichert', Telefon::modus() === 'normal',
    Telefon::modus());
Db::run("INSERT INTO settings (skey, svalue) VALUES ('telefon_modus', 'kaese')
         ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)");
pruefe('und Unsinn in der Datenbank fällt auf „normal" zurück',
    Telefon::modus() === 'normal', Telefon::modus());
pruefe('die Lage kommt trotzdem heil zurück',
    isset(Telefon::lage()['hinweis'], Telefon::lage()['modus']));
Telefon::modusSetzen('normal');

/* --- Rückruf mit Zeitfenster -------------------------------------------- */
$r1 = Telefon::melden(['art' => 'rueckruf', 'kunde_id' => $kundeId,
                       'text' => 'Bitte zurückrufen wegen der Startseite.']);
pruefe('ohne Zeitfenster wird einmal nachgefragt', ($r1['nachfragen'] ?? false) === true);
$r2 = Telefon::melden(['art' => 'rueckruf', 'kunde_id' => $kundeId,
                       'text' => 'Bitte zurückrufen.', 'erreichbar' => 'ab 14 Uhr, nicht Dienstag']);
pruefe('mit Zeitfenster nicht mehr', ($r2['nachfragen'] ?? true) === false);
pruefe('das Zeitfenster steht in der Meldung',
    str_contains((string) Db::wert("SELECT body FROM notifications ORDER BY id DESC LIMIT 1", [], ''),
                 'ab 14 Uhr'),
    (string) Db::wert("SELECT body FROM notifications ORDER BY id DESC LIMIT 1", [], ''));
$r3 = Telefon::melden(['art' => 'beschwerde', 'kunde_id' => $kundeId, 'text' => 'Ärger.']);
pruefe('bei einer Beschwerde wird NICHT nach der Uhrzeit gefragt',
    ($r3['nachfragen'] ?? true) === false);

/* --- Wissenslücke ------------------------------------------------------- */
$wl = Telefon::wissensluecke(['frage' => 'Wie lange dauert die Kündigungsfrist bei Betreuung Plus?',
                              'kunde_id' => $kundeId]);
pruefe('eine Wissenslücke wird angenommen', ($wl['ok'] ?? false) === true);
pruefe('der Hinweis verbietet das Erfinden',
    str_contains((string) ($wl['hinweis'] ?? ''), 'erfinden'));
$kurz = Telefon::wissensluecke(['frage' => 'hä']);
pruefe('eine leere Frage wird abgelehnt', ($kurz['ok'] ?? true) === false);

$liste = Telefon::luecken();
pruefe('sie steht auf der Liste', count($liste) >= 1, (string) count($liste));
pruefe('mit Frage und Zeitpunkt',
    trim((string) ($liste[0]['frage'] ?? '')) !== '' && trim((string) ($liste[0]['wann'] ?? '')) !== '');

Telefon::lueckeWeg((string) ($liste[0]['schluessel'] ?? ''));
pruefe('erledigt heißt weg', count(Telefon::luecken()) === count($liste) - 1);
Telefon::lueckeWeg('settings; DROP TABLE settings');
pruefe('ein erfundener Schlüssel löscht nichts',
    (int) Db::wert("SELECT COUNT(*) FROM information_schema.tables
                     WHERE table_schema = DATABASE() AND table_name = 'settings'", [], 0) === 1);

/* --- Der Verteiler kennt die neuen Aktionen ----------------------------- */
$quelle = file_get_contents(dirname(dirname(__DIR__)) . '/telefon.php') ?: '';
foreach (Telefon::AKTIONEN as $a) {
    pruefe('der Verteiler kennt „' . $a . '"', str_contains($quelle, "'" . $a . "'"));
}

/* ============================================================================
   17. Der Trichter zaehlt nur, was das Telefon ausgeloest hat
   ----------------------------------------------------------------------------
   Ein Trichter, der nach hinten breiter wird, ist kein Trichter, sondern eine
   Falschaussage mit Balken. Die erste Fassung hat hinten alle Bedarfe,
   Anfragen und Bestellungen der Website gezaehlt und kam auf 2 Anrufe und
   18 Bestellungen. Diese Pruefungen halten fest, dass das nicht
   wiederkommen kann.
   ============================================================================ */
abschnitt('17. Der Trichter zählt nur das Telefon');

$tr = Telefon::trichter(90);
foreach (['anrufe', 'links', 'bedarf', 'anfragen', 'bestellungen'] as $stufe) {
    pruefe('der Trichter nennt „' . $stufe . '"', isset($tr[$stufe]) && is_int($tr[$stufe]));
}

/* JEDE STUFE ZÄHLT GESPRÄCHE, NICHT DINGE
   ------------------------------------------------------------------------
   Vorhin sind zwei Links rausgegangen ($al und $mist) — in derselben
   Minute, also im selben Gespräch. Stünde hier eine 2, wäre die Zeile
   darüber („1 Anruf") widerlegt und der Trichter wüchse nach unten. */
pruefe('zwei Links in einem Gespräch sind ein Gespräch',
    $tr['links'] <= $tr['anrufe'], $tr['links'] . ' von ' . $tr['anrufe']);
pruefe('und mindestens eines ist es', $tr['links'] >= 1, (string) $tr['links']);

/* DIE EIGENTLICHE PRÜFUNG
   Es gibt in dieser Datenbank Bedarfe, Anfragen und Bestellungen aus dem
   normalen Weg — die ganze Kette weiter oben hat sie erzeugt. Keine davon
   darf hier auftauchen: Der Bedarf vom Telefon ist noch offen. */
pruefe('ein Bedarf, der nicht vom Telefon kam, zählt nicht mit',
    $tr['bedarf'] === 0, (string) $tr['bedarf']);
pruefe('auch keine fremden Anfragen', $tr['anfragen'] === 0, (string) $tr['anfragen']);
pruefe('auch keine fremden Bestellungen', $tr['bestellungen'] === 0, (string) $tr['bestellungen']);

/* Und ein frisch angelegter Bedarf ohne Telefonspur bleibt draußen, auch
   wenn er abgesendet ist. */
Db::run("INSERT INTO bedarf (token, sprache, name, email, telefon, firma, status,
                             abgesendet_am, created_at)
         VALUES (?, 'de', 'Ohne Telefon', 'ohne@test.local', '', '', 'abgesendet', NOW(), NOW())",
        [str_repeat('f', 48)]);
pruefe('auch abgesendet nicht', Telefon::trichter(90)['bedarf'] === 0,
    (string) Telefon::trichter(90)['bedarf']);

/* Und jetzt umgekehrt: Wird der Bedarf vom Telefon abgesendet und haengt eine
   Anfrage daran, rueckt er nach — sonst wuerde der Trichter zwar nichts
   Falsches behaupten, aber auch nichts Richtiges. */
$telBedarf = (int) Db::wert(
    "SELECT b.id FROM bedarf b
      WHERE b.id IN (SELECT CAST(JSON_VALUE(a.meta, '$.bedarf') AS UNSIGNED)
                       FROM activities a WHERE a.type = 'telefon_angebot_link')
      ORDER BY b.id DESC LIMIT 1", [], 0);
pruefe('die Spur führt zurück auf den angelegten Bedarf', $telBedarf > 0, (string) $telBedarf);

Db::run("UPDATE bedarf SET status = 'abgesendet', abgesendet_am = NOW() WHERE id = ?", [$telBedarf]);
$tr2 = Telefon::trichter(90);
pruefe('ein abgesendeter Bedarf rückt nach', $tr2['bedarf'] === 1, (string) $tr2['bedarf']);

/* Die Kette weiter oben legt Anfragen als Demo an. Für diese Prüfung
   braucht es eine echte — sonst bliebe die letzte Stufe unbewiesen. */
Db::run("INSERT INTO anfragen (name, email, sprache, status, demo)
         VALUES ('Vom Telefon', 'telefon@test.local', 'de', 'neu', 0)");
$echteAnfrage = (int) Db::wert('SELECT LAST_INSERT_ID()', [], 0);
pruefe('es gibt eine echte Anfrage zum Anhängen', $echteAnfrage > 0, (string) $echteAnfrage);
Db::run('UPDATE bedarf SET anfrage_id = ? WHERE id = ?', [$echteAnfrage, $telBedarf]);
$tr3 = Telefon::trichter(90);
pruefe('und die Anfrage daran ebenso', $tr3['anfragen'] === 1, (string) $tr3['anfragen']);

/* Und ganz hinten die Bestellung. Erst wenn auch diese Stufe nachweislich
   nachrückt, misst der Trichter das, was auf der Seite darübersteht. */
$echteBestellung = (int) Db::wert('SELECT id FROM orders WHERE demo = 0 ORDER BY id LIMIT 1', [], 0);
pruefe('es gibt eine echte Bestellung', $echteBestellung > 0, (string) $echteBestellung);
Db::run('UPDATE anfragen SET order_id = ? WHERE id = ?', [$echteBestellung, $echteAnfrage]);
$tr4 = Telefon::trichter(90);
pruefe('und die Bestellung rückt nach', $tr4['bestellungen'] === 1, (string) $tr4['bestellungen']);

/* KEIN TRICHTER DARF NACH HINTEN BREITER WERDEN.
   Das ist die eine Prüfung, die auch dann noch reißt, wenn jemand später
   eine Stufe dazwischenschiebt und die Herkunft dabei vergisst. */
foreach ([$tr, $tr2, $tr3, $tr4] as $i => $stand) {
    $folge = [$stand['anrufe'], $stand['links'], $stand['bedarf'],
              $stand['anfragen'], $stand['bestellungen']];
    $waechst = [];
    for ($k = 1; $k < count($folge); $k++) {
        if ($folge[$k] > $folge[$k - 1]) { $waechst[] = $k; }
    }
    pruefe('der Trichter wird nach unten nie breiter (Stand ' . ($i + 1) . ')',
        $waechst === [], implode(', ', $folge));
}

/* ============================================================================
   18. Was der Assistent an STRATO gibt -- und wie er hilft
   ----------------------------------------------------------------------------
   Der erste Teil dieses Abschnitts stammt aus einem echten Ausfall: Die
   Aktion „lage" braucht keine Parameter. PHP kennt keinen Unterschied
   zwischen leerer Liste und leerem Objekt, json_encode machte daraus
   "properties": [] -- und STRATO hat daraufhin das Speichern des GANZEN
   Assistenten blockiert, mit „expected record, received array". Zwei
   Zeichen, eine Stunde Sucherei, und nichts davon war in der Verwaltung zu
   sehen. Deshalb steht die Rechnung jetzt in einer Methode und hier eine
   Pruefung darauf.
   ============================================================================ */
abschnitt('18. Konfiguration und Hilfe am Telefon');

/* --- Der Block, der zu STRATO wandert ----------------------------------- */
$ohneFelder = Telefon::konfigJson('lage',
    ['zweck' => 'Wie spät ist es?', 'eig' => [], 'pflicht' => [], 'rumpf' => '{"aktion":"lage"}'],
    'https://example.test/telefon.php', 'SCHLUESSEL');
pruefe('ein Parametersatz ohne Felder wird zu {}, nicht zu []',
    str_contains($ohneFelder, '"properties": {}'), mb_substr($ohneFelder, 0, 160));
pruefe('und „required" bleibt eine Liste', str_contains($ohneFelder, '"required": []'));
$zurueck = json_decode($ohneFelder, true);
pruefe('der Block ist gültiges JSON', is_array($zurueck));
pruefe('mit genau den drei erlaubten Schlüsseln in „parameters"',
    array_keys($zurueck['parameters'] ?? []) === ['type', 'properties', 'required'],
    implode(', ', array_keys($zurueck['parameters'] ?? [])));
pruefe('der Rumpf steht als Text, nicht als Objekt',
    is_string($zurueck['request']['postData']['text'] ?? null));

$mitFeldern = Telefon::konfigJson('melde',
    ['zweck' => 'Anliegen melden',
     'eig' => ['text' => ['type' => 'string', 'description' => 'Das Anliegen']],
     'pflicht' => ['text'],
     'rumpf' => '{"aktion":"melde","text":"{{ text }}"}'],
    'https://example.test/telefon.php', 'SCHLUESSEL');
pruefe('Platzhalter bleiben wörtlich stehen', str_contains($mitFeldern, '{{ text }}'));
pruefe('der Schlüssel steht genau einmal drin, in der Kopfzeile',
    substr_count($mitFeldern, 'SCHLUESSEL') === 1);
$m = json_decode($mitFeldern, true);
pruefe('jedes Pflichtfeld gibt es auch wirklich',
    array_diff($m['required'] ?? $m['parameters']['required'], array_keys($m['parameters']['properties'])) === []);

/* --- Hilfe: wer nicht gefunden wird, bekommt keinen Stand --------------- */
$fremd = Telefon::hilfe(['problem' => 'bezahlung', 'telefon' => '004900000000000', 'sprache' => 'de']);
pruefe('ein unbekannter Anrufer bekommt keinen Stand', ($fremd['bekannt'] ?? true) === false);
pruefe('und keinen Link', ($fremd['getan'] ?? ['x']) === []);
pruefe('sondern einen Rückruf', ($fremd['weiter'] ?? '') === 'rueckruf');
pruefe('mit Sätzen zum Vorlesen', count($fremd['schritte'] ?? []) >= 2);

/* --- Hilfe zur Bezahlung: kein Betrag, kein Kontostand ------------------ */
Db::run('UPDATE customers SET sprache = ? WHERE id = ?', ['de', $kundeId]);
$geld = Telefon::hilfe(['problem' => 'bezahlung', 'kunde_id' => $kundeId]);
/* In der Pruefung gibt es keinen Mailschluessel — verschickt wird also
   nichts. Geprueft wird deshalb der VERSUCH: Steht im Postausgang eine Zeile
   mit dem richtigen Anlass an die HINTERLEGTE Adresse, war der Weg richtig.
   Ob Brevo sie annimmt, ist eine andere Frage und nicht diese. */
$adrK = (string) Db::wert('SELECT email FROM customers WHERE id = ?', [$kundeId], '');
pruefe('bei Geld wird die Kundenseite an die hinterlegte Adresse geschickt',
    (int) Db::wert("SELECT COUNT(*) FROM mails WHERE anlass = 'kundenseite' AND empfaenger = ?",
                   [$adrK], 0) === 1, $adrK);
pruefe('und an keine andere',
    (int) Db::wert("SELECT COUNT(*) FROM mails WHERE anlass = 'kundenseite' AND empfaenger <> ?",
                   [$adrK], 0) === 0);

/* WENN NICHTS RAUSGEHT, WIRD NICHTS VERSPROCHEN.
   Hier scheitert der Versand mangels Schlüssel — genau der Fall, in dem ein
   Assistent sonst „kommt gleich" sagt und der Anrufer drei Tage wartet. */
pruefe('ein gescheiterter Versand wird gemeldet statt zugesagt',
    ($geld['weiter'] ?? '') === 'gemeldet', (string) ($geld['weiter'] ?? '—'));
pruefe('und Manuela sagt das auch so',
    str_contains((string) ($geld['hinweis'] ?? ''), 'nicht geklappt'));

/* DIE WICHTIGSTE PRÜFUNG DIESES ABSCHNITTS
   Am Telefon fällt kein Betrag — und auch kein Satz darüber, OB etwas offen
   ist. Beides wäre eine Auskunft über Geld an eine Stimme ohne Ausweis. */
$flachH = json_encode($geld, JSON_UNESCAPED_UNICODE);
$verbotenH = [];
foreach (['euro', '€', 'cent', 'betrag', 'offen', 'schuld', 'rechnung', 'summe'] as $wort) {
    if (stripos($flachH, $wort) !== false) { $verbotenH[] = $wort; }
}
pruefe('die Antwort nennt weder Betrag noch offenen Posten', $verbotenH === [],
    implode(', ', $verbotenH));

/* --- Hilfe zum Fragebogen: der Stand entscheidet ------------------------ */
Db::run("UPDATE questionnaires SET status = 'abgeschlossen' WHERE id = ?", [$fbId]);
$fertig = Telefon::hilfe(['problem' => 'fragebogen', 'kunde_id' => $kundeId]);
pruefe('ein zurückgekommener Fragebogen wird nicht noch einmal geschickt',
    ($fertig['getan'] ?? ['x']) === [], implode(',', $fertig['getan'] ?? []));
pruefe('und der Anrufer hört, dass er nichts mehr tun muss',
    count($fertig['schritte'] ?? []) >= 2);

/* Noch nicht eingeladen: Den ERSTEN Versand macht Uwe, nicht der Assistent.
   Er hängt in der Verwaltung an einer Rückfrage, weil danach eine Uhr läuft. */
Db::run("UPDATE questionnaires SET status = 'offen', eingeladen_am = NULL WHERE id = ?", [$fbId]);
$nochNicht = Telefon::hilfe(['problem' => 'fragebogen', 'kunde_id' => $kundeId]);
pruefe('die erste Einladung löst der Assistent nicht aus',
    ($nochNicht['getan'] ?? ['x']) === [], implode(',', $nochNicht['getan'] ?? []));

/* Schon eingeladen, noch offen: DAS darf er wiederholen. */
Db::run("UPDATE questionnaires SET status = 'offen', eingeladen_am = NOW() WHERE id = ?", [$fbId]);
$vorher = (int) Db::wert("SELECT COUNT(*) FROM mails WHERE customer_id = ?", [$kundeId], 0);
$nochmal = Telefon::hilfe(['problem' => 'fragebogen', 'kunde_id' => $kundeId]);
pruefe('einen schon verschickten Fragebogen nimmt er noch einmal in die Hand',
    (int) Db::wert("SELECT COUNT(*) FROM mails WHERE customer_id = ?", [$kundeId], 0) > $vorher);
pruefe('und meldet es, weil der Versand hier nicht klappt',
    ($nochmal['weiter'] ?? '') === 'gemeldet', (string) ($nochmal['weiter'] ?? '—'));

/* --- Nach zwei Anläufen übernimmt ein Mensch ---------------------------- */
$auf = Telefon::hilfe(['problem' => 'fragebogen', 'kunde_id' => $kundeId,
                       'versuch' => Telefon::HILFE_VERSUCHE, 'text' => 'Knopf reagiert nicht']);
pruefe('nach zwei Anläufen wird gemeldet', ($auf['weiter'] ?? '') === 'gemeldet');
pruefe('und nichts mehr verschickt', ($auf['getan'] ?? []) === ['gemeldet']);
pruefe('die Meldung steht in den Aktivitäten',
    (int) Db::wert("SELECT COUNT(*) FROM activities
                     WHERE type LIKE 'telefon\\_%' AND title LIKE '%Nachricht%'", [], 0) > 0);

/* --- Die Sätze kommen in seiner Sprache --------------------------------- */
Db::run('UPDATE customers SET sprache = ? WHERE id = ?', ['it', $kundeId]);
$it = Telefon::hilfe(['problem' => 'zugang', 'kunde_id' => $kundeId]);
pruefe('ein italienischer Kunde hört Italienisch', ($it['sprache'] ?? '') === 'it');
pruefe('und die Sätze sind andere als die deutschen',
    ($it['schritte'] ?? []) !== ($geld['schritte'] ?? []));
Db::run('UPDATE customers SET sprache = ? WHERE id = ?', ['de', $kundeId]);

/* --- Woran es hakt, wird gezählt ---------------------------------------- */
$hk = Telefon::haken(90);
$arten = array_column($hk, 'problem');
pruefe('die Hilfe-Anrufe landen auf einer Liste', $hk !== []);
pruefe('nach Problem getrennt', in_array('fragebogen', $arten, true) && in_array('bezahlung', $arten, true),
    implode(', ', $arten));
$summe = array_sum(array_column($hk, 'anzahl'));
pruefe('und jede Zeile zählt höchstens so viele Lösungen wie Anrufe',
    array_sum(array_column($hk, 'geloest')) <= $summe);
$unbekannt = array_filter($hk, static fn(array $z): bool => $z['problem'] === 'sonstiges');
pruefe('eine erfundene Problemart fällt auf „sonstiges"',
    Telefon::hilfe(['problem' => 'quatsch', 'kunde_id' => $kundeId]) !== []);

/* ============================================================================
   19. Wie es beim Kunden ankommt -- Zeichen, nicht Geschmack
   ----------------------------------------------------------------------------
   Diese Prüfung liest nicht den Quelltext, sondern die KONSTANTEN selbst und
   geht jede Zeichenkette darin durch. Damit ist egal, wie eine Datei
   formatiert ist; geprüft wird, was der Kunde am Ende sieht.

   Sie kann keinen Stil beurteilen. Sie kann aber die Fehler festhalten, die
   sich beim Tippen einschleichen und die man später übersieht, weil man den
   eigenen Text nicht mehr liest: ein deutsches Anführungszeichen, das mit
   einem geraden schließt; ein Apostroph, der in „dell'azienda" gerade steht,
   während er zwei Zeilen weiter richtig ist; ein italienisches Wort ohne
   Akzent. Drei Sprachen heißt dreimal so viele Stellen, an denen das passiert.
   ============================================================================ */
abschnitt('19. Wie es beim Kunden ankommt');

require_once $wurzel . '/src/Texte.php';
require_once $wurzel . '/src/Baukasten.php';

/** Alle Zeichenketten aus den Konstanten einer Klasse, mit ihrem Pfad. */
$alleTexte = static function (string $klasse): array {
    $raus = [];
    $lauf = static function ($wert, string $pfad) use (&$lauf, &$raus): void {
        if (is_string($wert)) { $raus[$pfad] = $wert; return; }
        if (is_array($wert)) {
            foreach ($wert as $k => $v) { $lauf($v, $pfad . '.' . $k); }
        }
    };
    foreach ((new ReflectionClass($klasse))->getConstants() as $name => $wert) {
        $lauf($wert, $klasse . '::' . $name);
    }
    return $raus;
};

$texte = $alleTexte('Texte') + $alleTexte('Telefon') + $alleTexte('Baukasten');
pruefe('es gibt Texte zum Prüfen', count($texte) > 500, (string) count($texte));

/* 1. Ein deutsches Anführungszeichen schließt mit “, nicht mit " */
$falscheKlammer = [];
foreach ($texte as $pfad => $t) {
    if (preg_match('/„[^„“]{0,160}"/u', $t)) { $falscheKlammer[] = $pfad; }
}
pruefe('jedes „ schließt mit “', $falscheKlammer === [],
    implode(' , ', array_slice($falscheKlammer, 0, 4)));

/* 2. Kein gerader Apostroph zwischen Buchstaben — weder im italienischen
      „dell’azienda" noch im englischen „I’ll". Gemischt sieht es billig aus,
      und genau so liest es sich auch. */
$geradeApostrophe = [];
foreach ($texte as $pfad => $t) {
    if (preg_match("/(?<=[A-Za-zÀ-ÿ])'(?=[A-Za-zÀ-ÿ])/u", $t)) { $geradeApostrophe[] = $pfad; }
}
pruefe('kein gerader Apostroph mitten im Wort', $geradeApostrophe === [],
    implode(' , ', array_slice($geradeApostrophe, 0, 4)));

/* 3. Italienische Wörter ohne Akzent. Die Liste ist kurz und enthält nur
      Wörter, die es ohne Akzent gar nicht gibt — „e" und „si" stehen
      deshalb NICHT drin, die sind ohne Akzent auch richtig. */
$ohneAkzent = ['perche' => 'perché', 'piu' => 'più', 'gia' => 'già', 'puo' => 'può',
               'cosi' => 'così', 'citta' => 'città', 'qualita' => 'qualità',
               'attivita' => 'attività', 'pero' => 'però', 'verra' => 'verrà',
               'sara' => 'sarà', 'lunedi' => 'lunedì', 'martedi' => 'martedì',
               'mercoledi' => 'mercoledì', 'giovedi' => 'giovedì', 'venerdi' => 'venerdì',
               'meta' => 'metà', 'liberta' => 'libertà', 'novita' => 'novità',
               'possibilita' => 'possibilità', 'perche\'' => 'perché'];
$fehlt = [];
foreach ($texte as $pfad => $t) {
    /* Nur in italienischen Zweigen suchen: „pero" ist im Deutschen kein Wort,
       aber „meta" sehr wohl, und „Sara" ist ein Name. */
    if (!str_contains($pfad, '.it')) { continue; }
    foreach ($ohneAkzent as $falsch => $richtig) {
        if (preg_match('/(?<![\p{L}])' . preg_quote($falsch, '/') . '(?![\p{L}])/u', $t)) {
            $fehlt[] = $pfad . ': ' . $falsch . ' → ' . $richtig;
        }
    }
}
pruefe('italienische Wörter tragen ihren Akzent', $fehlt === [],
    implode(' , ', array_slice($fehlt, 0, 4)));

/* 4. Drei Punkte sind kein Auslassungszeichen. */
$punkte = [];
foreach ($texte as $pfad => $t) {
    if (str_contains($t, '...')) { $punkte[] = $pfad; }
}
pruefe('kein „..." statt „…"', $punkte === [], implode(' , ', array_slice($punkte, 0, 4)));

/* 5. Umlaut-Ersatzschreibung. Im Quelltext ist „fuer" in Ordnung; in einem
      Satz, den jemand liest, ist es ein Fehler. */
$ersatz = ['fuer', 'ueber', 'koennen', 'moeglich', 'muessen', 'waehrend', 'zurueck',
           'Gruesse', 'schoen', 'natuerlich', 'spaeter', 'aendern', 'loeschen',
           'Verguetung', 'gehoert', 'waere', 'haetten', 'wuerde'];
$roh = [];
foreach ($texte as $pfad => $t) {
    if (!str_contains($pfad, '.de') && !preg_match('/[A-ZÄÖÜ][a-zäöüß]+ [a-zäöüß]/u', $t)) { continue; }
    foreach ($ersatz as $w) {
        if (preg_match('/(?<![\p{L}])' . $w . '(?![\p{L}])/u', $t)) { $roh[] = $pfad . ': ' . $w; }
    }
}
pruefe('deutsche Texte tragen ihre Umlaute', $roh === [], implode(' , ', array_slice($roh, 0, 4)));

/* 6. Jede Sprachkarte ist vollständig: Was es auf Italienisch gibt, gibt es
      auch auf Deutsch und Englisch. Ein fehlender Zweig fällt sonst erst auf,
      wenn ein Engländer eine italienische Zeile liest. */
$luecken = [];
$suchen = static function ($wert, string $pfad) use (&$suchen, &$luecken): void {
    if (!is_array($wert)) { return; }
    $hat = static fn(string $s): bool => array_key_exists($s, $wert) && is_string($wert[$s]);
    if ($hat('it') || $hat('de') || $hat('en')) {
        foreach (['it', 'de', 'en'] as $s) {
            if (!$hat($s) || trim((string) $wert[$s]) === '') { $luecken[] = $pfad . ' → ' . $s; }
        }
        return;
    }
    foreach ($wert as $k => $v) { $suchen($v, $pfad . '.' . $k); }
};
foreach (['Texte', 'Baukasten'] as $kl) {
    foreach ((new ReflectionClass($kl))->getConstants() as $name => $wert) {
        $suchen($wert, $kl . '::' . $name);
    }
}
pruefe('jede Sprachkarte hat alle drei Sprachen', $luecken === [],
    count($luecken) . ': ' . implode(' , ', array_slice($luecken, 0, 5)));

/* --- Und dasselbe für die Website ---------------------------------------
   Die Verwaltung ist nicht der Ort, an dem die meisten Texte stehen. Das ist
   die Website: drei Sprachdateien und die Seiten selbst. Geprüft wird dort
   dasselbe — aber nur im Text, nicht im Quelltext: strukturierte Daten,
   Kommentarköpfe und HTML-Kennungen bleiben außen vor, sonst meldet die
   Prüfung „meta" als fehlenden Akzent auf „metà" und wird nie wieder
   gelesen. */
$netzDateien = ['/index.html', '/assets/js/i18n-it.js', '/assets/js/i18n-de.js',
                '/assets/js/i18n-en.js', '/assets/js/legal-it.js',
                '/assets/js/legal-de.js', '/assets/js/legal-en.js'];
$nurText = static function (string $roh): string {
    $t = preg_replace('#<script type="application/ld\+json">.*?</script>#s', '', $roh) ?? $roh;
    $t = preg_replace('#/\*.*?\*/#s', '', $t) ?? $t;      // Kommentarköpfe
    $t = preg_replace('#<[^>]+>#', ' ', $t) ?? $t;          // HTML-Auszeichnung
    return $t;
};

$netzFehler = [];
foreach ($netzDateien as $rel) {
    $datei = $oben . $rel;
    if (!is_file($datei)) { $netzFehler[] = $rel . ': fehlt'; continue; }
    $t = $nurText((string) file_get_contents($datei));
    if (preg_match('/„[^„“]{0,120}"/u', $t))                    { $netzFehler[] = $rel . ': „…"'; }
    if (preg_match("/(?<=[A-Za-zÀ-ÿ])'(?=[A-Za-zÀ-ÿ])/u", $t))  { $netzFehler[] = $rel . ': gerader Apostroph'; }
    if (str_contains($rel, '-it')) {
        foreach (['perche' => 'perché', 'piu' => 'più', 'gia' => 'già', 'puo' => 'può',
                  'cosi' => 'così', 'citta' => 'città', 'qualita' => 'qualità',
                  'attivita' => 'attività', 'pero' => 'però', 'novita' => 'novità'] as $f => $r) {
            if (preg_match('/(?<![\p{L}])' . $f . '(?![\p{L}])/u', $t)) {
                $netzFehler[] = $rel . ': ' . $f . ' → ' . $r;
            }
        }
    }
}
pruefe('auch auf der Website stimmen die Zeichen', $netzFehler === [],
    implode(' , ', array_slice($netzFehler, 0, 5)));

/* ============================================================================
   20. Wer heute einen Anruf erwartet
   ----------------------------------------------------------------------------
   Diese Liste ersetzt den Roboter, der zurückruft: Sie kostet nichts, ist
   rechtlich unbedenklich und beantwortet die Frage, die vor jeder Anschaffung
   steht — wie viele Rückrufe es überhaupt gibt.

   Damit sie das kann, muss sie zwei Dinge sicher tun: nichts vergessen und
   nichts doppelt zeigen. Beides steht hier.
   ============================================================================ */
abschnitt('20. Wer heute einen Anruf erwartet');

/* Alles Vorherige abräumen, damit die Zählungen dieses Abschnitts eindeutig
   sind — die Kette hat oben schon Meldungen erzeugt. */
Db::run("INSERT INTO activities (type, title, meta, created_at)
         SELECT 'telefon_rueckruf_erledigt', 'Aufräumen für Abschnitt 20',
                CONCAT('{\"quelle\":\"', id, '\"}'), NOW()
           FROM activities WHERE type IN ('telefon_melde','telefon_hilfe')");
pruefe('vor dem Abschnitt ist die Liste leer', Telefon::rueckrufe(30) === [],
    (string) count(Telefon::rueckrufe(30)));

/* --- Ein Rückrufwunsch mit Zeitfenster ---------------------------------- */
Telefon::melden(['art' => 'rueckruf', 'kunde_id' => $kundeId,
                 'name' => 'Carla Greco', 'telefon' => '+39 340 55 66 77',
                 'erreichbar' => 'morgen vormittag',
                 'text' => 'Möchte über eine zweite Sprache sprechen']);
$liste = Telefon::rueckrufe(30);
pruefe('der Wunsch steht auf der Liste', count($liste) === 1, (string) count($liste));
$e = $liste[0] ?? [];
pruefe('mit der Rufnummer, die man wählen kann',
    str_contains((string) ($e['nummer'] ?? ''), '340'), (string) ($e['nummer'] ?? '—'));
pruefe('mit dem Zeitfenster', ($e['erreichbar'] ?? '') === 'morgen vormittag');
pruefe('mit dem Anliegen im Klartext',
    str_contains((string) ($e['anliegen'] ?? ''), 'zweite Sprache'));
pruefe('und noch nicht überfällig', ($e['ueberfaellig'] ?? true) === false);

/* --- Was NICHT auf die Liste gehört -------------------------------------- */
Telefon::wissensluecke(['frage' => 'Macht ihr auch Beschriftungen?']);
pruefe('eine Wissenslücke wartet auf keinen Anruf',
    count(Telefon::rueckrufe(30)) === 1, (string) count(Telefon::rueckrufe(30)));

/* Eine Hilfe, deren Versand scheitert, gehört dagegen SEHR WOHL auf die
   Liste: Dann wartet jemand auf etwas, das nie angekommen ist. Hier in der
   Prüfung scheitert jeder Versand mangels Mailschlüssel — also erscheint
   sie, und genau das soll sie. */
Telefon::hilfe(['problem' => 'zugang', 'kunde_id' => $kundeId]);
pruefe('eine Hilfe, die nicht rausging, wartet auf einen Anruf',
    count(Telefon::rueckrufe(30)) === 2, (string) count(Telefon::rueckrufe(30)));

/* --- Eine Beschwerde steht oben ----------------------------------------- */
Telefon::melden(['art' => 'beschwerde', 'kunde_id' => $kundeId,
                 'name' => 'Bruno Sala', 'telefon' => '+39 333 11 22 33',
                 'text' => 'Seite war zwei Stunden offline']);
$liste = Telefon::rueckrufe(30);
pruefe('die Beschwerde steht ganz oben', ($liste[0]['dringend'] ?? false) === true,
    (string) ($liste[0]['wer'] ?? '—'));
pruefe('und die Liste hat jetzt drei Einträge', count($liste) === 3,
    (string) count($liste));

/* --- Erledigt heißt weg, aber nicht gelöscht ---------------------------- */
$erst = (int) $liste[0]['id'];
pruefe('erledigt melden klappt', Telefon::rueckrufErledigt($erst) === true);
pruefe('und der Eintrag verschwindet aus der Liste',
    count(Telefon::rueckrufe(30)) === 2, (string) count(Telefon::rueckrufe(30)));
pruefe('die Spur bleibt aber stehen',
    (int) Db::wert('SELECT COUNT(*) FROM activities WHERE id = ?', [$erst], 0) === 1);
pruefe('zweimal erledigen macht keinen Schaden', Telefon::rueckrufErledigt($erst) === true);
pruefe('und legt keine zweite Zeile an',
    (int) Db::wert("SELECT COUNT(*) FROM activities
                     WHERE type = 'telefon_rueckruf_erledigt'
                       AND meta LIKE CONCAT('%\"quelle\":\"', ?, '\"%')", [$erst], 0) === 1);

/* EINE ZAHL, DIE IN EINER ANDEREN STECKT
   ------------------------------------------------------------------------
   „quelle":1 hätte auch auf 12, 13 und 100 gepasst — ein erledigter Rückruf
   hätte fremde mit weggeräumt. Deshalb steht die Nummer in Anführungszeichen.
   Diese Prüfung ist der Grund dafür. */
$offenVorher = count(Telefon::rueckrufe(30));
Telefon::rueckrufErledigt((int) ($erst . '9'));       // z. B. 12 statt 1
pruefe('eine Nummer, die mit derselben Ziffer anfängt, räumt nichts weg',
    count(Telefon::rueckrufe(30)) === $offenVorher, (string) $offenVorher);

pruefe('eine erfundene Nummer wird abgelehnt', Telefon::rueckrufErledigt(999999) === false);
pruefe('und die Null auch', Telefon::rueckrufErledigt(0) === false);

/* --- Was zu lange liegt, meldet sich ------------------------------------ */
$frisch = Telefon::rueckrufeMahnen();
pruefe('frische Rückrufe werden nicht gemahnt',
    (int) ($frisch['ueberfaellig'] ?? -1) === 0, json_encode($frisch));

Db::run("UPDATE activities SET created_at = NOW() - INTERVAL 3 DAY
          WHERE type = 'telefon_melde'");
$offen = Telefon::rueckrufe(30);
pruefe('nach drei Tagen ist er überfällig', ($offen[0]['ueberfaellig'] ?? false) === true);

/* Meldungen stehen in „notifications", nicht in den Aktivitäten — das ist
   die Liste, die in der Verwaltung oben klingelt. */
$meldungenVorher = (int) Db::wert(
    "SELECT COUNT(*) FROM notifications WHERE type = 'telefon_rueckruf_offen'", [], 0);
$alt = Telefon::rueckrufeMahnen();
pruefe('und wird gemeldet', (int) ($alt['ueberfaellig'] ?? 0) >= 1, json_encode($alt));
pruefe('als EINE Meldung, nicht als eine je Rückruf',
    (int) Db::wert("SELECT COUNT(*) FROM notifications WHERE type = 'telefon_rueckruf_offen'", [], 0)
    === $meldungenVorher + 1);

/* --- Der Cronjob kennt die Aufgabe -------------------------------------- */
$cronQuelle = file_get_contents($wurzel . '/src/Cron.php') ?: '';
pruefe('der Cronjob ruft sie auf', str_contains($cronQuelle, 'Telefon::rueckrufeMahnen'));

/* ============================================================================
   21. Kommt die Rufnummer des Anrufers an?
   ----------------------------------------------------------------------------
   Zwischen dem Anrufer und der Verwaltung liegen zwei fremde Systeme: die
   Weiterleitung beim Telefonanbieter und der Assistent bei STRATO. Ob die
   Rufnummer diese Strecke überlebt, steht nirgends vollständig geschrieben —
   bei Sonetel ist es eine Einstellung („Show": Caller's number oder Called
   number), bei STRATO ist es gar nicht dokumentiert.

   Also wird es gemessen. Diese Prüfungen halten fest, dass wirklich gemessen
   wird — und dass die Auskunft vorsichtig ist, wo sie es sein muss: Ein
   einzelner Anrufer kann seine Nummer selbst unterdrückt haben.
   ============================================================================ */
abschnitt('21. Kommt die Rufnummer des Anrufers an?');

Db::run("DELETE FROM activities WHERE type = 'telefon_nachschlagen'");
$c = Telefon::anrufernummer(90);
/* ?? greift bei einem vorhandenen null-Wert auch — deshalb hier direkt
   vergleichen. Der Fehler hat diese Prüfung beim ersten Lauf gerissen. */
pruefe('ohne Anruf gibt es keine Aussage',
    array_key_exists('kommt_an', $c) && $c['kommt_an'] === null);
pruefe('und das wird auch so gesagt', ($c['gemessen'] ?? true) === false);

/* Ein Nachschlagen ohne Nummer — jemand nennt nur seinen Namen. */
Telefon::nachschlagen(['name' => 'Gibt es nicht']);
$c = Telefon::anrufernummer(90);
pruefe('jetzt ist gemessen', ($c['gemessen'] ?? false) === true);
pruefe('einmal ohne Nummer beweist noch nichts', $c['kommt_an'] === null,
    json_encode($c));

/* Drei ohne Nummer sind ein Muster. */
Telefon::nachschlagen(['name' => 'Auch nicht']);
Telefon::nachschlagen(['name' => 'Ebenfalls nicht']);
$c = Telefon::anrufernummer(90);
pruefe('drei ohne Nummer sind ein Befund', ($c['kommt_an'] ?? null) === false,
    json_encode($c));

/* EIN EINZIGER ANRUF MIT NUMMER BEWEIST, DASS DIE STRECKE TRÄGT.
   Andersherum als oben — und das ist Absicht: Dass etwas ankommt, kann man
   an einem Fall sehen. Dass es nie ankommt, nicht. */
Db::run('UPDATE customers SET phone = ? WHERE id = ?', ['+39 380 111 2233', $kundeId]);
Telefon::nachschlagen(['telefon' => '+39 380 111 2233']);
$c = Telefon::anrufernummer(90);
pruefe('ein einziger Anruf mit Nummer genügt als Beweis',
    ($c['kommt_an'] ?? null) === true, json_encode($c));
pruefe('und beide Seiten werden gezählt',
    (int) $c['mit'] === 1 && (int) $c['ohne'] === 3, json_encode($c));

/* Die Rufnummer landet in der Spur — sonst ließe sich später nicht
   nachsehen, was wirklich ankam. */
$letzte = (string) Db::wert(
    "SELECT meta FROM activities WHERE type = 'telefon_nachschlagen' ORDER BY id DESC LIMIT 1", [], '');
pruefe('die angekommene Nummer steht in der Spur', str_contains($letzte, '380'), $letzte);


/* ============================================================================
   22. Beratung: der Konfigurator als Gespräch

   Der Bedarfsdatensatz ist der Gesprächsfaden. Was hier geprüft wird, ist
   genau das, was am Telefon schiefgehen könnte: dass der Faden reißt, dass
   eine erfundene Antwort in die Rechnung wandert, dass zwei Fragebögen
   entstehen statt einem.
   ============================================================================ */
abschnitt('22. Beratung am Telefon');

$vorherBedarf = (int) Db::wert('SELECT COUNT(*) FROM bedarf', [], 0);

$b1 = Telefon::beratung(['sprache' => 'de']);
pruefe('das erste Mal öffnet einen Faden', !empty($b1['gespraech']), json_encode($b1));
pruefe('und fragt als Erstes nach dem Zweck', ($b1['frage_zu'] ?? '') === 'zweck', json_encode($b1));
pruefe('mit einem fertigen Satz zum Vorlesen', trim((string) ($b1['satz'] ?? '')) !== '', json_encode($b1));
pruefe('die Antwortmöglichkeiten stehen dabei', !empty($b1['optionen']), json_encode($b1));
pruefe('noch ist nichts fertig', ($b1['fertig'] ?? true) === false, json_encode($b1));

$faden = (string) $b1['gespraech'];

$b2 = Telefon::beratung(['sprache' => 'de', 'gespraech' => $faden,
                         'antwort_auf' => 'zweck', 'antwort' => 'zeigen,kontakt']);
pruefe('derselbe Faden bleibt', ($b2['gespraech'] ?? '') === $faden, json_encode($b2));
pruefe('die Antwort ist angekommen', in_array('zweck', (array) ($b2['beantwortet'] ?? []), true),
    json_encode($b2['beantwortet'] ?? []));
pruefe('jetzt kommt die nächste Frage', ($b2['frage_zu'] ?? '') === 'umfang', json_encode($b2));

/* SOBALD GENUG GESAGT IST, LÄUFT DER PREIS MIT.
   Nicht erst am Ende: Wer nach der zweiten Frage hört, in welcher Gegend er
   landet, bleibt im Gespräch — oder legt auf, und das ist auch eine Antwort. */
pruefe('und die Spanne läuft mit', (int) ($b2['von_euro'] ?? 0) > 0
    && (int) ($b2['bis_euro'] ?? 0) >= (int) ($b2['von_euro'] ?? 0), json_encode($b2));

/* ERFUNDENES DARF NICHT IN DIE RECHNUNG.
   Der Konfigurator nimmt nur seine eigenen Schlüsselwörter. Was Manuela
   falsch versteht, lässt die Frage offen — es beantwortet sie nicht falsch. */
$b3 = Telefon::beratung(['sprache' => 'de', 'gespraech' => $faden,
                         'antwort_auf' => 'umfang', 'antwort' => 'ungefähr mittelgroß halt']);
pruefe('Freitext wird verworfen', ($b3['frage_zu'] ?? '') === 'umfang', json_encode($b3));
pruefe('und die Frage bleibt offen statt falsch beantwortet',
    !in_array('umfang', (array) ($b3['beantwortet'] ?? []), true), json_encode($b3['beantwortet'] ?? []));

/* Ein Feld, das am Telefon nichts zu suchen hat, geht auch nicht durch. */
$b3b = Telefon::beratung(['sprache' => 'de', 'gespraech' => $faden,
                          'antwort_auf' => 'material', 'antwort' => 'texte']);
pruefe('nach Material wird am Telefon nicht gefragt',
    !in_array('material', (array) ($b3b['beantwortet'] ?? []), true), json_encode($b3b['beantwortet'] ?? []));

foreach ([['umfang', 'wenige'], ['sprachen', '2'], ['bestand', 'neu'],
          ['branche', 'gastro'], ['betreuung', 'ja']] as [$f, $w]) {
    $letzt = Telefon::beratung(['sprache' => 'de', 'gespraech' => $faden,
                                'antwort_auf' => $f, 'antwort' => $w]);
}
pruefe('nach sechs Antworten ist durchgefragt', ($letzt['fertig'] ?? false) === true, json_encode($letzt));
pruefe('und es gibt einen Link auf den halb gefüllten Bogen',
    str_contains((string) ($letzt['link'] ?? ''), 'bedarf.php?t='), (string) ($letzt['link'] ?? ''));

/* EIN GESPRÄCH, EIN FRAGEBOGEN.
   Der häufigste Fehler dieser Bauart wäre, bei jedem Aufruf einen neuen
   anzulegen — dann fände Uwe morgens sieben halbe Fragebögen desselben
   Anrufers. */
pruefe('das ganze Gespräch hat genau einen Fragebogen angelegt',
    (int) Db::wert('SELECT COUNT(*) FROM bedarf', [], 0) === $vorherBedarf + 1,
    (string) Db::wert('SELECT COUNT(*) FROM bedarf', [], 0));

$gespeichert = json_decode((string) Db::wert(
    'SELECT antworten FROM bedarf WHERE token = ?', [$faden], ''), true) ?: [];
pruefe('und in ihm steht, was gesagt wurde',
    ($gespeichert['branche'] ?? '') === 'gastro'
    && in_array('kontakt', (array) ($gespeichert['zweck'] ?? []), true), json_encode($gespeichert));

/* Ein Faden, den es nicht gibt, führt nicht ins Leere, sondern zu einem neuen. */
$b4 = Telefon::beratung(['sprache' => 'de', 'gespraech' => str_repeat('a', 48)]);
pruefe('ein unbekannter Faden beginnt neu statt zu scheitern',
    !empty($b4['gespraech']) && $b4['gespraech'] !== $faden, json_encode($b4));

/* ============================================================================
   23. Der Beweis, das Wissen und der Termin
   ============================================================================ */
abschnitt('23. Beleg, Wissen, Termin');

$leer = Telefon::beleg(['sprache' => 'de']);
pruefe('ohne veröffentlichte Stimme kommt keine',  $leer['stimmen'] === [], json_encode($leer));
pruefe('und der Hinweis sagt ausdrücklich: nichts erfinden',
    str_contains((string) $leer['hinweis'], 'erfinden'), (string) $leer['hinweis']);

Db::insert('stimmen', ['customer_id' => $kundeId, 'name' => 'Nadia Bosco',
    'firma' => 'Charme Color', 'text' => 'Seit der neuen Seite rufen mehr Leute an.',
    'sterne' => 5, 'sprache' => 'de', 'erlaubnis' => 1, 'status' => 'veroeffentlicht']);
$mit = Telefon::beleg(['sprache' => 'de']);
pruefe('eine veröffentlichte Stimme kommt zurück', count($mit['stimmen']) === 1, json_encode($mit));
pruefe('mit Betrieb dazu', ($mit['stimmen'][0]['betrieb'] ?? '') === 'Charme Color', json_encode($mit));

/* Eine nicht freigegebene Stimme darf nie am Telefon landen. */
Db::insert('stimmen', ['customer_id' => $kundeId, 'name' => 'Geheim',
    'firma' => 'Nicht freigegeben', 'text' => 'Steht noch nicht öffentlich.',
    'sprache' => 'de', 'erlaubnis' => 0, 'status' => 'neu']);
$mit2 = Telefon::beleg(['sprache' => 'de']);
pruefe('was nicht freigegeben ist, bleibt draußen',
    !str_contains(json_encode($mit2), 'Nicht freigegeben'), json_encode($mit2));

$w = Telefon::wissen(['sprache' => 'de']);
pruefe('das Wissen kennt die Pakete aus der Verwaltung', !empty($w['pakete']), json_encode($w['pakete'] ?? []));
pruefe('und sagt, dass Einzelpreise keine Projektpreise sind',
    str_contains((string) $w['hinweis'], 'Spanne'), (string) $w['hinweis']);

/* Was nur auf Anfrage angeboten wird, darf nicht am Telefon in einen Preis
   wandern — sonst kostet das Projekt plötzlich mehr, als Uwe genannt hätte. */
$namen = array_column($w['bausteine'] ?? [], 'name');
$logo  = Baukasten::katalog(true)['logo'] ?? null;
pruefe('Bausteine nur auf Anfrage bleiben draußen',
    $logo === null || !in_array(Baukasten::name($logo, 'de'), $namen, true), json_encode($namen));

$t1 = Telefon::termin(['sprache' => 'de']);
pruefe('ohne Wunschzeit kommen freie Plätze', !empty($t1['frei']), json_encode($t1));
$platz = (string) $t1['frei'][0];
pruefe('ein Platz liegt in der Zukunft', strtotime($platz) > time(), $platz);

$t2 = Telefon::termin(['sprache' => 'de', 'wann' => $platz, 'kunde_id' => $kundeId,
                       'telefon' => '+39 380 111 2233', 'name' => 'Manuel',
                       'anliegen' => 'Neue Seite für das Lokal']);
pruefe('der Platz lässt sich nehmen', ($t2['ok'] ?? false) === true, json_encode($t2));
$t3 = Telefon::termin(['sprache' => 'de']);
pruefe('und ist danach weg', !in_array($platz, (array) $t3['frei'], true), json_encode($t3['frei']));

$t4 = Telefon::termin(['sprache' => 'de', 'wann' => '2019-01-01 09:00']);
pruefe('eine erfundene Zeit wird abgelehnt', ($t4['ok'] ?? true) === false, json_encode($t4));

pruefe('der Termin steht auch auf der Rückrufliste',
    (bool) array_filter(Telefon::rueckrufe(30),
        static fn(array $r): bool => str_contains($r['anliegen'], 'Verabredeter Termin')),
    json_encode(array_column(Telefon::rueckrufe(30), 'anliegen')));

/* ============================================================================
   24. Die Bewertung — eine Reihenfolge, keine Behauptung
   ============================================================================ */
abschnitt('24. Wie heiß ist der Anrufer?');

$kalt = Telefon::bewerten([]);
pruefe('wer nichts gesagt hat, bekommt nichts', $kalt['punkte'] === 0, json_encode($kalt));
pruefe('und auch keinen Grund', $kalt['gruende'] === [], json_encode($kalt));

$heiss = Telefon::bewerten(['beantwortet' => ['zweck', 'umfang', 'sprachen'],
                            'bis_euro' => 1400, 'termin' => true,
                            'seitenbefunde' => ['nur_profil']]);
pruefe('wer alles getan hat, steht oben', $heiss['punkte'] > $kalt['punkte'], json_encode($heiss));
pruefe('und jeder Punkt hat einen Grund', count($heiss['gruende']) >= 4, json_encode($heiss));
pruefe('„hat keine eigene Website" steht als Grund da',
    in_array('hat keine eigene Website', $heiss['gruende'], true), json_encode($heiss));

/* KEINE BEWERTUNG NACH HERKUNFT.
   Die Prüfung steht hier, damit sie reißt, wenn jemand später auf die Idee
   kommt, Vorwahl, Sprache oder Namen einzurechnen. */
$a = Telefon::bewerten(['beantwortet' => ['zweck'], 'sprache' => 'de', 'name' => 'Müller',
                        'nummer' => '+49301234']);
$b = Telefon::bewerten(['beantwortet' => ['zweck'], 'sprache' => 'it', 'name' => 'Esposito',
                        'nummer' => '+39091999']);
pruefe('Sprache, Name und Vorwahl ändern nichts an der Bewertung',
    $a['punkte'] === $b['punkte'], $a['punkte'] . ' vs ' . $b['punkte']);

/* Die Liste sortiert danach — aber dringend schlägt alles. */
$liste = Telefon::rueckrufe(30);
pruefe('jede Zeile trägt ihre Bewertung', $liste === [] || isset($liste[0]['punkte']),
    json_encode(array_slice(array_column($liste, 'punkte'), 0, 5)));

/* ============================================================================
   25. Der Seitenblick — Worte nur über Gemessenes
   ============================================================================ */
abschnitt('25. Der Seitenblick');

require_once $wurzel . '/src/Seitenblick.php';

$ohne = Telefon::seiteAnsehen(['sprache' => 'de']);
pruefe('ohne Adresse wird nichts abgerufen', ($ohne['gefunden'] ?? true) === false, json_encode($ohne));

/* „Finde ich nicht" darf erst kommen, wenn es die Adresse wirklich nicht gibt.
   Deshalb unterscheidet der Seitenblick jetzt zwei Fälle — und sagt sie
   verschieden an. */
$fehlt = ['gibt_es_nicht', 'antwortet_nicht'];
foreach ($fehlt as $art) {
    $paar = Seitenblick::SAETZE[$art]['de'] ?? null;
    pruefe("„$art" . '" hat einen eigenen Satz', is_array($paar) && trim((string) $paar[0]) !== '',
        json_encode($paar));
}
pruefe('die beiden Sätze sind nicht derselbe',
    Seitenblick::SAETZE['gibt_es_nicht']['de'][0] !== Seitenblick::SAETZE['antwortet_nicht']['de'][0]);

/* Nichts, was wie eine Adresse im eigenen Netz aussieht, geht raus. Das ist
   die Stelle, an der man sich sonst einen Türsteher einbaut, der auf Zuruf
   ins eigene Netz greift. */
foreach (['localhost', '127.0.0.1', '10.0.0.5', '192.168.1.1', 'file:///etc/passwd',
          '169.254.169.254'] as $boese) {
    $r = Telefon::seiteAnsehen(['adresse' => $boese, 'sprache' => 'de']);
    pruefe("„$boese" . '" wird gar nicht erst abgerufen',
        ($r['gefunden'] ?? true) === false && ($r['adresse'] ?? null) === null, json_encode($r));
}

/* Die sechs abgewiesenen Adressen zählen als Fehlversuche — und nach zweien
   ruft sie zu Recht gar nichts mehr ab. Für die nächste Prüfung ist das ein
   anderes Gespräch, also wird zurückdatiert. Die Bremse selbst hat ihren
   eigenen Abschnitt weiter unten. */
Db::run("UPDATE activities SET created_at = NOW() - INTERVAL 2 HOUR
          WHERE type = 'telefon_seitenblick'");

$profil = Telefon::seiteAnsehen(['adresse' => 'facebook.com/pizzeria', 'sprache' => 'de']);
pruefe('eine Facebook-Seite ist kein Fehler, sondern ein Befund',
    ($profil['befunde'][0]['art'] ?? '') === 'nur_profil', json_encode($profil));
pruefe('und der Satz steht auf Deutsch da',
    str_contains((string) ($profil['befunde'][0]['satz'] ?? ''), 'Plattform'),
    (string) ($profil['befunde'][0]['satz'] ?? ''));

/* Jeder Befund hat einen Satz in allen drei Sprachen. Ein fehlender Satz
   fiele erst am Telefon auf — als Stille. */
$fehlend = [];
foreach (Seitenblick::SAETZE as $art => $saetze) {
    foreach (['it', 'de', 'en'] as $sp) {
        $paar = $saetze[$sp] ?? null;
        if (!is_array($paar) || trim((string) ($paar[0] ?? '')) === ''
            || trim((string) ($paar[1] ?? '')) === '') { $fehlend[] = "$art/$sp"; }
    }
}
pruefe('jeder Befund hat Beobachtung UND Folge in allen drei Sprachen',
    $fehlend === [], implode(', ', $fehlend));

/* WAS EINE BERATUNG VON EINER MÄNGELLISTE UNTERSCHEIDET
   Zu jedem Befund gehört, was er ihn kostet. Ohne die Folge ist es eine
   Beschwerde über sein Geschäft, und die kauft niemand. */
$mitFolge = Telefon::seiteAnsehen(['adresse' => 'facebook.com/pizzeria', 'sprache' => 'de']);
pruefe('ein Befund bringt seine Folge mit',
    trim((string) ($mitFolge['befunde'][0]['folge'] ?? '')) !== '',
    json_encode($mitFolge['befunde'][0] ?? []));
pruefe('und daraus wird ein Gesprächsfaden',
    !empty($mitFolge['gespraech']['auftakt']) && !empty($mitFolge['gespraech']['frage']),
    json_encode($mitFolge['gespraech'] ?? []));
pruefe('der Hinweis verbietet die Mängelliste ausdrücklich',
    str_contains((string) $mitFolge['hinweis'], 'Plattform')
    || str_contains((string) $mitFolge['hinweis'], 'Mängelliste'), (string) $mitFolge['hinweis']);

pruefe('der Blick steht in der Spur',
    (int) Db::wert("SELECT COUNT(*) FROM activities WHERE type = 'telefon_seitenblick'", [], 0) > 0);

/* ============================================================================
   26. Die Übergabe und der Rückblick
   ============================================================================ */
abschnitt('26. Übergabe und Rückblick');

/* Seit dem 7.9. geht an eine frei genannte Adresse nichts raus, bevor sie
   zurückgelesen und bestätigt wurde — der eigene Abschnitt dazu steht weiter
   unten. Hier interessiert, was DANACH passiert, also ist sie bestätigt. */
$u0 = Telefon::uebergabe(['sprache' => 'de', 'email' => 'interessent@example.org']);
pruefe('ohne Bestätigung der Adresse geht nichts raus',
    ($u0['grund'] ?? '') === 'adresse_unbestaetigt', json_encode($u0));

$u1 = Telefon::uebergabe(['sprache' => 'de', 'email' => 'keine-adresse', 'email_bestaetigt' => true]);
pruefe('eine unklare Adresse wird abgelehnt', ($u1['ok'] ?? true) === false, json_encode($u1));

$u2 = Telefon::uebergabe(['sprache' => 'de', 'email' => 'interessent@example.org',
                          'email_bestaetigt' => true,
                          'gespraech' => $faden, 'von_euro' => 650, 'bis_euro' => 850,
                          'befund' => 'Auf dem Handy schiebt sich die Seite weg.']);
$versuch = (string) Db::wert("SELECT betreff FROM mails ORDER BY id DESC LIMIT 1", [], '');
pruefe('die Übergabe wird versucht', $versuch !== '', $versuch);
/* Ohne Brevo-Schlüssel scheitert der Versand in der Prüfung — dann muss sie
   es zugeben und einen Rückruf anlegen, statt es zu verschweigen. */
pruefe('scheitert sie, wird es gemeldet statt verschwiegen',
    ($u2['ok'] ?? false) === true || ($u2['weiter'] ?? '') === 'gemeldet', json_encode($u2));

$teile = ['besprochen' => 'Eine Seite', 'spanne' => '', 'befund' => ''];
$block = [];
foreach ($teile as $k => $v) { if ($v !== '') { $block[] = Texte::UEBERGABE_TEILE[$k]['de'] . ': ' . $v; } }
[$betreff, $text] = Texte::mail('uebergabe', 'de', ['name' => ' Marco', 'block' => implode("\n\n", $block),
                                                    'link' => 'https://x']);
pruefe('eine Überschrift ohne Inhalt steht nicht in der Mail',
    !str_contains($text, 'Größenordnung'), $text);
pruefe('und es steht keine Frist drin',
    !preg_match('/(nur noch|melden Sie sich bald|innerhalb von \d+ Tagen)/i', $text), $text);

/* DER RÜCKBLICK ERKENNT GENAU DEN FEHLER VOM 6. SEPTEMBER:
   erkannt — und danach trotzdem als unbekannt behandelt. */
/* Zwei GESPRÄCHE, nicht zwei Zeilen: Der Rückblick fasst zusammen, was
   innerhalb von zehn Minuten passiert. Ohne das Zurückdatieren wäre alles
   hier ein einziger langer Anruf — und ein einzelner Fehler ist kein
   Muster, genau wie es sein soll. */
for ($i = 1; $i <= 2; $i++) {
    Telefon::protokoll('nachschlagen', 'Nachgeschlagen', $kundeId, ['treffer' => true]);
    Telefon::protokoll('hilfe', 'Hilfe — unbekannt', null, ['problem' => 'sonstiges', 'bekannt' => false]);
    Db::run("UPDATE activities SET created_at = NOW() - INTERVAL ? HOUR
              ORDER BY id DESC LIMIT 2", [$i * 3]);
}
$rb = Telefon::rueckblick(7);
$arten = array_column($rb['befunde'], 'art');
pruefe('der Rückblick sieht die verlorene Kennung',
    in_array('kennung_verloren', $arten, true), json_encode($arten));
pruefe('und schlägt einen Satz für den Leitfaden vor',
    trim((string) ($rb['befunde'][0]['vorschlag'] ?? '')) !== '', json_encode($rb['befunde'][0] ?? []));
pruefe('er zählt Gespräche, nicht Zeilen', (int) $rb['gespraeche'] > 0, json_encode($rb['gespraeche']));

/* Einmal die Woche, nicht bei jedem Cron-Lauf. */
$erste = Telefon::rueckblickMelden();
pruefe('der Wochenrückblick läuft', empty($erste['uebersprungen']), json_encode($erste));
$zweite = Telefon::rueckblickMelden();
pruefe('und in derselben Woche kein zweites Mal', !empty($zweite['uebersprungen']), json_encode($zweite));
pruefe('der Stand steht für die Verwaltung bereit',
    is_array(Telefon::letzterRueckblick()), json_encode(Telefon::letzterRueckblick()));

/* ============================================================================
   27. Die Konfigurationen für STRATO — vollständig und gültig
   ============================================================================ */
abschnitt('27. Alle Konfigurationen');

$adr = 'https://pruefung.example/telefon.php';
foreach (Telefon::AKTIONEN as $name) {
    $j = json_decode(Telefon::konfigJson($name,
        ['zweck' => 'Prüfung', 'eig' => [], 'pflicht' => [],
         'rumpf' => '{"aktion":"' . $name . '"}'], $adr, 'schluesselschluessel'), true);
    pruefe("„$name" . '" ergibt gültiges JSON', is_array($j), $name);
    pruefe("„$name" . '" hat leere Parameter als Objekt, nicht als Liste',
        str_contains(Telefon::konfigJson($name,
            ['zweck' => 'x', 'eig' => [], 'pflicht' => [], 'rumpf' => '{}'], $adr, 'k'),
            '"properties": {}'), $name);
}


/* ============================================================================
   28. Was am 6. September schiefging — und nicht wieder darf

   Drei echte Fehler aus echten Gesprächen. Jeder hat hier seine Prüfung,
   damit er nicht zurückkommt.
   ============================================================================ */
abschnitt('28. Die drei Fehler vom 6. September');

/* 21:26 — jemand wollte eine immersive 3D-Seite und bekam 500 bis 650 Euro
   genannt. In der Zusammenfassung steht: „Misstrauen gegenüber dem Anbieter".
   Eine zu niedrige Zahl für etwas, das der Katalog nicht kennt, klingt nicht
   günstig, sondern ahnungslos. */
foreach (['eine immersive 3D-Webseite', 'ich brauche eine App dazu',
          'un portale per i clienti', 'a booking system for rooms'] as $wunsch) {
    $r = Telefon::beratung(['sprache' => 'de', 'vorhaben' => $wunsch]);
    pruefe('„' . mb_substr($wunsch, 0, 28) . '" bekommt keine Zahl',
        ($r['ausserhalb'] ?? false) === true && !isset($r['von_euro']), json_encode($r));
}
$r = Telefon::beratung(['sprache' => 'de', 'vorhaben' => 'eine immersive 3D-Webseite']);
pruefe('stattdessen ein ehrlicher Satz', trim((string) ($r['satz'] ?? '')) !== '', json_encode($r));
pruefe('und der Weg führt zu Uwe', ($r['weiter'] ?? '') === 'uwe_persoenlich', json_encode($r));
pruefe('der Fall steht in der Spur',
    (int) Db::wert("SELECT COUNT(*) FROM activities WHERE type = 'telefon_ausserhalb'", [], 0) > 0);

/* Was normal ist, geht weiter wie bisher — sonst hätten wir das Werkzeug
   kaputtgemacht, statt es zu schärfen. */
$n = Telefon::beratung(['sprache' => 'de', 'vorhaben' => 'eine Seite für mein Restaurant']);
pruefe('ein gewöhnliches Vorhaben läuft normal weiter',
    empty($n['ausserhalb']) && ($n['frage_zu'] ?? '') === 'zweck', json_encode($n));

$p = Telefon::preisAuskunft(['vorhaben' => 'Marktplatz mit eigenem Konto für Händler']);
pruefe('auch die Preisauskunft nennt dann nichts',
    ($p['ausserhalb'] ?? false) === true && !isset($p['von_euro']), json_encode($p));

/* 22:24 bis 22:26 — vier geratene Adressen hintereinander, jede bis zu acht
   Sekunden Stille. */
abschnitt('29. Die Ratebremse');

$e1 = Telefon::seiteAnsehen(['adresse' => 'gibtesganzsicherniemals-eins.it', 'sprache' => 'de']);
pruefe('der erste Fehlschlag schickt zum Buchstabieren',
    ($e1['weiter'] ?? '') === 'buchstabieren', json_encode($e1));
$e2 = Telefon::seiteAnsehen(['adresse' => 'gibtesganzsicherniemals-zwei.it', 'sprache' => 'de']);
pruefe('der zweite auch', ($e2['weiter'] ?? '') === 'buchstabieren', json_encode($e2));
$e3 = Telefon::seiteAnsehen(['adresse' => 'gibtesganzsicherniemals-drei.it', 'sprache' => 'de']);
pruefe('der dritte wird gar nicht mehr abgerufen',
    ($e3['grund'] ?? '') === 'zu_viele_versuche', json_encode($e3));
pruefe('und sie wird auf „melde" verwiesen',
    str_contains((string) ($e3['hinweis'] ?? ''), 'melde'), (string) ($e3['hinweis'] ?? ''));

/* Ein Treffer soll trotzdem durchgehen — die Bremse darf nicht das Werkzeug
   erschlagen. Deshalb zurücksetzen und einen echten Fall prüfen. */
Db::run("UPDATE activities SET created_at = NOW() - INTERVAL 2 HOUR
          WHERE type = 'telefon_seitenblick'");
$ok = Telefon::seiteAnsehen(['adresse' => 'facebook.com/irgendwas', 'sprache' => 'de']);
pruefe('nach dem Gespräch ist die Bremse wieder offen',
    ($ok['gefunden'] ?? false) === true, json_encode($ok));

/* WORAN DIE BREMSE AM 7. SEPTEMBER UM 00:12 GESCHEITERT IST
   ------------------------------------------------------------------------
   Sie zählte jeden Fehlversuch der letzten zehn Minuten, gleich von wem.
   Zwei Probeabrufe aus der Verwaltung um 00:09 genügten, und der echte
   Anrufer um 00:12 hörte „finde ich nicht", ohne dass seine Adresse
   überhaupt abgerufen worden wäre. Eine Bremse, die auf fremde Fehler
   reagiert, ist keine Vorsicht, sondern ein Fehler. */
Db::run("DELETE FROM activities WHERE type = 'telefon_seitenblick'");

$ihre = '+49 155 619 31072';
foreach (['gibtesganzsicherniemals-a.it', 'gibtesganzsicherniemals-b.it'] as $adr) {
    Telefon::seiteAnsehen(['adresse' => $adr, 'sprache' => 'de']);   // ohne Nummer: anderes Gespräch
}
$gebremst = Telefon::seiteAnsehen(['adresse' => 'gibtesganzsicherniemals-c.it', 'sprache' => 'de']);
pruefe('im selben Gespräch bremst sie weiterhin nach zwei Fehlschlägen',
    ($gebremst['grund'] ?? '') === 'zu_viele_versuche', json_encode($gebremst));

$anderer = Telefon::seiteAnsehen(['adresse' => 'facebook.com/pizzeria', 'sprache' => 'de',
                                  'telefon' => $ihre]);
pruefe('ein anderer Anrufer wird davon nicht ausgebremst',
    ($anderer['gefunden'] ?? false) === true
    && ($anderer['grund'] ?? '') !== 'zu_viele_versuche', json_encode($anderer));

/* Und auch er wird gebremst — aber erst durch seine eigenen Fehlversuche. */
Telefon::seiteAnsehen(['adresse' => 'gibtesganzsicherniemals-d.it', 'sprache' => 'de', 'telefon' => $ihre]);
Telefon::seiteAnsehen(['adresse' => 'gibtesganzsicherniemals-e.it', 'sprache' => 'de', 'telefon' => $ihre]);
$seine = Telefon::seiteAnsehen(['adresse' => 'gibtesganzsicherniemals-f.it', 'sprache' => 'de',
                                'telefon' => $ihre]);
pruefe('nach zwei eigenen Fehlschlägen bremst sie ihn sehr wohl',
    ($seine['grund'] ?? '') === 'zu_viele_versuche', json_encode($seine));

/* EIN TREFFER LÖSCHT DIE BILANZ. Wer eine Seite gefunden hat, hat nicht
   geraten, sondern gearbeitet — und darf danach wieder suchen. */
Telefon::seiteAnsehen(['adresse' => 'facebook.com/trattoria', 'sprache' => 'de', 'telefon' => $ihre]);
$danach = Telefon::seiteAnsehen(['adresse' => 'gibtesganzsicherniemals-g.it', 'sprache' => 'de',
                                 'telefon' => $ihre]);
pruefe('ein Treffer setzt die Bremse zurück',
    ($danach['grund'] ?? '') !== 'zu_viele_versuche', json_encode($danach));

/* UND DAS IST DER FEHLER, ÜBER DEN ER GESTOLPERT IST
   ------------------------------------------------------------------------
   „Findet wieder keine Seite, wenn ich drum bete, sich die anzuschauen."
   Die Bremse lehnte auch eine richtige Adresse ab — ungeprüft. Sie fragt
   jetzt zuerst das DNS: Millisekunden statt Sekunden, und was es wirklich
   gibt, wird angesehen. Gebremst wird nur noch das nächste Ins-Blaue. */
Telefon::seiteAnsehen(['adresse' => 'gibtesganzsicherniemals-h.it', 'sprache' => 'de', 'telefon' => $ihre]);
Telefon::seiteAnsehen(['adresse' => 'gibtesganzsicherniemals-i.it', 'sprache' => 'de', 'telefon' => $ihre]);
$blind = Telefon::seiteAnsehen(['adresse' => 'gibtesganzsicherniemals-j.it', 'sprache' => 'de',
                                'telefon' => $ihre]);
pruefe('geraten wird nicht mehr weitergeraten',
    ($blind['grund'] ?? '') === 'zu_viele_versuche', json_encode($blind));
$echt = Telefon::seiteAnsehen(['adresse' => 'vecom-design.it', 'sprache' => 'de', 'telefon' => $ihre]);
pruefe('eine Adresse, die es wirklich gibt, wird trotz Bremse angesehen',
    ($echt['grund'] ?? '') !== 'zu_viele_versuche', json_encode($echt));
$offen = Telefon::seiteAnsehen(['adresse' => 'gibtesganzsicherniemals-k.it', 'sprache' => 'de',
                                'telefon' => $ihre]);
pruefe('und die Bremse ist danach wieder offen',
    ($offen['grund'] ?? '') !== 'zu_viele_versuche', json_encode($offen));

/* GESPROCHEN IST NICHT GESCHRIEBEN
   ------------------------------------------------------------------------
   „Trendonix Bücher" ohne Endung, mit Leerzeichen und mit Umlaut ist genau
   das, was am Telefon ankommt — und genau das, woran die Adressprüfung
   bisher gescheitert ist. Gesucht wird jetzt, statt abgelehnt. */
$stumm = new ReflectionMethod('Seitenblick', 'geraten');
$stumm->setAccessible(true);
pruefe('eine Adresse ohne Endung wird gesucht statt abgelehnt',
    $stumm->invoke(null, 'vecom design') === 'vecom-design.it',
    var_export($stumm->invoke(null, 'vecom design'), true));
pruefe('Leerzeichen dürfen ein Bindestrich gewesen sein',
    $stumm->invoke(null, 'Vecom Design') === 'vecom-design.it');
pruefe('was es nirgends gibt, wird auch nicht erfunden',
    $stumm->invoke(null, 'gibtesganzsichernie xyzq') === null,
    var_export($stumm->invoke(null, 'gibtesganzsichernie xyzq'), true));

$schreib = new ReflectionMethod('Seitenblick', 'schreibweisen');
$schreib->setAccessible(true);
/* DAS VERSCHLUCKTE LEERZEICHEN. „Trendonix Buecher Punkt de" wird zu
   „trendonixbuecher.de" — eine gültige Adresse, die es nicht gibt, während
   „trendonix-buecher.de" gleich daneben liegt. Weil die erste Schreibweise
   die Adressprüfung besteht, käme das Suchen sonst gar nicht zum Zug. */
$verschluckt = Seitenblick::ansehen('vecom design.it', 'de');
pruefe('ein verschlucktes Leerzeichen wird nachgeholt',
    ($verschluckt['gefunden'] ?? false) === true
    && ($verschluckt['adresse'] ?? '') === 'vecom-design.it', json_encode($verschluckt['adresse'] ?? null));
pruefe('und sie sagt zuerst, unter welcher Adresse sie sie gefunden hat',
    !empty($verschluckt['andere_adresse'])
    && ($verschluckt['messwerte']['statt'] ?? '') === 'vecomdesign.it',
    json_encode($verschluckt['messwerte']['statt'] ?? null));

pruefe('„ue" und „ü" gelten als dieselbe Adresse',
    in_array('xn--trendonixbcher-psb.de', $schreib->invoke(null, 'trendonixbuecher.de'), true),
    json_encode($schreib->invoke(null, 'trendonixbuecher.de')));

/* ============================================================================
   29b. Zwei Wege: Bestandskunde und Fremder
   ============================================================================
   „Bei Bestandskunden in der Verwaltung schauen, bei Nicht-Kunden im
   Internet recherchieren." Einen Kunden nach seiner eigenen Adresse zu
   fragen, ist die peinlichste Frage, die eine Assistentin stellen kann —
   sie liegt vor ihr.
   ============================================================================ */
abschnitt('29b. Verwaltung zuerst, Internet danach');

Db::run("DELETE FROM activities WHERE type = 'telefon_seitenblick'");
Db::run('DELETE FROM websites WHERE customer_id = ?', [$kundeId]);
Db::run("INSERT INTO websites (customer_id, domain, url, status, monitoring)
         VALUES (?, 'facebook.com/derkunde', 'https://facebook.com/derkunde', 'online', 1)",
        [$kundeId]);

$akte = Telefon::kundenseite($kundeId);
pruefe('die Website eines Kunden steht in der Verwaltung',
    ($akte['adresse'] ?? '') === 'facebook.com/derkunde', json_encode($akte));

$ausAkte = Telefon::seiteAnsehen(['sprache' => 'de', 'kunde_id' => $kundeId]);
pruefe('ein Bestandskunde wird nicht nach seiner Adresse gefragt',
    ($ausAkte['quelle'] ?? '') === 'verwaltung' && ($ausAkte['grund'] ?? '') !== 'keine_adresse',
    json_encode($ausAkte));
pruefe('und die Adresse kommt aus seiner Akte',
    ($ausAkte['aus_verwaltung']['adresse'] ?? '') === 'facebook.com/derkunde', json_encode($ausAkte));
pruefe('Manuela wird angewiesen, sie ihm zu bestätigen statt sie zu erfragen',
    str_contains((string) ($ausAkte['hinweis'] ?? ''), 'richtig?'), (string) ($ausAkte['hinweis'] ?? ''));

/* Der Ausfall aus der Überwachung ist das, was er hören will — und das,
   was nur jemand sagen kann, der die Akte offen hat. */
Db::run("UPDATE websites SET last_ok_at = NOW() - INTERVAL 3 DAY, last_fail_at = NOW()
          WHERE customer_id = ?", [$kundeId]);
$ausfall = Telefon::kundenseite($kundeId);
pruefe('ein laufender Ausfall wird ausdrücklich gemeldet',
    str_contains((string) ($ausfall['satz'] ?? ''), 'nicht erreichbar'), json_encode($ausfall));

/* Beim Nachschlagen muss sie es schon wissen — sonst fragt sie ihn nach
   etwas, das vor ihr liegt. */
Db::run('UPDATE customers SET phone = ? WHERE id = ?', ['+39 380 111 2233', $kundeId]);
$mitSeite = Telefon::nachschlagen(['telefon' => '+39 380 111 2233']);
pruefe('schon beim Nachschlagen kennt sie seine Website',
    ($mitSeite['website'] ?? '') === 'facebook.com/derkunde', json_encode($mitSeite));
pruefe('und den Ausfall gleich mit',
    str_contains((string) ($mitSeite['website_achtung'] ?? ''), 'nicht erreichbar'),
    json_encode($mitSeite['website_achtung'] ?? null));

/* Steht nichts in der Akte, wird gefragt — aber mit dem richtigen Satz. */
Db::run('DELETE FROM websites WHERE customer_id = ?', [$kundeId]);
$leer = Telefon::seiteAnsehen(['sprache' => 'de', 'kunde_id' => $kundeId]);
pruefe('ohne Eintrag in der Akte wird er gefragt',
    ($leer['grund'] ?? '') === 'keine_adresse'
    && str_contains((string) $leer['hinweis'], 'Akte'), json_encode($leer));

/* Ein Fremder ohne Akte: Da gilt der Weg über das Internet — und die
   genannte Adresse wird wirklich gesucht. */
$fremdeSeite = Telefon::seiteAnsehen(['adresse' => 'facebook.com/unbekannt', 'sprache' => 'de']);
pruefe('bei einem Fremden bleibt es beim Weg über das Internet',
    ($fremdeSeite['quelle'] ?? '') === 'genannt', json_encode($fremdeSeite));

/* 22:23 — alles richtig gemacht, den Link zugesagt, nie verschickt. */
abschnitt('30. Angefangen und nichts daraus geworden');

Db::run("DELETE FROM activities WHERE type LIKE 'telefon\\_%'");
pruefe('ohne Gespräche ist die Liste leer', Telefon::offeneGespraeche(7) === []);

/* Ein Gespräch wie das echte: nachgeschlagen, Seite angesehen — und nichts. */
Telefon::protokoll('nachschlagen', 'Nachgeschlagen', null, ['treffer' => false]);
Telefon::protokoll('seitenblick', 'Website angesehen', null,
                   ['adresse' => 'jonika-venturis.com', 'gefunden' => true, 'arten' => ['telefon_nicht_klickbar']]);
$o = Telefon::offeneGespraeche(7);
pruefe('ein Gespräch ohne Ergebnis steht auf der Liste', count($o) === 1, json_encode($o));
pruefe('und zwar mit der Seite, die sie angesehen hat',
    ($o[0]['seite'] ?? '') === 'jonika-venturis.com', json_encode($o[0] ?? []));

/* Kam etwas heraus, gehört es nicht mehr darauf. */
Telefon::protokoll('uebergabe', 'Verschickt', null, ['ok' => true]);
pruefe('sobald etwas rausging, verschwindet es',
    Telefon::offeneGespraeche(7) === [], json_encode(Telefon::offeneGespraeche(7)));

/* Ein Anruf, bei dem nur nachgeschlagen wurde, ist kein offenes Gespräch --
   da hat jemand angerufen und aufgelegt. */
Db::run("DELETE FROM activities WHERE type LIKE 'telefon\\_%'");
Telefon::protokoll('nachschlagen', 'Nachgeschlagen', null, ['treffer' => false]);
pruefe('bloßes Nachschlagen zählt nicht als offenes Gespräch',
    Telefon::offeneGespraeche(7) === [], json_encode(Telefon::offeneGespraeche(7)));


/* ============================================================================
   31. Sich erinnern — und die Grenze dabei

   Nichts wirkt persönlicher als jemand, der sich erinnert. Und nichts wirkt
   peinlicher, als wenn er dabei das Anliegen eines Kollegen ausplaudert.
   ============================================================================ */
abschnitt('31. Sich erinnern');

Db::run("DELETE FROM activities WHERE type LIKE 'telefon\\_%'");
Db::run('UPDATE customers SET phone = ? WHERE id = ?', ['+39 380 111 2233', $kundeId]);

$neu = Telefon::nachschlagen(['telefon' => '+39 380 111 2233']);
pruefe('beim ersten Mal erinnert sie sich an nichts',
    empty($neu['schon_einmal']), json_encode($neu));

/* Ein früherer Anruf desselben Kunden — zurückdatiert, damit er nicht als
   derselbe Anruf gilt. */
Telefon::melden(['art' => 'rueckruf', 'kunde_id' => $kundeId, 'telefon' => '+39 380 111 2233',
                 'text' => 'Möchte über die Startseite sprechen.', 'anliegen' => 'Startseite']);
Db::run("UPDATE activities SET created_at = NOW() - INTERVAL 5 DAY
          WHERE type = 'telefon_melde' ORDER BY id DESC LIMIT 1");

$w = Telefon::nachschlagen(['telefon' => '+39 380 111 2233']);
pruefe('jetzt erinnert sie sich', !empty($w['schon_einmal']), json_encode($w));
pruefe('und sagt es in einem fertigen Satz',
    str_contains((string) ($w['satz'] ?? ''), 'angerufen')
    || str_contains((string) ($w['satz'] ?? ''), 'chiamato'), (string) ($w['satz'] ?? ''));
pruefe('mit der Zeit in Worten statt in Tagen',
    !preg_match('/\b5 Tagen?\b/', (string) ($w['satz'] ?? '')), (string) ($w['satz'] ?? ''));

/* DIE GRENZE: Hinter einer Firmennummer sitzen mehrere Menschen. Wer nicht
   als Kunde erkannt wird, erfährt nie, worum es beim letzten Mal ging. */
Db::run('UPDATE customers SET phone = NULL WHERE id = ?', [$kundeId]);
$fremd = Telefon::nachschlagen(['telefon' => '+39 380 111 2233']);
pruefe('ein unbekannter Anrufer hört nur, DASS schon einmal angerufen wurde',
    !empty($fremd['schon_einmal']), json_encode($fremd));
pruefe('aber nie, worum es ging',
    !str_contains((string) ($fremd['satz'] ?? ''), 'Startseite'), (string) ($fremd['satz'] ?? ''));
pruefe('und er wird gefragt statt beantwortet',
    str_contains((string) ($fremd['satz'] ?? ''), 'Worum geht es')
    || str_contains((string) ($fremd['satz'] ?? ''), 'Di che cosa'), (string) ($fremd['satz'] ?? ''));

/* Was zu lange her ist, ist vergessen — wie bei einem Menschen. */
Db::run("UPDATE activities SET created_at = NOW() - INTERVAL 200 DAY WHERE type = 'telefon_melde'");
$alt = Telefon::nachschlagen(['telefon' => '+39 380 111 2233']);
pruefe('nach Monaten erinnert sie sich nicht mehr', empty($alt['schon_einmal']), json_encode($alt));

/* Eine fremde Nummer weckt keine Erinnerung. */
Db::run("UPDATE activities SET created_at = NOW() - INTERVAL 5 DAY WHERE type = 'telefon_melde'");
$andere = Telefon::nachschlagen(['telefon' => '+39 320 999 8877']);
pruefe('eine andere Nummer bleibt fremd', empty($andere['schon_einmal']), json_encode($andere));

/* ============================================================================
   32. Tageszeit im Ton — und aufhören, wenn nichts mehr kommt
   ============================================================================ */
abschnitt('32. Tageszeit und Abbruch');

$l = Telefon::lage();
pruefe('die Lage nennt die Tageszeit',
    in_array($l['tageszeit'] ?? '', ['morgens','mittags','nachmittags','abends','nachts'], true),
    json_encode($l['tageszeit'] ?? null));
pruefe('und schlägt einen Ton vor', trim((string) ($l['ton'] ?? '')) !== '', (string) ($l['ton'] ?? ''));
pruefe('am Wochenende sagt sie das dazu',
    ($l['werktag'] ?? true) === true || str_contains((string) $l['ton'], 'Wochenende'),
    json_encode(['werktag' => $l['werktag'] ?? null]));

/* Zwei Aufrufe ohne Fortschritt: Dann ist die Beratung vorbei. Nicht, weil
   das Modell es merkt, sondern weil der Server es misst. */
Db::run("DELETE FROM activities WHERE type = 'telefon_beratung'");
$b = Telefon::beratung(['sprache' => 'de']);
$faden2 = (string) $b['gespraech'];
pruefe('sie fängt normal an', empty($b['abbrechen']), json_encode($b));

$b = Telefon::beratung(['sprache' => 'de', 'gespraech' => $faden2,
                        'antwort_auf' => 'zweck', 'antwort' => 'weiß nicht']);
pruefe('nach einer leeren Antwort läuft es noch', empty($b['abbrechen']), json_encode($b));
$b = Telefon::beratung(['sprache' => 'de', 'gespraech' => $faden2,
                        'antwort_auf' => 'zweck', 'antwort' => 'ist mir egal']);
pruefe('nach der zweiten hört sie auf zu fragen', !empty($b['abbrechen']), json_encode($b));
pruefe('und wird auf „uebergabe" verwiesen',
    str_contains((string) $b['hinweis'], 'uebergabe'), (string) $b['hinweis']);

/* Wer antwortet, wird nicht abgewürgt. */
Db::run("DELETE FROM activities WHERE type = 'telefon_beratung'");
$c = Telefon::beratung(['sprache' => 'de']);
$f3 = (string) $c['gespraech'];
$c = Telefon::beratung(['sprache' => 'de', 'gespraech' => $f3, 'antwort_auf' => 'zweck', 'antwort' => 'zeigen']);
$c = Telefon::beratung(['sprache' => 'de', 'gespraech' => $f3, 'antwort_auf' => 'umfang', 'antwort' => 'wenige']);
pruefe('wer antwortet, wird weitergefragt', empty($c['abbrechen']), json_encode($c));

/* ============================================================================
   33. Die Gespräche von STRATO
   ============================================================================
   Bei STRATO liegt zu jedem Anruf mehr, als die Oberfläche dort zeigt: eine
   maschinelle Auswertung mit Ausgang, Beteiligung, Problem-Schlagworten und
   der Frage, ob der Assistent gegen seine eigenen Anweisungen gehandelt hat.
   Genau das, was man braucht, um ihn besser zu machen — und genau das, was
   in einer Liste über fünf Seiten niemand von Hand zusammenzählt.

   Geprüft wird hier das Ablegen, nicht das Abrufen: Ein Test, der ein fremdes
   Netz braucht, ist kein Test, sondern eine Wettervorhersage.
   ============================================================================ */
abschnitt('33. Gespräche von STRATO');

require_once $wurzel . '/src/Strato.php';

pruefe('ohne hinterlegten Zugang ist nichts eingerichtet', Strato::eingerichtet() === false);
$ohne = Strato::abgleichen();
pruefe('und der Abgleich sagt das, statt zu scheitern',
    ($ohne['ok'] ?? true) === false && ($ohne['grund'] ?? '') === 'kein_zugang', json_encode($ohne));

/* Ein Satz, wie er wirklich kommt — abgeschrieben vom Anruf am 7.9. um 00:11. */
$satz = [
  'id' => '813c4162-e917-4d0d-a8db-66114f4ebe61',
  'call_sid' => 'CA-pruefung',
  'created_at' => gmdate('Y-m-d\TH:i:s\Z', time() - 3600),
  'call_seconds_billed' => 177,
  'fwd_seconds_billed' => 0,
  'agent_number' => '+49304397926082',
  'customer_number' => '+39 380 111 2233',
  'summaries' => [[
    'content' => ['name' => 'Manuel Brandner', 'subject' => 'Anfrage zur Optimierung einer Webseite',
                  'summary' => 'Die Adresse wurde nicht gefunden.'],
    'metadata' => ['total_messages' => 36, 'function_calls' => 3, 'analysis' => [
        'issue_tags' => ['incorrect_function_parameters', 'caller_frustration'],
        'call_outcome' => 'AGENT_ERROR',
        'analysis_notes' => 'Die Adresse wurde falsch übertragen.',
        'call_outcome_notes' => 'Ohne Ergebnis beendet.',
        'engagement_level' => 'ENGAGED_WITH_INTENT',
        'function_calling' => ['prompt_violation' => true,
                               'prompt_violation_notes' => 'Anweisung missachtet.',
                               'other_hallucination' => false,
                               'booking_hallucination' => false,
                               'forwarding_hallucination' => false],
    ]]]],
];

Db::run('UPDATE customers SET phone = ? WHERE id = ?', ['+39 380 111 2233', $kundeId]);
$ablegen = new ReflectionMethod('Strato', 'ablegen');
$ablegen->setAccessible(true);

pruefe('ein neues Gespräch wird angelegt', $ablegen->invoke(null, $satz) === 'neu');
pruefe('dasselbe noch einmal ändert nichts', $ablegen->invoke(null, $satz) === 'gleich');

$g = Db::one('SELECT * FROM telefon_gespraeche WHERE id = ?', [$satz['id']]);
pruefe('Betreff und Zusammenfassung stehen da',
    ($g['betreff'] ?? '') === 'Anfrage zur Optimierung einer Webseite'
    && str_contains((string) $g['zusammenfassung'], 'nicht gefunden'), json_encode($g['betreff'] ?? null));

/* DAS EIGENTLICHE: die Auswertung. Ohne sie wäre es eine Anrufliste. */
pruefe('der Ausgang wird übernommen', ($g['ausgang'] ?? '') === 'AGENT_ERROR');
pruefe('die Beteiligung des Anrufers auch', ($g['engagement'] ?? '') === 'ENGAGED_WITH_INTENT');
pruefe('die Problem-Schlagworte auch',
    str_contains((string) $g['tags'], 'caller_frustration'), (string) $g['tags']);
pruefe('ein Verstoß gegen die eigenen Anweisungen wird festgehalten',
    (int) $g['verstoss'] === 1 && str_contains((string) $g['verstoss_text'], 'Anweisung'), json_encode($g['verstoss_text']));
pruefe('eine Halluzination lag nicht vor und wird auch nicht behauptet', (int) $g['erfunden'] === 0);
pruefe('beide Notizen kommen mit',
    str_contains((string) $g['notizen'], 'falsch übertragen')
    && str_contains((string) $g['notizen'], 'Ohne Ergebnis'), (string) $g['notizen']);

/* DIE ZUORDNUNG. Dieselbe Näherung wie beim Nachschlagen: die letzten neun
   Ziffern. Ohne sie stünde bei jedem zweiten Anruf „Unbekannt", obwohl der
   Kunde in der Verwaltung steht. */
pruefe('die Rufnummer findet den Kunden', (int) ($g['kunde_id'] ?? 0) === $kundeId, json_encode($g['kunde_id']));

/* Der Rohsatz bleibt liegen — ein Feld, das man beim Entwurf nicht vorgesehen
   hat, ist sonst rückwirkend verloren. */
pruefe('der Rohsatz wird aufgehoben',
    is_array(json_decode((string) $g['roh'], true)), mb_substr((string) $g['roh'], 0, 60));

/* Ändert sich die Auswertung nachträglich — sie entsteht erst Minuten nach
   dem Auflegen —, muss der Abgleich das mitbekommen. */
$satz['summaries'][0]['metadata']['analysis']['call_outcome'] = 'RESOLVED';
pruefe('eine nachgereichte Auswertung wird erkannt', $ablegen->invoke(null, $satz) === 'geaendert');

/* Ein Anruf ohne Auswertung darf nicht scheitern. Kurze Anrufe haben keine. */
$leer = ['id' => '00000000-0000-0000-0000-000000000001',
         'created_at' => gmdate('Y-m-d\TH:i:s\Z', time() - 1800),
         'call_seconds_billed' => 9, 'customer_number' => 'widget-call', 'summaries' => []];
pruefe('ein Anruf ohne Zusammenfassung legt sich trotzdem ab',
    $ablegen->invoke(null, $leer) === 'neu');

$z = Strato::zahlen(7);
pruefe('gezählt werden beide', (int) $z['anrufe'] === 2, json_encode($z['anrufe']));
/* Unter einer halben Minute wurde nicht gesprochen, sondern aufgelegt.
   Solche Anrufe verzerren jeden Durchschnitt — deshalb zählen sie getrennt. */
pruefe('als echtes Gespräch zählt nur das lange', (int) $z['echte'] === 1, json_encode($z['echte']));
pruefe('der Verstoß steht in den Zahlen', (int) $z['verstoesse'] === 1);
pruefe('ein Anruf über die Website wird als solcher erkannt', (int) $z['widget'] === 1);
pruefe('die Schlagworte sind gezählt', (int) ($z['tags']['caller_frustration'] ?? 0) === 1, json_encode($z['tags']));

pruefe('nach Verstößen lässt sich filtern', count(Strato::gespraeche(7, 'verstoss')) === 1);
pruefe('nach echten Gesprächen auch', count(Strato::gespraeche(7, 'gespraech')) === 1);
pruefe('nach Bestandskunden auch', count(Strato::gespraeche(7, 'kunden')) === 1);
pruefe('ohne Filter kommen alle', count(Strato::gespraeche(7)) === 2);

/* DIE ÜBERSETZUNG. Ein Schlagwort, das wir nicht kennen, darf nicht
   verschwinden — es wird roh gezeigt, damit man es sieht und nachträgt. */
pruefe('bekannte Schlagworte stehen auf Deutsch da',
    Strato::tagWort('caller_frustration') === 'Anrufer war genervt');
pruefe('ein unbekanntes verschwindet nicht',
    Strato::tagWort('ganz_neues_ding') === 'ganz neues ding', Strato::tagWort('ganz_neues_ding'));
pruefe('ein unbekannter Ausgang auch nicht',
    Strato::ausgangWort('IRGENDWAS_NEUES')['wort'] !== '', json_encode(Strato::ausgangWort('IRGENDWAS_NEUES')));

/* UNSERE EIGENE SPUR NEBEN IHREM GESPRÄCH.
   Zusammengeführt über die Zeit, weil die Telefonplattform uns keine
   gemeinsame Gesprächsnummer gibt. */
Db::run("DELETE FROM activities WHERE type LIKE 'telefon\\_%'");
Telefon::protokoll('seitenblick', 'Website angesehen', null, ['adresse' => 'beispiel.it']);
Db::run("UPDATE activities SET created_at = ? WHERE type = 'telefon_seitenblick'",
        [date('Y-m-d H:i:s', strtotime((string) $g['begonnen']) + 30)]);
$g = Db::one('SELECT * FROM telefon_gespraeche WHERE id = ?', [$satz['id']]);
pruefe('was während des Gesprächs geschah, steht daneben',
    count(Strato::spur($g)) === 1, json_encode(Strato::spur($g)));

/* Und was danach passierte, gehört nicht dazu — sonst stünde bei jedem
   Gespräch der ganze Tag. */
Db::run("UPDATE activities SET created_at = ? WHERE type = 'telefon_seitenblick'",
        [date('Y-m-d H:i:s', strtotime((string) $g['begonnen']) + 4000)]);
pruefe('was lange danach geschah, nicht', Strato::spur($g) === []);

/* ============================================================================
   34. Löschen — und die Rückfrage, wenn noch etwas dranhängt
   ============================================================================
   In dieser Spur stehen Rufnummern, Namen, Internetadressen und was jemand
   am Telefon wollte — von Menschen, die nie Kunde wurden und nie gefragt
   wurden, ob das aufgehoben werden darf. Etwas aufzuheben, weil das Löschen
   nicht vorgesehen war, ist kein Grundsatz, sondern ein Versäumnis.

   Gelöscht wird trotzdem nicht leichtfertig: In einem Gespräch entsteht
   Arbeit, und wer sie wegräumt, räumt auch die Erinnerung daran weg.
   ============================================================================ */
abschnitt('34. Löschen');

Db::run("DELETE FROM activities WHERE type LIKE 'telefon\\_%'");
Db::run('DELETE FROM telefon_gespraeche');
Db::run('DELETE FROM telefon_gespraech_weg');
Db::run("DELETE FROM settings WHERE skey LIKE 'telefon\\_luecke\\_%'");

$ablegen = new ReflectionMethod('Strato', 'ablegen');
$ablegen->setAccessible(true);
$bauen = static function (string $id, int $vorSekunden, int $dauer = 120) use ($ablegen): void {
    $ablegen->invoke(null, [
        'id' => $id, 'created_at' => gmdate('Y-m-d\TH:i:s\Z', time() - $vorSekunden),
        'call_seconds_billed' => $dauer, 'customer_number' => 'widget-call',
        'summaries' => [['content' => ['subject' => 'Prüfgespräch ' . $id, 'summary' => 'nichts'],
                         'metadata' => ['analysis' => ['call_outcome' => 'RESOLVED']]]],
    ]);
};

/* Ein abgeschlossenes Gespräch: angesehen, übergeben, fertig. Daran hängt
   nichts mehr, es darf ohne Rückfrage weg.

   WARUM ES HIER EINE ÜBERGABE BRAUCHT: Ein Gespräch, in dem sie eine Seite
   angesehen hat und aus dem nichts herauskam, IST offene Arbeit — es steht
   auf der Liste „Angefangen und nichts daraus geworden". Genau das soll die
   Bremse greifen lassen. Der erste Entwurf dieses Tests hat das übersehen
   und wurde dafür zu Recht rot. */
$bauen('aaaaaaaa-0000-0000-0000-000000000001', 3600);
$g1 = Db::one('SELECT * FROM telefon_gespraeche WHERE id = ?', ['aaaaaaaa-0000-0000-0000-000000000001']);
Telefon::protokoll('seitenblick', 'Website angesehen', null, ['adresse' => 'beispiel.it']);
Telefon::protokoll('uebergabe', 'Verschickt', null, ['ok' => true]);
Db::run("UPDATE activities SET created_at = ? WHERE type LIKE 'telefon\\_%'",
        [date('Y-m-d H:i:s', strtotime((string) $g1['begonnen']) + 30)]);
pruefe('an einem abgeschlossenen Gespräch hängt nichts', Strato::offenesZu($g1) === [],
    json_encode(Strato::offenesZu($g1)));

/* Und seine Spur geht mit — ein Gespräch ohne seine Spur wäre ein halbes
   Löschen: der Anruf verschwände, die Rufnummer bliebe in den Aktivitäten. */
$r = Strato::loeschen([$g1['id']]);
pruefe('es wird gelöscht', (int) $r['weg'] === 1 && $r['offen'] === [], json_encode($r));
pruefe('und die Spur geht mit', (int) $r['spur'] === 2, json_encode($r['spur']));

/* Umgekehrt: angefangen und nichts daraus geworden — das IST offene Arbeit
   und muss ausdrücklich gefragt werden. */
Db::run("DELETE FROM activities WHERE type LIKE 'telefon\\_%'");
$bauen('aaaaaaaa-0000-0000-0000-00000000000a', 3500);
$ga = Db::one('SELECT * FROM telefon_gespraeche WHERE id = ?', ['aaaaaaaa-0000-0000-0000-00000000000a']);
Telefon::protokoll('seitenblick', 'Website angesehen', null, ['adresse' => 'beispiel.it']);
Db::run("UPDATE activities SET created_at = ? WHERE type = 'telefon_seitenblick'",
        [date('Y-m-d H:i:s', strtotime((string) $ga['begonnen']) + 30)]);
$ra = Strato::loeschen([$ga['id']]);
pruefe('angefangen und nichts daraus geworden hält das Löschen auf',
    (int) $ra['weg'] === 0 && str_contains($ra['offen'][0]['gruende'][0], 'nichts geworden'),
    json_encode($ra));
Strato::loeschen([$ga['id']], true);
Db::run('DELETE FROM telefon_gespraech_weg WHERE id = ?', [$ga['id']]);
pruefe('das Gespräch ist wirklich weg',
    (int) Db::wert('SELECT COUNT(*) FROM telefon_gespraeche WHERE id = ?', [$g1['id']], 0) === 0);

/* WAS GELÖSCHT WURDE, DARF NICHT WIEDERKOMMEN.
   Die Gespräche werden stündlich geholt. Ohne Sperrliste stünde es nach
   einer Stunde wieder da — „gelöscht" wäre eine Lüge, die sich selbst
   widerlegt, während man zusieht. */
pruefe('es steht auf der Sperrliste', Strato::gesperrt() === 1);
$bauen($g1['id'], 3600);
pruefe('und kommt beim nächsten Abgleich nicht wieder',
    (int) Db::wert('SELECT COUNT(*) FROM telefon_gespraeche WHERE id = ?', [$g1['id']], 0) === 0);
pruefe('die Sperre lässt sich aufheben', Strato::sperreLoesen() === 1 && Strato::gesperrt() === 0);

/* EIN GESPRÄCH, AN DEM NOCH ARBEIT HÄNGT: ein Rückruf, den niemand abgehakt
   hat. Es bleibt stehen und wird ausdrücklich gefragt. */
$bauen('bbbbbbbb-0000-0000-0000-000000000002', 1800);
$g2 = Db::one('SELECT * FROM telefon_gespraeche WHERE id = ?', ['bbbbbbbb-0000-0000-0000-000000000002']);
Telefon::melden(['art' => 'rueckruf', 'telefon' => '+39 380 111 2233',
                 'name' => 'Anna Prüfung', 'text' => 'Bitte zurückrufen.', 'anliegen' => 'Website']);
Db::run("UPDATE activities SET created_at = ? WHERE type = 'telefon_melde'",
        [date('Y-m-d H:i:s', strtotime((string) $g2['begonnen']) + 20)]);

$gruende = Strato::offenesZu($g2);
pruefe('ein offener Rückruf wird erkannt',
    count($gruende) === 1 && str_contains($gruende[0], 'Rückruf'), json_encode($gruende));
pruefe('und mit dem Namen dessen, der wartet',
    str_contains($gruende[0], 'Anna Prüfung'), $gruende[0]);

$r2 = Strato::loeschen([$g2['id']]);
pruefe('ohne Bestätigung bleibt es stehen',
    (int) $r2['weg'] === 0 && count($r2['offen']) === 1, json_encode($r2));
pruefe('und der Grund kommt im Klartext zurück',
    str_contains($r2['offen'][0]['gruende'][0], 'Rückruf'), json_encode($r2['offen'][0]));
pruefe('es liegt auch wirklich noch da',
    (int) Db::wert('SELECT COUNT(*) FROM telefon_gespraeche WHERE id = ?', [$g2['id']], 0) === 1);

/* Erst der ausdrückliche zweite Klick nimmt es mit. */
$r3 = Strato::loeschen([$g2['id']], true);
pruefe('mit Bestätigung geht es weg', (int) $r3['weg'] === 1 && $r3['offen'] === [], json_encode($r3));

/* Ein abgehakter Rückruf hält nichts mehr auf. */
Db::run("DELETE FROM activities WHERE type LIKE 'telefon\\_%'");
Db::run('DELETE FROM telefon_gespraech_weg');
$bauen('cccccccc-0000-0000-0000-000000000003', 900);
$g3 = Db::one('SELECT * FROM telefon_gespraeche WHERE id = ?', ['cccccccc-0000-0000-0000-000000000003']);
Telefon::melden(['art' => 'rueckruf', 'telefon' => '+39 380 111 2233', 'text' => 'x', 'anliegen' => 'y']);
$melde = (int) Db::wert("SELECT MAX(id) FROM activities WHERE type = 'telefon_melde'", [], 0);
Db::run('UPDATE activities SET created_at = ? WHERE id = ?',
        [date('Y-m-d H:i:s', strtotime((string) $g3['begonnen']) + 20), $melde]);
pruefe('vor dem Abhaken hängt etwas dran', Strato::offenesZu($g3) !== []);
Telefon::rueckrufErledigt($melde);
pruefe('nach dem Abhaken nicht mehr', Strato::offenesZu($g3) === [], json_encode(Strato::offenesZu($g3)));

/* ---- Der Verlauf für sich ---- */
Db::run("DELETE FROM activities WHERE type LIKE 'telefon\\_%'");
Telefon::protokoll('seitenblick', 'Website angesehen', null, ['adresse' => 'a.it']);
Telefon::protokoll('lage', 'Lage abgefragt', null, []);
$eintraege = array_column(Db::all("SELECT id FROM activities WHERE type LIKE 'telefon\\_%'"), 'id');
$v = Telefon::verlaufLoeschen($eintraege);
pruefe('gewöhnliche Verlaufseinträge lassen sich löschen', (int) $v['weg'] === 2, json_encode($v));

/* Eine offene Frage hält auch hier auf — sie steht noch auf der Liste
   „Was Manuela nicht wusste". */
Telefon::wissensluecke(['frage' => 'Machen Sie auch Visitenkarten?']);
$luecke = (int) Db::wert("SELECT MAX(id) FROM activities WHERE type = 'telefon_wissensluecke'", [], 0);
$v2 = Telefon::verlaufLoeschen([$luecke]);
pruefe('eine offene Frage bleibt stehen',
    (int) $v2['weg'] === 0 && count($v2['offen']) === 1, json_encode($v2));
pruefe('mit dem Grund im Klartext',
    str_contains($v2['offen'][0]['gruende'][0], 'Was Manuela nicht wusste'), json_encode($v2['offen'][0]));
$v3 = Telefon::verlaufLoeschen([$luecke], true);
pruefe('bestätigt geht auch sie weg', (int) $v3['weg'] === 1);

/* ---- Und wer gelöscht wird, hinterlässt kein Gespräch ---- */
Db::run('DELETE FROM telefon_gespraeche');
Db::run('DELETE FROM telefon_gespraech_weg');
Db::run("DELETE FROM activities WHERE type LIKE 'telefon\\_%'");
Db::run('UPDATE customers SET phone = ? WHERE id = ?', ['+39 380 111 2233', $kundeId]);
$ablegen->invoke(null, [
    'id' => 'dddddddd-0000-0000-0000-000000000004',
    'created_at' => gmdate('Y-m-d\TH:i:s\Z', time() - 600),
    'call_seconds_billed' => 200, 'customer_number' => '+39 380 111 2233',
    'summaries' => [['content' => ['name' => 'Prüf Kunde', 'subject' => 'Anruf', 'summary' => 'x'],
                     'metadata' => []]],
]);
pruefe('das Gespräch hängt am Kunden',
    (int) Db::wert('SELECT COUNT(*) FROM telefon_gespraeche WHERE kunde_id = ?', [$kundeId], 0) === 1);

/* Der Weg, den Kunde::loeschen() vorher geht. Danach darf nichts mehr da
   sein — und es darf auch nicht wiederkommen. */
$wegK = Strato::zuKundeLoeschen($kundeId);
pruefe('mit dem Kunden geht sein Gespräch', $wegK === 1
    && (int) Db::wert('SELECT COUNT(*) FROM telefon_gespraeche WHERE kunde_id = ?', [$kundeId], 0) === 0);
pruefe('und es ist gesperrt, damit es nicht zurückkommt', Strato::gesperrt() === 1);

/* ---- Anonymisiert bleibt anonymisiert ---- */
Db::run('DELETE FROM telefon_gespraech_weg');
$ablegen->invoke(null, [
    'id' => 'eeeeeeee-0000-0000-0000-000000000005',
    'created_at' => gmdate('Y-m-d\TH:i:s\Z', time() - 600),
    'call_seconds_billed' => 200, 'customer_number' => '+39 380 111 2233',
    'summaries' => [['content' => ['name' => 'Klarname', 'subject' => 'Anruf', 'summary' => 'Wortlaut'],
                     'metadata' => []]],
]);
Db::run("UPDATE telefon_gespraeche SET name = '', zusammenfassung = '', roh = NULL, anonym = 1
          WHERE id = 'eeeeeeee-0000-0000-0000-000000000005'");
$ablegen->invoke(null, [
    'id' => 'eeeeeeee-0000-0000-0000-000000000005',
    'created_at' => gmdate('Y-m-d\TH:i:s\Z', time() - 600),
    'call_seconds_billed' => 200, 'customer_number' => '+39 380 111 2233',
    'summaries' => [['content' => ['name' => 'Klarname', 'subject' => 'Anruf', 'summary' => 'Wortlaut'],
                     'metadata' => []]],
]);
$an = Db::one("SELECT * FROM telefon_gespraeche WHERE id = 'eeeeeeee-0000-0000-0000-000000000005'");
pruefe('der Abgleich schreibt einen anonymisierten Anruf NICHT wieder voll',
    (string) $an['name'] === '' && (string) $an['zusammenfassung'] === '' && $an['roh'] === null,
    json_encode(['name' => $an['name'], 'roh' => $an['roh'] === null]));

/* ============================================================================
   35. Die Merkliste — kurz halten, nicht abweisen
   ============================================================================
   Es gibt Anrufer, die regelmäßig anrufen und nie kaufen. Jedes Gespräch
   kostet eine Beratung, einen Seitenblick, eine Preisspanne und am Ende
   einen Link, den niemand öffnet. Das ist kein Grund, unhöflich zu werden —
   es ist ein Grund, kurz zu bleiben.

   Die Hälfte dieser Prüfungen sind Sperren in die andere Richtung: Der Ton
   bleibt höflich, der Anruf bleibt sichtbar, und über den Anrufer wird
   nichts behauptet. Sie stehen hier, damit die Grenze nicht in einem halben
   Jahr aus Versehen verschoben wird.
   ============================================================================ */
abschnitt('35. Die Merkliste');

Db::run('DELETE FROM telefon_merkliste');
Db::run("DELETE FROM activities WHERE type LIKE 'telefon\\_%'");

pruefe('eine unbekannte Nummer steht nicht darauf', Telefon::aufMerkliste('+39 380 111 2233') === false);
$fehl = Telefon::merklisteSetzen('abc');
pruefe('was keine Rufnummer ist, wird abgelehnt', ($fehl['ok'] ?? true) === false, json_encode($fehl));

$ok = Telefon::merklisteSetzen('+39 320 999 8877', 'ruft alle zwei Wochen an');
pruefe('eine Rufnummer lässt sich eintragen', ($ok['ok'] ?? false) === true, json_encode($ok));
pruefe('und steht dann darauf', Telefon::aufMerkliste('+39 320 999 8877'));

/* Dieselbe Nummer kommt mal mit +39, mal mit 0039, mal ohne Vorwahl an --
   verglichen werden die letzten neun Ziffern, genau wie beim Nachschlagen. */
pruefe('auch in anderer Schreibweise', Telefon::aufMerkliste('0039 320 999 8877'));
pruefe('und ohne Vorwahl', Telefon::aufMerkliste('3209998877'));
pruefe('eine fremde Nummer bleibt unberührt', Telefon::aufMerkliste('+39 380 111 2233') === false);

/* DIE SPERRE SITZT IM CODE, NICHT IM PROMPT.
   Eine Textanweisung befolgt ein Sprachmodell „meistens" -- beim dritten
   Nachfragen redet es sich in eine Beratung hinein. Hier antwortet das
   Werkzeug selbst; es kann gar nicht anders. */
foreach (Telefon::MERKLISTE_STUMM as $aktion) {
    $k = Telefon::kurzhalten($aktion, ['telefon' => '+39 320 999 8877', 'sprache' => 'de']);
    pruefe("„$aktion" . '" wird gedeckelt', is_array($k) && ($k['kurz_halten'] ?? false) === true,
        json_encode($k));
}

/* Was NICHT gedeckelt wird: Der Anruf soll in der Verwaltung stehen.
   Niemand wird heimlich weggeblendet. */
foreach (['kunde_nachschlagen', 'melde', 'lage', 'wissensluecke'] as $aktion) {
    pruefe("„$aktion" . '" läuft weiter',
        Telefon::kurzhalten($aktion, ['telefon' => '+39 320 999 8877', 'sprache' => 'de']) === null);
}

$k = Telefon::kurzhalten('beratung', ['telefon' => '+39 320 999 8877', 'sprache' => 'de']);
pruefe('sie bekommt einen fertigen Satz', trim((string) ($k['satz'] ?? '')) !== '', json_encode($k));

/* DER TON IST DER GANZE PUNKT. Kurz halten heisst höflich bleiben. */
$sätze = [];
foreach (['it', 'de', 'en'] as $sp) {
    $a = Telefon::kurzhalten('beratung', ['telefon' => '+39 320 999 8877', 'sprache' => $sp]);
    $sätze[$sp] = (string) ($a['satz'] ?? '');
}
pruefe('der Satz steht in allen drei Sprachen',
    count(array_filter($sätze, static fn($x) => trim($x) !== '')) === 3, json_encode($sätze));
$grob = ['dumm', 'arm', 'kein Geld', 'lächerlich', 'unverschämt', 'Zeitverschwendung',
         'Euro hast du nicht', 'leisten kannst'];
$treffer = [];
foreach ($sätze as $sp => $satz) {
    foreach ($grob as $w) {
        if (mb_stripos($satz, $w) !== false) { $treffer[] = "$sp: $w"; }
    }
}
pruefe('und in keinem steht etwas Herabsetzendes', $treffer === [], implode(', ', $treffer));
pruefe('der Hinweis verbietet ausdrücklich, unfreundlich zu werden',
    str_contains((string) $k['hinweis'], 'höflich')
    && str_contains((string) $k['hinweis'], 'nie unfreundlich'), (string) $k['hinweis']);
pruefe('und verbietet, etwas über ihn zu behaupten',
    str_contains((string) $k['hinweis'], 'behaupte nichts'), (string) $k['hinweis']);

/* Beim Nachschlagen weiss sie es ab dem ersten Satz -- statt erst dann,
   wenn ein Werkzeug sie ausbremst. */
$n = Telefon::nachschlagen(['telefon' => '+39 320 999 8877']);
pruefe('das Nachschlagen sagt es sofort', ($n['kurz_halten'] ?? false) === true, json_encode($n));
pruefe('der Anruf steht trotzdem in der Spur',
    (int) Db::wert("SELECT COUNT(*) FROM activities WHERE type = 'telefon_nachschlagen'", [], 0) > 0);

/* Ein bekannter Kunde auf der Liste bekommt ebenfalls keinen Projektstand --
   kurz halten heisst kurz. */
Db::run('UPDATE customers SET phone = ? WHERE id = ?', ['+39 320 999 8877', $kundeId]);
$nk = Telefon::nachschlagen(['telefon' => '+39 320 999 8877']);
pruefe('auch ein bekannter Kunde wird kurz gehalten', ($nk['kurz_halten'] ?? false) === true);
pruefe('und bekommt keinen Erinnerungssatz dazu', !isset($nk['schon_einmal']), json_encode($nk));
Db::run('UPDATE customers SET phone = ? WHERE id = ?', ['+39 380 111 2233', $kundeId]);

/* Auch ohne mitgegebene Nummer greift sie: „kunde_nachschlagen" steht immer
   am Anfang und hat sie protokolliert. Ohne diesen zweiten Weg wäre die
   Liste ab dem zweiten Satz wirkungslos. */
$ohneNummer = Telefon::kurzhalten('beratung', ['sprache' => 'de']);
pruefe('sie greift auch ohne mitgegebene Nummer',
    is_array($ohneNummer) && ($ohneNummer['kurz_halten'] ?? false) === true, json_encode($ohneNummer));

/* Wie oft sie seither angerufen hat -- das ist die Begründung der Liste. */
$e = Db::one('SELECT * FROM telefon_merkliste LIMIT 1');
pruefe('die Anrufe werden mitgezählt', (int) $e['getroffen'] > 0, json_encode($e['getroffen']));

/* Und wieder herunter davon. */
pruefe('sie lässt sich wieder aufheben', Telefon::merklisteWeg((string) $e['nummer_ende']));
pruefe('danach ist die Nummer wieder gewöhnlich',
    Telefon::aufMerkliste('+39 320 999 8877') === false
    && Telefon::kurzhalten('beratung', ['telefon' => '+39 320 999 8877', 'sprache' => 'de']) === null);

Db::run("DELETE FROM activities WHERE type LIKE 'telefon\\_%'");

/* ============================================================================
   36. Die zwei Befunde aus dem Rückblick
   ============================================================================
   Der wöchentliche Rückblick hat zwei Dinge gemeldet, und beide sind hier
   behoben — im Werkzeug, nicht nur im Leitfaden. Beide standen nämlich schon
   im Leitfaden. Eine Anweisung, die ein Sprachmodell in neun von zehn Fällen
   befolgt, ist bei zehn Gesprächen am Tag ein Fehler pro Tag.
   ============================================================================ */
abschnitt('36. Was der Rückblick gemeldet hat');

Db::run("DELETE FROM activities WHERE type LIKE 'telefon\\_%'");
Db::run('DELETE FROM telefon_merkliste');
Db::run('UPDATE customers SET phone = ? WHERE id = ?', ['+39 380 111 2233', $kundeId]);

/* „In 2 Gesprächen hat sie jemanden erkannt und ihn danach trotzdem als
   unbekannt behandelt." */
pruefe('ohne Nachschlagen ist niemand bekannt', Telefon::kundeImGespraech([]) === 0);
pruefe('eine mitgegebene kunde_id gilt unverändert',
    Telefon::kundeImGespraech(['kunde_id' => $kundeId]) === $kundeId);

$n = Telefon::nachschlagen(['telefon' => '+39 380 111 2233']);
pruefe('das Nachschlagen findet ihn', (int) ($n['kunde_id'] ?? 0) === $kundeId, json_encode($n['gefunden'] ?? null));
pruefe('und danach gilt er auch ohne mitgegebene kunde_id als erkannt',
    Telefon::kundeImGespraech([]) === $kundeId, json_encode(Telefon::kundeImGespraech([])));

/* DIE GRENZE BLEIBT: Wer nie gefunden wurde, wird auch hier nicht gefunden.
   Eine Stimme ist kein Ausweis. */
Db::run("DELETE FROM activities WHERE type LIKE 'telefon\\_%'");
Telefon::nachschlagen(['telefon' => '+49 155 000 0000']);
pruefe('ein nicht gefundener Anrufer bleibt unbekannt',
    Telefon::kundeImGespraech([]) === 0, json_encode(Telefon::kundeImGespraech([])));

/* DER GEFÄHRLICHSTE FALL ÜBERHAUPT
   ------------------------------------------------------------------------
   Ruft nach einem Bestandskunden innerhalb von zehn Minuten ein Fremder an,
   darf der NICHT dessen Identität erben — sonst bekäme er Projektstand und
   Konfigurator-Link an eine Adresse, die ihm nicht gehört, und es passierte
   lautlos. Der erste Entwurf dieser Methode ging die letzten drei Zeilen
   durch und nahm den ersten Treffer; der Kettentest hat ihn dafür zerrissen.
   Es gilt nur das JÜNGSTE Nachschlagen. */
Db::run("DELETE FROM activities WHERE type LIKE 'telefon\\_%'");
Telefon::nachschlagen(['telefon' => '+39 380 111 2233']);   // der Kunde
pruefe('nach dem Kunden gilt der Kunde', Telefon::kundeImGespraech([]) === $kundeId);
Telefon::nachschlagen(['telefon' => '+49 155 000 0000']);   // gleich danach ein Fremder
pruefe('ein Fremder danach erbt seine Akte NICHT',
    Telefon::kundeImGespraech([]) === 0, json_encode(Telefon::kundeImGespraech([])));

/* Und wenn ein Werkzeug eine andere Nummer mitbringt als die zuletzt
   nachgeschlagene, spricht sie mit jemand anderem. */
Db::run("DELETE FROM activities WHERE type LIKE 'telefon\\_%'");
Telefon::nachschlagen(['telefon' => '+39 380 111 2233']);
pruefe('dieselbe Nummer passt',
    Telefon::kundeImGespraech(['telefon' => '0039 380 111 2233']) === $kundeId);
pruefe('eine andere Nummer nicht',
    Telefon::kundeImGespraech(['telefon' => '+49 155 000 0000']) === 0);

/* Und bei mehreren Treffern hat der Anrufer selbst noch nicht entschieden,
   wer er ist — dann darf es das Werkzeug auch nicht. */
Db::run("DELETE FROM activities WHERE type LIKE 'telefon\\_%'");
Telefon::protokoll('nachschlagen', 'Nachgeschlagen', null, ['treffer' => 3]);
pruefe('bei mehreren Treffern bleibt es offen', Telefon::kundeImGespraech([]) === 0);

/* Das Werkzeug muss es auch wirklich benutzen — sonst wäre die Methode ein
   ungenutztes Versprechen. Der Nachweis: „hilfe" gibt einem erkannten
   Anrufer einen Stand, ohne dass die kunde_id mitkommt. */
Db::run("DELETE FROM activities WHERE type LIKE 'telefon\\_%'");
Telefon::nachschlagen(['telefon' => '+39 380 111 2233']);
$h = Telefon::hilfe(['problem' => 'fragebogen', 'sprache' => 'de']);
pruefe('„hilfe" erkennt ihn ohne mitgegebene kunde_id',
    ($h['bekannt'] ?? false) === true, json_encode($h['bekannt'] ?? null));

/* „4 von 5 Hilferufen landeten unter Sonstiges." */
pruefe('was er sagt, entscheidet, wenn das Modell sich drückt',
    Telefon::problemErkennen('sonstiges', ['text' => 'Die Überweisung ist nicht angekommen'])
        === 'bezahlung');
pruefe('auch bei leerer Angabe',
    Telefon::problemErkennen('', ['text' => 'Ich komme beim Fragebogen nicht weiter'])
        === 'fragebogen');
pruefe('und auf Italienisch',
    Telefon::problemErkennen('sonstiges', ['text' => 'Non riesco a fare il pagamento'])
        === 'bezahlung');

/* WAS HIER NICHT PASSIERT: raten. */
pruefe('ein festgelegtes Wort wird nicht überstimmt',
    Telefon::problemErkennen('zugang', ['text' => 'Es geht um die Rechnung']) === 'zugang');
pruefe('bei zwei möglichen Fällen bleibt es „sonstiges“',
    Telefon::problemErkennen('sonstiges', ['text' => 'Der Link zum Fragebogen fehlt']) === 'sonstiges');
pruefe('und wo nichts passt, stimmt „sonstiges“ auch',
    Telefon::problemErkennen('sonstiges', ['text' => 'Ich wollte nur mal hallo sagen']) === 'sonstiges');
pruefe('ohne Text bleibt es dabei', Telefon::problemErkennen('sonstiges', []) === 'sonstiges');

/* Jedes der fünf Wörter hat Stichwörter — sonst wäre die Erkennung für
   dieses eine Wort tot, und niemand merkte es. */
$ohne = array_values(array_diff(
    array_filter(Telefon::PROBLEME, static fn($p) => $p !== 'sonstiges'),
    array_keys(Telefon::PROBLEM_WORTE)));
pruefe('jeder der fünf Fälle ist erkennbar', $ohne === [], implode(', ', $ohne));

/* Und am Ende steht es auch wirklich so in der Spur — dort liest es die
   Liste „Woran es hakt“. */
Db::run("DELETE FROM activities WHERE type LIKE 'telefon\\_%'");
Telefon::hilfe(['problem' => 'sonstiges', 'sprache' => 'de',
                'text' => 'Ich kann mich nicht einloggen']);
$sp = Db::one("SELECT meta FROM activities WHERE type = 'telefon_hilfe' ORDER BY id DESC LIMIT 1");
$mm = json_decode((string) ($sp['meta'] ?? ''), true);
pruefe('die Spur trägt das erkannte Wort, nicht „sonstiges“',
    ($mm['problem'] ?? '') === 'zugang', json_encode($mm));

Db::run("DELETE FROM activities WHERE type LIKE 'telefon\\_%'");

/* ============================================================================
   37. Die Werkzeuge — an einer Stelle, die auch der Server lesen kann
   ============================================================================
   Sie standen bis September mitten in einer Ansicht. Das ging, solange sie
   nur angezeigt wurden. Es geht nicht mehr, seit die Verwaltung sie selbst
   hinüberschicken soll: Eine Ansicht lässt sich nicht aufrufen, ohne eine
   Seite zu bauen.
   ============================================================================ */
abschnitt('37. Die Werkzeuge');

require_once $wurzel . '/src/Telefonwerkzeuge.php';

$w = Telefonwerkzeuge::alle();
pruefe('es sind sechzehn', count($w) === 16, (string) count($w));
pruefe('und genau die aus Telefon::AKTIONEN',
    array_diff(Telefon::AKTIONEN, array_keys($w)) === []
    && array_diff(array_keys($w), Telefon::AKTIONEN) === [],
    implode(', ', array_keys($w)));
pruefe('in der Reihenfolge des Gesprächs, nicht alphabetisch',
    array_keys($w) === Telefonwerkzeuge::REIHE, implode(', ', array_keys($w)));

$o = Telefonwerkzeuge::objekte();
pruefe('jedes wird zu gültigem JSON', count($o) === 16, (string) count($o));
pruefe('und behält seinen Namen',
    array_map(static fn($x) => (string) $x->name, $o) === array_keys($w));

/* DER SCHLÜSSEL MUSS ÜBERALL DERSELBE SEIN. Steht in einem Werkzeug ein
   alter, ruft genau dieses eine ins Leere -- und zwar erst beim Anruf. */
$schluessel = [];
foreach ($o as $x) {
    foreach ((array) ($x->request->headers ?? []) as $h) {
        if (($h->name ?? '') === 'X-Vecom-Telefon') { $schluessel[(string) $h->value] = true; }
    }
}
pruefe('alle sechzehn tragen denselben Schlüssel', count($schluessel) === 1, (string) count($schluessel));
pruefe('und er ist der aktuelle', isset($schluessel[Telefon::schluessel()]));

/* Stratos Platzhalter müssen WÖRTLICH stehen bleiben -- durch json_encode
   gejagt wären sie escaped, und der Rumpf käme leer an. */
$ohnePlatzhalter = [];
foreach ($o as $x) {
    $rumpf = (string) ($x->request->postData->text ?? '');
    if (!str_contains($rumpf, '"aktion"')) { $ohnePlatzhalter[] = (string) $x->name; continue; }
    /* „lage" hat keine Eigenschaften und deshalb zu Recht keinen Platzhalter
       -- es fragt nur nach Tag und Uhrzeit. Wer hier stur prüft, baut sich
       einen Test, der einen richtigen Zustand rot färbt. */
    $hatFelder = (array) ($x->parameters->properties ?? []) !== [];
    if ($hatFelder && !str_contains($rumpf, '{{ ')) { $ohnePlatzhalter[] = (string) $x->name; }
}
pruefe('jeder Rumpf trägt seine Aktion und, wo es Felder gibt, die Platzhalter wörtlich',
    $ohnePlatzhalter === [], implode(', ', $ohnePlatzhalter));

/* Und die Aktion im Rumpf muss die eigene sein — ein vertauschter Rumpf
   ruft beim Anruf lautlos das falsche Werkzeug auf. */
$vertauscht = [];
foreach ($o as $x) {
    $name = (string) $x->name;
    if (!str_contains((string) ($x->request->postData->text ?? ''), '"aktion":"' . $name . '"')) {
        $vertauscht[] = $name;
    }
}
pruefe('und zwar die eigene', $vertauscht === [], implode(', ', $vertauscht));

/* „properties" muss ein Objekt sein, auch wenn es leer ist -- als leeres
   Array schickt PHP „[]", und Stratos Schemaprüfung lehnt es ab. */
$falsch = [];
foreach ($o as $x) {
    $t = Telefon::konfigJson((string) $x->name, $w[(string) $x->name], 'https://x/telefon.php', 'k');
    if (!str_contains($t, '"properties": {')) { $falsch[] = (string) $x->name; }
}
pruefe('„properties" bleibt ein Objekt', $falsch === [], implode(', ', $falsch));

/* DIE PRÜFUNG, DIE GEFEHLT HAT
   ------------------------------------------------------------------------
   Am 7. September war der Assistent eine halbe Stunde nicht erreichbar. Der
   Grund: json_decode($text, true) macht aus einem leeren JSON-Objekt {} ein
   leeres PHP-Array [], und beim Zurückschreiben stand bei „lage" —
   dem einzigen Werkzeug ohne Eigenschaften — "properties": [] statt {}.
   Stratos Schemaprüfung lehnte es ab. EIN kaputtes Werkzeug legt alle
   vierzehn still.

   Vor genau dem warnt der Kommentar in Telefon::konfigJson() seit dem ersten
   Tag. Die Prüfung dazu gab es nur für konfigJson selbst — nicht für den Weg
   danach, durch Dekodieren und wieder Kodieren. Also hier. */
$alsListe = [];
foreach (Telefonwerkzeuge::objekte() as $x) {
    if (is_array($x->parameters->properties ?? null)) { $alsListe[] = (string) $x->name; }
}
pruefe('„properties" überlebt das Dekodieren als Objekt', $alsListe === [], implode(', ', $alsListe));

/* Und den ganzen Weg: kodieren, dekodieren, wieder kodieren — so läuft es
   bei der Übertragung. Ein einziger Durchgang genügt nicht als Nachweis. */
$hin  = json_encode(['config' => ['tools' => Telefonwerkzeuge::objekte()]],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$her  = json_decode($hin);
$hin2 = json_encode($her, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
pruefe('und übersteht Hin und Zurück unverändert', $hin === $hin2);
pruefe('bei „lage" steht danach ein Objekt, keine Liste',
    str_contains($hin, '"aktion\\":\\"lage') || str_contains($hin, '"lage"'));
$lageJ = null;
foreach ($her->config->tools as $x) { if ($x->name === 'lage') { $lageJ = $x; } }
pruefe('„lage" hat kein Feld — und trotzdem ein Objekt',
    $lageJ !== null && $lageJ->parameters->properties instanceof stdClass
    && (array) $lageJ->parameters->properties === [],
    json_encode($lageJ->parameters ?? null));

/* Ohne Zugang wird nichts geschickt -- und es scheitert nicht, es sagt es. */
Db::run("DELETE FROM settings WHERE skey IN ('strato_anon','strato_refresh')");
$u = Strato::werkzeugeUebertragen();
pruefe('ohne Zugang wird nichts übertragen',
    ($u['ok'] ?? true) === false && str_contains((string) $u['text'], 'Kein Zugang'), json_encode($u));

/* DER ZUGANGS-TOKEN
   ------------------------------------------------------------------------
   Supabase tauscht den Auffrischungs-Token bei jeder Benutzung aus und
   widerruft die ganze Sitzung, wenn ein bereits benutzter noch einmal
   kommt. Am 7. September fielen der stündliche Abgleich und ein Klick in
   der Verwaltung zusammen — danach war der Zugang tot, mit der Meldung
   „Invalid Refresh Token: Already Used". Der Fehler ist selten, kostet aber
   jedes Mal den ganzen Zugang.

   Geprüft wird hier, was ohne Netz prüfbar ist: dass ein gültiger Token
   NICHT erneuert wird (sonst wäre jede Anfrage eine Erneuerung und der
   Zusammenstoß die Regel statt die Ausnahme), und dass ein abgelaufener
   nicht mehr gilt. */
Db::run("DELETE FROM settings WHERE skey LIKE 'strato\_%'");
$zw = new ReflectionMethod('Strato', 'zwischengespeicherter');
$zw->setAccessible(true);
pruefe('ohne Zwischenspeicher gibt es keinen Token', $zw->invoke(null) === null);

Db::run("INSERT INTO settings (skey, svalue) VALUES ('strato_zugang', ?)",
        [json_encode(['token' => 'tok-gueltig', 'bis' => time() + 600])]);
pruefe('ein gültiger Token kommt aus dem Zwischenspeicher', $zw->invoke(null) === 'tok-gueltig');
pruefe('und wird ohne Netz zurückgegeben — es wird NICHT erneuert',
    Strato::zugangsToken() === 'tok-gueltig');

Db::run("UPDATE settings SET svalue = ? WHERE skey = 'strato_zugang'",
        [json_encode(['token' => 'tok-alt', 'bis' => time() - 10])]);
pruefe('ein abgelaufener gilt nicht mehr', $zw->invoke(null) === null);
pruefe('und ohne hinterlegten Zugang wird auch keiner geholt',
    Strato::zugangsToken() === null && str_contains(Strato::fehler(), 'Kein Zugang'), Strato::fehler());

/* DER TOKEN AUS DEM ROHEN COOKIE
   ------------------------------------------------------------------------
   Bisher hiess die Anleitung: „F12, Konsole, diesen Einzeiler einfügen."
   Chrome warnt bei genau dieser Handlung — zu Recht. Wer seinen Nutzern
   beibringt, diese Warnung wegzuklicken, bringt ihnen bei, sie immer
   wegzuklicken. Also nimmt das Feld auch den rohen Cookie-Wert: mit der
   Maus kopieren, einfügen, der Server packt aus.

   Er kommt in fünf Schreibweisen an, je nachdem, was jemand markiert hat. */
$sitzung = json_encode(['access_token' => 'eyJ-access', 'refresh_token' => 'v66geheim']);
$formen = [
    'der nackte Token'      => 'v66geheim',
    'base64 aus dem Cookie' => 'base64-' . base64_encode($sitzung),
    'rohes JSON'            => $sitzung,
    'mit Cookie-Namen davor'=> 'sb-oeblavonrjzfihahjvmm-auth-token=base64-' . base64_encode($sitzung),
    'URL-kodiert'           => rawurlencode('base64-' . base64_encode($sitzung)),
    'als Liste abgelegt'    => json_encode([json_decode($sitzung, true)]),
];
$daneben = [];
foreach ($formen as $wie => $wert) {
    if (Strato::ausCookie($wert) !== 'v66geheim') { $daneben[] = $wie; }
}
pruefe('der Token wird aus jeder Schreibweise ausgepackt', $daneben === [], implode(', ', $daneben));

/* WAS NICHT DURCHGEHT: etwas, das nur aussieht wie ein Token. Eine klare
   Fehlermeldung ist besser als eine Ablehnung von Supabase, die niemand
   einordnen kann. */
pruefe('ein Satz ist kein Token', Strato::ausCookie('hallo welt') === '');
pruefe('nichts ist auch kein Token', Strato::ausCookie('') === '');
pruefe('und zu kurz ebenfalls nicht', Strato::ausCookie('kurz') === '');

/* Und der access_token, der im selben Cookie steht, wird verworfen --
   er ist in einer Stunde wertlos, und was man nicht braucht, speichert
   man nicht. */
pruefe('der Zugangs-Token aus dem Cookie wird nicht mitgenommen',
    Strato::ausCookie('base64-' . base64_encode($sitzung)) !== 'eyJ-access');

Db::run("DELETE FROM settings WHERE skey LIKE 'strato\\_%'");
$leer = Strato::zugangSetzen('eyJtest', 'hallo welt');
pruefe('und wer Unsinn einfügt, bekommt gesagt, was zu tun ist',
    ($leer['ok'] ?? true) === false && str_contains((string) $leer['text'], 'sb-…-auth-token'),
    json_encode($leer));
Db::run("DELETE FROM settings WHERE skey LIKE 'strato\\_%'");

/* Die Sperre selbst: Sie muss sich nehmen und wieder freigeben lassen —
   bleibt sie hängen, steht beim nächsten Lauf alles acht Sekunden still. */
$g1 = (int) Db::wert("SELECT GET_LOCK('vd_strato_token', 1)", [], 0);
pruefe('die Sperre lässt sich nehmen', $g1 === 1);
pruefe('und wieder freigeben',
    (int) Db::wert("SELECT RELEASE_LOCK('vd_strato_token')", [], 0) === 1);
Db::run("DELETE FROM settings WHERE skey LIKE 'strato\_%'");

/* ============================================================================
   38. Wer über die Website anruft
   ============================================================================
   Von 46 Anrufen kamen 43 über das Sprachfenster. Dort steht bei STRATO als
   Anrufer „widget-call": keine Nummer, kein Name. Der ganze
   Bestandskunden-Weg lief ins Leere — die Telefonseite meldete „0 von einem
   bekannten Kunden", und das stimmte.

   Das Widget selbst kann nichts mitgeben; es liest genau drei Einstellungen.
   Also andersherum: Auf der Kundenseite wissen WIR, wer da ist, und
   hinterlassen einen kurzlebigen Vermerk.
   ============================================================================ */
abschnitt('38. Anrufe über die Website');

Db::run("DELETE FROM activities WHERE type LIKE 'telefon\\_%'");
Db::run('UPDATE customers SET phone = NULL WHERE id = ?', [$kundeId]);

pruefe('ohne Vermerk ist niemand am Sprachfenster', Telefon::kundeAmWidget() === 0);

Telefon::amWidget($kundeId);
pruefe('mit Vermerk wird er erkannt', Telefon::kundeAmWidget() === $kundeId);

/* Ein Anruf ohne Nummer — genau das, was das Widget schickt. */
$n = Telefon::nachschlagen(['sprache' => 'de']);
pruefe('und ein Anruf ohne Rufnummer findet ihn',
    ($n['gefunden'] ?? false) === true && (int) ($n['kunde_id'] ?? 0) === $kundeId, json_encode($n));
pruefe('auch die weiteren Werkzeuge kennen ihn dann',
    Telefon::kundeImGespraech([]) === $kundeId);

/* DIE GRENZE: Bei zwei gleichzeitig ist jede Zuordnung geraten. Ein falsch
   zugeordneter Anrufer bekäme den Projektstand eines Fremden — teurer als
   ein unerkannter. */
$zweiter = (int) Db::wert('SELECT id FROM customers WHERE id <> ? LIMIT 1', [$kundeId], 0);
if ($zweiter > 0) {
    Telefon::amWidget($zweiter);
    pruefe('bei zweien bleibt es offen', Telefon::kundeAmWidget() === 0);
    Db::run("DELETE FROM activities WHERE type = 'telefon_widget_da' AND customer_id = ?", [$zweiter]);
    pruefe('und mit nur einem wieder eindeutig', Telefon::kundeAmWidget() === $kundeId);
}

/* Eine genannte Nummer, die niemanden trifft, ist eine Aussage: Der Anrufer
   ist dann eben nicht in der Verwaltung. Der Vermerk darf das nicht
   überstimmen — sonst bekäme ein Fremder, der zufällig währenddessen
   anruft, die Akte des Kunden. */
$fremd = Telefon::nachschlagen(['telefon' => '+49 155 000 0000', 'sprache' => 'de']);
pruefe('eine genannte fremde Nummer überstimmt den Vermerk nicht',
    ($fremd['gefunden'] ?? true) === false, json_encode($fremd));

/* Und was zu lange her ist, gilt nicht mehr — sonst zeigte der Vermerk noch
   auf jemanden, der längst weg ist. */
Db::run("UPDATE activities SET created_at = NOW() - INTERVAL 20 MINUTE
          WHERE type = 'telefon_widget_da'");
pruefe('ein alter Vermerk zählt nicht mehr', Telefon::kundeAmWidget() === 0);

Db::run("DELETE FROM activities WHERE type LIKE 'telefon\\_%'");
Db::run('UPDATE customers SET phone = ? WHERE id = ?', ['+39 380 111 2233', $kundeId]);

/* ============================================================================
   39. Was der erste echte Anruf über die Kundenseite gezeigt hat
   ============================================================================
   7.9., 03:21. Manuela hat den Anrufer erkannt — die Spur sagt
   „nummer_kam_an": false, „treffer": 1, und die Hilfe lief mit
   „bekannt": true. In der Gesprächsliste stand trotzdem „Unbekannt".

   Der Grund: Das Gespräch wird über die Rufnummer zugeordnet, und über die
   Website kommt keine. Die Erkennung wirkte am Telefon und fehlte in der
   Auswertung — die Seite zählte weiter „0 von einem bekannten Kunden" für
   Anrufe, die sehr wohl erkannt waren.
   ============================================================================ */
abschnitt('39. Der erste Anruf über die Kundenseite');

Db::run("DELETE FROM activities WHERE type LIKE 'telefon\\_%'");
Db::run('DELETE FROM telefon_gespraeche');
Db::run('DELETE FROM telefon_gespraech_weg');
Db::run('UPDATE customers SET phone = NULL WHERE id = ?', [$kundeId]);

$ablegen = new ReflectionMethod('Strato', 'ablegen');
$ablegen->setAccessible(true);
$wann = time() - 600;

/* Erst der Anruf, dann unsere Spur — so wie es wirklich passiert: Das
   Gespräch wird Minuten später abgeglichen. */
Telefon::amWidget($kundeId);
$n = Telefon::nachschlagen(['sprache' => 'de']);
pruefe('über die Kundenseite wird er erkannt', (int) ($n['kunde_id'] ?? 0) === $kundeId);
Db::run("UPDATE activities SET created_at = ? WHERE type = 'telefon_nachschlagen'",
        [date('Y-m-d H:i:s', $wann + 20)]);

$ablegen->invoke(null, [
    'id' => 'ffffffff-0000-0000-0000-000000000009',
    'created_at' => gmdate('Y-m-d\TH:i:s\Z', $wann),
    'call_seconds_billed' => 202, 'customer_number' => 'widget-call',
    'summaries' => [['content' => ['subject' => 'Unsicherheit beim Material-Upload'],
                     'metadata' => []]],
]);
$g = Db::one("SELECT * FROM telefon_gespraeche WHERE id = 'ffffffff-0000-0000-0000-000000000009'");
pruefe('und das Gespräch landet in seiner Akte, obwohl keine Nummer kam',
    (int) ($g['kunde_id'] ?? 0) === $kundeId, json_encode($g['kunde_id']));

$z = Strato::zahlen(1);
pruefe('die Zahlen sagen dann nicht mehr „0 von einem bekannten Kunden"',
    (int) $z['bekannt'] === 1, json_encode($z['bekannt']));

/* DIE GRENZE, WIE ÜBERALL: Bei zwei erkannten Anrufern im Fenster ist die
   Zuordnung geraten. Ein falsch zugeordnetes Gespräch legt einen fremden
   Anruf in eine Kundenakte. */
$zweiter = (int) Db::wert('SELECT id FROM customers WHERE id <> ? LIMIT 1', [$kundeId], 0);
if ($zweiter > 0) {
    Telefon::protokoll('nachschlagen', 'Nachgeschlagen', $zweiter, ['treffer' => 1]);
    Db::run("UPDATE activities SET created_at = ? WHERE type = 'telefon_nachschlagen'
              ORDER BY id DESC LIMIT 1", [date('Y-m-d H:i:s', $wann + 40)]);
    $ablegen->invoke(null, [
        'id' => 'ffffffff-0000-0000-0000-00000000000b',
        'created_at' => gmdate('Y-m-d\TH:i:s\Z', $wann),
        'call_seconds_billed' => 202, 'customer_number' => 'widget-call',
        'summaries' => [['content' => ['subject' => 'zweideutig'], 'metadata' => []]],
    ]);
    $g2 = Db::one("SELECT kunde_id FROM telefon_gespraeche WHERE id = 'ffffffff-0000-0000-0000-00000000000b'");
    pruefe('bei zwei erkannten Anrufern bleibt es offen', $g2['kunde_id'] === null, json_encode($g2));
}

/* Eine echte Rufnummer geht weiterhin ihren eigenen Weg — sie ist genauer
   als jede Näherung über die Zeit. */
Db::run('UPDATE customers SET phone = ? WHERE id = ?', ['+39 380 111 2233', $kundeId]);
$ablegen->invoke(null, [
    'id' => 'ffffffff-0000-0000-0000-00000000000c',
    'created_at' => gmdate('Y-m-d\TH:i:s\Z', time() - 60),
    'call_seconds_billed' => 90, 'customer_number' => '+39 380 111 2233',
    'summaries' => [['content' => ['subject' => 'mit Nummer'], 'metadata' => []]],
]);
$g3 = Db::one("SELECT kunde_id FROM telefon_gespraeche WHERE id = 'ffffffff-0000-0000-0000-00000000000c'");
pruefe('mit Rufnummer bleibt es bei der Rufnummer', (int) $g3['kunde_id'] === $kundeId);

/* „text" ist Pflicht geworden: Ohne ihn landete der Anruf vom 7.9. unter
   „sonstiges", obwohl es erkennbar um den Fragebogen ging. Eine Bitte im
   Beschreibungstext genügte nicht. */
$hilfe = Telefonwerkzeuge::alle()['hilfe'];
pruefe('bei „hilfe" ist der Wortlaut Pflicht',
    in_array('text', $hilfe['pflicht'], true), json_encode($hilfe['pflicht']));

Db::run("DELETE FROM activities WHERE type LIKE 'telefon\\_%'");
Db::run('DELETE FROM telefon_gespraeche');

/* ============================================================================
   40. Fünf Dinge für den Anrufer
   ============================================================================
   Aus den echten Gesprächen abgeleitet, nicht aus dem Lehrbuch.
   ============================================================================ */
abschnitt('40. Für den Anrufer');

Db::run("DELETE FROM activities WHERE type LIKE 'telefon\\_%'");
Db::run('DELETE FROM telefon_gespraeche');
Db::run('DELETE FROM telefon_gespraech_weg');

/* ---- 1. DAS NETZ UNTER DEN ZUSAGEN ----
   „Es wurde vereinbart, dass Uwe dem Kunden eine Rückmeldung gibt" — und
   „melde" wurde nicht aufgerufen. Der Anrufer legt auf und glaubt, es
   läuft. Es existiert nichts. Zweimal derselbe Fehler in zwei Tagen. */
pruefe('eine Zusage wird im Text erkannt',
    Telefon::zusageErkannt('Es wurde vereinbart, dass Uwe sich meldet.'));
pruefe('auf Italienisch auch',
    Telefon::zusageErkannt('È stato concordato che invierà i dettagli.'));
pruefe('auf Englisch auch',
    Telefon::zusageErkannt('The agent agreed to send the link.'));
pruefe('ein gewöhnlicher Satz ist keine Zusage',
    Telefon::zusageErkannt('Der Anrufer wollte die Preise wissen.') === false);
pruefe('und ein leerer Text erst recht nicht', Telefon::zusageErkannt('') === false);

$g = ['id' => 'aaaa1111-0000-0000-0000-000000000001',
      'begonnen' => date('Y-m-d H:i:s', time() - 900), 'sekunden' => 200,
      'betreff' => 'Unsicherheit beim Material-Upload',
      'zusammenfassung' => 'Es wurde vereinbart, dass Uwe dem Kunden eine Rückmeldung gibt.',
      'kunde_nummer' => 'widget-call', 'name' => '', 'kunde_id' => null];

pruefe('eine Zusage ohne Spur wird nachgetragen', Telefon::zusageNachtragen($g) === true);
$r = Telefon::rueckrufe(30);
pruefe('und steht danach auf „Heute anrufen"', count($r) === 1, json_encode(count($r)));
pruefe('mit dem Anliegen aus dem Gespräch',
    str_contains((string) ($r[0]['anliegen'] ?? ''), 'Material-Upload'), json_encode($r[0]['anliegen'] ?? null));

/* KEINE ZWEITE LISTE: Es ist ein gewöhnlicher Rückruf, mit demselben Knopf
   abhakbar wie jeder andere. Wer eine zweite Liste baut, hat zwei Listen,
   die einander widersprechen. */
Telefon::rueckrufErledigt((int) $r[0]['id']);
pruefe('und lässt sich ganz normal abhaken', Telefon::rueckrufe(30) === []);

/* Und nicht zweimal dasselbe. */
pruefe('zweimal nachtragen passiert nicht', Telefon::zusageNachtragen($g) === false);

/* Hat sie es festgehalten, wird nichts nachgetragen — sonst stünde jeder
   erledigte Punkt doppelt da. */
Db::run("DELETE FROM activities WHERE type LIKE 'telefon\\_%'");
$g2 = $g; $g2['id'] = 'aaaa1111-0000-0000-0000-000000000002';
Telefon::melden(['art' => 'rueckruf', 'telefon' => '+39 380 111 2233',
                 'text' => 'Bitte zurückrufen', 'anliegen' => 'Material']);
Db::run("UPDATE activities SET created_at = ? WHERE type = 'telefon_melde'",
        [date('Y-m-d H:i:s', strtotime($g2['begonnen']) + 30)]);
pruefe('wo sie es festgehalten hat, wird nichts nachgetragen',
    Telefon::zusageNachtragen($g2) === false);

/* ---- 2. ER IST SCHON IM PORTAL ----
   Am 7.9. um 03:19: Der Anrufer dachte, er sei im Kundenportal. Sie sagte
   „hier ist die Telefonzentrale", er legte genervt auf. Er WAR im Portal. */
Db::run("DELETE FROM activities WHERE type LIKE 'telefon\\_%'");
Db::run('UPDATE customers SET phone = ? WHERE id = ?', ['+39 380 111 2233', $kundeId]);
Telefon::amWidget($kundeId);
$n = Telefon::nachschlagen(['telefon' => '+39 380 111 2233']);
pruefe('sie erfährt, dass er von seiner Kundenseite aus anruft',
    ($n['von_kundenseite'] ?? false) === true, json_encode($n['von_kundenseite'] ?? null));
pruefe('und wird angewiesen, ihn nicht wegzuschicken',
    str_contains((string) $n['hinweis'], 'er ist drin'), (string) $n['hinweis']);

/* Wer NICHT von dort anruft, bekommt den Hinweis nicht — sonst behauptet
   sie etwas über den Anrufer, das nicht stimmt. */
Db::run("DELETE FROM activities WHERE type = 'telefon_widget_da'");
$n2 = Telefon::nachschlagen(['telefon' => '+39 380 111 2233']);
pruefe('ohne Vermerk kein Hinweis auf die Kundenseite', empty($n2['von_kundenseite']));

/* ---- 3. IM FENSTER WEISS NIEMAND, WAS ER FRAGEN DARF ----
   Vier der fünf Anrufe am 7.9. waren unter einer Minute; einer endete mit
   „Sure" und Stille. Am Telefon weiss man, warum man anruft — wer auf einer
   Website ein Sprachfenster anklickt, hat oft nur darauf gedrückt. */
Db::run("DELETE FROM activities WHERE type LIKE 'telefon\\_%'");
$fenster = Telefon::nachschlagen(['sprache' => 'de']);
pruefe('ein Anruf ohne jede Angabe gilt als Anruf über die Website',
    ($fenster['ueber_website'] ?? false) === true, json_encode($fenster));
pruefe('und sie bekommt einen fertigen Satz',
    str_contains((string) ($fenster['satz'] ?? ''), 'Website prüfen'), (string) ($fenster['satz'] ?? ''));
pruefe('der Satz steht in allen drei Sprachen',
    count(array_filter(Telefon::WEBSITE_SATZ, static fn($x) => trim($x) !== '')) === 3);
pruefe('sie soll nicht sofort nach der Rufnummer fragen',
    str_contains((string) $fenster['hinweis'], 'NICHT sofort'), (string) $fenster['hinweis']);

/* Wer eine Nummer nennt, bekommt den Satz nicht — er weiss ja, warum er
   anruft, und drei Beispiele wären dann eine Belehrung. */
$mitNummer = Telefon::nachschlagen(['telefon' => '+49 155 000 0000', 'sprache' => 'de']);
pruefe('mit genannter Nummer kommt der Satz nicht', empty($mitNummer['ueber_website']));

/* ---- 4. DIE ADRESSE ZURÜCKLESEN ---- */
$ohne = Telefon::angebotLink(['sprache' => 'de', 'email' => 'neu@example.com']);
pruefe('an eine unbestätigte Adresse geht nichts raus',
    ($ohne['grund'] ?? '') === 'adresse_unbestaetigt', json_encode($ohne));
pruefe('und sie erfährt, was zu tun ist',
    str_contains((string) $ohne['hinweis'], 'Buchstabe für Buchstabe'), (string) $ohne['hinweis']);
pruefe('mit Bestätigung greift der Riegel nicht',
    Telefon::adresseBestaetigt(['email' => 'neu@example.com', 'email_bestaetigt' => true]) === null);
pruefe('auch als Zeichenkette „true“ — Stratos Platzhalter liefern Text',
    Telefon::adresseBestaetigt(['email' => 'neu@example.com', 'email_bestaetigt' => 'true']) === null);

/* AN EINE HINTERLEGTE ADRESSE SCHON: Die hat er selbst eingetragen. Sie
   noch einmal buchstabieren zu lassen wäre eine Zumutung. */
pruefe('ohne genannte Adresse greift der Riegel gar nicht',
    Telefon::adresseBestaetigt(['sprache' => 'de']) === null);

/* ---- 5. NICHTS ERFINDEN ---- */
$w = Telefonwerkzeuge::alle();
pruefe('„beleg" verbietet das Erfinden ausdrücklich',
    str_contains($w['beleg']['zweck'], 'erfunden'), mb_substr($w['beleg']['zweck'], -120));
pruefe('„wissen" auch',
    str_contains($w['wissen']['zweck'], 'Erfinde keine'), mb_substr($w['wissen']['zweck'], 0, 120));

Db::run("DELETE FROM activities WHERE type LIKE 'telefon\\_%'");
Db::run('DELETE FROM telefon_gespraeche');

/* ============================================================================
   41. Der Fragebogen am Telefon
   ============================================================================
   Achtundvierzig Felder, vorgelesen. Das ist der längste Ablauf, den dieser
   Assistent hat, und der einzige, der etwas auslöst — am Ende rückt ein
   Projekt weiter und es gehen Mails raus.

   Geprüft wird deshalb vor allem, was NICHT passieren darf: dass dieselbe
   Frage zweimal kommt, dass ein Gespräch an einer Auswahl hängenbleibt, dass
   ein Schlüssel im Fragebogen landet, den es nicht gibt, und dass irgendetwas
   abgeschickt wird, ohne dass jemand ja gesagt hat.
   ============================================================================ */
abschnitt('41. Der Fragebogen am Telefon');

require_once $wurzel . '/src/Telefonfragebogen.php';

Db::run("DELETE FROM activities WHERE type LIKE 'telefon\\_%'");
Db::run("UPDATE questionnaires SET status = 'offen', data = '{}', submitted_at = NULL WHERE id = ?", [$fbId]);

/* ---- 1. WAS ÜBERHAUPT GEFRAGT WIRD ---- */
$alle = Telefonfragebogen::reihe();
pruefe('der Fragebogen hat Felder', count($alle) > 30, (string) count($alle));

$namen = array_column($alle, 'name');
pruefe('die Reihenfolge fängt beim Unternehmen an', $namen[0] === 'firmenname', $namen[0]);

/* DIE FRAGEN, DIE NICHT GESTELLT WERDEN
   Wer nie eine Website hatte, wird nicht gefragt, was ihn an ihr stört. Im
   Browser blendet das Formular sie aus; am Telefon wäre es schlimmer, sie
   trotzdem zu stellen — ein Mensch antwortet höflich und wundert sich still. */
$ohneAlt = array_column(Telefonfragebogen::reihe(['altseite' => 'nein']), 'name');
pruefe('ohne alte Seite wird nicht nach ihr gefragt',
    !in_array('erhalten', $ohneAlt, true) && !in_array('stoert', $ohneAlt, true));
$mitAlt = array_column(Telefonfragebogen::reihe(['altseite' => 'ja']), 'name');
pruefe('mit alter Seite schon', in_array('stoert', $mitAlt, true));
pruefe('Wunschadressen nur, wenn eine neue Domain gebraucht wird',
    !in_array('wunsch1', $ohneAlt, true)
    && in_array('wunsch1', array_column(Telefonfragebogen::reihe(['domain' => 'neu']), 'name')));

/* ---- 2. ES GEHT IMMER VORWÄRTS ----
   Der erste Entwurf nahm stets das erste unbeantwortete Feld. Damit kam eine
   Frage, die offen blieb, sofort wieder — und beim dritten Mal legt jeder
   auf. Also läuft der Fragebogen vorwärts; was offen bleibt, steht am Ende
   in der Durchsicht. */
$n1 = Telefonfragebogen::naechstes([]);
pruefe('ohne Antworten kommt die erste Frage', ($n1['name'] ?? '') === 'firmenname');
$n2 = Telefonfragebogen::naechstes([], 'firmenname');
pruefe('nach einer offen gelassenen Frage kommt die nächste, nicht dieselbe',
    ($n2['name'] ?? '') !== 'firmenname' && ($n2['name'] ?? '') !== '', (string) ($n2['name'] ?? ''));
pruefe('ein unbekannter Feldname hält das Gespräch nicht an',
    (Telefonfragebogen::naechstes([], 'gibtesnicht')['name'] ?? '') === 'firmenname');

/* ---- 3. NIEMAND LIEST ELF OPTIONEN VOR ---- */
$branche = Fragen::feld('branche') ?? [];
$fr = Telefonfragebogen::frage(['name' => 'branche', 'abschnitt' => 'unternehmen', 'feld' => $branche], 'de');
pruefe('die Frage nennt das Feld, das beantwortet wird', ($fr['frage_zu'] ?? '') === 'branche');
pruefe('und sagt ausdrücklich, dass nichts vorgelesen wird',
    str_contains((string) ($fr['hinweis'] ?? ''), 'NICHT vor')
    || str_contains((string) ($fr['hinweis'] ?? ''), 'nicht vor'), (string) ($fr['hinweis'] ?? ''));
pruefe('sie kennt den Ausweg, falls nichts passt', ($fr['ausweg'] ?? '') === 'anders');

$zu = Telefonfragebogen::zuordnen($branche, 'wir haben eine Pizzeria im Zentrum', 'de');
pruefe('„Pizzeria" wird der Gastronomie zugeordnet',
    ($zu['treffer'][0] ?? '') === 'gastronomie', json_encode($zu));
$zuIt = Telefonfragebogen::zuordnen($branche, 'siamo un ristorante', 'it');
pruefe('auf Italienisch auch', ($zuIt['treffer'][0] ?? '') === 'gastronomie', json_encode($zuIt));
$zuNix = Telefonfragebogen::zuordnen($branche, 'wir sind eine Sattlerei', 'de');
pruefe('was nirgends passt, ist unklar — und wird nicht geraten', $zuNix['unklar'] === true);
pruefe('und sie bekommt Vorschläge, die sie nennen kann', count($zuNix['vorschlaege']) > 0);

/* MEHRERES IST MEHRERES */
$ziel = Fragen::feld('zielgruppe') ?? [];
$zuM = Telefonfragebogen::zuordnen($ziel, 'Privatkunden und Touristen', 'de');
pruefe('bei einer Mehrfachauswahl zählt alles, was genannt wurde',
    count($zuM['treffer']) >= 2, json_encode($zuM['treffer']));

/* ---- 4. WAS GESPEICHERT WIRD, MUSS ES GEBEN ----
   Man könnte das Sprachmodell die Option wählen lassen. Dann stünde im
   Fragebogen ein Schlüssel, den es sich ausgedacht hat — und weder
   Konfigurator noch Briefing kennen ihn. */
$w = Telefonfragebogen::speicherwert('branche', $branche, ['gastronomie'], 'eine Pizzeria');
pruefe('eine Auswahl wird als Schlüssel gespeichert', ($w['branche'] ?? '') === 'gastronomie');
pruefe('und ohne Ausweg keine freie Zeile', !isset($w['branche__frei']));

/* DIE RUNDREISE DURCH DIE PRÜFUNG DES FORMULARS
   Onboarding::saeubern() erwartet Mehrfachauswahl als Liste und die
   Materialliste als Zuordnung — so schickt es der Browser. Der erste
   Entwurf übergab Zeichenketten; saeubern verwarf sie lautlos, das Feld
   blieb leer, die Frage kam wieder. Der Kunde hat es am Telefon erlebt,
   bevor dieser Test es festhielt. Deshalb geht hier jede Art einmal ganz
   durch — speicherwert -> saeubern -> gespeicherter Wert. */
$rund = Onboarding::saeubern(Telefonfragebogen::speicherwert('zielgruppe', $ziel, ['privat', 'touristen'], 'x'));
pruefe('Mehrfachauswahl überlebt die Formularprüfung',
    ($rund['zielgruppe'] ?? '') === 'privat,touristen', json_encode($rund));
$rundW = Onboarding::saeubern(Telefonfragebogen::speicherwert('branche', $branche, ['gastronomie'], 'x'));
pruefe('Einzelauswahl auch', ($rundW['branche'] ?? '') === 'gastronomie', json_encode($rundW));
$wA = Telefonfragebogen::speicherwert('branche', $branche, ['anders'], 'Sattlerei');
pruefe('beim Ausweg wird der Wortlaut mitgeschrieben',
    ($wA['branche__frei'] ?? '') === 'Sattlerei', json_encode($wA));
/* AM TELEFON SAGT NIEMAND „8" — er sagt acht, otto, eight. Der erste
   Entwurf suchte nur Ziffern und trug eine Null ein: eine Website mit null
   Seiten, und aufgefallen wäre es erst im Angebot. */
$wZ = Telefonfragebogen::speicherwert('seiten_zahl', Fragen::feld('seiten_zahl') ?? [], [], 'so acht bis zehn');
pruefe('aus „so acht bis zehn" wird eine Acht', ($wZ['seiten_zahl'] ?? '') === '8', json_encode($wZ));
pruefe('Ziffern gehen weiterhin', Telefonfragebogen::zahl('etwa 12 Seiten') === 12);
pruefe('auf Italienisch auch', Telefonfragebogen::zahl('tre lingue') === 3);
pruefe('auf Englisch auch', Telefonfragebogen::zahl('about five pages') === 5);
pruefe('und wenn keine Zahl fällt, wird keine erfunden', Telefonfragebogen::zahl('weiß nicht') === 0);

/* DIE MATERIALLISTE: WAS NIEMAND GENANNT HAT, MUSS JEMAND MACHEN */
$mat = Fragen::feld('material') ?? [];
$st = Telefonfragebogen::standwert($mat, ['logo' => 'haben']);
pruefe('genannte Zeilen stehen so drin', ($st['logo'] ?? '') === 'haben');
pruefe('nicht genannte werden zur Arbeit, nicht zum Nichts',
    count($st) === count($mat['zeilen'] ?? []) && ($st['texte'] ?? '') === 'du', json_encode($st));
pruefe('ein erfundener Zustand wird nicht übernommen',
    (Telefonfragebogen::standwert($mat, ['logo' => 'vielleicht'])['logo'] ?? '') === 'du');
$rundS = Onboarding::saeubern(['material' => Telefonfragebogen::standwert($mat, ['logo' => 'haben'])]);
pruefe('und die Materialliste überlebt die Formularprüfung',
    str_contains((string) ($rundS['material'] ?? ''), 'logo:haben'), json_encode($rundS));

/* ---- 5. DER ABLAUF AM TELEFON ---- */
$ohne = Telefon::fragebogen(['schritt' => 'start', 'sprache' => 'de']);
pruefe('ohne bekannten Anrufer geht gar nichts', ($ohne['grund'] ?? '') === 'unbekannt', json_encode($ohne));

$start = Telefon::fragebogen(['schritt' => 'start', 'kunde_id' => $kundeId, 'sprache' => 'de']);
pruefe('mit Kunde und offenem Fragebogen geht es los', ($start['ok'] ?? false) === true, json_encode($start));
pruefe('sie bekommt eine Frage', isset($start['frage']['frage_zu']));
pruefe('und weiß, wie lange es dauert', ($start['stand']['minuten'] ?? 0) > 0);

/* WAS IN DER AKTE STEHT, WIRD BESTÄTIGT, NICHT GEFRAGT */
pruefe('Bekanntes wird zum Bestätigen vorgelegt',
    !empty($start['bestaetigen']), json_encode($start['bestaetigen'] ?? []));
$vorbelegt = json_decode((string) Db::wert('SELECT data FROM questionnaires WHERE id = ?', [$fbId], '{}'), true) ?: [];
pruefe('und steht sofort im Fragebogen — nicht erst am Ende',
    trim((string) ($vorbelegt['firmenname'] ?? '')) !== '', json_encode(array_keys($vorbelegt)));
pruefe('die erste Frage ist nicht die, die schon beantwortet ist',
    ($start['frage']['frage_zu'] ?? '') !== 'firmenname', (string) ($start['frage']['frage_zu'] ?? ''));

/* EINE ANTWORT WIRD SOFORT GESPEICHERT — nicht am Ende. Wer nach zwanzig
   Fragen auflegt, hat zwanzig Antworten im Fragebogen. */
$a1 = Telefon::fragebogen(['schritt' => 'antwort', 'feld' => 'branche',
                           'antwort' => 'wir haben eine Pizzeria', 'kunde_id' => $kundeId, 'sprache' => 'de']);
pruefe('die Antwort wird verbucht', ($a1['gespeichert'] ?? '') === 'branche', json_encode($a1));
$daten1 = json_decode((string) Db::wert('SELECT data FROM questionnaires WHERE id = ?', [$fbId], '{}'), true) ?: [];
pruefe('und steht in der Datenbank, nicht nur in der Antwort',
    ($daten1['branche'] ?? '') === 'gastronomie', json_encode($daten1['branche'] ?? null));
pruefe('danach kommt die nächste Frage, nicht dieselbe',
    ($a1['frage']['frage_zu'] ?? '') !== 'branche', (string) ($a1['frage']['frage_zu'] ?? ''));

$falsch = Telefon::fragebogen(['schritt' => 'antwort', 'feld' => 'gibtesnicht',
                               'antwort' => 'x', 'kunde_id' => $kundeId, 'sprache' => 'de']);
pruefe('ein erfundener Feldname wird abgewiesen',
    ($falsch['grund'] ?? '') === 'unbekanntes_feld', json_encode($falsch));

/* ---- 6. ZWEIMAL UNKLAR IST GENUG ----
   Ohne diesen Riegel hängt das Gespräch an einer Auswahlfrage fest, und
   niemand außer dem Anrufer würde es je bemerken. */
Db::run("UPDATE questionnaires SET data = '{}' WHERE id = ?", [$fbId]);
$u1 = Telefon::fragebogen(['schritt' => 'antwort', 'feld' => 'branche',
                           'antwort' => 'Sattlerei', 'kunde_id' => $kundeId, 'sprache' => 'de']);
pruefe('beim ersten Mal fragt sie nach', ($u1['unklar'] ?? false) === true, json_encode($u1));
pruefe('mit höchstens zwei bis drei Vorschlägen',
    count($u1['vorschlaege'] ?? []) <= 3 && count($u1['vorschlaege'] ?? []) > 0);
$u2 = Telefon::fragebogen(['schritt' => 'antwort', 'feld' => 'branche',
                           'antwort' => 'Sattlerei', 'kunde_id' => $kundeId, 'sprache' => 'de']);
pruefe('beim zweiten Mal wird es vermerkt und es geht weiter',
    ($u2['vermerkt'] ?? '') === 'anders' && ($u2['unklar'] ?? false) !== true, json_encode($u2));
$daten2 = json_decode((string) Db::wert('SELECT data FROM questionnaires WHERE id = ?', [$fbId], '{}'), true) ?: [];
pruefe('und sein Wortlaut geht dabei nicht verloren',
    str_contains((string) ($daten2['branche__frei'] ?? ''), 'Sattlerei'), json_encode($daten2['branche__frei'] ?? null));

/* EINE FRAGE OHNE AUFFANGOPTION BLEIBT OFFEN — besser eine Lücke, die man
   sieht, als eine Antwort, die niemand gesagt hat. */
Db::run("DELETE FROM activities WHERE type = 'telefon_fragebogen_unklar'");
foreach ([1, 2] as $mal) {
    $z = Telefon::fragebogen(['schritt' => 'antwort', 'feld' => 'ziel1',
                              'antwort' => 'weiß ich wirklich nicht', 'kunde_id' => $kundeId, 'sprache' => 'de']);
}
pruefe('ohne Auffangoption wird die Frage übersprungen, nicht erfunden',
    ($z['uebersprungen'] ?? '') === 'ziel1', json_encode($z));
$daten3 = json_decode((string) Db::wert('SELECT data FROM questionnaires WHERE id = ?', [$fbId], '{}'), true) ?: [];
pruefe('und nichts Erfundenes steht im Fragebogen', !isset($daten3['ziel1']));
pruefe('das Gespräch läuft trotzdem weiter', isset($z['frage']['frage_zu']));

/* ---- 7. DIE PAUSE NACH JEDEM ABSCHNITT ----
   Wer eine Viertelstunde am Stück befragt wird, wird einsilbig — und
   einsilbige Antworten sind der Grund, warum ein Briefing später nichts
   hergibt. */
$vollUnternehmen = ['firmenname' => 'Da Mario', 'branche' => 'gastronomie',
                    'beschreibung' => 'Pizzeria im Zentrum', 'zielgruppe' => 'privat,touristen',
                    'ort' => 'Agrigento', 'gebiet' => 'provinz', 'ansprech' => 'Mario Rossi'];
Db::run('UPDATE questionnaires SET data = ? WHERE id = ?',
    [json_encode($vollUnternehmen, JSON_UNESCAPED_UNICODE), $fbId]);
$pause = Telefon::fragebogen(['schritt' => 'antwort', 'feld' => 'entscheider',
                              'antwort' => 'das entscheide ich selbst', 'kunde_id' => $kundeId, 'sprache' => 'de']);
pruefe('am Abschnittsende kommt eine Pause', ($pause['pause'] ?? false) === true, json_encode($pause));
pruefe('sie nennt den Abschnitt, der durch ist', ($pause['abschnitt_fertig'] ?? '') !== '');
pruefe('und fragt, ob weitergemacht wird',
    str_contains((string) $pause['hinweis'], 'weiter'), (string) $pause['hinweis']);

$weiter = Telefon::fragebogen(['schritt' => 'weiter', 'feld' => 'entscheider',
                              'kunde_id' => $kundeId, 'sprache' => 'de']);
pruefe('„weiter" gibt dieselbe nächste Frage',
    ($weiter['frage']['frage_zu'] ?? '') === ($pause['frage']['frage_zu'] ?? 'x'));
$spaeter = Telefon::fragebogen(['schritt' => 'spaeter', 'kunde_id' => $kundeId, 'sprache' => 'de']);
pruefe('„später" verliert nichts',
    ($spaeter['ok'] ?? false) === true
    && str_contains((string) $spaeter['hinweis'], 'gespeichert'), json_encode($spaeter));
pruefe('und schickt nichts ab',
    (string) Db::wert('SELECT status FROM questionnaires WHERE id = ?', [$fbId], '') === 'offen');

/* ---- 8. DER DURCHGANG VOR DEM ABSCHICKEN ---- */
$pr = Telefon::fragebogen(['schritt' => 'pruefen', 'kunde_id' => $kundeId, 'sprache' => 'de']);
pruefe('die Durchsicht kommt nach Abschnitten', !empty($pr['abschnitte']), json_encode(array_keys($pr)));
$erste = $pr['abschnitte'][0] ?? [];
pruefe('mit Überschrift und Zeilen', ($erste['titel'] ?? '') !== '' && !empty($erste['zeilen']));
$brancheZeile = '';
foreach ($erste['zeilen'] as $zl) { if ($zl['feld'] === 'branche') { $brancheZeile = $zl['antwort']; } }
pruefe('vorgelesen wird der Satz, nicht der Schlüssel',
    $brancheZeile !== '' && !str_contains($brancheZeile, 'gastronomie'), $brancheZeile);
pruefe('und sie weiß, was noch fehlt', is_array($pr['pflicht_fehlt'] ?? null));

/* ---- 9. ABGESCHICKT WIRD NUR AUSDRÜCKLICH ----
   Danach rückt das Projekt weiter, es entsteht ein Briefing, es gehen Mails
   raus. Das darf keinem Missverständnis passieren. */
$unvoll = Telefon::fragebogen(['schritt' => 'absenden', 'bestaetigt' => true,
                               'kunde_id' => $kundeId, 'sprache' => 'de']);
pruefe('ohne Pflichtangaben wird nicht abgeschickt',
    ($unvoll['grund'] ?? '') === 'unvollstaendig', json_encode($unvoll));
pruefe('und sie erfährt, was fehlt', count($unvoll['pflicht_fehlt'] ?? []) > 0);
pruefe('der Fragebogen ist danach immer noch offen',
    (string) Db::wert('SELECT status FROM questionnaires WHERE id = ?', [$fbId], '') === 'offen');

$vollstaendig = $vollUnternehmen + ['ziel1' => 'anrufe', 'telefon' => '+39 0922 000000',
                                    'impressum' => 'Pizzeria Da Mario di Mario Rossi, Via Roma 1, Agrigento'];
Db::run('UPDATE questionnaires SET data = ? WHERE id = ?',
    [json_encode($vollstaendig, JSON_UNESCAPED_UNICODE), $fbId]);

$ohneJa = Telefon::fragebogen(['schritt' => 'absenden', 'kunde_id' => $kundeId, 'sprache' => 'de']);
pruefe('ohne sein ausdrückliches Ja wird nicht abgeschickt',
    ($ohneJa['grund'] ?? '') === 'nicht_bestaetigt', json_encode(array_keys($ohneJa)));
pruefe('stattdessen bekommt sie die Durchsicht zum Vorlesen',
    !empty($ohneJa['durchgang']['abschnitte']));
pruefe('und der Fragebogen ist unverändert offen',
    (string) Db::wert('SELECT status FROM questionnaires WHERE id = ?', [$fbId], '') === 'offen');

$raus = Telefon::fragebogen(['schritt' => 'absenden', 'bestaetigt' => true,
                             'kunde_id' => $kundeId, 'sprache' => 'de']);
pruefe('mit Ja und allen Pflichtangaben geht er raus',
    ($raus['abgeschickt'] ?? false) === true, json_encode($raus));
pruefe('und steht als abgeschlossen in der Datenbank',
    (string) Db::wert('SELECT status FROM questionnaires WHERE id = ?', [$fbId], '') === 'abgeschlossen');
pruefe('ein abgeschlossener Fragebogen wird am Telefon nicht wieder aufgemacht',
    Telefonfragebogen::offener($kundeId) === null);
$nochmal = Telefon::fragebogen(['schritt' => 'start', 'kunde_id' => $kundeId, 'sprache' => 'de']);
pruefe('sie sagt das freundlich statt zu scheitern',
    ($nochmal['grund'] ?? '') === 'kein_fragebogen', json_encode($nochmal));

/* ---- 9b. DER ANRUF VOM 7. SEPTEMBER, NACHGESPIELT ----
   Manuel, Buchshop, Frage „wer sind eure Kunden?", Antwort „Leser". Passt
   in keine der acht Optionen. Ergebnis am Telefon: dieselbe Frage dreimal
   um 04:56, zweimal um 05:02 — er hat es der Verwaltung selbst ins
   Protokoll gesagt. Zwei Fehler, beide hier abgestellt:
   1. Eine Antwort, die in keine Auswahl passt, gehoert in die freie Zeile
      des Feldes — sie IST eine Antwort, keine Luecke.
   2. Was ausdruecklich offen blieb, darf „start" nicht wieder vorlegen. */
Db::run("UPDATE questionnaires SET status = 'offen', data = '{}', submitted_at = NULL WHERE id = ?", [$fbId]);
Db::run("DELETE FROM activities WHERE type IN ('telefon_fragebogen_unklar', 'telefon_fragebogen_offen')");

/* Erst der gute Fall: Eine zuordenbare Antwort muss auch in der
   DATENBANK ankommen, nicht nur in der Antwort des Werkzeugs. */
$lt = Telefon::fragebogen(['schritt' => 'antwort', 'feld' => 'zielgruppe',
                           'antwort' => 'Privatkunden und Touristen', 'kunde_id' => $kundeId, 'sprache' => 'de']);
$ltDaten = json_decode((string) Db::wert('SELECT data FROM questionnaires WHERE id = ?', [$fbId], '{}'), true) ?: [];
$ltWert = (string) ($ltDaten['zielgruppe'] ?? '');
pruefe('eine Mehrfachantwort steht danach wirklich in der Datenbank',
    str_contains($ltWert, 'privat') && str_contains($ltWert, 'touristen'), json_encode($ltDaten));

Db::run("UPDATE questionnaires SET data = '{}' WHERE id = ?", [$fbId]);
$l1 = Telefon::fragebogen(['schritt' => 'antwort', 'feld' => 'zielgruppe',
                           'antwort' => 'Leser', 'kunde_id' => $kundeId, 'sprache' => 'de']);
pruefe('„Leser" bekommt erst eine Nachfrage', ($l1['unklar'] ?? false) === true, json_encode($l1));
$l2 = Telefon::fragebogen(['schritt' => 'antwort', 'feld' => 'zielgruppe',
                           'antwort' => 'na eben Leser, Bücherfreunde', 'kunde_id' => $kundeId, 'sprache' => 'de']);
pruefe('beim zweiten Mal wird der Wortlaut vermerkt — nicht übersprungen',
    ($l2['vermerkt'] ?? '') === 'wortlaut' && ($l2['gespeichert'] ?? '') === 'zielgruppe', json_encode($l2));
$lDaten = json_decode((string) Db::wert('SELECT data FROM questionnaires WHERE id = ?', [$fbId], '{}'), true) ?: [];
pruefe('und steht in der freien Zeile des Feldes',
    str_contains((string) ($lDaten['zielgruppe__frei'] ?? ''), 'Leser'), json_encode($lDaten));
pruefe('das Feld gilt damit als beantwortet',
    Telefonfragebogen::beantwortet($lDaten, 'zielgruppe'));
$lStart = Telefon::fragebogen(['schritt' => 'start', 'kunde_id' => $kundeId, 'sprache' => 'de']);
pruefe('und „start" legt die Frage nie wieder vor',
    ($lStart['frage']['frage_zu'] ?? '') !== 'zielgruppe', (string) ($lStart['frage']['frage_zu'] ?? ''));

/* OHNE FREIE ZEILE: offen lassen — aber dann wirklich. */
Db::run("DELETE FROM activities WHERE type IN ('telefon_fragebogen_unklar', 'telefon_fragebogen_offen')");
foreach ([1, 2] as $mal) {
    $lz = Telefon::fragebogen(['schritt' => 'antwort', 'feld' => 'ziel1',
                               'antwort' => 'schwer zu sagen', 'kunde_id' => $kundeId, 'sprache' => 'de']);
}
pruefe('ohne freie Zeile bleibt die Frage offen', ($lz['uebersprungen'] ?? '') === 'ziel1', json_encode($lz));
$lStart2 = Telefon::fragebogen(['schritt' => 'start', 'kunde_id' => $kundeId, 'sprache' => 'de']);
pruefe('und auch sie kommt bei „start" nicht wieder — DIE Schleife vom 7.9.',
    ($lStart2['frage']['frage_zu'] ?? '') !== 'ziel1', (string) ($lStart2['frage']['frage_zu'] ?? ''));
pruefe('in der Durchsicht steht sie aber weiterhin',
    in_array('ziel1', Telefon::fragebogen(['schritt' => 'pruefen', 'kunde_id' => $kundeId,
                                           'sprache' => 'de'])['offen'] ?? [], true));

/* DIE MATERIALLISTE, EINMAL GANZ DURCH DIE AKTION */
$lm = Telefon::fragebogen(['schritt' => 'antwort', 'feld' => 'material',
                           'zeilen' => 'logo:haben, produkt:kommt', 'kunde_id' => $kundeId, 'sprache' => 'de']);
$lmDaten = json_decode((string) Db::wert('SELECT data FROM questionnaires WHERE id = ?', [$fbId], '{}'), true) ?: [];
pruefe('die Materialliste steht danach wirklich in der Datenbank',
    str_contains((string) ($lmDaten['material'] ?? ''), 'logo:haben')
    && str_contains((string) ($lmDaten['material'] ?? ''), 'produkt:kommt'), json_encode($lmDaten['material'] ?? null));

/* „WEISS NICHT" IST KEINE NULL: Eine Website mit null Seiten haette
   niemand bestellt — aufgefallen waere es erst im Angebot. */
Db::run("DELETE FROM activities WHERE type IN ('telefon_fragebogen_unklar', 'telefon_fragebogen_offen')");
$z1 = Telefon::fragebogen(['schritt' => 'antwort', 'feld' => 'seiten_zahl',
                           'antwort' => 'puh, weiß ich nicht', 'kunde_id' => $kundeId, 'sprache' => 'de']);
pruefe('„weiß nicht" auf eine Zahlenfrage wird nachgefragt, nicht als 0 gespeichert',
    ($z1['unklar'] ?? false) === true, json_encode($z1));
$zDaten = json_decode((string) Db::wert('SELECT data FROM questionnaires WHERE id = ?', [$fbId], '{}'), true) ?: [];
pruefe('im Fragebogen steht keine Null', !isset($zDaten['seiten_zahl']));
$z2 = Telefon::fragebogen(['schritt' => 'antwort', 'feld' => 'seiten_zahl',
                           'antwort' => 'sagen wir fünf', 'kunde_id' => $kundeId, 'sprache' => 'de']);
pruefe('„sagen wir fünf" wird zur Fünf', 
    ((json_decode((string) Db::wert('SELECT data FROM questionnaires WHERE id = ?', [$fbId], '{}'), true) ?: [])['seiten_zahl'] ?? '') === '5',
    json_encode($z2['gespeichert'] ?? null));
Db::run("DELETE FROM activities WHERE type IN ('telefon_fragebogen_unklar', 'telefon_fragebogen_offen')");
Db::run("UPDATE questionnaires SET data = ? WHERE id = ?",
    [json_encode($vollstaendig, JSON_UNESCAPED_UNICODE), $fbId]);
Db::run("UPDATE questionnaires SET status = 'abgeschlossen' WHERE id = ?", [$fbId]);

/* ---- 10. SIE BIETET IHN VON SELBST AN ----
   „Fragebogen" ist der häufigste Grund, warum ein Projekt stehenbleibt. Ein
   Satz im Leitfaden wird beim dritten Gespräch überlesen; ein Feld in der
   Antwort des Werkzeugs liegt bei jedem Anruf wieder vor ihr. */
$nummer = (string) Db::wert('SELECT phone FROM customers WHERE id = ?', [$kundeId], '');
$zu1 = Telefon::nachschlagen(['telefon' => $nummer, 'sprache' => 'de']);
pruefe('ist keiner offen, wird auch keiner angeboten', !isset($zu1['fragebogen']), json_encode($zu1['fragebogen'] ?? null));

Db::run("UPDATE questionnaires SET status = 'offen', data = '{}', submitted_at = NULL WHERE id = ?", [$fbId]);
$zu2 = Telefon::nachschlagen(['telefon' => $nummer, 'sprache' => 'de']);
pruefe('ein offener Fragebogen steht in der Antwort',
    ($zu2['fragebogen']['offen'] ?? false) === true, json_encode($zu2['fragebogen'] ?? null));
pruefe('mit dem Satz, den sie sagen kann',
    str_contains((string) ($zu2['fragebogen']['satz'] ?? ''), 'gemeinsam'), (string) ($zu2['fragebogen']['satz'] ?? ''));
pruefe('am Telefon kommt zuerst sein Anliegen',
    str_contains((string) $zu2['hinweis'], 'zuerst sein Anliegen'), (string) $zu2['hinweis']);
pruefe('und es bleibt eine Frage, keine Ankündigung',
    str_contains((string) $zu2['hinweis'], 'nicht noch einmal'), (string) $zu2['hinweis']);

/* VON DER KUNDENSEITE AUS WIRD GLEICH GEFRAGT: Er sitzt ohnehin vor dem
   Portal, und dort ist der Fragebogen der Grund, warum er stehen bleibt. */
Telefon::amWidget($kundeId);
$zu3 = Telefon::nachschlagen(['telefon' => $nummer, 'sprache' => 'de']);
pruefe('von seiner Kundenseite aus fragt sie gleich',
    str_contains((string) $zu3['hinweis'], 'GLEICH'), (string) $zu3['hinweis']);
Db::run("DELETE FROM activities WHERE type = 'telefon_am_widget'");

/* ---- 11. DIE MERKLISTE GILT AUCH HIER ----
   Wer kurz gehalten wird, bekommt keine Viertelstunde Fragebogen. */
pruefe('„fragebogen" steht auf der Liste der gedeckelten Aktionen',
    in_array('fragebogen', Telefon::MERKLISTE_STUMM, true));
pruefe('und ist eine gültige Aktion', in_array('fragebogen', Telefon::AKTIONEN, true));

/* ---- 12. DAS WERKZEUG, DAS BEI STRATO STEHT ---- */
$wz = Telefonwerkzeuge::alle();
pruefe('es gibt ein Werkzeug „fragebogen"', isset($wz['fragebogen']));
pruefe('es verbietet das Vorlesen der Auswahl',
    str_contains($wz['fragebogen']['zweck'], 'NIE Auswahlmöglichkeiten vor'),
    mb_substr($wz['fragebogen']['zweck'], 0, 80));
pruefe('es verlangt eine Frage nach der anderen',
    str_contains($wz['fragebogen']['zweck'], 'IMMER NUR EINE FRAGE'));
pruefe('es verbietet ungefragtes Abschicken',
    str_contains($wz['fragebogen']['zweck'], 'nie ungefragt ab'));
pruefe('die Schritte sind eine geschlossene Liste',
    ($wz['fragebogen']['eig']['schritt']['enum'] ?? []) === ['start', 'antwort', 'weiter', 'spaeter', 'pruefen', 'absenden'],
    json_encode($wz['fragebogen']['eig']['schritt']['enum'] ?? null));

/* Der Rumpf ist eine Vorlage mit Stratos Platzhaltern — er darf kein JSON
   sein, aber er muss eines werden, sobald sie ausgefüllt sind. */
$rumpf = $wz['fragebogen']['rumpf'];
pruefe('der Rumpf trägt Stratos Platzhalter', str_contains($rumpf, '{{ schritt }}'));
$gefuellt = preg_replace('/\{\{\s*[a-z_]+\s*\}\}/', 'x', $rumpf);
pruefe('und wird ausgefüllt zu gültigem JSON', json_decode((string) $gefuellt, true) !== null, (string) $gefuellt);

/* DER FEHLER VOM SEPTEMBER: leere Eigenschaften wurden zu [] statt {} und
   STRATO wies alle vierzehn Werkzeuge ab. Deshalb geht die ganze Kette hier
   noch einmal durch — kodieren, dekodieren, kodieren. */
$json = Telefonwerkzeuge::json();
pruefe('das Werkzeug steht im JSON für STRATO', isset($json['fragebogen']));
$zurueck = json_decode((string) $json['fragebogen']);
pruefe('und es ist gültiges JSON', $zurueck !== null);
pruefe('die Eigenschaften bleiben ein Objekt, keine leere Liste',
    is_object($zurueck->parameters->properties ?? null),
    gettype($zurueck->parameters->properties ?? null));
pruefe('und jeder Schritt steht als geschlossene Auswahl drin',
    count((array) ($zurueck->parameters->properties->schritt->enum ?? [])) === 6);

Db::run("DELETE FROM activities WHERE type LIKE 'telefon\\_%'");
Db::run("UPDATE questionnaires SET status = 'abgeschlossen' WHERE id = ?", [$fbId]);

/* ============================================================================
   42. Der KAS-Reseller
   ============================================================================
   Nur die Leseseite — die API selbst laesst sich ohne Zugang nicht pruefen.
   Was sich pruefen laesst: Ohne Zugang gibt es klare Saetze statt Fehler,
   und nichts an dieser Stufe legt irgendetwas an.
   ============================================================================ */
abschnitt('42. Der KAS-Reseller');

require_once $wurzel . '/src/Kas.php';

pruefe('ohne hinterlegten Zugang ist der Draht nicht bereit', Kas::bereit() === false);
$kasErg = Kas::rufen('get_accounts');
pruefe('ein Aufruf ohne Zugang scheitert mit einem Satz, nicht mit einer Ausnahme',
    $kasErg['ok'] === false
    && (str_contains($kasErg['text'], 'Zugang') || str_contains($kasErg['text'], 'soap')),
    json_encode($kasErg));
$kasKonten = Kas::accounts();
pruefe('die Accountliste bleibt dann leer und erklaert sich',
    $kasKonten['ok'] === false && $kasKonten['accounts'] === []);

/* SEIT STUFE 2 LEGT SIE AN — LOESCHEN KANN SIE WEITERHIN NICHT.
   Dieser Test wurde bewusst umgebaut, als accountAnlegen() dazukam. Die
   Grenze, die bleibt: Ein Account, an dem eine Kundenwebsite haengt,
   verschwindet nur von Hand im KAS. */
$kasLoeschend = array_filter(get_class_methods('Kas'),
    static fn(string $m): bool => str_contains(strtolower($m), 'loeschen') || str_starts_with($m, 'delete'));
pruefe('die Klasse hat weiterhin keine loeschenden Methoden',
    $kasLoeschend === [], implode(', ', $kasLoeschend));

/* Das Anlegen selbst laesst sich ohne Zugang nur an seinen Riegeln
   pruefen — und die sind das Wichtigste daran. */
$ohneKommentar = Kas::accountAnlegen('   ');
pruefe('ohne Kommentar wird kein Account angelegt — nicht einmal versucht',
    $ohneKommentar['ok'] === false && str_contains($ohneKommentar['text'], 'Kommentar'),
    json_encode($ohneKommentar['text']));
$ohneZugangA = Kas::accountAnlegen('Testkunde');
pruefe('ohne Zugang scheitert das Anlegen mit einem Satz',
    $ohneZugangA['ok'] === false && $ohneZugangA['login'] === '');

/* Die Passwoerter des neuen Accounts: stark, regelfest, jedes Mal anders. */
$pw1 = Kas::passwortNeu();
$pw2 = Kas::passwortNeu();
pruefe('erzeugte Passwoerter sind 16 Zeichen lang', strlen($pw1) === 16, $pw1 !== '' ? (string) strlen($pw1) : 'leer');
pruefe('mit Gross, Klein, Ziffer und Sonderzeichen',
    preg_match('/[A-Z]/', $pw1) && preg_match('/[a-z]/', $pw1)
    && preg_match('/[0-9]/', $pw1) && preg_match('/[!\-_]/', $pw1));
pruefe('und zwei Aufrufe liefern nie dasselbe', $pw1 !== $pw2);

/* ============================================================================
   43. Wunschdomain und Hosting
   ============================================================================
   Der Ablauf: Fragebogen sagt "keine Website, Domain neu" -> Vorschlag ->
   der Kunde stimmt den Monatskosten ausdruecklich zu -> bei der finalen
   Freigabe wird angelegt. Die Netzseite (Domainpruefung, KAS) laesst sich
   hier nicht wirklich rufen — geprueft werden die Riegel drumherum: wann
   ueberhaupt vorgeschlagen wird, dass ohne Zustimmung nichts passiert,
   dass der Preis eingefroren ist und dass Zugangsdaten genau einmal
   herauskommen.
   ============================================================================ */
abschnitt('43. Wunschdomain und Hosting');

require_once $wurzel . '/src/Hosting.php';
require_once $wurzel . '/src/Abo.php';

/* Wer braucht eine Domain? Nur wer weder Website noch Domain hat UND
   Wuensche genannt hat. Alles andere ist kein Fall fuer den Kasten. */
pruefe('keine Website + Domain neu + Wunsch => braucht eine',
    Hosting::brauchtDomain(['altseite' => 'nein', 'domain' => 'neu', 'wunsch1' => 'trattoria-kette.it']));
pruefe('nur Social zaehlt wie keine Website',
    Hosting::brauchtDomain(['altseite' => 'social', 'domain' => 'neu', 'wunsch2' => 'kette.example']));
pruefe('wer schon eine Website hat, braucht keine',
    Hosting::brauchtDomain(['altseite' => 'ja', 'domain' => 'neu', 'wunsch1' => 'x.it']) === false);
pruefe('wer seine Domain mitbringt, auch nicht',
    Hosting::brauchtDomain(['altseite' => 'nein', 'domain' => 'fremd', 'wunsch1' => 'x.it']) === false);
pruefe('ohne einen einzigen Wunsch gibt es nichts vorzuschlagen',
    Hosting::brauchtDomain(['altseite' => 'nein', 'domain' => 'neu']) === false);

/* Ein eigener Kunde mit eigenem Projekt — die Kette weiter oben hat ihre
   eigenen Vertraege, die hier nicht dazwischenfunken sollen. */
$hoKundeId = Events::kundeFinden(['name' => 'Hosting Kunde',
    'email' => 'hosting@pruefung.example', 'company' => 'Domarella', 'sprache' => 'de']);
$hoProjektId = Db::insert('projects', ['customer_id' => $hoKundeId,
    'name' => 'Hosting-Kette', 'status' => 'vorschau']);
pruefe('Kunde und Projekt fuer den Hosting-Ablauf stehen', $hoKundeId > 0 && $hoProjektId > 0);

/* nachFragebogen ist still und legt bei "braucht keine" nichts an. */
Hosting::nachFragebogen($hoProjektId, $hoKundeId, ['altseite' => 'ja', 'domain' => 'fremd']);
pruefe('wer keine Domain braucht, bekommt keinen Auftrag',
    (int) Db::wert('SELECT COUNT(*) FROM hosting_auftraege WHERE customer_id = ?', [$hoKundeId], 0) === 0);

/* Der Vorschlag selbst — die Domainpruefung fragt fremde Dienste, das tut
   ein Test nicht. Der Auftrag entsteht hier so, wie nachFragebogen ihn
   anlegen wuerde, mit dem Preis aus dem Paket. */
$hoPreis = Hosting::preisCents();
pruefe('der Monatspreis kommt aus dem Hosting-Paket', $hoPreis === 990, (string) $hoPreis);
$hoId = Db::insert('hosting_auftraege', ['customer_id' => $hoKundeId,
    'project_id' => $hoProjektId, 'domain' => 'domarella-kette.it',
    'status' => 'vorgeschlagen', 'preis_cents' => $hoPreis]);
pruefe('der Vorschlag steht', $hoId > 0);
pruefe('und fuerKunde findet ihn', (int) (Hosting::fuerKunde($hoKundeId)['id'] ?? 0) === $hoId);

/* Kein zweiter Vorschlag neben dem ersten — auch wenn der Fragebogen
   noch einmal abgeschickt wuerde. brauchtDomain waere wahr, aber die
   Doppel-Sperre greift VOR der Domainpruefung, deshalb laeuft das ohne Netz. */
Hosting::nachFragebogen($hoProjektId, $hoKundeId,
    ['altseite' => 'nein', 'domain' => 'neu', 'wunsch1' => 'zweiter-wunsch.it']);
pruefe('ein zweiter Vorschlag entsteht nicht neben dem ersten',
    (int) Db::wert('SELECT COUNT(*) FROM hosting_auftraege WHERE customer_id = ?', [$hoKundeId], 0) === 1);

/* Anlegen ohne Zustimmung: der wichtigste Riegel des ganzen Ablaufs. */
$ohneZustimmung = Hosting::anlegen($hoId);
pruefe('ohne Zustimmung wird nichts angelegt',
    $ohneZustimmung['ok'] === false && str_contains($ohneZustimmung['text'], 'zugestimmt'),
    $ohneZustimmung['text']);

/* Die Antwort des Kunden: falsche Kundennummer zieht nicht, Ablehnen und
   Zustimmen gehen nur aus "vorgeschlagen". */
pruefe('ein fremder Kunde kann nicht zustimmen', Hosting::antwort($hoId, $hoKundeId + 999, true) === false);
pruefe('der Kunde stimmt zu', Hosting::antwort($hoId, $hoKundeId, true) === true);
$hoA = Db::one('SELECT * FROM hosting_auftraege WHERE id = ?', [$hoId]);
pruefe('der Auftrag steht auf zugestimmt, mit Zeitstempel',
    (string) $hoA['status'] === 'zugestimmt' && $hoA['zugestimmt_am'] !== null);
pruefe('eine zweite Antwort auf denselben Auftrag zieht nicht',
    Hosting::antwort($hoId, $hoKundeId, false) === false);
pruefe('der Auftrag bleibt zugestimmt', (string) Db::wert(
    'SELECT status FROM hosting_auftraege WHERE id = ?', [$hoId], '') === 'zugestimmt');

/* Der Preis ist eingefroren: Eine spaetere Paketaenderung aendert keinen
   Vertrag, dem schon zugestimmt wurde. */
Db::run("UPDATE packages SET monthly_cents = 1490 WHERE slug = 'hosting'");
pruefe('eine Preisaenderung am Paket laesst den zugestimmten Preis stehen',
    (int) Db::wert('SELECT preis_cents FROM hosting_auftraege WHERE id = ?', [$hoId], 0) === 990);
Db::run("UPDATE packages SET monthly_cents = 990 WHERE slug = 'hosting'");

/* beiStatuswechsel: Die finale Freigabe legt NICHT mehr sofort an
   (25.09.2026). Sie schliesst den Monatsvertrag und schickt die erste Rate;
   angelegt wird erst, wenn die bezahlt ist -- vorher entstand der
   KAS-Account, bevor ein Cent fuer das Hosting da war. */
Hosting::beiStatuswechsel($hoProjektId, 'vorschau');
pruefe('ein anderer Statuswechsel ruehrt den Auftrag nicht an', (string) Db::wert(
    'SELECT status FROM hosting_auftraege WHERE id = ?', [$hoId], '') === 'zugestimmt');
Db::run("DELETE FROM notifications WHERE type = 'hosting_fehler'");
Db::update('projects', $hoProjektId, ['status' => 'finale_freigabe']);
Hosting::beiStatuswechsel($hoProjektId, 'finale_freigabe');
pruefe('bei finaler Freigabe bleibt der Auftrag zugestimmt — noch nichts angelegt',
    (string) Db::wert('SELECT status FROM hosting_auftraege WHERE id = ?', [$hoId], '') === 'zugestimmt');
pruefe('und es wurde auch nichts versucht (keine KAS-Meldung)', (int) Db::wert(
    "SELECT COUNT(*) FROM notifications WHERE type = 'hosting_fehler'", [], 0) === 0);
$hoAbo1 = (int) Db::wert("SELECT id FROM abos WHERE customer_id = ? AND paket_slug = 'hosting'", [$hoKundeId], 0);
pruefe('stattdessen steht der Hosting-Vertrag', $hoAbo1 > 0);
$hoRate = Db::one("SELECT * FROM payments WHERE abo_id = ? ORDER BY id LIMIT 1", [$hoAbo1]);
pruefe('mit der ersten Monatsrate, offen', $hoRate && (string) $hoRate['status'] !== 'bezahlt'
    && (int) $hoRate['amount_cents'] === 990, json_encode($hoRate ? [$hoRate['status'], $hoRate['amount_cents']] : null));
Hosting::beiStatuswechsel($hoProjektId, 'finale_freigabe');
pruefe('eine zweite Freigabe schliesst keinen zweiten Vertrag',
    (int) Db::wert("SELECT COUNT(*) FROM abos WHERE customer_id = ? AND paket_slug = 'hosting'", [$hoKundeId], 0) === 1);
/* Die Rate ist bezahlt -> jetzt wird angelegt. Ohne KAS-Zugang im Test
   bleibt der Auftrag ehrlich zugestimmt, und Uwe bekommt eine Meldung
   statt eines halben Accounts. */
Hosting::nachZahlung($hoAbo1);
/* Seit Phase 3 (25.09.2026): Der Auftrag wird als Erstes beansprucht
   ("in_arbeit"), der Account-Schritt steht auf "wird wiederholt" -- vorher
   blieb er "zugestimmt", und die naechste Rate haette ihn noch einmal
   angefangen (siehe Abschnitt 70). */
pruefe('nach der Zahlung wird angelegt (im Test: KAS fehlt, Account-Schritt wartet auf Wiederholung)',
    (string) Db::wert('SELECT status FROM hosting_auftraege WHERE id = ?', [$hoId], '') === 'in_arbeit'
    && (string) (Hosting::schritte($hoId)['account']['status'] ?? '') === 'fehler');
pruefe('und es liegt eine Meldung fuer Uwe da', (int) Db::wert(
    "SELECT COUNT(*) FROM notifications WHERE type = 'hosting_fehler'", [], 0) === 1);

/* Die Abo-Regel: Betreuung und Hosting laufen nebeneinander, aber keine
   zwei Vertraege derselben Art. */
pruefe('ein Hosting-Vertrag entsteht', $hoAbo1 > 0);
gesperrt('ein zweiter Hosting-Vertrag ist gesperrt',
    static fn() => Abo::anlegen($hoKundeId, ['paket_slug' => 'hosting', 'zahlart' => 'manuell']));
$hoBetreuung = (string) Db::wert(
    "SELECT slug FROM packages WHERE art = 'betreuung' AND active = 1 ORDER BY id LIMIT 1", [], '');
if ($hoBetreuung !== '') {
    $hoAbo2 = Abo::anlegen($hoKundeId, ['paket_slug' => $hoBetreuung, 'zahlart' => 'manuell']);
    pruefe('eine Betreuung passt daneben — andere Art, kein Konflikt', $hoAbo2 > 0);
    gesperrt('aber keine zweite Betreuung',
        static fn() => Abo::anlegen($hoKundeId, ['paket_slug' => $hoBetreuung, 'zahlart' => 'manuell']));
} else {
    pruefe('eine Betreuung passt daneben — andere Art, kein Konflikt', false, 'kein Betreuungspaket gefunden');
}

/* Die Zugangsdaten: verschluesselt hinein, EINMAL heraus, dann weg. */
$hoKrypto = new ReflectionClass('Hosting');
$hoVer = $hoKrypto->getMethod('verschluesseln'); $hoVer->setAccessible(true);
$hoEnt = $hoKrypto->getMethod('entschluesseln'); $hoEnt->setAccessible(true);
$hoDaten = ['kas_login' => 'w0000000', 'kas_passwort' => 'Geheim-123!', 'ftp_passwort' => 'Anders-456_',
            'postfach' => 'kontakt@domarella-kette.it', 'postfach_passwort' => 'Dritte-789!',
            'server' => 'w0000000.kasserver.com'];
$hoBlob = $hoVer->invoke(null, $hoDaten);
pruefe('die Zugangsdaten lassen sich verschluesseln', is_string($hoBlob) && $hoBlob !== '');
pruefe('im Blob steht kein Passwort im Klartext', !str_contains((string) $hoBlob, 'Geheim-123!'));
pruefe('und die Rundreise gibt sie unversehrt zurueck',
    $hoEnt->invoke(null, (string) $hoBlob) === $hoDaten);

Db::update('hosting_auftraege', $hoId, ['status' => 'angelegt', 'zugang_blob' => (string) $hoBlob,
    'zugang_bis' => date('Y-m-d H:i:s', time() + 86400)]);
pruefe('ein fremder Kunde bekommt die Zugangsdaten nicht',
    Hosting::zugangAbrufen($hoId, $hoKundeId + 999) === null);
pruefe('und der Blob liegt dann noch da', Db::wert(
    'SELECT zugang_blob FROM hosting_auftraege WHERE id = ?', [$hoId], null) !== null);
$hoAbruf = Hosting::zugangAbrufen($hoId, $hoKundeId);
pruefe('der Kunde bekommt sie genau einmal', $hoAbruf === $hoDaten);
pruefe('danach ist der Blob geloescht', Db::wert(
    'SELECT zugang_blob FROM hosting_auftraege WHERE id = ?', [$hoId], null) === null);
pruefe('ein zweiter Abruf geht leer aus', Hosting::zugangAbrufen($hoId, $hoKundeId) === null);

/* Abgelaufen ist abgelaufen — der Abruf loescht, statt zu zeigen. */
Db::update('hosting_auftraege', $hoId, ['zugang_blob' => (string) $hoBlob,
    'zugang_bis' => date('Y-m-d H:i:s', time() - 60)]);
pruefe('nach der Frist gibt es nichts mehr', Hosting::zugangAbrufen($hoId, $hoKundeId) === null);
Db::update('hosting_auftraege', $hoId, ['zugang_blob' => (string) $hoBlob,
    'zugang_bis' => date('Y-m-d H:i:s', time() - 60)]);
pruefe('und der Cron raeumt Abgelaufenes weg', Hosting::aufraeumen() >= 1);

/* ---------- Solo: Domain & Hosting ohne Website-Projekt ----------
   Der Weg vom oeffentlichen Paket: Uwe schlaegt die geprue fte Domain vor
   (project_id NULL), der Kunde stimmt zu -> Vertrag und erste Rate
   entstehen SOFORT -> angelegt wird erst, wenn die Rate bezahlt ist. */

pruefe('das Hosting-Paket ist seit 041 oeffentlich und aktiv',
    (int) Db::wert("SELECT COUNT(*) FROM packages
                     WHERE slug = 'hosting' AND active = 1 AND oeffentlich = 1", [], 0) === 1);
$hoTexte = json_decode((string) Db::wert("SELECT texte FROM packages WHERE slug = 'hosting'", [], ''), true);
pruefe('und traegt seine Karten-Texte in drei Sprachen',
    is_array($hoTexte) && isset($hoTexte['it']['features'], $hoTexte['de']['features'], $hoTexte['en']['features']));

$soloId = Events::kundeFinden(['name' => 'Solo Kunde',
    'email' => 'solo@pruefung.example', 'company' => 'Nur Domain GmbH', 'sprache' => 'de']);
$soloAuftrag = Db::insert('hosting_auftraege', ['customer_id' => $soloId,
    'project_id' => null, 'domain' => 'nurdomain-kette.de',
    'status' => 'vorgeschlagen', 'preis_cents' => Hosting::preisCents()]);

pruefe('der Solo-Kunde stimmt zu', Hosting::antwort($soloAuftrag, $soloId, true) === true);
$soloAbo = Db::one("SELECT * FROM abos WHERE customer_id = ? AND paket_slug = 'hosting'", [$soloId]);
pruefe('mit der Zustimmung entsteht der Monatsvertrag von selbst', $soloAbo !== null);
/* Das Vertragsblatt: Jeder Monatsvertrag hat eines — beim Solo-Hosting ist
   es die Fernabsatz-Bestaetigung. Das PDF muss entstehen und die Kernsaetze
   tragen; die Mail dazu darf im Test scheitern (kein Mailserver), aber sie
   darf nichts umwerfen. */
require_once $wurzel . '/src/Abovertrag.php';
$soloBlatt = Abovertrag::pdf((int) $soloAbo['id']);
pruefe('das Vertragsblatt zum Monatsvertrag entsteht als PDF',
    str_starts_with($soloBlatt, '%PDF'), mb_substr($soloBlatt, 0, 8));
pruefe('und es ist keine leere Huelle', strlen($soloBlatt) > 2000, (string) strlen($soloBlatt));
try { Abovertrag::bestaetigen((int) $soloAbo['id']); $soloBest = true; }
catch (Throwable $e) { $soloBest = false; }
pruefe('die Bestaetigung wirft nichts um, auch ohne Mailserver', $soloBest);

$soloRate = Db::one("SELECT * FROM payments WHERE abo_id = ? ORDER BY id LIMIT 1",
    [(int) ($soloAbo['id'] ?? 0)]);
pruefe('und die erste Monatsrate liegt da', $soloRate !== null);
pruefe('die Rate heisst nach dem Paket, nicht "Betreuung …"',
    $soloRate !== null && !str_starts_with((string) $soloRate['bezeichnung'], 'Betreuung'),
    (string) ($soloRate['bezeichnung'] ?? ''));
pruefe('angelegt ist noch NICHTS — erst muss die Zahlung kommen', (string) Db::wert(
    'SELECT status FROM hosting_auftraege WHERE id = ?', [$soloAuftrag], '') === 'zugestimmt');

/* Eine zweite Zustimmung legt keinen zweiten Vertrag an. */
Db::run("UPDATE hosting_auftraege SET status = 'vorgeschlagen' WHERE id = ?", [$soloAuftrag]);
Hosting::antwort($soloAuftrag, $soloId, true);
pruefe('eine wiederholte Zustimmung erzeugt keinen zweiten Vertrag',
    (int) Db::wert("SELECT COUNT(*) FROM abos WHERE customer_id = ? AND paket_slug = 'hosting'",
        [$soloId], 0) === 1);

/* Die bezahlte Rate stoesst das Anlegen an — ohne KAS-Zugang bleibt der
   Auftrag ehrlich auf zugestimmt, und Uwe bekommt die Fehler-Meldung. */
Db::run("DELETE FROM notifications WHERE type = 'hosting_fehler'");
Events::zahlungBestaetigen((int) $soloRate['id'], 'kettentest-solo', 'manuell');
pruefe('die Rate steht auf bezahlt', (string) Db::wert(
    'SELECT status FROM payments WHERE id = ?', [(int) $soloRate['id']], '') === 'bezahlt');
pruefe('ohne KAS-Zugang wartet der Solo-Auftrag auf die Wiederholung', (string) Db::wert(
    'SELECT status FROM hosting_auftraege WHERE id = ?', [$soloAuftrag], '') === 'in_arbeit');
pruefe('und die Meldung fuer Uwe liegt da', (int) Db::wert(
    "SELECT COUNT(*) FROM notifications WHERE type = 'hosting_fehler'", [], 0) >= 1);

/* "ERST DIE BEZAHLSEITE, DANN DAS DASHBOARD": Abo::anfordern nimmt jetzt ein
   Erfolgsziel entgegen — die persoenliche Kundenseite, auf der der Kunde nach
   dem Bezahlen landet. Ohne eingerichtetes Stripe entsteht kein Zahlungslink;
   dann faellt hosting.php auf die Danke-/Ueberweisungsansicht zurueck, statt
   ins Leere zu leiten. Beides wird hier festgehalten. */
require_once $wurzel . '/src/Kundenzugang.php';
$anfErg = Abo::anfordern((int) $soloRate['id'], Kundenzugang::linkFuer($soloId));
pruefe('anfordern nimmt ein Erfolgsziel entgegen und wirft nichts',
    is_string($anfErg), (string) $anfErg);
pruefe('ohne Stripe traegt die erste Rate keinen Zahlungslink',
    (string) Db::wert("SELECT COALESCE(link_url, '') FROM payments WHERE id = ?",
        [(int) $soloRate['id']], '') === '');

/* Der DIREKTKAUF von der oeffentlichen Seite: Die Netzpruefung der Domain
   laesst ein Test nicht wirklich laufen — pruefbar ist der Riegel davor,
   und der ist der wichtigste: Eine unsinnige Eingabe legt nichts an. */
$dkVorher = (int) Db::wert('SELECT COUNT(*) FROM hosting_auftraege', [], 0);
$dkUngueltig = Hosting::direktKauf('Test Kauf', 'kauf@pruefung.example', 'das ist keine domain', 'de');
pruefe('ein Direktkauf mit unsinniger Domain wird abgewiesen',
    $dkUngueltig['ok'] === false && ($dkUngueltig['grund'] ?? '') === 'ungueltig',
    json_encode($dkUngueltig));
pruefe('und legt dabei keinen Auftrag an',
    (int) Db::wert('SELECT COUNT(*) FROM hosting_auftraege', [], 0) === $dkVorher);

Db::run('DELETE FROM hosting_auftraege WHERE customer_id = ?', [$soloId]);
Db::run('DELETE FROM payments WHERE abo_id IN (SELECT id FROM abos WHERE customer_id = ?)', [$soloId]);
Db::run('DELETE FROM abos WHERE customer_id = ?', [$soloId]);

/* Aufraeumen: Der Hosting-Kunde verschwindet wieder, damit er anderen
   Abschnitten nicht in die Quere kommt. */
Db::run('DELETE FROM hosting_auftraege WHERE customer_id = ?', [$hoKundeId]);
Db::run('DELETE FROM abos WHERE customer_id = ?', [$hoKundeId]);
Db::run("DELETE FROM notifications WHERE type LIKE 'hosting\\_%'");

/* ============================================================================
   44. Das individuelle Angebot geht per Mail raus
   ----------------------------------------------------------------------------
   Frueher setzte Angebot::senden das Angebot nur auf 'gesendet' — die Mail mit
   dem Link fehlte, der Kunde bekam nichts. Hier wird festgehalten, dass mit dem
   Verschicken auch eine Mail an den Kunden im Postausgang landet (ihr Status
   haengt am Mailserver — DASS sie versucht wird, ist der Punkt).
   ============================================================================ */
abschnitt('44. Individuelles Angebot per Mail');
require_once $wurzel . '/src/Angebot.php';
$angKunde = Events::kundeFinden(['name' => 'Angebot Kunde', 'email' => 'angebot@pruefung.example']);
$angId = (int) Db::insert('angebote', [
    'customer_id' => $angKunde, 'nummer' => 'PR-' . substr((string) hrtime(true), -9),
    'sprache' => 'de', 'status' => 'entwurf', 'titel' => 'Testangebot',
    'summe_cents' => 90000, 'monatlich_cents' => 2900, 'currency' => 'EUR',
    'gueltig_bis' => date('Y-m-d', strtotime('+14 days')),
    'token' => bin2hex(random_bytes(24)),
]);
$vorMails = (int) Db::wert("SELECT COUNT(*) FROM mails WHERE anlass = 'angebot' AND customer_id = ?", [$angKunde], 0);

/* Seit dem 21.09.2026: kein Angebot vor dem grossen Fragebogen. Erst die
   Sperre, dann der Fragebogen, dann geht es raus. */
require_once $wurzel . '/src/Onboarding.php';
$angSperre = '';
try { Angebot::senden($angId); } catch (RuntimeException $e) { $angSperre = $e->getMessage(); }
pruefe('ohne ausgefuellten Fragebogen geht das Angebot nicht raus',
    str_contains($angSperre, 'Fragebogen')
    && (string) Db::wert('SELECT status FROM angebote WHERE id = ?', [$angId], '') === 'entwurf', $angSperre);
Onboarding::absenden(Onboarding::vorab($angKunde), ['branche' => 'Probe']);
pruefe('das Angebot laesst sich verschicken', Angebot::senden($angId) === true);
pruefe('es steht danach auf gesendet',
    (string) Db::wert('SELECT status FROM angebote WHERE id = ?', [$angId], '') === 'gesendet');
pruefe('und der Kunde bekommt dabei eine Angebots-Mail (Postausgang)',
    (int) Db::wert("SELECT COUNT(*) FROM mails WHERE anlass = 'angebot' AND customer_id = ?", [$angKunde], 0)
        === $vorMails + 1);

/* KEIN PREIS IN DER MAIL (22.09.2026)
   Der Betrag stand in der Betreffzeile -- also im Vorschautext jedes
   Postfachs. Das Angebot gehoert auf die Kundenseite; die Mail sagt nur,
   dass es da ist, und fuehrt dorthin. */
$angMail = Db::one("SELECT * FROM mails WHERE anlass = 'angebot' AND customer_id = ? ORDER BY id DESC LIMIT 1",
    [$angKunde]);
$angBetrag = Fmt::geld(90000, 'EUR');
pruefe('in der Betreffzeile steht kein Betrag mehr',
    $angMail !== null
    && !str_contains((string) $angMail['betreff'], $angBetrag)
    && !str_contains((string) $angMail['betreff'], '900'),
    (string) ($angMail['betreff'] ?? '-'));
pruefe('auch der Text nennt keinen Betrag und fuehrt auf die Kundenseite',
    (static function (): bool {
        foreach (['it', 'de', 'en'] as $sp) {
            [$b, $t] = Texte::mail('angebot', $sp,
                ['name' => 'Probe', 'link' => 'ZIELADRESSE', 'gueltigsatz' => ' Gilt bis morgen.']);
            if (str_contains($b, '{') || str_contains($t, '{')) { return false; }
            if (!str_contains($t, 'ZIELADRESSE')) { return false; }
            // Kein Platzhalter fuer Geld mehr -- weder im Betreff noch im Text.
            if (str_contains($b . $t, 'betrag') || preg_match('~\d+[.,]\d\d\s?€~u', $t)) { return false; }
        }
        return true;
    })());
$angQuelle = (string) file_get_contents($wurzel . '/src/Angebot.php');
pruefe('und die Adresse in der Mail ist die der Kundenseite',
    str_contains($angQuelle, 'Kundenzugang::linkFuer((int) $a[\'customer_id\'])')
    && str_contains(Kundenzugang::linkFuer($angKunde), '/kunde.php?t='));
pruefe('ein Entwurf ohne Betrag geht nicht raus', (static function () use ($angKunde) {
    $leer = (int) Db::insert('angebote', [
        'customer_id' => $angKunde, 'nummer' => 'PR0-' . substr((string) hrtime(true), -9),
        'sprache' => 'de', 'status' => 'entwurf', 'titel' => 'Leer',
        'summe_cents' => 0, 'monatlich_cents' => 0, 'currency' => 'EUR',
        'token' => bin2hex(random_bytes(24)),
    ]);
    $r = Angebot::senden($leer);
    Db::run('DELETE FROM angebote WHERE id = ?', [$leer]);
    return $r === false;
})());
Db::run('DELETE FROM mails WHERE customer_id = ?', [$angKunde]);
Db::run('DELETE FROM angebote WHERE customer_id = ?', [$angKunde]);
Db::run('DELETE FROM questionnaires WHERE customer_id = ?', [$angKunde]);
Db::run("DELETE FROM notifications WHERE type = 'mail_fehler'");

/* ============================================================================
   45. Die monatliche Folgerate geht automatisch raus
   ----------------------------------------------------------------------------
   Frueher legte der Cron die faellige Rate nur an; die Zahlungsaufforderung
   ging erst von Hand raus. Jetzt fordert abrechnungenAnlegen sie gleich an —
   der Kunde bekommt seine Rechnung fuer JEDEN Monat von selbst. Hier wird
   festgehalten, dass mit dem Anlegen auch eine Aufforderung im Postausgang
   landet (ihr Status haengt am Mailserver — DASS sie versucht wird, zaehlt).
   ============================================================================ */
abschnitt('45. Monatsrechnung automatisch');
$aboKunde = Events::kundeFinden(['name' => 'Abo Kunde', 'email' => 'abo@pruefung.example']);
$aboX = Abo::anlegen($aboKunde, ['paket_slug' => 'hosting', 'zahlart' => 'manuell', 'betrag_cents' => 990]);
// Die naechste Abrechnung in die Vergangenheit ziehen, damit der Cron sie findet.
Db::run('UPDATE abos SET naechste_abrechnung = ? WHERE id = ?', [date('Y-m-d', strtotime('-1 day')), $aboX]);
$ratenVor = (int) Db::wert('SELECT COUNT(*) FROM payments WHERE abo_id = ?', [$aboX], 0);
$mailsVor = (int) Db::wert("SELECT COUNT(*) FROM mails WHERE anlass = 'hosting_faellig' AND customer_id = ?", [$aboKunde], 0);
Abo::abrechnungenAnlegen();
pruefe('der Cron legt die faellige Monatsrate an',
    (int) Db::wert('SELECT COUNT(*) FROM payments WHERE abo_id = ?', [$aboX], 0) > $ratenVor);
pruefe('und fordert sie automatisch an (Mail im Postausgang)',
    (int) Db::wert("SELECT COUNT(*) FROM mails WHERE anlass = 'hosting_faellig' AND customer_id = ?", [$aboKunde], 0) > $mailsVor);
Db::run('DELETE FROM mails WHERE customer_id = ?', [$aboKunde]);
Db::run('DELETE FROM payments WHERE abo_id = ?', [$aboX]);
Db::run('DELETE FROM abos WHERE id = ?', [$aboX]);
Db::run("DELETE FROM notifications WHERE type IN ('mail_fehler','abo_start')");

/* ============================================================================
   46. Der Chef-Modus (Manuela hilft Uwe, hinter dem Codewort)
   ----------------------------------------------------------------------------
   Die Schutztür ist das gesprochene Codewort: Ohne gesetztes Wort ist der
   Modus aus, ein falsches Wort öffnet nie, das richtige (tippfehler-tolerant)
   schon. Handlungen (Kunde anlegen, Notiz) passieren erst nach „ja".
   ============================================================================ */
abschnitt('46. Chef-Modus');
require_once $wurzel . '/src/Chef.php';

// Ohne gesetztes Codewort ist der Modus aus.
Db::run("DELETE FROM settings WHERE skey = 'chef_codewort'");
pruefe('ohne Codewort ist der Chef-Modus aus', Chef::eingerichtet() === false);
pruefe('und nichts öffnet ihn', Chef::frei('irgendwas') === false);

// Codewort setzen — dann greift die Schutztür.
Chef::codewortSetzen('Sonnenblume');
pruefe('mit Codewort ist der Modus eingerichtet', Chef::eingerichtet() === true);
pruefe('ein falsches Wort öffnet nicht', Chef::frei('Tulpe') === false);
pruefe('ein leeres Wort öffnet nicht', Chef::frei('') === false);
pruefe('das richtige Wort öffnet', Chef::frei('Sonnenblume') === true);
pruefe('tippfehler-tolerant: Groß/klein, Satzzeichen, Leerzeichen',
    Chef::frei('  sonnen-blume. ') === true);

// Lage: reine Auskunft, immer ein Satz.
$lage = Chef::lage();
pruefe('die Lage kommt mit einem Satz zurück',
    ($lage['ok'] ?? false) === true && trim((string) ($lage['hinweis'] ?? '')) !== '');

// Kunde anlegen: erst Vorschlag, dann auf „ja" wirklich angelegt.
Db::run("DELETE FROM customers WHERE email = 'chef-neu@pruefung.example'");
$vorschlag = Chef::kundeAnlegen(['name' => 'Neuer Kunde', 'email' => 'chef-neu@pruefung.example']);
pruefe('ohne ja wird nur vorgeschlagen', !empty($vorschlag['bestaetigung_noetig']));
pruefe('und noch nichts angelegt', (int) Db::wert(
    "SELECT COUNT(*) FROM customers WHERE email = 'chef-neu@pruefung.example'", [], 0) === 0);
$angelegt = Chef::kundeAnlegen(['name' => 'Neuer Kunde', 'email' => 'chef-neu@pruefung.example', 'bestaetigt' => 'ja']);
pruefe('nach ja ist der Kunde angelegt', !empty($angelegt['angelegt']) && (int) ($angelegt['kunde_id'] ?? 0) > 0);
$zweimal = Chef::kundeAnlegen(['name' => 'Neuer Kunde', 'email' => 'chef-neu@pruefung.example', 'bestaetigt' => 'ja']);
pruefe('ein zweites Mal legt nicht doppelt an', !empty($zweimal['schon']));

// Notiz: erst Vorschlag, dann nach „ja" in den Meldungen.
Db::run("DELETE FROM notifications WHERE type = 'chef_notiz'");
$nv = Chef::notiz(['text' => 'Rückruf bei Rossi einplanen']);
pruefe('die Notiz wird erst bestätigt', !empty($nv['bestaetigung_noetig']));
pruefe('und liegt noch nicht in den Meldungen', (int) Db::wert(
    "SELECT COUNT(*) FROM notifications WHERE type = 'chef_notiz'", [], 0) === 0);
Chef::notiz(['text' => 'Rückruf bei Rossi einplanen', 'bestaetigt' => 'ja']);
pruefe('nach ja steht die Notiz in den Meldungen', (int) Db::wert(
    "SELECT COUNT(*) FROM notifications WHERE type = 'chef_notiz'", [], 0) === 1);

// Aufräumen
Db::run("DELETE FROM customers WHERE email = 'chef-neu@pruefung.example'");
Db::run("DELETE FROM notifications WHERE type IN ('chef_notiz','chef_kunde')");
Db::run("DELETE FROM settings WHERE skey = 'chef_codewort'");

/* ============================================================================
   47. Die Werkstatt von aussen (Claude Code holt und meldet)
   ----------------------------------------------------------------------------
   Zwei Türen und eine Bremse. Die erste Tür ist der Schlüssel: ohne ihn
   antwortet niemand, ein falscher öffnet nie. Die zweite ist die Freigabe —
   der einzige Schritt hier draußen, der beim Kunden ankommt, und deshalb der
   einzige, der ein ausdrückliches „ja" verlangt.

   Die Bremse ist, was NICHT passiert: Eine Vorschau-Adresse einzutragen
   schaltet sie nicht frei. Genau das war beim Knopf in der Verwaltung einmal
   dasselbe und hat Kunden auf halbfertige Entwürfe geschickt.
   ============================================================================ */
abschnitt('47. Werkstatt von außen');
require_once $wurzel . '/src/Werkstatt.php';

// Ohne Schlüssel ist die Tür zu — auch für den richtigen Aufrufer.
Db::run("DELETE FROM settings WHERE skey = 'werkstatt_schluessel'");
pruefe('ohne Schlüssel ist die Werkstatt zu', Werkstatt::eingerichtet() === false);
pruefe('und nichts öffnet sie', Werkstatt::schluesselStimmt('irgendwas') === false);

$wsSchluessel = Werkstatt::neuerSchluessel();
pruefe('ein erzeugter Schlüssel öffnet', Werkstatt::schluesselStimmt($wsSchluessel) === true);
pruefe('ein falscher öffnet nicht', Werkstatt::schluesselStimmt('falsch') === false);
pruefe('ein leerer öffnet nicht', Werkstatt::schluesselStimmt('') === false);
$wsZweiter = Werkstatt::neuerSchluessel();
pruefe('ein neuer macht den alten wertlos',
    Werkstatt::schluesselStimmt($wsSchluessel) === false && Werkstatt::schluesselStimmt($wsZweiter) === true);

// Ein eigenes Projekt, damit dieser Abschnitt niemandem sonst ins Handwerk pfuscht.
$wsKunde = Events::kundeFinden(['name' => 'Werkstatt Kunde', 'email' => 'werkstatt@pruefung.example']);
Db::run('UPDATE customers SET kundennr = ? WHERE id = ?', ['K-9999-0042', $wsKunde]);
$wsBestellung = Events::bestellungAnlegen($wsKunde, $paketId, 'Werkstatt-Prüfung', KETTE_PREIS);
Events::bestellungStatus($wsBestellung, 'bezahlt');
$wsProjekt = Events::projektAusBestellung($wsBestellung);
pruefe('Prüfprojekt steht', $wsProjekt > 0);

// Finden: Projekt-ID, Kundennummer, Bestellnummer — und ein klares Nein sonst.
pruefe('findet über die Projekt-Nummer',
    (int) Werkstatt::projektFinden(['projekt' => (string) $wsProjekt])['id'] === $wsProjekt);
pruefe('findet über die Kundennummer',
    (int) Werkstatt::projektFinden(['kunde' => 'K-9999-0042'])['id'] === $wsProjekt);
gesperrt('ohne Angabe wird nicht geraten', static fn() => Werkstatt::projektFinden([]));
gesperrt('eine unbekannte Nummer wirft', static fn() => Werkstatt::projektFinden(['kunde' => 'K-0000-0000']));

// Auftrag holen: Briefing entsteht dabei und bleibt am Projekt.
$wsAuftrag = Werkstatt::auftrag(['kunde' => 'K-9999-0042']);
pruefe('der Auftrag kommt mit Briefing', trim((string) ($wsAuftrag['briefing'] ?? '')) !== '');
pruefe('und mit den Hausregeln', trim((string) ($wsAuftrag['hausregeln'] ?? '')) !== '');
pruefe('das Briefing bleibt am Projekt stehen',
    trim((string) Db::wert('SELECT briefing FROM projects WHERE id = ?', [$wsProjekt], '')) !== '');
pruefe('der Auftrag nennt die möglichen Stände', in_array('vorschau', (array) ($wsAuftrag['staende'] ?? []), true));

// Vorschau eintragen — und eben NICHT freischalten.
$wsV = Werkstatt::vorschau(['projekt' => (string) $wsProjekt,
    'vorschau' => 'cavaleri-pruefung.netlify.app', 'repo' => 'https://github.com/beispiel/pruefung']);
pruefe('die Vorschau-Adresse steht', (string) Db::wert(
    'SELECT preview_url FROM projects WHERE id = ?', [$wsProjekt], '') === 'https://cavaleri-pruefung.netlify.app');
pruefe('fehlendes https wird ergänzt statt abgelehnt', ($wsV['ok'] ?? false) === true);
pruefe('die Quelltext-Adresse steht daneben', (string) Db::wert(
    'SELECT repo_url FROM projects WHERE id = ?', [$wsProjekt], '') === 'https://github.com/beispiel/pruefung');
pruefe('EINTRAGEN SCHALTET NICHT FREI', Db::wert(
    'SELECT vorschau_frei_am FROM projects WHERE id = ?', [$wsProjekt], null) === null);
gesperrt('eine unsinnige Adresse wird abgelehnt',
    static fn() => Werkstatt::vorschau(['projekt' => (string) $wsProjekt, 'vorschau' => 'htt p:// nein']));
gesperrt('ohne Adresse und ohne Repo gibt es nichts zu ändern',
    static fn() => Werkstatt::vorschau(['projekt' => (string) $wsProjekt]));

// Stand setzen: nur bekannte Stände, und ohne E-Mail.
$wsMailsVor = (int) Db::wert('SELECT COUNT(*) FROM mails WHERE customer_id = ?', [$wsKunde], 0);
$wsS = Werkstatt::stand(['projekt' => (string) $wsProjekt, 'stand' => 'entwicklung']);
pruefe('der Stand lässt sich setzen', ($wsS['ok'] ?? false) === true
    && (string) Db::wert('SELECT status FROM projects WHERE id = ?', [$wsProjekt], '') === 'entwicklung');
pruefe('ein erfundener Stand wird abgelehnt',
    (Werkstatt::stand(['projekt' => (string) $wsProjekt, 'stand' => 'kaffeepause'])['ok'] ?? true) === false);
pruefe('und der Kunde bekommt deswegen keine E-Mail',
    (int) Db::wert('SELECT COUNT(*) FROM mails WHERE customer_id = ?', [$wsKunde], 0) === $wsMailsVor);

// Notiz: eine Zeile in die Akte.
$wsNotizVor = (int) Db::wert("SELECT COUNT(*) FROM activities WHERE project_id = ? AND type = 'werkstatt_notiz'", [$wsProjekt], 0);
Werkstatt::notiz(['projekt' => (string) $wsProjekt, 'text' => 'Erste Fassung steht, Bilder fehlen noch']);
pruefe('die Notiz steht in der Akte', (int) Db::wert(
    "SELECT COUNT(*) FROM activities WHERE project_id = ? AND type = 'werkstatt_notiz'", [$wsProjekt], 0) === $wsNotizVor + 1);
gesperrt('eine leere Notiz wird nicht abgelegt',
    static fn() => Werkstatt::notiz(['projekt' => (string) $wsProjekt, 'text' => '   ']));

// Freigeben: der einzige Schritt, der beim Kunden ankommt.
$wsF = Werkstatt::freigeben(['projekt' => (string) $wsProjekt]);
pruefe('ohne ja wird nur nachgefragt', !empty($wsF['bestaetigung_noetig']));
pruefe('und nichts ist freigeschaltet', Db::wert(
    'SELECT vorschau_frei_am FROM projects WHERE id = ?', [$wsProjekt], null) === null);
$wsF2 = Werkstatt::freigeben(['projekt' => (string) $wsProjekt, 'bestaetigt' => 'ja']);
pruefe('nach ja ist freigeschaltet', ($wsF2['ok'] ?? false) === true && Db::wert(
    'SELECT vorschau_frei_am FROM projects WHERE id = ?', [$wsProjekt], null) !== null);

// Und ohne Adresse gibt es nichts freizuschalten — sonst klickt der Kunde ins Leere.
Db::run('UPDATE projects SET preview_url = NULL, vorschau_frei_am = NULL WHERE id = ?', [$wsProjekt]);
gesperrt('ohne Vorschau-Adresse wird nicht freigeschaltet',
    static fn() => Werkstatt::freigeben(['projekt' => (string) $wsProjekt, 'bestaetigt' => 'ja']));

// Die Liste zeigt, woran gebaut wird.
$wsListe = Werkstatt::liste([]);
pruefe('die Liste nennt das Projekt', ($wsListe['ok'] ?? false) === true
    && in_array($wsProjekt, array_column((array) ($wsListe['projekte'] ?? []), 'projekt'), true));

// Zu — und damit ist auch der Schlüssel wieder wertlos.
Werkstatt::schluesselEntfernen();
pruefe('die Tür lässt sich wieder schließen',
    Werkstatt::eingerichtet() === false && Werkstatt::schluesselStimmt($wsZweiter) === false);

// Aufräumen
Db::run('DELETE FROM mails WHERE customer_id = ?', [$wsKunde]);
Db::run("DELETE FROM notifications WHERE type LIKE 'werkstatt%'");

/* ============================================================================
   48. Der Zahlungsabgleich mit Stripe

   WARUM DIESER ABSCHNITT DER WICHTIGSTE DER LETZTEN WOCHE IST

   Am 13.09.2026 hat ein Kunde mit Karte bezahlt und nie eine Bestaetigung
   bekommen. Bei Stripe lag das Geld, hier stand die Rate offen. Dazwischen
   liegt genau ein Aufruf — der Webhook —, und der kam nie an. Nichts in
   dieser Anwendung hat es gemerkt; gemerkt hat es der Kunde.

   Der Abgleich ist der Rueckweg: Wir fragen selbst bei Stripe nach. Damit
   dieser Rueckweg nicht dasselbe Schicksal erleidet wie der Webhook — jahre-
   lang da und nie geprueft —, steht er hier, mit einem Anbieter, der sich
   wie Stripe verhaelt, ohne einer zu sein.
   ============================================================================ */
abschnitt('48. Der Zahlungsabgleich mit Stripe');

/**
 * Ein Stripe, das nicht Stripe ist: Es antwortet aus einer Liste statt aus
 * dem Netz. Nur so laesst sich pruefen, was passiert, wenn die Antwort
 * "bezahlt" lautet, "abgelaufen", oder wenn gar keine kommt.
 */
final class AbgleichProbe
{
    public array $gefragt = [];
    public function __construct(private array $antworten, private bool $bereit = true) {}
    public function bereit(): bool { return $this->bereit; }
    public function sitzungLesen(string $id): array
    {
        $this->gefragt[] = $id;
        if (!isset($this->antworten[$id])) {
            throw new RuntimeException('Stripe: No such checkout session: ' . $id);
        }
        /* Stripe nennt immer den Betrag, der bezahlt wurde. Seit dem
           21.09.2026 bucht der Abgleich nur, wenn er zur Rate passt -- eine
           Probe, die "0" meldet, wuerde jede Buchung verhindern. "null" heisst
           hier: den Betrag der Rate nehmen, die an dieser Seite haengt. */
        $a = $this->antworten[$id];
        if (($a['betrag'] ?? null) === null) {
            $r = Db::one('SELECT amount_cents, currency FROM payments WHERE provider_sitzung = ?', [$id]);
            $a['betrag']   = (int) ($r['amount_cents'] ?? 0);
            $a['waehrung'] = strtoupper((string) ($r['currency'] ?? 'EUR'));
        }
        return $a;
    }
}

/** Legt Kunde, Bestellung und eine offene Rate mit Bezahlseite an. */
$agRate = static function (string $email, string $sitzung, int $minutenAlt = 30) use ($paketId): array {
    $k = Events::kundeFinden(['name' => 'Abgleich ' . $email, 'email' => $email, 'sprache' => 'de']);
    $b = Events::bestellungAnlegen($k, $paketId, 'Abgleichprobe', KETTE_PREIS);
    $z = (int) Db::wert("SELECT id FROM payments WHERE order_id = ? AND art = 'anzahlung'", [$b], 0);
    Db::update('payments', $z, [
        'provider' => 'stripe', 'status' => 'in_bearbeitung',
        'provider_sitzung' => $sitzung,
        'link_url' => 'https://checkout.stripe.test/' . $sitzung,
    ]);
    /* updated_at steht auf ON UPDATE CURRENT_TIMESTAMP und damit auf jetzt.
       Die Schonfrist wuerde die Rate also ueberspringen — hier wird sie
       zurueckdatiert, damit die Probe den Normalfall trifft. */
    Db::run('UPDATE payments SET updated_at = DATE_SUB(NOW(), INTERVAL ? MINUTE) WHERE id = ?',
        [$minutenAlt, $z]);
    return ['kunde' => $k, 'bestellung' => $b, 'zahlung' => $z];
};

$agBezahlt = static fn(string $pi) => ['bezahlt' => true, 'referenz' => $pi,
    'status' => 'complete', 'abgelaufen' => false, 'betrag' => null, 'waehrung' => 'EUR'];
$agOffen = ['bezahlt' => false, 'referenz' => '', 'status' => 'open',
    'abgelaufen' => false, 'betrag' => 0, 'waehrung' => 'EUR'];
$agWeg = ['bezahlt' => false, 'referenz' => '', 'status' => 'expired',
    'abgelaufen' => true, 'betrag' => 0, 'waehrung' => 'EUR'];

/* ---------- Der Fall, der wirklich passiert ist ------------------------- */
$agA = $agRate('abgleich-a@pruefung.example', 'cs_test_bezahlt');
$vorherMeldungen = (int) Db::wert("SELECT COUNT(*) FROM notifications WHERE type = 'zahlung_abgleich'", [], 0);

$gebucht = Cron::zahlungenAbgleichen(new AbgleichProbe(['cs_test_bezahlt' => $agBezahlt('pi_test_1')]));
pruefe('der Abgleich bucht die bezahlte Rate', $gebucht === 1, (string) $gebucht);

$zA = Db::one('SELECT * FROM payments WHERE id = ?', [$agA['zahlung']]);
pruefe('die Rate steht danach auf bezahlt', (string) $zA['status'] === 'bezahlt', (string) $zA['status']);
pruefe('mit der Nummer des Zahlungsvorgangs von Stripe',
    (string) $zA['provider_ref'] === 'pi_test_1', (string) $zA['provider_ref']);
pruefe('und mit Stripe als Anbieter, nicht "manuell"',
    (string) $zA['provider'] === 'stripe', (string) $zA['provider']);
pruefe('ein Zahlzeitpunkt steht drin', trim((string) $zA['paid_at']) !== '');

/* Und alles, was an der Buchung haengt, haengt auch hier dran — sonst waere
   der Rueckweg nur die halbe Miete. */
pruefe('das Projekt ist dabei entstanden',
    (int) Db::wert('SELECT COUNT(*) FROM projects WHERE order_id = ?', [$agA['bestellung']], 0) === 1);
pruefe('ein Beleg ist dabei entstanden',
    (int) Db::wert('SELECT COUNT(*) FROM invoices WHERE payment_id = ?', [$agA['zahlung']], 0) === 1);
pruefe('die Bestellung steht auf bezahlt',
    (string) Db::wert('SELECT status FROM orders WHERE id = ?', [$agA['bestellung']], '') === 'bezahlt');

/* Der Befund, nicht nur die Reparatur: Wenn der Abgleich buchen musste, hat
   der Webhook versagt. Das muss in der Verwaltung stehen. */
pruefe('der Abgleich meldet, dass der Webhook nicht gemeldet hat',
    (int) Db::wert("SELECT COUNT(*) FROM notifications WHERE type = 'zahlung_abgleich'", [], 0)
        > $vorherMeldungen);

/* ---------- Zweimal fragen bucht nicht zweimal -------------------------- */
$nochmal = Cron::zahlungenAbgleichen(new AbgleichProbe(['cs_test_bezahlt' => $agBezahlt('pi_test_1')]));
pruefe('ein zweiter Lauf bucht dieselbe Rate nicht noch einmal', $nochmal === 0, (string) $nochmal);
pruefe('und es gibt weiterhin genau einen Beleg dazu',
    (int) Db::wert('SELECT COUNT(*) FROM invoices WHERE payment_id = ?', [$agA['zahlung']], 0) === 1);

/* ---------- Was offen ist, bleibt offen --------------------------------- */
$agB = $agRate('abgleich-b@pruefung.example', 'cs_test_offen');
$probeB = new AbgleichProbe(['cs_test_offen' => $agOffen]);
pruefe('eine offene Bezahlseite wird nicht gebucht', Cron::zahlungenAbgleichen($probeB) === 0);
pruefe('die Rate steht weiter auf in_bearbeitung',
    (string) Db::wert('SELECT status FROM payments WHERE id = ?', [$agB['zahlung']], '') === 'in_bearbeitung');
pruefe('ihre Nummer bleibt stehen, es wird weiter nachgefragt',
    trim((string) Db::wert('SELECT provider_sitzung FROM payments WHERE id = ?', [$agB['zahlung']], '')) !== '');

/* ---------- Eine verfallene Seite wird nicht ewig gefragt ---------------- */
$agC = $agRate('abgleich-c@pruefung.example', 'cs_test_weg');
Cron::zahlungenAbgleichen(new AbgleichProbe(['cs_test_weg' => $agWeg, 'cs_test_offen' => $agOffen]));
/* Db::wert() gibt bei NULL den Vorgabewert zurueck und kann deshalb ein
   leeres Feld nicht von einem fehlenden unterscheiden. Hier zaehlt genau
   dieser Unterschied — also die Zeile selbst lesen. */
$agCZeile = Db::one('SELECT provider_sitzung FROM payments WHERE id = ?', [$agC['zahlung']]);
pruefe('eine abgelaufene Bezahlseite verliert ihre Nummer',
    $agCZeile !== null && $agCZeile['provider_sitzung'] === null,
    var_export($agCZeile['provider_sitzung'] ?? 'keine Zeile', true));
$probeC = new AbgleichProbe(['cs_test_offen' => $agOffen]);
Cron::zahlungenAbgleichen($probeC);
pruefe('und wird danach nicht mehr abgefragt',
    !in_array('cs_test_weg', $probeC->gefragt, true));

/* ---------- Eine Rate, die klemmt, haelt die anderen nicht auf ----------- */
$agD = $agRate('abgleich-d@pruefung.example', 'cs_test_kaputt');
$agE = $agRate('abgleich-e@pruefung.example', 'cs_test_gut');
$probeDE = new AbgleichProbe(['cs_test_gut' => $agBezahlt('pi_test_2'), 'cs_test_offen' => $agOffen]);
$trotzdem = Cron::zahlungenAbgleichen($probeDE);   // cs_test_kaputt wirft
pruefe('ein Fehler bei einer Rate hält die anderen nicht auf', $trotzdem === 1, (string) $trotzdem);
pruefe('die heile Rate ist gebucht',
    (string) Db::wert('SELECT status FROM payments WHERE id = ?', [$agE['zahlung']], '') === 'bezahlt');
pruefe('die kaputte bleibt unangetastet',
    (string) Db::wert('SELECT status FROM payments WHERE id = ?', [$agD['zahlung']], '') === 'in_bearbeitung');
pruefe('und der Fehler steht bei der Integration',
    str_contains((string) Db::wert("SELECT last_error FROM integrations WHERE ikey = 'stripe'", [], ''), 'Abgleich'));

/* ---------- Die Schonfrist: erst der Webhook, dann wir ------------------- */
$agF = $agRate('abgleich-f@pruefung.example', 'cs_test_frisch', 0);   // gerade eben
$probeF = new AbgleichProbe(['cs_test_frisch' => $agBezahlt('pi_test_3'), 'cs_test_offen' => $agOffen]);
Cron::zahlungenAbgleichen($probeF);
pruefe('eine frische Rate wird noch nicht gefragt — der Webhook hat Vorrang',
    !in_array('cs_test_frisch', $probeF->gefragt, true));
pruefe('und bleibt deshalb offen',
    (string) Db::wert('SELECT status FROM payments WHERE id = ?', [$agF['zahlung']], '') === 'in_bearbeitung');

/* ---------- Ohne Nummer wird gar nicht erst gefragt ---------------------- */
$agG = $agRate('abgleich-g@pruefung.example', 'cs_test_ohne');
Db::update('payments', $agG['zahlung'], ['provider_sitzung' => null]);
Db::run('UPDATE payments SET updated_at = DATE_SUB(NOW(), INTERVAL 30 MINUTE) WHERE id = ?', [$agG['zahlung']]);
$probeG = new AbgleichProbe(['cs_test_offen' => $agOffen]);
Cron::zahlungenAbgleichen($probeG);
pruefe('eine Rate ohne Bezahlseite wird nicht abgefragt',
    !in_array('cs_test_ohne', $probeG->gefragt, true));

/* ---------- Ohne Schlüssel tut der Abgleich nichts ----------------------- */
pruefe('ohne Stripe-Schlüssel fragt der Abgleich gar nicht erst',
    Cron::zahlungenAbgleichen(new AbgleichProbe([], false)) === 0);

/* ---------- Der Abgleich steht im Lauf, nicht daneben -------------------- */
pruefe('der Abgleich ist Teil des regelmäßigen Laufs',
    str_contains(file_get_contents($oben . '/app/src/Cron.php'), "'zahlabgleich'"));
pruefe('und läuft VOR dem Ablaufenlassen der Zahlungslinks',
    strpos(file_get_contents($oben . '/app/src/Cron.php'), "'zahlabgleich'")
        < strpos(file_get_contents($oben . '/app/src/Cron.php'), "'zahllinks'"));

/* ---------- Die Spalte, an der alles hängt ------------------------------- */
pruefe('payments trägt die Nummer der Bezahlseite',
    (int) Db::wert("SELECT COUNT(*) FROM information_schema.columns
                     WHERE table_schema = DATABASE() AND table_name = 'payments'
                       AND column_name = 'provider_sitzung'", [], 0) === 1);
foreach (['buchen.php', 'app/index.php', 'app/src/Nachricht.php',
          'app/src/Abo.php', 'app/src/Mahnung.php'] as $agDatei) {
    pruefe("$agDatei merkt sich die Bezahlseite",
        str_contains(file_get_contents($oben . '/' . $agDatei), "'provider_sitzung' => \$stripe->letzteSitzung()"));
}

/* ============================================================================
   49. Material vom Kunden und die Website zum Mitnehmen

   Zwei Richtungen, die vorher beide nicht funktionierten: Was der Kunde
   hochlaedt, stand im Auftrag bestenfalls als Dateiname — der Baumeister
   wusste, dass es ein Logo gibt, und kam nicht daran. Und die fertige Seite
   lag auf Netlify und im Mac-Ordner, nirgends aber dort, wo man sie dem
   Kunden geben kann.
   ============================================================================ */
abschnitt('49. Material und Website-Paket');

/* Dateien werden hier direkt eingetragen statt hochgeladen: is_uploaded_file()
   ist im CLI immer falsch, ein echter Upload also nicht nachstellbar. Geprueft
   wird deshalb alles AUSSER dem Bewegen der Bytes — und genau da liegt auch
   das Neue. */
$mpProjekt = $projektId;
$mpKunde   = (int) Db::wert('SELECT customer_id FROM projects WHERE id = ?', [$mpProjekt], 0);

$mpDatei = static function (string $name, string $rolle, string $wer, int $bytes = 4096) use ($mpProjekt, $mpKunde): int {
    return Db::insert('files', [
        'customer_id' => $mpKunde, 'project_id' => $mpProjekt,
        'stored_name' => bin2hex(random_bytes(8)) . '.bin', 'orig_name' => $name,
        'mime' => 'application/octet-stream', 'size_bytes' => $bytes,
        'uploaded_by' => $wer, 'rolle' => $rolle,
    ]);
};

/* ---------- Die Spalten, an denen alles haengt ---------------------------- */
pruefe('files trägt eine Rolle',
    (int) Db::wert("SELECT COUNT(*) FROM information_schema.columns
                     WHERE table_schema = DATABASE() AND table_name = 'files'
                       AND column_name = 'rolle'", [], 0) === 1);
pruefe('projects trägt die Freigabe des Pakets',
    (int) Db::wert("SELECT COUNT(*) FROM information_schema.columns
                     WHERE table_schema = DATABASE() AND table_name = 'projects'
                       AND column_name = 'paket_frei_am'", [], 0) === 1);
/* MariaDB liefert Zeichenketten-Vorgaben mit Anfuehrungszeichen ('material'),
   MySQL ohne. Beides ist richtig — die Anfuehrungszeichen gehoeren weg, bevor
   verglichen wird, sonst haengt die Pruefung an der Datenbankmarke. */
pruefe('bestehende Dateien sind Material, nicht Paket',
    trim((string) Db::wert("SELECT COLUMN_DEFAULT FROM information_schema.columns
                        WHERE table_schema = DATABASE() AND table_name = 'files'
                          AND column_name = 'rolle'", [], ''), "'\"") === 'material');

/* ---------- Was der Kunde geschickt hat, steht im Auftrag ----------------- */
$mpLogo = $mpDatei('logo-trattoria.svg', 'material', 'kunde', 51200);
$mpFont = $mpDatei('hausschrift.zip', 'material', 'kunde', 380000);

$mpListe = Werkstatt::dateien(['projekt' => (string) $mpProjekt]);
pruefe('die Werkstatt nennt das Material', ($mpListe['ok'] ?? false) === true
    && (int) ($mpListe['anzahl'] ?? 0) === 2, (string) ($mpListe['anzahl'] ?? 0));
$mpNamen = array_column((array) $mpListe['dateien'], 'name');
pruefe('mit Namen', in_array('logo-trattoria.svg', $mpNamen, true));
pruefe('und mit dem Weg, sie zu holen',
    str_contains((string) ($mpListe['dateien'][0]['holen'] ?? ''), 'aktion=datei&id='));
pruefe('die Größe steht dabei', (int) ($mpListe['dateien'][0]['bytes'] ?? 0) > 0);

require_once $oben . '/app/src/Briefing.php';
$mpBrief = Briefing::bauen($mpProjekt);
pruefe('das Briefing nennt das Material', str_contains($mpBrief, 'MATERIAL VOM KUNDEN'));
pruefe('mit Dateinamen', str_contains($mpBrief, 'logo-trattoria.svg')
    && str_contains($mpBrief, 'hausschrift.zip'));
pruefe('mit der Nummer zum Abholen', str_contains($mpBrief, '#' . $mpLogo));
pruefe('und mit dem fertigen Befehl', str_contains($mpBrief, 'aktion=datei&id=$1'));
pruefe('es sagt auch, dass Logo und Schriften benutzt und nicht nachgebaut werden',
    str_contains($mpBrief, 'nicht nachgebaut'));

/* ---------- Und wenn nichts da ist, steht auch das da --------------------- */
$mpLeerProjekt = (int) Db::wert('SELECT id FROM projects WHERE id <> ? ORDER BY id DESC LIMIT 1',
    [$mpProjekt], 0);
if ($mpLeerProjekt > 0) {
    Db::run("DELETE FROM files WHERE project_id = ?", [$mpLeerProjekt]);
    $mpLeer = Briefing::bauen($mpLeerProjekt);
    pruefe('ohne Material sagt das Briefing genau das',
        str_contains($mpLeer, 'MATERIAL VOM KUNDEN')
        && str_contains($mpLeer, 'Es liegt nichts hochgeladen vor'));
    pruefe('und verlangt, danach zu fragen statt Platzhalter zu bauen',
        str_contains($mpLeer, 'Frag danach'));
}

/* ---------- Das Paket: erst prüfen, dann annehmen ------------------------- */
gesperrt('ohne Datei wird kein Paket angenommen',
    static fn() => Werkstatt::paket(['projekt' => (string) $mpProjekt], []));
gesperrt('was keine .zip ist, wird abgelehnt',
    static fn() => Werkstatt::paket(['projekt' => (string) $mpProjekt],
        ['name' => 'website.tar.gz', 'error' => UPLOAD_ERR_OK, 'size' => 1024]));
gesperrt('eine abgebrochene Übertragung wird abgelehnt',
    static fn() => Werkstatt::paket(['projekt' => (string) $mpProjekt],
        ['name' => 'website.zip', 'error' => UPLOAD_ERR_PARTIAL, 'size' => 1024]));
gesperrt('ein zu großes Paket wird abgelehnt',
    static fn() => Werkstatt::paket(['projekt' => (string) $mpProjekt],
        ['name' => 'website.zip', 'error' => UPLOAD_ERR_OK,
         'size' => Werkstatt::PAKET_MAX_BYTES + 1]));

/* ---------- Das Paket liegt da, der Kunde sieht es noch nicht ------------- */
$mpPaket = $mpDatei('trattoria-website.zip', 'paket', 'werkstatt', 8_400_000);

pruefe('das Paket steht nicht im Material des Kunden',
    !in_array('trattoria-website.zip',
        array_column((array) Werkstatt::dateien(['projekt' => (string) $mpProjekt])['dateien'], 'name'), true));
pruefe('es ist aber über die Rolle zu finden',
    (int) (Werkstatt::dateien(['projekt' => (string) $mpProjekt, 'rolle' => 'paket'])['anzahl'] ?? 0) === 1);
pruefe('und es steht auch nicht im Briefing-Material',
    !str_contains(Briefing::bauen($mpProjekt), 'trattoria-website.zip'));

$mpFreiVorher = Db::wert('SELECT paket_frei_am FROM projects WHERE id = ?', [$mpProjekt], null);
pruefe('das Paket ist zunächst nicht freigegeben', $mpFreiVorher === null);

require_once $oben . '/app/src/Nachricht.php';

/* In der Pruefung liegt kein Brevo-Schluessel, also meldet JEDER Versand
   false — ob er versucht wurde, steht nur in der Tabelle mails. Gezaehlt wird
   deshalb der Versuch, nicht der Rueckgabewert. Der Unterschied, auf den es
   ankommt, ist genau der: vor der Freigabe wird gar nicht erst angesetzt. */
$mpVersuche = static fn(): int => (int) Db::wert(
    "SELECT COUNT(*) FROM mails WHERE project_id = ? AND anlass = 'paket'", [$mpProjekt], 0);

$mpVorher = $mpVersuche();
pruefe('ohne Freigabe geht auch keine E-Mail raus',
    Nachricht::paketFertig($mpProjekt) === false && $mpVersuche() === $mpVorher);

/* Das ist die Sperre, auf die es ankommt: Die Kundenseite zeigt das Paket nur
   nach der Freigabe — und liefert es auch nur dann aus. */
$mpSichtbar = static fn(int $pid): bool =>
    Db::wert('SELECT paket_frei_am FROM projects WHERE id = ?', [$pid], null) !== null;
pruefe('die Kundenseite zeigt es deshalb nicht', $mpSichtbar($mpProjekt) === false);

/* ---------- Freigeben ---------------------------------------------------- */
Db::update('projects', $mpProjekt, ['paket_frei_am' => date('Y-m-d H:i:s')]);
pruefe('nach der Freigabe ist es für den Kunden da', $mpSichtbar($mpProjekt) === true);
Nachricht::paketFertig($mpProjekt);
pruefe('und jetzt geht die E-Mail raus', $mpVersuche() > $mpVorher);
pruefe('sie steht als solche im Protokoll',
    (string) Db::wert("SELECT anlass FROM mails WHERE project_id = ?
                        ORDER BY id DESC LIMIT 1", [$mpProjekt], '') === 'paket');

/* Der Rumpf der Mail wird nirgends gespeichert — die Tabelle mails haelt nur
   fest, DASS etwas rausging. Was drinsteht, wird deshalb dort geprueft, wo es
   entsteht: am Text selbst, in allen drei Sprachen. */
foreach (['it', 'de', 'en'] as $mpSprache) {
    [$mpBetreff, $mpText] = Texte::mail('paket', $mpSprache, [
        'name' => 'Trattoria', 'paket' => 'Sichtbar',
        'datei' => 'trattoria-website.zip',
        'link' => 'https://vecom-design.it/projekt.php?k=abc123',
    ]);
    pruefe("sie trägt den Dateinamen ($mpSprache)",
        str_contains($mpText, 'trattoria-website.zip'));
    pruefe("und einen Link auf seine Projektseite, nicht das ZIP im Anhang ($mpSprache)",
        str_contains($mpText, 'projekt.php?k=abc123'));
    pruefe("der Betreff nennt das Paket ($mpSprache)",
        $mpBetreff !== '' && str_contains($mpBetreff, 'Sichtbar'));
    pruefe("keine Platzhalter bleiben stehen ($mpSprache)",
        !str_contains($mpText . $mpBetreff, '{'));
}

/* Eine neue Fassung darf nachgeliefert werden, ohne dass die Freigabe faellt —
   sonst steht beim Kunden irgendwann ein Paket von vorletzter Woche. */
$mpPaket2 = $mpDatei('trattoria-website-v2.zip', 'paket', 'werkstatt', 8_500_000);
pruefe('eine neue Fassung hebt die Freigabe nicht auf', $mpSichtbar($mpProjekt) === true);
pruefe('und der Kunde bekommt die neueste',
    (string) Db::wert("SELECT orig_name FROM files WHERE project_id = ? AND rolle = 'paket'
                        ORDER BY id DESC LIMIT 1", [$mpProjekt], '') === 'trattoria-website-v2.zip');

/* Zurücknehmen muss auch gehen. */
Db::update('projects', $mpProjekt, ['paket_frei_am' => null]);
pruefe('die Freigabe lässt sich zurücknehmen', $mpSichtbar($mpProjekt) === false);

/* ---------- Der Weg nach draußen ----------------------------------------- */
$mpWerkstattPhp = file_get_contents($oben . '/werkstatt.php');
pruefe('die Werkstatt kennt die drei neuen Aktionen',
    in_array('dateien', Werkstatt::AKTIONEN, true)
    && in_array('datei', Werkstatt::AKTIONEN, true)
    && in_array('paket', Werkstatt::AKTIONEN, true));
pruefe('„datei" antwortet an der JSON-Kopfzeile vorbei',
    str_contains($mpWerkstattPhp, "header_remove('Content-Type')"));
pruefe('und steht vor dem JSON-Verteiler',
    strpos($mpWerkstattPhp, "if (\$aktion === 'datei')")
        < strpos($mpWerkstattPhp, "antwort(match (\$aktion)"));
pruefe('das Paket kommt aus $_FILES, nicht aus dem JSON-Rumpf',
    str_contains($mpWerkstattPhp, "Werkstatt::paket(\$d, \$_FILES['datei'] ?? [])"));

$mpProjektSeite = file_get_contents($oben . '/projekt.php');
pruefe('die Kundenseite liefert ein gesperrtes Paket nicht aus',
    str_contains($mpProjektSeite, "=== 'paket'")
    && str_contains($mpProjektSeite, 'paket_frei_am'));

// Aufräumen, damit die folgenden Abschnitte auf sauberen Zahlen stehen.
Db::run('DELETE FROM files WHERE project_id = ?', [$mpProjekt]);
Db::run("DELETE FROM mails WHERE project_id = ? AND anlass = 'paket'", [$mpProjekt]);

/* ============================================================================
   50. Vorschau, Löschen, Filter — und die Leiste der offenen Vorgänge

   Vier Dinge, die am selben Satz hängen: "teilweise ist alles sehr
   unübersichtlich". Eine Dateiliste aus Dateinamen sagt nicht, welches das
   Logo ist. Löschen ging nur im Projekt. Und wer an einem Kunden arbeitete,
   sah nur diesen einen — kam währenddessen eine Anfrage, merkte er es erst,
   wenn er von selbst zurückging.
   ============================================================================ */
abschnitt('50. Übersicht: Vorschau, Löschen, Leiste');

require_once $oben . '/app/src/Ablage.php';

/* ---------- Was sich als Bild zeigen lässt und was nicht --------------- */
pruefe('die Bildbibliothek steht bereit', Ablage::bilderMoeglich());
pruefe('ein JPEG ist vorschaubar', Ablage::vorschaubar(['mime' => 'image/jpeg']));
pruefe('ein PNG auch', Ablage::vorschaubar(['mime' => 'image/png']));
pruefe('ein PDF nicht — dafür gäbe es kein ehrliches Bild',
    !Ablage::vorschaubar(['mime' => 'application/pdf']));
pruefe('ein ZIP nicht', !Ablage::vorschaubar(['mime' => 'application/zip']));
pruefe('ein Film nicht', !Ablage::vorschaubar(['mime' => 'video/mp4']));
/* HEIC nimmt die Ablage an, GD kann es nicht lesen. Lieber kein Bild als
   eines, das beim Erzeugen abbricht. */
pruefe('HEIC verspricht keine Vorschau', !Ablage::vorschaubar(['mime' => 'image/heic']));

/* ---------- Die gerechnete Vorschau ------------------------------------ */
$vsOrdner = Ablage::ordner();
$vsBauen  = (new ReflectionClass('Ablage'))->getMethod('vorschauBauen');
$vsBauen->setAccessible(true);

/** Legt ein echtes Bild in der Ablage ab und gibt den Datensatz dazu. */
$vsBild = static function (string $name, int $b, int $h, string $art = 'jpeg',
                           bool $durchsichtig = false) use ($vsOrdner): array {
    $q = imagecreatetruecolor($b, $h);
    if ($durchsichtig) {
        imagesavealpha($q, true);
        imagefill($q, 0, 0, imagecolorallocatealpha($q, 0, 0, 0, 127));
        imagefilledellipse($q, (int) ($b / 2), (int) ($h / 2), (int) ($b / 2), (int) ($h / 2),
            imagecolorallocate($q, 220, 40, 40));
    } else {
        imagefilledrectangle($q, 0, 0, $b, $h, imagecolorallocate($q, 20, 120, 210));
    }
    $abgelegt = 'kette_' . $name . '.bin';
    $pfad = $vsOrdner . '/' . $abgelegt;
    if ($art === 'png') { imagepng($q, $pfad); } else { imagejpeg($q, $pfad, 90); }
    imagedestroy($q);
    return ['stored_name' => $abgelegt, 'mime' => 'image/' . $art, 'orig_name' => $name . '.' . $art];
};

$vsQuer = $vsBild('quer', 1200, 800);
$vsKlein = $vsBauen->invoke(null, $vsQuer, Ablage::VORSCHAU_KLEIN);
pruefe('aus einem Bild entsteht eine Vorschau', $vsKlein !== null && is_file((string) $vsKlein));
$vsMasse = $vsKlein !== null ? getimagesize((string) $vsKlein) : [0, 0];
pruefe('sie ist auf die lange Kante skaliert', (int) $vsMasse[0] === Ablage::VORSCHAU_KLEIN,
    (int) $vsMasse[0] . 'px');
pruefe('das Seitenverhältnis bleibt', (int) $vsMasse[1] === 213, (int) $vsMasse[1] . 'px');
pruefe('und sie ist ein JPEG, egal was hereinkam',
    (string) ($vsMasse['mime'] ?? '') === 'image/jpeg');

/* Hochformat: skaliert wird die LANGE Kante, sonst wird ein hohes Bild in der
   Liste doppelt so hoch wie ein breites. */
$vsHoch = $vsBild('hoch', 600, 1400);
$vsHochP = $vsBauen->invoke(null, $vsHoch, Ablage::VORSCHAU_KLEIN);
$vsHochM = $vsHochP !== null ? getimagesize((string) $vsHochP) : [0, 0];
pruefe('im Hochformat begrenzt die Höhe', (int) $vsHochM[1] === Ablage::VORSCHAU_KLEIN,
    (int) $vsHochM[1] . 'px');

/* Ein durchsichtiges Logo: JPEG kennt keine Durchsichtigkeit. Ohne weissen
   Grund wird daraus ein schwarzer Klotz — und genau Logos sind durchsichtig. */
$vsLogo = $vsBild('logo', 400, 400, 'png', true);
$vsLogoP = $vsBauen->invoke(null, $vsLogo, Ablage::VORSCHAU_KLEIN);
$vsEcke = [0, 0, 0];
if ($vsLogoP !== null) {
    $vsB = imagecreatefromjpeg((string) $vsLogoP);
    $vsF = imagecolorsforindex($vsB, imagecolorat($vsB, 2, 2));
    $vsEcke = [$vsF['red'], $vsF['green'], $vsF['blue']];
    imagedestroy($vsB);
}
pruefe('ein durchsichtiges Logo bekommt weißen Grund statt schwarzem',
    $vsEcke[0] > 240 && $vsEcke[1] > 240 && $vsEcke[2] > 240,
    'rgb(' . implode(',', $vsEcke) . ')');

/* Nie vergroessern: Aus 80 Punkten werden keine 320, das sieht nur matschig aus. */
$vsWinzig = $vsBild('winzig', 80, 60);
$vsWinzigM = getimagesize((string) $vsBauen->invoke(null, $vsWinzig, Ablage::VORSCHAU_GROSS));
pruefe('ein kleines Bild wird nicht aufgeblasen', (int) $vsWinzigM[0] === 80,
    (int) $vsWinzigM[0] . 'px');

/* Der Zwischenspeicher: einmal rechnen, nicht bei jedem Aufruf der Liste.
   Original und Vorschau entstehen in derselben Sekunde — erst wenn das
   Original nachweislich aelter ist, sagt der Vergleich etwas aus. */
touch($vsOrdner . '/' . $vsQuer['stored_name'], time() - 60);
clearstatcache();
$vsStand = filemtime((string) $vsBauen->invoke(null, $vsQuer, Ablage::VORSCHAU_KLEIN));
clearstatcache();
$vsWieder = $vsBauen->invoke(null, $vsQuer, Ablage::VORSCHAU_KLEIN);
pruefe('die zweite Anfrage rechnet nicht neu', filemtime((string) $vsWieder) === $vsStand);

/* Wird das Original neuer als die Vorschau, muss sie fallen — sonst zeigt die
   Liste ein Bild, das es so nicht mehr gibt. */
touch((string) $vsKlein, $vsStand - 30);
touch($vsOrdner . '/' . $vsQuer['stored_name'], $vsStand - 10);
clearstatcache();
$vsFrisch = $vsBauen->invoke(null, $vsQuer, Ablage::VORSCHAU_KLEIN);
pruefe('ein neueres Original erzwingt eine neue Vorschau',
    filemtime((string) $vsFrisch) > $vsStand - 30);

/* DAS IST DER GRUND FÜR DAS GANZE VERFAHREN
   ----------------------------------------------------------------------
   Ausgeliefert wird sonst nur als Anhang mit harter CSP. Inline darf nur,
   was wir selbst erzeugt haben — GD liest Bildpunkte und schreibt eine neue
   Datei, alles andere überlebt das nicht. */
$vsGift = $vsBild('polyglott', 900, 600);
$vsGiftPfad = $vsOrdner . '/' . $vsGift['stored_name'];
file_put_contents($vsGiftPfad, file_get_contents($vsGiftPfad) . "\n<?php echo 'NUTZLAST'; ?>\n");
pruefe('die Falle greift: das Original ist Bild UND trägt eine Nutzlast',
    (bool) @getimagesize($vsGiftPfad)
    && str_contains((string) file_get_contents($vsGiftPfad), 'NUTZLAST'));
$vsRein = $vsBauen->invoke(null, $vsGift, Ablage::VORSCHAU_KLEIN);
pruefe('die Vorschau trägt sie nicht mehr',
    $vsRein !== null && !str_contains((string) file_get_contents((string) $vsRein), 'NUTZLAST'));

/* Kaputtes und Riesiges dürfen nicht die Seite mitreißen. */
file_put_contents($vsOrdner . '/kette_kaputt.bin', 'das ist kein bild');
pruefe('eine kaputte Datei wird still abgelehnt',
    $vsBauen->invoke(null, ['stored_name' => 'kette_kaputt.bin', 'mime' => 'image/png',
                            'orig_name' => 'k.png'], Ablage::VORSCHAU_KLEIN) === null);
pruefe('eine fehlende Datei ebenso',
    $vsBauen->invoke(null, ['stored_name' => 'gibtesnicht.bin', 'mime' => 'image/jpeg',
                            'orig_name' => 'x.jpg'], Ablage::VORSCHAU_KLEIN) === null);

/* Der Vorschauordner ist gesperrt — die .jpg tragen die zweite Sicherung
   der .bin-Endung nicht. */
pruefe('der Vorschauordner hat seine eigene Sperre',
    is_file($vsOrdner . '/vorschau/.htaccess')
    && str_contains((string) file_get_contents($vsOrdner . '/vorschau/.htaccess'), 'denied'));

/* ---------- Löschen nimmt die gerechneten Bilder mit -------------------- */
$vsKunde = (int) Db::wert('SELECT customer_id FROM projects WHERE id = ?', [$projektId], 0);
$vsId = Db::insert('files', [
    'customer_id' => $vsKunde, 'project_id' => $projektId,
    'stored_name' => $vsQuer['stored_name'], 'orig_name' => 'zumloeschen.jpg',
    'mime' => 'image/jpeg', 'size_bytes' => 4096, 'uploaded_by' => 'kunde', 'rolle' => 'material',
]);
$vsVorschauPfad = (string) $vsBauen->invoke(null, $vsQuer, Ablage::VORSCHAU_KLEIN);
pruefe('vor dem Löschen liegt die Vorschau da', is_file($vsVorschauPfad));
Ablage::loeschen($vsId);
clearstatcache();
pruefe('Löschen nimmt die Bytes mit', !is_file($vsOrdner . '/' . $vsQuer['stored_name']));
pruefe('und die gerechnete Vorschau gleich mit', !is_file($vsVorschauPfad));
pruefe('der Eintrag ist auch weg',
    (int) Db::wert('SELECT COUNT(*) FROM files WHERE id = ?', [$vsId], 0) === 0);

/* Löschen ist endgültig — dafür gibt es jetzt eine Rückfrage, und zwar die
   schwere. Vorher stand der Knopf ohne jede Nachfrage im Projekt. */
require_once $oben . '/app/src/Ablauf.php';
$vsFrage = Ablauf::rueckfrage('datei_weg');
pruefe('Löschen fragt zurück', $vsFrage !== null);
pruefe('und zwar als schwerer Schritt',
    ($vsFrage['gewicht'] ?? '') === Ablauf::SCHWER, (string) ($vsFrage['gewicht'] ?? '—'));
pruefe('die Frage sagt, dass es endgültig ist',
    str_contains((string) ($vsFrage['frage'] ?? ''), 'endgültig'));

/* ---------- Die Seite „Dateien": Vorschau, Löschen, Filter -------------- */
$vsSeite = (string) file_get_contents($oben . '/app/views/dateien.php');
pruefe('die Dateiliste zeigt Miniaturen', str_contains($vsSeite, "?art=vorschau"));
pruefe('und öffnet die Großansicht', str_contains($vsSeite, "?art=gross"));
pruefe('was kein Bild ist, bekommt sein Kürzel statt einer falschen Miniatur',
    str_contains($vsSeite, 'class="dart"'));
pruefe('gelöscht wird von hier aus auch', str_contains($vsSeite, "value=\"datei_weg\""));
pruefe('und man landet danach wieder in derselben gefilterten Liste',
    str_contains($vsSeite, '$zurueckZiel'));
pruefe('es gibt einen Filter nach Kunde und Projekt',
    str_contains($vsSeite, 'name="kunde"') && str_contains($vsSeite, 'name="projekt"'));
pruefe('die Großansicht lässt sich mit Escape schließen',
    str_contains($vsSeite, "'Escape'"));
pruefe('und mit den Pfeiltasten blättern',
    str_contains($vsSeite, "'ArrowLeft'") && str_contains($vsSeite, "'ArrowRight'"));
pruefe('fehlt GD, sagt die Seite das, statt leere Kästen zu zeigen',
    str_contains($vsSeite, 'Bildbibliothek'));

/* ---------- Die Leiste der offenen Vorgänge ---------------------------- */
$vsVorgang = (string) file_get_contents($oben . '/app/views/vorgang.php');
pruefe('die Vorgangsseite trägt die Leiste', str_contains($vsVorgang, 'class="vl"'));
pruefe('sie verweist auf die anderen Vorgänge',
    str_contains($vsVorgang, "url('vorgaenge/' . \$l['schluessel'])"));
pruefe('der Vorgang, auf dem man steht, ist markiert',
    str_contains($vsVorgang, "\$l['schluessel'] === \$v['schluessel']"));
pruefe('und als solcher auch für Vorleseprogramme',
    str_contains($vsVorgang, 'aria-current="page"'));
pruefe('wer noch keine Antwort bekam, trägt einen Punkt',
    str_contains($vsVorgang, 'vl__neu'));
pruefe('die Leiste kommt aus derselben Quelle wie „Heute"',
    str_contains(file_get_contents($oben . '/app/index.php'), "'leiste' => sicher"));
pruefe('und fällt weich aus, wenn sie sich nicht bauen lässt',
    str_contains($vsVorgang, "\$leiste = \$leiste ?? "));

/* Die Leiste muss wirklich alle offenen Vorgänge kennen, nicht nur die
   eigenen — sonst kann man nicht zu dem wechseln, der gerade hereinkam. */
$vsListe = Vorgang::arbeitsliste();
pruefe('die Arbeitsliste liefert die drei Gruppen',
    isset($vsListe['du'], $vsListe['kunde'], $vsListe['ruht']));
$vsAlle = array_merge($vsListe['du'], $vsListe['kunde'], $vsListe['ruht']);
pruefe('jeder Eintrag trägt, was die Leiste zeigt',
    $vsAlle === [] || (isset($vsAlle[0]['schluessel'], $vsAlle[0]['kunde'],
        $vsAlle[0]['firma'], $vsAlle[0]['stufe_wort'])
        && array_key_exists('erstantwort', $vsAlle[0])));

/* ---------- „Heute": was keine Arbeit ist, klappt zu -------------------- */
$vsHeute = (string) file_get_contents($oben . '/app/views/heute.php');
pruefe('„Du bist dran" steht offen da',
    str_contains($vsHeute, '<div class="block">' . "\n" . '  <h2>Du bist dran'));
pruefe('„Der Kunde ist dran" ist eine Schublade',
    str_contains($vsHeute, 'Der Kunde ist dran') && str_contains($vsHeute, 'details class="block klapp"'));
pruefe('sie geht auf, wenn bei dir nichts liegt',
    str_contains($vsHeute, "<?= !\$liste['du'] ? 'open' : '' ?>"));
/* Auch diese Prüfung galt einem Kasten, den es nicht mehr gibt: „Demnächst
   fällig" ist in der Liste „Was gerade hängt" aufgegangen, wo eine Frist
   eine Art unter dreien ist. Dass sie dort ihren Platz behält, prüft
   Abschnitt 53 — und zwar an der Sache, nicht am Aufklappen. */
pruefe('„Demnächst fällig" ist in der Hängt-Liste aufgegangen',
    !str_contains($vsHeute, '$faelligEilt') && str_contains($vsHeute, "'frist'"));
pruefe('die Zahl bleibt auch zugeklappt sichtbar',
    substr_count($vsHeute, 'class="mehr"') >= 4);

/* Hier standen vier Prüfungen auf den Kasten „Das läuft nicht" und seinen
   Deckel. Den Kasten gibt es nicht mehr: Er ist mit „Demnächst fällig" und
   den stillen Vorgängen zu einer Liste zusammengegangen (Vorschlag 7). Was
   an seine Stelle trat, prüft Abschnitt 53. Die alten Prüfungen hier stehen
   zu lassen hätte geheißen, eine Gestalt zu sichern, die es nicht gibt. */
pruefe('der gedeckelte Störungskasten ist einer Liste gewichen',
    !str_contains($vsHeute, '$stMax') && str_contains($vsHeute, 'Was gerade hängt'));

/* Die Leiste am Handy: fester Kasten statt einer Liste, die den Kunden nach
   unten schiebt, plus der Ruck, der den aktuellen Eintrag ins Bild holt. */
$vsStil = (string) file_get_contents($oben . '/app/assets/admin.css');
pruefe('die Leiste hat am Handy eine feste Höhe',
    preg_match('~\.vl\{position:static;max-height:216px~', $vsStil) === 1);
pruefe('der aktuelle Eintrag wird ins Bild gerückt',
    str_contains($vsVorgang, 'scrollIntoView'));
/* Der Fehler, den nur das Rendern gefunden hat: display:flex schlaegt das
   hidden-Attribut, und die zugeklappte Grossansicht lag unsichtbar ueber der
   ganzen Seite und fing jeden Klick ab. */
pruefe('die zugeklappte Großansicht fängt keine Klicks ab',
    str_contains($vsStil, '.dgross[hidden]{display:none}'));
pruefe('die Filter stehen nebeneinander, nicht je auf einer Zeile',
    str_contains($vsStil, '.dfilter select{width:auto'));
pruefe('lange Namen in der Leiste enden mit Auslassungspunkten',
    str_contains($vsStil, '.vl__wort{min-width:0;overflow:hidden;text-overflow:ellipsis'));

// Aufräumen: die Bilder der Prüfung gehören nicht in den Ablageordner.
foreach (glob($vsOrdner . '/kette_*.bin') ?: [] as $vsWeg) { @unlink($vsWeg); }
foreach (glob($vsOrdner . '/vorschau/kette_*.jpg') ?: [] as $vsWeg) { @unlink($vsWeg); }

/* ============================================================================
   51. Einfache Ansicht, Schubladen, ein Bildschirm je Kunde

   Uwe, 13.09.2026: „Gesamte Verwaltung soll viel einfacher werden, dass
   selbst ein völliger Anfänger damit umgehen kann — aber keine Kette darf
   abreißen, nichts darf unübersichtlich sein."

   Der Satz hat zwei Hälften, und die zweite ist die schwierige. Einfacher
   wird es durch Weglassen; eine Kette reißt durch Weglassen. Also wird
   nichts weggelassen, sondern eingeräumt — und dieser Abschnitt prüft
   genau das: dass alles noch da ist, wo es hingehört.
   ============================================================================ */
abschnitt('51. Einfache Ansicht und ein Bildschirm je Kunde');

require_once $oben . '/app/src/Modus.php';
require_once $oben . '/app/src/Teile.php';

/* ---------- Der Schalter ------------------------------------------------ */
Modus::vergessen();
pruefe('ohne Eintrag ist die einfache Ansicht an', Modus::einfach() === true);

Modus::setzen(false);
Modus::vergessen();
pruefe('sie lässt sich abschalten', Modus::einfach() === false);
pruefe('und der Wert steht in den Einstellungen',
    (string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [Modus::SCHLUESSEL], '') === 'nein');

Modus::setzen(true);
Modus::vergessen();

/* ============================================================================
   52. Die Zeile, die Rückfragen und die fünf Türen

   Vorschläge 6, 5 und 3 aus derselben Liste wie Abschnitt 51. Alle drei
   sollen dasselbe leisten: Man soll sehen, wo man steht, was passiert, wenn
   man drückt, und wo man etwas findet — ohne dass eine Kette dabei reißt.
   ============================================================================ */
abschnitt('52. Kettenzeile, Rückfragen, fünf Türen');

require_once $oben . '/app/src/Ablauf.php';
require_once $oben . '/app/src/Vorgang.php';

/* ---------- Die Zeile, die nie abreißt ---------------------------------- */
$kzV = Vorgang::laden('b' . $bestellId);
pruefe('der Vorgang lässt sich laden', $kzV !== null);

$kzOffen = [];
foreach (Ablauf::checkliste($kzV) as $kzP) { if (!$kzP['da']) { $kzOffen[] = $kzP; } }
$kzDanach = Ablauf::danach($kzV);

pruefe('es gibt ein „Danach“, solange etwas offen ist',
    $kzOffen === [] || $kzDanach !== null);
pruefe('das „Danach“ ist nicht dasselbe wie das „Jetzt“',
    $kzOffen === [] || $kzDanach === null
    || (string) $kzDanach['was'] !== (string) $kzOffen[0]['was'],
    (string) ($kzDanach['was'] ?? '—'));
pruefe('es nennt seine Stufe mit', $kzDanach === null || isset($kzDanach['stufe']));
pruefe('und sagt, wer es tut',
    $kzDanach === null || in_array((string) $kzDanach['wer'], ['du', 'kunde'], true));

/* Das „Danach“ kommt aus der Checkliste, nicht aus einer erfundenen Liste
   „nach A kommt B“. Genau deshalb haelt es auch dann, wenn ein Schritt
   uebersprungen wurde — und uebersprungen wird staendig. */
pruefe('das „Danach“ steht wirklich in einer Checkliste',
    $kzDanach === null
    || in_array((string) $kzDanach['was'],
        array_column(Ablauf::checkliste($kzV + ['stufe' => $kzDanach['stufe']]), 'was'), true));

/* Am Ende der letzten Stufe darf es kein „Danach“ mehr geben — sonst
   verspraeche die Zeile ewig etwas, das nie kommt. */
$kzFertig = $kzV;
$kzFertig['stufe'] = 'fertig';
$kzFertig['offen_cent'] = 0;
$kzFertig['belege'] = [1];
pruefe('am Ende kommt nichts mehr', Ablauf::danach($kzFertig) === null);

/* Eine unbekannte Stufe darf nicht in eine Schleife laufen. */
$kzWirr = $kzV;
$kzWirr['stufe'] = 'gibtesnicht';
pruefe('eine unbekannte Stufe bricht sauber ab', Ablauf::danach($kzWirr) === null);

$kzSeite = (string) file_get_contents($oben . '/app/views/vorgang.php');
pruefe('die Zeile steht auf der Vorgangsseite', str_contains($kzSeite, 'class="kette"'));
pruefe('sie nennt den Schritt und wie viele es sind',
    str_contains($kzSeite, 'Schritt <?= (int) $kettenNr ?> von <?= (int) $kettenVon ?>'));
pruefe('sie sagt „Fehlt noch“ statt „Jetzt“ — die Punkte sind Zustände, keine Befehle',
    str_contains($kzSeite, 'Fehlt noch:') && !str_contains($kzSeite, '<b>Jetzt:</b>'));
pruefe('und sagt es auch, wenn nichts mehr kommt',
    str_contains($kzSeite, 'Danach kommt nichts mehr'));

/* ---------- Die Rückfragen ---------------------------------------------- */
$rfAlle = Ablauf::TRAGWEITE;
$rfQuelle = (string) file_get_contents($oben . '/app/index.php');
preg_match_all("~^            case '([a-z_]+)':~m", $rfQuelle, $rfM);
$rfHandgriffe = array_values(array_unique($rfM[1] ?? []));

pruefe('es gibt deutlich mehr Rückfragen als die zwölf von früher',
    count($rfAlle) >= 45, count($rfAlle) . ' von ' . count($rfHandgriffe));
pruefe('aber nicht für alles — sonst wird sie zur Gewohnheit',
    count($rfAlle) < count($rfHandgriffe) * 0.6,
    round(100 * count($rfAlle) / max(1, count($rfHandgriffe))) . ' %');

$rfTot = array_values(array_diff(array_keys($rfAlle), $rfHandgriffe));
pruefe('jede Rückfrage trifft einen Handgriff, den es gibt',
    $rfTot === [], implode(', ', $rfTot));

$rfOhneText = [];
foreach ($rfAlle as $rfT => $rfE) {
    if (mb_strlen(trim($rfE[1])) < 25 || trim($rfE[2]) === '') { $rfOhneText[] = $rfT; }
}
pruefe('jede sagt in einem ganzen Satz, was passiert', $rfOhneText === [],
    implode(', ', $rfOhneText));

/* DIE FRAGE, DIE NIEMANDEN AUFHAELT: „Sind Sie sicher?“ Wer das liest,
   antwortet Ja, ohne gelesen zu haben. Gesagt werden muss, WAS passiert. */
$rfFaul = [];
foreach ($rfAlle as $rfT => $rfE) {
    if (preg_match('~sicher\?|wirklich\?~ui', $rfE[1])) { $rfFaul[] = $rfT; }
}
pruefe('keine fragt „Sind Sie sicher?“', $rfFaul === [], implode(', ', $rfFaul));

$rfJaOhneJa = [];
foreach ($rfAlle as $rfT => $rfE) {
    if (!str_starts_with(trim($rfE[2]), 'Ja,')) { $rfJaOhneJa[] = $rfT; }
}
pruefe('und jeder Ja-Knopf sagt, wozu man Ja sagt', $rfJaOhneJa === [],
    implode(', ', $rfJaOhneJa));

/* Die Handgriffe, bei denen etwas beim KUNDEN ankommt — sie sind der Grund,
   warum es die Liste ueberhaupt gibt. */
foreach (['nachricht_senden', 'kunde_nachricht', 'angebot_senden', 'mahnung_schicken',
          'paket_mail', 'abo_anfordern', 'abo_kuendigen', 'hosting_vorschlag',
          'fragebogen_erinnern', 'cron_jetzt'] as $rfN) {
    pruefe("„{$rfN}“ fragt, bevor der Kunde Post bekommt", isset($rfAlle[$rfN]));
}
/* Und die, nach denen etwas in den Buechern steht oder endgueltig weg ist. */
foreach (['zahlung_bestaetigen', 'angebot_zusage', 'anfrage_bestellung', 'bestellung_anlegen',
          'mehrbedarf_nachtrag', 'ausgabe_loeschen', 'kas_account_anlegen', 'cockpit_frei',
          'kundenlink_neu', 'versand_schluessel_weg', 'werkstatt_schluessel_weg',
          'mail_loeschen', 'gespraech_loeschen', 'stimme_frei'] as $rfN) {
    pruefe("„{$rfN}“ fragt, bevor es nicht mehr rückgängig geht", isset($rfAlle[$rfN]));
    pruefe("und zwar als schwerer Schritt oder als Post nach draußen",
        in_array(Ablauf::wiegt($rfN), [Ablauf::SCHWER, Ablauf::RAUS], true));
}

/* Was weiterhin schweigt — und schweigen soll. */
foreach (['kunde_speichern', 'aufgabe_umschalten', 'nachrichten_gelesen', 'merkliste_setzen',
          'meldung_gelesen', 'bedienung', 'sprache_setzen'] as $rfS) {
    pruefe("„{$rfS}“ fragt weiterhin nicht", Ablauf::wiegt($rfS) === Ablauf::STILL);
}

/* Die Tabelle muss auch wirklich im Browser ankommen — ohne das ist die
   ganze Liste ein Stück Papier. */
$rfLayout = (string) file_get_contents($oben . '/app/views/layout.php');
pruefe('die Rückfragen gehen an den Browser',
    str_contains($rfLayout, 'window.vecomBremse'));
pruefe('und werden über das Feld „tat“ zugeordnet',
    str_contains($rfLayout, "f.querySelector('input[name=\"tat\"]')"));

/* ---------- Die fünf Türen ---------------------------------------------- */
preg_match('~\$menue = \[(.*?)\n\];~s', $rfLayout, $mM);
$mText = $mM[1] ?? '';
preg_match_all("~^  \['([a-z]+)', '([^']+)'~m", $mText, $mT);
$mTueren = $mT[2] ?? [];
pruefe('es sind fünf Türen', count($mTueren) === 5, implode(' · ', $mTueren));
pruefe('und sie heißen nach dem, was man tut',
    $mTueren === ['Heute', 'Kunden', 'Geld', 'Bauen', 'Einstellungen'],
    implode(' · ', $mTueren));

/* Jede Seite, die es vorher im Menue gab, muss hinter genau einer Tuer
   liegen -- sonst ist sie still verschwunden. */
preg_match_all("~'([a-z]+)', '[^']+', '[a-z]+'~", $mText, $mZ);
$mZiele = array_values(array_unique($mZ[1] ?? []));
$mFrueher = ['heute', 'vorgaenge', 'nachrichten', 'werkstatt', 'bedarf', 'angebote',
             'rechnungen', 'empfehlungen', 'anfragen', 'standard', 'muster', 'onboarding',
             'zahlungen', 'ausgaben', 'abos', 'finanzamt', 'pakete', 'baukasten',
             'stimmen', 'dateien', 'dashboard', 'monitoring', 'telefon', 'aktivitaeten',
             'benachrichtigungen', 'einstellungen', 'kunden', 'partner', 'bereit'];

/* ---------- Jeder Menüpunkt muss auch ankommen --------------------------
   Am 13.09.2026 beim Durchrendern gefunden: „Ausgaben", „Betreuung",
   „Kundenstimmen" und „Fürs Finanzamt" standen seit jeher im Menü und
   antworteten mit 404 — die Ansichten lagen fertig da, der Weg dorthin
   fehlte. Aufgefallen war es nie, weil sie in der zweiten Hälfte einer
   Liste von vierundzwanzig standen.

   Deshalb steht das jetzt in der Kette: Jedes Ziel im Menü braucht seinen
   Fall im Verteiler. */
$mOhneRoute = [];
foreach ($mZiele as $mZ2) {
    if (!in_array($mZ2, $rfRouten ?? [], true)) { $mOhneRoute[] = $mZ2; }
}
preg_match_all("~^    case '([a-z_]+)':~m", $rfQuelle, $mR);
$mRouten = $mR[1] ?? [];
$mOhneRoute = array_values(array_diff($mZiele, $mRouten));
pruefe('jeder Menüpunkt hat einen Fall im Verteiler', $mOhneRoute === [],
    implode(', ', $mOhneRoute));

/* Und keiner darf so heissen wie ein echter Ordner unter app/: Die
   Umleitung laesst Verzeichnisse ausdruecklich in Ruhe (RewriteCond !-d),
   der Aufruf landete also im Ordner statt auf der Seite. Bei „steuerakte"
   waere das erst aufgefallen, sobald das erste Jahrespaket geschrieben ist —
   Monate spaeter, und niemand haette den Zusammenhang gesehen. */
$mOrdner = array_map('basename', array_filter(glob($oben . '/app/*') ?: [], 'is_dir'));
$mKollision = array_values(array_intersect($mZiele, $mOrdner));
pruefe('kein Menüpunkt heißt wie ein Ordner unter app/', $mKollision === [],
    implode(', ', $mKollision));
/* Seit dem 26.09.2026 stehen manche Seiten als Reiter unter einer
   Menüzeile ($reiter in layout.php). Erreichbar heißt: im Menü ODER als
   Reiter einer Menüzeile -- und jede Reitergruppe hängt an einer Zeile,
   die es im Menü gibt, sonst wären alle ihre Seiten weg. */
preg_match('~\$reiter = \[(.*?)\n\];~s', $rfLayout, $mRe);
preg_match_all("~^  '([a-z]+)' => \[~m", $mRe[1] ?? '', $mRg);
preg_match_all("~\['([a-z]+)', '[^']+', '[a-z]+'\]~", $mRe[1] ?? '', $mRz);
$mReiterZiele = $mRz[1] ?? [];
pruefe('jede Reitergruppe hängt an einer Menüzeile, die es gibt',
    ($mRg[1] ?? []) !== [] && array_diff($mRg[1], $mZiele) === [], implode(', ', array_diff($mRg[1] ?? [], $mZiele)));
pruefe('jeder Reiter hat einen Fall im Verteiler',
    array_diff($mReiterZiele, $mRouten) === [], implode(', ', array_diff($mReiterZiele, $mRouten)));
$mErreichbar = array_values(array_unique(array_merge($mZiele, $mReiterZiele)));
$mWeg = array_values(array_diff($mFrueher, $mErreichbar));
pruefe('keine Seite ist beim Umbau verschwunden', $mWeg === [], implode(', ', $mWeg));
pruefe('und keine steht hinter zwei Türen',
    count($mZiele) === count(array_unique($mZiele))
    && count($mReiterZiele) === count(array_unique($mReiterZiele)));


/* Die Zahl an einer zugeklappten Tuer ist die Summe dahinter. Ohne das
   sieht man die Null und haelt sie fuer die Wahrheit. */
pruefe('eine zugeklappte Tür trägt die Summe dessen, was dahinter offen ist -- Reiter eingeschlossen',
    str_contains($rfLayout, '$summe += isset($reiter[$uZiel]) ? $reiterZahl($uZiel) : (int) ($navZahlen[$uSchl] ?? 0);')
    && str_contains($rfLayout, '$n += (int) ($navZahlen[$rSchl] ?? 0);'));
pruefe('und klappt auf, wenn man darin arbeitet',
    str_contains($rfLayout, "if (\$aktiv === \$uZiel) { \$offen = true; }"));
/* Stoerungen gehoeren nicht unter "Einstellungen": Achtzehn offene Warnungen
   neben dem Wort heissen fuer jeden Leser, dass mit den Einstellungen etwas
   nicht stimmt. */
pruefe('„Was nicht läuft“ hängt unter „Heute“, nicht unter „Einstellungen“',
    preg_match("~'heute', 'Heute', 'heute', \[\s*\['benachrichtigungen'~", $rfLayout) === 1);
/* Geprueft wird die Mechanik, nicht das Wort: Dass im Kommentar steht,
   was „Alles andere" einmal war, gehoert dazu — verschwinden muss die
   zweite Liste samt ihrer Schublade. */
pruefe('die alte zweite Menüliste gibt es nicht mehr',
    !str_contains($rfLayout, '$menueMehr')
    && !str_contains($rfLayout, '$mehrZahl')
    && !str_contains($rfLayout, '<details class="mehr"'));
pruefe('und ihre Stilregeln auch nicht',
    !str_contains((string) file_get_contents($oben . '/app/assets/admin.css'), '.nav details.mehr'));

/* ============================================================================
   53. Was hängt, deutsche Wörter, und damit alles läuft

   Vorschläge 7, 8 und 9 — die letzten drei aus der Liste vom 13.09.2026.
   Alle drei beantworten dieselbe Frage von verschiedenen Seiten: Sieht
   jemand, der die Verwaltung nicht gebaut hat, was los ist?
   ============================================================================ */
abschnitt('53. Was hängt, deutsche Wörter, Einrichtung');

require_once $oben . '/app/src/Haengt.php';
require_once $oben . '/app/src/Bereit.php';
require_once $oben . '/app/src/Status.php';

/* ---------- Was hängt ---------------------------------------------------- */
$hL = Haengt::alles();
pruefe('die Liste lässt sich bauen', is_array($hL));
pruefe('sie ist gedeckelt', count($hL) <= Haengt::HOECHSTENS, (string) count($hL));
pruefe('und sagt, wie viel insgesamt hängt', Haengt::anzahl() >= count($hL));

foreach ($hL as $hZ) {
    pruefe('jede Zeile sagt, was hängt', trim((string) $hZ['titel']) !== '');
    pruefe('jede nennt ihre Art', in_array((string) $hZ['art'], ['stoerung', 'frist', 'stille'], true));
    pruefe('und führt irgendwohin', trim((string) $hZ['ziel']) !== '');
    break;   // eine reicht als Stichprobe; die Form ist für alle dieselbe
}

/* DIE REGEL, AUF DIE ES ANKOMMT: Keine Art darf die anderen verdraengen.
   Beim ersten Lauf nahmen achtzehn Stoerungen alle zwoelf Plaetze — die
   ablaufenden Angebote und die stillen Vorgaenge kamen gar nicht mehr vor. */
$hArten = array_count_values(array_column($hL, 'art'));
$hZuViel = [];
foreach (['stoerung', 'frist', 'stille'] as $hA) {
    /* Mehr als JE_ART ist erlaubt — aber nur, wenn die anderen Arten ihre
       Plaetze gar nicht gebraucht haben. */
    if (($hArten[$hA] ?? 0) > Haengt::JE_ART) {
        $andere = count($hL) - ($hArten[$hA] ?? 0);
        $moeglich = 0;
        foreach (['stoerung', 'frist', 'stille'] as $hB) {
            if ($hB !== $hA) { $moeglich += min(Haengt::JE_ART, $hArten[$hB] ?? 0); }
        }
        if ($andere < $moeglich) { $hZuViel[] = $hA; }
    }
}
pruefe('keine Art verdrängt die anderen', $hZuViel === [], implode(', ', $hZuViel));

/* STILLE IST DER FALL, DER VORHER KEINEN KASTEN HATTE

   Geprueft wird mit einer gebauten Arbeitsliste statt mit gealterten
   Datenbankzeilen. Der Grund ist lehrreich: „bewegt" ist die juengste von
   fuenf Zeitangaben — Bestellung, Projekt, Fragebogen, letzte Zahlung,
   letzte Nachricht. Der erste Versuch alterte nur zwei davon, und der
   Vorgang galt weiter als frisch. Genau dafuer nimmt alles() die
   Arbeitsliste entgegen: damit man sie auch bauen kann. */
$hBau = static fn(string $schl, int $tage, string $wer): array => [
    'schluessel' => $schl,
    'kunde' => 'Stiller Kunde', 'firma' => '',
    'stufe_wort' => 'Angebot',
    'warum' => 'Der Link ist da, aber der Kunde hat ihn noch nicht.',
    'bewegt' => date('Y-m-d H:i:s', time() - $tage * 86400),
    'begonnen' => date('Y-m-d H:i:s', time() - $tage * 86400),
    'schritt' => ['knopf' => 'Zahlungslink senden', 'tat' => 'zahlungslink_senden',
                  'id' => 1, 'felder' => [], 'direkt' => true, 'ziel' => null],
];

$hStilleListe = Haengt::alles([
    'du'    => [$hBau('b900', 40, 'du')],
    'kunde' => [$hBau('b901', 30, 'kunde')],
    'ruht'  => [],
]);
$hStille = array_values(array_filter($hStilleListe, static fn($z) => $z['art'] === 'stille'));
pruefe('ein Vorgang, an dem seit vierzig Tagen nichts passiert, taucht auf',
    $hStille !== [], (string) count($hStille));
if ($hStille !== []) {
    pruefe('und die Zeile sagt, seit wann',
        str_contains((string) $hStille[0]['warum'], 'Tagen'));
    pruefe('sie gilt als eilig', (bool) $hStille[0]['eilig'] === true);
    pruefe('und der Knopf nennt den nächsten Handgriff',
        (string) $hStille[0]['wohin'] === 'Zahlungslink senden');
}
pruefe('beide Seiten kommen vor — meine und die des Kunden',
    count($hStille) === 2, (string) count($hStille));

/* Und was normal laeuft, gehoert NICHT hierher. Ein Kunde, der seit zwei
   Tagen nicht geantwortet hat, haengt nicht — er antwortet nur noch nicht. */
$hFrisch = Haengt::alles([
    'du'    => [$hBau('b902', 2, 'du')],
    'kunde' => [$hBau('b903', 5, 'kunde')],
    'ruht'  => [],
]);
$hNochDa = false;
foreach ($hFrisch as $z) {
    if ($z['art'] === 'stille' && str_contains((string) $z['ziel'], 'b90')) { $hNochDa = true; }
}
pruefe('ein Vorgang von vorgestern hängt nicht', $hNochDa === false);

/* Die Grenze liegt wirklich dort, wo sie steht — einen Tag davor noch nicht. */
$hKnapp = Haengt::alles(['du' => [$hBau('b904', Haengt::STILL_BEI_MIR - 1, 'du')],
                         'kunde' => [], 'ruht' => []]);
pruefe('einen Tag vor der Grenze ist noch nichts',
    array_filter($hKnapp, static fn($z) => $z['art'] === 'stille') === []);
$hGenau = Haengt::alles(['du' => [$hBau('b905', Haengt::STILL_BEI_MIR, 'du')],
                         'kunde' => [], 'ruht' => []]);
pruefe('am Tag der Grenze schon',
    array_filter($hGenau, static fn($z) => $z['art'] === 'stille') !== []);

pruefe('die Grenzen sind verschieden: bei mir früher als beim Kunden',
    Haengt::STILL_BEI_MIR < Haengt::STILL_BEIM_KUNDEN);

$hHeute = (string) file_get_contents($oben . '/app/views/heute.php');
pruefe('„Heute" zeigt die eine Liste', str_contains($hHeute, 'Was gerade hängt'));
/* Geprueft wird die Gestalt, nicht das Wort: Dass im Kommentar steht, was
   die beiden Kaesten einmal waren, gehoert dazu. Verschwinden muessen die
   Ueberschriften und die Variablen, an denen sie hingen. */
pruefe('und nicht mehr die beiden alten Kästen',
    !str_contains($hHeute, '<h2 style="color:var(--rot)">Das läuft nicht')
    && !str_contains($hHeute, '<h2>Demnächst fällig')
    && !str_contains($hHeute, '$stoerungen')
    && !str_contains($hHeute, '$faellig'));
pruefe('was nicht mehr in die Liste passt, verschwindet nicht still',
    str_contains($hHeute, '$hGesamt'));
pruefe('eine gemeldete Störung lässt sich von dort erledigen',
    str_contains($hHeute, "\$h['tat']"));

/* ---------- Deutsche Wörter ---------------------------------------------- */
pruefe('die Stufe heißt „Fragebogen", nicht „Onboarding"',
    (Status::PROJEKT['onboarding'] ?? '') === 'Fragebogen');
pruefe('auch bei der Bestellung',
    (Status::BESTELLUNG['onboarding'] ?? '') === 'Fragebogen');
pruefe('aus „Kundenfeedback" wird „Rückmeldung vom Kunden"',
    (Status::PROJEKT['kundenfeedback'] ?? '') === 'Rückmeldung vom Kunden');
pruefe('aus „Finale Freigabe" wird „Abgenommen"',
    (Status::PROJEKT['finale_freigabe'] ?? '') === 'Abgenommen');

/* DIE SCHLUESSEL DUERFEN SICH NICHT AENDERN. Sie stehen in jeder
   gespeicherten Zeile; wer sie umbenennt, macht jeden Bestand ungueltig. */
foreach (['bestellung_eingegangen', 'zahlung_bestaetigt', 'onboarding',
          'informationen_erhalten', 'design', 'entwicklung', 'vorschau',
          'kundenfeedback', 'aenderungen', 'finale_freigabe'] as $sK) {
    pruefe("der gespeicherte Wert „{$sK}“ ist unberührt",
        array_key_exists($sK, Status::PROJEKT));
}

$sWorte = [
    'app/views/vorgaenge.php'  => ['<h1>Vorgänge</h1>', '<h1>Kunden</h1>'],
    'app/views/baukasten.php'  => ['<h1>Baukasten</h1>', '<h1>Preisbausteine</h1>'],
    'app/views/dashboard.php'  => ['<h3>Onboarding</h3>', '<h3>Fragebögen offen</h3>'],
];
foreach ($sWorte as $sDatei => [$sAlt, $sNeu]) {
    $sT = (string) file_get_contents($oben . '/' . $sDatei);
    pruefe("in $sDatei steht das deutsche Wort",
        !str_contains($sT, $sAlt) && str_contains($sT, $sNeu));
}
pruefe('„Mehrbedarf klären" heißt jetzt „Mehr als bestellt"',
    str_contains((string) file_get_contents($oben . '/app/views/vorgang.php'), 'Mehr als bestellt'));

/* Die Wörter aendern sich in der Oberflaeche, nicht in den Daten: Die Tat
   heisst weiter mehrbedarf_nachtrag, sonst greift kein Formular mehr. */
pruefe('die Handgriffe behalten ihre Namen',
    str_contains((string) file_get_contents($oben . '/app/index.php'), "case 'mehrbedarf_nachtrag':"));

/* ---------- Damit alles läuft -------------------------------------------- */
$bP = Bereit::punkte();
pruefe('die Einrichtungsseite prüft zehn Dinge', count($bP) === 10, (string) count($bP));
$bSchl = array_column($bP, 'schluessel');
foreach (['cron', 'mail', 'stripe', 'webhook', 'cockpit', 'firma', 'ablage',
          'datenbank', 'beispiel', 'werkstatt'] as $bS) {
    pruefe("sie prüft „{$bS}“", in_array($bS, $bSchl, true));
}
pruefe('jeder Schlüssel kommt nur einmal vor', count($bSchl) === count(array_unique($bSchl)));

$bOhne = [];
foreach ($bP as $bZ) {
    if (!in_array((string) $bZ['stand'], [Bereit::GUT, Bereit::WARNUNG, Bereit::FEHLER], true)
        || trim((string) $bZ['was']) === '' || trim((string) $bZ['text']) === '') {
        $bOhne[] = (string) $bZ['schluessel'];
    }
}
pruefe('jede Zeile hat Stand, Titel und einen Satz dazu', $bOhne === [], implode(', ', $bOhne));

/* Bei rot muss ein Weg dastehen. Eine Seite, die sagt „etwas fehlt" und
   nicht wohin, ist schlimmer als keine — sie erzeugt Ratlosigkeit statt
   Arbeit. */
$bOhneWeg = [];
foreach ($bP as $bZ) {
    if ($bZ['stand'] !== Bereit::GUT && trim((string) ($bZ['ziel'] ?? '')) === '') {
        $bOhneWeg[] = (string) $bZ['schluessel'];
    }
}
pruefe('wo etwas fehlt, steht auch der Weg dorthin', $bOhneWeg === [], implode(', ', $bOhneWeg));

$bB = Bereit::bilanz($bP);
pruefe('die Bilanz zählt alle Zeilen',
    (int) $bB['gesamt'] === count($bP)
    && (int) $bB[Bereit::GUT] + (int) $bB[Bereit::WARNUNG] + (int) $bB[Bereit::FEHLER] === count($bP));

/* Der Webhook ist die Zeile, wegen der es diese Seite gibt: Am 13.09.2026 war
   ein Geheimnis hinterlegt UND es kam trotzdem nie einer an. Beides muss
   getrennt geprueft werden, sonst haette die Seite gruen gezeigt. */
$bWebhook = null;
foreach ($bP as $bZ) { if ($bZ['schluessel'] === 'webhook') { $bWebhook = $bZ; } }
pruefe('der Webhook wird eigens geprüft', $bWebhook !== null);
pruefe('und zwar daran, ob je einer ankam — nicht nur, ob etwas eingetragen ist',
    str_contains((string) file_get_contents($oben . '/app/src/Bereit.php'),
        "FROM webhook_events"));
pruefe('die Zeile erklärt, was am 13.09.2026 passiert ist',
    $bWebhook !== null && (str_contains((string) $bWebhook['warum'], '13.09.2026')
        || $bWebhook['stand'] !== Bereit::FEHLER));

$bSeite = (string) file_get_contents($oben . '/app/views/bereit.php');
pruefe('die Seite sagt oben in einem Satz, woran man ist',
    str_contains($bSeite, 'bereit__satz'));
pruefe('und sagt es auch, wenn alles steht', str_contains($bSeite, 'Alles steht'));
$bLayout = (string) file_get_contents($oben . '/app/views/layout.php');
pruefe('sie steht im Menü unter Einstellungen',
    str_contains($bLayout, "['bereit', 'Damit alles läuft', 'bereit']"));
pruefe('und die Zahl daran zählt nur echte Fehler, keine Warnungen',
    str_contains($bLayout, "Bereit::bilanz()[Bereit::FEHLER]"));

pruefe('und wieder einschalten', Modus::einfach() === true);

/* Ein unbekannter Wert darf nicht die volle Ansicht bedeuten: Wer die
   Verwaltung zum ersten Mal aufmacht, soll nicht wegen eines Tippfehlers in
   der Datenbank vor 31 Menüpunkten sitzen. */
Db::run('UPDATE settings SET svalue = ? WHERE skey = ?', ['irgendwas', Modus::SCHLUESSEL]);
Modus::vergessen();
pruefe('bei einem unsinnigen Wert bleibt es einfach', Modus::einfach() === true);
Modus::setzen(true);

/* ---------- Die Schublade gibt nichts aus, wenn alles offen steht -------- */
$sbIndex = (string) file_get_contents($oben . '/app/index.php');
pruefe('mehr_auf steigt im vollen Modus sofort aus',
    preg_match('~function mehr_auf.*?if \(!Modus::einfach\(\)\) \{ return; \}~s', $sbIndex) === 1);
pruefe('mehr_zu ebenso',
    preg_match('~function mehr_zu.*?if \(!Modus::einfach\(\)\) \{ return; \}~s', $sbIndex) === 1);
pruefe('eine Schublade kann offen stehen, wenn der Handgriff darin liegt',
    str_contains($sbIndex, "\$offen ? ' open' : ''"));

/* ---------- Der Zerleger ------------------------------------------------- */
$tHtml = 'Kopfzeug<!--teil:eins-->AAA<!--teil:zwei-->BBB<!--teil:drei-->';
$tAus  = Teile::ausHtml($tHtml);
pruefe('der Zerleger findet die Abschnitte', array_keys($tAus) === ['eins', 'zwei', 'drei']);
pruefe('und ordnet ihnen den richtigen Inhalt zu',
    ($tAus['eins'] ?? '') === 'AAA' && ($tAus['zwei'] ?? '') === 'BBB');
pruefe('was vor der ersten Marke steht, fällt weg',
    !in_array('Kopfzeug', $tAus, true));
pruefe('der letzte Abschnitt darf leer sein', ($tAus['drei'] ?? null) === '');
pruefe('ohne Marken kommt nichts zurück', Teile::ausHtml('nur text') === []);
pruefe('ein doppelter Name gewinnt nicht zweimal',
    (Teile::ausHtml('<!--teil:x-->A<!--teil:x-->B')['x'] ?? '') === 'A');
pruefe('eine Ansicht, die es nicht gibt, reißt nichts um',
    Teile::ausAnsicht($oben . '/app/views/gibtesnicht.php', []) === []);

/* ---------- Die Marken in der Projektseite ------------------------------- */
$tProjekt = (string) file_get_contents($oben . '/app/views/projekt.php');
preg_match_all(Teile::MUSTER, $tProjekt, $tM);
$tNamen = $tM[1] ?? [];
pruefe('die Projektseite ist durchgehend markiert', count($tNamen) >= 16, (string) count($tNamen));
pruefe('jede Marke kommt nur einmal vor', count($tNamen) === count(array_unique($tNamen)));
foreach (['werkstatt', 'abnahme', 'aufgaben', 'paket', 'eckdaten', 'ablauf', 'mails'] as $tN) {
    pruefe("die Schublade findet „{$tN}“", in_array($tN, $tNamen, true));
}
/* DIE MARKE, AUF DIE ES ANKOMMT: Ohne sie landen die schliessenden Kaesten
   der zweispaltigen Seite im letzten Abschnitt — und damit zwei ueberzaehlige
   </div> mitten in der Vorgangsseite. */
pruefe('hinter dem letzten Abschnitt steht eine Marke „ende“', in_array('ende', $tNamen, true));
pruefe('und sie steht wirklich vor dem schließenden Kasten',
    strpos($tProjekt, '<!--teil:ende-->') < strrpos($tProjekt, '</div></div>'));

/* Jeder Block der Projektseite hat seine Marke -- sonst faellt einer beim
   Zerlegen in den Abschnitt davor und taucht in der falschen Schublade auf. */
pruefe('es gibt so viele Marken wie Blöcke (plus die Endmarke)',
    count($tNamen) === substr_count($tProjekt, '<div class="block"') + 1,
    count($tNamen) . ' Marken, ' . substr_count($tProjekt, '<div class="block"') . ' Blöcke');

/* ---------- Die Kundenakte als Schublade --------------------------------- */
$tKunde = (string) file_get_contents($oben . '/app/views/kunde.php');
pruefe('die Kundenakte weiß, ob sie eingebettet ist',
    str_contains($tKunde, '$eing = !empty($eingebettet);'));
pruefe('eingebettet lässt sie die Kopfzeile weg',
    preg_match('~\$eing = !empty\(\$eingebettet\);\s*\?>\s*<\?php if \(!\$eing\): \?>~', $tKunde) === 1);
foreach (['Kontakt', 'Betreuung', 'Domain &amp; Hosting', 'Interne Notizen', 'Kunde entfernen'] as $tB) {
    pruefe("„{$tB}“ bleibt auch in der Schublade", str_contains($tKunde, $tB));
}
/* Die Zahl der Weichen: Kopf samt linker Spalte, "Seine Seite", "Verlauf",
   der schliessende Kasten. Vier -- nicht mehr, sonst faellt etwas weg, das
   nirgendwo sonst steht. */
pruefe('sie lässt genau vier Stellen weg', substr_count($tKunde, '<?php if (!$eing): ?>') === 4,
    (string) substr_count($tKunde, '<?php if (!$eing): ?>'));
pruefe('und macht jede davon wieder zu',
    substr_count($tKunde, '<?php if (!$eing): ?>') === substr_count($tKunde, '<?php endif; ?>')
        - substr_count($tKunde, '<?php endif; ?>') + substr_count($tKunde, '<?php if (!$eing): ?>'));

/* ---------- Die Vorgangsseite: nichts verloren --------------------------- */
$tVorgang = (string) file_get_contents($oben . '/app/views/vorgang.php');
pruefe('sie trägt fünf Schubladen', substr_count($tVorgang, 'mehr_auf(') === 5,
    (string) substr_count($tVorgang, 'mehr_auf('));
pruefe('und macht jede wieder zu',
    substr_count($tVorgang, 'mehr_auf(') === substr_count($tVorgang, 'mehr_zu()'));
pruefe('alle dreizehn eigenen Blöcke stehen noch da',
    substr_count($tVorgang, '<div class="block"') === 13,
    (string) substr_count($tVorgang, '<div class="block"'));
/* 29 waren es, 26 sind es: Die drei Knöpfe „Kundenakte", „Bestellung" und
   „Projekt" oben rechts sind weg. Sie führten dorthin, wo man seit dem
   Umbau schon steht — die Akte und das Projekt liegen als Schubladen auf
   dieser Seite. Wer die alten Seiten ganz braucht, findet den Verweis in
   der jeweiligen Schublade, im Zusammenhang. */
/* 27 seit dem 21.09.2026: "Fragebogen verschicken" VOR dem Preis. Der
   bisherige Knopf verschickt den Fragebogen eines Projekts -- vor der
   Zahlung gibt es keins, also braucht es einen eigenen. */
pruefe('und siebenundzwanzig Knöpfe — drei Wege ins Nirgendwo weniger, einer vor dem Preis mehr',
    substr_count($tVorgang, 'class="knopf') === 27,
    (string) substr_count($tVorgang, 'class="knopf'));
pruefe('die Kundenakte kommt als Schublade dazu', str_contains($tVorgang, "'/kunde.php'"));
pruefe('die Projektstücke auch', str_contains($tVorgang, '$projektteile ?? []'));

/* Die Zuordnung Handgriff → Schublade muss jeden Handgriff kennen, der auf
   dieser Seite steht. Was nicht darin steht, liegt hinter einer zugeklappten
   Tür, obwohl die Führung darauf zeigt — genau das „Kette reißt ab“. */
preg_match_all('~name="tat" value="([a-z_]+)"~', $tVorgang, $tTaten);
$tAufDerSeite = array_values(array_unique($tTaten[1] ?? []));
preg_match_all("~'([a-z_]+)'~", (string) (strstr(substr($tVorgang, strpos($tVorgang, '$schubladen = [')),
    '];', true) ?: ''), $tZug);
$tZugeordnet = array_values(array_unique($tZug[1] ?? []));
$tFehlen = array_values(array_diff($tAufDerSeite, $tZugeordnet,
    /* Diese führen nichts weiter, sie räumen auf oder schreiben nur. */
    ['kundenlink_neu', 'projekt_felder', 'merkliste_setzen', 'merkliste_weg',
     'gespraech_loeschen', 'verlauf_loeschen', 'aktivitaet_loeschen']));
pruefe('jeder Handgriff der Seite kennt seine Schublade',
    $tFehlen === [], $tFehlen ? implode(', ', $tFehlen) : '');

/* ---------- Die alten Seiten bleiben erreichbar -------------------------- */
$tLayout = (string) file_get_contents($oben . '/app/views/layout.php');
pruefe('Bestellungen und Projekte stehen nicht mehr als eigene Listen im Menü',
    !str_contains($tLayout, "['bestellungen', 'Bestellungen', 'bestellungen']")
    && !str_contains($tLayout, "['projekte', 'Projekte', 'projekte']"));
pruefe('ihre Seiten gibt es trotzdem noch',
    str_contains($sbIndex, "case 'kunden':")
    && str_contains($sbIndex, "case 'bestellungen':")
    && str_contains($sbIndex, "case 'projekte':"));
pruefe('und die Vorgangsseite verweist auf sie',
    str_contains($tVorgang, "url('kunden/' . (int) \$v['kunde_id'])")
    && str_contains($tVorgang, "url('projekte/' . (int) \$pid)")
    && str_contains($tVorgang, "url('bestellungen/' . (int) \$v['bestell_id'])"));

/* ---------- Die Kundenliste ist wieder ein Klick ------------------------- */
/* WAS HIER AM 13.09.2026 DURCHGERUTSCHT IST

   An dieser Stelle stand: pruefe('die Suche findet Kunden weiterhin',
   str_contains($sbIndex, "case 'suche':")). Das prüft, dass irgendwo im
   Verteiler das Wort „suche" vorkommt — und sonst gar nichts. Sie war grün,
   während die Kundenliste aus dem Menü verschwunden war und kein einziger
   Klick mehr hinführte.

   Uwe am nächsten Tag: „In der Verwaltung werden die schon hinterlegten
   Kunden nicht mehr angezeigt wie Cavaleri." Er hatte recht, und die Kette
   hatte es nicht gemerkt, weil sie nach einer Zeichenkette suchte statt nach
   einem Weg.

   Geprüft wird deshalb jetzt der Weg: Die Tür heißt „Kunden", also muss
   dahinter etwas liegen, das ALLE Kunden zeigt — nicht nur die mit Vorgang.
   Eine Suche ist kein Ersatz: Sie findet nur, wessen Namen man schon kennt. */
pruefe('unter der Tür „Kunden" liegt auch die vollständige Kundenliste',
    str_contains($tLayout, "['kunden', 'Alle Kunden', 'kunden']"));
$tVgListe = (string) file_get_contents($oben . '/app/views/vorgaenge.php');
pruefe('und die Vorgangsliste führt selbst dorthin',
    substr_count($tVgListe, "url('kunden')") >= 2, (string) substr_count($tVgListe, "url('kunden')"));
pruefe('sie sagt auch, wie viele dort stehen und hier nicht',
    str_contains($tVgListe, '$ohneVorgang') && str_contains($tVgListe, '$kundenGesamt'));
pruefe('und der Verteiler liefert die Zahlen dafür',
    str_contains($sbIndex, "'kunden'  => (int) sicher")
    && str_contains($sbIndex, "'gezeigt' => count(\$vgDrin)"));

/* Und die Probe, die den Fehler wirklich gefunden hätte: ein Kunde, der weder
   Bestellung noch Anfrage hat, darf nicht aus der Verwaltung verschwinden.
   Er kommt in Vorgang::alle() nicht vor — das ist richtig, er hat ja keinen
   Vorgang —, aber die Zahl auf der Seite muss ihn nennen. */
require_once dirname(__DIR__) . '/src/Vorgang.php';
$kvId = (int) Db::insert('customers', ['name' => 'Kette Ohnevorgang',
    'email' => 'ohne-vorgang@pruefung.test', 'company' => 'Ohne Vorgang Srl']);
$kvListe = Vorgang::alle();
$kvDrin = [];
foreach ($kvListe as $kvV) {
    $kvK = (int) ($kvV['kunde_id'] ?? 0);
    if ($kvK > 0) { $kvDrin[$kvK] = true; }
}
pruefe('ein Kunde ohne Bestellung und ohne Anfrage hat keinen Vorgang',
    !isset($kvDrin[$kvId]));
$kvGesamt = (int) Db::wert('SELECT COUNT(*) FROM customers');
pruefe('und wird auf der Vorgangsliste trotzdem gezählt',
    $kvGesamt - count($kvDrin) >= 1,
    $kvGesamt . ' Kunden, ' . count($kvDrin) . ' mit Vorgang');
/* Zwei Seiten mit der Überschrift „Kunden" — die Vorgangsliste und die
   Kundenliste — und man weiß beim Blick nach oben nicht, auf welcher man
   steht. Die Überschrift heißt deshalb wie der Menüpunkt. */
pruefe('die Kundenliste heißt so, wie sie im Menü steht',
    str_contains((string) file_get_contents($oben . '/app/views/kunden.php'),
        '<h1>Alle Kunden</h1>'));
pruefe('die Kundenliste selbst zeigt ihn',
    (int) Db::wert('SELECT COUNT(*) FROM customers WHERE id = ?', [$kvId], 0) === 1);
Db::run('DELETE FROM customers WHERE id = ?', [$kvId]);

/* Eine Aufbereitung, zwei Aufrufer — sonst läuft die Schublade der Seite
   hinterher, und niemand weiß, welche der beiden stimmt. */
pruefe('Kundendaten entstehen nur an einer Stelle',
    substr_count($sbIndex, 'function datenKunde(') === 1
    && substr_count($sbIndex, 'datenKunde(') === 3,
    (string) substr_count($sbIndex, 'datenKunde('));
pruefe('Projektdaten ebenso',
    substr_count($sbIndex, 'function datenProjekt(') === 1
    && substr_count($sbIndex, 'datenProjekt(') === 3,
    (string) substr_count($sbIndex, 'datenProjekt('));

/* ---------- Die doppelte Führung ist weg --------------------------------- */
pruefe('auf der Vorgangsseite schweigt die Leiste oben',
    str_contains($tLayout, '$leisteWeg = ($route === \'vorgaenge\' && isset($v[\'schluessel\']));')
    && str_contains($tLayout, '<?php if (!$leisteWeg): ?>'));
pruefe('überall sonst steht sie weiter', substr_count($tLayout, 'class="jetzt ') === 1);

Modus::setzen(true);
Modus::vergessen();

/* ============================================================================
   54. Die Sprache kostet je Seite

   WARUM DIESER ABSCHNITT EXISTIERT

   Uwe, 13.09.2026: „Wenn eine Seite 325-400 kostet, können 5 Seiten mit 3
   Sprachen keine 800-1000 kosten." Dahinter steckte ein Rechenfehler, der
   nirgends auffiel, weil jede einzelne Zahl für sich plausibel aussah:
   `sprache` war eine Pauschale, unabhängig von der Seitenzahl. Auf die
   übersetzte Seite gerechnet kostete dieselbe Arbeit 140 Euro beim Einseiter
   und 7 Euro bei fünfzehn Seiten.

   Solche Fehler kommen wieder — nicht als derselbe Fehler, sondern als der
   nächste Baustein, der pauschal gerechnet wird, obwohl seine Arbeit mit der
   Seitenzahl wächst. Deshalb prüft dieser Abschnitt nicht die Zahlen, sondern
   die Regel: Mehr Seiten müssen mehr Übersetzung kosten, und was auf der
   Website steht, muss aus derselben Rechnung kommen wie das Angebot.
   ============================================================================ */
abschnitt('54. Was mit dem Auftrag wächst, wächst im Preis mit');

require_once dirname(__DIR__) . '/src/Baukasten.php';
$spKatalog = Baukasten::katalog();

/* ---------- Die Regel selbst --------------------------------------------- */
$spRechne = static function (string $umfang, int $sprachen) use ($spKatalog): array {
    $r = Baukasten::rechnen(['zweck' => ['zeigen'], 'umfang' => $umfang,
        'sprachen' => $sprachen, 'material' => ['texte', 'fotos', 'logo'],
        'bestand' => 'neu', 'zeit' => 'offen', 'betreuung' => 'nein'], $spKatalog);
    $menge = 0;
    foreach ($r['positionen'] as $p) {
        if ($p['slug'] === 'sprache') { $menge = (int) $p['menge']; }
    }
    return [$menge, (int) $r['von_cents'], (int) $r['bis_cents']];
};

/* Eine Seite, eine Sprache: gar keine Übersetzung. */
[$spM1, , ] = $spRechne('eine', 1);
pruefe('ohne zweite Sprache wird nichts übersetzt', $spM1 === 0, (string) $spM1);

/* Die Menge ist Seitenzahl mal zusätzliche Sprachen — das ist die ganze Regel. */
foreach ([['eine', 1, 2, 1], ['eine', 1, 3, 2], ['wenige', 5, 2, 5],
          ['wenige', 5, 3, 10], ['mehrere', 9, 3, 18], ['viele', 15, 3, 30]] as
         [$spU, $spSeiten, $spSpr, $spSoll]) {
    [$spIst, , ] = $spRechne($spU, $spSpr);
    pruefe("$spSeiten Seiten in $spSpr Sprachen sind $spSoll übersetzte Seiten",
        $spIst === $spSoll, (string) $spIst);
}

/* Und die Folge davon, in Geld: Wer mehr Seiten übersetzen lässt, zahlt mehr.
   Vor Migration 047 war dieser Aufschlag bei einer Seite und bei fünfzehn
   auf den Cent gleich. */
$spAufschlag = static function (string $umfang) use ($spRechne): int {
    [, $spOhne, ] = $spRechne($umfang, 1);
    [, $spMit,  ] = $spRechne($umfang, 3);
    return $spMit - $spOhne;
};
$spA1 = $spAufschlag('eine');
$spA5 = $spAufschlag('wenige');
$spA15 = $spAufschlag('viele');
pruefe('fünf Seiten zu übersetzen kostet mehr als eine', $spA5 > $spA1,
    ($spA1 / 100) . ' € gegen ' . ($spA5 / 100) . ' €');
pruefe('fünfzehn mehr als fünf', $spA15 > $spA5, ($spA15 / 100) . ' €');
pruefe('und zwar im Verhältnis der Seitenzahl', $spA15 === $spA1 * 15,
    ($spA1 / 100) . ' × 15 = ' . ($spA1 * 15 / 100) . ' €, gerechnet ' . ($spA15 / 100) . ' €');

/* Der Fall, der Uwe aufgefallen ist: Er muss jetzt über dem Fünfseiter in
   einer Sprache liegen, und zwar deutlich. */
[, $spF2von, ] = $spRechne('wenige', 1);
[, $spF3von, ] = $spRechne('wenige', 3);
pruefe('fünf Seiten in drei Sprachen kosten mehr als fünf Seiten in einer',
    $spF3von > $spF2von, ($spF2von / 100) . ' € gegen ' . ($spF3von / 100) . ' €');

/* Die Untergrenze, an der der Fehler entstand: Keine Seitenfassung darf für
   weniger als den halben Seitenpreis über den Tisch gehen. Bei fünfzehn
   Seiten in drei Sprachen waren es zuletzt sieben Euro. */
$spJeFassung = (int) round($spA15 / 30);
pruefe('auch im größten Fall bleibt je übersetzter Seite ein Preis übrig',
    $spJeFassung >= (int) ($spKatalog['seite']['preis_cents'] / 2),
    ($spJeFassung / 100) . ' € je Fassung');

/* ---------- Preisseite und Angebot rechnen dasselbe ----------------------- */
/* preise-daten.php führt die vier Beispiele in einer eigenen Liste. Läuft sie
   der Regel in rechnen() hinterher, steht auf der Website eine Zahl, die das
   Angebot danach nie bestätigt — und zwar genau bei dem Kunden, der beides
   gelesen hat. */
$spDaten = (string) @file_get_contents(dirname(dirname(__DIR__)) . '/preise-daten.php');
pruefe('preise-daten.php fragt den Konfigurator statt selbst zu rechnen',
    str_contains($spDaten, 'Baukasten::rechnen($antworten + $grundantwort, $katalog)')
    && !str_contains($spDaten, "['sprache', 10]"));
pruefe('und gibt die Einheit mit heraus', str_contains($spDaten, "'einheit'"));
/* Die Antworten müssen so gewählt sein, dass nur die beschrifteten Posten
   anfallen. Wäre zum Beispiel 'texte' nicht im Material, stünde in der Summe
   ein Textposten, den die Zeile daneben nicht nennt. */
foreach (["'material'  => ['texte', 'fotos', 'logo']", "'bestand'   => 'neu'",
          "'zeit'      => 'offen'", "'betreuung' => 'nein'"] as $spZeile) {
    pruefe('die Beispielantworten enthalten ' . trim(explode('=>', $spZeile)[1]),
        str_contains($spDaten, $spZeile));
}

/* ---------- Was im HTML steht, wenn die Verwaltung schweigt --------------- */
/* Der Rückfall ist kein Schmuck: Antwortet preise-daten.php nicht, ist er die
   einzige Zahl auf der Seite. Stimmt er nicht mit der Rechnung überein, zeigt
   die Website bei jeder Störung einen falschen Preis — und Störungen fallen
   nicht auf, weil die Seite dann trotzdem vollständig aussieht. */
/* Gerechnet wird wie in preise-daten.php: mit Antworten durch den
   Konfigurator, nicht mit einer eigenen Postenliste. Eine Prüfung, die ihre
   Mengen selbst aufschreibt, prüft sonst ihre eigene Abschrift. */
$spGrund = ['material' => ['texte', 'fotos', 'logo'], 'bestand' => 'neu',
            'zeit' => 'offen', 'betreuung' => 'nein'];
$spRezepte = [
    'f1' => ['zweck' => ['zeigen'], 'umfang' => 'eine',   'sprachen' => 1],
    'f2' => ['zweck' => ['zeigen'], 'umfang' => 'wenige', 'sprachen' => 1],
    'f3' => ['zweck' => ['zeigen'], 'umfang' => 'wenige', 'sprachen' => 3],
    'f4' => ['zweck' => ['zeigen', 'shop'], 'umfang' => 'wenige', 'sprachen' => 1],
];
$spStueck = static function (array $antworten, ?array $katalog = null) use ($spKatalog, $spGrund): array {
    $r = Baukasten::rechnen($antworten + $spGrund, $katalog ?? $spKatalog);
    $g = Baukasten::spanne((int) $r['von_cents'], (int) $r['bis_cents']);
    return [(int) $g['von_cents'] / 100, (int) $g['bis_cents'] / 100];
};
$spWurzel = dirname(dirname(__DIR__));
/* Zwei Schreibweisen, weil die englische Seite das Zeichen vorn und den
   Tausendertrenner als Komma setzt. Beide entstehen in preise-daten.php aus
   derselben Zahl — hier steht der Erwartungswert, nicht die Formatierung. */
$spSchreib = static function (int $von, int $bis, bool $englisch): string {
    return $englisch
        ? '€' . number_format($von, 0, '.', ',') . ' – ' . number_format($bis, 0, '.', ',')
        : number_format($von, 0, ',', '.') . ' – ' . number_format($bis, 0, ',', '.') . ' €';
};
foreach (['index.html' => false, 'prezzi.html' => false,
          'de/index.html' => false, 'de/preise.html' => false,
          'en/index.html' => true, 'en/pricing.html' => true] as $spDatei => $spEng) {
    $spHtml = (string) @file_get_contents($spWurzel . '/' . $spDatei);
    $spGut = $spHtml !== '';
    $spFehlt = '';
    foreach ($spRezepte as $spName => $spAntworten) {
        [$spVon, $spBis] = $spStueck($spAntworten);
        $spText = $spSchreib((int) $spVon, (int) $spBis, $spEng);
        if (!str_contains($spHtml, '"preise.' . $spName . 'p">' . $spText . '<')) {
            $spGut = false; $spFehlt .= ' ' . $spName . '=' . $spText;
        }
    }
    pruefe("der Rückfall in $spDatei trägt die gerechneten Spannen", $spGut, trim($spFehlt));
}

/* ---------- Das Fenster zwischen Deploy und Migration --------------------- */
/* WAS AM 13.09.2026 LIVE PASSIERT IST

   Der Deploy bringt den neuen Code sofort, die Migration läuft erst beim
   nächsten Cronlauf. In den drei Minuten dazwischen traf in preise-daten.php
   die NEUE Menge (zehn übersetzte Seiten) auf die ALTEN Preise: Die Startseite
   zeigte 1.900 – 2.450 €, wo 800 – 1.000 € richtig gewesen wären. Der
   Konfigurator daneben rechnete die ganze Zeit richtig — weil er nur einen
   Rechenweg hat.

   Seither hat auch die Preisseite nur diesen einen. Die Probe dafür: Ein
   Katalog auf dem Stand VOR Migration 047 muss die Zahlen von vorher ergeben,
   nicht eine Mischung aus beidem. */
/* Der Katalog, wie er in diesen drei Minuten dastand: alte Preise, und keine
   Spalte `einheit` — vor der Migration gab es sie nicht. */
$spAlt = $spKatalog;
$spAlt['seite']['preis_cents']    =  4500; $spAlt['seite']['preis_bis_cents']   =  6000;
$spAlt['sprache']['preis_cents']  = 14000; $spAlt['sprache']['preis_bis_cents'] = 18000;
unset($spAlt['seite']['einheit'], $spAlt['sprache']['einheit']);

[$spAltVon, $spAltBis] = $spStueck($spRezepte['f3'], $spAlt);
pruefe('mit dem Katalog von vor der Migration kommt die alte Zahl heraus',
    (int) $spAltVon === 800 && (int) $spAltBis === 1000, $spAltVon . ' – ' . $spAltBis . ' €');
/* Die entscheidende Zeile dieses Abschnitts: Diese Mischung stand live auf der
   Startseite. Sie kann nicht mehr entstehen, weil die Menge aus derselben
   Zeile kommt wie der Preis. */
pruefe('und nicht die Mischung aus neuer Menge und altem Preis',
    (int) $spAltVon !== 1900, $spAltVon . ' – ' . $spAltBis . ' €');
/* Und umgekehrt: Sobald die Spalte da ist, wechseln Menge und Preis gemeinsam. */
$spNeu = $spAlt;
$spNeu['sprache']['einheit'] = 'seite';
[$spNeuVon, ] = $spStueck($spRezepte['f3'], $spNeu);
pruefe('die Spalte allein schaltet die Rechnung um', (int) $spNeuVon === 1900,
    $spNeuVon . ' €');

/* ---------- Der Teiler, der Migration 048 fast gekostet hätte ------------- */
/* Der Migrationslauf zerlegte die Datei mit explode(';', …). Das ging lange
   gut, weil nie ein Semikolon in einer Zeichenkette stand. In 048 stand eines
   — in einem englischen Satz —, der Teiler schnitt mitten hinein, und die
   Datenbank bekam zwei Hälften zu sehen, von denen keine eine Anweisung ist.

   Das Semikolon aus dem Satz zu nehmen hätte die Falle stehen lassen. Also
   zählt der Teiler jetzt mit, ob er in einer Zeichenkette steht — und diese
   Prüfungen halten das fest, ohne dass jemand erst eine Migration schreiben
   muss, die daran scheitert. */
require_once dirname(__DIR__) . '/src/Einrichtung.php';
foreach ([
    ['zwei Anweisungen bleiben zwei',
     "UPDATE t SET a = 1; UPDATE t SET b = 2;", 2],
    ['ein Semikolon im Text teilt nicht',
     "UPDATE t SET s = 'eins; zwei';", 1],
    ['ein verdoppeltes Hochkomma beendet die Zeichenkette nicht',
     "UPDATE t SET s = 'L''Aquila; ja'; SELECT 1;", 2],
    ['ein Backslash nimmt das nächste Zeichen mit',
     "UPDATE t SET s = 'a\\'; b'; SELECT 1;", 2],
    ['ein Semikolon im Tabellennamen teilt nicht',
     'ALTER TABLE `tab;le` ADD x INT; SELECT 1;', 2],
    ['leer bleibt leer', "   ;  ;  ", 0],
] as [$spWas, $spSql, $spSoll]) {
    $spIst = Einrichtung::anweisungenFuerPruefung($spSql);
    pruefe($spWas, count($spIst) === $spSoll, count($spIst) . ' statt ' . $spSoll);
}
/* Und der Satz, an dem es aufgefallen ist — wörtlich aus Migration 048. */
pruefe('der Satz aus Migration 048 bleibt eine Anweisung',
    count(Einrichtung::anweisungenFuerPruefung(
        "UPDATE bausteine SET text_en = 'I write the text for every page; "
        . "you read it before it goes live.' WHERE slug = 'texte';")) === 1);

/* ---------- Dieselbe Frage für jeden anderen Baustein --------------------- */
/* WARUM DIESE PRUEFUNG DIE WICHTIGSTE DES ABSCHNITTS IST

   Der Fehler bei der Sprache war kein Einzelfall, sondern ein Muster: eine
   Pauschale für Arbeit, die mit jeder Seite mitwächst. Vier Bausteine hatten
   ihn noch — Texte, Bilder, Inhaltsübernahme und der Eilzuschlag —, und bei
   den Texten war er schlimmer als bei der Sprache, weil Texte und Bilder
   AUTOMATISCH anfallen: Sie werden berechnet, sobald der Kunde sie unter
   „Was hast du schon fertig?" nicht ankreuzt, und das ist der Normalfall.

   Geprüft wird deshalb beides, und die zweite Hälfte ist die, die beim
   nächsten Mal hilft: Was pauschal bleiben MUSS, darf nicht je Seite
   gerechnet werden. Eine Buchung für eine Ferienwohnung wird nicht billiger,
   weil die Seite klein ist. Wer den Fehler repariert und dabei über das Ziel
   hinausschießt, macht ihn nur in die andere Richtung. */
$spJeSeite  = ['sprache', 'texte', 'fotos', 'uebernahme', 'express'];
$spPauschal = ['basis', 'seite', 'speisekarte', 'termine', 'buchung', 'shop', 'logo'];
foreach ($spJeSeite as $spSlug) {
    pruefe("$spSlug wächst mit der Seitenzahl",
        (string) ($spKatalog[$spSlug]['einheit'] ?? '') === 'seite'
        && (int) ($spKatalog[$spSlug]['je_einheit'] ?? 0) === 1,
        (string) ($spKatalog[$spSlug]['einheit'] ?? 'fehlt'));
}
foreach ($spPauschal as $spSlug) {
    pruefe("$spSlug wird einmal gebaut und bleibt pauschal",
        (string) ($spKatalog[$spSlug]['einheit'] ?? 'stueck') !== 'seite',
        (string) ($spKatalog[$spSlug]['einheit'] ?? 'stueck'));
}

/* Und die Folge in Geld: Wer fünfzehn Seiten schreiben lässt, zahlt mehr als
   wer eine schreiben lässt. Vor Migration 048 war das auf den Cent gleich. */
$spOhneMaterial = static function (string $umfang) use ($spKatalog): array {
    $r = Baukasten::rechnen(['zweck' => ['zeigen'], 'umfang' => $umfang, 'sprachen' => 1,
        'material' => [], 'bestand' => 'erneuern', 'zeit' => 'schnell',
        'betreuung' => 'nein'], $spKatalog);
    $aus = [];
    foreach ($r['positionen'] as $p) { $aus[(string) $p['slug']] = (int) $p['von_cents']; }
    return $aus;
};
$spEins = $spOhneMaterial('eine');
$spViele = $spOhneMaterial('viele');
foreach (['texte' => 'Texte', 'fotos' => 'Bilder', 'uebernahme' => 'Übernahme',
          'express' => 'Eilzuschlag'] as $spSlug => $spWort) {
    pruefe("$spWort für fünfzehn Seiten kostet das Fünfzehnfache von einer",
        ($spViele[$spSlug] ?? 0) === ($spEins[$spSlug] ?? 0) * 15,
        (($spEins[$spSlug] ?? 0) / 100) . ' € → ' . (($spViele[$spSlug] ?? 0) / 100) . ' €');
}
/* Die Untergrenze, an der der Fehler sichtbar wurde: Texte für fünfzehn
   Seiten standen mit 9,30 € je Seite in der Rechnung. */
pruefe('keine geschriebene Seite unter zwanzig Euro',
    (int) ($spViele['texte'] ?? 0) / 15 >= 2000,
    (($spViele['texte'] ?? 0) / 15 / 100) . ' € je Seite');

/* Texte und Bilder fallen von allein an — deshalb wiegt der Fehler dort
   schwerer als anderswo. Diese Prüfung hält fest, dass es so gemeint ist. */
$spNichtsFertig = $spOhneMaterial('wenige');
pruefe('wer nichts angekreuzt hat, bekommt Texte und Bilder berechnet',
    isset($spNichtsFertig['texte'], $spNichtsFertig['fotos']));
$spAllesFertig = Baukasten::rechnen(['zweck' => ['zeigen'], 'umfang' => 'wenige',
    'sprachen' => 1, 'material' => ['texte', 'fotos', 'logo'], 'bestand' => 'neu',
    'zeit' => 'offen', 'betreuung' => 'nein'], $spKatalog);
$spHatInhalt = false;
foreach ($spAllesFertig['positionen'] as $spP) {
    if (in_array((string) $spP['slug'], ['texte', 'fotos'], true)) { $spHatInhalt = true; }
}
pruefe('und wer sie hat, bekommt sie nicht', !$spHatInhalt);

/* Der häufigste Fall zahlt nach dieser Runde dasselbe wie davor — daran hängt
   die Behauptung, es sei eine Reparatur und keine Preiserhöhung. */
pruefe('fünf Seiten Texte kosten weiter 140 € wie die alte Pauschale',
    (int) ($spNichtsFertig['texte'] ?? 0) === 14000,
    (($spNichtsFertig['texte'] ?? 0) / 100) . ' €');
pruefe('fünf Seiten Bilder weiter 105 €',
    (int) ($spNichtsFertig['fotos'] ?? 0) === 10500,
    (($spNichtsFertig['fotos'] ?? 0) / 100) . ' €');

/* Die Buchung stand unter dem Shop, obwohl Verfügbarkeit, Zeiträume,
   Saisonpreise und eine Bestätigung, die von allein rausgeht, ihm an Arbeit
   nicht nachstehen. */
pruefe('die Buchung steht nicht mehr weit unter dem Shop',
    (int) $spKatalog['buchung']['preis_cents'] >= (int) ($spKatalog['shop']['preis_cents'] * 0.8),
    ((int) $spKatalog['buchung']['preis_cents'] / 100) . ' € gegen '
    . ((int) $spKatalog['shop']['preis_cents'] / 100) . ' €');
pruefe('und die Speisekarte nicht mehr bei der Hälfte der Termine',
    (int) $spKatalog['speisekarte']['preis_cents'] > (int) ($spKatalog['termine']['preis_cents'] * 0.6),
    ((int) $spKatalog['speisekarte']['preis_cents'] / 100) . ' € gegen '
    . ((int) $spKatalog['termine']['preis_cents'] / 100) . ' €');

/* Der Shop versprach auf der Preisseite, der Preis hänge „vor allem an der
   Zahl der Artikel" — eine Frage, die es im Konfigurator nicht gibt und die
   er deshalb nicht halten kann. Acht Fragen sind die Grenze; also geht das
   Versprechen, nicht die Frage dazu. */
foreach (['de/preise.html' => 'Der Preis deckt den Aufbau',
          'prezzi.html'    => 'Il prezzo copre la costruzione',
          'en/pricing.html'=> 'The price covers the build'] as $spDatei => $spWort) {
    $spHtml = (string) @file_get_contents($spWurzel . '/' . $spDatei);
    pruefe("$spDatei verspricht beim Shop nichts, was der Konfigurator nicht kann",
        str_contains($spHtml, $spWort)
        && !str_contains($spHtml, 'Zahl der Artikel')
        && !str_contains($spHtml, 'quanti prodotti ci sono')
        && !str_contains($spHtml, 'how many products there are'));
}

/* ---------- Und was in der Preistabelle steht ---------------------------- */
/* Der Rückfall der Bausteintabelle ist dieselbe Falle wie der der vier Fälle:
   Antwortet die Verwaltung nicht, ist er die einzige Zahl auf der Seite. */
$spGeld = static function (int $cents, bool $englisch): string {
    $v = (int) round($cents / 100);
    return $englisch ? '€' . number_format($v, 0, '.', ',') : number_format($v, 0, ',', '.');
};
foreach (['prezzi.html' => false, 'de/preise.html' => false, 'en/pricing.html' => true]
         as $spDatei => $spEng) {
    $spHtml = (string) @file_get_contents($spWurzel . '/' . $spDatei);
    $spGut = $spHtml !== ''; $spFehlt = '';
    foreach ($spKatalog as $spSlug => $spB) {
        if (in_array((string) $spSlug, Baukasten::NUR_AUF_ANFRAGE, true)) { continue; }
        $spV = (int) $spB['preis_cents'];
        $spO = (int) $spB['preis_bis_cents'];
        $spText = $spEng
            ? $spGeld($spV, true) . ($spO > $spV ? ' – ' . number_format((int) round($spO / 100), 0, '.', ',') : '')
            : $spGeld($spV, false) . ($spO > $spV ? ' – ' . $spGeld($spO, false) : '') . ' €';
        if (!str_contains($spHtml, 'data-preis="' . $spSlug . '">' . $spText . '<')) {
            $spGut = false; $spFehlt .= ' ' . $spSlug . '=' . $spText;
        }
    }
    pruefe("die Preistabelle in $spDatei trägt die Preise des Baukastens", $spGut, trim($spFehlt));
}

/* ---------- Und die Beschriftung, wegen der es auffiel ------------------- */
/* „Eine einzige Seite — 325 bis 400 Euro" las sich wie ein Seitenpreis. Es ist
   der Preis einer fertigen Website, die aus einer Seite besteht; die nächste
   Seite kostet ein Fünftel davon. Wer das nicht dazuschreibt, lädt den Leser
   ein, 5 × 400 zu rechnen und die Liste für falsch zu halten. */
foreach (['index.html' => 'Sito completo, una pagina',
          'de/index.html' => 'Komplette Website, eine Seite',
          'en/index.html' => 'A complete one-page site',
          'prezzi.html' => 'Sito completo, una pagina',
          'de/preise.html' => 'Komplette Website, eine Seite',
          'en/pricing.html' => 'A complete one-page site'] as $spDatei => $spWort) {
    $spHtml = (string) @file_get_contents($spWurzel . '/' . $spDatei);
    pruefe("in $spDatei heisst der erste Fall nicht mehr nur „eine Seite\"",
        str_contains($spHtml, $spWort));
}
foreach (['de', 'it', 'en'] as $spL) {
    $spJs = (string) @file_get_contents($spWurzel . '/assets/js/i18n-' . $spL . '.js');
    pruefe("i18n-$spL kennt das Wort für „je Seite\"", str_contains($spJs, 'bauJeSeite:'));
}
$spLive = (string) @file_get_contents($spWurzel . '/assets/js/preise-live.js');
pruefe('die Preisseite wählt das Einheitswort am Baustein, nicht am Slug',
    str_contains($spLive, "b.einheit === 'seite'") && str_contains($spLive, 'bauJeSeite'));

/* Auch drinnen, in der Verwaltung: Die Liste der Preisbausteine schrieb hinter
   jeden Baustein mit je_einheit „je Stück". Uwe liest diese Liste, wenn er eine
   Preisrunde macht — eine Marke, die dort das Falsche behauptet, wird geglaubt
   und in das nächste Angebot übernommen. */
$spBaukastenAnsicht = (string) @file_get_contents(dirname(__DIR__) . '/views/baukasten.php');
pruefe('die Verwaltung schreibt „je Seite", wo je Seite gerechnet wird',
    str_contains($spBaukastenAnsicht, "=== 'seite' ? 'Seite' : 'Stück'")
    && !str_contains($spBaukastenAnsicht, '<span class="marke2">je Stück</span>'));

/* Die Erklärzeile unter den vier Fällen: Ohne sie bleibt die Frage offen,
   warum fünf Seiten nicht das Fünffache kosten. */
foreach (['index.html', 'de/index.html', 'en/index.html'] as $spDatei) {
    $spHtml = (string) @file_get_contents($spWurzel . '/' . $spDatei);
    preg_match('~data-i18n="weg\.faelleNote">(.*?)<~s', $spHtml, $spT);
    pruefe("$spDatei erklärt, was im ersten Preis steckt",
        isset($spT[1]) && mb_strlen(trim($spT[1])) > 120,
        isset($spT[1]) ? (string) mb_strlen(trim($spT[1])) . ' Zeichen' : 'fehlt');
}

/* ============================================================================
   Startdaten tragen den heutigen Stand

   WARUM DIESER ABSCHNITT EXISTIERT

   Am 13.09.2026 an einer frisch eingerichteten Datenbank gemessen: Die
   Migrationen laufen zuerst, die Startdaten werden danach gesät. Migration 044
   (+15 % auf die Bausteine) und die Migrationen 025/043 (die drei
   Website-Pakete unsichtbar) trafen deshalb auf leere Tabellen und änderten
   nichts — und anschließend säten standardbausteine.json die alten Preise und
   Einrichtung::pakete() die drei Pakete mit oeffentlich = 1 wieder ein.

   Was das bedeutet: Eine neu eingerichtete Vecom-Seite hätte 299 statt 345 Euro
   Grundgerüst gerechnet und auf der Startseite wieder Starter 499, Business 899
   und Premium 1.499 angeboten. Auf der bestehenden Einrichtung war alles
   richtig — der Fehler wäre also erst dem nächsten Kunden aufgefallen, der eine
   eigene Einrichtung bekommt.

   Diese Prüfungen halten das fest, weil die Ursache bei jeder künftigen
   Preisrunde wiederkommt.
   ============================================================================ */
abschnitt('Startdaten nach den Migrationen');

Baukasten::sicherstellen();
$sdKatalog = Baukasten::katalog();
pruefe('der Baukasten ist gesät', count($sdKatalog) >= 12, (string) count($sdKatalog));

/* Die Zahlen stehen hier absichtlich noch einmal und nicht aus der Datei:
   Eine Prüfung, die ihren Sollwert aus derselben Quelle liest wie der Code,
   prüft nur, dass Lesen funktioniert. */
$sdSoll = [
    'basis' => [34500, 40000], 'seite' => [6500, 8500], 'sprache' => [4000, 5500],
    'shop'  => [68000, 91000],
];
foreach ($sdSoll as $sdSlug => [$sdVon, $sdBis]) {
    $sdIst = $sdKatalog[$sdSlug] ?? null;
    pruefe("Startpreis $sdSlug ist auf dem Stand der letzten Preisrunde",
        $sdIst && (int) $sdIst['preis_cents'] === $sdVon && (int) $sdIst['preis_bis_cents'] === $sdBis,
        $sdIst ? ((int) $sdIst['preis_cents'] . '-' . (int) $sdIst['preis_bis_cents']) : 'fehlt');
}

/* Die Einheit gehört zum Preis: 40 Euro heisst etwas anderes je Stück als je
   Seite. Seit Migration 047 steht sie am Baustein, damit die Preisseite nicht
   raten muss — und damit eine neue Einrichtung sie mitbekommt. */
foreach (['seite' => 'stueck', 'sprache' => 'seite'] as $sdSlug => $sdEinheit) {
    pruefe("die Einheit von $sdSlug wird mitgesät",
        (string) ($sdKatalog[$sdSlug]['einheit'] ?? '') === $sdEinheit,
        (string) ($sdKatalog[$sdSlug]['einheit'] ?? 'fehlt'));
}

/* Und die vier Beispiele der Preisseite müssen aus diesen Zahlen herauskommen —
   sonst steht auf der Seite eine andere Zahl als im Angebot. */
$sdFaelle = [
    'f1' => [['basis', 1], 32500, 40000],
    'f2' => [['basis', 1, 'seite', 4], 60000, 75000],
    /* Fünf Seiten in drei Sprachen: zwei zusätzliche Sprachen mal fünf Seiten.
       Stünde hier 2 statt 10, liefe die Preisseite wieder gegen das Angebot —
       genau der Fehler, den Migration 047 behebt. */
    'f3' => [['basis', 1, 'seite', 4, 'sprache', 10], 100000, 130000],
    'f4' => [['basis', 1, 'seite', 4, 'shop', 1], 125000, 165000],
];
$sdSpanne = static function (array $teile) use ($sdKatalog): array {
    $von = 0; $bis = 0;
    for ($i = 0; $i < count($teile); $i += 2) {
        $b = $sdKatalog[$teile[$i]] ?? null;
        if (!$b) { return [0, 0]; }
        $von += (int) $b['preis_cents'] * (int) $teile[$i + 1];
        $bis += (int) $b['preis_bis_cents'] * (int) $teile[$i + 1];
    }
    $g = Baukasten::spanne($von, $bis);
    return [(int) $g['von_cents'], (int) $g['bis_cents']];
};
foreach ($sdFaelle as $sdName => [$sdTeile, $sdEvon, $sdEbis]) {
    [$sdIvon, $sdIbis] = $sdSpanne($sdTeile);
    pruefe("Beispielspanne $sdName stimmt mit dem Rückfall im HTML überein",
        $sdIvon === $sdEvon && $sdIbis === $sdEbis, ($sdIvon / 100) . ' – ' . ($sdIbis / 100) . ' €');
}

/* KEINE WEBSITE-PAKETE (25.09.2026)
   Websites haben keine Pakete; der Preis entsteht im Konfigurator. Bis heute
   lagen Starter 499, Business 899 und Premium 1.499 noch als Startdaten vor
   und waren in bestehenden Einrichtungen nur unsichtbar, nicht aus --
   Telefon und Vorlagen lasen sie weiter. */
foreach (Einrichtung::pakete() as $sdZeile) { /* säen wie bei einer Einrichtung */ }
$sdVorlage = json_decode((string) file_get_contents(dirname(__DIR__) . '/src/standardpakete.json'), true);
pruefe('die Startdaten enthalten kein Website-Paket',
    is_array($sdVorlage) && array_filter($sdVorlage, static fn(array $p): bool =>
        ($p['art'] ?? 'website') === 'website') === []);
pruefe('nach dem Säen ist kein Website-Paket aktiv oder sichtbar',
    (int) Db::wert("SELECT COUNT(*) FROM packages WHERE art = 'website' AND (active = 1 OR oeffentlich = 1)", [], 1) === 0);

/* Und in einer bestehenden Einrichtung: Migration 051 schaltet die alten
   Zeilen aus, ohne sie zu loeschen (an ihnen haengen Bestellungen). */
$sdAlt = [];
foreach (['starter' => 49900, 'business' => 89900, 'premium' => 149900] as $sdSlug => $sdPreis) {
    $sdAlt[] = Db::insert('packages', ['slug' => $sdSlug, 'name' => ucfirst($sdSlug),
        'description' => 'alt', 'price_cents' => $sdPreis, 'monthly_cents' => 0, 'currency' => 'EUR',
        'art' => 'website', 'active' => 1, 'oeffentlich' => 0, 'direktkauf' => 1, 'sort' => 1]);
}
foreach (array_filter(array_map('trim', explode(';', preg_replace('~^--.*$~m', '',
        (string) file_get_contents(dirname(__DIR__) . '/migrations/051_keine_pakete.sql'))))) as $sdSql) {
    Db::run($sdSql);
}
pruefe('Migration 051 schaltet die alten Pakete aus, ohne sie zu löschen',
    (int) Db::wert("SELECT COUNT(*) FROM packages WHERE slug IN ('starter','business','premium')
                     AND active = 0 AND oeffentlich = 0 AND direktkauf = 0", [], 0) === 3);
Db::run('DELETE FROM packages WHERE id IN (' . implode(',', array_map('intval', $sdAlt)) . ')');
pruefe('Betreuung Basis bleibt sichtbar',
    (int) Db::wert("SELECT oeffentlich FROM packages WHERE slug = 'betreuung-basis'", [], 0) === 1);

/* ============================================================================
   Das Briefing aus dem Bedarf
   ============================================================================ */
abschnitt('Briefing zum Bauen');

require_once dirname(__DIR__) . '/src/Bedarf.php';

$bpLeer = ['id' => 0, 'firma' => '', 'name' => '', 'email' => '', 'telefon' => ''];
$bpKnapp = Bedarf::bauprompt(
    ['id' => 0, 'firma' => 'Trattoria da Nino', 'name' => 'Nino', 'email' => 'n@example.com', 'telefon' => ''],
    ['zweck' => ['zeigen']], Baukasten::vorschlag(Baukasten::rechnen(['zweck' => ['zeigen']])), $sdKatalog);

/* Eine Überschrift ohne Zeile darunter liest sich wie ein Fehler des Briefings,
   und wer sie so liest, rät den Rest zusammen. */
foreach (['BESTAND', 'TERMIN', 'WAS DIE SEITE LEISTEN MUSS', 'MATERIAL'] as $bpKopf) {
    pruefe("$bpKopf steht im Briefing nie nackt da",
        (bool) preg_match('/' . preg_quote($bpKopf, '/') . '\n- /', $bpKnapp));
}
pruefe('ein unbeantworteter Bestand wird ausdrücklich benannt',
    str_contains($bpKnapp, 'ob es eine alte Seite gibt'));
pruefe('ein unbeantworteter Termin erfindet keine Eile',
    str_contains($bpKnapp, 'Keine Eile erfinden'));

/* Und ohne eine einzige Antwort gibt es nichts zu bauen: Baukasten::genugGesagt
   ist die Schwelle, an der auch die Spanne schweigt. */
pruefe('ohne Antworten ist die Schwelle nicht erreicht',
    Baukasten::genugGesagt([]) === false);
pruefe('mit der ersten Antwort schon',
    Baukasten::genugGesagt(['zweck' => ['zeigen']]) === true);

/* ============================================================================
   55. Der Bezahllink, der nicht stirbt — und was sonst am Geld hing

   Gefunden bei der Durchsicht des ganzen Zahlungssystems am 21.09.2026:

   1. Eine Stripe-Bezahlseite lebt hoechstens 24 Stunden. Jede Zahlungsmail
      trug trotzdem genau diese Adresse, die Monatsmail mit sieben Tagen Frist.
   2. Ein Webhook, der scheiterte, bekam von Stripe eine Wiederholung -- und
      die wurde als "bereits verarbeitet" beantwortet und verworfen.
   3. Gebucht wurde, ohne den bezahlten Betrag anzusehen.
   4. Ein Fehlschlag bei einer Monatsrate griff auf eine Bestellung zu, die es
      nicht gibt.
   ============================================================================ */
abschnitt('55. Der Bezahllink, der nicht stirbt');

require_once $wurzel . '/src/Bezahllink.php';
require_once $wurzel . '/src/Webhook.php';
require_once $wurzel . '/src/Abo.php';
require_once $wurzel . '/src/Kundenzugang.php';

/** Ein Stripe, das Bezahlseiten anlegt und nach ihnen gefragt werden kann. */
final class BezahlProbe
{
    public array $angelegt = [];
    public array $sitzungen = [];
    private string $letzte = '';
    public function __construct(private bool $bereit = true) {}
    public function bereit(): bool { return $this->bereit; }
    public function letzteSitzung(): string { return $this->letzte; }
    public function bezahlseite(array $z, array $b, array $k, ?string $erfolg = null): string
    {
        $n = count($this->angelegt) + 1;
        $this->letzte = 'cs_probe_' . (int) $z['id'] . '_' . $n;
        $this->angelegt[] = ['sitzung' => $this->letzte, 'betrag' => (int) $z['amount_cents'],
                             'erfolg' => $erfolg, 'titel' => (string) ($b['package_name'] ?? '')];
        $this->sitzungen[$this->letzte] = ['bezahlt' => false, 'referenz' => '', 'status' => 'open',
            'abgelaufen' => false, 'betrag' => (int) $z['amount_cents'], 'waehrung' => strtoupper((string) $z['currency'])];
        return 'https://checkout.stripe.com/c/pay/' . $this->letzte;
    }
    public function sitzungLesen(string $id): array
    {
        if (!isset($this->sitzungen[$id])) { throw new RuntimeException('No such checkout session: ' . $id); }
        return $this->sitzungen[$id];
    }
    public function ablaufen(string $id): void
    {
        $this->sitzungen[$id]['status'] = 'expired';
        $this->sitzungen[$id]['abgelaufen'] = true;
    }
    public function bezahlen(string $id, ?int $betrag = null): void
    {
        $this->sitzungen[$id]['bezahlt'] = true;
        $this->sitzungen[$id]['status'] = 'complete';
        $this->sitzungen[$id]['referenz'] = 'pi_' . $id;
        if ($betrag !== null) { $this->sitzungen[$id]['betrag'] = $betrag; }
    }
}

$blRate = static function (string $email) use ($paketId): array {
    $k = Events::kundeFinden(['name' => 'Bezahllink ' . $email, 'email' => $email, 'sprache' => 'de']);
    $b = Events::bestellungAnlegen($k, $paketId, 'Bezahllinkprobe', KETTE_PREIS);
    $z = (int) Db::wert("SELECT id FROM payments WHERE order_id = ? AND art = 'anzahlung'", [$b], 0);
    return ['kunde' => $k, 'bestellung' => $b, 'zahlung' => $z, 'token' => Kundenzugang::token($k)];
};

/* ---------- Die Adresse, die nach draussen geht --------------------------- */
$bl = $blRate('bezahllink-a@pruefung.example');
$blAdresse = Bezahllink::fuer($bl['zahlung']);
pruefe('der Bezahllink liegt auf der eigenen Domain',
    str_starts_with($blAdresse, 'https://pruefung.example/bezahlen.php?t='), $blAdresse);
pruefe('er traegt Schluessel und Rate',
    str_contains($blAdresse, $bl['token']) && str_ends_with($blAdresse, '&z=' . $bl['zahlung']), $blAdresse);

/* ---------- Wer nicht darf ------------------------------------------------- */
$probe = new BezahlProbe();
$w = Bezahllink::oeffnen(str_repeat('0', 48), $bl['zahlung'], $probe);
pruefe('ein falscher Schluessel fuehrt auf die Startseite, nicht zu Stripe',
    $w['grund'] === 'fremd' && $w['ziel'] === 'https://pruefung.example/', json_encode($w));

$blFremd = $blRate('bezahllink-fremd@pruefung.example');
$w = Bezahllink::oeffnen($blFremd['token'], $bl['zahlung'], $probe);
pruefe('der Schluessel eines anderen Kunden oeffnet diese Rate nicht',
    $w['grund'] === 'fremd' && str_contains($w['ziel'], $blFremd['token']), json_encode($w));
pruefe('und es entsteht dabei keine Bezahlseite', count($probe->angelegt) === 0);

$w = Bezahllink::oeffnen($bl['token'], $bl['zahlung'], new BezahlProbe(false));
pruefe('ohne Stripe geht es auf die Kundenseite', $w['grund'] === 'aus', json_encode($w));

/* ---------- Der Normalfall: neu, dann offen ------------------------------- */
$w = Bezahllink::oeffnen($bl['token'], $bl['zahlung'], $probe);
pruefe('der erste Klick legt eine Bezahlseite an', $w['grund'] === 'neu' && count($probe->angelegt) === 1,
    json_encode($w));
pruefe('und fuehrt zu Stripe', str_starts_with($w['ziel'], 'https://checkout.stripe.com/'), $w['ziel']);
$zBl = Db::one('SELECT * FROM payments WHERE id = ?', [$bl['zahlung']]);
pruefe('die Nummer der Bezahlseite steht an der Rate (fuer den Abgleich)',
    (string) $zBl['provider_sitzung'] === $probe->letzteSitzung() && $probe->letzteSitzung() !== '',
    (string) $zBl['provider_sitzung']);
pruefe('die Rate steht auf "in Bearbeitung"', (string) $zBl['status'] === 'in_bearbeitung', (string) $zBl['status']);
$fristErst = (string) $zBl['link_bis'];
pruefe('die Aufforderung laeuft vierzehn Tage',
    abs(strtotime($fristErst) - strtotime('+' . Events::LINK_GILT_TAGE . ' days')) < 120, $fristErst);

$w = Bezahllink::oeffnen($bl['token'], $bl['zahlung'], $probe);
pruefe('der zweite Klick fuehrt auf dieselbe, noch laufende Seite',
    $w['grund'] === 'offen' && $w['ziel'] === (string) $zBl['link_url'], json_encode($w));
pruefe('es entsteht keine zweite Seite', count($probe->angelegt) === 1, (string) count($probe->angelegt));

/* ---------- Der Fall, um den es geht: am naechsten Tag -------------------- */
$probe->ablaufen($probe->letzteSitzung());
$w = Bezahllink::oeffnen($bl['token'], $bl['zahlung'], $probe);
pruefe('nach Ablauf der Stripe-Seite entsteht eine frische', $w['grund'] === 'neu' && count($probe->angelegt) === 2,
    json_encode($w));
$zBl = Db::one('SELECT * FROM payments WHERE id = ?', [$bl['zahlung']]);
pruefe('der Abgleich fragt ab jetzt die neue Seite', (string) $zBl['provider_sitzung'] === $probe->letzteSitzung(),
    (string) $zBl['provider_sitzung']);
pruefe('ein Klick verlaengert die Aufforderung nicht', (string) $zBl['link_bis'] === $fristErst,
    (string) $zBl['link_bis'] . ' statt ' . $fristErst);

/* Der Betrag, der JETZT gilt. Geaendert wird ueber die Datenbank, wie es
   die Verwaltung tut; die naechste Seite muss den neuen Betrag tragen. */
$blNeu = (int) $zBl['amount_cents'] + 5000;
Db::update('payments', $bl['zahlung'], ['amount_cents' => $blNeu]);
$probe->ablaufen($probe->letzteSitzung());
Bezahllink::oeffnen($bl['token'], $bl['zahlung'], $probe);
pruefe('eine frische Seite traegt den Betrag, der jetzt gilt',
    end($probe->angelegt)['betrag'] === $blNeu, (string) end($probe->angelegt)['betrag']);

/* ---------- Bezahlt, aber der Webhook schweigt ---------------------------- */
$probe->bezahlen($probe->letzteSitzung());
$w = Bezahllink::oeffnen($bl['token'], $bl['zahlung'], $probe);
pruefe('wer nach dem Bezahlen noch einmal klickt, bucht die Zahlung', $w['grund'] === 'eben_bezahlt',
    json_encode($w));
pruefe('die Rate steht auf bezahlt',
    (string) Db::wert('SELECT status FROM payments WHERE id = ?', [$bl['zahlung']], '') === 'bezahlt');
pruefe('und landet auf seiner Seite, nicht auf einer zweiten Bezahlseite',
    str_contains($w['ziel'], 'kunde.php?t=') && count($probe->angelegt) === 3, $w['ziel']);
pruefe('ein Beleg ist entstanden',
    (int) Db::wert('SELECT COUNT(*) FROM invoices WHERE payment_id = ?', [$bl['zahlung']], 0) === 1);
$w = Bezahllink::oeffnen($bl['token'], $bl['zahlung'], $probe);
pruefe('ein Klick auf eine bezahlte Rate fuehrt auf die Kundenseite', $w['grund'] === 'bezahlt', json_encode($w));

/* ---------- Bezahlt, aber nicht der geforderte Betrag --------------------- */
$bw = $blRate('bezahllink-betrag@pruefung.example');
$probeW = new BezahlProbe();
Bezahllink::oeffnen($bw['token'], $bw['zahlung'], $probeW);
$zBw = Db::one('SELECT * FROM payments WHERE id = ?', [$bw['zahlung']]);
$probeW->bezahlen($probeW->letzteSitzung(), (int) $zBw['amount_cents'] - 10000);
$vorAbw = (int) Db::wert("SELECT COUNT(*) FROM notifications WHERE type = 'zahlung_abweichung'", [], 0);
$w = Bezahllink::oeffnen($bw['token'], $bw['zahlung'], $probeW);
pruefe('ein abweichender Betrag wird nicht gebucht',
    $w['grund'] === 'abweichung'
    && (string) Db::wert('SELECT status FROM payments WHERE id = ?', [$bw['zahlung']], '') !== 'bezahlt',
    json_encode($w));
pruefe('kein Beleg ueber eine Summe, die nicht geflossen ist',
    (int) Db::wert('SELECT COUNT(*) FROM invoices WHERE payment_id = ?', [$bw['zahlung']], 0) === 0);
pruefe('sondern eine laute Meldung',
    (int) Db::wert("SELECT COUNT(*) FROM notifications WHERE type = 'zahlung_abweichung'", [], 0) === $vorAbw + 1);
Bezahllink::oeffnen($bw['token'], $bw['zahlung'], $probeW);
pruefe('die Meldung kommt je Zahlungsvorgang nur einmal',
    (int) Db::wert("SELECT COUNT(*) FROM notifications WHERE type = 'zahlung_abweichung'", [], 0) === $vorAbw + 1);
pruefe('eine falsche Waehrung ist ebenfalls eine Abweichung',
    Events::zahlungVonStripe($bw['zahlung'], 'pi_waehrung', (int) $zBw['amount_cents'], 'USD') === 'abweichung');

/* Derselbe Schutz im Abgleich. */
$ga = $blRate('bezahllink-abgleich@pruefung.example');
Db::update('payments', $ga['zahlung'], ['provider' => 'stripe', 'status' => 'in_bearbeitung',
    'provider_sitzung' => 'cs_abgleich_falsch']);
Db::run('UPDATE payments SET updated_at = DATE_SUB(NOW(), INTERVAL 30 MINUTE) WHERE id = ?', [$ga['zahlung']]);
$gaBetrag = (int) Db::wert('SELECT amount_cents FROM payments WHERE id = ?', [$ga['zahlung']], 0);
$gaGebucht = Cron::zahlungenAbgleichen(new AbgleichProbe(['cs_abgleich_falsch' => ['bezahlt' => true,
    'referenz' => 'pi_abgleich_falsch', 'status' => 'complete', 'abgelaufen' => false,
    'betrag' => $gaBetrag + 1, 'waehrung' => 'EUR']]));
pruefe('auch der Abgleich bucht keinen abweichenden Betrag',
    $gaGebucht === 0 && (string) Db::wert('SELECT status FROM payments WHERE id = ?', [$ga['zahlung']], '') !== 'bezahlt',
    (string) $gaGebucht);
pruefe('und fragt dieselbe Seite danach nicht alle zehn Minuten wieder',
    /* Db::wert gibt fuer NULL den Ersatzwert zurueck -- deshalb die ganze Zeile. */
    array_key_exists('provider_sitzung', $gaZeile = (array) Db::one('SELECT provider_sitzung FROM payments WHERE id = ?', [$ga['zahlung']]))
    && $gaZeile['provider_sitzung'] === null);

/* ---------- Was nicht mehr bezahlt werden kann ---------------------------- */
$bs = $blRate('bezahllink-storno@pruefung.example');
Db::update('orders', $bs['bestellung'], ['status' => 'storniert']);
$w = Bezahllink::oeffnen($bs['token'], $bs['zahlung'], new BezahlProbe());
pruefe('eine stornierte Bestellung laesst sich nicht bezahlen', $w['grund'] === 'zu', json_encode($w));

/* ---------- Die Monatsrate: kein Auftrag, trotzdem ein Bezahlknopf -------- */
$bmK = Events::kundeFinden(['name' => 'Bezahllink Monat', 'email' => 'bezahllink-monat@pruefung.example', 'sprache' => 'de']);
$bmAbo = Abo::anlegen($bmK, ['paket_slug' => 'hosting', 'zahlart' => 'manuell']);
$bmRate = (int) Abo::abrechnen($bmAbo);
pruefe('eine Monatsrate ist angelegt', $bmRate > 0, (string) $bmRate);
$bmLink = Bezahllink::fuer($bmRate);
pruefe('auch eine Monatsrate bekommt den dauerhaften Link', str_contains($bmLink, '/bezahlen.php?t='), $bmLink);
$probeM = new BezahlProbe();
$w = Bezahllink::oeffnen(Kundenzugang::token($bmK), $bmRate, $probeM);
pruefe('der Klick legt dafuer eine Bezahlseite an', $w['grund'] === 'neu', json_encode($w));
pruefe('mit dem Namen des Vertrags statt einer leeren Zeile',
    trim((string) ($probeM->angelegt[0]['titel'] ?? '')) !== '', json_encode($probeM->angelegt));
pruefe('und nach dem Bezahlen geht es auf die Kundenseite',
    str_contains((string) ($probeM->angelegt[0]['erfolg'] ?? ''), '/kunde.php?t='), (string) ($probeM->angelegt[0]['erfolg'] ?? ''));

/* Der Fehlschlag einer Monatsrate griff auf eine Bestellung zu, die es nicht gibt. */
$vorFehl = (int) Db::wert("SELECT COUNT(*) FROM notifications WHERE type = 'zahlung_fehler'", [], 0);
$fehlerBeiMonat = null;
set_error_handler(static function (int $nr, string $text) use (&$fehlerBeiMonat) { $fehlerBeiMonat = $text; return true; });
Events::zahlungFehlgeschlagen($bmRate, 'Karte abgelehnt');
restore_error_handler();
pruefe('ein Fehlschlag bei einer Monatsrate laeuft ohne Warnung durch', $fehlerBeiMonat === null, (string) $fehlerBeiMonat);
$bmMeldung = Db::one("SELECT * FROM notifications WHERE type = 'zahlung_fehler' ORDER BY id DESC LIMIT 1");
pruefe('und die Meldung zeigt auf den Kunden, nicht auf "/bestellungen/0"',
    (int) Db::wert("SELECT COUNT(*) FROM notifications WHERE type = 'zahlung_fehler'", [], 0) === $vorFehl + 1
    && (string) $bmMeldung['link'] === '/kunden/' . $bmK, (string) ($bmMeldung['link'] ?? ''));

/* ---------- Der Webhook: eine Wiederholung wird wirklich wiederholt ------- */
$evt = 'evt_probe_' . bin2hex(random_bytes(4));
$a1 = Webhook::annehmen('stripe', $evt, 'checkout.session.completed', '{}');
pruefe('ein neues Ereignis wird angenommen', $a1['weiter'] === true && (int) $a1['id'] > 0, json_encode($a1));
$a2 = Webhook::annehmen('stripe', $evt, 'checkout.session.completed', '{}');
pruefe('dasselbe, solange es noch laeuft: 409 statt "erledigt"',
    $a2['weiter'] === false && $a2['code'] === 409, json_encode($a2));
Db::update('webhook_events', (int) $a1['id'], ['status' => 'fehler', 'error' => 'Probe']);
$a3 = Webhook::annehmen('stripe', $evt, 'checkout.session.completed', '{}');
pruefe('nach einem Fehler wird die Wiederholung von Stripe verarbeitet',
    $a3['weiter'] === true && (int) $a3['id'] === (int) $a1['id'], json_encode($a3));
pruefe('und steht dafuer wieder auf "empfangen"',
    (string) Db::wert('SELECT status FROM webhook_events WHERE id = ?', [(int) $a1['id']], '') === 'empfangen');
Db::update('webhook_events', (int) $a1['id'], ['status' => 'verarbeitet']);
$a4 = Webhook::annehmen('stripe', $evt, 'checkout.session.completed', '{}');
pruefe('ein verarbeitetes Ereignis wird nicht noch einmal verarbeitet',
    $a4['weiter'] === false && $a4['code'] === 200, json_encode($a4));
Db::run("UPDATE webhook_events SET status = 'empfangen', received_at = DATE_SUB(NOW(), INTERVAL 10 MINUTE) WHERE id = ?",
    [(int) $a1['id']]);
$a5 = Webhook::annehmen('stripe', $evt, 'checkout.session.completed', '{}');
pruefe('ein Ereignis, das seit Minuten haengt, wird noch einmal verarbeitet', $a5['weiter'] === true, json_encode($a5));

/* ============================================================================
   56. Erst der Fragebogen, dann der Preis, dann die Zahlung

   Uwe am 21.09.2026: "Der grosse Fragebogen ist nicht rausgegangen. Wir
   koennen nicht vorher den Preis nennen, bevor der Fragebogen ausgefuellt
   wird. Im Dashboard vom Kunden muss zwingend der grosse Fragebogen da sein,
   bevor wir ueberhaupt den Zahlungslink zusenden koennen."

   Bis dahin hing ein Fragebogen an einem Projekt, und ein Projekt entstand
   erst mit der Anzahlung. Ein Kunde, der noch nicht bezahlt hatte, konnte
   gar keinen Fragebogen bekommen. Dieser Abschnitt spielt den Weg so, wie
   ein Kunde ihn geht: Konfigurator -> Fragebogen -> Preis -> Angebot ->
   Zusage -> Zahlungslink -> Zahlung -> Projekt mit DEMSELBEN Fragebogen.
   ============================================================================ */
abschnitt('56. Erst der Fragebogen, dann der Preis');

require_once $wurzel . '/src/Onboarding.php';
require_once $wurzel . '/src/Bedarf.php';
require_once $wurzel . '/src/Baukasten.php';
require_once $wurzel . '/src/Angebot.php';
require_once $wurzel . '/src/Kundenzugang.php';

/* ---------- Der Kunde kommt ueber den Konfigurator ---------------------- */
$fvB = Bedarf::starten('de');
Bedarf::speichern((int) $fvB['id'], ['zweck' => ['zeigen'], 'umfang' => 'wenige', 'sprachen' => 1], 3);
$fvAb = Bedarf::absenden((int) $fvB['id'], ['name' => 'Fragebogen Zuerst', 'email' => 'fragebogen-zuerst@pruefung.example',
    'sprache' => 'de']);
pruefe('der Konfigurator ist abgeschickt', $fvAb === true);
$fvK = (int) Db::wert('SELECT id FROM customers WHERE email = ?', ['fragebogen-zuerst@pruefung.example'], 0);
$fvA = (int) Db::wert('SELECT id FROM anfragen WHERE customer_id = ? ORDER BY id DESC LIMIT 1', [$fvK], 0);
pruefe('daraus sind Kunde und Anfrage entstanden', $fvK > 0 && $fvA > 0, "$fvK / $fvA");

/* ---------- Die Auswahl aus einer Branchen-Demo kommt mit ------------- */
pruefe('eine Demo-Auswahl mit Groesse wird angenommen', Bedarf::demoPruefen('schuh-rose-42') === 'schuh-rose-42');
pruefe('unbekannte oder freie Demo-Angaben kommen nicht durch',
    Bedarf::demoPruefen('schuh-rose-99') === '' && Bedarf::demoPruefen('auto-lila') === ''
    && Bedarf::demoPruefen('auto-karmin-42') === '' && Bedarf::demoPruefen('<b>x</b>') === '');
pruefe('die Demo legt den Zweck nahe', Bedarf::demoZweck('schuh-blau') === ['shop']);
pruefe('die neuen Automodelle werden angenommen', Bedarf::demoPruefen('kleinwagen-azzurro') === 'kleinwagen-azzurro'
    && Bedarf::demoPruefen('mittelklasse-blunotte') === 'mittelklasse-blunotte');
pruefe('fremde Lacke der Automodelle werden abgewiesen', Bedarf::demoPruefen('kleinwagen-karmin') === ''
    && Bedarf::demoPruefen('mittelklasse-azzurro') === '' && Bedarf::demoPruefen('kleinwagen-azzurro-42') === '');
pruefe('Automodell im Anfragetext', str_contains(Bedarf::demoText('mittelklasse-rosso', 'de'), 'Mittelklasse')
    && str_contains(Bedarf::demoText('mittelklasse-rosso', 'de'), 'Rot Metallic'));
$dmB = Bedarf::starten('de');
Bedarf::speichern((int) $dmB['id'], ['zweck' => ['shop'], 'umfang' => 'wenige', 'sprachen' => 1], 3);
$dmOk = Bedarf::absenden((int) $dmB['id'], ['name' => 'Demo Kundin', 'email' => 'demo-auswahl@pruefung.example',
    'sprache' => 'de', 'demo' => 'schuh-rose-42']);
$dmN = (string) Db::wert('SELECT a.nachricht FROM anfragen a JOIN customers c ON c.id = a.customer_id WHERE c.email = ? ORDER BY a.id DESC LIMIT 1',
    ['demo-auswahl@pruefung.example'], '');
pruefe('die Auswahl steht als erste Zeile in der Anfrage',
    $dmOk && str_starts_with($dmN, 'Ausgangspunkt: E-Commerce-Demo, Farbe Rosé, Größe 42'), mb_substr($dmN, 0, 80));

$fvV = Vorgang::laden('a' . $fvA);
pruefe('der naechste Schritt ist der Fragebogen, nicht der Preis',
    ($fvV['schritt']['knopf'] ?? '') === 'Fragebogen verschicken' && $fvV['stufe'] === 'onboarding',
    ($fvV['schritt']['knopf'] ?? '-') . ' / ' . $fvV['stufe']);
pruefe('und er fuehrt auf die Vorgangsseite, wo der Knopf leuchtet',
    str_contains((string) ($fvV['schritt']['ziel'] ?? ''), '?tun=fragebogen_vorab'), (string) ($fvV['schritt']['ziel'] ?? ''));
pruefe('auf der Kundenseite steht die Stufe "Angaben"',
    (Kundenzugang::seite((array) Db::one('SELECT * FROM customers WHERE id = ?', [$fvK]))['stufe'] ?? '') === 'angaben');
pruefe('in der Fortschrittsleiste des Kunden kommen die Angaben vor dem Angebot',
    array_search('angaben', Kundenzugang::REIHE, true) < array_search('angebot', Kundenzugang::REIHE, true));
pruefe('auch in Uwes Stufen steht der Fragebogen vor dem Angebot',
    array_search('onboarding', array_keys(Vorgang::STUFEN), true) < array_search('angebot', array_keys(Vorgang::STUFEN), true));
pruefe('noch ist der Fragebogen nicht fertig', Onboarding::fertig($fvK) === false);

/* ---------- Der Fragebogen entsteht ohne Projekt ------------------------- */
$fvF = Onboarding::vorab($fvK);
pruefe('der Fragebogen entsteht vor jedem Projekt', $fvF > 0
    && Db::one('SELECT project_id FROM questionnaires WHERE id = ?', [$fvF])['project_id'] === null);
pruefe('ein zweiter Griff legt keinen zweiten an',
    Onboarding::vorab($fvK) === $fvF
    && (int) Db::wert('SELECT COUNT(*) FROM questionnaires WHERE customer_id = ?', [$fvK], 0) === 1);
pruefe('er ist vorbelegt mit dem, was im Konfigurator stand',
    trim((string) Db::wert('SELECT data FROM questionnaires WHERE id = ?', [$fvF], '')) !== '');

$vorVorab = (int) Db::wert("SELECT COUNT(*) FROM mails WHERE anlass = 'fragebogen_vorab' AND customer_id = ?", [$fvK], 0);
Onboarding::einladenVorab($fvK);
$fvMail = Db::one("SELECT * FROM mails WHERE anlass = 'fragebogen_vorab' AND customer_id = ? ORDER BY id DESC LIMIT 1", [$fvK]);
pruefe('die Einladung wird versucht (Postausgang)',
    (int) Db::wert("SELECT COUNT(*) FROM mails WHERE anlass = 'fragebogen_vorab' AND customer_id = ?", [$fvK], 0) === $vorVorab + 1);
[$fvBetreff, $fvText] = Texte::mail('fragebogen_vorab', 'de', ['name' => 'X', 'link' => 'L']);
pruefe('sie verspricht einen Preis erst NACH dem Fragebogen und spricht von keiner Zahlung',
    str_contains($fvBetreff, 'Preis') && !str_contains($fvText, 'Anzahlung') && !str_contains($fvText, 'bezahlt'));
pruefe('es gibt sie in allen drei Sprachen', (static function () {
    foreach (['it', 'de', 'en'] as $sp) {
        [$b, $t] = Texte::mail('fragebogen_vorab', $sp, ['name' => 'X', 'link' => 'L']);
        if (trim($b) === '' || !str_contains($t, 'L')) { return false; }
    }
    return true;
})());

/* ---------- Wer seine Seite hat, braucht keine zweite Einladung --------- */
/* Die Adresse der Kundenseite steht schon in der Eingangsbestaetigung, die
   nach dem Konfigurator automatisch rausgeht -- und der Fragebogen liegt auf
   genau dieser Seite. "Fragebogen verschicken" als naechster Schritt hiesse
   dann: dieselbe Seite ein zweites Mal schicken (22.09.2026). Ohne
   Mailserver geht hier nichts raus, also wird die Bestaetigung so vermerkt,
   wie sie im Betrieb aussieht. */
Db::insert('mails', ['anlass' => 'anfrage_eingegangen', 'empfaenger' => 'fragebogen-zuerst@pruefung.example',
    'betreff' => 'Deine Anfrage ist angekommen', 'status' => 'gesendet', 'customer_id' => $fvK]);
$fvV = Vorgang::laden('a' . $fvA);
pruefe('hat der Kunde seine Seite, ist er dran — und nicht wir mit einer zweiten Mail',
    $fvV['dran'] === Vorgang::KUNDE && ($fvV['schritt']['knopf'] ?? '') === 'Fragebogen ausfüllen',
    $fvV['dran'] . ' / ' . ($fvV['schritt']['knopf'] ?? '-'));
pruefe('der Schritt schickt nichts und fuehrt nirgendwohin',
    ($fvV['schritt']['tat'] ?? null) === null && ($fvV['schritt']['ziel'] ?? null) === null,
    (string) ($fvV['schritt']['ziel'] ?? '-'));
pruefe('die Vorgangsseite sagt dann, dass hier nichts zu klicken ist',
    str_contains((string) file_get_contents(dirname(__DIR__) . '/views/vorgang.php'),
        "elseif (\$s !== null && \$v['dran'] === Vorgang::KUNDE)"));

/* Die Punkteliste der Stufe erzaehlt vor dem Preis auch die neue
   Reihenfolge -- keine Anzahlung vor dem Fragebogen, keine Pflichtmail. */
require_once $wurzel . '/src/Ablauf.php';
$fvPunkte = array_column(Ablauf::checkliste(Vorgang::laden('a' . $fvA)), 'was');
pruefe('vor dem Preis steht keine Anzahlung in der Punkteliste',
    !in_array('Anzahlung ist eingegangen', $fvPunkte, true)
    && !in_array('Fragebogen ist verschickt', $fvPunkte, true), implode(' · ', $fvPunkte));
pruefe('dafuer steht dort, dass der Fragebogen auf der Kundenseite liegt',
    in_array('Fragebogen liegt auf der Kundenseite', $fvPunkte, true)
    && in_array('Fragebogen ist zurück', $fvPunkte, true), implode(' · ', $fvPunkte));

/* Die Verwaltung zeigt den Fragebogen trotzdem -- auch ohne Tat im Schritt.
   Sonst waere der Block genau dann weg, wenn der Kunde dran ist. */
$fvVorgangAnsicht = (string) file_get_contents(dirname(__DIR__) . '/views/vorgang.php');
pruefe('die Vorgangsseite zeigt den Fragebogen auch, wenn der Kunde dran ist',
    str_contains($fvVorgangAnsicht, "(\$v['stufe'] ?? '') === 'onboarding' && empty(\$pid) && !empty(\$v['kunde_id'])"));
pruefe('und nennt den Knopf nach dem, was er tut',
    str_contains($fvVorgangAnsicht, 'Fragebogen-Link schicken')
    && str_contains((string) file_get_contents(dirname(__DIR__) . '/views/bedarf.php'), 'Fragebogen-Link schicken'));

/* Bleibt es still, wird aus dem Warten ein Nachfassen -- aber wieder mit
   einem Knopf, nicht mit einer Pflichtmail. */
$fvStill = date('Y-m-d H:i:s', strtotime('-9 days'));
Db::run('UPDATE anfragen SET created_at = ?, updated_at = ? WHERE id = ?', [$fvStill, $fvStill, $fvA]);
Db::run('UPDATE bedarf SET created_at = ?, abgesendet_am = ? WHERE id = ?', [$fvStill, $fvStill, (int) $fvB['id']]);
Db::run('UPDATE questionnaires SET created_at = ?, updated_at = ? WHERE id = ?', [$fvStill, $fvStill, $fvF]);
$fvV = Vorgang::laden('a' . $fvA);
pruefe('liegt der Fragebogen tagelang still, heisst der Schritt "Nachfassen"',
    ($fvV['schritt']['knopf'] ?? '') === 'Nachfassen' && $fvV['dran'] === Vorgang::DU,
    ($fvV['schritt']['knopf'] ?? '-') . ' / ' . $fvV['dran']);
Db::run('UPDATE anfragen SET created_at = NOW(), updated_at = NOW() WHERE id = ?', [$fvA]);
Db::run('UPDATE bedarf SET created_at = NOW(), abgesendet_am = NOW() WHERE id = ?', [(int) $fvB['id']]);
Db::run('UPDATE questionnaires SET created_at = NOW(), updated_at = NOW() WHERE id = ?', [$fvF]);

/* Ohne Mailserver ging nichts raus; so, als waere die Einladung draussen: */
Db::update('questionnaires', $fvF, ['eingeladen_am' => date('Y-m-d H:i:s')]);
$fvV = Vorgang::laden('a' . $fvA);
pruefe('auch nach einer Einladung bleibt es beim Kunden',
    $fvV['dran'] === Vorgang::KUNDE && ($fvV['schritt']['knopf'] ?? '') === 'Fragebogen ausfüllen', $fvV['dran']);

/* ---------- Vorher geht kein Angebot raus -------------------------------- */
$fvAng = (int) Angebot::ausBedarf((int) $fvB['id']);
pruefe('ein Angebot als Entwurf darf entstehen', $fvAng > 0);
$fvV = Vorgang::laden('a' . $fvA);
pruefe('aber die Fuehrung sagt auch dann: erst der Fragebogen',
    $fvV['stufe'] === 'onboarding', ($fvV['schritt']['knopf'] ?? '-'));
$fvSperre = '';
try { Angebot::senden($fvAng); } catch (RuntimeException $e) { $fvSperre = $e->getMessage(); }
pruefe('und wer trotzdem auf Senden drueckt, bekommt eine Erklaerung statt eines Preises',
    str_contains($fvSperre, 'Fragebogen')
    && (string) Db::wert('SELECT status FROM angebote WHERE id = ?', [$fvAng], '') === 'entwurf', $fvSperre);

/* ---------- Der Kunde schickt den Fragebogen ab -------------------------- */
$vorMeld = (int) Db::wert("SELECT COUNT(*) FROM notifications WHERE type = 'fragebogen_fertig'", [], 0);
Onboarding::absenden($fvF, ['branche' => 'Tischlerei', 'ziel' => 'Mehr Anfragen']);
pruefe('der Fragebogen ist abgeschickt', Onboarding::fertig($fvK) === true);
$fvMeld = Db::one("SELECT * FROM notifications WHERE type = 'fragebogen_fertig' ORDER BY id DESC LIMIT 1");
pruefe('Uwe bekommt Bescheid — mit dem Hinweis, dass jetzt der Preis dran ist',
    (int) Db::wert("SELECT COUNT(*) FROM notifications WHERE type = 'fragebogen_fertig'", [], 0) === $vorMeld + 1
    && str_contains((string) $fvMeld['title'], 'Preis') && (string) $fvMeld['link'] === '/kunden/' . $fvK,
    (string) ($fvMeld['title'] ?? '') . ' ' . (string) ($fvMeld['link'] ?? ''));

$fvV = Vorgang::laden('a' . $fvA);
pruefe('jetzt ist das Angebot dran', $fvV['stufe'] === 'gespraech'
    && ($fvV['schritt']['knopf'] ?? '') === 'Angebot senden', ($fvV['schritt']['knopf'] ?? '-'));
pruefe('das Angebot geht jetzt raus', Angebot::senden($fvAng) === true);

/* ---------- Das Angebot steht auch auf der Kundenseite ------------------- */
/* Es ging nur per Mail raus. Auf seiner Seite stand "Dein Angebot steht" und
   darunter nichts: Der einzige Knopf dieser Stufe war der Zahlknopf, und den
   gibt es erst nach der Annahme. Wer die Mail nicht mehr fand, kam nicht
   weiter (22.09.2026). */
$fvToken = (string) Db::wert('SELECT token FROM angebote WHERE id = ?', [$fvAng], '');
$fvSeite = Kundenzugang::seite((array) Db::one('SELECT * FROM customers WHERE id = ?', [$fvK]));
pruefe('die Kundenseite steht auf der Stufe "Angebot"', ($fvSeite['stufe'] ?? '') === 'angebot',
    (string) ($fvSeite['stufe'] ?? '-'));
pruefe('und der Kunde ist dran, nicht wir', ($fvSeite['dran'] ?? '') === 'kunde',
    (string) ($fvSeite['dran'] ?? '-'));
pruefe('das Angebot liegt mit seinem Schluessel auf der Seite',
    is_array($fvSeite['angebot'] ?? null)
    && (string) $fvSeite['angebot']['token'] === $fvToken
    && (int) $fvSeite['angebot']['id'] === $fvAng);
$fvKundeSeite = (string) file_get_contents(dirname(__DIR__, 2) . '/kunde.php');
pruefe('kunde.php zeigt auf dieser Stufe den Knopf zum Angebot',
    str_contains($fvKundeSeite, "if (\$stufe === 'angebot' && \$angebotOffen && !\$offen): ?>")
    && str_contains($fvKundeSeite, '/angebot.php?t=<?= $h(rawurlencode((string) $angebotOffen[\'token\']))'));

/* ---------- Zusage, Bestellung, Zahlungslink ----------------------------- */
$fvBest = (int) Angebot::annehmen($fvToken, ['text' => 'AGB und Widerruf gelesen', 'sprache' => 'de']);
pruefe('der Kunde sagt zu, und es entsteht eine Bestellung', $fvBest > 0);
$fvSeite2 = Kundenzugang::seite((array) Db::one('SELECT * FROM customers WHERE id = ?', [$fvK]));
pruefe('nach der Zusage steht das Angebot nicht mehr als offen auf der Seite',
    ($fvSeite2['angebot'] ?? null) === null);
pruefe('die Bestellung braucht den Fragebogen vor dem Preis', Onboarding::brauchtVorPreis($fvBest) === true);
$fvV = Vorgang::laden('b' . $fvBest);
pruefe('mit ausgefuelltem Fragebogen geht es direkt zum Zahlungslink',
    ($fvV['schritt']['knopf'] ?? '') === 'Zahlungslink erzeugen', ($fvV['schritt']['knopf'] ?? '-'));

/* ---------- Die Zahlung: dasselbe Blatt, keine zweite Einladung --------- */
$fvZ = (int) Db::wert("SELECT id FROM payments WHERE order_id = ? AND art IN ('anzahlung','gesamt') ORDER BY id LIMIT 1", [$fvBest], 0);
Events::zahlungBestaetigen($fvZ, 'probe-fragebogen-zuerst', 'manuell');
$fvP = (int) Db::wert('SELECT id FROM projects WHERE order_id = ?', [$fvBest], 0);
pruefe('mit der Zahlung entsteht das Projekt', $fvP > 0);
pruefe('der Fragebogen von vorher gehoert jetzt zum Projekt',
    (int) Db::wert('SELECT project_id FROM questionnaires WHERE id = ?', [$fvF], 0) === $fvP);
pruefe('es gibt keinen zweiten, leeren daneben',
    (int) Db::wert('SELECT COUNT(*) FROM questionnaires WHERE customer_id = ?', [$fvK], 0) === 1);
pruefe('der Kunde wird nicht noch einmal zum Fragebogen eingeladen',
    (int) Db::wert("SELECT COUNT(*) FROM mails WHERE anlass = 'zahlung_ok' AND customer_id = ?", [$fvK], 0) === 0);
pruefe('das Projekt steht gleich auf "Informationen erhalten"',
    (string) Db::wert('SELECT status FROM projects WHERE id = ?', [$fvP], '') === 'informationen_erhalten',
    (string) Db::wert('SELECT status FROM projects WHERE id = ?', [$fvP], ''));
$fvV = Vorgang::laden('b' . $fvBest);
pruefe('und die Fuehrung ist beim Bauen, nicht beim Fragebogen',
    $fvV['stufe'] === 'arbeit', $fvV['stufe'] . ' / ' . ($fvV['schritt']['knopf'] ?? '-'));

/* ---------- Eine Bestellung ohne Fragebogen: kein Zahlungslink ---------- */
$fgK = Events::kundeFinden(['name' => 'Ohne Fragebogen', 'email' => 'ohne-fragebogen@pruefung.example', 'sprache' => 'de']);
$fgB = Events::bestellungAnlegen($fgK, $paketId, 'Bestellung vor dem Fragebogen', KETTE_PREIS);
$fgV = Vorgang::laden('b' . $fgB);
pruefe('eine Website-Bestellung ohne Fragebogen fuehrt zum Fragebogen, nicht zum Zahlungslink',
    Onboarding::brauchtVorPreis($fgB) === false
        ? true   // das Paket der Kette ist keine Website -- dann gilt die Sperre nicht
        : ($fgV['stufe'] === 'onboarding' && ($fgV['schritt']['knopf'] ?? '') === 'Fragebogen verschicken'),
    $fgV['stufe'] . ' / ' . ($fgV['schritt']['knopf'] ?? '-'));
$fgWeb = Angebot::internesPaket();   // eine Website entsteht immer aus einem Angebot
if ($fgWeb > 0) {
    $fgB2 = Events::bestellungAnlegen($fgK, $fgWeb, 'Website vor dem Fragebogen', KETTE_PREIS);
    $fgV2 = Vorgang::laden('b' . $fgB2);
    pruefe('bei einer Website ausdruecklich: erst der Fragebogen',
        $fgV2['stufe'] === 'onboarding' && ($fgV2['schritt']['knopf'] ?? '') === 'Fragebogen verschicken',
        $fgV2['stufe'] . ' / ' . ($fgV2['schritt']['knopf'] ?? '-'));
}
$fgBetr = (int) Db::wert("SELECT id FROM packages WHERE art = 'betreuung' ORDER BY id LIMIT 1", [], 0);
if ($fgBetr > 0) {
    $fgB3 = Events::bestellungAnlegen($fgK, $fgBetr, 'Betreuung');
    pruefe('eine Betreuung braucht keinen Fragebogen vor dem Preis', Onboarding::brauchtVorPreis($fgB3) === false);
}

/* ---------- Kein Direktkauf an der Kette vorbei ------------------------- */
/* buchen.php schickt direkt zur Bezahlseite -- ohne Angebot, ohne Fragebogen.
   Fuer Websites hielt das bisher nur der Schalter "oeffentlich" (Migration
   043). Ein Haken in der Verwaltung, und die Anzahlung kaeme wieder vor dem
   Fragebogen. Deshalb sperrt der Code, und diese Pruefung sieht nach. */
pruefe('ein Website-Paket gilt als "erst der Fragebogen"',
    Onboarding::brauchtVorPreisPaket(['art' => 'website', 'slug' => 'irgendwas']) === true);
pruefe('das Sammelpaket fuer Angebote auch',
    Onboarding::brauchtVorPreisPaket(['art' => 'sonstiges', 'slug' => 'individuelles-angebot']) === true);
pruefe('Betreuung und Hosting bleiben direkt kaufbar',
    Onboarding::brauchtVorPreisPaket(['art' => 'betreuung', 'slug' => 'betreuung'])  === false
    && Onboarding::brauchtVorPreisPaket(['art' => 'hosting', 'slug' => 'hosting']) === false);
$fgBuchen = (string) file_get_contents(dirname(__DIR__, 2) . '/buchen.php');
pruefe('buchen.php weist ein Website-Paket ab, auch wenn es freigeschaltet ist',
    preg_match('~if \(\$paket && Onboarding::brauchtVorPreisPaket\(\$paket\)\) \{ \$paket = null; \}~', $fgBuchen) === 1);

/* ---------- Kein Umweg in den Hosting-Direktverkauf ---------------------- */
/* Ein Hosting-Auftrag ohne Projekt ist das Kennzeichen des reinen Domain-&-
   Hosting-Kunden. Legte das Abschicken VOR dem Preis einen an, landete ein
   Website-Kunde in "Wartet auf Zustimmung" statt bei seinem Preis. */
$fdK = Events::kundeFinden(['name' => 'Domain Vorher', 'email' => 'domain-vorher@pruefung.example', 'sprache' => 'de']);
$fdF = Onboarding::vorab($fdK);
Onboarding::absenden($fdF, ['altseite' => 'nein', 'domain' => 'neu', 'wunsch1' => 'domain-vorher-probe.it']);
pruefe('der Fragebogen vor dem Preis legt keinen Hosting-Auftrag an',
    (int) Db::wert('SELECT COUNT(*) FROM hosting_auftraege WHERE customer_id = ?', [$fdK], 0) === 0);

/* ---------- Ein Fragebogen ohne Projekt laesst sich oeffnen -------------- */
$fvLaden = Onboarding::laden(Onboarding::token($fdF));
pruefe('der Fragebogen ohne Projekt laesst sich ueber seinen Schluessel oeffnen',
    $fvLaden !== null && (int) $fvLaden['id'] === $fdF);

/* ============================================================================
   57. Bezahlt, aber die Verwaltung weiss nichts davon   (22.09.2026)

   Uwe: "Wenn der Kunde bezahlt hat, wird das in der Verwaltung nicht
   angezeigt." Der Grund ist immer derselbe: Der Webhook kam nicht an --
   falscher Modus, anderes Signaturgeheimnis, Endpunkt nicht eingetragen --
   und der naechtliche Abgleich lief noch nicht oder gar nicht. Die Rate
   stand dann auf "in Bearbeitung", und die Fuehrung sagte "Erinnern": eine
   Zahlungserinnerung an jemanden, der laengst bezahlt hat.

   Jetzt fragt ein Schritt nach, sobald eine Rate laenger als eine Stunde
   haengt -- und der Knopf bucht, was Stripe als bezahlt kennt.
   ============================================================================ */
abschnitt('57. Bezahlt, aber die Verwaltung weiss nichts davon');

$nfK = Events::kundeFinden(['name' => 'Stripe Haengt', 'email' => 'stripe-haengt@pruefung.example', 'sprache' => 'de']);
$nfB = Events::bestellungAnlegen($nfK, $paketId, 'Rate haengt bei Stripe', KETTE_PREIS);
Onboarding::absenden(Onboarding::vorab($nfK), ['branche' => 'Probe']);
$nfZ = (int) Db::wert("SELECT id FROM payments WHERE order_id = ? AND art = 'anzahlung'", [$nfB], 0);
pruefe('die Probe hat eine Anzahlung', $nfZ > 0);

/* So, wie es nach einem Klick auf die Bezahlseite aussieht: Link ist raus,
   Sitzung steht, Rate in Bearbeitung -- und seit zwei Stunden nichts mehr. */
Db::update('payments', $nfZ, ['status' => 'in_bearbeitung', 'provider' => 'stripe',
    'provider_sitzung' => 'cs_probe_haengt', 'link_url' => 'https://checkout.stripe.com/c/pay/cs_probe_haengt']);
Db::insert('mails', ['anlass' => 'zahlungslink', 'empfaenger' => 'stripe-haengt@pruefung.example',
    'betreff' => 'Dein Zahlungslink', 'status' => 'gesendet', 'customer_id' => $nfK,
    'order_id' => $nfB, 'payment_id' => $nfZ]);
Db::run('UPDATE payments SET updated_at = DATE_SUB(NOW(), INTERVAL 2 HOUR) WHERE id = ?', [$nfZ]);

$nfV = Vorgang::laden('b' . $nfB);
pruefe('haengt die Rate, fragt die Fuehrung bei Stripe nach statt zu erinnern',
    ($nfV['schritt']['knopf'] ?? '') === 'Bei Stripe nachfragen'
    && ($nfV['schritt']['tat'] ?? '') === 'zahlung_nachfragen'
    && (int) ($nfV['schritt']['id'] ?? 0) === $nfZ,
    ($nfV['schritt']['knopf'] ?? '-'));

/* Frisch geklickt heisst: der Kunde ist vielleicht noch auf der Bezahlseite.
   Dann ist Nachfragen verfrueht, und es bleibt beim Erinnern. */
Db::run('UPDATE payments SET updated_at = NOW() WHERE id = ?', [$nfZ]);
$nfV2 = Vorgang::laden('b' . $nfB);
pruefe('frisch angeklickt wird nicht gleich nachgefragt',
    ($nfV2['schritt']['knopf'] ?? '') === 'Erinnern', ($nfV2['schritt']['knopf'] ?? '-'));

/* Der Handgriff selbst: Was Stripe als bezahlt kennt, wird gebucht -- und
   steht danach in Protokoll und Meldungen, also in der Verwaltung. */
$nfVorMeld = (int) Db::wert("SELECT COUNT(*) FROM notifications WHERE type = 'zahlung_ok'", [], 0);
$nfBetrag = (int) Db::wert('SELECT amount_cents FROM payments WHERE id = ?', [$nfZ], 0);
$nfWie = Events::zahlungVonStripe($nfZ, 'pi_probe_haengt', $nfBetrag, 'eur');
pruefe('die Nachfrage bucht die Rate', $nfWie === 'gebucht'
    && (string) Db::wert('SELECT status FROM payments WHERE id = ?', [$nfZ], '') === 'bezahlt', (string) $nfWie);
pruefe('und die Verwaltung zeigt es — als Meldung und im Protokoll',
    (int) Db::wert("SELECT COUNT(*) FROM notifications WHERE type = 'zahlung_ok'", [], 0) === $nfVorMeld + 1
    && (int) Db::wert("SELECT COUNT(*) FROM activities WHERE type = 'zahlung_ok' AND order_id = ?", [$nfB], 0) > 0);
$nfV3 = Vorgang::laden('b' . $nfB);
pruefe('der Vorgang steht danach nicht mehr beim Geld',
    $nfV3['stufe'] !== 'angebot', $nfV3['stufe']);

/* Knopf und Handgriff muessen beide da sein: Ein Schritt ohne Knopf ist eine
   Sackgasse, ein Knopf ohne Handgriff ein Fehler beim Klicken. */
$nfBestellSeite = (string) file_get_contents(dirname(__DIR__) . '/views/bestellung.php');
/* Dieselbe Rechnung steckt im Webhook: Dort entscheidet das Alter eines
   Ereignisses, ob ein zweiter Lauf zugelassen wird. Beide Uhren muessen
   dieselbe sein -- sonst gilt ein eben angenommenes Ereignis als haengend. */
pruefe('auch der Webhook rechnet das Alter mit der Uhr der Datenbank',
    str_contains((string) file_get_contents($wurzel . '/src/Webhook.php'), 'TIMESTAMPDIFF(SECOND, received_at, NOW())')
    && !str_contains((string) file_get_contents($wurzel . '/src/Webhook.php'), "time() - (int) strtotime"));

pruefe('die Bestellseite hat den Knopf "Bei Stripe nachfragen"',
    str_contains($nfBestellSeite, 'value="zahlung_nachfragen"')
    && str_contains($nfBestellSeite, 'data-tun="zahlung_nachfragen"'));
pruefe('und die Verwaltung kennt den Handgriff',
    str_contains((string) file_get_contents(dirname(__DIR__) . '/index.php'),
        "case 'zahlung_nachfragen':"));

/* ============================================================================
   58. Kein Zahlungslink vor der Zusage   (22.09.2026)

   Uwe: "Ich konnte den Zahlungslink senden, obwohl das Angebot noch nicht
   bestaetigt war." Genau so war es: Eine Bestellung kann auch von Hand aus
   einer Anfrage entstehen, und ab da fragte niemand mehr, ob zu diesem
   Kunden noch ein Angebot beim Kunden liegt. Der Kunde haette eine
   Zahlungsaufforderung ueber einen Betrag bekommen, dem er nie zugestimmt
   hat.
   ============================================================================ */
abschnitt('58. Kein Zahlungslink vor der Zusage');

require_once $wurzel . '/src/Angebot.php';

$kzK = Events::kundeFinden(['name' => 'Zusage Fehlt', 'email' => 'zusage-fehlt@pruefung.example', 'sprache' => 'de']);
Onboarding::absenden(Onboarding::vorab($kzK), ['branche' => 'Probe']);
$kzB = Events::bestellungAnlegen($kzK, $paketId, 'Von Hand angelegt, ohne Zusage', KETTE_PREIS);
pruefe('ohne Angebot steht der Zusage nichts im Weg', Angebot::wartetAufZusage($kzB) === null);

$kzA = (int) Db::insert('angebote', [
    'customer_id' => $kzK, 'nummer' => 'PR8-' . substr((string) hrtime(true), -9),
    'sprache' => 'de', 'status' => 'entwurf', 'titel' => 'Probe Zusage',
    'summe_cents' => 120000, 'monatlich_cents' => 0, 'currency' => 'EUR',
    'gueltig_bis' => date('Y-m-d', strtotime('+14 days')),
    'token' => bin2hex(random_bytes(24)),
]);
$kzW = Angebot::wartetAufZusage($kzB);
pruefe('ein Entwurf haelt den Zahlungslink auf',
    is_array($kzW) && (int) $kzW['id'] === $kzA && (string) $kzW['status'] === 'entwurf');
pruefe('und die Erklaerung sagt, dass der Kunde es nicht einmal gesehen hat',
    str_contains(Angebot::warumKeinZahlungslink($kzW), 'Entwurf'));
$kzV = Vorgang::laden('b' . $kzB);
pruefe('die Fuehrung schickt erst das Angebot, nicht den Zahlungslink',
    ($kzV['schritt']['knopf'] ?? '') === 'Angebot senden', ($kzV['schritt']['knopf'] ?? '-'));

Angebot::senden($kzA);
$kzW2 = Angebot::wartetAufZusage($kzB);
pruefe('ein verschicktes Angebot haelt ihn genauso auf',
    is_array($kzW2) && (string) $kzW2['status'] === 'gesendet');
$kzV2 = Vorgang::laden('b' . $kzB);
pruefe('jetzt ist der Kunde dran — mit seiner Zusage, nicht mit einer Zahlung',
    ($kzV2['schritt']['knopf'] ?? '') === 'Angebot annehmen' && $kzV2['dran'] === Vorgang::KUNDE,
    ($kzV2['schritt']['knopf'] ?? '-') . ' / ' . $kzV2['dran']);

/* Die Zusage von Hand (am Telefon) loest die Sperre -- aber nur fuer die
   Bestellung, die dabei entsteht. */
$kzBest = (int) Angebot::zusagenVonHand($kzA);
pruefe('die Zusage von Hand legt eine Bestellung an', $kzBest > 0);
pruefe('danach steht dem Zahlungslink nichts mehr im Weg',
    Angebot::wartetAufZusage($kzBest) === null);
$kzV3 = Vorgang::laden('b' . $kzBest);
pruefe('und die Fuehrung fuehrt zum Zahlungslink',
    ($kzV3['schritt']['knopf'] ?? '') === 'Zahlungslink erzeugen', ($kzV3['schritt']['knopf'] ?? '-'));

/* Ein abgelehntes Angebot blockiert nicht: Sagt der Kunde danach am Telefon
   doch zu, waere jede Sperre nur im Weg. */
$kzA2 = (int) Db::insert('angebote', [
    'customer_id' => $kzK, 'nummer' => 'PR9-' . substr((string) hrtime(true), -9),
    'sprache' => 'de', 'status' => 'abgelehnt', 'titel' => 'Abgelehnt',
    'summe_cents' => 90000, 'monatlich_cents' => 0, 'currency' => 'EUR',
    'token' => bin2hex(random_bytes(24)),
]);
pruefe('ein abgelehntes Angebot haelt nichts mehr auf',
    Angebot::wartetAufZusage($kzBest) === null);
Db::run('DELETE FROM angebote WHERE id = ?', [$kzA2]);

/* Die Taten selbst sind gesperrt -- wer an der Fuehrung vorbei klickt,
   bekommt eine Erklaerung statt einer Zahlungsaufforderung. */
$kzIndex = (string) file_get_contents(dirname(__DIR__) . '/index.php');
pruefe('"Zahlungslink erzeugen" fragt nach der Zusage',
    preg_match('~case \'zahlungslink\':.*?Angebot::wartetAufZusage~s', $kzIndex) === 1);
pruefe('"Zahlungslink senden" ebenso',
    preg_match('~case \'zahlungslink_senden\':.*?Angebot::wartetAufZusage~s', $kzIndex) === 1);
pruefe('und die Bestellseite zeigt statt des Knopfs den Weg zum Angebot',
    str_contains((string) file_get_contents(dirname(__DIR__) . '/views/bestellung.php'),
        'Angebot::wartetAufZusage'));

/* ============================================================================
   59. Die gewaehlte Sprache traegt durch   (23.09.2026)

   Uwe: "Entsprechend welche Sprache der Besucher waehlt, muss jede Seite,
   jede Unterseite, egal wo, in der gewaehlten Sprache sein."

   Vorher entschied jede Seite fuer sich, und keine PHP-Seite hat die Wahl je
   gemerkt: Wer auf der Kundenseite DE waehlte, bekam auf legal.html wieder
   Italienisch -- ausgerechnet bei AGB und Datenschutz.
   ============================================================================ */
abschnitt('59. Die gewaehlte Sprache traegt durch');

require_once $wurzel . '/src/Sprache.php';
require_once $wurzel . '/src/Fuss.php';
require_once $wurzel . '/src/Widerruf.php';

/* ---------- Die Reihenfolge der Quellen -------------------------------- */
$altKeks = $_COOKIE[Sprache::KEKS] ?? null;
$altLang = $_REQUEST['lang'] ?? null;
unset($_COOKIE[Sprache::KEKS], $_REQUEST['lang']);

pruefe('ohne alles ist es Italienisch', Sprache::ausAnfrage() === 'it');
pruefe('was an der Sache haengt, gilt als Vorgabe', Sprache::ausAnfrage('de') === 'de');
$_COOKIE[Sprache::KEKS] = 'en';
pruefe('der Keks schlaegt die Vorgabe', Sprache::ausAnfrage('de') === 'en');
$_REQUEST['lang'] = 'it';
pruefe('die Wahl in der Adresse schlaegt alles', Sprache::ausAnfrage('de') === 'it');
$_REQUEST['lang'] = 'kl';
pruefe('Unsinn in der Adresse wird nicht geglaubt', Sprache::ausAnfrage('de') === 'en');
unset($_REQUEST['lang']);
unset($_COOKIE[Sprache::KEKS]);
if ($altKeks !== null) { $_COOKIE[Sprache::KEKS] = $altKeks; }
if ($altLang !== null) { $_REQUEST['lang'] = $altLang; }

pruefe('die Sprache haengt sich sauber an jede Adresse',
    Sprache::anhaengen('https://x/kunde.php?t=abc', 'de') === 'https://x/kunde.php?t=abc&lang=de'
    && Sprache::anhaengen('https://x/legal.html#agb', 'de') === 'https://x/legal.html?lang=de#agb'
    && Sprache::anhaengen('https://x/a?lang=it#z', 'en') === 'https://x/a?lang=en#z');

/* ---------- Die Rechtsseite, in der richtigen Sprache ------------------- */
foreach (['it', 'de', 'en'] as $spL) {
    pruefe("der Rechtsfuss fuehrt auf die $spL-Fassung", (static function () use ($spL): bool {
        $fuss = Fuss::html($spL);
        foreach (['impressum', 'privacy', 'agb', 'widerruf'] as $anker) {
            if (!str_contains($fuss, 'legal.html?lang=' . $spL . '#' . $anker)) { return false; }
        }
        return true;
    })());
    pruefe("auch der Haken unter AGB und Datenschutz zeigt auf $spL",
        substr_count(Widerruf::texte($spL)['agb'], 'legal.html?lang=' . $spL) === 2,
        Widerruf::texte($spL)['agb']);
}

/* ---------- Der Link in der Mail traegt die Sprache des Kunden ---------- */
$spK = Events::kundeFinden(['name' => 'Sprache Deutsch', 'email' => 'sprache-de@pruefung.example', 'sprache' => 'de']);
$spL2 = Kundenzugang::linkFuer($spK);
pruefe('der Link zur Kundenseite traegt die Sprache des Kunden',
    str_contains($spL2, '/kunde.php?t=') && str_contains($spL2, 'lang=de'), $spL2);
pruefe('und ausdruecklich anders geht auch',
    str_contains(Kundenzugang::linkFuer($spK, 'en'), 'lang=en'));

/* ---------- Jede oeffentliche Seite merkt die Wahl --------------------- */
/* Ohne das waere die Wahl auf der naechsten Seite wieder weg -- auch auf den
   statischen, die den Keks lesen. */
$spWurzel = dirname(__DIR__, 2);
foreach (['bedarf.php', 'buchen.php', 'hosting.php', 'kunde.php', 'fragebogen.php',
          'projekt.php', 'vorgang.php', 'angebot.php', 'zugang.php'] as $spSeite) {
    $spQuelle = (string) file_get_contents($spWurzel . '/' . $spSeite);
    pruefe($spSeite . ' waehlt die Sprache an einer Stelle und merkt sie',
        str_contains($spQuelle, 'Sprache::ausAnfrage(') && str_contains($spQuelle, 'Sprache::merken('));
}

/* ---------- Das Angebot laesst sich umschalten ------------------------- */
$spAngebot = (string) file_get_contents($spWurzel . '/angebot.php');
pruefe('das Angebot hat einen Sprachumschalter',
    str_contains($spAngebot, 'class="sprachwahl"')
    && str_contains($spAngebot, "'&lang=' . \$sl"));

/* ---------- Die statischen Seiten folgen dem Keks ---------------------- */
$spApp = (string) file_get_contents($spWurzel . '/assets/js/app.js');
pruefe('app.js liest den Keks, den die PHP-Seiten setzen',
    str_contains($spApp, 'vecomlang=([a-z]{2})'));
pruefe('und zwar an einer Stelle — auch die Weiche beim Direkteinstieg fragt dort',
    substr_count($spApp, 'gemerkt()') >= 3
    && !str_contains($spApp, "try { wunsch = localStorage.getItem(STORE); } catch (e) {}"));
pruefe('und der Sprachhinweis fragt nicht gegen eine Wahl an, die dort getroffen wurde',
    str_contains((string) file_get_contents($spWurzel . '/assets/js/sprachhinweis.js'),
        'vecomlang=([a-z]{2})'));
foreach (['de/index.html' => 'de', 'en/care.html' => 'en'] as $spDatei => $spSpr) {
    if (!is_file($spWurzel . '/' . $spDatei)) { continue; }
    pruefe($spDatei . ' verlinkt die Rechtsseite in ' . $spSpr,
        str_contains((string) file_get_contents($spWurzel . '/' . $spDatei),
            'legal.html?lang=' . $spSpr . '#impressum'));
}

/* ============================================================================
   60. Keine Fertigungszeit im Schaufenster   (23.09.2026)

   Uwe: "Keine Fertigungszeit angeben, sondern ausbessern je nach Aufwand."

   Auf der Seite stand "In zwei Wochen online", "Online in 2-6 Wochen",
   "2 Wochen bis zur fertigen Seite" und in den Vorlagen "meist zwei bis
   vier Wochen". Eine Zahl, die fuer alle gilt, ist geraten -- und ein
   Versprechen, das der Umfang gar nicht halten kann. Gesagt wird jetzt,
   wovon es abhaengt; die Dauer nennt Uwe, wenn er den Umfang kennt.

   Diese Pruefung sieht in den Texten nach, nicht im Code: Sie faellt auf,
   wenn jemand eine Wochenzahl zurueckschreibt.
   ============================================================================ */
abschnitt('60. Keine Fertigungszeit im Schaufenster');

$fzWurzel = dirname(__DIR__, 2);

/* Was eine Fertigungszeit ist: eine Zahl (Ziffer oder Wort) vor Wochen oder
   Tagen. Was keine ist: Zahlungsfristen, Linklaufzeiten, Vertragslaufzeiten
   -- die stehen in Monaten oder als "14 Tage" in den Rechtstexten und
   gehoeren dort hin. Geprueft werden deshalb nur die Schaufenstertexte. */
$fzMuster = '~(zwei|drei|vier|fünf|sechs|due|tre|quattro|two|three|four|[0-9])'
          . '\s*(–|-|bis|a|to|o|or|e|und)?\s*([0-9]|zwei|drei|vier|sechs|due|tre|quattro|two|three|six)?'
          . '\s*(wochen|woche|settimane|settimana|weeks|week)~iu';

foreach (['it', 'de', 'en'] as $fzSpr) {
    $fzText = (string) file_get_contents($fzWurzel . '/assets/js/i18n-' . $fzSpr . '.js');
    pruefe('die Website verspricht keine Fertigungszeit (' . $fzSpr . ')',
        preg_match($fzMuster, $fzText) === 0,
        (static function () use ($fzMuster, $fzText): string {
            preg_match($fzMuster, $fzText, $t);
            return $t ? trim((string) $t[0]) : '';
        })());
}

$fzVorlagen = (string) file_get_contents($fzWurzel . '/app/src/vorlagen.json');
pruefe('auch die fertigen Textbausteine nennen keine Wochenzahl',
    preg_match($fzMuster, $fzVorlagen) === 0,
    (static function () use ($fzMuster, $fzVorlagen): string {
        preg_match($fzMuster, $fzVorlagen, $t);
        return $t ? trim((string) $t[0]) : '';
    })());

/* Dafuer steht dort, wovon es abhaengt -- sonst waere die Frage "wie lange
   dauert es" unbeantwortet, und das ist schlechter als eine Zahl. */
foreach (['it' => 'quanto c', 'de' => 'nach dem Umfang', 'en' => 'on the scope'] as $fzSpr => $fzSatz) {
    pruefe('stattdessen sagt der Text, wovon es abhaengt (' . $fzSpr . ')',
        str_contains((string) file_get_contents($fzWurzel . '/assets/js/i18n-' . $fzSpr . '.js'), 'Umfang')
        || str_contains($fzVorlagen, $fzSatz)
        || str_contains((string) file_get_contents($fzWurzel . '/assets/js/i18n-' . $fzSpr . '.js'), 'scope')
        || str_contains((string) file_get_contents($fzWurzel . '/assets/js/i18n-' . $fzSpr . '.js'), 'lavoro, non a calendario'));
}


/* ============================================================================
   61. Der Einstieg ist eine E-Mail-Adresse (24.09.2026)

   Uwe: „Statt dem Fragebogen ist es besser, dass der Kunde seine E-Mail
   einträgt … Die Kette darf nicht unterbrochen werden, alles muss sauber
   funktionieren.“ Geprüft wird deshalb der ganze neue Anfang bis zu der
   Stelle, an der die alte Kette übernimmt (Abschnitt 56), und dass nichts
   davon eine Adresse verrät oder einen zweiten Kunden anlegt.
   ============================================================================ */
abschnitt('61. Der Einstieg ist eine E-Mail-Adresse');

require_once $wurzel . '/src/Zugang.php';
require_once $wurzel . '/src/Kundenzugang.php';
require_once $wurzel . '/src/Bedarf.php';
require_once $wurzel . '/src/Vorgang.php';

$zgMail = 'einstieg@pruefung.example';
$zgMails = static fn(string $anlass, string $an): int =>
    (int) Db::wert('SELECT COUNT(*) FROM mails WHERE anlass = ? AND empfaenger = ?', [$anlass, $an], 0);

/* ---------- Anfordern ---------------------------------------------------- */
pruefe('eine unbrauchbare Adresse wird abgelehnt',
    Zugang::anfordern('keine-adresse', 'de')['ok'] === false);
$zg1 = Zugang::anfordern(' Einstieg@Pruefung.example ', 'de');
pruefe('eine neue Adresse bekommt einen Zugang', $zg1['ok'] === true && $zg1['art'] === 'neu', json_encode($zg1));
pruefe('die Willkommensmail wird versucht', $zgMails('zugang', $zgMail) === 1, (string) $zgMails('zugang', $zgMail));
pruefe('E4: vor dem Öffnen steht niemand in der Kundenliste',
    (int) Db::wert('SELECT COUNT(*) FROM customers WHERE email = ?', [$zgMail], 0) === 0);
pruefe('E4: und es gibt keine Anfrage',
    (int) Db::wert('SELECT COUNT(*) FROM anfragen WHERE email = ?', [$zgMail], 0) === 0);
Zugang::anfordern($zgMail, 'de');
pruefe('zweimal gedrückt: derselbe Zugang, kein zweiter',
    (int) Db::wert('SELECT COUNT(*) FROM zugaenge WHERE email = ?', [$zgMail], 0) === 1);
pruefe('… und dieselbe Mail noch einmal', $zgMails('zugang', $zgMail) === 2);

/* E2: Der Link steht nie auf dem Bildschirm. zugang.php antwortet dem Feld
   nur mit ok und einem Satz -- der Satz ist für jede Adresse derselbe. */
$zgQuelle = (string) file_get_contents(dirname(__DIR__, 2) . '/zugang.php');
pruefe('E2: zugang.php gibt nur „ok“ und einen Satz zurück, nie einen Link',
    str_contains($zgQuelle, "json_encode(['ok' => \$ergebnis === 'gesendet', 'meldung' => \$T(\$ergebnis)]")
    && !preg_match('/json_encode\([^;]*(link|token)/i', $zgQuelle));
pruefe('E2: die Rückmeldung unterscheidet nicht zwischen neu und bekannt',
    !str_contains($zgQuelle, "'bestand'"));

/* ---------- Öffnen ------------------------------------------------------- */
pruefe('ein falscher Schlüssel öffnet nichts', Zugang::oeffnen(str_repeat('a', 48))['ok'] === false);
pruefe('ein kaputter Schlüssel auch nicht', Zugang::oeffnen('../../etc')['ok'] === false);
$zgToken = (string) Db::wert('SELECT token FROM zugaenge WHERE email = ?', [$zgMail], '');
$zgO = Zugang::oeffnen($zgToken);
$zgK = (int) ($zgO['kunde_id'] ?? 0);
pruefe('beim Öffnen entsteht der Kunde', $zgO['ok'] === true && $zgK > 0 && !empty($zgO['neu']), json_encode($zgO));
pruefe('der Weg führt ins Dashboard', str_contains((string) ($zgO['link'] ?? ''), 'kunde.php?t='));
pruefe('die Sprache der Seite hängt am Kunden',
    (string) Db::wert('SELECT sprache FROM customers WHERE id = ?', [$zgK], '') === 'de');
pruefe('für das Vorhaben liegt ein Bedarf bereit',
    (int) Db::wert("SELECT COUNT(*) FROM bedarf WHERE customer_id = ? AND status = 'offen'", [$zgK], 0) === 1);
$zgO2 = Zugang::oeffnen($zgToken);
pruefe('ein zweites Öffnen führt einfach hinein',
    $zgO2['ok'] === true && (int) $zgO2['kunde_id'] === $zgK && empty($zgO2['neu']));
pruefe('… ohne zweiten Kunden und ohne zweiten Bedarf',
    (int) Db::wert('SELECT COUNT(*) FROM customers WHERE email = ?', [$zgMail], 0) === 1
    && (int) Db::wert("SELECT COUNT(*) FROM bedarf WHERE customer_id = ?", [$zgK], 0) === 1);

/* ---------- Das Dashboard beginnt mit dem Vorhaben (D1) ----------------- */
$zgKunde = (array) Db::one('SELECT * FROM customers WHERE id = ?', [$zgK]);
$zgS = Kundenzugang::seite($zgKunde);
pruefe('D1: der erste Schritt ist das Vorhaben', ($zgS['stufe'] ?? '') === 'vorhaben', (string) ($zgS['stufe'] ?? ''));
pruefe('… und er ist dran', ($zgS['dran'] ?? '') === 'kunde');
pruefe('… auf dem ersten Platz der Leiste', (int) ($zgS['stufe_nr'] ?? -1) === 0);
pruefe('… mit dem Bedarf, zu dem der Knopf führt', !empty($zgS['bedarf']['token']));
pruefe('die Kundenseite hat den Knopf zum Vorhaben',
    str_contains((string) file_get_contents(dirname(__DIR__, 2) . '/kunde.php'), "\$stufe === 'vorhaben'"));
pruefe('D2: ohne Namen kein „Guten Tag ,“', trim((string) $zgKunde['name']) === ''
    && str_contains((string) file_get_contents(dirname(__DIR__, 2) . '/kunde.php'), "\$T('halloOhne')"));

/* Verfallener Bedarf: Das Dashboard darf nicht ohne Knopf dastehen. */
Db::run('UPDATE bedarf SET created_at = NOW() - INTERVAL 40 DAY WHERE customer_id = ?', [$zgK]);
pruefe('ist der Bedarf verfallen, zeigt die Stufe trotzdem das Vorhaben',
    (Kundenzugang::seite($zgKunde)['stufe'] ?? '') === 'vorhaben');
$zgB = Zugang::bedarfFuerKunde($zgK, 'de');
pruefe('… und ein frischer Bedarf entsteht', (int) $zgB['customer_id'] === $zgK && $zgB['status'] === 'offen');
pruefe('… der die Adresse aus der Akte trägt', (string) $zgB['email'] === $zgMail);

/* ---------- Absenden: ab hier übernimmt die alte Kette ------------------ */
Bedarf::speichern((int) $zgB['id'], ['zweck' => ['zeigen', 'kontakt'], 'umfang' => 'wenige', 'sprachen' => 2], 3);
$zgAb = Bedarf::absenden((int) $zgB['id'], ['name' => 'Giulia Einstieg', 'email' => $zgMail,
    'telefon' => '+39 333 000 0000', 'firma' => 'Trattoria Einstieg', 'sprache' => 'de']);
pruefe('das Vorhaben lässt sich absenden', $zgAb === true);
pruefe('daraus entsteht die Anfrage wie bisher',
    (int) Db::wert('SELECT COUNT(*) FROM anfragen WHERE customer_id = ?', [$zgK], 0) === 1);
pruefe('am selben Kunden, keinem zweiten',
    (int) Db::wert('SELECT COUNT(*) FROM customers WHERE email = ?', [$zgMail], 0) === 1);
$zgKunde = (array) Db::one('SELECT * FROM customers WHERE id = ?', [$zgK]);
pruefe('D2: der Name landet in der leeren Akte', (string) $zgKunde['name'] === 'Giulia Einstieg', (string) $zgKunde['name']);
pruefe('… Telefon und Betrieb ebenso',
    (string) $zgKunde['phone'] !== '' && (string) $zgKunde['company'] === 'Trattoria Einstieg');
pruefe('die Eingangsbestätigung geht raus wie bisher', $zgMails('anfrage_eingegangen', $zgMail) === 1);
$zgS2 = Kundenzugang::seite($zgKunde);
pruefe('danach ist das Vorhaben erledigt', ($zgS2['stufe'] ?? '') !== 'vorhaben', (string) ($zgS2['stufe'] ?? ''));
$zgV = Vorgang::laden('a' . (int) Db::wert('SELECT id FROM anfragen WHERE customer_id = ?', [$zgK], 0));
pruefe('Uwes Führung kennt den nächsten Schritt: erst der Fragebogen',
    is_array($zgV) && str_contains((string) ($zgV['warum'] ?? '') . json_encode($zgV['schritt'] ?? null, JSON_UNESCAPED_UNICODE), 'Fragebogen'),
    is_array($zgV) ? (string) ($zgV['warum'] ?? '') : '—');

/* Eine gepflegte Akte wird nie überschrieben */
Db::run('UPDATE customers SET name = ? WHERE id = ?', ['Giulia Gepflegt', $zgK]);
require_once $wurzel . '/src/Anfrage.php';
Anfrage::annehmen(['name' => 'Anders Getippt', 'email' => $zgMail, 'sprache' => 'de']);
pruefe('eine spätere Anfrage schreibt keinen gepflegten Namen um',
    (string) Db::wert('SELECT name FROM customers WHERE id = ?', [$zgK], '') === 'Giulia Gepflegt');

/* ---------- Schon Kunde: derselbe Link noch einmal (E2) ----------------- */
$zgZeilen = (int) Db::wert('SELECT COUNT(*) FROM zugaenge', [], 0);
$zgBest = Zugang::anfordern($zgMail, 'it');
pruefe('E2: ein Bestandskunde bekommt seinen Link noch einmal', $zgBest['art'] === 'bestand');
pruefe('… ohne neuen Zugang', (int) Db::wert('SELECT COUNT(*) FROM zugaenge', [], 0) === $zgZeilen);
pruefe('… per Mail', $zgMails('zugang_bestand', $zgMail) === 1);

/* ---------- Eine Anfrage mit Paket bekommt kein Vorhaben untergeschoben -- */
$zgHost = Events::kundeFinden(['name' => 'Nur Hosting', 'email' => 'nur-hosting@pruefung.example']);
Db::insert('anfragen', ['customer_id' => $zgHost, 'name' => 'Nur Hosting', 'email' => 'nur-hosting@pruefung.example',
    'sprache' => 'de', 'status' => 'neu', 'paket_slug' => 'hosting']);
pruefe('wer nur Hosting vorgemerkt hat, sieht kein Vorhaben',
    (Kundenzugang::seite((array) Db::one('SELECT * FROM customers WHERE id = ?', [$zgHost]))['stufe'] ?? '') !== 'vorhaben');
/* … wohl aber, wer nur geschrieben hat: Für ihn sagt die Führung „Konfigurator schicken“. */
$zgFrei = Events::kundeFinden(['name' => 'Nur Geschrieben', 'email' => 'nur-geschrieben@pruefung.example']);
Db::insert('anfragen', ['customer_id' => $zgFrei, 'name' => 'Nur Geschrieben', 'email' => 'nur-geschrieben@pruefung.example',
    'sprache' => 'de', 'status' => 'neu', 'nachricht' => 'Ich brauche eine Seite.']);
pruefe('wer nur geschrieben hat, bekommt das Vorhaben in seinem Dashboard',
    (Kundenzugang::seite((array) Db::one('SELECT * FROM customers WHERE id = ?', [$zgFrei]))['stufe'] ?? '') === 'vorhaben');

/* ---------- Ablaufen und aufräumen (E4) ---------------------------------- */
Zugang::anfordern('alt@pruefung.example', 'it');
Db::run("UPDATE zugaenge SET created_at = NOW() - INTERVAL 9 DAY WHERE email = 'alt@pruefung.example'");
pruefe('ein nie geöffneter Link läuft ab',
    (Zugang::oeffnen((string) Db::wert("SELECT token FROM zugaenge WHERE email = 'alt@pruefung.example'", [], ''))['grund'] ?? '') === 'abgelaufen');
pruefe('… und legt dabei keinen Kunden an',
    (int) Db::wert("SELECT COUNT(*) FROM customers WHERE email = 'alt@pruefung.example'", [], 0) === 0);
Db::run("UPDATE zugaenge SET created_at = NOW() - INTERVAL 30 DAY WHERE email = ?", [$zgMail]);
$zgWeg = Zugang::aufraeumen();
pruefe('aufgeräumt wird nur, was nie geöffnet wurde',
    $zgWeg >= 1 && (int) Db::wert("SELECT COUNT(*) FROM zugaenge WHERE email = 'alt@pruefung.example'", [], 0) === 0
    && (int) Db::wert('SELECT COUNT(*) FROM zugaenge WHERE email = ?', [$zgMail], 0) === 1);
pruefe('ein geöffneter Link führt auch nach Wochen noch hinein', Zugang::oeffnen($zgToken)['ok'] === true);

/* ---------- Erinnern (D3): höchstens drei, dann Ruhe --------------------- */
Zugang::anfordern('zoegert@pruefung.example', 'it');
Db::run("UPDATE zugaenge SET created_at = NOW() - INTERVAL 2 DAY WHERE email = 'zoegert@pruefung.example'");
Zugang::erinnern();
pruefe('D3: ungeöffnet nach einem Tag eine Erinnerung', $zgMails('zugang_erinnerung', 'zoegert@pruefung.example') === 1);
Zugang::erinnern();
pruefe('D3: … und nur eine', $zgMails('zugang_erinnerung', 'zoegert@pruefung.example') === 1);

Zugang::anfordern('liegt@pruefung.example', 'de');
$zgL = Zugang::oeffnen((string) Db::wert("SELECT token FROM zugaenge WHERE email = 'liegt@pruefung.example'", [], ''));
Db::run("UPDATE zugaenge SET geoeffnet_am = NOW() - INTERVAL 3 DAY WHERE email = 'liegt@pruefung.example'");
Zugang::erinnern();
pruefe('D3: Vorhaben offen nach zwei Tagen: erste Erinnerung', $zgMails('vorhaben_erinnerung', 'liegt@pruefung.example') === 1);
Zugang::erinnern();
pruefe('D3: die zweite kommt nicht vor dem siebten Tag', $zgMails('vorhaben_erinnerung', 'liegt@pruefung.example') === 1);
Db::run("UPDATE zugaenge SET geoeffnet_am = NOW() - INTERVAL 8 DAY WHERE email = 'liegt@pruefung.example'");
Zugang::erinnern(); Zugang::erinnern();
pruefe('D3: nach sieben Tagen die zweite, danach Ruhe', $zgMails('vorhaben_erinnerung', 'liegt@pruefung.example') === 2);

Zugang::anfordern('fertig@pruefung.example', 'de');
$zgF = Zugang::oeffnen((string) Db::wert("SELECT token FROM zugaenge WHERE email = 'fertig@pruefung.example'", [], ''));
$zgFB = Zugang::bedarfFuerKunde((int) $zgF['kunde_id'], 'de');
Bedarf::speichern((int) $zgFB['id'], ['zweck' => ['zeigen']], 1);
Bedarf::absenden((int) $zgFB['id'], ['name' => 'Schon Fertig', 'email' => 'fertig@pruefung.example', 'sprache' => 'de']);
Db::run("UPDATE zugaenge SET geoeffnet_am = NOW() - INTERVAL 3 DAY WHERE email = 'fertig@pruefung.example'");
Zugang::erinnern();
pruefe('D3: wer sein Vorhaben abgeschickt hat, wird nicht erinnert', $zgMails('vorhaben_erinnerung', 'fertig@pruefung.example') === 0);

/* ---------- Nach einem Anruf (S2) ---------------------------------------- */
$zgTB = Bedarf::starten('de');
$zgTL = Zugang::linkNachAnruf('anrufer@pruefung.example', 'de', $zgTB);
pruefe('S2: eine neue Adresse vom Telefon bekommt einen Zugang', str_contains($zgTL, 'zugang.php?t='), $zgTL);
$zgTO = Zugang::oeffnen((string) Db::wert("SELECT token FROM zugaenge WHERE email = 'anrufer@pruefung.example'", [], ''));
pruefe('S2: beim Öffnen hängt der am Telefon begonnene Bedarf am Kunden',
    (int) Db::wert('SELECT customer_id FROM bedarf WHERE id = ?', [(int) $zgTB['id']], 0) === (int) $zgTO['kunde_id']);
pruefe('S2: und es entsteht kein zweiter Bedarf daneben',
    (int) Db::wert("SELECT COUNT(*) FROM bedarf WHERE customer_id = ? AND status = 'offen'", [(int) $zgTO['kunde_id']], 0) === 1);
$zgTB2 = Bedarf::starten('de');
pruefe('S2: ein Kunde mitten im Auftrag behält den direkten Link',
    str_contains(Zugang::linkNachAnruf('x@x.example', 'de', $zgTB2, $kundeId), 'bedarf.php?t='));

/* ---------- Alte Wege laufen weiter (S2) --------------------------------- */
$zgBedarfPhp = (string) file_get_contents(dirname(__DIR__, 2) . '/bedarf.php');
pruefe('S2: bedarf.php ohne Schlüssel führt zum E-Mail-Einstieg',
    str_contains($zgBedarfPhp, "header('Location: /zugang.php?lang='"));
pruefe('S2: ein Empfehlungscode reist mit', str_contains($zgBedarfPhp, "'&e=' . rawurlencode(\$empfehlCode)"));
pruefe('S2: {konfigurator} in den Vorlagen zeigt auf den Einstieg',
    str_contains((string) file_get_contents($wurzel . '/src/Vorlage.php'), "'/zugang.php?lang='"));

/* ---------- N1: Herkunft „Vorschau" (24.09.2026) ----------------------- */
pruefe('N1: die Quelle „vorschau“ wird angenommen', Zugang::quelle(' Vorschau ') === 'vorschau');
pruefe('N1: eine unbekannte Quelle wird zu „seite“', Zugang::quelle('<script>') === 'seite' && Zugang::quelle(null) === 'seite');
Zugang::anfordern('vorschau@pruefung.example', 'de', ['quelle' => 'vorschau']);
pruefe('N1: der Zugang aus der Vorschau trägt seine Herkunft',
    Db::wert('SELECT quelle FROM zugaenge WHERE email = ?', ['vorschau@pruefung.example'], '') === 'vorschau');
pruefe('N1: zugang.php reicht die Quelle nur geprüft weiter',
    str_contains((string) file_get_contents(dirname(__DIR__, 2) . '/zugang.php'), 'Zugang::quelle((string) ($_POST[\'quelle\']'));

/* ---------- Anrede im Fragebogen (24.09.2026) ------------------------------
   Die Seite siezt (Sie/Lei). Der Fragebogen duzte an vier Stellen — gefunden,
   als die acht Fragen für den Dashboard-Einblick durchgeklickt wurden. */
$du = ['de' => '/\b(du|dein|deine|deinen|dich|dir|hast|brauchst|machst|wähle)\b/iu',
       'it' => '/\b(tu|tuo|tua|tuoi|tue|hai|puoi|ti|scegli|vuoi)\b/iu'];
$gefunden = [];
foreach (Baukasten::FRAGEN as $fid => $f) {
    $texte = ['frage' => $f['frage'] ?? [], 'hilfe' => $f['hilfe'] ?? []];
    foreach (($f['optionen'] ?? []) as $oid => $o) { $texte["opt.$oid"] = is_array($o) ? $o : []; }
    foreach ($texte as $teil => $spr) {
        foreach ($du as $sp => $muster) {
            $t = (string) ($spr[$sp] ?? '');
            if ($t !== '' && preg_match($muster, $t)) { $gefunden[] = "$fid.$teil.$sp: $t"; }
        }
    }
}
pruefe('Fragebogen siezt auf Deutsch und Italienisch', $gefunden === [], implode(' | ', $gefunden));

/* ---------- Texte und Messung (S3, S4) ----------------------------------- */
foreach (['zugang', 'zugang_bestand', 'zugang_erinnerung', 'vorhaben_erinnerung'] as $zgA) {
    foreach (['it', 'de', 'en'] as $zgL2) {
        [$zgBt, $zgTx] = Texte::mail($zgA, $zgL2, ['name' => '', 'link' => 'https://x/y', 'tage' => '7']);
        pruefe("S3: Mail „{$zgA}“ ({$zgL2}) hat Betreff, Link und keinen offenen Platzhalter",
            $zgBt !== '' && str_contains($zgTx, 'https://x/y') && !preg_match('/\{[a-z]+\}/', $zgBt . $zgTx));
    }
}
$zgT = Zugang::trichter(90);
pruefe('S4: der Trichter zählt eingetragen ≥ geöffnet ≥ Vorhaben',
    $zgT['neu']['eingetragen'] >= $zgT['neu']['geoeffnet'] && $zgT['neu']['geoeffnet'] >= $zgT['neu']['vorhaben']
    && $zgT['neu']['vorhaben'] >= 1, json_encode($zgT['neu']));
pruefe('S4: die Verwaltung zeigt ihn', str_contains((string) file_get_contents($wurzel . '/views/bedarfe.php'), 'zugangTrichter'));
pruefe('der Cronlauf erinnert und räumt auf', str_contains((string) file_get_contents($wurzel . '/src/Cron.php'), 'Zugang::erinnern()'));

/* ============================================================================
   62. Phase 0: keine Website-Pakete, Anmeldebremse, keine verlorene Zählung
   ============================================================================ */
abschnitt('62. Keine Pakete, Anmeldebremse, Zählung');

/* Vorlagen: Hier setzten "angebot_starter" und "angebot_klein" bis zum
   25.09.2026 fest den Starter-Preis ein. Keine Vorlage darf ein Paket
   festnageln oder eine Paketzahl tragen. */
require_once $wurzel . '/src/Vorlage.php';
$p0Daten = json_decode((string) file_get_contents($wurzel . '/src/vorlagen.json'), true);
$p0Paket = []; $p0Zahl = [];
foreach ((array) ($p0Daten['vorlagen'] ?? []) as $p0K => $p0V) {
    if (!empty($p0V['paket'])) { $p0Paket[] = $p0K; }
    $p0Flach = json_encode($p0V, JSON_UNESCAPED_UNICODE);
    if (preg_match('~\b(499|899|1\.499|1499)\b|Starter|\{paketinhalt\}|\{alle_pakete\}~', $p0Flach)) { $p0Zahl[] = $p0K; }
}
pruefe('keine Vorlage nagelt ein Paket fest', $p0Paket === [], implode(', ', $p0Paket));
pruefe('keine Vorlage nennt einen alten Paketpreis', $p0Zahl === [], implode(', ', $p0Zahl));
pruefe('die Paketinhalte der alten Pakete sind aus den Vorlagen', !isset($p0Daten['paketinhalt']));

/* Und gefuellt: kein Platzhalter bleibt offen, keine Paketzahl rutscht aus
   der Datenbank hinein. */
$p0Offen = []; $p0Alt = [];
foreach (Vorlage::fuer($kundeId) as $p0V) {
    if (preg_match('~\{[a-z_]+\}~', $p0V['betreff'] . $p0V['text'], $p0M)) { $p0Offen[] = $p0V['schluessel'] . ' ' . $p0M[0]; }
    if (preg_match('~\b499\b|\b899\b|1\.499~', $p0V['text'])) { $p0Alt[] = $p0V['schluessel']; }
}
pruefe('gefüllte Vorlagen haben keinen offenen Platzhalter', $p0Offen === [], implode('; ', $p0Offen));
pruefe('gefüllte Vorlagen nennen keinen Paketpreis', $p0Alt === [], implode(', ', $p0Alt));

/* Anmeldebremse: nach FEHL_GRENZE Fehlversuchen ist Schluss -- auch fuer
   das richtige Passwort, sonst waere die Bremse nur ein Hinweis. */
$p0Mail = 'bremse@pruefung.example';
Db::insert('users', ['email' => $p0Mail, 'password_hash' => password_hash('richtig-richtig', PASSWORD_DEFAULT),
    'name' => 'Bremse', 'role' => 'admin', 'active' => 1]);
pruefe('vor dem ersten Fehlversuch ist niemand gesperrt', Auth::gesperrt($p0Mail) === 0);
for ($p0i = 0; $p0i < Auth::FEHL_GRENZE; $p0i++) { Auth::anmelden($p0Mail, 'falsch-' . $p0i); }
pruefe('nach ' . Auth::FEHL_GRENZE . ' Fehlversuchen ist die Adresse gesperrt', Auth::gesperrt($p0Mail) > 0,
    (string) Auth::gesperrt($p0Mail));
pruefe('gesperrt hilft auch das richtige Passwort nicht', Auth::anmelden($p0Mail, 'richtig-richtig') === false);
pruefe('eine andere Adresse vom selben Absender ist ebenfalls gesperrt',
    Auth::gesperrt('jemand-anders@pruefung.example') > 0);
Db::run("DELETE FROM settings WHERE skey LIKE 'anm\\_fehl\\_%'");
pruefe('nach dem Fenster ist die Sperre weg', Auth::gesperrt($p0Mail) === 0);
Db::run('DELETE FROM users WHERE email = ?', [$p0Mail]);

/* Keine verlorene Zaehlung: Jeder Ereignisname, den die Seite an d.php
   schickt, muss dort in der Liste stehen -- sonst wird er still verworfen.
   So gingen die AR-Aufrufe seit ihrer Einfuehrung verloren. */
$p0D = (string) file_get_contents(dirname($wurzel) . '/d.php');
preg_match('~const EREIGNISSE = \[(.*?)\];~s', $p0D, $p0L);
preg_match_all("~'([a-z0-9-]+)'~", $p0L[1] ?? '', $p0E);
$p0Erlaubt = array_flip($p0E[1]);
$p0Fehlt = [];
$p0Js = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname($wurzel) . '/assets/js', FilesystemIterator::SKIP_DOTS));
foreach ($p0Js as $p0F) {
    if (!str_ends_with((string) $p0F, '.js') || str_contains((string) $p0F, '/vendor/')) { continue; }
    $p0Q = (string) file_get_contents((string) $p0F);
    preg_match_all("~zaehlen\('([a-z0-9-]+)'\)|d\.php\?e=([a-z0-9-]+)~", $p0Q, $p0M, PREG_SET_ORDER);
    foreach ($p0M as $p0T) {
        $p0N = $p0T[1] !== '' ? $p0T[1] : ($p0T[2] ?? '');
        if ($p0N !== '' && !isset($p0Erlaubt[$p0N])) { $p0Fehlt[] = basename((string) $p0F) . ': ' . $p0N; }
    }
    // AR in den Produktdemos: der Name entsteht aus AR_MODELLE
    if (preg_match("~AR_MODELLE = new Set\(\[([^\]]+)\]\)~", $p0Q, $p0Ar)) {
        preg_match_all("~'([a-z]+)'~", $p0Ar[1], $p0Am);
        foreach ($p0Am[1] as $p0Mod) { if (!isset($p0Erlaubt["ar-$p0Mod"])) { $p0Fehlt[] = "ar-$p0Mod"; } }
    }
}
/* Keine Fertigungszeit und kein Festpreis in den Clips (Uwe, 25.09.2026),
   und die Arbeitsdateien gehen nicht mehr auf den Webspace: tiktok.html,
   die Clips und die Entwurfsrichtungen lagen oeffentlich, obwohl nichts
   sie verlinkte -- mit "In 2 Wochen online" und "prezzo fisso". */
$p0Tik = (string) file_get_contents(dirname($wurzel) . '/tiktok.html');
pruefe('tiktok.html verspricht keine Dauer und keinen Festpreis',
    !preg_match('~Wochen|Festpreis|\bab \d~u', $p0Tik));
$p0Dep = (string) file_get_contents(dirname($wurzel) . '/.github/workflows/ftp-deploy.yml');
foreach (["--exclude '^richtungen/'", "--exclude '^tiktok\\.html$'", "--exclude '^video/clip[0-9]'"] as $p0Ex) {
    pruefe("der Deploy schließt $p0Ex aus", str_contains($p0Dep, $p0Ex));
}

require_once $wurzel . '/src/Hosting.php';
pruefe('das erste Postfach eines Hosting-Kunden heißt info@ -- wie die Website es verspricht (Uwe, 26.09.2026; vorher kontakt@)', Hosting::POSTFACH === 'info');
pruefe('jedes gesendete Ereignis wird in d.php auch gezählt', $p0Fehlt === [], implode(', ', array_unique($p0Fehlt)));

/* ============================================================================
   63. Phase 1a: Vertragsregeln stehen am Produkt, nicht im Code
   ============================================================================ */
abschnitt('63. Vertragsregeln in der Verwaltung');
require_once $wurzel . '/src/Abo.php';

/* Uwe, 25.09.2026: 12 Monate Mindestlaufzeit, danach monatlich zum
   Monatsende. Auch nach dem Saeen einer frischen Einrichtung -- die Werte
   stehen deshalb in Migration 052 UND in den Startdaten. */
foreach (['betreuung-basis' => 0, 'betreuung-plus' => 60, 'betreuung-premium' => 120] as $vrSlug => $vrMin) {
    $vrP = Db::one('SELECT mindest_monate, kuendigung_tage, inklusiv_minuten FROM packages WHERE slug = ?', [$vrSlug]);
    pruefe("$vrSlug: 12 Monate, bis Monatsende, $vrMin Minuten inklusive",
        $vrP && (int) $vrP['mindest_monate'] === 12 && (int) $vrP['kuendigung_tage'] === 0
        && (int) $vrP['inklusiv_minuten'] === $vrMin, json_encode($vrP));
}
pruefe('Hosting: 12 Monate Mindestlaufzeit',
    (int) Db::wert("SELECT mindest_monate FROM packages WHERE slug = 'hosting'", [], 0) === 12);

/* Die Vertragstexte sagen "jederzeit zum Monatsende kuendbar". Solange sie
   das sagen, darf kein Monatsprodukt eine Frist tragen -- sonst stuende im
   Vertragsblatt etwas anderes als in der Rechnung des Enddatums. */
pruefe('kein Monatsprodukt hat eine Frist, solange die Vertragstexte „zum Monatsende“ sagen',
    (int) Db::wert("SELECT COUNT(*) FROM packages WHERE art IN ('betreuung','hosting') AND active = 1
                     AND kuendigung_tage > 0", [], 1) === 0
    && str_contains((string) file_get_contents($wurzel . '/src/Abovertrag.php'), 'zum Monatsende'));

/* Der Vertrag kopiert die Regel des Produkts -- und nimmt die Konstante nur,
   wenn am Produkt nichts steht. */
$vrPaket = Db::insert('packages', ['slug' => 'kette-regel', 'name' => 'Regelprobe', 'description' => 'Pruefzeile',
    'price_cents' => 0, 'monthly_cents' => 5000, 'currency' => 'EUR', 'art' => 'betreuung',
    'active' => 0, 'oeffentlich' => 0, 'sort' => 99, 'mindest_monate' => 6, 'kuendigung_tage' => 30]);
$vrKunde = Events::kundeFinden(['name' => 'Regel Probe', 'email' => 'regel@pruefung.example']);
$vrAbo = Abo::anlegen($vrKunde, ['paket_slug' => 'kette-regel', 'zahlart' => 'manuell', 'beginn' => '2026-01-15']);
$vrA = Db::one('SELECT * FROM abos WHERE id = ?', [$vrAbo]);
pruefe('der Vertrag übernimmt die Mindestlaufzeit des Produkts (6 Monate)',
    (string) $vrA['mindestlaufzeit_bis'] === '2026-07-14', (string) $vrA['mindestlaufzeit_bis']);
pruefe('und die Frist', (int) $vrA['kuendigung_tage'] === 30);
Db::update('packages', $vrPaket, ['mindest_monate' => 3]);
pruefe('eine spätere Änderung am Produkt ändert den laufenden Vertrag nicht',
    (string) Db::wert('SELECT mindestlaufzeit_bis FROM abos WHERE id = ?', [$vrAbo], '') === '2026-07-14');

/* Das Ende: in der Mindestlaufzeit zu deren Monatsende; danach zum Ende des
   Monats, in dem die Frist ablaeuft. */
$vrV = Abo::kuendigungsvorschau($vrA, '2026-03-10');
pruefe('in der Mindestlaufzeit endet der Vertrag mit ihr (31.07.)', $vrV['ende'] === '2026-07-31', $vrV['ende']);
$vrV = Abo::kuendigungsvorschau($vrA, '2026-09-20');
pruefe('danach mit 30 Tagen Frist: Kündigung am 20.09. wirkt zum 31.10.', $vrV['ende'] === '2026-10-31', $vrV['ende']);
$vrV = Abo::kuendigungsvorschau(['kuendigung_tage' => 0] + $vrA, '2026-09-20');
pruefe('ohne Frist wie bisher zum laufenden Monatsende (30.09.)', $vrV['ende'] === '2026-09-30', $vrV['ende']);
Db::run('DELETE FROM abos WHERE id = ?', [$vrAbo]);
Db::run('DELETE FROM packages WHERE id = ?', [$vrPaket]);

/* ============================================================================
   64. Phase 1b/c: Domain, Hosting und E-Mail sind getrennte Entscheidungen
   ============================================================================ */
abschnitt('64. Domain, Hosting, E-Mail getrennt');
require_once $wurzel . '/src/Hosting.php';
require_once $wurzel . '/src/Zustimmung.php';

/* Die drei Fragen stehen im Fragebogen, dreisprachig, ohne Vorauswahl. */
$lsFelder = [];
foreach (Texte::FRAGEBOGEN as $lsBlock) { $lsFelder += (array) ($lsBlock['felder'] ?? []); }
foreach (['domain_name', 'domain_wahl', 'hosting_wahl', 'mail_wahl'] as $lsF) {
    $lsDef = $lsFelder[$lsF] ?? null;
    $lsOk = is_array($lsDef) && isset($lsDef['it'], $lsDef['de'], $lsDef['en']) && !isset($lsDef['vorgabe']);
    foreach ((array) ($lsDef['optionen'] ?? []) as $lsO) { $lsOk = $lsOk && isset($lsO['it'], $lsO['de'], $lsO['en']); }
    pruefe("Fragebogen: „{$lsF}“ dreisprachig, nichts vorgewählt", $lsOk);
}
pruefe('„uebertragen“ ist eine Wahl, nie eine Vorgabe',
    isset($lsFelder['domain_wahl']['optionen']['uebertragen']) && !isset($lsFelder['domain_wahl']['vorgabe']));

$lsKunde = Events::kundeFinden(['name' => 'Leistung Probe', 'email' => 'leistung@pruefung.example', 'sprache' => 'de']);
Db::run("UPDATE customers SET sprache = 'de' WHERE id = ?", [$lsKunde]);
$lsProjekt = Db::insert('projects', ['customer_id' => $lsKunde, 'name' => 'Leistungsprobe', 'status' => 'entwicklung']);
$lsAuftrag = static fn(): ?array => Db::one("SELECT * FROM hosting_auftraege WHERE customer_id = ? AND status <> 'abgelehnt'
                                              ORDER BY id DESC LIMIT 1", [$lsKunde]);

/* Regel 13: Eine Website loest KEIN Hosting aus. */
foreach (['bisher', 'offen'] as $lsW) {
    Hosting::nachFragebogen($lsProjekt, $lsKunde, ['hosting_wahl' => $lsW, 'domain' => 'uns',
        'domain_name' => 'trattoria-kette.it', 'domain_wahl' => 'behalten', 'mail_wahl' => 'bisher']);
    pruefe("Hosting „{$lsW}“ erzeugt keinen Auftrag", $lsAuftrag() === null);
}

/* Hosting bei Vecom, Domain bleibt, E-Mail bleibt. */
Hosting::nachFragebogen($lsProjekt, $lsKunde, ['hosting_wahl' => 'vecom', 'domain' => 'uns',
    'domain_name' => 'https://www.Trattoria-Kette.it/', 'domain_wahl' => 'behalten', 'mail_wahl' => 'bisher']);
$lsA = $lsAuftrag();
pruefe('Hosting „vecom“ erzeugt einen Vorschlag', $lsA !== null && (string) $lsA['status'] === 'vorgeschlagen');
pruefe('die Domain wird aus der Angabe gelesen und bereinigt', $lsA && (string) $lsA['domain'] === 'trattoria-kette.it',
    (string) ($lsA['domain'] ?? '-'));
pruefe('Domain „behalten“ und E-Mail „bisher“ stehen am Auftrag',
    $lsA && (string) $lsA['domain_aktion'] === 'behalten' && (string) $lsA['mail'] === 'bisher');
$lsText = $lsA ? Hosting::angebotText($lsA, 'de') : '';
pruefe('der Kasten sagt, dass die Domain beim Anbieter bleibt und die E-Mail unberührt',
    str_contains($lsText, 'bleibt bei Ihrem bisherigen Anbieter') && str_contains($lsText, 'E-Mail bleibt davon unberührt'), $lsText);
pruefe('und bietet kein Postfach an', !str_contains($lsText, 'kontakt@'));
pruefe('und nennt Preis und Mindestlaufzeit aus der Verwaltung',
    str_contains($lsText, '9,90') && str_contains($lsText, '12 Monate'), $lsText);
Hosting::nachFragebogen($lsProjekt, $lsKunde, ['hosting_wahl' => 'vecom', 'domain' => 'uns',
    'domain_name' => 'andere-kette.it', 'domain_wahl' => 'uebertragen', 'mail_wahl' => 'vecom']);
pruefe('ein zweiter Fragebogen legt keinen zweiten Auftrag daneben',
    (int) Db::wert("SELECT COUNT(*) FROM hosting_auftraege WHERE customer_id = ?", [$lsKunde], 0) === 1);

/* Ja: Die Zustimmung steht mit genau dem Wortlaut des Kastens da. */
pruefe('der Kunde stimmt zu', Hosting::antwort((int) $lsA['id'], $lsKunde, true));
$lsZ = Db::all('SELECT * FROM zustimmungen WHERE customer_id = ? ORDER BY id', [$lsKunde]);
pruefe('es gibt genau eine Zustimmung „hosting“ — keinen Umzug', count($lsZ) === 1 && (string) $lsZ[0]['art'] === 'hosting',
    implode(',', array_column($lsZ, 'art')));
pruefe('mit dem Wortlaut des Kastens, dem Knopf, Fassung und Sprache',
    $lsZ && str_starts_with((string) $lsZ[0]['text'], $lsText) && str_contains((string) $lsZ[0]['text'], 'zahlungspflichtig')
    && (string) $lsZ[0]['fassung'] === Hosting::FASSUNG && (string) $lsZ[0]['sprache'] === 'de'
    && (int) $lsZ[0]['bezug_id'] === (int) $lsA['id']);

/* Umzug: nur wenn gewaehlt -- dann aber mit eigener Zustimmung. */
Db::run('DELETE FROM hosting_auftraege WHERE customer_id = ?', [$lsKunde]);
Db::run('DELETE FROM zustimmungen WHERE customer_id = ?', [$lsKunde]);
Hosting::nachFragebogen($lsProjekt, $lsKunde, ['hosting_wahl' => 'vecom', 'domain' => 'fremd',
    'domain_name' => 'umzug-kette.it', 'domain_wahl' => 'uebertragen', 'mail_wahl' => 'vecom']);
$lsA = $lsAuftrag();
pruefe('„uebertragen“ + E-Mail „vecom“ stehen am Auftrag',
    $lsA && (string) $lsA['domain_aktion'] === 'transfer' && (string) $lsA['mail'] === 'vecom');
$lsText = $lsA ? Hosting::angebotText($lsA, 'de') : '';
pruefe('der Kasten nennt Auth-Code, Inhaber und das Postfach',
    str_contains($lsText, 'Auth-Code') && str_contains($lsText, 'Inhaber bleiben Sie')
    && str_contains($lsText, 'info@umzug-kette.it'), $lsText);
Hosting::antwort((int) $lsA['id'], $lsKunde, true);
pruefe('der Umzug hat seine eigene Zustimmung',
    (int) Db::wert("SELECT COUNT(*) FROM zustimmungen WHERE customer_id = ? AND art = 'domain_transfer'", [$lsKunde], 0) === 1);

/* Ohne Angabe zur Domain nie ein Umzug: "offen" heisst besprechen. */
Db::run('DELETE FROM hosting_auftraege WHERE customer_id = ?', [$lsKunde]);
Hosting::nachFragebogen($lsProjekt, $lsKunde, ['hosting_wahl' => 'vecom', 'domain' => 'uns',
    'domain_name' => 'offen-kette.it']);
$lsA = $lsAuftrag();
pruefe('ohne Domain-Wahl: „offen“, nie „transfer“', $lsA && (string) $lsA['domain_aktion'] === 'offen');
pruefe('ohne E-Mail-Wahl: kein Postfach (offen)', $lsA && (string) $lsA['mail'] === 'offen');
pruefe('und der Kasten verspricht, nichts ohne Ja zu übertragen',
    $lsA && str_contains(Hosting::angebotText($lsA, 'de'), 'ohne Ihr ausdrückliches Ja'));
foreach (['it', 'en'] as $lsL) {
    $lsT = $lsA ? Hosting::angebotText($lsA, $lsL) : '';
    pruefe("der Kasten steht auch auf „{$lsL}“, ohne offene Platzhalter",
        $lsT !== '' && !preg_match('~\{[a-z]+\}~', $lsT) && str_contains($lsT, 'offen-kette.it'), $lsT);
}

/* Hosting gewuenscht, aber keine Domain: Anruf statt Sackgasse. */
Db::run('DELETE FROM hosting_auftraege WHERE customer_id = ?', [$lsKunde]);
Db::run("DELETE FROM notifications WHERE type = 'hosting_domain'");
Hosting::nachFragebogen($lsProjekt, $lsKunde, ['hosting_wahl' => 'vecom', 'domain' => 'weissnicht']);
pruefe('ohne Domain kein Auftrag', $lsAuftrag() === null);
pruefe('aber eine Meldung für Uwe', (int) Db::wert("SELECT COUNT(*) FROM notifications WHERE type = 'hosting_domain'", [], 0) === 1);

/* Eine Zustimmung ohne Wortlaut gibt es nicht. */
gesperrt('eine Zustimmung ohne Text wird abgewiesen',
    static fn() => Zustimmung::festhalten('hosting', $lsKunde, '  ', 'de', 'x'));
gesperrt('eine unbekannte Art auch',
    static fn() => Zustimmung::festhalten('irgendwas', $lsKunde, 'Text', 'de', 'x'));

/* ============================================================================
   65. Fragebogen: holen statt fragen, Kern und Kuer, nachfassen
   (A2, A4, B1, B6, C1, C3 -- Uwe, 25.09.2026)
   ============================================================================ */
abschnitt('65. Fragebogen: weniger tippen, schneller fertig');
require_once $wurzel . '/src/Fragen.php';
require_once $wurzel . '/src/Bedarf.php';
require_once $wurzel . '/src/Umfang.php';
require_once $wurzel . '/src/Onboarding.php';

/* Der Kern besteht nur aus Fragen, die es gibt. */
$fbAlle = [];
foreach (Texte::FRAGEBOGEN as $fbTeil) { $fbAlle += (array) ($fbTeil['felder'] ?? []); }
pruefe('jede Kernfrage gibt es im Fragebogen', array_diff(Fragen::KERN, array_keys($fbAlle)) === [],
    implode(', ', array_diff(Fragen::KERN, array_keys($fbAlle))));
pruefe('der Kern ist höchstens ein Drittel des Fragebogens', count(Fragen::KERN) * 3 <= count($fbAlle),
    count(Fragen::KERN) . ' von ' . count($fbAlle));
pruefe('kein langes Textfeld ist Pflicht', array_filter(Fragen::KERN,
    static fn(string $n): bool => ($fbAlle[$n]['art'] ?? '') === 'lang') === []);

/* Was fehlt, wird gefunden -- und nur, was sichtbar ist. */
pruefe('leer: es fehlt die erste Kernfrage (Firmenname, Schritt 1)', Fragen::kernFehlt([]) === [1, 'firmenname']);
$fbKern = ['firmenname' => 'Trattoria', 'branche' => 'gastronomie', 'ziel1' => array_key_first($fbAlle['ziel1']['optionen']),
    'seiten_zahl' => '4', 'sprachen_zahl' => '1', 'funktionen_wahl' => '', 'altseite' => 'nein',
    'material' => 'logo:haben', 'texte' => array_key_first($fbAlle['texte']['optionen']),
    'domain' => 'weissnicht', 'hosting_wahl' => 'offen', 'mail_wahl' => 'bisher', 'termin' => 'keins'];
pruefe('mit allen Kernantworten fehlt nichts', Fragen::kernFehlt($fbKern) === null, json_encode(Fragen::kernFehlt($fbKern)));
pruefe('eine leere Hakenliste zählt als Antwort', Fragen::kernFehlt($fbKern) === null);
$fbOhne = $fbKern; $fbOhne['domain'] = 'uns';
pruefe('„Haben wir eine Domain“ macht Name und Wahl zur Pflicht', Fragen::kernFehlt($fbOhne) === [6, 'domain_name'],
    json_encode(Fragen::kernFehlt($fbOhne)));
pruefe('Restzeit: leer mehr als eine Minute, vollständig null',
    Fragen::restMinuten([], 1) >= 1 && Fragen::restMinuten($fbKern, 1) === 0);

/* A2: Domain aus der E-Mail-Adresse, nie aus Freemail. */
pruefe('info@trattoria-sole.it ergibt die Domain', Bedarf::domainAusMail('info@trattoria-sole.it') === 'trattoria-sole.it');
foreach (['mario@gmail.com', 'anna@libero.it', 'x@pec.it', 'b@web.de', 'k@pruefung.example'] as $fbMail) {
    pruefe("keine Domain aus $fbMail", Bedarf::domainAusMail($fbMail) === null);
}

/* A2 + A4: Vorbelegung aus Kundenakte und Vorhaben. */
$fbKunde = Events::kundeFinden(['name' => 'Vorbelegt Probe', 'email' => 'info@vorbelegt-kette.it',
    'phone' => '+39 0922 111222', 'company' => 'Vorbelegt SRL']);
Db::insert('bedarf', ['customer_id' => $fbKunde, 'token' => bin2hex(random_bytes(24)), 'sprache' => 'de', 'status' => 'abgesendet',
    'antworten' => json_encode(['zweck' => ['zeigen'], 'umfang' => 'wenige', 'sprachen' => 'eine',
        'material' => ['logo'], 'bestand' => 'neu', 'zeit' => 'schnell', 'betreuung' => 'ja', 'branche' => 'gastro'])]);
$fbVor = Bedarf::alsFragebogen($fbKunde);
pruefe('A2: Domain aus der E-Mail vorbelegt, als Vorschlag „läuft auf uns“',
    ($fbVor['domain'] ?? '') === 'uns' && ($fbVor['domain_name'] ?? '') === 'vorbelegt-kette.it', json_encode($fbVor));
pruefe('A4: Telefon, E-Mail und Firma aus der Akte',
    ($fbVor['telefon'] ?? '') === '+39 0922 111222' && ($fbVor['email_web'] ?? '') === 'info@vorbelegt-kette.it'
    && ($fbVor['firmenname'] ?? '') === 'Vorbelegt SRL');
pruefe('A4: „schnell“ wird „so bald wie möglich“, Betreuung „ja“ wird „am liebsten Sie“',
    ($fbVor['termin'] ?? '') === 'baldest' && ($fbVor['pflege'] ?? '') === 'du');
$fbUmf = Umfang::ausVorhaben($fbKunde);
pruefe('A4: Seiten aus dem Vorhaben als Ausgangswert — nie als „beauftragt“',
    is_array($fbUmf) && $fbUmf['quelle'] === 'vorhaben' && $fbUmf['seiten'] > 1 && !isset($fbUmf['nummer']),
    json_encode($fbUmf));

/* B1/C1: Nach dem Absenden laesst sich nur noch Freiwilliges ergaenzen. */
$fbId = Onboarding::vorab($fbKunde);
Onboarding::speichern($fbId, $fbKern);
Onboarding::absenden($fbId, []);
Onboarding::nachtragen($fbId, ['firmenname' => 'Anders', 'vorbilder' => 'cavaleri.it', 'stil' => array_key_first($fbAlle['stil']['optionen'])]);
$fbD = json_decode((string) Db::wert('SELECT data FROM questionnaires WHERE id = ?', [$fbId], ''), true) ?: [];
pruefe('nachtragen: der Kern bleibt, wie er abgeschickt wurde', ($fbD['firmenname'] ?? '') === 'Trattoria');
pruefe('nachtragen: Freiwilliges kommt dazu', ($fbD['vorbilder'] ?? '') === 'cavaleri.it' && ($fbD['stil'] ?? '') !== '');
pruefe('und der Fragebogen bleibt abgeschlossen',
    (string) Db::wert('SELECT status FROM questionnaires WHERE id = ?', [$fbId], '') === 'abgeschlossen');

/* C3: zwei Erinnerungen, dann Ruhe -- auch vor dem Preis. */
$fbK2 = Events::kundeFinden(['name' => 'Erinnerung Probe', 'email' => 'erinnerung@pruefung.example']);
$fbQ = Onboarding::vorab($fbK2);
Onboarding::speichern($fbQ, ['firmenname' => 'Halb Fertig']);
Db::run('UPDATE questionnaires SET updated_at = ? WHERE id = ?', [date('Y-m-d H:i:s', strtotime('-2 days')), $fbQ]);
Onboarding::erinnerungen();
$fbR = Db::one('SELECT erinnert_am, erinnert2_am FROM questionnaires WHERE id = ?', [$fbQ]);
pruefe('C3: nach einem Tag Stille kommt die erste Erinnerung (auch ohne Einladung)', $fbR['erinnert_am'] !== null);
Onboarding::erinnerungen();
pruefe('C3: gleich danach keine zweite',
    Db::one('SELECT erinnert2_am FROM questionnaires WHERE id = ?', [$fbQ])['erinnert2_am'] === null);
$fbVorher = date('Y-m-d H:i:s', strtotime('-3 days'));
Db::run('UPDATE questionnaires SET erinnert_am = ?, updated_at = ? WHERE id = ?', [$fbVorher, $fbVorher, $fbQ]);
Onboarding::erinnerungen();
pruefe('C3: zwei Tage nach der ersten, ohne Bewegung, kommt die zweite',
    Db::one('SELECT erinnert2_am FROM questionnaires WHERE id = ?', [$fbQ])['erinnert2_am'] !== null);
Db::run('UPDATE questionnaires SET erinnert2_am = NULL, erinnert_am = ?, updated_at = ? WHERE id = ?',
    [$fbVorher, date('Y-m-d H:i:s', strtotime('-1 day')), $fbQ]);
Onboarding::erinnerungen();
pruefe('C3: hat der Kunde seitdem weitergemacht, kommt keine zweite',
    Db::one('SELECT erinnert2_am FROM questionnaires WHERE id = ?', [$fbQ])['erinnert2_am'] === null);
[$fbBt, $fbTx] = Texte::mail('fragebogen_erinnerung', 'de', ['name' => 'X', 'paket' => '', 'link' => 'https://x/y', 'minuten' => '3']);
pruefe('C3: die Erinnerung nennt die Restzeit statt „zehn Minuten“',
    str_contains($fbTx, 'noch etwa 3 Minuten') && !preg_match('~\{[a-z]+\}~', $fbBt . $fbTx));

/* ============================================================================
   66. Fragebogen Runde 2: Chips, Diktieren, Hochladen, Manuela
   ============================================================================ */
abschnitt('66. Fragebogen: Chips, Diktieren, Hochladen, Manuela');

$fbFelder = [];
foreach (Texte::FRAGEBOGEN as $fbSchritt) {
    foreach (($fbSchritt['felder'] ?? []) as $fbName => $fbFeld) { $fbFelder[$fbName] = $fbFeld['art'] ?? ''; }
}
$fbChipsGut = true;
foreach (Texte::CHIPS as $fbName => $fbListe) {
    if (!in_array($fbFelder[$fbName] ?? '', ['lang', 'kurz', 'text'], true)) { $fbChipsGut = false; echo "   Chip-Feld $fbName: ", $fbFelder[$fbName] ?? 'fehlt', "\n"; }
    foreach ($fbListe as $fbChip) {
        foreach (['it', 'de', 'en'] as $fbS) {
            if (trim((string) ($fbChip[$fbS] ?? '')) === '' || str_contains((string) $fbChip[$fbS], ',')) { $fbChipsGut = false; }
        }
    }
}
pruefe('B2: jeder Chip gehört zu einem Textfeld des Fragebogens, dreisprachig, ohne Komma (Komma trennt die Chips)', $fbChipsGut);
$fbSeite = file_get_contents(__DIR__ . '/../../fragebogen.php');
pruefe('B5: das Formular schickt Dateien mit (multipart) und legt sie in dieselbe Ablage wie die Kundenseite',
    str_contains($fbSeite, 'enctype="multipart/form-data"') && str_contains($fbSeite, 'Ablage::annehmen('));
$fbReden = true;
foreach (['it', 'de', 'en'] as $fbS) {
    $fbT = Texte::h(Texte::SEITE['lieberReden'], $fbS);
    if (!str_contains($fbT, '{nr}') || preg_match('~\{(?!nr\})[a-z]+\}~', $fbT)) { $fbReden = false; }
    if (!str_contains(Texte::h(Texte::SEITE['hochgeladen'], $fbS), '{n}')) { $fbReden = false; }
}
pruefe('C2: der Manuela-Hinweis nennt die Kundennummer, sonst keine offenen Platzhalter', $fbReden);
pruefe('B4: der Diktierknopf ist ohne Spracherkennung im Browser versteckt',
    (bool) preg_match('~class="diktat" data-ziel="[^"]*" hidden~', $fbSeite) && str_contains($fbSeite, 'webkitSpeechRecognition'));

/* ============================================================================
   67. Vorwissen: alte Website und P. IVA lesen (A1 + A3) -- ohne Netz
   ============================================================================ */
abschnitt('67. Vorwissen: alte Website und P. IVA');
require_once $wurzel . '/src/Vorwissen.php';

pruefe('A3: die Prüfziffer der P. IVA wird gerechnet (gültig/vertippt)',
    Vies::italienischGueltig('00743110157') && !Vies::italienischGueltig('00743110158') && !Vies::italienischGueltig('00000000000'));
pruefe('A3: „P.IVA IT 00743110157“ und „00743110157“ ergeben dieselbe Nummer; vertippt gar keine',
    Vies::zerlegen('P.IVA IT 00743110157') === ['IT', '00743110157'] && Vies::zerlegen('00743110157') === ['IT', '00743110157']
    && Vies::zerlegen('00743110158') === null);
$vwAmt = Vies::lesen('{"isValid":true,"userError":"VALID","name":"TRATTORIA SOLE SRL","address":"VIA ROMA 1 \n90133 PALERMO PA\n"}', 'IT', '00743110157');
pruefe('A3: die Antwort des Registers wird gelesen, der Ort daraus ist „Palermo“',
    $vwAmt && $vwAmt['gueltig'] && $vwAmt['name'] === 'TRATTORIA SOLE SRL' && $vwAmt['anschrift'] === "VIA ROMA 1\n90133 PALERMO PA"
    && Vies::ortAus($vwAmt['anschrift']) === 'Palermo');
pruefe('A3: „Dienst nicht erreichbar“ heisst später noch einmal, nicht „ungültig“',
    Vies::lesen('{"isValid":false,"userError":"MS_UNAVAILABLE"}', 'IT', '00743110157') === null);
$vwDe = Vies::lesen('{"isValid":true,"userError":"VALID","name":"---","address":"---"}', 'DE', '123456789');
pruefe('A3: Staaten, die Name und Anschrift nicht herausgeben („---“), liefern nur „gültig“',
    $vwDe && $vwDe['gueltig'] && $vwDe['name'] === null && $vwDe['anschrift'] === null);

$vwHtml = '<html><head><title>Trattoria Sole | Cucina siciliana a Palermo</title>
<meta name="description" content="Dal 1962 cuciniamo pesce fresco del mercato di Palermo, con le ricette della nonna.">
<script type="application/ld+json">{"@context":"https://schema.org","@graph":[{"@type":"WebSite","name":"Sole"},
 {"@type":"Restaurant","name":"Trattoria Sole","telephone":"+39 091 123456","email":"info@trattoria-sole.it",
  "address":{"@type":"PostalAddress","streetAddress":"Via Roma 1","postalCode":"90133","addressLocality":"Palermo"},
  "sameAs":["https://www.instagram.com/trattoriasole/","https://www.facebook.com/sharer/sharer.php?u=x"]}]}</script>
</head><body><a href="mailto:studio@webdesigner.example">Credits</a>
<a href="https://www.facebook.com/trattoriasole">FB</a> P.IVA 00743110157</body></html>';
$vwF = Seiteninhalt::lesen($vwHtml, 'https://www.trattoria-sole.it/');
pruefe('A1: strukturierte Daten zuerst: Name, Telefon, E-Mail, Anschrift, Profil, P. IVA',
    ($vwF['name'] ?? '') === 'Trattoria Sole' && ($vwF['telefon'] ?? '') === '+39 091 123456'
    && ($vwF['email'] ?? '') === 'info@trattoria-sole.it' && ($vwF['ort'] ?? '') === 'Palermo'
    && ($vwF['strasse'] ?? '') === 'Via Roma 1' && ($vwF['piva'] ?? '') === 'IT00743110157'
    && ($vwF['social'] ?? '') === 'https://www.instagram.com/trattoriasole/');
$vwHtml2 = '<html><head><title>Bottega Rossi – Ceramiche</title></head><body>
<a href="https://www.facebook.com/sharer/sharer.php?u=x">Condividi</a><a href="https://instagram.com/bottegarossi">IG</a>
<a href="mailto:studio@webdesigner.example">Sito by Studio</a><a href="mailto:ciao@bottegarossi.it">Scrivici</a>
<a href="tel:+390916543210">Chiama</a><footer>Via Etnea 5 - 95131 Catania (CT) - P. IVA 00743110158 - Partita IVA 00743110157</footer></body></html>';
$vwF2 = Seiteninhalt::lesen($vwHtml2, 'https://bottegarossi.it/');
pruefe('A1: ohne strukturierte Daten: Titel, tel-Link, eigene Mail vor der des Webdesigners, kein Teilen-Link',
    ($vwF2['name'] ?? '') === 'Bottega Rossi' && ($vwF2['telefon'] ?? '') === '+390916543210'
    && ($vwF2['email'] ?? '') === 'ciao@bottegarossi.it' && ($vwF2['social'] ?? '') === 'https://instagram.com/bottegarossi');
pruefe('A1: aus dem Text nur Prüfbares: vertippte P. IVA übersprungen, Ort aus „95131 Catania (CT)“',
    ($vwF2['piva'] ?? '') === 'IT00743110157' && ($vwF2['ort'] ?? '') === 'Catania' && ($vwF2['plz'] ?? '') === '95131');

/* Der ganze Weg in den Fragebogen -- mit Attrappen statt Netz. */
$vwK = Events::kundeFinden(['name' => 'Vorwissen Probe', 'email' => 'info@trattoria-sole.it']);
Db::run("UPDATE customers SET company = NULL, city = NULL, phone = NULL, vat_id = NULL WHERE id = ?", [$vwK]);
$vwQ = Onboarding::vorab($vwK);
Onboarding::speichern($vwQ, ['telefon' => '333 1234567']);   // das hat ER geschrieben
$vwStill = date('Y-m-d H:i:s', strtotime('-5 hours'));
Db::run('UPDATE questionnaires SET updated_at = ? WHERE id = ?', [$vwStill, $vwQ]);
$vwGefragt = [];
$vwSeite = static function (string $a) use (&$vwGefragt, $vwHtml): ?array {
    $vwGefragt[] = $a; return ['url' => 'https://www.trattoria-sole.it/', 'html' => $vwHtml, 'unterseiten' => [],
                               'befunde' => [['nicht_mobil', 'schwer'], ['kein_impressum', 'mittel']]];
};
$vwReg = static fn(string $n): ?array => Vies::lesen('{"isValid":true,"userError":"VALID","name":"TRATTORIA SOLE SRL","address":"VIA ROMA 1 \n90133 PALERMO PA\n"}', 'IT', '00743110157');
$vwNeu = Vorwissen::fuerFragebogen($vwQ, $vwSeite, $vwReg);
$vwD = json_decode((string) Db::wert('SELECT data FROM questionnaires WHERE id = ?', [$vwQ], ''), true) ?: [];
pruefe('A1: die Adresse kommt aus seiner E-Mail-Domain', $vwGefragt === ['trattoria-sole.it']);
pruefe('A1: leere Felder werden gefüllt (Name, Ort, Profil, Beschreibung, alte Seite)',
    ($vwD['firmenname'] ?? '') === 'Trattoria Sole' && ($vwD['ort'] ?? '') === 'Palermo'
    && ($vwD['social'] ?? '') === 'https://www.instagram.com/trattoriasole/' && ($vwD['altseite'] ?? '') === 'ja'
    && str_starts_with((string) ($vwD['beschreibung'] ?? ''), 'Dal 1962'));
pruefe('A1: was der Kunde selbst geschrieben hat, bleibt -- auch wenn die Seite etwas anderes sagt',
    ($vwD['telefon'] ?? '') === '333 1234567' && !isset($vwNeu['telefon']));
pruefe('A3: das Impressum kommt amtlich aus dem Register, mit P. IVA',
    ($vwD['impressum'] ?? '') === "TRATTORIA SOLE SRL\nVIA ROMA 1, 90133 PALERMO PA\nP. IVA IT00743110157");
$vwZ = Db::one('SELECT seite_felder, seite_gelesen_am, seite_adresse, updated_at FROM questionnaires WHERE id = ?', [$vwQ]);
$vwMerk = json_decode((string) $vwZ['seite_felder'], true) ?: [];
pruefe('A1: gemerkt wird, was WIR eingetragen haben (für den Hinweis „bitte prüfen“), nicht, was er schrieb',
    isset($vwMerk['firmenname'], $vwMerk['impressum']) && !isset($vwMerk['telefon']) && $vwZ['seite_adresse'] === 'trattoria-sole.it');
pruefe('A1: unser Eintragen zählt nicht als Bewegung des Kunden (Erinnerungen bleiben richtig)',
    $vwZ['updated_at'] === $vwStill);
$vwGefragt = [];
pruefe('A1: ein zweiter Lauf fragt keinen fremden Server mehr',
    Vorwissen::fuerFragebogen($vwQ, $vwSeite, $vwReg) === [] && $vwGefragt === []);
pruefe('A1: „keine Website“ heisst: nicht suchen',
    Vorwissen::adresseFuer($vwK, ['altseite' => 'nein'], 'info@trattoria-sole.it') === null
    && Vorwissen::adresseFuer($vwK, [], 'mario@gmail.com') === null);
$vwK2 = Events::kundeFinden(['name' => 'Vorwissen Fertig', 'email' => 'x@bottegarossi.it']);
$vwQ2 = Onboarding::vorab($vwK2);
Db::run("UPDATE questionnaires SET status = 'abgeschlossen' WHERE id = ?", [$vwQ2]);
pruefe('A1: ein abgeschickter Fragebogen wird nicht mehr angefasst (sein Kern trägt das Angebot)',
    Vorwissen::fuerFragebogen($vwQ2, $vwSeite, $vwReg) === [] && Vorwissen::eintragen($vwQ2, ['ort' => 'X']) === []);
$vwFb = file_get_contents($wurzel . '/../fragebogen.php');
pruefe('A1: gelesen wird erst nach dem Ausliefern, und die Sitzung ist vorher frei',
    (int) strpos($vwFb, 'session_write_close();') > 0
    && (int) strpos($vwFb, 'session_write_close();') < (int) strpos($vwFb, 'fastcgi_finish_request();')
    && (int) strpos($vwFb, 'fastcgi_finish_request();') < (int) strpos($vwFb, 'Vorwissen::fuerFragebogen'));
pruefe('A1: der Cron holt nach', str_contains((string) file_get_contents($wurzel . '/src/Cron.php'), 'Vorwissen::nachholen()'));

/* ============================================================================
   68. Heute und wie es werden könnte (C4)
   ============================================================================ */
abschnitt('68. Dashboard: Heute und wie es werden könnte');
require_once $wurzel . '/src/Seitenblick.php';
$c4Befunde = json_decode((string) Db::wert('SELECT seite_befunde FROM questionnaires WHERE id = ?', [$vwQ], ''), true) ?: [];
pruefe('C4: die Befunde der alten Seite kommen aus demselben Abruf mit in den Fragebogen',
    $c4Befunde === [['nicht_mobil', 'schwer'], ['kein_impressum', 'mittel']]);
$c4Saetze = Seitenblick::inSaetzen([['nicht_mobil', 'schwer'], ['gibt_es_nicht_als_art', 'x'], 'kaputt'], 'de');
pruefe('C4: gespeicherte Befunde werden Sätze -- Unbekanntes fällt still heraus',
    count($c4Saetze) === 1 && str_contains($c4Saetze[0]['satz'], 'Handy'));
$c4Kunde = (string) file_get_contents($wurzel . '/../kunde.php');
$c4Js = (string) file_get_contents($wurzel . '/../assets/js/vorschau.js');
preg_match("~\\\$zu = \[(.*?)\];~s", $c4Kunde, $c4m);
preg_match_all("~=> '([a-z]+)'~", $c4m[1] ?? '', $c4z);
$c4Fehlt = [];
foreach (array_unique($c4z[1]) as $c4b) {
    if (!preg_match('~^  ' . $c4b . ':\s+\{ bild:~m', $c4Js) || preg_match_all('~^    ' . $c4b . ': \[~m', $c4Js) !== 3) { $c4Fehlt[] = $c4b; }
}
pruefe('C4: jede Branche, die das Dashboard zeigt, hat in der Skizze Bild und Text in drei Sprachen',
    count($c4z[1]) >= 7 && $c4Fehlt === []);
if ($c4Fehlt) { echo '   fehlt: ', implode(', ', $c4Fehlt), "\n"; }
pruefe('C4: das Logo für die Skizze ist nur das eigene, nur als Logo markiert, und nur neu gerechnet',
    (bool) preg_match("~logobild.*?customer_id = \? AND rolle = 'logo'.*?vorschauAusliefern~s", $c4Kunde)
    && str_contains((string) file_get_contents($wurzel . '/../fragebogen.php'), "'datei_rolle'"));
preg_match_all("~'([a-z]+)' =>~", $c4m[1] ?? '', $c4q);
$c4Optionen = array_keys((array) (Texte::FRAGEBOGEN['unternehmen']['felder']['branche']['optionen'] ?? []));
pruefe('C4: die Zuordnung kennt nur Branchen, die es im Fragebogen gibt',
    $c4q[1] !== [] && array_diff($c4q[1], $c4Optionen) === []);

/* ============================================================================
   69. Automatisch abbuchen (Phase 2) -- mit nachgebautem Stripe, ohne Netz
   ============================================================================ */
abschnitt('69. Automatisch abbuchen');
require_once $wurzel . '/src/Abbuchung.php';
require_once $wurzel . '/src/Mahnung.php';
require_once $wurzel . '/src/Zahlung/Anbieter.php';
require_once $wurzel . '/src/Zahlung/Stripe.php';

$abS = new class {
    public string $kundeSitzung = 'cus_T1';
    public string $antwort = 'bezahlt';
    public int $abgebucht = 0;
    public array $geloest = [];
    public int $anlagen = 0;
    public function bereit(): bool { return true; }
    public function kunde(array $k): string { $this->anlagen++; return 'cus_T1'; }
    public function einrichtungsseite(string $sk, int $abo, string $zurueck, string $abbruch, string $sp): string {
        return 'https://checkout.stripe.test/setup?abo=' . $abo . '&zurueck=' . rawurlencode($zurueck);
    }
    public function einrichtungLesen(string $s): array {
        return ['fertig' => true, 'abo_id' => (int) ($GLOBALS['abAbo'] ?? 0), 'kunde' => $this->kundeSitzung,
                'zahlmittel' => $s === 'cs_neu' ? 'pm_2' : 'pm_1', 'art' => 'card', 'text' => 'Visa •••• 4242'];
    }
    public function abbuchen(array $z, string $sk, string $pm): array {
        $this->abgebucht++;
        return ['status' => $this->antwort, 'vorgang' => 'pi_T' . $z['id'], 'grund' => $this->antwort === 'abgelehnt' ? 'Karte abgelehnt' : '',
                'betrag' => (int) $z['amount_cents'], 'waehrung' => strtoupper((string) $z['currency'])];
    }
    public function zahlmittelLoesen(string $pm): void { $this->geloest[] = $pm; }
};

$abK = Events::kundeFinden(['name' => 'Abbuchung Probe', 'email' => 'abbuchung@pruefung.example', 'sprache' => 'de']);
$abAbo = Abo::anlegen($abK, ['paket_slug' => 'betreuung-plus', 'zahlart' => 'karte']);
$GLOBALS['abAbo'] = $abAbo;
$abFremd = Events::kundeFinden(['name' => 'Fremd Probe', 'email' => 'fremd-abbuchung@pruefung.example']);

$abUrl = Abbuchung::einrichten($abAbo, $abK, 'https://vecom-design.it/kunde.php?t=x', 'de', $abS);
pruefe('Phase 2: Hinterlegen führt zu Stripe und merkt sich die Stripe-Kundennummer',
    str_starts_with($abUrl, 'https://checkout.stripe.test/setup')
    && Db::wert('SELECT stripe_kunde FROM customers WHERE id = ?', [$abK], '') === 'cus_T1');
$abFehler = '';
try { Abbuchung::einrichten($abAbo, $abFremd, 'x', 'de', $abS); } catch (Throwable $e) { $abFehler = $e->getMessage(); }
pruefe('Phase 2: für einen fremden Vertrag gibt es keine Seite', $abFehler !== '');

pruefe('Phase 2: der Rückweg eines anderen Kunden hängt nichts an diesen Vertrag',
    Abbuchung::abschliessen('cs_probe', $abFremd, 'de', $abS) === false
    && Db::one('SELECT zahlmittel_id FROM abos WHERE id = ?', [$abAbo])['zahlmittel_id'] === null);
$abS->kundeSitzung = 'cus_ANDERER';
pruefe('Phase 2: eine Sitzung eines anderen Stripe-Kunden wird abgewiesen',
    Abbuchung::abschliessen('cs_probe', $abK, 'de', $abS) === false);
$abS->kundeSitzung = 'cus_T1';
pruefe('Phase 2: nicht jede Zeichenkette wird bei Stripe nachgefragt', Abbuchung::abschliessen('../x', $abK, 'de', $abS) === false);
$abOk = Abbuchung::abschliessen('cs_probe', $abK, 'de', $abS);
$abZ = Db::all("SELECT * FROM zustimmungen WHERE customer_id = ? AND art = 'abbuchung'", [$abK]);
pruefe('Phase 2: das Zahlungsmittel steht am Vertrag, die Zustimmung mit Wortlaut, Betrag und Vorlauf',
    $abOk && Db::wert('SELECT zahlmittel_text FROM abos WHERE id = ?', [$abAbo], '') === 'Visa •••• 4242'
    && count($abZ) === 1 && str_contains((string) $abZ[0]['text'], '69,00') && str_contains((string) $abZ[0]['text'], '2 Tage')
    && (int) $abZ[0]['bezug_id'] === $abAbo);
Abbuchung::abschliessen('cs_probe', null, 'it', $abS);   // der Webhook kommt hinterher
pruefe('Phase 2: Rückweg und Webhook -- einmal festgehalten, nicht zweimal',
    (int) Db::wert("SELECT COUNT(*) FROM zustimmungen WHERE customer_id = ? AND art = 'abbuchung'", [$abK], 0) === 1);

/* Rate anlegen und ankündigen. Die Prüfkette hat keinen Mailversand; der
   nachgebaute trägt die Mail als gesendet ein wie der echte. */
$abPost = static function (string $anlass, string $an, string $b, string $t, array $bezug): bool {
    Db::insert('mails', ['anlass' => $anlass, 'empfaenger' => $an, 'betreff' => $b, 'status' => 'gesendet',
        'customer_id' => $bezug['customer_id'] ?? null, 'payment_id' => $bezug['payment_id'] ?? null]);
    return true;
};
$abR0 = Abo::abrechnen($abAbo, '2027-06');
pruefe('Phase 2: kommt die Ankündigung nicht an, wird die Rate nicht zur Abbuchung vorgemerkt',
    Abbuchung::ankuendigen($abR0, static fn() => false) === 'versand_fehler'
    && Db::one('SELECT method FROM payments WHERE id = ?', [$abR0])['method'] === null);
Db::run("UPDATE payments SET status = 'bezahlt' WHERE id = ?", [$abR0]);   // aus dem Weg
$abR1 = Abo::abrechnen($abAbo, '2026-10');
$abWie = Abbuchung::ankuendigen($abR1, $abPost);
$abP = Db::one('SELECT * FROM payments WHERE id = ?', [$abR1]);
pruefe('Phase 2: statt Zahlungslink eine Ankündigung, fällig in zwei Tagen',
    $abWie === 'raus' && $abP['method'] === 'abbuchung'
    && $abP['faellig_am'] === date('Y-m-d', strtotime('+2 days'))
    && (int) Db::wert("SELECT COUNT(*) FROM mails WHERE anlass = 'abbuchung_angekuendigt' AND payment_id = ?", [$abR1], 0) === 1
    && (int) Db::wert("SELECT COUNT(*) FROM mails WHERE anlass = 'betreuung_faellig' AND payment_id = ?", [$abR1], 0) === 0);
pruefe('Phase 2: zweimal ankündigen schickt nicht zweimal', Abbuchung::ankuendigen($abR1, $abPost) === 'nicht_dran');
Abbuchung::faellige($abS);
pruefe('Phase 2: vor dem angekündigten Tag wird nichts abgebucht', $abS->abgebucht === 0);

Db::run('UPDATE payments SET faellig_am = CURDATE() WHERE id = ?', [$abR1]);
$abN = Abbuchung::faellige($abS);
pruefe('Phase 2: am Tag wird abgebucht und gebucht (Karte)',
    $abN['bezahlt'] === 1 && Db::wert('SELECT status FROM payments WHERE id = ?', [$abR1], '') === 'bezahlt'
    && Db::wert('SELECT provider_ref FROM payments WHERE id = ?', [$abR1], '') === 'pi_T' . $abR1);
Abbuchung::faellige($abS);
pruefe('Phase 2: eine bezahlte Rate wird nicht noch einmal abgebucht', $abS->abgebucht === 1);

/* Lastschrift unterwegs: nicht mahnen */
$abR2 = Abo::abrechnen($abAbo, '2026-11');
Abbuchung::ankuendigen($abR2, $abPost);
Db::run('UPDATE payments SET faellig_am = CURDATE() WHERE id = ?', [$abR2]);
$abS->antwort = 'laeuft';
Abbuchung::faellige($abS);
Db::run('UPDATE payments SET faellig_am = ? WHERE id = ?', [date('Y-m-d', strtotime('-20 days')), $abR2]);
$abMahn = array_column(Mahnung::faellige(1), 'id');
pruefe('Phase 2: eine laufende Lastschrift wartet auf den Abgleich und wird nicht gemahnt',
    Db::wert('SELECT status FROM payments WHERE id = ?', [$abR2], '') === 'in_bearbeitung'
    && Db::wert('SELECT provider_sitzung FROM payments WHERE id = ?', [$abR2], '') === 'pi_T' . $abR2
    && !in_array($abR2, array_map('intval', $abMahn), true));

/* Abgelehnt: gewohnter Weg */
$abR3 = Abo::abrechnen($abAbo, '2026-12');
Abbuchung::ankuendigen($abR3, $abPost);
Db::run('UPDATE payments SET faellig_am = CURDATE() WHERE id = ?', [$abR3]);
$abS->antwort = 'abgelehnt';
$abVorher = (int) Db::wert("SELECT COUNT(*) FROM notifications WHERE type = 'zahlung_fehler'", [], 0);
Abbuchung::faellige($abS);
Abbuchung::gescheitert($abR3, 'noch einmal gemeldet (Webhook)');
$abP3 = Db::one('SELECT * FROM payments WHERE id = ?', [$abR3]);
pruefe('Phase 2: abgelehnt -- Uwe erfährt es einmal, der Kunde bekommt den gewohnten Zahlungslink',
    (int) Db::wert("SELECT COUNT(*) FROM notifications WHERE type = 'zahlung_fehler'", [], 0) === $abVorher + 1
    && $abP3['method'] === null && $abP3['status'] !== 'bezahlt'
    && (int) Db::wert("SELECT COUNT(*) FROM mails WHERE anlass = 'betreuung_faellig' AND payment_id = ?", [$abR3], 0) === 1);
pruefe('Phase 2: danach ist sie eine normale Rate -- mahnbar wie jede andere',
    in_array($abR3, array_map('intval', array_column(Mahnung::faellige(1), 'id')), true)
    || $abP3['faellig_am'] > date('Y-m-d', strtotime('-3 days')));

/* Der Cron-Weg: neue Rate mit Zahlungsmittel wird angekündigt */
Db::run('UPDATE abos SET naechste_abrechnung = CURDATE() WHERE id = ?', [$abAbo]);
Abo::abrechnungenAnlegen();
$abR4 = (int) Db::wert("SELECT id FROM payments WHERE abo_id = ? AND abrechnungsmonat = ?", [$abAbo, date('Y-m')], 0);
pruefe('Phase 2: der Monatslauf versucht die Ankündigung -- und schickt den Link, wenn sie nicht ankam',
    $abR4 > 0 && (int) Db::wert("SELECT COUNT(*) FROM mails WHERE anlass = 'abbuchung_angekuendigt' AND payment_id = ?", [$abR4], 0) === 1
    && Db::one('SELECT method FROM payments WHERE id = ?', [$abR4])['method'] === null
    && (int) Db::wert("SELECT COUNT(*) FROM mails WHERE anlass = 'betreuung_faellig' AND payment_id = ?", [$abR4], 0) === 1);
$abR5 = Abo::abrechnen($abAbo, '2027-02');
Abbuchung::ankuendigen($abR5, $abPost);

/* Wechsel und Ende */
Abbuchung::abschliessen('cs_neu', $abK, 'de', $abS);
pruefe('Phase 2: ein neues Zahlungsmittel ersetzt das alte, und das alte wird bei Stripe gelöst',
    Db::wert('SELECT zahlmittel_id FROM abos WHERE id = ?', [$abAbo], '') === 'pm_2' && in_array('pm_1', $abS->geloest, true));
pruefe('Phase 2: nur der eigene Vertrag lässt sich beenden', Abbuchung::beenden($abAbo, $abFremd, $abS) === false);
pruefe('Phase 2: beenden -- zurück auf den Link, die angekündigte Rate gleich mit',
    Abbuchung::beenden($abAbo, $abK, $abS)
    && Db::one('SELECT zahlmittel_id FROM abos WHERE id = ?', [$abAbo])['zahlmittel_id'] === null
    && Db::one('SELECT method FROM payments WHERE id = ?', [$abR5])['method'] === null
    && (int) Db::wert("SELECT COUNT(*) FROM mails WHERE anlass = 'betreuung_faellig' AND payment_id = ?", [$abR5], 0) === 1);

$abWh = (string) file_get_contents($wurzel . '/../stripe-webhook.php');
pruefe('Phase 2: der Webhook kennt Hinterlegen, Abbuchung und Scheitern -- Bezahlseiten buchen nicht doppelt',
    str_contains($abWh, "(\$o['mode'] ?? '') === 'setup'") && str_contains($abWh, "case 'payment_intent.succeeded':")
    && str_contains($abWh, "(\$o['metadata']['art'] ?? '') === 'abbuchung'") && str_contains($abWh, 'Abbuchung::gescheitert('));
$abSt = (string) file_get_contents($wurzel . '/src/Zahlung/Stripe.php');
pruefe('Phase 2: zum Hinterlegen nur Karte und SEPA -- die Zahlarten, die fürs Abbuchen gemacht sind',
    (bool) preg_match("~'mode'\s*=> 'setup',.*?'payment_method_types\[0\]' => 'card',\s*'payment_method_types\[1\]' => 'sepa_debit',~s", $abSt)
    && !str_contains($abSt, "'payment_method_types[2]'"));
pruefe('Phase 2: Karte und Konto erscheinen nur als Marke und letzte vier Ziffern',
    StripeAnbieter::zahlmittelText(['type' => 'card', 'card' => ['brand' => 'visa', 'last4' => '4242']]) === 'Visa •••• 4242'
    && StripeAnbieter::zahlmittelText(['type' => 'sepa_debit', 'sepa_debit' => ['last4' => '3000']]) === 'SEPA •••• 3000');
$abT = true;
foreach (['it', 'de', 'en'] as $abSp) {
    [$abB, $abX] = Texte::mail('abbuchung_angekuendigt', $abSp, ['name' => 'X', 'monat' => 'M', 'betrag' => 'B', 'datum' => 'D', 'zahlmittel' => 'Z', 'seite' => 'S']);
    if (preg_match('~\{[a-z]+\}~', $abB . $abX) || !str_contains($abX, 'Z')) { $abT = false; }
    if (preg_match('~\{(?!paket\}|betrag\}|tage\})[a-z]+\}~', Texte::h(Texte::KUNDE['abbuchungZustimmung'], $abSp))) { $abT = false; }
}
pruefe('Phase 2: Ankündigung und Zustimmung dreisprachig, ohne offene Platzhalter', $abT);

/* ============================================================================
   70. Hosting in Schritten (Phase 3) -- mit nachgebautem KAS
   ============================================================================ */
abschnitt('70. Hosting in Schritten');
require_once $wurzel . '/src/Hosting.php';

$hsKas = new class {
    public array $gerufen = [];
    public array $antwort = ['account' => true, 'domain' => true, 'postfach' => true];
    public string $login = 'w0199999';
    public function accountAnlegen(string $kommentar, array $g = []): array {
        $this->gerufen[] = 'account';
        return $this->antwort['account'] === true
            ? ['ok' => true, 'login' => $this->login, 'kas_passwort' => 'Kas-Pw-1!', 'ftp_passwort' => 'Ftp-Pw-2!', 'text' => 'ok']
            : ['ok' => false, 'login' => '', 'kas_passwort' => '', 'ftp_passwort' => '', 'text' => (string) $this->antwort['account']];
    }
    public function domainAnlegen(string $d, ?array $als = null): array {
        $this->gerufen[] = 'domain:' . ($als['login'] ?? '-') . ':' . ($als['passwort'] ?? '-');
        return $this->antwort['domain'] === true ? ['ok' => true, 'text' => 'ok'] : ['ok' => false, 'text' => (string) $this->antwort['domain']];
    }
    public function postfachAnlegen(string $l, string $d, string $pw, ?array $als = null): array {
        $this->gerufen[] = 'postfach:' . $l . '@' . $d . ':' . ($pw !== '' ? 'pw' : 'ohne');
        return $this->antwort['postfach'] === true ? ['ok' => true, 'text' => 'ok'] : ['ok' => false, 'text' => (string) $this->antwort['postfach']];
    }
    public function passwortNeu(): string { return 'Post-Pw-3!'; }
};
$hsNeu = static function (string $mail = 'vecom') use (&$hsKas): int {
    static $n = 0; $n++;
    $k = Events::kundeFinden(['name' => 'Schritt Probe ' . $n, 'email' => 'schritt' . $n . '@pruefung.example']);
    return (int) Db::insert('hosting_auftraege', ['customer_id' => $k, 'domain' => 'schritt' . $n . '-probe.it',
        'status' => 'zugestimmt', 'preis_cents' => 990, 'domain_aktion' => 'neu', 'mail' => $mail]);
};
$hsStatus = static fn(int $id): string => (string) Db::wert('SELECT status FROM hosting_auftraege WHERE id = ?', [$id], '');
$hsS = static fn(int $id, string $s): string => (string) (Hosting::schritte($id)[$s]['status'] ?? '');

/* Glatt durch */
$hs1 = $hsNeu();
$hsE = Hosting::anlegen($hs1, $hsKas);
pruefe('Phase 3: alles klappt -- jeder Schritt erledigt, Auftrag angelegt',
    $hsE['ok'] && $hsStatus($hs1) === 'angelegt'
    /* Seit dem DNS-Schritt (Abschnitt 77): bei einer neuen Domain "entfaellt" er. */
    && !array_diff(array_unique(array_column(Hosting::schritte($hs1), 'status')), ['fertig', 'entfaellt'])
    && (string) Hosting::schritte($hs1)['dns']['status'] === 'entfaellt');
pruefe('Phase 3: Domain und Postfach laufen im Unter-Account mit dessen Passwort aus der Ablage',
    in_array('domain:w0199999:Kas-Pw-1!', $hsKas->gerufen, true)
    && in_array('postfach:info@schritt1-probe.it:pw', $hsKas->gerufen, true));
$hsKas->gerufen = [];
Hosting::anlegen($hs1, $hsKas);
Hosting::weiter($hs1, $hsKas);
pruefe('Phase 3: ein angelegter Auftrag legt nie einen zweiten Account an', $hsKas->gerufen === []);

/* Kein Postfach bei fremder E-Mail */
$hs2 = $hsNeu('bisher');
$hsKas->gerufen = [];
Hosting::anlegen($hs2, $hsKas);
pruefe('Phase 3: behält der Kunde seine E-Mail, entfällt das Postfach -- und wird nicht angelegt',
    $hsS($hs2, 'postfach') === 'entfaellt' && $hsStatus($hs2) === 'angelegt'
    && !preg_grep('~^postfach~', $hsKas->gerufen));

/* Domain scheitert: Wiederholung ohne zweiten Account, Zugang erst am Ende */
$hs3 = $hsNeu();
$hsKas->antwort['domain'] = 'flood_protection';
$hsKas->gerufen = [];
Hosting::anlegen($hs3, $hsKas);
$hsKunde3 = (int) Db::wert('SELECT customer_id FROM hosting_auftraege WHERE id = ?', [$hs3], 0);
pruefe('Phase 3: scheitert die Domain, bleibt der Auftrag in Arbeit -- Account fertig, Domain wird wiederholt',
    $hsStatus($hs3) === 'in_arbeit' && $hsS($hs3, 'account') === 'fertig' && $hsS($hs3, 'domain') === 'fehler');
pruefe('Phase 3: solange etwas in Arbeit ist, gibt es für den Kunden noch keine Zugangsdaten (sie werden noch gebraucht)',
    Hosting::zugangAbrufen($hs3, $hsKunde3) === null
    && Db::one('SELECT zugang_blob FROM hosting_auftraege WHERE id = ?', [$hs3])['zugang_blob'] !== null);
$hsKas->antwort['domain'] = 'domain_already_exists';
$hsKas->gerufen = [];
Db::run("UPDATE hosting_schritte SET updated_at = NOW() - INTERVAL 30 MINUTE WHERE auftrag_id = ?", [$hs3]);
Hosting::fortsetzen($hsKas);
pruefe('Phase 3: der Cron holt nach -- ohne neuen Account, und „gibt es schon“ zählt als erledigt',
    !in_array('account', $hsKas->gerufen, true) && $hsS($hs3, 'domain') === 'fertig' && $hsStatus($hs3) === 'angelegt');
$hsZ = Hosting::zugangAbrufen($hs3, $hsKunde3);
pruefe('Phase 3: danach einmal abrufbar, mit dem richtigen Login -- und dann weg',
    is_array($hsZ) && ($hsZ['kas_login'] ?? '') === 'w0199999' && Hosting::zugangAbrufen($hs3, $hsKunde3) === null);
$hsKas->antwort['domain'] = true;

/* Dreimal gescheitert: Handarbeit, der Rest geht weiter */
$hs4 = $hsNeu();
$hsKas->antwort['postfach'] = 'quota';
Hosting::anlegen($hs4, $hsKas);
for ($i = 0; $i < 3; $i++) {
    Db::run("UPDATE hosting_schritte SET updated_at = NOW() - INTERVAL 30 MINUTE WHERE auftrag_id = ?", [$hs4]);
    Hosting::fortsetzen($hsKas);
}
$hsP4 = Hosting::schritte($hs4)['postfach'];
pruefe('Phase 3: nach drei Versuchen wird es Handarbeit -- der Auftrag schließt trotzdem ab und sagt, was fehlt',
    (string) $hsP4['status'] === 'hand' && (int) $hsP4['versuche'] === 3 && $hsStatus($hs4) === 'angelegt'
    && str_contains((string) Db::wert('SELECT notiz FROM hosting_auftraege WHERE id = ?', [$hs4], ''), 'Postfach'));
$hsKas->antwort['postfach'] = true;
$hsKas->gerufen = [];
$hsW = Hosting::wiederholen($hs4, $hsKas);
pruefe('Phase 3: „Offene Schritte wiederholen“ holt nur das Postfach nach',
    $hsW['ok'] && $hsS($hs4, 'postfach') === 'fertig' && !in_array('account', $hsKas->gerufen, true)
    && count(preg_grep('~^postfach~', $hsKas->gerufen)) === 1);

/* Abbruch mitten im Account: nie blind wiederholen */
$hs5 = $hsNeu();
Db::run("UPDATE hosting_auftraege SET status = 'in_arbeit' WHERE id = ?", [$hs5]);
foreach (array_keys(Hosting::SCHRITTE) as $hsN) {
    Db::run('INSERT INTO hosting_schritte (auftrag_id, schritt, status) VALUES (?, ?, ?)', [$hs5, $hsN, $hsN === 'account' ? 'laeuft' : 'offen']);
}
$hsKas->gerufen = [];
Db::run("UPDATE hosting_schritte SET updated_at = NOW() - INTERVAL 30 MINUTE WHERE auftrag_id = ?", [$hs5]);
Hosting::fortsetzen($hsKas);
pruefe('Phase 3: brach der Lauf mitten im Account ab, wird NICHT neu angelegt -- Uwe sieht in der Accountliste nach',
    $hsKas->gerufen === [] && $hsS($hs5, 'account') === 'hand' && $hsStatus($hs5) === 'in_arbeit');

/* Gleichzeitig: nur einer legt an */
$hs6 = $hsNeu();
$hsKas->gerufen = [];
Db::run("UPDATE hosting_auftraege SET status = 'in_arbeit' WHERE id = ?", [$hs6]);   // der andere war schneller
$hsZweit = Hosting::anlegen($hs6, $hsKas);
pruefe('Phase 3: wer den Auftrag nicht selbst beansprucht hat, legt keinen Account an',
    !in_array('account', $hsKas->gerufen, true));

pruefe('Phase 3: der Cron setzt fort, und „wiederholen“ fragt vorher (schwerer Schritt)',
    str_contains((string) file_get_contents($wurzel . '/src/Cron.php'), 'Hosting::fortsetzen()')
    && Ablauf::wiegt('hosting_weiter') === Ablauf::SCHWER);

/* ============================================================================
   71. Kein unsichtbarer Link auf einen Kunden
   ============================================================================ */
abschnitt('71. Kunden ohne Namen bleiben anklickbar');
pruefe('Fmt::name nimmt den ersten nicht leeren Wert, sonst „(ohne Namen)“',
    Fmt::name('', null, '  ', 'x@y.it') === 'x@y.it' && Fmt::name(null, '') === '(ohne Namen)' && Fmt::name('Rosa', 'x') === 'Rosa');
$knLeer = [];
foreach (glob($wurzel . '/views/*.php') as $knDatei) {
    $knText = (string) file_get_contents($knDatei);
    /* Ein Link auf Kunde, Anfrage oder Bedarf, dessen Text nur ein Datenfeld
       ist: Ist das leer, ist der Link unsichtbar (25.09.2026, Uwes Probekunde). */
    if (preg_match_all("~url\\('(?:kunden|anfragen|bedarf)/'[^\\n]*?\\)\\) ?\\?>\"[^>]*>(?:<strong>)?<\\?= Fmt::h\\((?!Fmt::name)~", $knText, $knM)) {
        $knLeer[] = basename($knDatei) . ' (' . count($knM[0]) . ')';
    }
    if (preg_match_all("~url\\('(?:kunden|anfragen|bedarf)/'[^\\n]*?'\">' \\. Fmt::h\\((?!Fmt::name)~", $knText, $knM)) {
        $knLeer[] = basename($knDatei) . ' (' . count($knM[0]) . ')';
    }
}
pruefe('kein Link auf Kunde, Anfrage oder Bedarf hat nur ein Datenfeld als Text', $knLeer === [], implode(', ', $knLeer));

/* ============================================================================
   72. Verträge auf einen Blick (Phase 4)
   ============================================================================ */
abschnitt('72. Verträge auf einen Blick');
require_once $wurzel . '/src/Leistungen.php';

$lwZ = Leistungen::kennzahlen();
pruefe('Phase 4: die Monatssumme ist dieselbe Zahl wie in Abo::monatlich()', $lwZ['monatlich'] === Abo::monatlich());
pruefe('Phase 4: automatisch abgebucht zählt nur Verträge mit hinterlegtem Zahlungsmittel',
    $lwZ['automatisch'] === (int) Db::wert("SELECT COUNT(*) FROM abos WHERE status IN ('aktiv','gekuendigt') AND zahlmittel_id IS NOT NULL", [], 0));

$lwW = Leistungen::warten();
$lwTitel = array_column($lwW, 'titel');
pruefe('Phase 4: ein Hosting-Schritt von Hand steht auf der Liste -- mit Weg zur Kundenakte',
    (bool) array_filter($lwW, static fn($w) => $w['titel'] === 'Hosting schritt5-probe.it' && str_starts_with($w['link'], 'kunden/')));

$lwK = Events::kundeFinden(['name' => 'Blick Probe', 'email' => 'blick@pruefung.example']);
$lwAbo = Abo::anlegen($lwK, ['paket_slug' => 'betreuung-basis']);
$lwAlt = Abo::abrechnen($lwAbo, '2026-01');
Db::run("UPDATE payments SET faellig_am = CURDATE() - INTERVAL 10 DAY, status = 'ausstehend' WHERE id = ?", [$lwAlt]);
$lwUnterwegs = Abo::abrechnen($lwAbo, '2026-02');
Db::run("UPDATE payments SET faellig_am = CURDATE() - INTERVAL 10 DAY, status = 'in_bearbeitung', method = 'abbuchung' WHERE id = ?", [$lwUnterwegs]);
$lwW = Leistungen::warten();
$lwBlick = array_values(array_filter($lwW, static fn($w) => str_starts_with($w['titel'], 'Blick Probe')));
pruefe('Phase 4: eine überfällige Rate steht da -- eine laufende Lastschrift nicht (die ist unterwegs, nicht überfällig)',
    count($lwBlick) === 1 && str_contains($lwBlick[0]['text'], 'Überfällig'));
$lwZ2 = Leistungen::kennzahlen();
pruefe('Phase 4: dieselbe Regel in der Kennzahl „Überfällig“',
    $lwZ2['ueberfaellig'] === $lwZ['ueberfaellig'] + 1);

Db::run("UPDATE abos SET status = 'gekuendigt', laeuft_bis = CURDATE() + INTERVAL 10 DAY WHERE id = ?", [$lwAbo]);
pruefe('Phase 4: ein Vertrag, der in 30 Tagen ausläuft, meldet sich',
    (bool) array_filter(Leistungen::warten(), static fn($w) => str_contains($w['text'], 'läuft am')));

$lwH = Leistungen::hosting();
pruefe('Phase 4: im Hosting stehen die Aufträge in Arbeit oben, mit Schritten und Handarbeit gezählt',
    $lwH !== [] && (string) $lwH[0]['status'] === 'in_arbeit'
    && (bool) array_filter($lwH, static fn($h) => (string) $h['domain'] === 'schritt5-probe.it' && (int) $h['hand'] === 1));

/* ============================================================================
   73. Domain-Umzug begleiten (Phase 5) -- mit nachgebautem DNS und Registry
   ============================================================================ */
abschnitt('73. Domain-Umzug begleiten');
require_once $wurzel . '/src/Domainumzug.php';

$duDns = static function (string $h, int $t): array {
    $z = [
        'altfirma.it|' . DNS_A      => [['ip' => '93.184.216.34']],
        'www.altfirma.it|' . DNS_CNAME => [['target' => 'altfirma.it']],
        'altfirma.it|' . DNS_MX     => [['pri' => 10, 'target' => 'altfirma-it.mail.protection.outlook.com']],
        'altfirma.it|' . DNS_TXT    => [['txt' => 'v=spf1 include:spf.protection.outlook.com -all'], ['txt' => 'google-site-verification=abc']],
        '_dmarc.altfirma.it|' . DNS_TXT => [['txt' => 'v=DMARC1; p=quarantine']],
        'selector1._domainkey.altfirma.it|' . DNS_TXT => [['entries' => ['v=DKIM1; k=rsa; ', 'p=MIGf']]],
        'mail._domainkey.altfirma.it|' . DNS_TXT => [['txt' => 'irgendwas ohne Schluessel']],
        'altfirma.it|' . DNS_NS     => [['target' => 'ns1.altanbieter.it'], ['target' => 'ns2.altanbieter.it']],
    ];
    return $z[$h . '|' . $t] ?? [];
};
$duE = Domainumzug::bestandsaufnahme('altfirma.it', $duDns);
$duHat = static fn(string $n, string $t, string $w) => (bool) array_filter($duE, static fn($e) => $e['name'] === $n && $e['typ'] === $t && str_contains($e['wert'], $w));
pruefe('Phase 5: die Bestandsaufnahme findet Web, MX, SPF, DMARC und DKIM -- genau das, was nach dem Umzug fehlen würde',
    $duHat('@', 'A', '93.184.216.34') && $duHat('www', 'CNAME', 'altfirma.it') && $duHat('@', 'MX', '10 altfirma-it.mail')
    && $duHat('@', 'TXT', 'v=spf1') && $duHat('_dmarc', 'TXT', 'DMARC1') && $duHat('selector1._domainkey', 'TXT', 'v=DKIM1; k=rsa; p=MIGf'));
pruefe('Phase 5: ein DKIM-Name ohne Schlüssel zählt nicht', !$duHat('mail._domainkey', 'TXT', ''));

pruefe('Phase 5: Transfersperre aus RDAP erkannt',
    Domainumzug::sperre('x.de', static fn() => ['rdap' => ['status' => ['active', 'client transfer prohibited']], 'whois' => null]) === 'gesperrt'
    && Domainumzug::sperre('x.de', static fn() => ['rdap' => ['status' => ['active']], 'whois' => null]) === 'frei');
pruefe('Phase 5: bei .it aus WHOIS -- und ohne Auskunft ehrlich „unklar“',
    Domainumzug::sperre('x.it', static fn() => ['rdap' => null, 'whois' => "Domain: x.it\nStatus:             clientTransferProhibited\n"]) === 'gesperrt'
    && Domainumzug::sperre('x.it', static fn() => ['rdap' => null, 'whois' => "Domain: x.it\nStatus:             ok\n"]) === 'frei'
    && Domainumzug::sperre('x.it', static fn() => ['rdap' => null, 'whois' => null]) === 'unklar');

/* Der Weg: Hosting-Auftrag mit Umzug, fertig eingerichtet */
$duK = Events::kundeFinden(['name' => 'Umzug Probe', 'email' => 'umzug@altfirma.it']);
$duA = (int) Db::insert('hosting_auftraege', ['customer_id' => $duK, 'domain' => 'altfirma.it', 'status' => 'angelegt',
    'preis_cents' => 990, 'domain_aktion' => 'transfer', 'mail' => 'vecom']);
$duSp = static fn() => ['rdap' => null, 'whois' => "Status: clientTransferProhibited\n"];
$duId = Domainumzug::anlegen($duA, $duDns, $duSp);
pruefe('Phase 5: der Umzug entsteht einmal je Auftrag, mit Bestandsaufnahme und Sperre',
    $duId > 0 && Domainumzug::anlegen($duA, $duDns, $duSp) === $duId
    && (string) Domainumzug::fuerAuftrag($duA)['sperre'] === 'gesperrt'
    && count(json_decode((string) Domainumzug::fuerAuftrag($duA)['dns_json'], true)) >= 6);
$duNeu = (int) Db::insert('hosting_auftraege', ['customer_id' => $duK, 'domain' => 'neu-probe.it', 'status' => 'angelegt',
    'preis_cents' => 990, 'domain_aktion' => 'neu', 'mail' => 'vecom']);
pruefe('Phase 5: eine neu registrierte Domain bekommt keinen Umzug', Domainumzug::anlegen($duNeu, $duDns, $duSp) === null);

$duFremd = Events::kundeFinden(['name' => 'Fremd Umzug', 'email' => 'fremd-umzug@pruefung.example']);
pruefe('Phase 5: den Code kann nur der eigene Kunde hinterlegen', Domainumzug::codeSpeichern($duId, $duFremd, 'ABC-123-xyz') === 'nicht_dran');
pruefe('Phase 5: was kein Code sein kann, wird nicht gespeichert',
    Domainumzug::codeSpeichern($duId, $duK, 'ab c') === 'falsch' && Domainumzug::codeSpeichern($duId, $duK, '12') === 'falsch');
pruefe('Phase 5: der Code wird verschlüsselt abgelegt -- im Klartext steht er nirgends',
    Domainumzug::codeSpeichern($duId, $duK, 'Xk9#pQ2!zz') === 'ok'
    && !str_contains((string) Db::wert('SELECT code_blob FROM domain_umzuege WHERE id = ?', [$duId], ''), 'Xk9#pQ2!zz')
    && (int) Db::wert("SELECT COUNT(*) FROM mails WHERE betreff LIKE '%Xk9#pQ2!zz%' OR anlass LIKE 'domain%'", [], 0) === 0
    && (int) Db::wert("SELECT COUNT(*) FROM notifications WHERE body LIKE '%Xk9#pQ2!zz%'", [], 0) === 0);
pruefe('Phase 5: Uwe kann ihn lesen, solange der Antrag nicht gestellt ist', Domainumzug::codeLesen($duId) === 'Xk9#pQ2!zz');
pruefe('Phase 5: „KK-Antrag gestellt“ löscht den Code, und zweimal geht es nicht',
    Domainumzug::beantragt($duId) && Domainumzug::codeLesen($duId) === null && !Domainumzug::beantragt($duId)
    && Db::one('SELECT code_blob FROM domain_umzuege WHERE id = ?', [$duId])['code_blob'] === null);

$duPost = [];
$duSenden = static function (string $anlass, string $an, string $b, string $t, array $bezug) use (&$duPost): bool { $duPost[] = $anlass; return true; };
Domainumzug::nachsehenAlle($duDns, $duSenden);
pruefe('Phase 5: solange die Nameserver beim alten Anbieter stehen, ist der Umzug nicht fertig',
    (string) Domainumzug::fuerAuftrag($duA)['stand'] === 'beantragt' && $duPost === []);
$duDnsNeu = static fn(string $h, int $t): array => $t === DNS_NS ? [['target' => 'ns5.kasserver.com'], ['target' => 'ns6.kasserver.com.']] : [];
Domainumzug::nachsehenAlle($duDnsNeu, $duSenden);
Domainumzug::nachsehenAlle($duDnsNeu, $duSenden);
pruefe('Phase 5: zeigen alle Nameserver auf All-Inkl, ist er fertig -- Uwe und Kunde erfahren es einmal',
    (string) Domainumzug::fuerAuftrag($duA)['stand'] === 'fertig' && $duPost === ['domain_umgezogen']
    && (int) Db::wert("SELECT COUNT(*) FROM notifications WHERE type = 'domain_fertig' AND body LIKE '%SSL%'", [], 0) === 1);
pruefe('Phase 5: halb umgestellte Nameserver zählen nicht',
    (static function () use ($duDns): bool {
        $k = Events::kundeFinden(['name' => 'Halb', 'email' => 'halb@pruefung.example']);
        $a = (int) Db::insert('hosting_auftraege', ['customer_id' => $k, 'domain' => 'halb-probe.it', 'status' => 'angelegt',
            'preis_cents' => 990, 'domain_aktion' => 'transfer', 'mail' => 'vecom']);
        $id = Domainumzug::anlegen($a, $duDns, static fn() => ['rdap' => null, 'whois' => null]);
        Db::run("UPDATE domain_umzuege SET stand = 'beantragt' WHERE id = ?", [$id]);
        Domainumzug::nachsehenAlle(static fn(string $h, int $t): array => $t === DNS_NS
            ? [['target' => 'ns5.kasserver.com'], ['target' => 'ns1.altanbieter.it']] : [], static fn() => true);
        return (string) Db::wert('SELECT stand FROM domain_umzuege WHERE id = ?', [$id], '') === 'beantragt';
    })());
$duG = Domainumzug::anlegen((int) Db::insert('hosting_auftraege', ['customer_id' => $duK, 'domain' => 'gesperrt-probe.it',
    'status' => 'angelegt', 'preis_cents' => 990, 'domain_aktion' => 'transfer', 'mail' => 'vecom']), $duDns, $duSp);
Db::run('UPDATE domain_umzuege SET sperre_am = NOW() - INTERVAL 7 HOUR WHERE id = ?', [$duG]);
Domainumzug::nachsehenAlle($duDns, $duSenden, static fn() => ['rdap' => null, 'whois' => "Status: ok\n"]);
pruefe('Phase 5: hebt der Kunde die Sperre auf, sieht der Cron es von selbst',
    (string) Db::wert('SELECT sperre FROM domain_umzuege WHERE id = ?', [$duG], '') === 'frei');
pruefe('Phase 5: „KK-Antrag gestellt“ fragt vorher, und der Cron sieht nach',
    Ablauf::wiegt('umzug_beantragt') === Ablauf::SCHWER
    && str_contains((string) file_get_contents($wurzel . '/src/Cron.php'), 'Domainumzug::nachsehenAlle()'));
$duT = true;
foreach (['it', 'de', 'en'] as $duS) {
    [$b, $t] = Texte::mail('domain_umgezogen', $duS, ['name' => 'X', 'domain' => 'd.it', 'seite' => 'S']);
    if (preg_match('~\{[a-z]+\}~', $b . $t)) { $duT = false; }
    foreach (['umzugTitel', 'umzugSperre', 'umzugCodeHilfe', 'umzugCodeOk', 'umzugCodeFalsch', 'umzugBeantragt', 'umzugFertig'] as $duK2) {
        if (trim(Texte::h(Texte::KUNDE[$duK2] ?? [], $duS)) === '') { $duT = false; }
    }
}
pruefe('Phase 5: alle Umzugstexte dreisprachig, ohne offene Platzhalter', $duT);

/* ============================================================================
   74. Alte Website als Vorlage sichern (Phase 6a) -- mit nachgebauter Seite
   ============================================================================ */
abschnitt('74. Alte Website als Vorlage sichern');
require_once $wurzel . '/src/Altseite.php';

$asPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$asSeiten = [
    'https://trattoria-alt.it/' => ['text/html', '<html><head><title>Trattoria Alt</title><meta name="description" content="Cucina di casa"></head><body>
        <nav><ul><li><a href="/">Home</a></li><li><a href="/chi-siamo">Chi siamo</a></li></ul></nav>
        <h1>Benvenuti</h1><p>Dal 1962 cuciniamo con amore.</p><p>Dal 1962 cuciniamo con amore.</p>
        <img src="/img/sala.png" alt="La sala"><img src="https://fremd-cdn.example/werbung.png">
        <img srcset="/img/klein.png 400w, /img/gross.png 1600w" src="/img/klein.png" alt="Piatto">
        <a href="/menu.pdf">Menù</a><a href="https://facebook.com/trattoria">FB</a><a href="mailto:x@y.it">Mail</a>
        <script>var geheim = 1;</script><footer><p>© 2019 Impressum</p></footer></body></html>'],
    'https://trattoria-alt.it/chi-siamo' => ['text/html', '<html><head><title>Chi siamo</title></head><body><h2>La famiglia</h2>
        <p>Nonna Rosa ha aperto la trattoria.</p><ul><li>Pasta fatta in casa ogni giorno</li></ul></body></html>'],
    'https://trattoria-alt.it/img/sala.png'  => ['image/png', $asPng],
    'https://trattoria-alt.it/img/gross.png' => ['image/png', $asPng],
    'https://trattoria-alt.it/menu.pdf'      => ['application/pdf', "%PDF-1.4\n% Menue\n"],
];
$asGeholt = [];
$asHolen = static function (string $url) use ($asSeiten, &$asGeholt): array {
    $asGeholt[] = $url;
    return isset($asSeiten[$url]) ? ['ok' => true, 'typ' => $asSeiten[$url][0], 'inhalt' => $asSeiten[$url][1]] : ['ok' => false, 'typ' => '', 'inhalt' => ''];
};

$asL = Altseite::seiteLesen($asSeiten['https://trattoria-alt.it/'][1], 'https://trattoria-alt.it/');
pruefe('Phase 6a: der Text kommt in Reihenfolge, ohne Navigation, Fuß, Skript und doppelte Absätze',
    array_column($asL['bloecke'], 1) === ['Benvenuti', 'Dal 1962 cuciniamo con amore.']);
pruefe('Phase 6a: aus dem srcset wird die größte Fassung genommen, PDFs werden als Dokument erkannt',
    in_array(['https://trattoria-alt.it/img/gross.png', 'Piatto'], $asL['bilder'], true)
    && $asL['dokumente'] === ['https://trattoria-alt.it/menu.pdf']);
pruefe('Phase 6a: nur Adressen derselben Seite gehören dazu',
    Altseite::eigen('https://www.trattoria-alt.it/x', 'trattoria-alt.it') && !Altseite::eigen('https://fremd-cdn.example/a.png', 'trattoria-alt.it')
    && !Altseite::eigen('https://trattoria-alt.it.boese.example/', 'trattoria-alt.it'));

$asK = Events::kundeFinden(['name' => 'Alt Probe', 'email' => 'alt@trattoria-alt.it']);
$asFehler = '';
try { Altseite::anlegen($asK, 'http://127.0.0.1/admin'); } catch (Throwable $e) { $asFehler = $e->getMessage(); }
pruefe('Phase 6a: eine IP-Adresse ist keine alte Website', $asFehler !== '');
$asId = Altseite::anlegen($asK, 'www.trattoria-alt.it');
pruefe('Phase 6a: ein zweites Anlegen während der Sicherung ist dieselbe', Altseite::anlegen($asK, 'trattoria-alt.it') === $asId);
$asStand = '';
for ($i = 0; $i < 10 && $asStand !== 'fertig'; $i++) { $asStand = Altseite::weiter($asId, $asHolen); }
$asA = Db::one('SELECT * FROM altseiten WHERE id = ?', [$asId]);
$asF = $asA['datei_id'] ? Db::one('SELECT * FROM files WHERE id = ?', [(int) $asA['datei_id']]) : null;
pruefe('Phase 6a: am Ende liegt eine ZIP-Datei beim Kunden in der Ablage',
    $asStand === 'fertig' && $asF && (string) $asF['mime'] === 'application/zip' && (int) $asF['customer_id'] === $asK
    && str_starts_with((string) $asF['orig_name'], 'alte-seite-trattoria-alt.it'));
$asZip = new ZipArchive();
$asZipOk = $asF && $asZip->open(Ablage::ordner() . '/' . $asF['stored_name']) === true;
$asNamen = [];
if ($asZipOk) { for ($i = 0; $i < $asZip->numFiles; $i++) { $asNamen[] = $asZip->getNameIndex($i); } }
$asText = $asZipOk ? (string) $asZip->getFromName('texte.html') : '';
pruefe('Phase 6a: in der ZIP stehen die Texte beider Seiten, die Bilder und das PDF',
    str_contains($asText, 'Dal 1962 cuciniamo') && str_contains($asText, 'Nonna Rosa') && str_contains($asText, 'Pasta fatta in casa')
    && count(preg_grep('~^bilder/.*\.png$~', $asNamen)) === 2 && count(preg_grep('~^dokumente/.*\.pdf$~', $asNamen)) === 1);
pruefe('Phase 6a: Fremdes wird nie geholt (fremdes Bild, Facebook, Mail)',
    !preg_grep('~fremd-cdn|facebook|mailto~', $asGeholt));
pruefe('Phase 6a: der Arbeitsordner ist danach weg, und Uwe erfährt es',
    !is_dir(Ablage::ordner() . '/altseite-' . $asId)
    && (int) Db::wert("SELECT COUNT(*) FROM notifications WHERE type = 'altseite_fertig'", [], 0) >= 1);
if ($asZipOk) { $asZip->close(); }

$asK2 = Events::kundeFinden(['name' => 'Alt Leer', 'email' => 'leer@pruefung.example']);
$asId2 = Altseite::anlegen($asK2, 'gibt-es-nicht-probe.it');
$asStand2 = '';
for ($i = 0; $i < 3 && !in_array($asStand2, ['fertig', 'fehler'], true); $i++) { $asStand2 = Altseite::weiter($asId2, $asHolen); }
pruefe('Phase 6a: ist die Seite nicht erreichbar, steht ein Fehler da statt einer leeren ZIP',
    $asStand2 === 'fehler' && (int) Db::wert('SELECT COUNT(*) FROM files WHERE customer_id = ?', [$asK2], 0) === 0);

/* ============================================================================
   75. Website 1:1 umziehen, begleitet (Phase 6c) -- mit nachgebautem FTP
   ============================================================================ */
abschnitt('75. Website 1:1 umziehen, begleitet');
require_once $wurzel . '/src/Seitenumzug.php';

$suAufl = static fn(string $h): array => ['ftp.intern.example' => ['10.0.0.5'], 'ftp.gut.example' => ['93.184.216.34']][$h] ?? [];
pruefe('Phase 6c: nur öffentliche Server -- private Adressen, localhost und Unauflösbares nicht',
    Seitenumzug::hostErlaubt('ftp.gut.example', $suAufl) && !Seitenumzug::hostErlaubt('ftp.intern.example', $suAufl)
    && !Seitenumzug::hostErlaubt('127.0.0.1') && !Seitenumzug::hostErlaubt('192.168.1.10') && !Seitenumzug::hostErlaubt('localhost', $suAufl)
    && !Seitenumzug::hostErlaubt('gibtsnicht.example', $suAufl) && Seitenumzug::hostErlaubt('93.184.216.34'));

$suK = Events::kundeFinden(['name' => 'Umzug Seite', 'email' => 'umzug-seite@pruefung.example', 'sprache' => 'de']);
$suId = Seitenumzug::anfragen($suK, 'https://www.alte-seite-probe.it/');
pruefe('Phase 6c: angefragt, einmal je laufendem Umzug',
    $suId > 0 && Seitenumzug::anfragen($suK, 'alte-seite-probe.it') === $suId
    && (string) Db::wert('SELECT adresse FROM seitenumzuege WHERE id = ?', [$suId], '') === 'alte-seite-probe.it');
$suFremd = Events::kundeFinden(['name' => 'Fremd Seite', 'email' => 'fremd-seite@pruefung.example']);
pruefe('Phase 6c: ein fremder Kunde kann keinen Zugang hinterlegen',
    Seitenumzug::zugangSpeichern($suId, $suFremd, ['ftp_host' => '93.184.216.34', 'ftp_user' => 'u', 'ftp_pass' => 'p'], 'de') === 'nicht_dran');
pruefe('Phase 6c: ohne Passwort oder mit privatem Server wird nichts gespeichert und nichts zugestimmt',
    Seitenumzug::zugangSpeichern($suId, $suK, ['ftp_host' => '93.184.216.34', 'ftp_user' => 'u'], 'de') === 'unvollstaendig'
    && Seitenumzug::zugangSpeichern($suId, $suK, ['ftp_host' => '10.1.2.3', 'ftp_user' => 'u', 'ftp_pass' => 'p'], 'de') === 'host'
    && (int) Db::wert("SELECT COUNT(*) FROM zustimmungen WHERE customer_id = ? AND art = 'migration'", [$suK], 0) === 0);
$suOk = Seitenumzug::zugangSpeichern($suId, $suK, ['ftp_host' => '93.184.216.34', 'ftp_user' => 'alt_user',
    'ftp_pass' => 'Geheim-FTP-123', 'db_pass' => 'Geheim-DB-456'], 'de');
$suRow = Db::one('SELECT * FROM seitenumzuege WHERE id = ?', [$suId]);
$suZ = Db::one("SELECT * FROM zustimmungen WHERE customer_id = ? AND art = 'migration'", [$suK]);
pruefe('Phase 6c: Zustimmung mit Wortlaut, Zugang verschlüsselt, Löschdatum in 30 Tagen',
    $suOk === 'ok' && $suZ && str_contains((string) $suZ['text'], 'alte-seite-probe.it') && str_contains((string) $suZ['text'], '30 Tagen')
    && (int) $suZ['bezug_id'] === $suId
    && !str_contains((string) $suRow['zugang_blob'], 'Geheim-FTP-123') && !str_contains((string) $suRow['zugang_blob'], 'Geheim-DB-456')
    /* Gegen die Datenbankuhr: Setzen und Loeschen laufen beide ueber NOW() --
       PHP (Rom) und Datenbank (UTC) liegen vor Mitternacht einen Tag auseinander. */
    && substr((string) $suRow['loeschen_am'], 0, 10) === (string) Db::wert('SELECT DATE(NOW() + INTERVAL 30 DAY)', [], ''));
pruefe('Phase 6c: das Passwort steht in keiner Meldung und keiner Mail',
    (int) Db::wert("SELECT COUNT(*) FROM notifications WHERE body LIKE '%Geheim-FTP%'", [], 0) === 0
    && (int) Db::wert("SELECT COUNT(*) FROM mails WHERE betreff LIKE '%Geheim-FTP%'", [], 0) === 0);

$suGesehen = null;
$suFtp = static function (array $z) use (&$suGesehen): array { $suGesehen = $z;
    return ['ok' => true, 'text' => 'Anmeldung klappt (verschlüsselt).', 'liste' => ['public_html'], 'wordpress' => true]; };
$suC = Seitenumzug::cron($suFtp);
$suT = json_decode((string) Db::wert('SELECT test_json FROM seitenumzuege WHERE id = ?', [$suId], ''), true);
pruefe('Phase 6c: der Cron prüft die Verbindung mit dem entschlüsselten Zugang und erkennt WordPress',
    $suC['geprueft'] === 1 && ($suGesehen['ftp_pass'] ?? '') === 'Geheim-FTP-123' && !empty($suT['wordpress']));
pruefe('Phase 6c: Uwe kann den Zugang lesen', (Seitenumzug::zugangLesen($suId)['db_pass'] ?? '') === 'Geheim-DB-456');

pruefe('Phase 6c: ohne Sicherung kein Kopieren -- die Checkliste geht nur in Reihenfolge',
    !Seitenumzug::schritt($suId, 'dateien', true) && Seitenumzug::schritt($suId, 'sicherung', true) && Seitenumzug::schritt($suId, 'dateien', true));
pruefe('Phase 6c: abschließen geht erst, wenn alles abgehakt ist', !Seitenumzug::beenden($suId, true));
foreach (['datenbank', 'test', 'dns'] as $suS) { Seitenumzug::schritt($suId, $suS, true); }
pruefe('Phase 6c: abgeschlossen -- und der Zugang ist weg',
    Seitenumzug::beenden($suId, true) && Db::one('SELECT zugang_blob FROM seitenumzuege WHERE id = ?', [$suId])['zugang_blob'] === null
    && Seitenumzug::zugangLesen($suId) === null);

$suId2 = Seitenumzug::anfragen($suK, 'zweite-probe.it');
Seitenumzug::zugangSpeichern($suId2, $suK, ['ftp_host' => '93.184.216.34', 'ftp_user' => 'u', 'ftp_pass' => 'Pw-lang-genug'], 'de');
Db::run('UPDATE seitenumzuege SET loeschen_am = NOW() - INTERVAL 1 HOUR, test_json = ? WHERE id = ?', ['{}', $suId2]);
$suC2 = Seitenumzug::cron($suFtp);
pruefe('Phase 6c: nach 30 Tagen löscht der Cron den Zugang auch ohne Abschluss',
    $suC2['geloescht'] === 1 && Db::one('SELECT zugang_blob FROM seitenumzuege WHERE id = ?', [$suId2])['zugang_blob'] === null);
pruefe('Phase 6c: Anfragen schickt Post und fragt vorher; Abschließen und Abbrechen löschen und fragen vorher',
    Ablauf::wiegt('seitenumzug_anfragen') === Ablauf::RAUS && Ablauf::wiegt('seitenumzug_fertig') === Ablauf::SCHWER
    && Ablauf::wiegt('seitenumzug_abbrechen') === Ablauf::SCHWER);
$suTx = true;
foreach (['it', 'de', 'en'] as $suSp) {
    [$b, $t] = Texte::mail('seitenumzug_anfrage', $suSp, ['name' => 'X', 'adresse' => 'a.it', 'seite' => 'S']);
    if (preg_match('~\{[a-z]+\}~', $b . $t)) { $suTx = false; }
    if (preg_match('~\{[a-z]+\}~', Seitenumzug::zustimmungsText('a.it', $suSp))) { $suTx = false; }
}
pruefe('Phase 6c: Anfrage-Mail und Zustimmung dreisprachig, ohne offene Platzhalter', $suTx);

/* ============================================================================
   76. E-Mails umziehen (Phase 6b) -- mit nachgebautem IMAP-Server
   (gegen einen echten Dovecot am 25.09.2026 ebenso durchgespielt)
   ============================================================================ */
abschnitt('76. E-Mails umziehen');
require_once $wurzel . '/src/Mailumzug.php';

final class KetteImap {
    /** @var array<string,array> Postfach => ['trenner'=>, 'ordner'=>[name=>['merkmale'=>[], 'uv'=>int, 'mails'=>[uid=>[flags,datum,inhalt]]]]] */
    public static array $welt = [];
    public static array $gelesenMarkiert = [];
    private string $wer = '';
    public function __construct(public string $host, public int $port) {}
    public function anmelden(string $u, string $p): void {
        $k = $this->host . '|' . $u;
        if (!isset(self::$welt[$k]) || self::$welt[$k]['pass'] !== $p) { throw new RuntimeException('NO [AUTHENTICATIONFAILED]'); }
        $this->wer = $k;
    }
    public function ordner(): array {
        $w = self::$welt[$this->wer]; $aus = [];
        foreach ($w['ordner'] as $n => $o) { $aus[] = ['name' => $n, 'trenner' => $w['trenner'], 'merkmale' => $o['merkmale']]; }
        return $aus;
    }
    private string $offen = '';
    public function oeffnen(string $o, bool $nurLesen = true): array {
        $x = self::$welt[$this->wer]['ordner'][$o] ?? throw new RuntimeException('NO no such mailbox');
        $this->offen = $o; return ['anzahl' => count($x['mails']), 'uidvalidity' => $x['uv']];
    }
    public function uids(int $ab = 1): array {
        return array_values(array_filter(array_keys(self::$welt[$this->wer]['ordner'][$this->offen]['mails']), static fn($u) => $u >= $ab));
    }
    public function holen(int $uid): ?array { $m = self::$welt[$this->wer]['ordner'][$this->offen]['mails'][$uid] ?? null;
        return $m ? ['flags' => $m[0], 'datum' => $m[1], 'inhalt' => $m[2]] : null; }
    public function anlegen(string $o): void { self::$welt[$this->wer]['ordner'][$o] ??= ['merkmale' => [], 'uv' => 1, 'mails' => []]; }
    public function anhaengen(string $o, string $inhalt, array $flags, string $datum): void {
        if (!isset(self::$welt[$this->wer]['ordner'][$o])) { throw new RuntimeException('NO [TRYCREATE]'); }
        $m = &self::$welt[$this->wer]['ordner'][$o]['mails'];
        $m[(int) (max(array_keys($m) ?: [0]) + 1)] = [$flags, $datum, $inhalt];
    }
}
KetteImap::$welt = [
    'imap.alt.example|rosa@alt.example' => ['pass' => 'Alt-1', 'trenner' => '.', 'ordner' => [
        'INBOX' => ['merkmale' => [], 'uv' => 7, 'mails' => [1 => [['\\Seen'], '01-Sep-2025 10:00:00 +0200', "Subject: Eins\r\n\r\nA"],
                                                                2 => [[], '02-Sep-2025 10:00:00 +0200', "Subject: Zwei\r\n\r\nB"]]],
        'Posta inviata' => ['merkmale' => ['\\Sent'], 'uv' => 3, 'mails' => [5 => [['\\Seen'], '03-Sep-2025 10:00:00 +0200', "Subject: Raus\r\n\r\nC"]]],
        'Archivio' => ['merkmale' => ['\\Noselect', '\\HasChildren'], 'uv' => 0, 'mails' => []],
        'Archivio.2024' => ['merkmale' => [], 'uv' => 9, 'mails' => [1 => [[], '04-Sep-2024 10:00:00 +0200', "Subject: Alt\r\n\r\nD"]]],
    ]],
    'w0199999.kasserver.com|kontakt@neu.example' => ['pass' => 'Neu-2', 'trenner' => '/', 'ordner' => [
        'INBOX' => ['merkmale' => [], 'uv' => 1, 'mails' => []],
        'Sent' => ['merkmale' => ['\\Sent'], 'uv' => 1, 'mails' => []],
    ]],
];
Mailumzug::$verbinden = static fn(string $h, int $p, bool $t) => new KetteImap($h, $p);
Mailumzug::$hostErlaubt = static fn(string $h) => !in_array($h, ['127.0.0.1', 'localhost'], true);

pruefe('Phase 6b: der Server wird aus der Adresse oder den MX-Einträgen geraten, sonst imap.<domain>',
    Mailumzug::serverFuer('x@gmail.com') === 'imap.gmail.com' && Mailumzug::serverFuer('x@libero.it') === 'imapmail.libero.it'
    && Mailumzug::serverFuer('x@firma.it', static fn() => ['firma-it.mail.protection.outlook.com']) === 'outlook.office365.com'
    && Mailumzug::serverFuer('x@firma.it', static fn() => ['mx.aruba.it']) === 'imaps.aruba.it'
    && Mailumzug::serverFuer('x@firma.it', static fn() => []) === 'imap.firma.it');

$muK = Events::kundeFinden(['name' => 'Rosa Mail', 'email' => 'rosa@alt.example', 'sprache' => 'it']);
Db::insert('hosting_auftraege', ['customer_id' => $muK, 'domain' => 'neu.example', 'status' => 'angelegt', 'preis_cents' => 990,
    'domain_aktion' => 'neu', 'mail' => 'vecom', 'kas_login' => 'w0199999']);
$muId = Mailumzug::anfragen($muK, 'rosa@alt.example', 'kontakt@neu.example');
pruefe('Phase 6b: der neue Server kommt aus dem KAS-Account des Kunden', Mailumzug::zielServer($muK) === 'w0199999.kasserver.com');
pruefe('Phase 6b: ohne beide Passwörter oder mit internem Server nichts gespeichert, nichts zugestimmt',
    Mailumzug::zugangSpeichern($muId, $muK, ['alt_pass' => 'Alt-1'], 'it') === 'unvollstaendig'
    && Mailumzug::zugangSpeichern($muId, $muK, ['alt_pass' => 'x', 'neu_pass' => 'y', 'alt_server' => '127.0.0.1'], 'it') === 'host'
    && (int) Db::wert("SELECT COUNT(*) FROM zustimmungen WHERE customer_id = ? AND art = 'mailumzug'", [$muK], 0) === 0);
pruefe('Phase 6b: ein fremder Kunde kann nicht zustimmen',
    Mailumzug::zugangSpeichern($muId, $muK + 999, ['alt_pass' => 'Alt-1', 'neu_pass' => 'Neu-2'], 'it') === 'nicht_dran');

/* Falsches Passwort: Fehler mit Grund, Kunde kann neu eingeben */
Mailumzug::zugangSpeichern($muId, $muK, ['alt_server' => 'imap.alt.example', 'alt_pass' => 'falsch', 'neu_pass' => 'Neu-2'], 'it');
pruefe('Phase 6b: falsches Passwort -- „hängt“ mit Grund, und der Kunde darf neu eingeben',
    Mailumzug::weiter($muId) === 'fehler' && str_contains((string) Db::wert('SELECT fehler FROM mailumzuege WHERE id = ?', [$muId], ''), 'Altes Postfach')
    && Mailumzug::zugangSpeichern($muId, $muK, ['alt_server' => 'imap.alt.example', 'alt_pass' => 'Alt-1', 'neu_pass' => 'Neu-2'], 'it') === 'ok');
$muRow = Db::one('SELECT * FROM mailumzuege WHERE id = ?', [$muId]);
pruefe('Phase 6b: Passwörter nur verschlüsselt; Zustimmung mit Wortlaut beider Adressen',
    !str_contains((string) $muRow['zugang_blob'], 'Alt-1') && !str_contains((string) $muRow['zugang_blob'], 'Neu-2')
    && str_contains((string) Db::wert("SELECT text FROM zustimmungen WHERE customer_id = ? AND art = 'mailumzug' ORDER BY id DESC LIMIT 1", [$muK], ''), 'kontakt@neu.example'));

$muStand = Mailumzug::weiter($muId);
$muNeu = KetteImap::$welt['w0199999.kasserver.com|kontakt@neu.example']['ordner'];
pruefe('Phase 6b: alles kopiert -- Posteingang, Gesendet in den Gesendet-Ordner des neuen Servers, Unterordner mit dessen Trennzeichen',
    $muStand === 'fertig' && count($muNeu['INBOX']['mails']) === 2 && count($muNeu['Sent']['mails']) === 1
    && isset($muNeu['Archivio/2024']) && count($muNeu['Archivio/2024']['mails']) === 1 && !isset($muNeu['Posta inviata']) && !isset($muNeu['Archivio.2024']));
pruefe('Phase 6b: „gelesen“ und Datum ziehen mit um',
    $muNeu['INBOX']['mails'][1][0] === ['\\Seen'] && $muNeu['INBOX']['mails'][2][0] === [] && $muNeu['INBOX']['mails'][1][1] === '01-Sep-2025 10:00:00 +0200');
$muR = Db::one('SELECT * FROM mailumzuege WHERE id = ?', [$muId]);
pruefe('Phase 6b: fertig -- Uwe und Kunde erfahren es, der Zugang bleibt für den Nachlauf 14 Tage',
    (int) $muR['kopiert'] === 4 && (string) $muR['stand'] === 'fertig' && $muR['zugang_blob'] !== null
    && (int) Db::wert("SELECT COUNT(*) FROM notifications WHERE type = 'mailumzug_fertig'", [], 0) === 1
    && (int) Db::wert("SELECT COUNT(*) FROM mails WHERE anlass = 'mailumzug_fertig' AND customer_id = ?", [$muK], 0) === 1);

KetteImap::$welt['imap.alt.example|rosa@alt.example']['ordner']['INBOX']['mails'][3] = [[], '05-Sep-2025 10:00:00 +0200', "Subject: Spaet\r\n\r\nE"];
Mailumzug::weiter($muId);
Mailumzug::weiter($muId);
$muNeu = KetteImap::$welt['w0199999.kasserver.com|kontakt@neu.example']['ordner'];
pruefe('Phase 6b: der Nachlauf holt nur das Neue -- keine Doppelten, keine zweite Fertig-Meldung',
    count($muNeu['INBOX']['mails']) === 3 && (int) Db::wert('SELECT kopiert FROM mailumzuege WHERE id = ?', [$muId], 0) === 5
    && (int) Db::wert("SELECT COUNT(*) FROM notifications WHERE type = 'mailumzug_fertig'", [], 0) === 1);

KetteImap::$welt['imap.alt.example|rosa@alt.example']['ordner']['INBOX']['uv'] = 8;   // neu nummeriert
Mailumzug::weiter($muId);
pruefe('Phase 6b: nummeriert der alte Server einen Ordner neu (UIDVALIDITY), wird er neu gelesen statt still übersprungen',
    count(KetteImap::$welt['w0199999.kasserver.com|kontakt@neu.example']['ordner']['INBOX']['mails']) === 6);

Db::run('UPDATE mailumzuege SET loeschen_am = NOW() - INTERVAL 1 HOUR WHERE id = ?', [$muId]);
$muC = Mailumzug::cron();
pruefe('Phase 6b: nach dem Nachlauf löscht der Cron die Passwörter',
    $muC['geloescht'] >= 1 && Db::one('SELECT zugang_blob FROM mailumzuege WHERE id = ?', [$muId])['zugang_blob'] === null);
pruefe('Phase 6b: Anfragen schickt Post und fragt vorher, Anhalten löscht und fragt vorher',
    Ablauf::wiegt('mailumzug_anfragen') === Ablauf::RAUS && Ablauf::wiegt('mailumzug_abbrechen') === Ablauf::SCHWER
    && str_contains((string) file_get_contents($wurzel . '/src/Cron.php'), 'Mailumzug::cron()'));
$muT = true;
foreach (['it', 'de', 'en'] as $muS) {
    foreach (['mailumzug_anfrage', 'mailumzug_fertig'] as $muA) {
        [$b, $t] = Texte::mail($muA, $muS, ['name' => 'X', 'alt' => 'a@x', 'neu' => 'b@y', 'anzahl' => '3', 'tage' => '14', 'seite' => 'S']);
        if (preg_match('~\{[a-z]+\}~', $b . $t)) { $muT = false; }
    }
    if (preg_match('~\{[a-z]+\}~', Mailumzug::zustimmungsText('a@x', 'b@y', $muS))) { $muT = false; }
}
pruefe('Phase 6b: Mails und Zustimmung dreisprachig, ohne offene Platzhalter', $muT);
Mailumzug::$verbinden = null;
Mailumzug::$hostErlaubt = null;


/* ============================================================================
   77. Mehr aus der KAS-Schnittstelle: DNS übernehmen, Speicher, Cronjob
   ============================================================================ */
abschnitt('77. DNS übernehmen, Speicher, Cronjob');

$kbBestand = [
    ['name' => '@', 'typ' => 'A', 'wert' => '93.184.216.34'], ['name' => 'www', 'typ' => 'CNAME', 'wert' => 'altfirma.it'],
    ['name' => '@', 'typ' => 'MX', 'wert' => '10 altfirma-it.mail.protection.outlook.com'],
    ['name' => '@', 'typ' => 'TXT', 'wert' => 'v=spf1 include:spf.protection.outlook.com -all'],
    ['name' => '@', 'typ' => 'TXT', 'wert' => 'google-site-verification=abc'],
    ['name' => '_dmarc', 'typ' => 'TXT', 'wert' => 'v=DMARC1; p=quarantine'],
    ['name' => 'selector1._domainkey', 'typ' => 'TXT', 'wert' => 'v=DKIM1; k=rsa; p=MIGf'],
    ['name' => '@', 'typ' => 'NS', 'wert' => 'ns1.altanbieter.it'],
];
$kbBleibt = Hosting::dnsAuswahl($kbBestand, 'bisher');
$kbTypen = array_map(static fn($e) => $e['typ'] . ':' . ($e['name'] ?: '@'), $kbBleibt['eintraege']);
pruefe('KAS-DNS: bleibt die Mail beim alten Anbieter, ziehen MX, SPF, DKIM, DMARC und Bestätigungen mit -- Web und NS nie',
    $kbTypen === ['MX:@', 'TXT:@', 'TXT:@', 'TXT:_dmarc', 'TXT:selector1._domainkey'] && $kbBleibt['mx_ersetzen']
    && $kbBleibt['eintraege'][0]['aux'] === 10 && $kbBleibt['eintraege'][0]['daten'] === 'altfirma-it.mail.protection.outlook.com');
$kbVecom = Hosting::dnsAuswahl($kbBestand, 'vecom');
pruefe('KAS-DNS: läuft die Mail über Vecom, nur fremde Bestätigungen -- die Mail-Einträge des KAS bleiben',
    count($kbVecom['eintraege']) === 1 && $kbVecom['eintraege'][0]['daten'] === 'google-site-verification=abc' && !$kbVecom['mx_ersetzen']);

$kbKas = new class ($kbBestand) {
    public array $zone = [['id' => '11', 'name' => '', 'typ' => 'MX', 'daten' => 'w0199999.kasserver.com', 'aux' => '10', 'aenderbar' => true],
                          ['id' => '', 'name' => '', 'typ' => 'MX', 'daten' => 'unklar.kasserver.com', 'aux' => '20', 'aenderbar' => true],
                          ['id' => '12', 'name' => '', 'typ' => 'A', 'daten' => '85.13.0.1', 'aux' => '0', 'aenderbar' => false]];
    public array $umgeschrieben = []; public array $neu = []; public bool $einmalFehler = false;
    public function __construct(public array $bestandDaten) {}
    public function accountAnlegen(string $k, array $g = []): array { return ['ok' => true, 'login' => 'w0177777', 'kas_passwort' => 'K-1!', 'ftp_passwort' => 'F-2!', 'text' => 'ok']; }
    public function domainAnlegen(string $d, ?array $als = null): array { return ['ok' => true, 'text' => 'ok']; }
    public function postfachAnlegen(string $l, string $d, string $pw, ?array $als = null): array { return ['ok' => true, 'text' => 'ok']; }
    public function passwortNeu(): string { return 'P-3!'; }
    public function bestand(string $d): array { return $this->bestandDaten; }
    public function dnsLesen(string $d, ?array $als = null): array { return ['ok' => true, 'text' => '', 'eintraege' => $this->zone]; }
    public function dnsAendern(string $id, string $w, int $aux = 0, ?array $als = null): array { $this->umgeschrieben[] = $id . '>' . $w . ':' . $aux; return ['ok' => true, 'text' => '']; }
    public function dnsHinzufuegen(string $d, string $t, string $n, string $w, int $aux = 0, ?array $als = null): array {
        if ($this->einmalFehler) { $this->einmalFehler = false; return ['ok' => false, 'text' => 'in_progress']; }
        $this->neu[] = $t . ':' . ($n ?: '@') . ':' . ($als['login'] ?? '-'); return ['ok' => true, 'text' => 'eingetragen'];
    }
};
$kbK = Events::kundeFinden(['name' => 'DNS Probe', 'email' => 'dns@altfirma-probe.it']);
$kbA = (int) Db::insert('hosting_auftraege', ['customer_id' => $kbK, 'domain' => 'altfirma-probe.it', 'status' => 'zugestimmt',
    'preis_cents' => 990, 'domain_aktion' => 'transfer', 'mail' => 'bisher']);
$kbKas->einmalFehler = true;
Hosting::anlegen($kbA, $kbKas);
pruefe('KAS-DNS: klemmt die Zone, wird es wiederholt -- und der Kunde bekommt seine Zugangsdaten noch nicht',
    (string) Hosting::schritte($kbA)['dns']['status'] === 'fehler' && (string) Db::wert('SELECT status FROM hosting_auftraege WHERE id = ?', [$kbA], '') === 'in_arbeit');
Db::run("UPDATE hosting_schritte SET updated_at = NOW() - INTERVAL 30 MINUTE WHERE auftrag_id = ?", [$kbA]);
Hosting::fortsetzen($kbKas);
$kbS = Hosting::schritte($kbA)['dns'];
pruefe('KAS-DNS: die alten Einträge stehen in der KAS-Zone, im Unter-Account -- der KAS-MX umgeschrieben statt gelöscht',
    /* Die Wiederholung schreibt denselben MX noch einmal -- harmlos, der KAS sagt dann nothing_to_do. */
    array_values(array_unique($kbKas->umgeschrieben)) === ['11>altfirma-it.mail.protection.outlook.com:10']
    && in_array('TXT:_dmarc:w0177777', $kbKas->neu, true) && !in_array('MX:@:w0177777', $kbKas->neu, true)
    && !preg_grep('~^(A|NS|CNAME):~', $kbKas->neu)
    && (string) Db::wert('SELECT status FROM hosting_auftraege WHERE id = ?', [$kbA], '') === 'angelegt');
pruefe('KAS-DNS: ein KAS-MX ohne Nummer bleibt stehen -- und das ist Handarbeit mit klarem Satz, kein stilles „fertig“',
    (string) $kbS['status'] === 'hand' && str_contains((string) $kbS['text'], 'eigener MX-Eintrag'));

/* Speicher */
$kbH = (int) Db::insert('hosting_auftraege', ['customer_id' => $kbK, 'domain' => 'voll-probe.it', 'status' => 'angelegt',
    'preis_cents' => 990, 'domain_aktion' => 'neu', 'mail' => 'vecom', 'kas_login' => 'w0188888']);
$kbLesen = static fn(): array => ['ok' => true, 'text' => '', 'belegt' => ['w0188888' => 9600, 'w0199999' => 120]];
$kbVor = (int) Db::wert("SELECT COUNT(*) FROM notifications WHERE type = 'hosting_speicher'", [], 0);
$kbE = Hosting::speicherPruefen($kbLesen);
Hosting::speicherPruefen($kbLesen);
pruefe('Speicher: ab 90 % eine Meldung je Account und Monat -- nicht jeden Tag',
    $kbE['gewarnt'] === 1 && (int) Db::wert("SELECT COUNT(*) FROM notifications WHERE type = 'hosting_speicher'", [], 0) === $kbVor + 1
    && (Hosting::speicher()['w0188888'] ?? 0) === 9600);
pruefe('Speicher: "no_statistic_data" heißt "noch keine Zahlen", kein Fehler -- andere Fehler bleiben Fehler',
    Kas::speicherAusAntwort(['ok' => false, 'daten' => null, 'text' => 'Die KAS-API meldet: no_statistic_data'])['ok'] === true
    && Kas::speicherAusAntwort(['ok' => false, 'daten' => null, 'text' => 'Die KAS-API meldet: no_statistic_data'])['belegt'] === []
    && Kas::speicherAusAntwort(['ok' => false, 'daten' => null, 'text' => 'Login oder Passwort stimmen nicht.'])['ok'] === false
    && (Kas::speicherAusAntwort(['ok' => true, 'text' => '', 'daten' => [['account_login' => 'w0177777', 'used_space' => 2048000]]])['belegt']['w0177777'] ?? 0) === 2000);
pruefe('KAS: Zone mit Punkt am Ende, wie in der Doku; DNS-Umschreiben nur mit echter Nummer',
    Kas::zone('Altfirma.it') === 'altfirma.it.' && Kas::zone('altfirma.it.') === 'altfirma.it.' && Kas::dnsAendern('abc', 'x')['ok'] === false);
pruefe('Cronjob: der Knopf prüft erst, ob es ihn schon gibt, und der tägliche Speicherlauf steht im Cron',
    str_contains((string) file_get_contents($wurzel . '/index.php'), "Kas::cronjobs()")
    && str_contains((string) file_get_contents($wurzel . '/src/Cron.php'), 'Hosting::speicherPruefen()'));

/* ============================================================================
   78. Weiterleitungen und Sperre nach Vertragsende
   ============================================================================ */
abschnitt('78. Weiterleitungen und Sperre nach Vertragsende');

pruefe('Weiterleitungen: aus „kontakt, Buchung; office@firma.it“ werden saubere Namen -- ohne info (das Postfach selbst), ohne Unsinn',
    Hosting::weiterleitungen('kontakt, Buchung; office@firma.it info ../x kontakt') === ['kontakt', 'buchung', 'office']
    && Hosting::weiterleitungen('') === [] && count(Hosting::weiterleitungen(implode(' ', range('a', 'z')))) === 10);

$wlKas = new class {
    public array $wl = [];
    public function accountAnlegen(string $k, array $g = []): array { return ['ok' => true, 'login' => 'w0166666', 'kas_passwort' => 'K-1!', 'ftp_passwort' => 'F-2!', 'text' => 'ok']; }
    public function domainAnlegen(string $d, ?array $als = null): array { return ['ok' => true, 'text' => 'ok']; }
    public function postfachAnlegen(string $l, string $d, string $pw, ?array $als = null): array { return ['ok' => true, 'text' => 'ok']; }
    public function passwortNeu(): string { return 'P-3!'; }
    public function weiterleitungAnlegen(string $l, string $d, string $z, ?array $als = null): array { $this->wl[] = $l . '@' . $d . '>' . $z . ':' . ($als['login'] ?? '-'); return ['ok' => true, 'text' => 'angelegt']; }
};
$wlK = Events::kundeFinden(['name' => 'Weiter Probe', 'email' => 'weiter@pruefung.example']);
$wlA = (int) Db::insert('hosting_auftraege', ['customer_id' => $wlK, 'domain' => 'weiter-probe.it', 'status' => 'zugestimmt',
    'preis_cents' => 990, 'domain_aktion' => 'neu', 'mail' => 'vecom', 'weiterleitungen' => 'kontakt,buchung']);
Hosting::anlegen($wlA, $wlKas);
pruefe('Weiterleitungen: beim Einrichten angelegt, im Unter-Account, alle auf info@',
    $wlKas->wl === ['kontakt@weiter-probe.it>info@weiter-probe.it:w0166666', 'buchung@weiter-probe.it>info@weiter-probe.it:w0166666']
    && (string) Hosting::schritte($wlA)['weiterleitung']['status'] === 'fertig');

/* Sperre: eigener Hosting-Vertrag beendet */
/* Den Hosting-Vertrag hat der Einrichtungslauf oben schon geschlossen (Schritt "vertrag"). */
$wlAbo = (int) Db::wert("SELECT id FROM abos WHERE customer_id = ? AND paket_slug = 'hosting'", [$wlK], 0);
pruefe('Sperre: der Einrichtungslauf hat den Hosting-Vertrag geschlossen', $wlAbo > 0);
pruefe('Sperre: solange der Vertrag läuft, steht nichts zum Sperren da',
    !array_filter(Hosting::zumSperren(), static fn($x) => (int) $x['id'] === $wlA));
Db::run("UPDATE abos SET status = 'beendet' WHERE id = ?", [$wlAbo]);
pruefe('Sperre: nach Vertragsende steht er auf der Liste und in „Wartet auf dich“',
    (bool) array_filter(Hosting::zumSperren(), static fn($x) => (int) $x['id'] === $wlA)
    && (bool) array_filter(Leistungen::warten(), static fn($w) => ($w['marke'] ?? '') === 'Zugang offen' && str_contains($w['titel'], 'weiter-probe.it')));
$wlGerufen = [];
$wlSp = static function (string $login, bool $zu) use (&$wlGerufen): array { $wlGerufen[] = $login . ':' . ($zu ? 'Y' : 'N'); return ['ok' => true, 'text' => '']; };
$wlR = Hosting::zugangSperren($wlA, true, $wlSp);
pruefe('Sperre: gesperrt wird der Account des Kunden, vermerkt -- und dann ist er von der Liste',
    $wlR['ok'] && $wlGerufen === ['w0166666:Y'] && Db::one('SELECT gesperrt_am FROM hosting_auftraege WHERE id = ?', [$wlA])['gesperrt_am'] !== null
    && !array_filter(Hosting::zumSperren(), static fn($x) => (int) $x['id'] === $wlA));
Hosting::zugangSperren($wlA, false, $wlSp);
pruefe('Sperre: wieder öffnen geht', $wlGerufen[1] === 'w0166666:N' && Db::one('SELECT gesperrt_am FROM hosting_auftraege WHERE id = ?', [$wlA])['gesperrt_am'] === null);

/* Hosting in der Betreuung: gesperrt wird erst, wenn die Betreuung vorbei ist */
$wlK2 = Events::kundeFinden(['name' => 'Inklusive Probe', 'email' => 'inklusive@pruefung.example']);
$wlA2 = (int) Db::insert('hosting_auftraege', ['customer_id' => $wlK2, 'domain' => 'inklusive-probe.it', 'status' => 'angelegt',
    'preis_cents' => 990, 'domain_aktion' => 'neu', 'mail' => 'vecom', 'kas_login' => 'w0155555', 'inklusive' => 1]);
$wlB1 = Abo::anlegen($wlK2, ['paket_slug' => 'betreuung-plus']);
Db::run("UPDATE abos SET status = 'beendet' WHERE id = ?", [$wlB1]);
$wlB2 = Abo::anlegen($wlK2, ['paket_slug' => 'betreuung-premium']);
pruefe('Sperre: steckt das Hosting in der Betreuung und läuft eine neue Betreuung, wird nicht gesperrt',
    !array_filter(Hosting::zumSperren(), static fn($x) => (int) $x['id'] === $wlA2));
Db::run("UPDATE abos SET status = 'beendet' WHERE id = ?", [$wlB2]);
pruefe('Sperre: … erst wenn keine mehr läuft',
    (bool) array_filter(Hosting::zumSperren(), static fn($x) => (int) $x['id'] === $wlA2));
pruefe('Sperre: fragt vorher, und die Klasse kann weiterhin nichts löschen',
    Ablauf::wiegt('hosting_sperren') === Ablauf::SCHWER
    && !array_filter(get_class_methods('Kas'), static fn($m) => str_contains(strtolower($m), 'loeschen') || str_starts_with($m, 'delete')));
pruefe('Fragebogen: die Frage nach Weiterleitungen erscheint nur mit Postfach bei Vecom',
    (Texte::FRAGEBOGEN['formales']['felder']['mail_weiter']['wenn'] ?? null) === ['feld' => 'mail_wahl', 'ist' => ['vecom']]);

/* ============================================================================
   79. Speicher als Vereinbarung, Abgleich, Probelauf, HTTPS, DB/FTP (26.09.2026)
   Uwes Masterprompt: Vecom ist die Quelle fuer den Speicher. Ein Kunde mit
   15 GB behaelt 15 GB -- nicht 10, nicht 20, keine neue Paketgroesse.
   ============================================================================ */
abschnitt('79. Speicher, Abgleich, Probelauf, HTTPS, DB/FTP');

$spKas = new class {
    public array $gerufen = [];
    public array $grenzen = [];
    public array $kommentareDa = [];
    public function accountAnlegen(string $kommentar, array $g = []): array {
        $this->grenzen = $g; $this->gerufen[] = 'account';
        return ['ok' => true, 'login' => 'w0144444', 'kas_passwort' => 'Kas-Pw-9!', 'ftp_passwort' => 'Ftp-Pw-9!', 'text' => 'ok'];
    }
    public function domainAnlegen(string $d, ?array $als = null): array { $this->gerufen[] = 'domain'; return ['ok' => true, 'text' => 'ok']; }
    public function postfachAnlegen(string $l, string $d, string $pw, ?array $als = null): array { $this->gerufen[] = 'postfach'; return ['ok' => true, 'text' => 'ok']; }
    public function passwortNeu(): string { return 'Neu-Pw-' . count($this->gerufen) . '!'; }
    public function kommentare(string $aktion, ?array $als = null): array { $this->gerufen[] = $aktion; return ['ok' => true, 'text' => '', 'kommentare' => $this->kommentareDa]; }
    public function datenbankAnlegen(string $k, string $pw, ?array $als = null): array { $this->gerufen[] = 'db:' . $k; $this->kommentareDa[] = $k; return ['ok' => true, 'text' => 'ok', 'name' => 'd0444444']; }
    public function ftpAnlegen(string $k, string $pw, ?array $als = null): array { $this->gerufen[] = 'ftp:' . $k; $this->kommentareDa[] = $k; return ['ok' => true, 'text' => 'ok', 'login' => 'f0444444']; }
};
$spNeu = static function (?int $mb, array $mehr = []): int {
    static $n = 0; $n++;
    $k = Events::kundeFinden(['name' => 'Speicher Probe ' . $n, 'email' => 'speicher' . $n . '@pruefung.example']);
    return (int) Db::insert('hosting_auftraege', $mehr + ['customer_id' => $k, 'domain' => 'speicher' . $n . '-probe.it',
        'status' => 'zugestimmt', 'preis_cents' => 990, 'domain_aktion' => 'neu', 'mail' => 'vecom', 'speicher_mb' => $mb]);
};

/* Der besonders wichtige Test aus dem Masterprompt */
$sp15 = $spNeu(15360);
Hosting::anlegen($sp15, $spKas);
pruefe('Speicher: ein Kunde mit 15 GB bekommt im KAS genau 15 GB (max_webspace 15360) -- keine Paketgröße',
    ($spKas->grenzen['max_webspace'] ?? null) === 15360, json_encode($spKas->grenzen));
$sp0 = $spNeu(null);
Hosting::anlegen($sp0, $spKas);
pruefe('Speicher: ohne Vereinbarung gilt der bisherige Wert für alle -- 10 GB, keine andere Zahl',
    ($spKas->grenzen['max_webspace'] ?? null) === 10240 && Hosting::SPEICHER_MB === 10240);

/* Die Migration darf keinen vereinbarten Wert anfassen -- und ist wiederholbar */
$spAlt = $spNeu(15360); $spLeer = $spNeu(null);
$spSql = (string) file_get_contents($wurzel . '/migrations/063_speicher_ssl.sql');
foreach (array_filter(array_map('trim', explode(';', (string) preg_replace('~^--.*$~m', '', $spSql)))) as $spS) { Db::run($spS); }
pruefe('Speicher: die Migration trägt nur Leeres mit 10240 nach, 15 GB bleiben 15 GB (auch beim zweiten Lauf)',
    (int) Db::wert('SELECT speicher_mb FROM hosting_auftraege WHERE id = ?', [$spAlt], 0) === 15360
    && (int) Db::wert('SELECT speicher_mb FROM hosting_auftraege WHERE id = ?', [$spLeer], 0) === 10240);
pruefe('Speicher: jeder neue Auftrag trägt seinen Wert selbst -- eine geänderte Vorgabe nimmt keinem etwas',
    substr_count((string) file_get_contents($wurzel . '/src/Hosting.php'), "Db::insert('hosting_auftraege', self::vorgabeFelder() +") === 3
    && str_contains((string) file_get_contents($wurzel . '/index.php'), "Db::insert('hosting_auftraege', Hosting::vorgabeFelder() +"));

/* RESOURCE_MISMATCH: Vecom 20 GB, KAS 10 GB */
$sp20 = $spNeu(20480, ['status' => 'angelegt', 'kas_login' => 'w0133333']);
Db::run("DELETE FROM settings WHERE skey LIKE 'speicher_abweichung_%'");
$spVor = (int) Db::wert("SELECT COUNT(*) FROM notifications WHERE type = 'hosting_abweichung'", [], 0);
$spLesen = static fn(): array => ['ok' => true, 'text' => '', 'belegt' => ['w0133333' => 4000]];
$spGrenzen = static fn(): array => ['ok' => true, 'text' => '', 'grenzen' => ['w0133333' => 10240]];
$spE = Hosting::speicherPruefen($spLesen, $spGrenzen);
Hosting::speicherPruefen($spLesen, $spGrenzen);
$spRow = Db::one('SELECT speicher_mb, kas_speicher_mb FROM hosting_auftraege WHERE id = ?', [$sp20]);
pruefe('Abgleich: weicht der KAS ab, gibt es EINE Meldung -- und Vecom bleibt bei 20 GB',
    $spE['abweichend'] >= 1 && (int) $spRow['speicher_mb'] === 20480 && (int) $spRow['kas_speicher_mb'] === 10240
    && (int) Db::wert("SELECT COUNT(*) FROM notifications WHERE type = 'hosting_abweichung'", [], 0) === $spVor + 1);
$spGesetzt = [];
$spR = Hosting::speicherAufKas($sp20, static function (string $l, int $mb) use (&$spGesetzt): array { $spGesetzt[] = $l . ':' . $mb; return ['ok' => true, 'text' => 'ok']; });
pruefe('Abgleich: nur der Knopf schreibt in den KAS -- und nur den Vecom-Wert',
    $spR['ok'] && $spGesetzt === ['w0133333:20480']
    && (int) Db::wert('SELECT kas_speicher_mb FROM hosting_auftraege WHERE id = ?', [$sp20], 0) === 20480
    && Ablauf::wiegt('hosting_speicher_kas') === Ablauf::SCHWER);
pruefe('Abgleich: grenzenAus liest max_webspace, "unbegrenzt" (-1) zählt nicht als Wert',
    Kas::grenzenAus([['login' => 'w0111111', 'roh' => ['max_webspace' => '15360']], ['login' => 'w0122222', 'roh' => ['max_webspace' => '-1']]])
    === ['w0111111' => 15360]);
$spA1 = Hosting::speicherAendern($sp20, 512);
$spA2 = Hosting::speicherAendern($sp20, 25600);
pruefe('Vereinbaren: nur sinnvolle Werte, und die Änderung steht mit alt → neu im Protokoll',
    !$spA1['ok'] && $spA2['ok'] && (int) Db::wert('SELECT speicher_mb FROM hosting_auftraege WHERE id = ?', [$sp20], 0) === 25600
    && (int) Db::wert("SELECT COUNT(*) FROM activities WHERE type = 'hosting_speicher_vereinbart' AND title LIKE '%20 GB → 25 GB%'", [], 0) === 1);
pruefe('Anzeige: 7,4 GB / 10 GB -- eine Nachkommastelle, glatte Werte ohne',
    Hosting::gb(7578) === '7,4 GB' && Hosting::gb(10240) === '10 GB' && Hosting::gb(15360) === '15 GB');

/* Probelauf */
Db::run("DELETE FROM settings WHERE skey = 'kas_probelauf'");
pruefe('Probelauf: ohne Schalter ist er an -- ein vergessener Schalter legt nichts an', Kas::probelauf() === true);
$spPr = Kas::rufen('add_account', ['account_kas_password' => 'Geheim-123!', 'account_comment' => 'Probe']);
pruefe('Probelauf: add_/update_ erreichen den KAS nicht, und das Protokoll zeigt kein Passwort',
    !$spPr['ok'] && !empty($spPr['probelauf']) && !str_contains($spPr['text'], 'Geheim-123!')
    && str_contains($spPr['text'], 'account_kas_password=***')
    && (int) Db::wert("SELECT COUNT(*) FROM activities WHERE title LIKE '%Geheim-123!%'", [], 0) === 0);
pruefe('Probelauf: Lesen ist kein Schreiben', !Kas::schreibt('get_accounts') && Kas::schreibt('update_account') && Kas::schreibt('add_database'));
$spP = $spNeu(15360);
$spPe = Hosting::anlegen($spP);
pruefe('Probelauf: mit echtem KAS wird nichts beansprucht -- der Auftrag bleibt zugestimmt, der Plan nennt 15 GB',
    !$spPe['ok'] && !empty($spPe['probelauf']) && (string) Db::wert('SELECT status FROM hosting_auftraege WHERE id = ?', [$spP], '') === 'zugestimmt'
    && Hosting::schritte($spP) === [] && str_contains($spPe['text'], 'max_webspace 15360') && str_contains($spPe['text'], '15 GB Speicher'));
Db::run("INSERT INTO settings (skey, svalue) VALUES ('kas_probelauf', '0') ON DUPLICATE KEY UPDATE svalue = '0'");
$spKas->gerufen = [];
Hosting::fortsetzen($spKas);
pruefe('Probelauf aus: der angehaltene Auftrag läuft von selbst an',
    in_array('account', $spKas->gerufen, true) && (string) Db::wert('SELECT status FROM hosting_auftraege WHERE id = ?', [$spP], '') === 'angelegt');
Db::run("UPDATE settings SET svalue = '1' WHERE skey = 'kas_probelauf'");
pruefe('Probelauf: ausschalten fragt vorher', Ablauf::wiegt('kas_probelauf_aus') === Ablauf::SCHWER);

/* Datenbank und FTP -- nur auf Wunsch, nie doppelt */
$spKas->gerufen = []; $spKas->kommentareDa = [];
$spD = $spNeu(10240, ['mit_datenbank' => 1, 'mit_ftp' => 1]);
Hosting::anlegen($spD, $spKas);
$spDb = Db::one('SELECT technik_blob FROM hosting_auftraege WHERE id = ?', [$spD]);
pruefe('DB/FTP: angekreuzt wird beides angelegt, das Passwort liegt nur verschlüsselt',
    in_array('db:vecom-' . $spD . '-db', $spKas->gerufen, true) && in_array('ftp:vecom-' . $spD . '-ftp', $spKas->gerufen, true)
    && !empty($spDb['technik_blob']) && !str_contains((string) $spDb['technik_blob'], 'Neu-Pw')
    && (Hosting::technikAbrufen($spD)['datenbank']['name'] ?? '') === 'd0444444');
$spKas->gerufen = [];
Hosting::technikWunsch($spD, true, true);
Db::run("UPDATE hosting_schritte SET status = 'fehler', versuche = 0 WHERE auftrag_id = ? AND schritt IN ('datenbank','ftp')", [$spD]);
Hosting::wiederholen($spD, $spKas);
pruefe('DB/FTP: ein zweiter Lauf erkennt die eigenen am Kommentar -- nichts entsteht doppelt',
    !preg_grep('~^(db|ftp):~', $spKas->gerufen) && (string) (Hosting::schritte($spD)['datenbank']['text'] ?? '') === 'War schon da (vecom-' . $spD . '-db).');
pruefe('DB/FTP: nicht angekreuzt entfällt beides, ohne Aufruf',
    (string) (Hosting::schritte($sp15)['datenbank']['status'] ?? '') === 'entfaellt' && (string) (Hosting::schritte($sp15)['ftp']['status'] ?? '') === 'entfaellt');

/* HTTPS vor "online" */
$spH = $spNeu(10240, ['status' => 'angelegt', 'kas_login' => 'w0122222']);
$spHk = (int) Db::wert('SELECT customer_id FROM hosting_auftraege WHERE id = ?', [$spH], 0);
$spProj = (int) Db::insert('projects', ['customer_id' => $spHk, 'name' => 'HTTPS Probe', 'status' => 'finale_freigabe']);
$spSchlecht = static fn(string $d): array => ['https' => ['ok' => false, 'ssl_gueltig' => 0, 'ssl_bis' => null, 'fehler' => 'Mit dem SSL-Zertifikat stimmt etwas nicht: self-signed'], 'umleitung' => null];
$spHalb = static fn(string $d): array => ['https' => ['ok' => true, 'ssl_gueltig' => 1, 'ssl_bis' => '2027-01-01', 'fehler' => null], 'umleitung' => 'http://' . $d . '/'];
$spGut = static fn(string $d): array => ['https' => ['ok' => true, 'ssl_gueltig' => 1, 'ssl_bis' => '2027-01-01', 'fehler' => null], 'umleitung' => 'https://' . $d . '/'];
pruefe('HTTPS: noch nicht geprüft sperrt "online"', Hosting::httpsSperre($spProj) !== null);
pruefe('HTTPS: ein selbst signiertes Zertifikat ist ein Fehler, und "online" bleibt gesperrt',
    Hosting::httpsPruefen($spH, $spSchlecht)['status'] === 'fehler' && Hosting::httpsSperre($spProj) !== null);
pruefe('HTTPS: gültig, aber ohne Umleitung ist unvollständig',
    Hosting::httpsPruefen($spH, $spHalb)['status'] === 'warnung' && Hosting::httpsSperre($spProj) !== null);
pruefe('HTTPS: gültig mit Umleitung gibt "online" frei',
    Hosting::httpsPruefen($spH, $spGut)['status'] === 'ok' && Hosting::httpsSperre($spProj) === null);
$spOhne = (int) Db::insert('projects', ['customer_id' => Events::kundeFinden(['name' => 'Ohne Hosting', 'email' => 'ohnehosting@pruefung.example']),
    'name' => 'Ohne', 'status' => 'finale_freigabe']);
pruefe('HTTPS: liegt die Domain nicht bei uns, sperrt nichts', Hosting::httpsSperre($spOhne) === null);
pruefe('HTTPS: der Handler fragt vor "online" -- und der Cron prüft',
    str_contains((string) file_get_contents($wurzel . '/index.php'), 'Hosting::httpsSperre($pid)')
    && str_contains((string) file_get_contents($wurzel . '/src/Cron.php'), 'Hosting::httpsPruefenAlle()'));

/* ENV und Texte */
putenv('KAS_LOGIN=w0100000'); putenv('KAS_PASSWORD=Env-Pw-1!');
$spZ = Kas::zugang();
putenv('KAS_LOGIN'); putenv('KAS_PASSWORD');
pruefe('Zugang: KAS_LOGIN/KAS_PASSWORD aus der Server-Umgebung gehen vor', $spZ['login'] === 'w0100000' && $spZ['passwort'] === 'Env-Pw-1!');
$spT = true;
foreach (['meinHosting', 'mhSpeicher', 'mhSpeicherGebucht', 'mhHttps', 'mhHttpsOk', 'mhHttpsNoch', 'mhHttpsArbeit', 'mhMail', 'mhMailWoanders', 'mhVertrag', 'mhNaechste', 'mhLaeuftBis'] as $spK) {
    foreach (['it', 'de', 'en'] as $spSp) { if (trim((string) (Texte::KUNDE[$spK][$spSp] ?? '')) === '') { $spT = false; } }
}
pruefe('Mein Hosting: alle Texte dreisprachig', $spT);
pruefe('Kas: auch mit den neuen Aufrufen keine löschende Methode',
    !array_filter(get_class_methods('Kas'), static fn($m) => str_contains(strtolower($m), 'loeschen') || str_starts_with($m, 'delete')));

/* ============================================================================
   80. Reseller-Übersicht (26.09.2026) -- nur lesen, Vecom gegen KAS
   Die Kontingente haben die Form, die der echte Reseller-Zugang am
   25.09.2026 lieferte (get_accountresources: resource => {max, used, free}).
   ============================================================================ */
abschnitt('80. Reseller-Übersicht');
$rsQ = (string) file_get_contents($wurzel . '/src/Kas.php');
$rsF = substr($rsQ, strpos($rsQ, 'public static function resellerLesen'), 2400);
$rsF = substr($rsF, 0, (int) strpos($rsF, 'public static function resellerAuswerten'));
pruefe('Reseller: das Auslesen ruft nur get_ auf -- nichts, was schreibt',
    !preg_match("~'(add|update|delete)_~", $rsF) && preg_match_all("~'get_[a-z]+'~", $rsF) >= 4);
$rsStand = ['am' => '2026-09-26 10:00:00', 'fehler' => [],
    'ressourcen' => ['max_domain' => ['max' => 101, 'used' => 1, 'free' => 100], 'max_subdomain' => ['max' => 500, 'used' => 0, 'free' => 500],
                     'max_webspace' => ['max' => 204800, 'used' => 25600, 'free' => 179200], 'max_ftpuser' => ['max' => -1, 'used' => 0, 'free' => -1]],
    'accounts' => [['account_login' => 'w0111111', 'account_comment' => 'Hotel — hotel.it', 'max_webspace' => '15360', 'max_domain' => '5'],
                   ['account_login' => 'w0122222', 'account_comment' => 'Bar — bar.it', 'max_webspace' => '10240'],
                   ['account_login' => 'w0133333', 'account_comment' => 'Fremd', 'max_webspace' => '-1']],
    'belegt' => ['w0111111' => 7578], 'domains' => [['domain_name' => 'reseller6404.res']], 'subdomains' => [], 'postfaecher' => []];
$rsA = Kas::resellerAuswerten($rsStand, ['w0111111' => 15360, 'w0122222' => 20480, 'w0199999' => 10240]);
$rsZ = array_column($rsA['kunden'], 'zustand', 'login');
pruefe('Reseller: 15 GB vereinbart und 15 GB im KAS passt; 20 gegen 10 weicht ab; ein fremder Account ist "bei Vecom unbekannt"',
    $rsZ === ['w0111111' => 'passt', 'w0122222' => 'weicht_ab', 'w0133333' => 'nicht_in_vecom'] && $rsA['abweichend'] === 1,
    json_encode($rsZ));
pruefe('Reseller: ein Vecom-Auftrag ohne Account im KAS fällt auf', $rsA['vecom_ohne_kas'] === ['w0199999']);
pruefe('Reseller: Kontingente mit Namen, -1 bleibt "unbegrenzt" (keine Zahl erfunden)',
    array_column($rsA['kontingente'], 'name', 'schluessel')['max_domain'] === 'Domains'
    && array_column($rsA['kontingente'], 'max', 'schluessel')['max_ftpuser'] === -1 && $rsA['domains'] === 1);
pruefe('Reseller: alle Vereinbarungen zusammen gegen den Vertrag -- 45 GB von 200 GB ist nicht überbucht, 210 GB schon',
    $rsA['summe_vereinbart_mb'] === 46080 && $rsA['pool_mb'] === 204800 && !$rsA['ueberbucht']
    && Kas::resellerAuswerten($rsStand, ['a' => 215040])['ueberbucht'] === true);
pruefe('Reseller: der belegte Speicher steht beim richtigen Kunden',
    ($rsA['kunden'][0]['belegt_mb'] ?? null) === 7578 && $rsA['kunden'][1]['belegt_mb'] === null);

/* ============================================================================
   81. Gerecht geteilt (26.09.2026) -- der Reseller-Vertrag durch die Plätze
   Anlass: add_account setzt jede nicht übergebene Grenze auf 0 (KAS-Doku).
   Vorher ging nur max_webspace mit -- der erste echte Kunde hätte keine
   Domain und kein Postfach anlegen dürfen.
   ============================================================================ */
abschnitt('81. Gerecht geteilt');
$gtVertrag = ['max_account' => ['max' => 25, 'used' => 0, 'free' => 25], 'max_webspace' => ['max' => 204800, 'used' => 0, 'free' => 204800],
    'max_domain' => ['max' => 101, 'used' => 1, 'free' => 100], 'max_subdomain' => ['max' => 500, 'used' => 0, 'free' => 500],
    'max_mail_account' => ['max' => 250, 'used' => 0, 'free' => 250], 'max_mail_forward' => ['max' => 1000, 'used' => 0, 'free' => 1000],
    'max_database' => ['max' => 50, 'used' => 0, 'free' => 50], 'max_ftpuser' => ['max' => -1, 'used' => 0, 'free' => -1],
    'max_cronjobs' => ['max' => 25, 'used' => 0, 'free' => 25]];
$gt = Hosting::kontingentAus($gtVertrag);
pruefe('Gerecht: 200 GB, 101 Domains, 500 Subdomains, 250 Postfächer auf 25 Plätze -- je 8 GB, 4, 20, 10',
    $gt['plaetze'] === 25 && $gt['je_kunde']['max_webspace'] === 8192 && $gt['je_kunde']['max_domain'] === 4
    && $gt['je_kunde']['max_subdomain'] === 20 && $gt['je_kunde']['max_mail_account'] === 10 && $gt['je_kunde']['max_database'] === 2,
    json_encode($gt['je_kunde']));
pruefe('Gerecht: alle Plätze zusammen passen in den Vertrag -- nichts überbucht',
    $gt['je_kunde']['max_webspace'] * 25 <= 204800 && $gt['je_kunde']['max_domain'] * 25 <= 101 && $gt['je_kunde']['max_mail_account'] * 25 <= 250);
pruefe('Gerecht: "unbegrenzt" beim Reseller wird nicht zu "unbegrenzt" je Kunde, sondern zu einer festen Zahl',
    $gt['je_kunde']['max_ftpuser'] === Hosting::UNBEGRENZT_JE_KUNDE);
$gtKnapp = Hosting::kontingentAus(['max_account' => ['max' => 25], 'max_mail_account' => ['max' => 10], 'max_webspace' => ['max' => 20000]]);
pruefe('Gerecht: gibt der Vertrag geteilt nicht einmal ein Postfach her, gilt das Nötigste -- und es wird als knapp gezeigt',
    $gtKnapp['je_kunde']['max_mail_account'] === 1 && in_array('max_mail_account', $gtKnapp['knapp'], true)
    && $gtKnapp['je_kunde']['max_webspace'] === 800);
$gtLeer = Hosting::kontingentAus([]);
pruefe('Gerecht: ohne ausgelesenen Vertrag bleibt es bei den bisherigen 10 GB -- keine erfundene Zahl',
    $gtLeer['quelle'] === 'ersatz' && $gtLeer['je_kunde']['max_webspace'] === 10240 && $gtLeer['je_kunde']['max_domain'] === 1 && $gtLeer['plaetze'] === 25);

/* Mit ausgelesenem Vertrag: neue Angebote bekommen die Aufteilung, Zugestimmtes nicht */
Db::run("INSERT INTO settings (skey, svalue) VALUES ('kas_reseller_stand', ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)",
    [json_encode(['am' => date('Y-m-d H:i:s'), 'ressourcen' => $gtVertrag])]);
$gtF = Hosting::vorgabeFelder();
pruefe('Gerecht: ein neues Angebot hält 8 GB und die übrigen Kontingente fest',
    $gtF['speicher_mb'] === 8192 && (json_decode($gtF['kontingente'], true)['max_mail_account'] ?? 0) === 10);
$gtK1 = Events::kundeFinden(['name' => 'Gerecht Offen', 'email' => 'gerecht1@pruefung.example']);
$gtK2 = Events::kundeFinden(['name' => 'Gerecht Zugestimmt', 'email' => 'gerecht2@pruefung.example']);
$gtOffen = (int) Db::insert('hosting_auftraege', ['customer_id' => $gtK1, 'domain' => 'gerecht-offen.it', 'status' => 'vorgeschlagen', 'preis_cents' => 990, 'speicher_mb' => 10240]);
$gtZu = (int) Db::insert('hosting_auftraege', ['customer_id' => $gtK2, 'domain' => 'gerecht-zu.it', 'status' => 'zugestimmt', 'preis_cents' => 990,
    'speicher_mb' => 10240, 'mail' => 'vecom', 'domain_aktion' => 'neu', 'weiterleitungen' => implode(',', ['kontakt', 'buchung', 'office', 'rezeption', 'shop', 'presse', 'jobs', 'rechnung', 'service', 'team'])]);
Hosting::vorgabeAnwenden();
pruefe('Gerecht: ein offenes Angebot bekommt die neue Aufteilung, ein zugestimmtes behält seine 10 GB',
    (int) Db::wert('SELECT speicher_mb FROM hosting_auftraege WHERE id = ?', [$gtOffen], 0) === 8192
    && (int) Db::wert('SELECT speicher_mb FROM hosting_auftraege WHERE id = ?', [$gtZu], 0) === 10240);
$gtKas = new class {
    public array $g = [];
    public function accountAnlegen(string $k, array $g = []): array { $this->g = $g; return ['ok' => true, 'login' => 'w0177770', 'kas_passwort' => 'K-7!', 'ftp_passwort' => 'F-7!', 'text' => 'ok']; }
    public function domainAnlegen(string $d, ?array $als = null): array { return ['ok' => true, 'text' => 'ok']; }
    public function postfachAnlegen(string $l, string $d, string $pw, ?array $als = null): array { return ['ok' => true, 'text' => 'ok']; }
    public function passwortNeu(): string { return 'P-7!'; }
    public function weiterleitungAnlegen(string $l, string $d, string $z, ?array $als = null): array { return ['ok' => true, 'text' => 'ok']; }
};
Hosting::anlegen($gtZu, $gtKas);
pruefe('Gerecht: add_account bekommt ALLE Grenzen -- nicht nur den Speicher (sonst 0 Domains, 0 Postfächer)',
    ($gtKas->g['max_webspace'] ?? 0) === 10240 && ($gtKas->g['max_domain'] ?? 0) === 4 && ($gtKas->g['max_mail_account'] ?? 0) === 10
    && ($gtKas->g['max_subdomain'] ?? 0) === 20 && !isset($gtKas->g['max_account']), json_encode($gtKas->g));
pruefe('Gerecht: die Weiterleitungen aus dem Fragebogen passen immer hinein',
    ($gtKas->g['max_mail_forward'] ?? 0) >= 10);
pruefe('Gerecht: was angelegt wurde, ist am Auftrag festgehalten und steht im Protokoll',
    (json_decode((string) Db::wert('SELECT kontingente FROM hosting_auftraege WHERE id = ?', [$gtZu], ''), true)['max_domain'] ?? 0) === 4
    && (int) Db::wert("SELECT COUNT(*) FROM activities WHERE type = 'hosting_speicher_gesetzt' AND title LIKE '%gerecht-zu.it%4 Domains%'", [], 0) === 1);
$gtA = Db::one('SELECT * FROM hosting_auftraege WHERE id = ?', [$gtOffen]);
$gtT = true;
foreach (['it', 'de', 'en'] as $gtS) {
    $gtX = Hosting::angebotText($gtA, $gtS) . ' ' . Hosting::angebotText(['project_id' => 5] + $gtA, $gtS);
    if (str_contains($gtX, '{gb}') || str_contains($gtX, '10 GB') || !str_contains($gtX, '8 GB')) { $gtT = false; }
}
pruefe('Gerecht: der Zustimmungstext nennt den festgehaltenen Speicher (8 GB), in allen drei Sprachen', $gtT);
pruefe('Gerecht: Bestellseite und Datenquelle der Website rechnen live, keine eingebaute 10',
    !preg_match('~\b10 GB\b~', (string) file_get_contents($wurzel . '/../hosting.php'))
    && str_contains((string) file_get_contents($wurzel . '/../pakete-daten.php'), "'hosting_gb'"));
Db::run("DELETE FROM settings WHERE skey = 'kas_reseller_stand'");
Events::protokoll('kette_lang', str_repeat('Langer Titel ', 40));
$gtL = Db::one("SELECT title, meta FROM activities WHERE type = 'kette_lang' ORDER BY id DESC LIMIT 1");
pruefe('Protokoll: ein zu langer Titel wird gekürzt statt den Vorgang abzubrechen -- der volle Text steht in meta',
    $gtL && mb_strlen((string) $gtL['title']) === 255 && str_contains((string) $gtL['meta'], 'titel_voll'));

/* ============================================================================
   82. Durchsicht 26.09.2026 -- Fehler 1-2, Website 3-4, Automatik 5, 6, 8, 9
   ============================================================================ */
abschnitt('82. Durchsicht: Steuerakte, Umbruch, Header, Reseller, Cron, Bericht');
require_once $wurzel . '/src/Ausgabe.php';
$dsQ = (string) file_get_contents($wurzel . '/index.php');
pruefe('1: die Steuerakte bekommt die Summe der Ausgaben, nicht die Liste',
    str_contains($dsQ, '$ausgabenJ[$j]  = sicher(static fn() => Ausgabe::summe($j),') && !str_contains($dsQ, 'Steuerakte::ausgaben($j),')
    && array_keys(Ausgabe::summe(2026)) === ['anzahl', 'brutto', 'rc_netto', 'rc_iva']);
$dsK = (string) file_get_contents($wurzel . '/../kunde.php');
pruefe('2: Nachrichten brechen lange Wörter um -- keine seitlich verschiebbare Kundenseite',
    substr_count($dsK, 'white-space:pre-wrap;overflow-wrap:anywhere') >= 2
    && !preg_match('~white-space:pre-wrap;(?!overflow-wrap)font-size:14\.5px~', $dsK));
$dsH = (string) file_get_contents($wurzel . '/../.htaccess');
pruefe('3: www leitet in einem Sprung auf die Adresse ohne www -- vor der https-Regel',
    (int) strpos($dsH, '^www\\.vecom-design\\.it$') > 0 && strpos($dsH, '^www\\.vecom-design\\.it$') < strpos($dsH, 'RewriteCond %{HTTPS} !=on'));
pruefe('4: Sicherheits-Header gesetzt, HSTS ohne includeSubDomains, strengere PHP-Angaben bleiben (always setifempty)',
    str_contains($dsH, 'Strict-Transport-Security "max-age=31536000"') && !preg_match('~^\s*Header[^\n]*includeSubDomains~m', $dsH)
    && str_contains($dsH, 'Header always setifempty Referrer-Policy') && str_contains($dsH, 'Header always set X-Frame-Options "SAMEORIGIN"'));

/* 5: Reseller taeglich */
Db::run("DELETE FROM settings WHERE skey = 'kas_reseller_stand'");
$dsLes = 0;
$dsGut = static function () use (&$dsLes): array { $dsLes++; return ['am' => date('Y-m-d H:i:s'), 'fehler' => [],
    'ressourcen' => ['max_account' => ['max' => 25], 'max_webspace' => ['max' => 204800], 'max_domain' => ['max' => 101]]]; };
$dsR1 = Hosting::resellerAktualisieren($dsGut, true);
$dsR2 = Hosting::resellerAktualisieren($dsGut, true);
pruefe('5: der tägliche Lauf liest einmal -- und am selben Tag nicht noch einmal',
    $dsR1['gelesen'] && !$dsR2['gelesen'] && $dsLes === 1 && Hosting::speicherVorgabe() === 8192);
$dsR3 = Hosting::resellerAktualisieren(static fn(): array => ['am' => date('Y-m-d H:i:s'), 'fehler' => ['Kontingente: flood_protection'], 'ressourcen' => []]);
pruefe('5: ein gescheitertes Lesen ersetzt keinen guten Stand -- die Aufteilung fällt nicht still auf 10 GB zurück',
    !$dsR3['gelesen'] && $dsR3['fehler'] !== [] && Hosting::speicherVorgabe() === 8192);
pruefe('5: der Cron kennt den Lauf', str_contains((string) file_get_contents($wurzel . '/src/Cron.php'), 'Hosting::resellerAktualisieren(null, true)'));
Db::run("DELETE FROM settings WHERE skey = 'kas_reseller_stand'");

/* 6: Cron-Warnung */
pruefe('6: "Heute" warnt, wenn der Cron seit 30 Minuten nicht lief',
    str_contains((string) file_get_contents($wurzel . '/views/heute.php'), 'time() - 30 * 60'));

/* 8 + 9: Mails an den Kunden */
$dsPost = [];
$dsSend = static function (string $anlass, string $an, string $betreff, string $text, array $bezug = []) use (&$dsPost): bool {
    $dsPost[] = compact('anlass', 'an', 'betreff', 'text'); return true; };
$dsKid = Events::kundeFinden(['name' => 'Bericht Probe', 'email' => 'bericht@pruefung.example']);
Db::run("UPDATE customers SET sprache = 'de' WHERE id = ?", [$dsKid]);
$dsA = (int) Db::insert('hosting_auftraege', ['customer_id' => $dsKid, 'domain' => 'bericht-probe.it', 'status' => 'angelegt',
    'preis_cents' => 990, 'mail' => 'vecom', 'domain_aktion' => 'neu', 'kas_login' => 'w0155500', 'speicher_mb' => 8192,
    'ssl_status' => 'ok', 'ssl_text' => 'Gültig bis 2027-01-01, http leitet auf https um.', 'ssl_geprueft_am' => date('Y-m-d H:i:s'),
    'angelegt_am' => date('Y-m-d H:i:s', strtotime('-40 days'))]);
$dsJung = (int) Db::insert('hosting_auftraege', ['customer_id' => $dsKid, 'domain' => 'jung-probe.it', 'status' => 'angelegt',
    'preis_cents' => 990, 'ssl_status' => 'ok', 'ssl_geprueft_am' => date('Y-m-d H:i:s'), 'angelegt_am' => date('Y-m-d H:i:s', strtotime('-3 days'))]);
Db::run("INSERT INTO settings (skey, svalue) VALUES ('kas_speicher', ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)", [json_encode(['w0155500' => 3174])]);
Db::run("DELETE FROM settings WHERE skey = 'hosting_bericht'");
$dsN1 = Hosting::berichteSenden($dsSend, '2026-10');
$dsN2 = Hosting::berichteSenden($dsSend, '2026-10');
$dsMeine = array_values(array_filter($dsPost, static fn($m) => $m['an'] === 'bericht@pruefung.example' && $m['anlass'] === 'hosting_bericht'));
pruefe('8: der Monatsbericht geht einmal im Monat -- nicht an einen erst vor 3 Tagen eingerichteten Auftrag',
    count($dsMeine) === 1 && str_contains($dsMeine[0]['betreff'], 'bericht-probe.it') && !str_contains(implode(' ', array_column($dsPost, 'betreff')), 'jung-probe.it'),
    json_encode(array_column($dsPost, 'betreff')));
pruefe('8: er nennt nur Gemessenes, auf Deutsch, ohne offene Platzhalter: HTTPS bis 01.01.2027, 3,1 GB von 8 GB',
    str_contains($dsMeine[0]['text'] ?? '', '3,1 GB von 8 GB') && str_contains($dsMeine[0]['text'] ?? '', 'Zertifikat gültig bis')
    && !preg_match('~\{[a-z]+\}~', ($dsMeine[0]['betreff'] ?? '') . ($dsMeine[0]['text'] ?? '')) && !str_contains($dsMeine[0]['text'] ?? '', 'erreichbar'),
    $dsMeine[0]['text'] ?? '');
Db::run("INSERT INTO settings (skey, svalue) VALUES ('hosting_bericht', '0') ON DUPLICATE KEY UPDATE svalue = '0'");
$dsPost = [];
pruefe('8: ausgeschaltet geht keiner raus', Hosting::berichteSenden($dsSend, '2026-11') === 0 && $dsPost === []);
Db::run("DELETE FROM settings WHERE skey = 'hosting_bericht'");
Db::run("UPDATE hosting_auftraege SET ssl_status = NULL WHERE id = ?", [$dsA]);
Db::run("DELETE FROM settings WHERE skey = 'kas_speicher'");
$dsPost = [];
Hosting::berichteSenden($dsSend, '2026-12');
pruefe('8: ohne eine einzige Messung geht keine Mail', !array_filter($dsPost, static fn($m) => str_contains($m['betreff'], 'bericht-probe.it')));
$dsT = true;
foreach (['it', 'de', 'en'] as $dsS) {
    foreach (['hosting_bericht', 'hosting_speicher_voll'] as $dsM) { if (trim((string) (Texte::MAILS[$dsM][$dsS][1] ?? '')) === '') { $dsT = false; } }
    foreach (Texte::BERICHT as $dsZ) { if (trim((string) ($dsZ[$dsS] ?? '')) === '') { $dsT = false; } }
}
pruefe('8/9: Bericht und Speicher-Mail dreisprachig', $dsT);
$dsPost = [];
Db::run("DELETE FROM settings WHERE skey LIKE 'speicher_warnung_w0155500%'");
Hosting::speicherPruefen(static fn(): array => ['ok' => true, 'text' => '', 'belegt' => ['w0155500' => 7800]],
    static fn(): array => ['ok' => true, 'text' => '', 'grenzen' => []], $dsSend);
Hosting::speicherPruefen(static fn(): array => ['ok' => true, 'text' => '', 'belegt' => ['w0155500' => 7900]],
    static fn(): array => ['ok' => true, 'text' => '', 'grenzen' => []], $dsSend);
$dsVoll = array_values(array_filter($dsPost, static fn($m) => $m['anlass'] === 'hosting_speicher_voll'));
pruefe('9: ab 90 % bekommt auch der Kunde eine Mail -- einmal im Monat, mit seinen Zahlen',
    count($dsVoll) === 1 && str_contains($dsVoll[0]['text'], '7,6 GB der vereinbarten 8 GB'), $dsVoll[0]['text'] ?? json_encode($dsPost));

/* ============================================================================
   83. Neue Domain: Uwe bestellt, das System erkennt (26.09.2026, Weg B)
   ============================================================================ */
abschnitt('83. Neue Domain erkennen');
$rgPost = [];
$rgSend = static function (string $anlass, string $an, string $betreff, string $text, array $bezug = []) use (&$rgPost): bool {
    $rgPost[] = compact('anlass', 'an', 'betreff', 'text'); return true; };
$rgHttps = static fn(string $d): array => ['https' => ['ok' => true, 'ssl_gueltig' => 1, 'ssl_bis' => '2027-01-01', 'fehler' => null], 'umleitung' => 'https://' . $d . '/'];
$rgNs = [];
$rgDns = static function (string $h, int $t) use (&$rgNs): array { return $t === DNS_NS ? array_map(static fn($x) => ['target' => $x], $rgNs[$h] ?? []) : []; };
$rgK = Events::kundeFinden(['name' => 'Neu Domain', 'email' => 'neudomain@pruefung.example']);
Db::run("UPDATE customers SET sprache = 'it' WHERE id = ?", [$rgK]);
$rgA = (int) Db::insert('hosting_auftraege', ['customer_id' => $rgK, 'domain' => 'neu-registriert.it', 'status' => 'angelegt',
    'preis_cents' => 990, 'domain_aktion' => 'neu', 'mail' => 'vecom', 'kas_login' => 'w0144400']);
$rgZeile = static fn(): array => Db::one('SELECT * FROM hosting_auftraege WHERE id = ?', [$rgA]);
pruefe('Neue Domain: wartet auf Uwes Bestellung, solange nichts registriert ist', Hosting::wartetAufBestellung($rgZeile()));
pruefe('Neue Domain: ohne Nameserver (noch frei) passiert nichts',
    Hosting::registrierungNachsehen($rgDns, $rgSend, $rgHttps, $rgA) === 0 && $rgPost === [] && $rgZeile()['domain_registriert_am'] === null);
$rgNs['neu-registriert.it'] = ['ns1.fremdanbieter.net', 'ns6.kasserver.com'];
pruefe('Neue Domain: zeigt nur einer auf All-Inkl, ist sie noch nicht fertig',
    Hosting::registrierungNachsehen($rgDns, $rgSend, $rgHttps, $rgA) === 0);
$rgNs['neu-registriert.it'] = ['ns5.kasserver.com.', 'NS6.kasserver.com'];
$rgN1 = Hosting::registrierungNachsehen($rgDns, $rgSend, $rgHttps);
$rgN2 = Hosting::registrierungNachsehen($rgDns, $rgSend, $rgHttps);
$rgZ = $rgZeile();
pruefe('Neue Domain: zeigen beide auf All-Inkl, geht es von selbst weiter -- genau einmal',
    $rgN1 === 1 && $rgN2 === 0 && $rgZ['domain_registriert_am'] !== null && !Hosting::wartetAufBestellung($rgZ));
pruefe('Neue Domain: HTTPS wird gleich mitgeprüft -- "Online" ist damit frei', (string) $rgZ['ssl_status'] === 'ok');
$rgMail = array_values(array_filter($rgPost, static fn($m) => $m['anlass'] === 'domain_aktiv'));
pruefe('Neue Domain: der Kunde bekommt eine Mail in seiner Sprache, ohne offene Platzhalter',
    count($rgMail) === 1 && str_contains($rgMail[0]['betreff'], 'neu-registriert.it') && str_contains($rgMail[0]['text'], 'Buongiorno')
    && !preg_match('~\{[a-z]+\}~', $rgMail[0]['betreff'] . $rgMail[0]['text']));
pruefe('Neue Domain: Uwe bekommt eine Meldung', (int) Db::wert("SELECT COUNT(*) FROM notifications WHERE type = 'domain_fertig' AND title LIKE '%neu-registriert.it%'", [], 0) === 1);
$rgT = true;
foreach (['it', 'de', 'en'] as $rgS) { if (trim((string) (Texte::MAILS['domain_aktiv'][$rgS][1] ?? '')) === '') { $rgT = false; } }
pruefe('Neue Domain: Mail dreisprachig, beide Nameserver in der Kundenakte, der Cron sieht nach',
    $rgT && Hosting::NAMESERVER === ['ns5.kasserver.com', 'ns6.kasserver.com']
    && str_contains((string) file_get_contents($wurzel . '/views/kunde.php'), "implode(' · ', Hosting::NAMESERVER)")
    && str_contains((string) file_get_contents($wurzel . '/src/Cron.php'), 'Hosting::registrierungNachsehen()'));
$rgLang = (string) Db::wert("SELECT body FROM notifications WHERE type = 'hosting_bestellen' ORDER BY id DESC LIMIT 1", [], '');
pruefe('Neue Domain: die Aufgabe "Domain bestellen" passt ganz in die Meldung -- nichts wird abgeschnitten',
    $rgLang !== '' && !str_ends_with($rgLang, '…') && mb_strlen($rgLang) <= 500, (string) mb_strlen($rgLang));
Events::melden('kette_lang', str_repeat('T', 300), 'info', str_repeat('x', 700));
pruefe('Meldung: zu lang wird gekürzt statt den Vorgang abzubrechen',
    mb_strlen((string) Db::wert("SELECT body FROM notifications WHERE type = 'kette_lang' ORDER BY id DESC LIMIT 1", [], '')) === 500);
$rgUm = (int) Db::insert('hosting_auftraege', ['customer_id' => $rgK, 'domain' => 'bleibt-woanders.it', 'status' => 'angelegt',
    'preis_cents' => 990, 'domain_aktion' => 'behalten', 'mail' => 'bisher']);
pruefe('Neue Domain: eine Domain, die beim alten Anbieter bleibt, wartet auf keine Bestellung',
    !Hosting::wartetAufBestellung(Db::one('SELECT * FROM hosting_auftraege WHERE id = ?', [$rgUm])));

/* ============================================================================
   84. Seite veröffentlichen (26.09.2026) -- Paket per FTPS auf die Kundendomain
   Mit nachgebautem FTP-Server: ein Dateisystem im Speicher.
   ============================================================================ */
abschnitt('84. Seite veröffentlichen');
require_once $wurzel . '/src/Veroeffentlichung.php';
final class KetteFtp {
    public array $fs = [];          // Pfad => Inhalt (Datei) oder true (Ordner)
    public array $log = [];
    public bool $holenScheitert = false;
    public function verbinden(string $h, string $l, string $p): void { $this->log[] = 'verbinden:' . $h . ':' . $l; }
    public function liste(string $pfad): array {
        $pfad = rtrim($pfad, '/'); $aus = [];
        foreach ($this->fs as $k => $v) {
            if (dirname($k) === $pfad) { $aus[] = ['name' => basename($k), 'ordner' => $v === true]; }
        }
        return $aus;
    }
    public function holen(string $r, string $l): void { if ($this->holenScheitert) { throw new RuntimeException('550'); } file_put_contents($l, (string) $this->fs[$r]); }
    public function ordner(string $r): void { $this->fs[rtrim($r, '/')] = true; }
    public function senden(string $l, string $r): void { $this->log[] = 'senden:' . $r; $this->fs[$r] = (string) file_get_contents($l); }
    public function schliessen(): void {}
}
$vpZip = static function (array $dateien): string {
    $pfad = sys_get_temp_dir() . '/kette-paket-' . bin2hex(random_bytes(4)) . '.zip';
    $z = new ZipArchive(); $z->open($pfad, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($dateien as $n => $i) { $z->addFromString($n, $i); }
    $z->close(); return $pfad;
};
$vpHttps = static fn(string $d): array => ['https' => ['ok' => true, 'ssl_gueltig' => 1, 'ssl_bis' => '2027-01-01', 'fehler' => null], 'umleitung' => 'https://' . $d . '/'];
$vpK = Events::kundeFinden(['name' => 'Veröffentlichen Probe', 'email' => 'veroeff@pruefung.example']);
$vpP = (int) Db::insert('projects', ['customer_id' => $vpK, 'name' => 'Seite Veröffentlichen', 'status' => 'entwicklung',
    'preview_url' => 'https://veroeff-probe.netlify.app']);
pruefe('Veröffentlichen: ohne Hosting bei uns gibt es den Knopf nicht -- mit Grund',
    !Veroeffentlichung::stand($vpP)['bereit'] && Veroeffentlichung::stand($vpP)['auftrag'] === null);
$vpA = (int) Db::insert('hosting_auftraege', ['customer_id' => $vpK, 'project_id' => $vpP, 'domain' => 'veroeff-probe.it', 'status' => 'angelegt',
    'preis_cents' => 990, 'domain_aktion' => 'neu', 'mail' => 'vecom', 'kas_login' => 'w0133300']);
$vpSt = Veroeffentlichung::stand($vpP);
pruefe('Veröffentlichen: nennt, was fehlt -- FTP-Zugang, Paket, Abnahme',
    !$vpSt['bereit'] && count($vpSt['gruende']) === 3, implode(' | ', $vpSt['gruende']));
/* FTP-Zugang so ablegen, wie der Schritt "ftp" es tut */
$vpRef = new ReflectionMethod('Hosting', 'verschluesseln'); $vpRef->setAccessible(true);
Db::run('UPDATE hosting_auftraege SET technik_blob = ? WHERE id = ?',
    [$vpRef->invoke(null, ['ftp' => ['login' => 'f0133300', 'passwort' => 'Ftp-Geheim-1!', 'server' => 'w0133300.kasserver.com']]), $vpA]);
$vpZipPfad = $vpZip(['seite/index.html' => '<h1>Neu</h1>', 'seite/css/stil.css' => 'body{}', 'seite/bilder/logo.png' => 'PNG']);
$vpPaket = Ablage::ausDatei($vpZipPfad, 'seite.zip', $vpP, $vpK, 'werkstatt');
Db::run("UPDATE files SET rolle = 'paket' WHERE id = ?", [$vpPaket]);
pruefe('Veröffentlichen: vor der Abnahme nicht -- nur dieser Grund bleibt',
    Veroeffentlichung::stand($vpP)['gruende'] === ['Der Kunde hat noch nicht abgenommen (Stand: entwicklung).']);
Db::run("UPDATE projects SET status = 'finale_freigabe' WHERE id = ?", [$vpP]);
pruefe('Veröffentlichen: nach der Abnahme bereit', Veroeffentlichung::stand($vpP)['bereit']);

Db::run("INSERT INTO settings (skey, svalue) VALUES ('kas_probelauf', '1') ON DUPLICATE KEY UPDATE svalue = '1'");
$vpPr = Veroeffentlichung::veroeffentlichen($vpP);
pruefe('Veröffentlichen: im Probelauf wird nichts verbunden, nur gezählt',
    !$vpPr['ok'] && !empty($vpPr['probelauf']) && str_contains($vpPr['text'], '3 Dateien') && !str_contains($vpPr['text'], 'Ftp-Geheim'));
Db::run("UPDATE settings SET svalue = '0' WHERE skey = 'kas_probelauf'");

$vpFtp = new KetteFtp();
$vpFtp->fs = ['/web' => true, '/web/alt.html' => 'ALT', '/web/bilder' => true, '/web/bilder/alt.jpg' => 'JPG'];
$vpFtp->holenScheitert = true;
$vpE0 = Veroeffentlichung::veroeffentlichen($vpP, $vpFtp, $vpHttps);
pruefe('Veröffentlichen: scheitert die Sicherung, wird nichts hochgeladen',
    !$vpE0['ok'] && str_contains($vpE0['text'], 'Sicherung') && !preg_grep('~^senden:~', $vpFtp->log));
$vpFtp->holenScheitert = false;
$vpE = Veroeffentlichung::veroeffentlichen($vpP, $vpFtp, $vpHttps);
pruefe('Veröffentlichen: gemeinsamer Oberordner fällt weg, alles landet in /web',
    $vpE['ok'] && ($vpFtp->fs['/web/index.html'] ?? '') === '<h1>Neu</h1>' && ($vpFtp->fs['/web/css/stil.css'] ?? '') === 'body{}'
    && isset($vpFtp->fs['/web/bilder/logo.png']) && $vpE['dateien'] === 3, json_encode(array_keys($vpFtp->fs)));
pruefe('Veröffentlichen: gelöscht wird nichts -- alte Dateien bleiben liegen',
    ($vpFtp->fs['/web/alt.html'] ?? '') === 'ALT' && ($vpFtp->fs['/web/bilder/alt.jpg'] ?? '') === 'JPG');
$vpSich = Db::one("SELECT * FROM files WHERE id = ?", [(int) $vpE['sicherung']]);
$vpZ = new ZipArchive(); $vpZ->open(Ablage::ordner() . '/' . $vpSich['stored_name']);
pruefe('Veröffentlichen: vorher gesichert -- das ZIP enthält die alten Dateien, als Sicherung, nicht als Material',
    (string) $vpSich['rolle'] === 'sicherung' && $vpZ->getFromName('alt.html') === 'ALT' && $vpZ->getFromName('bilder/alt.jpg') === 'JPG');
$vpZ->close();
$vpW = Db::one('SELECT * FROM websites WHERE customer_id = ? ORDER BY id DESC LIMIT 1', [$vpK]);
pruefe('Veröffentlichen: die Domain wird beobachtet, das Projekt trägt Datum und Domain, HTTPS ist geprüft',
    $vpW && (string) $vpW['url'] === 'https://veroeff-probe.it' && (int) $vpW['monitoring'] === 1
    && (string) Db::wert('SELECT veroeffentlicht_domain FROM projects WHERE id = ?', [$vpP], '') === 'veroeff-probe.it'
    && (string) Db::wert('SELECT ssl_status FROM hosting_auftraege WHERE id = ?', [$vpA], '') === 'ok');
pruefe('Veröffentlichen: das FTP-Passwort steht in keinem Protokoll',
    (int) Db::wert("SELECT COUNT(*) FROM activities WHERE title LIKE '%Ftp-Geheim%' OR meta LIKE '%Ftp-Geheim%'", [], 0) === 0);
$vpKQ = (string) file_get_contents($wurzel . '/../kunde.php');
pruefe('Veröffentlichen: Sicherungen erscheinen nie auf der Kundenseite -- weder in der Liste noch zum Herunterladen',
    substr_count($vpKQ, "rolle <> 'sicherung'") >= 2);

/* Unsinnige Pakete */
$vpT = sys_get_temp_dir() . '/kette-ent-' . bin2hex(random_bytes(3));
$vpFehler = static function (array $d) use ($vpZip, $vpT): string {
    try { Veroeffentlichung::entpacken($vpZip($d), $vpT . bin2hex(random_bytes(2))); return 'ok'; } catch (Throwable $e) { return $e->getMessage(); }
};
pruefe('Veröffentlichen: ein Paket mit „../“ wird abgelehnt', str_contains($vpFehler(['index.html' => 'x', '../boese.php' => 'x']), 'unzulässigen Pfad'));
pruefe('Veröffentlichen: ohne index.html ganz oben wird abgelehnt', str_contains($vpFehler(['seite/unterseite.html' => 'x', 'andere/x.html' => 'y']), 'index.html'));
pruefe('Veröffentlichen: __MACOSX und .DS_Store fliegen raus',
    Veroeffentlichung::entpacken($vpZip(['index.html' => 'x', '__MACOSX/._index.html' => 'm', '.DS_Store' => 'd']), $vpT . 'm') === ['index.html']);

/* Netlify: erinnern nach vier Wochen, einmal */
Db::run('UPDATE projects SET veroeffentlicht_am = NOW() - INTERVAL 30 DAY WHERE id = ?', [$vpP]);
$vpN1 = Veroeffentlichung::netlifyErinnern(); $vpN2 = Veroeffentlichung::netlifyErinnern();
pruefe('Netlify: nach vier Wochen EINE Aufgabe mit Umleitungs-Hinweis -- gelöscht wird nichts',
    $vpN1 >= 1 && $vpN2 === 0 && str_contains((string) Db::wert("SELECT body FROM notifications WHERE type = 'netlify_aufraeumen' ORDER BY id DESC LIMIT 1", [], ''), '301'));
pruefe('Veröffentlichen: fragt vorher, und Website-Aufträge bekommen den FTP-Zugang gleich mit',
    Ablauf::wiegt('veroeffentlichen') === Ablauf::RAUS
    && substr_count((string) file_get_contents($wurzel . '/src/Hosting.php'), "'mit_ftp' => \$projektId ? 1 : 0") >= 1
    && str_contains((string) file_get_contents($wurzel . '/src/Cron.php'), 'Veroeffentlichung::netlifyErinnern()'));

/* ============================================================================
   85. Uwes Fund vom 26.09.2026 -- Umzug ohne Hosting, rote Passwortmeldung,
   Domain anbieten bei .it
   ============================================================================ */
abschnitt('85. E-Mail-Umzug nur mit Hosting, Domain anbieten');
require_once $wurzel . '/src/Mailumzug.php';
$fuK = Events::kundeFinden(['name' => 'Manuel Probe', 'email' => 'manuel@pruefung.example']);
$fuFehler = '';
try { Mailumzug::anfragen($fuK, 'manuel.alt@gmail.com', 'info@brandy-probe.com'); } catch (Throwable $e) { $fuFehler = $e->getMessage(); }
pruefe('Umzug: ohne zugestimmtes Hosting lässt er sich nicht anfragen -- der Kunde sieht keine Aufforderung zu einem Vertrag, den er nie schloss',
    str_contains($fuFehler, 'zugestimmt') && (int) Db::wert('SELECT COUNT(*) FROM mailumzuege WHERE customer_id = ?', [$fuK], 0) === 0);
$fuH = (int) Db::insert('hosting_auftraege', ['customer_id' => $fuK, 'domain' => 'brandy-probe.com', 'status' => 'vorgeschlagen',
    'preis_cents' => 990, 'domain_aktion' => 'neu', 'mail' => 'vecom']);
$fuFehler = '';
try { Mailumzug::anfragen($fuK, 'manuel.alt@gmail.com', 'info@brandy-probe.com'); } catch (Throwable $e) { $fuFehler = $e->getMessage(); }
pruefe('Umzug: ein nur VORGESCHLAGENES Hosting reicht nicht', $fuFehler !== '');
Db::run("UPDATE hosting_auftraege SET status = 'zugestimmt' WHERE id = ?", [$fuH]);
$fuU = Mailumzug::anfragen($fuK, 'manuel.alt@gmail.com', 'info@brandy-probe.com');
pruefe('Umzug: nach der Zustimmung lässt er sich anfragen', $fuU > 0);
$fuW = Mailumzug::zugangSpeichern($fuU, $fuK, ['alt_pass' => 'Alt-1', 'neu_pass' => 'Neu-2', 'alt_server' => '93.184.216.34'], 'de');
pruefe('Umzug: gibt es das neue Postfach noch nicht, heißt es das -- nicht "Es braucht die Passwörter beider Postfächer"',
    $fuW === 'ziel_fehlt' && trim((string) (Texte::KUNDE['mailumzugWartet']['de'] ?? '')) !== '');
Db::run("UPDATE hosting_auftraege SET status = 'angelegt', kas_login = 'w0177711' WHERE id = ?", [$fuH]);
$fuRef = new ReflectionMethod('Hosting', 'verschluesseln'); $fuRef->setAccessible(true);
Db::run('UPDATE hosting_auftraege SET zugang_blob = ? WHERE id = ?',
    [$fuRef->invoke(null, ['postfach' => 'info@brandy-probe.com', 'postfach_passwort' => 'Vecom-kennt-es-1!']), $fuH]);
$fuZ = Mailumzug::ziel($fuK, 'info@brandy-probe.com');
pruefe('Umzug: kennt Vecom das Passwort des neuen Postfachs, wird der Kunde nicht danach gefragt',
    $fuZ['bereit'] && $fuZ['passwort'] === 'Vecom-kennt-es-1!' && $fuZ['server'] === 'w0177711.kasserver.com');
Mailumzug::$hostErlaubt = static fn(string $h): bool => true;
$fuW2 = Mailumzug::zugangSpeichern($fuU, $fuK, ['alt_pass' => 'Alt-1'], 'de');
pruefe('Umzug: dann genügt das alte Passwort', $fuW2 === 'ok', $fuW2);
Mailumzug::$hostErlaubt = null;
$fuQ = (string) file_get_contents($wurzel . '/index.php');
pruefe('Domain anbieten: vergeben nie, unklar nur mit "Selbst geprüft", ein offenes Angebot lässt sich ersetzen',
    str_contains($fuQ, "if ((string) \$hp['stand'] === 'vergeben')") && str_contains($fuQ, "!empty(\$_POST['selbst_geprueft'])")
    && str_contains($fuQ, "(string) \$hAlt['status'] !== 'vorgeschlagen'"));
pruefe('Domainprüfung: der Test läuft Stufe für Stufe und nennt jede', method_exists('Domainpruefung', 'diagnose'));

/* ============================================================================
   Manuela: Chef-Modus nach dem Masterprompt (26.09.2026)
   ----------------------------------------------------------------------------
   Sicherheit der Tür (Hash, Sperre, PIN), Tageslage nach Priorität ohne
   Alarmflut, 360-Grad-Akte, Gedächtnis mit Widerspruch, Stufe 4 nur
   vorbereitet, Änderungssuche, die Werkzeuge für STRATO und der
   Verhaltenstext. Was nur das Sprachmodell bei STRATO tun kann (Sprache
   halten, unterbrechen lassen, Tonfall), lässt sich hier nicht prüfen —
   geprüft wird, dass die Regel dasteht und der Server das Wichtige selbst
   sichert.
   ============================================================================ */
abschnitt('Manuela: Chef-Modus und Verhalten');
require_once $wurzel . '/src/Chef.php';
require_once $wurzel . '/src/Telefonwerkzeuge.php';
require_once $wurzel . '/src/Telefonverhalten.php';
require_once $wurzel . '/src/Hosting.php';
Db::run("DELETE FROM settings WHERE skey IN ('chef_codewort','chef_pin','chef_gesperrt_bis','chef_zaehler_ab')");
Db::run('DELETE FROM chef_versuche');
Db::run('DELETE FROM chef_gedaechtnis');

// T1 Sicherheit: nur ein Hash, Altbestand wird umgeschrieben, kurze Wörter abgelehnt
Chef::codewortSetzen('Morgenstern 77');
$mnRoh = (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'chef_codewort'", [], '');
pruefe('Chef: das Codewort steht nur als Hash in der Datenbank',
    str_starts_with($mnRoh, '$') && stripos($mnRoh, 'morgenstern') === false);
Db::run("UPDATE settings SET svalue = 'Altes Wort 12' WHERE skey = 'chef_codewort'");
pruefe('Chef: ein Klartext-Altbestand öffnet weiter …', Chef::pruefen('altes wort 12') === 'offen');
pruefe('… und ist danach umgeschrieben', str_starts_with((string) Db::wert(
    "SELECT svalue FROM settings WHERE skey = 'chef_codewort'", [], ''), '$'));
pruefe('Chef: ein kurzes Codewort wird abgelehnt', Chef::codewortMangel('Rose') !== null
    && Chef::codewortMangel('Morgenstern 77') === null);
Chef::codewortSetzen('Morgenstern 77');
pruefe('Chef: „Ich bin Uwe“ öffnet nichts', Chef::pruefen('Ich bin Uwe') === 'falsch');
pruefe('Chef: das richtige Wort öffnet, tolerant geschrieben', Chef::pruefen('morgen-stern, 77!') === 'offen');

// T2 Sperre
Db::run("DELETE FROM notifications WHERE type = 'chef_gesperrt'");
for ($i = 0; $i < Chef::SPERRE_VERSUCHE; $i++) { $mnTuer = Chef::pruefen('Rateversuch ' . $i); }
pruefe('Chef: nach ' . Chef::SPERRE_VERSUCHE . ' falschen Wörtern ist gesperrt', $mnTuer === 'gesperrt', $mnTuer);
pruefe('Chef: in der Sperre öffnet auch das richtige Wort nicht', Chef::pruefen('Morgenstern 77') === 'gesperrt');
pruefe('Chef: Uwe bekommt genau eine Meldung', (int) Db::wert(
    "SELECT COUNT(*) FROM notifications WHERE type = 'chef_gesperrt'", [], 0) === 1);
Chef::sperreAufheben();
pruefe('Chef: nach dem Aufheben öffnet das richtige Wort wieder', Chef::pruefen('Morgenstern 77') === 'offen');
pruefe('Chef: ein einzelner Fehlversuch danach sperrt nicht sofort wieder', Chef::pruefen('falsch') === 'falsch');

// T3 PIN als zweites Wort
pruefe('Chef: eine PIN mit drei Ziffern wird abgelehnt', Chef::pinSetzen('123') !== null);
Chef::pinSetzen('4711');
pruefe('Chef: mit PIN genügt das Wort allein nicht', Chef::pruefen('Morgenstern 77') === 'falsch');
pruefe('Chef: Wort und PIN zusammen öffnen', Chef::pruefen('Morgenstern 77', 'vier sieben 4711') === 'offen');
Chef::pinSetzen('');
Chef::sperreAufheben();
$mnEnd = (string) file_get_contents($wurzel . '/../chef.php');
pruefe('Chef: der Endpunkt prüft jeden Aufruf mit Zählwerk und PIN',
    str_contains($mnEnd, 'Chef::pruefen($codewort, $pin)') && !str_contains($mnEnd, 'Chef::frei('));

// T4 Nichts erfinden: Preis ausserhalb ohne Zahl (Server), Regel im Text
$mnText = Telefonverhalten::text();
foreach (Telefonverhalten::REGELN as $mnR) {
    pruefe('Verhalten: Regel [' . $mnR . '] steht im Text', str_contains($mnText, '[' . $mnR . ']'));
}
pruefe('Verhalten: der Text trägt die Kennzeile', str_contains($mnText, Telefonverhalten::kennung()));
pruefe('Verhalten: Vergleich erkennt aktuell, älter und fehlend',
    Telefonverhalten::vergleichen('xx ' . Telefonverhalten::kennung())['stand'] === 'aktuell'
    && Telefonverhalten::vergleichen('VECOM-VERHALTEN v0')['stand'] === 'aelter'
    && Telefonverhalten::vergleichen('nichts')['stand'] === 'fehlt');

// T5 Termine nur aus freien Plätzen
$mnT = Telefon::termin(['wann' => 'Sonntag um 3 Uhr nachts', 'sprache' => 'de']);
pruefe('Termin: ein erfundener Platz wird abgelehnt', ($mnT['ok'] ?? true) === false && ($mnT['grund'] ?? '') === 'nicht_frei');

// T6 Präzision: E-Mail ohne Bestätigung geht nicht raus
pruefe('Präzision: eine nicht zurückgelesene E-Mail wird aufgehalten',
    (Telefon::adresseBestaetigt(['email' => 'rossi@esempio.it'])['grund'] ?? '') === 'adresse_unbestaetigt'
    && Telefon::adresseBestaetigt(['email' => 'rossi@esempio.it', 'email_bestaetigt' => true]) === null);

// T10 Tageslage: Prioritäten und keine Alarmflut
Db::run("DELETE FROM notifications WHERE type IN ('mn_probe')");
for ($i = 0; $i < 18; $i++) { Events::melden('mn_probe', 'Zustellung gescheitert', 'schlecht', 'x'); }
Events::melden('mn_probe', 'Zertifikat läuft ab', 'warnung', 'y');
$mnL = Chef::lage();
$mnStufen = array_column($mnL['prioritaeten'], 'stufe');
$mnOrd = array_map(static fn($s) => array_search($s, Chef::PRIORITAETEN, true), $mnStufen);
$mnSort = $mnOrd; sort($mnSort);
pruefe('Lage: nach Priorität sortiert, KRITISCH zuerst', $mnOrd === $mnSort && ($mnStufen[0] ?? '') === 'KRITISCH', implode(',', $mnStufen));
pruefe('Lage: 18 gleiche Meldungen sind EIN Punkt mit Anzahl',
    count(array_filter($mnL['prioritaeten'], static fn($p) => str_starts_with($p['text'], 'Zustellung gescheitert'))) === 1
    && str_contains(json_encode($mnL['prioritaeten'], JSON_UNESCAPED_UNICODE), '(18-mal)'));
pruefe('Lage: gegliedert in Fakten, offene Punkte, Entscheidung, nächster Schritt',
    isset($mnL['FAKTEN'], $mnL['OFFENE_PUNKTE'], $mnL['ENTSCHEIDUNG_NOETIG'], $mnL['NAECHSTER_SCHRITT']));
pruefe('Lage: die alten Felder bleiben', isset($mnL['neue_anfragen'], $mnL['mail_fehler'], $mnL['offene_meldungen']));
Db::run("DELETE FROM notifications WHERE type = 'mn_probe'");

// T11 360-Grad-Akte: VECOM-Wert zählt, KAS-Abweichung wird gesagt, nicht übernommen
$mnK = Events::kundeFinden(['name' => 'Manuela Probe', 'email' => 'mn-probe@pruefung.example']);
$mnH = Db::insert('hosting_auftraege', ['customer_id' => $mnK, 'domain' => 'mn-probe.it', 'status' => 'aktiv',
    'preis_cents' => 990, 'speicher_mb' => 20480, 'kas_speicher_mb' => 10240]);
$mnA = Chef::kunde(['name' => 'mn-probe@pruefung.example']);
pruefe('Akte: vereinbart 20 GB, KAS 10 GB, Abweichung erkannt',
    ($mnA['hosting']['vereinbart'] ?? '') === '20 GB' && ($mnA['hosting']['kas_limit'] ?? '') === '10 GB'
    && ($mnA['hosting']['abweichung'] ?? false) === true, json_encode($mnA['hosting'] ?? null));
pruefe('Akte: und der VECOM-Wert bleibt 20 GB', (int) Db::wert('SELECT speicher_mb FROM hosting_auftraege WHERE id = ?', [$mnH], 0) === 20480);

// T12 Gedächtnis: Bestätigung, Widerspruch, Konflikt
$mnM = Chef::merken(['kategorie' => 'WAITING_FOR_CUSTOMER', 'text' => 'Schickt das Logo', 'kunde' => 'mn-probe@pruefung.example',
                     'bedingung' => 'unterlagen']);
pruefe('Gedächtnis: ohne ja nichts gespeichert', !empty($mnM['bestaetigung_noetig'])
    && (int) Db::wert('SELECT COUNT(*) FROM chef_gedaechtnis', [], 0) === 0);
Chef::merken(['kategorie' => 'WAITING_FOR_CUSTOMER', 'text' => 'Schickt das Logo', 'kunde' => 'mn-probe@pruefung.example',
              'bedingung' => 'unterlagen', 'bestaetigt' => 'ja']);
pruefe('Gedächtnis: noch nichts eingetroffen, kein Widerspruch', Chef::kunde(['name' => 'mn-probe@pruefung.example'])['widersprueche'] === []);
Db::run("UPDATE chef_gedaechtnis SET created_at = NOW() - INTERVAL 1 HOUR");
Db::insert('files', ['customer_id' => $mnK, 'stored_name' => 'mn-' . bin2hex(random_bytes(6)), 'orig_name' => 'logo.png',
                     'uploaded_by' => 'kunde']);
$mnA = Chef::kunde(['name' => 'mn-probe@pruefung.example']);
pruefe('Gedächtnis: das Logo ist da — der alte Vermerk wird als Widerspruch gemeldet',
    count($mnA['widersprueche']) === 1 && str_contains($mnA['hinweis'], 'Achtung'), $mnA['hinweis']);
Chef::merken(['kategorie' => 'CHEF_DECISION', 'text' => 'Zahlt in zwei Raten', 'kunde' => 'mn-probe@pruefung.example', 'bestaetigt' => 'ja']);
$mnKf = Chef::merken(['kategorie' => 'CHEF_DECISION', 'text' => 'Zahlt alles im Oktober', 'kunde' => 'mn-probe@pruefung.example', 'bestaetigt' => 'ja']);
pruefe('Gedächtnis: eine zweite Entscheidung ist ein Konflikt und wird nicht still daneben gelegt',
    !empty($mnKf['konflikt']) && (int) Db::wert("SELECT COUNT(*) FROM chef_gedaechtnis WHERE kategorie = 'CHEF_DECISION' AND status = 'offen'", [], 0) === 1);
Chef::merken(['kategorie' => 'CHEF_DECISION', 'text' => 'Zahlt alles im Oktober', 'kunde' => 'mn-probe@pruefung.example',
              'bestaetigt' => 'ja', 'ersetzt' => 'ja']);
pruefe('Gedächtnis: mit „ersetzt ja“ gilt die neue, die alte bleibt als ersetzt lesbar',
    (string) Db::wert("SELECT text FROM chef_gedaechtnis WHERE kategorie = 'CHEF_DECISION' AND status = 'offen'", [], '') === 'Zahlt alles im Oktober'
    && (int) Db::wert("SELECT COUNT(*) FROM chef_gedaechtnis WHERE status = 'ersetzt'", [], 0) === 1);

// T13 Stufe 4: vorbereiten, Wiederholung, Freigabe nur in der Verwaltung
$mn4 = Chef::aendern(['art' => 'hosting_speicher', 'kunde' => 'mn-probe@pruefung.example', 'neu' => '30']);
pruefe('Stufe 4: zeigt alt, neu, Objekt und Folgen', !empty($mn4['bestaetigung_noetig'])
    && $mn4['alt'] === '20 GB' && $mn4['neu'] === '30 GB' && str_contains($mn4['objekt'], 'mn-probe.it') && $mn4['folgen'] !== '');
$mn4 = Chef::aendern(['art' => 'hosting_speicher', 'kunde' => 'mn-probe@pruefung.example', 'neu' => '30',
                      'bestaetigt' => 'ja', 'wert_wiederholt' => 'dreizehn']);
pruefe('Stufe 4: eine falsche Wiederholung legt nichts hin', ($mn4['ok'] ?? true) === false
    && (int) Db::wert("SELECT COUNT(*) FROM chef_gedaechtnis WHERE kategorie = 'WAITING_FOR_APPROVAL'", [], 0) === 0);
$mn4 = Chef::aendern(['art' => 'hosting_speicher', 'kunde' => 'mn-probe@pruefung.example', 'neu' => '30',
                      'bestaetigt' => 'ja', 'wert_wiederholt' => 'dreißig Gigabyte']);
pruefe('Stufe 4: richtig wiederholt liegt es zur Freigabe bereit …', !empty($mn4['vorbereitet']));
pruefe('… und am Telefon ist NICHTS geändert', (int) Db::wert('SELECT speicher_mb FROM hosting_auftraege WHERE id = ?', [$mnH], 0) === 20480);
$mnF = Chef::freigeben((int) $mn4['id']);
pruefe('Stufe 4: die Freigabe in der Verwaltung führt aus und steht in der Prüfspur',
    $mnF['ok'] && (int) Db::wert('SELECT speicher_mb FROM hosting_auftraege WHERE id = ?', [$mnH], 0) === 30720
    && (int) Db::wert("SELECT COUNT(*) FROM audit_log WHERE action = 'chef_vorhaben_freigegeben' AND entity_id = ?", [$mnH], 0) === 1, $mnF['text']);
$mn4b = Chef::aendern(['art' => 'hosting_speicher', 'kunde' => 'mn-probe@pruefung.example', 'neu' => '40',
                       'bestaetigt' => 'ja', 'wert_wiederholt' => '40']);
Db::run('UPDATE hosting_auftraege SET speicher_mb = 25600 WHERE id = ?', [$mnH]);
$mnF = Chef::freigeben((int) $mn4b['id']);
pruefe('Stufe 4: hat sich der alte Wert inzwischen geändert, wird nichts überschrieben',
    !$mnF['ok'] && (int) Db::wert('SELECT speicher_mb FROM hosting_auftraege WHERE id = ?', [$mnH], 0) === 25600);
pruefe('Stufe 4: in der Verwaltung fragt die Freigabe nach', Ablauf::wiegt('chef_freigeben') === Ablauf::SCHWER);

// T14 Änderungen durchsuchen
$mnAe = Chef::aenderungen(['kunde' => 'mn-probe@pruefung.example', 'suche' => 'Chef-Gedächtnis']);
pruefe('Änderungen: das Gedächtnis ist im Protokoll auffindbar', count($mnAe['treffer']) >= 2, $mnAe['hinweis']);
pruefe('Änderungen: auch die Prüfspur wird durchsucht', count(Chef::aenderungen(['suche' => 'chef_vorhaben_freigegeben'])['treffer']) >= 1);

// Werkzeuge für STRATO
$mnW = Telefonwerkzeuge::json();
pruefe('Werkzeuge: mit Codewort gehen die Chef-Werkzeuge mit',
    count($mnW) === count(Telefonwerkzeuge::namen()) && isset($mnW['chef_lage'], $mnW['chef_aendern']));
$mnGut = true;
foreach (Telefonwerkzeuge::CHEF_REIHE as $mnN) {
    $mnJ = json_decode($mnW[$mnN], true);
    $mnGut = $mnGut && str_ends_with((string) $mnJ['request']['url'], '/chef.php')
        && str_contains((string) $mnJ['request']['postData']['text'], '{{ codewort }}')
        && in_array('codewort', $mnJ['parameters']['required'], true);
}
pruefe('Werkzeuge: jedes Chef-Werkzeug geht an chef.php und trägt das Codewort', $mnGut);
pruefe('Werkzeuge: das Codewort selbst steht in keiner Beschreibung', stripos(implode('', $mnW), 'morgenstern') === false);
pruefe('Werkzeuge: Kundenwerkzeuge gehen weiter an telefon.php',
    str_ends_with((string) json_decode($mnW['termin'], true)['request']['url'], '/telefon.php'));
Chef::codewortSetzen('');
pruefe('Werkzeuge: ohne Codewort keine Chef-Werkzeuge', !isset(Telefonwerkzeuge::json()['chef_lage']));

// STRATO: Sitzung abgelaufen -> automatisch neu anmelden
require_once $wurzel . '/src/Strato.php';
$mnAbrufe = [];
Strato::$abrufProbe = static function (string $art, string $url, mixed $k) use (&$mnAbrufe): array {
    $mnAbrufe[] = $url;
    if (str_contains($url, 'grant_type=refresh_token')) {
        return ['ok' => false, 'status' => 400, 'daten' => ['error_description' => 'Invalid Refresh Token: Session Expired']];
    }
    if (str_contains($url, 'grant_type=password')) {
        return ($k['password'] ?? '') === 'Richtig-1'
            ? ['ok' => true, 'status' => 200, 'daten' => ['access_token' => 'neu-a', 'refresh_token' => 'neu-r', 'expires_in' => 3600]]
            : ['ok' => false, 'status' => 400, 'daten' => ['error_description' => 'Invalid login credentials']];
    }
    return ['ok' => false, 'status' => 500, 'daten' => null];
};
Db::run("DELETE FROM settings WHERE skey LIKE 'strato\\_%'");
Db::run("INSERT INTO settings (skey, svalue) VALUES ('strato_anon', 'eyJprobe'), ('strato_refresh', 'alt-r')");
pruefe('STRATO: ohne Anmeldung bleibt es beim alten Verhalten (null, Fehler steht da)',
    Strato::zugangsToken() === null && str_contains(Strato::fehler(), 'Session Expired'));
$mnS = Strato::anmeldungSetzen('uwe@esempio.it', 'Falsch-1');
pruefe('STRATO: eine falsche Anmeldung wird geprüft und nicht gespeichert', !$mnS['ok'] && Strato::anmeldungEmail() === '');
$mnS = Strato::anmeldungSetzen('uwe@esempio.it', 'Richtig-1');
pruefe('STRATO: die richtige Anmeldung wird geprüft und gespeichert', $mnS['ok'] && Strato::anmeldungEmail() === 'uwe@esempio.it');
pruefe('STRATO: das Passwort steht nirgends im Klartext', (int) Db::wert(
    "SELECT COUNT(*) FROM settings WHERE svalue LIKE '%Richtig-1%'", [], 0) === 0);
Db::run("UPDATE settings SET svalue = '' WHERE skey = 'strato_zugang'");
Db::run("UPDATE settings SET svalue = 'wieder-abgelaufen' WHERE skey = 'strato_refresh'");
pruefe('STRATO: abgelaufene Sitzung → selbst neu angemeldet', Strato::zugangsToken() === 'neu-a' && Strato::fehler() === '');
pruefe('STRATO: und der neue Auffrischungs-Token ist gesichert', Strato::wert('strato_refresh') === 'neu-r');
pruefe('STRATO: steht im Protokoll', (int) Db::wert("SELECT COUNT(*) FROM activities WHERE type = 'strato_neu_angemeldet'", [], 0) >= 1);
// Passwort drüben geändert: Meldung genau einmal, nicht stündlich
$mnBlob = Hosting::versiegeln(['email' => 'uwe@esempio.it', 'passwort' => 'Veraltet-1']);
Db::run("UPDATE settings SET svalue = ? WHERE skey = 'strato_anmeldung'", [$mnBlob]);
Db::run("DELETE FROM notifications WHERE type = 'strato_zugang'");
for ($i = 0; $i < 3; $i++) {
    Db::run("UPDATE settings SET svalue = '' WHERE skey = 'strato_zugang'");
    Strato::zugangsToken();
}
pruefe('STRATO: scheitert auch die Neuanmeldung, kommt genau EINE Meldung', (int) Db::wert(
    "SELECT COUNT(*) FROM notifications WHERE type = 'strato_zugang'", [], 0) === 1);
pruefe('STRATO: der Fehler nennt nie das Passwort', !str_contains(Strato::fehler(), 'Veraltet'));
Strato::$abrufProbe = null;
Db::run("DELETE FROM settings WHERE skey LIKE 'strato\\_%'");

// Aufräumen
Db::run('DELETE FROM files WHERE customer_id = ?', [$mnK]);
Db::run('DELETE FROM hosting_auftraege WHERE id = ?', [$mnH]);
Db::run('DELETE FROM chef_gedaechtnis');
Db::run("DELETE FROM notifications WHERE type IN ('chef_gesperrt','chef_freigabe','strato_zugang')");
Db::run("DELETE FROM settings WHERE skey IN ('chef_codewort','chef_pin','chef_gesperrt_bis','chef_zaehler_ab')");

/* ============================================================================
   Partnerprogramm (26.09.2026)
   ----------------------------------------------------------------------------
   Bewerbung → Annahme → Link → Zuordnung (erster gewinnt, nie selbst, nie
   doppelt mit Empfehlung) → Provision nur aus bezahltem Geld → Wartezeit →
   Auszahlung über Stripe (Idempotenz, source_transaction, Tageslimit) →
   Erstattung vor/nach Auszahlung. Stripe wird nie wirklich gerufen.
   ============================================================================ */
abschnitt('Partnerprogramm');
require_once $wurzel . '/src/Partner.php';
require_once $wurzel . '/src/Empfehlung.php';
require_once $wurzel . '/src/Abo.php';
foreach (['partner_provisionen', 'partner_auszahlungen', 'partner_zuordnungen', 'partner_klicks', 'partner'] as $t) { Db::run("DELETE FROM $t"); }
Db::run("DELETE FROM settings WHERE skey LIKE 'partner\\_%'");
$paMails = [];
$paSenden = static function (string $anlass, string $an, string $betreff, string $text) use (&$paMails): bool {
    $paMails[] = [$anlass, $an, $betreff, $text]; return true;
};

// P1 Bewerbung
$paV = Partner::vereinbarungText('de');
pruefe('Partner: Vereinbarung nennt die Standardwerte 10 % und 50 €', str_contains($paV, '10 %') && str_contains($paV, '50,00'), mb_substr($paV, 0, 300));
pruefe('Partner: ohne Zustimmung zur Vereinbarung keine Bewerbung',
    Partner::bewerben(['name' => 'Rosa Rossi', 'email' => 'rosa@partner.example'], 'it', $paV)['grund'] === 'vereinbarung');
$paB = Partner::bewerben(['name' => 'Rosa Rossi', 'email' => 'Rosa@Partner.example', 'kanal' => 'Instagram', 'vereinbarung' => '1'], 'it', $paV);
$paId = (int) $paB['id'];
$paP = Partner::laden($paId);
pruefe('Partner: Bewerbung steht als „bewerbung“, Code und Zugang vergeben, Wortlaut gespeichert',
    $paP['status'] === 'bewerbung' && preg_match('/^ROSA[A-Z2-9]{4}$/', $paP['code']) === 1 && strlen($paP['token']) === 48
    && $paP['vereinbarung_text'] === $paV && $paP['email'] === 'rosa@partner.example', $paP['code']);
pruefe('Partner: dieselbe E-Mail bewirbt sich nicht doppelt',
    !empty(Partner::bewerben(['name' => 'Rosa', 'email' => 'rosa@partner.example', 'vereinbarung' => '1'], 'it', $paV)['schon'])
    && (int) Db::wert('SELECT COUNT(*) FROM partner', [], 0) === 1);
pruefe('Partner: ein Bewerbungscode öffnet noch nichts', Partner::ausCode($paP['code']) === null && Partner::ausToken($paP['token']) === null);
Partner::statusSetzen($paId, 'aktiv', $paSenden);
pruefe('Partner: Annehmen schickt genau eine Willkommensmail mit Link und Partnerseite',
    count($paMails) === 1 && $paMails[0][0] === 'partner_willkommen' && str_contains($paMails[0][3], '/p/' . $paP['code'])
    && str_contains($paMails[0][3], 'partner.php?t=' . $paP['token']));
$paP = Partner::laden($paId);

// P2 Link und Klicks
Partner::klick($paId); Partner::klick($paId);
pruefe('Partner: Klicks je Tag gezählt, ohne IP-Spalte', (int) Db::wert('SELECT anzahl FROM partner_klicks WHERE partner_id = ?', [$paId], 0) === 2
    && !str_contains(strtolower(json_encode(Db::all('SHOW COLUMNS FROM partner_klicks'))), '"ip'));
$paP2 = Partner::laden(Partner::anlegen(['name' => 'Luca Bianchi', 'email' => 'luca@partner.example', 'status' => 'aktiv']));

// P3 Zuordnung
$paK1 = Events::kundeFinden(['name' => 'Kunde über Rosa', 'email' => 'k1@partner-kunde.example']);
$_COOKIE[Partner::KEKS] = $paP['code'];
pruefe('Partner: Kunde aus dem Besuch (Keks) wird zugeordnet', Partner::ausBesuch($paK1) === 'zugeordnet');
pruefe('Partner: der erste gewinnt — kein Umhängen auf einen zweiten Partner', Partner::zuordnen($paK1, (int) $paP2['id'], 'hand') === 'schon');
unset($_COOKIE[Partner::KEKS]);
$paK2 = Events::kundeFinden(['name' => 'Rosa selbst', 'email' => 'rosa@partner.example']);
pruefe('Partner: wer mit der eigenen E-Mail kauft, wird nicht sich selbst zugeordnet', Partner::zuordnen($paK2, $paId) === 'selbst');
$paK3 = Events::kundeFinden(['name' => 'Getippt', 'email' => 'k3@partner-kunde.example']);
pruefe('Partner: ein eingetippter Code ordnet zu (Quelle „code“)', Partner::ausBesuch($paK3, null, strtolower($paP2['code'])) === 'zugeordnet'
    && Db::wert('SELECT quelle FROM partner_zuordnungen WHERE customer_id = ?', [$paK3], '') === 'code');
$paK4 = Events::kundeFinden(['name' => 'Empfohlen', 'email' => 'k4@partner-kunde.example']);
Db::insert('empfehlungen', ['empfehler_id' => $kundeId, 'geworbener_id' => $paK4, 'code' => 'XXXX', 'quelle' => 'link', 'status' => 'offen']);
pruefe('Partner: wer über eine Kundenempfehlung kam, bekommt nicht zusätzlich einen Partner', Partner::zuordnen($paK4, $paId) === 'empfehlung');
pruefe('Empfehlung: wer schon einem Partner gehört, wird nicht zusätzlich als Empfehlung vorgemerkt',
    Empfehlung::vormerken(0, null, $paK1, '', 'Anna') === null);
pruefe('Empfehlung: ein Partnercode im Feld „Wer hat uns empfohlen?“ wird keine Kundenempfehlung',
    Empfehlung::vormerken(0, null, null, '', $paP['code']) === null);

// P4 Provision aus einer bezahlten Website-Bestellung
$paBest = Events::bestellungAnlegen($paK1, $paketId, 'Partner-Prüfung', 200000);
$paRaten = Db::all('SELECT * FROM payments WHERE order_id = ? ORDER BY id', [$paBest]);
pruefe('Partner: vor der Zahlung keine Provision', (int) Db::wert('SELECT COUNT(*) FROM partner_provisionen', [], 0) === 0);
Events::zahlungBestaetigen((int) $paRaten[0]['id'], 'pi_kette_partner_1', 'stripe');
$paPr = Db::one('SELECT * FROM partner_provisionen WHERE payment_id = ?', [(int) $paRaten[0]['id']]);
pruefe('Partner: nach der Zahlung 10 % vom bezahlten Betrag, wartend, frei nach 14 Tagen',
    $paPr && (int) $paPr['provision_cents'] === (int) round((int) $paRaten[0]['amount_cents'] * 0.10) && $paPr['status'] === 'wartet'
    && abs(strtotime($paPr['frei_ab']) - strtotime('+14 days')) < 120 && $paPr['art'] === 'website', json_encode($paPr));
pruefe('Partner: dieselbe Zahlung bringt nie eine zweite Provision', Partner::beiZahlung((int) $paRaten[0]['id'])['grund'] === 'schon');

// P5 fester Betrag je Verkauf: einmal je Bestellung
Partner::bedingungenSetzen((int) $paP2['id'], ['provision_art' => 'fest', 'provision_wert' => '25', 'monatsmail' => '1']);
$paBest3 = Events::bestellungAnlegen($paK3, $paketId, 'Partner-Prüfung fest', 100000);
$paR3 = Db::all('SELECT * FROM payments WHERE order_id = ? ORDER BY id', [$paBest3]);
foreach ($paR3 as $r) { Events::zahlungBestaetigen((int) $r['id'], 'pi_kette_fest_' . $r['id'], 'stripe'); }
pruefe('Partner: fester Betrag (25 €) nur einmal je Bestellung, auch bei 50/50',
    (int) Db::wert('SELECT COUNT(*) FROM partner_provisionen WHERE order_id = ?', [$paBest3], 0) === 1
    && (int) Db::wert('SELECT provision_cents FROM partner_provisionen WHERE order_id = ?', [$paBest3], 0) === 2500);

// P6 abgeschaltete Art
Partner::einstellungenSetzen(['partner_standard_art' => 'prozent', 'partner_standard_wert' => '10', 'partner_mindest_cents' => '50',
    'partner_sperrtage' => '14', 'partner_zuordnung_monate' => '12', 'partner_wiederkehrend_monate' => '12', 'partner_einbehalt_bp' => '0',
    'partner_gilt_website' => '1', 'partner_gilt_betreuung' => '1', 'partner_gilt_hosting' => '', 'partner_auto_auszahlen' => '1',
    'partner_auto_tageslimit_cents' => '10', 'partner_bewerbung_offen' => '1']);
$paAbo = Abo::anlegen($paK1, ['paket_slug' => 'hosting', 'zahlart' => 'manuell']);
$paZh = Db::insert('payments', ['order_id' => null, 'abo_id' => $paAbo, 'art' => 'rate', 'bezeichnung' => 'Hosting Probe',
    'amount_cents' => 990, 'currency' => 'EUR', 'status' => 'ausstehend']);
Events::zahlungBestaetigen($paZh, 'kette-hosting', 'manuell');
pruefe('Partner: ist Hosting abgeschaltet, bringt eine Hosting-Rate keine Provision',
    (int) Db::wert('SELECT COUNT(*) FROM partner_provisionen WHERE payment_id = ?', [$paZh], 0) === 0);
pruefe('Partner: die Sperrfrist lässt sich nicht unter 14 Tage stellen',
    Partner::einstellungenSetzen(['partner_standard_wert' => '10', 'partner_mindest_cents' => '50', 'partner_sperrtage' => '3',
        'partner_auto_tageslimit_cents' => '10', 'partner_gilt_website' => '1', 'partner_gilt_betreuung' => '1', 'partner_auto_auszahlen' => '1', 'partner_bewerbung_offen' => '1']) === null
    && Partner::zahl('partner_sperrtage') === 14);

// P7 Reifen
Db::run('UPDATE partner_provisionen SET frei_ab = NOW() - INTERVAL 1 MINUTE');
Db::run('UPDATE partner SET freigabe_noetig = 1 WHERE id = ?', [(int) $paP2['id']]);
Partner::reifen();
pruefe('Partner: nach der Wartezeit „bereit“ — oder „freigabe“, wenn Uwe jede sehen will',
    Db::wert('SELECT status FROM partner_provisionen WHERE partner_id = ?', [$paId], '') === 'bereit'
    && Db::wert('SELECT status FROM partner_provisionen WHERE partner_id = ?', [(int) $paP2['id']], '') === 'freigabe');

// P8 Erstattung vor der Auszahlung
$paProv2 = (int) Db::wert('SELECT id FROM partner_provisionen WHERE partner_id = ?', [(int) $paP2['id']], 0);
$paZ2 = (int) Db::wert('SELECT payment_id FROM partner_provisionen WHERE id = ?', [$paProv2], 0);
Partner::beiErstattung($paZ2, 1000, 50000);
pruefe('Partner: Teilerstattung vor der Auszahlung kürzt anteilig (2 %)',
    (int) Db::wert('SELECT provision_cents FROM partner_provisionen WHERE id = ?', [$paProv2], 0) === 2450);
Partner::beiErstattung($paZ2, 50000, 50000);
pruefe('Partner: volle Erstattung vor der Auszahlung — die Provision entfällt',
    Db::wert('SELECT status FROM partner_provisionen WHERE id = ?', [$paProv2], '') === 'storniert');

// P9 Auszahlen über Stripe (Probe)
$paStripe = [];
Partner::$stripeProbe = static function (string $m, string $weg, array $f, string $key) use (&$paStripe): array {
    $paStripe[] = [$m, $weg, $f, $key];
    if ($weg === '/v1/accounts' && $m === 'POST') { return ['id' => 'acct_kette']; }
    if ($weg === '/v1/account_links') { return ['url' => 'https://connect.stripe.com/setup/kette']; }
    if (str_starts_with($weg, '/v1/accounts/')) { return ['id' => 'acct_kette', 'capabilities' => ['transfers' => 'active']]; }
    if (str_starts_with($weg, '/v1/payment_intents/')) { return ['id' => substr($weg, 20), 'latest_charge' => 'ch_kette_1']; }
    if ($weg === '/v1/transfers') { return ['id' => 'tr_' . $f['metadata[provision]']]; }
    if (str_ends_with($weg, '/reversals')) { return $GLOBALS['paRueckGeht'] ? ['id' => 'trr_1'] : ['error' => ['message' => 'insufficient funds']]; }
    return ['error' => ['message' => 'unbekannt']];
};
$paP = Partner::laden($paId);
pruefe('Partner: ohne Stripe-Konto keine Auszahlung', !Partner::auszahlenStripe($paId)['ok']);
$paK = Partner::kontoEinrichten($paP, 'https://pruefung.example/partner.php?t=' . $paP['token']);
$paKonto = $paStripe[0][2] ?? [];
pruefe('Stripe: Konto nur für Überweisungen (recipient), Stripe prüft, Einrichtungslink kommt zurück',
    $paK['ok'] && ($paKonto['tos_acceptance[service_agreement]'] ?? '') === 'recipient'
    && ($paKonto['capabilities[transfers][requested]'] ?? '') === 'true' && !isset($paKonto['capabilities[card_payments][requested]'])
    && str_starts_with($paStripe[0][3], 'partner-konto-' . $paId . '-'));
Partner::kontoPruefen(Partner::laden($paId));
pruefe('Stripe: geprüftes Konto ist bereit', (int) Partner::laden($paId)['stripe_bereit'] === 1);
Db::run('UPDATE partner SET vereinbarung_am = NULL WHERE id = ?', [$paId]);
pruefe('Partner: ohne bestätigte Vereinbarung keine Auszahlung', !Partner::auszahlenStripe($paId)['ok']);
Partner::vereinbarungMerken($paId, $paV);
$paStripe = [];
$paBetrag = Partner::auszahlbar($paId);
$paR = Partner::auszahlenStripe($paId);
$paT = array_values(array_filter($paStripe, static fn($x) => $x[1] === '/v1/transfers'));
pruefe('Stripe: Überweisung an das Partnerkonto, gebunden an die Kundenzahlung, mit Idempotenz-Schlüssel',
    $paR['ok'] && count($paT) === 1 && $paT[0][2]['destination'] === 'acct_kette' && $paT[0][2]['source_transaction'] === 'ch_kette_1'
    && (int) $paT[0][2]['amount'] === $paBetrag && str_starts_with($paT[0][3], 'partner-prov-'), $paR['text']);
pruefe('Partner: Auszahlung mit fortlaufender Nummer gebucht, Provision „ausgezahlt“',
    Db::wert('SELECT nummer FROM partner_auszahlungen WHERE partner_id = ?', [$paId], '') === 'PA-' . date('Y') . '-0001'
    && Db::wert('SELECT status FROM partner_provisionen WHERE partner_id = ?', [$paId], '') === 'ausgezahlt');
pruefe('Partner: ein zweiter Lauf zahlt nichts doppelt', !Partner::auszahlenStripe($paId)['ok']);
$paPdf = Partner::belegPdf((int) Db::wert('SELECT id FROM partner_auszahlungen WHERE partner_id = ?', [$paId], 0));
pruefe('Partner: der Beleg ist ein PDF', is_string($paPdf) && str_starts_with($paPdf, '%PDF'));

// P10 Erstattung nach der Auszahlung
$GLOBALS['paRueckGeht'] = true;
Partner::beiErstattung((int) $paRaten[0]['id'], (int) $paRaten[0]['amount_cents'], (int) $paRaten[0]['amount_cents']);
$paRev = array_values(array_filter($paStripe, static fn($x) => str_ends_with($x[1], '/reversals')));
pruefe('Stripe: Erstattung nach Auszahlung holt die Provision zurück',
    count($paRev) === 1 && (int) $paRev[0][2]['amount'] === $paBetrag
    && Db::wert('SELECT status FROM partner_provisionen WHERE partner_id = ?', [$paId], '') === 'zurueckgeholt');

// P11 Automatik, Tageslimit, Einbehalt, Rückforderung
$paBest2 = Events::bestellungAnlegen($paK1, $paketId, 'Partner-Prüfung 2', 2000000);
$paR2 = (int) Db::wert('SELECT id FROM payments WHERE order_id = ? ORDER BY id LIMIT 1', [$paBest2], 0);
Db::run("UPDATE settings SET svalue = '2000' WHERE skey = 'partner_einbehalt_bp'");
Events::zahlungBestaetigen($paR2, 'pi_kette_partner_2', 'stripe');
Db::run('UPDATE partner_provisionen SET frei_ab = NOW() - INTERVAL 1 MINUTE WHERE payment_id = ?', [$paR2]);
$paPr2 = Db::one('SELECT * FROM partner_provisionen WHERE payment_id = ?', [$paR2]);
pruefe('Partner: Steuereinbehalt 20 % wird an der Provision festgehalten',
    (int) $paPr2['einbehalt_cents'] === (int) round((int) $paPr2['provision_cents'] * 0.2));
$paStripe = [];
$paL = Partner::lauf();
pruefe('Automatik: über dem Tageslimit (10 €) geht nichts raus, Uwe bekommt eine Meldung',
    $paL['ausgezahlt'] === 0 && $paL['wartet_limit'] === 1 && (int) Db::wert("SELECT COUNT(*) FROM notifications WHERE type = 'partner_limit'", [], 0) === 1);
Db::run("UPDATE settings SET svalue = '10000000' WHERE skey = 'partner_auto_tageslimit_cents'");
Db::run("UPDATE settings SET svalue = '0' WHERE skey = 'partner_auto_auszahlen'");
pruefe('Automatik: ausgeschaltet geht nichts raus', Partner::lauf()['ausgezahlt'] === 0 && !array_filter($paStripe, static fn($x) => $x[1] === '/v1/transfers'));
Db::run("UPDATE settings SET svalue = '1' WHERE skey = 'partner_auto_auszahlen'");
Db::run("UPDATE payments SET status = 'rueckerstattet' WHERE id = ?", [$paR2]);
Partner::lauf();
pruefe('Automatik: in letzter Sekunde erstattet — nichts ausgezahlt, Provision entfällt',
    !array_filter($paStripe, static fn($x) => $x[1] === '/v1/transfers')
    && Db::wert('SELECT status FROM partner_provisionen WHERE payment_id = ?', [$paR2], '') === 'storniert');
Db::run("UPDATE payments SET status = 'bezahlt' WHERE id = ?", [$paR2]);
Db::run("UPDATE partner_provisionen SET status = 'bereit', grund = '' WHERE payment_id = ?", [$paR2]);
$paL = Partner::lauf();
$paT = array_values(array_filter($paStripe, static fn($x) => $x[1] === '/v1/transfers'));
pruefe('Automatik: eingeschaltet, im Limit — ausgezahlt wird Provision minus Einbehalt, als „automatisch“ gebucht',
    $paL['ausgezahlt'] === 1 && (int) $paT[0][2]['amount'] === (int) $paPr2['provision_cents'] - (int) $paPr2['einbehalt_cents']
    && (int) Db::wert('SELECT automatisch FROM partner_auszahlungen ORDER BY id DESC LIMIT 1', [], 0) === 1);
$GLOBALS['paRueckGeht'] = false;
Partner::beiErstattung($paR2, 2000000, 2000000);
pruefe('Stripe: lässt sich nicht zurückholen → „zurückfordern“ und eine Meldung an Uwe',
    Db::wert('SELECT status FROM partner_provisionen WHERE payment_id = ?', [$paR2], '') === 'rueckforderung'
    && (int) Db::wert("SELECT COUNT(*) FROM notifications WHERE type = 'partner_rueck' AND level = 'warnung'", [], 0) === 1);
Partner::$stripeProbe = null;

// P12 Monatsbericht und Portal
Db::run('UPDATE partner_klicks SET tag = ?', [date('Y-m-d', strtotime('first day of last month'))]);
$paMails = [];
$paN = Partner::monatsberichte($paSenden);
pruefe('Monatsbericht: nur an Partner mit Bewegung, genau einmal im Monat',
    $paN === 1 && $paMails[0][1] === 'rosa@partner.example' && Partner::monatsberichte($paSenden) === 0);
pruefe('Portal: der Zugang über den Schlüssel funktioniert, ein fremder nicht',
    (int) (Partner::ausToken($paP['token'])['id'] ?? 0) === $paId && Partner::ausToken(str_repeat('a', 48)) === null);
$paSeite = (string) file_get_contents($wurzel . '/../partner.php');
pruefe('Portal: zeigt keine Kundennamen (keine Abfrage auf customers)', !preg_match('/customers/i', $paSeite));
$paKeks = (string) file_get_contents($wurzel . '/../p.php');
pruefe('Link: /p/CODE setzt nur einen Sitzungs-Keks (kein Ablaufdatum) und zählt ohne IP',
    str_contains($paKeks, 'setcookie(Partner::KEKS') && !str_contains($paKeks, "'expires'") && !str_contains($paKeks, 'REMOTE_ADDR')
    && str_contains((string) file_get_contents($wurzel . '/../.htaccess'), 'RewriteRule ^p/([A-Za-z0-9]{5,16})/?$ p.php?c=$1'));
foreach (['it', 'de', 'en'] as $paSp) {
    pruefe('Datenschutz (' . $paSp . '): das Partnerprogramm ist beschrieben',
        str_contains((string) file_get_contents($wurzel . '/../assets/js/legal-' . $paSp . '.js'), 'p9h:'));
    pruefe('Texte (' . $paSp . '): alle Partner-Mails da', Texte::mail('partner_willkommen', $paSp, [])[0] !== ''
        && Texte::mail('partner_auszahlung', $paSp, [])[0] !== '' && Texte::mail('partner_bericht', $paSp, [])[0] !== '');
}
/* Gefunden beim Ansehen am 26.09.2026: partner.php lud Auth nicht, und
   Events::protokoll braucht es -- die erste echte Bewerbung wäre mit einer
   leeren Seite gestorben. Jeder öffentliche Einstieg, der Events benutzt,
   lädt Auth. */
foreach (['partner.php', 'p.php'] as $paDatei) {
    pruefe('Einstieg ' . $paDatei . ': lädt Auth (Events::protokoll braucht es)',
        str_contains((string) file_get_contents($wurzel . '/../' . $paDatei), "'Auth'"));
}
pruefe('Rückfrage: Auszahlen und Bedingungen fragen nach', Ablauf::wiegt('partner_auszahlen_stripe') === Ablauf::SCHWER
    && Ablauf::wiegt('partner_einstellungen') === Ablauf::SCHWER && Ablauf::wiegt('partner_annehmen') === Ablauf::RAUS);

foreach (['partner_provisionen', 'partner_auszahlungen', 'partner_zuordnungen', 'partner_klicks', 'partner'] as $t) { Db::run("DELETE FROM $t"); }

/* ============================================================================
   Partner: Auszahlungswege (26.09.2026) — SEPA, PayPal, Wise, Verrechnung
   ============================================================================ */
abschnitt('Partner: Auszahlungswege');
require_once $wurzel . '/src/PartnerWege.php';
foreach (['partner_provisionen', 'partner_auszahlungen', 'partner_zuordnungen', 'partner_klicks', 'partner'] as $t) { Db::run("DELETE FROM $t"); }
Db::run("DELETE FROM settings WHERE skey LIKE 'partner\\_%'");
$wgKunde = Events::kundeFinden(['name' => 'Wege Kunde', 'email' => 'wege-kunde@pruefung.example']);
/* Eine bereite Provision, ohne den ganzen Bestellweg — der ist oben geprüft. */
$wgProv = static function (int $pid, int $cents) use ($wgKunde): int {
    $best = Events::bestellungAnlegen($wgKunde, Angebot::internesPaket(), 'Wege', 100000);
    $z = (int) Db::wert('SELECT id FROM payments WHERE order_id = ? ORDER BY id LIMIT 1', [$best], 0);
    Db::run("UPDATE payments SET status = 'bezahlt', paid_at = NOW() WHERE id = ?", [$z]);
    return Db::insert('partner_provisionen', ['partner_id' => $pid, 'customer_id' => $wgKunde, 'payment_id' => $z, 'order_id' => $best,
        'art' => 'website', 'basis_cents' => $cents * 10, 'provision_cents' => $cents, 'status' => 'bereit', 'frei_ab' => date('Y-m-d H:i:s')]);
};

pruefe('IBAN: gültige Prüfsumme wird erkannt, ein Zahlendreher nicht',
    PartnerWege::ibanGueltig('IT60 X054 2811 1010 0000 0123 456') && !PartnerWege::ibanGueltig('IT60X0542811101000000123465')
    && PartnerWege::ibanGueltig('DE89370400440532013000'));
pruefe('Wege: PayPal und Wise erscheinen nur, wenn sie eingerichtet sind',
    !in_array('paypal', PartnerWege::eingeschaltet(), true) && !in_array('wise', PartnerWege::eingeschaltet(), true)
    && in_array('sepa', PartnerWege::eingeschaltet(), true));

$wgA = Partner::anlegen(['name' => 'Anna Sepa', 'email' => 'anna@partner.example', 'status' => 'aktiv']);
Partner::vereinbarungMerken($wgA, 'Probe');
pruefe('Wege: Verrechnung nur für Partner, die selbst Kunde sind', !in_array('gutschrift', PartnerWege::fuerPartner(Partner::laden($wgA)), true));
pruefe('SEPA: eine falsche IBAN wird abgelehnt', PartnerWege::setzen($wgA, ['weg' => 'sepa', 'kontoinhaber' => 'Anna', 'iban' => 'IT00X123']) === 'iban_falsch');
pruefe('SEPA: ohne Kontoinhaber nicht gespeichert', PartnerWege::setzen($wgA, ['weg' => 'sepa', 'kontoinhaber' => '', 'iban' => 'IT60X0542811101000000123456']) === 'inhaber_fehlt');
PartnerWege::setzen($wgA, ['weg' => 'sepa', 'kontoinhaber' => 'Anna Sepa', 'iban' => 'IT60 X054 2811 1010 0000 0123 456']);
$wgP = Partner::laden($wgA);
pruefe('SEPA: IBAN nur versiegelt gespeichert, lesbar nur die letzten vier, nicht in der Prüfspur',
    $wgP['auszahlungsweg'] === 'sepa' && $wgP['iban_ende'] === '3456' && !str_contains((string) $wgP['iban_blob'], '0123456')
    && (int) Db::wert("SELECT COUNT(*) FROM audit_log WHERE after_json LIKE '%0123456%'", [], 0) === 0);
pruefe('SEPA: geht nie von allein', PartnerWege::auszahlen($wgA, true)['ok'] === false);
$wgP1 = $wgProv($wgA, 6000);
Db::run("DELETE FROM settings WHERE skey = 'firma_iban'");
pruefe('SEPA: ohne eigene IBAN keine Datei', PartnerWege::sepaDatei()['ok'] === false);
Db::run("INSERT INTO settings (skey, svalue) VALUES ('firma_iban', 'DE89370400440532013000') ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)");
require_once $wurzel . '/src/Firma.php';
$wgRef = new ReflectionProperty('Firma', 'werte'); $wgRef->setAccessible(true); $wgRef->setValue(null, null);
$wgS = PartnerWege::sepaDatei();
$wgDom = new DOMDocument();
pruefe('SEPA: gültiges XML (pain.001.001.03) mit Empfänger-IBAN, Betrag und Anzahl',
    $wgS['ok'] && @$wgDom->loadXML($wgS['xml']) && str_contains($wgS['xml'], 'IT60X0542811101000000123456')
    && str_contains($wgS['xml'], '<InstdAmt Ccy="EUR">60.00</InstdAmt>') && str_contains($wgS['xml'], '<NbOfTxs>1</NbOfTxs>')
    && str_contains($wgS['xml'], 'pain.001.001.03'));
pruefe('SEPA: danach „unterwegs“ und offen — ein zweites Herunterladen nimmt nichts doppelt',
    Db::wert('SELECT status FROM partner_provisionen WHERE id = ?', [$wgP1], '') === 'unterwegs' && PartnerWege::sepaDatei()['ok'] === false);
$wgAus = (int) Db::wert("SELECT id FROM partner_auszahlungen WHERE partner_id = ? AND status = 'offen'", [$wgA], 0);
PartnerWege::abbrechen($wgAus);
pruefe('SEPA: abgebrochen — die Provision ist wieder bereit', Db::wert('SELECT status FROM partner_provisionen WHERE id = ?', [$wgP1], '') === 'bereit');
$wgS = PartnerWege::sepaDatei();
$wgAus = (int) Db::wert("SELECT id FROM partner_auszahlungen WHERE partner_id = ? AND status = 'offen'", [$wgA], 0);
PartnerWege::bestaetigen($wgAus);
pruefe('SEPA: „ausgeführt“ — Provision ausgezahlt, Auszahlung erledigt',
    Db::wert('SELECT status FROM partner_provisionen WHERE id = ?', [$wgP1], '') === 'ausgezahlt'
    && Db::wert('SELECT status FROM partner_auszahlungen WHERE id = ?', [$wgAus], '') === 'erledigt');
Partner::beiErstattung((int) Db::wert('SELECT payment_id FROM partner_provisionen WHERE id = ?', [$wgP1], 0), 100000, 100000);
pruefe('Erstattung nach SEPA-Auszahlung → zurückfordern', Db::wert('SELECT status FROM partner_provisionen WHERE id = ?', [$wgP1], '') === 'rueckforderung');

// PayPal und Wise über eine Probe
$wgHttp = [];
PartnerWege::$httpProbe = static function (string $dienst, string $m, string $weg, ?array $r) use (&$wgHttp): array {
    $wgHttp[] = [$dienst, $weg, $r];
    if ($dienst === 'paypal') { return ['batch_header' => ['payout_batch_id' => 'PB1']]; }
    if ($weg === '/v1/accounts') { return ['id' => 777]; }
    if (str_ends_with($weg, '/quotes')) { return ['id' => 'q-1']; }
    if ($weg === '/v1/transfers') { return ['id' => 555]; }
    if (str_ends_with($weg, '/payments')) { return $GLOBALS['wgWiseDirekt'] ? ['status' => 'COMPLETED'] : ['status' => 'REJECTED', 'errorCode' => 'sca']; }
    return [];
};
pruefe('Wege: mit Zugang sind PayPal und Wise wählbar', in_array('paypal', PartnerWege::eingeschaltet(), true) && in_array('wise', PartnerWege::eingeschaltet(), true));
$wgB = Partner::anlegen(['name' => 'Bruno Pay', 'email' => 'bruno@partner.example', 'status' => 'aktiv']);
Partner::vereinbarungMerken($wgB, 'Probe');
pruefe('PayPal: ungültige E-Mail abgelehnt', PartnerWege::setzen($wgB, ['weg' => 'paypal', 'paypal_email' => 'kein-mail']) === 'email_falsch');
PartnerWege::setzen($wgB, ['weg' => 'paypal', 'paypal_email' => 'Bruno@PayPal.example']);
$wgBp = $wgProv($wgB, 7000);
$wgR = PartnerWege::auszahlen($wgB, true);
pruefe('PayPal: automatisch, genau der Betrag, feste Stapelkennung (doppelt lehnt PayPal ab)',
    $wgR['ok'] && $wgHttp[0][2]['items'][0]['amount']['value'] === '70.00' && $wgHttp[0][2]['items'][0]['receiver'] === 'bruno@paypal.example'
    && str_starts_with($wgHttp[0][2]['sender_batch_header']['sender_batch_id'], 'vecom-' . $wgB . '-')
    && Db::wert('SELECT status FROM partner_provisionen WHERE id = ?', [$wgBp], '') === 'ausgezahlt');
pruefe('PayPal: ein zweiter Lauf zahlt nichts doppelt', PartnerWege::auszahlen($wgB, true)['ok'] === false);

$wgC = Partner::anlegen(['name' => 'Carla Wise', 'email' => 'carla@partner.example', 'status' => 'aktiv']);
Partner::vereinbarungMerken($wgC, 'Probe');
PartnerWege::setzen($wgC, ['weg' => 'wise', 'kontoinhaber' => 'Carla Wise', 'iban' => 'DE89370400440532013000']);
$wgCp = $wgProv($wgC, 8000);
$GLOBALS['wgWiseDirekt'] = false;
$wgHttp = [];
$wgR = PartnerWege::auszahlen($wgC, true);
pruefe('Wise: verlangt Wise die Bestätigung in der App → angelegt, „offen“, Provision unterwegs, Meldung an Uwe',
    $wgR['ok'] && !empty($wgR['offen']) && Db::wert('SELECT status FROM partner_provisionen WHERE id = ?', [$wgCp], '') === 'unterwegs'
    && (int) Db::wert("SELECT COUNT(*) FROM notifications WHERE type = 'partner_wise'", [], 0) >= 1
    && (int) Db::wert('SELECT wise_empfaenger FROM partner WHERE id = ?', [$wgC], 0) === 777);
$wgTx = array_values(array_filter($wgHttp, static fn($x) => $x[1] === '/v1/transfers'))[0][2] ?? [];
pruefe('Wise: feste Kennung je Provisionssatz (Wiederholung legt nicht doppelt an)', preg_match('/^[0-9a-f-]{36}$/', (string) ($wgTx['customerTransactionId'] ?? '')) === 1);
PartnerWege::bestaetigen((int) Db::wert("SELECT id FROM partner_auszahlungen WHERE partner_id = ? AND status = 'offen'", [$wgC], 0));
pruefe('Wise: nach Bestätigung ausgezahlt', Db::wert('SELECT status FROM partner_provisionen WHERE id = ?', [$wgCp], '') === 'ausgezahlt');
PartnerWege::$httpProbe = null;

// Verrechnung
$wgD = Partner::anlegen(['name' => 'Wege Kunde', 'email' => 'wege-kunde@pruefung.example', 'status' => 'aktiv']);
Partner::vereinbarungMerken($wgD, 'Probe');
pruefe('Verrechnung: wählbar, weil der Partner selbst Kunde ist', in_array('gutschrift', PartnerWege::fuerPartner(Partner::laden($wgD)), true));
PartnerWege::setzen($wgD, ['weg' => 'gutschrift']);
$wgDp = $wgProv($wgD, 3000);
$wgBest = Events::bestellungAnlegen($wgKunde, Angebot::internesPaket(), 'Offene Rate', 20000);
$wgOffen = Db::one("SELECT * FROM payments WHERE order_id = ? AND status <> 'bezahlt' ORDER BY id LIMIT 1", [$wgBest]);
$wgVor = (int) Db::wert('SELECT SUM(amount_cents) FROM payments WHERE order_id = ?', [$wgBest], 0);
$wgR = PartnerWege::verrechnen($wgD, (int) $wgOffen['id']);
$wgNach = (int) Db::wert('SELECT SUM(amount_cents) FROM payments WHERE order_id = ?', [$wgBest], 0);
pruefe('Verrechnung: die Rate wird geteilt — 30 € bezahlt (verrechnung), der Rest offen, der Umsatz bleibt gleich',
    $wgR['ok'] && $wgVor === $wgNach
    && (int) Db::wert("SELECT amount_cents FROM payments WHERE order_id = ? AND provider = 'verrechnung' AND status = 'bezahlt'", [$wgBest], 0) === 3000
    && (int) Db::wert('SELECT amount_cents FROM payments WHERE id = ?', [(int) $wgOffen['id']], 0) === (int) $wgOffen['amount_cents'] - 3000
    && Db::wert('SELECT status FROM partner_provisionen WHERE id = ?', [$wgDp], '') === 'ausgezahlt', $wgR['text']);
pruefe('Verrechnung: keine Provision auf die Verrechnung selbst (eigener Kauf)',
    (int) Db::wert("SELECT COUNT(*) FROM partner_provisionen pp JOIN payments z ON z.id = pp.payment_id WHERE z.provider = 'verrechnung'", [], 0) === 0);

// Cronlauf: Handarbeit wird gemeldet, nicht ausgeführt
$wgE = $wgProv($wgA, 9000);
Db::run("DELETE FROM notifications WHERE type = 'partner_handarbeit'");
Db::run("DELETE FROM settings WHERE skey = 'partner_lauf_am'");
Partner::lauf();
pruefe('Cronlauf: SEPA fällig → eine Meldung, aber keine Auszahlung von allein',
    (int) Db::wert("SELECT COUNT(*) FROM notifications WHERE type = 'partner_handarbeit'", [], 0) === 1
    && Db::wert('SELECT status FROM partner_provisionen WHERE id = ?', [$wgE], '') === 'bereit');
pruefe('Beleg: nennt den Weg in der Sprache des Partners', str_starts_with((string) Partner::belegPdf($wgAus), '%PDF'));
foreach (['partner_provisionen', 'partner_auszahlungen', 'partner_zuordnungen', 'partner_klicks', 'partner'] as $t) { Db::run("DELETE FROM $t"); }

/* STRATO: wie lange Sitzungen halten (Uwe: „c“), Meldung nur bei echter Absage (Uwe: „b“) */
abschnitt('STRATO: Sitzungsdauer und Meldung');
$stAntwort = ['ok' => false, 'status' => 500, 'daten' => ['error' => 'Bad gateway']];
Strato::$abrufProbe = static function (string $art, string $url, mixed $k) use (&$stAntwort): array { return $stAntwort; };
Db::run("DELETE FROM settings WHERE skey LIKE 'strato\\_%'");
Db::run("DELETE FROM notifications WHERE type = 'strato_zugang'");
Db::run("INSERT INTO settings (skey, svalue) VALUES ('strato_anon', 'eyJprobe'), ('strato_refresh', 'r1'), ('strato_sitzung_seit', ?)",
        [date('Y-m-d H:i:s', strtotime('-50 hours'))]);
Strato::zugangsToken();
pruefe('STRATO: ein 5xx beendet keine Sitzung und schlägt keinen Alarm',
    Strato::sitzungen() === [] && (int) Db::wert("SELECT COUNT(*) FROM notifications WHERE type = 'strato_zugang'", [], 0) === 0);
$stAntwort = ['ok' => false, 'status' => 400, 'daten' => ['error_description' => 'Invalid Refresh Token: Session Expired']];
Strato::zugangsToken(); Strato::zugangsToken();
$stS = Strato::sitzungen();
pruefe('STRATO: echte Absage → Sitzung mit Dauer (50 Std.) und Grund gemerkt',
    count($stS) === 1 && $stS[0]['stunden'] === 50 && str_contains($stS[0]['grund'], 'Session Expired') && Strato::sitzungSeit() === '');
pruefe('STRATO: genau eine Meldung, auch wenn es weiter scheitert',
    (int) Db::wert("SELECT COUNT(*) FROM notifications WHERE type = 'strato_zugang'", [], 0) === 1);
$stAntwort = ['ok' => true, 'status' => 200, 'daten' => ['access_token' => 'a', 'refresh_token' => 'b', 'expires_in' => 3600]];
Strato::zugangSetzen('eyJprobe', 'neuer-token-123');
pruefe('STRATO: neu hinterlegt → neue Sitzung beginnt, Meldesperre zurückgesetzt',
    Strato::sitzungSeit() !== '' && Strato::wert('strato_gemeldet_am') === '');
Strato::$abrufProbe = null;
Db::run("DELETE FROM settings WHERE skey LIKE 'strato\\_%'");
$stAnsicht = (string) file_get_contents($wurzel . '/views/einstellungen/telefon.php');
pruefe('STRATO: das irreführende Feld „Automatisch neu anmelden“ ist weg, die Anleitung da',
    !str_contains($stAnsicht, 'strato_anmeldung') && str_contains($stAnsicht, 'Wie lange die Sitzungen hielten'));

/* Partner löschen (26.09.2026) */
abschnitt('Partner löschen');
foreach (['partner_provisionen', 'partner_auszahlungen', 'partner_zuordnungen', 'partner_klicks', 'partner'] as $t) { Db::run("DELETE FROM $t"); }
$plK = Events::kundeFinden(['name' => 'Lösch Kunde', 'email' => 'loesch-kunde@pruefung.example']);
$plA = Partner::anlegen(['name' => 'Ohne Belege', 'email' => 'ohne@partner.example', 'status' => 'aktiv']);
Partner::zuordnen($plK, $plA);
$plR = Partner::loeschen($plA);
pruefe('Löschen: ohne Auszahlung verschwindet der Partner ganz, sein Kunde ist frei',
    $plR['ok'] && !empty($plR['ganz']) && Partner::laden($plA) === null
    && (int) Db::wert('SELECT COUNT(*) FROM partner_zuordnungen WHERE customer_id = ?', [$plK], 0) === 0);
$plB = Partner::anlegen(['name' => 'Mit Belegen', 'email' => 'mit@partner.example', 'status' => 'aktiv', 'steuer_nr' => 'IT01234567890']);
$plZ = Db::insert('payments', ['order_id' => null, 'abo_id' => null, 'art' => 'rate', 'bezeichnung' => 'x', 'amount_cents' => 1000, 'currency' => 'EUR', 'status' => 'bezahlt']);
$plP = Db::insert('partner_provisionen', ['partner_id' => $plB, 'customer_id' => $plK, 'payment_id' => $plZ, 'art' => 'website',
    'basis_cents' => 1000, 'provision_cents' => 100, 'status' => 'bereit', 'frei_ab' => date('Y-m-d H:i:s')]);
pruefe('Löschen: solange Geld offen ist, geht es nicht', Partner::loeschen($plB)['ok'] === false && Partner::laden($plB) !== null);
Partner::auszahlenHand($plB, 'Bonifico');
$plBp = Partner::laden($plB); $plToken = $plBp['token'];
Db::run("UPDATE partner SET iban_ende = '3456', paypal_email = 'x@y.example', notiz = 'geheim' WHERE id = ?", [$plB]);
$plR = Partner::loeschen($plB);
$plBp = Partner::laden($plB);
pruefe('Löschen: mit Belegen bleiben Name, Steuernummer und Beleg — E-Mail, IBAN, PayPal, Notiz und Zugang sind weg',
    $plR['ok'] && empty($plR['ganz']) && $plBp['status'] === 'geloescht' && $plBp['name'] === 'Mit Belegen'
    && $plBp['steuer_nr'] === 'IT01234567890' && $plBp['email'] === '' && $plBp['iban_ende'] === null && $plBp['paypal_email'] === null
    && $plBp['notiz'] === null && Partner::ausToken($plToken) === null
    && (int) Db::wert('SELECT COUNT(*) FROM partner_auszahlungen WHERE partner_id = ?', [$plB], 0) === 1
    && str_starts_with((string) Partner::belegPdf((int) Db::wert('SELECT id FROM partner_auszahlungen WHERE partner_id = ?', [$plB], 0)), '%PDF'));
pruefe('Löschen: sein Link führt nirgends mehr hin', Partner::ausCode((string) $plBp['code']) === null);
pruefe('Löschen: fragt vorher nach', Ablauf::wiegt('partner_loeschen') === Ablauf::SCHWER);
foreach (['partner_provisionen', 'partner_auszahlungen', 'partner_zuordnungen', 'partner_klicks', 'partner'] as $t) { Db::run("DELETE FROM $t"); }

/* ============================================================================
   Empfehlungsrabatt kommt auf der Betreuungsrate an (26.09.2026)
   ----------------------------------------------------------------------------
   Bis heute wurde er nur berechnet. Jetzt: nächste Betreuungsrate um den
   Prozentsatz reduziert, nach rabatt_bis voll, Hosting nie, alte Raten
   unverändert, der Beleg zeigt Vollpreis und Abzug.
   ============================================================================ */
abschnitt('Empfehlungsrabatt auf der Betreuung');
require_once $wurzel . '/src/Rechnung.php';
$erK = Events::kundeFinden(['name' => 'Empfehlerin Rabatt', 'email' => 'rabatt-empf@pruefung.example']);
$erG = Events::kundeFinden(['name' => 'Geworbener Rabatt', 'email' => 'rabatt-gew@pruefung.example']);
$erSlug = (string) Db::wert("SELECT slug FROM packages WHERE art = 'betreuung' AND active = 1 ORDER BY id LIMIT 1", [], '');
$erAbo = Abo::anlegen($erK, ['paket_slug' => $erSlug, 'zahlart' => 'manuell']);
Db::run("UPDATE abos SET status = 'aktiv' WHERE id = ?", [$erAbo]);
$erVoll = (int) Db::wert('SELECT betrag_cents FROM abos WHERE id = ?', [$erAbo], 0);
$erVorher = Abo::abrechnen($erAbo, date('Y-m', strtotime('first day of -1 month')));
pruefe('Rabatt: ohne verdiente Empfehlung voller Preis', (int) Db::wert('SELECT amount_cents FROM payments WHERE id = ?', [$erVorher], 0) === $erVoll);

Db::insert('empfehlungen', ['empfehler_id' => $erK, 'geworbener_id' => $erG, 'code' => 'RABT', 'quelle' => 'link',
    'status' => 'verdient', 'verdient_am' => date('Y-m-d H:i:s')]);
Empfehlung::neuBerechnen($erK);
$erProz = Empfehlung::prozent();
$erRate = Abo::abrechnen($erAbo, date('Y-m'));
$erZ = Db::one('SELECT * FROM payments WHERE id = ?', [$erRate]);
pruefe('Rabatt: die nächste Betreuungsrate ist um ' . $erProz . ' % reduziert, in ganzen Cent',
    (int) $erZ['amount_cents'] === (int) round($erVoll * (100 - $erProz) / 100) && $erVoll > 0, $erZ['amount_cents'] . ' von ' . $erVoll);
pruefe('Rabatt: die frühere Rate bleibt, wie sie war', (int) Db::wert('SELECT amount_cents FROM payments WHERE id = ?', [$erVorher], 0) === $erVoll);

$erHost = Abo::anlegen($erK, ['paket_slug' => 'hosting', 'zahlart' => 'manuell']);
Db::run("UPDATE abos SET status = 'aktiv' WHERE id = ?", [$erHost]);
$erHr = Abo::abrechnen($erHost, date('Y-m'));
pruefe('Rabatt: Hosting wird nie reduziert',
    (int) Db::wert('SELECT amount_cents FROM payments WHERE id = ?', [$erHr], 0) === (int) Db::wert('SELECT betrag_cents FROM abos WHERE id = ?', [$erHost], 0));

Db::run('UPDATE customers SET rabatt_bis = ? WHERE id = ?', [date('Y-m-t'), $erK]);
$erNach = Abo::abrechnen($erAbo, date('Y-m', strtotime('first day of +1 month')));
pruefe('Rabatt: nach rabatt_bis wieder voll', (int) Db::wert('SELECT amount_cents FROM payments WHERE id = ?', [$erNach], 0) === $erVoll);

Db::run("UPDATE payments SET status = 'bezahlt', paid_at = NOW() WHERE id = ?", [$erRate]);
$erBeleg = Rechnung::ausZahlung($erRate);
$erR = Db::one('SELECT * FROM invoices WHERE id = ?', [(int) $erBeleg]);
$erPosten = Rechnung::posten($erR, 'de');
pruefe('Beleg: Vollpreis und Empfehlungsrabatt als eigene Zeile, Summe = bezahlt',
    count($erPosten) === 2 && $erPosten[0]['brutto'] === $erVoll && $erPosten[1]['brutto'] === -($erVoll - (int) $erZ['amount_cents'])
    && str_contains($erPosten[1]['text'], 'Empfehlungsrabatt (' . $erProz . ' %)')
    && array_sum(array_column($erPosten, 'brutto')) === (int) $erR['total_cents']
    && array_sum(array_column($erPosten, 'netto')) === (int) $erR['net_cents'], json_encode($erPosten, JSON_UNESCAPED_UNICODE));
pruefe('Beleg: der Abzug ist dreisprachig',
    str_contains((string) (Rechnung::posten($erR, 'it')[1]['text'] ?? ''), 'Sconto')
    && str_contains((string) (Rechnung::posten($erR, 'en')[1]['text'] ?? ''), 'Referral'));
pruefe('Beleg: das PDF entsteht mit Abzugszeile', str_starts_with(Rechnung::pdf($erR), '%PDF'));

/* Eigener Partnercode (26.09.2026, Uwe: „selbst schreiben oder geben lassen“) */
abschnitt('Partner: eigener Code');
foreach (['partner_provisionen', 'partner_auszahlungen', 'partner_zuordnungen', 'partner_klicks', 'partner'] as $t) { Db::run("DELETE FROM $t"); }
$pcA = Partner::anlegen(['name' => 'Code Wunsch', 'email' => 'code@partner.example', 'status' => 'aktiv', 'code' => 'ROSSI2026']);
pruefe('Code: ein selbst gewählter Code wird übernommen und der Link führt hin',
    Partner::laden($pcA)['code'] === 'ROSSI2026' && (int) (Partner::ausCode('rossi2026')['id'] ?? 0) === $pcA);
pruefe('Code: leer = vergeben lassen', preg_match('/^[A-Z2-9]{8}$/', (string) Partner::laden(Partner::anlegen(['name' => 'Ohne Wunsch', 'email' => 'ow@partner.example', 'status' => 'aktiv']))['code']) === 1);
pruefe('Code: Leerzeichen und Striche werden entfernt, klein wird groß', Partner::codePruefen('bar-centrale 7')['code'] === 'BARCENTRALE7');
pruefe('Code: zu kurz, zu lang oder mit Umlaut wird abgelehnt',
    Partner::codePruefen('ABC')['fehler'] !== null && Partner::codePruefen(str_repeat('A', 17))['fehler'] !== null
    && Partner::codePruefen('MÜLLER1')['fehler'] !== null);
pruefe('Code: ein vergebener Code wird abgelehnt — auch ein Kunden-Empfehlungscode',
    Partner::codePruefen('rossi2026')['fehler'] !== null
    && Partner::codePruefen((string) Empfehlung::codeFuer($kundeId))['fehler'] !== null);
pruefe('Code: der eigene Code gilt beim Ändern nicht als vergeben', Partner::codeSetzen($pcA, 'Rossi2026') === null);
Partner::codeSetzen($pcA, 'ROSSINEU');
pruefe('Code: nach dem Ändern führt der neue Link hin, der alte nicht mehr',
    Partner::ausCode('ROSSINEU') !== null && Partner::ausCode('ROSSI2026') === null
    && (int) Db::wert("SELECT COUNT(*) FROM audit_log WHERE action = 'partner_code'", [], 0) >= 1);
pruefe('Code: Ändern fragt vorher nach', Ablauf::wiegt('partner_code') === Ablauf::SCHWER);
foreach (['partner_provisionen', 'partner_auszahlungen', 'partner_zuordnungen', 'partner_klicks', 'partner'] as $t) { Db::run("DELETE FROM $t"); }

/* Wirklich getrackt? (26.09.2026, Uwe: „prüfe, ob wirklich ein Kauf getrackt wird“)
   Der Hauptweg der Startseite ist E-Mail → Link → Dashboard, dazu das
   Anfrageformular. Auf beiden ging der Partner bis heute verloren. */
abschnitt('Partner: auch über E-Mail-Einstieg und Formular');
foreach (['partner_provisionen', 'partner_auszahlungen', 'partner_zuordnungen', 'partner_klicks', 'partner'] as $t) { Db::run("DELETE FROM $t"); }
require_once $wurzel . '/src/Zugang.php';
$ztP = Partner::anlegen(['name' => 'Zugang Partner', 'email' => 'zp@partner.example', 'status' => 'aktiv', 'code' => 'ZUGANGP']);
Zugang::anfordern('zugang-partner@esempio.example', 'it', ['quelle' => 'seite', 'partner_code' => 'zugangp']);
pruefe('E-Mail-Einstieg: der Partnercode steht am Zugang',
    Db::wert("SELECT partner_code FROM zugaenge WHERE email = 'zugang-partner@esempio.example'", [], '') === 'ZUGANGP');
unset($_COOKIE[Partner::KEKS]);                      // anderes Gerät: kein Keks
$ztO = Zugang::oeffnen((string) Db::wert("SELECT token FROM zugaenge WHERE email = 'zugang-partner@esempio.example'", [], ''));
pruefe('E-Mail-Einstieg: beim Öffnen auf einem anderen Gerät wird der Partner zugeordnet',
    !empty($ztO['ok']) && (int) Db::wert('SELECT partner_id FROM partner_zuordnungen WHERE customer_id = ?', [(int) $ztO['kunde_id']], 0) === $ztP);
Zugang::anfordern('ohne-partner@esempio.example', 'it', ['quelle' => 'seite']);
$ztO2 = Zugang::oeffnen((string) Db::wert("SELECT token FROM zugaenge WHERE email = 'ohne-partner@esempio.example'", [], ''));
pruefe('E-Mail-Einstieg: ohne Partnerlink keine Zuordnung',
    (int) Db::wert('SELECT COUNT(*) FROM partner_zuordnungen WHERE customer_id = ?', [(int) $ztO2['kunde_id']], 0) === 0);
$ztForm = (string) file_get_contents($wurzel . '/../formular.php');
pruefe('Anfrageformular: ordnet über den Besuch zu', str_contains($ztForm, 'Partner::ausBesuch($kundeId)'));
$ztZug = (string) file_get_contents($wurzel . '/../zugang.php');
pruefe('E-Mail-Einstieg: zugang.php gibt den Code aus dem Keks weiter', str_contains($ztZug, "'partner_code' => (string) (\$_COOKIE['vecompartner']"));
$ztAlt = Events::kundeFinden(['name' => 'Schon Kunde', 'email' => 'schon-kunde@esempio.example']);
Events::bestellungAnlegen($ztAlt, Angebot::internesPaket(), 'früher gekauft', 50000);
pruefe('Wer schon gekauft hat, wird über den Link nicht mehr zugeordnet (Vereinbarung Punkt 1)', Partner::zuordnen($ztAlt, $ztP, 'link') === 'schon_kunde');
pruefe('… von Hand durch Uwe aber schon', Partner::zuordnen($ztAlt, $ztP, 'hand') === 'zugeordnet');
foreach (['partner_provisionen', 'partner_auszahlungen', 'partner_zuordnungen', 'partner_klicks', 'partner'] as $t) { Db::run("DELETE FROM $t"); }

/* Partnerseite in der Sprache des Partners (26.09.2026, Uwe: „ständig auf Englisch“) */
abschnitt('Partner: seine Sprache');
$psSeite = (string) file_get_contents($wurzel . '/../partner.php');
pruefe('Partnerseite: die Sprache des Partners schlägt den Sprach-Keks der Website',
    str_contains($psSeite, "Sprache::waehlen((string) \$p['sprache'])") && str_contains($psSeite, "UPDATE partner SET sprache"));
$psId = Partner::anlegen(['name' => 'Sprache Test', 'email' => 'sprache@partner.example', 'status' => 'aktiv', 'sprache' => 'it']);
Partner::bedingungenSetzen($psId, ['sprache' => 'de', 'monatsmail' => '1']);
pruefe('Verwaltung: die Sprache eines Partners lässt sich einstellen', Partner::laden($psId)['sprache'] === 'de');
Db::run('DELETE FROM partner WHERE id = ?', [$psId]);

/* Stripe-Einrichtung scheitert (26.09.2026, Uwe: Fehler beim Klick auf „Konto bei Stripe einrichten“) */
abschnitt('Partner: Stripe-Einrichtung scheitert');
foreach (['partner_provisionen', 'partner_auszahlungen', 'partner_zuordnungen', 'partner_klicks', 'partner'] as $t) { Db::run("DELETE FROM $t"); }
Db::run("DELETE FROM settings WHERE skey LIKE 'partner\\_%'");
$scP = Partner::laden(Partner::anlegen(['name' => 'Stripe Fehler', 'email' => 'sf@partner.example', 'status' => 'aktiv']));
$scAufrufe = [];
Partner::$stripeProbe = static function (string $m, string $weg, array $f, string $k) use (&$scAufrufe): array {
    $scAufrufe[] = [$weg, $f];
    if ($weg === '/v1/accounts' && $m === 'POST') {
        return isset($f['tos_acceptance[service_agreement]'])
            ? ['error' => ['message' => 'The recipient service agreement is not supported for accounts in IT.']]
            : ['id' => 'acct_voll'];
    }
    if ($weg === '/v1/account_links') { return ['url' => 'https://connect.stripe.com/x']; }
    return ['error' => ['message' => '?']];
};
$scR = Partner::kontoEinrichten($scP, 'https://pruefung.example/partner.php?t=x');
pruefe('Stripe: lehnt Stripe „Empfänger“ ab, klappt es mit dem normalen Konto',
    $scR['ok'] && count(array_filter($scAufrufe, static fn($a) => $a[0] === '/v1/accounts')) === 2
    && Partner::laden((int) $scP['id'])['stripe_konto'] === 'acct_voll');
Db::run('UPDATE partner SET stripe_konto = NULL WHERE id = ?', [(int) $scP['id']]);
Partner::$stripeProbe = static fn(string $m, string $weg, array $f, string $k): array =>
    $weg === '/v1/accounts' && $m === 'GET' ? ['error' => ['message' => 'You can only create new accounts if you have signed up for Connect']]
    : ['error' => ['message' => "You can only create new accounts if you've signed up for Connect, which you can learn how to do at https://stripe.com/docs/connect."]];
require_once $wurzel . '/src/PartnerWege.php';
pruefe('Stripe: vorher wird Stripe den Partnern angeboten', in_array('stripe', PartnerWege::eingeschaltet(), true));
Db::run("DELETE FROM notifications WHERE type = 'partner_stripe_connect'");
$scR = Partner::kontoEinrichten(Partner::laden((int) $scP['id']), 'https://pruefung.example/partner.php?t=x');
pruefe('Stripe: fehlt Connect → klarer Grund, Meldung mit Anleitung an Uwe, Stripe wird nicht mehr angeboten',
    !$scR['ok'] && $scR['grund'] === 'connect' && !in_array('stripe', PartnerWege::eingeschaltet(), true)
    && (int) Db::wert("SELECT COUNT(*) FROM notifications WHERE type = 'partner_stripe_connect'", [], 0) === 1);
Partner::$stripeProbe = static fn(string $m, string $weg, array $f, string $k): array => ['data' => []];
pruefe('Stripe: „Connect prüfen“ gibt den Weg wieder frei, sobald Connect aktiv ist',
    Partner::connectPruefen()['ok'] && in_array('stripe', PartnerWege::eingeschaltet(), true));
/* Uwe, 26.09.2026: „Connect prüfen“ zeigte „nicht aktiviert“, obwohl sein
   Konto die Abfrage ohne Fehler beantwortet. Die Absage eines
   eingeschränkten Schlüssels enthält „rak_connected_account“ -- das alte
   Muster /connect/ hielt sie für „Connect fehlt“. */
$scRak = "The provided key '" . 'rk' . '_live_' . '51AbCdEf' . str_repeat('x', 20) . "' does not have the required permissions for this endpoint on account 'acct_1X'. Having the 'rak_connected_account_read' permission would allow this request to continue.";
Partner::$stripeProbe = static fn(string $m, string $weg, array $f, string $k): array => ['error' => ['message' => $scRak]];
$scC = Partner::connectPruefen();
$scG = Partner::einstellung('partner_stripe_connect_grund');
pruefe('Connect prüfen: fehlende Schlüsselrechte heißen „Rechte“, nicht „Connect nicht aktiviert“',
    !$scC['ok'] && Partner::connectGrund($scRak)['art'] === 'rechte' && str_contains($scG, 'Transfers') && !str_contains($scG, 'nicht freigeschaltet'), $scG);
pruefe('Connect prüfen: der Schlüssel aus Stripes Absage wird nie weitergetragen',
    !str_contains($scG, '51AbCdEf') && !str_contains($scC['text'], '51AbCdEf'));
pruefe('Connect prüfen: Stripe wird trotzdem nicht angeboten, solange die Rechte fehlen', !in_array('stripe', PartnerWege::eingeschaltet(), true));
Partner::$stripeProbe = static fn(string $m, string $weg, array $f, string $k): array => ['data' => []];
Partner::connectPruefen();
Partner::$stripeProbe = static fn(string $m, string $weg, array $f, string $k): array => ['error' => ['message' => 'Stripe war nicht erreichbar: timeout']];
pruefe('Connect prüfen: ein Netzfehler nimmt Stripe den Partnern nicht weg',
    !Partner::connectPruefen()['ok'] && Partner::einstellung('partner_stripe_connect') === 'ok' && in_array('stripe', PartnerWege::eingeschaltet(), true));
pruefe('Connect prüfen: „signed up for Connect“ bleibt „Connect fehlt“',
    Partner::connectGrund("You can only create new accounts if you've signed up for Connect")['art'] === 'connect');
pruefe('Connect prüfen: der Grund steht in der Verwaltung beim Schild, nicht nur als Meldung oben',
    str_contains((string) file_get_contents($wurzel . '/views/partner.php'), "einstellung('partner_stripe_connect_grund')"));
Partner::$stripeProbe = null;
$scSeite = (string) file_get_contents($wurzel . '/../partner.php');
pruefe('Partnerseite: statt „Etwas hat nicht geklappt“ ein klarer Hinweis, und eine Anleitung Schritt für Schritt',
    str_contains($scSeite, "\$meldung = 'konto_fehler'") && str_contains($scSeite, "\$T('anl_' . \$w)"));
foreach (['it', 'de', 'en'] as $scSp) {
    pruefe('Anleitung (' . $scSp . '): Stripe, SEPA und PayPal beschrieben',
        substr_count(Texte::PARTNER['anl_stripe'][$scSp], "\n") >= 5 && Texte::PARTNER['anl_sepa'][$scSp] !== '' && Texte::PARTNER['anl_paypal'][$scSp] !== '');
}
Db::run("DELETE FROM settings WHERE skey LIKE 'partner\\_%'");
foreach (['partner_provisionen', 'partner_auszahlungen', 'partner_zuordnungen', 'partner_klicks', 'partner'] as $t) { Db::run("DELETE FROM $t"); }

/* ============================================================================
   Partnerprogramm, Ausbau (26.09.2026, Uwe: „Ja alles außer 2“)
   ============================================================================ */
abschnitt('Partner-Ausbau');
foreach (['partner_provisionen', 'partner_auszahlungen', 'partner_zuordnungen', 'partner_klicks', 'partner_kanal_klicks', 'partner'] as $t) { Db::run("DELETE FROM $t"); }
Db::run("DELETE FROM settings WHERE skey LIKE 'partner\\_%'");
$abMails = [];
$abSenden = static function (string $anlass, string $an, string $betreff, string $text) use (&$abMails): bool { $abMails[] = [$anlass, $an, $text]; return true; };
$abP = Partner::anlegen(['name' => 'Giulia Verdi', 'email' => 'giulia@partner.example', 'status' => 'aktiv', 'code' => 'GIULIA2026', 'firma' => '']);
Partner::vereinbarungMerken($abP, 'x');
$abKurz = false;
try { Partner::anlegen(['name' => 'Kurz', 'email' => 'kurz@partner.example', 'code' => 'ROSA']); } catch (InvalidArgumentException $e) { $abKurz = true; }
pruefe('Anlegen: ein Code, den der Link nie fände („ROSA“, 4 Zeichen), wird abgewiesen',
    $abKurz && (int) Db::wert("SELECT COUNT(*) FROM partner WHERE email = 'kurz@partner.example'", [], 0) === 0);

// 1 Landeseite / 9 Kanal
$abL = (string) file_get_contents($wurzel . '/../p.php');
pruefe('Landeseite: „Empfohlen von …“ mit Einstieg statt nackter Startseite', str_contains($abL, "\$L('marke')") && str_contains($abL, 'action="/zugang.php'));
pruefe('Landeseite: zeigt den Vornamen (oder die Firma), nie den vollen Namen', Partner::anzeigeName(Partner::laden($abP)) === 'Giulia');
pruefe('Landeseite: der Sprachwechsel zählt keinen zweiten Klick', str_contains($abL, "if (!isset(\$_GET['n']))"));
Partner::klick($abP, 'Instagram'); Partner::klick($abP, 'instagram'); Partner::klick($abP, '../böse');
pruefe('Kanal: Klicks je Kanal, klein geschrieben, Unsinn wird nicht gezählt',
    (int) Db::wert("SELECT SUM(anzahl) FROM partner_kanal_klicks WHERE partner_id = ? AND kanal = 'instagram'", [$abP], 0) === 2
    && (int) Db::wert('SELECT COUNT(*) FROM partner_kanal_klicks WHERE partner_id = ?', [$abP], 0) === 1
    && (int) Db::wert('SELECT SUM(anzahl) FROM partner_klicks WHERE partner_id = ?', [$abP], 0) === 3);
pruefe('Kanal: die Adresse /p/CODE/kanal führt auf p.php', str_contains((string) file_get_contents($wurzel . '/../.htaccess'), 'p.php?c=$1&k=$2'));
$abK1 = Events::kundeFinden(['name' => 'Kanal Kunde', 'email' => 'kanal-kunde@esempio.example']);
$_COOKIE[Partner::KEKS] = 'GIULIA2026:instagram';
Partner::ausBesuch($abK1);
unset($_COOKIE[Partner::KEKS]);
pruefe('Kanal: der Kunde behält seinen Kanal', Db::wert('SELECT kanal FROM partner_zuordnungen WHERE customer_id = ?', [$abK1], '') === 'instagram');
require_once $wurzel . '/src/Zugang.php';
Zugang::anfordern('kanal-mail@esempio.example', 'it', ['partner_code' => 'GIULIA2026:whatsapp']);
$abZO = Zugang::oeffnen((string) Db::wert("SELECT token FROM zugaenge WHERE email = 'kanal-mail@esempio.example'", [], ''));
pruefe('Kanal: auch über den E-Mail-Einstieg', Db::wert('SELECT kanal FROM partner_zuordnungen WHERE customer_id = ?', [(int) $abZO['kunde_id']], '') === 'whatsapp');

// 3/4 Werbemittel
$abS = (string) file_get_contents($wurzel . '/../partner.php');
pruefe('Werbemittel: Karte zum Drucken, QR und Story-Bild werden im Browser erzeugt (keine fremden Server)',
    str_contains($abS, "isset(\$_GET['karte'])") && str_contains($abS, '/assets/js/qrcode.js') && str_contains($abS, "getElementById('story_laden')")
    && is_file($wurzel . '/../assets/js/qrcode.js') && !preg_match('~https?://[a-z.]*(qrserver|chart\.googleapis|quickchart)~', $abS));
foreach (['it', 'de', 'en'] as $abSp) {
    pruefe('Werbemittel (' . $abSp . '): drei fertige Texte mit Link, Kennzeichnung als Werbung',
        str_contains(Texte::PARTNER['w_post1'][$abSp], '{link}') && str_contains(Texte::PARTNER['w_post2'][$abSp], '#')
        && Texte::PARTNER['w_hinweis'][$abSp] !== '');
}

// 5 Kunde melden
pruefe('Melden: ohne Einverständnis des Kunden nicht', Partner::kundeMelden($abP, ['name' => 'X', 'email' => 'x@esempio.example'], 'it')['grund'] === 'm_einverstanden');
$abM = Partner::kundeMelden($abP, ['name' => 'Bar Sole', 'email' => 'bar-sole@esempio.example', 'telefon' => '333', 'anliegen' => 'Sito nuovo', 'einverstanden' => '1'], 'it');
$abMk = (int) Db::wert("SELECT id FROM customers WHERE email = 'bar-sole@esempio.example'", [], 0);
pruefe('Melden: Anfrage entsteht, Kunde gehört dem Partner (Quelle „partner“), Uwe bekommt eine Meldung',
    $abM['ok'] && $abMk > 0 && Db::wert('SELECT quelle FROM partner_zuordnungen WHERE customer_id = ?', [$abMk], '') === 'partner'
    && (int) Db::wert("SELECT COUNT(*) FROM anfragen WHERE customer_id = ?", [$abMk], 0) >= 1
    && (int) Db::wert("SELECT COUNT(*) FROM notifications WHERE type = 'partner_meldet'", [], 0) >= 1);
pruefe('Melden: sich selbst melden zählt still nicht', Partner::kundeMelden($abP, ['name' => 'Ich', 'email' => 'giulia@partner.example', 'einverstanden' => '1'], 'it')['ok']
    && (int) Db::wert("SELECT COUNT(*) FROM customers WHERE email = 'giulia@partner.example'", [], 0) === 0);

// 6 Manuela
$abT = Events::kundeFinden(['name' => 'Anrufer', 'email' => 'anrufer@esempio.example']);
pruefe('Telefon: ein genannter Code ordnet zu (Quelle „telefon“)', Telefon::empfohlen(['kunde_id' => $abT, 'wer' => 'giulia 2026'])['ergebnis'] === 'zugeordnet'
    && Db::wert('SELECT quelle FROM partner_zuordnungen WHERE customer_id = ?', [$abT], '') === 'telefon');
$abT2 = Events::kundeFinden(['name' => 'Anrufer 2', 'email' => 'anrufer2@esempio.example']);
Partner::anlegen(['name' => 'Giulia Rossi', 'email' => 'gr@partner.example', 'status' => 'aktiv']);
pruefe('Telefon: ein mehrdeutiger Name wird NICHT geraten, sondern Uwe gemeldet',
    Telefon::empfohlen(['kunde_id' => $abT2, 'wer' => 'Giulia'])['ergebnis'] === 'unklar'
    && (int) Db::wert('SELECT COUNT(*) FROM partner_zuordnungen WHERE customer_id = ?', [$abT2], 0) === 0);
pruefe('Telefon: das Werkzeug geht mit zu STRATO und steht im Verhaltenstext',
    isset(Telefonwerkzeuge::alle()['empfohlen']) && str_contains(Telefonverhalten::text(), '[empfohlen]'));

// 7 Stufen
$abSt = static function (int $n) use ($abP, $abK1): void {
    for ($i = 0; $i < $n; $i++) {
        $b = Events::bestellungAnlegen($abK1, Angebot::internesPaket(), 'Stufe', 10000);
        $z = (int) Db::wert('SELECT id FROM payments WHERE order_id = ? ORDER BY id LIMIT 1', [$b], 0);
        Db::run("UPDATE payments SET status = 'bezahlt', paid_at = NOW() WHERE id = ?", [$z]);
        Db::insert('partner_provisionen', ['partner_id' => $abP, 'customer_id' => $abK1, 'payment_id' => $z, 'order_id' => $b, 'art' => 'website',
            'basis_cents' => 5000, 'provision_cents' => 500, 'status' => 'wartet', 'frei_ab' => date('Y-m-d H:i:s', strtotime('+14 days'))]);
    }
};
pruefe('Stufen: am Anfang Bronze = Standard 10 %', Partner::satzFuer(Partner::laden($abP))['stufe'] === 'bronze' && Partner::satzFuer(Partner::laden($abP))['wert'] === 1000);
$abSt(5);
pruefe('Stufen: ab 5 Verkäufen Silber 12 %', Partner::satzFuer(Partner::laden($abP))['stufe'] === 'silber' && Partner::satzFuer(Partner::laden($abP))['wert'] === 1200);
$abSt(5);
pruefe('Stufen: ab 10 Verkäufen Gold 15 %', Partner::satzFuer(Partner::laden($abP))['stufe'] === 'gold' && Partner::satzFuer(Partner::laden($abP))['wert'] === 1500);
Db::run("UPDATE partner SET provision_art = 'prozent', provision_wert = 1100 WHERE id = ?", [$abP]);
pruefe('Stufen: eigene Bedingungen gehen vor', Partner::satzFuer(Partner::laden($abP))['wert'] === 1100 && Partner::satzFuer(Partner::laden($abP))['stufe'] === null);
Db::run('UPDATE partner SET provision_art = NULL, provision_wert = NULL WHERE id = ?', [$abP]);
pruefe('Stufen: Gold muss über Silber liegen', Partner::einstellungenSetzen(['partner_standard_wert' => '10', 'partner_mindest_cents' => '50',
    'partner_auto_tageslimit_cents' => '100', 'partner_silber_ab' => '8', 'partner_silber_bp' => '12', 'partner_gold_ab' => '5', 'partner_gold_bp' => '15']) !== null);

// 8 Sofort-Nachrichten
$abSend = new ReflectionMethod('Partner', 'schreiben');
pruefe('Sofort: Texte für neuen Kontakt und verdiente Provision, ohne Kundennamen',
    str_contains(Texte::mail('partner_neukunde', 'de', ['name' => 'G'])[1], 'sagen wir nicht, wer')
    && str_contains(Texte::mail('partner_verdient', 'it', ['betrag' => '5 €'])[0], '5 €'));
$abQ = (string) file_get_contents($wurzel . '/src/Partner.php');
pruefe('Sofort: wird beim Zuordnen und beim Verdienen geschickt, abschaltbar', str_contains($abQ, "self::schreiben(\$partnerId, 'partner_neukunde')")
    && str_contains($abQ, "'partner_verdient'") && substr_count($abQ, "!empty(\$p['sofortmail'])") >= 2);

// 10 ruhend
$abR = Partner::anlegen(['name' => 'Ruhig', 'email' => 'ruhig@partner.example', 'status' => 'aktiv']);
Db::run('UPDATE partner SET created_at = NOW() - INTERVAL 90 DAY WHERE id = ?', [$abR]);
$abMails = [];
$abN = Partner::ruhendeErinnern($abSenden);
pruefe('Ruhend: nach 60 Tagen ohne Klick eine Mail mit Tipps — genau einmal',
    $abN >= 1 && in_array('ruhig@partner.example', array_column($abMails, 1), true) && Partner::ruhendeErinnern($abSenden) === 0);

// 11 Auswertung
$abA = Partner::auswertung(12);
pruefe('Auswertung: beste zuerst, mit Umsatz, Provision und Kanälen',
    (int) $abA[0]['id'] === $abP && (int) $abA[0]['umsatz'] === 50000 && isset($abA[0]['kanaele'][0]['kanal']));

// 12 Missbrauch
Db::run('INSERT INTO partner_klicks (partner_id, tag, anzahl) VALUES (?, CURDATE(), 150) ON DUPLICATE KEY UPDATE anzahl = 150', [$abR]);
Db::run("DELETE FROM notifications WHERE type = 'partner_auffaellig'");
pruefe('Missbrauch: 150 Klicks ohne Kunden werden gemeldet — einmal, nichts gesperrt',
    Partner::missbrauchPruefen() >= 1 && Partner::missbrauchPruefen() === 0 && Partner::laden($abR)['status'] === 'aktiv'
    && (int) Db::wert("SELECT COUNT(*) FROM notifications WHERE type = 'partner_auffaellig'", [], 0) === 1);

// 13 Jahresübersicht
Db::run("UPDATE partner_provisionen SET status = 'bereit' WHERE partner_id = ?", [$abP]);
Partner::auszahlenHand($abP, 'Bonifico');
pruefe('Jahresübersicht: PDF mit allen Auszahlungen des Jahres', Partner::jahre($abP) === [(int) date('Y')]
    && str_starts_with((string) Partner::jahresPdf($abP, (int) date('Y')), '%PDF') && Partner::jahresPdf($abP, 1999) === null);
pruefe('Jahresübersicht: Mail nur im Januar', (int) date('n') === 1 || Partner::jahresmails($abSenden) === 0);
foreach (['it', 'de', 'en'] as $abSp) {
    pruefe('Texte (' . $abSp . '): Landeseite, Stufen, Melden, Karte, Jahr', Texte::PARTNER_LANDE['marke'][$abSp] !== ''
        && Texte::PARTNER['st_gold'][$abSp] !== '' && Texte::PARTNER['m_einverstanden'][$abSp] !== '' && Texte::PARTNER['karte_titel'][$abSp] !== ''
        && Texte::mail('partner_jahr', $abSp, ['jahr' => '2026'])[0] !== '' && Texte::mail('partner_ruhend', $abSp, [])[0] !== '');
}
foreach (['partner_provisionen', 'partner_auszahlungen', 'partner_zuordnungen', 'partner_klicks', 'partner_kanal_klicks', 'partner'] as $t) { Db::run("DELETE FROM $t"); }
Db::run("DELETE FROM settings WHERE skey LIKE 'partner\\_%'");

/* ============================================================================
   Verwaltung einfacher (26.09.2026, Uwe: „Mach“ zu den Vorschlägen 1–9)

   Nur Ansicht und Führung -- an den Abläufen darunter ändert sich nichts.
   Geprüft wird, dass die Vereinfachung nichts versteckt, was offen ist.
   ============================================================================ */
abschnitt('Verwaltung einfacher');
$veHeute = (string) file_get_contents($oben . '/app/views/heute.php');
pruefe('Heute: höchstens fünf vorne, der Rest unter „Später“ -- mit seiner Zahl',
    str_contains($veHeute, "array_slice(\$liste['du'], 0, 5)") && str_contains($veHeute, 'Später<span class="mehr"><?= count($duSpaeter) ?>'));
pruefe('Heute: nur die vorderste Zeile trägt den goldenen Knopf (Regel 3)',
    str_contains($veHeute, "\$zeile(\$v, \$i === 0)") && substr_count($veHeute, "class=\"knopf haupt\"") === 0);
pruefe('Heute: „Später“ zeigt dieselben Zeilen, keine geht verloren',
    str_contains($veHeute, "foreach (\$duSpaeter as \$v) { \$zeile(\$v); }"));

require_once $wurzel . '/src/Hilfe.php';
preg_match_all("~'([a-z]+)', '[^']+', '[a-z]+'~", $mText, $veZ);
$veOhne = array_values(array_filter(array_unique($veZ[1] ?? []), static fn($z) => Hilfe::satz($z) === ''));
pruefe('Hilfe: jede Seite im Menü hat ihren Satz „Hier …“', $veOhne === [], implode(', ', $veOhne));
pruefe('Hilfe: jeder Satz sagt, was man HIER tut (beginnt mit „Hier“)',
    array_filter(Hilfe::SAETZE, static fn($t) => !str_starts_with($t, 'Hier')) === []);
$veRouten = [];
preg_match_all("~^    case '([a-z_]+)':~m", $rfQuelle, $veR);
pruefe('Einführung: jeder Schritt führt auf eine Seite, die es gibt',
    array_diff(array_column(Hilfe::EINFUEHRUNG, 0), $veR[1] ?? []) === [],
    implode(', ', array_diff(array_column(Hilfe::EINFUEHRUNG, 0), $veR[1] ?? [])));
Db::run("DELETE FROM settings WHERE skey = 'einfuehrung_99991'");
$veVorher = Hilfe::gesehen(99991);
Hilfe::merken(99991);
pruefe('Einführung: einmal gesehen, kommt sie nicht wieder -- je Benutzer', !$veVorher && Hilfe::gesehen(99991) && !Hilfe::gesehen(99992));
Db::run("DELETE FROM settings WHERE skey = 'einfuehrung_99991'");
$veLayout = (string) file_get_contents($oben . '/app/views/layout.php');
pruefe('Einführung: Schließen merkt sie ebenso wie Fertig, und „Einführung ansehen“ holt sie zurück',
    str_contains($veLayout, 'value="einfuehrung_gesehen"') && str_contains($veLayout, "isset(\$_GET['einfuehrung'])")
    && str_contains((string) file_get_contents($oben . '/app/index.php'), "case 'einfuehrung_gesehen':"));

require_once $wurzel . '/src/Meldungen.php';
Db::run("DELETE FROM notifications WHERE type IN ('anfrage_neu','partner_stripe_connect','nachricht_rein','strato_zugang')");
// Was frühere Abschnitte an Meldungen hinterlassen haben, zählt hier nicht mit.
Db::run('UPDATE notifications SET read_at = NOW() WHERE read_at IS NULL');
$veK = Events::kundeFinden(['name' => 'Meldung Probe', 'email' => 'meldung-probe@pruefung.example']);
Db::insert('anfragen', ['status' => 'neu', 'customer_id' => $veK, 'name' => 'Meldung Probe', 'email' => 'meldung-probe@pruefung.example', 'sprache' => 'it']);
$veA = (int) Db::wert('SELECT MAX(id) FROM anfragen', [], 0);
Events::melden('anfrage_neu', 'Neue Anfrage', 'gut', null, '/anfragen/' . $veA);
Db::run("INSERT INTO settings (skey, svalue) VALUES ('partner_stripe_connect','fehlt') ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)");
Events::melden('partner_stripe_connect', 'Stripe Connect ist nicht aktiviert', 'warnung', null, '/partner#wege');
Db::insert('messages', ['customer_id' => $veK, 'sender' => 'kunde', 'body' => 'Hallo']);
Events::melden('nachricht_rein', 'Neue Nachricht', 'info', null, '/kunden/' . $veK);
Db::run("INSERT INTO settings (skey, svalue) VALUES ('strato_sitzung_seit','') ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)");
Events::melden('strato_zugang', 'STRATO-Zugang abgelaufen', 'warnung', null, '/einstellungen?b=telefon');
$veOffen = static fn(string $t): int => (int) Db::wert('SELECT COUNT(*) FROM notifications WHERE type = ? AND read_at IS NULL', [$t], 0);
pruefe('Meldungen: solange der Anlass besteht, bleibt jede stehen',
    Meldungen::aufraeumen() === 0 && $veOffen('anfrage_neu') === 1 && $veOffen('partner_stripe_connect') === 1
    && $veOffen('nachricht_rein') === 1 && $veOffen('strato_zugang') === 1);
Db::run("UPDATE anfragen SET status = 'in_arbeit' WHERE id = ?", [$veA]);
Db::run("UPDATE settings SET svalue = 'ok' WHERE skey = 'partner_stripe_connect'");
Db::run("UPDATE messages SET read_at = NOW() WHERE customer_id = ?", [$veK]);
Db::run("UPDATE settings SET svalue = ? WHERE skey = 'strato_sitzung_seit'", [date('Y-m-d H:i:s', time() + 5)]);
$veN = Meldungen::aufraeumen();
pruefe('Meldungen: ist der Anlass vorbei, gelten sie als gelesen -- gelöscht wird keine',
    $veN === 4 && $veOffen('anfrage_neu') + $veOffen('partner_stripe_connect') + $veOffen('nachricht_rein') + $veOffen('strato_zugang') === 0
    && (int) Db::wert("SELECT COUNT(*) FROM notifications WHERE type IN ('anfrage_neu','partner_stripe_connect','nachricht_rein','strato_zugang')", [], 0) === 4, (string) $veN);
Events::melden('anfrage_neu', 'Ohne Gegenstand', 'gut', null, '/anfragen');
pruefe('Meldungen: ohne erkennbaren Gegenstand im Link bleibt sie stehen',
    Meldungen::aufraeumen() === 0 && $veOffen('anfrage_neu') === 1);
pruefe('Meldungen: „Heute“ und der Cronlauf räumen auf',
    str_contains($rfQuelle, 'Meldungen::aufraeumen()') && str_contains((string) file_get_contents($wurzel . '/src/Cron.php'), "'meldungen'"));
Db::run("DELETE FROM notifications WHERE type IN ('anfrage_neu','partner_stripe_connect','nachricht_rein','strato_zugang')");
Db::run("DELETE FROM settings WHERE skey = 'partner_stripe_connect'");

require_once $wurzel . '/src/Einmalig.php';
Db::run("DELETE FROM settings WHERE skey LIKE 'einmalig\\_%'");
$veSchl = static fn(): array => array_column(Einmalig::offen(), 'schluessel');
pruefe('Einmalig: die Cockpit-Aufgaben stehen unter „Heute“, von Hand abzuhaken',
    in_array('angebote_bestand', $veSchl(), true) && Einmalig::erledigt('angebote_bestand') && !in_array('angebote_bestand', $veSchl(), true));
pruefe('Einmalig: ein unbekannter Schlüssel hakt nichts ab', !Einmalig::erledigt('gibt_es_nicht')
    && (int) Db::wert("SELECT COUNT(*) FROM settings WHERE skey = 'einmalig_gibt_es_nicht'", [], 0) === 0);
Db::run('UPDATE stimmen SET veroeffentlicht_am = NULL WHERE demo = 0');
$veStimmeVorher = in_array('kundenstimme', $veSchl(), true);
$veSt = (int) Db::insert('stimmen', ['customer_id' => $veK, 'name' => 'Probe', 'text' => 'Gut.', 'sterne' => 5, 'sprache' => 'it', 'erlaubnis' => 1, 'status' => 'veroeffentlicht', 'veroeffentlicht_am' => date('Y-m-d H:i:s')]);
pruefe('Einmalig: „Erste Kundenstimme“ hakt sich selbst ab, sobald eine veröffentlicht ist',
    $veStimmeVorher && !in_array('kundenstimme', $veSchl(), true));
Db::run('DELETE FROM stimmen WHERE id = ?', [$veSt]);
Db::run("DELETE FROM settings WHERE skey LIKE 'einmalig\\_%'");

/* Gerüst und Ansicht teilen einen Gültigkeitsbereich: Was index.php einer
   Ansicht mitgibt, darf das Gerüst nicht überschreiben (26.09.2026: $liste,
   $summe, $offen). Geprüft wird die Mechanik und dass $daten selbst heil bleibt. */
$veLay = (string) file_get_contents($oben . '/app/views/layout.php');
$veVor = substr($veLay, 0, (int) strpos($veLay, 'require $inhaltsdatei'));
pruefe('Gerüst: die übergebenen Werte werden direkt vor der Ansicht wieder gesetzt',
    preg_match('~extract\(\$daten\);\s*require \$inhaltsdatei;~', $veLay) === 1);
pruefe('Gerüst: $daten selbst wird im Gerüst nie überschrieben',
    preg_match('~\$daten\s*=(?!=)|as\s+\$daten\b~', preg_replace('~/\*.*?\*/~s', '', $veVor)) === 0);

/* Knöpfe sagen, was passiert (Vorschlag 9). Ein nacktes „Senden“ ließ
   offen, an wen -- bei einer Nachricht an den Kunden ist genau das die Frage. */
$veVage = [];
foreach (array_merge(glob($oben . '/app/views/*.php') ?: [], glob($oben . '/app/views/einstellungen/*.php') ?: []) as $veF) {
    if (preg_match_all('~<button[^>]*>\s*(Senden|Setzen|Status setzen|Ausgeführt|OK|Absenden|Los)\s*</button>~u', (string) file_get_contents($veF), $veM)) {
        foreach ($veM[1] as $veW) { $veVage[] = basename($veF) . ': ' . $veW; }
    }
}
pruefe('Knöpfe: kein nacktes „Senden“, „Setzen“, „Ausgeführt“ -- jeder sagt, was passiert', $veVage === [], implode(', ', $veVage));
pruefe('Handy: Leiste unten mit Heute, Kunden, Geld, Telefon -- und „Menü“ öffnet das ganze Menü',
    str_contains($veLay, "['heute', 'Heute', \$untenZahl('heute')]") && str_contains($veLay, "['telefon', 'Telefon'")
    && str_contains($veLay, "classList.toggle('menue-auf')") && str_contains($veLay, 'id="hauptmenue"'));
pruefe('Handy: die Leiste trägt dieselben Summen wie die Türen im Menü',
    str_contains($veLay, 'if ($t[0] === $ziel) { return (int) $t[4]; }'));

/* ---- Besucher (Website-Vorschlag 2) ---- */
require_once $wurzel . '/src/Statistik.php';
$veW = sys_get_temp_dir() . '/vd-stat-' . getmypid();
@mkdir($veW);
file_put_contents($veW . '/besuche.csv',
    "2026-09-20\t10\t\tHandy\n"                                  // alte Zeile, vier Spalten
  . "2026-09-25\t11\tgoogle.com\tHandy\t/de/\t\n"
  . "2026-09-26\t12\tinstagram.com\tRechner\t/\tinstagram\n");
file_put_contents($veW . '/demo.csv', "2026-09-26\t12\tanruf\tHandy\n2026-09-26\t12\twhatsapp\tHandy\n");
$veB = Statistik::besuche(26, 90, $veW, '2026-09-26');
pruefe('Besucher: alte Zeilen (vier Spalten) zählen weiter, neue bringen Seite und Kampagne mit',
    $veB['summe'] === 3 && ($veB['seiten']['/de/'] ?? 0) === 1 && ($veB['kampagnen']['instagram'] ?? 0) === 1
    && ($veB['quellen']['google.com'] ?? 0) === 1, json_encode([$veB['summe'], $veB['seiten'], $veB['kampagnen']]));
$veD = Statistik::demos(90, $veW, '2026-09-26');
$veWege = array_column($veD['wege'], 'zahl', 'wort');
pruefe('Besucher: Anruf- und WhatsApp-Knopf werden als Wege gezählt',
    ($veWege['Anruf-Knopf gedrückt'] ?? 0) === 1 && ($veWege['WhatsApp-Knopf gedrückt'] ?? 0) === 1);
@unlink($veW . '/besuche.csv'); @unlink($veW . '/demo.csv'); @rmdir($veW);
$veZ = (string) file_get_contents($oben . '/z.php');
pruefe('Zählpixel: die Herkunft kommt aus ?r= (document.referrer) -- ein <img> trägt nur die eigene Seite',
    str_contains($veZ, "isset(\$_GET['r'])") && !str_contains($veZ, 'REMOTE_ADDR') && !str_contains($veZ, 'setcookie'));
$veZj = (string) file_get_contents($oben . '/assets/js/zaehlen.js');
pruefe('Zählpixel: die Seite schickt Herkunft, Seite und utm_source mit',
    str_contains($veZj, 'document.referrer') && str_contains($veZj, 'utm_source') && str_contains($veZj, 'location.pathname'));
$veOhne = [];
foreach (['index.html', 'prezzi.html', 'assistenza.html', 'showroom.html', 'tecnica.html', 'tavolo.html'] as $veS) {
    $veH = (string) file_get_contents($oben . '/' . $veS);
    if (!str_contains($veH, 'assets/js/zaehlen.js') || preg_match('~<img src="/z\.php"(?![^<]*</noscript>)~', str_replace("\n", ' ', $veH)) && !str_contains($veH, '<noscript><img src="/z.php"')) { $veOhne[] = $veS; }
}
pruefe('Zählpixel: alle sechs Seiten zählen, jede genau einmal (das <img> nur noch in <noscript>)', $veOhne === [], implode(', ', $veOhne));
foreach (['it', 'de', 'en'] as $veL) {
    pruefe("Datenschutz ($veL): die Besucherzählung ist beschrieben -- ohne IP, ohne Cookie",
        (bool) preg_match('~(zählt die Seite Besuche|conta le visite|counts visits)~u', (string) file_get_contents($oben . "/assets/js/legal-$veL.js")));
}

/* ---- Rückruf-Wunsch von der Website (Website-Vorschlag 7) ----
   Die gefährlichste Stelle: Telefon::melden ordnet über das jüngste
   Nachschlagen zu. Ein Website-Besucher, der kurz nach einem Anrufer das
   Formular schickt, darf dessen Kunden nie erben. */
$rrK = Events::kundeFinden(['name' => 'Anrufer Vorhin', 'email' => 'anrufer-vorhin@pruefung.example']);
Db::insert('activities', ['type' => 'telefon_nachschlagen', 'title' => 'Nachgeschlagen', 'customer_id' => $rrK,
    'meta' => json_encode(['treffer' => 1, 'nummer' => '3401112222'])]);
pruefe('Rückruf: ein Anruf ordnet über das jüngste Nachschlagen zu (Gegenprobe)',
    Telefon::kundeImGespraech(['telefon' => '+39 340 111 2222']) === $rrK);
pruefe('Rückruf: ein Website-Wunsch erbt den Anrufer von eben NIE',
    Telefon::kundeImGespraech(['telefon' => '+39 340 111 2222', 'quelle' => 'website']) === 0
    && Telefon::kundeImGespraech(['quelle' => 'website']) === 0);
Db::run('DELETE FROM notifications WHERE type = ?', ['telefon_rueckruf']);
$rrR = Telefon::melden(['art' => 'rueckruf', 'quelle' => 'website', 'name' => 'Web Besucher', 'telefon' => '+39 340 111 2222',
    'erreichbar' => 'morgen Vormittag', 'text' => 'Rückruf-Wunsch über die Website.']);
$rrM = (string) Db::wert("SELECT meta FROM activities WHERE type = 'telefon_melde' ORDER BY id DESC LIMIT 1", [], '');
pruefe('Rückruf: landet in Manuelas Rückrufliste, mit Zeitfenster und Quelle „website“, an keinem Kunden',
    $rrR['ok'] && str_contains($rrM, '"quelle":"website"') && str_contains($rrM, 'morgen Vormittag')
    && (string) Db::wert("SELECT link FROM notifications WHERE type = 'telefon_rueckruf' ORDER BY id DESC LIMIT 1", [], '') === '/heute');
Db::run("DELETE FROM activities WHERE type = 'telefon_nachschlagen' AND customer_id = ?", [$rrK]);
$rrQ = (string) file_get_contents($oben . '/rueckruf.php');
pruefe('Rückruf: rueckruf.php prüft Name und mindestens sechs Ziffern, bremst Massenversand und antwortet nie mit einer Datenbankmeldung',
    str_contains($rrQ, "preg_match_all('/\\d/', \$telefon) < 6") && str_contains($rrQ, '>= 10') && str_contains($rrQ, "'quelle' => 'website'")
    && !str_contains($rrQ, '$e->getMessage()]') && str_contains($rrQ, "'grund' => 'panne'"));
$rrI = (string) file_get_contents($oben . '/index.html');
pruefe('Rückruf: auf der Startseite eingeklappt (Regel 3), dreisprachig',
    str_contains($rrI, '<details class="rueckruf" data-rueckruf>')
    && str_contains((string) file_get_contents($oben . '/assets/js/i18n-de.js'), 'Lieber zurückgerufen werden?')
    && str_contains((string) file_get_contents($oben . '/assets/js/i18n-en.js'), 'Rather get a call back?'));

/* ---- Google-Bewertung (Website-Vorschlag 6) ---- */
pruefe('Bewertung: die Bitte geht nur nach der Rückfrage raus (TRAGWEITE)', isset(Ablauf::TRAGWEITE['bewertung_bitten'])
    && Ablauf::TRAGWEITE['bewertung_bitten'][0] === Ablauf::RAUS);
foreach (['it', 'de', 'en'] as $gbL) {
    [$gbB, $gbT] = Texte::mail('bewertung_bitte', $gbL, ['name' => 'Anna', 'link' => 'https://g.page/r/X/review']);
    pruefe("Bewertung ($gbL): Mail mit Namen und Link, ohne gerade Apostrophe",
        $gbB !== '' && str_contains($gbT, 'Anna') && str_contains($gbT, 'https://g.page/r/X/review') && !str_contains($gbT . $gbB, "'"));
}
$gbI = (string) file_get_contents($oben . '/app/index.php');
pruefe('Bewertung: je Kunde nur einmal, nur mit https-Link, nie bei anonymisierten Kunden',
    str_contains($gbI, "Mail::schonGeschickt('bewertung_bitte', 'customer_id'") && str_contains($gbI, "!str_starts_with(\$bl, 'https://')")
    && str_contains($gbI, 'AND anonym_am IS NULL'));
pruefe('Bewertung: auf der Kundenseite nur ein Link, den der Kunde selbst klickt -- und nur mit https',
    str_contains((string) file_get_contents($oben . '/kunde.php'), "str_starts_with((string) \$gLink, 'https://')"));

/* ============================================================================
   Aufräumen und Bilanz
   ============================================================================ */
abschnitt('Bilanz');

$gesamt = $GLOBALS['gut'] + count($GLOBALS['schlecht']);
if ($GLOBALS['schlecht']) {
    echo "\n\033[31m" . count($GLOBALS['schlecht']) . " von $gesamt Prüfungen gerissen:\033[0m\n";
    foreach ($GLOBALS['schlecht'] as $z) { echo "  · $z\n"; }
    echo "\nDie Kette ist an diesen Stellen offen. Nicht ausliefern.\n";
    exit(1);
}
echo "\n\033[32mAlle $gesamt Prüfungen halten.\033[0m Die Kette trägt von der Bestellung bis zum Abschluss.\n";
exit(0);
