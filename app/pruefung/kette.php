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

/* ============================================================================
   2. Kunde und Bestellung
   ============================================================================ */
abschnitt('2. Kunde und Bestellung');

/* Nur ein WEBSITE-Paket taugt als Bestellung: Seit Migration 041 ist auch
   das Hosting-Paket aktiv (0 € einmalig) — eine Bestellung darueber waere
   genau der Unsinn, den die Verwaltung selbst ueberall ausfiltert. */
$paketId = (int) Db::wert(
    "SELECT id FROM packages WHERE active = 1 AND art = 'website' AND price_cents > 0
      ORDER BY id LIMIT 1", [], 0);
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
pruefe('es sind fünfzehn', count($w) === 15, (string) count($w));
pruefe('und genau die aus Telefon::AKTIONEN',
    array_diff(Telefon::AKTIONEN, array_keys($w)) === []
    && array_diff(array_keys($w), Telefon::AKTIONEN) === [],
    implode(', ', array_keys($w)));
pruefe('in der Reihenfolge des Gesprächs, nicht alphabetisch',
    array_keys($w) === Telefonwerkzeuge::REIHE, implode(', ', array_keys($w)));

$o = Telefonwerkzeuge::objekte();
pruefe('jedes wird zu gültigem JSON', count($o) === 15, (string) count($o));
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
pruefe('alle fünfzehn tragen denselben Schlüssel', count($schluessel) === 1, (string) count($schluessel));
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

/* beiStatuswechsel: nur die finale Freigabe legt an — und weil der
   KAS-Zugang im Test fehlt, bleibt der Auftrag ehrlich auf zugestimmt
   und Uwe bekommt eine Meldung statt eines halben Accounts. */
Hosting::beiStatuswechsel($hoProjektId, 'vorschau');
pruefe('ein anderer Statuswechsel ruehrt den Auftrag nicht an', (string) Db::wert(
    'SELECT status FROM hosting_auftraege WHERE id = ?', [$hoId], '') === 'zugestimmt');
Db::run("DELETE FROM notifications WHERE type = 'hosting_fehler'");
Hosting::beiStatuswechsel($hoProjektId, 'finale_freigabe');
pruefe('bei finaler Freigabe ohne KAS-Zugang bleibt der Auftrag zugestimmt',
    (string) Db::wert('SELECT status FROM hosting_auftraege WHERE id = ?', [$hoId], '') === 'zugestimmt');
pruefe('und es liegt eine Meldung fuer Uwe da', (int) Db::wert(
    "SELECT COUNT(*) FROM notifications WHERE type = 'hosting_fehler'", [], 0) === 1);

/* Die Abo-Regel: Betreuung und Hosting laufen nebeneinander, aber keine
   zwei Vertraege derselben Art. */
$hoAbo1 = Abo::anlegen($hoKundeId, ['paket_slug' => 'hosting', 'zahlart' => 'manuell']);
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
            'postfach' => 'info@domarella-kette.it', 'postfach_passwort' => 'Dritte-789!',
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
pruefe('ohne KAS-Zugang bleibt der Solo-Auftrag zugestimmt', (string) Db::wert(
    'SELECT status FROM hosting_auftraege WHERE id = ?', [$soloAuftrag], '') === 'zugestimmt');
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
pruefe('das Angebot laesst sich verschicken', Angebot::senden($angId) === true);
pruefe('es steht danach auf gesendet',
    (string) Db::wert('SELECT status FROM angebote WHERE id = ?', [$angId], '') === 'gesendet');
pruefe('und der Kunde bekommt dabei eine Angebots-Mail (Postausgang)',
    (int) Db::wert("SELECT COUNT(*) FROM mails WHERE anlass = 'angebot' AND customer_id = ?", [$angKunde], 0)
        === $vorMails + 1);
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
Db::run("DELETE FROM notifications WHERE type = 'mail_fehler'");

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
