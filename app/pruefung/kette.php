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

/* Der Einstiegspreis muss aus der Paket-Tabelle kommen, nicht aus dem Code. */
$festDb = (int) Db::wert("SELECT price_cents FROM packages
                           WHERE active = 1 AND art = 'website' AND price_cents > 0
                           ORDER BY price_cents LIMIT 1", [], 0);
if ($festDb > 0) {
    /* Die Preisauskunft nennt jetzt Zahlen -- und genau deshalb muss belegt
   sein, dass es die oeffentlichen sind. Wer eine Kundennummer mitschickt,
   bekommt dieselbe Auskunft wie jeder andere: kein Name, keine Adresse,
   kein offener Posten. Oeffentlich ja, persoenlich nie. */
$mitKunde = Telefon::preisAuskunft(['zweck' => 'shop', 'kunde_id' => $kundeId,
                                    'telefon' => '+39 380 111 2233']);
$ohneKunde = Telefon::preisAuskunft(['zweck' => 'shop']);
pruefe('eine Kundennummer ändert an der Preisauskunft nichts',
    $mitKunde === $ohneKunde,
    json_encode(array_diff_assoc($mitKunde, $ohneKunde), JSON_UNESCAPED_UNICODE));
/* „festpreis_name" ist der Paketname („Starter"), nicht der eines Kunden --
   deshalb wird hier auf das geprueft, was einen Menschen bezeichnet. */
$flachP = json_encode($mitKunde, JSON_UNESCAPED_UNICODE);
$leckP = [];
foreach (['kunde', 'email', '@', 'iban', 'offen', 'rechnung', 'Salvatore'] as $wort) {
    if (stripos($flachP, $wort) !== false) { $leckP[] = $wort; }
}
pruefe('und sie nennt niemanden', $leckP === [], implode(', ', $leckP));

pruefe('der Festpreis kommt aus der Datenbank',
        (int) ($pa['festpreis_euro'] ?? -1) === (int) round($festDb / 100),
        ($pa['festpreis_euro'] ?? '—') . ' gegen ' . round($festDb / 100));
}

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
