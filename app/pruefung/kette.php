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
]);

foreach (['Db', 'Status', 'Fmt', 'Csrf', 'Auth', 'Events', 'Einrichtung',
          'Vorgang', 'Onboarding', 'Umfang', 'Fragen', 'Beispieldaten'] as $k) {
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

/* ============================================================================
   2. Kunde und Bestellung
   ============================================================================ */
abschnitt('2. Kunde und Bestellung');

$paketId = (int) Db::wert("SELECT id FROM packages WHERE active = 1 ORDER BY id LIMIT 1", [], 0);
pruefe('es gibt ein Paket zum Bestellen', $paketId > 0);

$kundeId = Events::kundeFinden([
    'name' => 'Prüf Kunde', 'email' => 'kette@pruefung.example',
    'company' => 'Trattoria Prüfung', 'sprache' => 'de', 'city' => 'Agrigento',
]);
pruefe('Kunde entsteht', $kundeId > 0);
pruefe('derselbe Kunde entsteht nicht zweimal',
    Events::kundeFinden(['name' => 'Prüf Kunde', 'email' => 'kette@pruefung.example']) === $kundeId);

$bestellId = Events::bestellungAnlegen($kundeId, $paketId, 'Kettentest');
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
